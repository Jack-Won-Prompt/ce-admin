<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * CE Admin 웹 SSO(Microsoft Entra ID · OIDC) 설정.
 *
 * **`.env` 에 두지 않는다**(지시서 LTL-UNICORN-20260909-02 §3). Tenant IDㆍClient IDㆍ
 * Client SecretㆍRedirect URI 는 코드에도 저장소에도 남기지 않고, 이미 있는 설정
 * 체계(`settings` 표 · App\Models\Setting)에 담아 관리 화면에서 넣는다.
 * Secret 은 Setting 이 Crypt 로 암호화해 담는다 — 평문은 DB 에 남지 않는다.
 *
 * **시험과 운영을 갈라 담는다** (2026-09-29 지시).
 *
 * 팝빌ㆍ토스ㆍ위드웍스가 그러하듯 두 벌을 함께 담아 두고 어느 쪽을 쓸지 고른다.
 * 한 벌만 두면 운영으로 넘길 때 시험 값을 지워야 하고, 되돌릴 일이 생기면 그것을
 * 다시 받아 넣어야 한다 — 그 사이에 아무도 들어오지 못한다.
 *
 * 담기는 이름은 «test_tenant_id»ㆍ«live_tenant_id» 처럼 환경이 앞에 붙는다.
 * 고른 환경은 «env», 켜고 끄는 것은 «enabled» 로 환경과 상관없이 하나다 —
 * 「지금 SSO 를 쓰는가」와 「어느 쪽 자격으로 쓰는가」는 다른 물음이다.
 *
 * 값은 **요청 때 읽는다**. config/services.php 의 정적 값에 기대지 않는다 —
 * 관리 화면에서 고친 값이 다음 요청부터 곧바로 들어야 한다.
 * 매 요청 여러 줄을 읽는 것이 아까워 캐시에 담고, 저장할 때 그 캐시를 버린다.
 */
final class SsoSettings
{
    /** settings 표에서 이 무리를 쓴다 */
    public const GROUP = 'sso_web';

    /** 캐시 열쇠 — 저장하면 버린다 */
    private const CACHE_KEY = 'sso.web.config';

    /** 고를 수 있는 환경 */
    public const ENVS = [
        'test' => '테스트',
        'live' => '운영',
    ];

    /** 환경마다 따로 담는 칸 */
    public const FIELDS = [
        'tenant_id'     => ['label' => 'Tenant ID',     'secret' => false],
        'client_id'     => ['label' => 'Client ID',     'secret' => false],
        'client_secret' => ['label' => 'Client Secret', 'secret' => true],
        'redirect_uri'  => ['label' => 'Redirect URI',  'secret' => false],
    ];

    /** 환경과 상관없이 하나뿐인 칸 */
    public const COMMON = [
        'enabled' => ['label' => '사용 여부'],
        'env'     => ['label' => '사용 환경'],
    ];

    /** 켜려면 반드시 채워져 있어야 하는 것 */
    public const REQUIRED = ['tenant_id', 'client_id', 'client_secret', 'redirect_uri'];

    /** 고르지 않았을 때 — 시험으로 둔다. 운영 자격이 모르는 새 쓰이면 안 된다. */
    public const DEFAULT_ENV = 'test';

    /**
     * 담긴 것 모두 — 두 환경 + 공통.
     *
     * @return array{env:string, enabled:bool, test:array, live:array}
     */
    public static function raw(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $rows = Setting::where('group', self::GROUP)->get()->keyBy('key');
            } catch (\Throwable $e) {
                /* 표가 아직 없는 서버(배포 직후 마이그레이션 전)에서도 부팅은 되어야
                   한다. 값이 없으면 SSO 는 꺼진 것으로 본다 — 기존 로그인은 그대로다. */
                $rows = collect();
            }

            $get = fn (string $k) => $rows->get($k)?->plainValue();

            $한벌 = function (string $env) use ($get) {
                $것 = [];

                foreach (array_keys(self::FIELDS) as $k) {
                    $것[$k] = $get($env . '_' . $k);
                }

                return $것;
            };

            $고른것 = (string) ($get('env') ?: self::DEFAULT_ENV);

