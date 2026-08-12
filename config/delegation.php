<?php
// config/delegation.php
// 요양비 지급청구 위임장(별지 제19호의7서식) 자동채움용 고정 정보.
// 준요양기관/수령계좌는 기관 고정값이므로 .env 로 관리한다.

return [

    /*
    |─────────────────────────────────────────────────────────────
    | ② 준요양기관 (콜로플라스트 위임 대상 기관 정보)
    |─────────────────────────────────────────────────────────────
    */
    'provider' => [
        'name'   => env('DELEGATION_PROVIDER_NAME', ''),    // 상호
        'biz_no' => env('DELEGATION_PROVIDER_BIZ_NO', ''),  // 사업자등록번호(법인등록번호)
        'ceo'    => env('DELEGATION_PROVIDER_CEO', ''),      // 대표자
        'phone'  => env('DELEGATION_PROVIDER_PHONE', ''),    // 전화번호(휴대전화번호)
    ],

    /*
    |─────────────────────────────────────────────────────────────
    | ③ 요양비 수령계좌 (수령자는 보통 준요양기관)
    |─────────────────────────────────────────────────────────────
    */
    'account' => [
        'receiver' => env('DELEGATION_ACCOUNT_RECEIVER', ''),  // 수령자
        'bank'     => env('DELEGATION_ACCOUNT_BANK', ''),      // 금융기관명
        'holder'   => env('DELEGATION_ACCOUNT_HOLDER', ''),    // 예금주
        'number'   => env('DELEGATION_ACCOUNT_NUMBER', ''),    // 계좌번호
    ],

    /*
    |─────────────────────────────────────────────────────────────
    | ⑤ 위임기간 — 서명일부터 N년 (최장 5년)
    |─────────────────────────────────────────────────────────────
    */
    'period_years' => (int) env('DELEGATION_PERIOD_YEARS', 5),

    /*
    |─────────────────────────────────────────────────────────────
    | 원본 PDF 오버레이용 서명 좌표 (A4 기준, 단위: mm)
    | 원본 양식 1페이지 "위임인 (서명 또는 인)" 서명란 위치.
    | 미세조정이 필요하면 .env 로 덮어쓴다.
    |─────────────────────────────────────────────────────────────
    */
    'signature' => [
        'x' => (float) env('DELEGATION_SIG_X', 164),  // 좌상단 X (mm) — "(서명 또는 인)" 중심(x≈178)
        'y' => (float) env('DELEGATION_SIG_Y', 266),  // 좌상단 Y (mm) — "(서명 또는 인)" 바로 위
        'w' => (float) env('DELEGATION_SIG_W', 28),   // 너비 (mm, 높이는 비율 자동)
    ],

    /*
    |─────────────────────────────────────────────────────────────
    | 글자 항목의 좌표 (A4 기준, 단위: mm)
    |
    | 예전에는 이 값들이 코드 안에 숫자로 박혀 있어, 양식이 조금만 달라져도
    | 배포를 해야 했다. 서명 위치와 같은 방식으로 화면에서 관리한다.
    | DB(delegation_settings.field_positions)가 있으면 그 값이 이긴다.
    |
    | key => [라벨, x, y, 글자크기]
    |─────────────────────────────────────────────────────────────
    */
    'fields' => [
        // ① 위임인
        'patient_name'     => ['label' => '위임인 성명',        'x' => 133, 'y' => 52,  'size' => 8],
        'patient_rrn'      => ['label' => '위임인 주민등록번호', 'x' => 133, 'y' => 59,  'size' => 8],
        'patient_mobile'   => ['label' => '위임인 전화번호',     'x' => 80,  'y' => 90,  'size' => 8],
        // 미성년자일 때만 찍는다
        'patient_birth'    => ['label' => '위임인 생년월일(미성년)', 'x' => 133, 'y' => 66, 'size' => 8],
        'guardian_name'    => ['label' => '보호자 성명(미성년)',     'x' => 133, 'y' => 73, 'size' => 8],
        'guardian_relation'=> ['label' => '보호자 관계(미성년)',     'x' => 133, 'y' => 80, 'size' => 8],
        // ② 준요양기관
        'provider_name'    => ['label' => '준요양기관 상호',    'x' => 78,  'y' => 116, 'size' => 8],
        'provider_biz_no'  => ['label' => '준요양기관 사업자번호','x' => 78, 'y' => 125, 'size' => 8],
        'provider_ceo'     => ['label' => '준요양기관 대표자',  'x' => 78,  'y' => 135, 'size' => 8],
        'provider_phone'   => ['label' => '준요양기관 전화번호', 'x' => 78,  'y' => 144, 'size' => 8],
        // ③ 요양비 수령계좌
        'account_receiver' => ['label' => '수령자',             'x' => 100, 'y' => 156, 'size' => 8],
        'account_bank'     => ['label' => '금융기관명',          'x' => 140, 'y' => 163, 'size' => 8],
        'account_holder'   => ['label' => '예금주',             'x' => 140, 'y' => 171, 'size' => 8],
        'account_number'   => ['label' => '계좌번호',            'x' => 140, 'y' => 179, 'size' => 8],
        // ⑤ 위임기간 — 인쇄된 년/월/일 글자와 겹치지 않게 작게
        'period_from_y'    => ['label' => '위임기간 시작 년',    'x' => 54,  'y' => 246, 'size' => 7],
        'period_from_m'    => ['label' => '위임기간 시작 월',    'x' => 73,  'y' => 246, 'size' => 7],
        'period_from_d'    => ['label' => '위임기간 시작 일',    'x' => 83,  'y' => 246, 'size' => 7],
        'period_to_y'      => ['label' => '위임기간 종료 년',    'x' => 108, 'y' => 246, 'size' => 7],
        'period_to_m'      => ['label' => '위임기간 종료 월',    'x' => 128, 'y' => 246, 'size' => 7],
        'period_to_d'      => ['label' => '위임기간 종료 일',    'x' => 139, 'y' => 246, 'size' => 7],
    ],

    /*
    | 보호자 서명 — 미성년자일 때 위임인 서명 옆(아래)에 함께 찍는다
    */
    'guardian_signature' => [
        'x' => (float) env('DELEGATION_GSIG_X', 164),
        'y' => (float) env('DELEGATION_GSIG_Y', 280),
        'w' => (float) env('DELEGATION_GSIG_W', 28),
    ],

    /*
    | 미성년 판정 나이 — 민법상 성년은 만 19세다.
    */
    'minor_age' => (int) env('DELEGATION_MINOR_AGE', 19),

];
