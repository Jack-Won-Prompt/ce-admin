<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

/**
 * 이 기계를 스스로 잰다 (2026-10-02 지시).
 *
 * CloudWatch 를 기다리지 않는다. 기본 EC2 지표에는 **메모리와 디스크가 아예 없고**
 * (에이전트를 따로 세워야 한다), 운영 역할에는 CloudWatch 권한도 없다. 그런데 우리는
 * 그 기계 안에서 돌고 있으니 `/proc` 과 `df` 로 바로 읽으면 된다 — 더 빠르고 공짜다.
 *
 * 지난 값은 `sar` 에서 읽는다. `sysstat-collect.timer` 가 **10분마다** 모으고 있어
 * (2026-10-02 확인) cron 을 새로 걸 필요가 없다. 라라벨 스케줄러는 이 서버에 아직
 * 등록되어 있지 않다(SR #31).
 */
class ServerMetrics
{
    /** CPU 를 잴 때 두 번 읽는 사이 (마이크로초) */
    private const 재는틈 = 180000;

    /**
     * 지금 값.
     *
     * CPU 는 한 번 읽어서는 알 수 없다 — `/proc/stat` 은 **부팅 뒤 누적값**이라
     * 두 번 읽어 그 사이의 차이를 봐야 한다. 그래서 0.18초를 기다린다.
     */
    public function 지금(): array
    {
        return Cache::remember('mon:server', config('monitoring.cache.server', 10), function () {
            $메모리 = $this->메모리();
            $디스크 = $this->디스크();

            return [
                'cpu'     => $this->cpu(),
                'memory'  => $메모리,
                'disk'    => $디스크,
                'load'    => $this->부하(),
                'cores'   => $this->코어(),
                'uptime'  => $this->가동(),
                'at'      => now()->format('H:i:s'),
            ];
        });
    }

    /** 누적값을 두 번 읽어 그 사이의 바쁜 비율을 낸다 */
    private function cpu(): ?float
    {
        $첫 = $this->스탯();
        if (! $첫) {
            return null;
        }

        usleep(self::재는틈);
        $둘 = $this->스탯();
        if (! $둘) {
            return null;
        }

        $전체 = $둘['total'] - $첫['total'];
        $쉼   = $둘['idle'] - $첫['idle'];

        if ($전체 <= 0) {
            return null;
        }

        return round((1 - $쉼 / $전체) * 100, 1);
    }

    /**
     * `/proc/stat` 첫 줄을 읽는다.
     *
     *   cpu  user nice system idle iowait irq softirq steal guest guest_nice
     *
     * 쉰 것은 idle 과 iowait 를 함께 본다 — 디스크를 기다리는 동안도 일을 하지 않은
     * 것이다. steal(다른 손님에게 빼앗긴 몫)은 바쁜 쪽에 둔다. 우리 몫이 모자라서
     * 생기는 느림이라, 가려 두면 왜 느린지 알 수 없다.
     */
    private function 스탯(): ?array
    {
        $줄 = $this->첫줄('/proc/stat');
        if (! $줄 || ! preg_match('/^cpu\s+(.+)$/', $줄, $m)) {
            return null;
        }

        $값 = array_map('intval', preg_split('/\s+/', trim($m[1])));
        if (count($값) < 5) {
            return null;
        }

        return [
            'total' => array_sum($값),
            'idle'  => $값[3] + ($값[4] ?? 0),
        ];
    }

    /** `/proc/meminfo` — 쓰는 양은 「전체 − 쓸 수 있는 양」이다 */
    private function 메모리(): array
    {
        $글 = $this->읽기('/proc/meminfo');
        if (! $글) {
            return ['percent' => null, 'used_mb' => null, 'total_mb' => null];
        }

        $찾기 = function (string $이름) use ($글): int {
            return preg_match('/^' . $이름 . ':\s+(\d+) kB/m', $글, $m) ? (int) $m[1] : 0;
        };

        $전체 = $찾기('MemTotal');

        /* buff/cache 를 쓰는 양에 넣으면 늘 90% 로 보인다 — 커널이 남는 자리를 캐시로
           채워 두기 때문이다. MemAvailable 은 「새 일에 바로 내줄 수 있는 양」이라
           사람이 생각하는 여유와 맞는다. 없는 옛 커널에서만 free+cached 로 떨어진다. */
        $쓸수있음 = $찾기('MemAvailable') ?: ($찾기('MemFree') + $찾기('Cached') + $찾기('Buffers'));

        if ($전체 <= 0) {
            return ['percent' => null, 'used_mb' => null, 'total_mb' => null];
        }

        $쓰는양 = max(0, $전체 - $쓸수있음);

        return [
            'percent'  => round($쓰는양 / $전체 * 100, 1),
            'used_mb'  => (int) round($쓰는양 / 1024),
            'total_mb' => (int) round($전체 / 1024),
        ];
    }

