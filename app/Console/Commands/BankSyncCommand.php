<?php

namespace App\Console\Commands;

use App\Services\BankSync;
use Illuminate\Console\Command;

/**
 * 통장 거래내역을 긁어 온다 (요청서 5쪽).
 *
 * 서른 분마다 돈다. 눌러 주는 사람이 없어도 맞춰지는 그물이다 — 화면의 「지금 가져오기」가
 * 실패하거나 아무도 안 열어 본 날에도 내역은 쌓여 있어야 한다.
 *
 * 기다리지 않는다. 팝빌이 아직 모으는 중이면 그냥 물러난다 — 다음 차례가 같은 기간을
 * 다시 걸어 읽는다. 붙잡고 기다리면 스케줄이 그만큼 늦어진다.
 */
class BankSyncCommand extends Command
{
    protected $signature = 'bank:sync {--days= : 며칠치를 긁을지} {--wait : 다 모일 때까지 기다린다}';

    protected $description = '팝빌 계좌조회로 통장 거래내역을 받아 온다';

    public function handle(BankSync $sync): int
    {
        if (!$sync->configured()) {
            $this->warn('계좌조회 설정이 없습니다 — 건너뜁니다.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: config('bank.sync_days', 7));

        $out = $sync->pull(today()->subDays($days), today(), (bool) $this->option('wait'));

        $this->line($out['message']);

        return $out['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
