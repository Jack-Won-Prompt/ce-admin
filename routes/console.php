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

/* Withworks 는 아직 우리를 불러 주지 않는다. 상세를 열 때만 물어보면 아무도 안 연 주문은
   상태가 옛것으로 남아, 배송이 끝났는데도 청구 대상에서 빠진다. 그래서 우리가 훑는다. */
Schedule::command('withworks:sync')->everyTenMinutes()->withoutOverlapping();

/* 청구 준비 여부는 발행·배송 때마다 그 자리에서 다시 따지지만, 주문을 건드리지 않는
   변화(위임 등록일이 환자 쪽에서 바뀌는 것 같은)가 있어 주기적으로 다시 본다. */
Schedule::command('claim:refresh')->hourly()->withoutOverlapping();

/* 통장에 무엇이 들어왔는지 서른 분마다 긁는다(요청서 5쪽 · 2026-08-31 회신).

   화면에서 「지금 가져오기」로 곧바로 받을 수 있지만, 눌러 주는 사람이 없어도 맞춰지는
   그물이 있어야 한다 — 무통장으로 들어온 돈과 기관 환급은 아무도 알려 주지 않는다.
   기다리지 않는다. 팝빌이 아직 모으는 중이면 다음 차례가 같은 기간을 다시 읽는다. */
Schedule::command('bank:sync')->everyThirtyMinutes()->withoutOverlapping();

/* 공단 재등록이 다가온 사람을 담당자에게 알린다 (2026-09-05 지시).

   공단에 신규 등록하면 2년 뒤 다시 등록해야 한다. 기한을 놓치면 자격이 끊기고,
   그 뒤에 나간 물건은 공단에 청구할 수 없다 — 이미 보낸 값은 우리가 떠안는다.
   환자는 자기 등록이 언제 끝나는지 모른다.

   기한 계산은 진작 있었다(등록일 + 2년). 없던 것은 그것을 누가 언제 보는가다.
   기한이 칸에만 적혀 있으면 아무도 보지 않는다.

   아침 아홉 시에 한 번 돈다 — 담당자가 자리에 앉는 때다. 밤에 알려 봐야
   그때는 아무도 전화를 걸 수 없다. */
Schedule::command('nhis:renew-notice')->dailyAt('09:00')->withoutOverlapping();