            return [
                'env'     => isset(self::ENVS[$고른것]) ? $고른것 : self::DEFAULT_ENV,
                'enabled' => filter_var($get('enabled'), FILTER_VALIDATE_BOOLEAN),
                'test'    => $한벌('test'),
                'live'    => $한벌('live'),
            ];
        });
    }

    /**
     * 지금 쓰는 한 벌 — 원문이다. Secret 을 화면에 실을 때는 maskedSecret() 을 쓴다.
     *
     * 부르는 쪽(로그인ㆍ로그아웃)은 예전 그대로 이것만 본다. 어느 환경을 고르든
     * 같은 이름으로 같은 뜻의 값이 온다 — 고르는 일은 여기서 끝난다.
     *
     * @param  string|null  $env  보고 싶은 환경. 비우면 고른 환경.
     * @return array{tenant_id:?string, client_id:?string, client_secret:?string, redirect_uri:?string, enabled:bool, env:string}
     */
    public static function all(?string $env = null): array
    {
        $담긴것 = self::raw();
        $볼것   = ($env !== null && isset(self::ENVS[$env])) ? $env : $담긴것['env'];

        return $담긴것[$볼것] + [
            'enabled' => $담긴것['enabled'],
            'env'     => $볼것,
        ];
    }

    /** 지금 고른 환경 */
    public static function env(): string
    {
        return self::raw()['env'];
    }

    /** 지금 고른 환경을 사람이 읽을 말로 */
    public static function envLabel(): string
    {
        return self::ENVS[self::env()] ?? self::env();
    }

    /** SSO 로 로그인할 수 있는가 — 켜져 있고, **고른 환경에** 필요한 값이 다 있을 때만 */
    public static function usable(): bool
    {
        $v = self::all();

        if (! $v['enabled']) {
            return false;
        }

        foreach (self::REQUIRED as $k) {
            if (blank($v[$k])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 화면에 실을 Secret — 뒤 넉 자만 남긴다.
     *
     * 원문은 내려보내지 않는다(write-only). 담당자가 「담겨는 있는가」와 「내가 넣은
     * 그것이 맞는가」를 가리는 데에는 뒤 넉 자로 넉넉하다.
     */
    public static function maskedSecret(?string $env = null): ?string
    {
        $s = (string) (self::all($env)['client_secret'] ?? '');

        if ($s === '') {
            return null;
        }

        return '••••••••' . mb_substr($s, -4);
    }

    /**
     * 한 환경의 값을 담는다. 보내 온 이름만 손댄다 — 보내지 않은 칸은 그대로 둔다.
     *
     * Secret 은 **빈 값으로 덮어쓰지 않는다.** 화면이 원문을 들고 있지 않으므로,
     * 다른 칸만 고쳐 저장할 때마다 Secret 이 지워지면 매번 다시 받아 넣어야 한다.
     *
     * @param  array<string, mixed>  $values
     * @return array<string>  실제로 바뀐 칸 이름 — 감사로그에 남긴다(값은 남기지 않는다)
     */
    public static function put(array $values, string $env): array
    {
        if (! isset(self::ENVS[$env])) {
            throw new \InvalidArgumentException('모르는 환경입니다 — ' . $env);
        }

        $바뀐것 = [];

        foreach (self::FIELDS as $key => $f) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $new = $values[$key];

            if ($f['secret'] && ($new === null || $new === '')) {
                continue;   // 빈 채로 보낸 Secret 은 「고치지 않겠다」는 뜻이다
            }

            if (self::한칸담기($env . '_' . $key, $new, (bool) $f['secret'])) {
                $바뀐것[] = $key;
            }
        }

        self::forget();

        return $바뀐것;
    }

    /**
     * 공통 칸(켜고 끄기ㆍ어느 환경을 쓸까)을 담는다.
     *
     * @param  array<string, mixed>  $values
     * @return array<string>
     */
    public static function putCommon(array $values): array
    {
        $바뀐것 = [];

        foreach (array_keys(self::COMMON) as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if (self::한칸담기($key, $values[$key], false)) {
                $바뀐것[] = $key;
            }
        }

        self::forget();

        return $바뀐것;
    }

    /** 한 칸을 담는다 — 값이 그대로면 손대지 않는다(자취를 어지럽히지 않게) */
    private static function 한칸담기(string $key, mixed $new, bool $secret): bool
    {
        $row   = Setting::firstOrNew(['group' => self::GROUP, 'key' => $key]);
        $old   = $row->exists ? $row->plainValue() : null;
        $plain = is_bool($new) ? ($new ? '1' : '0') : (string) $new;

        if ($old === $plain) {
            return false;
        }

        $row->setPlainValue($plain, $secret);
        $row->save();

        return true;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
