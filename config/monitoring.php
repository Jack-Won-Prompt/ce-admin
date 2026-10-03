<?php

/*
|--------------------------------------------------------------------------
| 시스템 감시 (2026-10-02 지시)
|--------------------------------------------------------------------------
|
| 화면이 읽는 자리를 한곳에 모았다. 운영에서 실제로 확인한 값이 기본값이다.
|
| ## 어디서 오는 값인가
|
| AWS CloudWatch 로만 알 수 있는 것과, 이 기계에서 직접 잴 수 있는 것이 갈린다.
| CPUㆍ메모리ㆍ디스크는 CloudWatch 를 기다리지 않고 `/proc` 과 `df`, 지난 값은 `sar`
| 에서 읽는다 — 화면이 바로 서야 하기 때문이다.
|
| **CloudWatch 권한은 2026-10-03 에 열렸다.** 10-02 에는 `cloudwatch:ListMetrics` 가
| AccessDenied 였다. 운영에서 권한을 열고 CloudWatch 에이전트를 붙여 메모리ㆍ디스크를
| `CWAgent` 이름칸으로 올리게 했다. 화면은 두 자리를 함께 보여 준다 — 이 기계가 바로
| 잰 값과 CloudWatch 에 쌓인 값이다. 두 값이 벌어지면 한쪽이 멈춘 것이다.
|
| 아직 막힌 것은 `ec2:DescribeInstances`ㆍ`elasticloadbalancing:DescribeLoadBalancers`
| ㆍ`ce:GetCostAndUsage` 다. 그 칸은 어느 권한이 모자란지 화면에 적는다.
|
| ## 지역
|
| `.env` 의 `AWS_DEFAULT_REGION` 이 라라벨 기본값 `us-east-1` 그대로였다. 실제 자원은
| 서울(ap-northeast-2)에 있다 — Aurora 주소가 그렇게 적혀 있다. 그 값을 고치면 S3 를
| 쓰는 자리에 영향이 갈 수 있어, 감시 화면은 **자기 지역을 따로 쥔다.**
|
*/

return [

    /** 자원이 있는 지역 — Aurora 주소에서 확인한 값 */
    'region' => env('MONITOR_AWS_REGION', 'ap-northeast-2'),

    /**
     * 볼 EC2 기계. 비워 두면 STS 가 돌려주는 ARN 에서 찾아낸다
     * (`assumed-role/unicorn-ec2-ssm-role/i-0229bd5e3bb567f37` → 뒤쪽).
     */
    'instance_id' => env('MONITOR_EC2_INSTANCE'),

    /** Aurora 클러스터. 비워 두면 `DB_HOST` 앞머리에서 찾아낸다 */
    'db_cluster' => env('MONITOR_DB_CLUSTER'),

    /**
     * 로드밸런서의 CloudWatch 꼬리표(`app/이름/아이디`).
     *
     * 운영은 nginx 가 80ㆍ443 을 직접 받는다 — 로드밸런서가 **없다**(2026-10-02 확인).
     * 그래서 비워 둔다. 앞에 세우면 이 값을 채우면 그 칸이 살아난다.
     */
    'load_balancer' => env('MONITOR_ALB'),

    /** 넘으면 주의ㆍ위험으로 적는 선 (백분율, 응답은 밀리초) */
    'thresholds' => [
        'cpu'      => ['warn' => 70, 'bad' => 85],
        'memory'   => ['warn' => 80, 'bad' => 90],
        'disk'     => ['warn' => 75, 'bad' => 85],
        'db_conn'  => ['warn' => 60, 'bad' => 80],
        'response' => ['warn' => 800, 'bad' => 2000],
        'error_5xx' => ['warn' => 1, 'bad' => 10],
    ],

    /** 이 기계를 재는 자리 */
    'sar_dir'   => env('MONITOR_SAR_DIR', '/var/log/sysstat'),
    'nginx_log' => env('MONITOR_NGINX_LOG', '/var/log/nginx/access.log'),

    /**
     * nginx 기록을 얼마나 거슬러 읽나 (바이트).
     *
     * 7.6MB 쯤 쌓여 있고 하루면 20MB 를 넘는다. 끝에서부터 이만큼만 읽어 오늘 것을
     * 고른다 — 다 읽으면 화면 한 번에 수십MB 를 메모리에 담는다.
     */
    'nginx_bytes' => (int) env('MONITOR_NGINX_BYTES', 24 * 1024 * 1024),

    /**
     * 같은 값을 다시 묻지 않는 동안 (초).
     *
     * 과금은 Cost Explorer 를 부르며 **호출마다 0.01달러**가 붙는다. 여섯 시간에 한 번만
     * 묻는다 — 하루 네 번, 한 달 1.2달러어치다.
     */
    'cache' => [
        'server' => 10,
        'nginx'  => 60,
        'db'     => 15,
        'aws'    => 300,
        'cost'   => 6 * 3600,
    ],

    /** 화면이 스스로 다시 읽는 간격 (초). 12초마다 읽는 일은 하지 않는다 */
    'refresh' => (int) env('MONITOR_REFRESH', 60),
];
