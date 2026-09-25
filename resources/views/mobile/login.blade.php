{{-- 모바일 로그인 — 앱의 login_screen 을 그대로 옮긴다 (2026-09-25 지시).

     앱에 있는 것: Microsoft 계정(SSO) 단추 · 「또는 이메일로 로그인」 · 이메일 · 비밀번호.

     **앱과 다른 점 하나** — 앱은 브라우저를 띄워 일회용 코드를 받아 앱 토큰으로 바꾼다.
     모바일 웹은 그 자체가 브라우저라 그 춤이 필요 없다. 웹 SSO 를 그대로 타고
     **웹 세션**을 세운다. 세션이 서야 /m 과 /api 가 함께 열린다. --}}
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
           background:linear-gradient(160deg, #0B63CE 0%, #0A4FA6 46%, #F4F6F9 46%, #F4F6F9 100%);
           color:var(--m-text); display:flex; flex-direction:column; }
    .wrap { flex:1; display:flex; flex-direction:column; justify-content:center; padding:24px 20px 32px; }
    .brand { text-align:center; color:#fff; margin-bottom:26px; }
    .brand .logo { width:62px; height:62px; border-radius:20px; background:rgba(255,255,255,.18);
                   display:flex; align-items:center; justify-content:center; margin:0 auto 12px; }
    .brand .logo i { font-size:32px; }
    .brand h1 { margin:0; font-size:23px; font-weight:800; letter-spacing:-.4px; }
    .brand p  { margin:4px 0 0; font-size:13px; opacity:.88; }

    .card { background:#fff; border-radius:20px; padding:22px 18px;
            box-shadow:0 14px 40px rgba(11,40,80,.16); }

    .f { margin-bottom:13px; }
    .f label { display:block; font-size:12.5px; font-weight:700; color:var(--m-sub); margin-bottom:6px; }
    .f input { width:100%; padding:13px; border:1px solid var(--m-line); border-radius:12px;
               font-size:15.5px; font-family:inherit; }
    .f input:focus { outline:2px solid #E8F1FC; border-color:var(--m-primary); }

    .btn { width:100%; padding:14px; border:0; border-radius:12px; font-size:15.5px;
           font-weight:700; cursor:pointer; background:var(--m-primary); color:#fff; }
    .btn:active { background:var(--m-primary-d); }
    .btn[disabled] { background:#C4CBD4; }
    .btn.ms { background:#fff; color:#1B1F26; border:1px solid var(--m-line);
              display:flex; align-items:center; justify-content:center; gap:9px; }
    .btn.ms:active { background:#F4F6F9; }
    .ms-logo { width:19px; height:19px; display:grid; grid-template-columns:1fr 1fr; gap:2px; }
    .ms-logo span { display:block; }

    .or { display:flex; align-items:center; gap:10px; margin:18px 0; color:var(--m-mute); font-size:12.5px; }
    .or::before, .or::after { content:''; flex:1; height:1px; background:var(--m-line); }

    .err { background:#FEF0F0; border:1px solid #F6D3D3; color:var(--m-danger);
           padding:11px 13px; border-radius:11px; font-size:13.5px; margin-bottom:14px; line-height:1.5; }

    .foot { text-align:center; font-size:11.5px; color:var(--m-mute); padding-top:18px; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="logo"><i class="bx bxs-capsule"></i></div>
    <h1>CE Admin</h1>
    <p>콜로플라스트 코리아</p>
  </div>

  <div class="card">
    @if ($errors->any())
      <div class="err">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
      <div class="err" style="background:#E7F6F1; border-color:#BFE6D9; color:#12805C;">{{ session('status') }}</div>
    @endif

    @if ($sso)
      <button class="btn ms" onclick="location.assign(@js(route('sso.redirect')))">
        <span class="ms-logo">
          <span style="background:#F25022"></span><span style="background:#7FBA00"></span>
          <span style="background:#00A4EF"></span><span style="background:#FFB900"></span>
        </span>
        Microsoft 계정으로 로그인
      </button>
      @if ($password)
        <div class="or">또는 이메일로 로그인</div>
      @endif
    @endif

    @if ($password)
      <form method="POST" action="{{ route('login.store') }}" id="loginForm">
        @csrf
        <input type="hidden" name="from" value="m">
        <div class="f">
          <label for="email">이메일</label>
          <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                 value="{{ old('email') }}" placeholder="name@coloplast.com" required>
        </div>
        <div class="f">
          <label for="password">비밀번호</label>
          <input id="password" name="password" type="password" autocomplete="current-password"
                 placeholder="비밀번호" required>
        </div>
        <button class="btn" type="submit" id="loginBtn">로그인</button>
      </form>
    @elseif (! $sso)
      <div class="err">로그인할 수 있는 방법이 없습니다. 관리자에게 문의해 주십시오.</div>
    @endif
  </div>

  <div class="foot">모바일 웹 · 문의 1588-7866</div>
</div>

<script>
  document.getElementById('loginForm')?.addEventListener('submit', () => {
    const b = document.getElementById('loginBtn');
    b.disabled = true; b.textContent = '로그인 중…';
  });
</script>
</body>
</html>
