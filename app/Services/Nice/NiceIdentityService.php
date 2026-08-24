<?php
// app/Services/Nice/NiceIdentityService.php

namespace App\Services\Nice;

use App\Models\NiceSetting;
use App\Models\PrescriptionConsent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * NICE 통합인증(IDO/INTC v1.0) 연동.
 *
 * 예전에는 CheckPlus 표준창이었다. 암호화 토큰을 받아 대칭키를 손으로 유도하고, 폼을
 * 만들어 표준창으로 POST 하고, 돌아온 enc_data 를 풀었다. 통합인증은 그 자리를
 * 세 번의 REST 호출로 바꾼다 — 우리가 만들 폼도, 들고 있을 표준창 주소도 없다.
 *
 * 흐름:
 *   1) 접근토큰   POST /ido/intc/{version}/auth/token
 *      Authorization: Basic base64url(client_id:client_secret)
 *      → access_token(24시간) · ticket · iterators
 *   2) 인증 주소  POST /ido/intc/{version}/auth/url   (Bearer access_token)
 *      → auth_url(팝업으로 열 주소) · transaction_id
 *   3) 사용자가 인증을 마치면 return_url 로 web_transaction_id 가 온다
 *   4) 인증 결과  POST /ido/intc/{version}/auth/result (Bearer access_token)
 *      → enc_data · integrity_value
 *
 * 키는 우리가 만들지 않고 유도한다 — PBKDF2(sha256, ticket, salt=transaction_id,
 * iterators, 64byte)를 base64url 로 적은 문자열에서, 앞 32자가 대칭키이고
 * 48번째부터 32자가 무결성키다(NICE 가이드 3.1). 그 문자열을 다시 디코딩하지 않는다.
 *
 * 결과는 AES-256-GCM 이다. base64url 로 푼 앞 16byte 가 IV, 끝 16byte 가 인증 태그다.
 *
 * 자격증명이 없으면 enabled()=false 이며 이 서비스는 호출되지 않는다. 자격증명은
 * 관리자 설정(nice_settings)에 있고, 인스턴스를 만들 때 config('nice.*') 로 반영된다.
 */
class NiceIdentityService
{
    /** 성공 결과코드 — 세 API 가 모두 이 값을 쓴다 */
    private const OK = '0000';

    public function __construct()
    {
        NiceSetting::applyToConfig();
    }

    public function enabled(): bool
    {
        return (bool) config('nice.enabled');
    }

    /** 서명 전 본인확인을 강제하는가 (자격증명 미설정 시 항상 false). */
    public function enforce(): bool
    {
        return $this->enabled() && (bool) config('nice.enforce');
    }

