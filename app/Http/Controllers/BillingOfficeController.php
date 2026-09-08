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
     * 관할 찾기 — 읍ㆍ면ㆍ동으로 좁히고, 없으면 시군구로 본다.
     *
     * 읍면동 이름은 시군구가 달라도 겹친다(중동ㆍ신흥동…). 그래서 시군구를 함께 받으면
     * 그것으로 먼저 가리고, 그렇게 걸러 아무것도 없으면 읍면동만으로 다시 본다 —
     * 시군구를 못 뽑은 주소도 있기 때문이다(도로명 주소).
     *
     * **읍면동이 없어도 시군구만 있으면 찾는다.** 지자체(의료급여)는 시ㆍ군ㆍ구청 하나가
     * 그 안을 통째로 맡으므로 동까지 갈 것이 없다. 여태 읍면동이 없으면 그냥 물러났고,
     * 주소는 대개 도로명이라 그 자리가 가장 자주 걸렸다 — 우리 표에 있는데도 밖에
     * 물으러 나갔다. 시군구 전체를 맡는 줄(emd 가 빈 줄)을 먼저 본다.
     */
    public function lookup(Request $request): JsonResponse
    {
        $emd     = trim((string) $request->input('emd'));
        $sigungu = trim((string) $request->input('sigungu'));

        if ($emd === '' && $sigungu === '') {
            return response()->json(['success' => true, 'rows' => [], 'message' => '읍ㆍ면ㆍ동도 시ㆍ군ㆍ구도 알 수 없습니다.']);
        }

        $kind = $request->input('kind');

        /* **시도로도 가린다.**

           「중구」는 서울ㆍ부산ㆍ대구ㆍ인천ㆍ대전ㆍ울산에 다 있다. 시군구 이름만 보고
           찾으면 여섯 시도의 중구청이 나란히 서서, 담당자가 어느 줄이 이 환자의 것인지
           가릴 수 없다(2026-09-08 · 3차 5회 신우재).

           주소에서 편 시도 이름으로 좁힌다. 주소를 못 읽었거나 그 시도로 쌓아 둔 것이
           없으면 가리지 않는다 — 좁히다 아무것도 못 찾는 것보다 낫다. */
        $sido = \App\Support\ClaimAgency::sidoFromAddress((string) $request->input('address'));

        $시도로 = function ($q) use ($sido) {
            if ($sido !== '') {
                $q->where(fn ($w) => $w->where('sido', $sido)->orWhereNull('sido'));
            }

            return $q;
        };

        /* **읍면동을 모르면 그 시군구의 줄을 모두 본다.**

           도로명 주소에는 읍면동이 없어 시군구만 아는 일이 흔하다. 여태 그때
           「그 구 전체를 맡는 줄」만 보았는데, 그 꼴로 쌓인 것은 지자체뿐이다 —
           공단 지사는 관할 읍면동을 적어 두고 쓰므로 한 줄도 걸리지 않아,
           이미 등록해 둔 지사가 「쌓아 둔 청구처가 없습니다」로 나왔다
           (2026-09-08 · 3차 5회 신우재).

           구 전체를 맡는 줄이 앞에 서고, 그 뒤에 그 구의 읍면동 줄이 선다. */
        $시군구전체 = fn () => BillingOffice::with('areas')->active()->kind($kind)
            ->whereHas('areas', fn ($a) => $시도로($a->where('sigungu', $sigungu)))
            ->orderByRaw('(SELECT MIN(CASE WHEN emd IS NULL THEN 0 ELSE 1 END)
                             FROM billing_office_areas
                            WHERE billing_office_id = billing_offices.id
                              AND sigungu = ?)', [$sigungu])
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($emd === '') {
            $rows = $시군구전체();

            return response()->json([
                'success'  => true,
                'emd'      => null,
                'sigungu'  => $sigungu,
                'sido'     => $sido ?: null,
                'narrowed' => true,
                'wide'     => true,
                'rows'     => $rows->map(fn ($o) => $this->payload($o)),
            ]);
        }

        $base = fn () => BillingOffice::with('areas')->active()
            ->kind($kind)
            ->whereHas('areas', fn ($a) => $시도로($a->where('emd', $emd)));

        $rows = $sigungu !== ''
            ? $base()->whereHas('areas', fn ($a) => $시도로($a->where('emd', $emd)->where('sigungu', $sigungu)))->get()
            : collect();

        $narrowed = $rows->isNotEmpty();
        if (!$narrowed) {
            $rows = $base()->get();
        }

        /* 동으로 못 찾았지만 시군구는 안다 — 그 구 전체를 맡는 곳이 있으면 그것이 답이다.
           동을 하나하나 쌓아 두지 않아도 되게 하는 자리다. */
        $wide = false;
        if ($rows->isEmpty() && $sigungu !== '') {
            $rows = $시군구전체();
            $wide = $rows->isNotEmpty();
        }

        return response()->json([
            'success'  => true,
            'emd'      => $emd,
            'sigungu'  => $sigungu ?: null,
            'sido'     => $sido ?: null,
            'narrowed' => $narrowed || $wide,
            'wide'     => $wide,
            'rows'     => $rows->map(fn ($o) => $this->payload($o)),
        ]);
    }

    /**
     * 밖에 물어 후보를 세운다 — 우리 표에 아직 없는 곳.
     *
     * 지금까지는 담당자가 공단 지사찾기를 새 창으로 열어 눈으로 읽고 옮겨 적었다.
     * 옮기다 틀리면 엉뚱한 곳으로 팩스가 갔다. 여기서 대신 묻고, 고른 것을 그대로
     * 등록 칸에 앉힌다 — 사람은 확인하고 누르기만 한다.
     *
     * 밖이 막히거나 늦어도 이 화면은 멈추지 않는다. 후보가 비면 예전처럼 손으로
     * 적으면 된다 — 그 길을 걷어 내지 않았다.
     */
    public function resolve(Request $request, \App\Services\JurisdictionLookup $lookup): JsonResponse
    {
        $emd     = trim((string) $request->input('emd'));
        $sigungu = trim((string) $request->input('sigungu'));
        $address = trim((string) $request->input('address'));

        $nhis  = $lookup->nhisBranches($emd, $sigungu);
        $local = $lookup->communityCenters($address);

        return response()->json([
            'success' => true,
            'nhis'    => $nhis,
            'local'   => $local,
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
     * 관할을 다시 적는다.
     *
     * 화면에서는 「용강동, 신수동」처럼 쉼표나 줄바꿈으로 여러 개를 적는다.
     * 시도ㆍ시군구는 한 줄에 하나만 받는다 — 한 지사가 두 시군구에 걸치는 일은
     * 드물고, 그런 때는 줄을 나눠 등록하는 편이 헷갈리지 않는다.
     *
     * **읍ㆍ면ㆍ동을 비우면 「그 시군구 전체」다.** 공단은 한 지사가 여러 동을 나눠
     * 맡아 동으로 가려야 하지만, 지자체(의료급여)는 시ㆍ군ㆍ구청 하나가 그 안을 통째로
     * 맡는다. 그것을 적을 길이 없어 동을 스무 개 넘게 적어 두지 않으면 찾히지 않았다.
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

        /* 읍ㆍ면ㆍ동을 하나도 적지 않았는데 시군구는 적었다 — 그 시군구 전체라는 뜻이다.
           한 줄만 세운다(emd 는 비운다). 시군구조차 없으면 관할이 없는 것이니 두지 않는다. */
        if ($emds->isEmpty()) {
            if ($sigungu !== '') {
                BillingOfficeArea::create([
                    'billing_office_id' => $office->id,
                    'sido'              => $sido ?: null,
                    'sigungu'           => $sigungu,
                    'emd'               => null,
                ]);
            }

            return;
        }

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
            /* 읍면동을 비운 줄은 「그 시군구 전체」다. 목록에 빈칸으로 두면 관할이
               없는 것처럼 보이므로 그렇다고 적는다. 창을 다시 열 때도 그 빈 줄이
               글 칸으로 돌아가면 안 되니 areas 에서는 뺀다. */
            'areas'        => $o->areas->pluck('emd')->filter()->values()->all(),
            'areas_text'   => $o->areas->pluck('emd')->filter()->isEmpty()
                                ? ($o->areas->first()?->sigungu ? '— ' . $o->areas->first()->sigungu . ' 전체' : '')
                                : $o->areas->pluck('emd')->filter()->implode(', '),
        ];
    }
}
