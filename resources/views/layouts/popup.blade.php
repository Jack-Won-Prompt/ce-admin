{{-- 팝업 창 레이아웃 — 사이드바 없이 혼자 서지만 본 화면과 같은 디자인을 쓴다.
     공단 사이트와 나란히 놓고 보는 창이라 사이드바가 자리를 먹으면 안 되고, 그렇다고
     생김새까지 달라지면 우리 화면이 아닌 것처럼 보인다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('windowTitle', 'CE Admin')</title>

<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<style>
@include('partials._design-tokens')

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body {
    font-family:'Pretendard Variable','Pretendard',-apple-system,BlinkMacSystemFont,
                'Apple SD Gothic Neo','Noto Sans KR','Segoe UI',sans-serif;
    background:var(--bg); color:var(--text-primary);
    font-size:13px; line-height:1.6; -webkit-font-smoothing:antialiased;
  }

  /* 본 화면과 같은 규격의 버튼·입력 — 팝업만 다르게 보이지 않도록 최소한만 옮겨 온다 */
  .btn {
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    height:32px; padding:0 12px; border-radius:var(--radius, 8px);
    border:1px solid var(--border); background:var(--gray-0); color:var(--text-primary);
    font-size:12px; font-weight:700; line-height:1; cursor:pointer; white-space:nowrap;
    font-family:inherit; text-decoration:none;
  }
  .btn:hover { border-color:var(--primary); color:var(--primary); }
  .btn-primary { background:var(--primary); border-color:var(--primary); color:var(--gray-0); }
  .btn-primary:hover { background:var(--primary-600); border-color:var(--primary-600); color:var(--gray-0); }
  .btn-sm { height:28px; padding:0 10px; font-size:11px; }

  .form-control {
    width:100%; height:32px; padding:0 10px; font-size:13px; font-family:inherit;
    border:1px solid var(--border); border-radius:var(--radius, 8px);
    background:var(--gray-0); color:var(--text-primary);
  }
  .form-control:focus { outline:none; border-color:var(--primary); }
  textarea.form-control { height:auto; padding:8px 10px; resize:vertical; }

  .toast {
    position:fixed; left:50%; bottom:26px; transform:translateX(-50%);
    background:var(--gray-1000); color:var(--gray-0); padding:9px 16px;
    border-radius:var(--radius, 8px); font-size:12px; font-weight:500;
    opacity:0; transition:opacity .15s; pointer-events:none; z-index:99;
  }
  .toast.on { opacity:1; }
</style>
@stack('styles')
</head>
<body>
@yield('body')
<div class="toast" id="toast"></div>
@stack('scripts')
</body>
</html>
