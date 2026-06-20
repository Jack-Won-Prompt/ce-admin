<?php
// app/Services/TossPayments/TossClient.php
// 토스페이먼츠 REST API 기본 클라이언트 (cURL)

namespace App\Services\TossPayments;

use Illuminate\Support\Facades\Log;

class TossClient
{
    protected string $secretKey;
    protected string $baseUrl;
    protected bool   $testMode;

    /** 토스 결제 상태 레이블 */
    public const STATUS_LABELS = [
        'READY'              => ['대기',         'secondary'],
        'IN_PROGRESS'        => ['처리중',        'info'],
        'WAITING_FOR_DEPOSIT'=> ['입금대기',      'warning'],
        'DONE'               => ['완료',          'success'],
        'CANCELED'           => ['취소',          'danger'],
        'PARTIAL_CANCELED'   => ['부분취소',      'warning'],
        'ABORTED'            => ['실패',          'danger'],
        'EXPIRED'            => ['만료',          'secondary'],
    ];

    /** 은행 코드 → 은행명 (문자 코드 + 숫자 코드 병행 지원) */
    public const BANK_NAMES = [
        // 문자 코드 (토스페이먼츠 API)
        'KDB' => '산업은행',  'IBK' => '기업은행',  'KB'  => 'KB국민',
        'SH'  => '수협',      'NH'  => 'NH농협',    'WB'  => '우리은행',
        'SC'  => 'SC제일',    'CB'  => '시티은행',  'DGB' => 'DGB대구',
        'BNK' => 'BNK부산',   'GJB' => '광주은행',  'JJB' => '전북은행',
        'KNB' => 'KN경남',    'SB'  => '새마을',    'CU'  => '신협',
        'SFB' => '저축은행',  'KFB' => '케이뱅크',  'KKB' => '카카오뱅크',
        'TBK' => '토스뱅크',  'HNB' => '하나은행',  'SHB' => '신한은행',
        // 숫자 코드 (금융결제원 표준)
        '002' => '산업은행',  '003' => '기업은행',  '004' => 'KB국민',
        '007' => '수협',      '011' => 'NH농협',    '020' => '우리은행',
        '023' => 'SC제일',    '027' => '시티은행',  '031' => 'DGB대구',
        '032' => 'BNK부경',   '034' => '광주은행',  '035' => '전북은행',
        '037' => '전남은행',  '039' => 'KN경남',    '045' => '새마을',
        '048' => '신협',      '050' => '저축은행',  '064' => '산림조합',
        '071' => '우체국',    '081' => '하나은행',  '088' => '신한은행',
        '089' => '케이뱅크',  '090' => '카카오뱅크','092' => '토스뱅크',
    ];

    public function __construct()
    {
        $this->secretKey = config('toss.secret_key', '');
        $this->baseUrl   = rtrim(config('toss.base_url', 'https://api.tosspayments.com'), '/');
        $this->testMode  = (bool) config('toss.test_mode', true);
    }

    // ─────────────────────────────────────────────────────────────
    // Public HTTP 메서드
    // ─────────────────────────────────────────────────────────────

    /** GET 요청 */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST 요청 */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    // ─────────────────────────────────────────────────────────────
    // API 키 설정 여부 확인
    // ─────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /** API 서버 연결 가능 여부 확인 */
    public function ping(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        try {
            $ch = curl_init($this->baseUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY         => true,
            ]);
            curl_exec($ch);
            $err = curl_errno($ch);
            return $err === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 내부 구현
    // ─────────────────────────────────────────────────────────────

    /**
     * cURL HTTP 요청
     * 성공: 응답 배열 반환
     * 실패: TossApiException throw
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            throw new TossApiException('토스페이먼츠 API 키가 설정되지 않았습니다. .env의 TOSS_SECRET_KEY를 확인하세요.');
        }

        $url       = $this->baseUrl . $path;
        $authToken = base64_encode($this->secretKey . ':');
        $headers   = [
            'Authorization: Basic ' . $authToken,
            'Content-Type: application/json',
        ];

        Log::debug('[Toss][' . $method . '] ' . $path, array_filter(['body' => $data ?: null]));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        if ($curlErr) {
            throw new TossApiException('토스 API 연결 실패: ' . $curlErr);
        }

        $decoded = json_decode($raw, true);

        if ($httpCode >= 400) {
            $code    = $decoded['code']    ?? 'UNKNOWN';
            $message = $decoded['message'] ?? '알 수 없는 오류';
            Log::error('[Toss] API 오류', ['http' => $httpCode, 'code' => $code, 'msg' => $message]);
            throw new TossApiException("[{$code}] {$message}", $httpCode);
        }

        Log::debug('[Toss] 응답', ['status' => $httpCode, 'keys' => array_keys($decoded ?? [])]);

        return $decoded ?? [];
    }

    /** 웹훅 서명 검증 (HMAC-SHA256) */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('toss.webhook_secret', '');
        if (empty($secret)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
