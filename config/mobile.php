<?php
// config/mobile.php
//
// 모바일 앱의 판 관리. 값은 관리자 화면(설정 › 서비스 연동 설정 › 모바일 앱)에서
// 고치고, 앱이 GET /api/app/version 으로 물어본다.
//
// 판을 둘로 나눈 까닭 —
//   latest  스토어에 올라간 새 판. 알리기만 한다. 「나중에」로 넘길 수 있다.
//   min     이 아래로는 쓸 수 없는 판. 넘길 수 없다. 서버와 주고받는 약속이
//           바뀌었을 때 쓴다 — 옛 판을 그대로 두면 엉뚱한 자료가 올라온다.
// 둘 다 비어 있으면 앱은 아무것도 묻지 않는다.

return [
    'latest_version' => env('MOBILE_LATEST_VERSION', ''),
    'min_version'    => env('MOBILE_MIN_VERSION', ''),

    'store_url' => env(
        'MOBILE_STORE_URL',
        'https://play.google.com/store/apps/details?id=com.coloplast.ceadmin',
    ),

    // 새 판에서 무엇이 달라졌는지 한두 줄. 비우면 기본 문구가 나간다.
    'notice' => env('MOBILE_UPDATE_NOTICE', ''),
];
