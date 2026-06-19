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

        $query = Order::with(['patient'])
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

        $orders = $query->paginate(25)->withQueryString();

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
            'orders', 'counts', 'tab', 'taxColExists',
            'monthlyTaxAmount', 'monthlyCashAmount'
        ));
    }
}
