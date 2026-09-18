<?php

namespace App\Console\Commands;

use App\Services\WithworksImport;
use Illuminate\Console\Command;

/**
 * 위드웍스 운영 자료를 가져온다 — 손으로 부르는 자리.
 *
 * 화면(설정 › 위드웍스 자료 가져오기)이 같은 서비스를 부른다. 명령을 따로 두는 것은
 * 첫 판처럼 열 만 줄을 담을 때다 — 화면은 웹 요청이라 오래 쥐고 있으면 끊긴다.
 */
class WithworksImportCommand extends Command
{
    protected $signature = 'withworks:import
                            {갈래? : prescription_infos · customers · customer_addresses (없으면 모두)}
                            {--from= : 이 번호 뒤부터 다시 읽는다 (0 이면 처음부터)}';

    protected $description = '위드웍스 운영 자료를 우리 표로 가져옵니다 (읽기만 합니다)';

    public function handle(WithworksImport $svc): int
    {
        $갈래들 = $this->argument('갈래')
            ? [$this->argument('갈래')]
            : array_keys(WithworksImport::대상);

        foreach ($갈래들 as $열쇠) {
            if (! isset(WithworksImport::대상[$열쇠])) {
                $this->error("모르는 갈래입니다: {$열쇠}");

                return self::FAILURE;
            }

            if ($this->option('from') !== null) {
                $svc->마지막번호저장($열쇠, (int) $this->option('from'));
            }

            $이름   = WithworksImport::대상[$열쇠]['이름'];
            $시작   = $svc->마지막번호($열쇠);
            $this->info("── {$이름} ({$열쇠}) · {$시작}번 뒤부터");

            $때 = microtime(true);

            try {
                $r = $svc->가져오기($열쇠, function ($읽음, $마지막) use ($이름) {
                    $this->output->write(sprintf("\r   %s  %s줄 · 마지막 %s      ",
                        $이름, number_format($읽음), number_format($마지막)));
                });
            } catch (\Throwable $e) {
                $this->newLine();
                /* 질의가 통째로 실린 오류는 수십만 자다 — 앞머리만 보인다 */
                $this->error('  ' . mb_substr($e->getMessage(), 0, 300));

                return self::FAILURE;
            }

            $this->newLine();
            $this->line(sprintf('   가져옴 %s줄 · 마지막 번호 %s · %.1f초',
                number_format($r['읽음']), number_format($r['마지막']), microtime(true) - $때));
        }

        $this->newLine();
        $this->info('── 지금까지 담긴 것 ──');
        foreach ($svc->현황() as $열쇠 => $h) {
            $this->line(sprintf('  %-22s %8s줄 · 마지막 %s',
                $h['이름'], number_format($h['담긴줄']), number_format($h['마지막'])));
        }

        return self::SUCCESS;
    }
}
