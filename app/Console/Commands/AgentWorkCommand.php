<?php

namespace App\Console\Commands;

use App\Services\AgentWorker;
use Illuminate\Console\Command;

/**
 * 받아 둔 오류ㆍSR 을 Agent 가 읽고 회신한다 (2026-10-02 지시).
 *
 *   php artisan agent:work           — 세 건까지
 *   php artisan agent:work --limit=1 — 한 건만 (손으로 확인할 때)
 *
 * 스케줄러가 1분마다 부른다(routes/console.php). 열쇠가 없거나 받은 짐이 없으면
 * 아무 일도 하지 않고 끝난다.
 */
class AgentWorkCommand extends Command
{
    protected $signature = 'agent:work {--limit= : 한 번에 처리할 건수}';

    protected $description = '받아 둔 오류ㆍSR 을 Claude 로 분석해 보내 온 쪽에 회신한다';

    public function handle(AgentWorker $작업자): int
    {
        $한번에 = (int) ($this->option('limit') ?: AgentWorker::한번에);

        $결과 = $작업자->돌린다(max(1, $한번에));

        foreach ($결과['글'] as $줄) {
            $this->line('  ' . $줄);
        }

        $this->info(sprintf('처리 %d건 · 멈춤 %d건', $결과['처리'], $결과['건너뜀']));

        return self::SUCCESS;
    }
}
