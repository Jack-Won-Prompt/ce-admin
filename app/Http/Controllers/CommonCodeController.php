<?php

namespace App\Http\Controllers;

use App\Models\CommonCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 환경 설정 — 공통 코드.
 *
 * 화면마다 고르는 목록을 여기서 등록·수정한다. 지금은 서류 유형 하나지만, 목록을
 * 늘릴 때 코드를 고칠 일은 없다(config/common-codes.php 에 한 항목을 더하면 탭이 는다).
 */
class CommonCodeController extends Controller
{
    public function index(Request $request): View
    {
        $groups  = CommonCode::groups();
        $current = (string) $request->get('group', array_key_first($groups));
        if (!isset($groups[$current])) {
            $current = array_key_first($groups);
        }

        $counts = CommonCode::selectRaw('`group`, count(*) as cnt')
            ->groupBy('group')->pluck('cnt', 'group');

        return view('common-codes.index', [
            'groups'   => $groups,
            'current'  => $current,
            'counts'   => $counts,
            'kinds'    => CommonCode::kinds($current),
            'gridData' => $this->rows($current),
        ]);
    }

    /** 한 목록의 줄들 — 표에 실을 값은 여기서 만든다 */
    private function rows(string $group): array
    {
        $kinds = CommonCode::kinds($group);

        return CommonCode::group($group)
            ->orderBy('kind')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (CommonCode $c) => [
                'id'         => $c->id,
                'kind'       => $kinds[$c->kind] ?? ($c->kind ?: '-'),
                'kind_key'   => $c->kind,
                'code'       => $c->code,
                'label'      => $c->label,
                'note'       => $c->note ?: '',
                'sort_order' => $c->sort_order,
                'active'     => $c->is_active ? '사용' : '사용 안 함',
                'is_active'  => $c->is_active,
                'is_system'  => $c->is_system,
                'system'     => $c->is_system ? '시스템' : '',
            ])->values()->all();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $code = CommonCode::create($data + ['created_by' => Auth::id()]);
        CommonCode::forget($code->group);

        activity()->causedBy(Auth::user())->performedOn($code)
            ->log("공통 코드 등록: {$code->group} {$code->code} {$code->label}");

