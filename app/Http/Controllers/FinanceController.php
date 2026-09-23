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
                // 감출 머리글도 함께 — 탭은 화면을 다시 열지 않으므로 여기서 주지 않으면 그대로 보인다
                'hidden'  => self::숨길칸($tab),
                'rows'    => $gridData,
                'count'   => count($gridData),
                // PG 탭의 결제수단 고르개를 그 자리에서 채운다 — 탭은 화면을 다시 열지 않는다
                'pay_methods' => $this->pg수단들,
            ]);
        }

        return view('finance.index', [
            'tab'      => $tab,
            'pgView'   => $this->pgView($request),
            // PG 탭의 결제수단 고르개 — 받아 온 줄에 실제로 있는 것만 세운다
            'pg수단들' => $this->pg수단들,
            'dateFrom' => $from,
            'dateTo'   => $to,
            'gridData' => $gridData,
            'columns'  => $columns,
            'hidden'   => self::숨길칸($tab),
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

        /* PG 정산은 환자결제ㆍ미정산 두 탭에서만 쓴다 — 나머지 탭까지 토스를 부르면
           그만큼 느려지기만 한다 (2026-09-15 지시) */
        $this->pg정산 = in_array($tab, ['patient', 'unpaid'], true)
            ? $this->pg정산색인($from, $to, $rows)
            : [];

        /* 위드웍스 판매현황과 같은 칸을 뒤에 잇는다 (2026-09-07 지시).
           다른 다섯 목록(주문 관리ㆍ입금 내역ㆍ현금영수증ㆍ청구 관리ㆍ교환반품취소)이
           이미 그 차례를 쓰고 있었는데 Finance 만 제 칸만 세우고 있었다 — 저쪽 화면을
           보다 이리로 넘어오면 눈이 다시 배워야 했다.

           동의는 사람에 붙어 줄마다 물으면 쉰 줄에 백을 묻는다. 한 번에 모아 둔다. */
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('patient_id'));

        /* 정정 이력 — 고치기 전 줄과 그것을 무른 줄 (2026-09-22 확인요청 4쪽).

           정정은 주문을 제자리에서 고쳐, 표에는 마지막 내용 한 줄만 섰다. 「원 주문
           라인 / 취소 라인 / 정정 라인」 셋이 보여야 재무가 얼마가 오갔는지 셀 수
           있다. 고치기 전 값은 order_amendments 에 남는다. */
        $정정 = $this->amendRows($rows);

        /* 정정 줄은 제 주문 바로 뒤에 세운다 — 표를 훑을 때 셋이 붙어 있어야 읽힌다.
           차례는 「정정 후(지금) · 원 주문 · 취소」다. 목록이 최신 먼저라 지금 값이
           맨 위에 서고, 그 아래로 물러난 것들이 따라온다. */
        $data = $rows->flatMap(fn (Order $o) => array_merge(
            [[
                // 정정한 적이 있으면 이 줄이 「정정 후」다 — 무엇을 보고 있는지 밝힌다
                'kind'   => isset($정정[$o->id]) ? '정정 후' : '주문',
                'reason' => '',
            ]
            + $this->orderRow($o)
            + $extras->rx($o->prescription, $o->patient)
            + $extras->ww($o, $o->prescription, $o->patient)
            + $extras->of($o)],
            $정정[$o->id] ?? [],
        ))->values();

        /* 통합주문내역은 갈래를 가리지 않고 모두 담는다 (2026-09-11 확인요청 8쪽).
           환자 결제ㆍ정산ㆍ미정산은 주문 줄에서 이미 보이는데 반품환불만 제 탭에
           따로 서 있었다 — 「이 달에 무슨 일이 있었나」를 한 표에서 보려면 그것도
           여기 있어야 한다. */
        if ($tab === 'orders') {
            $data = $data->concat($this->returnRowsForOrders($from, $to, $request))->values();
        }

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
            /* 제품을 아직 고르지 않은 줄 (2026-09-22 확인요청 1쪽).

               「통합주문내역에 제품코드 추가」라는 요청이었는데, 칸은 진작 서 있었고
               **값이 비어 있던 것**이었다 — 운영 주문 여든두 건 가운데 스무 건이
               그랬다(2026-09-22 서버 확인). 그 스물은 모두 품목 줄이 아예 없는 건,
               곧 담당자가 유형만 고르고 제품은 아직 안 고른 작성 중인 건이다.
               채울 코드가 없다.

               빈칸으로 두면 「값이 빠졌다」로 읽히고, 재무는 어디서 새어 나갔는지
               찾는다. 없는 것이 아니라 **아직 고르지 않은 것**이라고 적는다.
               제품명 자리에 들어 있는 「-」는 OrderSync 가 세워 둔 표시다. */
            'code'       => ($o->product_code ?? '') !== '' ? $o->product_code : '미선택',
            'product'    => in_array(trim((string) $o->product_name), ['', '-'], true)
                                ? '미선택' : $o->product_name,
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
            /* 입금확인 — 다른 아홉 목록과 같은 잣대다 (2026-09-20 지시).

               「결제 시 모든 화면에서 결제수단, 입금확인, 입금 금액, 결제 시각을
               필수로 확인」한다. 이 탭에만 입금확인 칸이 없어 넷 가운데 하나가
               비어 있었다. 잣대는 ceMoneyCols 를 채우는 OrderGridExtras 와 같다 —
               받았으면 그 날, 받을 돈이 애초에 없으면 「본인부담 없음」이다. */
            'deposit_at' => match (true) {
                                $o->isDepositConfirmed() =>
                                    ($o->deposit_confirmed_at ?? $o->paidAt())?->format('Y-m-d') ?? '입금완료',
                                $o->expectedDeposit() === 0 && $nhis > 0 => '본인부담 없음',
                                default                  => '',
                            },
            'payer'      => $o->patient?->remitter_name ?: ($o->tossPayment?->customer_name ?? ''),
            /* 공단ㆍ지자체가 통장에 찍는 이름 (2026-09-11 엑셀 · 2026-09-15 지시).
               공단은 「NB + 주민번호 앞 여섯 자리」라는 규칙이 있다. 지자체는 정해진
               것이 없어 기관마다 다르므로 **지어내지 않고 빈칸으로 둔다.** */
            'agency_payer' => self::nbPayer($o),
            /* PG 정산 — 토스가 알려 준 값이다. 이 표를 그릴 때 한 번에 받아 둔다
               (pgSettleIndex). 정산 줄이 아직 없으면 빈칸이다 — 없는 것과 0 은 다르다. */
            'pg_fee'       => $this->pg정산[$o->id]['fee']    ?? null,
            'pg_payout'    => $this->pg정산[$o->id]['payout'] ?? null,
            'pg_payout_at' => $this->pg정산[$o->id]['date']   ?? '',
            /* 결제수단은 OrderGridExtras::of() 가 적는다 — 여기서도 적으면 배열을 더할 때
               왼쪽이 이겨 이 값이 남고, 열 화면 가운데 이 탭만 잣대가 달라진다
               (2026-09-14 지시로 「실제로 있었던 일」만 적도록 바뀌었다). */
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
            /* 실제로 들어온 돈. 지금은 승인액과 같은 값이다 — 부분 지급을 따로 적어
               두는 자리가 아직 없다(공단 청구 결과 등록이 승인액 하나만 받는다).
               그래도 칸은 갈라 둔다. 같은 이름을 두 칸이 쓰면 표가 둘을 한 칸으로
               본다(위 columnsFor 의 주석 참고). */
            'agency_paid' => $agencyPaid,
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
    /**
     * 반품환불을 통합주문내역의 줄 꼴로 (2026-09-11 확인요청 8쪽).
     *
     * 수량과 금액은 **음수로 적는다**. 되돌린 것이라 더하면 그대로 상계된다 —
     * 표 아래 합계가 곧 「이 달에 남은 것」이 된다.
     *
     * 결제ㆍ청구 칸은 비운다. 반품에는 그 값이 없고, 0 을 적으면 「받지 못했다」로
     * 읽힌다.
     */
    /**
     * 정정 줄 — 주문 하나마다 「원 주문」과 「취소」 두 줄 (2026-09-22 확인요청 4쪽).
     *
     * 정정은 주문을 제자리에서 고친다. 그래서 표에는 마지막 내용 한 줄만 섰고,
     * 재무는 원래 얼마였는지, 얼마가 물러났는지를 볼 수 없었다.
     *
     *   원 주문   고치기 전 값 그대로 (+)
     *   취소      같은 값을 음수로 (−) — 그만큼이 물러났다
     *   정정 후   주문 자체 (fromOrders 가 세운다)
     *
     * 셋을 더하면 지금 금액이 남는다 — 표 아래 합계가 흐트러지지 않는다.
     *
     * 거듭 정정한 건은 차례마다 두 줄이 선다. 2차 정정의 「원 주문」은 1차 정정의
     * 결과이므로, 그렇게 세워야 오간 것이 빠짐없이 남는다.
     *
     * 결제ㆍ청구 칸은 비운다. 물러난 줄에는 그 값이 없고, 0 을 적으면 「받지
     * 못했다」로 읽힌다 — 반품 줄과 같은 규칙이다.
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     * @return array<int, array<int, array<string,mixed>>>  주문 id => 줄들
     */
    private function amendRows($orders): array
    {
        if ($orders->isEmpty() || ! \Illuminate\Support\Facades\Schema::hasTable('order_amendments')) {
            return [];
        }

        $이력 = \App\Models\OrderAmendment::whereIn('order_id', $orders->pluck('id'))
            ->orderByDesc('seq')
            ->get();

        if ($이력->isEmpty()) {
            return [];
        }

        $주문 = $orders->keyBy('id');
        $out  = [];

        foreach ($이력 as $a) {
            $o    = $주문->get($a->order_id);
            $금액 = (int) $a->patient_copay + (int) $a->nhis_amount;
            $날   = $a->amended_at?->format('Y-m-d') ?? '';

            $바탕 = [
                'reason'     => $a->reason ?? '',
                'order_no'   => $o?->order_number ?? '',
                'order_at'   => $o?->created_at?->format('Y-m-d') ?? '',
                'patient_id' => $o?->patient_id,
                'patient'    => $o?->patient?->name ?? '',
                'code'       => $a->product_code ?? '',
                'product'    => $a->product_name ?? '',
                'shipped_at' => '',
                'delivered'  => '',
                'tracking'   => '',
                // 어느 판매주문의 줄이었는가 — 창고 화면과 맞춰 보는 자리다
                'ww_so_no'   => $a->withworks_so_no ?? '',
            ];

            // 아래에서 위로 쌓으므로 취소를 먼저 넣는다 — 화면에는 원 주문이 먼저 선다
            $out[$a->order_id][] = $바탕 + [
                'kind'       => "정정 {$a->seq}차 · 원 주문",
                'qty'        => (int) $a->quantity,
                'total'      => $금액,
                'billed'     => (int) $a->total_amount,
                'copay'      => (int) $a->patient_copay,
                'nhis'       => (int) $a->nhis_amount,
                'ship_state' => '',
                'status'     => '정정 전',
                'cancelled'  => '',
                'cancel_at'  => '',
                'paid_at'    => '',
                'paid'       => 0,
            ];

            $out[$a->order_id][] = $바탕 + [
                'kind'       => "정정 {$a->seq}차 · 취소",
                'qty'        => -(int) $a->quantity,
                'total'      => -$금액,
                'billed'     => -(int) $a->total_amount,
                'copay'      => -(int) $a->patient_copay,
                'nhis'       => -(int) $a->nhis_amount,
                'ship_state' => '',
                'status'     => '정정 취소',
                'cancelled'  => '취소',
                'cancel_at'  => $날,
                'paid_at'    => '',
                'paid'       => 0,
            ];
        }

        return $out;
    }

    private function returnRowsForOrders(string $from, string $to, Request $request)
    {
        $질의 = OrderReturn::with(['order.patient', 'items'])
            ->whereBetween(\DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $말 = $request->q;
            $질의->where(fn ($s) => $s
                ->whereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$말}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$말}%")));
        }

        return $질의->get()->map(function (OrderReturn $r) {
            $수량 = (int) $r->items->sum('quantity');
            $금액 = (int) $r->items->sum(fn ($i) => (int) $i->quantity * (int) $i->unit_price);
            $환불 = (int) $r->refund_amount;

            return [
                'kind'       => '반품환불',
                'reason'     => OrderReturn::reasonLabel($r->reason_code),
                'order_no'   => $r->order?->order_number ?? '',
                'order_at'   => $r->created_at?->format('Y-m-d') ?? '',
                'patient_id' => $r->order?->patient_id,
                'patient'    => $r->order?->patient?->name ?? '',
                'code'       => '',
                'product'    => $r->items->pluck('product_name')->filter()->implode(', '),
                // 되돌린 것이므로 음수다
                'qty'        => -$수량,
                'total'      => -$금액,
                'billed'     => -$환불,
                'copay'      => -$환불,
                'nhis'       => 0,
                'shipped_at' => '',
                'delivered'  => '',
                'ship_state' => $r->statusLabel(),
                'tracking'   => '',
                'status'     => $r->statusLabel(),
                'cancelled'  => '',
                'cancel_at'  => '',
                'paid_at'    => $r->refunded_at?->format('Y-m-d') ?? '',
                'paid'       => -$환불,
            ];
        });
    }

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

        /* 고르개에 세울 결제수단은 **거르기 전** 줄에서 뽑는다 — 걸러 낸 뒤에 뽑으면
           한 번 고른 순간 나머지 수단이 목록에서 사라져 되돌아갈 길이 없다. */
        $this->pg수단들 = $정산->수단들($줄들);

        // 토스 화면이 묻는 것 가운데 정산 응답으로 가릴 수 있는 셋 (2026-09-14 확인요청 1쪽)
        $줄들 = $정산->거르기($줄들, [
            'method'     => $request->get('pay_method'),
            'pay_status' => $request->get('pay_status'),
            'mid'        => $request->get('mid'),
        ]);

        /* 검색어는 건별에서만 뜻이 있다 — 묶어 놓은 줄에는 주문번호가 없다.
           우리 주문번호ㆍ이름으로도 찾는다(2026-09-14) — 담당자가 아는 말은 그쪽이다. */
        $우리것 = $갈래 === 'detail' ? $this->pg우리주문($줄들) : [];

        if ($갈래 === 'detail' && $request->filled('q')) {
            $말 = mb_strtolower($request->get('q'));
            $줄들 = array_values(array_filter($줄들, function ($s) use ($말, $우리것) {
                $우리 = $우리것[(string) ($s['orderId'] ?? '')] ?? [];
                $건초 = ($s['orderId'] ?? '') . ' ' . ($s['method'] ?? '') . ' ' . ($s['mId'] ?? '')
                      . ' ' . ($s['transactionKey'] ?? '')
                      . ' ' . ($우리['order_no'] ?? '') . ' ' . ($우리['patient'] ?? '');

                return str_contains(mb_strtolower($건초), $말);
            }));
        }

        $자료 = match ($갈래) {
            'detail' => $정산->건별($줄들, $우리것),
            'daily'  => $정산->일자별($줄들),
            'method' => $정산->결제수단별($줄들),
            default  => $정산->요약($줄들),
        };

        return [$자료, $this->columnsFor('pg:' . $갈래)];
    }

    /** 화면이 세울 결제수단 고르개 — pgSettlements 가 채운다 */
    private array $pg수단들 = [];

    /** 주문 id => 토스 정산(수수료ㆍ정산액ㆍ정산 입금일). 환자결제ㆍ미정산 탭이 쓴다 */
    private array $pg정산 = [];

    public function pg수단목록(): array
    {
        return $this->pg수단들;
    }

    /**
     * 정산 줄에 우리 주문을 잇는다 (2026-09-14 확인요청 1쪽).
     *
     * 토스는 구매자명도 구매상품도 주지 않는다. 그런데 정산 줄의 orderId 는 우리가
     * 토스에 보낸 주문 아이디 그대로라, 그것으로 결제 줄을 타고 주문까지 갈 수 있다.
     * 담당자는 이 표에서 「누구의 무엇인가」를 늘 따로 찾아보고 있었다.
     *
     * @return array<string, array{order_no:string, patient:string, product:string}>
     */
    private function pg우리주문(array $줄들): array
    {
        $아이디 = array_values(array_filter(array_map(
            fn ($s) => (string) ($s['orderId'] ?? ''), $줄들)));

        if (! $아이디) {
            return [];
        }

        $표 = [];

        foreach (\App\Models\TossPayment::with('order.patient')
                    ->whereIn('toss_order_id', $아이디)->get() as $t) {
            $표[(string) $t->toss_order_id] = [
                'order_no' => $t->order?->order_number ?? '',
                'patient'  => $t->order?->patient?->name ?? ($t->customer_name ?? ''),
                'product'  => $t->order?->product_name ?? '',
            ];
        }

        /* 결제 줄이 없는 건은 결제 링크에서 되짚는다 — 링크로 받은 건은 그쪽에 남는다 */
        foreach (\App\Models\PaymentLink::with('order.patient')
                    ->whereIn('toss_order_id', $아이디)->get() as $l) {
            $키 = (string) $l->toss_order_id;
            if (isset($표[$키])) {
                continue;
            }
            $표[$키] = [
                'order_no' => $l->order?->order_number ?? '',
                'patient'  => $l->order?->patient?->name ?? '',
                'product'  => $l->order?->product_name ?? '',
            ];
        }

        return $표;
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

    /**
     * 공단이 통장에 찍는 입금자명 — 「NB + 주민번호 앞 여섯 자리」.
     *
     * 2026-09-11 엑셀의 칸 설명이 그대로 규칙이다.
     *   「NB주민번호 => EX)NB801234 : 건보 경우, NB주민번호앞 6자리로 입금.
     *     지자체는 정해진거 없이 각각 입금명 다름」
     *
     * 지자체는 **빈칸으로 둔다.** 규칙이 없는데 무엇이든 적어 두면 담당자가 그것과
     * 통장을 맞추려 든다 — 맞을 리가 없고, 안 맞는 까닭도 알 수 없다.
     */
    private static function nbPayer(?Order $o): string
    {
        if (! $o || ($o->prescription?->claim_agency ?? '') !== \App\Support\ClaimAgency::NHIS) {
            return '';
        }

        /* 가린 값에서 앞 여섯 자리만 읽는다 — 복호화할 까닭이 없다 */
        $가린것 = $o->prescription?->resident_no_ocr_masked
               ?? \App\Support\ResidentNo::mask($o->patient?->resident_no ?? null);

        $앞여섯 = substr(preg_replace('/\D/', '', (string) $가린것), 0, 6);

        return strlen($앞여섯) === 6 ? 'NB' . $앞여섯 : '';
    }

    /**
     * 토스 정산에서 주문마다 수수료ㆍ정산액ㆍ정산 입금일을 끌어온다 (2026-09-15 지시).
     *
     * 2026-09-11 엑셀에 이 셋이 있는데 화면에는 없었다. 그때는 토스 정산 연동이
     * 없어 「받아 와야 아는 값」이라 비워 두었는데, 그 연동이 들어왔다.
     *
     * 이어 붙이는 열쇠는 정산 줄의 orderId 다 — 우리가 토스에 보낸 주문 아이디
     * 그대로라, 결제 줄(TossPaymentㆍPaymentLink)을 타고 주문까지 간다.
     *
     * 매출일(soldDate)로 받는다. 정산 입금은 며칠 뒤지만 매출일은 판 날 그대로라,
     * 화면이 보고 있는 기간과 같은 창으로 물으면 빠지는 줄이 없다.
     *
     * @return array<int, array{fee:int, payout:int, date:string}>
     */
    private function pg정산색인(string $from, string $to, \Illuminate\Support\Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        try {
            $줄들 = app(\App\Services\TossPayments\SettlementService::class)
                        ->가져오기(str_replace('-', '', $from), str_replace('-', '', $to), 'soldDate');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Finance] 토스 정산을 받지 못했습니다',
                ['error' => $e->getMessage()]);

            return [];
        }

        if (! $줄들) {
            return [];
        }

        /* 토스 주문 아이디 → 우리 주문 id. 두 표를 모두 본다 — 가상계좌는 결제 줄에,
           링크로 받은 건은 결제 링크에 남는다. */
        $ids = $orders->pluck('id');
        $짝  = [];

        foreach (\App\Models\TossPayment::whereIn('order_id', $ids)
                    ->whereNotNull('toss_order_id')->get(['order_id', 'toss_order_id']) as $t) {
            $짝[(string) $t->toss_order_id] = (int) $t->order_id;
        }
        foreach (\App\Models\PaymentLink::whereIn('order_id', $ids)
                    ->whereNotNull('toss_order_id')->get(['order_id', 'toss_order_id']) as $l) {
            $짝[(string) $l->toss_order_id] ??= (int) $l->order_id;
        }

        $표 = [];

        foreach ($줄들 as $줄) {
            $oid = $짝[(string) ($줄['orderId'] ?? '')] ?? null;
            if ($oid === null) {
                continue;
            }

            /* 한 주문에 정산 줄이 여럿일 수 있다(부분취소 따위) — 더한다 */
            $표[$oid]['fee']    = ($표[$oid]['fee']    ?? 0) + (int) ($줄['fee'] ?? 0);
            $표[$oid]['payout'] = ($표[$oid]['payout'] ?? 0) + (int) ($줄['payOutAmount'] ?? 0);
            $표[$oid]['date']   = (string) ($줄['paidOutDate'] ?? '');
        }

        return $표;
    }

    /**
     * 화면에서 감출 칸 — 탭마다 다르다 (2026-09-15 지시).
     *
     * `기능 추가 및 수정/20260915/Finance_Unicorn screen_-2026-09-11.xlsx` 의 머리글에
     * 담당자가 칠로 표시를 해 두었다. **회색은 감추고, 노랑은 새로 넣는다.**
     * 노랑 열넷은 이미 넣었고(2026-09-15), 여기는 회색을 담는다.
     *
     * 이름으로 건다. 칸 이름(name)이 아니라 머리글(header)이다 — 엑셀에 적힌 것이
     * 머리글이라, 그 말을 그대로 두어야 다음에 파일과 맞춰 볼 수 있다.
     *
     * 머리 칠을 지워 둔 칸 열다섯(결제일ㆍ관할 지자체ㆍ구분(SB/SCI)ㆍ등록 일시ㆍ등록자ㆍ
     * 마지막 확정 수량ㆍ병원명ㆍ보호자명ㆍ수정 일시ㆍ수정자ㆍ신구매/재구매ㆍ인마켓 마감일ㆍ
     * 처방 유형ㆍ처방전 발행일ㆍ환급해당기관)은 **감추지 않는다** — 회색도 노랑도 아니어서
     * 물어보았고, 그대로 보이게 두라는 답을 받았다 (2026-09-15).
     *
     * 칸만 걷는다 — 값은 줄에 그대로 실려 있다. 엑셀 다운은 표가 세운 칸을 내려주므로
     * 감춘 칸은 거기에도 안 나온다. 다시 보이게 하려면 이 목록에서 빼면 된다.
     *
     * @return list<string> 감출 머리글
     */
    public static function 숨길칸(string $tab): array
    {
        /* PG정산내역은 토스에서 온 표라 이 목록과 겹치는 칸이 없다 */
        static $표 = [
        /* 통합주문내역 — 엑셀에서 회색인 59칸 */
        'orders' => [
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '개인정보동의',
            '검수 요청 메모', '고유 라인 번호', '구입일', '급여 종료일',
            '납품창고', '다음 재구매 가능일', '담당 의사명', '등급',
            '라인번호', '마감일자 변경여부', '발주 번호', '발주일자',
            '배송요청일자', '배송주소', '배송주소명', '배송주소코드',
            '사용 시작일', '상담 진행', '상병 구분', '상병 명',
            '상병코드', '상세 라인번호', '샘플스티커 부착유무', '신환master 등록일',
            '요류역학검사 결과', '요류역학검사일', '위임동의', '유효기간',
            '의사면허번호', '일일 도뇨 횟수', '재구매일', '제품바코드',
            '주문 담당자', '진단 확인일', '진료과목명', '참고 사항',
            '처방 사유', '처방전 사용기간', '처방전종료일', '총 처방일수',
            '총계', '추가정보 등록일', '출고번호', '표준코드',
            '피킹유형', '확정수량', '횟수',
        ],

        /* 환자 결제내역 — 엑셀에서 회색인 71칸 */
        'patient' => [
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '개인정보동의',
            '검수 요청 메모', '고유 라인 번호', '구매 거래처', '구매 거래처명',
            '구입일', '급여 종료일', '납품창고', '다음 재구매 가능일',
            '담당 의사명', '등급', '라인번호', '마감일자 변경여부',
            '매출 단가', '발주 번호', '발주일자', '배송요청일자',
            '배송주소', '배송주소명', '배송주소코드', '병원거래처',
            '병원거래처 주소', '사용 시작일', '상담 진행', '상병 구분',
            '상병 명', '상병코드', '상세 라인번호', '샘플스티커 부착유무',
            '신환master 등록일', '요류역학검사 결과', '요류역학검사일', '요양병원 코드',
            '위임동의', '유형', '유효기간', '의사면허번호',
            '일일 도뇨 횟수', '입고 상태', '재구매일', '제품그룹',
            '제품바코드', '주문 담당자', '진단 확인일', '진료과목명',
            '참고 사항', '처방 사유', '처방전 사용기간', '처방전종료일',
            '총 처방일수', '총계', '추가정보 등록일', '출고번호',
            '출고일자', '출고창고', '판매 거래처명', '표준코드',
            '피킹유형', '확정수량', '횟수',
        ],

        /* 건보ㆍ지자체 결제내역 — 엑셀에서 회색인 69칸 */
        'agency' => [
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '개인정보동의',
            '검수 요청 메모', '고유 라인 번호', '구매 거래처', '구입일',
            '급여 종료일', '납품창고', '다음 재구매 가능일', '담당 의사명',
            '등급', '라인번호', '마감일자 변경여부', '매출 단가',
            '발주 번호', '발주일자', '배송요청일자', '배송주소',
            '배송주소명', '배송주소코드', '병원거래처', '병원거래처 주소',
            '사용 시작일', '상담 진행', '상병 구분', '상병 명',
            '상병코드', '상세 라인번호', '샘플스티커 부착유무', '신환master 등록일',
            '요류역학검사 결과', '요류역학검사일', '요양병원 코드', '위임동의',
            '유형', '유효기간', '의사면허번호', '일일 도뇨 횟수',
            '입고 상태', '재구매일', '제품그룹', '제품바코드',
            '주문 담당자', '진단 확인일', '진료과목명', '처방 사유',
            '처방전 사용기간', '처방전종료일', '총 처방일수', '총계',
            '추가정보 등록일', '출고번호', '출고일자', '출고창고',
            '판매 거래처명', '표준코드', '피킹유형', '확정수량',
            '횟수',
        ],

        /* 미정산내역 — 엑셀에서 회색인 68칸 */
        'unpaid' => [
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '개인정보동의',
            '검수 요청 메모', '고유 라인 번호', '구매 거래처', '급여 종료일',
            '납품창고', '다음 재구매 가능일', '담당 의사명', '등급',
            '라인번호', '마감일자 변경여부', '매출 단가', '발주 번호',
            '발주일자', '배송요청일자', '배송주소', '배송주소명',
            '배송주소코드', '병원거래처', '병원거래처 주소', '사용 시작일',
            '상담 진행', '상병 구분', '상병 명', '상병코드',
            '상세 라인번호', '샘플스티커 부착유무', '신환master 등록일', '요류역학검사 결과',
            '요류역학검사일', '요양병원 코드', '위임동의', '유형',
            '유효기간', '의사면허번호', '일일 도뇨 횟수', '입고 상태',
            '재구매일', '제품그룹', '제품바코드', '주문 담당자',
            '진단 확인일', '진료과목명', '처방 사유', '처방전 사용기간',
            '처방전종료일', '총 처방일수', '총계', '추가정보 등록일',
            '출고번호', '출고일자', '출고창고', '판매 거래처명',
            '표준코드', '피킹유형', '확정수량', '횟수',
        ],

        /* 반품환불내역 — 엑셀에서 회색인 58칸 */
        'returns' => [
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '개인정보동의',
            '검수 요청 메모', '고유 라인 번호', '구입일', '급여 종료일',
            '다음 재구매 가능일', '담당 의사명', '등급', '라인번호',
            '발주 번호', '발주일자', '배송요청일자', '배송주소',
            '배송주소코드', '병원거래처', '병원거래처 주소', '사용 시작일',
            '상담 진행', '상병 구분', '상병 명', '상병코드',
            '상세 라인번호', '샘플스티커 부착유무', '신환master 등록일', '요류역학검사 결과',
            '요류역학검사일', '요양병원 코드', '위임동의', '유효기간',
            '의사면허번호', '일일 도뇨 횟수', '재구매일', '제품그룹',
            '제품바코드', '주문 담당자', '진단 확인일', '진료과목명',
            '처방 사유', '처방전 사용기간', '처방전종료일', '총 처방일수',
            '총계', '추가정보 등록일', '출고번호', '표준코드',
            '피킹유형', '횟수',
        ],

        /* 부가세신고내역 — 엑셀에서 회색인 77칸 */
        'vat' => [
            /* 이 시트에는 NB주민번호 칸이 아예 없다 — 다른 다섯에만 노랗게
               적혀 있다. 함께 쓰는 묶음(ceWwCols)으로 넣었으므로 여기서 건다. */
            '1일 처방 개수', 'Description 1', 'Description 2', 'Description 3',
            'Description 4', 'Five/Six', 'Five/Six(110days)', 'Lot',
            'QTY of RB', 'QTY of SB', 'S/O Date', '검수 요청 메모',
            '고유 라인 번호', '구매 거래처', '구매 거래처명', '구입일',
            '급여 종료일', '납품창고', '다음 재구매 가능일', '담당 의사명',
            '등급', '라인번호', '마감일자 변경여부', '매출 단가',
            '발주 번호', '발주일자', '배송요청일자', '배송주소',
            '배송주소명', '배송주소코드', '병원거래처', '병원거래처 주소',
            '사용 시작일', '상담 진행', '상병 구분', '상병 명',
            '상병코드', '상세 라인번호', '샘플스티커 부착유무', '신환master 등록일',
            '요류역학검사 결과', '요류역학검사일', '요양병원 코드', '유형',
            '유효기간', '의사면허번호', '일일 도뇨 횟수', '입고 상태',
            '입고일자', '입고확정일자', '재구매일', '제품그룹',
            '제품바코드', '주문 담당자', '주민등록번호', '진단 확인일',
            '진료과목명', '참고 사항', '처방 사유', '처방전 사용기간',
            '처방전종료일', '청구 전략 코드', '청구서상태', '총 처방일수',
            '총계', '추가정보 등록일', '추가정보 번호', '추가정보 유형',
            '출고번호', '출고일자', '출고창고', '판매 거래처명',
            '표준코드', '피킹유형', '확정수량', '횟수',
            'NB주민번호',
        ],
        ];

        return $표[$tab] ?? [];
    }

    private function columnsFor(string $tab): array
    {
        $money = ['align' => 'right', 'editor' => 'number'];

        /* PG정산내역 — 토스 화면의 칸 이름을 그대로 쓴다 (2026-09-11 확인요청 6ㆍ7쪽).
           수수료는 공급가와 부가세를 갈라 보여 준다 — 세금계산서와 맞출 때 그 둘이 필요하다. */
        $pg공통 = [
            ['header' => '결제+취소 건수', 'name' => '건수', 'width' => 120] + $money,
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

            /* 요약 — 토스 화면처럼 정산액 입금일로 묶은 표다(2026-09-14 확인요청 1쪽).
               전체 합은 표 아래 합계줄이 세운다. */
            'pg:summary' => array_merge(
                [
                    ['header' => '정산액 입금일', 'name' => '입금일', 'width' => 120, 'align' => 'center', 'sortable' => true],
                    ['header' => '매출일',        'name' => '매출일', 'width' => 180, 'align' => 'center', 'sortable' => true],
                ],
                $pg공통,
                [['header' => '당일 정산액', 'name' => '정산액', 'width' => 130] + $money],
            ),

            /* 건별 — 토스 화면의 차례 그대로다 (2026-09-14 확인요청 1쪽).
               CE 주문번호ㆍ구매자명ㆍ구매상품 셋은 토스에 없는 우리 값이다. 앞쪽에 둔다 —
               담당자가 이 표에서 먼저 찾는 것이 「누구 것인가」다. */
            'pg:detail' => [
                ['header' => 'CE 주문번호', 'name' => 'CE주문번호', 'width' => 130, 'sortable' => true],
                ['header' => '구매자명',   'name' => '구매자명',  'width' => 90,  'sortable' => true],
                ['header' => '구매상품',   'name' => '구매상품',  'width' => 200],
                ['header' => '상점아이디(MID)', 'name' => '상점아이디', 'width' => 130],
                ['header' => '정산액 입금일', 'name' => '입금일',  'width' => 110, 'align' => 'center', 'sortable' => true],
                ['header' => '매출일',     'name' => '매출일',    'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '원거래 승인일', 'name' => '승인일시', 'width' => 130, 'align' => 'center', 'sortable' => true],
                ['header' => '취소일시',   'name' => '취소일시',  'width' => 130, 'align' => 'center', 'sortable' => true],
                ['header' => '주문번호',   'name' => '주문번호',  'width' => 190, 'sortable' => true],
                ['header' => '결제수단',   'name' => '결제수단',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '결제상태',   'name' => '결제상태',  'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '결제기관',   'name' => '결제기관',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '카드종류',   'name' => '카드종류',  'width' => 100, 'align' => 'center'],
                ['header' => '할부',       'name' => '할부',      'width' => 80,  'align' => 'center'],
                ['header' => '결제·취소액', 'name' => '결제취소액', 'width' => 110] + $money,
                ['header' => 'PG수수료',   'name' => 'PG수수료',  'width' => 100] + $money,
                ['header' => '공급가액',   'name' => '공급가액',  'width' => 100] + $money,
                ['header' => '부가세',     'name' => '부가세',    'width' => 90]  + $money,
                ['header' => '할부수수료', 'name' => '할부수수료', 'width' => 100] + $money,
                ['header' => '당일 정산액', 'name' => '정산액',    'width' => 110] + $money,
                ['header' => '카드 매입상태', 'name' => '매입상태', 'width' => 110, 'align' => 'center'],
                ['header' => '카드 승인번호', 'name' => '카드승인번호', 'width' => 120],
                ['header' => 'TID',        'name' => 'TID',       'width' => 260],
                ['header' => '영수증',     'name' => '영수증',    'width' => 90,  'align' => 'center'],
            ],

            'pg:daily' => array_merge(
                [['header' => '매출일', 'name' => '매출일', 'width' => 120, 'align' => 'center', 'sortable' => true]],
                $pg공통,
                [['header' => '정산액', 'name' => '정산액', 'width' => 130] + $money],
            ),

            /* 결제수단별 — 토스 화면이 PG수수료를 넷으로 갈라 적는다 (2026-09-14 확인요청 2쪽) */
            'pg:method' => [
                ['header' => '결제수단', 'name' => '결제수단', 'width' => 140, 'align' => 'center', 'sortable' => true],
                ['header' => '결제+취소 건수', 'name' => '건수', 'width' => 120] + $money,
                ['header' => '매출액',   'name' => '매출액',   'width' => 120] + $money,
                ['header' => '수수료 일반',   'name' => '수수료일반',   'width' => 110] + $money,
                ['header' => '수수료 할부',   'name' => '수수료할부',   'width' => 110] + $money,
                ['header' => '수수료 포인트', 'name' => '수수료포인트', 'width' => 110] + $money,
                ['header' => '수수료 기타',   'name' => '수수료기타',   'width' => 110] + $money,
                ['header' => 'PG수수료', 'name' => 'PG수수료', 'width' => 110] + $money,
                ['header' => 'PG부가세', 'name' => 'PG부가세', 'width' => 110] + $money,
                ['header' => 'PG수수료 합', 'name' => '수수료합', 'width' => 120] + $money,
                ['header' => '당일 정산액', 'name' => '정산액', 'width' => 130] + $money,
            ],

            // 14쪽 — 전체 주문 현황 및 매출 확인
            'orders' => [
                /* 갈래와 사유 (2026-09-11 확인요청 8쪽). 한 표에 주문과 반품환불이
                   함께 서므로, 음수만으로 가리지 않고 이름으로도 가른다. */
                ['header' => '구분',       'name' => 'kind',      'width' => 84,  'align' => 'center', 'sortable' => true],
                ['header' => '사유',       'name' => 'reason',    'width' => 130, 'align' => 'center', 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '고객ID',     'name' => 'patient_id','width' => 80,  'align' => 'center'],
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
                ['header' => '제품코드',   'name' => 'code',      'width' => 110],
                ['header' => '제품명',     'name' => 'product',   'width' => 200],
                ['header' => '주문수량',   'name' => 'qty',       'width' => 90] + $money,
                ['header' => '주문금액',   'name' => 'total',     'width' => 110] + $money,
                /* 비율을 이름에 박지 않는다 (2026-09-22 확인요청 1쪽 · 탭 이름과 같은 까닭).
                   환자 결제 청구용이 아닌 건은 본인부담이 100% 인 경우가 있고, 지자체는
                   90% 가 아니라 100% 로 들어오는 일이 있다 — 이름이 늘 맞지는 않는다.
                   탭 이름(self::TABS)에서는 2026-09-11 에 이미 뗐는데 이 칸만 남아 있었다. */
                ['header' => '환자부담금', 'name' => 'copay', 'width' => 130] + $money,
                ['header' => '공단/지자체 부담금', 'name' => 'nhis', 'width' => 170] + $money,
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '배송일자',   'name' => 'delivered', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '배송상태',   'name' => 'ship_state','width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '송장번호',   'name' => 'tracking',  'width' => 130],
                ['header' => '주문상태',   'name' => 'status',    'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '취소여부',   'name' => 'cancelled', 'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '취소일자',   'name' => 'cancel_at', 'width' => 100, 'align' => 'center'],
                /* 받았는가 — 어떻게ㆍ언제ㆍ얼마 (2026-09-14 지시).

                   줄에는 진작 담겨 있었는데 이 탭만 칸이 없어 보이지 않았다. 다른 아홉
                   목록은 ceMoneyCols() 로 같은 넷을 세운다 — 이 탭은 칸을 서버에서
                   받으므로 여기에 적어 둔다.

                   결제 시각은 날짜만 적는 「결제일자」와 달리 시각까지 적는다. 같은 날
                   두 번 오간 건을 가리려면 시각이 있어야 한다.

                   칸 이름은 다른 아홉 목록과 똑같이 적는다 (2026-09-20 지시) — 같은
                   값이 화면마다 다른 이름으로 서면 담당자가 같은 것인지 되묻는다.
                   여태 「입금일시ㆍ입금금액」이라 적혀 있었고 입금확인은 아예 없었다. */
                ['header' => '결제수단',   'name' => 'pay_method', 'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '입금확인',   'name' => 'deposit_at', 'width' => 115, 'align' => 'center', 'sortable' => true],
                ['header' => '입금 금액',  'name' => 'paid',       'width' => 110] + $money,
                ['header' => '결제 시각',  'name' => 'paid_time',  'width' => 140, 'align' => 'center', 'sortable' => true],
                /* 카드로 받은 건의 상세 (2026-09-23 지시) — 여태 「카드」까지만 적혀,
                   어느 카드로 얼마가 언제 승인됐는지는 주문 화면까지 들어가야 알았다.
                   카드가 아닌 건은 빈칸이다. */
                ['header' => '카드사',     'name' => 'card_issuer',      'width' => 100, 'align' => 'center'],
                ['header' => '카드번호',   'name' => 'card_no',          'width' => 150],
                ['header' => '카드승인번호','name' => 'card_approve_no', 'width' => 120],
                ['header' => '할부',       'name' => 'card_installment', 'width' => 80,  'align' => 'center'],
            ],

            // 15쪽 — 환자 본인부담금 입금 확인
            'patient' => [
                /* 갈래 — 정정한 건은 한 주문에 세 줄이 선다 (2026-09-22 확인요청 4쪽).
                   「정정 후 / 정정 n차 · 원 주문 / 정정 n차 · 취소」다. 이 칸이 없으면
                   같은 주문번호가 여러 줄 서는 까닭을 화면에서 알 수 없다. */
                ['header' => '구분',       'name' => 'kind',      'width' => 120, 'align' => 'center', 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '주문일자',   'name' => 'order_at',  'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '거래처명',   'name' => 'patient',   'width' => 90,  'sortable' => true],
                // 환자에게 청구한 금액 — 입금과 맞춰 보는 값이다
                ['header' => '주문금액',   'name' => 'billed',    'width' => 110] + $money,
                ['header' => '본인부담액', 'name' => 'copay',     'width' => 110] + $money,
                ['header' => '입금일자',   'name' => 'paid_at',   'width' => 100, 'align' => 'center', 'sortable' => true],
                /* 입금확인 — 다른 목록과 같은 넷을 여기에도 세운다 (2026-09-20 지시).
                   입금일자와 달리 「받았는가」를 묻는 칸이다 — 본인부담이 0원인 건은
                   빈칸이 아니라 「본인부담 없음」이라 적힌다. */
                ['header' => '입금확인',   'name' => 'deposit_at', 'width' => 115, 'align' => 'center', 'sortable' => true],
                /* 결제 시각 — 날짜만으로는 같은 날 두 번 오간 건을 가릴 수 없다(2026-09-10 지시) */
                ['header' => '결제 시각',  'name' => 'paid_time', 'width' => 150, 'align' => 'center', 'sortable' => true],
                ['header' => '입금 금액',  'name' => 'paid',      'width' => 110] + $money,
                ['header' => '입금자명',   'name' => 'payer',     'width' => 100],
                ['header' => '결제수단',   'name' => 'pay_method','width' => 100, 'align' => 'center', 'sortable' => true],
                /* 카드로 받은 건의 상세 (2026-09-23 지시) — 여태 「카드」까지만 적혀,
                   어느 카드로 얼마가 언제 승인됐는지는 주문 화면까지 들어가야 알았다.
                   카드가 아닌 건은 빈칸이다. */
                ['header' => '카드사',     'name' => 'card_issuer',      'width' => 100, 'align' => 'center'],
                ['header' => '카드번호',   'name' => 'card_no',          'width' => 150],
                ['header' => '카드승인번호','name' => 'card_approve_no', 'width' => 120],
                ['header' => '할부',       'name' => 'card_installment', 'width' => 80,  'align' => 'center'],
                ['header' => '정산상태',   'name' => 'settle',    'width' => 90,  'align' => 'center', 'sortable' => true],
                /* 2026-09-11 엑셀과 맞춘다 (2026-09-15 지시) — 그 파일에 있는데 화면에
                   없던 넷이다. PG 세 칸은 토스 정산 연동이 없던 동안 비워 두었는데,
                   그 연동이 들어왔으므로(SettlementService) 이제 실제 값을 적는다. */
                ['header' => '주문상태',   'name' => 'status',    'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '출고일자',   'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => 'PG사 수수료', 'name' => 'pg_fee',   'width' => 110] + $money,
                ['header' => 'PG사 정산금액(수수료제외 회사계좌입금금액)', 'name' => 'pg_payout', 'width' => 220] + $money,
                ['header' => 'PG사 정산일(회사계좌 입금일자)', 'name' => 'pg_payout_at', 'width' => 180, 'align' => 'center', 'sortable' => true],
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
                /* 「입금금액」은 제 이름을 갖는다 (2026-09-22 확인요청 1쪽).

                   여태 승인금액과 **같은 name(approved)** 을 쓰고 있었다. 표는 칸을
                   이름으로 가리므로(wwGrid 의 _colMap ㆍ저장해 둔 너비ㆍ자리 옮기기가
                   모두 name 열쇠다), 둘이 한 칸으로 읽혔다 — 입금금액 머리를 끌면
                   승인금액이 늘어나고, 옮겨 둔 자리와 너비도 서로 덮었다.
                   확인요청 1쪽의 「입금금액 grid 조정 가능하게 필요」가 그것이다. */
                ['header' => '입금금액',   'name' => 'agency_paid', 'width' => 110] + $money,
                /* 통장에 찍히는 이름 (2026-09-11 엑셀). 공단은 「NB + 주민번호 앞 여섯
                   자리」로 넣고, 지자체는 정해진 것이 없어 기관마다 다르다. */
                ['header' => '입금자명',   'name' => 'agency_payer', 'width' => 120],
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
                // 2026-09-11 엑셀과 맞춘다 (2026-09-15 지시)
                ['header' => '주문상태',     'name' => 'status',    'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '출고일자',     'name' => 'shipped_at','width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => 'PG사 수수료', 'name' => 'pg_fee',   'width' => 110] + $money,
                ['header' => 'PG사 정산금액(수수료제외 회사계좌입금금액)', 'name' => 'pg_payout', 'width' => 220] + $money,
                ['header' => 'PG사 정산일(회사계좌 입금일자)', 'name' => 'pg_payout_at', 'width' => 180, 'align' => 'center', 'sortable' => true],
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
