<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Support\OrderGridExtras;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finance 목록 여섯 (요청서 14~19쪽, 2026-08-31).
 *
 * 재무가 보는 자리다. 담당자가 일하는 화면(주문 관리ㆍ정산/회계)과 다른 것을 묻는다 —
 * 그쪽은 「이 건을 어떻게 처리하나」이고 이쪽은 「이 달에 얼마가 오갔나」다. 그래서
 * 한 줄이 주문 하나이고, 손댈 단추가 없다. 보고 내려받는 자리다.
 *
 * 여섯을 한 화면의 탭으로 두는 까닭 — 묻는 것이 같고(기간), 여는 사람이 같고, 무엇보다
 * 여섯을 오가며 견주어 본다. 메뉴를 여섯으로 늘리면 그때마다 기간을 다시 고른다.
 *
 * 요청서 14쪽의 공통 확인사항 셋을 모두 지킨다 — 엑셀로 내려받고, 주문번호로 서로
 * 이어지고, 기간으로 거른다.
 */
class FinanceController extends Controller
{
    public const TABS = [
        'orders'  => '통합주문내역',
        'patient' => '정산내역 · 환자결제(10%)',
        'agency'  => '정산내역 · 공단ㆍ지자체(90%)',
        'unpaid'  => '미정산내역',
        'returns' => '반품환불내역',
        'vat'     => '부가세신고내역',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'orders';

        $from = $request->get('date_from', today()->startOfMonth()->toDateString());
        $to   = $request->get('date_to',   today()->toDateString());

        [$gridData, $columns] = $tab === 'returns'
            ? $this->returns($from, $to, $request)
            : $this->fromOrders($tab, $from, $to, $request);

        return view('finance.index', [
            'tab'      => $tab,
            'dateFrom' => $from,
            'dateTo'   => $to,
            'gridData' => $gridData,
            'columns'  => $columns,
        ]);
    }

