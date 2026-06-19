<?php
// app/Http/Controllers/Api/OrderApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    // ── GET /api/orders ──────────────────────────────────
    /** 주문 목록 (페이지네이션) */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['patient', 'prescription'])
            ->latest();

        // 상태 필터
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 검색 (주문번호 / 환자명)
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $orders->map(fn(Order $o) => $this->formatOrder($o)),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
            ],
        ]);
    }

    // ── GET /api/orders/{order_number} ───────────────────
    /** 주문 상세 */
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::with(['patient', 'prescription'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order, detail: true),
        ]);
    }

    // ── 내부: 주문 포맷 ───────────────────────────────────
    private function formatOrder(Order $o, bool $detail = false): array
    {
        $base = [
            'order_number'       => $o->order_number,
            'status'             => $o->status,
            'status_label'       => $o->status_label,
            'product_name'       => $o->product_name,
            'quantity'           => $o->quantity,
            'total_amount'       => $o->total_amount,
            'patient_copay'      => $o->patient_copay,
            'patient_name'       => $o->patient?->name,
            'tracking_number'    => $o->tracking_number,
            'estimated_delivery' => $o->estimated_delivery?->format('Y-m-d'),
            'created_at'         => $o->created_at->format('Y-m-d H:i'),
        ];

        if ($detail) {
            $base += [
                'prescription_id'       => $o->prescription?->rx_number,
                'product_code'          => $o->product_code,
                'unit_price'            => $o->unit_price,
                'nhis_amount'           => $o->nhis_amount,
                'shipping_fee'          => $o->shipping_fee,
                'shipping_address'      => $o->shipping_address,
                'delivered_at'          => $o->delivered_at?->format('Y-m-d H:i'),
                'nhis_claim_status'     => $o->nhis_claim_status,
                'nhis_submitted_at'     => $o->nhis_submitted_at?->format('Y-m-d H:i'),
                'tax_invoice_status'    => $o->tax_invoice_status,
                'cash_receipt_status'   => $o->cash_receipt_status,
                'note'                  => $o->note,
            ];
        }

        return $base;
    }
}