    /**
     * 관리자 설정 화면의 「연결 테스트」.
     *
     * 접근토큰까지만 받아 본다. 인증 주소까지 받으면 쓰지도 않을 거래가 NICE 쪽에
     * 하나 열린다 — 자격증명이 맞는지는 토큰으로 충분히 가려진다.
     *
     * @return array{ok:bool, message:string, detail:string}
     */
    public function testConnection(): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => '자격증명(client_id / client_secret)을 모두 입력해 주세요.', 'detail' => ''];
        }

        try {
            $t = $this->fetchToken();

            return [
                'ok'      => true,
                'message' => '연결 성공 — 접근토큰을 받았습니다.',
                'detail'  => '유효기간 '.date('Y-m-d H:i', $t['expires_at']).' · iterators '.$t['iterators'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'detail' => ''];
        }
    }

    /**
     * 표준창을 열 주소를 받아 온다. 이 주소를 팝업으로 열면 NICE 인증창이 뜬다.
     *
     * 이 거래에 딸린 것(요청번호ㆍ거래번호ㆍticketㆍiterators)은 캐시에 둔다 —
     * 돌아왔을 때 결과를 풀 열쇠를 그것으로 다시 유도한다.
     *
     * @return array{auth_url:string}
     */
    public function startVerification(PrescriptionConsent $consent, string $returnUrl): array
    {
        $token = $this->token();
        $reqNo = $this->newRequestNo();

        $res = $this->call('auth/url', [
            'request_no'  => $reqNo,
            'return_url'  => $returnUrl,
            /* 인증 수단. 기본은 휴대폰인증(M) 하나다 — 위임장에 서명할 사람이
               금융인증서까지 갖추고 있으리라 기대할 수 없다. */
            'svc_types'   => (array) config('nice.svc_types', ['M']),
            'method_type' => 'GET',
        ], $token['access_token']);

        $authUrl       = (string) ($res['auth_url'] ?? '');
        $transactionId = (string) ($res['transaction_id'] ?? '');

        if ($authUrl === '' || $transactionId === '') {
            throw new RuntimeException('본인확인 주소를 받지 못했습니다.');
        }

        Cache::put($this->cacheKey($consent), [
            'req_no'         => $reqNo,
            'transaction_id' => $transactionId,
            'ticket'         => $token['ticket'],
            'iterators'      => $token['iterators'],
        ], now()->addMinutes((int) config('nice.crypto_ttl_minutes', 10)));

        return ['auth_url' => $authUrl];
    }

    /**
     * 표준창이 돌려준 web_transaction_id 로 결과를 받아 푼다.
     *
     * 돌려주는 모양은 예전 그대로다 — 이 값을 쓰는 쪽(동의서 저장ㆍ환자 대조)은
     * 바뀐 것을 몰라도 된다.
     */
    public function handleCallback(PrescriptionConsent $consent, array $params): array
    {
        $store = Cache::get($this->cacheKey($consent));
        if (!$store) {
            throw new RuntimeException('본인확인 세션이 만료되었습니다. 다시 시도해 주세요.');
        }
        Cache::forget($this->cacheKey($consent));

        $webTxId = trim((string) ($params['web_transaction_id'] ?? ''));
        if ($webTxId === '') {
            throw new RuntimeException('본인확인이 완료되지 않았습니다.');
        }

        $res = $this->call('auth/result', [
            'request_no'         => $store['req_no'],
            'transaction_id'     => $store['transaction_id'],
            'web_transaction_id' => $webTxId,
        ], $this->token()['access_token']);

        $encData   = (string) ($res['enc_data'] ?? '');
        $integrity = (string) ($res['integrity_value'] ?? '');
        if ($encData === '') {
            throw new RuntimeException('본인확인 응답이 비어 있습니다.');
        }

        // 열쇠는 이 거래의 ticket · transaction_id · iterators 에서만 나온다
        $kdf     = $this->kdf($store['ticket'], $store['transaction_id'], (int) $store['iterators']);
        $key     = substr($kdf, 0, 32);
        $hmacKey = substr($kdf, 48, 32);

        // 무결성 검증(변조 방지) — 암호문 그대로를 HMAC 한다
        $calc = self::b64u(hash_hmac('sha256', $encData, $hmacKey, true));
        if (!hash_equals($calc, $integrity)) {
            throw new RuntimeException('본인확인 응답 무결성 검증에 실패했습니다.');
        }

        $plain = $this->decrypt($encData, $key);
        if ($plain === null) {
            throw new RuntimeException('본인확인 응답 복호화에 실패했습니다.');
        }

        $r = json_decode($plain, true) ?: [];

        return [
            'name'        => (string) ($r['name'] ?? ''),
            'birthdate'   => preg_replace('/\D/', '', (string) ($r['birthdate'] ?? '')),  // YYYYMMDD
            'gender'      => (string) ($r['gender'] ?? ''),
            'nation'      => (string) ($r['national_info'] ?? ''),
            'mobileco'    => (string) ($r['mobile_co'] ?? ''),
            'mobile'      => (string) ($r['mobile_no'] ?? ''),
            /* 통합인증은 「무엇으로 인증했는지」를 따로 주지 않는다. 우리가 무엇을
               열어 줬는지는 알고 있으니 그것을 적어 둔다(대개 휴대폰인증 하나다). */
            'authtype'    => implode(',', (array) config('nice.svc_types', ['M'])),
            'response_no' => $webTxId,
            'ci'          => (string) ($r['ci'] ?? ''),
            'di'          => (string) ($r['di'] ?? ''),
            'receivedata' => '',
        ];
    }

    // ──────────────────────────────────────────────────────────────

    /** 접근토큰 — 받아 둔 것이 살아 있으면 그것을 쓴다(24시간). */
    private function token(): array
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_array($cached) && !empty($cached['access_token'])) {
            return $cached;
        }

        $t = $this->fetchToken();

        /* 만료 30분 전에 버린다. 인증 도중에 토큰이 죽으면 결과를 받으러 갈 때
           빈손이 된다 — 그때는 다시 받을 수 없다(거래는 이미 끝났다). */
        $ttl = max(60, $t['expires_at'] - time() - 1800);
        Cache::put($this->tokenCacheKey(), $t, $ttl);

        return $t;
    }

    /**
     * 접근토큰을 새로 받는다. ticket · iterators 가 함께 온다 — 열쇠를 유도할 때
     * 쓰이므로 토큰과 한 몸으로 들고 있어야 한다.
     *
     * @return array{access_token:string, ticket:string, iterators:int, expires_at:int}
     */
    private function fetchToken(): array
    {
        $clientId     = (string) config('nice.client_id');
        $clientSecret = (string) config('nice.client_secret');

        $res = $this->request('auth/token', [
            'grant_type'  => 'client_credentials',
            'request_no'  => $this->newRequestNo(),
        ], 'Basic '.self::b64u($clientId.':'.$clientSecret));

        $expiresIn = (int) ($res['expires_in'] ?? 0);   // epoch milliseconds

        return [
            'access_token' => (string) ($res['access_token'] ?? ''),
            'ticket'       => (string) ($res['ticket'] ?? ''),
            'iterators'    => (int) ($res['iterators'] ?? 0),
            'expires_at'   => $expiresIn > 0 ? intdiv($expiresIn, 1000) : time() + 3600,
        ];
    }

    /** Bearer 토큰을 달고 부른다. */
    private function call(string $path, array $body, string $accessToken): array
    {
        return $this->request($path, $body, 'Bearer '.$accessToken);
    }

    /**
     * 한 번의 호출. 결과코드가 0000 이 아니면 그 자리에서 멈춘다 —
     * 실패를 성공처럼 흘려보내면 빈 값으로 서명이 진행된다.
     */
    private function request(string $path, array $body, string $authorization): array
    {
        $base    = rtrim((string) config('nice.api_base'), '/');
        $version = trim((string) config('nice.version', 'v1.0'), '/');
        $url     = "{$base}/ido/intc/{$version}/{$path}";

        try {
            $res = Http::withHeaders([
                    'Content-Type'   => 'application/json',
                    'Authorization'  => $authorization,
                    // 쓰는 운영체계와 개발언어를 적어 달라는 규약이다
                    'X-Intc-DevLang' => (PHP_OS_FAMILY === 'Windows' ? 'Windows' : 'Linux').'/PHP',
                ])
                ->timeout((int) config('nice.http_timeout', 10))
                ->post($url, $body);
        } catch (\Throwable $e) {
            Log::warning('[NICE] 호출 실패', ['path' => $path, 'error' => $e->getMessage()]);
            throw new RuntimeException('NICE 서버에 닿지 못했습니다.');
        }

        /* 실패도 대개 본문에 까닭이 적혀 온다(예: 1007 허용되지 않은 IP 접근).
           HTTP 상태만 보고 「400」이라 답하면, 정작 무엇이 문제인지는 로그를 뒤져야
           알 수 있다. 본문을 먼저 읽고, 읽을 것이 없을 때만 상태로 말한다. */
        $json = $res->json() ?: [];
        $code = (string) ($json['result_code'] ?? '');

        if ($code !== '' && $code !== self::OK) {
            $msg = (string) ($json['result_message'] ?? '알 수 없는 오류');
            Log::warning('[NICE] 결과코드', ['path' => $path, 'code' => $code, 'message' => $msg]);
            throw new RuntimeException("NICE 오류 [{$code}] {$msg}");
        }

        if (!$res->successful()) {
            Log::warning('[NICE] 응답 오류', ['path' => $path, 'status' => $res->status(), 'body' => $res->body()]);
            throw new RuntimeException('NICE 응답 오류 (HTTP '.$res->status().')');
        }

        if ($code === '') {
            throw new RuntimeException('NICE 응답을 읽지 못했습니다.');
        }

        return $json;
    }

    /**
     * 열쇠 자료. PBKDF2(sha256) 512bit 를 base64url 로 적은 문자열이며,
     * 그 문자열을 다시 디코딩하지 않고 글자 그대로 잘라 쓴다(NICE 가이드).
     */
    private function kdf(string $ticket, string $transactionId, int $iterators): string
    {
        return self::b64u(hash_pbkdf2('sha256', $ticket, $transactionId, $iterators, 64, true));
    }

    /** AES-256-GCM — 앞 16byte 가 IV, 끝 16byte 가 인증 태그다. */
    private function decrypt(string $encData, string $key): ?string
    {
        $raw = self::b64uDecode($encData);
        if (strlen($raw) <= 32) {
            return null;
        }

        $iv          = substr($raw, 0, 16);
        $cipherAndTag = substr($raw, 16);
        $cipherText  = substr($cipherAndTag, 0, -16);
        $tag         = substr($cipherAndTag, -16);

        $plain = openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? null : $plain;
    }

    /** 요청고유번호 — 규약이 20~50byte 를 요구한다. */
    private function newRequestNo(): string
    {
        return 'CE'.date('YmdHis').Str::lower(Str::random(14));   // 30자
    }

    private function cacheKey(PrescriptionConsent $consent): string
    {
        return 'nice:intc:'.$consent->id;
    }

    /** 자격증명을 바꾸면 앞서 받아 둔 토큰이 재사용되지 않게 한다. */
    private function tokenCacheKey(): string
    {
        return 'nice:intc:token:'.substr(sha1((string) config('nice.client_id')), 0, 16);
    }

    /** Base64 URL 인코딩(패딩 없음) — NICE 는 이 모양만 받는다. */
    private static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
