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
    :root {
      /* 콜로플라스트 브랜드 시안(#00B0CA) 기준 팔레트 */
      --primary:        #00B0CA;
      --primary-light:  #E6F7FA;
      --primary-dark:   #008EA4;
      --primary-accent: #7FD8E4;
      --success:        #12B76A;  --success-light: #ECFDF5;
      --warning:        #F59E0B;  --warning-light: #FFFBEB;
      --danger:         #EF4444;  --danger-light:  #FEF2F2;
      --info:           #0EA5E9;  --info-light:    #F0F9FF;
      --purple:         #7C3AED;
      --bg:             #F4F6FA;
      --bg-card:        #FFFFFF;
      --border:         #E5E9F0;
      --border-light:   #F1F4F9;
      --text-primary:   #0D1B2A;
      --text-secondary: #2D3A4A;
      --text-muted:     #8B95A1;
      --shadow:    0 1px 3px rgba(13,27,42,.06), 0 1px 2px rgba(13,27,42,.04);
      --shadow-md: 0 4px 12px rgba(13,27,42,.08), 0 2px 6px rgba(13,27,42,.04);
      --shadow-lg: 0 16px 32px rgba(13,27,42,.10), 0 4px 8px rgba(13,27,42,.04);
      --radius:    8px;
      --radius-lg: 12px;
      --transition: all .18s ease;
      --menu-bg:     #FFFFFF;
      --menu-color:  #4B5563;
      --menu-active: #00B0CA;
      --nav-h: 60px;
      --sidebar-w: 240px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { overflow-x: hidden; }
    body {
      font-family: 'Pretendard Variable', 'Pretendard', -apple-system, BlinkMacSystemFont,
                   'Apple SD Gothic Neo', 'Noto Sans KR', 'Segoe UI', sans-serif;
      background: #FFFFFF;
      color: var(--text-primary);
      font-size: 14px;
      line-height: 1.6;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* ═══════════════════════════════════════════
       LAYOUT
    ═══════════════════════════════════════════ */
    .layout-wrapper   { display: flex; flex-direction: column; min-height: 100vh; }
    .layout-container { display: flex; flex: 1; min-height: 0; }

    /* ── Sidebar ── */
    .layout-menu {
      width: var(--sidebar-w);
      flex-shrink: 0;
      background: var(--menu-bg);
      position: fixed; top: 0; left: 0; bottom: 0;
      z-index: 100;
      overflow-y: auto; overflow-x: hidden;
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column;
      transition: width .25s cubic-bezier(.4,0,.2,1), transform .25s cubic-bezier(.4,0,.2,1);
      scrollbar-width: none;
    }
    .layout-menu::-webkit-scrollbar { display: none; }
    .layout-menu.hidden { transform: translateX(-100%); }

    /* Collapsed */
    .layout-menu.collapsed { width: 64px; }
    .layout-menu.collapsed .app-brand-text,
    .layout-menu.collapsed .app-brand-sub,
    .layout-menu.collapsed .menu-header,
    .layout-menu.collapsed .menu-link span,
    .layout-menu.collapsed .menu-badge,
    .layout-menu.collapsed .menu-user-info { display: none; }
    .layout-menu.collapsed .app-brand { justify-content: center; padding: 0; height: var(--nav-h); }
    .layout-menu.collapsed .app-brand > a { flex: 0 0 auto; min-width: 0; }
    .layout-menu.collapsed .menu-link { justify-content: center; padding: 10px 0; margin: 2px 8px; }
    .layout-menu.collapsed .menu-icon { width: auto; }
    .layout-menu.collapsed .menu-user { justify-content: center; padding: 10px 0; }
    .layout-menu.collapsed .menu-footer { padding: 10px 6px; }
    .layout-menu.collapsed ~ .layout-page,
    .layout-page.collapsed { margin-left: 64px; }

    /* Tooltip on hover when collapsed */
    .layout-menu.collapsed .menu-item { position: relative; }
    .layout-menu.collapsed .menu-link::after {
      content: attr(data-title);
      position: absolute; left: calc(100% + 8px); top: 50%;
      transform: translateY(-50%);
      background: #0D1B2A; color: #F1F5F9;
      font-size: 12px; font-weight: 500; white-space: nowrap;
      padding: 5px 10px; border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,.15);
      opacity: 0; pointer-events: none;
      transition: opacity .12s ease;
      z-index: 200;
    }
    .layout-menu.collapsed .menu-link:hover::after { opacity: 1; }

    /* Collapse toggle btn */
    .menu-collapse-btn {
      margin-left: auto; flex-shrink: 0;
      width: 28px; height: 28px; border-radius: 6px;
      background: transparent; border: none;
      color: var(--text-muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      transition: background .15s, color .15s;
    }
    .menu-collapse-btn:hover { background: var(--bg); color: var(--primary); }
    .layout-menu.collapsed .menu-collapse-btn { margin-left: 0; }
    .menu-collapse-btn .ic-expanded { display: flex; }
    .menu-collapse-btn .ic-collapsed { display: none; }
    .layout-menu.collapsed .menu-collapse-btn .ic-expanded { display: none; }
    .layout-menu.collapsed .menu-collapse-btn .ic-collapsed { display: flex; }

    /* Brand */
    .app-brand {
      display: flex; align-items: center; gap: 10px;
      padding: 0 16px;
      border-bottom: 1px solid var(--border);
      min-height: var(--nav-h);
      text-decoration: none;
      flex-shrink: 0;
    }
    .app-brand-logo {
      width: 34px; height: 34px; border-radius: 9px;
      background: var(--primary);
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 800; flex-shrink: 0;
      letter-spacing: -.5px;
    }
    .app-brand-text {
      font-size: 15px; font-weight: 700; color: var(--text-primary);
      letter-spacing: -.3px; line-height: 1.2;
    }
    .app-brand-sub { font-size: 10.5px; color: var(--text-muted); margin-top: 1px; letter-spacing: 0; }

    /* Menu sections */
    .menu-inner { flex: 1; padding: 6px 0 10px; }
    .menu-header {
      /* 그룹 라벨은 한글이라 uppercase 효과가 없고, 자간이 넓으면 커진 글자가 흐트러져
         letter-spacing 을 줄였다 */
      font-size: 12.5px; font-weight: 700; color: var(--text-secondary);
      letter-spacing: -.1px;
      padding: 16px 18px 6px;
      /* 그룹 접기 토글 버튼 */
      display: flex; align-items: center; gap: 6px;
      width: 100%; background: none; border: none;
      font-family: inherit; text-align: left; cursor: pointer;
    }
    .menu-header:hover { color: var(--primary); }
    .menu-caret {
      margin-left: auto; font-size: 15px; opacity: .55;
      transition: transform .18s ease;
    }
    .menu-group.is-collapsed .menu-caret { transform: rotate(-90deg); }
    .menu-group.is-collapsed > .menu-group-items { display: none; }
    /* 그룹 안에 현재 화면이 있으면 헤더를 강조 */
    .menu-group.has-active > .menu-header { color: var(--primary); }
    /* 접힌 그룹에 알림 건수가 있으면 헤더에 합계를 표시(숨겨져 놓치지 않도록) */
    .menu-group-badge {
      background: var(--danger); color: #fff;
      font-size: 9.5px; font-weight: 700; padding: 1px 6px;
      border-radius: 10px; letter-spacing: 0;
    }
    .menu-group:not(.is-collapsed) .menu-group-badge { display: none; }
    /* 아이콘 모드에서는 헤더가 숨겨져 펼칠 수 없으므로 접힘을 무시한다 */
    .layout-menu.collapsed .menu-group.is-collapsed > .menu-group-items { display: block; }

    .menu-item { position: relative; }
    .menu-link {
      display: flex; align-items: center; gap: 9px;
      padding: 9px 12px; margin: 1px 8px; border-radius: 8px;
      color: var(--menu-color); font-size: 13.5px; font-weight: 500;
      text-decoration: none; transition: var(--transition); position: relative;
      letter-spacing: -.1px;
    }
    .menu-link:hover { background: var(--bg); color: var(--primary); }
    .menu-item.active > .menu-link {
      background: var(--primary-light);
      color: var(--primary);
      font-weight: 600;
    }
    .menu-item.active > .menu-link::before {
      content: '';
      position: absolute; left: -8px; top: 50%;
      transform: translateY(-50%);
      width: 3px; height: 20px; border-radius: 0 3px 3px 0;
      background: var(--primary);
    }
    .menu-item.active > .menu-link .menu-icon { color: var(--primary); }
    .menu-icon {
      font-size: 17px; width: 20px; text-align: center; flex-shrink: 0;
      color: var(--text-muted); transition: color .15s;
    }
    .menu-link:hover .menu-icon { color: var(--primary); }
    .menu-badge {
      margin-left: auto;
      background: var(--danger); color: #fff;
      font-size: 10px; font-weight: 700; padding: 1px 6px;
      border-radius: 20px; min-width: 18px; text-align: center;
      line-height: 1.6;
    }
    .menu-badge.blue   { background: var(--primary); }
    .menu-badge.orange { background: var(--warning); }

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
    .menu-user-name { font-size: 12.5px; font-weight: 600; color: var(--text-primary); line-height: 1.3; }
    .menu-user-role { font-size: 10.5px; color: var(--text-muted); }

    /* ── Layout Page ── */
    .layout-page {
      flex: 1; display: flex; flex-direction: column;
      min-width: 0; margin-left: var(--sidebar-w);
      transition: margin-left .25s cubic-bezier(.4,0,.2,1);
    }

    /* ── Top Navbar ── */
    .layout-navbar {
      display: flex; align-items: center; gap: 8px;
      padding: 0 24px;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      position: fixed; top: 0; left: var(--sidebar-w); right: 0; z-index: 50;
      min-height: var(--nav-h);
      transition: left .25s cubic-bezier(.4,0,.2,1);
    }
    body.menu-collapsed .layout-navbar { left: 64px; }
    .navbar-brand-area { flex: 1; min-width: 0; overflow: hidden; }
    .page-title {
      font-size: 16px; font-weight: 700; color: var(--text-primary);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      letter-spacing: -.3px;
    }
    .page-breadcrumb {
      font-size: 11.5px; color: var(--text-muted); margin-top: 1px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .navbar-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }

    /* Navbar icon buttons */
    .btn-icon {
      width: 36px; height: 36px; border-radius: 8px;
      border: none; background: transparent;
      display: flex; align-items: center; justify-content: center;
      color: var(--text-muted); cursor: pointer; position: relative;
      transition: var(--transition); flex-shrink: 0; font-size: 17px;
    }
    .btn-icon:hover { background: var(--bg); color: var(--primary); }
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
    .content-wrapper { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; padding-top: var(--nav-h); }
    .page-body { flex: 1; padding: 14px 24px 20px; min-width: 0; }


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
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
    }
    .card-header {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 16px; border-bottom: 1px solid var(--border);
      background: transparent;
    }
    .card-header-title { font-size: 14px; font-weight: 700; color: var(--text-primary); letter-spacing: -.2px; }
    .card-header-sub   { font-size: 12px; color: var(--text-muted); margin-left: 4px; }
    .card-body { padding: 14px 16px; }
    .mt-4 { margin-top: 12px; } .mb-4 { margin-bottom: 12px; }

    /* ── Buttons ── */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: var(--radius);
      font-size: 13.5px; font-weight: 600; cursor: pointer;
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
    .btn-sm  { padding: 5px 12px; font-size: 12.5px; }
    .btn-xs  { padding: 3px 9px; font-size: 11.5px; }
    .w-full  { width: 100%; justify-content: center; }
    .flex-1  { flex: 1; }

    /* Button loading states */
    .btn[data-loading]     { opacity: .75; cursor: not-allowed; pointer-events: none; }
    .btn[data-state="success"] { background: var(--success) !important; border-color: var(--success) !important; color: #fff !important; pointer-events: none; }
    .btn[data-state="error"]   { background: var(--danger)  !important; border-color: var(--danger)  !important; color: #fff !important; pointer-events: none; }

    /* ── Badges ── */
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 600;
      letter-spacing: -.1px;
    }
    .badge-primary,   .bg-label-primary   { background: var(--primary-light); color: var(--primary); }
    .badge-success,   .bg-label-success   { background: var(--success-light); color: var(--success); }
    .badge-warning,   .bg-label-warning   { background: var(--warning-light); color: #B45309; }
    .badge-danger,    .bg-label-danger    { background: var(--danger-light);  color: var(--danger); }
    .badge-info,      .bg-label-info      { background: var(--info-light);    color: var(--info); }
    .badge-secondary, .bg-label-secondary { background: var(--border-light);  color: var(--text-secondary); }
    .badge.bg-primary  { background: var(--primary) !important; color: #fff; }
    .badge.bg-success  { background: var(--success) !important; color: #fff; }
    .badge.bg-warning  { background: var(--warning) !important; color: #fff; }
    .badge.bg-danger   { background: var(--danger)  !important; color: #fff; }
    .badge.bg-info     { background: var(--info)    !important; color: #fff; }

    /* ── Forms ── */
    .form-group { margin-bottom: 10px; }
    .form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; letter-spacing: -.1px; }
    .form-label span { color: var(--danger); }
    .form-control {
      width: 100%; padding: 9px 12px; font-size: 13.5px;
      border: 1px solid var(--border); border-radius: var(--radius);
      background: var(--bg-card); color: var(--text-primary);
      transition: var(--transition); outline: none; font-family: inherit;
      line-height: 1.5;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0,176,202,.12);
    }
    .form-control::placeholder { color: var(--text-muted); }
    .form-select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238B95A1' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
      padding-right: 30px;
    }
    textarea.form-control { resize: vertical; }
    .input-group { display: flex; }
    .input-group .form-control { border-radius: var(--radius) 0 0 var(--radius); }
    .input-group .btn { border-radius: 0 var(--radius) var(--radius) 0; }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 11px 14px; font-size: 11.5px; font-weight: 700;
      color: var(--text-muted); text-align: left; text-transform: uppercase;
      letter-spacing: .5px; border-bottom: 1px solid var(--border);
      white-space: nowrap; background: #F9FAFB;
    }
    thead th:first-child { border-radius: var(--radius-lg) 0 0 0; }
    thead th:last-child  { border-radius: 0 var(--radius-lg) 0 0; }
    tbody td {
      padding: 11px 14px; font-size: 13.5px;
      border-bottom: 1px solid var(--border-light);
      vertical-align: middle; color: var(--text-secondary);
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: #FAFBFD; }

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
    .patient-detail { font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; }

    /* ── OCR Fields ── */
    .ocr-field {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border-radius: var(--radius);
      border: 1px solid var(--border); margin-bottom: 6px;
      background: var(--bg);
    }
    .ocr-label { font-size: 11px; color: var(--text-muted); width: 80px; flex-shrink: 0; }
    .ocr-value { font-size: 13px; font-weight: 600; flex: 1; }
    .ocr-check { color: var(--success); font-size: 14px; }
    .ocr-warn  { color: var(--warning); font-size: 14px; }

    /* ── Alerts ── */
    .alert {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px; border-radius: var(--radius-lg);
      margin-bottom: 16px; font-size: 13.5px; font-weight: 500;
      border-left: 4px solid transparent;
    }
    .alert-success { background: var(--success-light); border-color: var(--success); color: #065F46; }
    .alert-danger  { background: var(--danger-light);  border-color: var(--danger);  color: #991B1B; }
    .alert-warning { background: var(--warning-light); border-color: var(--warning); color: #92400E; }
    .alert-info    { background: var(--info-light);    border-color: var(--info);    color: #0C4A6E; }

    /* ── Card Footer ── */
    .card-footer {
      padding: 12px 18px; border-top: 1px solid var(--border);
      background: #FAFBFD; border-radius: 0 0 var(--radius-lg) var(--radius-lg);
      font-size: 13px; color: var(--text-muted);
    }

    /* ── Pagination ── */
    .pagination { display: flex; gap: 4px; align-items: center; margin: 0; flex-wrap: wrap; }
    .page-item .page-link {
      display: flex; align-items: center; justify-content: center;
      min-width: 32px; height: 32px; padding: 0 8px;
      border-radius: 6px; font-size: 13px; font-weight: 600;
      border: 1px solid var(--border); background: #fff; color: var(--text-secondary);
      text-decoration: none; transition: var(--transition);
    }
    .page-item .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    .page-item.active .page-link { background: var(--primary); border-color: var(--primary); color: #fff; }
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
    .toast {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 13px 16px; border-radius: 10px; color: #fff;
      font-size: 13.5px; font-weight: 500; box-shadow: var(--shadow-lg);
      animation: slideIn .25s ease; min-width: 260px;
      word-break: keep-all; line-height: 1.5; background: #1a2436;
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
      background: #1a2436; color: #fff;
      box-shadow: var(--shadow-lg);
      animation: slideIn .25s ease; min-width: 300px; max-width: 360px;
      cursor: pointer; border-left: 3px solid var(--primary);
      position: relative; pointer-events: auto;
    }
    .chat-toast:hover { background: #0f1929; }
    .chat-toast-avatar {
      width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
      background: var(--primary); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 700;
    }
    .chat-toast-body { flex: 1; min-width: 0; }
    .chat-toast-header { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
    .chat-toast-name { font-size: 13px; font-weight: 700; color: #fff; }
    .chat-toast-room { font-size: 11px; color: #94A3B8; }
    .chat-toast-msg {
      font-size: 13px; color: #CBD5E1; font-weight: 400;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;
    }
    .chat-toast-time { font-size: 10px; color: #64748B; margin-top: 2px; }
    .chat-toast-close {
      position: absolute; top: 8px; right: 10px;
      background: none; border: none; color: #64748B;
      font-size: 14px; cursor: pointer; line-height: 1; padding: 2px;
    }
    .chat-toast-close:hover { color: #fff; }
    .chat-toast-icon { font-size: 11px; color: #94A3B8; }
    @keyframes chatBtnPulse {
      0%   { background: transparent; transform: scale(1); }
      50%  { background: rgba(0,176,202,.2); transform: scale(1.15); }
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
      .page-title { font-size: 14.5px; }
      .page-breadcrumb { display: none; }
      .page-body { padding: 14px 14px 60px; }
      .card-body { padding: 14px; }
      .card-header { padding: 12px 14px; }
      table { font-size: 12.5px; }
      thead th { padding: 9px 10px; font-size: 10.5px; }
      tbody td { padding: 10px 10px; font-size: 12.5px; }
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
      color: #fff; font-size: 13px; font-weight: 800; line-height: 1;
    }
    .theme-label { font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 8px; }

    /* ═══════════════════════════════════════════
       GLOBAL SHARED COMPONENTS
    ═══════════════════════════════════════════ */

    /* ── Stat cards (all pages) ── */
    .stat-grid { display: grid; gap: 14px; }
    .stat-card {
      background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg);
      box-shadow: var(--shadow); padding: 18px 20px;
      display: flex; align-items: center; gap: 16px;
      text-decoration: none; color: inherit; cursor: pointer;
      transition: var(--transition);
    }
    .stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); color: inherit; }
    .stat-icon {
      width: 48px; height: 48px; border-radius: 10px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 22px;
    }
    .stat-icon.primary { background: var(--primary-light); color: var(--primary); }
    .stat-icon.success { background: var(--success-light); color: var(--success); }
    .stat-icon.warning { background: var(--warning-light); color: var(--warning); }
    .stat-icon.danger  { background: var(--danger-light);  color: var(--danger); }
    .stat-icon.info    { background: var(--info-light);    color: var(--info); }
    .stat-icon.purple  { background: #F3EEFF;              color: var(--purple); }
    .stat-icon.gray    { background: var(--border-light);  color: var(--text-muted); }
    .stat-val   { font-size: 24px; font-weight: 800; line-height: 1.1; color: var(--text-primary); }
    .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 3px; font-weight: 500; }
    .stat-info  { min-width: 0; }

    /* ── Pill tabs (status/type filter) ── */
    .tab-pills {
      display: flex; gap: 3px; padding: 4px; background: var(--bg);
      border-radius: 10px; flex-wrap: wrap; border: 1px solid var(--border);
    }
    .tab-pill {
      padding: 6px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 600;
      color: var(--text-muted); cursor: pointer; transition: var(--transition);
      border: none; background: transparent; white-space: nowrap;
      display: inline-flex; align-items: center; gap: 6px; line-height: 1;
    }
    .tab-pill:hover { color: var(--text-primary); background: rgba(255,255,255,.7); }
    .tab-pill.active { background: #fff; color: var(--primary); box-shadow: var(--shadow); }
    .tab-count {
      background: var(--border); color: var(--text-muted);
      font-size: 10px; padding: 1px 6px; border-radius: 10px; font-weight: 700; min-width: 18px; text-align: center;
    }
    .tab-pill.active .tab-count { background: var(--primary-light); color: var(--primary); }

    /* ── Underline tabs ── */
    .tab-underline { display: flex; border-bottom: 2px solid var(--border); gap: 0; margin-bottom: 18px; }
    .tab-u {
      padding: 9px 18px; font-size: 13px; font-weight: 600; color: var(--text-muted);
      cursor: pointer; border: none; background: transparent; margin-bottom: -2px;
      border-bottom: 2px solid transparent; transition: var(--transition);
      display: inline-flex; align-items: center; gap: 6px;
    }
    .tab-u:hover { color: var(--text-primary); }
    .tab-u.active { color: var(--primary); border-bottom-color: var(--primary); }

    /* ── Filter bar ── */
    .filter-bar {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
      padding: 12px 16px; background: var(--bg-card);
      border: 1px solid var(--border); border-radius: var(--radius-lg);
      margin-bottom: 16px; box-shadow: var(--shadow);
    }
    .filter-sep { width: 1px; height: 20px; background: var(--border); margin: 0 4px; flex-shrink: 0; }
    .filter-label { font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap; }

    /* ── Search input with icon ── */
    .search-wrap { position: relative; display: inline-flex; align-items: center; }
    .search-wrap > i { position: absolute; left: 10px; color: var(--text-muted); font-size: 16px; pointer-events: none; }
    .search-wrap .form-control { padding-left: 34px; }

    /* ── Online/offline dot ── */
    .status-dot { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; }
    .status-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .status-dot.online  { color: var(--success); }
    .status-dot.online::before  { background: var(--success); box-shadow: 0 0 0 2px var(--success-light); }
    .status-dot.offline { color: var(--text-muted); }
    .status-dot.offline::before { background: #9CA3AF; }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 52px 24px; }
    .empty-state i { font-size: 38px; opacity: .25; display: block; margin-bottom: 12px; color: var(--text-muted); }
    .empty-state p { font-size: 13.5px; color: var(--text-muted); margin: 0 0 14px; }

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
      padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0;
    }
    .modal-title { font-size: 15px; font-weight: 700; color: var(--text-primary); flex: 1; letter-spacing: -.2px; }
    .modal-close {
      width: 28px; height: 28px; border-radius: 6px; border: none;
      background: transparent; color: var(--text-muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; transition: var(--transition); flex-shrink: 0;
    }
    .modal-close:hover { background: var(--bg); color: var(--text-primary); }
    .modal-bd { padding: 20px; overflow-y: auto; flex: 1; }
    .modal-ft {
      padding: 12px 20px; border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: flex-end; gap: 8px;
      flex-shrink: 0; background: #FAFBFD; border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    /* ── Info grid (key-value pairs) ── */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .info-grid-3 { grid-template-columns: repeat(3,1fr); }
    .info-cell { background: var(--bg); border-radius: var(--radius); padding: 10px 12px; }
    .info-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    .info-value { font-size: 13px; font-weight: 600; color: var(--text-primary); }

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
    .rounded-pill { border-radius: 20px; }
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
      font-size: 13px; font-weight: 600; color: var(--text-muted);
      background: transparent; border: none; cursor: pointer;
      border-bottom: 2px solid transparent; margin-bottom: -1px; transition: var(--transition);
    }
    .nav-link:hover { color: var(--text-primary); }
    .nav-link.active { color: var(--primary); border-bottom-color: var(--primary); }
    .table-light thead th { background: #F9FAFB; }
    .table-hover tbody tr:hover td { background: #FAFBFD; }
    .align-middle td { vertical-align: middle; }
    .ps-4 { padding-left: 18px; }
    .form-control-sm { padding: 6px 10px; font-size: 12.5px; }
    .form-select-sm { padding: 6px 28px 6px 10px; font-size: 12.5px; }
    .btn-outline-secondary {
      background: var(--bg-card); border: 1px solid var(--border); color: var(--text-secondary);
      border-radius: var(--radius); padding: 8px 14px; font-size: 13px; font-weight: 600;
      cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: var(--transition);
    }
    .btn-outline-secondary:hover { border-color: var(--primary); color: var(--primary); }
    .btn-outline-primary {
      background: transparent; border: 1px solid var(--primary); color: var(--primary);
      border-radius: var(--radius); padding: 8px 14px; font-size: 13px; font-weight: 600;
      cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: var(--transition);
      text-decoration: none;
    }
    .btn-outline-primary:hover { background: var(--primary); color: #fff; }
    .btn-secondary { background: var(--border-light); border: 1px solid var(--border); color: var(--text-secondary); border-radius: var(--radius); padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
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
        coloplast: ['#00b0ca','#e6f7fa','#008ea4','#7fd8e4'],   // 브랜드 기본
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
    html.is-framed .page-body { padding-top: 14px; }
  </style>
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
        <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;flex:1;min-width:0;">
          <div class="app-brand-logo">CE</div>
          <div style="min-width:0;">
            <div class="app-brand-text">CE Admin</div>
            <div class="app-brand-sub">Coloplast Korea</div>
          </div>
        </a>
        <button class="menu-collapse-btn" id="menuCollapseBtn" onclick="toggleCollapse()" title="메뉴 접기/펼치기">
          <span class="ic-expanded"><i class="bx bx-sidebar"></i></span>
          <span class="ic-collapsed"><i class="bx bx-sidebar"></i></span>
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
          <span>메인</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        <div class="menu-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('dashboard') }}" data-title="대시보드">
            <i class="menu-icon bx bx-home-smile"></i><span>대시보드</span>
          </a>
        </div>
        </div></div>
        @endif

        {{-- ══ 환자 · 처방 ══ --}}
        @if($vis('patients', 'prescription-upload', 'prescriptions'))
        <div class="menu-group" data-menu-group="patient">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>환자 · 처방</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('patients'))
        <div class="menu-item {{ request()->routeIs('patients*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('patients.index') }}" data-title="환자관리">
            <i class="menu-icon bx bx-user-pin"></i><span>환자관리</span>
          </a>
        </div>
        @endif
        @if($vis('prescription-upload'))
        <div class="menu-item {{ request()->routeIs('prescriptions.upload') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('prescriptions.upload') }}" data-title="처방전 업로드">
            <i class="menu-icon bx bx-upload"></i><span>처방전 업로드</span>
          </a>
        </div>
        @endif
        @if($vis('prescriptions'))
        <div class="menu-item {{ request()->routeIs('prescriptions.index') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('prescriptions.index') }}" data-title="처방전 목록">
            <i class="menu-icon bx bx-file-blank"></i>
            <span>처방전 목록</span>
            @php $pendingCount = \App\Models\Prescription::whereIn('status',['review_needed','ocr_done'])->count(); @endphp
            @if($pendingCount > 0)
              <span class="menu-badge">{{ $pendingCount }}</span>
            @endif
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 주문 · 재구매 ══ --}}
        @if($vis('orders', 'repurchase', 'shop-orders'))
        <div class="menu-group" data-menu-group="order">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>주문 · 재구매</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('orders'))
        <div class="menu-item {{ request()->routeIs('orders*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('orders.index') }}" data-title="주문관리">
            <i class="menu-icon bx bx-cart-alt"></i>
            <span>주문관리</span>
            @php $orderCount = \App\Models\Order::where('status','pending')->count(); @endphp
            @if($orderCount > 0)
              <span class="menu-badge blue">{{ $orderCount }}</span>
            @endif
          </a>
        </div>
        @endif
        @if($vis('repurchase'))
        <div class="menu-item {{ request()->routeIs('repurchase*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('repurchase.index') }}" data-title="재구매 관리">
            <i class="menu-icon bx bx-refresh"></i>
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

        {{-- ══ 서류 · 동의 ══ --}}
        @if($vis('documents', 'privacy-consents'))
        <div class="menu-group" data-menu-group="docs">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>서류 · 동의</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('documents'))
        <div class="menu-item {{ request()->routeIs('documents*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('documents.index') }}" data-title="서류 관리">
            <i class="menu-icon bx bx-folder-open"></i>
            <span>서류 관리</span>
          </a>
        </div>
        @endif
        @if($vis('privacy-consents'))
        <div class="menu-item {{ request()->routeIs('privacy-consents*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('privacy-consents.index') }}" data-title="개인정보동의">
            <i class="menu-icon bx bx-check-shield"></i>
            <span>개인정보동의</span>
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 청구 · 회계 ══ --}}
        @if($vis('nhis', 'invoice', 'settlement', 'taxinvoice', 'cashbill'))
        <div class="menu-group" data-menu-group="billing">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>청구 · 회계</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('nhis'))
        <div class="menu-item {{ request()->routeIs('nhis*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('nhis.index') }}" data-title="NHIS 청구">
            <i class="menu-icon bx bx-plus-medical"></i>
            <span>NHIS 청구</span>
            @php $nhisCount = \App\Models\Order::where('nhis_claim_status','pending')->whereIn('status',['delivered','shipping','confirmed'])->count(); @endphp
            @if($nhisCount > 0)
              <span class="menu-badge">{{ $nhisCount }}</span>
            @endif
          </a>
        </div>
        @endif
        @if($vis('invoice'))
        <div class="menu-item {{ request()->routeIs('invoice*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('invoice.index') }}" data-title="계산서 발행">
            <i class="menu-icon bx bx-receipt"></i>
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
        @if($vis('settlement'))
        <div class="menu-item {{ request()->routeIs('settlement*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('settlement.index') }}" data-title="정산/회계">
            <i class="menu-icon bx bx-calculator"></i>
            <span>정산/회계</span>
            @php $unpaidCount = \App\Models\TossPayment::where('status','WAITING')->count(); @endphp
            @if($unpaidCount > 0)
              <span class="menu-badge">{{ $unpaidCount }}</span>
            @endif
          </a>
        </div>
        @endif
        @if($vis('taxinvoice'))
        <div class="menu-item {{ request()->routeIs('taxinvoice*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('taxinvoice.index') }}" data-title="전자세금계산서">
            <i class="menu-icon bx bx-file"></i>
            <span>전자세금계산서</span>
          </a>
        </div>
        @endif
        @if($vis('cashbill'))
        <div class="menu-item {{ request()->routeIs('cashbill*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('cashbill.index') }}" data-title="현금영수증">
            <i class="menu-icon bx bx-receipt"></i>
            <span>현금영수증</span>
          </a>
        </div>
        @endif
        </div></div>
        @endif

        {{-- ══ 발송 · 내역 ══ --}}
        @if($vis('fax', 'dispatch'))
        <div class="menu-group" data-menu-group="dispatch">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>발송 · 내역</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('fax'))
        <div class="menu-item {{ request()->routeIs('fax*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('fax.index') }}" data-title="팩스 발송">
            <i class="menu-icon bx bx-printer"></i>
            <span>팩스 발송</span>
          </a>
        </div>
        @endif
        @if($vis('dispatch'))
        <div class="menu-item {{ request()->routeIs('dispatch*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('dispatch.index') }}" data-title="발송/발행 내역">
            <i class="menu-icon bx bx-send"></i>
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
          <span>지원</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('institutional-notices'))
        <div class="menu-item {{ request()->routeIs('institutional-notices*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('institutional-notices.index') }}" data-title="기관 공지사항">
            <i class="menu-icon bx bx-buildings"></i>
            <span>기관 공지사항</span>
          </a>
        </div>
        @endif
        @if($vis('notices'))
        <div class="menu-item {{ request()->routeIs('notices*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('notices.index') }}" data-title="공지사항">
            <i class="menu-icon bx bx-bell"></i>
            <span>공지사항</span>
            <span class="menu-badge blue" id="noticNavBadge" style="display:none;"></span>
          </a>
        </div>
        @endif
        @if($vis('inquiries'))
        <div class="menu-item {{ request()->routeIs('inquiries*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('inquiries.index') }}" data-title="문의하기">
            <i class="menu-icon bx bx-support"></i>
            <span>문의하기</span>
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
          <a class="menu-link" href="{{ route('sr.index') }}" data-title="SR 관리">
            <i class="menu-icon bx bx-clipboard"></i>
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
        @if($vis('admin-users', 'permission-groups', 'delegation-settings', 'ocr-settings', 'nice-settings'))
        <div class="menu-group" data-menu-group="settings">
        <button type="button" class="menu-header" onclick="toggleMenuGroup(this)">
          <span>설정</span><span class="menu-group-badge"></span><i class="bx bx-chevron-down menu-caret"></i>
        </button>
        <div class="menu-group-items">
        @if($vis('admin-users'))
        <div class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('admin.users.index') }}" data-title="관리자 관리">
            <i class="menu-icon bx bx-shield-quarter"></i><span>관리자 관리</span>
          </a>
        </div>
        @endif
        @if($vis('permission-groups'))
        <div class="menu-item {{ request()->routeIs('permission-groups*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('permission-groups.index') }}" data-title="권한 그룹">
            <i class="menu-icon bx bx-lock-alt"></i><span>권한 그룹</span>
          </a>
        </div>
        @endif
        @if($vis('delegation-settings'))
        <div class="menu-item {{ request()->routeIs('delegation-settings*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('delegation-settings.edit') }}" data-title="위임장 설정">
            <i class="menu-icon bx bx-cog"></i>
            <span>위임장 설정</span>
          </a>
        </div>
        @endif
        @if($vis('ocr-settings'))
        <div class="menu-item {{ request()->routeIs('ocr-settings*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('ocr-settings.edit') }}" data-title="OCR 설정">
            <i class="menu-icon bx bx-scan"></i>
            <span>OCR 설정</span>
          </a>
        </div>
        @endif
        @if($vis('nice-settings'))
        <div class="menu-item {{ request()->routeIs('nice-settings*') ? 'active' : '' }}">
          <a class="menu-link" href="{{ route('nice-settings.edit') }}" data-title="본인확인 설정">
            <i class="menu-icon bx bx-id-card"></i>
            <span>본인확인 설정</span>
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

      {{-- User Footer --}}
      <div class="menu-footer">
        <div class="menu-user">
          <div class="menu-user-avatar">{{ mb_substr(Auth::user()->name, 0, 1) }}</div>
          <div class="menu-user-info flex-1" style="min-width:0;">
            <div class="menu-user-name text-truncate">{{ Auth::user()->name }}</div>
            <div class="menu-user-role">{{ Auth::user()->role === 'admin' ? 'CE 관리자' : '매니저' }}</div>
          </div>
          <form method="POST" action="{{ route('logout') }}" class="ms-auto menu-user-info">
            @csrf
            <button type="submit" class="btn-icon" title="로그아웃" style="width:32px;height:32px;font-size:15px;color:#64748b;">
              <i class="bx bx-log-out-circle"></i>
            </button>
          </form>
        </div>
      </div>
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
            <i class="bx bx-home-alt-2" style="font-size:11px;margin-right:3px;"></i>
            {!! $__env->yieldContent('breadcrumb', '홈') !!}
          </div>
        </div>

        <div class="navbar-actions">
          {{-- Notifications --}}
          <button class="btn-icon" title="알림">
            <i class="bx bx-bell"></i>
            <span class="notif-dot"></span>
          </button>
          {{-- Chat --}}
          <button class="btn-icon" id="chatToggleBtn" title="채팅" onclick="ChatPanel.toggle()">
            <i class="bx bx-chat"></i>
            <span class="notif-dot" id="chatUnreadDot" style="display:none;"></span>
          </button>
          {{-- Help --}}
          <button class="btn-icon" id="helpToggleBtn" title="도움말" onclick="HelpPanel.toggle()">
            <i class="bx bx-help-circle"></i>
          </button>
          {{-- SR 관리 --}}
          @perm('service-requests')
          <button class="btn-icon" id="srToggleBtn" title="SR 관리" onclick="SrPanel.toggle()">
            <i class="bx bx-clipboard"></i>
            <span class="btn-icon-badge" id="srBadge" style="display:none;"></span>
          </button>
          @endperm
          {{-- Theme Picker --}}
          <div class="theme-picker-wrap">
            <button class="btn-icon" id="themePickerBtn" title="테마 컬러" onclick="ThemePicker.togglePanel()">
              <i class="bx bx-palette"></i>
            </button>
            <div class="theme-panel" id="themePanel">
              <div class="theme-panel-title">테마 컬러</div>
              <div class="theme-swatches">
                <div class="theme-swatch" data-theme="coloplast" style="background:#00b0ca" title="콜로플라스트" onclick="ThemePicker.apply('coloplast')"></div>
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
          <div class="nav-divider"></div>
          {{-- User avatar --}}
          <div class="nav-user-avatar" title="{{ Auth::user()->name }}">
            {{ mb_substr(Auth::user()->name, 0, 1) }}
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

  // 저장된 상태 복원
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    menu.classList.add('collapsed');
    page.style.marginLeft = '68px';
    document.body.classList.add('menu-collapsed');
  }

  window.toggleCollapse = function() {
    const collapsed = menu.classList.toggle('collapsed');
    page.style.marginLeft = collapsed ? '68px' : '260px';
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

{{-- ══ 퀵 메뉴 ══ --}}
<style>
#quickMenu {
  position: fixed; right: 0; top: 72px;
  z-index: 900; display: flex; flex-direction: column; align-items: flex-end;
}
#quickMenuToggle {
  width: 44px; height: 44px; border-radius: 10px 0 0 10px;
  background: var(--primary);
  border: none; color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; box-shadow: -2px 2px 14px rgba(37,99,235,.3);
  transition: all .2s ease;
}
#quickMenuToggle:hover { background: var(--primary-dark); width: 48px; }
#quickMenuToggle.active { background: #1e293b; }
#quickMenuItems { display: none; flex-direction: column; align-items: flex-end; margin-top: 6px; gap: 4px; }
#quickMenuItems.open { display: flex; }
.quick-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; border-radius: 10px 0 0 10px;
  background: #fff; color: var(--text-primary);
  font-size: 13px; font-weight: 600;
  text-decoration: none; border: 1px solid var(--border); border-right: none;
  box-shadow: -3px 2px 12px rgba(75,70,92,.12);
  transition: all .18s; white-space: nowrap;
  animation: quickSlideIn .18s ease;
}
.quick-item:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }
.quick-item .bx { font-size: 16px; color: var(--primary); }
@keyframes quickSlideIn {
  from { opacity:0; transform:translateX(20px); }
  to   { opacity:1; transform:translateX(0); }
}
</style>

<div id="quickMenu">
  <button id="quickMenuToggle" title="퀵 메뉴" onclick="toggleQuickMenu()">
    <i class="bx bx-menu" id="quickMenuIcon"></i>
  </button>
  <div id="quickMenuItems">
    <button class="quick-item" onclick="toggleQuickMenu();NoticePanel.open();" style="animation-delay:.03s;border:none;cursor:pointer;width:100%;text-align:left;">
      <i class="bx bx-bell"></i> 공지사항
    </button>
    <button class="quick-item" onclick="toggleQuickMenu();IS_ADMIN?InquiryPanel.open():InquiryPanel.openCreate();" style="animation-delay:.07s;border:none;cursor:pointer;width:100%;text-align:left;">
      <i class="bx bx-support"></i> 문의하기
    </button>
  </div>
</div>

<script>
function toggleQuickMenu() {
  const items  = document.getElementById('quickMenuItems');
  const toggle = document.getElementById('quickMenuToggle');
  const icon   = document.getElementById('quickMenuIcon');
  const open   = items.classList.toggle('open');
  toggle.classList.toggle('active', open);
  icon.className = open ? 'bx bx-x' : 'bx bx-menu';
}
document.addEventListener('click', (e) => {
  if (!document.getElementById('quickMenu').contains(e.target)) {
    document.getElementById('quickMenuItems').classList.remove('open');
    document.getElementById('quickMenuToggle').classList.remove('active');
    document.getElementById('quickMenuIcon').className = 'bx bx-menu';
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
       <a href="/prescriptions/RX-1" data-ce-tab="RX-1 검수" data-ce-icon="bx-scan">
     동적으로 만든 마크업(알림 토스트·그리드 셀 등)에도 위임으로 적용된다.
     Ctrl/Cmd/Shift/가운데 클릭은 브라우저 기본 동작(새 창·새 탭)을 그대로 둔다. */
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[data-ce-tab]');
    if (!a) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const href = a.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript')) return;
    e.preventDefault();
    window.ceOpenTab(href, a.dataset.ceTab || a.textContent.trim(), a.dataset.ceIcon || 'bx-window-alt');
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
          <div class="modal-bd" style="font-size:13.5px;line-height:1.75;color:var(--text-primary);white-space:normal;">
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
      coloplast: { label: '콜로플라스트', primary: '#00b0ca', light: '#e6f7fa', dark: '#008ea4', accent: '#7fd8e4' },
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
          '<span style="font-size:10px;font-weight:700;color:#555;"><i class="fa-solid fa-thumbtack"></i> 메모 고정' +
            (m.rx_number ? '<span style="font-size:9px;font-weight:400;margin-left:4px;opacity:.7;">' + escH(m.rx_number) + '</span>' : '') +
          '</span>' +
          '<div style="display:flex;gap:4px;">' +
            '<button data-unpin="' + m.id + '" title="고정 해제" style="width:18px;height:18px;border:none;border-radius:3px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:#555;"><i class="fa-solid fa-thumbtack" style="transform:rotate(45deg);"></i></button>' +
            '<button data-close="' + m.id + '" title="닫기" style="width:18px;height:18px;border:none;border-radius:3px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:#555;">×</button>' +
          '</div>' +
        '</div>' +
        '<div style="padding:8px;">' +
          '<textarea data-id="' + m.id + '" style="width:100%;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:60px;">' + escH(m.content) + '</textarea>' +
          '<div style="font-size:10px;color:#aaa;margin-top:2px;">' + escH(m.created_at) + ' · ' + escH(m.user_name) + '</div>' +
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
  box-shadow: -4px 0 32px rgba(0,0,0,.15);
  display: flex; flex-direction: column; z-index: 1000;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#chatPanel.open { right: 0; }

.chat-header {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  background: #0f172a; color: #fff; flex-shrink: 0;
}
.chat-header-title { font-size: 14px; font-weight: 700; flex: 1; }
.chat-header-close {
  background: none; border: none; color: #94a3b8;
  font-size: 18px; cursor: pointer; padding: 2px 6px; border-radius: 4px;
}
.chat-header-close:hover { color: #fff; background: rgba(255,255,255,.1); }

.chat-body { display: flex; flex: 1; overflow: hidden; }

/* ── Room List ── */
.chat-rooms {
  width: 240px; border-right: 1px solid var(--border);
  display: flex; flex-direction: column; flex-shrink: 0; background: #f8fafc;
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
  background: #f8fafc;
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
  border-bottom: 1px solid #f1f5f9; position: relative;
}
.chat-room-item:hover { background: #f1f5f9; }
.chat-room-item.active { background: var(--primary-light); }
.chat-room-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
}
.chat-room-avatar.group { background: var(--purple); }
.chat-room-info { flex: 1; min-width: 0; }
.chat-room-name { font-size: 12px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-room-preview { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-room-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; flex-shrink: 0; }
.chat-room-time { font-size: 10px; color: var(--text-muted); }
.chat-room-badge {
  background: var(--danger); color: #fff;
  font-size: 10px; font-weight: 700; padding: 1px 5px;
  border-radius: 10px; min-width: 16px; text-align: center;
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
  padding: 10px 14px; border-radius: 14px;
  font-size: 14px; line-height: 1.6; word-break: break-word;
  background: #f1f5f9; color: var(--text-primary);
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
  padding: 8px 12px; border-radius: 10px;
  background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.3);
}
.msg-row:not(.mine) .msg-file { background: #f8fafc; border-color: var(--border); }
.msg-file i { font-size: 18px; }
.msg-file-info { min-width: 0; }
.msg-file-name { font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.msg-file-size { font-size: 10px; opacity: .7; }

/* ── 파일 붙여넣기 미리보기 ── */
#chatPastePreview {
  display: none; flex-shrink: 0;
  padding: 8px 12px; border-top: 1px solid var(--border);
  background: #f8fafc; gap: 8px; align-items: center;
}
#chatPastePreview.show { display: flex; }
#chatPasteThumb { max-height: 60px; max-width: 80px; border-radius: 6px; object-fit: cover; }
#chatPasteFileName { font-size: 12px; font-weight: 600; flex: 1; }
#chatPasteClear {
  background: none; border: none; color: var(--danger);
  font-size: 16px; cursor: pointer; padding: 2px 6px; border-radius: 4px;
}
#chatPasteClear:hover { background: var(--danger-light); }

/* ── Input Area ── */
.chat-input-area {
  padding: 14px 16px; border-top: 2px solid var(--border);
  display: flex; gap: 10px; align-items: flex-end; flex-shrink: 0;
  background: #f8fafc;
}
#chatInput {
  flex: 1; resize: none; border: 1.5px solid var(--border); border-radius: 12px;
  padding: 11px 14px; font-size: 14px; line-height: 1.6;
  min-height: 44px; max-height: 160px; outline: none; font-family: inherit;
  background: #fff;
}
#chatInput:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(77,107,140,.12); }
#chatInput::placeholder { color: #b0bec5; }
.chat-send-btn {
  width: 44px; height: 44px; border-radius: 12px;
  background: var(--primary); border: none; color: #fff;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background .15s;
}
.chat-send-btn:hover { background: var(--primary-dark); }
.chat-send-btn i { font-size: 16px; }
.chat-file-btn {
  width: 40px; height: 40px; border-radius: 10px; border: 1.5px solid var(--border);
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
  background: #fff; border-radius: 14px; padding: 24px;
  width: 360px; box-shadow: var(--shadow-lg);
}
.chat-modal-title { font-size: 15px; font-weight: 700; margin-bottom: 16px; }
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
  padding: 0 4px; border-radius: 10px; background: var(--danger); color: #fff;
  font-size: 9.5px; font-weight: 700; line-height: 15px; text-align: center;
}
#srOverlay { display: none; position: fixed; inset: 0; z-index: 1000; }
#srOverlay.show { display: block; }

#srPanel {
  position: fixed; top: 0; right: -860px; width: 860px; max-width: 96vw; height: 100vh;
  background: var(--bg-card); border-left: 1px solid var(--border);
  box-shadow: -6px 0 40px rgba(0,0,0,.18);
  display: flex; flex-direction: column; z-index: 1001;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#srPanel.open { right: 0; }
.sr-header {
  display: flex; align-items: center; gap: 9px;
  padding: 15px 18px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.sr-header-title { font-size: 15px; font-weight: 800; color: var(--text-primary); }
.sr-header-sub { font-size: 11.5px; color: var(--text-muted); }
.sr-header-close {
  margin-left: auto; width: 28px; height: 28px; border: none; background: none;
  color: var(--text-muted); font-size: 20px; line-height: 1; cursor: pointer; border-radius: 6px;
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
  min-width: 18px; padding: 0 5px; height: 17px; border-radius: 10px;
  font-size: 10px; font-weight: 700; background: var(--border-light); color: var(--text-muted);
  display: inline-flex; align-items: center; justify-content: center;
}
.sr-tab.active .cnt { background: var(--primary); color: #fff; }

.sr-body { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 18px 18px; }
.sr-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.sr-field label { font-size: 12px; font-weight: 700; color: var(--text-secondary); }
.sr-field input[type=text], .sr-field select, .sr-field textarea {
  padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px;
  font-size: 13.5px; font-family: inherit; background: #fff;
}
.sr-field textarea { min-height: 110px; resize: vertical; }
.sr-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.sr-hint { font-size: 11px; color: var(--text-muted); line-height: 1.6; }
.sr-detail {
  border: 1px solid var(--border); border-radius: var(--radius);
  padding: 14px 16px; margin-bottom: 14px; background: var(--bg);
}
.sr-detail h5 { margin: 0 0 4px; font-size: 14px; font-weight: 800; color: var(--text-primary); }
.sr-detail .meta { font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px; }
.sr-detail .body { font-size: 13px; line-height: 1.8; white-space: pre-wrap; color: var(--text-primary); }
.sr-answer-box {
  margin-top: 12px; padding: 12px 14px; background: var(--primary-light);
  border: 1px solid var(--primary-accent, var(--border)); border-radius: 8px;
}
.sr-answer-box .lbl { font-size: 10.5px; font-weight: 800; color: var(--primary); margin-bottom: 5px; }
.sr-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.sr-b-open        { background: var(--warning-light); color: var(--warning); }
.sr-b-in_progress { background: var(--primary-light);  color: var(--primary); }
.sr-b-answered    { background: var(--success-light);  color: var(--success); }
.sr-b-closed      { background: var(--border-light);   color: var(--text-muted); }

</style>

{{-- Chat Panel HTML --}}
<div id="chatOverlay" onclick="ChatPanel.close()"></div>

<div id="chatPanel">
  {{-- 패널 헤더 --}}
  <div class="chat-header">
    <i class="fa-solid fa-comments" style="font-size:16px;color:#94a3b8;"></i>
    <span class="chat-header-title">채팅</span>
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
        <div id="shopCustomerBar" style="display:none;background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:7px 14px;font-size:12px;color:#374151;flex-shrink:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <i class="fa-solid fa-user-circle" style="color:#64748b;"></i>
            <span id="shopCustomerName" style="font-weight:600;"></span>
            <span id="shopCustomerPhone" style="color:#64748b;"></span>
            <span id="shopCustomerEmail" style="color:#94a3b8;font-size:11px;"></span>
            <a id="shopCustomerPatientLink" href="#" target="_blank"
               style="margin-left:auto;font-size:11px;background:var(--primary);color:#fff;padding:2px 8px;border-radius:4px;text-decoration:none;display:none;">
              환자 기록 보기
            </a>
            <span id="shopCustomerNoPatient" style="margin-left:auto;font-size:11px;color:#94a3b8;display:none;">등록된 환자 없음</span>
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
      <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">유형</label>
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
      <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">그룹 이름</label>
      <input type="text" id="chatGroupName" class="form-control" style="margin-top:4px;" placeholder="그룹 이름 입력">
    </div>
    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);">대화 상대</label>
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
      <select id="srFilterStatus" onchange="SrPanel.load()"
              style="height:32px;padding:0 10px;border:1px solid var(--border);border-radius:8px;font-size:12.5px;">
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
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
const SrPanel = (() => {
  const LIST_URL  = BASE_URL + '/sr/list';
  const STORE_URL = BASE_URL + '/sr';
  const STATUS_CLS = { open:'sr-b-open', in_progress:'sr-b-in_progress', answered:'sr-b-answered', closed:'sr-b-closed' };

  let _grid = null, _rows = [], _sel = null, _loaded = false;
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  function toggle() { document.getElementById('srPanel').classList.contains('open') ? close() : open(); }

  function open() {
    document.getElementById('srPanel').classList.add('open');
    document.getElementById('srOverlay').classList.add('show');
    const lbl = document.getElementById('srPageLabel');
    if (lbl) lbl.value = document.title.replace(/\s*\|.*$/, '').trim() || location.pathname;
    if (!_loaded) { buildGrid(); load(); _loaded = true; }
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
      height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
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

  // ── 패널 열기/닫기 ──────────────────────────────────────────
  function toggle() {
    const panel = document.getElementById('chatPanel');
    if (panel.classList.contains('open')) close();
    else open();
  }

  function open() {
    document.getElementById('chatPanel').classList.add('open');
    document.getElementById('chatOverlay').classList.add('show');
    loadRooms();
  }

  function close() {
    document.getElementById('chatPanel').classList.remove('open');
    document.getElementById('chatOverlay').classList.remove('show');
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

    const row = document.createElement('div');
    row.className = 'msg-row' + (mine ? ' mine' : '');
    row.dataset.msgId = m.id;
    row.innerHTML = `
      <div class="msg-avatar ${mine ? 'mine-av' : ''}">${initials}</div>
      <div class="msg-content">
        ${!mine ? `<div class="msg-sender-label">보낸 사람</div><div class="msg-name">${escHtml(m.user_name)}</div>${m.screen_name ? `<div class="msg-screen-name">화면명: ${escHtml(m.screen_name)}</div>` : ''}` : ''}
        ${bodyHtml}
        <div class="msg-time">${m.time_label}</div>
      </div>`;
    return row;
  }

  function appendMessage(m) {
    const el  = buildMessageEl(m);
    const box = document.getElementById('chatMessages');
    box.appendChild(el);
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

    input.value = '';
    clearPaste();

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

  return { toggle, open, close, loadRooms, selectRoom, loadMore, send, clearPaste, openNewRoom, closeNewRoom, createRoom, lightbox, setCategory };
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
.consent-notif-progress { height:3px; background:var(--border); border-radius:2px; overflow:hidden; }
.consent-notif-progress-bar { height:100%; background:var(--success); border-radius:2px; width:100%; transition:width linear; }
.consent-notif.declined .consent-notif-progress-bar { background:var(--danger); }
</style>
<script>
// ── 위임동의 실시간 알림 ─────────────────────────────────────
(function () {
  if (!pusherClient) return;

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
        <a href="${rxUrl}" data-ce-tab="${escHtml(data.rx_number ?? '처방전')} 검수" data-ce-icon="bx-scan"
           style="font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;">
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
    showConsentNotif(data);
    // order 페이지에서 버튼 업데이트할 수 있도록 커스텀 이벤트 발송
    window.dispatchEvent(new CustomEvent('ce:consentResult', { detail: data }));
  });
  adminCh.bind('prescription.uploaded', function (data) {
    showPrescriptionNotif(data);
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
.rx-notif-progress { height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; }
.rx-notif-progress-bar { height: 100%; background: var(--primary); border-radius: 2px; width: 100%; }
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
      <a href="${rxUrl}" data-ce-tab="${escHtml(data.rx_number ?? '처방전')} 검수" data-ce-icon="bx-scan"
         style="font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;">
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
  box-shadow: -4px 0 32px rgba(0,0,0,.15);
  display: flex; flex-direction: column; z-index: 1000;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
.side-panel.open { right: 0; }

#sidePanelOverlay {
  display: none; position: fixed; inset: 0; z-index: 999;
  background: rgba(0,0,0,.2);
}
#sidePanelOverlay.show { display: block; }

.sp-header {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  background: #0f172a; color: #fff; flex-shrink: 0;
}
.sp-title { font-size: 14px; font-weight: 700; flex: 1; }
.sp-close, .sp-back {
  background: none; border: none; color: #94a3b8;
  font-size: 16px; cursor: pointer; padding: 4px 8px; border-radius: 4px;
  display: flex; align-items: center; gap: 4px; line-height: 1;
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
  font-size: 14px; font-weight: 600; color: var(--text-primary);
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
.inq-title { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inq-meta  { font-size: 11px; color: var(--text-muted); margin-top: 2px; display: flex; gap: 8px; }

/* ── 문의 스레드 ── */
.inq-thread { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.inq-thread-info {
  padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid var(--border);
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
.inq-msg-av.admin-av  { background: #10b981; }
.inq-msg-av.user-av   { background: #64748b; }
.inq-msg-av.mine-av   { background: var(--primary); }
.inq-msg-body { max-width: 80%; }
.inq-msg-name { font-size: 10px; color: var(--text-muted); margin-bottom: 3px; }
.inq-msg.mine .inq-msg-name { text-align: right; }
.inq-msg-bubble {
  padding: 10px 14px; border-radius: 14px;
  font-size: 13px; line-height: 1.7; word-break: break-word;
  background: #f1f5f9; color: var(--text-primary);
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
  border-radius: 10px; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.3);
}
.inq-msg:not(.mine) .inq-msg-file { background: #f8fafc; border-color: var(--border); }
.inq-msg-file-name { font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }

/* 붙여넣기 미리보기 */
.inq-paste-preview {
  display: none; flex-shrink: 0; padding: 8px 14px;
  border-top: 1px solid var(--border); background: #f0f9ff;
  align-items: center; gap: 8px;
}
.inq-paste-preview.show { display: flex; }
.inq-paste-img { max-height: 56px; max-width: 80px; border-radius: 6px; object-fit: cover; }
.inq-paste-name { font-size: 12px; font-weight: 600; flex: 1; color: var(--text-primary); }
.inq-paste-clear { background: none; border: none; color: var(--danger); font-size: 18px; cursor: pointer; padding: 2px 6px; border-radius: 4px; line-height: 1; }
.inq-paste-clear:hover { background: var(--danger-light); }

/* 스레드 입력 영역 */
.inq-thread-input {
  padding: 10px 14px; border-top: 2px solid var(--border);
  background: #f8fafc; flex-shrink: 0;
  display: flex; gap: 8px; align-items: flex-end;
}
#inqThreadInput {
  flex: 1; resize: none; border: 1.5px solid var(--border); border-radius: 10px;
  padding: 9px 12px; font-size: 13px; line-height: 1.6; font-family: inherit;
  min-height: 40px; max-height: 120px; outline: none; background: #fff;
}
#inqThreadInput:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,176,202,.12); }

/* ── 문의 작성 폼 ── */
.sp-form { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.sp-form .form-control { font-size: 13px; }
.sp-form-actions { display: flex; justify-content: flex-end; gap: 8px; }

/* ── 검색/필터 바 ── */
.sp-toolbar {
  padding: 10px 14px; border-bottom: 1px solid var(--border);
  display: flex; gap: 8px; align-items: center; flex-shrink: 0;
  background: #f8fafc;
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
    <i class="fa-solid fa-bullhorn" style="font-size:14px;color:#4FC8DA;"></i>
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
    <i class="fa-solid fa-headset" style="font-size:14px;color:#34d399;"></i>
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
              ${n.is_pinned ? '<span style="display:inline-block;background:var(--danger);color:#fff;font-size:9px;font-weight:700;padding:1px 6px;border-radius:3px;margin-right:6px;vertical-align:middle;">공지</span>' : ''}
              ${!n.is_read ? '<span class="ni-unread-dot"></span>' : ''}
              ${_esc(n.title)}
            </div>
            <div class="ni-meta">
              <span><i class="fa-solid fa-user" style="font-size:9px;margin-right:2px;"></i>${_esc(n.author)}</span>
              <span>${n.date}</span>
              <span><i class="fa-solid fa-eye" style="font-size:9px;margin-right:2px;"></i>${n.views}</span>
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
      ? `<span style="background:var(--danger);color:#fff;border-radius:10px;padding:0 5px;font-size:10px;margin-left:4px;">${pending_count}</span>`
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
      <div style="padding:10px 14px;border-top:2px solid var(--border);background:#f8fafc;flex-shrink:0;">
        <textarea id="inqMsgBody" style="width:100%;resize:none;border:1.5px solid var(--border);border-radius:10px;padding:9px 12px;font-size:13px;line-height:1.6;font-family:inherit;min-height:40px;max-height:120px;outline:none;background:#fff;box-sizing:border-box;" placeholder="답변 입력... (Ctrl+V 이미지 가능, Enter 전송, Shift+Enter 줄바꿈)" rows="2"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
          <label class="btn btn-sm btn-outline" style="font-size:11px;cursor:pointer;padding:4px 8px;margin:0;">
            <i class="fa-solid fa-paperclip"></i> 파일
            <input type="file" id="inqMsgFile" style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
          </label>
          <button onclick="InquiryPanel.doAddMessage()" class="btn btn-primary btn-sm" style="font-size:11px;">
            <i class="fa-solid fa-paper-plane"></i> 답변 전송
          </button>
        </div>
      </div>` : `<div style="padding:14px 16px;border-top:1px solid var(--border);background:#f8fafc;flex-shrink:0;font-size:12px;color:var(--text-muted);text-align:center;"><i class="fa-solid fa-clock" style="margin-right:5px;"></i>답변을 기다리는 중입니다.</div>`;

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
    const adminMark = m.is_admin ? '<span style="font-size:9px;background:var(--primary);color:#fff;border-radius:3px;padding:1px 4px;margin-left:4px;">관리자</span>' : '';

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
        <div style="padding:10px 12px;background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:flex-start;gap:10px;">
          <i class="fa-solid fa-location-dot" style="color:var(--primary);margin-top:2px;font-size:13px;flex-shrink:0;"></i>
          <div style="flex:1;min-width:0;">
            <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">현재 페이지</div>
            <div style="font-size:13px;font-weight:600;color:var(--text-primary);">${_esc(pageTitle)}</div>
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
  box-shadow: -4px 0 32px rgba(0,0,0,.12);
  display: flex; flex-direction: column; z-index: 999;
  transition: right .28s cubic-bezier(.4,0,.2,1);
}
#helpPanel.open { right: 0; }
.help-header {
  display: flex; align-items: center; gap: 8px;
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  background: var(--bg); flex-shrink: 0;
}
.help-header-icon { font-size: 22px; color: var(--primary); }
.help-header-title { font-size: 14px; font-weight: 700; flex: 1; color: var(--text-primary); }
.help-header-close {
  background: none; border: none; color: var(--text-muted);
  font-size: 20px; cursor: pointer; padding: 2px 6px; border-radius: 4px; line-height: 1;
}
.help-header-close:hover { color: var(--text-primary); background: var(--border-light); }
.help-body { flex: 1; overflow-y: auto; padding: 16px; }
.help-tour-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;
  background: var(--primary-light); color: var(--primary);
  border: 1.5px solid var(--primary); font-size: 13px; font-weight: 600;
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
.help-item-text strong { color: var(--text-primary); display: block; margin-bottom: 1px; font-size: 12.5px; }
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
.tour-title { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.tour-body { font-size: 12.5px; color: var(--text-secondary); line-height: 1.65; margin-bottom: 16px; }
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
.tour-btn { padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: var(--transition); }
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
      body: '<b>처방전·환자·주문·NHIS·정산</b> 등 주요 기능이 이 메뉴에 있습니다. 아이콘을 클릭하면 메뉴가 접힙니다.'
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
    {
      selector: '#quickMenu',
      title: '빠른 메뉴',
      body: '우측 하단 버튼으로 처방전 업로드, 환자 추가, 문의 등을 빠르게 실행할 수 있습니다.'
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