    /** 뿌리 칸의 디스크 */
    private function 디스크(): array
    {
        $빈것 = ['percent' => null, 'used_gb' => null, 'total_gb' => null];

        $글 = $this->명령('df -kP / 2>/dev/null');
        if (! $글) {
            return $빈것;
        }

        $줄들 = preg_split('/\R/', trim($글));
        $끝 = trim((string) end($줄들));
        $칸 = preg_split('/\s+/', $끝);

        /* df 는 칸이 여섯이다 — 장치ㆍ전체ㆍ쓴 것ㆍ남은 것ㆍ비율ㆍ붙은 자리.
           장치 이름이 길면 줄을 접는 판도 있어 -P 로 한 줄에 오게 한다. */
        if (count($칸) < 6) {
            return $빈것;
        }

        $전체 = (int) $칸[1];
        $쓴것 = (int) $칸[2];

        if ($전체 <= 0) {
            return $빈것;
        }

        return [
            'percent'  => round($쓴것 / $전체 * 100, 1),
            'used_gb'  => round($쓴것 / 1048576, 1),
            'total_gb' => round($전체 / 1048576, 1),
        ];
    }

    /** 1ㆍ5ㆍ15분 부하 */
    private function 부하(): array
    {
        $줄 = $this->첫줄('/proc/loadavg');
        if (! $줄) {
            return [];
        }

        $칸 = preg_split('/\s+/', trim($줄));

        return [
            '1m'  => (float) ($칸[0] ?? 0),
            '5m'  => (float) ($칸[1] ?? 0),
            '15m' => (float) ($칸[2] ?? 0),
        ];
    }

    private function 코어(): int
    {
        $글 = $this->읽기('/proc/cpuinfo');

        return $글 ? max(1, preg_match_all('/^processor\s*:/m', $글)) : 1;
    }

    /** 몇째 날 몇 시간 돌고 있나 */
    private function 가동(): ?string
    {
        $줄 = $this->첫줄('/proc/uptime');
        if (! $줄) {
            return null;
        }

        $초 = (int) (float) explode(' ', trim($줄))[0];
        $날 = intdiv($초, 86400);
        $시 = intdiv($초 % 86400, 3600);
        $분 = intdiv($초 % 3600, 60);

        return $날 > 0 ? "{$날}일 {$시}시간" : ($시 > 0 ? "{$시}시간 {$분}분" : "{$분}분");
    }

    /**
     * 지난 CPUㆍ메모리 — `sar` 가 10분마다 모아 둔 것을 읽는다.
     *
     * 오늘 자리는 `sa{일}` 이다(`/var/log/sysstat/sa02`). 자정을 넘기면 새 파일이
     * 열리므로 어제 것까지 함께 읽어 이어 붙인다 — 아침에 열었을 때 그래프가
     * 몇 점만 있는 꼴을 막는다.
     *
     * @return array{points: array<int, array{t: string, cpu: ?float, mem: ?float}>, source: string}
     */
    public function 지난값(int $시간 = 12): array
    {
        return Cache::remember("mon:server:series:{$시간}", 300, function () use ($시간) {
            $cpu = [];
            $mem = [];

            /* 어제ㆍ오늘 두 날을 본다. sar 파일은 하루 한 장이다 */
            foreach ([now()->subDay(), now()] as $날) {
                $자리 = rtrim((string) config('monitoring.sar_dir'), '/') . '/sa' . $날->format('d');
                if (! is_readable($자리)) {
                    continue;
                }

                $cpu += $this->sarCpu($자리, $날);
                $mem += $this->sarMem($자리, $날);
            }

            ksort($cpu);
            ksort($mem);

            $부터 = now()->subHours($시간)->getTimestamp();
            $점 = [];

            foreach ($cpu as $때 => $값) {
                if ($때 < $부터) {
                    continue;
                }
                $점[] = [
                    't'   => date('H:i', $때),
                    'cpu' => $값,
                    'mem' => $mem[$때] ?? null,
                ];
            }

            return [
                'points' => $점,
                'source' => $점 ? '서버 사용률 기록 (10분 간격)' : '서버 사용률 기록을 읽지 못했습니다',
            ];
        });
    }

    /** `sar -u` — 쉰 비율(%idle)의 나머지가 바쁜 비율이다 */
    private function sarCpu(string $자리, \DateTimeInterface $날): array
    {
        $값 = [];

        foreach ($this->sar($자리, '-u') as [$때, $칸, $머리]) {
            /* 시각을 뗀 값 줄은 「all 1.11 0.01 0.40 0.03 0.25 98.20」 이다.
               코어마다 적는 판도 있어 묶음(all) 줄만 쓴다. */
            if (($칸[0] ?? '') !== 'all') {
                continue;
            }

            $쉼 = $this->칸값($칸, $머리, '%idle');
            if ($쉼 === null) {
                continue;
            }

            $값[$this->때를초로($때, $날)] = round(100 - $쉼, 1);
        }

        return $값;
    }

