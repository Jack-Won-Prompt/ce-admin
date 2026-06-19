<?php

namespace App\Console\Commands;

use App\Services\Popbill\CashbillSyncService;
use Illuminate\Console\Command;

class CashbillSyncCommand extends Command
{
    protected $signature = 'cashbill:sync
                            {--corp-num= : 사업자번호 (기본: 설정 값)}
                            {--days=30   : 동기화할 기간 (일 수, 기본 30일)}
                            {--start=    : 시작일 YYYYMMDD (--days 대신 지정 가능)}
                            {--end=      : 종료일 YYYYMMDD}
                            {--status    : 비최종 상태 레코드 상태값만 갱신}';

    protected $description = '팝빌 현금영수증 내역을 DB에 동기화합니다';

    public function handle(CashbillSyncService $syncSvc): int
    {
        $corpNum = $this->option('corp-num') ?? config('popbill.test.corp_num');

        if ($this->option('status')) {
            $this->info("[Cashbill Sync] 비최종 상태 갱신 시작 (corpNum={$corpNum})");
            $r = $syncSvc->refreshPendingStatus($corpNum);
            $this->info("  갱신: {$r['updated']}건  오류: {$r['errors']}건");
            return Command::SUCCESS;
        }

        $end   = $this->option('end')   ?? now()->format('Ymd');
        $start = $this->option('start') ?? now()->subDays((int) $this->option('days') - 1)->format('Ymd');

        $this->info("[Cashbill Sync] {$start} ~ {$end} 동기화 시작 (corpNum={$corpNum})");

        $r = $syncSvc->syncFromPopbill($corpNum, $start, $end);
        $this->info("  동기화: {$r['synced']}건  오류: {$r['errors']}건");

        // 상태 갱신도 함께
        $r2 = $syncSvc->refreshPendingStatus($corpNum);
        $this->info("  상태 갱신: {$r2['updated']}건  오류: {$r2['errors']}건");

        return Command::SUCCESS;
    }
}
