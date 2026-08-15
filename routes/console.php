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

// 팩스 전송 결과 반영: 5분마다.
// 접수는 바로 되지만 변환·발신은 몇 분 걸리고 실패도 그때 드러난다. 사람이 '대기건 동기화'를
// 누르기 전까지 실패한 건이 접수 상태로 보이면, 공단 제출이 안 된 것을 늦게 알게 된다.
Schedule::command('fax:sync-pending')->everyFiveMinutes()->withoutOverlapping();
