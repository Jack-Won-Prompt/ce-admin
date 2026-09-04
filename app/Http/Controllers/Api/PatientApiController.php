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
    /** 모바일 처방자료 업로드 — 환자 이름ㆍ연락처로 검색 */
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
                'id'         => $p->id,
                'name'       => $p->name,
                'mobile'     => $p->mobile ?? $p->phone ?? '-',
                // 동명이인을 가리는 자리다 — 이름만으로는 누구인지 알 수 없다
                'birth_date' => $this->birthOf($p),
            ])->values(),
        ]);
    }

    /**
     * 화면에 보일 생년월일.
     *
     * 생년월일 칸이 비어 있는 자료가 많다 — 주민번호만 받아 둔 거래처가 그렇다.
     * 그때는 가려 둔 주민번호 앞자리에서 짚는다. 뒷자리 첫 숫자가 어느 백 년인지를
     * 말해 준다(1ㆍ2ㆍ5ㆍ6 은 1900년대, 3ㆍ4ㆍ7ㆍ8 은 2000년대, 9ㆍ0 은 1800년대).
     * 원문을 풀지 않는다 — 가린 값만 읽는다.
     */
    private function birthOf(Patient $p): ?string
    {
        if ($p->birth_date) {
            return $p->birth_date->format('Y-m-d');
        }

        $masked = $p->masked_resident_no;
        if (! $masked || ! preg_match('/^(\d{2})(\d{2})(\d{2})-([0-9])/', $masked, $m)) {
            return null;
        }

        $century = match ($m[4]) {
            '1', '2', '5', '6' => '19',
            '3', '4', '7', '8' => '20',
            '9', '0'           => '18',
            default            => null,
        };

        if (! $century) {
            return null;
        }

        /* 있지도 않은 날짜는 내보내지 않는다 — 「1800-00-00」을 보여 주면 담당자가
           그걸 생년월일로 읽고 같은 사람이라 여긴다. 차라리 비어 있는 편이 낫다. */
        $year = (int) "{$century}{$m[1]}";
        if (! checkdate((int) $m[2], (int) $m[3], $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[3]);
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
