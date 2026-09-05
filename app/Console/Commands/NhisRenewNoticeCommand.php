<?php

namespace App\Console\Commands;

use App\Services\NhisRenewNotice;
use Illuminate\Console\Command;

/**
 * 공단 재등록이 다가온 사람을 담당자에게 알린다.
 *
 * 하루 한 번 돈다. 기한 2주 전부터 알리고, 이미 지난 건도 석 달까지는 알린다 —
 * 놓친 건이야말로 알려야 한다.
 */
class NhisRenewNoticeCommand extends Command
{
    protected $signature   = 'nhis:renew-notice {--dry : 알리지 않고 몇 건인지만 센다}';
    protected $description = '공단 재등록 기한이 2주 안으로 들어온 사람을 담당자에게 알린다';

    public function handle(NhisRenewNotice $notice): int
    {
        if ($this->option('dry')) {
            $rows = \App\Models\Patient::query()
                ->whereNotNull('nhis_renew_due')
                ->whereBetween('nhis_renew_due', [
                    now()->subMonths(3)->toDateString(),
                    now()->addDays(NhisRenewNotice::LEAD_DAYS)->toDateString(),
                ])->get(['id', 'name', 'nhis_renew_due', 'nhis_renew_told_at']);

            $this->table(['id', '이름', '재등록 기한', '마지막 알림'],
                $rows->map(fn ($p) => [$p->id, $p->name, $p->nhis_renew_due, $p->nhis_renew_told_at ?: '—'])->all());
            $this->info($rows->count() . '건이 알림 범위에 있습니다(알리지 않았습니다).');

            return self::SUCCESS;
        }

        $r = $notice->sweep();

        $this->info(sprintf('훑음 %d · 알림 %d · 오늘 이미 알림 %d',
            $r['checked'], $r['told'], $r['skipped']));

        return self::SUCCESS;
    }
}
