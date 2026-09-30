<?php
// config/kakao.php

return [
    'api_key'      => env('KAKAO_API_KEY', ''),
    'user_id'      => env('KAKAO_USER_ID', ''),
    'sender_key'   => env('KAKAO_SENDER_KEY', ''),
    'sender_phone' => env('KAKAO_SENDER_PHONE', ''),
    'channel_id'   => env('KAKAO_CHANNEL_ID', ''),    // 카카오 채널 ID (@채널명)
    'channel_url'  => env('KAKAO_CHANNEL_URL', ''),   // https://pf.kakao.com/_xxxxx
    'test_mode'    => env('KAKAO_TEST_MODE', true),

    /*
     * 알림톡과 문자를 어떻게 함께 쓸 것인가 (2026-09-30 지시).
     *
     *   alimtalk_first  알림톡을 먼저 보내고, **나가면 거기서 멈춘다**.
     *                   막히면 문자로 잇는다.
     *   all             켜 둔 채널로 **모두** 보낸다.
     *
     * 2026-09-19 에 all 로 바꾼 적이 있다 — 「알림톡이 성공하면 거기서 멈춰
     * 둘 다 켜 두어도 한쪽만 나간다」가 그때의 까닭이었다. 2026-09-30 지시로
     * 되돌린다: 같은 말을 두 통 받는 쪽이 더 번거롭다는 판단이다.
     *
     * 화면(설정 › 서비스 연동 설정 › 카카오 알림톡)에서 고른다.
     */
    'send_policy'  => env('KAKAO_SEND_POLICY', 'alimtalk_first'),
];
