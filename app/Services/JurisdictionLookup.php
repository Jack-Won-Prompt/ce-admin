<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 주소로 관할을 묻는다 — 공단 지사와 지자체 행정복지센터.
 *
 * 우리 표(billing_offices)에 아직 없는 곳을 담당자가 손으로 찾아 적던 자리다. 공단
 * 지사찾기를 새 창으로 열어 눈으로 읽고 옮겨 적었는데, 옮기다 틀리면 엉뚱한 곳으로
 * 팩스가 갔다. 여기서 대신 물어 후보를 세워 준다.
 *
 * 두 곳에 묻는다.
 *
 *   공단 — 지사찾기 화면이 쓰는 길을 그대로 쓴다(retrieveBranchListAjax.do).
 *          읍ㆍ면ㆍ동이나 시ㆍ군ㆍ구를 주면 지역본부ㆍ지사명ㆍ우편번호ㆍ주소ㆍ지사코드가
 *          돌아온다. 공개된 API 가 아니라 화면이 쓰는 길이므로, 공단이 화면을 고치면
 *          끊긴다 — 끊겨도 화면은 멀쩡해야 한다. 그래서 실패는 빈 목록과 한 줄 설명으로
 *          돌려보내고, 담당자는 예전처럼 손으로 등록할 수 있다.
 *
 *          전화ㆍ팩스는 여기서 오지 않는다. 부서별 연락처 화면(wbhaff83500m01.do)이
 *          지사를 세션에 쥐고 있어, 지사코드를 실어 보내도 앞서 본 지사만 되돌려 준다 —
 *          재 보고 확인했다. 그 번호는 담당자가 한 번 적어 두면 우리 표에 남는다.
 *
 *   지자체 — 카카오 로컬. 주소를 좌표로 바꾸고(행정동까지), 그 자리 둘레에서
 *          「○○ 행정복지센터」를 찾는다. 이름ㆍ주소ㆍ전화번호가 함께 온다.
 *          REST 키가 있어야 한다(환경 설정 ▸ 카카오).
 *
 * 어느 쪽이든 서버가 밖으로 나가는 일이라 짧게 끊는다. 관할 하나 찾자고 화면이 오래
 * 멈춰 있으면 담당자는 그냥 새 창을 연다.
 */
class JurisdictionLookup
{
    /** 공단 지사찾기가 쓰는 길 */
    private const NHIS_URL = 'https://www.nhis.or.kr/nhis/about/retrieveBranchListAjax.do';

    /** 찾는 갈래 — 화면의 라디오와 같다 */
    private const SK_SIGUNGU = '1';
    private const SK_EMD     = '2';

    private const TIMEOUT = 6;

    /**
     * 공단 지사 — 읍ㆍ면ㆍ동으로 묻고, 여럿이면 시ㆍ군ㆍ구로 가린다.
     *
     * 읍면동 이름은 시군구가 달라도 겹친다(삼성동은 관악ㆍ강남동부ㆍ대전동부 셋으로 온다).
     * 주소에 시군구가 있으면 그것으로 가리고, 가려서 아무것도 남지 않으면 가리기 전 것을
     * 그대로 보여 준다 — 못 가렸다고 아무것도 안 보여 주면 담당자가 할 수 있는 일이 없다.
     *
     * @return array{rows: array<int, array<string, string>>, narrowed: bool, error: ?string}
     */
    public function nhisBranches(string $emd, string $sigungu = ''): array
    {
        $emd     = trim($emd);
        $sigungu = trim($sigungu);

        if ($emd === '' && $sigungu === '') {
            return ['rows' => [], 'narrowed' => false, 'error' => '읍ㆍ면ㆍ동도 시ㆍ군ㆍ구도 알 수 없습니다.'];
        }

        [$sk, $sv] = $emd !== '' ? [self::SK_EMD, $emd] : [self::SK_SIGUNGU, $sigungu];

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->asForm()
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->post(self::NHIS_URL, [
                    'CT' => '', 'LOC' => '', 'brchCd' => '', 'pstnType' => '',
                    'pageIndex' => '1', 'SK' => $sk, 'SV' => $sv,
                ]);

            if (!$res->successful()) {
                return ['rows' => [], 'narrowed' => false, 'error' => '공단 지사찾기가 응답하지 않았습니다.'];
            }

            $rows = $this->parseNhis($res->body());
        } catch (\Throwable $e) {
            /* 밖이 막혀도 이 화면은 멈추지 않는다 — 예전처럼 손으로 등록하면 된다 */
            Log::warning('[관할] 공단 지사찾기 실패: ' . $e->getMessage());

            return ['rows' => [], 'narrowed' => false, 'error' => '공단 지사찾기에 닿지 못했습니다.'];
        }

        $narrowed = false;
        if ($sigungu !== '' && count($rows) > 1) {
            $hit = array_values(array_filter($rows, fn ($r) => str_contains($r['address'], $sigungu)));
            if ($hit) {
                $rows     = $hit;
                $narrowed = true;
            }
        }

