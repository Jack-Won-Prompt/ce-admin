<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\SsoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Microsoft Entra ID(OIDC) 로그인.
 *
 * 지시서 LTL-UNICORN-20260909-02 §4·§5.
 *
 * **기존 로컬 로그인은 그대로 둔다.** 전환 정책이 아직 없어(§0-3), 이 길은 설정에서
 * 켜야만 열린다(sso.web.enabled · 기본 꺼짐). 꺼져 있으면 라우트가 404 로 답한다 —
 * 켜지도 않은 길이 열려 있는 것처럼 보이면 안 된다.
 *
 * 사람을 가리는 잣대는 **email(UPN)** 이다(§5-2). 등록되지 않은 사람은 들이지 않고
 * 안내만 한다 — 자동으로 만들어 줄지는 아직 정해지지 않았다(sso.web.jit_create).
 */
class EntraController extends Controller
{
    /** 인가 요청으로 보낸다 */
    public function redirect(Request $request): RedirectResponse
    {
        $this->켜졌나();

        return $this->provider()->redirect();
    }

    /**
     * 돌아온 code 로 토큰을 받아 로그인한다.
     *
     * state·nonce 는 Socialite 가 본다 — 끄지 않는다(§5-1).
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->켜졌나();

        try {
            $entra = $this->provider()->user();
        } catch (\Throwable $e) {
            Log::warning('[SSO] 토큰 교환에 실패했습니다', ['error' => $e->getMessage()]);
            $this->남긴다(null, 'sso_login_failed', '토큰 교환 실패');

            return redirect()->route('login')
                ->withErrors(['email' => 'SSO 로그인에 실패했습니다. 잠시 후 다시 시도해 주십시오.']);
        }

        $email = trim((string) ($entra->getEmail() ?: ($entra->user['upn'] ?? '')));

        if ($email === '') {
            Log::warning('[SSO] 토큰에 email·upn 이 없습니다', ['id' => $entra->getId()]);
            $this->남긴다(null, 'sso_login_failed', 'email(UPN) 없음');

            return redirect()->route('login')
                ->withErrors(['email' => '계정에 이메일(UPN)이 없어 로그인할 수 없습니다. 관리자에게 문의해 주십시오.']);
        }

        $user = User::where('email', $email)->first();

        /* 등록되지 않은 사람 — 기본은 들이지 않는다(§5-2).
           자동으로 만들어 줄지는 아직 정해지지 않아 설정으로 열어 둔다. */
        if (! $user) {
            if (! config('sso.web.jit_create', false)) {
                $this->남긴다(null, 'sso_login_denied', "미등록 계정 {$email}");

                return redirect()->route('login')->withErrors([
                    'email' => "이 계정({$email})은 CE Admin 에 등록되어 있지 않습니다. 관리자에게 등록을 요청해 주십시오.",
                ]);
            }

            $user = User::create([
                'name'      => $entra->getName() ?: $email,
                'email'     => $email,
                'password'  => bcrypt(\Illuminate\Support\Str::random(40)),
                'role'      => config('sso.web.jit_role', 'manager'),
                'is_active' => true,
            ]);

            activity()->performedOn($user)->log("SSO 첫 로그인으로 사용자를 만들었습니다 ({$email})");
        }

        if (property_exists($user, 'is_active') || isset($user->is_active)) {
            if (! $user->is_active) {
                $this->남긴다($user, 'sso_login_denied', '사용이 멈춘 계정');

                return redirect()->route('login')
                    ->withErrors(['email' => '사용이 멈춘 계정입니다. 관리자에게 문의해 주십시오.']);
            }
        }

