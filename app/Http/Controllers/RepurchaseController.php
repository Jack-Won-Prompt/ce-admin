<?php
// app/Http/Controllers/RepurchaseController.php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepurchaseController extends Controller
{
    // ── 메인 페이지 (캘린더 / 목록) ───────────────────────
    public function index(Request $request): View
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);
        $view  = $request->input('view', 'calendar'); // calendar | list

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // 해당 월 일자별 건수
        $countsByDate = Prescription::whereNotNull('repurchase_date')
            ->whereBetween('repurchase_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(repurchase_date) as d, COUNT(*) as cnt')
            ->groupBy('d')
            ->pluck('cnt', 'd')
            ->toArray();

        // 목록 뷰 — 해당 월 전체 + 필터
        $listItems = null;
        if ($view === 'list') {
            $query = Prescription::with(['patient', 'creator'])
                ->whereNotNull('repurchase_date')
                ->whereBetween('repurchase_date', [$startOfMonth, $endOfMonth])
                ->orderBy('repurchase_date');

            if ($request->filled('search')) {
                $kw = $request->search;
                $query->where(function ($q) use ($kw) {
                    $q->where('rx_number', 'like', "%{$kw}%")
                      ->orWhere('patient_name_ocr', 'like', "%{$kw}%")
                      ->orWhere('hospital_name', 'like', "%{$kw}%");
                });
            }

            $listItems = $query->paginate(20)->withQueryString();
        }

        // 월 전체 합계
        $totalCount = array_sum($countsByDate);

        return view('repurchase.index', compact(
            'year', 'month', 'view',
            'countsByDate', 'listItems', 'totalCount',
            'startOfMonth', 'endOfMonth'
        ));
    }

    // ── AJAX: 특정 날짜 처방전 목록 ───────────────────────
    public function dayItems(Request $request): JsonResponse
    {
        $date = $request->input('date'); // YYYY-MM-DD
        if (!$date) {
            return response()->json(['data' => []]);
        }

        $items = Prescription::with(['patient'])
            ->whereDate('repurchase_date', $date)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'rx_number'    => $p->rx_number,
                'patient_name' => $p->patient_name_ocr ?? $p->patient?->name ?? '-',
                'hospital'     => $p->hospital_name ?? '-',
                'status'       => $p->status,
                'status_label' => $p->status_label,
                'status_badge' => $p->status_badge,
                'created_at'   => $p->created_at->format('Y-m-d H:i'),
                'url'          => route('prescriptions.show', $p->rx_number),
            ]);

        return response()->json(['data' => $items, 'date' => $date]);
    }
}
