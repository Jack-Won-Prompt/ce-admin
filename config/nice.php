<?php
// config/nice.php
// NICE 본인확인 서비스(신규 REST API) 연동 설정.
// 자격증명은 기관 고정값이므로 .env 로 관리하고, 코드에서는 config('nice.*')로만 접근한다.
// 값이 아직 없으면(자리표시자) enabled=false 로 판단되어 서명 화면에서 '미설정'으로 표시되고,
// 강제(enforce)도 걸리지 않아 기존 서명 흐름은 그대로 동작한다. 자격증명을 채우면 자동 활성화.

$clientId     = env('NICE_CLIENT_ID', '');
$clientSecret = env('NICE_CLIENT_SECRET', '');
$productId    = env('NICE_PRODUCT_ID', '');

$enabled = $clientId !== '' && $clientSecret !== '' && $productId !== '';

return [

    /*
    |──────────────────────────────────────────────────────────────
    | 자격증명 (NICE 디지털 신분증/본인확인 기관회원 발급값)
    |──────────────────────────────────────────────────────────────
    */
    'client_id'     => $clientId,      // 발급받은 client_id (기관코드)
    'client_secret' => $clientSecret,  // 발급받은 client_secret
    'product_id'    => $productId,     // 이용상품 ID (본인확인 서비스)

    // 모든 자격증명이 채워졌을 때만 실제 연동 활성화
    'enabled' => $enabled,

    // 활성화 시 서명 전 본인확인을 '강제'할지 여부.
    // 기본값: 활성화되면 강제(true), 자격증명 없으면 비강제(false)로 기존 흐름 유지.
    'enforce' => (bool) env('NICE_ENFORCE', $enabled),

    /*
    |──────────────────────────────────────────────────────────────
    | 엔드포인트 (운영 기본값 — 필요 시 .env 로 덮어쓰기)
    |──────────────────────────────────────────────────────────────
    */
    // API 서버 (기관토큰/암호화토큰 발급)
    'api_base' => rtrim(env('NICE_API_BASE', 'https://svc.niceapi.co.kr:22001'), '/'),

    // 표준창(팝업) 호출 URL
    'standard_url' => env('NICE_STANDARD_URL', 'https://nice.checkplus.co.kr/CheckPlusSafeModel/service.cb'),

    // NICE API 호출 타임아웃(초) — 응답 지연이 서명 페이지를 붙잡지 않게 제한
    'http_timeout' => (int) env('NICE_HTTP_TIMEOUT', 10),

    /*
    |──────────────────────────────────────────────────────────────
    | 본인확인 결과 ↔ 처방전 환자 매칭 정책
    |──────────────────────────────────────────────────────────────
    */
    'match' => [
        // 이름 일치 필수
        'require_name'  => (bool) env('NICE_MATCH_NAME', true),
        // 생년월일 일치 필수 (환자 생년월일이 있을 때 적용)
        'require_birth' => (bool) env('NICE_MATCH_BIRTH', true),
    ],

    // 암호화 자료(key/iv/hmac)의 캐시 보관 시간(분) — 표준창 왕복 동안만 유지
    'crypto_ttl_minutes' => (int) env('NICE_CRYPTO_TTL', 10),
];
