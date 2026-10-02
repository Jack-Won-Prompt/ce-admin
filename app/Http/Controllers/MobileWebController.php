<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * 모바일 웹(H5) — 앱과 같은 화면을 웹으로 낸다 (2026-09-25 지시).
 *
 * **자료는 이 컨트롤러가 나르지 않는다.** 판만 그리고, 화면이 앱과 똑같은
 * `/api/*` 를 부른다 — 같은 코드가 답하므로 앱과 기능이 갈릴 수 없다.
 * 여기서 조회를 따로 짜면 그 순간부터 둘이 어긋나기 시작한다.
 *
 * 인증은 웹 세션으로 통한다. sanctum 가드가 web 가드를 먼저 보므로
 * 로그인한 채로 열면 `/api/*` 가 그대로 답한다(2026-09-25 확인).
 */
class MobileWebController extends Controller
{
    /**
     * 모바일 로그인 — 앱의 login_screen.
     *
     * 무엇을 보일지는 서버가 정한다(앱의 /auth/options 와 같은 잣대) — 화면에서
     * 가리는 것만으로는 닫은 것이 아니다.
     */
    public function login(): View
    {
        return view('mobile.login', [
            'sso'      => \App\Support\SsoSettings::usable(),
            'password' => (bool) config('auth.password_login.web', true),
        ]);
    }

