<?php
// app/Http/Controllers/SettlementController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TossPayment;
use App\Services\Popbill\MessageService;
use App\Services\TossPayments\TossApiException;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SettlementController extends Controller
{
    /** 처방 유형 — 정의는 Prescription 에 있다. 여기서 쓰던 이름을 남겨 둔다. */
    public const ACC_TYPES = \App\Models\Prescription::ACC_TYPES;

    public function __construct(private readonly VirtualAccountService $vaService) {}

    // ─────────────────────────────────────────────────────────────
    // 정산/가상계좌 화면
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $tab      = $request->get('tab', 'settlement');
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('date_to',   now()->format('Y-m-d'));

        // ── 정산 요약 ──────────────────────────────────────────
        $base = fn() => Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);

        $summary = [
            'total_orders'  => $base()->count(),
            'total_amount'  => $base()->sum('total_amount'),
            'nhis_amount'   => $base()->sum('nhis_amount'),
            'patient_copay' => $base()->sum('patient_copay'),
            'nhis_reimb'    => $base()->sum('nhis_reimbursement'),
        ];

        $statusCounts = [
            'all'       => Order::count(),
            'pending'   => Order::where('status', 'pending')->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        // ── 정산 목록 ──────────────────────────────────────────
        // items.lots ㆍ billingOffice — 네 화면이 함께 쓰는 칸이 읽는다
        $query = Order::with(['patient', 'prescription.billingOffice', 'tossPayment',
                              'paymentLinks', 'items.lots', 'operationUser'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // 원내·원외·처방외를 나눠 본다
        if ($request->filled('acc_type')) {
            $query->whereHas('prescription', fn ($p) => $p->where('counsel_acc_add_type', $request->acc_type));
        }
        if ($request->filled('search')) {
            $kw = $request->search;
            $query->where(function ($q) use ($kw) {
                $q->where('order_number', 'like', "%{$kw}%")
                  ->orWhere('product_name', 'like', "%{$kw}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$kw}%"));
            });
        }

        // ── 토스 API 상태 ──────────────────────────────────────
        $tossConfigured = $this->vaService->isConfigured();
        $tossReachable  = $tossConfigured ? $this->vaService->ping() : false;
        $tossApiStatus  = match(true) {
            !$tossConfigured => 'unconfigured',
            $tossReachable   => 'connected',
            default          => 'error',
        };

        // ── 가상계좌 현황 ──────────────────────────────────────
        $vaQuery = Order::with(['patient', 'tossPayment'])
            ->whereIn('status', \App\Models\Order::OPEN_AFTER_CONFIRM)
            ->where('patient_copay', '>', 0)
            ->latest();

        if ($request->filled('va_search')) {
            $kw = $request->va_search;
            $vaQuery->where(function ($q) use ($kw) {
                $q->where('order_number', 'like', "%{$kw}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$kw}%"))
                  ->orWhereHas('tossPayment', fn($p) => $p->where('account_number', 'like', "%{$kw}%"));
            });
        }
        if ($request->filled('va_status')) {
            match($request->va_status) {
                'issued'    => $vaQuery->whereHas('tossPayment'),
                'not_issued'=> $vaQuery->whereDoesntHave('tossPayment'),
                /* 사람이 확인한 건도 입금완료다 — 토스만 보면 통장으로 받은 건이 샌다 */
                'done'      => $vaQuery->where(fn($q) => $q->whereNotNull('deposit_confirmed_at')
                                    ->orWhereHas('tossPayment', fn($t) => $t->where('status', 'DONE'))),
                'waiting'   => $vaQuery->whereNull('deposit_confirmed_at')
                                    ->whereHas('tossPayment', fn($q) => $q->where('status', 'WAITING_FOR_DEPOSIT')),
                default     => null,
            };
        }

        $vaStats = [
            'total'      => Order::whereIn('status', \App\Models\Order::OPEN_AFTER_CONFIRM)->where('patient_copay', '>', 0)->count(),
            'issued'     => TossPayment::count(),
            /* 토스가 확인한 것 + 사람이 확인한 것(토스가 아직 모르는 것만 더한다) */
            'done'       => TossPayment::where('status', 'DONE')->count()
                            + Order::whereNotNull('deposit_confirmed_at')
                                ->whereDoesntHave('tossPayment', fn($q) => $q->where('status', 'DONE'))
                                ->count(),
            'waiting'    => TossPayment::where('status', 'WAITING_FOR_DEPOSIT')
                                ->whereHas('order', fn($q) => $q->whereNull('deposit_confirmed_at'))
                                ->count(),
            'not_issued' => Order::whereIn('status', \App\Models\Order::OPEN_AFTER_CONFIRM)
                                ->where('patient_copay', '>', 0)
                                ->whereDoesntHave('tossPayment')->count(),
            'pending_amount' => TossPayment::where('status', 'WAITING_FOR_DEPOSIT')
                                    ->whereHas('order', fn($q) => $q->whereNull('deposit_confirmed_at'))
                                    ->sum('amount'),
        ];

        // ── wwGrid: 활성 탭 데이터/컬럼 (클라이언트사이드, 배지→텍스트, 금액→정수) ──
        if ($tab === 'virtual_account') {
            [$gridData, $gridColumns] = $this->buildVaGrid($vaQuery->get());
        } else {
            [$gridData, $gridColumns] = $this->buildSettlementGrid($query->get());
        }
        $total = $gridData->count();

        return view('settlement.index', compact(
            'tab', 'dateFrom', 'dateTo',
            'summary', 'statusCounts', 'vaStats',
            'tossConfigured', 'tossApiStatus',
            'gridData', 'gridColumns', 'total'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // wwGrid: 정산 목록 데이터/컬럼
    // ─────────────────────────────────────────────────────────────

    private function buildSettlementGrid($orders): array
    {
        $nhisMap = ['pending' => '대기', 'submitted' => '청구완료', 'approved' => '승인', 'rejected' => '반려'];

        /* 네 화면이 함께 쓰던 칸을 여기에도 세운다(요청서 3쪽). 동의 두 가지는 사람에
           붙어, 줄마다 물으면 서른 줄에 예순을 더 묻는다 — 미리 모아 둔다. */
        $extras = \App\Support\OrderGridExtras::forPatients($orders->pluck('patient_id'));

        $data = $orders->map(function ($order) use ($nhisMap, $extras) {
            $tp = $order->tossPayment;

            /* 토스가 확인했든 담당자가 통장을 보고 확인했든 「들어왔다」는 하나다.
               사람이 확인한 것을 따로 두면, 화면이 두 가지 진실을 말하게 된다. */
            $vaState = match (true) {
                $order->deposit_confirmed_at !== null => '입금완료',
                !$tp             => '미발급',
                $tp->is_done     => '입금완료',
                $tp->is_expired  => '만료',
                default          => '대기중',
            };

            $sl = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label' => $order->status, 'badge' => 'secondary'];

            return [
                'id'           => $order->id,
                'order_no'     => $order->order_number,
                'patient'      => $order->patient?->name ?? '-',
                'rx_number'    => $order->prescription?->rx_number ?? '-',
                // 원내·원외·처방외는 정산에서 나눠 봐야 하는 값이다
                'acc_type'     => self::ACC_TYPES[$order->prescription?->counsel_acc_add_type] ?? '-',
                'product'      => $order->product_name ?? '-',
                'total_amount' => (int) ($order->total_amount ?? 0),
                'nhis_amount'  => (int) ($order->nhis_amount ?? 0),
                'unit_price'   => (int) ($order->unit_price ?? 0),
                'copay'        => (int) ($order->patient_copay ?? 0),
                'va_state'     => $vaState,
                /* 결제 방식 — 받은 뒤에야 말할 수 있다. 확인 전에는 「-」로 둔다. */
                'pay_method'     => ($order->deposit_confirmed_at !== null || (bool) $tp?->is_done)
                                        ? $order->payMethodLabel() : '-',
                'pay_method_key' => $order->pay_method,
                'deposit'      => $order->deposit_confirmed_at !== null
                                    ? number_format((int) ($order->deposit_amount ?? $order->expectedDeposit()))
                                    : ($tp?->is_done ? number_format($tp->amount ?? 0) : '-'),
                /* 「입금 확인」 단추가 무엇을 할지 가리는 데 쓴다(컬럼 아님) */
                'deposit_done' => $order->deposit_confirmed_at !== null || (bool) $tp?->is_done,
                'deposit_hand' => $order->deposit_confirmed_at !== null,
                // 배송비는 없다(2026-09-03 확정) — 받을 돈은 본인부담뿐이다
                'deposit_due'  => $order->expectedDeposit(),
                'status'       => $sl['label'],
                'settle'       => $order->settleStatusLabel(),
                /* 못 받은 채로 닫은 건은 이것이 없으면 나중에 다시 못 들춘다
                   (요청서 12쪽 — 「입금 받지 못할 경우에도 마감 확정해야 하는 경우」) */
                'settle_reason' => $order->settle_reason ?? '',
                'settle_key'   => $order->settle_status,
                /* 증빙 — 발행된 것만 눌러서 펼쳐 볼 수 있다. 칸에는 아무 글자도 두지
                   않는다(단추만 선다). 발행 여부와 승인번호는 단추가 읽는 값이다. */
                'proof'        => '',
                'tax_issued'   => $order->tax_invoice_status === 'issued',
                'tax_no'       => (string) ($order->tax_invoice_no ?? ''),
                'tax_url'      => $order->tax_invoice_status === 'issued'
                                    ? route('orders.taxInvoicePreview', $order) : null,
                'cash_issued'  => $order->cash_receipt_status === 'issued',
                'cash_no'      => (string) ($order->cash_receipt_no ?? ''),
                'cash_url'     => $order->cash_receipt_status === 'issued'
                                    ? route('orders.cashReceiptPreview', $order) : null,
                'nhis_claim'   => $nhisMap[$order->nhis_claim_status ?? 'pending'] ?? '대기',
                'created'      => $order->created_at?->format('Y-m-d') ?? '-',
                // 상세 팝오버 URL (컬럼 아님 — 외부 버튼에서 사용)
                'rx_url'       => $order->prescription ? route('settlement.prescription-detail', $order->prescription) : null,
                /* 「주문 보기」가 여는 자리 — 팝업이 아니라 주문 등록 화면을 탭으로 연다 */
                'rx_open_url'  => $order->prescription ? route('prescriptions.show', $order->prescription) : null,
                'rx_number'    => $order->prescription?->rx_number,
                'order_url'    => $order->product_name ? route('settlement.order-detail', $order) : null,

                // 네 화면이 함께 쓰는 칸 — 차례와 이름이 어디서나 같다
            ] + $extras->rx($order->prescription, $order->patient)
              + $extras->ww($order, $order->prescription, $order->patient)
              + $extras->of($order);
        })->values();

        $columns = [
            ['header' => '주문번호',    'name' => 'order_no',     'width' => 120, 'sortable' => true],
            ['header' => '이름',      'name' => 'patient',      'width' => 90,  'sortable' => true],
            ['header' => '처방번호',    'name' => 'rx_number',    'width' => 120],
            ['header' => '유형',        'name' => 'acc_type',     'width' => 100, 'align' => 'center', 'sortable' => true],
            /* 한 개 값이라 더하지 않는다 — 다 합쳐 봐야 아무 뜻이 없다.
               이름이 「주문금액」이었다. 정산 담당자는 그것을 이 건의 값으로 읽는데
               실제로는 한 개 값이라, 121만 원짜리 주문이 2,250원으로 보였다.
               이 건의 값은 뒤의 「총 금액」ㆍ「본인 부담금」ㆍ「기관 부담금」이 말한다. */
            ['header' => '단가',        'name' => 'unit_price',   'width' => 100, 'editor' => 'number', 'summary' => false],
            ['header' => '입금액',      'name' => 'deposit',      'width' => 100, 'align' => 'right'],
            // 발행된 세금계산서ㆍ현금영수증을 그 자리에서 펼쳐 보는 단추 자리
            ['header' => '증빙',        'name' => 'proof',        'width' => 176, 'align' => 'center'],
            ['header' => '주문상태',    'name' => 'status',       'width' => 90,  'align' => 'center', 'sortable' => true],
            // 정산이 어디까지 갔는가(요청서 12쪽) — 마감 → 확정
            ['header' => '정산상태',    'name' => 'settle',       'width' => 90,  'align' => 'center', 'sortable' => true],
            ['header' => '정산 사유',   'name' => 'settle_reason','width' => 200],
            ['header' => '접수일',      'name' => 'created',      'width' => 100, 'sortable' => true],
        ];

        /* 네 화면이 함께 쓰던 칸을 이어 붙인다(요청서 3쪽). 이 화면은 칸을 PHP 로 넘기므로
           ceMoneyCols()ㆍceWwCols() 를 화면에서 펼친다 — buildSettlementGrid 는 앞의 것만
           만들고, 뒤는 settlement/index 가 잇는다. */

        return [$data, $columns];
    }

    // ─────────────────────────────────────────────────────────────
    // wwGrid: 가상계좌 목록 데이터/컬럼
    // ─────────────────────────────────────────────────────────────

    private function buildVaGrid($vaOrders): array
    {
        $data = $vaOrders->map(function ($order) {
            $tp = $order->tossPayment;

            /* 담당자가 통장을 보고 세운 것도 「입금완료」다. 토스만 보면 가상계좌를
               발급하지 않은 건이 영영 「미발급」으로 남는다. */
            $vaStatus = match (true) {
                $order->deposit_confirmed_at !== null => '입금완료',
                !$tp                                  => '미발급',
                $tp->is_done                          => '입금완료',
                $tp->is_expired                       => '만료',
                $tp->status === 'WAITING_FOR_DEPOSIT' => '입금대기',
                default                               => $tp->status_label,
            };

            $sl = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label' => $order->status, 'badge' => 'secondary'];

            return [
                'id'         => $order->id,
                'order_no'   => $order->order_number,
                'patient'    => $order->patient?->name ?? '-',
                'mobile'     => $order->patient?->mobile ?? '-',
                'copay'      => (int) ($order->patient_copay ?? 0),
                'status'     => $sl['label'],
                'va_account' => $tp ? trim(($tp->bank_name ?? '') . ' ' . ($tp->account_number ?? '')) : '미발급',
                'va_status'  => $vaStatus,
                'due'        => $tp?->due_date?->format('Y-m-d H:i') ?? '-',
                'deposited'  => $order->deposit_confirmed_at?->format('Y-m-d H:i')
                                ?? $tp?->deposited_at?->format('Y-m-d H:i') ?? '-',
                /* 누가 세웠는지 — 사람이 세운 것은 그렇다고 적어 둔다 */
                'deposit_by' => $order->deposit_confirmed_at ? '담당자 확인' : ($tp?->is_done ? '토스' : '-'),
                /* 아래 셋은 컬럼이 아니다 — 「입금 확인」 단추가 무엇을 할지 가린다 */
                'deposit_done' => $order->deposit_confirmed_at !== null || (bool) $tp?->is_done,
                'deposit_hand' => $order->deposit_confirmed_at !== null,
                // 배송비는 없다(2026-09-03 확정) — 받을 돈은 본인부담뿐이다
                'deposit_due'  => $order->expectedDeposit(),
            ];
        })->values();

        $columns = [
            ['header' => '주문번호',      'name' => 'order_no',   'width' => 120, 'sortable' => true],
            ['header' => '이름',        'name' => 'patient',    'width' => 90,  'sortable' => true],
            ['header' => '연락처',        'name' => 'mobile',     'width' => 120],
            ['header' => '본인부담금',    'name' => 'copay',      'width' => 110, 'editor' => 'number'],
            ['header' => '주문상태',      'name' => 'status',     'width' => 90,  'align' => 'center', 'sortable' => true],
            ['header' => '가상계좌 발급', 'name' => 'va_account', 'width' => 180],
            ['header' => '입금 상태',     'name' => 'va_status',  'width' => 100, 'align' => 'center', 'sortable' => true],
            ['header' => '만료일시',      'name' => 'due',        'width' => 140, 'align' => 'center'],
            ['header' => '입금확인일',    'name' => 'deposited',  'width' => 140, 'align' => 'center'],
            ['header' => '확인',        'name' => 'deposit_by', 'width' => 90,  'align' => 'center'],
        ];

        return [$data, $columns];
    }

    // ─────────────────────────────────────────────────────────────
    // 처방전 상세 팝업 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function prescriptionDetail(\App\Models\Prescription $prescription): JsonResponse
    {
        $prescription->load(['patient', 'assignedUser', 'items', 'order']);

        $statusLabels = \App\Models\Prescription::STATUS_LABELS;

        return response()->json([
            'id'               => $prescription->id,
            'rx_number'        => $prescription->rx_number,
            'status_label'     => $statusLabels[$prescription->status]['label'] ?? $prescription->status,
            'status_badge'     => $statusLabels[$prescription->status]['badge'] ?? 'secondary',
            'upload_source'    => $prescription->upload_source === 'mobile' ? '모바일' : '웹',
            'issued_date'      => $prescription->issued_date?->format('Y-m-d'),
            'created_at'       => $prescription->created_at->format('Y-m-d H:i'),
            // 환자
            'patient_name'     => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-',
            'patient_birth'    => $prescription->patient?->birth_date?->format('Y-m-d') ?? '-',
            'patient_mobile'   => $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '-',
            'resident_no'      => $prescription->masked_resident_no_ocr ?? '-',
            // 병원·의사
            'hospital_name'    => $prescription->hospital_name ?? '-',
            'doctor_name'      => $prescription->doctor_name   ?? '-',
            'department'       => $prescription->department    ?? '-',
            'disease_name'     => $prescription->disease_name  ?? '-',
            'disease_code'     => $prescription->disease_code  ?? '-',
            // 처방 수량
            'daily_count'      => $prescription->daily_count,
            'total_days'       => $prescription->total_days,
            'total_count'      => $prescription->total_count,
            // 담당
            'assigned_user'    => $prescription->assignedUser?->name ?? '-',
            'admin_note'       => $prescription->admin_note ?? '',
            // 처방 품목
            'items'            => $prescription->items->map(fn($item) => [
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_status'     => match($item->nhis_status ?? '') {
                    'eligible'   => '급여',
                    'ineligible' => '비급여',
                    'partial'    => '일부급여',
                    default      => '-',
                },
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 주문 상세 팝업 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function orderDetail(Order $order): JsonResponse
    {
        $order->load(['patient', 'creator', 'tossPayment']);

        $statusLabel = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label' => $order->status, 'badge' => 'secondary'];
        $nhisMap     = ['pending' => '대기', 'submitted' => '청구완료', 'approved' => '승인', 'rejected' => '반려'];

        return response()->json([
            'order_number'    => $order->order_number,
            'status_label'    => $statusLabel['label'],
            'status_badge'    => $statusLabel['badge'],
            'nhis_status'     => $nhisMap[$order->nhis_claim_status ?? 'pending'] ?? '대기',
            'created_at'      => $order->created_at->format('Y-m-d H:i'),
            'delivered_at'    => $order->delivered_at?->format('Y-m-d H:i'),
            // 환자
            'patient_name'    => $order->patient?->name ?? '-',
            'patient_mobile'  => $order->patient?->mobile ?? '-',
            // 금액
            'unit_price'      => $order->unit_price,
            'nhis_amount'     => $order->nhis_amount,
            'patient_copay'   => $order->patient_copay,
            'total_amount'    => $order->total_amount,
            'nhis_reimb'      => $order->nhis_reimbursement,
            // 배송
            'shipping_address'=> $order->shipping_address ?? '-',
            'tracking_number' => $order->tracking_number  ?? '-',
            // 담당
            'creator'         => $order->creator?->name ?? '-',
            'note'            => $order->note ?? '',
            // 주문 품목 (주문 자체 필드 기준)
            'items'           => [[
                'product_name'  => $order->product_name  ?? '-',
                'product_code'  => $order->product_code  ?? '-',
                'quantity'      => $order->quantity       ?? 0,
                'unit_price'    => $order->unit_price     ?? 0,
                'nhis_amount'   => $order->nhis_amount    ?? 0,
                'patient_copay' => $order->patient_copay  ?? 0,
            ]],
            // 가상계좌
            'toss_payment'    => $order->tossPayment ? [
                'status_label'   => $order->tossPayment->status_label,
                'status_badge'   => $order->tossPayment->status_badge,
                'bank_name'      => $order->tossPayment->bank_name,
                'account_number' => $order->tossPayment->account_number,
                'amount'         => $order->tossPayment->amount,
                'due_date'       => $order->tossPayment->due_date?->format('Y-m-d H:i'),
                'deposited_at'   => $order->tossPayment->deposited_at?->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 가상계좌 발급 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function issueVirtualAccount(Request $request, Order $order): JsonResponse
    {
        // 호출 측(처방전 화면)이 자체적으로 안내 SMS를 발송하는 경우 skip_sms=1 로 서버 발송을 생략한다.
        $skipSms = $request->boolean('skip_sms');

        // 처방 아이템 기준으로 금액 동기화 (주문 생성 후 아이템이 수정된 경우 대비)
        $prescription = $order->prescription;
        if ($prescription) {
            $prescription->loadMissing('items');
            $freshCopay    = (float) $prescription->items->sum('patient_copay');
            /* 배송비는 없다(2026-09-03 확정). 여기에 「없으면 3,000원」이라는 기본값이
               있어, 배송비를 적지 않은 주문에도 3,000원이 붙어 청구되었다. */
            if ($freshCopay > 0) {
                $order->update([
                    'patient_copay' => $freshCopay,
                    'total_amount'  => $freshCopay,
                ]);
                $order->refresh();
            }
        }

        if ($order->patient_copay <= 0) {
            return response()->json(['success' => false, 'message' => '본인부담금이 없는 주문입니다.'], 422);
        }
        if ($order->tossPayment?->status === 'DONE') {
            return response()->json(['success' => false, 'message' => '이미 입금 완료된 주문입니다.'], 422);
        }

        // ── 가상계좌 발급 비활성화 또는 API 키 미설정 → SMS만 발송 ──
        if (!config('toss.virtual_account_enabled', true) || !$this->vaService->isConfigured()) {
            $validHours = (int) config('toss.virtual_account.valid_hours', 72);
            $dueDate    = now()->addHours($validHours)->format('Y-m-d H:i');

            Log::info('[VA] 가상계좌 발급 비활성화 — SMS만 발송', ['order' => $order->order_number]);
            activity()->causedBy(auth()->user())->performedOn($order)
                ->log('가상계좌 발급 비활성화 상태 — SMS 발송만 처리');

            $fallbackBank    = config('toss.virtual_account.fallback_bank', '');
            $fallbackAccount = config('toss.virtual_account.fallback_account', '');

            TossPayment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_key'    => null,
                    'toss_order_id'  => null,
                    'method'         => 'VIRTUAL_ACCOUNT',
                    'status'         => 'DISABLED',
                    'amount'         => (int) $order->patient_copay,
                    'bank'           => $fallbackBank,
                    'account_number' => $fallbackAccount,
                    'customer_name'  => $order->patient?->name ?? '환자',
                    'due_date'       => now()->addHours($validHours),
                ]
            );

            $smsSent = $skipSms ? false : $this->sendVirtualAccountSms(
                $order,
                $fallbackBank,
                $fallbackAccount,
                (int) $order->patient_copay,
                $dueDate
            );

            return response()->json([
                'success'        => true,
                'disabled'       => true,
                'sms_sent'       => $smsSent,
                'message'        => $smsSent
                    ? '가상계좌 발급이 비활성화 상태입니다. 안내 SMS를 발송했습니다.'
                    : '가상계좌 발급이 비활성화 상태입니다. (SMS 발송 불가 — 연락처/대체계좌 확인 필요)',
                'bank_name'      => $fallbackBank,
                'account_number' => $fallbackAccount,
                'due_date'       => $dueDate,
                'amount'         => (int) $order->patient_copay,
            ]);
        }

        try {
            $tp = $this->vaService->issueVirtualAccount($order);

            activity()->causedBy(auth()->user())->performedOn($order)
                ->log("가상계좌 발급: {$tp->bank_name} {$tp->account_number}");

            $smsSent = $skipSms ? false : $this->sendVirtualAccountSms(
                $order,
                $tp->bank_name,
                $tp->account_number,
                (int) $tp->amount,
                $tp->due_date?->format('Y-m-d H:i')
            );

            return response()->json([
                'success'        => true,
                'sms_sent'       => $smsSent,
                'bank_name'      => $tp->bank_name,
                'account_number' => $tp->account_number,
                'due_date'       => $tp->due_date?->format('Y-m-d H:i'),
                'amount'         => $tp->amount,
            ]);
        } catch (TossApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 입금 상태 실시간 조회 (AJAX)
    // ─────────────────────────────────────────────────────────────

    /**
     * 담당자가 통장을 보고 입금을 확인한다.
     *
     * 토스가 알려 주지 못하는 건이 있다 — 가상계좌를 발급하지 않았거나, 토스 밖에서
     * 계좌이체ㆍ현금으로 들어왔거나, 웹훅이 유실된 건. 그런 건을 영영 「대기중」으로
     * 두지 않으려고 사람이 직접 세운다.
     *
     * 금액은 청구액(본인부담금)을 그대로 적는다 — 배송비는 없다. 다른 값을 보내면 그 값을
     * 적되, 말없이 덮지 않고 기록에 남긴다.
     */
    public function confirmDeposit(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'amount' => 'nullable|integer|min:0',
            'note'   => 'nullable|string|max:200',
        ]);

        if ($order->tossPayment?->is_done) {
            return response()->json(['success' => false, 'message' => '토스에서 이미 입금이 확인된 주문입니다.'], 422);
        }
        if ($order->deposit_confirmed_at) {
            return response()->json(['success' => false, 'message' => '이미 입금 확인된 주문입니다.'], 422);
        }

        $due    = $order->expectedDeposit();
        $amount = $request->filled('amount') ? (int) $request->input('amount') : $due;

        $order->update([
            'deposit_confirmed_at' => now(),
            'deposit_confirmed_by' => Auth::id(),
            'deposit_amount'       => $amount,
            'deposit_note'         => $request->input('note'),
        ]);

        /* 돈이 걸린 상태 변경이다 — 누가ㆍ얼마를ㆍ무엇을 보고 확인했는지 남긴다.
           청구액과 다르면 그 사실도 함께 적는다. */
        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('입금 확인(담당자): ' . number_format($amount) . '원'
                . ($amount !== $due ? ' — 청구액 ' . number_format($due) . '원과 다름' : '')
                . ($request->filled('note') ? ' · ' . $request->input('note') : ''));

        $issued = app(\App\Services\DepositAutoIssue::class)->run($order, '담당자 확인');

        return response()->json([
            'success'      => true,
            'issued'       => $issued,
            'confirmed_at' => $order->deposit_confirmed_at->format('Y-m-d H:i'),
            'amount'       => $amount,
            'mismatch'     => $amount !== $due,
            'message'      => $amount === $due
                ? '입금 확인했습니다.'
                : '입금 확인했습니다. 청구액(' . number_format($due) . '원)과 금액이 다릅니다.',
        ]);
    }

    /**
     * 무엇으로 받았는지 고른다 — 그리고 그것이 곧 입금 확인이다.
     *
     * 입금이 확인되기 전에는 「무엇으로 받았는가」를 말할 수 없다. 그래서 결제 방식 칸은
     * 「-」로 비어 있고, 담당자가 통장ㆍ단말을 보고 방식을 고르는 순간 그 건은 받은
     * 것이 된다. 방식을 고르는 일과 입금을 세우는 일이 하나다.
     *
     * 금액은 청구액(본인부담금)을 그대로 적는다 — 배송비는 없다.
     */
    public function setPayMethod(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'method' => 'required|string|in:' . implode(',', array_keys(\App\Models\PaymentLink::METHODS)),
        ]);

        if ($order->deposit_confirmed_at || $order->tossPayment?->is_done) {
            return response()->json(['success' => false, 'message' => '이미 입금이 확인된 주문입니다.'], 422);
        }

        $due = $order->expectedDeposit();

        $order->update([
            'pay_method'           => $request->input('method'),
            'deposit_confirmed_at' => now(),
            'deposit_confirmed_by' => Auth::id(),
            'deposit_amount'       => $due,
        ]);
        $order->refresh();

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log("입금 확인(담당자): {$order->payMethodLabel()} " . number_format($due) . '원');

        /* 돈이 들어왔으면 청구전략이 정한 세무 서류를 낸다. 실패해도 입금 확인은
           그대로 둔다 — 들어온 것은 들어온 것이다(기본은 꺼져 있다). */
        $issued = app(\App\Services\DepositAutoIssue::class)->run($order, '담당자 확인');

        return response()->json([
            'success'      => true,
            'issued'       => $issued,
            'method'       => $order->payMethod(),
            'label'        => $order->payMethodLabel(),
            'amount'       => $due,
            'confirmed_at' => $order->deposit_confirmed_at->format('Y-m-d H:i'),
            'message'      => "{$order->payMethodLabel()}으로 " . number_format($due) . '원 입금 확인했습니다.',
        ]);
    }

    /** 잘못 세운 것을 되돌린다 — 되돌린 것도 기록에 남는다. */
    /**
     * 담당자가 세운 입금 확인을 되돌린다.
     *
     * 입금이 확인되면 청구전략대로 세금계산서나 현금영수증이 자동으로 나간다. 그 입금이
     * 없던 일이 되면 그 돈으로 나간 증빙도 없던 일이 된다 — 묻지 않고 함께 취소한다.
     *
     * 한때 물어보게 두었다(cancel_docs). 팝빌 취소가 국세청 실취소라서였는데, 그러면
     * 「입금은 없던 일인데 신고는 살아 있는」 줄이 남는 길이 열린다. 받지 않은 돈으로 낸
     * 신고가 남는 쪽이 더 나쁘다 — 지금은 늘 함께 취소한다. 대신 되돌리기 전에 화면이
     * 무엇이 함께 사라지는지 적어 두고 한 번 묻는다.
     */
    public function revokeDeposit(Order $order): JsonResponse
    {
        if (!$order->deposit_confirmed_at) {
            return response()->json(['success' => false, 'message' => '담당자가 확인한 입금이 아닙니다.'], 422);
        }

        $was = (int) ($order->deposit_amount ?? 0);
        $order->update([
            'deposit_confirmed_at' => null,
            'deposit_confirmed_by' => null,
            'deposit_amount'       => null,
            'deposit_note'         => null,
            /* 방식도 함께 비운다 — 받지 않은 건에 「무엇으로 받았다」가 남아 있으면
               다음 사람이 이미 받은 것으로 읽는다. */
            'pay_method'           => null,
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('입금 확인 취소(담당자): ' . number_format($was) . '원');

        $docs = $this->cancelIssuedDocs($order);

        $message = '입금 확인을 취소했습니다.';
        if ($docs['done']) {
            $message .= ' ' . implode('·', $docs['done']) . '도 취소했습니다.';
        }
        if ($docs['failed']) {
            $message .= ' 다만 ' . implode(' · ', $docs['failed']) . '.';
        }

        return response()->json([
            'success'    => true,
            'message'    => $message,
            // 목록이 증빙 단추를 그 자리에서 흐리게 돌리는 데 쓴다
            'tax_issued'  => $order->refresh()->tax_invoice_status === 'issued',
            'cash_issued' => $order->cash_receipt_status === 'issued',
            'doc_failed'  => (bool) $docs['failed'],
        ]);
    }

    /**
     * 이 건에 나가 있는 증빙을 팝빌에서 취소한다.
     *
     * 발행과 마찬가지로 화면이 쓰는 취소 경로를 그대로 부른다 — 취소 신고ㆍ상태 기록ㆍ
     * 청구 준비도 갱신이 이미 그 안에 있다. 같은 일을 여기에 다시 적으면 두 곳이 서로
     * 다르게 자란다.
     *
     * 하나가 실패해도 나머지는 이어서 한다. 입금 확인 취소 자체는 이미 끝난 일이라
     * 되돌리지 않는다 — 남는 것은 기록과 알림이다.
     *
     * @return array{done:array<string>, failed:array<string>}
     */
    private function cancelIssuedDocs(Order $order): array
    {
        $done = $failed = [];

        $jobs = [
            ['세금계산서', 'tax_invoice_status',  'cancelTaxInvoice'],
            ['현금영수증', 'cash_receipt_status', 'cancelCashReceipt'],
        ];

        foreach ($jobs as [$what, $statusCol, $action]) {
            if ($order->{$statusCol} !== 'issued') {
                continue;
            }

            try {
                $res  = app(\App\Http\Controllers\OrderController::class)->{$action}($order);
                $body = json_decode($res->getContent(), true) ?: [];

                if ($body['success'] ?? false) {
                    $done[] = $what;
                } else {
                    $failed[] = $what . '는 취소하지 못했습니다(' . ($body['message'] ?? '사유 없음') . ')';
                }
            } catch (\Throwable $e) {
                Log::warning('[입금 확인 취소] ' . $what . ' 취소 실패', [
                    'order' => $order->order_number, 'error' => $e->getMessage(),
                ]);
                $failed[] = $what . '는 취소하지 못했습니다';
            }

            $order->refresh();
        }

        /* 카드로 받은 건은 전표도 함께 무른다 — 계산서만 걷고 승인이 살아 있으면
           환자 카드에는 값이 그대로 남는다. 거래명세서는 손대지 않는다: 물건은
           나간 채로 있고, 무엇을 얼마에 보냈는지는 돈과 따로 남아야 한다. */
        [$cardDone, $cardFailed] = $this->cancelCardApproval($order);
        $done   = array_merge($done, $cardDone);
        $failed = array_merge($failed, $cardFailed);

        return ['done' => $done, 'failed' => $failed];
    }

    /**
     * 이 건의 카드 승인을 토스에서 무른다.
     *
     * 가상계좌ㆍ무통장입금은 우리가 통장을 보고 적은 것이라 무를 곳이 없다 — 돈은
     * 담당자가 따로 돌려보낸다. 카드만 승인을 취소할 수 있고, 그래야 전표가 사라진다.
     *
     * 이미 취소된 것은 건너뛴다. 실패해도 입금 확인 취소 자체는 되돌리지 않는다 —
     * 남는 것은 기록과 알림이고, 담당자가 토스 화면에서 마저 무른다.
     *
     * @return array{0:array<string>, 1:array<string>}
     */
    private function cancelCardApproval(Order $order): array
    {
        $pay = \App\Models\TossPayment::where('order_id', $order->id)
            ->where('method', 'CARD')
            ->whereIn('status', ['DONE', 'PARTIAL_CANCELED'])
            ->orderByDesc('id')
            ->first();

        if (! $pay || ! $pay->payment_key) {
            return [[], []];
        }

        try {
            $res = app(\App\Services\TossPayments\TossClient::class)
                ->post('/v1/payments/' . $pay->payment_key . '/cancel', [
                    'cancelReason' => '입금 확인 취소',
                ]);

            $pay->update([
                'status'       => $res['status'] ?? 'CANCELED',
                'raw_response' => $res,
            ]);

            /* 결제 요청 줄도 함께 무른다 — 「결제완료」로 남으면 다시 보낼 수 없다 */
            \App\Models\PaymentLink::where('payment_key', $pay->payment_key)
                ->update(['status' => 'cancelled']);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log('카드 승인 취소: ' . number_format((int) $pay->amount) . '원');

            return [['카드전표'], []];
        } catch (\Throwable $e) {
            Log::warning('[입금 확인 취소] 카드 승인 취소 실패', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return [[], ['카드 승인은 취소하지 못했습니다(' . $e->getMessage() . ')']];
        }
    }

    public function checkPaymentStatus(Order $order): JsonResponse
    {
        $tp = $order->tossPayment;
        if (!$tp?->payment_key) {
            return response()->json(['success' => false, 'message' => '발급된 가상계좌가 없습니다.'], 404);
        }

        try {
            $data = $this->vaService->fetchByPaymentKey($tp->payment_key);
            $tp->refresh();

            return response()->json([
                'success'     => true,
                'status'      => $tp->status,
                'status_label'=> $tp->status_label,
                'status_badge'=> $tp->status_badge,
                'deposited_at'=> $tp->deposited_at?->format('Y-m-d H:i'),
            ]);
        } catch (TossApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 가상계좌 안내 SMS 재발송 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function resendVirtualAccountSms(Order $order): JsonResponse
    {
        $tp = $order->tossPayment;
        if (!$tp || !$tp->account_number) {
            return response()->json(['success' => false, 'message' => '발급된 가상계좌가 없습니다.'], 404);
        }
        if ($tp->status === 'DONE') {
            return response()->json(['success' => false, 'message' => '이미 입금 완료된 주문입니다.'], 422);
        }

        $smsSent = $this->sendVirtualAccountSms(
            $order,
            $tp->bank_name,
            $tp->account_number,
            (int) $tp->amount,
            $tp->due_date?->format('Y-m-d H:i')
        );

        return $smsSent
            ? response()->json(['success' => true, 'message' => '안내 SMS를 재발송했습니다.'])
            : response()->json(['success' => false, 'message' => 'SMS 발송에 실패했습니다. 환자 연락처를 확인하세요.'], 422);
    }

    // ─────────────────────────────────────────────────────────────
    // 가상계좌 안내 SMS 발송 (공통 헬퍼)
    // 발송 실패가 발급 자체를 막지 않도록 예외를 흡수하고 bool 반환.
    // ─────────────────────────────────────────────────────────────

    private function sendVirtualAccountSms(
        Order $order,
        ?string $bankName,
        ?string $accountNumber,
        int $amount,
        ?string $dueDate
    ): bool {
        $accountNumber = trim((string) $accountNumber);
        if ($accountNumber === '') {
            Log::warning('[VA] SMS 발송 생략 — 계좌번호 없음', ['order' => $order->order_number]);
            return false;
        }

        $mobile = $order->patient?->mobile
               ?? $order->prescription?->mobile_ocr
               ?? null;
        if (!$mobile) {
            Log::warning('[VA] SMS 발송 생략 — 환자 연락처 없음', ['order' => $order->order_number]);
            return false;
        }

        $patientName = $order->patient?->name ?? '환자';
        $bankName    = trim((string) $bankName);

        try {
            $amountFmt = number_format($amount);
            $lines = [
                "[콜로플라스트] {$patientName}님 본인부담금 입금 안내입니다.",
                "- 주문번호: {$order->order_number}",
                "- 입금계좌: " . trim("{$bankName} {$accountNumber}"),
                "- 입금금액: {$amountFmt}원",
            ];
            if ($dueDate) {
                $lines[] = "- 입금기한: {$dueDate}";
            }
            $lines[] = "기한 내 미입금 시 주문이 취소될 수 있습니다.";

            app(MessageService::class)->send($mobile, implode("\n", $lines), $patientName);

            activity()->causedBy(auth()->user())->performedOn($order)
                ->log("가상계좌 안내 SMS 발송: {$mobile}");

            return true;
        } catch (\Throwable $e) {
            Log::warning('[VA] SMS 발송 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 정산 상태를 옮긴다 (요청서 12쪽, 2026-08-31 회신 A).
     *
     * 마감은 셈을 닫는 것이고 확정은 잠그는 것이다. 확정된 건은 여기로 다시 들어오지
     * 못한다 — 잠갔다는 말이 그 뜻이다. 되돌려야 할 일이 생기면 DB 를 고치는 것이 아니라
     * 왜 되돌리는지를 사람이 남기고 손대야 한다.
     *
     * 못 받은 돈이 있어도 닫을 수 있다(요청서 12쪽 — 「입금 받지 못할 경우에도 마감
     * 확정해야 하는 경우」). 그때는 사유를 받는다.
     */
    public function settle(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::SETTLE_STATUS_LABELS))],
            'reason' => 'nullable|string|max:300',
        ]);

        if ($order->isSettleLocked()) {
            return response()->json([
                'success' => false,
                'message' => '확정된 건입니다 — 되돌리려면 관리자에게 요청하십시오.',
            ], 409);
        }

        /* 받을 돈이 남았는데 닫으려 하면 까닭을 묻는다. 막지는 않는다 — 3PL 샘플로
           입고 잡고 닫는 일이 실제로 있다(요청서 12쪽). 다만 말없이 닫히면 그 건은
           나중에 아무도 못 찾는다. */
        $left = $order->expectedDeposit() - (int) ($order->isDepositConfirmed()
                    ? ($order->deposit_amount ?: $order->expectedDeposit()) : 0);

        if (in_array($data['status'], ['closed', 'confirmed'], true)
            && $left > 0 && blank($data['reason'])) {
            return response()->json([
                'success' => false,
                'message' => '아직 ' . number_format($left) . '원이 남았습니다 — 마감 사유를 입력해 주십시오.',
            ], 422);
        }

        $order->update([
            'settle_status'    => $data['status'],
            'settle_status_at' => now(),
            'settle_status_by' => Auth::id(),
            'settle_reason'    => $data['reason'] ?: $order->settle_reason,
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('정산 상태: ' . $order->settleStatusLabel()
                  . ($data['reason'] ? ' — ' . $data['reason'] : ''));

        return response()->json([
            'success' => true,
            /* 「마감으로」와 「반려로」는 받침에 따라 조사가 갈린다. 「상태로」를 붙여
               그 갈림을 없앤다 — 상태가 늘 수도 있는데 그때마다 조사를 따질 수 없다. */
            'message' => $order->settleStatusLabel() . ' 상태로 옮겼습니다.',
            'label'   => $order->settleStatusLabel(),
        ]);
    }

}
