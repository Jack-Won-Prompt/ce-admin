<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\CashbillRecord;
use App\Models\Order;
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
     *   per_page    (기본 15, 최대 100)
     *   order       D(내림차순)|A(오름차순) - trade_dt 기준
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
            'page'       => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|min:1|max:100',
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

        $total   = $query->count();
        $records = $query->forPage($page, $perPage)->get();

        return response()->json([
            'total'     => $total,
            'perPage'   => $perPage,
            'pageNum'   => $page,
            'pageCount' => (int) ceil($total / $perPage),
            'list'      => $records->map(fn(CashbillRecord $r) => $this->toListItem($r)),
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

        $orders = Order::with(['patient', 'prescription'])
            ->whereIn('cash_receipt_status', ['issued', 'cancelled'])
            ->whereBetween('cash_receipt_issued_at', [$start, $end])
            ->orderByDesc('cash_receipt_issued_at')
            ->get();

        $list = $orders->map(fn(Order $o) => [
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
        ]);

        return response()->json(['total' => $orders->count(), 'list' => $list]);
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function toListItem(CashbillRecord $r): array
    {
        return [
            'mgtKey'       => $r->mgt_key,
            'tradeDT'      => $r->trade_dt,
            'tradeDate'    => $r->trade_date,
            'tradeType'    => $r->trade_type,
            'tradeUsage'   => $r->trade_usage,
            'taxationType' => $r->taxation_type,
            'totalAmount'  => $r->total_amount,
            'supplyCost'   => $r->supply_cost,
            'tax'          => $r->tax,
            'serviceFee'   => $r->service_fee,
            'customerName' => $r->customer_name,
            'itemName'     => $r->item_name,
            'confirmNum'   => $r->confirm_num,
            'stateCode'    => $r->state_code,
            'ntsresult'    => $r->nts_result,
            'ntsresultDT'  => $r->nts_result_dt,
            'issueDT'      => $r->issue_dt,
            'syncedAt'     => $r->synced_at?->toDateTimeString(),
        ];
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
