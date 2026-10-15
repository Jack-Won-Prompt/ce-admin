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

/* 가상계좌 입금을 토스에 되묻는다 — **받는 길 하나에 돈을 걸지 않는다** (2026-10-15 지시).

   여태 입금을 아는 길은 토스 웹훅 하나뿐이었다. 그 한 번이 실패하면 돈은 들어왔는데
   우리는 영영 모른다 — 화면에는 「입금 대기」로 남고, 증빙도 창고도 서지 않는다.

   2026-10-14 에 그렇게 됐다. 웹훅 마흔한 번이 401 로 거절됐고(우리 서명 검증이
   막았다 — 그날 고쳤다), 그 사이 입금된 건을 사람이 하나씩 찾아 맞추었다.
   토스는 「콜백 호출이 실패했습니다」 메일만 보낼 뿐, 묻지 않으면 다시 알려 주지
   않는다. 웹훅이 끊기는 길은 그 하나가 아니다 — 저쪽 재시도가 끝나거나, 우리
   서버가 잠긐 멈추거나, 인증서가 끊기면 똑같이 놓친다.

   입금을 기다리는 줄만 묻는다 — 운영에서 한 번에 서넥이라 저쪽에 부담을 주지 않는다. */
Schedule::command('toss:sync-deposits')->everyTenMinutes()->withoutOverlapping();


/* Agent 는 스케줄로 돌지 않는다 (2026-10-02 지시).
   오류ㆍSR 은 웹훅으로 들어오는 그 자리에서 곧바로 일한다 —
   AgentHookController 가 받아 적은 뒤 그 줄 하나를 두고 작업자를 띄운다.
   1분마다 빈 표를 들여다보는 일을 없앴다. */

/* 청구 준비 여부는 예약으로 훑지 않는다 (2026-09-18 지시).

   주문을 건드리지 않는 변화(처방전 그림ㆍ청구 기관ㆍ위임 등록일ㆍ서류함) 때문에 한
   시간마다 훑었는데, 그 사이에는 목록이 틀린 채로 서 있었다 — 실제로 여섯 건이
   「준비완료」로 보이는데 자료가 빠져 있었다. 이제 그 넷이 바뀌는 자리에서 곧바로
   다시 따진다(Prescription·Patient·PrescriptionDocument 의 booted).

   `claim:refresh` 는 손으로 부르는 그물로 남는다 — 자료를 손으로 고쳤거나 판정 잣대를
   바꾼 뒤에 한 번에 맞출 때 쓴다. */

/* 통장 긁기는 예약하지 않는다 (2026-09-18 지시).

   계좌조회 설정이 없어 예약이 돌아도 첫 줄에서 「건너뜁니다」 하고 나온다. 쓰기로
   정하면 그때 다시 세운다 — 그전까지는 화면의 「지금 가져오기」로 부른다. */

/* 공단 재등록 임박은 화면에서 본다 (2026-09-18 지시).

   공단에 신규 등록하면 2년 뒤 다시 등록해야 한다. 기한을 놓치면 자격이 끊기고,
   그 뒤에 나간 물건은 공단에 청구할 수 없다 — 이미 보낸 값은 우리가 떠안는다.
   환자는 자기 등록이 언제 끝나는지 모른다.

   여태 아침 아홉 시에 알림을 밀어 넣었다. 밀어 넣는 방식은 그날 그 자리에 있어야
   보이고, 놓치면 다시 볼 자리가 없다. 거래처 목록의 「재등록 임박」 거르개로 바꾼다 —
   언제 열어도 지금 임박한 사람이 그대로 서 있고, 몇 명인지가 거르개에 적힌다.

   `nhis:renew-notice` 는 손으로 부를 수 있게 남겨 둔다. */
