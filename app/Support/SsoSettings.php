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
 * 값은 **요청 때 읽는다**. config/services.php 의 정적 값에 기대지 않는다 —
 * 관리 화면에서 고친 값이 다음 요청부터 곧바로 들어야 한다.
 * 매 요청 네 줄을 읽는 것이 아까워 캐시에 담고, 저장할 때 그 캐시를 버린다.
 */
final class SsoSettings
{
    /** settings 표에서 이 무리를 쓴다 */
    public const GROUP = 'sso_web';

    /** 캐시 열쇠 — 저장하면 버린다 */
    private const CACHE_KEY = 'sso.web.config';

    /** 다루는 칸과 그 성질 */
    public const FIELDS = [
        'tenant_id'     => ['label' => 'Tenant ID',     'secret' => false],
        'client_id'     => ['label' => 'Client ID',     'secret' => false],
        'client_secret' => ['label' => 'Client Secret', 'secret' => true],
        'redirect_uri'  => ['label' => 'Redirect URI',  'secret' => false],
        'enabled'       => ['label' => '사용 여부',      'secret' => false],
    ];

    /** 켜려면 반드시 채워져 있어야 하는 것 */
    public const REQUIRED = ['tenant_id', 'client_id', 'client_secret', 'redirect_uri'];

    /**
     * 지금 값 한 벌 — 원문이다. Secret 을 화면에 실을 때는 masked() 를 쓴다.
     *
     * @return array{tenant_id:?string, client_id:?string, client_secret:?string, redirect_uri:?string, enabled:bool}
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = [];

            try {
                $rows = Setting::where('group', self::GROUP)->get()->keyBy('key');
            } catch (\Throwable $e) {
                /* 표가 아직 없는 서버(배포 직후 마이그레이션 전)에서도 부팅은 되어야
                   한다. 값이 없으면 SSO 는 꺼진 것으로 본다 — 기존 로그인은 그대로다. */
                $rows = collect();
            }

            $get = fn (string $k) => $rows->get($k)?->plainValue();

            return [
                'tenant_id'     => $get('tenant_id'),
                'client_id'     => $get('client_id'),
                'client_secret' => $get('client_secret'),
                'redirect_uri'  => $get('redirect_uri'),
                'enabled'       => filter_var($get('enabled'), FILTER_VALIDATE_BOOLEAN),
            ];
        });
    }

    /** SSO 로 로그인할 수 있는가 — 켜져 있고 필요한 값이 다 있을 때만 */
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
    public static function maskedSecret(): ?string
    {
        $s = (string) (self::all()['client_secret'] ?? '');

        if ($s === '') {
            return null;
        }

        return '••••••••' . mb_substr($s, -4);
    }

    /**
     * 값을 담는다. 보내 온 이름만 손댄다 — 보내지 않은 칸은 그대로 둔다.
     *
     * Secret 은 **빈 값으로 덮어쓰지 않는다.** 화면이 원문을 들고 있지 않으므로,
     * 다른 칸만 고쳐 저장할 때마다 Secret 이 지워지면 매번 다시 받아 넣어야 한다.
     *
     * @param  array<string, mixed>  $values
     * @return array<string>  실제로 바뀐 칸 이름 — 감사로그에 남긴다(값은 남기지 않는다)
     */
    public static function put(array $values): array
    {
        $바뀐것 = [];

        foreach (self::FIELDS as $key => $f) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $new = $values[$key];

            if ($f['secret'] && ($new === null || $new === '')) {
                continue;   // 빈 채로 보낸 Secret 은 「고치지 않겠다」는 뜻이다
            }

            $row = Setting::firstOrNew(['group' => self::GROUP, 'key' => $key]);
            $old = $row->exists ? $row->plainValue() : null;

            $plain = is_bool($new) ? ($new ? '1' : '0') : (string) $new;
            if ($old === $plain) {
                continue;
            }

            $row->setPlainValue($plain, (bool) $f['secret']);
            $row->save();

            $바뀐것[] = $key;
        }

        self::forget();

        return $바뀐것;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