        return ['rows' => $rows, 'narrowed' => $narrowed, 'error' => null];
    }

    /**
     * 돌아온 조각에서 한 줄씩 집는다.
     *
     * 화면이 그리는 조각이라 태그가 바뀔 수 있다. 그래서 클래스 이름에 기대지 않고
     * 값의 생김새로 집는다 — 지사명은 <p> 안, 주소는 「[우.우편번호] …」, 지사코드는
     * 위치안내 단추가 부르는 함수의 첫 인자다.
     */
    private function parseNhis(string $html): array
    {
        if (!preg_match_all('#<li\b[^>]*>(.*?)</li>#us', $html, $m)) {
            return [];
        }

        $rows = [];
        foreach ($m[1] as $li) {
            if (!preg_match('#\[우\.(\d{5})\]\s*([^<]+)#u', $li, $addr)) continue;   // 주소가 없으면 지사 줄이 아니다

            preg_match('#<p>\s*([^<]+?)\s*</p>#u', $li, $name);
            preg_match('#fn_showJisaInfo\(\s*\'(\d+)\'#', $li, $code);
            preg_match('#class="flag[^"]*"[^>]*>\s*([^<]+?)\s*<#u', $li, $region);

            $officeName = trim($name[1] ?? '');
            if ($officeName === '') continue;

            $rows[] = [
                'office_name' => $officeName,
                'region'      => trim($region[1] ?? ''),
                'postcode'    => $addr[1],
                'address'     => trim(preg_replace('/\s+/u', ' ', $addr[2])),
                'code'        => $code[1] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * 지자체 — 그 주소를 맡는 행정복지센터.
     *
     * 주소를 좌표로 바꾸면 행정동이 함께 온다(법정동과 다르다 — 관할은 행정동이 가른다).
     * 그 이름으로 둘레를 찾아 이름ㆍ주소ㆍ전화번호를 얻는다.
     *
     * @return array{rows: array<int, array<string, string>>, hdong: ?string, error: ?string}
     */
    public function communityCenters(string $address): array
    {
        $address = trim($address);
        $key     = (string) config('services.kakao_local.rest_key', '');

        if ($key === '') {
            return ['rows' => [], 'hdong' => null,
                    'error' => '카카오 로컬 REST 키가 없습니다(환경 설정 ▸ 카카오).'];
        }
        if ($address === '') {
            return ['rows' => [], 'hdong' => null, 'error' => '주소가 비어 있습니다.'];
        }

        try {
            $http = Http::timeout(self::TIMEOUT)->withHeaders(['Authorization' => 'KakaoAK ' . $key]);

            // ① 주소 → 좌표
            $geo = $http->get('https://dapi.kakao.com/v2/local/search/address.json',
                              ['query' => $address, 'size' => 1]);
            $doc = $geo->json('documents.0');
            if (!$doc) {
                return ['rows' => [], 'hdong' => null, 'error' => '그 주소를 찾지 못했습니다.'];
            }

            $x = (string) ($doc['x'] ?? '');
            $y = (string) ($doc['y'] ?? '');

            // ② 좌표 → 행정동(H). 법정동(B)도 함께 오지만 관할은 행정동이 가른다.
            $reg = $http->get('https://dapi.kakao.com/v2/local/geo/coord2regioncode.json',
                              ['x' => $x, 'y' => $y]);
            $hdong = collect((array) $reg->json('documents', []))
                ->firstWhere('region_type', 'H');

            $emd     = trim((string) ($hdong['region_3depth_name'] ?? ''));
            $sigungu = trim((string) ($hdong['region_2depth_name'] ?? ''));

            // ③ 그 행정동의 센터를 둘레에서 찾는다
            $kw  = trim($sigungu . ' ' . $emd . ' 행정복지센터');
            $res = $http->get('https://dapi.kakao.com/v2/local/search/keyword.json', [
                'query'  => $kw,
                'x'      => $x,
                'y'      => $y,
                'radius' => 5000,
                'size'   => 5,
                'sort'   => 'distance',
            ]);

            $rows = collect((array) $res->json('documents', []))
                ->map(fn ($d) => [
                    'office_name' => (string) ($d['place_name'] ?? ''),
                    'address'     => (string) ($d['road_address_name'] ?: $d['address_name'] ?? ''),
                    'tel'         => (string) ($d['phone'] ?? ''),
                    'region'      => $sigungu,
                    'distance'    => (string) ($d['distance'] ?? ''),
                ])
                ->filter(fn ($r) => $r['office_name'] !== '')
                ->values()
                ->all();

            return ['rows' => $rows, 'hdong' => $emd ?: null, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[관할] 카카오 로컬 실패: ' . $e->getMessage());

            return ['rows' => [], 'hdong' => null, 'error' => '카카오 로컬에 닿지 못했습니다.'];
        }
    }
}
