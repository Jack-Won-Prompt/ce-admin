<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    // 기록하지 않을 라우트 패턴
    private const SKIP_ROUTES = [
        'tour.done', 'chat.*', 'panel.*', 'maintenance.*',
        'nhis.faxCallback', 'toss.*', 'products.search',
        'repurchase.day',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // GET 요청이며 인증된 사용자만 기록
        if (!$request->isMethod('GET') || !Auth::check()) {
            return $response;
        }

        $routeName = Route::currentRouteName() ?? '';

        // 스킵 대상 확인
        foreach (self::SKIP_ROUTES as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return $response;
            }
        }

        // 알려진 메뉴명이 없는 라우트는 스킵
        if (!isset(UserActivityLog::MENU_NAMES[$routeName])) {
            return $response;
        }

        UserActivityLog::create([
            'user_id'    => Auth::id(),
            'type'       => 'page',
            'menu_name'  => UserActivityLog::menuName($routeName),
            'route_name' => $routeName,
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 300),
        ]);

        return $response;
    }
}