    /** `sar -r` — 쓰는 비율 칸(%memused)을 쓴다 */
    private function sarMem(string $자리, \DateTimeInterface $날): array
    {
        $값 = [];

        foreach ($this->sar($자리, '-r') as [$때, $칸, $머리]) {
            $v = $this->칸값($칸, $머리, '%memused');
            if ($v === null) {
                continue;
            }
            $값[$this->때를초로($때, $날)] = round($v, 1);
        }

        return $값;
    }

    /**
     * 머리글에서 칸 이름을 찾아 값 줄의 같은 자리를 읽는다.
     *
     * 자리를 숫자로 박아 두면 sysstat 판이 올라 칸이 하나 끼는 날 조용히 다른 값을
     * 읽는다(`%steal` 은 그렇게 끼어든 칸이다). 이름으로 찾는다.
     *
     * 머리글에는 맨 앞에 시각 칸이 하나 더 있고 값 줄에서는 그것을 떼어 냈으므로
     * 한 칸 당겨 본다.
     */
    private function 칸값(array $칸, array $머리, string $이름): ?float
    {
        $자리수 = array_search($이름, $머리, true);
        if ($자리수 === false) {
            return null;
        }

        $v = $칸[$자리수 - 1] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * `sar` 를 돌려 줄마다 [시각, 값칸들, 머리글칸들] 로 끊어 준다.
     *
     * 사람이 보려고 만든 글이라 머리글이 중간에 다시 끼고 마지막에 「Average:」 줄이
     * 붙는다. 둘 다 걸러 낸다. 「Linux …」 로 시작하는 첫 줄도 버린다.
     *
     * **머리글 줄도 시각으로 시작한다** — 그것으로는 값 줄과 가려지지 않는다.
     *
     *   00:33:45        CPU     %user     %nice   %system   %iowait    %steal     %idle
     *   00:33:46        all      0.00      0.00      0.00      0.00      0.00    100.00
     *
     * 가리는 잣대는 `%` 나 `kb` 로 시작하는 칸이 있는가다. 값 줄에는 그런 칸이 없다.
     */
    private function sar(string $자리, string $무엇): array
    {
        $글 = $this->명령('LC_ALL=C sar -f ' . escapeshellarg($자리) . ' ' . $무엇 . ' 2>/dev/null');
        if (! $글) {
            return [];
        }

        $나온것 = [];
        $머리 = [];

        foreach (preg_split('/\R/', $글) as $줄) {
            $줄 = trim($줄);
            if ($줄 === '' || str_starts_with($줄, 'Linux') || str_starts_with($줄, 'Average')) {
                continue;
            }

            $칸 = preg_split('/\s+/', $줄);

            /* 머리글인가 — `%idle`ㆍ`kbmemfree` 같은 칸이 있으면 머리글이다 */
            foreach ($칸 as $한칸) {
                if (str_starts_with($한칸, '%') || str_starts_with($한칸, 'kb')) {
                    $머리 = $칸;
                    continue 2;   // 줄을 도는 바깥 고리로 — 머리글은 값이 아니다
                }
            }

            if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $칸[0])) {
                continue;   // 시각으로 시작하지 않는 줄은 값 줄이 아니다
            }

            $때 = array_shift($칸);

            /* 12시간 꼴로 적는 판은 AM/PM 이 따라붙는다 — 시각에 붙여 둔다 */
            if (in_array(($칸[0] ?? ''), ['AM', 'PM'], true)) {
                $때 .= ' ' . array_shift($칸);
            }

            $나온것[] = [$때, $칸, $머리];
        }

        return $나온것;
    }

    private function 때를초로(string $때, \DateTimeInterface $날): int
    {
        return (int) strtotime($날->format('Y-m-d') . ' ' . $때);
    }

    /* ── 자리 읽기 — 못 읽어도 화면이 서야 한다 ───────────────────── */

    private function 읽기(string $자리): ?string
    {
        return is_readable($자리) ? (@file_get_contents($자리) ?: null) : null;
    }

    private function 첫줄(string $자리): ?string
    {
        $글 = $this->읽기($자리);

        return $글 === null ? null : (explode("\n", $글)[0] ?: null);
    }

    /**
     * 바깥 명령을 돌린다.
     *
     * `df` 와 `sar` 는 읽어 올 자리가 파일이 아니라 명령이다. 서버에서 끈 판도 있어
     * 쓸 수 있는지 먼저 본다 — 꺼져 있으면 화면이 그 칸만 비운다.
     */
    private function 명령(string $명령): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $꺼둔것 = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $꺼둔것, true)) {
            return null;
        }

        $글 = @shell_exec($명령);

        return is_string($글) && trim($글) !== '' ? $글 : null;
    }
}
