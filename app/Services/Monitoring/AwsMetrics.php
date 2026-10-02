<?php

namespace App\Services\Monitoring;

use Aws\CloudWatch\CloudWatchClient;
use Aws\CostExplorer\CostExplorerClient;
use Aws\Exception\AwsException;
use Aws\Sts\StsClient;
use Illuminate\Support\Facades\Cache;

/**
 * AWS 에 묻는다 — 되는 것만 담고, 안 되는 것은 **왜 안 되는지 그대로 알린다**
 * (2026-10-02 지시).
 *
 * ## 지금 운영은 권한이 없다
 *
 * 기계에 붙은 역할은 `unicorn-ec2-ssm-role` 이고(STS 로 확인) 여기에는 CloudWatch
 * 권한이 **없다** — `cloudwatch:ListMetrics` 가 AccessDenied 로 돌아온다.
 * 그래서 이 반은 지금 거의 다 「권한 없음」을 돌려준다.
 *
 * 빈 칸에 그럴듯한 숫자를 넣지 않는다. 감시 화면이 거짓을 보이면 아예 없는 것보다
 * 나쁘다 — 사람이 그것을 믿고 판단한다. 대신 **어느 권한이 모자란지** 화면에 적어
 * 그것만 붙이면 곧 채워지게 한다.
 *
 * ## 과금은 부를 때마다 돈이 든다
 *
 * Cost Explorer 는 **호출 한 번에 0.01달러**다. 여섯 시간에 한 번만 묻는다
 * (`config('monitoring.cache.cost')`). 화면을 백 번 열어도 네 번만 나간다.
 */
class AwsMetrics
{
    private string $지역;

    public function __construct()
    {
        $this->지역 = (string) config('monitoring.region', 'ap-northeast-2');
    }

    /* ── 우리는 누구인가 ─────────────────────────────────────────── */

    /**
     * STS 로 신분을 묻는다. 이것은 권한이 따로 필요 없어 늘 된다 —
     * 자격이 아예 없을 때만 막힌다.
     */
    public function 신분(): array
    {
        return Cache::remember('mon:aws:who', 3600, function () {
            try {
                $나 = (new StsClient($this->차림()))->getCallerIdentity();
                $arn = (string) $나['Arn'];

                return [
                    'ok'      => true,
                    'account' => (string) $나['Account'],
                    'arn'     => $arn,
                    'role'    => $this->역할이름($arn),
                    'instance' => $this->기계($arn),
                    'region'  => $this->지역,
                ];
            } catch (\Throwable $e) {
                return ['ok' => false, 'region' => $this->지역] + $this->탈($e, 'sts:GetCallerIdentity');
            }
        });
    }

    /** `arn:aws:sts::…:assumed-role/unicorn-ec2-ssm-role/i-0229…` → 역할 이름 */
    private function 역할이름(string $arn): ?string
    {
        return preg_match('~assumed-role/([^/]+)/~', $arn, $m) ? $m[1] : null;
    }

    /** 같은 ARN 의 뒤쪽이 이 기계의 아이디다 — IMDS 를 따로 묻지 않아도 된다 */
    private function 기계(string $arn): ?string
    {
        if ($박은것 = config('monitoring.instance_id')) {
            return (string) $박은것;
        }

        return preg_match('~/(i-[0-9a-f]+)$~', $arn, $m) ? $m[1] : null;
    }

    public function 기계아이디(): ?string
    {
        $신분 = $this->신분();

        return $신분['instance'] ?? null;
    }

    /* ── CloudWatch ─────────────────────────────────────────────── */

