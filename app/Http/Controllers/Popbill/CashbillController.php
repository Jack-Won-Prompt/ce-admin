<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\CashbillRecord;
use App\Models\Order;
use App\Support\BillingStrategy;
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
        $records = $query->with(['order.patient', 'order.prescription.billingOffice', 'order.items.lots', 'order.operationUser', 'order.tossPayment'])
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
    /**
     * 카드로 받은 건의 결제 이력 (2026-09-23 지시).
     *
     * 이 화면은 현금영수증만 세웠다. 그런데 본인부담을 카드로 받은 건은 현금영수증이
     * 나가지 않고 **카드매출전표**가 증빙이라, 그 건들은 이 목록에 한 줄도 없었다 —
     * 「현금/카드영수증」 한 자리에서 둘을 함께 보려면 카드 쪽도 세워야 한다.
     *
     * 이력의 원천은 **결제 링크**다. toss_payments 는 한 주문에 한 줄이라 재결제가
     * 앞 줄을 덮어써(2026-09-23 확인), 승인 → 취소 → 재승인 세 걸음이 남지 않는다.
     * 결제 링크는 걸음마다 한 줄이 서고 상태(sent·paid·cancelled·failed)가 그대로
     * 남으므로, 정정을 거친 건의 이력이 온전히 읽힌다.
     */
    public function cardReceipts(Request $request): JsonResponse
    {
        /* 이 화면은 날짜를 팝빌 꼴(Ymd)로 보낸다 — 하이픈이 없다. 그대로 넣으면
           MySQL 이 날짜로 읽지 못해 한 줄도 나오지 않는다. 두 꼴을 다 받는다. */
        $날짜 = function (?string $v): ?string {
            $v = trim((string) $v);
            if ($v === '') { return null; }
            return preg_match('/^\d{8}$/', $v) ? substr($v,0,4).'-'.substr($v,4,2).'-'.substr($v,6,2) : $v;
        };

        $from = $날짜($request->query('start_date'));
        $to   = $날짜($request->query('end_date'));

        $q = \App\Models\PaymentLink::with(['order.patient', 'order.prescription'])
            ->where('method', 'card');

        if ($from) { $q->whereDate('created_at', '>=', $from); }
        if ($to)   { $q->whereDate('created_at', '<=', $to); }

        $상태글 = ['sent' => '발송', 'paid' => '승인', 'cancelled' => '취소', 'failed' => '실패'];

        $rows = $q->orderByDesc('id')->limit(500)->get()->map(fn ($l) => [
            'record_type' => 'card',
            'id'          => $l->id,
            'date'        => $l->created_at?->format('Y-m-d'),
            'datetime'    => ($l->paid_at ?? $l->sent_at ?? $l->created_at)?->format('Y-m-d H:i'),
            'order_no'    => $l->order?->order_number ?? '',
            'rx_number'   => $l->order?->prescription?->rx_number ?? '',
            'patient'     => $l->order?->patient?->name ?? '',
            /* 취소는 뺀 금액으로 적는다 — 승인과 나란히 놓았을 때 합이 맞아야 읽힌다 */
            'amount'      => $l->status === 'cancelled' ? -(int) $l->amount : (int) $l->amount,
            'status'      => $상태글[$l->status] ?? $l->status,
            'method'      => '카드',
            'payment_key' => $l->payment_key ?? '',
            'receiver'    => $l->receiver ?? '',
        ]);

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function orderReceipts(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        $start = \Carbon\Carbon::createFromFormat('Ymd', $request->query('start_date'))->startOfDay();
        $end   = \Carbon\Carbon::createFromFormat('Ymd', $request->query('end_date'))->endOfDay();

        /* 이름으로도 찾는다 (2026-09-14 지시 · 확인요청 7쪽).

           팝빌에서 받아 온 줄은 customer_name 으로 거르는 자리가 이미 있었는데
           (applyFilters), 처방전에서 낸 줄은 그 자리가 없어 이름을 쳐도 함께 남았다 —
           한 표에 섞여 서는 두 갈래라 한쪽만 걸리면 걸러지지 않은 것으로 보인다. */
        $이름 = trim((string) $request->query('name'));

        $orders = Order::with(['patient', 'prescription.billingOffice', 'items.lots', 'operationUser', 'tossPayment'])
            ->whereIn('cash_receipt_status', ['issued', 'cancelled'])
            ->whereBetween('cash_receipt_issued_at', [$start, $end])
            ->when($이름 !== '', fn ($q) => $this->이름거르개($q, $이름))
            ->orderByDesc('cash_receipt_issued_at')
            ->get();

        /* 우리 표에 이미 들어온 건은 여기서 세우지 않는다 (2026-09-22 확인요청 2쪽).

           이 화면은 두 벌을 합쳐 그린다 — 팝빌에서 받아 둔 cashbill_records 와, 주문에
           적힌 현금영수증이다. 발행하면 그 자리에서 우리 표로 들이므로(registIssue
           뒤의 refreshOne) 같은 발행이 두 줄로 섰다.

           그냥 두 줄이면 눈에 띄기라도 하는데, 정정한 건에서는 뜻이 뒤집힌다. 팝빌
           쪽에는 「정정 전 발행 · 취소 · 정정 후 발행」 세 줄이 제대로 서 있고, 주문
           쪽은 **마지막 상태 한 줄**만 세운다 — 그 한 줄이 셋 위에 겹쳐 서서, 담당자
           눈에는 정정 전후가 뒤섞인 채 「초과 금액 1개 라인」으로 읽혔다.

           오간 자취는 우리 표가 온전히 들고 있으므로 그쪽에 맡긴다. 아직 들어오지
           않은 건(동기화가 실패했거나 옛 건)만 주문에서 세운다 — 그래야 낸 것이
           화면에서 사라지지 않는다. */
        $이미들어온것 = \App\Models\CashbillRecord::where('corp_num', $request->query('corp_num', config('popbill.test.corp_num')))
            ->whereIn('order_number', $orders->pluck('order_number'))
            ->pluck('order_number')
            ->flip();

        $orders = $orders->reject(fn (Order $o) => $이미들어온것->has($o->order_number))->values();

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

        /* ── 발행 대기 ──────────────────────────────────────────────
           「계산서 발행」 화면이 하던 일이다 — 2026-09-01 요청으로 그 화면을 없애고
           여기로 모았다. 낸 것만 보이면 「무엇이 남았는가」를 이 화면에서 알 수 없다.
           대상은 청구전략이 현금영수증으로 정한 건이다 — 처방외ㆍ산재ㆍ자동차보험은
           본인이 전액을 내고, 처방전ㆍ일반도 본인부담 10%가 현금영수증으로 간다. */
        $pendingQuery = Order::with(['patient', 'prescription.billingOffice', 'items.lots', 'operationUser', 'tossPayment'])
            ->whereIn('status', \App\Models\Order::OPEN_AFTER_CONFIRM);

        BillingStrategy::targets($pendingQuery, 'cash_receipt');

        $pending = $pendingQuery
            ->when($이름 !== '', fn ($q) => $this->이름거르개($q, $이름))
            ->where(fn ($q) => $q->whereNull('cash_receipt_status')
                                 ->orWhere('cash_receipt_status', '!=', 'issued'))
            /* 카드로 받은 건은 대기에 세우지 않는다 (2026-09-26 지시).

               현금영수증은 **가상계좌ㆍ무통장입금일 때만** 낸다 — 카드는 카드사가
               국세청에 신고하고, 우리 증빙은 카드매출전표다(DepositAutoIssue 주석).
               자동 발행은 그 갈래를 옳게 지나가는데 **이 대기 목록만 결제수단을 보지
               않아** 카드 건이 「발행 대기」로 서 있었다. 담당자가 그 줄을 눌러 발행하면
               카드전표와 현금영수증이 겹쳐 **국세청에 두 번 신고**된다.

               토스 승인이 남아 있는 건을 카드로 본다. 결제수단 칸(pay_method)은 「무엇으로
               안내할 것인가」이기도 해서 받기 전에도 「링크페이」로 적혀 있다 — 그것만
               보고 빼면 아직 받지 않은 건까지 대기에서 사라진다. */
            ->whereDoesntHave('tossPayment', fn ($q) => $q->where('status', 'DONE'))
            /* 언제 것인가 — 나간 날이 있으면 그 날, 없으면 받은 날이다. */
            ->where(fn ($q) => $q->whereBetween('delivered_at', [$start, $end])
                                 ->orWhere(fn ($x) => $x->whereNull('delivered_at')
                                                        ->whereBetween('created_at', [$start, $end])))
            ->orderByDesc('id')
            ->get();

        $pendingExtras = OrderGridExtras::forPatients($pending->pluck('patient_id'));

        $pendingList = $pending->map(function (Order $o) use ($pendingExtras) {
            $rx   = $o->prescription;
            $rate = (int) (BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class)['cash_receipt'] ?? 0);
            /* 제품 금액(본인부담 + 기관부담)에 비율을 곱한다 — 자동 발행이 세는 법과
               같다. 배송비는 없다(2026-09-03 확정). total_amount 에는 옛 건의 배송비가
               섞여 있어 그 칸은 쓰지 않는다. */
            $amount = (int) round(((int) ($o->patient_copay ?? 0) + (int) ($o->nhis_amount ?? 0)) * $rate / 100);
            $at = $o->delivered_at ?? $o->created_at;
            $구분 = $o->patient?->deduction === '지출증빙' ? 'business_expense' : 'income_deduction';

            return [
                'source'           => 'order',
                '_sortKey'         => $at?->format('YmdHis') ?? '',
                'tradeType'        => '발행 대기',
                'orderId'          => $o->id,
                'orderNumber'      => $o->order_number,
                'rxNumber'         => $rx?->rx_number,
                'patientName'      => $o->patient?->name ?? $rx?->patient_name_ocr ?? '—',
                'receiptNo'        => null,
                /* 발행 구분은 거래처에 적어 둔 것을 따른다 (2026-09-22 확인요청 4쪽) —
                   자동 발행(DepositAutoIssue::cashReceipt)이 보는 것과 같은 잣대다.
                   여기만 소득공제로 박아 두면 대기 줄과 실제로 나가는 것이 어긋난다. */
                'receiptTypeKey'   => $구분,
                'receiptTypeLabel' => Order::CASH_RECEIPT_TYPE_LABELS[$구분] ?? '소득공제',
                'identifier'       => $o->patient?->cash_receipt_no ?: $o->patient?->mobile,
                'amount'           => $amount,
                'status'           => 'pending',
                'issuedAt'         => null,
                'cancelledAt'      => null,
            ] + $pendingExtras->rx($rx, $o->patient)
              + $pendingExtras->ww($o, $rx, $o->patient)
              + $pendingExtras->of($o);
        });

        $all = $list->concat($pendingList)->values();

        return response()->json(['total' => $all->count(), 'list' => $all]);
    }

    /**
     * 이름으로 주문을 거른다 — 거래처에 적힌 이름과 처방전에서 읽은 이름을 함께 본다.
     *
     * 거래처가 아직 이어지지 않은 건은 patients 에 줄이 없다. 그때는 처방전의
     * patient_name_ocr 이 화면에 서므로, 그 값으로도 찾혀야 한다 — 보이는 이름으로
     * 쳤는데 안 나오면 없는 건으로 읽는다.
     */
    private function 이름거르개($query, string $이름)
    {
        return $query->where(function ($w) use ($이름) {
            $w->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$이름}%"))
              ->orWhereHas('prescription', fn ($p) => $p->where('patient_name_ocr', 'like', "%{$이름}%"));
        });
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
