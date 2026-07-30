<?php
// app/Services/PermissionService.php
//
// 권한 판정은 전부 이 클래스 하나를 지난다.
// 헬퍼 perm(), Blade @perm, Gate, CheckPagePermission 미들웨어가 모두 여기를 호출한다.

namespace App\Services;

use App\Models\PermissionGroupPage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /** 사용자별 권한 매트릭스 캐시 (요청 1회 조회) */
    private array $matrixCache = [];

    /** 라우트명 → [페이지키, 액션] 캐시 */
    private array $routeCache = [];

    /**
     * 이 사용자가 해당 페이지에서 해당 액션을 할 수 있는가.
     *
     * 판정 순서:
     *   1) role=admin → 항상 허용 (권한 설정 오류로 관리자까지 잠기는 사고 방지)
     *   2) admin_only 페이지인데 admin 이 아니면 거부
     *   3) 페이지가 그 액션을 아예 지원하지 않으면 거부
     *   4) 전권 그룹(is_full_access) → 허용
     *   5) 그룹 미지정 → 대시보드 조회만 허용
     *   6) 그 외 → permission_group_pages 조회
     */
    public function allows(?User $user, string $page, string $action = 'view'): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $def = config("permissions.pages.$page");
        if (!$def) {
            // 레지스트리에 없는 페이지 키 — 오타로 화면이 조용히 사라지는 것을 막기 위해
            // 경고를 남기고 허용한다(차단은 미들웨어의 페이지 매칭이 담당).
            Log::warning('알 수 없는 권한 페이지 키', ['page' => $page, 'action' => $action]);
            return true;
        }

        if (!empty($def['admin_only'])) {
            return false;   // 위에서 admin 은 이미 통과했다
        }

        if (!in_array($action, $def['actions'] ?? ['view'], true)) {
            return false;
        }

        $group = $user->permissionGroup;

        if (!$group) {
            return $page === 'dashboard' && $action === 'view';
        }

        if ($group->is_full_access) {
            return true;
        }

        return (bool) ($this->matrix($user)[$page][$action] ?? false);
    }

    /** 이 사용자에게 보여야 할 페이지 키 목록 */
    public function visiblePages(?User $user): array
    {
        $keys = array_keys(config('permissions.pages', []));
        return array_values(array_filter($keys, fn ($k) => $this->allows($user, $k, 'view')));
    }

    /**
     * 라우트명 → [페이지키, 액션]. 매칭되는 페이지가 없으면 [null, null].
     *
     * 매칭 규칙(구체적인 것 우선):
     *   1) overrides 에 라우트명이 있으면 그 지정을 따른다
     *   2) 페이지의 exact 목록에 정확히 일치
     *   3) 페이지의 routes 접두사에 일치 (가장 긴 접두사 우선)
     * 액션은 HTTP 메서드로 추론한다(override 가 있으면 그것을 쓴다).
     */
    public function resolveRoute(?string $routeName, string $method): array
    {
        if (!$routeName) {
            return [null, null];
        }

        $key = $routeName . '|' . $method;
        if (isset($this->routeCache[$key])) {
            return $this->routeCache[$key];
        }

        $pages     = config('permissions.pages', []);
        $overrides = config('permissions.overrides', []);

        $page   = null;
        $action = $this->actionFromMethod($method);

        if (isset($overrides[$routeName])) {
            [$oPage, $oAction] = $overrides[$routeName];
            if ($oPage)   $page   = $oPage;
            if ($oAction) $action = $oAction;
        }

        if (!$page) {
            // exact 우선
            foreach ($pages as $pk => $def) {
                if (in_array($routeName, $def['exact'] ?? [], true)) {
                    $page = $pk;
                    break;
                }
            }
        }

        if (!$page) {
            // 접두사 매칭 — 더 긴(구체적인) 접두사가 이긴다
            $bestLen = -1;
            foreach ($pages as $pk => $def) {
                foreach ($def['routes'] ?? [] as $prefix) {
                    if ($routeName === $prefix || str_starts_with($routeName, $prefix . '.')) {
                        if (strlen($prefix) > $bestLen) {
                            $bestLen = strlen($prefix);
                            $page    = $pk;
                        }
                    }
                }
            }
        }

        // 페이지가 그 액션을 지원하지 않으면 view 로 낮춘다
        // (예: 조회 전용 페이지의 POST 검색 엔드포인트)
        if ($page && !in_array($action, $pages[$page]['actions'] ?? ['view'], true)) {
            $action = 'view';
        }

        return $this->routeCache[$key] = [$page, $action];
    }

    private function actionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'POST'         => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE'       => 'delete',
            default        => 'view',
        };
    }

    /** 사용자의 페이지×액션 매트릭스 (요청당 1회 조회) */
    private function matrix(User $user): array
    {
        if (isset($this->matrixCache[$user->id])) {
            return $this->matrixCache[$user->id];
        }

        $rows = PermissionGroupPage::where('permission_group_id', $user->permission_group_id)->get();

        $matrix = [];
        foreach ($rows as $r) {
            $matrix[$r->page_key] = [
                'view'   => (bool) $r->can_view,
                'create' => (bool) $r->can_create,
                'update' => (bool) $r->can_update,
                'delete' => (bool) $r->can_delete,
                'send'   => (bool) $r->can_send,
            ];
        }

        return $this->matrixCache[$user->id] = $matrix;
    }
}
