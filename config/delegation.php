<?php
// config/delegation.php
// 요양비 지급청구 위임장(별지 제19호의7서식) 자동채움용 고정 정보.
// 준요양기관/수령계좌는 기관 고정값이므로 .env 로 관리한다.

return [

    /*
    |─────────────────────────────────────────────────────────────
    | ② 준요양기관 (콜로플라스트 위임 대상 기관 정보)
    |─────────────────────────────────────────────────────────────
    */
    'provider' => [
        'name'   => env('DELEGATION_PROVIDER_NAME', ''),    // 상호
        'biz_no' => env('DELEGATION_PROVIDER_BIZ_NO', ''),  // 사업자등록번호(법인등록번호)
        'ceo'    => env('DELEGATION_PROVIDER_CEO', ''),      // 대표자
        'phone'  => env('DELEGATION_PROVIDER_PHONE', ''),    // 전화번호(휴대전화번호)
    ],

    /*
    |─────────────────────────────────────────────────────────────
    | ③ 요양비 수령계좌 (수령자는 보통 준요양기관)
    |─────────────────────────────────────────────────────────────
    */
    'account' => [
        'receiver' => env('DELEGATION_ACCOUNT_RECEIVER', ''),  // 수령자
        'bank'     => env('DELEGATION_ACCOUNT_BANK', ''),      // 금융기관명
        'holder'   => env('DELEGATION_ACCOUNT_HOLDER', ''),    // 예금주
        'number'   => env('DELEGATION_ACCOUNT_NUMBER', ''),    // 계좌번호
    ],

    /*
    |─────────────────────────────────────────────────────────────
    | ⑤ 위임기간 — 서명일부터 N년 (최장 5년)
    |─────────────────────────────────────────────────────────────
    */
    'period_years' => (int) env('DELEGATION_PERIOD_YEARS', 5),

];
