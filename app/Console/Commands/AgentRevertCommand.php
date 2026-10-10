<?php

namespace App\Console\Commands;

use App\Services\AgentFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 자동 고침을 되물린다 — 배포 점검이 멈췄을 때 (2026-10-10 지시).
 *
 * 배포는 두 걸음이다. 1단계에서 테스트서버에 올려 라우트가 서는지ㆍ화면이 응답하는지
 * 보고, 지나야 운영으로 간다. 여기서 멈추면 운영에는 가지 않지만 **그 커밋은 main 에
 * 남는다** — 다음 사람이 올린 것이 점검을 지나면 묻어서 함께 운영으로 올라간다.
 *
 * 그래서 멈춘 그 자리에서 이 명령을 부른다. 워크플로의 1단계가 실패하면 같은 서버에서
 * 바로 돈다(`.github/workflows/deploy.yml`).
 *
 * **사람이 올린 커밋은 건드리지 않는다.** 적은 이가 Agent 일 때만 되물린다 — 남의
 * 일을 말없이 되돌리는 것이 멈춰 세우는 것보다 나쁘다.
 */
class AgentRevertCommand extends Command
{
    protected $signature = 'agent:revert
                            {sha : 되물릴 커밋}
                            {--reason= : 까닭 — 커밋 글월에 적는다}';

    protected $description = '자동 고침 커밋을 되물린다 (배포 점검이 멈췄을 때)';

    /** Agent 가 적는 이름 — AgentFixer 의 커밋과 같아야 한다 */
    private const 우리메일 = 'agent@ce-admin.co.kr';

    public function handle(AgentFixer $고치개): int
    {
        $해시 = trim((string) $this->argument('sha'));
        $까닭 = trim((string) ($this->option('reason') ?: '배포 점검에서 멈췄습니다'));

        if ($해시 === '') {
            $this->error('되물릴 커밋을 적어 주십시오.');

            return self::FAILURE;
        }

        $적은이 = $고치개->적은이($해시);

        if ($적은이 === null) {
            $this->error("그 커밋을 찾지 못했습니다 ({$해시})");

            return self::FAILURE;
        }

        if ($적은이 !== self::우리메일) {
            /* 사람이 올린 것이다 — 그대로 둔다. 운영 배포는 어차피 멈춰 있고,
               무엇이 잘못됐는지는 올린 사람이 가장 잘 안다. */
            $this->info("사람이 올린 커밋이라 두고 봅니다 ({$적은이})");
            Log::info('[Agent] 되물리지 않았습니다 — 사람이 올린 커밋', [
                'sha' => $해시, 'author' => $적은이,
            ]);

            return self::SUCCESS;
        }

        $결과 = $고치개->되물린다($해시, $까닭);

        $this->line($결과['글']);
        Log::warning('[Agent] 자동 고침을 되물렸습니다', [
            'sha' => $해시, 'reason' => $까닭, 'ok' => $결과['했나'],
        ]);

        return $결과['했나'] ? self::SUCCESS : self::FAILURE;
    }
}
