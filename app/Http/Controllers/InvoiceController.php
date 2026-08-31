<?php
// app/Http/Controllers/InvoiceController.php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $taxColExists = Schema::hasColumn('orders', 'tax_invoice_status');

        $invoiceStatuses = ['confirmed', 'shipping', 'delivered'];

        // prescription ㆍ items.lots — 네 화면이 함께 쓰는 칸이 읽는다
        $query = Order::with(['patient', 'prescription.billingOffice', 'items.lots', 'operationUser'])
            ->whereIn('status', $invoiceStatuses)
            ->latest();

        // 탭 필터
        $tab = $request->get('tab', 'all');
        if ($taxColExists) {
            match($tab) {
                'tax_pending'  => $query->where('tax_invoice_status', 'not_issued'),
                'cash_pending' => $query->where('cash_receipt_status', 'not_issued'),
                'tax_issued'   => $query->where('tax_invoice_status', 'issued'),
                'cash_issued'  => $query->where('cash_receipt_status', 'issued'),
                default        => null,
            };
        }

        // 검색
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        // 날짜 (delivered_at 없는 주문은 created_at 기준)
        if ($request->filled('date_from')) {
            $query->where(function($sub) use ($request) {
                $sub->whereDate('delivered_at', '>=', $request->date_from)
                    ->orWhere(function($q2) use ($request) {
                        $q2->whereNull('delivered_at')->whereDate('created_at', '>=', $request->date_from);
                    });
            });
        }
        if ($request->filled('date_to')) {
            $query->where(function($sub) use ($request) {
                $sub->whereDate('delivered_at', '<=', $request->date_to)
                    ->orWhere(function($q2) use ($request) {
                        $q2->whereNull('delivered_at')->whereDate('created_at', '<=', $request->date_to);
                    });
            });
        }

        // ── wwGrid용 데이터 ──
        $crTypes = Order::CASH_RECEIPT_TYPE_LABELS;

        /* 네 화면이 함께 쓰던 칸을 여기에도 세운다(요청서 3쪽). 동의 두 가지는 사람에
           붙어, 줄마다 물으면 서른 줄에 예순을 더 묻는다 — 미리 모아 둔다. */
        $rows   = $query->get();
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('patient_id'));

        $gridData = $rows->map(function ($order) use ($taxColExists, $crTypes, $extras) {
            $tiStatus = $taxColExists ? ($order->tax_invoice_status ?? 'not_issued') : null;
            $crStatus = $taxColExists ? ($order->cash_receipt_status ?? 'not_issued') : null;

            $statusLabel = match($order->status) {
                'confirmed' => '주문확정',
                'shipping'  => '배송중',
                'delivered' => '배송완료',
                default     => $order->status,
            };
            $statusColor = match($order->status) {
                'confirmed' => 'var(--primary)',
                'shipping'  => 'var(--warning)',
                'delivered' => 'var(--success)',
                default     => 'var(--text-muted)',
            };

            $tiDisplay = !$taxColExists ? '-'
                : ($tiStatus === 'issued' ? '발행완료'
                : ($tiStatus === 'cancelled' ? '취소' : '미발행'));
            $crDisplay = !$taxColExists ? '-'
                : ($crStatus === 'issued' ? '발행완료'
                : ($crStatus === 'cancelled' ? '취소' : '미발행'));

            return [
                'id'             => $order->id,
                // ── 그리드 표시 컬럼 ──
                'order_number'   => $order->order_number ?? '',
                'patient_name'   => $order->patient?->name ?? '-',
                'product_name'   => $order->product_name ?? '-',
                'total_amount'   => (int) ($order->total_amount ?? 0),
                'status_label'   => $statusLabel,
                'created_at'     => $order->created_at?->format('Y-m-d') ?? '-',
                'delivered_at'   => $order->delivered_at?->format('Y-m-d') ?? '-',
                'ti_display'     => $tiDisplay,
                'cr_display'     => $crDisplay,
                // ── 상세 패널(selectOrder)용 추가 필드 (그리드 컬럼 아님) ──
                'status'         => $order->status,
                'status_color'   => $statusColor,
                'patient_mobile' => $order->patient?->mobile ?? '',
                'tax_col_exists' => $taxColExists,
                'ti_status'      => $tiStatus,
                'ti_no'          => $order->tax_invoice_no ?? '',
                'ti_type'        => $order->tax_invoice_type ?? '',
                'ti_biz_name'    => $order->tax_invoice_biz_name ?? '',
                'ti_biz_no'      => $order->tax_invoice_biz_no ?? '',
                'ti_email'       => $order->tax_invoice_email ?? '',
                'ti_supply'      => (int) ($order->tax_invoice_supply ?? 0),
                'ti_vat'         => (int) ($order->tax_invoice_vat ?? 0),
                'ti_issued_at'   => $order->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '',
                'ti_cancelled_at'=> $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') ?? '',
                'cr_status'      => $crStatus,
                'cr_no'          => $order->cash_receipt_no ?? '',
                'cr_type'        => $order->cash_receipt_type ?? '',
                'cr_type_label'  => isset($order->cash_receipt_type) ? ($crTypes[$order->cash_receipt_type] ?? '') : '',
                'cr_identifier'  => $order->cash_receipt_identifier ?? '',
                'cr_amount'      => (int) ($order->cash_receipt_amount ?? 0),
                'cr_issued_at'   => $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '',
                'cr_cancelled_at'=> $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') ?? '',

                // 네 화면이 함께 쓰는 칸 — 차례와 이름이 어디서나 같다
            ] + $extras->rx($order->prescription, $order->patient)
              + $extras->ww($order, $order->prescription, $order->patient)
              + $extras->of($order);
        });

        $total = $gridData->count();

        // 요약 카운트 (컬럼이 있을 때만)
        $counts = collect([
            'total'        => Order::whereIn('status', $invoiceStatuses)->count(),
            'tax_pending'  => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','not_issued')->count() : 0,
            'cash_pending' => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','not_issued')->count() : 0,
            'tax_issued'   => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','issued')->count() : 0,
            'cash_issued'  => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','issued')->count() : 0,
        ]);

        // 이번 달 발행 금액
        $monthlyTaxAmount  = $taxColExists
            ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','issued')
                ->whereMonth('tax_invoice_issued_at', now()->month)
                ->whereYear('tax_invoice_issued_at', now()->year)
                ->sum('tax_invoice_supply')
            : 0;

        $monthlyCashAmount = $taxColExists
            ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','issued')
                ->whereMonth('cash_receipt_issued_at', now()->month)
                ->whereYear('cash_receipt_issued_at', now()->year)
                ->sum('cash_receipt_amount')
            : 0;

        return view('invoice.index', compact(
            'gridData', 'total', 'counts', 'tab', 'taxColExists',
            'monthlyTaxAmount', 'monthlyCashAmount'
        ));
    }
}
