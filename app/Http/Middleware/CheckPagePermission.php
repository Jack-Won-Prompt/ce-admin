<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 페이지 권한 서버측 강제.
 *
 * 라우트명을 config/permissions.php 의 페이지로 매칭하고, HTTP 메서드로 액션을 추론해
 * 검사한다. 이 방식으로 컨트롤러 249개를 손대지 않고 전 라우트에 권한이 걸린다.
 * 추론이 실제 의미와 다른 라우트는 레지스트리의 overrides 로 바로잡는다.
 *
 * 레지스트리의 어느 페이지에도 매칭되지 않는 라우트는 통과시킨다. 화면이 아니라
 * 앱 전체가 공용으로 쓰는 유틸리티(채팅·투어·알림 패널·API·제품검색 등)이기 때문이며,
 * 목록과 근거는 config/permissions.php 의 unscoped_documented 에 적어 두었다.
 */
class CheckPagePermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);   // 인증은 auth 미들웨어가 담당
        }

        [$page, $action] = $this->permissions->resolveRoute(
            $request->route()?->getName(),
            $request->method()
        );

        if (!$page) {
            return $next($request);
        }

        if ($this->permissions->allows($user, $page, $action)) {
            return $next($request);
        }

        $label   = config("permissions.pages.$page.label", $page);
        $actions = config('permissions.actions', []);
        $message = $action === 'view'
            ? "'{$label}' 화면에 접근할 권한이 없습니다."
            : "'{$label}' 화면에서 " . ($actions[$action] ?? $action) . " 권한이 없습니다.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
