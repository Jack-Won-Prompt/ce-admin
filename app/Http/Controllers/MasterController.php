<?php

namespace App\Http\Controllers;

use App\Models\MasterItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 마스터 관리 — 카테고리별 탭 안에 검색 필터와 목록.
 *
 * 병원과 기관은 담는 항목이 거의 겹쳐 한 표에 category 로 나눠 담는다. 화면도 하나다.
 * 카테고리를 늘리려면 config/masters.php 에 한 항목만 더하면 탭이 생긴다.
 *
 * 청구처(공단 지사ㆍ지자체 부서)는 그 틀을 쓰지 않는다. 담는 것이 다르고(구분ㆍ부서ㆍ
 * 담당업무), 무엇보다 관할 읍ㆍ면ㆍ동을 여러 줄 딸고 있어 한 줄짜리 마스터 항목에
 * 담기지 않는다. 그래서 탭만 여기에 세우고 그리기는 조각이 맡는다
 * (resources/views/masters/_billing_offices.blade.php).
 *
 * 화면을 따로 두었던 것을 들여온 까닭은 하나다 — 병원ㆍ기관과 마찬가지로 「어디에
 * 연락하는가」를 적어 두는 자리라, 찾으러 갈 곳이 둘일 까닭이 없다.
 */
class MasterController extends Controller
{
    /** 마스터 항목 틀을 쓰지 않고 스스로 그리는 탭 */
    private const BILLING_OFFICE = 'billing_office';

    /* 반품 사유도 그 틀을 쓰지 않는다. 담는 것이 이름ㆍ차례가 아니라 규칙이라
       (금액조정ㆍ발행포함) 한 줄짜리 마스터 항목에 담기지 않는다. */
    private const RETURN_REASON = 'return_reason';

    public function index(Request $request): View
    {
        $categories = MasterItem::categories()
            + [self::BILLING_OFFICE => ['label' => '청구처', 'fields' => []]]
            + [self::RETURN_REASON  => ['label' => '반품 사유', 'fields' => []]];

        $current = $request->get('cat');
        if (!isset($categories[$current])) {
            $current = array_key_first($categories);
        }

        // 탭마다 건수를 보여 준다 — 어느 탭에 자료가 있는지 열어 보지 않아도 안다
        $counts = MasterItem::selectRaw('category, count(*) as cnt')
            ->groupBy('category')->pluck('cnt', 'category')->all();
        $counts[self::BILLING_OFFICE] = \App\Models\BillingOffice::count();
        $counts[self::RETURN_REASON]  = \App\Models\ReturnReason::count();

        if ($current === self::RETURN_REASON) {
            return view('masters.index', [
                'categories' => $categories,
                'current'    => $current,
                'counts'     => $counts,
                'reasons'    => \App\Models\ReturnReason::orderBy('sort_order')->get(),
            ]);
        }

        if ($current === self::BILLING_OFFICE) {
            return view('masters.index', [
                'categories' => $categories,
                'current'    => $current,
                'counts'     => $counts,
                // 청구처 조각이 쓰는 것 — 구분(공단ㆍ지자체)과 구분별 건수
                'kinds'      => \App\Models\BillingOffice::KINDS,
                'boCounts'   => [
                    'nhis'  => \App\Models\BillingOffice::where('kind', \App\Models\BillingOffice::KIND_NHIS)->count(),
                    'local' => \App\Models\BillingOffice::where('kind', \App\Models\BillingOffice::KIND_LOCAL)->count(),
                ],
            ]);
        }

        return view('masters.index', [
            'categories' => $categories,
            'current'    => $current,
            'counts'     => $counts,
            'gridData'   => $this->rows($request, $current),
            'q'          => (string) $request->get('q', ''),
            'onlyActive' => $request->boolean('active_only'),
        ]);
    }

