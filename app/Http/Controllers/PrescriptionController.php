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
                // 요청서 6쪽 — 목록에서 바로 견주는 값들
                'resident_no'  => $rx->resident_no_ocr_masked ?? $rx->patient?->masked_resident_no ?? '',
                'uploader'     => $rx->creator?->name ?? '',
                'reviewed_at'  => $rx->reviewed_at?->format('Y-m-d H:i') ?? '',
                'review_memo'  => $rx->review_memo ?? '',
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
        $soNo = $prescription->order?->withworks_so_no;
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
            /* 청구전략 — 우리 화면이 정한 것(유형 × 자격)을 위드웍스 코드로 옮겨 보낸다.
               예전에는 25 가 박혀 있어 어느 건이든 같은 값이 나갔다. 코드표를 아직 받지
               못해 표의 값은 모두 25 지만, 받으면 config 한 곳만 고치면 된다. */
            'billing_strategy'        => $this->withworksBillingStrategy($prescription),
            /* 수량은 낱개로 센다 — 우리 화면의 「수량」은 총계(1일 처방개수 × 총 처방기간)다.
               밝히지 않으면 저쪽은 RB(박스)로 읽고 r_box 를 곱해, 540개가 5,400개로
               등록됐다. 고치는 쪽(so_update)은 진작 낱개로 읽고 있어 만들 때와 고칠 때가
               열 배 어긋나 있었다. */
            'qty_unit'                => 'EA',
            /* 확정은 창고에서 한다. 우리는 등록까지만 한다 — 올리자마자 확정되면
               수량ㆍ배송지를 고칠 자리가 없고, 재고가 그 자리에서 묶인다. */
            'confirm'                 => false,
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

                /* 창고에 판매주문이 섰다 — 이제 고객에게 알린다. 여기까지 와야 「확정」이다.
                   보내지 못해도 주문은 이미 선 것이라 되돌리지 않는다. 무슨 일이 있었는지는
                   답에 실어 화면이 함께 보여 준다. */
                $sms = $this->sendOrderConfirmedSms($prescription);

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

            return response()->json(['success' => false, 'message' => "Withworks 연계 실패: {$errMsg}"]);

        } catch (\Throwable $e) {
            Log::error('Withworks API 연결 오류', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Withworks 서버에 연결할 수 없습니다.'], 500);
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
    private function sendOrderConfirmedSms(Prescription $prescription): array
    {
        $prescription->refresh()->loadMissing('patient', 'order');
        $order = $prescription->order;

        if (! $order) {
            return ['sent' => false, 'method' => '', 'message' => '주문이 없어 결제 안내를 보내지 못했습니다.'];
        }

        /* 받을 돈이 없으면 보내지 않는다. 차상위경감ㆍ기초는 본인부담이 0 이라,
           그대로 두면 환자에게 「0원을 입금해 주십시오」가 나갔다. */
        if ($order->expectedDeposit() <= 0) {
            return [
                'sent'    => false,
                'method'  => '',
                'message' => '본인부담금이 없어 결제 안내를 보내지 않았습니다.',
            ];
        }

        $method = $this->confirmPayMethod();
        $mobile = $prescription->patient?->mobile ?: $prescription->mobile_ocr;

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
     * 주문 확정 안내를 무엇으로 보낼지.
     *
     * 링크페이는 토스 결제 페이지를 여는 것이라 클라이언트 키·시크릿 키가 있어야 한다.
     * 승인 전에 골라 두었으면 무통장입금으로 내려간다 — 보내지 못하고 멈추는 것보다
     * 계좌라도 적어 보내는 편이 낫다.
     */
    private function confirmPayMethod(): string
    {
        $want = config('order.confirm_pay_method', 'bank') === 'card'
            ? \App\Models\PaymentLink::METHOD_CARD
            : \App\Models\PaymentLink::METHOD_BANK;

        if ($want === \App\Models\PaymentLink::METHOD_CARD
            && (! config('toss.client_key') || ! config('toss.secret_key'))) {
            Log::warning('[주문 확정 안내] 링크페이로 설정돼 있으나 토스 키가 없어 무통장입금으로 보낸다');
            return \App\Models\PaymentLink::METHOD_BANK;
        }

        return $want;
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
            /* 25 는 아무것도 가리키지 않는 줄이었다(데모웍스 account_id 0). 등록과 같은
               자리에서 셈해 보낸다 — 등록과 수정이 다른 전략으로 나가면 안 된다. */
            'billing_strategy'        => $this->withworksBillingStrategy($prescription),
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
           이 화면은 처방 서류만 받으므로 청구 갈래(claim)는 보내지 않는다. */
        $docTypes = [];
        foreach (['rx', 'etc'] as $kind) {
            $docTypes[$kind] = \App\Models\CommonCode::options('doc_type', $kind)
                /* 위임장은 고르는 자리에 두지 않는다 — 주문 등록에서 환자가 서명하면
                   그때 저절로 만들어져 서류 관리에 들어간다. 여기서 또 올리게 두면
                   같은 위임장이 두 장이 되고, 어느 것이 서명본인지 알 수 없다. */
                ->reject(fn ($c) => $c->code === 'delegation')
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

        /* 찍어 올린 종이의 그늘을 걷을지(2026-09-02 요청서 · camscanner 처럼).
           켠 사람만 쓴다 — 스캐너로 곧게 뜬 파일까지 손대면 오히려 나빠진다. */
        $scanClean = $request->boolean('scan_clean');

        foreach ($prescriptionFiles as $file) {
            $subDir   = 'prescriptions/' . now()->format('Y/m');
            $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($subDir, $fileName, 'public');

            if ($scanClean) {
                \App\Support\ScanClean::applyInPlace('public', $path, $file->getMimeType());
            }

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

                /* 결과지ㆍ등록신청서도 같이 찍어 올린다 — 처방전만 걷을 까닭이 없다 */
                if ($scanClean) {
                    \App\Support\ScanClean::applyInPlace('public', $path, $file->getMimeType());
                }

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
            'account'         => $patient->only([
                'name', 'care_type', 'resident_no', 'birth_date', 'gender',
                'mobile', 'phone', 'email', 'fax', 'sb_sci',
                'postcode', 'address', 'address_detail', 'note',
                'contact_status', 'contact_channel', 'remitter_name',
                'deduction', 'cash_receipt_no',
                'nhis_reg_status', 'nhis_reg_date', 'nhis_renew', 'nhis_renew_due',
                'nhis_agree_start', 'nhis_agree_end',
                'basic_reeval', 'basic_reeval_due',
            ]) + ['resident_no' => $patient->resident_no],
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

        /* 주문 목록 탭 — 이 화면 안에서 다른 건으로 건너뛰는 자리다.
           여기서 하려는 일은 「다음에 손댈 주문을 고르는 것」이라, 아직 확정되지 않은 건만
           세운다(주문 대기). 확정ㆍ배송ㆍ완료된 건은 손댈 차례가 지났고, 그것들을 훑는
           자리는 주문 관리다.

           반품ㆍ교환ㆍ취소가 붙은 건도 뺀다(판매만) — 그것들은 「교환/반품/취소」 화면이 맡는다.

           목록은 wwGrid 가 한 번에 다 받아 그리므로 통째로 넘긴다. 다만 끝없이 늘어날
           표라 최근 것부터 상한을 둔다 — 넘친 만큼은 화면이 말해 준다. */
        $orderListLimit = 500;
        $orderListTotal = \App\Models\Order::whereDoesntHave('returns')
            ->where('status', 'pending')->count();
        $orderListSource = \App\Models\Order::with([
                'patient', 'prescription.assignedUser', 'prescription.creator', 'prescription.updater',
                'prescription.billingOffice', 'items.lots', 'operationUser',
            ])
            ->whereDoesntHave('returns')
            ->where('status', 'pending')
            ->latest('id')
            ->limit($orderListLimit)
            ->get();

        /* 동의 두 가지는 사람에 붙는다 — 줄마다 물으면 마흔 줄에 여든을 더 묻는다.
           목록을 만들기 전에 한 번에 모아 둔다. */
        $extras = \App\Support\OrderGridExtras::forPatients($orderListSource->pluck('patient_id'));

        $orderListRows = $orderListSource
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
                'status'    => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                'sold_at'   => $o->created_at?->format('Y-m-d') ?? '',
                // 고르면 이 주소로 간다. claim=1 은 「임자 없으면 내가 맡는다」는 표시다.
                'url'       => $rx ? route('prescriptions.show', $rx) . '?claim=1' : null,

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

        return view('prescriptions.order', compact(
            'prescription', 'patients', 'prevId', 'nextId', 'repurchaseBlock',
            'tossConfigured', 'kakaoConfigured', 'kakaoTemplates', 'smsTemplates',
            'memosData', 'prevCounselings', 'prevCounselingsData',
            'lastFaxHistory', 'attachmentsJson', 'allDocsJson', 'patientsJson',
            'orderManagers', 'privacyState',
            'orderListRows', 'orderListTotal', 'orderListLimit'
        ));
    }

    // ── OCR 수정 저장 ─────────────────────────────────────
    public function updateOcr(Request $request, Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        /* 이 저장이 처방전을 사람에게 처음 붙이는 저장인가 — 아래에서 위임동의를 보낼지
           가리는 데 쓴다. 붙고 난 뒤에는 몇 번을 저장하든 첫 저장이 아니다. */
        $hadPatient = (bool) $prescription->patient_id;

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
        $totalNhis  = $prescription->items->sum('nhis_amount');
        $totalCopay = $prescription->items->sum('patient_copay');

        /* 저장하면 주문 관리에도 선다. 처방전 그림이 없어도, 제품을 아직 안 골랐어도
           그렇다 — 주문 등록에서 저장한 건은 곧 하나의 거래이고, 그것을 보는 자리가
           주문 관리다. 예전에는 「주문 생성 및 연계」를 눌러야만 줄이 생겨, 상담만
           받아 적어 둔 건은 어느 목록에도 없이 떠 있었다.

           여기서는 우리 쪽 주문만 만든다. 위드웍스로 보내는 것은 그 단추가 할 일이다 —
           저장할 때마다 창고로 주문이 날아가서는 안 된다. */
        $order = $this->ensureOrder($prescription);

        activity()->causedBy(Auth::user())->performedOn($prescription)->log('OCR 필드 수정');

        /* 이 저장으로 사람이 처음 붙었으면 위임동의 서명 SMS 를 함께 보낸다.
           보내지 못해도 저장은 이미 끝난 것이라 되돌리지 않는다 — 무슨 일이 있었는지는
           답에 실어 화면이 함께 보여 준다(주문 확정 안내와 같은 방식). */
        $consentSms = $this->consentSmsOnFirstSave($prescription, $hadPatient);

        return response()->json([
            'success'     => true,
            'message'     => '저장되었습니다.',
            'consent_sms' => $consentSms,
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
                           . '메시지 관리에서 승인받은 알림톡 문구를 적어 주십시오.',
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
        $patientName = trim((string) $request->input('name'))
            ?: ($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '환자');

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
    private function consentSmsOnFirstSave(Prescription $prescription, bool $hadPatient): array
    {
        $no = fn (?string $why) => ['sent' => false, 'reason' => $why, 'expires_at' => null];

        if (!config('order.consent_sms_on_first_save')) return $no(null);   // 꺼 두었으면 말도 하지 않는다
        if ($hadPatient) return $no(null);                                  // 첫 저장이 아니다

        $prescription->refresh()->loadMissing('patient');
        $patient = $prescription->patient;
        if (!$patient) return $no(null);                                    // 아직 사람이 붙지 않았다

        /* 이미 받아 둔 동의가 있으면 다시 받지 않는다. 처방전이 아니라 사람으로 본다 —
           위임은 사람이 하는 것이고, 화면의 「위임동의 완료」도 그렇게 읽는다. */
        $hasAgreed = \App\Models\PrescriptionConsent::whereIn(
                'prescription_id', Prescription::where('patient_id', $patient->id)->select('id'))
            ->where('status', 'agreed')->exists();
        if ($hasAgreed) return $no('이미 위임동의를 받은 분입니다.');

        // 아직 살아 있는 링크가 있으면 그것을 쓰게 둔다 — 두 통이 가면 어느 것을 여는지 갈린다
        $alive = \App\Models\PrescriptionConsent::whereIn(
                'prescription_id', Prescription::where('patient_id', $patient->id)->select('id'))
            ->where('status', 'pending')->where('expires_at', '>', now())->exists();
        if ($alive) return $no('보낸 서명 링크가 아직 열려 있습니다.');

        $mobile = preg_replace('/\D/', '', (string) ($patient->mobile ?: $prescription->mobile_ocr));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return $no('연락처가 없어 위임동의를 보내지 못했습니다.');
        }

        /* 서명 링크는 30분만 열린다. 밤에 보내면 환자가 아침에 열어 이미 만료다 —
           정해 둔 시간 밖이면 보내지 않고 그렇게 말한다. */
        if (!$this->withinConsentHours()) {
            return $no('발송 시간(' . config('order.consent_sms_hours') . ') 밖이라 보내지 않았습니다.');
        }

        $name = $patient->name ?: ($prescription->patient_name_ocr ?: '고객');

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
        $message = "[콜로플라스트] {$patientName}님\n건강보험 급여 위임동의 서명 요청입니다.\n서명 링크(30분 유효):\n{$url}";

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
    public function duplicate(Request $request, Prescription $prescription): JsonResponse
    {
        $request->validate(['patient_id' => 'nullable|integer|exists:patients,id']);

        /* 어느 칸이 날짜인지는 표에 물어본다. 칸이 늘 때마다 여기 목록을 고쳐 적는 일을
           만들지 않으려는 것이다 — 적기를 잊으면 지난 날짜가 조용히 따라온다. */
        $dateCols = collect(Schema::getColumnListing('prescriptions'))
            ->filter(fn ($c) => in_array(
                Schema::getColumnType('prescriptions', $c), ['date', 'datetime', 'timestamp'], true
            ))->values()->all();

        // 그 건에만 속한 것들 — 베끼면 안 되는 자리
        $skip = array_merge($dateCols, [
            'id', 'rx_number', 'status', 'is_blank_draft',
            'reviewed_by', 'review_memo',
            'image_path', 'image_original_name', 'image_mime_type', 'image_size',
            'counsel_no', 'counsel_order_id',
            'created_by', 'updated_by',
            'registration_no', 'serial_no',
        ]);

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

        activity()->causedBy(Auth::user())->performedOn($copy)
            ->log("{$prescription->rx_number} 를 베껴 {$copy->rx_number} 를 만듦");

        return response()->json([
            'success'   => true,
            'message'   => "{$copy->rx_number} 로 베껴 왔습니다. 날짜는 새로 적어 주십시오.",
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
    private function withworksBillingStrategy(Prescription $prescription): ?int
    {
        /* 자격을 아직 고르지 않은 건은 열쇠가 null 이다 — 널로 배열을 찾으면 PHP 가
           나무란다. 빈 글자로 바꾸면 표에 없는 열쇠가 되어 기본값으로 내려간다. */
        $key = \App\Support\BillingStrategy::key(
            $prescription->counsel_acc_add_type,
            $prescription->benefit_class,
        ) ?? '';

        $mode = config('services.demoworks.mode') === 'production' ? 'production' : 'test';
        $conf = (array) config("services.withworks_billing_strategy.{$mode}", []);

        $id = ((array) ($conf['map'] ?? []))[$key] ?? $conf['default'] ?? null;

        return $id === null ? null : (int) $id;
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
