<?php

namespace App\Http\Controllers;

use App\Support\SsoSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * SSO 설정 화면 — 시스템 설정 › SSO 설정.
 *
 * 지시서 LTL-UNICORN-20260909-02 §3.2.
 *
 * 값은 이미 있는 설정 체계(settings 표)에 담는다. Secret 은 Setting 이 암호화해
 * 담고, 화면에는 **뒤 넉 자만** 보인다 — 원문은 내려보내지 않는다(write-only).
 */
class SsoSettingController extends Controller
{
    public function edit(): View
    {
        $담긴것 = SsoSettings::raw();

        return view('sso-settings.edit', [
            '환경들'  => SsoSettings::ENVS,
            '고른환경' => $담긴것['env'],
            'enabled' => $담긴것['enabled'],
            'usable'  => SsoSettings::usable(),
            /* 두 벌을 함께 실어 준다 — 화면이 탭으로 갈라 보여 준다.
               Secret 은 원문을 내려보내지 않고 뒤 넉 자만 싣는다. */
            '값' => collect(SsoSettings::ENVS)->mapWithKeys(fn ($말, $env) => [$env => [
                'tenant_id'    => $담긴것[$env]['tenant_id'],
                'client_id'    => $담긴것[$env]['client_id'],
                'redirect_uri' => $담긴것[$env]['redirect_uri'],
                'secretMasked' => SsoSettings::maskedSecret($env),
                '채워짐'        => ! collect(SsoSettings::REQUIRED)
                    ->contains(fn ($k) => blank($담긴것[$env][$k] ?? null)),
            ]])->all(),
            /* 이 서버가 만들어 낼 주소 — HQ 에 등록해야 하는 값이라 그대로 보여 준다 */
            'suggestRedirect' => route('auth.entra.callback'),
            'suggestLogout'   => route('auth.entra.frontchannel-logout'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /* 칸 이름에 환경이 붙어 온다(tenant_id__test · tenant_id__live).

           두 벌이 한 폼에 함께 실려 오므로 이름이 같으면 어느 쪽 Secret 인지
           가릴 수 없다. 이름을 갈라 두고 **서버가 고른 환경의 것만** 읽는다 —
           보이지 않는 탭의 값은 들어와도 쓰지 않는다. 화면이 감추는 것만으로는
           갈랐다고 할 수 없다. */
        $갈래 = ['required', 'string', 'in:' . implode(',', array_keys(SsoSettings::ENVS))];
        $잣대 = ['env' => $갈래, 'use_env' => ['nullable'] + $갈래, 'enabled' => ['nullable']];

        $칸잣대 = [
            'tenant_id'     => ['nullable', 'string', 'max:100'],
            'client_id'     => ['nullable', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'redirect_uri'  => ['nullable', 'url', 'max:300'],
        ];

        foreach (array_keys(SsoSettings::ENVS) as $e) {
            foreach ($칸잣대 as $k => $r) {
                $잣대[$k . '__' . $e] = $r;
            }
        }

        $보낸것 = $request->validate($잣대);

        /* 어느 환경의 칸을 고쳤는가. 화면은 탭 하나를 저장하므로 한 번에 한 벌이다. */
        $고친환경 = $보낸것['env'];

        /* 고른 환경의 것만 제 이름으로 옮겨 담는다 */
        $data = ['env' => $고친환경, 'use_env' => $보낸것['use_env'] ?? null];

        foreach (array_keys($칸잣대) as $k) {
            $data[$k] = $보낸것[$k . '__' . $고친환경] ?? null;
        }

        /* 어느 환경으로 쓸까 — 고르개를 보내지 않았으면 지금 그대로 둔다 */
        $쓸환경 = $data['use_env'] ?? SsoSettings::env();
        $켤까   = $request->boolean('enabled');

        /* 켜려면 **쓸 환경에** 넷이 다 있어야 한다(§3.2-5). 지금 담긴 값과 이번에
           보낸 값을 함께 본다 — Secret 은 빈 채로 보내는 것이 「그대로 두겠다」는
           뜻이라, 이미 담겨 있으면 채워진 것으로 센다.

           고친 환경과 쓸 환경이 다를 수 있다. 시험 칸을 고치면서 운영으로 넘기는
           일이 그렇다 — 그때 보아야 하는 것은 **운영 쪽**이다. */
        if ($켤까) {
            $이미 = SsoSettings::all($쓸환경);
            $빈것 = [];

            foreach (SsoSettings::REQUIRED as $k) {
                $값 = ($쓸환경 === $고친환경) ? ($data[$k] ?? null) : null;

                if (blank($값) && blank($이미[$k] ?? null)) {
                    $빈것[] = SsoSettings::FIELDS[$k]['label'];
                }
            }

            if ($빈것) {
                return back()->withErrors([
                    'enabled' => (SsoSettings::ENVS[$쓸환경] ?? $쓸환경) . ' 설정의 '
                        . implode('ㆍ', $빈것) . ' 을(를) 채우지 않으면 SSO 를 켤 수 없습니다.',
                ])->withInput();
            }
        }

        $바뀐것 = SsoSettings::put($data, $고친환경);

        $공통 = SsoSettings::putCommon(['enabled' => $켤까, 'env' => $쓸환경]);

        /* 자취에는 **어느 칸을 바꿨는지만** 남긴다 — 값은 남기지 않는다(§3.2-4).
           어느 환경을 고쳤는지는 함께 남긴다. 두 벌이 되었으므로 그것을 모르면
           나중에 자취를 읽어도 어느 자격이 바뀌었는지 알 수 없다. */
        if ($바뀐것 || $공통) {
            $말 = [];

            if ($바뀐것) {
                $말[] = (SsoSettings::ENVS[$고친환경] ?? $고친환경) . ' 설정 — ' . implode(', ', array_map(
                    fn ($k) => SsoSettings::FIELDS[$k]['label'] ?? $k, $바뀐것));
            }

            foreach ($공통 as $k) {
                $말[] = (SsoSettings::COMMON[$k]['label'] ?? $k)
                    . ($k === 'env' ? ' → ' . (SsoSettings::ENVS[$쓸환경] ?? $쓸환경)
                                    : ' → ' . ($켤까 ? '사용' : '사용 안 함'));
            }

            activity()->causedBy(Auth::user())->log('SSO 설정 변경 — ' . implode(' · ', $말));
        }

        return back()->with('success', ($바뀐것 || $공통)
            ? '저장했습니다.'
            : '바뀐 것이 없습니다.');
    }

    /**
     * 연동 테스트 — 담긴 Tenant ID 로 OIDC discovery 를 읽어 본다.
     *
     * **Secret 까지 보지는 않는다**(§3.2-3). 여기서 확인하는 것은 「이 테넌트가
     * 있는가」뿐이다 — Secret 이 맞는지는 실제로 로그인해 봐야 안다.
     */
    public function test(Request $request): JsonResponse
    {
        /* 어느 환경을 시험할지 받는다 (2026-09-29). 두 벌이 되었으므로 고르지 않으면
           지금 쓰는 쪽을 본다 — 화면은 탭마다 제 환경을 보낸다. */
        $env    = $request->query('env');
        $tenant = trim((string) (SsoSettings::all(is_string($env) ? $env : null)['tenant_id'] ?? ''));

        if ($tenant === '') {
            return response()->json(['success' => false, 'message' => 'Tenant ID 를 먼저 저장해 주십시오.'], 422);
        }

        $url = "https://login.microsoftonline.com/{$tenant}/v2.0/.well-known/openid-configuration";

        try {
            $res = Http::connectTimeout(5)->timeout(10)->get($url);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft 를 부르지 못했습니다 — ' . $e->getMessage(),
            ], 502);
        }

        if (! $res->ok()) {
            return response()->json([
                'success' => false,
                'message' => "Tenant 를 찾지 못했습니다 (HTTP {$res->status()}). Tenant ID 를 다시 확인해 주십시오.",
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Tenant 를 확인했습니다.',
            'issuer'     => $res->json('issuer'),
            'authorize'  => $res->json('authorization_endpoint'),
            'token'      => $res->json('token_endpoint'),
            'end_session' => $res->json('end_session_endpoint'),
        ]);
    }
}
