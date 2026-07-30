<?php
// app/Http/Controllers/PermissionGroupController.php
// 권한 그룹 관리 (관리자 전용 — config/permissions.php 의 admin_only 로 미들웨어가 차단)

namespace App\Http\Controllers;

use App\Models\PermissionGroup;
use App\Models\PermissionGroupPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PermissionGroupController extends Controller
{
    public function index(): View
    {
        $groups = PermissionGroup::withCount('users')->orderByDesc('is_full_access')
                                 ->orderBy('name')->get();

        $gridData = $groups->map(fn (PermissionGroup $g) => [
            'id'          => $g->id,
            'name'        => $g->name . ($g->is_full_access ? ' (기본)' : ''),
            'description' => $g->description ?? '',
            'users'       => $g->users_count,
            'locked'      => $g->isLocked() ? '기본 그룹' : '',
            'updated'     => $g->updated_at?->format('Y-m-d H:i') ?? '',
        ])->values();

        return view('permission-groups.index', [
            'groups'    => $groups,
            'gridData'  => $gridData,
            'total'     => $gridData->count(),
            'pageDefs'  => config('permissions.pages'),
            'menuGroups'=> config('permissions.groups'),
            'actionDefs'=> config('permissions.actions'),
        ]);
    }

    /** 그룹 1건 + 권한 매트릭스 (편집 탭이 fetch 로 가져간다) */
    public function show(PermissionGroup $group): JsonResponse
    {
        return response()->json([
            'success' => true,
            'group'   => [
                'id'             => $group->id,
                'name'           => $group->name,
                'description'    => $group->description ?? '',
                'is_full_access' => $group->is_full_access,
                'locked'         => $group->isLocked(),
                'users_count'    => $group->users()->count(),
            ],
            'matrix'  => $group->permissionMatrix(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:60', 'unique:permission_groups,name'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        $group = PermissionGroup::create($data + ['is_full_access' => false]);

        activity()->causedBy(Auth::user())->log("권한 그룹 생성: {$group->name}");

        return response()->json(['success' => true, 'id' => $group->id, 'message' => '권한 그룹을 만들었습니다.']);
    }

    /**
     * 그룹 정보 + 권한 매트릭스 전체 교체.
     * matrix 형식: { 'prescriptions': ['view','create'], 'orders': ['view'] , ... }
     */
    public function update(Request $request, PermissionGroup $group): JsonResponse
    {
        if ($group->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => '기본 제공 그룹(전체 권한)은 수정할 수 없습니다. 새 그룹을 만들어 사용하세요.',
            ], 422);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:60', 'unique:permission_groups,name,' . $group->id],
            'description' => ['nullable', 'string', 'max:200'],
            'matrix'      => ['array'],
        ]);

        $pageDefs = config('permissions.pages');
        $incoming = $request->input('matrix', []);

        DB::transaction(function () use ($group, $data, $incoming, $pageDefs) {
            $group->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);

            // 매트릭스 전체 교체 — 레지스트리에 없는 페이지/지원하지 않는 액션은 버린다
            $group->pages()->delete();

            foreach ($incoming as $pageKey => $actions) {
                $def = $pageDefs[$pageKey] ?? null;
                if (!$def || !empty($def['admin_only'])) {
                    continue;
                }
                $allowed = array_intersect((array) $actions, $def['actions'] ?? ['view']);
                if (!$allowed) {
                    continue;
                }

                $row = ['permission_group_id' => $group->id, 'page_key' => $pageKey];
                foreach (PermissionGroupPage::ACTION_COLUMN as $action => $column) {
                    $row[$column] = in_array($action, $allowed, true);
                }
                // 조회 없이 하위 액션만 주는 것은 의미가 없으므로 조회를 자동 부여
                $row['can_view'] = true;

                PermissionGroupPage::create($row);
            }
        });

        activity()->causedBy(Auth::user())->log("권한 그룹 수정: {$group->name}");

        return response()->json(['success' => true, 'message' => '권한을 저장했습니다.']);
    }

    public function destroy(PermissionGroup $group): JsonResponse
    {
        if ($group->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => '기본 제공 그룹(전체 권한)은 삭제할 수 없습니다.',
            ], 422);
        }

        $count = $group->users()->count();
        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "이 그룹에 소속된 사용자가 {$count}명 있습니다. 먼저 다른 그룹으로 옮겨 주세요.",
            ], 422);
        }

        $name = $group->name;
        $group->delete();

        activity()->causedBy(Auth::user())->log("권한 그룹 삭제: {$name}");

        return response()->json(['success' => true, 'message' => '권한 그룹을 삭제했습니다.']);
    }
}
