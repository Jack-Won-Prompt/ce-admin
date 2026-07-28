<?php
// app/Services/Nice/NiceIdentityService.php

namespace App\Services\Nice;

use App\Models\NiceSetting;
use App\Models\PrescriptionConsent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * NICE 본인확인 서비스(신규 REST API) 연동.
 *
 * 흐름:
 *   1) 기관 access_token 발급 (client_credentials, 캐시)
 *   2) 암호화 토큰 발급 → req_dtim/req_no/token_val 로 대칭키(key/iv/hmac) 유도
 *   3) 요청 데이터(returnurl 등) AES128-CBC 암호화 + HMAC → 표준창 파라미터 생성
 *      · key/iv/hmac 은 캐시에 보관(콜백에서 복호화에 필요)
 *   4) 표준창 완료 후 returnurl 콜백의 enc_data 를 보관 키로 복호화 → 본인확인 결과
 *
 * 자격증명(client_id/secret/productID)이 없으면 enabled()=false 이며, 이 서비스는 호출되지 않는다.
 * 자격증명은 관리자 설정(nice_settings 테이블)에 저장되며, 인스턴스 생성 시 config('nice.*')로 반영된다.
 * DB 행이 없거나 접근 불가면 .env 설정이 그대로 쓰인다.
 */
