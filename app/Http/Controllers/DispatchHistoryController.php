<?php
// app/Http/Controllers/DispatchHistoryController.php

namespace App\Http\Controllers;

use App\Models\NhisFaxLog;
use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Http\Request;

class DispatchHistoryController extends Controller
{
    public function index(Request $request)
    {
        $type     = $request->input('type', 'virtual_account');
        $search   = $request->input('search');
        $dateFrom = $request->input('date_from') ?: now()->subDays(29)->format('Y-m-d');
        $dateTo   = $request->input('date_to')   ?: now()->format('Y-m-d');
        $perPage  = (int) $request->input('per_page', 20);

        $rows = match ($type) {
            'tax_invoice'   => $this->taxInvoiceQuery($search, $dateFrom, $dateTo),
            'cash_receipt'  => $this->cashReceiptQuery($search, $dateFrom, $dateTo),
            'nhis'          => $this->nhisQuery($search, $dateFrom, $dateTo),
            default         => $this->virtualAccountQuery($search, $dateFrom, $dateTo),
        };

        $rows = $rows->paginate($perPage)->withQueryString();

        $counts = [
            'virtual_account' => TossPayment::whereHas('order')->count(),
            'tax_invoice'     => Order::whereNotNull('tax_invoice_no')->count(),
            'cash_receipt'    => Order::whereNotNull('cash_receipt_no')->count(),
            'nhis'            => NhisFaxLog::count(),
        ];

        return view('dispatch.index', compact('rows', 'type', 'counts', 'dateFrom', 'dateTo', 'search', 'perPage'));
    }

    public function show(string $type, int $id)
    {
        return match ($type) {
            'tax_invoice'   => $this->showTaxInvoice($id),
            'cash_receipt'  => $this->showCashReceipt($id),
            'nhis'          => $this->showNhis($id),
            default         => $this->showVirtualAccount($id),
        };
    }

    /* ── 상세: 가상계좌 ─────────────────────────── */
    private function showVirtualAccount(int $id)
    {
        $record = TossPayment::with([
            'order.prescription.patient',
            'order.prescription.items',
            'order.patient',
            'order.faxLogs.sender',
        ])->findOrFail($id);

        $order       = $record->order;
        $prescription = $order?->prescription;
        $patient     = $order?->patient ?? $prescription?->patient;
        $type        = 'virtual_account';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 세금계산서 ──────────────────────── */
    private function showTaxInvoice(int $id)
    {
        $order = Order::with([
            'prescription.patient',
            'prescription.items',
            'patient',
            'faxLogs.sender',
        ])->where('tax_invoice_status', '!=', 'not_issued')
          ->findOrFail($id);

        $record      = null;
        $prescription = $order->prescription;
        $patient     = $order->patient ?? $prescription?->patient;
        $type        = 'tax_invoice';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 현금영수증 ──────────────────────── */
    private function showCashReceipt(int $id)
    {
        $order = Order::with([
            'prescription.patient',
            'prescription.items',
            'patient',
        ])->where('cash_receipt_status', '!=', 'not_issued')
          ->findOrFail($id);

        $record      = null;
        $prescription = $order->prescription;
        $patient     = $order->patient ?? $prescription?->patient;
        $type        = 'cash_receipt';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: NHIS 청구 팩스 ──────────────────── */
    private function showNhis(int $id)
    {
        $record = NhisFaxLog::with([
            'order.prescription.patient',
            'order.prescription.items',
            'order.patient',
            'sender',
        ])->findOrFail($id);

        // 같은 주문의 전체 발송 이력 (타임라인용)
        $allLogs = NhisFaxLog::with('sender')
            ->where('order_id', $record->order_id)
            ->orderBy('created_at')
            ->get();

        $order       = $record->order;
        $prescription = $order?->prescription;
        $patient     = $order?->patient ?? $prescription?->patient;
        $type        = 'nhis';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type', 'allLogs'));
    }

    /* ── 쿼리 헬퍼 ─────────────────────────────── */
    private function virtualAccountQuery(?string $search, string $from, string $to)
    {
        return TossPayment::with(['order.prescription.patient', 'order.patient'])
            ->whereBetween(\DB::raw('DATE(toss_payments.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('toss_payments.toss_order_id', 'like', "%{$search}%")
                       ->orWhere('toss_payments.account_number', 'like', "%{$search}%")
                       ->orWhere('toss_payments.customer_name', 'like', "%{$search}%")
                       ->orWhereHas('order.patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"))
                       ->orWhereHas('order', fn($q3) => $q3->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('toss_payments.created_at');
    }

    private function taxInvoiceQuery(?string $search, string $from, string $to)
    {
        return Order::with(['prescription.patient', 'patient'])
            ->whereNotNull('tax_invoice_no')
            ->whereBetween(\DB::raw('DATE(tax_invoice_issued_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('order_number', 'like', "%{$search}%")
                       ->orWhere('tax_invoice_no', 'like', "%{$search}%")
                       ->orWhere('tax_invoice_biz_name', 'like', "%{$search}%")
                       ->orWhereHas('patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('tax_invoice_issued_at');
    }

    private function cashReceiptQuery(?string $search, string $from, string $to)
    {
        return Order::with(['prescription.patient', 'patient'])
            ->whereNotNull('cash_receipt_no')
            ->whereBetween(\DB::raw('DATE(cash_receipt_issued_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('order_number', 'like', "%{$search}%")
                       ->orWhere('cash_receipt_no', 'like', "%{$search}%")
                       ->orWhere('cash_receipt_identifier', 'like', "%{$search}%")
                       ->orWhereHas('patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('cash_receipt_issued_at');
    }

    private function nhisQuery(?string $search, string $from, string $to)
    {
        return NhisFaxLog::with(['order.prescription.patient', 'order.patient', 'sender'])
            ->whereBetween(\DB::raw('DATE(nhis_fax_logs.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('document_title', 'like', "%{$search}%")
                       ->orWhere('reference_no',  'like', "%{$search}%")
                       ->orWhereHas('order', fn($q3) => $q3->where('order_number', 'like', "%{$search}%"))
                       ->orWhereHas('order.patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('nhis_fax_logs.created_at');
    }
}
