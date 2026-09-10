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
            // 담당자가 다 적고 검수를 기다리는 것. 예전 「처리중」(OCR) 자리를 대신한다.
            'review_requested' => Prescription::where('status', 'review_requested')->count(),
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

        /* 최근 처방전 목록.

           주문 줄의 결제까지 함께 불러 둔다 — 「주문」 칸이 입금을 보았는지로
           대기 이름을 가른다(2026-09-10 확인요청 8ㆍ9쪽). 열 줄이라 큰일은 아니나
           줄마다 묻지 않는 편이 낫다. */
        $recentPrescriptions = Prescription::with(['patient', 'assignedUser', 'order.tossPayment'])
            ->latest()
            ->take(10)
            ->get();

        // wwGrid용: 최근 처방전 현황(배지→텍스트, 더블클릭 시 상세 이동용 rx_number 포함)
        $recentRxGrid = $recentPrescriptions->map(fn ($rx) => [
            'rx_number' => $rx->rx_number,
            'patient'   => $rx->patient?->name ?? $rx->patient_name_ocr ?? '-',
            'birth'     => $rx->patient?->birth_date?->format('Y-m-d') ?? '-',
            'ocr'       => $rx->status_label,
            /* 주문 줄이 있다는 것만으로 「주문완료」라 적고 있었다 (2026-09-10 확인요청 9쪽).

               그 줄은 처방전이 담길 때 저절로 선다(Prescription::booted → OrderSync::seed).
               그러니 이 칸은 늘 「주문완료」였다 — 검수도 안 끝난 건이, 제품을 한 줄도
               담지 않은 건이 다 주문완료로 보였다.

               주문 줄이 스스로 말하게 한다. 아직 창고로 보내지 않았으면 무엇을
               기다리는지 적히고(입금 대기ㆍ출고 대기), 보냈으면 어디까지 왔는지 적힌다
               (주문 확정ㆍ재고 할당ㆍ송장 출력ㆍ출고 완료…). */
            'order'     => $rx->order?->status_label ?? '주문 대기',
            'claim'     => $rx->order?->nhis_claim_status === 'approved' ? '청구완료' : '청구대기',
            'manager'   => $rx->assignedUser?->name ?? '-',
        ])->values();

        // 최근 활동 로그
        $activities = \Spatie\Activitylog\Models\Activity::latest()->take(4)->get();

        return view('dashboard.index', compact(
            'stats', 'recentPrescriptions', 'recentRxGrid', 'activities'
        ));
    }
}
