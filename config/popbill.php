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
        'sender_num'   => env('POPBILL_TEST_SENDER_NUM'),
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
