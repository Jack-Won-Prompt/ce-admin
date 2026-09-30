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
 * 켜야만 열린다(sso.web.enabled · 기본 꺼짐). 꺼져 있으면 로그인 화면으로 되돌리고
 * 「준비 중」이라 알린다 — 로그인 화면의 「Microsoft 계정으로 로그인」 단추가 예전부터
 * 하던 말과 같다.
 *
 * 한때 404 로 답했다. 켜지도 않은 길이 열려 있는 것처럼 보이지 않게 한 것인데,
 * 그러면 **배포가 됐는지도 알 수 없다** — HQ 에 넘길 주소를 확인하러 열어 본 사람은
 * 「이 길이 아예 없다」로 읽는다(2026-09-09). 감출 것이 없는 자리다: 이 기능이 있다는
 * 것은 로그인 화면에 이미 드러나 있다.
 *
 * 사람을 가리는 잣대는 **email(UPN)** 이다(§5-2). 등록되지 않은 사람은 들이지 않고
 * 안내만 한다 — 자동으로 만들어 줄지는 아직 정해지지 않았다(sso.web.jit_create).
 */
class EntraController extends Controller
{
    /** 앱이 브라우저로 여는 로그인에 붙는 표·되돌아갈 주소 — 세션에 담아 콜백에서 가린다 */
    private const APP_SESSION_KEY = 'sso.app_nonce';

    /**
     * 이 사람이 SSO 로 들어왔다는 표 (2026-09-29 지시).
     *
     * 나갈 때 Microsoft 에도 알려야 하는지는 **어떻게 들어왔는지**로 갈린다.
     * 비밀번호로 들어온 사람까지 Microsoft 로그아웃으로 보내면, 그 브라우저에서
     * 쓰던 다른 Microsoft 자리까지 함께 끊긴다 — 우리가 끊을 것이 아니다.
     */
    public const SSO_SESSION_KEY = 'sso.signed_in';

    /**
     * 앱으로 되돌아가는 주소의 앞머리. 여기 적힌 것만 받는다.
     *
     * 운영판과 개발판을 한 폰에 같이 두려고 앱을 둘로 찍는데(2026-09-21 지시),
     * 둘 다 같은 주소를 받으면 안드로이드가 어느 앱을 부를지 정하지 못한다.
     * 목록에 없는 값은 버리고 운영판 주소로 떨어뜨린다 — 그래야 이 자리가
     * 아무 데로나 돌려보내는 문이 되지 않는다.
     */
    private const APP_SCHEMES = ['ceadmin', 'ceadmin-dev'];

    /** 일회용 코드가 사는 시간 — 앱이 곧바로 바꾸므로 짧게 둔다 */
    private const APP_CODE_TTL = 120;

    /**
     * 인가 요청으로 보낸다.
     *
     * 앱에서 열었으면 ?app=<표> 가 실려 온다 (2026-09-21 지시). 그 표를 세션에
     * 담아 두었다가 콜백에서 가린다 — 앱은 세션 로그인 대신 일회용 코드를 받는다.
     * Entra 에 보내는 요청은 웹과 똑같다. 그래서 본사에 모바일 리디렉션 주소를
     * 따로 등록하지 않아도 된다.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($꺼짐 = $this->꺼졌으면()) {
            return $꺼짐;
        }

        $앱표 = (string) $request->query('app', '');
        $돌아갈곳 = (string) $request->query('cb', '');
        $request->session()->forget(self::APP_SESSION_KEY);

        /* 모바일 웹에서 시작했으면 적어 둔다 (2026-09-30 지시).
           Microsoft 를 다녀오는 동안 우리 화면을 떠나 있으므로, 돌아왔을 때
           어디서 왔는지 알 길이 세션밖에 없다. 적어 두지 않으면 폰으로 들어온
           사람이 관리자 대시보드에 떨어진다 — 아이디ㆍ비밀번호 길은 이미
           같은 표(login_from)를 쓰고 있어 그것을 그대로 쓴다. */
        if ($request->query('from') === 'm') {
            $request->session()->put('login_from', 'm');
        } elseif ($앱표 !== '') {
            /* 앱에서 시작한 것은 웹 세션을 만들지 않는다. 앞선 모바일 웹 로그인이
               남긴 표가 그대로 있으면, 나중에 이 브라우저로 들어오는 사람이
               엉뚱하게 모바일 화면으로 떨어진다 — 여기서 지운다. */
            $request->session()->forget('login_from');
        }

        if ($앱표 !== '' && preg_match('/^[A-Za-z0-9_-]{16,128}$/', $앱표)) {
            $request->session()->put(self::APP_SESSION_KEY, [
                'nonce'  => $앱표,
                'scheme' => in_array($돌아갈곳, self::APP_SCHEMES, true)
                    ? $돌아갈곳
                    : self::APP_SCHEMES[0],
            ]);
        }

