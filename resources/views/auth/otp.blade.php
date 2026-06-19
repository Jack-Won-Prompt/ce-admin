<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 2차 인증</title>
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
      --success:       #10B981;
      --success-light: #ECFDF5;
      --text-primary:  #0D1B2A;
      --text-muted:    #8B95A1;
      --border:        #E5E9F0;
      --bg:            #F4F6FA;
      --radius:        10px;
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

    /* ══ LEFT PANEL ══ */
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

    .deco-ring {
      position: absolute; border-radius: 50%; pointer-events: none;
      border: 1px solid rgba(27,102,245,.15);
    }
    .deco-ring-1 { width: 280px; height: 280px; top: 10%; right: 6%; animation: floatY 8s ease-in-out infinite; }
    .deco-ring-2 { width: 160px; height: 160px; bottom: 18%; right: 22%; animation: floatY 11s ease-in-out infinite reverse; }
    .deco-ring-3 { width: 80px;  height: 80px;  top: 55%;  right: 4%;  animation: floatY 6s ease-in-out infinite; border-color: rgba(27,102,245,.25); }

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

    /* OTP 설명 영역 */
    .left-otp-info { position: relative; z-index: 2; }
    .left-otp-icon {
      width: 72px; height: 72px; border-radius: 20px;
      background: rgba(27,102,245,.2); border: 1px solid rgba(27,102,245,.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 36px; color: #93C5FD; margin-bottom: 28px;
    }
    .left-otp-info h2 {
      font-size: 2rem; font-weight: 800; color: #fff;
      letter-spacing: -.5px; line-height: 1.25; margin-bottom: 16px;
    }
    .left-otp-info h2 em {
      font-style: normal;
      background: linear-gradient(90deg, #93C5FD, #60A5FA);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .left-otp-info p {
      font-size: 14px; color: rgba(255,255,255,.5); line-height: 1.75; max-width: 380px;
    }

    .otp-steps { margin-top: 36px; display: flex; flex-direction: column; gap: 16px; }
    .otp-step { display: flex; align-items: flex-start; gap: 14px; }
    .otp-step-num {
      width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
      background: rgba(27,102,245,.25); border: 1px solid rgba(27,102,245,.4);
      color: #93C5FD; font-size: 12px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }
    .otp-step-text { font-size: 13px; color: rgba(255,255,255,.55); line-height: 1.6; padding-top: 3px; }

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

    /* ══ RIGHT PANEL ══ */
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

    .auth-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--primary-light); border-radius: 20px;
      padding: 4px 12px; font-size: 11.5px; font-weight: 700;
      color: var(--primary); margin-bottom: 22px; letter-spacing: .3px;
    }

    .auth-title {
      font-size: 1.75rem; font-weight: 800;
      color: var(--text-primary); margin-bottom: 6px; letter-spacing: -.4px;
    }
    .auth-subtitle {
      font-size: 13.5px; color: var(--text-muted);
      margin-bottom: 30px; line-height: 1.65;
    }
    .auth-subtitle strong { color: var(--text-primary); }

    /* 알림 */
    .alert-error {
      display: flex; align-items: center; gap: 10px;
      background: #FEF2F2; border: 1px solid #FECACA;
      border-left: 3px solid var(--danger);
      border-radius: 10px; padding: 12px 14px;
      color: #B91C1C; font-size: 13px; margin-bottom: 22px;
      animation: shake .35s ease;
    }
    .alert-success {
      display: flex; align-items: center; gap: 10px;
      background: var(--success-light); border: 1px solid #A7F3D0;
      border-left: 3px solid var(--success);
      border-radius: 10px; padding: 12px 14px;
      color: #065F46; font-size: 13px; margin-bottom: 22px;
    }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%,60%  { transform: translateX(-4px); }
      40%,80%  { transform: translateX(4px); }
    }

    /* OTP 6자리 입력 박스 */
    .otp-inputs {
      display: flex; gap: 10px; justify-content: center;
      margin-bottom: 24px;
    }
    .otp-digit {
      width: 52px; height: 60px;
      border: 1.5px solid var(--border); border-radius: var(--radius);
      font-size: 1.6rem; font-weight: 800;
      color: var(--text-primary); background: var(--bg);
      text-align: center; outline: none; font-family: inherit;
      transition: border-color .2s, box-shadow .2s, background .2s;
      caret-color: var(--primary);
    }
    .otp-digit:focus {
      border-color: var(--primary); background: #fff;
      box-shadow: 0 0 0 3px rgba(27,102,245,.1);
    }
    .otp-digit.filled {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary);
    }
    .otp-digit.error {
      border-color: var(--danger);
      background: #FEF2F2;
      color: var(--danger);
    }

    /* 숨겨진 실제 input */
    #codeInput { display: none; }

    .form-error { font-size: 12px; color: var(--danger); text-align: center; margin-bottom: 16px; }

    /* 타이머 */
    .otp-timer {
      text-align: center; font-size: 13px;
      color: var(--text-muted); margin-bottom: 20px;
    }
    .otp-timer span { font-weight: 700; color: var(--primary); }
    .otp-timer.expiring span { color: var(--danger); }

    .btn-login {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 13px;
      background: var(--primary); color: #fff;
      border: none; border-radius: var(--radius);
      font-size: 15px; font-weight: 700; cursor: pointer;
      font-family: inherit; letter-spacing: -.1px;
      transition: background .2s, box-shadow .2s, transform .12s;
      box-shadow: 0 4px 16px rgba(27,102,245,.35);
      margin-bottom: 16px;
    }
    .btn-login:hover  { background: var(--primary-dark); box-shadow: 0 6px 22px rgba(27,102,245,.45); }
    .btn-login:active { transform: scale(.99); }
    .btn-login:disabled {
      background: #C0C7D0; box-shadow: none; cursor: not-allowed; transform: none;
    }

    .resend-row {
      text-align: center; font-size: 13px; color: var(--text-muted);
      margin-bottom: 20px;
    }
    .resend-row button {
      background: none; border: none; color: var(--primary);
      font-size: 13px; font-weight: 600; cursor: pointer;
      font-family: inherit; padding: 0;
    }
    .resend-row button:hover { text-decoration: underline; }
    .resend-row button:disabled { color: var(--text-muted); cursor: not-allowed; text-decoration: none; }

    .back-link {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      font-size: 13px; color: var(--text-muted); text-decoration: none;
      transition: color .2s;
    }
    .back-link:hover { color: var(--primary); }

    .auth-right-footer {
      position: absolute; bottom: 24px; left: 0; right: 0;
      text-align: center; font-size: 12px; color: var(--text-muted);
    }

    @media (max-width: 900px) {
      .auth-left { display: none; }
      .auth-right { padding: 40px 24px; }
    }
    @media (max-width: 480px) {
      .auth-right { padding: 32px 20px; }
      .otp-digit { width: 44px; height: 54px; font-size: 1.4rem; }
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

    <div class="left-brand">
      <div class="left-brand-logo">CE</div>
      <div>
        <div class="left-brand-text">CE Admin</div>
        <div class="left-brand-sub">Coloplast Korea</div>
      </div>
    </div>

    <div class="left-otp-info">
      <div class="left-otp-icon">
        <i class="bx bx-mobile-alt"></i>
      </div>
      <h2>2단계 인증으로<br><em>보안을 강화</em>합니다</h2>
      <p>계정 보안을 위해 등록된 휴대폰으로 발송된<br>6자리 인증번호를 입력해 주세요.</p>

      <div class="otp-steps">
        <div class="otp-step">
          <div class="otp-step-num">1</div>
          <div class="otp-step-text">등록된 휴대폰으로 6자리 인증번호 SMS가 발송됩니다</div>
        </div>
        <div class="otp-step">
          <div class="otp-step-num">2</div>
          <div class="otp-step-text">수신한 인증번호를 5분 이내에 입력하세요</div>
        </div>
        <div class="otp-step">
          <div class="otp-step-num">3</div>
          <div class="otp-step-text">인증 완료 후 CE Admin 대시보드로 이동합니다</div>
        </div>
      </div>
    </div>

    <div class="left-badges">
      <div class="left-badge"><i class="bx bx-shield-quarter"></i> 2차 인증</div>
      <div class="left-badge"><i class="bx bx-lock-alt"></i> 데이터 암호화</div>
      <div class="left-badge"><i class="bx bx-time-five"></i> 24/7 운영</div>
    </div>
  </div>

  {{-- ══ RIGHT PANEL ══ --}}
  <div class="auth-right">
    <div class="auth-right-inner">

      <div class="auth-tag">
        <i class="bx bx-shield-quarter"></i> 2차 인증
      </div>

      <h1 class="auth-title">인증번호 확인</h1>
      <p class="auth-subtitle">
        <strong>{{ $maskedPhone }}</strong> 으로 발송된<br>
        6자리 인증번호를 입력해 주세요.
      </p>

      {{-- 재발송 성공 알림 --}}
      @if (session('resent'))
        <div class="alert-success">
          <i class="bx bx-check-circle" style="font-size:20px;flex-shrink:0;"></i>
          <span>{{ session('resent') }}</span>
        </div>
      @endif

      {{-- 에러 --}}
      @if ($errors->has('code'))
        <div class="alert-error">
          <i class="bx bx-error-circle" style="font-size:20px;flex-shrink:0;"></i>
          <span>{{ $errors->first('code') }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('login.otp.verify') }}" id="otpForm">
        @csrf
        <input type="hidden" name="code" id="codeInput">

        {{-- 6자리 개별 박스 --}}
        <div class="otp-inputs" id="otpBoxes">
          @for ($i = 0; $i < 6; $i++)
            <input type="text"
                   inputmode="numeric"
                   maxlength="1"
                   class="otp-digit{{ $errors->has('code') ? ' error' : '' }}"
                   autocomplete="one-time-code"
                   data-idx="{{ $i }}">
          @endfor
        </div>

        @error('code')
          <p class="form-error">{{ $message }}</p>
        @enderror

        {{-- 타이머 --}}
        <div class="otp-timer" id="otpTimer">
          인증번호 유효시간: <span id="timerDisplay">05:00</span>
        </div>

        <button type="submit" class="btn-login" id="submitBtn" disabled>
          <i class="bx bx-check-shield" style="font-size:20px;"></i>
          인증 완료
        </button>
      </form>

      {{-- 재발송 --}}
      <div class="resend-row">
        인증번호를 받지 못하셨나요?
        <form method="POST" action="{{ route('login.otp.resend') }}" style="display:inline;">
          @csrf
          <button type="submit" id="resendBtn">재발송</button>
        </form>
      </div>

      <a href="{{ route('login') }}" class="back-link">
        <i class="bx bx-arrow-back"></i> 다른 계정으로 로그인
      </a>

    </div>

    <div class="auth-right-footer">
      © {{ date('Y') }} Coloplast Korea · CE Admin v2.0
    </div>
  </div>

  <script>
    // ── OTP 박스 컨트롤 ──────────────────────────────────────
    const digits  = document.querySelectorAll('.otp-digit');
    const hidden  = document.getElementById('codeInput');
    const submit  = document.getElementById('submitBtn');

    function syncHidden() {
      const val = [...digits].map(d => d.value).join('');
      hidden.value = val;
      submit.disabled = val.length < 6;
      digits.forEach((d, i) => {
        d.classList.toggle('filled', d.value !== '');
        d.classList.remove('error');
      });
    }

    digits.forEach((el, i) => {
      el.addEventListener('input', e => {
        // 숫자만 허용
        el.value = el.value.replace(/\D/g, '').slice(-1);
        syncHidden();
        if (el.value && i < 5) digits[i + 1].focus();
      });

      el.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !el.value && i > 0) {
          digits[i - 1].value = '';
          digits[i - 1].focus();
          syncHidden();
        }
        if (e.key === 'ArrowLeft'  && i > 0) digits[i - 1].focus();
        if (e.key === 'ArrowRight' && i < 5) digits[i + 1].focus();
      });

      // 붙여넣기
      el.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData)
          .getData('text').replace(/\D/g, '').slice(0, 6);
        text.split('').forEach((c, j) => {
          if (digits[j]) digits[j].value = c;
        });
        syncHidden();
        const nextEmpty = [...digits].findIndex(d => !d.value);
        (digits[nextEmpty >= 0 ? nextEmpty : 5]).focus();
      });
    });

    // 첫 박스에 자동 포커스
    digits[0].focus();

    // ── 타이머 ─────────────────────────────────────────────
    const timerEl  = document.getElementById('timerDisplay');
    const timerDiv = document.getElementById('otpTimer');
    let   seconds  = 5 * 60;

    const tick = setInterval(() => {
      seconds--;
      const m = String(Math.floor(seconds / 60)).padStart(2, '0');
      const s = String(seconds % 60).padStart(2, '0');
      timerEl.textContent = `${m}:${s}`;

      if (seconds <= 60) timerDiv.classList.add('expiring');

      if (seconds <= 0) {
        clearInterval(tick);
        timerEl.textContent = '만료됨';
        submit.disabled = true;
        submit.textContent = '인증번호가 만료되었습니다';
      }
    }, 1000);

    // ── 재발송 쿨다운 (30초) ──────────────────────────────
    const resendBtn = document.getElementById('resendBtn');
    let   resendCd  = 0;

    function startResendCooldown(sec) {
      resendCd = sec;
      resendBtn.disabled = true;
      const t = setInterval(() => {
        resendCd--;
        resendBtn.textContent = `재발송 (${resendCd}초)`;
        if (resendCd <= 0) {
          clearInterval(t);
          resendBtn.textContent = '재발송';
          resendBtn.disabled = false;
        }
      }, 1000);
    }

    @if(session('resent'))
      startResendCooldown(30);
    @else
      // 첫 진입 후 10초 쿨다운
      startResendCooldown(10);
    @endif
  </script>

</body>
</html>
