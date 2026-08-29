<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 영업일 셈.
 *
 * 「입고일로부터 2영업일 이내」는 입고한 날을 빼고 그다음 영업일부터 둘을 센다는 뜻이다.
 * 월요일에 들어오면 수요일까지, 목요일에 들어오면 그다음 월요일까지다.
 *
 * 토·일과 설정에 적어 둔 공휴일을 건너뛴다. 공휴일 목록은 해마다 바뀌므로 설정 화면에서
 * 고친다 — 코드에 박으면 해가 바뀔 때마다 배포해야 한다.
 */
class BusinessDays
{
    /** 쉬는 날 목록 (Y-m-d). 요청 한 번에 여러 건을 재므로 한 번만 만든다. */
    private static ?array $holidays = null;

    /** @return string[] */
    public static function holidays(): array
    {
        if (self::$holidays !== null) {
            return self::$holidays;
        }

        $raw = (string) config('returns.holidays', '');

        return self::$holidays = collect(preg_split('/[,\s]+/', $raw))
            ->map(fn ($d) => trim($d))
            ->filter(fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            ->unique()
            ->values()
            ->all();
    }

    /** 설정을 바꿔 저장한 뒤 다시 읽게 한다 */
    public static function forget(): void
    {
        self::$holidays = null;
    }

    public static function isBusinessDay(CarbonInterface $day): bool
    {
        if ($day->isWeekend()) {
            return false;
        }

        return !in_array($day->format('Y-m-d'), self::holidays(), true);
    }

    /**
     * 기준일로부터 영업일 $days 뒤. 기준일 자체는 세지 않는다.
     *
     * 시각은 그날의 끝으로 맞춘다 — 「2영업일 이내」는 그날 안에만 끝내면 되는 것이지
     * 같은 시각까지가 아니다.
     */
    public static function add(CarbonInterface $from, int $days): CarbonImmutable
    {
        $day = CarbonImmutable::parse($from)->startOfDay();

        for ($left = max(0, $days); $left > 0;) {
            $day = $day->addDay();
            if (self::isBusinessDay($day)) {
                $left--;
            }
        }

        return $day->endOfDay();
    }

    /**
     * 기한까지 남은 영업일. 이미 지났으면 음수다.
     *
     * 「하루 남음」과 「이틀 지남」을 같은 잣대로 보여 주려고 하나로 만든다.
     */
    public static function until(CarbonInterface $due, ?CarbonInterface $now = null): int
    {
        $now  = CarbonImmutable::parse($now ?? now())->startOfDay();
        $due  = CarbonImmutable::parse($due)->startOfDay();
        $sign = $due->lessThan($now) ? -1 : 1;

        [$a, $b] = $sign === 1 ? [$now, $due] : [$due, $now];

        $count = 0;
        for ($day = $a; $day->lessThan($b);) {
            $day = $day->addDay();
            if (self::isBusinessDay($day)) {
                $count++;
            }
        }

        return $sign * $count;
    }
}
