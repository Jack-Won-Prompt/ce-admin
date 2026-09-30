{{-- 모바일 신규 신설 — 제출 완료 (2026-10-01 지시).

     공개 폼의 완료 화면(privacy/done)과 같은 말을 하되 모바일 규격으로 짓는다.
     다음에 무엇이 일어나는지 적어 둔다 — 「제출되었습니다」만 적으면 사람이
     기다려야 하는지 다시 해야 하는지 알 수 없다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="theme-color" content="#0B63CE">
  <title>신청 완료 — CE Admin</title>
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; width:100%; overflow-x:hidden; }
    body { min-height:100vh; font-family:'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
           background:linear-gradient(160deg, #0D1B3E 0%, #14306B 44%, #1565C0 100%);
           color:#fff; display:flex; flex-direction:column; }
    .wrap { flex:1; display:flex; flex-direction:column; justify-content:center; padding:30px 22px; }
    .mark { width:82px; height:82px; border-radius:50%; margin:0 auto 22px;
            background:linear-gradient(135deg,#12805C,#1EAF7B);
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 12px 30px rgba(0,0,0,.32); }
    .mark i { font-size:44px; color:#fff; }
    h1 { margin:0; text-align:center; font-size:22px; font-weight:800; letter-spacing:-.4px; }
    .lead { margin:10px 0 0; text-align:center; font-size:14px; line-height:1.7;
            color:rgba(255,255,255,.72); }
    .card { margin-top:26px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
            border-radius:18px; padding:18px 16px; backdrop-filter:blur(18px); }
    .card h2 { margin:0 0 13px; font-size:14px; font-weight:800; display:flex; align-items:center; gap:7px; }
    .card h2 i { font-size:17px; color:#7FB3F0; }
    ol { margin:0; padding-left:20px; }
    ol li { font-size:13.5px; line-height:1.85; color:rgba(255,255,255,.84); }
    .go { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:22px;
          width:100%; padding:16px; border:0; border-radius:14px; text-decoration:none;
          font-size:15.5px; font-weight:800; color:#fff;
          background:linear-gradient(135deg,#1565C0,#0288D1); }
    .foot { text-align:center; font-size:12px; color:rgba(255,255,255,.5); padding-top:20px; line-height:1.7; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="mark"><i class="bx bx-check"></i></div>

  <h1>신청서가 제출되었습니다</h1>
  <p class="lead">개인정보 수집·이용 동의서가 정상 접수되었습니다.<br>담당자가 확인 후 연락드립니다.</p>

  <div class="card">
    <h2><i class="bx bx-list-check"></i>다음 절차</h2>
    <ol>
      <li>담당자가 신청 내용을 확인합니다.</li>
      <li>처방 서류 등록 안내를 문자로 보내드립니다.</li>
      <li>서류 확인이 끝나면 주문·결제 안내를 보내드립니다.</li>
    </ol>
  </div>

  <a class="go" href="{{ route('m.login') }}">
    <i class="bx bx-log-in"></i> 로그인 화면으로
  </a>

  <div class="foot">
    콜로플라스트 코리아 · 문의 1588-7866<br>
    신청 내용 정정이 필요하시면 위 번호로 연락해 주십시오.
  </div>
</div>
</body>
</html>
