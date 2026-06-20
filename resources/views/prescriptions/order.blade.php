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
    <div class="help-item-text"><strong>처방 제품 탭</strong>판매유형을 선택하고 제품을 추가합니다. 제품 검색으로 Todoworks에서 직접 가져옵니다.</div>
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
    <div class="help-item-text">처방 제품 탭에서 <b>판매유형</b> 선택 후 제품 추가</div>
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
  <span style="color:var(--primary);font-weight:600;">{{ $prescription->rx_number }}</span>
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
  .order-layout { display: grid; grid-template-columns: 320px 1fr; gap: 18px; align-items: start; }
  .order-layout.viewer-right { grid-template-columns: 1fr 320px; }
  .order-layout.viewer-right > :first-child { order: 2; }
  @media (max-width: 1200px) { .order-layout.viewer-right { grid-template-columns: 1fr 280px; } }
  @media (max-width: 768px)  { .order-layout.viewer-right > :first-child { order: unset; } }
  .img-viewer { background: #1e293b; border-radius: var(--radius-lg); aspect-ratio: 3/4; display: flex; flex-direction: column; overflow: hidden; position: relative; }
  .img-viewer-toolbar { padding: 8px 12px; background: #0f172a; display: flex; align-items: center; gap: 8px; }
  .img-viewer-toolbar span { font-size: 12px; color: #94a3b8; flex: 1; text-align: center; }
  .img-tool-btn { width: 28px; height: 28px; background: rgba(255,255,255,.1); border: none; border-radius: 6px; color: #94a3b8; font-size: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); }
  .img-tool-btn:hover { background: rgba(255,255,255,.2); color: #fff; }
  .img-viewer-canvas { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
  .img-placeholder { text-align: center; color: #475569; }
  .img-placeholder i { font-size: 56px; margin-bottom: 10px; display: block; opacity: .4; }
  .img-placeholder p { font-size: 13px; opacity: .6; }
  .ocr-edit-row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; align-items: center; margin-bottom: 10px; }
  .ocr-edit-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
  .field-group { position: relative; }
  .field-group .field-status { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 13px; }
  .field-group input.has-warn { border-color: var(--warning); background: #fffbeb; }
  .field-group input.has-ok   { border-color: var(--success); }
  .benefit-box { padding: 12px 14px; background: var(--success-light); border: 1px solid #86efac; border-radius: var(--radius); margin-top: 4px; }
  .benefit-title { font-size: 13px; font-weight: 700; color: var(--success); }
  .benefit-detail { font-size: 12px; color: var(--text-secondary); margin-top: 4px; line-height: 1.8; }
  .product-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg); margin-bottom: 8px; cursor: pointer; transition: var(--transition); }
  .product-card.selected { border-color: var(--primary); background: var(--primary-light); }
  .product-card:hover { border-color: var(--primary); }
  .product-img { width: 44px; height: 44px; border-radius: 8px; background: #cce9f6; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .product-name { font-size: 13px; font-weight: 600; }
  .product-code { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
  .product-price { font-size: 13px; font-weight: 700; color: var(--primary); margin-left: auto; }
  /* ── 제품 자동완성 드롭다운 ── */
  .pac-wrap { position:relative; flex:1; min-width:0; }
  .pac-drop { position:absolute; top:calc(100% + 2px); left:0; right:0; background:#fff; border:1px solid var(--primary); border-radius:8px; box-shadow:0 6px 24px rgba(0,0,0,.13); z-index:2000; max-height:340px; overflow-y:auto; display:none; }
  .pac-drop.open { display:block; }
  .pac-item { display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer; border-bottom:1px solid var(--border); transition:background .1s; }
  .pac-item:last-child { border-bottom:none; }
  .pac-item:hover, .pac-item.ac-active { background:var(--primary-light); }
  .pac-item-icon { width:34px; height:34px; border-radius:7px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
  .pac-item-body { flex:1; min-width:0; }
  .pac-item-name { font-size:12px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .pac-item-meta { font-size:10px; color:var(--text-muted); margin-top:2px; display:flex; flex-wrap:wrap; align-items:center; gap:4px; }
  .pac-item-price { font-size:12px; font-weight:700; color:var(--primary); white-space:nowrap; flex-shrink:0; }
  .pac-status { padding:10px 14px; font-size:12px; color:var(--text-muted); text-align:center; }
  .qty-control { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
  .qty-btn { width: 26px; height: 26px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; transition: var(--transition); }
  .qty-btn:hover { border-color: var(--primary); color: var(--primary); }
  .qty-input { width: 100px; text-align: center; font-size: 13px; font-weight: 700; border: 1px solid var(--border); border-radius: var(--radius); padding: 3px 6px; background: var(--bg-card); }
  .cost-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px dashed var(--border); font-size: 13px; }
  .cost-row:last-child { border-bottom: none; }
  .cost-row.total { font-weight: 700; font-size: 14px; border-bottom: none; border-top: 2px solid var(--border); padding-top: 10px; margin-top: 4px; }
  .cost-row.total .cost-val { color: var(--primary); font-size: 16px; }
  .workflow-step { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border-light); }
  .workflow-step:last-child { border-bottom: none; }
  .ws-icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
  .ws-icon.done   { background: var(--success-light); color: var(--success); }
  .ws-icon.active { background: var(--primary-light); color: var(--primary); }
  .ws-icon.pending{ background: var(--bg); color: var(--text-muted); }
  .ws-label { font-size: 12px; font-weight: 600; } .ws-time { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
  .ws-arrow { margin-left: auto; color: var(--text-muted); font-size: 12px; }
  .page-body-inner { padding-bottom: 40px; }
  .info-bar-pinned { position:fixed !important; top:var(--nav-h); left:var(--sidebar-w); right:0; margin:0 !important; z-index:50; box-shadow:0 2px 10px rgba(0,0,0,.10); }
  body.menu-collapsed .info-bar-pinned { left:64px; }
  .tab-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
  .tab-bar-tabs { display: flex; }
  .tab-btn { padding: 8px 16px; font-size: 13px; font-weight: 600; color: var(--text-muted); border: none; background: transparent; border-bottom: 2px solid transparent; cursor: pointer; transition: var(--transition); margin-bottom: -1px; }
  .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
  .tab-pane { display: none; } .tab-pane.active { display: block; }
  /* ── 카드 / 테이블 뷰 토글 ── */
  .cv { display: block; } .tv { display: none; }
  .tab-view-table .cv { display: none; } .tab-view-table .tv { display: block; }
  .tab-view-table #btnAccToggleAll { display: none; }
  /* OCR·주문 탭은 cv 유지 (입력 기능 보존) — 아코디언 크롬만 제거 */
  .tab-view-table #tab-ocr   > .cv { display: block !important; }
  .tab-view-table #tab-order > .cv { display: block !important; }
  .tab-view-table #tab-ocr   > .tv { display: none  !important; }
  .tab-view-table #tab-order > .tv { display: none  !important; }
  /* OCR 탭 table mode: 아코디언 → 플랫 섹션 */
  .tab-view-table #tab-ocr .rx-acc-item { border:none; border-bottom:1px solid var(--border); border-radius:0; margin-bottom:0; }
  .tab-view-table #tab-ocr .rx-acc-item:last-child { border-bottom:none; }
  .tab-view-table #tab-ocr .rx-acc-header { background:var(--primary-light) !important; pointer-events:none; padding:5px 12px; }
  .tab-view-table #tab-ocr .rx-acc-header > span:first-child { color:var(--primary) !important; font-size:11px; }
  .tab-view-table #tab-ocr .rx-acc-icon,
  .tab-view-table #tab-ocr .acc-inline-btns { display:none !important; }
  .tab-view-table #tab-ocr .rx-acc-body { display:block !important; padding:10px 12px; }
  .tab-tbl { width:100%; border-collapse:collapse; font-size:12px; }
  .tab-tbl td, .tab-tbl th { padding:5px 9px; border:1px solid var(--border); vertical-align:middle; }
  .tab-tbl th { background:var(--bg); font-size:10px; font-weight:700; color:var(--text-secondary); white-space:nowrap; width:1%; min-width:76px; }
  .tab-tbl td { color:var(--text-primary); overflow:hidden; }
  .tab-tbl th { overflow:hidden; }
  .tab-tbl td.pac-cell { overflow:visible; position:relative; }
  /* 드롭다운이 다른 행 위에 표시되도록 */
  .tab-tbl tbody tr:has(.pac-drop.open) { z-index:100; position:relative; }
  .tab-tbl .tbl-sec td { background:var(--primary-light); color:var(--primary); font-size:11px; font-weight:700; }
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(4px); z-index: 200; display: none; align-items: center; justify-content: center; }
  .modal-overlay.show { display: flex; }
  .modal-box { background: var(--bg-card); border-radius: var(--radius-lg); width: 480px; max-width: 95vw; box-shadow: var(--shadow-lg); animation: slideUp .25s ease; }
  @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
  .modal-header { padding: 18px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
  .modal-title { font-size: 15px; font-weight: 700; flex: 1; }
  .modal-close { background: none; border: none; font-size: 16px; color: var(--text-muted); cursor: pointer; }
  .modal-body { padding: 20px; } .modal-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }
  @media (max-width: 1200px) { .order-layout { grid-template-columns: 280px 1fr; } }
  @media (max-width: 768px)  { .order-layout { grid-template-columns: 1fr; } .action-footer { left: 0; flex-wrap: wrap; bottom: 42px; } }

  .section-title { font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .item-card { border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 10px; margin-bottom: 6px; background: var(--bg-card); }
  .item-num { font-size: 12px; font-weight: 700; color: #fff; background: var(--primary); border-radius: 4px; padding: 0 9px; flex-shrink: 0; align-self: flex-end; height: 34px; display: flex; align-items: center; }
  /* Inline row: name + qty + buttons */
  .item-row { display: flex; align-items: flex-start; gap: 6px; }
.item-inline-field { display: flex; flex-direction: column; flex-shrink: 0; }
  .item-field-label { font-size: 10px; color: var(--text-muted); margin-bottom: 2px; }
  .item-summary { display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 4px 8px; background: var(--bg); border-radius: var(--radius); border: 1px solid var(--border); margin-top: 6px; }
  .item-nhis-sel { font-size:11px !important; height:26px !important; padding:0 6px !important; width:110px !important; min-width:0 !important; flex-shrink:0; }
  /* 카드뷰 item-row 안에서는 다른 입력 항목과 높이 통일 */
  .item-row .item-nhis-sel { height:34px !important; padding:0 4px !important; }
  .tab-view-table .item-nhis-sel { width:100% !important; }
  .items-total-bar { display: flex; gap: 16px; font-size: 12px; padding: 8px 12px; background: var(--success-light); border: 1px solid #86efac; border-radius: var(--radius); margin-top: 4px; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* 판매 유형 라디오 버튼 */
  .so-type-opt { display:inline-flex; align-items:center; cursor:pointer; }
  .so-type-opt input[type=radio] { display:none; }
  .so-type-opt span {
    display:inline-flex; align-items:center; gap:5px;
    padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600;
    border:1.5px solid var(--border); background:#fff; color:var(--text-secondary);
    transition:var(--transition); user-select:none;
  }
  .so-type-opt span:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  .so-type-opt input[type=radio]:checked + span { background:var(--primary); border-color:var(--primary); color:#fff; }

  /* ── RX Inspection Accordion ── */
  .rx-acc-item { border:1px solid var(--border); border-radius:var(--radius); margin-bottom:6px; overflow:hidden; background:var(--bg-card); transition:border-color .18s; }
  .rx-acc-item.is-open { border-color:var(--primary); }
  .rx-acc-header { display:flex; align-items:center; justify-content:space-between; padding:11px 14px; cursor:pointer; background:var(--bg-card); user-select:none; transition:var(--transition); gap:8px; }
  .rx-acc-header:hover { background:var(--bg); }
  .rx-acc-item.is-open > .rx-acc-header { background:var(--primary-light); }
  .rx-acc-item.is-open > .rx-acc-header > span:first-child { color:var(--primary); }
  .rx-acc-header > span:first-child { display:flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:var(--text-primary); }
  .rx-acc-meta { display:flex; align-items:center; gap:8px; }
  .rx-acc-meta-hint { font-size:11px; color:var(--text-muted); }
  .acc-inline-btns { display:none; align-items:center; gap:4px; }
  .rx-acc-item.is-open > .rx-acc-header .acc-inline-btns { display:flex; }
  .rx-acc-icon { font-size:11px; color:var(--text-muted); transition:transform .2s ease; }
  .rx-acc-icon.open { transform:rotate(180deg); }
  .rx-acc-body { padding:14px 16px; background:var(--bg-card); border-top:1px solid var(--border-light); }
  .rx-field-grid  { display:grid; grid-template-columns:1fr 1fr;         gap:10px 18px; }
  .rx-grid-3      { display:grid; grid-template-columns:1fr 1fr 1fr;     gap:10px 16px; }
  .rx-grid-4      { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px 12px; }
  .rx-field-row { display:flex; align-items:center; gap:8px; min-width:0; }
  .rx-field-row.full { grid-column:1 / -1; }
  .rx-field-label { font-size:11px; font-weight:600; color:var(--text-secondary); white-space:nowrap; min-width:88px; flex-shrink:0; }
  .rx-grid-3 .rx-field-label, .rx-grid-4 .rx-field-label { min-width:70px; }
  .rx-ocr-badge { display:inline-flex; align-items:center; gap:3px; background:var(--primary-light); color:var(--primary); border:1px solid var(--primary-accent); border-radius:4px; font-size:10px; font-weight:700; padding:1px 5px; }
  .rx-ocr-badge i { font-size:9px; }
  @media(max-width:1100px){ .rx-grid-4 { grid-template-columns:1fr 1fr; } }
  @media(max-width:900px) { .rx-field-grid, .rx-grid-3 { grid-template-columns:1fr 1fr; } .rx-grid-4 { grid-template-columns:1fr 1fr; } }
  @media(max-width:600px) { .rx-field-grid, .rx-grid-3, .rx-grid-4 { grid-template-columns:1fr; } .rx-field-row.full { grid-column:1; } }

  /* 이전 상담 목록 hover */
  .pc-list-item { border-left: 3px solid transparent; }
  .pc-list-item:hover { background: var(--bg) !important; }

  /* 이전 상담 이력 아코디언 */
  .hist-acc-item { border:1px solid var(--border); border-radius:var(--radius); margin-bottom:6px; overflow:hidden; }
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
  .pc-field-row { display:flex; flex-direction:column; gap:2px; padding:7px 9px; background:var(--bg); border-radius:5px; border:1px solid var(--border-light); min-width:0; }
  .pc-field-label { font-size:10px; font-weight:600; color:var(--text-muted); }
  .pc-field-val { font-size:12px; font-weight:600; color:var(--text-primary); word-break:break-all; }

  /* ── 첨부 파일 썸네일 ── */
  .attach-strip { display:flex; flex-wrap:wrap; gap:8px; padding:10px 12px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); margin-top:10px; }
  .attach-thumb { position:relative; width:64px; cursor:pointer; flex-shrink:0; }
  .attach-thumb-img { width:64px; height:64px; object-fit:cover; border-radius:6px; border:2px solid var(--border); display:block; transition:border-color .15s; }
  .attach-thumb-img:hover { border-color:var(--primary); }
  .attach-thumb-pdf { width:64px; height:64px; border-radius:6px; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:24px; background:#fff1f0; color:var(--danger); transition:border-color .15s; }
  .attach-thumb-pdf:hover { border-color:var(--danger); }
  .attach-type-badge { position:absolute; bottom:0; left:0; right:0; text-align:center; font-size:9px; font-weight:700; padding:2px 4px; border-radius:0 0 4px 4px; background:rgba(0,0,0,.6); color:#fff; line-height:1.3; }
  .attach-del-btn { position:absolute; top:-4px; right:-4px; width:18px; height:18px; border-radius:50%; background:var(--danger); border:none; color:#fff; font-size:9px; cursor:pointer; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .15s; z-index:2; }
  .attach-thumb:hover .attach-del-btn { opacity:1; }
  .doc-thumb.active .attach-thumb-img,
  .doc-thumb.active .attach-thumb-pdf { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary-light); }
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
  <div id="patient-info-bar" style="background:var(--primary-light);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:-20px -24px 20px;padding:10px 24px;position:relative;z-index:50;">

    {{-- ── 오른쪽 액션 버튼 그룹 ── --}}
    <div style="display:flex;gap:5px;align-items:center;flex-shrink:0;flex-wrap:wrap;order:10;">

      {{-- 위임동의 SMS 발송 --}}
      <div style="position:relative;">
        <div id="consentBtnWrap">
          <button type="button" id="consentActionBtn" onclick="toggleConsentPopover(event)"
                  style="padding:5px 11px;background:#6366f1;color:#fff;border:none;font-weight:700;font-size:11px;display:flex;align-items:center;gap:4px;border-radius:var(--radius);white-space:nowrap;cursor:pointer;">
            <i class="fa-solid fa-file-signature" style="font-size:11px;"></i> 위임동의
          </button>
        </div>
        <div id="consentResultBadge" style="display:none;align-items:center;gap:4px;padding:4px 9px;border-radius:var(--radius);font-size:11px;white-space:nowrap;"></div>
        {{-- 위임동의 팝오버 --}}
        <div id="consentPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:380px;background:var(--bg-card);border:1px solid #6366f1;border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:502;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:#6366f1;border:1px solid #6366f1;transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:#6366f1;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i id="consentModalIcon" class="fa-solid fa-file-signature" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
            <span id="consentModalTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">위임동의 SMS 발송</span>
            <button onclick="closeConsentPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
            <div id="consentResendNotice" style="display:none;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 12px;font-size:12px;color:#c2410c;line-height:1.6;">
              <i class="fa-solid fa-rotate-right"></i>
              <strong>이전 동의 링크가 만료되었습니다.</strong><br>
              새로운 동의 링크를 발송합니다. 이전 링크는 더 이상 사용할 수 없습니다.
            </div>
            @php
              $consentBase = rtrim(config('app.consent_public_url', config('app.url')), '/');
              $isLocalUrl  = str_contains($consentBase, 'localhost') || str_contains($consentBase, '127.0.0.1');
            @endphp
            @if($isLocalUrl)
            <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 12px;font-size:12px;color:#dc2626;line-height:1.6;">
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
              <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">수신 번호</label>
              <input type="text" class="form-control" id="consentMobile"
                     placeholder="010-XXXX-XXXX / 02-XXXX-XXXX"
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}"
                     style="font-size:13px;" oninput="updateConsentPreview()" />
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">환자명</label>
              <input type="text" class="form-control" id="consentPatientName"
                     value="{{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '' }}"
                     readonly style="background:var(--bg-secondary,#f8f9fa);font-size:13px;" />
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
              <div id="consentMsgPreview" style="background:#f8fafc;border:1px solid var(--border);border-radius:6px;padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:1.8;color:#374151;font-family:monospace;"></div>
            </div>
            <div id="consentSendResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:600;"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
              <button class="btn btn-outline btn-sm" onclick="closeConsentPopover()">취소</button>
              <button class="btn btn-primary btn-sm" id="btnConsentSend" onclick="sendConsentSms()">
                <i class="fa-solid fa-paper-plane"></i> 발송
              </button>
            </div>
          </div>
        </div>
        {{-- 위임동의 서명 확인 팝오버 --}}
        <div id="consentSignPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:460px;background:var(--bg-card);border:1px solid var(--success);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:503;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:var(--success);border:1px solid var(--success);transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid #a7f3d0;">
            <i class="fa-solid fa-signature" style="color:var(--success);font-size:15px;flex-shrink:0;"></i>
            <span style="font-size:13px;font-weight:700;color:#065f46;flex:1;">위임동의 서명 확인</span>
            <button onclick="closeConsentSignPopover()" style="background:none;border:none;cursor:pointer;color:#065f46;font-size:16px;line-height:1;">&#215;</button>
          </div>
          <div style="padding:0;">
            <div id="csignLoading" style="padding:40px;text-align:center;color:var(--text-muted);font-size:13px;">
              <span style="display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:8px;"></span>서명 정보 불러오는 중...
            </div>
            <div id="csignContent" style="display:none;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid var(--border);">
                <div style="padding:14px 20px;border-right:1px solid var(--border);">
                  <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명자</div>
                  <div id="csignName" style="font-size:15px;font-weight:700;color:var(--text-primary);"></div>
                </div>
                <div style="padding:14px 20px;">
                  <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명한 전화번호</div>
                  <div id="csignMobile" style="font-size:15px;font-weight:700;color:var(--text-primary);font-family:monospace;"></div>
                </div>
              </div>
              <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">서명 일시</div>
                <div id="csignTime" style="font-size:13px;color:var(--text-primary);font-family:monospace;"></div>
              </div>
              <div id="csignImgWrap" style="padding:20px;background:#f8fafb;text-align:center;border-bottom:1px solid var(--border);">
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
          <div style="padding:12px 14px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);">
            <a id="csignPdfBtn" href="#" target="_blank"
               style="display:none;padding:5px 13px;background:var(--danger);color:#fff;font-weight:700;font-size:12px;border-radius:var(--radius);text-decoration:none;align-items:center;gap:5px;">
              <i class="fa-solid fa-file-pdf"></i> PDF 다운로드
            </a>
            <button class="btn btn-outline btn-sm" onclick="closeConsentSignPopover()">닫기</button>
          </div>
        </div>
      </div>

      {{-- 가상계좌 발급 --}}
      <div id="vaButtonWrap">
      @if($prescription->order)
        @php
          $tp = $prescription->order->tossPayment;
        @endphp
        @if($tp && $tp->status === 'DONE')
          {{-- 입금완료 --}}
          <div style="padding:5px 11px;background:var(--success-light);border:1px solid var(--success);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--success);display:flex;align-items:center;gap:4px;white-space:nowrap;">
            <i class="fa-solid fa-circle-check" style="font-size:11px;"></i> 입금완료
          </div>
        @elseif($tp && $tp->status === 'DISABLED')
          {{-- VA 비활성화 – SMS만 발송 완료 --}}
          <div style="padding:5px 11px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--warning);display:flex;align-items:center;gap:4px;white-space:nowrap;">
            <i class="fa-solid fa-comment-sms" style="font-size:11px;"></i> SMS 발송 완료
          </div>
        @elseif($tp)
          {{-- 발급됨 – 입금대기 or 만료 --}}
          <button type="button"
                  data-url="{{ route('settlement.check-status', $prescription->order) }}"
                  onclick="checkVaStatus(this)"
                  style="padding:5px 11px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:11px;font-weight:700;color:var(--warning);display:flex;align-items:center;gap:4px;white-space:nowrap;cursor:pointer;">
            <i class="fa-solid fa-building-columns" style="font-size:11px;"></i>
            {{ $tp->bank_name }} {{ $tp->account_number }}
          </button>
        @else
          {{-- 미발급 --}}
          <div id="vaNotIssuedWrap" style="position:relative;">
            <button type="button" id="btnVaTrigger"
                    data-url="{{ route('settlement.issue-va', $prescription->order) }}"
                    data-sms-url="{{ route('prescriptions.smsSend', $prescription) }}"
                    onclick="toggleVaPopover(event)"
                    style="padding:5px 11px;background:#0ea5e9;color:#fff;border:none;font-weight:700;font-size:11px;display:flex;align-items:center;gap:4px;border-radius:var(--radius);white-space:nowrap;cursor:pointer;">
              <i class="fa-solid fa-building-columns" style="font-size:11px;"></i> 가상계좌 발급
            </button>
            {{-- 가상계좌 발급 팝오버 --}}
            <div id="vaPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:360px;background:var(--bg-card);border:1px solid #0ea5e9;border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:501;">
              <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
                <div style="width:10px;height:10px;background:#0ea5e9;border:1px solid #0ea5e9;transform:rotate(45deg);margin:3px auto 0;"></div>
              </div>
              <div style="background:#0ea5e9;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-building-columns" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
                <span id="vaPopoverTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">가상계좌 발급</span>
                <button onclick="closeVaPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
              </div>
              {{-- 확인 view --}}
              <div id="vaPopoverConfirm" style="padding:14px;">
                <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;">
                  <div style="font-size:11px;color:var(--text-muted);font-weight:600;margin-bottom:8px;">발급 정보 확인</div>
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
                <div style="background:var(--warning-light);border:1px solid #fde68a;border-radius:var(--radius);padding:8px 10px;font-size:11px;color:var(--warning);margin-bottom:12px;">
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
                <div id="vaDisabledNote" style="display:none;background:var(--warning-light);border:1px solid #fde68a;border-radius:var(--radius);padding:8px 10px;font-size:11px;color:var(--warning);margin-bottom:10px;">
                  <i class="fa-solid fa-circle-info"></i>
                  가상계좌 발급이 비활성화 상태입니다 (<code>TOSS_VA_ENABLED=false</code>). SMS는 정상 발송되었습니다.
                </div>
                <div style="background:var(--primary-light,#e0f4fb);border:1px solid var(--primary-accent);border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;">
                  <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;font-weight:600;">입금 계좌 안내</div>
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
          <div id="vaResultBadge" style="display:none;align-items:center;gap:4px;padding:4px 9px;background:var(--warning-light);border:1px solid #fcd34d;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
            <i class="fa-solid fa-building-columns" style="color:var(--warning);font-size:10px;"></i>
            <span id="vaResultBadgeText" style="font-weight:700;color:var(--warning);">-</span>
          </div>
        @endif
      @endif
      </div>{{-- #vaButtonWrap --}}

      {{-- 카카오 알림톡 --}}
      @php $kakaoSent = (bool)$prescription->kakao_sent_at; @endphp
      <div id="kakaoTriggerWrap" style="position:relative;">
        <button class="btn" id="btnKakaoTrigger" onclick="toggleKakaoPopover(event)"
                style="padding:5px 11px;font-weight:700;font-size:11px;display:flex;align-items:center;gap:4px;border-radius:var(--radius);white-space:nowrap;cursor:pointer;
                       {{ $kakaoSent ? 'background:var(--success-light);color:var(--success);border:1px solid #86efac;' : 'background:#FEE500;color:#191919;border:none;' }}">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:13px;height:13px;fill:{{ $kakaoSent ? 'var(--success)' : '#191919' }};flex-shrink:0;"><path d="M12 3C6.477 3 2 6.477 2 10.8c0 2.7 1.548 5.082 3.9 6.498l-.97 3.6a.3.3 0 0 0 .462.328l4.326-2.88A11.4 11.4 0 0 0 12 18.6c5.523 0 10-3.477 10-7.8S17.523 3 12 3z"/></svg>
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
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;">메시지 유형 선택</div>
              <div style="display:flex;flex-direction:column;gap:4px;" id="kakaoTemplateList">
                @foreach($kakaoTemplates as $code => $tpl)
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;transition:var(--transition);"
                       class="kakao-tpl-item" data-code="{{ $code }}"
                       onmouseover="this.style.borderColor='#FEE500';this.style.background='#FFFDE7';"
                       onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='var(--border)';this.style.background='';}">
                  <input type="radio" name="kakao_tpl" value="{{ $code }}" style="accent-color:#FEE500;" onchange="onTplChange(this)">
                  <div>
                    <div style="font-weight:600;">{{ $tpl['label'] }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ $tpl['desc'] }}</div>
                  </div>
                </label>
                @endforeach
              </div>
            </div>
            <div id="kakaoPreviewWrap" style="display:none;">
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">메시지 미리보기</div>
              <div id="kakaoPreviewBox" style="background:#F9F0FF;border:1px solid #E6C9FF;border-radius:var(--radius);padding:10px 12px;font-size:11px;line-height:1.8;white-space:pre-wrap;color:#333;max-height:120px;overflow-y:auto;"></div>
            </div>
            @if($prescription->order)
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;font-size:11px;display:flex;flex-direction:column;gap:4px;">
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:600;">본인 부담금</span>
                <span id="kakaoCopayAmt" style="font-weight:700;">{{ number_format($calcCopay) }}원</span>
              </div>
              @if($prescription->order->shipping_fee)
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:600;">배송비</span>
                <span style="font-weight:700;">{{ number_format($prescription->order->shipping_fee) }}원</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:4px;border-top:1px solid var(--border);">
                <span style="color:var(--text-muted);font-weight:600;">합계</span>
                <span id="kakaoDepositAmt" style="font-weight:700;color:var(--primary);">{{ number_format($calcDeposit) }}원</span>
              </div>
              @endif
            </div>
            @endif
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">수신 번호</div>
              <input type="text" id="kakaoMobile" class="form-control" style="font-size:12px;height:32px;"
                     placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone
                     value="{{ $prescription->patient?->mobile ?? $prescription->mobile_ocr ?? '' }}">
            </div>
            @if(config('kakao.test_mode'))
            <div style="background:var(--warning-light);border:1px solid #fde68a;border-radius:var(--radius);padding:6px 10px;font-size:10px;color:var(--warning);">
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
        <button class="btn" id="btnSmsTrigger" onclick="toggleSmsPopover(event)"
                style="padding:5px 11px;font-weight:700;font-size:11px;display:flex;align-items:center;gap:4px;border-radius:var(--radius);white-space:nowrap;cursor:pointer;
                       {{ $smsSent ? 'background:var(--success-light);color:var(--success);border:1px solid #86efac;' : 'background:var(--primary);color:#fff;border:none;' }}">
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
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;">메시지 유형 선택</div>
              <div style="display:flex;flex-direction:column;gap:4px;" id="smsTemplateList">
                @foreach($smsTemplates as $code => $tpl)
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;transition:var(--transition);"
                       class="sms-tpl-item" data-code="{{ $code }}" data-text="{{ addslashes($tpl['text']) }}"
                       onmouseover="this.style.borderColor='var(--primary)';this.style.background='rgba(27,102,245,.06)';"
                       onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='var(--border)';this.style.background='';}">
                  <input type="radio" name="sms_tpl" value="{{ $code }}" style="accent-color:var(--primary);" onchange="onSmsTplChange(this)">
                  <div>
                    <div style="font-weight:600;">{{ $tpl['label'] }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ $tpl['desc'] }}</div>
                  </div>
                </label>
                @endforeach
              </div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">메시지 내용 <span id="smsMsgLen" style="color:var(--text-muted);">(0자)</span></div>
              <textarea id="smsMsgBody" rows="5"
                        style="width:100%;font-size:11px;line-height:1.7;border:1px solid var(--border);border-radius:var(--radius);padding:8px 10px;resize:vertical;background:var(--bg-card);color:var(--text);"
                        placeholder="메시지 유형을 선택하면 자동으로 채워집니다."
                        oninput="updateSmsLen()"></textarea>
              <div id="smsMsgType" style="font-size:10px;color:var(--text-muted);margin-top:2px;text-align:right;"></div>
            </div>
            @if($prescription->order)
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;font-size:11px;display:flex;flex-direction:column;gap:4px;">
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:600;">본인 부담금</span>
                <span id="smsCopayAmt" style="font-weight:700;">{{ number_format($calcCopay) }}원</span>
              </div>
              @if($prescription->order->shipping_fee)
              <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--text-muted);font-weight:600;">배송비</span>
                <span style="font-weight:700;">{{ number_format($prescription->order->shipping_fee) }}원</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:4px;border-top:1px solid var(--border);">
                <span style="color:var(--text-muted);font-weight:600;">합계</span>
                <span id="smsDepositAmt" style="font-weight:700;color:var(--primary);">{{ number_format($calcDeposit) }}원</span>
              </div>
              @endif
            </div>
            @endif
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">수신 번호</div>
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

      {{-- 현금영수증 --}}
      <div id="cashReceiptArea">
        @if($prescription->order?->cash_receipt_status === 'issued')
        <div style="display:flex;align-items:center;gap:4px;padding:4px 9px;background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
          <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:10px;"></i>
          <span style="font-weight:700;color:var(--success);">현금영수증</span>
          <button onclick="toggleCrDetailPopover(event)" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid var(--success);color:var(--success);border-radius:3px;cursor:pointer;margin-left:2px;">상세</button>
          <button onclick="cancelCashReceipt()" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:3px;cursor:pointer;">취소</button>
        </div>
        @else
        <button class="btn btn-outline" id="btnCrIssueTrigger" onclick="toggleCrIssuePopover(event)" style="padding:5px 10px;font-size:11px;white-space:nowrap;">
          <i class="fa-solid fa-receipt"></i> 현금영수증
        </button>
        @endif
      </div>

      {{-- 팩스 전송 --}}
      <div id="faxTriggerWrap" style="display:{{ $lastFaxHistory ? 'none' : 'block' }};position:relative;">
        <button class="btn btn-outline" id="btnFaxTrigger" onclick="toggleFaxPopover(event)"
                style="padding:5px 11px;font-size:11px;white-space:nowrap;display:flex;align-items:center;gap:4px;position:relative;">
          <i class="fa-solid fa-fax" style="font-size:12px;"></i> 팩스
          <span id="faxSentBadge" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};position:absolute;top:-5px;right:-5px;width:16px;height:16px;border-radius:50%;background:var(--success);border:2px solid var(--bg-card);align-items:center;justify-content:center;">
            <i class="fa-solid fa-check" style="font-size:7px;color:#fff;"></i>
          </span>
        </button>
        <div id="faxPopover" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:580px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:500;">
          <div style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
            <div style="width:10px;height:10px;background:#374151;border:1px solid #374151;transform:rotate(45deg);margin:3px auto 0;"></div>
          </div>
          {{-- 헤더 --}}
          <div style="background:#374151;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
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
            $fhRecip   = ['nhis'=>'국민건강보험공단','hira'=>'건강보험심사평가원','custom'=>'기타'][$lastFaxHistory?->recipient_type ?? ''] ?? ($lastFaxHistory?->recipient_type ?? '');
            $fhPdfUrl  = $lastFaxHistory?->pdf_path
              ? (rtrim(request()->root(), '/') . '/storage/' . $lastFaxHistory->pdf_path)
              : null;
          @endphp
          <div id="faxSentBanner" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};padding:8px 14px;background:#f0fdf4;border-bottom:1px solid #86efac;font-size:11px;align-items:center;gap:8px;">
            <i class="fa-solid fa-circle-check" style="color:var(--success);flex-shrink:0;"></i>
            <div style="flex:1;line-height:1.5;">
              <span id="faxSentBannerText" style="font-weight:600;color:#166534;">{{ $lastFaxHistory ? "{$fhTimeStr} 전송 완료 — {$fhRecip} ({$fhFaxNo})" . ($fhLabels ? ' | ' . implode(', ', $fhLabels) : '') : '' }}</span>
            </div>
            <a id="faxSentBannerPdf" href="{{ $fhPdfUrl ?? '#' }}" target="_blank"
               style="font-size:10px;color:var(--primary);white-space:nowrap;text-decoration:none;font-weight:600;{{ $fhPdfUrl ? '' : 'display:none;' }}">
              <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
          </div>
          {{-- 2컬럼 본문 --}}
          <div style="padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch;">
            {{-- 왼쪽: 수신처 + 팩스번호 + 안내 --}}
            <div style="display:flex;flex-direction:column;gap:10px;">
              <div>
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;">수신처</div>
                <div style="display:flex;flex-direction:column;gap:5px;">
                  <button type="button" class="fax-recipient-btn" data-fax="" data-recipient-type="nhis" onclick="selectFaxRecipient(this)"
                          style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:2px solid var(--primary);border-radius:var(--radius);background:var(--primary-light);cursor:pointer;text-align:left;">
                    <div>
                      <div style="font-size:12px;font-weight:700;color:var(--primary);">국민건강보험공단</div>
                      <div style="font-size:10px;color:var(--text-muted);">NHIS · 지사 검색</div>
                    </div>
                    <i class="fa-solid fa-magnifying-glass" style="font-size:12px;color:var(--primary);"></i>
                  </button>
                  <button type="button" class="fax-recipient-btn" data-fax="02-705-4000" data-recipient-type="hira" onclick="selectFaxRecipient(this)"
                          style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:2px solid var(--border);border-radius:var(--radius);background:var(--bg-card);cursor:pointer;text-align:left;">
                    <div>
                      <div style="font-size:12px;font-weight:700;color:var(--text);">건강보험심사평가원</div>
                      <div style="font-size:10px;color:var(--text-muted);">HIRA</div>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:var(--text-muted);font-family:monospace;">02-705-4000</span>
                  </button>
                  <button type="button" class="fax-recipient-btn" data-fax="" data-recipient-type="custom" onclick="selectFaxRecipient(this)"
                          style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:2px solid var(--border);border-radius:var(--radius);background:var(--bg-card);cursor:pointer;text-align:left;">
                    <div>
                      <div style="font-size:12px;font-weight:700;color:var(--text);">기타</div>
                      <div style="font-size:10px;color:var(--text-muted);">직접 입력</div>
                    </div>
                    <i class="fa-solid fa-pen" style="font-size:11px;color:var(--text-muted);"></i>
                  </button>
                </div>
                {{-- NHIS 지사 검색 패널 --}}
                <div id="nhisSearchPanel" style="display:none;background:var(--bg);border:1px solid var(--primary);border-radius:var(--radius);padding:8px;margin-top:6px;">
                  <input type="text" id="nhisSearchInput" class="form-control"
                         style="height:28px;font-size:11px;margin-bottom:6px;padding:3px 8px;"
                         placeholder="지역명 또는 지사명 검색..."
                         oninput="renderNhisOffices(this.value)">
                  <div id="nhisOfficeList" style="max-height:112px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;"></div>
                </div>
              </div>
              <div>
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:5px;">수신 팩스번호</div>
                <input type="text" id="fax-no" class="form-control" style="font-size:12px;height:32px;"
                       placeholder="지사 선택 또는 직접 입력"
                       oninput="onFaxNoInput()">
              </div>
              <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:7px 10px;font-size:10px;color:var(--text-muted);line-height:1.6;">
                <i class="fa-solid fa-circle-info" style="margin-right:3px;"></i>
                NHIS는 지사를 검색하여 선택하세요. 팩스번호는 직접 수정 가능합니다.
              </div>
            </div>
            {{-- 오른쪽: 전송 서류 --}}
            <div style="display:flex;flex-direction:column;">
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;">전송 서류 선택</div>
              <div style="display:flex;flex-direction:column;gap:4px;flex:1;overflow-y:auto;padding-right:2px;">
                <label style="display:flex;align-items:flex-start;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;">
                  <input type="checkbox" id="fax-doc-auth" value="authorization" style="accent-color:var(--primary);margin-top:3px;" checked>
                  <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                      <span style="font-weight:600;">위임장</span>
                      @php
                        $latestConsent = $prescription->consents()->where('status','agreed')->latest()->first();
                      @endphp
                      @if($latestConsent?->signature_data)
                        <span style="font-size:10px;background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:3px;padding:1px 6px;">전자서명 완료</span>
                      @else
                        <span style="font-size:10px;background:#fffbeb;color:#b45309;border:1px solid #fcd34d;border-radius:3px;padding:1px 6px;">자동 생성</span>
                      @endif
                      <button type="button"
                              onclick="window.open('{{ route('prescriptions.authorization', $prescription) }}','auth_preview','width=860,height=1100,scrollbars=yes,resizable=yes')"
                              style="font-size:10px;color:var(--primary);background:none;border:none;padding:0;cursor:pointer;" title="위임장 미리보기">
                        <i class="fa-solid fa-up-right-from-square"></i> 미리보기
                      </button>
                    </div>
                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                      @if($latestConsent?->signature_data)
                        환자 전자서명이 포함된 위임장
                      @else
                        서명 없음 — 처방 정보로 자동 생성
                      @endif
                    </div>
                  </div>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;">
                  <input type="checkbox" id="fax-doc-rx" value="prescription" style="accent-color:var(--primary);" checked>
                  <div>
                    <div style="font-weight:600;">처방전</div>
                    <div style="font-size:10px;color:var(--text-muted);">
                      @if($prescription->image_path)
                        업로드된 처방전 이미지
                      @else
                        <span style="color:var(--warning);">처방전 이미지 없음</span>
                      @endif
                    </div>
                  </div>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;">
                  <input type="checkbox" id="fax-doc-purchase" value="purchase_history" style="accent-color:var(--primary);" checked>
                  <div>
                    <div style="font-weight:600;">제품 구매내역</div>
                    <div style="font-size:10px;color:var(--text-muted);">판매 제품 상세 내역서</div>
                  </div>
                </label>
                @php $crIssued = $prescription->order?->cash_receipt_status === 'issued'; @endphp
                <label id="fax-cr-label" style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:{{ $crIssued ? 'pointer' : 'default' }};font-size:12px;opacity:{{ $crIssued ? '1' : '0.5' }};">
                  <input type="checkbox" id="fax-doc-cash-receipt" value="cash_receipt" style="accent-color:var(--primary);" {{ $crIssued ? 'checked' : 'disabled' }}>
                  <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:6px;">
                      <span style="font-weight:600;">현금영수증</span>
                      <span id="fax-cr-badge" style="font-size:10px;border-radius:3px;padding:1px 6px;{{ $crIssued ? 'background:#f0fdf4;color:#166534;border:1px solid #86efac;' : 'background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;' }}">{{ $crIssued ? '발행완료' : '미발행' }}</span>
                    </div>
                    <div id="fax-cr-desc" style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                      {{ $crIssued ? '승인번호: ' . $prescription->order->cash_receipt_no : '현금영수증 발행 후 선택 가능' }}
                    </div>
                  </div>
                </label>

                {{-- ── 첨부 문서 (주민등록증·위임장 등) ── --}}
                @if($prescription->attachments->isNotEmpty())
                <div style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);">
                  <div style="font-size:10px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">
                    <i class="fa-solid fa-paperclip"></i> 첨부 문서
                  </div>
                  @foreach($prescription->attachments as $att)
                  <label style="display:flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:12px;margin-bottom:3px;">
                    <input type="checkbox" class="fax-att-chk" value="{{ $att->id }}"
                           style="accent-color:var(--primary);" checked>
                    <div style="flex:1;min-width:0;">
                      <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-weight:600;">{{ $att->doc_type_label }}</span>
                        <span style="font-size:10px;background:var(--primary-light);color:var(--primary);border:1px solid var(--primary-accent);border-radius:3px;padding:1px 5px;">첨부</span>
                      </div>
                      <div style="font-size:10px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $att->file_original_name }}</div>
                    </div>
                    @if($att->is_image)
                      <img src="{{ $att->file_url }}" style="width:28px;height:28px;object-fit:cover;border-radius:3px;border:1px solid var(--border);flex-shrink:0;" />
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
                    style="width:100%;padding:8px;background:#374151;color:#fff;border:none;border-radius:var(--radius);font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
              <i class="fa-solid fa-paper-plane"></i> 팩스 전송
            </button>
          </div>
        </div>
      </div>
      <div id="faxResultBadge" style="display:{{ $lastFaxHistory ? 'flex' : 'none' }};align-items:center;gap:4px;padding:4px 9px;background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--success);">팩스 전송</span>
        <button id="faxPdfViewBtn" data-url="{{ $lastFaxHistory?->pdfUrl() }}"
                onclick="openFaxPdfModal()"
                style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid #6366f1;color:#6366f1;border-radius:3px;cursor:pointer;margin-left:2px;">보기</button>
        <button onclick="reopenFaxPopover(event)" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid var(--success);color:var(--success);border-radius:3px;cursor:pointer;">재전송</button>
      </div>

      {{-- Withworks 판매번호 --}}
      <div id="wwSoCard" style="display:flex;align-items:center;gap:5px;padding:4px 9px;border:1px solid {{ $prescription->order?->withworks_so_no ? 'var(--primary)' : 'var(--border)' }};border-radius:var(--radius);background:{{ $prescription->order?->withworks_so_no ? 'var(--primary-light)' : 'var(--bg-card)' }};">
        <i class="fa-solid fa-link" style="color:var(--primary);font-size:10px;flex-shrink:0;"></i>
        <div id="wwSoContent" style="font-size:11px;line-height:1.2;">
          @if($prescription->order?->withworks_so_no)
          <span style="font-family:monospace;font-weight:800;color:var(--primary);">{{ $prescription->order->withworks_so_no }}</span>
          @else
          <span id="wwSoBadge" style="color:var(--text-muted);">미연계</span>
          @endif
        </div>
      </div>

      {{-- 세금계산서 --}}
      @if($prescription->order?->tax_invoice_status === 'issued')
      <div style="display:flex;align-items:center;gap:4px;padding:4px 9px;background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--success);">세금계산서</span>
        <button onclick="cancelTaxInvoice()" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:3px;cursor:pointer;margin-left:2px;">취소</button>
      </div>
      @else
      <div id="tiNotIssuedWrap" style="position:relative;">
        <button class="btn btn-outline" id="btnTiTrigger" onclick="toggleTaxInvoicePopover(event)" style="padding:5px 10px;font-size:11px;white-space:nowrap;">
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
              <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">발행 유형</label>
              <select id="ti-type" class="form-control" style="font-size:12px;">
                <option value="electronic">전자세금계산서</option>
                <option value="manual">수기</option>
              </select>
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">공급받는자 상호 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-biz-name" class="form-control" style="font-size:12px;" placeholder="(주)예시">
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">대표자명 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-ceo-name" class="form-control" style="font-size:12px;" placeholder="홍길동">
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">사업자등록번호 <span style="color:var(--danger);">*</span></label>
              <input type="text" id="ti-biz-no" class="form-control" style="font-size:12px;" placeholder="123-45-67890">
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">이메일 (전자발송)</label>
              <input type="email" id="ti-email" class="form-control" style="font-size:12px;" placeholder="billing@example.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">공급가액 <span style="color:var(--danger);">*</span></label>
                <input type="text" id="ti-supply" class="form-control" style="font-size:12px;" inputmode="numeric" placeholder="0" oninput="formatCrAmount(this); autoCalcVat()">
              </div>
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">세액 <span style="color:var(--danger);">*</span></label>
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
      <div id="tiResultBadge" style="display:none;align-items:center;gap:4px;padding:4px 9px;background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
        <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:10px;"></i>
        <span style="font-weight:700;color:var(--success);">세금계산서</span>
        <button onclick="cancelTaxInvoice()" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:3px;cursor:pointer;margin-left:2px;">취소</button>
      </div>
      @endif
    </div>

    {{-- 환자명 + 메모 버튼 묶음 --}}
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;">
        <i class="fa-solid fa-user"></i>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;">
          {{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-' }}
          @if(!$prescription->patient)
            <span style="font-size:10px;font-weight:400;color:var(--text-muted);margin-left:4px;">(OCR)</span>
          @endif
        </div>
        <div style="font-size:11px;color:var(--text-muted);">
          @if($prescription->patient)
            {{ $prescription->patient->birth_date?->format('Y-m-d') }} · 만 {{ $prescription->patient->age }}세
          @else
            {{ $prescription->masked_resident_no_ocr ?? '-' }}
          @endif
        </div>
      </div>
    </div>
    <div style="width:1px;height:32px;background:var(--border);"></div>
    <div style="font-size:12px;color:var(--text-secondary);">
      <i class="fa-solid fa-phone" style="color:var(--primary);"></i>
      {{ $prescription->patient?->mobile ?? '-' }}
    </div>
    <div style="font-size:12px;color:var(--text-secondary);">
      <i class="fa-solid fa-hospital" style="color:var(--primary);"></i>
      {{ $prescription->hospital_name ?? '-' }}
    </div>
    <div style="font-size:12px;color:var(--text-secondary);">
      <i class="fa-solid fa-user-tie" style="color:var(--primary);"></i> 담당: {{ $prescription->assignedUser?->name ?? '-' }}
    </div>
      @if($prescription->patient?->is_nhis_eligible)
      <span class="badge badge-success" style="order:8;"><i class="fa-solid fa-won-sign"></i> 급여 대상 ({{ $prescription->patient->nhis_coverage_rate }}%)</span>
      @endif
  </div>

  {{-- ── 메모 패널 (JS로 위치 결정 — fixed) ─────────────── --}}
  <div id="memoPanelWrap" style="display:none;position:fixed;z-index:1200;width:340px;">
    <div style="background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.14);overflow:hidden;">
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
                  style="padding:4px 14px;background:var(--primary);color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer;font-weight:600;">
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
      <div style="display:flex;justify-content:flex-end;margin-bottom:6px;">
        <button type="button" id="btnToggleViewerSide" onclick="toggleViewerSide()"
                class="btn btn-outline btn-sm" title="뷰어를 오른쪽으로"
                style="font-size:11px;padding:3px 10px;display:flex;align-items:center;gap:5px;">
          <i class="fa-solid fa-arrows-left-right" style="font-size:11px;"></i>
          <span id="btnToggleViewerSideLabel">오른쪽으로</span>
        </button>
      </div>
      <div class="img-viewer">
        <div class="img-viewer-toolbar">
          <button class="img-tool-btn" onclick="zoomOut()" title="축소"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
          <span id="zoomLabel">100%</span>
          <button class="img-tool-btn" onclick="zoomIn()"  title="확대"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          <button class="img-tool-btn" onclick="rotateImg()" title="회전"><i class="fa-solid fa-rotate-right"></i></button>
          <button class="img-tool-btn" onclick="resetImg()" title="처음으로 복원"><i class="fa-solid fa-arrows-rotate"></i></button>
          <a id="viewerOpenBtn" class="img-tool-btn" href="{{ $prescription->image_url ?? '#' }}" target="_blank" title="원본보기" @if(!$prescription->image_url) style="display:none;" @endif><i class="fa-solid fa-expand"></i></a>
        </div>
        <div class="img-viewer-canvas" id="imgCanvas">
          @php $isRxPdf = str_contains($prescription->image_mime_type ?? '', 'pdf'); @endphp
          @if($prescription->image_url && $isRxPdf)
            <img id="prescCanvas" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="" draggable="false" />
            <iframe id="pdfCanvas" src="{{ $prescription->image_url }}" style="width:100%;height:100%;border:none;background:#fff;"></iframe>
          @elseif($prescription->image_url)
            <img id="prescCanvas" src="{{ $prescription->image_url }}" style="max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="처방전 이미지" draggable="false" />
            <div id="viewerBadge" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.7);color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;">
              {{ $prescription->rx_number }}
            </div>
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
        <div style="padding:8px 12px;background:#0f172a;display:flex;align-items:center;justify-content:center;gap:12px;">
          <button onclick="prevRecord()" style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);border:none;color:#94a3b8;cursor:pointer;"><i class="fa-solid fa-chevron-left" style="font-size:11px;"></i></button>
          <span style="font-size:12px;color:#94a3b8;">{{ $prescription->rx_number }}</span>
          <button onclick="nextRecord()" style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);border:none;color:#94a3b8;cursor:pointer;"><i class="fa-solid fa-chevron-right" style="font-size:11px;"></i></button>
        </div>
      </div>

      {{-- ── 통합 문서 스트립 (처방전 + 첨부 파일) ── --}}
      <div class="mt-3" id="docStripWrap" @if(!$prescription->image_url && $prescription->attachments->isEmpty()) style="display:none;" @endif>
        <div style="font-size:11px;font-weight:700;color:var(--text-secondary);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-file-image"></i> 문서 (<span id="docCount">{{ ($prescription->image_url ? 1 : 0) + $prescription->attachments->count() }}</span>건)
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

      {{-- ── 첨부 문서 추가 버튼 ── --}}
      <div class="mt-2">
        <div style="position:relative;display:inline-block;">
          <input type="text" id="attachDocTypeSelect" value="주민등록증" autocomplete="off"
                 style="font-size:11px;border:1px solid var(--border);border-radius:var(--radius);padding:5px 24px 5px 8px;background:var(--bg-card);color:var(--text-primary);width:110px;"
                 oninput="_adtFilter(this.value)"
                 onfocus="_adtOpen()"
                 onblur="setTimeout(_adtClose,150)" />
          <span onmousedown="event.preventDefault();_adtToggle()" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);font-size:20px;pointer-events:auto;">▾</span>
          <div id="_adtDrop" style="display:none;position:absolute;top:calc(100% + 2px);left:0;min-width:100%;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:10001;">
            <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('처방전')"   style="padding:5px 10px;font-size:11px;cursor:pointer;">처방전</div>
            <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('위임장')"   style="padding:5px 10px;font-size:11px;cursor:pointer;">위임장</div>
            <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('주민등록증')" style="padding:5px 10px;font-size:11px;cursor:pointer;">주민등록증</div>
            <div class="_adt-opt" onmousedown="event.preventDefault();_adtPick('기타')"     style="padding:5px 10px;font-size:11px;cursor:pointer;">기타</div>
          </div>
        </div>
        <button type="button" onclick="document.getElementById('attachUploadInput').click()"
          style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:5px 12px;border-radius:var(--radius);border:1px dashed var(--border);background:var(--bg);color:var(--text-secondary);cursor:pointer;transition:all .15s;margin-left:6px;vertical-align:top;"
          onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
          onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
          <i class="fa-solid fa-plus"></i> 첨부 문서 추가
        </button>
        <input type="file" id="attachUploadInput" accept=".jpg,.jpeg,.png,.pdf,.heic" style="display:none" onchange="handleAttachUpload(this)">
      </div>

      {{-- 등록자 / OCR 신뢰도 탭 카드 --}}
      <div class="card mt-4">
        {{-- 탭 헤더 --}}
        <div style="display:flex;border-bottom:1px solid var(--border);padding:0 4px;">
          <button id="infoTab-uploader" onclick="switchInfoTab('uploader')"
                  style="flex:1;padding:8px 4px;font-size:11px;font-weight:600;background:none;border:none;border-bottom:2px solid var(--primary);color:var(--primary);cursor:pointer;">
            <i class="fa-solid fa-upload" style="font-size:10px;"></i> 등록자
          </button>
          <button id="infoTab-ocr" onclick="switchInfoTab('ocr')"
                  style="flex:1;padding:8px 4px;font-size:11px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;color:var(--text-muted);cursor:pointer;">
            <i class="fa-solid fa-brain" style="font-size:10px;"></i> OCR 신뢰도
          </button>
        </div>

        {{-- 등록자 패널 --}}
        <div id="infoPanel-uploader" class="card-body" style="padding:10px 14px;">
          @if($prescription->creator)
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;font-weight:700;color:var(--primary);">
                {{ mb_substr($prescription->creator->name, 0, 1) }}
              </div>
              <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:700;">{{ $prescription->creator->name }}</div>
                <div style="font-size:10px;color:var(--text-muted);">{{ $prescription->created_at->format('Y-m-d H:i') }}</div>
              </div>
              <button onclick="openChatWith({{ $prescription->creator->id }}, '{{ $prescription->creator->name }}')"
                      style="flex-shrink:0;width:28px;height:28px;border-radius:50%;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;"
                      title="{{ $prescription->creator->name }}와 채팅">
                <i class="fa-solid fa-comments"></i>
              </button>
            </div>
            @if($prescription->admin_note)
            <div style="margin-top:10px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);font-size:12px;line-height:1.7;color:var(--text-primary);white-space:pre-wrap;">
              <div style="font-size:10px;font-weight:700;color:#d97706;margin-bottom:4px;"><i class="fa-solid fa-note-sticky"></i> 등록자 메모</div>{{ $prescription->admin_note }}</div>
            @endif
          @else
            <div style="font-size:11px;color:var(--text-muted);">
              <i class="fa-solid fa-circle-question"></i> 등록자 정보 없음 · {{ $prescription->created_at->format('Y-m-d H:i') }}
            </div>
          @endif
        </div>

        {{-- OCR 신뢰도 패널 --}}
        <div id="infoPanel-ocr" class="card-body" style="display:none;padding:14px;">
          @if($prescription->ocr_confidence)
          @php $dispConf = $prescription->display_confidence; @endphp
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <div style="flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden;">
              <div id="conf-bar" style="width:{{ $dispConf }}%;height:100%;background:{{ $dispConf >= 90 ? 'var(--success)' : ($dispConf >= 70 ? 'var(--warning)' : 'var(--danger)') }};border-radius:4px;transition:width .4s;"></div>
            </div>
            <span id="conf-label" style="font-size:14px;font-weight:700;color:{{ $dispConf >= 90 ? 'var(--success)' : 'var(--warning)' }};">{{ $dispConf }}%</span>
          </div>
          @else
          <div id="conf-empty" style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">분석 전</div>
          @endif
          <div style="display:flex;gap:6px;">
            <button id="btn-reanalyze" class="btn btn-outline" onclick="reanalyzeOCR()"
                    style="flex:1;font-size:12px;justify-content:center;gap:6px;">
              <i class="fa-solid fa-rotate"></i> OCR 재분석
            </button>
            <button class="btn btn-outline" onclick="resetOCR()" title="원본 데이터로 초기화"
                    style="font-size:12px;justify-content:center;gap:6px;color:var(--text-muted);">
              <i class="fa-solid fa-arrow-rotate-left"></i> 초기화
            </button>
          </div>
        </div>
      </div>

      @if($prescription->review_memo)
      <div class="card mt-3">
        <div class="card-header"><i class="fa-solid fa-note-sticky" style="color:var(--warning);"></i><span class="card-header-title">검수 메모</span></div>
        <div class="card-body" style="font-size:12px;color:var(--text-secondary);">{{ $prescription->review_memo }}</div>
      </div>
      @endif
    </div>{{-- /viewerInner --}}
    </div>{{-- /viewerCol --}}

    {{-- Col 2: OCR Edit + Order --}}
    <div id="tabsCol">
      <div id="tabBarOuter"><div id="tabBarInner" class="tab-bar">
        <div class="tab-bar-tabs">
          <button class="tab-btn active" onclick="switchTab(this,'tab-ocr')"><i class="fa-solid fa-wand-magic-sparkles"></i> 처방전 검수</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-product')"><i class="fa-solid fa-boxes-stacked"></i> 처방 제품</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-order')"><i class="fa-solid fa-cart-shopping"></i> 주문 연계</button>
          <button class="tab-btn" onclick="switchTab(this,'tab-history')"><i class="fa-solid fa-timeline"></i> 이력</button>
        </div>
        <div style="display:flex;align-items:center;gap:5px;">
          <button type="button" id="btnAccToggleAll" onclick="toggleAllAcc()"
                  style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;transition:var(--transition);">
            <i class="fa-solid fa-angles-down" id="btnAccToggleAllIcon"></i>
            <span id="btnAccToggleAllLabel">전체 열기</span>
          </button>
          <button type="button" id="btnViewToggle" onclick="toggleTabView()"
                  style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;transition:var(--transition);">
            <i class="fa-solid fa-table-list" id="btnViewToggleIcon"></i>
            <span id="btnViewToggleLabel">테이블뷰</span>
          </button>
        </div>
      </div></div>{{-- /tabBarInner /tabBarOuter --}}

      {{-- Tab: OCR Edit (처방전 검수) --}}
      <div class="tab-pane active" id="tab-ocr">
      <div class="cv">

        {{-- OCR 신뢰도 경고 배너 --}}
        @if($prescription->ocr_confidence && $prescription->display_confidence < 85)
        <div style="display:flex;align-items:center;gap:8px;background:var(--warning-light);border:1px solid #fde68a;border-radius:var(--radius);padding:10px 14px;margin-bottom:12px;font-size:12px;font-weight:600;color:var(--warning);">
          <i class="fa-solid fa-triangle-exclamation" style="font-size:15px;flex-shrink:0;"></i>
          OCR 신뢰도 {{ $prescription->display_confidence }}% — 아래 항목을 수동으로 확인 후 저장해주세요.
        </div>
        @endif

        @php $displayRn = $prescription->masked_resident_no_ocr ?? $prescription->patient?->masked_resident_no; @endphp


        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 1: 상담 · 환자 정보 (통합)
        ───────────────────────────────────────────────── --}}
        @php
          $curCounselNo   = $prescription->counseling?->counselling_no ?? '';
          $curCounselDate = $prescription->counseling?->counsel_date ?? now()->format('Y-m-d');
          $rawCallNo = $prescription->counseling?->call_no ?? '';
          $digCallNo = preg_replace('/[^0-9]/', '', $rawCallNo);
          if (strlen($digCallNo) === 11)     $fmtCallNo = substr($digCallNo,0,3).'-'.substr($digCallNo,3,4).'-'.substr($digCallNo,7);
          elseif (strlen($digCallNo) >= 9)   $fmtCallNo = substr($digCallNo,0,3).'-'.substr($digCallNo,3,3).'-'.substr($digCallNo,6);
          else                               $fmtCallNo = $rawCallNo;
          $isReturningPatient = $prescription->patient_id && $prevCounselings->isNotEmpty();
        @endphp
        <div class="rx-acc-item is-open">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-clipboard-user" style="color:var(--primary);"></i> 상담 · 환자 정보
              @if($isReturningPatient)
                <span style="display:inline-flex;align-items:center;gap:3px;background:#fef3c7;color:#d97706;border:1px solid #fde68a;border-radius:4px;font-size:10px;font-weight:700;padding:1px 6px;">
                  <i class="fa-solid fa-rotate-right" style="font-size:9px;"></i> 재방문
                </span>
              @endif
            </span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">상담번호 · 환자명 · 연락처 · 주소</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon open"></i>
            </div>
          </div>
          <div class="rx-acc-body">

            {{-- ▸ 상담 정보 소제목 + 메모 버튼 --}}
            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:11px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid var(--border-light);">
              <span style="display:flex;align-items:center;gap:5px;">
                <i class="fa-solid fa-clipboard-list" style="font-size:10px;"></i> 상담 정보
              </span>
              <button id="memoPanelToggleBtn" onclick="toggleMemoPanel(event)"
                      style="display:flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;border:1px solid var(--primary);background:#fff;color:var(--primary);font-size:10px;font-weight:700;cursor:pointer;position:relative;flex-shrink:0;">
                <i class="fa-solid fa-note-sticky"></i> 메모
                <span id="memoBadgeCount"
                      style="display:{{ $prescription->memos->count() > 0 ? 'flex' : 'none' }};position:absolute;top:-5px;right:-5px;width:14px;height:14px;border-radius:50%;background:var(--danger);color:#fff;font-size:9px;align-items:center;justify-content:center;font-weight:700;line-height:1;">
                  {{ $prescription->memos->count() }}
                </span>
              </button>
            </div>
            {{-- 상담번호 (full) --}}
            <div class="rx-field-row" style="margin-bottom:10px;">
              <span class="rx-field-label">상담번호</span>
              <div style="display:flex;gap:6px;flex:1;min-width:0;">
                <input type="text" class="form-control" id="f-counselling-no"
                       value="{{ $curCounselNo }}"
                       placeholder="채번 버튼을 눌러 번호를 생성하세요"
                       style="flex:1;" />
                @if($isReturningPatient)
                <button type="button" onclick="openPrevCounselModal()"
                        title="이전 상담 이력 {{ $prevCounselings->count() }}건"
                        style="flex-shrink:0;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;padding:0 11px;height:36px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:var(--radius);font-size:12px;font-weight:700;cursor:pointer;">
                  <i class="fa-solid fa-clock-rotate-left"></i> 과거 상담
                  <span style="font-size:10px;background:#f0d060;border-radius:10px;padding:1px 5px;">{{ $prevCounselings->count() }}</span>
                </button>
                @endif
                <button type="button" id="btnCounselNo" onclick="generateCounselNo()"
                        style="flex-shrink:0;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;padding:0 12px;height:36px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-size:12px;font-weight:700;cursor:pointer;">
                  <i class="fa-solid fa-hashtag"></i> 추가상담(채번)
                </button>
              </div>
            </div>
            {{-- 4단: 상담일자 | 상담유형 | 처방전여부 | 상담상태 --}}
            <div class="rx-grid-4" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">상담 일자</span>
                <div style="display:flex;gap:4px;flex:1;align-items:center;">
                  <input type="date" class="form-control" id="f-counsel-date" value="{{ $curCounselDate }}" style="flex:1;min-width:0;" />
                  <button type="button" onclick="document.getElementById('f-counsel-date').value='{{ now()->format('Y-m-d') }}'"
                          style="flex-shrink:0;height:36px;padding:0 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;">오늘</button>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">상담 유형</span>
                <select class="form-control" id="f-counsel-type" onchange="onCounselTypeChange(this.value)" style="flex:1;">
                  <option value="">선택</option>
                  <option value="1013" @selected(($prescription->counseling?->type ?? '') == '1013')>구매</option>
                  <option value="1016" @selected(($prescription->counseling?->type ?? '') == '1016')>개인구매</option>
                  <option value="1020" @selected(($prescription->counseling?->type ?? '') == '1020')>반품</option>
                  <option value="1030" @selected(($prescription->counseling?->type ?? '') == '1030')>문의</option>
                  <option value="1050" @selected(($prescription->counseling?->type ?? '') == '1050')>기타</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">처방전 여부</span>
                <select class="form-control" id="f-acc-add-type" style="flex:1;">
                  <option value="">선택</option>
                  <option value="20"  @selected(($prescription->counseling?->acc_add_type ?? '') == '20')>처방외</option>
                  <option value="10"  @selected(($prescription->counseling?->acc_add_type ?? '') == '10')>원외</option>
                  <option value="30"  @selected(($prescription->counseling?->acc_add_type ?? '') == '30')>원내</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">상담 상태</span>
                <select class="form-control" id="f-counsel-status" onchange="onCounselStatusChange(this.value)" style="flex:1;">
                  <option value="">선택</option>
                  <option value="02" @selected(($prescription->counseling?->status ?? '') == '02')>등록</option>
                  <option value="50" @selected(($prescription->counseling?->status ?? '') == '50')>재상담</option>
                  <option value="95" @selected(($prescription->counseling?->status ?? '') == '95')>확정</option>
                  <option value="99" @selected(($prescription->counseling?->status ?? '') == '99')>취소</option>
                </select>
              </div>
            </div>
            {{-- 재상담일자 --}}
            <div style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">재 상담 일자</span>
                <input type="date" class="form-control" id="f-re-counsel-date"
                       value="{{ $prescription->counseling?->re_counsel_date ?? '' }}" style="flex:1;" />
              </div>
            </div>
            {{-- 메모 (full) --}}
            <div class="rx-field-row" style="margin-bottom:14px;">
              <span class="rx-field-label">메모</span>
              <textarea class="form-control" id="f-counsel-memo" rows="2" style="flex:1;resize:vertical;">{{ $prescription->counseling?->contents ?? '' }}</textarea>
            </div>

            {{-- ▸ 환자 정보 소제목 --}}
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid var(--border-light);">
              <i class="fa-solid fa-user" style="font-size:10px;color:var(--primary);"></i> 환자 정보
            </div>
            {{-- 3단: 환자명 | 연락처 | 주민등록번호 --}}
            <div class="rx-grid-3" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">환자명 <span style="color:red;">*</span></span>
                <div class="field-group" style="flex:1;">
                  <input type="text" class="form-control has-ok" id="f-name" value="{{ $prescription->patient_name_ocr }}" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">연락처</span>
                <input type="text" class="form-control" id="f-mobile"
                       value="{{ $prescription->mobile_ocr ?? $prescription->patient?->mobile ?? '' }}"
                       placeholder="010-XXXX-XXXX / 02-XXXX-XXXX" data-phone style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">주민등록번호</span>
                <div style="display:flex;gap:4px;flex:1;">
                  <input type="hidden" id="f-resident-real" value="{{ $prescription->resident_no_ocr ?? $prescription->patient?->resident_no ?? '' }}" />
                  <input type="text" class="form-control" id="f-resident"
                         value="{{ $displayRn }}" placeholder="XXXXXX-XXXXXXX" readonly
                         style="flex:1;background:var(--bg-secondary,#f8f9fa);cursor:default;letter-spacing:1px;" />
                  <button type="button" id="btn-resident-toggle" onclick="toggleResidentNo()" title="주민등록번호 표시/숨김"
                          style="flex-shrink:0;width:36px;height:36px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;">
                    <i class="fa-solid fa-lock" id="icon-resident-toggle"></i>
                  </button>
                </div>
              </div>
            </div>
            {{-- 2단: 보호자명 | 일일도뇨횟수 --}}
            <div class="rx-field-grid" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">보호자명</span>
                <input type="text" class="form-control" id="f-guardian" value="{{ $prescription->counseling?->udf24 ?? '' }}" placeholder="보호자명" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">일일 도뇨횟수</span>
                <select class="form-control" id="f-diverticulums" style="flex:1;">
                  <option value="">선택</option>
                  <option value="01" @selected(($prescription->counseling?->diverticulums ?? '') == '01')>1회 미만</option>
                  <option value="02" @selected(($prescription->counseling?->diverticulums ?? '') == '02')>1~2회</option>
                  <option value="03" @selected(($prescription->counseling?->diverticulums ?? '') == '03')>3회 ~ 4회</option>
                  <option value="04" @selected(($prescription->counseling?->diverticulums ?? '') == '04')>5회</option>
                  <option value="05" @selected(($prescription->counseling?->diverticulums ?? '') == '05')>6회 이상</option>
                  <option value="06" @selected(($prescription->counseling?->diverticulums ?? '') == '06')>N/A</option>
                </select>
              </div>
            </div>
            {{-- 주소 (full) --}}
            <div class="rx-field-row">
              <span class="rx-field-label">주소</span>
              <div style="display:flex;flex-direction:column;gap:4px;flex:1;">
                <div style="display:flex;gap:6px;align-items:center;">
                  <input type="text" class="form-control" id="f-postcode" readonly value="{{ $prescription->postcode ?? '' }}"
                         placeholder="우편번호" style="width:110px;background:var(--bg-secondary,#f8f9fa);cursor:default;" />
                  <button type="button" class="btn btn-outline btn-sm" onclick="openAddressSearch('f-postcode','f-address','f-address-detail')" style="white-space:nowrap;flex-shrink:0;">
                    <i class="fa-solid fa-magnifying-glass"></i> 주소 검색
                  </button>
                  <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text-secondary);white-space:nowrap;cursor:pointer;margin:0;">
                    <input type="checkbox" id="sameShipping" checked onchange="syncShippingAddress(this.checked)" style="width:14px;height:14px;cursor:pointer;" />
                    배송 주소 동일
                  </label>
                </div>
                <div style="display:flex;gap:6px;">
                  <input type="text" class="form-control" id="f-address"
                         value="{{ $prescription->address_ocr ?? $prescription->patient?->address ?? '' }}"
                         placeholder="도로명 주소" readonly style="flex:1;background:var(--bg-secondary,#f8f9fa);cursor:default;" />
                  <input type="text" class="form-control" id="f-address-detail"
                         value="{{ $prescription->address_detail ?? '' }}"
                         placeholder="상세 주소" style="flex:1;" />
                </div>
              </div>
            </div>

          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 3: 병원 · 처방 정보 (기본 펼침, OCR)
        ───────────────────────────────────────────────── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-hospital" style="color:var(--info);"></i> 병원 · 처방 정보
            </span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">병원명 · 의사 · 처방일 · 유효기간</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            {{-- 3단: 병원명 | 요양병원코드 | 담당의사 --}}
            <div class="rx-grid-3" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">병원명 <span style="color:red;">*</span></span>
                <div class="field-group" style="flex:1;">
                  <input type="text" class="form-control has-ok" id="f-hospital" value="{{ $prescription->hospital_name }}" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">요양병원코드</span>
                <input type="text" class="form-control" id="f-hospital-code" value="{{ $prescription->counseling?->erp_cd9 ?? '' }}" placeholder="요양병원코드" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">담당의사</span>
                <input type="text" class="form-control" id="f-doctor" value="{{ $prescription->doctor_name ?? $prescription->counseling?->udf15 ?? '' }}" placeholder="의사 성명" style="flex:1;" />
              </div>
            </div>
            {{-- 4단: 처방전발행일 | 처방기간 | 종료일 | 진단확인일 --}}
            <div class="rx-grid-4" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">처방전발행일</span>
                <div class="field-group" style="flex:1;">
                  <input type="date" class="form-control has-ok" id="f-date" value="{{ $prescription->issued_date?->format('Y-m-d') ?? $prescription->counseling?->udf12 ?? '' }}" style="min-width:0;" onchange="calcNextRepurchase()" />
                  <span class="field-status"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i></span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">처방기간</span>
                <div style="display:flex;align-items:center;gap:4px;flex:1;">
                  <input type="number" class="form-control" id="f-rx-period" value="{{ $prescription->total_days ?? $prescription->counseling?->udf13 ?? '' }}" placeholder="일수" style="flex:1;min-width:0;" onchange="calcNextRepurchase()" />
                  <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">일</span>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">종료일</span>
                <input type="date" class="form-control" id="f-rx-end-date" value="{{ $prescription->counseling?->udf14 ?? '' }}" style="flex:1;min-width:0;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">진단확인일</span>
                <input type="date" class="form-control" id="f-diagnosis-date" value="{{ $prescription->counseling?->udf2 ?? '' }}" style="flex:1;min-width:0;" />
              </div>
            </div>
            {{-- 재구매일 (full) --}}
            <div class="rx-field-row">
              <span class="rx-field-label">재구매일</span>
              <div style="display:flex;align-items:center;gap:8px;flex:1;">
                <span id="disp-issued-date" style="font-size:12px;color:var(--text-muted);">{{ $prescription->issued_date?->format('Y-m-d') ?? '-' }}</span>
                <i class="fa-solid fa-arrow-right" style="font-size:10px;color:var(--text-muted);"></i>
                <span id="disp-renew-date" style="font-size:13px;font-weight:700;color:var(--primary);">{{ $prescription->repurchase_date?->format('Y-m-d') ?? '-' }}</span>
                <input type="hidden" id="f-repurchase-date" value="{{ $prescription->repurchase_date?->format('Y-m-d') ?? '' }}" />
              </div>
            </div>
          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 4: 처방 수량 · 상병 (기본 펼침, OCR)
        ───────────────────────────────────────────────── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span>
              <i class="fa-solid fa-clipboard-list" style="color:var(--purple);"></i> 처방 수량 · 상병
            </span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">상병명 · 수량 · 기간 · 구분</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            {{-- 상병명/코드 (full) --}}
            <div class="rx-field-row" style="margin-bottom:10px;">
              <span class="rx-field-label">상병명 / 코드</span>
              <div style="display:flex;gap:6px;flex:1;">
                <input type="text" class="form-control" id="f-disease" value="{{ $prescription->disease_name }}" placeholder="상병명" style="flex:2;" />
                <input type="text" class="form-control" id="f-disease-code" value="{{ $prescription->disease_code ?? $prescription->counseling?->udf5 ?? '' }}" placeholder="코드" style="flex:3;" />
              </div>
            </div>
            {{-- 3단: 상병구분 | 구분(SB/SCI) | 요류역학검사일 --}}
            <div class="rx-grid-3" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">상병 구분</span>
                <select class="form-control" id="f-disease-class" style="flex:1;">
                  <option value="">선택</option>
                  <option value="1"   @selected(($prescription->counseling?->udf3 ?? '') == '1')>1</option>
                  <option value="2-1" @selected(($prescription->counseling?->udf3 ?? '') == '2-1')>2-1</option>
                  <option value="2-2" @selected(($prescription->counseling?->udf3 ?? '') == '2-2')>2-2</option>
                  <option value="3"   @selected(($prescription->counseling?->udf3 ?? '') == '3')>3</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">구분(SB/SCI)</span>
                <select class="form-control" id="f-sb-sci" style="flex:1;">
                  <option value="">선택</option>
                  <option value="SB"  @selected(($prescription->counseling?->udf6 ?? '') == 'SB')>SB</option>
                  <option value="SCI" @selected(($prescription->counseling?->udf6 ?? '') == 'SCI')>SCI</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">요류역학검사일</span>
                <input type="date" class="form-control" id="f-uro-date" value="{{ $prescription->counseling?->udf7 ?? '' }}" style="flex:1;" />
              </div>
            </div>
            {{-- 3단: 1일처방개수 | 총처방기간 | 총계 --}}
            <div class="rx-grid-3">
              <div class="rx-field-row">
                <span class="rx-field-label">1일 처방개수</span>
                <input type="number" class="form-control" id="f-daily" value="{{ $prescription->daily_count ?? $prescription->counseling?->udf8 ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">처방기간(일)</span>
                <input type="number" class="form-control" id="f-days" value="{{ $prescription->total_days ?? $prescription->counseling?->udf9 ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">총계(개)</span>
                <input type="number" class="form-control" id="f-total" value="{{ $prescription->total_count ?? $prescription->counseling?->udf10 ?? '' }}" min="1" style="flex:1;" oninput="syncRxRef()" />
              </div>
            </div>
          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 5: 급여 · 보험 정보 (collapsed)
        ───────────────────────────────────────────────── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span><i class="fa-solid fa-shield-halved" style="color:var(--success);"></i> 급여 · 보험 정보</span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">급여구분 · 공단 · 위임동의</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            {{-- 3단: 급여구분 | 공단등록상태 | 2년후공단재등록 --}}
            <div class="rx-grid-3" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">급여 구분</span>
                <select class="form-control" id="f-benefit-class" style="flex:1;">
                  <option value="">선택</option>
                  <option value="일반"      @selected(($prescription->counseling?->udf11 ?? '') == '일반')>일반</option>
                  <option value="차상위경감" @selected(($prescription->counseling?->udf11 ?? '') == '차상위경감')>차상위경감</option>
                  <option value="기초"      @selected(($prescription->counseling?->udf11 ?? '') == '기초')>기초</option>
                  <option value="자동차보험" @selected(($prescription->counseling?->udf11 ?? '') == '자동차보험')>자동차보험</option>
                  <option value="산재"      @selected(($prescription->counseling?->udf11 ?? '') == '산재')>산재</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">공단등록 상태</span>
                <select class="form-control" id="f-nhis-status" style="flex:1;">
                  <option value="">선택</option>
                  <option value="진행중"   @selected(($prescription->counseling?->udf19 ?? '') == '진행중')>진행중</option>
                  <option value="완료"     @selected(($prescription->counseling?->udf19 ?? '') == '완료')>완료</option>
                  <option value="필요없음" @selected(($prescription->counseling?->udf19 ?? '') == '필요없음')>필요없음</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">2년후공단재등록</span>
                <input type="text" class="form-control" id="f-nhis-renew" value="{{ $prescription->counseling?->udf4 ?? '' }}" placeholder="날짜 또는 비고" style="flex:1;" />
              </div>
            </div>
            {{-- 2단: 위임동의 시작일 | 종료일 --}}
            <div class="rx-field-grid" style="margin-bottom:10px;">
              @php
                $agreeStart = ($prescription->counseling_data['udf42'] ?? null) ?: now()->format('Y-m-d');
                $agreeEnd   = ($prescription->counseling_data['udf43'] ?? null) ?: \Carbon\Carbon::parse($agreeStart)->addMonth()->format('Y-m-d');
              @endphp
              <div class="rx-field-row">
                <span class="rx-field-label">위임동의 시작일</span>
                <input type="date" class="form-control" id="f-nhis-agree-start"
                       value="{{ $agreeStart }}"
                       onchange="autoAgreeEnd(this.value)"
                       style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">위임동의 종료일</span>
                <input type="date" class="form-control" id="f-nhis-agree-end"
                       value="{{ $agreeEnd }}"
                       style="flex:1;" />
              </div>
            </div>

            {{-- 위임동의 현황 배지 --}}
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border-light);">
              <div id="consentStatusBadge" style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
                <i class="fa-solid fa-circle-info"></i> <span id="consentStatusText">동의 현황 없음</span>
              </div>
            </div>
          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 6: 신/재구매 정보 (collapsed)
        ───────────────────────────────────────────────── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span><i class="fa-solid fa-cart-shopping" style="color:var(--warning);"></i> 신/재구매 정보</span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">신/재구매 · 영수증 · 사유 · 담당</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            {{-- 4단: 신구매/재구매 | Five program | 소득공제 구분 | 현금영수증번호 --}}
            <div class="rx-grid-4" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">신/재구매</span>
                <select class="form-control" id="f-purchase-type" style="flex:1;">
                  <option value="">선택</option>
                  <option value="신구매" @selected(($prescription->counseling?->udf17 ?? '') == '신구매')>신구매</option>
                  <option value="재구매" @selected(($prescription->counseling?->udf17 ?? '') == '재구매')>재구매</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">Five program</span>
                <select class="form-control" id="f-five-program" style="flex:1;">
                  <option value="">선택</option>
                  <option value="00" @selected(($prescription->counseling?->five_program ?? '') == '00')>N/A</option>
                  <option value="05" @selected(($prescription->counseling?->five_program ?? '') == '05')>Five</option>
                  <option value="06" @selected(($prescription->counseling?->five_program ?? '') == '06')>Six</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">소득공제</span>
                <select class="form-control" id="f-deduction" style="flex:1;">
                  <option value="">선택</option>
                  <option value="소득공제" @selected(($prescription->counseling?->udf22 ?? '') == '소득공제')>소득공제</option>
                  <option value="지출증빙" @selected(($prescription->counseling?->udf22 ?? '') == '지출증빙')>지출증빙</option>
                  <option value="자진발급" @selected(($prescription->counseling?->udf22 ?? '') == '자진발급')>자진발급</option>
                </select>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">현금영수증번호</span>
                <input type="text" class="form-control" id="f-cash-receipt" value="{{ $prescription->counseling?->udf23 ?? '' }}" placeholder="010-XXX-XXXX" style="flex:1;" />
              </div>
            </div>
            {{-- 3단: 주문담당자 | 다음재구매가능일 | 입원/산재/보훈 --}}
            <div class="rx-grid-3" style="margin-bottom:10px;">
              <div class="rx-field-row">
                <span class="rx-field-label">주문 담당자</span>
                <input type="text" class="form-control" id="f-order-manager" value="{{ ($prescription->counseling_data['udf25'] ?? null) ?: auth()->user()->name }}" placeholder="담당자" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">다음재구매일</span>
                <div style="display:flex;gap:4px;flex:1;align-items:center;">
                  <input type="date" class="form-control" id="f-next-repurchase" value="{{ $prescription->counseling?->udf30 ?? '' }}" style="flex:1;" />
                  <button type="button" onclick="calcNextRepurchase(true)"
                          title="처방전발행일 + 처방기간(일) + 1일"
                          style="flex-shrink:0;height:36px;padding:0 8px;border:1px solid var(--primary);border-radius:var(--radius);background:var(--primary-light);color:var(--primary);font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;">
                    <i class="fa-solid fa-rotate"></i> 자동
                  </button>
                </div>
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">입원/산재/보훈</span>
                <select class="form-control" id="f-special-case" style="flex:1;">
                  <option value="">선택</option>
                  <option value="입원" @selected(($prescription->counseling?->udf18 ?? '') == '입원')>입원</option>
                  <option value="산재" @selected(($prescription->counseling?->udf18 ?? '') == '산재')>산재</option>
                  <option value="보훈" @selected(($prescription->counseling?->udf18 ?? '') == '보훈')>보훈</option>
                  <option value="출국" @selected(($prescription->counseling?->udf18 ?? '') == '출국')>출국</option>
                </select>
              </div>
            </div>
            {{-- 사유 (full) --}}
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
                    '취소-보훈(건보적용불가)','취소-통화연결실패','취소-이질감','취소-처방전  error(이중발행 등)',
                    '취소-미입금','취소-비용부담','취소-단순변심','취소-유치도뇨','취소-처방전 사용기간만료',
                    '취소-CKL제품 의료기구매','취소-출국','취소-사망',
                    '관리자 확인 -시스템 issue(판매 주문부터 시작/확정)',
                    '재고부족으로 발송지연','카카오구매-요양병원',
                  ] as $reason)
                  <option value="{{ $reason }}" @selected(($prescription->counseling?->udf20 ?? '') == $reason)>{{ $reason }}</option>
                  @endforeach
                </select>
            </div>
          </div>
        </div>

        {{-- ─────────────────────────────────────────────────
             아코디언 그룹 7: 추가 정보 (collapsed)
        ───────────────────────────────────────────────── --}}
        <div class="rx-acc-item">
          <div class="rx-acc-header" onclick="toggleAcc(this)">
            <span><i class="fa-solid fa-ellipsis" style="color:var(--text-muted);"></i> 추가 정보</span>
            <div class="rx-acc-meta">
              <span class="rx-acc-meta-hint">신환등록 · 기이카운트 병원 · Five</span>
              <div class="acc-inline-btns" onclick="event.stopPropagation()">
                <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
                <button class="btn btn-warning btn-sm" style="font-size:11px;padding:3px 8px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
                <button class="btn btn-success btn-sm" style="font-size:11px;padding:3px 8px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
              </div>
              <i class="fa-solid fa-chevron-down rx-acc-icon"></i>
            </div>
          </div>
          <div class="rx-acc-body" style="display:none;">
            {{-- 3단: 신환등록일 | Five(110days) | 추가정보 등록일 --}}
            <div class="rx-grid-3">
              <div class="rx-field-row">
                <span class="rx-field-label">신환master등록일</span>
                <input type="date" class="form-control" id="f-new-patient-date" value="{{ $prescription->counseling?->udf32 ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">Five(110days)</span>
                <input type="text" class="form-control" id="f-five" value="{{ $prescription->counseling?->five ?? '' }}" style="flex:1;" />
              </div>
              <div class="rx-field-row">
                <span class="rx-field-label">추가정보 등록일</span>
                <input type="text" class="form-control" id="f-add-reg-date" value="{{ $prescription->counseling?->reg_date ?? '' }}" readonly style="flex:1;background:var(--bg-secondary,#f8f9fa);" />
              </div>
            </div>
          </div>
        </div>

        {{-- 저장 / 승인 버튼 --}}
        <div style="display:flex;gap:6px;margin-top:14px;justify-content:flex-end;">
          <button class="btn btn-outline btn-sm" style="font-size:11px;padding:4px 10px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
          <button class="btn btn-warning btn-sm" style="font-size:11px;padding:4px 10px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
          <button class="btn btn-success btn-sm" style="font-size:11px;padding:4px 10px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
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
              <th>재상담일자</th><td data-from="f-re-counsel-date">{{ $prescription->counseling?->re_counsel_date ?? '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-user"></i> 환자 정보</td></tr>
            <tr>
              <th>환자명</th><td data-from="f-name">{{ $prescription->patient_name_ocr ?: '-' }}</td>
              <th>연락처</th><td data-from="f-mobile">{{ $prescription->mobile_ocr ?? $prescription->patient?->mobile ?? '-' }}</td>
            </tr>
            <tr>
              <th>보호자명</th><td data-from="f-guardian">{{ $prescription->counseling?->udf24 ?? '-' }}</td>
              <th>일일도뇨횟수</th><td data-from="f-diverticulums">-</td>
            </tr>
            <tr>
              <th>주소</th>
              <td colspan="3" id="tv-address">@php $fullAddrTv = trim(($prescription->address_ocr ?? $prescription->patient?->address ?? '') . ' ' . ($prescription->address_detail ?? '')); @endphp{{ $fullAddrTv ?: '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-hospital"></i> 병원 · 처방 정보</td></tr>
            <tr>
              <th>병원명</th><td data-from="f-hospital">{{ $prescription->hospital_name ?: '-' }}</td>
              <th>요양병원코드</th><td data-from="f-hospital-code">{{ $prescription->counseling?->erp_cd9 ?? '-' }}</td>
            </tr>
            <tr>
              <th>담당의사</th><td data-from="f-doctor">{{ $prescription->doctor_name ?? $prescription->counseling?->udf15 ?? '-' }}</td>
              <th>처방전발행일</th><td data-from="f-date">{{ $prescription->issued_date?->format('Y-m-d') ?? '-' }}</td>
            </tr>
            <tr>
              <th>처방기간</th><td data-from="f-rx-period">{{ ($prescription->total_days ?? '-') }}</td>
              <th>재구매일</th><td id="tv-renew-date">{{ $prescription->repurchase_date?->format('Y-m-d') ?? '-' }}</td>
            </tr>
            <tr class="tbl-sec"><td colspan="4"><i class="fa-solid fa-clipboard-list"></i> 처방 수량 · 상병</td></tr>
            <tr>
              <th>상병명</th><td data-from="f-disease">{{ $prescription->disease_name ?: '-' }}</td>
              <th>상병코드</th><td data-from="f-disease-code">{{ $prescription->disease_code ?? $prescription->counseling?->udf5 ?? '-' }}</td>
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
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end;">
          <button class="btn btn-outline btn-sm" style="font-size:11px;padding:4px 10px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
          <button class="btn btn-warning btn-sm" style="font-size:11px;padding:4px 10px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
          <button class="btn btn-success btn-sm" style="font-size:11px;padding:4px 10px;" onclick="approveRx()"><i class="fa-solid fa-circle-check"></i> 승인요청</button>
        </div>
      </div>{{-- /tv --}}

      </div>{{-- /tab-ocr --}}

      {{-- ── 미저장 경고 다이얼로그 ── --}}
      <div id="unsavedDlg" style="display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:380px;margin:16px;box-shadow:0 24px 64px rgba(0,0,0,.22);animation:modalIn .18s ease;overflow:hidden;">
          <div style="padding:24px 24px 0;display:flex;gap:14px;align-items:flex-start;">
            <div style="width:40px;height:40px;border-radius:10px;background:#FFF7ED;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-triangle-exclamation" style="color:#F59E0B;font-size:18px;"></i>
            </div>
            <div>
              <p style="font-size:15px;font-weight:700;color:#1E1B4B;margin:0 0 6px;">저장하지 않은 변경사항</p>
              <p style="font-size:13px;color:#6B7280;line-height:1.6;margin:0;">처방전 검수 탭에 저장되지 않은 내용이 있습니다.<br>탭을 이동하기 전에 저장하시겠습니까?</p>
            </div>
          </div>
          <div style="padding:20px 24px 24px;display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
            <button id="unsavedDlgCancel" style="padding:8px 16px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">취소</button>
            <button id="unsavedDlgDiscard" style="padding:8px 16px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;color:#6B7280;font-size:13px;font-weight:600;cursor:pointer;">저장 없이 이동</button>
            <button id="unsavedDlgSave" style="padding:8px 16px;border:none;border-radius:8px;background:#7367F0;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-floppy-disk"></i> 저장 후 이동</button>
          </div>
        </div>
      </div>

      {{-- Tab: Product --}}
      <div class="tab-pane" id="tab-product">

        {{-- 판매 유형 선택 (카드/테이블뷰 공통) --}}
        <div class="card mb-3" style="border-color:var(--primary);">
          <div class="card-body" style="padding:12px 16px;">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
              <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--text-primary);white-space:nowrap;">
                <i class="fa-solid fa-tag" style="color:var(--primary);"></i> 판매 유형
                <span style="color:var(--danger);font-size:11px;">*</span>
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <label class="so-type-opt">
                  <input type="radio" name="so_type_radio" value="1013" checked onchange="onSoTypeChange(this.value)">
                  <span><i class="fa-solid fa-hospital"></i> CE 판매</span>
                </label>
                <label class="so-type-opt">
                  <input type="radio" name="so_type_radio" value="1016" onchange="onSoTypeChange(this.value)">
                  <span><i class="fa-solid fa-user"></i> 개인판매</span>
                </label>
                <label class="so-type-opt">
                  <input type="radio" name="so_type_radio" value="1022" onchange="onSoTypeChange(this.value)">
                  <span><i class="fa-solid fa-gift"></i> 샘플판매</span>
                </label>
              </div>
              <div id="soTypeBadge" style="margin-left:auto;">
                <span class="badge badge-primary" style="font-size:11px;">1013 · CE 판매</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            {{-- 처방 제품 헤더 (카드/테이블뷰 공통) --}}
            <div class="section-title">
              <i class="fa-solid fa-boxes-stacked" style="color:var(--purple);"></i> 처방 제품 정보
              <div style="display:flex;align-items:center;gap:8px;margin-left:auto;">
                <span style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:500;color:var(--text-secondary);background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:2px 10px;">
                  <b style="color:var(--text-primary);">{{ $prescription->patient_name_ocr ?? '-' }}</b>
                  <span style="color:var(--border);">|</span>
                  <span>1일 <b id="rx-ref-daily">{{ $prescription->daily_count ?? '-' }}</b>개</span>
                  <span style="color:var(--border);">|</span>
                  <span>처방 <b id="rx-ref-days">{{ $prescription->total_days ?? '-' }}</b>일</span>
                  <span style="color:var(--border);">|</span>
                  <span>총 <b id="rx-ref-total">{{ $prescription->total_count ?? '-' }}</b>개</span>
                </span>
                <button type="button" class="btn btn-primary btn-sm" onclick="addItem()"
                        style="padding:2px 10px;font-size:11px;">
                  <i class="fa-solid fa-plus"></i> 제품 추가
                </button>
              </div>
            </div>

            {{-- 카드뷰 --}}
            <div class="cv">
              <div id="items-container">{{-- JS renderItems() --}}</div>
            </div>

            {{-- 테이블뷰 --}}
            <div class="tv">
              <div id="items-table-container"><div style="color:var(--text-muted);font-size:12px;text-align:center;padding:8px 0;">제품 없음</div></div>
            </div>

            {{-- 합계 / 버튼 (카드/테이블뷰 공통) --}}
            <div class="items-total-bar">
              <span><i class="fa-solid fa-circle-dollar-to-slot" style="color:var(--success);"></i>
                총 NHIS 급여: <b style="color:var(--success);" id="summary-nhis">₩ {{ number_format($calcNhis) }}</b>
              </span>
              <span style="margin-left:auto;">
                총 환자부담: <b id="summary-copay">₩ {{ number_format($calcCopay) }}</b>
              </span>
            </div>
            <div style="display:flex;gap:6px;margin-top:12px;justify-content:flex-end;">
              <button class="btn btn-outline btn-sm" style="font-size:11px;padding:4px 10px;" onclick="resetOCR()"><i class="fa-solid fa-rotate-left"></i> 원본 복원</button>
              <button class="btn btn-warning btn-sm" style="font-size:11px;padding:4px 10px;" onclick="saveOCR()"><i class="fa-solid fa-floppy-disk"></i> 저장</button>
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
                         placeholder="우편번호" style="width:110px;background:var(--bg-secondary,#f8f9fa);cursor:default;" />
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
                         placeholder="도로명 주소" readonly style="flex:1;background:var(--bg-secondary,#f8f9fa);cursor:default;" />
                  <input type="text" class="form-control" id="shippingAddrDetail"
                         placeholder="상세 주소" style="flex:1;" />
                </div>

              </div>
            </div>

            {{-- 주문 생성 / 수정·삭제 버튼 영역 --}}
            <div id="orderActionArea" style="margin-top:12px;">
              @if($prescription->order)
              {{-- 이미 주문 있음: 수정 + 삭제 --}}
              <div id="orderExistsInfo" style="background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);padding:10px 14px;margin-bottom:10px;font-size:12px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:15px;"></i>
                <div>
                  <b style="color:var(--success);">주문 생성 완료</b>
                  <span style="color:var(--text-muted);margin-left:8px;">{{ $prescription->order->order_number }}</span>
                  @if($prescription->order->withworks_so_no)
                    <span style="color:var(--primary);margin-left:6px;font-family:monospace;font-size:11px;">SO: {{ $prescription->order->withworks_so_no }}</span>
                  @endif
                </div>
              </div>
              <div style="display:flex;gap:8px;">
                <button class="btn btn-warning flex-1" id="btnUpdateOrder" onclick="updateOrder(event)">
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
                  <th style="font-weight:800;color:var(--primary);">환자 부담 합계</th>
                  <td style="font-weight:700;color:var(--primary);" id="tv-costTotal">₩ {{ number_format($calcCopay + 3000) }}</td>
                </tr>
                <tr class="tbl-sec"><td colspan="2"><i class="fa-solid fa-truck"></i> 배송 정보</td></tr>
                <tr><th>받는 사람</th><td id="tv-ship-recipient">{{ $prescription->order?->shipping_recipient ?? ($prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-') }}</td></tr>
                <tr><th>배송 주소</th><td id="tv-ship-addr">{{ trim(($prescription->order?->shipping_address ?? '') . ' ' . ($prescription->order?->shipping_address_detail ?? '')) ?: '-' }}</td></tr>
                @if($prescription->order)
                  <tr>
                    <th>주문번호</th>
                    <td><span style="color:var(--success);font-weight:700;">{{ $prescription->order->order_number }}</span>
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
                <button class="btn btn-warning flex-1" onclick="updateOrder(event)"><i class="fa-solid fa-pen-to-square"></i> 주문 수정</button>
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
              <i class="fa-solid fa-check ws-arrow" style="color:var(--success);"></i>
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']) ? 'done' : 'active' }}"><i class="fa-solid fa-eye"></i></div>
              <div><div class="ws-label">OCR 처리</div><div class="ws-time">{{ $prescription->updated_at->format('H:i') }} · 자동</div></div>
              @if(in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']))
                <i class="fa-solid fa-check ws-arrow" style="color:var(--success);"></i>
              @else
                <i class="fa-solid fa-spinner fa-spin ws-arrow" style="color:var(--primary);"></i>
              @endif
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ in_array($prescription->status, ['approved','ordered']) ? 'done' : ($prescription->status === 'review_needed' ? 'active' : 'pending') }}"><i class="fa-solid fa-clipboard-check"></i></div>
              <div><div class="ws-label">검수 확인</div><div class="ws-time">{{ $prescription->reviewed_at ? $prescription->reviewed_at->format('H:i').' · '.$prescription->reviewer?->name : '대기 중' }}</div></div>
              @if(in_array($prescription->status, ['approved','ordered']))
                <i class="fa-solid fa-check ws-arrow" style="color:var(--success);"></i>
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
                <i class="fa-solid fa-check ws-arrow" style="color:var(--success);"></i>
              @endif
            </div>
            <div class="workflow-step">
              <div class="ws-icon {{ $prescription->order?->nhis_claim_status === 'approved' ? 'done' : 'pending' }}"><i class="fa-solid fa-hospital"></i></div>
              <div><div class="ws-label">NHIS 청구</div><div class="ws-time">{{ $prescription->order?->nhis_reimbursement ? '환급: ₩'.number_format($prescription->order->nhis_reimbursement) : '대기 중' }}</div></div>
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
                  <td style="text-align:center;"><i class="fa-solid fa-check" style="color:var(--success);"></i></td>
                  <td>{{ $prescription->created_at->format('Y-m-d H:i') }} · {{ $prescription->upload_source === 'mobile' ? 'iOS 앱' : '웹' }}</td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-eye" style="color:var(--info);margin-right:5px;"></i>OCR 처리</td>
                  <td style="text-align:center;">
                    @if(in_array($prescription->status, ['ocr_done','review_needed','approved','ordered']))
                      <i class="fa-solid fa-check" style="color:var(--success);"></i>
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
                      <i class="fa-solid fa-check" style="color:var(--success);"></i>
                    @else
                      <i class="fa-solid fa-clock" style="color:var(--text-muted);"></i>
                    @endif
                  </td>
                  <td>{{ $prescription->reviewed_at ? $prescription->reviewed_at->format('Y-m-d H:i').' · '.($prescription->reviewer?->name ?? '-') : '대기 중' }}</td>
                </tr>
                <tr>
                  <td><i class="fa-solid fa-cart-shopping" style="color:var(--success);margin-right:5px;"></i>주문 생성</td>
                  <td style="text-align:center;">
                    @if($prescription->order)
                      <i class="fa-solid fa-check" style="color:var(--success);"></i>
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
                  <td><i class="fa-solid fa-hospital" style="color:var(--purple);margin-right:5px;"></i>NHIS 청구</td>
                  <td style="text-align:center;">
                    @if($prescription->order?->nhis_claim_status === 'approved')
                      <i class="fa-solid fa-check" style="color:var(--success);"></i>
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

{{-- 이전 상담 이력 조회 모달 --}}
@if($isReturningPatient)
<div class="modal-overlay" id="prevCounselModal" style="z-index:10000;" onclick="if(event.target===this)closePrevCounselModal()">
  <div class="modal-box" style="width:800px;max-width:96vw;height:82vh;display:flex;flex-direction:column;">
    <div class="modal-header">
      <i class="fa-solid fa-clock-rotate-left" style="color:#d97706;font-size:17px;"></i>
      <span class="modal-title">이전 상담 이력</span>
      <span style="font-size:11px;color:var(--text-muted);background:#fef3c7;border:1px solid #fde68a;border-radius:4px;padding:1px 8px;margin-left:4px;">
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
            $pcd    = $pc->counseling_data ?? [];
            $pcSt   = $pcd['status'] ?? '';
            $pcDate = $pcd['counsel_date'] ?? $pc->created_at->format('Y-m-d');
            $pcNo   = $pcd['counselling_no'] ?? '-';
          @endphp
          <div class="pc-list-item" data-idx="{{ $i }}" onclick="selectPrevCounsel({{ $i }})"
               style="padding:11px 14px;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .15s;">
            <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:3px;word-break:break-all;">{{ $pcNo }}</div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
              <span style="font-size:11px;color:var(--text-muted);">
                <i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $pcDate }}
              </span>
              @if($pcSt)
                <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;background:{{ $pcStatusColorMap[$pcSt] ?? '#ccc' }};color:#fff;flex-shrink:0;">
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
                    style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border:1px solid var(--primary);border-radius:6px;color:var(--primary);font-size:11px;font-weight:600;cursor:pointer;background:var(--bg-card);white-space:nowrap;flex-shrink:0;">
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



{{-- Attachment Delete Confirm Popover --}}
{{-- 팩스 PDF 뷰어 팝오버 --}}
<div id="faxPdfPopover" style="display:none;position:fixed;z-index:10100;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.22);flex-direction:column;overflow:hidden;width:min(820px,90vw);height:min(88vh,88vh);">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border);flex-shrink:0;gap:8px;">
    <div style="display:flex;align-items:center;gap:7px;">
      <i class="fa-regular fa-file-pdf" style="color:#e53e3e;font-size:15px;"></i>
      <span style="font-size:12px;font-weight:700;">팩스 전송 서류</span>
      <span style="font-size:11px;color:var(--text-muted);">— {{ $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '—' }}</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px;">
      <a id="faxPdfDownloadBtn" href="#" download
         style="padding:4px 10px;background:var(--primary);color:#fff;border-radius:var(--radius);font-size:11px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <i class="fa-solid fa-download" style="font-size:10px;"></i> 다운로드
      </a>
      <button onclick="closeFaxPdfModal()"
              style="background:none;border:none;font-size:18px;line-height:1;color:var(--text-muted);cursor:pointer;padding:2px 6px;">&times;</button>
    </div>
  </div>
  <iframe id="faxPdfFrame" src="" style="flex:1;width:100%;border:none;background:#525659;"></iframe>
</div>

<div id="deleteAttachPopover" style="display:none;position:fixed;width:220px;background:var(--bg-card);border:1px solid var(--danger);border-radius:var(--radius-lg);box-shadow:0 6px 20px rgba(0,0,0,.18);z-index:1000;padding:14px 16px;">
  <div style="font-size:12px;font-weight:700;color:var(--danger);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
    <i class="fa-solid fa-triangle-exclamation"></i> 삭제 확인
  </div>
  <div style="font-size:11px;color:var(--text-primary);margin-bottom:12px;">
    <span id="deleteAttachName" style="font-weight:600;word-break:break-all;"></span><br>
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
      <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:20px;"></i>
      <span class="modal-title">주문 연계 완료</span>
      <button class="modal-close" onclick="closeModal('orderModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="orderModalBody" style="text-align:center;padding:28px 20px;">
      <div style="font-size:52px;color:var(--success);margin-bottom:12px;">✅</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:6px;">주문 연계 완료</div>
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
      <i class="fa-solid fa-shield-halved" style="color:var(--success);font-size:20px;"></i>
      <span class="modal-title">처방전 승인요청</span>
      <button class="modal-close" onclick="closeModal('approveModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div style="background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);padding:14px;margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:var(--success);">✅ 검수 승인 요청</div>
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
      <button class="btn btn-success" id="btnConfirmApprove" onclick="confirmApprove(this)"><i class="fa-solid fa-circle-check"></i> 승인 확정</button>
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
      <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:var(--radius);padding:14px;">
        <div style="font-size:13px;color:#b91c1c;font-weight:600;" id="dangerConfirmMsg"></div>
        <div style="font-size:11px;color:#dc2626;margin-top:6px;">이 작업은 되돌릴 수 없습니다.</div>
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
<div id="crIssuePopover" style="display:none;position:fixed;width:340px;background:var(--bg-card);border:1px solid var(--success);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:601;">
  <div id="crIssuePopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
    <div style="width:10px;height:10px;background:var(--success);border:1px solid var(--success);transform:rotate(45deg);margin:3px auto 0;"></div>
  </div>
  <div style="background:var(--success);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-receipt" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">현금영수증 발행</span>
    <button onclick="closeCrIssuePopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px;display:block;">유형</label>
      <div style="display:flex;gap:16px;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
          <input type="radio" name="cr-type" value="income_deduction" checked style="accent-color:var(--success);"> 소득공제 (개인)
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
          <input type="radio" name="cr-type" value="business_expense" style="accent-color:var(--success);"> 지출증빙 (사업자)
        </label>
      </div>
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">휴대폰번호 또는 사업자번호 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="cr-identifier" class="form-control" style="font-size:12px;" placeholder="010-0000-0000" maxlength="13" inputmode="numeric" oninput="formatCrIdentifier(this)">
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;">금액 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="cr-amount" class="form-control" style="font-size:12px;" inputmode="numeric" placeholder="0" oninput="formatCrAmount(this)">
    </div>
    <div id="cr-no-order-notice" style="display:none;font-size:11px;color:#b91c1c;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:8px 10px;text-align:center;">
      <i class="fa-solid fa-circle-exclamation"></i> 주문을 먼저 생성한 후 현금영수증을 발행할 수 있습니다.
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button class="btn btn-outline btn-sm" onclick="closeCrIssuePopover()">취소</button>
      <button class="btn btn-success btn-sm" id="btnSubmitCashReceipt" onclick="submitCashReceipt()">
        <i class="fa-solid fa-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- 현금영수증 상세 팝오버 (고정 위치) --}}
<div id="crDetailPopover" style="display:none;position:fixed;width:300px;background:var(--bg-card);border:1px solid var(--success);border-radius:var(--radius-lg);box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:600;">
  <div id="crDetailPopoverArrow" style="position:absolute;top:-8px;left:24px;width:14px;height:8px;overflow:hidden;">
    <div style="width:10px;height:10px;background:var(--bg-card);border:1px solid var(--success);transform:rotate(45deg);margin:3px auto 0;"></div>
  </div>
  <div style="background:var(--success);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-receipt" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">현금영수증 상세</span>
    <button onclick="closeCrDetailPopover()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">×</button>
  </div>
  <div style="padding:14px;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <colgroup><col width="38%"><col width="62%"></colgroup>
      <tbody>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">승인번호</th>
          <td id="cr-d-no" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">유형</th>
          <td id="cr-d-type" style="padding:7px 0;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">식별번호</th>
          <td id="cr-d-identifier" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">거래금액</th>
          <td id="cr-d-amount" style="padding:7px 0;font-weight:700;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">발행일시</th>
          <td id="cr-d-issued-at" style="padding:7px 0;font-size:11px;"></td>
        </tr>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">주문번호</th>
          <td id="cr-d-order-no" style="padding:7px 0;font-family:monospace;font-size:11px;"></td>
        </tr>
        <tr>
          <th style="padding:7px 0;font-weight:600;color:var(--text-muted);text-align:left;">환자명</th>
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
  const _labelMap = { '처방전': 'prescription', '위임장': 'delegation', '주민등록증': 'id_card', '기타': 'other' };
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
  // fixed 상태라면 해제 후 위치 재측정
  const inner = document.getElementById('viewerInner');
  const outer = document.getElementById('viewerCol');
  if (inner && inner.style.position === 'fixed') {
    inner.style.position = inner.style.top = inner.style.left = inner.style.width = inner.style.zIndex = '';
    if (outer) outer.style.minHeight = '';
  }
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

  // ── 뷰어 열 JS sticky ────────────────────────────────
  // #viewerCol 은 그리드 흐름에 유지(공간 확보), #viewerInner 만 fixed 전환
  (function () {
    if (window.matchMedia('(max-width: 768px)').matches) return;
    const outer = document.getElementById('viewerCol');
    const inner = document.getElementById('viewerInner');
    if (!outer || !inner) return;

    const navEl = document.getElementById('layoutNavbar');

    function getViewerTop() {
      const patBar = document.getElementById('patient-info-bar');
      let bottom = navEl ? navEl.getBoundingClientRect().bottom : 60;
      if (patBar && patBar.classList.contains('info-bar-pinned')) {
        bottom = patBar.getBoundingClientRect().bottom;
      }
      return bottom + 8;
    }

    let naturalTop  = null;
    let naturalLeft = null;
    let innerW      = null;
    let innerH      = null;
    let isFixed     = false;

    function measure() {
      const rect  = outer.getBoundingClientRect();
      naturalTop  = rect.top + window.scrollY;
      naturalLeft = rect.left;
      innerW      = outer.offsetWidth;
      innerH      = inner.offsetHeight;
    }

    function fix() {
      const top = getViewerTop();
      outer.style.minHeight = innerH + 'px';
      inner.style.position  = 'fixed';
      inner.style.top       = top + 'px';
      inner.style.left      = naturalLeft + 'px';
      inner.style.width     = innerW + 'px';
      inner.style.zIndex    = '40';
      isFixed = true;
    }

    function unfix() {
      inner.style.position = inner.style.top = inner.style.left =
      inner.style.width    = inner.style.zIndex = '';
      outer.style.minHeight = '';
      isFixed = false;
    }

    function onScroll() {
      if (naturalTop === null) measure();
      const top       = getViewerTop();
      const shouldFix = window.scrollY > naturalTop - top;
      if (shouldFix && !isFixed)       { measure(); fix(); }
      else if (!shouldFix && isFixed)  unfix();
      else if (isFixed) inner.style.top = getViewerTop() + 'px';
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', () => {
      naturalTop = null;
      if (isFixed) unfix();
    });
  })();

  // ── 탭바 sticky — bar를 body로 reparent해서 transform/overflow 우회 ──
  (function () {
    if (window.matchMedia('(max-width: 768px)').matches) return;
    const bar = document.getElementById('tabBarInner');
    if (!bar) return;
    const barParent = bar.parentNode;   // #tabBarOuter

    // 현재 고정된 헤더들의 bottom 합산 (navbar + 환자 정보 바)
    function getTop() {
      const navEl  = document.getElementById('layoutNavbar');
      const patBar = document.getElementById('patient-info-bar');
      let bottom = navEl ? navEl.getBoundingClientRect().bottom : 60;
      if (patBar && patBar.classList.contains('info-bar-pinned')) {
        bottom = patBar.getBoundingClientRect().bottom;
      }
      return bottom + 4;
    }

    // bar가 body로 빠질 때 공간 확보용 placeholder
    const ph = document.createElement('div');
    ph.style.display = 'none';
    barParent.insertBefore(ph, bar);

    let absTop = null, barLeft = 0, barW = 0, barH = 0;
    let isFixed = false;

    function measure() {
      const r = bar.getBoundingClientRect();
      absTop  = r.top + window.scrollY;
      barLeft = r.left;
      barW    = bar.offsetWidth;
      barH    = bar.offsetHeight;
      ph.style.height = barH + 'px';
    }

    function fix() {
      measure();
      const top = getTop();
      const bg  = getComputedStyle(document.body).backgroundColor;
      ph.style.display = 'block';
      document.body.appendChild(bar);
      bar.style.cssText =
        `position:fixed;top:${top}px;left:${barLeft}px;width:${barW}px;` +
        `z-index:200;background:${bg};box-shadow:0 2px 8px rgba(0,0,0,.14);margin-bottom:0;`;
      isFixed = true;
    }

    function unfix() {
      barParent.insertBefore(bar, ph);
      bar.style.cssText = '';
      ph.style.display = 'none';
      absTop  = null;
      isFixed = false;
    }

    function onScroll() {
      if (absTop === null) measure();
      const top       = getTop();
      const shouldFix = window.scrollY > absTop - top;
      if (shouldFix && !isFixed)      fix();
      else if (!shouldFix && isFixed) unfix();
      // 이미 고정 중이면 top 값 갱신 (환자 바 pin/unpin 반응)
      else if (isFixed) bar.style.top = getTop() + 'px';
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', () => { if (isFixed) unfix(); absTop = null; });
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
  if (btn) {
    btn.style.background   = isTable ? 'var(--primary-light)' : 'var(--bg)';
    btn.style.borderColor  = isTable ? 'var(--primary)'       : 'var(--border)';
    btn.style.color        = isTable ? 'var(--primary)'       : 'var(--text-secondary)';
  }
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
                   style="font-size:12px;min-width:0;flex:1;height:30px;padding:2px 7px;" autocomplete="off"
                   placeholder="제품명 또는 코드 입력..."
                   value="${displayName}"
                   oninput="pacInput(${idx},this.value)"
                   onkeydown="pacKey(event,${idx})"
                   onfocus="if(this.value.trim())pacInput(${idx},this.value)"
                   onblur="pacBlur(${idx})" />
            <button type="button" class="btn btn-primary btn-sm" title="제품 검색"
                    style="flex-shrink:0;padding:0 8px;height:30px;"
                    onmousedown="event.preventDefault()"
                    onclick="pacSearchBtn(${idx})">
              <i class="fa-solid fa-magnifying-glass" style="font-size:11px;"></i>
            </button>
          </div>
          <div class="pac-drop" id="pac-drop-${idx}"></div>
        </div>
        <input type="hidden" class="item-name"  value="${escHtml(item.product_name||'')}" />
        <input type="hidden" class="item-code"  value="${escHtml(item.product_code||'')}" />
        <input type="hidden" class="item-rbox"  value="${escHtml(item.r_box||'')}" />
        <input type="hidden" class="item-stock" value="${escHtml(String(item.stock||''))}" />
        <input type="hidden" class="item-price" value="${escHtml(fmtPrice(item.product_price))}" />
      </td>
      <td>
        <select class="form-control form-select item-nhis item-nhis-sel"
                style="font-size:11px;padding:2px 4px;height:30px;width:100%;"
                onchange="calcItem(${idx})">${nhisOpts(nhisSt)}</select>
      </td>
      <td>
        <input type="text" inputmode="numeric" class="form-control item-ins-price"
               style="font-size:12px;text-align:right;padding:2px 7px;height:30px;width:100%;"
               value="${fmtPrice(item.insurance_price)}" placeholder="₩"
               oninput="calcItem(${idx})" />
      </td>
      <td>
        <input type="number" class="form-control item-qty"
               style="font-size:12px;text-align:center;padding:2px 4px;height:30px;width:100%;"
               value="${item.quantity||1}" min="1"
               oninput="calcItem(${idx})" />
      </td>
      <td style="text-align:right;color:var(--success);white-space:nowrap;" class="item-nhis-amt">₩ ${nhisAmt}</td>
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
      <th style="text-align:right;color:var(--success);background:var(--bg);">₩${nhisTotal.toLocaleString('ko-KR')}</th>
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
    body: '처방전 검수 → 처방 제품 → 주문 연계 → 이력 순서로 진행합니다. 각 탭을 클릭해 이동하세요.'
  },
  {
    selector: '.tab-btn:nth-child(1)',
    title: '처방전 검수 탭',
    body: 'OCR이 자동 추출한 환자·병원·제품 정보를 확인하고 수정합니다. 완료 후 <b>검수 완료</b> 버튼을 클릭하세요.'
  },
  {
    selector: '.tab-btn:nth-child(2)',
    title: '처방 제품 탭',
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
  const VA_ISSUE_URL_TPL = '/settlement/orders/__ID__/virtual-account';
  const SMS_SEND_URL = @json(route('prescriptions.smsSend', $prescription));

  // ── 판매 유형 ────────────────────────────────────────
  const SO_TYPE_LABELS = { '1013': 'CE 판매', '1016': '개인판매', '1022': '샘플판매' };
  let currentSoType = '1013';

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

  // ── 주민등록번호 마스킹/표시 토글 ────────────────────────────
  function maskResidentNo(val) {
    if (!val) return '';
    const v = val.replace(/\s+/g, '');
    const m = v.match(/^(\d{6})-?(\d)/);
    return m ? m[1] + '-' + m[2] + '••••••' : val;
  }

  let _residentVisible = false;
  function toggleResidentNo() {
    const inp    = document.getElementById('f-resident');
    const real   = document.getElementById('f-resident-real').value;
    const icon   = document.getElementById('icon-resident-toggle');
    _residentVisible = !_residentVisible;
    if (_residentVisible) {
      inp.value    = real;
      inp.readOnly = false;
      inp.style.background = '';
      inp.style.cursor     = '';
      icon.className = 'fa-solid fa-lock-open';
      inp.addEventListener('input', function () {
        document.getElementById('f-resident-real').value = inp.value;
      }, { once: false });
    } else {
      document.getElementById('f-resident-real').value = inp.value;
      inp.value    = maskResidentNo(inp.value);
      inp.readOnly = true;
      inp.style.background = 'var(--bg-secondary,#f8f9fa)';
      inp.style.cursor     = 'default';
      icon.className = 'fa-solid fa-lock';
    }
  }

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
    el.style.background = isRecounsel ? '' : 'var(--bg-secondary,#f8f9fa)';
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
  function toggleAcc(header) {
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
    const allOpen = Array.from(items).every(el => el.classList.contains('is-open'));
    items.forEach(el => {
      const body = el.querySelector('.rx-acc-body');
      const icon = el.querySelector('.rx-acc-icon');
      body.style.display = allOpen ? 'none' : 'block';
      if (icon) icon.classList.toggle('open', !allOpen);
      el.classList.toggle('is-open', !allOpen);
    });
    syncToggleAllBtn();
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
    if (_productDirty) parts.push('처방 제품');
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
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    if (tabId === 'tab-order')   { recalcAllItems(); renderOrderSummary(); }
    if (tabId === 'tab-product') {
      if (document.getElementById('tabsCol')?.classList.contains('tab-view-table')) {
        renderItemsTable();
      } else {
        renderItems();
      }
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
      showUnsavedDlg(btn, tabId, '처방 제품', saveOCR);
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
    return `<div class="item-card" data-idx="${idx}">
      <div class="item-row">
        <div class="item-inline-field" style="flex:1;min-width:0;">
          <div class="item-field-label">제품명</div>
          <div class="pac-wrap" style="position:relative;">
            <input type="text" class="form-control item-display" id="pac-input-${idx}"
                   style="width:100%;font-size:12px;height:34px;" autocomplete="off"
                   placeholder="제품명 또는 코드 입력..."
                   value="${displayName}"
                   oninput="pacInput(${idx},this.value)"
                   onkeydown="pacKey(event,${idx})"
                   onfocus="if(this.value.trim())pacInput(${idx},this.value)"
                   onblur="pacBlur(${idx})" />
            <div class="pac-drop" id="pac-drop-${idx}"></div>
          </div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">&nbsp;</div>
          <button type="button" class="btn btn-primary btn-sm" title="제품 검색"
                  style="flex-shrink:0;padding:0 10px;height:34px;"
                  onmousedown="event.preventDefault()"
                  onclick="pacSearchBtn(${idx})">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>
        <input type="hidden" class="item-name"  value="${escHtml(item.product_name||'')}" />
        <input type="hidden" class="item-code"  value="${escHtml(item.product_code||'')}" />
        <input type="hidden" class="item-rbox"  value="${escHtml(item.r_box||'')}" />
        <input type="hidden" class="item-stock" value="${escHtml(String(item.stock||''))}" />
        <div class="item-inline-field">
          <div class="item-field-label">급여구분</div>
          <select class="form-control form-select item-nhis item-nhis-sel"
                  onchange="calcItem(${idx})">${nhisOpts}</select>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">수량</div>
          <input type="number" class="form-control item-qty" value="${item.quantity||1}" min="1"
                 oninput="calcItem(${idx})" style="font-size:12px;width:60px;text-align:center;height:34px;" />
        </div>
        <div class="item-inline-field" id="item-rbox-field-${idx}" style="display:${item.r_box?'flex':'none'};">
          <div class="item-field-label">R-Box</div>
          <div class="item-rbox-display" style="height:34px;display:flex;align-items:center;font-size:12px;font-weight:700;color:#7C3AED;white-space:nowrap;">${escHtml(item.r_box||'')}</div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">소비자가</div>
          <input type="text" inputmode="numeric" class="form-control item-price" value="${fmtPrice(item.product_price)}"
                 placeholder="₩" oninput="calcItem(${idx})" style="font-size:12px;width:88px;text-align:right;height:34px;" />
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">보험가</div>
          <input type="text" inputmode="numeric" class="form-control item-ins-price" value="${fmtPrice(item.insurance_price)}"
                 placeholder="₩" oninput="calcItem(${idx})" style="font-size:12px;width:88px;text-align:right;height:34px;" />
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">총금액</div>
          <div class="item-total-amt" style="font-size:12px;font-weight:700;color:var(--primary);height:34px;display:flex;align-items:center;white-space:nowrap;min-width:80px;">₩ ${totalAmt}</div>
        </div>
        <div class="item-inline-field">
          <div class="item-field-label">&nbsp;</div>
          <button type="button" class="btn btn-sm" onclick="removeItem(${idx})"
                  style="flex-shrink:0;padding:0 8px;height:34px;background:none;border:1px solid var(--danger);color:var(--danger);" title="삭제">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>
      <div class="item-meta" id="item-meta-${idx}" style="display:${item.stock?'flex':'none'};align-items:center;gap:6px;padding:4px 2px 2px;flex-wrap:wrap;">
        ${item.stock  ? `<span style="background:var(--success-light);color:var(--success);padding:1px 8px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fa-solid fa-layer-group" style="font-size:9px;margin-right:3px;"></i>재고: ${Number(item.stock).toLocaleString()}</span>` : ''}
      </div>
      <div class="item-summary">
        <span style="color:var(--text-muted);font-size:11px;">NHIS 급여:</span>
        <b style="color:var(--success);" class="item-nhis-amt">₩ ${nhisAmt}</b>
        <span style="margin-left:auto;color:var(--text-muted);font-size:11px;">환자부담:</span>
        <b class="item-copay">₩ ${copay}</b>
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
      el.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:8px 0;">처방 제품 탭에서 제품을 먼저 선택해주세요.</div>';
      return;
    }
    el.innerHTML = validItems.map(item => {
      const base     = (item.insurance_price || item.product_price || 0);
      const total    = Math.round(base * item.quantity).toLocaleString('ko-KR');
      const nhisAmt  = Math.round(item.nhis_amount   || 0);
      const copay    = Math.round(item.patient_copay || 0);
      const nhisSt   = item.nhis_status || 'eligible';
      const nhisLabel = nhisSt === 'eligible' ? '급여(90%)' : (nhisSt === 'partial' ? '일부(50%)' : '비급여');
      const nhisColor = nhisSt === 'ineligible' ? 'var(--text-muted)' : 'var(--success)';
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
      resident_no_ocr:  strOrNull('f-resident-real'),
      mobile_ocr:       strOrNull('f-mobile'),
      address_ocr:      strOrNull('f-address'),
      postcode:         strOrNull('f-postcode'),
      address_detail:   strOrNull('f-address-detail'),
      guardian:         strOrNull('f-guardian'),
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
    document.getElementById('f-resident-real').value = @json($prescription->resident_no_ocr ?? $prescription->patient?->resident_no ?? '');
    document.getElementById('f-resident').value      = @json($prescription->masked_resident_no_ocr ?? ($prescription->patient?->masked_resident_no ?? ''));
    document.getElementById('f-resident').readOnly   = true;
    document.getElementById('f-resident').style.background = 'var(--bg-secondary,#f8f9fa)';
    document.getElementById('f-resident').style.cursor = 'default';
    document.getElementById('icon-resident-toggle').className = 'fa-solid fa-lock';
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

    const shippingAddress = (() => {
      const base   = document.getElementById('shippingAddr')?.value?.trim() || '';
      const detail = document.getElementById('shippingAddrDetail')?.value?.trim() || '';
      return base ? (detail ? base + ' ' + detail : base) : null;
    })();

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
        shipping_address:  shippingAddress,
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
      ? `<span style="display:inline-block;background:var(--success-light,#e6f4ea);color:var(--success);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">연계 완료</span>`
      : `<span style="display:inline-block;background:#fff3e0;color:var(--warning);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">연계 미완료</span>`;

    document.getElementById('orderModalBody').innerHTML = `
      <div style="font-size:52px;color:var(--success);margin-bottom:12px;">✅</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:4px;">주문 생성 완료</div>
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
        content.innerHTML = `<span style="font-family:monospace;font-weight:800;color:var(--primary);font-size:11px;">${soNo}</span><span class="badge badge-${tl[1]}" style="font-size:9px;margin-left:4px;">${tl[0]}</span>`;
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
      chk.style.color = 'var(--success)';
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
      chk2.style.color = 'var(--success)';
      histStep.appendChild(chk2);
    }
  }

  /** 주문 생성 후 가상계좌 버튼 동적 주입 */
  function injectVaButton(orderId) {
    const wrap = document.getElementById('vaButtonWrap');
    if (!wrap || wrap.querySelector('#btnVaTrigger, #vaResultBadge')) return;
    const vaUrl  = VA_ISSUE_URL_TPL.replace('__ID__', orderId);
    wrap.innerHTML = `
      <div id="vaNotIssuedWrap" style="position:relative;">
        <button type="button" id="btnVaTrigger"
                data-url="${vaUrl}"
                data-sms-url="${SMS_SEND_URL}"
                onclick="toggleVaPopover(event)"
                style="padding:5px 11px;background:#0ea5e9;color:#fff;border:none;font-weight:700;font-size:11px;display:flex;align-items:center;gap:4px;border-radius:var(--radius);white-space:nowrap;cursor:pointer;">
          <i class="fa-solid fa-building-columns" style="font-size:11px;"></i> 가상계좌 발급
        </button>
        <div id="vaResultBadge" style="display:none;align-items:center;gap:4px;padding:4px 9px;background:var(--warning-light);border:1px solid #fcd34d;border-radius:var(--radius);font-size:11px;white-space:nowrap;">
          <i class="fa-solid fa-building-columns" style="color:var(--warning);font-size:10px;"></i>
          <span id="vaResultBadgeText" style="font-weight:700;color:var(--warning);">-</span>
        </div>
      </div>`;
  }

  /** 주문 생성 후 버튼 영역을 수정/삭제 형태로 교체 */
  function switchToEditDeleteButtons(orderNum, soNo) {
    const area = document.getElementById('orderActionArea');
    if (!area) return;
    area.innerHTML = `
      <div style="background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);padding:10px 14px;margin-bottom:10px;font-size:12px;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:15px;"></i>
        <div>
          <b style="color:var(--success);">주문 생성 완료</b>
          <span style="color:var(--text-muted);margin-left:8px;">${orderNum}</span>
          ${soNo ? `<span style="color:var(--primary);margin-left:6px;font-family:monospace;font-size:11px;">SO: ${soNo}</span>` : ''}
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-warning flex-1" id="btnUpdateOrder" onclick="updateOrder(event)">
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

    const shippingAddress = (() => {
      const base   = document.getElementById('shippingAddr')?.value?.trim() || '';
      const detail = document.getElementById('shippingAddrDetail')?.value?.trim() || '';
      return base ? (detail ? base + ' ' + detail : base) : null;
    })();

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
        shipping_address: shippingAddress,
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
    ['kakaoPopover','smsPopover','faxPopover','vaPopover','crDetailPopover','consentPopover','consentSignPopover','crIssuePopover','taxInvoicePopover'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
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
    btn.style.background = 'var(--success-light)';
    btn.style.color      = 'var(--success)';
    btn.style.border     = '1px solid #86efac';
    btn.querySelector('svg')?.setAttribute('fill', 'var(--success)');
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
    btn.style.background = 'var(--success-light)';
    btn.style.color      = 'var(--success)';
    btn.style.border     = '1px solid #86efac';
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
      item.style.background  = checked ? 'rgba(27,102,245,.06)' : '';
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
      const activeBtn = document.querySelector('.fax-recipient-btn[data-recipient-type="nhis"]');
      if (activeBtn && activeBtn.style.background.includes('var(--primary-light)')) {
        document.getElementById('nhisSearchPanel').style.display = 'block';
        renderNhisOffices('');
      }
      refreshFaxSentBanner();
    }
  }

  function closeFaxPopover() {
    document.getElementById('faxPopover').style.display = 'none';
  }

  function reopenFaxPopover(e) {
    e.stopPropagation();
    document.getElementById('faxResultBadge').style.display = 'none';
    document.getElementById('faxTriggerWrap').style.display = 'block';
    closeAllPopovers();
    document.getElementById('faxPopover').style.display = 'block';
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
          <span style="font-size:9px;font-weight:700;color:var(--primary);background:var(--primary-light);border-radius:3px;padding:1px 5px;flex-shrink:0;">${o.region}</span>
          <span style="font-size:11px;font-weight:600;color:var(--text);">${o.name}</span>
        </div>
        <span style="font-size:10px;font-family:monospace;color:var(--text-muted);flex-shrink:0;">${o.fax}</span>
      </button>
    `).join('');
  }

  function selectNhisOffice(fax, name) {
    document.getElementById('fax-no').value = fax;
    document.getElementById('nhisSearchPanel').style.display = 'none';
    // update the NHIS button label to show selected branch
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
      'fax-doc-auth':         { value: 'authorization',    label: '위임장' },
      'fax-doc-rx':           { value: 'prescription',     label: '처방전' },
      'fax-doc-purchase':     { value: 'purchase_history', label: '제품 구매내역' },
      'fax-doc-cash-receipt': { value: 'cash_receipt',     label: '현금영수증' },
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
      ? `<div style="margin-top:6px;padding:6px 10px;background:#fffbeb;border:1px solid #fcd34d;border-radius:4px;font-size:11px;color:#b45309;">
           ⚠ 위임장: 환자 서명 없음 — 처방 정보로 자동 생성된 문서가 전송됩니다.
         </div>`
      : (data.auth_info ? `<div style="margin-top:6px;padding:6px 10px;background:#f0fdf4;border:1px solid #86efac;border-radius:4px;font-size:11px;color:#166534;">
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
            <div style="width:40px;height:40px;border-radius:50%;background:#f0fdf4;border:2px solid #86efac;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📠</div>
            <div>
              <div style="font-size:15px;font-weight:700;">팩스 전송 완료</div>
              <div style="font-size:11px;color:var(--text-muted);">요청이 정상적으로 접수되었습니다.</div>
            </div>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:14px;font-size:12px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;gap:10px;">
              <span style="color:var(--text-muted);width:60px;flex-shrink:0;">수신처</span>
              <span style="font-weight:600;">${data.recipient} <span style="color:var(--text-muted);font-weight:400;">(${data.fax_no})</span></span>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <span style="color:var(--text-muted);width:60px;flex-shrink:0;">전송 서류</span>
              <div style="display:flex;flex-wrap:wrap;gap:4px;">
                ${docLabels.map(l => `<span style="padding:2px 8px;background:var(--primary-light);color:var(--primary);border-radius:3px;font-size:11px;font-weight:600;">${l}</span>`).join('')}
              </div>
            </div>
            ${authNote}
            ${receiptLine}
          </div>

          <a href="${pdfUrl}" target="_blank"
             style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;
                    padding:10px;background:#1e293b;color:#fff;border-radius:var(--radius);
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
  function switchInfoTab(tab) {
    const tabs   = ['uploader', 'ocr'];
    tabs.forEach(t => {
      const btn   = document.getElementById('infoTab-' + t);
      const panel = document.getElementById('infoPanel-' + t);
      const active = t === tab;
      if (btn) {
        btn.style.borderBottomColor = active ? 'var(--primary)' : 'transparent';
        btn.style.color             = active ? 'var(--primary)' : 'var(--text-muted)';
      }
      if (panel) panel.style.display = active ? '' : 'none';
    });
  }

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

        if (triggerBtn) { triggerBtn.disabled = true; triggerBtn.style.background = '#64748b'; triggerBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> 발급 완료'; }
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

  // ── NHIS 청구 ─────────────────────────────────────────
  async function submitNhis() {
    @if($prescription->order)
    showToast('📡 NHIS 청구 데이터를 송신하고 있습니다...', 'info');
    const res = await apiRequest('/orders/{{ $prescription->order->id }}/nhis', 'POST');
    setTimeout(() => showToast(res.success ? '✅ NHIS 청구 송신 완료!' : res.message, res.success ? 'success' : 'danger'), 1500);
    @endif
  }

  // ── OCR 재분석 ────────────────────────────────────────
  async function reanalyzeOCR() {
    const btn = document.getElementById('btn-reanalyze');
    BtnState.loading(btn, '분석 중...');

    const res = await apiRequest(`/prescriptions/${RX_NUMBER}/reanalyze`, 'POST');

    if (res.success) {
      const f = res.fields;
      // 폼 필드 업데이트
      document.getElementById('f-name').value     = f.patient_name_ocr || '';
      document.getElementById('f-resident-real').value = f.resident_no_ocr || '';
      document.getElementById('f-resident').value      = maskResidentNo(f.resident_no_ocr || '');
      document.getElementById('f-resident').readOnly   = true;
      document.getElementById('f-resident').style.background = 'var(--bg-secondary,#f8f9fa)';
      document.getElementById('icon-resident-toggle').className = 'fa-solid fa-lock';
      document.getElementById('f-mobile').value   = f.mobile_ocr       || '';
      document.getElementById('f-postcode').value       = f.postcode       || '';
      document.getElementById('f-address').value        = f.address_ocr   || '';
      document.getElementById('f-address-detail').value = f.address_detail || '';
      document.getElementById('f-hospital').value = f.hospital_name    || '';
      document.getElementById('f-doctor').value   = f.doctor_name      || '';
      document.getElementById('f-date').value     = f.issued_date      || '';
      document.getElementById('f-disease').value  = f.disease_name     || '';
      document.getElementById('f-disease-code').value = f.disease_code || '';
      document.getElementById('f-daily').value    = f.daily_count      || '';
      document.getElementById('f-days').value     = f.total_days       || '';
      document.getElementById('f-total').value    = f.total_count      || '';
      calcRenewDate();

      // 신뢰도 바 업데이트
      const conf   = res.confidence;
      const color  = conf >= 90 ? 'var(--success)' : (conf >= 70 ? 'var(--warning)' : 'var(--danger)');
      const bar    = document.getElementById('conf-bar');
      const label  = document.getElementById('conf-label');
      const empty  = document.getElementById('conf-empty');
      if (bar)   { bar.style.width = conf + '%'; bar.style.background = color; }
      if (label) { label.textContent = conf + '%'; label.style.color = color; }
      if (empty) { empty.style.display = 'none'; }
      if (!bar) {
        // 신뢰도 바가 없었던 경우(처음 분석) — 재로드로 UI 전체 갱신
        location.reload();
        return;
      }

      showToast(`처방전 OCR 재분석 완료 (신뢰도 ${conf}%)`, 'success');
    }

    BtnState.reset(btn);
  }

  // ── 네비게이션 ────────────────────────────────────────
  const PREV_RX = @json($prevId);   // 더 최근 처방전 rx_number
  const NEXT_RX = @json($nextId);   // 더 오래된 처방전 rx_number

  function prevRecord() {
    if (!PREV_RX) { showToast('첫 번째 처방전입니다.', 'info'); return; }
    location.href = `${BASE_URL}/prescriptions/${encodeURIComponent(PREV_RX)}`;
  }
  function nextRecord() {
    if (!NEXT_RX) { showToast('마지막 처방전입니다.', 'info'); return; }
    location.href = `${BASE_URL}/prescriptions/${encodeURIComponent(NEXT_RX)}`;
  }

  // 이전/다음 없을 때 버튼 비활성화
  document.addEventListener('DOMContentLoaded', () => {
    if (!PREV_RX) document.querySelectorAll('[onclick="prevRecord()"]').forEach(b => b.disabled = true);
    if (!NEXT_RX) document.querySelectorAll('[onclick="nextRecord()"]').forEach(b => b.disabled = true);
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

  // ── 이전 상담 이력 모달 ──────────────────────────────
  @if($isReturningPatient)
  const _PREV_COUNSEL_LIST = @json($prevCounselingsData);
  const _RX_URL_BASE = @json(rtrim(url('/prescriptions'), '/'));

  const _PC_TYPE_MAP   = {'1013':'구매(CE)','1016':'개인구매','1020':'반품','1030':'문의','1050':'기타'};
  const _PC_ACC_MAP    = {'20':'처방외','10':'처방전-원외','30':'처방전-원내'};
  const _PC_STAT_MAP   = {'02':'등록','50':'재상담','95':'확정','99':'취소'};
  const _PC_STAT_COLOR = {'02':'var(--info)','50':'var(--warning)','95':'var(--success)','99':'var(--danger)'};
  const _PC_DIVER_MAP  = {'01':'1회 미만','02':'1~2회','03':'3~4회','04':'5회','05':'6회 이상','06':'N/A'};

  function openPrevCounselModal() {
    document.getElementById('prevCounselModal').classList.add('show');
    // 첫 번째 항목 자동 선택
    if (_PREV_COUNSEL_LIST.length) selectPrevCounsel(0);
  }
  function closePrevCounselModal() {
    document.getElementById('prevCounselModal').classList.remove('show');
  }

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
    <div style="margin-top:10px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;">
      <div style="font-size:10px;font-weight:700;color:#92400e;margin-bottom:5px;"><i class="fa-solid fa-note-sticky"></i> 상담 메모</div>
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
              ${item.nhis_status ? `<span style="font-size:10px;padding:1px 7px;border-radius:10px;background:${item.nhis_status==='Y'?'var(--success-light)':'var(--bg)'};color:${item.nhis_status==='Y'?'var(--success)':'var(--text-muted)'};border:1px solid ${item.nhis_status==='Y'?'#86efac':'var(--border)'};">${nhisMap[item.nhis_status]??item.nhis_status}</span>` : ''}
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
    const _PC_CONSENT_COLOR = {'agreed':'var(--success)','pending':'var(--warning)','declined':'var(--danger)','expired':'var(--text-muted)'};
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
                  background:${isDone?'var(--success-light)':isExp?'#fef2f2':'#fffbeb'};
                  color:${isDone?'var(--success)':isExp?'var(--danger)':'var(--warning)'};">
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
    const _FAX_COLOR = {0:'var(--text-muted)',1:'var(--info)',2:'var(--success)',3:'var(--danger)',4:'var(--text-muted)'};
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
      ? `<i class="fa-solid fa-file-prescription" style="font-size:9px;"></i> ${_pcEsc(d.rx_number)}${d.rx_status_label ? ` <span style="padding:0 5px;background:var(--bg);border:1px solid var(--border);border-radius:10px;">${_pcEsc(d.rx_status_label)}</span>` : ''}`
      : '';
    document.getElementById('pcStickyBtn').onclick = () => window.location.href = `${_RX_URL_BASE}/${_pcEsc(d.rx_number??'')}`;
    stickyHeader.style.display = 'block';

    // ── 스크롤 바디 ────────────────────────────────────
    document.getElementById('prevCounselBody').innerHTML = `
      ${_pcAcc('clipboard-list','var(--primary)',   '상담 정보',        counselBody,  true)}
      ${_pcAcc('user',          'var(--success)',   '환자 정보',        patientBody,  true)}
      ${_pcAcc('hospital',      'var(--info)',      '병원 · 처방 정보', hospitalBody, false)}
      ${_pcAcc('box',           'var(--warning)',   '제품 구매 이력',   purchaseBody, true)}
      ${_pcAcc('file-signature','#8b5cf6',          '위임동의',         consentBody,  false)}
      ${_pcAcc('university',    'var(--info)',      '가상계좌',         vaBody,       false)}
      ${_pcAcc('receipt',       'var(--success)',   '현금영수증',       crBody,       false)}
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
      if (data.signature_data) {
        document.getElementById('csignImg').src = data.signature_data;
        imgWrap.style.display = 'block';
        noSig.style.display   = 'none';
      } else {
        imgWrap.style.display = 'none';
        noSig.style.display   = 'block';
      }

      // PDF 다운로드 버튼
      const pdfBtn = document.getElementById('csignPdfBtn');
      if (data.pdf_url) {
        pdfBtn.href = data.pdf_url;
        pdfBtn.style.display = 'inline-flex';
      } else {
        pdfBtn.style.display = 'none';
      }

      loading.style.display = 'none';
      content.style.display = 'block';
    } catch (_) {
      loading.style.display = 'none';
      errEl.style.display   = 'block';
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
    const name    = (document.getElementById('consentPatientName')?.value ?? '').trim() || '환자';
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
    if (!mobile) { alert('수신 번호를 입력해주세요.'); return; }

    const btn = document.getElementById('btnConsentSend');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

    try {
      const res  = await fetch(CONSENT_SMS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ mobile }),
      });
      const data = await res.json();
      const box  = document.getElementById('consentSendResult');
      box.style.display = 'block';

      if (data.success) {
        box.style.background = 'var(--success-light)';
        box.style.color      = 'var(--success)';
        box.style.border     = '1px solid #86efac';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지`;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        updateConsentStatus();
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid #fca5a5';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      const box = document.getElementById('consentSendResult');
      box.style.display = 'block';
      box.style.background = 'var(--danger-light)';
      box.style.color = 'var(--danger)';
      box.style.border = '1px solid #fca5a5';
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
      agreed:  { bg:'var(--success-light)', border:'#86efac', color:'var(--success)', icon:'fa-circle-check',  text:'위임동의 완료',  action:'openConsentSignModal()', btnLabel:'서명확인', btnBorder:'var(--success)', btnColor:'var(--success)' },
      declined:{ bg:'var(--danger-light)',  border:'#fca5a5', color:'var(--danger)',  icon:'fa-circle-xmark',  text:'동의 거절됨',    action:'openConsentModal()',    btnLabel:'재발송',   btnBorder:'var(--danger)',  btnColor:'var(--danger)' },
      pending: { bg:'#ede9fe',              border:'#a5b4fc', color:'#6366f1',        icon:'fa-clock',          text:'위임동의 대기중', action:'openConsentModal()',    btnLabel:'재발송',   btnBorder:'#6366f1',        btnColor:'#6366f1' },
      expired: { bg:'var(--bg)',            border:'var(--border)', color:'var(--text-muted)', icon:'fa-ban',   text:'위임동의 만료',  action:'openConsentModal(true)',btnLabel:'재발송',   btnBorder:'var(--text-muted)', btnColor:'var(--text-muted)' },
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
    rb.innerHTML = `<i class="fa-solid ${cfg.icon}" style="color:${cfg.color};font-size:10px;"></i><span style="font-weight:700;color:${cfg.color};margin-left:2px;">${cfg.text}</span><button onclick="event.stopPropagation();${cfg.action}" style="height:16px;padding:0 5px;font-size:9px;background:none;border:1px solid ${cfg.btnBorder};color:${cfg.btnColor};border-radius:3px;cursor:pointer;margin-left:4px;">${cfg.btnLabel}</button>`;
  }

  async function updateConsentStatus() {
    try {
      const res  = await fetch(CONSENT_STATUS_URL, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
      const data = await res.json();
      if (!data.exists) return;

      const textEl  = document.getElementById('consentStatusText');
      const badgeEl = document.getElementById('consentStatusBadge');
      const colorMap = { agreed: 'var(--success)', declined: 'var(--danger)', pending: 'var(--warning)', expired: 'var(--text-muted)' };
      const iconMap  = { agreed: 'fa-circle-check', declined: 'fa-circle-xmark', pending: 'fa-clock', expired: 'fa-ban' };
      const color = colorMap[data.status] ?? 'var(--text-muted)';
      const icon  = iconMap[data.status]  ?? 'fa-circle-info';

      badgeEl.style.color = color;
      let label = `위임동의 ${data.status_label}`;
      if (data.status === 'pending' && data.remaining_min > 0) label += ` (${data.remaining_min}분 남음)`;
      if (data.responded_at) label += ` · ${data.responded_at}`;
      textEl.innerHTML = `<i class="fa-solid ${icon}"></i> ${label}`;

      // 버튼도 동기화
      _applyConsentBtn(data.status);

      // 서명 이미지 배지 표시
      if (data.status === 'agreed' && data.has_signature) {
        const badgeArea = document.getElementById('consentStatusBadge');
        if (badgeArea && !badgeArea.querySelector('.sign-badge')) {
          const sb = document.createElement('span');
          sb.className = 'sign-badge';
          sb.style.cssText = 'display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--success-light);color:var(--success);border:1px solid #86efac;margin-left:6px;';
          sb.innerHTML = '<i class="fa-solid fa-signature"></i> 서명 있음';
          badgeArea.appendChild(sb);
        }
      }
    } catch (_) {}
  }

  // 페이지 로드 시 동의 현황 즉시 확인
  updateConsentStatus();

  // ── 위임동의 실시간 결과 수신 (Pusher → layout에서 발송) ──
  window.addEventListener('ce:consentResult', function (e) {
    const data = e.detail;
    if (!data || data.rx_number !== @json($prescription->rx_number)) return;

    // 버튼 즉시 업데이트
    _applyConsentBtn(data.status);

    // 아코디언 내 현황 배지 업데이트
    const textEl  = document.getElementById('consentStatusText');
    const badgeEl = document.getElementById('consentStatusBadge');
    if (textEl && badgeEl) {
      const isAgreed = data.status === 'agreed';
      badgeEl.style.color = isAgreed ? 'var(--success)' : 'var(--danger)';
      let label = `위임동의 ${isAgreed ? '서명 완료' : '거절됨'}`;
      if (data.responded_at) label += ` · ${data.responded_at}`;
      textEl.innerHTML = `<i class="fa-solid fa-${isAgreed ? 'circle-check' : 'circle-xmark'}"></i> ${label}`;

      if (isAgreed && data.has_signature && !badgeEl.querySelector('.sign-badge')) {
        const sb = document.createElement('span');
        sb.className = 'sign-badge';
        sb.style.cssText = 'display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;background:var(--success-light);color:var(--success);border:1px solid #86efac;margin-left:6px;';
        sb.innerHTML = '<i class="fa-solid fa-signature"></i> 서명 있음';
        badgeEl.appendChild(sb);
      }
    }
  });

  // ── 제품 자동완성 (Todoworks API) ─────────────────────
  const _pacTimers = {};   // debounce timers per idx
  const _pacCache  = {};   // 검색 결과 캐시

  function pacInput(idx, val) {
    const drop = document.getElementById(`pac-drop-${idx}`);
    if (!drop) return;
    // "제품명 (코드)" 형태에서 "(코드)" 접미사 제거 후 검색
    const kw = val.replace(/\s*\([^)]*\)\s*$/, '').trim();
    if (kw.length < 1) { pacClose(idx); return; }
    clearTimeout(_pacTimers[idx]);
    drop.innerHTML = '<div class="pac-status"><i class="fa-solid fa-spinner fa-spin"></i> 검색 중...</div>';
    drop.classList.add('open');
    _pacTimers[idx] = setTimeout(() => pacFetch(idx, kw), 300);
  }

  async function pacFetch(idx, keyword) {
    const drop = document.getElementById(`pac-drop-${idx}`);
    if (!drop) return;
    const cacheKey = keyword.toLowerCase();
    let data;
    if (_pacCache[cacheKey]) {
      data = _pacCache[cacheKey];
    } else {
      try {
        const res = await apiRequest(`/products/search?q=${encodeURIComponent(keyword)}`, 'GET');
        if (!res.success || !res.data?.length) {
          drop.innerHTML = '<div class="pac-status">검색 결과가 없습니다.</div>';
          return;
        }
        data = res.data;
        _pacCache[cacheKey] = data;
      } catch {
        drop.innerHTML = '<div class="pac-status">조회 오류가 발생했습니다.</div>';
        return;
      }
    }
    pacRender(idx, data);
  }

  function pacRender(idx, data) {
    const drop = document.getElementById(`pac-drop-${idx}`);
    if (!drop) return;
    drop.innerHTML = data.map((item) => {
      const qty        = item.stock ?? null;
      const hasStock   = qty !== null;
      const stockColor = hasStock ? (qty > 0 ? 'var(--success)' : 'var(--danger)') : 'var(--text-muted)';
      const stockBg    = hasStock ? (qty > 0 ? 'var(--success-light)' : 'var(--danger-light)') : 'var(--bg)';
      const stockBdr   = hasStock ? stockColor : 'var(--border)';
      const stockTxt   = hasStock ? `재고: <b>${Number(qty).toLocaleString()}</b>` : '재고: -';
      return `
      <div class="pac-item"
           data-code="${escHtml(item.code ?? '')}"
           data-name="${escHtml(item.name ?? '')}"
           data-price="${item.price ?? 0}"
           data-rbox="${escHtml(item.r_box ?? '')}"
           data-stock="${qty ?? ''}"
           onmousedown="pacSelect(event,${idx},this)">
        <div class="pac-item-icon"><i class="fa-solid fa-box"></i></div>
        <div class="pac-item-body">
          <div class="pac-item-name">${escHtml(item.name)}</div>
          <div class="pac-item-meta">
            ${item.code ? `<span style="background:var(--primary-light);color:var(--primary);padding:1px 5px;border-radius:3px;font-size:10px;">${escHtml(item.code)}</span>` : ''}
            ${item.spec ? `<span>${escHtml(item.spec)}</span>` : ''}
            ${item.unit ? `<span>· ${escHtml(item.unit)}</span>` : ''}
            ${item.r_box ? `<span style="background:#F3EEFF;color:#7C3AED;padding:1px 5px;border-radius:3px;">R-Box: ${escHtml(item.r_box)}</span>` : ''}
            <span style="background:${stockBg};border:1px solid ${stockBdr};color:${stockColor};padding:1px 5px;border-radius:3px;">${stockTxt}</span>
          </div>
        </div>
        ${item.price ? `<div class="pac-item-price">₩ ${Number(item.price).toLocaleString()}</div>` : ''}
      </div>`;
    }).join('');
    drop.classList.add('open');
  }

  function pacSelect(e, idx, el) {
    e.preventDefault();
    const code  = el.dataset.code  || '';
    const name  = el.dataset.name  || '';
    const price = parseFloat(el.dataset.price) || 0;
    const rbox  = el.dataset.rbox  || '';

    // 재고: data-stock(조회 완료) → 뱃지 텍스트 파싱 → 빈값
    let stock = el.dataset.stock || '';
    if (!stock) {
      const stockBadge = el.querySelector('[id^="pac-stock-"]');
      const m = (stockBadge?.textContent ?? '').match(/재고:\s*([\d,]+)/);
      if (m) stock = m[1].replace(/,/g, '');
    }

    const card = document.querySelector(`.item-card[data-idx="${idx}"]`);
    if (card) {
      card.querySelector('.item-name').value    = name;
      card.querySelector('.item-code').value    = code;
      card.querySelector('.item-display').value = name + (code ? ` (${code})` : '');
      card.querySelector('.item-rbox').value    = rbox;
      card.querySelector('.item-stock').value   = stock;
      if (price) {
        card.querySelector('.item-price').value     = fmtPrice(price);
        card.querySelector('.item-ins-price').value = fmtPrice(price);
      }
      updateItemMeta(idx, rbox, stock);
      calcItem(idx);

      // 재고 미로딩 상태로 선택된 경우 → 선택 후 별도 조회하여 카드에 반영
      if (!stock && code) {
        apiRequest(`/products/stock?code=${encodeURIComponent(code)}`, 'GET')
          .then(res => {
            if (res.success && res.qty !== null) {
              const qty = String(res.qty);
              card.querySelector('.item-stock').value = qty;
              updateItemMeta(idx, rbox, qty);
            }
          }).catch(() => {});
      }
    }
    pacClose(idx);
    showToast(`"${name}" 선택됨`, 'success');
  }

  function pacClose(idx) {
    const drop = document.getElementById(`pac-drop-${idx}`);
    if (drop) drop.classList.remove('open');
  }

  function pacBlur(idx) {
    setTimeout(() => pacClose(idx), 180);
  }

  let _pacActiveIdx = {}; // 키보드 활성 인덱스
  function pacKey(e, idx) {
    const drop  = document.getElementById(`pac-drop-${idx}`);
    if (!drop || !drop.classList.contains('open')) return;
    const items = drop.querySelectorAll('.pac-item');
    if (!items.length) return;
    let cur = _pacActiveIdx[idx] ?? -1;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      cur = Math.min(cur + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      cur = Math.max(cur - 1, 0);
    } else if (e.key === 'Enter' && cur >= 0) {
      e.preventDefault();
      pacSelect(e, idx, items[cur]);
      return;
    } else if (e.key === 'Escape') {
      pacClose(idx);
      return;
    } else { return; }
    _pacActiveIdx[idx] = cur;
    items.forEach((el, i) => el.classList.toggle('ac-active', i === cur));
    items[cur]?.scrollIntoView({ block: 'nearest' });
  }

  // 검색 버튼 클릭 → 현재 입력값으로 즉시 검색
  function pacSearchBtn(idx) {
    const inp = document.getElementById(`pac-input-${idx}`);
    if (!inp) return;
    inp.focus();
    const kw = inp.value.trim();
    if (!kw) { showToast('검색어를 입력해주세요.', 'warning'); return; }
    clearTimeout(_pacTimers[idx]);
    const drop = document.getElementById(`pac-drop-${idx}`);
    if (drop) {
      drop.innerHTML = '<div class="pac-status"><i class="fa-solid fa-spinner fa-spin"></i> 검색 중...</div>';
      drop.classList.add('open');
    }
    pacFetch(idx, kw);
  }

  // 구 팝업 호환
  function openProductSearch(idx) { pacSearchBtn(idx); }

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
        const color = qty > 0 ? 'var(--success)' : 'var(--danger)';
        badge.style.background    = qty > 0 ? 'var(--success-light)' : 'var(--danger-light)';
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
    meta.innerHTML = stock ? `<span style="background:var(--success-light);color:var(--success);padding:1px 8px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fa-solid fa-layer-group" style="font-size:9px;margin-right:3px;"></i>재고: ${Number(stock).toLocaleString()}</span>` : '';
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
        <div style="background:var(--success-light);border:1px solid #86efac;border-radius:var(--radius);padding:8px 10px;font-size:11px;">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
            <span style="font-weight:700;color:var(--success);flex:1;">현금영수증 발행완료</span>
            <button onclick="toggleCrDetailPopover(event)" style="height:20px;padding:0 7px;font-size:10px;background:none;border:1px solid var(--success);color:var(--success);border-radius:4px;cursor:pointer;">상세</button>
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
    document.getElementById('ti-biz-no').value   = @json($prescription->order?->tax_invoice_biz_no   ?? '');
    document.getElementById('ti-email').value    = @json($prescription->order?->tax_invoice_email    ?? '');
    document.getElementById('ti-supply').value   = supply ? supply.toLocaleString('ko-KR') : '';
    document.getElementById('ti-vat').value      = vat    ? vat.toLocaleString('ko-KR')    : '';

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

  async function submitTaxInvoice() {
    if (!_ORDER_ID) { showToast('주문 생성 후 발행 가능합니다.', 'danger'); return; }
    const btn     = document.getElementById('btnSubmitTaxInvoice');
    const bizName = document.getElementById('ti-biz-name').value.trim();
    const ceoName = document.getElementById('ti-ceo-name').value.trim();
    const bizNo   = document.getElementById('ti-biz-no').value.trim();
    const supply  = document.getElementById('ti-supply').value.replace(/,/g, '');
    const vat     = document.getElementById('ti-vat').value.replace(/,/g, '');
    if (!bizName) { showToast('공급받는자 상호를 입력하세요.', 'danger'); return; }
    if (!ceoName) { showToast('대표자명을 입력하세요.', 'danger'); return; }
    if (!bizNo)   { showToast('사업자등록번호를 입력하세요.', 'danger'); return; }
    if (!supply)  { showToast('공급가액을 입력하세요.', 'danger'); return; }

    BtnState.loading(btn, '발행 중...');
    const res = await apiRequest(`/orders/${_ORDER_ID}/tax-invoice`, 'POST', {
      tax_invoice_type:     document.getElementById('ti-type').value,
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
      badge.style.cssText = 'font-size:10px;border-radius:3px;padding:1px 6px;background:#f0fdf4;color:#166534;border:1px solid #86efac;';
      desc.textContent    = crNo ? `승인번호: ${crNo}` : '발행완료';
    } else {
      label.style.cursor  = 'default';
      label.style.opacity = '0.5';
      chk.disabled        = true;
      chk.checked         = false;
      badge.textContent   = '미발행';
      badge.style.cssText = 'font-size:10px;border-radius:3px;padding:1px 6px;background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;';
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
           style="margin:0 8px 6px;padding:9px 10px;background:#FAFAF5;border:1px solid #E8E4D0;border-radius:7px;position:relative;">
        <div style="display:flex;align-items:flex-start;gap:6px;">
          <div draggable="true" ondragstart="memoDragStart(event,${m.id})"
               title="드래그해서 화면에 고정"
               style="flex-shrink:0;cursor:grab;padding:3px 5px;border-radius:4px;color:#bbb;font-size:13px;margin-top:0px;user-select:none;transition:background .15s,color .15s;"
               onmouseover="this.style.background='#eee';this.style.color='#7C3AED'"
               onmouseout="this.style.background='transparent';this.style.color='#bbb'">
            <i class="fa-solid fa-grip-vertical"></i>
          </div>
          <textarea class="memo-ta" data-id="${m.id}"
                    style="flex:1;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:42px;"
                    oninput="autoResizeTa(this)" onblur="updateMemoContent(${m.id},this.value)">${escHtmlMemo(m.content)}</textarea>
          <div style="display:flex;flex-direction:column;gap:3px;flex-shrink:0;">
            <button onclick="togglePin(${m.id})" title="${m.is_pinned ? '고정 해제' : '화면 고정'}"
                    style="width:22px;height:22px;border:none;border-radius:4px;cursor:pointer;background:${m.is_pinned ? '#7C3AED' : '#f1f1f1'};color:${m.is_pinned ? '#fff' : '#888'};font-size:11px;display:flex;align-items:center;justify-content:center;">
              <i class="fa-solid fa-thumbtack"></i>
            </button>
            <button onclick="deleteMemo(${m.id})" title="삭제"
                    style="width:22px;height:22px;border:none;border-radius:4px;cursor:pointer;background:#fff0f0;color:var(--danger);font-size:11px;display:flex;align-items:center;justify-content:center;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>
        <div style="font-size:10px;color:#aaa;margin-top:4px;padding-left:17px;">${m.created_at} · ${escHtmlMemo(m.user_name)}</div>
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
    if (!confirm('메모를 삭제하시겠습니까?')) return;
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
    el.style.cssText = `position:fixed;left:${x}px;top:${y}px;width:${w}px;min-width:180px;min-height:90px;z-index:9000;background:#FFFDE7;border:1px solid #F0D060;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);display:flex;flex-direction:column;`;
    el.innerHTML = `
      <div class="pm-header" style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:#F9C800;border-radius:8px 8px 0 0;cursor:move;user-select:none;flex-shrink:0;">
        <span style="font-size:10px;font-weight:700;color:#555;"><i class="fa-solid fa-thumbtack"></i> 메모 고정
          <span style="font-size:9px;font-weight:400;margin-left:4px;opacity:.7;">${escHtmlMemo(m.rx_number ?? '')}</span>
        </span>
        <div style="display:flex;gap:4px;">
          <button onclick="unpinWidget(${m.id})" title="고정 해제"
                  style="width:18px;height:18px;border:none;border-radius:3px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:#555;">
            <i class="fa-solid fa-thumbtack" style="transform:rotate(45deg);"></i>
          </button>
          <button onclick="closeWidget(${m.id})" title="닫기 (고정 유지)"
                  style="width:18px;height:18px;border:none;border-radius:3px;background:rgba(0,0,0,.1);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:#555;">×</button>
        </div>
      </div>
      <div style="padding:8px;flex:1;display:flex;flex-direction:column;min-height:0;">
        <textarea class="pinned-memo-ta" data-id="${m.id}"
                  style="flex:1;width:100%;border:none;background:transparent;resize:none;font-size:12px;line-height:1.5;outline:none;min-height:48px;"
                  onblur="updateMemoContent(${m.id},this.value)">${escHtmlMemo(m.content)}</textarea>
        <div style="font-size:10px;color:#aaa;margin-top:2px;flex-shrink:0;">${m.created_at} · ${escHtmlMemo(m.user_name)}</div>
      </div>
      <div class="pm-resize" title="크기 조절"
           style="position:absolute;right:0;bottom:0;width:16px;height:16px;cursor:se-resize;display:flex;align-items:flex-end;justify-content:flex-end;padding:2px;">
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
          <path d="M2 8L8 2M5 8L8 5M8 8L8 8" stroke="#bbb" stroke-width="1.4" stroke-linecap="round"/>
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

  // ── 환자 정보 바 스크롤 고정 ───────────────────────────
  (function () {
    const bar = document.getElementById('patient-info-bar');
    const ph  = document.getElementById('patient-info-bar-ph');
    if (!bar) return;
    window.addEventListener('scroll', function () {
      const shouldPin = window.scrollY > 10;
      if (shouldPin && !bar.classList.contains('info-bar-pinned')) {
        const h = bar.offsetHeight;
        bar.classList.add('info-bar-pinned');
        if (ph) { ph.style.height = h + 'px'; ph.style.display = 'block'; }
      } else if (!shouldPin && bar.classList.contains('info-bar-pinned')) {
        bar.classList.remove('info-bar-pinned');
        if (ph) ph.style.display = 'none';
      }
    }, { passive: true });
  })();
</script>
@endpush
