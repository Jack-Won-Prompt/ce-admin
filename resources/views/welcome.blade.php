<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 처방전 관리 플랫폼</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary:      #1B66F5;
      --primary-dark: #1250C4;
      --bg-dark:      #060E1C;
      --bg-card:      rgba(255,255,255,.04);
      --border-dark:  rgba(255,255,255,.08);
      --text-muted:   rgba(255,255,255,.5);
      --text-dim:     rgba(255,255,255,.3);
      --radius:       14px;
    }

    html { scroll-behavior: smooth; }
    body {
      font-family: 'Pretendard Variable', 'Pretendard', -apple-system, BlinkMacSystemFont,
                   'Apple SD Gothic Neo', 'Noto Sans KR', 'Segoe UI', sans-serif;
      background: var(--bg-dark);
      color: #fff;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* ─── GLOBAL DECO ─── */
    .blob { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; filter: blur(90px); }
    .blob-1 { width:600px;height:600px; top:-200px;right:-200px; background:rgba(27,102,245,.08); }
    .blob-2 { width:500px;height:500px; bottom:-150px;left:-150px; background:rgba(18,80,196,.06); }
    .blob-3 { width:300px;height:300px; top:50%;left:40%; background:rgba(27,102,245,.04); }

    /* ─── NAVBAR ─── */
    .navbar {
      position: fixed; top:0; left:0; right:0; z-index:100;
      display:flex; align-items:center; justify-content:space-between;
      padding:0 64px; height:68px;
      background:rgba(6,14,28,.85); backdrop-filter:blur(16px);
      border-bottom:1px solid var(--border-dark);
    }
    .nav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
    .nav-logo {
      width:38px; height:38px; border-radius:10px;
      background:var(--primary);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:900; letter-spacing:-1px;
      box-shadow:0 4px 16px rgba(27,102,245,.45);
    }
    .nav-name { font-size:1.1rem; font-weight:700; color:#fff; }
    .nav-sub  { font-size:10px; color:var(--text-dim); letter-spacing:.6px; text-transform:uppercase; }
    .btn-nav-login {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 22px; border-radius:8px;
      background:var(--primary);
      color:#fff; font-size:13.5px; font-weight:700;
      text-decoration:none; font-family:inherit;
      box-shadow:0 4px 16px rgba(27,102,245,.35);
      transition:background .2s, box-shadow .2s;
    }
    .btn-nav-login:hover { background:var(--primary-dark); box-shadow:0 6px 24px rgba(27,102,245,.5); }

    /* ─── HERO ─── */
    .hero {
      position:relative; z-index:1;
      min-height:100vh;
      display:flex; align-items:center; justify-content:center;
      text-align:center; padding:120px 24px 80px;
    }
    .hero-inner { max-width:780px; }
    .hero-tag {
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(27,102,245,.12); border:1px solid rgba(27,102,245,.25);
      border-radius:20px; padding:6px 16px;
      font-size:12px; font-weight:700; color:#93C5FD;
      letter-spacing:.4px; margin-bottom:28px;
    }
    .hero-title {
      font-size:clamp(2.4rem,6vw,4rem);
      font-weight:800; line-height:1.15;
      letter-spacing:-.6px; margin-bottom:20px;
    }
    .hero-title em {
      font-style:normal;
      background:linear-gradient(90deg,#93C5FD,#60A5FA,#3B82F6);
      -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .hero-desc { font-size:16px; color:var(--text-muted); line-height:1.8; max-width:560px; margin:0 auto 40px; }
    .hero-actions { display:flex; align-items:center; justify-content:center; gap:14px; flex-wrap:wrap; }
    .btn-primary {
      display:inline-flex; align-items:center; gap:9px;
      padding:14px 32px; border-radius:10px;
      background:var(--primary);
      color:#fff; font-size:15px; font-weight:700;
      text-decoration:none; font-family:inherit;
      box-shadow:0 8px 28px rgba(27,102,245,.45);
      transition:background .2s, transform .15s, box-shadow .2s;
    }
    .btn-primary:hover { background:var(--primary-dark); transform:translateY(-1px); box-shadow:0 12px 36px rgba(27,102,245,.55); }
    .btn-ghost {
      display:inline-flex; align-items:center; gap:8px;
      padding:14px 28px; border-radius:10px;
      border:1.5px solid var(--border-dark);
      color:rgba(255,255,255,.7); font-size:14px; font-weight:600;
      text-decoration:none; font-family:inherit;
      transition:border-color .2s, color .2s;
    }
    .btn-ghost:hover { border-color:rgba(27,102,245,.4); color:#93C5FD; }

    .hero-stats {
      display:flex; align-items:center; justify-content:center; gap:40px;
      margin-top:56px; padding-top:48px;
      border-top:1px solid var(--border-dark); flex-wrap:wrap;
    }
    .hero-stat-num   { font-size:2rem; font-weight:800; color:#93C5FD; line-height:1; }
    .hero-stat-label { font-size:12px; color:var(--text-muted); margin-top:4px; }

    /* ─── SECTION ─── */
    .section { position:relative; z-index:1; padding:96px 24px; }
    .section-inner { max-width:1100px; margin:0 auto; }
    .section-tag {
      display:inline-flex; align-items:center; gap:6px;
      background:rgba(27,102,245,.1); border-radius:20px;
      padding:4px 14px; font-size:12px; font-weight:700; color:#93C5FD; margin-bottom:14px;
    }
    .section-title { font-size:clamp(1.7rem,4vw,2.4rem); font-weight:800; letter-spacing:-.4px; margin-bottom:12px; }
    .section-desc  { font-size:15px; color:var(--text-muted); line-height:1.7; max-width:520px; }

    /* Feature grid */
    .feature-grid {
      display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
      gap:20px; margin-top:56px;
    }
    .feature-card {
      background:var(--bg-card); border:1px solid var(--border-dark);
      border-radius:var(--radius); padding:28px;
      transition:border-color .2s, transform .2s;
    }
    .feature-card:hover { border-color:rgba(27,102,245,.35); transform:translateY(-3px); }
    .feature-icon {
      width:48px; height:48px; border-radius:12px;
      display:flex; align-items:center; justify-content:center;
      font-size:22px; margin-bottom:18px;
    }
    .feature-icon.blue   { background:rgba(27,102,245,.18); color:#93C5FD; }
    .feature-icon.cyan   { background:rgba(6,182,212,.12);  color:#67E8F9; }
    .feature-icon.green  { background:rgba(16,185,129,.12); color:#6EE7B7; }
    .feature-icon.amber  { background:rgba(245,158,11,.12); color:#FCD34D; }
    .feature-icon.violet { background:rgba(124,58,237,.12); color:#C4B5FD; }
    .feature-icon.rose   { background:rgba(239,68,68,.12);  color:#FCA5A5; }
    .feature-title { font-size:15px; font-weight:700; margin-bottom:8px; }
    .feature-desc  { font-size:13px; color:var(--text-muted); line-height:1.65; }

    /* ─── WORKFLOW ─── */
    .workflow-section {
      background:rgba(255,255,255,.025);
      border-top:1px solid var(--border-dark);
      border-bottom:1px solid var(--border-dark);
    }
    .workflow-steps {
      display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
      gap:0; margin-top:56px;
      border:1px solid var(--border-dark); border-radius:var(--radius); overflow:hidden;
    }
    .workflow-step {
      padding:32px 20px; text-align:center;
      border-right:1px solid var(--border-dark); position:relative;
    }
    .workflow-step:last-child { border-right:none; }
    .workflow-num {
      width:36px; height:36px; border-radius:50%;
      background:rgba(27,102,245,.15); border:1.5px solid rgba(27,102,245,.3);
      color:#93C5FD; font-size:14px; font-weight:800;
      display:flex; align-items:center; justify-content:center;
      margin:0 auto 14px;
    }
    .workflow-step-title { font-size:13.5px; font-weight:700; margin-bottom:6px; }
    .workflow-step-desc  { font-size:12px; color:var(--text-muted); line-height:1.6; }

    /* ─── ORG GRID ─── */
    .org-grid { display:flex; flex-wrap:wrap; gap:14px; margin-top:40px; }
    .org-badge {
      display:flex; align-items:center; gap:10px;
      background:var(--bg-card); border:1px solid var(--border-dark);
      border-radius:10px; padding:12px 18px;
      font-size:13px; color:rgba(255,255,255,.7);
    }
    .org-badge i { font-size:20px; color:#93C5FD; }

    /* ─── CTA ─── */
    .cta-section { text-align:center; padding:100px 24px; position:relative; z-index:1; }
    .cta-box {
      max-width:640px; margin:0 auto;
      background:linear-gradient(135deg,rgba(27,102,245,.08),rgba(18,80,196,.04));
      border:1px solid rgba(27,102,245,.2);
      border-radius:20px; padding:60px 40px;
    }
    .cta-icon {
      width:64px; height:64px; border-radius:16px;
      background:var(--primary);
      display:flex; align-items:center; justify-content:center;
      font-size:28px; margin:0 auto 24px;
      box-shadow:0 8px 28px rgba(27,102,245,.4);
    }
    .cta-title { font-size:1.9rem; font-weight:800; margin-bottom:12px; letter-spacing:-.3px; }
    .cta-desc  { font-size:14.5px; color:var(--text-muted); line-height:1.7; margin-bottom:32px; }

    /* ─── FOOTER ─── */
    .footer {
      border-top:1px solid var(--border-dark); padding:32px 64px;
      display:flex; align-items:center; justify-content:space-between;
      flex-wrap:wrap; gap:16px;
      font-size:12.5px; color:var(--text-dim);
      position:relative; z-index:1;
    }
    .footer-brand { display:flex; align-items:center; gap:10px; }
    .footer-logo {
      width:28px; height:28px; border-radius:7px;
      background:var(--primary);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-size:10px; font-weight:900;
    }
    .footer-links { display:flex; gap:24px; }
    .footer-links a { color:var(--text-dim); text-decoration:none; transition:color .2s; }
    .footer-links a:hover { color:#93C5FD; }

    @media (max-width:768px) {
      .navbar { padding:0 24px; }
      .hero { padding:100px 20px 60px; }
      .hero-stats { gap:28px; }
      .section { padding:64px 20px; }
      .workflow-steps { grid-template-columns:1fr; }
      .workflow-step { border-right:none; border-bottom:1px solid var(--border-dark); }
      .workflow-step:last-child { border-bottom:none; }
      .footer { padding:24px; flex-direction:column; align-items:center; text-align:center; }
    }
  </style>
</head>
<body>

  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  {{-- NAVBAR --}}
  <nav class="navbar">
    <a class="nav-brand" href="{{ route('welcome') }}">
      <div class="nav-logo">CE</div>
      <div>
        <div class="nav-name">CE Admin</div>
        <div class="nav-sub">Coloplast Korea</div>
      </div>
    </a>
    <a class="btn-nav-login" href="{{ route('login') }}">
      <i class="bx bx-log-in-circle"></i> 로그인
    </a>
  </nav>

  {{-- HERO --}}
  <section class="hero">
    <div class="hero-inner">
      <div class="hero-tag"><i class="bx bx-star"></i> 처방전 통합 관리 플랫폼</div>
      <h1 class="hero-title">처방전 관리의<br><em>새로운 기준</em>을 만듭니다</h1>
      <p class="hero-desc">
        OCR 자동화·NHIS 급여 청구·실시간 주문 연계·기관 정책 모니터링까지<br>
        병원 행정 업무를 하나의 플랫폼에서 처리하세요.
      </p>
      <div class="hero-actions">
        <a class="btn-primary" href="{{ route('login') }}"><i class="bx bx-log-in-circle"></i> 지금 시작하기</a>
        <a class="btn-ghost" href="#features"><i class="bx bx-info-circle"></i> 주요 기능 보기</a>
      </div>
      <div class="hero-stats">
        <div><div class="hero-stat-num">AI</div><div class="hero-stat-label">OCR 자동 인식</div></div>
        <div><div class="hero-stat-num">3</div><div class="hero-stat-label">정부기관 연동</div></div>
        <div><div class="hero-stat-num">실시간</div><div class="hero-stat-label">주문·배송 추적</div></div>
        <div><div class="hero-stat-num">24/7</div><div class="hero-stat-label">자동 수집</div></div>
      </div>
    </div>
  </section>

  {{-- FEATURES --}}
  <section class="section" id="features">
    <div class="section-inner">
      <div class="section-tag"><i class="bx bx-cube-alt"></i> 핵심 기능</div>
      <h2 class="section-title">업무 전 과정을<br>하나의 화면으로</h2>
      <p class="section-desc">처방 접수부터 건강보험 청구, 배송 완료까지 모든 단계를 자동화합니다.</p>
      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon blue"><i class="bx bx-scan"></i></div>
          <div class="feature-title">AI OCR 처방전 인식</div>
          <div class="feature-desc">AI 기반 Vision이 처방전 이미지를 자동 분석해 환자 정보, 질병 코드 등을 추출합니다.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon cyan"><i class="bx bx-plus-medical"></i></div>
          <div class="feature-title">NHIS 급여 청구 연동</div>
          <div class="feature-desc">건강보험심사평가원 팩스 자동 전송, 청구 상태 추적, 급여 승인·반려 내역을 통합 관리합니다.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon green"><i class="bx bx-cart-alt"></i></div>
          <div class="feature-title">Withworks 주문 자동 연계</div>
          <div class="feature-desc">처방 승인 즉시 Withworks 판매주문(SO)을 자동 생성하고 배송 현황을 실시간으로 추적합니다.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon amber"><i class="bx bx-buildings"></i></div>
          <div class="feature-title">기관 공지사항 자동 수집</div>
          <div class="feature-desc">보건복지부·심사평가원·국민건강보험공단 정책 공지를 매일 자동 수집하여 수가 변경 등 핵심 정보를 즉시 파악합니다.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon violet"><i class="bx bx-receipt"></i></div>
          <div class="feature-title">세금계산서·현금영수증 발행</div>
          <div class="feature-desc">API를 통해 전자 세금계산서·현금영수증을 발행하고 발송 이력을 한곳에서 관리합니다.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon rose"><i class="bx bx-bar-chart-alt-2"></i></div>
          <div class="feature-title">통합 대시보드</div>
          <div class="feature-desc">처방·주문·정산·NHIS 청구 현황을 실시간 카드·차트로 표시하고 재구매 일정을 캘린더로 관리합니다.</div>
        </div>
      </div>
    </div>
  </section>

  {{-- WORKFLOW --}}
  <section class="section workflow-section">
    <div class="section-inner">
      <div class="section-tag"><i class="bx bx-git-branch"></i> 업무 흐름</div>
      <h2 class="section-title">처방 접수부터 완료까지<br>5단계로 끝납니다</h2>
      <p class="section-desc">복잡한 의료기기 급여 청구 업무를 표준화된 워크플로우로 처리하세요.</p>
      <div class="workflow-steps">
        <div class="workflow-step">
          <div class="workflow-num">1</div>
          <div class="workflow-step-title">처방전 업로드</div>
          <div class="workflow-step-desc">모바일·웹에서 이미지 업로드, AI가 자동 분석</div>
        </div>
        <div class="workflow-step">
          <div class="workflow-num">2</div>
          <div class="workflow-step-title">OCR 검수</div>
          <div class="workflow-step-desc">추출 데이터 확인 및 수정, 제품 매핑</div>
        </div>
        <div class="workflow-step">
          <div class="workflow-num">3</div>
          <div class="workflow-step-title">주문 생성</div>
          <div class="workflow-step-desc">Withworks SO 자동 생성, 배송 정보 연계</div>
        </div>
        <div class="workflow-step">
          <div class="workflow-num">4</div>
          <div class="workflow-step-title">NHIS 청구</div>
          <div class="workflow-step-desc">급여 청구 팩스 전송, 결과 자동 수신</div>
        </div>
        <div class="workflow-step">
          <div class="workflow-num">5</div>
          <div class="workflow-step-title">정산 완료</div>
          <div class="workflow-step-desc">세금계산서 발행, 현금영수증, 정산 마감</div>
        </div>
      </div>
    </div>
  </section>

  {{-- ORGS --}}
  <section class="section">
    <div class="section-inner">
      <div class="section-tag"><i class="bx bx-link-external"></i> 연동 기관</div>
      <h2 class="section-title">주요 의료 기관과<br>직접 연결됩니다</h2>
      <p class="section-desc">정부 기관의 공지사항을 실시간으로 수집해 정책 변경을 놓치지 않습니다.</p>
      <div class="org-grid">
        <div class="org-badge"><i class="bx bx-buildings"></i> 보건복지부 (MOHW)</div>
        <div class="org-badge"><i class="bx bx-buildings"></i> 건강보험심사평가원 (HIRA)</div>
        <div class="org-badge"><i class="bx bx-buildings"></i> 국민건강보험공단 (NHIS)</div>
        <div class="org-badge"><i class="bx bx-store"></i> Withworks 유통 플랫폼</div>
        <div class="org-badge"><i class="bx bx-credit-card"></i> 토스페이먼츠</div>
        <div class="org-badge"><i class="bx bx-receipt"></i> 팝빌(전자세금계산서)</div>
      </div>
    </div>
  </section>

  {{-- CTA --}}
  <section class="cta-section" id="login">
    <div class="cta-box">
      <div class="cta-icon"><i class="bx bx-rocket"></i></div>
      <h2 class="cta-title">지금 바로 시작하세요</h2>
      <p class="cta-desc">CE Admin은 Coloplast Korea 임직원 전용 플랫폼입니다.<br>계정이 없으신 경우 IT 관리자에게 문의하세요.</p>
      <a class="btn-primary" href="{{ route('login') }}" style="font-size:16px;padding:15px 40px;display:inline-flex;">
        <i class="bx bx-log-in-circle" style="font-size:20px;"></i> 로그인하기
      </a>
    </div>
  </section>

  {{-- FOOTER --}}
  <footer class="footer">
    <div class="footer-brand">
      <div class="footer-logo">CE</div>
      <span>© {{ date('Y') }} Coloplast Korea · CE Admin v2.0</span>
    </div>
    <div class="footer-links">
      <a href="#">개인정보 처리방침</a>
      <a href="#">이용약관</a>
      <a href="#">IT 지원 문의</a>
    </div>
    <span>Powered by Coloplast</span>
  </footer>

</body>
</html>
