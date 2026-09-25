{{-- 모바일 로그인 — 앱의 login_screen 을 그대로 옮긴다 (2026-09-25 지시).

     **앱과 다른 점 하나** — 앱은 브라우저를 띄워 일회용 코드를 받아 앱 토큰으로 바꾼다.
     모바일 웹은 그 자체가 브라우저라 그 춤이 필요 없다. 웹 SSO 를 그대로 타고
     **웹 세션**을 세운다. 세션이 서야 /m 과 /api 가 함께 열린다.

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · Microsoft 단추는 **늘 보인다**. SSO 가 닫혀 있으면 눌렀을 때
         「Microsoft 계정 로그인이 아직 설정되지 않았습니다…」를 알린다
       · 아이디ㆍ비밀번호 길이 닫혀 있을 때, 그 단추를 2초 안에 잇따라 여덟 번
         누르면 입력칸이 열린다 (앱의 뒷문 셈과 같다)
       · 이름표ㆍ안내말ㆍ오류말을 앱과 같은 말로
       · 비밀번호 보기 단추
       · 「로그인 후 SMS 인증번호가 발송됩니다.」 --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0B63CE">
  <title>로그인 — CE Admin</title>
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    :root { --m-primary:#0B63CE; --m-primary-d:#0A4FA6; --m-line:#E5E8EC;
            --m-text:#1B1F26; --m-sub:#667085; --m-mute:#98A2B3; --m-danger:#D32F2F; }
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; width:100%; overflow-x:hidden; }
    body { min-height:100vh; font-family:'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
           background:linear-gradient(160deg, #0D1B3E 0%, #14306B 44%, #1565C0 100%);
           color:#fff; display:flex; flex-direction:column; }
    .wrap { flex:1; display:flex; flex-direction:column; justify-content:center; padding:28px 22px 34px; }

    .brand { text-align:center; margin-bottom:26px; }
    .brand .logo { width:74px; height:74px; border-radius:24px; margin:0 auto 16px;
                   background:linear-gradient(135deg,#1565C0,#0288D1);
                   display:flex; align-items:center; justify-content:center;
                   box-shadow:0 12px 28px rgba(0,0,0,.34); }
    .brand .logo i { font-size:38px; color:#fff; }
    .brand h1 { margin:0; font-size:24px; font-weight:800; letter-spacing:-.4px;
                background:linear-gradient(135deg,#fff,#B3D4FF); -webkit-background-clip:text;
                background-clip:text; color:transparent; }
    .brand p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.62); }

    .ms { width:100%; padding:15px; border:0; border-radius:14px; background:#fff; color:#1B1F26;
          font-size:15px; font-weight:700; font-family:inherit; cursor:pointer;
          display:flex; align-items:center; justify-content:center; gap:10px;
          box-shadow:0 6px 16px rgba(0,0,0,.22); }
    .ms:active { background:#F4F6F9; }
    .ms-logo { width:20px; height:20px; display:grid; grid-template-columns:1fr 1fr; gap:2px; }
    .ms-logo span { display:block; }
    .ms-note { text-align:center; font-size:12px; color:rgba(255,255,255,.6); margin:12px 0 0; }

    .or { display:flex; align-items:center; gap:14px; margin:22px 0 16px;
          color:rgba(255,255,255,.6); font-size:12.5px; }
    .or::before, .or::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.25); }

    .card { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
            border-radius:20px; padding:20px 18px; backdrop-filter:blur(18px); }
    .f { margin-bottom:14px; position:relative; }
    .f label { display:block; font-size:12.5px; font-weight:700; color:rgba(255,255,255,.76); margin-bottom:7px; }
    .f .box { position:relative; display:flex; align-items:center; }
    .f .box > i.lead { position:absolute; left:13px; font-size:18px; color:rgba(255,255,255,.55); }
    .f input { width:100%; padding:14px 44px 14px 40px; border-radius:12px;
               border:1px solid rgba(255,255,255,.24); background:rgba(255,255,255,.1);
               color:#fff; font-size:15.5px; font-family:inherit; }
    .f input::placeholder { color:rgba(255,255,255,.4); }
    .f input:focus { outline:0; border-color:#7FB3F0; background:rgba(255,255,255,.16); }
    .eye { position:absolute; right:8px; background:none; border:0; color:rgba(255,255,255,.6);
           font-size:19px; padding:8px; cursor:pointer; }
    .f .msg { color:#FFC9C9; font-size:12.5px; margin-top:6px; }

    .badge2fa { display:flex; align-items:center; gap:7px; padding:9px 12px; border-radius:10px;
                background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.2);
                color:rgba(255,255,255,.72); font-size:12px; margin-bottom:14px; }

    .go { width:100%; padding:15px; border:0; border-radius:13px; font-size:15.5px; font-weight:700;
          font-family:inherit; cursor:pointer; color:#fff;
          background:linear-gradient(135deg,#1565C0,#0288D1);
          display:flex; align-items:center; justify-content:center; gap:8px; }
    .go:active { filter:brightness(.93); }
    .go[disabled] { opacity:.6; }

    .err { background:rgba(211,47,47,.2); border:1px solid rgba(255,138,138,.5); color:#FFD7D7;
           padding:11px 13px; border-radius:11px; font-size:13.5px; margin-bottom:14px; line-height:1.5; }
    .ok  { background:rgba(18,128,92,.22); border:1px solid rgba(140,224,198,.5); color:#CFF3E6; }

    .toast { position:fixed; left:50%; bottom:26px; transform:translate(-50%, 130%);
             background:#1B1F26; color:#fff; font-size:13.5px; padding:12px 16px;
             border-radius:11px; max-width:88vw; line-height:1.5; z-index:90;
             transition:transform .22s ease; box-shadow:0 8px 24px rgba(0,0,0,.34); }
    .toast.on { transform:translate(-50%, 0); }

    .foot { text-align:center; font-size:11.5px; color:rgba(255,255,255,.45); padding-top:20px; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="logo"><i class="bx bx-plus-medical"></i></div>
    <h1>Coloplast CE Admin</h1>
    <p>의료기기 주문 · 청구 관리</p>
  </div>

  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  @if (session('status'))
    <div class="err ok">{{ session('status') }}</div>
  @endif

  {{-- Microsoft 단추는 늘 보인다 — 앱과 같다 --}}
  <button class="ms" id="msBtn" onclick="msGo()">
    <span class="ms-logo">
      <span style="background:#F25022"></span><span style="background:#7FBA00"></span>
      <span style="background:#00A4EF"></span><span style="background:#FFB900"></span>
    </span>
    Microsoft 계정으로 로그인
  </button>
  <p class="ms-note">임직원은 Microsoft 계정(Entra ID)으로 로그인해 주십시오</p>

  {{-- 아이디ㆍ비밀번호 길이 닫혀 있으면 통째로 감춘다 --}}
  <div id="pwArea" style="display:{{ $password ? 'block' : 'none' }};">
    <div class="or">또는 이메일로 로그인</div>

    <div class="card">
      <form method="POST" action="{{ route('login.store') }}" id="loginForm" novalidate>
        @csrf
        <input type="hidden" name="from" value="m">

        <div class="f">
          <label for="email">이메일</label>
          <div class="box">
            <i class="bx bx-envelope lead"></i>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                   value="{{ old('email') }}" placeholder="name@coloplast.com">
          </div>
          <div class="msg" id="emailErr"></div>
        </div>

        <div class="f">
          <label for="password">비밀번호</label>
          <div class="box">
            <i class="bx bx-lock-alt lead"></i>
            <input id="password" name="password" type="password" autocomplete="current-password"
                   placeholder="비밀번호">
            <button type="button" class="eye" id="eye" onclick="pwToggle()" aria-label="비밀번호 보기">
              <i class="bx bx-hide"></i>
            </button>
          </div>
          <div class="msg" id="pwErr"></div>
        </div>

        <div class="badge2fa">
          <i class="bx bx-shield" style="font-size:15px;"></i>
          로그인 후 SMS 인증번호가 발송됩니다.
        </div>

        <button class="go" type="submit" id="loginBtn">
          <span id="loginTxt">로그인</span> <i class="bx bx-right-arrow-alt"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="foot">모바일 웹 · 문의 1588-7866</div>
</div>

<div class="toast" id="toast"></div>

<script>
  const SSO쓸수있나 = @json((bool) $sso);
  const SSO길      = @json($sso ? route('sso.redirect') : null);
  let 비번보임      = @json((bool) $password);

  let 알림때 = null;
  function 알림(글) {
    const el = document.getElementById('toast');
    el.textContent = 글;
    el.classList.add('on');
    clearTimeout(알림때);
    알림때 = setTimeout(() => el.classList.remove('on'), 3200);
  }

  /* 뒷문 셈 — 2초 안에 잇따라 여덟 번 누르면 입력칸이 열린다 (앱의 _countTapToReveal) */
  const 열쇠누름수 = 8;
  let 누름 = 0, 지난번 = null;

  function 세어열기() {
    const 지금 = Date.now();
    누름 = (지난번 === null || 지금 - 지난번 > 2000) ? 1 : 누름 + 1;
    지난번 = 지금;
    if (누름 < 열쇠누름수) return false;

    누름 = 0;
    비번보임 = true;
    document.getElementById('pwArea').style.display = 'block';
    알림('관리자 로그인 입력란을 표시합니다.');
    return true;
  }

  function msGo() {
    if (!비번보임 && 세어열기()) return;
    if (!SSO쓸수있나) {
      알림('Microsoft 계정 로그인이 아직 설정되지 않았습니다. IT 관리자에게 문의해 주십시오.');
      return;
    }
    location.assign(SSO길);
  }

  function pwToggle() {
    const 칸 = document.getElementById('password');
    const 눈 = document.querySelector('#eye i');
    const 감춤 = 칸.type === 'password';
    칸.type = 감춤 ? 'text' : 'password';
    눈.className = 감춤 ? 'bx bx-show' : 'bx bx-hide';
  }

  document.getElementById('loginForm')?.addEventListener('submit', function (e) {
    const 메일 = document.getElementById('email').value.trim();
    const 비번 = document.getElementById('password').value;
    document.getElementById('emailErr').textContent = 메일 ? '' : '이메일을 입력해 주십시오.';
    document.getElementById('pwErr').textContent    = 비번 ? '' : '비밀번호를 입력해 주십시오.';
    if (!메일 || !비번) { e.preventDefault(); return; }

    document.getElementById('loginBtn').disabled = true;
    document.getElementById('loginTxt').textContent = '로그인 중…';
  });
</script>
</body>
</html>
