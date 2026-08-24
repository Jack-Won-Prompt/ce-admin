<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |──────────────────────────────────────────────────────
    | 위드웍스 연동 (테스트=데모웍스 · 운영=위드웍스)
    |──────────────────────────────────────────────────────
    | 값은 .env 가 아니라 DB(withworks_settings)에서 온다. 설정 › 위드웍스 연동 화면에서
    | 관리하고, WithworksSetting::applyToConfig() 가 요청마다 아래 자리를 채운다.
    |
    | 여기에 env() 를 두지 않는다. 두 곳에 값이 있으면 화면에서 바꿔도 .env 가 이기는 것처럼
    | 보이는 때가 생기고, 어느 쪽이 실제로 쓰이는지 아무도 확신하지 못한다.
    */
    'demoworks' => [
        'api_url'        => null,
        'token'          => null,
        'webhook_secret' => null,
        'account_id'     => null,
        'so_type'        => null,
        'return_so_type' => null,
        'mode'           => null,
    ],

    /*
     * 위드웍스로 넘기는 청구전략.
     *
     * so_store 에 billing_strategy 를 25 로 박아 보내고 있었다. 기초든 일반이든 처방외든
     * 늘 같은 값이라, 우리 화면이 정한 청구전략(유형 × 자격)이 저쪽에는 닿지 않았다.
     * 조회(so_show)로 물어봐도 저쪽 응답에는 billing 칸이 없어 무엇으로 받았는지도
     * 알 수 없다.
     *
     * 열쇠는 우리 표의 것과 같다(「유형|자격」). 값은 위드웍스의 청구전략 코드다 —
     * 아직 어느 코드가 무엇인지 받지 못해 모두 25 로 두었다. 코드표를 받으면 이 표만
     * 고치면 되고, 여기 없는 짝은 default 로 나간다.
     */
    'withworks_billing_strategy' => [
        'default' => 25,
        'map'     => [
            '30|일반'       => 25,   // 처방전-원내 · 일반
            '30|차상위경감' => 25,
            '30|기초'       => 25,
            '30|산재'       => 25,
            '30|자동차보험' => 25,
            '10|일반'       => 25,   // 처방전-원외 · 일반
            '10|차상위경감' => 25,
            '10|기초'       => 25,
            '10|산재'       => 25,
            '10|자동차보험' => 25,
            '20|'           => 25,   // 처방외
        ],
    ],

    /*
    |──────────────────────────────────────────────────────
    | CE샵 Webhook & API
    |──────────────────────────────────────────────────────
    | 설정 › 서비스 연동 설정 화면에서 관리한다(settings-schema 의 ce_shop 그룹).
    | 아래는 화면에서 아직 저장하지 않았을 때의 기본값이다.
    |
    | 공유 비밀에는 기본값을 두지 않는다. 예전에는 'ce-shop-secret-2026' 이 박혀 있었는데,
    | 코드에 적힌 비밀은 아는 사람이면 누구나 쓸 수 있어 비밀이 아니다. 정해지지 않았으면
    | 없는 것이 맞고, 받는 쪽이 그때 거절한다.
    */
    'ce_shop' => [
        'webhook_secret' => env('CE_SHOP_WEBHOOK_SECRET'),
        'base_url'       => env('CE_SHOP_BASE_URL', 'http://localhost/ce-shop/public'),
        'api_enabled'    => env('CE_SHOP_API_ENABLED', false),
    ],
];
