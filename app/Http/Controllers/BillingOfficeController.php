<?php

namespace App\Http\Controllers;

use App\Models\BillingOffice;
use App\Models\BillingOfficeArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 청구처 정보 — 값을 주고받는 길만 남았다.
 *
 * 보는 화면은 마스터 관리의 「청구처」 탭이다(masters.index?cat=billing_office).
 * 화면을 따로 두었던 것을 그리로 들였다 — 병원ㆍ기관과 마찬가지로 「어디에
 * 연락하는가」를 적어 두는 자리라, 찾으러 갈 곳이 둘일 까닭이 없다.
 *
 * 공단 지사와 지자체 부서를, 그 담당자와 관할 읍ㆍ면ㆍ동까지 적어 둔다.
 * 미리 다 채우지 않는다 — 건을 처리하며 한 번 찾은 것을 그 자리에서 쌓는다.
 */
class BillingOfficeController extends Controller
{
    /** 목록 — 화면의 표가 읽는다. */
    public function list(Request $request): JsonResponse
    {
        $rows = BillingOffice::with('areas')
            ->kind($request->input('kind'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kw = trim((string) $request->input('q'));
                $q->where(function ($w) use ($kw) {
                    $w->where('office_name', 'like', "%{$kw}%")
                      ->orWhere('dept', 'like', "%{$kw}%")
                      ->orWhere('manager_name', 'like', "%{$kw}%")
                      ->orWhere('duty', 'like', "%{$kw}%")
                      ->orWhere('region', 'like', "%{$kw}%")
                      ->orWhereHas('areas', fn ($a) => $a->where('emd', 'like', "%{$kw}%"));
                });
            })
            ->orderBy('kind')->orderBy('region')->orderBy('office_name')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'rows' => $rows->map(fn ($o) => $this->payload($o))]);
    }

    /**
     * 관할 찾기 — 읍ㆍ면ㆍ동으로 좁힌다.
     *
     * 읍면동 이름은 시군구가 달라도 겹친다(중동ㆍ신흥동…). 그래서 시군구를 함께 받으면
     * 그것으로 먼저 가리고, 그렇게 걸러 아무것도 없으면 읍면동만으로 다시 본다 —
     * 시군구를 못 뽑은 주소도 있기 때문이다(도로명 주소).
     */
    public function lookup(Request $request): JsonResponse
    {
        $emd     = trim((string) $request->input('emd'));
        $sigungu = trim((string) $request->input('sigungu'));

        if ($emd === '') {
            return response()->json(['success' => true, 'rows' => [], 'message' => '읍ㆍ면ㆍ동을 알 수 없습니다.']);
        }

        $base = fn () => BillingOffice::with('areas')->active()
            ->kind($request->input('kind'))
            ->whereHas('areas', fn ($a) => $a->where('emd', $emd));

        $rows = $sigungu !== ''
            ? $base()->whereHas('areas', fn ($a) => $a->where('emd', $emd)->where('sigungu', $sigungu))->get()
            : collect();

        $narrowed = $rows->isNotEmpty();
        if (!$narrowed) {
            $rows = $base()->get();
        }

        return response()->json([
            'success'  => true,
            'emd'      => $emd,
            'sigungu'  => $sigungu ?: null,
            'narrowed' => $narrowed,
            'rows'     => $rows->map(fn ($o) => $this->payload($o)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);

        $office = DB::transaction(function () use ($data, $request) {
            $office = BillingOffice::create($data + ['created_by' => auth()->id()]);
            $this->syncAreas($office, $request);

            return $office;
        });

        activity()->causedBy(auth()->user())->performedOn($office)
            ->log("청구처 등록: {$office->displayName()}");

        return response()->json(['success' => true, 'row' => $this->payload($office->load('areas'))]);
    }

    public function update(Request $request, BillingOffice $billingOffice): JsonResponse
    {
        $data = $this->rules($request);

        DB::transaction(function () use ($billingOffice, $data, $request) {
            $billingOffice->update($data);
            $this->syncAreas($billingOffice, $request);
        });

        activity()->causedBy(auth()->user())->performedOn($billingOffice)
            ->log("청구처 수정: {$billingOffice->displayName()}");

        return response()->json(['success' => true, 'row' => $this->payload($billingOffice->load('areas'))]);
    }

    public function destroy(BillingOffice $billingOffice): JsonResponse
    {
        $name = $billingOffice->displayName();
        $billingOffice->areas()->delete();
        $billingOffice->delete();

        activity()->causedBy(auth()->user())->log("청구처 삭제: {$name}");

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────

    private function rules(Request $request): array
    {
        return $request->validate([
            'kind'         => 'required|in:nhis,local',
            'region'       => 'nullable|string|max:40',
            'office_name'  => 'required|string|max:100',
            'dept'         => 'nullable|string|max:100',
            'manager_name' => 'nullable|string|max:40',
            'title'        => 'nullable|string|max:40',
            'duty'         => 'nullable|string|max:200',
            'tel'          => 'nullable|string|max:40',
            'fax'          => 'nullable|string|max:40',
            'address'      => 'nullable|string|max:200',
            'note'         => 'nullable|string|max:200',
            'is_active'    => 'boolean',
        ]);
    }

    /**
     * 관할 읍ㆍ면ㆍ동을 다시 적는다.
     *
     * 화면에서는 「용강동, 신수동」처럼 쉼표나 줄바꿈으로 여러 개를 적는다.
     * 시도ㆍ시군구는 한 줄에 하나만 받는다 — 한 지사가 두 시군구에 걸치는 일은
     * 드물고, 그런 때는 줄을 나눠 등록하는 편이 헷갈리지 않는다.
     */
    private function syncAreas(BillingOffice $office, Request $request): void
    {
        $raw = (string) $request->input('areas', '');
        $sido    = trim((string) $request->input('area_sido'));
        $sigungu = trim((string) $request->input('area_sigungu'));

        $emds = collect(preg_split('/[,\n\r]+/u', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->unique()
            ->values();

        $office->areas()->delete();

        foreach ($emds as $emd) {
            BillingOfficeArea::create([
                'billing_office_id' => $office->id,
                'sido'              => $sido ?: null,
                'sigungu'           => $sigungu ?: null,
                'emd'               => mb_substr($emd, 0, 40),
            ]);
        }
    }

    private function payload(BillingOffice $o): array
    {
        return [
            'id'           => $o->id,
            'kind'         => $o->kind,
            'kind_label'   => $o->kindLabel(),
            'region'       => $o->region,
            'office_name'  => $o->office_name,
            'dept'         => $o->dept,
            'manager_name' => $o->manager_name,
            'title'        => $o->title,
            'duty'         => $o->duty,
            'tel'          => $o->tel,
            'fax'          => $o->fax,
            'address'      => $o->address,
            'note'         => $o->note,
            'is_active'    => (bool) $o->is_active,
            'display_name' => $o->displayName(),
            'area_sido'    => $o->areas->first()->sido ?? null,
            'area_sigungu' => $o->areas->first()->sigungu ?? null,
            'areas'        => $o->areas->pluck('emd')->all(),
            'areas_text'   => $o->areas->pluck('emd')->implode(', '),
        ];
    }
}
