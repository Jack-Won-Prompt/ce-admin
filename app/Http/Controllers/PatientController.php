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

        // ── wwGrid 데이터 ──────────────────────────────────
        $gridData = $query->get()->map(function ($p) {
            // 생년월일 + 나이
            $birth = $p->birth_date
                ? $p->birth_date->format('Y-m-d') . ' (만 ' . $p->age . '세)'
                : '-';

            // 성별 배지 → 텍스트
            $gender = match ($p->gender) {
                'male'   => '남',
                'female' => '여',
                default  => '-',
            };

            // 건보 배지 → 텍스트
            $nhis = $p->is_nhis_eligible
                ? '급여 ' . $p->nhis_coverage_rate . '%'
                : '비급여';

            // 재구매일 + D-day
            $rd = $p->prescriptions_max_repurchase_date;
            if ($rd) {
                $rdDate = \Carbon\Carbon::parse($rd);
                $diff   = (int) today()->diffInDays($rdDate, false);
                $repurchase = $rdDate->format('Y-m-d') . ($diff >= 0 ? ' (D-' . $diff . ')' : ' (D+' . abs($diff) . ')');
            } else {
                $repurchase = '-';
            }

            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'resident_no'     => $p->masked_resident_no ?? '-',
                'birth_date'      => $birth,
                'gender'          => $gender,
                'mobile'          => $p->mobile ?? $p->phone ?? '-',
                'nhis'            => $nhis,
                'rx_count'        => (int) $p->prescriptions_count,
                'repurchase_date' => $repurchase,
                'created'         => $p->created_at?->format('Y-m-d') ?? '',
            ];
        });

        $total = $gridData->count();

        return view('patients.index', compact('gridData', 'total'));
    }

    // ── 상세/편집 화면 ────────────────────────────────────
    public function show(Patient $patient): View
    {
        $patient->load(['prescriptions' => fn($q) => $q->latest()->take(20)]);
        return view('patients.show', compact('patient'));
    }

    /** 환자 이력(처방전·상담·구매) — 목록 화면 우측 상세 탭용 JSON */
    public function histories(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $rx = $patient->prescriptions()->latest()->take(50)->get();

        $prescriptions = $rx->map(fn ($p) => [
            'rx_number' => $p->rx_number,
            'hospital'  => $p->hospital_name ?? '-',
            'date'      => $p->created_at->format('Y-m-d'),
            'status'    => $p->status_label,
            'url'       => route('prescriptions.show', $p),
        ])->values();

        $counseling = $rx->filter(fn ($p) => !empty($p->counseling_data))->map(function ($p) {
            $c = $p->counseling;
            return [
                'counsel_no' => $c->counselling_no ?? $c->counsel_no ?? '-',
                'rx_number'  => $p->rx_number,
                'date'       => ($c->counselling_date ?? null) ?: $p->created_at->format('Y-m-d'),
                'note'       => $c->memo ?? $c->note ?? $p->review_memo ?? '',
                'url'        => route('prescriptions.show', $p),
            ];
        })->values();

        $purchases = $patient->orders()->latest()->take(50)->get()->map(fn ($o) => [
            'order_number' => $o->order_number,
            'product'      => $o->product_name ?? '-',
            'qty'          => (int) ($o->quantity ?? 1),
            'amount'       => (int) $o->total_amount,
            'status'       => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
            'date'         => $o->created_at->format('Y-m-d'),
            'url'          => route('orders.show', $o),
        ])->values();

        return response()->json([
            'name'          => $patient->name,
            'prescriptions' => $prescriptions,
            'counseling'    => $counseling,
            'purchases'     => $purchases,
        ]);
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
