<?php

namespace App\Console\Commands;

use App\Services\WithworksSync;
use Illuminate\Console\Command;

/**
 * Withworks 물류 상태를 주기적으로 끌어온다.
 *
 * 콜백이 없어 우리가 물어봐야 하는데, 담당자가 주문 상세를 열 때만 물어보면 아무도 안 연
 * 주문은 상태가 옛것으로 남는다. 배송이 끝났는데도 '출고 대기'로 보여 청구 대상에서 빠지는
 * 것이 실제 문제였다.
 */
class WithworksSyncCommand extends Command
{
    protected $signature = 'withworks:sync {--limit=200 : 한 번에 확인할 주문 수}';

    protected $description = 'Withworks 판매주문의 물류 진행 상태를 끌어온다';

    public function handle(WithworksSync $sync): int
    {
        $r = $sync->sweep((int) $this->option('limit'));

        if (!$r['configured']) {
            $this->warn('Withworks API 설정이 없습니다 — 건너뜁니다.');

            return self::SUCCESS;
        }

        $this->info("확인 {$r['checked']}건 · 변경 {$r['updated']}건");

        return self::SUCCESS;
    }
}
