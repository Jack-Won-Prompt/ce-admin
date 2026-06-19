<?php
// config/nhis.php — NHIS 건강보험 청구 및 e-Fax 설정

return [

    /*
    |--------------------------------------------------------------------------
    | NHIS 기관 정보
    |--------------------------------------------------------------------------
    */
    'institution' => [
        'name'    => env('NHIS_INSTITUTION_NAME', 'CE (주)대한소변기기'),
        'code'    => env('NHIS_INSTITUTION_CODE', ''),      // 요양기관 기호
        'biz_no'  => env('NHIS_BIZ_NO', ''),               // 사업자등록번호
    ],

    /*
    |--------------------------------------------------------------------------
    | e-Fax 설정
    | driver: "simulation" (개발/테스트) | "hifaxkorea" | "efax" | "custom"
    |--------------------------------------------------------------------------
    */
    'efax' => [
        'driver'         => env('NHIS_EFAX_DRIVER', 'simulation'),
        'sender_number'  => env('NHIS_EFAX_SENDER', ''),     // 발신 팩스번호
        'nhis_fax_number'=> env('NHIS_FAX_NUMBER', '02-000-0000'),  // 공단 수신 팩스번호

        // HiFaxKorea API (드라이버: hifaxkorea)
        'hifaxkorea' => [
            'api_url'    => env('HIFAX_API_URL', 'https://api.hifaxkorea.com'),
            'api_key'    => env('HIFAX_API_KEY', ''),
            'api_secret' => env('HIFAX_API_SECRET', ''),
        ],

        // 일반 e-Fax API (드라이버: efax)
        'efax' => [
            'api_url'    => env('EFAX_API_URL', ''),
            'account_id' => env('EFAX_ACCOUNT_ID', ''),
            'api_key'    => env('EFAX_API_KEY', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 청구 설정
    |--------------------------------------------------------------------------
    */
    'claim' => [
        'auto_submit_on_delivery' => env('NHIS_AUTO_SUBMIT', false), // 배송완료 시 자동 청구
        'retry_failed_after_hours'=> 24,                              // 실패 재시도 대기 시간
        'max_retries'             => 3,
    ],
];