        return $this->provider()->redirect();
    }

    /**
     * 돌아온 code 로 토큰을 받아 로그인한다.
     *
     * state·nonce 는 Socialite 가 본다 — 끄지 않는다(§5-1).
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($꺼짐 = $this->꺼졌으면()) {
            return $꺼짐;
        }

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

            /* 권한 그룹을 함께 넣는다 (2026-09-29 지시).

               권한은 역할이 아니라 **권한 그룹**이 정한다(`PermissionService::allows`).
               그룹이 없는 사람에게는 대시보드 보기만 내주므로, 여태 SSO 로 들어온 사람은
               메뉴가 대시보드 하나뿐이었다. */
            if ($그룹 = $this->들어갈권한그룹()) {
                $user->forceFill(['permission_group_id' => $그룹])->save();
            }

            activity()->performedOn($user)->log("SSO 첫 로그인으로 사용자를 생성했습니다 ({$email})");
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

        /* 이미 만들어져 있던 사람도 그룹이 비어 있으면 채운다 (2026-09-29 지시).

           JIT 는 처음 들어올 때만 돈다. 그 앞에 들어온 사람(운영의 DerekㆍMayㆍStella)은
           그룹 없이 남아 대시보드 하나만 보였다. 로그인할 때마다 한 번 보아 채운다.
           **이미 그룹이 있는 사람은 건드리지 않는다** — 담당자가 좁혀 둔 것을 덮으면 안 된다. */
        if (! $user->permission_group_id && ($그룹 = $this->들어갈권한그룹())) {
            $user->forceFill(['permission_group_id' => $그룹])->save();
            activity()->performedOn($user)->log('SSO 로그인 — 권한 그룹이 없어 채웠습니다');
        }

        /* 앱에서 시작한 로그인이면 웹 세션을 만들지 않는다 (2026-09-21 지시).
           브라우저에 로그인 상태를 남기지 않고, 앱이 한 번만 쓸 수 있는 코드를
           들려 보낸다 — 앱은 그것을 POST /api/auth/sso/exchange 에서 앱 토큰으로
           바꾼다. 코드는 캐시에 2분만 살고, 한 번 쓰면 사라진다. */
        if ($앱것 = $request->session()->pull(self::APP_SESSION_KEY)) {
            $앱표     = is_array($앱것) ? ($앱것['nonce'] ?? '') : (string) $앱것;
            $돌아갈곳 = is_array($앱것) ? ($앱것['scheme'] ?? '') : '';
            if (! in_array($돌아갈곳, self::APP_SCHEMES, true)) {
                $돌아갈곳 = self::APP_SCHEMES[0];
            }

            $코드 = \Illuminate\Support\Str::random(64);

            \Illuminate\Support\Facades\Cache::put("sso:app-code:{$코드}", [
                'user_id' => $user->id,
                'nonce'   => $앱표,
            ], self::APP_CODE_TTL);

            /* 개발판으로 들어온 것을 이력에서 가릴 수 있게 적어 둔다 — 같은 서버를
               운영판과 개발판이 함께 보므로, 안 갈라 두면 섞인다. */
            $this->남긴다($user, 'sso_login', $email
                . ($돌아갈곳 === self::APP_SCHEMES[0] ? ' (앱)' : ' (앱·개발판)'));

            return redirect()->away($돌아갈곳 . '://sso?code=' . urlencode($코드));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        /* 나갈 때 Microsoft 에도 알리려면 어떻게 들어왔는지 알아야 한다.
           regenerate() 뒤에 적는다 — 앞에 적어도 옮겨지기는 하지만, 세션을 새로
           세운 다음에 적는 편이 읽는 사람에게 또렷하다. */
        $request->session()->put(self::SSO_SESSION_KEY, true);

        $this->남긴다($user, 'sso_login', $email);

        /* 모바일 웹에서 시작했으면 모바일 화면으로 돌려보낸다 (2026-09-30 지시).
           가려던 자리가 **모바일 화면일 때만** 그쪽으로 간다 — 관리자 화면을
           열려다 튕긴 자취가 세션에 남아 있으면 intended 가 그것을 먼저 써,
           폰으로 들어온 사람이 관리자 대시보드에 떨어진다(2026-09-30 확인). */
        $모바일 = $request->session()->pull('login_from') === 'm';

        if (! $모바일) {
            return redirect()->intended(route('dashboard'));
        }

        $가려던곳 = (string) $request->session()->pull('url.intended', '');

        return redirect()->to(
            str_starts_with($가려던곳, url('/m')) ? $가려던곳 : route('m.home')
        );
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

    /**
     * 우리 쪽에서 시작하는 로그아웃 — IdP 에도 알린다.
     *
     * 화면의 ［로그아웃］도 여기로 온다(2026-09-29 지시). AuthController::destroy 가
     * SSO 로 들어온 사람이면 이 자리를 그대로 부른다 — 끊는 일이 두 군데로 갈리면
     * 한쪽만 고쳐져 어긋난다.
     *
     * **우리 세션을 먼저 끊고 그 다음에 Microsoft 로 보낸다.** 순서를 뒤집으면,
     * Microsoft 에 닿지 못했을 때 여기 로그인한 채로 남는다 — 나갔다고 믿고 자리를
     * 뜨는 사람이 생긴다. 저쪽이 끊기지 않는 것보다 여기가 끊기지 않는 것이 나쁘다.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        /* 끊기 전에 어디서 쓰던 사람인지 봐 둔다 (2026-09-30 지시).
           Microsoft 로그아웃은 미리 등록해 둔 한 주소(관리자 로그인)로만 돌아올 수
           있다. 그 자리에서 모바일 로그인으로 넘겨주려면 표가 하나 있어야 한다. */
        $모바일 = $request->session()->get('login_from') === 'm'
            || str_starts_with((string) $request->headers->get('referer'), url('/m'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /* 새 세션에 적는다 — 위에서 옛 세션을 비웠으므로 이 값만 남는다.
           GET /login 이 이것을 보고 모바일 로그인으로 보낸 뒤 지운다. */
        if ($모바일) {
            $request->session()->put('ui', 'm');
        }

        if ($user) {
            $this->남긴다($user, 'sso_logout', 'RP-initiated');
        }

        $tenant = SsoSettings::all()['tenant_id'] ?? null;

        if (! $tenant) {
            return redirect()->route($모바일 ? 'm.login' : 'login');
        }

        /* 모바일 웹에서 나가도 여기로 온다 — 로그아웃은 웹과 같게 둔다
           (2026-09-25 지시). post_logout_redirect_uri 도 Entra 앱 등록에 적어 둔
           이 주소라야 받아 준다. */
        return redirect()->away(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/logout"
            . '?post_logout_redirect_uri=' . urlencode(route('login'))
        );
    }

    // ──────────────────────────────────────────────────────

    /**
     * 켜지지 않았으면 아무것도 하지 않고 로그인 화면으로 되돌린다.
     *
     * 404 로 답하지 않는다 — 그러면 「아직 안 켰다」와 「배포가 안 됐다」를 가릴 수 없다.
     */
    private function 꺼졌으면(): ?RedirectResponse
    {
        if (SsoSettings::usable()) {
            return null;
        }

        return redirect()->route('login')->withErrors([
            'email' => 'SSO 로그인은 현재 준비 중입니다. IT 관리자에게 문의하십시오.',
        ]);
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
     * SSO 로 만든 사람을 어느 권한 그룹에 넣을 것인가 (2026-09-29 지시).
     *
     * 설정이 'full' 이면 전권 그룹(`is_full_access`)을 쓴다 — 「SSO 로 등록된 사용자는
     * 모든 메뉴가 보여야 함」이 지시다. 그런 그룹이 아직 없으면 **만들어 둔다**:
     * 운영에는 마이그레이션이 만든 「전체 권한」 그룹이 사라져 있었고(2026-09-29 확인),
     * 없다고 그냥 두면 들어온 사람마다 대시보드 하나만 보게 된다.
     *
     * 숫자를 적어 두면 그 그룹에 넣는다. 빈 값이면 넣지 않는다.
     */
    private function 들어갈권한그룹(): ?int
    {
        $값 = trim((string) config('sso.web.jit_permission_group', ''));

        if ($값 === '') {
            return null;
        }

        if (ctype_digit($값)) {
            return \App\Models\PermissionGroup::whereKey((int) $값)->value('id');
        }

        if ($값 !== 'full') {
            return null;
        }

        $전권 = \App\Models\PermissionGroup::where('is_full_access', true)->value('id');

        if ($전권) {
            return (int) $전권;
        }

        /* 없으면 만든다 — 마이그레이션이 만드는 그것과 같은 이름ㆍ같은 뜻이다 */
        return \App\Models\PermissionGroup::create([
            'name'           => '전체 권한',
            'description'    => '모든 페이지와 모든 동작을 사용할 수 있는 기본 그룹입니다.',
            'is_full_access' => true,
        ])->id;
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
                /* 콜백 주소에는 인가 코드가 붙어 있고 길이도 칸을 넘는다 —
                   경로만 남긴다(UserActivityLog::safeUrl 의 주석을 본다) */
                'url'         => UserActivityLog::safeUrl(request()->fullUrl()),
                'ip_address'  => request()->ip(),
                'user_agent'  => UserActivityLog::safeAgent(request()->userAgent()),
            ]);
        } catch (\Throwable $e) {
            /* 자취를 못 남겼다고 로그인을 막지는 않는다 */
            Log::warning('[SSO] 이력을 남기지 못했습니다', ['error' => $e->getMessage()]);
        }
    }
}
