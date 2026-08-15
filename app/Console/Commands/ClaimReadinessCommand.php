<?php

namespace App\Console\Commands;

use App\Services\ClaimReadiness;
use Illuminate\Console\Command;

/**
 * 청구 준비 상태를 다시 따진다.
 *
 * 발행·배송 같은 사건마다 그 자리에서도 따지지만, 주문을 건드리지 않는 변화(위임 등록일이
 * 환자 쪽에서 바뀌는 것 같은)가 있어 주기적으로 다시 본다.
 */
class ClaimReadinessCommand extends Command
{
    protected $signature = 'claim:refresh {--limit=500 : 한 번에 확인할 주문 수}';

    protected $description = '주문의 공단 청구 준비 상태를 다시 계산한다';

    public function handle(ClaimReadiness $svc): int
    {
        $r = $svc->sweep((int) $this->option('limit'));
        $this->info("확인 {$r['checked']}건 · 준비완료 {$r['ready']}건");

        return self::SUCCESS;
    }
}
