<?php
// app/Http/Controllers/PatientController.php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    // ── 목록 ──────────────────────────────────────────────
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $query = Patient::withCount('prescriptions')
                        ->withMax('prescriptions', 'repurchase_date')
                        ->latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($request->filled('nhis')) {
            $query->where('is_nhis_eligible', $request->nhis === '1');
        }

        // 재구매일 기간 필터
        if ($request->filled('repurchase_within')) {
            $days = (int) $request->repurchase_within;
            $query->whereHas('prescriptions', function ($sub) use ($days) {
                $sub->whereNotNull('repurchase_date')
                    ->whereBetween('repurchase_date', [today(), today()->addDays($days)]);
            });
        }

        $perPage  = in_array((int) $request->input('per_page'), [10, 15, 30]) ? (int) $request->input('per_page') : 10;
        $patients = $query->paginate($perPage)->withQueryString();

        // AJAX 더보기 요청
        if ($request->wantsJson()) {
            return response()->json([
                'data'     => $patients->map(fn($p) => [
                    'id'                        => $p->id,
                    'name'                      => $p->name,
                    'note'                      => $p->note,
                    'masked_resident_no'        => $p->masked_resident_no,
                    'birth_date'                => $p->birth_date?->format('Y-m-d'),
                    'age'                       => $p->age,
                    'gender'                    => $p->gender,
                    'mobile'                    => $p->mobile ?? $p->phone,
                    'is_nhis_eligible'          => $p->is_nhis_eligible,
                    'nhis_coverage_rate'        => $p->nhis_coverage_rate,
                    'prescriptions_count'       => $p->prescriptions_count,
                    'prescriptions_max_repurchase_date' => $p->prescriptions_max_repurchase_date,
                    'created_at'                => $p->created_at->format('Y-m-d'),
                    'show_url'                  => route('patients.show', $p),
                ]),
                'has_more' => $patients->hasMorePages(),
                'next_page'=> $patients->currentPage() + 1,
                'total'    => $patients->total(),
                'loaded'   => $patients->firstItem() + $patients->count() - 1,
            ]);
        }

        return view('patients.index', compact('patients'));
    }

    // ── 상세/편집 화면 ────────────────────────────────────
    public function show(Patient $patient): View
    {
        $patient->load(['prescriptions' => fn($q) => $q->latest()->take(20)]);
        return view('patients.show', compact('patient'));
    }

    // ── 등록 ──────────────────────────────────────────────
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:50',
            'resident_no'        => 'nullable|string|max:20',
            'birth_date'         => 'nullable|date',
            'gender'             => 'nullable|in:male,female',
            'mobile'             => 'nullable|string|max:30',
            'phone'              => 'nullable|string|max:30',
            'address'            => 'nullable|string|max:300',
            'health_insurance_no'=> 'nullable|string|max:20',
            'is_nhis_eligible'   => 'boolean',
            'nhis_coverage_rate' => 'nullable|integer|min:0|max:100',
            'note'               => 'nullable|string|max:1000',
        ]);

        $patient = Patient::create($data);

        activity()->causedBy(auth()->user())->performedOn($patient)
            ->log("{$patient->name} 환자 등록");

        return response()->json([
            'success' => true,
            'message' => "{$patient->name} 환자가 등록되었습니다.",
            'id'      => $patient->id,
        ]);
    }

    // ── 수정 ──────────────────────────────────────────────
    public function update(Request $request, Patient $patient): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:50',
            'resident_no'        => 'nullable|string|max:20',
            'birth_date'         => 'nullable|date',
            'gender'             => 'nullable|in:male,female',
            'mobile'             => 'nullable|string|max:30',
            'phone'              => 'nullable|string|max:30',
            'address'            => 'nullable|string|max:300',
            'health_insurance_no'=> 'nullable|string|max:20',
            'is_nhis_eligible'   => 'boolean',
            'nhis_coverage_rate' => 'nullable|integer|min:0|max:100',
            'note'               => 'nullable|string|max:1000',
        ]);

        $patient->update($data);

        activity()->causedBy(auth()->user())->performedOn($patient)
            ->log("{$patient->name} 환자 정보 수정");

        return response()->json(['success' => true, 'message' => '저장되었습니다.']);
    }

    // ── 삭제 (소프트) ─────────────────────────────────────
    public function destroy(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $name = $patient->name;
        $patient->delete();

        activity()->causedBy(auth()->user())
            ->log("{$name} 환자 삭제");

        return response()->json(['success' => true, 'message' => "{$name} 환자가 삭제되었습니다."]);
    }
}
