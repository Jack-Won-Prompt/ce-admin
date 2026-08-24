<?php

return [

    /*
     * 입금이 확인되면 세무 서류를 스스로 발행할지.
     *
     * 이 발행은 국세청 실신고다(팝빌 운영 모드). 잘못 나간 것을 되돌리려면 취소 신고를
     * 다시 해야 하므로, 기본은 꺼 둔다. 자동 발행을 쓰기로 정하면 .env 에서 켠다.
     *
     *   BILLING_AUTO_ISSUE=true
     *
     * 켜도 다음은 지킨다 — 이미 발행된 것은 다시 내지 않고, 금액이 0 이면 내지 않으며,
     * 청구전략이 「확인중」인 건에는 손대지 않는다(App\Services\DepositAutoIssue).
     */
    'auto_issue' => (bool) env('BILLING_AUTO_ISSUE', false),

];
