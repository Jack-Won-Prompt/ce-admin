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
        /* 비율을 이름에 박지 않는다 (2026-09-11 확인요청 1쪽).
           환자 결제 청구용이 아닌 건은 100% 인 경우가 있고, 지자체는 90% 가 아니라
           100% 로 들어오는 일이 있다 — 이름이 늘 맞지는 않는다. */
        'patient' => '정산내역 · 환자결제',
        'agency'  => '정산내역 · 공단ㆍ지자체',
        'unpaid'  => '미정산내역',
        'returns' => '반품환불내역',
        'vat'     => '부가세신고내역',
        /* PG정산내역 — 따로 있던 화면을 이 안으로 들인다 (2026-09-11 확인요청 1쪽).
           안에서 다시 넷으로 갈린다(요약ㆍ건별ㆍ일자별ㆍ결제수단별) — 토스 화면과 같다. */
        'pg'      => 'PG정산내역',
    ];

    /**
     * PG정산내역 안의 갈래.
     *
     * 맨 앞은 여태 따로 있던 PG정산내역 화면을 그대로 들인 것이다 (2026-09-11 지시).
     * 나머지 넷은 토스 화면의 탭 이름을 그대로 쓴다 — 그 화면을 보던 사람이 같은
     * 자리를 찾을 수 있게.
     *
     * 「결제내역」과 「정산」은 다른 것이다. 앞은 받았는가를, 뒤는 수수료를 뗀 얼마가
     * 언제 우리 통장에 들어오는가를 말한다.
     */
    public const PG_VIEWS = [
        'payments' => '결제내역',
        'summary'  => '요약',
        'detail'   => '건별',
        'daily'    => '일자별',
        'method'   => '결제수단별',
    ];

    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'orders';

        $from = $request->get('date_from', today()->startOfMonth()->toDateString());
        $to   = $request->get('date_to',   today()->toDateString());

        [$gridData, $columns] = match (true) {
            $tab === 'pg'      => $this->pgSettlements($from, $to, $request),
            $tab === 'returns' => $this->returns($from, $to, $request),
            default            => $this->fromOrders($tab, $from, $to, $request),
        };

        /* 탭을 누를 때는 화면을 통째로 다시 열지 않는다 (2026-09-10 지시).
           여섯 탭이 묻는 것은 같고(기간ㆍ검색어) 바뀌는 것은 표뿐이라, 값만 주고
           그 자리에서 표를 다시 그린다 — 화면이 깜빡이지 않는다. */
        if ($request->boolean('json')) {
            return response()->json([
                'tab'     => $tab,
                'label'   => self::TABS[$tab],
                'view'    => $this->pgView($request),
                'columns' => $columns,
                'rows'    => $gridData,
                'count'   => count($gridData),
            ]);
        }

        return view('finance.index', [
            'tab'      => $tab,
            'pgView'   => $this->pgView($request),
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

        /* 위드웍스 판매현황과 같은 칸을 뒤에 잇는다 (2026-09-07 지시).
           다른 다섯 목록(주문 관리ㆍ입금 내역ㆍ현금영수증ㆍ청구 관리ㆍ교환반품취소)이
           이미 그 차례를 쓰고 있었는데 Finance 만 제 칸만 세우고 있었다 — 저쪽 화면을
           보다 이리로 넘어오면 눈이 다시 배워야 했다.

           동의는 사람에 붙어 줄마다 물으면 쉰 줄에 백을 묻는다. 한 번에 모아 둔다. */
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('patient_id'));

        $data = $rows->map(fn (Order $o) => $this->orderRow($o)
            + $extras->rx($o->prescription, $o->patient)
            + $extras->ww($o, $o->prescription, $o->patient)
            + $extras->of($o))->values();

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
           본인부담뿐이고, 옛 건에는 배송비까지 섞여 있다. 재무가 매출을 세는
           자리에서 그 값을 쓰면 기관 몫이 통째로 빠진다.
           배송비는 이제 없다(2026-09-03 확정) — 옛 스물여섯 건에만 남아 있다. */
        $total = $copay + $nhis;

        // 환자에게 청구한 금액. 입금과 맞춰 볼 때 이 값이 맞다(옛 건은 배송비가 섞여 있다).
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
            // 고객ID — 재무가 같은 이름 두 사람을 가릴 때 쓴다
            'patient_id' => $o->patient_id,
            'patient'    => $o->patient?->name ?? '',
            'code'       => $o->product_code ?? '',
            'product'    => $o->product_name ?? '',
            'qty'        => (int) $o->quantity,
            'total'      => $total,
            // 환자에게 청구한 금액 — 입금과 맞춰 볼 때 쓴다
            'billed'     => $billed,
            'copay'      => $copay,
            'nhis'       => $nhis,
            'shipped_at' => $shipped ? \Carbon\Carbon::parse($shipped)->format('Y-m-d') : '',
            'delivered'  => $o->delivered_at?->format('Y-m-d') ?? '',
            'ship_state' => $o->status_label,
            'tracking'   => $o->tracking_number ?: ($o->withworks_tracking_no ?? ''),
            'status'     => $o->status_label,
            'cancelled'  => $o->status === 'cancelled' ? '취소' : '',
            'cancel_at'  => $o->status === 'cancelled' ? ($o->updated_at?->format('Y-m-d') ?? '') : '',

            // ── 환자 결제 ─────────────────────────────────
            /* 언제 받았는가 — 한 곳에서 센다(Order::paidAt · 2026-09-10 지시).

               여태 담당자가 확인한 날짜와 가상계좌 입금일만 보아, 토스로 카드ㆍ
               간편결제를 받은 건은 이 칸이 빈 채로 섰다. 승인 시각이 곧 결제 시각이다.

               날짜와 시각을 따로 담는다 — 「입금일자」는 날짜로 세는 자리라 그대로 두고,
               시각은 제 칸에서 본다. */
            'paid_at'    => $o->paidAt()?->format('Y-m-d') ?? '',
            'paid_time'  => $o->paidAtLabel('Y-m-d H:i:s'),
            'paid'       => $paid,
            'payer'      => $o->patient?->remitter_name ?: ($o->tossPayment?->customer_name ?? ''),
            /* 토스가 알려 준 실제 유형이 있으면 그것이 사실이다 — 「링크페이」는 우리가
               무엇으로 안내했는가일 뿐이다(2026-09-09 지시) */
            'pay_method' => ($o->pay_method || $o->tossPayment) ? $o->payMethodLabel() : '',
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
            // 정산이 어디까지 갔는가(요청서 12쪽) — 마감 → 확정
            'settle'     => $o->settleStatusLabel(),
            'settle_reason' => $o->settle_reason ?? '',
            'note'       => $o->nhis_rejection_reason ?? '',

            // ── 미정산 ────────────────────────────────────
            'received'   => $paid + $agencyPaid,
            /* 아직 못 받은 돈. 환자 몫과 기관 몫을 따로 세어 더한다 — 총액에서 받은
               것을 빼면 옛 건의 배송비가 섞여 몇천 원씩 어긋난다. */
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
        $query = OrderReturn::with(['order.patient', 'order.prescription.billingOffice', 'order.items', 'items'])
            ->whereBetween(\DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('receipt_no', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows   = $query->get();
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('order.patient_id'));

        $data = $rows->map(fn (OrderReturn $r) => [
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
        ]
            /* 되돌린 건도 원 주문의 위드웍스 칸을 함께 세운다 — 어느 판매가
               되돌아온 것인지 그 자리에서 읽힌다. 주문이 없으면 빈 칸이 선다. */
            + ($r->order
                ? $extras->rx($r->order->prescription, $r->order->patient)
                  + $extras->ww($r->order, $r->order->prescription, $r->order->patient, $r)
                  + $extras->of($r->order)
                : []))->values();

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
    /** 지금 보는 PG 갈래 */
    private function pgView(Request $request): string
    {
        $v = (string) $request->get('view', 'payments');

        return array_key_exists($v, self::PG_VIEWS) ? $v : 'payments';
    }

    /**
     * PG정산내역 — 토스에서 받아 네 갈래로 보여 준다 (2026-09-11 확인요청 1ㆍ6ㆍ7쪽).
     *
     * 저쪽이 주는 것은 건별 한 벌뿐이라, 요약ㆍ일자별ㆍ결제수단별은 그것을 묶어 만든다.
     * 기간은 다른 탭과 같은 칸을 쓴다 — 묻는 것이 같은데 칸을 따로 두면 번갈아 볼 때
     * 기간을 두 번 맞춰야 한다.
     */
    private function pgSettlements(string $from, string $to, Request $request): array
    {
        $갈래 = $this->pgView($request);

        if ($갈래 === 'payments') {
            return $this->pgPayments($from, $to, $request);
        }

        $기준 = $request->get('date_type') === 'paidOutDate' ? 'paidOutDate' : 'soldDate';

        $정산 = app(\App\Services\TossPayments\SettlementService::class);
        $줄들 = $정산->가져오기($from, $to, $기준);

        /* 검색어는 건별에서만 뜻이 있다 — 묶어 놓은 줄에는 주문번호가 없다 */
        if ($갈래 === 'detail' && $request->filled('q')) {
            $말 = mb_strtolower($request->get('q'));
            $줄들 = array_values(array_filter($줄들, fn ($s) => str_contains(
                mb_strtolower(($s['orderId'] ?? '') . ' ' . ($s['method'] ?? '') . ' ' . ($s['mId'] ?? '')), $말)));
        }

        $자료 = match ($갈래) {
            'detail' => $정산->건별($줄들),
            'daily'  => $정산->일자별($줄들),
            'method' => $정산->결제수단별($줄들),
            default  => $정산->요약($줄들),
        };

        return [$자료, $this->columnsFor('pg:' . $갈래)];
    }

    /**
     * 결제내역 — 여태 따로 있던 PG정산내역 화면을 그대로 들인다 (2026-09-11 지시).
     *
     * 가상계좌를 발급하고 입금을 기다리는 자리라, 정산과 함께 보아야 「받았는데
     * 아직 안 들어온 돈」이 한 화면에서 읽힌다.
     */
    private function pgPayments(string $from, string $to, Request $request): array
    {
        $질의 = \App\Models\TossPayment::with(['order.patient'])
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $말 = $request->get('q');
            $질의->where(fn ($s) => $s
                ->where('toss_order_id', 'like', "%{$말}%")
                ->orWhere('customer_name', 'like', "%{$말}%")
                ->orWhere('account_number', 'like', "%{$말}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$말}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$말}%")));
        }

        $자료 = $질의->get()->map(fn (\App\Models\TossPayment $t) => [
            'issued_at' => $t->created_at?->format('Y-m-d H:i') ?? '',
            'order_no'  => $t->order?->order_number ?? '',
            'patient'   => $t->order?->patient?->name ?? '',
            'method'    => $t->method_label,
            'status'    => $t->status_label,
            'amount'    => (int) $t->amount,
            // 가상계좌로 받은 건만 값이 선다 — 카드는 계좌가 없다
            'bank'      => $t->bank_name,
            'account'   => $t->account_number ?? '',
            'holder'    => $t->customer_name ?? '',
            'due_date'  => $t->due_date?->format('Y-m-d') ?? '',
            'paid_at'   => $t->deposited_at?->format('Y-m-d H:i') ?? '',
            'toss_no'   => $t->toss_order_id ?? '',
        ])->values()->all();

        return [$자료, $this->columnsFor('pg:payments')];
    }

    private function columnsFor(string $tab): array
    {
        $money = ['align' => 'right', 'editor' => 'number'];

        /* PG정산내역 — 토스 화면의 칸 이름을 그대로 쓴다 (2026-09-11 확인요청 6ㆍ7쪽).
           수수료는 공급가와 부가세를 갈라 보여 준다 — 세금계산서와 맞출 때 그 둘이 필요하다. */
        $pg공통 = [
            ['header' => '건수',     'name' => '건수',     'width' => 80]  + $money,
            ['header' => '매출액',   'name' => '매출액',   'width' => 120] + $money,
            ['header' => 'PG수수료', 'name' => 'PG수수료', 'width' => 110] + $money,
            ['header' => 'PG부가세', 'name' => 'PG부가세', 'width' => 110] + $money,
            ['header' => '수수료 합', 'name' => '수수료합', 'width' => 110] + $money,
        ];

        return match ($tab) {
            'pg:payments' => [
                ['header' => '발급일시',     'name' => 'issued_at', 'width' => 140, 'sortable' => true],
                ['header' => '주문번호',     'name' => 'order_no',  'width' => 130, 'sortable' => true],
                ['header' => '이름',         'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '결제수단',     'name' => 'method',    'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '상태',         'name' => 'status',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '금액',         'name' => 'amount',    'width' => 110] + $money,
                ['header' => '가상계좌은행', 'name' => 'bank',      'width' => 110],
                ['header' => '가상계좌번호', 'name' => 'account',   'width' => 160],
                ['header' => '예금주명',     'name' => 'holder',    'width' => 100],
                ['header' => '입금기한',     'name' => 'due_date',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '입금일시',     'name' => 'paid_at',   'width' => 140, 'sortable' => true],
                ['header' => '토스 주문번호', 'name' => 'toss_no',  'width' => 180],
            ],

            'pg:summary' => array_merge($pg공통, [
                ['header' => '입금 정산액', 'name' => '입금정산액', 'width' => 130] + $money,
            ]),

            'pg:detail' => [
                ['header' => '매출일',     'name' => '매출일',   'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '정산액 입금일', 'name' => '입금일', 'width' => 110, 'align' => 'center', 'sortable' => true],
                ['header' => '승인일시',   'name' => '승인일시', 'width' => 130, 'align' => 'center', 'sortable' => true],
                ['header' => '결제수단',   'name' => '결제수단', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '주문번호',   'name' => '주문번호', 'width' => 190, 'sortable' => true],
                ['header' => '상점아이디(MID)', 'name' => '상점아이디', 'width' => 130],
                ['header' => '매출액',     'name' => '매출액',   'width' => 110] + $money,
                ['header' => 'PG수수료',   'name' => 'PG수수료', 'width' => 100] + $money,
                ['header' => 'PG부가세',   'name' => 'PG부가세', 'width' => 100] + $money,
                ['header' => '수수료 합',   'name' => '수수료합', 'width' => 100] + $money,
                ['header' => '정산액',     'name' => '정산액',   'width' => 110] + $money,
            ],

            'pg:daily' => array_merge(
                [['header' => '매출일', 'name' => '매출일', 'width' => 120, 'align' => 'center', 'sortable' => true]],
                $pg공통,
                [['header' => '정산액', 'name' => '정산액', 'width' => 130] + $money],
            ),

            'pg:method' => array_merge(
                [['header' => '결제수단', 'name' => '결제수단', 'width' => 140, 'align' => 'center', 'sortable' => true]],
                $pg공통,
                [['header' => '정산액', 'name' => '정산액', 'width' => 130] + $money],
            ),

            // 14쪽 — 전체 주문 현황 및 매출 확인
            'orders' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '고객ID',     'name' => 'patient_id','width' => 80,  'align' => 'center'],
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
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
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
                // 환자에게 청구한 금액 — 입금과 맞춰 보는 값이다
                ['header' => '주문금액',   'name' => 'billed',    'width' => 110] + $money,
                ['header' => '본인부담액', 'name' => 'copay',     'width' => 110] + $money,
                ['header' => '입금일자',   'name' => 'paid_at',   'width' => 100, 'align' => 'center', 'sortable' => true],
                /* 결제 시각 — 날짜만으로는 같은 날 두 번 오간 건을 가릴 수 없다(2026-09-10 지시) */
                ['header' => '결제 시각',  'name' => 'paid_time', 'width' => 150, 'align' => 'center', 'sortable' => true],
                ['header' => '입금금액',   'name' => 'paid',      'width' => 110] + $money,
                ['header' => '입금자명',   'name' => 'payer',     'width' => 100],
                ['header' => '결제수단',   'name' => 'pay_method','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '정산상태',   'name' => 'settle',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => 'PG 사',      'name' => 'pg',        'width' => 110, 'align' => 'center'],
            ],

            // 16쪽 — 공단 및 지자체 지급금 관리
            'agency' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '지급기관명', 'name' => 'agency',    'width' => 180, 'sortable' => true],
                ['header' => '청구금액',   'name' => 'claimed',   'width' => 110] + $money,
                ['header' => '승인금액',   'name' => 'approved',  'width' => 110] + $money,
                ['header' => '입금일자',   'name' => 'agency_at', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '입금금액',   'name' => 'approved',  'width' => 110] + $money,
                ['header' => '청구상태',   'name' => 'claim_state','width' => 90, 'align' => 'center', 'sortable' => true],
                ['header' => '정산상태',   'name' => 'settle',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '미정산금액', 'name' => 'unpaid',    'width' => 110] + $money,
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '비고',       'name' => 'note',      'width' => 200],
            ],

            // 17쪽 — 미수금 관리
            'unpaid' => [
                ['header' => '주문번호',     'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',     'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '거래처명',     'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '총 주문금액',  'name' => 'total',     'width' => 110] + $money,
                ['header' => '환자부담금',   'name' => 'copay',     'width' => 110] + $money,
                ['header' => '공단부담금',   'name' => 'nhis',      'width' => 110] + $money,
                ['header' => '실제 수납금액','name' => 'received',  'width' => 120] + $money,
                ['header' => '미정산금액',   'name' => 'unpaid',    'width' => 110] + $money,
                ['header' => '미정산구분',   'name' => 'unpaid_of', 'width' => 120, 'align' => 'center', 'sortable' => true],
                ['header' => '경과일수',     'name' => 'aged',      'width' => 90,  'align' => 'right', 'sortable' => true],
                // 조치상태 — 정산 상태가 그 자리다(요청서 12쪽의 마감ㆍ확정ㆍ반려ㆍ보류ㆍ취소)
                ['header' => '입금 상태',    'name' => 'settle',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '사유',        'name' => 'settle_reason', 'width' => 200],
                ['header' => '출고일자',     'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
            ],

            // 18쪽 — 매출 차감 및 환불 관리
            'returns' => [
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
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
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
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
