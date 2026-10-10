<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Services\AgentWorker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 받은 짐 하나를 큐에서 일한다 (2026-10-07 지시).
 *
 * 전에는 웹훅을 받은 자리에서 `exec('… agent:work &')` 로 프로세스를 떼어 던졌다.
 * 즉시 돌기는 했지만 세 가지가 없었다 — 재시도, 실패한 자취, 동시 수 상한. 여러
 * 건이 한꺼번에 오면 Claude 호출도 그만큼 동시에 나갔다.
 *
 * 이제 줄(queue `agent`)에 쌓고 일꾼이 차례로 집는다. Claude 가 429 를 주거나
 * 길이 막히면 60초ㆍ300초 뒤 다시 시도하고, 세 번 모두 어긋나면 `failed_jobs` 에
 * 남아 `queue:retry` 로 되돌릴 수 있다.
 *
 * ## 처리 표시를 다루는 까닭
 *
 * AgentWorker 는 일을 마치든 멈추든 그 줄의 `response` 칸에 글을 적는다 — 그 칸이
 * 「손댔다」는 표시라서, 멈춘 뒤 다시 집으려면 그 표시를 지워야 한다. 그래서
 * 멈춘 글이 돌아오면 표시를 지우고 잘못을 다시 던져 큐에 되돌린다. **마지막
 * 차례에서는 지우지 않는다** — 무슨 일로 멈췄는지가 화면에 남아야 한다.
 */
class AgentWorkJob implements ShouldQueue
{
    use Queueable;

    /** 세 번까지 */
    public int $tries = 3;

    /** Claude 가 120초, 고치는 걸음이 더 걸린다 — 넉넉히 둔다 */
    public int $timeout = 600;

    /** 어긋난 뒤 얼마나 쉬고 다시 할까 */
    public array $backoff = [60, 300];

    /**
     * 줄을 갈래마다 따로 둔다 (2026-10-10 지시).
     *
     * 오류는 `agent`, SR 은 `agent-sr` 이다. 일꾼이 `--queue=agent,agent-sr` 로 돌아
     * **둘 다 차례가 되면 오류를 먼저** 집는다 — SR 이 밀려 있어도 오류가 그 뒤에
     * 줄 서지 않는다.
     */
    public function __construct(public int $자취번호, string $갈래 = 'error.raised')
    {
        $this->onQueue($갈래 === 'sr.created' ? 'agent-sr' : 'agent');
    }

    public function handle(AgentWorker $일꾼): void
    {
        $결과 = $일꾼->한줄만($this->자취번호);
        $글   = implode(' · ', (array) ($결과['글'] ?? []));

        Log::info('[Agent] 큐에서 일했습니다', [
            'log'   => $this->자취번호,
            'try'   => $this->attempts(),
            'glu'   => mb_substr($글, 0, 300),
        ]);

        if (! str_contains($글, '멈췄습니다')) {
            return;
        }

        /* 아직 차례가 남았으면 처리 표시를 지우고 되돌린다 */
        if ($this->attempts() < $this->tries) {
            WebhookLog::where('id', $this->자취번호)->update(['response' => null]);
        }

        throw new \RuntimeException($글);
    }
}
