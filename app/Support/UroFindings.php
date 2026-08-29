<?php

namespace App\Support;

/**
 * 요류역학검사 확인사항 — 등록 신청서(별지 제4호서식)가 요구하는 소견.
 *
 * 자가도뇨 소모성 재료가 급여 대상이 되려면 요류역학검사에서 다섯 가지 소견 가운데
 * 하나 이상이 나오거나, 비뇨기계통의 선천기형으로 검사 자체가 불가해야 한다.
 * 지금까지는 검사일만 적었고 「무엇이 확인됐는지」는 어디에도 남지 않아, 공단에서
 * 되물으면 처방전을 다시 꺼내 읽어야 했다.
 *
 * ⚠ 검사는 등록 신청서 발행일 기준 3년 이내 것만 유효하다(서식의 단서).
 */
class UroFindings
{
    /** 다섯 소견 — 하나 이상이면 된다 */
    public const RESULTS = [
        'areflexic'    => '무반사방광 (Areflexic bladder)',
        'underactive'  => '배뇨근 저활동성 (Detrusor underactivity)',
        'dysfunction'  => '기능이상성 배뇨 (Dysfunctional voiding)',
        'dyssynergia'  => '배뇨근-외조임근 협동장애 (Detrusor external-sphincter dyssynergia)',
        'hyperreflexia'=> '배뇨근 과활동성 및 수축력 저하 (Detrusor hyper-reflexia and impaired contractility)',
    ];

    /** 검사 자체를 못 하는 경우 — 의사소견서를 따로 낸다 */
    public const UNABLE = 'unable';
    public const UNABLE_LABEL = '비뇨기계통의 선천기형으로 요류역학검사 불가 (의사소견서 제출)';

    public static function all(): array
    {
        return self::RESULTS + [self::UNABLE => self::UNABLE_LABEL];
    }

    /** 담긴 값(쉼표로 이은 열쇠)을 배열로 */
    public static function parse(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($k) => isset(self::all()[$k])
        ));
    }

    /** 사람이 읽는 말로 */
    public static function labels(?string $value): array
    {
        return array_map(fn ($k) => self::all()[$k], self::parse($value));
    }

    /**
     * 검사가 아직 살아 있는가 — 발행일 기준 3년.
     *
     * 지난 검사로 신청서를 내면 공단이 되돌려 보낸다. 그것을 미리 알려 준다.
     */
    public static function expired(?string $uroDate, ?string $issuedAt = null): bool
    {
        if (!$uroDate) {
            return false;
        }

        $base = $issuedAt ? \Carbon\Carbon::parse($issuedAt) : now();

        return \Carbon\Carbon::parse($uroDate)->lt($base->copy()->subYears(3));
    }
}
