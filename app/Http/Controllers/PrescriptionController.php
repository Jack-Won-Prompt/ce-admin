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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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
        /* 나간 것은 발송 내역에 쌓여야 한다 — 팝빌을 곧바로 부르면 그 자취가 없다 */
        private readonly \App\Services\MessageSender $sender,
    ) {}

    // ── 처방전 목록 ───────────────────────────────────────
    public function index(Request $request): View
    {
        /* 올린 파일이 몇 장인지 목록에서 바로 보인다 (2026-09-10 지시).
           줄마다 물으면 마흔 줄에 마흔 번을 묻는다 — 한 번에 세어 온다. */
        $query = Prescription::with(['patient', 'assignedUser', 'creator', 'order'])
            ->withCount('attachments')
            /* 아직 안 닫힌 다시 올리기 요청이 몇 건인가 (2026-09-12 지시).
               줄마다 물으면 마흔 줄에 마흔 번을 묻는다 — 한 번에 세어 온다. */
            ->withCount(['reuploadRequests as open_reuploads_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->latest();

        // '처방전 관리' 로 화면만 열고 아무것도 입력하지 않은 초안은 목록에 띄우지 않는다
        $query->whereNot(fn ($q) => $q->blankDraft());

        /* 상담만 적어 둔 건도 띄우지 않는다 (2026-09-14 지시).

           상담 한 건이 처방전 한 줄을 차지하는 구조라, 같은 사람에게 상담을 두 번
           적으면 목록에 두 줄이 섰다 — 자료를 한 번 올렸을 뿐인데 처방전이 둘로
           갈라진 것으로 보인다. 상담만 적힌 줄은 아직 처방전이라 부를 것이 없다.
           거래처 상세의 상담 이력에서는 그대로 보인다.

           업로드는 이 줄을 이어 쓰지 않는다 (2026-09-15 지시). 여태는 찾아 이어
           썼으나, 올린 자료가 엉뚱한 옛 번호에 붙는 일이 있어 이제 올릴 때마다 새
           번호를 만든다 — 상담 줄은 상담 줄대로 남는다. */
        $query->whereNot(fn ($q) => $q->counselOnly());

        /* 주문이 접수된 건은 띄우지 않는다 (2026-09-15 지시).

           처방전 목록은 「아직 검수가 남은 것」을 보는 자리다. 주문이 서고 나면
           그 건을 보는 자리는 주문 관리이지 여기가 아니다 — 두 목록에 같은 건이
           서 있으면 어디서 손대야 하는지가 흐려진다.

           상태로 고른 때는 그 잣대를 따른다. 「주문 완료」를 골라 놓고도 안
           보이면 목록이 고장 난 것으로 읽힌다. 찾는 말이 있을 때도 푼다 —
           이름을 치는 까닭은 대개 「이 사람 건이 지금 어떻게 되어 있나」라서,
           주문까지 간 건이야말로 그때 가장 먼저 찾는 것이다. */
        if (! $request->filled('status') && ! $request->filled('search')) {
            $query->where('status', '!=', 'ordered');
        }

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
                  /* 요양기관코드(병원코드)로도 찾는다 — 공단과 맞출 때 병원 이름보다
                     이 번호를 들고 오는 일이 많다(2026-09-08 확인요청 7쪽) */
                  ->orWhere('hospital_code', 'like', "%{$kw}%")
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
                'hosp_code'  => $rx->hospital_code ?? '',
                'issued'     => $rx->issued_date?->format('Y-m-d') ?? '',
                'status'     => $rx->status_label,
                'acc_type'   => $rx->accTypeLabel(),
                'so_type'    => $soType ? (Order::SO_TYPE_LABELS[$soType][0] ?? $soType) : '-',
                'order_no'   => $order?->order_number ?? '',
                'so_no'      => $order?->withworks_so_no ?? '',
                'assignee'   => $rx->assignedUser?->name ?? '미지정',
                // 요청서 6쪽 — 목록에서 바로 견주는 값들
                'resident_no'  => $rx->resident_no_ocr_masked ?? $rx->patient?->masked_resident_no ?? '',
                'uploader'     => $rx->creator?->name ?? '',
                'reviewed_at'  => $rx->reviewed_at?->format('Y-m-d H:i') ?? '',
                'review_memo'  => $rx->review_memo ?? '',
                'review_request_memo' => (\Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'review_request_memo')
                                            ? ($rx->review_request_memo ?? '') : ''),
                'created'    => $rx->created_at?->format('Y-m-d H:i') ?? '',

                /* 올린 파일 — 처방전 그림 한 장에 첨부를 더한다(2026-09-10 지시).
                   생성 서류(위임장 따위)는 우리가 만든 것이라 세지 않는다. */
                'files'      => $rx->attachments_count + ($rx->image_path ? 1 : 0),
                /* 「파일 검수」 단추가 설 자리. 값은 상태를 담아 둔다 — 이미 마친 건은
                   단추가 「검수 완료」로 서고 눌러도 다시 승인하지 않는다. */
                'review'     => $rx->status,

                /* 다시 올리기를 물어 둔 것이 있나 (2026-09-12 지시). 파일 검수
                   바로 옆에 세운다 — 「검수했나」와 「되물었나」는 잇대어 읽는 값이다. */
                'reupload'   => $rx->open_reuploads_count,
            ];
        });
        $total = $gridData->count();

        $statusCounts = [
            'all'            => Prescription::count(),
            'review_needed'  => Prescription::where('status', 'review_needed')->count(),
            'review_requested' => Prescription::where('status', 'review_requested')->count(),
            // 되물은 건과 되돌아온 건 (2026-09-15 지시)
            'review_hold'    => Prescription::where('status', 'review_hold')->count(),
            'review_resent'  => Prescription::where('status', 'review_resent')->count(),
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
    /**
     * 위드웍스 화면으로 건너갈 주소를 받아 온다.
     *
     * 저쪽이 서명된 한 번짜리 로그인 주소를 만들어 준다(2분). 그 주소로 열면 연동
     * 계정으로 로그인된 채 판매주문 화면이 이 번호로 열린다.
     *
     * 저쪽이 아직 그 길을 모르면(구버전) 그냥 판매주문 주소를 준다 — 위드웍스에
     * 로그인해 둔 브라우저면 그대로 열리고, 아니면 로그인 화면이 먼저 뜬다.
     */
    public function withworksSoLink(Prescription $prescription): JsonResponse
    {
        /* 어느 주문의 판매번호인가 (2026-09-16 고침).

           이 주소는 show() 를 거치지 않으므로 $prescription->order 가 늘 **첫 주문**
           (원 주문)이다. 그래서 추가 주문 화면에서 연동 단추를 눌러도 원 주문의
           판매번호로 위드웍스가 열렸다 — 화면에는 추가 주문 번호가 적혀 있는데
           건너가면 다른 주문이 서 있었다.

           화면이 보고 있는 주문 번호를 함께 받아 그것으로 가린다. 넘어오지 않은
           옛 호출은 여태처럼 첫 주문으로 떨어진다. */
        $order = ($번호 = trim((string) request('order')))
            ? $prescription->orders()->where('order_number', $번호)->first()
            : null;

        $soNo = ($order ?? $prescription->order)?->withworks_so_no;
        if (!$soNo) {
            return response()->json(['success' => false, 'message' => '아직 위드웍스에 연계되지 않은 주문입니다.'], 422);
        }

        $baseUrl = rtrim((string) config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');
        $plain   = $baseUrl . '/salesorder?so_no=' . urlencode($soNo);

        if (!$baseUrl) {
            return response()->json(['success' => false, 'message' => '위드웍스 주소가 설정되어 있지 않습니다.'], 500);
        }

        try {
            $res = Http::withToken($token)->timeout(8)
                ->get("{$baseUrl}/api/v1/ce-admin/sso_link", ['so_no' => $soNo]);

            $url = $res->successful() && ($res->json('success') ?? false)
                ? ($res->json('result.url') ?? null)
                : null;

            return response()->json([
                'success'   => true,
                'url'       => $url ?: $plain,
                'auto_login'=> (bool) $url,
            ]);
        } catch (\Throwable $e) {
            Log::warning('위드웍스 로그인 링크 실패', ['so_no' => $soNo, 'error' => $e->getMessage()]);

            return response()->json(['success' => true, 'url' => $plain, 'auto_login' => false]);
        }
    }

    /**
     * 창고로 보낼 판매주문 내용 — 등록과 정정이 **같은 것**을 쓴다.
     *
     * 두 곳이 따로 만들면 정정한 건만 다른 값으로 창고에 선다. 실제로 청구전략이
     * 그랬다 — 등록은 셈해 보내고 수정은 25 를 박아 보내, 고치는 순간 전략이
     * 바뀌었다(2026-09-15 에 한 자리로 모았다).
     */
    /**
     * 창고로 보낼 내용.
     *
     * 꼴을 만드는 곳은 **한 곳뿐이다**(WithworksLink::창고내용) — 결제전송도 같은
     * 것을 쓴다 (2026-09-16 지시). 두 곳이 따로 만들면 어느 길로 갔느냐에 따라
     * 저쪽에 다른 값이 서고, 그 어긋남은 출고 뒤에야 드러난다.
     *
     * 화면이 들고 있는 값으로 몇 칸을 덮는다 — 적어 두었지만 아직 저장되지 않은
     * 값이 있기 때문이다. 널ㆍ빈 글자는 덮지 않는다.
     */
    private function withworksPayload(Request $request, Prescription $prescription): array
    {
        $order = $prescription->orders()
                    ->where('order_number', $request->input('order_number'))->first()
                 ?? $prescription->order;

        if (! $order) {
            abort(404, '주문을 찾을 수 없습니다.');
        }

        return app(\App\Services\WithworksLink::class)->창고내용($order, [
            'ce_order_number'         => $request->input('order_number'),
            'shipping_address'        => $request->input('shipping_address'),
            'shipping_address_detail' => $request->input('shipping_address_detail'),
            'delivery_date'           => $request->input('delivery_date'),
            'recipient_name'          => $request->input('recipient_name'),
            'ho_account_id'           => $request->input('ho_account_id'),
            'items'                   => $request->input('items'),
        ]);
    }

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

        /* 아직 살 때가 아니면 여기서 멈춘다(요청서 2쪽, 2026-08-31). 물건이 나가면
           되돌릴 수 없고, 이르게 나간 건은 나중에 청구가 반려된다 — 창고에 넘기기
           전인 이 자리가 막을 수 있는 마지막 곳이다. */
        if ($why = \App\Support\RepurchaseWindow::block($prescription)) {
            return response()->json(['success' => false, 'message' => $why], 422);
        }

        /* 위임 서명이 없으면 창고로 보내지 않는다 (2026-09-14 지시).

           여태 이 자리는 동의를 보지 않았다 — 주문 줄은 저장만 해도 서 있으므로
           (OrderSync) 이 주소를 바로 부르면 서명 없이 창고로 나가고, 연계가 되는
           순간 결제 안내까지 나갔다. */
        if ($why = \App\Support\DelegationGate::block($prescription)) {
            return response()->json([
                'success' => false,
                'code'    => \App\Support\DelegationGate::CODE,
                'message' => $why,
            ], 422);
        }

        /* 받는 주소가 없으면 보내지 않는다.

           주소 없이 나간 주문은 창고에 「받는 곳이 없는 출고」로 서고, 송장을 낼 때
           그 자리에서 깨진다(3PL 송장출력이 배송지를 조인한다). 그때는 이미 늦다 —
           창고가 손을 댄 뒤에는 저쪽이 주문 수정을 막는다.

           화면에서도 막지만(gateShippingAddress) 여기서도 막는다 — 화면을 거치지
           않고 부르는 길이 있고, 막지 못하면 되돌릴 수 없는 주문이 선다. */
        $addrForCheck = $request->shipping_address
            ?? $prescription->order?->shipping_address
            ?? $prescription->address_ocr
            ?? null;

        if (blank($addrForCheck)) {
            return response()->json([
                'success' => false,
                'message' => '받는 주소가 없어 창고로 보낼 수 없습니다 — 배송지를 먼저 채워 주십시오.',
            ], 422);
        }

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        if (!$baseUrl || !$token) {
            return response()->json(['success' => false, 'message' => '위드웍스 API 설정이 없습니다.'], 500);
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

        $payload = $this->withworksPayload($request, $prescription);

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asForm()
                ->post("{$baseUrl}/api/v1/ce-admin/so_store", $payload);

            $body = $response->json();

            // 보낸 것을 그대로 이메일로도 남긴다 — 저쪽 화면과 나란히 견주려는 것이다
            \App\Services\WithworksNotice::sent('주문 등록', 'so_store', $payload, $body);

            if ($response->successful() && ($body['success'] ?? false)) {
                $result = $body['result'] ?? [];
                $soNo   = $result['so_no'] ?? null;

                /* 판매번호는 **보낸 그 주문**에 적는다 (2026-09-16 고침).

                   여태 $prescription->order 에 적었는데 그것은 언제나 첫 주문이라,
                   추가 주문을 보낼 때마다 원 주문의 판매번호가 새것으로 덮였다.
                   추가 주문 자신은 끝내 판매번호를 받지 못해 창고와 이어지지 않았다.
                   창고로 보낸 주문번호는 위에서 이미 검증했으므로 그것으로 찾는다. */
                $보낸주문 = $prescription->orders()
                    ->where('order_number', $request->input('order_number'))->first()
                    ?? $prescription->order;

                if ($보낸주문) {
                    $updateData = [];
                    if ($soNo)                          $updateData['withworks_so_no'] = $soNo;
                    if ($result['so_id'] ?? null)       $updateData['withworks_so_id'] = $result['so_id'];
                    if (!empty($updateData)) {
                        try { $보낸주문->update($updateData); } catch (\Throwable) {}
                    }
                }

                activity()->causedBy(Auth::user())->performedOn($prescription)
                    ->log("위드웍스 판매주문 연계: {$soNo}");

                /* 창고에 판매주문이 섰다 — 이제 고객에게 알린다. 여기까지 와야 「확정」이다.
                   보내지 못해도 주문은 이미 선 것이라 되돌리지 않는다. 무슨 일이 있었는지는
                   답에 실어 화면이 함께 보여 준다. */
                $sms = $this->sendOrderConfirmedSms($prescription, $보낸주문);

                /* 받을 돈이 없는 건은 여기서 증빙을 낸다 (2026-09-16 지시).

                   본인부담금이 0원이면 입금 확인이 영영 서지 않아, 출고까지 끝나도
                   세금계산서와 거래명세서가 만들어지지 않았다. 창고로 보내는 이 자리가
                   그 건의 「확정」이다.

                   받을 돈이 있는 건은 안에서 스스로 지나간다 — 그 건은 토스 웹훅이
                   결제를 알릴 때 낸다. 이미 발행된 것도 안에서 거른다. */
                if ($보낸주문 && (int) $보낸주문->expectedDeposit() === 0) {
                    try {
                        app(\App\Services\DepositAutoIssue::class)
                            ->run($보낸주문->refresh(), '주문 연계(본인부담금 없음)');
                    } catch (\Throwable $e) {
                        Log::warning('[주문 연계] 증빙 발행 실패', [
                            'order' => $보낸주문->order_number, 'error' => $e->getMessage(),
                        ]);
                    }
                }

                $accountNew  = $result['patient_account_new'] ?? false;
                $addressNew  = $result['patient_address_new'] ?? false;

                $detail = [];
                if ($accountNew) $detail[] = '환자 거래처 신규 등록';
                if ($addressNew) $detail[] = '배송지 신규 등록';

                return response()->json([
                    'success' => true,
                    'so_no'   => $soNo,
                    'message' => '위드웍스 판매주문이 생성되었습니다.' . ($detail ? ' (' . implode(', ', $detail) . ')' : ''),
                    'patient_account_id' => $result['patient_account_id'] ?? null,
                    'patient_address_id' => $result['patient_address_id'] ?? null,
                    'sms'     => $sms,
                ]);
            }

            $errMsg = $body['message'] ?? "HTTP {$response->status()}";
            Log::warning('Withworks SO 생성 실패', [
                'status' => $response->status(),
                'body'   => $body,
                'raw'    => substr($response->body(), 0, 500),
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json(['success' => false, 'message' => "위드웍스 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '위드웍스 서버에 연결할 수 없습니다.'], 500);
        }
    }

    /**
     * 주문 확정 안내 — 창고에 판매주문이 선 뒤에 보낸다.
     *
     * 「주문 생성 및 연계」를 누른 그 순간이 아니라, 저쪽에 줄이 실제로 선 뒤여야 한다.
     * 우리 쪽만 만들어 두고 알렸다가 연계가 실패하면, 고객은 확정 문자를 받았는데
     * 창고에는 아무것도 없는 꼴이 된다.
     *
     * 보내는 말은 「주문이 확정됐다」가 아니라 「어떻게 내시면 된다」다 — 확정을 알리는
     * 일과 돈을 받는 일이 문자 두 통으로 갈리면 고객은 두 번 읽고 한 번 헷갈린다.
     * 그래서 결제전송(PaymentLinkService)이 쓰는 그 길을 그대로 탄다. 결제 이력에도
     * 같이 쌓이고, 담당자가 손으로 다시 보낼 때와 문구가 갈리지 않는다.
     *
     * 무엇으로 안내할지는 설정 → 서비스 설정 → 주문 에서 고른다(order.confirm_pay_method).
     * 링크페이는 토스페이먼츠 승인을 받아야 쓸 수 있어, 키가 비어 있으면 무통장입금으로
     * 내려가 보낸다 — 승인 전에 골라 두었다고 아무것도 못 보내서는 안 된다.
     *
     * @return array{sent: bool, method: string, message: string}
     */
    /**
     * 창고에 판매주문이 선 뒤 고객에게 결제를 안내한다.
     *
     * @param Order|null $대상 안내할 주문. 비우면 첫 주문을 본다 — 추가 주문을
     *                         연계하고도 첫 주문(원 주문)으로 안내가 나가던 것을
     *                         막는다 (2026-09-16 고침).
     */
    private function sendOrderConfirmedSms(Prescription $prescription, ?Order $대상 = null): array
    {
        $prescription->refresh()->loadMissing('patient', 'order');
        $order = $대상?->refresh() ?? $prescription->order;

        if (! $order) {
            return ['sent' => false, 'method' => '', 'message' => '주문이 없어 결제 안내를 보내지 못했습니다.'];
        }

        /* 위임 서명이 없으면 접수 안내도 결제 안내도 보내지 않는다 (2026-09-14 지시).
           연계 앞에서 이미 막지만, 결제 문자는 한 번 나가면 거둘 수 없어 여기서도 본다. */
        if (\App\Support\DelegationGate::block($prescription)) {
            return ['sent' => false, 'method' => '', 'message' => '위임 서명이 완료되지 않아 결제 안내를 보내지 않았습니다.'];
        }

        /* 주문이 접수됐다는 것을 먼저 알린다(2026-09-03 확정 · 시나리오 2.2).

           한때는 한 통으로 합쳤다 — 「확정을 알리는 일과 돈을 받는 일이 문자 두 통으로
           갈리면 고객은 두 번 읽고 한 번 헷갈린다」는 까닭이었다. 그러나 받을 돈이
           없는 건(차상위경감ㆍ기초)은 결제 안내를 보내지 않으므로, 합쳐 두면 그 사람들은
           주문이 섰다는 것조차 듣지 못했다. 접수는 누구에게나 알린다. */
        $received = $this->sendOrderReceivedSms($prescription, $order);

        /* 받을 돈이 없으면 결제 안내는 보내지 않는다. 차상위경감ㆍ기초는 본인부담이
           0 이라, 그대로 두면 환자에게 「0원을 입금해 주십시오」가 나갔다. */
        if ($order->expectedDeposit() <= 0) {
            return [
                'sent'    => false,
                'method'  => '',
                'message' => ($received ? '주문 접수 안내를 보냈습니다. ' : '')
                           . '본인부담금이 없어 결제 안내는 보내지 않았습니다.',
            ];
        }

        $method = $this->confirmPayMethod($order);
        $mobile = $prescription->patient?->mobile ?: $prescription->mobile_ocr;

        /* 가상계좌는 주소를 보내는 것이 아니라 계좌를 발급해 적어 보내는 것이라
           길이 다르다. 발급하고, 거래처에 적어 두고, 문자로 보낸다(2026-09-03). */
        if ($method === \App\Models\PaymentLink::METHOD_VIRTUAL) {
            $out = app(\App\Services\VirtualAccountForOrder::class)->issueAndNotify($order);

            if ($out['sent']) {
                activity()->causedBy(Auth::user())->performedOn($prescription)
                    ->log('주문 확정 안내(가상계좌) 발송 → ' . ($order->patient?->mobile ?: '-'));
            }

            return ['sent' => $out['sent'], 'method' => '가상계좌', 'message' => $out['message']];
        }

        try {
            $res = app(\App\Services\PaymentLinkService::class)->issue($order, $method, $mobile);
        } catch (\Throwable $e) {
            Log::error('[주문 확정 안내] 발송 실패', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);
            return ['sent' => false, 'method' => $method, 'message' => '결제 안내 발송 실패: ' . $e->getMessage()];
        }

        /* 화면에 적는 이름. 예전에는 결제전송 팝오버만 「카드결제」라 불러 여기서 따로
           「링크페이」로 바꿔 적었는데, 이제 이름표가 한 가지라 그대로 쓴다. */
        $label = \App\Models\PaymentLink::METHODS[$method] ?? $method;

        if ($res['sent'] ?? false) {
            activity()->causedBy(Auth::user())->performedOn($prescription)
                ->log("주문 확정 안내({$label}) 발송 → " . ($res['link']->receiver ?? '-'));
        }

        $sent = (bool) ($res['sent'] ?? false);

        return [
            'sent'    => $sent,
            'method'  => $label,
            // 보냈으면 「무엇으로 갔나」, 못 보냈으면 「왜 못 갔나」를 그대로 적는다
            'message' => $sent
                ? "{$label} 안내를 " . ($res['message'] ?? '보냈습니다.')
                : ($res['message'] ?? '보내지 못했습니다.'),
        ];
    }

    /**
     * 주문이 접수됐다는 것을 알린다(2026-09-03 확정 · 시나리오 2.2).
     *
     * 결제 안내와 갈라 둔다. 한때는 한 통으로 합쳤으나, 받을 돈이 없는 건(차상위경감ㆍ
     * 기초)은 결제 안내를 보내지 않으므로 그 사람들은 주문이 섰다는 것조차 듣지
     * 못했다. 접수는 누구에게나 알린다.
     *
     * 두 번 보내지 않는다 — 저장을 다시 눌러도 접수 통지가 또 가면 안 된다.
     *
     * 못 보내도 결제 안내를 막지 않는다. 둘은 다른 일이다.
     */
    private function sendOrderReceivedSms(Prescription $prescription, \App\Models\Order $order): bool
    {
        $source = 'order-received';

        $mobile = preg_replace('/\D/', '',
            (string) ($prescription->patient?->mobile ?: $prescription->mobile_ocr));

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return false;
        }

        if ($prescription->id && \App\Models\MessageHistory::where('source', $source)
                ->where('prescription_id', $prescription->id)
                ->where('success_count', '>', 0)
                ->exists()) {
            return false;                                  // 이미 알렸다
        }

        // 환자가 받는 문자다 — (E) 를 뗀다(2026-09-10 지시)
        $name = \App\Models\Patient::bare($prescription->patient?->name) ?: ($prescription->patient_name_ocr ?: '고객');

        /* 문구는 메시지 유형(SMS ▸ 주문 확정)에 적어 둔 것을 쓴다 — 담당자가 화면에서
           고칠 수 있어야 하고, 손으로 보낼 때와 갈리지 않아야 한다. */
        $body = \App\Models\MessageTemplate::channel('sms')->active()
            ->where('code', 'order_confirmed')->value('body')
            ?: "[콜로플라스트] #{고객명}님, 주문이 접수되었습니다.\n주문번호: #{주문번호}\n본인 부담금: #{본인부담금}원";

        /* 받을 돈이 없는 건(차상위경감ㆍ기초)은 부담금 줄을 통째로 뺀다 —
           「본인 부담금: 0원」은 빠뜨린 것처럼 읽힌다. 담당자가 문구를 고쳐도
           자리표만 찾으면 되므로 그대로 걸린다. */
        if ((int) $order->expectedDeposit() <= 0) {
            $body = implode("\n", array_filter(
                explode("\n", $body),
                fn ($line) => ! str_contains($line, '#{본인부담금}'),
            ));
        }

        $text = strtr($body, [
            '#{고객명}'     => $name,
            '#{주문번호}'   => $order->order_number,
            '#{본인부담금}' => number_format((int) $order->expectedDeposit()),
            '#{처방번호}'   => $prescription->rx_number,
        ]);

        try {
            $res = app(\App\Services\MessageSender::class)->sendBulk(
                'sms',
                [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $prescription->patient_id]],
                $text,
                null,
                ['source' => $source, 'prescription_id' => $prescription->id],
            );
        } catch (\Throwable $e) {
            Log::warning('[주문 접수 안내] 보내지 못했다', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($res['success'] ?? false) {
            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("주문 접수 안내 발송 → {$mobile}");

            return true;
        }

        return false;
    }

    /**
     * 주문 확정 안내를 무엇으로 보낼지.
     *
     * 링크페이는 토스 결제 페이지를 여는 것이라 클라이언트 키·시크릿 키가 있어야 한다.
     * 승인 전에 골라 두었으면 무통장입금으로 내려간다 — 보내지 못하고 멈추는 것보다
     * 계좌라도 적어 보내는 편이 낫다.
     */
    private function confirmPayMethod(?\App\Models\Order $order = null): string
    {
        /* 주문에 적힌 것이 먼저다(2026-09-03). 여태 설정 하나로 정해져 어느 환자든
           같은 안내가 나갔는데, 사람마다 내는 방법이 다르다. 적힌 것이 없으면
           예전처럼 설정을 따른다 — 다른 길로 만든 옛 주문이 갑자기 멈추지 않게. */
        $want = $order?->pay_method
            ?: (config('order.confirm_pay_method', 'bank') === 'card'
                ? \App\Models\PaymentLink::METHOD_CARD
                : \App\Models\PaymentLink::METHOD_BANK);

        /* 링크페이도 가상계좌도 토스를 거친다 — 키가 없으면 둘 다 못 한다.
           보내지 못하고 멈추는 것보다 계좌라도 적어 보내는 편이 낫다. */
        $needsToss = in_array($want, [
            \App\Models\PaymentLink::METHOD_CARD,
            \App\Models\PaymentLink::METHOD_VIRTUAL,
        ], true);

        if ($needsToss && (! config('toss.client_key') || ! config('toss.secret_key'))) {
            Log::warning('[주문 확정 안내] 토스 키가 없어 무통장입금으로 보낸다', ['want' => $want]);
            return \App\Models\PaymentLink::METHOD_BANK;
        }

        return $want;
    }

    // ── Withworks 판매주문 수정 연계 ──────────────────────
    /**
     * 주문 정정 — 원 판매주문을 취소하고 새로 세운다 (2026-09-15 지시).
     *
     * 여태 so_update 로 **같은 판매주문을 제자리에서** 고쳤다. 그래서 창고가
     * 할당ㆍ피킹에 손을 댄 건은 저쪽이 거절했고, 우리 화면도 아예 단추를 잠갔다 —
     * 담당자는 창고에 전화를 걸어 되돌려 달라 부탁하고 나서야 고칠 수 있었다.
     *
     * 이제 창고 단계가 길을 가른다. 몸통은 OrderAmendService 에 있다.
     */
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

        /* 화면이 보고 있는 주문을 고친다 (2026-09-16 고침).

           $prescription->order 는 언제나 첫 주문이라, 추가 주문을 정정하면 원 주문이
           취소되고 새로 등록됐다 — 고치려던 주문은 그대로 남았다. 위에서 검증한
           order_number 로 찾는다. */
        $order = $prescription->orders()
            ->where('order_number', $request->input('order_number'))->first()
            ?? $prescription->order;

        if (! $order) {
            return response()->json(['success' => false, 'message' => '주문을 찾을 수 없습니다.'], 404);
        }

        /* 새로 세울 내용 — 처음 등록할 때와 **같은 것**을 쓴다. 두 곳이 따로
           만들면 정정한 건만 다른 값으로 창고에 서게 된다. */
        $창고내용 = $this->withworksPayload($request, $prescription);

        $결과 = app(\App\Services\OrderAmendService::class)
                    ->정정($order->refresh(), $창고내용, (int) $order->결제기준금액());

        if (! $결과['ok']) {
            return response()->json([
                'success' => false,
                'message' => $결과['message'],
                'state'   => $결과['state'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'so_no'   => $결과['so_no'],
            'state'   => $결과['state'],
            'message' => $결과['message'] ?: '위드웍스 판매주문을 재등록했습니다.',
        ]);
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
            return response()->json(['success' => false, 'message' => '위드웍스 API 설정이 없습니다.'], 500);
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
                    ->log("위드웍스 판매주문 삭제: {$request->order_number}");

                return response()->json(['success' => true, 'message' => '위드웍스 판매주문이 삭제되었습니다.']);
            }

            $errMsg = $body['message'] ?? "HTTP {$response->status()}";
            Log::warning('Withworks SO 삭제 실패', ['status' => $response->status(), 'body' => $body]);
            return response()->json(['success' => false, 'message' => "위드웍스 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류 (삭제)', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '위드웍스 서버에 연결할 수 없습니다.'], 500);
        }
    }

    /** 끝 글자에 받침이 있는가 — 은/는, 이/가 를 고르는 데 쓴다 */
    private static function hasFinalConsonant(string $word): bool
    {
        $last = mb_substr(trim($word), -1);
        $code = mb_ord($last, 'UTF-8');

        if ($code < 0xAC00 || $code > 0xD7A3) {
            return false;                       // 한글이 아니면 가리지 않는다
        }

        return (($code - 0xAC00) % 28) !== 0;
    }

    /**
     * 유형마다 받을 수 있는 수 (테스트 시나리오 시작 포인트 · 2026-09-03).
     *
     *   처방전     1     한 번에 한 건이다. 두 장이면 처방전이 두 건으로 갈라져
     *                    주문도 둘이 서고, 그 뒤 청구가 어느 쪽인지 흐려진다.
     *   등록신청서 1     공단에 한 장만 낸다.
     *   결과지     40    검사지는 여러 장이다. 40 은 위쪽 전체 제한과 같다.
     *
     * 적어 두지 않은 유형(신분증ㆍ기타 따위)은 세지 않는다 — 정해진 바가 없다.
     *
     * @return string|null 넘었으면 그 말, 아니면 null
     */
    private function overDocLimit(array $prescriptionFiles, array $attachmentFiles): ?string
    {
        $limits = ['prescription' => 1, 'registration_form' => 1, 'test_result' => 40];
        $labels = ['prescription' => '처방전', 'registration_form' => '등록신청서', 'test_result' => '결과지'];

        $count = ['prescription' => count($prescriptionFiles)];

        foreach ($attachmentFiles as $a) {
            $t = $a['doc_type'];
            $count[$t] = ($count[$t] ?? 0) + 1;
        }

        foreach ($limits as $type => $max) {
            $n = $count[$type] ?? 0;
            if ($n > $max) {
                /* 「처방전는」이 되지 않게 받침을 본다 */
                $josa = self::hasFinalConsonant($labels[$type]) ? '은' : '는';

                return "{$labels[$type]}{$josa} 한 번에 {$max}건까지 올릴 수 있습니다 — {$n}건을 고르셨습니다. "
                     . '타일에서 서류 유형을 확인해 주십시오.';
            }
        }

        return null;
    }

    // ── 업로드 페이지 ─────────────────────────────────────
    public function uploadPage(Request $request): View
    {
        $prescriptions = Prescription::with(['patient', 'assignedUser'])->latest()->limit(5)->get();
        /* 담당자로 고를 수 있는 사람 — 처방전 목록 화면과 같은 잣대로 본다.

           여기만 role='manager' 만 보아, 관리자로 등록된 사람은 목록에 서지 않았다.
           지금 열 사람 가운데 다섯이 관리자다 — 자기 이름을 쳐도 「그런 담당자가
           없습니다」가 뜨니, 담당자를 비운 채 올리게 된다. 그러면 창고 소식이 갈
           곳이 없어진다(OrderNotice 는 담당자를 먼저 본다). */
        $managers      = User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get();
        // 화면으로 나가는 목록이므로 마스킹 컬럼만 읽는다 — 평문·암호문은 조회하지 않는다(P0-1)
        $patientsJson  = self::patientPickerList();

        // 화면 상단에 알리는 검수 대기 건수 (시안 128:3171)
        $reviewPending = Prescription::where('status', 'review_needed')->count();

        /* 서류명은 환경 설정에서 정한다 — 화면에 박아 두면 한 줄 늘리는 데도 배포가 필요했다.
           앱도 같은 목록을 받는다(GET /api/prescriptions/doc-types). 규칙은 한 자리에 둔다. */
        $docTypes = \App\Support\UploadDocTypes::grouped();

        return view('prescriptions.upload', compact('prescriptions', 'managers',
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
        /* 한 사람에게 초안은 하나다 (2026-09-11 보탬).

           「찾고 → 없으면 만든다」 사이에 틈이 있다. 단추를 겹눌렀거나 탭을 둘 열었거나
           새로고침이 겹치면 두 요청이 나란히 「없다」를 보고 둘 다 만든다 — 그럴 때마다
           처방번호가 하나씩 헛되이 나간다. 사람마다 빗장을 걸어 그 틈을 없앤다.

           빗장을 잡지 못해도 일은 이어 간다 — 초안 하나 더 서는 것이 화면이 열리지
           않는 것보다 낫다. 아래 자가 정리가 그것을 거둔다. */
        $자물쇠 = Cache::lock('rx-draft:' . Auth::id(), 10);
        $잡음   = false;

        try {
            $잡음  = $자물쇠->block(5);
            $draft = $this->빈초안잡기();
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            $draft = $this->빈초안잡기();
        } finally {
            if ($잡음) {
                $자물쇠->release();
            }
        }

        /* 유형은 처방외로 열어 둔다 (2026-09-15 지시).

           ［신규 등록］은 이제 **처방전 없이 시작하는 건**이 주된 용도다. 유형이
           비어 있으면 검수 문에서 막히고(gateReviewed) 청구전략도 서지 않아,
           담당자는 주문을 만들려다 두 번 되돌아와야 했다.

           처방전을 들고 온 건은 처방자료 업로드 화면으로 들어온다 — 그쪽은 이 초안을
           다시 쓰지 않고 늘 새 번호를 만든다(2026-09-15 · ac66043). 그래서 여기에
           기본값을 두어도 처방전 있는 건이 처방외로 시작하는 일은 없다.

           처방전이 뒤늦게 도착하면 담당자가 유형을 원내ㆍ원외로 바꾼다. 그때부터는
           검수를 지나야 하고, 자격과 함께 청구전략이 다시 선다.

           이미 골라 둔 초안은 그대로 둔다 — 되쓰는 초안의 담당자 선택을 덮지 않는다. */
        if (! $draft->counsel_acc_add_type) {
            $draft->forceFill(['counsel_acc_add_type' => '20'])->save();
        }

        /* 누구의 상담인지 정해 놓고 들어오는 길이 있다(거래처 관리의 「상담하기」).
           초안에 그 환자를 미리 붙여 두면 이름·연락처를 다시 치지 않아도 된다. */
        if ($request->filled('patient')) {
            $patient = \App\Models\Patient::find($request->patient);

            if ($patient) {
                /* 다른 사람으로 들어오면 갈아 끼운다 (2026-09-11 고침).

                   여태는 붙은 환자가 다르면 손대지 않고 지나갔다. 초안에 환자가 붙어도
                   다시 쓰이지 않던 때에는 그런 초안이 올 일이 없었는데, 이제 빈 초안은
                   환자가 붙어도 다시 쓰인다 — 그대로 두면 갑의 상담 화면에 을이 떠 있다.

                   빈 초안이라 잃을 것이 없다. 주문ㆍ동의ㆍ서류ㆍ첨부ㆍ메모가 하나라도
                   있으면 애초에 여기까지 오지 않는다(scopeBlankDraft). */
                $갈아낌 = $draft->patient_id && $draft->patient_id !== $patient->id;

                $draft->forceFill([
                    'patient_id'       => $patient->id,
                    'patient_name_ocr' => $갈아낌 ? $patient->name
                                                  : ($draft->patient_name_ocr ?: $patient->name),
                    'mobile_ocr'       => $갈아낌 ? ($patient->mobile ?: $patient->phone)
                                                  : ($draft->mobile_ocr ?: ($patient->mobile ?: $patient->phone)),
                ])->saveQuietly();
            }
        }

        // 팝업으로 열었으면 그 표시를 이어 준다 — 상담 창은 아래에 저장·닫기 띠를 세운다
        return redirect()->route('prescriptions.show', array_filter([
            'prescription' => $draft->rx_number,
            'popup'        => $request->boolean('popup') ? 1 : null,
        ]));
    }

    /**
     * 내 빈 초안을 하나 잡는다 — 있으면 다시 쓰고, 없으면 세운다.
     *
     * 빗장 안에서 부른다. 둘 이상 서 있으면 가장 나중 것만 남기고 거둔다 — 빗장이
     * 서기 전에 쌓인 것과, 빗장을 잡지 못한 요청이 만든 것이 여기서 정리된다.
     * 빈 초안에는 주문도 첨부도 동의도 없으므로(scopeBlankDraft) 거둬도 잃는 것이 없다.
     */
    private function 빈초안잡기(): Prescription
    {
        $것들 = Prescription::blankDraftsOf(Auth::id())->latest('id')->get();
        $draft = $것들->shift();

        foreach ($것들 as $여분) {
            activity()->causedBy(Auth::user())->performedOn($여분)
                ->log("빈 초안 정리 ({$여분->rx_number}) — 담당자별 초안은 하나만 유지합니다");
            $여분->delete();
        }

        if (! $draft) {
            return Prescription::create([
                'rx_number'      => Prescription::generateRxNumber(),
                'created_by'     => Auth::id(),
                'status'         => 'pending',
                'upload_source'  => 'web',
                'is_blank_draft' => true,
            ]);
        }

        /* 며칠 전 것이라면 번호와 접수일을 오늘 것으로 새로 매긴다. 그대로 두면
           처방번호에 박힌 날짜가 실제 접수일과 어긋난다(RX-20260807-001 을 8/15 에 접수).
           빈 초안이라 되돌아볼 내용이 없으므로 접수일을 옮겨도 잃는 것이 없다.
           saveQuietly — 저장 훅이 '내용이 생겼다'고 보고 초안 표시를 풀어 버린다. */
        if (! $draft->created_at->isToday()) {
            $draft->forceFill([
                'rx_number'  => Prescription::generateRxNumber(),
                'created_at' => now(),
            ])->saveQuietly();
        }

        return $draft;
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
            'review_request_memo'   => 'nullable|string|max:1000',
        ], [
            'patient_id.required' => '환자를 먼저 선택하십시오.',
        ]);

        /* 보낸 장수와 받은 장수를 견준다.

           PHP 는 `max_file_uploads`(기본 20)를 넘는 파일을 **라라벨이 보기 전에 조용히
           버린다.** 위의 `max:40` 은 이미 잘린 배열을 보므로 걸리지 않는다. 그래서
           결과지 열아홉 장짜리 건을 올리면 두 장이 사라진 채 「업로드 완료」가 떴다 —
           빠진 것을 아무도 모르고, 공단 팩스에도 그대로 빠진 채 나간다.

           브라우저가 몇 장을 보냈는지 함께 적어 보내므로 여기서 알 수 있다.
           모자라면 **아무것도 저장하지 않고** 무엇을 고쳐야 하는지 알린다. */
        $sent     = (int) $request->input('expected_file_count', 0);
        $received = count($request->file('prescription_images', []));

        if ($sent > 0 && $received < $sent) {
            $limit = (int) ini_get('max_file_uploads');

            return response()->json([
                'success' => false,
                'message' => "보낸 파일 {$sent}장 가운데 {$received}장만 서버에 닿았습니다 — "
                           . "한 번에 올릴 수 있는 파일이 {$limit}장으로 막혀 있습니다.

"
                           . '나누어 올리시거나, php.ini 의 max_file_uploads 를 늘려 주십시오. '
                           . '빠진 채로 저장하면 공단 팩스에도 빠진 채 나가므로 아무것도 저장하지 않았습니다.',
                'sent'     => $sent,
                'received' => $received,
                'limit'    => $limit,
            ], 422);
        }

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

        /* 막는 답은 화면이 부른 방식에 맞춰 돌려준다.
           업로드 화면은 ajax 로 보내는데 back()->with('error') 만 돌려주었더니,
           올라가지도 않고 화면에는 아무 말도 뜨지 않았다 — 담당자는 왜 안 되는지
           모른 채 다시 눌렀다(2026-09-03 시험 2차에서 드러났다). */
        $refuse = function (string $why) use ($request) {
            return ($request->expectsJson() || $request->ajax())
                ? response()->json(['success' => false, 'message' => $why], 422)
                : back()->with('error', $why);
        };

        /* **처방전이 없어도 받는다**(2026-09-09 지시).

           여태는 처방전 한 장을 반드시 청했다. 그런데 신분증ㆍ결과지만 먼저 들어오고
           처방전은 나중에 오는 건이 있다 — 그때 담당자는 올릴 자리가 없어, 처방전이
           올 때까지 서류를 손에 들고 기다렸다.

           처방전이 없으면 **그림 없는 처방전 한 건**을 세우고 서류를 거기에 단다.
           그 건은 검수 필요로 서므로 목록에서 눈에 띄고, 처방전은 주문 등록 화면의
           첨부 자리에서 나중에 붙인다.

           올린 것이 하나도 없으면 세울 것도 없다 — 그때만 물린다. */
        if (empty($prescriptionFiles) && empty($attachmentFiles)) {
            return $refuse('올릴 파일이 없습니다.');
        }

        /* 유형마다 받을 수 있는 수가 정해져 있다(테스트 시나리오 시작 포인트).
           한 번에 여러 장을 고르다 유형을 잘못 찍으면 처방전이 두 장 올라가고,
           그러면 처방전이 두 건으로 갈라져 주문도 둘이 된다. */
        if ($over = $this->overDocLimit($prescriptionFiles, $attachmentFiles)) {
            return $refuse($over);
        }

        $created         = [];
        $firstPrescription = null;

        /* 업로드는 **늘 새 처방전 번호**로 세운다 — 처방전 서류가 없어도 그렇다
           (2026-09-15 지시: 「업로드 시 기존의 처방전 번호에 업로드 하는 일은 절대 불가」).

           예전에는 같은 사람의 오늘 빈 건(상담하기ㆍ처방전 등록으로 먼저 선 줄)을 찾아
           거기에 그림을 채웠다(2026-09-10 확인요청 2쪽). 목록에 두 줄이 서지 않게 하려던
           것이지만, 올린 것이 이미 있던 번호로 들어가는 길이라 걷었다. */
        foreach ($prescriptionFiles as $file) {
            $subDir   = 'prescriptions/' . now()->format('Y/m');
            $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($subDir, $fileName, 'public');

            $그림칸 = [
                'image_path'          => $path,
                'image_original_name' => $file->getClientOriginalName(),
                'image_mime_type'     => $file->getMimeType(),
                'image_size'          => $file->getSize(),
                // OCR 은 쓰지 않는다 — 올리면 곧장 검수 필요로 두고 담당자가 손으로 적는다
                'status'              => 'review_needed',
            ];

            $prescription = Prescription::create([
                'rx_number'        => Prescription::generateRxNumber(),
                'patient_id'       => $request->patient_id ?: null,
                'assigned_user_id' => $request->assigned_user_id,
                'created_by'       => Auth::id(),
                'admin_note'       => $request->admin_note,
                'upload_source'    => 'web',
            ] + $그림칸);

            if (!$firstPrescription) {
                $firstPrescription = $prescription;
            }

            /* 예전에는 여기서 OCR 을 돌려 환자명ㆍ병원ㆍ상병 따위를 채우고,
               신뢰도 85 를 기준으로 OCR 완료 / 검수 필요를 갈랐다. 이제 쓰지 않는다 —
               숫자가 무엇을 뜻하는지 사람마다 달리 읽었고, 높든 낮든 어차피 눈으로 보고
               고쳤다. 올리면 검수 필요로 두고 담당자가 처음부터 손으로 적는다.
               환자 자동 연결도 OCR 이 읽은 이름에 기대던 것이라 함께 걷었다 —
               올릴 때 환자를 고르면 그 값(patient_id)이 그대로 들어간다. */

            // 상담번호 — 새 건이므로 여기서 매긴다
            $prescription->update([
                'counsel_no'   => $prescription->counsel_no ?: Prescription::generateCounselNo(),
                'counsel_date' => $prescription->counsel_date ?: now()->format('Y-m-d'),
            ]);

            $created[] = $prescription->rx_number;

            activity()->causedBy(Auth::user())->performedOn($prescription)
                      ->log("{$prescription->rx_number} 업로드 완료 (웹)");
        }

        /* 처방전 그림이 없는 건 — 서류만 먼저 왔다. 그림 없는 처방전 한 건을 세워
           서류를 달 자리를 만든다. 그림 칸은 비운다: 없는 것을 지어내지 않는다. */
        if (! $firstPrescription && ! empty($attachmentFiles)) {
            // 처방전이 없어도 새 번호로 세운다 — 기존 건에 달지 않는다(2026-09-15 지시)
            $firstPrescription = Prescription::create([
                'rx_number'        => Prescription::generateRxNumber(),
                'patient_id'       => $request->patient_id ?: null,
                'assigned_user_id' => $request->assigned_user_id,
                'created_by'       => Auth::id(),
                'admin_note'       => $request->admin_note,
                'upload_source'    => 'web',
                'status'           => 'review_needed',
            ]);

            $firstPrescription->update([
                'counsel_no'   => $firstPrescription->counsel_no ?: Prescription::generateCounselNo(),
                'counsel_date' => $firstPrescription->counsel_date ?: now()->format('Y-m-d'),
            ]);

            $created[] = $firstPrescription->rx_number;

            $무엇 = collect($attachmentFiles)
                ->pluck('doc_type')
                ->map(fn ($t) => PrescriptionAttachment::labelFor($t))
                ->unique()->implode('ㆍ');

            activity()->causedBy(Auth::user())->performedOn($firstPrescription)
                ->log("{$firstPrescription->rx_number} 업로드 완료 (웹 · 처방전 없이 {$무엇})");
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
        /* 올린 뒤에는 **처방전 목록**으로 간다 (2026-09-10 확인요청 4쪽).

           여태는 주문 등록 화면을 바로 열었다. 그런데 올린 자료는 먼저 검수해야 하고,
           검수는 처방전 목록에서 한다 — 목록의 「파일 검수」로 올린 것을 내리읽고
           그 자리에서 마친다. 주문 등록으로 곧장 보내면 그 걸음을 건너뛰게 된다.

           방금 올린 건이 첫 줄에 서도록 처방번호로 좁혀 둔다. 여러 건이면 좁히지 않고
           검수 필요만 걸어 둔다 — 무엇이 올라왔는지 한눈에 본다. */
        $목록주소 = count($created) === 1
            ? route('prescriptions.index', ['status' => 'review_needed', 'search' => $created[0]])
            : route('prescriptions.index', ['status' => 'review_needed']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'   => true,
                'rx_number' => $firstPrescription?->rx_number,
                'created'   => $created,
                'url'       => $목록주소,
                // 그 건을 바로 열어야 할 때 쓰는 자리 — 지금은 목록이 먼저다
                'rx_url'    => $firstPrescription ? route('prescriptions.show', $firstPrescription) : null,
                'message'   => count($created) === 1
                                 ? "{$firstPrescription->rx_number} 업로드 완료 — 처방전 목록에서 검수하십시오."
                                 : count($created) . '개 처방전 업로드 완료: ' . implode(', ', $created),
            ]);
        }

        return redirect()->to($목록주소)->with('success', count($created) === 1
            ? "{$firstPrescription->rx_number} 업로드 완료 — 처방전 목록에서 검수하십시오."
            : count($created) . '개 처방전 업로드 완료: ' . implode(', ', $created));
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

        /* 빠졌던 신분증이 이제 올라왔으면 공단 팩스를 다시 잰다.
           동의가 끝나는 그 순간에만 재던 것이라, 그때 없던 서류가 뒤에 올라와도
           아무도 다시 재지 않았다(order.nhis_fax_on_id_card 로 켠다). */
        if ($att->doc_type === 'id_card') {
            \App\Support\NhisFaxRetry::afterIdCard($prescription->refresh());
        }

        return response()->json([
            'success' => true,
            'attachment' => [
                'id'        => $att->id,
                'url'       => $att->file_url,
                'type'      => $att->doc_type,
                'typeLabel' => $att->doc_type_label,
                'name'      => $att->file_original_name,
                'isPdf'     => $att->is_pdf,
                /* 방금 올린 등록신청서에도 곧바로 신청인란을 얹을 수 있어야 한다 —
                   이 값이 없으면 화면을 새로 고치기 전에는 단추가 서지 않는다
                   (2026-09-17 지시) */
                'tuneKey'           => $att->is_image ? 'att:' . $att->id : null,
                'bright'            => 0,
                'contrast'          => 0,
                'regOverlay'        => $att->신청인란얹을수있나(),
                'regOverlayApplied' => false,
            ],
        ]);
    }

    /**
     * 문서 한 장의 밝기ㆍ명암을 적어 둔다 (2026-09-09 지시).
     *
     * 파일은 건드리지 않는다. 화면은 CSS 로, 공단 팩스와 서류는 GD 로 이 숫자를
     * 다시 입힌다 — 그래서 언제든 0 으로 되돌리면 원본이다.
     *
     * 예전 사진 보정은 올릴 때 파일을 그 자리에서 고쳤다. 스캐너로 곧게 뜬 것까지
     * 나빠졌고 되돌릴 길이 없어 걷어냈다. 같은 실수를 되풀이하지 않는다.
     */
    public function saveImageTune(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'key'        => 'required|string|max:32',
            'brightness' => 'required|integer|min:-100|max:100',
            'contrast'   => 'required|integer|min:-100|max:100',
        ]);

        $값 = ['img_brightness' => $data['brightness'], 'img_contrast' => $data['contrast']];

        if ($data['key'] === 'rx') {
            $대상 = $prescription;
            $무엇 = '처방전';
        } elseif (str_starts_with($data['key'], 'att:')) {
            /* 남의 처방전에 붙은 문서를 고치지 못하게 한다 — 열쇠는 화면에서 온다 */
            $대상 = PrescriptionAttachment::where('prescription_id', $prescription->id)
                ->findOrFail((int) substr($data['key'], 4));
            $무엇 = $대상->doc_type_label;
        } else {
            return response()->json(['success' => false, 'message' => '맞출 수 없는 문서입니다.'], 422);
        }

        $대상->forceFill($값)->save();

        activity()->causedBy(Auth::user())->performedOn($prescription)
            ->log("{$무엇}의 밝기 {$data['brightness']}ㆍ명암 {$data['contrast']} 로 맞췄습니다");

        /* 이미 만들어 둔 팩스통합본이 있으면 알려 준다 (2026-09-12 지시).

           숫자만 고쳐 두면 화면은 달라 보이는데 만들어 둔 PDF 는 옛 그림 그대로다 —
           그것을 내려받아 보낸 사람은 맞춘 적이 없는 셈이 된다. 다시 만드는 일은
           쪽을 펴고 굽느라 시간이 걸리므로, 부르는 쪽이 진행을 보여 줄 수 있게
           「있다」와 「어디로 부르면 되는가」만 돌려주고 실제 재생성은 따로 부른다. */
        $통합본있나 = PrescriptionDocument::where('prescription_id', $prescription->id)
            ->where('type', 'fax')->exists();

        return response()->json([
            'success'        => true,
            'has_fax_pdf'    => $통합본있나,
            'regenerate_url' => $통합본있나
                ? route('prescriptions.faxRegenerate', $prescription)
                : null,
        ]);
    }

    public function destroyAttachment(Prescription $prescription, PrescriptionAttachment $attachment): \Illuminate\Http\JsonResponse
    {
        if ($attachment->prescription_id !== $prescription->id) {
            abort(403);
        }

        /* 같은 파일을 가리키는 줄이 또 있으면 **파일은 남긴다.**

           「최종 신규 복제」는 파일을 복사하지 않고 **잇는다** — 같은 file_path 를
           두 건이 함께 가리킨다. 그때 한쪽에서 첨부를 지우며 디스크 파일까지 지우면
           원본 건의 그림이 사라진다. 목록에는 줄이 남고 열면 404 가 되어, 나중에 왜
           없어졌는지 알 길이 없다(2026-09-09 지시). */
        $함께쓴다 = PrescriptionAttachment::where('file_path', $attachment->file_path)
            ->where('id', '!=', $attachment->id)
            ->exists();

        if (! $함께쓴다) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json(['success' => true]);
    }

    // ── 등록신청서 신청인란 얹기 (2026-09-17 지시) ──────────
    //
    // 등록신청서는 병원이 ② 요양기관 확인란을 적고 확인해 내주는 종이다. 그 종이를
    // 찍어 올리면 ③ 신청인란 — 신청인ㆍ수진자와의 관계ㆍ전화번호ㆍ서명 — 은 비어
    // 있다. 위임장이 하는 일과 같게, 받아 둔 전자서명과 우리가 아는 값을 얹는다.
    //
    // 찍은 사진마다 자리가 달라 처음 자리만 세워 주고 담당자가 끌어 맞춘다.

    /** 얹을 것과 자리를 화면에 내준다 */
    public function registrationOverlay(Prescription $prescription, PrescriptionAttachment $attachment): \Illuminate\Http\JsonResponse
    {
        $this->이첨부인가($prescription, $attachment);

        /* 적어 둔 자리는 **처음 자리 위에 덮는다** (2026-09-17 지시).

           서식에 칸이 늘면(년월일을 더한 것처럼) 예전에 맞춰 둔 건에는 그 칸의 자리가
           없다. 덮어 읽으면 새 칸은 처음 자리에 서고, 맞춰 둔 칸은 그대로다.

           빼 둔 칸(off)은 따로 적는다 — 자리가 없는 것과 일부러 뺀 것을 가려야
           예전 자리를 그대로 두고도 뺀 칸이 되살아나지 않는다. */
        $적어둔 = $attachment->overlay_fields ?: [];
        $자리   = $적어둔['fields'] ?? $적어둔;              // 옛 꼴(자리만 적힌 것)도 읽는다
        $off    = $적어둔['off']    ?? [];
        $각도   = (int) ($적어둔['rotate'] ?? 0);

        return response()->json([
            'success'       => true,
            'source_url'    => route('prescriptions.attachments.overlaySource', [$prescription, $attachment]),
            'values'        => \App\Support\RegistrationOverlay::values($prescription),
            'fields'        => array_merge(\App\Support\RegistrationOverlay::defaults(), $자리),
            'off'           => array_values((array) $off),
            'rotate'        => $각도,
            'applied'       => $attachment->신청인란얹었나(),
            'has_signature' => \App\Support\RegistrationOverlay::signature($prescription) !== null,
        ]);
    }

    /** 자리를 다시 잡을 때 쓰는 바탕 그림 — 얹기 전의 원본이다 */
    public function registrationOverlaySource(Prescription $prescription, PrescriptionAttachment $attachment)
    {
        $this->이첨부인가($prescription, $attachment);

        $경로 = $attachment->바탕그림경로();

        abort_unless($경로 && Storage::disk('public')->exists($경로), 404);

        return response()->file(Storage::disk('public')->path($경로), [
            /* 얹고 다시 열 때 옛 그림이 나오지 않게 한다 */
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** 잡아 둔 자리대로 얹어 첨부 파일을 갈아 놓는다 */
    public function saveRegistrationOverlay(Request $request, Prescription $prescription, PrescriptionAttachment $attachment): \Illuminate\Http\JsonResponse
    {
        $this->이첨부인가($prescription, $attachment);

        $data = $request->validate([
            'fields'              => 'required|array',
            'fields.*.x'          => 'required|numeric|min:0|max:1',
            'fields.*.y'          => 'required|numeric|min:0|max:1',
            'fields.*.w'          => 'nullable|numeric|min:0.01|max:1',
            'fields.*.size'       => 'nullable|numeric|min:0.002|max:0.1',
            // 화면에서 세워 둔 각도 — 저장할 때 그대로 굳힌다
            'rotate'              => 'nullable|integer|in:0,90,180,270',
        ]);

        /* 아는 이름만 받는다 — 화면에서 온 값이라 그대로 믿지 않는다.
           화면에서 지운 칸은 아예 오지 않는다(그 칸은 얹지 않는다). */
        $자리 = array_intersect_key($data['fields'], array_flip(
            array_merge(\App\Support\RegistrationOverlay::글자칸, ['signature'])
        ));

        if (! $자리) {
            return response()->json(['success' => false, 'message' => '입력할 항목이 없습니다.'], 422);
        }

        $각도 = (int) ($data['rotate'] ?? 0);

        try {
            $그림 = \App\Support\RegistrationOverlay::compose($attachment, $자리, $각도);
        } catch (\Throwable $e) {
            Log::warning('[등록신청서 얹기] 그리지 못했습니다', [
                'attachment' => $attachment->id, 'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        /* 원본은 처음 얹을 때 한 번만 옮겨 적는다 — 두 번째부터는 이미 적혀 있는
           그 원본 위에 다시 얹는다(얹은 것 위에 또 얹으면 글자가 겹친다). */
        $원본 = $attachment->overlay_source_path ?: $attachment->file_path;

        $새경로 = 'attachments/' . $prescription->id . '/'
                . uniqid('reg_') . '.' . $그림['ext'];

        Storage::disk('public')->put($새경로, $그림['bytes']);

        /* 앞서 얹어 둔 것은 지운다. 원본은 그대로 둔다 — 자리를 다시 잡을 바탕이다. */
        if ($attachment->신청인란얹었나()
            && $attachment->file_path
            && $attachment->file_path !== $원본) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        /* 뺀 칸을 적어 둔다 — 다음에 열 때 되살아나지 않게 한다 */
        $쓸수있는 = array_merge(\App\Support\RegistrationOverlay::글자칸, ['signature']);

        $attachment->forceFill([
            'file_path'           => $새경로,
            'file_mime_type'      => $그림['mime'],
            'file_size'           => strlen($그림['bytes']),
            'overlay_source_path' => $원본,
            'overlay_fields'      => [
                'fields' => $자리,
                'off'    => array_values(array_diff($쓸수있는, array_keys($자리))),
                'rotate' => $각도,
            ],
        ])->save();

        activity()->causedBy(Auth::user())->performedOn($prescription)
            ->log('등록신청서 신청인란(신청인ㆍ관계ㆍ전화번호ㆍ서명)을 입력했습니다');

        return response()->json([
            'success' => true,
            'url'     => $attachment->fresh()->file_url,
            'message' => '신청인란을 저장했습니다.',
        ]);
    }

    /** 얹은 것을 걷고 원본으로 되돌린다 */
    public function resetRegistrationOverlay(Prescription $prescription, PrescriptionAttachment $attachment): \Illuminate\Http\JsonResponse
    {
        $this->이첨부인가($prescription, $attachment);

        if (! $attachment->신청인란얹었나()) {
            return response()->json(['success' => false, 'message' => '적용된 신청인란이 없습니다.'], 422);
        }

        $얹은것 = $attachment->file_path;
        $원본   = $attachment->overlay_source_path;

        $attachment->forceFill([
            'file_path'           => $원본,
            'file_size'           => Storage::disk('public')->exists($원본)
                                     ? Storage::disk('public')->size($원본)
                                     : $attachment->file_size,
            'overlay_source_path' => null,
            /* 자리는 남겨 둔다 — 다시 얹을 때 잡아 둔 그 자리에서 시작한다 */
        ])->save();

        if ($얹은것 && $얹은것 !== $원본) {
            Storage::disk('public')->delete($얹은것);
        }

        activity()->causedBy(Auth::user())->performedOn($prescription)
            ->log('등록신청서 신청인란을 삭제하고 원본 이미지로 복원했습니다');

        return response()->json([
            'success' => true,
            'url'     => $attachment->fresh()->file_url,
            'message' => '원본 이미지로 복원했습니다.',
        ]);
    }

    /** 이 처방전에 붙은 등록신청서 그림인가 — 아니면 여기서 멈춘다 */
    private function 이첨부인가(Prescription $prescription, PrescriptionAttachment $attachment): void
    {
        abort_if($attachment->prescription_id !== $prescription->id, 403);
        abort_unless($attachment->신청인란얹을수있나(), 422,
            '등록신청서 이미지 파일에만 신청인란을 입력할 수 있습니다.');
    }

    /** 작업 대기 리스트가 한 번에 그리는 줄 수 */
    private const 작업대기상한 = 500;

    /**
     * 작업 대기 리스트의 잣대 — 네 가지를 함께 본다.
     *
     *   · 되돌린 적이 없는 주문(교환ㆍ반품ㆍ취소가 붙지 않은 것)
     *   · 주문 상태가 아직 pending 인 것
     *   · 처방전 검수를 마친 것(approved·ordered·ocr_done)
     *   · **아직 창고로 넘기지 않은 것** (2026-09-15 지시)
     *
     * 검수 전 건이 서면, 다음에 손댈 것을 고르는 자리에서 아직 볼 차례가 아닌 것을
     * 고르게 된다 — 골라 들어가도 주문을 낼 수 없다(검수 문에 막힌다).
     *
     * 넘긴 건도 마찬가지다. 이 자리는 「검수 승인부터 주문 등록 전까지」를 보는
     * 곳이고, 주문 등록의 끝은 **위드웍스 연계**다 (2026-09-15 지시).
     *
     * 연계 여부를 주문 상태로 갈음할 수 없다. 연계는 판매번호만 적고 상태는
     * pending 그대로 두기 때문이다(createWithworksOrder 403줄) — 그래서 이미
     * 창고로 넘어간 건 스물셋이 이 목록에 그대로 서 있었다. 판매번호를 직접 본다.
     *
     * @param bool $함께 관계까지 미리 불러올지 — 세기만 할 때는 필요 없다
     */
    private function 작업대기질의(bool $함께 = false)
    {
        $검수마침 = ['approved', 'ordered', 'ocr_done'];

        $q = \App\Models\Order::whereDoesntHave('returns')
            ->where('status', 'pending')
            ->whereNull('withworks_so_no')
            ->whereHas('prescription', fn ($p) => $p->whereIn('status', $검수마침));

        return $함께 ? $q->with($this->주문줄관계()) : $q;
    }

    /** 줄 하나를 그리는 데 드는 관계 — 줄마다 물으면 오백 줄에 오백 번을 묻는다 */
    private function 주문줄관계(): array
    {
        return [
            'patient', 'prescription.assignedUser', 'prescription.creator', 'prescription.updater',
            'prescription.billingOffice', 'items.lots', 'operationUser', 'returns',
            /* 진행 상태를 「입금 대기 / 출고 대기」로 갈라 적는다 — 그 판정이
               토스 결제를 본다(2026-09-10 확인요청 8쪽). */
            'tossPayment',
            /* 결제수단을 적을 때 되짚는다 — 주문에 방식을 정해 두지 않은 건은
               마지막으로 보낸 결제 링크가 답이다(Order::payMethod). 미리 불러 두지
               않으면 오백 줄에 오백 번을 묻는다. */
            'paymentLinks',
        ];
    }

    /**
     * 주문을 작업 대기 리스트의 줄 꼴로 바꾼다.
     *
     * 화면을 열 때와 이름으로 찾을 때가 같은 것을 쓴다 — 두 곳이 따로 그리면
     * 찾은 결과만 칸이 모자라거나 값이 달라진다.
     */
    private function 주문줄들($orders)
    {
        /* 동의 두 가지는 사람에 붙는다 — 줄마다 물으면 마흔 줄에 여든을 더 묻는다.
           목록을 만들기 전에 한 번에 모아 둔다. */
        $extras = \App\Support\OrderGridExtras::forPatients($orders->pluck('patient_id'));

        return $orders
        ->map(function ($o) use ($extras) {
            $rx = $o->prescription;
            $d  = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '';

            return [
            'id'        => $o->id,
            'order_no'  => $o->order_number,
            'rx_number' => $rx?->rx_number ?? '',
            'patient'   => $o->patient?->name ?? ($rx?->patient_name_ocr ?? ''),
            // 배정 담당자 — 아직 아무도 집어 들지 않은 건은 비어 있다
            'manager'   => $rx?->assignedUser?->name ?? '',
            /* 이름 말고 누구인지도 함께 — 더블클릭한 사람이 임자인지 남인지는
               이름으로 견줄 수 없다(같은 이름이 둘일 수 있다). */
            'manager_id' => $rx?->assigned_user_id,
            'status'    => $o->status_label,
            /* 되돌린 적이 있는가 (2026-09-14 지시).
               이름으로 찾으면 교환ㆍ반품ㆍ취소 건도 함께 서므로 무엇이었는지 가려야
               한다. 작업 대기 줄은 되돌린 적이 없는 것만 모으므로 늘 「판매」다 —
               찾은 결과에서만 다른 말이 선다. */
            'deal'       => ($rt = $o->returns->first())
                ? \App\Models\OrderReturn::TYPES[$rt->type]
                    . ($o->returns->count() > 1 ? ' 외 ' . ($o->returns->count() - 1) . '건' : '')
                : '판매',
            'deal_state' => $rt
                ? (\App\Models\OrderReturn::STATUS_LABELS[$rt->status] ?? $rt->status) : '',
            'sold_at'   => $o->created_at?->format('Y-m-d') ?? '',
            /* 고르면 이 주소로 간다. claim=1 은 「임자 없으면 내가 맡는다」는 표시다.

               처방전이 없는 주문은 주문 번호로 연다 — 처방전 없이도 사고, 잘못 올린
               처방전을 지운 뒤 다시 올리기도 한다. 예전에는 여기서 빈 값을 내보내
               더블클릭이 「이어져 있지 않습니다」로 막혔는데, 목록에 서 있는 일을
               열 수 없게 하는 것은 막을 일이 아니라 열어 줄 일이었다. */
            /* 목록의 한 줄은 **주문**이다. 처방번호만 넘기면 화면이 그 처방전의 첫
               주문(원 주문)을 그려, 추가 주문 줄을 더블클릭해도 원 주문이 열렸다 —
               판매번호ㆍ금액ㆍ제품이 모두 다른 주문 것으로 보였다 (2026-09-16 고침). */
            'url'       => $rx
                ? route('prescriptions.show', [
                    'prescription' => $rx,
                    'order'        => $o->order_number,
                    'claim'        => 1,
                  ])
                : route('orders.open', $o),

            /* 이 화면에만 있는 칸 — 누구인가ㆍ누가 돈을 보냈는가ㆍ
               창고가 지금 무엇을 하고 있는가. */
            'resident_no' => $rx?->resident_no_ocr_masked ?? $o->patient?->masked_resident_no ?? '',
            // 송금자명 — 돈을 보내는 사람이 환자와 다른 일이 잦다(보호자가 보낸다)
            'remitter'    => $o->patient?->remitter_name ?? '',
            'creator'     => $rx?->creator?->name ?? '',
            'updater'     => $rx?->updater?->name ?? '',

            // 병원ㆍ처방 정보 탭의 칸 + 네 화면이 함께 쓰는 칸
            ] + $extras->rx($rx, $o->patient)
              + $extras->ww($o, $rx, $o->patient)
              + $extras->of($o);
        })->values();
    }

    /**
     * 이름으로 찾으면 그 사람의 **모든 건**을 보여 준다 (2026-09-14 지시).
     *
     * 작업 대기 리스트는 「지금 손댈 차례」만 세우는 자리라 교환ㆍ반품ㆍ취소가 붙었거나
     * 이미 창고로 넘어간 건은 빠진다. 그런데 담당자가 이름을 치는 까닭은 대개 그 반대다 —
     * 「이 사람 건이 지금 어떻게 되어 있나」를 보려는 것이고, 되돌린 건이야말로 그때 가장
     * 먼저 찾는 것이다.
     *
     * 그래서 찾는 말이 있으면 잣대를 풀고 그 사람의 것을 모두 내준다. 비어 있으면
     * 화면이 처음 받아 둔 작업 대기 줄로 돌아간다(화면 쪽에서 가린다).
     */
    public function orderListSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $말 = trim((string) $request->input('q'));

        if ($말 === '') {
            return response()->json(['success' => true, 'rows' => [], 'total' => 0]);
        }

        $숫자 = preg_replace('/\D/', '', $말);

        $질의 = \App\Models\Order::with($this->주문줄관계())
            ->where(function ($w) use ($말, $숫자) {
                $w->where('order_number', 'like', "%{$말}%")
                    ->orWhere('product_name', 'like', "%{$말}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$말}%"))
                    ->orWhereHas('prescription', fn ($p) => $p
                        ->where('rx_number', 'like', "%{$말}%")
                        ->orWhere('patient_name_ocr', 'like', "%{$말}%")
                        ->orWhere('hospital_name', 'like', "%{$말}%")
                        ->orWhere('hospital_code', 'like', "%{$말}%"));

                /* 요양기관코드는 여덟 자리 숫자다 — 손으로 칠 때 하이픈이 섞이면
                   글자 그대로는 찾히지 않는다 */
                if ($숫자 !== '' && $숫자 !== $말) {
                    $w->orWhereHas('prescription', fn ($p) => $p->where('hospital_code', 'like', "%{$숫자}%"));
                }
            });

        $총 = (clone $질의)->count();
        $줄 = $this->주문줄들($질의->latest('id')->limit(self::작업대기상한)->get());

        return response()->json([
            'success' => true,
            'rows'    => $줄,
            'total'   => $총,
            'limit'   => self::작업대기상한,
        ]);
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
            // datetime 이라 그대로 실으면 2026-09-14T09:31:00.000000Z 로 나간다 (2026-09-14)
            'counsel_date'       => $p->counsel_date?->format('Y-m-d H:i'),
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
            'license_no'         => $p->license_no,
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
     * 주민등록번호 원문 — 상담 담당자가 화면에서 확인한다.
     *
     * 이 번호는 저장할 때 암호화되고, 화면에는 가린 값만 내려간다(P0-1). 그런데 전화를
     * 받으며 본인을 확인해야 하는 자리에서는 원문이 필요하다 — 그래서 「표시」를 눌렀을
     * 때만, 한 건씩, 사유 코드를 달아 연다.
     *
     * 사유 코드는 config/rrn.php 에 이미 승인되어 있는 operator_view 다
     * (「검수 화면에서 담당자가 원문 확인」). 여는 순간 누가ㆍ언제ㆍ무엇을 열었는지
     * 감사로그에 남는다 — ResidentNo::decrypt 가 그 일을 한다.
     *
     * 처방전에 적힌 번호를 먼저 보고, 없으면 이어진 환자의 번호를 본다.
     */
    public function residentNo(Prescription $prescription): JsonResponse
    {
        $plain = $prescription->residentNoOcrFor('operator_view')
              ?: $prescription->patient?->residentNoFor('operator_view');

        if (!$plain) {
            return response()->json(['success' => false, 'message' => '적혀 있는 주민등록번호가 없습니다.']);
        }

        // 보기 좋게 끊어 준다 — 저장은 숫자만 한다
        $shown = strlen($plain) === 13
            ? substr($plain, 0, 6) . '-' . substr($plain, 6)
            : $plain;

        return response()->json(['success' => true, 'resident_no' => $shown]);
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
                'status'    => $o->status_label,
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
            /* 거래처에서 고친 뒤 이 화면이 따라오지 않던 넷.
               읽기만 하는 칸이라 마스터가 정본인데, 채우는 표에 빠져 있어
               고치고 돌아와도 빈칸이었다 — 새로 고쳐야 보였다. */
            'f-contact-status'    => $patient->contactStatusLabel(),
            'f-contact-channel'   => $patient->contactChannelLabel(),
            'f-fax'               => $patient->fax,
            'f-guardian'          => $patient->remitter_name,
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
            // 개인정보 동의도 사람에게 묶어 읽는다 — 다른 사람을 고르면 그 사람 것으로 다시 그린다
            'privacy'         => \App\Models\PrivacyConsent::stateFor(
                                     $patient->id, $patient->bare_name, $patient->mobile),

            /* 거래처 수정 창이 채워 넣을 값 — 사람의 칸 이름 그대로다.

               fill 을 쓰면 안 된다. 그것은 이 화면의 칸(f-…)에 비추려고 고른 것이라
               사업부ㆍ성별ㆍFaxㆍ연락 상태ㆍ송금자명ㆍ메모가 빠져 있다. 빠진 채로
               창을 채우고 저장하면 그 칸들이 빈 값으로 덮여 지워진다. */
            /* array_merge 다. `+` 는 **왼쪽이 이겨서** 아래에 다시 적은 날짜가
               only() 의 Carbon 에 밀린다 — 고쳐 놓고도 그대로였다. */
            'account'         => array_merge($patient->only([
                'name', 'care_type', 'resident_no', 'birth_date', 'gender',
                'mobile', 'phone', 'main_contact', 'marketing_consent', 'email', 'fax', 'sb_sci',
                'postcode', 'address', 'address_detail', 'note',
                'contact_status', 'contact_channel', 'remitter_name',
                'deduction', 'cash_receipt_no',
                'nhis_reg_status', 'nhis_reg_date', 'nhis_renew', 'nhis_renew_due',
                'nhis_agree_start', 'nhis_agree_end',
                'basic_reeval', 'basic_reeval_due',
                // 미성년 보호자 (2026-09-07 · 결함 ㉕)
                'guardian_name', 'guardian_relation', 'guardian_birth_date', 'guardian_phone',
            ]), [
                'resident_no' => $patient->resident_no,

                /* 날짜는 날짜로 내보낸다.

                   only() 는 캐스트된 **Carbon 객체를 그대로** 담는다 — 모델의
                   `date:Y-m-d` 는 toArray()/toJson() 에만 들고 이 길에는 들지 않는다.
                   그래서 JSON 으로 나갈 때 Carbon 이 제 방식(ISO8601 UTC)으로 찍혀
                   `2017-04-27T15:00:00.000000Z` 가 된다. 우리 시간 04-28 00:00 이
                   UTC 로는 전날 15:00 이라, 날짜 칸이 그것을 받아 **하루가 앞섰다**
                   (2026-09-08 송예린 — 170428 인 아이가 04-27 로 떴다).

                   위의 $d() 가 이미 그 일을 한다. 날짜 칸은 모두 그것을 지나게 한다. */
                'birth_date'          => $d($patient->birth_date),
                'guardian_birth_date' => $d($patient->guardian_birth_date),
                'nhis_reg_date'       => $d($patient->nhis_reg_date),
                'nhis_renew_due'      => $d($patient->nhis_renew_due),
                'nhis_agree_start'    => $d($patient->nhis_agree_start),
                'nhis_agree_end'      => $d($patient->nhis_agree_end),
                'basic_reeval_due'    => $d($patient->basic_reeval_due),

                /* 미성년인지는 **서버가 말한다**. 창이 생년월일을 보고 스스로 세도록
                   두었더니, 그 칸이 아직 채워지기 전이거나 꼴이 달라 늘 성년으로
                   읽혔다 — 보호자 칸이 영영 서지 않았다. */
                'is_minor'    => $patient->is_minor,
            ]),
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

    // ── 주문 연계 페이지 (검수 화면) ──────────────────────
    /**
     * 주문으로 이 화면을 연다 — 처방전이 없어도 (2026-09-07 지시).
     *
     * 이 화면은 처방전을 열쇠로 선다. 그래서 처방전이 없는 주문은 「작업 대기
     * 리스트」에서 더블클릭해도 「이어져 있지 않습니다」로 막혔다 — 목록에는
     * 서 있는데 열 수가 없으니, 담당자에게는 「할 일이 하나 있는데 눌러도
     * 안 열린다」로 보였다.
     *
     * 그런데 **처방전 없이도 산다.** 처방외 주문이 그렇고, 처방전을 잘못 올려
     * 지운 뒤 다시 올리는 일도 그렇다. 막을 일이 아니라 열어 줄 일이다.
     *
     * 빈 처방전을 하나 세워 이 주문에 잇고 그 자리로 보낸다. 담당자는 거기서
     * 다시 올리거나, 처방 없이 그대로 이어 간다.
     *
     * 지워진 처방전을 되살리지는 않는다 — 지운 데는 까닭이 있었을 것이고,
     * 되살리면 그때 버린 값이 함께 돌아온다.
     */
    public function openFromOrder(Order $order): RedirectResponse
    {
        /* 어느 주문을 보러 왔는지 함께 넘긴다 (2026-09-16 고침).

           화면은 ?order= 가 없으면 그 처방전의 **첫 주문**을 그린다. 그래서 추가 주문의
           상세에서 들어와도 원 주문이 열렸고, 판매번호ㆍ금액ㆍ제품이 모두 원 주문 것으로
           보였다 — 담긴 값은 멀쩡한데 화면만 다른 주문을 보여 준 것이다. */
        return redirect()->route('prescriptions.show', [
            'prescription' => $this->prescriptionFor($order),
            'order'        => $order->order_number,
            'claim'        => 1,
        ]);
    }

    /**
     * 이 주문의 처방전. 없으면 빈 초안을 하나 세워 잇는다.
     *
     * 지워진 처방전을 되살리지는 않는다 — 지운 데는 까닭이 있었을 것이고,
     * 되살리면 그때 버린 값이 함께 돌아온다.
     */
    private function prescriptionFor(Order $order): Prescription
    {
        if ($order->prescription) {
            return $order->prescription;
        }

        $draft = Prescription::create([
            'rx_number'        => Prescription::generateRxNumber(),
            'created_by'       => Auth::id(),
            'status'           => 'pending',
            'upload_source'    => 'web',
            'is_blank_draft'   => true,
            'patient_id'       => $order->patient_id,
            'patient_name_ocr' => $order->patient?->name,
            'mobile_ocr'       => $order->patient?->mobile ?: $order->patient?->phone,
        ]);

        $order->forceFill(['prescription_id' => $draft->id])->save();
        $order->setRelation('prescription', $draft);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log("처방전이 없어 빈 처방전을 생성하여 연결했습니다 ({$draft->rx_number})");

        return $draft;
    }

    // ── 담당자 배정 (주문 목록에서 골라 한 번에) ───────────
    /**
     * 고른 주문의 담당자를 한 사람으로 정한다 (2026-09-07 지시).
     *
     * 여태 담당자는 **여는 사람이 곧 임자**가 되는 길 하나뿐이었다(claim). 그것은
     * 아무도 맡지 않은 건에는 맞지만, 이미 임자가 있는 건을 남에게 넘길 수는
     * 없었다 — 자리를 비우거나 일이 몰릴 때 넘길 길이 없었다는 뜻이다.
     * 화면에서 부르는 곳 없이 서버에만 남아 있던 assignUser 도 그 자취다.
     *
     * 한 번에 여러 건을 넘긴다. 스무 건이면 스무 번을 여는 대신 목록에서 고른다.
     *
     * 처방전이 없는 주문도 넘길 수 있어야 한다 — 처방전 없이도 사기 때문이다.
     * 그런 건은 빈 초안을 세워 잇고 그 초안에 담당자를 적는다(담당자는 처방전에
     * 붙는다). 열었을 때와 같은 걸음이라 새로 생기는 것이 없다.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'        => 'required|array|min:1',
            'order_ids.*'      => 'integer',
            'assigned_user_id' => 'required|exists:users,id',
        ]);

        $to = User::findOrFail($data['assigned_user_id']);

        $orders = Order::with(['prescription.assignedUser', 'prescription.patient', 'patient'])
            ->whereIn('id', $data['order_ids'])
            ->get();

        $moved = [];   // 실제로 바뀐 것
        $same  = 0;    // 이미 그 사람 것이던 건

        foreach ($orders as $order) {
            $rx = $this->prescriptionFor($order);

            if ((int) $rx->assigned_user_id === (int) $to->id) {
                $same++;
                continue;
            }

            $before = $rx->assignedUser?->name;
            $rx->forceFill(['assigned_user_id' => $to->id])->save();

            activity()->causedBy(Auth::user())->performedOn($rx)->log(
                $before
                    ? "담당자를 {$before} 에서 {$to->name} (으)로 바꿨습니다"
                    : "담당자로 {$to->name} 을(를) 배정했습니다"
            );

            $rx->setRelation('order', $order);
            $moved[] = ['order' => $order, 'rx' => $rx];
        }

        /* 알림은 배정이 끝난 뒤 한 번이다. 건마다 울리면 받는 쪽이 읽지 않고 지운다.
           알리지 못해도 배정은 이미 됐다 — 그래서 안에서 삼킨다. */
        if ($moved) {
            app(\App\Services\AssignNotice::class)
                ->tell($to, array_column($moved, 'rx'), Auth::user());
        }

        return response()->json([
            'success' => true,
            'name'    => $to->name,
            'user_id' => $to->id,
            'moved'   => count($moved),
            'same'    => $same,
            /* 목록을 통째로 다시 부르지 않고 바뀐 줄만 고쳐 그린다 — 이 화면은
               적다 만 것이 딸린 자리라 새로 고치면 그것이 사라진다. */
            'rows'    => collect($moved)->map(fn ($m) => [
                'order_id'  => $m['order']->id,
                'rx_number' => $m['rx']->rx_number,
                'manager'   => $to->name,
                'url'       => route('prescriptions.show', $m['rx']) . '?claim=1',
            ])->values(),
        ]);
    }

    public function show(Prescription $prescription): View
    {
        /* 주문 목록에서 더블클릭해 들어온 건이다(claim=1).
           아직 맡은 사람이 없으면 연 사람이 맡는다 — 집어 든 사람이 임자가 되어야
           두 사람이 같은 건을 붙들지 않는다. 이미 임자가 있으면 덮지 않는다. */
        if (request()->boolean('claim') && ! $prescription->assigned_user_id && Auth::id()) {
            $prescription->forceFill(['assigned_user_id' => Auth::id()])->save();
            activity()->causedBy(Auth::user())->performedOn($prescription)
                ->log('주문 목록에서 열어 담당자로 지정');
        }

        $prescription->load(['patient', 'assignedUser', 'creator', 'reviewer', 'updater', 'order.tossPayment', 'items', 'memos.user', 'attachments', 'documents.creator', 'billingOffice']);
        $patients = Patient::orderBy('name')->get();

        /* 어느 주문을 보고 있는가 (2026-09-14 확인요청 4쪽).

           처방전 한 장에 주문이 둘 이상 설 수 있다 — 수량을 나눠 사는 건의 추가 주문이다.
           그런데 이 화면은 **처방번호로 열린다.** 무엇을 그릴지 정해 주지 않으면 늘 원
           주문만 보이고 추가 주문은 열 길이 없다.

           ?order= 로 적어 준 것이 있으면 그것을, 없으면 원 주문을 그린다. 화면 곳곳에서
           쓰는 $prescription->order 를 여기서 갈아 끼우므로, 그 서른 자리를 손대지
           않고도 고른 주문이 그려진다. */
        $주문들 = $prescription->orders()->with('tossPayment')->orderBy('id')->get();
        $보는주문 = null;

        if ($번호 = trim((string) request('order'))) {
            $보는주문 = $주문들->firstWhere('order_number', $번호);
        }

        $보는주문 ??= $주문들->first();
        $prescription->setRelation('order', $보는주문);

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
                    'hospital_name', 'doctor_name', 'license_no', 'issued_date',
                    'postcode', 'address_detail', 'patient_id', 'repurchase_date',
                ])
                ->filter(fn($p) => !empty($p->counsel_no))
                ->values();
        }

        $tossConfigured  = $this->vaService->isConfigured();
        /* 알림톡은 팝빌로 나간다. 예전에는 알리고 키가 있는지를 물었는데, 그 키는
           채워진 적이 없어 화면이 늘 「미설정」이었다(그래 놓고 발송은 성공이라 답했다). */
        $kakaoConfigured = (bool) (config('popbill.LinkID') && config('popbill.SecretKey')
                                   && config('popbill.test.corp_num'));
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

        /* tuneKey 가 있는 문서만 밝기ㆍ명암을 맞출 수 있다. 우리가 받아 둔 그림이라야
           고쳐 적을 자리가 있다 — 시스템이 만든 서류나 서명 그림에는 그 자리가 없다.

           「PDF 가 아니면」이 아니라 「그림이면」으로 가린다. 형식이 적혀 있지 않은
           옛 파일이 PDF 가 아니라는 이유로 그림 대접을 받으면, 맞출 수 없는 문서에
           단추가 서고 GD 가 열지 못해 아무 일도 일어나지 않는다. */
        $attachmentsJson = $prescription->attachments->map(function ($a) {
            return [
                'id'        => $a->id,
                'url'       => $a->file_url,
                'type'      => $a->doc_type,
                'typeLabel' => $a->doc_type_label,
                'name'      => $a->file_original_name,
                'isPdf'     => $a->is_pdf,
                'isRx'      => false,
                'tuneKey'   => $a->is_image ? 'att:' . $a->id : null,
                'bright'    => (int) ($a->img_brightness ?? 0),
                'contrast'  => (int) ($a->img_contrast ?? 0),
                /* 등록신청서 그림에는 ③ 신청인란을 얹을 수 있다 (2026-09-17 지시) */
                'regOverlay'        => $a->신청인란얹을수있나(),
                'regOverlayApplied' => $a->신청인란얹었나(),
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
            'tuneKey'   => str_starts_with($prescription->image_mime_type ?? '', 'image/') ? 'rx' : null,
            'bright'    => (int) ($prescription->img_brightness ?? 0),
            'contrast'  => (int) ($prescription->img_contrast ?? 0),
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

        /* 신분증 링크(kind='id_card')로 받은 본인 신분증.

           위임동의와 다른 줄에 담기므로 위의 $lastConsent 로는 닿지 않는다 —
           마지막 동의가 신분증 건이 아닐 수 있다. 받아 둔 것 가운데 마지막을 찾는다. */
        $idCard = $prescription->consents
            ->sortByDesc('id')
            ->first(fn ($c) => $c->patient_id_path ?? null);

        if ($idCard) {
            $signDocs[] = [
                'id'        => -3,
                'url'       => route('files.consent-patient-id', $idCard),
                'type'      => 'patient_id',
                'typeLabel' => '본인 신분증',
                'name'      => '신분증 ' . ($idCard->patient_name ?? ''),
                'isPdf'     => false,
                'isRx'      => false,
            ];
        }

        /* 시스템이 만든 서류(위임동의서ㆍ요양비위임장ㆍ팩스통합본ㆍ세금계산서…)도
           같은 자리에 세운다. 따로 목록 카드를 두던 것을 걷었다 — 보는 자리가 둘이면
           어느 쪽을 봐야 하는지 매번 헤맸고, 그 카드에서는 확대도 이동도 되지 않았다. */
        $allDocsJson = array_merge($rxDoc, $attachmentsJson, self::generatedDocsJson($prescription), $signDocs);

        // 이름 옆 「조회」 창이 쓰는 목록 — 업로드 화면과 같은 것을 쓴다
        $patientsJson = self::patientPickerList();

        /* 주문 담당자로 고를 수 있는 사람 — 지금 쓰고 있는 CE 담당자 전부(관리자 포함).
           이름을 그대로 담는 칸이라 이름만 넘긴다. */
        $orderManagers = \App\Models\User::where('is_active', true)
            ->orderBy('name')->pluck('name')->unique()->values()->all();

        /* 담당자로 넘길 수 있는 사람 — 처방전 업로드 화면과 같은 무리를 쓴다.
           여기는 이름이 아니라 누구인지가 실려야 해서 번호를 함께 넘긴다. */
        $assignables = \App\Models\User::where('is_active', true)
            ->whereIn('role', ['admin', 'manager'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();

        /* 주문 목록 탭 — 이 화면 안에서 다른 건으로 건너뛰는 자리다.
           여기서 하려는 일은 「다음에 손댈 주문을 고르는 것」이라, 아직 확정되지 않은 건만
           세운다(주문 대기). 확정ㆍ배송ㆍ완료된 건은 손댈 차례가 지났고, 그것들을 훑는
           자리는 주문 관리다.

           반품ㆍ교환ㆍ취소가 붙은 건도 뺀다(판매만) — 그것들은 「교환/반품/취소」 화면이 맡는다.

           목록은 wwGrid 가 한 번에 다 받아 그리므로 통째로 넘긴다. 다만 끝없이 늘어날
           표라 최근 것부터 상한을 둔다 — 넘친 만큼은 화면이 말해 준다. */
        /* **검수를 마친 건만 선다** (2026-09-10 지시).

           검수 전 건이 이 목록에 서면, 다음에 손댈 것을 고르는 자리에서 아직 볼
           차례가 아닌 것을 고르게 된다 — 골라 들어가도 주문을 낼 수 없다(검수 문에
           막힌다). 검수를 마친 뒤(approved)와 이미 주문이 나간 뒤(ordered)를 함께
           본다. 예전 자료의 ocr_done 도 이 화면 다른 자리가 「주문 가능」으로 세는
           값이라 같이 둔다. */
        $검수마침 = ['approved', 'ordered', 'ocr_done'];

        /* 입력 검수 — 파일 검수와 다른 칸을 본다 (2026-09-16 지시) */
        $입력검수 = $prescription->입력검수상태();

        $orderListLimit = self::작업대기상한;
        $orderListTotal = $this->작업대기질의()->count();
        $orderListRows  = $this->주문줄들(
            $this->작업대기질의(true)->latest('id')->limit($orderListLimit)->get()
        );

        /* 개인정보 수집·이용 동의 — 아직 환자로 맺어지지 않은 처방전도 있어,
           환자가 있으면 그 사람으로, 없으면 처방전에 적힌 이름ㆍ휴대폰으로 찾는다. */
        $privacyState = \App\Models\PrivacyConsent::stateFor(
            $prescription->patient_id,
            $prescription->patient?->bare_name ?? $prescription->patient_name_ocr,
            $prescription->patient?->mobile    ?? $prescription->mobile_ocr,
        );

        /* 아직 살 때가 아닌 건은 눌러 보기 전에 알려 준다(요청서 2쪽). 눌러야 알면
           제품과 배송지를 다 채운 뒤에야 막히는 셈이라, 그 일이 통째로 헛일이 된다. */
        $repurchaseBlock = \App\Support\RepurchaseWindow::block($prescription);

        /* 시험 중이면 전화번호를 손으로 적지 않고 **우리 사람 번호에서 고른다**
           (2026-09-07 지시). 손으로 치다 한 자만 틀려도 남의 전화로 안내가 가고,
           맞게 쳐도 시험 문자가 실제 환자에게 간다.

           운영이면 빈 배열이라 화면이 지금처럼 그냥 적는 칸을 세운다. */
        $testPhones = config('web.mode') === 'test'
            ? \App\Models\User::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('name')
                ->get(['name', 'phone'])
                ->map(fn ($u) => ['name' => $u->name, 'phone' => $u->phone])
                ->values()->all()
            : [];

        /* 결제를 이미 보냈는가ㆍ이미 받았는가 (2026-09-14 지시).

           ［결제전송］은 누르면 곧 환자에게 문자가 나가는 단추다. 그런데 결제 안내는
           주문을 만들 때 저절로도 나가고(웹훅이 창고 확정을 알리면 DepositAutoIssue 가
           보낸다) 담당자가 손으로도 보낸다 — 이미 나간 줄 모르고 한 번 더 눌러 같은
           안내가 두 번 가는 일이 있었다. 받은 뒤에 또 보내면 더 나쁘다.

           보낸 자취는 payment_links 에 쌓이고, 받았는지는 그 줄의 paid 와 주문의
           입금 확인 둘 가운데 하나라도 서면 참이다. */
        $payLinks  = $prescription->order?->paymentLinks()->latest('id')->get() ?? collect();
        $payLast   = $payLinks->first();
        $payState  = [
            'sent'      => $payLinks->isNotEmpty(),
            'paid'      => $payLinks->contains('status', 'paid')
                           || (bool) $prescription->order?->deposit_confirmed_at,
            'method'    => $payLast ? (\App\Models\PaymentLink::METHODS[$payLast->method] ?? $payLast->method) : '',
            'status'    => $payLast?->status ?? '',
            'status_label' => $payLast ? ($payLast->status_label ?? '') : '',
            'sent_at'   => $payLast?->sent_at?->format('Y-m-d H:i') ?? '',
            'count'     => $payLinks->count(),
            /* 받을 돈을 다 받았는가 — 이 값이 서면 더 보내지 못한다 (2026-09-15 지시).
               paid 와 다르다. paid 는 「한 번이라도 받았는가」라서, 정정으로 금액이
               늘어 차액이 남은 건도 참이 된다 — 그 건은 더 보낼 수 있어야 한다. */
            'settled'   => (bool) $prescription->order?->다받았나(),
            'received'  => (int) ($prescription->order?->받은금액() ?? 0),
            /* 결제가 취소된 건인가 (2026-09-16 지시).

               토스에서 취소되면 받은 돈이 0이 되고 입금 확인도 거둬진다
               (PaymentCancelSync). 그러면 paid 는 저절로 거짓이 되는데, 화면에는
               「보낸 적 있음」만 남아 **왜 다시 보내야 하는지**가 드러나지 않는다.
               취소됐다는 사실을 따로 적어 딱지가 그것을 말하게 한다. */
            'cancelled' => in_array(
                $prescription->order?->tossPayment?->status,
                ['CANCELED', 'PARTIAL_CANCELED'], true),
            'cancelled_amount' => (int) ($prescription->order?->tossPayment?->cancel_amount ?? 0),
        ];

        /* 주문 고르개가 쓸 줄들과, 추가 주문이 더 살 수 있는 수량 (2026-09-14 확인요청 4쪽).

           원 주문 총 구매 가능 정보 = 처방 총계. 거기서 이미 주문한 수량을 뺀 것이
           더 살 수 있는 몫이다. 넘겨 팔면 넘은 만큼은 공단에 청구할 수 없고, 그 사실은
           청구 단계에서야 드러난다. */
        $주문줄들 = $주문들->map(fn (\App\Models\Order $o) => [
            'id'      => $o->id,
            'number'  => $o->order_number,
            'kind'    => $o->order_kind,
            'label'   => $o->isExtra() ? '추가 주문' : '원 주문',
            'qty'     => (int) $o->items()->sum('quantity') ?: (int) ($o->quantity ?? 0),
            'so_no'   => $o->withworks_so_no ?? '',
            'current' => $보는주문 && $o->id === $보는주문->id,
        ])->values();

        /* 추가 주문 화면이 보여 줄 원 주문의 품목 (2026-09-16 지시).

           추가 주문은 제품 칸이 비어서 시작한다 — 이번에 더 살 것을 새로 고르기
           때문이다. 그런데 담당자는 **원 주문이 무엇을 얼마나 샀는지**를 보고
           고른다. 탭을 옮겨 다니지 않고 그 자리에서 보게 한다. */
        $원주문품목 = collect();

        if ($보는주문?->isExtra() && $보는주문->parentOrder) {
            $원주문품목 = $보는주문->parentOrder->items->map(fn ($i) => [
                'product_name'  => $i->product_name,
                'product_code'  => $i->product_code,
                'quantity'      => (int) $i->quantity,
                'unit_price'    => (int) ($i->insurance_price ?: $i->product_price),
                'nhis_amount'   => (int) $i->nhis_amount,
                'patient_copay' => (int) $i->patient_copay,
            ])->values();
        }

        $처방총계 = (int) ($prescription->total_count ?? 0);
        $이미주문 = (int) $주문줄들->sum('qty');
        $남은수량 = max(0, $처방총계 - $이미주문);

        return view('prescriptions.order', compact(
            'prescription', 'patients', 'prevId', 'nextId', 'repurchaseBlock', 'testPhones', 'payState',
            '주문줄들', '처방총계', '이미주문', '남은수량', '원주문품목',
            'tossConfigured', 'kakaoConfigured', 'kakaoTemplates', 'smsTemplates',
            'memosData', 'prevCounselings', 'prevCounselingsData',
            'lastFaxHistory', 'attachmentsJson', 'allDocsJson', 'patientsJson',
            'orderManagers', 'assignables', 'privacyState',
            'orderListRows', 'orderListTotal', 'orderListLimit',
            '입력검수'
        ));
    }

    /**
     * 추가 주문을 세운다 (2026-09-14 확인요청 4쪽).
     *
     * 처방전 한 장으로 수량을 나눠 사는 건이다. 먼저 일부만 사고 뒤에 나머지를 더 산다.
     * 처방번호는 그대로라 담당자에게는 여전히 한 건이고, 주문번호만 따로 선다.
     *
     * 가져오는 것 — 환자ㆍ판매유형ㆍ배송지는 원 주문과 같게 둔다(2026-09-14 지시).
     * 같은 사람이 같은 곳으로 받는 것이 예사라, 매번 다시 적게 하면 옮겨 적다 어긋난다.
     * 제품 줄은 비워 둔다 — 무엇을 더 살지는 이제 고를 일이다.
     *
     * 결제ㆍ증빙은 이 주문이 스스로 한다(2026-09-14 지시) — 결제 링크도 세금계산서도
     * 현금영수증도 따로 나간다. 본인부담금이 따로 셈해지므로 합치면 어느 쪽 금액인지
     * 가릴 수 없다.
     */
    public function createExtraOrder(Prescription $prescription): \Illuminate\Http\RedirectResponse
    {
        $원주문 = $prescription->order;

        if (! $원주문) {
            return back()->with('error', '원 주문이 아직 없습니다 — 먼저 주문을 만든 뒤에 추가 주문을 생성합니다.');
        }

        /* 처방 총계를 넘겨 팔 수는 없다. 넘은 만큼은 공단에 청구할 수 없고, 그 사실은
           청구 단계에서야 드러난다. 총계를 아직 안 적은 건은 막지 않는다 — 적기 전에
           막으면 적을 길이 없다. */
        $총계 = (int) ($prescription->total_count ?? 0);
        $이미 = (int) \App\Models\OrderItem::whereIn('order_id', $prescription->orders()->pluck('id'))
                        ->sum('quantity');

        if ($총계 > 0 && $이미 >= $총계) {
            return back()->with('error',
                "처방 총계를 이미 다 주문했습니다 (총계 {$총계}개 · 주문 {$이미}개).");
        }

        /* 원 주문에서 **입력한 값**만 물려받는다 (2026-09-15 지시).

           여태는 배송지 네 칸과 판매유형만 가져왔다. 그래서 담당자는 추가 주문마다
           청구처ㆍ담당자ㆍ세금계산서 정보를 다시 적어야 했고, 같은 처방전인데도 두
           주문의 적힌 내용이 어긋나는 일이 있었다.

           **겪은 일은 가져오지 않는다.** 결제ㆍ입금 확인, 위드웍스 판매번호와 출고
           정보, 세금계산서ㆍ현금영수증 발행 번호, 취소ㆍ정정 상태, 공단 청구, 정산은
           이 주문이 아직 하지 않은 일이다. 가져오면 새 주문이 이미 결제ㆍ출고ㆍ발행된
           것처럼 보인다.

           세금계산서ㆍ현금영수증은 **적어 둔 것과 발행한 것**을 갈라 본다. 사업자
           정보와 발행 구분은 담당자가 적은 값이라 물려받고, 승인번호ㆍ금액ㆍ발행 시각은
           발행한 자국이라 두고 간다. */
        $물려받을칸 = [
            'operation_user_id', 'reference_note', 'so_type',
            'shipping_postcode', 'shipping_address', 'shipping_address_detail', 'shipping_recipient',
            // 배송비는 받을 돈으로 세지 않는다(2026-09-03 확정) — fillable 에도 없다
            'pay_method', 'ship_request_date',
            'note', 'warehouse_note',
            'tax_invoice_type', 'tax_invoice_purpose', 'tax_invoice_biz_name',
            'tax_invoice_ceo_name', 'tax_invoice_biz_no', 'tax_invoice_email',
            'cash_receipt_type', 'cash_receipt_identifier',
        ];

        $물려받은것 = collect($원주문->only($물려받을칸))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        $추가 = \App\Models\Order::create(array_merge($물려받은것, [
            'order_number'     => \App\Models\Order::generateOrderNumber(),
            'prescription_id'  => $prescription->id,
            'patient_id'       => $prescription->patient_id,
            'parent_order_id'  => $원주문->id,
            'order_kind'       => \App\Models\Order::KIND_EXTRA,
            'created_by'       => Auth::id(),
            'status'           => 'pending',
            'so_type'          => $원주문->so_type ?: (\App\Models\Order::saleSoTypes()[0] ?? null),
            // 아직 고른 것이 없다는 뜻 — 제품명은 비울 수 없는 칸이다(OrderSync 와 같은 표시)
            'product_name'     => '-',
            'product_code'     => null,
            'quantity'         => 0,
            // 기본값이 없는 칸이라 비워 두면 표가 거절한다(1364) — 0 으로 세운다
            'unit_price'       => 0,
            'nhis_amount'      => 0,
            'patient_copay'    => 0,
            'total_amount'     => 0,
        ]));

        activity()->causedBy(Auth::user())->performedOn($추가)
            ->log("추가 주문 {$추가->order_number} 생성 (원 주문 {$원주문->order_number} · 처방전 {$prescription->rx_number})");

        return redirect()->route('prescriptions.show', [
            'prescription' => $prescription->rx_number,
            'order'        => $추가->order_number,
        ])->with('success', "추가 주문 {$추가->order_number} 을 생성했습니다. 주문 제품 탭에서 더 살 제품을 선택하십시오.");
    }

    // ── OCR 수정 저장 ─────────────────────────────────────
    public function updateOcr(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        /* 거래처를 이을 수 없으면 저장하지 않는다 (2026-09-20 지시).

           여태 이름 없이도 저장이 되었고, 그때마다 주문번호가 발급되어 주문 관리에
           **이름 없는 줄**이 남았다 — 2026-09-20 기준 열세 건 가운데 여섯이 그랬다.
           주문번호는 위드웍스ㆍ토스ㆍ팝빌ㆍ공단으로 나가는 대외 식별자라, 누구
           것인지 모르는 채 먼저 태울 번호가 아니다.

           이을 길은 둘이다 — 「조회」로 고른 사람(patient_id)이 함께 오거나, 이름이
           적혀 있어 서버가 찾아 잇는다(PatientLink::attach). 둘 다 없으면 막는다.
           화면도 같은 자리에서 막지만(saveOCR), 화면만 믿지 않는다. */
        $이을사람있나 = $request->filled('patient_id')
                     || trim((string) $request->input('patient_name_ocr')) !== ''
                     || $prescription->patient_id;

        if (! $이을사람있나) {
            return response()->json([
                'success' => false,
                'message' => '거래처를 먼저 선택하십시오. 이름 없이 저장할 수 없습니다.',
                'field'   => 'patient_name_ocr',
            ], 422);
        }

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
            'license_no'       => 'nullable|string|max:30',
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
            // 화면이 보고 있는 주문 — 추가 주문이면 처방 품목을 덮지 않는다
            'order_number'          => 'nullable|string|max:50',
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
            'use_start_date'        => 'nullable|date',
            'benefit_end_date'      => 'nullable|date',
            // 어떻게 받을 것인가 — 주문 연계 때 이 값대로 안내가 나간다(2026-09-03)
            'pay_method'            => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Models\PaymentLink::METHODS))],
            // 연락할 때 먼저 거는 번호 — 거래처에 적는다(2026-09-10 확인요청 5쪽)
            'main_contact'          => 'nullable|in:mobile,guardian',
            // 참고 사항 — 검수 메모와 다른 칸이다(2026-09-10 확인요청 5쪽)
            'reference_note'        => 'nullable|string|max:2000',
            'buy_date'              => 'nullable|date',
            // 시안 148:3046 (추가정보 카드)
            'inmarket_due'          => 'nullable|date',
            'last_confirmed_qty'    => 'nullable|integer|min:0',
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
            'billing_strategy'      => 'nullable|string|max:40',
            // 청구처 — 공단이냐 지자체냐에 따라 이후 절차가 통째로 갈린다
            'claim_agency'          => 'nullable|string|in:nhis,local,none',
            'billing_office_id'     => 'nullable|integer|exists:billing_offices,id',
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
            'specialty'             => 'nullable|string|max:100',
            'disease_grade'         => 'nullable|string|max:10',
            'uro_findings'          => 'nullable|string|max:200',
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
        $rxCols = [
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
            'license_no'           => $request->input('license_no'),
            'rx_use_period'        => $request->input('rx_period'),
            'rx_end_date'          => $request->input('rx_end_date'),
            'diagnosis_date'       => $request->input('diagnosis_date'),
            'disease_class'        => $request->input('disease_class'),
            'uro_date'             => $request->input('uro_date'),
            'benefit_class'        => $request->input('benefit_class'),
            /* 청구전략은 유형 × 자격이 정한다. 화면이 보낸 값을 그대로 적지 않고 여기서
               다시 셈한다 — 그래야 두 칸과 전략이 어긋난 건이 남지 않는다. 칸이 없는
               서버에서는 조용히 건너뛴다. */
            'billing_strategy'     => \App\Support\BillingStrategy::hasColumn()
                                        ? \App\Support\BillingStrategy::key(
                                            $request->input('counsel_acc_add_type'),
                                            $request->input('benefit_class'))
                                        : null,
            'claim_agency'         => $request->input('claim_agency'),
            /* 이 건을 보내는 청구처 — 주소로 찾아 사람이 고른 한 줄이다 */
            'billing_office_id'    => $request->input('billing_office_id'),
            'local_gov'            => $request->input('local_gov'),
            // 거래·주문
            'purchase_type'        => $request->input('purchase_type'),
            'five_program'         => $request->input('five_program'),
            'five_110days'         => $request->input('five_110days'),
            'order_manager'        => $request->input('order_manager'),
            'next_repurchase'      => $request->input('next_repurchase'),
            'special_case'         => $request->input('special_case'),
            'reason'               => $request->input('reason'),
            'specialty'            => $request->input('specialty'),
            'disease_grade'        => $request->input('disease_grade'),
            'uro_findings'         => $request->input('uro_findings'),
            'dealer_type'          => $request->input('dealer_type'),
            'pay_date'             => $request->input('pay_date'),
            'use_start_date'       => $request->input('use_start_date'),
            'benefit_end_date'     => $request->input('benefit_end_date'),
            'buy_date'             => $request->input('buy_date'),
            'inmarket_due'         => $request->input('inmarket_due'),
            'last_confirmed_qty'   => $request->input('last_confirmed_qty'),
            'diverticulums'        => $request->input('diverticulums'),
            'caregiver_name'       => $request->input('guardian'),
        ];

        /* **화면이 보내 왔는가**로 가른다 — 값이 비었는가로 가르지 않는다.

           예전에는 값이 null 인 칸을 모두 걸러 냈다. 「화면이 보내지 않은 칸은
           건드리지 않는다」는 뜻이었는데, 라라벨이 빈 문자열을 null 로 바꾸므로
           (ConvertEmptyStringsToNull) **비우려고 보낸 것**도 함께 걸렸다. 그래서
           한 번 적힌 값은 화면에서 지울 수 없었다 — 자격을 「선택」으로 되돌려도
           「일반」이 그대로 남았고(2026-09-08 · 3차 5회 문채아), 의사면허번호를
           지우고 저장해도 예전 번호가 남았다(2026-09-09 · 저장 이력에서 드러남).

           보내 온 이름으로 가르면 둘 다 바로 선다. 보내지 않은 칸은 여전히
           손대지 않는다 — 이 자리를 부분 저장으로 쓰는 길들이 그 약속에 기댄다.

           이름이 다른 몇을 따로 적는다. 청구전략은 화면이 보내는 값이 아니라
           유형 × 자격으로 다시 셈한 값이라, 유형을 보내 왔을 때만 함께 적는다. */
        $보낸이름 = [
            'rx_use_period'    => 'rx_period',
            'caregiver_name'   => 'guardian',
            'billing_strategy' => 'counsel_acc_add_type',
        ];
        $rxCols = array_filter(
            $rxCols,
            fn ($v, $칸) => $request->has($보낸이름[$칸] ?? $칸),
            ARRAY_FILTER_USE_BOTH,
        );

        /* **검수 요청 메모**는 담당자의 말이다 — 검수를 요청하기 전에만 받는다.

           검수자가 남기는 review_memo 와는 다른 칸이다. 여태 한 칸에 셋(요청 메모ㆍ
           승인 메모ㆍ반려 사유)이 섞여, 나중에 적은 것이 앞의 것을 덮었다
           (2026-09-09 지시). 화면도 요청 뒤에는 칸을 잠그지만 서버에서도 가린다 —
           화면만 믿을 수는 없다. */
        if ($request->has('review_request_memo')
            && in_array($prescription->status, ['pending', 'rejected'], true)
            && \Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'review_request_memo')) {
            $rxCols['review_request_memo'] = $request->input('review_request_memo');
        }

        /* **참고 사항**은 언제든 고친다 — 검수 요청 뒤에도, 승인 뒤에도.
           이 건을 두고 오래 남겨 둘 말이라 걸음에 따라 잠그지 않는다.
           칸이 없는 서버에서는 그냥 지나간다(2026-09-10 확인요청 5쪽). */
        if ($request->has('reference_note')
            && \Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'reference_note')) {
            $rxCols['reference_note'] = $request->input('reference_note');
        }

        if ($request->has('benefit_class')) {
            $rxCols['benefit_class'] = $request->input('benefit_class');

            /* 자격을 비우면 청구전략 열쇠도 다시 셈한다 — 두 값이 어긋나면
               목록이 「처방외인데 열쇠는 일반」이라는 줄을 세운다. */
            if (\App\Support\BillingStrategy::hasColumn()) {
                $rxCols['billing_strategy'] = \App\Support\BillingStrategy::key(
                    $request->input('counsel_acc_add_type'),
                    $request->input('benefit_class'),
                );
            }
        }

        if ($rxCols) {
            $prescription->update($rxCols);
        }

        /* 결제 방식은 주문에도 적는다. 연계할 때 안내를 무엇으로 보낼지 그 값이
           정한다(confirmPayMethod). 아직 주문이 없으면 만들 때 함께 적힌다.

           **받은 뒤에는 건드리지 않는다.** 이 칸은 확정 전에는 「무엇으로 안내할
           것인가」이지만, 확정 뒤에는 「무엇으로 받았는가」라 사실이다. 그런데 두
           화면이 같은 칸을 쓴다 — 정산에서는 셋(링크페이ㆍ가상계좌ㆍ무통장입금)을
           고르고 여기서는 둘(링크페이ㆍ가상계좌)만 고른다. 무통장입금으로 받아 둔
           건에서 이 화면을 저장하면, 고를 수 없는 값이라 링크페이로 내려앉은 것이
           그대로 덮여 **받은 방법이 바뀌어 버렸다**(2026-09-07 · 3차 5회 서나윤).
           받은 돈의 자취를 다른 화면이 고쳐 쓰게 두지 않는다. */
        if ($request->filled('pay_method') && $prescription->order) {
            $order = $prescription->order;
            $받았다 = $order->deposit_confirmed_at !== null || (bool) $order->tossPayment?->is_done;

            if (! $받았다) {
                $order->update(['pay_method' => $request->input('pay_method')]);
            }
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
            /* 어떻게 내는 사람인가(2026-09-03). 한 번 고르면 그 사람 것으로 남아
               다음 주문의 상세 목록 탭이 그 값으로 열린다. */
            'pay_method'          => $request->input('pay_method'),
            /* 연락할 때 먼저 거는 번호 — 거래처 관리에도 있는 칸이다.
               두 화면이 같은 값을 보아야 통화할 때 헤매지 않는다(2026-09-10 확인요청 5쪽). */
            'main_contact'        => $request->input('main_contact'),
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

        /* 급여 종료일ㆍ다음 재구매 가능일 = 모든 서류 발행일(＝결제일) ＋ 총 처방일수.

           돈이 들어오면 그때 서버가 채운다. 그런데 **결제가 없는 건**(기초ㆍ차상위)은
           들어올 돈이 없어 그 자리가 오지 않는다 — 그런 건은 담당자가 구입일을 적어
           저장하는 이 자리가 그때다(2026-09-09 확정).

           이미 받은 건은 손대지 않는다. 받은 날이 정본이고, 나중에 이 화면을 저장할
           때마다 오늘로 밀리면 급여 기간이 조용히 늘어난다. */
        if (! $prescription->order?->isDepositConfirmed()) {
            \App\Support\BenefitDates::apply($prescription);
        }

        /* ── 아이템 동기화 ────────────────────────────────────────

           추가 주문을 보고 있을 때는 처방 품목을 건드리지 않는다 (2026-09-15 지시).

           처방 품목은 처방전 한 장에 딸린 값이고, 화면은 보고 있는 주문의 품목을
           싣는다. 추가 주문에서 이 자리가 돌면 원 주문이 산 320개가 지워지고 이번에
           고른 16개로 바뀐다 — 처방전에 무엇을 얼마나 팔았는지가 사라진다.

           추가 주문의 품목은 주문 쪽(OrderController::update)이 적는다. */
        $items = $request->input('items', []);

        /* 화면이 보고 있는 주문. 관계로 잡은 order 는 언제나 첫 주문이라, 추가 주문을
           보고 있어도 원 주문으로 읽힌다 — 화면이 보내 준 번호로 가린다. */
        $보는주문 = $request->filled('order_number')
            ? $prescription->orders()->where('order_number', $request->input('order_number'))->first()
            : $prescription->order;

        if (!empty($items) && ! $보는주문?->isExtra()) {
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
                    /* 기관이 내는 몫은 청구전략(유형 × 자격)이 정한다. 전략이 아직
                       정해지지 않았거나 비율이 확인중인 자격이면 예전 규칙으로 셈한다. */
                    $rate = \App\Support\BillingStrategy::payerRate(
                                $request->input('counsel_acc_add_type'),
                                $request->input('benefit_class'))
                        ?? match($nhisStatus) {
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
        /* 합계도 화면이 보는 주문을 따른다 (2026-09-16).

           추가 주문은 처방 품목을 건드리지 않으므로 처방 품목 합계를 돌려주면
           화면의 「총 본인 부담금」이 원 주문 금액으로 바뀐다. 보낸 줄로 셈한다. */
        $totalNhis  = $보는주문?->isExtra()
            ? (int) collect($items)->sum(fn ($i) => (float) ($i['nhis_amount'] ?? 0))
            : $prescription->items->sum('nhis_amount');

        $totalCopay = $보는주문?->isExtra()
            ? (int) collect($items)->sum(fn ($i) => (float) ($i['patient_copay'] ?? 0))
            : $prescription->items->sum('patient_copay');

        /* 저장하면 주문 관리에도 선다. 처방전 그림이 없어도, 제품을 아직 안 골랐어도
           그렇다 — 주문 등록에서 저장한 건은 곧 하나의 거래이고, 그것을 보는 자리가
           주문 관리다. 예전에는 「주문 생성 및 연계」를 눌러야만 줄이 생겨, 상담만
           받아 적어 둔 건은 어느 목록에도 없이 떠 있었다.

           여기서는 우리 쪽 주문만 만든다. 위드웍스로 보내는 것은 그 단추가 할 일이다 —
           저장할 때마다 창고로 주문이 날아가서는 안 된다. */
        $order = $this->ensureOrder($prescription);

        /* 「OCR 필드 수정」이라 적어 왔다. 이 칸들이 처음에 처방전 그림을 OCR 로 읽어
           채우던 자리라 그렇게 불렀는데, 지금은 담당자가 손으로 적는다 — 저장 이력에서
           그 말은 무슨 일이 있었는지 아무것도 알려 주지 않는다.
           지난 줄에 남은 옛 말은 history() 가 읽을 때 이 말로 옮겨 세운다. */
        activity()->causedBy(Auth::user())->performedOn($prescription)->log('주문 등록 저장');

        /* 처방전 접수를 알리고, 위임동의 서명 SMS 를 보낸다(테스트 시나리오 1.1.x).
           서명 화면이 개인정보 동의도 함께 받으므로 링크 한 통으로 둘이 끝난다.

           한때 「이 저장으로 사람이 처음 붙었는가」로 가렸다. 그런데 업로드할 때 이미
           거래처를 고르므로 사람은 늘 붙어 있었다 — 그 잣대로는 한 번도 나가지 않았다
           (2026-09-03 시험 2차에서 드러났다). 두 번 보내지 않는 일은 각자 자기
           자취로 가린다.

           보내지 못해도 저장은 이미 끝난 것이라 되돌리지 않는다 — 무슨 일이 있었는지는
           답에 실어 화면이 함께 보여 준다(주문 확정 안내와 같은 방식). */
        $rxSms      = $this->rxReceivedSmsOnFirstSave($prescription);
        $consentSms = $this->consentSmsOnFirstSave($prescription);

        return response()->json([
            'success'     => true,
            'message'     => '저장되었습니다.',
            'consent_sms' => $consentSms,
            'rx_sms'      => $rxSms,
            /* 화면에 돌려줄 줄 — 추가 주문은 그 주문의 품목을 돌려준다 (2026-09-16).

               화면은 저장하고 나면 이 줄들로 표를 통째로 갈아 끼운다. 추가 주문은
               처방 품목을 건드리지 않으므로(위 아이템 동기화) 처방 품목을 그대로
               돌려주면 **담당자가 이번에 고른 제품이 그 자리에서 사라지고 원 주문이
               산 품목이 들어앉는다.** 실제로 30개를 담아 연계했는데 430개가
               저장됐다. */
            'items'       => $보는주문?->isExtra() ? [] : $prescription->items->map(fn($item) => [
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_status'     => $item->nhis_status,
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
                /* 장비코드도 함께 돌려준다 (2026-09-14 지시).
                   화면은 저장하고 나면 이 줄들로 표를 통째로 갈아 끼운다. 여기에 없는
                   값은 그 자리에서 사라지는데, 장비코드는 공단에 청구할 때 쓰는 번호라
                   빈 채로 두면 다시 찾아 적어야 한다. 품번에서 끌어내므로 담아 둘
                   칸이 없어도 언제나 같은 값이 선다. */
                'device_code'     => (string) (\App\Support\DeviceCode::for($item->product_code) ?? ''),
            ])->values(),
            'total_nhis'  => $totalNhis,
            'total_copay' => $totalCopay,
            /* 이 건이 어느 사람에게 붙었는지 돌려준다. 새 사람으로 저장하면 그 사람은
               방금 만들어진 것이라 화면이 id 를 모른다 — 저장 직후에 「상담하기」를
               누르면 누구와 상담하는지 가리지 못해 열리지 않았다. */
            'patient_id'   => $prescription->fresh()->patient_id,
            'patient_name' => $prescription->fresh()->patient?->name,
            /* 이 건의 주문번호. 화면이 아직 모르고 있으면(빈 초안으로 시작해 방금 선 경우)
               이것을 받아 쥔다 — 그래야 배송 정보를 담을 자리를 안다.
               「방금 생겼는가」로 가리지 않는다. 처방전이 담길 때 모델이 먼저 세우기도 해서
               (Prescription::booted → OrderSync::seed) 그 갈래로는 늘 「이미 있음」이 된다. */
            'order_created' => $order?->order_number ?? $prescription->order?->order_number,
            'order_id'      => $order?->id ?? $prescription->order?->id,
        ]);
    }

    /** 이 처방전의 주문 줄을 세워 둔다 — 몸통은 App\Support\OrderSync 다. */
    private function ensureOrder(Prescription $prescription): ?\App\Models\Order
    {
        return \App\Support\OrderSync::ensure($prescription);
    }

    // ── 검수 요청 ─────────────────────────────────────────
    /* 담당자가 손으로 다 적었다는 신호다. 여기서 상태만 바꾸고 값은 건드리지 않는다 —
       적는 일은 저장(saveOCR)이 이미 했다. 검수자가 「검수 완료」를 누르기 전까지
       담당자는 계속 고칠 수 있다. */
    /**
     * 입력 검수 요청 — 적어 넣은 값을 봐 달라고 청한다 (2026-09-16 지시).
     *
     * 파일 검수(requestReview)와 다른 일이다. 그쪽은 올라온 처방전ㆍ서류 이미지를 보고,
     * 이쪽은 담당자가 상세 목록ㆍ병원 처방 정보에 적어 넣은 값을 본다.
     *
     * 한 칸을 함께 쓰던 때에는 파일 검수가 끝난 건(approved)이 여기서 422 로 막혔다 —
     * 그런데 입력은 파일 검수 **뒤에** 하는 일이라, 정상 흐름에서는 누를 창이 없었다.
     * 이제 파일 검수 상태를 보지 않는다.
     */
    public function requestInputReview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        if ($prescription->입력검수승인했나()) {
            return response()->json([
                'success' => false,
                'message' => '이미 입력 검수를 마쳤습니다.',
            ], 422);
        }

        $prescription->update([
            'input_review_status'       => Prescription::INPUT_REVIEW_REQUESTED,
            'input_review_requested_at' => now(),
            'input_review_requested_by' => Auth::id(),
            'input_review_request_memo' => $request->input('memo') ?: $prescription->input_review_request_memo,
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('입력 검수 요청');

        /* 승인할 수 있는 사람들에게 알린다 — 파일 검수와 같은 길을 쓴다.
           알리지 못해도 요청은 이미 됐다. */
        try {
            app(\App\Services\ReviewNotice::class)->askReview($prescription->refresh());
        } catch (\Throwable $e) {
            Log::warning('[입력 검수] 요청 알림 실패', ['rx' => $prescription->rx_number, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success'      => true,
            'message'      => '입력 검수를 요청했습니다.',
            'input_review' => $prescription->refresh()->입력검수상태(),
        ]);
    }

    /**
     * 입력 검수 승인 — 적어 넣은 값을 확인했다.
     *
     * 처방전 상태(status)는 건드리지 않는다. 그것은 파일 검수의 것이다 —
     * 여기서 함께 옮기면 처방전 목록의 검수 줄이 알 수 없는 까닭으로 움직인다.
     */
    public function approveInputReview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        if ($prescription->입력검수승인했나()) {
            return response()->json([
                'success' => false,
                'message' => '이미 입력 검수를 마쳤습니다.',
            ], 422);
        }

        /* 요청이 있어야 승인한다 (2026-09-17 지시).

           여태 「이미 승인했나」만 보아, 요청이 없는 건에도 승인이 그대로 떨어졌다.
           그러면 누가 무엇을 검수해 달라 했는지 없이 승인만 남는다 —
           requested_at 과 requested_by 가 빈 채로 「승인됨」이 되어, 두 사람이
           맞대어 보는 절차가 한 사람의 단추 한 번으로 줄어든다. */
        if (! $prescription->입력검수요청했나()) {
            return response()->json([
                'success' => false,
                'message' => '입력 검수 요청이 없습니다 — 먼저 ［입력 검수 요청］을 눌러 주십시오.',
            ], 422);
        }

        $prescription->update([
            'input_review_status'      => Prescription::INPUT_REVIEW_APPROVED,
            'input_review_approved_at' => now(),
            'input_review_approved_by' => Auth::id(),
            'input_review_memo'        => $request->input('memo'),
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('입력 검수 승인');

        try {
            app(\App\Services\ReviewNotice::class)->tellApproved($prescription->refresh());
        } catch (\Throwable $e) {
            Log::warning('[입력 검수] 승인 알림 실패', ['rx' => $prescription->rx_number, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success'      => true,
            'message'      => '입력 검수를 승인했습니다.',
            'input_review' => $prescription->refresh()->입력검수상태(),
        ]);
    }

    public function requestReview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        if (in_array($prescription->status, ['approved', 'ordered'], true)) {
            return response()->json([
                'success' => false,
                'message' => '이미 검수가 끝난 처방전입니다.',
            ], 422);
        }

        /* 되물어서 되돌아온 건은 「검수 재요청」으로 세운다 (2026-09-15 지시).

           처음 올라온 건의 검수 요청과 섞이면, 검수자가 「이것은 한 번 되물었던
           건」임을 알 수 없다. 되물은 자취(검수 보류였거나 다시 올리기 요청이
           걸려 있었거나)가 있으면 재요청이다.

           요청하며 남긴 말은 **요청 메모** 칸에 담는다 — 검수자의 말과 섞이지 않는다.
           비워 두면 상세 목록에서 적어 둔 것을 그대로 지킨다. */
        $되물은적있나 = $prescription->status === 'review_hold'
            || $prescription->reuploadRequests()->exists();

        $요청 = ['status' => $되물은적있나 ? 'review_resent' : 'review_requested'];

        if (\Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'review_request_memo')) {
            $요청['review_request_memo'] = $request->memo ?: $prescription->review_request_memo;
        } elseif ($request->memo) {
            $요청['review_memo'] = $request->memo;   // 칸이 없는 서버는 예전대로
        }

        $prescription->update($요청);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('검수 요청');

        /* 승인할 수 있는 사람들에게 알린다 (2026-09-07 지시).
           여태 상태만 바꾸고 아무에게도 알리지 않아, 요청한 담당자는 눌러 놓고
           기다리고 검수자는 목록을 들여다봐야 알았다.
           알리지 못해도 요청은 이미 됐다 — 안에서 삼킨다. */
        app(\App\Services\ReviewNotice::class)->askReview($prescription->refresh());

        /* 바뀐 상태를 함께 돌려준다 — 화면이 그 자리만 고쳐 세우면 되도록.
           예전에는 새로고침으로 맞췄는데, 적던 자리가 통째로 처음으로 돌아갔다. */
        $prescription->refresh();

        return response()->json([
            'success'      => true,
            'message'      => '검수 요청 완료',
            'status'       => $prescription->status,
            'status_label' => $prescription->status_label,
            'status_badge' => $prescription->status_badge,
        ]);
    }

    /**
     * 올린 파일 목록 — 처방전 목록의 「파일 검수」 창이 읽는다 (2026-09-10 지시).
     *
     * 우리가 만든 서류(위임장ㆍ동의서)는 담지 않는다. 검수는 「환자가 올린 것이
     * 맞는가」를 보는 일이라, 우리가 만든 것을 함께 세우면 무엇을 봐야 하는지 흐려진다.
     */
    public function files(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $prescription->loadMissing(['attachments.uploader', 'creator']);
        // 되물은 차례대로 — 새것이 위로
        $prescription->load(['reuploadRequests' => fn ($q) => $q->latest('requested_at')]);

        /* 다시 올리기 요청을 파일별로 모아 둔다 (2026-09-12 지시).

           짚개는 첨부 id 다. 본 그림은 첨부가 아니어서 0 으로 센다 — 검수 창이
           파일을 가리킬 때 쓰는 값과 같다. */
        $요청들 = $prescription->reuploadRequests->groupBy(fn ($r) => (int) ($r->attachment_id ?? 0));

        $적기 = fn ($열쇠) => ($요청들[$열쇠] ?? collect())->map(fn ($r) => [
            'id'        => $r->id,
            'reason'    => $r->reason,
            'label'     => \App\Models\PrescriptionReuploadRequest::사유[$r->reason] ?? $r->reason,
            'memo'      => $r->memo,
            'by'        => $r->requested_by_name,
            'at'        => $r->requested_at?->format('Y-m-d H:i'),
            'to'        => $r->target_user_name,
            'sent'      => (bool) $r->fcm_sent,
            'error'     => $r->fcm_error,
            'resolved'  => $r->resolved_at?->format('Y-m-d H:i'),
        ])->values();

        $목록 = [];

        if ($prescription->image_url) {
            $목록[] = [
                'id'    => 0,
                'name'  => $prescription->rx_number,
                'label' => '처방전',
                'url'   => $prescription->image_url,
                'isPdf' => str_contains($prescription->image_mime_type ?? '', 'pdf'),
                /* 누가 올렸는지 서류 이름 옆에 (2026-09-12 지시). 본 그림은 첨부가
                   아니어서 올린 이가 따로 없다 — 처방전을 만든 사람이 그 사람이다. */
                'by'    => $prescription->creator?->name ?? '',
                /* 맞춰 둔 밝기ㆍ명암 — 검수 창도 같은 값으로 열고 같은 자리에 적는다
                   (2026-09-12 지시). key 는 image-tune 이 쓰는 그 열쇠다. */
                'key'      => 'rx',
                'bright'   => (int) ($prescription->img_brightness ?? 0),
                'contrast' => (int) ($prescription->img_contrast ?? 0),
                'requests' => $적기(0),
            ];
        }

        foreach ($prescription->attachments as $a) {
            if (! $a->file_url) {
                continue;   // 줄만 남고 파일이 없는 것 — 보여 줄 것이 없다
            }

            $목록[] = [
                'id'    => $a->id,
                'name'  => $a->file_original_name,
                'label' => $a->doc_type_label,
                'url'   => $a->file_url,
                'isPdf' => $a->is_pdf,
                'by'    => $a->uploader?->name ?? ($prescription->creator?->name ?? ''),
                /* 우리가 만든 서류인가 — 파일 창이 표시해 준다 (2026-09-16 지시).
                   담당자가 「내가 올린 적 없는 파일」을 보고 되묻는 일이 잦았다. */
                'made'     => $a->우리가만든것인가(),
                'key'      => 'att:' . $a->id,
                'bright'   => (int) ($a->img_brightness ?? 0),
                'contrast' => (int) ($a->img_contrast ?? 0),
                'requests' => $적기($a->id),
            ];
        }

        return response()->json([
            'ok'      => true,
            'rx'      => $prescription->rx_number,
            'patient' => $prescription->patient?->name ?: $prescription->patient_name_ocr,
            'status'  => $prescription->status,
            'label'   => $prescription->status_label,
            'files'   => $목록,
            'reasons' => \App\Models\PrescriptionReuploadRequest::사유,
        ]);
    }

    // ── 자료 다시 올리기 요청 ─────────────────────────────
    /**
     * 검수 창에서 파일 한 장을 짚어 「이것을 다시」를 남기고 앱으로 알린다
     * (2026-09-12 지시).
     *
     * 그림이 흐려 글씨가 안 읽히거나 처방전 자리에 다른 서류가 올라온 것을
     * 여태는 전화로 물었다. 올린 사람은 무엇을 다시 올려야 하는지 몰랐다.
     */
    public function requestReupload(
        Request $request,
        Prescription $prescription,
        \App\Services\ReuploadRequestService $요청서,
    ): \Illuminate\Http\JsonResponse {
        $값 = $request->validate([
            /* 0 이면 처방전 본 그림. 검수 창이 파일을 가리킬 때 쓰는 값 그대로다. */
            'file_id' => 'required|integer|min:0',
            'reason'  => 'required|in:' . implode(',', array_keys(\App\Models\PrescriptionReuploadRequest::사유)),
            'memo'    => 'nullable|string|max:500',
        ]);

        $첨부id = (int) $값['file_id'] ?: null;

        if ($첨부id && ! $prescription->attachments()->whereKey($첨부id)->exists()) {
            return response()->json(['success' => false, 'message' => '이 처방전의 파일이 아닙니다.'], 422);
        }

        if ($값['reason'] === 'etc' && ! trim((string) ($값['memo'] ?? ''))) {
            return response()->json(['success' => false, 'message' => '그 밖의 사유를 선택하셨으면 내용을 입력해 주십시오.'], 422);
        }

        $요청 = $요청서->걸기($prescription, $첨부id, $값['reason'], $값['memo'] ?? null);

        return response()->json([
            'success'      => true,
            'message'      => $요청->fcm_sent
                ? ($요청->target_user_name . '님 앱으로 전송했습니다.')
                : ('요청을 남겼습니다 — ' . ($요청->fcm_error ?: '앱 알림은 가지 않았습니다.')),
            'sent'         => $요청->fcm_sent,
            'status'       => $prescription->fresh()->status,
            'status_label' => $prescription->fresh()->status_label,
        ]);
    }

    // ── 검수 승인 ─────────────────────────────────────────
    public function approve(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        /* 이미 마친 건은 다시 승인하지 않는다. 두 번 누르면 검수자와 검수일시가 덮여,
           누가 언제 보았는지가 사라진다 — 검수 요청 쪽은 진작 이렇게 막고 있었다. */
        if (in_array($prescription->status, ['approved', 'ordered'], true)) {
            return response()->json([
                'success' => false,
                'message' => '이미 검수를 마친 처방전입니다.',
            ], 422);
        }

        $prescription->update([
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_memo' => $request->memo,
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('검수 승인');

        /* 요청한 담당자에게 되돌려 알린다 — 승인됐는지도 목록을 다시 봐야 알았다 */
        app(\App\Services\ReviewNotice::class)->tellApproved($prescription->refresh());

        // 바뀐 것을 함께 돌려준다 — 화면이 그 자리만 고쳐 세우면 되도록
        $prescription->refresh()->load('reviewer');

        return response()->json([
            'success'           => true,
            'message'           => '검수 승인 완료',
            'status'            => $prescription->status,
            'status_label'      => $prescription->status_label,
            'status_badge'      => $prescription->status_badge,
            'reviewer'          => $prescription->reviewer?->name,
            'reviewed_at'       => $prescription->reviewed_at?->format('Y-m-d H:i'),
            'reviewed_at_short' => $prescription->reviewed_at?->format('H:i'),
        ]);
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
    /**
     * 카카오 알림톡 발송 — 팝빌(ATS).
     *
     * 알림톡은 카카오가 승인한 템플릿으로만 나간다. 본문도 승인된 문구와 같아야 하므로,
     * 우리 유형의 본문에 값만 채워 그대로 보낸다(치환자가 곧 템플릿의 변수 자리다).
     *
     * 예전에는 알리고(App\Services\KakaoService)로 갔는데, 키가 하나도 없고 시험 모드가
     * 켜져 있어 「발송되었습니다」라고 답하고 아무것도 보내지 않았다 — 그렇게 기록만
     * 남은 건이 열두 건이다. 보내지 못하면 보내지 못했다고 답한다.
     */
    public function sendKakao(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'template_code' => 'required|string',
            'mobile'        => 'required|string',
        ]);

        $prescription->load(['patient', 'order.tossPayment']);
        $order = $prescription->order;
        $tp    = $order?->tossPayment;

        $tpl = \App\Models\MessageTemplate::channel('alimtalk')->active()
            ->where('code', $request->template_code)->first();

        if (!$tpl) {
            return response()->json(['success' => false, 'message' => '메시지 유형을 찾지 못했습니다.'], 422);
        }

        /* 팝빌에 등록ㆍ승인된 템플릿 코드가 있어야 나간다. 없으면 여기서 멈춘다 —
           보낸 것처럼 답해 두면 아무도 받지 못한 채 보냈다고 기록만 남는다. */
        $atsCode = \App\Models\MessageTemplate::hasAtsColumn() ? trim((string) $tpl->ats_template_code) : '';
        if ($atsCode === '') {
            return response()->json([
                'success' => false,
                'message' => "「{$tpl->label}」에 팝빌 알림톡 템플릿 코드가 없습니다. "
                           . '메시지 관리에서 승인받은 템플릿 코드를 넣어 주십시오.',
            ], 422);
        }

        $params = [
            '#{고객명}'    => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '고객',
            '#{처방번호}'  => $prescription->rx_number,
            '#{주문번호}'  => $order?->order_number ?? '-',
            '#{제품명}'    => $order?->product_name ?? $prescription->rx_number,
            // 배송비는 없다(2026-09-03 확정) — 받을 돈은 본인부담금 그대로다
            '#{금액}'      => $order ? number_format($order->expectedDeposit()) : '-',
            '#{본인부담금}'=> $order ? number_format($order->patient_copay ?? 0) : '-',
            '#{은행명}'    => $tp?->bank_name ?? '-',
            '#{계좌번호}'  => $tp?->account_number ?? '-',
            '#{기한}'      => $tp?->due_date?->format('Y-m-d H:i') ?? '-',
            '#{택배사}'    => '택배',
            '#{운송장번호}'=> $order?->tracking_number ?? '-',
            '#{배송지}'    => $order?->shipping_address ?? '-',
        ];

        $content = trim(strtr((string) $tpl->body, $params));
        $mobile  = preg_replace('/\D/', '', $request->mobile);

        /* 본문이 곧 나가는 글이다. 비어 있으면 팝빌이 거절하기 전에 여기서 멈춘다 —
           승인받은 문구를 메시지 관리의 본문 칸에 옮겨 적어야 한다. */
        if ($content === '') {
            return response()->json([
                'success' => false,
                'message' => "「{$tpl->label}」의 본문이 비어 있습니다. "
                           . '메시지 관리에서 승인받은 알림톡 문구를 입력해 주십시오.',
            ], 422);
        }

        try {
            $kakao = app(\App\Services\Popbill\KakaoService::class);

            $receiver        = $kakao->newReceiver();
            $receiver->rcv   = $mobile;
            $receiver->rcvnm = $params['#{고객명}'];
            $receiver->msg   = $content;

            $receiptNum = $kakao->sendAts(
                corpNum:      config('popbill.test.corp_num'),
                templateCode: $atsCode,
                sender:       config('popbill.test.sms_sender') ?: config('popbill.test.sender_num'),
                content:      $content,
                messages:     [$receiver],
                userId:       config('popbill.test.user_id'),
            );
        } catch (\Throwable $e) {
            Log::warning('[알림톡] 발송 실패', [
                'rx' => $prescription->rx_number, 'tpl' => $atsCode, 'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => '알림톡 발송 실패: ' . $e->getMessage()], 502);
        }

        $prescription->update(['kakao_sent_at' => now()]);
        activity()->causedBy(auth()->user())->performedOn($prescription)
            ->log("카카오 알림톡 발송: {$tpl->label}({$atsCode}) → {$mobile} · 접수번호 {$receiptNum}");

        return response()->json([
            'success'     => true,
            'message'     => '알림톡이 발송되었습니다.',
            'receipt_num' => $receiptNum,
        ]);
    }

    // ── 카카오 알림톡 미리보기 ──────────────────────────────
    public function kakaoPreview(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate(['template_code' => 'required|string']);

        $prescription->load(['patient', 'order.tossPayment', 'items']);
        $order = $prescription->order;
        $tp    = $order?->tossPayment;

        // 기관이 내는 몫은 청구전략이 정한다(정해지지 않았으면 예전 규칙)
        $stratRate = \App\Support\BillingStrategy::payerRate(
            $prescription->counsel_acc_add_type, $prescription->benefit_class);
        $itemCopay = (int) $prescription->items->sum(function ($i) use ($stratRate) {
            $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
            $qty  = (int)($i->quantity ?? 1);
            $rate = $stratRate ?? match ($i->nhis_status ?? 'eligible') {
                'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
            };
            return round($base * $qty) - round($base * $rate * $qty);
        });

        $params = [
            '#{고객명}'    => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '고객',
            '#{주문번호}'  => $order?->order_number ?? '-',
            '#{제품명}'    => $order?->product_name ?? $prescription->rx_number,
            '#{본인부담금}'=> $itemCopay ? number_format($itemCopay) : '-',
            '#{금액}'      => $itemCopay ? number_format($itemCopay) : '-',
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

    /**
     * 주문 목록에서 빈 건을 지운다.
     *
     * 처방전도 올라오지 않았고 주문도 창고에 서지 않은 건 — 잘못 만들어져 「손댈 차례」에
     * 이름만 올려 두고 있는 자리다. 처방전과 주문 줄을 함께 지운다(둘 다 소프트 삭제라
     * 되돌릴 수 있다).
     *
     * 하나라도 실제로 일어난 일이 있으면 지우지 않는다. 지우는 것은 되돌리기 어렵고,
     * 어긋난 자리를 남기느니 못 지우는 편이 낫다.
     */
    public function destroyEmpty(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $prescription->loadMissing('order');
        $order = $prescription->order;

        $blockers = [];
        if ($prescription->image_path)            $blockers[] = '처방전이 올라와 있습니다';
        if ($order?->withworks_so_no)             $blockers[] = '창고에 주문이 서 있습니다';
        if ($order?->deposit_confirmed_at)        $blockers[] = '입금이 확인된 건입니다';
        if (($order?->tax_invoice_status ?? 'not_issued') !== 'not_issued')   $blockers[] = '세금계산서가 발행된 건입니다';
        if (($order?->cash_receipt_status ?? 'not_issued') !== 'not_issued')  $blockers[] = '현금영수증이 발행된 건입니다';
        if ($prescription->attachments()->exists()) $blockers[] = '첨부한 서류가 있습니다';
        if ($prescription->consents()->where('status', 'agreed')->exists()) $blockers[] = '위임동의 서명을 받은 건입니다';

        if ($blockers) {
            return response()->json([
                'success' => false,
                'message' => '지울 수 없습니다 — ' . implode(' · ', $blockers) . '.',
            ], 422);
        }

        $no = $prescription->rx_number;
        activity()->causedBy(Auth::user())->performedOn($prescription)
            ->log("빈 건 삭제 (처방전 {$no}" . ($order ? ", 주문 {$order->order_number}" : '') . ')');

        $order?->delete();
        $prescription->delete();

        return response()->json(['success' => true, 'message' => "{$no} 을(를) 지웠습니다."]);
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
        /* 환자가 보는 서명 화면에 그대로 서는 이름이다 — (E) 를 뗀다(2026-09-10 지시).
           화면에서 고쳐 보낸 이름은 사람이 적은 것이라 그대로 둔다. */
        $patientName = trim((string) $request->input('name'))
            ?: (\App\Models\Patient::bare($prescription->patient?->name) ?: ($prescription->patient_name_ocr ?? '환자'));

        return $this->issueConsent($prescription, $mobile, $patientName);
    }

    /**
     * 첫 저장에 얹는 위임동의 SMS.
     *
     * 주문 등록에서 처음 저장하는 그 자리가 처방전을 사람에게 붙이는 자리다 — 그 전에는
     * 보낼 번호도 없다. 담당자가 저장하자마자 손으로 누르던 단추를 대신 누른다.
     *
     * 보내지 않는 때에는 까닭을 함께 돌려준다 — 조용히 지나가면 담당자는 나간 줄 알고
     * 기다린다. 어느 경우든 손으로 보내는 단추는 그대로 있다.
     *
     * @return array{sent: bool, reason: ?string, expires_at: ?string}
     */
    /**
     * 처방전이 접수됐다는 것을 알린다(테스트 시나리오 1.1.x · 2026-09-03).
     *
     * 담당자가 상세 목록을 처음 저장하는 그 자리가 처방전을 사람에게 붙이는 자리다.
     * 그때 환자에게는 아무 말도 가지 않았다 — 서류를 보낸 사람은 접수됐는지 몰라
     * 전화로 물었다.
     *
     * 위임동의 링크와는 다른 통이다. 그쪽은 「서명해 주십시오」이고 이것은
     * 「받았습니다」다. 동의를 이미 받아 둔 사람에게는 링크가 가지 않으므로,
     * 합쳐 두면 그 사람들은 접수됐다는 것조차 듣지 못한다.
     *
     * 두 번 보내지 않는다. 못 보내도 저장을 막지 않는다.
     */
    private function rxReceivedSmsOnFirstSave(Prescription $prescription): array
    {
        /* 못 보냈으면 **까닭을 함께 돌려준다**.

           여태 참ㆍ거짓만 돌려주어, 연락처가 비어 못 보낸 것과 이미 보낸 것이
           화면에서 똑같이 「아무 일도 없음」으로 보였다. 담당자는 나간 줄 알고
           기다린다(2026-09-08 · 3차 5회 문채아 — 연락처가 비어 있었다). */
        $no = fn (?string $why) => ['sent' => false, 'reason' => $why];

        if (! config('order.rx_received_sms_on_first_save')) return $no(null);

        $prescription->refresh()->loadMissing('patient');
        $patient = $prescription->patient;
        if (! $patient) return $no(null);                    // 아직 사람이 붙지 않았다

        $source = 'rx-received';

        $mobile = preg_replace('/\\D/', '', (string) ($patient->mobile ?: $prescription->mobile_ocr));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return $no('연락처가 없어 접수 안내를 보내지 못했습니다.');
        }

        if (\App\Models\MessageHistory::where('source', $source)
                ->where('prescription_id', $prescription->id)
                ->where('success_count', '>', 0)
                ->exists()) {
            return $no(null);                                // 이미 알렸다 — 말할 것이 없다
        }

        // 환자가 받는 글이다 — (E) 는 우리 쪽 사업부 표시라 여기 설 자리가 없다(2026-09-10)
        $name = \App\Models\Patient::bare($patient->name) ?: ($prescription->patient_name_ocr ?: '고객');

        $body = \App\Models\MessageTemplate::channel('sms')->active()
            ->where('code', 'rx_received')->value('body')
            ?: "[콜로플라스트] #{고객명}님, 처방전이 접수되었습니다.\n처방번호: #{처방번호}";

        $text = strtr($body, [
            '#{고객명}'   => $name,
            '#{처방번호}' => $prescription->rx_number,
        ]);

        try {
            $res = app(\App\Services\MessageSender::class)->sendBulk(
                'sms',
                [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $patient->id]],
                $text,
                null,
                ['source' => $source, 'prescription_id' => $prescription->id],
            );
        } catch (\Throwable $e) {
            Log::warning('[처방전 접수 안내] 보내지 못했다', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);

            return $no('접수 안내를 보내지 못했습니다.');
        }

        if ($res['success'] ?? false) {
            activity()->causedBy(Auth::user())->performedOn($prescription)
                ->log("처방전 접수 안내 발송 → {$mobile}");

            return ['sent' => true, 'reason' => null];
        }

        return $no('접수 안내를 보내지 못했습니다.');
    }

    private function consentSmsOnFirstSave(Prescription $prescription): array
    {
        $no = fn (?string $why) => ['sent' => false, 'reason' => $why, 'expires_at' => null];

        if (!config('order.consent_sms_on_first_save')) return $no(null);   // 꺼 두었으면 말도 하지 않는다

        $prescription->refresh()->loadMissing('patient');
        $patient = $prescription->patient;
        if (!$patient) return $no(null);                                    // 아직 사람이 붙지 않았다

        /* **자격으로 가르지 않는다.** 처음 오는 거래처는 산재ㆍ자동차보험ㆍ처방외라도
           서명을 받는다(2026-09-08 지시).

           한때 「우리가 대신 받을 급여가 없는 갈래에는 묻지 않는다」로 막아 두었다가
           되돌렸다. 그 판단은 이 문자를 급여 위임 하나로만 본 것이다 — 서명 화면은
           개인정보 수집ㆍ이용 동의도 함께 받고, 자격은 나중에 바뀐다. 처음에 한 번
           받아 두지 않으면 그때 가서 환자를 다시 부르게 된다.

           가르는 잣대는 자격이 아니라 **처음인가**다. 아래가 그 일을 한다.

           한 번 물어본 사람에게는 저장할 때마다 다시 보내지 않는다. 처방전이 아니라
           사람으로 본다 — 위임은 사람이 하는 것이고, 화면의 「위임동의 완료」도
           그렇게 읽는다.

           만료된 것도 「이미 물어본 것」으로 센다. 링크는 30분만 살아 있어, 그것을
           빼면 담당자가 칸 하나 고쳐 저장할 때마다 환자에게 새 링크가 나간다.
           만료된 건을 다시 보내는 것은 배지 안의 「재발송」이 할 일이다. */
        $asked = \App\Models\PrescriptionConsent::whereIn(
                'prescription_id', Prescription::where('patient_id', $patient->id)->select('id'))
            ->exists();
        if ($asked) return $no('이미 위임동의를 보낸 분입니다.');

        $mobile = preg_replace('/\D/', '', (string) ($patient->mobile ?: $prescription->mobile_ocr));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return $no('연락처가 없어 위임동의를 보내지 못했습니다.');
        }

        /* 서명 링크는 30분만 열린다. 밤에 보내면 환자가 아침에 열어 이미 만료다 —
           정해 둔 시간 밖이면 보내지 않고 그렇게 말한다. */
        if (!$this->withinConsentHours()) {
            return $no('발송 시간(' . config('order.consent_sms_hours') . ') 밖이라 보내지 않았습니다.');
        }

        // 환자가 받는 글이고 서명 화면에 그대로 선다 — (E) 를 뗀다(2026-09-10)
        $name = \App\Models\Patient::bare($patient->name) ?: ($prescription->patient_name_ocr ?: '고객');

        try {
            $res = $this->issueConsent($prescription, $mobile, $name)->getData(true);
        } catch (\Throwable $e) {
            Log::error('[위임동의] 첫 저장 발송 실패', ['rx' => $prescription->rx_number, 'error' => $e->getMessage()]);

            return $no('위임동의를 보내지 못했습니다.');
        }

        return [
            'sent'       => (bool) ($res['success'] ?? false),
            'reason'     => ($res['success'] ?? false) ? null : ($res['message'] ?? '보내지 못했습니다.'),
            'expires_at' => $res['expires_at'] ?? null,
        ];
    }

    /** 지금이 위임동의를 보내도 되는 시간인가 — 비워 두었으면 가리지 않는다 */
    private function withinConsentHours(): bool
    {
        $range = trim((string) config('order.consent_sms_hours'));
        if ($range === '') return true;

        if (!preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $range, $m)) {
            Log::warning('[위임동의] 발송 시간 설정을 읽지 못했다 — 가리지 않는다', ['value' => $range]);

            return true;
        }

        $now = now();
        $from = $now->copy()->setTimeFromTimeString($m[1]);
        $to   = $now->copy()->setTimeFromTimeString($m[2]);

        return $now->betweenIncluded($from, $to);
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
        /* 링크가 열려 있는 동안은 설정이 정한다 (2026-09-19).

           설정(delegation_sign.link_minutes)과 그것을 읽는 자리
           (DelegationSignController::유효분)가 진작 있었는데, 정작 여기는 30을
           글자로 박아 두어 설정을 바꿔도 이 길로 나간 링크만 30분이었다.
           문자에 적는 「○분 유효」도 같은 값을 쓴다 — 따로 적으면 화면이 거짓말을 한다. */
        $유효분     = \App\Http\Controllers\DelegationSignController::유효분();
        $token       = \Illuminate\Support\Str::random(24);
        $expiresAt   = now()->addMinutes($유효분);

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
            /* 서명 화면에 「누가 보냈는가」를 세우려면 남아 있어야 한다 —
               환자가 받는 것은 모르는 번호에서 온 링크 하나다(요청서 2026-09-02). */
            'sent_by'            => \Illuminate\Support\Facades\Auth::id(),
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
        $message = "[콜로플라스트] {$patientName}님\n요양비 청구 서류 확인 및 전자서명 요청입니다.\n서명 링크({$유효분}분 유효):\n{$url}";

        try {
            /* 발송 내역을 쌓는 길로 보낸다. 팝빌을 곧바로 부르면 문자는 나가지만
               「발송ㆍ내역」에는 아무것도 남지 않아, 나갔는지 담당자가 알 길이 없었다. */
            $res = $this->sender->sendBulk('sms',
                [['rcv' => $mobile, 'rcvnm' => $patientName, 'patient_id' => $prescription->patient_id]],
                $message, null,
                ['source' => 'consent', 'prescription_id' => $prescription->id]);

            if (! ($res['success'] ?? false)) {
                throw new \RuntimeException($res['message'] ?? '문자를 보내지 못했습니다.');
            }

            activity()->causedBy(auth()->user())->performedOn($prescription)
                ->log("위임동의 SMS 발송 → {$patientName} {$mobile}");

            return response()->json([
                'success'    => true,
                'message'    => 'SMS가 발송되었습니다.',
                'expires_at' => $expiresAt->format('H:i'),
                'consent_id' => $consent->id,
            ]);
        } catch (\Throwable $e) {
            /* 문자가 못 나가도 **서명 요청은 지우지 않는다** (2026-09-19 지시).
               여태 여기서 $consent->delete() 를 했다. 그러면 문자 한 번 실패로 링크가
               통째로 사라져, 담당자가 그 링크를 손으로 건네줄 길조차 없었다 —
               시험에서 발신번호 하나가 미등록이라 서명을 아예 받지 못했다.
               줄은 그대로 두고 링크를 함께 돌려준다. 환자는 그 링크로 바로 서명한다. */
            Log::error('[위임동의] SMS 발송 실패 — 링크는 남긴다', [
                'error' => $e->getMessage(), 'rx' => $prescription->id, 'consent' => $consent->id,
            ]);

            return response()->json([
                'success'    => false,
                'sent'       => false,
                'message'    => 'SMS 발송 실패: ' . $e->getMessage(),
                'url'        => $url,
                'expires_at' => $expiresAt->format('H:i'),
                'consent_id' => $consent->id,
            ], 200);
        }
    }

    /**
     * 신분증만 받는 링크를 보낸다.
     *
     * 위임동의 링크는 서명ㆍ개인정보 동의ㆍ신분증을 한자리에서 받는다. 그런데 신분증만
     * 빠진 채로 끝나는 건이 있다 — 사진이 흐리거나, 그 자리에 신분증이 없거나.
     * 서명을 다시 받자고 위임동의를 새로 보낼 수는 없다(받아 둔 서명이 무효가 된다).
     * 그래서 신분증 하나만 청하는 링크를 따로 둔다(2026-09-09 지시).
     *
     * 같은 표를 쓰되 kind 로 갈라 세운다 — 토큰ㆍ만료ㆍ발송 내역이 이미 여기에 있다.
     * 본인 것과 보호자 것을 둘 다 받는다. 미성년이 아니면 보호자 칸은 세우지 않는다.
     */
    public function issueIdCard(Prescription $prescription, string $mobile, string $patientName): \Illuminate\Http\JsonResponse
    {
        /* 신분증만 받는 링크도 같은 설정을 따른다 (2026-09-19) */
        $유효분   = \App\Http\Controllers\DelegationSignController::유효분();
        $token     = \Illuminate\Support\Str::random(24);
        $expiresAt = now()->addMinutes($유효분);

        /* 나이는 마스킹된 주민번호 앞자리로 안다 — 원문을 열지 않는다(P0-1). */
        $masked  = $prescription->resident_no_ocr_masked ?: $prescription->patient?->masked_resident_no;
        $birth   = \App\Support\ResidentNo::birthDateFromMasked($masked);
        $isMinor = $birth ? $birth->age < (int) config('delegation.minor_age', 19) : false;

        $consent = \App\Models\PrescriptionConsent::create([
            'prescription_id'    => $prescription->id,
            'kind'               => 'id_card',
            'token'              => $token,
            'patient_name'       => $patientName,
            'patient_mobile'     => $mobile,
            'expires_at'         => $expiresAt,
            'status'             => 'pending',
            'sent_by'            => \Illuminate\Support\Facades\Auth::id(),
            'is_minor'           => $isMinor,
            'patient_birth_date' => $birth?->toDateString(),
            'guardian_name'      => $isMinor ? ($prescription->patient?->guardian_name ?: null) : null,
        ]);

        $baseUrl = rtrim(config('app.consent_public_url', config('app.url')), '/');
        // 반드시 https:// 스킴으로 (일부 SMS 앱은 http를 자동 링크 미처리)
        if (str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }
        $url = $baseUrl . '/consent/' . $token;

        $message = "[콜로플라스트] {$patientName}님\n건강보험 등록에 필요한 신분증 제출 요청입니다.\n제출 링크({$유효분}분 유효):\n{$url}";

        try {
            $res = $this->sender->sendBulk('sms',
                [['rcv' => $mobile, 'rcvnm' => $patientName, 'patient_id' => $prescription->patient_id]],
                $message, null,
                ['source' => 'consent', 'prescription_id' => $prescription->id]);

            if (! ($res['success'] ?? false)) {
                throw new \RuntimeException($res['message'] ?? '문자를 보내지 못했습니다.');
            }

            activity()->causedBy(auth()->user())->performedOn($prescription)
                ->log("신분증 제출 SMS 발송 → {$patientName} {$mobile}");

            return response()->json([
                'success'    => true,
                'message'    => 'SMS가 발송되었습니다.',
                'expires_at' => $expiresAt->format('H:i'),
                'consent_id' => $consent->id,
            ]);
        } catch (\Throwable $e) {
            $consent->delete();
            Log::error('[신분증] SMS 발송 실패', ['error' => $e->getMessage(), 'rx' => $prescription->id]);

            return response()->json(['success' => false, 'message' => 'SMS 발송 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 신분증 SMS 발송 ───────────────────────────────────
    public function sendIdCardSms(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'mobile' => 'required|string|max:20',
            'name'   => 'nullable|string|max:50',
        ]);

        // 오타로 건을 만들고 SMS 를 태우는 일만 막는다(sendConsentSms 와 같은 잣대).
        $mobile = preg_replace('/\D/', '', $request->mobile);
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return response()->json(['success' => false, 'message' => '수신 번호 형식이 올바르지 않습니다.'], 422);
        }

        // 신분증 제출 화면도 환자가 본다 — 같은 잣대다(2026-09-10 지시)
        $patientName = trim((string) $request->input('name'))
            ?: (\App\Models\Patient::bare($prescription->patient?->name) ?: ($prescription->patient_name_ocr ?? '환자'));

        return $this->issueIdCard($prescription, $mobile, $patientName);
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
        // 환자가 받는 문자다 — (E) 를 뗀다(2026-09-10 지시)
        $patientName = \App\Models\Patient::bare($prescription->patient?->name) ?: ($prescription->patient_name_ocr ?? '');

        try {
            $res = $this->sender->sendBulk('sms',
                [['rcv' => $mobile, 'rcvnm' => $patientName, 'patient_id' => $prescription->patient_id]],
                $message, null,
                ['source' => 'prescription', 'prescription_id' => $prescription->id]);

            if (! ($res['success'] ?? false)) {
                throw new \RuntimeException($res['message'] ?? '문자를 보내지 못했습니다.');
            }

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
    /**
     * 왜 공단에 청구하지 않는가 — 사람이 읽을 한 마디.
     *
     * 유형이 「처방외」면 자격이 무엇이든 처방외다. 자격만 보고 말하면
     * 처방외 건에 「일반」이라 적혀 담당자가 왜 막혔는지 알 수 없다.
     */
    private function noClaimReason(Prescription $prescription): string
    {
        if ((string) $prescription->counsel_acc_add_type === \App\Support\BillingStrategy::TYPE_NONRX) {
            return '처방외';
        }

        return $prescription->benefit_class ?: '해당 없음';
    }

    public function sendFax(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'recipient_type'  => 'required|string|max:50',
            'fax_no'          => ['required', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'documents'       => 'nullable|array',
            'documents.*'     => 'string|in:authorization,delegation,prescription,purchase_history,cash_receipt,tax_invoice,guardian_id',
            'attachment_ids'  => 'nullable|array',
            'attachment_ids.*' => 'integer|exists:prescription_attachments,id',
        ]);

        /* 지자체(시군구청)로 내는 건은 팩스로 보내지 않는다 — 등기로 부친다
           (2026-09-05 지시).

           받는 곳이 받지 않는 방법으로 서류가 나가면, 나간 줄 알고 등기를 부치지
           않는다. 그러면 청구가 아예 접수되지 않은 채 기한이 지난다.

           수신처를 「기타(직접 입력)」로 골라 다른 곳에 보내는 것은 막지 않는다 —
           공단ㆍ지자체가 아닌 곳(보험사 따위)으로 증빙을 보내는 길이 따로 있다. */
        if ($request->recipient_type === 'nhis'
            && optional($prescription->billingOffice)->kind === 'local') {
            return response()->json([
                'success' => false,
                'message' => '지자체(시군구청) 건은 팩스로 보내지 않습니다 — 등기로 부치십시오. '
                           . '「청구 관리」에서 서류를 출력하여 발송한 뒤 등기번호를 입력해 주십시오.',
            ], 422);
        }

        /* 공단에 청구하지 않는 건은 공단으로 보내지 않는다 (2026-09-05 보탬).

           산재ㆍ자동차보험ㆍ처방외는 요양비 청구 자체가 없다(ClaimAgency::NONE).
           그런데 팩스 창은 이런 건에서도 「국민건강보험공단」을 수신처로 세우고
           지사 목록을 펴 두었다 — 담당자가 그 자리에서 관할 지사를 골라 보내면,
           청구하지도 않을 건의 처방전ㆍ신분증ㆍ결과지가 공단으로 나간다.
           한 번 나간 것은 되돌릴 수 없다.

           「기타(직접 입력)」로 보내는 길은 열어 둔다 — 근로복지공단ㆍ보험사로
           증빙을 보내는 일이 실제로 있다(케이스 4.5ㆍ10.5). */
        if ($request->recipient_type === 'nhis'
            && ($prescription->claim_agency ?? null) === \App\Support\ClaimAgency::NONE) {
            return response()->json([
                'success' => false,
                'message' => '이 건은 공단에 청구하지 않습니다(' . $this->noClaimReason($prescription) . ') — '
                           . '공단으로 보낼 수 없습니다. 근로복지공단ㆍ보험사로 보내려면 '
                           . '수신처를 「기타(직접 입력)」를 선택하고 번호를 입력해 주십시오.',
            ], 422);
        }

        if (empty($request->documents) && empty($request->attachment_ids)) {
            return response()->json(['success' => false, 'message' => '전송할 서류를 하나 이상 선택해 주십시오.'], 422);
        }

        $docLabels = [
            'authorization'    => '위임장',
            'delegation'       => '요양비위임장',
            'prescription'     => '처방전',
            'purchase_history' => '제품 구매내역',
            'cash_receipt'     => '현금영수증',
            /* 미성년자 건에만 함께 나간다. 첨부가 아니라 개인정보동의에 딸린 파일이라
               attachment_ids 로는 고를 수 없다 — 여기서 이름을 붙인다. */
            'guardian_id'      => '법정대리인 신분증',
        ];
        /* 심평원은 우리 팩스를 받지 않는다. 고를 수 있게 두면 잘못 보낸다.

           「공단」 자리는 이 건의 관할 청구처를 따른다 — 기초(의료급여) 건은
           시군구청으로 내는데도 「국민건강보험공단」이라 적혀 남았다.
           보낸 곳과 적힌 곳이 다르면 나중에 어디로 갔는지 알 수 없다. */
        $office = $prescription->billingOffice;

        $recipientLabels = [
            'nhis'   => ($office && $office->kind === 'local')
                ? $office->displayName()
                : '국민건강보험공단',
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

        /* 붙일 파일이 하나도 없으면 보내지 않는다.
           여태 여기서 멈추지 않아, 팝빌을 부르지도 않고 「보냈습니다」로 답했다 —
           담당자는 나간 줄 알고 기다렸고, 공단에는 아무것도 가지 않았다.
           서버에 파일이 없는 일이 실제로 있다(발행은 다른 서버에서 한 건이 있다). */
        if (empty($filePaths)) {
            return response()->json([
                'success' => false,
                'message' => '보낼 파일을 서버에서 찾지 못해 보내지 않았습니다 — 서류 관리에서 파일을 확인해 주십시오.',
            ], 422);
        }

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

        /* 팩스 제목 — 갈래로 묶어 짧게 적는다.

           예전에는 첨부마다 「갈래: 파일이름」을 그대로 이어 붙였다. 결과지가 열여덟
           장인 건에서는 제목이 천 자를 넘어 `fax_histories.title`(varchar 200)에
           들어가지 못했고, 팝빌로 팩스를 보낸 뒤 자취를 남기다 죽어 화면에는
           「Server Error」만 떴다 — 보내기는 보냈는데 남은 것이 없는 셈이다.

           읽는 사람에게 필요한 것은 「무엇을 몇 장 보냈나」다. 파일 이름 열여덟 개가
           아니다. 갈래마다 한 번만 적고 여러 장이면 장수를 붙인다. */
        $attachCounts = [];
        foreach ($attachmentLabels as $label) {
            $kind = trim(explode(':', $label, 2)[0]);
            $attachCounts[$kind] = ($attachCounts[$kind] ?? 0) + 1;
        }

        $allDocLabels = array_merge($docs, array_map(
            fn ($kind, $n) => $n > 1 ? "{$kind} {$n}장" : $kind,
            array_keys($attachCounts), $attachCounts
        ));

        $faxTitle = "[CE] {$prescription->rx_number} " . implode('·', $allDocLabels);

        /* 그래도 넘칠 수 있다 — 갈래가 많으면 길어진다. 칸에 들어갈 만큼만 남긴다.
           제목이 잘리는 것과 자취가 통째로 사라지는 것은 견줄 일이 아니다. */
        $faxTitle = mb_substr($faxTitle, 0, 200);

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

        /* 자취 남기기 — 여기서 죽어도 팩스는 이미 나갔다.

           팩스를 보낸 뒤에 적는 자리라, 여기서 예외가 나면 화면에는 500 「Server Error」
           만 뜬다. 담당자는 안 나간 줄 알고 다시 보내고, 같은 팩스가 두 번 간다.
           남기지 못한 것과 보내지 못한 것은 다른 일이다 — 못 남겼으면 그렇게 알린다. */
        try {
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
        } catch (\Throwable $e) {
            Log::error('[Fax] 보냈으나 이력을 남기지 못했다', [
                'rx' => $prescription->rx_number, 'receipt' => $receiptNum, 'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success'     => true,
                'message'     => '팩스는 발송되었으나 전송 이력을 저장하지 못했습니다 — 발송 내역에 표시되지 않습니다. '
                               . '다시 보내지 마시고 관리자에게 알려 주십시오.'
                               . ($receiptNum ? " (접수번호 {$receiptNum})" : ''),
                'receipt_num' => $receiptNum,
                'recipient'   => $recipient,
                'fax_no'      => $request->fax_no,
                'documents'   => $allDocLabels,
                'auth_info'   => $authInfo,
                'pdf_url'     => $pdfUrl,
                'log_failed'  => true,
            ]);
        }

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
     * 시스템이 만든 서류 목록 (서명 완료 뒤 문서 칸을 새로 고칠 때 쓴다).
     *
     * 예전에는 「생성 서류」 카드의 HTML 조각을 돌려줬다. 그 카드를 걷고 문서 칸
     * 하나로 모았으므로, 이제는 화면이 그림칸을 다시 그릴 수 있게 값만 준다.
     */
    public function generatedDocs(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $prescription->load('documents');

        return response()->json(['docs' => self::generatedDocsJson($prescription)]);
    }

    /**
     * 시스템이 만든 서류를 문서 칸이 읽는 모양으로.
     *
     * id 는 음수다 — 첨부 파일의 id 와 한 배열에 서므로 겹치면 안 되고, 팩스 창처럼
     * 「첨부만」 세는 곳이 id 가 양수인 것만 고르기 때문이다.
     */
    private static function generatedDocsJson(Prescription $prescription): array
    {
        return $prescription->documents->map(fn ($d) => [
            'id'          => -1000 - $d->id,
            'docId'       => $d->id,
            'url'         => route('documents.preview', $d),
            'downloadUrl' => route('documents.download', $d),
            'type'        => $d->type,
            'typeLabel'   => $d->typeLabel(),
            'name'        => $d->original_filename ?: $d->typeLabel(),
            /* 지금 만드는 것은 모두 PDF 지만, 예전에 장표를 PNG 로 그려 넣던 시절의
               줄이 남아 있다. 확장자로 가른다 — PDF 가 아닌 것을 pdf.js 에 주면
               그리지 못하고 예전 방식으로 떨어진다. */
            'isPdf'       => strtolower(pathinfo((string) $d->file_path, PATHINFO_EXTENSION)) === 'pdf',
            'isRx'        => false,
            'isGenerated' => true,
        ])->values()->toArray();
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

        /* 기존 팩스통합본 교체.

           파일은 두 디스크에 흩어져 있다 — 보낼 때 만든 것은 public, 다시 만든 것은
           local(app/private) 이다. 기본 디스크만 보고 지워 왔더니 다른 쪽에 있는
           파일은 남고 표의 줄만 사라져, 아무도 가리키지 않는 PDF 가 쌓였다.
           내려받기가 두 디스크를 다 뒤지듯(PrescriptionDocumentController) 여기서도
           둘 다 본다 (2026-09-12). */
        foreach (PrescriptionDocument::where('prescription_id', $prescription->id)->where('type', 'fax')->get() as $old) {
            foreach (['local', 'public'] as $디스크) {
                if ($old->file_path && Storage::disk($디스크)->exists($old->file_path)) {
                    Storage::disk($디스크)->delete($old->file_path);
                }
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
                $rxImageDataUri = $this->rxImageToPortraitDataUri(
                    $absPath,
                    (int) ($prescription->img_brightness ?? 0),
                    (int) ($prescription->img_contrast ?? 0),
                );
            }
        }

        $parts = [];

        if ($this->faxBodyHasAnything($docs, $prescription, $rxImageDataUri)) {
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
            $parts[] = $dompdf->output();
        }

        // 요양비위임장(별지 제19호의7 원본 오버레이) 병합
        if (in_array('delegation', $docs)) {
            $delegBytes = app(\App\Http\Controllers\ConsentController::class)->overlayPdfBytes($prescription);
            if ($delegBytes) {
                $parts[] = $delegBytes;
            }
        }

        $pdfOutput = $this->joinFaxParts($parts);

        $mobile   = preg_replace('/[^0-9]/', '', $patient?->mobile ?? '');
        $filename = '팩스통합본_' . ($patient?->name ?? '') . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';

        return [$pdfOutput, $filename];
    }

    /**
     * 서식이 그릴 것이 있는가 — fax-pdf.blade 의 갈래와 같은 조건이다.
     *
     * 하나도 없으면 그 서식은 백지 한 장을 그린다. 그 백지가 위임장 앞에 붙어
     * 공단으로 나갔다 — 받는 쪽에는 빈 장으로 시작하는 팩스가 된다.
     */
    private function faxBodyHasAnything(
        array $docs,
        Prescription $prescription,
        ?string $rxImageDataUri,
        array $attachmentDataUris = [],
        ?array $taxInvoiceForm = null,
        ?array $cashReceiptForm = null
    ): bool {
        return in_array('authorization', $docs, true)
            || (bool) $rxImageDataUri
            || (in_array('purchase_history', $docs, true) && $prescription->order)
            || (bool) $taxInvoiceForm
            || (bool) $cashReceiptForm
            || (bool) $attachmentDataUris;
    }

    /**
     * 만들어 둔 조각을 한 벌로 잇는다.
     *
     * 한 장도 없으면 저장하지 않고 알린다 — 빈 파일을 남기면 화면은 「보냈다」로
     * 읽고, 담당자는 무엇이 빠졌는지 알 수 없다.
     *
     * @param  array<int, string>  $parts
     */
    private function joinFaxParts(array $parts): string
    {
        if (! $parts) {
            throw new \RuntimeException('팩스로 보낼 서류가 하나도 없습니다.');
        }

        return count($parts) === 1 ? $parts[0] : $this->mergePdfBytes($parts);
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

        /* 처방전 본 그림 — PDF 로 올라온 것도 담는다 (2026-09-12 지시).

           여태는 PDF 를 그대로 data URI 로 만들어 <img> 에 넣었다. 그림이 아니니
           통합본에 빈 자리가 남았다. 쪽마다 펴서 보정을 입힌 그림으로 넣는다. */
        $rxImageDataUri = null;
        $rx딸림쪽       = [];

        if (in_array('prescription', $documents) && $prescription->image_path) {
            $absPath = Storage::disk('public')->path($prescription->image_path);
            $밝기 = (int) ($prescription->img_brightness ?? 0);
            $명암 = (int) ($prescription->img_contrast ?? 0);

            if (file_exists($absPath) && @getimagesize($absPath) !== false) {
                $rxImageDataUri = $this->rxImageToPortraitDataUri($absPath, $밝기, $명암);
            } elseif (file_exists($absPath)) {
                $쪽들 = self::pdfPageImages($absPath, 'rx_' . $prescription->rx_number);

                foreach ($쪽들 as $번 => $쪽) {
                    $uri = $this->rxImageToPortraitDataUri($쪽, $밝기, $명암);
                    @unlink($쪽);

                    if ($번 === 0) {
                        $rxImageDataUri = $uri;
                    } else {
                        // 둘째 쪽부터는 첨부 자리에 이어 붙인다 — 보기 차례는 그대로다
                        $rx딸림쪽[] = ['label' => '처방전 (' . ($번 + 1) . '쪽)', 'dataUri' => $uri, 'type' => 'image'];
                    }
                }
            }
        }

        // 선택된 첨부파일을 base64 data URI로 변환
        $attachmentDataUris = $rx딸림쪽;
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
                    $dataUri = $this->rxImageToPortraitDataUri(
                        $absPath,
                        (int) ($att->img_brightness ?? 0),
                        (int) ($att->img_contrast ?? 0),
                    );
                    $attachmentDataUris[] = [
                        'label'   => $att->doc_type_label,
                        'dataUri' => $dataUri,
                        'type'    => 'image',
                    ];
                    continue;
                }

                /* PDF 첨부도 담는다 (2026-09-12 지시).

                   dompdf 는 남의 PDF 를 끼우지 못한다. 그래서 여태 통째로 빠졌다 —
                   팩스로는 나가는데 「무엇을 보냈나」를 남기는 통합본에는 없었다.
                   쪽마다 그림으로 펴서, 보정을 입힌 그림으로 넣는다. */
                $쪽들 = self::pdfPageImages($absPath, 'att_' . $att->id);
                $여러쪽 = count($쪽들) > 1;

                foreach ($쪽들 as $번 => $쪽) {
                    $attachmentDataUris[] = [
                        'label'   => $att->doc_type_label . ($여러쪽 ? ' (' . ($번 + 1) . '쪽)' : ''),
                        'dataUri' => $this->rxImageToPortraitDataUri(
                            $쪽,
                            (int) ($att->img_brightness ?? 0),
                            (int) ($att->img_contrast ?? 0),
                        ),
                        'type'    => 'image',
                    ];
                    @unlink($쪽);
                }
            }
        }

        /* 세금계산서ㆍ현금영수증은 서식 조각을 그대로 한 장씩 끼운다. 내려받는 PDF 와
           같은 조각이라 팩스와 종이가 같은 것을 보여 준다. */
        $taxInvoiceForm = null;
        if (in_array('tax_invoice', $documents) && $prescription->order?->tax_invoice_status === 'issued') {
            $taxInvoiceForm = \App\Support\TaxInvoiceForm::data($prescription->order);
        }

        $cashReceiptForm = null;
        if (in_array('cash_receipt', $documents) && $prescription->order?->cash_receipt_status === 'issued') {
            $cashReceiptForm = \App\Support\CashReceiptForm::data($prescription->order);
        }

        $parts = [];

        if ($this->faxBodyHasAnything($documents, $prescription, $rxImageDataUri,
                                      $attachmentDataUris, $taxInvoiceForm, $cashReceiptForm)) {
            $html = view('prescriptions.fax-pdf', [
                'prescription'       => $prescription,
                'patient'            => $prescription->patient,
                'consent'            => $consent,
                'order'              => $prescription->order,
                'docs'               => $documents,
                'rxImageDataUri'     => $rxImageDataUri,
                'attachmentDataUris' => $attachmentDataUris,
                'taxInvoiceForm'     => $taxInvoiceForm,
                'cashReceiptForm'    => $cashReceiptForm,
            ])->render();

            $dompdf = $this->makeFaxDompdf();
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $parts[] = $dompdf->output();
        }

        // 요양비위임장(별지 제19호의7 원본 오버레이) 병합
        if (in_array('delegation', $documents)) {
            $delegBytes = app(\App\Http\Controllers\ConsentController::class)->overlayPdfBytes($prescription);
            if ($delegBytes) {
                $parts[] = $delegBytes;
            }
        }

        $pdfOutput = $this->joinFaxParts($parts);

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

    /**
     * 팩스ㆍ서류에 넣을 그림 한 장.
     *
     * 가로로 찍힌 것은 세로로 돌리고, 화면에서 맞춰 둔 밝기ㆍ명암을 여기서 입힌다.
     * 파일은 건드리지 않는다 — 원본은 그대로 두고 나가는 그림에만 입힌다(2026-09-09).
     *
     * 화면은 CSS filter 로, 여기서는 GD 로 같은 값을 쓴다. 두 셈법이 조금 다르므로
     * 눈에 같아 보이도록 맞춰 옮긴다(아래 imageTune).
     */
    private function rxImageToPortraitDataUri(string $absPath, int $bright = 0, int $contrast = 0): string
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
            $src = $rotated;
        } elseif ($bright === 0 && $contrast === 0) {
            /* 돌릴 것도 입힐 것도 없으면 원본 바이트를 그대로 보낸다 —
               GD 로 한 번 굽는 것만으로도 글자가 무뎌진다. */
            imagedestroy($src);
            $mime = mime_content_type($absPath) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        self::imageTune($src, $bright, $contrast);

        ob_start();
        imagejpeg($src, null, 92);
        imagedestroy($src);
        $jpeg = ob_get_clean();

        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    /**
     * 화면에서 맞춘 값을 GD 로 옮긴다. 둘 다 -100 ~ 100 이고 0 이 원본이다.
     *
     * 밝기 — CSS 는 곱셈(brightness(1.2)), GD 는 덧셈(-255~255)이다. 100 을 255 로 편다.
     * 명암 — GD 의 IMG_FILTER_CONTRAST 는 **부호가 거꾸로**다. 음수가 대비를 키운다.
     */
    private static function imageTune(\GdImage $im, int $bright, int $contrast): void
    {
        $bright   = max(-100, min(100, $bright));
        $contrast = max(-100, min(100, $contrast));

        if ($bright !== 0) {
            imagefilter($im, IMG_FILTER_BRIGHTNESS, (int) round($bright * 2.55));
        }
        if ($contrast !== 0) {
            imagefilter($im, IMG_FILTER_CONTRAST, -$contrast);
        }
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
                            foreach (self::faxFilesWithTune(
                                $absPath,
                                (int) ($prescription->img_brightness ?? 0),
                                (int) ($prescription->img_contrast ?? 0),
                                'rx_' . $prescription->rx_number,
                            ) as $한장) {
                                $files[] = $한장;
                            }
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

                case 'delegation':
                    /* 요양비위임장 — 서명하면 시스템이 만든다(생성 서류). 첨부가 아니라
                       그쪽에 담기므로 첨부 목록에서는 찾을 수 없었다. 그래서 공단에
                       보낼 넷 가운데 이것만 늘 「없다」로 읽혀, 자동 팩스가 한 번도
                       나가지 못했다(2026-09-03 시험 2차 Case 4.1 에서 드러났다). */
                    $deleg = \App\Models\PrescriptionDocument::where('prescription_id', $prescription->id)
                        ->where('type', 'delegation')
                        ->latest('id')
                        ->first();

                    if ($deleg?->file_path) {
                        foreach (['public', 'local'] as $disk) {
                            if (Storage::disk($disk)->exists($deleg->file_path)) {
                                $files[] = Storage::disk($disk)->path($deleg->file_path);
                                break;
                            }
                        }
                    }
                    break;

                case 'guardian_id':
                    /* 법정대리인 신분증 — 미성년자가 서명할 때 보호자가 올린 것이다.
                       첨부가 아니라 동의 기록에 딸려 들어가(consents/guardian-id/…)
                       첨부 목록에서는 찾을 수 없다. 공단은 미성년 건에 보호자 신분증을
                       요구하므로 여기서 따로 꺼내 붙인다(2026-09-04 확정). */
                    $gc = PrescriptionConsent::where('prescription_id', $prescription->id)
                        ->whereNotNull('guardian_id_path')
                        ->latest('id')
                        ->first();

                    if ($gc?->guardian_id_path) {
                        foreach (['public', 'local'] as $disk) {
                            if (Storage::disk($disk)->exists($gc->guardian_id_path)) {
                                $files[] = Storage::disk($disk)->path($gc->guardian_id_path);
                                break;
                            }
                        }
                    }
                    break;

                case 'cash_receipt':
                    // 발행된 건만. 서식 그대로 PDF 로 그려 붙인다 — 팝빌 팩스는 PDF 를 받는다.
                    $order = $prescription->order;
                    if ($order?->cash_receipt_status === 'issued') {
                        if (!is_dir(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }
                        $tmpPath = storage_path('app/temp/cashreceipt_' . $prescription->rx_number . '_' . time() . '.pdf');
                        file_put_contents($tmpPath, \App\Support\CashReceiptForm::render($order));
                        $files[] = $tmpPath;
                    }
                    break;

                case 'tax_invoice':
                    // 발행된 건만. 서식 그대로 PDF 로 그려 붙인다 — 팝빌 팩스는 PDF 를 받는다.
                    $order = $prescription->order;
                    if ($order?->tax_invoice_status === 'issued') {
                        if (!is_dir(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }
                        $tmpPath = storage_path('app/temp/taxinvoice_' . $prescription->rx_number . '_' . time() . '.pdf');
                        file_put_contents($tmpPath, \App\Support\TaxInvoiceForm::render($order));
                        $files[] = $tmpPath;
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
                    foreach (self::faxFilesWithTune(
                        $absPath,
                        (int) ($att->img_brightness ?? 0),
                        (int) ($att->img_contrast ?? 0),
                        'att_' . $att->id,
                    ) as $한장) {
                        $files[] = $한장;
                    }
                }
            }
        }

        return array_values(array_filter($files));
    }

    /**
     * PDF 를 쪽마다 그림 한 장으로 편다 (2026-09-12 지시).
     *
     * 밝기ㆍ명암은 GD 로 입히는데 GD 는 PDF 를 열지 못한다. 그래서 여태 PDF 로
     * 올라온 처방전은 화면에서 아무리 맞춰도 **원본이 그대로 팩스로 나갔다**.
     * 공단은 팩스로 받아 읽는 쪽이라, 흐린 채로 가면 확인이 어렵다.
     *
     * 펴 놓고 나면 그 뒤는 그림과 똑같다 — 보정한 그림으로 PDF 를 만든다.
     * 순서가 거꾸로면(만들고 나서 손대면) 이미 구워진 쪽을 다시 굽는 셈이 된다.
     *
     * 펴지 못하면 빈 배열을 돌려준다 — 부르는 쪽이 원본을 그대로 쓴다.
     */
    private static function pdfPageImages(string $absPath, string $이름): array
    {
        $펴개 = '/usr/bin/pdftoppm';
        if (! is_file($펴개)) {
            return [];
        }

        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $앞 = $dir . '/pdfpg_' . preg_replace('/[^A-Za-z0-9_-]/', '', $이름) . '_' . time() . '_' . mt_rand(1000, 9999);

        /* 200dpi — 팩스가 실제로 싣는 해상도(약 200×100)보다 넉넉하다.
           더 올리면 파일만 커지고 팩스에서는 달라지지 않는다. */
        $cmd = escapeshellcmd($펴개) . ' -jpeg -r 200 -jpegopt quality=92 '
             . escapeshellarg($absPath) . ' ' . escapeshellarg($앞) . ' 2>&1';

        @exec($cmd, $나온말, $끝값);

        if ($끝값 !== 0) {
            Log::warning('[팩스] PDF 를 펴지 못했습니다', ['파일' => $absPath, '말' => implode(' ', $나온말)]);

            return [];
        }

        $쪽들 = glob($앞 . '-*.jpg') ?: [];
        sort($쪽들, SORT_NATURAL);

        return $쪽들;
    }

    /**
     * 팩스로 나갈 파일 한 장 — 맞춰 둔 밝기ㆍ명암을 입혀 굽는다 (2026-09-09 지시).
     *
     * 팝빌은 **파일**을 받는다. 그래서 화면에서 아무리 맞춰 두어도 여기서 원본 경로를
     * 그대로 붙이면 원본이 그대로 나간다 — 실제로 그랬다. 통합본 PDF 에만 값이 들어가
     * 있었고, 그것은 「무엇을 보냈나」를 남기는 기록일 뿐 나가는 물건이 아니었다.
     *
     * 원본 파일은 건드리지 않는다. 임시로 한 장 구워 그 경로를 준다 — 위임장ㆍ구매내역ㆍ
     * 현금영수증ㆍ세금계산서가 이미 같은 길로 나간다.
     *
     * **돌리지는 않는다.** 통합본은 가로로 찍힌 것을 세로로 돌리지만, 팩스로 나가는
     * 파일은 여태 돌리지 않았다. 밝기만 고치러 왔다가 방향까지 바꿔 놓지 않는다.
     *
     * 맞출 것이 없거나(0ㆍ0) GD 가 열지 못하는 것(PDF)은 원본 경로를 그대로 돌려준다.
     */
    /**
     * 팩스로 나갈 파일들 — PDF 면 쪽마다 한 장이 된다 (2026-09-12 지시).
     *
     * 맞출 것이 없으면(0ㆍ0) 원본 하나를 그대로 준다. PDF 라도 그렇다 — 펴서 다시
     * 굽는 것만으로도 글자가 무뎌지고, 쪽이 여럿이면 팩스 장수만 늘어난다.
     */
    private static function faxFilesWithTune(string $absPath, int $bright, int $contrast, string $이름): array
    {
        if ($bright === 0 && $contrast === 0) {
            return [$absPath];
        }

        /* GD 가 여는 것(그림)은 여태 하던 대로 한 장 */
        if (@getimagesize($absPath) !== false) {
            return [self::faxFileWithTune($absPath, $bright, $contrast, $이름)];
        }

        $쪽들 = self::pdfPageImages($absPath, $이름);
        if (! $쪽들) {
            return [$absPath];       // 펴지 못했으면 원본 그대로 — 안 보내는 것보다 낫다
        }

        $구운것 = [];
        foreach ($쪽들 as $번 => $쪽) {
            $구운것[] = self::faxFileWithTune($쪽, $bright, $contrast, $이름 . '_p' . ($번 + 1));
            @unlink($쪽);            // 편 것은 굽고 나면 쓸 데가 없다
        }

        return $구운것;
    }

    private static function faxFileWithTune(string $absPath, int $bright, int $contrast, string $이름): string
    {
        if ($bright === 0 && $contrast === 0) {
            return $absPath;
        }

        $src = @imagecreatefromstring((string) file_get_contents($absPath));
        if (! $src) {
            return $absPath;   // PDF 등 — GD 가 열지 못한다
        }

        self::imageTune($src, $bright, $contrast);

        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $dir . '/fax_' . preg_replace('/[^A-Za-z0-9_-]/', '', $이름) . '_' . time() . '.jpg';
        imagejpeg($src, $tmp, 92);
        imagedestroy($src);

        return is_file($tmp) ? $tmp : $absPath;
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

    /**
     * 마지막으로 적어 둔 건을 그대로 베껴 새 건을 세운다.
     *
     * 같은 사람이 같은 것을 다시 사는 일이 잦다. 그때마다 병원ㆍ상병ㆍ제품ㆍ수량을 다시
     * 적는 것은 옮겨 적는 일일 뿐이고, 옮기다 어긋나면 지난번과 다른 주문이 된다.
     *
     * 날짜만 비운다. 처방전 발행일ㆍ진단 확인일ㆍ결제일 같은 것은 그 건에만 속한 사실이라,
     * 베껴 오면 지난달 날짜로 이번 달 주문을 내는 셈이 된다. 나머지는 그대로 온다.
     *
     * 처방전 그림과 첨부 서류도 베끼지 않는다 — 그 종이는 그 건의 것이다.
     */
    /**
     * 이 건을 그대로 베껴 새 번호로 세운다.
     *
     * **같은 처방전으로 제품을 더 사는 자리**다(2026-09-09 지시). 그래서 거래처 정보도
     * 처방전 정보도 **날짜까지 그대로** 이어 간다 — 같은 처방전이니 발행일ㆍ진단
     * 확인일ㆍ요류역학검사일이 달라질 까닭이 없다. 예전에는 날짜를 모두 비웠는데,
     * 그러면 스무 칸 남짓을 다시 적어야 했다.
     *
     * **올려 둔 파일도 함께 이어 간다.** 다만 **복사하지 않고 잇는다** — 새 줄을
     * 만들되 file_path 는 같은 곳을 가리킨다. 파일은 한 벌이고 두 건이 함께 쓴다.
     * 지울 때 원본이 사라지지 않게 destroyAttachment 가 함께 쓰는지 보고 지운다.
     *
     * 새 건이 다시 받아야 하는 것만 두고 온다 — 검수 자취ㆍ보낸 때ㆍ상담 번호처럼
     * 그 건에만 속한 자국이다.
     */
    /**
     * 저장 이력 — 언제ㆍ누가ㆍ무엇을 저장했는가, 그리고 그 저장이 무엇을 바꿨는가.
     *
     * 이 처방전과 딸린 주문ㆍ거래처의 변경을 한 표로 모은다. 담당자가 값을 의심할 때
     * 「누가 언제 그렇게 만들었는가」를 그 자리에서 본다.
     *
     * **저장 한 번이 한 줄이다.** 한 번 저장에 칸 여덟이 바뀌면 목록에는 한 줄로 서고,
     * 그 여덟은 줄에 매달아 보낸다 — 상세 보기가 저장 전(왼쪽)ㆍ저장 후(오른쪽)로
     * 나란히 세운다. 예전에는 칸마다 한 줄이라, 한 번 저장이 여덟 줄로 흩어져
     * 「이 저장이 무엇을 했는가」가 보이지 않았다.
     *
     * 무엇이 바뀌었는지 남지 않은 지난 줄(모델에 이력을 켜기 전의 것)도 제 줄로 선다.
     * 매달린 칸이 없을 뿐이다 — 없는 것을 지어내지 않는다.
     */
    public function history(Prescription $prescription): JsonResponse
    {
        /* 이 건과 한 몸인 것들을 함께 본다 — 주문과 거래처를 따로 열어 견주게 하지
           않는다. 세는 일은 App\Support\SaveHistory 가 한다(거래처 관리도 같은 것을
           쓴다 — 컨트롤러 안에 두었더니 두 벌이 될 참이었다). */
        $대상 = [[Prescription::class, $prescription->id]];

        /* 보고 있는 주문의 이력을 본다 (2026-09-16 고침).

           이 주소도 show() 를 거치지 않아 $prescription->order 가 늘 첫 주문이었다.
           그래서 추가 주문을 열어 두고 이력 탭을 보면 원 주문의 이력이 나왔다. */
        $이력주문 = ($번호 = trim((string) request('order')))
            ? $prescription->orders()->where('order_number', $번호)->first()
            : null;

        if ($이력주문 ??= $prescription->order) {
            $대상[] = [\App\Models\Order::class, $이력주문->id];
        }
        if ($prescription->patient_id) {
            $대상[] = [\App\Models\Patient::class, $prescription->patient_id];
        }

        return response()->json([
            'success' => true,
            'rows'    => \App\Support\SaveHistory::rows($대상),
        ]);
    }

    public function duplicate(Request $request, Prescription $prescription): JsonResponse
    {
        $request->validate(['patient_id' => 'nullable|integer|exists:patients,id']);

        /* 그 건에만 속한 자국 — 베끼면 안 되는 자리.

           날짜는 이제 대부분 따라간다. 남기지 않는 것은 **그 건이 겪은 일의 때**다:
           검수한 때ㆍ문자를 보낸 때ㆍ상담한 날. 새 건은 그 일을 아직 겪지 않았다. */
        $skip = [
            'id', 'rx_number', 'status', 'is_blank_draft',
            'reviewed_by', 'review_memo', 'reviewed_at',
            'kakao_sent_at', 'sms_sent_at',
            'counsel_no', 'counsel_order_id', 'counsel_date', 'counsel_re_date',
            'created_by', 'updated_by',
            'created_at', 'updated_at', 'deleted_at',
            'registration_no', 'serial_no',
        ];

        $attrs = collect($prescription->getAttributes())
            ->except($skip)
            ->filter(fn ($v) => $v !== null)
            ->all();

        $copy = Prescription::create(array_merge($attrs, [
            'rx_number'     => Prescription::generateRxNumber(),
            'status'        => 'pending',
            'upload_source' => 'web',
            'created_by'    => Auth::id(),
            'updated_by'    => Auth::id(),
            /* 「조회」에서 고른 사람으로 이어 달라고 하면 그 사람에게 붙인다.
               같은 사람이 다시 사는 것이면 원본과 같고, 다른 사람이면 그쪽으로 간다. */
            'patient_id'    => $request->input('patient_id') ?: $prescription->patient_id,
        ]));

        // 제품 줄도 함께 베낀다 — 같은 것을 다시 사는 것이 이 단추의 뜻이다
        foreach ($prescription->items as $item) {
            $copy->items()->create(
                collect($item->getAttributes())->except(['id', 'prescription_id', 'created_at', 'updated_at'])->all()
            );
        }

        /* 올려 둔 파일을 **잇는다** — file_path 는 그대로 두고 줄만 새로 만든다.
           복사하면 같은 그림이 디스크에 두 벌 쌓이고, 결과지가 열아홉 장인 건이면
           그만큼 늘어난다. */
        $이은파일 = 0;
        foreach ($prescription->attachments()->orderBy('display_order')->orderBy('id')->get() as $att) {
            PrescriptionAttachment::create(
                collect($att->getAttributes())
                    ->except(['id', 'created_at', 'updated_at'])
                    ->merge([
                        'prescription_id' => $copy->id,
                        'uploaded_by'     => Auth::id(),
                    ])
                    ->all()
            );
            $이은파일++;
        }

        activity()->causedBy(Auth::user())->performedOn($copy)
            ->log("{$prescription->rx_number} 를 복제하여 {$copy->rx_number} 생성 — 첨부파일 {$이은파일}건 공유");

        return response()->json([
            'success'   => true,
            'message'   => "{$copy->rx_number} 로 복제했습니다"
                           . ($이은파일 ? " — 첨부파일 {$이은파일}건을 공유합니다." : '.'),
            'rx_number' => $copy->rx_number,
            'url'       => route('prescriptions.show', $copy, absolute: false),
        ]);
    }

    /**
     * 위드웍스로 넘길 청구전략 — 저쪽 billing_strategies 표의 id 다.
     *
     * 코드값이 아니라 줄 번호라 서버마다 다르다. 그래서 지금 붙어 있는 곳(test·production)의
     * 표에서만 찾는다 — 데모웍스 id 를 운영으로 보내면 엉뚱한 줄을 가리킨다.
     *
     * 못 찾으면 null 을 돌려준다. 아무 값이나 실어 보내느니 싣지 않는 편이 낫다 —
     * 저쪽은 값이 없으면 제 기본값(전자세금계산서 100%)으로 갈아 끼우고, 그것이
     * 우리가 25 를 보내던 시절에 실제로 일어나던 일이다.
     */
    /* 청구전략을 위드웍스 코드로 옮기는 일은 WithworksLink 로 옮겼다 (2026-09-16).
       창고로 보낼 내용을 만드는 곳이 한 곳이라, 그 셈도 그 옆에 있어야 한다. */

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
    /* 몸통은 App\Support\PatientLink 로 옮겼다 — 위임동의 서명도 같은 규칙으로
       환자를 잇는다. 두 벌로 두면 한쪽만 고쳐져 서로 다르게 이어진다. */
    private function linkOrCreatePatient(Prescription $prescription, array $d): void
    {
        \App\Support\PatientLink::attach($prescription, $d);
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
