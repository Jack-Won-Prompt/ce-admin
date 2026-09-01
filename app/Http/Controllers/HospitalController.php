<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 병원 조회ㆍ등록.
 *
 * 주문 등록 화면이 병원명 옆 「조회」로 부른다. 있으면 고르고, 없으면 그 자리에서
 * 만들어 고른다 — 거래처 등록 팝업과 같은 결이다.
 */
class HospitalController extends Controller
{
    /** 이름ㆍ요양기관번호로 찾는다 */
    public function search(Request $request): JsonResponse
    {
        $rows = Hospital::query()
            ->where('is_active', true)
            ->search($request->query('q'))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'tel', 'address', 'department']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 없는 병원을 그 자리에서 만든다 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:120',
            /* 요양기관번호는 여덟 자리다. 아직 모르는 채로 접수하는 건이 있어 비워 둘 수
               있게 두되, 적었으면 다른 병원이 쓰던 번호와 겹치지 않게 막는다 —
               번호가 겹치면 청구가 남의 병원으로 간다. */
            'code'       => ['nullable', 'string', 'max:20', Rule::unique('hospitals', 'code')->whereNotNull('code')],
            'tel'        => 'nullable|string|max:30',
            'fax'        => 'nullable|string|max:30',
            'address'    => 'nullable|string|max:255',
            'department' => 'nullable|string|max:60',
            'memo'       => 'nullable|string|max:255',
        ], [
            'code.unique' => '이미 같은 요양기관번호를 쓰는 병원이 있습니다.',
        ]);

        $name = trim($data['name']);

        /* 같은 이름이 이미 있으면 새로 만들지 않고 그것을 준다. 손으로 치는 자리라
           띄어쓰기만 다른 같은 병원이 쌓이기 쉽다. */
        $exists = Hospital::whereRaw('TRIM(name) = ?', [$name])->first();
        if ($exists) {
            // 번호를 모르고 있던 줄이면 이번에 적은 번호로 채워 준다
            if (! $exists->code && ! empty($data['code'])) {
                $exists->update(['code' => $data['code']]);
            }

            return response()->json([
                'success' => true,
                'created' => false,
                'message' => '이미 있는 병원이라 그것으로 골랐습니다.',
                'data'    => $exists->only(['id', 'name', 'code', 'tel', 'address', 'department']),
            ]);
        }

        $h = Hospital::create($data + ['name' => $name, 'created_by' => Auth::id()]);

        activity()->causedBy(Auth::user())->performedOn($h)
            ->log('병원 등록: ' . $h->name . ($h->code ? ' (' . $h->code . ')' : ''));

        return response()->json([
            'success' => true,
            'created' => true,
            'data'    => $h->only(['id', 'name', 'code', 'tel', 'address', 'department']),
        ]);
    }
}
