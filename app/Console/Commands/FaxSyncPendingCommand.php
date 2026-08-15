<?php

namespace App\Console\Commands;

use App\Services\Popbill\FaxSyncService;
use Illuminate\Console\Command;

/**
 * 아직 결과가 안 온 팩스의 상태를 팝빌에서 받아 적는다.
 *
 * 화면의 '대기건 동기화' 버튼과 같은 일을 한다. 사람이 누르기 전에는 실패한 건도 접수
 * 상태로 보여서, 공단 제출이 실패한 것을 늦게 알게 된다.
 */
class FaxSyncPendingCommand extends Command
{
    protected $signature   = 'fax:sync-pending {--corp= : 사업자번호(생략하면 설정값)}';
    protected $description = '전송 결과가 안 온 팩스를 팝빌에서 조회해 이력에 반영한다';

    public function handle(FaxSyncService $sync): int
    {
        $result = $sync->syncPending($this->option('corp'));

        $this->info(sprintf(
            '팩스 결과 동기화 — 대상 %d · 반영 %d · 실패 %d',
            $result['checked'], $result['synced'], $result['errors']
        ));

        return self::SUCCESS;
    }
}