        /* 역할은 Entra 의 App Roles(roles claim)가 정한다(§5-3).
           groups claim 은 쓰지 않는다 — 150개가 넘으면 빠지고 온다.
           매핑이 아직 정해지지 않아, 짝이 없으면 지금 역할을 그대로 둔다. */
        $this->역할을맞춘다($user, (array) ($entra->user['roles'] ?? []));

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $this->남긴다($user, 'sso_login', $email);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * IdP 가 보내는 front-channel logout.
     *
     * 세션이 있든 없든 200 으로 답한다 — iframe 안에서 불리므로 리디렉션이나
     * 오류를 돌려주면 저쪽 화면에 그것이 그대로 뜬다.
     */
    public function frontchannelLogout(Request $request): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->남긴다($user, 'sso_logout', 'front-channel');
        }

        return response('', 200)->header('Content-Type', 'text/plain');
    }

    /** 우리 쪽에서 시작하는 로그아웃 — IdP 에도 알린다 */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $this->남긴다($user, 'sso_logout', 'RP-initiated');
        }

        $tenant = SsoSettings::all()['tenant_id'] ?? null;

        if (! $tenant) {
            return redirect()->route('login');
        }

        return redirect()->away(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/logout"
            . '?post_logout_redirect_uri=' . urlencode(route('login'))
        );
    }

    // ──────────────────────────────────────────────────────

    /** 켜지지 않았으면 이 길은 없는 것이다 */
    private function 켜졌나(): void
    {
        abort_unless(SsoSettings::usable(), 404);
    }

    /**
     * 설정에 담긴 값으로 Socialite 를 세운다.
     *
     * config/services.php 의 정적 값을 쓰지 않는다(§3-3) — 관리 화면에서 고친 값이
     * 다음 요청부터 곧바로 들어야 한다.
     */
    private function provider()
    {
        $v = SsoSettings::all();

        $cfg = [
            'client_id'     => $v['client_id'],
            'client_secret' => $v['client_secret'],
            'redirect'      => $v['redirect_uri'],
            'tenant'        => $v['tenant_id'],
        ];

        /* buildProvider 는 client_idㆍsecretㆍredirect 셋만 넘긴다 — tenant 처럼
           제공자가 따로 읽는 값은 setConfig 로 다시 넣어야 한다. 넣지 않으면
           제공자가 기본값 'common' 으로 가서, 우리 테넌트가 아니라 아무 계정에나
           묻는 주소가 만들어진다. */
        $provider = Socialite::buildProvider(\SocialiteProviders\Microsoft\Provider::class, $cfg)
            ->setConfig(new \SocialiteProviders\Manager\Config(
                $cfg['client_id'],
                $cfg['client_secret'],
                $cfg['redirect'],
                ['tenant' => $cfg['tenant']],
            ));

        /* scopes() 는 제공자의 기본값에 **더한다** — 그러면 우리가 청하지 않은
           User.Read 까지 함께 간다. HQ 에 등록하는 권한은 넷뿐이라(지시서 §8)
           setScopes 로 그 넷만 남긴다. */
        return $provider->setScopes(['openid', 'profile', 'email', 'offline_access']);
    }

    /**
     * roles claim 을 우리 역할로 옮긴다.
     *
     * 짝은 설정(config/sso.php)이 들고 있다. **아직 정해지지 않았다** — 비어 있으면
     * 아무것도 하지 않는다. 짝이 없다고 역할을 지우면, 이미 쓰고 있던 사람이
     * SSO 로 한 번 들어왔다는 이유로 권한을 잃는다.
     *
     * @param  array<string>  $roles
     */
    private function 역할을맞춘다(User $user, array $roles): void
    {
        $map = (array) config('sso.web.role_map', []);

        if (! $map || ! $roles) {
            return;
        }

        foreach ($roles as $r) {
            if (isset($map[$r]) && $user->role !== $map[$r]) {
                $user->forceFill(['role' => $map[$r]])->save();
                activity()->performedOn($user)->log("SSO 역할 매핑 {$r} → {$map[$r]}");

                return;
            }
        }
    }

    /** 로그인ㆍ로그아웃 자취 — 이미 쓰고 있는 자리에 남긴다(§5-4) */
    private function 남긴다(?User $user, string $action, string $note): void
    {
        try {
            UserActivityLog::create([
                'user_id'     => $user?->id,
                'type'        => 'auth',
                'action'      => $action,
                'reason_text' => $note,
                'menu_name'   => 'SSO',
                'route_name'  => request()->route()?->getName(),
                'url'         => request()->fullUrl(),
                'ip_address'  => request()->ip(),
                'user_agent'  => substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            /* 자취를 못 남겼다고 로그인을 막지는 않는다 */
            Log::warning('[SSO] 자취를 남기지 못했습니다', ['error' => $e->getMessage()]);
        }
    }
}
