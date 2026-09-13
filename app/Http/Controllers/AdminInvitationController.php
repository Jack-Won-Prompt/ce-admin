<?php

namespace App\Http\Controllers;

use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminInvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'   => ['required', 'email', 'max:200', 'unique:users,email', 'unique:admin_invitations,email'],
            'role'    => ['required', Rule::in(['admin', 'manager'])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        // 동일 이메일 대기 중 초대 삭제 후 재발송
        AdminInvitation::where('email', $data['email'])->whereNull('accepted_at')->delete();

        $invitation = AdminInvitation::create([
            'email'      => $data['email'],
            'role'       => $data['role'],
            'token'      => Str::random(48),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addHours(72),
        ]);

        try {
            Mail::to($invitation->email)->send(new AdminInvitationMail($invitation, Auth::user(), $data['message'] ?? ''));
        } catch (\Throwable $e) {
            $invitation->delete();
            return response()->json(['success' => false, 'message' => '이메일 발송에 실패했습니다: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => '초대 이메일이 발송되었습니다.', 'invitation' => $this->formatInvitation($invitation->load('inviter'))]);
    }

    public function list(): JsonResponse
    {
        $invitations = AdminInvitation::with('inviter')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($inv) => $this->formatInvitation($inv));

        return response()->json(['success' => true, 'invitations' => $invitations]);
    }

    public function resend(int $id): JsonResponse
    {
        $invitation = AdminInvitation::findOrFail($id);

        if ($invitation->accepted_at) {
            return response()->json(['success' => false, 'message' => '이미 수락된 초대입니다.'], 422);
        }

        $invitation->update([
            'token'      => Str::random(48),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addHours(72),
        ]);

        try {
            Mail::to($invitation->email)->send(new AdminInvitationMail($invitation, Auth::user()));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => '이메일 발송에 실패했습니다: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => '초대 이메일이 재발송되었습니다.', 'invitation' => $this->formatInvitation($invitation->load('inviter'))]);
    }

    public function destroy(int $id): JsonResponse
    {
        $invitation = AdminInvitation::findOrFail($id);

        if ($invitation->accepted_at) {
            return response()->json(['success' => false, 'message' => '이미 수락된 초대는 삭제할 수 없습니다.'], 422);
        }

        $invitation->delete();

        return response()->json(['success' => true]);
    }

    public function accept(string $token): View|RedirectResponse
    {
        $invitation = AdminInvitation::where('token', $token)->first();

        /* 로그인 화면은 $errors 만 보여 준다 — with('error') 로 보내면 안내가 보이지 않는다 */
        if (!$invitation || !$invitation->isPending()) {
            return $this->toLogin('유효하지 않거나 만료된 초대 링크입니다.');
        }

        if ($this->alreadyRegistered($invitation)) {
            return $this->toLogin("이미 등록된 계정입니다 ({$invitation->email}). 로그인해 주십시오.");
        }

        return view('admin.invite.accept', compact('invitation'));
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $invitation = AdminInvitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isPending()) {
            return $this->toLogin('유효하지 않거나 만료된 초대 링크입니다.');
        }

        if ($this->alreadyRegistered($invitation)) {
            return $this->toLogin("이미 등록된 계정입니다 ({$invitation->email}). 로그인해 주십시오.");
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /* 사용자 생성과 수락 표시는 함께 되거나 함께 안 되어야 한다 (2026-09-13).
           둘이 따로 저장되던 때, 사용자만 만들어지고 초대는 대기로 남아 수락 화면이
           계속 열렸고, 다시 누를 때마다 중복 이메일로 500 이 났다(09-11, 12회). */
        try {
            $user = DB::transaction(function () use ($invitation, $data) {
                $user = User::create([
                    'name'      => $data['name'],
                    'email'     => $invitation->email,
                    'password'  => $data['password'],
                    'role'      => $invitation->role,
                    'is_active' => true,
                ]);

                $invitation->update(['accepted_at' => now()]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            /* 두 번 연달아 눌러 앞 요청이 먼저 만든 경우 */
            $this->alreadyRegistered($invitation);

            return $this->toLogin("이미 등록된 계정입니다 ({$invitation->email}). 로그인해 주십시오.");
        }

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', '계정이 생성되었습니다. 환영합니다!');
    }

    /**
     * 초대받은 이메일이 이미 사용자로 있는가 (2026-09-13).
     *
     * 있으면 새로 만들지 않는다 — 만들려 하면 중복 이메일로 500 이 난다.
     * 앞선 수락이 중간에 끊겼거나 관리자가 직접 등록한 경우다. 초대는 수락으로
     * 닫아, 목록에서 대기로 남지 않게 한다.
     */
    private function alreadyRegistered(AdminInvitation $invitation): bool
    {
        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            return false;
        }

        if (! $invitation->accepted_at) {
            $invitation->update(['accepted_at' => $user->created_at ?? now()]);
        }

        return true;
    }

    private function toLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    private function formatInvitation(AdminInvitation $inv): array
    {
        $status = $inv->accepted_at ? 'accepted'
                : ($inv->expires_at->isPast() ? 'expired' : 'pending');

        return [
            'id'           => $inv->id,
            'email'        => $inv->email,
            'role'         => $inv->role,
            'status'       => $status,
            'invited_by'   => $inv->inviter?->name ?? '—',
            'accepted_at'  => $inv->accepted_at?->format('Y-m-d H:i'),
            'expires_at'   => $inv->expires_at->format('Y-m-d H:i'),
            'created_at'   => $inv->created_at->format('Y-m-d H:i'),
        ];
    }
}
