<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

/**
 * 웹이 오늘 얼마나 오갔나 — nginx 기록에서 센다 (2026-10-02 지시).
 *
 * 로드밸런서가 없다. nginx 가 80ㆍ443 을 직접 받는다(2026-10-02 확인). 그래서
 * 「요청 수ㆍ5xxㆍ응답 시간」은 ALB 지표가 아니라 이 기록에서 나온다.
 *
 * ## 응답 시간은 여기서 나오지 않는다
 *
 * 기록 꼴이 nginx 기본(`combined`)이라 `$request_time` 이 **적히지 않는다.**
 * 칸은 열둘뿐이고 그중에 걸린 시간이 없다. 그래서 평균 응답은 우리가 앱에서 재어
 * 둔 것(`ResponseTimeRecorder`)을 쓴다 — nginx 설정을 건드리지 않아도 된다.
 */
class NginxMetrics
{
    public function 오늘(): array
    {
        return Cache::remember('mon:nginx', config('monitoring.cache.nginx', 60), function () {
            $자리 = (string) config('monitoring.nginx_log');

            if (! is_readable($자리)) {
                return $this->빈것('기록을 읽을 수 없습니다 — ' . $자리);
            }

            /* nginx 는 세계시로 적는다. 오늘이 어디까지인지도 그 눈으로 봐야 한다 */
            $오늘 = gmdate('d/M/Y');
            $어제 = gmdate('d/M/Y', time() - 86400);

            $셈    = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
            $모두  = 0;
            $시간별 = [];
            $오류줄 = [];
            $경로   = [];

            foreach ($this->끝에서읽기($자리) as $줄) {
                /* 「… [02/Oct/2026:04:33:18 +0000] "GET /path HTTP/1.1" 200 21 …」 */
                if (! preg_match('~\[(\d{2}/\w{3}/\d{4}):(\d{2}):\d{2}:\d{2} [^\]]*\] "(\w+) ([^ "]*)[^"]*" (\d{3}) ~', $줄, $m)) {
                    continue;
                }

                [, $날, $시, $방법, $길, $코드] = $m;

                if ($날 !== $오늘) {
                    /* 끝에서 거슬러 읽으므로 어제까지는 지나칠 수 있다. 그보다 앞이면
                       더 볼 것이 없다 — 다만 읽는 양을 이미 잘라 두었으니 그냥 넘긴다. */
                    continue;
                }

                $모두++;
                $갈래 = intdiv((int) $코드, 100) . 'xx';
                if (isset($셈[$갈래])) {
                    $셈[$갈래]++;
                }

                $시간별[$시] = ($시간별[$시] ?? 0) + 1;

                /* 우리 화면인 것만 센다 — 그림ㆍ파일 내려받기는 많아서 가린다 */
                if (! preg_match('~\.(js|css|png|jpe?g|gif|svg|ico|woff2?|map)$~i', $길)) {
                    $짧은길 = explode('?', $길)[0];
                    $경로[$짧은길] = ($경로[$짧은길] ?? 0) + 1;
                }

                if ((int) $코드 >= 500) {
                    $오류줄[] = [
                        'at'     => $this->한국시각($날, $줄),
                        'method' => $방법,
                        'path'   => mb_substr(explode('?', $길)[0], 0, 80),
                        'code'   => (int) $코드,
                    ];
                }
            }

            ksort($시간별);
            arsort($경로);

            return [
                'total'   => $모두,
                'by_class' => $셈,
                'hourly'  => $this->스물네칸($시간별),
                'top'     => array_slice($경로, 0, 8, true),
                'errors'  => array_slice(array_reverse($오류줄), 0, 10),
                'source'  => basename($자리) . ' · 오늘(UTC ' . $오늘 . ')',
                'note'    => null,
            ];
        });
    }

    /** 스물네 시간을 빠짐없이 채운다 — 비면 그래프가 들쭉날쭉해 보인다 */
    private function 스물네칸(array $시간별): array
    {
        $칸 = [];

        for ($i = 0; $i < 24; $i++) {
            $시 = sprintf('%02d', $i);
            /* 세계시로 센 것을 한국 시각 이름으로 갈아 적는다 (+9시간) */
            $칸[] = [
                't' => sprintf('%02d', ($i + 9) % 24),
                'n' => $시간별[$시] ?? 0,
            ];
        }

        /* 한국 시각 순으로 돌려 준다 — 00시가 맨 앞에 오게 */
        usort($칸, fn ($a, $b) => $a['t'] <=> $b['t']);

        return $칸;
    }

    private function 한국시각(string $날, string $줄): string
    {
        return preg_match('~\[\d{2}/\w{3}/\d{4}:(\d{2}:\d{2}:\d{2})~', $줄, $m)
            ? (string) \Illuminate\Support\Carbon::createFromFormat(
                'd/M/Y H:i:s', $날 . ' ' . $m[1], 'UTC')->setTimezone(config('app.timezone'))->format('H:i:s')
            : '-';
    }

    /**
     * 기록을 끝에서부터 정해진 양만 읽는다.
     *
     * 하루면 20MB 를 넘는다. 다 읽으면 화면 한 번에 그만큼을 메모리에 담는다 —
     * 처방전 화면이 68MB 로 벽을 맞은 것과 같은 꼴이 된다. 끝에서 자른다.
     *
     * 자른 첫 줄은 가운데가 잘려 있을 수 있어 버린다.
     *
     * @return \Generator<string>
     */
    private function 끝에서읽기(string $자리): \Generator
    {
        $최대 = max(1024 * 1024, (int) config('monitoring.nginx_bytes'));
        $손 = @fopen($자리, 'rb');

        if (! $손) {
            return;
        }

        try {
            $크기 = (int) @filesize($자리);

            if ($크기 > $최대) {
                fseek($손, $크기 - $최대);
                fgets($손);   // 잘린 줄은 버린다
            }

            while (($줄 = fgets($손)) !== false) {
                yield $줄;
            }
        } finally {
            fclose($손);
        }
    }

    private function 빈것(string $까닭): array
    {
        return [
            'total'   => null,
            'by_class' => ['2xx' => null, '3xx' => null, '4xx' => null, '5xx' => null],
            'hourly'  => [],
            'top'     => [],
            'errors'  => [],
            'source'  => null,
            'note'    => $까닭,
        ];
    }
}
