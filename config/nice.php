<?php
// config/nice.php
// NICE 본인확인 서비스(신규 REST API) 연동 설정.
// 자격증명은 기관 고정값이므로 .env 로 관리하고, 코드에서는 config('nice.*')로만 접근한다.
// 값이 아직 없으면(자리표시자) enabled=false 로 판단되어 서명 화면에서 '미설정'으로 표시되고,
// 강제(enforce)도 걸리지 않아 기존 서명 흐름은 그대로 동작한다. 자격증명을 채우면 자동 활성화.

$clientId     = env('NICE_CLIENT_ID', '');
$clientSecret = env('NICE_CLIENT_SECRET', '');
$productId    = env('NICE_PRODUCT_ID', '');

/* 통합인증(IDO/INTC)은 client_id 와 client_secret 두 개로 부른다.
   productID 는 예전 CheckPlus 표준창이 쓰던 값이라 여기서는 묻지 않는다 —
   그것까지 있어야 켜지게 두면, 새 자격증명만 받은 기관이 계속 「미설정」이 된다. */
$enabled = $clientId !== '' && $clientSecret !== '';

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
    /* 통합인증 API 서버 (접근토큰ㆍ인증주소ㆍ인증결과).
       .env 에 옛 CheckPlus 주소(svc.niceapi.co.kr)가 남아 있으면 못 본 척한다 —
       그 API 는 이제 부르지 않는데, 적혀 있다는 이유로 새 주소를 덮으면 배포하고도
       계속 옛 서버로 가서 403 을 받는다(설정 표 쪽도 같은 이유로 걸러 낸다). */
    'api_base' => rtrim(
        str_contains((string) env('NICE_API_BASE', ''), 'svc.niceapi.co.kr')
            ? 'https://auth.niceid.co.kr'
            : env('NICE_API_BASE', 'https://auth.niceid.co.kr'),
        '/'
    ),

    // API 규격 버전 — 주소에 그대로 들어간다(/ido/intc/{version}/…)
    'version' => env('NICE_API_VERSION', 'v1.0'),

    /* 표준창에 세울 인증 수단 — M 휴대폰 · F 금융인증서 · I 아이핀 · U 공동인증서.
       기본은 휴대폰 하나다. 위임장에 서명할 사람이 금융인증서까지 갖추고 있으리라
       기대할 수 없다. */
    'svc_types' => array_values(array_filter(
        explode(',', (string) env('NICE_SVC_TYPES', 'M'))
    )),

    /* 표준창 주소는 이제 우리가 들고 있지 않다 — 인증 주소 요청 API 가 건마다 만들어
       준다. 예전 값이 설정 표에 남아 있어도 쓰이지 않는다. */

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
