{{-- 모바일 웹(H5) 판 — 앱과 같은 얼개 (2026-09-25 지시).

     앱(ce_app)의 화면을 그대로 웹으로 옮긴다. 아래 탭 넷(처방전ㆍ업로드ㆍ채팅ㆍ설정)과
     위 머리글은 앱과 같은 자리에 둔다 — 쓰던 사람이 다시 익히지 않아도 되게.

     자료는 **앱이 쓰는 /api/* 를 그대로** 부른다. 같은 코드가 답하므로 앱과 기능이
     갈릴 수 없다. 인증은 웹 세션으로 통한다(sanctum 이 web 가드를 먼저 본다). --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0B63CE">
  <title>@yield('title', 'CE Admin')</title>

  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">

  <style>
    /* ── 바탕 ─────────────────────────────────────────── */
    :root {
      --m-primary:#0B63CE; --m-primary-d:#0A4FA6; --m-primary-l:#E8F1FC;
      --m-bg:#F4F6F9; --m-card:#FFFFFF; --m-line:#E5E8EC;
      --m-text:#1B1F26; --m-sub:#667085; --m-mute:#98A2B3;
      --m-danger:#D32F2F; --m-ok:#12805C; --m-warn:#B54708;
      --m-head:64px; --m-tab:60px;
      --m-safe-b:env(safe-area-inset-bottom, 0px);
    }
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; }
    body {
      font-family:'Pretendard', -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', sans-serif;
      background:var(--m-bg); color:var(--m-text); font-size:15px; line-height:1.55;
      padding-bottom:calc(var(--m-tab) + var(--m-safe-b));
      overscroll-behavior-y:contain;
    }
    a { color:inherit; text-decoration:none; }
    button { font-family:inherit; }

    /* ── 위 머리글 ─────────────────────────────────────── */
    .m-head {
      position:sticky; top:0; z-index:40; height:var(--m-head);
      background:linear-gradient(135deg, var(--m-primary) 0%, var(--m-primary-d) 100%);
      color:#fff; display:flex; align-items:center; gap:10px; padding:0 14px;
      box-shadow:0 2px 10px rgba(11,99,206,.18);
    }
    .m-head h1 { margin:0; font-size:18px; font-weight:800; letter-spacing:-.3px; flex:1;
                 overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .m-head .sub { display:block; font-size:11.5px; font-weight:500; opacity:.85; margin-top:1px; }
    .m-head-btn {
      width:38px; height:38px; border-radius:12px; border:0; background:rgba(255,255,255,.16);
      color:#fff; font-size:19px; display:flex; align-items:center; justify-content:center; cursor:pointer;
    }
    .m-head-btn:active { background:rgba(255,255,255,.3); }

    /* ── 몸통 ─────────────────────────────────────────── */
    .m-body { padding:12px; min-height:calc(100vh - var(--m-head) - var(--m-tab)); }

    .m-card { background:var(--m-card); border:1px solid var(--m-line); border-radius:14px;
              padding:14px; margin-bottom:10px; }
    .m-card.tap:active { background:#FAFBFC; }

    /* ── 아래 탭 ──────────────────────────────────────── */
    .m-tabs {
      position:fixed; left:0; right:0; bottom:0; z-index:45;
      height:calc(var(--m-tab) + var(--m-safe-b)); padding-bottom:var(--m-safe-b);
      background:#fff; border-top:1px solid var(--m-line);
      display:grid; grid-template-columns:repeat(4, 1fr);
    }
    .m-tab { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
             color:var(--m-mute); font-size:11px; font-weight:600; }
    .m-tab i { font-size:22px; }
    .m-tab.on { color:var(--m-primary); }
    .m-tab .dot { position:absolute; transform:translate(12px,-10px); min-width:16px; height:16px;
                  border-radius:999px; background:var(--m-danger); color:#fff; font-size:10px;
                  font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px; }

    /* ── 조각들 ──────────────────────────────────────── */
    .m-chips { display:flex; gap:6px; overflow-x:auto; padding:2px 0 8px; scrollbar-width:none; }
    .m-chips::-webkit-scrollbar { display:none; }
    .m-chip { flex:0 0 auto; padding:7px 13px; border-radius:999px; border:1px solid var(--m-line);
              background:#fff; color:var(--m-sub); font-size:13px; font-weight:600; cursor:pointer; }
    .m-chip.on { background:var(--m-primary); border-color:var(--m-primary); color:#fff; }

    .m-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px;
               font-size:11.5px; font-weight:700; }
    .m-badge.need { background:#FEF0F0; color:var(--m-danger); }
    .m-badge.req  { background:#FFF6E8; color:var(--m-warn); }
    .m-badge.done { background:#E7F6F1; color:var(--m-ok); }
    .m-badge.gray { background:#F2F4F7; color:var(--m-sub); }

    .m-field { margin-bottom:12px; }
    .m-label { display:block; font-size:12.5px; font-weight:700; color:var(--m-sub); margin-bottom:6px; }
    .m-input, .m-select, .m-textarea {
      width:100%; padding:12px 13px; border:1px solid var(--m-line); border-radius:11px;
      font-size:15px; font-family:inherit; background:#fff; color:var(--m-text);
    }
    .m-input:focus, .m-select:focus, .m-textarea:focus { outline:2px solid var(--m-primary-l); border-color:var(--m-primary); }
    .m-textarea { min-height:110px; resize:vertical; }

    .m-btn { width:100%; padding:14px; border:0; border-radius:12px; background:var(--m-primary);
             color:#fff; font-size:15.5px; font-weight:700; cursor:pointer; }
    .m-btn:active { background:var(--m-primary-d); }
    .m-btn[disabled] { background:#C4CBD4; }
    .m-btn.ghost { background:#fff; color:var(--m-primary); border:1px solid var(--m-primary); }
    .m-btn.danger { background:var(--m-danger); }

    .m-empty { text-align:center; padding:58px 18px; color:var(--m-mute); }
    .m-empty i { font-size:46px; display:block; margin-bottom:10px; opacity:.5; }

    .m-spin { width:22px; height:22px; border:2.5px solid var(--m-primary-l);
              border-top-color:var(--m-primary); border-radius:50%; animation:msp .7s linear infinite; margin:30px auto; }
    @keyframes msp { to { transform:rotate(360deg); } }

    /* 토스트 — 앱의 SnackBar 자리 */
    .m-toast { position:fixed; left:50%; bottom:calc(var(--m-tab) + var(--m-safe-b) + 16px);
               transform:translateX(-50%); z-index:60; max-width:92vw;
               background:#1B1F26; color:#fff; padding:12px 16px; border-radius:12px;
               font-size:14px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.24);
               opacity:0; transition:opacity .18s, transform .18s; pointer-events:none; }
    .m-toast.on { opacity:1; transform:translateX(-50%) translateY(-4px); }
    .m-toast.ok   { background:#12805C; }
    .m-toast.bad  { background:#D32F2F; }
    .m-toast.warn { background:#B54708; }

    /* 아래에서 올라오는 판 — 앱의 BottomSheet */
    .m-sheet-back { position:fixed; inset:0; z-index:70; background:rgba(15,23,42,.45); display:none; }
    .m-sheet-back.on { display:block; }
    .m-sheet { position:fixed; left:0; right:0; bottom:0; z-index:71; background:#fff;
               border-radius:18px 18px 0 0; padding:18px 16px calc(18px + var(--m-safe-b));
               max-height:86vh; overflow-y:auto; transform:translateY(100%); transition:transform .22s; }
    .m-sheet.on { transform:translateY(0); }
    .m-sheet h2 { margin:0 0 4px; font-size:18px; font-weight:800; }
    .m-sheet .desc { margin:0 0 16px; font-size:13.5px; color:var(--m-sub); line-height:1.5; }
    .m-grab { width:38px; height:4px; border-radius:99px; background:#D6DAE0; margin:0 auto 14px; }
  </style>
  @stack('styles')
</head>
<body>

<header class="m-head">
  @hasSection('back')
    <button class="m-head-btn" onclick="history.length > 1 ? history.back() : location.assign('{{ route('m.prescriptions') }}')" aria-label="뒤로">
      <i class="bx bx-chevron-left"></i>
    </button>
  @endif
  <h1>
    @yield('title', 'CE Admin')
    @hasSection('subtitle')<span class="sub">@yield('subtitle')</span>@endif
  </h1>
  @yield('head-actions')
</header>

<main class="m-body">@yield('body')</main>

@php
  $탭 = $탭 ?? '';
@endphp
<nav class="m-tabs">
  <a class="m-tab {{ $탭 === 'rx' ? 'on' : '' }}"      href="{{ route('m.prescriptions') }}"><i class="bx bx-file"></i>처방전</a>
  <a class="m-tab {{ $탭 === 'upload' ? 'on' : '' }}"  href="{{ route('m.upload') }}"><i class="bx bx-upload"></i>업로드</a>
  <a class="m-tab {{ $탭 === 'chat' ? 'on' : '' }}"    href="{{ route('m.chat') }}"><i class="bx bx-message-rounded"></i>채팅</a>
  <a class="m-tab {{ $탭 === 'settings' ? 'on' : '' }}" href="{{ route('m.settings') }}"><i class="bx bx-cog"></i>설정</a>
</nav>

<div class="m-toast" id="mToast"></div>
<div class="m-sheet-back" id="mSheetBack" onclick="mSheetClose()"></div>

<script>
  /* ── 앱의 SnackBar 자리 ───────────────────────────── */
  let _mToastTimer = null;
  function mTell(글, 갈래) {
    const el = document.getElementById('mToast');
    el.textContent = 글;
    el.className = 'm-toast on ' + (갈래 || '');
    clearTimeout(_mToastTimer);
    _mToastTimer = setTimeout(() => { el.className = 'm-toast ' + (갈래 || ''); }, 2600);
  }

  /* ── 아래에서 올라오는 판 ─────────────────────────── */
  function mSheetOpen(el) {
    document.getElementById('mSheetBack').classList.add('on');
    (typeof el === 'string' ? document.getElementById(el) : el).classList.add('on');
  }
  function mSheetClose() {
    document.getElementById('mSheetBack').classList.remove('on');
    document.querySelectorAll('.m-sheet.on').forEach(s => s.classList.remove('on'));
  }

  /* ── 앱이 쓰는 API 를 그대로 부른다 ────────────────
     인증은 웹 세션으로 통한다. 같은 코드가 답하므로 앱과 기능이 갈릴 수 없다. */
  async function mApi(길, 옵션) {
    옵션 = 옵션 || {};
    const 머리 = Object.assign({ 'Accept': 'application/json' }, 옵션.headers || {});
    if (옵션.body && !(옵션.body instanceof FormData)) {
      머리['Content-Type'] = 'application/json';
      옵션.body = JSON.stringify(옵션.body);
    }
    머리['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]')?.content || '';

    const r = await fetch('/api' + 길, Object.assign({}, 옵션, { headers: 머리, credentials: 'same-origin' }));

    if (r.status === 401 || r.status === 419) {
      location.assign('{{ route('login') }}');
      throw new Error('로그인이 필요합니다.');
    }

    let 몸 = null;
    try { 몸 = await r.json(); } catch (e) { 몸 = null; }

    if (!r.ok) {
      throw new Error((몸 && (몸.message || 몸.error)) || '요청을 처리하지 못했습니다.');
    }
    return 몸;
  }

  /* 글자를 화면에 안전하게 넣는다 */
  function mEsc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* 날짜를 짧게 — 오늘이면 시각만 */
  function mWhen(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return String(v).slice(0, 16);
    const 오늘 = new Date();
    const 같은날 = d.toDateString() === 오늘.toDateString();
    const 두자리 = n => String(n).padStart(2, '0');
    return 같은날 ? `${두자리(d.getHours())}:${두자리(d.getMinutes())}`
                  : `${d.getFullYear()}-${두자리(d.getMonth() + 1)}-${두자리(d.getDate())}`;
  }
</script>
@stack('scripts')
</body>
</html>
