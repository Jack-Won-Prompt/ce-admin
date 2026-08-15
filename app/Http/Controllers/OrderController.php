<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionDocument;
use App\Services\Popbill\CashbillService;
use App\Services\Popbill\MessageService;
use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    // ── 목록 ──────────────────────────────────────────────
    public function index(Request $request): View
    {
        $query = Order::with(['patient', 'prescription', 'creator'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"))
                    ->orWhere('product_name', 'like', "%{$q}%");
            });
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $statusCounts = Order::selectRaw('status, count(*) as cnt')->groupBy('status')
                            ->pluck('cnt', 'status');

        // wwGrid: 필터된 전체를 그리드용 배열로 (클라이언트사이드)
        $gridData = $query->get()->map(function ($o) {
            $ww = $o->withworks_so_no
                ? trim($o->withworks_so_no . ($o->withworks_status_label ? ' · ' . $o->withworks_status_label : ''))
                : '';
            return [
                'id'        => $o->id,
                'order_no'  => $o->order_number,
                'patient'   => $o->patient?->name ?? '',
                'product'   => $o->product_name ?? '',
                'qty'       => (int) ($o->quantity ?? 1),
                'copay'     => (int) $o->patient_copay,
                'shipping'  => (int) $o->shipping_fee,
                'total'     => (int) $o->total_amount,
                'address'   => $o->shipping_address ?? '',
                'so_type'   => \App\Models\Order::SO_TYPE_LABELS[$o->so_type][0] ?? '',
                'status'    => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                'withworks' => $ww,
                'created'   => $o->created_at->format('Y-m-d H:i'),
            ];
        })->values();

        return view('orders.index', compact('gridData', 'statusCounts'));
    }

    // ── 상세 ──────────────────────────────────────────────
    public function show(Order $order): View
    {
        // 다른 화면의 '상세내용' 탭에 주입될 때(?partial=1)는 크롬 없는 프래그먼트로 렌더
        if (request()->boolean('partial')) {
            view()->share('layout', 'layouts.partial');
        }

        $order->load(['patient', 'prescription.items', 'creator']);

        $withworksStatus = null;
        if ($order->withworks_so_no) {
            $baseUrl = rtrim(config('services.todoworks.api_url', ''), '/');
            $token   = config('services.todoworks.token');
            if ($baseUrl && $token) {
                try {
                    $res = Http::withToken($token)->timeout(5)
                        ->get("{$baseUrl}/api/v1/ce-admin/so_show", [
                            'ce_order_number' => $order->order_number,
                        ]);
                    if ($res->successful() && ($res->json('success') ?? false)) {
                        $withworksStatus = $res->json('result');
                        $ship = $withworksStatus['ship'] ?? null;
                        $order->update([
                            'withworks_status'            => $withworksStatus['status'] ?? null,
                            'withworks_status_label'      => $withworksStatus['status_label'] ?? null,
                            'withworks_status_at'         => now(),
                            'withworks_ship_no'           => $ship['ship_no'] ?? null,
                            'withworks_ship_status'       => $ship['ship_status'] ?? null,
                            'withworks_ship_status_label' => $ship['ship_status_label'] ?? null,
                            'withworks_tracking_no'       => $ship['tracking_no'] ?? null,
                            'withworks_ship_at'           => $ship ? now() : null,
                        ]);
                    }
                } catch (\Throwable) {}
            }
        }

        return view('orders.show', compact('order', 'withworksStatus'));
    }

    // ── 주문 생성 ─────────────────────────────────────────
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'prescription_id'         => 'required|exists:prescriptions,id',
            'items'                   => 'nullable|array',
            'items.*.product_name'    => 'nullable|string|max:200',
            'items.*.product_code'    => 'nullable|string|max:50',
            'items.*.quantity'        => 'nullable|integer|min:1',
            'items.*.product_price'   => 'nullable|numeric|min:0',
            'items.*.insurance_price' => 'nullable|numeric|min:0',
            'items.*.nhis_amount'     => 'nullable|numeric|min:0',
            'items.*.patient_copay'   => 'nullable|numeric|min:0',
            'total_nhis'              => 'nullable|numeric|min:0',
            'patient_copay'           => 'nullable|numeric|min:0',
            'shipping_fee'            => 'nullable|numeric|min:0',
            'shipping_address'        => 'nullable|string|max:200',
            'shipping_recipient'      => 'nullable|string|max:100',
            'so_type'                 => 'nullable|string|in:1013,1016,1022',
        ]);

        $prescription = Prescription::findOrFail($request->prescription_id);

        // 이미 주문이 있으면 반환
        if ($prescription->order()->exists()) {
            return response()->json(['success' => false, 'message' => '이미 주문이 생성된 처방전입니다.'], 409);
        }

        // items 배열에서 대표 제품 및 합계 계산
        $items       = collect($request->input('items', []))->filter(fn($i) => !empty($i['product_name']));
        $firstItem   = $items->first() ?? [];
        $totalCopay  = $request->patient_copay ?? $items->sum('patient_copay');
        $totalNhis   = $request->total_nhis    ?? $items->sum('nhis_amount');
        $unitPrice   = (float)($firstItem['insurance_price'] ?? $firstItem['product_price'] ?? 0);
        $totalQty    = $items->sum('quantity') ?: 1;

        // 다중 제품이면 품목명을 note에 기록
        $productNames = $items->pluck('product_name')->implode(', ');

        $shippingFee = $request->shipping_fee ?? 3000;
        $totalAmount = $totalCopay + $shippingFee;

        $order = Order::create([
            'order_number'     => Order::generateOrderNumber(),
            'prescription_id'  => $prescription->id,
            'patient_id'       => $prescription->patient_id,
            'created_by'       => Auth::id(),
            'product_name'     => $firstItem['product_name'] ?? ($prescription->product_name ?? '-'),
            'product_code'     => $firstItem['product_code'] ?? $prescription->product_code,
            'quantity'         => $totalQty,
            'unit_price'       => $unitPrice,
            'nhis_amount'      => $totalNhis,
            'patient_copay'    => $totalCopay,
            'shipping_fee'     => $shippingFee,
            'total_amount'     => $totalAmount,
            'shipping_address'   => $request->shipping_address,
            'shipping_recipient' => $request->shipping_recipient,
            'estimated_delivery' => now()->addDays(3)->format('Y-m-d'),
            'status'             => 'pending',
            'so_type'            => $request->so_type ?? '1013',
            'note'             => $items->count() > 1 ? "제품 목록: {$productNames}" : null,
        ]);

        /* 품목을 줄 단위로 남긴다. orders 의 product_name·quantity 는 목록 화면이 쓰는 요약이고,
           두 번째 제품부터는 여기에만 있다. 공단 제출용 구매내역 서류도 이 줄을 근거로 만든다. */
        foreach ($items->values() as $i => $item) {
            $order->items()->create([
                'product_name'    => $item['product_name'],
                'product_code'    => $item['product_code']    ?? null,
                'quantity'        => max(1, (int) ($item['quantity'] ?? 1)),
                'product_price'   => $item['product_price']   ?? null,
                'insurance_price' => $item['insurance_price'] ?? null,
                'nhis_amount'     => $item['nhis_amount']     ?? null,
                'patient_copay'   => $item['patient_copay']   ?? null,
                'sort_order'      => $i,
            ]);
        }

        // 처방전 상태 업데이트
        $prescription->update(['status' => 'ordered']);

        activity()->causedBy(Auth::user())
            ->performedOn($order)
            ->log("{$order->order_number} 주문 생성");

        // SMS 알림
        $mobile      = $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? null;
        $patientName = $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '';
        if ($mobile) {
            try {
                $delivery    = $order->estimated_delivery->format('Y-m-d');
                $copayFmt    = number_format((int) $order->patient_copay);
                $totalFmt    = number_format((int) $order->total_amount);
                $smsContent  = "[콜로플라스트] {$patientName}님 주문이 확정되었습니다.\n"
                             . "- 주문번호: {$order->order_number}\n"
                             . "- 제품: {$order->product_name}\n"
                             . "- 환자부담금: {$copayFmt}원 (배송비 포함 {$totalFmt}원)\n"
                             . "- 예상배송일: {$delivery}";
                app(MessageService::class)->send($mobile, $smsContent, $patientName);
            } catch (\Throwable $e) {
                Log::warning('[Order] SMS 발송 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success'      => true,
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'estimated_delivery' => $order->estimated_delivery->format('Y-m-d'),
            'total_amount' => $order->total_amount,
        ]);
    }

    // ── 주문 수정 ─────────────────────────────────────────
    public function update(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'items'                   => 'nullable|array',
            'items.*.product_name'    => 'nullable|string|max:200',
            'items.*.product_code'    => 'nullable|string|max:50',
            'items.*.quantity'        => 'nullable|integer|min:1',
            'items.*.product_price'   => 'nullable|numeric|min:0',
            'items.*.insurance_price' => 'nullable|numeric|min:0',
            'items.*.nhis_amount'     => 'nullable|numeric|min:0',
            'items.*.patient_copay'   => 'nullable|numeric|min:0',
            'shipping_address'        => 'nullable|string|max:200',
            'shipping_recipient'      => 'nullable|string|max:100',
            'shipping_postcode'       => 'nullable|string|max:10',
            'so_type'                 => 'nullable|string|in:1013,1016,1022',
            'delivery_date'           => 'nullable|date',
        ]);

        $items      = collect($request->input('items', []))->filter(fn($i) => !empty($i['product_name']));
        $firstItem  = $items->first() ?? [];
        $totalCopay = $request->patient_copay ?? $items->sum('patient_copay');
        $totalNhis  = $request->total_nhis    ?? $items->sum('nhis_amount');
        $unitPrice  = (float)($firstItem['insurance_price'] ?? $firstItem['product_price'] ?? 0);
        $totalQty   = $items->sum('quantity') ?: 1;
        $shippingFee = $order->shipping_fee ?? 3000;
        $totalAmount = $totalCopay + $shippingFee;
        $productNames = $items->pluck('product_name')->implode(', ');

        $order->update([
            'product_name'     => $firstItem['product_name'] ?? $order->product_name,
            'product_code'     => $firstItem['product_code'] ?? $order->product_code,
            'quantity'         => $totalQty,
            'unit_price'       => $unitPrice,
            'nhis_amount'      => $totalNhis,
            'patient_copay'    => $totalCopay,
            'total_amount'     => $totalAmount,
            'shipping_address'   => $request->shipping_address   ?? $order->shipping_address,
            'shipping_recipient' => $request->shipping_recipient ?? $order->shipping_recipient,
            'so_type'            => $request->so_type            ?? $order->so_type,
            'note'             => $items->count() > 1 ? "제품 목록: {$productNames}" : $order->note,
        ]);

        activity()->causedBy(Auth::user())
            ->performedOn($order)
            ->log("{$order->order_number} 주문 수정");

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
        ]);
    }

    // ── 주문 삭제 ─────────────────────────────────────────
    public function destroy(Order $order): \Illuminate\Http\JsonResponse
    {
        $prescription = $order->prescription;

        // 처방전 상태 복원
        if ($prescription) {
            $prescription->update(['status' => 'approved']);
        }

        activity()->causedBy(Auth::user())
            ->performedOn($order)
            ->log("{$order->order_number} 주문 삭제");

        $order->delete();

        return response()->json(['success' => true, 'message' => '주문이 삭제되었습니다.']);
    }


    // ── 운송장 번호 저장 ──────────────────────────────────
    public function updateTracking(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $request->validate(['tracking_number' => 'required|string|max:100']);

        $order->update(['tracking_number' => $request->tracking_number]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('운송장 번호 저장: ' . $request->tracking_number);

        return response()->json(['success' => true, 'message' => '운송장 번호가 저장되었습니다.']);
    }

    // ── 주문 상태 변경 ────────────────────────────────────
    public function updateStatus(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $request->validate(['status' => 'required|in:confirmed,shipping,delivered,cancelled']);

        $order->update([
            'status'       => $request->status,
            'delivered_at' => $request->status === 'delivered' ? now() : null,
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('주문 상태 변경: ' . $request->status);

        return response()->json(['success' => true]);
    }

    // ── 세금계산서 발행 (팝빌) ───────────────────────────
    public function issueTaxInvoice(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        if ($order->tax_invoice_status === 'issued') {
            return response()->json(['success' => false, 'message' => '이미 발행된 세금계산서입니다.'], 409);
        }

        $data = $request->validate([
            'tax_invoice_type'     => 'required|in:electronic,manual',
            // 공급받는자가 개인이면 사업자번호 자리에 주민등록번호가 들어간다.
            // 팝빌은 invoiceeType 으로 구분하며, '사업자' 로 두고 13자리를 보내면 거절한다.
            'tax_invoice_invoicee' => 'nullable|in:사업자,개인',
            'tax_invoice_biz_name' => 'required|string|max:100',
            'tax_invoice_ceo_name' => 'required|string|max:50',
            'tax_invoice_biz_no'   => 'nullable|string|max:20',
            'tax_invoice_email'    => 'nullable|email|max:100',
            'tax_invoice_supply'   => 'required|numeric|min:0',
            'tax_invoice_vat'      => 'required|numeric|min:0',
        ]);

        $invoiceeType = $data['tax_invoice_invoicee'] ?? '사업자';
        $invoiceeNum  = preg_replace('/\D/', '', (string) ($data['tax_invoice_biz_no'] ?? ''));

        /* 개인 건에 번호를 적어 보내지 않았으면 처방전의 주민번호로 발행한다.
           화면은 주민번호를 쓰기 전용으로 두므로 담당자가 번호를 볼 수 없다 —
           본문으로 내려보내지 않고 여기서만 연다. 복호화는 감사로그가 남는다. */
        if ($invoiceeType === '개인' && $invoiceeNum === '') {
            $invoiceeNum = preg_replace('/\D/', '',
                (string) $order->prescription?->residentNoOcrFor('tax_invoice'));
        }

        if ($invoiceeType === '개인' && strlen($invoiceeNum) !== 13) {
            return response()->json([
                'success' => false,
                'message' => '개인 발행에는 주민등록번호 13자리가 필요합니다. 처방전에 주민등록번호를 먼저 저장해 주세요.',
            ], 422);
        }
        if ($invoiceeType === '사업자' && strlen($invoiceeNum) !== 10) {
            return response()->json([
                'success' => false,
                'message' => '사업자등록번호 10자리를 입력해 주세요.',
            ], 422);
        }

        try {
            $corpNum = config('popbill.test.corp_num');
            $userId  = config('popbill.test.user_id');
            $mgtKey  = 'TI' . now()->format('Ymd') . str_pad($order->id, 6, '0', STR_PAD_LEFT);
            $supply  = (int) $data['tax_invoice_supply'];
            $vat     = (int) $data['tax_invoice_vat'];

            $svc    = app(TaxinvoiceService::class);
            $inv    = $svc->newInvoice();
            $detail = $svc->newDetail();

            $inv->writeDate          = now()->format('Ymd');
            $inv->chargeDirection    = '정과금';
            $inv->issueType          = '정발행';
            $inv->taxType            = '과세';
            $inv->invoicerCorpNum    = $corpNum;
            $inv->invoicerMgtKey     = $mgtKey;
            $inv->invoicerCorpName   = config('popbill.company.corp_name');
            $inv->invoicerCEOName    = config('popbill.company.ceo_name');
            $inv->invoicerAddr       = config('popbill.company.addr');
            $inv->invoicerBizClass   = config('popbill.company.biz_class');
            $inv->invoicerBizType    = config('popbill.company.biz_type');
            $inv->invoicerEmail      = config('popbill.company.email');
            $inv->invoicerTEL        = config('popbill.company.tel');
            $inv->invoiceeType       = $invoiceeType;
            $inv->invoiceeCorpNum    = $invoiceeNum;
            $inv->invoiceeCorpName   = $data['tax_invoice_biz_name'];
            $inv->invoiceeCEOName    = $data['tax_invoice_ceo_name'];
            $inv->invoiceeEmail1     = $data['tax_invoice_email'] ?? '';
            $inv->supplyCostTotal    = (string) $supply;
            $inv->taxTotal           = (string) $vat;
            $inv->totalAmount        = (string) ($supply + $vat);
            $inv->purposeType        = '영수';
            $inv->remark1            = $order->order_number;

            $detail->serialNum  = 1;
            $detail->itemName   = $order->product_name ?? '처방약';
            $detail->qty        = '1';
            $detail->unitCost   = (string) $supply;
            $detail->supplyCost = (string) $supply;
            $detail->tax        = (string) $vat;
            $inv->detailList    = [$detail];

            $result    = $svc->registIssue($corpNum, $inv, $userId);
            $invoiceNo = $result->ntsConfirmNum ?? $mgtKey;
            $issuedAt  = now();

            $order->update([
                'tax_invoice_status'    => 'issued',
                'tax_invoice_no'        => $invoiceNo,
                'tax_invoice_type'      => $data['tax_invoice_type'],
                'tax_invoice_biz_name'  => $data['tax_invoice_biz_name'],
                'tax_invoice_ceo_name'  => $data['tax_invoice_ceo_name'],
                // 개인 건은 주민번호다. 이 칸은 평문 컬럼이므로 마스킹해서 남긴다 —
                // 원문은 처방전의 전용 암호화 칸에만 있어야 한다(P0-1).
                'tax_invoice_biz_no'    => $invoiceeType === '개인'
                    ? \App\Support\ResidentNo::mask($invoiceeNum)
                    : $invoiceeNum,
                'tax_invoice_email'     => $data['tax_invoice_email'] ?? null,
                'tax_invoice_supply'    => $data['tax_invoice_supply'],
                'tax_invoice_vat'       => $data['tax_invoice_vat'],
                'tax_invoice_issued_at' => $issuedAt,
            ]);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("세금계산서 발행 ({$invoiceNo}) — {$data['tax_invoice_biz_name']}");

            // PDF 자동 생성 + 서류 관리 저장
            try {
                $order->loadMissing('patient');
                $pdfBytes = $this->buildTaxInvoicePdf($order);
                $pdfName  = '세금계산서_' . ($order->tax_invoice_biz_name ?? '') . '_' . $order->order_number . '.pdf';
                $pdfPath  = 'tax_invoices/' . $order->id . '/' . $pdfName;
                Storage::put($pdfPath, $pdfBytes);
                PrescriptionDocument::create([
                    'prescription_id'   => $order->prescription_id,
                    'patient_id'        => $order->patient_id,
                    'created_by'        => Auth::id(),
                    'type'              => 'tax_invoice',
                    'file_path'         => $pdfPath,
                    'original_filename' => $pdfName,
                ]);

                /* 장표 이미지도 함께 남긴다. 공단 팩스 합본은 dompdf 로 만드는데
                   외부 PDF 는 페이지로 못 끼우므로, 팩스에 실리는 것은 이 이미지다. */
                \App\Support\TaxInvoiceImage::ensure($order);
            } catch (\Throwable $e) {
                Log::warning('[TaxInvoice] PDF 서류 저장 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'success'        => true,
                'message'        => '세금계산서가 발행되었습니다.',
                'tax_invoice_no' => $invoiceNo,
                'issued_at'      => $issuedAt->format('Y-m-d H:i'),
            ]);

        } catch (\Throwable $e) {
            Log::error('[TaxInvoice] 발행 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '발행 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 세금계산서 취소 (팝빌) ───────────────────────────
    public function cancelTaxInvoice(Order $order): \Illuminate\Http\JsonResponse
    {
        if ($order->tax_invoice_status !== 'issued') {
            return response()->json(['success' => false, 'message' => '발행된 세금계산서가 없습니다.'], 422);
        }

        try {
            $corpNum = config('popbill.test.corp_num');
            $userId  = config('popbill.test.user_id');
            // 발행 시와 동일한 패턴으로 mgtKey 재구성 (TI + Ymd + orderId)
            $mgtKey  = 'TI' . $order->tax_invoice_issued_at?->format('Ymd')
                     . str_pad($order->id, 6, '0', STR_PAD_LEFT);

            app(TaxinvoiceService::class)->cancelIssue($corpNum, 'SELL', $mgtKey, null, $userId);

            $order->update([
                'tax_invoice_status'       => 'cancelled',
                'tax_invoice_cancelled_at' => now(),
            ]);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("세금계산서 취소 ({$order->tax_invoice_no})");

            return response()->json(['success' => true, 'message' => '세금계산서가 취소되었습니다.']);

        } catch (\Throwable $e) {
            Log::error('[TaxInvoice] 취소 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '취소 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 현금영수증 발행 (팝빌) ───────────────────────────
    public function issueCashReceipt(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        if ($order->cash_receipt_status === 'issued') {
            return response()->json(['success' => false, 'message' => '이미 발행된 현금영수증입니다.'], 409);
        }

        $data = $request->validate([
            'cash_receipt_type'       => 'required|in:income_deduction,business_expense',
            'cash_receipt_identifier' => 'required|string|max:30',
            'cash_receipt_amount'     => 'required|numeric|min:1',
        ]);

        try {
            $corpNum    = config('popbill.test.corp_num');
            $userId     = config('popbill.test.user_id');
            $amount     = (int) $data['cash_receipt_amount'];
            $supplyCost = (int) round($amount / 1.1);
            $tax        = $amount - $supplyCost;
            $mgtKey     = 'CR' . now()->format('Ymd') . str_pad($order->id, 6, '0', STR_PAD_LEFT);

            $svc = app(CashbillService::class);
            $cb  = $svc->newCashbill();

            $cb->mgtKey           = $mgtKey;
            $cb->tradeType        = '승인거래';
            $cb->tradeUsage       = $data['cash_receipt_type'] === 'income_deduction' ? '소득공제용' : '지출증빙용';
            $cb->taxationType     = '과세';
            $cb->franchiseCorpNum = $corpNum;
            $cb->totalAmount      = (string) $amount;
            $cb->supplyCost       = (string) $supplyCost;
            $cb->tax              = (string) $tax;
            $cb->serviceFee       = '0';
            $cb->identityNum      = $data['cash_receipt_identifier'];
            $cb->customerName     = $order->patient?->name ?? '';
            $cb->itemName         = $order->product_name ?? '처방약';
            $cb->orderNumber      = $order->order_number;
            $cb->email            = $order->patient?->email ?? '';

            $result  = $svc->registIssue($corpNum, $cb, $userId);
            $receiptNo = $result->confirmNum ?? $mgtKey;
            $issuedAt  = now();

            $order->update([
                'cash_receipt_status'     => 'issued',
                'cash_receipt_no'         => $receiptNo,
                'cash_receipt_type'       => $data['cash_receipt_type'],
                'cash_receipt_identifier' => $data['cash_receipt_identifier'],
                'cash_receipt_amount'     => $data['cash_receipt_amount'],
                'cash_receipt_issued_at'  => $issuedAt,
            ]);

            /* 현금영수증 화면 목록은 우리 표(cashbill_records)를 읽는다. 발행만 하고 두면
               그 화면에서 방금 발행한 건이 보이지 않아 담당자가 동기화를 눌러야 했다. */
            try {
                app(\App\Services\Popbill\CashbillSyncService::class)->refreshOne($corpNum, $mgtKey);
            } catch (\Throwable $e) {
                Log::warning('[CashReceipt] 발행 후 동기화 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }

            $typeLabel = Order::CASH_RECEIPT_TYPE_LABELS[$data['cash_receipt_type']] ?? '';

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("현금영수증 발행 ({$receiptNo}) — {$typeLabel} / {$data['cash_receipt_identifier']}");

            // PDF 자동 생성 + 서류 관리 저장
            try {
                $order->loadMissing('patient');
                $pdfBytes = $this->buildCashReceiptPdf($order);
                $mobile   = preg_replace('/[^0-9]/', '', $order->patient?->mobile ?? '');
                $pdfName  = '현금영수증_' . ($order->patient?->name ?? '') . '_' . $mobile . '_' . $order->order_number . '.pdf';
                $pdfPath  = 'cash_receipts/' . $order->id . '/' . $pdfName;
                Storage::put($pdfPath, $pdfBytes);
                PrescriptionDocument::create([
                    'prescription_id'   => $order->prescription_id,
                    'patient_id'        => $order->patient_id,
                    'created_by'        => Auth::id(),
                    'type'              => 'cash_receipt',
                    'file_path'         => $pdfPath,
                    'original_filename' => $pdfName,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[CashReceipt] PDF 서류 저장 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }

            // SMS 알림
            $mobile      = $order->patient?->mobile
                        ?? $order->prescription?->mobile_ocr
                        ?? null;
            $patientName = $order->patient?->name ?? '';
            if ($mobile) {
                try {
                    $amountFormatted = number_format((int) $data['cash_receipt_amount']);
                    $smsContent = "[콜로플라스트] {$patientName}님 현금영수증이 발행되었습니다.\n"
                                . "- 유형: {$typeLabel}\n"
                                . "- 금액: {$amountFormatted}원\n"
                                . "- 승인번호: {$receiptNo}";
                    app(MessageService::class)->send($mobile, $smsContent, $patientName);
                } catch (\Throwable $e) {
                    Log::warning('[CashReceipt] SMS 발송 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success'         => true,
                'message'         => '현금영수증이 발행되었습니다.',
                'cash_receipt_no' => $receiptNo,
                'issued_at'       => $issuedAt->format('Y-m-d H:i'),
            ]);

        } catch (\Throwable $e) {
            Log::error('[CashReceipt] 발행 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '발행 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 현금영수증 취소 (팝빌) ───────────────────────────
    public function cancelCashReceipt(Order $order): \Illuminate\Http\JsonResponse
    {
        if ($order->cash_receipt_status !== 'issued') {
            return response()->json(['success' => false, 'message' => '발행된 현금영수증이 없습니다.'], 422);
        }

        try {
            $corpNum      = config('popbill.test.corp_num');
            $userId       = config('popbill.test.user_id');
            $cancelMgtKey = 'CRC' . now()->format('Ymd') . str_pad($order->id, 6, '0', STR_PAD_LEFT);

            app(CashbillService::class)->revokeRegistIssue(
                corpNum:      $corpNum,
                mgtKey:       $cancelMgtKey,
                orgMgtKey:    $order->cash_receipt_no,
                orgTradeDate: $order->cash_receipt_issued_at?->format('Ymd') ?? '',
                userId:       $userId,
            );

            $order->update([
                'cash_receipt_status'       => 'cancelled',
                'cash_receipt_cancelled_at' => now(),
            ]);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("현금영수증 취소 ({$order->cash_receipt_no})");

            return response()->json(['success' => true, 'message' => '현금영수증이 취소되었습니다.']);

        } catch (\Throwable $e) {
            Log::error('[CashReceipt] 취소 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '취소 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 현금영수증 PDF 바이트 생성 (헬퍼) ──────────────────
    private function buildCashReceiptPdf(Order $order): string
    {
        $this->ensureNanumGothicVariantsRegistered();
        $typeLabel   = $order->cash_receipt_type === 'income_deduction' ? '소득공제' : '지출증빙';
        $amount      = number_format((int) $order->cash_receipt_amount);
        $issuedAt    = $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '-';
        $patientName = $order->patient?->name ?? '-';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; font-family:'NanumGothic',sans-serif; }
body { font-size:13px; color:#111; padding:30px 36px; }
.title { text-align:center; font-size:22px; font-weight:700; letter-spacing:4px; padding:12px 0 8px; border-bottom:2px solid #111; margin-bottom:8px; }
.subtitle { text-align:center; font-size:11px; color:#555; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
th { width:38%; padding:8px 4px; font-weight:700; color:#444; text-align:left; border-bottom:1px solid #ddd; }
td { padding:8px 4px; border-bottom:1px solid #ddd; }
.amount { font-size:18px; font-weight:700; }
.footer { margin-top:24px; text-align:center; font-size:10px; color:#888; border-top:1px dashed #ccc; padding-top:10px; }
</style>
</head>
<body>
<div class="title">현금영수증</div>
<div class="subtitle">국세청 현금영수증 발행 확인증</div>
<table>
  <tr><th>승인번호</th><td><b>{$order->cash_receipt_no}</b></td></tr>
  <tr><th>거래유형</th><td>{$typeLabel}</td></tr>
  <tr><th>식별번호</th><td>{$order->cash_receipt_identifier}</td></tr>
  <tr><th>거래금액</th><td class="amount">&#8361;{$amount}</td></tr>
  <tr><th>발행일시</th><td>{$issuedAt}</td></tr>
  <tr><th>주문번호</th><td>{$order->order_number}</td></tr>
  <tr><th>고객명</th><td>{$patientName}</td></tr>
</table>
<div class="footer">본 영수증은 소득공제 · 지출증빙용으로 사용하실 수 있습니다.</div>
</body>
</html>
HTML;
        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        // 쓰인 글자만 심는다. 나눔고딕 원본이 4.5MB 라 통째로 심으면 산출물이 2.7MB 가 되고
        // 만드는 동안 메모리가 128MB 를 넘겨 위임장 내려받기가 500 으로 떨어졌다.
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('NanumGothic');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 340, 480], 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    // ── 세금계산서 PDF 바이트 생성 (헬퍼) ──────────────────
    private function buildTaxInvoicePdf(Order $order): string
    {
        $this->ensureNanumGothicVariantsRegistered();
        $supply   = number_format((int) $order->tax_invoice_supply);
        $vat      = number_format((int) $order->tax_invoice_vat);
        $total    = number_format((int) $order->tax_invoice_supply + (int) $order->tax_invoice_vat);
        $issuedAt = $order->tax_invoice_issued_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; font-family:'NanumGothic',sans-serif; }
body { font-size:13px; color:#111; padding:30px 36px; }
.title { text-align:center; font-size:20px; font-weight:700; letter-spacing:4px; padding:12px 0 8px; border-bottom:2px solid #111; margin-bottom:8px; }
.subtitle { text-align:center; font-size:11px; color:#555; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
th { width:38%; padding:8px 4px; font-weight:700; color:#444; text-align:left; border-bottom:1px solid #ddd; }
td { padding:8px 4px; border-bottom:1px solid #ddd; }
.amount { font-size:16px; font-weight:700; }
.footer { margin-top:24px; text-align:center; font-size:10px; color:#888; border-top:1px dashed #ccc; padding-top:10px; }
</style>
</head>
<body>
<div class="title">전자세금계산서</div>
<div class="subtitle">발행 확인증</div>
<table>
  <tr><th>승인번호</th><td><b>{$order->tax_invoice_no}</b></td></tr>
  <tr><th>공급받는자</th><td>{$order->tax_invoice_biz_name}</td></tr>
  <tr><th>사업자등록번호</th><td>{$order->tax_invoice_biz_no}</td></tr>
  <tr><th>대표자명</th><td>{$order->tax_invoice_ceo_name}</td></tr>
  <tr><th>공급가액</th><td>&#8361;{$supply}</td></tr>
  <tr><th>세액</th><td>&#8361;{$vat}</td></tr>
  <tr><th>합계금액</th><td class="amount">&#8361;{$total}</td></tr>
  <tr><th>발행일</th><td>{$issuedAt}</td></tr>
  <tr><th>주문번호</th><td>{$order->order_number}</td></tr>
</table>
<div class="footer">본 세금계산서는 국세청 전자세금계산서 시스템을 통해 발행되었습니다.</div>
</body>
</html>
HTML;
        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        // 쓰인 글자만 심는다. 나눔고딕 원본이 4.5MB 라 통째로 심으면 산출물이 2.7MB 가 되고
        // 만드는 동안 메모리가 128MB 를 넘겨 위임장 내려받기가 500 으로 떨어졌다.
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('NanumGothic');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    // ── 현금영수증 PDF 다운로드 ───────────────────────────
    public function downloadCashReceiptPdf(Order $order)
    {
        if ($order->cash_receipt_status !== 'issued') {
            abort(404, '발행된 현금영수증이 없습니다.');
        }

        $mobile    = preg_replace('/[^0-9]/', '', $order->patient?->mobile ?? '');
        $filename  = '현금영수증_' . ($order->patient?->name ?? '') . '_' . $mobile . '_' . $order->order_number . '.pdf';
        $pdfOutput = $this->buildCashReceiptPdf($order);

        // 스토리지에 저장 + 서류 목록 기록
        try {
            $filePath = 'cash_receipts/' . $order->id . '/' . $filename;
            Storage::put($filePath, $pdfOutput);

            PrescriptionDocument::create([
                'prescription_id'   => $order->prescription_id,
                'patient_id'        => $order->patient_id,
                'created_by'        => Auth::id(),
                'type'              => 'cash_receipt',
                'file_path'         => $filePath,
                'original_filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::warning('현금영수증 PDF 서류 저장 실패: ' . $e->getMessage());
        }

        return response($pdfOutput, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    private function ensureNanumGothicVariantsRegistered(): void
    {
        $path = storage_path('fonts/installed-fonts.json');
        if (!file_exists($path)) return;
        $fonts = json_decode(file_get_contents($path), true) ?? [];
        if (!isset($fonts['nanumgothic']['normal'])) return;
        $normalKey = $fonts['nanumgothic']['normal'];
        $changed = false;
        foreach (['bold', 'italic', 'bold_italic'] as $v) {
            if (!isset($fonts['nanumgothic'][$v])) { $fonts['nanumgothic'][$v] = $normalKey; $changed = true; }
        }
        if ($changed) file_put_contents($path, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ── Withworks 상태 즉시 조회 (AJAX) ──────────────────
    public function fetchWithworksStatus(Order $order): \Illuminate\Http\JsonResponse
    {
        if (!$order->withworks_so_no) {
            return response()->json(['success' => false, 'message' => 'Withworks 미연동 주문입니다.'], 422);
        }

        $baseUrl = rtrim(config('services.todoworks.api_url', ''), '/');
        $token   = config('services.todoworks.token');

        if (!$baseUrl || !$token) {
            return response()->json(['success' => false, 'message' => 'Withworks API 설정이 없습니다.'], 500);
        }

        try {
            $res = Http::withToken($token)->timeout(5)
                ->get("{$baseUrl}/api/v1/ce-admin/so_show", [
                    'ce_order_number' => $order->order_number,
                ]);

            if ($res->successful() && ($res->json('success') ?? false)) {
                $result = $res->json('result');
                $ship   = $result['ship'] ?? null;

                $order->update([
                    'withworks_status'            => $result['status'] ?? null,
                    'withworks_status_label'      => $result['status_label'] ?? null,
                    'withworks_status_at'         => now(),
                    'withworks_ship_no'           => $ship['ship_no'] ?? null,
                    'withworks_ship_status'       => $ship['ship_status'] ?? null,
                    'withworks_ship_status_label' => $ship['ship_status_label'] ?? null,
                    'withworks_tracking_no'       => $ship['tracking_no'] ?? null,
                    'withworks_ship_at'           => $ship ? now() : null,
                ]);
                return response()->json([
                    'success'            => true,
                    'status'             => $result['status'] ?? null,
                    'status_label'       => $result['status_label'] ?? null,
                    'ship'               => $ship,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Withworks에서 주문을 찾을 수 없습니다.'], 404);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'API 호출 실패: ' . $e->getMessage()], 500);
        }
    }
}
