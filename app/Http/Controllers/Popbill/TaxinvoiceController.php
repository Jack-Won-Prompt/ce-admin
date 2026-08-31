<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\PopbillTaxinvoice;
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
        $tiRows = $tiQuery->with(['order.patient', 'order.prescription.billingOffice', 'order.items.lots', 'order.operationUser'])
            ->orderByDesc('write_date')->orderByDesc('id')->get();

        $tiExtras = \App\Support\OrderGridExtras::forPatients($tiRows->pluck('order.patient_id'));

        $tiRecords = $tiRows
            ->map(fn($r) => [
                'record_type'     => 'taxinvoice',
                'sort_date'       => $r->write_date,
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
                'totalAmount'     => (string) $r->total_amount,
                'ntsconfirmNum'   => $r->nts_confirm_num,

                // 팝빌이 주는 나머지 (요청서 6쪽)
                'invoicerCeoName' => $r->invoicer_ceo_name,
                'invoiceeCeoName' => $r->invoicee_ceo_name,
                'stateDT'         => $r->state_dt,
                'isFinal'         => (bool) $r->is_final,
                'syncedAt'        => $r->synced_at?->toDateTimeString(),

                // 우리 주문을 타고 온 것
                'orderNumber'     => $r->order?->order_number,
                'rxNumber'        => $r->order?->prescription?->rx_number,
                'patientName'     => $r->order?->patient?->name,
            ] + ($r->order
                    ? $tiExtras->rx($r->order->prescription, $r->order->patient)
                      + $tiExtras->ww($r->order, $r->order->prescription, $r->order->patient)
                      + $tiExtras->of($r->order)
                    : []));

        // ── 3. 처방전 확인·주문완료 내역 ───────────────────────────
        $startDT = \Carbon\Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $endDT   = \Carbon\Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        $rxRecords = \App\Models\Prescription::whereIn('status', ['approved', 'ordered'])
            ->whereBetween('reviewed_at', [$startDT, $endDT])
            ->orderByDesc('reviewed_at')
            ->get()
            ->map(fn($p) => [
                'record_type'     => 'prescription',
                'sort_date'       => $p->reviewed_at?->format('Ymd') ?? $startDate,
                'invoicerMgtKey'  => null,
                'invoiceeMgtKey'  => null,
                'trusteeMgtKey'   => null,
                'itemKey'         => null,
                'stateCode'       => null,
                'taxType'         => null,
                'purposeType'     => null,
                'issueType'       => null,
                'writeDate'       => $p->reviewed_at?->format('Ymd'),
                'issueDT'         => null,
                'invoicerCorpNum' => null,
                'invoicerCorpName'=> null,
                'invoiceeCorpNum' => null,
                'invoiceeCorpName'=> $p->patient_name_ocr ?? '—',
                'supplyCostTotal' => (string) intval($p->patient_copay ?? 0),
                'taxTotal'        => '0',
                'totalAmount'     => (string) intval($p->patient_copay ?? 0),
                'ntsconfirmNum'   => null,
                'rx_number'       => $p->rx_number,
                'rx_status'       => $p->status,
            ]);

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
    public function registIssue(Request $request): JsonResponse
    {
        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');

        $invoice = $this->svc->newInvoice();

        $invoice->writeDate       = $request->input('write_date', now()->format('Ymd'));
        $invoice->taxType         = $request->input('tax_type', '과세');
        $invoice->issueType       = $request->input('issue_type', '정발행');
        $invoice->purposeType     = $request->input('purpose_type', '영수');
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
}
