<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // 업무 큐 통계
        $stats = [
            'review_needed'  => Prescription::where('status', 'review_needed')->count(),
            'ocr_processing' => Prescription::whereIn('status', ['pending', 'ocr_processing'])->count(),
            'approved_today' => Prescription::where('status', 'approved')->whereDate('reviewed_at', today())->count(),
            'total_today'    => Prescription::whereDate('created_at', today())->count(),
            'total_month'    => Prescription::whereMonth('created_at', now()->month)->count(),
            'orders_pending' => Order::where('status', 'pending')->count(),
            'nhis_pending'        => Order::where('nhis_claim_status', 'pending')->count(),
            'repurchase_today'    => Prescription::whereNotNull('repurchase_date')
                                        ->whereDate('repurchase_date', today())->count(),
            'repurchase_upcoming' => Prescription::whereNotNull('repurchase_date')
                                        ->whereBetween('repurchase_date', [today(), today()->addDays(7)])->count(),
        ];

        // 최근 처방전 목록
        $recentPrescriptions = Prescription::with(['patient', 'assignedUser', 'order'])
            ->latest()
            ->take(10)
            ->get();

        // wwGrid용: 최근 처방전 현황(배지→텍스트, 더블클릭 시 상세 이동용 rx_number 포함)
        $recentRxGrid = $recentPrescriptions->map(fn ($rx) => [
            'rx_number' => $rx->rx_number,
            'patient'   => $rx->patient?->name ?? $rx->patient_name_ocr ?? '-',
            'birth'     => $rx->patient?->birth_date?->format('Y-m-d') ?? '-',
            'ocr'       => $rx->status_label,
            'order'     => $rx->order ? '주문완료' : '주문대기',
            'claim'     => $rx->order?->nhis_claim_status === 'approved' ? '청구완료' : '청구대기',
            'manager'   => $rx->assignedUser?->name ?? '-',
        ])->values();

        // 최근 활동 로그
        $activities = \Spatie\Activitylog\Models\Activity::latest()->take(4)->get();

        // 푸터 회사 정보 — 위임장 설정과 같은 값을 쓴다.
        // 따로 두면 한쪽만 고쳐져 서식과 화면이 어긋난다.
        $company = \App\Models\DelegationSetting::first();

        return view('dashboard.index', compact(
            'stats', 'recentPrescriptions', 'recentRxGrid', 'activities', 'company'
        ));
    }
}