    /**
     * 앱이 들고 온 표로 세션을 세우고 모바일 화면으로 들여보낸다 (2026-10-02 지시).
     *
     * 앱 화면에 결함이 나면 서버 설정(모바일 앱 › 앱 화면 방식)을 「모바일 웹」으로
     * 두어 버틴다. 그때 앱은 자기 화면 대신 이 자리를 WebView 로 연다 — 앱은 앱
     * 토큰을 들고 있고 `/m` 은 웹 세션을 보므로, 그 사이를 한 번 쓰는 표로 잇는다
     * (표는 POST /api/auth/web-ticket 이 낸다).
     *
     * 표는 꺼내는 순간 사라진다. 뒤에 붙는 `to` 는 /m 아래만 받는다 — 밖을 받으면
     * 남의 주소로 보내는 문이 된다.
     */
    public function enter(Request $request): RedirectResponse
    {
        $표 = (string) $request->query('ticket', '');
        $담긴것 = $표 !== '' ? Cache::pull("m:ticket:{$표}") : null;

        $user = $담긴것 ? User::find($담긴것['user_id'] ?? 0) : null;

        if (! $user || ! $user->is_active) {
            return redirect()->route('m.login')->withErrors([
                'email' => '로그인 표가 만료되었습니다. 앱에서 다시 들어와 주십시오.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        /* 나갈 때 모바일 로그인으로 돌아가게 표를 남긴다 — 관리자 로그인 화면은
           앱 안에서 더 낯설다(AuthController::모바일인가 와 같은 자리). */
        $request->session()->put('login_from', 'm');

        $this->남긴다($user, $request);

        return redirect()->to($this->갈곳((string) $request->query('to', '')));
    }

    /** 표를 들고 들어온 자취 — 「누가 언제 앱에서 웹 화면으로 들어왔나」 */
    private function 남긴다(User $user, Request $request): void
    {
        try {
            \App\Models\UserActivityLog::create([
                'user_id'     => $user->id,
                'type'        => 'auth',
                'action'      => 'app_web_enter',
                'reason_text' => '앱에서 모바일 웹 화면으로 들어옴',
                'menu_name'   => '모바일 웹',
                'route_name'  => $request->route()?->getName(),
                'url'         => \App\Models\UserActivityLog::safeUrl($request->fullUrl()),
                'ip_address'  => $request->ip(),
                'user_agent'  => \App\Models\UserActivityLog::safeAgent($request->userAgent()),
            ]);
        } catch (\Throwable $e) {
            // 자취를 못 남겨도 들여보내는 일은 막지 않는다
        }
    }

    /** 들여보낼 자리 — /m 아래가 아니면 모바일 첫 화면으로 */
    private function 갈곳(string $적힌곳): string
    {
        if ($적힌곳 === '' || ! str_starts_with($적힌곳, '/m')) {
            return route('m.home');
        }

        // 「//남의주소」ㆍ「/m\…」 처럼 밖으로 나가는 꼴을 막는다
        if (str_starts_with($적힌곳, '//') || str_contains($적힌곳, '\\')) {
            return route('m.home');
        }

        return url($적힌곳);
    }

    /**
     * 채팅 탭을 보일지 — 앱의 _chatVisible 과 같은 잣대 (2026-09-25 정합성 검증).
     *
     * 서버 설정(환경 설정 ▸ 모바일 앱)을 따른다. 못 읽으면 보인다 — 여태 늘 보였고,
     * 잠깐 설정을 못 읽었다고 메뉴가 사라지면 안 된다.
     */
    private function 채팅보임(): bool
    {
        /* 앱이 /auth/options 로 받는 값과 **같은 자리**를 본다
           (AuthApiController::options → mobile.chat_hidden). 두 곳에서 따로 읽으면
           설정 하나를 바꿔도 한쪽만 달라진다. */
        return ! (bool) config('mobile.chat_hidden', false);
    }

    /** 처방전 목록 — 앱의 prescription_list_screen */
    public function prescriptions(): View
    {
        return view('mobile.prescriptions', ['탭' => 'rx', '채팅보임' => $this->채팅보임()]);
    }

    /** 처방전 상세 — 앱의 prescription_detail_screen */
    public function prescription(string $rx_number): View
    {
        return view('mobile.prescription', ['탭' => 'rx', 'rxNumber' => $rx_number, '채팅보임' => $this->채팅보임()]);
    }

    /** 처방자료 업로드 — 앱의 prescription_upload_screen */
    public function upload(): View
    {
        return view('mobile.upload', ['탭' => 'upload', '채팅보임' => $this->채팅보임()]);
    }

    /** 채팅 목록 — 앱의 chat_list_screen */
    public function chat(): View
    {
        return view('mobile.chat', ['탭' => 'chat', '채팅보임' => $this->채팅보임()]);
    }

    /** 채팅방 — 앱의 chat_room_screen */
    public function chatRoom(int $room): View
    {
        return view('mobile.chat-room', ['탭' => 'chat', 'roomId' => $room, '채팅보임' => $this->채팅보임()]);
    }

    /** 설정 — 앱의 settings_screen */
    public function settings(): View
    {
        return view('mobile.settings', ['탭' => 'settings', '채팅보임' => $this->채팅보임()]);
    }

    /** 알림 이력 — 앱의 notification_list_screen */
    public function notifications(): View
    {
        return view('mobile.notifications', ['탭' => 'settings', '채팅보임' => $this->채팅보임()]);
    }

    /** 공지사항 — 앱의 notice_list_screen */
    public function notices(): View
    {
        return view('mobile.notices', ['탭' => 'settings', '채팅보임' => $this->채팅보임()]);
    }

    /** 공지 상세 — 앱의 notice_detail_screen */
    public function notice(int $id): View
    {
        return view('mobile.notice', ['탭' => 'settings', 'noticeId' => $id, '채팅보임' => $this->채팅보임()]);
    }

    /** 문의 목록 — 앱의 inquiry_list_screen */
    public function inquiries(): View
    {
        return view('mobile.inquiries', ['탭' => 'settings', '채팅보임' => $this->채팅보임()]);
    }

    /** 문의 등록 — 앱의 inquiry_create_screen */
    public function inquiryCreate(): View
    {
        return view('mobile.inquiry-create', ['탭' => 'settings', '채팅보임' => $this->채팅보임()]);
    }

    /** 문의 상세 — 앱의 inquiry_detail_screen */
    public function inquiry(int $id): View
    {
        return view('mobile.inquiry', ['탭' => 'settings', 'inquiryId' => $id, '채팅보임' => $this->채팅보임()]);
    }
}
