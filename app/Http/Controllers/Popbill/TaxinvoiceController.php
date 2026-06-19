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

        $tiRecords = $tiQuery->orderByDesc('write_date')->orderByDesc('id')->get()
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
            ]);

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
