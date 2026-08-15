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

    /**
     * 주소에서 관할 지자체를 뽑는다.
     *
     * 요양비를 받는 곳은 자치단체다. 특별시·광역시 아래의 구는 자치구라 그 구가 받지만,
     * 도 아래 시의 구는 행정구라 자치권이 없어 시가 받는다(예: 경기도 성남시 분당구 → 성남시).
     * 세종은 아래에 시군구가 없어 세종시가 받는다.
     */
    public static function localGovFromAddress(?string $address): ?string
    {
        $addr = trim(preg_replace('/\s+/', ' ', (string) $address));
        if ($addr === '') {
            return null;
        }

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