class NiceIdentityService
{
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
     * 관리자 설정 화면의 '연결 테스트' — 자격증명으로 기관토큰·암호화토큰 발급까지 실제로 시도한다.
     * 캐시를 건드리지 않고 새로 발급해(진행 중인 본인확인에 영향 없음) 자격증명 자체를 검증한다.
     *
     * @return array{ok:bool, message:string, detail:string}
     */
    public function testConnection(): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => '자격증명(client_id / client_secret / productID)을 모두 입력해 주세요.', 'detail' => ''];
        }

        try {
            [$access] = $this->fetchAccessToken();
            $crypto   = $this->issueCryptoToken($access);

            return [
                'ok'      => true,
                'message' => '연결 성공 — 기관토큰·암호화토큰 발급을 확인했습니다.',
                'detail'  => 'site_code: '.$crypto['site_code'].' / token_version_id: '.$crypto['token_version_id'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'detail' => ''];
        }
    }

    /**
     * 표준창 호출용 파라미터 생성. 반환값을 클라이언트 폼으로 POST 하면 NICE 팝업이 뜬다.
     *
     * @return array{standard_url:string, token_version_id:string, enc_data:string, integrity_value:string}
     */
    public function startVerification(PrescriptionConsent $consent, string $returnUrl): array
    {
        $access = $this->accessToken();
        $crypto = $this->issueCryptoToken($access);

        // 대칭키 유도: base64( SHA256(req_dtim + req_no + token_val) )
        $hash    = base64_encode(hash('sha256', $crypto['req_dtim'].$crypto['req_no'].$crypto['token_val'], true));
        $key     = substr($hash, 0, 16);
        $iv      = substr($hash, -16);
        $hmacKey = substr($hash, 0, 32);

        // 요청 데이터 (표준창 동작 지정)
        $reqData = [
            'requestno'   => $crypto['req_no'],
            'returnurl'   => $returnUrl,
            'sitecode'    => $crypto['site_code'],
            'methodtype'  => 'get',            // 결과를 returnurl 로 GET 전달
            'popupyn'     => 'Y',
            'receivedata' => $consent->token,  // 콜백에서 되돌려받을 식별자(에코)
        ];

        $encData   = base64_encode(openssl_encrypt(json_encode($reqData, JSON_UNESCAPED_SLASHES), 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv));
        $integrity = base64_encode(hash_hmac('sha256', $encData, $hmacKey, true));

        // 콜백 복호화를 위해 대칭키 자료 보관(왕복 시간 동안만)
        Cache::put($this->cacheKey($consent), [
            'key'               => $key,
            'iv'                => $iv,
            'hmac_key'          => $hmacKey,
            'token_version_id'  => $crypto['token_version_id'],
            'req_no'            => $crypto['req_no'],
        ], now()->addMinutes((int) config('nice.crypto_ttl_minutes', 10)));

        return [
            'standard_url'     => config('nice.standard_url'),
            'token_version_id' => $crypto['token_version_id'],
            'enc_data'         => $encData,
            'integrity_value'  => $integrity,
        ];
    }

    /**
     * returnurl 콜백 처리: enc_data 를 보관 키로 복호화해 본인확인 결과를 반환.
     *
     * @param  array  $params  콜백으로 넘어온 token_version_id / enc_data / integrity_value
     * @return array  본인확인 결과(정규화)
     */
    public function handleCallback(PrescriptionConsent $consent, array $params): array
    {
        $store = Cache::get($this->cacheKey($consent));
        if (!$store) {
            throw new RuntimeException('본인확인 세션이 만료되었습니다. 다시 시도해 주세요.');
        }
        Cache::forget($this->cacheKey($consent));

        $encData   = (string) ($params['enc_data'] ?? '');
        $integrity = (string) ($params['integrity_value'] ?? '');
        if ($encData === '') {
            throw new RuntimeException('본인확인 응답이 비어 있습니다.');
        }

        // 무결성 검증(변조 방지)
        $calc = base64_encode(hash_hmac('sha256', $encData, $store['hmac_key'], true));
        if (!hash_equals($calc, $integrity)) {
            throw new RuntimeException('본인확인 응답 무결성 검증에 실패했습니다.');
        }

        $plain = openssl_decrypt(base64_decode($encData), 'aes-128-cbc', $store['key'], OPENSSL_RAW_DATA, $store['iv']);
        if ($plain === false) {
            throw new RuntimeException('본인확인 응답 복호화에 실패했습니다.');
        }

        $r = json_decode($plain, true) ?: [];

        $resultCode = (string) ($r['resultcode'] ?? '');
        if ($resultCode !== '0000') {
            throw new RuntimeException('본인확인이 완료되지 않았습니다. (코드 '.$resultCode.')');
        }

        // 요청 시 실어 보낸 식별자(에코)와 요청번호가 이 동의 건의 것인지 확인 — 응답 바꿔치기 방지
        $echo = (string) ($r['receivedata'] ?? '');
        if ($echo !== '' && !hash_equals($consent->token, $echo)) {
            throw new RuntimeException('본인확인 응답이 요청과 일치하지 않습니다.');
        }
        $respReqNo = (string) ($r['requestno'] ?? $r['reqno'] ?? '');
        if ($respReqNo !== '' && !hash_equals((string) $store['req_no'], $respReqNo)) {
            throw new RuntimeException('본인확인 응답이 요청과 일치하지 않습니다.');
        }

        // 필드 정규화(문서 버전에 따라 키 대소문자/명칭 차이가 있어 폭넓게 수용)
        return [
            'name'        => (string) ($r['utf8_name'] ?? $r['name'] ?? ''),
            'birthdate'   => preg_replace('/\D/', '', (string) ($r['birthdate'] ?? '')),  // YYYYMMDD
            'gender'      => (string) ($r['gender'] ?? ''),
            'nation'      => (string) ($r['nationalinfo'] ?? ''),
            'mobileco'    => (string) ($r['mobileco'] ?? ''),
            'mobile'      => (string) ($r['mobileno'] ?? ''),
            'authtype'    => (string) ($r['authtype'] ?? ''),
            'response_no' => (string) ($r['responseno'] ?? ''),
            'ci'          => (string) ($r['ci'] ?? ''),
            'di'          => (string) ($r['di'] ?? ''),
            'receivedata' => (string) ($r['receivedata'] ?? ''),
        ];
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * 기관 access_token (client_credentials) — 응답의 expires_in 만큼 캐시.
     * 캐시 키에 client_id 를 섞어, 자격증명을 바꾸면 이전 토큰이 재사용되지 않게 한다.
     */
    private function accessToken(): string
    {
        $cacheKey = $this->accessTokenCacheKey();
        $cached   = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $ttl] = $this->fetchAccessToken();
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * 기관 access_token 을 NICE 에서 새로 발급받는다 (캐시 미사용).
     *
     * @return array{0:string, 1:int}  [access_token, 캐시 TTL(초)]
     */
    private function fetchAccessToken(): array
    {
        $res = Http::asForm()
            ->timeout((int) config('nice.http_timeout', 10))
            ->withBasicAuth(config('nice.client_id'), config('nice.client_secret'))
            ->post(config('nice.api_base').'/digital/niceid/oauth/oauth/token', [
                'grant_type' => 'client_credentials',
                'scope'      => 'default',
            ]);

        $token = data_get($res->json(), 'dataBody.access_token');
        if (!$res->successful() || !$token) {
            Log::error('NICE access_token 발급 실패', ['status' => $res->status(), 'body' => $res->body()]);
            throw new RuntimeException('본인확인 서비스 연결에 실패했습니다. (토큰 발급)');
        }

        // expires_in(초) 이 오면 그대로 쓰되, 만료 직전 재사용을 피하려고 60초 여유를 둔다.
        $expiresIn = (int) data_get($res->json(), 'dataBody.expires_in', 0);

        return [$token, $expiresIn > 120 ? $expiresIn - 60 : 3600];
    }

    /** 자격증명을 바꾸면 이전 토큰이 재사용되지 않도록 client_id 를 키에 섞는다. (무효화는 NiceSetting) */
    private function accessTokenCacheKey(): string
    {
        return 'nice:access_token:'.md5((string) config('nice.client_id'));
    }

    /**
     * 암호화 토큰 발급.
     * @return array{site_code:string, token_val:string, token_version_id:string, req_no:string, req_dtim:string}
     */
    private function issueCryptoToken(string $accessToken): array
    {
        $reqDtim = date('YmdHis');
        $reqNo   = bin2hex(random_bytes(12));          // 24자 유니크
        $ts      = (string) time();

        $auth = base64_encode($accessToken.':'.$ts.':'.config('nice.client_id'));

        $res = Http::withHeaders([
                'Authorization' => 'bearer '.$auth,
                'client_id'     => config('nice.client_id'),
                'productID'     => config('nice.product_id'),
                'Content-Type'  => 'application/json',
            ])
            ->timeout((int) config('nice.http_timeout', 10))
            ->post(config('nice.api_base').'/digital/niceid/api/v1.0/common/crypto/token', [
                'dataHeader' => ['CNTY_CD' => 'ko'],
                'dataBody'   => [
                    'req_dtim' => $reqDtim,
                    'req_no'   => $reqNo,
                    'enc_mode' => '1',
                ],
            ]);

        $body     = $res->json();
        $tokenVal = data_get($body, 'dataBody.token_val');
        $siteCode = data_get($body, 'dataBody.site_code');
        $tokenVer = data_get($body, 'dataBody.token_version_id');

        if (!$res->successful() || !$tokenVal || !$siteCode || !$tokenVer) {
            Log::error('NICE 암호화 토큰 발급 실패', ['status' => $res->status(), 'body' => $res->body()]);
            throw new RuntimeException('본인확인 서비스 연결에 실패했습니다. (암호화 토큰)');
        }

        return [
            'site_code'        => $siteCode,
            'token_val'        => $tokenVal,
            'token_version_id' => $tokenVer,
            'req_no'           => $reqNo,
            'req_dtim'         => $reqDtim,
        ];
    }

    private function cacheKey(PrescriptionConsent $consent): string
    {
        return 'nice:req:'.$consent->token;
    }
}
