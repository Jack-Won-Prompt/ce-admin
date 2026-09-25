{{-- 모바일 OTP — 앱의 otp_screen 을 그대로 옮긴다 (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 여섯 칸을 따로 둔다. 한 자를 치면 다음 칸으로 가고, 지우면 앞 칸으로 간다
         (앱의 _onDigitChanged)
       · 여섯째 칸이 채워지면 스스로 보낸다
       · 재발송은 들어온 순간부터 60초를 센다 — 앱과 같다
       · 「인증번호를 받지 못하셨습니까?」ㆍ「인증하기」ㆍ「재발송 (Ns)」
       · 여섯 자리를 못 채우고 보내면 「인증번호 6자리를 모두 입력해 주십시오.」 --}}
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
    :root { --m-primary:#0B63CE; --m-danger:#D32F2F; }
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; width:100%; overflow-x:hidden; }
    body { min-height:100vh; font-family:'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
           background:linear-gradient(160deg, #0D1B3E 0%, #14306B 44%, #1565C0 100%);
           color:#fff; display:flex; flex-direction:column; }
    .wrap { flex:1; display:flex; flex-direction:column; justify-content:center; padding:28px 22px 34px; }

    .brand { text-align:center; margin-bottom:24px; }
    .brand .logo { width:66px; height:66px; border-radius:22px; margin:0 auto 14px;
                   background:linear-gradient(135deg,#1565C0,#0288D1);
                   display:flex; align-items:center; justify-content:center;
                   box-shadow:0 12px 28px rgba(0,0,0,.34); }
    .brand .logo i { font-size:31px; color:#fff; }
    .brand h1 { margin:0; font-size:22px; font-weight:800; }
    .lead { text-align:center; font-size:14px; color:rgba(255,255,255,.72);
            line-height:1.65; margin:10px 0 22px; }
    .lead b { color:#fff; }

    .card { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
            border-radius:20px; padding:22px 16px; backdrop-filter:blur(18px); }

    .boxes { display:flex; gap:8px; justify-content:center; margin-bottom:18px; }
    .boxes input { width:46px; height:56px; text-align:center; font-size:24px; font-weight:800;
                   border-radius:13px; border:1px solid rgba(255,255,255,.26);
                   background:rgba(255,255,255,.1); color:#fff; font-family:inherit; }
    .boxes input:focus { outline:0; border-color:#7FB3F0; background:rgba(255,255,255,.18); }

    .go { width:100%; padding:15px; border:0; border-radius:13px; font-size:15.5px; font-weight:700;
          font-family:inherit; cursor:pointer; color:#fff;
          background:linear-gradient(135deg,#1565C0,#0288D1); }
    .go[disabled] { opacity:.6; }

    .again { text-align:center; margin-top:16px; font-size:13px; color:rgba(255,255,255,.66); }
    .again button { background:none; border:0; color:#9CC8FF; font-size:13px; font-weight:700;
                    font-family:inherit; cursor:pointer; padding:4px; }
    .again button[disabled] { color:rgba(255,255,255,.4); }

    .err { background:rgba(211,47,47,.2); border:1px solid rgba(255,138,138,.5); color:#FFD7D7;
           padding:11px 13px; border-radius:11px; font-size:13.5px; margin-bottom:14px; line-height:1.5; }
    .ok  { background:rgba(18,128,92,.22); border:1px solid rgba(140,224,198,.5); color:#CFF3E6; }

    .toast { position:fixed; left:50%; bottom:26px; transform:translate(-50%, 130%);
             background:#1B1F26; color:#fff; font-size:13.5px; padding:12px 16px;
             border-radius:11px; max-width:88vw; line-height:1.5; z-index:90;
             transition:transform .22s ease; box-shadow:0 8px 24px rgba(0,0,0,.34); }
    .toast.on { transform:translate(-50%, 0); }
    .toast.bad { background:#C62828; }

    .back { display:block; text-align:center; margin-top:20px; font-size:13px;
            color:rgba(255,255,255,.5); }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="logo"><i class="bx bx-message-square-dots"></i></div>
    <h1>SMS 인증</h1>
  </div>

  <p class="lead">
    <b>{{ $maskedPhone ?? '등록된 번호' }}</b>으로<br>
    발송된 6자리 인증번호를 입력해 주십시오.
  </p>

  <div class="card">
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="err ok">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('login.otp.verify') }}" id="otpForm">
      @csrf
      <input type="hidden" name="from" value="m">
      <input type="hidden" name="code" id="code">

      <div class="boxes" id="boxes">
        @for ($i = 0; $i < 6; $i++)
          <input type="text" inputmode="numeric" maxlength="1" data-i="{{ $i }}"
                 autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                 aria-label="인증번호 {{ $i + 1 }}번째 자리">
        @endfor
      </div>

      <button class="go" type="submit" id="okBtn">인증하기</button>
    </form>

    <div class="again">
      인증번호를 받지 못하셨습니까?
      <form method="POST" action="{{ route('login.otp.resend') }}" id="reForm" style="display:inline;">
        @csrf
        <input type="hidden" name="from" value="m">
        <button type="submit" id="reBtn">재발송</button>
      </form>
    </div>
  </div>

  <a class="back" href="{{ route('m.login') }}">다른 계정으로 로그인</a>
</div>

<div class="toast" id="toast"></div>

<script>
  const 칸들 = Array.from(document.querySelectorAll('#boxes input'));

  let 알림때 = null;
  function 알림(글, 나쁨) {
    const el = document.getElementById('toast');
    el.textContent = 글;
    el.className = 'toast on' + (나쁨 ? ' bad' : '');
    clearTimeout(알림때);
    알림때 = setTimeout(() => { el.className = 'toast' + (나쁨 ? ' bad' : ''); }, 3200);
  }

  function 모은코드() { return 칸들.map(c => c.value).join(''); }

  /* 앱의 _onDigitChanged — 치면 다음 칸, 지우면 앞 칸, 여섯째가 차면 보낸다 */
  칸들.forEach((칸, i) => {
    칸.addEventListener('input', () => {
      칸.value = 칸.value.replace(/\D/g, '').slice(0, 1);
      if (칸.value && i < 5) 칸들[i + 1].focus();
      if (i === 5 && 칸.value) document.getElementById('otpForm').requestSubmit();
    });
    칸.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !칸.value && i > 0) { 칸들[i - 1].focus(); e.preventDefault(); }
    });
    /* 문자 앱에서 여섯 자리를 통째로 붙여 넣는 일이 흔하다 */
    칸.addEventListener('paste', e => {
      const 글 = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      if (!글) return;
      e.preventDefault();
      글.split('').forEach((d, k) => { if (칸들[k]) 칸들[k].value = d; });
      칸들[Math.min(글.length, 5)].focus();
      if (글.length === 6) document.getElementById('otpForm').requestSubmit();
    });
  });
  칸들[0].focus();

  document.getElementById('otpForm').addEventListener('submit', function (e) {
    const 코드 = 모은코드();
    if (코드.length < 6) {
      e.preventDefault();
      알림('인증번호 6자리를 모두 입력해 주십시오.', true);
      return;
    }
    document.getElementById('code').value = 코드;
    const b = document.getElementById('okBtn');
    b.disabled = true; b.textContent = '확인 중…';
  });

  /* 재발송은 들어온 순간부터 60초를 센다 — 앱과 같다 */
  const 재발송 = document.getElementById('reBtn');
  let 남은초 = Number(@json($resendCooldown ?? 0)) || 60;

  (function 초세기() {
    if (남은초 > 0) {
      재발송.disabled = true;
      재발송.textContent = `재발송 (${남은초}s)`;
      남은초--;
      setTimeout(초세기, 1000);
    } else {
      재발송.disabled = false;
      재발송.textContent = '재발송';
    }
  })();

  document.getElementById('reForm').addEventListener('submit', () => {
    재발송.disabled = true; 재발송.textContent = '보내는 중…';
  });
</script>
</body>
</html>
