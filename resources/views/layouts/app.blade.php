{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="ko" class="light-style layout-menu-fixed" dir="ltr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>CE Admin — @yield('title', '대시보드')</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}" />

  {{-- Fonts: Pretendard (Korean modern typeface) --}}
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">

  {{-- Bootstrap 5 --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  {{-- Boxicons --}}
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  {{-- FontAwesome --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/all.min.css" />

  {{-- Global CSS — Soldoc Design System --}}
  <style>
    /* ═══════════════════════════════════════════
       SOLDOC DESIGN TOKENS
    ═══════════════════════════════════════════ */
@include('partials._design-tokens')

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    /* hidden 을 쓰면 세로가 auto 가 되어 스크롤 컨테이너가 만들어지고,
       안쪽의 position:sticky 가 제자리에 붙지 못한다. clip 은 가로만 잘라낸다. */
    html { overflow-x: clip; }
    body {
      font-family: 'Pretendard Variable', 'Pretendard', -apple-system, BlinkMacSystemFont,
                   'Apple SD Gothic Neo', 'Noto Sans KR', 'Segoe UI', sans-serif;
      background: var(--bg);   /* Figma 루트 프레임 = grayscale/100 */
      color: var(--text-primary);
      font-size: 14px;
      line-height: 1.6;
      overflow-x: clip;
      -webkit-font-smoothing: antialiased;
    }

    /* ═══════════════════════════════════════════
       LAYOUT
    ═══════════════════════════════════════════ */
    .layout-wrapper   { display: flex; flex-direction: column; min-height: 100vh; }
    .layout-container { display: flex; flex: 1; min-height: 0; }

    /* ── Sidebar ── */
    /* Figma: 폭 320, padding 0 16 16, 페이지 배경 위에 흰 메뉴 패널이 얹힌 구조라
       사이드바 자체는 배경·테두리가 없다 */
    .layout-menu {
      width: var(--sidebar-w);
      flex-shrink: 0;
      background: transparent;
      padding: 0 16px 16px;
      position: fixed; top: 0; left: 0; bottom: 0;
      z-index: 100;
      overflow-y: auto; overflow-x: hidden;
      display: flex; flex-direction: column;
      transition: width .25s cubic-bezier(.4,0,.2,1), transform .25s cubic-bezier(.4,0,.2,1);
      scrollbar-width: none;
    }
    .layout-menu::-webkit-scrollbar { display: none; }
    .layout-menu.hidden { transform: translateX(-100%); }

    /* Collapsed */
    .layout-menu.collapsed { width: var(--sidebar-collapsed-w); }
    .layout-menu.collapsed .app-brand-text,
    .layout-menu.collapsed .app-brand-sub,
    .layout-menu.collapsed .menu-header,
    .layout-menu.collapsed .menu-link span,
    .layout-menu.collapsed .menu-badge,
    .layout-menu.collapsed .menu-user-info { display: none; }
    /* 접힘 폭이 64px 이라 사이드바 좌우 16px 패딩을 그대로 두면 내용이 32px 밖에 못 쓴다 */
    .layout-menu.collapsed { padding: 0 8px 8px; }
    .layout-menu.collapsed .menu-inner { padding: 8px; gap: 8px; }
    /* 접힘 폭 64 - 좌우 패딩 16 = 내용 48px. 로고 28 + 버튼 28 을 가로로 두면 넘쳐서
       justify-content:center 가 무력화되고 둘 다 사이드바 모서리에 붙는다.
       세로로 쌓으면 28 + 4 + 28 = 60 이라 머리 높이 68 안에 들어가고 가운데 정렬이 살아난다 */
    .layout-menu.collapsed .app-brand {
      flex-direction: column; justify-content: center; gap: 4px;
      padding: 0; height: var(--nav-h);
    }
    .layout-menu.collapsed .app-brand > a { flex: 0 0 auto; min-width: 0; }
    .layout-menu.collapsed .app-brand-names { display: none; }
    .layout-menu.collapsed .menu-link { justify-content: center; padding: 8px 0; }
    .layout-menu.collapsed .menu-icon { width: 16px; }
    .layout-menu.collapsed .menu-user { justify-content: center; padding: 10px 0; }
    .layout-menu.collapsed .menu-footer { padding: 10px 6px; }
    .layout-menu.collapsed ~ .layout-page,
    .layout-page.collapsed { margin-left: var(--sidebar-collapsed-w); }

    /* Tooltip on hover when collapsed */
    .layout-menu.collapsed .menu-item { position: relative; }
    .layout-menu.collapsed .menu-link::after {
      content: attr(data-title);
      position: absolute; left: calc(100% + 8px); top: 50%;
      transform: translateY(-50%);
      background: var(--gray-1000); color: var(--gray-100);
      font-size: 12px; font-weight: 500; white-space: nowrap;
      padding: 5px 10px; border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,.15);
      opacity: 0; pointer-events: none;
      transition: opacity .12s ease;
      z-index: 200;
    }
    .layout-menu.collapsed .menu-link:hover::after { opacity: 1; }

    /* Collapse toggle btn */
    /* Figma 228:4332 은 16×16 chevron 만 있고 그 오른쪽 끝이 머리 padding 끝(16px 안쪽)에 붙는다.
       28×28 조작 영역은 개발자가 넣은 것이라 남기되, margin-right -6px 로 통째로 6px 밀어
       가운데 놓인 16px 아이콘의 오른쪽 끝이 시안 위치(16px 안쪽)에 오게 한다 */
    .menu-collapse-btn {
      margin-left: auto; margin-right: -6px; flex-shrink: 0;
      width: 28px; height: 28px; border-radius: 6px;
      background: transparent; border: none;
      color: var(--gray-500); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px;
      transition: background .15s, color .15s;
    }
    .menu-collapse-btn:hover { background: var(--bg); color: var(--primary); }
    .layout-menu.collapsed .menu-collapse-btn { margin-left: 0; margin-right: 0; }
    .menu-collapse-btn .ic-expanded { display: flex; }
    .menu-collapse-btn .ic-collapsed { display: none; }
    /* 아이콘 파일이 « 하나뿐이라 접힘용은 좌우를 뒤집어 »(펼치기)로 읽히게 한다 */
    .menu-collapse-btn .ic-collapsed { transform: scaleX(-1); }
    .layout-menu.collapsed .menu-collapse-btn .ic-expanded { display: none; }
    .layout-menu.collapsed .menu-collapse-btn .ic-collapsed { display: flex; }

    /* Brand */
    /* 브랜드 영역 — Figma 228:4332: 288×68, padding 0 16, space-between, itemSpacing 12
       (로고묶음 안쪽 gap 8 · 이름묶음 gap 4 는 그대로)
       (사이드바가 페이지 배경 위에 얹히는 구조라 하단 구분선은 없다) */
    .app-brand {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      padding: 0 16px;
      min-height: var(--nav-h);
      text-decoration: none;
      flex-shrink: 0;
    }
    /* 브랜드 링크는 색을 안 줘 부트스트랩 기본 링크색(DS 램프 26색 밖)을 물려받고 있었다.
       시안(174:955) 로고타입은 gray-1000 이다. */
    .app-brand > a { color: var(--gray-1000); }
    /* 시안은 로고 이미지에 exposure -1 이 걸려 거의 검정(rgb 14,14,14)으로 나온다.
       원본 PNG 는 불투명부가 전부 rgb 111 단색이라 brightness .127 이면 111×.127=14.1 → 14 로 떨어진다.
       (에셋을 exposure 반영해 다시 내려받으면 이 filter 는 지운다) */
    .app-brand-logo {
      width: 28px; height: 28px; flex-shrink: 0;
      object-fit: cover; display: block;
      filter: brightness(.127);
    }
    /* 워드마크가 이미지라 화면낭독기·검색에는 글자가 남아야 한다 */
    .visually-hidden {
      position: absolute; width: 1px; height: 1px; overflow: hidden;
      clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap;
    }
    .app-brand-text { width: 64.7px; height: 10px; display: block; }
    .app-brand-text svg { width: 100%; height: 100%; display: block; }
    /* Figma: Pretendard Medium 10 / lh 1.2 / grayscale-500 */
    .app-brand-sub { font-size: 10px; font-weight: 500; line-height: 1.2; color: var(--gray-500); }
    .app-brand-names { display: flex; flex-direction: column; justify-content: center; gap: 4px; min-width: 0; }

    /* Menu sections */
    .menu-inner {
      flex: 1;
      background: var(--gray-0);
      border-radius: 12px;
      padding: 16px;
      display: flex; flex-direction: column; gap: 16px;
      backdrop-filter: blur(8px);
    }
    /* 그룹 안 항목은 간격 없이 붙는다(Figma) */
    .menu-group-items { display: flex; flex-direction: column; }
    /* 그룹 헤더 — Figma: Pretendard Medium 11 / lh 1.2 / grayscale-600, 우측 12px chevron */
    .menu-header {
      font-size: 11px; font-weight: 500; line-height: 1.2;
      color: var(--gray-600);
      padding: 0;
      display: flex; align-items: center; justify-content: space-between; gap: 6px;
      width: 100%; background: none; border: none;
      font-family: inherit; text-align: left; cursor: pointer;
    }
    .menu-header:hover { color: var(--primary); }
    .menu-caret {
      margin-left: auto; width: 12px; height: 12px; flex-shrink: 0;
      transition: transform .18s ease;
    }
    .menu-group.is-collapsed .menu-caret { transform: rotate(-90deg); }
    .menu-group.is-collapsed > .menu-group-items { display: none; }
    /* 그룹 안에 현재 화면이 있으면 헤더를 강조 */
    .menu-group.has-active > .menu-header { color: var(--primary); }
    /* 접힌 그룹에 알림 건수가 있으면 헤더에 합계를 표시(숨겨져 놓치지 않도록) */
    .menu-group-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 20px; height: 20px; padding: 0 6px;
      background: var(--alert-100); color: var(--alert-500);
      font-size: 10px; font-weight: 700;
      border-radius: 999px; letter-spacing: 0; line-height: 1;
    }
    .menu-group:not(.is-collapsed) .menu-group-badge { display: none; }
    /* 아이콘 모드에서는 헤더가 숨겨져 펼칠 수 없으므로 접힘을 무시한다 */
    .layout-menu.collapsed .menu-group.is-collapsed > .menu-group-items { display: block; }

    /* 메뉴 항목 — Figma: padding 6/8, gap 8, radius 8, 아이콘 16, 텍스트 Medium 13 / lh 1.6
       활성: 배경 primary-50, 텍스트·아이콘 primary-500, Bold 700 */
    /* Figma: 그룹은 column · gap 8 */
    .menu-group { display: flex; flex-direction: column; gap: 8px; }

    .menu-item { position: relative; }
    .menu-link {
      display: flex; align-items: center; gap: 8px;
      padding: 6px 8px; border-radius: 8px;
      color: var(--gray-800); font-size: 13px; font-weight: 500; line-height: 1.6;
      text-decoration: none; transition: var(--transition); position: relative;
    }
    .menu-link:hover { background: var(--gray-50); color: var(--primary); }
    .menu-item.active > .menu-link {
      background: var(--primary-light);
      color: var(--primary);
      font-weight: 700;
    }
    .menu-icon {
      width: 16px; height: 16px; flex-shrink: 0;
      color: inherit; transition: color .15s;
    }
    /* 인라인 SVG 아이콘 — 색은 currentColor 라 위 규칙이 그대로 먹는다 */
    .ds-icon { width: 16px; height: 16px; flex-shrink: 0; display: block; }
    /* Figma: 20×20 원형 · 10px/700 · 연한 배경 + 진한 글자
       (빨강 alert-100/alert-500, 파랑 primary-100/primary-600) */
    .menu-badge {
      margin-left: auto;
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 20px; height: 20px; padding: 0;
      background: var(--alert-100); color: var(--alert-500);
      font-size: 10px; font-weight: 700;
      border-radius: 999px; text-align: center;
      line-height: 1;
    }
    .menu-badge.blue   { background: var(--primary-100); color: var(--primary-600); }
    .menu-badge.orange { background: var(--warning-light); color: #B45309; }

    /* Menu Footer */
    .menu-footer { padding: 10px 8px; border-top: 1px solid var(--border); }
    .menu-user {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 10px; border-radius: 8px;
      cursor: pointer; transition: var(--transition);
    }
    .menu-user:hover { background: var(--bg); }
    .menu-user-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; flex-shrink: 0;
    }
    .menu-user-name { font-size: 13px; font-weight: 500; color: var(--text-primary); line-height: 21px; }
    .menu-user-role { font-size: 10px; color: var(--text-muted); }

    /* ── Layout Page ── */
    .layout-page {
      flex: 1; display: flex; flex-direction: column;
      min-width: 0; margin-left: var(--sidebar-w);
      transition: margin-left .25s cubic-bezier(.4,0,.2,1);
    }

    /* ── Top Navbar ── */
    /* Figma 174:1146 — 배경·구분선 없음(페이지 배경 위에 그대로 얹힌다), height 68, padding 0 16 */
    .layout-navbar {
      display: flex; align-items: center; gap: 8px;
      padding: 0 16px;
      background: transparent;
      border-bottom: none;
      position: fixed; top: 0; left: var(--sidebar-w); right: 0; z-index: 50;
      min-height: var(--nav-h);
      transition: left .25s cubic-bezier(.4,0,.2,1);
    }
    body.menu-collapsed .layout-navbar { left: var(--sidebar-collapsed-w); }
    .navbar-brand-area { flex: 1; min-width: 0; overflow: hidden; }
    .page-title {
      font-size: 16px; font-weight: 700; line-height: 1.2; color: var(--gray-800);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .page-breadcrumb {
      font-size: 12px; font-weight: 500; line-height: 1.2; color: var(--gray-600);
      margin-top: 4px;   /* Figma: 제목과 gap 4 */
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    /* 마디 사이 8 — 시안 248:4008 Frame 48101452 는 HORIZONTAL/8 이다
       (홈 x336 w11 → '-' x355 · '-' w6 → 화면명 x369, 둘 다 8).
       한 덩어리 문자열 '홈 - 서류 관리' 로 두면 하이픈 양옆이 일반 공백이라
       12px/500 에서 3.0px 밖에 안 된다 — 시안보다 5 좁다.
       화면 다섯 곳이 각자 갖고 있던 같은 규칙을 여기로 올린다. */
    .page-breadcrumb .bc-trail { display: inline-flex; align-items: center; gap: 8px; vertical-align: middle; }
    .navbar-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }

    /* Navbar icon buttons */
    /* 헤더 아이콘 버튼 — Figma: 32×32, 흰 배경, radius 8, 아이콘 16 */
    .btn-icon {
      width: 32px; height: 32px; border-radius: 8px;
      border: none; background: var(--gray-0);
      display: flex; align-items: center; justify-content: center; gap: 6px;
      color: var(--gray-800); cursor: pointer; position: relative;
      transition: var(--transition); flex-shrink: 0; font-size: 16px;
    }
    .btn-icon:hover { background: var(--primary-light); color: var(--primary); }

    /* 사용자 칩 — Figma: 흰 배경, radius 8, height 32, padding 0 12, gap 6 */
    .nav-user-chip {
      display: flex; align-items: center; gap: 6px;
      height: 32px; padding: 0 12px; border-radius: 8px;
      background: var(--gray-0); color: var(--gray-800);
      font-size: 13px; font-weight: 500; line-height: 1.6;
      flex-shrink: 0; white-space: nowrap;
    }
    .nav-user-chip-name { max-width: 140px; overflow: hidden; text-overflow: ellipsis; }
    .notif-dot {
      position: absolute; top: 7px; right: 7px;
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--danger); border: 1.5px solid #fff;
    }

    /* Navbar user avatar */
    .nav-user-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; cursor: pointer;
      margin-left: 4px; flex-shrink: 0;
      border: 2px solid var(--primary-light);
    }

    /* Navbar divider */
    .nav-divider {
      width: 1px; height: 20px; background: var(--border);
      margin: 0 4px; flex-shrink: 0;
    }

    /* ── Content ── */
    /* overflow-x:hidden 을 쓰면 세로도 auto 가 되어 이 요소가 스크롤 컨테이너가 된다.
       그러면 안쪽의 position:sticky 가 이 요소를 기준으로 잡는데, 정작 여기는 스크롤되지
       않아 붙어 있지 못하고 본문과 함께 밀려 올라간다(검수 화면 뷰어가 그랬다).
       clip 은 가로만 잘라내고 스크롤 컨테이너를 만들지 않는다. */
    .content-wrapper { flex: 1; display: flex; flex-direction: column; overflow-x: clip; padding-top: var(--nav-h); }
    /* Figma 174:1184 container — padding 0 16 16, 블록 간 gap 16 */
    /* Figma 148:5526 container — 본문 블록 사이 간격은 12 다 (16 이 아니다) */
    .page-body {
      flex: 1; min-width: 0;
      padding: 0 var(--content-pad) var(--content-pad);
      display: flex; flex-direction: column; gap: 12px;
    }

    /* ── 아래를 채우는 두 도구 ────────────────────────────
       .page-body 는 이미 세로 flex 이고 남는 높이를 갖고 있다(1920×1200 에서 1132).
       목록 화면은 .ds-grid-section 이 flex:1 이라 흰 카드가 아래까지 내려온다.
       그런데 대시보드·등록 폼·설정처럼 목록이 아닌 화면은 마지막 덩이가
       flex:0 1 auto 라 자라지 않아, 짧은 날에는 그 아래로 회색 바닥이 드러났다
       (설정 여섯 화면에서 최대 772).

       흰 카드는 대개 투명 껍데기 안에 들어 있어 껍데기만 늘려서는 회색이 그대로다.
       그래서 두 조각으로 나눈다 —
         .fill-rest  남는 높이를 받는다
         .fill-col   받은 높이를 다시 자식에게 나눠 주려면 세로 flex 여야 한다
       껍데기에 둘 다 붙이고, 그 안의 흰 카드에 .fill-rest 를 붙이면 끝까지 내려온다.

       `:last-child` 로 하지 않은 까닭 — 화면 서른아홉 중 열일곱이 숨은 모달이나
       <script> 를 DOM 마지막 자식으로 갖고 있어 엉뚱한 것이 잡힌다. */
    .page-body > .fill-rest,
    .fill-col > .fill-rest { flex: 1 1 auto; min-height: 0; }
    .fill-col { display: flex; flex-direction: column; min-height: 0; }


    /* ══════════════════════════════════════════════════
       Figma 표준 layout 본문 컴포넌트 (174:1184 하위)
       화면마다 따로 스타일을 짜면 시안과 어긋나므로 여기 한 곳에 둔다.
    ══════════════════════════════════════════════════ */

    /* 상태 필터 칩 (174:1185) — row gap 8 */
    .ds-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ds-chip {
      display: inline-flex; align-items: center; justify-content: center; gap: 4px;
      padding: 6px 10px; border-radius: 999px;
      background: var(--gray-100); color: var(--gray-500);
      font-size: 12px; font-weight: 700; line-height: 19px;
      border: none; cursor: pointer; text-decoration: none;
      transition: var(--transition);
    }
    .ds-chip:hover { color: var(--primary); }
    .ds-chip.active { background: var(--primary); color: var(--gray-0); }
    /* 건수 배지 — 16×16 원, 칩 상태에 따라 색이 뒤집힌다 */
    .ds-chip-count {
      display: inline-flex; align-items: center; justify-content: center;
      /* 시안은 16×16 정원이다 — 두 자리(37·34)도 16 그대로다.
         좌우 여백을 두면 두 자리가 20~21 로 벌어진다. 여백은 0 으로 두고,
         min-width 만 남겨 세 자리부터 글자 폭만큼 늘어나게 한다. */
      min-width: 16px; height: 16px; padding: 0; border-radius: 999px;
      background: var(--gray-500); color: var(--gray-0);
      font-size: 10px; font-weight: 700; line-height: 1; flex-shrink: 0;
    }
    .ds-chip.active .ds-chip-count { background: var(--gray-0); color: var(--primary); }

    /* 칩은 시안에서 검색 카드 '안쪽 첫 줄'이다.
       프레임 7개(114:4778 · 128:1744 · 148:5526 · 248:2923 · 266:66 · 282:53 · 282:934)가
       모두 같은 구조다 — 카드 1568×140 = pad 12 + 칩줄 31 + gap 12 + 구분선 1px + gap 12
       + 필드줄 61 + pad 12.
       화면 8곳의 마크업을 옮기는 대신, 칩 묶음이 검색 카드 바로 앞에 오면
       두 블록을 한 카드처럼 이어 붙인다. 칩만 있는 화면은 지금 모습 그대로다. */
    .ds-chips:has(+ .ds-filter-card) {
      padding: 12px 16px;
      border-radius: 12px 12px 0 0;
      background: var(--gray-0);
      margin-bottom: -12px;          /* .page-body 의 gap 12 를 지운다 */
    }
    .ds-chips:has(+ .ds-filter-card) + .ds-filter-card {
      border-radius: 0 0 12px 12px;
      /* 시안 Vector 4 는 1536×0 — 카드 전폭(1568)이 아니라 좌우 16 안쪽에만 그어진다.
         테두리 대신 배경으로 그으면 선이 카드 높이를 먹지 않아 140(시안값)이 그대로 나온다. */
      background-image: linear-gradient(var(--gray-100), var(--gray-100));
      background-repeat: no-repeat;
      background-position: 16px 0;
      background-size: calc(100% - 32px) 1px;
    }

    /* 화면들이 각자 정의해 쓰던 검색 줄(.filter-bar / .search-bar).
       내부 배치는 화면마다 입력 구성이 달라 그대로 두고, 바깥 껍데기만
       Figma 표준 필터 카드(174:1210)에 맞춘다 — 흰 카드·radius 12·padding 12/16.
       아래쪽 여백은 .page-body 의 gap 이 만든다. */
    .filter-bar, .search-bar {
      display: flex; align-items: flex-end; flex-wrap: wrap;
      gap: 8px 16px;
      padding: 12px 16px;
      border-radius: 12px;
      background: var(--gray-0);
    }

    /* 검색·필터 카드 (174:1210) — padding 12/16, gap 24, radius 12 */
    /* 시안 캐시 14장(248:4088 · 266:336 · 282:335 · 282:1204 · 324:338 · 324:4932 ·
       324:1986 · 324:10973 · 342:4328 · 352:555 · 352:3491 · 248:3205 · 207:1324 · 207:1478)이
       예외 없이 같다 — 안쪽 줄 1536 = 필드 그리드 1384 + gap 24 + 버튼 자리 128.
       버튼 자리 128 은 60×32 두 개(초기화·검색)와 gap 8 이 딱 들어가는 폭이다.
       그런데 결과바를 걷어내면서(499d611) 엑셀 저장·선택 상세·선택 인쇄 같은 단추가
       이 줄로 내려와 화면에 따라 최대 566 을 차지한다. 필드가 남는 자리를 받는 구조라
       단추가 많을수록 검색칸이 좁아졌다 — 1920 에서 열폭이 90.9~139.5 로 갈리고
       1280 에서는 20~68 까지 뭉개진다(cashbill 열폭 20 → 세 칸 묶어도 92).
       그래서 필드 그리드에 시안값을 못박고, 남는 단추는 다음 줄로 접는다.
       접히는 줄 사이는 시안 카드의 세로 gap 과 같은 12 다. */
    .ds-filter-card {
      display: flex; align-items: stretch; flex-wrap: wrap; gap: 12px 24px;
      padding: 12px 16px; border-radius: 12px;
      background: var(--gray-0);
    }
    /* 입력 영역은 9열 그리드 (174:1211) */
    /* 9열 · 열 사이 16.
       시안 전수(캐시 36장)에서 9열 검색 그리드의 gridColumnGap 은 16 이 27장, 12 가 9장이다.
       16 쪽에 「표준 레이아웃」 두 장(펼침·접힘)이 들어 있고, 12 는 먼저 그려진 아홉 장
       (거래처 관리 3 · 처방전 목록 1 · 주문 관리 5)뿐이라 16 을 표준으로 삼는다.
       그 아홉 장은 시안이 아직 따라오지 못한 자리로 보고 예외를 두지 않았다 — 디자이너 확인 대상. */
    /* 폭은 시안값 1384 로 못박는다 — 그래야 한 열이 어느 화면에서나 139.6 이다.
       카드가 그보다 좁아지면(1600 이하 · 사이드바 펼친 노트북) 100% 를 받아
       그 폭 안에서 아홉 열이 다시 고르게 나뉜다. */
    .ds-filter-fields {
      flex: 0 0 auto; width: min(100%, 1384px); min-width: 0;
      display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 16px;
    }
    .ds-filter-field { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
    .ds-filter-field.span-2 { grid-column: span 2; }
    .ds-filter-field.span-3 { grid-column: span 3; }
    .ds-filter-field.span-4 { grid-column: span 4; }
    /* 항목 이름 — Figma 필터 라벨 */
    /* Figma 114:4778 — 라벨 13/500 · lh21 · grayscale/700. 라벨 21 + gap 8 + 인풋 32 = 필드 61 */
    .ds-field-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
    /* 기간처럼 두 입력을 한 칸에 넣는 경우 */
    /* 자리가 모자라면 겹치지 말고 아래로 접는다 — 날짜 칸은 132 아래로 줄지 않는다 */
    .ds-field-range { display: flex; align-items: center; gap: 8px; min-width: 0; flex-wrap: wrap; row-gap: 6px; }   /* 시안 8 */
    .ds-field-range .form-control { min-width: 0; flex: 1; }
    /* 날짜는 「2026-06-01」과 달력 아이콘이 함께 서야 한다 — 그보다 좁아지면 글자가 잘린다 */
    .ds-field-range input[type="date"], .ds-field-range .ce-date-wrap { min-width: 132px; }
    /* 기간 두 입력 사이 '~' — 시안 31장이 예외 없이 13/400 · #101317(gray-1000)이다
       (표준 레이아웃 두 장 포함). gray-400 이던 것을 바로잡는다. */
    .ds-field-sep { color: var(--gray-1000); font-size: 13px; font-weight: 400; line-height: 21px; flex-shrink: 0; }
    /* 버튼은 우측 하단 정렬 (174:1236) */
    /* 단추 묶음은 늘 오른쪽 끝이다 — 다음 줄로 접혀도 마찬가지라 margin-left 를 auto 로 둔다
       (시안 48101589 은 x1791 로 안쪽 줄의 오른쪽 끝에 붙어 있다). */
    .ds-filter-actions { display: flex; align-items: flex-end; justify-content: flex-end; gap: 8px; flex-shrink: 0;
                         flex-wrap: wrap; row-gap: 8px; margin-left: auto; }
    /* 조회 결과 건수 — 목록 위에 띄를 따로 두지 않고 찾는 줄 안에 적는다.
       탭이 있는 화면은 탭 이름 뒤 괄호로 적고, 없는 화면만 이 칸을 쓴다. */
    .ds-filter-total { align-self: center; margin-right: auto; white-space: nowrap;
                       font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-600); }
    .ds-filter-total b { font-weight: 700; color: var(--gray-1000); }
    .ds-btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      min-width: 60px; height: 32px; padding: 0 12px;
      border-radius: 8px; background: var(--gray-0);
      border: 1px solid var(--gray-200); color: var(--gray-1000);
      font-size: 13px; font-weight: 500; line-height: 21px;
      cursor: pointer; transition: var(--transition); white-space: nowrap;
      text-decoration: none;   /* <a> 로 쓸 때 브라우저 기본 밑줄이 나온다 */
    }
    .ds-btn:hover { background: var(--gray-50); }
    /* 주 동작(검색)은 테두리만 primary — Figma 174:1239 */
    .ds-btn-primary { border-color: var(--primary); color: var(--primary); }
    .ds-btn-primary:hover { background: var(--primary-light); }

    /* 그리드 영역 (174:1241) — 상단바 + 카드, gap 12 */
    .ds-grid-section { display: flex; flex-direction: column; gap: 12px; flex: 1; min-height: 0;
                       padding-top: 4px; /* 시안 Frame 48101545 pad 4/0/0/0 */ }
    .ds-grid-bar {
      display: flex; align-items: center; justify-content: space-between;
      height: 32px; flex-shrink: 0;
      /* 자리가 남을 때는 space-between 이 알아서 벌리고, 좁아져 두 묶음이 만나는 순간부터
         이 12 가 버틴다. 없으면 건수 묶음과 안내문이 간격 0 으로 맞붙는다(1280 에서 확인). */
      column-gap: 12px;
    }
    .ds-grid-bar-left  { display: flex; align-items: center; gap: 12px; min-width: 0; flex-shrink: 0; }
    /* 오른쪽 액션 묶음 — 상단바는 space-between 이라 그대로 우측에 붙는다.
       시안 148:5526 은 안내문 묶음과 버튼 묶음 사이 12, 버튼끼리 8 이다. */
    .ds-grid-bar-right { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .ds-grid-total { font-size: 16px; font-weight: 700; line-height: 26px; color: var(--gray-800); }
    /* b 는 브라우저 기본이 bolder 라 부모 700 위에서 900 으로 풀린다.
       시안(114:4778)은 '전체'·숫자·'건' 이 모두 16/700 이다. 굵기를 못박는다. */
    .ds-grid-total b { font-weight: 700; color: var(--primary); }
    /* '전체 N건' 과 '선택 N건' 사이 4×4 구분점 (148:5526 Rectangle 10 · r999 · gray-300) */
    .ds-grid-sel { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-600); }
    {{-- 숫자에도 굵기를 못박는다. <b> 의 브라우저 기본 bolder 가 부모 500 위에서 700 으로 올려
         시안(33장 전부 13/500 · #4898A9)과 어긋난다 — 「전체 N건」이 900 으로 찍히던 것과 같은 이치다. --}}
    .ds-grid-sel b { font-weight: 500; color: var(--primary-400); }
    .ds-grid-total + .ds-grid-sel::before {
      content: ''; display: inline-block; vertical-align: middle;
      width: 4px; height: 4px; border-radius: 999px;
      background: var(--gray-300); margin-right: 12px;
    }
    /* 안내문 — 시안은 앞에 12×12 alert-circle 이 붙고 글자와 간격 4 다.
       마크업을 화면마다 고치지 않도록 아이콘은 mask 로 그린다. */
    .ds-grid-hint {
      /* 문장을 flex 로 담으면 안 된다. flex 컨테이너는 자식 사이의 '공백만 있는 글자마디'를
         아예 그리지 않아서 "행을 <b>더블클릭</b>하면" 이 "행을더블클릭하면" 으로 붙는다.
         gap 을 주면 이번엔 <b> 앞뒤로 간격이 끼어들어 "행을 더블클릭 하면" 으로 갈라진다.
         둘 다 문장을 망가뜨린다 — 안쪽은 보통 글줄(inline)로 두고 아이콘만 inline-block 으로
         앞에 세운다. 이러면 낱말 사이 공백이 글자 그대로 살아난다. (.ds-grid-bar 의
         flex 아이템이라 바깥 display 는 어차피 block 으로 굳는다.) */
      display: inline-block;
      min-width: 0; margin-right: 4px;
      font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600);
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .ds-grid-hint::before {
      /* 상자를 글줄 높이(19)만큼 잡고 vertical-align:top 으로 글줄 꼭대기에 맞춘 뒤
         12×12 아이콘을 그 안에서 가운데 그린다. 이러면 어림 보정값 없이 정확히 가운데 선다. */
      content: ''; display: inline-block; vertical-align: top;
      width: 12px; height: 19px; margin-right: 4px;
      background: currentColor;
      -webkit-mask: var(--icon-alert-circle) center / 12px 12px no-repeat;
              mask: var(--icon-alert-circle) center / 12px 12px no-repeat;
    }
    /* 개발자가 안내문 앞에 아이콘을 이미 넣어 둔 화면이 있다(NHIS 청구의 info-circle,
       정산/회계 가상계좌 탭의 경고 삼각형). 그 자리에 전역 아이콘까지 그리면 두 개가 나란히 선다.
       마크업에서 지우는 대신 — 개발자가 고른 아이콘이 뜻을 나르는 자리다 — 전역 쪽을 접는다. */
    .ds-grid-hint:has(> i:first-child)::before,
    .ds-grid-hint:has(> svg:first-child)::before { content: none; }
    /* 결과바 버튼은 시안에서 테두리가 없다 (검색 카드의 초기화·검색과 다르다) */
    .ds-grid-bar .ds-btn { border-color: transparent; flex-shrink: 0; }
    .ds-grid-bar .ds-btn:hover { border-color: var(--gray-200); }
    /* 결과바 버튼은 primary 여도 테두리가 없다 — 시안 19개 전수 확인
       (엑셀 저장 · 환자 추가 · 선택 상세 · 신규 위임동의 전송 · 캘린더뷰 …).
       테두리가 있는 것은 카드 안 버튼뿐이다(처방전 검수 화면 · 주문 상세). */
    /* 카드는 안에 든 것만큼만 높다. flex:1 로 남는 자리를 다 먹게 해 두었더니,
       찾은 것이 몇 줄뿐인 날에는 표 아래로 흰 바닥이 화면 끝까지 이어졌다.
       드물게 긴 목록은 그리드가 height:'fit' 으로 뷰포트까지 채우고 안에서 스크롤한다. */
    .ds-grid-card {
      display: flex; flex-direction: column; flex: 1; min-height: 0;
      background: var(--gray-0); border-radius: 12px; overflow: hidden;
    }
    /* 카드는 남는 자리를 끝까지 받는다 — 찾은 것이 몇 줄뿐이어도 바닥까지 흰색이다.
       전에는 `flex: 0 1 auto` 로 내용만큼만 두었다. 그러면 다섯 줄짜리 날에
       카드가 574 에서 끝나고 그 아래 610 이 회색으로 드러났다(2000×1200 · 거래처 관리).
       시안도 목록 카드를 `layoutGrow 1` 로 그린다(391:451 · 1568×858).
       가로로 놓이는 카드(재구매 달력)는 `.ds-grid-section` 의 직계 자식이 아니라
       이 규칙에 걸리지 않는다 — 예전에 모든 카드에 걸었다가 달력이 562 로 접혔던 자리다. */
    .ds-grid-section > .ds-grid-card { flex: 1 1 auto; }
    /* 카드가 overflow:hidden 이라, 안에 들어간 패널(조회 결과·상세 내용)이
       카드보다 길면 잘리고 스크롤도 안 생긴다. 패널이 스스로 스크롤하게 한다. */
    .ds-grid-card > [id^="pnl"] { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
    /* 그리드는 이미 카드(r12) 안에 있다 — 자기 테두리·모서리를 또 갖지 않는다.
       .card 안의 그리드까지 한꺼번에 걷지는 않는다 — 거기에는 카드 안쪽 여백만큼
       들여 놓은 표(상품 조회 로그 같은)도 있어, 테두리를 걷으면 어디까지가 표인지
       가장자리가 사라진다. 카드 밑변까지 붙는 표는 제 화면에서 걷는다. */
    .ds-grid-card .cg-wrap { border: 0; border-radius: 0; }
    /* 그리드 껍데기(.cg-root)가 카드 높이를 받아 세로로 나눈다 —
       판은 남는 자리를 다 받고, 「전체 N건」 띠는 카드 바닥에 붙는다.
       전에는 띠가 표 바로 밑에 떠 있고 그 아래가 통째로 비었다.

       카드와 그리드 사이에 display:block 껍데기가 끼면 높이가 거기서 끊긴다
       (#pnlUsers · #pnlList · .ti-grid-pane · .card-body 가 그렇다).
       그리드를 품은 껍데기는 세로 flex 로 높이를 넘긴다 — 곁에 선 것들
       (.ds-panel-actions · .pg-note 같은)은 제 높이 그대로 남는다. */
    .ds-grid-card *:has(.cg-root),
    .card *:has(.cg-root) { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }
    .ds-grid-card .cg-root,
    .card .cg-root,
    .page-body > .cg-root { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    .cg-root > .cg-wrap { flex: 1 1 auto; min-height: 0; }

    /* 카드 안 패널 탭 (114:4778) — h44 · pad 0/16 · gap 16 · 하단 1px */
    .pnl-tabs { display: flex; gap: 8px; padding: 0 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
    .pnl-tab {
      height: 44px; padding: 0 8px; border: none; background: none; cursor: pointer;
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 500; line-height: 21px;
      /* 시안(114:4778)의 비활성 탭 '상세 내용' 은 gray-600 이다. gray-400 은 흰 배경
         대비 2.70:1 로 읽기 어렵다 — gray-600 은 5.32:1. --text-muted 토큰 자체는
         다른 화면도 쓰므로 건드리지 않고 이 규칙만 옮긴다. */
      color: var(--gray-600);
      border-bottom: 1px solid transparent; margin-bottom: -1px;
    }
    /* 탭 이름 뒤 괄호 안의 건수 — 탭은 gap 6 의 flex 라 그대로 두면
       「조회 결과 ( 23 )」처럼 띄어서 보인다. 그 한 칸만 되돌린다. */
    .pnl-tab-cnt, .titab .pnl-tab-cnt { margin-left: -6px; }
    .pnl-tab:hover  { color: var(--primary); }
    .pnl-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
    .pnl-empty {
      padding: 60px 20px; text-align: center;
      /* gray-400 은 흰 배경 대비 2.70:1 이라 읽기 어렵다. 시안 안내문 색인 gray-600 은 5.32:1. */
      font-size: 13px; font-weight: 400; line-height: 21px; color: var(--gray-600);
    }
    /* 카드 하단 페이지네이션 줄 (174:1333) */
    .ds-grid-foot {
      display: flex; align-items: center; justify-content: flex-end; gap: 2px;
      padding: 12px; border-top: 1px solid var(--gray-200);
    }

    /* ── Mobile Overlay ── */
    .layout-overlay {
      display: none; position: fixed; inset: 0; z-index: 99;
      background: rgba(13,27,42,.35); backdrop-filter: blur(3px);
    }
    .layout-overlay.show { display: block; }

    /* ═══════════════════════════════════════════
       COMPONENTS
    ═══════════════════════════════════════════ */

    /* ── Cards ── */
    /* Figma 카드에는 그림자가 없다 (radius 12 · 흰 배경) */
    /* 시안 카드는 흰 채움에 테두리가 없다 — 148:6653(1568×749) · 156:7261(1536×93) ·
       248:4141 · 342:4381 어디에도 stroke 가 없고, 안여백은 12/16 이다.
       목록 카드(.ds-grid-card)는 이미 테두리가 없는데 상세·설정 화면이 쓰는 이 카드만
       1px 을 두르고 있어 같은 부품이 화면마다 달라 보였다.
       화면 쉰둘을 재 보니 보이는 카드 열아홉이 모두 회색 바탕 위에 홀로 있고
       카드 안에 든 카드는 하나도 없다 — 테두리가 없어도 경계가 살아 있다. */
    .card {
      background: var(--bg-card);
      border: none;
      border-radius: var(--radius-lg);
      box-shadow: none;
    }
    .card-header {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; border-bottom: 1px solid var(--border);
      background: transparent;
    }
    .card-header-title { font-size: 14px; font-weight: 700; color: var(--text-primary); letter-spacing: -.2px; }
    .card-header-sub   { font-size: 12px; color: var(--text-muted); margin-left: 4px; }
    .card-body { padding: 12px 16px; }
    .mt-4 { margin-top: 12px; } .mb-4 { margin-bottom: 12px; }

    /* ── Buttons ── */
    /* Figma: 높이 32 · 13px/500 · padding 좌우 12 · radius 8 — .ds-btn 과 같은 규격 */
    .btn {
      /* 아이콘↔글자 사이는 시안이 8 이다(248:4115 초기화 60×32 HORIZONTAL/8).
         .ds-btn 은 8 인데 이쪽만 6 이라 같은 크기 단추의 글자 시작이 2 어긋났다. */
      display: inline-flex; align-items: center; gap: 8px;
      padding: 5px 12px; border-radius: var(--radius);
      font-size: 13px; font-weight: 500; line-height: 20px; cursor: pointer;
      text-decoration: none; border: 1px solid transparent;
      transition: var(--transition); white-space: nowrap;
      font-family: inherit; letter-spacing: -.1px;
    }
    .btn-primary {
      background: var(--primary); color: #fff; border-color: var(--primary);
    }
    .btn-primary:hover { background: var(--primary-dark); color: #fff; }
    .btn-success { background: var(--success); color: #fff; border-color: var(--success); }
    .btn-success:hover { background: #0fa05c; color: #fff; }
    .btn-warning { background: var(--warning); color: #fff; border-color: var(--warning); }
    .btn-warning:hover { background: #d97706; color: #fff; }
    .btn-danger  { background: var(--danger);  color: #fff; border-color: var(--danger); }
    .btn-danger:hover  { background: #dc2626; color: #fff; }
    .btn-outline {
      background: var(--bg-card); border-color: var(--border); color: var(--text-secondary);
    }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    .btn-ghost { background: transparent; border-color: transparent; color: var(--text-secondary); }
    .btn-ghost:hover { background: var(--bg); color: var(--primary); }
    .btn-sm  { padding: 5px 12px; font-size: 13px; }
    .btn-xs  { padding: 3px 10px; font-size: 13px; }
    .w-full  { width: 100%; justify-content: center; }
    .flex-1  { flex: 1; }

    /* Button loading states */
    .btn[data-loading]     { opacity: .75; cursor: not-allowed; pointer-events: none; }
    .btn[data-state="success"] { background: var(--success) !important; border-color: var(--success) !important; color: #fff !important; pointer-events: none; }
    .btn[data-state="error"]   { background: var(--danger)  !important; border-color: var(--danger)  !important; color: #fff !important; pointer-events: none; }

    /* ── Badges ── */
    /* Figma: 높이 22 · radius 6 · padding 2/6 · 11px/500 — 알약이 아니라 사각 라운드 */
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 6px; border-radius: 6px; font-size: 11px; font-weight: 500;
      line-height: 18px; letter-spacing: -.1px;
    }
    /* 상태 배지 색 — Figma 8개 프레임 실측 결과에 맞춘다.
       시안은 정상 진행 상태를 전부 primary 연톤으로 그린다(OCR 완료·주문 대기·대기 중 13건),
       주의만 alert 연톤(검수 필요 4건)이다. 시안 전체에 초록·주황은 0건이므로
       success/info 를 primary 로 돌린다. 상태 구분은 배지 안 라벨 문구가 계속 담당한다.
       warning 은 시안에 대응 표본이 없어 손대지 않았다.

       2026-08-20 DS 개정으로 success·warning **램프가 생겼지만**, 화면 시안 67장을
       다시 훑어도 #88BE75·#FBAB61 을 쓰는 프레임은 여전히 0장이다 — 램프만 정의됐다.
       그래서 이 규칙들은 그대로 둔다. 화면 시안이 새 색을 쓰기 시작하면 그때 따라간다.
       .badge-warning 의 #B45309 도 남긴다 — DS 의 warning-500(#FBAB61)을 글자로 쓰면
       warning-50 바탕 위 대비가 1.77:1 이라 읽히지 않는다(디자이너 확인 대상). */
    .badge-primary,   .bg-label-primary   { background: var(--primary-light); color: var(--primary); }
    .badge-success,   .bg-label-success   { background: var(--primary-light); color: var(--primary); }
    .badge-warning,   .bg-label-warning   { background: var(--warning-light); color: #B45309; }
    .badge-danger,    .bg-label-danger    { background: var(--danger-light);  color: var(--danger); }
    .badge-info,      .bg-label-info      { background: var(--primary-light); color: var(--primary); }
    .badge-secondary, .bg-label-secondary { background: var(--border-light);  color: var(--text-secondary); }
    .badge.bg-primary  { background: var(--primary) !important; color: #fff; }
    .badge.bg-success  { background: var(--success) !important; color: #fff; }
    .badge.bg-warning  { background: var(--warning) !important; color: #fff; }
    .badge.bg-danger   { background: var(--danger)  !important; color: #fff; }
    .badge.bg-info     { background: var(--info)    !important; color: #fff; }

    /* ── Forms ── */
    .form-group { margin-bottom: 10px; }
    .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 5px; letter-spacing: -.1px; }
    .form-label span { color: var(--danger); }
    /* Figma: 높이 32 · 13px · padding 좌우 12 · radius 8 · border gray-200
       (5 + 20 + 5 + 테두리 2 = 32) */
    .form-control {
      width: 100%; padding: 5px 12px; font-size: 13px;
      border: 1px solid var(--border); border-radius: var(--radius);
      background: var(--bg-card); color: var(--text-primary);
      transition: var(--transition); outline: none; font-family: inherit;
      line-height: 20px;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(40,121,139,.12);
    }
    /* Figma placeholder = grayscale/500 (--text-muted 는 gray-400 이라 한 단계 연하다) */
    .form-control::placeholder { color: var(--gray-500); }
    /* 고르는 칸은 어디서나 같은 화살표를 단다. .form-select 를 빠뜨린 select 가
       열한 곳 있었고(마스터 관리·위임장 서명의 검색줄, 거래처 등록 모달, 처방전 검수 폼 여섯)
       그 자리만 운영체제 기본 화살표가 나와 같은 줄의 옆 칸과 달라 보였다.
       마크업을 열한 군데 고치는 대신 select 면 규칙이 걸리게 한다. */
    .form-select,
    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238B95A1' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
      padding-right: 30px;
    }
    /* textarea 는 시안에 규격이 없다. 한 줄 입력(32px)에 맞춘 패딩을 그대로 쓰면
       여러 줄 입력이 위아래로 눌리므로 기존 여백을 유지한다. */
    textarea.form-control { resize: vertical; padding: 9px 12px; line-height: 1.5; }
    .input-group { display: flex; }
    .input-group .form-control { border-radius: var(--radius) 0 0 var(--radius); }
    .input-group .btn { border-radius: 0 var(--radius) var(--radius) 0; }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    /* wwGrid 의 표(.cg-table)는 제외한다. 시안의 그리드 머리글은 대문자도 자간도 없다
       (13/700 · 왼쪽 · 자간 0). 여기서 걸러 두지 않으면 wwGrid.css 가 선언으로 덮지 않는
       두 속성이 그대로 새어, 'No' 가 'NO' 로 · '신환 Master 등록일' 이
       '신환 MASTER 등록일' 로 보이고 자간이 .5px 벌어진다. */
    table:not(.cg-table) thead th {
      padding: 12px; font-size: 13px; font-weight: 700;
      color: var(--text-muted); text-align: left; text-transform: uppercase;
      letter-spacing: .5px; border-bottom: 1px solid var(--border);
      white-space: nowrap; background: var(--gray-50);
    }
    thead th:first-child { border-radius: var(--radius-lg) 0 0 0; }
    thead th:last-child  { border-radius: 0 var(--radius-lg) 0 0; }
    tbody td {
      padding: 10px 12px; font-size: 13px;
      border-bottom: 1px solid var(--border-light);
      vertical-align: middle; color: var(--text-secondary);
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: var(--gray-50); }

    /* ── Patient Card ── */
    .patient-card {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 16px; border-radius: var(--radius-lg);
      border: 1px solid var(--primary-accent); background: var(--primary-light);
      margin-bottom: 12px;
    }
    .patient-avatar {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; flex-shrink: 0;
    }
    .patient-name   { font-size: 14px; font-weight: 700; letter-spacing: -.2px; }
    .patient-detail { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

    /* ── OCR Fields ── */
    .ocr-field {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border-radius: var(--radius);
      border: 1px solid var(--border); margin-bottom: 6px;
      background: var(--bg);
    }
    .ocr-label { font-size: 11px; color: var(--text-muted); width: 80px; flex-shrink: 0; }
    .ocr-value { font-size: 13px; font-weight: 500; flex: 1; }
    .ocr-check { color: var(--success); font-size: 14px; }
    .ocr-warn  { color: var(--warning); font-size: 14px; }

    /* ── Alerts ── */
    .alert {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; border-radius: var(--radius-lg);
      margin-bottom: 16px; font-size: 13px; font-weight: 500;
      border-left: 4px solid transparent;
    }
    .alert-success { background: var(--success-light); border-color: var(--success); color: #065F46; }
    .alert-danger  { background: var(--danger-light);  border-color: var(--danger);  color: #991B1B; }
    .alert-warning { background: var(--warning-light); border-color: var(--warning); color: #92400E; }
    .alert-info    { background: var(--info-light);    border-color: var(--info);    color: #0C4A6E; }

    /* ── Card Footer ── */
    .card-footer {
      padding: 12px 18px; border-top: 1px solid var(--border);
      background: var(--gray-50); border-radius: 0 0 var(--radius-lg) var(--radius-lg);
      font-size: 13px; color: var(--text-muted);
    }

    /* ── Pagination ── */
    .pagination { display: flex; gap: 4px; align-items: center; margin: 0; flex-wrap: wrap; }
    /* Figma: 높이 28 · radius 6 · 13px/500 · 활성은 채움이 아니라 연톤 */
    .page-item .page-link {
      display: flex; align-items: center; justify-content: center;
      min-width: 28px; height: 28px; padding: 0 8px;
      border-radius: 6px; font-size: 13px; font-weight: 500;
      border: 1px solid var(--border); background: #fff; color: var(--text-secondary);
      text-decoration: none; transition: var(--transition);
    }
    .page-item .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    .page-item.active .page-link { background: var(--primary-light); border-color: var(--primary-light); color: var(--primary); }
    .page-item.disabled .page-link { opacity: .4; pointer-events: none; }

    /* ── Utilities ── */
    .fw-bold      { font-weight: 700; }
    .text-primary { color: var(--primary)    !important; }
    .text-success { color: var(--success)    !important; }
    .text-danger  { color: var(--danger)     !important; }
    .text-muted   { color: var(--text-muted) !important; }
    .mt-3 { margin-top: 12px; }

    /* ── Toast ── */
    .toast-container {
      position: fixed; bottom: 20px; right: 20px; z-index: 9999;
      display: flex; flex-direction: column; gap: 8px; max-width: 360px;
    }
    /* 부트스트랩 5 는 .toast:not(.showing):not(.show) 로 토스트를 숨겨 둔다(0,3,0).
       우리 .toast(0,1,0) 규칙이 눌려, showToast() 가 만든 것이 화면에 뜨지 않았다 —
       이 화면의 창고 알림뿐 아니라 앱 전체의 토스트가 그랬다.
       id 를 앞에 붙여(1,1,0) 되찾는다. 부트스트랩의 .show 를 붙이는 길도 있으나,
       그러면 토스트를 만드는 곳마다 그 약속을 지켜야 한다. */
    #toastContainer .toast { display: flex; }
    .toast {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 13px 16px; border-radius: 8px; color: #fff;
      font-size: 13px; font-weight: 500; box-shadow: var(--shadow-lg);
      animation: slideIn .25s ease; min-width: 260px;
      word-break: keep-all; line-height: 1.5; background: var(--gray-900);
      border-left: 3px solid transparent;
    }
    .toast .t-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
    .toast .t-msg  { flex: 1; }
    .toast.success { border-left-color: var(--success); }
    .toast.success .t-icon { color: var(--success); }
    .toast.danger  { border-left-color: var(--danger); }
    .toast.danger  .t-icon { color: #FCA5A5; }
    .toast.info    { border-left-color: var(--info); }
    .toast.info    .t-icon { color: #7DD3FC; }
    .toast.warning { border-left-color: var(--warning); }
    .toast.warning .t-icon { color: #FCD34D; }
    @keyframes slideIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }

    /* ── Chat Toast ── */
    .chat-toast {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px; border-radius: 12px;
      background: var(--gray-900); color: #fff;
      box-shadow: var(--shadow-lg);
      animation: slideIn .25s ease; min-width: 300px; max-width: 360px;
      cursor: pointer; border-left: 3px solid var(--primary);
      position: relative; pointer-events: auto;
    }
    .chat-toast:hover { background: var(--gray-1000); }
    .chat-toast-avatar {
      width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 700;
    }
    .chat-toast-body { flex: 1; min-width: 0; }
    .chat-toast-header { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
    .chat-toast-name { font-size: 13px; font-weight: 700; color: #fff; }
    .chat-toast-room { font-size: 11px; color: var(--gray-400); }
    .chat-toast-msg {
      font-size: 13px; color: var(--gray-300); font-weight: 400;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;
    }
    .chat-toast-time { font-size: 10px; color: var(--gray-600); margin-top: 2px; }
    .chat-toast-close {
      position: absolute; top: 8px; right: 10px;
      background: none; border: none; color: var(--gray-600);
      font-size: 14px; cursor: pointer; line-height: 1; padding: 2px;
    }
    .chat-toast-close:hover { color: #fff; }
    .chat-toast-icon { font-size: 11px; color: var(--gray-400); }
    @keyframes chatBtnPulse {
      0%   { background: transparent; transform: scale(1); }
      50%  { background: rgba(40,121,139,.2); transform: scale(1.15); }
      100% { background: transparent; transform: scale(1); }
    }

    /* ── Responsive ── */
    @media (max-width: 1200px) {
      .layout-menu { transform: translateX(-100%); }
      .layout-menu.open { transform: translateX(0); }
      .layout-page { margin-left: 0 !important; }
      .layout-overlay.show { display: block; }
      .menu-collapse-btn { display: none !important; }
      .layout-navbar { left: 0 !important; }
    }
    @media (max-width: 768px) {
      .layout-navbar { padding: 0 14px; min-height: 54px; left: 0 !important; }
      :root { --nav-h: 54px; }
      .page-title { font-size: 14px; }
      .page-breadcrumb { display: none; }
      .page-body { padding: 14px 14px 60px; }
      .card-body { padding: 14px; }
      .card-header { padding: 12px 14px; }
      table { font-size: 13px; }
      thead th { padding: 12px; font-size: 13px; }
      tbody td { padding: 10px 12px; font-size: 13px; }
    }

    /* ── Theme Picker ── */
    .theme-picker-wrap { position: relative; }
    .theme-panel {
      position: absolute; top: calc(100% + 6px); right: 0;
      background: #fff; border: 1px solid var(--border);
      border-radius: 12px; padding: 14px 12px;
      box-shadow: var(--shadow-lg); width: 192px; z-index: 300;
      display: none; animation: fadeUp .15s ease;
    }
    .theme-panel.open { display: block; }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(6px); }
      to   { opacity: 1; transform: none; }
    }
    .theme-panel-title {
      font-size: 10px; font-weight: 700; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px;
    }
    .theme-swatches { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .theme-swatch {
      width: 30px; height: 30px; border-radius: 50%; cursor: pointer;
      border: 2.5px solid transparent; position: relative;
      transition: transform .12s, border-color .12s, box-shadow .12s;
    }
    .theme-swatch:hover { transform: scale(1.18); box-shadow: 0 2px 8px rgba(0,0,0,.2); }
    .theme-swatch.active { border-color: #0D1B2A; }
    .theme-swatch.active::after {
      content: '✓'; position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 13px; font-weight: 700; line-height: 1;
    }
    .theme-label { font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 8px; }

    /* ═══════════════════════════════════════════
       GLOBAL SHARED COMPONENTS
    ═══════════════════════════════════════════ */

    /* ── Stat cards (all pages) ──
       세 화면이 쓴다 — 대시보드 · 기관 공지사항 · CE샵 모니터링.
       대시보드는 시안 382:107 에 맞추느라 이 규칙을 화면에서 통째로 덮어쓰고 있었고,
       나머지 둘은 옛 값 그대로여서 같은 부품이 화면마다 달랐다:
         안여백 16 ↔ 18/20 · 그림자 없음 ↔ 있음 · 칸 사이 12 ↔ 14 ·
         아이콘 32×32 r8/16 ↔ 48×48 r12/22 · 값 14/700 ↔ 24/700.
       시안에는 17px 이상 글자도, 카드 그림자도 없다 — 시안값을 전역으로 올린다.
       (대시보드의 같은 선언은 근거 주석을 안고 있어 그대로 둔다. 값은 이제 같다.) */
    .stat-grid { display: grid; gap: 12px; }
    /* 시안 382:383 · 382:6832 는 251×73 이고 stroke 도 effect 도 없다 —
       테두리 1px 이 있으면 73 이 아니라 75 가 된다. .card 와 같은 이유로 걷어낸다. */
    .stat-card {
      background: var(--gray-0); border: none; border-radius: 12px;
      box-shadow: none; padding: 16px;
      display: flex; align-items: center; gap: 16px;
      text-decoration: none; color: inherit; cursor: pointer;
      transition: var(--transition);
    }
    /* 시안 카드는 평평하다 — 들림·그림자 대신 테두리 색만 바뀐다 */
    .stat-card:hover { background: var(--gray-50); color: inherit; }
    .stat-icon {
      width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 16px;
    }
    /* 화면 시안에는 초록·주황·남색이 없다(67장 전수 0건 — 2026-08-20 DS 개정 뒤에도 그렇다.
       램프는 생겼지만 어느 화면도 쓰지 않는다). 강조는 primary 램프, 처리 대기는 alert 램프로만
       나눈다 (클래스 이름은 그대로 둔다 — 화면들이 이 이름으로 붙인다). */
    .stat-icon.primary { background: var(--primary-50); color: var(--primary-500); }
    .stat-icon.success { background: var(--primary-50); color: var(--primary-700); }
    .stat-icon.warning { background: var(--alert-50);   color: var(--alert-500); }
    .stat-icon.danger  { background: var(--alert-50);   color: var(--alert-500); }
    .stat-icon.info    { background: var(--primary-50); color: var(--primary-400); }
    .stat-icon.purple  { background: var(--primary-50); color: var(--primary-600); }
    .stat-icon.gray    { background: var(--gray-100);   color: var(--gray-600); }
    .stat-val   { font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-1000); }
    .stat-label { font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-800); margin-top: 0; }
    .stat-info  { min-width: 0; }

    /* ── Pill tabs (status/type filter) ── */
    .tab-pills {
      display: flex; gap: 3px; padding: 4px; background: var(--bg);
      border-radius: 12px; flex-wrap: wrap; border: 1px solid var(--border);
    }
    .tab-pill {
      padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500;
      color: var(--text-muted); cursor: pointer; transition: var(--transition);
      border: none; background: transparent; white-space: nowrap;
      display: inline-flex; align-items: center; gap: 6px; line-height: 1;
    }
    .tab-pill:hover { color: var(--text-primary); background: rgba(255,255,255,.7); }
    .tab-pill.active { background: #fff; color: var(--primary); box-shadow: var(--shadow); }
    .tab-count {
      background: var(--border); color: var(--text-muted);
      font-size: 10px; padding: 1px 6px; border-radius: 999px; font-weight: 700; min-width: 18px; text-align: center;
    }
    .tab-pill.active .tab-count { background: var(--primary-light); color: var(--primary); }

    /* ── Underline tabs ── */
    .tab-underline { display: flex; border-bottom: 2px solid var(--border); gap: 0; margin-bottom: 18px; }
    .tab-u {
      padding: 9px 18px; font-size: 13px; font-weight: 500; color: var(--text-muted);
      cursor: pointer; border: none; background: transparent; margin-bottom: -2px;
      border-bottom: 2px solid transparent; transition: var(--transition);
      display: inline-flex; align-items: center; gap: 6px;
    }
    .tab-u:hover { color: var(--text-primary); }
    .tab-u.active { color: var(--primary); border-bottom-color: var(--primary); }

    /* ── Filter bar ── (표준 정의는 위 DS 컴포넌트 영역에 있다) */
    /* 세로 막대는 두지 않는다 — 「~」 글자가 이미 사이를 가르고 있어 막대까지 서면
       한 줄에 구분자가 둘이 된다. 바탕만 지우고 폭 1px 을 남겼더니 이번에는 그 「~」가
       1px 안에 갇혀 보이지 않았다 — 크기는 글자가 정하게 둔다. */
    .filter-sep { margin: 0 4px; flex-shrink: 0; }
    .filter-label { font-size: 13px; font-weight: 500; color: var(--text-muted); white-space: nowrap; }

    /* ── Search input with icon ── */
    .search-wrap { position: relative; display: inline-flex; align-items: center; }
    .search-wrap > i { position: absolute; left: 10px; color: var(--text-muted); font-size: 16px; pointer-events: none; }
    .search-wrap .form-control { padding-left: 34px; }

    /* ── Online/offline dot ── */
    .status-dot { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500; }
    .status-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .status-dot.online  { color: var(--success); }
    .status-dot.online::before  { background: var(--success); box-shadow: 0 0 0 2px var(--success-light); }
    .status-dot.offline { color: var(--text-muted); }
    .status-dot.offline::before { background: var(--gray-400); }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 48px 24px; }
    /* 자식 선택자여야 한다 — 그냥 .empty-state i 로 두면 이 안에 든 단추의 아이콘까지
       38px 블록으로 만들어 「수집 시작」 단추가 143×62 로 부풀었다(다른 단추는 32). */
    .empty-state > i { font-size: 38px; opacity: .25; display: block; margin-bottom: 12px; color: var(--text-muted); }
    .empty-state p { font-size: 13px; color: var(--text-muted); margin: 0 0 12px; }

    /* ── Modal overlay (design system) ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 1000;
      background: rgba(13,27,42,.45); backdrop-filter: blur(4px);
      align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--bg-card); border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg); width: 100%; position: relative;
      animation: fadeUp .18s ease;
      display: flex; flex-direction: column; max-height: 90vh;
    }
    .modal-box.sm  { max-width: 420px; }
    .modal-box.md  { max-width: 580px; }
    .modal-box.lg  { max-width: 780px; }
    .modal-box.xl  { max-width: 960px; }
    .modal-hd {
      display: flex; align-items: center; gap: 10px;
      /* 시안 165:1316 머리 960×54 — pad 16/24 · gap 12 */
      padding: 16px 24px; border-bottom: 1px solid var(--border); flex-shrink: 0;
    }
    .modal-title { font-size: 14px; font-weight: 700; color: var(--text-primary); flex: 1; letter-spacing: -.2px; }
    /* 모달 닫기 — 시안 165:1318 은 16×16 아이콘이고 머리는 54(pad16 + 22 + pad16)다.
       28×28 · 18px 이면 머리가 그만큼 커진다. 24×24 · 16px 이 머리를 지키는 최소다.
       화면들이 10×16 · 11×29 · 13×20 … 열 가지로 갈려 있어 한 값으로 모은다. */
    .modal-close {
      width: 24px; height: 24px; border-radius: 6px; border: none;
      background: transparent; color: var(--gray-500); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; line-height: 1; padding: 0;
      transition: var(--transition); flex-shrink: 0;
    }
    .modal-close:hover { background: var(--bg); color: var(--text-primary); }
    /* 시안 165:1320 본문 — pad 24 · 세로 gap 16 */
    .modal-bd { padding: 24px; overflow-y: auto; flex: 1; }
    .modal-ft {
      padding: 12px 20px; border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: flex-end; gap: 8px;
      flex-shrink: 0; background: var(--gray-50); border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    /* ── Info grid (key-value pairs) ── */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .info-grid-3 { grid-template-columns: repeat(3,1fr); }
    .info-cell { background: var(--bg); border-radius: var(--radius); padding: 10px 12px; }
    /* 항목 이름은 한 값으로 쓴다 — .ds-field-label 과 같다(Figma 114:4778 ·
       13/500 · lh21 · grayscale/700). 예전에는 11/700 · gray-400 에 영문 대문자
       변환까지 걸린 캡션 값이라, 같은 화면의 검색 필터 라벨과 나란히 두면 같은
       말인데 크기도 색도 달랐다. 두 화면이 이미 각자 이 값으로 되돌려 놓고 있었다. */
    .info-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); margin-bottom: 4px; }
    .info-value { font-size: 13px; font-weight: 500; color: var(--text-primary); }

    /* ── Section card title ── */
    .section-title { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    /* ── Bootstrap compat shims (for pages still using Bootstrap) ── */
    .d-flex { display: flex; }
    .align-items-center { align-items: center; }
    .justify-content-between { justify-content: space-between; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 12px; }
    .ms-auto { margin-left: auto; }
    .me-1 { margin-right: 4px; }
    .mb-0 { margin-bottom: 0; }
    .fw-semibold { font-weight: 600; }
    .fw-bold { font-weight: 700; }
    .small { font-size: 12px; }
    .py-5 { padding-top: 48px; padding-bottom: 48px; }
    .text-center { text-align: center; }
    .text-end { text-align: right; }
    .table-responsive { overflow-x: auto; }
    .font-monospace { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace; }
    .text-decoration-none { text-decoration: none; }
    .text-dark { color: var(--text-primary); }
    .bg-white { background: #fff; }
    .border-bottom { border-bottom: 1px solid var(--border); }
    .border-top { border-top: 1px solid var(--border); }
    .p-0 { padding: 0; }
    .py-3 { padding-top: 12px; padding-bottom: 12px; }
    .px-3 { padding-left: 12px; padding-right: 12px; }
    .px-4 { padding-left: 18px; padding-right: 18px; }
    .py-4 { padding-top: 18px; padding-bottom: 18px; }
    .fs-4 { font-size: 20px; }
    .fs-5 { font-size: 18px; }
    .fs-1 { font-size: 36px; }
    .h-100 { height: 100%; }
    .w-100 { width: 100%; }
    .list-unstyled { list-style: none; padding-left: 0; margin: 0; }
    .text-break { word-break: break-word; }
    .rounded-circle { border-radius: 50%; }
    .rounded-pill { border-radius: 999px; }
    .spinner-border { display: inline-block; width: 2rem; height: 2rem; border: .25em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin .75s linear infinite; }
    .spinner-border.spinner-border-sm { width: 1rem; height: 1rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .d-none { display: none !important; }
    .position-relative { position: relative; }
    .position-absolute { position: absolute; }
    .col-md-4 { width: 33.333%; }
    .col-sm-4 { width: 33.333%; }
    .row { display: flex; flex-wrap: wrap; margin: 0 -6px; }
    .row.g-3 > * { padding: 6px; box-sizing: border-box; }
    .row.g-3.mb-4 { margin-bottom: 18px; }
    .col-md-4, .col-sm-4 { flex: 0 0 33.333%; max-width: 33.333%; }
    .nav-tabs { display: flex; list-style: none; padding: 0; margin: 0; }
    .nav-item { position: relative; }
    .nav-link {
      display: flex; align-items: center; padding: 10px 16px;
      font-size: 13px; font-weight: 500; color: var(--text-muted);
      background: transparent; border: none; cursor: pointer;
      border-bottom: 2px solid transparent; margin-bottom: -1px; transition: var(--transition);
    }
    .nav-link:hover { color: var(--text-primary); }
    .nav-link.active { color: var(--primary); border-bottom-color: var(--primary); }
    .table-light thead th { background: var(--gray-50); }
    .table-hover tbody tr:hover td { background: var(--gray-50); }
    .align-middle td { vertical-align: middle; }
    .ps-4 { padding-left: 18px; }
    /* 시안의 입력 규격은 32 하나뿐이라 sm 도 같은 높이로 맞춘다 */
    .form-control-sm { padding: 5px 12px; font-size: 13px; }
    .form-select-sm { padding: 5px 28px 5px 12px; font-size: 13px; }
    /* .btn 을 거치지 않는 독립 정의라 기본 버튼 규격(32 · 13/500)을 여기에도 맞춘다 */
    .btn-outline-secondary {
      background: var(--bg-card); border: 1px solid var(--border); color: var(--text-secondary);
      border-radius: var(--radius); padding: 5px 12px; font-size: 13px; font-weight: 500; line-height: 20px;
      cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: var(--transition);
    }
    .btn-outline-secondary:hover { border-color: var(--primary); color: var(--primary); }
    .btn-outline-primary {
      background: transparent; border: 1px solid var(--primary); color: var(--primary);
      border-radius: var(--radius); padding: 5px 12px; font-size: 13px; font-weight: 500; line-height: 20px;
      cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: var(--transition);
      text-decoration: none;
    }
    .btn-outline-primary:hover { background: var(--primary); color: #fff; }
    .btn-secondary { background: var(--border-light); border: 1px solid var(--border); color: var(--text-secondary); border-radius: var(--radius); padding: 5px 12px; font-size: 13px; font-weight: 500; line-height: 20px; cursor: pointer; }
    .input-group { display: flex; }
    .input-group .form-control { border-radius: var(--radius) 0 0 var(--radius) !important; }
    .input-group .btn-outline-secondary { border-radius: 0 var(--radius) var(--radius) 0; border-left: none; }
    .card-footer.bg-white { background: #fff; }
    @media (max-width: 768px) {
      .col-md-4, .col-sm-4 { flex: 0 0 100%; max-width: 100%; }
    }
  </style>

  {{-- 테마 플래시 방지: localStorage 값을 즉시 적용 --}}
  <script>
    (function() {
      var THEMES = {
        coloplast: ['#28798B','#E9F9FB','#0B5C6E','#72BCCC'],   // Figma DS primary 500/50/600/300
        blue:   ['#4d6b8c','#edf1f7','#3d5570','#9ab3cc'],
        purple: ['#7c3aed','#f5f3ff','#6d28d9','#c4b5fd'],
        green:  ['#16a34a','#f0fdf4','#15803d','#86efac'],
        sky:    ['#0284c7','#f0f9ff','#0369a1','#7dd3fc'],
        orange: ['#d97706','#fffbeb','#b45309','#fcd34d'],
        teal:   ['#0d9488','#f0fdfa','#0f766e','#5eead4'],
        mint:   ['#10b981','#ecfdf5','#059669','#6ee7b7'],
        gray:   ['#64748b','#f8fafc','#475569','#cbd5e1'],
      };
      /* 저장 키에 버전을 둔다.
         구버전 키('ce-admin-theme')에는 과거 기본값 'blue'(스틸)가 이미 저장돼 있어서,
         그대로 읽으면 :root 토큰을 인라인 스타일로 덮어써 브랜드 색이 적용되지 않는다.
         키를 올려 낡은 값을 무시하고, 남아 있던 구버전 키는 정리한다. */
      try { localStorage.removeItem('ce-admin-theme'); } catch (e) {}
      var name = localStorage.getItem('ce-admin-theme-v2') || 'coloplast';
      var t = THEMES[name] || THEMES.coloplast;
      var r = document.documentElement;
      r.style.setProperty('--primary', t[0]);
      r.style.setProperty('--primary-light', t[1]);
      r.style.setProperty('--primary-dark', t[2]);
      r.style.setProperty('--primary-accent', t[3]);
      r.style.setProperty('--menu-active', t[0]);
    })();
  </script>

  {{-- MDI 워크스페이스: iframe(탭) 안에서 열릴 때 사이드바·네비 숨기고 콘텐츠만 --}}
  <script>
    (function () {
      try {
        var p = new URLSearchParams(location.search);
        if (window.self !== window.top || p.get('frame') === '1') {
          document.documentElement.classList.add('is-framed');
        }
      } catch (e) {}
    })();
  </script>
  <style>
    html.is-framed .layout-menu,
    html.is-framed .layout-navbar { display: none !important; }
    html.is-framed .layout-page { margin-left: 0 !important; }
    html.is-framed .content-wrapper { padding-top: 0 !important; }
    /* 탭 안에서는 위 여백을 거의 두지 않는다. 탭줄과 본문 사이 간격은 워크스페이스 셸의
       #wsRoot gap 12 가 이미 만든다(시안 148:5526 container gap 12) — 여기서 14 를
       더하면 탭으로 열었을 때만 26 이 되어 시안보다 벌어진다.
       다만 0 으로 붙여 두니 첫 카드의 그림자와 모서리가 탭줄에 닿아, 10 만 둔다. */
    html.is-framed .page-body { padding-top: 10px; }

    /* 액자 안에서는 문서가 창을 넘지 않는다 — 넘치는 만큼은 본문이 스스로 굴린다.

       예전에는 넘친 채로 두고, 액자를 쥔 바깥 화면이 그만큼 액자를 늘려 맞췄다
       (거래처 관리의 pfFit 이 그랬다). 그런데 안쪽 판이 「짧아도 바닥까지」 채우도록
       바뀌면서, 액자를 늘리면 안쪽도 그만큼 늘어 넘치는 양이 그대로 남았다 —
       서로 쫓느라 화면이 끝없이 떨렸다.

       넘치지 않게 두면 늘릴 까닭도 사라진다. 길면 본문이 굴러가고, 아래가 잘리지도
       않는다.

       키를 창에서부터 아래로 내려 준다. 한 마디라도 빠뜨리면 그 아래는 다시 내용만큼
       커져 버리므로, 액자 바닥까지 이어지는 마디를 모두 적는다. */
    html.is-framed, html.is-framed body { height: 100%; overflow: hidden; }
    html.is-framed .layout-wrapper,
    html.is-framed .layout-container,
    html.is-framed .layout-page,
    html.is-framed .content-wrapper { height: 100%; min-height: 0; }
    html.is-framed .page-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }

    /* 프레임 안에서만 보이는 화면 단추 줄. 비어 있으면 자리도 차지하지 않는다 —
       :empty 로는 공백 때문에 잡히지 않아 자식이 없을 때를 본다. */
    .framed-actions { display: none; }
    html.is-framed .framed-actions:has(> *) {
      display: flex; justify-content: flex-end; align-items: center;
      gap: 8px; flex-wrap: wrap; margin-bottom: 12px;
    }
  </style>
  {{-- wwGrid 자산은 레이아웃이 단 한 번만 싣는다.
       화면마다 각자 싣던 때는 (1) 한 화면이라도 빠뜨리면 그 화면 그리드가 죽고
       (2) 두 번 실리면 GridModal 재선언으로 두 번째 로드가 죽었다.
       소유자를 한 곳으로 두면 두 문제가 함께 사라진다. --}}
  <link rel="stylesheet" href="@assetv('vendor/wwgrid/wwGrid.css')">
  {{-- 날짜 칸(글자 칸 + 달력 단추) --}}
  <link rel="stylesheet" href="@assetv('vendor/ce/date-input.css')">
  @stack('styles')
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     VUEXY LAYOUT WRAPPER
══════════════════════════════════════════════════════════ --}}
<div class="layout-wrapper">
  <div class="layout-container">

    {{-- ════ SIDEBAR ════ --}}
    <aside class="layout-menu" id="layoutMenu">

      {{-- Brand + Collapse toggle --}}
      <div class="app-brand">
        <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:8px;text-decoration:none;min-width:0;">
          <img class="app-brand-logo" src="{{ \App\Support\Asset::dataUri('vendor/ds-icons/brand-mark.png') }}" alt="" width="28" height="28">
          <span class="app-brand-names">
            <span class="app-brand-text">@dsicon('brand-wordmark', '')<span class="visually-hidden">CE Admin</span></span>
            <span class="app-brand-sub">Coloplast Korea</span>
          </span>
        </a>
        <button class="menu-collapse-btn" id="menuCollapseBtn" onclick="toggleCollapse()" title="메뉴 접기/펼치기">
          <span class="ic-expanded">@dsicon('chevron-double-right')</span>
          <span class="ic-collapsed">@dsicon('chevron-double-right')</span>
        </button>
      </div>

      {{-- Menu --}}
      <div class="menu-inner">

        @php
          // 권한 그룹으로 필터링 — 볼 수 있는 페이지를 한 번만 계산해 재사용한다.
          // $vis(...키) : 넘긴 페이지 중 하나라도 볼 수 있으면 true (그룹 헤더 판정에도 씀)
          $vp  = perm_pages();
          $vis = fn (...$keys) => count(array_intersect($keys, $vp)) > 0;
        @endphp

        {{-- ══ 메인 ══ --}}
        @if($vis('dashboard'))
        <div class="menu-group" data-menu-group="main">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>메인</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        <div class="menu-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="home-04" href="{{ route('dashboard') }}" data-title="대시보드">
            @dsicon('home-04', 'ds-icon menu-icon')<span>대시보드</span>
          </a>
        </div>
        </div></div>
        @endif

        {{-- ══ 환자 · 처방 ══ --}}
        @if($vis('patients', 'prescription-upload', 'prescriptions'))
        <div class="menu-group" data-menu-group="patient">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>환자ㆍ처방</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('patients'))
        <div class="menu-item {{ request()->routeIs('patients*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="id-verified" href="{{ route('patients.index') }}" data-title="거래처 관리">
            @dsicon('id-verified', 'ds-icon menu-icon')<span>거래처 관리</span>
          </a>
        </div>
        @endif
        @if($vis('prescription-upload'))
        <div class="menu-item {{ request()->routeIs('prescriptions.upload') ? 'active' : '' }}">
          <a class="menu-link" data-icon="file-down-02" href="{{ route('prescriptions.upload') }}" data-title="처방자료 업로드">
            @dsicon('file-down-02', 'ds-icon menu-icon')<span>처방자료 업로드</span>
          </a>
        </div>
        @endif
        @if($vis('prescriptions'))
        {{-- 1차 요청 CR-MNU-01·02 — 「처방전 관리」는 이름이 「주문」으로 바뀌어
             주문ㆍ재구매 그룹으로 옮겨 갔다. 아래 「처방전 목록」만 이 그룹에 남는다. --}}
        <div class="menu-item {{ request()->routeIs('prescriptions.index') ? 'active' : '' }}">
          <a class="menu-link" data-icon="file-02" href="{{ route('prescriptions.index') }}" data-title="처방전 목록">
            @dsicon('file-02', 'ds-icon menu-icon')
            <span>처방전 목록</span>
            {{-- 손이 가야 하는 것 — 아직 검수가 끝나지 않은 처방전 --}}
            @php $pendingCount = \App\Models\Prescription::whereIn('status',['review_needed','review_requested','ocr_done'])->count(); @endphp
            @if($pendingCount > 0)
              <span class="menu-badge">{{ $pendingCount }}</span>
            @endif
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 주문 · 재구매 ══ --}}
        @if($vis('orders', 'repurchase', 'shop-orders', 'prescriptions', 'sample-orders'))
        <div class="menu-group" data-menu-group="order">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>주문ㆍ재구매</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        {{-- 새 상담·처방을 시작하는 자리. 빈 초안을 하나 잡아 검수 화면으로 보낸다.
             한때 메뉴에서 빼고 처방전 목록에서만 들어가게 했더니, 목록에 없는 것을
             새로 적을 길이 사라졌다 — 처방전을 고르는 일과 새로 만드는 일은 다르다. --}}
        @if($vis('prescriptions'))
        @perm('prescriptions', 'create')
        <div class="menu-item {{ request()->routeIs('prescriptions.create') || request()->routeIs('prescriptions.show') ? 'active' : '' }}">
          <a class="menu-link" data-icon="file-edit-02" href="{{ route('prescriptions.create') }}" data-title="주문 등록">
            @dsicon('file-edit-02', 'ds-icon menu-icon')<span>주문 등록</span>
          </a>
        </div>
        @endperm
        @endif
        {{-- 1차 요청 10쪽이 적은 차례 그대로다 —
             주문(주문 등록) / 교환·반품·취소 / CE샘플판매주문 / 주문 관리 / 주문 현황.
             「주문 관리」는 orders.index 다(시안 148:5526 의 빵부스러기가 이 화면을 그렇게 적는다).
             한때 「주문현황」이라 부르며 주문 등록 바로 뒤에 두었는데, 요청서 차례로 되돌리면서
             이름도 요청서대로 「주문 관리」로 돌린다 — 그래야 다섯째 「주문 현황」과 갈린다.
             「주문 현황」(요청 29·30쪽 현황 Dashboard)은 아직 화면이 없어 자리를 만들지 않았다.
             재구매 관리·CE샵 주문은 요청 목록에 없어 뒤에 그대로 남긴다. --}}
        {{-- 1차 요청 CR-MNU-04 --}}
        @if($vis('order-returns'))
        <div class="menu-item {{ request()->routeIs('order-returns*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="add-package" href="{{ route('order-returns.index') }}" data-title="교환/반품/취소">
            @dsicon('add-package', 'ds-icon menu-icon')
            <span>교환/반품/취소</span>
            @php
              try {
                $rtnOpen = \App\Models\OrderReturn::whereNotIn('status', ['done','refunded','cancelled'])->count();
              } catch (\Throwable $e) { $rtnOpen = 0; }
            @endphp
            @if($rtnOpen > 0)<span class="menu-badge blue">{{ $rtnOpen }}</span>@endif
          </a>
        </div>
        @endif
        {{-- CE 샘플주문 — 처방 없이 나가는 물건이라 주문과 따로 둔다 --}}
        @if($vis('sample-orders'))
        <div class="menu-item {{ request()->routeIs('sample-orders*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="add-package" href="{{ route('sample-orders.index') }}" data-title="CE 샘플주문">
            @dsicon('add-package', 'ds-icon menu-icon')<span>CE 샘플주문</span>
          </a>
        </div>
        @endif
        {{-- 주문이 지금 어디까지 왔는지 보는 자리. 요청서가 「주문 관리」로 적었다. --}}
        @if($vis('orders'))
        <div class="menu-item {{ request()->routeIs('orders*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="handle-with-care" href="{{ route('orders.index') }}" data-title="주문 관리">
            @dsicon('handle-with-care', 'ds-icon menu-icon')
            <span>주문 관리</span>
            @php $orderCount = \App\Models\Order::where('status','pending')->count(); @endphp
            @if($orderCount > 0)
              <span class="menu-badge blue">{{ $orderCount }}</span>
            @endif
          </a>
        </div>
        @endif
        @if($vis('repurchase'))
        <div class="menu-item {{ request()->routeIs('repurchase*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="add-package" href="{{ route('repurchase.index') }}" data-title="재구매 관리">
            @dsicon('add-package', 'ds-icon menu-icon')
            <span>재구매 관리</span>
            @php
              try {
                $repurchaseToday = \App\Models\Prescription::whereNotNull('repurchase_date')
                  ->whereDate('repurchase_date', now()->toDateString())->count();
              } catch(\Throwable $e) { $repurchaseToday = 0; }
            @endphp
            @if($repurchaseToday > 0)
              <span class="menu-badge blue">{{ $repurchaseToday }}</span>
            @endif
          </a>
        </div>
        @endif
        @if(config('services.ce_shop.api_enabled') && $vis('shop-orders'))
        <div class="menu-item {{ request()->routeIs('shop-orders*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('shop-orders.index') }}" data-title="CE샵 주문">
            <i class="menu-icon bx bx-store-alt"></i>
            <span>CE샵 주문</span>
            <span class="menu-badge" id="shopOrderBadge" style="background:var(--primary);color:#fff;display:none;"></span>
          </a>
        </div>
        @endif
        {{-- CE샵 모니터링 메뉴 비활성화 --}}
        {{-- <div class="menu-item {{ request()->routeIs('shop-monitoring*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('shop-monitoring.index') }}" data-title="CE샵 모니터링">
            <i class="menu-icon bx bx-radar"></i>
            <span>CE샵 모니터링</span>
          </a>
        </div> --}}
        </div></div>
        @endif

        {{-- ══ 청구 · 회계 ══ --}}
        @if($vis('nhis', 'invoice', 'settlement', 'taxinvoice', 'cashbill'))
        <div class="menu-group" data-menu-group="billing">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>청구ㆍ회계</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('settlement'))
        <div class="menu-item {{ request()->routeIs('settlement*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="calculator" href="{{ route('settlement.index') }}" data-title="정산/회계">
            @dsicon('calculator', 'ds-icon menu-icon')
            <span>정산/회계</span>
            @php $unpaidCount = \App\Models\TossPayment::where('status','WAITING')->count(); @endphp
            @if($unpaidCount > 0)
              <span class="menu-badge">{{ $unpaidCount }}</span>
            @endif
          </a>
        </div>
        @endif
        @if($vis('nhis'))
        <div class="menu-item {{ request()->routeIs('nhis*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="coin-hand" href="{{ route('nhis.index') }}" data-title="청구 관리">
            @dsicon('coin-hand', 'ds-icon menu-icon')
            <span>청구 관리</span>
            @php $nhisCount = \App\Models\Order::where('nhis_claim_status','pending')->whereIn('status',['delivered','shipping','confirmed'])->count(); @endphp
            @if($nhisCount > 0)
              <span class="menu-badge">{{ $nhisCount }}</span>
            @endif
          </a>
        </div>
        @endif
        {{-- 「청구처 정보」는 메뉴에 두지 않는다 — 마스터 관리의 「청구처」 탭으로 들였다.
             병원ㆍ기관과 마찬가지로 「어디에 연락하는가」를 적어 두는 자리라,
             찾으러 갈 곳이 둘일 까닭이 없다. --}}
        @if($vis('invoice'))
        <div class="menu-item {{ request()->routeIs('invoice*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="receipt" href="{{ route('invoice.index') }}" data-title="계산서 발행">
            @dsicon('receipt', 'ds-icon menu-icon')
            <span>계산서 발행</span>
            @php
              try {
                $invoiceCount = \Illuminate\Support\Facades\Schema::hasColumn('orders','tax_invoice_status')
                  ? \App\Models\Order::where('status','delivered')
                      ->where(function($q){ $q->where('tax_invoice_status','not_issued')->orWhere('cash_receipt_status','not_issued'); })
                      ->count()
                  : 0;
              } catch(\Throwable $e) { $invoiceCount = 0; }
            @endphp
            @if($invoiceCount > 0)
              <span class="menu-badge blue">{{ $invoiceCount }}</span>
            @endif
          </a>
        </div>
        @endif
        {{-- 전자세금계산서는 메뉴에 두지 않는다. 발행과 취소는 「계산서 발행」 화면에서
             주문을 보며 하고, 이 화면은 팝빌 목록을 그대로 비추던 자리였다.
             화면과 경로는 남아 있어 주소로는 열린다. --}}
        @if($vis('cashbill'))
        <div class="menu-item {{ request()->routeIs('cashbill*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="cash" href="{{ route('cashbill.index') }}" data-title="현금영수증">
            @dsicon('cash', 'ds-icon menu-icon')
            <span>현금영수증</span>
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 서류 · 동의 ══ --}}
        @if($vis('documents', 'prescription-consents', 'privacy-consents'))
        <div class="menu-group" data-menu-group="docs">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>서류ㆍ동의</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('documents'))
        <div class="menu-item {{ request()->routeIs('documents*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="briefcase" href="{{ route('documents.index') }}" data-title="서류 관리">
            @dsicon('briefcase', 'ds-icon menu-icon')
            <span>서류 관리</span>
          </a>
        </div>
        @endif
        @if($vis('prescription-consents'))
        <div class="menu-item {{ request()->routeIs('prescription-consents*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="curricullum-vitae" href="{{ route('prescription-consents.index') }}" data-title="위임장 서명">
            @dsicon('curricullum-vitae', 'ds-icon menu-icon')
            <span>위임장 서명</span>
          </a>
        </div>
        @endif
        @if($vis('privacy-consents'))
        <div class="menu-item {{ request()->routeIs('privacy-consents*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="check-user" href="{{ route('privacy-consents.index') }}" data-title="개인정보동의">
            @dsicon('check-user', 'ds-icon menu-icon')
            <span>개인정보동의</span>
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 발송 · 내역 ══ --}}
        @if($vis('fax', 'messages', 'dispatch'))
        <div class="menu-group" data-menu-group="dispatch">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>발송ㆍ내역</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('fax'))
        <div class="menu-item {{ request()->routeIs('fax*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="printer" href="{{ route('fax.index') }}" data-title="공단 팩스 발송">
            @dsicon('printer', 'ds-icon menu-icon')
            <span>공단 팩스 발송</span>
          </a>
        </div>
        @endif
        @if($vis('messages'))
        <div class="menu-item {{ request()->routeIs('messages*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="message-typing" href="{{ route('messages.index') }}" data-title="메시지 관리">
            @dsicon('message-typing', 'ds-icon menu-icon')
            <span>메시지 관리</span>
          </a>
        </div>
        @endif
        @if($vis('dispatch'))
        <div class="menu-item {{ request()->routeIs('dispatch*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="send-money" href="{{ route('dispatch.index') }}" data-title="발송/발행 내역">
            @dsicon('send-money', 'ds-icon menu-icon')
            <span>발송/발행 내역</span>
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 지원 ══ --}}
        @if($vis('institutional-notices', 'notices', 'inquiries', 'service-requests'))
        <div class="menu-group" data-menu-group="support">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>지원</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('institutional-notices'))
        <div class="menu-item {{ request()->routeIs('institutional-notices*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="police-station" href="{{ route('institutional-notices.index') }}" data-title="기관 공지사항">
            @dsicon('police-station', 'ds-icon menu-icon')
            <span>기관 공지사항</span>
          </a>
        </div>
        @endif
        @if($vis('notices'))
        <div class="menu-item {{ request()->routeIs('notices*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="notification-calendar" href="{{ route('notices.index') }}" data-title="공지사항">
            @dsicon('notification-calendar', 'ds-icon menu-icon')
            <span>공지사항</span>
            <span class="menu-badge blue" id="noticNavBadge" style="display:none;"></span>
          </a>
        </div>
        @endif
        @if($vis('inquiries'))
        <div class="menu-item {{ request()->routeIs('inquiries*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="bubble-chat-edit" href="{{ route('inquiries.index') }}" data-title="환자 문의">
            @dsicon('bubble-chat-edit', 'ds-icon menu-icon')
            <span>환자 문의</span>
            @if(Auth::user()->role === 'admin')
              @php
                try { $inquiryPending = \App\Models\Inquiry::where('status', 'pending')->count(); }
                catch(\Throwable $e) { $inquiryPending = 0; }
              @endphp
              @if($inquiryPending > 0)
                <span class="menu-badge">{{ $inquiryPending }}</span>
              @endif
            @endif
          </a>
        </div>
        @endif
        @if($vis('service-requests'))
        <div class="menu-item {{ request()->routeIs('sr.*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="dialogue" href="{{ route('sr.index') }}" data-title="SR 관리">
            @dsicon('dialogue', 'ds-icon menu-icon')
            <span>SR 관리</span>
            @php
              try { $srOpen = \App\Models\ServiceRequest::whereIn('status', ['open','in_progress'])->count(); }
              catch(\Throwable $e) { $srOpen = 0; }
            @endphp
            @if($srOpen > 0)
              <span class="menu-badge">{{ $srOpen }}</span>
            @endif
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 설정 ══ --}}
        @if($vis('admin-users', 'permission-groups', 'masters', 'common-codes', 'delegation-settings'))
        <div class="menu-group" data-menu-group="settings">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>설정</span><span class="menu-group-badge"></span>@dsicon('chevron-group', 'ds-icon menu-caret')
        </button>
        <div class="menu-group-items">
        @if($vis('admin-users'))
        <div class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="user-shield" href="{{ route('admin.users.index') }}" data-title="관리자 관리">
            @dsicon('user-shield', 'ds-icon menu-icon')<span>관리자 관리</span>
          </a>
        </div>
        @endif
        @if($vis('permission-groups'))
        <div class="menu-item {{ request()->routeIs('permission-groups*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="lock-group" href="{{ route('permission-groups.index') }}" data-title="권한 그룹">
            @dsicon('lock-group', 'ds-icon menu-icon')<span>권한 그룹</span>
          </a>
        </div>
        @endif
        @if($vis('masters'))
        <div class="menu-item {{ request()->routeIs('masters*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="add-package" href="{{ route('masters.index') }}" data-title="마스터 관리">
            @dsicon('add-package', 'ds-icon menu-icon')
            <span>마스터 관리</span>
          </a>
        </div>
        @endif
        @if($vis('common-codes'))
        <div class="menu-item {{ request()->routeIs('common-codes*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="wrench" href="{{ route('common-codes.index') }}" data-title="환경 설정">
            @dsicon('wrench', 'ds-icon menu-icon')
            <span>환경 설정</span>
          </a>
        </div>
        @endif
        @if($vis('delegation-settings'))
        <div class="menu-item {{ request()->routeIs('delegation-settings*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="curricullum-vitae" href="{{ route('delegation-settings.edit') }}" data-title="위임장 설정">
            @dsicon('curricullum-vitae', 'ds-icon menu-icon')
            <span>위임장 설정</span>
          </a>
        </div>
        @endif
        {{-- OCR 설정도 메뉴에 두지 않는다 — 같은 까닭이다.
             화면과 경로는 남아 있어 주소로는 열린다(/settings/ocr). --}}
        {{-- 본인확인 설정은 메뉴에 두지 않는다. 한 번 맞춰 두면 다시 열 일이 드물고,
             설정 묶음이 길어질수록 매일 쓰는 것이 아래로 밀린다.
             화면과 경로는 남아 있어 주소로는 열린다(/settings/nice). --}}
        @if($vis('service-settings'))
        <div class="menu-item {{ request()->routeIs('service-settings*') ? 'active' : '' }}">
          <a class="menu-link" data-icon="wrench" href="{{ route('service-settings.index') }}" data-title="서비스 연동 설정">
            @dsicon('wrench', 'ds-icon menu-icon')
            <span>서비스 연동 설정</span>
          </a>
        </div>
        @endif

        {{-- 사용자 로그 메뉴 비활성화 --}}
        {{-- <div class="menu-item {{ request()->routeIs('user-logs*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('user-logs.index') }}" data-title="사용자 로그">
            <i class="menu-icon bx bx-list-check"></i><span>사용자 로그</span>
          </a>
        </div> --}}
        </div></div>
        @endif
      </div>{{-- /menu-inner --}}

      {{-- Figma 사이드바의 자식은 브랜드 행과 메뉴 패널 둘뿐이다.
           사용자 정보와 로그아웃은 헤더 우측(사용자 칩·logout-02)으로 옮겼으므로
           여기에 두면 같은 기능이 두 곳에 생긴다. --}}
      @php /* 사이드바 하단 사용자 블록 제거 — Figma 174:1510 에 해당 노드 없음 */ @endphp
      @if(false)
      <div class="menu-footer">
        <div class="menu-user">
          <div class="menu-user-avatar">{{ mb_substr(Auth::user()->name, 0, 1) }}</div>
          <div class="menu-user-info flex-1" style="min-width:0;">
            <div class="menu-user-name text-truncate">{{ Auth::user()->name }}</div>
            <div class="menu-user-role">{{ Auth::user()->role === 'admin' ? 'CE 관리자' : '매니저' }}</div>
          </div>
          <form method="POST" action="{{ route('logout') }}" class="ms-auto menu-user-info">
            @csrf
            <button type="submit" class="btn-icon" title="로그아웃" style="width:32px;height:32px;font-size:15px;color:var(--gray-600);">
              <i class="bx bx-log-out-circle"></i>
            </button>
          </form>
        </div>
      </div>
      @endif
    </aside>{{-- /layout-menu --}}

    {{-- ════ MAIN ════ --}}
    <div class="layout-page" id="layoutPage">

      {{-- Navbar --}}
      <header class="layout-navbar" id="layoutNavbar">
        {{-- Mobile menu toggle --}}
        <button class="btn-icon d-xl-none me-2" id="menuToggleBtn" onclick="toggleMenu()" style="font-size:22px;">
          <i class="bx bx-menu"></i>
        </button>

        <div class="navbar-brand-area">
          <div class="page-title">@yield('page-title', '대시보드')</div>
          <div class="page-breadcrumb">
            {{-- 시안(174:955)의 빵부스러기는 아이콘 없이 '홈' 이 제목과 같은 x336 에서
                 시작한다. 집 아이콘은 낱말이 아니라 장식이라 걷어냈다.

                 마디 사이는 시안이 8 인데(248:4008), 화면이 '홈 - 서류 관리' 라는
                 한 덩어리로 넘기면 하이픈 양옆이 일반 공백이라 3.0px 밖에 안 된다.
                 평문으로 온 것만 ' - ' 에서 갈라 마디로 세운다.
                 태그가 섞여 온 것(링크·화면이 직접 짠 마디)은 그 화면의 마크업이라
                 건드리지 않고 그대로 흘린다. --}}
            @php $__bc = trim($__env->yieldContent('breadcrumb', '홈')); @endphp
            @if (strip_tags($__bc) === $__bc && str_contains($__bc, ' - '))
              <span class="bc-trail">
                @foreach (explode(' - ', $__bc) as $__i => $__seg)
                  @if ($__i)<span>-</span>@endif
                  <span>{{ $__seg }}</span>
                @endforeach
              </span>
            @else
              {!! $__bc !!}
            @endif
          </div>
        </div>

        <div class="navbar-actions">
          {{-- Notifications --}}
          <button class="btn-icon" title="알림">
            @dsicon('bell-02')
            <span class="notif-dot"></span>
          </button>
          {{-- Chat --}}
          <button class="btn-icon" id="chatToggleBtn" title="채팅" onclick="ChatPanel.toggle()">
            @dsicon('message-typing')
            <span class="notif-dot" id="chatUnreadDot" style="display:none;"></span>
          </button>
          {{-- Help --}}
          <button class="btn-icon" id="helpToggleBtn" title="도움말" onclick="HelpPanel.toggle()">
            @dsicon('help-circle-contained')
          </button>
          {{-- SR 관리 --}}
          @perm('service-requests')
          <button class="btn-icon" id="srToggleBtn" title="SR 관리" onclick="SrPanel.toggle()">
            @dsicon('wrench')
            <span class="btn-icon-badge" id="srBadge" style="display:none;"></span>
          </button>
          @endperm
          {{-- Theme Picker --}}
          <div class="theme-picker-wrap">
            <button class="btn-icon" id="themePickerBtn" title="테마 컬러" onclick="ThemePicker.togglePanel()">
              @dsicon('palette')
            </button>
            <div class="theme-panel" id="themePanel">
              <div class="theme-panel-title">테마 컬러</div>
              <div class="theme-swatches">
                <div class="theme-swatch" data-theme="coloplast" style="background:#28798B" title="콜로플라스트" onclick="ThemePicker.apply('coloplast')"></div>
                <div class="theme-swatch" data-theme="blue"   style="background:#4d6b8c" title="스틸"   onclick="ThemePicker.apply('blue')"></div>
                <div class="theme-swatch" data-theme="purple" style="background:#7c3aed" title="보라"   onclick="ThemePicker.apply('purple')"></div>
                <div class="theme-swatch" data-theme="green"  style="background:#16a34a" title="초록"   onclick="ThemePicker.apply('green')"></div>
                <div class="theme-swatch" data-theme="sky"    style="background:#0284c7" title="하늘"   onclick="ThemePicker.apply('sky')"></div>
                <div class="theme-swatch" data-theme="orange" style="background:#d97706" title="주황"   onclick="ThemePicker.apply('orange')"></div>
                <div class="theme-swatch" data-theme="teal"   style="background:#0d9488" title="틸"     onclick="ThemePicker.apply('teal')"></div>
                <div class="theme-swatch" data-theme="mint"   style="background:#10b981" title="민트"   onclick="ThemePicker.apply('mint')"></div>
                <div class="theme-swatch" data-theme="gray"   style="background:#64748b" title="그레이" onclick="ThemePicker.apply('gray')"></div>
              </div>
              <div class="theme-label" id="themeLabel">콜로플라스트</div>
            </div>
          </div>
          @yield('header-actions')
          {{-- 로그아웃 (Figma header 우측 6번째 아이콘) --}}
          <form method="POST" action="{{ route('logout') }}" style="display:contents;">
            @csrf
            <button type="submit" class="btn-icon" title="로그아웃">@dsicon('logout-02')</button>
          </form>
          {{-- 사용자 칩 (Figma) --}}
          <div class="nav-user-chip" title="{{ Auth::user()->name }}">
            @dsicon('user')
            <span class="nav-user-chip-name">{{ Auth::user()->name }}</span>
          </div>
        </div>
      </header>

      {{-- Content Wrapper --}}
      <div class="content-wrapper">

        {{-- Flash Messages --}}
        @if(session('success') || session('error'))
        <div style="padding: 12px 24px 0; min-width: 0;">
          @if(session('success'))
            <div class="alert alert-success"><i class="bx bx-check-circle me-1"></i> {{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger"><i class="bx bx-x-circle me-1"></i> {{ session('error') }}</div>
          @endif
        </div>
        @endif

        {{-- Page Content --}}
        <main class="page-body">
          {{-- 화면 단추를 프레임 안에서도 쓸 수 있게 한다.
               header-actions 는 상단 네비바에 붙는데, 탭(iframe) 안에서는 그 네비바를
               숨긴다 — 바깥 워크스페이스의 것이 따로 있기 때문이다. 그러면 「신규 접수」
               같은 단추가 통째로 사라져, 메뉴로 들어온 사람은 등록할 길이 없었다.
               프레임일 때만 화면 위에 같은 단추를 한 벌 더 둔다. --}}
          <div class="framed-actions">@yield('header-actions')</div>
          @yield('content')
        </main>

        {{-- Footer --}}
        <div class="content-backdrop"></div>
      </div>{{-- /content-wrapper --}}
    </div>{{-- /layout-page --}}

  </div>{{-- /layout-container --}}

  {{-- Mobile overlay --}}
  <div class="layout-overlay" id="layoutOverlay" onclick="toggleMenu()"></div>
</div>{{-- /layout-wrapper --}}

{{-- Toast Container --}}
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:9999;"></div>

{{-- Bootstrap 5 JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

{{-- ══ Sidebar Toggle (Mobile) ══ --}}
<script>
function toggleMenu() {
  const menu    = document.getElementById('layoutMenu');
  const overlay = document.getElementById('layoutOverlay');
  menu.classList.toggle('open');
  overlay.classList.toggle('show');
}

/* ── Sidebar Collapse (Desktop) ── */
(function() {
  const STORAGE_KEY = 'ceAdminMenuCollapsed';
  const menu = document.getElementById('layoutMenu');
  const page = document.getElementById('layoutPage');

  /* 여백은 CSS 가 --sidebar-w / --sidebar-collapsed-w 로 정한다.
     예전에는 여기서 인라인 style.marginLeft 로 덮어썼는데, 그 값이 옛 사이드바 폭(260px)
     이라 폭을 바꾼 뒤로는 펼칠 때마다 본문이 사이드바 밑으로 들어갔다.
     클래스만 토글하고 치수는 한 곳(토큰)에서만 관리한다. */

  // 저장된 상태 복원
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    menu.classList.add('collapsed');
    document.body.classList.add('menu-collapsed');
  }

  window.toggleCollapse = function() {
    const collapsed = menu.classList.toggle('collapsed');
    document.body.classList.toggle('menu-collapsed', collapsed);
    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
  };
})();
document.addEventListener('click', (e) => {
  const menu = document.getElementById('layoutMenu');
  const btn  = document.getElementById('menuToggleBtn');
  if (menu && !menu.contains(e.target) && btn && !btn.contains(e.target)) {
    menu.classList.remove('open');
    document.getElementById('layoutOverlay')?.classList.remove('show');
  }
});
</script>


@php
  $_ceToured    = auth()->user()?->toured_pages ?? [];
  $_tourPageKey = \Illuminate\Support\Facades\Route::currentRouteName() ?? request()->path();
@endphp
<script type="application/json" id="ce-server-data">{"toured":{!! json_encode($_ceToured) !!},"pageKey":{!! json_encode($_tourPageKey) !!}}</script>
<script>
  // 앱 기본 URL (서브디렉토리 배포 대응)
  const BASE_URL   = '{{ rtrim(url('/'), '/') }}';
  // CSRF 토큰 전역 설정
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
  // 투어 완료 페이지 목록 (DB, 사용자별)
  (function () {
    const d = JSON.parse(document.getElementById('ce-server-data').textContent);
    window.CE_TOURED     = d.toured   || [];
    window.TOUR_PAGE_KEY = d.pageKey  || '';
  })();

  /* ── 워크스페이스 탭 열기 ────────────────────────────────
     프레임 안(워크스페이스 탭)에서는 부모에게 새 탭 요청을 보내 현재 탭이 전환되지 않게 하고,
     워크스페이스 밖(단독 페이지)에서는 브라우저 새 탭으로 대체한다. */
  window.ceOpenTab = function (url, title, icon) {
    const framed = window.self !== window.top;
    if (framed) {
      try {
        window.parent.postMessage(
          { source: 'ce-workspace', action: 'open-tab', url: url, title: title, icon: icon },
          window.location.origin
        );
        return true;
      } catch (e) { /* 부모 접근 실패 시 아래 폴백 */ }
    }
    window.open(url, '_blank', 'noopener');
    return false;
  };

  /* 화면 안에 다른 화면을 액자로 들여둔 자리가 있다(환자 전체 상세·접수 상세).
     그 액자 안에서 「새 탭으로」를 부탁하면 바로 위 화면에게 말이 오는데, 탭을 만드는
     것은 그보다 위의 워크스페이스다. 중간에서 끊기지 않게 그대로 올려 보낸다. */
  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin) return;
    if (e.data?.source !== 'ce-workspace' || e.data?.action !== 'open-tab') return;
    if (window.self === window.top) return;          // 우리가 워크스페이스면 우리가 처리한다
    if (e.source === window.parent) return;          // 위에서 내려온 말은 되돌리지 않는다

    try { window.parent.postMessage(e.data, window.location.origin); } catch (err) {}
  });

  /* ── 공통 칸 — 위드웍스 판매주문 현황의 차례를 따른다 ──────────────
     저쪽 화면을 보다 우리 목록으로 넘어와도 눈이 다시 배우지 않아야 한다는
     요청이다(2026-08-31). 저쪽 예순 칸 가운데 창고ㆍERP 고유 스물하나(etc SoNo ㆍ
     출고창고 ㆍ납품창고 ㆍ확정판매금액 …)는 우리가 만들 수도 받을 수도 없어 세우지
     않는다 — 그것은 위드웍스 화면에서 본다.

     저쪽이 부르지 않는 우리 칸(동의ㆍ발행ㆍ상병ㆍ수량 따위)은 걷지 않고 뒤에 잇는다 —
     지난 요청으로 세운 것들이라, 차례를 맞추자고 지우면 그때 한 일이 없던 일이 된다.

     쓰는 법 — columns: [ …번호ㆍ이름, ...ceMoneyCols(), 날짜, …화면 고유, ...ceWwCols() ] */
  window.ceWwCols = function () {
    return [
      // ── 위드웍스 차례 ──────────────────────────────────
      { header: '요양병원 코드',  name: 'rx_hosp_code', width: 110 },
      { header: '병원명',         name: 'rx_hospital',  width: 150, sortable: true },
      { header: '판매상태',       name: 'ww_sale_status', width: 110, align: 'center', sortable: true },
      { header: '출고상태',       name: 'ww_ship_status', width: 110, align: 'center', sortable: true },
      { header: '입고 상태',      name: 'ww_rcpt',      width: 90,  align: 'center' },
      { header: '배송주소명',     name: 'ww_recipient', width: 100 },
      { header: '배송요청일자',   name: 'ww_due',       width: 110, align: 'center', sortable: true },
      { header: '참조 번호',      name: 'ww_ref_no',    width: 150, sortable: true },
      { header: '청구 전략 코드', name: 'ww_bs_code',   width: 120 },
      { header: '청구전략명',     name: 'ww_bs_name',   width: 160, sortable: true },
      { header: '송장 번호',      name: 'ww_ship_no',   width: 130 },
      { header: '비고',           name: 'ww_remark',    width: 200 },
      { header: '운송장',         name: 'ww_tracking',  width: 130 },
      { header: '신구매/재구매',  name: 'rx_purchase',  width: 110, align: 'center', sortable: true },
      { header: '자격',           name: 'rx_benefit',   width: 100, align: 'center', sortable: true },
      { header: '처방전 발행일',  name: 'rx_issued',    width: 120, align: 'center', sortable: true },
      { header: '처방전 사용기간', name: 'rx_period',   width: 120, align: 'right' },
      { header: '일일 도뇨 횟수', name: 'rx_cath_freq', width: 120, align: 'center', sortable: true },
      { header: '담당 의사명',    name: 'rx_doctor',    width: 100, sortable: true },
      { header: '상담 진행',      name: 'ww_counsel',   width: 90,  align: 'center', sortable: true },
      { header: '구입일',         name: 'rx_buy_date',  width: 100, align: 'center', sortable: true },
      { header: '다음 재구매 가능일', name: 'rx_next_repur', width: 130, align: 'center', sortable: true },
      { header: '보호자명',       name: 'ww_guardian',  width: 100 },
      { header: '청구처',         name: 'claim_agency', width: 130, sortable: true },
      { header: '신환master 등록일', name: 'ww_new_master', width: 130, align: 'center', sortable: true },
      { header: '소득공제/지출증빙', name: 'ww_deduction', width: 120, align: 'center' },
      { header: '현금영수증번호', name: 'ww_cash_no',   width: 130 },
      { header: '등록 일시',      name: 'ww_created_at', width: 130, align: 'center', sortable: true },
      { header: '수정 일시',      name: 'ww_updated_at', width: 130, align: 'center', sortable: true },

      /* ── 저쪽이 부르지 않는 우리 칸 ─────────────────────
         동의ㆍ청구ㆍ발행은 우리 절차라 위드웍스 화면에 있을 까닭이 없다. */
      { header: '개인정보동의', name: 'privacy_consent', width: 110, align: 'center', sortable: true },
      { header: '위임동의',     name: 'nhis_consent',    width: 90,  align: 'center', sortable: true },
      { header: '청구 준비',    name: 'claim_ready',     width: 90,  align: 'center', sortable: true },
      { header: '공단 청구',    name: 'nhis_claim',      width: 90,  align: 'center', sortable: true },
      { header: '세금계산서',   name: 'tax_invoice',     width: 100, align: 'center', sortable: true },
      { header: '현금영수증',   name: 'cash_receipt',    width: 100, align: 'center', sortable: true },

      // 병원ㆍ처방 정보 탭의 나머지 — 저쪽 목록에 없는 것들
      { header: '검수 메모',      name: 'rx_memo',      width: 200 },
      { header: '처방 유형',      name: 'rx_acc_type',  width: 100, align: 'center', sortable: true },
      { header: '진단 확인일',    name: 'rx_diag_date', width: 110, align: 'center', sortable: true },
      { header: '상병 구분',      name: 'rx_dz_grade',  width: 90,  align: 'center', sortable: true },
      { header: '상병코드',       name: 'rx_dz_code',   width: 110, sortable: true },
      { header: '상병 명',        name: 'rx_dz_name',   width: 160, sortable: true },
      { header: '요류역학검사일', name: 'rx_uro_date',  width: 120, align: 'center', sortable: true },
      { header: '확인사항',       name: 'rx_uro_find',  width: 220 },
      { header: '1일 처방 개수',  name: 'rx_daily',     width: 110, align: 'right',  sortable: true },
      { header: '총 처방일수',    name: 'rx_days',      width: 100, align: 'right',  sortable: true },
      { header: '총계',           name: 'rx_total',     width: 90,  align: 'right',  sortable: true },
      { header: '처방전종료일',   name: 'rx_end',       width: 120, align: 'center', sortable: true },
      { header: '전문과목',       name: 'rx_specialty', width: 100, sortable: true },
      { header: '의사면허번호',   name: 'rx_license',   width: 110 },
      { header: '사유',           name: 'rx_reason',    width: 200, sortable: true },
      { header: '주문 담당자',    name: 'rx_order_mgr', width: 100, sortable: true },
      { header: 'Five/Six',       name: 'rx_five',      width: 90,  align: 'center', sortable: true },
      { header: 'Five/Six(110days)', name: 'rx_five110', width: 140, align: 'center' },
      { header: '관할 청구처',    name: 'rx_office',    width: 160, sortable: true },
      { header: '결제일',         name: 'rx_pay_date',  width: 100, align: 'center', sortable: true },
      { header: '사용 시작일',    name: 'rx_agree_start', width: 110, align: 'center', sortable: true },
      { header: '급여 종료일',    name: 'rx_agree_end', width: 110, align: 'center', sortable: true },
      { header: '추가정보 등록일', name: 'rx_created',  width: 120, align: 'center', sortable: true },
      { header: '관할 지자체',    name: 'rx_local_gov', width: 140, sortable: true },
      { header: '재구매일',       name: 'rx_repur_date', width: 110, align: 'center', sortable: true },
      { header: '하루 사용 수량', name: 'rx_use_qty',   width: 110, align: 'right',  sortable: true },
      { header: '인마켓 마감일',  name: 'rx_inmarket',  width: 110, align: 'center', sortable: true },
      { header: '마지막 확정 수량', name: 'rx_last_qty', width: 120, align: 'right', sortable: true },
    ];
  };

  /* 정산 — 날짜 칸 바로 앞에 세운다. 화면마다 그 칸의 이름이 다르지만(판매일자ㆍ
     등록일ㆍ접수일ㆍ주문일) 가리키는 것은 같다. 다섯의 차례는 어디서나 이대로다. */
  window.ceMoneyCols = function () {
    const money = (v) => {
      const n = Number(v || 0);
      if (!n) return '';
      const s = document.createElement('span');
      s.textContent = n.toLocaleString('ko-KR');
      return s;
    };

    return [
      { header: '결제수단',     name: 'pay_method',      width: 100, align: 'center', sortable: true },
      { header: '입금확인',     name: 'deposit_at',      width: 100, align: 'center', sortable: true },
      { header: '총 금액',      name: 'total_amount',    width: 100, align: 'right',  sortable: true, renderer: money },
      { header: '본인 부담금',  name: 'copay',           width: 110, align: 'right',  sortable: true, renderer: money },
      { header: '기관 부담금',  name: 'nhis_amount',     width: 110, align: 'right',  sortable: true, renderer: money },
    ];
  };

  // ── 버튼 프로세스 상태 유틸리티 ────────────────────────
  const BtnState = (() => {
    function loading(btn, text = '처리 중...') {
      if (!btn) return;
      btn.dataset.origHtml = btn.innerHTML;
      btn.dataset.loading  = '1';
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> ${text}`;
    }
    function reset(btn) {
      if (!btn) return;
      if (btn.dataset.origHtml !== undefined) btn.innerHTML = btn.dataset.origHtml;
      delete btn.dataset.origHtml;
      delete btn.dataset.loading;
      delete btn.dataset.state;
      btn.disabled = false;
      btn.style.animation = '';
    }
    function success(btn, text = '완료', duration = 1800) {
      if (!btn) return;
      btn.dataset.state = 'success';
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-check" style="font-size:12px;"></i> ${text}`;
      setTimeout(() => reset(btn), duration);
    }
    function error(btn, text = '오류', duration = 2000) {
      if (!btn) return;
      btn.dataset.state = 'error';
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-xmark" style="font-size:12px;"></i> ${text}`;
      setTimeout(() => reset(btn), duration);
    }
    // 폼 submit 버튼 자동 로딩 상태
    document.addEventListener('DOMContentLoaded', () => {
      document.addEventListener('submit', (e) => {
        const submitBtn = e.target.querySelector('[type="submit"]:not([data-no-loading])');
        if (submitBtn) loading(submitBtn);
      }, true);
    });
    return { loading, reset, success, error };
  })();

  // ── Toast 함수 ──────────────────────────────────────────
  function showToast(msg, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    const toast     = document.createElement('div');
    toast.className = `toast ${type}`;

    const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
    toast.innerHTML = `<span style="margin-right:6px;">${icons[type] || ''}</span>${msg}`;

    // 닫기 버튼
    const closeBtn = document.createElement('span');
    closeBtn.innerHTML = ' &times;';
    closeBtn.style.cssText = 'margin-left:10px;cursor:pointer;opacity:.7;font-size:16px;';
    closeBtn.onclick = () => removeToast(toast);
    toast.appendChild(closeBtn);

    container.appendChild(toast);
    setTimeout(() => removeToast(toast), duration);
  }

  function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'opacity .3s, transform .3s';
    setTimeout(() => toast.remove(), 300);
  }

  /* ── 사이드바 메뉴 그룹 펼침/접힘 ──────────────────────────
     헤더를 누르면 그룹이 접히고, 상태는 localStorage 에 저장돼 새로고침·다른
     화면에서도 유지된다. 현재 화면이 속한 그룹은 항상 펼친 상태로 시작한다.
     접힌 그룹에 알림 건수가 있으면 헤더에 합계를 띄워 놓치지 않게 한다. */
  (function () {
    const KEY = 'ce_menu_collapsed_groups';

    function load() {
      try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) { return new Set(); }
    }
    function save(set) {
      try { localStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (e) { /* noop */ }
    }

    // 그룹 내 알림 배지 합계를 헤더에 반영 (접혔을 때만 CSS 로 노출)
    function syncGroupBadge(group) {
      const el = group.querySelector('.menu-group-badge');
      if (!el) return;
      let sum = 0;
      group.querySelectorAll('.menu-group-items .menu-badge').forEach(function (b) {
        if (b.offsetParent === null && b.style.display === 'none') return;   // 비어 있는 동적 배지 제외
        const n = parseInt((b.textContent || '').replace(/\D/g, ''), 10);
        if (!isNaN(n)) sum += n;
      });
      el.textContent = sum > 0 ? String(sum) : '';
      el.style.display = sum > 0 ? '' : 'none';
    }

    window.toggleMenuGroup = function (btn) {
      const group = btn.closest('.menu-group');
      if (!group) return;
      const name = group.dataset.menuGroup;
      const set  = load();
      const collapsed = group.classList.toggle('is-collapsed');
      if (collapsed) set.add(name); else set.delete(name);
      save(set);
      syncGroupBadge(group);
    };

    document.querySelectorAll('.menu-group').forEach(function (group) {
      const hasActive = !!group.querySelector('.menu-item.active');
      group.classList.toggle('has-active', hasActive);

      if (hasActive) {
        // 현재 화면이 있는 그룹은 펼쳐 두고, 저장된 접힘 상태도 해제한다
        const set = load();
        if (set.delete(group.dataset.menuGroup)) save(set);
      } else if (load().has(group.dataset.menuGroup)) {
        group.classList.add('is-collapsed');
      }
      syncGroupBadge(group);
    });

    // 동적으로 갱신되는 배지(공지·CE샵 주문)를 헤더 합계에 반영
    window.refreshMenuGroupBadges = function () {
      document.querySelectorAll('.menu-group').forEach(syncGroupBadge);
    };

    /* 워크스페이스는 탭을 바꿀 때 .menu-item.active 를 JS 로 옮긴다.
       그때 활성 항목이 접힌 그룹에 가려지지 않도록 다시 펼쳐 준다. */
    window.syncMenuGroupsActive = function () {
      document.querySelectorAll('.menu-group').forEach(function (group) {
        const hasActive = !!group.querySelector('.menu-item.active');
        group.classList.toggle('has-active', hasActive);
        if (hasActive) group.classList.remove('is-collapsed');
      });
    };
  })();

  /* ── [data-ce-tab] 링크는 워크스페이스 새 탭으로 ────────────
     상세 화면으로 가는 링크에 속성만 붙이면 현재 탭이 전환되지 않고 새 탭이 열린다.
       <a href="/prescriptions/RX-1" data-ce-tab="주문 - RX-1" data-ce-icon="bx-scan">
     동적으로 만든 마크업(알림 토스트·그리드 셀 등)에도 위임으로 적용된다.
     Ctrl/Cmd/Shift/가운데 클릭은 브라우저 기본 동작(새 창·새 탭)을 그대로 둔다. */
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[data-ce-tab]');
    if (!a) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const href = a.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript')) return;
    e.preventDefault();
    window.ceOpenTab(href, a.dataset.ceTab || a.textContent.trim(), a.dataset.ceIcon || '');
  });

  /* ── 커스텀 알림 / 확인 다이얼로그 ────────────────────────
     브라우저 기본 alert()/confirm() 대신 디자인 시스템 모달을 쓴다.
     confirm 은 동기 반환이 불가하므로 Promise 를 반환한다:
       if (!await ceConfirm('...')) return;
       await ceAlert('...');
     opts: { title, confirmText, cancelText, tone: 'default'|'danger'|'warning' } */
  (function () {
    const TONE = {
      default: { icon: 'fa-circle-question',      color: 'var(--primary)', btn: 'btn-primary' },
      danger:  { icon: 'fa-triangle-exclamation', color: 'var(--danger)',  btn: 'btn-danger'  },
      warning: { icon: 'fa-circle-exclamation',   color: 'var(--warning)', btn: 'btn-primary' },
      info:    { icon: 'fa-circle-info',          color: 'var(--primary)', btn: 'btn-primary' },
    };

    function esc(s) {
      return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // 메시지의 줄바꿈(\n)을 <br> 로 살린다 (기존 alert 문구를 그대로 옮기기 위해)
    function body(msg) { return esc(msg).replace(/\n/g, '<br>'); }

    function open(kind, msg, opts = {}) {
      const tone = TONE[opts.tone] || TONE[kind === 'alert' ? 'info' : 'default'];
      const ov   = document.createElement('div');
      ov.className = 'modal-overlay open';
      ov.style.zIndex = '20000';   // 화면 자체 모달(10000)·팝오버(10100) 위
      ov.innerHTML = `
        <div class="modal-box sm" role="dialog" aria-modal="true">
          <div class="modal-hd">
            <i class="fa-solid ${tone.icon}" style="color:${tone.color};font-size:17px;"></i>
            <span class="modal-title">${esc(opts.title || (kind === 'alert' ? '알림' : '확인'))}</span>
          </div>
          <div class="modal-bd" style="font-size:13px;line-height:1.75;color:var(--text-primary);white-space:normal;">
            ${body(msg)}
          </div>
          <div class="modal-ft">
            ${kind === 'confirm'
              ? `<button type="button" class="btn btn-outline btn-sm" data-ce="cancel">${esc(opts.cancelText || '취소')}</button>`
              : ''}
            <button type="button" class="btn ${tone.btn} btn-sm" data-ce="ok">
              ${esc(opts.confirmText || (kind === 'alert' ? '확인' : '확인'))}
            </button>
          </div>
        </div>`;
      document.body.appendChild(ov);

      const okBtn     = ov.querySelector('[data-ce="ok"]');
      const cancelBtn = ov.querySelector('[data-ce="cancel"]');
      const prevFocus = document.activeElement;

      return new Promise(resolve => {
        let settled = false;
        function done(value) {
          if (settled) return;
          settled = true;
          document.removeEventListener('keydown', onKey, true);
          ov.remove();
          if (prevFocus && typeof prevFocus.focus === 'function') prevFocus.focus();
          resolve(value);
        }
        function onKey(e) {
          if (e.key === 'Escape') { e.preventDefault(); done(kind === 'alert' ? undefined : false); }
          else if (e.key === 'Enter') { e.preventDefault(); done(kind === 'alert' ? undefined : true); }
        }

        okBtn.addEventListener('click', () => done(kind === 'alert' ? undefined : true));
        cancelBtn?.addEventListener('click', () => done(kind === 'alert' ? undefined : false));
        // 배경 클릭: 확인은 취소로, 알림은 닫기로 처리
        ov.addEventListener('click', e => { if (e.target === ov) done(kind === 'alert' ? undefined : false); });
        document.addEventListener('keydown', onKey, true);

        okBtn.focus();
      });
    }

    /** 알림. 닫힐 때까지 기다리려면 await. */
    window.ceAlert = (msg, opts) => open('alert', msg, opts);

    /** 확인. Promise<boolean> — 확인 true / 취소·Esc·배경클릭 false. */
    window.ceConfirm = (msg, opts) => open('confirm', msg, opts);

    /**
     * 입력. Promise<string|null> — 저장하면 입력값, 취소·Esc·배경클릭이면 null.
     * opts: { value, multiline, placeholder, confirmText, cancelText }
     */
    window.cePrompt = function (title, opts = {}) {
      const ov = document.createElement('div');
      ov.className = 'modal-overlay open';
      ov.style.zIndex = '20000';
      const field = opts.multiline
        ? `<textarea class="form-control" data-ce="input" rows="4" style="font-size:13px;resize:vertical;"
                     placeholder="${esc(opts.placeholder || '')}"></textarea>`
        : `<input type="text" class="form-control" data-ce="input" style="font-size:13px;"
                  placeholder="${esc(opts.placeholder || '')}">`;
      ov.innerHTML = `
        <div class="modal-box sm" role="dialog" aria-modal="true">
          <div class="modal-hd">
            <i class="fa-solid fa-pen" style="color:var(--primary);font-size:17px;"></i>
            <span class="modal-title">${esc(title || '입력')}</span>
          </div>
          <div class="modal-bd">${field}</div>
          <div class="modal-ft">
            <button type="button" class="btn btn-outline btn-sm" data-ce="cancel">${esc(opts.cancelText || '취소')}</button>
            <button type="button" class="btn btn-primary btn-sm" data-ce="ok">${esc(opts.confirmText || '확인')}</button>
          </div>
        </div>`;
      document.body.appendChild(ov);

      const input = ov.querySelector('[data-ce="input"]');
      input.value = opts.value ?? '';
      const prevFocus = document.activeElement;

      return new Promise(resolve => {
        let settled = false;
        function done(value) {
          if (settled) return;
          settled = true;
          document.removeEventListener('keydown', onKey, true);
          ov.remove();
          if (prevFocus && typeof prevFocus.focus === 'function') prevFocus.focus();
          resolve(value);
        }
        function onKey(e) {
          if (e.key === 'Escape') { e.preventDefault(); done(null); }
          // 여러 줄 입력에서 Enter 는 줄바꿈이다. 저장은 Ctrl/⌘+Enter.
          else if (e.key === 'Enter' && (!opts.multiline || e.ctrlKey || e.metaKey)) {
            e.preventDefault(); done(input.value);
          }
        }
        ov.querySelector('[data-ce="ok"]').addEventListener('click', () => done(input.value));
        ov.querySelector('[data-ce="cancel"]').addEventListener('click', () => done(null));
        ov.addEventListener('click', e => { if (e.target === ov) done(null); });
        document.addEventListener('keydown', onKey, true);

        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
      });
    };

    /**
     * 인라인 onsubmit 용 헬퍼: onsubmit="return ceConfirmSubmit(this, '메시지')"
     * 항상 false 를 반환해 즉시 제출을 막고, 확인을 누르면 그때 폼을 제출한다.
     */
    window.ceConfirmSubmit = function (form, msg, opts) {
      ceConfirm(msg, opts).then(ok => { if (ok) form.submit(); });
      return false;
    };
  })();

  // ── 공통 AJAX fetch 래퍼 (에러 자동 Toast) ─────────────
  async function apiRequest(url, method = 'POST', data = {}) {
    try {
      const res = await fetch(url.startsWith('/') ? BASE_URL + url : url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN,
          'Accept': 'application/json',
        },
        body: method !== 'GET' ? JSON.stringify(data) : undefined,
      });

      // HTTP 에러 코드 처리
      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        if (res.status === 422 && errData.errors) {
          // 422 Validation: errors 객체 포함하여 반환 (개별 처리 가능)
          const firstMsg = Object.values(errData.errors).flat()[0] || errData.message;
          showToast(firstMsg, 'danger');
          return { success: false, message: firstMsg, errors: errData.errors };
        }
        const errMsg = errData.message || errData.error || `서버 오류 (HTTP ${res.status})`;
        showToast(errMsg, 'danger');
        return { success: false, message: errMsg };
      }

      const json = await res.json();

      // 서버에서 success: false 로 응답 시 자동 Toast
      if (json.success === false && json.message) {
        showToast(json.message, 'danger');
      }

      return json;

    } catch (networkErr) {
      const msg = '네트워크 오류가 발생했습니다. 인터넷 연결을 확인해주세요.';
      showToast(msg, 'danger');
      console.error('apiRequest error:', networkErr);
      return { success: false, message: msg };
    }
  }

  // ── PHP Flash 메시지를 Toast로 자동 표시 ───────────────
  document.addEventListener('DOMContentLoaded', () => {
    @if(session('success'))
      showToast(@json(session('success')), 'success');
    @endif
    @if(session('error'))
      showToast(@json(session('error')), 'danger');
    @endif
    @if(session('warning'))
      showToast(@json(session('warning')), 'warning');
    @endif
    @if(session('info'))
      showToast(@json(session('info')), 'info');
    @endif
    @if($errors->any())
      @foreach($errors->all() as $error)
        showToast(@json($error), 'danger');
      @endforeach
    @endif
  });

  // ── 전역 JS 에러 → Toast ───────────────────────────────
  window.addEventListener('unhandledrejection', (e) => {
    console.error('Unhandled Promise Rejection:', e.reason);
    showToast('처리 중 오류가 발생했습니다.', 'danger');
  });

  // ── Theme Color Picker ─────────────────────────────────
  const ThemePicker = (function() {
    const THEMES = {
      coloplast: { label: '콜로플라스트', primary: '#28798B', light: '#E9F9FB', dark: '#0B5C6E', accent: '#72BCCC' },
      blue:   { label: '스틸',   primary: '#4d6b8c', light: '#edf1f7', dark: '#3d5570', accent: '#9ab3cc' },
      purple: { label: '보라',   primary: '#7c3aed', light: '#f5f3ff', dark: '#6d28d9', accent: '#c4b5fd' },
      green:  { label: '초록',   primary: '#16a34a', light: '#f0fdf4', dark: '#15803d', accent: '#86efac' },
      sky:    { label: '하늘',   primary: '#0284c7', light: '#f0f9ff', dark: '#0369a1', accent: '#7dd3fc' },
      orange: { label: '주황',   primary: '#d97706', light: '#fffbeb', dark: '#b45309', accent: '#fcd34d' },
      teal:   { label: '틸',     primary: '#0d9488', light: '#f0fdfa', dark: '#0f766e', accent: '#5eead4' },
      mint:   { label: '민트',   primary: '#10b981', light: '#ecfdf5', dark: '#059669', accent: '#6ee7b7' },
      gray:   { label: '그레이', primary: '#64748b', light: '#f8fafc', dark: '#475569', accent: '#cbd5e1' },
    };

    function apply(name) {
      const t = THEMES[name]; if (!t) return;
      const r = document.documentElement;
      r.style.setProperty('--primary',        t.primary);
      r.style.setProperty('--primary-light',  t.light);
      r.style.setProperty('--primary-dark',   t.dark);
      r.style.setProperty('--primary-accent', t.accent);
      r.style.setProperty('--menu-active',    t.primary);
      document.querySelectorAll('.theme-swatch').forEach(s =>
        s.classList.toggle('active', s.dataset.theme === name)
      );
      const lbl = document.getElementById('themeLabel');
      if (lbl) lbl.textContent = t.label;
      localStorage.setItem('ce-admin-theme-v2', name);
    }

    function togglePanel() {
      const panel = document.getElementById('themePanel');
      if (panel) panel.classList.toggle('open');
    }

    // 패널 외부 클릭 시 닫기
    document.addEventListener('click', function(e) {
      const btn   = document.getElementById('themePickerBtn');
      const panel = document.getElementById('themePanel');
      if (btn && panel && !btn.contains(e.target) && !panel.contains(e.target))
        panel.classList.remove('open');
    });

    // 저장된 테마 즉시 적용 (DOM 준비 후)
    function init() {
      apply(localStorage.getItem('ce-admin-theme-v2') || 'coloplast');
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }

    return { apply, togglePanel };
  })();
</script>

{{-- 모달은 본문 바깥, body 끝에 둔다.
     화면 파일이 @section('content') 밖에 모달을 두면 Blade 가 그 markup 을
     레이아웃보다 먼저 뱉어 <!DOCTYPE> 앞에 붙는다. 그러면 브라우저가 quirks mode 로
     들어가 sticky·스크롤 계산이 어긋난다. 그런 markup 은 이 스택으로 보낸다. --}}
@stack('modals')

{{-- 화면의 인라인 스크립트가 wwGrid 를 바로 쓸 수 있도록 스택보다 먼저 싣는다 --}}
<script src="@assetv('vendor/wwgrid/wwGrid.js')"></script>
{{-- 날짜 칸에 직접 쳐 넣기ㆍ붙여넣기. 칸의 종류를 바꾸지 않아 달력은 그대로 쓴다. --}}
<script src="@assetv('vendor/ce/date-input.js')"></script>

{{-- 시안대로 그리드 하단 상태바(footer)를 끈 화면에서, 결과바의 ‘선택 N건’ 표시를
     그리드 선택 상태에 맞춘다. 그리드가 선택이 바뀔 때마다 부르는 지점에
     표시 갱신만 얹는다 — 선택 상태는 공개 API 로 읽기만 하고 바꾸지 않는다. --}}
<script>
window.dsBindSelCount = function (grid, elId) {
  const el = document.getElementById(elId);
  if (!grid || !el || typeof grid._updateFooter !== 'function') return;
  const orig = grid._updateFooter.bind(grid);
  grid._updateFooter = function () {
    orig();
    el.textContent = grid.getCheckedRows().length;
  };
  grid._updateFooter();
};
</script>

@stack('scripts')

{{-- ═══════════════════════════════════════════════════════════
     전역 고정 메모 위젯 (어떤 화면에서든 고정 메모 표시)
═══════════════════════════════════════════════════════════ --}}
<div id="globalPinnedMemos"></div>
<script>
(function () {
  const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const BASE_URL = '{{ rtrim(url('/'), '/') }}';

  function escH(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function makeDraggable(el, id) {
    const header = el.querySelector('.gpm-header');
    if (!header) return;
    let sx, sy, ox, oy;
    header.addEventListener('mousedown', function (e) {
      if (e.target.tagName === 'BUTTON') return;
      sx = e.clientX; sy = e.clientY;
      ox = parseInt(el.style.left) || 0;
      oy = parseInt(el.style.top)  || 0;
      function onMove(ev) {
        el.style.left = (ox + ev.clientX - sx) + 'px';
        el.style.top  = (oy + ev.clientY - sy) + 'px';
      }
      function onUp() {
        const pos = { x: parseInt(el.style.left), y: parseInt(el.style.top) };
        localStorage.setItem('pmpos_' + id, JSON.stringify(pos));
        fetch(BASE_URL + '/prescriptions/memos/' + id + '/pin-global', {
          method: 'PATCH',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
          body: JSON.stringify({ pin_x: pos.x, pin_y: pos.y }),
        }).catch(() => {});
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup',   onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup',   onUp);
    });
  }

  function renderGlobalPinned(memos) {
    const container = document.getElementById('globalPinnedMemos');
    if (!container) return;
    container.innerHTML = '';
    memos.forEach(function (m) {
      // 이미 order 페이지에서 렌더된 위젯과 중복 방지: 해당 페이지에 동일 ID가 있으면 스킵
      if (document.getElementById('pinned-memo-' + m.id)) return;

      const saved = JSON.parse(localStorage.getItem('pmpos_' + m.id) || 'null');
      const x = saved?.x ?? m.pin_x ?? (window.innerWidth - 260 - 20);
      const y = saved?.y ?? m.pin_y ?? (window.innerHeight - 200 - 20);

      const el = document.createElement('div');
      el.id = 'pinned-memo-' + m.id;
      el.style.cssText = 'position:fixed;left:' + x + 'px;top:' + y + 'px;width:240px;z-index:9000;background:#FFFDE7;border:1px solid #F0D060;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);';
      el.innerHTML =
        '<div class="gpm-header" style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:#F9C800;border-radius:8px 8px 0 0;cursor:move;user-select:none;">' +
          '<span style="font-size:10px;font-weight:700;color:var(--gray-600);"><i class="fa-solid fa-thumbtack"></i> 메모 고정' +
            (m.rx_number ? '<span style="font-size:10px;font-weight:400;margin-left:4px;opacity:.7;">' + escH(m.rx_number) + '</span>' : '') +
          '</span>' +
          '<div style="display:flex;gap:4px;">' +
            '<button data-unpin="' + m.id + '" title="고정 해제" style="width:18px;height:18px;border:none;border-radius:6px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:var(--gray-600);"><i class="fa-solid fa-thumbtack" style="transform:rotate(45deg);"></i></button>' +
            '<button data-close="' + m.id + '" title="닫기" style="width:18px;height:18px;border:none;border-radius:6px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:var(--gray-600);">×</button>' +
          '</div>' +
        '</div>' +
        '<div style="padding:8px;">' +
          '<textarea data-id="' + m.id + '" style="width:100%;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:60px;">' + escH(m.content) + '</textarea>' +
          '<div style="font-size:10px;color:var(--gray-400);margin-top:2px;">' + escH(m.created_at) + ' · ' + escH(m.user_name) + '</div>' +
        '</div>';

      // 내용 수정 blur
      el.querySelector('textarea').addEventListener('blur', function () {
        fetch(BASE_URL + '/prescriptions/memos/' + m.id + '/update-global', {
          method: 'PATCH',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
          body: JSON.stringify({ content: this.value }),
        }).catch(() => {});
      });

      // 고정 해제
      el.querySelector('[data-unpin]').addEventListener('click', function () {
        fetch(BASE_URL + '/prescriptions/memos/' + m.id + '/unpin', {
          method: 'PATCH',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
          body: JSON.stringify({ pin_x: null, pin_y: null }),
        }).catch(() => {});
        el.remove();
      });

      // 닫기 (고정 유지)
      el.querySelector('[data-close]').addEventListener('click', function () { el.remove(); });

      // 텍스트영역 자동 높이
      const ta = el.querySelector('textarea');
      ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px';

      document.getElementById('globalPinnedMemos').appendChild(el);
      makeDraggable(el, m.id);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    fetch('{{ route('prescriptions.memos.pinned') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(renderGlobalPinned)
      .catch(function () {});
  });
})();
</script>

{{-- ═══════════════════════════════════════════════════════════
     CHAT PANEL
═══════════════════════════════════════════════════════════ --}}
<style>
/* ── Chat Panel ───────────────────────────────────────────── */
#chatPanel {
  position: fixed; top: 0; right: -780px; width: 780px; height: 100vh;
  background: #fff; border-left: 1px solid var(--border);
  display: flex; flex-direction: column; z-index: 1000;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#chatPanel.open { right: 0; box-shadow: -4px 0 32px rgba(0,0,0,.15); }

.chat-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  background: var(--gray-1000); color: #fff; flex-shrink: 0;
}
.chat-header-title { font-size: 14px; font-weight: 700; flex: 1; }
/* 닫기 단추 셋(채팅·SR·도움말)이 23×33 · 28×28 · 32×26 으로 제각각이었다.
   같은 자리에서 같은 일을 하니 헤더 아이콘 버튼과 같은 32×32 · r8 로 맞춘다.
   글자 크기 18·20 은 시안 규격(10~16) 밖이라 16 으로 내린다. */
.chat-header-close {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; flex-shrink: 0;
  background: none; border: none; color: var(--gray-400);
  font-size: 16px; line-height: 1; cursor: pointer; border-radius: 8px;
}
.chat-header-close:hover { color: #fff; background: rgba(255,255,255,.1); }

.chat-body { display: flex; flex: 1; overflow: hidden; }

/* ── Room List ── */
.chat-rooms {
  width: 240px; border-right: 1px solid var(--border);
  display: flex; flex-direction: column; flex-shrink: 0; background: var(--gray-50);
}
.chat-rooms-toolbar {
  display: flex; align-items: center; padding: 10px 10px 6px;
  gap: 6px; border-bottom: 1px solid var(--border);
}
.chat-rooms-toolbar span { font-size: 11px; font-weight: 700; color: var(--text-secondary); flex: 1; }
.chat-room-tabs {
  display: flex; gap: 6px;
  padding: 8px 10px 10px;
  border-bottom: 1px solid var(--border);
  background: var(--gray-50);
}
.chat-room-tab {
  flex: 1;
  border: 1px solid var(--border);
  background: #fff;
  color: var(--text-secondary);
  border-radius: 999px;
  padding: 7px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: var(--transition);
}
.chat-room-tab:hover {
  border-color: var(--primary);
  color: var(--primary);
}
.chat-room-tab.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 4px 12px rgba(77,107,140,.18);
}
.chat-rooms-list { flex: 1; overflow-y: auto; }
.chat-room-item {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 12px; cursor: pointer; transition: background .15s;
  border-bottom: 1px solid var(--gray-100); position: relative;
}
.chat-room-item:hover { background: var(--gray-100); }
.chat-room-item.active { background: var(--primary-light); }
.chat-room-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
}
.chat-room-avatar.group { background: var(--purple); }
.chat-room-info { flex: 1; min-width: 0; }
.chat-room-name { font-size: 12px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-room-preview { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-room-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; flex-shrink: 0; }
.chat-room-time { font-size: 10px; color: var(--text-muted); }
.chat-room-badge {
  background: var(--danger); color: #fff;
  font-size: 10px; font-weight: 700; padding: 1px 5px;
  border-radius: 999px; min-width: 16px; text-align: center;
}

/* ── Chat Window ── */
.chat-window { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

.chat-win-header {
  display: flex; align-items: center; gap: 8px;
  padding: 13px 18px; border-bottom: 1px solid var(--border);
  flex-shrink: 0; background: #fff;
}
.chat-win-title { font-size: 14px; font-weight: 700; flex: 1; }
.chat-win-members { font-size: 12px; color: var(--text-muted); }

.chat-messages {
  flex: 1; overflow-y: auto; padding: 18px 18px 10px;
  display: flex; flex-direction: column; gap: 12px;
}

.chat-empty {
  flex: 1; display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 8px; color: var(--text-muted);
}
.chat-empty i { font-size: 36px; opacity: .3; }

/* ── Message Bubble ── */
.msg-row { display: flex; gap: 8px; max-width: 100%; }
.msg-row.mine { flex-direction: row-reverse; }
.msg-avatar {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; align-self: flex-end;
}
.msg-avatar.mine-av { background: var(--primary-dark); }
.msg-content { max-width: 75%; }
.msg-sender-label {
  font-size: 10px;
  color: var(--primary);
  font-weight: 700;
  margin-bottom: 2px;
}
.msg-screen-name {
  font-size: 10px;
  color: var(--text-secondary);
  margin-bottom: 4px;
}
.msg-name { font-size: 10px; color: var(--text-muted); margin-bottom: 3px; }
.msg-row.mine .msg-name { text-align: right; }
.msg-bubble {
  padding: 10px 14px; border-radius: 12px;
  font-size: 14px; line-height: 1.6; word-break: break-word;
  background: var(--gray-100); color: var(--text-primary);
  border-bottom-left-radius: 4px;
}
.msg-row.mine .msg-bubble {
  background: var(--primary); color: #fff;
  border-bottom-right-radius: 4px; border-bottom-left-radius: 12px;
}
.msg-time { font-size: 10px; color: var(--text-muted); margin-top: 3px; }
.msg-row.mine .msg-time { text-align: right; }

/* 첨부 이미지 */
.msg-img { max-width: 200px; max-height: 180px; border-radius: 8px; cursor: pointer; object-fit: cover; }
/* 파일 첨부 */
.msg-file {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; border-radius: 8px;
  background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.3);
}
.msg-row:not(.mine) .msg-file { background: var(--gray-50); border-color: var(--border); }
.msg-file i { font-size: 18px; }
.msg-file-info { min-width: 0; }
.msg-file-name { font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.msg-file-size { font-size: 10px; opacity: .7; }

/* ── 답글 · 수정 · 삭제 ── */
/* 답글은 원본 바로 아래에 한 단계만 들여쓴다. 더 깊이 들어가면 좁은 패널에서 읽기 어렵다. */
.msg-row.is-reply { padding-left: 26px; }
.msg-row.is-reply.mine { padding-left: 0; padding-right: 26px; }
.msg-quote {
  border-left: 3px solid var(--primary); background: var(--gray-50);
  border-radius: 6px; padding: 4px 8px; margin-bottom: 4px;
  font-size: 11px; line-height: 1.45; cursor: pointer; max-width: 100%;
}
.msg-row.mine .msg-quote { border-left: none; border-right: 3px solid var(--primary); text-align: right; }
.msg-quote-name { font-weight: 700; color: var(--primary); display: block; }
.msg-quote-body {
  color: var(--text-secondary); display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
.msg-quote.gone .msg-quote-body { font-style: italic; color: var(--text-muted); }
.msg-deleted {
  font-size: 12px; color: var(--text-muted); font-style: italic;
  border: 1px dashed var(--border); border-radius: 10px; padding: 7px 11px;
}
.msg-edited { font-size: 10px; color: var(--text-muted); margin-left: 4px; }

/* 말풍선에 올렸을 때만 나오는 ⋯ */
.msg-tools {
  display: flex; gap: 2px; align-items: center;
  opacity: 0; transition: opacity .12s; align-self: center;
}
.msg-row:hover .msg-tools { opacity: 1; }
.msg-tool-btn {
  background: none; border: none; cursor: pointer; padding: 3px 5px;
  border-radius: 5px; color: var(--text-muted); font-size: 11px; line-height: 1;
}
.msg-tool-btn:hover { background: var(--gray-100); color: var(--text-primary); }

/* 입력창 위 '답글 대상' 배너 */
#chatReplyBar {
  display: none; align-items: center; gap: 8px; flex-shrink: 0;
  padding: 7px 14px; background: var(--primary-light);
  border-top: 1px solid var(--primary-200); font-size: 12px;
}
#chatReplyBar.show { display: flex; }
#chatReplyBarText { flex: 1; min-width: 0; color: var(--text-secondary); }
#chatReplyBarText b { color: var(--primary); }
#chatReplyBarText span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── 패널을 끌어 옮겼을 때 ── */
/* 옮기기 전에는 오른쪽에서 밀려 나오는 서랍이고, 옮긴 뒤에는 떠 있는 창이 된다.
   좌표를 직접 잡으므로 right 기준 애니메이션을 끈다. */
#chatPanel.moved {
  right: auto; transition: none;
  height: min(760px, calc(100vh - 40px));
  border: 1px solid var(--border); border-radius: 12px; overflow: hidden;
  box-shadow: 0 16px 48px rgba(0,0,0,.24);
}
#chatPanel.moved:not(.open) { display: none; }
#chatPanel.dragging { user-select: none; }
.chat-header.movable { cursor: grab; }
.chat-header.movable:active { cursor: grabbing; }
.chat-header-btn {
  background: none; border: none; color: var(--gray-400);
  font-size: 12px; cursor: pointer; padding: 3px 6px; border-radius: 4px;
}
.chat-header-btn:hover { color: #fff; background: rgba(255,255,255,.1); }

/* ── 파일 붙여넣기 미리보기 ── */
#chatPastePreview {
  display: none; flex-shrink: 0;
  padding: 8px 12px; border-top: 1px solid var(--border);
  background: var(--gray-50); gap: 8px; align-items: center;
}
#chatPastePreview.show { display: flex; }
#chatPasteThumb { max-height: 60px; max-width: 80px; border-radius: 6px; object-fit: cover; }
#chatPasteFileName { font-size: 12px; font-weight: 500; flex: 1; }
#chatPasteClear {
  background: none; border: none; color: var(--danger);
  font-size: 16px; cursor: pointer; padding: 2px 6px; border-radius: 4px;
}
#chatPasteClear:hover { background: var(--danger-light); }

/* ── Input Area ── */
.chat-input-area {
  padding: 14px 16px; border-top: 2px solid var(--border);
  display: flex; gap: 10px; align-items: flex-end; flex-shrink: 0;
  background: var(--gray-50);
}
#chatInput {
  flex: 1; resize: none; border: 1.5px solid var(--border); border-radius: 12px;
  padding: 11px 14px; font-size: 14px; line-height: 1.6;
  min-height: 44px; max-height: 160px; outline: none; font-family: inherit;
  background: #fff;
}
#chatInput:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(77,107,140,.12); }
#chatInput::placeholder { color: var(--gray-400); }
.chat-send-btn {
  width: 44px; height: 44px; border-radius: 12px;
  background: var(--primary); border: none; color: #fff;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background .15s;
}
.chat-send-btn:hover { background: var(--primary-dark); }
.chat-send-btn i { font-size: 16px; }
.chat-file-btn {
  width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border);
  background: #fff; color: var(--text-secondary); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: var(--transition);
}
.chat-file-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
input#chatFileInput { display: none; }

/* ── New Room Modal ── */
#chatNewRoomModal {
  display: none; position: fixed; inset: 0; z-index: 1100;
  background: rgba(0,0,0,.45); align-items: center; justify-content: center;
}
#chatNewRoomModal.show { display: flex; }
.chat-modal-box {
  background: #fff; border-radius: 12px; padding: 24px;
  width: 360px; box-shadow: var(--shadow-lg);
}
.chat-modal-title { font-size: 14px; font-weight: 700; margin-bottom: 16px; }
.chat-user-check { display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer; }
.chat-user-check input { width: 15px; height: 15px; cursor: pointer; }
.chat-user-check label { font-size: 13px; cursor: pointer; }
.chat-modal-actions { display: flex; gap: 8px; margin-top: 18px; justify-content: flex-end; }

/* ── Image Lightbox ── */
#chatLightbox {
  display: none; position: fixed; inset: 0; z-index: 1200;
  background: rgba(0,0,0,.85); align-items: center; justify-content: center;
  cursor: zoom-out;
}
#chatLightbox.show { display: flex; }
#chatLightboxImg { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
#chatLightboxClose {
  position: absolute; top: 18px; right: 22px;
  width: 40px; height: 40px; border-radius: 50%;
  background: rgba(255,255,255,.15); border: 2px solid rgba(255,255,255,.4);
  color: #fff; font-size: 22px; line-height: 1; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .15s;
}
#chatLightboxClose:hover { background: rgba(255,255,255,.3); }

/* ── Overlay (패널 외부 클릭 시 닫기) ── */
#chatOverlay {
  display: none; position: fixed; inset: 0; z-index: 999;
}
#chatOverlay.show { display: block; }

/* ══════════════════════════════════════════════════════════
   SR 관리 패널 (모든 화면에서 우측 슬라이드인)
══════════════════════════════════════════════════════════ */
.btn-icon-badge {
  position: absolute; top: 2px; right: 2px; min-width: 15px; height: 15px;
  padding: 0 4px; border-radius: 999px; background: var(--danger); color: #fff;
  font-size: 10px; font-weight: 700; line-height: 15px; text-align: center;
}
#srOverlay { display: none; position: fixed; inset: 0; z-index: 1000; }
#srOverlay.show { display: block; }

#srPanel {
  position: fixed; top: 0; right: -860px; width: 860px; max-width: 96vw; height: 100vh;
  background: var(--bg-card); border-left: 1px solid var(--border);
  display: flex; flex-direction: column; z-index: 1001;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#srPanel.open { right: 0; box-shadow: -6px 0 40px rgba(0,0,0,.18); }
.sr-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.sr-header-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.sr-header-sub { font-size: 12px; color: var(--text-muted); }
.sr-header-close {
  display: flex; align-items: center; justify-content: center;
  margin-left: auto; width: 32px; height: 32px; flex-shrink: 0; border: none; background: none;
  color: var(--text-muted); font-size: 16px; line-height: 1; cursor: pointer; border-radius: 8px;
}
.sr-header-close:hover { background: var(--bg); color: var(--text-primary); }

.sr-tabs { display: flex; gap: 4px; padding: 10px 18px 0; border-bottom: 2px solid var(--border); flex-shrink: 0; }
.sr-tab {
  padding: 8px 16px; font-size: 13px; font-weight: 700; border: none; background: none;
  color: var(--text-secondary); border-bottom: 3px solid transparent; margin-bottom: -2px; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.sr-tab:hover { color: var(--primary); }
.sr-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.sr-tab .cnt {
  min-width: 18px; padding: 0 5px; height: 17px; border-radius: 999px;
  font-size: 10px; font-weight: 700; background: var(--border-light); color: var(--text-muted);
  display: inline-flex; align-items: center; justify-content: center;
}
.sr-tab.active .cnt { background: var(--primary); color: #fff; }

.sr-body { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 18px 18px; }
.sr-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.sr-field label { font-size: 12px; font-weight: 700; color: var(--text-secondary); }
.sr-field input[type=text], .sr-field select, .sr-field textarea {
  padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px;
  font-size: 13px; font-family: inherit; background: #fff;
}
.sr-field textarea { min-height: 110px; resize: vertical; }
.sr-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.sr-hint { font-size: 11px; color: var(--text-muted); line-height: 1.6; }
.sr-detail {
  border: 1px solid var(--border); border-radius: var(--radius);
  padding: 14px 16px; margin-bottom: 14px; background: var(--bg);
}
.sr-detail h5 { margin: 0 0 4px; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.sr-detail .meta { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
.sr-detail .body { font-size: 13px; line-height: 1.8; white-space: pre-wrap; color: var(--text-primary); }
.sr-answer-box {
  margin-top: 12px; padding: 12px 14px; background: var(--primary-light);
  border: 1px solid var(--primary-accent, var(--border)); border-radius: 8px;
}
.sr-answer-box .lbl { font-size: 11px; font-weight: 700; color: var(--primary); margin-bottom: 5px; }
.sr-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
.sr-b-open        { background: var(--warning-light); color: var(--warning); }
.sr-b-in_progress { background: var(--primary-light);  color: var(--primary); }
.sr-b-answered    { background: var(--success-light);  color: var(--success); }
.sr-b-closed      { background: var(--border-light);   color: var(--text-muted); }

</style>

{{-- Chat Panel HTML --}}
<div id="chatOverlay" onclick="ChatPanel.close()"></div>

<div id="chatPanel">
  {{-- 패널 헤더 --}}
  <div class="chat-header movable" id="chatHeader">
    <i class="fa-solid fa-comments" style="font-size:16px;color:var(--gray-400);"></i>
    <span class="chat-header-title">채팅</span>
    <button class="chat-header-btn" id="chatResetPosBtn" title="위치 초기화" style="display:none;"
            onclick="ChatPanel.resetPosition()"><i class="fa-solid fa-arrow-right-to-bracket"></i></button>
    <button class="chat-header-close" onclick="ChatPanel.close()">×</button>
  </div>

  <div class="chat-body">
    {{-- 방 목록 --}}
    <div class="chat-rooms">
      <div class="chat-rooms-toolbar">
        <span>대화</span>
        <button class="chat-file-btn" title="새 채팅" onclick="ChatPanel.openNewRoom()" style="width:26px;height:26px;">
          <i class="fa-solid fa-plus" style="font-size:11px;"></i>
        </button>
      </div>
      <div class="chat-room-tabs">
        <button type="button" class="chat-room-tab active" id="chatRoomTab-company" onclick="ChatPanel.setCategory('company')">회사</button>
        <button type="button" class="chat-room-tab" id="chatRoomTab-customer" onclick="ChatPanel.setCategory('customer')">고객</button>
      </div>
      <div class="chat-rooms-list" id="chatRoomList">
        <div style="padding:24px 12px;text-align:center;color:var(--text-muted);font-size:12px;">
          <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
      </div>
    </div>

    {{-- 대화창 --}}
    <div class="chat-window">
      {{-- 빈 상태 --}}
      <div class="chat-empty" id="chatEmptyState">
        <i class="fa-regular fa-comments"></i>
        <span style="font-size:13px;">채팅방을 선택하세요</span>
      </div>

      {{-- 활성 대화 --}}
      <div id="chatActiveWindow" style="display:none;flex-direction:column;height:100%;">
        <div class="chat-win-header">
          <div>
            <div class="chat-win-title" id="chatWinTitle">-</div>
            <div class="chat-win-members" id="chatWinMembers"></div>
          </div>
        </div>

        {{-- CE샵 고객 정보 배너 --}}
        <div id="shopCustomerBar" style="display:none;background:var(--gray-50);border-bottom:1px solid var(--gray-200);padding:7px 14px;font-size:12px;color:#374151;flex-shrink:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <i class="fa-solid fa-user-circle" style="color:var(--gray-600);"></i>
            <span id="shopCustomerName" style="font-weight:700;"></span>
            <span id="shopCustomerPhone" style="color:var(--gray-600);"></span>
            <span id="shopCustomerEmail" style="color:var(--gray-400);font-size:11px;"></span>
            <a id="shopCustomerPatientLink" href="#" target="_blank"
               style="margin-left:auto;font-size:11px;background:var(--primary);color:#fff;padding:2px 8px;border-radius:4px;text-decoration:none;display:none;">
              환자 기록 보기
            </a>
            <span id="shopCustomerNoPatient" style="margin-left:auto;font-size:11px;color:var(--gray-400);display:none;">등록된 환자 없음</span>
          </div>
        </div>

        <div class="chat-messages" id="chatMessages">
          <div style="text-align:center;color:var(--text-muted);font-size:12px;" id="chatLoadMore" style="display:none;">
            <button onclick="ChatPanel.loadMore()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 12px;font-size:11px;cursor:pointer;color:var(--text-secondary);">이전 메시지</button>
          </div>
        </div>

        {{-- 붙여넣기 파일 미리보기 --}}
        <div id="chatPastePreview">
          <img id="chatPasteThumb" src="" alt="" style="display:none;">
          <i class="fa-solid fa-file" id="chatPasteFileIcon" style="font-size:24px;color:var(--primary);display:none;"></i>
          <span id="chatPasteFileName">파일명</span>
          <button id="chatPasteClear" onclick="ChatPanel.clearPaste()">×</button>
        </div>

        {{-- 답글 대상 --}}
        <div id="chatReplyBar">
          <i class="fa-solid fa-reply" style="color:var(--primary);"></i>
          <div id="chatReplyBarText"></div>
          <button class="chat-tool-btn" onclick="ChatPanel.cancelReply()"
                  style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;">×</button>
        </div>

        {{-- 입력창 --}}
        <div class="chat-input-area">
          <button class="chat-file-btn" title="파일 첨부" onclick="document.getElementById('chatFileInput').click()">
            <i class="fa-solid fa-paperclip" style="font-size:13px;"></i>
          </button>
          <input type="file" id="chatFileInput" accept="*/*">
          <textarea id="chatInput" rows="1" placeholder="메시지를 입력하세요 (Shift+Enter: 줄바꿈)"></textarea>
          <button class="chat-send-btn" onclick="ChatPanel.send()">
            <i class="fa-solid fa-paper-plane" style="font-size:14px;"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- 새 채팅방 모달 --}}
<div id="chatNewRoomModal">
  <div class="chat-modal-box">
    <div class="chat-modal-title"><i class="fa-solid fa-plus" style="color:var(--primary);margin-right:6px;"></i>새 채팅 시작</div>
    <div style="margin-bottom:10px;">
      <label style="font-size:12px;font-weight:500;color:var(--text-secondary);">유형</label>
      <div style="display:flex;gap:12px;margin-top:6px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
          <input type="radio" name="chatRoomType" value="direct" checked> 1:1 채팅
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
          <input type="radio" name="chatRoomType" value="group"> 그룹 채팅
        </label>
      </div>
    </div>
    <div id="chatGroupNameWrap" style="display:none;margin-bottom:10px;">
      <label style="font-size:12px;font-weight:500;color:var(--text-secondary);">그룹 이름</label>
      <input type="text" id="chatGroupName" class="form-control" style="margin-top:4px;" placeholder="그룹 이름 입력">
    </div>
    <label style="font-size:12px;font-weight:500;color:var(--text-secondary);">대화 상대</label>
    <div id="chatUserList" style="margin-top:6px;max-height:180px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:6px 10px;"></div>
    <div class="chat-modal-actions">
      <button class="btn btn-outline btn-sm" onclick="ChatPanel.closeNewRoom()">취소</button>
      <button class="btn btn-primary btn-sm" onclick="ChatPanel.createRoom()">시작</button>
    </div>
  </div>
</div>

{{-- 이미지 라이트박스 --}}
<div id="chatLightbox" onclick="if(event.target===this)this.classList.remove('show')">
  <button id="chatLightboxClose" onclick="document.getElementById('chatLightbox').classList.remove('show')" title="닫기">×</button>
  <img id="chatLightboxImg" src="" alt="">
</div>

@perm('service-requests')
{{-- ═══════════════════════════════════════════════════════════
     SR 관리 패널 — 모든 화면에서 바로 등록·답변
════════════════════════════════════════════════════════════ --}}
<div id="srOverlay" onclick="SrPanel.close()"></div>
<div id="srPanel">
  <div class="sr-header">
    <i class="bx bx-clipboard" style="font-size:19px;color:var(--primary);"></i>
    <div>
      <div class="sr-header-title">SR 관리</div>
      <div class="sr-header-sub">화면 개선·오류를 등록하고 답변을 남깁니다</div>
    </div>
    <button class="sr-header-close" onclick="SrPanel.close()">×</button>
  </div>

  <div class="sr-tabs">
    <button type="button" class="sr-tab active" id="srTabList" onclick="SrPanel.show('list')">
      <i class="bx bx-list-ul"></i> SR 목록 <span class="cnt" id="srCntAll">0</span>
    </button>
    <button type="button" class="sr-tab" id="srTabNew" onclick="SrPanel.show('new')">
      <i class="bx bx-plus"></i> 신규 등록
    </button>
    <button type="button" class="sr-tab" id="srTabDetail" onclick="SrPanel.show('detail')" style="display:none;">
      <i class="bx bx-detail"></i> 상세 · 답변
    </button>
    <a href="{{ route('sr.index') }}" class="sr-tab" style="margin-left:auto;color:var(--text-muted);"
       title="전용 화면에서 검색·필터로 보기">
      <i class="bx bx-link-external"></i> 전체 화면
    </a>
  </div>

  {{-- 목록 --}}
  <div class="sr-body" id="srPaneList">
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
      {{-- 화면의 다른 고르는 칸과 같은 화살표를 달려면 .form-control 이어야 한다.
           단 .form-control 은 width:100% 라 이 칸이 823 로 늘어 툴바가 32 → 72 로
           두 줄이 되고 표가 40 내려갔다 — 폭만 제 글자만큼으로 되돌린다. --}}
      <select id="srFilterStatus" class="form-control" onchange="SrPanel.load()"
              style="width:auto;height:32px;padding:0 30px 0 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;">
        <option value="">전체 상태</option>
        @foreach($srStatuses ?? \App\Models\ServiceRequest::STATUSES as $k => $v)
          <option value="{{ $k }}">{{ $v }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-outline btn-sm" onclick="SrPanel.load()">
        <i class="bx bx-refresh"></i> 새로고침
      </button>
      <span class="sr-hint" style="margin-left:auto;">행을 <b>클릭</b>하면 상세·답변으로 이동합니다.</span>
    </div>
    <div id="srGrid"></div>
  </div>

  {{-- 신규 등록 --}}
  <div class="sr-body" id="srPaneNew" style="display:none;">
    @perm('service-requests', 'create')
    <div class="sr-row2">
      <div class="sr-field">
        <label>구분</label>
        <select id="srCategory">
          @foreach(\App\Models\ServiceRequest::CATEGORIES as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="sr-field">
        <label>우선순위</label>
        <select id="srPriority">
          @foreach(\App\Models\ServiceRequest::PRIORITIES as $k => $v)
            <option value="{{ $k }}" {{ $k === 'normal' ? 'selected' : '' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="sr-field">
      <label>제목 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="srTitle" maxlength="200" placeholder="예) 처방전 목록에 발행일 필터 추가">
    </div>
    <div class="sr-field">
      <label>내용 <span style="color:var(--danger);">*</span></label>
      <textarea id="srContent" maxlength="5000" placeholder="어떤 화면에서 무엇이 어떻게 되면 좋을지 적어 주세요."></textarea>
    </div>
    <div class="sr-field">
      <label>대상 화면</label>
      <input type="text" id="srPageLabel" readonly style="background:var(--bg);color:var(--text-muted);">
      <span class="sr-hint">패널을 연 화면이 자동으로 기록됩니다.</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="srSubmitBtn" onclick="SrPanel.submit()"
            style="width:100%;height:40px;">
      <i class="bx bx-send"></i> SR 등록
    </button>
    @else
    <div class="sr-hint" style="padding:40px 0;text-align:center;">SR 등록 권한이 없습니다.</div>
    @endperm
  </div>

  {{-- 상세 · 답변 --}}
  <div class="sr-body" id="srPaneDetail" style="display:none;">
    <div id="srDetailBox"></div>
    @perm('service-requests', 'update')
    <div class="sr-field">
      <label>답변</label>
      <textarea id="srAnswer" maxlength="5000" placeholder="처리 결과나 안내를 적어 주세요."></textarea>
    </div>
    <div class="sr-row2">
      <div class="sr-field">
        <label>상태</label>
        <select id="srStatus">
          @foreach(\App\Models\ServiceRequest::STATUSES as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="sr-field" style="justify-content:flex-end;">
        <button type="button" class="btn btn-primary btn-sm" id="srAnswerBtn" onclick="SrPanel.saveAnswer()"
                style="height:38px;">
          <i class="bx bx-save"></i> 답변 저장
        </button>
      </div>
    </div>
    @else
    <div class="sr-hint">답변 권한이 없어 조회만 가능합니다.</div>
    @endperm
  </div>
</div>

{{-- ══ SR 관리 패널 JS ══ --}}
<script>
const SrPanel = (() => {
  const LIST_URL  = BASE_URL + '/sr/list';
  const STORE_URL = BASE_URL + '/sr';
  const STATUS_CLS = { open:'sr-b-open', in_progress:'sr-b-in_progress', answered:'sr-b-answered', closed:'sr-b-closed' };

  let _grid = null, _rows = [], _sel = null, _loaded = false;
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  function toggle() { document.getElementById('srPanel').classList.contains('open') ? close() : open(); }

  async function open() {
    document.getElementById('srPanel').classList.add('open');
    document.getElementById('srOverlay').classList.add('show');
    const lbl = document.getElementById('srPageLabel');
    if (lbl) lbl.value = document.title.replace(/\s*\|.*$/, '').trim() || location.pathname;

    if (!_loaded) {
      _loaded = true;
      buildGrid();
      load();
    }
  }
  function close() {
    document.getElementById('srPanel').classList.remove('open');
    document.getElementById('srOverlay').classList.remove('show');
  }

  function show(which) {
    ['list', 'new', 'detail'].forEach(k => {
      const pane = document.getElementById('srPane' + k[0].toUpperCase() + k.slice(1));
      const tab  = document.getElementById('srTab'  + k[0].toUpperCase() + k.slice(1));
      if (pane) pane.style.display = (k === which) ? '' : 'none';
      if (tab)  tab.classList.toggle('active', k === which);
    });
  }

  function buildGrid() {
    _grid = new wwGrid({
      el: document.getElementById('srGrid'),
      height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
      footer: { total: true, selected: false, modified: false },
      columns: [
        { header: '상태',   name: 'statusLabel',   width: 80,  align: 'center', sortable: true },
        { header: '구분',   name: 'categoryLabel', width: 90,  align: 'center', sortable: true },
        { header: '우선',   name: 'priorityLabel', width: 70,  align: 'center', sortable: true },
        { header: '제목',   name: 'title',         width: 260 },
        { header: '대상 화면', name: 'page',       width: 130 },
        { header: '등록자', name: 'writer',        width: 90,  sortable: true },
        { header: '등록일', name: 'created',       width: 130, align: 'center', sortable: true },
      ],
      data: [],
    });

    document.getElementById('srGrid').addEventListener('click', e => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = _grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row) selectRow(row.id);
    });
  }

  async function load() {
    const st = document.getElementById('srFilterStatus')?.value ?? '';
    try {
      const res = await fetch(LIST_URL + (st ? '?status=' + encodeURIComponent(st) : ''),
                              { headers: { 'Accept': 'application/json' } });
      const d = await res.json();
      _rows = d.rows || [];
      if (_grid) _grid.setData(_rows);

      const open = (d.counts?.open ?? 0) + (d.counts?.in_progress ?? 0);
      document.getElementById('srCntAll').textContent = d.counts?.all ?? 0;
      const badge = document.getElementById('srBadge');
      if (badge) {
        badge.textContent = open;
        badge.style.display = open > 0 ? '' : 'none';
      }
    } catch (e) { showToast('SR 목록을 불러오지 못했습니다.', 'danger'); }
  }

  function selectRow(id) {
    const r = _rows.find(x => x.id === id);
    if (!r) return;
    _sel = r;

    document.getElementById('srDetailBox').innerHTML = `
      <div class="sr-detail">
        <h5>${esc(r.title)}</h5>
        <div class="meta">
          <span class="sr-badge ${STATUS_CLS[r.status] || ''}">${esc(r.statusLabel)}</span>
          · ${esc(r.categoryLabel)} · 우선순위 ${esc(r.priorityLabel)}
          · ${esc(r.writer)} · ${esc(r.created)}
          ${r.page ? ' · 대상: ' + esc(r.page) : ''}
        </div>
        <div class="body">${esc(r.content)}</div>
        ${r.answer ? `<div class="sr-answer-box">
          <div class="lbl">답변 · ${esc(r.answerer)} · ${esc(r.answered_at)}</div>
          <div class="body">${esc(r.answer)}</div>
        </div>` : ''}
      </div>`;

    const a = document.getElementById('srAnswer');
    if (a) a.value = r.answer || '';
    const s = document.getElementById('srStatus');
    if (s) s.value = r.status;

    document.getElementById('srTabDetail').style.display = '';
    show('detail');
  }

  async function submit() {
    const title   = document.getElementById('srTitle').value.trim();
    const content = document.getElementById('srContent').value.trim();
    if (!title || !content) { ceAlert('제목과 내용을 모두 입력해 주세요.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srSubmitBtn');
    btn.disabled = true;
    try {
      const res = await fetch(STORE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
        body: JSON.stringify({
          title, content,
          category:   document.getElementById('srCategory').value,
          priority:   document.getElementById('srPriority').value,
          page_label: document.getElementById('srPageLabel').value,
          page_url:   location.pathname + location.search,
        }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) {
        ceAlert(d.message || Object.values(d.errors ?? {}).flat().join('\n') || '등록하지 못했습니다.', { tone: 'danger' });
        return;
      }
      showToast(d.message, 'success');
      document.getElementById('srTitle').value = '';
      document.getElementById('srContent').value = '';
      await load();
      show('list');
    } catch (e) {
      ceAlert('등록 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  }

  async function saveAnswer() {
    if (!_sel) return;
    const answer = document.getElementById('srAnswer').value.trim();
    if (!answer) { ceAlert('답변 내용을 입력해 주세요.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srAnswerBtn');
    btn.disabled = true;
    try {
      const res = await fetch(`${STORE_URL}/${_sel.id}/answer`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
        body: JSON.stringify({ answer, status: document.getElementById('srStatus').value }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) { ceAlert(d.message || '저장하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      await load();
      selectRow(_sel.id);
    } catch (e) {
      ceAlert('저장 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  }

  return { toggle, open, close, show, load, submit, saveAnswer };
})();
</script>
@endperm

{{-- Pusher + Echo (CDN) --}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
// Laravel Echo 없이 Pusher 직접 사용
const PUSHER_KEY     = '{{ config("broadcasting.connections.pusher.key") }}';
const PUSHER_CLUSTER = '{{ config("broadcasting.connections.pusher.options.cluster", "ap3") }}';
const AUTH_USER_ID   = {{ Auth::id() }};
const IS_ADMIN       = '{{ Auth::user()->role }}' === 'admin';

let pusherClient = null;
if (PUSHER_KEY) {
  pusherClient = new Pusher(PUSHER_KEY, {
    cluster: PUSHER_CLUSTER,
    authEndpoint: BASE_URL + '/broadcasting/auth',
    auth: { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } },
  });
  pusherClient.connection.bind('connected',    () => console.log('[Pusher] connected'));
  pusherClient.connection.bind('error',    (e) => console.error('[Pusher] error', e));
  pusherClient.connection.bind('disconnected', () => console.warn('[Pusher] disconnected'));
}

// ══════════════════════════════════════════════════════════════
// ChatPanel 모듈
// ══════════════════════════════════════════════════════════════
const ChatPanel = (() => {
  let currentRoomId  = null;
  let currentPage    = 1;
  let hasMore        = false;
  let pasteFile      = null;
  let activeRoomCategory = 'company';
  let subscribedRooms = new Set();
  let replyToId      = null;   // 지금 답글을 달고 있는 원본 메시지
  const POS_KEY      = 'ce.chatPanel.pos';

  // ── 패널 열기/닫기 ──────────────────────────────────────────
  function toggle() {
    const panel = document.getElementById('chatPanel');
    if (panel.classList.contains('open')) close();
    else open();
  }

  function open() {
    const panel = document.getElementById('chatPanel');
    panel.classList.add('open');
    // 떠 있는 창으로 옮겨 둔 상태면 뒤를 덮지 않는다 — 화면을 계속 쓰려고 옮긴 것이다.
    if (!panel.classList.contains('moved')) {
      document.getElementById('chatOverlay').classList.add('show');
    }
    loadRooms();
  }

  function close() {
    document.getElementById('chatPanel').classList.remove('open');
    document.getElementById('chatOverlay').classList.remove('show');
  }

  // ── 패널 위치 옮기기 ─────────────────────────────────────────
  /** 헤더를 잡고 끌면 떠 있는 창이 된다. 위치는 브라우저에 기억해 둔다. */
  function initDrag() {
    const panel  = document.getElementById('chatPanel');
    const header = document.getElementById('chatHeader');
    if (!panel || !header) return;

    restorePosition();

    let sx = 0, sy = 0, ox = 0, oy = 0, dragging = false;

    header.addEventListener('pointerdown', (e) => {
      if (e.button !== 0 || e.target.closest('button')) return;
      const box = panel.getBoundingClientRect();
      // 좌표 기준으로 바꾸는 순간부터 right 앵커를 버린다
      moveTo(box.left, box.top);
      sx = e.clientX; sy = e.clientY; ox = box.left; oy = box.top;
      dragging = true;
      panel.classList.add('dragging');
      header.setPointerCapture(e.pointerId);
    });

    header.addEventListener('pointermove', (e) => {
      if (!dragging) return;
      moveTo(ox + (e.clientX - sx), oy + (e.clientY - sy));
    });

    const stop = (e) => {
      if (!dragging) return;
      dragging = false;
      panel.classList.remove('dragging');
      try { header.releasePointerCapture(e.pointerId); } catch (_) {}
      const box = panel.getBoundingClientRect();
      localStorage.setItem(POS_KEY, JSON.stringify({ left: box.left, top: box.top }));
    };
    header.addEventListener('pointerup', stop);
    header.addEventListener('pointercancel', stop);

    // 창 크기가 줄어 패널이 화면 밖으로 나가면 도로 끌어들인다
    window.addEventListener('resize', () => {
      if (!panel.classList.contains('moved')) return;
      const box = panel.getBoundingClientRect();
      moveTo(box.left, box.top);
    });
  }

  /** 화면 안에 물려 둔다 — 헤더가 사라지면 다시 잡을 수 없다 */
  function moveTo(left, top) {
    const panel = document.getElementById('chatPanel');
    panel.classList.add('moved');
    const box = panel.getBoundingClientRect();
    const maxLeft = Math.max(0, window.innerWidth  - box.width);
    const maxTop  = Math.max(0, window.innerHeight - 48);
    panel.style.left = Math.min(Math.max(0, left), maxLeft) + 'px';
    panel.style.top  = Math.min(Math.max(0, top),  maxTop)  + 'px';
    document.getElementById('chatResetPosBtn').style.display = '';
    document.getElementById('chatOverlay').classList.remove('show');
  }

  function restorePosition() {
    try {
      const saved = JSON.parse(localStorage.getItem(POS_KEY) || 'null');
      if (saved && Number.isFinite(saved.left) && Number.isFinite(saved.top)) {
        moveTo(saved.left, saved.top);
      }
    } catch (_) {}
  }

  /** 오른쪽 서랍으로 되돌린다 */
  function resetPosition() {
    const panel = document.getElementById('chatPanel');
    panel.classList.remove('moved');
    panel.style.left = '';
    panel.style.top  = '';
    localStorage.removeItem(POS_KEY);
    document.getElementById('chatResetPosBtn').style.display = 'none';
    if (panel.classList.contains('open')) {
      document.getElementById('chatOverlay').classList.add('show');
    }
  }

  // ── 미읽음 배지 갱신 (캐시 기반) ────────────────────────────
  function updateUnreadBadges() {
    const cache = window._chatRoomCache || {};
    const total = Object.values(cache).reduce((s, r) => s + (r.unread || 0), 0);
    document.getElementById('chatUnreadDot').style.display = total > 0 ? '' : 'none';
    const fab = document.getElementById('chatFabBadge');
    if (fab) {
      if (total > 0) { fab.textContent = total > 99 ? '99+' : total; fab.style.display = 'flex'; }
      else { fab.style.display = 'none'; }
    }
  }

  function extractScreenNameFromBody(body) {
    if (!body) return null;
    const match = String(body).match(/(?:^|\n)\[[^\]\n]+\]\s*([^\n]+)/);
    return match ? match[1].trim() : null;
  }

  function stripScreenNameFromBody(body) {
    if (body == null) return body;
    return String(body).replace(/(?:\r?\n)\[[^\]\n]+\]\s*[^\n]+$/, '').trimEnd();
  }

  function normalizeChatData(data) {
    if (!data) return data;
    const screenName = data.screen_name || extractScreenNameFromBody(data.body);
    return {
      ...data,
      screen_name: screenName || null,
      body: stripScreenNameFromBody(data.body),
    };
  }

  // ── 방 목록 로드 ─────────────────────────────────────────────
  async function loadRooms() {
    const res  = await fetch(BASE_URL + '/chat/rooms', {
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    const data = await res.json();

    // 방 캐시 갱신 (현재 열린 방은 unread=0 유지)
    window._chatRoomCache = window._chatRoomCache || {};
    data.rooms.forEach(r => {
      r.unread = (r.id === currentRoomId) ? 0 : (r.unread || 0);
      window._chatRoomCache[r.id] = r;
    });
    renderRooms(data.rooms);
    window._chatUsers = data.users;

    // 미읽음 도트 + FAB 배지
    const totalUnread = data.rooms.reduce((s, r) => s + (r.unread || 0), 0);
    document.getElementById('chatUnreadDot').style.display = totalUnread > 0 ? '' : 'none';
    const fab = document.getElementById('chatFabBadge');
    if (fab) {
      if (totalUnread > 0) {
        fab.textContent = totalUnread > 99 ? '99+' : totalUnread;
        fab.style.display = 'flex';
      } else {
        fab.style.display = 'none';
      }
    }

    // Pusher 구독 (방마다)
    data.rooms.forEach(r => subscribeRoom(r.id));
  }

  function renderRooms(rooms) {
    refreshRoomTabs(rooms);
    const el = document.getElementById('chatRoomList');
    const filteredRooms = rooms.filter(r => (r.category || 'company') === activeRoomCategory);
    if (!filteredRooms.length) {
      const emptyLabel = activeRoomCategory === 'customer' ? '고객 채팅방이 없습니다' : '회사 채팅방이 없습니다';
      el.innerHTML = `<div style="padding:20px 12px;text-align:center;color:var(--text-muted);font-size:12px;">${emptyLabel}</div>`;
      return;
    }
    el.innerHTML = filteredRooms.map(r => `
      <div class="chat-room-item ${r.id === currentRoomId ? 'active' : ''}"
           id="room-item-${r.id}" onclick="ChatPanel.selectRoom(${r.id})">
        <div class="chat-room-avatar ${r.type === 'group' ? 'group' : ''}">${r.name.charAt(0)}</div>
        <div class="chat-room-info">
          <div class="chat-room-name">${escHtml(r.name)}</div>
          <div class="chat-room-preview">${r.latest_body ? escHtml(r.latest_body) : '&nbsp;'}</div>
        </div>
        <div class="chat-room-meta">
          <div class="chat-room-time">${r.latest_time || ''}</div>
          ${r.unread && r.id !== currentRoomId ? `<div class="chat-room-badge">${r.unread}</div>` : ''}
        </div>
      </div>
    `).join('');
  }

  // ── Pusher 채널 구독 ─────────────────────────────────────────
  function refreshRoomTabs(rooms) {
    const companyCount = rooms.filter(r => (r.category || 'company') === 'company').length;
    const customerCount = rooms.filter(r => (r.category || 'company') === 'customer').length;
    const companyTab = document.getElementById('chatRoomTab-company');
    const customerTab = document.getElementById('chatRoomTab-customer');

    companyTab.textContent = `회사 (${companyCount})`;
    customerTab.textContent = `고객 (${customerCount})`;
    companyTab.classList.toggle('active', activeRoomCategory === 'company');
    customerTab.classList.toggle('active', activeRoomCategory === 'customer');
  }

  function setCategory(category) {
    activeRoomCategory = category === 'customer' ? 'customer' : 'company';
    renderRooms(Object.values(window._chatRoomCache || {}));
  }

  function ensureRoomCategoryVisible(roomId) {
    const room = window._chatRoomCache?.[roomId];
    if (!room) return;

    const category = room.category || 'company';
    if (category !== activeRoomCategory) {
      activeRoomCategory = category;
      renderRooms(Object.values(window._chatRoomCache || {}));
    }
  }

  function subscribeRoom(roomId) {
    if (!pusherClient || subscribedRooms.has(roomId)) return;
    subscribedRooms.add(roomId);
    const ch = pusherClient.subscribe('private-chat.' + roomId);
    ch.bind('message.sent', (data) => {
      data = normalizeChatData(data);
      // 내가 보낸 메시지는 send() 에서 이미 appendMessage 했으므로 중복 방지
      if (data.user_id === AUTH_USER_ID) return;

      const panelOpen   = document.getElementById('chatPanel').classList.contains('open');
      const isActiveRoom = data.room_id === currentRoomId && panelOpen;

      if (isActiveRoom) {
        // 현재 열려있는 방 → 바로 표시
        appendMessage(data);
        scrollBottom();
        markRead(roomId);
      } else {
        // 다른 방 또는 패널이 닫혀있을 때 → 토스트 알림
        loadRooms();
        showChatToast(data, roomId);
      }
    });

    // 상대가 고치거나 지운 것 — 이미 그려진 말풍선만 바꾼다. 알림은 띄우지 않는다.
    ch.bind('message.changed', (data) => {
      if (data.action === 'edited') applyEdited(data.id, data.body);
      else if (data.action === 'deleted') applyDeleted(data.id);
    });
  }

  // ── 채팅 전용 토스트 ─────────────────────────────────────────
  function showChatToast(data, roomId) {
    data = normalizeChatData(data);
    const container = document.getElementById('toastContainer');

    // 방 이름 찾기 (캐시 우선, 없으면 DOM)
    const cached   = window._chatRoomCache?.[roomId];
    const roomEl   = document.getElementById('room-item-' + roomId);
    const roomName = cached?.name
      || roomEl?.querySelector('.chat-room-name')?.textContent?.trim()
      || '채팅';

    // 메시지 미리보기
    let preview = data.body || '';
    if (!preview && data.attachment_name) preview = '📎 ' + data.attachment_name;
    if (!preview && data.is_image)        preview = '🖼️ 이미지';

    const initials = (data.user_name || '?').charAt(0).toUpperCase();
    const now      = new Date().toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });

    const toast = document.createElement('div');
    toast.className = 'chat-toast';
    toast.innerHTML = `
      <div class="chat-toast-avatar">${initials}</div>
      <div class="chat-toast-body">
        <div class="chat-toast-header">
          <span class="chat-toast-name">${escHtml(data.user_name)}</span>
          <span class="chat-toast-icon"><i class="fa-solid fa-comments"></i></span>
          <span class="chat-toast-room">${escHtml(roomName)}</span>
        </div>
        <div class="chat-toast-msg">${escHtml(preview)}</div>
        <div class="chat-toast-time">${now}</div>
      </div>
      <button class="chat-toast-close" onclick="this.closest('.chat-toast').remove()">×</button>
    `;

    // 클릭 시 해당 방 열기
    toast.addEventListener('click', (e) => {
      if (e.target.classList.contains('chat-toast-close')) return;
      toast.remove();
      const panel = document.getElementById('chatPanel');
      if (!panel.classList.contains('open')) {
        ChatPanel.open();
        setTimeout(() => ChatPanel.selectRoom(roomId), 400);
      } else {
        ChatPanel.selectRoom(roomId);
      }
    });

    container.appendChild(toast);

    // 5초 후 자동 제거
    setTimeout(() => {
      if (toast.parentNode) {
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(20px)';
        toast.style.transition = 'opacity .3s, transform .3s';
        setTimeout(() => toast.remove(), 300);
      }
    }, 5000);

    // 헤더 채팅 버튼 + FAB 강조 (펄스)
    const btn = document.getElementById('chatToggleBtn');
    btn.style.animation = 'none'; btn.offsetHeight;
    btn.style.animation = 'chatBtnPulse .6s ease 3';
    const fab = document.getElementById('chatFab');
    if (fab) { fab.classList.remove('pulse'); fab.offsetHeight; fab.classList.add('pulse'); }
  }

  // ── 방 선택 ──────────────────────────────────────────────────
  async function selectRoom(roomId) {
    ensureRoomCategoryVisible(roomId);
    currentRoomId = roomId;
    currentPage   = 1;
    hasMore       = false;

    // 활성 스타일
    document.querySelectorAll('.chat-room-item').forEach(el => el.classList.remove('active'));
    const item = document.getElementById('room-item-' + roomId);
    if (item) item.classList.add('active');

    // 방 메타 업데이트
    const cachedRoom = window._chatRoomCache?.[roomId] || {};
    const room  = cachedRoom.name || document.querySelector(`#room-item-${roomId} .chat-room-name`)?.textContent || '';
    document.getElementById('chatWinTitle').textContent   = room;
    document.getElementById('chatWinMembers').textContent = '';

    // CE샵 고객 정보 배너
    const shopBar = document.getElementById('shopCustomerBar');
    const si = cachedRoom.shop_info;
    if (si && si.name) {
      document.getElementById('shopCustomerName').textContent  = si.name;
      document.getElementById('shopCustomerPhone').textContent = si.phone || '';
      document.getElementById('shopCustomerEmail').textContent = si.email || '';
      const link    = document.getElementById('shopCustomerPatientLink');
      const noMatch = document.getElementById('shopCustomerNoPatient');
      if (si.patient_id) {
        link.href = `{{ url('/patients') }}/${si.patient_id}`;
        link.style.display    = 'inline-block';
        noMatch.style.display = 'none';
      } else {
        link.style.display    = 'none';
        noMatch.style.display = 'inline';
      }
      shopBar.style.display = 'flex';
    } else {
      shopBar.style.display = 'none';
    }

    // 창 표시
    document.getElementById('chatEmptyState').style.display       = 'none';
    const win = document.getElementById('chatActiveWindow');
    win.style.display = 'flex';

    // 메시지 로드
    document.getElementById('chatMessages').innerHTML =
      '<div style="text-align:center;padding:24px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i></div>';

    const res  = await fetch(`${BASE_URL}/chat/rooms/${roomId}/messages?page=1`, {
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    const data = await res.json();
    hasMore    = data.has_more;

    const msgEl = document.getElementById('chatMessages');
    msgEl.innerHTML = '';

    if (hasMore) {
      msgEl.insertAdjacentHTML('afterbegin',
        `<div style="text-align:center;margin-bottom:8px;">
           <button onclick="ChatPanel.loadMore()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 12px;font-size:11px;cursor:pointer;color:var(--text-secondary);">이전 메시지</button>
         </div>`
      );
    }

    data.messages.forEach(m => appendMessage(m));
    scrollBottom();
    subscribeRoom(roomId);
    markRead(roomId);

    // 방 배지 제거 + 전체 미읽음 배지 갱신
    const badge = item?.querySelector('.chat-room-badge');
    if (badge) badge.remove();
    if (window._chatRoomCache?.[roomId]) window._chatRoomCache[roomId].unread = 0;
    updateUnreadBadges();
  }

  // ── 이전 메시지 더 불러오기 ──────────────────────────────────
  async function loadMore() {
    currentPage++;
    const res  = await fetch(`${BASE_URL}/chat/rooms/${currentRoomId}/messages?page=${currentPage}`, {
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    const data = await res.json();
    hasMore    = data.has_more;

    const msgEl  = document.getElementById('chatMessages');
    const anchor = msgEl.firstElementChild;
    const frag   = document.createDocumentFragment();

    data.messages.forEach(m => {
      const div = buildMessageEl(m);
      frag.appendChild(div);
    });

    if (!data.has_more) {
      anchor.remove();
    }
    msgEl.insertBefore(frag, anchor ? anchor.nextSibling : msgEl.firstChild);
  }

  // ── 메시지 렌더링 ────────────────────────────────────────────
  function buildMessageEl(m) {
    m = normalizeChatData(m);
    const mine    = m.user_id === AUTH_USER_ID;
    const initials = m.user_name.charAt(0);
    let bodyHtml  = '';

    if (m.attachment_path && m.is_image) {
      const url = BASE_URL + '/storage/' + m.attachment_path;
      bodyHtml = `<img class="msg-img" src="${url}" alt="${escHtml(m.attachment_name)}"
                       onclick="ChatPanel.lightbox('${url}')">`;
    } else if (m.attachment_path) {
      const url  = BASE_URL + '/storage/' + m.attachment_path;
      const size = m.attachment_size ? formatBytes(m.attachment_size) : '';
      bodyHtml = `<div class="msg-file">
        <i class="fa-solid fa-file-arrow-down" style="font-size:20px;"></i>
        <div class="msg-file-info">
          <div class="msg-file-name">${escHtml(m.attachment_name)}</div>
          ${size ? `<div class="msg-file-size">${size}</div>` : ''}
        </div>
        <a href="${url}" download="${escHtml(m.attachment_name)}" style="margin-left:auto;">
          <i class="fa-solid fa-download" style="font-size:13px;color:inherit;"></i>
        </a>
      </div>`;
    }

    if (m.body) {
      bodyHtml = `<div class="msg-bubble">${escHtml(m.body).replace(/\n/g,'<br>')}${bodyHtml ? '<br>' + bodyHtml : ''}</div>`;
    } else if (bodyHtml) {
      bodyHtml = `<div class="msg-bubble" style="padding:6px;">${bodyHtml}</div>`;
    }

    // 지운 메시지는 자리만 남긴다 — 답글이 매달려 있으면 지워 버릴 수 없다.
    if (m.is_deleted) {
      bodyHtml = `<div class="msg-deleted"><i class="fa-solid fa-ban"></i> 삭제된 메시지입니다</div>`;
    }

    // 답글이면 원본을 인용해 위에 얹는다
    let quoteHtml = '';
    if (m.reply_to) {
      const q = m.reply_to;
      const qBody = q.is_deleted ? '삭제된 메시지' : (q.body || '📎 첨부파일');
      quoteHtml = `<div class="msg-quote ${q.is_deleted ? 'gone' : ''}" onclick="ChatPanel.jumpTo(${q.id})" title="원본으로 이동">
        <span class="msg-quote-name">${escHtml(q.user_name)}</span>
        <span class="msg-quote-body">${escHtml(qBody)}</span>
      </div>`;
    }

    const editedHtml = (m.edited_at && !m.is_deleted) ? '<span class="msg-edited">(수정됨)</span>' : '';

    // 남의 글엔 답글만, 내 글엔 수정·삭제까지
    let toolsHtml = '';
    if (!m.is_deleted) {
      const reply = `<button class="msg-tool-btn" title="답글" onclick="ChatPanel.startReply(${m.id})"><i class="fa-solid fa-reply"></i></button>`;
      const edit  = `<button class="msg-tool-btn" title="수정" onclick="ChatPanel.editMessage(${m.id})"><i class="fa-solid fa-pen"></i></button>`;
      const del   = `<button class="msg-tool-btn" title="삭제" onclick="ChatPanel.deleteMessage(${m.id})"><i class="fa-solid fa-trash"></i></button>`;
      toolsHtml = `<div class="msg-tools">${reply}${mine ? edit + del : ''}</div>`;
    }

    const row = document.createElement('div');
    row.className = 'msg-row' + (mine ? ' mine' : '') + (m.reply_to_id ? ' is-reply' : '');
    row.dataset.msgId = m.id;
    row.dataset.threadRoot = m.reply_to_id ? '' : m.id;
    row.innerHTML = `
      <div class="msg-avatar ${mine ? 'mine-av' : ''}">${initials}</div>
      <div class="msg-content">
        ${!mine ? `<div class="msg-sender-label">보낸 사람</div><div class="msg-name">${escHtml(m.user_name)}</div>${m.screen_name ? `<div class="msg-screen-name">화면명: ${escHtml(m.screen_name)}</div>` : ''}` : ''}
        ${quoteHtml}
        ${bodyHtml}
        <div class="msg-time">${m.time_label}${editedHtml}</div>
      </div>
      ${toolsHtml}`;
    return row;
  }

  function appendMessage(m) {
    const box = document.getElementById('chatMessages');
    const el  = buildMessageEl(m);

    /* 답글이면 그 대화 묶음을 통째로 맨 아래로 옮긴다 — 서버 정렬(thread_at)과 같은 규칙이다.
       새로고침해야 순서가 맞는 일이 없도록 화면에서도 즉시 맞춘다. */
    if (m.reply_to_id) {
      const rootId = findThreadRoot(m.reply_to_id);
      if (rootId) {
        const group = [...box.querySelectorAll('.msg-row')].filter(r => threadRootOf(r) === rootId);
        group.forEach(r => box.appendChild(r));
      }
    }
    box.appendChild(el);
  }

  /** 이 행이 속한 묶음의 대표 id */
  function threadRootOf(rowEl) {
    const id = Number(rowEl.dataset.msgId);
    const quoted = rowEl.querySelector('.msg-quote');
    if (!quoted) return id;
    const m = quoted.getAttribute('onclick')?.match(/jumpTo\((\d+)\)/);
    return m ? findThreadRoot(Number(m[1])) : id;
  }

  /** 인용을 따라 올라가 원본을 찾는다 (한 단계로 눕히는 서버 규칙과 같다) */
  function findThreadRoot(msgId, depth = 0) {
    if (depth > 20) return msgId;
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    if (!row) return msgId;
    const quoted = row.querySelector('.msg-quote');
    if (!quoted) return msgId;
    const m = quoted.getAttribute('onclick')?.match(/jumpTo\((\d+)\)/);
    return m ? findThreadRoot(Number(m[1]), depth + 1) : msgId;
  }

  function scrollBottom() {
    const box = document.getElementById('chatMessages');
    box.scrollTop = box.scrollHeight;
  }

  // ── 메시지 전송 ──────────────────────────────────────────────
  async function send() {
    if (!currentRoomId) return;
    const input = document.getElementById('chatInput');
    const body  = input.value.trim();
    if (!body && !pasteFile) return;

    const form = new FormData();
    if (body)      form.append('body', body);
    if (pasteFile) form.append('attachment', pasteFile);
    if (replyToId) form.append('reply_to_id', replyToId);

    input.value = '';
    clearPaste();
    cancelReply();

    // X-Socket-Id 포함 시 서버의 broadcast()->toOthers() 가 발신자 제외
    const socketId = pusherClient?.connection?.socket_id;
    const headers  = { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' };
    if (socketId) headers['X-Socket-Id'] = socketId;

    const res  = await fetch(`${BASE_URL}/chat/rooms/${currentRoomId}/messages`, {
      method: 'POST',
      headers,
      body: form,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      showToast('전송 실패: ' + (err.message || err.error || `HTTP ${res.status}`), 'danger');
      return;
    }
    const msg = await res.json();
    appendMessage(msg);
    scrollBottom();

    // 방 목록 프리뷰 업데이트
    const preview = document.querySelector(`#room-item-${currentRoomId} .chat-room-preview`);
    if (preview) preview.textContent = body || '📎 ' + (pasteFile?.name || '파일');
  }

  // ── 답글 ─────────────────────────────────────────────────────
  function startReply(msgId) {
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    if (!row) return;
    replyToId = msgId;

    const name = row.querySelector('.msg-name')?.textContent?.trim() || '나';
    const body = row.querySelector('.msg-bubble')?.textContent?.trim()
              || row.querySelector('.msg-file-name')?.textContent?.trim()
              || (row.querySelector('.msg-img') ? '🖼️ 이미지' : '');

    document.getElementById('chatReplyBarText').innerHTML =
      `<b>${escHtml(name)}</b> 에게 답글<span>${escHtml(body)}</span>`;
    document.getElementById('chatReplyBar').classList.add('show');
    document.getElementById('chatInput').focus();
  }

  function cancelReply() {
    replyToId = null;
    document.getElementById('chatReplyBar').classList.remove('show');
  }

  /** 인용을 눌렀을 때 원본으로 스크롤 + 잠깐 강조 */
  function jumpTo(msgId) {
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    if (!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const bubble = row.querySelector('.msg-bubble, .msg-deleted');
    if (!bubble) return;
    const before = bubble.style.boxShadow;
    bubble.style.boxShadow = '0 0 0 3px var(--primary-200)';
    setTimeout(() => { bubble.style.boxShadow = before; }, 1200);
  }

  // ── 수정 · 삭제 ──────────────────────────────────────────────
  async function editMessage(msgId) {
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    const bubble = row?.querySelector('.msg-bubble');
    if (!bubble) return;

    const current = bubble.innerText.trim();
    const next = await cePrompt('메시지 수정', { value: current, multiline: true, confirmText: '저장' });
    if (next === null) return;
    const body = String(next).trim();
    if (!body || body === current) return;

    const res = await fetch(`${BASE_URL}/chat/messages/${msgId}`, {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ body }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      showToast('수정 실패: ' + (err.message || `HTTP ${res.status}`), 'danger');
      return;
    }
    const data = await res.json();
    applyEdited(msgId, data.body);
  }

  async function deleteMessage(msgId) {
    const ok = await ceConfirm('이 메시지를 지웁니다.\n답글이 달려 있으면 자리는 남고 내용만 사라집니다.',
      { title: '메시지 삭제', confirmText: '삭제', tone: 'danger' });
    if (!ok) return;

    const res = await fetch(`${BASE_URL}/chat/messages/${msgId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      showToast('삭제 실패: ' + (err.message || `HTTP ${res.status}`), 'danger');
      return;
    }
    applyDeleted(msgId);
  }

  /** 화면에 이미 그려진 말풍선을 제자리에서 바꾼다 (내가 했을 때도, 상대가 했을 때도) */
  function applyEdited(msgId, body) {
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    if (!row) return;
    const bubble = row.querySelector('.msg-bubble');
    if (bubble) bubble.innerHTML = escHtml(body).replace(/\n/g, '<br>');
    const time = row.querySelector('.msg-time');
    if (time && !time.querySelector('.msg-edited')) {
      time.insertAdjacentHTML('beforeend', '<span class="msg-edited">(수정됨)</span>');
    }
    // 이 메시지를 인용한 답글의 미리보기도 함께 고친다
    document.querySelectorAll(`.msg-quote[onclick*="jumpTo(${msgId})"] .msg-quote-body`)
      .forEach(el => { el.textContent = body; });
  }

  function applyDeleted(msgId) {
    const row = document.querySelector(`.msg-row[data-msg-id="${msgId}"]`);
    if (!row) return;
    const content = row.querySelector('.msg-content');
    const quote   = content.querySelector('.msg-quote');
    const time    = content.querySelector('.msg-time');
    content.querySelectorAll('.msg-bubble').forEach(el => el.remove());
    const holder = document.createElement('div');
    holder.className = 'msg-deleted';
    holder.innerHTML = '<i class="fa-solid fa-ban"></i> 삭제된 메시지입니다';
    content.insertBefore(holder, time);
    if (quote) quote.remove();
    row.querySelector('.msg-tools')?.remove();
    if (time) time.querySelector('.msg-edited')?.remove();

    document.querySelectorAll(`.msg-quote[onclick*="jumpTo(${msgId})"]`).forEach(q => {
      q.classList.add('gone');
      q.querySelector('.msg-quote-body').textContent = '삭제된 메시지';
    });
    if (replyToId === msgId) cancelReply();
  }

  // ── 읽음 처리 ────────────────────────────────────────────────
  function markRead(roomId) {
    fetch(`${BASE_URL}/chat/rooms/${roomId}/read`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
  }

  // ── 파일 붙여넣기 처리 ───────────────────────────────────────
  function handlePaste(file) {
    pasteFile = file;
    const preview = document.getElementById('chatPastePreview');
    preview.classList.add('show');
    document.getElementById('chatPasteFileName').textContent = file.name || '이미지';

    if (file.type.startsWith('image/')) {
      const thumb = document.getElementById('chatPasteThumb');
      thumb.src = URL.createObjectURL(file);
      thumb.style.display = '';
      document.getElementById('chatPasteFileIcon').style.display = 'none';
    } else {
      document.getElementById('chatPasteThumb').style.display = 'none';
      document.getElementById('chatPasteFileIcon').style.display = '';
    }
  }

  function clearPaste() {
    pasteFile = null;
    document.getElementById('chatPastePreview').classList.remove('show');
    document.getElementById('chatPasteThumb').src = '';
    document.getElementById('chatFileInput').value = '';
  }

  // ── 새 채팅방 모달 ───────────────────────────────────────────
  async function openNewRoom() {
    const listEl = document.getElementById('chatUserList');
    listEl.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    document.getElementById('chatNewRoomModal').classList.add('show');

    // 항상 최신 사용자 목록을 서버에서 가져옴
    try {
      const res   = await fetch(`${BASE_URL}/chat/rooms`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' } });
      const data  = await res.json();
      const users = data.users || [];
      window._chatUsers = users;
      listEl.innerHTML = users.length
        ? users.map(u => `
          <div class="chat-user-check">
            <input type="checkbox" id="cu-${u.id}" value="${u.id}">
            <label for="cu-${u.id}">${escHtml(u.name)} <span style="color:var(--text-muted);font-size:11px;">(${u.role})</span></label>
          </div>`).join('')
        : '<div style="padding:10px;font-size:12px;color:var(--text-muted);">대화 가능한 상대가 없습니다.</div>';
    } catch(e) {
      listEl.innerHTML = '<div style="padding:10px;font-size:12px;color:var(--danger);">목록을 불러오지 못했습니다.</div>';
    }

    // 유형 변경
    document.querySelectorAll('[name=chatRoomType]').forEach(r => {
      r.onchange = () => {
        document.getElementById('chatGroupNameWrap').style.display =
          r.value === 'group' ? '' : 'none';
      };
    });
    // 1:1 기본 선택 초기화
    const directRadio = document.querySelector('[name=chatRoomType][value=direct]');
    if (directRadio) { directRadio.checked = true; }
    document.getElementById('chatGroupNameWrap').style.display = 'none';
    document.getElementById('chatGroupName').value = '';
  }

  function closeNewRoom() {
    document.getElementById('chatNewRoomModal').classList.remove('show');
  }

  async function createRoom() {
    const type    = document.querySelector('[name=chatRoomType]:checked').value;
    const name    = document.getElementById('chatGroupName').value.trim();
    const checked = [...document.querySelectorAll('#chatUserList input:checked')];

    if (!checked.length) { showToast('대화 상대를 선택하세요.', 'warning'); return; }
    if (type === 'group' && !name) { showToast('그룹 이름을 입력하세요.', 'warning'); return; }
    if (type === 'direct' && checked.length > 1) { showToast('1:1 채팅은 상대방을 한 명만 선택하세요.', 'warning'); return; }

    const startBtn = document.querySelector('.chat-modal-actions .btn-primary');
    if (startBtn) { startBtn.disabled = true; startBtn.textContent = '생성 중...'; }

    try {
      const userIds = checked.map(c => parseInt(c.value));
      const res = await fetch(`${BASE_URL}/chat/rooms`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ type, name, user_ids: userIds }),
      });
      const data = await res.json();
      if (!res.ok) {
        showToast(data.message || '채팅방 생성에 실패했습니다.', 'danger');
        return;
      }
      closeNewRoom();
      await loadRooms();
      selectRoom(data.room_id);
    } catch(e) {
      showToast('오류가 발생했습니다. 다시 시도해주세요.', 'danger');
    } finally {
      if (startBtn) { startBtn.disabled = false; startBtn.textContent = '시작'; }
    }
  }

  // ── 라이트박스 ───────────────────────────────────────────────
  function lightbox(url) {
    document.getElementById('chatLightboxImg').src = url;
    document.getElementById('chatLightbox').classList.add('show');
  }

  // ── 유틸 ─────────────────────────────────────────────────────
  function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
              .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + 'B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + 'KB';
    return (bytes/1048576).toFixed(1) + 'MB';
  }

  // ── 페이지 로드 시 백그라운드 자동 구독 ──────────────────────
  async function initBackground() {
    if (!pusherClient) return;
    try {
      const res  = await fetch(BASE_URL + '/chat/rooms', {
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
      });
      if (!res.ok) return;
      const data = await res.json();

      // 방 캐시 저장 (토스트에서 방 이름 참조용)
      window._chatRoomCache = {};
      (data.rooms || []).forEach(r => {
        window._chatRoomCache[r.id] = r;
        subscribeRoom(r.id);
      });
      window._chatUsers = data.users || [];

      // 미읽음 배지 표시 (헤더 + FAB)
      const totalUnread = (data.rooms || []).reduce((s, r) => s + (r.unread || 0), 0);
      document.getElementById('chatUnreadDot').style.display = totalUnread > 0 ? '' : 'none';
      const fab = document.getElementById('chatFabBadge');
      if (fab) {
        if (totalUnread > 0) { fab.textContent = totalUnread > 99 ? '99+' : totalUnread; fab.style.display = 'flex'; }
        else { fab.style.display = 'none'; }
      }
    } catch (e) {
      console.warn('[Chat] 백그라운드 초기화 실패:', e);
    }
  }

  // ── CE샵 public 채널 구독 (새 룸 자동 감지) ─────────────────
  function subscribeCeShopChannel() {
    if (!pusherClient) return;
    const ch = pusherClient.subscribe('ce-shop');
    ch.bind('message.new', async (data) => {
      data = normalizeChatData(data);
      const roomId = data.room_id;
      // 이미 private 채널 구독 중이면 subscribeRoom 핸들러가 처리 → 중복 방지
      if (subscribedRooms.has(roomId)) return;
      // 새 룸: 방 목록 갱신 후 구독
      await initBackground();
      // 메시지 표시 / 토스트
      const panelOpen = document.getElementById('chatPanel').classList.contains('open');
      if (panelOpen && roomId === currentRoomId) {
        appendMessage(data);
        scrollBottom();
        markRead(roomId);
      } else {
        showChatToast(data, roomId);
      }
    });
  }

  // ── 이벤트 바인딩 ────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    // 페이지 로드 즉시 Pusher 구독 시작
    initBackground();
    subscribeCeShopChannel();

    const input = document.getElementById('chatInput');

    // Enter 전송, Shift+Enter 줄바꿈
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });

    // 자동 높이 조절
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    // 파일 붙여넣기 (Ctrl+V)
    input.addEventListener('paste', (e) => {
      const items = e.clipboardData?.items;
      if (!items) return;
      for (const item of items) {
        if (item.kind === 'file') {
          e.preventDefault();
          handlePaste(item.getAsFile());
          return;
        }
      }
    });

    // 전역 붙여넣기 (패널이 열려있을 때)
    document.addEventListener('paste', (e) => {
      if (!document.getElementById('chatPanel').classList.contains('open')) return;
      if (document.activeElement === input) return; // input의 paste로 처리됨
      const items = e.clipboardData?.items;
      if (!items) return;
      for (const item of items) {
        if (item.kind === 'file') {
          handlePaste(item.getAsFile());
          return;
        }
      }
    });

    // 파일 선택
    document.getElementById('chatFileInput').addEventListener('change', (e) => {
      const file = e.target.files?.[0];
      if (file) handlePaste(file);
    });
  });

  // 헤더 드래그는 패널이 DOM 에 있어야 걸 수 있다
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDrag);
  else initDrag();

  return { toggle, open, close, loadRooms, selectRoom, loadMore, send, clearPaste, openNewRoom, closeNewRoom, createRoom, lightbox, setCategory,
           startReply, cancelReply, jumpTo, editMessage, deleteMessage, resetPosition };
})();
</script>

{{-- ── 위임동의 실시간 알림 ──────────────────────────────────────── --}}
<style>
.consent-notif {
  position:fixed; top:72px; right:20px; z-index:9999;
  background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.18);
  border-left:5px solid var(--success); min-width:320px; max-width:380px;
  padding:14px 16px; animation:slideInRight .3s ease;
  display:flex; flex-direction:column; gap:6px;
}
.consent-notif.declined { border-left-color:var(--danger); }
@keyframes slideInRight {
  from { opacity:0; transform:translateX(40px); }
  to   { opacity:1; transform:translateX(0); }
}
.consent-notif-title { font-size:13px; font-weight:700; display:flex; align-items:center; gap:6px; }
.consent-notif-body  { font-size:12px; color:var(--text-secondary); line-height:1.5; }
.consent-notif-actions { display:flex; align-items:center; justify-content:space-between; margin-top:2px; }
.consent-notif-progress { height:3px; background:var(--border); border-radius:999px; overflow:hidden; }
.consent-notif-progress-bar { height:100%; background:var(--success); border-radius:999px; width:100%; transition:width linear; }
.consent-notif.declined .consent-notif-progress-bar { background:var(--danger); }
</style>
<script>
// ── 위임동의 실시간 알림 ─────────────────────────────────────
(function () {
  if (!pusherClient) return;

  /* 알림은 맨 바깥 창에서만 띄운다.
     화면은 탭(iframe) 안에서 열리고 이 스크립트는 껍데기와 탭마다 한 벌씩 돈다 —
     그래서 서버가 한 번 보낸 알림이 열려 있는 탭 수만큼 겹쳐 떴다(탭 하나면 두 장).
     띄우는 일은 껍데기가 맡고, 탭 안에서는 화면을 고치는 데 쓰는 이벤트만 흘린다. */
  const IS_FRAMED = window.self !== window.top;

  const DURATION = 10000; // 10초

  function showConsentNotif(data) {
    const isAgreed = data.status === 'agreed';
    const el = document.createElement('div');
    el.className = 'consent-notif' + (isAgreed ? '' : ' declined');

    const rxUrl = BASE_URL + '/prescriptions/' + encodeURIComponent(data.rx_number || '');

    el.innerHTML = `
      <div class="consent-notif-title">
        <i class="fa-solid fa-${isAgreed ? 'circle-check' : 'circle-xmark'}"
           style="color:var(--${isAgreed ? 'success' : 'danger'});font-size:16px;"></i>
        위임동의 ${isAgreed ? '서명 완료' : '거절'}
      </div>
      <div class="consent-notif-body">
        <b>${escHtml(data.patient_name ?? '환자')}</b>님
        ${isAgreed ? '이 건강보험 급여 위임동의에 <b>서명</b>하였습니다.' : '이 위임동의를 <b>거절</b>하였습니다.'}
        <br><span style="color:var(--text-muted);font-size:11px;">${escHtml(data.rx_number ?? '')}${data.responded_at ? ' · ' + data.responded_at : ''}</span>
      </div>
      <div class="consent-notif-actions">
        <a href="${rxUrl}" data-ce-tab="주문 - ${escHtml(data.rx_number ?? '신규')}" data-ce-icon="bx-scan"
           style="font-size:12px;font-weight:500;color:var(--primary);text-decoration:none;">
          <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;"></i> 처방전 확인
        </a>
        <button onclick="this.closest('.consent-notif').remove()"
                style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;padding:0 2px;">&times;</button>
      </div>
      <div class="consent-notif-progress">
        <div class="consent-notif-progress-bar" id="cnpb-${Date.now()}"></div>
      </div>
    `;

    document.body.appendChild(el);

    // 프로그레스 바 감소 애니메이션
    const bar = el.querySelector('.consent-notif-progress-bar');
    requestAnimationFrame(() => {
      bar.style.transition = `width ${DURATION}ms linear`;
      bar.style.width = '0%';
    });

    // 자동 제거
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(() => el.remove(), 400); }, DURATION);
  }

  function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  const adminCh = pusherClient.subscribe('private-admin');
  adminCh.bind('consent.submitted', function (data) {
    if (!IS_FRAMED) showConsentNotif(data);
    /* 이 이벤트는 탭 안에서도 흘려야 한다 — 주문 화면이 이것을 받아
       위임동의 단추와 생성 서류를 그 자리에서 고친다. */
    window.dispatchEvent(new CustomEvent('ce:consentResult', { detail: data }));
  });
  adminCh.bind('prescription.uploaded', function (data) {
    if (IS_FRAMED) return;
    showPrescriptionNotif(data);
  });

  /* 창고에서 상태가 바뀌면 토스트로 알린다.
     웹훅은 사람이 보고 있지 않을 때 들어온다 — 목록을 새로 불러야 알게 되면
     출고나 취소처럼 곧 손을 써야 하는 일이 늦어진다.
     같은 사건이 두 번 방송되어도 토스트가 겹치지 않게 잠깐 기억해 둔다. */
  const wwSeen = new Map();
  adminCh.bind('withworks.status', function (data) {
    if (!data || IS_FRAMED) return;

    const key = (data.event || '') + '|' + (data.body || '');
    const now = Date.now();
    if (wwSeen.get(key) > now - 5000) return;
    wwSeen.set(key, now);

    const msg = data.url
      ? `<b>${escHtml(data.title)}</b><br>` +
        `<a href="${escHtml(data.url)}" style="color:inherit;text-decoration:underline;">` +
        `${escHtml(data.body)}</a>`
      : `<b>${escHtml(data.title)}</b><br>${escHtml(data.body)}`;

    showToast(msg, data.tone || 'info', 8000);
  });
})();
</script>

{{-- ── 처방전 업로드 실시간 알림 ────────────────────────────────────── --}}
<style>
#rxNotifContainer {
  position: fixed; top: 72px; right: 20px; z-index: 9998;
  display: flex; flex-direction: column; gap: 10px;
  max-width: 380px; pointer-events: none;
}
.rx-notif {
  background: #fff; border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,.18);
  border-left: 5px solid var(--primary);
  padding: 14px 16px;
  animation: slideInRight .3s ease;
  display: flex; flex-direction: column; gap: 6px;
  pointer-events: all;
}
.rx-notif-title { font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
.rx-notif-body  { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
.rx-notif-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 2px; }
.rx-notif-progress { height: 3px; background: var(--border); border-radius: 999px; overflow: hidden; }
.rx-notif-progress-bar { height: 100%; background: var(--primary); border-radius: 999px; width: 100%; }
</style>
<div id="rxNotifContainer"></div>
<script>
function showPrescriptionNotif(data) {
  if (!data) return;
  const DURATION = 12000;

  function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  const rxUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/prescriptions/' + encodeURIComponent(data.rx_number || '');

  const el = document.createElement('div');
  el.className = 'rx-notif';
  el.innerHTML = `
    <div class="rx-notif-title">
      <i class="fa-solid fa-file-medical" style="color:var(--primary);font-size:15px;"></i>
      새 처방전 업로드
    </div>
    <div class="rx-notif-body">
      <b>${escHtml(data.uploader_name)}</b>님이 처방전을 업로드했습니다.<br>
      <span style="color:var(--text-muted);font-size:11px;">
        ${escHtml(data.patient_name ?? '')}${data.hospital_name ? ' · ' + escHtml(data.hospital_name) : ''}
        · ${escHtml(data.rx_number ?? '')} · ${escHtml(data.uploaded_at ?? '')}
      </span>
    </div>
    <div class="rx-notif-actions">
      <a href="${rxUrl}" data-ce-tab="주문 - ${escHtml(data.rx_number ?? '신규')}" data-ce-icon="bx-scan"
         style="font-size:12px;font-weight:500;color:var(--primary);text-decoration:none;">
        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;"></i> 처방전 확인
      </a>
      <button onclick="this.closest('.rx-notif').remove()"
              style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;padding:0 2px;">&times;</button>
    </div>
    <div class="rx-notif-progress">
      <div class="rx-notif-progress-bar"></div>
    </div>
  `;

  const container = document.getElementById('rxNotifContainer');
  container.appendChild(el);

  // 프로그레스 바 감소 애니메이션
  const bar = el.querySelector('.rx-notif-progress-bar');
  requestAnimationFrame(() => {
    bar.style.transition = `width ${DURATION}ms linear`;
    bar.style.width = '0%';
  });

  // 자동 제거
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = 'opacity .4s';
    setTimeout(() => el.remove(), 400);
  }, DURATION);
}
</script>

{{-- ═══════════════════════════════════════════════════════════
     공지사항 · 문의하기 슬라이드 패널
════════════════════════════════════════════════════════════ --}}
<style>
/* ── 공통 사이드 패널 ────────────────────────────────────────── */
.side-panel {
  position: fixed; top: 0; right: -500px; width: 500px; height: 100vh;
  background: #fff; border-left: 1px solid var(--border);
  display: flex; flex-direction: column; z-index: 1000;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
.side-panel.open { right: 0; box-shadow: -4px 0 32px rgba(0,0,0,.15); }

#sidePanelOverlay {
  display: none; position: fixed; inset: 0; z-index: 999;
  background: rgba(0,0,0,.2);
}
#sidePanelOverlay.show { display: block; }

/* 옆 패널 머리도 채팅·SR·도움말과 같은 12/16 · gap 8 로 둔다 */
.sp-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  background: var(--gray-1000); color: #fff; flex-shrink: 0;
}
.sp-title { font-size: 14px; font-weight: 700; flex: 1; }
.sp-close, .sp-back {
  background: none; border: none; color: var(--gray-400);
  font-size: 16px; cursor: pointer; padding: 0 8px; min-width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; gap: 4px; line-height: 1;
}
.sp-close:hover, .sp-back:hover { color: #fff; background: rgba(255,255,255,.1); }
.sp-back { font-size: 13px; }

.sp-body { flex: 1; overflow-y: auto; }

.sp-loading {
  display: flex; align-items: center; justify-content: center;
  height: 200px; color: var(--text-muted); font-size: 14px; gap: 8px;
}
.sp-empty {
  padding: 48px 24px; text-align: center; color: var(--text-muted);
}
.sp-empty i { font-size: 32px; opacity: .3; display: block; margin-bottom: 10px; }

/* ── 공지 목록 ── */
.notice-item {
  padding: 14px 18px; border-bottom: 1px solid var(--border-light);
  cursor: pointer; transition: background .14s;
}
.notice-item:hover { background: var(--bg); }
.notice-item.pinned { background: #fffbf0; }
.notice-item.pinned:hover { background: #fff3d6; }
.ni-title {
  font-size: 14px; font-weight: 700; color: var(--text-primary);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-bottom: 5px; display: flex; align-items: center; gap: 5px;
}
.ni-unread-dot {
  display: inline-block; flex-shrink: 0;
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--primary);
}
.ni-meta { display: flex; gap: 10px; font-size: 11px; color: var(--text-muted); }

/* ── 공지 상세 ── */
.nd-header { padding: 18px 18px 14px; border-bottom: 1px solid var(--border); }
.nd-title { font-size: 16px; font-weight: 700; line-height: 1.5; margin-bottom: 10px; }
.nd-meta { display: flex; gap: 14px; font-size: 12px; color: var(--text-muted); flex-wrap: wrap; }
.nd-content { padding: 20px 18px; font-size: 14px; line-height: 1.85; color: var(--text-primary); white-space: pre-wrap; word-break: break-word; }
.nd-nav { border-top: 2px solid var(--border); }
.nd-nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 18px; border-bottom: 1px solid var(--border-light);
  cursor: pointer; transition: background .14s;
}
.nd-nav-item:hover { background: var(--bg); }
.nd-nav-label { font-size: 10px; font-weight: 700; color: var(--text-muted); width: 28px; flex-shrink: 0; }
.nd-nav-title { font-size: 13px; flex: 1; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nd-nav-date  { font-size: 11px; color: var(--text-muted); flex-shrink: 0; }

/* ── 문의 목록 ── */
.inq-item {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 18px; border-bottom: 1px solid var(--border-light);
  cursor: pointer; transition: background .14s;
}
.inq-item:hover { background: var(--bg); }
.inq-item-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  background: var(--warning);
}
.inq-item-dot.answered { background: var(--success); }
.inq-info { flex: 1; min-width: 0; }
.inq-title { font-size: 13px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inq-meta  { font-size: 11px; color: var(--text-muted); margin-top: 2px; display: flex; gap: 8px; }

/* ── 문의 스레드 ── */
.inq-thread { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.inq-thread-info {
  padding: 12px 16px; background: var(--gray-50); border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.inq-thread-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inq-thread-meta  { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

.inq-thread-msgs {
  flex: 1; overflow-y: auto; padding: 16px 14px;
  display: flex; flex-direction: column; gap: 12px;
  background: #fff;
}

/* 메시지 버블 */
.inq-msg { display: flex; gap: 8px; max-width: 100%; }
.inq-msg.mine { flex-direction: row-reverse; }
.inq-msg-av {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: #fff; align-self: flex-end;
}
.inq-msg-av.admin-av  { background: var(--primary); }
.inq-msg-av.user-av   { background: var(--gray-600); }
.inq-msg-av.mine-av   { background: var(--primary); }
.inq-msg-body { max-width: 80%; }
.inq-msg-name { font-size: 10px; color: var(--text-muted); margin-bottom: 3px; }
.inq-msg.mine .inq-msg-name { text-align: right; }
.inq-msg-bubble {
  padding: 10px 14px; border-radius: 12px;
  font-size: 13px; line-height: 1.7; word-break: break-word;
  background: var(--gray-100); color: var(--text-primary);
  border-bottom-left-radius: 4px; white-space: pre-wrap;
}
.inq-msg.mine .inq-msg-bubble {
  background: var(--primary); color: #fff;
  border-bottom-right-radius: 4px; border-bottom-left-radius: 14px;
}
.inq-msg-time { font-size: 10px; color: var(--text-muted); margin-top: 3px; }
.inq-msg.mine .inq-msg-time { text-align: right; }
.inq-msg-img { max-width: 200px; max-height: 180px; border-radius: 8px; cursor: zoom-in; object-fit: cover; display: block; }
.inq-msg-file {
  display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  border-radius: 8px; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.3);
}
.inq-msg:not(.mine) .inq-msg-file { background: var(--gray-50); border-color: var(--border); }
.inq-msg-file-name { font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }

/* 붙여넣기 미리보기 */
.inq-paste-preview {
  display: none; flex-shrink: 0; padding: 8px 14px;
  border-top: 1px solid var(--border); background: #f0f9ff;
  align-items: center; gap: 8px;
}
.inq-paste-preview.show { display: flex; }
.inq-paste-img { max-height: 56px; max-width: 80px; border-radius: 6px; object-fit: cover; }
.inq-paste-name { font-size: 12px; font-weight: 500; flex: 1; color: var(--text-primary); }
.inq-paste-clear { background: none; border: none; color: var(--danger); font-size: 18px; cursor: pointer; padding: 2px 6px; border-radius: 4px; line-height: 1; }
.inq-paste-clear:hover { background: var(--danger-light); }

/* 스레드 입력 영역 */
.inq-thread-input {
  padding: 10px 14px; border-top: 2px solid var(--border);
  background: var(--gray-50); flex-shrink: 0;
  display: flex; gap: 8px; align-items: flex-end;
}
#inqThreadInput {
  flex: 1; resize: none; border: 1px solid var(--border); border-radius: 8px;
  padding: 9px 12px; font-size: 13px; line-height: 1.6; font-family: inherit;
  min-height: 40px; max-height: 120px; outline: none; background: #fff;
}
#inqThreadInput:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(40,121,139,.12); }

/* ── 문의 작성 폼 ── */
.sp-form { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.sp-form .form-control { font-size: 13px; }
.sp-form-actions { display: flex; justify-content: flex-end; gap: 8px; }

/* ── 검색/필터 바 ── */
.sp-toolbar {
  padding: 10px 14px; border-bottom: 1px solid var(--border);
  display: flex; gap: 8px; align-items: center; flex-shrink: 0;
  background: var(--gray-50);
}
.sp-toolbar .form-control { font-size: 12px; }

@media (max-width: 540px) {
  .side-panel { width: 100%; right: -100%; }
}
</style>

{{-- 공통 오버레이 --}}
<div id="sidePanelOverlay" onclick="SidePanels.closeAll()"></div>

{{-- ── 공지사항 패널 ── --}}
<div id="noticePanel" class="side-panel">
  <div class="sp-header">
    <button class="sp-back" id="noticePanelBack" style="display:none;" onclick="NoticePanel.back()">
      <i class="fa-solid fa-chevron-left"></i> 목록
    </button>
    <i class="fa-solid fa-bullhorn" style="font-size:14px;color:#4898A9;"></i>
    <span class="sp-title" id="noticePanelTitle">공지사항</span>
    @if(Auth::user()->role === 'admin')
      <a href="{{ route('notices.create') }}" class="sp-back" title="공지 등록">
        <i class="fa-solid fa-plus"></i>
      </a>
    @endif
    <button class="sp-close" onclick="NoticePanel.close()">×</button>
  </div>
  <div class="sp-body" id="noticePanelBody">
    <div class="sp-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>
  </div>
</div>

{{-- ── 문의하기 패널 ── --}}
<div id="inquiryPanel" class="side-panel">
  <div class="sp-header">
    <button class="sp-back" id="inquiryPanelBack" style="display:none;" onclick="InquiryPanel.back()">
      <i class="fa-solid fa-chevron-left"></i> 목록
    </button>
    <i class="fa-solid fa-headset" style="font-size:14px;color:var(--primary-300);"></i>
    <span class="sp-title" id="inquiryPanelTitle">문의하기</span>
    @if(Auth::user()->role !== 'admin')
    <button class="sp-back" id="inquiryNewBtn" onclick="InquiryPanel.showCreate()" title="새 문의">
      <i class="fa-solid fa-pen"></i>
    </button>
    @else
    <span id="inquiryNewBtn" style="display:none;"></span>
    @endif
    <button class="sp-close" onclick="InquiryPanel.close()">×</button>
  </div>
  <div class="sp-body" id="inquiryPanelBody">
    <div class="sp-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>
  </div>
</div>

<script>
// ── 전역 헬퍼 ──────────────────────────────────────────────────
function _esc(str) {
  if (str == null) return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

// ── 패널 공통 관리 ─────────────────────────────────────────────
const SidePanels = {
  closeAll() {
    document.getElementById('noticePanel').classList.remove('open');
    document.getElementById('inquiryPanel').classList.remove('open');
    document.getElementById('sidePanelOverlay').classList.remove('show');
  },
  openOverlay() {
    document.getElementById('sidePanelOverlay').classList.add('show');
  },
};

// ══════════════════════════════════════════════════════════════
// NoticePanel
// ══════════════════════════════════════════════════════════════
const NoticePanel = (() => {
  // 사이드바 배지 갱신
  function _updateBadge(unreadCount) {
    const el = document.getElementById('noticNavBadge');
    if (!el) return;
    if (unreadCount > 0) { el.textContent = unreadCount; el.style.display = ''; }
    else                 { el.style.display = 'none'; }
  }

  function toggle() {
    document.getElementById('noticePanel').classList.contains('open') ? close() : open();
  }

  function open() {
    SidePanels.closeAll();
    document.getElementById('noticePanel').classList.add('open');
    SidePanels.openOverlay();
    loadList();
  }

  function close() { SidePanels.closeAll(); }

  function back() {
    _setHeader('공지사항', false);
    loadList();
  }

  function _setHeader(title, showBack) {
    document.getElementById('noticePanelTitle').textContent = title;
    document.getElementById('noticePanelBack').style.display = showBack ? '' : 'none';
  }

  function _body() { return document.getElementById('noticePanelBody'); }

  function _loading() {
    _body().innerHTML = '<div class="sp-loading"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';
  }

  // ── 목록 ─────────────────────────────────────────────────────
  function loadList(search) {
    if (search === undefined) search = '';
    _setHeader('공지사항', false);
    _loading();

    const url = BASE_URL + '/panel/notices' + (search ? '?search=' + encodeURIComponent(search) : '');
    fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => _renderList(d, search))
      .catch(() => { _body().innerHTML = '<div class="sp-loading">오류가 발생했습니다.</div>'; });
  }

  function _renderList(data, search) {
    const { notices, unread_count, is_admin } = data;

    // 서버 기준 미읽음 수로 배지 갱신
    _updateBadge(unread_count || 0);

    let html = `
      <div class="sp-toolbar">
        <input type="text" id="noticeSearch" class="form-control" placeholder="제목 검색..." value="${_esc(search)}" style="flex:1;">
        <button onclick="NoticePanel.doSearch()" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        ${is_admin ? `<a href="${BASE_URL}/notices/create" class="btn btn-primary btn-sm" style="white-space:nowrap;"><i class="fa-solid fa-plus"></i> 작성하기</a>` : ''}
      </div>`;

    if (!notices.length) {
      html += `<div class="sp-empty">
        <i class="fa-solid fa-bullhorn"></i>
        ${search ? '검색 결과가 없습니다.' : '등록된 공지사항이 없습니다.'}
      </div>`;
    } else {
      html += notices.map(n => `
        <div class="notice-item ${n.is_pinned ? 'pinned' : ''}" id="ni-${n.id}" style="display:flex;align-items:center;gap:8px;">
          <div style="flex:1;min-width:0;cursor:pointer;" onclick="NoticePanel.showDetail(${n.id})">
            <div class="ni-title">
              ${n.is_pinned ? '<span style="display:inline-block;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:6px;margin-right:6px;vertical-align:middle;">공지</span>' : ''}
              ${!n.is_read ? '<span class="ni-unread-dot"></span>' : ''}
              ${_esc(n.title)}
            </div>
            <div class="ni-meta">
              <span><i class="fa-solid fa-user" style="font-size:10px;margin-right:2px;"></i>${_esc(n.author)}</span>
              <span>${n.date}</span>
              <span><i class="fa-solid fa-eye" style="font-size:10px;margin-right:2px;"></i>${n.views}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;" onclick="event.stopPropagation()">
            <a href="${BASE_URL}/notices/${n.id}" class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 7px;white-space:nowrap;" title="전체 페이지로 보기">
              <i class="fa-solid fa-up-right-from-square"></i> 상세보기
            </a>
          </div>
        </div>`).join('');
    }

    _body().innerHTML = html;
    const inp = document.getElementById('noticeSearch');
    if (inp) inp.addEventListener('keydown', e => { if (e.key === 'Enter') NoticePanel.doSearch(); });
  }

  function doSearch() {
    loadList(document.getElementById('noticeSearch')?.value || '');
  }

  // ── 상세 ─────────────────────────────────────────────────────
  function showDetail(id) {
    _setHeader('공지사항', true);
    _loading();

    fetch(BASE_URL + '/panel/notices/' + id, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => {
        // 서버가 읽음 처리 후 최신 미읽음 수 반환 → 배지 갱신
        _updateBadge(d.unread_count || 0);
        // 목록의 미읽음 점 즉시 제거
        const ni = document.getElementById('ni-' + id);
        if (ni) { const dot = ni.querySelector('.ni-unread-dot'); if (dot) dot.remove(); }
        _renderDetail(d);
      })
      .catch(() => { _body().innerHTML = '<div class="sp-loading">오류가 발생했습니다.</div>'; });
  }

  function _renderDetail({ notice, prev, next, is_admin }) {
    let html = `
      <div class="nd-header">
        <div style="margin-bottom:8px;">
          ${notice.is_pinned ? '<span class="badge badge-danger" style="font-size:10px;">공지</span>' : ''}
        </div>
        <div class="nd-title">${_esc(notice.title)}</div>
        <div class="nd-meta">
          <span><i class="fa-solid fa-user" style="margin-right:3px;font-size:10px;"></i>${_esc(notice.author)}</span>
          <span><i class="fa-solid fa-calendar" style="margin-right:3px;font-size:10px;"></i>${notice.date}</span>
          <span><i class="fa-solid fa-eye" style="margin-right:3px;font-size:10px;"></i>${notice.views}회</span>
        </div>
        ${is_admin ? `<div style="margin-top:10px;">
          <a href="${BASE_URL}/notices/${notice.id}/edit" class="btn btn-outline btn-sm" style="font-size:11px;">
            <i class="fa-solid fa-pen"></i> 수정
          </a>
        </div>` : ''}
      </div>
      <div class="nd-content">${_esc(notice.content)}</div>
      <div class="nd-nav">`;

    if (next) html += `
      <div class="nd-nav-item" onclick="NoticePanel.showDetail(${next.id})">
        <span class="nd-nav-label">다음</span>
        <span class="nd-nav-title">${_esc(next.title)}</span>
        <span class="nd-nav-date">${next.date}</span>
      </div>`;
    if (prev) html += `
      <div class="nd-nav-item" onclick="NoticePanel.showDetail(${prev.id})">
        <span class="nd-nav-label">이전</span>
        <span class="nd-nav-title">${_esc(prev.title)}</span>
        <span class="nd-nav-date">${prev.date}</span>
      </div>`;

    html += `</div>`;
    _body().innerHTML = html;
    _body().scrollTop = 0;
  }

  // 페이지 로드 시 배지 초기화 (패널 열지 않아도 배지 표시)
  function initBadge() {
    fetch(BASE_URL + '/panel/notices', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => _updateBadge(d.unread_count || 0))
      .catch(() => {});
  }

  return { toggle, open, close, back, loadList, doSearch, showDetail, initBadge };
})();

// 페이지 로드 후 공지 배지 초기화
document.addEventListener('DOMContentLoaded', () => NoticePanel.initBadge());

// ── CE샵 주문 배지 ─────────────────────────────────────────────
(function initShopOrderBadge() {
  function refresh() {
    fetch('{{ url("/api/shop-badge") }}', { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => {
        const el = document.getElementById('shopOrderBadge');
        if (!el) return;
        if ((d.count || 0) > 0) { el.textContent = d.count; el.style.display = ''; }
        else                     { el.style.display = 'none'; }
      })
      .catch(() => {});
  }
  document.addEventListener('DOMContentLoaded', refresh);
  setInterval(refresh, 60000);
})();

// ══════════════════════════════════════════════════════════════
// InquiryPanel
// ══════════════════════════════════════════════════════════════
const InquiryPanel = (() => {
  let _currentId  = null;
  let _pasteFile  = null;   // 붙여넣기된 이미지 파일

  // ── 유틸 ──────────────────────────────────────────────────────
  function toggle() {
    document.getElementById('inquiryPanel').classList.contains('open') ? close() : open();
  }
  function open() {
    SidePanels.closeAll();
    document.getElementById('inquiryPanel').classList.add('open');
    SidePanels.openOverlay();
    loadList();
  }
  function close() { SidePanels.closeAll(); }
  function back()  { _setHeader('문의하기', false, true); loadList(); }

  function _setHeader(title, showBack, showNew) {
    document.getElementById('inquiryPanelTitle').textContent   = title;
    document.getElementById('inquiryPanelBack').style.display  = showBack ? '' : 'none';
    document.getElementById('inquiryNewBtn').style.display     = showNew  ? '' : 'none';
  }
  function _body()    { return document.getElementById('inquiryPanelBody'); }
  function _loading() { _body().innerHTML = '<div class="sp-loading"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>'; }

  // FormData 전용 fetch 헬퍼
  async function _fetchForm(url, formData) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
      body: formData,
    });
    return res.json();
  }

  // sp-body flex 오버라이드 해제 (목록/폼 뷰로 전환 시)
  function _resetBodyStyle() {
    const b = _body();
    b.style.overflow = b.style.display = b.style.flexDirection = '';
  }

  // ── 목록 ─────────────────────────────────────────────────────
  function loadList(filter) {
    if (filter === undefined) filter = '';
    _resetBodyStyle();
    _setHeader('문의하기', false, true);
    _loading();
    const url = BASE_URL + '/panel/inquiries' + (filter ? '?status=' + filter : '');
    fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => _renderList(d, filter))
      .catch(() => { _body().innerHTML = '<div class="sp-loading">오류가 발생했습니다.</div>'; });
  }

  function _renderList(data, filter) {
    const { inquiries, pending_count } = data;
    const pc = pending_count > 0
      ? `<span style="background:var(--danger);color:#fff;border-radius:999px;padding:0 5px;font-size:10px;margin-left:4px;">${pending_count}</span>`
      : '';
    let html = `<div class="sp-toolbar" style="gap:6px;flex-wrap:wrap;">
      <button onclick="InquiryPanel.loadList('')" class="btn btn-sm ${!filter ? 'btn-primary' : 'btn-outline'}" style="font-size:11px;">전체</button>
      <button onclick="InquiryPanel.loadList('pending')" class="btn btn-sm ${filter==='pending' ? 'btn-primary' : 'btn-outline'}" style="font-size:11px;">대기중 ${pc}</button>
      <button onclick="InquiryPanel.loadList('answered')" class="btn btn-sm ${filter==='answered' ? 'btn-primary' : 'btn-outline'}" style="font-size:11px;">답변완료</button>
      <a href="${BASE_URL}/inquiries/create" class="btn btn-primary btn-sm" style="font-size:11px;margin-left:auto;white-space:nowrap;"><i class="fa-solid fa-plus"></i> 작성하기</a>
    </div>`;

    if (!inquiries.length) {
      const msg = filter === 'pending' ? '대기 중인 문의가 없습니다.' : filter === 'answered' ? '답변 완료된 문의가 없습니다.' : '등록된 문의가 없습니다.';
      html += `<div class="sp-empty"><i class="fa-solid fa-headset"></i>${msg}</div>`;
    } else {
      html += inquiries.map(i => {
        const badge = i.status === 'answered'
          ? '<span class="badge badge-success" style="font-size:10px;flex-shrink:0;"><i class="fa-solid fa-circle-check"></i> 완료</span>'
          : '<span class="badge badge-warning" style="font-size:10px;flex-shrink:0;">대기중</span>';
        return `<div class="inq-item" style="display:flex;align-items:center;gap:6px;">
          <div class="inq-item-dot ${i.status === 'answered' ? 'answered' : ''}" style="flex-shrink:0;"></div>
          <div class="inq-info" style="flex:1;min-width:0;cursor:pointer;" onclick="InquiryPanel.showDetail(${i.id})">
            <div class="inq-title">${_esc(i.title)}</div>
            <div class="inq-meta">
              <span class="badge badge-secondary" style="font-size:10px;">${_esc(i.category)}</span>
              ${IS_ADMIN ? `<span>${_esc(i.user)}</span>` : ''}
              <span>${i.date}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;align-items:flex-end;" onclick="event.stopPropagation()">
            ${badge}
            <a href="${BASE_URL}/inquiries/${i.id}" class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 7px;white-space:nowrap;" title="전체 페이지로 보기">
              <i class="fa-solid fa-up-right-from-square"></i> 상세보기
            </a>
          </div>
        </div>`;
      }).join('');
    }
    _body().innerHTML = html;
  }

  // ── 스레드 상세 ───────────────────────────────────────────────
  function showDetail(id) {
    _currentId = id;
    _pasteFile  = null;
    _setHeader('문의 스레드', true, false);
    _loading();
    fetch(BASE_URL + '/panel/inquiries/' + id, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
      .then(r => r.json())
      .then(d => _renderThread(d))
      .catch(() => { _body().innerHTML = '<div class="sp-loading">오류가 발생했습니다.</div>'; });
  }

  function _renderThread({ inquiry, messages, is_admin, can_delete }) {
    const answered = inquiry.status === 'answered';
    const statusBadge = answered
      ? '<span class="badge badge-success"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> 답변완료</span>'
      : '<span class="badge badge-warning"><i class="fa-solid fa-clock" style="font-size:10px;"></i> 대기중</span>';

    const deleteBtn = can_delete
      ? `<button onclick="InquiryPanel.doDelete(${inquiry.id})" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger);font-size:11px;"><i class="fa-solid fa-trash"></i></button>`
      : '';

    // 정보 바
    const infoBar = `<div class="inq-thread-info">
      <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-bottom:6px;">
        <span class="badge badge-secondary">${_esc(inquiry.category)}</span>${statusBadge}
      </div>
      <div style="font-size:13px;font-weight:700;color:var(--text-primary);line-height:1.4;margin-bottom:4px;">${_esc(inquiry.title)}</div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="font-size:11px;color:var(--text-muted);">
          <i class="fa-solid fa-user" style="font-size:10px;margin-right:3px;"></i>${_esc(inquiry.user)}
          <span style="margin:0 5px;">·</span>${inquiry.date}
        </div>
        ${deleteBtn}
      </div>
    </div>`;

    // 메시지들
    const msgHtml = messages.length
      ? messages.map(m => _buildMsgEl(m)).join('')
      : '<div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px;">아직 메시지가 없습니다.</div>';

    // 입력 영역 — 관리자만 표시
    const inputArea = IS_ADMIN ? `
      <div class="inq-paste-preview" id="inqPastePreview">
        <img id="inqPasteImg" src="" alt="붙여넣기 이미지" class="inq-paste-img"/>
        <span class="inq-paste-name" id="inqPasteName">이미지</span>
        <button type="button" onclick="InquiryPanel.clearPaste()" class="inq-paste-clear" title="제거">×</button>
      </div>
      <div style="padding:10px 14px;border-top:2px solid var(--border);background:var(--gray-50);flex-shrink:0;">
        <textarea id="inqMsgBody" style="width:100%;resize:none;border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;line-height:1.6;font-family:inherit;min-height:40px;max-height:120px;outline:none;background:#fff;box-sizing:border-box;" placeholder="답변 입력... (Ctrl+V 이미지 가능, Enter 전송, Shift+Enter 줄바꿈)" rows="2"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
          <label class="btn btn-sm btn-outline" style="font-size:11px;cursor:pointer;padding:4px 8px;margin:0;">
            <i class="fa-solid fa-paperclip"></i> 파일
            <input type="file" id="inqMsgFile" style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
          </label>
          <button onclick="InquiryPanel.doAddMessage()" class="btn btn-primary btn-sm" style="font-size:11px;">
            <i class="fa-solid fa-paper-plane"></i> 답변 전송
          </button>
        </div>
      </div>` : `<div style="padding:14px 16px;border-top:1px solid var(--border);background:var(--gray-50);flex-shrink:0;font-size:12px;color:var(--text-muted);text-align:center;"><i class="fa-solid fa-clock" style="margin-right:5px;"></i>답변을 기다리는 중입니다.</div>`;

    const msgsWrap = `<div class="inq-thread-msgs" id="inqThreadMessages">${msgHtml}</div>`;

    _body().innerHTML = infoBar + msgsWrap + inputArea;
    // 메시지 스크롤을 독립적으로 유지하기 위해 sp-body를 flex 컨테이너로 전환
    const b = _body();
    b.style.overflow      = 'hidden';
    b.style.display       = 'flex';
    b.style.flexDirection = 'column';
    _scrollBottom();
    if (IS_ADMIN) _setupThreadInput();
  }

  function _buildMsgEl(m) {
    const mine = (m.user_id === AUTH_USER_ID);
    const avClass = mine ? 'mine-av' : (m.is_admin ? 'admin-av' : 'user-av');
    const adminMark = m.is_admin ? '<span style="font-size:10px;background:var(--primary);color:#fff;border-radius:6px;padding:1px 4px;margin-left:4px;">관리자</span>' : '';

    let content = '';
    if (m.body) {
      content += `<div class="inq-msg-bubble">${_escNl(m.body)}</div>`;
    }
    if (m.attachment_path) {
      const url = BASE_URL + '/storage/' + m.attachment_path;
      if (m.is_image) {
        content += `<div><img src="${url}" alt="${_esc(m.attachment_name)}" class="inq-msg-img" onclick="document.getElementById('chatLightboxImg').src='${url}';document.getElementById('chatLightbox').classList.add('show');"></div>`;
      } else {
        const sizeKb = m.attachment_size ? Math.round(m.attachment_size / 1024) + ' KB' : '';
        content += `<div class="inq-msg-file"><a href="${url}" download="${_esc(m.attachment_name)}" style="color:inherit;text-decoration:none;"><i class="fa-solid fa-file" style="margin-right:5px;"></i>${_esc(m.attachment_name)} <span style="font-size:10px;color:var(--text-muted);">${sizeKb}</span></a></div>`;
      }
    }

    return `<div class="inq-msg${mine ? ' mine' : ''}">
      <div class="inq-msg-av ${avClass}">${_esc(m.user_initial)}</div>
      <div class="inq-msg-body">
        <div class="inq-msg-name">${_esc(m.user_name)}${adminMark}</div>
        ${content}
        <div class="inq-msg-time">${m.time_label}</div>
      </div>
    </div>`;
  }

  function _escNl(str) {
    return _esc(str).replace(/\n/g, '<br>');
  }

  function _scrollBottom() {
    const el = document.getElementById('inqThreadMessages');
    if (el) el.scrollTop = el.scrollHeight;
  }

  function _setupThreadInput() {
    const ta = document.getElementById('inqMsgBody');
    if (!ta) return;

    // Enter: 전송 / Shift+Enter: 줄바꿈
    ta.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        InquiryPanel.doAddMessage();
      }
    });
    // 자동 높이 조절
    ta.addEventListener('input', () => {
      ta.style.height = 'auto';
      ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    });
    // 이미지 붙여넣기
    ta.addEventListener('paste', _handlePaste);

    // 파일 선택 미리보기
    const fi = document.getElementById('inqMsgFile');
    if (fi) {
      fi.addEventListener('change', () => {
        if (fi.files[0]) _setPasteFile(fi.files[0]);
      });
    }
  }

  function _handlePaste(e) {
    const items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (let i = 0; i < items.length; i++) {
      if (items[i].type.indexOf('image') !== -1) {
        e.preventDefault();
        const file = items[i].getAsFile();
        if (file) _setPasteFile(file);
        return;
      }
    }
  }

  function _setPasteFile(file) {
    _pasteFile = file;
    const preview = document.getElementById('inqPastePreview');
    const img     = document.getElementById('inqPasteImg');
    if (!preview || !img) return;
    const reader = new FileReader();
    reader.onload = ev => { img.src = ev.target.result; preview.style.display = 'flex'; };
    reader.readAsDataURL(file);
  }

  function clearPaste() {
    _pasteFile = null;
    const preview = document.getElementById('inqPastePreview');
    const img     = document.getElementById('inqPasteImg');
    const fi      = document.getElementById('inqMsgFile');
    if (preview) preview.style.display = 'none';
    if (img)     img.src = '';
    if (fi)      fi.value = '';
  }

  // ── 새 문의 작성 ─────────────────────────────────────────────
  function openCreate() {
    if (IS_ADMIN) { open(); return; }
    SidePanels.closeAll();
    document.getElementById('inquiryPanel').classList.add('open');
    SidePanels.openOverlay();
    showCreate();
  }

  function showCreate() {
    if (IS_ADMIN) { loadList(); return; }
    _currentId  = null;
    _pasteFile  = null;
    _resetBodyStyle();
    _setHeader('새 문의 작성', true, false);

    const pageUrl   = window.location.pathname + window.location.search;
    const pageTitle = (document.querySelector('.page-title') || {}).textContent
                   ? document.querySelector('.page-title').textContent.trim()
                   : (document.title || pageUrl);

    _body().innerHTML = `
      <div class="sp-form" style="padding-top:18px;">
        <div style="padding:10px 12px;background:var(--gray-50);border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:flex-start;gap:10px;">
          <i class="fa-solid fa-location-dot" style="color:var(--primary);margin-top:2px;font-size:13px;flex-shrink:0;"></i>
          <div style="flex:1;min-width:0;">
            <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">현재 페이지</div>
            <div style="font-size:13px;font-weight:500;color:var(--text-primary);">${_esc(pageTitle)}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:1px;word-break:break-all;">${_esc(pageUrl)}</div>
          </div>
          <label style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-secondary);cursor:pointer;white-space:nowrap;flex-shrink:0;">
            <input type="checkbox" id="inqIncludePage" checked style="width:13px;height:13px;"> 포함
          </label>
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">분류 <span style="color:var(--danger);">*</span></label>
          <select id="inqCategory" class="form-control form-select">
            <option value="">분류 선택</option>
            <option value="general">일반</option>
            <option value="technical">기술</option>

            <option value="other">기타</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">제목 <span style="color:var(--danger);">*</span></label>
          <input type="text" id="inqTitle" class="form-control" placeholder="문의 제목">
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">내용</label>
          <textarea id="inqContent" class="form-control" rows="6" placeholder="문의 내용을 입력하세요... (Ctrl+V로 이미지 붙여넣기 가능)"></textarea>
        </div>

        <div class="inq-paste-preview" id="inqCreatePastePreview" style="display:none;">
          <img id="inqCreatePasteImg" src="" alt="붙여넣기 이미지" style="max-height:100px;max-width:100%;border-radius:6px;"/>
          <button type="button" onclick="InquiryPanel.clearCreatePaste()" class="inq-paste-remove" title="제거">×</button>
        </div>

        <div style="display:flex;align-items:center;gap:8px;">
          <label class="btn btn-sm btn-outline" style="font-size:11px;cursor:pointer;padding:4px 8px;margin:0;">
            <i class="fa-solid fa-paperclip"></i> 파일 첨부
            <input type="file" id="inqCreateFile" style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
          </label>
          <span id="inqCreateFileName" style="font-size:11px;color:var(--text-muted);"></span>
        </div>

        <div style="padding:10px 12px;background:var(--info-light);border:1px solid var(--primary-accent);border-radius:var(--radius);font-size:12px;color:var(--primary);">
          <i class="fa-solid fa-circle-info" style="margin-right:6px;"></i>
          영업일 기준 1~2일 내 답변드립니다.
        </div>

        <div class="sp-form-actions">
          <button onclick="InquiryPanel.back()" class="btn btn-outline btn-sm">취소</button>
          <button onclick="InquiryPanel.doStore('${_esc(pageTitle)}','${_esc(pageUrl)}')" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-paper-plane"></i> 문의 등록
          </button>
        </div>
      </div>`;

    // 이미지 붙여넣기 연결
    const ta = document.getElementById('inqContent');
    if (ta) {
      ta.addEventListener('paste', e => {
        const items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
          if (items[i].type.indexOf('image') !== -1) {
            e.preventDefault();
            const file = items[i].getAsFile();
            if (file) {
              _pasteFile = file;
              const reader = new FileReader();
              reader.onload = ev => {
                const img = document.getElementById('inqCreatePasteImg');
                const pre = document.getElementById('inqCreatePastePreview');
                if (img) img.src = ev.target.result;
                if (pre) pre.style.display = 'flex';
              };
              reader.readAsDataURL(file);
            }
            return;
          }
        }
      });
    }
    // 파일 선택 핸들러
    const fi = document.getElementById('inqCreateFile');
    if (fi) {
      fi.addEventListener('change', () => {
        if (fi.files[0]) {
          _pasteFile = fi.files[0];
          const fn = document.getElementById('inqCreateFileName');
          if (fn) fn.textContent = fi.files[0].name;
          if (fi.files[0].type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = ev => {
              const img = document.getElementById('inqCreatePasteImg');
              const pre = document.getElementById('inqCreatePastePreview');
              if (img) img.src = ev.target.result;
              if (pre) pre.style.display = 'flex';
            };
            reader.readAsDataURL(fi.files[0]);
          }
        }
      });
    }
  }

  function clearCreatePaste() {
    _pasteFile = null;
    const pre = document.getElementById('inqCreatePastePreview');
    const img = document.getElementById('inqCreatePasteImg');
    const fi  = document.getElementById('inqCreateFile');
    const fn  = document.getElementById('inqCreateFileName');
    if (pre) pre.style.display = 'none';
    if (img) img.src = '';
    if (fi)  fi.value = '';
    if (fn)  fn.textContent = '';
  }

  // ── AJAX 액션 ─────────────────────────────────────────────────
  async function doStore(pageTitle, pageUrl) {
    if (!pageTitle) pageTitle = '';
    if (!pageUrl)   pageUrl   = '';
    const category    = (document.getElementById('inqCategory') || {}).value || '';
    const title       = ((document.getElementById('inqTitle') || {}).value || '').trim();
    const body        = ((document.getElementById('inqContent') || {}).value || '').trim();
    const includePage = (document.getElementById('inqIncludePage') || {}).checked;

    if (!category) { showToast('분류를 선택해주세요.', 'warning'); return; }
    if (!title)    { showToast('제목을 입력해주세요.', 'warning'); return; }
    if (!body && !_pasteFile) { showToast('내용을 입력하거나 파일을 첨부해주세요.', 'warning'); return; }

    let finalBody = body;
    if (includePage && pageUrl) {
      finalBody = '[발생 페이지: ' + pageTitle + ' (' + pageUrl + ')]\n\n' + body;
    }

    const fd = new FormData();
    fd.append('title',    title);
    fd.append('category', category);
    if (finalBody) fd.append('body', finalBody);
    if (_pasteFile) fd.append('attachment', _pasteFile, _pasteFile.name || 'paste.png');

    const res = await _fetchForm(BASE_URL + '/panel/inquiries', fd);
    if (res && res.success) {
      showToast(res.message || '문의가 등록되었습니다.', 'success');
      _pasteFile = null;
      showDetail(res.inquiry_id);
    }
  }

  async function doAddMessage() {
    if (!_currentId) return;
    const ta     = document.getElementById('inqMsgBody');
    const fi     = document.getElementById('inqMsgFile');
    const body   = ta ? ta.value.trim() : '';
    const file   = _pasteFile || (fi && fi.files[0] ? fi.files[0] : null);

    if (!body && !file) { showToast('내용을 입력하거나 파일을 첨부해주세요.', 'warning'); return; }

    const fd = new FormData();
    if (body) fd.append('body', body);
    if (file) fd.append('attachment', file, file.name || 'paste.png');

    const res = await _fetchForm(BASE_URL + '/panel/inquiries/' + _currentId + '/messages', fd);
    if (res && res.success) {
      if (ta) { ta.value = ''; ta.style.height = 'auto'; }
      clearPaste();
      // 새 메시지를 스레드에 추가
      const thread = document.getElementById('inqThreadMessages');
      if (thread && res.message) {
        thread.insertAdjacentHTML('beforeend', _buildMsgEl(res.message));
        _scrollBottom();
      }
    }
  }

  async function doDelete(id) {
    if (!await ceConfirm('이 문의를 삭제하시겠습니까?', { tone: 'danger', confirmText: '삭제' })) return;
    const res = await apiRequest(BASE_URL + '/panel/inquiries/' + id, 'DELETE', {});
    if (res && res.success) {
      showToast('삭제되었습니다.', 'success');
      back();
    }
  }

  return { toggle, open, close, openCreate, back, loadList, showDetail, showCreate, doStore, doAddMessage, doDelete, clearPaste, clearCreatePaste };
})();

// Esc 키로 라이트박스 닫기
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.getElementById('chatLightbox').classList.remove('show');
    HelpPanel.close();
    Tour.end();
  }
});
</script>

{{-- ═══════════════════════════════════════════════════════════
     HELP PANEL + TOUR SYSTEM
═══════════════════════════════════════════════════════════ --}}
<style>
/* ── Help Panel ───────────────────────────────────────────── */
#helpPanel {
  position: fixed; top: 0; right: -380px; width: 380px; height: 100vh;
  background: #fff; border-left: 1px solid var(--border);
  display: flex; flex-direction: column; z-index: 999;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#helpPanel.open { right: 0; box-shadow: -4px 0 32px rgba(0,0,0,.12); }
.help-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  background: var(--bg); flex-shrink: 0;
}
.help-header-icon { font-size: 22px; color: var(--primary); }
.help-header-title { font-size: 14px; font-weight: 700; flex: 1; color: var(--text-primary); }
.help-header-close {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; flex-shrink: 0;
  background: none; border: none; color: var(--text-muted);
  font-size: 16px; line-height: 1; cursor: pointer; border-radius: 8px;
}
.help-header-close:hover { color: var(--text-primary); background: var(--border-light); }
.help-body { flex: 1; overflow-y: auto; padding: 16px; }
.help-tour-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;
  background: var(--primary-light); color: var(--primary);
  border: 1px solid var(--primary); font-size: 13px; font-weight: 500;
  cursor: pointer; transition: var(--transition);
}
.help-tour-btn:hover { background: var(--primary); color: #fff; }
.help-section { margin-bottom: 18px; }
.help-section-title {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px;
  color: var(--text-muted); margin-bottom: 10px; padding-bottom: 5px;
  border-bottom: 1px solid var(--border-light);
}
.help-item { display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; }
.help-item-icon {
  width: 30px; height: 30px; border-radius: 6px;
  background: var(--primary-light); color: var(--primary);
  display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;
}
.help-item-icon.warn { background: var(--warning-light); color: var(--warning); }
.help-item-icon.success { background: var(--success-light); color: var(--success); }
.help-item-icon.info { background: var(--info-light); color: var(--info); }
.help-item-icon.purple { background: var(--primary-light); color: var(--primary); }
.help-item-text { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }
.help-item-text strong { color: var(--text-primary); display: block; margin-bottom: 1px; font-size: 13px; }
.help-tip {
  background: #eff6ff; border-left: 3px solid var(--primary);
  border-radius: 0 6px 6px 0; padding: 8px 12px; margin-bottom: 10px;
  font-size: 12px; color: var(--text-secondary); line-height: 1.5;
}
.help-tip i { margin-right: 4px; color: var(--primary); }
.help-badge-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }

/* ── Tour Overlay ─────────────────────────────────────────── */
#tourOverlay { position: fixed; inset: 0; z-index: 10000; display: none; }
#tourOverlay.active { display: block; }
#tourSpotlight {
  position: fixed; z-index: 10001; border-radius: 8px; pointer-events: none;
  box-shadow: 0 0 0 9999px rgba(0,0,0,.68);
  transition: all .35s cubic-bezier(.4,0,.2,1);
  outline: 2px solid var(--primary); outline-offset: 2px;
}
#tourTooltip {
  position: fixed; z-index: 10002; background: #fff; border-radius: 12px;
  padding: 20px 22px; width: 310px;
  box-shadow: 0 16px 48px rgba(0,0,0,.22);
  transition: all .3s cubic-bezier(.4,0,.2,1);
}
.tour-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--primary); margin-bottom: 5px;
}
.tour-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.tour-body { font-size: 13px; color: var(--text-secondary); line-height: 21px; margin-bottom: 16px; }
.tour-footer { display: flex; align-items: center; gap: 6px; }
.tour-progress {
  font-size: 11px; color: var(--text-muted); margin-right: auto;
  display: flex; align-items: center; gap: 4px;
}
.tour-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--border);
  transition: background .2s;
}
.tour-dot.active { background: var(--primary); }
.tour-btn { padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; transition: var(--transition); }
.tour-btn-skip { background: none; color: var(--text-muted); padding: 4px 8px; font-size: 11px; }
.tour-btn-skip:hover { color: var(--danger); }
.tour-btn-prev { background: var(--border-light); color: var(--text-secondary); }
.tour-btn-prev:hover { background: var(--border); }
.tour-btn-next { background: var(--primary); color: #fff; }
.tour-btn-next:hover { background: var(--primary-dark); }
</style>

{{-- Help Panel --}}
<div id="helpPanel">
  <div class="help-header">
    <i class="bx bx-help-circle help-header-icon"></i>
    <span class="help-header-title" id="helpPanelTitle">@yield('help-title', '도움말')</span>
    <button class="help-header-close" onclick="HelpPanel.close()"><i class="bx bx-x"></i></button>
  </div>
  <div class="help-body" id="helpPanelBody">
    <button class="help-tour-btn" id="helpTourBtn" onclick="Tour.start()" style="display:none;">
      <i class="bx bx-play-circle" style="font-size:16px;"></i> 화면 안내 투어 시작
    </button>
    @hasSection('help-content')
      @yield('help-content')
    @else
      <div class="help-tip"><i class="bx bx-info-circle"></i>이 페이지의 도움말을 준비 중입니다.</div>
    @endif
  </div>
</div>

{{-- Tour Overlay --}}
<div id="tourOverlay">
  <div id="tourSpotlight"></div>
  <div id="tourTooltip">
    <div class="tour-label" id="tourLabel"></div>
    <div class="tour-title" id="tourTitle"></div>
    <div class="tour-body" id="tourBody"></div>
    <div class="tour-footer">
      <div class="tour-progress" id="tourDots"></div>
      <button class="tour-btn tour-btn-skip" onclick="Tour.skip()">건너뛰기</button>
      <button class="tour-btn tour-btn-prev" id="tourPrevBtn" onclick="Tour.prev()">← 이전</button>
      <button class="tour-btn tour-btn-next" id="tourNextBtn" onclick="Tour.next()">다음 →</button>
    </div>
  </div>
</div>

<script>
// ── Help Panel ──────────────────────────────────────────────
const HelpPanel = (() => {
  function toggle() {
    const p = document.getElementById('helpPanel');
    p.classList.contains('open') ? close() : open();
  }
  function open() { document.getElementById('helpPanel').classList.add('open'); }
  function close() { document.getElementById('helpPanel').classList.remove('open'); }
  return { toggle, open, close };
})();

// ── Tour System ────────────────────────────────────────────
const Tour = (() => {
  let _steps = [];
  let _idx   = 0;
  let _pageKey = '';

  // 사이드바·네비바 공통 기본 투어 (페이지별 스텝 없을 때 사용)
  const _defaultSteps = [
    {
      selector: '.app-brand',
      title: 'CE Admin',
      body: 'CE Admin 관리 시스템에 오신 것을 환영합니다. 좌측 메뉴에서 각 기능으로 이동할 수 있습니다.'
    },
    {
      selector: '.menu-inner',
      title: '사이드바 메뉴',
      body: '<b>처방전·환자·주문·청구·정산</b> 등 주요 기능이 이 메뉴에 있습니다. 아이콘을 클릭하면 메뉴가 접힙니다.'
    },
    {
      selector: '.layout-navbar',
      title: '상단 네비게이션',
      body: '알림, 채팅, 도움말(?), SR 관리 버튼이 있습니다. <b>?</b> 버튼을 누르면 현재 페이지 도움말을 볼 수 있습니다.'
    },
    {
      selector: '#helpToggleBtn',
      title: '도움말 버튼',
      body: '이 버튼을 클릭하면 현재 페이지 설명과 투어를 다시 시작할 수 있습니다. 언제든지 활용하세요.'
    },
  ];

  function _init() {
    const cfg = window.HELP_TOUR_STEPS;
    _steps   = (cfg && cfg.length) ? cfg : _defaultSteps;
    _pageKey = window.TOUR_PAGE_KEY || window.location.pathname;

    // 투어 버튼 항상 표시
    const btn = document.getElementById('helpTourBtn');
    if (btn) btn.style.display = 'flex';

    // 이 사용자가 아직 이 페이지 투어를 보지 않은 경우 자동 시작 (1.2초 후)
    if (_pageKey && !(window.CE_TOURED || []).includes(_pageKey)) {
      setTimeout(start, 1200);
    }
  }

  function start() {
    if (!_steps.length) { showToast('이 페이지의 투어가 없습니다.', 'info'); return; }
    _idx = 0;
    HelpPanel.close();
    document.getElementById('tourOverlay').classList.add('active');
    _buildDots();
    _showStep();
  }

  function _buildDots() {
    const el = document.getElementById('tourDots');
    el.innerHTML = _steps.map((_, i) =>
      `<div class="tour-dot ${i === _idx ? 'active' : ''}" id="tdot-${i}"></div>`
    ).join('');
  }

  function _activateDot(i) {
    document.querySelectorAll('.tour-dot').forEach((d, idx) =>
      d.classList.toggle('active', idx === i));
  }

  function _showStep() {
    const step = _steps[_idx];
    if (!step) { end(); return; }

    document.getElementById('tourLabel').textContent = `${_idx + 1} / ${_steps.length}`;
    document.getElementById('tourTitle').textContent = step.title;
    document.getElementById('tourBody').innerHTML    = step.body;

    const prevBtn = document.getElementById('tourPrevBtn');
    const nextBtn = document.getElementById('tourNextBtn');
    prevBtn.style.visibility = _idx === 0 ? 'hidden' : 'visible';
    nextBtn.textContent      = _idx === _steps.length - 1 ? '완료 ✓' : '다음 →';

    _activateDot(_idx);

    const el = document.querySelector(step.selector);
    const spotlight = document.getElementById('tourSpotlight');
    const tooltip   = document.getElementById('tourTooltip');

    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => {
        const r = el.getBoundingClientRect();
        const pad = 8;
        spotlight.style.cssText = `top:${r.top-pad}px;left:${r.left-pad}px;width:${r.width+pad*2}px;height:${r.height+pad*2}px;`;
        _posTooltip(r, tooltip);
      }, 320);
    } else {
      spotlight.style.cssText = 'top:-9999px;left:-9999px;width:0;height:0;';
      tooltip.style.cssText   = 'top:50%;left:50%;transform:translate(-50%,-50%);';
    }
  }

  function _posTooltip(r, tooltip) {
    const W = window.innerWidth, H = window.innerHeight;
    const TW = 330, TH = 200, GAP = 16, PAD = 12;
    let top, left;
    tooltip.style.transform = '';

    if (r.bottom + GAP + TH < H) {        // below
      top  = r.bottom + GAP;
      left = Math.max(PAD, Math.min(r.left, W - TW - PAD));
    } else if (r.top - GAP - TH > 0) {    // above
      top  = r.top - GAP - TH;
      left = Math.max(PAD, Math.min(r.left, W - TW - PAD));
    } else if (r.right + GAP + TW < W) {  // right
      top  = Math.max(PAD, r.top);
      left = r.right + GAP;
    } else {                               // left
      top  = Math.max(PAD, r.top);
      left = Math.max(PAD, r.left - GAP - TW);
    }
    tooltip.style.cssText = `top:${top}px;left:${left}px;`;
  }

  function next() { if (_idx >= _steps.length - 1) { end(); return; } _idx++; _showStep(); }
  function prev() { if (_idx <= 0) return; _idx--; _showStep(); }

  function end() {
    document.getElementById('tourOverlay').classList.remove('active');
    if (!_pageKey) return;
    // 이미 저장된 경우 중복 요청 방지
    if ((window.CE_TOURED || []).includes(_pageKey)) return;
    window.CE_TOURED = [...(window.CE_TOURED || []), _pageKey];
    fetch(BASE_URL + '/tour/done', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
      body: JSON.stringify({ page: _pageKey }),
    }).catch(() => {});
  }

  function skip() { end(); }

  // 오버레이 클릭 시 닫기 (스포트라이트 영역 외)
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tourOverlay').addEventListener('click', e => {
      if (e.target === document.getElementById('tourOverlay')) skip();
    });
    _init();
  });

  return { start, next, prev, skip, end };
})();
</script>

<script>
/* 전화번호 자동 포맷 — [data-phone] 속성을 가진 모든 input에 적용 */
(function () {
  function fmtPhone(raw) {
    const d = raw.replace(/\D/g, '').slice(0, 11);
    if (!d) return '';
    if (d.startsWith('02')) {
      if (d.length <= 5) return d.slice(0, 2) + (d.length > 2 ? '-' + d.slice(2) : '');
      if (d.length <= 9) return d.slice(0, 2) + '-' + d.slice(2, 5) + '-' + d.slice(5);
      return d.slice(0, 2) + '-' + d.slice(2, 6) + '-' + d.slice(6, 10);
    }
    if (d.length <= 6) return d.slice(0, 3) + (d.length > 3 ? '-' + d.slice(3) : '');
    if (d.length <= 10) return d.slice(0, 3) + '-' + d.slice(3, 6) + '-' + d.slice(6);
    return d.slice(0, 3) + '-' + d.slice(3, 7) + '-' + d.slice(7, 11);
  }

  document.addEventListener('input', function (e) {
    const el = e.target;
    if (!el.hasAttribute || !el.hasAttribute('data-phone')) return;
    const pos  = el.selectionStart;
    const prev = el.value;
    const next = fmtPhone(prev);
    if (next === prev) return;
    el.value = next;
    const offset = next.length - prev.length;
    const newPos = Math.max(0, pos + offset);
    el.setSelectionRange(newPos, newPos);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-phone]').forEach(function (el) {
      if (el.value) el.value = fmtPhone(el.value);
    });
  });

  window.fmtPhone = fmtPhone;
})();
</script>
</body>
</html>
