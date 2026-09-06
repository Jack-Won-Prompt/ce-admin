{{-- 스토어 심사와 앱 안에서 여는 문서 세 장(이용약관·개인정보처리방침·계정 삭제)의 껍데기.
     로그인 없이 열려야 한다 — 구글이 심사할 때도, 지운 계정의 주인이 열 때도 그렇다.
     관리자 화면 레이아웃(layouts/app)은 사이드바와 로그인 사용자를 전제해서 쓰지 않는다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') | {{ $company['name'] }}</title>
<meta name="robots" content="index,follow">
<style>
  :root{
    --brand:#0a3d62; --brand-2:#1e5f8e; --accent:#2e86de;
    --bg:#f4f6f9; --card:#fff; --line:#e3e8ef; --text:#1f2d3d; --muted:#6b7a90;
    --radius:12px;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--bg);color:var(--text);
    font-family:'Noto Sans KR','Malgun Gothic',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;
    font-size:15px;line-height:1.75;-webkit-text-size-adjust:100%;}
  .wrap{max-width:760px;margin:0 auto;background:var(--bg);}
  .appbar{position:sticky;top:0;z-index:10;background:var(--brand);color:#fff;padding:14px 18px;
    display:flex;align-items:center;gap:10px;box-shadow:0 2px 10px rgba(10,61,98,.18);}
  .appbar .logo{font-weight:800;letter-spacing:-.3px;font-size:16px;}
  .hero{background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#fff;padding:26px 18px 30px;}
  .hero h1{margin:0 0 6px;font-size:22px;font-weight:800;letter-spacing:-.5px;}
  .hero p{margin:0;font-size:13px;opacity:.9;}
  .container{padding:16px 16px 40px;}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
    padding:22px 20px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.03);}
  .card h2{margin:0 0 14px;font-size:16px;font-weight:800;color:var(--brand);
    padding-bottom:10px;border-bottom:2px solid var(--line);}
  .card h3{margin:20px 0 8px;font-size:14px;font-weight:800;}
  .card h3:first-child{margin-top:0;}
  .card p{margin:0 0 12px;}
  .card ul,.card ol{margin:0 0 12px;padding-left:20px;}
  .card li{margin-bottom:6px;}
  .muted{color:var(--muted);font-size:13px;}
  table{width:100%;border-collapse:collapse;margin:0 0 12px;font-size:14px;}
  th,td{border:1px solid var(--line);padding:9px 10px;text-align:left;vertical-align:top;}
  th{background:#f7f9fc;font-weight:700;width:32%;}
  .callout{background:#f7f9fc;border-left:3px solid var(--accent);border-radius:0 8px 8px 0;
    padding:14px 16px;margin:0 0 12px;}
  .callout strong{color:var(--brand);}
  a{color:var(--accent);}
  .navlinks{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px;}
  .navlinks a{display:inline-block;padding:8px 14px;background:#fff;border:1px solid var(--line);
    border-radius:20px;font-size:13px;text-decoration:none;color:var(--brand);font-weight:700;}
  .navlinks a.on{background:var(--brand);color:#fff;border-color:var(--brand);}
  footer{padding:22px 18px 40px;color:var(--muted);font-size:12px;line-height:1.9;}
  @media (max-width:520px){
    th{width:38%;}
    .hero h1{font-size:19px;}
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="appbar"><span class="logo">CE Admin</span></div>

  <div class="hero">
    <h1>@yield('title')</h1>
    <p>@yield('effective')</p>
  </div>

  <div class="container">
    @yield('body')

    <div class="navlinks">
      <a href="{{ route('legal.terms') }}"    class="{{ request()->routeIs('legal.terms')   ? 'on' : '' }}">이용약관</a>
      <a href="{{ route('legal.privacy') }}"  class="{{ request()->routeIs('legal.privacy') ? 'on' : '' }}">개인정보처리방침</a>
      <a href="{{ route('legal.deletion') }}" class="{{ request()->routeIs('legal.deletion')? 'on' : '' }}">계정 삭제</a>
    </div>
  </div>

  <footer>
    {{ $company['name'] }} · 대표 {{ $company['ceo'] }}<br>
    {{ $company['addr'] }}<br>
    문의 {{ $company['tel'] }} · <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>
  </footer>
</div>
</body>
</html>
