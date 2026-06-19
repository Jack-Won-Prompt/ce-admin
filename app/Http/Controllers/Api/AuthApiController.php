<?php
// app/Http/Controllers/Api/AuthApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginOtpToken;
use App\Models\User;
use App\Services\Popbill\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    private const OTP_TTL_MINUTES  = 5;
    private const OTP_MAX_ATTEMPTS = 5;

    // ── POST /api/auth/login ──────────────────────────────
    // 1단계: 이메일/비밀번호 검증 → OTP SMS 발송
    // 응답: {otp_required: true, pending_token, masked_phone}
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->is_active || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        if (empty($user->phone)) {
            return response()->json([
                'success' => false,
                'message' => '등록된 휴대폰 번호가 없습니다. 관리자에게 문의하세요.',
            ], 403);
        }

        // 기존 미사용 OTP 무효화
        LoginOtpToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code         = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pendingToken = Str::random(64);

        LoginOtpToken::create([
            'user_id'       => $user->id,
            'code'          => $code,
            'pending_token' => $pendingToken,
            'expires_at'    => now()->addMinutes(self::OTP_TTL_MINUTES),
            'created_at'    => now(),
        ]);

        $this->sendOtpSms($user, $code);

        return response()->json([
            'success'       => true,
            'otp_required'  => true,
            'pending_token' => $pendingToken,
            'masked_phone'  => $this->maskPhone($user->phone),
            'message'       => '인증번호를 발송했습니다.',
        ], 202);
    }

    // ── POST /api/auth/verify-otp ─────────────────────────
    // 2단계: OTP 검증 → Sanctum Bearer 토큰 발급
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'pending_token' => 'required|string',
            'code'          => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $otp = LoginOtpToken::where('pending_token', $request->pending_token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => '인증 세션이 만료되었습니다. 다시 로그인해 주세요.',
            ], 422);
        }

        // 시도 횟수 관리 (pending_token 기준으로 user의 최근 otp 재사용)
        $attemptsKey = 'otp_attempts_' . $otp->id;
        $attempts    = cache($attemptsKey, 0);

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $otp->update(['used_at' => now()]);
            return response()->json([
                'success' => false,
                'message' => '인증 시도 횟수를 초과했습니다. 다시 로그인해 주세요.',
            ], 429);
        }

        if ($otp->code !== $request->code) {
            cache([$attemptsKey => $attempts + 1], now()->addMinutes(self::OTP_TTL_MINUTES));
            $remaining = self::OTP_MAX_ATTEMPTS - ($attempts + 1);
            return response()->json([
                'success'   => false,
                'message'   => "인증번호가 올바르지 않습니다. (남은 시도: {$remaining}회)",
                'remaining' => $remaining,
            ], 422);
        }

        $otp->update(['used_at' => now()]);

        /** @var User $user */
        $user = $otp->user;

        // 기존 모바일 토큰 삭제 후 새 토큰 발급 (단일 기기 로그인)
        $user->tokens()->where('name', 'mobile-app')->delete();
        $token = $user->createToken('mobile-app', ['prescription:upload', 'prescription:read'])->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'pusher'  => [
                'key'     => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
        ]);
    }

    // ── POST /api/auth/resend-otp ─────────────────────────
    // OTP 재발송 (pending_token 유지, 새 코드 발급)
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'pending_token' => 'required|string',
        ]);

        $prevOtp = LoginOtpToken::where('pending_token', $request->pending_token)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (!$prevOtp) {
            return response()->json([
                'success' => false,
                'message' => '인증 세션이 만료되었습니다. 다시 로그인해 주세요.',
            ], 422);
        }

        $user = $prevOtp->user;

        // 기존 OTP 무효화
        LoginOtpToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code         = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pendingToken = Str::random(64);

        LoginOtpToken::create([
            'user_id'       => $user->id,
            'code'          => $code,
            'pending_token' => $pendingToken,
            'expires_at'    => now()->addMinutes(self::OTP_TTL_MINUTES),
            'created_at'    => now(),
        ]);

        $this->sendOtpSms($user, $code);

        return response()->json([
            'success'       => true,
            'pending_token' => $pendingToken,
            'message'       => '인증번호를 재발송했습니다.',
        ]);
    }

    // ── POST /api/auth/logout ─────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->update(['fcm_token' => null]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '로그아웃되었습니다.',
        ]);
    }

    // ── GET /api/auth/me ──────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user'    => [
                'id'    => $request->user()->id,
                'name'  => $request->user()->name,
                'email' => $request->user()->email,
                'role'  => $request->user()->role,
            ],
        ]);
    }

    // ── POST /api/auth/fcm-token ──────────────────────────
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string|max:512']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────

    private function sendOtpSms(User $user, string $code): void
    {
        try {
            app(MessageService::class)->send(
                $user->phone,
                "[콜로플라스트] 로그인 인증번호: {$code}\n5분 내 입력하세요.",
                $user->name,
            );
        } catch (\Throwable $e) {
            Log::error('모바일 2FA OTP SMS 발송 실패', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 8) {
            return $phone;
        }
        return substr($digits, 0, 3) . '-****-' . substr($digits, -4);
    }
}
