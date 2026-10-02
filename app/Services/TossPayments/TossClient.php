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
    /**
     * 은행 코드 → 이름.
     *
     * **토스가 쓰는 두 자리 코드가 정본이다** (2026-10-02 토스 문서로 맞췄다).
     * 응답의 `virtualAccount.bankCode` 가 두 자리로 오는데 여기에는 세 자리만
     * 있어서, 화면과 문자에 「11」ㆍ「06」ㆍ「20」이 그대로 찍혔다.
     *
     * 영문 코드도 바로잡았다. 예전에 적어 둔 `WB`ㆍ`KB`ㆍ`NH` 따위는 우리가
     * 지어낸 이름이라 저쪽이 모른다 — 우리은행을 `WB` 로 보냈다가
     * 「[INVALID_BANK] 유효하지 않은 은행입니다」로 거절당했다.
     *
     * 세 자리(금융결제원 표준)도 남겨 둔다 — 다른 자리에서 들어올 수 있다.
     */
    public const BANK_NAMES = [
        // 토스가 주고받는 두 자리 코드
        '02' => '산업은행',   '03' => '기업은행',   '06' => '국민은행',
        '07' => '수협은행',   '11' => '농협은행',   '12' => '단위농협',
        '20' => '우리은행',   '23' => 'SC제일은행', '27' => '씨티은행',
        '30' => '수협중앙회', '31' => '대구은행',   '32' => '부산은행',
        '34' => '광주은행',   '35' => '제주은행',   '37' => '전북은행',
        '39' => '경남은행',   '45' => '새마을금고', '48' => '신협',
        '50' => '저축은행중앙회', '54' => '홍콩상하이은행', '60' => 'Bank of America',
        '64' => '산림조합',   '71' => '우체국예금보험', '81' => '하나은행',
        '88' => '신한은행',   '89' => '케이뱅크',   '90' => '카카오뱅크',
        '92' => '토스뱅크',

        // 토스가 받는 영문 코드
        'KDB'  => '산업은행',  'IBK'     => '기업은행',  'KOOKMIN'   => '국민은행',
        'SUHYUP' => '수협은행', 'NONGHYEOP' => '농협은행', 'WOORI'   => '우리은행',
        'SC'   => 'SC제일은행','CITI'    => '씨티은행',  'DAEGU'     => '대구은행',
        'BUSAN' => '부산은행', 'GWANGJU' => '광주은행',  'JEONBUK'   => '전북은행',
        'JEJU' => '제주은행',  'KYONGNAM' => '경남은행', 'SAEMAUL'   => '새마을금고',
        'SHINHYEOP' => '신협', 'KBANK'   => '케이뱅크',  'KAKAOBANK' => '카카오뱅크',
        'TOSSBANK' => '토스뱅크', 'HANA'  => '하나은행',  'SHINHAN'   => '신한은행',
        'POST' => '우체국예금보험',

        // 세 자리(금융결제원 표준) — 다른 자리에서 들어올 수 있다
        '002' => '산업은행',  '003' => '기업은행',  '004' => '국민은행',
        '007' => '수협은행',  '011' => '농협은행',  '020' => '우리은행',
        '023' => 'SC제일은행','027' => '씨티은행',  '031' => '대구은행',
        '032' => '부산은행',  '034' => '광주은행',  '035' => '전북은행',
        '037' => '전남은행',  '039' => '경남은행',  '045' => '새마을금고',
        '048' => '신협',      '050' => '저축은행중앙회', '064' => '산림조합',
        '071' => '우체국예금보험', '081' => '하나은행', '088' => '신한은행',
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
            throw new TossApiException('토스페이먼츠 API 키가 설정되지 않았습니다. .env의 TOSS_SECRET_KEY를 확인하십시오.');
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

    /**
     * 웹훅 서명 검증 — 서명이 포함된 웹훅(payout.changed / seller.changed 등)용.
     * 가상계좌 입금 웹훅에는 서명이 없으므로 이 메서드 대신 API 재조회로 검증한다.
     *
     * 토스 검증 절차:
     *  1) HMAC-SHA256("{rawBody}:{transmissionTime}", 보안키) → 원본(raw) 바이트
     *  2) 헤더값 "v1:<base64>,<base64>" 에서 v1: 뒤 값들을 base64 디코딩
     *  3) 1)의 해시가 2)의 디코딩 값 중 하나와 일치하면 정상 (보안키 교체 대비 2개)
     *
     * @param string $signature        tosspayments-webhook-signature 헤더 전체값
     * @param string $transmissionTime tosspayments-webhook-transmission-time 헤더값
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, string $transmissionTime): bool
    {
        $secret = config('toss.webhook_secret', '');
        if ($secret === '' || $signature === '' || $transmissionTime === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $rawBody . ':' . $transmissionTime, $secret, true);
        $values   = preg_replace('/^v1:/', '', trim($signature));

        foreach (preg_split('/[:\s,]+/', $values, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $decoded = base64_decode($part, true);
            if ($decoded !== false && hash_equals($decoded, $computed)) {
                return true;
            }
        }

        return false;
    }
}