    /** 지금 탭의 목록 */
    private function rows(Request $request, string $category): array
    {
        $fields = MasterItem::fieldKeys($category);

        $query = MasterItem::category($category)
            ->orderBy('sort_order')->orderBy('name');

        if ($request->filled('q')) {
            $kw     = $request->q;
            $digits = preg_replace('/\D/', '', $kw);
            $query->where(function ($w) use ($kw, $digits, $fields) {
                // 그 카테고리가 실제로 쓰는 칸에서만 찾는다
                foreach (array_intersect($fields, ['name', 'code', 'ceo', 'manager', 'address', 'note']) as $f) {
                    $w->orWhere($f, 'like', "%{$kw}%");
                }
                if ($digits !== '' && strlen($digits) >= 3) {
                    // 번호는 하이픈째 저장된다 — 비교할 때 구분자를 뗀다
                    $bare = fn ($c) => "REPLACE(REPLACE({$c}, '-', ''), ' ', '')";
                    foreach (array_intersect($fields, ['phone', 'fax', 'biz_no', 'code']) as $f) {
                        $w->orWhereRaw($bare($f) . ' LIKE ?', ["%{$digits}%"]);
                    }
                }
            });
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return $query->get()->map(function (MasterItem $m) use ($fields) {
            $row = ['id' => $m->id, 'use' => $m->is_active ? '사용' : '중지'];
            foreach ($fields as $f) {
                $row[$f] = (string) ($m->{$f} ?? '');
            }
            return $row;
        })->all();
    }

    // ── 등록 · 수정 · 삭제 ──────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $item = MasterItem::create($data + ['created_by' => auth()->id()]);

        activity()->causedBy(auth()->user())->performedOn($item)
            ->log("마스터 등록: {$this->catLabel($item->category)} {$item->name}");

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function update(Request $request, MasterItem $master): JsonResponse
    {
        $master->update($this->validated($request, $master));

        activity()->causedBy(auth()->user())->performedOn($master)
            ->log("마스터 수정: {$this->catLabel($master->category)} {$master->name}");

        return response()->json(['success' => true, 'item' => $master]);
    }

    public function destroy(MasterItem $master): JsonResponse
    {
        $label = $master->name;
        $cat   = $master->category;
        $master->delete();   // 소프트 삭제 — 지난 자료가 가리키던 것을 잃지 않는다

        activity()->causedBy(auth()->user())->log("마스터 삭제: {$this->catLabel($cat)} {$label}");

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, ?MasterItem $except = null): array
    {
        $category = $request->input('category', $except?->category);
        MasterItem::categoryOr404((string) $category);

        $rules = [
            'category'  => ['required', Rule::in(array_keys(MasterItem::categories()))],
            'name'      => 'required|string|max:150',
            'is_active' => 'boolean',
            'code'      => ['nullable', 'string', 'max:60'],
            'biz_no'    => 'nullable|string|max:40',
            'ceo'       => 'nullable|string|max:60',
            'manager'   => 'nullable|string|max:60',
            'phone'     => 'nullable|string|max:40',
            'fax'       => 'nullable|string|max:40',
            'email'     => 'nullable|email|max:190',
            'address'   => 'nullable|string|max:300',
            'note'      => 'nullable|string|max:500',
        ];

        $data = $request->validate($rules);

        // 코드는 카테고리 안에서 겹치지 않게. 같은 코드가 둘이면 어느 것을 고른 건지 알 수 없다.
        if (!empty($data['code'])) {
            $dup = MasterItem::category($category)->where('code', $data['code'])
                ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
                ->exists();
            if ($dup) {
                abort(response()->json(['success' => false, 'message' => '같은 코드가 이미 있습니다.'], 422));
            }
        }

        // 그 카테고리가 쓰지 않는 칸은 저장하지 않는다
        $allowed = array_merge(MasterItem::fieldKeys($category), ['category', 'is_active']);
        return array_intersect_key($data, array_flip($allowed));
    }

    private function catLabel(string $category): string
    {
        return MasterItem::categories()[$category]['label'] ?? $category;
    }

    /**
     * 반품 사유의 규칙을 고친다 (요청서 6쪽, 2026-08-31).
     *
     * 사유는 코드로 찾는다 — 이름이 바뀌어도 이미 접수된 건이 가리키는 곳은 그대로여야
     * 한다. 그래서 여기서는 코드를 만들거나 지우지 않고, 있는 줄의 규칙만 고친다.
     * 사유를 늘리는 일은 드물고, 늘리면 흐름(FLOWS)과 승인자도 함께 봐야 한다.
     */
    public function updateReturnReasons(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'rows'                        => 'required|array',
            'rows.*.code'                 => 'required|string|exists:return_reasons,code',
            'rows.*.label'                => 'required|string|max:60',
            'rows.*.adjusts_amount'       => 'nullable|boolean',
            'rows.*.includes_issue'       => 'nullable|boolean',
            'rows.*.is_active'            => 'nullable|boolean',
            'rows.*.sort_order'           => 'nullable|integer|min:0|max:9999',
        ]);

        foreach ($data['rows'] as $row) {
            \App\Models\ReturnReason::where('code', $row['code'])->update([
                'label'          => $row['label'],
                // 체크를 풀면 아예 오지 않는다 — 없으면 끈 것이다
                'adjusts_amount' => (bool) ($row['adjusts_amount'] ?? false),
                'includes_issue' => (bool) ($row['includes_issue'] ?? false),
                'is_active'      => (bool) ($row['is_active'] ?? false),
                'sort_order'     => (int) ($row['sort_order'] ?? 0),
            ]);
        }

        return back()->with('success', '반품 사유를 적었습니다.');
    }

}
