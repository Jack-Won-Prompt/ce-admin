<?php

return [

    /*
    | 카카오 로컬 — 주소를 좌표ㆍ행정동으로 바꾸고 그 자리 둘레를 찾는다.
    | 관할 청구처 찾기에서 지자체(행정복지센터)를 세울 때 쓴다.
    | 알림톡(config/kakao.php)과는 다른 열쇠다 — 그쪽은 발송 대행사의 것이고
    | 이것은 카카오 개발자센터에서 받는 REST API 키다.
    */
    'kakao_local' => [
        'rest_key' => env('KAKAO_LOCAL_REST_KEY', ''),
    ],

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
     * so_store 의 billing_strategy 는 **위드웍스 billing_strategies 표의 id** 다. 코드값이
     * 아니라 줄 번호라, 서버(데모웍스ㆍ위드웍스)마다 다를 수 있다 — 그래서 갈라 둔다.
     *
     * 지금까지 25 를 보내고 있었다. 데모웍스에서 그 줄을 찾아보니 account_id 0 의 빈
     * 줄이었다 — 아무것도 가리키지 않는다. 그때 저쪽은 조용히 자기 기본값(전자세금계산서
     * 100%)으로 갈아 끼운다(CeAdminController::resolveBillingStrategyId). 그래서 지금껏
     * 사고가 나지 않았을 뿐, 우리가 정한 전략은 한 번도 닿은 적이 없다.
     *
     * 열쇠는 우리 표(App\Support\BillingStrategy)의 것과 같다 — 「유형|자격」.
     * 옆에 적어 둔 것은 그 줄이 실제로 무엇을 하는가다(위드웍스 화면에서 읽은 것).
     */
    'withworks_billing_strategy' => [

        /*
         * 데모웍스 (테스트) — 2026-08-31 확인.
         * 245ㆍ246ㆍ247 은 이날 위드웍스가 우리 요청대로 만들어 준 것이다.
         */
        'test' => [
            'default' => 133,   // 전자세금계산서(100%) — 저쪽 기본값과 같게 둔다
            'map'     => [
                '10|일반'       => 136,  // 현금영수증(10%)+전자세금계산서(90%) — 환자 10 · 공단 90
                '10|차상위경감' => 245,  // 공단 전자세금계산서(100%)     — 공단 100 · 세금계산서 Y
                '10|기초'       => 246,  // 지자체 직접청구(0%)           — 청구서를 내지 않는다
                '10|산재'       => 133,  // 전자세금계산서(100%)          — 환자 100 · 세금계산서 Y
                '10|자동차보험' => 247,  // 자동차보험 현금영수증(100%)   — 환자 100 · 세금계산서 N
                '20|'           => 137,  // 현금영수증(100%)              — 환자 100 · 세금계산서 N
            ],
        ],

        /*
         * 위드웍스 (운영) — 650ㆍ651ㆍ652 만 받았다.
         *
         * 나머지 셋(일반ㆍ산재ㆍ처방외)은 원래 있던 줄이라 운영 id 를 아직 모른다.
         * 데모웍스의 136ㆍ133ㆍ137 을 그대로 옮기면 엉뚱한 줄을 가리킨다 — id 는
         * 서버마다 다르다. 받을 때까지 비워 둔다: 값이 없으면 저쪽이 제 기본값
         * (전자세금계산서 100%)으로 갈아 끼우고, 그것이 지금까지 일어나던 일이다.
         *
         * default 도 비워 둔다. 운영의 「전자세금계산서(100%)」 id 를 받으면 그것이
         * 곧 산재의 값이자 default 다.
         */
        'production' => [
            'default' => null,
            'map'     => [
                '10|일반'       => null, // 현금영수증(10%)+전자세금계산서(90%) ← id 필요
                '10|차상위경감' => 650,  // 공단 전자세금계산서(100%)
                '10|기초'       => 651,  // 지자체 직접청구(0%)
                '10|산재'       => null, // 전자세금계산서(100%)                ← id 필요
                '10|자동차보험' => 652,  // 자동차보험 현금영수증(100%)
                '20|'           => null, // 현금영수증(100%)                    ← id 필요
            ],
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
