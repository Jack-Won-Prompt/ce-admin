{{-- 모바일 OTP — 앱의 otp_screen 을 그대로 옮긴다 (2026-09-25 지시).
     여섯 자리ㆍ인증하기ㆍ재발송(기다리는 초를 세어 보여 준다). --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0B63CE">
  <title>SMS 인증 — CE Admin</title>
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    :root { --m-primary:#0B63CE; --m-primary-d:#0A4FA6; --m-line:#E5E8EC;
            --m-text:#1B1F26; --m-sub:#667085; --m-mute:#98A2B3; --m-danger:#D32F2F; }
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; width:100%; overflow-x:hidden; }
    body { min-height:100vh; font-family:'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
           background:linear-gradient(160deg, #0B63CE 0%, #0A4FA6 40%, #F4F6F9 40%, #F4F6F9 100%);
           color:var(--m-text); display:flex; flex-direction:column; }
    .wrap { flex:1; display:flex; flex-direction:column; justify-content:center; padding:24px 20px 32px; }
    .brand { text-align:center; color:#fff; margin-bottom:24px; }
    .brand .logo { width:58px; height:58px; border-radius:19px; background:rgba(255,255,255,.18);
                   display:flex; align-items:center; justify-content:center; margin:0 auto 11px; }
    .brand .logo i { font-size:29px; }
    .brand h1 { margin:0; font-size:21px; font-weight:800; }
    .card { background:#fff; border-radius:20px; padding:22px 18px; box-shadow:0 14px 40px rgba(11,40,80,.16); }
    .lead { font-size:14px; color:var(--m-sub); line-height:1.6; margin:0 0 18px; text-align:center; }
    .lead b { color:var(--m-text); }

    .code { width:100%; padding:15px; border:1px solid var(--m-line); border-radius:13px;
            font-size:26px; font-weight:800; letter-spacing:11px; text-align:center;
            font-family:inherit; }
    .code:focus { outline:2px solid #E8F1FC; border-color:var(--m-primary); }

    .btn { width:100%; padding:14px; border:0; border-radius:12px; font-size:15.5px;
           font-weight:700; cursor:pointer; background:var(--m-primary); color:#fff; margin-top:14px; }
    .btn:active { background:var(--m-primary-d); }
    .btn[disabled] { background:#C4CBD4; }
    .btn.ghost { background:#fff; color:var(--m-primary); border:1px solid var(--m-line); }
    .btn.ghost[disabled] { background:#fff; color:var(--m-mute); }

    .err { background:#FEF0F0; border:1px solid #F6D3D3; color:var(--m-danger);
           padding:11px 13px; border-radius:11px; font-size:13.5px; margin-bottom:14px; line-height:1.5; }
    .ok  { background:#E7F6F1; border:1px solid #BFE6D9; color:#12805C;
           padding:11px 13px; border-radius:11px; font-size:13.5px; margin-bottom:14px; line-height:1.5; }
    .back { display:block; text-align:center; margin-top:16px; font-size:13px; color:var(--m-sub); }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="logo"><i class="bx bx-message-square-dots"></i></div>
    <h1>SMS 인증</h1>
  </div>

  <div class="card">
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif

    <p class="lead">
      <b>{{ $maskedPhone ?? '등록된 번호' }}</b>으로<br>
      발송된 6자리 인증번호를 입력해 주십시오.
    </p>

    <form method="POST" action="{{ route('login.otp.verify') }}" id="otpForm">
      @csrf
      <input type="hidden" name="from" value="m">
      <input class="code" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
             maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
      <button class="btn" type="submit" id="okBtn" disabled>인증하기</button>
    </form>

    <form method="POST" action="{{ route('login.otp.resend') }}" id="reForm">
      @csrf
      <input type="hidden" name="from" value="m">
      <button class="btn ghost" type="submit" id="reBtn">재발송</button>
    </form>

    <a class="back" href="{{ route('m.login') }}">다른 계정으로 로그인</a>
  </div>
</div>

<script>
  const 칸 = document.getElementById('code');
  const 확인 = document.getElementById('okBtn');

  칸.addEventListener('input', () => {
    칸.value = 칸.value.replace(/\D/g, '').slice(0, 6);
    확인.disabled = 칸.value.length !== 6;
    if (칸.value.length === 6) document.getElementById('otpForm').requestSubmit();
  });

  document.getElementById('otpForm').addEventListener('submit', () => {
    확인.disabled = true; 확인.textContent = '확인 중…';
  });

  /* 재발송은 잇따라 누르지 못하게 초를 센다 — 앱과 같다 */
  const 재발송 = document.getElementById('reBtn');
  let 남은초 = Number(@json($resendCooldown ?? 0));

  function 초세기() {
    if (남은초 > 0) {
      재발송.disabled = true;
      재발송.textContent = `재발송 (${남은초}s)`;
      남은초--;
      setTimeout(초세기, 1000);
    } else {
      재발송.disabled = false;
      재발송.textContent = '재발송';
    }
  }
  초세기();

  document.getElementById('reForm').addEventListener('submit', () => {
    재발송.disabled = true; 재발송.textContent = '보내는 중…';
  });
</script>
</body>
</html>