    /**
     * 화면이 쓰는 지표를 **한 번에** 끌어 온다.
     *
     * `GetMetricData` 는 여러 질의를 한 요청에 담는다. 하나하나 `GetMetricStatistics`
     * 로 부르면 호출이 지표 수만큼 늘어난다 — 느리고, 요금도 호출 수로 센다.
     *
     * @return array{ok: bool, series: array, latest: array, error?: string, action?: string}
     */
    public function 지표(int $시간 = 12): array
    {
        return Cache::remember("mon:aws:metrics:{$시간}", config('monitoring.cache.aws', 300), function () use ($시간) {
            $질의 = $this->질의들();

            if (! $질의) {
                return ['ok' => false, 'series' => [], 'latest' => [],
                        'error' => '볼 자원을 찾지 못했습니다', 'action' => null];
            }

            try {
                $답 = (new CloudWatchClient($this->차림()))->getMetricData([
                    'StartTime'         => new \DateTimeImmutable("-{$시간} hours"),
                    'EndTime'           => new \DateTimeImmutable('now'),
                    'ScanBy'            => 'TimestampAscending',
                    'MetricDataQueries' => array_values($질의),
                ]);
            } catch (\Throwable $e) {
                return ['ok' => false, 'series' => [], 'latest' => []]
                     + $this->탈($e, 'cloudwatch:GetMetricData');
            }

            $줄기 = [];
            $끝값 = [];

            foreach ($답['MetricDataResults'] ?? [] as $r) {
                $id  = (string) $r['Id'];
                $값들 = $r['Values'] ?? [];
                $때들 = $r['Timestamps'] ?? [];

                $점 = [];
                foreach ($값들 as $i => $v) {
                    $점[] = [
                        't' => isset($때들[$i]) ? $때들[$i]->setTimezone(
                            new \DateTimeZone(config('app.timezone')))->format('H:i') : '',
                        'v' => round((float) $v, 2),
                    ];
                }

                $줄기[$id] = $점;
                $끝값[$id] = $점 ? end($점)['v'] : null;
            }

            return ['ok' => true, 'series' => $줄기, 'latest' => $끝값];
        });
    }

    /**
     * 무엇을 물을지 세운다.
     *
     * 자원이 없으면 그 질의를 넣지 않는다 — 없는 꼬리표로 물으면 빈 줄이 와서
     * 「0 이구나」로 잘못 읽힌다. 로드밸런서는 운영에 없으므로 설정에 적어 줄 때만 묻는다.
     */
    private function 질의들(): array
    {
        $질의 = [];

        if ($기계 = $this->기계아이디()) {
            $질의['ec2_cpu'] = $this->한질의('ec2_cpu', 'AWS/EC2', 'CPUUtilization',
                [['Name' => 'InstanceId', 'Value' => $기계]], 'Average');
            $질의['ec2_net_in'] = $this->한질의('ec2_net_in', 'AWS/EC2', 'NetworkIn',
                [['Name' => 'InstanceId', 'Value' => $기계]], 'Sum');
        }

        if ($묶음 = app(DatabaseMetrics::class)->지금()['cluster'] ?? null) {
            foreach ([
                'rds_cpu'     => ['CPUUtilization', 'Average'],
                'rds_conn'    => ['DatabaseConnections', 'Average'],
                'rds_storage' => ['FreeLocalStorage', 'Average'],
            ] as $id => [$이름, $셈]) {
                $질의[$id] = $this->한질의($id, 'AWS/RDS', $이름,
                    [['Name' => 'DBClusterIdentifier', 'Value' => $묶음]], $셈);
            }
        }

        if ($lb = config('monitoring.load_balancer')) {
            foreach ([
                'alb_req'   => ['RequestCount', 'Sum'],
                'alb_5xx'   => ['HTTPCode_Target_5XX_Count', 'Sum'],
                'alb_time'  => ['TargetResponseTime', 'Average'],
            ] as $id => [$이름, $셈]) {
                $질의[$id] = $this->한질의($id, 'AWS/ApplicationELB', $이름,
                    [['Name' => 'LoadBalancer', 'Value' => (string) $lb]], $셈);
            }
        }

        return $질의;
    }

    private function 한질의(string $id, string $이름칸, string $지표, array $꼬리, string $셈): array
    {
        return [
            'Id'         => $id,
            'ReturnData' => true,
            'MetricStat' => [
                'Metric' => ['Namespace' => $이름칸, 'MetricName' => $지표, 'Dimensions' => $꼬리],
                'Period' => 300,
                'Stat'   => $셈,
            ],
        ];
    }

    /* ── 과금 ───────────────────────────────────────────────────── */

