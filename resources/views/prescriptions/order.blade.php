{{-- resources/views/prescriptions/order.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 확인 및 주문')
@section('page-title', '처방전 확인 및 주문')

@section('help-title', '처방전 확인 및 주문')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 구성</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>처방전 이미지 확인 → 제품 선택 → 주문 생성 → Withworks 연계까지 한 화면에서 처리합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">탭 안내</div>
  <div class="help-item">
    <div class="help-item-icon info"><i class="bx bx-image"></i></div>
    <div class="help-item-text"><strong>처방전 이미지 탭</strong>원본 이미지를 확대/축소하며 확인합니다. OCR 결과와 대조하세요.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-clipboard"></i></div>
    <div class="help-item-text"><strong>처방전 정보 탭</strong>OCR로 추출된 환자/병원/제품 정보를 수정할 수 있습니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-box"></i></div>
    <div class="help-item-text"><strong>주문 제품 탭</strong>판매유형을 선택하고 제품을 추가합니다. 제품 검색으로 Todoworks에서 직접 가져옵니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-cart"></i></div>
    <div class="help-item-text"><strong>주문 연계 탭</strong>배송 정보를 입력하고 주문을 생성합니다. Withworks에 자동 연계됩니다.</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">주문 생성 순서</div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;font-size:13px;">1</div>
    <div class="help-item-text">처방전 정보 탭에서 환자 정보 확인·수정 후 <b>검수 완료</b></div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;font-size:13px;">2</div>
    <div class="help-item-text">주문 제품 탭에서 <b>판매유형</b> 선택 후 제품 추가</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;font-size:13px;">3</div>
    <div class="help-item-text">주문 연계 탭에서 배송지 확인 후 <b>주문 생성 및 연계</b> 클릭</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;font-size:13px;">4</div>
    <div class="help-item-text">우측 카드에서 <b>Withworks 판매번호(SO)** 확인</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">Withworks 판매유형</div>
  <div class="help-badge-row">
    <span class="badge badge-primary">CE 판매 (1013)</span>
    <span class="badge badge-info">개인판매 (1016)</span>
    <span class="badge badge-warning">샘플판매 (1022)</span>
  </div>
</div>
@endsection
@section('breadcrumb')
  홈 / 주문관리 / 처방전 확인 &nbsp;·&nbsp;
  <span style="color:var(--primary);font-weight:500;">{{ $prescription->rx_number }}</span>
@endsection

@section('header-actions')
  <span class="badge badge-{{ $prescription->status_badge }}">{{ $prescription->status_label }}</span>
  <a href="{{ route('prescriptions.index') }}" class="btn btn-outline btn-sm">
    <i class="bx bx-arrow-back"></i> 목록
  </a>
  <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">
    <i class="bx bx-home-alt-2"></i>
  </a>
@endsection

@push('styles')
<style>
  /* 상담 창(팝업)의 아래 띠 — 창을 닫는 자리가 화면 안에 있어야 한다.
     띠가 본문 끝을 덮지 않도록 그만큼 아래 여백을 준다. */
  #counselPopupBar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 900;
    display: flex; align-items: center; gap: 8px; justify-content: flex-end;
    padding: 10px 16px; background: var(--bg-card); border-top: 1px solid var(--border);
    box-shadow: 0 -4px 16px rgba(0,0,0,.08);
  }
  #counselPopupNote { margin-right: auto; font-size: 12px; color: var(--text-muted); }
  body.is-counsel-popup { padding-bottom: 60px; }
  /* 창으로 띄운 상담은 사이드바·네비가 자리를 먹지 않게 둔다 — 본문만 보인다 */
  body.is-counsel-popup .layout-menu,
  body.is-counsel-popup .layout-navbar { display: none !important; }
  body.is-counsel-popup .layout-page { margin-left: 0 !important; }
  body.is-counsel-popup .info-bar-pinned { top: 0; left: 0; }

  /* 이전·다음으로 처방전을 넘길 때 흰 화면이 번쩍이지 않게 한다.
     브라우저가 지금 보이는 화면을 붙들고 있다가 다음 화면이 준비되면 겹쳐 바꾼다 —
     화면은 그대로 두고 내용만 바뀐 것처럼 보인다. 이동 자체는 예전과 같은 진짜
     이동이라, 화면 안의 상태는 새로 짜인다(반쯤 갈아 끼운 화면이 남지 않는다).
     지원하지 않는 브라우저는 이 규칙을 그냥 무시하고 예전처럼 넘어간다. */
  @view-transition { navigation: auto; }
  ::view-transition-old(root),
  ::view-transition-new(root) { animation-duration: .16s; }
  /* 뷰어는 넘겨도 자리와 크기가 같다 — 따로 이름을 줘 그 자리에서 바뀌게 한다 */
  #viewerCol { view-transition-name: rx-viewer; }
  @media (prefers-reduced-motion: reduce) {
    ::view-transition-old(root), ::view-transition-new(root) { animation: none; }
  }

  /* 좌우 배치 — 시안 137:350. 뷰어 360 고정, 사이 간격 12 */
  .order-layout { display: grid; grid-template-columns: 360px 1fr; gap: 12px; align-items: start; }
  .order-layout.viewer-right { grid-template-columns: 1fr 360px; }
  .order-layout.viewer-right > :first-child { order: 2; }
  @media (max-width: 1200px) { .order-layout.viewer-right { grid-template-columns: 1fr 280px; } }
  @media (max-width: 768px)  { .order-layout.viewer-right > :first-child { order: unset; } }
  /* ── 뷰어 카드 머리 (시안 137:883) — 높이 44, 아래 선 ── */
  /* 뷰어가 화면보다 길면 그 안에서 스크롤된다. 그때 처방번호 줄까지 밀려 올라가면
     지금 무엇을 보고 있는지 알 수 없다. 이 줄만 뷰어 맨 위에 붙여 둔다. */
  /* 시안 148:1548 은 44 안에 1px 선을 포함한다(strokeAlign INSIDE).
     아래 여백에서 그 1 을 빼야 머리 전체가 44 가 된다(빼지 않으면 45). */
  .vw-head { position:sticky; top:0; z-index:3;
             display:flex; align-items:center; justify-content:space-between; gap:4px;
             min-height:44px; padding:8px 16px 7px; background:var(--gray-0);
             border-bottom:1px solid var(--gray-200); }
  .vw-nav  { display:flex; align-items:center; gap:8px; min-width:0; }
  .vw-nav-btn { width:20px; height:20px; display:flex; align-items:center; justify-content:center;
                background:none; border:none; padding:0; font-size:13px; color:var(--gray-600); cursor:pointer; }
  .vw-nav-btn:hover { color:var(--primary); }
  .vw-rx   { font-size:13px; font-weight:700; line-height:1.6; color:var(--gray-1000);
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .vw-acts { display:flex; align-items:center; justify-content:flex-end; gap:6px; flex-shrink:0; }
  .vw-btn  { display:inline-flex; align-items:center; justify-content:center; gap:6px;
             height:28px; padding:0 12px; border-radius:8px;
             background:var(--gray-0); border:1px solid var(--gray-200);
             font-size:12px; font-weight:500; color:var(--gray-1000);
             cursor:pointer; text-decoration:none; white-space:nowrap; }
  .vw-btn:hover { background:var(--gray-50); }
  .vw-btn-icon { width:28px; padding:0; }

  /* ── 이미지 영역 (시안 137:839) — 높이 340 고정 ── */
  .img-viewer { position:relative; height:340px; background:var(--gray-0);
                display:flex; flex-direction:column; overflow:hidden; }

  /* ── 이미지 위에 얹는 도구 패널 (시안 137:901) ──
     예전에는 어두운 가로 툴바가 이미지 위아래를 차지했다. 시안은 이미지 왼쪽에
     반투명 세로 띠를 얹어 이미지가 보이는 넓이를 잃지 않는다. */
  .vw-tools { position:absolute; top:8px; left:8px; bottom:8px; z-index:2;
              display:flex; flex-direction:column; justify-content:space-between; align-items:center;
              gap:8px; padding:8px; border-radius:8px; background:rgba(255,255,255,.4); }
  .vw-tool-group { display:flex; flex-direction:column; align-items:center; gap:8px; }
  .vw-tool { width:32px; height:32px; display:flex; align-items:center; justify-content:center;
             border-radius:8px; background:var(--gray-0); border:none; padding:0;
             font-size:13px; color:var(--gray-800); cursor:pointer; transition:var(--transition); }
  .vw-tool:hover { color:var(--primary); }
  .vw-zoom { font-size:12px; font-weight:500; line-height:1.2; color:var(--gray-1000); text-align:center; }
  .img-viewer-canvas { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
  .img-placeholder { text-align: center; color: var(--gray-700); }
  .img-placeholder i { font-size: 56px; margin-bottom: 10px; display: block; opacity: .4; }
  .img-placeholder p { font-size: 13px; opacity: .6; }
  .ocr-edit-row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; align-items: center; margin-bottom: 10px; }
  .ocr-edit-label { font-size: 12px; font-weight: 500; color: var(--text-secondary); }
  .field-group { position: relative; }
  .field-group .field-status { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 13px; }
  .field-group input.has-warn { border-color: var(--alert-500); background: var(--alert-50); }
  .field-group input.has-ok   { border-color: var(--primary); }
  .benefit-box { padding: 12px 14px; background: var(--primary-50); border: 1px solid var(--primary-200); border-radius: var(--radius-lg); margin-top: 4px; }
  .benefit-title { font-size: 13px; font-weight: 700; color: var(--primary); }
  .benefit-detail { font-size: 12px; color: var(--text-secondary); margin-top: 4px; line-height: 1.8; }
  .product-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--bg); margin-bottom: 8px; cursor: pointer; transition: var(--transition); }
  .product-card.selected { border-color: var(--primary); background: var(--primary-light); }
  .product-card:hover { border-color: var(--primary); }
  .product-img { width: 44px; height: 44px; border-radius: 8px; background: var(--primary-100); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .product-name { font-size: 13px; font-weight: 700; }
  .product-code { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
  .product-price { font-size: 13px; font-weight: 700; color: var(--primary); margin-left: auto; }
  /* 제품 검색은 팝오버로 연다 — 칸 옆에 붙는 창이라 여기서 줄 모양은 .pac-wrap 뿐이다 */
  .pac-wrap { position:relative; flex:1; min-width:0; }
  .pac-status { padding:10px 14px; font-size:12px; color:var(--text-muted); text-align:center; }
  .qty-control { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
  .qty-btn { width: 28px; height: 28px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; transition: var(--transition); }
  .qty-btn:hover { border-color: var(--primary); color: var(--primary); }
  .qty-input { width: 100px; text-align: center; font-size: 13px; font-weight: 700; border: 1px solid var(--border); border-radius: var(--radius); padding: 3px 6px; background: var(--bg-card); }
  /* 비용 내역 (시안 148:3105 > 48101528) — 줄 h21 · 줄 사이 4 · 줄마다 선은 없다.
     라벨 13/500 #474D54, 값 13/500 #101317. 합계만 위에 1px 선을 두고 양쪽 다 16/700 주색. */
  .cost-row { display: flex; justify-content: space-between; align-items: center; gap: 8px;
              min-height: 21px; font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  .cost-row + .cost-row { margin-top: 4px; }
  .cost-row .cost-val { color: var(--gray-1000); }
  .cost-row.total { font-weight: 700; font-size: 16px; line-height: 26px; min-height: 26px; color: var(--primary);
                    border-top: 1px solid var(--border); padding-top: 12px; margin-top: 12px; }
  .cost-row.total .cost-val { color: var(--primary); font-size: 16px; }
  /* 제품 요약(JS renderOrderSummary)은 같은 클래스를 두 줄짜리 행에 쓴다 — 원래 모양을 지킨다 */
  #order-items-summary .cost-row { padding: 7px 0; margin-top: 0; min-height: 0; line-height: 1.6;
                                   font-weight: 400; color: var(--text-primary); border-bottom: 1px dashed var(--border); }
  #order-items-summary .cost-row:last-child { border-bottom: none; }
  .workflow-step { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border-light); }
  .workflow-step:last-child { border-bottom: none; }
  .ws-icon { width: 28px; height: 28px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
  /* --primary-light 는 --primary-50 과 같은 값이라, done 을 primary-50 으로 두면
     active 와 배경·글자색이 완전히 같아져 '완료'와 '진행 중'을 구분할 수 없다.
     done 은 한 단계 진한 primary-100/600 으로 눌러 둔다. */
  .ws-icon.done   { background: var(--primary-100); color: var(--primary-600); }
  .ws-icon.active { background: var(--primary-light); color: var(--primary); }
  .ws-icon.pending{ background: var(--bg); color: var(--text-muted); }
  .ws-label { font-size: 12px; font-weight: 500; } .ws-time { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
  .ws-arrow { margin-left: auto; color: var(--text-muted); font-size: 12px; }
  /* 아래 여백은 .page-body 의 16(=--content-pad)만 쓴다.
     시안 315:58 의 container 는 pad 0/16/16/16 이고 마지막 블록에서 끝까지 16 이다.
     여기서 40 을 더하면 56 이 되어 화면 아래가 그만큼 비어 보인다.
     이 값은 분할 보기 높이 계산(sizeSplit)에도 그대로 들어가므로,
     지우면 왼쪽·오른쪽 열이 그만큼 더 길어져 빈 칸도 함께 줄어든다. */
  .page-body-inner { padding-bottom: 0; }
  .info-bar-pinned { position:fixed !important; top:var(--nav-h); left:var(--sidebar-w); right:0; margin:0 !important; z-index:50; border-bottom:1px solid var(--border); }
  body.menu-collapsed .info-bar-pinned { left:64px; }
  /* MDI 워크스페이스 iframe(사이드바·네비 숨김)에서는 전체폭·최상단으로 고정(정보바·탭바 어긋남 방지) */
  html.is-framed .info-bar-pinned { top:0; left:0; }

  /* ── 환자 정보 바 (시안 137:290) ──
     액션 버튼이 저마다 다른 색으로 채워져 있어 무엇이 더 중요한 동작인지 알기 어려웠다.
     시안은 모두 같은 흰 버튼으로 두고 글자색으로만 성격을 나눈다. */
  .pib-name { font-size:16px; font-weight:700; line-height:1.6; color:var(--gray-1000); }
  /* Figma: 나이 pill h22 (테두리 2 + 패딩 4 + 16), 전화·병원·담당 tag h18 (테두리 없음) */
  .pib-chip { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px;
              background:var(--gray-100); border:1px solid var(--gray-200);
              font-size:11px; font-weight:500; line-height:16px; color:var(--gray-800); white-space:nowrap; }
  .pib-tag { display:inline-flex; align-items:center; justify-content:center; padding:0 6px; border-radius:6px;
             background:var(--primary-light); color:var(--primary);
             font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; }
  .pib-val { font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000); }
  .pib-dot { width:4px; height:4px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
  .pib-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px;
             height:32px; padding:0 12px; border-radius:8px;
             background:var(--gray-0); border:1px solid var(--gray-200);
             font-size:13px; font-weight:500; line-height:1.6; color:var(--gray-1000);
             cursor:pointer; white-space:nowrap; }
  .pib-btn:hover { background:var(--gray-50); }
  .pib-btn i { font-size:14px; }
  .pib-btn-primary { color:var(--primary); }
  .pib-btn-off { background:var(--gray-50); color:var(--gray-400); cursor:default; }
  /* 시안 148:1304 실측 — 바 1568×78 · r12 · pad 12/16 · gap 16 · 가로
       아바타 54×54 · r12 · bg gray-100
       오른쪽 묶음(세로 gap 2)
         윗줄 h32 : [이름 + 칩] ←→ [액션 버튼]   (가로 gap 8)
         아랫줄 h19: 전화 · 병원 · 담당           (가로 gap 12)
     마크업 순서는 이름 → 액션 → 연락처다. 액션 묶음에 팝오버가 잔뜩 붙어 있어
     마크업을 옮기지 않고 격자 위치만 지정한다. */
  .pib-avatar { width:54px; height:54px; flex-shrink:0; border-radius:12px;
                background:var(--gray-100); display:flex; align-items:center; justify-content:center;
                color:var(--gray-400); font-size:24px; }
  .pib-body     { flex:1; min-width:0;
                  display:grid; grid-template-columns:minmax(0,1fr) auto;
                  align-items:center; row-gap:2px; column-gap:8px; }
  .pib-body > .pib-ident    { grid-row:1; grid-column:1; }
  .pib-body > .pib-actions  { grid-row:1; grid-column:2; }
  .pib-body > .pib-row-meta { grid-row:2; grid-column:1 / -1; }
  .pib-ident    { display:flex; align-items:center; gap:8px; flex-wrap:wrap; min-width:0; }
  .pib-actions  { display:flex; align-items:center; justify-content:flex-end; gap:6px; flex-wrap:wrap; }
  .pib-row-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  /* 좁은 폭에서는 버튼 줄이 아래로 내려가야 이름·연락처가 눌리지 않는다 */
  @media (max-width: 1280px) {
    .pib-body { grid-template-columns:minmax(0,1fr); }
    .pib-body > .pib-actions  { grid-row:2; grid-column:1; justify-content:flex-start; }
    .pib-body > .pib-row-meta { grid-row:3; }
  }
  /* 오른쪽 버튼이 늘어나면 탭 이름이 눌려 세로로 접힌다. 탭은 줄지 않게 고정하고,
     폭이 모자라면 버튼 줄이 아래로 넘어가게 한다. */
  /* 좌우 두 영역 모두 흰 카드 (시안 137:441 · 137:517) */
  #viewerCol, #tabsCol { background: var(--gray-0); border-radius: 12px; }
  /* 뷰어는 스크롤해도 자리를 지킨다. 오른쪽 항목을 채우는 동안 처방전이 계속 보여야 한다.
     예전에는 스크롤 위치를 재서 fixed 로 바꾸는 코드가 있었는데, 값이 어긋나면 화면이
     튀었다. sticky 로 두면 브라우저가 알아서 맞춘다.
     뷰어가 화면보다 길면 그 안에서만 스크롤된다. */
  /* 붙는 위치는 고정 헤더 아래여야 한다. 12px 로 두면 헤더에 가려 처방번호 줄이 안 보인다. */
  #viewerCol { position: sticky; top: calc(var(--nav-h, 60px) + 12px); align-self: start;
               max-height: calc(100vh - var(--nav-h, 60px) - 24px);
               overflow: hidden auto; }
  /* 워크스페이스 안(iframe)에서는 높이만 조정한다.
     top 을 따로 주면 붙지 않아, 기본값(nav + 12)을 그대로 쓴다. */
  html.is-framed #viewerCol { max-height: calc(100vh - 24px); }

  /* ── 좌우 분할 (넓은 화면) ────────────────────────────────
     sticky 는 페이지가 스크롤될 때만 자리를 지킨다. 아코디언을 접어 페이지가
     화면보다 짧아지면 붙을 자리가 없어져 뷰어가 제 위치로 내려온다.
     좌우를 각각 스크롤시키면 페이지 자체가 스크롤되지 않으므로, 무엇을 여닫든
     뷰어는 움직일 수 없다. 높이는 JS 가 재서 넣는다(sizeSplit).
     좁은 화면(768 이하)에서는 한 열로 쌓이므로 걸지 않는다. */
  .order-layout.is-split { align-items: stretch; }
  .order-layout.is-split > #viewerCol { position: static; top: auto; max-height: none;
                                        height: 100%; overflow: hidden auto; }
  .order-layout.is-split > #tabsCol   { height: 100%; overflow: hidden auto; }
  /* 시안(148:1304)은 오른쪽 열 1196 안에서 아코디언 카드가 1164(=1196-16-16)다.
     구현은 세로 스크롤바가 안쪽 폭에서 15px 을 먹어 1149 가 된다.
     스크롤바 자리를 없애면(scrollbar-width:none) 그 15px 을 되찾을 수 있지만,
     이 열은 분할 보기에서 혼자 스크롤되는 유일한 곳이다 — 1920×1200 에서 안쪽
     내용 1717 중 985 만 보이고 732(43%)가 아래에 숨는다. 1920×1080 이면 852(50%).
     페이지 자체는 스크롤되지 않으므로 막대를 없애면 '얼마나 남았는지' 알릴 것이
     화면에 하나도 남지 않는다. 15px(1.3%)보다 그 표시가 크다고 보고 막대를 살려 둔다.
     시안 폭 1164 는 스크롤바가 보이는 한 퍼블리싱으로 맞출 수 없다 — 디자이너 확인 필요. */
  /* 열이 스크롤되면 탭 줄이 따라 올라가 버린다. 열 안에서 붙여 둔다. */
  .order-layout.is-split > #tabsCol > #tabBarOuter { position: sticky; top: 0; z-index: 2;
                                        background: var(--gray-0); border-radius: 12px 12px 0 0; }
  .tab-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; row-gap: 6px;
             min-height: 44px; padding: 0 16px; border-bottom: 1px solid var(--gray-200); margin-bottom: 0; }
  #tabsCol > .tab-pane { padding: 16px; }
  .tab-bar-tabs { display: flex; align-items: stretch; gap: 8px; flex-shrink: 0; }
  /* 오른쪽은 두 묶음이다 (시안 137:695) — 글자 링크(gap 12)와 테두리 버튼(gap 6), 사이는 16 */
  .tab-bar-acts { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; justify-content: flex-end; margin-left: auto; }
  .tb-links { display: flex; align-items: center; gap: 12px; }
  .tb-btns  { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  /* 전체 열기·테이블뷰는 테두리 없는 글자 링크 (시안 137:697·701) */
  .tb-link { display: inline-flex; align-items: center; gap: 4px; padding: 0; border: none; background: none;
             font-size: 12px; font-weight: 500; line-height: 1.6; color: var(--gray-700);
             cursor: pointer; white-space: nowrap; }
  .tb-link:hover { color: var(--primary); }
  .tb-link i { font-size: 12px; }
  /* 탭 — 시안 137:687: padding 0 8, 13/500, 고른 탭만 주색 + 1px 밑줄 */
  .tab-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 8px;
             font-size: 13px; font-weight: 500; line-height: 1.6; color: var(--gray-600); border: none; background: transparent;
             border-bottom: 1px solid transparent; cursor: pointer; transition: var(--transition); margin-bottom: -1px; }
  .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
  /* 탭바 오른쪽 액션 버튼 — 종류와 무관하게 같은 크기로 세운다.
     .btn/.btn-sm 을 쓰면 레이아웃 쪽 규칙과 우선순위 다툼이 나므로 이 클래스만 쓴다. */
  /* 테두리 버튼 — 시안 137:706: 높이 28, padding 0 12, radius 8, 12/500.
     폭은 글자에 맞춘다(시안이 hug). */
  .tb-act { display:inline-flex; align-items:center; justify-content:center; gap:6px;
            box-sizing:border-box; height:28px; padding:0 12px;
            border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-0);
            color:var(--gray-1000); font-size:12px; font-weight:500; line-height:1.6;
            white-space:nowrap; cursor:pointer; transition:var(--transition); }
  .tb-act:hover { background:var(--gray-50); }
  .tb-act i    { font-size:12px; }
  .tab-pane { display: none; } .tab-pane.active { display: block; }
  /* 검수 탭은 이제 아코디언만 담는다. 처방 제품은 자기 탭으로 돌아갔다. */
  /* ── 카드 / 테이블 뷰 토글 ── */
  .cv { display: block; } .tv { display: none; }
  .tab-view-table .cv { display: none; } .tab-view-table .tv { display: block; }
  .tab-view-table #btnAccToggleAll { display: none; }
  /* OCR·주문 탭은 cv 유지 (입력 기능 보존) — 아코디언 크롬만 제거 */
  .tab-view-table #tab-ocr   > .cv { display: block; }
  .tab-view-table #tab-order > .cv { display: block; }
  .tab-view-table #tab-ocr   > .tv { display: none; }
  .tab-view-table #tab-order > .tv { display: none; }
  /* OCR 탭 table mode: 아코디언 → 플랫 섹션 */
  .tab-view-table #tab-ocr .rx-acc-item { border:none; border-bottom:1px solid var(--border); border-radius:0; margin-bottom:0; }
  .tab-view-table #tab-ocr .rx-acc-item:last-child { border-bottom:none; }
  .tab-view-table #tab-ocr .rx-acc-header { background:var(--primary-light); pointer-events:none; padding:5px 12px; }
  .tab-view-table #tab-ocr .rx-acc-btns { display:none; }
  .tab-view-table #tab-ocr .rx-acc-header > span:first-child { color:var(--primary); font-size:11px; }
  .tab-view-table #tab-ocr .rx-acc-icon,
  .tab-view-table #tab-ocr .rx-acc-body { display:block !important; padding:10px 12px; }
  .tab-tbl { width:100%; border-collapse:collapse; font-size:12px; }
  .tab-tbl td, .tab-tbl th { padding:5px 9px; border:1px solid var(--border); vertical-align:middle; }
  .tab-tbl th { background:var(--bg); font-size:10px; font-weight:700; color:var(--text-secondary); white-space:nowrap; width:1%; min-width:76px; }
  /* 전역 thead th 가 대문자·자간 .5px·모서리 12px(첫·끝 칸)을 얹는다.
     이 표는 칸마다 1px 테두리가 있어 모서리가 붙으면 한 칸만 둥글게 잘린다. */
  .tab-tbl thead th { text-transform:none; letter-spacing:normal; border-radius:0; }
  .tab-tbl td { color:var(--text-primary); overflow:hidden; }
  .tab-tbl th { overflow:hidden; }
  .tab-tbl td.pac-cell { overflow:visible; position:relative; }
  .tab-tbl .tbl-sec td { background:var(--primary-light); color:var(--primary); font-size:11px; font-weight:700; }
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(4px); z-index: 200; display: none; align-items: center; justify-content: center; }
  .modal-overlay.show { display: flex; }
  .modal-box { background: var(--bg-card); border-radius: var(--radius-lg); width: 480px; max-width: 95vw; box-shadow: var(--shadow-lg); animation: slideUp .25s ease; }
  @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
  .modal-header { padding: 18px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
  .modal-title { font-size: 14px; font-weight: 700; flex: 1; }
  .modal-close { background: none; border: none; font-size: 16px; color: var(--text-muted); cursor: pointer; }
  .modal-body { padding: 20px; } .modal-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }
  @media (max-width: 1200px) { .order-layout { grid-template-columns: 280px 1fr; } }
  @media (max-width: 768px)  { .order-layout { grid-template-columns: 1fr; } .action-footer { left: 0; flex-wrap: wrap; bottom: 42px; } }

  /* 전역 .section-title 은 11px 대문자 캡션용이라 text-transform/letter-spacing 과
     오른쪽으로 뻗는 ::after 실선을 함께 준다. 여기서는 13/700 제목 + 아래 실선이라
     ::after 가 남으면 선이 두 줄로 겹친다. 상속되는 세 가지를 명시적으로 끈다. */
  .section-title { font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; text-transform: none; letter-spacing: normal; }
  .section-title::after { content: none; }
  .item-card { border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 8px 10px; margin-bottom: 6px; background: var(--bg-card); }
  .item-num { font-size: 12px; font-weight: 700; color: #fff; background: var(--primary); border-radius: 6px; padding: 0 9px; flex-shrink: 0; align-self: flex-end; height: 32px; display: flex; align-items: center; }
  /* Inline row: name + qty + buttons */
  .item-row { display: flex; align-items: flex-start; gap: 6px; }
.item-inline-field { display: flex; flex-direction: column; flex-shrink: 0; }
  .item-field-label { font-size: 10px; color: var(--text-muted); margin-bottom: 2px; }
  .item-summary { display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 4px 8px; background: var(--bg); border-radius: var(--radius); border: 1px solid var(--border); margin-top: 6px; }
  .item-nhis-sel { font-size:11px !important; height:32px !important; padding:0 6px !important; width:110px !important; min-width:0 !important; flex-shrink:0; }
  /* 카드뷰 item-row 안에서는 다른 입력 항목과 높이 통일 */
  .item-row .item-nhis-sel { height:32px !important; padding:0 4px !important; }
  .tab-view-table .item-nhis-sel { width:100% !important; }
  /* 쓰는 곳 없음 — 처방 제품 카드 아래 합계 띠는 시안 148:3105 대로 카드 머리 오른쪽
     맨글자(.pt-head-total)로 옮겼다. 규칙만 남겨 둔다. */
  .items-total-bar { display: flex; gap: 16px; font-size: 12px; padding: 8px 12px; background: var(--primary-50); border: 1px solid var(--primary-200); border-radius: var(--radius-lg); margin-top: 4px; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* 판매 유형 라디오 버튼 — 시안 148:3105 Frame 48101489.
     알약 143×32 · r8 · pad 0/12 · gap 8 · bd 1px #E8EAEC · bg #FFFFFF — 셋 다 같다.
     선택 여부는 알약 배경이 아니라 왼쪽 12×12 도넛의 바깥 원 색으로만 알린다
     (선택 #28798B / 미선택 #C2C5C8, 안쪽 6×6 흰 원). */
  .so-type-opt { display:inline-flex; align-items:center; cursor:pointer; }
  .so-type-opt input[type=radio] { display:none; }
  .so-type-opt span {
    display:inline-flex; align-items:center; gap:8px;
    /* 시안 폭은 143 이지만 「End User Direct」는 그 안에서 두 줄로 접힌다.
       폭을 최소값으로 두고 글자만큼 늘린다 — 접힌 이름은 읽는 데 걸린다. */
    min-width:143px; white-space:nowrap; height:32px; padding:0 12px; border-radius:8px;
    font-size:13px; font-weight:400;
    border:1px solid var(--border); background:#fff; color:var(--gray-1000);
    transition:var(--transition); user-select:none;
  }
  .so-type-opt span::before {
    content:''; width:12px; height:12px; border-radius:999px; flex-shrink:0;
    background:var(--gray-0); box-shadow:inset 0 0 0 3px var(--gray-300);
  }
  .so-type-opt input[type=radio]:checked + span::before { box-shadow:inset 0 0 0 3px var(--primary); }
  /* hover 는 시안에 없다. 선택 표시(도넛)와 헷갈리지 않도록 배경만 아주 옅게 준다. */
  .so-type-opt span:hover { background:var(--gray-50); }

  /* ── 처방 제품 탭 — 시안 148:3105 (2026-08-11 재작성판) ─────────────────────
     카드마다 전폭 머리띠를 두고 본문을 그 아래로 내린다.
     머리 h44 · pad 8/16 · 좌우 space-between · 아래 1px #E8EAEC (시안 Frame 48101479).
     접기 화살표(chevron 14)는 이 탭 카드를 여닫는 동작이 없어 넣지 않았다. */
  #tab-product .pt-card-head { display:flex; align-items:center; justify-content:space-between; gap:8px;
                               min-height:44px; padding:8px 16px; border-bottom:1px solid var(--gray-200); }
  #tab-product .pt-head-left  { display:flex; align-items:center; gap:12px; min-width:0; flex-wrap:wrap; }
  #tab-product .pt-head-right { display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:flex-end; }
  /* 제목 — 아이콘 20 + 13/700 (시안 Frame 48101480) */
  #tab-product .pt-card-title { display:flex; align-items:center; gap:8px;
                                font-size:13px; font-weight:700; line-height:21px; color:var(--gray-1000); white-space:nowrap; }
  #tab-product .pt-card-title > i { width:20px; height:20px; font-size:16px; color:var(--primary); flex-shrink:0;
                                    display:inline-flex; align-items:center; justify-content:center; }
  /* 머리 배지 — 낱개 알약 h22 · r999 · pad 2/8 · gap 6 · 11/500 (시안 Frame 48101522) */
  #tab-product .pt-head-badges { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  #tab-product .pt-hb { display:inline-flex; align-items:center; height:22px; padding:2px 8px; border-radius:999px;
                        background:var(--gray-100); font-size:11px; font-weight:500; line-height:18px;
                        color:var(--gray-800); white-space:nowrap; }
  #tab-product .pt-hb b { font-weight:500; color:inherit; }
  /* 머리 오른쪽 합계 — 12/500 주색 맨글자 (시안 "총 NHIS 급여: ₩ 0") */
  #tab-product .pt-head-total { display:inline-flex; align-items:center; gap:6px;
                                font-size:12px; font-weight:500; line-height:19px; color:var(--primary); white-space:nowrap; }
  #tab-product .pt-head-total b { font-weight:500; color:inherit; }
  /* 판매 유형 머리 오른쪽 '1013 · CE 판매' — 시안은 배지가 아니라 12/500 #656C74 맨글자다.
     onSoTypeChange() 가 같은 배지 마크업을 innerHTML 로 다시 써 넣으므로 JS 는 두고 CSS 로 누른다. */
  #tab-product #soTypeBadge .badge { background:none; padding:0; border-radius:0;
                                     font-size:12px !important; font-weight:500; line-height:19px; color:var(--gray-600); }
  #tab-product .pt-head-btns { display:flex; align-items:center; gap:6px; }
  /* 판매 유형 본문 — 라벨 100 + gap 8 + 라디오 묶음 446 (시안 Frame 48101481) */
  #tab-product .pt-field-row   { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  #tab-product .pt-field-label { flex:0 0 100px; width:100px;
                                 font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  #tab-product .pt-radio-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

  /* ── 처방 제품 행 (카드뷰) — 시안 Frame 48101492: 1132×118 ────────────────
     .item-card 는 테이블뷰에서 <tr> 로도 쓰인다. 카드뷰 컨테이너 안으로만 범위를 잡는다. */
  #items-container .item-card { display:flex; align-items:stretch; padding:0; margin-bottom:12px; }
  #items-container .item-card:last-child { margin-bottom:0; }
  #items-container .item-card-main { flex:1; min-width:0; display:flex; flex-direction:column; gap:12px; padding:12px; }
  #items-container .item-row { gap:12px; flex-wrap:wrap; }
  /* 열 폭 — 시안 281 / 141×5 (전부 grow), 열 사이 12.
     기준폭을 236/118 로 두고 grow 를 2:1 로 주면 시안 폭 1044 에서
     281.1 / 140.6 으로 떨어진다(236+158×2/7, 118+158×1/7).
     이 기준폭이면 오른쪽 패널이 890 까지 좁아져도 여섯 칸이 한 줄에 남고,
     그보다 좁아지면 칸이 짓눌리는 대신 아랫줄로 내려간다 — 시안 프레임도 가로 WRAP 이다.
     890 이라는 경계는 시안 근거가 없는 결정값이다. */
  #items-container .item-row > .item-inline-field { flex:1 1 118px; min-width:0; }
  #items-container .item-inline-field { gap:8px; }
  /* 라벨 — 시안 13/500 · lh21 · #474D54 (구현은 10px #999EA4 였다) */
  #items-container .item-field-label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); margin-bottom:0; }
  /* 제품명 열 281 = 입력 226 + gap 8 + [검색] 47 */
  #items-container .item-name-row { display:flex; align-items:center; gap:8px; }
  #items-container .item-search-btn { flex-shrink:0; height:32px; padding:0 12px; border-radius:8px;
                                      background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000);
                                      font-size:13px; font-weight:500; line-height:20px; }
  #items-container .item-search-btn:hover { background:var(--gray-50); }
  /* 값 칸 + 바깥 '₩' — 시안 입력 121 + gap 8 + '₩' 12 = 141 */
  #items-container .item-money-row { display:flex; align-items:center; gap:8px; }
  #items-container .item-money-row .form-control { flex:1; min-width:0; }
  #items-container .item-won { flex-shrink:0; font-size:13px; font-weight:400; line-height:21px; color:var(--gray-1000); }
  /* 급여 구분 select — 시안 141 한 칸 · 13/400 · pad 0/12 (전역 !important 를 되받는다) */
  #items-container .item-nhis-sel { width:100% !important; height:32px !important;
                                    font-size:13px !important; padding:0 30px 0 12px !important; }
  /* 총 금액 — 시안은 읽기전용 입력칸 모양(121×32 · bg #F9FAFC · bd 1px · 값 13/400 #333940) */
  #items-container .item-total-amt { height:32px; padding:0 12px; border:1px solid var(--gray-200); border-radius:8px;
                                     background:var(--gray-50); color:var(--gray-800);
                                     font-size:13px; font-weight:400; line-height:20px;
                                     display:flex; align-items:center; white-space:nowrap; }
  /* 보조줄 — 시안 Frame 48101527: 상자 없이 배지 + 값, 묶음 사이 gap 12 */
  #items-container .item-summary { background:none; border:none; border-radius:0; padding:0; margin-top:0;
                                   gap:12px; flex-wrap:wrap; }
  #items-container .item-sum-grp { display:inline-flex; align-items:center; gap:8px; }
  #items-container .item-sum-badge { display:inline-flex; align-items:center; height:19px; padding:0 4px; border-radius:4px;
                                     background:var(--gray-100); font-size:12px; font-weight:500; line-height:19px;
                                     color:var(--gray-600); white-space:nowrap; }
  #items-container .item-sum-badge.is-copay { background:var(--primary-50); color:var(--primary-400); }
  #items-container .item-summary b { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-1000); }
  #items-container .item-summary b.item-copay { color:var(--primary); }
  #items-container .item-sum-div { width:1px; height:8px; background:var(--gray-300); flex-shrink:0; }
  /* 오른쪽 삭제칸 64 — 시안 Frame 48101658: pad 0/16 · bg #F9FAFC · 왼쪽 1px 경계.
     행 카드에 overflow:hidden 을 걸지 않는다 — 안쪽 그림자와 모서리가 어긋난다. */
  #items-container .item-del-col { flex:0 0 64px; display:flex; align-items:center; justify-content:center;
                                   padding:0 16px; background:var(--gray-50);
                                   border-left:1px solid var(--gray-200); border-radius:0 12px 12px 0; }
  #items-container .item-del-btn { width:32px; height:32px; padding:0; border-radius:8px; flex-shrink:0;
                                   background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000);
                                   display:inline-flex; align-items:center; justify-content:center; }
  #items-container .item-del-btn:hover { background:var(--gray-50); }

  /* ── RX Inspection Accordion ── */
  /* 아코디언 — 시안 137:544: radius 12, 헤더 44 높이에 padding 8 16 */
  /* 아코디언 — 시안 137:544 · 137:545.
     열렸다고 테두리 색이나 머리 배경을 바꾸지 않는다. 시안은 닫힘·열림 모두
     흰 바탕에 #E8EAEC 테두리 하나로 두고, 펼침 여부는 화살표 방향으로만 알린다. */
  .rx-acc-item { border:1px solid var(--gray-200); border-radius:12px; margin-bottom:12px; overflow:hidden; background:var(--gray-0); }
  .rx-acc-header { display:flex; align-items:center; justify-content:space-between; min-height:44px; padding:8px 16px; cursor:pointer; background:var(--gray-0); user-select:none; transition:var(--transition); gap:8px; }
  .rx-acc-header:hover { background:var(--gray-50); }
  /* 왼쪽 — 아이콘 20 + 제목 13/700 (시안 137:546) */
  .rx-acc-header > span:first-child { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:var(--gray-1000); }
  .rx-acc-header > span:first-child > i,
  .rx-acc-header > span:first-child > svg { width:20px; height:20px; font-size:16px; flex-shrink:0;
                                            display:inline-flex; align-items:center; justify-content:center; }
  /* 오른쪽 — 힌트 12/500 + 화살표 14 (시안 137:555) */
  .rx-acc-meta { display:flex; align-items:center; gap:12px; }
  /* 헤더 오른쪽 버튼 (시안 148:2642) — 펼쳤을 때만 보인다.
     접힌 카드(137:545)에는 힌트와 화살표만 있다. */
  .rx-acc-btns { display:none; align-items:center; gap:6px; }
  .rx-acc-item.is-open > .rx-acc-header .rx-acc-btns { display:flex; }
  .rx-acc-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px;
                height:28px; padding:0 12px; border-radius:8px;
                background:var(--gray-0); border:1px solid var(--gray-200);
                font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000);
                cursor:pointer; white-space:nowrap; }
  .rx-acc-btn:hover { background:var(--gray-50); }
  /* 저장만 주색으로 채운다 (시안 148:2647) */
  .rx-acc-btn-fill { background:var(--primary); border-color:var(--primary); color:var(--gray-0); }
  .rx-acc-btn-fill:hover { background:var(--primary); filter:brightness(1.06); }
  .rx-acc-meta-hint { font-size:12px; font-weight:500; color:var(--gray-600); }
  .rx-acc-icon { width:14px; height:14px; font-size:14px; color:var(--gray-600); flex-shrink:0;
                 display:inline-flex; align-items:center; justify-content:center; transition:transform .2s ease; }
  .rx-acc-icon.open { transform:rotate(180deg); }
  /* 아코디언 본문 — 시안 148:2651: padding 12/16.
     칸 사이는 세로 8, 가로 24 (시안 148:2660 이 열 사이 24, 열 안 8). */
  .rx-acc-body { padding:12px 16px; background:var(--bg-card); border-top:1px solid var(--gray-200); }
  .rx-field-grid  { display:grid; grid-template-columns:1fr 1fr;         gap:8px 24px; }
  .rx-grid-3      { display:grid; grid-template-columns:1fr 1fr 1fr;     gap:8px 24px; }
  .rx-grid-4      { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px 24px; }

  /* 입력칸 — 시안 148:2665: 높이 32, 좌우 12, radius 8, 값 13/400 (#101317).
     전역 .form-control 이 이미 같은 규격이라 여기서 다시 잠그지 않는다. */
  .rx-acc-body .form-select  { background-position:right 12px center; padding-right:30px; }
  /* 메모 상자 — 시안 315:58 Frame 48101493: 253×32 한 줄 입력 · r8 · pad 0/12 · 값 13/400 lh21.
     옛 시안(148:1304)의 446×112 여러 줄 상자는 없어졌다. 다른 입력과 같은 32 로 맞춘다.
     태그는 textarea 그대로 둔다 — input 으로 바꾸면 이미 저장된 여러 줄 메모가
     다음 저장 때 한 줄로 뭉개진다(로직 담당 확인 필요). resize:vertical 은 인라인으로
     남아 있어, 여러 줄 값은 사용자가 늘려서 볼 수 있다. */
  .rx-acc-body textarea.form-control { height:32px; min-height:32px; padding:5px 12px; line-height:20px; }
  /* 구획 — 시안 148:2652: 소제목과 입력 사이 12, 구획끼리 24 */
  /* 구획 안은 3열이다 — 시안 315:58 의 Frame 48101577·48101578·48101512 가
     모두 layoutMode GRID · gridColumnCount 3 · gridColumnGap 24 · gridRowGap 24 다.
     1920 폭에서 본문 안쪽 1132 → 열 361 (361×3 + 24×2 = 1131),
     라벨 100 + gap 8 을 빼면 입력영역 253 으로 시안 실측과 맞는다.
     열 안의 줄 사이는 8, 줄 높이는 32 (.rx-col · .rx-field-row). */
  .rx-cols { display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px; align-items:start; }
  .rx-col  { display:flex; flex-direction:column; gap:8px; min-width:0; }
  /* 시안은 1920 한 폭만 그려져 있다. 아래 두 단계는 시안 근거가 없는 결정값이다.
     입력영역 폭 = (뷰포트 − 788 − 열간격) / 열수 − 108.
     3열: 1920→253 · 1728→189 · 1600→147 · 1512→117 (날짜값이 잘리기 시작한다)
     2열: 1600→286 · 1440→206 · 1280→126
     그래서 1600 까지만 3열로 두고, 그 아래는 2열로 접는다.
     1100 아래 1열은 원래 있던 규칙 그대로다. */
  @media (max-width:1599px) { .rx-cols { grid-template-columns:1fr 1fr; } }
  @media (max-width:1100px) { .rx-cols { grid-template-columns:1fr; } }
  /* 입력칸 옆에 붙는 부속 버튼 — 시안 148:2667·2676: 32 높이, 흰 배경, 13/500 */
  .rx-side-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; flex-shrink:0;
                 height:32px; padding:0 12px; border-radius:8px;
                 background:var(--gray-0); border:1px solid var(--gray-200);
                 font-size:13px; font-weight:500; line-height:1.6; color:var(--gray-1000);
                 cursor:pointer; white-space:nowrap; }
  .rx-side-btn:hover { background:var(--gray-50); }
  .rx-sec      { display:flex; flex-direction:column; gap:12px; }
  .rx-sec + .rx-sec { margin-top:24px; }
  /* 소제목 — 시안 148:2654: 14/700 #333940, 줄 높이 28, 아이콘·밑줄 없음 */
  .rx-sec-head  { display:flex; align-items:center; justify-content:space-between; gap:12px; min-height:28px; }
  /* 소제목 줄(1132×28)과 필드 묶음(1132×152) 은 한 프레임 안에서 gap 12 다
     (시안 148:1304 — Frame 48101488 · 48101489 둘 다 itemSpacing 12 · VERTICAL).
     마크업이 .rx-sec 로 감싸여 있지 않아 붙은 형제에게 여백을 준다. */
  .rx-sec-head + .rx-cols { margin-top:12px; }
  .rx-sec-title { font-size:14px; font-weight:700; line-height:1.6; color:var(--gray-800); }
  /* 메모 버튼 — 시안 148:2655: 높이 28, 좌우 8, 검정 테두리, 12/500 */
  .rx-sec-btn { display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 8px;
                border-radius:8px; background:var(--gray-0); border:1px solid var(--gray-1000);
                font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000);
                cursor:pointer; position:relative; flex-shrink:0; }
  .rx-sec-btn:hover { background:var(--gray-50); }
  .rx-sec-btn i { font-size:12px; }
  .rx-field-row { display:flex; align-items:center; gap:8px; min-width:0; }
  .rx-field-row.full { grid-column:1 / -1; }
  /* 3열이 되면 입력영역이 253 까지 좁아진다. flex 항목의 기본 최소 폭은 '내용 폭'이라
     선택지 글이 긴 select(사유·일일 도뇨 횟수)나 긴 placeholder 를 가진 입력이 줄지 않고
     버티면 카드를 가로로 넘긴다. 아코디언 안 입력은 전부 0 까지 줄게 둔다.
     폭을 따로 잠근 칸(우편번호 72 · 상담번호 144)은 인라인 style 이 이겨서 그대로다. */
  .rx-acc-body .rx-field-row .form-control,
  .rx-acc-body .rx-field-row .field-group { min-width:0; }
  /* 라벨 폭을 고정해 어느 줄에서든 입력이 같은 자리에서 시작하게 한다.
     min-width 였을 때는 '신환master등록일'(84px)처럼 긴 이름이 입력을 오른쪽으로
     밀어, 같은 아코디언 안에서도 시작 위치가 78·79·81·91 로 흩어졌다.
     88 은 가장 긴 이름(84)이 잘리지 않는 값이다. */
  /* 항목 이름 — 시안 148:2663: 100×32, 13/500, 줄높이 1.2, #474D54, 세로 가운데 */
  /* 항목 이름 — 시안 148:2663: 100×32, 13/500, 줄높이 1.2, #474D54, 세로 가운데.
     폭이 100 으로 고정이라 '구입일 (모든 서류 발행일)' 같은 긴 이름은 줄을 바꿔야 한다.
     nowrap 이면 입력칸 아래로 삐져나가 가려진다. 시안도 줄바꿈을 넣어 두 줄로 쓴다. */
  .rx-field-label { display:flex; align-items:center; width:100px; min-height:32px; flex-shrink:0;
                    font-size:13px; font-weight:500; line-height:1.2; color:var(--gray-700);
                    white-space:normal; word-break:keep-all; overflow-wrap:anywhere; }
  /* '배송 주소 동일' 체크 묶음 — 시안 315:58 Frame 48101499:
     묶음 96×21 · gap 6, 상자 16×16 · r6 · 1px #28798B, 글자 74 · 13/500 · #28798B.
     기본 체크박스는 모서리를 못 깎아 appearance 를 끄고 체크 표시를 직접 그린다. */
  #sameShipping { appearance:none; -webkit-appearance:none; box-sizing:border-box; position:relative;
                  width:16px; height:16px; margin:0; flex-shrink:0;
                  border:1px solid var(--primary); border-radius:6px; background:var(--gray-0); }
  /* 시안의 상자는 켠 상태에서도 흰 바탕이다 — Rectangle 5 fill #FFFFFF · stroke 1px #28798B,
     그 위에 check-01(6×4 벡터)이 stroke 1.5px #28798B 로 얹힌다.
     즉 '주색으로 찬 상자 + 흰 체크'가 아니라 '흰 상자 + 주색 체크'다. 뒤집지 않는다. */
  #sameShipping:checked::after { content:''; position:absolute; left:5px; top:1px; width:5px; aspect-ratio:5/9;
                  border:solid var(--primary); border-width:0 1.5px 1.5px 0; transform:rotate(45deg); }

  /* '재구매일' 줄 — 시안 315:58 Frame 48101499(재구매일):
     [발행일 100 FIXED · bg #F9FAFC][arrow-right-sm 14][재구매일 123 FILL] 사이 8
     (100+8+14+8+123 = 253). 옛 시안 148:1304 의 '208 · 208' 은 2열 시절 값이라 틀렸다.
     왼쪽 칸은 calcRenewDate() 가 이미 글자를 채우던 #disp-issued-date 라 상자 모양만 입혔다.
     오른쪽 칸은 시안 배경이 #FFFFFF 지만 구현은 자동 계산 readonly 라 --gray-50 을 그대로 둔다.
     기준 폭은 100 이지만 줄이지 못하게 잠그면(flex:0 0 100px) 3열 1600·2열 1280 에서
     이 줄만 열 밖으로 15~39px 삐져나간다(실측). flex:0 1 100px 로 두면 1920 에서는
     남는 자리가 오른쪽 입력으로 가서 100 그대로이고, 좁아지면 같이 줄어든다. */
  .rx-date-flow { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }
  .rx-date-flow > .rx-date-shown { display:flex; align-items:center; box-sizing:border-box;
                  flex:0 1 100px; min-width:0; height:32px; padding:0 12px;
                  border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-50);
                  font-size:13px; font-weight:400; line-height:20px; color:var(--gray-800);
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .rx-date-flow > .rx-date-arrow { flex:0 0 14px; width:14px; text-align:center;
                  font-size:12px; line-height:14px; color:var(--gray-1000); }
  .rx-ocr-badge { display:inline-flex; align-items:center; gap:3px; background:var(--primary-light); color:var(--primary); border:1px solid var(--primary-accent); border-radius:4px; font-size:10px; font-weight:700; padding:1px 5px; }
  .rx-ocr-badge i { font-size:9px; }
  @media(max-width:1100px){ .rx-grid-4 { grid-template-columns:1fr 1fr; } }
  @media(max-width:900px) { .rx-field-grid, .rx-grid-3 { grid-template-columns:1fr 1fr; } .rx-grid-4 { grid-template-columns:1fr 1fr; } }
  @media(max-width:600px) { .rx-field-grid, .rx-grid-3, .rx-grid-4 { grid-template-columns:1fr; } .rx-field-row.full { grid-column:1; } }

  /* 이전 상담 목록 hover */
  .pc-list-item { border-left: 3px solid transparent; }
  .pc-list-item:hover { background: var(--bg) !important; }

  /* 이전 상담 이력 아코디언 */
  .hist-acc-item { border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:6px; overflow:hidden; }
  .hist-acc-item.is-open { border-color:var(--primary-accent); }
  .hist-acc-header { display:flex; align-items:center; justify-content:space-between; padding:9px 12px; cursor:pointer; background:var(--bg); font-size:12px; font-weight:700; color:var(--text-primary); user-select:none; transition:background .15s; }
  .hist-acc-header:hover { background:var(--primary-light); }
  .hist-acc-item.is-open > .hist-acc-header { background:var(--primary-light); color:var(--primary); }
  .hist-ci { font-size:10px; color:var(--text-muted); transition:transform .2s; }
  .hist-ci.open { transform:rotate(180deg); }
  .hist-acc-body { display:none; padding:12px; border-top:1px solid var(--border-light); background:var(--bg-card); }
  .hist-acc-item.is-open > .hist-acc-body { display:block; }

  /* 필드 그리드 (이력 모달 내부) */
  .pc-field-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 10px; }
  .pc-field-full { grid-column:1 / -1; }
  .pc-field-row { display:flex; flex-direction:column; gap:2px; padding:7px 9px; background:var(--bg); border-radius:6px; border:1px solid var(--border-light); min-width:0; }
  .pc-field-label { font-size:10px; font-weight:500; color:var(--text-muted); }
  .pc-field-val { font-size:12px; font-weight:500; color:var(--text-primary); word-break:break-all; }

  /* ── 이미지 아래 카드 묶음 (시안 148:1585) ──
     360×634 · pad 16 · gap 12 · 세로 · 위쪽에만 1px 선.
     여백을 여기서 한 번 주면 카드 폭은 360-32=328 로 저절로 맞는다.
     예전에는 이 상자가 없어 카드가 뷰어 폭을 그대로 채우고 카드 사이도 붙어 있었다. */
  #viewerCards { display:flex; flex-direction:column; gap:12px;
                 padding:16px; border-top:1px solid var(--gray-200); }
  /* 생성 서류는 JS 가 통째로 갈아 끼우는 상자다(refreshGeneratedDocs).
     상자를 그대로 두면 서류가 0건일 때도 칸을 차지해 빈 간격 12 가 생긴다.
     display:contents 로 두면 안의 카드가 바로 이 묶음의 칸이 된다. */
  #viewerCards > #genDocsContainer { display:contents; }
  /* 카드 사이 간격은 gap 12 하나로만 잡는다 (검수 메모 카드의 .mt-3 상쇄) */
  #viewerCards > .mt-3 { margin-top:0; }

  /* ── 첨부 파일 썸네일 ── */
  /* ── 뷰어 아래 카드 공통 (시안 137:792) ──
     제목 줄은 44 높이에 아래 선 하나, 본문은 카드 안쪽에 따로 여백을 준다. */
  .vw-card { border:1px solid var(--gray-200); border-radius:12px; background:var(--gray-0); }
  /* 머리 44 는 아래 1px 선을 포함한 값이다 (시안 148:1587) — 아래 여백에서 1 을 뺀다 */
  .vw-card-head { display:flex; align-items:center; justify-content:space-between; gap:4px;
                  min-height:44px; padding:8px 16px 7px; border-bottom:1px solid var(--gray-200); }
  .vw-card-title { display:flex; align-items:center; gap:4px; font-size:13px; font-weight:700;
                   line-height:1.6; color:var(--gray-1000); }
  .vw-card-title b { color:var(--primary); font-weight:700; }   /* 개수는 주색 (시안 137:796) */
  .vw-card-acts { display:flex; align-items:center; gap:6px; }
  /* 카드 머리에 놓이는 작은 버튼 — 높이 28 (시안 137:798 · 137:802) */
  .vw-btn-sm { display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 12px;
               border-radius:8px; background:var(--gray-0); border:1px solid var(--gray-200);
               font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000);
               cursor:pointer; white-space:nowrap; }
  .vw-btn-sm:hover { background:var(--gray-50); }
  .vw-btn-sm i { font-size:12px; }
  .vw-btn-add { color:var(--primary); }

  /* ── 생성 서류 (시안 137:961) ──
     한 줄에 이름과 버튼을 나란히 두던 것을, 위는 파일 정보 아래는 버튼 세 개로 나눈다.
     좁은 뷰어 폭에서 파일명이 잘리지 않게 하려는 배치다. */
  .gd-list  { display:flex; flex-direction:column; gap:8px; padding:12px 16px; }
  .gd-item  { display:flex; flex-direction:column; gap:8px; padding:8px 12px;
              background:var(--gray-0); border:1px solid var(--gray-200); border-radius:8px; }
  .gd-top   { display:flex; align-items:center; gap:8px; }
  /* 시안 148:1585 의 서류 아이콘은 회색이다 — 본체 #E8EAEC(gray-200), 줄 #C2C5C8(gray-300).
     여기 아이콘은 한 가지 색만 쓰는 외곽선 글리프(fa-regular)라 줄 색으로 맞춘다.
     빨강은 시안에 없고, 서류 유형과 무관하게 모두 같은 회색이다. */
  .gd-icon  { width:28px; height:28px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
              font-size:22px; color:var(--gray-300); }
  .gd-info  { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; }
  .gd-name  { font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000);
              overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .gd-meta  { display:flex; align-items:center; gap:4px; font-size:11px; font-weight:500; line-height:1.6;
              color:var(--gray-500); min-width:0; }
  .gd-dot   { width:2px; height:2px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
  .gd-state { color:var(--primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .gd-acts  { display:flex; gap:6px; }
  .gd-btn   { flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;
              height:28px; border-radius:8px; background:var(--gray-0); border:1px solid var(--gray-200);
              font-size:12px; font-weight:500; line-height:1.6; color:var(--gray-1000);
              cursor:pointer; text-decoration:none; white-space:nowrap; }
  .gd-btn:hover { background:var(--gray-50); }
  .gd-btn i { font-size:12px; }

  /* ── 등록자 카드 (시안 137:652) ──
     머리줄 없이 역할별 한 줄씩 쌓는다. */
  .rg-card { padding:12px 16px; display:flex; flex-direction:column; gap:12px; }
  .rg-rows { display:flex; flex-direction:column; }
  .rg-row  { display:flex; align-items:center; gap:8px; padding:8px; background:var(--gray-0);
             border-bottom:1px solid var(--gray-200); }
  .rg-row:last-child { border-bottom:none; }
  /* 역할 배지 — 검수는 주색, 등록은 검정, 수정은 회색 (시안 137:660·667·674) */
  .rg-badge { display:inline-flex; align-items:center; justify-content:center; padding:0 4px; border-radius:6px;
              font-size:11px; font-weight:700; line-height:1.6; color:var(--gray-0); flex-shrink:0; }
  .rg-badge-review { background:var(--primary); }
  .rg-badge-create { background:var(--gray-1000); }
  .rg-badge-update { background:var(--gray-500); }
  .rg-name { flex:1; min-width:0; font-size:13px; font-weight:700; line-height:1.6; color:var(--gray-1000);
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .rg-when { display:flex; align-items:center; gap:4px; font-size:12px; font-weight:500; line-height:1.6;
             color:var(--gray-600); flex-shrink:0; white-space:nowrap; }
  .rg-chat { width:24px; height:24px; border-radius:999px; border:none; background:var(--gray-100);
             color:var(--gray-600); font-size:11px; cursor:pointer; flex-shrink:0;
             display:inline-flex; align-items:center; justify-content:center; }
  .rg-chat:hover { background:var(--primary-light); color:var(--primary); }

  /* ── 문서 타일 (시안 137:806) — 3열, 타일 높이 80 ── */
  .attach-strip { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:8px; padding:12px 16px; }
  /* 시안 148:1605 — 고르지 않은 타일에는 테두리가 없다. 고른 타일만 2px 주색이고,
     선이 안쪽에 그려져(strokeAlign INSIDE) 타일 바깥 크기는 93×80 로 같다.
     box-sizing:border-box 라 테두리가 굵어져도 자리가 밀리지 않는다. */
  .attach-thumb { position:relative; height:80px; border-radius:8px; overflow:hidden; cursor:pointer;
                  border:none; background:var(--gray-100); }
  .doc-thumb.active { border:2px solid var(--primary); }
  .attach-thumb-img { width:100%; height:100%; object-fit:cover; display:block; }
  .attach-thumb-pdf { width:100%; height:100%; display:flex; align-items:center; justify-content:center;
                      font-size:24px; background:var(--alert-50); color:var(--danger); }
  .attach-type-badge { position:absolute; left:0; right:0; bottom:0; padding:4px; text-align:center;
                       background:rgba(0,0,0,.4); color:var(--gray-0);
                       font-size:11px; font-weight:500; line-height:1.2; }
  .attach-del-btn { position:absolute; top:4px; right:4px; width:18px; height:18px; border-radius:999px;
                    background:var(--danger); border:none; color:#fff; font-size:9px; cursor:pointer;
                    display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .15s; z-index:2; }
  .attach-thumb:hover .attach-del-btn { opacity:1; }

  /* 보호자 영역의 진행 상태 — 받은 것과 아직 안 받은 것 */
  .gb-state { display:inline-flex; align-items:center; gap:4px; padding:1px 8px; border-radius:999px;
              font-size:11px; font-weight:700; line-height:18px; white-space:nowrap;
              background:var(--gray-100); color:var(--gray-600); border:1px solid var(--gray-200); }
  .gb-state.done { background:var(--primary-50); color:var(--primary); border-color:var(--primary-200); }

  /* 위임 서명 카드 — 서명과 신분증 미리보기 */
  .sc-cap { font-size:10px; font-weight:700; color:var(--gray-500); letter-spacing:.5px;
            text-transform:uppercase; margin-bottom:6px; }
  .sc-box { background:var(--gray-0); border:1px solid var(--gray-200); border-radius:8px;
            padding:8px; text-align:center; }
  .sc-box img { max-width:100%; max-height:150px; display:block; margin:0 auto; }

  /* 팝오버 안에서 메시지 유형을 손보는 작은 버튼 */
  .rx-tpl-mini { flex-shrink:0; border:1px solid var(--gray-200); background:var(--gray-0); border-radius:6px;
                 padding:1px 6px; font-size:10px; font-weight:700; line-height:18px;
                 color:var(--gray-600); cursor:pointer; }
  .rx-tpl-mini:hover { border-color:var(--primary); color:var(--primary); }

  /* ── 크게 보기 창 ────────────────────────────────────────
     덮개가 없다. 이 창 밖은 그대로 눌리고 입력된다.
     z-index 900 — 모달(1000 이상)보다 아래라 모달이 뜨면 그 밑으로 들어간다. */
  #bigViewer { position: fixed; z-index: 900; display: flex; flex-direction: column;
               min-width: 320px; min-height: 240px;
               background: var(--gray-0); border: 1px solid var(--gray-300); border-radius: 12px;
               box-shadow: 0 16px 48px rgba(0,0,0,.24); overflow: hidden; }
  #bigViewerHead { display: flex; align-items: center; gap: 8px; flex-shrink: 0;
                   height: 40px; padding: 0 8px 0 12px;
                   background: var(--gray-50); border-bottom: 1px solid var(--gray-200);
                   cursor: move; user-select: none; }
  #bigViewerTitle { flex: 1; min-width: 0; font-size: 13px; font-weight: 700; color: var(--gray-900);
                    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .bv-acts { display: flex; align-items: center; gap: 2px; flex-shrink: 0; cursor: default; }
  .bv-btn { display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; padding: 0; border: none; border-radius: 6px;
            background: none; color: var(--gray-700); font-size: 12px; cursor: pointer; text-decoration: none; }
  .bv-btn:hover { background: var(--gray-200); color: var(--primary); }
  .bv-close:hover { background: var(--alert-100); color: var(--alert-500); }
  .bv-zoom { min-width: 38px; text-align: center; font-size: 11px; font-weight: 700; color: var(--gray-600); }
  #bigViewerBody { flex: 1; min-height: 0; overflow: auto; background: var(--gray-100);
                   display: flex; align-items: center; justify-content: center; }
  #bvImg { display: block; max-width: none; transform-origin: center center;
           cursor: grab; user-select: none; }
  #bvImg:active { cursor: grabbing; }
  #bvFrame { width: 100%; height: 100%; border: none; background: #fff; }
  /* 오른쪽 아래 모서리를 잡아 크기를 바꾼다 */
  #bigViewerGrip { position: absolute; right: 0; bottom: 0; width: 16px; height: 16px;
                   cursor: nwse-resize;
                   background: linear-gradient(135deg, transparent 50%, var(--gray-400) 50%, var(--gray-400) 60%,
                               transparent 60%, transparent 72%, var(--gray-400) 72%, var(--gray-400) 82%, transparent 82%); }
  /* 창을 옮기거나 크기를 바꾸는 동안 iframe 이 마우스를 삼키지 않게 한다 */
  #bigViewer.is-moving #bvFrame { pointer-events: none; }
</style>
@endpush

@section('content')

@php
// 주문 연계 탭 calcItem() 공식과 동일: insurance_price × rate × qty
$calcNhis = $prescription->items->sum(function($i) {
    $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
    $qty  = (int)($i->quantity ?? 1);
    $rate = match ($i->nhis_status ?? 'eligible') {
        'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
    };
    return round($base * $rate * $qty);
});
$calcCopay = $prescription->items->sum(function($i) {
    $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
    $qty  = (int)($i->quantity ?? 1);
    $rate = match ($i->nhis_status ?? 'eligible') {
        'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
    };
    return round($base * $qty) - round($base * $rate * $qty);
});
$calcShipping = (int)($prescription->order?->shipping_fee ?? 0);
$calcDeposit  = $calcCopay + $calcShipping;
@endphp

  {{-- Patient Info Bar --}}
  <div id="patient-info-bar-ph" style="display:none;"></div>
  <div id="patient-info-bar" style="background:var(--gray-0);border-radius:12px;display:flex;align-items:center;gap:16px;margin:0 0 12px;padding:12px 16px;position:relative;z-index:50;">

    {{-- 시안 148:1304 — 왼쪽 아바타 54×54(r12 · gray-100), 오른쪽은 두 줄이다.
         장식이라 누를 수 없고 데이터도 쓰지 않는다. --}}
    <div class="pib-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></div>

    <div class="pib-body">

        {{-- 왼쪽 — 환자명과 배지 (시안 137:301) --}}
        <div class="pib-ident">
          <span class="pib-name" id="hdrPatientName">
            {{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-' }}
            @if(!$prescription->patient)
              <span style="font-size:10px;font-weight:400;color:var(--text-muted);margin-left:4px;">(OCR)</span>
            @endif
          </span>
          <span class="pib-chip" id="hdrPatientSub">
            @if($prescription->patient)
              {{ $prescription->patient->birth_date?->format('Y-m-d') }} · 만 {{ $prescription->patient->age }}세
            @else
              {{ $prescription->masked_resident_no_ocr ?? '-' }}
            @endif
          </span>
        </div>

        {{-- 오른쪽 — 액션 버튼 (시안 137:311) --}}
        <div class="pib-actions">

      {{-- 위임동의 SMS 발송 --}}
      <div style="position:relative;">
        <div id="consentBtnWrap">
          <button class="pib-btn pib-btn-primary" type="button" id="consentActionBtn" onclick="toggleConsentPopover(event)">
            <i class="fa-solid fa-file-signature" style="font-size:11px;"></i> 위임동의
          </button>
        </div>
        <div id="consentResultBadge" style="display:none;align-items:center;height:32px;gap:4px;padding:4px 9px;border-radius:var(--radius);font-size:11px;white-space:nowrap;"></div>
        {{-- 위임동의 팝오버 --}}
        <div id="consentPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:380px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:502;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i id="consentModalIcon" class="fa-solid fa-file-signature" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
            <span id="consentModalTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">위임동의 SMS 발송</span>
            <button onclick="closeConsentPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            <div id="consentResendNotice" style="display:none;background:var(--alert-50);border:1px solid var(--alert-100);border-radius:6px;padding:10px 12px;font-size:12px;color:var(--alert-500);line-height:1.6;">
              <i class="fa-solid fa-rotate-right"></i>
              <strong>이전 동의 링크가 만료되었습니다.</strong><br>
              새로운 동의 링크를 발송합니다. 이전 링크는 더 이상 사용할 수 없습니다.
            </div>
            @php
              $consentBase = rtrim(config('app.consent_public_url', config('app.url')), '/');
              $isLocalUrl  = str_contains($consentBase, 'localhost') || str_contains($consentBase, '127.0.0.1');
            @endphp
            @if($isLocalUrl)
            <div style="background:var(--alert-50);border:1px solid var(--alert-100);border-radius:6px;padding:10px 12px;font-size:12px;color:var(--alert-500);line-height:1.6;">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <strong>링크 클릭 불가 경고:</strong> CONSENT_PUBLIC_URL이 <code>{{ $consentBase }}</code>로 설정되어 있어 환자 휴대폰에서 링크가 클릭되지 않습니다.<br>
              <span style="opacity:.85;">.env에서 <code>CONSENT_PUBLIC_URL</code>을 실제 공인 도메인으로 변경하세요.</span>
            </div>
            @endif
            <p id="consentModalDesc" style="font-size:12px;color:var(--text-secondary);margin:0;line-height:1.6;">
              환자에게 <strong>건강보험 급여 위임동의</strong> 링크를 SMS로 발송합니다.<br>
              환자는 로그인 없이 서명 페이지에서 이름 확인 후 서명할 수 있습니다.<br>
              <span style="color:var(--warning);font-weight:700;">링크는 발송 후 30분간만 유효합니다.</span>
            </p>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-secondary);margin-bottom:4px;display:block;">수신 번호</label>
              <input type="text" class="form-control" id="consentMobile"
                     placeholder="010-XXXX-XXXX / 02-XXXX-XXXX"
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}"
                     style="font-size:13px;" oninput="updateConsentPreview()" />
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-secondary);margin-bottom:4px;display:block;">환자명</label>
              {{-- OCR 이름이 틀리거나 비어 있는 경우가 있어 여기서 고쳐 보낼 수 있게 한다.
                   비워 두면 서버가 처방전에 적힌 이름을 쓴다. --}}
              <input type="text" class="form-control" id="consentPatientName" maxlength="50"
                     placeholder="{{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '환자' }}"
                     value="{{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '' }}"
                     style="font-size:13px;" oninput="updateConsentPreview()" />
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-secondary);margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
              <div id="consentMsgPreview" style="background:var(--gray-50);border:1px solid var(--border);border-radius:6px;padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:1.8;color:var(--gray-800);font-family:monospace;"></div>
            </div>
            <div id="consentSendResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <button class="btn btn-outline btn-sm" onclick="closeConsentPopover()">취소</button>
              <button class="btn btn-primary btn-sm" id="btnConsentSend" onclick="sendConsentSms()">
                <i class="fa-solid fa-paper-plane"></i> 발송
              </button>
            </div>
          </div>
        </div>
        {{-- 위임동의 서명 확인 팝오버 --}}
        <div id="consentSignPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:540px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:503;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:linear-gradient(135deg,var(--primary-50),var(--primary-100));border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--primary-200);">
            <i class="fa-solid fa-signature" style="color:var(--primary);font-size:15px;flex-shrink:0;"></i>
            <span style="font-size:13px;font-weight:700;color:var(--primary-700);flex:1;">위임동의 서명 확인</span>
            <button onclick="closeConsentSignPopover()" style="background:none;border:none;cursor:pointer;color:var(--primary-700);font-size:16px;line-height:1;">&#215;</button>
          </div>
          <div style="padding:0;">
            <div id="csignLoading" style="padding:40px;text-align:center;color:var(--text-muted);font-size:13px;">
              <span style="display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:8px;"></span>서명 정보 불러오는 중...
            </div>
            <div id="csignContent" style="display:none;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid var(--border);">
                <div style="padding:14px 20px;border-right:1px solid var(--border);">
                  <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명자</div>
                  <div id="csignName" style="font-size:14px;font-weight:700;color:var(--text-primary);"></div>
                </div>
                <div style="padding:14px 20px;">
                  <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명한 전화번호</div>
                  <div id="csignMobile" style="font-size:14px;font-weight:700;color:var(--text-primary);font-family:monospace;"></div>
                </div>
              </div>
              <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명 일시</div>
                <div id="csignTime" style="font-size:13px;color:var(--text-primary);font-family:monospace;"></div>
              </div>
              <div id="csignImgWrap" style="padding:20px;background:var(--gray-50);text-align:center;border-bottom:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:12px;text-align:left;">서명</div>
                <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:12px;display:inline-block;min-width:200px;">
                  <img id="csignImg" src="" alt="서명 이미지" style="max-width:100%;max-height:200px;display:block;margin:0 auto;" />
                </div>
              </div>
              <div id="csignNoSig" style="display:none;padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">
                <i class="fa-solid fa-circle-info"></i> 저장된 서명 이미지가 없습니다.
              </div>
            </div>
            <div id="csignError" style="display:none;padding:24px;text-align:center;color:var(--danger);font-size:13px;">
              <i class="fa-solid fa-triangle-exclamation"></i> 서명 정보를 불러오지 못했습니다.
            </div>
          </div>
          <div style="padding:12px 14px;display:flex;justify-content:flex-end;flex-wrap:nowrap;gap:6px;border-top:1px solid var(--border);">
            {{-- 서명 이미지만 따로 받아 가는 경우가 있다(서류 첨부·대조) --}}
            <a id="csignPngBtn" href="{{ route('prescriptions.consentSignature', $prescription) }}"
               style="display:none;padding:5px 10px;background:var(--primary);color:#fff;font-weight:700;font-size:11px;line-height:1;white-space:nowrap;border-radius:var(--radius);text-decoration:none;align-items:center;gap:4px;">
              <i class="fa-solid fa-download"></i> 서명 PNG
            </a>
            <a id="csignPdfBtn" href="#" target="_blank"
               style="display:none;padding:5px 10px;background:var(--danger);color:#fff;font-weight:700;font-size:11px;line-height:1;white-space:nowrap;border-radius:var(--radius);text-decoration:none;align-items:center;gap:4px;">
              <i class="fa-solid fa-file-pdf"></i> 위임동의서 PDF
            </a>
            <a id="csignDelegationBtn" href="{{ route('prescriptions.delegationPdfOriginal', $prescription) }}" target="_blank"
               style="display:none;padding:5px 10px;background:var(--primary);color:#fff;font-weight:700;font-size:11px;line-height:1;white-space:nowrap;border-radius:var(--radius);text-decoration:none;align-items:center;gap:4px;">
              <i class="fa-solid fa-file-signature"></i> 요양비 위임장 PDF
            </a>
            {{-- 서명이 끝났으면 공단에 위임 등록을 해야 한다. 입력 지원 창을 연다. --}}
            <button id="csignNhisBtn" type="button"
               onclick="window.open('{{ route('nhis.assist.delegation', $prescription) }}', 'nhis_delegation_{{ $prescription->rx_number }}', 'width=980,height=1000,scrollbars=yes,resizable=yes')"
               style="display:none;padding:5px 10px;background:var(--success,#28c76f);color:#fff;font-weight:700;font-size:11px;line-height:1;white-space:nowrap;border-radius:var(--radius);border:none;cursor:pointer;align-items:center;gap:4px;"
               title="공단 요양비청구위임내역등록(2225) 화면에 붙여넣을 값을 순서대로 보여 줍니다.">
              <i class="fa-solid fa-clipboard-list"></i> 공단 위임 등록
            </button>
            <button id="csignRegenBtn" type="button" onclick="regenerateDelegation(this)"
               data-url="{{ route('prescriptions.delegationRegenerate', $prescription) }}"
               style="display:none;padding:5px 10px;background:var(--primary);color:#fff;font-weight:700;font-size:11px;line-height:1;white-space:nowrap;border-radius:var(--radius);border:none;cursor:pointer;align-items:center;gap:4px;"
               title="현재 위임장 설정(기관·계좌·서명위치)으로 첨부문서를 다시 생성합니다.">
              <i class="fa-solid fa-rotate"></i> 설정 반영 재생성
            </button>
            <button class="btn btn-outline btn-sm" style="white-space:nowrap;padding:5px 10px;font-size:11px;" onclick="closeConsentSignPopover()">닫기</button>
          </div>
        </div>
      </div>

      {{-- 가상계좌 발급 --}}
      {{-- 가상계좌 발급은 화면에 두지 않는다. 결제전송에서 카드·가상계좌·무통장입금을
           함께 고르므로 같은 일을 하는 단추가 둘이 된다.
           지우지는 않는다 — 발급된 계좌를 보여 주는 자리와 웹훅 처리가 여기에 매여 있고,
           다시 꺼내야 할 때 display 만 되돌리면 된다. --}}
      <div id="vaButtonWrap" style="display:none;">
      @if($prescription->order)
        @php
          $tp = $prescription->order->tossPayment;
        @endphp
        @if($tp && $tp->status === 'DONE')
          {{-- 입금완료 --}}
          <div style="height:32px;padding:5px 11px;background:var(--primary-50);border:1px solid var(--primary);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:4px;white-space:nowrap;">
            <i class="fa-solid fa-circle-check" style="font-size:11px;"></i> 입금완료
          </div>
        @elseif($tp && $tp->status === 'DISABLED')
          {{-- VA 비활성화 – SMS만 발송 완료 --}}
          <div style="height:32px;padding:5px 11px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--warning);display:flex;align-items:center;gap:4px;white-space:nowrap;">
            <i class="fa-solid fa-comment-sms" style="font-size:11px;"></i> SMS 발송 완료
          </div>
        @elseif($tp)
          {{-- 발급됨 – 입금대기 or 만료 --}}
          <button type="button"
                  data-url="{{ route('settlement.check-status', $prescription->order) }}"
                  onclick="checkVaStatus(this)"
                  style="height:32px;padding:5px 11px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--warning);display:flex;align-items:center;gap:4px;white-space:nowrap;cursor:pointer;">
            <i class="fa-solid fa-building-columns" style="font-size:11px;"></i>
            {{ $tp->bank_name }} {{ $tp->account_number }}
          </button>
        @else
          {{-- 미발급 --}}
          <div id="vaNotIssuedWrap" style="position:relative;">
            <button class="pib-btn pib-btn-primary" type="button" id="btnVaTrigger"
                    data-url="{{ route('settlement.issue-va', $prescription->order) }}"
                    data-sms-url="{{ route('prescriptions.smsSend', $prescription) }}"
                    onclick="toggleVaPopover(event)"
                    style="white-space:nowrap;cursor:pointer;">
              <i class="fa-solid fa-building-columns" style="font-size:11px;"></i> 가상계좌 발급
            </button>
            {{-- 가상계좌 발급 팝오버 --}}
            <div id="vaPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:360px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:501;">
              <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
                <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
              </div>
              <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-building-columns" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
                <span id="vaPopoverTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">가상계좌 발급</span>
                <button onclick="closeVaPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
              </div>
              {{-- 확인 view --}}
              <div id="vaPopoverConfirm" style="padding:14px;">
                <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;">
                  <div style="font-size:11px;color:var(--text-muted);font-weight:500;margin-bottom:8px;">발급 정보 확인</div>
                  <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">환자명</span>
                    <b>{{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-' }}</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">주문번호</span>
                    <b style="font-family:monospace;color:var(--primary);">{{ $prescription->order?->order_number ?? '-' }}</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">환자 본인부담금</span>
                    <b style="color:var(--primary);">&#8361;{{ number_format($calcCopay) }}</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:12px;">
                    <span style="color:var(--text-muted);">배송비</span>
                    <b>&#8361;{{ number_format($prescription->order?->shipping_fee ?? 3000) }}</b>
                  </div>
                </div>
                <div style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:8px 10px;font-size:11px;color:var(--primary-600);margin-bottom:12px;">
                  <i class="fa-solid fa-circle-info"></i>
                  토스페이먼츠 가상계좌가 발급되며, 환자에게 입금 안내가 이루어집니다.
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                  <button class="btn btn-outline btn-sm" onclick="closeVaPopover()">취소</button>
                  <button class="btn btn-primary btn-sm" id="vaConfirmIssueBtn" onclick="doIssueVirtualAccount()">
                    <i class="fa-solid fa-building-columns"></i> 발급 확인
                  </button>
                </div>
              </div>
              {{-- 결과 view --}}
              <div id="vaPopoverResult" style="display:none;padding:14px;">
                <div id="vaDisabledNote" style="display:none;background:var(--alert-50);border:1px solid var(--alert-100);border-radius:var(--radius);padding:8px 10px;font-size:11px;color:var(--alert-500);margin-bottom:10px;">
                  <i class="fa-solid fa-circle-info"></i>
                  가상계좌 발급이 비활성화 상태입니다 (<code>TOSS_VA_ENABLED=false</code>). SMS는 정상 발송되었습니다.
                </div>
                <div style="background:var(--primary-50);border:1px solid var(--primary-accent);border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;">
                  <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;font-weight:500;">입금 계좌 안내</div>
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">은행</span>
                    <b id="vaResultBank">-</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">계좌번호</span>
                    <b id="vaResultAccount" style="font-family:monospace;letter-spacing:.5px;">-</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:5px;">
                    <span style="color:var(--text-muted);">입금 금액</span>
                    <b id="vaResultAmount" style="color:var(--primary);">-</b>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;">
                    <span style="color:var(--text-muted);">입금 기한</span>
                    <span id="vaResultDue" style="color:var(--warning);">-</span>
                  </div>
                </div>
                <div style="font-size:11px;color:var(--text-muted);text-align:center;margin-bottom:12px;">
                  <i class="fa-solid fa-circle-info"></i> 입금 확인은 자동으로 처리되며, 정산/회계 메뉴에서 확인할 수 있습니다.
                </div>
                <div style="display:flex;justify-content:flex-end;">
                  <button class="btn btn-primary btn-sm" onclick="closeVaAndShowResultBadge()">
                    <i class="fa-solid fa-circle-check"></i> 확인
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div id="vaResultBadge" style="display:none;align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--gray-100);border:1px solid var(--gray-300);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
            <i class="fa-solid fa-building-columns" style="color:var(--gray-700);font-size:10px;"></i>
            <span id="vaResultBadgeText" style="font-weight:700;color:var(--gray-700);">-</span>
          </div>
        @endif
      @endif
      </div>{{-- #vaButtonWrap --}}

      {{-- 카카오 알림톡 --}}
      @php $kakaoSent = (bool)$prescription->kakao_sent_at; @endphp
      <div id="kakaoTriggerWrap" style="position:relative;">
        <button class="pib-btn pib-btn-primary" id="btnKakaoTrigger" onclick="toggleKakaoPopover(event)">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:13px;height:13px;fill:{{ $kakaoSent ? 'var(--primary)' : '#191919' }};flex-shrink:0;"><path d="M12 3C6.477 3 2 6.477 2 10.8c0 2.7 1.548 5.082 3.9 6.498l-.97 3.6a.3.3 0 0 0 .462.328l4.326-2.88A11.4 11.4 0 0 0 12 18.6c5.523 0 10-3.477 10-7.8S17.523 3 12 3z"/></svg>
          알림톡
        </button>
        {{-- 알림톡 팝오버 --}}
        <div id="kakaoPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:320px;background:var(--bg-card);border:1px solid #FEE500;border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:501;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--bg-card);border:1px solid #FEE500;transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:#FEE500;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:16px;height:16px;fill:#191919;flex-shrink:0;"><path d="M12 3C6.477 3 2 6.477 2 10.8c0 2.7 1.548 5.082 3.9 6.498l-.97 3.6a.3.3 0 0 0 .462.328l4.326-2.88A11.4 11.4 0 0 0 12 18.6c5.523 0 10-3.477 10-7.8S17.523 3 12 3z"/></svg>
            <span style="font-size:13px;font-weight:700;color:#191919;flex:1;">카카오 알림톡 발송</span>
            @if(config('kakao.channel_url'))
            <a href="{{ config('kakao.channel_url') }}" target="_blank" style="color:#191919;font-size:11px;text-decoration:none;opacity:.7;margin-right:4px;white-space:nowrap;"><i class="fa-solid fa-arrow-up-right-from-square"></i> 채널</a>
            @endif
            <button onclick="closeKakaoPopover()" style="background:none;border:none;cursor:pointer;color:#191919;font-size:15px;line-height:1;">×</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            <div>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                <div style="font-size:11px;font-weight:500;color:var(--text-muted);flex:1;">메시지 유형 선택</div>
                @perm('messages', 'create')
                <button type="button" class="rx-tpl-mini" onclick="rxTplNew('alimtalk')">+ 추가</button>
                @endperm
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;" id="kakaoTemplateList">
                @foreach($kakaoTemplates as $code => $tpl)
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;transition:var(--transition);"
                       class="kakao-tpl-item" data-code="{{ $code }}"
                       onmouseover="this.style.borderColor='#FEE500';this.style.background='#FFFDE7';"
                       onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='var(--border)';this.style.background='';}">
                  <input type="radio" name="kakao_tpl" value="{{ $code }}" style="accent-color:#FEE500;" onchange="onTplChange(this)">
                  <div style="min-width:0;flex:1;">
                    <div style="font-weight:700;">{{ $tpl['label'] }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ $tpl['desc'] }}</div>
                  </div>
                  @perm('messages', 'update')
                  <button type="button" class="rx-tpl-mini" onclick="event.preventDefault();event.stopPropagation();rxTplEdit('alimtalk','{{ $code }}')">수정</button>
                  @endperm
                </label>
                @endforeach
              </div>
            </div>
            <div id="kakaoPreviewWrap" style="display:none;">
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">메시지 미리보기</div>
              <div id="kakaoPreviewBox" style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius);padding:10px 12px;font-size:11px;line-height:1.8;white-space:pre-wrap;color:var(--gray-800);max-height:120px;overflow-y:auto;"></div>
            </div>
            @if($prescription->order)
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;font-size:11px;display:flex;flex-direction:column;gap:4px;">
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:500;">본인 부담금</span>
                <span id="kakaoCopayAmt" style="font-weight:700;">{{ number_format($calcCopay) }}원</span>
              </div>
              @if($prescription->order->shipping_fee)
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:500;">배송비</span>
                <span style="font-weight:700;">{{ number_format($prescription->order->shipping_fee) }}원</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:4px;border-top:1px solid var(--border);">
                <span style="color:var(--text-muted);font-weight:500;">합계</span>
                <span id="kakaoDepositAmt" style="font-weight:700;color:var(--primary);">{{ number_format($calcDeposit) }}원</span>
              </div>
              @endif
            </div>
            @endif
            <div>
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">수신 번호</div>
              <input type="text" id="kakaoMobile" class="form-control" style="font-size:12px;height:32px;"
                     placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}">
            </div>
            @if(config('kakao.test_mode'))
            <div style="background:var(--alert-50);border:1px solid var(--alert-100);border-radius:var(--radius);padding:6px 10px;font-size:10px;color:var(--alert-500);">
              <i class="fa-solid fa-flask"></i> 테스트 모드 — 실제 미전송
            </div>
            @endif
            <button id="btnKakaoSend" onclick="sendKakaoMsg()"
                    style="width:100%;padding:8px;background:#FEE500;color:#191919;border:none;border-radius:var(--radius);font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
              <i class="fa-solid fa-paper-plane"></i> 알림톡 발송
            </button>
          </div>
        </div>
      </div>
      {{-- SMS 알림 --}}
      @php $smsSent = (bool)$prescription->sms_sent_at; @endphp
      <div id="smsTriggerWrap" style="position:relative;">
        <button class="pib-btn pib-btn-primary" id="btnSmsTrigger" onclick="toggleSmsPopover(event)">
          <i class="fa-solid fa-comment-sms" style="font-size:12px;"></i> SMS
        </button>
        {{-- SMS 팝오버 --}}
        <div id="smsPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:320px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:500;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--bg-card);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-comment-sms" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
            <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">SMS 알림 발송</span>
            <button onclick="closeSmsPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:15px;line-height:1;">×</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            <div>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                <div style="font-size:11px;font-weight:500;color:var(--text-muted);flex:1;">메시지 유형 선택</div>
                @perm('messages', 'create')
                <button type="button" class="rx-tpl-mini" onclick="rxTplNew('sms')">+ 추가</button>
                @endperm
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;" id="smsTemplateList">
                @foreach($smsTemplates as $code => $tpl)
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;transition:var(--transition);"
                       class="sms-tpl-item" data-code="{{ $code }}" data-text="{{ addslashes($tpl['text']) }}"
                       onmouseover="this.style.borderColor='var(--primary)';this.style.background='rgba(40,121,139,.06)';"
                       onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='var(--border)';this.style.background='';}">
                  <input type="radio" name="sms_tpl" value="{{ $code }}" style="accent-color:var(--primary);" onchange="onSmsTplChange(this)">
                  <div style="min-width:0;flex:1;">
                    <div style="font-weight:700;">{{ $tpl['label'] }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ $tpl['desc'] }}</div>
                  </div>
                  @perm('messages', 'update')
                  <button type="button" class="rx-tpl-mini" onclick="event.preventDefault();event.stopPropagation();rxTplEdit('sms','{{ $code }}')">수정</button>
                  @endperm
                </label>
                @endforeach
              </div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">메시지 내용 <span id="smsMsgLen" style="color:var(--text-muted);">(0자)</span></div>
              <textarea id="smsMsgBody" rows="5"
                        style="width:100%;font-size:11px;line-height:1.7;border:1px solid var(--border);border-radius:var(--radius);padding:8px 10px;resize:vertical;background:var(--bg-card);color:var(--text);"
                        placeholder="메시지 유형을 선택하면 자동으로 채워집니다."
                        oninput="updateSmsLen()"></textarea>
              <div id="smsMsgType" style="font-size:10px;color:var(--text-muted);margin-top:2px;text-align:right;"></div>
            </div>
            @if($prescription->order)
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;font-size:11px;display:flex;flex-direction:column;gap:4px;">
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:500;">본인 부담금</span>
                <span id="smsCopayAmt" style="font-weight:700;">{{ number_format($calcCopay) }}원</span>
              </div>
              @if($prescription->order->shipping_fee)
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:500;">배송비</span>
                <span style="font-weight:700;">{{ number_format($prescription->order->shipping_fee) }}원</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:4px;border-top:1px solid var(--border);">
                <span style="color:var(--text-muted);font-weight:500;">합계</span>
                <span id="smsDepositAmt" style="font-weight:700;color:var(--primary);">{{ number_format($calcDeposit) }}원</span>
              </div>
              @endif
            </div>
            @endif
            <div>
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">수신 번호</div>
              <input type="text" id="smsMobile" class="form-control" style="font-size:12px;height:32px;"
                     placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}">
            </div>
            <button id="btnSmsSend" onclick="sendSmsMsg()"
                    style="width:100%;padding:8px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
              <i class="fa-solid fa-paper-plane"></i> SMS 발송
            </button>
          </div>
        </div>
      </div>

      {{-- 결제 전송 — 환자에게 「여기서 내십시오」를 보낸다.
           카드·가상계좌는 우리 결제 페이지 주소를 보내고, 무통장입금은 우리 계좌를 적어 보낸다.
           보낸 것과 낸 것은 아래 이력에 쌓인다 — 다시 보낼지 전화할지를 그걸 보고 정한다. --}}
      <div id="payTriggerWrap" style="position:relative;">
        {{-- 주문이 없어도 눌리게 둔다. 잠가 두면 눌러도 아무 일이 없어 고장으로 읽힌다 —
             창을 열어 「주문을 먼저 만들라」고 그 자리에서 알려 주는 편이 낫다. --}}
        <button class="pib-btn" id="btnPayTrigger" onclick="togglePayPopover(event)">
          <i class="fa-solid fa-won-sign" style="font-size:12px;"></i> 결제전송
        </button>

        <div id="payPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:420px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:500;">
          <div id="payPopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-won-sign" style="color:#fff;font-size:14px;"></i>
            <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">결제 전송</span>
            <button onclick="closePayPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:15px;line-height:1;">×</button>
          </div>

          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            @unless($prescription->order)
              <div style="padding:10px 12px;background:var(--alert-50);border:1px solid var(--alert-200);border-radius:var(--radius);font-size:12px;color:#B54708;">
                아직 주문이 없습니다. <b>주문 연계</b> 탭에서 주문을 만든 뒤에 보낼 수 있습니다.
              </div>
            @endunless
            <div style="display:flex;justify-content:space-between;font-size:12px;">
              <span style="color:var(--text-muted);">결제 금액</span>
              <b id="payAmount" style="color:var(--primary);">{{ number_format($prescription->order?->total_amount ?? 0) }}원</b>
            </div>

            <div>
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:6px;">결제 방법</div>
              <div style="display:flex;flex-direction:column;gap:4px;" id="payMethods">
                @foreach(\App\Models\PaymentLink::METHODS as $code => $label)
                  @php
                    $hint = ['card' => '결제 페이지에서 카드로 냅니다',
                             'virtual' => '결제 페이지에서 가상계좌를 발급받습니다',
                             'bank' => '콜로플라스트 입금계좌를 문자로 안내합니다'][$code];
                  @endphp
                  <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;">
                    <input type="radio" name="pay_method" value="{{ $code }}" style="accent-color:var(--primary);"
                           @checked($code === 'card')>
                    <div style="min-width:0;flex:1;">
                      <div style="font-weight:700;">{{ $label }}</div>
                      <div style="font-size:10px;color:var(--text-muted);">{{ $hint }}</div>
                    </div>
                  </label>
                @endforeach
              </div>
            </div>

            <div>
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">받는 번호</div>
              <input type="text" id="payMobile" class="form-control" style="font-size:12px;height:32px;"
                     placeholder="010-XXXX-XXXX" data-phone
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}">
              <div style="font-size:10px;color:var(--text-muted);margin-top:4px;">
                알림톡으로 먼저 보내고, 막히면 문자로 이어 보냅니다.
              </div>
            </div>

            <button type="button" class="btn btn-primary btn-sm" id="btnPaySend" onclick="sendPaymentLink(this)"
                    @unless($prescription->order) disabled @endunless>
              <i class="fa-solid fa-paper-plane"></i> 전송
            </button>

            {{-- 보낸 것들 — 무엇을 언제 보냈고 냈는지. 다시 보낼지 전화할지는 이걸 보고 정한다 --}}
            <div>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <div style="font-size:11px;font-weight:500;color:var(--text-muted);flex:1;">전송 이력</div>
                <button type="button" class="rx-tpl-mini" onclick="loadPaymentLinks()">새로고침</button>
              </div>
              <div id="payHistory" style="max-height:180px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);font-size:11px;">
                <div style="padding:10px;color:var(--text-muted);text-align:center;">불러오는 중…</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- 현금영수증 --}}
      <div id="cashReceiptArea">
        @if($prescription->order?->cash_receipt_status === 'issued')
        <div style="display:flex;align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
          <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:10px;"></i>
          <span style="font-weight:700;color:var(--primary);">현금영수증</span>
          <button onclick="toggleCrDetailPopover(event)" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--primary);color:var(--primary);border-radius:6px;cursor:pointer;margin-left:2px;">상세</button>
          <button onclick="cancelCashReceipt()" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:6px;cursor:pointer;">취소</button>
        </div>
        @else
        <button class="pib-btn" id="btnCrIssueTrigger" onclick="toggleCrIssuePopover(event)">
          <i class="fa-solid fa-receipt"></i> 현금영수증
        </button>
        @endif
      </div>

      {{-- 팩스 전송 --}}
      <div id="faxTriggerWrap" style="display:{{ $lastFaxHistory ? 'none' : 'block' }};position:relative;">
        <button class="pib-btn" id="btnFaxTrigger" onclick="toggleFaxPopover(event)">
          <i class="fa-solid fa-fax" style="font-size:12px;"></i> 팩스
          <span id="faxSentBadge" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};position:absolute;top:-5px;right:-5px;width:16px;height:16px;border-radius:50%;background:var(--primary);border:2px solid var(--bg-card);align-items:center;justify-content:center;">
            <i class="fa-solid fa-check" style="font-size:7px;color:#fff;"></i>
          </span>
        </button>
        <div id="faxPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:580px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:500;">
          {{-- 머리 색은 다른 창과 같은 주색이다. 여기만 검은색이라 팩스 창만 다른 곳에서
               온 것처럼 보였다(알림톡의 노랑은 카카오 색이라 그대로 둔다). --}}
          <div id="faxPopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          {{-- 헤더 --}}
          <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-fax" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
            <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">팩스 전송</span>
            <button onclick="closeFaxPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:15px;line-height:1;">×</button>
          </div>
          {{-- 전송 완료 배너 --}}
          @php
            $fhDocs    = $lastFaxHistory?->documents ?? [];
            $fhLabels  = array_map(fn($d) => ['authorization'=>'위임장','prescription'=>'처방전','purchase_history'=>'제품 구매내역','cash_receipt'=>'현금영수증'][$d] ?? $d, $fhDocs);
            $fhTimeStr = $lastFaxHistory?->created_at?->format('Y-m-d H:i') ?? '';
            $fhFaxNo   = $lastFaxHistory?->fax_no ?? '';
            $fhRecip   = ['nhis'=>'국민건강보험공단','custom'=>'기타'][$lastFaxHistory?->recipient_type ?? ''] ?? ($lastFaxHistory?->recipient_type ?? '');
            $fhPdfUrl  = $lastFaxHistory?->pdf_path
              ? (rtrim(request()->root(), '/') . '/storage/' . $lastFaxHistory->pdf_path)
              : null;
          @endphp
          <div id="faxSentBanner" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};padding:8px 14px;background:var(--primary-50);border-bottom:1px solid var(--primary-200);font-size:11px;align-items:center;gap:8px;">
            <i class="fa-solid fa-circle-check" style="color:var(--primary);flex-shrink:0;"></i>
            <div style="flex:1;line-height:1.5;">
              <span id="faxSentBannerText" style="font-weight:500;color:var(--primary-600);">{{ $lastFaxHistory ? "{$fhTimeStr} 전송 완료 — {$fhRecip} ({$fhFaxNo})" . ($fhLabels ? ' | ' . implode(', ', $fhLabels) : '') : '' }}</span>
            </div>
            <a id="faxSentBannerPdf" href="{{ $fhPdfUrl ?? '#' }}" target="_blank"
               style="font-size:10px;color:var(--primary);white-space:nowrap;text-decoration:none;font-weight:500;{{ $fhPdfUrl ? '' : 'display:none;' }}">
              <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
          </div>
          {{-- 2컬럼 본문 --}}
          <div style="padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch;">
            {{-- 왼쪽: 수신처 + 팩스번호 + 안내 --}}
            <div style="display:flex;flex-direction:column;gap:10px;">
              <div>
                <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:6px;">수신처</div>
                <div style="display:flex;flex-direction:column;gap:5px;">
                  <button type="button" class="fax-recipient-btn" data-fax="" data-recipient-type="nhis" onclick="selectFaxRecipient(this)"
                          style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid var(--primary);border-radius:var(--radius);background:var(--primary-light);cursor:pointer;text-align:left;">
                    <div>
                      <div style="font-size:12px;font-weight:700;color:var(--primary);">국민건강보험공단</div>
                      <div style="font-size:10px;color:var(--text-muted);">공단 · 지사 검색</div>
                    </div>
                    <i class="fa-solid fa-magnifying-glass" style="font-size:12px;color:var(--primary);"></i>
                  </button>
                  <button type="button" class="fax-recipient-btn" data-fax="" data-recipient-type="custom" onclick="selectFaxRecipient(this)"
                          style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg-card);cursor:pointer;text-align:left;">
                    <div>
                      <div style="font-size:12px;font-weight:700;color:var(--text);">기타</div>
                      <div style="font-size:10px;color:var(--text-muted);">직접 입력</div>
                    </div>
                    <i class="fa-solid fa-pen" style="font-size:11px;color:var(--text-muted);"></i>
                  </button>
                </div>
                {{-- 공단 지사 검색 패널 --}}
                <div id="nhisSearchPanel" style="display:none;background:var(--bg);border:1px solid var(--primary);border-radius:var(--radius);padding:8px;margin-top:6px;">
                  <input type="text" id="nhisSearchInput" class="form-control"
                         style="height:32px;font-size:11px;margin-bottom:6px;padding:3px 8px;"
                         placeholder="지역명 또는 지사명 검색..."
                         oninput="renderNhisOffices(this.value)">
                  <div id="nhisOfficeList" style="max-height:112px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;"></div>
                </div>
              </div>
              <div>
                <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:5px;">수신 팩스번호</div>
                <input type="text" id="fax-no" class="form-control" style="font-size:12px;height:32px;"
                       placeholder="지사 선택 또는 직접 입력"
                       oninput="onFaxNoInput()">
              </div>
              <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:7px 10px;font-size:10px;color:var(--text-muted);line-height:1.6;">
                <i class="fa-solid fa-circle-info" style="margin-right:3px;"></i>
                공단은 지사를 검색하여 선택하세요. 팩스번호는 직접 수정 가능합니다.
              </div>
            </div>
            {{-- 오른쪽: 전송 서류 --}}
            <div style="display:flex;flex-direction:column;">
              <div style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:6px;">전송 서류 선택</div>
              <div style="display:flex;flex-direction:column;gap:4px;flex:1;overflow-y:auto;padding-right:2px;">
                @php
                  $latestConsent = $prescription->consents()->where('status','agreed')->latest()->first();
                @endphp
                {{-- 팩스는 환자 등록·재등록(Step1) 전용이다. 위임 등록과 청구는 공단 사이트에
                     직접 입력·업로드하므로 청구 서류를 팩스로 보내지 않는다. --}}
                <div style="padding:9px 11px;border:1px solid var(--primary-200);border-radius:var(--radius);background:var(--primary-light);font-size:11px;color:var(--text-secondary);line-height:1.65;">
                  <b style="color:var(--primary);">팩스는 환자 등록·재등록용입니다.</b><br>
                  등록신청서 · 결과지 · 신분증을 아래 첨부에서 골라 공단 관할지사로 보냅니다.<br>
                  위임 등록과 청구 서류는 팩스가 아니라 <b>공단 사이트에 직접 업로드</b>합니다.
                </div>

                {{-- ── 첨부 문서 (등록신청서·결과지·신분증) ── --}}
                @if($prescription->attachments->isNotEmpty())
                <div style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);">
                  <div style="font-size:10px;font-weight:500;color:var(--text-muted);margin-bottom:4px;">
                    <i class="fa-solid fa-paperclip"></i> 첨부 문서
                  </div>
                  @foreach($prescription->attachments as $att)
                  <label style="display:flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;margin-bottom:3px;">
                    <input type="checkbox" class="fax-att-chk" value="{{ $att->id }}"
                           style="accent-color:var(--primary);" checked>
                    <div style="flex:1;min-width:0;">
                      <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-weight:500;">{{ $att->doc_type_label }}</span>
                        <span style="font-size:10px;background:var(--primary-light);color:var(--primary);border:1px solid var(--primary-accent);border-radius:6px;padding:1px 5px;">첨부</span>
                      </div>
                      <div style="font-size:10px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $att->file_original_name }}</div>
                    </div>
                    @if($att->is_image)
                      <img src="{{ $att->file_url }}" style="width:28px;height:28px;object-fit:cover;border-radius:6px;border:1px solid var(--border);flex-shrink:0;" />
                    @else
                      <i class="fa-regular fa-file-pdf" style="color:var(--danger);font-size:18px;flex-shrink:0;"></i>
                    @endif
                  </label>
                  @endforeach
                </div>
                @endif
              </div>
            </div>
          </div>
          {{-- 전송 버튼 (전체 너비) --}}
          <div style="padding:0 14px 14px;">
            <button id="btnFaxSend" onclick="sendFax()"
                    style="width:100%;padding:8px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
              <i class="fa-solid fa-paper-plane"></i> 팩스 전송
            </button>
          </div>
        </div>
      </div>
      <div id="faxResultBadge" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--primary);">팩스 전송</span>
        <button id="faxPdfViewBtn" data-url="{{ $lastFaxHistory?->pdfUrl() }}"
                onclick="openFaxPdfModal()"
                style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--primary);color:var(--primary);border-radius:6px;cursor:pointer;margin-left:2px;">보기</button>
        <button onclick="reopenFaxPopover(event)" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--primary);color:var(--primary);border-radius:6px;cursor:pointer;">재전송</button>
      </div>

      {{-- Withworks 판매번호 --}}
      <div id="wwSoCard" style="display:flex;align-items:center;height:32px;gap:5px;padding:4px 9px;border:1px solid {{ $prescription->order?->withworks_so_no ? 'var(--primary)' : 'var(--border)' }};border-radius:var(--radius);background:{{ $prescription->order?->withworks_so_no ? 'var(--primary-light)' : 'var(--bg-card)' }};">
        <i class="fa-solid fa-link" style="color:var(--primary);font-size:10px;flex-shrink:0;"></i>
        <div id="wwSoContent" style="font-size:11px;line-height:1.2;">
          @if($prescription->order?->withworks_so_no)
          <span style="font-family:monospace;font-weight:700;color:var(--primary);">{{ $prescription->order->withworks_so_no }}</span>
          @else
          <span id="wwSoBadge" style="color:var(--text-muted);">미연계</span>
          @endif
        </div>
      </div>

      {{-- 세금계산서 --}}
      @if($prescription->order?->tax_invoice_status === 'issued')
      <div id="tiIssuedBadge" style="display:flex;align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--primary);">세금계산서</span>
        <button onclick="cancelTaxInvoice()" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:6px;cursor:pointer;margin-left:2px;">취소</button>
      </div>
      @else
      <div id="tiNotIssuedWrap" style="position:relative;">
        <button class="pib-btn" id="btnTiTrigger" onclick="toggleTaxInvoicePopover(event)">
          <i class="fa-solid fa-file-invoice"></i> 세금계산서
        </button>
        <div id="taxInvoicePopover" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:400px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:501;">
          <div style="position:absolute;top:-8px;right:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-file-invoice" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
            <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">세금계산서 발행</span>
            <button onclick="closeTaxInvoicePopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">발행 유형</label>
              <select id="ti-type" class="form-control" style="font-size:12px;">
                <option value="electronic">전자세금계산서</option>
                <option value="manual">수기</option>
              </select>
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">공급받는자 구분 <span style="color:var(--danger);">*</span></label>
              {{-- 이 화면의 세금계산서는 환자가 구매한 건이라 '개인'이 정상이다.
                   사업자는 대리점·병원이 사가는 예외 건에만 쓴다. --}}
              <select id="ti-invoicee" class="form-control" style="font-size:12px;" onchange="tiInvoiceeChanged()">
                <option value="개인">개인 — 환자 (주민등록번호)</option>
                <option value="사업자">사업자 (사업자등록번호)</option>
              </select>
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">공급받는자 상호 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-biz-name" class="form-control" style="font-size:12px;" placeholder="(주)예시">
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">대표자명 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-ceo-name" class="form-control" style="font-size:12px;" placeholder="홍길동">
            </div>
            <div>
              <label id="ti-biz-no-label" style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">사업자등록번호 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-biz-no" class="form-control" style="font-size:12px;" placeholder="123-45-67890">
              <div id="ti-biz-no-hint" style="display:none;font-size:11px;color:var(--text-muted);margin-top:4px;">
                비워 두면 이 처방전에 저장된 환자 주민등록번호로 발행합니다. 번호는 화면에 나오지 않으며 열람 기록이 남습니다.
              </div>
            </div>
            <div>
              <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">이메일 (전자발송)</label>
              <input type="email" id="ti-email" class="form-control" style="font-size:12px;" placeholder="billing@example.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <div>
                <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">공급가액 <span style="color:var(--danger);">*</span></label>
                <input type="text" id="ti-supply" class="form-control" style="font-size:12px;" inputmode="numeric" placeholder="0" oninput="formatCrAmount(this); autoCalcVat()">
              </div>
              <div>
                <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">세액 <span style="color:var(--danger);">*</span></label>
                <input type="text" id="ti-vat" class="form-control" style="font-size:12px;" inputmode="numeric" placeholder="0" oninput="formatCrAmount(this)">
              </div>
            </div>
            <div style="font-size:11px;color:var(--text-muted);background:var(--bg);border-radius:var(--radius);padding:7px 10px;">
              <i class="fa-solid fa-circle-info"></i> 공급가액 입력 시 세액(10%)이 자동 계산됩니다.
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <button class="btn btn-outline btn-sm" onclick="closeTaxInvoicePopover()">취소</button>
              <button class="btn btn-primary btn-sm" id="btnSubmitTaxInvoice" onclick="submitTaxInvoice()">
                <i class="fa-solid fa-file-invoice"></i> 발행
              </button>
            </div>
          </div>
        </div>
      </div>
      <div id="tiResultBadge" style="display:none;align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--primary);">세금계산서</span>
        <button onclick="cancelTaxInvoice()" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:6px;cursor:pointer;margin-left:2px;">취소</button>
      </div>
      @endif
    </div>

      {{-- 라벨 칩 + 값, 사이는 4px 점 --}}
      <div class="pib-row-meta">
        <span style="display:inline-flex;align-items:center;gap:6px;">
          <span class="pib-tag">전화</span>
          <span class="pib-val" id="hdrPatientPhone">{{ $prescription->patient?->mobile ?? '-' }}</span>
        </span>
        <span class="pib-dot"></span>
        <span style="display:inline-flex;align-items:center;gap:6px;">
          <span class="pib-tag">병원</span>
          <span class="pib-val" id="hdrHospital">{{ $prescription->hospital_name ?? '-' }}</span>
        </span>
        <span class="pib-dot"></span>
        <span style="display:inline-flex;align-items:center;gap:6px;">
          <span class="pib-tag">담당</span>
          <span class="pib-val" id="hdrAssignee">{{ $prescription->assignedUser?->name ?? '-' }}</span>
        </span>
      </div>
    </div>{{-- /pib-body --}}
  </div>

  {{-- ── 메모 패널 (JS로 위치 결정 — fixed) ─────────────── --}}
  <div id="memoPanelWrap" style="display:none;position:fixed;z-index:1200;width:340px;">
    <div style="background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.14);overflow:hidden;">
      {{-- 헤더 --}}
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--primary);color:#fff;">
        <span style="font-size:13px;font-weight:700;"><i class="fa-solid fa-note-sticky"></i> 메모
          <span id="memoPanelCount" style="font-size:11px;opacity:.85;margin-left:4px;">({{ $prescription->memos->count() }}건)</span>
        </span>
        <button onclick="toggleMemoPanel(event)" style="background:none;border:none;color:#fff;cursor:pointer;font-size:16px;line-height:1;padding:0;">×</button>
      </div>
      {{-- 새 메모 입력 --}}
      <div style="padding:10px 12px;border-bottom:1px solid var(--border);">
        <textarea id="memoNewInput" placeholder="새 메모를 입력하세요..." rows="2"
                  style="width:100%;border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:12px;resize:none;outline:none;"
                  onkeydown="if(event.ctrlKey&&event.key==='Enter')saveMemo()"></textarea>
        <div style="display:flex;justify-content:flex-end;margin-top:4px;">
          <button onclick="saveMemo()"
                  style="padding:4px 14px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:12px;cursor:pointer;font-weight:500;">
            <i class="fa-solid fa-plus"></i> 저장
          </button>
        </div>
      </div>
      {{-- 메모 목록 --}}
      <div id="memoList" style="max-height:320px;overflow-y:auto;padding:8px 0;"></div>
    </div>
  </div>

  <div class="page-body-inner">
  <div class="order-layout">

    {{-- Col 1: Image Viewer --}}
    <div id="viewerCol">
    <div id="viewerInner">
      {{-- 카드 머리 — 이전·다음과 처방번호, 오른쪽에 뷰어 조작 (시안 137:883) --}}
      <div class="vw-head">
        <div class="vw-nav">
          <button type="button" class="vw-nav-btn" onclick="prevRecord()" title="이전 처방전"><i class="fa-solid fa-chevron-left"></i></button>
          <span class="vw-rx">{{ $prescription->rx_number }}</span>
          <button type="button" class="vw-nav-btn" onclick="nextRecord()" title="다음 처방전"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        {{-- 볼 것이 있는지는 처방전 이미지가 아니라 문서 전체로 따진다.
             이미지 없이 첨부(신분증·위임 서명 등)만 있는 처방전에서 크게 보기·원본보기가
             통째로 숨어, 붙어 있는 파일을 열 길이 없었다. --}}
        @php $firstDoc = $allDocsJson[0] ?? null; @endphp
        <div class="vw-acts">
          <button type="button" id="btnToggleViewerSide" onclick="toggleViewerSide()" class="vw-btn" title="뷰어 위치 바꾸기">
            <span id="btnToggleViewerSideLabel">오른쪽으로</span>
          </button>
          {{-- 파일을 크게 보되 화면은 계속 쓸 수 있어야 한다 — 모달이 아니라 떠 있는 창을 연다 --}}
          <button type="button" id="btnBigViewer" class="vw-btn" onclick="openBigViewer()" title="파일을 큰 창으로 봅니다 (창을 옮길 수 있고, 그동안에도 입력할 수 있습니다)"
                  @if(!$firstDoc) style="display:none;" @endif>크게 보기</button>
          {{-- 끌어 옮기고 키운 것을 한 번에 되돌린다. 그림 위 도구에도 같은 것이 있지만
               거기까지 손을 옮겨야 했다 — 크게 보기 옆이 손이 이미 가 있는 자리다. --}}
          <button type="button" id="btnResetView" class="vw-btn vw-btn-icon" onclick="resetImg()"
                  title="처음으로 되돌리기 (배율·회전·위치)"
                  @if(!$firstDoc) style="display:none;" @endif><i class="fa-solid fa-arrows-rotate"></i></button>
          <a id="viewerOpenBtn" class="vw-btn vw-btn-icon"
             href="{{ $prescription->image_url ?? ($firstDoc['url'] ?? '#') }}" target="_blank" title="원본보기"
             @if(!$firstDoc) style="display:none;" @endif><i class="fa-solid fa-expand"></i></a>
        </div>
      </div>

      <div class="img-viewer">
        {{-- 이미지 위에 얹는 도구 — 위는 회전·복원, 아래는 확대·축소 (시안 137:901) --}}
        <div class="vw-tools">
          <div class="vw-tool-group">
            <button type="button" class="vw-tool" onclick="rotateImg()" title="회전"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="vw-tool" onclick="resetImg()" title="처음으로 복원"><i class="fa-solid fa-arrows-rotate"></i></button>
          </div>
          <div class="vw-tool-group">
            <button type="button" class="vw-tool" onclick="zoomOut()" title="축소"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <span id="zoomLabel" class="vw-zoom">100%</span>
            <button type="button" class="vw-tool" onclick="zoomIn()" title="확대"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          </div>
        </div>
        <div class="img-viewer-canvas" id="imgCanvas">
          @php $isRxPdf = str_contains($prescription->image_mime_type ?? '', 'pdf'); @endphp
          @if($prescription->image_url && $isRxPdf)
            <img id="prescCanvas" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="" draggable="false" />
            <iframe id="pdfCanvas" src="{{ $prescription->image_url }}" style="width:100%;height:100%;border:none;background:#fff;"></iframe>
          @elseif($prescription->image_url)
            <img id="prescCanvas" src="{{ $prescription->image_url }}" style="max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="처방전 이미지" draggable="false" />
            <iframe id="pdfCanvas" src="" style="display:none;width:100%;height:100%;border:none;background:#fff;"></iframe>
          @else
            <div class="img-placeholder">
              <i class="fa-regular fa-file-image"></i>
              <p>이미지 없음</p>
            </div>
            <img id="prescCanvas" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="" draggable="false" />
            <iframe id="pdfCanvas" src="" style="display:none;width:100%;height:100%;border:none;background:#fff;"></iframe>
          @endif
        </div>
        {{-- 이전·다음과 처방번호는 카드 머리로 올라갔다 (시안 137:883) --}}
      </div>

      {{-- 이미지 아래 카드들을 한 상자에 담는다 (시안 148:1585) —
           여백 16·간격 12·위쪽 선은 전부 #viewerCards 의 CSS 가 준다. --}}
      <div id="viewerCards">

      {{-- ── 위임 서명 — 서명 이미지와 보호자 신분증 ──────────
           서명이 끝난 뒤에만 나타난다. 값은 위임동의 현황 조회에서 함께 받는다. --}}
      <div class="vw-card" id="signCard" style="display:none;">
        <div class="vw-card-head">
          <span class="vw-card-title">위임 서명</span>
          <div class="vw-card-acts">
            <a id="signCardPng" class="vw-btn" href="{{ route('prescriptions.consentSignature', $prescription) }}"
               title="서명 이미지를 파일로 받습니다">서명 PNG</a>
          </div>
        </div>
        <div style="padding:12px;display:flex;flex-direction:column;gap:12px;">
          <div>
            <div class="sc-cap">위임인 서명</div>
            <div class="sc-box"><img id="signCardImg" alt="위임인 서명" /></div>
          </div>
          <div id="signCardGuardianWrap" style="display:none;">
            <div class="sc-cap">보호자 서명 <span id="signCardGuardianWho"></span></div>
            <div class="sc-box"><img id="signCardGuardianImg" alt="보호자 서명" /></div>
          </div>
          <div id="signCardIdWrap" style="display:none;">
            <div class="sc-cap">
              보호자 신분증
              <a id="signCardIdOpen" href="#" target="_blank" rel="noopener"
                 style="float:right;font-size:11px;color:var(--primary);text-decoration:none;">크게 보기</a>
            </div>
            <div class="sc-box"><img id="signCardIdImg" alt="보호자 신분증" /></div>
          </div>
        </div>
      </div>

      {{-- ── 통합 문서 스트립 (처방전 + 첨부 파일) ── --}}
      <div class="vw-card" id="docStripWrap" @if(!$prescription->image_url && $prescription->attachments->isEmpty()) style="display:none;" @endif>
        {{-- 카드 머리 — 제목·개수와 유형 선택·첨부 추가 (시안 137:793) --}}
        <div class="vw-card-head">
          <span class="vw-card-title">문서 <b id="docCount">{{ ($prescription->image_url ? 1 : 0) + $prescription->attachments->count() }}</b></span>
          <div class="vw-card-acts">
            <div style="position:relative;">
              <input type="text" id="attachDocTypeSelect" value="신분증" autocomplete="off"
                     class="vw-btn-sm" style="width:120px;padding-right:26px;font-weight:400;"
                     oninput="_adtFilter(this.value)" onfocus="_adtOpen()" onblur="setTimeout(_adtClose,150)" />
              <span onmousedown="event.preventDefault();_adtToggle()"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--gray-600);font-size:12px;">
                <i class="fa-solid fa-chevron-right"></i>
              </span>
              <div id="_adtDrop" style="display:none;position:absolute;top:calc(100% + 2px);left:0;min-width:100%;background:var(--gray-0);border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:10001;">
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('처방전')"   style="padding:6px 12px;font-size:12px;cursor:pointer;">처방전</div>
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('위임장')"   style="padding:6px 12px;font-size:12px;cursor:pointer;">위임장</div>
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('신분증')" style="padding:6px 12px;font-size:12px;cursor:pointer;">신분증</div>
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('등록신청서')" style="padding:6px 12px;font-size:12px;cursor:pointer;">등록신청서</div>
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('결과지')"   style="padding:6px 12px;font-size:12px;cursor:pointer;">결과지</div>
                <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('기타')"     style="padding:6px 12px;font-size:12px;cursor:pointer;">기타</div>
              </div>
            </div>
            <button type="button" class="vw-btn-sm vw-btn-add" onclick="document.getElementById('attachUploadInput').click()">
              <i class="fa-solid fa-plus"></i> 첨부문서 추가
            </button>
            <input type="file" id="attachUploadInput" accept=".jpg,.jpeg,.png,.pdf,.heic" style="display:none" onchange="handleAttachUpload(this)">
          </div>
        </div>
        <div class="attach-strip" id="docStrip">
          {{-- 처방전 (삭제 불가) --}}
          @if($prescription->image_url)
            @php $isRxPdfThumb = str_contains($prescription->image_mime_type ?? '', 'pdf'); @endphp
            <div class="attach-thumb doc-thumb active" onclick="switchViewerDoc(this)">
              @if($isRxPdfThumb)
                <div class="attach-thumb-pdf"><i class="fa-regular fa-file-pdf"></i></div>
              @else
                <img class="attach-thumb-img" src="{{ $prescription->image_url }}" alt="처방전" loading="lazy" />
              @endif
              <div class="attach-type-badge">처방전</div>
            </div>
          @endif
          {{-- 첨부 파일 --}}
          @foreach($prescription->attachments as $att)
            @php $isPdf = $att->is_pdf; @endphp
            <div class="attach-thumb doc-thumb" data-att-id="{{ $att->id }}" onclick="switchViewerDoc(this)">
              @if($isPdf)
                <div class="attach-thumb-pdf"><i class="fa-regular fa-file-pdf"></i></div>
              @else
                <img class="attach-thumb-img" src="{{ $att->file_url }}" alt="{{ $att->doc_type_label }}" loading="lazy" />
              @endif
              <div class="attach-type-badge">{{ $att->doc_type_label }}</div>
              <button class="attach-del-btn" onclick="deleteAttachment(event, {{ $att->id }}, this)" title="삭제">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          @endforeach
        </div>
      </div>

      {{-- ── 생성 서류 (위임동의서·요양비위임장 등) — 서명 시 실시간 갱신 ── --}}
      <div id="genDocsContainer">
        @include('prescriptions._generated_docs')
      </div>

      {{-- 유형 선택과 첨부 추가는 문서 카드 머리로 올라갔다 (시안 137:797) --}}

      {{-- 등록자 카드 — 등록·검수·수정을 한 줄씩 (시안 137:653) --}}
      <div class="vw-card rg-card">

        {{-- 역할별 한 줄 (시안 137:658) --}}
        <div class="rg-rows" id="infoPanel-uploader">
          @if($prescription->reviewer)
          <div class="rg-row">
            <span class="rg-badge rg-badge-review">검수</span>
            <span class="rg-name">{{ $prescription->reviewer->name }}</span>
            <span class="rg-when"><span>검수일자</span><span>{{ $prescription->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</span></span>
          </div>
          @endif
          <div class="rg-row">
            <span class="rg-badge rg-badge-create">등록</span>
            <span class="rg-name">{{ $prescription->creator?->name ?? '정보 없음' }}</span>
            <span class="rg-when"><span>등록일자</span><span>{{ $prescription->created_at->format('Y-m-d H:i') }}</span></span>
            @if($prescription->creator)
            <button type="button" class="rg-chat" title="{{ $prescription->creator->name }}와 채팅"
                    onclick="openChatWith({{ $prescription->creator->id }}, '{{ $prescription->creator->name }}')">
              <i class="fa-solid fa-comments"></i>
            </button>
            @endif
          </div>
          {{-- 수정 기록이 붙기 전(2026-08-06 이전) 처방전은 값이 없어 줄을 그리지 않는다 --}}
          @if($prescription->updater)
          <div class="rg-row">
            <span class="rg-badge rg-badge-update">수정</span>
            <span class="rg-name">{{ $prescription->updater->name }}</span>
            <span class="rg-when"><span>수정일자</span><span>{{ $prescription->updated_at->format('Y-m-d H:i') }}</span></span>
          </div>
          @endif
        </div>

        @if($prescription->admin_note)
        <div style="padding:10px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-size:12px;line-height:1.7;color:var(--gray-1000);white-space:pre-wrap;">
          <div style="font-size:10px;font-weight:700;color:var(--gray-600);margin-bottom:4px;"><i class="fa-solid fa-note-sticky"></i> 등록자 메모</div>{{ $prescription->admin_note }}
        </div>
        @endif

        {{-- OCR 신뢰도는 쓰지 않는다. 숫자가 무엇을 뜻하는지 사람마다 달리 읽었고,
             높든 낮든 하는 일은 같았다 — 어차피 눈으로 보고 고친다. --}}
      </div>

      @if($prescription->review_memo)
      <div class="card mt-3" id="reviewMemoCard">
        <div class="card-header"><i class="fa-solid fa-note-sticky" style="color:var(--warning);"></i><span class="card-header-title">검수 메모</span></div>
        <div class="card-body" style="font-size:12px;color:var(--text-secondary);">{{ $prescription->review_memo }}</div>
      </div>
      @endif
      </div>{{-- /viewerCards --}}
    </div>{{-- /viewerInner --}}
    </div>{{-- /viewerCol --}}

    {{-- Col 2: OCR Edit + Order --}}
    <div id="tabsCol">
      <div id="tabBarOuter"><div id="tabBarInner" class="tab-bar">
        <div class="tab-bar-tabs">
          <button class="tab-btn active" onclick="switchTab(this,'tab-ocr')">처방전 검수</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-product')">주문 제품</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-order')">주문 연계</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-history')">이력</button>
        </div>
        <div class="tab-bar-acts">
          {{-- 글자 링크 묶음 — 시안은 이 둘만 테두리 없이 둔다 (137:696) --}}
          <div class="tb-links">
            <button type="button" id="btnAccToggleAll" class="tb-link" onclick="toggleAllAcc()">
              <i class="fa-solid fa-angles-down" id="btnAccToggleAllIcon"></i>
              <span id="btnAccToggleAllLabel">전체 열기</span>
            </button>
            <button type="button" id="btnViewToggle" class="tb-link" onclick="toggleTabView()">
              <i class="fa-solid fa-table-list" id="btnViewToggleIcon"></i>
              <span id="btnViewToggleLabel">테이블뷰</span>
            </button>
          </div>

          {{-- 테두리 버튼 묶음 (137:705) --}}
          <div class="tb-btns">
          {{-- 시안 순서 그대로 — 환자 조회, 신규 등록 (137:706·708). 아이콘 없이 글자만. --}}
          <button type="button" id="btnPatientLookup" class="tb-act" onclick="openPatientLookup()"
                  title="환자명으로 조회해 과거 상담이력을 가져옵니다">환자 조회</button>
          <button type="button" id="btnNewEntry" class="tb-act" onclick="resetReviewScreen()"
                  title="검수 화면의 모든 입력 내용을 비웁니다">신규 등록</button>
          {{-- 원본 복원·승인 요청·저장은 시안(148:2639)대로 아코디언 헤더에 둔다 --}}
          </div>{{-- /tb-btns --}}
        </div>
      </div></div>{{-- /tabBarInner /tabBarOuter --}}

      {{-- Tab: OCR Edit (처방전 검수) --}}
      <div class="tab-pane active" id="tab-ocr">
      <div class="cv">

        @php $displayRn = $prescription->masked_resident_no_ocr ?? $prescription->patient?->masked_resident_no; @endphp


        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 1: 상담 · 환자 정보 (통합)
        ───────────────────────────────────────────────── --}}
        @php
          $curCounselNo   = $prescription->counsel_no ?? '';
          $curCounselDate = $prescription->counsel_date ?? now()->format('Y-m-d');
          $rawCallNo = $prescription->counsel_call_no ?? '';
          $digCallNo = preg_replace('/[^0-9]/', '', $rawCallNo);
          if (strlen($digCallNo) === 11)     $fmtCallNo = substr($digCallNo,0,3).'-'.substr($digCallNo,3,4).'-'.substr($digCallNo,7);
          elseif (strlen($digCallNo) >= 9)   $fmtCallNo = substr($digCallNo,0,3).'-'.substr($digCallNo,3,3).'-'.substr($digCallNo,6);
          else                               $fmtCallNo = $rawCallNo;
          $isReturningPatient = $prescription->patient_id && $prevCounselings->isNotEmpty();
        @endphp
        <div class="rx-acc-item is-open">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i> 상담ㆍ환자 정보
              @if($isReturningPatient)
                <span style="display:inline-flex;align-items:center;gap:3px;background:var(--primary-50);color:var(--primary-600);border:1px solid var(--primary-200);border-radius:4px;font-size:10px;font-weight:700;padding:1px 6px;">
                  <i class="fa-solid fa-rotate-right" style="font-size:9px;"></i> 재방문
                </span>
              @endif
            </span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">상담정보ㆍ환자정보</span>
              {{-- 헤더를 눌러 접히지 않게 클릭을 여기서 멈춘다 --}}
              <div class="rx-acc-btns" onclick="event.stopPropagation()">
                <button type="button" class="rx-acc-btn" onclick="resetOCR()" title="입력값을 원본 OCR 결과로 되돌립니다">원본 복원</button>
                <button type="button" class="rx-acc-btn" onclick="approveRx()" title="검수 완료 후 승인을 요청합니다">승인 요청</button>
                <button type="button" class="rx-acc-btn rx-acc-btn-fill" onclick="saveOCR()" title="검수 내용을 저장합니다">저장</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon open"></i>
            </div>
          </div>
          <div class="rx-acc-body">

            {{-- ▸ 상담 정보 소제목 + 메모 버튼 --}}
            <div class="rx-sec-head">
              <span class="rx-sec-title">상담 정보</span>
              <button id="memoPanelToggleBtn" onclick="toggleMemoPanel(event)"
                      class="rx-sec-btn">
                <i class="fa-solid fa-note-sticky"></i> 메모
                <span id="memoBadgeCount"
                      style="display:{{ $prescription->memos->count() > 0 ? 'flex' : 'none' }};position:absolute;top:-5px;right:-5px;width:14px;height:14px;border-radius:50%;background:var(--danger);color:#fff;font-size:10px;align-items:center;justify-content:center;font-weight:700;line-height:1;">
                  {{ $prescription->memos->count() }}
                </span>
              </button>
            </div>
            <div class="rx-cols">
            <div class="rx-col">
              {{-- 1열 — 상담 번호 · 상담 일자 (시안 315:58 Frame 48101490) --}}
              <div class="rx-field-row" style="align-items:flex-start;">
                <span class="rx-field-label">상담 번호</span>
                {{-- 시안(Frame 48101481)은 두 칸이다: [입력 144 FILL][추가상담(채번) 101 HUG], 사이 8 = 253.
                     '과거 상담 N' 은 재방문 환자에게만 붙는 세 번째 칸이라 253 에 다 서지 못한다.
                     입력의 기준 폭을 시안값 144 로 두고 줄바꿈을 허용해, 자리가 모자라면
                     버튼만 아랫줄로 내려가게 했다. 버튼은 하나도 지우지 않는다.
                     min-width 로 144 를 '잠그면' 안 된다 — 3열 1600(입력영역 141)·2열 1280(117)에서
                     입력이 줄지 못해 열 밖으로 3~27px 삐져나갔다. min-width:0 으로 두면
                     좁을 때 입력이 열 폭까지 줄어들고 버튼만 아랫줄로 내려간다. --}}
                <div style="display:flex;gap:8px;flex:1;min-width:0;align-items:center;flex-wrap:wrap;row-gap:8px;">
                  <input type="text" class="form-control" id="f-counselling-no"
                         value="{{ $curCounselNo }}"
                         placeholder="채번 버튼을 눌러 번호를 생성하세요"
                         style="flex:1 1 144px;min-width:0;" />
                  @if($isReturningPatient)
                  <button type="button" class="rx-side-btn" onclick="openPrevCounselModal()"
                          title="이전 상담 이력 {{ $prevCounselings->count() }}건">
                    과거 상담 {{ $prevCounselings->count() }}
                  </button>
                  @endif
                  <button type="button" id="btnCounselNo" class="rx-side-btn" onclick="generateCounselNo()">추가상담(채번)</button>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">상담 일자</span>
                <div style="display:flex;gap:8px;flex:1;align-items:center;min-width:0;">
                  <input type="date" class="form-control" id="f-counsel-date" value="{{ $curCounselDate }}" style="flex:1;min-width:0;" />
                  <button type="button" class="rx-side-btn"
                          onclick="document.getElementById('f-counsel-date').value='{{ now()->format('Y-m-d') }}'">오늘</button>
                </div>
              </div>
            </div>

            <div class="rx-col">
              {{-- 2열 — 상담 유형 · 상담 상태 (시안 315:58 Frame 48101511) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">상담 유형</span>
                <select class="form-control" id="f-counsel-type" onchange="onCounselTypeChange(this.value)" style="flex:1;">
                  <option value="">선택</option>
                  <option value="1013" @selected(($prescription->counsel_type ?? '') == '1013')>구매</option>
                  <option value="1016" @selected(($prescription->counsel_type ?? '') == '1016')>개인구매</option>
                  <option value="1020" @selected(($prescription->counsel_type ?? '') == '1020')>반품</option>
                  <option value="1030" @selected(($prescription->counsel_type ?? '') == '1030')>문의</option>
                  <option value="1050" @selected(($prescription->counsel_type ?? '') == '1050')>기타</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">상담 상태</span>
                <select class="form-control" id="f-counsel-status" onchange="onCounselStatusChange(this.value)" style="flex:1;">
                  <option value="">선택</option>
                  <option value="02" @selected(($prescription->counsel_status ?? '') == '02')>등록</option>
                  <option value="50" @selected(($prescription->counsel_status ?? '') == '50')>재상담</option>
                  <option value="95" @selected(($prescription->counsel_status ?? '') == '95')>확정</option>
                  <option value="99" @selected(($prescription->counsel_status ?? '') == '99')>취소</option>
                </select>
              </div>
            </div>

            <div class="rx-col">
              {{-- 3열 — 처방전 여부 · 메모 (시안 315:58 Frame 48101512).
                   재 상담 일자는 시안에 없지만, 상담 상태가 '재상담'일 때만 열리는
                   입력이라 빼면 그 값을 넣을 자리가 사라진다. 시안 두 줄 뒤에 이어 둔다. --}}
              <div class="rx-field-row">
                <span class="rx-field-label">처방전 여부</span>
                <select class="form-control" id="f-acc-add-type" style="flex:1;">
                  <option value="">선택</option>
                  <option value="20"  @selected(($prescription->counsel_acc_add_type ?? '') == '20')>처방외</option>
                  <option value="10"  @selected(($prescription->counsel_acc_add_type ?? '') == '10')>원외</option>
                  <option value="30"  @selected(($prescription->counsel_acc_add_type ?? '') == '30')>원내</option>
                </select>
              </div>
              <div class="rx-field-row" style="align-items:flex-start;">
                <span class="rx-field-label">메모</span>
                {{-- 값은 제 컬럼에서 읽는다. 상담 JSON 에서 꺼내 각자 컬럼에 담았다. --}}
                <textarea class="form-control" id="f-counsel-memo" rows="2" style="flex:1;resize:vertical;">{{ $prescription->counsel_contents ?? '' }}</textarea>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">재 상담 일자</span>
                <input type="date" class="form-control" id="f-re-counsel-date"
                       value="{{ $prescription->counsel_re_date ?? '' }}" style="flex:1;" />
              </div>
            </div>
            </div>{{-- /rx-cols --}}

            {{-- ▸ 환자 정보 소제목 --}}
            <div class="rx-sec-head" style="margin-top:24px;">
              <span class="rx-sec-title">환자 정보</span>
            </div>
            <div class="rx-cols">
            <div class="rx-col">
              {{-- 1열 — 환자명* · 주민등록번호 · 전화번호 1 · 전화번호 2 · 주소 (시안 315:58 Frame 48101490).
                   생년월일과 보호자(guardianBox)는 시안에 없지만 주민번호로 계산되는 짝이라
                   주민등록번호 바로 아래에 남긴다. --}}
              <div class="rx-field-row">
                {{-- 시안 라벨 56개 중 '환자명 *' 하나만 13/700 이다(나머지는 전부 13/500).
                     '병원명 *' 은 시안도 13/500 이라 굵게 하지 않았다. --}}
                <span class="rx-field-label" style="font-weight:700;">환자명 <span style="color:var(--primary);">*</span></span>
                <div class="field-group" style="flex:1;">
                  <input type="text" class="form-control has-ok" id="f-name" value="{{ $prescription->patient_name_ocr }}" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--primary);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">주민등록번호</span>
                {{-- 쓰기 전용이다. 저장된 번호는 복호화해 내려보내지 않고, 있다는 사실만
                     마스킹으로 알린다. 새로 치면 덮어쓰고, 비워 두면 있던 값을 그대로 둔다. --}}
                <input type="text" class="form-control" id="f-resident" value="" maxlength="14"
                       autocomplete="off" inputmode="numeric"
                       placeholder="{{ $displayRn ?: 'XXXXXX-XXXXXXX' }}"
                       title="저장된 번호는 다시 볼 수 없습니다. 새로 입력하면 덮어씁니다."
                       style="flex:1;min-width:0;letter-spacing:1px;" oninput="rnRecalc()" />
              </div>
              {{-- 주민번호 앞자리로 생년월일·만 나이를 즉시 계산해 보여준다.
                   번호를 치는 중에도 바뀌고, 아직 안 쳤으면 저장된 마스킹으로 계산한다. --}}
              <div class="rx-field-row">
                <span class="rx-field-label">생년월일</span>
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                  <input type="text" class="form-control" id="f-birth" readonly
                         style="flex:1;min-width:0;background:var(--gray-50);" placeholder="주민번호를 입력하면 계산됩니다" />
                  <span id="f-age-badge" style="display:none;flex-shrink:0;font-size:11px;font-weight:700;
                        padding:2px 8px;border-radius:999px;white-space:nowrap;"></span>
                </div>
              </div>
              {{-- ── 미성년자 — 법정대리인 ─────────────────────────
                   만 나이가 기준보다 어릴 때만 나타난다. 여기 적어 두면 위임 서명 화면에
                   그대로 보이고, 보호자는 서명과 신분증만 더하면 된다. --}}
              <div id="guardianBox" style="display:none;flex-direction:column;gap:8px;
                   border:1px solid var(--alert-100); background:var(--alert-50);
                   border-radius:8px; padding:10px 12px; margin:4px 0;">
                <div style="font-size:12px;font-weight:700;color:var(--alert-500);">
                  미성년자 — 보호자(법정대리인) 정보
                  <span style="font-weight:500;color:var(--gray-600);">위임 서명 화면에 함께 보입니다.</span>
                </div>
                <div class="rx-field-row">
                  <span class="rx-field-label">보호자 이름</span>
                  <input type="text" class="form-control" id="f-guardian-name" maxlength="50"
                         value="{{ $prescription->patient?->guardian_name ?? '' }}"
                         placeholder="보호자 성명" style="flex:1;" />
                </div>
                <div class="rx-field-row">
                  <span class="rx-field-label">관계</span>
                  <select class="form-control" id="f-guardian-relation" style="flex:1;">
                    @php $gRel = $prescription->patient?->guardian_relation ?? ''; @endphp
                    <option value="">선택</option>
                    @foreach(config('delegation.guardian_relations', ['부','모','조부','조모','법정대리인']) as $r)
                      <option value="{{ $r }}" {{ $gRel === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="rx-field-row">
                  <span class="rx-field-label">보호자 생년월일</span>
                  <input type="text" class="form-control" id="f-guardian-birth" maxlength="10"
                         value="{{ $prescription->patient?->guardian_birth_date ?? '' }}"
                         placeholder="YYYY-MM-DD" inputmode="numeric" style="flex:1;" />
                </div>
                <div class="rx-field-row">
                  <span class="rx-field-label">보호자 전화번호</span>
                  <input type="text" class="form-control" id="f-guardian-phone"
                         value="{{ $prescription->patient?->guardian_phone ?? '' }}"
                         placeholder="010-XXXX-XXXX" data-phone style="flex:1;" />
                </div>

                {{-- 받은 것과 아직 안 받은 것을 한눈에. 서명 화면에서 들어오면 채워진다. --}}
                <div class="rx-field-row">
                  <span class="rx-field-label">진행 상태</span>
                  <div style="display:flex;gap:6px;flex-wrap:wrap;flex:1;min-width:0;">
                    <span id="gbSignState" class="gb-state">위임장 서명 미완료</span>
                    <span id="gbIdState"   class="gb-state">신분증 업로드 미완료</span>
                  </div>
                </div>
              </div>

              <div class="rx-field-row">
                <span class="rx-field-label">전화번호 1</span>
                <input type="text" class="form-control" id="f-mobile"
                       value="{{ $prescription->mobile_ocr ?? $prescription->patient?->mobile ?? '' }}"
                       placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">전화번호 2</span>
                <input type="text" class="form-control" id="f-mobile2"
                       value="{{ $prescription->patient?->phone ?? '' }}"
                       placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone style="flex:1;" />
              </div>
              <div class="rx-field-row" style="align-items:flex-start;">
                <span class="rx-field-label">주소</span>
                <div style="display:flex;flex-direction:column;gap:8px;flex:1;min-width:0;">
                  {{-- 1줄 — 시안 315:58 Frame 48101496: [우편번호 72 FIXED][도로명 주소 92 FILL][주소 검색 73 HUG],
                       사이 8 (72+8+92+8+73 = 253). 옛 시안 148:1304 의 '179 · 179' 는 2열 시절 값이라 틀렸다.
                       우편번호·도로명은 둘 다 읽기 전용이라 시안대로 바탕이 #F9FAFC(--gray-50) 다.
                       1920 에서는 세 칸이 253 에 딱 맞고, 그보다 좁으면 우편번호 72 와 '주소 검색' 73 이
                       줄지 않아 카드를 넘긴다(입력영역 147 기준 14 초과). 줄바꿈을 허용해
                       모자랄 때만 '주소 검색' 이 아랫줄로 내려가게 했다. --}}
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;row-gap:8px;">
                    <input type="text" class="form-control" id="f-postcode" readonly value="{{ $prescription->postcode ?? '' }}"
                           placeholder="우편번호" style="flex:0 0 72px;min-width:0;background:var(--gray-50);cursor:default;" />
                    <input type="text" class="form-control" id="f-address"
                           value="{{ $prescription->address_ocr ?? $prescription->patient?->address ?? '' }}"
                           placeholder="도로명 주소" readonly style="flex:1;min-width:0;background:var(--gray-50);cursor:default;" />
                    <button type="button" class="rx-side-btn" onclick="openAddressSearch('f-postcode','f-address','f-address-detail')">주소 검색</button>
                  </div>
                  {{-- 2줄 — 시안 315:58 Frame 48101497: [상세 주소 149 FILL][배송 주소 동일 96 HUG], 사이 8.
                       '배송 주소 동일' 묶음은 96 에서 줄지 않으므로, 입력영역이 좁아지면 이 줄이
                       열 밖으로 12px 삐져나왔다(2열 1280 기준 실측). 1줄과 같이 줄바꿈을 허용해
                       모자랄 때만 체크 묶음이 아랫줄로 내려가게 한다. --}}
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;row-gap:8px;">
                    <input type="text" class="form-control" id="f-address-detail"
                           value="{{ $prescription->address_detail ?? '' }}"
                           placeholder="상세 주소" style="flex:1;min-width:0;" />
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;line-height:21px;color:var(--primary);white-space:nowrap;cursor:pointer;margin:0;flex-shrink:0;">
                      <input type="checkbox" id="sameShipping" checked onchange="syncShippingAddress(this.checked)" style="width:16px;height:16px;cursor:pointer;" />
                      배송 주소 동일
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <div class="rx-col">
              {{-- 2열 — 일일 도뇨 횟수 · Five(110days) · 현금영수증 · 보호자명 · Email · 건보 등록
                   (시안 315:58 Frame 48101514) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">일일 도뇨 횟수</span>
                <select class="form-control" id="f-diverticulums" style="flex:1;">
                  <option value="">선택</option>
                  <option value="01" @selected(($prescription->diverticulums ?? '') == '01')>1회 미만</option>
                  <option value="02" @selected(($prescription->diverticulums ?? '') == '02')>1~2회</option>
                  <option value="03" @selected(($prescription->diverticulums ?? '') == '03')>3회 ~ 4회</option>
                  <option value="04" @selected(($prescription->diverticulums ?? '') == '04')>5회</option>
                  <option value="05" @selected(($prescription->diverticulums ?? '') == '05')>6회 이상</option>
                  <option value="06" @selected(($prescription->diverticulums ?? '') == '06')>N/A</option>
                </select>
              </div>
              {{-- 아래 둘은 시안대로 급여·보험 / 거래·주문 구획에서 옮겨 왔다 --}}
              <div class="rx-field-row">
                <span class="rx-field-label">Five(110days)</span>
                <input type="text" class="form-control" id="f-five" value="{{ $prescription->five_110days ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">현금영수증</span>
                {{-- 시안 315:58 Frame 48101500 — 이 줄은 두 칸이다: [소득공제 123][현금영수증 번호 123], 사이 8 (= 253).
                     소득공제 select 은 병원ㆍ처방 아코디언 오른쪽 열에 따로 서 있던 것을
                     통째로 옮겨 왔다(id·option·값 그대로). 저장 때 deduction 과
                     cash_receipt_no 로 함께 넘어가던 짝이라 한 줄에 서는 것이 맞다.
                     시안은 라벨 칸을 하나만 주고 첫 칸이 무엇인지는 '선택된 값'으로 알린다.
                     값이 비면 무슨 칸인지 알 수 없으므로, 빈 선택지 문구를 '소득공제 선택'으로 둔다 —
                     이러면 '소득공제' 라는 말이 화면에서 사라지지 않는다. value 는 그대로 빈 값이다. --}}
                <div style="display:flex;gap:8px;flex:1;min-width:0;">
                  <select class="form-control" id="f-deduction" title="소득공제" style="flex:1;min-width:0;">
                    <option value="">소득공제 선택</option>
                    <option value="소득공제" @selected(($prescription->patient?->deduction ?? '') == '소득공제')>소득공제</option>
                    <option value="지출증빙" @selected(($prescription->patient?->deduction ?? '') == '지출증빙')>지출증빙</option>
                    <option value="자진발급" @selected(($prescription->patient?->deduction ?? '') == '자진발급')>자진발급</option>
                  </select>
                  <input type="text" class="form-control" id="f-cash-receipt" title="현금영수증 번호" value="{{ $prescription->patient?->cash_receipt_no ?? '' }}" placeholder="010-XXX-XXXX" style="flex:1;min-width:0;" />
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">보호자명</span>
                <input type="text" class="form-control" id="f-guardian" value="{{ $prescription->caregiver_name ?? '' }}" placeholder="보호자명" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">Email</span>
                <input type="email" class="form-control" id="f-email"
                       value="{{ $prescription->patient?->email ?? '' }}" placeholder="name@example.com" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">공단 등록</span>
                <select class="form-control" id="f-nhis-status" style="flex:1;">
                  <option value="">선택</option>
                  <option value="진행중"   @selected(($prescription->patient?->nhis_reg_status ?? '') == '진행중')>진행중</option>
                  <option value="완료"     @selected(($prescription->patient?->nhis_reg_status ?? '') == '완료')>완료</option>
                  <option value="필요없음" @selected(($prescription->patient?->nhis_reg_status ?? '') == '필요없음')>필요없음</option>
                </select>
              </div>
            </div>

            <div class="rx-col">
              {{-- 3열 — 건보 등록일 · 건보 재등록 대상자/기한 · 기초(의료급여) 재평가 대상자/기한
                   (시안 315:58 Frame 48101515) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">공단 등록일</span>
                <input type="date" class="form-control" id="f-nhis-reg-date"
                       value="{{ $prescription->patient?->nhis_reg_date ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">공단 재등록 대상자</span>
                <input type="text" class="form-control" id="f-nhis-renew" value="{{ $prescription->patient?->nhis_renew ?? '' }}" placeholder="날짜 또는 비고" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">공단 재등록 기한</span>
                <input type="date" class="form-control" id="f-nhis-renew-due"
                       value="{{ $prescription->patient?->nhis_renew_due ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">기초(의료급여)<br>재평가 대상자</span>
                <input type="text" class="form-control" id="f-basic-reeval"
                       value="{{ $prescription->patient?->basic_reeval ?? '' }}" placeholder="대상 여부 또는 비고" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">기초(의료급여)<br>재평가 기한</span>
                <input type="date" class="form-control" id="f-basic-reeval-due"
                       value="{{ $prescription->patient?->basic_reeval_due ?? '' }}" style="flex:1;" />
              </div>
            </div>
            </div>{{-- /rx-cols --}}

          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 3: 병원 · 처방 정보 (기본 펼침, OCR)
        ───────────────────────────────────────────────── --}}
                {{-- ── 병원ㆍ처방 정보 (시안 148:2827) ──
             병원·처방 / 처방수량·상병 / 급여·보험 / 신재구매 / 추가정보 다섯 카드를
             시안대로 하나로 합쳤다. 항목은 2열로 나눈다. --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-hospital" style="color:var(--primary);"></i> 병원ㆍ처방 정보
            </span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">병원 처방 정보ㆍ처방수량 상병ㆍ급여 보험 정보ㆍ신/재구매 정보</span>
              {{-- 헤더를 눌러 접히지 않게 클릭을 여기서 멈춘다 --}}
              <div class="rx-acc-btns" onclick="event.stopPropagation()">
                <button type="button" class="rx-acc-btn" onclick="resetOCR()" title="입력값을 원본 OCR 결과로 되돌립니다">원본 복원</button>
                <button type="button" class="rx-acc-btn" onclick="approveRx()" title="검수 완료 후 승인을 요청합니다">승인 요청</button>
                <button type="button" class="rx-acc-btn rx-acc-btn-fill" onclick="saveOCR()" title="검수 내용을 저장합니다">저장</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            @php
              // 합치기 전에는 급여·보험 구획 안에 있던 정의다. 아래 입력과
              // 테이블뷰가 함께 쓰므로 본문 맨 앞으로 옮겼다.
              $agreeStart = ($prescription->patient?->nhis_agree_start ?? null) ?: now()->format('Y-m-d');
              $agreeEnd   = ($prescription->patient?->nhis_agree_end ?? null) ?: \Carbon\Carbon::parse($agreeStart)->addMonth()->format('Y-m-d');
            @endphp
            <div class="rx-cols">
            <div class="rx-col">
              {{-- 1열 — 요양병원 코드 … 1일 처방 개수 10줄 (시안 315:58 Frame 48101490, 361×392) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">요양병원 코드</span>
                {{-- 값은 제 컬럼에서 읽는다. 상담 JSON 에서 꺼내 각자 컬럼에 담았다. --}}
                <input type="text" class="form-control" id="f-hospital-code" value="{{ $prescription->hospital_code ?? '' }}" placeholder="요양병원 코드" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">병원명 <span style="color:var(--primary);">*</span></span>
                <div class="field-group" style="flex:1;">
                  <input type="text" class="form-control has-ok" id="f-hospital" value="{{ $prescription->hospital_name }}" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--primary);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">판매 거래처 유형</span>
                <input type="text" class="form-control" id="f-dealer-type" value="{{ $prescription->dealer_type ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">추가정보 등록일</span>
                <input type="text" class="form-control" id="f-add-reg-date" value="{{ $prescription->created_at?->format('Y-m-d') ?? '' }}" readonly style="flex:1;background:var(--bg-secondary,var(--gray-50));" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">진단 확인일</span>
                <input type="date" class="form-control" id="f-diagnosis-date" value="{{ $prescription->diagnosis_date ?? '' }}" style="flex:1;min-width:0;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">상병 구분</span>
                <select class="form-control" id="f-disease-class" style="flex:1;">
                  <option value="">선택</option>
                  <option value="1"   @selected(($prescription->disease_class ?? '') == '1')>1</option>
                  <option value="2-1" @selected(($prescription->disease_class ?? '') == '2-1')>2-1</option>
                  <option value="2-2" @selected(($prescription->disease_class ?? '') == '2-2')>2-2</option>
                  <option value="3"   @selected(($prescription->disease_class ?? '') == '3')>3</option>
                </select>
              </div>
              {{-- 시안 315:58 Frame 48101493 은 253 한 칸이고(값 'N31.8, R30.0, N30.8, K21.0')
                   위아래 간격이 다른 줄과 같은 8 이다. 한 칸으로 합치면 '상병명' 입력이 사라지므로
                   개발이 넣은 두 칸을 그대로 둔다. 두 칸 사이는 8 로 통일했다. --}}
              <div class="rx-field-row">
                <span class="rx-field-label">상병코드</span>
                <div style="display:flex;gap:8px;flex:1;min-width:0;">
                  <input type="text" class="form-control" id="f-disease" value="{{ $prescription->disease_name }}" placeholder="상병명" style="flex:2;min-width:0;" />
                  <input type="text" class="form-control" id="f-disease-code" value="{{ $prescription->disease_code ?? $prescription->disease_code ?? '' }}" placeholder="코드" style="flex:3;min-width:0;" />
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">구분(SB/SCI)</span>
                <select class="form-control" id="f-sb-sci" style="flex:1;">
                  <option value="">선택</option>
                  <option value="SB"  @selected(($prescription->patient?->sb_sci ?? '') == 'SB')>SB</option>
                  <option value="SCI" @selected(($prescription->patient?->sb_sci ?? '') == 'SCI')>SCI</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">요류역학검사일</span>
                <input type="date" class="form-control" id="f-uro-date" value="{{ $prescription->uro_date ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">1일 처방 개수</span>
                <input type="number" class="form-control" id="f-daily" value="{{ $prescription->daily_count ?? $prescription->daily_count ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
            </div>
            <div class="rx-col">
              {{-- 2열 — 총 처방 기간 … Five/Six program 10줄 (시안 315:58 Frame 48101492, 361×392) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">총 처방 기간</span>
                <input type="number" class="form-control" id="f-days" value="{{ $prescription->total_days ?? $prescription->total_days ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">총계</span>
                <input type="number" class="form-control" id="f-total" value="{{ $prescription->total_count ?? $prescription->total_count ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">처방전 발행일</span>
                <div class="field-group" style="flex:1;">
                  <input type="date" class="form-control has-ok" id="f-date" value="{{ $prescription->issued_date?->format('Y-m-d') ?? $prescription->issued_date?->format('Y-m-d') ?? '' }}" style="min-width:0;" onchange="calcNextRepurchase()" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--primary);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">처방전 사용 기간 (교부일로부터)</span>
                {{-- 시안은 입력영역 안 부속 사이를 전부 8 로 둔다(상담 번호·상담 일자·주소·재구매일 모두 8).
                     '일' 글자는 시안에 없지만 단위 안내라 남기고 간격만 4 → 8 로 맞췄다.
                     값은 제 컬럼에서 읽는다. --}}
                <div style="display:flex;align-items:center;gap:8px;flex:1;">
                  <input type="number" class="form-control" id="f-rx-period" value="{{ $prescription->total_days ?? $prescription->rx_use_period ?? '' }}" placeholder="일수" style="flex:1;min-width:0;" onchange="calcNextRepurchase()" />
                  <span style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-600);white-space:nowrap;">일</span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">담당 의사명</span>
                <input type="text" class="form-control" id="f-doctor" value="{{ $prescription->doctor_name ?? $prescription->doctor_name ?? '' }}" placeholder="의사 성명" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">보험 유형</span>
                <select class="form-control" id="f-benefit-class" style="flex:1;">
                  <option value="">선택</option>
                  <option value="일반"      @selected(($prescription->benefit_class ?? '') == '일반')>일반</option>
                  <option value="차상위경감" @selected(($prescription->benefit_class ?? '') == '차상위경감')>차상위경감</option>
                  <option value="기초"      @selected(($prescription->benefit_class ?? '') == '기초')>기초</option>
                  <option value="자동차보험" @selected(($prescription->benefit_class ?? '') == '자동차보험')>자동차보험</option>
                  <option value="산재"      @selected(($prescription->benefit_class ?? '') == '산재')>산재</option>
                </select>
              </div>
              {{-- 청구처 — 공단이냐 지자체냐에 따라 이후 절차가 통째로 갈린다.
                   급여구분을 고르면 따라오되, 확정은 담당자가 한다. --}}
              <div class="rx-field-row">
                <span class="rx-field-label">청구처</span>
                <select class="form-control" id="f-claim-agency" style="flex:1;">
                  <option value="">선택</option>
                  @foreach(\App\Support\ClaimAgency::LABELS as $v => $label)
                    <option value="{{ $v }}" @selected(($prescription->claim_agency ?? '') === $v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="rx-field-row" id="row-local-gov" style="{{ ($prescription->claim_agency ?? '') === \App\Support\ClaimAgency::LOCAL ? '' : 'display:none;' }}">
                <span class="rx-field-label">관할 지자체</span>
                <input type="text" class="form-control" id="f-local-gov"
                       value="{{ $prescription->local_gov ?? '' }}"
                       placeholder="예: 서울특별시 강남구" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">신구매/재구매</span>
                <select class="form-control" id="f-purchase-type" style="flex:1;">
                  <option value="">선택</option>
                  <option value="신구매" @selected(($prescription->purchase_type ?? '') == '신구매')>신구매</option>
                  <option value="재구매" @selected(($prescription->purchase_type ?? '') == '재구매')>재구매</option>
                </select>
              </div>
              <div class="rx-field-row">
              <span class="rx-field-label">사유</span>
              <select class="form-control" id="f-reason" style="flex:1;">
                  <option value="">선택</option>
                  @foreach([
                    '진행중-재구매일자대기','진행중-샘플진행중','진행중-재고여유','진행중-입원중','진행중-통화연결실패',
                    '진행중-미입금','진행중-유치도뇨','진행중-입금대기 또는 대리점 판매확정 미진행 예상',
                    '진행중-대리점이 사유 확인중','진행중-출국','진행중-보류요청','진행중-환자정보 요청중',
                    '진행중-대리점 출고 대기','진행중-이질감','진행중-공단 등록 진행중',
                    '취소-타사제품','취소-재고여유','취소-복원','취소-입원중','취소-산재',
                    '취소-보훈(급여적용불가)','취소-통화연결실패','취소-이질감','취소-처방전  error(이중발행 등)',
                    '취소-미입금','취소-비용부담','취소-단순변심','취소-유치도뇨','취소-처방전 사용기간만료',
                    '취소-CKL제품 의료기구매','취소-출국','취소-사망',
                    '관리자 확인 -시스템 issue(판매 주문부터 시작/확정)',
                    '재고부족으로 발송지연','카카오구매-요양병원',
                  ] as $reason)
                  <option value="{{ $reason }}" @selected(($prescription->reason ?? '') == $reason)>{{ $reason }}</option>
                  @endforeach
                </select>
            </div>
              <div class="rx-field-row">
                <span class="rx-field-label">주문 담당자</span>
                <input type="text" class="form-control" id="f-order-manager" value="{{ ($prescription->order_manager ?? null) ?: auth()->user()->name }}" placeholder="담당자" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">Five/Six program</span>
                <select class="form-control" id="f-five-program" style="flex:1;">
                  <option value="">선택</option>
                  <option value="00" @selected(($prescription->five_program ?? '') == '00')>N/A</option>
                  <option value="05" @selected(($prescription->five_program ?? '') == '05')>Five</option>
                  <option value="06" @selected(($prescription->five_program ?? '') == '06')>Six</option>
                </select>
              </div>
            </div>
            <div class="rx-col">
              {{-- 3열 — Five(110days) … 재구매일 9줄 (시안 315:58 Frame 48101491, 361×392).
                   종료일·신환master등록일은 시안에 없지만 개발이 넣은 입력이라 끝에 이어 남긴다. --}}
              <div class="rx-field-row">
                <span class="rx-field-label">Five(110days)</span>
                <input type="text" class="form-control" id="f-five-2" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">환급 해당 기관</span>
                <select class="form-control" id="f-special-case" style="flex:1;">
                  <option value="">선택</option>
                  <option value="입원" @selected(($prescription->special_case ?? '') == '입원')>입원</option>
                  <option value="산재" @selected(($prescription->special_case ?? '') == '산재')>산재</option>
                  <option value="보훈" @selected(($prescription->special_case ?? '') == '보훈')>보훈</option>
                  <option value="출국" @selected(($prescription->special_case ?? '') == '출국')>출국</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">결제일</span>
                <input type="date" class="form-control" id="f-pay-date" value="{{ $prescription->pay_date ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">구입일 (모든 서류 발행일)</span>
                <input type="date" class="form-control" id="f-buy-date" value="{{ $prescription->buy_date ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">사용 시작일 (사용 개시일)</span>
                <input type="date" class="form-control" id="f-nhis-agree-start"
                       value="{{ $agreeStart }}"
                       onchange="autoAgreeEnd(this.value)"
                       style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">급여 종료일 (사용 종료일)</span>
                <input type="date" class="form-control" id="f-nhis-agree-end"
                       value="{{ $agreeEnd }}"
                       style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">다음 재구매 가능일</span>
                {{-- 부속 간격은 시안대로 8. '자동' 버튼은 시안에 없지만 계산 버튼이라 남기고,
                     글자만 같은 자리의 .rx-side-btn 과 같은 13/500 으로 맞췄다(11/500 은 규격에 없다).
                     min-width:0 이 없으면 이 묶음이 '자동' 버튼 폭(68) 아래로 줄지 못해
                     3열 1600 에서 열 밖으로 68px, 2열 1280 에서 92px 삐져나갔다(실측).
                     값은 제 컬럼에서 읽는다. --}}
                <div style="display:flex;gap:8px;flex:1;min-width:0;align-items:center;">
                  <input type="date" class="form-control" id="f-next-repurchase" value="{{ $prescription->next_repurchase ?? '' }}" style="flex:1;min-width:0;" />
                  <button type="button" onclick="calcNextRepurchase(true)"
                          title="처방전발행일 + 처방기간(일) + 1일"
                          style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border:1px solid var(--primary);border-radius:8px;background:var(--primary-light);color:var(--primary);font-size:13px;font-weight:500;line-height:20px;cursor:pointer;white-space:nowrap;">
                    <i class="fa-solid fa-rotate"></i> 자동
                  </button>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">진단확인일</span>
                <input type="date" class="form-control" id="f-diag-confirm-2" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">재구매일</span>
                {{-- 시안 315:58 Frame 48101499 는 두 칸이다:
                     [발행일 100 FIXED · bg #F9FAFC][arrow-right-sm 14][재구매일 123 FILL], 사이 8 (= 253).
                     왼쪽 칸은 calcRenewDate() 가 이미 글자를 채워 두던 #disp-issued-date 를
                     그대로 쓴다 — display:none 만 풀고 상자 모양을 입혔다(새 로직 없음).
                     오른쪽은 저장되는 입력(#f-repurchase-date)이고 자동 계산이라 읽기 전용이다.
                     #disp-renew-date 는 테이블뷰가 글자로 읽어가므로 감춘 채로 남긴다. --}}
                <div class="rx-date-flow">
                  <span id="disp-issued-date" class="rx-date-shown">{{ $prescription->issued_date?->format('Y-m-d') ?? '-' }}</span>
                  <i class="fa-solid fa-arrow-right rx-date-arrow" aria-hidden="true"></i>
                  <input type="text" class="form-control" id="f-repurchase-date" readonly
                         value="{{ $prescription->repurchase_date?->format('Y-m-d') ?? '' }}"
                         placeholder="처방전 발행일과 처방 기간으로 자동 계산됩니다"
                         style="flex:1;min-width:0;background:var(--gray-50);cursor:default;" />
                </div>
                <span id="disp-renew-date" style="display:none;">{{ $prescription->repurchase_date?->format('Y-m-d') ?? '-' }}</span>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">종료일</span>
                <input type="date" class="form-control" id="f-rx-end-date" value="{{ $prescription->rx_end_date ?? '' }}" style="flex:1;min-width:0;" />
              </div>
              {{-- '소득공제' 줄은 환자 정보 구획의 '현금영수증' 줄로 옮겼다
                   (시안 315:58 Frame 48101500 이 두 값을 한 줄에 둔다). --}}
              <div class="rx-field-row">
                <span class="rx-field-label">신환master등록일</span>
                <input type="date" class="form-control" id="f-new-patient-date" value="{{ $prescription->patient?->new_patient_date ?? '' }}" style="flex:1;" />
              </div>
            </div>
            </div>{{-- /rx-cols --}}
          </div>
        </div>

        {{-- ── 추가정보 (시안 148:3046) ── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-ellipsis" style="color:var(--primary);"></i> 추가정보
            </span>
            <div class="rx-acc-meta">
              {{-- 화면에서 NHIS·건보 표현은 걷어냈다 — 「공단」으로 적는다 --}}
              <span class="rx-acc-meta-hint">공단 위임동의ㆍ인마켓 마감일ㆍ수량</span>
              {{-- 헤더를 눌러 접히지 않게 클릭을 여기서 멈춘다 --}}
              <div class="rx-acc-btns" onclick="event.stopPropagation()">
                <button type="button" class="rx-acc-btn" onclick="resetOCR()" title="입력값을 원본 OCR 결과로 되돌립니다">원본 복원</button>
                <button type="button" class="rx-acc-btn" onclick="approveRx()" title="검수 완료 후 승인을 요청합니다">승인 요청</button>
                <button type="button" class="rx-acc-btn rx-acc-btn-fill" onclick="saveOCR()" title="검수 내용을 저장합니다">저장</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            <div class="rx-cols">
            <div class="rx-col">
              {{-- 위 둘은 병원ㆍ처방 카드의 '사용 시작일'·'급여 종료일'과 같은 값이다.
                   시안이 양쪽에 그려 두어 서로 비추게 한다(아래 초기화 코드 참조). --}}
              <div class="rx-field-row">
                <span class="rx-field-label">공단 위임동의 시작일</span>
                <input type="date" class="form-control" id="f-agree-start-2" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">공단 위임동의 종료일</span>
                <input type="date" class="form-control" id="f-agree-end-2" style="flex:1;" />
              </div>

            </div>
            <div class="rx-col">
              {{-- 2열 — 하루 사용 수량 · 마지막 확정 수량 (시안 315:58 Frame 48101492) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">하루 사용 수량</span>
                {{-- 값은 제 컬럼에서 읽는다 --}}
                <input type="number" min="0" class="form-control" id="f-daily-use-qty"
                       value="{{ $prescription->daily_use_qty ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">마지막 확정 수량</span>
                <input type="number" min="0" class="form-control" id="f-last-qty"
                       value="{{ $prescription->last_confirmed_qty ?? '' }}" style="flex:1;" />
              </div>
            </div>
            <div class="rx-col">
              {{-- 3열 — 인마켓 마감일 (시안 315:58 Frame 48101491) --}}
              <div class="rx-field-row">
                <span class="rx-field-label">인마켓 마감일</span>
                {{-- 값은 제 컬럼에서 읽는다 --}}
                <input type="date" class="form-control" id="f-inmarket-due"
                       value="{{ $prescription->inmarket_due ?? '' }}" style="flex:1;" />
              </div>
            </div>
            </div>{{-- /rx-cols --}}
          </div>
        </div>

      </div>{{-- /cv --}}

      {{-- ── 테이블뷰 ── --}}
      <div class="tv">
        <table class="tab-tbl">
          <tbody>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-clipboard-list"></i> 상담 정보</td></tr>
            <tr>
              <th>상담번호</th><td data-from="f-counselling-no">{{ $curCounselNo ?: '-' }}</td>
              <th>상담일자</th><td data-from="f-counsel-date">{{ $curCounselDate ?: '-' }}</td>
            </tr>
            <tr>
              <th>상담유형</th><td data-from="f-counsel-type">-</td>
              <th>처방전여부</th><td data-from="f-acc-add-type">-</td>
            </tr>
            <tr>
              <th>상담상태</th><td data-from="f-counsel-status">-</td>
              <th>재상담일자</th><td data-from="f-re-counsel-date">{{ $prescription->counsel_re_date ?? '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-user"></i> 환자 정보</td></tr>
            <tr>
              <th>환자명</th><td data-from="f-name">{{ $prescription->patient_name_ocr ?: '-' }}</td>
              <th>연락처</th><td data-from="f-mobile">{{ $prescription->mobile_ocr ?? $prescription->patient?->mobile ?? '-' }}</td>
            </tr>
            <tr>
              <th>보호자명</th><td data-from="f-guardian">{{ $prescription->caregiver_name ?? '-' }}</td>
              <th>일일도뇨횟수</th><td data-from="f-diverticulums">-</td>
            </tr>
            <tr>
              <th>주소</th>
              <td colspan="3" id="tv-address">@php $fullAddrTv = trim(($prescription->address_ocr ?? $prescription->patient?->address ?? '') . ' ' . ($prescription->address_detail ?? '')); @endphp{{ $fullAddrTv ?: '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-hospital"></i> 병원 · 처방 정보</td></tr>
            <tr>
              <th>병원명</th><td data-from="f-hospital">{{ $prescription->hospital_name ?: '-' }}</td>
              <th>요양병원코드</th><td data-from="f-hospital-code">{{ $prescription->hospital_code ?? '-' }}</td>
            </tr>
            <tr>
              <th>담당의사</th><td data-from="f-doctor">{{ $prescription->doctor_name ?? $prescription->doctor_name ?? '-' }}</td>
              <th>처방전발행일</th><td data-from="f-date">{{ $prescription->issued_date?->format('Y-m-d') ?? '-' }}</td>
            </tr>
            <tr>
              <th>처방기간</th><td data-from="f-rx-period">{{ ($prescription->total_days ?? '-') }}</td>
              <th>재구매일</th><td id="tv-renew-date">{{ $prescription->repurchase_date?->format('Y-m-d') ?? '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-clipboard-list"></i> 처방 수량 · 상병</td></tr>
            <tr>
              <th>상병명</th><td data-from="f-disease">{{ $prescription->disease_name ?: '-' }}</td>
              <th>상병코드</th><td data-from="f-disease-code">{{ $prescription->disease_code ?? $prescription->disease_code ?? '-' }}</td>
            </tr>
            <tr>
              <th>상병구분</th><td data-from="f-disease-class">-</td>
              <th>SB/SCI</th><td data-from="f-sb-sci">-</td>
            </tr>
            <tr>
              <th>1일처방개수</th><td data-from="f-daily">{{ $prescription->daily_count ?? '-' }}</td>
              <th>총처방기간</th><td data-from="f-days">{{ $prescription->total_days ?? '-' }}일</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-shield-halved"></i> 급여 · 보험 정보</td></tr>
            <tr>
              <th>급여구분</th><td data-from="f-benefit-class">-</td>
              <th>공단등록상태</th><td data-from="f-nhis-status">-</td>
            </tr>
            <tr>
              <th>위임동의 시작일</th><td data-from="f-nhis-agree-start">{{ $agreeStart ?? '-' }}</td>
              <th>위임동의 종료일</th><td data-from="f-nhis-agree-end">{{ $agreeEnd ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>{{-- /tv --}}

      </div>{{-- /tab-ocr --}}

      {{-- ── 미저장 경고 다이얼로그 ── --}}
      <div id="unsavedDlg" style="display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:380px;margin:16px;box-shadow:0 24px 64px rgba(0,0,0,.22);animation:modalIn .18s ease;overflow:hidden;">
          <div style="padding:24px 24px 0;display:flex;gap:14px;align-items:flex-start;">
            <div style="width:40px;height:40px;border-radius:12px;background:var(--alert-50);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-triangle-exclamation" style="color:var(--alert-500);font-size:18px;"></i>
            </div>
            <div>
              <p style="font-size:14px;font-weight:700;color:var(--gray-1000);margin:0 0 6px;">저장하지 않은 변경사항</p>
              <p style="font-size:13px;color:var(--gray-600);line-height:1.6;margin:0;">처방전 검수 탭에 저장되지 않은 내용이 있습니다.<br>탭을 이동하기 전에 저장하시겠습니까?</p>
            </div>
          </div>
          <div style="padding:20px 24px 24px;display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
            <button id="unsavedDlgCancel" style="padding:8px 16px;border:1px solid var(--gray-200);border-radius:8px;background:#fff;color:var(--gray-800);font-size:13px;font-weight:500;cursor:pointer;">취소</button>
            <button id="unsavedDlgDiscard" style="padding:8px 16px;border:1px solid var(--gray-200);border-radius:8px;background:#fff;color:var(--gray-600);font-size:13px;font-weight:500;cursor:pointer;">저장 없이 이동</button>
            <button id="unsavedDlgSave" style="padding:8px 16px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-floppy-disk"></i> 저장 후 이동</button>
          </div>
        </div>
      </div>

      {{-- Tab: Product (처방전 검수 탭에서는 검수 영역 아래에 함께 표시) --}}
      <div class="tab-pane" id="tab-product">

        {{-- 판매 유형 선택 (카드/테이블뷰 공통) --}}
        {{-- 카드 간격은 시안 12 다. 부트스트랩 CDN 의 .mb-3 은 16px 이고 !important 라
             전역에서 못 이긴다 — 클래스를 떼고 인라인으로 12 를 준다. --}}
        {{-- 시안 Frame 48101493: 카드 테두리는 다른 카드와 같은 #E8EAEC 다(주색 강조 아님) --}}
        <div class="card" style="margin-bottom:12px;">
          {{-- 머리띠 — h44 · pad 8/16 · 아래 1px --}}
          <div class="pt-card-head">
            <div class="pt-head-left">
              <span class="pt-card-title"><i class="fa-solid fa-tag"></i> 판매 유형</span>
            </div>
            <div class="pt-head-right">
              {{-- 시안에는 값이 박혀 있었다. 지금 골라진 유형을 보여야 한다 —
                   보이는 것과 보내는 것이 갈리면 안 된다.
                   $soCur 는 아래 라디오 블록에서 정하므로 여기서 먼저 셈해 둔다. --}}
              @php
                $soCur = $prescription->order?->so_type;
                if (!in_array($soCur, \App\Models\Order::saleSoTypes(), true)) {
                    $soCur = \App\Models\Order::saleSoTypes()[0];
                }
              @endphp
              <div id="soTypeBadge">
                <span class="badge badge-primary" style="font-size:11px;">{{ $soCur }} · {{ \App\Models\Order::SO_TYPE_LABELS[$soCur][0] ?? $soCur }}</span>
              </div>
            </div>
          </div>
          <div class="card-body" style="padding:12px 16px;">
            <div class="pt-field-row">
              {{-- 시안 "판매 유형 *" 100×32 · 13/500 · #474D54 — 별표도 라벨과 같은 색이다 --}}
              <span class="pt-field-label">판매 유형 *</span>
              <div class="pt-radio-group">
                @php
                  $soIcons = ['1013' => 'fa-hospital', '1016' => 'fa-user',
                              '1022' => 'fa-gift',     '5001' => 'fa-truck-fast'];
                  /* 저장된 값이 지금 고를 수 있는 목록에 없으면(옛 1013 등) 첫 번째로
                     떨어뜨린다. 그러지 않으면 아무것도 선택되지 않은 채로 열린다. */
                  $soCur = $prescription->order?->so_type;
                  if (!in_array($soCur, \App\Models\Order::saleSoTypes(), true)) {
                      $soCur = \App\Models\Order::saleSoTypes()[0];
                  }
                @endphp
                {{-- 반품 계열 유형(5004·5005·5006)은 여기 없다. 판매를 만들면서 고를 수 있게
                     두면 반품 유형으로 판매가 나간다. --}}
                @foreach(\App\Models\Order::saleSoTypes() as $code)
                @php $meta = \App\Models\Order::SO_TYPE_LABELS[$code]; @endphp
                <label class="so-type-opt">
                  <input type="radio" name="so_type_radio" value="{{ $code }}"
                         @checked($soCur === (string) $code) onchange="onSoTypeChange(this.value)">
                  <span><i class="fa-solid {{ $soIcons[$code] ?? 'fa-tag' }}"></i> {{ $meta[0] }}</span>
                </label>
                @endforeach
              </div>

            </div>
          </div>
        </div>

        <div class="card">
          {{-- 처방 제품 헤더 (카드/테이블뷰 공통) — 시안 Frame 48101494 의 머리띠 h44 --}}
          <div class="pt-card-head">
            <div class="pt-head-left">
              <span class="pt-card-title"><i class="fa-solid fa-boxes-stacked"></i> 처방 제품 정보</span>
              {{-- 낱개 알약 4개 — 시안 Frame 48101522: h22 · r999 · pad 2/8 · gap 6 · 11/500.
                   시안에 없는 구분자 '|' 와 테두리를 빼고 알약을 넷으로 나눴다 --}}
              <span class="pt-head-badges">
                <span class="pt-hb"><b id="rx-ref-name">{{ $prescription->patient_name_ocr ?? '-' }}</b></span>
                <span class="pt-hb">1일 <b id="rx-ref-daily">{{ $prescription->daily_count ?? '-' }}</b>개</span>
                <span class="pt-hb">처방 <b id="rx-ref-days">{{ $prescription->total_days ?? '-' }}</b>일</span>
                <span class="pt-hb">총 <b id="rx-ref-total">{{ $prescription->total_count ?? '-' }}</b>개</span>
              </span>
            </div>
            <div class="pt-head-right">
              {{-- 합계 — 시안은 카드 아래 띠가 아니라 머리 오른쪽 12/500 맨글자다.
                   '총 환자부담' 은 시안 이 카드에 없지만 지우지 않고 같은 자리로 옮겼다. --}}
              <span class="pt-head-total"><i class="fa-solid fa-circle-dollar-to-slot"></i>
                총 급여: <b id="summary-nhis">₩ {{ number_format($calcNhis) }}</b>
              </span>
              <span class="pt-head-total">
                총 환자부담: <b id="summary-copay">₩ {{ number_format($calcCopay) }}</b>
              </span>
              {{-- 버튼 3개 — 시안 Frame 48101503: [원본 복원 69][제품 추가 69][저장 45 주색] h28 · r8 · pad 0/12 · 12/500.
                   원본 복원·저장은 검수 탭 아코디언 머리에 있던 resetOCR()·saveOCR() 를 그대로 쓴다
                   (둘 다 items 를 읽고 쓴다). 저장 버튼은 onclick 문자열이 정확히 'saveOCR()' 여야
                   saveOCR() 안 querySelectorAll('[onclick="saveOCR()"]') 이 로딩 상태를 함께 건다. --}}
              <div class="pt-head-btns">
                <button type="button" class="rx-acc-btn" onclick="resetOCR()" title="입력값을 원본 OCR 결과로 되돌립니다">원본 복원</button>
                <button type="button" class="rx-acc-btn" onclick="addItem()"><i class="fa-solid fa-plus"></i> 제품 추가</button>
                <button type="button" class="rx-acc-btn rx-acc-btn-fill" onclick="saveOCR()" title="검수 내용을 저장합니다">저장</button>
              </div>
            </div>
          </div>
          {{-- 본문 — 시안 Frame 48101511: pad 12/16 · 행 사이 12 --}}
          <div class="card-body" style="padding:12px 16px;">
            {{-- 카드뷰 --}}
            <div class="cv">
              <div id="items-container">{{-- JS renderItems() --}}</div>
            </div>

            {{-- 테이블뷰 --}}
            <div class="tv">
              <div id="items-table-container"><div style="color:var(--text-muted);font-size:12px;text-align:center;padding:8px 0;">제품 없음</div></div>
            </div>

          </div>
        </div>

      </div>{{-- /tab-product --}}

      {{-- Tab: Order --}}
      <div class="tab-pane" id="tab-order">
      <div class="cv">
        <div class="card">
          <div class="card-body">
            <div class="section-title"><i class="fa-solid fa-boxes-stacked" style="color:var(--primary);"></i> 처방 제품 요약</div>
            <div id="order-items-summary">{{-- JS renderOrderSummary() --}}</div>

            <div class="section-title" style="margin-top:20px;"><i class="fa-solid fa-receipt" style="color:var(--primary);"></i> 비용 내역</div>
            <div class="cost-row"><span>급여 청구 금액</span><span class="cost-val" id="costNhisAmt">₩ {{ number_format($calcNhis) }}</span></div>
            <div class="cost-row"><span>환자부담 (급여 적용 후)</span><span class="cost-val" id="costNhis">₩ {{ number_format($calcCopay) }}</span></div>
            <div class="cost-row"><span>배송비</span><span class="cost-val">₩ 3,000</span></div>
            <div class="cost-row total"><span>환자 부담 합계</span><span class="cost-val" id="costTotal">₩ {{ number_format($calcCopay + 3000) }}</span></div>

            <div style="margin-top:16px;">
              <label class="form-label">배송 정보</label>
              <div style="display:flex;flex-direction:column;gap:6px;">

                {{-- 받는 사람 --}}
                <div style="display:flex;align-items:center;gap:6px;">
                  <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;width:72px;flex-shrink:0;">
                    <i class="fa-solid fa-user" style="color:var(--primary);width:14px;"></i> 받는 사람
                  </span>
                  <input type="text" class="form-control" id="shippingRecipient"
                         placeholder="받는 사람 이름"
                         value="{{ $prescription->order?->shipping_recipient ?? ($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '') }}"
                         style="flex:1;" />
                </div>

                {{-- 우편번호 + 주소 검색 --}}
                <div style="display:flex;gap:6px;">
                  <input type="text" class="form-control" id="shippingPostcode" readonly
                         placeholder="우편번호" style="width:110px;background:var(--bg-secondary,var(--gray-50));cursor:default;" />
                  <button type="button" class="btn btn-outline btn-sm" onclick="openAddressSearch('shippingPostcode','shippingAddr','shippingAddrDetail')"
                          style="white-space:nowrap;flex-shrink:0;">
                    <i class="fa-solid fa-magnifying-glass"></i> 주소 검색
                  </button>
                  <button type="button" class="btn btn-sm" onclick="fillFromPrescriptionAddress()"
                          style="white-space:nowrap;flex-shrink:0;background:var(--primary-light);border:1px solid var(--primary);color:var(--primary);"
                          title="처방전 탭의 주소를 배송 주소로 가져옵니다">
                    <i class="fa-solid fa-file-import"></i> 처방전 주소 가져오기
                  </button>
                  <button type="button" class="btn btn-sm" onclick="clearShippingAddress()"
                          style="white-space:nowrap;flex-shrink:0;background:none;border:1px solid var(--border);color:var(--text-muted);" title="주소 지우기">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>

                {{-- 도로명 + 상세 --}}
                <div style="display:flex;gap:6px;">
                  <input type="text" class="form-control" id="shippingAddr"
                         placeholder="도로명 주소" readonly style="flex:1;background:var(--bg-secondary,var(--gray-50));cursor:default;" />
                  <input type="text" class="form-control" id="shippingAddrDetail"
                         placeholder="상세 주소" style="flex:1;" />
                </div>

              </div>
            </div>

            {{-- 주문 생성 / 수정·삭제 버튼 영역 --}}
            <div id="orderActionArea" style="margin-top:12px;">
              @if($prescription->order)
              {{-- 이미 주문 있음: 수정 + 삭제 --}}
              <div id="orderExistsInfo" style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:10px 14px;margin-bottom:10px;font-size:12px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:15px;"></i>
                <div>
                  <b style="color:var(--primary);">주문 생성 완료</b>
                  <span style="color:var(--text-muted);margin-left:8px;">{{ $prescription->order->order_number }}</span>
                  @if($prescription->order->withworks_so_no)
                    <span style="color:var(--primary);margin-left:6px;font-family:monospace;font-size:11px;">SO: {{ $prescription->order->withworks_so_no }}</span>
                  @endif
                </div>
              </div>
              <div style="display:flex;gap:8px;">
                <button class="btn btn-primary flex-1" id="btnUpdateOrder" onclick="updateOrder(event)">
                  <i class="fa-solid fa-pen-to-square"></i> 주문 수정
                </button>
                <button class="btn btn-danger" id="btnDeleteOrder" onclick="confirmDeleteOrder(event)"
                        style="flex-shrink:0;padding:0 18px;">
                  <i class="fa-solid fa-trash-can"></i> 삭제
                </button>
              </div>
              @else
              {{-- 주문 없음: 생성 버튼 --}}
              <button class="btn btn-primary w-full" id="btnCreateOrder" onclick="createOrder(event)">
                <i class="fa-solid fa-cart-plus"></i> 주문 생성 및 연계
              </button>
              @endif
            </div>
          </div>
        </div>
      </div>{{-- /cv --}}
      <div class="tv">
        <div class="card">
          <div class="card-body" style="padding:12px 16px;">
            <table class="tab-tbl" style="margin-bottom:12px;">
              <tbody>
                <tr class="tbl-sec"><td colspan="2"><i class="fa-solid fa-receipt"></i> 비용 내역</td></tr>
                <tr><th>급여 청구 금액</th><td id="tv-costNhisAmt">₩ {{ number_format($calcNhis) }}</td></tr>
                <tr><th>환자부담</th><td id="tv-costNhis">₩ {{ number_format($calcCopay) }}</td></tr>
                <tr><th>배송비</th><td>₩ 3,000</td></tr>
                <tr>
                  <th style="font-weight:700;color:var(--primary);">환자 부담 합계</th>
                  <td style="font-weight:700;color:var(--primary);" id="tv-costTotal">₩ {{ number_format($calcCopay + 3000) }}</td>
                </tr>
                <tr class="tbl-sec"><td colspan="2"><i class="fa-solid fa-truck"></i> 배송 정보</td></tr>
                <tr><th>받는 사람</th><td id="tv-ship-recipient">{{ $prescription->order?->shipping_recipient ?? ($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-') }}</td></tr>
                <tr><th>배송 주소</th><td id="tv-ship-addr">{{ trim(($prescription->order?->shipping_address ?? '') . ' ' . ($prescription->order?->shipping_address_detail ?? '')) ?: '-' }}</td></tr>
                @if($prescription->order)
                  <tr>
                    <th>주문번호</th>
                    <td><span style="color:var(--primary);font-weight:700;">{{ $prescription->order->order_number }}</span>
                    @if($prescription->order->withworks_so_no)<span style="color:var(--primary);font-family:monospace;margin-left:8px;font-size:11px;">SO: {{ $prescription->order->withworks_so_no }}</span>@endif</td>
                  </tr>
                @else
                  <tr><th>주문상태</th><td style="color:var(--text-muted);">주문 없음</td></tr>
                @endif
              </tbody>
            </table>
            <div id="order-items-summary-tv" style="margin-bottom:10px;"></div>
            @if($prescription->order)
              <div style="display:flex;gap:8px;">
                <button class="btn btn-primary flex-1" onclick="updateOrder(event)"><i class="fa-solid fa-pen-to-square"></i> 주문 수정</button>
                <button class="btn btn-danger" onclick="confirmDeleteOrder(event)" style="flex-shrink:0;padding:0 18px;"><i class="fa-solid fa-trash-can"></i> 삭제</button>
              </div>
            @else
              <button class="btn btn-primary w-full" onclick="createOrder(event)"><i class="fa-solid fa-cart-plus"></i> 주문 생성 및 연계</button>
            @endif
          </div>
        </div>
      </div>{{-- /tv --}}
      </div>{{-- /tab-order --}}

      {{-- Tab: History --}}
      <div class="tab-pane" id="tab-history">
      <div class="cv">
        <div class="card">
          <div class="card-body">
            <div class="workflow-step">
              <div class="ws-icon done"><i class="fa-solid fa-mobile-screen"></i></div>
              <div><div class="ws-label">모바일/웹 업로드</div><div class="ws-time">{{ $prescription->created_at->format('H:i') }} · {{ $prescription->upload_source === 'mobile' ? 'iOS 앱' : '웹' }}</div></div>
              <i class="fa-solid fa-check ws-arrow" style="color:var(--primary);"></i>
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']) ? 'done' : 'active' }}"><i class="fa-solid fa-eye"></i></div>
              <div><div class="ws-label">OCR 처리</div><div class="ws-time">{{ $prescription->updated_at->format('H:i') }} · 자동</div></div>
              @if(in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']))
                <i class="fa-solid fa-check ws-arrow" style="color:var(--primary);"></i>
              @else
                <i class="fa-solid fa-spinner fa-spin ws-arrow" style="color:var(--primary);"></i>
              @endif
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ in_array($prescription->status, ['approved','ordered']) ? 'done' : ($prescription->status === 'review_needed' ? 'active' : 'pending') }}"><i class="fa-solid fa-clipboard-check"></i></div>
              <div><div class="ws-label">검수 확인</div><div class="ws-time">{{ $prescription->reviewed_at ? $prescription->reviewed_at->format('H:i').' · '.$prescription->reviewer?->name : '대기 중' }}</div></div>
              @if(in_array($prescription->status, ['approved','ordered']))
                <i class="fa-solid fa-check ws-arrow" style="color:var(--primary);"></i>
              @endif
            </div>
            <div class="workflow-step" id="histOrderStep">
              <div class="ws-icon {{ $prescription->order ? 'done' : 'pending' }}" id="histOrderIcon"><i class="fa-solid fa-cart-shopping"></i></div>
              <div>
                <div class="ws-label">주문 생성</div>
                <div class="ws-time" id="histOrderTime">
                  @if($prescription->order)
                    {{ $prescription->order->order_number }}
                    @if($prescription->order->withworks_so_no)
                      <span style="color:var(--primary);font-family:monospace;display:block;">SO: {{ $prescription->order->withworks_so_no }}</span>
                    @endif
                  @else
                    대기 중
                  @endif
                </div>
              </div>
              @if($prescription->order)
                <i class="fa-solid fa-check ws-arrow" style="color:var(--primary);"></i>
              @endif
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ $prescription->order?->nhis_claim_status === 'approved' ? 'done' : 'pending' }}"><i class="fa-solid fa-hospital"></i></div>
              <div><div class="ws-label">청구</div><div class="ws-time">{{ $prescription->order?->nhis_reimbursement ? '환급: ₩'.number_format($prescription->order->nhis_reimbursement) : '대기 중' }}</div></div>
            </div>
          </div>
        </div>
      </div>{{-- /cv --}}
      <div class="tv">
        <div class="card">
          <div class="card-body" style="padding:12px 16px;">
            <table class="tab-tbl">
              <thead>
                <tr>
                  <th style="min-width:130px;">단계</th>
                  <th style="min-width:46px;text-align:center;">상태</th>
                  <th>시간 · 담당</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><i class="fa-solid fa-mobile-screen" style="color:var(--primary);margin-right:5px;"></i>모바일/웹 업로드</td>
                  <td style="text-align:center;"><i class="fa-solid fa-check" style="color:var(--primary);"></i></td>
                  <td>{{ $prescription->created_at->format('Y-m-d H:i') }} · {{ $prescription->upload_source === 'mobile' ? 'iOS 앱' : '웹' }}</td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-eye" style="color:var(--info);margin-right:5px;"></i>OCR 처리</td>
                  <td style="text-align:center;">
                    @if(in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']))
                      <i class="fa-solid fa-check" style="color:var(--primary);"></i>
                    @else
                      <i class="fa-solid fa-spinner fa-spin" style="color:var(--primary);"></i>
                    @endif
                  </td>
                  <td>{{ $prescription->updated_at->format('Y-m-d H:i') }} · 자동</td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-clipboard-check" style="color:var(--warning);margin-right:5px;"></i>검수 확인</td>
                  <td style="text-align:center;">
                    @if(in_array($prescription->status, ['approved','ordered']))
                      <i class="fa-solid fa-check" style="color:var(--primary);"></i>
                    @else
                      <i class="fa-solid fa-clock" style="color:var(--text-muted);"></i>
                    @endif
                  </td>
                  <td>{{ $prescription->reviewed_at ? $prescription->reviewed_at->format('Y-m-d H:i').' · '.($prescription->reviewer?->name ?? '-') : '대기 중' }}</td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-cart-shopping" style="color:var(--primary);margin-right:5px;"></i>주문 생성</td>
                  <td style="text-align:center;">
                    @if($prescription->order)
                      <i class="fa-solid fa-check" style="color:var(--primary);"></i>
                    @else
                      <i class="fa-solid fa-clock" style="color:var(--text-muted);"></i>
                    @endif
                  </td>
                  <td>
                    @if($prescription->order)
                      <span style="font-weight:700;">{{ $prescription->order->order_number }}</span>
                      @if($prescription->order->withworks_so_no)<span style="color:var(--primary);font-family:monospace;margin-left:6px;font-size:11px;">SO: {{ $prescription->order->withworks_so_no }}</span>@endif
                    @else대기 중@endif
                  </td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-hospital" style="color:var(--primary);margin-right:5px;"></i>청구</td>
                  <td style="text-align:center;">
                    @if($prescription->order?->nhis_claim_status === 'approved')
                      <i class="fa-solid fa-check" style="color:var(--primary);"></i>
                    @else
                      <i class="fa-solid fa-clock" style="color:var(--text-muted);"></i>
                    @endif
                  </td>
                  <td>{{ $prescription->order?->nhis_reimbursement ? '환급: ₩'.number_format($prescription->order->nhis_reimbursement) : '대기 중' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>{{-- /tv --}}
      </div>{{-- /tab-history --}}
    </div>

  </div>
  </div>{{-- /page-body-inner --}}

@endsection

@push('modals')
{{-- 이전 상담 이력 조회 모달 --}}
@if($isReturningPatient)
<div class="modal-overlay" id="prevCounselModal" style="z-index:10000;" onclick="if(event.target===this)closePrevCounselModal()">
  <div class="modal-box" style="width:800px;max-width:96vw;height:82vh;display:flex;flex-direction:column;">
    <div class="modal-header">
      <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);font-size:17px;"></i>
      <span class="modal-title">이전 상담 이력</span>
      <span style="font-size:11px;color:var(--text-muted);background:var(--gray-100);border:1px solid var(--gray-200);border-radius:4px;padding:1px 8px;margin-left:4px;">
        {{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-' }} · {{ $prevCounselings->count() }}건
      </span>
      <button class="modal-close" onclick="closePrevCounselModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">

      {{-- 왼쪽: 날짜/번호 목록 --}}
      <div style="width:230px;flex-shrink:0;border-right:1px solid var(--border);overflow-y:auto;background:var(--bg);">
        @php
          $pcStatusColorMap = ['02'=>'var(--info)','50'=>'var(--warning)','95'=>'var(--success)','99'=>'var(--danger)'];
          $pcStatusLabelMap = ['02'=>'등록','50'=>'재상담','95'=>'확정','99'=>'취소'];
        @endphp
        @foreach($prevCounselings as $i => $pc)
          @php
            $pcSt   = $pc->counsel_status ?? '';
            $pcDate = $pc->counsel_date ?: $pc->created_at->format('Y-m-d');
            $pcNo   = $pc->counsel_no ?: '-';
          @endphp
          <div class="pc-list-item" data-idx="{{ $i }}" onclick="selectPrevCounsel({{ $i }})"
               style="padding:11px 14px;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .15s;">
            <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:3px;word-break:break-all;">{{ $pcNo }}</div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
              <span style="font-size:11px;color:var(--text-muted);">
                <i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $pcDate }}
              </span>
              @if($pcSt)
                <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $pcStatusColorMap[$pcSt] ?? 'var(--gray-300)' }};color:#fff;flex-shrink:0;">
                  {{ $pcStatusLabelMap[$pcSt] ?? $pcSt }}
                </span>
              @endif
            </div>
          </div>
        @endforeach
      </div>

      {{-- 오른쪽: 상세 뷰 --}}
      <div id="prevCounselDetail" style="flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden;">
        {{-- sticky 헤더 --}}
        <div id="prevCounselStickyHeader" style="display:none;flex-shrink:0;padding:10px 18px;border-bottom:1px solid var(--border);background:var(--bg-card);">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div style="min-width:0;">
              <span id="pcStickyNo" style="font-size:13px;font-weight:700;color:var(--primary);"></span>
              <span id="pcStickyName" style="font-size:12px;color:var(--text-muted);margin-left:8px;"></span>
              <div id="pcStickyRx" style="font-size:10px;color:var(--text-muted);margin-top:2px;"></div>
            </div>
            <button id="pcStickyBtn"
                    style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border:1px solid var(--primary);border-radius:6px;color:var(--primary);font-size:11px;font-weight:500;cursor:pointer;background:var(--bg-card);white-space:nowrap;flex-shrink:0;">
              <i class="fa-solid fa-arrow-right"></i> 처방전 상세
            </button>
          </div>
        </div>
        {{-- 스크롤 바디 --}}
        <div id="prevCounselBody" style="flex:1;overflow-y:auto;padding:20px 22px;">
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);gap:10px;min-height:200px;">
            <i class="fa-solid fa-hand-pointer" style="font-size:28px;opacity:.35;"></i>
            <span style="font-size:13px;">왼쪽 목록에서 상담 이력을 선택하세요</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     환자 조회 모달 — 이름으로 환자 검색 → 과거 상담이력 선택 → 검수 화면으로 가져오기
     (현재 처방전의 환자와 무관하게 조회 가능하므로 조건 없이 항상 렌더)
══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="patientLookupModal" style="z-index:10000;" onclick="if(event.target===this)closePatientLookup()">
  <div class="modal-box" style="width:1000px;max-width:97vw;height:84vh;display:flex;flex-direction:column;">
    <div class="modal-header">
      <i class="fa-solid fa-magnifying-glass" style="color:var(--primary);font-size:16px;"></i>
      <span class="modal-title">환자 조회</span>
      <span style="font-size:11px;color:var(--text-muted);margin-left:4px;">환자명(또는 연락처)으로 조회해 과거 상담이력을 가져옵니다</span>
      <button class="modal-close" onclick="closePatientLookup()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    {{-- 검색 바 --}}
    <div style="flex-shrink:0;padding:12px 18px;border-bottom:1px solid var(--border);display:flex;gap:8px;align-items:center;">
      <input type="text" id="plQuery" placeholder="환자명 2글자 이상 (예: 홍길동) 또는 연락처 4자리 이상"
             autocomplete="off"
             style="flex:1;height:32px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;">
      <button type="button" id="plSearchBtn" onclick="plSearch()"
              style="height:32px;padding:0 18px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
        <i class="fa-solid fa-magnifying-glass"></i> 검색
      </button>
    </div>

    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">
      {{-- 1) 환자 목록 --}}
      <div style="width:250px;flex-shrink:0;border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--bg);">
        <div style="flex-shrink:0;padding:7px 14px;font-size:11px;font-weight:500;color:var(--text-muted);border-bottom:1px solid var(--border-light);">
          환자 <span id="plPatientCount"></span>
        </div>
        <div id="plPatientList" style="flex:1;overflow-y:auto;">
          <div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--text-muted);">환자명을 검색하세요</div>
        </div>
      </div>

      {{-- 2) 상담이력 목록 --}}
      <div style="width:240px;flex-shrink:0;border-right:1px solid var(--border);display:flex;flex-direction:column;">
        <div style="flex-shrink:0;padding:7px 14px;font-size:11px;font-weight:500;color:var(--text-muted);border-bottom:1px solid var(--border-light);">
          상담이력 <span id="plCounselCount"></span>
        </div>
        <div id="plCounselList" style="flex:1;overflow-y:auto;">
          <div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--text-muted);">환자를 선택하세요</div>
        </div>
      </div>

      {{-- 3) 선택한 상담이력 요약 + 가져오기 --}}
      <div style="flex:1;min-width:0;display:flex;flex-direction:column;">
        <div id="plDetailBody" style="flex:1;overflow-y:auto;padding:18px 20px;">
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;min-height:200px;color:var(--text-muted);gap:10px;">
            <i class="fa-solid fa-hand-pointer" style="font-size:26px;opacity:.35;"></i>
            <span style="font-size:13px;">가져올 상담이력을 선택하세요</span>
          </div>
        </div>
        <div style="flex-shrink:0;padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:center;">
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary);cursor:pointer;">
            <input type="checkbox" id="plWithItems" checked style="width:16px;height:16px;">
            처방 제품도 함께 가져오기
          </label>
          <button type="button" id="plImportBtn" onclick="plImportSelected()" disabled
                  style="margin-left:auto;height:32px;padding:0 18px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
            <i class="fa-solid fa-file-import"></i> 이 상담이력 가져오기
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Attachment Delete Confirm Popover --}}
{{-- 팩스 PDF 뷰어 팝오버 --}}
<div id="faxPdfPopover" style="display:none;position:fixed;z-index:10100;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.22);flex-direction:column;overflow:hidden;width:min(820px,90vw);height:min(88vh,88vh);">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border);flex-shrink:0;gap:8px;">
    <div style="display:flex;align-items:center;gap:7px;">
      <i class="fa-regular fa-file-pdf" style="color:var(--alert-500);font-size:15px;"></i>
      <span style="font-size:12px;font-weight:700;">팩스 전송 서류</span>
      <span style="font-size:11px;color:var(--text-muted);">— {{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '—' }}</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px;">
      <a id="faxPdfDownloadBtn" href="#" download
         style="padding:4px 10px;background:var(--primary);color:#fff;border-radius:var(--radius);font-size:11px;font-weight:500;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <i class="fa-solid fa-download" style="font-size:10px;"></i> 다운로드
      </a>
      <button onclick="closeFaxPdfModal()"
              style="background:none;border:none;font-size:18px;line-height:1;color:var(--text-muted);cursor:pointer;padding:2px 6px;">&times;</button>
    </div>
  </div>
  <iframe id="faxPdfFrame" src="" style="flex:1;width:100%;border:none;background:var(--gray-700);"></iframe>
</div>

<div id="deleteAttachPopover" style="display:none;position:fixed;width:220px;background:var(--bg-card);border:1px solid var(--danger);border-radius:var(--radius-lg);box-shadow:0 6px 20px rgba(0,0,0,.18);z-index:1000;padding:14px 16px;">
  <div style="font-size:12px;font-weight:700;color:var(--danger);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
    <i class="fa-solid fa-triangle-exclamation"></i> 삭제 확인
  </div>
  <div style="font-size:11px;color:var(--text-primary);margin-bottom:12px;">
    <span id="deleteAttachName" style="font-weight:700;word-break:break-all;"></span><br>
    <span style="color:var(--text-muted);">파일을 삭제합니다. 복구할 수 없습니다.</span>
  </div>
  <div style="display:flex;gap:6px;justify-content:flex-end;">
    <button onclick="_closeAttachPopover()" style="font-size:11px;padding:4px 10px;border:1px solid var(--border);border-radius:var(--radius);background:none;cursor:pointer;">취소</button>
    <button id="btnConfirmAttachDelete" style="font-size:11px;padding:4px 10px;border:none;border-radius:var(--radius);background:var(--danger);color:#fff;cursor:pointer;"><i class="fa-solid fa-trash-can"></i> 삭제</button>
  </div>
</div>

{{-- Delete Confirm Modal --}}
<div class="modal-overlay" id="deleteOrderModal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);font-size:18px;"></i>
      <span class="modal-title">주문 삭제 확인</span>
      <button class="modal-close" onclick="closeModal('deleteOrderModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" style="padding:20px 24px;">
      <p style="font-size:13px;margin:0 0 8px;">다음 주문을 삭제합니다. 이 작업은 되돌릴 수 없습니다.</p>
      <div style="background:var(--bg);border-radius:var(--radius);padding:12px 14px;font-size:12px;line-height:2;">
        <div><span style="color:var(--text-muted);">CE 주문번호</span> &nbsp;<b id="deleteOrderNum" style="font-family:monospace;color:var(--danger);">-</b></div>
        <div><span style="color:var(--text-muted);">Withworks SO</span> &nbsp;<b id="deleteOrderSoNo" style="font-family:monospace;color:var(--primary);">-</b></div>
      </div>
      <p style="font-size:12px;color:var(--warning);margin:10px 0 0;"><i class="fa-solid fa-circle-info"></i> Withworks 판매주문도 함께 삭제됩니다.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('deleteOrderModal')">취소</button>
      <button class="btn btn-danger" id="btnConfirmDelete" onclick="executeDeleteOrder(event)">
        <i class="fa-solid fa-trash-can"></i> 삭제 확인
      </button>
    </div>
  </div>
</div>

{{-- Order Modal --}}
<div class="modal-overlay" id="orderModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:20px;"></i>
      <span class="modal-title">주문 연계 완료</span>
      <button class="modal-close" onclick="closeModal('orderModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="orderModalBody" style="text-align:center;padding:28px 20px;">
      <div style="font-size:52px;color:var(--primary);margin-bottom:12px;">✅</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:6px;">주문 연계 완료</div>
      <div style="font-size:14px;color:var(--text-muted);margin-bottom:20px;">주문이 생성되었습니다.</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('orderModal')">닫기</button>
    </div>
  </div>
</div>

{{-- Approve Modal --}}
<div class="modal-overlay" id="approveModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-shield-halved" style="color:var(--primary);font-size:20px;"></i>
      <span class="modal-title">처방전 승인요청</span>
      <button class="modal-close" onclick="closeModal('approveModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:14px;margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:var(--primary);">✅ 검수 승인 요청</div>
        <div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
          처방전 {{ $prescription->rx_number }}의 OCR 검수를 완료하고 승인 요청합니다.
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">승인 메모</label>
        <textarea class="form-control" id="approveMemo" rows="3" placeholder="승인 관련 메모를 입력하세요..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('approveModal')">취소</button>
      <button class="btn btn-primary" id="btnConfirmApprove" onclick="confirmApprove(this)"><i class="fa-solid fa-circle-check"></i> 승인 확정</button>
    </div>
  </div>
</div>



{{-- 커스텀 Danger Confirm 모달 --}}
<div class="modal-overlay" id="dangerConfirmModal">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
      <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);font-size:20px;"></i>
      <span class="modal-title" id="dangerConfirmTitle">확인</span>
      <button class="modal-close" onclick="closeDangerConfirm()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div style="background:var(--alert-50);border:1px solid var(--alert-100);border-radius:var(--radius);padding:14px;">
        <div style="font-size:13px;color:var(--alert-500);font-weight:700;" id="dangerConfirmMsg"></div>
        <div style="font-size:11px;color:var(--alert-500);margin-top:6px;">이 작업은 되돌릴 수 없습니다.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDangerConfirm()">취소</button>
      <button class="btn btn-danger" id="dangerConfirmOkBtn"><i class="fa-solid fa-trash"></i> 확인</button>
    </div>
  </div>
</div>

{{-- Product Search Modal (레거시 — 자동완성으로 대체됨, 삭제 보류) --}}


{{-- 현금영수증 발행 팝오버 (position:fixed — cashReceiptArea 외부) --}}
<div id="crIssuePopover" style="display:none;position:fixed;width:340px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:601;">
  <div id="crIssuePopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
    <div style="width:10px;height:10px;background:var(--primary);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
  </div>
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-receipt" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">현금영수증 발행</span>
    <button onclick="closeCrIssuePopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
    <div>
      <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:6px;display:block;">유형</label>
      <div style="display:flex;gap:16px;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
          <input type="radio" name="cr-type" value="income_deduction" checked style="accent-color:var(--primary);"> 소득공제 (개인)
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
          <input type="radio" name="cr-type" value="business_expense" style="accent-color:var(--primary);"> 지출증빙 (사업자)
        </label>
      </div>
    </div>
    <div>
      <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">휴대폰번호 또는 사업자번호 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="cr-identifier" class="form-control" style="font-size:12px;" placeholder="010-0000-0000" maxlength="13" inputmode="numeric" oninput="formatCrIdentifier(this)">
    </div>
    <div>
      <label style="font-size:11px;font-weight:500;color:var(--text-muted);margin-bottom:4px;display:block;">금액 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="cr-amount" class="form-control" style="font-size:12px;" inputmode="numeric" placeholder="0" oninput="formatCrAmount(this)">
    </div>
    <div id="cr-no-order-notice" style="display:none;font-size:11px;color:var(--alert-500);background:var(--alert-50);border:1px solid var(--alert-100);border-radius:6px;padding:8px 10px;text-align:center;">
      <i class="fa-solid fa-circle-exclamation"></i> 주문을 먼저 생성한 후 현금영수증을 발행할 수 있습니다.
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button class="btn btn-outline btn-sm" onclick="closeCrIssuePopover()">취소</button>
      <button class="btn btn-primary btn-sm" id="btnSubmitCashReceipt" onclick="submitCashReceipt()">
        <i class="fa-solid fa-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- 현금영수증 상세 팝오버 (고정 위치) --}}
<div id="crDetailPopover" style="display:none;position:fixed;width:300px;background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:600;">
  <div id="crDetailPopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
    <div style="width:10px;height:10px;background:var(--bg-card);border:1px solid var(--primary);transform:rotate(45deg);margin:3px auto 0;"></div>
  </div>
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-receipt" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">현금영수증 상세</span>
    <button onclick="closeCrDetailPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">×</button>
  </div>
  <div style="padding:14px;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <colgroup><col width="38%"><col width="62%"></colgroup>
      <tbody>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">승인번호</th>
          <td id="cr-d-no" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">유형</th>
          <td id="cr-d-type" style="padding:7px 0;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">식별번호</th>
          <td id="cr-d-identifier" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">거래금액</th>
          <td id="cr-d-amount" style="padding:7px 0;font-weight:700;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">발행일시</th>
          <td id="cr-d-issued-at" style="padding:7px 0;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">주문번호</th>
          <td id="cr-d-order-no" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr>
          <th style="padding:7px 0;font-weight:700;color:var(--text-muted);text-align:left;">환자명</th>
          <td id="cr-d-patient" style="padding:7px 0;"></td>
        </tr>
      </tbody>
    </table>
    <div style="display:flex;justify-content:flex-end;margin-top:10px;">
      @if($prescription->order)
      <a href="{{ route('orders.cashReceiptPdf', $prescription->order) }}"
         download
         class="btn btn-primary btn-sm"
         style="display:flex;align-items:center;gap:6px;text-decoration:none;">
        <i class="fa-solid fa-file-pdf"></i> PDF 다운로드
      </a>
      @endif
    </div>
  </div>
</div>

{{-- ── 메시지 유형 편집 (메시지 관리 화면과 같은 것을 고친다) ── --}}
<div id="rxTplBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10200;"
     onclick="rxTplClose()"></div>
<div id="rxTplModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:520px;max-width:94vw;background:var(--bg-card);border:1px solid var(--primary);
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:10201;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <span id="rxTplTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">메시지 유형</span>
    <button onclick="rxTplClose()" style="background:none;border:none;cursor:pointer;color:var(--gray-0);font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    <div id="rxTplAlimNote" style="display:none;font-size:11px;color:var(--alert-500);line-height:1.6;">
      알림톡 코드는 <b>카카오에 등록한 템플릿코드</b>와 같아야 실제로 발송됩니다.
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:4px;">발송 수단</label>
        <input type="text" id="rxTplChannelLabel" class="form-control" readonly style="background:var(--gray-50);" />
      </div>
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:4px;">코드</label>
        <input type="text" id="rxTplCode" class="form-control" maxlength="60" placeholder="order_confirmed" />
      </div>
    </div>
    <div>
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">이름</label>
      <input type="text" id="rxTplLabel" class="form-control" maxlength="100" />
    </div>
    <div>
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">설명</label>
      <input type="text" id="rxTplDesc" class="form-control" maxlength="200" />
    </div>
    <div id="rxTplBodyWrap">
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">본문</label>
      <textarea id="rxTplBody" class="form-control" rows="6" style="font-size:13px;line-height:1.7;"></textarea>
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;line-height:1.6;">
        #{고객명} #{처방번호} #{주문번호} #{본인부담금} #{금액} #{운송장번호}
      </div>
    </div>
    <div id="rxTplResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="rxTplClose()">취소</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="rxTplSave()">저장</button>
    </div>
  </div>
</div>

{{-- ── 크게 보기 — 떠 있는 창 ────────────────────────────────
     덮개(backdrop)를 두지 않는다. 이 창이 떠 있는 동안에도 오른쪽 항목을 계속
     입력해야 하므로, 화면을 막으면 안 된다. 그래서 모달이 아니라 창이다.
     z-index 는 900 — 진짜 모달(1000 이상) 밑에 있어야 그것들이 이 창을 덮는다. --}}
<div id="bigViewer" style="display:none;">
  <div id="bigViewerHead">
    <i class="fa-solid fa-up-right-and-down-left-from-center" style="font-size:11px;opacity:.7;"></i>
    <span id="bigViewerTitle">처방전</span>
    <div class="bv-acts">
      <button type="button" class="bv-btn" onclick="bvRotate()" title="회전"><i class="fa-solid fa-rotate-left"></i></button>
      <button type="button" class="bv-btn" onclick="bvZoom(-1)" title="축소"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
      <span id="bvZoomLabel" class="bv-zoom">100%</span>
      <button type="button" class="bv-btn" onclick="bvZoom(1)" title="확대"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
      <button type="button" class="bv-btn" onclick="bvFit()" title="맞춤"><i class="fa-solid fa-arrows-rotate"></i></button>
      <a id="bvOpen" class="bv-btn" href="#" target="_blank" rel="noopener" title="새 탭에서 원본 열기"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
      <button type="button" class="bv-btn bv-close" onclick="closeBigViewer()" title="닫기">&#215;</button>
    </div>
  </div>
  <div id="bigViewerBody">
    <img    id="bvImg"   src="" alt="" draggable="false" />
    <iframe id="bvFrame" src="" style="display:none;"></iframe>
  </div>
  <div id="bigViewerGrip" title="크기 조절"></div>
</div>
@endpush

@php
$_itemsData = $prescription->items->map(fn($i) => [
    'product_name'    => $i->product_name,
    'product_code'    => $i->product_code,
    'quantity'        => $i->quantity,
    'product_price'   => $i->product_price,
    'insurance_price' => $i->insurance_price,
    'nhis_status'     => $i->nhis_status ?? 'eligible',
    'nhis_amount'     => $i->nhis_amount,
    'patient_copay'   => $i->patient_copay,
])->values();
@endphp

@push('scripts')
<script>
// ── 통합 문서 뷰어 ─────────────────────────────────────
const ALL_DOCS = @json($allDocsJson);
let currentDocIdx = 0;

/* ── 메시지 유형 편집 ──────────────────────────────────────
   메시지 관리 화면과 같은 표를 고친다. 저장하면 화면을 다시 불러와 팝오버 목록에
   반영한다 — 목록을 서버가 그리므로 다시 그리게 하는 편이 어긋날 일이 없다. */
const RX_TPL_LIST_URL  = @json(route('messages.templates'));
const RX_TPL_STORE_URL = @json(route('messages.templates.store'));
const RX_TPL_BASE_URL  = @json(url('/messages/templates'));
let _rxTplChannel = 'sms', _rxTplId = null;

function rxTplNew(channel) {
  _rxTplChannel = channel; _rxTplId = null;
  document.getElementById('rxTplTitle').textContent = '메시지 유형 추가';
  ['rxTplCode', 'rxTplLabel', 'rxTplDesc', 'rxTplBody'].forEach(id => document.getElementById(id).value = '');
  _rxTplOpen();
}

async function rxTplEdit(channel, code) {
  _rxTplChannel = channel;
  document.getElementById('rxTplTitle').textContent = '메시지 유형 수정';
  _rxTplOpen();
  try {
    const res = await fetch(RX_TPL_LIST_URL + '?channel=' + encodeURIComponent(channel), { headers: { 'Accept': 'application/json' } });
    const d   = await res.json();
    const t   = (d.templates ?? []).find(x => x.code === code);
    if (!t) { _rxTplSay('유형을 찾지 못했습니다.', false); return; }
    _rxTplId = t.id;
    document.getElementById('rxTplCode').value  = t.code;
    document.getElementById('rxTplLabel').value = t.label;
    document.getElementById('rxTplDesc').value  = t.description ?? '';
    document.getElementById('rxTplBody').value  = t.body ?? '';
  } catch (e) { _rxTplSay('불러오지 못했습니다.', false); }
}

function _rxTplOpen() {
  const isAlim = _rxTplChannel === 'alimtalk';
  document.getElementById('rxTplChannelLabel').value = isAlim ? '카카오 알림톡' : '문자(SMS)';
  document.getElementById('rxTplAlimNote').style.display = isAlim ? 'block' : 'none';
  // 알림톡 본문은 카카오가 정한다 — 여기서 고쳐도 나가지 않으므로 칸을 감춘다
  document.getElementById('rxTplBodyWrap').style.display = isAlim ? 'none' : '';
  document.getElementById('rxTplResult').style.display = 'none';
  document.getElementById('rxTplBackdrop').style.display = 'block';
  document.getElementById('rxTplModal').style.display    = 'block';
  document.getElementById('rxTplLabel').focus();
}

function rxTplClose() {
  document.getElementById('rxTplBackdrop').style.display = 'none';
  document.getElementById('rxTplModal').style.display    = 'none';
}

function _rxTplSay(msg, ok) {
  const box = document.getElementById('rxTplResult');
  box.style.display    = 'block';
  box.style.background = ok ? 'var(--primary-50)' : 'var(--danger-light)';
  box.style.color      = ok ? 'var(--primary)'    : 'var(--danger)';
  box.style.border     = '1px solid ' + (ok ? 'var(--primary-200)' : '#fca5a5');
  box.textContent = msg;
}

async function rxTplSave() {
  const body = {
    channel:     _rxTplChannel,
    code:        document.getElementById('rxTplCode').value.trim(),
    label:       document.getElementById('rxTplLabel').value.trim(),
    description: document.getElementById('rxTplDesc').value.trim(),
    body:        document.getElementById('rxTplBody').value,
    is_active:   true,
  };
  if (!body.code || !body.label) { _rxTplSay('코드와 이름은 반드시 입력해야 합니다.', false); return; }

  try {
    const res = await fetch(_rxTplId ? `${RX_TPL_BASE_URL}/${_rxTplId}` : RX_TPL_STORE_URL, {
      method: _rxTplId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify(body),
    });
    const d = await res.json();
    if (d.success) {
      _rxTplSay('저장했습니다. 화면을 다시 불러옵니다.', true);
      setTimeout(() => location.reload(), 900);
    } else {
      _rxTplSay(d.message ?? (Object.values(d.errors ?? {})[0]?.[0]) ?? '저장 실패', false);
    }
  } catch (e) { _rxTplSay('네트워크 오류가 발생했습니다.', false); }
}

/* ── 크게 보기 창 ──────────────────────────────────────────
   파일을 큰 창으로 띄우되 화면은 계속 쓸 수 있어야 한다. 덮개를 두지 않고
   창만 띄운다 — 창 밖은 그대로 눌리고 입력된다. */
let _bvZoom = 1, _bvRot = 0;
/* 끌어 옮긴 거리. 창을 옮기는 것과 그림을 옮기는 것은 다른 일이라 따로 센다. */
let _bvTx = 0, _bvTy = 0;
let _bvBox  = null;   // 옮기거나 크기를 바꾼 위치를 기억해 다시 열 때 그대로 쓴다

function _bvEl(id) { return document.getElementById(id); }

function openBigViewer() {
  const doc = ALL_DOCS[currentDocIdx];
  const url = doc?.url || document.getElementById('viewerOpenBtn')?.getAttribute('href');
  if (!url || url === '#') { showToast('열 파일이 없습니다.', 'warning'); return; }

  const win   = _bvEl('bigViewer');
  const img   = _bvEl('bvImg');
  const frame = _bvEl('bvFrame');

  _bvEl('bigViewerTitle').textContent = doc?.name || '처방전';
  _bvEl('bvOpen').href = url;

  if (doc?.isPdf) {
    img.style.display = 'none'; img.src = '';
    frame.src = url; frame.style.display = '';
  } else {
    frame.style.display = 'none'; frame.src = '';
    img.src = url; img.style.display = '';
  }

  /* 처음 열 때는 본문 왼쪽 절반. 옮겼던 적이 있으면 그 자리를 그대로 쓴다.
     화면 왼쪽 끝(12)에서 열었더니 사이드바를 덮어, 메뉴가 접힌 것처럼 보였다 —
     본문이 시작하는 자리에서 연다. */
  const box = _bvBox ?? (() => {
    const left = Math.round(_bvContentLeft()) + 12;
    return {
      left,
      top:  Math.round((window.innerHeight - Math.round(window.innerHeight * 0.9)) / 2),
      w:    Math.max(360, Math.round((window.innerWidth - left) * 0.55)),
      h:    Math.round(window.innerHeight * 0.9),
    };
  })();
  _bvApplyBox(box);

  win.style.display = 'flex';
  bvFit();
}

function closeBigViewer() {
  const win = _bvEl('bigViewer');
  win.style.display = 'none';
  // 창을 닫으면 파일도 놓아 준다. src='' 로 두면 브라우저가 현재 주소를 다시 부르므로
  // 속성 자체를 지운다.
  _bvEl('bvFrame').removeAttribute('src');
  _bvEl('bvImg').removeAttribute('src');
}

/* 본문이 시작하는 가로 자리 — 사이드바 오른쪽 끝이다.
   탭(액자) 안에서는 사이드바가 숨으므로 0 이 된다. */
function _bvContentLeft() {
  const menu = document.getElementById('layoutMenu');
  if (!menu || getComputedStyle(menu).display === 'none') return 0;
  const r = menu.getBoundingClientRect();
  return r.width > 0 ? r.right : 0;
}

/* 창이 화면 밖으로 나가지 않게 붙들면서 자리와 크기를 준다 */
function _bvApplyBox(box) {
  const win = _bvEl('bigViewer');
  const w = Math.max(320, Math.min(box.w, window.innerWidth  - 16));
  const h = Math.max(240, Math.min(box.h, window.innerHeight - 16));
  // 왼쪽은 본문이 시작하는 자리까지만 — 더 왼쪽으로 밀면 사이드바를 덮는다
  const minLeft = _bvContentLeft();
  const left = Math.max(minLeft, Math.min(box.left, window.innerWidth - w));
  const top  = Math.max(0, Math.min(box.top,  window.innerHeight - h));
  win.style.left = left + 'px';
  win.style.top  = top  + 'px';
  win.style.width  = w + 'px';
  win.style.height = h + 'px';
  _bvBox = { left, top, w, h };
}

function _bvApplyImg() {
  const img = _bvEl('bvImg');
  if (!img) return;
  // 옮긴 뒤에 돌리고 키운다 — 순서를 바꾸면 키울수록 옮긴 거리까지 함께 늘어난다
  img.style.transform = `translate(${_bvTx}px, ${_bvTy}px) rotate(${_bvRot}deg) scale(${_bvZoom})`;
  _bvEl('bvZoomLabel').textContent = Math.round(_bvZoom * 100) + '%';
}

function bvZoom(dir) {
  _bvZoom = Math.min(5, Math.max(0.2, _bvZoom + dir * 0.2));
  _bvApplyImg();
}

function bvRotate() { _bvRot = (_bvRot + 90) % 360; _bvApplyImg(); }

/* 창 크기에 맞춰 되돌린다 */
function bvFit() {
  _bvZoom = 1; _bvRot = 0; _bvTx = 0; _bvTy = 0;
  const img  = _bvEl('bvImg');
  const body = _bvEl('bigViewerBody');
  if (img) {
    img.style.maxWidth  = (body.clientWidth  - 16) + 'px';
    img.style.maxHeight = (body.clientHeight - 16) + 'px';
    img.style.width = 'auto'; img.style.height = 'auto';
  }
  _bvApplyImg();
}

/* 머리를 잡아 옮기고, 오른쪽 아래 모서리를 잡아 크기를 바꾼다.
   pointer 이벤트를 쓰면 마우스가 창 밖으로 나가도 놓칠 때까지 따라온다. */
(function () {
  const win  = document.getElementById('bigViewer');
  if (!win) return;
  const head = document.getElementById('bigViewerHead');
  const grip = document.getElementById('bigViewerGrip');
  let mode = null, sx = 0, sy = 0, start = null;

  function begin(e, which) {
    // 머리 안의 버튼을 누른 것은 옮기려는 뜻이 아니다
    if (which === 'move' && e.target.closest('.bv-acts')) return;
    mode = which; sx = e.clientX; sy = e.clientY;
    start = { ..._bvBox };
    win.classList.add('is-moving');
    win.setPointerCapture(e.pointerId);
    e.preventDefault();
  }

  head.addEventListener('pointerdown', (e) => begin(e, 'move'));
  grip.addEventListener('pointerdown', (e) => begin(e, 'size'));

  win.addEventListener('pointermove', (e) => {
    if (!mode) return;
    const dx = e.clientX - sx, dy = e.clientY - sy;
    if (mode === 'move') {
      _bvApplyBox({ ...start, left: start.left + dx, top: start.top + dy });
    } else {
      _bvApplyBox({ ...start, w: start.w + dx, h: start.h + dy });
    }
  });

  const end = (e) => {
    if (!mode) return;
    mode = null;
    win.classList.remove('is-moving');
    try { win.releasePointerCapture(e.pointerId); } catch (_) {}
  };
  win.addEventListener('pointerup', end);
  win.addEventListener('pointercancel', end);

  // 화면 크기가 바뀌면 창이 밖으로 나가 있을 수 있다
  window.addEventListener('resize', () => {
    if (win.style.display !== 'none' && _bvBox) _bvApplyBox(_bvBox);
  });
})();

/* 큰 창 안에서 그림을 끌어 옮기고 휠로 키운다 — 작은 뷰어와 같은 손놀림이다.
   크게 본다는 것은 대개 어느 한 곳을 들여다본다는 뜻인데, 지금까지는 키우기만 하고
   그 자리로 옮길 수가 없어 가운데만 볼 수 있었다.
   PDF 는 건드리지 않는다 — 브라우저의 PDF 뷰어가 제 몫으로 한다. */
(function () {
  const body = document.getElementById('bigViewerBody');
  const img  = document.getElementById('bvImg');
  if (!body || !img) return;

  let dragging = false, sx = 0, sy = 0, startX = 0, startY = 0;

  img.addEventListener('pointerdown', (e) => {
    if (img.style.display === 'none') return;
    dragging = true;
    sx = e.clientX; sy = e.clientY;
    startX = _bvTx;  startY = _bvTy;
    img.setPointerCapture(e.pointerId);
    img.style.cursor = 'grabbing';
    e.preventDefault();
  });

  img.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    _bvTx = startX + (e.clientX - sx);
    _bvTy = startY + (e.clientY - sy);
    _bvApplyImg();
  });

  const stop = (e) => {
    if (!dragging) return;
    dragging = false;
    img.style.cursor = 'grab';
    try { img.releasePointerCapture(e.pointerId); } catch (_) {}
  };
  img.addEventListener('pointerup', stop);
  img.addEventListener('pointercancel', stop);

  // 두 번 누르면 옮긴 것만 되돌린다(배율은 그대로 둔다 — 다시 키우게 하지 않는다)
  img.addEventListener('dblclick', () => { _bvTx = 0; _bvTy = 0; _bvApplyImg(); });

  /* 휠은 커서가 가리키는 곳을 기준으로 키운다. 가운데 기준으로 키우면 보려던 곳이
     화면 밖으로 밀려나 다시 끌어와야 한다. */
  body.addEventListener('wheel', (e) => {
    if (img.style.display === 'none') return;   // PDF 일 때는 그대로 둔다
    e.preventDefault();

    const before = _bvZoom;
    const after  = Math.min(5, Math.max(0.2, before * (e.deltaY < 0 ? 1.12 : 1 / 1.12)));
    if (after === before) return;

    const rect = body.getBoundingClientRect();
    const cx = e.clientX - rect.left - rect.width  / 2;
    const cy = e.clientY - rect.top  - rect.height / 2;
    const k  = after / before;
    _bvTx = cx + (_bvTx - cx) * k;
    _bvTy = cy + (_bvTy - cy) * k;
    _bvZoom = after;
    _bvApplyImg();
  }, { passive: false });
})();

function switchViewerDoc(el) {
  const thumbs = Array.from(document.querySelectorAll('#docStrip .doc-thumb'));
  const idx = thumbs.indexOf(el);
  if (idx < 0 || idx >= ALL_DOCS.length) return;

  currentDocIdx = idx;
  const doc = ALL_DOCS[idx];

  thumbs.forEach(t => t.classList.remove('active'));
  el.classList.add('active');

  const prescImg = document.getElementById('prescCanvas');
  const pdfFrame = document.getElementById('pdfCanvas');
  const badge    = document.getElementById('viewerBadge');
  const openBtn  = document.getElementById('viewerOpenBtn');

  if (doc.isPdf) {
    if (prescImg) { prescImg.style.display = 'none'; prescImg.src = ''; }
    if (pdfFrame) { pdfFrame.src = doc.url; pdfFrame.style.display = ''; }
    if (badge) badge.style.display = 'none';
  } else {
    if (pdfFrame) { pdfFrame.style.display = 'none'; pdfFrame.src = ''; }
    if (prescImg) { prescImg.src = doc.url; prescImg.style.display = ''; }
    if (badge) { badge.textContent = doc.name; badge.style.display = ''; }
    resetImg();
  }

  if (openBtn) { openBtn.href = doc.url || '#'; openBtn.style.display = ''; }
  // 처음에 볼 것이 없어 숨겨 두었더라도, 문서를 고른 이상 열 수 있어야 한다
  ['btnBigViewer', 'btnResetView'].forEach(id => {
    const b = document.getElementById(id);
    if (b) b.style.display = '';
  });

  // 크게 보기 창이 떠 있으면 고른 문서를 따라간다
  const bv = document.getElementById('bigViewer');
  if (bv && bv.style.display !== 'none') openBigViewer();
}

function _closeAttachPopover() {
  document.getElementById('deleteAttachPopover').style.display = 'none';
}

function deleteAttachment(e, id, btn) {
  e.stopPropagation();
  const pop    = document.getElementById('deleteAttachPopover');
  const nameEl = document.getElementById('deleteAttachName');
  const doc    = ALL_DOCS.find(a => a.id === id);
  if (nameEl) nameEl.textContent = doc?.name ?? '파일';

  // 버튼 위치 기준으로 팝오버 위치 계산
  const r = btn.getBoundingClientRect();
  const pw = 220, ph = 120;
  let left = r.right - pw;
  let top  = r.top - ph - 8;
  if (top < 8) top = r.bottom + 8;
  if (left < 8) left = 8;
  pop.style.left = left + 'px';
  pop.style.top  = top  + 'px';
  pop.style.display = 'block';

  const confirmBtn = document.getElementById('btnConfirmAttachDelete');
  if (confirmBtn) {
    confirmBtn.onclick = () => {
      _closeAttachPopover();
      fetch(`{{ route('prescriptions.attachments.destroy', [$prescription, '__ATT__']) }}`.replace('__ATT__', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      }).then(r => r.json()).then(d => {
        if (d.success) {
          const thumb = btn.closest('.doc-thumb');
          const docIdx = ALL_DOCS.findIndex(a => a.id === id);
          if (docIdx !== -1) ALL_DOCS.splice(docIdx, 1);
          thumb.remove();
          const strip = document.getElementById('docStrip');
          const wrap  = document.getElementById('docStripWrap');
          if (strip && wrap && !strip.querySelectorAll('.doc-thumb').length) wrap.style.display = 'none';
          const countEl = document.getElementById('docCount');
          if (countEl) countEl.textContent = ALL_DOCS.length;
          if (currentDocIdx >= ALL_DOCS.length) {
            const firstThumb = strip ? strip.querySelector('.doc-thumb') : null;
            if (firstThumb) switchViewerDoc(firstThumb);
          }
          showToast('첨부 파일이 삭제되었습니다.', 'success');
        }
      }).catch(() => showToast('삭제 실패', 'danger'));
    };
  }

  // 외부 클릭 시 닫기
  setTimeout(() => {
    document.addEventListener('click', function _outside(ev) {
      if (!pop.contains(ev.target)) { _closeAttachPopover(); document.removeEventListener('click', _outside); }
    });
  }, 0);
}

// ── 첨부 문서 종류 combobox ──────────────────────────────
function _adtOpen()   { const d=document.getElementById('_adtDrop'); if(d){document.querySelectorAll('#_adtDrop ._adt-opt').forEach(el=>el.style.display='');d.style.display='block';} }
function _adtClose()  { const d=document.getElementById('_adtDrop'); if(d) d.style.display='none'; }
function _adtToggle() { const d=document.getElementById('_adtDrop'); if(d) d.style.display=d.style.display==='block'?'none':'block'; }
function _adtPick(v)  { const i=document.getElementById('attachDocTypeSelect'); if(i) i.value=v; _adtClose(); }
function _adtFilter(q) {
  document.querySelectorAll('#_adtDrop ._adt-opt').forEach(el => {
    el.style.display = el.textContent.includes(q) ? '' : 'none';
  });
  document.getElementById('_adtDrop').style.display = 'block';
}

function handleAttachUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const _labelMap = { '처방전': 'prescription', '위임장': 'delegation', '신분증': 'id_card',
                      '등록신청서': 'registration_form', '결과지': 'test_result', '기타': 'other' };
  const inputVal  = (document.getElementById('attachDocTypeSelect').value || '').trim() || '기타';
  const docType   = _labelMap[inputVal] ?? 'other';
  const docLabel  = (docType === 'other' && inputVal !== '기타') ? inputVal : '';
  const fd = new FormData();
  fd.append('file', file);
  fd.append('doc_type', docType);
  if (docLabel) fd.append('doc_label', docLabel);
  fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
  input.value = '';

  showToast('첨부 문서 업로드 중…', 'info');
  fetch('{{ route('prescriptions.attachments.store', $prescription) }}', {
    method: 'POST', body: fd,
  }).then(r => r.json()).then(d => {
    if (!d.success) { showToast(d.message || '업로드 실패', 'danger'); return; }
    const att = d.attachment;
    ALL_DOCS.push(att);
    const strip = document.getElementById('docStrip');
    const wrap  = document.getElementById('docStripWrap');
    const thumbHtml = att.isPdf
      ? `<div class="attach-thumb-pdf"><i class="fa-regular fa-file-pdf"></i></div>`
      : `<img class="attach-thumb-img" src="${att.url}" alt="${att.typeLabel}" loading="lazy">`;
    const thumbEl = document.createElement('div');
    thumbEl.className = 'attach-thumb doc-thumb';
    thumbEl.dataset.attId = att.id;
    thumbEl.setAttribute('onclick', 'switchViewerDoc(this)');
    thumbEl.innerHTML = `${thumbHtml}
      <div class="attach-type-badge">${att.typeLabel}</div>
      <button class="attach-del-btn" onclick="deleteAttachment(event,${att.id},this)" title="삭제"><i class="fa-solid fa-xmark"></i></button>`;
    strip.appendChild(thumbEl);
    if (wrap) wrap.style.display = '';
    const countEl = document.getElementById('docCount');
    if (countEl) countEl.textContent = ALL_DOCS.length;
    switchViewerDoc(thumbEl);
    showToast('첨부 문서가 추가되었습니다.', 'success');
  }).catch(() => showToast('업로드 실패', 'danger'));
}

// ── 뷰어 위치 전환 (좌 ↔ 우) ────────────────────────────
function toggleViewerSide() {
  const layout = document.querySelector('.order-layout');
  if (!layout) return;
  const isRight = layout.classList.toggle('viewer-right');
  localStorage.setItem('rx_viewer_side', isRight ? 'right' : 'left');
  _applyViewerSideBtn(isRight);
}

function _applyViewerSideBtn(isRight) {
  const btn = document.getElementById('btnToggleViewerSide');
  const lbl = document.getElementById('btnToggleViewerSideLabel');
  if (btn) btn.title = isRight ? '뷰어를 왼쪽으로' : '뷰어를 오른쪽으로';
  if (lbl) lbl.textContent = isRight ? '왼쪽으로' : '오른쪽으로';
}

document.addEventListener('DOMContentLoaded', function () {
  if (localStorage.getItem('rx_viewer_side') === 'right') {
    const layout = document.querySelector('.order-layout');
    if (layout) layout.classList.add('viewer-right');
    _applyViewerSideBtn(true);
  }
  if (localStorage.getItem('rx_tab_view') === 'table') {
    _applyTableView(true);
    setTimeout(() => { syncCardToTable(); renderItemsTable(); syncOrderTabToTable(); }, 250);
  }

  // 스크롤에 따라 뷰어를 화면에 붙이던 코드는 걷어냈다.
  // 본문과 함께 흐르는 편이 시안에 맞고, 위치를 계산할 일도 없어진다.

  // ── 탭바 고정: body로 reparent해서 정보바 바로 아래에 fixed (transform/overflow 우회) ──
  (function () {
    if (window.matchMedia('(max-width: 768px)').matches) return;
    const bar = document.getElementById('tabBarInner');
    if (!bar) return;
    const barParent = bar.parentNode;   // #tabBarOuter

    // 정보바 바로 아래 y좌표(고정 헤더 겹침 방지). 정보바가 fixed면 그 bottom, 아니면 navbar bottom
    function getTop() {
      const navEl  = document.getElementById('layoutNavbar');
      const patBar = document.getElementById('patient-info-bar');
      const navVisible = navEl && getComputedStyle(navEl).display !== 'none';
      let bottom = navVisible ? navEl.getBoundingClientRect().bottom : 0;
      if (patBar && patBar.classList.contains('info-bar-pinned')) {
        bottom = patBar.getBoundingClientRect().bottom;
      }
      return bottom;   // 정보바와 붙도록 여백 0
    }

    const ph = document.createElement('div');   // 고정 시 자리 유지 placeholder
    ph.style.display = 'none';
    barParent.insertBefore(ph, bar);

    let absTop = null, barLeft = 0, barW = 0, isFixed = false;

    function measure() {
      const r = bar.getBoundingClientRect();
      absTop  = r.top + window.scrollY;
      barLeft = r.left;
      barW    = bar.offsetWidth;
      ph.style.height = bar.offsetHeight + 'px';
    }

    function fix() {
      measure();                       // ph.height 설정 + 자연 위치 기록(reparent 전)
      ph.style.display = 'block';       // 자리 유지 → 아래 콘텐츠가 위로 튀지 않음
      document.body.appendChild(bar);
      const r = ph.getBoundingClientRect();   // 자기 열(col2)의 실제 좌/폭
      // 평면(그림자 X) + 하단 실선 → 정보바에 '붙은' 헤더로 보이게
      bar.style.cssText =
        `position:fixed;top:${getTop()}px;left:${r.left}px;width:${ph.offsetWidth}px;` +
        `z-index:45;background:var(--bg-card);margin:0;padding-bottom:0;` +
        `border-bottom:1px solid var(--border);`;
      isFixed = true;
    }
    function unfix() {
      barParent.insertBefore(bar, ph);
      bar.style.cssText = '';
      ph.style.display = 'none';
      absTop = null; isFixed = false;
    }

    function onScroll() {
      const top = getTop();
      // 자연 위치: 고정 중이면 placeholder, 아니면 bar 자체(rect=뷰포트 기준, 스크롤러 무관)
      const natTop = (isFixed ? ph : bar).getBoundingClientRect().top;
      if (natTop <= top && !isFixed)      fix();
      else if (natTop > top && isFixed)   unfix();
      else if (isFixed) {
        const r = ph.getBoundingClientRect();
        bar.style.top = getTop() + 'px'; bar.style.left = r.left + 'px'; bar.style.width = ph.offsetWidth + 'px';
      }
    }

    // capture:true → 워크스페이스 iframe 등 어떤 스크롤러의 scroll도 포착
    window.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', () => { if (isFixed) unfix(); absTop = null; onScroll(); });
    onScroll();
  })();
});

// ── 카드 / 테이블 뷰 토글 ──────────────────────────────
function toggleTabView() {
  const col = document.getElementById('tabsCol');
  if (!col) return;
  const isTable = col.classList.toggle('tab-view-table');
  localStorage.setItem('rx_tab_view', isTable ? 'table' : 'card');
  _applyTableView(isTable);
  if (isTable) {
    syncCardToTable();
    renderItemsTable();
    syncOrderTabToTable();
  } else {
    // 테이블뷰 DOM 비우고 카드뷰 재생성 — querySelector 충돌 방지
    const tblCont = document.getElementById('items-table-container');
    if (tblCont) tblCont.innerHTML = '';
    renderItems();
  }
}

function _applyTableView(isTable) {
  const btn = document.getElementById('btnViewToggle');
  const lbl = document.getElementById('btnViewToggleLabel');
  const col = document.getElementById('tabsCol');
  if (col) col.classList.toggle('tab-view-table', isTable);
  // 테두리 없는 글자 링크라 색만 바꾼다 (시안 137:701)
  if (btn) btn.style.color = isTable ? 'var(--primary)' : 'var(--gray-700)';
  if (lbl) lbl.textContent = isTable ? '카드뷰' : '테이블뷰';
}

function syncCardToTable() {
  document.querySelectorAll('[data-from]').forEach(el => {
    const src = document.getElementById(el.dataset.from);
    if (!src) return;
    let val;
    if (src.tagName === 'SELECT') {
      val = (src.value ? src.options[src.selectedIndex]?.text?.trim() : '') || '-';
    } else {
      val = src.value?.trim() || '-';
    }
    el.textContent = val;
  });
  const tvAddr = document.getElementById('tv-address');
  if (tvAddr) {
    const pc  = document.getElementById('f-postcode')?.value?.trim()      ?? '';
    const adr = document.getElementById('f-address')?.value?.trim()       ?? '';
    const dtl = document.getElementById('f-address-detail')?.value?.trim() ?? '';
    tvAddr.textContent = [pc, adr, dtl].filter(Boolean).join(' ') || '-';
  }
  const tvRenew = document.getElementById('tv-renew-date');
  if (tvRenew) tvRenew.textContent = document.getElementById('disp-renew-date')?.textContent?.trim() || '-';
}

function syncOrderTabToTable() {
  const idMap = { 'tv-costNhisAmt':'costNhisAmt', 'tv-costNhis':'costNhis', 'tv-costTotal':'costTotal' };
  for (const [tvId, srcId] of Object.entries(idMap)) {
    const tv = document.getElementById(tvId);
    const src = document.getElementById(srcId);
    if (tv && src) tv.textContent = src.textContent;
  }
  const tvRec = document.getElementById('tv-ship-recipient');
  if (tvRec) tvRec.textContent = document.getElementById('shippingRecipient')?.value?.trim() || '-';
  const tvAddr = document.getElementById('tv-ship-addr');
  if (tvAddr) {
    const pc = document.getElementById('shippingPostcode')?.value?.trim() ?? '';
    const a  = document.getElementById('shippingAddr')?.value?.trim()     ?? '';
    const d  = document.getElementById('shippingAddrDetail')?.value?.trim()  ?? '';
    tvAddr.textContent = [pc, a, d].filter(Boolean).join(' ') || '-';
  }
  const tvOrderSum = document.getElementById('order-items-summary-tv');
  const cvOrderSum = document.getElementById('order-items-summary');
  if (tvOrderSum && cvOrderSum) tvOrderSum.innerHTML = cvOrderSum.innerHTML;
}

function renderItemsTable() {
  const el = document.getElementById('items-table-container');
  if (!el) return;
  // 카드뷰 DOM 비워 querySelector 충돌 방지 (items[] 배열이 진실의 원천)
  const cardCont = document.getElementById('items-container');
  if (cardCont) cardCont.innerHTML = '';

  const nhisOpts = (sel) => [['eligible','급여(90%)'],['ineligible','비급여'],['partial','일부(50%)']].map(
    ([v,l]) => `<option value="${v}"${sel===v?' selected':''}>${l}</option>`
  ).join('');

  const rows = items.map((item, idx) => {
    const nhisSt     = item.nhis_status || 'eligible';
    const nhisAmt    = Number(item.nhis_amount   || 0).toLocaleString('ko-KR');
    const copay      = Number(item.patient_copay || 0).toLocaleString('ko-KR');
    const displayName = item.product_name
      ? escHtml(item.product_name) + (item.product_code ? ` (${escHtml(item.product_code)})` : '')
      : '';
    return `<tr class="item-card" data-idx="${idx}">
      <td style="text-align:center;color:var(--text-muted);font-size:11px;">${idx+1}</td>
      <td class="pac-cell">
        <div class="pac-wrap" style="position:relative;width:100%;">
          <div style="display:flex;gap:4px;align-items:center;">
            <input type="text" class="form-control item-display" id="pac-input-${idx}"
                   style="font-size:12px;min-width:0;flex:1;height:32px;padding:2px 7px;" autocomplete="off"
                   placeholder="제품명 또는 코드 입력 후 검색"
                   value="${displayName}"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();pacSearchBtn(${idx});}" />
            <button type="button" class="btn btn-primary btn-sm" title="제품 검색"
                    style="flex-shrink:0;padding:0 8px;height:32px;"
                    onmousedown="event.preventDefault()"
                    onclick="pacSearchBtn(${idx},this)">
              <i class="fa-solid fa-magnifying-glass" style="font-size:11px;"></i>
            </button>
          </div>
        </div>
        <input type="hidden" class="item-name"  value="${escHtml(item.product_name||'')}" />
        <input type="hidden" class="item-code"  value="${escHtml(item.product_code||'')}" />
        <input type="hidden" class="item-rbox"  value="${escHtml(item.r_box||'')}" />
        <input type="hidden" class="item-stock" value="${escHtml(String(item.stock||''))}" />
        <input type="hidden" class="item-price" value="${escHtml(fmtPrice(item.product_price))}" />
      </td>
      <td>
        <select class="form-control form-select item-nhis item-nhis-sel"
                style="font-size:11px;padding:2px 4px;height:32px;width:100%;"
                onchange="calcItem(${idx})">${nhisOpts(nhisSt)}</select>
      </td>
      <td>
        <input type="text" inputmode="numeric" class="form-control item-ins-price"
               style="font-size:12px;text-align:right;padding:2px 7px;height:32px;width:100%;"
               value="${fmtPrice(item.insurance_price)}" placeholder="₩"
               oninput="calcItem(${idx})" />
      </td>
      <td>
        <input type="number" class="form-control item-qty"
               style="font-size:12px;text-align:center;padding:2px 4px;height:32px;width:100%;"
               value="${item.quantity||1}" min="1"
               oninput="calcItem(${idx})" />
      </td>
      <td style="text-align:right;color:var(--primary);white-space:nowrap;" class="item-nhis-amt">₩ ${nhisAmt}</td>
      <td style="text-align:right;white-space:nowrap;" class="item-copay">₩ ${copay}</td>
      <td style="text-align:center;">
        <button type="button" class="btn btn-sm"
                style="padding:0 6px;height:28px;background:none;border:1px solid var(--danger);color:var(--danger);"
                onclick="removeItem(${idx})" title="삭제">
          <i class="fa-solid fa-trash" style="font-size:11px;"></i>
        </button>
      </td>
    </tr>`;
  }).join('');

  const nhisTotal  = items.reduce((s, i) => s + Number(i.nhis_amount   || 0), 0);
  const copayTotal = items.reduce((s, i) => s + Number(i.patient_copay || 0), 0);

  el.innerHTML = `<table class="tab-tbl" style="table-layout:fixed;width:100%;">
    <colgroup>
      <col style="width:3%;">
      <col style="width:36%;">
      <col style="width:13%;">
      <col style="width:11%;">
      <col style="width:7%;">
      <col style="width:12%;">
      <col style="width:12%;">
      <col style="width:6%;">
    </colgroup>
    <thead><tr>
      <th style="text-align:center;">#</th>
      <th>제품명</th>
      <th>급여구분</th>
      <th style="text-align:right;">보험가</th>
      <th style="text-align:center;">수량</th>
      <th style="text-align:right;">급여금액</th>
      <th style="text-align:right;">환자부담</th>
      <th></th>
    </tr></thead>
    <tbody>${rows}</tbody>
    <tfoot><tr>
      <th colspan="5" style="text-align:right;background:var(--bg);">합계</th>
      <th style="text-align:right;color:var(--primary);background:var(--bg);">₩${nhisTotal.toLocaleString('ko-KR')}</th>
      <th style="text-align:right;background:var(--bg);">₩${copayTotal.toLocaleString('ko-KR')}</th>
      <th style="background:var(--bg);"></th>
    </tr></tfoot>
  </table>`;

  document.querySelectorAll('#items-table-container .item-ins-price').forEach(initPriceInput);
}

window.HELP_TOUR_STEPS = [
  {
    selector: '.tab-bar',
    title: '처방전 처리 탭',
    body: '처방전 검수 → 주문 제품 → 주문 연계 → 이력 순서로 진행합니다. 각 탭을 클릭해 이동하세요.'
  },
  {
    selector: '.tab-btn:nth-child(1)',
    title: '처방전 검수 탭',
    body: 'OCR이 자동 추출한 환자·병원·제품 정보를 확인하고 수정합니다. 완료 후 <b>검수 완료</b> 버튼을 클릭하세요.'
  },
  {
    selector: '.tab-btn:nth-child(2)',
    title: '주문 제품 탭',
    body: '<b>판매유형</b>(CE판매·개인판매·샘플판매)을 먼저 선택하고, 제품 검색 버튼으로 Todoworks에서 제품을 가져옵니다.'
  },
  {
    selector: '.tab-btn:nth-child(3)',
    title: '주문 연계 탭',
    body: '받는 사람과 배송 주소를 확인한 후 <b>주문 생성 및 연계</b> 버튼을 클릭합니다. Withworks 판매주문이 자동 생성됩니다.'
  },
  {
    selector: '#wwSoCard',
    title: 'Withworks 판매번호',
    body: '주문 생성 후 이 카드에 Withworks SO 번호가 표시됩니다. 연계 완료 여부를 여기서 확인하세요.'
  },
  {
    selector: '.tab-btn:nth-child(4)',
    title: '이력 탭',
    body: '처방전의 전체 처리 이력을 확인합니다. 업로드 → OCR → 검수 → 주문 생성 단계가 순서대로 표시됩니다.'
  },
];
</script>
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
  const RX_ID     = {{ $prescription->id }};          // 정수 id (payload용)
  const RX_NUMBER = @json($prescription->rx_number); // 라우트 경로용
  const NEW_ENTRY_URL = @json(route('prescriptions.create')); // 신규 등록 — 새 처방번호 발급
  const VA_ISSUE_URL_TPL = '/settlement/orders/__ID__/virtual-account';
  const SMS_SEND_URL = @json(route('prescriptions.smsSend', $prescription));

  // ── 판매 유형 ────────────────────────────────────────
  /* 위드웍스 code_list 를 따르는 값이라 모델 상수 하나만 보게 한다 —
     두 벌로 두면 새 유형이 생길 때 한쪽만 늘어난다. */
  const SO_TYPE_LABELS = @json(collect(\App\Models\Order::SO_TYPE_LABELS)->map(fn($v) => $v[0]));
  /* 화면에 골라져 있는 것을 그대로 쓴다.
     라디오는 서버에서 5001 이 선택된 채로 그려지는데, 사람이 손대지 않으면 onchange 가
     불리지 않아 여기 박아 둔 옛 기본값 1013 이 그대로 나갔다. 고를 수 있는 유형이
     5001 하나로 좁혀진 뒤로는 그 값이 늘 거절되어 주문이 아예 만들어지지 않았다
     (「The selected so type is invalid.」). */
  let currentSoType = document.querySelector('input[name="so_type_radio"]:checked')?.value
                      || @json(\App\Models\Order::saleSoTypes()[0]);

  // ── 기존 주문 상태 ───────────────────────────────────
  @if($prescription->order)
  @php
  $_orderData = ['id' => $prescription->order->id, 'order_number' => $prescription->order->order_number, 'withworks_so_no' => $prescription->order->withworks_so_no ?? '', 'so_type' => $prescription->order->so_type ?? '1013', 'shipping_address' => $prescription->order->shipping_address ?? ''];
  @endphp
  let existingOrder = @json($_orderData);
  let orderExists = true;
  @else
  let existingOrder = null;
  let orderExists = false;
  @endif

  function onSoTypeChange(val) {
    currentSoType = val;
    const badge = document.getElementById('soTypeBadge');
    if (badge) badge.innerHTML = `<span class="badge badge-primary" style="font-size:11px;">${val} · ${SO_TYPE_LABELS[val] ?? val}</span>`;
  }

  // 기존 주문의 so_type으로 라디오 초기화
  document.addEventListener('DOMContentLoaded', () => {
    if (existingOrder?.so_type) {
      currentSoType = existingOrder.so_type;
      const radio = document.querySelector(`input[name="so_type_radio"][value="${existingOrder.so_type}"]`);
      if (radio) { radio.checked = true; onSoTypeChange(existingOrder.so_type); }
    }
  });

  // ── 주소 검색 (카카오 우편번호 서비스) ───────────────────
  function openAddressSearch(postcodeId, addressId, detailId) {
    const W = 500, H = 600;
    const left = Math.floor((window.screen.width  - W) / 2);
    const top  = Math.floor((window.screen.height - H) / 2);
    new daum.Postcode({
      width:  W,
      height: H,
      oncomplete: function(data) {
        const addr = data.roadAddress || data.jibunAddress;
        document.getElementById(postcodeId).value = data.zonecode;
        document.getElementById(addressId).value  = addr;
        const detailEl = document.getElementById(detailId);
        if (detailEl) { detailEl.value = ''; detailEl.focus(); }
        // 처방 주소 검색 후 배송 주소 동일 체크 시 자동 반영
        if (postcodeId === 'f-postcode' && document.getElementById('sameShipping')?.checked) {
          syncShippingAddress(true);
        }
      }
    }).open({ left, top });
  }

  function clearShippingAddress() {
    ['shippingPostcode','shippingAddr','shippingAddrDetail','shippingRecipient'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }

  /* ── 주민번호 → 생년월일·만 나이 (실시간) ────────────────
     복호화하지 않는다. 앞 일곱 자리만으로 생년월일과 세기를 알 수 있고, 그 일곱 자리는
     마스킹에도 남아 있다. 담당자가 번호를 치는 중에도 다시 계산한다. */
  const MINOR_AGE = @json((int) config('delegation.minor_age', 19));

  function birthFromRrn(v) {
    const m = String(v ?? '').replace(/\s/g, '').match(/^(\d{2})(\d{2})(\d{2})-?(\d)/);
    if (!m) return null;
    const [, yy, mm, dd, g] = m;
    const century = { 1:1900, 2:1900, 5:1900, 6:1900, 3:2000, 4:2000, 7:2000, 8:2000, 9:1800, 0:1800 }[+g];
    if (!century) return null;
    const y = century + +yy, mo = +mm, d = +dd;
    const dt = new Date(y, mo - 1, d);
    // 2003-02-29 처럼 없는 날은 Date 가 다음 달로 넘겨 버린다 — 되돌려 확인한다
    if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
    return dt;
  }

  function ageOf(dt) {
    const now = new Date();
    let a = now.getFullYear() - dt.getFullYear();
    const before = now.getMonth() < dt.getMonth()
                || (now.getMonth() === dt.getMonth() && now.getDate() < dt.getDate());
    return before ? a - 1 : a;
  }

  const pad2 = (n) => String(n).padStart(2, '0');

  function rnRecalc() {
    const inp   = document.getElementById('f-resident');
    // 아직 치지 않았으면 저장된 마스킹(placeholder)으로 계산한다
    const src   = (inp?.value || '').trim() || (inp?.placeholder || '');
    const birth = birthFromRrn(src);
    const bEl   = document.getElementById('f-birth');
    const badge = document.getElementById('f-age-badge');
    const box   = document.getElementById('guardianBox');

    if (!birth) {
      if (bEl)  bEl.value = '';
      if (badge) badge.style.display = 'none';
      if (box)  box.style.display = 'none';
      return;
    }

    const ymd = `${birth.getFullYear()}-${pad2(birth.getMonth() + 1)}-${pad2(birth.getDate())}`;
    const age = ageOf(birth);
    const minor = age < MINOR_AGE;

    if (bEl) bEl.value = ymd;
    if (badge) {
      badge.textContent = `만 ${age}세` + (minor ? ' · 미성년' : '');
      badge.style.display    = '';
      badge.style.background = minor ? 'var(--alert-50)'  : 'var(--gray-100)';
      badge.style.color      = minor ? 'var(--alert-500)' : 'var(--gray-600)';
      badge.style.border     = '1px solid ' + (minor ? 'var(--alert-100)' : 'var(--gray-200)');
    }
    if (box) box.style.display = minor ? 'flex' : 'none';
  }

  /* 보호자 생년월일 — 숫자 여덟 자리를 치면 YYYY-MM-DD 로 맞춘다 */
  function formatBirthInput(el) {
    const d = el.value.replace(/\D/g, '').slice(0, 8);
    el.value = d.length > 6 ? `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6)}`
             : d.length > 4 ? `${d.slice(0,4)}-${d.slice(4)}`
             : d;
  }

  document.addEventListener('DOMContentLoaded', () => {
    rnRecalc();
    const gb = document.getElementById('f-guardian-birth');
    if (gb) gb.addEventListener('input', () => formatBirthInput(gb));
  });

  /* 주민등록번호는 쓰기 전용이다. 저장된 값을 복호화해 화면에 되돌려 주던
     '표시' 토글과 그것이 쓰던 코드는 걷어냈다. 있다는 사실은 placeholder 의
     마스킹으로 알리고, 새로 친 값만 저장으로 넘어간다. */

  // ── 재구매일 계산 ────────────────────────────────────────
  function calcRenewDate() {
    const dateVal = document.getElementById('f-date')?.value;
    const daysVal = parseInt(document.getElementById('f-days')?.value) || 0;
    const dispDate   = document.getElementById('disp-issued-date');
    const dispRenew  = document.getElementById('disp-renew-date');
    const hiddenDate = document.getElementById('f-repurchase-date');
    if (dispDate) dispDate.textContent = dateVal || '-';
    if (!dateVal || daysVal <= 0) {
      if (dispRenew)  dispRenew.textContent = '-';
      if (hiddenDate) hiddenDate.value = '';
      return;
    }
    const d = new Date(dateVal);
    d.setDate(d.getDate() + daysVal);
    const y   = d.getFullYear();
    const m   = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const result = `${y}-${m}-${day}`;
    if (dispRenew)  dispRenew.textContent = result;
    if (hiddenDate) hiddenDate.value = result;
  }

  // 처방일·처방기간 입력 시 재처방일 갱신
  // ── 상담 유형 변경 → 주문연계 탭 SO type 동기화 ────────
  function onCounselTypeChange(val) {
    const soMap = { '1013': '1013', '1016': '1016' };
    const soVal = soMap[val];
    if (soVal) {
      const radio = document.querySelector(`input[name="so-type"][value="${soVal}"]`);
      if (radio) { radio.checked = true; currentSoType = soVal; updateSoTypeSummary?.(); }
    }
  }

  // ── 상담 상태 변경 → 재상담 일자 활성화/비활성화 ────────
  function onCounselStatusChange(val) {
    const el    = document.getElementById('f-re-counsel-date');
    const wrap  = el?.closest('.rx-field-row');
    if (!el) return;
    const isRecounsel = val === '50';
    el.disabled = !isRecounsel;
    el.style.background = isRecounsel ? '' : 'var(--bg-secondary,var(--gray-50))';
    el.style.opacity    = isRecounsel ? '' : '.55';
    if (!isRecounsel) el.value = '';
    // 상담 상태 select 색상 표시
    const statusColors = { '02': '', '50': 'var(--warning)', '95': 'var(--success)', '99': 'var(--danger)' };
    const select = document.getElementById('f-counsel-status');
    if (select) select.style.color = statusColors[val] ?? '';
  }

  document.addEventListener('DOMContentLoaded', function () {
    const dateEl = document.getElementById('f-date');
    const daysEl = document.getElementById('f-days');
    if (dateEl) dateEl.addEventListener('change', calcRenewDate);
    if (daysEl) daysEl.addEventListener('input',  calcRenewDate);
    calcRenewDate(); // 초기 계산

    // 배송 주소 동일 초기 동기화 (기본 체크 상태)
    syncShippingAddress(true);

    // 상세 주소 직접 입력 시 실시간 반영
    const addrDetail = document.getElementById('f-address-detail');
    if (addrDetail) {
      addrDetail.addEventListener('input', function () {
        if (document.getElementById('sameShipping')?.checked) {
          document.getElementById('shippingAddrDetail').value = this.value;
        }
      });
    }

    // 전화번호 자동 포맷 (f-call-no)
    const callNoEl = document.getElementById('f-call-no');
    if (callNoEl) {
      callNoEl.addEventListener('input', function (e) {
        const pos  = e.target.selectionStart;
        const prev = e.target.value;
        e.target.value = formatPhone(e.target.value);
        const diff = e.target.value.length - prev.length;
        e.target.setSelectionRange(pos + diff, pos + diff);
      });
    }

    // 상담 상태 초기 색상 적용
    const initStatus = document.getElementById('f-counsel-status')?.value;
    if (initStatus) onCounselStatusChange(initStatus);

    // 상담 유형 초기 SO type 반영 (소프트 연동 — 기존 SO 선택 안 건드림)
    // (최초 로드 시에는 덮어쓰지 않음)
  });

  // ── 배송 주소 동일 체크 ───────────────────────────────────
  function syncShippingAddress(checked) {
    if (!checked) return;
    document.getElementById('shippingPostcode').value   = document.getElementById('f-postcode').value;
    document.getElementById('shippingAddr').value       = document.getElementById('f-address').value;
    document.getElementById('shippingAddrDetail').value = document.getElementById('f-address-detail').value;
    // 받는 사람이 비어있으면 환자명으로 채움
    const rec = document.getElementById('shippingRecipient');
    if (rec && !rec.value.trim()) {
      rec.value = document.getElementById('f-name')?.value?.trim() || '';
    }
  }

  /* ── 청구처 ────────────────────────────────────────────────
     요양비를 공단에 내느냐 지자체에 내느냐에 따라 이후 절차가 통째로 갈린다. 급여구분과
     주소로 짐작해 채워 주되 확정하지는 않는다 — 틀리면 엉뚱한 곳에 청구가 가므로 마지막
     판단은 담당자가 한다. 이미 담당자가 골라 둔 값은 덮어쓰지 않는다. */
  const CLAIM_BY_BENEFIT = {
    '기초': 'local',
    '일반': 'nhis', '차상위경감': 'nhis',
    '자동차보험': 'none', '산재': 'none',
  };

  /** 주소에서 관할 지자체를 뽑는다 (서버의 ClaimAgency 와 같은 규칙) */
  function localGovFromAddress(address) {
    const addr = (address || '').replace(/\s+/g, ' ').trim();
    const m = addr.match(/^(\S+?(?:특별자치시|특별자치도|특별시|광역시|도))\s*(.*)$/);
    if (!m) return '';

    const [, sido, rest] = m;
    if (sido.includes('특별자치시')) return sido;      // 세종 — 아래에 시군구가 없다

    // 특별시·광역시는 자치구(와 군)가 받고, 도는 시·군이 받는다.
    // 도 아래 시의 구는 행정구라 자치권이 없어 시까지만 본다(경기도 성남시 분당구 → 성남시).
    const isMetro = sido.endsWith('특별시') || sido.endsWith('광역시');
    const g = rest.match(isMetro ? /^(\S+?[구군])(?:\s|$)/ : /^(\S+?[시군])(?:\s|$)/);
    return g ? `${sido} ${g[1]}` : '';
  }

  function onClaimAgencyChange() {
    const agency = document.getElementById('f-claim-agency')?.value ?? '';
    const row    = document.getElementById('row-local-gov');
    const input  = document.getElementById('f-local-gov');
    if (!row || !input) return;

    row.style.display = agency === 'local' ? '' : 'none';

    // 지자체로 바뀌었는데 비어 있으면 주소에서 뽑아 제시한다
    if (agency === 'local' && !input.value.trim()) {
      input.value = localGovFromAddress(document.getElementById('f-address')?.value);
    }
  }

  function suggestClaimAgency() {
    const sel = document.getElementById('f-claim-agency');
    if (!sel || sel.value) return;                 // 담당자가 이미 골랐으면 두지 않는다

    const guess = CLAIM_BY_BENEFIT[document.getElementById('f-benefit-class')?.value ?? ''];
    if (!guess) return;

    sel.value = guess;
    onClaimAgencyChange();
  }

  document.getElementById('f-benefit-class')?.addEventListener('change', suggestClaimAgency);
  document.getElementById('f-claim-agency')?.addEventListener('change', onClaimAgencyChange);
  suggestClaimAgency();

  // ── 처방전 주소 가져오기 ──────────────────────────────────
  function fillFromPrescriptionAddress() {
    const postcode = document.getElementById('f-postcode')?.value?.trim() ?? '';
    const address  = document.getElementById('f-address')?.value?.trim()  ?? '';
    const detail   = document.getElementById('f-address-detail')?.value?.trim() ?? '';

    if (!address) {
      showToast('처방전 탭에 주소가 입력되어 있지 않습니다.', 'warning');
      return;
    }

    document.getElementById('shippingPostcode').value   = postcode;
    document.getElementById('shippingAddr').value       = address;
    document.getElementById('shippingAddrDetail').value = detail;

    // 배송 주소 동일 체크박스 해제 (직접 지정한 것이므로)
    const cb = document.getElementById('sameShipping');
    if (cb) cb.checked = false;

    showToast('처방전 주소를 배송 주소로 가져왔습니다.', 'success');
  }

  // ── 멀티 제품 아이템 상태 ────────────────────────────────
  const DEFAULT_QTY = 1;
  let items = @json($_itemsData);
  if (!items.length) {
      items = [{ product_name:'', product_code:'', quantity:DEFAULT_QTY, product_price:'', insurance_price:'', nhis_status:'eligible', nhis_amount:0, patient_copay:0 }];
  }
  let currentSearchIdx = 0;

  // ── 처방전 검수 아코디언 토글 ───────────────────────────
  /* 아코디언을 여닫으면 위쪽 높이가 달라져 화면 전체가 밀린다. 뷰어는 붙어 있어도
     기준이 되는 스크롤 위치가 통째로 움직이니 같이 튄 것처럼 보인다.
     기준 요소가 화면에서 제자리에 있도록, 달라진 만큼 스크롤을 되돌린다. */
  /* 뷰어가 화면에 붙기 시작하는 스크롤 위치. 이보다 위로 올라가면 뷰어가 제자리에서
     떨어져 같이 내려오므로, 보정할 때 이 아래로는 내려가지 않게 한다. */
  function viewerStickY() {
    const vc = document.getElementById('viewerCol');
    if (!vc || !vc.parentElement) return 0;
    const top = parseFloat(getComputedStyle(vc).top) || 0;
    return Math.max(0, vc.parentElement.getBoundingClientRect().top + window.scrollY - top);
  }

  function keepInPlace(anchor, mutate) {
    // 분할 상태에서는 오른쪽 열이 스크롤한다. 페이지는 움직이지 않는다.
    const col = document.getElementById('tabsCol');
    const inCol = col && col.parentElement.classList.contains('is-split') &&
                  col.scrollHeight > col.clientHeight + 1;
    const before = anchor.getBoundingClientRect().top;
    mutate();
    const delta = anchor.getBoundingClientRect().top - before;
    if (!delta) return;
    if (inCol) { col.scrollTop += delta; return; }
    // 위쪽 패널이 접히면 줄어든 높이가 지금 스크롤보다 커서 맨 위까지 튕겨 올라간다.
    // 그때는 뷰어가 붙어 있는 최소 위치까지만 올린다.
    const target = Math.max(window.scrollY + delta, viewerStickY());
    // html 에 scroll-behavior:smooth 가 걸려 있어, 보정까지 애니메이션으로 흐르면
    // 화면이 미끄러지듯 움직여 보인다. 즉시 옮긴다.
    window.scrollTo({ top: target, behavior: 'instant' });
  }

  /* 좌우 분할 — 페이지가 아니라 두 열이 각각 스크롤하게 만든다.
     .order-layout 이 화면 아래끝까지만 차지하면 문서가 화면을 넘지 않아
     페이지 스크롤이 사라지고, 뷰어는 어떤 조작에도 움직이지 않는다. */
  function sizeSplit() {
    const lay = document.querySelector('.order-layout');
    if (!lay) return;
    // 좁은 화면은 한 열로 쌓이므로 분할하지 않는다
    if (window.innerWidth <= 768) {
      lay.classList.remove('is-split');
      lay.style.height = '';
      return;
    }
    lay.classList.add('is-split');
    lay.style.height = 'auto';                       // 재기 전 초기화
    window.scrollTo({ top: 0, behavior: 'instant' }); // 분할하면 페이지는 스크롤되지 않는다

    // 아래쪽에 남겨야 할 여백 — 조상들의 padding·margin 을 더한다.
    // 좌표 차이로 재면 옆에 선 사이드바가 더 길 때 그 길이까지 딸려 들어온다.
    let below = parseFloat(getComputedStyle(lay).marginBottom) || 0;
    for (let p = lay.parentElement; p && p !== document.documentElement; p = p.parentElement) {
      const s = getComputedStyle(p);
      below += (parseFloat(s.paddingBottom)     || 0)
             + (parseFloat(s.marginBottom)      || 0)
             + (parseFloat(s.borderBottomWidth) || 0);
    }
    const top = lay.getBoundingClientRect().top;
    lay.style.height = Math.max(320, window.innerHeight - top - below) + 'px';
  }

  window.addEventListener('resize', sizeSplit);
  document.addEventListener('DOMContentLoaded', () => {
    sizeSplit();
    // 환자 정보 줄이 줄바꿈되면 위쪽 높이가 달라진다. 그때마다 다시 잰다.
    const bar = document.querySelector('.patient-info-bar') || document.querySelector('.pib-body');
    if (bar && window.ResizeObserver) new ResizeObserver(sizeSplit).observe(bar);
  });

  function toggleAcc(header) {
    keepInPlace(header, () => _toggleAcc(header));
  }

  function _toggleAcc(header) {
    const item   = header.closest('.rx-acc-item');
    const body   = header.nextElementSibling;
    const isOpen = body.style.display !== 'none';

    // 다른 패널 모두 닫기
    document.querySelectorAll('#tab-ocr .rx-acc-item').forEach(el => {
      const b = el.querySelector('.rx-acc-body');
      const i = el.querySelector('.rx-acc-icon');
      if (b && b !== body) {
        b.style.display = 'none';
        if (i) i.classList.remove('open');
        el.classList.remove('is-open');
      }
    });

    // 클릭한 패널 토글
    body.style.display = isOpen ? 'none' : 'block';
    const icon = header.querySelector('.rx-acc-icon');
    if (icon) icon.classList.toggle('open', !isOpen);
    item.classList.toggle('is-open', !isOpen);
    syncToggleAllBtn();
  }

  function toggleAllAcc() {
    const items   = document.querySelectorAll('#tab-ocr .rx-acc-item');
    if (!items.length) return;
    const allOpen = Array.from(items).every(el => el.classList.contains('is-open'));
    // 전체 닫기도 마찬가지다 — 첫 머리를 제자리에 붙들어 둔다
    keepInPlace(items[0], () => {
      items.forEach(el => {
        const body = el.querySelector('.rx-acc-body');
        const icon = el.querySelector('.rx-acc-icon');
        body.style.display = allOpen ? 'none' : 'block';
        if (icon) icon.classList.toggle('open', !allOpen);
        el.classList.toggle('is-open', !allOpen);
      });
      syncToggleAllBtn();
    });
  }

  function syncToggleAllBtn() {
    const bodies   = document.querySelectorAll('#tab-ocr .rx-acc-body');
    const allOpen  = Array.from(bodies).every(b => b.style.display !== 'none');
    const iconEl   = document.getElementById('btnAccToggleAllIcon');
    const labelEl  = document.getElementById('btnAccToggleAllLabel');
    if (!iconEl) return;
    if (allOpen) {
      iconEl.className  = 'fa-solid fa-angles-up';
      labelEl.textContent = '전체 닫기';
    } else {
      iconEl.className  = 'fa-solid fa-angles-down';
      labelEl.textContent = '전체 열기';
    }
  }

  // ── 미저장 감지 ────────────────────────────────────────
  let _ocrDirty     = false;
  let _productDirty = false;
  let _orderDirty   = false;

  function markOcrDirty()     { _ocrDirty     = true; }
  function markProductDirty() { _productDirty = true; }
  function markOrderDirty()   { _orderDirty   = true; }
  function clearAllDirty()    { _ocrDirty = false; _productDirty = false; _orderDirty = false; }
  function isAnyDirty()       { return _ocrDirty || _productDirty || _orderDirty; }

  function _dirtyLabel() {
    const parts = [];
    if (_ocrDirty)     parts.push('처방전 검수');
    if (_productDirty) parts.push('주문 제품');
    if (_orderDirty)   parts.push('주문 연계');
    return parts.join(' · ');
  }

  function _activeSaveFn() {
    const oc = document.querySelector('.tab-btn.active')?.getAttribute('onclick') ?? '';
    if (oc.includes('tab-order')) return _saveOrderForNav;
    return saveOCR;
  }

  document.addEventListener('DOMContentLoaded', () => {
    // 탭별 입력 감지
    const panes = { 'tab-ocr': markOcrDirty, 'tab-product': markProductDirty, 'tab-order': markOrderDirty };
    Object.entries(panes).forEach(([id, fn]) => {
      const el = document.getElementById(id);
      if (el) { el.addEventListener('input', fn); el.addEventListener('change', fn); }
    });

    // 페이지 이탈 링크 클릭 가로채기 (사이드바·상단 버튼 등)
    document.addEventListener('click', e => {
      if (!isAnyDirty()) return;
      const link = e.target.closest('a[href]');
      if (!link) return;
      const href = link.getAttribute('href');
      if (!href || href === '#' || href.startsWith('javascript:') || link.target === '_blank') return;
      e.preventDefault();
      showUnsavedDlg(null, null, _dirtyLabel(), _activeSaveFn(), href);
    }, true);
  });

  // 브라우저 뒤로가기 · 새로고침 · 탭 닫기
  window.addEventListener('beforeunload', e => {
    if (isAnyDirty()) { e.preventDefault(); e.returnValue = ''; }
  });

  async function _saveOrderForNav() {
    if (orderExists && existingOrder) {
      const btn = document.getElementById('btnUpdateOrder');
      if (btn) await updateOrder({ target: btn });
    } else {
      const btn = document.getElementById('btnCreateOrder');
      if (btn) await createOrder({ target: btn });
    }
  }

  // ── 탭 전환 ────────────────────────────────────────────
  function _doSwitchTab(btn, tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => {
      p.classList.remove('active', 'ocr-split-top', 'ocr-split-bottom');
    });
    btn.classList.add('active');

    const isTableView = () => document.getElementById('tabsCol')?.classList.contains('tab-view-table');

    // 시안(148:2628·2827·3046)의 검수 탭에는 아코디언 카드만 있다.
    // 예전에는 처방 제품을 아래에 붙여 함께 보였는데, 시안에 없어 각자 탭으로 되돌린다.
    if (tabId === 'tab-ocr') {
      document.getElementById('tab-ocr').classList.add('active');
      return;
    }

    document.getElementById(tabId).classList.add('active');
    if (tabId === 'tab-order')   { recalcAllItems(); renderOrderSummary(); }
    if (tabId === 'tab-product') {
      if (isTableView()) renderItemsTable(); else renderItems();
    }
  }

  function switchTab(btn, tabId) {
    const activeBtn     = document.querySelector('.tab-btn.active');
    const activeOnclick = activeBtn?.getAttribute('onclick') ?? '';
    const fromOcr     = activeOnclick.includes('tab-ocr')    && tabId !== 'tab-ocr';
    const fromProduct = activeOnclick.includes('tab-product') && tabId !== 'tab-product'
                        && (tabId === 'tab-order' || tabId === 'tab-history');
    const fromOrder   = activeOnclick.includes('tab-order')   && tabId !== 'tab-order';

    if (fromOcr && _ocrDirty) {
      showUnsavedDlg(btn, tabId, '처방전 검수', saveOCR);
      return;
    }
    if (fromProduct && _productDirty) {
      showUnsavedDlg(btn, tabId, '주문 제품', saveOCR);
      return;
    }
    if (fromOrder && _orderDirty) {
      showUnsavedDlg(btn, tabId, '주문 연계', _saveOrderForNav);
      return;
    }
    _doSwitchTab(btn, tabId);
  }

  // btn+tabId: 탭 전환 모드 / url: 페이지 이탈 모드
  function showUnsavedDlg(btn, tabId, tabLabel, saveFn, url = null) {
    const dlg = document.getElementById('unsavedDlg');
    dlg.querySelector('p:last-of-type').innerHTML =
      `<b>${tabLabel}</b> 탭에 저장되지 않은 내용이 있습니다.<br>` +
      (url ? '페이지를 이동하기 전에 저장하시겠습니까?' : '탭을 이동하기 전에 저장하시겠습니까?');
    dlg.style.display = 'flex';

    const proceed = () => url ? (clearAllDirty(), window.location.href = url) : _doSwitchTab(btn, tabId);

    const onCancel  = () => { dlg.style.display = 'none'; cleanup(); };
    const onDiscard = () => { clearAllDirty(); dlg.style.display = 'none'; proceed(); cleanup(); };
    const onSave    = async () => {
      dlg.style.display = 'none';
      cleanup();
      if (saveFn) await saveFn();
      proceed();
    };

    const btnCancel  = document.getElementById('unsavedDlgCancel');
    const btnDiscard = document.getElementById('unsavedDlgDiscard');
    const btnSave    = document.getElementById('unsavedDlgSave');

    btnCancel.addEventListener('click',  onCancel,  { once: true });
    btnDiscard.addEventListener('click', onDiscard, { once: true });
    btnSave.addEventListener('click',    onSave,    { once: true });

    function cleanup() {
      btnCancel.removeEventListener('click',  onCancel);
      btnDiscard.removeEventListener('click', onDiscard);
      btnSave.removeEventListener('click',    onSave);
    }
  }

  // ── 이미지 조작 ────────────────────────────────────────
  let zoomLevel = 100, rotation = 0;
  let _tx = 0, _ty = 0;           // 드래그 누적 이동량 (px)
  let _drag = false, _sx = 0, _sy = 0; // 드래그 시작점

  function zoomIn()    { zoomLevel = Math.min(zoomLevel+100, 500); applyTransform(); }
  function zoomOut()   { zoomLevel = Math.max(zoomLevel-100, 100); applyTransform(); }
  function rotateImg() { rotation  = (rotation+90)%360;           applyTransform(); }
  function resetImg()  { zoomLevel = 100; rotation = 0; _tx = 0; _ty = 0; applyTransform(); }

  function applyTransform() {
    document.getElementById('zoomLabel').textContent = zoomLevel + '%';
    const img = document.getElementById('prescCanvas');
    if (img) img.style.transform = `translate(${_tx}px,${_ty}px) scale(${zoomLevel/100}) rotate(${rotation}deg)`;
  }

  // 드래그 이벤트 초기화 (DOMContentLoaded 이후 실행)
  document.addEventListener('DOMContentLoaded', function () {
    const img = document.getElementById('prescCanvas');
    if (!img) return;

    img.addEventListener('mousedown', function (e) {
      if (e.button !== 0) return;
      _drag = true;
      _sx   = e.clientX - _tx;
      _sy   = e.clientY - _ty;
      img.style.cursor = 'grabbing';
      e.preventDefault();
    });

    document.addEventListener('mousemove', function (e) {
      if (!_drag) return;
      _tx = e.clientX - _sx;
      _ty = e.clientY - _sy;
      applyTransform();
    });

    document.addEventListener('mouseup', function () {
      if (!_drag) return;
      _drag = false;
      const img = document.getElementById('prescCanvas');
      if (img) img.style.cursor = 'grab';
    });

    // 더블클릭으로 위치 초기화
    img.addEventListener('dblclick', function () {
      _tx = 0; _ty = 0; applyTransform();
    });

    // 스크롤(휠)로 확대/축소 — 커서 위치 기준
    const canvas = document.getElementById('imgCanvas');
    if (canvas) {
      canvas.addEventListener('wheel', function (e) {
        e.preventDefault();
        const step    = 30;
        const prevZoom = zoomLevel;
        if (e.deltaY < 0) {
          zoomLevel = Math.min(zoomLevel + step, 500);
        } else {
          zoomLevel = Math.max(zoomLevel - step, 20);
        }
        // 커서 위치 기준으로 이동량 보정
        const rect    = canvas.getBoundingClientRect();
        const cx      = e.clientX - rect.left - rect.width  / 2;
        const cy      = e.clientY - rect.top  - rect.height / 2;
        const scale   = zoomLevel / prevZoom;
        _tx = cx + (_tx - cx) * scale;
        _ty = cy + (_ty - cy) * scale;
        applyTransform();
      }, { passive: false });
    }
  });

  // ── 멀티 제품: 아이템 HTML 템플릿 ───────────────────────
  function itemHtml(idx, item) {
    const displayName = item.product_name
        ? escHtml(item.product_name) + (item.product_code ? ` (${escHtml(item.product_code)})` : '')
        : '';
    const nhisAmt  = Number(item.nhis_amount  || 0).toLocaleString();
    const copay    = Number(item.patient_copay || 0).toLocaleString();
    const nhisSt   = item.nhis_status || 'eligible';
    const insBase  = Number(item.insurance_price || item.product_price || 0);
    const totalAmt = Math.round(insBase * Number(item.quantity || 1)).toLocaleString('ko-KR');
    const nhisOpts = [['eligible','급여(90%)'],['ineligible','비급여'],['partial','일부(50%)']].map(
        ([v,l]) => `<option value="${v}"${nhisSt===v?' selected':''}>${l}</option>`
    ).join('');
    // 시안 148:3105 Frame 48101492 — 행 카드 1132×118.
    // 가로 2칸: 내용(pad 12 · 세로 gap 12) + 오른쪽 삭제칸 64(#F9FAFC).
    // 열 폭 281/141×5, 라벨 13/500 #474D54 와 입력 사이 8. 규격은 CSS(#items-container)에 있다.
    return `<div class="item-card" data-idx="${idx}">
      <div class="item-card-main">
      <div class="item-row">
        <div class="item-inline-field" style="flex:2 1 236px;min-width:0;">
          <div class="item-field-label">제품명</div>
          <div class="item-name-row">
            {{-- 치는 대로 목록이 따라 내려오던 것은 걷어냈다. 두 글자에도 창고를 찾으러
                 가느라 느렸고, 아래로 펼쳐진 목록이 다음 칸을 가렸다.
                 이제 검색 단추로 창을 열어 세 글자 이상에서 찾는다. --}}
            <div class="pac-wrap" style="position:relative;">
              <input type="text" class="form-control item-display" id="pac-input-${idx}"
                     style="width:100%;font-size:13px;height:32px;" autocomplete="off"
                     placeholder="제품명 또는 코드 입력 후 검색"
                     value="${displayName}"
                     onkeydown="if(event.key==='Enter'){event.preventDefault();pacSearchBtn(${idx});}" />
            </div>
            <button type="button" class="btn btn-sm item-search-btn" title="제품 검색"
                    onmousedown="event.preventDefault()"
                    onclick="pacSearchBtn(${idx},this)">
              <i class="fa-solid fa-magnifying-glass"></i> 검색
            </button>
          </div>
        </div>
        <input type="hidden" class="item-name"  value="${escHtml(item.product_name||'')}" />
        <input type="hidden" class="item-code"  value="${escHtml(item.product_code||'')}" />
        <input type="hidden" class="item-rbox"  value="${escHtml(item.r_box||'')}" />
        <input type="hidden" class="item-stock" value="${escHtml(String(item.stock||''))}" />
        <div class="item-inline-field">
          <div class="item-field-label">급여 구분</div>
          <select class="form-control form-select item-nhis item-nhis-sel"
                  onchange="calcItem(${idx})">${nhisOpts}</select>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">수량</div>
          <input type="number" class="form-control item-qty" value="${item.quantity||1}" min="1"
                 oninput="calcItem(${idx})" style="font-size:13px;width:100%;height:32px;" />
        </div>
        <div class="item-inline-field" id="item-rbox-field-${idx}" style="display:${item.r_box?'flex':'none'};">
          <div class="item-field-label">R-Box</div>
          <div class="item-rbox-display" style="height:32px;display:flex;align-items:center;font-size:12px;font-weight:700;color:var(--primary);white-space:nowrap;">${escHtml(item.r_box||'')}</div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">소비자가</div>
          <div class="item-money-row">
            <input type="text" inputmode="numeric" class="form-control item-price" value="${fmtPrice(item.product_price)}"
                   placeholder="소비자가 입력" oninput="calcItem(${idx})" style="font-size:13px;height:32px;" />
            <span class="item-won">₩</span>
          </div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">보험가</div>
          <div class="item-money-row">
            <input type="text" inputmode="numeric" class="form-control item-ins-price" value="${fmtPrice(item.insurance_price)}"
                   placeholder="보험가 입력" oninput="calcItem(${idx})" style="font-size:13px;height:32px;" />
            <span class="item-won">₩</span>
          </div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">총 금액</div>
          <div class="item-total-amt">₩ ${totalAmt}</div>
        </div>
      </div>
      <div class="item-meta" id="item-meta-${idx}" style="display:${item.stock?'flex':'none'};align-items:center;gap:6px;padding:4px 2px 2px;flex-wrap:wrap;">
        ${item.stock  ? `<span style="background:var(--primary-50);color:var(--primary);padding:1px 8px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fa-solid fa-layer-group" style="font-size:9px;margin-right:3px;"></i>재고: ${Number(item.stock).toLocaleString()}</span>` : ''}
      </div>
      <div class="item-summary">
        <span class="item-sum-grp">
          {{-- 화면에서 NHIS·건보 표현은 걷어냈다 — 「급여」로 적는다 --}}
          <span class="item-sum-badge">급여</span>
          <b class="item-nhis-amt">₩ ${nhisAmt}</b>
        </span>
        <span class="item-sum-div"></span>
        <span class="item-sum-grp">
          <span class="item-sum-badge is-copay">환자부담</span>
          <b class="item-copay">₩ ${copay}</b>
        </span>
      </div>
      </div>
      <div class="item-del-col">
        <button type="button" class="btn btn-sm item-del-btn" onclick="removeItem(${idx})" title="삭제">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
    </div>`;
  }

  function renderItems() {
    document.getElementById('items-container').innerHTML =
        items.map((item, idx) => itemHtml(idx, item)).join('');
    document.querySelectorAll('.item-price, .item-ins-price').forEach(initPriceInput);
    calcTotals();
  }

  function addItem() {
    items.push({ product_name:'', product_code:'', quantity:DEFAULT_QTY, product_price:'', insurance_price:'', nhis_status:'eligible', nhis_amount:0, patient_copay:0 });
    if (document.getElementById('tabsCol')?.classList.contains('tab-view-table')) {
      renderItemsTable();
    } else {
      renderItems();
    }
    // 추가된 아이템 스크롤
    const cards = document.querySelectorAll('.item-card');
    cards[cards.length-1]?.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }

  function removeItem(idx) {
    items.splice(idx, 1);
    if (!items.length) {
      items = [{ product_name:'', product_code:'', quantity:DEFAULT_QTY, product_price:'', insurance_price:'', nhis_status:'eligible', nhis_amount:0, patient_copay:0, r_box:'', stock:'' }];
    }
    renderItems();
    if (document.getElementById('tabsCol')?.classList.contains('tab-view-table')) renderItemsTable();
  }

  /* ══ 환자 조회 → 과거 상담이력 가져오기 ══════════════════════ */
  const PL_SEARCH_URL   = @json(route('prescriptions.patientSearch'));
  const PL_COUNSEL_TPL  = @json(url('prescriptions/patients/__ID__/counselings'));
  let _plCounselings = [];   // 선택한 환자의 상담이력
  let _plSelected    = -1;   // 선택한 상담이력 인덱스

  function openPatientLookup() {
    document.getElementById('patientLookupModal').classList.add('show');
    const q = document.getElementById('plQuery');
    // 현재 화면의 환자명을 기본 검색어로 넣어준다
    if (!q.value.trim()) q.value = document.getElementById('f-name')?.value?.trim() || '';
    q.focus();
    q.select();
    if (q.value.trim().length >= 2) plSearch();
  }

  function closePatientLookup() {
    document.getElementById('patientLookupModal').classList.remove('show');
  }

  // 엔터로 검색
  document.getElementById('plQuery')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); plSearch(); }
  });

  async function plSearch() {
    const q    = document.getElementById('plQuery').value.trim();
    const btn  = document.getElementById('plSearchBtn');
    const list = document.getElementById('plPatientList');

    if (q.length < 2) { showToast('두 글자 이상 입력해 주세요.', 'warning'); return; }

    btn.disabled = true;
    list.innerHTML = '<div style="padding:24px 14px;text-align:center;font-size:12px;color:var(--text-muted);">'
      + '<i class="fa-solid fa-spinner fa-spin"></i> 조회 중...</div>';
    _plResetCounselPane('환자를 선택하세요');

    try {
      const res = await fetch(PL_SEARCH_URL + '?q=' + encodeURIComponent(q), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const d = await res.json();
      const rows = d.patients || [];
      document.getElementById('plPatientCount').textContent = rows.length ? rows.length + '명' : '';

      if (!rows.length) {
        list.innerHTML = '<div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--text-muted);">'
          + (d.message ? _pcEsc(d.message) : '검색 결과가 없습니다.') + '</div>';
        return;
      }

      list.innerHTML = rows.map((p, i) => `
        <div class="pl-patient-item" data-idx="${i}" onclick="plSelectPatient(${p.id}, this)"
             style="padding:10px 14px;border-bottom:1px solid var(--border-light);cursor:pointer;border-left:3px solid transparent;">
          <div style="font-size:13px;font-weight:700;color:var(--text-primary);">${_pcEsc(p.name)}</div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
            ${_pcEsc(p.resident_no)} · ${_pcEsc(p.mobile)}
          </div>
          <div style="font-size:10px;margin-top:3px;color:${p.counseling_count ? 'var(--primary)' : 'var(--text-muted)'};">
            상담이력 ${p.counseling_count}건
          </div>
        </div>`).join('');
    } catch (e) {
      list.innerHTML = '<div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--danger);">조회 중 오류가 발생했습니다.</div>';
    } finally {
      btn.disabled = false;
    }
  }

  function _plResetCounselPane(msg) {
    _plCounselings = [];
    _plSelected    = -1;
    document.getElementById('plCounselCount').textContent = '';
    document.getElementById('plCounselList').innerHTML =
      `<div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--text-muted);">${_pcEsc(msg)}</div>`;
    document.getElementById('plDetailBody').innerHTML =
      '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;min-height:200px;color:var(--text-muted);gap:10px;">'
      + '<i class="fa-solid fa-hand-pointer" style="font-size:26px;opacity:.35;"></i>'
      + '<span style="font-size:13px;">가져올 상담이력을 선택하세요</span></div>';
    document.getElementById('plImportBtn').disabled = true;
  }

  async function plSelectPatient(patientId, el) {
    document.querySelectorAll('.pl-patient-item').forEach(function (n) {
      n.style.background = ''; n.style.borderLeftColor = 'transparent';
    });
    if (el) { el.style.background = 'var(--primary-light)'; el.style.borderLeftColor = 'var(--primary)'; }

    const list = document.getElementById('plCounselList');
    _plResetCounselPane('불러오는 중...');
    list.innerHTML = '<div style="padding:24px 14px;text-align:center;font-size:12px;color:var(--text-muted);">'
      + '<i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';

    try {
      const res = await fetch(PL_COUNSEL_TPL.replace('__ID__', patientId), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const d = await res.json();
      _plCounselings = d.counselings || [];
      document.getElementById('plCounselCount').textContent = _plCounselings.length ? _plCounselings.length + '건' : '';

      if (!_plCounselings.length) {
        list.innerHTML = '<div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--text-muted);">상담이력이 없습니다.</div>';
        return;
      }

      list.innerHTML = _plCounselings.map((c, i) => {
        const st = c.status ?? '';
        return `
        <div class="pl-counsel-item" data-idx="${i}" onclick="plSelectCounsel(${i})"
             style="padding:10px 13px;border-bottom:1px solid var(--border-light);cursor:pointer;border-left:3px solid transparent;">
          <div style="font-size:12px;font-weight:700;color:var(--primary);word-break:break-all;">${_pcEsc(c.counselling_no ?? '-')}</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:3px;">
            <span style="font-size:10px;color:var(--text-muted);">
              <i class="fa-regular fa-calendar" style="font-size:9px;"></i> ${_pcEsc(c.counsel_date || c.reg_date || '-')}
            </span>
            ${st ? `<span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:${_PC_STAT_COLOR[st] ?? 'var(--gray-300)'};color:#fff;flex-shrink:0;">${_pcEsc(_PC_STAT_MAP[st] ?? st)}</span>` : ''}
          </div>
          <div style="font-size:10px;color:var(--text-muted);margin-top:3px;">${_pcEsc(c.rx_number ?? '')}</div>
        </div>`;
      }).join('');
    } catch (e) {
      list.innerHTML = '<div style="padding:28px 14px;text-align:center;font-size:12px;color:var(--danger);">상담이력을 불러오지 못했습니다.</div>';
    }
  }

  function plSelectCounsel(idx) {
    const d = _plCounselings[idx];
    if (!d) return;
    _plSelected = idx;

    document.querySelectorAll('.pl-counsel-item').forEach(function (n, i) {
      n.style.background = i === idx ? 'var(--primary-light)' : '';
      n.style.borderLeftColor = i === idx ? 'var(--primary)' : 'transparent';
    });

    const itemsHtml = (d.items && d.items.length)
      ? d.items.map(it => `
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px dashed var(--border-light);font-size:12px;">
            <span>${_pcEsc(it.product_name ?? '-')}${it.product_code ? ` <span style="color:var(--text-muted);font-size:10px;">[${_pcEsc(it.product_code)}]</span>` : ''}</span>
            <span style="font-weight:700;color:var(--primary);flex-shrink:0;">×${it.quantity ?? 1}</span>
          </div>`).join('')
      : '<div style="font-size:12px;color:var(--text-muted);padding:6px 0;">등록된 제품이 없습니다.</div>';

    document.getElementById('plDetailBody').innerHTML = `
      <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:2px;">${_pcEsc(d.counselling_no ?? '-')}</div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:14px;">${_pcEsc(d.rx_number ?? '')} · ${_pcEsc(d.reg_date ?? '')}</div>

      <div class="pc-field-grid">
        ${_pcFR('환자명',       d.patient_name_ocr)}
        ${_pcFR('연락처',       _pcPhone(d.mobile_ocr || d.call_no))}
        ${_pcFR('주민번호',     d.resident_no_masked)}
        ${_pcFR('보호자명',     d.udf24)}
        ${_pcFR('병원명',       d.hospital_name)}
        ${_pcFR('담당의사',     d.doctor_name || d.udf15)}
        ${_pcFR('처방전발행일', d.issued_date || d.udf12)}
        ${_pcFR('상담 유형',    _PC_TYPE_MAP[d.type??''] || d.type)}
        ${_pcFR('재구매 가능일', d.repurchase_date)}
        ${_pcFR('주소', [d.postcode, d.address_ocr, d.address_detail].filter(Boolean).join(' '), true)}
      </div>

      <div style="margin-top:14px;font-size:11px;font-weight:500;color:var(--text-muted);">처방 제품</div>
      <div style="margin-top:4px;">${itemsHtml}</div>

      <div style="margin-top:14px;padding:10px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;">
        <div style="font-size:10px;font-weight:700;color:var(--gray-600);margin-bottom:5px;"><i class="fa-solid fa-note-sticky"></i> 상담 메모</div>
        <div style="font-size:12px;line-height:1.8;white-space:pre-wrap;color:${d.contents ? 'var(--text-primary)' : 'var(--text-muted)'};">${d.contents ? _pcEsc(d.contents) : '(메모 없음)'}</div>
      </div>`;

    document.getElementById('plImportBtn').disabled = false;
  }

  /* 선택한 상담이력을 검수 화면 입력값으로 채운다.
     값이 있는 항목만 덮어써서, 이력에 없는 필드의 기존 입력은 보존한다. */
  async function plImportSelected() {
    const d = _plCounselings[_plSelected];
    if (!d) { showToast('가져올 상담이력을 선택하세요.', 'warning'); return; }

    const withItems = document.getElementById('plWithItems').checked;
    const proceed = await ceConfirm(
      `상담이력 ${d.counselling_no ?? ''} 의 내용을 검수 화면으로 가져옵니다.\n`
      + (withItems ? '처방 제품도 함께 교체됩니다.\n' : '')
      + '\n계속하시겠습니까?',
      { title: '상담이력 가져오기', confirmText: '가져오기' }
    );
    if (!proceed) return;

    // 상담이력 필드 → 검수 화면 입력 필드 대응
    const MAP = {
      'f-name':            d.patient_name_ocr,
      'f-mobile':          d.mobile_ocr || d.call_no,
      'f-guardian':        d.udf24,
      'f-diverticulums':   d.diverticulums,
      'f-postcode':        d.postcode,
      'f-address':         d.address_ocr,
      'f-address-detail':  d.address_detail,
      'f-hospital':        d.hospital_name,
      'f-hospital-code':   d.erp_cd9,
      'f-doctor':          d.doctor_name || d.udf15,
      'f-date':            d.issued_date || d.udf12,
      'f-rx-period':       d.udf13,
      'f-rx-end-date':     d.udf14,
      'f-counselling-no':  d.counselling_no,
      'f-counsel-date':    d.counsel_date || d.reg_date,
      'f-counsel-type':    d.type,
      'f-acc-add-type':    d.acc_add_type,
      'f-counsel-status':  d.status,
      'f-counsel-memo':    d.contents,
      'f-re-counsel-date': d.re_counsel_date,
      'f-repurchase-date': d.repurchase_date,
    };

    let filled = 0;
    for (const [id, val] of Object.entries(MAP)) {
      const el = document.getElementById(id);
      if (!el) continue;
      const v = (val === null || val === undefined) ? '' : String(val).trim();
      if (v === '') continue;                       // 빈 값으로는 덮어쓰지 않는다
      el.value = v;
      el.dispatchEvent(new Event('change', { bubbles: true }));
      filled++;
    }

    // 주민번호는 마스킹뿐이라 값으로 넣으면 그대로 저장된다. 있다는 표시만 한다.
    const _rn = document.getElementById('f-resident');
    if (_rn && d.resident_no_masked) _rn.placeholder = d.resident_no_masked;

    // 처방 제품 교체
    if (withItems && d.items && d.items.length) {
      items = d.items.map(it => ({
        product_name:    it.product_name ?? '',
        product_code:    it.product_code ?? '',
        quantity:        it.quantity ?? DEFAULT_QTY,
        product_price:   it.product_price ?? '',
        insurance_price: it.insurance_price ?? '',
        // 과거 이력은 Y/N 로 저장된 경우가 있어 검수 화면 값으로 변환
        nhis_status:     (it.nhis_status === 'Y') ? 'eligible'
                        : (it.nhis_status === 'N') ? 'ineligible'
                        : (it.nhis_status || 'eligible'),
        nhis_amount:     it.nhis_amount ?? 0,
        patient_copay:   it.patient_copay ?? 0,
      }));
      const isTable = !!document.getElementById('tabsCol')?.classList.contains('tab-view-table');
      if (isTable) renderItemsTable(); else renderItems();
      calcTotals();
      markProductDirty();
    }

    // 테이블뷰 미러 갱신
    if (document.getElementById('tabsCol')?.classList.contains('tab-view-table')) {
      syncCardToTable();
      syncOrderTabToTable();
    }

    markOcrDirty();
    closePatientLookup();
    showToast(`상담이력을 가져왔습니다. (${filled}개 항목${withItems && d.items?.length ? ` · 제품 ${d.items.length}건` : ''})`, 'success');
  }

  /* ── 신규 등록: 새 처방번호로 새 건 시작 ────────────────────
     예전에는 현재 처방전 위에서 화면 입력만 비웠다. 그러면 저장할 때
     현재 건이 덮어써져서, 사용자가 기대하는 '신규 등록' 과 어긋났다.
     지금은 빈 초안을 새로 잡아 그 화면으로 이동한다 — 처방번호부터
     새로 발급되고 보던 건은 손대지 않는다. */
  async function resetReviewScreen() {
    const ok = await ceConfirm(
      '새 처방전을 등록합니다. 새 처방번호가 발급되며,\n'
      + '지금 보고 있는 처방전(' + RX_NUMBER + ')은 그대로 남습니다.\n\n'
      + '※ 저장하지 않은 입력 내용은 사라집니다.\n\n'
      + '계속하시겠습니까?',
      { title: '신규 등록', tone: 'warning', confirmText: '신규 등록' }
    );
    if (!ok) return;

    // 이동 확인을 방금 받았으므로 '미저장' 경고(링크 가로채기 · beforeunload)는 끈다
    clearAllDirty();
    location.href = NEW_ENTRY_URL;
  }

  /* ── 전체 아이템 재계산 (각 아이템의 개별 급여 구분 사용) ── */
  function recalcAllItems() {
    document.querySelectorAll('.item-card').forEach(card => {
      const idx = parseInt(card.dataset.idx);
      calcItem(idx);
    });
  }

  /* ── 가격 필드 천단위 콤마 헬퍼 ── */
  function parsePrice(val) {
    return parseFloat(String(val).replace(/,/g, '')) || 0;
  }
  function fmtPrice(val) {
    const n = parsePrice(val);
    return n > 0 ? Math.round(n).toLocaleString('ko-KR') : '';
  }
  function initPriceInput(input) {
    input.addEventListener('focus', function() {
      const raw = parsePrice(this.value);
      this.value = raw > 0 ? raw : '';
    });
    input.addEventListener('blur', function() {
      const raw = parsePrice(this.value);
      this.value = fmtPrice(raw);
    });
  }

  function calcItem(idx) {
    const card = document.querySelector(`.item-card[data-idx="${idx}"]`);
    if (!card) return;
    const price    = parsePrice(card.querySelector('.item-price').value);
    const insPrice = parsePrice(card.querySelector('.item-ins-price').value);
    const nhisSel  = card.querySelector('.item-nhis')?.value || 'eligible';
    const qty      = parseInt(card.querySelector('.item-qty').value)          || 1;
    const base     = insPrice > 0 ? insPrice : price;
    const rate     = nhisSel === 'eligible' ? 0.9 : (nhisSel === 'partial' ? 0.5 : 0);
    const nhisAmt  = Math.round(base * rate * qty);
    const copay    = Math.round(base * qty) - nhisAmt;

    const insBase = insPrice > 0 ? insPrice : price;
    card.querySelector('.item-nhis-amt').textContent  = '₩ ' + nhisAmt.toLocaleString('ko-KR');
    card.querySelector('.item-copay').textContent     = '₩ ' + copay.toLocaleString('ko-KR');
    const totalEl = card.querySelector('.item-total-amt');
    if (totalEl) totalEl.textContent = '₩ ' + Math.round(insBase * qty).toLocaleString('ko-KR');

    // items 배열 동기화
    items[idx] = {
      product_name:    card.querySelector('.item-name').value,
      product_code:    card.querySelector('.item-code').value,
      r_box:           card.querySelector('.item-rbox')?.value  || '',
      stock:           card.querySelector('.item-stock')?.value || '',
      quantity:        qty,
      product_price:   price    || null,
      insurance_price: insPrice || null,
      nhis_status:     nhisSel,
      nhis_amount:     nhisAmt,
      patient_copay:   copay,
    };
    calcTotals();
  }

  /* ── items 배열 기준으로 합계 계산 (DOM 파싱 없이) ── */
  function calcTotals() {
    const totalNhis  = items.reduce((s, i) => s + (Number(i.nhis_amount)   || 0), 0);
    const totalCopay = items.reduce((s, i) => s + (Number(i.patient_copay) || 0), 0);
    const fmtNhis    = Math.round(totalNhis).toLocaleString('ko-KR');
    const fmtCopay   = Math.round(totalCopay).toLocaleString('ko-KR');
    const el = id => document.getElementById(id);
    const shipping = {{ $prescription->order?->shipping_fee ?? 3000 }};
    const vaTotal  = Math.round(totalCopay) + shipping;
    if (el('summary-nhis'))  el('summary-nhis').textContent  = '₩ ' + fmtNhis;
    if (el('summary-copay')) el('summary-copay').textContent = '₩ ' + fmtCopay;
    if (el('costNhisAmt'))   el('costNhisAmt').textContent   = '₩ ' + fmtNhis;
    if (el('costNhis'))      el('costNhis').textContent      = '₩ ' + fmtCopay;
    if (el('costTotal'))     el('costTotal').textContent     = '₩ ' + (Math.round(totalCopay) + 3000).toLocaleString('ko-KR');
    if (el('vaTotalAmt'))    el('vaTotalAmt').textContent    = '₩' + vaTotal.toLocaleString('ko-KR');
    if (el('vaCopayAmt'))    el('vaCopayAmt').textContent    = '본인부담 ₩' + Math.round(totalCopay).toLocaleString('ko-KR');
    const fmtDeposit = vaTotal.toLocaleString('ko-KR');
    if (el('kakaoCopayAmt'))   el('kakaoCopayAmt').textContent   = fmtCopay + '원';
    if (el('kakaoDepositAmt')) el('kakaoDepositAmt').textContent = fmtDeposit + '원';
    if (el('smsCopayAmt'))     el('smsCopayAmt').textContent     = fmtCopay + '원';
    if (el('smsDepositAmt'))   el('smsDepositAmt').textContent   = fmtDeposit + '원';
    SMS_PLACEHOLDERS['#{본인부담금}'] = fmtCopay;
    SMS_PLACEHOLDERS['#{금액}']       = fmtDeposit;
  }

  function renderOrderSummary() {
    const el = document.getElementById('order-items-summary');
    if (!el) return;
    const validItems = items.filter(i => i.product_name);
    if (!validItems.length) {
      el.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:8px 0;">주문 제품 탭에서 제품을 먼저 선택해주세요.</div>';
      return;
    }
    el.innerHTML = validItems.map(item => {
      const base     = (item.insurance_price || item.product_price || 0);
      const total    = Math.round(base * item.quantity).toLocaleString('ko-KR');
      const nhisAmt  = Math.round(item.nhis_amount   || 0);
      const copay    = Math.round(item.patient_copay || 0);
      const nhisSt   = item.nhis_status || 'eligible';
      const nhisLabel = nhisSt === 'eligible' ? '급여(90%)' : (nhisSt === 'partial' ? '일부(50%)' : '비급여');
      const nhisColor = nhisSt === 'ineligible' ? 'var(--text-muted)' : 'var(--primary)';
      const nhisInfo  = nhisSt === 'ineligible'
          ? `<span style="font-size:11px;color:var(--text-muted);">${nhisLabel}</span>`
          : `<span style="font-size:11px;color:${nhisColor};">${nhisLabel} &minus;₩${nhisAmt.toLocaleString('ko-KR')}</span>
             <span style="font-size:11px;color:var(--text-secondary);">→ 환자 ₩${copay.toLocaleString('ko-KR')}</span>`;
      return `<div class="cost-row" style="align-items:flex-start;">
        <div style="display:flex;flex-direction:column;gap:2px;font-size:12px;">
          <span>${escHtml(item.product_name)}${item.product_code?` <span style="color:var(--text-muted);font-size:11px;">(${escHtml(item.product_code)})</span>`:''} × ${item.quantity}</span>
          <div style="display:flex;gap:8px;">${nhisInfo}</div>
        </div>
        <span class="cost-val" style="font-size:12px;white-space:nowrap;">₩ ${total}</span>
      </div>`;
    }).join('');
  }

  // ── 수량 조절 (order tab 호환) ──────────────────────────
  function changeQty(delta) {
    const el = document.getElementById('orderQty');
    if (!el) return;
    let val = Math.max(1, parseInt(el.value) + delta);
    el.value = val;
  }
  function selectProduct(el) {
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
  }


  // ── 위임동의 시작일 변경 → 종료일 자동 = 시작일 + 1개월 ──
  function autoAgreeEnd(startVal) {
    if (!startVal) return;
    const d = new Date(startVal);
    d.setMonth(d.getMonth() + 1);
    const yyyy = d.getFullYear();
    const mm   = String(d.getMonth() + 1).padStart(2, '0');
    const dd   = String(d.getDate()).padStart(2, '0');
    document.getElementById('f-nhis-agree-end').value = `${yyyy}-${mm}-${dd}`;
  }

  // ── 종료일·다음재구매일 자동계산 ──
  function calcNextRepurchase(showWarn = false) {
    const dateVal   = document.getElementById('f-date')?.value;
    const periodVal = parseInt(document.getElementById('f-rx-period')?.value ?? '');
    if (!dateVal || !periodVal || periodVal < 1) {
      if (showWarn) showToast('처방전발행일과 처방기간(일)을 먼저 입력해주세요.', 'warning');
      return;
    }
    const fmt = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

    // 종료일 = 처방전발행일 + 처방기간
    const endDate = new Date(dateVal);
    endDate.setDate(endDate.getDate() + periodVal);
    document.getElementById('f-rx-end-date').value = fmt(endDate);

    // 다음재구매일 = 처방전발행일 + 처방기간 + 1
    const nextDate = new Date(dateVal);
    nextDate.setDate(nextDate.getDate() + periodVal + 1);
    document.getElementById('f-next-repurchase').value = fmt(nextDate);
  }

  // ── OCR 저장 ─────────────────────────────────────────
  let _saving = false;
  function syncRxRef() {
    const daily = document.getElementById('f-daily')?.value;
    const days  = document.getElementById('f-days')?.value;
    const total = document.getElementById('f-total')?.value;
    const refDaily = document.getElementById('rx-ref-daily');
    const refDays  = document.getElementById('rx-ref-days');
    const refTotal = document.getElementById('rx-ref-total');
    if (refDaily) refDaily.textContent = daily || '-';
    if (refDays)  refDays.textContent  = days  || '-';
    if (refTotal) refTotal.textContent = total || '-';
  }

  async function saveOCR() {
    if (_saving) return;        // 중복 요청 방지
    const name = document.getElementById('f-name').value.trim();
    const hosp = document.getElementById('f-hospital').value.trim();
    if (!name || !hosp) {
      showToast('환자명, 병원명은 필수 항목입니다.', 'warning');
      return;
    }

    // DOM에서 items 최신 상태 수집
    const itemsPayload = Array.from(document.querySelectorAll('.item-card')).map(card => {
      const idx      = parseInt(card.dataset.idx);
      const stored   = items[idx] || {};
      const price    = parsePrice(card.querySelector('.item-price').value)    || null;
      const insPrice = parsePrice(card.querySelector('.item-ins-price').value) || null;
      const qty      = parseInt(card.querySelector('.item-qty').value)          || 1;
      const nhisSel  = card.querySelector('.item-nhis')?.value || 'eligible';
      const base     = (insPrice || price || 0);
      const rate     = nhisSel === 'eligible' ? 0.9 : (nhisSel === 'partial' ? 0.5 : 0);
      const nhisAmt  = Math.round(base * rate * qty);
      const copay    = Math.round(base * qty) - nhisAmt;
      return {
        product_name:    card.querySelector('.item-name').value.trim() || null,
        product_code:    card.querySelector('.item-code').value.trim() || null,
        quantity:        qty,
        product_price:   price    ? Math.round(price)    : null,
        insurance_price: insPrice ? Math.round(insPrice) : null,
        nhis_status:     nhisSel,
        nhis_amount:     nhisAmt,
        patient_copay:   copay,
      };
    }).filter(i => i.product_name);

    _saving = true;
    const saveBtns = document.querySelectorAll('[onclick="saveOCR()"]');
    saveBtns.forEach(btn => BtnState.loading(btn, '저장 중...'));

    const intOrNull = id => { const v = document.getElementById(id)?.value; return (v !== '' && v != null) ? parseInt(v, 10) : null; };
    const strOrNull = id => { const v = document.getElementById(id)?.value?.trim(); return v || null; };

    const payload = {
      // ── 환자 정보 ────────────────────────────────────────
      patient_name_ocr: name,
      resident_no_ocr:  strOrNull('f-resident'),   // 비우면 서버가 기존 값을 지키다
      mobile_ocr:       strOrNull('f-mobile'),
      address_ocr:      strOrNull('f-address'),
      postcode:         strOrNull('f-postcode'),
      address_detail:   strOrNull('f-address-detail'),
      guardian:         strOrNull('f-guardian'),
      // 미성년자 — 법정대리인. 위임 서명 화면에 그대로 보인다.
      guardian_name:     strOrNull('f-guardian-name'),
      guardian_relation: strOrNull('f-guardian-relation'),
      guardian_birth:    strOrNull('f-guardian-birth'),
      guardian_phone:    strOrNull('f-guardian-phone'),
      // 시안 148:2708 로 새로 생긴 항목들
      mobile2:          strOrNull('f-mobile2'),
      email:            strOrNull('f-email'),
      nhis_reg_date:    strOrNull('f-nhis-reg-date'),
      nhis_renew_due:   strOrNull('f-nhis-renew-due'),
      basic_reeval:     strOrNull('f-basic-reeval'),
      basic_reeval_due: strOrNull('f-basic-reeval-due'),
      // 시안 148:2827 로 새로 생긴 항목
      dealer_type:      strOrNull('f-dealer-type'),
      pay_date:         strOrNull('f-pay-date'),
      buy_date:         strOrNull('f-buy-date'),
      // 시안 148:3046 (추가정보 카드)
      inmarket_due:       strOrNull('f-inmarket-due'),
      last_confirmed_qty: strOrNull('f-last-qty'),
      daily_use_qty:      strOrNull('f-daily-use-qty'),
      diverticulums:    strOrNull('f-diverticulums'),
      // ── 병원·처방 정보 ────────────────────────────────────
      hospital_name:    hosp,
      hospital_code:    strOrNull('f-hospital-code'),
      doctor_name:      strOrNull('f-doctor'),
      issued_date:      strOrNull('f-date'),
      repurchase_date:  strOrNull('f-repurchase-date'),
      rx_period:        intOrNull('f-rx-period'),
      rx_end_date:      strOrNull('f-rx-end-date'),
      diagnosis_date:   strOrNull('f-diagnosis-date'),
      // ── 처방 수량·상병 ─────────────────────────────────────
      disease_name:     strOrNull('f-disease'),
      disease_code:     strOrNull('f-disease-code'),
      disease_class:    strOrNull('f-disease-class'),
      sb_sci:           strOrNull('f-sb-sci'),
      uro_date:         strOrNull('f-uro-date'),
      daily_count:      intOrNull('f-daily'),
      total_days:       intOrNull('f-days'),
      total_count:      intOrNull('f-total'),
      // ── 급여·보험 정보 ─────────────────────────────────────
      benefit_class:    strOrNull('f-benefit-class'),
      claim_agency:     strOrNull('f-claim-agency'),
      // 지자체가 아니면 관할 지자체는 값이 있을 이유가 없다
      local_gov:        (document.getElementById('f-claim-agency')?.value === 'local')
                          ? strOrNull('f-local-gov') : null,
      nhis_reg_status:  strOrNull('f-nhis-status'),
      nhis_renew:       strOrNull('f-nhis-renew'),
      nhis_agree_start: strOrNull('f-nhis-agree-start'),
      nhis_agree_end:   strOrNull('f-nhis-agree-end'),
      // ── 거래·주문 정보 ─────────────────────────────────────
      purchase_type:    strOrNull('f-purchase-type'),
      five_program:     strOrNull('f-five-program'),
      deduction:        strOrNull('f-deduction'),
      cash_receipt_no:  strOrNull('f-cash-receipt'),
      order_manager:    strOrNull('f-order-manager'),
      next_repurchase:  strOrNull('f-next-repurchase'),
      special_case:     strOrNull('f-special-case'),
      reason:           strOrNull('f-reason'),
      // ── 추가 정보 ──────────────────────────────────────────
      new_patient_date: strOrNull('f-new-patient-date'),
      five_110days:     strOrNull('f-five'),
      // ── 상담 기본 정보 ─────────────────────────────────────
      counsel_no:           strOrNull('f-counselling-no'),
      counsel_date:         strOrNull('f-counsel-date'),
      counsel_type:         strOrNull('f-counsel-type'),
      counsel_acc_add_type: strOrNull('f-acc-add-type'),
      counsel_status:       strOrNull('f-counsel-status'),
      counsel_call_no:      strOrNull('f-call-no'),
      counsel_re_date:      strOrNull('f-re-counsel-date'),
      counsel_memo:         strOrNull('f-counsel-memo'),
      // ── 제품 ──────────────────────────────────────────────
      items:            itemsPayload,
    };

    try {
      const res = await apiRequest(`/prescriptions/${RX_NUMBER}/ocr`, 'POST', payload);
      if (res.success) {
        clearAllDirty();
        showToast('저장되었습니다.', 'success');
        saveBtns.forEach(btn => BtnState.success(btn, '저장 완료'));
        setTimeout(() => saveBtns.forEach(btn => BtnState.reset(btn)), 2500);
        if (res.items && res.items.length) {
          items = res.items.map((item, idx) => ({
            ...item,
            r_box: items[idx]?.r_box || '',
            stock: items[idx]?.stock || '',
          }));
          renderItems();
          recalcAllItems();
        }
        syncRxRef();
      } else {
        const msgs = res.errors ? Object.values(res.errors).flat() : [res.message || '저장 실패'];
        msgs.forEach(m => showToast(m, 'danger'));
        saveBtns.forEach(btn => BtnState.error(btn, '저장 실패'));
        setTimeout(() => saveBtns.forEach(btn => BtnState.reset(btn)), 2500);
      }
    } catch (e) {
      saveBtns.forEach(btn => BtnState.error(btn, '오류'));
      setTimeout(() => saveBtns.forEach(btn => BtnState.reset(btn)), 2500);
    } finally {
      _saving = false;
    }
  }

  // ── 원본 복원 ─────────────────────────────────────────
  function resetOCR() {
    document.getElementById('f-name').value         = @json($prescription->patient_name_ocr);
    // 주민번호는 되돌릴 원본을 화면이 들고 있지 않다. 친 값만 지운다 —
    // 그러면 저장할 때 값이 비어 서버가 기존 값을 그대로 둔다.
    document.getElementById('f-resident').value = '';
    document.getElementById('f-mobile').value       = @json($prescription->mobile_ocr ?? $prescription->patient?->mobile ?? '');
    document.getElementById('f-postcode').value       = @json($prescription->postcode ?? '');
    document.getElementById('f-address').value        = @json($prescription->address_ocr ?? $prescription->patient?->address ?? '');
    document.getElementById('f-address-detail').value = @json($prescription->address_detail ?? '');
    document.getElementById('f-hospital').value     = @json($prescription->hospital_name);
    document.getElementById('f-doctor').value       = @json($prescription->doctor_name);
    document.getElementById('f-date').value         = @json($prescription->issued_date?->format('Y-m-d'));
    document.getElementById('f-disease').value      = @json($prescription->disease_name);
    document.getElementById('f-disease-code').value = @json($prescription->disease_code);
    document.getElementById('f-daily').value        = @json($prescription->daily_count);
    document.getElementById('f-days').value         = @json($prescription->total_days);
    document.getElementById('f-total').value        = @json($prescription->total_count);
    items = @json($_itemsData);
    if (!items.length) items = [{ product_name:'', product_code:'', quantity:DEFAULT_QTY, product_price:'', insurance_price:'', nhis_status:'eligible', nhis_amount:0, patient_copay:0 }];
    renderItems();
    calcRenewDate();
    showToast('원본 데이터로 복원되었습니다.', 'info');
  }

  // ── 승인 요청 ─────────────────────────────────────────
  function approveRx() { document.getElementById('approveModal').classList.add('show'); }

  async function confirmApprove(btn) {
    BtnState.loading(btn, '처리 중...');
    const memo = document.getElementById('approveMemo').value;
    try {
      const res = await apiRequest(`/prescriptions/${RX_NUMBER}/approve`, 'POST', { memo });
      if (res.success) {
        BtnState.success(btn, '승인 완료');
        showToast('✅ 처방전이 승인되었습니다.', 'success');
        setTimeout(() => { closeModal('approveModal'); location.reload(); }, 1200);
      } else {
        BtnState.error(btn, '승인 실패');
        showToast(res.message || '승인 실패', 'danger');
        setTimeout(() => BtnState.reset(btn), 2500);
      }
    } catch (e) {
      BtnState.error(btn, '오류');
      setTimeout(() => BtnState.reset(btn), 2500);
    }
  }

  // ── 주문 생성 및 Withworks 연계 ──────────────────────────
  async function createOrder(e) {
    const btn = e.target;
    BtnState.loading(btn, '주문 생성 중...');

    const validItems = items.filter(i => i.product_name);
    const totalCopay = validItems.reduce((s, i) => s + (i.patient_copay || 0), 0);
    const totalNhis  = validItems.reduce((s, i) => s + (i.nhis_amount  || 0), 0);

    /* 기본주소와 상세주소를 따로 쥔다.
       우리 주문에는 합쳐 넣는 것이 읽기 좋지만, 위드웍스는 둘을 따로 받아 스스로
       합친다(기본 + 상세 + 전화). 합친 것을 기본주소 자리에 넣으면 상세가 두 번
       붙는다 — 실제로 「…테헤란로 152 강남파이낸스센터 강남파이낸스센터(010-…)」로
       들어가 있었다. */
    const shippingBase   = document.getElementById('shippingAddr')?.value?.trim() || '';
    const shippingDetail = document.getElementById('shippingAddrDetail')?.value?.trim() || '';
    const shippingAddress = shippingBase
      ? (shippingDetail ? shippingBase + ' ' + shippingDetail : shippingBase)
      : null;

    const shippingRecipient = document.getElementById('shippingRecipient')?.value?.trim() || null;

    const localPayload = {
      prescription_id:    parseInt(RX_ID),
      items:              validItems,
      total_nhis:         totalNhis,
      patient_copay:      totalCopay,
      shipping_postcode:  document.getElementById('shippingPostcode')?.value?.trim() || null,
      shipping_address:   shippingAddress,
      shipping_recipient: shippingRecipient,
      so_type:            currentSoType,
    };

    // ① 로컬 주문 생성
    const res = await apiRequest('/orders', 'POST', localPayload);
    if (!res.success) {
      BtnState.error(btn, '생성 실패');
      showToast(res.message || '주문 생성 실패', 'danger');
      return;
    }

    // ② Withworks 판매주문 연계
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Withworks 연계 중...';

    const wwItems = validItems.map(i => ({
      item_code:  i.product_code || '',
      qty:        i.quantity     || 1,
      unit_price: Math.round(i.insurance_price || i.product_price || 0),
    })).filter(i => i.item_code);

    let soNo = null;
    let wwSuccess = false;
    let wwMessage = '';

    if (wwItems.length > 0) {
      const wwPayload = {
        order_number:      res.order_number,
        items:             wwItems,
        // 위드웍스는 기본과 상세를 따로 받아 스스로 합친다 — 합쳐 보내면 두 번 붙는다
        shipping_address:        shippingBase || null,
        shipping_address_detail: shippingDetail,
        recipient_name:    shippingRecipient,
        delivery_date:     res.estimated_delivery || null,
        so_type:           currentSoType,
      };
      const wwRes = await apiRequest(`/prescriptions/${RX_NUMBER}/withworks-order`, 'POST', wwPayload);
      wwSuccess = wwRes.success ?? false;
      soNo      = wwRes.so_no  ?? null;
      wwMessage = wwRes.message ?? '';
    } else {
      wwMessage = '제품 코드가 없어 Withworks 연계를 건너뜁니다.';
    }

    BtnState.reset(btn);

    // ③ 결과 모달
    const wwBadge = wwSuccess
      ? `<span style="display:inline-block;background:var(--primary-50);color:var(--primary);border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;">연계 완료</span>`
      : `<span style="display:inline-block;background:var(--gray-100);color:var(--gray-600);border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;">연계 미완료</span>`;

    document.getElementById('orderModalBody').innerHTML = `
      <div style="font-size:52px;color:var(--primary);margin-bottom:12px;">✅</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:4px;">주문 생성 완료</div>
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
        CE 주문번호: <b style="color:var(--primary);">${res.order_number}</b>
      </div>
      <div style="background:var(--bg);border-radius:var(--radius);padding:14px;text-align:left;font-size:12px;line-height:2.2;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="color:var(--text-muted);">Withworks SO</span>
          <span>${wwBadge}</span>
        </div>
        ${soNo ? `<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">SO 번호</span><b style="color:var(--primary);">${soNo}</b></div>` : ''}
        ${!wwSuccess && wwMessage ? `<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">사유</span><span style="color:var(--warning);font-size:11px;">${wwMessage}</span></div>` : ''}
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">제품 수</span><b>${localPayload.items?.length ?? 0}종</b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">환자 부담금</span><b style="color:var(--primary);">₩ ${(totalCopay + 3000).toLocaleString()}</b></div>
        <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">예상 배송일</span><b>${res.estimated_delivery ?? '-'}</b></div>
      </div>`;
    document.getElementById('orderModal').classList.add('show');

    // ④ 버튼 상태 → 수정/삭제로 전환 + Col 3 업데이트
    if (res.success) {
      _orderDirty = false;
      existingOrder = {
        id:              res.order_id ?? null,
        order_number:    res.order_number,
        withworks_so_no: soNo ?? '',
        so_type:         currentSoType,
        shipping_address: shippingAddress,
      };
      orderExists   = true;
      // 현금영수증 발행에 필요한 주문 ID·금액 동기화
      _ORDER_ID     = res.order_id ?? _ORDER_ID;
      _ORDER_TOTAL  = res.total_amount ?? (totalCopay + 3000) ?? _ORDER_TOTAL;
      _PATIENT_COPAY = res.patient_copay ?? totalCopay ?? _PATIENT_COPAY;
      switchToEditDeleteButtons(res.order_number, soNo);
      updateWwSoDisplay(res.order_number, soNo, currentSoType);
      injectVaButton(res.order_id);
    }
  }

  /** Col 3의 Withworks 판매번호 카드 + 워크플로우 실시간 업데이트 */
  function updateWwSoDisplay(orderNum, soNo, soType) {
    // ── 환자 정보 바 Withworks 판매번호 표시 ──────────────
    const card    = document.getElementById('wwSoCard');
    const content = document.getElementById('wwSoContent');

    if (card) {
      card.style.borderColor = soNo ? 'var(--primary)' : 'var(--border)';
      card.style.background  = soNo ? 'var(--primary-light)' : 'var(--bg-card)';
    }
    if (content) {
      const typeLabels = { '1013': ['CE 판매','primary'], '1016': ['개인판매','info'], '1022': ['샘플판매','warning'] };
      const tl = typeLabels[soType] || [soType || '-', 'secondary'];
      if (soNo) {
        content.innerHTML = `<span style="font-family:monospace;font-weight:700;color:var(--primary);font-size:11px;">${soNo}</span><span class="badge badge-${tl[1]}" style="font-size:10px;margin-left:4px;">${tl[0]}</span>`;
      } else {
        content.innerHTML = `<span style="font-size:11px;color:var(--warning);"><i class="fa-solid fa-triangle-exclamation"></i> 연계 실패</span>`;
      }
    }

    // ── 워크플로우 "주문 생성" 스텝 (사이드바 + 이력 탭) ─────────────────
    const soTimeHtml = `${orderNum}${soNo ? `<span style="color:var(--primary);font-family:monospace;display:block;">SO: ${soNo}</span>` : ''}`;

    // 사이드바
    const wsIcon = document.getElementById('wsOrderIcon');
    const wsTime = document.getElementById('wsOrderTime');
    if (wsIcon) wsIcon.className = 'ws-icon done';
    if (wsTime) wsTime.innerHTML = soTimeHtml;
    const wsStep = document.getElementById('wsOrderStep');
    if (wsStep && !wsStep.querySelector('.ws-arrow')) {
      const chk = document.createElement('i');
      chk.className = 'fa-solid fa-check ws-arrow';
      chk.style.color = 'var(--primary)';
      wsStep.appendChild(chk);
    }

    // 이력 탭
    const histIcon = document.getElementById('histOrderIcon');
    const histTime = document.getElementById('histOrderTime');
    if (histIcon) histIcon.className = 'ws-icon done';
    if (histTime) histTime.innerHTML = soTimeHtml;
    const histStep = document.getElementById('histOrderStep');
    if (histStep && !histStep.querySelector('.ws-arrow')) {
      const chk2 = document.createElement('i');
      chk2.className = 'fa-solid fa-check ws-arrow';
      chk2.style.color = 'var(--primary)';
      histStep.appendChild(chk2);
    }
  }

  /** 주문 생성 후 가상계좌 버튼 동적 주입 */
  function injectVaButton(orderId) {
    const wrap = document.getElementById('vaButtonWrap');
    if (!wrap || wrap.querySelector('#btnVaTrigger, #vaResultBadge')) return;
    // 자리는 감춰 두었다. 여기서 다시 보이게 하면 감춘 뜻이 없어진다.
    wrap.style.display = 'none';
    const vaUrl  = VA_ISSUE_URL_TPL.replace('__ID__', orderId);
    wrap.innerHTML = `
      <div id="vaNotIssuedWrap" style="position:relative;">
        <button type="button" id="btnVaTrigger" class="pib-btn pib-btn-primary"
                data-url="${vaUrl}"
                data-sms-url="${SMS_SEND_URL}"
                onclick="toggleVaPopover(event)"
                style="white-space:nowrap;cursor:pointer;">
          <i class="fa-solid fa-building-columns" style="font-size:11px;"></i> 가상계좌 발급
        </button>
        <div id="vaResultBadge" style="display:none;align-items:center;height:32px;gap:4px;padding:4px 9px;background:var(--gray-100);border:1px solid var(--gray-300);border-radius:var(--radius);font-size:11px;white-space:nowrap;">
          <i class="fa-solid fa-building-columns" style="color:var(--gray-700);font-size:10px;"></i>
          <span id="vaResultBadgeText" style="font-weight:700;color:var(--gray-700);">-</span>
        </div>
      </div>`;
  }

  /** 주문 생성 후 버튼 영역을 수정/삭제 형태로 교체 */
  function switchToEditDeleteButtons(orderNum, soNo) {
    const area = document.getElementById('orderActionArea');
    if (!area) return;
    area.innerHTML = `
      <div style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:10px 14px;margin-bottom:10px;font-size:12px;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-circle-check" style="color:var(--primary);font-size:15px;"></i>
        <div>
          <b style="color:var(--primary);">주문 생성 완료</b>
          <span style="color:var(--text-muted);margin-left:8px;">${orderNum}</span>
          ${soNo ? `<span style="color:var(--primary);margin-left:6px;font-family:monospace;font-size:11px;">SO: ${soNo}</span>` : ''}
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-primary flex-1" id="btnUpdateOrder" onclick="updateOrder(event)">
          <i class="fa-solid fa-pen-to-square"></i> 주문 수정
        </button>
        <button class="btn btn-danger" id="btnDeleteOrder" onclick="confirmDeleteOrder(event)"
                style="flex-shrink:0;padding:0 18px;">
          <i class="fa-solid fa-trash-can"></i> 삭제
        </button>
      </div>`;
  }

  // ── 주문 수정 ─────────────────────────────────────────
  async function updateOrder(e) {
    if (!existingOrder) { showToast('주문 정보를 찾을 수 없습니다.', 'danger'); return; }
    const btn = e.target.closest('button');
    BtnState.loading(btn, '수정 중...');

    const validItems = items.filter(i => i.product_name);
    const totalCopay = validItems.reduce((s, i) => s + (i.patient_copay || 0), 0);
    const totalNhis  = validItems.reduce((s, i) => s + (i.nhis_amount  || 0), 0);

    /* 기본주소와 상세주소를 따로 쥔다.
       우리 주문에는 합쳐 넣는 것이 읽기 좋지만, 위드웍스는 둘을 따로 받아 스스로
       합친다(기본 + 상세 + 전화). 합친 것을 기본주소 자리에 넣으면 상세가 두 번
       붙는다 — 실제로 「…테헤란로 152 강남파이낸스센터 강남파이낸스센터(010-…)」로
       들어가 있었다. */
    const shippingBase   = document.getElementById('shippingAddr')?.value?.trim() || '';
    const shippingDetail = document.getElementById('shippingAddrDetail')?.value?.trim() || '';
    const shippingAddress = shippingBase
      ? (shippingDetail ? shippingBase + ' ' + shippingDetail : shippingBase)
      : null;

    const shippingRecipient = document.getElementById('shippingRecipient')?.value?.trim() || null;

    // ① 로컬 주문 수정
    const localRes = await apiRequest(`/orders/${existingOrder.id}`, 'PUT', {
      items:              validItems,
      total_nhis:         totalNhis,
      patient_copay:      totalCopay,
      shipping_address:   shippingAddress,
      shipping_recipient: shippingRecipient,
      so_type:            currentSoType,
    });

    if (!localRes.success) {
      BtnState.error(btn, '수정 실패');
      showToast(localRes.message || '주문 수정 실패', 'danger');
      return;
    }

    // ② Withworks 수정
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Withworks 수정 중...';
    const wwItems = validItems.map(i => ({
      item_code:  i.product_code || '',
      qty:        i.quantity     || 1,
      unit_price: Math.round(i.insurance_price || i.product_price || 0),
    })).filter(i => i.item_code);

    let wwSuccess = false, wwMessage = '';
    if (wwItems.length > 0) {
      const wwRes = await apiRequest(`/prescriptions/${RX_NUMBER}/withworks-order`, 'PUT', {
        order_number:     existingOrder.order_number,
        items:            wwItems,
        // 위드웍스는 기본과 상세를 따로 받아 스스로 합친다 — 합쳐 보내면 두 번 붙는다
        shipping_address:        shippingBase || null,
        shipping_address_detail: shippingDetail,
        recipient_name:   shippingRecipient,
        so_type:          currentSoType,
      });
      wwSuccess = wwRes.success ?? false;
      wwMessage = wwRes.message ?? '';
    }

    BtnState.reset(btn);

    existingOrder.so_type         = currentSoType;
    existingOrder.shipping_address = shippingAddress;
    // 수정된 금액 동기화
    _ORDER_TOTAL   = localRes.total_amount ?? (totalCopay + 3000) ?? _ORDER_TOTAL;
    _PATIENT_COPAY = totalCopay ?? _PATIENT_COPAY;

    // Col 3 판매번호 카드 업데이트 (수정 후 SO 번호는 동일 유지, 타입만 갱신)
    updateWwSoDisplay(existingOrder.order_number, existingOrder.withworks_so_no, currentSoType);

    _orderDirty = false;
    showToast(
      wwSuccess
        ? '✅ 주문이 수정되었습니다. (Withworks 동기화 완료)'
        : (wwMessage ? `주문 수정 완료 (Withworks: ${wwMessage})` : '주문 수정 완료 (Withworks 연계 실패)'),
      wwSuccess ? 'success' : 'warning'
    );
  }

  // ── 주문 삭제 확인 ────────────────────────────────────
  function confirmDeleteOrder(e) {
    if (!existingOrder) return;
    document.getElementById('deleteOrderNum').textContent  = existingOrder.order_number;
    document.getElementById('deleteOrderSoNo').textContent = existingOrder.withworks_so_no || '연계 없음';
    document.getElementById('deleteOrderModal').classList.add('show');
  }

  async function executeDeleteOrder(e) {
    if (!existingOrder) return;
    const btn = e.target.closest('button');
    BtnState.loading(btn, '삭제 중...');

    // ① Withworks 삭제
    let wwSuccess = true;
    if (existingOrder.withworks_so_no) {
      const wwRes = await apiRequest(`/prescriptions/${RX_NUMBER}/withworks-order`, 'DELETE', {
        order_number: existingOrder.order_number,
      });
      wwSuccess = wwRes.success ?? false;
      if (!wwSuccess) {
        BtnState.error(btn, '삭제 실패');
        showToast('Withworks 삭제 실패: ' + (wwRes.message || ''), 'danger');
        return;
      }
    }

    // ② 로컬 주문 삭제
    const localRes = await apiRequest(`/orders/${existingOrder.id}`, 'DELETE', {});
    BtnState.reset(btn);

    closeModal('deleteOrderModal');

    if (!localRes.success) {
      showToast(localRes.message || '주문 삭제 실패', 'danger');
      return;
    }

    // ③ UI 초기화 → 생성 버튼으로 복원
    existingOrder  = null;
    orderExists    = false;
    _ORDER_ID      = 0;
    _ORDER_TOTAL   = 0;
    _PATIENT_COPAY = 0;
    document.getElementById('orderActionArea').innerHTML = `
      <button class="btn btn-primary w-full" id="btnCreateOrder" onclick="createOrder(event)">
        <i class="fa-solid fa-cart-plus"></i> 주문 생성 및 연계
      </button>`;

    // 환자 정보 바 Withworks 판매번호 초기화
    const card = document.getElementById('wwSoCard');
    const content = document.getElementById('wwSoContent');
    if (card) { card.style.borderColor = 'var(--border)'; card.style.background = 'var(--bg-card)'; }
    if (content) content.innerHTML = `<span id="wwSoBadge" style="color:var(--text-muted);font-size:11px;">미연계</span>`;

    // 워크플로우 스텝 초기화 (사이드바 + 이력 탭)
    const wsIcon = document.getElementById('wsOrderIcon');
    const wsTime = document.getElementById('wsOrderTime');
    if (wsIcon) wsIcon.className = 'ws-icon pending';
    if (wsTime) wsTime.textContent = '대기 중';
    document.getElementById('wsOrderStep')?.querySelector('.ws-arrow')?.remove();

    const histIcon = document.getElementById('histOrderIcon');
    const histTime = document.getElementById('histOrderTime');
    if (histIcon) histIcon.className = 'ws-icon pending';
    if (histTime) histTime.textContent = '대기 중';
    document.getElementById('histOrderStep')?.querySelector('.ws-arrow')?.remove();

    showToast('✅ 주문이 삭제되었습니다.', 'success');
  }

  // ── 공통: 모든 팝오버/팝업 닫기 ───────────────────────
  function closeAllPopovers() {
    ['kakaoPopover','smsPopover','faxPopover','vaPopover','crDetailPopover','consentPopover','consentSignPopover','crIssuePopover','taxInvoicePopover','payPopover'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
  }

  /* ── 상담 창(팝업)의 아래 띠 ────────────────────────────
     거래처 관리의 「상담하기」로 열면 이 화면이 창으로 뜬다. 본 화면 그대로 고치되,
     창을 닫는 자리가 필요하다 — 아래에 저장·닫기를 세운다.

     적다 만 채로 닫는 일이 잦다. 그냥 닫으면 적은 것이 사라지므로 물어본다. */
  (function () {
    const isPopup = new URLSearchParams(location.search).get('popup') === '1';
    if (!isPopup) return;

    document.body.classList.add('is-counsel-popup');

    const bar = document.createElement('div');
    bar.id = 'counselPopupBar';
    bar.innerHTML = `
      <span id="counselPopupNote">적은 내용은 저장을 눌러야 남습니다.</span>
      <button type="button" class="ds-btn" onclick="counselPopupClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" onclick="counselPopupSave(this)">저장</button>`;
    document.body.appendChild(bar);

    /* 저장은 본 화면의 저장을 그대로 부른다 — 상담 창이라고 다른 규칙으로 저장하면
       같은 값이 두 길로 들어가 서로 어긋난다. */
    window.counselPopupSave = async function (btn) {
      const note = document.getElementById('counselPopupNote');
      note.textContent = '저장하는 중…';
      try {
        await saveOCR();
        note.textContent = '저장했습니다.';
        // 목록을 띄워 둔 창이 있으면 새로 읽게 한다 — 방금 적은 상담이 거기 보여야 한다
        try { window.opener?.postMessage({ source: 'ce-counsel', action: 'saved' }, location.origin); } catch (_) {}
      } catch (e) {
        note.textContent = '저장하지 못했습니다.';
      }
    };

    window.counselPopupClose = async function () {
      if (isAnyDirty()) {
        const ok = await ceConfirm('적은 내용을 저장하고 닫을까요?\n저장하지 않으면 적은 것이 사라집니다.',
                                   { tone: 'warning', confirmText: '저장하고 닫기', cancelText: '그냥 닫기' });
        if (ok) {
          await counselPopupSave();
          if (isAnyDirty()) return;      // 저장이 막혔으면 닫지 않는다
        }
        clearAllDirty();                 // 그냥 닫기 — beforeunload 가 한 번 더 묻지 않게
      }
      window.close();
    };
  })();

  /* ── 결제 전송 ─────────────────────────────────────────
     만들어 보내고, 무엇을 보냈는지 그 자리에서 본다. 창이 열릴 때 이력을 한 번 불러
     둔다 — 보내기 전에 「아까 보낸 것이 아직 안 냈구나」를 먼저 보게 하려는 것이다. */
  const PAY_STORE_URL  = @json($prescription->order ? route('payment-links.store', $prescription->order) : null);
  const PAY_INDEX_URL  = @json($prescription->order ? route('payment-links.index', $prescription->order) : null);
  const PAY_CANCEL_URL = @json(url('payment-links'));

  function togglePayPopover(e) {
    e.stopPropagation();
    const pop    = document.getElementById('payPopover');
    const isOpen = pop.style.display !== 'none';
    closeAllPopovers();
    pop.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
      placePayPopover();
      const mobile = document.getElementById('f-mobile')?.value;
      if (mobile) document.getElementById('payMobile').value = mobile;
      loadPaymentLinks();
    }
  }

  function closePayPopover() { document.getElementById('payPopover').style.display = 'none'; }

  /* 팩스 창과 같은 사정이다 — 단추가 오른쪽에 있으면 창이 화면 밖으로 밀려 잘린다 */
  function placePayPopover() {
    const pop  = document.getElementById('payPopover');
    const wrap = document.getElementById('payTriggerWrap');
    if (!pop || !wrap || pop.style.display === 'none') return;

    const gap  = 8;
    const w    = pop.offsetWidth || 420;
    const r    = wrap.getBoundingClientRect();
    const over = (r.left + w) - (window.innerWidth - gap);
    const left = over > 0 ? -Math.min(over, Math.max(0, r.left - gap)) : 0;

    pop.style.left = left + 'px';
    const arrow = document.getElementById('payPopoverArrow');
    if (arrow) arrow.style.left = Math.min(Math.max(24 - left, 12), w - 26) + 'px';
  }
  window.addEventListener('resize', placePayPopover);

  async function sendPaymentLink(btn) {
    if (!PAY_STORE_URL) { showToast('주문을 먼저 만들어 주십시오.', 'warning'); return; }

    const method = document.querySelector('input[name="pay_method"]:checked')?.value;
    const mobile = document.getElementById('payMobile').value.trim();
    if (!method) { showToast('결제 방법을 고르십시오.', 'warning'); return; }
    if (!mobile) { showToast('받는 번호를 적어 주십시오.', 'warning'); return; }

    BtnState.loading(btn, '보내는 중...');
    try {
      const res = await apiRequest(PAY_STORE_URL, 'POST', { method, mobile });
      showToast(res.message || (res.success ? '보냈습니다.' : '보내지 못했습니다.'),
                res.success ? 'success' : 'danger', 5000);
      loadPaymentLinks();
    } catch (e) {
      showToast('보내지 못했습니다: ' + (e.message || ''), 'danger', 5000);
    } finally {
      BtnState.reset(btn);
    }
  }

  async function loadPaymentLinks() {
    const box = document.getElementById('payHistory');
    if (!box) return;
    if (!PAY_INDEX_URL) {
      box.innerHTML = '<div style="padding:10px;color:var(--text-muted);text-align:center;">주문을 만들면 이력이 쌓입니다.</div>';
      return;
    }
    try {
      const res  = await apiRequest(PAY_INDEX_URL, 'GET');
      const rows = res.rows ?? [];
      if (!rows.length) {
        box.innerHTML = '<div style="padding:10px;color:var(--text-muted);text-align:center;">보낸 것이 없습니다.</div>';
        return;
      }
      box.innerHTML = rows.map(r => `
        <div style="display:flex;align-items:center;gap:6px;padding:7px 10px;border-bottom:1px solid var(--border-light);">
          <span style="font-weight:700;width:56px;flex-shrink:0;">${escHtml(r.method)}</span>
          <span style="width:64px;flex-shrink:0;text-align:right;">${Number(r.amount).toLocaleString()}원</span>
          <span style="flex:1;min-width:0;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            ${escHtml(r.sent_at || '-')} · ${escHtml(r.channel)}${r.error ? ' · ' + escHtml(r.error) : ''}
          </span>
          <span style="font-weight:700;color:${r.status === 'paid' ? 'var(--primary)' : (r.status === 'failed' ? 'var(--danger)' : 'var(--gray-700)')};">
            ${escHtml(r.status_label)}
          </span>
          ${r.open ? `<button type="button" class="rx-tpl-mini" onclick="copyPayLink('${escHtml(r.url)}')">주소</button>
                      <button type="button" class="rx-tpl-mini" onclick="cancelPayLink(${r.id})">닫기</button>` : ''}
        </div>`).join('');
    } catch (e) {
      box.innerHTML = '<div style="padding:10px;color:var(--danger);text-align:center;">이력을 불러오지 못했습니다.</div>';
    }
  }

  /* 문자가 막히는 환자도 있다 — 주소를 복사해 다른 길로 보낼 수 있게 둔다 */
  function copyPayLink(url) {
    navigator.clipboard?.writeText(url)
      .then(() => showToast('결제 주소를 복사했습니다.', 'success'))
      .catch(() => showToast(url, 'info', 8000));
  }

  async function cancelPayLink(id) {
    if (!await ceConfirm('이 결제 요청을 닫습니다. 환자가 주소를 눌러도 열리지 않습니다.',
                         { tone: 'warning', confirmText: '닫기' })) return;
    try {
      const res = await apiRequest(`${PAY_CANCEL_URL}/${id}/cancel`, 'POST');
      showToast(res.message || '닫았습니다.', res.success ? 'success' : 'danger');
      loadPaymentLinks();
    } catch (e) {
      showToast('닫지 못했습니다.', 'danger');
    }
  }

  // ── 카카오 알림톡 팝오버 ─────────────────────────────
  function toggleKakaoPopover(e) {
    e.stopPropagation();
    const pop    = document.getElementById('kakaoPopover');
    const isOpen = pop.style.display !== 'none';
    closeAllPopovers();
    pop.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
      const mobile = document.getElementById('f-mobile')?.value ?? '';
      document.getElementById('kakaoMobile').value = mobile;
    }
  }

  function closeKakaoPopover() {
    document.getElementById('kakaoPopover').style.display = 'none';
  }

  function markKakaoSent() {
    const btn = document.getElementById('btnKakaoTrigger');
    if (!btn) return;
    btn.style.background = 'var(--primary-50)';
    btn.style.color      = 'var(--primary)';
    btn.style.border     = '1px solid var(--primary-200)';
    btn.querySelector('svg')?.setAttribute('fill', 'var(--primary)');
  }

  // 팝오버 외부 클릭 시 닫기
  document.addEventListener('click', e => {
    const pop = document.getElementById('kakaoPopover');
    const btn = document.getElementById('btnKakaoTrigger');
    if (pop && !pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
      pop.style.display = 'none';
    }
  });

  // 템플릿 선택 시 자동 미리보기
  function onTplChange(radio) {
    document.querySelectorAll('.kakao-tpl-item').forEach(item => {
      const checked = item.querySelector('input').checked;
      item.style.borderColor = checked ? '#FEE500' : 'var(--border)';
      item.style.background  = checked ? '#FFFDE7' : '';
    });
    loadKakaoPreview();
  }

  async function loadKakaoPreview() {
    const tpl = document.querySelector('input[name=kakao_tpl]:checked')?.value;
    if (!tpl) return;

    const wrap = document.getElementById('kakaoPreviewWrap');
    const box  = document.getElementById('kakaoPreviewBox');
    wrap.style.display = 'block';
    box.textContent = '불러오는 중...';

    try {
      const res  = await fetch(`{{ route('prescriptions.kakaoPreview', $prescription) }}?template_code=${tpl}`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      box.textContent = data.preview ?? '미리보기 없음';

      const mobileEl = document.getElementById('kakaoMobile');
      if (data.mobile && !mobileEl.value) mobileEl.value = data.mobile;
    } catch {
      box.textContent = '미리보기 실패';
    }
  }

  async function sendKakaoMsg() {
    const tpl    = document.querySelector('input[name=kakao_tpl]:checked')?.value;
    const mobile = document.getElementById('kakaoMobile').value.trim();
    if (!tpl)    { showToast('메시지 유형을 선택해주세요.', 'warning'); return; }
    if (!mobile) { showToast('수신 번호를 입력해주세요.', 'warning');  return; }

    const btn = document.getElementById('btnKakaoSend');
    BtnState.loading(btn, '발송 중...');

    try {
      const res  = await fetch(@json(route('prescriptions.kakaoSend', $prescription)), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ template_code: tpl, mobile }),
      });
      const data = await res.json();
      if (data.success) {
        closeKakaoPopover();
        markKakaoSent();
        showToast('✅ ' + data.message, 'success');
      } else {
        showToast(data.message || '발송 실패', 'danger');
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  }

  // ── SMS 알림 팝오버 ───────────────────────────────────
  const SMS_PLACEHOLDERS = {
    '#{고객명}':      @json($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '고객'),
    '#{처방번호}':    @json($prescription->rx_number),
    '#{주문번호}':    @json($prescription->order?->order_number ?? '-'),
    '#{본인부담금}':  @json($prescription->order ? number_format($calcCopay) : '-'),
    '#{배송비}':      @json($prescription->order ? number_format($prescription->order->shipping_fee ?? 0) : '-'),
    '#{금액}':        @json($prescription->order ? number_format($calcDeposit) : '-'),
    '#{운송장번호}':  @json($prescription->order?->tracking_number ?? '-'),
  };


  function toggleSmsPopover(e) {
    e.stopPropagation();
    const pop    = document.getElementById('smsPopover');
    const isOpen = pop.style.display !== 'none';
    closeAllPopovers();
    pop.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
      const mobile = document.getElementById('f-mobile')?.value ?? '';
      document.getElementById('smsMobile').value = mobile;
    }
  }

  function closeSmsPopover() {
    document.getElementById('smsPopover').style.display = 'none';
  }

  function markSmsSent() {
    const btn = document.getElementById('btnSmsTrigger');
    if (!btn) return;
    btn.style.background = 'var(--primary-50)';
    btn.style.color      = 'var(--primary)';
    btn.style.border     = '1px solid var(--primary-200)';
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('smsPopover');
    const btn = document.getElementById('btnSmsTrigger');
    if (pop && !pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
      pop.style.display = 'none';
    }
  });

  function onSmsTplChange(radio) {
    document.querySelectorAll('.sms-tpl-item').forEach(item => {
      const checked = item.querySelector('input').checked;
      item.style.borderColor = checked ? 'var(--primary)' : 'var(--border)';
      item.style.background  = checked ? 'rgba(40,121,139,.06)' : '';
    });

    const label = radio.closest('.sms-tpl-item');
    let text = label.dataset.text || '';
    Object.entries(SMS_PLACEHOLDERS).forEach(([key, val]) => {
      text = text.replaceAll(key, val);
    });
    document.getElementById('smsMsgBody').value = text;
    updateSmsLen();
  }

  function updateSmsLen() {
    const body = document.getElementById('smsMsgBody');
    const len  = body.value.length;
    document.getElementById('smsMsgLen').textContent = `(${len}자)`;
    const typeEl = document.getElementById('smsMsgType');
    // EUC-KR 기준 한글 2바이트 계산
    const bytes = [...body.value].reduce((n, c) => n + (c.charCodeAt(0) > 127 ? 2 : 1), 0);
    typeEl.textContent = bytes > 90 ? 'LMS (장문)' : 'SMS (단문)';
    typeEl.style.color = bytes > 90 ? 'var(--warning)' : 'var(--text-muted)';
  }

  async function sendSmsMsg() {
    const mobile  = document.getElementById('smsMobile').value.trim();
    const message = document.getElementById('smsMsgBody').value.trim();
    if (!mobile)  { showToast('수신 번호를 입력해주세요.', 'warning');  return; }
    if (!message) { showToast('메시지 내용을 입력해주세요.', 'warning'); return; }

    const btn = document.getElementById('btnSmsSend');
    BtnState.loading(btn, '발송 중...');

    try {
      const res  = await fetch(@json(route('prescriptions.smsSend', $prescription)), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ mobile, message }),
      });
      const data = await res.json();
      if (data.success) {
        closeSmsPopover();
        markSmsSent();
        showToast('✅ ' + data.message, 'success');
      } else {
        showToast(data.message || 'SMS 발송 실패', 'danger');
      }
    } catch {
      showToast('오류가 발생했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  }

  // ── 팩스 전송 팝오버 ─────────────────────────────────
  function toggleFaxPopover(e) {
    e.stopPropagation();
    closeAllPopovers();
    const pop = document.getElementById('faxPopover');
    const opening = pop.style.display === 'none';
    pop.style.display = opening ? 'block' : 'none';
    if (opening) {
      placeFaxPopover();
      const activeBtn = document.querySelector('.fax-recipient-btn[data-recipient-type="nhis"]');
      if (activeBtn && activeBtn.style.background.includes('var(--primary-light)')) {
        document.getElementById('nhisSearchPanel').style.display = 'block';
        renderNhisOffices('');
      }
      refreshFaxSentBanner();
    }
  }

  /* 팩스 창은 단추에 왼쪽 끝을 맞춘다. 단추가 화면 오른쪽에 있으면 580 폭이 그대로
     창밖으로 밀려 나가 절반이 잘렸다 — 넘치는 만큼 왼쪽으로 당긴다.
     꼬리는 단추를 계속 가리켜야 하므로 당긴 만큼 되밀어 준다. */
  function placeFaxPopover() {
    const pop  = document.getElementById('faxPopover');
    const wrap = document.getElementById('faxTriggerWrap');
    if (!pop || !wrap || pop.style.display === 'none') return;

    const gap  = 8;
    const w    = pop.offsetWidth || 580;
    const r    = wrap.getBoundingClientRect();
    const over = (r.left + w) - (window.innerWidth - gap);
    const left = over > 0 ? -Math.min(over, Math.max(0, r.left - gap)) : 0;

    pop.style.left = left + 'px';

    const arrow = document.getElementById('faxPopoverArrow');
    if (arrow) {
      arrow.style.left = Math.min(Math.max(24 - left, 12), w - 26) + 'px';   // 창 안에 머무는 선에서
    }
  }

  window.addEventListener('resize', placeFaxPopover);

  function closeFaxPopover() {
    document.getElementById('faxPopover').style.display = 'none';
  }

  function reopenFaxPopover(e) {
    e.stopPropagation();
    document.getElementById('faxResultBadge').style.display = 'none';
    document.getElementById('faxTriggerWrap').style.display = 'block';
    closeAllPopovers();
    document.getElementById('faxPopover').style.display = 'block';
    placeFaxPopover();
    if (typeof refreshFaxSentBanner === 'function') refreshFaxSentBanner();
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('faxPopover');
    const btn = document.getElementById('btnFaxTrigger');
    if (pop && !pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
      pop.style.display = 'none';
    }
  });

  function selectFaxRecipient(btn) {
    document.querySelectorAll('.fax-recipient-btn').forEach(b => {
      b.style.borderColor = 'var(--border)';
      b.style.background  = 'var(--bg-card)';
      b.querySelector('div > div:first-child').style.color = 'var(--text)';
      const numEl = b.querySelector('span');
      if (numEl) numEl.style.color = 'var(--text-muted)';
    });
    btn.style.borderColor = 'var(--primary)';
    btn.style.background  = 'var(--primary-light)';
    btn.querySelector('div > div:first-child').style.color = 'var(--primary)';
    const numEl = btn.querySelector('span');
    if (numEl) numEl.style.color = 'var(--primary)';

    const faxEl = document.getElementById('fax-no');
    const nhisPanel = document.getElementById('nhisSearchPanel');

    if (btn.dataset.recipientType === 'nhis') {
      nhisPanel.style.display = 'block';
      renderNhisOffices('');
      document.getElementById('nhisSearchInput').focus();
      faxEl.value = '';
    } else {
      nhisPanel.style.display = 'none';
      if (btn.dataset.fax) {
        faxEl.value = btn.dataset.fax;
      } else {
        faxEl.value = '';
        faxEl.focus();
      }
    }
  }

  function onFaxNoInput() {
    const customBtn = document.querySelector('.fax-recipient-btn[data-recipient-type="custom"]');
    if (!customBtn) return;
    const isCustomSelected = customBtn.style.background.includes('var(--primary-light)');
    if (isCustomSelected) return;
    // 입력값 유지한 채로 기타 버튼만 시각적으로 선택 처리
    document.querySelectorAll('.fax-recipient-btn').forEach(b => {
      b.style.borderColor = 'var(--border)';
      b.style.background  = 'var(--bg-card)';
      b.querySelector('div > div:first-child').style.color = 'var(--text)';
      const numEl = b.querySelector('span');
      if (numEl) numEl.style.color = 'var(--text-muted)';
    });
    customBtn.style.borderColor = 'var(--primary)';
    customBtn.style.background  = 'var(--primary-light)';
    customBtn.querySelector('div > div:first-child').style.color = 'var(--primary)';
    document.getElementById('nhisSearchPanel').style.display = 'none';
  }

  const NHIS_OFFICES = [
    // 서울
    { region:'서울', name:'강남지사',   fax:'02-3470-5261' },
    { region:'서울', name:'강동지사',   fax:'02-3299-5261' },
    { region:'서울', name:'강북지사',   fax:'02-997-5261'  },
    { region:'서울', name:'강서지사',   fax:'02-2600-5261' },
    { region:'서울', name:'관악지사',   fax:'02-3289-5261' },
    { region:'서울', name:'광진지사',   fax:'02-3290-5261' },
    { region:'서울', name:'구로지사',   fax:'02-858-5261'  },
    { region:'서울', name:'노원지사',   fax:'02-3391-5261' },
    { region:'서울', name:'도봉지사',   fax:'02-955-5261'  },
    { region:'서울', name:'동대문지사', fax:'02-3299-5262' },
    { region:'서울', name:'동작지사',   fax:'02-3280-5261' },
    { region:'서울', name:'마포지사',   fax:'02-3279-5262' },
    { region:'서울', name:'서대문지사', fax:'02-360-5261'  },
    { region:'서울', name:'서초지사',   fax:'02-3489-5261' },
    { region:'서울', name:'성동지사',   fax:'02-3499-5261' },
    { region:'서울', name:'성북지사',   fax:'02-3289-5262' },
    { region:'서울', name:'송파지사',   fax:'02-3470-5262' },
    { region:'서울', name:'양천지사',   fax:'02-2600-5262' },
    { region:'서울', name:'영등포지사', fax:'02-2670-5261' },
    { region:'서울', name:'용산지사',   fax:'02-3279-5261' },
    { region:'서울', name:'은평지사',   fax:'02-3910-5261' },
    { region:'서울', name:'종로지사',   fax:'02-720-4242'  },
    { region:'서울', name:'중구지사',   fax:'02-3279-5263' },
    { region:'서울', name:'중랑지사',   fax:'02-3392-5261' },
    // 경기
    { region:'경기', name:'고양지사',   fax:'031-900-5261' },
    { region:'경기', name:'광명지사',   fax:'031-6940-5261'},
    { region:'경기', name:'광주지사',   fax:'031-760-5261' },
    { region:'경기', name:'구리지사',   fax:'031-560-5261' },
    { region:'경기', name:'군포지사',   fax:'031-461-5261' },
    { region:'경기', name:'김포지사',   fax:'031-990-5261' },
    { region:'경기', name:'남양주지사', fax:'031-590-5261' },
    { region:'경기', name:'부천지사',   fax:'032-320-5261' },
    { region:'경기', name:'성남지사',   fax:'031-750-5261' },
    { region:'경기', name:'수원지사',   fax:'031-250-5261' },
    { region:'경기', name:'시흥지사',   fax:'031-499-5261' },
    { region:'경기', name:'안산지사',   fax:'031-490-5261' },
    { region:'경기', name:'안양지사',   fax:'031-380-5261' },
    { region:'경기', name:'양주지사',   fax:'031-840-5261' },
    { region:'경기', name:'여주지사',   fax:'031-880-5261' },
    { region:'경기', name:'오산지사',   fax:'031-379-5261' },
    { region:'경기', name:'용인지사',   fax:'031-219-5261' },
    { region:'경기', name:'의정부지사', fax:'031-850-5261' },
    { region:'경기', name:'이천지사',   fax:'031-639-5261' },
    { region:'경기', name:'파주지사',   fax:'031-940-5261' },
    { region:'경기', name:'평택지사',   fax:'031-659-5261' },
    { region:'경기', name:'포천지사',   fax:'031-539-5261' },
    { region:'경기', name:'하남지사',   fax:'031-790-5261' },
    { region:'경기', name:'화성지사',   fax:'031-369-5261' },
    // 인천
    { region:'인천', name:'계양지사',   fax:'032-540-5261' },
    { region:'인천', name:'남동지사',   fax:'032-460-5261' },
    { region:'인천', name:'부평지사',   fax:'032-509-5261' },
    { region:'인천', name:'서구지사',   fax:'032-570-5261' },
    { region:'인천', name:'연수지사',   fax:'032-289-5261' },
    { region:'인천', name:'중구지사',   fax:'032-760-5261' },
    // 부산
    { region:'부산', name:'강서지사',   fax:'051-979-5261' },
    { region:'부산', name:'금정지사',   fax:'051-519-5261' },
    { region:'부산', name:'기장지사',   fax:'051-790-5261' },
    { region:'부산', name:'남구지사',   fax:'051-610-5261' },
    { region:'부산', name:'동래지사',   fax:'051-550-5261' },
    { region:'부산', name:'사상지사',   fax:'051-309-5261' },
    { region:'부산', name:'사하지사',   fax:'051-206-5261' },
    { region:'부산', name:'서부지사',   fax:'051-256-5261' },
    { region:'부산', name:'연제지사',   fax:'051-608-5261' },
    { region:'부산', name:'해운대지사', fax:'051-740-5261' },
    // 대구
    { region:'대구', name:'남구지사',   fax:'053-620-5261' },
    { region:'대구', name:'달서지사',   fax:'053-580-5261' },
    { region:'대구', name:'달성지사',   fax:'053-659-5261' },
    { region:'대구', name:'동구지사',   fax:'053-940-5261' },
    { region:'대구', name:'북구지사',   fax:'053-350-5261' },
    { region:'대구', name:'서구지사',   fax:'053-560-5261' },
    { region:'대구', name:'수성지사',   fax:'053-760-5261' },
    // 광주
    { region:'광주', name:'광산지사',   fax:'062-960-5261' },
    { region:'광주', name:'남구지사',   fax:'062-608-5261' },
    { region:'광주', name:'동구지사',   fax:'062-220-5261' },
    { region:'광주', name:'북구지사',   fax:'062-520-5261' },
    { region:'광주', name:'서구지사',   fax:'062-380-5261' },
    // 대전
    { region:'대전', name:'대덕지사',   fax:'042-719-5261' },
    { region:'대전', name:'동구지사',   fax:'042-280-5261' },
    { region:'대전', name:'서구지사',   fax:'042-480-5261' },
    { region:'대전', name:'유성지사',   fax:'042-860-5261' },
    { region:'대전', name:'중구지사',   fax:'042-580-5261' },
    // 울산
    { region:'울산', name:'남구지사',   fax:'052-260-5261' },
    { region:'울산', name:'동구지사',   fax:'052-230-5261' },
    { region:'울산', name:'북구지사',   fax:'052-289-5261' },
    { region:'울산', name:'울주지사',   fax:'052-239-5261' },
    { region:'울산', name:'중구지사',   fax:'052-290-5261' },
    // 세종
    { region:'세종', name:'세종지사',   fax:'044-850-5261' },
    // 강원
    { region:'강원', name:'강릉지사',   fax:'033-820-5261' },
    { region:'강원', name:'동해지사',   fax:'033-530-5261' },
    { region:'강원', name:'속초지사',   fax:'033-639-5261' },
    { region:'강원', name:'원주지사',   fax:'033-760-5261' },
    { region:'강원', name:'춘천지사',   fax:'033-259-5261' },
    { region:'강원', name:'태백지사',   fax:'033-580-5261' },
    // 충북
    { region:'충북', name:'제천지사',   fax:'043-649-5261' },
    { region:'충북', name:'청주지사',   fax:'043-279-5261' },
    { region:'충북', name:'충주지사',   fax:'043-840-5261' },
    // 충남
    { region:'충남', name:'논산지사',   fax:'041-731-5261' },
    { region:'충남', name:'당진지사',   fax:'041-350-5261' },
    { region:'충남', name:'서산지사',   fax:'041-660-5261' },
    { region:'충남', name:'아산지사',   fax:'041-530-5261' },
    { region:'충남', name:'천안지사',   fax:'041-589-5261' },
    // 전북
    { region:'전북', name:'군산지사',   fax:'063-460-5261' },
    { region:'전북', name:'완주지사',   fax:'063-240-5261' },
    { region:'전북', name:'익산지사',   fax:'063-850-5261' },
    { region:'전북', name:'전주지사',   fax:'063-279-5261' },
    { region:'전북', name:'정읍지사',   fax:'063-570-5261' },
    // 전남
    { region:'전남', name:'광양지사',   fax:'061-760-5261' },
    { region:'전남', name:'나주지사',   fax:'061-330-5261' },
    { region:'전남', name:'목포지사',   fax:'061-280-5261' },
    { region:'전남', name:'순천지사',   fax:'061-720-5261' },
    { region:'전남', name:'여수지사',   fax:'061-640-5261' },
    // 경북
    { region:'경북', name:'경주지사',   fax:'054-779-5261' },
    { region:'경북', name:'구미지사',   fax:'054-460-5261' },
    { region:'경북', name:'안동지사',   fax:'054-840-5261' },
    { region:'경북', name:'영주지사',   fax:'054-639-5261' },
    { region:'경북', name:'포항지사',   fax:'054-289-5261' },
    // 경남
    { region:'경남', name:'거제지사',   fax:'055-680-5261' },
    { region:'경남', name:'김해지사',   fax:'055-329-5261' },
    { region:'경남', name:'진주지사',   fax:'055-760-5261' },
    { region:'경남', name:'창원지사',   fax:'055-239-5261' },
    { region:'경남', name:'통영지사',   fax:'055-649-5261' },
    // 제주
    { region:'제주', name:'서귀포지사', fax:'064-730-5261' },
    { region:'제주', name:'제주지사',   fax:'064-720-5261' },
  ];

  function renderNhisOffices(query) {
    const q = (query || '').trim();
    const list = q
      ? NHIS_OFFICES.filter(o => o.region.includes(q) || o.name.includes(q) || o.fax.includes(q))
      : NHIS_OFFICES;
    const container = document.getElementById('nhisOfficeList');
    if (!list.length) {
      container.innerHTML = '<div style="font-size:11px;color:var(--text-muted);padding:6px;text-align:center;">검색 결과 없음</div>';
      return;
    }
    container.innerHTML = list.map(o => `
      <button type="button" onclick="selectNhisOffice('${o.fax}','${o.region} ${o.name}')"
              style="display:flex;align-items:center;justify-content:space-between;padding:5px 8px;border:1px solid var(--border);border-radius:4px;background:var(--bg-card);cursor:pointer;text-align:left;width:100%;">
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-light);border-radius:6px;padding:1px 5px;flex-shrink:0;">${o.region}</span>
          <span style="font-size:11px;font-weight:500;color:var(--text);">${o.name}</span>
        </div>
        <span style="font-size:10px;font-family:monospace;color:var(--text-muted);flex-shrink:0;">${o.fax}</span>
      </button>
    `).join('');
  }

  function selectNhisOffice(fax, name) {
    document.getElementById('fax-no').value = fax;
    document.getElementById('nhisSearchPanel').style.display = 'none';
    // 고른 지사를 공단 지사 버튼 라벨에 반영
    const nhisBtn = document.querySelector('.fax-recipient-btn[data-recipient-type="nhis"]');
    if (nhisBtn) {
      nhisBtn.dataset.fax = fax;
      const subLabel = nhisBtn.querySelector('div > div:last-child');
      if (subLabel) subLabel.textContent = name + ' · ' + fax;
    }
  }

  async function sendFax() {
    const faxNo = document.getElementById('fax-no').value.trim();
    if (!faxNo) { showToast('수신 팩스번호를 입력해주세요.', 'warning'); return; }

    const docMap = {
      /* 팩스는 환자 등록·재등록 전용이라 생성 서류를 싣지 않는다.
         등록신청서·결과지·신분증은 첨부(attachment_ids)로 나간다. */
    };
    const selected = Object.entries(docMap)
      .filter(([id]) => document.getElementById(id)?.checked);

    const docs      = selected.map(([, d]) => d.value);
    const docLabels = selected.map(([, d]) => d.label);

    // 첨부 문서 선택
    const attIds = Array.from(document.querySelectorAll('.fax-att-chk:checked'))
      .map(el => parseInt(el.value));
    const attLabels = Array.from(document.querySelectorAll('.fax-att-chk:checked'))
      .map(el => el.closest('label').querySelector('span')?.textContent?.trim() ?? '첨부');

    if (!selected.length && !attIds.length) {
      showToast('전송할 서류를 하나 이상 선택해주세요.', 'warning'); return;
    }

    const activeBtn = document.querySelector('.fax-recipient-btn[style*="var(--primary-light)"]');
    const recipientType = activeBtn?.dataset?.recipientType ?? 'custom';
    const recipientName = activeBtn?.querySelector('div > div:first-child')?.textContent?.trim() ?? '기타';

    const btn = document.getElementById('btnFaxSend');
    BtnState.loading(btn, '전송 중...');

    try {
      const res = await fetch(@json(route('prescriptions.faxSend', $prescription)), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          recipient_type: recipientType,
          fax_no: faxNo,
          documents: docs,
          attachment_ids: attIds,
        }),
      });
      const data = await res.json();
      if (data.success) {
        showFaxResultModal(data, [...docLabels, ...attLabels]);
      } else {
        showToast(data.message || '팩스 전송 실패', 'danger');
      }
    } catch {
      showToast('오류가 발생했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  }

  let _lastFaxResult = null;

  function markFaxSent(data, docLabels) {
    _lastFaxResult = { data, docLabels, sentAt: new Date() };

    // 버튼 배지
    const badge = document.getElementById('faxSentBadge');
    if (badge) { badge.style.display = 'flex'; }

    // 결과 배지 영역으로 전환
    const tw = document.getElementById('faxTriggerWrap');
    const rb = document.getElementById('faxResultBadge');
    if (tw) tw.style.display = 'none';
    if (rb) rb.style.display = 'flex';

    // 보기 버튼 URL 갱신
    const viewBtn = document.getElementById('faxPdfViewBtn');
    if (viewBtn && data.pdf_url) viewBtn.dataset.url = data.pdf_url;

    // 팝오버 배너 업데이트
    refreshFaxSentBanner();
  }

  function openFaxPdfModal() {
    const btn = document.getElementById('faxPdfViewBtn');
    const url = btn?.dataset.url || '{{ route('prescriptions.faxPdf', $prescription) }}';
    if (!url) { showToast('PDF 파일이 없습니다.', 'warning'); return; }

    const pop = document.getElementById('faxPdfPopover');
    document.getElementById('faxPdfFrame').src = url;
    document.getElementById('faxPdfDownloadBtn').href = url;

    // 버튼 아래, 화면 가로 중앙에 위치
    const r  = btn.getBoundingClientRect();
    const pw = Math.min(820, window.innerWidth * 0.90);
    const ph = Math.min(window.innerHeight * 0.88, window.innerHeight - r.bottom - 24);
    const left = Math.max(8, (window.innerWidth - pw) / 2);
    const top  = r.bottom + 8;

    pop.style.left   = left + 'px';
    pop.style.top    = top  + 'px';
    pop.style.width  = pw   + 'px';
    pop.style.height = ph   + 'px';
    pop.style.display = 'flex';

    setTimeout(() => {
      document.addEventListener('click', _faxPdfOutside);
    }, 0);
  }

  function _faxPdfOutside(e) {
    const pop = document.getElementById('faxPdfPopover');
    if (pop && !pop.contains(e.target) && e.target.id !== 'faxPdfViewBtn') {
      closeFaxPdfModal();
    }
  }

  function closeFaxPdfModal() {
    const pop = document.getElementById('faxPdfPopover');
    if (pop) pop.style.display = 'none';
    document.getElementById('faxPdfFrame').src = '';
    document.removeEventListener('click', _faxPdfOutside);
  }


  function refreshFaxSentBanner() {
    if (!_lastFaxResult) return;
    const { data, docLabels, sentAt } = _lastFaxResult;
    const banner   = document.getElementById('faxSentBanner');
    const textEl   = document.getElementById('faxSentBannerText');
    const pdfLink  = document.getElementById('faxSentBannerPdf');
    if (!banner || !textEl) return;

    const timeStr  = sentAt.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
    textEl.textContent = `${timeStr} 전송 완료 — ${data.recipient} (${data.fax_no}) | ${docLabels.join(', ')}`;

    const pdfUrl = data.pdf_url
      || ('{{ route('prescriptions.faxPdf', $prescription) }}?' + data.documents.map(d => 'docs[]=' + encodeURIComponent(d)).join('&'));
    if (pdfLink) {
      pdfLink.href = pdfUrl;
      pdfLink.style.display = '';
    }

    banner.style.display = 'flex';
  }

  function showFaxResultModal(data, docLabels) {
    markFaxSent(data, docLabels);
    const authNote = data.auth_info?.is_auto_generated
      ? `<div style="margin-top:6px;padding:6px 10px;background:var(--alert-50);border:1px solid var(--alert-100);border-radius:4px;font-size:11px;color:var(--alert-500);">
           ⚠ 위임장: 환자 서명 없음 — 처방 정보로 자동 생성된 문서가 전송됩니다.
         </div>`
      : (data.auth_info ? `<div style="margin-top:6px;padding:6px 10px;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:4px;font-size:11px;color:var(--primary-600);">
           ✓ 위임장: 환자 전자서명이 포함된 문서가 전송됩니다.
         </div>` : '');

    const receiptLine = data.receipt_num
      ? `<div style="margin-top:4px;font-size:11px;color:var(--text-muted);">Popbill 접수번호: <b>${data.receipt_num}</b></div>`
      : '';

    // 저장된 PDF URL 우선, 없으면 실시간 생성 URL
    const pdfUrl = data.pdf_url
      || ('{{ route('prescriptions.faxPdf', $prescription) }}?' + data.documents.map(d => 'docs[]=' + encodeURIComponent(d)).join('&'));
    const pdfNote = data.pdf_url
      ? `<div style="font-size:10px;color:var(--text-muted);margin-top:3px;">storage/fax/${RX_NUMBER} 에 저장됨</div>`
      : '';

    const modalHtml = `
      <div id="faxResultModal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;" onclick="if(event.target===this)this.remove()">
        <div style="position:relative;background:var(--bg-card);border-radius:var(--radius-lg);padding:24px 28px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
          <button onclick="document.getElementById('faxResultModal').remove();closeFaxPopover();"
                  style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:18px;line-height:1;color:var(--text-muted);cursor:pointer;padding:2px 6px;"
                  title="닫기">&times;</button>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div style="width:40px;height:40px;border-radius:999px;background:var(--primary-50);border:1px solid var(--primary-200);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📠</div>
            <div>
              <div style="font-size:14px;font-weight:700;">팩스 전송 완료</div>
              <div style="font-size:11px;color:var(--text-muted);">요청이 정상적으로 접수되었습니다.</div>
            </div>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:14px;font-size:12px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;gap:10px;">
              <span style="color:var(--text-muted);width:60px;flex-shrink:0;">수신처</span>
              <span style="font-weight:700;">${data.recipient} <span style="color:var(--text-muted);font-weight:400;">(${data.fax_no})</span></span>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <span style="color:var(--text-muted);width:60px;flex-shrink:0;">전송 서류</span>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                ${docLabels.map(l => `<span style="padding:2px 8px;background:var(--primary-light);color:var(--primary);border-radius:6px;font-size:11px;font-weight:500;">${l}</span>`).join('')}
              </div>
            </div>
            ${authNote}
            ${receiptLine}
          </div>

          <a href="${pdfUrl}" target="_blank"
             style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;
                    padding:10px;background:var(--gray-900);color:#fff;border-radius:var(--radius);
                    font-weight:700;font-size:13px;text-decoration:none;cursor:pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;fill:none;stroke:#fff;stroke-width:2;" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-8m0 8l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
            </svg>
            PDF 다운로드 (전송 서류 통합본)
          </a>
          ${pdfNote}

          <div style="margin-top:10px;display:flex;gap:8px;">
            <button onclick="document.getElementById('faxResultModal').remove();document.getElementById('faxPopover').style.display='block';"
                    style="flex:1;padding:9px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:var(--radius);font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
              <i class="fa-solid fa-rotate-right"></i> 다시 전송
            </button>
            <button onclick="document.getElementById('faxResultModal').remove();closeFaxPopover();"
                    style="flex:1;padding:9px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-weight:700;font-size:13px;cursor:pointer;">
              확인
            </button>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }

  // ── 등록자 메모 팝업 ──────────────────────────────────
  // ── 채팅방 열기 (우측 채팅 패널) ────────────────────
  async function openChatWith(userId, userName) {
    try {
      // 1. 방 생성 or 기존 방 조회
      const res  = await fetch(@json(route('chat.createRoom')), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ type: 'direct', user_ids: [userId] }),
      });
      const data = await res.json();
      if (!data.room_id) { showToast('채팅방을 열 수 없습니다.', 'danger'); return; }

      // 2. 패널 직접 열기 (open()은 loadRooms를 비동기로 호출하므로 직접 제어)
      document.getElementById('chatPanel').classList.add('open');
      document.getElementById('chatOverlay').classList.add('show');

      // 3. 방 목록 완전히 로드 후 해당 방 선택 (순서 보장)
      await ChatPanel.loadRooms();
      ChatPanel.selectRoom(data.room_id);

    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    }
  }

  // ── 가상계좌 발급 ─────────────────────────────────────
  function toggleVaPopover(e) {
    e.stopPropagation();
    const pop = document.getElementById('vaPopover');
    if (!pop) return;
    if (pop.style.display !== 'none') { pop.style.display = 'none'; return; }
    closeAllPopovers();
    document.getElementById('vaPopoverConfirm').style.display = 'block';
    document.getElementById('vaPopoverResult').style.display  = 'none';
    document.getElementById('vaPopoverTitle').textContent     = '가상계좌 발급';
    const issueBtn = document.getElementById('vaConfirmIssueBtn');
    if (issueBtn) { issueBtn.disabled = false; issueBtn.innerHTML = '<i class="fa-solid fa-building-columns"></i> 발급 확인'; }
    pop.style.display = 'block';
  }

  function closeVaPopover() {
    const pop    = document.getElementById('vaPopover');
    if (pop) pop.style.display = 'none';
    // 발급 완료 후 닫을 때 버튼 영역을 결과 배지로 전환
    const vaRbTx = document.getElementById('vaResultBadgeText');
    if (vaRbTx && vaRbTx.textContent && vaRbTx.textContent !== '-') {
      const vaWrap = document.getElementById('vaNotIssuedWrap');
      const vaRb   = document.getElementById('vaResultBadge');
      if (vaWrap) vaWrap.style.display = 'none';
      if (vaRb)   vaRb.style.display   = 'flex';
    }
  }

  function closeVaAndShowResultBadge() { closeVaPopover(); }

  async function doIssueVirtualAccount() {
    const triggerBtn = document.getElementById('btnVaTrigger');
    const confirmBtn = document.getElementById('vaConfirmIssueBtn');
    if (!triggerBtn) return;
    const url    = triggerBtn.dataset.url;
    const smsUrl = triggerBtn.dataset.smsUrl;
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 발급 중...'; }
    try {
      // skip_sms=1: 이 화면은 발급 후 아래에서 자체적으로 안내 SMS를 발송하므로 서버 발송을 생략(이중 발송 방지)
      const res = await fetch(url + (url.includes('?') ? '&' : '?') + 'skip_sms=1', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json'
        }
      });
      const data = await res.json();
      if (data.success) {
        const rawAmount  = Number(String(data.amount ?? 0).replace(/,/g, ''));
        const fmtAmount  = rawAmount.toLocaleString('ko-KR');
        const bankName   = data.bank_name      ?? '';
        const accountNo  = data.account_number ?? '';
        const dueDate    = data.due_date       ?? '-';
        const isDisabled = !!data.disabled;

        document.getElementById('vaResultBank').textContent    = bankName   || (isDisabled ? '미발급(비활성화)' : '-');
        document.getElementById('vaResultAccount').textContent = accountNo  || (isDisabled ? '미발급(비활성화)' : '-');
        document.getElementById('vaResultAmount').textContent  = '₩' + fmtAmount;
        document.getElementById('vaResultDue').textContent     = dueDate;
        document.getElementById('vaPopoverTitle').textContent  = isDisabled ? '발급 완료 (VA 비활성화)' : '발급 완료';
        document.getElementById('vaPopoverConfirm').style.display = 'none';
        document.getElementById('vaPopoverResult').style.display  = 'block';

        // 비활성화 상태일 때 안내 문구 표시
        const disabledNote = document.getElementById('vaDisabledNote');
        if (disabledNote) disabledNote.style.display = isDisabled ? 'block' : 'none';

        if (triggerBtn) { triggerBtn.disabled = true; triggerBtn.style.background = 'var(--gray-600)'; triggerBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> 발급 완료'; }
        // 결과 배지 텍스트 미리 준비 (팝오버 닫을 때 배지로 전환)
        const vaRbTx = document.getElementById('vaResultBadgeText');
        if (vaRbTx) {
          vaRbTx.textContent = bankName && accountNo ? `${bankName} ${accountNo}` : '발급완료';
        }
        // ── 가상계좌 내용 SMS 자동 발송 ──────────────────────
        const patientName  = SMS_PLACEHOLDERS['#{고객명}'] ?? '';
        const mobile       = document.getElementById('smsMobile')?.value?.trim() ?? '';
        const shippingFee  = Number(data.shipping_fee ?? 3000);
        const productTotal = items.reduce((s, i) => {
            const base = Number(i.insurance_price ?? i.product_price ?? 0);
            const qty  = Number(i.quantity ?? 1);
            return s + Math.round(base * qty);
        }, 0);
        const copayAmt = items.reduce((s, i) => {
            const base = Number(i.insurance_price ?? i.product_price ?? 0);
            const qty  = Number(i.quantity ?? 1);
            const rate = i.nhis_status === 'eligible' ? 0.9 : i.nhis_status === 'partial' ? 0.5 : 0.0;
            const nhis = Math.round(base * rate * qty);
            return s + Math.round(base * qty) - nhis;
        }, 0);
        const depositAmt   = copayAmt + shippingFee;      // 입금금액 (본인부담금 + 배송비)
        const fmtProduct   = productTotal.toLocaleString('ko-KR');
        const fmtCopay     = copayAmt.toLocaleString('ko-KR');
        const fmtDeposit   = depositAmt.toLocaleString('ko-KR');
        let smsMsg;
        if (isDisabled && !bankName && !accountNo) {
          smsMsg = `[콜로플라스트] ${patientName}님, 주문이 확정되었습니다.\n■ 제품 총 금액: ${fmtProduct}원\n■ 본인부담금: ${fmtCopay}원\n■ 입금금액: ${fmtDeposit}원 (본인부담금 + 배송비)\n입금 계좌는 별도 안내드리겠습니다.`;
        } else {
          smsMsg = `[콜로플라스트] ${patientName}님, 가상계좌가 발급되었습니다.\n■ 제품 총 금액: ${fmtProduct}원\n■ 본인부담금: ${fmtCopay}원\n■ 입금금액: ${fmtDeposit}원 (본인부담금 + 배송비)\n■ 은행: ${bankName}\n■ 계좌번호: ${accountNo}\n■ 입금기한: ${dueDate}\n기한 내 입금 부탁드립니다.`;
        }
        if (mobile && smsUrl) {
          fetch(smsUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
              'Accept': 'application/json',
            },
            body: JSON.stringify({ mobile, message: smsMsg }),
          }).then(r => r.json()).then(sd => {
            showToast(sd.success ? (isDisabled ? '✅ SMS 발송 완료 (가상계좌 비활성화)' : '✅ 가상계좌 발급 및 SMS 발송 완료') : `완료 (SMS 실패: ${sd.message})`, sd.success ? 'success' : 'warning');
            if (sd.success) { markSmsSent(); }
          }).catch(() => {
            showToast('완료 (SMS 발송 오류)', 'warning');
          });
        } else {
          showToast(isDisabled ? '✅ 가상계좌 발급 비활성화 — 번호 미입력으로 SMS 미발송' : '✅ 가상계좌가 발급되었습니다.', isDisabled ? 'warning' : 'success');
        }
      } else {
        showToast(data.message || '가상계좌 발급 실패', 'danger');
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="fa-solid fa-building-columns"></i> 발급 확인'; }
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="fa-solid fa-building-columns"></i> 발급 확인'; }
    }
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('vaPopover');
    const btn = document.getElementById('btnVaTrigger');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target) && e.target !== btn && !(btn && btn.contains(e.target))) {
      pop.style.display = 'none';
    }
  });

  // ── 가상계좌 입금 상태 확인 ───────────────────────────
  async function checkVaStatus(btn) {
    BtnState.loading(btn, '확인 중...');
    try {
      const res  = await fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (data.success) {
        if (data.status === 'DONE') {
          BtnState.success(btn, '입금 확인');
          showToast('✅ 입금이 확인되었습니다!', 'success');
          setTimeout(() => location.reload(), 1200);
        } else {
          showToast(`현재 상태: ${data.status_label}`, 'info');
          BtnState.reset(btn);
        }
      } else {
        showToast(data.message || '조회 실패', 'danger');
        BtnState.error(btn, '조회 실패');
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      BtnState.error(btn, '오류');
    }
  }

  /* 공단 청구를 여기서 송신하던 기능은 걷어냈다 — 청구는 공단 사이트에 직접 입력·업로드한다.
     남아 있던 호출부가 있으면 안내만 한다. */
  function submitNhis() {
    showToast('공단 청구는 요양기관정보마당에 직접 입력·업로드합니다.', 'info');
  }


  // ── OCR 재분석 ────────────────────────────────────────

  // ── 네비게이션 ────────────────────────────────────────
  const PREV_RX = @json($prevId);   // 더 최근 처방전 rx_number
  const NEXT_RX = @json($nextId);   // 더 오래된 처방전 rx_number

  /* 이전/다음은 새 탭이 아니라 현재 탭에서 넘기는 '페이징'이 맞다(클릭마다 탭이 늘면 안 됨).
     다만 그대로 이동하면 미저장 입력이 사라지므로 탭 전환과 동일하게 확인을 받는다. */
  async function _gotoRecord(rxNumber) {
    if (isAnyDirty()) {
      const ok = await ceConfirm(
        `저장하지 않은 변경이 있습니다. (${_dirtyLabel()})\n이동하면 변경 내용이 사라집니다.\n\n계속하시겠습니까?`,
        { tone: 'warning', confirmText: '이동' }
      );
      if (!ok) return;
      clearAllDirty();
    }
    /* 화면 전환은 브라우저에 맡긴다(@view-transition) — 흰 화면이 끼지 않는다.
       넘기는 동안 두 번 눌리지 않게 단추를 잠근다. 이동은 곧 일어나므로 되돌릴 일은 없다. */
    document.querySelectorAll('.vw-nav-btn').forEach(b => { b.disabled = true; });
    location.href = `${BASE_URL}/prescriptions/${encodeURIComponent(rxNumber)}`;
  }

  function prevRecord() {
    if (!PREV_RX) { showToast('첫 번째 처방전입니다.', 'info'); return; }
    _gotoRecord(PREV_RX);
  }
  function nextRecord() {
    if (!NEXT_RX) { showToast('마지막 처방전입니다.', 'info'); return; }
    _gotoRecord(NEXT_RX);
  }

  // 이전/다음 없을 때 버튼 비활성화
  document.addEventListener('DOMContentLoaded', () => {
    if (!PREV_RX) document.querySelectorAll('[onclick="prevRecord()"]').forEach(b => b.disabled = true);
    if (!NEXT_RX) document.querySelectorAll('[onclick="nextRecord()"]').forEach(b => b.disabled = true);

    /* 시안(148:2708 · 148:2827)이 같은 항목을 두 카드에 그려 두었다.
       한쪽에 적고 다른 쪽에 다르게 적으면 어느 값을 저장할지 알 수 없으므로,
       두 칸이 늘 같은 값을 보이게 서로 비춘다. 저장은 원래 칸 하나만 보낸다. */
    [['f-five', 'f-five-2'], ['f-diagnosis-date', 'f-diag-confirm-2'],
     ['f-nhis-agree-start', 'f-agree-start-2'], ['f-nhis-agree-end', 'f-agree-end-2']].forEach(([srcId, dupId]) => {
      const src = document.getElementById(srcId), dup = document.getElementById(dupId);
      if (!src || !dup) return;
      dup.value = src.value;
      src.addEventListener('input',  () => { dup.value = src.value; });
      src.addEventListener('change', () => { dup.value = src.value; });
      dup.addEventListener('input',  () => { src.value = dup.value; });
      dup.addEventListener('change', () => { src.value = dup.value; });
    });
  });

  function closeModal(id) { document.getElementById(id).classList.remove('show'); }

  // ── 상담번호 채번 ──────────────────────────────────────
  const COUNSEL_NO_URL = @json(route('prescriptions.counselNo', $prescription));

  async function generateCounselNo() {
    const btn = document.getElementById('btnCounselNo');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;"></span> 채번 중...';
    try {
      const res  = await fetch(COUNSEL_NO_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
      });
      const data = await res.json();
      if (data.success) {
        document.getElementById('f-counselling-no').value = data.counselling_no;
        // 상담 일자가 비어있으면 오늘로
        const dateEl = document.getElementById('f-counsel-date');
        if (!dateEl.value) dateEl.value = data.counsel_date;
        showToast(`상담번호 ${data.counselling_no} 채번 완료`, 'success');
      } else {
        showToast(data.message ?? '채번 실패', 'danger');
      }
    } catch (e) {
      showToast('채번 중 오류가 발생했습니다.', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }

  /* ── 상담이력 표시 공용 코드 ────────────────────────────
     '이전 상담 이력' 모달과 '환자 조회' 모달이 함께 쓰므로 조건부 블록 밖에 둔다.
     (환자 조회는 이전 상담이력이 없는 처방전에서도 열 수 있다) */
  const _RX_URL_BASE = @json(rtrim(url('/prescriptions'), '/'));

  const _PC_TYPE_MAP   = {'1013':'구매(CE)','1016':'개인구매','1020':'반품','1030':'문의','1050':'기타'};
  const _PC_ACC_MAP    = {'20':'처방외','10':'처방전-원외','30':'처방전-원내'};
  const _PC_STAT_MAP   = {'02':'등록','50':'재상담','95':'확정','99':'취소'};
  const _PC_STAT_COLOR = {'02':'var(--info)','50':'var(--warning)','95':'var(--success)','99':'var(--danger)'};
  const _PC_DIVER_MAP  = {'01':'1회 미만','02':'1~2회','03':'3~4회','04':'5회','05':'6회 이상','06':'N/A'};

  // ── 헬퍼 함수 ────────────────────────────────────────
  function _pcEsc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function _pcPhone(v) {
    if (!v) return null;
    const n = v.replace(/\D/g,'');
    if (n.length === 11) return n.slice(0,3)+'-'+n.slice(3,7)+'-'+n.slice(7);
    if (n.length >= 9)   return n.slice(0,3)+'-'+n.slice(3,6)+'-'+n.slice(6);
    return v;
  }
  function _pcMoney(v) { return v ? Number(v).toLocaleString('ko-KR')+'원' : null; }
  function _pcFR(label, val, full) {
    if (!val) return '';
    return `<div class="pc-field-row${full?' pc-field-full':''}">
      <span class="pc-field-label">${label}</span>
      <span class="pc-field-val">${_pcEsc(String(val))}</span>
    </div>`;
  }
  function _pcAcc(icon, color, title, bodyHtml, open) {
    return `<div class="hist-acc-item${open?' is-open':''}">
      <div class="hist-acc-header" onclick="this.closest('.hist-acc-item').classList.toggle('is-open');this.querySelector('.hist-ci').classList.toggle('open')">
        <span><i class="fa-solid fa-${icon}" style="color:${color};font-size:12px;"></i> ${title}</span>
        <i class="fa-solid fa-chevron-down hist-ci${open?' open':''}"></i>
      </div>
      <div class="hist-acc-body">${bodyHtml}</div>
    </div>`;
  }

  // ── 이전 상담 이력 모달 (이 환자에게 이력이 있을 때만) ──────
  @if($isReturningPatient)
  const _PREV_COUNSEL_LIST = @json($prevCounselingsData);

  function openPrevCounselModal() {
    document.getElementById('prevCounselModal').classList.add('show');
    // 첫 번째 항목 자동 선택
    if (_PREV_COUNSEL_LIST.length) selectPrevCounsel(0);
  }
  function closePrevCounselModal() {
    document.getElementById('prevCounselModal').classList.remove('show');
  }

  function selectPrevCounsel(idx) {
    document.querySelectorAll('.pc-list-item').forEach((el, i) => {
      el.style.background = i === idx ? 'var(--primary-light)' : '';
      el.style.borderLeft = i === idx ? '3px solid var(--primary)' : '3px solid transparent';
    });
    const d = _PREV_COUNSEL_LIST[idx];
    if (!d) return;
    const st = d.status ?? '';

    // ── 상담 정보 ─────────────────────────────────────
    let counselBody = `<div class="pc-field-grid">
      ${_pcFR('상담번호',    d.counselling_no)}
      ${_pcFR('상담 일자',   d.counsel_date || d.reg_date)}
      ${_pcFR('상담 유형',   _PC_TYPE_MAP[d.type??''] || d.type)}
      ${_pcFR('처방전 여부', _PC_ACC_MAP[d.acc_add_type??''] || d.acc_add_type)}
      ${_pcFR('상담 상태',   _PC_STAT_MAP[st] || st)}
      ${_pcFR('전화번호',    _pcPhone(d.call_no))}
      ${_pcFR('재상담 일자', d.re_counsel_date)}
      ${_pcFR('재구매 가능일', d.repurchase_date)}
    </div>
    <div style="margin-top:10px;padding:10px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;">
      <div style="font-size:10px;font-weight:700;color:var(--gray-600);margin-bottom:5px;"><i class="fa-solid fa-note-sticky"></i> 상담 메모</div>
      <div style="font-size:12px;line-height:1.8;white-space:pre-wrap;color:${d.contents ? 'var(--text-primary)' : 'var(--text-muted)'};">${d.contents ? _pcEsc(d.contents) : '(메모 없음)'}</div>
    </div>`;

    // ── 환자 정보 ─────────────────────────────────────
    let patientBody = `<div class="pc-field-grid">
      ${_pcFR('환자명',       d.patient_name_ocr)}
      ${_pcFR('연락처',       _pcPhone(d.mobile_ocr || d.call_no))}
      ${_pcFR('주민번호',     d.resident_no_masked)}
      ${_pcFR('보호자명',     d.udf24)}
      ${_pcFR('일일 도뇨횟수',_PC_DIVER_MAP[d.diverticulums??''] || d.diverticulums)}
      ${d.postcode ? _pcFR('우편번호', d.postcode) : ''}
      ${_pcFR('주소', [d.address_ocr, d.address_detail].filter(Boolean).join(' '), true)}
    </div>`;

    // ── 병원·처방 정보 ────────────────────────────────
    let hospitalBody = `<div class="pc-field-grid">
      ${_pcFR('병원명',       d.hospital_name)}
      ${_pcFR('요양병원코드', d.erp_cd9)}
      ${_pcFR('담당의사',     d.doctor_name || d.udf15)}
      ${_pcFR('처방전발행일', d.issued_date || d.udf12)}
      ${_pcFR('처방기간',     d.udf13 ? d.udf13+'일' : null)}
      ${_pcFR('처방종료일',   d.udf14)}
    </div>`;

    // ── 제품 구매 이력 ────────────────────────────────
    let purchaseBody = '';
    if (d.items && d.items.length) {
      const soTypeMap = {'1013':'CE 판매','1016':'개인판매','1022':'샘플판매'};
      const nhisMap   = {'Y':'급여','N':'비급여','':`-`};
      const purchaseDate = d.order?.created_at ?? null;
      purchaseBody += d.items.map((item, i) => `
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;margin-bottom:8px;background:var(--bg-card);">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
            <div>
              <span style="font-size:12px;font-weight:700;">${_pcEsc(item.product_name ?? '-')}</span>
              ${item.product_code ? `<span style="font-size:10px;color:var(--text-muted);margin-left:6px;">[${_pcEsc(item.product_code)}]</span>` : ''}
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
              <span style="font-size:11px;font-weight:700;color:var(--primary);">×${item.quantity ?? 1}</span>
              ${item.nhis_status ? `<span style="font-size:10px;padding:1px 7px;border-radius:999px;background:${item.nhis_status==='Y'?'var(--primary-50)':'var(--bg)'};color:${item.nhis_status==='Y'?'var(--primary)':'var(--text-muted)'};border:1px solid ${item.nhis_status==='Y'?'var(--primary-200)':'var(--border)'};">${nhisMap[item.nhis_status]??item.nhis_status}</span>` : ''}
            </div>
          </div>
          <div style="display:flex;gap:10px;font-size:11px;color:var(--text-secondary);flex-wrap:wrap;align-items:center;">
            ${purchaseDate ? `<span style="background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:1px 7px;color:var(--text-muted);"><i class="fa-regular fa-calendar" style="font-size:9px;margin-right:3px;"></i>구매일: <b>${_pcEsc(purchaseDate)}</b></span>` : ''}
            ${item.product_price  ? `<span>제품가: <b>${Number(item.product_price).toLocaleString('ko-KR')}원</b></span>` : ''}
            ${item.insurance_price? `<span>보험가: <b>${Number(item.insurance_price).toLocaleString('ko-KR')}원</b></span>` : ''}
            ${item.nhis_amount    ? `<span>급여액: <b>${Number(item.nhis_amount).toLocaleString('ko-KR')}원</b></span>` : ''}
            ${item.patient_copay  ? `<span>본인부담: <b style="color:var(--danger);">${Number(item.patient_copay).toLocaleString('ko-KR')}원</b></span>` : ''}
          </div>
        </div>`).join('');
    } else {
      purchaseBody += '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">등록된 제품이 없습니다.</div>';
    }
    if (d.order) {
      const soTypeMap = {'1013':'CE 판매','1016':'개인판매','1022':'샘플판매'};
      const orderStatusColor = {'pending':'var(--text-muted)','confirmed':'var(--primary)','shipping':'var(--info)','delivered':'var(--success)','cancelled':'var(--danger)'};
      purchaseBody += `<div style="margin-top:10px;padding:12px 14px;background:var(--primary-light);border:1px solid var(--primary-accent);border-radius:var(--radius);">
        <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:8px;display:flex;align-items:center;gap:5px;">
          <i class="fa-solid fa-cart-shopping"></i> 주문 정보
        </div>
        <div class="pc-field-grid">
          ${_pcFR('주문번호',   d.order.order_number)}
          ${_pcFR('판매유형',   soTypeMap[d.order.so_type] || d.order.so_type)}
          ${_pcFR('주문상태',   d.order.status_label || d.order.status)}
          ${_pcFR('주문일',     d.order.created_at)}
          ${_pcFR('총 금액',    d.order.total_amount ? Number(d.order.total_amount).toLocaleString('ko-KR')+'원' : null)}
          ${_pcFR('Withworks SO', d.order.withworks_so_no)}
        </div>
      </div>`;
    }

    // ── 위임동의 ─────────────────────────────────────
    const _PC_CONSENT_COLOR = {'agreed':'var(--primary)','pending':'var(--warning)','declined':'var(--danger)','expired':'var(--text-muted)'};
    let consentBody = '';
    if (d.consents && d.consents.length) {
      consentBody = d.consents.map((c, ci) => {
        const stColor = _PC_CONSENT_COLOR[c.status] || 'var(--text-muted)';
        return `<div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;${ci>0?'margin-top:8px':''}">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span style="font-size:12px;font-weight:700;color:${stColor};">${_pcEsc(c.status_label)}</span>
            ${c.pdf_path ? `<a href="${c.pdf_path}" target="_blank" style="font-size:11px;color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:4px;"><i class="fa-solid fa-file-pdf"></i> 동의서 PDF</a>` : ''}
          </div>
          <div class="pc-field-grid" style="margin-top:8px;">
            ${_pcFR('환자명', c.patient_name)}
            ${_pcFR('동의 일시', c.responded_at)}
            ${_pcFR('만료 일시', c.expires_at)}
          </div>
        </div>`;
      }).join('');
    } else {
      consentBody = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">위임동의 이력 없음</div>';
    }

    // ── 가상계좌 ─────────────────────────────────────
    let vaBody = '';
    if (d.order?.toss) {
      const t = d.order.toss;
      const isDone = t.is_done;
      const isExp  = t.is_expired;
      vaBody = `<div class="pc-field-grid">
        ${_pcFR('결제수단', t.method === 'VIRTUAL_ACCOUNT' ? '가상계좌' : (t.method ?? '-'))}
        ${_pcFR('상태', t.status_label)}
        ${_pcFR('은행', t.bank)}
        ${_pcFR('계좌번호', t.account_number)}
        ${_pcFR('예금주', t.customer_name)}
        ${_pcFR('금액', t.amount ? Number(t.amount).toLocaleString('ko-KR')+'원' : null)}
        ${_pcFR('입금 기한', t.due_date)}
        ${_pcFR('입금 완료', t.deposited_at)}
      </div>
      <div style="margin-top:8px;padding:6px 10px;border-radius:var(--radius);font-size:11px;font-weight:700;
                  background:${isDone?'var(--primary-50)':isExp?'var(--alert-50)':'var(--gray-100)'};
                  color:${isDone?'var(--primary)':isExp?'var(--danger)':'var(--gray-700)'};">
        ${isDone ? '<i class="fa-solid fa-circle-check"></i> 입금 완료' : isExp ? '<i class="fa-solid fa-clock"></i> 기한 만료' : '<i class="fa-solid fa-hourglass-half"></i> 입금 대기 중'}
      </div>`;
    } else {
      vaBody = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">가상계좌 결제 이력 없음</div>';
    }

    // ── 현금영수증 ────────────────────────────────────
    let crBody = '';
    if (d.order?.cash_receipt_status && d.order.cash_receipt_status !== 'not_issued') {
      const crStatusMap = {'issued':'발행 완료','not_issued':'미발행','cancelled':'취소','pending':'대기','failed':'실패'};
      const crTypeMap   = {'income_deduction':'소득공제 (개인)','business_expense':'지출증빙 (사업자)'};
      crBody = `<div class="pc-field-grid">
        ${_pcFR('상태',     crStatusMap[d.order.cash_receipt_status] || d.order.cash_receipt_status)}
        ${_pcFR('발행유형', crTypeMap[d.order.cash_receipt_type]     || d.order.cash_receipt_type)}
        ${_pcFR('승인번호', d.order.cash_receipt_no)}
        ${_pcFR('금액',     d.order.cash_receipt_amount ? Number(d.order.cash_receipt_amount).toLocaleString('ko-KR')+'원' : null)}
        ${_pcFR('발행일시', d.order.cash_receipt_issued_at)}
      </div>`;
    } else {
      crBody = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">현금영수증 이력 없음</div>';
    }

    // ── 팩스 이력 ─────────────────────────────────────
    const _FAX_STATE = {0:'대기',1:'전송 중',2:'전송 완료',3:'실패',4:'취소'};
    const _FAX_COLOR = {0:'var(--text-muted)',1:'var(--info)',2:'var(--primary)',3:'var(--danger)',4:'var(--text-muted)'};
    let faxBody = '';
    if (d.fax_histories && d.fax_histories.length) {
      faxBody = d.fax_histories.map((f, fi) => {
        const stLabel = _FAX_STATE[f.popbill_state] ?? '-';
        const stColor = _FAX_COLOR[f.popbill_state] ?? 'var(--text-muted)';
        return `<div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;${fi>0?'margin-top:8px':''}">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span style="font-size:12px;font-weight:700;color:${stColor};">${stLabel}</span>
            ${f.fax_no ? `<span style="font-size:11px;color:var(--text-secondary);font-family:monospace;">${_pcEsc(f.fax_no)}</span>` : ''}
          </div>
          <div class="pc-field-grid">
            ${_pcFR('제목',     f.title)}
            ${_pcFR('발송자',   f.sent_by_name)}
            ${_pcFR('예약 시각',f.reserve_dt)}
            ${_pcFR('동기화',   f.synced_at)}
            ${f.popbill_result !== null && f.popbill_result !== undefined ? _pcFR('결과코드', f.popbill_result) : ''}
          </div>
        </div>`;
      }).join('');
    } else {
      faxBody = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">팩스 발송 이력 없음</div>';
    }

    // ── 조립 ──────────────────────────────────────────
    // ── sticky 헤더 업데이트 ─────────────────────────
    const stickyHeader = document.getElementById('prevCounselStickyHeader');
    const patientName  = d.patient_name_ocr || '';
    document.getElementById('pcStickyNo').textContent   = d.counselling_no ?? '-';
    document.getElementById('pcStickyName').textContent = patientName ? `· ${patientName}` : '';
    document.getElementById('pcStickyRx').innerHTML     = d.rx_number
      ? `<i class="fa-solid fa-file-prescription" style="font-size:9px;"></i> ${_pcEsc(d.rx_number)}${d.rx_status_label ? ` <span style="padding:0 5px;background:var(--bg);border:1px solid var(--border);border-radius:999px;">${_pcEsc(d.rx_status_label)}</span>` : ''}`
      : '';
    // 다른 처방전으로 '이동'하지 않고 새 탭으로 열어, 현재 검수 화면의 입력을 잃지 않게 한다
    document.getElementById('pcStickyBtn').onclick = () => ceOpenTab(
      `${_RX_URL_BASE}/${encodeURIComponent(d.rx_number ?? '')}`,
      `주문 - ${d.rx_number ?? '신규'}`, 'file-edit-02');
    stickyHeader.style.display = 'block';

    // ── 스크롤 바디 ────────────────────────────────────
    document.getElementById('prevCounselBody').innerHTML = `
      ${_pcAcc('clipboard-list','var(--primary)',   '상담 정보',        counselBody,  true)}
      ${_pcAcc('user',          'var(--primary)',   '환자 정보',        patientBody,  true)}
      ${_pcAcc('hospital',      'var(--info)',      '병원 · 처방 정보', hospitalBody, false)}
      ${_pcAcc('box',           'var(--warning)',   '제품 구매 이력',   purchaseBody, true)}
      ${_pcAcc('file-signature','var(--primary)',   '위임동의',         consentBody,  false)}
      ${_pcAcc('university',    'var(--info)',      '가상계좌',         vaBody,       false)}
      ${_pcAcc('receipt',       'var(--primary)',   '현금영수증',       crBody,       false)}
      ${_pcAcc('fax',           'var(--text-secondary)', '팩스 이력',   faxBody,      false)}
    `;
  }
  @endif

  // ── 위임동의 SMS 발송 ─────────────────────────────────
  const CONSENT_SMS_URL    = @json(route('prescriptions.consentSms', $prescription));
  const CONSENT_STATUS_URL = @json(route('prescriptions.consentStatus', $prescription));

  // ── 서명 확인 팝오버 ─────────────────────────────────────
  function closeConsentSignPopover() {
    const pop = document.getElementById('consentSignPopover');
    if (pop) pop.style.display = 'none';
  }

  async function openConsentSignModal() {
    const pop     = document.getElementById('consentSignPopover');
    const loading = document.getElementById('csignLoading');
    const content = document.getElementById('csignContent');
    const errEl   = document.getElementById('csignError');

    closeAllPopovers();

    // 초기화
    loading.style.display = 'block';
    content.style.display = 'none';
    errEl.style.display   = 'none';
    pop.style.display = 'block';

    try {
      const res  = await fetch(CONSENT_STATUS_URL, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      });
      const data = await res.json();

      if (!data.exists || data.status !== 'agreed') {
        pop.style.display = 'none';
        openConsentModal();
        return;
      }

      // 정보 채우기
      document.getElementById('csignName').textContent   = data.patient_name   || '-';
      document.getElementById('csignMobile').textContent = data.patient_mobile  || '-';
      document.getElementById('csignTime').textContent   = data.responded_at
        ? data.responded_at.replace('T', ' ').slice(0, 19)
        : '-';

      // 서명 이미지
      const imgWrap = document.getElementById('csignImgWrap');
      const noSig   = document.getElementById('csignNoSig');
      const pngBtn  = document.getElementById('csignPngBtn');
      if (data.signature_data) {
        document.getElementById('csignImg').src = data.signature_data;
        imgWrap.style.display = 'block';
        noSig.style.display   = 'none';
        if (pngBtn) pngBtn.style.display = 'inline-flex';
      } else {
        imgWrap.style.display = 'none';
        noSig.style.display   = 'block';
        if (pngBtn) pngBtn.style.display = 'none';
      }

      // PDF 다운로드 버튼
      const pdfBtn = document.getElementById('csignPdfBtn');
      if (data.pdf_url) {
        pdfBtn.href = data.pdf_url;
        pdfBtn.style.display = 'inline-flex';
      } else {
        pdfBtn.style.display = 'none';
      }

      // 요양비 지급청구 위임장 PDF + 재생성 (서명이 있을 때만)
      const delegBtn = document.getElementById('csignDelegationBtn');
      const regenBtn = document.getElementById('csignRegenBtn');
      const hasSig = !!data.signature_data;
      if (delegBtn) delegBtn.style.display = hasSig ? 'inline-flex' : 'none';
      if (regenBtn) regenBtn.style.display = hasSig ? 'inline-flex' : 'none';
      const nhisBtn = document.getElementById('csignNhisBtn');
      if (nhisBtn) nhisBtn.style.display = hasSig ? 'inline-flex' : 'none';

      loading.style.display = 'none';
      content.style.display = 'block';
    } catch (_) {
      loading.style.display = 'none';
      errEl.style.display   = 'block';
    }
  }

  // 현재 위임장 설정으로 요양비위임장 재생성 → 첨부문서 갱신
  async function regenerateDelegation(btn) {
    if (!await ceConfirm('현재 위임장 설정(기관·계좌·서명위치)으로 요양비위임장을 다시 생성해 첨부문서에 반영할까요?',
                         { confirmText: '재생성' })) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 재생성 중...';
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message || '재생성 완료', 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(data.message || '재생성 실패', 'danger');
        btn.disabled = false; btn.innerHTML = orig;
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      btn.disabled = false; btn.innerHTML = orig;
    }
  }

  // 팩스통합본 재생성 (현재 데이터로, 요양비위임장 포함)
  async function regenerateFax(btn) {
    if (!await ceConfirm('현재 데이터로 팩스통합본을 다시 생성할까요? (요양비위임장 포함)',
                         { confirmText: '재생성' })) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 재생성 중...';
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message || '재생성 완료', 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(data.message || '재생성 실패', 'danger');
        btn.disabled = false; btn.innerHTML = orig;
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      btn.disabled = false; btn.innerHTML = orig;
    }
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('consentSignPopover');
    const btn = document.getElementById('consentActionBtn');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target) && e.target !== btn && !(btn && btn.contains(e.target))) {
      pop.style.display = 'none';
    }
  });

  function toggleConsentPopover(e) {
    e.stopPropagation();
    const pop = document.getElementById('consentPopover');
    if (!pop) return;
    if (pop.style.display !== 'none') { pop.style.display = 'none'; return; }
    openConsentModal();
  }

  function closeConsentPopover() {
    const pop = document.getElementById('consentPopover');
    if (pop) pop.style.display = 'none';
  }

  function openConsentModal(isResend = false) {
    const notice  = document.getElementById('consentResendNotice');
    const titleEl = document.getElementById('consentModalTitle');
    const iconEl  = document.getElementById('consentModalIcon');
    const sendBtn = document.getElementById('btnConsentSend');
    const result  = document.getElementById('consentSendResult');
    if (result)  result.style.display = 'none';
    if (sendBtn) sendBtn.disabled = false;

    if (isResend) {
      if (titleEl) titleEl.textContent = '위임동의 재발송';
      if (iconEl)  { iconEl.className = 'fa-solid fa-rotate-right'; iconEl.style.color = '#fff'; }
      if (notice)  notice.style.display = 'block';
      if (sendBtn) sendBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> 재발송';
    } else {
      if (titleEl) titleEl.textContent = '위임동의 SMS 발송';
      if (iconEl)  { iconEl.className = 'fa-solid fa-file-signature'; iconEl.style.color = '#fff'; }
      if (notice)  notice.style.display = 'none';
      if (sendBtn) sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 발송';
    }

    const mobileEl  = document.getElementById('consentMobile');
    const fMobileEl = document.getElementById('f-mobile');
    if (mobileEl) {
      const src = fMobileEl?.value?.trim() || mobileEl.value;
      mobileEl.value = formatPhone(src);
    }
    updateConsentPreview();

    closeAllPopovers();
    const pop = document.getElementById('consentPopover');
    if (pop) pop.style.display = 'block';
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('consentPopover');
    const btn = document.getElementById('consentActionBtn');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target) && e.target !== btn && !(btn && btn.contains(e.target))) {
      pop.style.display = 'none';
    }
  });

  function updateConsentPreview() {
    // 비워 두면 서버가 처방전 이름을 쓴다. 미리보기도 같은 값(placeholder)을 보여준다.
    const nameEl  = document.getElementById('consentPatientName');
    const name    = (nameEl?.value ?? '').trim() || (nameEl?.placeholder ?? '').trim() || '환자';
    const baseUrl = @json(rtrim(config('app.consent_public_url', config('app.url')), '/')).replace('http://', 'https://');
    const mockUrl = baseUrl + '/consent/(링크)';
    const preview = `[콜로플라스트] ${name}님\n건강보험 급여 위임동의 서명 요청입니다.\n서명 링크(30분 유효):\n${mockUrl}`;
    const el = document.getElementById('consentMsgPreview');
    if (el) el.textContent = preview;
  }

  function formatPhone(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length <= 3) return v;
    if (v.length <= 7) return v.slice(0,3) + '-' + v.slice(3);
    return v.slice(0,3) + '-' + v.slice(3,7) + '-' + v.slice(7);
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('consentMobile').addEventListener('input', function(e) {
      const pos = e.target.selectionStart;
      const prev = e.target.value;
      e.target.value = formatPhone(e.target.value);
      // keep cursor roughly at the same position
      const diff = e.target.value.length - prev.length;
      e.target.setSelectionRange(pos + diff, pos + diff);
    });
  });

  async function sendConsentSms() {
    const mobile = document.getElementById('consentMobile').value.trim();
    if (!mobile) { ceAlert('수신 번호를 입력해주세요.', { tone: 'warning' }); return; }
    if (mobile.replace(/\D/g, '').length < 9) {
      ceAlert('수신 번호를 다시 확인해주세요.', { tone: 'warning' }); return;
    }
    // 비워 두면 서버가 처방전에 적힌 이름을 쓴다
    const name = (document.getElementById('consentPatientName')?.value ?? '').trim();

    const btn = document.getElementById('btnConsentSend');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

    try {
      const res  = await fetch(CONSENT_SMS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ mobile, name }),
      });
      const data = await res.json();
      const box  = document.getElementById('consentSendResult');
      box.style.display = 'block';

      if (data.success) {
        box.style.background = 'var(--primary-50)';
        box.style.color      = 'var(--primary)';
        box.style.border     = '1px solid var(--primary-200)';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지`;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        updateConsentStatus();
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid var(--alert-100)';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      const box = document.getElementById('consentSendResult');
      box.style.display = 'block';
      box.style.background = 'var(--danger-light)';
      box.style.color = 'var(--danger)';
      box.style.border = '1px solid var(--alert-100)';
      box.textContent = '네트워크 오류가 발생했습니다.';
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
    }
  }

  // ── 위임동의 버튼 상태 업데이트 ────────────────────────────
  function _applyConsentBtn(status) {
    const bw  = document.getElementById('consentBtnWrap');
    const rb  = document.getElementById('consentResultBadge');
    if (!bw || !rb) return;
    const cfgMap = {
      agreed:  { bg:'var(--primary-50)', border:'var(--primary-200)', color:'var(--primary)', icon:'fa-circle-check',  text:'위임동의 완료',  action:'openConsentSignModal()', btnLabel:'서명확인', btnBorder:'var(--primary)', btnColor:'var(--primary)' },
      declined:{ bg:'var(--danger-light)',  border:'var(--alert-100)', color:'var(--danger)',  icon:'fa-circle-xmark',  text:'동의 거절됨',    action:'openConsentModal()',    btnLabel:'재발송',   btnBorder:'var(--danger)',  btnColor:'var(--danger)' },
      pending: { bg:'var(--gray-100)',      border:'var(--gray-300)', color:'var(--gray-700)', icon:'fa-clock',        text:'위임동의 대기중', action:'openConsentModal()',    btnLabel:'재발송',   btnBorder:'var(--gray-700)',       btnColor:'var(--gray-700)' },
      {{-- 만료는 시안의 '비활성' 조합(bg #F9FAFC · 글자 #999EA4). 대기중(gray-100)과 바탕이 겹치지 않게 한다 --}}
      expired: { bg:'var(--gray-50)',       border:'var(--border)', color:'var(--text-muted)', icon:'fa-ban',   text:'위임동의 만료',  action:'openConsentModal(true)',btnLabel:'재발송',   btnBorder:'var(--text-muted)', btnColor:'var(--text-muted)' },
    };
    const cfg = cfgMap[status];
    if (!cfg) return;
    bw.style.display = 'none';
    rb.style.display = 'flex';
    rb.style.alignItems = 'center';
    rb.style.gap = '4px';
    rb.style.padding = '4px 9px';
    rb.style.background = cfg.bg;
    rb.style.border = `1px solid ${cfg.border}`;
    rb.style.borderRadius = 'var(--radius)';
    rb.style.fontSize = '11px';
    rb.style.whiteSpace = 'nowrap';
    rb.innerHTML = `<i class="fa-solid ${cfg.icon}" style="color:${cfg.color};font-size:10px;"></i><span style="font-weight:700;color:${cfg.color};margin-left:2px;">${cfg.text}</span><button onclick="event.stopPropagation();${cfg.action}" style="height:16px;padding:0 5px;font-size:10px;background:none;border:1px solid ${cfg.btnBorder};color:${cfg.btnColor};border-radius:6px;cursor:pointer;margin-left:4px;">${cfg.btnLabel}</button>`;
  }

  /* 보호자 영역의 진행 상태 — 서명과 신분증을 받았는지.
     둘 다 서명 화면에서 들어오므로 여기서는 결과만 보여 준다. */
  function _guardianState(data) {
    const sign = document.getElementById('gbSignState');
    const idc  = document.getElementById('gbIdState');
    if (!sign || !idc) return;

    const hasSign = !!data.guardian_signature;
    const hasId   = !!data.guardian_id_url;

    sign.textContent = hasSign ? '위임장 서명 완료' : '위임장 서명 미완료';
    sign.className   = 'gb-state' + (hasSign ? ' done' : '');
    idc.textContent  = hasId ? '신분증 업로드 완료' : '신분증 업로드 미완료';
    idc.className    = 'gb-state' + (hasId ? ' done' : '');
  }

  /* 서명이 끝난 건에만 서명·신분증 카드를 세운다.
     신분증은 본문으로 오지 않는다 — 볼 때만 권한을 거치는 주소로 불러온다. */
  function _fillSignCard(data) {
    const card = document.getElementById('signCard');
    if (!card) return;

    if (data.status !== 'agreed' || !data.signature_data) {
      card.style.display = 'none';
      return;
    }
    card.style.display = '';
    document.getElementById('signCardImg').src = data.signature_data;

    const gWrap = document.getElementById('signCardGuardianWrap');
    if (data.guardian_signature) {
      document.getElementById('signCardGuardianImg').src = data.guardian_signature;
      const who = [data.guardian_name, data.guardian_relation].filter(Boolean).join(' · ');
      document.getElementById('signCardGuardianWho').textContent = who ? `(${who})` : '';
      gWrap.style.display = '';
    } else {
      gWrap.style.display = 'none';
    }

    const iWrap = document.getElementById('signCardIdWrap');
    if (data.guardian_id_url) {
      document.getElementById('signCardIdImg').src   = data.guardian_id_url;
      document.getElementById('signCardIdOpen').href = data.guardian_id_url;
      iWrap.style.display = '';
    } else {
      iWrap.style.display = 'none';
    }
  }

  async function updateConsentStatus() {
    try {
      const res  = await fetch(CONSENT_STATUS_URL, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
      const data = await res.json();
      if (!data.exists) return;

      // 상태 배지부터 세운다. 아래에서 무엇이 잘못돼도 이건 이미 그려져 있어야 한다.
      _applyConsentBtn(data.status);

      _fillSignCard(data);
      // 서명 화면에서 보호자가 적어 넣은 값이 있으면 화면에도 반영한다
      if (data.is_minor) {
        const set = (id, v) => { const el = document.getElementById(id); if (el && v && !el.value) el.value = v; };
        set('f-guardian-name',     data.guardian_name);
        set('f-guardian-relation', data.guardian_relation);
        set('f-guardian-birth',    data.guardian_birth_date);
        set('f-guardian-phone',    data.guardian_phone);
      }
      _guardianState(data);

      // 아코디언 안에 현황을 적던 자리(consentStatusText · consentStatusBadge)는
      // 시안 개편 때 없어졌다. 서명 여부·본인확인은 '서명확인' 버튼이 여는 창에서 본다.
      // 남아 있던 그 코드가 없는 요소를 가드 없이 만져 TypeError 를 냈고,
      // 아래 catch 가 그것을 삼켜 위의 _applyConsentBtn() 까지 닿지 못했다.
      // 그래서 서명이 끝난 처방전도 배지 없이 '위임동의' 버튼만 보였다.
    } catch (e) {
      // 조용히 삼키면 같은 일이 또 숨는다. 화면은 그대로 두되 흔적은 남긴다.
      console.error('[위임동의] 현황 확인 실패', e);
    }
  }

  // 페이지 로드 시 동의 현황 즉시 확인
  updateConsentStatus();

  // ── 위임동의 실시간 결과 수신 (Pusher → layout에서 발송) ──
  window.addEventListener('ce:consentResult', function (e) {
    const data = e.detail;
    if (!data || data.rx_number !== @json($prescription->rx_number)) return;

    // 버튼 즉시 업데이트 (아코디언 안 현황 자리는 시안 개편 때 없어졌다)
    _applyConsentBtn(data.status);

    // 서명 완료 시 생성 서류(요양비위임장 등) 실시간 반영
    if (data.status === 'agreed') {
      refreshGeneratedDocs();
    }
  });

  // 생성 서류 목록을 서버에서 다시 받아 갱신 (새로고침 없이)
  let _genDocsTries = 0;
  async function refreshGeneratedDocs() {
    try {
      const res = await fetch(@json(route('prescriptions.generatedDocs', $prescription)), {
        headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const html = (await res.text()).trim();
      const container = document.getElementById('genDocsContainer');
      if (!container) return;
      const hadDeleg = html.includes('요양비위임장');
      container.innerHTML = html;
      // 서명 직후 서버에서 아직 위임장 생성 중이면 잠깐 뒤 재시도 (최대 3회)
      if (!hadDeleg && _genDocsTries < 3) {
        _genDocsTries++;
        setTimeout(refreshGeneratedDocs, 1500);
      } else {
        _genDocsTries = 0;
        if (hadDeleg && typeof showToast === 'function') showToast('요양비위임장이 생성 서류에 추가되었습니다.', 'success');
      }
    } catch (e) { /* 무시 */ }
  }

  // ── 제품 자동완성 (Todoworks API) ─────────────────────
  /* 제품 찾기 — 검색 단추를 누르면 그 자리 옆에 창이 열린다.
     치는 대로 따라 내려오던 목록은 없앴다. 두 글자에도 창고를 찾으러 가느라 느렸고,
     펼쳐진 목록이 아래 칸을 가렸다. 세 글자 미만은 찾지 않는다 — 두 글자로 찾으면
     수백 건이 쏟아져 고르지 못하고, 그 조회가 창고를 붙들어 다음 조회까지 늦춘다. */
  const _pacCache  = {};   // 검색 결과 캐시(같은 말로 다시 찾지 않는다)
  const _pacNotice = {};   // 다 보여 주지 못한 검색의 안내
  const _pacFound  = {};   // 방금 창에 보인 것 — 고른 뒤 값을 꺼내 쓴다
  const _prodModal = new GridModal();

  function pacSearchBtn(idx, btn) {
    const inp = document.getElementById(`pac-input-${idx}`);
    if (!inp) return;

    _prodModal.open({
      title: '제품 조회', width: 460, height: 360,
      mode: 'popover', anchor: btn || inp,
      minChars: 3,
      query: inp.value.trim(),
      onSearch: async (kw) => {
        const key = kw.toLowerCase();
        let data = _pacCache[key];
        if (!data) {
          const res = await apiRequest(`/products/search?q=${encodeURIComponent(kw)}`, 'GET');
          if (!res.success) throw new Error(res.message || '조회 실패');
          data = res.data ?? [];
          _pacCache[key] = data;
          _pacNotice[key] = res.notice ?? null;
        }
        /* 창고가 한 번에 다 주지 않는다. 못 보여 준 것이 있으면 건수 자리에 그대로 적는다 —
           「30건」만 보이면 그게 전부인 줄 알고 없는 제품이라 여긴다.
           창이 건수를 적은 뒤에 덮어써야 하므로 다음 차례로 미룬다. */
        if (_pacNotice[key]) {
          const notice = _pacNotice[key];
          setTimeout(() => {
            const c = document.querySelector('.cg-modal-counter');
            if (c) { c.textContent = notice; c.style.color = '#B54708'; }
          }, 0);
        }
        _pacFound[idx] = {};
        return data.map((it) => {
          const code = it.code ?? it.name;
          _pacFound[idx][code] = it;
          return {
            value: code,
            label: it.name ?? '',
            sub: [it.code, it.spec, it.unit,
                  it.r_box ? 'R-Box ' + it.r_box : null,
                  it.price ? '₩ ' + Number(it.price).toLocaleString() : null]
                 .filter(Boolean).join(' · '),
          };
        });
      },
      onConfirm: (code) => applyProduct(idx, _pacFound[idx]?.[code]),
    });
  }

  /* 고른 제품을 그 행에 앉힌다. 재고는 여기서 한 건만 따로 묻는다 —
     창고의 재고 조회는 한 건에 7초라, 목록을 만들 때 모두 물으면 검색 자체가 끊긴다. */
  function applyProduct(idx, p) {
    if (!p) return;
    const code  = p.code ?? '';
    const name  = p.name ?? '';
    const price = parseFloat(p.price) || 0;
    const rbox  = p.r_box ?? '';

    const card = document.querySelector(`.item-card[data-idx="${idx}"]`);
    if (!card) return;

    card.querySelector('.item-name').value    = name;
    card.querySelector('.item-code').value    = code;
    card.querySelector('.item-display').value = name + (code ? ` (${code})` : '');
    card.querySelector('.item-rbox').value    = rbox;
    /* 찾을 때 재고까지 함께 받았으면 그대로 쓴다 — 방금 받은 것을 버리고 다시 묻지 않는다.
       예전 API 로 물러선 경우에는 재고가 없으므로 그때만 따로 묻는다. */
    const stock = (p.stock ?? '') === '' ? '' : String(p.stock);
    card.querySelector('.item-stock').value   = stock;
    if (price) {
      card.querySelector('.item-price').value     = fmtPrice(price);
      card.querySelector('.item-ins-price').value = fmtPrice(price);
    }
    updateItemMeta(idx, rbox, stock);
    calcItem(idx);

    if (code && stock === '') {
      apiRequest(`/products/stock?code=${encodeURIComponent(code)}`, 'GET')
        .then(res => {
          if (res.success && res.qty !== null) {
            const qty = String(res.qty);
            card.querySelector('.item-stock').value = qty;
            updateItemMeta(idx, rbox, qty);
          }
        }).catch(() => {});
    }

    showToast(`"${name}" 선택됨`, 'success');
  }

  // 구 팝업 호환
  function openProductSearch(idx) { pacSearchBtn(idx); }
  // 예전 이름으로 부르는 자리가 남아 있어도 죽지 않게 둔다
  function pacClose() {}

  // XSS 방지용 HTML 이스케이프
  function escHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ── 재고 수량 비동기 조회 ─────────────────────────────────
  async function fetchProductStock(itemCode, badgeId) {
    const badge = document.getElementById(badgeId);
    if (!badge) return;
    try {
      const res = await apiRequest(`/products/stock?code=${encodeURIComponent(itemCode)}`, 'GET');
      if (res.success && res.qty !== null) {
        const qty = Number(res.qty);
        const color = qty > 0 ? 'var(--primary)' : 'var(--danger)';
        badge.style.background    = qty > 0 ? 'var(--primary-50)' : 'var(--danger-light)';
        badge.style.borderColor   = color;
        badge.style.color         = color;
        badge.innerHTML = `재고: <b>${qty.toLocaleString()}</b>`;
        // 카드 data 속성에도 저장 (선택 시 읽기용)
        badge.closest('[data-code]')?.setAttribute('data-stock', String(qty));
      } else {
        badge.innerHTML = '재고: -';
      }
    } catch {
      badge.innerHTML = '재고: -';
    }
  }

  // 선택된 아이템 카드의 메타(R-Box / 재고) 정보 표시 업데이트
  function updateItemMeta(idx, rbox, stock) {
    const rboxField = document.getElementById(`item-rbox-field-${idx}`);
    if (rboxField) {
      const disp = rboxField.querySelector('.item-rbox-display');
      if (disp) disp.textContent = rbox || '';
      rboxField.style.display = rbox ? 'flex' : 'none';
    }
    const meta = document.getElementById(`item-meta-${idx}`);
    if (!meta) return;
    meta.innerHTML = stock ? `<span style="background:var(--primary-50);color:var(--primary);padding:1px 8px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fa-solid fa-layer-group" style="font-size:9px;margin-right:3px;"></i>재고: ${Number(stock).toLocaleString()}</span>` : '';
    meta.style.display = stock ? 'flex' : 'none';
  }

  // ── 초기 렌더 ─────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    renderItems();
    recalcAllItems();   // 로드 시 per-item 급여구분 기준으로 금액 재계산
    calcNextRepurchase(); // 다음재구매일 초기 자동 계산
  });

  // ── 세금계산서 / 현금영수증 ───────────────────────────
  let _ORDER_ID      = {{ $prescription->order?->id ?? 0 }};
  let _ORDER_TOTAL   = {{ (int)($prescription->order?->total_amount   ?? 0) }};
  let _PATIENT_COPAY = {{ (int)($prescription->order?->patient_copay  ?? 0) }};
  const _PATIENT_MOBILE = @json($prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '');

  // ── 현금영수증 상태 ───────────────────────────────────
  let _cr = {
    status:      @json($prescription->order?->cash_receipt_status ?? ''),
    no:          @json($prescription->order?->cash_receipt_no ?? ''),
    type:        @json($prescription->order?->cash_receipt_type ?? ''),
    identifier:  @json($prescription->order?->cash_receipt_identifier ?? ''),
    amount:      {{ (int)($prescription->order?->cash_receipt_amount ?? 0) }},
    issuedAt:    @json($prescription->order?->cash_receipt_issued_at?->format('Y-m-d H:i') ?? ''),
    orderNo:     @json($prescription->order?->order_number ?? ''),
    patientName: @json($prescription->patient?->name ?? $prescription->patient_name_ocr ?? ''),
  };

  function renderCashReceiptArea() {
    const area = document.getElementById('cashReceiptArea');
    if (!area) return;
    if (_cr.status === 'issued') {
      area.innerHTML = `
        <div style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:8px 10px;font-size:11px;">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <i class="fa-solid fa-circle-check" style="color:var(--primary);"></i>
            <span style="font-weight:700;color:var(--primary);flex:1;">현금영수증 발행완료</span>
            <button onclick="toggleCrDetailPopover(event)" style="height:20px;padding:0 7px;font-size:10px;background:none;border:1px solid var(--primary);color:var(--primary);border-radius:4px;cursor:pointer;">상세</button>
            <button onclick="cancelCashReceipt()" style="height:20px;padding:0 7px;font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:4px;cursor:pointer;">취소</button>
          </div>
          <div style="color:var(--text-muted);">No: ${_cr.no} · ${_cr.issuedAt.substring(0, 10)}</div>
        </div>`;
    } else {
      area.innerHTML = `
        <button class="btn btn-outline w-full" onclick="toggleCrIssuePopover(event)" style="justify-content:center;">
          <i class="fa-solid fa-receipt"></i> 현금영수증 발행
        </button>`;
    }
  }

  function toggleCrDetailPopover(e) {
    e.stopPropagation();
    const pop = document.getElementById('crDetailPopover');
    if (pop.style.display !== 'none') { pop.style.display = 'none'; return; }
    closeAllPopovers();
    document.getElementById('cr-d-no').textContent         = _cr.no;
    document.getElementById('cr-d-type').textContent       = _cr.type === 'income_deduction' ? '소득공제' : '지출증빙';
    document.getElementById('cr-d-identifier').textContent = _cr.identifier;
    document.getElementById('cr-d-amount').textContent     = '₩' + Number(_cr.amount).toLocaleString('ko-KR');
    document.getElementById('cr-d-issued-at').textContent  = _cr.issuedAt;
    document.getElementById('cr-d-order-no').textContent   = _cr.orderNo;
    document.getElementById('cr-d-patient').textContent    = _cr.patientName;
    const rect = e.currentTarget.getBoundingClientRect();
    pop.style.top  = (rect.bottom + 8) + 'px';
    const left = Math.min(rect.left, window.innerWidth - 310);
    pop.style.left = Math.max(4, left) + 'px';
    const arrow = document.getElementById('crDetailPopoverArrow');
    if (arrow) arrow.style.left = (rect.left - Math.max(4, left) + rect.width / 2 - 7) + 'px';
    pop.style.display = 'block';
  }

  function closeCrDetailPopover() {
    document.getElementById('crDetailPopover').style.display = 'none';
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('crDetailPopover');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target)) {
      pop.style.display = 'none';
    }
  });

  function printCashReceipt() {
    const typeLabel = _cr.type === 'income_deduction' ? '소득공제' : '지출증빙';
    const amount    = Number(_cr.amount).toLocaleString('ko-KR');
    const html = `<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>현금영수증</title>
<style>
  @page { margin: 10mm; size: A6 portrait; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: '맑은 고딕', 'Malgun Gothic', sans-serif; font-size: 12px; color: #111; padding: 12px; }
  .title { text-align: center; font-size: 20px; font-weight: 700; letter-spacing: 4px; padding: 10px 0 6px; border-bottom: 2px solid #111; margin-bottom: 12px; }
  .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th { width: 38%; padding: 7px 4px; font-weight: 600; color: #444; text-align: left; border-bottom: 1px solid #ddd; }
  td { padding: 7px 4px; border-bottom: 1px solid #ddd; }
  .amount { font-size: 16px; font-weight: 700; }
  .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #888; border-top: 1px dashed #ccc; padding-top: 10px; }
</style>
</head>
<body>
<div class="title">현금영수증</div>
<div class="subtitle">국세청 현금영수증 발행 확인증</div>
<table>
  <tr><th>승인번호</th><td><b>${_cr.no}</b></td></tr>
  <tr><th>거래유형</th><td>${typeLabel}</td></tr>
  <tr><th>식별번호</th><td>${_cr.identifier}</td></tr>
  <tr><th>거래금액</th><td class="amount">₩${amount}</td></tr>
  <tr><th>발행일시</th><td>${_cr.issuedAt}</td></tr>
  <tr><th>주문번호</th><td>${_cr.orderNo}</td></tr>
  <tr><th>고객명</th><td>${_cr.patientName}</td></tr>
</table>
<div class="footer">본 영수증은 소득공제·지출증빙용으로 사용하실 수 있습니다.</div>
</body>
</html>`;
    const w = window.open('', '_blank', 'width=420,height=600,scrollbars=no');
    w.document.write(html);
    w.document.close();
    w.focus();
    w.onload = () => { w.print(); };
  }

  function toggleTaxInvoicePopover(e) {
    e.stopPropagation();
    const pop = document.getElementById('taxInvoicePopover');
    if (!pop) return;
    if (pop.style.display !== 'none') { pop.style.display = 'none'; return; }
    openTaxInvoiceModal();
  }

  function closeTaxInvoicePopover() {
    const pop = document.getElementById('taxInvoicePopover');
    if (pop) pop.style.display = 'none';
  }

  function openTaxInvoiceModal() {
    const savedSupply = {{ (int)($prescription->order?->tax_invoice_supply ?? 0) }};
    const savedVat    = {{ (int)($prescription->order?->tax_invoice_vat    ?? 0) }};
    const supply = savedSupply || Math.round(_ORDER_TOTAL / 1.1);
    const vat    = savedVat    || (_ORDER_TOTAL - Math.round(_ORDER_TOTAL / 1.1));

    document.getElementById('ti-type').value     = @json($prescription->order?->tax_invoice_type     ?? 'electronic');
    document.getElementById('ti-biz-name').value = @json($prescription->order?->tax_invoice_biz_name ?? '');
    document.getElementById('ti-ceo-name').value = @json($prescription->order?->tax_invoice_ceo_name ?? '');
    document.getElementById('ti-email').value    = @json($prescription->order?->tax_invoice_email    ?? '');
    document.getElementById('ti-supply').value   = supply ? supply.toLocaleString('ko-KR') : '';
    document.getElementById('ti-vat').value      = vat    ? vat.toLocaleString('ko-KR')    : '';

    /* 이 화면은 환자가 사 간 건이라 개인이 기본이다. 지난 발행이 사업자였을 때만 사업자로 연다.
       주민번호는 마스킹해 저장하므로 그대로 다시 보내면 안 된다 — 비워 두고 서버가 꺼내 쓰게 한다. */
    const savedBizNo = @json($prescription->order?->tax_invoice_biz_no ?? '');
    const wasBiz     = /^\d{10}$/.test(String(savedBizNo).replace(/\D/g, ''));
    document.getElementById('ti-invoicee').value = wasBiz ? '사업자' : '개인';
    document.getElementById('ti-biz-no').value   = wasBiz ? savedBizNo : '';
    tiInvoiceeChanged();

    closeAllPopovers();
    const pop = document.getElementById('taxInvoicePopover');
    if (pop) pop.style.display = 'block';
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('taxInvoicePopover');
    const btn = document.getElementById('btnTiTrigger');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target) && e.target !== btn && !(btn && btn.contains(e.target))) {
      pop.style.display = 'none';
    }
  });

  function autoCalcVat() {
    const supply = parseInt(document.getElementById('ti-supply').value.replace(/,/g, '')) || 0;
    const vat    = Math.round(supply * 0.1);
    document.getElementById('ti-vat').value = vat ? vat.toLocaleString('ko-KR') : '';
  }

  /* 발행하면 팩스 서류 목록의 '세금계산서' 를 바로 고를 수 있게 연다. */
  function setFaxTaxInvoiceState(issued, tiNo) {
    const label = document.getElementById('fax-ti-label');
    const chk   = document.getElementById('fax-doc-tax-invoice');
    const badge = document.getElementById('fax-ti-badge');
    const desc  = document.getElementById('fax-ti-desc');
    if (!label || !chk) return;
    label.style.cursor  = issued ? 'pointer' : 'default';
    label.style.opacity = issued ? '1' : '0.5';
    chk.disabled        = !issued;
    chk.checked         = !!issued;
    if (badge) {
      badge.textContent   = issued ? '발행완료' : '미발행';
      badge.style.cssText = issued
        ? 'font-size:10px;border-radius:6px;padding:1px 6px;background:var(--primary-50);color:var(--primary-600);border:1px solid var(--primary-200);'
        : 'font-size:10px;border-radius:6px;padding:1px 6px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-300);';
    }
    if (desc) desc.textContent = issued
      ? (tiNo ? `승인번호: ${tiNo}` : '발행완료')
      : '세금계산서 발행 후 선택 가능';
  }

  /* 개인이면 사업자번호 자리가 주민등록번호가 된다. 비워 두면 서버가 처방전의
     주민번호를 꺼내 쓰므로, 번호를 화면으로 내려보내지 않는다. */
  function tiInvoiceeChanged() {
    const isPerson = document.getElementById('ti-invoicee').value === '개인';
    const label = document.getElementById('ti-biz-no-label');
    const input = document.getElementById('ti-biz-no');
    const hint  = document.getElementById('ti-biz-no-hint');
    label.innerHTML = isPerson
      ? '주민등록번호'
      : '사업자등록번호 <span style="color:var(--danger);">*</span>';
    input.placeholder = isPerson ? '비워 두면 환자 주민등록번호로 발행' : '123-45-67890';
    hint.style.display = isPerson ? 'block' : 'none';
    if (isPerson && !document.getElementById('ti-biz-name').value.trim()) {
      const name = document.getElementById('f-name')?.value?.trim();
      if (name) {
        document.getElementById('ti-biz-name').value = name;
        document.getElementById('ti-ceo-name').value = name;
      }
    }
  }

  async function submitTaxInvoice() {
    if (!_ORDER_ID) { showToast('주문 생성 후 발행 가능합니다.', 'danger'); return; }
    const btn      = document.getElementById('btnSubmitTaxInvoice');
    const invoicee = document.getElementById('ti-invoicee').value;
    const bizName  = document.getElementById('ti-biz-name').value.trim();
    const ceoName  = document.getElementById('ti-ceo-name').value.trim();
    const bizNo    = document.getElementById('ti-biz-no').value.trim();
    const supply   = document.getElementById('ti-supply').value.replace(/,/g, '');
    const vat      = document.getElementById('ti-vat').value.replace(/,/g, '');
    if (!bizName) { showToast('공급받는자 상호를 입력하세요.', 'danger'); return; }
    if (!ceoName) { showToast('대표자명을 입력하세요.', 'danger'); return; }
    if (invoicee === '사업자' && !bizNo) { showToast('사업자등록번호를 입력하세요.', 'danger'); return; }
    if (!supply)  { showToast('공급가액을 입력하세요.', 'danger'); return; }

    BtnState.loading(btn, '발행 중...');
    const res = await apiRequest(`/orders/${_ORDER_ID}/tax-invoice`, 'POST', {
      tax_invoice_type:     document.getElementById('ti-type').value,
      tax_invoice_invoicee: invoicee,
      tax_invoice_biz_name: bizName,
      tax_invoice_ceo_name: ceoName,
      tax_invoice_biz_no:   bizNo,
      tax_invoice_email:    document.getElementById('ti-email').value.trim() || null,
      tax_invoice_supply:   supply,
      tax_invoice_vat:      vat,
    });
    BtnState.reset(btn, '<i class="fa-solid fa-file-invoice"></i> 발행');

    if (res.success) {
      closeTaxInvoicePopover();
      const tiWrap = document.getElementById('tiNotIssuedWrap');
      const tiRb   = document.getElementById('tiResultBadge');
      if (tiWrap) tiWrap.style.display = 'none';
      if (tiRb)   tiRb.style.display   = 'flex';
      setFaxTaxInvoiceState(true, res.tax_invoice_no);
      showToast(`✅ 세금계산서 발행 완료 (${res.tax_invoice_no})`, 'success');
    } else {
      showToast(res.message || '발행 실패', 'danger');
    }
  }

  function showDangerConfirm(title, msg, onConfirm) {
    document.getElementById('dangerConfirmTitle').textContent = title;
    document.getElementById('dangerConfirmMsg').textContent   = msg;
    const okBtn = document.getElementById('dangerConfirmOkBtn');
    okBtn.onclick = () => { closeDangerConfirm(); onConfirm(); };
    document.getElementById('dangerConfirmModal').classList.add('show');
  }

  function closeDangerConfirm() {
    document.getElementById('dangerConfirmModal').classList.remove('show');
  }

  async function cancelTaxInvoice() {
    if (!_ORDER_ID) return;
    showDangerConfirm('세금계산서 취소', '세금계산서를 취소하시겠습니까?', async () => {
      const res = await apiRequest(`/orders/${_ORDER_ID}/tax-invoice`, 'DELETE');
      if (res.success) {
        showToast('세금계산서가 취소되었습니다.', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(res.message || '취소 실패', 'danger');
      }
    });
  }

  function formatCrIdentifier(input) {
    const raw = input.value.replace(/\D/g, '');
    if (raw.startsWith('0')) {
      // 휴대폰: 3-4-4 (11자리) or 3-3-4 (10자리)
      if (raw.length <= 3) {
        input.value = raw;
      } else if (raw.length <= 6) {
        input.value = raw.slice(0,3) + '-' + raw.slice(3);
      } else if (raw.length <= 10) {
        input.value = raw.slice(0,3) + '-' + raw.slice(3,6) + '-' + raw.slice(6);
      } else {
        input.value = raw.slice(0,3) + '-' + raw.slice(3,7) + '-' + raw.slice(7,11);
      }
    } else {
      // 사업자번호: 3-2-5
      if (raw.length <= 3) {
        input.value = raw;
      } else if (raw.length <= 5) {
        input.value = raw.slice(0,3) + '-' + raw.slice(3);
      } else {
        input.value = raw.slice(0,3) + '-' + raw.slice(3,5) + '-' + raw.slice(5,10);
      }
    }
  }

  function formatCrAmount(input) {
    const raw = input.value.replace(/[^\d]/g, '');
    input.value = raw ? Number(raw).toLocaleString('ko-KR') : '';
  }

  function toggleCrIssuePopover(e) {
    e.stopPropagation();
    const pop = document.getElementById('crIssuePopover');
    if (!pop) return;
    if (pop.style.display !== 'none') { pop.style.display = 'none'; return; }
    openCashReceiptModal(e.currentTarget);
  }

  function closeCrIssuePopover() {
    const pop = document.getElementById('crIssuePopover');
    if (pop) pop.style.display = 'none';
  }

  function openCashReceiptModal(triggerEl) {
    const savedId   = @json($prescription->order?->cash_receipt_identifier ?? '');
    const savedAmt  = {{ (int)($prescription->order?->cash_receipt_amount ?? 0) }};
    const savedType = @json($prescription->order?->cash_receipt_type ?? '');

    const panelCrNo     = (document.getElementById('f-cash-receipt')?.value ?? '').trim();
    const currentMobile = (document.getElementById('f-mobile')?.value ?? '').trim();
    const idEl = document.getElementById('cr-identifier');
    idEl.value = savedId || panelCrNo || currentMobile || _PATIENT_MOBILE;
    formatCrIdentifier(idEl);
    const livecopay = items.reduce((s, i) => s + (Number(i.patient_copay) || 0), 0);
    const crAmtRaw = savedAmt || livecopay || _PATIENT_COPAY || '';
    document.getElementById('cr-amount').value = crAmtRaw ? Number(crAmtRaw).toLocaleString('ko-KR') : '';
    if (savedType) {
      const radio = document.querySelector(`input[name="cr-type"][value="${savedType}"]`);
      if (radio) radio.checked = true;
    }
    const noticeEl = document.getElementById('cr-no-order-notice');
    if (noticeEl) noticeEl.style.display = 'none';

    closeAllPopovers();
    const pop = document.getElementById('crIssuePopover');
    if (!pop) return;
    if (triggerEl) {
      const rect = triggerEl.getBoundingClientRect();
      pop.style.top  = (rect.bottom + 8) + 'px';
      const left = Math.min(rect.left, window.innerWidth - 350);
      pop.style.left = Math.max(4, left) + 'px';
      const arrow = document.getElementById('crIssuePopoverArrow');
      if (arrow) arrow.style.left = (rect.left - Math.max(4, left) + rect.width / 2 - 7) + 'px';
    }
    pop.style.display = 'block';
  }

  document.addEventListener('click', e => {
    const pop = document.getElementById('crIssuePopover');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target)) {
      const btn = document.getElementById('btnCrIssueTrigger');
      if (btn && btn.contains(e.target)) return;
      pop.style.display = 'none';
    }
  });

  async function submitCashReceipt() {
    const noticeEl = document.getElementById('cr-no-order-notice');
    if (noticeEl) noticeEl.style.display = 'none';

    if (!_ORDER_ID) {
      if (noticeEl) noticeEl.style.display = 'block';
      return;
    }

    const btn        = document.getElementById('btnSubmitCashReceipt');
    const identifier = document.getElementById('cr-identifier').value.replace(/\D/g, '');
    const amount     = document.getElementById('cr-amount').value.replace(/,/g, '');
    const type       = document.querySelector('input[name="cr-type"]:checked')?.value;
    if (!type)       { showToast('유형을 선택하세요.', 'danger'); return; }
    if (!identifier) { showToast('휴대폰번호 또는 사업자번호를 입력하세요.', 'danger'); return; }
    if (!amount)     { showToast('금액을 입력하세요.', 'danger'); return; }

    BtnState.loading(btn, '발행 중...');
    const res = await apiRequest(`/orders/${_ORDER_ID}/cash-receipt`, 'POST', {
      cash_receipt_type:       type,
      cash_receipt_identifier: identifier,
      cash_receipt_amount:     amount,
    });
    BtnState.reset(btn);

    if (res.success) {
      _cr = {
        ..._cr,
        status:     'issued',
        no:         res.cash_receipt_no,
        type:       type,
        identifier: identifier,
        amount:     parseInt(amount) || 0,
        issuedAt:   res.issued_at ?? '',
      };
      renderCashReceiptArea();
      syncFaxCrState(true, res.cash_receipt_no);
      closeCrIssuePopover();
      showToast(`✅ 현금영수증 발행 완료 (${res.cash_receipt_no})`, 'success');
    }
  }

  function syncFaxCrState(issued, crNo) {
    const label   = document.getElementById('fax-cr-label');
    const chk     = document.getElementById('fax-doc-cash-receipt');
    const badge   = document.getElementById('fax-cr-badge');
    const desc    = document.getElementById('fax-cr-desc');
    if (!label) return;
    if (issued) {
      label.style.cursor  = 'pointer';
      label.style.opacity = '1';
      chk.disabled        = false;
      chk.checked         = true;
      badge.textContent   = '발행완료';
      badge.style.cssText = 'font-size:10px;border-radius:6px;padding:1px 6px;background:var(--primary-50);color:var(--primary-600);border:1px solid var(--primary-200);';
      desc.textContent    = crNo ? `승인번호: ${crNo}` : '발행완료';
    } else {
      label.style.cursor  = 'default';
      label.style.opacity = '0.5';
      chk.disabled        = true;
      chk.checked         = false;
      badge.textContent   = '미발행';
      badge.style.cssText = 'font-size:10px;border-radius:6px;padding:1px 6px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-300);';
      desc.textContent    = '현금영수증 발행 후 선택 가능';
    }
  }

  async function cancelCashReceipt() {
    if (!_ORDER_ID) return;
    showDangerConfirm('현금영수증 취소', '현금영수증을 취소하시겠습니까?', async () => {
      const res = await apiRequest(`/orders/${_ORDER_ID}/cash-receipt`, 'DELETE');
      if (res.success) {
        _cr = { ..._cr, status: '', no: '', type: '', identifier: '', amount: 0, issuedAt: '' };
        renderCashReceiptArea();
        syncFaxCrState(false, '');
        showToast('현금영수증이 취소되었습니다.', 'success');
      } else {
        showToast(res.message || '취소 실패', 'danger');
      }
    });
  }


  // ══════════════════════════════════════════════════════════
  //  메모 기능
  // ══════════════════════════════════════════════════════════
  const _MEMO_STORE_URL   = @json(route('prescriptions.memos.store', $prescription));
  const _MEMO_UPDATE_BASE = @json(url('prescriptions/' . $prescription->rx_number . '/memos'));
  const _MEMO_PIN_BASE    = @json(url('prescriptions/' . $prescription->rx_number . '/memos'));
  const _CSRF             = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  let _memos = @json($memosData);

  // ── 패널 열기/닫기 ───────────────────────────────────────
  function toggleMemoPanel(e) {
    e?.stopPropagation();
    const wrap = document.getElementById('memoPanelWrap');
    const btn  = document.getElementById('memoPanelToggleBtn');
    const open = wrap.style.display === 'none';
    if (open) {
      // 버튼 바로 아래에 위치
      const r = btn.getBoundingClientRect();
      const panelW = 340;
      let left = r.left;
      // 화면 오른쪽 밖으로 나가면 오른쪽 정렬
      if (left + panelW > window.innerWidth - 8) left = window.innerWidth - panelW - 8;
      wrap.style.top  = (r.bottom + 6) + 'px';
      wrap.style.left = left + 'px';
      wrap.style.display = 'block';
      renderMemoList();
      document.getElementById('memoNewInput').focus();
    } else {
      wrap.style.display = 'none';
    }
  }
  document.addEventListener('click', function (e) {
    const wrap = document.getElementById('memoPanelWrap');
    const btn  = document.getElementById('memoPanelToggleBtn');
    if (!wrap.contains(e.target) && !btn.contains(e.target)) {
      wrap.style.display = 'none';
    }
  });

  // ── 메모 카드 렌더 ──────────────────────────────────────
  function renderMemoList() {
    const list = document.getElementById('memoList');
    if (!list) return;
    if (!_memos.length) {
      list.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px 0;">작성된 메모가 없습니다.</div>';
      return;
    }
    list.innerHTML = _memos.map(m => `
      <div class="memo-card" id="mc-${m.id}"
           style="margin:0 8px 6px;padding:9px 10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;position:relative;">
        <div style="display:flex;align-items:flex-start;gap:6px;">
          <div draggable="true" ondragstart="memoDragStart(event,${m.id})"
               title="드래그해서 화면에 고정"
               style="flex-shrink:0;cursor:grab;padding:3px 5px;border-radius:4px;color:var(--gray-300);font-size:13px;margin-top:0px;user-select:none;transition:background .15s,color .15s;"
               onmouseover="this.style.background='var(--gray-100)';this.style.color='var(--primary)'"
               onmouseout="this.style.background='transparent';this.style.color='var(--gray-300)'">
            <i class="fa-solid fa-grip-vertical"></i>
          </div>
          <textarea class="memo-ta" data-id="${m.id}"
                    style="flex:1;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:42px;"
                    oninput="autoResizeTa(this)" onblur="updateMemoContent(${m.id},this.value)">${escHtmlMemo(m.content)}</textarea>
          <div style="display:flex;flex-direction:column;gap:3px;flex-shrink:0;">
            <button onclick="togglePin(${m.id})" title="${m.is_pinned ? '고정 해제' : '화면 고정'}"
                    style="width:22px;height:22px;border:none;border-radius:4px;cursor:pointer;background:${m.is_pinned ? 'var(--primary)' : 'var(--gray-100)'};color:${m.is_pinned ? '#fff' : 'var(--gray-500)'};font-size:11px;display:flex;align-items:center;justify-content:center;">
              <i class="fa-solid fa-thumbtack"></i>
            </button>
            <button onclick="deleteMemo(${m.id})" title="삭제"
                    style="width:22px;height:22px;border:none;border-radius:4px;cursor:pointer;background:var(--alert-50);color:var(--danger);font-size:11px;display:flex;align-items:center;justify-content:center;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>
        <div style="font-size:10px;color:var(--gray-400);margin-top:4px;padding-left:17px;">${m.created_at} · ${escHtmlMemo(m.user_name)}</div>
      </div>
    `).join('');
    list.querySelectorAll('.memo-ta').forEach(autoResizeTa);
  }

  function escHtmlMemo(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function autoResizeTa(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }

  function _updateBadge() {
    const cnt = _memos.length;
    const badge = document.getElementById('memoBadgeCount');
    const panelCnt = document.getElementById('memoPanelCount');
    if (badge) { badge.textContent = cnt; badge.style.display = cnt > 0 ? 'flex' : 'none'; }
    if (panelCnt) panelCnt.textContent = `(${cnt}건)`;
  }

  // ── 새 메모 저장 ─────────────────────────────────────────
  async function saveMemo() {
    const ta = document.getElementById('memoNewInput');
    const content = ta.value.trim();
    if (!content) return;
    try {
      const res = await fetch(_MEMO_STORE_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
        body: JSON.stringify({ content }),
      });
      const memo = await res.json();
      _memos.unshift(memo);
      ta.value = '';
      _updateBadge();
      renderMemoList();
    } catch { showToast('메모 저장 실패', 'danger'); }
  }

  // ── 메모 내용 수정 ────────────────────────────────────────
  async function updateMemoContent(id, content) {
    const m = _memos.find(x => x.id === id);
    if (!m || m.content === content) return;
    m.content = content;
    try {
      await fetch(`${_MEMO_UPDATE_BASE}/${id}`, {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
        body: JSON.stringify({ content }),
      });
      // 화면에 고정된 메모도 동기화
      const floatEl = document.getElementById(`pinned-memo-${id}`);
      if (floatEl) { const ta = floatEl.querySelector('.pinned-memo-ta'); if (ta) ta.value = content; }
    } catch { showToast('메모 수정 실패', 'danger'); }
  }

  // ── 메모 삭제 ────────────────────────────────────────────
  async function deleteMemo(id) {
    if (!await ceConfirm('메모를 삭제하시겠습니까?', { tone: 'danger', confirmText: '삭제' })) return;
    try {
      await fetch(`${_MEMO_UPDATE_BASE}/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
      });
      _memos = _memos.filter(x => x.id !== id);
      _updateBadge();
      renderMemoList();
      // 고정 위젯도 제거
      document.getElementById(`pinned-memo-${id}`)?.remove();
    } catch { showToast('메모 삭제 실패', 'danger'); }
  }

  // ── 고정 토글 ────────────────────────────────────────────
  async function togglePin(id) {
    const m = _memos.find(x => x.id === id);
    if (!m) return;
    // 고정 위치 기본값: 화면 우하단
    const defaultX = window.innerWidth  - 280 - 20;
    const defaultY = window.innerHeight - 180 - 20;
    const savedPos = JSON.parse(localStorage.getItem(`pmpos_${id}`) || 'null');
    const pinX = savedPos?.x ?? m.pin_x ?? defaultX;
    const pinY = savedPos?.y ?? m.pin_y ?? defaultY;
    try {
      const res = await fetch(`${_MEMO_PIN_BASE}/${id}/pin`, {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
        body: JSON.stringify({ pin_x: pinX, pin_y: pinY }),
      });
      const data = await res.json();
      m.is_pinned = data.is_pinned;
      m.pin_x = data.pin_x;
      m.pin_y = data.pin_y;
      renderMemoList();
      if (m.is_pinned) {
        renderPinnedWidget(m);
      } else {
        document.getElementById(`pinned-memo-${id}`)?.remove();
      }
    } catch { showToast('고정 변경 실패', 'danger'); }
  }

  // ── 드래그로 화면에 끌어오기 ────────────────────────────
  function memoDragStart(e, id) {
    e.dataTransfer.setData('memoId', String(id));
    e.dataTransfer.effectAllowed = 'move';
  }
  document.addEventListener('dragover', e => e.preventDefault());
  document.addEventListener('drop', function (e) {
    const id = parseInt(e.dataTransfer.getData('memoId'), 10);
    if (!id) return;
    e.preventDefault();
    const m = _memos.find(x => x.id === id);
    if (!m) return;
    // position:fixed는 뷰포트 기준 — scrollX/Y 없음
    const x = Math.max(0, Math.min(e.clientX - 120, window.innerWidth  - 250));
    const y = Math.max(0, Math.min(e.clientY - 20,  window.innerHeight - 120));
    m.pin_x     = x;
    m.pin_y     = y;
    m.is_pinned = true;
    localStorage.setItem(`pmpos_${id}`, JSON.stringify({ x, y }));
    renderPinnedWidget(m);
    renderMemoList();
    // DB에 고정 상태 저장
    fetch(`${_MEMO_PIN_BASE}/${id}/pin`, {
      method : 'PATCH',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
      body   : JSON.stringify({ pin_x: x, pin_y: y }),
    });
  });

  // ── 고정 위젯 렌더 ───────────────────────────────────────
  function renderPinnedWidget(m) {
    document.getElementById(`pinned-memo-${m.id}`)?.remove();
    const savedPos = JSON.parse(localStorage.getItem(`pmpos_${m.id}`) || 'null');
    const x = savedPos?.x ?? m.pin_x ?? (window.innerWidth - 280 - 20);
    const y = savedPos?.y ?? m.pin_y ?? (window.innerHeight - 180 - 20);

    const savedSize = JSON.parse(localStorage.getItem(`pmsize_${m.id}`) || 'null');
    const w = savedSize?.w ?? 240;
    const h = savedSize?.h ?? null;

    const el = document.createElement('div');
    el.id = `pinned-memo-${m.id}`;
    el.style.cssText = `position:fixed;left:${x}px;top:${y}px;width:${w}px;min-width:180px;min-height:90px;z-index:9000;background:var(--primary-50);border:1px solid var(--primary-200);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);display:flex;flex-direction:column;`;
    el.innerHTML = `
      <div class="pm-header" style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:var(--primary-100);border-radius:8px 8px 0 0;cursor:move;user-select:none;flex-shrink:0;">
        <span style="font-size:10px;font-weight:700;color:var(--gray-700);"><i class="fa-solid fa-thumbtack"></i> 메모 고정
          <span style="font-size:10px;font-weight:400;margin-left:4px;opacity:.7;">${escHtmlMemo(m.rx_number ?? '')}</span>
        </span>
        <div style="display:flex;gap:4px;">
          <button onclick="unpinWidget(${m.id})" title="고정 해제"
                  style="width:18px;height:18px;border:none;border-radius:6px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:var(--gray-700);">
            <i class="fa-solid fa-thumbtack" style="transform:rotate(45deg);"></i>
          </button>
          <button onclick="closeWidget(${m.id})" title="닫기 (고정 유지)"
                  style="width:18px;height:18px;border:none;border-radius:6px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:var(--gray-700);">×</button>
        </div>
      </div>
      <div style="padding:8px;flex:1;display:flex;flex-direction:column;min-height:0;">
        <textarea class="pinned-memo-ta" data-id="${m.id}"
                  style="flex:1;width:100%;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:48px;"
                  onblur="updateMemoContent(${m.id},this.value)">${escHtmlMemo(m.content)}</textarea>
        <div style="font-size:10px;color:var(--gray-400);margin-top:2px;flex-shrink:0;">${m.created_at} · ${escHtmlMemo(m.user_name)}</div>
      </div>
      <div class="pm-resize" title="크기 조절"
           style="position:absolute;right:0;bottom:0;width:16px;height:16px;cursor:se-resize;display:flex;align-items:flex-end;justify-content:flex-end;padding:2px;">
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
          <path d="M2 8L8 2M5 8L8 5M8 8L8 8" style="stroke:var(--gray-300);" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
      </div>
    `;
    document.body.appendChild(el);

    // 높이 복원
    if (h) el.style.height = h + 'px';

    makePinnedDraggable(el, m.id);
    makePinnedResizable(el, m.id);
  }

  function unpinWidget(id) {
    togglePin(id);
  }
  function closeWidget(id) {
    document.getElementById(`pinned-memo-${id}`)?.remove();
  }

  function makePinnedResizable(el, id) {
    const handle = el.querySelector('.pm-resize');
    if (!handle) return;
    let sx, sy, sw, sh;
    handle.addEventListener('mousedown', function (e) {
      e.preventDefault();
      e.stopPropagation();
      sx = e.clientX; sy = e.clientY;
      sw = el.offsetWidth; sh = el.offsetHeight;
      function onMove(ev) {
        const nw = Math.max(180, sw + ev.clientX - sx);
        const nh = Math.max(90,  sh + ev.clientY - sy);
        el.style.width  = nw + 'px';
        el.style.height = nh + 'px';
      }
      function onUp() {
        const size = { w: el.offsetWidth, h: el.offsetHeight };
        localStorage.setItem(`pmsize_${id}`, JSON.stringify(size));
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  function makePinnedDraggable(el, id) {
    const header = el.querySelector('.pm-header');
    let sx, sy, ox, oy;
    header.addEventListener('mousedown', function (e) {
      sx = e.clientX; sy = e.clientY;
      ox = el.offsetLeft; oy = el.offsetTop;
      function onMove(ev) {
        const nx = ox + ev.clientX - sx;
        const ny = oy + ev.clientY - sy;
        el.style.left = nx + 'px'; el.style.top = ny + 'px';
      }
      function onUp() {
        const pos = { x: parseInt(el.style.left), y: parseInt(el.style.top) };
        localStorage.setItem(`pmpos_${id}`, JSON.stringify(pos));
        // DB에도 위치 저장
        fetch(`${_MEMO_PIN_BASE}/${id}/pin`, {
          method: 'PATCH',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':_CSRF,'Accept':'application/json'},
          body: JSON.stringify({ pin_x: pos.x, pin_y: pos.y }),
        });
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      }
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  // ── 초기 고정 위젯 복원 ──────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    _memos.filter(m => m.is_pinned).forEach(m => renderPinnedWidget(m));
  });

  // ── 환자 정보 바: 상시 고정 헤더 ───────────────────────────
  // 정보바는 margin-top(-)으로 네비 바로 아래에 풀블리드되는 '서브 헤더'라 애초에
  // pin 경계 안에서 시작함. 스크롤에 따라 pin/unpin을 토글하면 참조 불일치로 매 이벤트마다
  // 붙었다 떼며 여백이 흔들림 → 전환을 없애고 처음부터 끝까지 고정으로 유지한다.
  (function () {
    const bar = document.getElementById('patient-info-bar');
    const ph  = document.getElementById('patient-info-bar-ph');
    if (!bar || !ph) return;
    ph.style.display = 'block';
    bar.classList.add('info-bar-pinned');           // 상시 고정
    function sync() {
      // 콘텐츠가 고정바 '바로 아래'에서 시작하도록 자리표시자 높이 확보(문서좌표 기준)
      const docY = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
      const phTop     = ph.getBoundingClientRect().top + docY;   // 흐름상 자리(자기 높이와 무관)
      const barBottom = bar.getBoundingClientRect().bottom + docY;
      ph.style.height = Math.max(0, Math.round(barBottom - phTop)) + 'px';
    }
    sync();
    // 폰트/이미지 로드나 wrap 변화로 높이가 바뀔 수 있어 재동기화
    window.addEventListener('resize', sync);
    window.addEventListener('load', sync);
    requestAnimationFrame(sync);
  })();
</script>
@endpush
