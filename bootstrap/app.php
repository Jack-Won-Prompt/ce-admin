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
            'webhooks/shop-order',
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogUserActivity::class);
        $middleware->alias(['admin' => \App\Http\Middleware\AdminOnly::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 CSRF 토큰 만료 시 로그인 페이지로 리다이렉트
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if ($response->getStatusCode() === 419) {
                return redirect()->route('login')
                    ->withErrors(['email' => '세션이 만료되었습니다. 다시 로그인해 주세요.']);
            }
            return $response;
        });
    })->create();
