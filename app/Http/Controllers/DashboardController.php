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
        $recentPrescriptions = Prescription::with(['patient', 'assignedUser'])
            ->latest()
            ->take(10)
            ->get();

        // 최근 활동 로그
        $activities = \Spatie\Activitylog\Models\Activity::latest()->take(5)->get();

        return view('dashboard.index', compact(
            'stats', 'recentPrescriptions', 'activities'
        ));
    }
}