    /**
     * 주문에서 세는 다섯.
     *
     * 기간은 주문일로 본다. 재무가 「이 달 매출」을 물을 때 세는 것이 주문일이라,
     * 출고일이나 입금일로 세면 달을 넘긴 건이 이 달에 끼어든다.
     */
    private function fromOrders(string $tab, string $from, string $to, Request $request): array
    {
        $query = Order::with(['patient', 'prescription.billingOffice', 'items', 'tossPayment'])
            ->whereBetween(\DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($tab === 'unpaid') {
            self::scopeUnpaid($query);
        }

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('order_number', 'like', "%{$kw}%")
                ->orWhere('product_name', 'like', "%{$kw}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows = $query->get();

        $data = $rows->map(fn (Order $o) => $this->orderRow($o))->values();

        return [$data, $this->columnsFor($tab)];
    }

    /**
     * 한 줄에 담기는 것.
     *
     * 탭마다 세우는 칸이 다르지만 값은 한 벌로 만든다 — 탭을 옮길 때마다 같은 값을
     * 다르게 셈하면 여섯이 서로 안 맞는다.
     */
    private function orderRow(Order $o): array
    {
        $copay = (int) $o->patient_copay;
        $nhis  = (int) $o->nhis_amount;

        /* 총 주문금액은 환자 몫과 기관 몫을 더한 것이다.
           orders.total_amount 를 쓰면 안 된다 — 그 칸은 「환자가 낼 돈」이라
           본인부담 + 배송비다(OrderController::store 의 $totalCopay + $shippingFee).
           서른일곱 건 가운데 서른이 그렇게 어긋나 있다. 재무가 매출을 세는 자리에서
           그 값을 쓰면 기관 몫이 통째로 빠진다. */
        $total = $copay + $nhis;

        // 환자에게 청구한 금액 — 배송비가 붙은 그것. 입금과 맞춰 볼 때 이 값이 맞다.
        $billed = (int) ($o->total_amount ?: $copay);

        // 실제로 들어온 돈 — 담당자가 확인했거나 토스가 확인해 준 것
        $paid = $o->isDepositConfirmed()
            ? (int) ($o->deposit_amount ?: $billed)
            : 0;

        // 기관이 준 돈 — 승인된 건만 받은 것으로 본다
        $agencyPaid = $o->nhis_claim_status === 'approved'
            ? (int) ($o->nhis_reimbursement ?: $nhis)
            : 0;

        $shipped = $o->shipped_at ?? $o->delivered_at;

        return [
            'order_no'   => $o->order_number,
            'order_at'   => $o->created_at?->format('Y-m-d') ?? '',
            // 환자 ID — 재무가 같은 이름 두 사람을 가릴 때 쓴다
            'patient_id' => $o->patient_id,
            'patient'    => $o->patient?->name ?? '',
            'code'       => $o->product_code ?? '',
            'product'    => $o->product_name ?? '',
            'qty'        => (int) $o->quantity,
            'total'      => $total,
            // 환자에게 청구한 금액(본인부담 + 배송비) — 입금과 맞춰 볼 때 쓴다
            'billed'     => $billed,
            'copay'      => $copay,
            'nhis'       => $nhis,
            'shipped_at' => $shipped ? \Carbon\Carbon::parse($shipped)->format('Y-m-d') : '',
            'delivered'  => $o->delivered_at?->format('Y-m-d') ?? '',
            'ship_state' => Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
            'tracking'   => $o->tracking_number ?: ($o->withworks_tracking_no ?? ''),
            'status'     => Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
            'cancelled'  => $o->status === 'cancelled' ? '취소' : '',
            'cancel_at'  => $o->status === 'cancelled' ? ($o->updated_at?->format('Y-m-d') ?? '') : '',

            // ── 환자 결제 ─────────────────────────────────
            'paid_at'    => $o->deposit_confirmed_at?->format('Y-m-d')
                            ?? $o->tossPayment?->deposited_at?->format('Y-m-d') ?? '',
            'paid'       => $paid,
            'payer'      => $o->patient?->remitter_name ?: ($o->tossPayment?->customer_name ?? ''),
            'pay_method' => $o->pay_method ? (\App\Models\PaymentLink::METHODS[$o->pay_method] ?? $o->pay_method) : '',
            /* PG 사만 적는다. 정산일ㆍ정산금액ㆍ수수료ㆍ회사계좌 입금은 토스 정산을
               받아 와야 아는 값인데 그 연동이 아직 없다 — 모르는 것을 0 으로 적으면
               「수수료가 없다」로 읽힌다. */
            'pg'         => $o->tossPayment ? '토스페이먼츠' : '',

            // ── 기관 ──────────────────────────────────────
            'agency'     => $o->prescription?->billingOffice?->displayName()
                            ?: (\App\Support\ClaimAgency::LABELS[$o->prescription?->claim_agency ?? ''] ?? ''),
            'claimed'    => $nhis,
            'approved'   => $agencyPaid,
            'agency_at'  => $o->nhis_approved_at?->format('Y-m-d') ?? '',
            'claim_state' => $o->claimStatusLabel(),
            'note'       => $o->nhis_rejection_reason ?? '',

            // ── 미정산 ────────────────────────────────────
            'received'   => $paid + $agencyPaid,
            /* 아직 못 받은 돈. 환자 몫과 기관 몫을 따로 세어 더한다 — 총액에서 받은
               것을 빼면 배송비가 섞여 몇백 원씩 어긋난다. */
            'unpaid'     => max(0, $copay - $paid) + max(0, $nhis - $agencyPaid),
            /* 누가 안 냈는가. 둘 다 안 냈으면 둘 다 적는다 — 하나만 적으면 나머지가
               묻히고, 그 건은 한쪽만 받고 끝난다. */
            'unpaid_of'  => implode(' · ', array_filter([
                                $copay > 0 && $paid <= 0 ? '환자' : null,
                                $nhis  > 0 && $agencyPaid <= 0
                                    ? (($o->prescription?->claim_agency ?? '') === \App\Support\ClaimAgency::LOCAL
                                        ? '지자체' : '공단') : null,
                            ])),
            // 나간 지 며칠 됐는가 — 오래 묵은 건이 눈에 띄어야 손을 쓴다
            'aged'       => $shipped ? (int) \Carbon\Carbon::parse($shipped)->startOfDay()->diffInDays(today()) : '',

            // ── 부가세 ────────────────────────────────────
            /* 공급가액과 부가세는 발행된 것이 있으면 그 값이 맞다 — 신고에 실린 숫자다.
               없으면 총액에서 10% 를 갈라 어림한다. */
            'supply'     => (int) ($o->tax_invoice_supply ?: round($total / 1.1)),
            'vat'        => (int) ($o->tax_invoice_vat ?: $total - round($total / 1.1)),
            'by_card'    => $o->pay_method === 'card' ? $billed : 0,
            'by_cash'    => $o->cash_receipt_status === 'issued' ? (int) $o->cash_receipt_amount : 0,
            'by_tax'     => $o->tax_invoice_status === 'issued'
                                ? (int) ($o->tax_invoice_supply + $o->tax_invoice_vat) : 0,
        ];
    }

    /** 반품환불내역 — 되돌린 건이 원본이라 주문이 아니라 접수에서 센다 */
    private function returns(string $from, string $to, Request $request): array
    {
        $query = OrderReturn::with(['order.patient', 'items'])
            ->whereBetween(\DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('receipt_no', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $data = $query->get()->map(fn (OrderReturn $r) => [
            'order_no'  => $r->order?->order_number ?? '',
            'patient'   => $r->order?->patient?->name ?? '',
            'product'   => $r->items->pluck('product_name')->filter()->implode(', ')
                            ?: ($r->order?->product_name ?? ''),
            'taken_at'  => $r->created_at?->format('Y-m-d') ?? '',
            // 되돌리는 일이 끝난 날 — 아직이면 비어 있고, 그 빈칸이 곧 「진행 중」이다
            'done_at'   => $r->status === 'done' ? ($r->updated_at?->format('Y-m-d') ?? '') : '',
            'qty'       => (int) $r->items->sum('quantity'),
            'amount'    => (int) $r->items->sum(fn ($i) => (int) $i->quantity * (int) $i->unit_price),
            'refund_at' => $r->refunded_at?->format('Y-m-d') ?? '',
            'refund'    => (int) $r->refund_amount,
            'state'     => $r->statusLabel(),
            'reason'    => OrderReturn::reasonLabel($r->reason_code),
        ])->values();

        return [$data, $this->columnsFor('returns')];
    }

    /**
     * 아직 다 받지 못한 건.
     *
     * 환자 몫이 남았거나 기관 몫이 남았거나. 취소된 건은 받을 것이 없어 뺀다.
     */
    public static function scopeUnpaid($query)
    {
        return $query
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('patient_copay', '>', 0)
                    ->whereNull('deposit_confirmed_at')
                    ->whereDoesntHave('tossPayment', fn ($t) => $t->where('status', 'DONE')))
                ->orWhere(fn ($w) => $w->where('nhis_amount', '>', 0)
                    ->where('nhis_claim_status', '!=', 'approved')));
    }

    /**
     * 탭마다 세우는 칸 (요청서 14~19쪽 그대로).
     *
     * 값은 한 벌이고 여기서 고르기만 한다 — 탭을 옮길 때마다 같은 값을 다르게 셈하면
     * 여섯이 서로 안 맞는다.
     */
    private function columnsFor(string $tab): array
    {
        $money = ['align' => 'right', 'editor' => 'number'];

        return match ($tab) {
            // 14쪽 — 전체 주문 현황 및 매출 확인
            'orders' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환자 ID',    'name' => 'patient_id','width' => 80,  'align' => 'center'],
                ['header' => '환자명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '제품코드',   'name' => 'code',      'width' => 110],
                ['header' => '제품명',     'name' => 'product',   'width' => 200],
                ['header' => '주문수량',   'name' => 'qty',       'width' => 90] + $money,
                ['header' => '주문금액',   'name' => 'total',     'width' => 110] + $money,
                ['header' => '환자부담금(10%)', 'name' => 'copay', 'width' => 130] + $money,
                ['header' => '공단/지자체 부담금(90%)', 'name' => 'nhis', 'width' => 170] + $money,
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '배송일자',   'name' => 'delivered', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '배송상태',   'name' => 'ship_state','width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '송장번호',   'name' => 'tracking',  'width' => 130],
                ['header' => '주문상태',   'name' => 'status',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '취소여부',   'name' => 'cancelled', 'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '취소일자',   'name' => 'cancel_at', 'width' => 100, 'align' => 'center'],
            ],

            // 15쪽 — 환자 본인부담금 입금 확인
            'patient' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환자명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                // 환자에게 청구한 금액(본인부담 + 배송비) — 입금과 맞춰 보는 값이다
                ['header' => '주문금액',   'name' => 'billed',    'width' => 110] + $money,
                ['header' => '본인부담액', 'name' => 'copay',     'width' => 110] + $money,
                ['header' => '입금일자',   'name' => 'paid_at',   'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '입금금액',   'name' => 'paid',      'width' => 110] + $money,
                ['header' => '입금자명',   'name' => 'payer',     'width' => 100],
                ['header' => '결제수단',   'name' => 'pay_method','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => 'PG 사',      'name' => 'pg',        'width' => 110, 'align' => 'center'],
            ],

            // 16쪽 — 공단 및 지자체 지급금 관리
            'agency' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환자명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '지급기관명', 'name' => 'agency',    'width' => 180, 'sortable' => true],
                ['header' => '청구금액',   'name' => 'claimed',   'width' => 110] + $money,
                ['header' => '승인금액',   'name' => 'approved',  'width' => 110] + $money,
                ['header' => '입금일자',   'name' => 'agency_at', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '입금금액',   'name' => 'approved',  'width' => 110] + $money,
                ['header' => '청구상태',   'name' => 'claim_state','width' => 90, 'align' => 'center', 'sortable' => true],
                ['header' => '미정산금액', 'name' => 'unpaid',    'width' => 110] + $money,
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '비고',       'name' => 'note',      'width' => 200],
            ],

            // 17쪽 — 미수금 관리
            'unpaid' => [
                ['header' => '주문번호',     'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',     'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환자명',       'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '총 주문금액',  'name' => 'total',     'width' => 110] + $money,
                ['header' => '환자부담금',   'name' => 'copay',     'width' => 110] + $money,
                ['header' => '공단부담금',   'name' => 'nhis',      'width' => 110] + $money,
                ['header' => '실제 수납금액','name' => 'received',  'width' => 120] + $money,
                ['header' => '미정산금액',   'name' => 'unpaid',    'width' => 110] + $money,
                ['header' => '미정산구분',   'name' => 'unpaid_of', 'width' => 120, 'align' => 'center', 'sortable' => true],
                ['header' => '경과일수',     'name' => 'aged',      'width' => 90,  'align' => 'right', 'sortable' => true],
                ['header' => '출고일자',     'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
            ],

            // 18쪽 — 매출 차감 및 환불 관리
            'returns' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '환자명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '제품명',     'name' => 'product',   'width' => 200],
                ['header' => '반품접수일', 'name' => 'taken_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '반품완료일', 'name' => 'done_at',   'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '반품수량',   'name' => 'qty',       'width' => 90] + $money,
                ['header' => '반품금액',   'name' => 'amount',    'width' => 110] + $money,
                ['header' => '환불일자',   'name' => 'refund_at', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환불금액',   'name' => 'refund',    'width' => 110] + $money,
                ['header' => '환불상태',   'name' => 'state',     'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '사유',       'name' => 'reason',    'width' => 120, 'align' => 'center', 'sortable' => true],
            ],

            // 19쪽 — 부가세 신고자료 생성 및 검증
            default => [
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문상태',   'name' => 'status',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '배송일자',   'name' => 'delivered', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '환자명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '제품명',     'name' => 'product',   'width' => 200],
                ['header' => '공급가액',   'name' => 'supply',    'width' => 110] + $money,
                ['header' => '부가세',     'name' => 'vat',       'width' => 100] + $money,
                ['header' => '합계금액',   'name' => 'total',     'width' => 110] + $money,
                ['header' => '카드',       'name' => 'by_card',   'width' => 110] + $money,
                ['header' => '현금영수증', 'name' => 'by_cash',   'width' => 110] + $money,
                ['header' => '세금계산서', 'name' => 'by_tax',    'width' => 110] + $money,
            ],
        };
    }
}
