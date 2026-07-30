<?php
// app/Support/permissions.php
// 권한 확인 헬퍼. composer.json 의 autoload.files 로 전역 로드된다.

use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

if (!function_exists('perm')) {
    /**
     * 현재 로그인 사용자가 해당 페이지에서 해당 액션을 할 수 있는가.
     *   perm('prescriptions')            // 조회 가능?
     *   perm('prescriptions', 'send')    // 발송 가능?
     */
    function perm(string $page, string $action = 'view'): bool
    {
        return app(PermissionService::class)->allows(Auth::user(), $page, $action);
    }
}

if (!function_exists('perm_pages')) {
    /** 현재 사용자에게 보여야 할 페이지 키 목록 */
    function perm_pages(): array
    {
        return app(PermissionService::class)->visiblePages(Auth::user());
    }
}

if (!function_exists('perm_any')) {
    /** 해당 페이지에서 액션 중 하나라도 가능한가 (그룹 헤더 표시 판단 등) */
    function perm_any(string $page, array $actions = ['view']): bool
    {
        foreach ($actions as $a) {
            if (perm($page, $a)) {
                return true;
            }
        }
        return false;
    }
}
