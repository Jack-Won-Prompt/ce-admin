<?php
// app/Http/Controllers/PrescriptionController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\User;
use App\Models\PrescriptionConsent;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\KakaoService;
use App\Services\Popbill\FaxService as PopbillFaxService;
use App\Services\Popbill\MessageService as PopbillMessageService;
use App\Services\OcrService;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly OcrService $ocrService,
        private readonly VirtualAccountService $vaService,
        private readonly KakaoService $kakaoService,
        private readonly PopbillMessageService $smsService,
    ) {}

    // ── 처방전 목록 ───────────────────────────────────────
    public function index(Request $request): View
    {
        $query = Prescription::with(['patient', 'assignedUser', 'creator', 'order'])->latest();

        if ($request->input('status') === 'no_order') {
            $query->whereIn('status', ['approved', 'ocr_done'])
                  ->whereDoesntHave('order');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
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

        $perPage = in_array((int) $request->input('per_page', 10), [10, 20, 50, 100])
            ? (int) $request->input('per_page', 10)
            : 10;
        $prescriptions = $query->paginate($perPage)->withQueryString();

        $statusCounts = [
            'all'            => Prescription::count(),
            'review_needed'  => Prescription::where('status', 'review_needed')->count(),
            'ocr_processing' => Prescription::whereIn('status', ['pending', 'ocr_processing'])->count(),
            'approved'       => Prescription::where('status', 'approved')->count(),
            'no_order'       => Prescription::whereIn('status', ['approved', 'ocr_done'])->whereDoesntHave('order')->count(),
            'ordered'        => Prescription::where('status', 'ordered')->count(),
            'rejected'       => Prescription::where('status', 'rejected')->count(),
        ];

        $managers = User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get();

        return view('prescriptions.list', compact('prescriptions', 'statusCounts', 'managers'));
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
            'so_type'          => 'nullable|string|in:1013,1016,1022',
        ]);

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');
        $token   = config('services.todoworks.token');

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
            'ho_account_id'           => $request->ho_account_id ?? null,
            'remark'                  => $prescription->admin_note,
            'items'                   => $request->items,
            // 판매 유형
            'so_type'                 => $request->so_type ?? $prescription->order?->so_type ?? null,
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
                $soNo = $body['result']['so_no'] ?? null;

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

                $result      = $body['result'] ?? [];
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
            'so_type'          => 'nullable|string|in:1013,1016,1022',
        ]);

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');
        $token   = config('services.todoworks.token');

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
            'so_type'                 => $request->so_type ?? $prescription->order?->so_type ?? null,
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

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');
        $token   = config('services.todoworks.token');

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
        $patientsJson  = \App\Models\Patient::orderBy('name')->get(['id', 'name', 'mobile', 'resident_no'])
            ->map(fn ($p) => [
                'id'     => $p->id,
                'name'   => $p->name,
                'mobile' => $p->mobile ? preg_replace('/(\d{3})(\d{3,4})(\d{4})/', '$1-$2-$3', $p->mobile) : '',
                'rn'     => $p->resident_no ? substr($p->resident_no, 0, 6) . '-*' : '',
            ])->values();

        $mobilePending = Prescription::where('upload_source', 'mobile')
            ->whereIn('status', ['pending', 'ocr_processing', 'ocr_done', 'review_needed'])
            ->latest()->take(5)->get();

        return view('prescriptions.upload', compact('prescriptions', 'managers', 'mobilePending', 'patientsJson'));
    }

    // ── 웹에서 직접 업로드 ────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'prescription_images'   => 'required|array|max:10',
            'prescription_images.*' => 'file|mimes:jpg,jpeg,png,pdf,heic|max:50240',
            'file_doc_types'        => 'nullable|array',
            'file_doc_types.*'      => 'nullable|string|in:prescription,id_card,delegation,other',
            'patient_id'            => 'nullable|exists:patients,id',
            'assigned_user_id'      => 'nullable|exists:users,id',
            'admin_note'            => 'nullable|string|max:500',
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
        $ocrErrors       = [];
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
                'status'              => 'ocr_processing',
            ]);

            if (!$firstPrescription) {
                $firstPrescription = $prescription;
            }

            // OCR 처리
            try {
                $ocrResult = $this->ocrService->extractFromImage($path);
                $d         = $ocrResult['data'];

                $prescription->update([
                    'status'           => $ocrResult['confidence'] >= 85 ? 'ocr_done' : 'review_needed',
                    'ocr_confidence'   => $ocrResult['confidence'],
                    'ocr_raw_data'     => ['raw_text' => $ocrResult['raw_text']],
                    'patient_name_ocr' => $d['patient_name']  ?? $d['patient_name_ocr'] ?? null,
                    'resident_no_ocr'  => $d['resident_no']   ?? null,
                    'mobile_ocr'       => $d['mobile'] ?? $d['phone'] ?? null,
                    'address_ocr'      => $d['address'] ?? null,
                    'registration_no'  => $d['registration_no'] ?? null,
                    'serial_no'        => $d['serial_no']       ?? null,
                    'is_reissue'       => $d['is_reissue']      ?? false,
                    'hospital_name'    => $d['hospital_name']   ?? null,
                    'hospital_code'    => $d['hospital_code']   ?? null,
                    'doctor_name'      => $d['doctor_name']     ?? null,
                    'specialty'        => $d['specialty']       ?? null,
                    'license_no'       => $d['license_no']      ?? null,
                    'specialist_no'    => $d['specialist_no']   ?? null,
                    'department'       => $d['department']  ?? null,
                    'disease_name'     => $d['disease_name'] ?? null,
                    'disease_code'     => $d['disease_code'] ?? null,
                    'daily_count'      => isset($d['daily_count'])  ? (int)$d['daily_count']  : null,
                    'total_days'       => isset($d['total_days'])   ? (int)$d['total_days']   : null,
                    'total_count'      => isset($d['total_count'])  ? (int)$d['total_count']  : null,
                    'usage_period'     => $d['usage_period'] ?? null,
                    'issued_date'      => $d['issued_date']  ?? null,
                ]);

                // 명시적 환자 선택이 없으면 OCR 기반 자동 연결
                if (!$request->filled('patient_id')) {
                    $this->linkOrCreatePatient($prescription, $d);
                }

            } catch (\Exception $e) {
                $prescription->update(['status' => 'review_needed']);
                $ocrErrors[] = "{$prescription->rx_number} OCR 실패: " . $e->getMessage();
            }

            $prescription->update([
                'counseling_data' => array_merge(
                    $prescription->counseling_data ?? [],
                    ['counselling_no' => Prescription::generateCounselNo(), 'counsel_date' => now()->format('Y-m-d')]
                ),
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
                    'doc_label'          => PrescriptionAttachment::DOC_TYPE_LABELS[$item['doc_type']] ?? '기타',
                    'ocr_raw_text'       => null,
                    'ocr_confidence'     => 0,
                    'display_order'      => $order,
                    'uploaded_by'        => Auth::id(),
                ]);
            }
        }

        if (!empty($ocrErrors)) {
            session()->flash('error', implode(' | ', $ocrErrors));
        }

        if (count($created) === 1) {
            return redirect()->route('prescriptions.show', $firstPrescription)
                ->with('success', "{$firstPrescription->rx_number} 업로드 완료 — OCR 결과를 확인하세요.");
        }

        return redirect()->route('prescriptions.index')
            ->with('success', count($created) . '개 처방전 업로드 완료: ' . implode(', ', $created));
    }

    // ── 첨부 파일 삭제 ────────────────────────────────────
    public function storeAttachment(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file'      => 'required|file|mimes:jpg,jpeg,png,pdf,heic|max:51200',
            'doc_type'  => 'required|string|in:id_card,delegation,other,prescription',
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
                                        : (PrescriptionAttachment::DOC_TYPE_LABELS[$request->doc_type] ?? '기타'),
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

    // ── OCR 미리보기 (임시 저장 + OCR, DB 저장 없음) ────────
    public function analyze(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'prescription_image' => 'required|file|mimes:jpg,jpeg,png,pdf,heic|max:51200',
        ]);

        $file     = $request->file('prescription_image');
        $subDir   = 'prescriptions/temp';
        $fileName = 'tmp_' . Auth::id() . '_' . now()->format('Ymd_His') . '_' . uniqid()
                    . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs($subDir, $fileName, 'public');

        try {
            $ocrResult = $this->ocrService->extractFromImage($path);
            $d         = $ocrResult['data'];

            return response()->json([
                'success'    => true,
                'temp_path'  => $path,
                'image_url'  => Storage::disk('public')->url($path),
                'confidence' => $ocrResult['confidence'],
                'raw_text'   => $ocrResult['raw_text'] ?? null,
                'fields'     => [
                    'patient_name'  => $d['patient_name']  ?? null,
                    'resident_no'   => $d['resident_no']   ?? null,
                    'mobile'        => $d['mobile'] ?? $d['phone'] ?? null,
                    'address'       => $d['address']       ?? null,
                    'hospital_name' => $d['hospital_name'] ?? null,
                    'hospital_code' => $d['hospital_code'] ?? null,
                    'doctor_name'   => $d['doctor_name']   ?? null,
                    'specialty'     => $d['specialty']     ?? null,
                    'issued_date'   => $d['issued_date']   ?? null,
                    'disease_name'  => $d['disease_name']  ?? null,
                    'disease_code'  => $d['disease_code']  ?? null,
                    'daily_count'   => $d['daily_count']   ?? null,
                    'total_days'    => $d['total_days']    ?? null,
                    'total_count'   => $d['total_count']   ?? null,
                    'product_name'  => $d['product_name']  ?? null,
                    'usage_period'  => $d['usage_period']  ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Storage::disk('public')->delete($path);
            Log::error('OCR 미리보기 실패', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'OCR 처리 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── OCR 확인 후 최종 업로드 (temp_path 기반) ────────────
    public function confirmUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'temp_path'        => 'required|string|max:300',
            'orig_name'        => 'nullable|string|max:300',
            'assigned_user_id' => 'nullable|exists:users,id',
            'admin_note'       => 'nullable|string|max:500',
            // OCR 편집 필드
            'patient_name'     => 'nullable|string|max:100',
            'resident_no'      => 'nullable|string|max:20',
            'mobile'           => 'nullable|string|max:30',
            'address'          => 'nullable|string|max:300',
            'hospital_name'    => 'nullable|string|max:100',
            'doctor_name'      => 'nullable|string|max:50',
            'specialty'        => 'nullable|string|max:100',
            'issued_date'      => 'nullable|date',
            'disease_name'     => 'nullable|string|max:500',
            'disease_code'     => 'nullable|string|max:200',
            'daily_count'      => 'nullable|integer|min:1',
            'total_days'       => 'nullable|integer|min:1',
            'total_count'      => 'nullable|integer|min:1',
            'product_name'     => 'nullable|string|max:200',
            'usage_period'     => 'nullable|string|max:200',
            'confidence'       => 'nullable|integer',
        ]);

        // 임시 파일 → 영구 경로 이동
        $tempPath = $request->input('temp_path');
        if (!Storage::disk('public')->exists($tempPath)) {
            return back()->with('error', '임시 파일을 찾을 수 없습니다. 다시 업로드해주세요.');
        }

        $ext     = pathinfo($tempPath, PATHINFO_EXTENSION);
        $subDir  = 'prescriptions/' . now()->format('Y/m');
        $newName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $newPath = $subDir . '/' . $newName;
        Storage::disk('public')->move($tempPath, $newPath);

        $origName = $request->input('orig_name') ?: basename($newPath);
        $mimeType = Storage::disk('public')->mimeType($newPath);
        $fileSize = Storage::disk('public')->size($newPath);

        $prescription = Prescription::create([
            'rx_number'           => Prescription::generateRxNumber(),
            'assigned_user_id'    => $request->assigned_user_id ?: null,
            'created_by'          => Auth::id(),
            'admin_note'          => $request->admin_note,
            'image_path'          => $newPath,
            'image_original_name' => $origName,
            'image_mime_type'     => $mimeType,
            'image_size'          => $fileSize,
            'upload_source'       => 'web',
            'status'              => ($request->integer('confidence', 0) >= 85) ? 'ocr_done' : 'review_needed',
            'ocr_confidence'      => $request->integer('confidence', 0) ?: null,
            // OCR 필드
            'patient_name_ocr'    => $request->patient_name,
            'resident_no_ocr'     => $request->resident_no,
            'mobile_ocr'          => $request->mobile,
            'address_ocr'         => $request->address,
            'hospital_name'       => $request->hospital_name,
            'doctor_name'         => $request->doctor_name,
            'specialty'           => $request->specialty,
            'issued_date'         => $request->issued_date ?: null,
            'disease_name'        => $request->disease_name,
            'disease_code'        => $request->disease_code,
            'daily_count'         => $request->daily_count ? (int)$request->daily_count : null,
            'total_days'          => $request->total_days  ? (int)$request->total_days  : null,
            'total_count'         => $request->total_count ? (int)$request->total_count : null,
            'usage_period'        => $request->usage_period,
        ]);

        $this->linkOrCreatePatient($prescription, $request->all());

        // 상담번호 자동 채번 (항상 생성)
        $prescription->update([
            'counseling_data' => ['counselling_no' => Prescription::generateCounselNo(), 'counsel_date' => now()->format('Y-m-d')],
        ]);

        activity()->causedBy(Auth::user())->performedOn($prescription)
                  ->log("{$prescription->rx_number} 업로드 완료 (웹·미리보기 확인)");

        return redirect()->route('prescriptions.show', $prescription)
                         ->with('success', "처방전 {$prescription->rx_number} 등록 완료");
    }

    // ── 주문 연계 페이지 (검수 화면) ──────────────────────
    public function show(Prescription $prescription): View
    {
        $prescription->load(['patient', 'assignedUser', 'creator', 'reviewer', 'order.tossPayment', 'items', 'memos.user', 'attachments']);
        $patients = Patient::orderBy('name')->get();

        // 이전(ID 작은 쪽) / 다음(ID 큰 쪽) — rx_number 반환
        $prevId = Prescription::where('id', '<', $prescription->id)->orderByDesc('id')->value('rx_number');
        $nextId = Prescription::where('id', '>', $prescription->id)->orderBy('id')->value('rx_number');

        // 같은 환자의 이전 상담 이력 (상담번호 있는 것만, 최대 10건)
        $prevCounselings = collect();
        if ($prescription->patient_id) {
            $prevCounselings = Prescription::where('patient_id', $prescription->patient_id)
                ->where('id', '!=', $prescription->id)
                ->whereNotNull('counseling_data')
                ->orderByDesc('id')
                ->limit(10)
                ->with(['items', 'order.tossPayment', 'consents', 'faxHistories'])
                ->get([
                    'id', 'rx_number', 'counseling_data', 'created_at', 'status',
                    'patient_name_ocr', 'resident_no_ocr', 'mobile_ocr', 'address_ocr',
                    'hospital_name', 'doctor_name', 'issued_date',
                    'postcode', 'address_detail', 'patient_id', 'repurchase_date',
                ])
                ->filter(fn($p) => !empty($p->counseling_data['counselling_no']))
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
        $prevCounselingsData = $prevCounselings->map(function ($p) {
            return array_merge($p->counseling_data ?? [], [
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
        })->values();

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
        $allDocsJson = array_merge($rxDoc, $attachmentsJson);

        return view('prescriptions.order', compact(
            'prescription', 'patients', 'prevId', 'nextId',
            'tossConfigured', 'kakaoConfigured', 'kakaoTemplates', 'smsTemplates',
            'memosData', 'prevCounselings', 'prevCounselingsData',
            'lastFaxHistory', 'attachmentsJson', 'allDocsJson'
        ));
    }

    // ── OCR 수정 저장 ─────────────────────────────────────
    public function updateOcr(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $request->validate([
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
            'counsel_type'          => 'nullable|string|max:10',
            'counsel_acc_add_type'  => 'nullable|string|max:10',
            'counsel_status'        => 'nullable|string|max:10',
            'counsel_call_no'       => 'nullable|string|max:30',
            'counsel_re_date'       => 'nullable|date',
            'counsel_memo'          => 'nullable|string|max:2000',
            // 환자 정보 추가
            'guardian'              => 'nullable|string|max:50',
            'diverticulums'         => 'nullable|string|max:10',
            // 병원·처방 추가
            'hospital_code'         => 'nullable|string|max:50',
            'rx_period'             => 'nullable|integer|min:0',
            'rx_end_date'           => 'nullable|date',
            'diagnosis_date'        => 'nullable|date',
            // 처방 수량·상병 추가
            'disease_class'         => 'nullable|string|max:10',
            'sb_sci'                => 'nullable|string|max:50',
            'uro_date'              => 'nullable|date',
            // 급여·보험 추가
            'benefit_class'         => 'nullable|string|max:20',
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

        $prescription->update($request->only([
            'patient_name_ocr', 'resident_no_ocr', 'mobile_ocr', 'address_ocr',
            'postcode', 'address_detail',
            'hospital_name', 'doctor_name',
            'department', 'disease_name', 'disease_code',
            'daily_count', 'total_days', 'total_count', 'issued_date', 'repurchase_date',
            'product_name', 'product_code', 'quantity', 'nhis_status',
            'product_price', 'insurance_price', 'patient_id', 'admin_note',
        ]));

        // ── 상담 편집 필드 저장 (counseling_data JSON에 merge) ──────────
        $counselingEditable = array_filter([
            // 상담 기본
            'counselling_no'  => $request->input('counsel_no'),
            'counsel_date'    => $request->input('counsel_date'),
            'type'            => $request->input('counsel_type'),
            'acc_add_type'    => $request->input('counsel_acc_add_type'),
            'status'          => $request->input('counsel_status'),
            'call_no'         => $request->input('counsel_call_no')
                                     ? preg_replace('/\D/', '', $request->input('counsel_call_no'))
                                     : null,
            're_counsel_date' => $request->input('counsel_re_date'),
            'contents'        => $request->input('counsel_memo'),
            // 환자 정보
            'udf24'           => $request->input('guardian'),
            'diverticulums'   => $request->input('diverticulums'),
            // 병원·처방
            'erp_cd9'         => $request->input('hospital_code'),
            'udf13'           => $request->input('rx_period') !== null ? (string)$request->input('rx_period') : null,
            'udf14'           => $request->input('rx_end_date'),
            'udf2'            => $request->input('diagnosis_date'),
            // 처방 수량·상병
            'udf3'            => $request->input('disease_class'),
            'udf6'            => $request->input('sb_sci'),
            'udf7'            => $request->input('uro_date'),
            // 급여·보험
            'udf11'           => $request->input('benefit_class'),
            'udf19'           => $request->input('nhis_reg_status'),
            'udf4'            => $request->input('nhis_renew'),
            'udf42'           => $request->input('nhis_agree_start'),
            'udf43'           => $request->input('nhis_agree_end'),
            // 거래·주문
            'udf17'           => $request->input('purchase_type'),
            'five_program'    => $request->input('five_program'),
            'udf22'           => $request->input('deduction'),
            'udf23'           => $request->input('cash_receipt_no'),
            'udf25'           => $request->input('order_manager'),
            'udf30'           => $request->input('next_repurchase'),
            'udf18'           => $request->input('special_case'),
            'udf20'           => $request->input('reason'),
            // 추가 정보
            'udf32'           => $request->input('new_patient_date'),
            'five'            => $request->input('five_110days'),
        ], fn($v) => $v !== null);

        if (!empty($counselingEditable)) {
            $existing = $prescription->counseling_data ?? [];
            $prescription->update(['counseling_data' => array_merge($existing, $counselingEditable)]);
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

    // ── OCR 재분석 ────────────────────────────────────────
    public function reanalyze(Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        if (!$prescription->image_path) {
            return response()->json(['success' => false, 'message' => '이미지가 없어 재분석할 수 없습니다.'], 422);
        }

        $prescription->update(['status' => 'ocr_processing']);

        try {
            $ocrResult = $this->ocrService->extractFromImage($prescription->image_path);
            $d         = $ocrResult['data'];

            $prescription->update([
                'status'           => $ocrResult['confidence'] >= 85 ? 'ocr_done' : 'review_needed',
                'ocr_confidence'   => $ocrResult['confidence'],
                'ocr_raw_data'     => ['raw_text' => $ocrResult['raw_text']],
                // 수진자
                'patient_name_ocr' => $d['patient_name']  ?? $d['patient_name_ocr'] ?? $prescription->patient_name_ocr,
                'resident_no_ocr'  => $d['resident_no']   ?? $prescription->resident_no_ocr,
                'mobile_ocr'       => $d['mobile'] ?? $d['phone'] ?? $prescription->mobile_ocr,
                'address_ocr'      => $d['address'] ?? $prescription->address_ocr,
                // 기관
                'registration_no'  => $d['registration_no'] ?? $prescription->registration_no,
                'serial_no'        => $d['serial_no']       ?? $prescription->serial_no,
                'is_reissue'       => $d['is_reissue']      ?? $prescription->is_reissue,
                'hospital_name'    => $d['hospital_name']   ?? $prescription->hospital_name,
                'hospital_code'    => $d['hospital_code']   ?? $prescription->hospital_code,
                'doctor_name'      => $d['doctor_name']     ?? $prescription->doctor_name,
                'specialty'        => $d['specialty']       ?? $prescription->specialty,
                'license_no'       => $d['license_no']      ?? $prescription->license_no,
                'specialist_no'    => $d['specialist_no']   ?? $prescription->specialist_no,
                // 진료
                'department'       => $d['department']   ?? $prescription->department,
                'disease_name'     => $d['disease_name'] ?? $prescription->disease_name,
                'disease_code'     => $d['disease_code'] ?? $prescription->disease_code,
                // 처방 수량
                'daily_count'      => isset($d['daily_count'])  ? (int)$d['daily_count']  : $prescription->daily_count,
                'total_days'       => isset($d['total_days'])   ? (int)$d['total_days']   : $prescription->total_days,
                'total_count'      => isset($d['total_count'])  ? (int)$d['total_count']  : $prescription->total_count,
                // 기타
                'usage_period'     => $d['usage_period'] ?? $prescription->usage_period,
                'issued_date'      => $d['issued_date']  ?? $prescription->issued_date,
            ]);

        } catch (\Exception $e) {
            $prescription->update(['status' => 'review_needed']);
            return response()->json(['success' => false, 'message' => 'OCR 재분석 실패: ' . $e->getMessage()], 500);
        }

        // 환자 자동 등록/연결 (재분석 시에도 적용)
        $this->linkOrCreatePatient($prescription, $d);

        $prescription->refresh();

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('OCR 재분석');

        return response()->json([
            'success'    => true,
            'message'    => 'OCR 재분석 완료',
            'confidence' => $prescription->display_confidence,
            'fields'     => [
                'patient_name_ocr' => $prescription->patient_name_ocr,
                'resident_no_ocr'  => $prescription->resident_no_ocr,
                'mobile_ocr'       => $prescription->mobile_ocr,
                'address_ocr'      => $prescription->address_ocr,
                'postcode'         => $prescription->postcode,
                'address_detail'   => $prescription->address_detail,
                'hospital_name'    => $prescription->hospital_name,
                'doctor_name'      => $prescription->doctor_name,
                'issued_date'      => $prescription->issued_date?->format('Y-m-d'),
                'disease_name'     => $prescription->disease_name,
                'disease_code'     => $prescription->disease_code,
                'daily_count'      => $prescription->daily_count,
                'total_days'       => $prescription->total_days,
                'total_count'      => $prescription->total_count,
            ],
        ]);
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
        $request->validate(['mobile' => 'required|string']);

        $mobile      = preg_replace('/\D/', '', $request->mobile);
        $patientName = $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '환자';
        $token       = \Illuminate\Support\Str::random(24);
        $expiresAt   = now()->addMinutes(30);

        $consent = \App\Models\PrescriptionConsent::create([
            'prescription_id' => $prescription->id,
            'token'           => $token,
            'patient_name'    => $patientName,
            'patient_mobile'  => $mobile,
            'expires_at'      => $expiresAt,
            'status'          => 'pending',
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
                ->log("위임동의 SMS 발송 → {$mobile}");

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
            'documents.*'     => 'string|in:authorization,prescription,purchase_history,cash_receipt',
            'attachment_ids'  => 'nullable|array',
            'attachment_ids.*' => 'integer|exists:prescription_attachments,id',
        ]);

        if (empty($request->documents) && empty($request->attachment_ids)) {
            return response()->json(['success' => false, 'message' => '전송할 서류를 하나 이상 선택해주세요.'], 422);
        }

        $docLabels = [
            'authorization'    => '위임장',
            'prescription'     => '처방전',
            'purchase_history' => '제품 구매내역',
            'cash_receipt'     => '현금영수증',
        ];
        $recipientLabels = [
            'nhis'   => '국민건강보험공단',
            'hira'   => '건강보험심사평가원',
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
        $sender     = config('popbill.test.sender_num') ?: config('popbill.company.tel', '');

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
        $allowed = ['authorization', 'prescription', 'purchase_history', 'cash_receipt'];
        $docs    = array_values(array_intersect(
            (array) $request->input('docs', ['authorization']),
            $allowed
        ));
        if (empty($docs)) {
            $docs = ['authorization'];
        }

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

        $mobile   = preg_replace('/[^0-9]/', '', $patient?->mobile ?? '');
        $filename = '팩스통합본_' . ($patient?->name ?? '') . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';
        $pdfOutput = $dompdf->output();

        // 스토리지에 저장 + 서류 목록 기록
        try {
            $dir      = 'fax/' . $prescription->id;
            $filePath = $dir . '/' . $filename;
            Storage::put($filePath, $pdfOutput);

            PrescriptionDocument::create([
                'prescription_id'   => $prescription->id,
                'patient_id'        => $patient?->id,
                'created_by'        => Auth::id(),
                'type'              => 'fax',
                'file_path'         => $filePath,
                'original_filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::warning('팩스 PDF 서류 저장 실패: ' . $e->getMessage());
        }

        return response($pdfOutput, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
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

        $html = view('prescriptions.fax-pdf', [
            'prescription'       => $prescription,
            'patient'            => $prescription->patient,
            'consent'            => $consent,
            'order'              => $prescription->order,
            'docs'               => $documents,
            'rxImageDataUri'     => $rxImageDataUri,
            'attachmentDataUris' => $attachmentDataUris,
        ])->render();

        $dompdf = $this->makeFaxDompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $patient  = $prescription->patient;
        $mobile   = preg_replace('/[^0-9]/', '', $patient?->mobile ?? '');
        $dir      = 'fax/' . $prescription->rx_number;
        $filename = '팩스통합본_' . ($patient?->name ?? '') . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';
        $fullPath = storage_path('app/public/' . $dir . '/' . $filename);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $dompdf->output());

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
        $options->setIsFontSubsettingEnabled(false);
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
                    if ($prescription->order?->items?->isNotEmpty()) {
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

        foreach ($order->items ?? [] as $item) {
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
    public static function smsTemplates(): array
    {
        return [
            'rx_received' => [
                'label' => '처방전 접수 완료',
                'desc'  => '처방전이 접수되었음을 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 처방전이 접수되었습니다.\n처방번호: #{처방번호}\n확인 후 연락드리겠습니다.",
            ],
            'order_confirmed' => [
                'label' => '주문 확정',
                'desc'  => '주문 확정 및 결제 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 주문이 확정되었습니다.\n주문번호: #{주문번호}\n본인 부담금: #{본인부담금}원",
            ],
            'va_guide' => [
                'label' => '가상계좌 발급 안내',
                'desc'  => '본인 부담금 및 납부 계좌 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 주문이 접수되었습니다.\n주문번호: #{주문번호}\n본인 부담금: #{본인부담금}원\n입금 금액: #{금액}원 (본인부담금 + 배송비)\n\n담당자가 가상계좌를 안내드릴 예정입니다.\n감사합니다.",
            ],
            'shipping_started' => [
                'label' => '배송 시작',
                'desc'  => '택배 발송 및 운송장 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 제품이 발송되었습니다.\n주문번호: #{주문번호}\n운송장: #{운송장번호}",
            ],
            'custom' => [
                'label' => '직접 입력',
                'desc'  => '메시지를 직접 작성',
                'text'  => '',
            ],
        ];
    }

    // ── 환자 자동 등록/연결 ───────────────────────────────
    private function linkOrCreatePatient(Prescription $prescription, array $d): void
    {
        $name       = $d['patient_name'] ?? $d['patient_name_ocr'] ?? null;
        $residentNo = $d['resident_no']  ?? null;
        $mobile     = $d['mobile'] ?? $d['phone'] ?? null;
        $address    = $d['address'] ?? null;

        // 환자명이 없으면 연결 불가
        if (empty($name)) {
            return;
        }

        $patient = null;

        // ① 주민등록번호로 기존 환자 검색 (가장 정확)
        if ($residentNo) {
            $patient = Patient::where('resident_no', $residentNo)->first();
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

        if ($patient) {
            // 기존 환자 — 비어있는 필드만 OCR 값으로 채움
            $updates = [];
            if (!$patient->resident_no && $residentNo) $updates['resident_no'] = $residentNo;
            if (!$patient->mobile      && $mobile)     $updates['mobile']      = $mobile;
            if (!$patient->address     && $address)    $updates['address']     = $address;
            if ($updates) {
                $patient->update($updates);
            }
        } else {
            // 신규 환자 등록
            $patient = Patient::create([
                'name'        => $name,
                'resident_no' => $residentNo,
                'mobile'      => $mobile,
                'address'     => $address,
            ]);

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
