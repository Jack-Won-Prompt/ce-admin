<?php
// app/Models/NiceSetting.php
// NICE 본인확인 설정 (단일 행). 값이 없으면 config/nice.php(.env) 기본값으로 시드.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NiceSetting extends Model
{
    protected $fillable = [
        'client_id', 'client_secret', 'product_id',
        'enforce', 'match_name', 'match_birth',
        'api_base', 'standard_url', 'tested_at',
    ];

    protected $casts = [
        // 발급 비밀키 → 애플리케이션 레벨 암호화 저장
        'client_secret' => 'encrypted',
        'enforce'       => 'boolean',
        'match_name'    => 'boolean',
        'match_birth'   => 'boolean',
        'tested_at'     => 'datetime',
    ];

    // 직렬화(toArray/toJson) 시 비밀키 노출 방지
    protected $hidden = ['client_secret'];

    /** 단일 설정 행 반환 (없으면 config/nice.php 기본값으로 생성). */
    public static function current(): self
    {
        return static::firstOr(function () {
            return static::create([
                'client_id'     => (string) config('nice.client_id', ''),
                'client_secret' => (string) config('nice.client_secret', ''),
                'product_id'    => (string) config('nice.product_id', ''),
                'enforce'       => (bool) config('nice.enforce', false),
                'match_name'    => (bool) config('nice.match.require_name', true),
                'match_birth'   => (bool) config('nice.match.require_birth', true),
                'api_base'      => (string) config('nice.api_base', ''),
                'standard_url'  => (string) config('nice.standard_url', ''),
            ]);
        });
    }

    /**
     * 자격증명이 채워졌는가 (= 실제 연동 활성화 조건).
     *
     * 통합인증(IDO/INTC)은 client_id 와 client_secret 두 개로 부른다. productID 까지
     * 있어야 켜지게 두었더니, 새 자격증명만 받은 기관이 계속 「미설정」으로 남았다.
     */
    public function isConfigured(): bool
    {
        return trim((string) $this->client_id) !== ''
            && trim((string) $this->client_secret) !== '';
    }

    /**
     * DB 설정값으로 런타임 config('nice.*') 를 덮어써서
     * config() 를 읽는 서비스·컨트롤러·블레이드가 그대로 DB 값을 쓰게 한다.
     * DB 접근 불가(마이그레이션 전 등)면 아무것도 하지 않고 .env 설정을 유지한다.
     */
    public static function applyToConfig(): ?self
    {
        try {
            $s = static::current();
        } catch (\Throwable $e) {
            Log::warning('NiceSetting 조회 실패, config(.env) 기본값 사용', ['error' => $e->getMessage()]);
            return null;
        }

        $configured = $s->isConfigured();

        config([
            'nice.client_id'     => (string) $s->client_id,
            'nice.client_secret' => (string) $s->client_secret,
            'nice.product_id'    => (string) $s->product_id,
            'nice.enabled'       => $configured,
            // 자격증명이 없으면 강제하지 않는다(기존 서명 흐름 유지).
            'nice.enforce'       => $configured && $s->enforce,
            'nice.match'         => [
                'require_name'  => $s->match_name,
                'require_birth' => $s->match_birth,
            ],
        ]);

        /* 엔드포인트는 비워두면 config/.env 기본값을 그대로 쓴다.
           다만 표에 옛 CheckPlus 주소(svc.niceapi.co.kr)가 남아 있으면 못 본 척한다 —
           그 API 는 이제 부르지 않는데, 값이 남아 있다는 이유로 새 주소를 덮어 버리면
           배포하고도 계속 옛 서버로 가서 403 을 받는다. 실제로 그렇게 하루를 보냈다.
           설정 화면에서 새 주소로 고치면 그때부터는 그 값이 쓰인다. */
        $apiBase = trim((string) $s->api_base);
        if ($apiBase !== '' && !str_contains($apiBase, 'svc.niceapi.co.kr')) {
            config(['nice.api_base' => rtrim($apiBase, '/')]);
        }

        return $s;
    }

    /** 자격증명 변경 시 기관 access_token 캐시를 버린다. */
    public function forgetAccessToken(): void
    {
        Cache::forget('nice:access_token:'.md5((string) $this->client_id));
    }
}
