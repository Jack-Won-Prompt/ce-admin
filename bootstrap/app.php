<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();  // Sanctum Bearer 토큰 인증
        $middleware->validateCsrfTokens(except: [
            'nhis/fax-callback',
            'toss/webhook',
            'popbill/webhook/*',        // 팝빌 전송결과 알림 (2026-09-10)
            'webhooks/shop-order',
            'consent/*/nice/callback',   // NICE 표준창 returnurl(외부 도메인 리다이렉트)
        ]);
        /* 세션이 끊긴 뒤 화면 탭(iframe)이 /login 으로 넘어가면 워크스페이스 안에 로그인
           화면이 조각처럼 박힌다. 창 전체를 옮기도록 가로챈다. 리다이렉트를 보고 판단하므로
           다른 미들웨어보다 바깥에 서야 한다. */
        $middleware->prependToGroup('web', \App\Http\Middleware\BreakFrameOnGuest::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogUserActivity::class);
        // 권한 그룹 기반 페이지·액션 차단 (config/permissions.php 레지스트리 기준)
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckPagePermission::class);
        $middleware->alias(['admin' => \App\Http\Middleware\AdminOnly::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /* 서버에서 난 잘못을 표에도 담는다 (2026-09-11 지시).
           파일 로그는 서버에 들어가야 볼 수 있고 하루가 지나면 갈린다 —
           담당자가 화면에서 함께 볼 자리가 있어야 「아까 그 오류」를 짚을 수 있다.
           담다가 터져도 본래 잘못을 덮지 않도록 안에서 모두 감쌌다. */
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorRecorder::담기($e);
        });

        // 419 CSRF 토큰 만료 시 로그인 페이지로 리다이렉트
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if ($response->getStatusCode() === 419) {
                return redirect()->route('login')
                    ->withErrors(['email' => '세션이 만료되었습니다. 다시 로그인해 주세요.']);
            }
            return $response;
        });
    })->create();
