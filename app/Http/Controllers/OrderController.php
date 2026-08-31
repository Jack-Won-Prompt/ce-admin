<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionDocument;
use App\Services\Popbill\CashbillService;
use App\Services\Popbill\MessageService;
use App\Services\ClaimReadiness;
use App\Services\Popbill\TaxinvoiceService;
use App\Services\WithworksSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    // ── 목록 ──────────────────────────────────────────────
    public function index(Request $request): View
    {
        // items.lots — 출고한 Lot 과 유효기간이 목록에 선다(요청서 2쪽)
        $query = Order::with(['patient', 'prescription.billingOffice', 'creator', 'returns',
                              'items.lots', 'operationUser'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /* 거래 구분 — 판매만 있던 목록에 교환·반품·취소를 함께 담는다.
           별도 화면으로 갈라 두면 한 주문에 무슨 일이 있었는지 두 곳을 오가야 알 수 있다.
           'sale' 은 되돌린 적이 없는 주문이다. */
        if ($request->filled('deal')) {
            $request->deal === 'sale'
                ? $query->whereDoesntHave('returns')
                : $query->whereHas('returns', fn ($r) => $r->where('type', $request->deal));
        }
        /* 처방 유형 — 원내·원외·처방외. 정산 방식과 필요한 서류가 달라
           거래 이력에서도 나눠 봐야 한다. */
        if ($request->filled('acc_type')) {
            $query->whereHas('prescription', fn ($p) => $p->where('counsel_acc_add_type', $request->acc_type));
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

        // 거래 구분별 건수 — 칩에 붙는다
        $dealCounts = ['sale' => Order::whereDoesntHave('returns')->count()];
        foreach (\App\Models\OrderReturn::TYPES as $type => $label) {
            $dealCounts[$type] = Order::whereHas('returns', fn ($r) => $r->where('type', $type))->count();
        }

        // wwGrid: 필터된 전체를 그리드용 배열로 (클라이언트사이드)
        $orders = $query->get();
        $extras = \App\Support\OrderGridExtras::forPatients($orders->pluck('patient_id'));

        $gridData = $orders->map(function ($o) use ($extras) {
            /* 유형 — 되돌린 적이 없으면 '판매', 있으면 가장 최근 건의 종류.
               여러 건이 붙었으면 몇 건인지 함께 적는다. 상세로 들어가 보라는 신호다.
               어디까지 진행됐는지는 옆 칸(등록 상태)에서 따로 본다 — 한 칸에 둘을 섞으면
               정렬이 종류와 상태가 뒤엉킨 순서가 되어 쓸모가 없다. */
            $rt   = $o->returns->first();
            $deal = $rt
                ? \App\Models\OrderReturn::TYPES[$rt->type]
                    . ($o->returns->count() > 1 ? ' 외 ' . ($o->returns->count() - 1) . '건' : '')
                : '판매';

            return [
                'id'        => $o->id,
                'order_no'  => $o->order_number,
                'deal'      => $deal,
                // 교환·반품·취소 건만 진행 상태가 있다. 판매는 옆의 '상태'가 그 자리다.
                'deal_state' => $rt ? (\App\Models\OrderReturn::STATUS_LABELS[$rt->status] ?? $rt->status) : '',
                'patient'   => $o->patient?->name ?? '',
                'product'   => $o->product_name ?? '',
                'qty'       => (int) ($o->quantity ?? 1),
                'copay'     => (int) $o->patient_copay,
                'shipping'  => (int) $o->shipping_fee,
                'total'     => (int) $o->total_amount,
                'address'   => $o->shipping_address ?? '',
                'so_type'   => \App\Models\Order::SO_TYPE_LABELS[$o->so_type][0] ?? '',
                'status'    => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                // 언제 팔았고 언제 되돌아왔는지. 둘 사이가 벌어진 건은 눈에 띄어야 한다.
                'sold_at'   => $o->created_at->format('Y-m-d'),
                'deal_at'   => $rt?->created_at?->format('Y-m-d') ?? '',
                // 병원ㆍ처방 정보 탭의 칸 + 네 화면이 함께 쓰는 칸
            ] + $extras->rx($o->prescription, $o->patient)
              + $extras->ww($o, $o->prescription, $o->patient)
              + $extras->of($o);
        })->values();

        return view('orders.index', compact('gridData', 'statusCounts', 'dealCounts'));
    }

    /**
     * Operation 담당자ㆍ마감 체크ㆍ참고사항을 적는다 (요청서 6ㆍ10ㆍ11ㆍ12쪽).
     *
     * 상담을 맡은 사람(처방전의 배정 담당자)과 다른 사람이라 여기서 따로 적는다.
     * 마감 체크는 「이 건은 더 볼 것이 없다」는 표시다 — 12쪽의 정산 상태(마감ㆍ확정ㆍ
     * 반려ㆍ보류ㆍ취소)와는 다른 것이라, 그것이 정해지면 그때 이어 붙인다.
     */
    public function operation(Request $request, Order $order): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'operation_user_id' => 'nullable|exists:users,id',
            'closing_checked'   => 'nullable|boolean',
            'reference_note'    => 'nullable|string|max:500',
        ]);

        $checked = (bool) ($data['closing_checked'] ?? false);

        $order->update([
            'operation_user_id' => $data['operation_user_id'] ?: null,
            'reference_note'    => $data['reference_note'] ?? null,
            /* 이미 찍힌 날은 그대로 둔다 — 저장을 다시 누를 때마다 날이 오늘로 밀리면
               언제 마감했는지를 잃는다. 체크를 풀면 함께 지운다. */
            'closing_checked_at' => $checked ? ($order->closing_checked_at ?? now()) : null,
            'closing_checked_by' => $checked ? ($order->closing_checked_by ?? Auth::id()) : null,
        ]);

        /* 마감 체크는 정산 상태의 「마감」과 같은 말이다(요청서 6쪽과 12쪽이 각각 적어
           왔지만 가리키는 것이 하나다). 두 곳에 따로 두면 한쪽만 눌린 건이 생기고,
           그때 「마감 몇 건」이 화면마다 달라진다.

           확정된 건은 건드리지 않는다 — 잠갔다는 말이 그 뜻이다. 체크를 풀어도 진행중으로
           되돌리지 않는다: 마감을 무르는 일은 정산/회계에서 까닭을 남기고 한다. */
        if ($checked && !$order->isSettleLocked() && $order->settle_status === 'open') {
            $order->update([
                'settle_status'    => 'closed',
                'settle_status_at' => now(),
                'settle_status_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Operation 정보를 적었습니다.');
    }

    // ── 상세 ──────────────────────────────────────────────
    public function show(Order $order): View
    {
        // 다른 화면의 '상세내용' 탭에 주입될 때(?partial=1)는 크롬 없는 프래그먼트로 렌더
        if (request()->boolean('partial')) {
            view()->share('layout', 'layouts.partial');
        }

        $order->load(['patient', 'prescription.items', 'creator', 'tossPayment', 'returns']);

        // 상세를 열 때도 최신을 본다. 스케줄이 주기적으로 훑지만 그 사이에 바뀌었을 수 있다.
        $withworksStatus = app(WithworksSync::class)->pull($order);

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
            'shipping_address_detail' => 'nullable|string|max:200',
            'shipping_postcode'       => 'nullable|string|max:10',
            'shipping_recipient'      => 'nullable|string|max:100',
            'so_type'                 => ['nullable', 'string', Rule::in(Order::saleSoTypes())],
        ]);

        $prescription = Prescription::findOrFail($request->prescription_id);

        /* 저장만 해도 주문 줄은 선다(주문 관리에 보이도록 — PrescriptionController::ensureOrder).
           그 줄은 아직 창고로 보내지 않은 빈 껍데기다. 여기서는 그것을 채워 보낸다 —
           「이미 있다」고 물리면 저장을 한 번이라도 한 건은 영영 주문을 낼 수 없다.
           이미 보낸 주문(SO 가 붙은 것)은 그대로 막는다 — 같은 것을 두 번 보낼 수는 없다. */
        $existing = $prescription->order()->first();
        if ($existing && $existing->withworks_so_no) {
            return response()->json(['success' => false, 'message' => '이미 주문이 생성된 처방전입니다.'], 409);
        }

        /* 제품이 없으면 주문을 내지 않는다. 여기서 막지 않으면 처방전이 「주문 완료」로
           넘어가는데 실제로 판 것은 없다 — 그 상태를 보고 다음 사람이 청구를 건다.
           저장으로 서는 빈 껍데기(OrderSync)는 그대로 둔다. 그것은 「아직 손대지 않은
           건」이라는 표시이고, 여기는 「이제 판다」는 자리라 뜻이 다르다. */
        if (collect($request->input('items', []))->filter(fn ($i) => ! empty($i['product_name']))->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '주문할 제품이 없습니다. 제품을 먼저 선택한 뒤 주문을 생성해 주십시오.',
            ], 422);
        }

        /* 아직 살 때가 아닌 건은 여기서도 막는다(요청서 2쪽, 2026-08-31). 주문이 서는
           길이 둘이라, 한쪽만 막으면 다른 길로 그대로 나간다. */
        if ($why = \App\Support\RepurchaseWindow::block($prescription)) {
            return response()->json(['success' => false, 'message' => $why], 422);
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

        // 배송비는 받지 않기로 했다(2026-08-24). 보내오면 그 값을 쓰고, 없으면 0 이다.
        $shippingFee = $request->shipping_fee ?? 0;
        $totalAmount = $totalCopay + $shippingFee;

        $attrs = [
            'order_number'     => $existing?->order_number ?? Order::generateOrderNumber(),
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
        ] + self::shippingExtras($request);

        if ($existing) {
            // 껍데기를 채운다 — 번호는 그대로 두어 이미 적어 둔 곳(입금ㆍ영수증)이 어긋나지 않는다
            $existing->update($attrs);
            $existing->items()->delete();
            $order = $existing;
        } else {
            $order = Order::create($attrs);
        }

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

        /* 주문 확정 문자는 여기서 보내지 않는다.
           여기는 우리 쪽 줄만 선 자리다 — 창고에 판매주문이 서기 전이라, 연계가 실패하면
           고객은 확정 문자를 받았는데 창고에는 아무것도 없는 꼴이 된다. 실제로 그렇게
           나가고 있었다. 창고에 줄이 선 뒤로 옮겼다
           (PrescriptionController::sendOrderConfirmedSms).

           문구도 그 자리에서 메시지 관리의 「주문 확정」 유형을 읽는다 — 여기 박혀 있던
           글은 고치려면 배포를 해야 했고, 보낸 기록이 발송ㆍ발행 내역에 남지도 않았다. */

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
            'so_type'                 => ['nullable', 'string', Rule::in(Order::saleSoTypes())],
            'delivery_date'           => 'nullable|date',
        ]);

        $items      = collect($request->input('items', []))->filter(fn($i) => !empty($i['product_name']));
        $firstItem  = $items->first() ?? [];
        $totalCopay = $request->patient_copay ?? $items->sum('patient_copay');
        $totalNhis  = $request->total_nhis    ?? $items->sum('nhis_amount');
        $unitPrice  = (float)($firstItem['insurance_price'] ?? $firstItem['product_price'] ?? 0);
        $totalQty   = $items->sum('quantity') ?: 1;
        // 배송비는 받지 않기로 했다(2026-08-24) — 예전 주문에 적힌 값은 그대로 지킨다
        $shippingFee = $order->shipping_fee ?? 0;
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
        ] + self::shippingExtras($request, $order));

        activity()->causedBy(Auth::user())
            ->performedOn($order)
            ->log("{$order->order_number} 주문 수정");

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
        ]);
    }

    /**
     * 배송지의 우편번호와 상세주소.
     *
     * 화면에는 세 칸(우편번호ㆍ도로명ㆍ상세)이 있는데 주문에는 한 칸뿐이라 두 값이 저장되지
     * 않고 사라졌다. 칸을 늘렸지만(2026_08_28_000001) 아직 옮기지 않은 서버가 있으므로,
     * 있을 때만 쓴다 — 없는 칸에 쓰려 들면 저장 자체가 깨진다.
     *
     * @return array<string,mixed>
     */
    private static function shippingExtras(Request $request, ?Order $order = null): array
    {
        $out = [];
        foreach (['shipping_postcode', 'shipping_address_detail'] as $col) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', $col)) {
                $out[$col] = $request->input($col) ?? $order?->{$col};
            }
        }
        return $out;
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

        // 배송이 끝나야 청구할 수 있다 — 상태가 바뀌면 준비 여부도 달라진다
        app(ClaimReadiness::class)->refresh($order);

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

            /* 처방전에 없으면 거래처에 적힌 것을 본다. 주민번호를 고치는 자리는
               거래처관리 하나이므로(요청서 1쪽), 그쪽에만 적어 둔 건이 대부분이다.
               처방전만 보다가 「없다」고 막으면, 자동 발행도 같은 길이라 출고 뒤
               계산서가 조용히 나가지 않는다. */
            if (strlen($invoiceeNum) !== 13) {
                $invoiceeNum = preg_replace('/\D/', '',
                    (string) $order->patient?->residentNoFor('tax_invoice'));
            }
        }

        if ($invoiceeType === '개인' && strlen($invoiceeNum) !== 13) {
            return response()->json([
                'success' => false,
                'message' => '개인 발행에는 주민등록번호 13자리가 필요합니다 — 거래처관리나 처방전에 먼저 저장해 주십시오.',
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

            } catch (\Throwable $e) {
                Log::warning('[TaxInvoice] PDF 서류 저장 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }

            app(ClaimReadiness::class)->refresh($order->refresh());

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

            /* 나갔던 종이를 걷는다 — 취소한 계산서의 PDF 가 주문의 서류에 그대로 남아
               있으면 다음 사람이 그것을 유효한 증빙으로 읽는다. */
            $this->dropIssuedDocs($order, 'tax_invoice', 'tax_invoices');

            // 취소하면 청구 자료가 다시 모자라진다
            app(ClaimReadiness::class)->refresh($order);

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

            /* 방금 쓴 것을 거래처에도 적어 둔다. 그러지 않으면 다음 발행에서 또 빈칸으로
               열리고, 담당자는 같은 값을 다시 친다 — 거래처 열 명 가운데 현금영수증번호가
               한 명도 채워지지 않은 까닭이 그것이다.
               이미 적혀 있으면 건드리지 않는다. 담당자가 확인해 둔 값이 이번에 한 번
               다르게 친 것에 덮이면 안 된다. */
            $this->rememberForPatient($order, $data);

            /* 현금영수증 화면 목록은 우리 표(cashbill_records)를 읽는다. 발행만 하고 두면
               그 화면에서 방금 발행한 건이 보이지 않아 담당자가 동기화를 눌러야 했다. */
            try {
                app(\App\Services\Popbill\CashbillSyncService::class)->refreshOne($corpNum, $mgtKey);
            } catch (\Throwable $e) {
                Log::warning('[CashReceipt] 발행 후 동기화 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            }

            app(ClaimReadiness::class)->refresh($order->refresh());

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

            // 나갔던 종이를 걷는다(세금계산서 취소와 같은 뜻이다)
            $this->dropIssuedDocs($order, 'cash_receipt', 'cash_receipts');

            app(ClaimReadiness::class)->refresh($order);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("현금영수증 취소 ({$order->cash_receipt_no})");

            return response()->json(['success' => true, 'message' => '현금영수증이 취소되었습니다.']);

        } catch (\Throwable $e) {
            Log::error('[CashReceipt] 취소 실패', ['order' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '취소 실패: ' . $e->getMessage()], 500);
        }
    }

    // ── 현금영수증 PDF 바이트 생성 (헬퍼) ──────────────────
    /**
     * 발행된 현금영수증을 종이 서식대로 그린다.
     *
     * 예전에는 「국세청 현금영수증 발행 확인증」이라는 표 한 장이었다. 승인번호와
     * 금액은 맞았지만 받는 쪽이 아는 종이가 아니었고, 가맹점 정보가 어디에도 없었다.
     * 서식은 App\Support\CashReceiptForm 이 그린다.
     */
    private function buildCashReceiptPdf(Order $order): string
    {
        $this->ensureNanumGothicVariantsRegistered();

        return \App\Support\CashReceiptForm::render($order);
    }

    // ── 세금계산서 PDF 바이트 생성 (헬퍼) ──────────────────
    /**
     * 발행된 전자세금계산서를 종이 서식대로 그린다.
     *
     * 예전에는 「발행 확인증」이라는 표 한 장이었다. 승인번호와 금액은 맞았지만 받는
     * 쪽이 아는 종이가 아니었다 — 국세청 별지 제11호 서식(붉은 선이 박힌 그것)이어야
     * 한다. 서식은 App\Support\TaxInvoiceForm 이 그린다.
     */
    private function buildTaxInvoicePdf(Order $order): string
    {
        $this->ensureNanumGothicVariantsRegistered();

        return \App\Support\TaxInvoiceForm::render($order);
    }

    /**
     * 취소한 증빙의 종이를 주문의 서류에서 걷는다.
     *
     * 취소해 놓고 PDF 를 그대로 두면 주문 등록 화면의 문서 칸에 그 종이가 남아, 다음
     * 사람이 유효한 증빙으로 읽는다. 취소된 계산서는 증빙이 아니다.
     *
     * 어느 줄이 이 주문의 것인지는 파일 자리로 가린다(tax_invoices/{주문id}/…).
     * 서류 표에는 주문 id 칸이 없고, 한 처방에 주문이 둘 이상 붙는 날이 오면
     * 처방 id 로 지우다가 남의 종이까지 걷게 된다. 옛 장표(PNG)도 같은 자리에 있어
     * 함께 걷힌다.
     */
    private function dropIssuedDocs(Order $order, string $type, string $dir): void
    {
        $rows = PrescriptionDocument::where('type', $type)
            ->where('file_path', 'like', $dir . '/' . $order->id . '/%')
            ->get();

        foreach ($rows as $doc) {
            foreach (['local', 'public'] as $disk) {
                if ($doc->file_path && Storage::disk($disk)->exists($doc->file_path)) {
                    Storage::disk($disk)->delete($doc->file_path);
                }
            }
            $doc->delete();
        }

        if ($rows->isNotEmpty()) {
            Log::info('[증빙 취소] 첨부 서류 삭제', [
                'order' => $order->order_number, 'type' => $type, 'count' => $rows->count(),
            ]);
        }
    }

    /**
     * 발행된 증빙을 그 자리에서 펼쳐 본다(내려받지 않는다).
     *
     * 서식은 주문의 칸에서 그때그때 그린다 — 발행할 때 저장해 둔 파일을 찾아가지
     * 않는다. 옛 건은 그 파일이 없거나 옛 모양이라, 목록에서 눌렀을 때 어떤 건은
     * 열리고 어떤 건은 안 열리는 일이 생긴다.
     */
    public function previewCashReceipt(Order $order)
    {
        if ($order->cash_receipt_status !== 'issued') {
            abort(404, '발행된 현금영수증이 없습니다.');
        }

        return $this->inlinePdf(
            \App\Support\CashReceiptForm::render($order),
            '현금영수증_' . ($order->patient?->name ?? '') . '_' . $order->order_number . '.pdf'
        );
    }

    public function previewTaxInvoice(Order $order)
    {
        if ($order->tax_invoice_status !== 'issued') {
            abort(404, '발행된 세금계산서가 없습니다.');
        }

        $this->ensureNanumGothicVariantsRegistered();

        return $this->inlinePdf(
            \App\Support\TaxInvoiceForm::render($order),
            '세금계산서_' . ($order->tax_invoice_biz_name ?? '') . '_' . $order->order_number . '.pdf'
        );
    }

    private function inlinePdf(string $bytes, string $filename)
    {
        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
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

        $sync = app(WithworksSync::class);

        if (!$sync->configured()) {
            return response()->json(['success' => false, 'message' => 'Withworks API 설정이 없습니다.'], 500);
        }

        $result = $sync->pull($order);

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Withworks에서 주문 상태를 가져오지 못했습니다.'], 502);
        }

        return response()->json([
            'success'      => true,
            'status'       => $result['status'] ?? null,
            'status_label' => $result['status_label'] ?? null,
            'ship'         => $result['ship'] ?? null,
        ]);
    }

    /**
     * 발행하며 쓴 값을 거래처에 남긴다 (요청서 8ㆍ9쪽 뒤처리).
     *
     * 값이 쌓이는 바퀴다. 발행 창은 거래처가 적어 둔 것으로 열리고,
     * 발행하며 고친 것은 여기서 거래처로 돌아간다. 한 번 적으면 다음부터 따라온다.
     *
     * 이미 적혀 있으면 덮지 않는다 — 담당자가 확인해 둔 값이 이번에 한 번 다르게 친
     * 것에 밀리면 안 된다.
     */
    private function rememberForPatient(Order $order, array $data): void
    {
        $p = $order->patient;

        if (!$p) {
            return;
        }

        $fill = [];

        if (blank($p->deduction)) {
            $fill['deduction'] = $data['cash_receipt_type'] === 'business_expense' ? '지출증빙' : '소득공제';
        }

        /* 자진발급 번호는 「번호를 못 받았다」는 표시라 거래처에 적어 둘 것이 아니다 —
           적어 두면 다음에도 자진발급으로 열려 진짜 번호를 받을 기회가 사라진다. */
        if (blank($p->cash_receipt_no)
            && $data['cash_receipt_identifier'] !== \App\Models\Patient::SELF_ISSUE_NO) {
            $fill['cash_receipt_no'] = $data['cash_receipt_identifier'];
        }

        if ($fill) {
            $p->forceFill($fill)->save();
        }
    }

}
