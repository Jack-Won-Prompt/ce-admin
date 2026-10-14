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
        return self::서명이맞는꼴($rawBody, $signature, $transmissionTime) !== null;
    }

    /**
     * 서명이 맞는 **꼴의 이름**을 돌려준다 — 맞는 것이 없으면 null.
     *
     * 2026-10-13 부터 토스가 서명 헤더를 붙여 보내기 시작했다(10-12 0건 → 10-13 6건 →
     * 10-14 40건). 그런데 우리가 쓰던 셈(`본문:시각`)이 저쪽 것과 달라 **붙어 온 것은
     * 모두 서명 불일치로 거절**되었고, 그 가운데 가상계좌 입금 통보가 있어 돈이 들어온
     * 건 셋이 장부에 남지 않았다.
     *
     * 어느 꼴이 맞는지 **지어내지 않는다.** 흔히 쓰는 꼴 몇 가지를 모두 맞춰 보고,
     * 맞는 것이 있으면 그 이름을 돌려준다 — 운영에 들어오는 진짜 서명이 답을 알려 준다.
     * 어느 것도 맞지 않으면 null 이고, 그래도 요청은 막지 않는다(TossWebhookController).
     * 주소에 박은 열쇠가 이미 걸러 냈고, 처리하는 자리가 토스에 다시 물어 확인한다.
     */
    public static function 서명이맞는꼴(string $rawBody, string $signature, string $transmissionTime): ?string
    {
        $secret = (string) config('toss.webhook_secret', '');

        if ($secret === '' || $signature === '') {
            return null;
        }

        /* ── 무엇에 HMAC 을 거는가 ─────────────────────────────────────
           흔히 쓰는 꼴을 모두 세운다. 어느 것이 맞는지 **지어내지 않고** 다 맞춰 본다. */
        $재료들 = ['본문만' => $rawBody];

        foreach (['' => '붙임', ':' => '쌍점', '.' => '점', '|' => '막대', "
" => '줄바꿈'] as $사이 => $이름) {
            $재료들['시각' . $이름 . '본문'] = $transmissionTime . $사이 . $rawBody;
            $재료들['본문' . $이름 . '시각'] = $rawBody . $사이 . $transmissionTime;
        }

        /* ── 열쇠를 어떻게 쓰는가 ──────────────────────────────────────
           우리 열쇠는 16진수 64자다 — **32바이트를 글자로 적어 둔 것**일 수 있다.
           그대로 쓰는 것과 디코딩해 쓰는 것이 다른 서명을 낳는다. */
        $열쇠들 = ['글자그대로' => $secret];

        if (ctype_xdigit($secret) && strlen($secret) % 2 === 0) {
            $열쇠들['16진수디코딩'] = (string) hex2bin($secret);
        }

        $풀린것 = base64_decode($secret, true);
        if ($풀린것 !== false && $풀린것 !== '' && base64_encode($풀린것) === $secret) {
            $열쇠들['base64디코딩'] = $풀린것;
        }

        /* ── 서명 값은 어떤 꼴로 오는가 ────────────────────────────── */
        $값 = preg_replace('/^(v1|sha256)[:=]/i', '', trim($signature));
        $조각들 = preg_split('/[:\s,;]+/', (string) $값, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($열쇠들 as $열쇠이름 => $열쇠) {
            foreach ($재료들 as $재료이름 => $재료) {
                $raw = hash_hmac('sha256', $재료, $열쇠, true);

                $꼴들 = [
                    'base64'     => base64_encode($raw),
                    'base64url'  => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
                    'hex소문자'  => bin2hex($raw),
                    'hex대문자'  => strtoupper(bin2hex($raw)),
                ];

                foreach ($조각들 as $조각) {
                    foreach ($꼴들 as $꼴이름 => $기대) {
                        if (hash_equals($기대, $조각)) {
                            return $열쇠이름 . ' · ' . $재료이름 . ' · ' . $꼴이름;
                        }
                    }

                    /* 받은 값이 base64 면 풀어서 날바이트로도 견준다 */
                    $풀림 = base64_decode($조각, true);
                    if ($풀림 !== false && strlen($풀림) === 32 && hash_equals($raw, $풀림)) {
                        return $열쇠이름 . ' · ' . $재료이름 . ' · base64(날바이트)';
                    }
                }
            }
        }

        return null;
    }
}
