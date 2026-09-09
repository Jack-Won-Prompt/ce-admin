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
        $v = SsoSettings::all();

        return view('sso-settings.edit', [
            'tenantId'     => $v['tenant_id'],
            'clientId'     => $v['client_id'],
            'redirectUri'  => $v['redirect_uri'],
            'enabled'      => $v['enabled'],
            'secretMasked' => SsoSettings::maskedSecret(),
            'usable'       => SsoSettings::usable(),
            /* 이 서버가 만들어 낼 주소 — HQ 에 등록해야 하는 값이라 그대로 보여 준다 */
            'suggestRedirect' => route('auth.entra.callback'),
            'suggestLogout'   => route('auth.entra.frontchannel-logout'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id'     => ['nullable', 'string', 'max:100'],
            'client_id'     => ['nullable', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'redirect_uri'  => ['nullable', 'url', 'max:300'],
            'enabled'       => ['nullable'],
        ]);

        $켤까 = $request->boolean('enabled');

        /* 켜려면 넷이 다 있어야 한다(§3.2-5). 지금 담긴 값과 이번에 보낸 값을 함께
           본다 — Secret 은 빈 채로 보내는 것이 「그대로 두겠다」는 뜻이라, 이미
           담겨 있으면 채워진 것으로 센다. */
        if ($켤까) {
            $이미 = SsoSettings::all();
            $빈것 = [];

            foreach (SsoSettings::REQUIRED as $k) {
                $값 = $data[$k] ?? null;
                if (blank($값) && blank($이미[$k] ?? null)) {
                    $빈것[] = SsoSettings::FIELDS[$k]['label'];
                }
            }

            if ($빈것) {
                return back()->withErrors([
                    'enabled' => implode('ㆍ', $빈것) . ' 을(를) 채우지 않으면 SSO 를 켤 수 없습니다.',
                ])->withInput();
            }
        }

        $바뀐것 = SsoSettings::put($data + ['enabled' => $켤까]);

        /* 자취에는 **어느 칸을 바꿨는지만** 남긴다 — 값은 남기지 않는다(§3.2-4) */
        if ($바뀐것) {
            activity()->causedBy(Auth::user())
                ->log('SSO 설정 변경 — ' . implode(', ', array_map(
                    fn ($k) => SsoSettings::FIELDS[$k]['label'] ?? $k,
                    $바뀐것,
                )));
        }

        return back()->with('success', $바뀐것
            ? '저장했습니다.'
            : '바뀐 것이 없습니다.');
    }

    /**
     * 연동 테스트 — 담긴 Tenant ID 로 OIDC discovery 를 읽어 본다.
     *
     * **Secret 까지 보지는 않는다**(§3.2-3). 여기서 확인하는 것은 「이 테넌트가
     * 있는가」뿐이다 — Secret 이 맞는지는 실제로 로그인해 봐야 안다.
     */
    public function test(): JsonResponse
    {
        $tenant = trim((string) (SsoSettings::all()['tenant_id'] ?? ''));

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
