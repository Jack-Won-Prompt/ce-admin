<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aurora 를 DB 쪽에서 직접 묻는다 (2026-10-02 지시).
 *
 * RDS 의 연결 수ㆍ용량은 CloudWatch 로도 볼 수 있지만, 운영 역할에 그 권한이 없고
 * (2026-10-02 확인) 애초에 **우리가 그 DB 에 붙어 있으니** 직접 물으면 된다.
 * CloudWatch 보다 늦지도 않다 — CloudWatch 는 1~5분 묵은 값을 준다.
 *
 * CPU 는 DB 안에서 알 수 없다. 그것만 CloudWatch 가 필요하다.
 */
class DatabaseMetrics
{
    /**
     * `SHOW GLOBAL STATUS` 는 **자리표시자를 받지 않는다.**
     *
     *   SHOW GLOBAL STATUS LIKE ?   →   SQLSTATE[42000] 1064
     *
     * 준비된 구문의 자리표시자는 값이 올 수 있는 곳에만 쓸 수 있고, LIKE 꼴은 그 자리가
     * 아니다. 그래서 이름을 글에 바로 넣는다 — 대신 **우리가 적은 이름만** 넣는다.
     * 글자ㆍ숫자ㆍ밑줄이 아닌 것이 섞이면 묻지 않는다.
     */
    private function 한칸(string $종류, string $이름): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $이름)) {
            return null;
        }

        try {
            $줄 = DB::select("SHOW GLOBAL {$종류} LIKE '{$이름}'");
        } catch (\Throwable) {
            return null;
        }

        return isset($줄[0]) ? (string) $줄[0]->Value : null;
    }

    private function 상태(string $이름): ?string
    {
        return $this->한칸('STATUS', $이름);
    }

    private function 설정(string $이름): ?string
    {
        return $this->한칸('VARIABLES', $이름);
    }

    public function 지금(): array
    {
        return Cache::remember('mon:db', config('monitoring.cache.db', 15), function () {
            $연결   = (int) $this->상태('Threads_connected');
            $최대   = (int) $this->설정('max_connections');
            $가동초 = (int) $this->상태('Uptime');

            $읽기요청 = (int) $this->상태('Innodb_buffer_pool_read_requests');
            $디스크   = (int) $this->상태('Innodb_buffer_pool_reads');

            return [
                'connections'     => $연결,
                'max_connections' => $최대 ?: null,
                'conn_percent'    => $최대 > 0 ? round($연결 / $최대 * 100, 1) : null,
                'running'         => (int) $this->상태('Threads_running'),
                'max_used'        => (int) $this->상태('Max_used_connections'),
                'slow_queries'    => (int) $this->상태('Slow_queries'),
                'questions'       => (int) $this->상태('Questions'),
                'aborted'         => (int) $this->상태('Aborted_connects'),
                'qps'             => $가동초 > 0
                    ? round((int) $this->상태('Questions') / $가동초, 1)
                    : null,
                'buffer_hit'      => $읽기요청 > 0
                    ? round((1 - $디스크 / $읽기요청) * 100, 2)
                    : null,
                'uptime_hours'    => $가동초 > 0 ? round($가동초 / 3600, 1) : null,
                'version'         => $this->판(),
                'aurora_version'  => $this->설정('aurora_version'),
                'read_only'       => $this->설정('innodb_read_only') === 'ON',
                'replica_lag_ms'  => $this->상태('Aurora_replica_lag_in_msec'),
                'size'            => $this->크기(),
                'host'            => $this->호스트(),
                'cluster'         => $this->클러스터(),
            ];
        });
    }

    private function 판(): ?string
    {
        try {
            return (string) (DB::select('SELECT VERSION() v')[0]->v ?? null) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 담긴 양.
     *
     * `information_schema.tables` 의 크기는 **어림이다** — InnoDB 는 쪽 단위로 자리를
     * 잡으므로 실제 파일 크기와 조금 다르다. 늘고 줄는 흐름을 보는 데는 넉넉하다.
     * RDS 의 남은 저장 공간(FreeStorageSpace)은 CloudWatch 만 안다.
     */
    private function 크기(): array
    {
        try {
            $줄 = DB::select('SELECT SUM(data_length + index_length) s, SUM(data_free) f, COUNT(*) t '
                           . 'FROM information_schema.tables WHERE table_schema = DATABASE()');
            $r = $줄[0] ?? null;

            return [
                'mb'     => $r ? round(((int) $r->s) / 1048576, 1) : null,
                'free_mb' => $r ? round(((int) $r->f) / 1048576, 1) : null,
                'tables' => $r ? (int) $r->t : null,
            ];
        } catch (\Throwable) {
            return ['mb' => null, 'free_mb' => null, 'tables' => null];
        }
    }

    private function 호스트(): ?string
    {
        return config('database.connections.' . config('database.default') . '.host') ?: null;
    }

    /**
     * Aurora 클러스터 이름 — CloudWatch 에 물을 때 꼬리표로 쓴다.
     *
     * `unicorn-prod-aurora.cluster-cfuiok8ocdok.ap-northeast-2.rds.amazonaws.com`
     * 의 맨 앞이 클러스터 이름이다.
     */
    private function 클러스터(): ?string
    {
        if ($박은것 = config('monitoring.db_cluster')) {
            return (string) $박은것;
        }

        $호스트 = (string) $this->호스트();

        return str_contains($호스트, '.rds.amazonaws.com')
            ? (explode('.', $호스트)[0] ?: null)
            : null;
    }
}
