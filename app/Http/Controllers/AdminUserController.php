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
        $users = User::orderBy('role')->orderBy('name')->get();

        $usersData = $users->map(function ($u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone ?? '',
                'role'       => $u->role,
                'is_active'  => (bool) $u->is_active,
                'created_at' => $u->created_at?->format('Y-m-d') ?? '',
            ];
        })->values();

        return view('admin.users.index', compact('users', 'usersData'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:200', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'role'     => ['required', Rule::in(['admin', 'manager'])],
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
            'is_active'=> ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');

        // 자기 자신의 role/is_active 변경 방지
        if ($user->id === Auth::id()) {
            unset($data['role'], $data['is_active']);
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
            'is_active'  => (bool) $user->is_active,
            'created_at' => $user->created_at?->format('Y-m-d'),
        ];
    }
}
