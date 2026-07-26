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

        $rows = $rows->get();

        // wwGrid: 타입별 그리드 데이터/컬럼 (클라이언트사이드, 배지→텍스트)
        [$gridData, $gridColumns] = $this->buildGrid($type, $rows);
        $total = $gridData->count();

        $counts = [
            'virtual_account' => TossPayment::whereHas('order')->count(),
            'tax_invoice'     => Order::whereNotNull('tax_invoice_no')->count(),
            'cash_receipt'    => Order::whereNotNull('cash_receipt_no')->count(),
            'nhis'            => NhisFaxLog::count(),
        ];

        return view('dispatch.index', compact('gridData', 'gridColumns', 'total', 'type', 'counts', 'dateFrom', 'dateTo', 'search', 'perPage'));
    }

    /** 타입별 wwGrid 데이터/컬럼 생성 (원본 테이블 셀을 텍스트로 매핑) */
    private function buildGrid(string $type, $rows): array
    {
        if ($type === 'tax_invoice') {
            $data = $rows->map(function ($o) {
                $patient = $o->patient ?? $o->prescription?->patient;
                $si = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$o->tax_invoice_status] ?? ['미발행', 'secondary'];
                return [
                    'id'       => $o->id,
                    'created'  => $o->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '-',
                    'order_no' => $o->order_number,
                    'patient'  => $patient?->name ?? '-',
                    'biz_no'   => $o->tax_invoice_biz_no ?? '-',
                    'biz_name' => $o->tax_invoice_biz_name ?? '-',
                    'supply'   => (int) $o->tax_invoice_supply,
                    'vat'      => (int) $o->tax_invoice_vat,
                    'inv_no'   => $o->tax_invoice_no ?? '-',
                    'status'   => $si[0],
                ];
            })->values();
            $columns = [
                ['header' => '발행일시',   'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no', 'width' => 120],
                ['header' => '환자명',     'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '사업자번호', 'name' => 'biz_no',   'width' => 120],
                ['header' => '상호',       'name' => 'biz_name', 'width' => 140],
                ['header' => '공급가액',   'name' => 'supply',   'width' => 100, 'editor' => 'number'],
                ['header' => '부가세',     'name' => 'vat',      'width' => 90,  'editor' => 'number'],
                ['header' => '계산서번호', 'name' => 'inv_no',   'width' => 130],
                ['header' => '상태',       'name' => 'status',   'width' => 80,  'align' => 'center', 'sortable' => true],
            ];
        } elseif ($type === 'cash_receipt') {
            $data = $rows->map(function ($o) {
                $patient = $o->patient ?? $o->prescription?->patient;
                $ci = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$o->cash_receipt_status] ?? ['미발행', 'secondary'];
                return [
                    'id'       => $o->id,
                    'created'  => $o->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '-',
                    'order_no' => $o->order_number,
                    'patient'  => $patient?->name ?? '-',
                    'cr_type'  => \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$o->cash_receipt_type] ?? '-',
                    'ident'    => $o->cash_receipt_identifier ?? '-',
                    'amount'   => (int) $o->cash_receipt_amount,
                    'rc_no'    => $o->cash_receipt_no ?? '-',
                    'status'   => $ci[0],
                ];
            })->values();
            $columns = [
                ['header' => '발행일시',   'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no', 'width' => 120],
                ['header' => '환자명',     'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '종류',       'name' => 'cr_type',  'width' => 90,  'align' => 'center'],
                ['header' => '식별번호',   'name' => 'ident',    'width' => 130],
                ['header' => '발행금액',   'name' => 'amount',   'width' => 100, 'editor' => 'number'],
                ['header' => '영수증번호', 'name' => 'rc_no',    'width' => 130],
                ['header' => '상태',       'name' => 'status',   'width' => 80,  'align' => 'center', 'sortable' => true],
            ];
        } elseif ($type === 'nhis') {
            $data = $rows->map(function ($log) {
                $order   = $log->order;
                $patient = $order?->patient ?? $order?->prescription?->patient;
                $sl = \App\Models\NhisFaxLog::STATUS_LABELS[$log->status] ?? ['label' => $log->status, 'badge' => 'secondary'];
                $result = '-';
                if ($log->nhis_result) {
                    $rl = \App\Models\NhisFaxLog::NHIS_RESULT_LABELS[$log->nhis_result] ?? ['label' => $log->nhis_result];
                    $result = $rl['label'] . ($log->approved_amount ? ' (₩' . number_format($log->approved_amount) . ')' : '');
                }
                return [
                    'id'       => $log->id,
                    'created'  => $log->created_at->format('Y-m-d H:i'),
                    'order_no' => $order?->order_number ?? '-',
                    'patient'  => $patient?->name ?? '-',
                    'fax'      => $log->fax_number ?? '-',
                    'claim'    => (int) $log->claim_amount,
                    'nhis'     => (int) $log->nhis_amount,
                    'ref_no'   => $log->reference_no ?? '-',
                    'status'   => $sl['label'],
                    'result'   => $result,
                    'sender'   => $log->sender?->name ?? '-',
                ];
            })->values();
            $columns = [
                ['header' => '발송일시', 'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호', 'name' => 'order_no', 'width' => 120],
                ['header' => '환자명',   'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '발송 팩스','name' => 'fax',      'width' => 120],
                ['header' => '청구금액', 'name' => 'claim',    'width' => 100, 'editor' => 'number'],
                ['header' => 'NHIS 부담','name' => 'nhis',     'width' => 100, 'editor' => 'number'],
                ['header' => '참조번호', 'name' => 'ref_no',   'width' => 120],
                ['header' => '전송상태', 'name' => 'status',   'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '심사결과', 'name' => 'result',   'width' => 130, 'align' => 'center'],
                ['header' => '발송자',   'name' => 'sender',   'width' => 90],
            ];
        } else { // virtual_account
            $data = $rows->map(function ($tp) {
                $order   = $tp->order;
                $patient = $order?->patient ?? $order?->prescription?->patient;
                $due = $tp->due_date ? $tp->due_date->format('Y-m-d') . ($tp->is_expired ? ' (만료)' : '') : '-';
                return [
                    'id'       => $tp->id,
                    'created'  => $tp->created_at->format('Y-m-d H:i'),
                    'order_no' => $order?->order_number ?? '-',
                    'patient'  => $patient?->name ?? $tp->customer_name ?? '-',
                    'bank'     => $tp->bank_name,
                    'account'  => $tp->account_number ?? '-',
                    'amount'   => (int) $tp->amount,
                    'due'      => $due,
                    'status'   => $tp->status_label,
                ];
            })->values();
            $columns = [
                ['header' => '발행일시', 'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호', 'name' => 'order_no', 'width' => 120],
                ['header' => '환자명',   'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '은행',     'name' => 'bank',     'width' => 90],
                ['header' => '계좌번호', 'name' => 'account',  'width' => 150],
                ['header' => '금액',     'name' => 'amount',   'width' => 100, 'editor' => 'number'],
                ['header' => '만료일',   'name' => 'due',      'width' => 130, 'align' => 'center'],
                ['header' => '상태',     'name' => 'status',   'width' => 90,  'align' => 'center', 'sortable' => true],
            ];
        }

        return [$data, $columns];
    }

    public function show(string $type, int $id)
    {
        // 상세보기 탭 iframe에서 열릴 때 사이드바/네비 숨김
        view()->share('embed', request()->boolean('embed'));

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
