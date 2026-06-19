<?php
// app/Http/Controllers/SettlementController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TossPayment;
use App\Services\TossPayments\TossApiException;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function __construct(private readonly VirtualAccountService $vaService) {}

    // ─────────────────────────────────────────────────────────────
    // 정산/가상계좌 화면
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $tab      = $request->get('tab', 'settlement');
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('date_to',   now()->format('Y-m-d'));

        // ── 정산 요약 ──────────────────────────────────────────
        $base = fn() => Order::whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);

        $summary = [
            'total_orders'  => $base()->count(),
            'total_amount'  => $base()->sum('total_amount'),
            'nhis_amount'   => $base()->sum('nhis_amount'),
            'patient_copay' => $base()->sum('patient_copay'),
            'nhis_reimb'    => $base()->sum('nhis_reimbursement'),
            'shipping_fee'  => $base()->sum('shipping_fee'),
        ];

        $statusCounts = [
            'all'       => Order::count(),
            'pending'   => Order::where('status', 'pending')->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        // ── 정산 목록 ──────────────────────────────────────────
        $query = Order::with(['patient', 'prescription', 'tossPayment'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $kw = $request->search;
            $query->where(function ($q) use ($kw) {
                $q->where('order_number', 'like', "%{$kw}%")
                  ->orWhere('product_name', 'like', "%{$kw}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$kw}%"));
            });
        }

        $orders = $query->paginate(10)->withQueryString();

        // ── 토스 API 상태 ──────────────────────────────────────
        $tossConfigured = $this->vaService->isConfigured();
        $tossReachable  = $tossConfigured ? $this->vaService->ping() : false;
        $tossApiStatus  = match(true) {
            !$tossConfigured => 'unconfigured',
            $tossReachable   => 'connected',
            default          => 'error',
        };

        // ── 가상계좌 현황 ──────────────────────────────────────
        $vaQuery = Order::with(['patient', 'tossPayment'])
            ->whereIn('status', ['confirmed', 'shipping', 'delivered'])
            ->where('patient_copay', '>', 0)
            ->latest();

        if ($request->filled('va_search')) {
            $kw = $request->va_search;
            $vaQuery->where(function ($q) use ($kw) {
                $q->where('order_number', 'like', "%{$kw}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$kw}%"))
                  ->orWhereHas('tossPayment', fn($p) => $p->where('account_number', 'like', "%{$kw}%"));
            });
        }
        if ($request->filled('va_status')) {
            match($request->va_status) {
                'issued'    => $vaQuery->whereHas('tossPayment'),
                'not_issued'=> $vaQuery->whereDoesntHave('tossPayment'),
                'done'      => $vaQuery->whereHas('tossPayment', fn($q) => $q->where('status', 'DONE')),
                'waiting'   => $vaQuery->whereHas('tossPayment', fn($q) => $q->where('status', 'WAITING_FOR_DEPOSIT')),
                default     => null,
            };
        }

        $vaOrders = $vaQuery->paginate(10, ['*'], 'va_page')->withQueryString();

        $vaStats = [
            'total'      => Order::whereIn('status', ['confirmed','shipping','delivered'])->where('patient_copay', '>', 0)->count(),
            'issued'     => TossPayment::count(),
            'done'       => TossPayment::where('status', 'DONE')->count(),
            'waiting'    => TossPayment::where('status', 'WAITING_FOR_DEPOSIT')->count(),
            'not_issued' => Order::whereIn('status', ['confirmed','shipping','delivered'])
                                ->where('patient_copay', '>', 0)
                                ->whereDoesntHave('tossPayment')->count(),
            'pending_amount' => TossPayment::where('status', 'WAITING_FOR_DEPOSIT')->sum('amount'),
        ];

        return view('settlement.index', compact(
            'tab', 'dateFrom', 'dateTo',
            'summary', 'statusCounts', 'orders',
            'vaOrders', 'vaStats',
            'tossConfigured', 'tossApiStatus'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // 처방전 상세 팝업 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function prescriptionDetail(\App\Models\Prescription $prescription): JsonResponse
    {
        $prescription->load(['patient', 'assignedUser', 'items', 'order']);

        $statusLabels = \App\Models\Prescription::STATUS_LABELS;

        return response()->json([
            'id'               => $prescription->id,
            'rx_number'        => $prescription->rx_number,
            'status_label'     => $statusLabels[$prescription->status]['label'] ?? $prescription->status,
            'status_badge'     => $statusLabels[$prescription->status]['badge'] ?? 'secondary',
            'upload_source'    => $prescription->upload_source === 'mobile' ? '모바일' : '웹',
            'issued_date'      => $prescription->issued_date?->format('Y-m-d'),
            'created_at'       => $prescription->created_at->format('Y-m-d H:i'),
            'ocr_confidence'   => $prescription->display_confidence,
            // 환자
            'patient_name'     => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-',
            'patient_birth'    => $prescription->patient?->birth_date?->format('Y-m-d') ?? '-',
            'patient_mobile'   => $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '-',
            'resident_no'      => $prescription->masked_resident_no_ocr ?? '-',
            // 병원·의사
            'hospital_name'    => $prescription->hospital_name ?? '-',
            'doctor_name'      => $prescription->doctor_name   ?? '-',
            'department'       => $prescription->department    ?? '-',
            'disease_name'     => $prescription->disease_name  ?? '-',
            'disease_code'     => $prescription->disease_code  ?? '-',
            // 처방 수량
            'daily_count'      => $prescription->daily_count,
            'total_days'       => $prescription->total_days,
            'total_count'      => $prescription->total_count,
            // 담당
            'assigned_user'    => $prescription->assignedUser?->name ?? '-',
            'admin_note'       => $prescription->admin_note ?? '',
            // 처방 품목
            'items'            => $prescription->items->map(fn($item) => [
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_status'     => match($item->nhis_status ?? '') {
                    'eligible'   => '급여',
                    'ineligible' => '비급여',
                    'partial'    => '일부급여',
                    default      => '-',
                },
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 주문 상세 팝업 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function orderDetail(Order $order): JsonResponse
    {
        $order->load(['patient', 'creator', 'tossPayment']);

        $statusLabel = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label' => $order->status, 'badge' => 'secondary'];
        $nhisMap     = ['pending' => '대기', 'submitted' => '청구완료', 'approved' => '승인', 'rejected' => '반려'];

        return response()->json([
            'order_number'    => $order->order_number,
            'status_label'    => $statusLabel['label'],
            'status_badge'    => $statusLabel['badge'],
            'nhis_status'     => $nhisMap[$order->nhis_claim_status ?? 'pending'] ?? '대기',
            'created_at'      => $order->created_at->format('Y-m-d H:i'),
            'delivered_at'    => $order->delivered_at?->format('Y-m-d H:i'),
            // 환자
            'patient_name'    => $order->patient?->name ?? '-',
            'patient_mobile'  => $order->patient?->mobile ?? '-',
            // 금액
            'unit_price'      => $order->unit_price,
            'nhis_amount'     => $order->nhis_amount,
            'patient_copay'   => $order->patient_copay,
            'shipping_fee'    => $order->shipping_fee,
            'total_amount'    => $order->total_amount,
            'nhis_reimb'      => $order->nhis_reimbursement,
            // 배송
            'shipping_address'=> $order->shipping_address ?? '-',
            'tracking_number' => $order->tracking_number  ?? '-',
            // 담당
            'creator'         => $order->creator?->name ?? '-',
            'note'            => $order->note ?? '',
            // 주문 품목 (주문 자체 필드 기준)
            'items'           => [[
                'product_name'  => $order->product_name  ?? '-',
                'product_code'  => $order->product_code  ?? '-',
                'quantity'      => $order->quantity       ?? 0,
                'unit_price'    => $order->unit_price     ?? 0,
                'nhis_amount'   => $order->nhis_amount    ?? 0,
                'patient_copay' => $order->patient_copay  ?? 0,
            ]],
            // 가상계좌
            'toss_payment'    => $order->tossPayment ? [
                'status_label'   => $order->tossPayment->status_label,
                'status_badge'   => $order->tossPayment->status_badge,
                'bank_name'      => $order->tossPayment->bank_name,
                'account_number' => $order->tossPayment->account_number,
                'amount'         => $order->tossPayment->amount,
                'due_date'       => $order->tossPayment->due_date?->format('Y-m-d H:i'),
                'deposited_at'   => $order->tossPayment->deposited_at?->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 가상계좌 발급 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function issueVirtualAccount(Request $request, Order $order): JsonResponse
    {
        // 처방 아이템 기준으로 금액 동기화 (주문 생성 후 아이템이 수정된 경우 대비)
        $prescription = $order->prescription;
        if ($prescription) {
            $prescription->loadMissing('items');
            $freshCopay    = (float) $prescription->items->sum('patient_copay');
            $shippingFee   = $order->shipping_fee ?? 3000;
            if ($freshCopay > 0) {
                $order->update([
                    'patient_copay' => $freshCopay,
                    'total_amount'  => $freshCopay + $shippingFee,
                ]);
                $order->refresh();
            }
        }

        if ($order->patient_copay <= 0) {
            return response()->json(['success' => false, 'message' => '본인부담금이 없는 주문입니다.'], 422);
        }
        if ($order->tossPayment?->status === 'DONE') {
            return response()->json(['success' => false, 'message' => '이미 입금 완료된 주문입니다.'], 422);
        }

        // ── 가상계좌 발급 비활성화 또는 API 키 미설정 → SMS만 발송 ──
        if (!config('toss.virtual_account_enabled', true) || !$this->vaService->isConfigured()) {
            $validHours = (int) config('toss.virtual_account.valid_hours', 72);
            $dueDate    = now()->addHours($validHours)->format('Y-m-d H:i');

            Log::info('[VA] 가상계좌 발급 비활성화 — SMS만 발송', ['order' => $order->order_number]);
            activity()->causedBy(auth()->user())->performedOn($order)
                ->log('가상계좌 발급 비활성화 상태 — SMS 발송만 처리');

            TossPayment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_key'    => null,
                    'toss_order_id'  => null,
                    'method'         => 'VIRTUAL_ACCOUNT',
                    'status'         => 'DISABLED',
                    'amount'         => (int) $order->patient_copay,
                    'bank'           => config('toss.virtual_account.fallback_bank', ''),
                    'account_number' => config('toss.virtual_account.fallback_account', ''),
                    'customer_name'  => $order->patient?->name ?? '환자',
                    'due_date'       => now()->addHours($validHours),
                ]
            );

            return response()->json([
                'success'        => true,
                'disabled'       => true,
                'message'        => '가상계좌 발급이 비활성화 상태입니다. SMS가 발송됩니다.',
                'bank_name'      => config('toss.virtual_account.fallback_bank', ''),
                'account_number' => config('toss.virtual_account.fallback_account', ''),
                'due_date'       => $dueDate,
                'amount'         => (int) $order->patient_copay,
                'shipping_fee'   => (int) ($order->shipping_fee ?? 3000),
            ]);
        }

        try {
            $tp = $this->vaService->issueVirtualAccount($order);

            activity()->causedBy(auth()->user())->performedOn($order)
                ->log("가상계좌 발급: {$tp->bank_name} {$tp->account_number}");

            return response()->json([
                'success'        => true,
                'bank_name'      => $tp->bank_name,
                'account_number' => $tp->account_number,
                'due_date'       => $tp->due_date?->format('Y-m-d H:i'),
                'amount'         => $tp->amount,
                'shipping_fee'   => (int) ($order->shipping_fee ?? 3000),
            ]);
        } catch (TossApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 입금 상태 실시간 조회 (AJAX)
    // ─────────────────────────────────────────────────────────────

    public function checkPaymentStatus(Order $order): JsonResponse
    {
        $tp = $order->tossPayment;
        if (!$tp?->payment_key) {
            return response()->json(['success' => false, 'message' => '발급된 가상계좌가 없습니다.'], 404);
        }

        try {
            $data = $this->vaService->fetchByPaymentKey($tp->payment_key);
            $tp->refresh();

            return response()->json([
                'success'     => true,
                'status'      => $tp->status,
                'status_label'=> $tp->status_label,
                'status_badge'=> $tp->status_badge,
                'deposited_at'=> $tp->deposited_at?->format('Y-m-d H:i'),
            ]);
        } catch (TossApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
