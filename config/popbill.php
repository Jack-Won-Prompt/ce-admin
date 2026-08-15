<?php

return [
    'LinkID'            => env('POPBILL_ID'),
    'SecretKey'         => env('POPBILL_SECRET_KEY'),
    'IsTest'            => env('POPBILL_IS_TEST', true),
    'IPRestrictOnOff'   => env('POPBILL_IP_RESTRICT_ON_OFF', true),
    'UseStaticIP'       => env('POPBILL_USE_STATIC_IP', false),
    'UseLocalTimeYN'    => env('POPBILL_USE_LOCAL_TIME_YN', true),
    'LINKHUB_COMM_MODE' => env('POPBILL_LINKHUB_COMM_MODE', 'CURL'),

    'sms_simulate'      => env('POPBILL_SMS_SIMULATE', true),

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
