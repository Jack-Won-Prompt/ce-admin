<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PopbillTaxinvoice;
use App\Support\BillingStrategy;
use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxinvoiceController extends Controller
{
    public function __construct(private readonly TaxinvoiceService $svc) {}

    /** 잔여포인트 */
    public function balance(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $balance = $this->svc->getBalance($corpNum);
        return response()->json(['corp_num' => $corpNum, 'balance' => $balance]);
    }

    /** 팝빌 세금계산서 관리 URL */
    public function url(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $togo    = $request->query('togo', 'WRITE');
        $url     = $this->svc->getUrl($corpNum, $userId, $togo);
        return response()->json(['url' => $url]);
    }

    /**
     * 목록 조회
     *  1) Popbill에서 해당 기간 전체 fetch → DB upsert
     *  2) DB에서 페이징하여 반환
     */
    /**
     * 2차 요청(R2-02)의 검색조건을 건다.
     *
     * 이 표에는 주문번호가 없다. 다만 발행할 때 관리번호를 `TI` + 발행일(Ymd) + 주문 id(6자리)
     * 로 만들어 왔으므로 뒤 6자리로 주문을 되짚을 수 있다. 정식 조인키가 아니라 규칙에 기댄
     * 것이라, 그 모양이 아닌 관리번호(수기 발행·옛 건)는 조인 대상에서 저절로 빠진다.
     *
     * 없는 조건(메모·서류 담당자)은 걸지 않는다. 빈 칸을 두면 담당자가 넣어 보고 아무것도
     * 안 걸러지는 것을 겪는다.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($v = trim((string) $request->query('invoicee_name'))) {
            $query->where('invoicee_corp_name', 'like', "%{$v}%");
        }

        // 주민번호 — 마스킹으로 저장돼 부분검색만 된다
        if ($v = preg_replace('/\D/', '', (string) $request->query('invoicee_num'))) {
            $query->where('invoicee_corp_num', 'like', "%{$v}%");
        }

        foreach ([['total_amount', 'amount_min', 'amount_max'],
                  ['supply_cost_total', 'supply_min', 'supply_max'],
                  ['tax_total', 'tax_min', 'tax_max']] as [$col, $min, $max]) {
            if (($v = $request->query($min)) !== null && $v !== '') {
                $query->where($col, '>=', (int) $v);
            }
            if (($v = $request->query($max)) !== null && $v !== '') {
                $query->where($col, '<=', (int) $v);
            }
        }

        if ($v = preg_replace('/\D/', '', (string) $request->query('issue_from'))) {
            $query->where('issue_dt', '>=', $v . '000000');
        }
        if ($v = preg_replace('/\D/', '', (string) $request->query('issue_to'))) {
            $query->where('issue_dt', '<=', $v . '235959');
        }

        // 판매번호·자격·유형은 주문을 되짚어야 나온다
        $orderNumber  = trim((string) $request->query('order_number'));
        $benefitClass = trim((string) $request->query('benefit_class'));
        $accType      = trim((string) $request->query('acc_type'));

        if ($orderNumber === '' && $benefitClass === '' && $accType === '') {
            return;
        }

        $ids = \App\Models\Order::query()
            ->when($orderNumber !== '', fn ($q) => $q->where('order_number', 'like', "%{$orderNumber}%"))
            ->when($benefitClass !== '' || $accType !== '', fn ($q) => $q->whereHas('prescription',
                fn ($p) => $p->when($benefitClass !== '', fn ($x) => $x->where('benefit_class', $benefitClass))
                             ->when($accType !== '',      fn ($x) => $x->where('counsel_acc_add_type', $accType))))
            ->pluck('id');

        // 관리번호 뒤 6자리가 주문 id 다
        $query->where(function ($q) use ($ids) {
            foreach ($ids as $id) {
                $q->orWhere('mgt_key', 'like', 'TI%' . str_pad((string) $id, 6, '0', STR_PAD_LEFT));
            }
            if ($ids->isEmpty()) {
                $q->whereRaw('1 = 0');   // 맞는 주문이 없으면 결과도 없어야 한다
            }
        });
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key_type' => 'nullable|in:SELL,BUY,TRUSTEE',
            'start_date'   => 'required|date_format:Ymd',
            'end_date'     => 'required|date_format:Ymd',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $corpNum    = $request->query('corp_num', config('popbill.test.corp_num'));
        $mgtKeyType = $request->query('mgt_key_type', 'SELL');
        $startDate  = $request->query('start_date');
        $endDate    = $request->query('end_date');
        $page       = (int) $request->query('page', 1);
        $perPage    = (int) $request->query('per_page', 15);
        $taxType    = $request->query('tax_type_code', []);

        // ── 1. Popbill fetch → DB 저장/상태 갱신 ───────────────────
        try {
            $rows = $this->svc->searchAll($corpNum, $mgtKeyType, $startDate, $endDate);
            foreach ($rows as $info) {
                $data = PopbillTaxinvoice::fromPopbillInfo($info, $corpNum, $mgtKeyType);
                if (empty($data['mgt_key'])) {
                    continue;
                }
                $existing = PopbillTaxinvoice::where([
                    'corp_num'     => $corpNum,
                    'mgt_key_type' => $mgtKeyType,
                    'mgt_key'      => $data['mgt_key'],
                ])->first();

                if (!$existing) {
                    PopbillTaxinvoice::create($data);
                } elseif ($existing->state_code !== (int) $data['state_code']) {
                    $existing->update([
                        'state_code' => $data['state_code'],
                        'state_dt'   => $data['state_dt'],
                        'is_final'   => $data['is_final'],
                        'synced_at'  => now(),
                    ]);
                }

                /* 팝빌에서 취소된 것을 **주문에도 옮겨 적는다** (2026-09-30 지시).

                   여태 이 표의 상태만 갱신하고 주문은 그대로 두었다. 사람이 팝빌
                   화면에서 직접 취소하면 그 사실이 우리 주문에 닿지 않아, 화면은
                   계속 「발행완료」라 적었다 —

                     TI20260930000002  팝빌 취소 19:19:25
                     우리 주문 #2      tax_invoice_status=issued (22:40 정정 뒤에도)

                   그러면 청구 전 건에 이미 없는 계산서가 살아 있는 것처럼 읽히고,
                   다음 발행이 「이미 발행됨」에 걸려 새 금액이 나가지 못한다.

                   반대 방향(취소를 되돌리는 일)은 하지 않는다 — 우리가 낸 뒤 저쪽이
                   아직 못 따라온 찰나일 수 있고, 그때 되살리면 없는 계산서가 선다. */
                if ((int) $data['state_code'] === 600) {
                    self::주문의계산서취소기록($data['mgt_key']);
                }
            }
        } catch (\Throwable) {
            // Popbill 오류 시 DB 캐시로 폴백 (경고 없이 계속 진행)
        }

        // ── 2. 세금계산서 DB 레코드 ────────────────────────────────
        $tiQuery = PopbillTaxinvoice::where('corp_num', $corpNum)
            ->where('mgt_key_type', $mgtKeyType)
            ->whereBetween('write_date', [$startDate, $endDate]);

        if (!empty($taxType)) {
            $tiQuery->whereIn('tax_type', (array) $taxType);
        }

        $this->applyFilters($tiQuery, $request);

        /* 발행 건이 어느 주문의 것인지는 order_id 가 안다(요청서 6쪽 · 관리번호에 심어
           둔 주문 id 를 읽어 둔 칸이다). 그 주문을 타고 가면 네 화면이 함께 쓰는 칸을
           여기서도 세울 수 있다 — 처방 유형ㆍ청구전략ㆍ자격ㆍ관할 청구처가 그것이다. */
        $tiRows = $tiQuery->with(['order.patient', 'order.prescription.billingOffice', 'order.items.lots', 'order.operationUser', 'order.tossPayment'])
            ->orderByDesc('write_date')->orderByDesc('id')->get();

        $tiExtras = \App\Support\OrderGridExtras::forPatients($tiRows->pluck('order.patient_id'));

        /* 그 계산서를 낸 때의 주문 금액 — 정정으로 물러난 값이다 (2026-09-30 지시).

           금액 칸(받을 금액ㆍ본인 부담금ㆍ기관 부담금)은 주문의 **지금** 값을 싣는다.
           그런데 취소된 계산서 줄에는 그때의 금액이 서야 한다 — 합계 729,000원짜리
           계산서 옆에 지금 값(675,000 · 67,500 · 607,500)이 나란히 서면 어느 것도
           맞지 않아 보인다.

           정정 이력에 그때의 금액과 그때 낸 계산서 번호가 함께 남아 있다. 번호로
           짝을 지어 그 줄에는 그때 값을 싣는다. */
        $물러난금액 = \Illuminate\Support\Facades\Schema::hasTable('order_amendments')
            ? \App\Models\OrderAmendment::whereIn('order_id', $tiRows->pluck('order_id')->filter()->unique())
                ->whereNotNull('tax_invoice_no')
                ->get()
                ->keyBy('tax_invoice_no')
            : collect();

        $tiRecords = $tiRows
            ->map(fn($r) => [
                'record_type'     => 'taxinvoice',
                /* 같은 날 줄이 뒤섞이지 않게 **시각까지** 본다 (2026-09-30 지시).

                   여태 작성일자(Ymd)만으로 세웠다. 하루에 여러 건이 나가면 그 안의
                   차례가 조회할 때마다 달라져, 담당자가 「방금 낸 것」을 눈으로 못
                   찾았다. 발행 시각이 있으면 그것을, 없으면 작성일 끝으로 둔다. */
                'sort_date'       => $r->issue_dt ?: ($r->write_date . '999999'),
                'invoicerMgtKey'  => $r->mgt_key_type === 'SELL'    ? $r->mgt_key : null,
                'invoiceeMgtKey'  => $r->mgt_key_type === 'BUY'     ? $r->mgt_key : null,
                'trusteeMgtKey'   => $r->mgt_key_type === 'TRUSTEE' ? $r->mgt_key : null,
                'itemKey'         => $r->item_key,
                'stateCode'       => (string) $r->state_code,
                'taxType'         => $r->tax_type,
                'purposeType'     => $r->purpose_type,
                'issueType'       => $r->issue_type,
                'writeDate'       => $r->write_date,
                'issueDT'         => $r->issue_dt,
                'invoicerCorpNum' => $r->invoicer_corp_num,
                'invoicerCorpName'=> $r->invoicer_corp_name,
                'invoiceeCorpNum' => $r->invoicee_corp_num,
                'invoiceeCorpName'=> $r->invoicee_corp_name,
                'supplyCostTotal' => (string) $r->supply_cost_total,
                'taxTotal'        => (string) $r->tax_total,
                /* 예전에 0 으로 적힌 줄이 있다 — 고쳐 쓰지 않고 보일 때 더한다 */
                'totalAmount'     => (string) ($r->total_amount ?: $r->supply_cost_total + $r->tax_total),
                'ntsconfirmNum'   => $r->nts_confirm_num,

                /* 대표자 이름은 **팝빌 목록 응답에 아예 없다** (2026-09-30 확인).

                   조회로 받아 오는 항목에 invoicerCEOName·invoiceeCEOName 이 담기지
                   않아, 상호는 서는데 성명 칸만 늘 비어 있었다 — 한 쪽만 맞아 보인다.

                   우리가 신고할 때 적어 둔 값이 주문에 그대로 있다. 그것으로 채운다 —
                   신고한 값이므로 국세청 기록과 어긋나지 않는다. 공급자 대표자는
                   설정에 담긴 우리 회사 값이다. */
                'invoicerCeoName' => $r->invoicer_ceo_name
                                      ?: config('popbill.company.ceo_name'),
                'invoiceeCeoName' => $r->invoicee_ceo_name
                                      ?: ($r->order?->tax_invoice_ceo_name
                                          ?: \App\Models\Patient::bare($r->order?->patient?->name)),
                'stateDT'         => $r->state_dt,
                'isFinal'         => (bool) $r->is_final,
                'syncedAt'        => $r->synced_at?->toDateTimeString(),

                // 우리 주문을 타고 온 것
                'orderNumber'     => $r->order?->order_number,
                'rxNumber'        => $r->order?->prescription?->rx_number,
                // 이름 앞의 (E) 는 우리 쪽 표식이다 — 환자 이름으로 보여 주지 않는다
                'patientName'     => \App\Models\Patient::bare($r->order?->patient?->name),
            ] + ($r->order
                    ? $tiExtras->rx($r->order->prescription, $r->order->patient)
                      + $tiExtras->ww($r->order, $r->order->prescription, $r->order->patient)
                      + $tiExtras->of($r->order)
                    : []))
            /* 그때의 금액으로 덮는다 — 짝이 없으면(정정을 거치지 않은 계산서면)
               지금 값이 곧 그때 값이라 그대로 둔다. */
            ->map(function (array $줄) use ($물러난금액) {
                $옛 = $물러난금액->get((string) ($줄['ntsconfirmNum'] ?? ''));

                if (! $옛) {
                    return $줄;
                }

                return array_merge($줄, [
                    /* 「받을 금액」 칸은 다섯 화면에서 **본인＋기관**(주문 총액)을
                       담는다(OrderGridExtras::of). 그때 값도 같은 잣대로 센다 —
                       이 줄만 다른 셈을 쓰면 화면끼리 말이 갈린다. */
                    'total_amount'   => (int) $옛->patient_copay + (int) $옛->nhis_amount,
                    'copay'          => (int) $옛->patient_copay,
                    'patient_copay'  => (int) $옛->patient_copay,
                    'nhis_amount'    => (int) $옛->nhis_amount,
                    /* 받은 돈ㆍ창고 매출은 지금 줄의 것이다 — 물러난 줄에 실으면
                       같은 돈이 두 번 적힌 것처럼 보인다 */
                    'deposit_amount' => 0,
                    'ww_so_amt'      => '',
                ]);
            })
            /* 취소된 계산서는 **발행 줄과 취소 줄 둘**로 편다 (2026-09-30 지시).

               여태 한 줄만 서고 그 줄이 「발행취소」였다. 그러면 얼마를 냈다가 얼마를
               물렸는지 이 화면에서 셀 수 없다 — 낸 적이 있다는 사실이 사라진다.
               결제 자취를 걸음마다 세우는 것(payment_events)과 같은 뜻이다.

                 발행 줄  발행완료 · 그때 시각 · 금액 그대로
                 취소 줄  발행취소 · 취소 시각 · 금액에 − 를 붙인다

               그대로 더하면 이 기간에 국세청에 남은 금액이 나온다. */
            ->flatMap(function (array $줄) {
                if ((int) ($줄['stateCode'] ?? 0) !== 600) {
                    return [$줄];
                }

                $음수 = fn ($v) => -abs((int) $v);

                $발행 = array_merge($줄, [
                    'record_type' => 'taxinvoice',
                    'stateCode'   => '300',
                    'sort_date'   => $줄['issueDT'] ?: $줄['sort_date'],
                    '_pair'       => 'issued',
                ]);

                $취소 = array_merge($줄, [
                    'sort_date'       => $줄['stateDT'] ?: $줄['sort_date'],
                    'supplyCostTotal' => (string) $음수($줄['supplyCostTotal']),
                    'taxTotal'        => (string) $음수($줄['taxTotal']),
                    'totalAmount'     => (string) $음수($줄['totalAmount']),
                    'total_amount'    => $음수($줄['total_amount']   ?? 0),
                    'copay'           => $음수($줄['copay']          ?? 0),
                    'patient_copay'   => $음수($줄['patient_copay']  ?? 0),
                    'nhis_amount'     => $음수($줄['nhis_amount']    ?? 0),
                    '_pair'           => 'cancelled',
                ]);

                return [$취소, $발행];
            })
            ->values();

        // ── 3. 세금계산서 발행 대기 ─────────────────────────────
        /* 「계산서 발행」 화면이 하던 일이다 — 2026-09-01 요청으로 그 화면을 없애고
           여기로 모았다. 전에는 검수·주문완료 처방전을 모두 세웠는데, 그 안에는
           현금영수증으로 가는 건(처방외ㆍ산재ㆍ자동차보험)도 섞여 있었다.
           이 화면은 세금계산서 대상만 보여야 한다. */
        $startDT = \Carbon\Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $endDT   = \Carbon\Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        /* 이름으로도 거른다 (2026-09-22 확인요청 2쪽).

           발행된 줄은 invoicee_corp_name(공급받는자 상호 = 환자 이름)으로 거르는
           자리가 이미 있었는데(applyFilters), 아직 안 낸 대기 줄에는 그 자리가 없어
           이름을 쳐도 함께 남았다 — 한 표에 섞여 서는 두 갈래라 한쪽만 걸리면
           걸러지지 않은 것으로 보인다. 현금영수증 화면이 이미 같은 짝을 맞춰 두었다. */
        $이름 = trim((string) $request->query('invoicee_name'));

        $pendingQuery = Order::with(['patient', 'prescription', 'tossPayment'])
            ->whereIn('status', \App\Models\Order::OPEN_AFTER_CONFIRM)
            ->when($이름 !== '', fn ($q) => $q->where(function ($w) use ($이름) {
                $w->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$이름}%"))
                  ->orWhereHas('prescription', fn ($p) => $p->where('patient_name_ocr', 'like', "%{$이름}%"));
            }));

        BillingStrategy::targets($pendingQuery, 'tax_invoice');

        $rxRecords = $pendingQuery
            ->where(fn ($q) => $q->whereNull('tax_invoice_status')
                                 ->orWhere('tax_invoice_status', '!=', 'issued'))
            /* 언제 것인가 — 나간 날이 있으면 그 날, 없으면 받은 날이다.
               출고 전 건도 대기 목록에 서야 담당자가 순서를 잡을 수 있다. */
            ->where(fn ($q) => $q->whereBetween('delivered_at', [$startDT, $endDT])
                                 ->orWhere(fn ($x) => $x->whereNull('delivered_at')
                                                        ->whereBetween('created_at', [$startDT, $endDT])))
            ->orderByDesc('id')
            ->get()
            ->map(function (Order $o) {
                $rx    = $o->prescription;
                $rate  = (int) (BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class)['tax_invoice'] ?? 0);
                /* 밑돈은 본인부담 + 기관부담이다. total_amount 를 쓰면 안 된다 —
                   그 칸에는 옛 건의 배송비가 섞여 있어 발행 금액이 어긋난다. */
                $amount = (int) round(((int) ($o->patient_copay ?? 0) + (int) ($o->nhis_amount ?? 0)) * $rate / 100);
                $supply = (int) round($amount / 1.1);
                $at     = $o->delivered_at ?? $o->created_at;

                return [
                    'record_type'     => 'pending',
                    /* 대기 줄도 시각까지 — 발행된 줄과 같은 잣대라야 섞어 세울 수 있다 */
                    'sort_date'       => $at?->format('YmdHis') ?? '',
                    'invoicerMgtKey'  => null,
                    'invoiceeMgtKey'  => null,
                    'trusteeMgtKey'   => null,
                    'itemKey'         => null,
                    'stateCode'       => null,
                    'taxType'         => null,
                    'purposeType'     => null,
                    'issueType'       => null,
                    'writeDate'       => $at?->format('Ymd'),
                    'issueDT'         => null,
                    'invoicerCorpNum' => null,
                    'invoicerCorpName'=> null,
                    'invoiceeCorpNum' => null,
                    /* 이름 앞의 (E) 를 뗀다 (2026-09-30 지시).

                       발행된 줄은 발행 경로가 Patient::bare() 로 떼어 신고하는데
                       (DepositAutoIssue), 대기 줄만 환자 이름을 그대로 실어 한 사람이
                       두 이름으로 섰다 —

                         발행취소  공급받는자 박경진
                         발행 대기 공급받는자 (E)박경진

                       (E) 는 운영 자료에서 옮겨 왔다는 우리 쪽 표식이지 환자 이름이
                       아니다. 대기 줄에 그것이 보이면 담당자는 다른 사람으로 읽는다. */
                    'invoiceeCorpName'=> \App\Models\Patient::bare($o->patient?->name)
                                          ?: ($rx?->patient_name_ocr ?? '—'),
                    'supplyCostTotal' => (string) $supply,
                    'taxTotal'        => (string) ($amount - $supply),
                    'totalAmount'     => (string) $amount,
                    'ntsconfirmNum'   => null,
                    'order_id'        => $o->id,
                    'order_number'    => $o->order_number,
                    'rx_number'       => $rx?->rx_number,
                ];
            });

        // ── 4. 합치기 → 날짜 내림차순 → 페이징 ────────────────────
        $combined = $tiRecords->concat($rxRecords)
            ->sortByDesc('sort_date')
            ->values();

        $total = $combined->count();
        $list  = $combined->forPage($page, $perPage)->values();

        return response()->json([
            'total'     => $total,
            'perPage'   => $perPage,
            'pageNum'   => $page,
            'pageCount' => (int) ceil($total / $perPage),
            'list'      => $list,
        ]);
    }

    /**
     * 비완료 상태 레코드를 Popbill에서 동기화
     *  - state_code not in (400, 500) 인 레코드를 GetInfo로 갱신
     */
    public function sync(Request $request): JsonResponse
    {
        $corpNum    = $request->query('corp_num', config('popbill.test.corp_num'));
        $mgtKeyType = $request->query('mgt_key_type', 'SELL');

        $pending = PopbillTaxinvoice::where('corp_num', $corpNum)
            ->where('mgt_key_type', $mgtKeyType)
            ->where('is_final', false)
            ->get();

        $updated = 0;
        $errors  = 0;

        foreach ($pending as $record) {
            try {
                $info = $this->svc->getInfo($corpNum, $mgtKeyType, $record->mgt_key);
                $data = PopbillTaxinvoice::fromPopbillInfo($info, $corpNum, $mgtKeyType);
                $record->fill($data)->save();
                $updated++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        return response()->json([
            'synced'  => $updated,
            'errors'  => $errors,
            'pending' => $pending->count(),
        ]);
    }

    /** 상태 확인 (DB 우선, 비완료 시 Popbill 재조회 후 DB 갱신) */
    public function info(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key_type' => 'required|in:SELL,BUY,TRUSTEE',
            'mgt_key'      => 'required|string',
        ]);

        $corpNum    = $request->query('corp_num', config('popbill.test.corp_num'));
        $mgtKeyType = $request->query('mgt_key_type');
        $mgtKey     = $request->query('mgt_key');

        // DB에 최종 상태로 저장된 경우 Popbill 호출 없이 GetInfo로 상세 조회
        // (DB에는 요약 정보만 있으므로 상세는 항상 Popbill 호출)
        $result = $this->svc->getInfo($corpNum, $mgtKeyType, $mgtKey);

        // 조회 결과를 DB에 upsert
        $data = PopbillTaxinvoice::fromPopbillInfo($result, $corpNum, $mgtKeyType);
        if (!empty($data['mgt_key'])) {
            PopbillTaxinvoice::updateOrCreate(
                ['corp_num' => $corpNum, 'mgt_key_type' => $mgtKeyType, 'mgt_key' => $data['mgt_key']],
                $data
            );
        }

        return response()->json($result);
    }

    /** 팝업 URL */
    public function popupUrl(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key_type' => 'required|in:SELL,BUY,TRUSTEE',
            'mgt_key'      => 'required|string',
        ]);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $url     = $this->svc->getPopupUrl($corpNum, $request->query('mgt_key_type'), $request->query('mgt_key'), $userId);
        return response()->json(['url' => $url]);
    }

    /** 인쇄 URL */
    public function printUrl(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key_type' => 'required|in:SELL,BUY,TRUSTEE',
            'mgt_key'      => 'required|string',
        ]);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $url     = $this->svc->getPrintUrl($corpNum, $request->query('mgt_key_type'), $request->query('mgt_key'), $userId);
        return response()->json(['url' => $url]);
    }

    /** 발행 취소 */
    public function cancelIssue(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key_type' => 'required|in:SELL,BUY,TRUSTEE',
            'mgt_key'      => 'required|string',
            'memo'         => 'nullable|string|max:255',
        ]);

        $corpNum    = $request->input('corp_num', config('popbill.test.corp_num'));
        $mgtKeyType = $request->input('mgt_key_type');
        $mgtKey     = $request->input('mgt_key');
        $userId     = config('popbill.test.user_id');

        $result = $this->svc->cancelIssue($corpNum, $mgtKeyType, $mgtKey, $request->input('memo'), $userId);

        // 취소 후 DB 상태 갱신
        PopbillTaxinvoice::where(['corp_num' => $corpNum, 'mgt_key_type' => $mgtKeyType, 'mgt_key' => $mgtKey])
            ->update(['state_code' => 500, 'is_final' => true, 'synced_at' => now()]);

        return response()->json($result);
    }

    /** 즉시발행 */
    /**
     * 주문을 찾아 발행에 쓸 값을 한 벌로 돌려준다(2026-09-03).
     *
     * 이 화면은 손으로 적는 자리다. 그래서 주문 발행 길과 달리 품목도 장비코드도
     * 사람이 채워야 했는데, 장비코드를 외우고 있는 사람은 없다 — 다른 표를 열어
     * 찾아 옮겨 적다가 틀린다.
     *
     * 채우는 일은 화면이 한다. 여기서는 무엇을 채울지만 준다.
     */
    public function orderSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $rows = Order::with(['patient', 'prescription.items', 'items'])
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($y) use ($q) {
                    $y->where('order_number', 'like', "%{$q}%")
                      ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%"))
                      ->orWhere('withworks_so_no', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json(['data' => $rows->map(function (Order $o) {
            $rx    = $o->prescription;
            $st    = BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class);
            $rate  = (int) ($st['tax_invoice'] ?? 0);

            /* 낼 금액은 청구전략이 정한 몫이다 — 제품값 전부가 아니다. 주문 발행
               길이 세는 법과 같게 둔다(다르면 같은 건에 두 금액이 생긴다). */
            $base  = (int) ($o->patient_copay ?? 0) + (int) ($o->nhis_amount ?? 0);
            $total = (int) round($base * $rate / 100);

            return [
                'id'          => $o->id,
                'order_no'    => $o->order_number,
                'patient'     => \App\Models\Patient::bare($o->patient?->name) ?: ($rx?->patient_name_ocr ?? '-'),
                'strategy'    => $st['label'] ?? '-',
                'rate'        => $rate,
                'total'       => $total,
                'issued'      => $o->tax_invoice_status === 'issued' && ! $o->tax_invoice_cancelled_at,
                'created'     => $o->created_at?->format('Y-m-d'),
                // 공급받는자 — 주문에 적어 둔 것이 있으면 그것이 먼저다
                'biz_no'      => (string) ($o->tax_invoice_biz_no ?: ''),
                /* 국세청에 신고될 이름이다 — (E) 를 떼지 않으면 그대로 나간다.
                   발행 경로(DepositAutoIssue)는 이미 bare_name 으로 내는데, 담당자가
                   화면에서 손으로 낼 때 쓰는 이 기본값만 원본을 실었다. */
                'biz_name'    => (string) ($o->tax_invoice_biz_name
                                    ?: \App\Models\Patient::bare($o->patient?->name)),
                'ceo_name'    => (string) ($o->tax_invoice_ceo_name ?: ''),
                'email'       => (string) ($o->tax_invoice_email ?: ($o->patient?->email ?? '')),
                'items'       => \App\Support\IssueLines::rowsFor($o),
            ];
        })->all()]);
    }

    public function registIssue(Request $request): JsonResponse
    {
        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');

        $invoice = $this->svc->newInvoice();

        $invoice->writeDate       = $request->input('write_date', now()->format('Ymd'));
        $invoice->taxType         = $request->input('tax_type', '과세');
        $invoice->issueType       = $request->input('issue_type', '정발행');
        // 고르지 않았으면 청구다 — 받기 전에 내는 계산서다 (2026-09-14 지시)
        $invoice->purposeType     = $request->input('purpose_type', \App\Support\TaxInvoiceForm::PURPOSE);
        $invoice->chargeDirection = $request->input('charge_direction', '정과금');

        $invoice->invoicerCorpNum     = $request->input('invoicer_corp_num', $corpNum);
        $invoice->invoicerMgtKey      = $request->input('invoicer_mgt_key', '');
        $invoice->invoicerCorpName    = $request->input('invoicer_corp_name', '');
        $invoice->invoicerCEOName     = $request->input('invoicer_ceo_name', '');
        $invoice->invoicerAddr        = $request->input('invoicer_addr', '');
        $invoice->invoicerBizType     = $request->input('invoicer_biz_type', '');
        $invoice->invoicerBizClass    = $request->input('invoicer_biz_class', '');
        $invoice->invoicerContactName = $request->input('invoicer_contact_name', '');
        $invoice->invoicerTEL         = $request->input('invoicer_tel', '');
        $invoice->invoicerEmail       = $request->input('invoicer_email', '');

        $invoice->invoiceeType         = $request->input('invoicee_type', '사업자');
        $invoice->invoiceeCorpNum      = $request->input('invoicee_corp_num', '');
        $invoice->invoiceeCorpName     = $request->input('invoicee_corp_name', '');
        $invoice->invoiceeCEOName      = $request->input('invoicee_ceo_name', '');
        $invoice->invoiceeAddr         = $request->input('invoicee_addr', '');
        $invoice->invoiceeBizType      = $request->input('invoicee_biz_type', '');
        $invoice->invoiceeBizClass     = $request->input('invoicee_biz_class', '');
        $invoice->invoiceeContactName1 = $request->input('invoicee_contact_name', '');
        $invoice->invoiceeTEL1         = $request->input('invoicee_tel', '');
        $invoice->invoiceeEmail1       = $request->input('invoicee_email', '');

        $invoice->supplyCostTotal = (string) $request->input('supply_cost_total', '0');
        $invoice->taxTotal        = (string) $request->input('tax_total', '0');
        $invoice->totalAmount     = (string) $request->input('total_amount', '0');
        $invoice->remark1         = $request->input('remark1', '');

        $details = [];
        foreach ($request->input('details', []) as $i => $d) {
            $detail             = $this->svc->newDetail();
            $detail->serialNum  = (string) ($i + 1);
            $detail->purchaseDT = $d['purchase_dt']  ?? '';
            $detail->itemName   = $d['item_name']    ?? '';
            $detail->spec       = $d['spec']         ?? '';
            $detail->qty        = $d['qty']          ?? '';
            $detail->unitCost   = $d['unit_cost']    ?? '';
            $detail->supplyCost = $d['supply_cost']  ?? '';
            $detail->tax        = $d['tax']          ?? '';
            $detail->remark     = $d['remark']       ?? '';
            $details[]          = $detail;
        }
        if (!empty($details)) {
            $invoice->detailList = $details;
        }

        $result = $this->svc->registIssue($corpNum, $invoice, $userId);
        return response()->json($result);
    }

    /**
     * 팝빌에서 취소된 계산서를 낸 주문의 기록을 맞춘다 (2026-09-30 지시).
     *
     * 문서번호는 `TI` + 발행일(Ymd) + 주문 id(6자리)로 만든다 — 뒤 여섯 자리가 주문
     * id 다. 그 모양이 아닌 번호(수기 발행ㆍ옛 건)는 되짚을 수 없으므로 지나간다.
     */
    private static function 주문의계산서취소기록(?string $mgtKey): void
    {
        if (! $mgtKey || ! preg_match('/^TI\d{8}(\d{6})$/', $mgtKey, $m)) {
            return;
        }

        $order = Order::find((int) $m[1]);

        /* 그 주문이 지금 쓰고 있는 번호일 때만 맞춘다 — 재발행으로 번호가 바뀌었으면
           옛 번호의 취소를 새 계산서에 적으면 안 된다. */
        if (! $order
            || $order->tax_invoice_status !== 'issued'
            || $order->tax_invoice_mgt_key !== $mgtKey) {
            return;
        }

        $order->forceFill([
            'tax_invoice_status'       => 'cancelled',
            'tax_invoice_cancelled_at' => $order->tax_invoice_cancelled_at ?? now(),
        ])->save();

        \Illuminate\Support\Facades\Log::info('[세금계산서] 팝빌에서 취소된 것을 주문에 옮겨 적었습니다',
            ['order' => $order->id, 'mgt_key' => $mgtKey]);
    }

}
