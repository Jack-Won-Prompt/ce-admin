<?php
// config/ocr.php
// 처방전 OCR 설정. 공급자는 AWS Textract 단일이다.
// 자격증명이 없거나 미지원 형식이면 OCR 은 동작하지 않고 오류를 올린다(폴백 대상 없음).

$awsKey    = env('AWS_ACCESS_KEY_ID', '');
$awsSecret = env('AWS_SECRET_ACCESS_KEY', '');
$awsRegion = env('AWS_DEFAULT_REGION', env('AWS_REGION', 'ap-northeast-2'));  // 서울 리전

return [

    // 공급자는 textract 뿐이다 (과거 ai 공급자는 제거됨)
    'default_provider' => 'textract',

    /*
    |──────────────────────────────────────────────────────────────
    | AWS Textract (유일한 OCR 공급자)
    |──────────────────────────────────────────────────────────────
    | ⚠ Textract 는 한글(CJK) 미지원 — 숫자(주민번호·날짜·전화)·라틴 위주로 인식되고
    |   환자명·병원명·상병명 등 한글 필드는 누락될 수 있어 검수 화면에서 보정한다.
    */
    'textract' => [
        'region'  => $awsRegion,
        'key'     => $awsKey,
        'secret'  => $awsSecret,
        'version' => 'latest',
        // 자격증명이 채워졌을 때만 사용. EC2 IAM Role 사용 시 강제 활성화 override.
        'enabled' => (bool) env('OCR_TEXTRACT_ENABLED', $awsKey !== '' && $awsSecret !== ''),
    ],
];
