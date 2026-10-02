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
     * 위드웍스로 넘기는 청구전략 — 저쪽 billing_strategies 표의 id 다.
     *
     * 코드값이 아니라 줄 번호라 서버마다 다르다. 그래서 갈라 둔다. 전에는 25 를
     * 보내고 있었는데 그것은 account_id 0 의 빈 줄이라 아무것도 가리키지 않았다.
     *
     * 여기서 하는 일은 「이 주문이 어느 전략에 붙는가」를 저쪽에 일러 주는 것까지다.
     * 그 전략이 실제로 청구서를 어떻게 내는지는 위드웍스 몫이고, 우리 세금계산서ㆍ
     * 현금영수증은 팝빌로 우리가 직접 낸다(DepositAutoIssue). 그래서 저쪽 상세 줄이
     * 어떻게 채워져 있든 우리 발행은 흔들리지 않는다 — 유형이 서 있으면 된다.
     *
     * 비어 있는 자리는 아무 id 도 싣지 않는다. 틀린 줄을 가리키느니 저쪽이 제
     * 기본값(전자세금계산서 100%)으로 갈아 끼우는 편이 낫다.
     */
    'withworks_billing_strategy' => [

        // 데모웍스 (테스트) — 여섯 다 있다
        'test' => [
            'default' => 133,
            'map'     => [
                '10|일반'       => 136,  // 현금영수증(10%)+전자세금계산서(90%)
                '10|차상위경감' => 245,  // 건강보험공단 100%
                '10|기초'       => 249,  // 지자체(시군구청) 100%
                '10|산재'       => 250,  // 산재 현금영수증(100%)
                '10|자동차보험' => 247,  // 자동차보험 현금영수증(100%)
                '20|'           => 137,  // 현금영수증(100%)
            ],
        ],

        /* 위드웍스 (운영) — 저쪽 계정 148659 에 실제로 서 있는 전략으로 맞춘다
           (2026-10-01 지시 ㉮-1).

           전에는 650ㆍ651ㆍ652 를 보내고 있었다. **그 번호는 저쪽 표에 없다** —
           운영 billing_strategies 를 직접 읽어 확인했다(id 42~697 중 650~652 구간이
           통째로 비어 있고, 지워진 줄도 아니다). 「새로 만든 셋을 받았다」고 적어
           두었으나 그 전략은 만들어지지 않았다.

           없는 번호를 보내면 저쪽은 그대로 받아 적는다(nullable|integer 검사뿐).
           확정 때 채워 주는 보강도 「비어 있을 때만」이라, 없는 줄을 가리킨 채 남는다.
           2026-02 에 25 를 보내 같은 일을 겪었다(account_id 0 의 빈 줄).

           ── 계정 148659 에 서 있는 일곱 (모두 2018-02-26 · use_yn=Y)

             42 사용분처리(100%)   43 샘플(0%)            44 전자세금계산서(100%)
             45 할인(80%)          46 할인(90%)
             47 현금영수증(10%)+전자세금계산서(90%)       48 현금영수증(100%)

           ── 우리 청구전략(BillingStrategy)과 맞댄 것

             일반       10/90  → 47
             차상위경감  0/100 → 44   (공단에 전자세금계산서 100%)
             기초        0/100 → 44   (지자체에 전자세금계산서 100%)
             산재      100/0   → 48
             자동차보험 100/0  → 48
             처방외    100/0   → 48

           **차상위경감과 기초가 같은 44 로 간다** — 저쪽에서는 청구처가 공단인지
           지자체인지 갈리지 않는다. 우리 쪽은 claim_agency(nhis / local_gov)가 들고
           있으므로 공단 팩스ㆍ지자체 서류는 바르게 갈린다. 저쪽이 제 청구서에서
           둘을 갈라야 한다면 전용 전략을 만들어 받고 그 번호로 바꾼다. */
        'production' => [
            'default' => 44,     // 못 찾아도 기관 100% 로 둔다 — 저쪽 기본값과 같다
            'map'     => [
                '10|일반'       => 47,  // 현금영수증(10%)+전자세금계산서(90%)
                '10|차상위경감' => 44,  // 전자세금계산서(100%) — 공단
                '10|기초'       => 44,  // 전자세금계산서(100%) — 지자체
                '10|산재'       => 48,  // 현금영수증(100%)
                '10|자동차보험' => 48,  // 현금영수증(100%)
                '20|'           => 48,  // 현금영수증(100%)
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

    /*
    |──────────────────────────────────────────────────────
    | SupportWorks 오류 보고 (App\Support\SupportWorksReporter)
    |──────────────────────────────────────────────────────
    | 운영에서 난 예외를 SupportWorks 로 보낸다. 값은 .env 에만 둔다.
    | 둘 중 하나라도 비어 있으면 보고는 조용히 건너뛴다.
    */
    'supportworks' => [
        'error_url'   => env('SW_ERROR_URL'),
        'error_token' => env('SW_ERROR_TOKEN'),
    ],

    /* 오류ㆍSR 을 Agent 에게 넘기는 길 (2026-10-02 지시).
       값은 환경 설정 › Agent 연계에서 고친다 — 여기 기본값은 모두 꺼짐이다.
       열쇠(token)는 암호화해 담기므로 .env 에 두지 않는다. */
    'agent' => [
        // 쓰는 자리를 둘로 가른다 — SR 관리와 오류 기록 (2026-10-02 지시)
        'sr_enabled'    => env('AGENT_SR_ENABLED', false),
        'error_enabled' => env('AGENT_ERROR_ENABLED', false),
        'url'           => env('AGENT_URL'),
        'token'         => env('AGENT_TOKEN'),
        'send_image'    => false,
        /* Agent 작업자의 열쇠와 모델 — 값은 DB(환경 설정 › Agent 연계)에 담는다.
           .env 에 두지 않는 까닭은 서버를 만지지 않고 바꿀 수 있어야 하기 때문이다
           (2026-10-02 지시). 여기 기본값은 비어 있다. */
        'api_key'       => null,
        'model'         => 'claude-opus-5',
    ],

    /*
    |──────────────────────────────────────────────────────
    | 앱 알림(FCM) 자격 — App\Helpers\FcmHelper
    |──────────────────────────────────────────────────────
    | storage/ 아래 상대 경로. 앱은 판마다 Firebase 프로젝트가 달라, 서버가 드는
    | 자격이 그 판과 짝이 맞아야 알림이 닿는다. 시험 동안에는 개발용으로 두고
    | 운영 전환 때 되돌린다 — .env 의 FCM_SERVICE_ACCOUNT 한 줄이다.
    */
    'fcm' => [
        'service_account' => env('FCM_SERVICE_ACCOUNT', 'app/firebase/service-account.json'),
    ],
];