    /**
     * 이번 달 들어 지금까지 쓴 돈과, 어느 서비스가 많이 썼는지.
     *
     * `UnblendedCost` 를 쓴다 — 실제로 청구되는 금액이다. 「월말까지 이렇게 가면
     * 얼마」는 지난 날수로 늘려 어림한 값이라 그렇다고 적어 둔다.
     *
     * 값이 하루쯤 늦게 모인다 — AWS 가 쓴 양을 모아 적는 데 시간이 걸린다. 오늘 몫이
     * 0 으로 보이는 것은 그 탓이고 잘못이 아니다.
     */
    public function 과금(): array
    {
        return Cache::remember('mon:aws:cost', config('monitoring.cache.cost', 21600), function () {
            $첫날 = now()->startOfMonth();
            $내일 = now()->addDay()->startOfDay();

            try {
                /* Cost Explorer 는 어느 지역에서 부르든 us-east-1 한곳에서만 받는다 */
                $ce = new CostExplorerClient([
                    'version' => 'latest',
                    'region'  => 'us-east-1',
                ]);

                $답 = $ce->getCostAndUsage([
                    'TimePeriod'  => [
                        'Start' => $첫날->format('Y-m-d'),
                        'End'   => $내일->format('Y-m-d'),
                    ],
                    'Granularity' => 'MONTHLY',
                    'Metrics'     => ['UnblendedCost'],
                    'GroupBy'     => [['Type' => 'DIMENSION', 'Key' => 'SERVICE']],
                ]);
            } catch (\Throwable $e) {
                return ['ok' => false] + $this->탈($e, 'ce:GetCostAndUsage');
            }

            $합 = 0.0;
            $돈단위 = 'USD';
            $서비스 = [];

            foreach ($답['ResultsByTime'] ?? [] as $칸) {
                foreach ($칸['Groups'] ?? [] as $g) {
                    $값 = (float) ($g['Metrics']['UnblendedCost']['Amount'] ?? 0);
                    $돈단위 = (string) ($g['Metrics']['UnblendedCost']['Unit'] ?? $돈단위);
                    $이름 = (string) ($g['Keys'][0] ?? '-');

                    if ($값 <= 0) {
                        continue;   // 0달러 서비스는 줄만 늘린다
                    }

                    $서비스[$이름] = round(($서비스[$이름] ?? 0) + $값, 2);
                    $합 += $값;
                }
            }

            arsort($서비스);

            /* 이번 달이 며칠 지났나 — 하루치로 나눠 월말을 어림한다 */
            $지난날 = max(1, (int) $첫날->diffInDays(now()) + 1);
            $이달날수 = (int) now()->daysInMonth;

            return [
                'ok'         => true,
                'month'      => now()->format('Y년 n월'),
                'currency'   => $돈단위,
                'total'      => round($합, 2),
                'per_day'    => round($합 / $지난날, 2),
                'forecast'   => round($합 / $지난날 * $이달날수, 2),
                'days'       => $지난날,
                'days_total' => $이달날수,
                'services'   => array_slice($서비스, 0, 8, true),
                'note'       => 'AWS 가 쓴 양을 모아 적는 데 하루쯤 걸립니다 — 오늘 몫은 아직 안 보일 수 있습니다.',
            ];
        });
    }

    /* ── 공통 ───────────────────────────────────────────────────── */

    private function 차림(): array
    {
        return ['version' => 'latest', 'region' => $this->지역];
    }

    /**
     * 막힌 까닭을 사람이 읽을 말로 바꾼다.
     *
     * AWS 의 글월은 한 줄이 수백 자라 화면에 그대로 적을 수 없다. 무엇이 모자란지만
     * 남기고, 어느 권한을 붙여야 하는지 함께 적는다.
     */
    private function 탈(\Throwable $e, string $권한): array
    {
        $코드 = $e instanceof AwsException ? (string) $e->getAwsErrorCode() : '';
        $막힘 = in_array($코드, ['AccessDenied', 'AccessDeniedException', 'UnauthorizedOperation',
                                'AuthFailure', 'DataUnavailableException'], true);

        return [
            'error'  => $막힘
                ? '권한이 없습니다'
                : ($코드 ?: class_basename($e)),
            'denied' => $막힘,
            'action' => $권한,
        ];
    }
}
