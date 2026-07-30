<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::with('permissionGroup')->orderBy('role')->orderBy('name')->get();

        // 권한 그룹 셀렉트 옵션 (전권 그룹을 위로)
        $permissionGroups = \App\Models\PermissionGroup::orderByDesc('is_full_access')
            ->orderBy('name')
            ->get(['id', 'name', 'is_full_access']);

        $usersData = $users->map(function ($u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone ?? '',
                'role'       => $u->role,
                'permission_group_id' => $u->permission_group_id,
                'is_active'  => (bool) $u->is_active,
                'created_at' => $u->created_at?->format('Y-m-d') ?? '',
            ];
        })->values();

        // wwGrid 표시용 배열 (id 포함, 배지→텍스트, 날짜 포맷)
        $meId = Auth::id();
        $gridData = $users->map(function ($u) use ($meId) {
            return [
                'id'      => $u->id,
                'name'    => $u->name . ($u->id === $meId ? ' (나)' : ''),
                'email'   => $u->email,
                'phone'   => $u->phone ?: '—',
                'role'    => $u->role === 'admin' ? '관리자' : '매니저',
                // admin 은 그룹과 무관하게 항상 전권이므로 그렇게 표시한다
                'group'   => $u->role === 'admin'
                                ? '전체 권한 (관리자)'
                                : ($u->permissionGroup?->name ?? '미지정'),
                'status'  => $u->is_active ? '활성' : '비활성',
                'created' => $u->created_at?->format('Y-m-d') ?? '',
            ];
        })->values();

        $total = $gridData->count();

        return view('admin.users.index', compact('users', 'usersData', 'gridData', 'total', 'permissionGroups'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:200', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'role'     => ['required', Rule::in(['admin', 'manager'])],
            'permission_group_id' => ['nullable', 'exists:permission_groups,id'],
            'is_active'=> ['boolean'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $user = User::create($data);

        return response()->json(['success' => true, 'user' => $this->formatUser($user)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => ['nullable', 'string', 'max:20'],
            'role'     => ['required', Rule::in(['admin', 'manager'])],
            'permission_group_id' => ['nullable', 'exists:permission_groups,id'],
            'is_active'=> ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');

        // 자기 자신의 role/권한그룹/is_active 변경 방지 (스스로 권한을 낮춰 잠기는 것을 막는다)
        if ($user->id === Auth::id()) {
            unset($data['role'], $data['permission_group_id'], $data['is_active']);
        }

        $user->update($data);

        return response()->json(['success' => true, 'user' => $this->formatUser($user->fresh())]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => '자기 자신은 삭제할 수 없습니다.'], 422);
        }

        $user->delete();

        return response()->json(['success' => true]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone ?? '',
            'role'       => $user->role,
            'permission_group_id' => $user->permission_group_id,
            'group_name' => $user->role === 'admin'
                                ? '전체 권한 (관리자)'
                                : ($user->permissionGroup?->name ?? '미지정'),
            'is_active'  => (bool) $user->is_active,
            'created_at' => $user->created_at?->format('Y-m-d'),
        ];
    }
}
