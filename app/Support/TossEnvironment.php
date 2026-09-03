<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * 토스페이먼츠를 시험으로 돌 것인가 운영으로 돌 것인가(2026-09-03 지시).
 *
 * 여태 키 한 벌만 담아 두고, 바꿀 때 `.env` 의 값을 갈아 끼웠다. 그러면 두 가지가
 * 잘못되기 쉽다 — 운영 키를 넣고 「테스트 모드」를 끄는 것을 잊거나, 시험이 끝난 뒤
 * 운영 키를 되돌리지 않는다. 실제로 운영 키는 `.env` 주석으로만 남아 있었다.
 *
 * 두 벌을 다 담아 두고 한 칸으로 고른다. 고르는 일은 여기서만 한다 — 키를 부르는
 * 자리가 스무 곳쯤 되는데 그 자리마다 갈래를 따지게 하면 한 곳만 빠뜨려도
 * 운영 키로 시험이 나간다.
 */
final class TossEnvironment
{
    public const TEST = 'test';
    public const LIVE = 'live';

    public const LABELS = [
        self::TEST => '테스트',
        self::LIVE => '운영',
    ];

    /** 지금 고른 갈래 */
    public static function current(): string
    {
        return config('toss.env') === self::LIVE ? self::LIVE : self::TEST;
    }

    public static function isLive(): bool
    {
        return self::current() === self::LIVE;
    }

    public static function label(): string
    {
        return self::LABELS[self::current()];
    }

    /**
     * 고른 갈래의 키를 쓰이는 자리(toss.client_key · toss.secret_key)에 앉힌다.
     *
     * 부팅할 때 설정을 읽어 온 바로 뒤에 한 번 부른다.
     *
     * 고른 갈래의 키가 비어 있으면 건드리지 않는다 — `.env` 에 적힌 예전 값이
     * 그대로 쓰인다. 아직 두 벌을 담기 전인 서버가 갑자기 키를 잃고 멈추면 안 된다.
     */
    public static function apply(): void
    {
        $env = self::current();

        $client = trim((string) config("toss.{$env}.client_key"));
        $secret = trim((string) config("toss.{$env}.secret_key"));

        if ($client !== '') {
            config(['toss.client_key' => $client]);
        }
        if ($secret !== '') {
            config(['toss.secret_key' => $secret]);
        }

        /* 테스트 모드는 이제 갈래가 정한다. 예전에는 따로 켜고 껐는데, 운영 키를
           넣고 이것을 끄는 것을 잊는 일이 있었다. */
        config(['toss.test_mode' => $env === self::TEST]);

        /* 골라 둔 갈래인데 키가 없으면 소리 없이 다른 키로 돈다 — 알려 둔다 */
        if ($client === '' || $secret === '') {
            Log::warning('[토스] 고른 갈래의 키가 비어 있어 예전 설정을 그대로 쓴다', [
                'env'    => $env,
                'client' => $client !== '',
                'secret' => $secret !== '',
            ]);
        }
    }

    /**
     * 지금 쓰이는 키가 고른 갈래와 맞는가 — 설정 화면이 알린다.
     *
     * 운영을 골랐는데 test_ 키가 돌고 있으면 결제가 되는 것처럼 보이지만 돈은
     * 오가지 않는다. 그 반대는 더 나쁘다.
     */
    public static function mismatch(): ?string
    {
        $key = (string) config('toss.secret_key');
        if ($key === '') {
            return '시크릿 키가 비어 있습니다.';
        }

        $isTestKey = str_starts_with($key, 'test_');

        if (self::isLive() && $isTestKey) {
            return '「운영」을 골랐는데 테스트 키(test_)가 쓰이고 있습니다 — 운영 키를 넣어 주십시오.';
        }
        if (! self::isLive() && ! $isTestKey) {
            return '「테스트」를 골랐는데 운영 키(live_)가 쓰이고 있습니다 — 실제 결제가 일어납니다.';
        }

        return null;
    }
}
