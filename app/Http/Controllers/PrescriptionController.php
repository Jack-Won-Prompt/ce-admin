<?php
// app/Http/Controllers/PrescriptionController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\Patient;
use App\Support\ResidentNo;
use App\Models\User;
use App\Models\PrescriptionConsent;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\KakaoService;
use App\Services\Popbill\FaxService as PopbillFaxService;
use App\Services\Popbill\MessageService as PopbillMessageService;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly VirtualAccountService $vaService,
        private readonly KakaoService $kakaoService,
        private readonly PopbillMessageService $smsService,
    ) {}

    // ── 처방전 목록 ───────────────────────────────────────
    public function index(Request $request): View
    {
        $query = Prescription::with(['patient', 'assignedUser', 'creator', 'order'])->latest();

        // '처방전 관리' 로 화면만 열고 아무것도 입력하지 않은 초안은 목록에 띄우지 않는다
        $query->whereNot(fn ($q) => $q->blankDraft());

        if ($request->input('status') === 'no_order') {
            // 주문을 만들 수 있는 것 — 검수가 끝난 것. ocr_done 은 예전 데이터 몫이다.
            $query->whereIn('status', ['approved', 'ocr_done'])
                  ->whereDoesntHave('order');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        /* 처방 유형 — 원내·원외·처방외는 정산 방식과 필요한 서류가 달라 나눠 봐야 한다.
           정산 화면에만 있던 구분을 처방전 목록에서도 고를 수 있게 한다. */
        if ($request->filled('acc_type')) {
            $query->where('counsel_acc_add_type', $request->acc_type);
        }
        $dateFrom = $request->input('date_from') ?: now()->subDays(6)->format('Y-m-d');
        $dateTo   = $request->input('date_to')   ?: now()->format('Y-m-d');
        $query->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
        if ($request->filled('search')) {
            $kw = $request->search;
            $query->where(function ($q) use ($kw) {
                $q->where('rx_number', 'like', "%{$kw}%")
                  ->orWhere('patient_name_ocr', 'like', "%{$kw}%")
                  ->orWhere('hospital_name', 'like', "%{$kw}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$kw}%"));
            });
        }

        $gridData = $query->get()->map(function (Prescription $rx) {
            $order = $rx->order;
            $soType = $order?->so_type;

            return [
                'id'         => $rx->id,
                'rx_number'  => $rx->rx_number,
                'source'     => $rx->upload_source === 'mobile' ? '모바일' : '웹',
                'patient'    => $rx->patient?->name ?? $rx->patient_name_ocr ?? '-',
                'hospital'   => $rx->hospital_name ?? '-',
                'issued'     => $rx->issued_date?->format('Y-m-d') ?? '',
                'status'     => $rx->status_label,
                'acc_type'   => $rx->accTypeLabel(),
                'so_type'    => $soType ? (Order::SO_TYPE_LABELS[$soType][0] ?? $soType) : '-',
                'order_no'   => $order?->order_number ?? '',
                'so_no'      => $order?->withworks_so_no ?? '',
                'assignee'   => $rx->assignedUser?->name ?? '미지정',
                'created'    => $rx->created_at?->format('Y-m-d H:i') ?? '',
            ];
        });
        $total = $gridData->count();

        $statusCounts = [
            'all'            => Prescription::count(),
            'review_needed'  => Prescription::where('status', 'review_needed')->count(),
            'review_requested' => Prescription::where('status', 'review_requested')->count(),
            'approved'       => Prescription::where('status', 'approved')->count(),
            'no_order'       => Prescription::whereIn('status', ['approved', 'ocr_done'])->whereDoesntHave('order')->count(),
            'ordered'        => Prescription::where('status', 'ordered')->count(),
            'rejected'       => Prescription::where('status', 'rejected')->count(),
        ];

        // 유형별 건수 — 목록의 유형 칩에 붙는다
        $accCounts = [];
        foreach (Prescription::ACC_TYPES as $code => $label) {
            $accCounts[$code] = Prescription::where('counsel_acc_add_type', $code)->count();
        }

        $managers = User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get();

        return view('prescriptions.list', compact('gridData', 'total', 'statusCounts', 'managers', 'accCounts'));
    }

    // ── 담당자 지정 (AJAX) ────────────────────────────────
    public function assignUser(Request $request, Prescription $prescription)
    {
        $request->validate([
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $prescription->update(['assigned_user_id' => $request->assigned_user_id ?: null]);

        $user = $request->assigned_user_id ? User::find($request->assigned_user_id) : null;

        // 담당자 배정 시 해당 담당자에게 채팅 알림 발송
        if ($user && $user->id !== Auth::id()) {
            try {
                $me = Auth::id();

                // 1:1 채팅방 조회 or 생성
                $room = ChatRoom::where('type', 'direct')
                    ->whereHas('users', fn($q) => $q->where('user_id', $me))
                    ->whereHas('users', fn($q) => $q->where('user_id', $user->id))
                    ->first();

                if (!$room) {
                    $room = ChatRoom::create(['type' => 'direct']);
                    $room->users()->attach([$me, $user->id]);
                }

                $patientName = $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '환자';
                $body = "📋 처방전 담당자로 배정되었습니다.\n"
                    . "· 처방번호: {$prescription->rx_number}\n"
                    . "· 환자: {$patientName}\n"
                    . "· 병원: " . ($prescription->hospital_name ?? '-');

                $message = ChatMessage::create([
                    'chat_room_id' => $room->id,
                    'user_id'      => $me,
                    'body'         => $body,
                ]);

                $room->users()->updateExistingPivot($me, ['last_read_at' => now()]);

                broadcast(new ChatMessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                \Log::warning('담당자 배정 채팅 알림 실패', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'name'    => $user?->name ?? '-',
        ]);
    }

    // ── Withworks 판매주문 연계 ────────────────────────────
    public function createWithworksOrder(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'order_number'     => 'required|string',
            'items'            => 'required|array|min:1',
            'items.*.item_code'  => 'required|string',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string',
            'delivery_date'    => 'nullable|date',
            'so_type'          => ['nullable', 'string', Rule::in(Order::saleSoTypes())],
        ]);

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        if (!$baseUrl || !$token) {
            return response()->json(['success' => false, 'message' => 'Withworks API 설정이 없습니다.'], 500);
        }

        $patient = $prescription->patient;

        // 배송지: 요청값 우선, 없으면 처방전 주소 조합
        $shippingAddress = $request->shipping_address
            ?? trim(($prescription->postcode ? '' : '') . ($prescription->address_detail ?? ''))
            ?: null;

        // 배송지 상세: 요청값 우선, 없으면 처방전 저장값
        $shippingAddressDetail = $request->shipping_address_detail
            ?? $prescription->address_detail
            ?? null;

        $payload = [
            'ce_order_number'         => $request->order_number,
            'rx_number'               => $prescription->rx_number,
            // 환자 정보 (거래처·배송지 자동 등록용)
            'patient_name'            => $patient?->name ?? $prescription->patient_name_ocr ?? '환자',
            'patient_mobile'          => $patient?->mobile ?? null,
            'patient_zipcode'         => $prescription->postcode ?? null,
            // 배송지
            'shipping_address'        => $shippingAddress,
            'shipping_address_detail' => $shippingAddressDetail,
            // 기타
            'delivery_date'           => $request->delivery_date,
            // 콜로플라스트 거래처 id — 테스트와 운영이 다르다(설정 화면에서 관리)
            'ho_account_id'           => $request->ho_account_id ?? config('services.demoworks.account_id'),
            'remark'                  => $prescription->admin_note,
            'items'                   => $request->items,
            /* 판매 유형 — 위드웍스와는 End User Direct 로만 주고받는다. 다른 유형으로
               넘기면 저쪽 콜백 대상에서 빠져 진행 상태를 영영 못 받는다. */
            'so_type'                 => config('services.demoworks.so_type', '5001'),
            // 받는 사람
            'recipient_name'          => $request->recipient_name ?? $prescription->order?->shipping_recipient ?? null,
            // 청구전략
            'billing_strategy'        => 25,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asForm()
                ->post("{$baseUrl}/api/v1/ce-admin/so_store", $payload);

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                $result = $body['result'] ?? [];
                $soNo   = $result['so_no'] ?? null;

                // 주문에 Withworks SO번호/ID 기록
                if ($prescription->order) {
                    $updateData = [];
                    if ($soNo)                          $updateData['withworks_so_no'] = $soNo;
                    if ($result['so_id'] ?? null)       $updateData['withworks_so_id'] = $result['so_id'];
                    if (!empty($updateData)) {
                        try { $prescription->order->update($updateData); } catch (\Throwable) {}
                    }
                }

                activity()->causedBy(Auth::user())->performedOn($prescription)
                    ->log("Withworks 판매주문 연계: {$soNo}");

                $accountNew  = $result['patient_account_new'] ?? false;
                $addressNew  = $result['patient_address_new'] ?? false;

                $detail = [];
                if ($accountNew) $detail[] = '환자 거래처 신규 등록';
                if ($addressNew) $detail[] = '배송지 신규 등록';

                return response()->json([
                    'success' => true,
                    'so_no'   => $soNo,
                    'message' => 'Withworks 판매주문이 생성되었습니다.' . ($detail ? ' (' . implode(', ', $detail) . ')' : ''),
                    'patient_account_id' => $result['patient_account_id'] ?? null,
                    'patient_address_id' => $result['patient_address_id'] ?? null,
                ]);
            }

            $errMsg = $body['message'] ?? "HTTP {$response->status()}";
            Log::warning('Withworks SO 생성 실패', [
                'status' => $response->status(),
                'body'   => $body,
                'raw'    => substr($response->body(), 0, 500),
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json(['success' => false, 'message' => "Withworks 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Withworks 서버에 연결할 수 없습니다.'], 500);
        }
    }

    // ── Withworks 판매주문 수정 연계 ──────────────────────
    public function updateWithworksOrder(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'order_number'     => 'required|string',
            'items'            => 'required|array|min:1',
            'items.*.item_code'  => 'required|string',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string',
            'delivery_date'    => 'nullable|date',
            'so_type'          => ['nullable', 'string', Rule::in(Order::saleSoTypes())],
        ]);

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        if (!$baseUrl || !$token) {
            return response()->json(['success' => false, 'message' => 'Withworks API 설정이 없습니다.'], 500);
        }

        $patient = $prescription->patient;
        $shippingAddress = $request->shipping_address ?? null;
        $shippingAddressDetail = $request->shipping_address_detail ?? $prescription->address_detail ?? null;

        $payload = [
            'ce_order_number'         => $request->order_number,
            'patient_name'            => $patient?->name ?? $prescription->patient_name_ocr ?? '환자',
            'patient_mobile'          => $patient?->mobile ?? null,
            'patient_zipcode'         => $prescription->postcode ?? null,
            'shipping_address'        => $shippingAddress,
            'shipping_address_detail' => $shippingAddressDetail,
            'delivery_date'           => $request->delivery_date,
            'items'                   => $request->items,
            // 등록과 같은 이유로 수정 때도 End User Direct 로 고정한다
            'so_type'                 => config('services.demoworks.so_type', '5001'),
            'recipient_name'          => $request->recipient_name ?? $prescription->order?->shipping_recipient ?? null,
            'billing_strategy'        => 25,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asForm()
                ->put("{$baseUrl}/api/v1/ce-admin/so_update", $payload);

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                $soNo = $body['result']['so_no'] ?? '-';
                activity()->causedBy(Auth::user())->performedOn($prescription)
                    ->log("Withworks 판매주문 수정: {$soNo}");

                return response()->json([
                    'success' => true,
                    'so_no'   => $body['result']['so_no'] ?? null,
                    'message' => 'Withworks 판매주문이 수정되었습니다.',
                ]);
            }

            $errMsg = $body['message'] ?? "HTTP {$response->status()}";
            Log::warning('Withworks SO 수정 실패', ['status' => $response->status(), 'body' => $body]);
            return response()->json(['success' => false, 'message' => "Withworks 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류 (수정)', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Withworks 서버에 연결할 수 없습니다.'], 500);
        }
    }

    // ── Withworks 판매주문 삭제 연계 ──────────────────────
    public function deleteWithworksOrder(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string',
        ]);

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        if (!$baseUrl || !$token) {
            return response()->json(['success' => false, 'message' => 'Withworks API 설정이 없습니다.'], 500);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asForm()
                ->delete("{$baseUrl}/api/v1/ce-admin/so_delete", [
                    'ce_order_number' => $request->order_number,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                activity()->causedBy(Auth::user())->performedOn($prescription)
                    ->log("Withworks 판매주문 삭제: {$request->order_number}");

                return response()->json(['success' => true, 'message' => 'Withworks 판매주문이 삭제되었습니다.']);
            }

            $errMsg = $body['message'] ?? "HTTP {$response->status()}";
            Log::warning('Withworks SO 삭제 실패', ['status' => $response->status(), 'body' => $body]);
            return response()->json(['success' => false, 'message' => "Withworks 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류 (삭제)', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Withworks 서버에 연결할 수 없습니다.'], 500);
        }
    }

    // ── 업로드 페이지 ─────────────────────────────────────
    public function uploadPage(Request $request): View
    {
        $prescriptions = Prescription::with(['patient', 'assignedUser'])->latest()->limit(5)->get();
        $managers      = User::where('role', 'manager')->get();
        // 화면으로 나가는 목록이므로 마스킹 컬럼만 읽는다 — 평문·암호문은 조회하지 않는다(P0-1)
        $patientsJson  = self::patientPickerList();

        $mobilePending = Prescription::where('upload_source', 'mobile')
            ->whereIn('status', ['pending', 'ocr_processing', 'ocr_done', 'review_needed', 'review_requested'])
            ->latest()->take(5)->get();

        // 화면 상단에 알리는 검수 대기 건수 (시안 128:3171)
        $reviewPending = Prescription::where('status', 'review_needed')->count();

        /* 서류명은 환경 설정에서 정한다 — 화면에 박아 두면 한 줄 늘리는 데도 배포가 필요했다.
           갈래로 나누어 보낸다 — 처방 서류 자리와 청구·기타 자리에서 고를 수 있는 것이 다르다. */
        $docTypes = [];
        foreach (['rx', 'claim', 'etc'] as $kind) {
            $docTypes[$kind] = \App\Models\CommonCode::options('doc_type', $kind)
                ->map(fn ($c) => ['code' => $c->code, 'label' => $c->label])->values()->all();
        }

        return view('prescriptions.upload', compact('prescriptions', 'managers', 'mobilePending',
                                                    'patientsJson', 'reviewPending', 'docTypes'));
    }

    /**
     * 빈 검수·등록 화면 (메뉴 '처방전 관리').
     *
     * 검수 화면은 저장된 처방전 1건 위에서 동작한다(승인·팩스·서류 URL 이 모두
     * 레코드를 필요로 한다). 그래서 화면을 열 때 빈 초안을 한 건 잡아 두고
     * 거기에 입력하게 한다. 저장하면 그 초안이 곧 새 처방전이 된다.
     *
     * 메뉴를 여러 번 눌러도 초안이 쌓이지 않도록, 아직 아무것도 입력하지 않은
     * 내 초안이 있으면 그것을 재사용한다.
     */
    public function create(Request $request): RedirectResponse
    {
        $draft = Prescription::blankDraftsOf(Auth::id())->latest()->first();

        if ($draft) {
            /* 아직 아무것도 안 적힌 초안은 다시 쓴다 — 누를 때마다 빈 행이 쌓이면 목록이 지저분해진다.
               다만 며칠 전 것이라면 번호와 접수일을 오늘 것으로 새로 매긴다. 그대로 두면
               처방번호에 박힌 날짜가 실제 접수일과 어긋난다(RX-20260807-001 을 8/15 에 접수).
               빈 초안이라 되돌아볼 내용이 없으므로 접수일을 옮겨도 잃는 것이 없다.
               saveQuietly — 저장 훅이 '내용이 생겼다'고 보고 초안 표시를 풀어 버린다. */
            if (!$draft->created_at->isToday()) {
                $draft->forceFill([
                    'rx_number'  => Prescription::generateRxNumber(),
                    'created_at' => now(),
                ])->saveQuietly();
            }
        } else {
            $draft = Prescription::create([
                'rx_number'      => Prescription::generateRxNumber(),
                'created_by'     => Auth::id(),
                'status'         => 'pending',
                'upload_source'  => 'web',
                'is_blank_draft' => true,
            ]);
        }

        /* 누구의 상담인지 정해 놓고 들어오는 길이 있다(거래처 관리의 「상담하기」).
           초안에 그 환자를 미리 붙여 두면 이름·연락처를 다시 치지 않아도 된다. */
        if ($request->filled('patient')) {
            $patient = \App\Models\Patient::find($request->patient);

            if ($patient && (!$draft->patient_id || $draft->patient_id === $patient->id)) {
                $draft->forceFill([
                    'patient_id'       => $patient->id,
                    'patient_name_ocr' => $draft->patient_name_ocr ?: $patient->name,
                    'mobile_ocr'       => $draft->mobile_ocr ?: ($patient->mobile ?: $patient->phone),
                ])->saveQuietly();
            }
        }

        // 팝업으로 열었으면 그 표시를 이어 준다 — 상담 창은 아래에 저장·닫기 띠를 세운다
        return redirect()->route('prescriptions.show', array_filter([
            'prescription' => $draft->rx_number,
            'popup'        => $request->boolean('popup') ? 1 : null,
        ]));
    }

    // ── 웹에서 직접 업로드 ────────────────────────────────
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'prescription_images'   => 'required|array|max:40',
            'prescription_images.*' => 'file|mimes:jpg,jpeg,png,pdf,heic|max:50240',
            'file_doc_types'        => 'nullable|array',
            'file_doc_types.*'      => ['nullable', 'string',
                                        \Illuminate\Validation\Rule::in(\App\Models\CommonCode::codes('doc_type'))],
            // 누구의 처방인지 모르는 채로는 받지 않는다 — 나중에 잇는 일이 더 비싸다
            'patient_id'            => 'required|exists:patients,id',
            'assigned_user_id'      => 'nullable|exists:users,id',
            'admin_note'            => 'nullable|string|max:500',
        ], [
            'patient_id.required' => '환자를 먼저 고르십시오.',
        ]);

        $docTypes = $request->input('file_doc_types', []);

        // 처방전 파일과 첨부 파일 분리
        $prescriptionFiles = [];
        $attachmentFiles   = [];
        foreach ($request->file('prescription_images') as $i => $file) {
            $type = $docTypes[$i] ?? 'prescription';
            if ($type === 'prescription') {
                $prescriptionFiles[] = $file;
            } else {
                $attachmentFiles[] = ['file' => $file, 'doc_type' => $type];
            }
        }

        if (empty($prescriptionFiles)) {
            return back()->with('error', '처방전 파일을 최소 1개 이상 포함해야 합니다.');
        }

        $created         = [];
        $firstPrescription = null;

        foreach ($prescriptionFiles as $file) {
            $subDir   = 'prescriptions/' . now()->format('Y/m');
            $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($subDir, $fileName, 'public');

            $prescription = Prescription::create([
                'rx_number'           => Prescription::generateRxNumber(),
                'patient_id'          => $request->patient_id ?: null,
                'assigned_user_id'    => $request->assigned_user_id,
                'created_by'          => Auth::id(),
                'admin_note'          => $request->admin_note,
                'image_path'          => $path,
                'image_original_name' => $file->getClientOriginalName(),
                'image_mime_type'     => $file->getMimeType(),
                'image_size'          => $file->getSize(),
                'upload_source'       => 'web',
                // OCR 은 쓰지 않는다 — 올리면 곧장 검수 필요로 두고 담당자가 손으로 적는다
                'status'              => 'review_needed',
            ]);

            if (!$firstPrescription) {
                $firstPrescription = $prescription;
            }

            /* 예전에는 여기서 OCR 을 돌려 환자명ㆍ병원ㆍ상병 따위를 채우고,
               신뢰도 85 를 기준으로 OCR 완료 / 검수 필요를 갈랐다. 이제 쓰지 않는다 —
               숫자가 무엇을 뜻하는지 사람마다 달리 읽었고, 높든 낮든 어차피 눈으로 보고
               고쳤다. 올리면 검수 필요로 두고 담당자가 처음부터 손으로 적는다.
               환자 자동 연결도 OCR 이 읽은 이름에 기대던 것이라 함께 걷었다 —
               올릴 때 환자를 고르면 그 값(patient_id)이 그대로 들어간다. */

            $prescription->update([
                'counsel_no'   => Prescription::generateCounselNo(),
                'counsel_date' => now()->format('Y-m-d'),
            ]);

            $created[] = $prescription->rx_number;

            activity()->causedBy(Auth::user())->performedOn($prescription)
                      ->log("{$prescription->rx_number} 업로드 완료 (웹)");
        }

        // 첨부 파일 처리 (첫 번째 처방전에 연결)
        if ($firstPrescription && !empty($attachmentFiles)) {
            foreach ($attachmentFiles as $order => $item) {
                $file    = $item['file'];
                $subDir  = 'prescriptions/attachments/' . now()->format('Y/m');
                $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path    = $file->storeAs($subDir, $fileName, 'public');

                PrescriptionAttachment::create([
                    'prescription_id'    => $firstPrescription->id,
                    'file_path'          => $path,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type'     => $file->getMimeType(),
                    'file_size'          => $file->getSize(),
                    'doc_type'           => $item['doc_type'],
                    'doc_label'          => PrescriptionAttachment::labelFor($item['doc_type']),
                    'ocr_raw_text'       => null,
                    'ocr_confidence'     => 0,
                    'display_order'      => $order,
                    'uploaded_by'        => Auth::id(),
                ]);
            }
        }

        /* 화면 안에서 부른 것이면 어디로 갈지는 화면이 정한다 — 올린 자리는 그대로 두고
           주문 등록 화면만 새 화면 탭으로 연다. 곧바로 옮겨 가면 여러 건을 잇달아 올릴 때
           매번 되돌아와야 했다. */
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'rx_number' => $firstPrescription?->rx_number,
                'created'   => $created,
                'url'       => $firstPrescription
                                 ? route('prescriptions.show', $firstPrescription)
                                 : route('prescriptions.index'),
                'message'   => count($created) === 1
                                 ? "{$firstPrescription->rx_number} 업로드 완료"
                                 : count($created) . '개 처방전 업로드 완료: ' . implode(', ', $created),
            ]);
        }

        if (count($created) === 1) {
            return redirect()->route('prescriptions.show', $firstPrescription)
                ->with('success', "{$firstPrescription->rx_number} 업로드 완료");
        }

        return redirect()->route('prescriptions.index')
            ->with('success', count($created) . '개 처방전 업로드 완료: ' . implode(', ', $created));
    }

    // ── 첨부 파일 삭제 ────────────────────────────────────
    public function storeAttachment(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file'      => 'required|file|mimes:jpg,jpeg,png,pdf,heic|max:51200',
            'doc_type'  => ['required', 'string',
                            \Illuminate\Validation\Rule::in(\App\Models\CommonCode::codes('doc_type'))],
            'doc_label' => 'nullable|string|max:50',
        ]);

        $file     = $request->file('file');
        $subDir   = 'prescriptions/attachments/' . now()->format('Y/m');
        $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs($subDir, $fileName, 'public');

        $maxOrder = $prescription->attachments()->max('display_order') ?? -1;

        $att = PrescriptionAttachment::create([
            'prescription_id'    => $prescription->id,
            'file_path'          => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type'     => $file->getMimeType(),
            'file_size'          => $file->getSize(),
            'doc_type'           => $request->doc_type,
            'doc_label'          => ($request->doc_type === 'other' && $request->filled('doc_label'))
                                        ? $request->doc_label
                                        : PrescriptionAttachment::labelFor($request->doc_type),
            'display_order'      => $maxOrder + 1,
            'uploaded_by'        => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'attachment' => [
                'id'        => $att->id,
                'url'       => $att->file_url,
                'type'      => $att->doc_type,
                'typeLabel' => $att->doc_type_label,
                'name'      => $att->file_original_name,
                'isPdf'     => $att->is_pdf,
            ],
        ]);
    }

    public function destroyAttachment(Prescription $prescription, PrescriptionAttachment $attachment): \Illuminate\Http\JsonResponse
    {
        if ($attachment->prescription_id !== $prescription->id) {
            abort(403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['success' => true]);
    }

    /**
     * 상담 이력 1건을 화면(검수 화면 이전상담 모달 · 환자 조회 모달)에서 쓰는 배열로 직렬화.
     * 예전에는 counseling_data JSON 을 그대로 실었다. 지금은 컬럼에서 꺼낸다.
     */
    /**
     * 컬럼에 있는 상담·부가 항목을 화면이 쓰는 키 이름으로 내보낸다.
     *
     * 값은 모두 컬럼에서 나온다. 키 이름만 옛 것을 쓰는 이유는 검수 화면 JS 가 그 이름으로
     * 받고 있어서다 — 이름까지 한꺼번에 바꾸면 화면 전체를 같이 고쳐야 한다.
     */
    private function counselingColumns(Prescription $p): array
    {
        $pt = $p->patient;

        return array_filter([
            'counselling_no'     => $p->counsel_no,
            'counsel_date'       => $p->counsel_date,
            'type'               => $p->counsel_type,
            'acc_add_type'       => $p->counsel_acc_add_type,
            'status'             => $p->counsel_status,
            'call_no'            => $p->counsel_call_no,
            're_counsel_date'    => $p->counsel_re_date,
            'contents'           => $p->counsel_contents,
            'erp_cd9'            => $p->hospital_code,
            'udf13'              => $p->rx_use_period,
            'udf14'              => $p->rx_end_date,
            'udf2'               => $p->diagnosis_date,
            'udf3'               => $p->disease_class,
            'udf7'               => $p->uro_date,
            'udf11'              => $p->benefit_class,
            'udf17'              => $p->purchase_type,
            'udf18'              => $p->special_case,
            'udf20'              => $p->reason,
            'udf24'              => $p->caregiver_name,
            'udf25'              => $p->order_manager,
            'udf30'              => $p->next_repurchase,
            'five_program'       => $p->five_program,
            'five'               => $p->five_110days,
            'daily_use_qty'      => $p->daily_use_qty,
            'diverticulums'      => $p->diverticulums,
            'dealer_type'        => $p->dealer_type,
            'pay_date'           => $p->pay_date,
            'buy_date'           => $p->buy_date,
            'inmarket_due'       => $p->inmarket_due,
            'last_confirmed_qty' => $p->last_confirmed_qty,
            // 환자에 붙은 값
            'email'              => $pt?->email,
            'mobile2'            => $pt?->phone,
            'udf6'               => $pt?->sb_sci,
            'udf19'              => $pt?->nhis_reg_status,
            'udf4'               => $pt?->nhis_renew,
            'udf22'              => $pt?->deduction,
            'udf23'              => $pt?->cash_receipt_no,
            'udf32'              => $pt?->new_patient_date,
            'udf42'              => $pt?->nhis_agree_start,
            'udf43'              => $pt?->nhis_agree_end,
            'nhis_reg_date'      => $pt?->nhis_reg_date,
            'nhis_renew_due'     => $pt?->nhis_renew_due,
            'basic_reeval'       => $pt?->basic_reeval,
            'basic_reeval_due'   => $pt?->basic_reeval_due,
            'guardian_name'      => $pt?->guardian_name,
            'guardian_relation'  => $pt?->guardian_relation,
            'guardian_birth'     => $pt?->guardian_birth_date,
            'guardian_phone'     => $pt?->guardian_phone,
        ], fn ($v) => $v !== null && $v !== '');
    }
    private function counselingPayload(Prescription $p): array
    {
        return array_merge($this->counselingColumns($p), [
            'rx_number'          => $p->rx_number,
            'rx_status'          => $p->status,
            'rx_status_label'    => $p->status_label,
            'reg_date'           => $p->created_at->format('Y-m-d'),
            'patient_name_ocr'   => $p->patient_name_ocr,
            'mobile_ocr'         => $p->mobile_ocr,
            'resident_no_masked' => $p->masked_resident_no_ocr,
            'address_ocr'        => $p->address_ocr,
            'postcode'           => $p->postcode,
            'address_detail'     => $p->address_detail,
            'hospital_name'      => $p->hospital_name,
            'doctor_name'        => $p->doctor_name,
            'issued_date'        => $p->issued_date?->format('Y-m-d'),
            'repurchase_date'    => $p->repurchase_date?->format('Y-m-d'),
            'items'              => $p->items->map(function ($i) {
                return [
                    'product_name'    => $i->product_name,
                    'product_code'    => $i->product_code,
                    'quantity'        => $i->quantity,
                    'product_price'   => $i->product_price,
                    'insurance_price' => $i->insurance_price,
                    'nhis_status'     => $i->nhis_status,
                    'nhis_amount'     => $i->nhis_amount,
                    'patient_copay'   => $i->patient_copay,
                ];
            })->toArray(),
            'order' => $p->order ? [
                'order_number'      => $p->order->order_number,
                'so_type'           => $p->order->so_type,
                'status'            => $p->order->status,
                'status_label'      => $p->order->status_label,
                'total_amount'      => $p->order->total_amount,
                'patient_copay'     => $p->order->patient_copay,
                'shipping_fee'      => $p->order->shipping_fee,
                'withworks_so_no'   => $p->order->withworks_so_no,
                'created_at'        => $p->order->created_at->format('Y-m-d'),
                // 현금영수증
                'cash_receipt_status'     => $p->order->cash_receipt_status,
                'cash_receipt_no'         => $p->order->cash_receipt_no,
                'cash_receipt_type'       => $p->order->cash_receipt_type,
                'cash_receipt_amount'     => $p->order->cash_receipt_amount,
                'cash_receipt_issued_at'  => $p->order->cash_receipt_issued_at?->format('Y-m-d H:i'),
                // 가상계좌
                'toss' => $p->order->tossPayment ? [
                    'method'         => $p->order->tossPayment->method,
                    'status'         => $p->order->tossPayment->status,
                    'status_label'   => $p->order->tossPayment->status_label,
                    'bank'           => $p->order->tossPayment->bank_name,
                    'account_number' => $p->order->tossPayment->account_number,
                    'customer_name'  => $p->order->tossPayment->customer_name,
                    'amount'         => $p->order->tossPayment->amount,
                    'due_date'       => $p->order->tossPayment->due_date?->format('Y-m-d H:i'),
                    'deposited_at'   => $p->order->tossPayment->deposited_at?->format('Y-m-d H:i'),
                    'is_done'        => $p->order->tossPayment->is_done,
                    'is_expired'     => $p->order->tossPayment->is_expired,
                ] : null,
            ] : null,
            // 위임동의
            'consents' => $p->consents->map(fn($c) => [
                'status'       => $c->status,
                'status_label' => $c->statusLabel(),
                'responded_at' => $c->responded_at?->format('Y-m-d H:i'),
                'expires_at'   => $c->expires_at?->format('Y-m-d H:i'),
                'patient_name' => $c->patient_name,
                'pdf_path'     => $c->pdf_path ? \Storage::disk('public')->url($c->pdf_path) : null,
            ])->values()->toArray(),
            // 팩스 이력
            'fax_histories' => $p->faxHistories->map(fn($f) => [
                'fax_no'          => $f->fax_no,
                'recipient_type'  => $f->recipient_type,
                'popbill_state'   => $f->popbill_state,
                'popbill_result'  => $f->popbill_result,
                'reserve_dt'      => $f->reserve_dt,
                'synced_at'       => $f->synced_at?->format('Y-m-d H:i'),
                'sent_by_name'    => $f->sentBy?->name,
                'title'           => $f->title,
            ])->values()->toArray(),
        ]);
    }

    /**
     * 검수 화면 '환자 조회': 이름/연락처로 환자 검색 (상담이력 건수 포함).
     */
    /** 주민번호 마스킹(820108-1******)에서 생년월일을 읽는다 — 못 읽으면 빈 값 */
    /**
     * 「이름 조회」 창이 쓰는 사람 목록. 업로드 화면과 주문 등록 화면이 같은 창을 쓰므로
     * 만드는 자리도 하나로 둔다. 화면으로 나가는 목록이라 마스킹 컬럼만 읽는다 —
     * 평문ㆍ암호문은 조회하지 않는다(P0-1).
     */
    private static function patientPickerList(): \Illuminate\Support\Collection
    {
        return \App\Models\Patient::orderBy('name')
            ->get(['id', 'name', 'mobile', 'phone', 'birth_date', 'resident_no_masked'])
            ->map(fn ($p) => [
                'id'     => $p->id,
                'name'   => $p->name,
                'mobile' => $p->mobile ? preg_replace('/(\d{3})(\d{3,4})(\d{4})/', '$1-$2-$3', $p->mobile) : '',
                'phone'  => $p->phone ?: '',
                /* 생년월일로도 찾는다 — 같은 이름이 여럿일 때 이것으로 가른다.
                   birth_date 칸은 지금 어느 환자도 채워 두지 않아, 비어 있으면 주민번호
                   앞자리에서 읽는다(뒷자리 첫 숫자가 1900ㆍ2000 년대를 가른다). */
                'birth'  => $p->birth_date?->format('Y-m-d') ?: self::birthFromMasked($p->resident_no_masked),
                'rn'     => $p->resident_no_masked ? substr($p->resident_no_masked, 0, 6) . '-*' : '',
            ])->values();
    }

    private static function birthFromMasked(?string $masked): string
    {
        if (!$masked || !preg_match('/^(\d{2})(\d{2})(\d{2})-([0-9])/', $masked, $m)) {
            return '';
        }

        $century = in_array($m[4], ['1', '2', '5', '6'], true) ? 19 : 20;
        $date    = sprintf('%d%s-%s-%s', $century, $m[1], $m[2], $m[3]);

        return checkdate((int) $m[2], (int) $m[3], (int) ($century . $m[1])) ? $date : '';
    }

    /**
     * 「조회」로 고른 사람이 지금까지 만든 건들.
     *
     * 사람을 고른 다음 물어야 할 것이 하나 더 있다 — 「이번이 새 건인가, 하던 건인가」.
     * 같은 사람이 여러 번 사고, 적다 만 건이 남아 있기도 하다. 그것을 묻지 않고 늘 새
     * 건으로 두면 하던 건이 둘로 갈라진다.
     *
     * 처방전 한 건이 곧 주문 등록 한 건이다. 주문까지 간 건은 주문번호를 함께 적고,
     * 처방 없이 주문만 있는 건도 빠뜨리지 않는다(처방외로 산 것) — 다만 그것은 이
     * 화면이 고칠 수 있는 건이 아니라 주문 상세로 보낸다.
     */
    public function patientCases(Patient $patient): JsonResponse
    {
        $rows = [];

        $rx = $patient->prescriptions()->with(['order', 'items'])->latest('id')->take(50)->get();
        foreach ($rx as $p) {
            $rows[] = [
                'key'       => $p->rx_number,
                'rx_number' => $p->rx_number,
                'order_no'  => $p->order?->order_number ?: '',
                'date'      => ($p->issued_date ?: $p->created_at)?->format('Y-m-d') ?? '',
                'kind'      => $p->order ? '주문' : '작성 중',
                'product'   => $this->caseProductLabel($p),
                'amount'    => (int) ($p->order?->total_amount ?? 0),
                'status'    => $p->status_label,
                /* 같은 자리(호스트) 안에서만 오간다. 절대 주소로 두면 APP_URL 이 가리키는
                   곳으로 건너뛰어, 로컬에서 열었는데 운영 화면이 뜨는 일이 생긴다. */
                'url'       => route('prescriptions.show', $p, absolute: false),
                'here'      => true,   // 이 화면에서 이어서 고칠 수 있는 건
            ];
        }

        // 처방전 없이 주문만 있는 건
        $seen = $rx->pluck('order.id')->filter()->all();
        foreach ($patient->orders()->latest('id')->take(50)->get() as $o) {
            if ($o->prescription_id || in_array($o->id, $seen, true)) {
                continue;
            }
            $rows[] = [
                'key'       => 'ORD:' . $o->id,
                'rx_number' => '',
                'order_no'  => $o->order_number,
                'date'      => $o->created_at?->format('Y-m-d') ?? '',
                'kind'      => '처방 없음',
                'product'   => $o->product_name ?: '-',
                'amount'    => (int) $o->total_amount,
                'status'    => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                'url'       => route('orders.show', $o, absolute: false),
                'here'      => false,  // 주문 상세로 보낸다
            ];
        }

        // 최근 것이 위로
        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return response()->json([
            'success' => true,
            'patient' => ['id' => $patient->id, 'name' => $patient->name],
            'cases'   => $rows,
        ]);
    }

    /** 건 한 줄에 적을 제품 이름 — 여러 개면 「첫 제품 외 N」 */
    private function caseProductLabel(Prescription $p): string
    {
        $names = $p->items->pluck('product_name')->filter()->values();
        if ($names->isEmpty()) {
            return '-';
        }

        return $names->count() === 1 ? $names[0] : $names[0] . ' 외 ' . ($names->count() - 1);
    }

    /**
     * 「조회」로 고른 사람의 상담ㆍ환자 정보 한 벌.
     *
     * 예전에는 이름만 채워 넣었다. 그러면 담당자가 이미 마스터에 적혀 있는 전화번호ㆍ주소ㆍ
     * 공단 등록일을 처방전마다 다시 옮겨 적어야 했고, 옮겨 적다 어긋나면 어느 쪽이 맞는지
     * 알 수 없었다. 고른 순간 그 사람이 가진 것을 전부 가져오고, 담당자는 처방전에만
     * 적힌 것(병원ㆍ상병ㆍ수량ㆍ기간)을 채운다.
     *
     * 돌려주는 값의 열쇠는 화면의 입력칸 id 다 — 화면이 다시 짝을 맞출 일이 없다.
     */
    public function patientDetail(Patient $patient): JsonResponse
    {
        /* 날짜 칸이라고 다 Carbon 으로 오지 않는다 — 캐스트가 걸린 것과 문자열로
           그냥 담긴 것이 섞여 있다. 어느 쪽이 와도 같은 모양으로 내보낸다. */
        $d = function ($v) {
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d');
            }
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($v)->format('Y-m-d');
            } catch (\Throwable) {
                return $v;   // 「대상 아님」 같은 메모가 적혀 있을 수도 있다
            }
        };

        /* 주민번호는 가린 것만 내보낸다. 원문을 화면으로 되돌리지 않는다(P0-1) —
           화면은 이 가린 값을 그대로 들고 있다가, 손대지 않았으면 저장할 때 보내지 않는다. */
        $masked = $patient->masked_resident_no;

        $fill = [
            'f-name'              => $patient->name,
            'f-resident'          => $masked,
            'f-birth'             => $d($patient->birth_date),
            'f-sb-sci'            => $patient->sb_sci,
            'f-mobile'            => $patient->mobile,
            'f-mobile2'           => $patient->phone,
            'f-postcode'          => $patient->postcode,
            'f-address'           => $patient->address,
            'f-address-detail'    => $patient->address_detail,
            'f-email'             => $patient->email,
            'f-deduction'         => $patient->deduction,
            'f-cash-receipt'      => $patient->cash_receipt_no,
            'f-nhis-status'       => $patient->nhis_reg_status,
            'f-nhis-reg-date'     => $d($patient->nhis_reg_date),
            'f-nhis-renew'        => $patient->nhis_renew,
            'f-nhis-renew-due'    => $d($patient->nhis_renew_due),
            'f-basic-reeval'      => $patient->basic_reeval,
            'f-basic-reeval-due'  => $d($patient->basic_reeval_due),
            'f-new-patient-date'  => $d($patient->new_patient_date),
            // 위임동의 기간 — 병원ㆍ처방 정보 쪽에 있지만 값은 이 사람의 것이다
            'f-nhis-agree-start'  => $d($patient->nhis_agree_start),
            'f-nhis-agree-end'    => $d($patient->nhis_agree_end),
            // 미성년 법정대리인
            'f-guardian-name'     => $patient->guardian_name,
            'f-guardian-relation' => $patient->guardian_relation,
            'f-guardian-birth'    => $d($patient->guardian_birth_date),
            'f-guardian-phone'    => $patient->guardian_phone,
        ];

        return response()->json([
            'success'         => true,
            'id'              => $patient->id,
            'name'            => $patient->name,
            'resident_masked' => $masked,
            'fill'            => array_map(fn ($v) => $v === null ? '' : (string) $v, $fill),
            'consent'         => $this->latestConsentState($patient),
        ]);
    }

    /**
     * 이 사람의 가장 최근 위임동의 상태.
     *
     * 동의는 처방전에 달리지만 사람에게 묶어 읽는다 — 지난 처방전에서 이미 서명을 받았다면
     * 새 처방전에서도 「위임동의 완료」로 보여야 한다. 만료된 것은 만료로 적는다.
     */
    private function latestConsentState(Patient $patient): ?array
    {
        $c = \App\Models\PrescriptionConsent::whereIn(
                'prescription_id',
                Prescription::where('patient_id', $patient->id)->select('id')
            )
            ->orderByDesc('responded_at')->orderByDesc('id')
            ->first();

        if (!$c) {
            return null;
        }

        $status = $c->status;
        // 답이 없는 채로 기한이 지났으면 대기중이 아니라 만료다
        if ($status === 'pending' && $c->expires_at && $c->expires_at->isPast()) {
            $status = 'expired';
        }

        return [
            'status'       => $status,
            'responded_at' => $c->responded_at?->format('Y-m-d'),
            'rx_number'    => $c->prescription?->rx_number,
        ];
    }

    public function patientSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => false, 'message' => '두 글자 이상 입력해 주세요.', 'patients' => []]);
        }

        $digits = preg_replace('/\D/', '', $q);

        $patients = Patient::where(function ($sub) use ($q, $digits) {
                $sub->where('name', 'like', "%{$q}%");
                if ($digits !== '' && strlen($digits) >= 4) {
                    $sub->orWhere('mobile', 'like', "%{$digits}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->withCount(['prescriptions as counseling_count' => fn ($sub) => $sub->whereNotNull('counsel_no')])
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'success'  => true,
            'patients' => $patients->map(fn (Patient $p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'resident_no'      => $p->masked_resident_no ?? '-',
                'mobile'           => $p->mobile ?? $p->phone ?? '-',
                'counseling_count' => (int) $p->counseling_count,
            ])->values(),
        ]);
    }

    /**
     * 검수 화면 '환자 조회': 선택한 환자의 과거 상담 이력 목록.
     * 반환 형식은 검수 화면의 '이전 상담 이력' 모달과 동일해 그대로 재사용·가져오기가 된다.
     */
    public function patientCounselings(Patient $patient): JsonResponse
    {
        $rows = Prescription::where('patient_id', $patient->id)
            ->whereNotNull('counsel_no')
            ->orderByDesc('id')
            ->limit(30)
            ->with(['items', 'order.tossPayment', 'consents', 'faxHistories'])
            ->get([
                'id', 'rx_number', 'created_at', 'status',
                'patient_name_ocr', 'resident_no_ocr_masked', 'mobile_ocr', 'address_ocr',
                'hospital_name', 'doctor_name', 'issued_date',
                'postcode', 'address_detail', 'patient_id', 'repurchase_date',
            ])
            ->filter(fn ($p) => !empty($p->counsel_no))
            ->values();

        return response()->json([
            'success'     => true,
            'patient'     => ['id' => $patient->id, 'name' => $patient->name],
            'counselings' => $rows->map(fn ($p) => $this->counselingPayload($p))->values(),
        ]);
    }

    // ── 주문 연계 페이지 (검수 화면) ──────────────────────
    public function show(Prescription $prescription): View
    {
        $prescription->load(['patient', 'assignedUser', 'creator', 'reviewer', 'updater', 'order.tossPayment', 'items', 'memos.user', 'attachments', 'documents.creator']);
        $patients = Patient::orderBy('name')->get();

        // 이전(ID 작은 쪽) / 다음(ID 큰 쪽) — rx_number 반환
        $prevId = Prescription::where('id', '<', $prescription->id)->orderByDesc('id')->value('rx_number');
        $nextId = Prescription::where('id', '>', $prescription->id)->orderBy('id')->value('rx_number');

        // 같은 환자의 이전 상담 이력 (상담번호 있는 것만, 최대 10건)
        $prevCounselings = collect();
        if ($prescription->patient_id) {
            $prevCounselings = Prescription::where('patient_id', $prescription->patient_id)
                ->where('id', '!=', $prescription->id)
                ->whereNotNull('counsel_no')
                ->orderByDesc('id')
                ->limit(10)
                ->with(['items', 'order.tossPayment', 'consents', 'faxHistories'])
                ->get([
                    'id', 'rx_number', 'created_at', 'status',
                    'patient_name_ocr', 'resident_no_ocr_masked', 'mobile_ocr', 'address_ocr',
                    'hospital_name', 'doctor_name', 'issued_date',
                    'postcode', 'address_detail', 'patient_id', 'repurchase_date',
                ])
                ->filter(fn($p) => !empty($p->counsel_no))
                ->values();
        }

        $tossConfigured  = $this->vaService->isConfigured();
        $kakaoConfigured = $this->kakaoService->isConfigured();
        $kakaoTemplates  = \App\Services\KakaoService::templates();
        $smsTemplates    = self::smsTemplates();

        $memosData = $prescription->memos->map(function ($m) {
            return [
                'id'         => $m->id,
                'content'    => $m->content,
                'user_name'  => $m->user?->name ?? '-',
                'created_at' => $m->created_at->format('Y-m-d H:i'),
                'is_pinned'  => $m->is_pinned,
                'pin_x'      => $m->pin_x,
                'pin_y'      => $m->pin_y,
            ];
        })->values();

        // Blade @json 파싱 오류 방지: 복잡한 클로저를 컨트롤러에서 직렬화
        $prevCounselingsData = $prevCounselings->map(fn ($p) => $this->counselingPayload($p))->values();

        $lastFaxHistory = \App\Models\FaxHistory::where('prescription_id', $prescription->id)
            ->latest()
            ->first();

        $attachmentsJson = $prescription->attachments->map(function ($a) {
            return [
                'id'        => $a->id,
                'url'       => $a->file_url,
                'type'      => $a->doc_type,
                'typeLabel' => $a->doc_type_label,
                'name'      => $a->file_original_name,
                'isPdf'     => $a->is_pdf,
                'isRx'      => false,
            ];
        })->values()->toArray();

        // 처방전 이미지 + 첨부 문서 통합 배열 (뷰어 strip용)
        $rxDoc = $prescription->image_url ? [[
            'id'        => 0,
            'url'       => $prescription->image_url,
            'type'      => 'prescription',
            'typeLabel' => '처방전',
            'name'      => $prescription->rx_number,
            'isPdf'     => str_contains($prescription->image_mime_type ?? '', 'pdf'),
            'isRx'      => true,
        ]] : [];
        /* 위임 서명과 보호자 신분증도 문서로 함께 세운다.
           첨부 파일과 같은 자리에 두면 썸네일ㆍ확대ㆍ크게 보기가 그대로 동작한다.
           둘 다 본문으로 내려보내지 않고 권한을 거치는 주소만 준다. */
        $signDocs = [];
        $lastConsent = $prescription->consents()->where('status', 'agreed')->latest()->first();
        if ($lastConsent) {
            if ($lastConsent->signature_data) {
                $signDocs[] = [
                    'id'        => -1,
                    'url'       => route('prescriptions.consentSignature', $prescription),
                    'type'      => 'signature',
                    'typeLabel' => '위임 서명',
                    'name'      => '서명 ' . ($lastConsent->patient_name ?? ''),
                    'isPdf'     => false,
                    'isRx'      => false,
                ];
            }
            if ($lastConsent->guardian_id_path) {
                $signDocs[] = [
                    'id'        => -2,
                    'url'       => route('files.consent-guardian-id', $lastConsent),
                    'type'      => 'guardian_id',
                    'typeLabel' => '보호자 신분증',
                    'name'      => '신분증 ' . ($lastConsent->guardian_name ?? ''),
                    'isPdf'     => false,
                    'isRx'      => false,
                ];
            }
        }

        $allDocsJson = array_merge($rxDoc, $attachmentsJson, $signDocs);

        // 이름 옆 「조회」 창이 쓰는 목록 — 업로드 화면과 같은 것을 쓴다
        $patientsJson = self::patientPickerList();

        return view('prescriptions.order', compact(
            'prescription', 'patients', 'prevId', 'nextId',
            'tossConfigured', 'kakaoConfigured', 'kakaoTemplates', 'smsTemplates',
            'memosData', 'prevCounselings', 'prevCounselingsData',
            'lastFaxHistory', 'attachmentsJson', 'allDocsJson', 'patientsJson'
        ));
    }

    // ── OCR 수정 저장 ─────────────────────────────────────
    public function updateOcr(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            // 「조회」로 고른 사람. 없으면 서버가 이름으로 찾거나 새로 만든다.
            'patient_id'       => 'nullable|integer|exists:patients,id',
            'patient_name_ocr' => 'nullable|string|max:50',
            'resident_no_ocr'  => 'nullable|string|max:20',
            'mobile_ocr'       => 'nullable|string|max:30',
            'address_ocr'      => 'nullable|string|max:300',
            'postcode'         => 'nullable|string|max:10',
            'address_detail'   => 'nullable|string|max:200',
            'hospital_name'    => 'nullable|string|max:100',
            'doctor_name'      => 'nullable|string|max:50',
            'department'       => 'nullable|string|max:100',
            'disease_name'     => 'nullable|string|max:500',
            'disease_code'     => 'nullable|string|max:200',
            'daily_count'      => 'nullable|integer|min:1',
            'total_days'       => 'nullable|integer|min:1',
            'total_count'      => 'nullable|integer|min:1',
            'issued_date'      => 'nullable|date',
            'repurchase_date'  => 'nullable|date',
            'product_name'     => 'nullable|string|max:200',
            'product_code'     => 'nullable|string|max:50',
            'quantity'         => 'nullable|integer|min:1',
            'nhis_status'      => 'nullable|in:eligible,ineligible,partial',
            'product_price'    => 'nullable|numeric|min:0',
            'insurance_price'  => 'nullable|numeric|min:0',
            'patient_id'       => 'nullable|exists:patients,id',
            'admin_note'       => 'nullable|string',
            'items'                   => 'nullable|array|max:20',
            'items.*.product_name'    => 'nullable|string|max:200',
            'items.*.product_code'    => 'nullable|string|max:50',
            'items.*.quantity'        => 'nullable|integer|min:1',
            'items.*.product_price'   => 'nullable|numeric|min:0',
            'items.*.insurance_price' => 'nullable|numeric|min:0',
            'items.*.nhis_status'     => 'nullable|in:eligible,ineligible,partial',
            // 상담 기본 정보
            'counsel_no'            => 'nullable|string|max:50',
            'counsel_date'          => 'nullable|date',
            'counsel_acc_add_type'  => 'nullable|string|max:10',
            'counsel_status'        => 'nullable|string|max:10',
            'counsel_call_no'       => 'nullable|string|max:30',
            'counsel_re_date'       => 'nullable|date',
            // 환자 정보 추가
            'guardian'              => 'nullable|string|max:50',
            // 미성년자 — 법정대리인
            'guardian_name'         => 'nullable|string|max:50',
            'guardian_relation'     => 'nullable|string|max:50',
            'guardian_birth'        => 'nullable|date',
            'guardian_phone'        => 'nullable|string|max:40',
            // 시안 148:2708 로 새로 생긴 항목
            'mobile2'               => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:190',
            'nhis_reg_date'         => 'nullable|date',
            'nhis_renew_due'        => 'nullable|date',
            'basic_reeval'          => 'nullable|string|max:100',
            'basic_reeval_due'      => 'nullable|date',
            // 시안 148:2827 로 새로 생긴 항목
            'dealer_type'           => 'nullable|string|max:50',
            'pay_date'              => 'nullable|date',
            'buy_date'              => 'nullable|date',
            // 시안 148:3046 (추가정보 카드)
            'inmarket_due'          => 'nullable|date',
            'last_confirmed_qty'    => 'nullable|integer|min:0',
            'daily_use_qty'         => 'nullable|integer|min:0',
            'diverticulums'         => 'nullable|string|max:10',
            // 병원·처방 추가
            'hospital_code'         => 'nullable|string|max:50',
            'rx_period'             => 'nullable|integer|min:0',
            'rx_end_date'           => 'nullable|date',
            'diagnosis_date'        => 'nullable|date',
            // 처방 수량·상병 추가
            // 상병명을 그대로 적는다 — 열 자로는 「신경인성 방광 이외」 같은 이름이 들어가지 않는다
            'disease_class'         => 'nullable|string|max:100',
            'sb_sci'                => 'nullable|string|max:50',
            'uro_date'              => 'nullable|date',
            // 급여·보험 추가
            'benefit_class'         => 'nullable|string|max:20',
            // 청구처 — 공단이냐 지자체냐에 따라 이후 절차가 통째로 갈린다
            'claim_agency'          => 'nullable|string|in:nhis,local,none',
            'local_gov'             => 'nullable|string|max:60',
            'nhis_reg_status'       => 'nullable|string|max:20',
            'nhis_renew'            => 'nullable|string|max:100',
            'nhis_agree_start'      => 'nullable|date',
            'nhis_agree_end'        => 'nullable|date',
            // 거래·주문 추가
            'purchase_type'         => 'nullable|string|max:20',
            'five_program'          => 'nullable|string|max:10',
            'deduction'             => 'nullable|string|max:20',
            'cash_receipt_no'       => 'nullable|string|max:50',
            'order_manager'         => 'nullable|string|max:50',
            'next_repurchase'       => 'nullable|date',
            'special_case'          => 'nullable|string|max:50',
            'reason'                => 'nullable|string|max:200',
            // 추가 정보
            'new_patient_date'      => 'nullable|date',
            'five_110days'          => 'nullable|string|max:50',
        ]);

        $payload = $request->only([
            'patient_name_ocr', 'resident_no_ocr', 'mobile_ocr', 'address_ocr',
            'postcode', 'address_detail',
            'hospital_name', 'doctor_name',
            'department', 'disease_name', 'disease_code',
            'daily_count', 'total_days', 'total_count', 'issued_date', 'repurchase_date',
            'product_name', 'product_code', 'quantity', 'nhis_status',
            'product_price', 'insurance_price', 'patient_id', 'admin_note',
        ]);

        // 주민번호는 화면에 마스킹으로만 보인다. 담당자가 '표시'를 눌러 원문을 불러오지 않았으면
        // 값이 오지 않는데, 이때 null 을 그대로 쓰면 저장만으로 기존 값이 지워진다(P0-1).
        if (!$request->filled('resident_no_ocr')) {
            unset($payload['resident_no_ocr']);
        }

        $prescription->update($payload);

        /* ── 상담·부가 항목 저장 ─────────────────────────────────────────
           예전에는 counseling_data JSON 한 칸에 뭉쳐 담았다. 값이 JSON 안에 있으면 인덱스를
           걸 수 없어 검색·정렬이 되지 않아, 전부 각자 컬럼으로 옮겼다.
           환자에 속한 값은 아래에서 환자를 이은 뒤에 쓴다 — 지금은 아직 patient_id 가 없을 수 있다. */
        $rxCols = array_filter([
            // 상담
            'counsel_no'           => $request->input('counsel_no'),
            'counsel_date'         => $request->input('counsel_date'),
            // counsel_type 은 건드리지 않는다 — 「상담 유형」 칸을 걷어 보내오는 값이 없다.
            // 유형은 거래처 관리의 상담 창에서 정한다. 여기서 덮어쓰면 그 값이 지워진다.
            'counsel_acc_add_type' => $request->input('counsel_acc_add_type'),
            'counsel_status'       => $request->input('counsel_status'),
            'counsel_call_no'      => $request->input('counsel_call_no')
                                        ? preg_replace('/\D/', '', $request->input('counsel_call_no'))
                                        : null,
            'counsel_re_date'      => $request->input('counsel_re_date'),
            // counsel_contents 는 건드리지 않는다 — 「검수 메모」 칸을 화면에서 걷어
            // 보내오는 값이 없다. 여기서 덮어쓰면 예전에 적어 둔 것이 지워진다.
            // 병원·처방
            'hospital_code'        => $request->input('hospital_code'),
            'rx_use_period'        => $request->input('rx_period'),
            'rx_end_date'          => $request->input('rx_end_date'),
            'diagnosis_date'       => $request->input('diagnosis_date'),
            'disease_class'        => $request->input('disease_class'),
            'uro_date'             => $request->input('uro_date'),
            'benefit_class'        => $request->input('benefit_class'),
            'claim_agency'         => $request->input('claim_agency'),
            'local_gov'            => $request->input('local_gov'),
            // 거래·주문
            'purchase_type'        => $request->input('purchase_type'),
            'five_program'         => $request->input('five_program'),
            'five_110days'         => $request->input('five_110days'),
            'order_manager'        => $request->input('order_manager'),
            'next_repurchase'      => $request->input('next_repurchase'),
            'special_case'         => $request->input('special_case'),
            'reason'               => $request->input('reason'),
            'dealer_type'          => $request->input('dealer_type'),
            'pay_date'             => $request->input('pay_date'),
            'buy_date'             => $request->input('buy_date'),
            'inmarket_due'         => $request->input('inmarket_due'),
            'last_confirmed_qty'   => $request->input('last_confirmed_qty'),
            'daily_use_qty'        => $request->input('daily_use_qty'),
            'diverticulums'        => $request->input('diverticulums'),
            'caregiver_name'       => $request->input('guardian'),
        ], fn ($v) => $v !== null);

        if ($rxCols) {
            $prescription->update($rxCols);
        }

        $promotedPatientFields = array_filter([
            'email'               => $request->input('email'),
            'phone'               => $request->input('mobile2'),
            'sb_sci'              => $request->input('sb_sci'),
            // 사업부 — 골랐을 때만 올린다. IC 면 저장되는 이름 앞에 (E) 가 붙는다(모델이 단다)
            'care_type'           => Patient::hasCareTypeColumn() ? $request->input('care_type') : null,
            'nhis_reg_status'     => $request->input('nhis_reg_status'),
            'nhis_reg_date'       => $request->input('nhis_reg_date'),
            'nhis_renew'          => $request->input('nhis_renew'),
            'nhis_renew_due'      => $request->input('nhis_renew_due'),
            'nhis_agree_start'    => $request->input('nhis_agree_start'),
            'nhis_agree_end'      => $request->input('nhis_agree_end'),
            'basic_reeval'        => $request->input('basic_reeval'),
            'basic_reeval_due'    => $request->input('basic_reeval_due'),
            'cash_receipt_no'     => $request->input('cash_receipt_no'),
            'deduction'           => $request->input('deduction'),
            'new_patient_date'    => $request->input('new_patient_date'),
            'guardian_name'       => $request->input('guardian_name'),
            'guardian_relation'   => $request->input('guardian_relation'),
            'guardian_birth_date' => $request->input('guardian_birth'),
            'guardian_phone'      => $request->input('guardian_phone'),
        ], fn ($v) => $v !== null);

        /* 「조회」로 고른 사람이 함께 왔으면 그 사람으로 잇는다. 화면에서 고른 것이
           이름으로 찾는 것보다 확실하다 — 같은 이름이 여럿일 때 서버는 가리지 못한다.
           처방전 자체는 업로드 때 이미 만들어졌으므로, 여기서 잇는 것만으로
           그 사람의 새 처방전이 된다. */
        if ($request->filled('patient_id')
            && (int) $request->input('patient_id') !== (int) $prescription->patient_id
            && Patient::whereKey($request->input('patient_id'))->exists()) {
            $prescription->update(['patient_id' => (int) $request->input('patient_id')]);
        }

        // 환자 마스터 업데이트 또는 자동 등록/연결
        $prescription->refresh();
        if ($prescription->patient) {
            // 이미 연결된 환자 — 모든 필드를 입력값으로 덮어씀
            $patientUpdates = [];
            if ($request->filled('patient_name_ocr')) $patientUpdates['name']        = $request->patient_name_ocr;
            if ($request->filled('resident_no_ocr'))  $patientUpdates['resident_no'] = $request->resident_no_ocr;
            if ($request->filled('mobile_ocr'))       $patientUpdates['mobile']      = $request->mobile_ocr;
            if ($request->filled('address_ocr'))      $patientUpdates['address']     = $request->address_ocr;

            /* 생년월일·성별은 주민번호 앞 7자리에서 나온다(P0-1 — 원문을 열지 않는다).
               담당자가 따로 입력하는 칸이 아니라서, 채워 두지 않으면 거래처 관리 그리드의
               두 칸이 늘 비어 있다. 이미 값이 있으면 건드리지 않는다. */
            if ($request->filled('resident_no_ocr')) {
                $masked = ResidentNo::mask($request->resident_no_ocr);
                if (!$prescription->patient->birth_date && $b = ResidentNo::birthDateFromMasked($masked)) {
                    $patientUpdates['birth_date'] = $b;
                }
                if (!$prescription->patient->gender && $g = ResidentNo::genderFromMasked($masked)) {
                    $patientUpdates['gender'] = $g;
                }
            }

            if ($patientUpdates) {
                $prescription->patient->update($patientUpdates);
            }
        } elseif (!$request->filled('patient_id')) {
            // 연결된 환자 없음 — 자동 등록/연결
            $this->linkOrCreatePatient($prescription, [
                'patient_name' => $request->patient_name_ocr,
                'resident_no'  => $request->resident_no_ocr,
                'mobile'       => $request->mobile_ocr,
                'address'      => $request->address_ocr,
                'care_type'    => Patient::hasCareTypeColumn() ? $request->input('care_type') : null,
            ]);
        }

        // 환자로 승격된 항목은 환자를 이은 뒤에 쓴다(건보 등록·위임동의 기간·보호자 등).
        if (!empty($promotedPatientFields)) {
            $prescription->refresh();
            $prescription->patient?->update($promotedPatientFields);
        }

        /* 공단에 신규 등록하면 2년 뒤 다시 등록해야 한다. 등록일이 있는데 기한이 비어 있으면
           채워 둔다 — 화면에서도 계산해 넣지만, 다른 길로 저장될 때도 비지 않게 여기서 한 번 더 본다.
           손으로 적어 둔 값은 건드리지 않는다. */
        $prescription->refresh();
        $pt = $prescription->patient;
        if ($pt && $pt->nhis_reg_date && !$pt->nhis_renew_due) {
            $pt->update([
                'nhis_renew_due' => \Illuminate\Support\Carbon::parse($pt->nhis_reg_date)->addYears(2)->toDateString(),
            ]);
        }

        // ── 아이템 동기화 ────────────────────────────────────────
        $items = $request->input('items', []);
        if (!empty($items)) {
            $prescription->items()->delete();
            foreach ($items as $i => $d) {
                if (empty($d['product_name'])) continue;
                $price = isset($d['insurance_price']) && $d['insurance_price'] > 0
                    ? (float)$d['insurance_price']
                    : (isset($d['product_price']) ? (float)$d['product_price'] : null);
                $qty = max(1, (int)($d['quantity'] ?? 1));
                $nhisStatus = $d['nhis_status'] ?? 'eligible';
                $nhisAmt = 0.0;
                $copay   = 0.0;
                if ($price !== null) {
                    $rate = match($nhisStatus) {
                        'eligible' => ($prescription->patient?->nhis_coverage_rate ?? 90) / 100,
                        'partial'  => 0.50,
                        default    => 0.0,
                    };
                    $nhisAmt = round($price * $rate * $qty, 2);
                    $copay   = round($price * $qty - $nhisAmt, 2);
                }
                $prescription->items()->create([
                    'product_name'    => $d['product_name'],
                    'product_code'    => $d['product_code'] ?? null,
                    'quantity'        => $qty,
                    'product_price'   => isset($d['product_price'])   ? (float)$d['product_price']   : null,
                    'insurance_price' => isset($d['insurance_price']) ? (float)$d['insurance_price'] : null,
                    'nhis_status'     => $nhisStatus,
                    'nhis_amount'     => $nhisAmt,
                    'patient_copay'   => $copay,
                    'sort_order'      => $i,
                ]);
            }

            // 첫 번째 아이템을 처방전 메인 필드에도 반영 (목록/OCR 표시용)
            $firstItem = $prescription->items()->first();
            if ($firstItem) {
                $prescription->update([
                    'product_name'    => $firstItem->product_name,
                    'product_code'    => $firstItem->product_code,
                    'quantity'        => $firstItem->quantity,
                    'product_price'   => $firstItem->product_price,
                    'insurance_price' => $firstItem->insurance_price,
                    'nhis_status'     => $firstItem->nhis_status,
                    'nhis_amount'     => $firstItem->nhis_amount,
                    'patient_copay'   => $firstItem->patient_copay,
                ]);
            }
        }

        $prescription->load('items');
        $totalNhis  = $prescription->items->sum('nhis_amount');
        $totalCopay = $prescription->items->sum('patient_copay');

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('OCR 필드 수정');

        return response()->json([
            'success'     => true,
            'message'     => '저장되었습니다.',
            'items'       => $prescription->items->map(fn($item) => [
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_status'     => $item->nhis_status,
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
            ])->values(),
            'total_nhis'  => $totalNhis,
            'total_copay' => $totalCopay,
        ]);
    }

    // ── 검수 요청 ─────────────────────────────────────────
    /* 담당자가 손으로 다 적었다는 신호다. 여기서 상태만 바꾸고 값은 건드리지 않는다 —
       적는 일은 저장(saveOCR)이 이미 했다. 검수자가 「검수 완료」를 누르기 전까지
       담당자는 계속 고칠 수 있다. */
    public function requestReview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        if (in_array($prescription->status, ['approved', 'ordered'], true)) {
            return response()->json([
                'success' => false,
                'message' => '이미 검수가 끝난 처방전입니다.',
            ], 422);
        }

        $prescription->update([
            'status'      => 'review_requested',
            'review_memo' => $request->memo ?: $prescription->review_memo,
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('검수 요청');

        return response()->json(['success' => true, 'message' => '검수 요청 완료']);
    }

    // ── 검수 승인 ─────────────────────────────────────────
    public function approve(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $prescription->update([
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_memo' => $request->memo,
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('검수 승인');

        return response()->json(['success' => true, 'message' => '검수 승인 완료']);
    }

    // ── 반려 ─────────────────────────────────────────────
    public function reject(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate(['reason' => 'required|string']);

        $prescription->update([
            'status'      => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_memo' => $request->reason,
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('반려: ' . $request->reason);

        return response()->json(['success' => true, 'message' => '반려 처리 완료']);
    }

    // ── 카카오 알림톡 발송 ────────────────────────────────
    public function sendKakao(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'template_code' => 'required|string',
            'mobile'        => 'required|string',
        ]);

        $prescription->load(['patient', 'order.tossPayment']);
        $order = $prescription->order;
        $tp    = $order?->tossPayment;

        $params = [
            '#{고객명}'    => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '고객',
            '#{주문번호}'  => $order?->order_number ?? '-',
            '#{제품명}'    => $order?->product_name ?? $prescription->rx_number,
            '#{금액}'      => $order ? number_format(($order->patient_copay ?? 0) + ($order->shipping_fee ?? 0)) : '-',
            '#{은행명}'    => $tp?->bank_name ?? '-',
            '#{계좌번호}'  => $tp?->account_number ?? '-',
            '#{기한}'      => $tp?->due_date?->format('Y-m-d H:i') ?? '-',
            '#{택배사}'    => '택배',
            '#{운송장번호}'=> $order?->tracking_number ?? '-',
            '#{배송지}'    => $order?->shipping_address ?? '-',
            '#{채널명}'    => config('kakao.channel_id', '콜로플라스트'),
        ];

        $result = $this->kakaoService->sendAlimtalk(
            $request->mobile,
            $request->template_code,
            $params,
            \App\Services\KakaoService::templates()[$request->template_code]['label'] ?? ''
        );

        if ($result['success']) {
            $prescription->update(['kakao_sent_at' => now()]);
            activity()->causedBy(auth()->user())->performedOn($prescription)
                ->log('카카오 알림톡 발송: ' . $request->template_code . ' → ' . $request->mobile);
        }

        return response()->json($result);
    }

    // ── 카카오 알림톡 미리보기 ──────────────────────────────
    public function kakaoPreview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate(['template_code' => 'required|string']);

        $prescription->load(['patient', 'order.tossPayment', 'items']);
        $order = $prescription->order;
        $tp    = $order?->tossPayment;

        $itemCopay = (int) $prescription->items->sum(function ($i) {
            $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
            $qty  = (int)($i->quantity ?? 1);
            $rate = match ($i->nhis_status ?? 'eligible') {
                'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
            };
            return round($base * $qty) - round($base * $rate * $qty);
        });

        $params = [
            '#{고객명}'    => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '고객',
            '#{주문번호}'  => $order?->order_number ?? '-',
            '#{제품명}'    => $order?->product_name ?? $prescription->rx_number,
            '#{본인부담금}'=> $itemCopay ? number_format($itemCopay) : '-',
            '#{금액}'      => $itemCopay ? number_format($itemCopay + ($order->shipping_fee ?? 0)) : '-',
            '#{은행명}'    => $tp?->bank_name ?? '-',
            '#{계좌번호}'  => $tp?->account_number ?? '-',
            '#{기한}'      => $tp?->due_date?->format('Y-m-d H:i') ?? '-',
            '#{택배사}'    => '택배',
            '#{운송장번호}'=> $order?->tracking_number ?? '-',
            '#{배송지}'    => $order?->shipping_address ?? '-',
            '#{채널명}'    => config('kakao.channel_id', '콜로플라스트'),
        ];

        $preview = $this->kakaoService->buildPreview($request->template_code, $params);
        $mobile  = $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '';

        return response()->json([
            'preview' => $preview,
            'mobile'  => $mobile,
        ]);
    }

    // ── 상담번호 채번 ──────────────────────────────────────
    public function generateCounselNo(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'        => true,
            'counselling_no' => Prescription::generateCounselNo(),
            'counsel_date'   => now()->format('Y-m-d'),
        ]);
    }

    // ── 위임동의 SMS 발송 ─────────────────────────────────
    public function sendConsentSms(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'mobile' => 'required|string|max:20',
            'name'   => 'nullable|string|max:50',
        ]);

        // 오타로 동의 건을 만들고 SMS 를 태우는 일만 막는다.
        // 02-XXX-XXXX(9자리)까지 받아 들여 실제 번호를 거부하지 않는다.
        $mobile = preg_replace('/\D/', '', $request->mobile);
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return response()->json(['success' => false, 'message' => '수신 번호 형식이 올바르지 않습니다.'], 422);
        }

        // OCR 이름이 틀리거나 비어 있는 경우가 있어 화면에서 고쳐 보낼 수 있다.
        // 보내지 않았거나 비웠으면 처방전에 적힌 이름을 쓴다.
        $patientName = trim((string) $request->input('name'))
            ?: ($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '환자');

        return $this->issueConsent($prescription, $mobile, $patientName);
    }

    /**
     * 동의 건을 만들고 서명 링크를 SMS 로 보낸다.
     *
     * 검수 화면과 위임장 서명 화면 두 곳에서 부른다. 토큰·유효시간·문구가 갈리면
     * 환자가 받는 링크가 화면마다 달라지므로 한 곳에만 둔다.
     * $mobile 은 숫자만, $patientName 은 이미 정해진 이름이 들어온다.
     */
    public function issueConsent(Prescription $prescription, string $mobile, string $patientName): \Illuminate\Http\JsonResponse
    {
        $token       = \Illuminate\Support\Str::random(24);
        $expiresAt   = now()->addMinutes(30);

        /* 미성년자는 혼자 위임할 수 없다. 서명 화면에서 법정대리인의 이름과 서명을 함께 받는다.
           나이는 마스킹된 주민번호 앞자리로 안다 — 원문을 열지 않는다(P0-1). */
        $masked = $prescription->resident_no_ocr_masked ?: $prescription->patient?->masked_resident_no;
        $birth  = \App\Support\ResidentNo::birthDateFromMasked($masked);
        $isMinor = $birth ? $birth->age < (int) config('delegation.minor_age', 19) : false;

        $consent = \App\Models\PrescriptionConsent::create([
            'prescription_id'    => $prescription->id,
            'token'              => $token,
            'patient_name'       => $patientName,
            'patient_mobile'     => $mobile,
            'expires_at'         => $expiresAt,
            'status'             => 'pending',
            'is_minor'           => $isMinor,
            'patient_birth_date' => $birth?->toDateString(),
            // 검수 화면에서 미리 적어 둔 보호자 정보를 실어 보낸다.
            // 서명 화면에 그대로 보이고, 보호자는 서명과 신분증만 더하면 된다.
            'guardian_name'       => $isMinor ? ($prescription->patient?->guardian_name ?: null) : null,
            'guardian_relation'   => $isMinor ? ($prescription->patient?->guardian_relation ?: null) : null,
            'guardian_birth_date' => $isMinor ? ($prescription->patient?->guardian_birth_date ?: null) : null,
            'guardian_phone'      => $isMinor ? ($prescription->patient?->guardian_phone ?: null) : null,
        ]);

        $baseUrl = rtrim(config('app.consent_public_url', config('app.url')), '/');
        // 반드시 https:// 스킴으로 (일부 SMS 앱은 http를 자동 링크 미처리)
        if (str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }
        $url = $baseUrl . '/consent/' . $token;

        // URL이 localhost인 경우 링크가 클릭되지 않을 수 있음 — 운영 서버 URL로 변경 필요
        $message = "[콜로플라스트] {$patientName}님\n건강보험 급여 위임동의 서명 요청입니다.\n서명 링크(30분 유효):\n{$url}";

        try {
            $this->smsService->send($mobile, $message, $patientName);

            activity()->causedBy(auth()->user())->performedOn($prescription)
                ->log("위임동의 SMS 발송 → {$patientName} {$mobile}");

            return response()->json([
                'success'    => true,
                'message'    => 'SMS가 발송되었습니다.',
                'expires_at' => $expiresAt->format('H:i'),
                'consent_id' => $consent->id,
            ]);
        } catch (\Throwable $e) {
            $consent->delete();
            Log::error('[위임동의] SMS 발송 실패', ['error' => $e->getMessage(), 'rx' => $prescription->id]);
            return response()->json(['success' => false, 'message' => 'SMS 발송 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── SMS 알림 발송 ──────────────────────────────────────
    public function sendSms(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'mobile'   => 'required|string',
            'message'  => 'required|string|max:2000',
        ]);

        $mobile      = $request->mobile;
        $message     = $request->message;
        $patientName = $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '';

        try {
            $this->smsService->send($mobile, $message, $patientName);

            $prescription->update(['sms_sent_at' => now()]);
            activity()->causedBy(auth()->user())->performedOn($prescription)
                ->log('SMS 발송 → ' . $request->mobile);

            return response()->json(['success' => true, 'message' => 'SMS가 발송되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('[SMS] 처방전 발송 실패', ['error' => $e->getMessage(), 'rx' => $prescription->id]);
            return response()->json(['success' => false, 'message' => 'SMS 발송 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 팩스 전송 ─────────────────────────────────────────
    public function sendFax(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'recipient_type'  => 'required|string|max:50',
            'fax_no'          => ['required', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'documents'       => 'nullable|array',
            'documents.*'     => 'string|in:authorization,delegation,prescription,purchase_history,cash_receipt,tax_invoice',
            'attachment_ids'  => 'nullable|array',
            'attachment_ids.*' => 'integer|exists:prescription_attachments,id',
        ]);

        if (empty($request->documents) && empty($request->attachment_ids)) {
            return response()->json(['success' => false, 'message' => '전송할 서류를 하나 이상 선택해주세요.'], 422);
        }

        $docLabels = [
            'authorization'    => '위임장',
            'delegation'       => '요양비위임장',
            'prescription'     => '처방전',
            'purchase_history' => '제품 구매내역',
            'cash_receipt'     => '현금영수증',
        ];
        // 심평원은 우리 팩스를 받지 않는다. 고를 수 있게 두면 잘못 보낸다.
        $recipientLabels = [
            'nhis'   => '국민건강보험공단',
            'custom' => '기타',
        ];

        $docs      = array_map(fn($d) => $docLabels[$d] ?? $d, $request->documents ?? []);
        $recipient = $recipientLabels[$request->recipient_type] ?? $request->recipient_type;

        // 첨부 문서 라벨 수집
        $attachmentIds    = $request->attachment_ids ?? [];
        $attachmentLabels = [];
        if (!empty($attachmentIds)) {
            $attachments = PrescriptionAttachment::whereIn('id', $attachmentIds)
                ->where('prescription_id', $prescription->id)
                ->get();
            foreach ($attachments as $att) {
                $attachmentLabels[] = $att->doc_type_label . ': ' . $att->file_original_name;
            }
        }

        // 위임장 포함 여부 + 서명 상태 확인
        $authInfo = null;
        if (in_array('authorization', $request->documents ?? [])) {
            $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
                ->where('status', 'agreed')
                ->latest()
                ->first();

            $authInfo = [
                'has_signature'   => (bool) $consent?->signature_data,
                'consent_id'      => $consent?->id,
                'is_auto_generated' => !($consent?->signature_data),
            ];
        }

        // 파일 경로 수집
        $filePaths = $this->collectFaxFiles($prescription, $request->documents ?? [], $authInfo, $attachmentIds);

        // 합본 PDF 저장 + 서류 관리 기록
        $pdfPath    = null;
        $pdfUrl     = null;
        try {
            [$pdfPath, $pdfUrl] = $this->saveFaxPdf($prescription, $request->documents ?? [], $attachmentIds);

            if ($pdfPath) {
                PrescriptionDocument::create([
                    'prescription_id'   => $prescription->id,
                    'patient_id'        => $prescription->patient?->id,
                    'created_by'        => Auth::id(),
                    'type'              => 'fax',
                    'file_path'         => $pdfPath,
                    'original_filename' => basename($pdfPath),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[Fax] PDF 저장 실패', ['rx' => $prescription->rx_number, 'error' => $e->getMessage()]);
        }

        $allDocLabels = array_merge($docs, $attachmentLabels);
        $faxTitle     = "[CE] {$prescription->rx_number} " . implode('·', $allDocLabels);

        // Popbill 팩스 전송 (설정된 경우)
        $receiptNum = null;
        $corpNum    = config('popbill.test.corp_num');
        $sender     = config('popbill.test.fax_sender') ?: config('popbill.test.sender_num') ?: config('popbill.company.tel', '');

        if ($corpNum && !empty($filePaths)) {
            try {
                $faxSvc   = app(PopbillFaxService::class);
                $receiver = new \stdClass();
                $receiver->rcv   = preg_replace('/[^0-9]/', '', $request->fax_no);
                $receiver->rcvnm = $recipient;

                $receiptNum = $faxSvc->sendFax(
                    $corpNum,
                    preg_replace('/[^0-9]/', '', $sender),
                    [$receiver],
                    $filePaths,
                    null, null,
                    $faxTitle,
                );
            } catch (\Throwable $e) {
                Log::warning('[Fax] Popbill 팩스 전송 실패 — 로그만 기록', [
                    'rx'    => $prescription->rx_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 임시 파일 정리
        foreach ($filePaths as $path) {
            if (str_contains($path, 'temp/') && file_exists($path)) {
                @unlink($path);
            }
        }

        // FaxHistory 기록
        \App\Models\FaxHistory::create([
            'prescription_id' => $prescription->id,
            'corp_num'        => $corpNum ?? '',
            'receipt_num'     => $receiptNum ?? ('LOCAL-' . now()->format('YmdHis') . '-' . rand(100, 999)),
            'sender'          => $sender ?? '',
            'title'           => $faxTitle,
            'receivers'       => [['rcv' => $request->fax_no, 'rcvnm' => $recipient]],
            'file_names'      => array_map('basename', $filePaths),
            'fax_no'          => $request->fax_no,
            'recipient_type'  => $request->recipient_type,
            'documents'       => $request->documents ?? [],
            'attachment_ids'  => $attachmentIds,
            'pdf_path'        => $pdfPath,
            'sent_by'         => auth()->id(),
            'popbill_state'   => $receiptNum ? \App\Models\FaxHistory::STATE_WAIT : \App\Models\FaxHistory::STATE_FAIL,
        ]);

        $allDocsForLog = implode(', ', $allDocLabels);
        activity()->causedBy(auth()->user())->performedOn($prescription)
            ->log("팩스 전송 → {$recipient} ({$request->fax_no}) | 서류: {$allDocsForLog}"
                . ($receiptNum ? " | 접수번호: {$receiptNum}" : '')
                . ($pdfPath    ? " | PDF: {$pdfPath}" : ''));

        return response()->json([
            'success'       => true,
            'message'       => "팩스 전송이 요청되었습니다.",
            'receipt_num'   => $receiptNum,
            'recipient'     => $recipient,
            'fax_no'        => $request->fax_no,
            'documents'     => $allDocLabels,
            'auth_info'     => $authInfo,
            'pdf_url'       => $pdfUrl,
        ]);
    }

    // ── 위임장 미리보기 ───────────────────────────────────
    public function authorization(Prescription $prescription): View
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->first();

        $patient = $prescription->patient;

        return view('prescriptions.authorization', [
            'prescription'   => $prescription,
            'patient'        => $patient,
            'consent'        => $consent,
            'isAutoGenerated' => !($consent?->signature_data),
        ]);
    }

    // ── 팩스 서류 PDF 다운로드 ────────────────────────────
    public function downloadFaxPdf(Request $request, Prescription $prescription): \Illuminate\Http\Response
    {
        $allowed = ['authorization', 'delegation', 'prescription', 'purchase_history', 'cash_receipt'];
        $docs    = array_values(array_intersect(
            (array) $request->input('docs', ['authorization']),
            $allowed
        ));
        if (empty($docs)) {
            $docs = ['authorization'];
        }

        [$pdfOutput, $filename] = $this->buildFaxCombinedPdf($prescription, $docs);
        $this->storeFaxDocument($prescription, $pdfOutput, $filename);

        return response($pdfOutput, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    /**
     * 생성 서류 목록 partial HTML 반환 (서명 완료 시 실시간 갱신용).
     */
    public function generatedDocs(Prescription $prescription): \Illuminate\Http\Response
    {
        $prescription->load('documents.creator');
        return response(view('prescriptions._generated_docs', compact('prescription'))->render());
    }

    /**
     * 관리자: 팩스통합본을 현재 데이터로 재생성 (요양비위임장 포함). 기존 팩스통합본 교체.
     */
    public function regenerateFax(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        // 적용 가능한 모든 문서로 재생성 (요양비위임장 포함) — 데이터 없는 섹션은 뷰에서 자동 제외
        $docs = ['authorization', 'delegation', 'prescription', 'purchase_history', 'cash_receipt'];

        try {
            [$pdfOutput, $filename] = $this->buildFaxCombinedPdf($prescription, $docs);
        } catch (\Throwable $e) {
            Log::warning('팩스통합본 재생성 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '재생성 실패: ' . $e->getMessage()], 500);
        }

        // 기존 팩스통합본 교체
        foreach (PrescriptionDocument::where('prescription_id', $prescription->id)->where('type', 'fax')->get() as $old) {
            if ($old->file_path && Storage::exists($old->file_path)) {
                Storage::delete($old->file_path);
            }
            $old->delete();
        }
        $this->storeFaxDocument($prescription, $pdfOutput, $filename);

        return response()->json(['success' => true, 'message' => '팩스통합본을 재생성했습니다 (요양비위임장 포함).']);
    }

    /**
     * 팩스통합본 PDF 생성 → [바이너리, 파일명]. 'delegation' 선택 시 요양비위임장 PDF를 FPDI로 병합.
     */
    private function buildFaxCombinedPdf(Prescription $prescription, array $docs): array
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->first();

        $prescription->load(['patient', 'items', 'order']);
        $patient = $prescription->patient;
        $order   = $prescription->order;

        // 처방전 이미지 → base64 data URI (가로형이면 90° 회전해 세로형으로)
        $rxImageDataUri = null;
        if (in_array('prescription', $docs) && $prescription->image_path) {
            $absPath = Storage::disk('public')->path($prescription->image_path);
            if (file_exists($absPath)) {
                $rxImageDataUri = $this->rxImageToPortraitDataUri($absPath);
            }
        }

        $html = view('prescriptions.fax-pdf', [
            'prescription'   => $prescription,
            'patient'        => $patient,
            'consent'        => $consent,
            'order'          => $order,
            'docs'           => $docs,
            'rxImageDataUri' => $rxImageDataUri,
        ])->render();

        $dompdf = $this->makeFaxDompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();

        // 요양비위임장(별지 제19호의7 원본 오버레이) 병합
        if (in_array('delegation', $docs)) {
            $delegBytes = app(\App\Http\Controllers\ConsentController::class)->overlayPdfBytes($prescription);
            if ($delegBytes) {
                $pdfOutput = $this->mergePdfBytes([$pdfOutput, $delegBytes]);
            }
        }

        $mobile   = preg_replace('/[^0-9]/', '', $patient?->mobile ?? '');
        $filename = '팩스통합본_' . ($patient?->name ?? '') . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';

        return [$pdfOutput, $filename];
    }

    /** 팩스통합본을 스토리지 저장 + 서류(type=fax) 기록 */
    private function storeFaxDocument(Prescription $prescription, string $pdfOutput, string $filename): void
    {
        try {
            $filePath = 'fax/' . $prescription->id . '/' . $filename;
            Storage::put($filePath, $pdfOutput);

            PrescriptionDocument::create([
                'prescription_id'   => $prescription->id,
                'patient_id'        => $prescription->patient?->id,
                'created_by'        => Auth::id(),
                'type'              => 'fax',
                'file_path'         => $filePath,
                'original_filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::warning('팩스 PDF 서류 저장 실패: ' . $e->getMessage());
        }
    }

    /** 여러 PDF 바이너리를 FPDI로 순서대로 병합해 하나의 PDF 바이너리 반환 */
    private function mergePdfBytes(array $pdfList): string
    {
        $m = new \setasign\Fpdi\Tcpdf\Fpdi();
        $m->setPrintHeader(false);
        $m->setPrintFooter(false);
        $m->SetAutoPageBreak(false);

        foreach ($pdfList as $bytes) {
            if (!$bytes) {
                continue;
            }
            $stream = \setasign\Fpdi\PdfParser\StreamReader::createByString($bytes);
            $count  = $m->setSourceFile($stream);
            for ($i = 1; $i <= $count; $i++) {
                $tpl  = $m->importPage($i);
                $size = $m->getTemplateSize($tpl);
                $m->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $m->useTemplate($tpl);
            }
        }

        return $m->Output('', 'S');
    }

    // ── 팩스 합본 PDF 저장 ────────────────────────────────
    private function saveFaxPdf(Prescription $prescription, array $documents, array $attachmentIds = []): array
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')->latest()->first();

        $prescription->load(['patient', 'items', 'order']);

        $rxImageDataUri = null;
        if (in_array('prescription', $documents) && $prescription->image_path) {
            $absPath = Storage::disk('public')->path($prescription->image_path);
            if (file_exists($absPath)) {
                $rxImageDataUri = $this->rxImageToPortraitDataUri($absPath);
            }
        }

        // 선택된 첨부파일을 base64 data URI로 변환 (이미지만)
        $attachmentDataUris = [];
        if (!empty($attachmentIds)) {
            $attachments = PrescriptionAttachment::whereIn('id', $attachmentIds)
                ->where('prescription_id', $prescription->id)
                ->orderBy('display_order')
                ->get();

            foreach ($attachments as $att) {
                if (!$att->file_path) continue;
                $absPath = Storage::disk('public')->path($att->file_path);
                if (!file_exists($absPath)) continue;

                if ($att->is_image) {
                    $dataUri = $this->rxImageToPortraitDataUri($absPath);
                    $attachmentDataUris[] = [
                        'label'   => $att->doc_type_label,
                        'dataUri' => $dataUri,
                        'type'    => 'image',
                    ];
                }
                // PDF 첨부는 dompdf가 외부 PDF를 삽입할 수 없으므로 이미지만 처리
            }
        }

        /* 세금계산서는 장표 이미지로 한 장 싣는다 — dompdf 는 외부 PDF 를 못 끼운다. */
        $taxInvoiceDataUri = null;
        if (in_array('tax_invoice', $documents) && $prescription->order?->tax_invoice_status === 'issued') {
            $imgPath = \App\Support\TaxInvoiceImage::ensure($prescription->order);
            $taxInvoiceDataUri = 'data:image/png;base64,' . base64_encode(Storage::get($imgPath));
        }

        $html = view('prescriptions.fax-pdf', [
            'prescription'       => $prescription,
            'patient'            => $prescription->patient,
            'consent'            => $consent,
            'order'              => $prescription->order,
            'docs'               => $documents,
            'rxImageDataUri'     => $rxImageDataUri,
            'attachmentDataUris' => $attachmentDataUris,
            'taxInvoiceDataUri'  => $taxInvoiceDataUri,
        ])->render();

        $dompdf = $this->makeFaxDompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();

        // 요양비위임장(별지 제19호의7 원본 오버레이) 병합
        if (in_array('delegation', $documents)) {
            $delegBytes = app(\App\Http\Controllers\ConsentController::class)->overlayPdfBytes($prescription);
            if ($delegBytes) {
                $pdfOutput = $this->mergePdfBytes([$pdfOutput, $delegBytes]);
            }
        }

        $patient  = $prescription->patient;
        $mobile   = preg_replace('/[^0-9]/', '', $patient?->mobile ?? '');
        $dir      = 'fax/' . $prescription->rx_number;
        $filename = '팩스통합본_' . ($patient?->name ?? '') . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';
        $fullPath = storage_path('app/public/' . $dir . '/' . $filename);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $pdfOutput);

        $relativePath = $dir . '/' . $filename;
        $url          = rtrim(request()->root(), '/') . '/storage/' . $relativePath;

        Log::info('[Fax] PDF 저장 완료', ['path' => $relativePath, 'url' => $url]);

        return [$relativePath, $url];
    }

    private function rxImageToPortraitDataUri(string $absPath): string
    {
        $raw = file_get_contents($absPath);
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            // GD로 열 수 없으면 원본 그대로
            $mime = mime_content_type($absPath) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w > $h) {
            // 가로형 → 시계 방향 90° 회전하여 세로형으로
            $rotated = imagerotate($src, -90, 0);
            imagedestroy($src);
            ob_start();
            imagejpeg($rotated, null, 92);
            imagedestroy($rotated);
            $jpeg = ob_get_clean();
            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        }

        imagedestroy($src);
        $mime = mime_content_type($absPath) ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    private function makeFaxDompdf(): \Dompdf\Dompdf
    {
        $this->ensureNanumGothicVariantsRegistered();

        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        // 쓰인 글자만 심는다. 나눔고딕 원본이 4.5MB 라 통째로 심으면 산출물이 2.7MB 가 되고
        // 만드는 동안 메모리가 128MB 를 넘겨 위임장 내려받기가 500 으로 떨어졌다.
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('NanumGothic');
        return new \Dompdf\Dompdf($options);
    }

    private function ensureNanumGothicVariantsRegistered(): void
    {
        $path = storage_path('fonts/installed-fonts.json');
        if (!file_exists($path)) {
            return;
        }
        $fonts = json_decode(file_get_contents($path), true) ?? [];
        if (!isset($fonts['nanumgothic']['normal'])) {
            return;
        }
        $normalKey = $fonts['nanumgothic']['normal'];
        $changed   = false;
        foreach (['bold', 'italic', 'bold_italic'] as $variant) {
            if (!isset($fonts['nanumgothic'][$variant])) {
                $fonts['nanumgothic'][$variant] = $normalKey;
                $changed = true;
            }
        }
        if ($changed) {
            file_put_contents($path, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // ── 팩스 전송 파일 수집 ───────────────────────────────
    private function collectFaxFiles(Prescription $prescription, array $documents, ?array $authInfo, array $attachmentIds = []): array
    {
        $files = [];

        foreach ($documents as $doc) {
            switch ($doc) {
                case 'authorization':
                    $consent = $authInfo && $authInfo['consent_id']
                        ? PrescriptionConsent::find($authInfo['consent_id'])
                        : null;
                    $patient = $prescription->patient;
                    $html    = view('prescriptions.authorization', [
                        'prescription'    => $prescription,
                        'patient'         => $patient,
                        'consent'         => $consent,
                        'isAutoGenerated' => $authInfo['is_auto_generated'] ?? true,
                    ])->render();
                    $tmpPath = storage_path('app/temp/auth_' . $prescription->rx_number . '_' . time() . '.html');
                    if (!is_dir(storage_path('app/temp'))) {
                        mkdir(storage_path('app/temp'), 0755, true);
                    }
                    file_put_contents($tmpPath, $html);
                    $files[] = $tmpPath;
                    break;

                case 'prescription':
                    if ($prescription->image_path) {
                        $absPath = Storage::disk('public')->path($prescription->image_path);
                        if (file_exists($absPath)) {
                            $files[] = $absPath;
                        }
                    }
                    break;

                case 'purchase_history':
                    // 구매내역 — Order items에서 생성
                    // 주문 품목이 없으면 처방 품목으로 만든다(품목 표 도입 전 주문)
                    if ($prescription->order
                        && ($prescription->order->items->isNotEmpty() || $prescription->items->isNotEmpty())) {
                        $html    = $this->buildPurchaseHistoryHtml($prescription);
                        $tmpPath = storage_path('app/temp/purchase_' . $prescription->rx_number . '_' . time() . '.html');
                        if (!is_dir(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }
                        file_put_contents($tmpPath, $html);
                        $files[] = $tmpPath;
                    }
                    break;

                case 'cash_receipt':
                    $order = $prescription->order;
                    if ($order?->cash_receipt_status === 'issued') {
                        $html    = $this->buildCashReceiptHtml($order);
                        $tmpPath = storage_path('app/temp/cashreceipt_' . $prescription->rx_number . '_' . time() . '.html');
                        if (!is_dir(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }
                        file_put_contents($tmpPath, $html);
                        $files[] = $tmpPath;
                    }
                    break;

                case 'tax_invoice':
                    // 발행된 건만. 장표 이미지가 아직 없으면(옛 건) 그 자리에서 그린다.
                    $order = $prescription->order;
                    if ($order?->tax_invoice_status === 'issued') {
                        $files[] = Storage::path(\App\Support\TaxInvoiceImage::ensure($order));
                    }
                    break;
            }
        }

        // 첨부 문서 파일 추가
        if (!empty($attachmentIds)) {
            $attachments = PrescriptionAttachment::whereIn('id', $attachmentIds)
                ->where('prescription_id', $prescription->id)
                ->orderBy('display_order')
                ->get();
            foreach ($attachments as $att) {
                $absPath = Storage::disk('public')->path($att->file_path);
                if (file_exists($absPath)) {
                    $files[] = $absPath;
                }
            }
        }

        return array_values(array_filter($files));
    }

    private function buildPurchaseHistoryHtml(Prescription $prescription): string
    {
        $order   = $prescription->order;
        $patient = $prescription->patient;
        $rows    = '';

        /* 주문 품목이 정본이다. 품목 표가 생기기 전에 만들어진 주문은 줄이 없으므로
           처방 품목으로 대신 채운다 — 서류가 빈 채로 공단에 나가는 것보다 낫다. */
        $lines = $order?->items->isNotEmpty() ? $order->items : $prescription->items;

        foreach ($lines as $item) {
            $rows .= "<tr>
                <td>{$item->product_name}</td>
                <td>{$item->product_code}</td>
                <td style='text-align:center'>{$item->quantity}</td>
                <td style='text-align:right'>" . number_format((float)$item->unit_price) . "</td>
                <td style='text-align:right'>" . number_format((float)($item->unit_price * $item->quantity)) . "</td>
            </tr>";
        }

        $total = number_format((float)($order->total_amount ?? 0));

        return <<<HTML
<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
<style>
  body{font-family:'맑은 고딕',sans-serif;font-size:12px;padding:20mm;}
  h2{text-align:center;font-size:16px;margin-bottom:16px;}
  table{width:100%;border-collapse:collapse;font-size:11px;}
  th,td{border:1px solid #bbb;padding:5px 8px;}
  th{background:#f0f0f0;font-weight:700;}
  .total{text-align:right;font-weight:700;margin-top:10px;}
</style></head><body>
<h2>제품 구매내역서</h2>
<p style="margin-bottom:10px;">
  주문번호: {$order->order_number} &nbsp;|&nbsp;
  환자명: {$patient?->name} &nbsp;|&nbsp;
  처방전: {$prescription->rx_number} &nbsp;|&nbsp;
  발행일: {$prescription->issued_date?->format('Y-m-d')}
</p>
<table>
  <thead><tr><th>제품명</th><th>제품코드</th><th>수량</th><th>단가(원)</th><th>금액(원)</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
<div class="total">합계: {$total}원</div>
</body></html>
HTML;
    }

    private function buildCashReceiptHtml(Order $order): string
    {
        $typeLabel  = $order->cash_receipt_type === 'income_deduction' ? '소득공제' : '지출증빙';
        $amount     = number_format((int) $order->cash_receipt_amount);
        $issuedAt   = $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '';
        $patient    = $order->patient;

        return <<<HTML
<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
<style>
  body{font-family:'맑은 고딕',sans-serif;font-size:12px;padding:20mm;}
  .title{text-align:center;font-size:20px;font-weight:700;letter-spacing:4px;padding:10px 0 6px;border-bottom:2px solid #111;margin-bottom:12px;}
  .subtitle{text-align:center;font-size:11px;color:#555;margin-bottom:20px;}
  table{width:100%;border-collapse:collapse;}
  th{width:38%;padding:7px 4px;font-weight:600;color:#444;text-align:left;border-bottom:1px solid #ddd;}
  td{padding:7px 4px;border-bottom:1px solid #ddd;}
  .amount{font-size:16px;font-weight:700;}
  .footer{margin-top:20px;text-align:center;font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:10px;}
</style></head><body>
<div class="title">현금영수증</div>
<div class="subtitle">국세청 현금영수증 발행 확인증</div>
<table>
  <tr><th>승인번호</th><td><b>{$order->cash_receipt_no}</b></td></tr>
  <tr><th>거래유형</th><td>{$typeLabel}</td></tr>
  <tr><th>식별번호</th><td>{$order->cash_receipt_identifier}</td></tr>
  <tr><th>거래금액</th><td class="amount">&#8361;{$amount}</td></tr>
  <tr><th>발행일시</th><td>{$issuedAt}</td></tr>
  <tr><th>주문번호</th><td>{$order->order_number}</td></tr>
  <tr><th>고객명</th><td>{$patient?->name}</td></tr>
</table>
<div class="footer">본 영수증은 소득공제·지출증빙용으로 사용하실 수 있습니다.</div>
</body></html>
HTML;
    }

    // ── SMS 템플릿 목록 ────────────────────────────────────
    /**
     * 문자 메시지 유형.
     *
     * 예전에는 이 자리에 배열이 박혀 있어 문구 한 줄을 고치려면 배포를 해야 했다.
     * 이제 message_templates 표에서 읽는다. 표가 비어 있으면 예전 값으로 채우므로
     * 배포 직후에도 예전과 같이 동작한다.
     */
    public static function smsTemplates(): array
    {
        return \App\Models\MessageTemplate::resolve('sms');
    }

    // ── 환자 자동 등록/연결 ───────────────────────────────
    private function linkOrCreatePatient(Prescription $prescription, array $d): void
    {
        $name       = $d['patient_name'] ?? $d['patient_name_ocr'] ?? null;
        $residentNo = $d['resident_no']  ?? null;
        $mobile     = $d['mobile'] ?? $d['phone'] ?? null;
        $address    = $d['address'] ?? null;

        // 이름이 없으면 연결 불가
        if (empty($name)) {
            return;
        }

        $patient = null;

        // ① 주민등록번호로 기존 환자 검색 (가장 정확)
        //    평문 비교가 아니라 조회용 해시로 찾는다 — 평문 컬럼은 곧 사라진다(P0-1)
        if ($residentNo) {
            $patient = Patient::whereResidentNo($residentNo)->first();
        }

        // ② 이름 + 휴대폰으로 검색
        if (!$patient && $mobile) {
            $patient = Patient::where('name', $name)
                ->where('mobile', $mobile)
                ->first();
        }

        // ③ 이름만으로 검색 (동명이인 주의 — 하나일 때만 연결)
        if (!$patient) {
            $sameNamePatients = Patient::where('name', $name)->get();
            if ($sameNamePatients->count() === 1) {
                $patient = $sameNamePatients->first();
            }
        }

        /* 생년월일·성별은 주민번호 앞 7자리에서 나온다. 원문을 열 필요가 없다(P0-1).
           이 값을 채우지 않아 거래처 관리 그리드의 두 칸이 늘 비어 있었다. */
        $masked = ResidentNo::mask($residentNo);
        $birth  = ResidentNo::birthDateFromMasked($masked);
        $gender = ResidentNo::genderFromMasked($masked);

        if ($patient) {
            // 기존 환자 — 비어있는 필드만 OCR 값으로 채움
            $updates = [];
            if (!$patient->resident_no && $residentNo) $updates['resident_no'] = $residentNo;
            if (!$patient->mobile      && $mobile)     $updates['mobile']      = $mobile;
            if (!$patient->address     && $address)    $updates['address']     = $address;
            if (!$patient->birth_date  && $birth)      $updates['birth_date']  = $birth;
            if (!$patient->gender      && $gender)     $updates['gender']      = $gender;
            if ($updates) {
                $patient->update($updates);
            }
        } else {
            // 신규 환자 등록
            $attrs = [
                'name'        => $name,
                'resident_no' => $residentNo,
                'mobile'      => $mobile,
                'address'     => $address,
                'birth_date'  => $birth,
                'gender'      => $gender,
            ];
            // 사업부는 골랐을 때만 넣는다 — 칸이 없는 서버에서 빈 값을 끼우면 질의가 깨진다
            if (!empty($d['care_type'])) {
                $attrs['care_type'] = $d['care_type'];
            }

            $patient = Patient::create($attrs);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($patient)
                ->log("{$name} 환자 자동 등록 (처방전 {$prescription->rx_number})");
        }

        // 처방전에 patient_id 연결
        $prescription->update(['patient_id' => $patient->id]);
    }

    // ── 메모 CRUD ─────────────────────────────────────────

    public function storeMemo(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $memo = $prescription->memos()->create([
            'user_id' => Auth::id(),
            'content' => $request->input('content', ''),
        ]);
        $memo->load('user');

        return response()->json([
            'id'         => $memo->id,
            'content'    => $memo->content,
            'user_name'  => $memo->user?->name ?? '-',
            'created_at' => $memo->created_at->format('Y-m-d H:i'),
            'is_pinned'  => false,
            'pin_x'      => null,
            'pin_y'      => null,
        ]);
    }

    public function updateMemo(Request $request, Prescription $prescription, \App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->update(['content' => $request->input('content', '')]);
        return response()->json(['ok' => true]);
    }

    public function destroyMemo(Prescription $prescription, \App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->delete();
        return response()->json(['ok' => true]);
    }

    public function toggleMemoPin(Request $request, Prescription $prescription, \App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->update([
            'is_pinned' => !$memo->is_pinned,
            'pin_x'     => $request->input('pin_x', $memo->pin_x),
            'pin_y'     => $request->input('pin_y', $memo->pin_y),
        ]);
        return response()->json([
            'is_pinned'  => $memo->is_pinned,
            'content'    => $memo->content,
            'user_name'  => $memo->user?->name ?? '-',
            'created_at' => $memo->created_at->format('Y-m-d H:i'),
            'rx_number'  => $prescription->rx_number,
            'pin_x'      => $memo->pin_x,
            'pin_y'      => $memo->pin_y,
        ]);
    }

    public function pinMemoGlobal(Request $request, \App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->update([
            'pin_x' => $request->input('pin_x', $memo->pin_x),
            'pin_y' => $request->input('pin_y', $memo->pin_y),
        ]);
        return response()->json(['ok' => true]);
    }

    public function updateMemoGlobal(Request $request, \App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->update(['content' => $request->input('content', $memo->content)]);
        return response()->json(['ok' => true]);
    }

    public function unpinMemo(\App\Models\PrescriptionMemo $memo): \Illuminate\Http\JsonResponse
    {
        $memo->update(['is_pinned' => false, 'pin_x' => null, 'pin_y' => null]);
        return response()->json(['ok' => true]);
    }

    public function pinnedMemos(): \Illuminate\Http\JsonResponse
    {
        $memos = \App\Models\PrescriptionMemo::with(['prescription', 'user'])
            ->where('is_pinned', true)
            ->latest()
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'user_name'  => $m->user?->name ?? '-',
                'created_at' => $m->created_at->format('Y-m-d H:i'),
                'rx_number'  => $m->prescription?->rx_number ?? '',
                'pin_x'      => $m->pin_x,
                'pin_y'      => $m->pin_y,
            ]);

        return response()->json($memos);
    }
}
