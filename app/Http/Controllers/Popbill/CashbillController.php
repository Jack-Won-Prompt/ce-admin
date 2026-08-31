<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\CashbillRecord;
use App\Models\Order;
use App\Support\OrderGridExtras;
use App\Services\Popbill\CashbillService;
use App\Services\Popbill\CashbillSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbillController extends Controller
{
    public function __construct(
        private readonly CashbillService     $svc,
        private readonly CashbillSyncService $syncSvc,
    ) {}

    /** 잔여포인트 조회 */
    public function balance(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $balance = $this->svc->getBalance($corpNum);
        return response()->json(['corp_num' => $corpNum, 'balance' => $balance]);
    }

    /** 팝빌 현금영수증 관리 URL */
    public function url(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $togo    = $request->query('togo', 'HOME');
        $url = $this->svc->getUrl($corpNum, $userId, $togo);
        return response()->json(['url' => $url]);
    }

    /**
     * 목록 조회 (DB 기반)
     * 쿼리 파라미터:
     *   start_date  YYYYMMDD (필수)
     *   end_date    YYYYMMDD (필수)
     *   trade_type  승인거래|취소거래 (선택)
     *   trade_usage 소득공제용|지출증빙용 (선택)
     *   page        (기본 1)
     *   per_page    (기본 15, 최대 1000)
     *   order       D(내림차순)|A(오름차순) - trade_dt 기준
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
            'page'       => 'nullable|integer|min:1',
            // 화면은 한 해치를 한 번에 본다. 이 조회는 팝빌이 아니라 우리 표를 읽으므로
            // 상한을 낮게 둘 이유가 없다 — 100 이면 화면이 보내는 값에 걸려 422 가 났다.
            'per_page'   => 'nullable|integer|min:1|max:1000',
        ]);

        $corpNum   = $request->query('corp_num', config('popbill.test.corp_num'));
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $perPage   = (int) $request->query('per_page', 15);
        $page      = (int) $request->query('page', 1);
        $order     = $request->query('order', 'D') === 'A' ? 'asc' : 'desc';

        $query = CashbillRecord::where('corp_num', $corpNum)
            ->where('trade_dt', '>=', $startDate . '000000')
            ->where('trade_dt', '<=', $endDate   . '235959')
            ->orderBy('trade_dt', $order);

        if ($tradeType = $request->query('trade_type')) {
            $query->where('trade_type', $tradeType);
        }
        if ($tradeUsage = $request->query('trade_usage')) {
            $query->where('trade_usage', $tradeUsage);
        }

        $this->applyFilters($query, $request);

        $total = $query->count();

        /* 발행 건이 어느 주문의 것인지는 order_id 가 안다(요청서 6쪽). 그 주문을 타고
           가면 네 화면이 함께 쓰는 칸을 여기서도 세울 수 있다 — 처방 유형ㆍ청구전략ㆍ
           자격ㆍ관할 청구처가 그것이다. */
        $records = $query->with(['order.patient', 'order.prescription.billingOffice', 'order.items.lots'])
                         ->forPage($page, $perPage)->get();

        $extras = OrderGridExtras::forPatients($records->pluck('order.patient_id'));

        return response()->json([
            'total'     => $total,
            'perPage'   => $perPage,
            'pageNum'   => $page,
            'pageCount' => (int) ceil($total / $perPage),
            'list'      => $records->map(fn (CashbillRecord $r) => $this->toListItem($r, $extras)),
        ]);
    }

    /**
     * 상세 조회 (DB → 필요시 팝빌 갱신)
     * 비최종 상태이면 팝빌 GetInfo 로 상태 갱신 후 DB 업데이트
     */
    public function info(Request $request): JsonResponse
    {
        $request->validate(['mgt_key' => 'required|string']);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $mgtKey  = $request->query('mgt_key');

        $rec = CashbillRecord::where('corp_num', $corpNum)->where('mgt_key', $mgtKey)->first();

        // DB에 없거나 비최종 상태이면 팝빌에서 전체 동기화
        if (!$rec || !$rec->isFinal()) {
            $rec = $this->syncSvc->refreshOne($corpNum, $mgtKey);
        }

        return response()->json($this->toDetailItem($rec));
    }

    /**
     * 수동 동기화 (UI 버튼)
     * 지정 기간의 팝빌 데이터를 DB에 저장하고 상태도 갱신
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $start   = $request->input('start_date');
        $end     = $request->input('end_date');

        $r1 = $this->syncSvc->syncFromPopbill($corpNum, $start, $end);
        $r2 = $this->syncSvc->refreshPendingStatus($corpNum);

        return response()->json([
            'message' => '동기화 완료',
            'synced'  => $r1['synced'],
            'updated' => $r2['updated'],
            'errors'  => $r1['errors'] + $r2['errors'],
        ]);
    }

    /** 현금영수증 팝업 URL */
    public function popupUrl(Request $request): JsonResponse
    {
        $request->validate(['mgt_key' => 'required|string']);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');
        $url = $this->svc->getPopupUrl($corpNum, $request->query('mgt_key'), $userId);
        return response()->json(['url' => $url]);
    }

    /** 인쇄 URL */
    public function printUrl(Request $request): JsonResponse
    {
        $request->validate(['mgt_key' => 'required|string']);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');
        $url = $this->svc->getPrintUrl($corpNum, $request->query('mgt_key'), $userId);
        return response()->json(['url' => $url]);
    }

    /** 즉시발행 */
    public function registIssue(Request $request): JsonResponse
    {
        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');

        $cashbill = $this->svc->newCashbill();
        $cashbill->mgtKey            = $request->input('mgt_key');
        $cashbill->tradeType         = $request->input('trade_type', '승인거래');
        $cashbill->tradeUsage        = $request->input('trade_usage', '소득공제용');
        $cashbill->taxationType      = $request->input('taxation_type', '과세');
        $cashbill->franchiseCorpNum  = $request->input('franchise_corp_num', $corpNum);
        $cashbill->franchiseCorpName = $request->input('franchise_corp_name', '');
        $cashbill->franchiseCEOName  = $request->input('franchise_ceo_name', '');
        $cashbill->franchiseAddr     = $request->input('franchise_addr', '');
        $cashbill->franchiseTEL      = $request->input('franchise_tel', '');
        $cashbill->supplyCost        = $request->input('supply_cost', '0');
        $cashbill->tax               = $request->input('tax', '0');
        $cashbill->serviceFee        = $request->input('service_fee', '0');
        $cashbill->totalAmount       = $request->input('total_amount', '0');
        $cashbill->identityNum       = $request->input('identity_num', '');
        $cashbill->customerName      = $request->input('customer_name', '');
        $cashbill->itemName          = $request->input('item_name', '');
        $cashbill->email             = $request->input('email', '');
        $cashbill->hp                = $request->input('hp', '');

        $result = $this->svc->registIssue($corpNum, $cashbill, $userId);

        // 발행 직후 DB에 저장
        try {
            $this->syncSvc->refreshOne($corpNum, $cashbill->mgtKey);
        } catch (\Throwable) { /* 실패해도 발행 결과는 반환 */ }

        return response()->json($result);
    }

    /** 취소현금영수증 즉시발행 */
    public function revoke(Request $request): JsonResponse
    {
        $request->validate([
            'mgt_key'        => 'required|string|max:24',
            'org_confirm_num'=> 'required|string',
            'org_trade_date' => 'required|date_format:Ymd',
        ]);

        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');

        $result = $this->svc->revokeRegistIssue(
            corpNum:      $corpNum,
            mgtKey:       $request->input('mgt_key'),
            orgMgtKey:    $request->input('org_confirm_num'),
            orgTradeDate: $request->input('org_trade_date'),
            userId:       $userId,
        );

        // 취소 발행 후 DB 저장
        try {
            $this->syncSvc->refreshOne($corpNum, $request->input('mgt_key'));
        } catch (\Throwable) {}

        return response()->json($result);
    }

    /**
     * 처방전 발행 현금영수증 목록 (orders 테이블)
     */
    public function orderReceipts(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        $start = \Carbon\Carbon::createFromFormat('Ymd', $request->query('start_date'))->startOfDay();
        $end   = \Carbon\Carbon::createFromFormat('Ymd', $request->query('end_date'))->endOfDay();

        $orders = Order::with(['patient', 'prescription.billingOffice', 'items.lots'])
            ->whereIn('cash_receipt_status', ['issued', 'cancelled'])
            ->whereBetween('cash_receipt_issued_at', [$start, $end])
            ->orderByDesc('cash_receipt_issued_at')
            ->get();

        // 팝빌 쪽 줄과 같은 칸을 세운다 — 한 표에 섞여 서므로 이름이 갈리면 안 된다
        $extras = OrderGridExtras::forPatients($orders->pluck('patient_id'));

        $list = $orders->map(fn (Order $o) => [
            'source'           => 'order',
            'orderId'          => $o->id,
            'orderNumber'      => $o->order_number,
            'rxNumber'         => $o->prescription?->rx_number,
            'patientName'      => $o->patient?->name ?? $o->prescription?->patient_name_ocr ?? '—',
            'receiptNo'        => $o->cash_receipt_no,
            'receiptTypeKey'   => $o->cash_receipt_type,
            'receiptTypeLabel' => Order::CASH_RECEIPT_TYPE_LABELS[$o->cash_receipt_type] ?? $o->cash_receipt_type,
            'identifier'       => $o->cash_receipt_identifier,
            'amount'           => (int) $o->cash_receipt_amount,
            'status'           => $o->cash_receipt_status,
            'issuedAt'         => $o->cash_receipt_issued_at?->format('YmdHis'),
            'cancelledAt'      => $o->cash_receipt_cancelled_at?->format('YmdHis'),
        ] + $extras->rx($o->prescription, $o->patient)
          + $extras->ww($o, $o->prescription, $o->patient)
          + $extras->of($o));

        return response()->json(['total' => $orders->count(), 'list' => $list]);
    }

    // ── private helpers ──────────────────────────────────────────────────────

    /**
     * 2차 요청(R2-03)의 검색조건을 건다.
     *
     * 이 표는 팝빌에서 받아 온 발행 내역이라 환자 자격·처방 유형 같은 우리 값이 없다.
     * 다만 `order_number` 가 있어 주문을 타고 처방전까지 갈 수 있다. 그 값들이 JSON 안에
     * 있던 동안에는 조인해도 걸러낼 수 없었는데, 컬럼으로 올라온 뒤로는 된다.
     *
     * 없는 조건(서류 담당자·메모)은 걸지 않는다. 빈 칸을 만들어 두면 담당자가 넣어 보고
     * 아무것도 안 걸러지는 것을 겪는다.
     */
    private function applyFilters($query, Request $request): void
    {
        // 판매번호 — 주문번호가 그대로 들어 있다
        if ($v = trim((string) $request->query('order_number'))) {
            $query->where('order_number', 'like', "%{$v}%");
        }

        if ($v = trim((string) $request->query('customer_name'))) {
            $query->where('customer_name', 'like', "%{$v}%");
        }

        // 주민번호 — 휴대폰번호가 들어간 건이 섞여 있어 부분검색으로 둔다
        if ($v = preg_replace('/\D/', '', (string) $request->query('identity_num'))) {
            $query->where('identity_num', 'like', "%{$v}%");
        }

        // 휴대폰번호(요청서 6쪽). 앞자리만 기억하는 일이 잦아 부분검색이다.
        if ($v = preg_replace('/\D/', '', (string) $request->query('hp'))) {
            $query->where('hp', 'like', "%{$v}%");
        }

        if ($v = trim((string) $request->query('confirm_num'))) {
            $query->where('confirm_num', 'like', "%{$v}%");
        }

        // 금액 — 범위로 받는다. 정확히 일치하는 금액을 아는 경우는 드물다.
        foreach ([['supply_cost', 'supply_min', 'supply_max'],
                  ['tax',         'tax_min',    'tax_max'],
                  ['total_amount','amount_min', 'amount_max']] as [$col, $min, $max]) {
            if (($v = $request->query($min)) !== null && $v !== '') {
                $query->where($col, '>=', (int) $v);
            }
            if (($v = $request->query($max)) !== null && $v !== '') {
                $query->where($col, '<=', (int) $v);
            }
        }

        // 발급일자 — 거래일시(trade_dt)와 다른 값이라 따로 받는다
        if ($v = preg_replace('/\D/', '', (string) $request->query('issue_from'))) {
            $query->where('issue_dt', '>=', $v . '000000');
        }
        if ($v = preg_replace('/\D/', '', (string) $request->query('issue_to'))) {
            $query->where('issue_dt', '<=', $v . '235959');
        }

        /* 자격·유형은 우리 쪽 값이라 주문을 타고 처방전까지 가야 한다.
           order_number 가 없는 발행 건(수기 발행 등)은 애초에 걸러질 값이 없다. */
        foreach (['benefit_class' => 'benefit_class', 'acc_type' => 'counsel_acc_add_type'] as $param => $column) {
            if ($v = trim((string) $request->query($param))) {
                $query->whereIn('order_number', function ($sub) use ($column, $v) {
                    $sub->select('orders.order_number')
                        ->from('orders')
                        ->join('prescriptions', 'prescriptions.id', '=', 'orders.prescription_id')
                        ->where("prescriptions.{$column}", $v);
                });
            }
        }
    }

    /**
     * 팝빌이 주는 칸을 남김없이 내준다 (요청서 6쪽, 2026-08-31).
     *
     * 예전에는 스물몇 가운데 스물을 버리고 여덟만 내줬다. 그래서 담당자가 전송 결과나
     * 취소 사유를 보려면 팝빌 사이트를 따로 열어야 했다.
     *
     * 팝빌에 없는 칸(팩스번호ㆍ추가공제ㆍ거래방법ㆍ비고ㆍ인쇄여부)은 세우지 않는다 —
     * 빈 칸을 만들어 두면 「아직 안 받아 왔나」 하고 되묻게 된다.
     */
    private function toListItem(CashbillRecord $r, ?OrderGridExtras $extras = null): array
    {
        $o = $r->order;

        return [
            // ── 팝빌이 주는 그대로 ────────────────────────────
            'mgtKey'       => $r->mgt_key,
            'itemKey'      => $r->item_key,
            'tradeDT'      => $r->trade_dt,
            'tradeDate'    => $r->trade_date,
            'issueDT'      => $r->issue_dt,
            'regDT'        => $r->reg_dt,
            'tradeType'    => $r->trade_type,
            'tradeUsage'   => $r->trade_usage,
            'taxationType' => $r->taxation_type,
            'totalAmount'  => $r->total_amount,
            'supplyCost'   => $r->supply_cost,
            'tax'          => $r->tax,
            'serviceFee'   => $r->service_fee,
            'customerName' => $r->customer_name,
            'itemName'     => $r->item_name,
            'identityNum'  => $r->identity_num,
            'hp'           => $r->hp,
            'email'        => $r->email,
            'confirmNum'   => $r->confirm_num,
            // 취소 건이 가리키는 원본 — 무엇을 물렸는지는 이 둘로 찾는다
            'orgConfirmNum' => $r->org_confirm_num,
            'orgTradeDate'  => $r->org_trade_date,
            'stateCode'    => $r->state_code,
            'stateDT'      => $r->state_dt,
            // 취소 사유가 여기 실려 온다
            'stateMemo'    => $r->state_memo,
            'ntsresult'    => $r->nts_result,
            'ntsresultDT'  => $r->nts_result_dt,
            'ntsresultCode'    => $r->nts_result_code,
            'ntsresultMessage' => $r->nts_result_message,
            'ntsSendDT'    => $r->nts_send_dt,
            // 가맹점 — 우리가 아니라 남의 것으로 발행한 건이 섞여 있다
            'franchiseCorpNum'  => $r->franchise_corp_num,
            'franchiseCorpName' => $r->franchise_corp_name,
            'orderNumber'  => $r->order_number,
            'syncedAt'     => $r->synced_at?->toDateTimeString(),

            // ── 우리 주문을 타고 온 칸 ────────────────────────
            'rxNumber'     => $o?->prescription?->rx_number,
            'patientName'  => $o?->patient?->name,
        ] + ($extras && $o
                ? $extras->rx($o->prescription, $o->patient)
                  + $extras->ww($o, $o->prescription, $o->patient)
                  + $extras->of($o)
                : []);
    }

    private function toDetailItem(CashbillRecord $r): array
    {
        return [
            'mgtKey'              => $r->mgt_key,
            'tradeDT'             => $r->trade_dt,
            'tradeDate'           => $r->trade_date,
            'tradeType'           => $r->trade_type,
            'tradeUsage'          => $r->trade_usage,
            'taxationType'        => $r->taxation_type,
            'totalAmount'         => $r->total_amount,
            'supplyCost'          => $r->supply_cost,
            'tax'                 => $r->tax,
            'serviceFee'          => $r->service_fee,
            'identityNum'         => $r->identity_num,
            'customerName'        => $r->customer_name,
            'itemName'            => $r->item_name,
            'orderNumber'         => $r->order_number,
            'email'               => $r->email,
            'hp'                  => $r->hp,
            'confirmNum'          => $r->confirm_num,
            'orgConfirmNum'       => $r->org_confirm_num,
            'orgTradeDate'        => $r->org_trade_date,
            'stateCode'           => $r->state_code,
            'stateDT'             => $r->state_dt,
            'issueDT'             => $r->issue_dt,
            'ntsresult'           => $r->nts_result,
            'ntsresultDT'         => $r->nts_result_dt,
            'ntsresultCode'       => $r->nts_result_code,
            'ntsresultMessage'    => $r->nts_result_message,
            'franchiseCorpNum'    => $r->franchise_corp_num,
            'franchiseCorpName'   => $r->franchise_corp_name,
            'franchiseCEOName'    => $r->franchise_ceo_name,
            'franchiseAddr'       => $r->franchise_addr,
            'franchiseTEL'        => $r->franchise_tel,
            'syncedAt'            => $r->synced_at?->toDateTimeString(),
        ];
    }
}
