<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * 응답이 얼마나 걸렸나를 분 단위로 모은다 (2026-10-02 지시).
 *
 * ## 왜 앱에서 재는가
 *
 * nginx 기록 꼴이 기본(`combined`)이라 `$request_time` 이 **적히지 않는다.** 감시
 * 화면의 「평균 응답」을 채우려면 둘 중 하나다.
 *
 *   ① nginx 기록 꼴을 고치고 nginx 를 다시 읽힌다 — 운영 설정을 건드린다
 *   ② 앱에서 잰다 — 설정을 건드리지 않는다
 *
 * ②를 골랐다. 운영 중에 nginx 설정을 바꾸는 것보다 가볍고, 화면 이름별로도 볼 수
 * 있다. 대신 nginx 가 받아 PHP 에 넘기기까지의 시간은 빠진다 — 우리가 쥔 구간만 잰다.
 *
 * ## 요청마다 쌓지 않는다
 *
 * 줄을 요청마다 표에 적으면 하루 2만 줄이 넘는다. 분마다 **셈과 합** 둘만 캐시에
 * 쥐고, 평균은 볼 때 나눈다. 두 시간이 지난 칸은 캐시가 스스로 버린다.
 *
 * 캐시가 파일이라 더하기가 끊어질 틈이 있다 — 같은 순간에 들어온 둘 중 하나를 잃을
 * 수 있다. 평균을 보는 데는 넉넉하고, 자리를 잠그느라 요청을 세우는 것보다 낫다.
 */
class ResponseTimeRecorder
{
    /** 모아 두는 동안 (초) — 화면이 보는 한 시간보다 넉넉하게 */
    private const 보관 = 7200;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * 응답을 내보낸 **뒤에** 적는다 — 적는 일이 사람을 기다리게 하지 않는다.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->잴것인가($request)) {
                return;
            }

            $걸린밀리 = (int) round((microtime(true) - $this->시작($request)) * 1000);

            if ($걸린밀리 < 0 || $걸린밀리 > 600000) {
                return;   // 터무니없는 값은 버린다 (시계가 어긋난 자리)
            }

            $열쇠 = 'mon:rt:' . now()->format('YmdHi');
            $칸 = Cache::get($열쇠);

            $칸 = [
                'n'   => (int) ($칸['n'] ?? 0) + 1,
                'sum' => (int) ($칸['sum'] ?? 0) + $걸린밀리,
                'max' => max((int) ($칸['max'] ?? 0), $걸린밀리),
                'e5'  => (int) ($칸['e5'] ?? 0) + ($response->getStatusCode() >= 500 ? 1 : 0),
            ];

            Cache::put($열쇠, $칸, self::보관);
        } catch (\Throwable) {
            /* 재는 일이 화면을 깨뜨려서는 안 된다. 조용히 물러난다 */
        }
    }

    /**
     * 라라벨이 들어온 때를 쥐고 있으면 그것을 쓴다.
     *
     * `LARAVEL_START` 는 `public/index.php` 가 맨 처음 적는 값이라 부트까지 포함한
     * 진짜 걸린 시간이 된다. 없으면(시험 등) 요청에 적힌 때로 떨어진다.
     */
    private function 시작(Request $request): float
    {
        if (defined('LARAVEL_START')) {
            return (float) LARAVEL_START;
        }

        return (float) ($request->server('REQUEST_TIME_FLOAT') ?: microtime(true));
    }

    /**
     * 그림ㆍ글꼴 같은 붙임은 세지 않는다.
     *
     * 그런 것이 섞이면 평균이 1ms 쪽으로 끌려가 화면이 늘 「빠르다」고 적는다.
     * 보고 싶은 것은 **사람이 기다리는 시간**이다.
     */
    private function 잴것인가(Request $request): bool
    {
        if ($request->isMethod('OPTIONS')) {
            return false;
        }

        return ! preg_match('~\.(js|css|png|jpe?g|gif|svg|ico|woff2?|ttf|map|webp)$~i', $request->path());
    }

    /**
     * 지난 한 시간의 분별 값 — 감시 화면이 읽는 자리.
     *
     * @return array{avg: ?int, max: ?int, count: int, e5: int, points: array<int, array{t: string, v: int}>}
     */
    public static function 모은것(int $분수 = 60): array
    {
        $셈 = 0;
        $합 = 0;
        $꼭 = 0;
        $오류 = 0;
        $점 = [];

        for ($i = $분수 - 1; $i >= 0; $i--) {
            $때 = now()->subMinutes($i);
            $칸 = Cache::get('mon:rt:' . $때->format('YmdHi'));

            $n = (int) ($칸['n'] ?? 0);
            $s = (int) ($칸['sum'] ?? 0);

            $셈  += $n;
            $합  += $s;
            $꼭  = max($꼭, (int) ($칸['max'] ?? 0));
            $오류 += (int) ($칸['e5'] ?? 0);

            $점[] = [
                't' => $때->format('H:i'),
                'v' => $n > 0 ? (int) round($s / $n) : 0,
            ];
        }

        return [
            'avg'    => $셈 > 0 ? (int) round($합 / $셈) : null,
            'max'    => $꼭 ?: null,
            'count'  => $셈,
            'e5'     => $오류,
            'points' => $점,
        ];
    }
}
