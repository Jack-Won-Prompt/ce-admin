<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 병원 조회ㆍ등록ㆍ관리.
 *
 * 주문 등록 화면이 병원명 옆 「조회」로 부른다. 있으면 고르고, 없으면 그 자리에서
 * 만들어 고른다 — 거래처 등록 팝업과 같은 결이다.
 *
 * ## 관리 화면을 더한다 (2026-10-02 지시)
 *
 * 여태 이 표를 **보는 자리가 없었다.** 조회와 등록뿐이라 한 번 잘못 담긴 줄을
 * 고칠 길이 아예 없었다 — 이름과 요양기관번호가 어긋나도 손댈 수 없었고, 그 번호로
 * 다른 병원을 등록하려 하면 「이미 같은 요양기관번호를 쓰는 병원이 있습니다」로 막혔다.
 *
 * 운영에 302곳이 담겨 있는데 상태가 좋지 않다(2026-10-02 확인).
 *
 *   · 같은 요양기관번호를 두 곳 이상이 쓰는 경우 **24건**
 *     (11100206 을 「연세대학교의과대학세브란스」와 「연세의료원」이 함께 쓴다)
 *   · 병원명 자리에 메모가 들어간 줄 — 「2 1층로 변경」ㆍ「현금결제 확인」ㆍ「5735」ㆍ
 *     「★의료용품구매확인서 동봉」. 이관할 때 메모 칸이 이름으로 들어온 것으로 보인다
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

    /**
     * 병원 관리 화면 (2026-10-02 지시).
     *
     * 목록ㆍ수정ㆍ겹친 번호 모아보기를 한 자리에 세운다.
     */
    public function index(Request $request): View
    {
        $찾는말   = trim((string) $request->query('q'));
        $겹친것만 = $request->boolean('dup');

        /* 번호가 겹친 줄 — 정리할 자리를 바로 찾는다. 두 번 쓰이므로 먼저 셈해 둔다 */
        $겹친번호 = Hospital::query()
            ->selectRaw('code, count(*) c')
            ->whereNotNull('code')->where('code', '!=', '')
            ->groupBy('code')->havingRaw('count(*) > 1')
            ->pluck('c', 'code');

        $q = Hospital::query()->search($찾는말);

        if ($겹친것만) {
            $q->whereIn('code', $겹친번호->keys()->all() ?: ['']);
        }

        $줄들 = $q->orderByRaw('code IS NULL, code')->orderBy('name')->get();

        /* 처방전이 이 병원을 몇 건 쓰고 있나 — 합칠 때 어느 쪽을 남길지 가리는 잣대다.
           처방전의 병원은 **글자로** 적혀 있다(외래키가 아니다). 번호로도 이름으로도
           적혀 있어 둘 다 센다. */
        $쓰임 = Prescription::query()
            ->selectRaw('hospital_code, hospital_name, count(*) c')
            ->groupBy('hospital_code', 'hospital_name')
            ->get();

        $번호별 = $쓰임->groupBy('hospital_code')->map->sum('c');
        $이름별 = $쓰임->groupBy('hospital_name')->map->sum('c');

        return view('hospitals.index', [
            'rows' => $줄들->map(fn (Hospital $h) => [
                'id'         => $h->id,
                'name'       => (string) $h->name,
                'code'       => (string) ($h->code ?? ''),
                'tel'        => (string) ($h->tel ?? ''),
                'fax'        => (string) ($h->fax ?? ''),
                'address'    => (string) ($h->address ?? ''),
                'department' => (string) ($h->department ?? ''),
                'memo'       => (string) ($h->memo ?? ''),
                'active'     => $h->is_active ? '사용' : '사용 안 함',
                /* 번호가 겹치는 줄은 한눈에 보여야 한다 — 겹치면 청구가 남의 병원으로 간다 */
                'dup'        => ($h->code && ($겹친번호[$h->code] ?? 0) > 1) ? '겹침' : '',
                'used'       => (int) (($번호별[$h->code] ?? 0) + ($이름별[$h->name] ?? 0)),
            ])->values(),
            'q'        => $찾는말,
            'dupOnly'  => $겹친것만,
            'dupCount' => $겹친번호->count(),
            'total'    => Hospital::count(),
            'canEdit'  => perm('hospitals', 'update'),
        ]);
    }

    /**
     * 한 곳을 고친다 (2026-10-02 지시).
     *
     * 번호가 겹치면 막는다 — 겹치면 청구가 남의 병원으로 간다. 다만 **자기 자신은
     * 뺀다**(`ignore`): 이름만 고치려는데 제 번호에 걸려 막히면 손댈 길이 없다.
     */
    public function update(Request $request, Hospital $hospital): JsonResponse
    {
        abort_unless(perm('hospitals', 'update'), 403);

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'code'       => ['nullable', 'string', 'max:20',
                             Rule::unique('hospitals', 'code')->ignore($hospital->id)->whereNotNull('code')],
            'tel'        => 'nullable|string|max:30',
            'fax'        => 'nullable|string|max:30',
            'address'    => 'nullable|string|max:255',
            'department' => 'nullable|string|max:60',
            'memo'       => 'nullable|string|max:255',
            'is_active'  => 'nullable|boolean',
        ], [
            'code.unique' => '이미 같은 요양기관번호를 쓰는 병원이 있습니다.',
        ]);

        $전 = [$hospital->name, $hospital->code];

        $hospital->fill($data)->save();
        $hospital->name = trim($hospital->name);
        $hospital->save();

        activity()->causedBy(Auth::user())->performedOn($hospital)
            ->log(sprintf('병원 수정: 「%s(%s)」 → 「%s(%s)」',
                $전[0], $전[1] ?: '-', $hospital->name, $hospital->code ?: '-'));

        return response()->json([
            'success' => true,
            'message' => '고쳤습니다.',
        ]);
    }

    /**
     * 겹친 두 곳을 하나로 합친다 (2026-10-02 지시).
     *
     * 남길 쪽으로 처방전에 적힌 글자를 옮기고, 버릴 쪽은 **지우지 않고 「사용 안 함」**
     * 으로 둔다 — 지우면 그 줄을 보고 적어 둔 지난 자취를 되짚을 수 없다.
     *
     * 처방전의 병원은 `hospital_name`ㆍ`hospital_code` 두 칸에 **글자로** 적혀 있다.
     * 그래서 합치는 일은 그 글자를 바꿔 적는 것이다.
     */
    public function merge(Request $request): JsonResponse
    {
        abort_unless(perm('hospitals', 'update'), 403);

        $data = $request->validate([
            'keep' => 'required|integer|exists:hospitals,id',
            'drop' => 'required|integer|different:keep|exists:hospitals,id',
        ], [
            'drop.different' => '같은 병원끼리는 합칠 수 없습니다.',
        ]);

        $남길것 = Hospital::findOrFail((int) $data['keep']);
        $버릴것 = Hospital::findOrFail((int) $data['drop']);

        $옮긴줄 = DB::transaction(function () use ($남길것, $버릴것) {
            /* 버릴 쪽 이름으로 적힌 처방전을 남길 쪽으로 옮긴다. 번호도 함께 맞춘다 —
               번호가 달랐다면 그것이 바로 어긋나 있던 자리다. */
            $n = Prescription::where('hospital_name', $버릴것->name)
                ->update([
                    'hospital_name' => $남길것->name,
                    'hospital_code' => $남길것->code,
                ]);

            $버릴것->update([
                'is_active' => false,
                'memo'      => trim(mb_substr(
                    trim((string) $버릴것->memo) . ' / ' . now()->format('Y-m-d')
                    . ' 「' . $남길것->name . '」 으로 합침', 0, 255)),
            ]);

            return $n;
        });

        activity()->causedBy(Auth::user())->performedOn($남길것)
            ->log(sprintf('병원 합치기: 「%s」 → 「%s」 · 처방전 %d건 옮김',
                $버릴것->name, $남길것->name, $옮긴줄));

        return response()->json([
            'success' => true,
            'moved'   => $옮긴줄,
            'message' => '「' . $버릴것->name . '」 을 「' . $남길것->name . '」 으로 합쳤습니다'
                       . ' (처방전 ' . number_format($옮긴줄) . '건 옮김).',
        ]);
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
                'message' => '이미 등록된 병원이어서 해당 병원을 선택했습니다.',
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
