<?php
// config/ocr.php
// 처방전 OCR 공급자(provider) 설정.
// 실제 사용할 provider 는 관리자 설정(DB: ocr_settings)에서 고르며, 여기 default_provider 는
// DB 설정이 아직 없을 때의 기본값이다. Textract 자격증명이 없으면 OcrService 가 자동으로
// AI(Claude/OpenAI)로 폴백하므로, 자격증명 확보 전에도 OCR 은 정상 동작한다.

$awsKey    = env('AWS_ACCESS_KEY_ID', '');
$awsSecret = env('AWS_SECRET_ACCESS_KEY', '');
$awsRegion = env('AWS_DEFAULT_REGION', env('AWS_REGION', 'ap-northeast-2'));  // 서울 리전

return [

    // DB 미설정 시 기본 provider: textract | ai
    'default_provider' => env('OCR_PROVIDER', 'textract'),

    /*
    |──────────────────────────────────────────────────────────────
    | AWS Textract (신규 기본 OCR)
    |──────────────────────────────────────────────────────────────
    | ⚠ Textract 는 한글(CJK) 미지원 — 숫자(주민번호·날짜·전화)·라틴 위주로 인식되고
    |   환자명·병원명·상병명 등 한글 필드는 누락될 수 있어 검수 화면에서 보정한다.
    */
    'textract' => [
        'region'  => $awsRegion,
        'key'     => $awsKey,
        'secret'  => $awsSecret,
        'version' => 'latest',
        // 자격증명이 채워졌을 때만 실제 사용(없으면 AI 폴백). EC2 IAM Role 사용 시 강제 활성화 override.
        'enabled' => (bool) env('OCR_TEXTRACT_ENABLED', $awsKey !== '' && $awsSecret !== ''),
    ],
];
