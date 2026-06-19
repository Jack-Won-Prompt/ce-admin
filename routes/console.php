<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 현금영수증 팝빌 동기화: 매시간 실행 (최근 1일치 + 비최종 상태 갱신)
Schedule::command('cashbill:sync --days=1')->hourly()->withoutOverlapping();

// 국세청 전송 실패·전송중 상태 갱신: 15분마다
Schedule::command('cashbill:sync --status')->everyFifteenMinutes()->withoutOverlapping();
