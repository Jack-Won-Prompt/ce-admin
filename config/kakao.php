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
];
