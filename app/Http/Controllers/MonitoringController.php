<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResponseTimeRecorder;
use App\Models\ErrorLog;
use App\Services\Monitoring\AwsMetrics;
use App\Services\Monitoring\DatabaseMetrics;
use App\Services\Monitoring\NginxMetrics;
use App\Services\Monitoring\ServerMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 시스템 감시 (2026-10-02 지시).
 *
 * 화면은 두 가지를 함께 보여 준다 — **우리가 직접 잰 것**과 **AWS 가 준 것**이다.
 * 지금 운영 역할에는 CloudWatch 권한이 없어 AWS 쪽은 거의 다 「권한 없음」이다.
 * 그래도 CPUㆍ메모리ㆍ디스크ㆍ요청ㆍ응답ㆍDB 는 모두 실제 값으로 찬다.
 *
 * `모으기()` 를 화면과 다시 읽기(JSON) 둘이 함께 쓴다 — 두 자리가 어긋나지 않게.
 */
class MonitoringController extends Controller
{
    public function __construct(
        private ServerMetrics $서버,
        private NginxMetrics $웹,
        private DatabaseMetrics $디비,
        private AwsMetrics $aws,
    ) {
    }

    public function index(): View
    {
        return view('monitoring.index', [
            '값'     => $this->모으기(),
            'refresh' => (int) config('monitoring.refresh', 60),
            '선'     => config('monitoring.thresholds'),
        ]);
    }

    /** 화면이 스스로 다시 읽는 자리 — 통째로 그리지 않고 수치만 바꾼다 */
    public function data(Request $request)
    {
        return response()->json($this->모으기((int) $request->integer('hours', 12)));
    }

    private function 모으기(int $시간 = 12): array
    {
        $시간 = max(1, min(48, $시간));

        $서버 = $this->서버->지금();
        $웹   = $this->웹->오늘();
        $디비 = $this->디비->지금();
        $응답 = ResponseTimeRecorder::모은것(60);

        return [
            'server'   => $서버,
            'series'   => $this->서버->지난값($시간),
            'web'      => $웹,
            'response' => $응답,
            'db'       => $디비,
            'aws'      => [
                'identity' => $this->aws->신분(),
                'metrics'  => $this->aws->지표($시간),
            ],
            'cost'     => $this->aws->과금(),
            'health'   => $this->건강($서버, $웹, $디비, $응답),
            'incidents' => $this->최근장애(),
            'checked_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 한눈에 보는 상태.
     *
     * 선을 넘은 것이 하나라도 있으면 그 가장 나쁜 것을 그대로 말한다. 「정상」이라고
     * 적어 놓고 그 안에 위험이 묻히면 감시하는 뜻이 없다.
     */
    private function 건강(array $서버, array $웹, array $디비, array $응답): array
    {
        $선 = config('monitoring.thresholds');
        $말 = [];

        $견주기 = function (string $이름, $값, array $잣대) use (&$말) {
            if ($값 === null) {
                return;
            }
            if ($값 >= ($잣대['bad'] ?? PHP_INT_MAX)) {
                $말[] = ['level' => 'bad', 'text' => $이름];
            } elseif ($값 >= ($잣대['warn'] ?? PHP_INT_MAX)) {
                $말[] = ['level' => 'warn', 'text' => $이름];
            }
        };

        $견주기('CPU 사용률이 높습니다', $서버['cpu'] ?? null, $선['cpu']);
        $견주기('메모리 사용률이 높습니다', $서버['memory']['percent'] ?? null, $선['memory']);
        $견주기('디스크 사용률이 높습니다', $서버['disk']['percent'] ?? null, $선['disk']);
        $견주기('DB 연결이 많습니다', $디비['conn_percent'] ?? null, $선['db_conn']);
        $견주기('응답이 느립니다', $응답['avg'] ?? null, $선['response']);
        $견주기('서버 오류가 있습니다', $웹['by_class']['5xx'] ?? null, $선['error_5xx']);

        $가장나쁜 = 'ok';
        foreach ($말 as $한) {
            if ($한['level'] === 'bad') {
                $가장나쁜 = 'bad';
                break;
            }
            $가장나쁜 = 'warn';
        }

        return [
            'level'  => $가장나쁜,
            'label'  => ['ok' => '정상', 'warn' => '주의', 'bad' => '위험'][$가장나쁜],
            'reasons' => $말,
        ];
    }

    /**
     * 최근 장애 — 우리가 이미 쥐고 있는 오류 기록에서 모은다.
     *
     * 따로 장애 표를 세우지 않는다. 500 이 난 것과 브라우저가 멈춘 것이 이미 거기에
     * 담겨 있고, 처리 상태도 그 자리에서 함께 보인다 — 「복구」인지 「확인 중」인지를
     * 사람이 적어 둔 그대로 쓴다.
     */
    private function 최근장애(): array
    {
        return ErrorLog::query()
            ->where('last_at', '>=', now()->subDays(3))
            ->orderByDesc('last_at')
            ->limit(12)
            ->get(['id', 'kind', 'message', 'http_status', 'source', 'hit', 'last_at', 'status'])
            ->map(fn (ErrorLog $r) => [
                'id'     => $r->id,
                'at'     => $r->last_at?->format('m-d H:i'),
                'kind'   => $r->kind,
                'text'   => mb_substr(preg_replace('/\s+/', ' ', (string) $r->message), 0, 70),
                'code'   => $r->http_status,
                'source' => $r->source_label,
                'hit'    => $r->hit,
                'state'  => $r->status_label,
                'tone'   => ErrorLog::상태색[$r->status] ?? 'muted',
            ])
            ->values()
            ->all();
    }
}
