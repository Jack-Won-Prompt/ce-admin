<?php

namespace App\Console\Commands;

use App\Services\AgentWorker;
use Illuminate\Console\Command;

/**
 * 받아 둔 오류ㆍSR 을 Agent 가 읽고 회신한다 (2026-10-02 지시).
 *
 *   php artisan agent:work           — 손대지 않은 것 세 건까지 (손으로 확인할 때)
 *   php artisan agent:work --log=123 — 그 웹훅 자취 한 줄만
 *
 * **스케줄로 돌지 않는다** (2026-10-02 지시). 웹훅을 받은 자리가 그 줄 번호를 들고
 * 이 명령을 띄운다. 열쇠가 없거나 받은 짐이 없으면 아무 일도 하지 않고 끝난다.
 */
class AgentWorkCommand extends Command
{
    protected $signature = 'agent:work
                            {--limit= : 한 번에 처리할 건수}
                            {--log= : 이 웹훅 자취 한 줄만 (웹훅이 띄울 때 쓴다)}';

    protected $description = '받아 둔 오류ㆍSR 을 Claude 로 분석해 보내 온 쪽에 회신한다';

    public function handle(AgentWorker $작업자): int
    {
        /* 웹훅이 띄운 것이면 그 줄 하나만 본다 — 남의 줄까지 집으면 같은 짐을
           두 번 묻는 일이 생긴다(웹훅이 잇달아 올 때). */
        if ($하나 = (int) $this->option('log')) {
            $결과 = $작업자->한줄만($하나);
        } else {
            $한번에 = (int) ($this->option('limit') ?: AgentWorker::한번에);
            $결과 = $작업자->돌린다(max(1, $한번에));
        }

        foreach ($결과['글'] as $줄) {
            $this->line('  ' . $줄);
        }

        $this->info(sprintf('처리 %d건 · 멈춤 %d건', $결과['처리'], $결과['건너뜀']));

        return self::SUCCESS;
    }
}
