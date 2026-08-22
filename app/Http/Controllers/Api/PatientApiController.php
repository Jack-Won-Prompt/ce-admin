<?php
// app/Http/Controllers/Api/PatientApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientApiController extends Controller
{
    // ── GET /api/patients/search?q=... ────────────────────
    /** 모바일 처방자료 업로드 — 환자 이름/연락처로 검색 (웹 patientSearch()와 동일한 규칙) */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([
                'success'  => false,
                'message'  => '두 글자 이상 입력해주세요.',
                'patients' => [],
            ]);
        }

        $digits = preg_replace('/\D/', '', $q);

        $patients = Patient::where(function ($sub) use ($q, $digits) {
                $sub->where('name', 'like', "%{$q}%");
                if ($digits !== '' && strlen($digits) >= 4) {
                    $sub->orWhere('mobile', 'like', "%{$digits}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'success'  => true,
            'patients' => $patients->map(fn (Patient $p) => [
                'id'     => $p->id,
                'name'   => $p->name,
                'mobile' => $p->mobile ?? $p->phone ?? '-',
            ])->values(),
        ]);
    }

    // ── POST /api/patients ────────────────────────────────
    /** 모바일에서 검색 결과가 없을 때 새 환자 등록 후 그대로 선택 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:50',
            'resident_no' => 'nullable|string|max:20',
            'mobile'      => 'nullable|string|max:30',
        ], [
            'name.required' => '이름을 입력해주세요.',
        ]);

        $patient = Patient::create($data);

        return response()->json([
            'success' => true,
            'message' => "{$patient->name} 님이 등록되었습니다.",
            'patient' => ['id' => $patient->id, 'name' => $patient->name],
        ]);
    }
}
