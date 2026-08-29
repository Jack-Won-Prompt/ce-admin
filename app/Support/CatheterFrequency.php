<?php

namespace App\Support;

/**
 * 일일 도뇨 횟수.
 *
 * 위드웍스에서 물려받은 값은 구간이었다 — 「1회 미만 · 1~2회 · 3~4회 · 5회 · 6회 이상」.
 * 구간으로는 여섯 번과 아홉 번을 가릴 수 없어, 재구매 주기를 셈할 때 늘 같은 값으로
 * 읽혔다. 앞으로는 횟수를 그대로 적는다(1~10 · 10회 이상).
 *
 * 옛 값은 고치지 않는다. 그 건의 담당자가 실제로 몇 번인지 다시 물어야 알 수 있는데,
 * 임의로 옮기면 없던 사실이 생긴다. 옛 값이 담긴 건에서는 그 값이 그대로 보이고
 * 그대로 저장된다 — 고칠 때만 새 값 가운데 고른다.
 */
class CatheterFrequency
{
    /** 위드웍스에서 물려받은 구간 값 — 새로 고르지는 않는다 */
    public const LEGACY = [
        '01' => '1회 미만',
        '02' => '1~2회',
        '03' => '3회 ~ 4회',
        '04' => '5회',
        '05' => '6회 이상',
        '06' => 'N/A',
    ];

    /** 앞으로 고르는 값 — 횟수 그대로 */
    public static function options(): array
    {
        $out = [];

        for ($n = 1; $n <= 10; $n++) {
            $out[(string) $n] = $n . '회';
        }

        $out['10+'] = '10회 이상';

        return $out;
    }

    public static function label(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::options()[$value] ?? self::LEGACY[$value] ?? $value;
    }

    /** 옛 구간 값인가 — 화면이 그 줄만 따로 보여 주려고 묻는다 */
    public static function isLegacy(?string $value): bool
    {
        return $value !== null && $value !== '' && isset(self::LEGACY[$value]);
    }
}
