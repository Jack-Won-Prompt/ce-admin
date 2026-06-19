<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 로그인</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary:       #1B66F5;
      --primary-dark:  #1250C4;
      --primary-light: #EBF2FF;
      --danger:        #EF4444;
      --text-primary:  #0D1B2A;
      --text-muted:    #8B95A1;
      --border:        #E5E9F0;
      --bg:            #F4F6FA;
      --radius:        10px;
      --shadow:        0 1px 3px rgba(13,27,42,.06), 0 1px 2px rgba(13,27,42,.04);
      --shadow-md:     0 4px 12px rgba(13,27,42,.08), 0 2px 6px rgba(13,27,42,.04);
    }

    html, body { height: 100%; }

    body {
      font-family: 'Pretendard Variable', 'Pretendard', -apple-system, BlinkMacSystemFont,
                   'Apple SD Gothic Neo', 'Noto Sans KR', 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      background: var(--bg);
      -webkit-font-smoothing: antialiased;
    }

    /* ══════════════════════════════════════
       LEFT PANEL
    ══════════════════════════════════════ */
    .auth-left {
      width: 52%;
      background: linear-gradient(150deg, #0A1628 0%, #0D1E3A 50%, #0A1A30 100%);
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-start;
      padding: 60px 72px;
      overflow: hidden;
      min-height: 100vh;
    }

    /* Decorative gradient blobs */
    .auth-left::before {
      content: '';
      position: absolute; top: -160px; right: -120px;
      width: 500px; height: 500px; border-radius: 50%;
      background: radial-gradient(circle, rgba(27,102,245,.18) 0%, transparent 65%);
      pointer-events: none;
    }
    .auth-left::after {
      content: '';
      position: absolute; bottom: -120px; left: -60px;
      width: 400px; height: 400px; border-radius: 50%;
      background: radial-gradient(circle, rgba(27,102,245,.10) 0%, transparent 65%);
      pointer-events: none;
    }

    /* Floating rings */
    .deco-ring {
      position: absolute; border-radius: 50%; pointer-events: none;
      border: 1px solid rgba(27,102,245,.15);
    }
    .deco-ring-1 { width: 280px; height: 280px; top: 10%; right: 6%; animation: floatY 8s ease-in-out infinite; }
    .deco-ring-2 { width: 160px; height: 160px; bottom: 18%; right: 22%; animation: floatY 11s ease-in-out infinite reverse; }
    .deco-ring-3 { width: 80px;  height: 80px;  top: 55%;  right: 4%;  animation: floatY 6s ease-in-out infinite; border-color: rgba(27,102,245,.25); }

    /* Dots */
    .deco-dot { position: absolute; border-radius: 50%; pointer-events: none; background: rgba(27,102,245,.4); }
    .deco-dot-1 { width:8px; height:8px; top:25%; right:38%; }
    .deco-dot-2 { width:5px; height:5px; top:60%; right:45%; animation: pulse 3.5s ease-in-out infinite; }
    .deco-dot-3 { width:10px;height:10px;top:38%;right:10%; animation: pulse 4.5s ease-in-out infinite .5s; }

    @keyframes floatY {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-20px); }
    }
    @keyframes pulse {
      0%,100% { opacity: .4; transform: scale(1); }
      50%     { opacity: 1;  transform: scale(1.3); }
    }

    /* Brand area */
    .left-brand {
      display: flex; align-items: center; gap: 14px;
      margin-bottom: 52px; position: relative; z-index: 2;
    }
    .left-brand-logo {
      width: 48px; height: 48px; border-radius: 13px;
      background: var(--primary);
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-size: 16px; font-weight: 800; letter-spacing: -1px;
      box-shadow: 0 8px 24px rgba(27,102,245,.45);
    }
    .left-brand-text { font-size: 1.4rem; font-weight: 700; color: #fff; letter-spacing: -.4px; }
    .left-brand-sub  { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 2px; letter-spacing: .5px; text-transform: uppercase; }

    /* Headline */
    .left-headline { position: relative; z-index: 2; margin-bottom: 44px; }
    .left-headline h1 {
      font-size: 2.5rem; font-weight: 800; line-height: 1.22;
      color: #fff; letter-spacing: -.6px; margin-bottom: 16px;
    }
    .left-headline h1 em {
      font-style: normal;
      background: linear-gradient(90deg, #93C5FD, #60A5FA, #3B82F6);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .left-headline p {
      font-size: 14.5px; color: rgba(255,255,255,.5); line-height: 1.75;
      max-width: 400px;
    }

    /* Feature list */
    .left-features { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 18px; }
    .left-feature { display: flex; align-items: center; gap: 16px; }
    .left-feature-icon {
      width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 19px;
    }
    .left-feature-icon.blue   { background: rgba(27,102,245,.2);  color: #93C5FD; }
    .left-feature-icon.cyan   { background: rgba(6,182,212,.15);   color: #67E8F9; }
    .left-feature-icon.green  { background: rgba(16,185,129,.15);  color: #6EE7B7; }
    .left-feature-icon.amber  { background: rgba(245,158,11,.15);  color: #FCD34D; }
    .left-feature-title { font-size: 13px; font-weight: 700; color: rgba(255,255,255,.9); }
    .left-feature-desc  { font-size: 11.5px; color: rgba(255,255,255,.38); margin-top: 2px; line-height: 1.5; }

    /* Bottom badges */
    .left-badges {
      position: absolute; bottom: 32px; left: 72px;
      display: flex; gap: 10px; z-index: 2;
    }
    .left-badge {
      display: flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.09);
      border-radius: 20px; padding: 5px 12px;
      font-size: 11.5px; color: rgba(255,255,255,.45);
    }
    .left-badge i { font-size: 13px; color: rgba(147,197,253,.7); }

    /* ══════════════════════════════════════
       RIGHT PANEL
    ══════════════════════════════════════ */
    .auth-right {
      flex: 1;
      display: flex; flex-direction: column;
      justify-content: center; align-items: center;
      padding: 48px 40px;
      background: #fff;
      position: relative; overflow: hidden;
    }

    .auth-right::before {
      content: '';
      position: absolute; top: -100px; right: -100px;
      width: 300px; height: 300px; border-radius: 50%;
      background: radial-gradient(circle, rgba(27,102,245,.04) 0%, transparent 70%);
      pointer-events: none;
    }

    .auth-right-inner {
      width: 100%; max-width: 380px;
      position: relative; z-index: 1;
    }

    /* Tag */
    .auth-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--primary-light); border-radius: 20px;
      padding: 4px 12px; font-size: 11.5px; font-weight: 700;
      color: var(--primary); margin-bottom: 22px; letter-spacing: .3px;
    }
    .auth-tag i { font-size: 13px; }

    .auth-title {
      font-size: 1.75rem; font-weight: 800;
      color: var(--text-primary); margin-bottom: 6px; letter-spacing: -.4px;
    }
    .auth-subtitle {
      font-size: 13.5px; color: var(--text-muted);
      margin-bottom: 30px; line-height: 1.65;
    }

    /* Error alert */
    .alert-error {
      display: flex; align-items: center; gap: 10px;
      background: #FEF2F2; border: 1px solid #FECACA;
      border-left: 3px solid var(--danger);
      border-radius: 10px; padding: 12px 14px;
      color: #B91C1C; font-size: 13px; margin-bottom: 22px;
      animation: shake .35s ease;
    }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%,60%  { transform: translateX(-4px); }
      40%,80%  { transform: translateX(4px); }
    }

    /* Form */
    .form-group { margin-bottom: 18px; }
    .form-label {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 7px;
    }
    .form-label a { font-size: 12px; color: var(--primary); font-weight: 500; text-decoration: none; }
    .form-label a:hover { text-decoration: underline; }

    .input-wrap { position: relative; display: flex; align-items: center; }
    .input-icon {
      position: absolute; left: 14px; font-size: 18px;
      color: var(--text-muted); pointer-events: none; transition: color .2s;
    }
    .input-wrap:focus-within .input-icon { color: var(--primary); }

    .form-control {
      width: 100%; padding: 11px 44px;
      border: 1.5px solid var(--border); border-radius: var(--radius);
      font-size: 14px; color: var(--text-primary); background: var(--bg);
      outline: none; font-family: inherit;
      transition: border-color .2s, background .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--primary); background: #fff;
      box-shadow: 0 0 0 3px rgba(27,102,245,.1);
    }
    .form-control::placeholder { color: #C0C7D0; }
    .form-error { font-size: 12px; color: var(--danger); margin-top: 5px; }

    /* Password toggle */
    .pw-toggle {
      position: absolute; right: 12px;
      background: none; border: none; color: var(--text-muted);
      font-size: 18px; cursor: pointer; padding: 4px; line-height: 1;
      transition: color .18s;
    }
    .pw-toggle:hover { color: var(--primary); }

    /* Remember row */
    .remember-row {
      display: flex; align-items: center; gap: 8px; margin-bottom: 22px;
    }
    .remember-row input[type="checkbox"] {
      width: 16px; height: 16px;
      accent-color: var(--primary); cursor: pointer; flex-shrink: 0;
    }
    .remember-row label { font-size: 13px; color: #4B5563; cursor: pointer; user-select: none; }

    /* Submit */
    .btn-login {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 13px;
      background: var(--primary); color: #fff;
      border: none; border-radius: var(--radius);
      font-size: 15px; font-weight: 700; cursor: pointer;
      font-family: inherit; letter-spacing: -.1px;
      transition: background .2s, box-shadow .2s, transform .12s;
      box-shadow: 0 4px 16px rgba(27,102,245,.35);
    }
    .btn-login:hover { background: var(--primary-dark); box-shadow: 0 6px 22px rgba(27,102,245,.45); }
    .btn-login:active { transform: scale(.99); }

    /* Divider */
    .auth-divider {
      display: flex; align-items: center; gap: 12px;
      margin: 22px 0; font-size: 12px; color: var(--text-muted);
    }
    .auth-divider::before, .auth-divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* SSO button */
    .btn-sso {
      display: flex; align-items: center; justify-content: center; gap: 10px;
      width: 100%; padding: 11px;
      background: #fff; border: 1.5px solid var(--border);
      border-radius: var(--radius); font-size: 14px; font-weight: 600;
      color: var(--text-primary); cursor: pointer; font-family: inherit;
      text-decoration: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .btn-sso:hover {
      border-color: var(--primary); background: var(--primary-light);
      box-shadow: 0 2px 10px rgba(27,102,245,.12);
    }
    .btn-sso svg { width: 20px; height: 20px; flex-shrink: 0; }
    .btn-sso-sub { font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 8px; }

    /* Footer */
    .auth-right-footer {
      position: absolute; bottom: 24px; left: 0; right: 0;
      text-align: center; font-size: 12px; color: var(--text-muted);
    }

    /* Responsive */
    @media (max-width: 900px) {
      .auth-left { display: none; }
      .auth-right { padding: 40px 24px; }
    }
    @media (max-width: 480px) {
      .auth-right { padding: 32px 20px; }
      .auth-title { font-size: 1.5rem; }
    }
  </style>
</head>
<body>

  {{-- ══ LEFT PANEL ══ --}}
  <div class="auth-left">
    <div class="deco-ring deco-ring-1"></div>
    <div class="deco-ring deco-ring-2"></div>
    <div class="deco-ring deco-ring-3"></div>
    <div class="deco-dot deco-dot-1"></div>
    <div class="deco-dot deco-dot-2"></div>
    <div class="deco-dot deco-dot-3"></div>

    {{-- Brand --}}
    <div class="left-brand">
      <div class="left-brand-logo">CE</div>
      <div>
        <div class="left-brand-text">CE Admin</div>
        <div class="left-brand-sub">Coloplast Korea</div>
      </div>
    </div>

    {{-- Headline --}}
    <div class="left-headline">
      <h1>처방전 관리의<br><em>새로운 기준</em></h1>
      <p>OCR 자동화, NHIS 급여 청구, 실시간 주문 연계까지<br>병원 업무를 하나의 플랫폼에서 처리하세요.</p>
    </div>

    {{-- Features --}}
    <div class="left-features">
      <div class="left-feature">
        <div class="left-feature-icon blue"><i class="bx bx-scan"></i></div>
        <div>
          <div class="left-feature-title">AI OCR 자동 인식</div>
          <div class="left-feature-desc">처방전 이미지를 자동으로 분석하고 데이터를 추출</div>
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon cyan"><i class="bx bx-plus-medical"></i></div>
        <div>
          <div class="left-feature-title">NHIS 급여 청구 연동</div>
          <div class="left-feature-desc">건강보험심사평가원과 실시간 급여 청구 처리</div>
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon green"><i class="bx bx-cart-alt"></i></div>
        <div>
          <div class="left-feature-title">주문 자동 연계</div>
          <div class="left-feature-desc">처방 승인 즉시 배송 주문으로 자동 전환</div>
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon amber"><i class="bx bx-bar-chart-alt-2"></i></div>
        <div>
          <div class="left-feature-title">실시간 통합 대시보드</div>
          <div class="left-feature-desc">처방·주문·정산 현황을 한눈에 파악</div>
        </div>
      </div>
    </div>

    {{-- Bottom badges --}}
    <div class="left-badges">
      <div class="left-badge"><i class="bx bx-shield-quarter"></i> 보안 인증</div>
      <div class="left-badge"><i class="bx bx-lock-alt"></i> 데이터 암호화</div>
      <div class="left-badge"><i class="bx bx-time-five"></i> 24/7 운영</div>
    </div>
  </div>

  {{-- ══ RIGHT PANEL ══ --}}
  <div class="auth-right">
    <div class="auth-right-inner">

      <div class="auth-tag">
        <i class="bx bx-log-in"></i> 관리자 전용
      </div>

      <h1 class="auth-title">다시 오셨군요!</h1>
      <p class="auth-subtitle">CE Admin에 접속할 계정을 선택하세요</p>

      {{-- Error --}}
      @if ($errors->any())
        <div class="alert-error">
          <i class="bx bx-error-circle" style="font-size:20px;flex-shrink:0;"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      {{-- Microsoft SSO Button --}}
      <a href="{{ route('sso.redirect') }}" class="btn-sso">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21">
          <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
          <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
          <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
          <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
        </svg>
        Microsoft 계정으로 로그인
      </a>
      <p class="btn-sso-sub">Coloplast 임직원은 Microsoft 계정(Entra ID)으로 로그인하세요</p>

      <div class="auth-divider">임시 로그인</div>

      <form method="POST" action="{{ route('login.store') }}">
        @csrf

        {{-- Email --}}
        <div class="form-group">
          <label class="form-label" for="email">
            <span>이메일 주소</span>
          </label>
          <div class="input-wrap">
            <i class="bx bx-envelope input-icon"></i>
            <input type="email" id="email" name="email"
                   class="form-control"
                   value="{{ old('email', 'admin@ce-admin.co.kr') }}"
                   placeholder="admin@example.com"
                   autofocus>
          </div>
          @error('email')
            <p class="form-error">{{ $message }}</p>
          @enderror
        </div>

        {{-- Password --}}
        <div class="form-group">
          <label class="form-label" for="password">
            <span>비밀번호</span>
            <a href="#">비밀번호 찾기</a>
          </label>
          <div class="input-wrap">
            <i class="bx bx-lock-alt input-icon"></i>
            <input type="password" id="password" name="password"
                   class="form-control"
                   value="12345678"
                   placeholder="••••••••">
            <button type="button" class="pw-toggle" onclick="togglePw()">
              <i class="bx bx-show" id="pwToggleIcon"></i>
            </button>
          </div>
          @error('password')
            <p class="form-error">{{ $message }}</p>
          @enderror
        </div>

        {{-- Remember --}}
        <div class="remember-row">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">로그인 상태 유지</label>
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn-login">
          <i class="bx bx-log-in-circle" style="font-size:20px;"></i>
          로그인
        </button>
      </form>

    </div>

    <div class="auth-right-footer">
      © {{ date('Y') }} Coloplast Korea · CE Admin v2.0
    </div>
  </div>

  <script>
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
