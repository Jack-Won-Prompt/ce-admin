<?php

return [
    'LinkID'            => env('POPBILL_ID'),
    'SecretKey'         => env('POPBILL_SECRET_KEY'),
    'IsTest'            => env('POPBILL_IS_TEST', true),
    'IPRestrictOnOff'   => env('POPBILL_IP_RESTRICT_ON_OFF', true),
    'UseStaticIP'       => env('POPBILL_USE_STATIC_IP', false),
    'UseLocalTimeYN'    => env('POPBILL_USE_LOCAL_TIME_YN', true),
    'LINKHUB_COMM_MODE' => env('POPBILL_LINKHUB_COMM_MODE', 'CURL'),

    /*
     * 문자를 실제로 보내지 않고 로그만 남길지.
     *
     * 예전 기본값은 true 였다. 그래서 팝빌을 운영으로 돌린 뒤에도(POPBILL_IS_TEST=false)
     * 세금계산서ㆍ현금영수증ㆍ팩스는 실제로 나가는데 문자만 조용히 시뮬레이션이었다 —
     * 화면은 「발송되었습니다」라고 하는데 아무도 받지 못했다. 문자만 다르게 서 있을
     * 이유가 없어 팝빌 시험 여부를 그대로 따르게 한다.
     *
     * 그래도 따로 끄고 켤 수 있게 열쇠는 남긴다(POPBILL_SMS_SIMULATE).
     */
    'sms_simulate'      => env('POPBILL_SMS_SIMULATE', env('POPBILL_IS_TEST', true)),

    /*
     * 발행ㆍ취소를 팝빌에 보내지 않고 성공으로 처리할지.
     *
     * 팝빌이 운영이면(IS_TEST=false) 발행은 곧 국세청 신고다. 화면을 처음부터 끝까지
     * 훑어 보는 시험에서는 그 신고가 나가면 안 된다 — 되돌리려면 취소 신고를 또 해야
     * 하고, 그것도 남의 회사 이름으로 남는다. 이 열쇠가 켜져 있으면 조회는 그대로
     * 팝빌에 묻고, 쓰는 일(즉시발행ㆍ임시저장ㆍ발행ㆍ삭제ㆍ취소)만 막는다.
     */
    'issue_simulate'    => env('POPBILL_ISSUE_SIMULATE', false),

    'test' => [
        'corp_num'     => env('POPBILL_TEST_CORP_NUM'),
        'user_id'      => env('POPBILL_TEST_USER_ID'),
        'cert_key'     => env('POPBILL_TEST_CERT_KEY'),
        'receiver_hp'  => env('POPBILL_TEST_RECEIVER_HP'),
        /*
         * 발신번호는 문자와 팩스가 따로 승인된다. 콜로플라스트 계정에서도 서로 다른 번호가
         * 승인돼 있어(문자 1588-7866 · 팩스 02-722-6002) 한 값을 같이 쓰면 한쪽이 미등록으로
         * 걸린다. 서비스별 값을 먼저 보고, 없으면 예전 단일 키로 떨어진다.
         */
        'sender_num'   => env('POPBILL_TEST_SENDER_NUM'),
        'sms_sender'   => env('POPBILL_SMS_SENDER_NUM', env('POPBILL_TEST_SENDER_NUM')),
        'fax_sender'   => env('POPBILL_FAX_SENDER_NUM', env('POPBILL_TEST_SENDER_NUM')),
        'receiver_fax' => env('POPBILL_TEST_RECEIVER_FAX'),
    ],

    'company' => [
        'corp_name' => env('COMPANY_CORP_NAME', ''),
        'ceo_name'  => env('COMPANY_CEO_NAME',  ''),
        'addr'      => env('COMPANY_ADDR',       ''),
        'biz_class' => env('COMPANY_BIZ_CLASS',  ''),
        'biz_type'  => env('COMPANY_BIZ_TYPE',   ''),
        'email'     => env('COMPANY_EMAIL',      ''),
        'tel'       => env('COMPANY_TEL',        ''),
    ],
];
