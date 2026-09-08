<?php

namespace App\Support;

/**
 * 청구처 판정.
 *
 * 요양비를 어디에 청구하느냐가 이후 절차를 통째로 가른다. 공단은 위임 등록을 먼저 하고
 * 사이트에 입력·업로드하지만, 지자체는 위임 절차가 아예 없고 서류도 다르며 등기로 보낸다.
 * 그래서 처방전 단계에서 갈라 둬야 청구 단계에서 헤매지 않는다.
 *
 * 판정 근거는 두 가지다 — 청구처는 급여구분에서, 관할 지자체는 환자 주소지에서 나온다.
 * 다만 어느 쪽도 확정하지 않고 기본값으로만 내놓는다. 틀리면 엉뚱한 곳에 청구가 가므로
 * 마지막 판단은 담당자가 한다.
 */
final class ClaimAgency
{
    public const NHIS  = 'nhis';   // 건강보험공단
    public const LOCAL = 'local';  // 지자체(시군구청)
    public const NONE  = 'none';   // 요양비 청구 대상이 아님

    public const LABELS = [
        self::NHIS  => '건강보험공단',
        self::LOCAL => '지자체(시군구청)',
        self::NONE  => '해당 없음',
    ];

    /**
     * 급여구분으로 청구처를 짐작한다.
     *
     * 기초(의료급여)는 지자체가 부담하고, 일반·차상위경감은 공단이 부담한다.
     * 자동차보험·산재는 보험사·근로복지공단 소관이라 요양비 청구 자체가 없다.
     */
    public static function fromBenefitClass(?string $benefitClass): ?string
    {
        return match (trim((string) $benefitClass)) {
            '기초'                => self::LOCAL,
            '일반', '차상위경감'   => self::NHIS,
            '자동차보험', '산재'   => self::NONE,
            default               => null,
        };
    }

    /** 줄여 쓴 시도명 → 온전한 이름 (카카오 도로명주소가 「서울 중구 …」로 준다) */
    private const 시도줄임 = [
        '서울' => '서울특별시',   '부산' => '부산광역시',   '대구' => '대구광역시',
        '인천' => '인천광역시',   '광주' => '광주광역시',   '대전' => '대전광역시',
        '울산' => '울산광역시',   '세종' => '세종특별자치시',
        '경기' => '경기도',       '강원' => '강원특별자치도', '충북' => '충청북도',
        '충남' => '충청남도',     '전북' => '전북특별자치도', '전남' => '전라남도',
        '경북' => '경상북도',     '경남' => '경상남도',     '제주' => '제주특별자치도',
    ];

    /**
     * 첫 마디가 줄여 쓴 시도명이면 온전한 이름으로 편다.
     *
     * 주소 검색(카카오)이 「서울 중구 세종대로 110」처럼 시도를 줄여 준다. 아래 규칙은
     * 「서울특별시」같은 온전한 이름만 받으므로, 펴 주지 않으면 관할 지자체가 빈 채로
     * 남는다 — 기초(의료급여)는 지자체에 등기로 청구하므로 그러면 어디로 부칠지 모른다.
     */
    public static function 시도를편다(string $addr): string
    {
        $머리 = explode(' ', $addr, 2);
        if (count($머리) === 2 && isset(self::시도줄임[$머리[0]])) {
            return self::시도줄임[$머리[0]] . ' ' . $머리[1];
        }

        return $addr;
    }

    /**
     * 주소에서 관할 지자체를 뽑는다.
     *
     * 요양비를 받는 곳은 자치단체다. 특별시·광역시 아래의 구는 자치구라 그 구가 받지만,
     * 도 아래 시의 구는 행정구라 자치권이 없어 시가 받는다(예: 경기도 성남시 분당구 → 성남시).
     * 세종은 아래에 시군구가 없어 세종시가 받는다.
     */
    /** 주소 앞머리의 시도 이름 — 온전한 이름으로 편다. 못 읽으면 빈 글. */
    public static function sidoFromAddress(?string $address): string
    {
        $addr = trim((string) $address);
        if ($addr === '') {
            return '';
        }

        $addr = self::시도를편다($addr);

        return preg_match('/^(\S+?(?:특별자치시|특별자치도|특별시|광역시|도))(?:\s|$)/u', $addr, $m)
            ? $m[1] : '';
    }

    public static function localGovFromAddress(?string $address): ?string
    {
        $addr = trim(preg_replace('/\s+/', ' ', (string) $address));
        if ($addr === '') {
            return null;
        }

        $addr = self::시도를편다($addr);

        // 시도 — 없으면 주소가 아니거나 우리가 다룰 수 있는 모양이 아니다
        if (!preg_match('/^(\S+?(?:특별자치시|특별자치도|특별시|광역시|도))\s*(.*)$/u', $addr, $m)) {
            return null;
        }

        [$sido, $rest] = [$m[1], $m[2]];

        // 세종특별자치시는 아래에 시군구가 없다
        if (str_contains($sido, '특별자치시')) {
            return $sido;
        }

        $isMetro = str_ends_with($sido, '특별시') || str_ends_with($sido, '광역시');

        if ($isMetro) {
            // 특별시·광역시 → 자치구(또는 광역시의 군)
            return preg_match('/^(\S+?[구군])(?:\s|$)/u', $rest, $g)
                ? $sido . ' ' . $g[1]
                : null;
        }

        // 도 → 시·군이 자치단체. 그 아래 구가 붙어 있어도 시까지만 본다.
        return preg_match('/^(\S+?[시군])(?:\s|$)/u', $rest, $g)
            ? $sido . ' ' . $g[1]
            : null;
    }
}
