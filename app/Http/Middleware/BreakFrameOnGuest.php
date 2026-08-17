<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 세션이 끊겼을 때 프레임 안에 로그인 화면이 뜨는 것을 막는다.
 *
 * 화면 탭은 iframe 이라, 세션이 만료된 뒤 탭을 누르면 그 안에서 /login 으로 넘어간다.
 * 그러면 사이드바가 있는 워크스페이스 안에 로그인 화면이 조각처럼 박혀 보인다.
 * 로그인은 창 전체가 해야 하는 일이다.
 *
 * 리다이렉트를 가로채 창 전체를 옮기는 짧은 문서로 바꾼다. 프레임 밖이면 손대지 않는다.
 *
 * 요청이 프레임 안에서 왔는지는 두 가지로 본다.
 *   · 우리 워크스페이스가 붙이는 frame=1
 *   · 브라우저가 알려 주는 Sec-Fetch-Dest: iframe (주소에 표시가 없어도 잡힌다)
 */
class BreakFrameOnGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->isFramed($request) || !$this->goesToLogin($response)) {
            return $response;
        }

        $target = $response->headers->get('Location');

        /* 화면을 그리지 않는 요청(fetch·XHR)까지 문서로 바꾸면 호출한 쪽이 JSON 을 기다리다
           엉뚱한 것을 받는다. 그쪽에는 401 로 알려 주고 판단을 맡긴다. */
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message'   => '세션이 만료되었습니다. 다시 로그인해 주십시오.',
                'login_url' => $target,
            ], 401);
        }

        return response($this->breakoutHtml($target), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function isFramed(Request $request): bool
    {
        return $request->boolean('frame')
            || strtolower((string) $request->headers->get('Sec-Fetch-Dest')) === 'iframe';
    }

    private function goesToLogin(Response $response): bool
    {
        if (!$response->isRedirection()) {
            return false;
        }

        $location = (string) $response->headers->get('Location');

        return $location !== '' && str_contains($location, route('login'));
    }

    /**
     * 창 전체를 로그인으로 옮긴다.
     *
     * top 이 다른 출처면 접근이 막히므로 그때는 이 프레임만이라도 옮긴다 — 아무 데도 못 가고
     * 빈 화면으로 남는 것보다 낫다. 스크립트가 꺼져 있을 때를 위해 링크도 하나 둔다.
     */
    private function breakoutHtml(string $target): string
    {
        $url = e($target);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ko"><head><meta charset="UTF-8"><title>다시 로그인</title>
        <style>
          body { margin:0; height:100vh; display:flex; align-items:center; justify-content:center;
                 font-family:'Malgun Gothic','맑은 고딕',sans-serif; background:#f7f8fa; color:#4b5563; }
          .box { text-align:center; font-size:13px; line-height:1.9; }
          a { color:#28798B; font-weight:700; }
        </style></head>
        <body>
          <div class="box">
            세션이 만료되었습니다.<br>로그인 화면으로 이동합니다.
            <div style="margin-top:10px;"><a href="{$url}" target="_top">지금 이동</a></div>
          </div>
          <script>
            (function () {
              var url = {$this->jsUrl($target)};
              try { window.top.location.replace(url); }
              catch (e) { window.location.replace(url); }
            })();
          </script>
        </body></html>
        HTML;
    }

    private function jsUrl(string $target): string
    {
        return json_encode($target, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
            | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
