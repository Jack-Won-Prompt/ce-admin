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
    // 이메일/비밀번호 검증. 그다음은 서버 설정(설정 › 서비스 연동 설정 › 로그인)이 가른다.
    //   문자 인증 켬 → {otp_required: true, pending_token, masked_phone} (202)
    //   문자 인증 끔 → {otp_required: false, token, user, pusher}        (200)
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
            // 기기마다 토큰을 따로 쥔다. 옛 판에서는 오지 않으므로 없어도 받는다.
            'device_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->is_active || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        /* 아이디ㆍ비밀번호 길을 닫아 둔 동안에도 관리자는 들어올 수 있어야 한다 —
           화면에서는 여덟 번 눌러야 칸이 나오고, 들어오는 것은 여기서 가린다. */
        if (! config('auth.password_login.app', true) && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '아이디·비밀번호 로그인은 사용하지 않습니다. 관리자에게 문의하세요.',
            ], 403);
        }

        if (! $user->canEnter('app')) {
            return response()->json([
                'success' => false,
                'message' => '이 계정은 관리자 화면에서만 쓸 수 있습니다.',
            ], 403);
        }

        // 문자 인증을 끄면(설정 › 서비스 연동 설정 › 로그인) 비밀번호만으로 들여보낸다
        if (! config('auth.otp_enabled', true)) {
            return response()->json($this->issueToken($user, $request->input('device_id')));
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
            'device_id'     => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
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

        return response()->json($this->issueToken($user, $request->input('device_id')));
    }

    // ── GET /api/auth/options ─────────────────────────────
    /**
     * 로그인 화면이 무엇을 보여 줄지 앱에 알린다. 로그인 밖의 자리다 —
     * 로그인하기 전에 물어야 하는 값이라서.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success'        => true,
            'password_login' => (bool) config('auth.password_login.app', true),
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

    /**
     * 로그인 완료 응답. OTP 를 건너뛴 로그인과 OTP 검증 뒤 로그인이 같은 값을 받는다
     * — 앱이 두 경로를 가려 저장하지 않아도 되게.
     */
    private function issueToken(User $user, ?string $deviceId = null): array
    {
        /* 토큰은 기기마다 따로 쥔다. 예전에는 로그인할 때 그 계정의 모바일 토큰을
           모두 지웠다 — 폰과 태블릿을 함께 쓰면 서로를 계속 밀어냈다.
           같은 기기에서 다시 로그인하면 그 기기 것만 갈아 끼운다. 그래야 한 기기가
           로그인을 되풀이해도 토큰이 쌓이지 않는다.
           기기 값을 보내지 않는 옛 판은 예전 이름을 그대로 쓴다. */
        $name = $deviceId ? "mobile-app:{$deviceId}" : 'mobile-app';

        $user->tokens()->where('name', $name)->delete();
        $token = $user->createToken($name, ['prescription:upload', 'prescription:read'])->plainTextToken;

        return [
            'success'      => true,
            'otp_required' => false,
            'token'        => $token,
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'pusher'       => [
                'key'     => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
        ];
    }

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