        return response()->json(['success' => true, 'message' => "{$code->label} 을(를) 등록했습니다."]);
    }

    public function update(Request $request, CommonCode $commonCode): JsonResponse
    {
        $data = $this->validated($request, $commonCode);

        /* 시스템 코드는 이름과 차례만 손댈 수 있다 — 코드 값이 바뀌면 이미 쌓인
           서류가 제 이름을 잃고, 꺼 두면 그 서류를 다시 고를 길이 없어진다. */
        if ($commonCode->is_system) {
            $data = collect($data)->only(['label', 'note', 'sort_order'])->all();
        }

        $commonCode->update($data);
        CommonCode::forget($commonCode->group);

        activity()->causedBy(Auth::user())->performedOn($commonCode)
            ->log("공통 코드 수정: {$commonCode->group} {$commonCode->code} {$commonCode->label}");

        return response()->json(['success' => true, 'message' => '저장했습니다.']);
    }

    public function destroy(CommonCode $commonCode): JsonResponse
    {
        if ($commonCode->is_system) {
            return response()->json([
                'success' => false,
                'message' => '시스템이 쓰는 코드라 지울 수 없습니다. 필요하면 이름만 고치십시오.',
            ], 422);
        }

        $label = $commonCode->label;
        $group = $commonCode->group;

        /* 지우지 않고 꺼 둔다 — 이미 그 유형으로 올려 둔 서류가 이름을 잃으면 안 된다.
           꺼 두면 새로 고를 때만 보이지 않는다. */
        $commonCode->update(['is_active' => false]);
        CommonCode::forget($group);

        activity()->causedBy(Auth::user())->performedOn($commonCode)
            ->log("공통 코드 사용 중지: {$group} {$commonCode->code} {$label}");

        return response()->json([
            'success' => true,
            'message' => "{$label} 을(를) 사용 안 함으로 바꿨습니다. 이미 올린 서류의 이름은 그대로 남습니다.",
        ]);
    }

    /**
     * 표에서 고친 것을 한꺼번에 받는다.
     *
     * 줄마다 창을 열면 여러 줄을 고치는 날에 그만큼 손이 간다. 엑셀처럼 표에서
     * 고치고 한 번 저장한다. 시스템 코드는 이름·차례·메모만 받고, 지우는 것은 거절한다.
     */
    public function bulk(Request $request): JsonResponse
    {
        $group = (string) $request->input('group');
        CommonCode::groupOr404($group);
        $kinds = array_keys(CommonCode::kinds($group));

        $data = $request->validate([
            'rows'                => ['array'],
            'rows.*.id'           => ['nullable', 'integer', 'exists:common_codes,id'],
            'rows.*.kind'         => ['required', Rule::in($kinds)],
            'rows.*.code'         => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'rows.*.label'        => ['required', 'string', 'max:100'],
            'rows.*.note'         => ['nullable', 'string', 'max:200'],
            'rows.*.sort_order'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.is_active'    => ['boolean'],
            'removed'             => ['array'],
            'removed.*'           => ['integer', 'exists:common_codes,id'],
        ], [
            'rows.*.code.regex' => '코드는 영문 소문자·숫자·밑줄만 씁니다 (예: tax_invoice).',
        ]);

        $saved = $off = 0;
        $skipped = [];

        foreach ($data['rows'] ?? [] as $row) {
            $existing = !empty($row['id']) ? CommonCode::find($row['id']) : null;

            // 같은 목록 안에서 코드는 하나뿐이다
            $dup = CommonCode::group($group)->where('code', $row['code'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
            if ($dup) {
                $skipped[] = "{$row['code']} — 이미 쓰는 코드";
                continue;
            }

            $fields = [
                'kind'       => $row['kind'],
                'code'       => $row['code'],
                'label'      => $row['label'],
                'note'       => $row['note'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active'  => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing) {
                // 시스템 코드는 부르는 이름만 바뀐다
                if ($existing->is_system) {
                    $fields = collect($fields)->only(['label', 'note', 'sort_order'])->all();
                }
                $existing->update($fields);
            } else {
                CommonCode::create($fields + ['group' => $group, 'created_by' => Auth::id()]);
            }
            $saved++;
        }

        foreach ($data['removed'] ?? [] as $id) {
            $code = CommonCode::find($id);
            if (!$code) {
                continue;
            }
            if ($code->is_system) {
                $skipped[] = "{$code->label} — 시스템 코드라 둔다";
                continue;
            }
            /* 지우지 않고 꺼 둔다 — 이미 그 유형으로 올려 둔 서류가 이름을 잃으면 안 된다 */
            $code->update(['is_active' => false]);
            $off++;
        }

        CommonCode::forget($group);

        activity()->causedBy(Auth::user())
            ->log("공통 코드 일괄 저장: {$group} 저장 {$saved} · 사용중지 {$off}");

        $msg = "저장 {$saved}건" . ($off ? " · 사용 중지 {$off}건" : '');
        if ($skipped) {
            $msg .= ' · 건너뜀: ' . implode(', ', $skipped);
        }

        return response()->json(['success' => true, 'message' => $msg]);
    }

    private function validated(Request $request, ?CommonCode $except = null): array
    {
        $group = (string) $request->input('group', $except?->group);
        CommonCode::groupOr404($group);
        $kinds = array_keys(CommonCode::kinds($group));

        return $request->validate([
            'group'      => ['required', Rule::in(array_keys(CommonCode::groups()))],
            'kind'       => ['required', Rule::in($kinds)],
            'code'       => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                             Rule::unique('common_codes', 'code')->where('group', $group)
                                 ->ignore($except?->id)],
            'label'      => ['required', 'string', 'max:100'],
            'note'       => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['boolean'],
        ], [
            'code.regex'  => '코드는 영문 소문자·숫자·밑줄만 씁니다 (예: tax_invoice).',
            'code.unique' => '이미 쓰는 코드입니다.',
        ]);
    }
}
