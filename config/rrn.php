<?php

/**
 * 주민등록번호(고유식별정보) 처리 설정 — P0-1
 *
 * 처리 근거: 개인정보보호법 §24-2①1 (법령에서 구체적으로 요구하는 경우).
 * 국민건강보험법 시행규칙 별지 서식(급여비 지급청구서 / 급여비 지급청구 위임장)이
 * 주민등록번호 기재를 요구하므로, 그 제출 목적에 한해 보유한다.
 *
 * 키는 APP_KEY 와 반드시 분리한다. APP_KEY 가 노출되어도 주민번호는 열리지 않아야 한다.
 */
return [

    /*
     * 전용 암호화 키. base64:... 형식.
     *   php artisan rrn:key            (아래 명령이 없으면)
     *   php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
     *
     * 값이 없으면 암호화/복호화 시 예외를 던진다 — 평문으로 조용히 흘러가면 안 된다.
     */
    'key' => env('RRN_ENCRYPTION_KEY'),

    'cipher' => env('RRN_CIPHER', 'AES-256-CBC'),

    /*
     * 조회용 해시의 pepper. 암호화 키와 별개로 둔다.
     * 주민번호는 경우의 수가 좁아 pepper 없는 SHA-256 은 전수 대입으로 역산된다.
     */
    'pepper' => env('RRN_HASH_PEPPER'),

    /*
     * 보유 기간 — 승인된 RoPA 기재값. 바꾸려면 RoPA 개정이 선행되어야 한다.
     *   basis = last_transaction : 해당 환자의 최종 주문·청구일 (없으면 환자 등록일)
     */
    'retention' => [
        'years' => (int) env('RRN_RETENTION_YEARS', 5),
        'basis' => env('RRN_RETENTION_BASIS', 'last_transaction'),
    ],

    /*
     * 복호화가 허용되는 사유 코드. 여기에 없는 코드로는 복호화할 수 없다.
     * 감사로그에 그대로 기록되므로, 코드를 늘릴 때는 그 경로가 정말 평문을 필요로 하는지 따져야 한다.
     */
    'decrypt_reasons' => [
        'nhis_claim_form' => '급여비 지급청구서·위임장 등 법정서식 출력',
        'operator_view'   => '검수 화면에서 담당자가 원문 확인',
        'backfill_verify' => '이관 배치의 왕복 검증',
    ],
];
