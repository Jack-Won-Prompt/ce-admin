<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  {{-- 프레임 안에서 열리면 창 전체를 이 주소로 옮긴다.
       화면 탭이 iframe 이라, 세션이 끊긴 뒤에는 이 화면이 워크스페이스 안에 조각처럼
       박혀 보인다. 서버(BreakFrameOnGuest)가 대부분 막지만, 브라우저가 뒤로가기로
       캐시에서 되살리는 경우처럼 서버를 거치지 않는 길도 있어 화면에도 한 겹 둔다. --}}
  <script>
    if (window.self !== window.top) {
      try { window.top.location.replace(window.location.href); }
      catch (e) { window.location.replace(window.location.href); }
    }
  </script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 로그인</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF_Medium.woff2" as="font" type="font/woff2" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  {{-- 시안: Figma Website › 로그인 (505:9424) — 왼쪽 640 흰 카드, 오른쪽 청록 판에 그림. --}}
  <style>
@include('partials._design-tokens')
@include('partials._website-font')

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body { height: 100%; }
    body {
      font-family: 'NEXON Lv2 Gothic OTF', 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;
      min-height: 100vh;
      display: flex;
      padding: 16px;
      background: var(--primary-500);
      color: var(--gray-1000);
      word-break: keep-all;
      -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; text-decoration: none; }
    button, input { font: inherit; }
    .sr-only {
      position: absolute; width: 1px; height: 1px; overflow: hidden;
      clip: rect(0 0 0 0); white-space: nowrap; border: 0;
    }

    /* ── 왼쪽 카드 (505:9453) — 세 덩어리를 60 간격으로 세로 가운데 ── */
    .lg-card {
      position: relative; flex: 0 0 640px; width: 640px; min-height: calc(100vh - 32px);
      display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 60px;
      padding: 0 80px; border-radius: 32px; background: var(--gray-0);
    }
    .lg-head { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
    .lg-head h1 { font-size: 32px; font-weight: 500; line-height: 45px; color: var(--gray-1000); }
    .lg-head p { font-size: 16px; font-weight: 500; line-height: 27px; color: var(--gray-600); white-space: nowrap; }

    /* 오류 — 시안에 없는 상태. 알림 램프로 칠한다 */
    .alert-error {
      align-self: stretch; margin: -36px 0; display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; border: 1px solid var(--alert-100); border-left: 3px solid var(--alert-500);
      border-radius: 12px; background: var(--alert-50); font-size: 14px; line-height: 1.6; color: var(--gray-900);
    }

    /* Microsoft SSO (505:9457) */
    .lg-sso { width: 440px; max-width: 100%; display: flex; flex-direction: column; align-items: center; gap: 12px; }
    .btn-sso {
      width: 100%; height: 50px; display: flex; align-items: center; justify-content: center; gap: 12px;
      padding: 0 16px; border: 1px solid var(--gray-200); border-radius: 12px; background: var(--gray-0);
      font-size: 16px; font-weight: 500; line-height: 27px; color: var(--gray-1000); cursor: pointer;
      transition: border-color .2s, background-color .2s, box-shadow .2s;
    }
    .btn-sso:hover { border-color: var(--primary-500); background: var(--primary-50); box-shadow: 0 4px 14px rgba(40, 121, 139, .12); }
    .btn-sso img { width: 20px; height: 20px; flex: none; }
    .btn-sso-sub { font-size: 14px; font-weight: 400; line-height: 24px; color: var(--gray-500); text-align: center; white-space: nowrap; }

    /* 임시 로그인 (505:9462) — 구분선 · 입력 · 상태 유지 · 단추, 24 간격 */
    .lg-pw { align-self: stretch; display: flex; flex-direction: column; gap: 24px; }
    .lg-pw form { display: flex; flex-direction: column; gap: 24px; }
    .auth-divider { display: flex; align-items: center; gap: 24px; font-size: 14px; font-weight: 400; line-height: 24px; color: var(--gray-700); white-space: nowrap; }
    .auth-divider::before, .auth-divider::after { content: ''; flex: 1 1 0; height: 1px; background: var(--gray-200); }

    .lg-inputs { display: flex; flex-direction: column; gap: 12px; }
    .input-wrap { position: relative; }
    .form-control {
      width: 100%; height: 50px; padding: 0 16px; border: 1px solid var(--gray-200); border-radius: 12px; background: var(--gray-0);
      font-size: 16px; font-weight: 400; line-height: 27px; color: var(--gray-1000); outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control::placeholder { color: var(--gray-500); opacity: 1; }
    .form-control:focus { border-color: var(--primary-500); box-shadow: 0 0 0 3px rgba(40, 121, 139, .12); }
    .input-wrap .form-control[type="password"], .input-wrap .form-control.has-toggle { padding-right: 48px; }    .form-error { margin-top: -4px; font-size: 13px; line-height: 1.6; color: var(--alert-500); }

    /* 비밀번호 보기 — 시안에는 없지만 쓰던 기능이라 칸 안 오른쪽에 조용히 둔다 */
    .pw-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
      border: 0; background: none; color: var(--gray-400); font-size: 18px; cursor: pointer; transition: color .2s;
    }
    .pw-toggle:hover { color: var(--primary-500); }

    .lg-find { align-self: flex-end; font-size: 14px; font-weight: 400; line-height: 24px; color: var(--primary-700); }
    .lg-find:hover { text-decoration: underline; }

    /* 로그인 상태 유지 (505:9473) — 22 칸, 청록 1px 테두리, 고르면 청록 체크 */
    .remember-row { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;
      font-size: 16px; font-weight: 500; line-height: 27px; color: var(--primary-500); }
    .remember-row input { position: absolute; opacity: 0; pointer-events: none; }
    .lg-check {
      flex: none; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
      border: 1px solid var(--primary-500); border-radius: 8px; background: var(--gray-0);
    }
    .lg-check::after {
      content: ''; width: 19.25px; height: 19.25px; opacity: 0; transition: opacity .15s;
      background: url('{{ asset('images/website/icons/check-login-20.svg') }}') center/contain no-repeat;
    }
    .remember-row input:checked + .lg-check::after { opacity: 1; }
    .remember-row input:focus-visible + .lg-check { box-shadow: 0 0 0 3px rgba(40, 121, 139, .2); }

    .btn-login {
      width: 100%; height: 50px; display: flex; align-items: center; justify-content: center;
      padding: 0 16px; border: 0; border-radius: 12px; background: var(--primary-500);
      font-size: 16px; font-weight: 500; line-height: 27px; color: var(--gray-0); cursor: pointer;
      transition: background-color .2s, box-shadow .2s;
    }
    .btn-login:hover { background: var(--primary-600); box-shadow: 0 8px 22px rgba(11, 92, 110, .28); }

    .auth-footer {
      position: absolute; left: 0; right: 0; bottom: 24px;
      text-align: center; font-size: 12px; line-height: 1.6; color: var(--gray-400);
    }

    /* ── 오른쪽 판 (505:9481) — 그림을 가운데에 ── */
    .lg-visual { flex: 1 1 0; min-width: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .lg-visual img { width: 1089.55px; max-width: 100%; height: auto; }

    @media (max-width: 1100px) {
      .lg-visual img { max-width: calc(100% - 48px); }
    }
    @media (max-width: 900px) {
      body { padding: 12px; }
      .lg-visual { display: none; }
      .lg-card { flex: 1 1 auto; width: auto; min-width: 0; padding: 48px 24px 72px; gap: 40px; border-radius: 28px; }
      .lg-sso { width: 100%; }
      .alert-error { margin: -24px 0; }
    }
    @media (max-width: 480px) {
      .lg-head h1 { font-size: 26px; }
      .lg-head p, .btn-sso-sub { white-space: normal; }
    }
  </style>
</head>
<body>

  {{-- ══ 왼쪽 카드 ══ --}}
  <main class="lg-card">
    <div class="lg-head">
      <h1>로그인</h1>
      <p>CE Admin에 접속할 계정을 선택하세요.</p>
    </div>

    {{-- Error --}}
    @if ($errors->any())
      <div class="alert-error">
        <i class="bx bx-error-circle" style="font-size:20px;flex-shrink:0;color:var(--alert-500);"></i>
        <span>{{ $errors->first() }}</span>
      </div>
    @endif

    {{-- Microsoft SSO Button --}}
    <div id="ssoArea" class="lg-sso">
      <a href="{{ route('sso.redirect') }}" class="btn-sso">
        <img src="{{ asset('images/website/login/ms-logo.png') }}" width="20" height="20" alt="">
        Microsoft 계정으로 로그인
      </a>
      <p class="btn-sso-sub">Coloplast 임직원은 Microsoft 계정(Entra ID)으로 로그인하세요.</p>
    </div>

    {{-- 아이디ㆍ비밀번호 길은 설정으로 여닫는다(설정 › 서비스 연동 설정 › 로그인).
         끄면 이 자리가 감춰지고 Microsoft 계정만 남는다. 다만 아주 없애지는 않는다 —
         위 SSO 자리를 여덟 번 잇달아 누르면 다시 나온다. SSO 가 말썽일 때 고칠
         사람까지 갇히면 안 되기 때문이다. 그때 실제로 들어올 수 있는 것은 관리자
         뿐이고, 그 가림은 AuthController::store() 가 한다 — 화면을 감추는 것만으로는
         닫은 것이 아니다. --}}
    <div id="pwArea" class="lg-pw" @unless (config('auth.password_login.web', true)) style="display:none" @endunless>
      <div class="auth-divider">임시 로그인</div>

      <form method="POST" action="{{ route('login.store') }}">
        @csrf

        {{-- 시안은 칸 위 이름표 없이 자리표시만 둔다. 이름표는 읽기 도구를 위해 남기고 화면에서만 감춘다. --}}
        <div class="lg-inputs">
          <label class="sr-only" for="email">이메일 주소</label>
          <input type="email" id="email" name="email"
                 class="form-control"
                 value="{{ old('email', 'admin@ce-admin.co.kr') }}"
                 placeholder="이메일 주소 입력"
                 autofocus>
          @error('email')
            <p class="form-error">{{ $message }}</p>
          @enderror

          <label class="sr-only" for="password">비밀번호</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password"
                   class="form-control has-toggle"
                   value="12345678"
                   placeholder="비밀번호 입력">
            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="비밀번호 보기">
              <i class="bx bx-show" id="pwToggleIcon"></i>
            </button>
          </div>
          @error('password')
            <p class="form-error">{{ $message }}</p>
          @enderror

          <a class="lg-find" href="#">비밀번호 찾기</a>
        </div>

        {{-- Remember --}}
        <label class="remember-row" for="remember">
          <input type="checkbox" id="remember" name="remember">
          <span class="lg-check" aria-hidden="true"></span>
          로그인 상태 유지
        </label>

        {{-- Submit --}}
        <button type="submit" class="btn-login">로그인</button>
      </form>
    </div>

    {{-- 시안 바닥글과 같은 ⓒ (U+24D2) --}}
    <div class="auth-footer">
      ⓒ {{ date('Y') }} Coloplast Korea · CE Admin v2.0
    </div>
  </main>

  {{-- ══ 오른쪽 판 ══ --}}
  <div class="lg-visual" aria-hidden="true">
    <img src="{{ asset('images/website/login/illustration.svg') }}" width="1090" height="800" alt="">
  </div>

  <script>
    /* SSO 자리를 여덟 번 잇달아 누르면 아이디ㆍ비밀번호 칸이 나온다.
       SSO 가 말썽일 때 고칠 사람까지 갇히지 않도록 남겨 둔 뒷문이다.
       나온다고 아무나 들어오는 것은 아니다 — 서버가 관리자만 받는다.

       셈은 sessionStorage 에 둔다. 단추가 링크라 누를 때마다 화면이 다시 뜨는데,
       변수에 담으면 그때마다 0 으로 돌아가 여덟 번을 채울 수 없다.
       두 걸음 사이가 2초를 넘으면 처음부터 다시 센다 — 어쩌다 눌린 것까지 세면
       뜻하지 않게 열린다. */
    @unless (config('auth.password_login.web', true))
    (function () {
      const NEEDED = 8, GAP = 2000;
      const KEY_N = 'pwTapCount', KEY_T = 'pwTapAt', KEY_OPEN = 'pwAreaOpen';
      const area = document.getElementById('ssoArea');
      const pw   = document.getElementById('pwArea');
      if (!area || !pw) return;

      const open = () => { pw.style.display = ''; sessionStorage.setItem(KEY_OPEN, '1'); };

      if (sessionStorage.getItem(KEY_OPEN) === '1') open();

      area.addEventListener('click', function () {
        const now  = Date.now();
        const last = parseInt(sessionStorage.getItem(KEY_T) || '0', 10);
        const n    = (now - last > GAP ? 0 : parseInt(sessionStorage.getItem(KEY_N) || '0', 10)) + 1;

        sessionStorage.setItem(KEY_N, String(n));
        sessionStorage.setItem(KEY_T, String(now));

        if (n >= NEEDED) {
          sessionStorage.removeItem(KEY_N);
          open();
        }
      });
    })();
    @endunless

    function togglePw() {
      const input = document.getElementById('password');
      const icon  = document.getElementById('pwToggleIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-hide';
      } else {
        input.type = 'password';
        icon.className = 'bx bx-show';
      }
    }
  </script>

</body>
</html>
