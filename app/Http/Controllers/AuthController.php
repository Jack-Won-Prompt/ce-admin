<?php

namespace App\Http\Controllers;

use App\Models\LoginOtpToken;
use App\Models\User;
use App\Services\InstitutionalNoticeCrawlerService;
use App\Services\Popbill\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const OTP_TTL_MINUTES  = 5;
    private const OTP_MAX_ATTEMPTS = 5;

    /**
     * 로그인 페이지
     * GET /login
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * 1단계: 이메일/비밀번호 검증 후 OTP 발송
     * POST /login
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::validate($credentials)) {
            return back()
                ->withErrors(['email' => '이메일 또는 비밀번호가 올바르지 않습니다.'])
                ->onlyInput('email');
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->first();

        if (!$user->is_active) {
            return back()
                ->withErrors(['email' => '비활성화된 계정입니다. 관리자에게 문의하세요.'])
                ->onlyInput('email');
        }

        // OTP 비활성화 시 즉시 로그인
        if (!config('auth.otp_enabled', true)) {
            Auth::loginUsingId($user->id, $request->boolean('remember'));
            $request->session()->regenerate();
            $this->dispatchCrawlIfNeeded();
            return redirect()->intended(route('dashboard'));
        }

        if (empty($user->phone)) {
            return back()
                ->withErrors(['email' => '등록된 휴대폰 번호가 없습니다. 관리자에게 문의하세요.'])
                ->onlyInput('email');
        }

        // 기존 미사용 OTP 무효화 후 신규 발급
        LoginOtpToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginOtpToken::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'created_at' => now(),
        ]);

        $this->sendOtpSms($user, $code);

        $request->session()->put('2fa_pending', [
            'user_id'  => $user->id,
            'remember' => $request->boolean('remember'),
            'attempts' => 0,
        ]);

        return redirect()->route('login.otp');
    }

    /**
     * 2단계: OTP 입력 페이지
     * GET /login/otp
     */
    public function showOtp(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('2fa_pending')) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::find($request->session()->get('2fa_pending.user_id'));

        return view('auth.otp', [
            'maskedPhone' => $this->maskPhone($user->phone ?? ''),
        ]);
    }

    /**
     * 2단계: OTP 검증 후 최종 로그인
     * POST /login/otp
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $pending = $request->session()->get('2fa_pending');

        if (!$pending) {
            return redirect()->route('login');
        }

        // 시도 횟수 초과
        if (($pending['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            $request->session()->forget('2fa_pending');
            return redirect()->route('login')
                ->withErrors(['email' => '인증 시도 횟수를 초과했습니다. 다시 로그인해 주세요.']);
        }

        $otp = LoginOtpToken::where('user_id', $pending['user_id'])
            ->where('code', $request->input('code'))
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (!$otp) {
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
            $request->session()->put('2fa_pending', $pending);

            $remaining = self::OTP_MAX_ATTEMPTS - $pending['attempts'];
            return back()->withErrors([
                'code' => "인증번호가 올바르지 않거나 만료되었습니다. (남은 시도: {$remaining}회)",
            ]);
        }

        $otp->update(['used_at' => now()]);

        Auth::loginUsingId($pending['user_id'], $pending['remember']);
        $request->session()->forget('2fa_pending');
        $request->session()->regenerate();

        $this->dispatchCrawlIfNeeded();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * OTP 재발송
     * POST /login/otp/resend
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('2fa_pending');

        if (!$pending) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::find($pending['user_id']);

        LoginOtpToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginOtpToken::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'created_at' => now(),
        ]);

        $this->sendOtpSms($user, $code);

        // 재발송 시 시도 횟수 초기화
        $pending['attempts'] = 0;
        $request->session()->put('2fa_pending', $pending);

        return redirect()->route('login.otp')
            ->with('resent', '인증번호를 재발송했습니다.');
    }

    /**
     * Microsoft Entra ID SSO 리다이렉트 (플레이스홀더)
     * GET /auth/sso
     */
    public function ssoRedirect(): RedirectResponse
    {
        return back()->withErrors(['email' => 'SSO 로그인은 현재 준비 중입니다. IT 관리자에게 문의하세요.']);
    }

    /**
     * 로그아웃
     * POST /logout
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ──────────────────────────────────────────────────────────

    private function sendOtpSms(User $user, string $code): void
    {
        try {
            app(MessageService::class)->send(
                $user->phone,
                "[콜로플라스트] 로그인 인증번호: {$code}\n5분 내 입력하세요.",
                $user->name,
            );
        } catch (\Throwable $e) {
            Log::error('2FA OTP SMS 발송 실패', [
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
        // 010-****-5678 형식
        return substr($digits, 0, 3) . '-****-' . substr($digits, -4);
    }

    private function dispatchCrawlIfNeeded(): void
    {
        try {
            if (!InstitutionalNoticeCrawlerService::hasTodayData()) {
                register_shutdown_function(function () {
                    try {
                        (new InstitutionalNoticeCrawlerService())->crawlAll();
                    } catch (\Throwable $e) {
                        Log::error('Login crawl failed', ['error' => $e->getMessage()]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Crawl check failed on login', ['error' => $e->getMessage()]);
        }
    }
}
