{{-- resources/views/fax/index.blade.php --}}
@extends('layouts.app')

@section('title', '팩스 발송')
@section('page-title', '팩스 발송')
@section('breadcrumb', '홈 / 팩스 발송')

@section('help-title', '팩스 발송 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>팝빌 API를 통해 PDF·이미지 파일을 팩스로 전송하고, 전송 내역을 조회하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">지원 파일 형식</div>
  <div class="help-item"><div class="help-item-icon"><i class="bx bx-file"></i></div><div class="help-item-text">PDF, TIFF, JPG, PNG, GIF — 건당 최대 10MB</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">전송 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">대기</span>
    <span class="badge badge-info">전송중</span>
    <span class="badge badge-success">성공</span>
    <span class="badge badge-danger">실패</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">유의사항</div>
  <div class="help-item"><div class="help-item-icon warn"><i class="bx bx-error"></i></div><div class="help-item-text">발신번호는 팝빌 포털에서 사전 등록·인증 후 사용 가능합니다.</div></div>
  <div class="help-item"><div class="help-item-icon warn"><i class="bx bx-error"></i></div><div class="help-item-text">테스트 환경에서는 실제 팩스가 발송되지 않습니다.</div></div>
</div>
@endsection

@push('styles')
<style>
  /* ── 레이아웃 ──
     .page-body 가 flex column · gap 16 이라 최상위 블록 사이 여백은 gap 이 만든다.
     탭(전송 내역 / 팩스 발송)이 두 판을 번갈아 보여 준다. */
  .fax-pane { display:flex; flex-direction:column; gap:12px; }

  /* 탭바 — Figma 탭 규격: h44 · pad 0/16 · gap 16 · 하단 1px border · 활성 밑줄 1px primary */
  .titab-bar { display:flex; gap:16px; padding:0 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .titab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
    color:var(--gray-600); border-bottom:1px solid transparent; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
  .titab:hover { color:var(--primary); }
  .titab.active { color:var(--primary); border-bottom-color:var(--primary); }

  /* 결과바 '선택 N건' — 전역(layouts/app.blade.php)에 .ds-grid-sel 이 없어 화면에서 정의한다.
     patients/index.blade.php 도 같은 이유로 같은 값을 갖고 있다. 전역으로 올려야 한다. */

  /* 동기화 문구(#sync-status) — 전역 .ds-grid-bar-right 는 flex-shrink:0 이고
     .content-wrapper 는 overflow-x:clip 이라(가로 스크롤이 안 생긴다) 문구가 길어지면
     '엑셀 저장' 버튼이 화면 밖으로 밀려 눌리지 않는다.
     '동기화 완료: 총 N건 중 N건 갱신, 오류 N건' 이 약 300px 이므로 2열 폭(298px)으로 묶고
     넘치면 말줄임한다. 전문은 syncPending() 이 띄우는 토스트가 그대로 보여 준다.
     비어 있을 때는 결과바 gap 6px 만 남으므로 숨긴다. */
  #sync-status { max-width:298px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  #sync-status:empty { display:none; }

  /* ── 요약 카드 ── */
  .fax-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
  @media(max-width:700px){ .fax-summary { grid-template-columns:1fr 1fr; } }
  .sum-card {
    background:var(--gray-0); border-radius:12px;
    padding:12px 16px; display:flex; align-items:center; gap:12px;
  }
  .sum-card .sc-icon {
    width:32px; height:32px; border-radius:8px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:16px;
  }
  .sum-card .sc-label { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .sum-card .sc-val   { font-size:16px; font-weight:700; line-height:26px; color:var(--gray-800); }
  /* 클래스 이름(blue/green/gray)은 개발 코드가 쓰던 그대로 두고 색만 DS 토큰으로 옮겼다.
     시안에 초록이 없어 green 도 primary 계열을 쓴다. */
  .sum-card.blue  .sc-icon { background:var(--primary-50);  color:var(--primary-500); }
  .sum-card.green .sc-icon { background:var(--primary-100); color:var(--primary-600); }
  .sum-card.gray  .sc-icon { background:var(--gray-100);    color:var(--gray-500); }

  /* ── 발송 폼 카드 ── */
  .send-card { background:var(--gray-0); border-radius:12px; overflow:hidden; }
  .send-card-head {
    padding:12px 16px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:8px;
    font-size:14px; font-weight:700; line-height:22px; color:var(--text-primary);
  }
  .send-card-head i { font-size:16px; color:var(--primary); }
  .send-card-body { padding:16px; display:flex; flex-direction:column; gap:12px; }

  .form-row { display:flex; flex-direction:column; gap:8px; }
  /* 입력 — Figma: h32 · r8 · pad 5/12 · 13/400 · lh20 · bd 1px gray-200 (.form-control 과 같은 규격).
     addReceiver() 가 만드는 행이 .form-input 을 쓰므로 클래스명은 그대로 둔다. */
  .form-input {
    width:100%; height:32px; padding:5px 12px;
    border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px;
    color:var(--text-primary); background:var(--gray-0);
    font-family:inherit; transition:var(--transition);
  }
  .form-input::placeholder { color:var(--gray-500); }
  .form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(40,121,139,.12); }
  .form-input.error { border-color:var(--danger); }
  select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238B95A1' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; }

  /* 수신자 섹션 */
  .receivers-box { border:1px solid var(--gray-200); border-radius:8px; overflow:hidden; }
  .receiver-row {
    display:grid; grid-template-columns:1fr auto;
    gap:8px; padding:8px 12px; border-bottom:1px solid var(--gray-200);
    align-items:center;
  }
  .receiver-inputs { display:flex; flex-direction:column; gap:6px; }
  .receiver-row:last-child { border-bottom:none; }
  .receiver-add-btn {
    align-self:flex-start;
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    height:32px; padding:5px 12px; border-radius:8px;
    font-size:13px; font-weight:500; line-height:20px; color:var(--primary);
    background:var(--primary-light); border:1px solid var(--primary-200); cursor:pointer;
    transition:var(--transition);
  }
  .receiver-add-btn:hover { background:var(--primary-100); }
  .receiver-del-btn {
    width:28px; height:28px; border-radius:8px; border:none;
    background:var(--danger-light); color:var(--danger); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:16px;
    transition:var(--transition); flex-shrink:0;
  }
  .receiver-del-btn:hover { background:var(--alert-100); }

  /* 파일 드롭존 */
  .drop-zone {
    border:1px dashed var(--gray-300); border-radius:12px;
    padding:16px; text-align:center; cursor:pointer;
    transition:var(--transition); background:var(--gray-50);
  }
  .drop-zone.drag-over { border-color:var(--primary); background:var(--primary-light); }
  .drop-zone .dz-icon { font-size:16px; line-height:26px; color:var(--gray-500); }
  .drop-zone .dz-text { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  .drop-zone .dz-sub  { font-size:12px; font-weight:400; line-height:19px; color:var(--gray-500); margin-top:2px; }
  .file-list { display:flex; flex-direction:column; gap:6px; }
  .file-item {
    display:flex; align-items:center; gap:8px;
    padding:5px 12px; background:var(--gray-0); border:1px solid var(--gray-200);
    border-radius:8px; font-size:13px; font-weight:400; line-height:20px;
  }
  .file-item i { color:var(--primary); font-size:16px; }
  .file-item .fi-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .file-item .fi-size { color:var(--gray-500); white-space:nowrap; }
  .file-item .fi-del  { color:var(--danger); cursor:pointer; margin-left:4px; font-size:16px; }

  /* 예약 전송 토글 */
  .reserve-toggle { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); cursor:pointer; }
  .reserve-toggle input[type=checkbox] { width:16px; height:16px; accent-color:var(--primary); cursor:pointer; }

  /* 전송 버튼 — Figma 버튼 규격 h32 · r8 · pad 5/12 · 13/500 · lh20 */
  .send-btn {
    height:32px; padding:5px 12px;
    background:var(--primary); color:var(--gray-0);
    border:1px solid var(--primary); border-radius:8px;
    font-size:13px; font-weight:500; line-height:20px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:6px;
    transition:var(--transition);
  }
  .send-btn:hover:not(:disabled) { background:var(--primary-dark); border-color:var(--primary-dark); }
  .send-btn:disabled { opacity:.6; cursor:not-allowed; }

  /* ── 전송 내역 ──
     껍데기(.hist-card/.hist-head/.hist-filter/.btn-search)는 표준 컴포넌트로 갈아탔다.
     검색줄 → .ds-filter-card · 결과바 → .ds-grid-bar · 표 → .ds-grid-card.
     '상태 동기화' 는 .ds-btn 위에 회전 애니메이션만 얹는다(syncPending() 이 .syncing 을 토글한다). */
  .btn-sync:disabled { opacity:.5; cursor:not-allowed; }
  .btn-sync.syncing i { animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }

  /* 아래 표 스타일은 지금 붙는 마크업이 없다(목록은 wwGrid 가 그린다).
     개발 자산이라 지우지 않고 값만 시안 규격으로 맞춰 둔다. */
  .hist-table { width:100%; border-collapse:collapse; font-size:13px; }
  .hist-table th {
    padding:10px 14px; background:var(--gray-50); font-size:13px; font-weight:700; line-height:21px;
    color:var(--gray-600); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap;
  }
  .hist-table td {
    padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle;
    font-size:13px; font-weight:400; line-height:21px;
  }
  .hist-table tr:last-child td { border-bottom:none; }
  .hist-table tr:hover td { background:var(--gray-50); }
  .hist-table .mono { font-family:monospace; font-size:11px; }
  .btn-detail {
    height:28px; padding:3px 10px; font-size:13px; font-weight:500; line-height:20px;
    background:var(--primary-light); color:var(--primary); border:none;
    border-radius:8px; cursor:pointer; white-space:nowrap;
  }
  .btn-detail:hover { background:var(--primary-100); }
  .hist-empty { padding:40px; text-align:center; color:var(--gray-500); font-size:13px; }

  /* 상태 배지 — Figma: r6 · pad 2/6 · 11px/500 · lh18 */
  .fax-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; }
  .fax-badge.wait   { background:var(--gray-100); color:var(--gray-600); }
  .fax-badge.send   { background:var(--primary-light); color:var(--primary); }
  .fax-badge.ok     { background:var(--primary-light); color:var(--primary); }
  .fax-badge.fail   { background:var(--danger-light); color:var(--danger); }
  .fax-badge.cancel { background:var(--gray-200); color:var(--gray-700); }

  /* 상세 모달 */
  .nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal { background:var(--gray-0); border-radius:12px; box-shadow:var(--shadow-lg); width:560px; max-width:92vw; max-height:85vh; display:flex; flex-direction:column; }
  .nd-modal-head { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .nd-modal-head h3 { flex:1; font-size:14px; font-weight:700; line-height:22px; margin:0; }
  .nd-modal-close { background:none; border:none; font-size:16px; color:var(--gray-500); cursor:pointer; line-height:1; }
  .nd-modal-body { padding:16px; overflow-y:auto; flex:1; }

  /* 아래는 openDetail() 이 만드는 마크업이 쓰는 클래스라 이름을 그대로 둔다. */
  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .detail-item .di-label { font-size:11px; font-weight:700; line-height:18px; color:var(--gray-600); margin-bottom:4px; }
  .detail-item .di-val   { font-size:13px; font-weight:500; line-height:21px; }
  .detail-item.full { grid-column:1/-1; }
  .rcv-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:8px; }
  .rcv-table th { padding:6px 10px; background:var(--gray-50); font-size:13px; font-weight:700; line-height:21px; color:var(--gray-600); border-bottom:1px solid var(--border); }
  .rcv-table td { padding:6px 10px; border-bottom:1px solid var(--border); font-size:13px; font-weight:400; line-height:21px; }
  .rcv-table tr:last-child td { border-bottom:none; }

  /* 상세 모달 - 정보 영역 */
  .fax-info-area {
    background:var(--gray-50); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;
  }
  .fax-info-area > div { display:flex; align-items:center; gap:8px; }
  .fax-info-area p { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:0; font-size:13px; font-weight:400; line-height:21px; }
  .fax-api-badge {
    display:inline-flex; align-items:center; padding:2px 6px;
    background:var(--primary); color:var(--gray-0); border-radius:6px;
    font-size:11px; font-weight:700; line-height:18px; flex-shrink:0;
  }
  .fax-info-title { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-right:4px; }
  .fax-stat-blue { color:var(--primary); font-weight:700; }
  .fax-stat-red  { color:var(--danger);  font-weight:700; }

  /* 상세 모달 - 일반 테이블 */
  .fax-normal-table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:12px; border:1px solid var(--border); }
  .fax-normal-table th {
    background:var(--gray-50); padding:0 14px; font-size:13px; font-weight:700; line-height:21px;
    color:var(--gray-600); text-align:center; border:1px solid var(--border); white-space:nowrap;
  }
  .fax-normal-table td { padding:0 14px; border:1px solid var(--border); font-size:13px; font-weight:400; line-height:21px; color:var(--text-primary); }
  .fax-conv-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .fax-dl-btn {
    height:20px; line-height:20px; padding:0 4px; background:none; border:none;
    color:var(--primary); font-size:11px; font-weight:500; cursor:pointer; text-decoration:underline;
    display:inline-flex; align-items:center; gap:4px;
  }
  /* 작은 버튼 규격 — h28 · r8 · pad 3/10 · 13/500 */
  .fax-preview-btn {
    display:inline-flex; align-items:center; height:28px; padding:3px 10px;
    background:var(--primary-light); color:var(--primary);
    border:1px solid var(--primary-200); border-radius:8px;
    font-size:13px; font-weight:500; line-height:20px; cursor:pointer;
  }
  .fax-preview-btn:hover { background:var(--primary-100); }

  /* 페이지네이션 — 그리드 카드 하단 줄 */
  .hist-pager { padding:12px 16px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .pager-info { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .pager-btns { display:flex; gap:2px; }
  .pager-btn {
    height:32px; min-width:32px; padding:0 10px; border:1px solid var(--gray-200); border-radius:8px;
    background:var(--gray-0); font-size:13px; font-weight:500; line-height:20px; cursor:pointer; color:var(--gray-800);
    transition:var(--transition);
  }
  .pager-btn:hover { border-color:var(--primary); color:var(--primary); }
  .pager-btn.active { background:var(--primary); color:var(--gray-0); border-color:var(--primary); }
  .pager-btn:disabled { opacity:.4; cursor:not-allowed; }
</style>
@endpush

@section('content')

{{-- 요약 카드 --}}
<div class="fax-summary">
  <div class="sum-card blue">
    <div class="sc-icon"><i class="bx bx-wallet"></i></div>
    <div>
      <div class="sc-label">잔여 포인트</div>
      <div class="sc-val" id="balance-val">—</div>
    </div>
  </div>
  <div class="sum-card green">
    <div class="sc-icon"><i class="bx bx-check-circle"></i></div>
    <div>
      <div class="sc-label">오늘 발송 (성공)</div>
      <div class="sc-val" id="today-ok-val">—</div>
    </div>
  </div>
  <div class="sum-card gray">
    <div class="sc-icon"><i class="bx bx-history"></i></div>
    <div>
      <div class="sc-label">이번 달 발송</div>
      <div class="sc-val" id="month-total-val">—</div>
    </div>
  </div>
</div>

{{-- 본문 --}}
{{-- 탭: 전송 내역 / 팩스 발송 (전송 내역 먼저) --}}
<div class="titab-bar">
  <button type="button" class="titab active" data-tab="hist" onclick="tiTab('hist')"><i class="bx bx-history"></i> 전송 내역</button>
  <button type="button" class="titab" data-tab="issue" onclick="tiTab('issue')"><i class="bx bx-printer"></i> 팩스 발송</button>
</div>

{{-- ── 전송 내역 — 검색 필터 / 결과바 / 그리드 카드 ── --}}
<div class="fax-pane" data-titab="hist">

  {{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래 --}}
  <div class="ds-filter-card">
    <div class="ds-filter-fields">
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">전송 기간</label>
        <div class="ds-field-range">
          <input type="date" id="f-start" class="form-control" value="{{ date('Ymd', strtotime('-30 days')) }}">
          <span class="ds-field-sep">~</span>
          <input type="date" id="f-end"   class="form-control" value="{{ date('Ymd') }}">
        </div>
      </div>
    </div>
    <div class="ds-filter-actions">
      <button type="button" class="ds-btn ds-btn-primary" onclick="loadHistory(1)">조회</button>
    </div>
  </div>

  {{-- 결과바(h32) — 좌: 전체·선택 건수, 우: 안내문 + 액션 버튼.
       그리드 툴바의 '엑셀 저장'과 하단 상태바의 건수를 전부 여기로 옮겼다. --}}
  <div class="ds-grid-section">
    <div class="ds-grid-bar">
      <div class="ds-grid-bar-left">
        <span class="ds-grid-total">전체 <b id="fax-total-count">0</b>건</span>
        <span class="ds-grid-sel">선택 <b id="fax-sel-count">0</b>건</span>
      </div>
      <div class="ds-grid-bar-right">
        <span class="ds-grid-hint" id="sync-status"></span>
        <span class="ds-grid-hint">행 <b>더블클릭</b> 또는 체크 후 버튼</span>
        <button type="button" class="ds-btn btn-sync" id="sync-btn" onclick="syncPending()" title="미완료 건 팝빌 상태 동기화">
          <i class="bx bx-refresh"></i> 상태 동기화
        </button>
        <button type="button" class="ds-btn" onclick="faxRowAction('detail')"><i class="bx bx-show"></i> 선택 상세</button>
        <button type="button" class="ds-btn" onclick="window.__faxGrid?.downloadExcel()">엑셀 저장</button>
      </div>
    </div>

    <div class="ds-grid-card">
      <div id="faxHistGrid"></div>

      <div class="hist-pager" id="hist-pager" style="display:none;">
        <div class="pager-info" id="pager-info"></div>
        <div class="pager-btns" id="pager-btns"></div>
      </div>
    </div>
  </div>

</div>

{{-- ── 팩스 발송 폼 ── --}}
<div class="send-card" data-titab="issue">
  <div class="send-card-head">
    <i class="bx bx-printer"></i>
    <span>팩스 발송</span>
  </div>
  <div class="send-card-body">

    {{-- 사업자번호 --}}
    <div class="form-row">
      <label class="ds-field-label">사업자번호</label>
      <input id="corp-num" class="form-input" type="text" value="{{ $corpNum }}" placeholder="1234567890">
    </div>

    {{-- 발신번호 --}}
    <div class="form-row">
      <label class="ds-field-label">발신 팩스번호 <span style="color:var(--danger)">*</span></label>
      <div class="ds-field-range">
        <select id="sender-select" class="form-input" style="flex:1;">
          <option value="">— 발신번호 선택 —</option>
        </select>
        <button type="button" onclick="loadSenderNumbers()" class="ds-btn" style="white-space:nowrap;">
          <i class="bx bx-refresh"></i>
        </button>
      </div>
    </div>

    {{-- 발신자명 --}}
    <div class="form-row">
      <label class="ds-field-label">발신자명</label>
      <input id="sender-name" class="form-input" type="text" placeholder="(선택) 발신자명">
    </div>

    {{-- 제목 --}}
    <div class="form-row">
      <label class="ds-field-label">팩스 제목</label>
      <input id="fax-title" class="form-input" type="text" placeholder="(선택) 팩스 제목">
    </div>

    {{-- 수신자 --}}
    <div class="form-row">
      <label class="ds-field-label">수신자 <span style="color:var(--danger)">*</span></label>
      <div class="receivers-box" id="receivers-box">
        <div class="receiver-row" data-idx="0">
          <div class="receiver-inputs">
            <input class="form-input rcv-num"  type="text" placeholder="팩스번호 (숫자만)" value="{{ $receiverFax }}" data-phone>
            <input class="form-input rcv-name" type="text" placeholder="수신자명 (선택)">
          </div>
          <button type="button" class="receiver-del-btn" onclick="removeReceiver(this)" style="visibility:hidden;">
            <i class="bx bx-x"></i>
          </button>
        </div>
      </div>
      <button type="button" class="receiver-add-btn" onclick="addReceiver()">
        <i class="bx bx-plus"></i> 수신자 추가
      </button>
    </div>

    {{-- 파일 첨부 --}}
    <div class="form-row">
      <label class="ds-field-label">첨부 파일 <span style="color:var(--danger)">*</span> <span style="font-weight:400;color:var(--gray-500)">(PDF·TIFF·JPG·PNG·GIF, 최대 10MB)</span></label>
      <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
        <input type="file" id="file-input" accept=".pdf,.tif,.tiff,.jpg,.jpeg,.gif,.png" multiple style="display:none">
        <div class="dz-icon"><i class="bx bx-cloud-upload"></i></div>
        <div class="dz-text">클릭하거나 파일을 드래그하세요</div>
        <div class="dz-sub">PDF, TIFF, JPG, PNG, GIF</div>
      </div>
      <div class="file-list" id="file-list"></div>
    </div>

    {{-- 예약 전송 --}}
    <div class="form-row">
      <label class="reserve-toggle">
        <input type="checkbox" id="reserve-chk" onchange="toggleReserve()">
        예약 전송
      </label>
      <input id="reserve-dt" class="form-input" type="datetime-local" style="display:none;">
    </div>

    {{-- 전송 버튼 --}}
    <button class="send-btn" id="send-btn" onclick="sendFax()">
      <i class="bx bx-send"></i> 팩스 전송
    </button>

  </div>
</div>

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal">
    <div class="nd-modal-head">
      <i class="bx bx-printer" style="color:var(--primary);font-size:16px;"></i>
      <h3>팩스 전송 상세</h3>
      <button class="nd-modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="nd-modal-body" id="detail-body">
      <div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// 전송 내역 wwGrid (조회 결과를 setData로 주입)
(function () {
  const el = document.getElementById('faxHistGrid');
  if (!el) return;
  window.__faxGrid = new wwGrid({
    el: el,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 상단 결과바에 있다.
    height: 460, editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: [
      { header: '전송일시', name: 'sentAt',     width: 150, sortable: true },
      { header: '발신번호', name: 'sendNum',    width: 120 },
      { header: '수신번호', name: 'receiveNum', width: 120 },
      { header: '제목',     name: 'title',      width: 220 },
      { header: '상태',     name: 'status',     width: 90, align: 'center', sortable: true },
    ],
    data: [],
  });
  // 하단 상태바에 있던 '선택 N건' 을 결과바로 잇는다(전역 헬퍼).
  window.dsBindSelCount(window.__faxGrid, 'fax-sel-count');
  // '전체 N건' 도 같은 상태바 표시였다. 같은 방식으로 결과바에 잇기만 한다.
  (function (g) {
    const t = document.getElementById('fax-total-count');
    if (!g || !t || typeof g._updateFooter !== 'function') return;
    const orig = g._updateFooter.bind(g);
    g._updateFooter = function () { orig(); t.textContent = g.getData().length; };
    g._updateFooter();
  })(window.__faxGrid);
  function faxOpenRow(r) {
    if (!r || !r.receiptNum) return;
    openDetail(r.receiptNum);
  }
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]'); if (!cell) return;
    const r = window.__faxGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (r) faxOpenRow(r);
  });
  window.faxRowAction = function (action) {
    const c = window.__faxGrid.getCheckedRows();
    if (!c.length)    { showToast('행을 먼저 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    const r = c[0];
    if (action === 'detail') { faxOpenRow(r); return; }
  };
})();
</script>
<script>
// 탭 전환(전송 내역 / 팩스 발송) — 기본은 전송 내역
function tiTab(name) {
  document.querySelectorAll('[data-titab]').forEach(el => { el.style.display = (el.dataset.titab === name) ? '' : 'none'; });
  document.querySelectorAll('.titab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
}
tiTab('hist');
</script>
<script>
const CORP_NUM   = document.getElementById('corp-num');
const FAX_BASE   = BASE_URL + '/api/popbill/fax';
const SMS_BASE   = BASE_URL + '/api/popbill/message';
const HEADERS    = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' };

let selectedFiles = [];
let histPage = 1;
let histMeta = {};

/* ── 초기화 ── */
document.addEventListener('DOMContentLoaded', () => {
  // 날짜 input을 먼저 초기화한 후 조회 실행
  const today = new Date();
  const ago30 = new Date(today);
  ago30.setDate(today.getDate() - 30);
  document.getElementById('f-start').value = fmtDate(ago30);
  document.getElementById('f-end').value   = fmtDate(today);

  loadBalance();
  loadSenderNumbers();
  initDropZone();
  loadHistory(1);
  loadTodayStats();
});

function fmtDate(d) {
  return d.getFullYear() + '-' +
    String(d.getMonth()+1).padStart(2,'0') + '-' +
    String(d.getDate()).padStart(2,'0');
}
function toApiDate(v) { return v.replace(/-/g,''); }

/* ── 잔여포인트 ── */
async function loadBalance() {
  try {
    const res = await fetch(`${FAX_BASE}/balance?corp_num=${CORP_NUM.value}`, { headers: HEADERS });
    const data = await res.json();
    document.getElementById('balance-val').textContent =
      typeof data.balance === 'number' ? data.balance.toLocaleString() + ' P' : '—';
  } catch { document.getElementById('balance-val').textContent = '오류'; }
}

/* ── 발신번호 로드 ── */
async function loadSenderNumbers() {
  const sel = document.getElementById('sender-select');
  sel.innerHTML = '<option value="">불러오는 중…</option>';
  try {
    const res  = await fetch(`${FAX_BASE}/sender-numbers?corp_num=${CORP_NUM.value}`, { headers: HEADERS });
    const list = await res.json();
    if (!Array.isArray(list) || list.length === 0) {
      sel.innerHTML = '<option value="">등록된 발신번호 없음</option>';
      return;
    }
    sel.innerHTML = '<option value="">— 발신번호 선택 —</option>' +
      list.map(n => `<option value="${n.number}">${n.number}${n.state == 1 ? '' : ' (미인증)'}</option>`).join('');
  } catch {
    sel.innerHTML = '<option value="">로드 실패</option>';
  }
}

/* ── 오늘 통계 ── */
async function loadTodayStats() {
  const today = toApiDate(fmtDate(new Date()));
  try {
    const res  = await fetch(`${FAX_BASE}/search?corp_num=${CORP_NUM.value}&start_date=${today}&end_date=${today}&per_page=1`, { headers: HEADERS });
    const data = await res.json();
    document.getElementById('today-ok-val').textContent = data.total ?? 0;
  } catch { document.getElementById('today-ok-val').textContent = '—'; }

  const firstDay = fmtDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1)).replace(/-/g,'');
  try {
    const res  = await fetch(`${FAX_BASE}/search?corp_num=${CORP_NUM.value}&start_date=${firstDay}&end_date=${today}&per_page=1`, { headers: HEADERS });
    const data = await res.json();
    document.getElementById('month-total-val').textContent = data.total ?? 0;
  } catch { document.getElementById('month-total-val').textContent = '—'; }
}

/* ── 수신자 관리 ── */
let rcvIdx = 0;
function addReceiver() {
  rcvIdx++;
  const box = document.getElementById('receivers-box');
  const row = document.createElement('div');
  row.className = 'receiver-row';
  row.dataset.idx = rcvIdx;
  row.innerHTML = `
    <div class="receiver-inputs">
      <input class="form-input rcv-num"  type="text" placeholder="팩스번호 (숫자만)" data-phone>
      <input class="form-input rcv-name" type="text" placeholder="수신자명 (선택)">
    </div>
    <button type="button" class="receiver-del-btn" onclick="removeReceiver(this)">
      <i class="bx bx-x"></i>
    </button>`;
  box.appendChild(row);
  updateDelBtns();
}

function removeReceiver(btn) {
  btn.closest('.receiver-row').remove();
  updateDelBtns();
}

function updateDelBtns() {
  const rows = document.querySelectorAll('#receivers-box .receiver-row');
  rows.forEach(r => {
    const btn = r.querySelector('.receiver-del-btn');
    btn.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
  });
}

/* ── 드롭존 ── */
function initDropZone() {
  const zone  = document.getElementById('drop-zone');
  const input = document.getElementById('file-input');

  input.addEventListener('change', () => addFiles(input.files));
  zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop',      e => {
    e.preventDefault(); zone.classList.remove('drag-over');
    addFiles(e.dataTransfer.files);
  });
}

function addFiles(fileList) {
  const allowed = ['application/pdf','image/tiff','image/jpeg','image/gif','image/png'];
  Array.from(fileList).forEach(f => {
    if (f.size > 10 * 1024 * 1024) { showToast(`${f.name}: 10MB 초과`, 'danger'); return; }
    if (selectedFiles.some(s => s.name === f.name && s.size === f.size)) return;
    selectedFiles.push(f);
  });
  renderFileList();
}

function renderFileList() {
  const list = document.getElementById('file-list');
  list.innerHTML = selectedFiles.map((f, i) => `
    <div class="file-item">
      <i class="bx bx-file-blank"></i>
      <span class="fi-name">${f.name}</span>
      <span class="fi-size">${(f.size/1024).toFixed(0)} KB</span>
      <i class="bx bx-x fi-del" onclick="removeFile(${i})"></i>
    </div>`).join('');
}

function removeFile(idx) {
  selectedFiles.splice(idx, 1);
  renderFileList();
}

/* ── 예약 전송 토글 ── */
function toggleReserve() {
  const chk = document.getElementById('reserve-chk');
  document.getElementById('reserve-dt').style.display = chk.checked ? 'block' : 'none';
}

/* ── 팩스 전송 ── */
async function sendFax() {
  const corpNum    = CORP_NUM.value.trim();
  const sender     = document.getElementById('sender-select').value;
  const senderName = document.getElementById('sender-name').value.trim();
  const title      = document.getElementById('fax-title').value.trim();

  // 유효성 검사
  if (!sender) { showToast('발신번호를 선택하세요.', 'danger'); return; }
  if (selectedFiles.length === 0) { showToast('전송할 파일을 첨부하세요.', 'danger'); return; }

  const receivers = [];
  document.querySelectorAll('#receivers-box .receiver-row').forEach(row => {
    const num  = row.querySelector('.rcv-num').value.trim().replace(/\D/g,'');
    const name = row.querySelector('.rcv-name').value.trim();
    if (num) receivers.push({ rcv: num, rcvnm: name });
  });
  if (receivers.length === 0) { showToast('수신 팩스번호를 입력하세요.', 'danger'); return; }

  const fd = new FormData();
  fd.append('corp_num', corpNum);
  fd.append('sender',   sender);
  if (senderName) fd.append('sender_name', senderName);
  if (title)      fd.append('title',       title);
  receivers.forEach((r, i) => {
    fd.append(`receivers[${i}][rcv]`,   r.rcv);
    fd.append(`receivers[${i}][rcvnm]`, r.rcvnm);
  });
  selectedFiles.forEach(f => fd.append('files[]', f));

  const reserveChk = document.getElementById('reserve-chk').checked;
  if (reserveChk) {
    const dt = document.getElementById('reserve-dt').value;
    if (!dt) { showToast('예약 일시를 입력하세요.', 'danger'); return; }
    fd.append('reserve_dt', dt.replace(/[-T:]/g,'').slice(0,14));
  }

  const btn = document.getElementById('send-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 전송 중…';

  try {
    const res  = await fetch(`${FAX_BASE}/send`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '전송 실패');

    showToast(`팩스 전송 완료! 접수번호: ${data.receipt_num}`, 'success', 6000);
    selectedFiles = [];
    renderFileList();
    loadHistory(1);
    loadBalance();
    loadTodayStats();
    // 30초 후 자동 동기화 (전송 결과 반영)
    setTimeout(() => syncPending(), 30000);
  } catch(e) {
    showToast('전송 실패: ' + e.message, 'danger', 6000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-send"></i> 팩스 전송';
  }
}

/* ── 전송 내역 조회 (DB 기반, 조회 시 팝빌 자동 동기화) ── */
async function loadHistory(page = 1) {
  histPage = page;
  const corpNum   = CORP_NUM.value.trim();
  const startDate = toApiDate(document.getElementById('f-start').value);
  const endDate   = toApiDate(document.getElementById('f-end').value);

  window.__faxGrid && window.__faxGrid.setData([]);

  // 팝빌에서 해당 기간 내역 먼저 동기화 (신규 저장 + 상태 변경 반영)
  try {
    await fetch(`${FAX_BASE}/sync-from-popbill`, {
      method: 'POST',
      headers: { ...HEADERS, 'Content-Type': 'application/json' },
      body: JSON.stringify({ corp_num: corpNum, start_date: startDate, end_date: endDate }),
    });
  } catch(_) { /* 동기화 실패해도 DB 조회는 계속 */ }

  try {
    const url = `${FAX_BASE}/history?corp_num=${corpNum}&start_date=${startDate}&end_date=${endDate}&page=${page}&per_page=15`;
    const res  = await fetch(url, { headers: HEADERS });
    const data = await res.json();

    if (!res.ok) throw new Error(data.message || '조회 실패');

    histMeta = { total: data.total ?? 0, page, perPage: 15 };
    const list = data.list ?? [];

    if (list.length === 0) {
      window.__faxGrid.setData([]);
      document.getElementById('hist-pager').style.display = 'none';
      return;
    }

    const rows = list.map(row => {
      const s          = String(row.state ?? 0);
      const statusTxt  = { '0':'대기','1':'전송중','2':'성공','3':'실패','4':'취소' }[s] ?? '알수없음';
      const sentAt     = row.sendDT ? row.sendDT.replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5') : '—';
      return {
        sentAt,
        sendNum:    row.sendNum ?? '—',
        receiveNum: row.receiveNum ?? '—',
        title:      row.title || '—',
        status:     statusTxt,
        receiptNum: row.receiptNum,
      };
    });
    window.__faxGrid.setData(rows);

    renderPager(data.total ?? 0, page, 15);
  } catch(e) {
    window.__faxGrid && window.__faxGrid.setData([]);
    showToast('조회 실패: ' + e.message, 'error');
  }
}

/* ── 미완료 건 팝빌 동기화 ── */
async function syncPending() {
  const btn  = document.getElementById('sync-btn');
  const info = document.getElementById('sync-status');
  btn.disabled = true;
  btn.classList.add('syncing');
  info.textContent = '동기화 중…';

  try {
    const res  = await fetch(`${FAX_BASE}/sync-pending`, {
      method: 'POST',
      headers: { ...HEADERS, 'Content-Type': 'application/json' },
      body: JSON.stringify({ corp_num: CORP_NUM.value.trim() }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '동기화 실패');

    const msg = `동기화 완료: 총 ${data.total}건 중 ${data.synced}건 갱신` +
      (data.errors > 0 ? `, 오류 ${data.errors}건` : '');
    info.textContent = msg;
    showToast(msg, data.errors > 0 ? 'warning' : 'success');
    loadHistory(histPage);
  } catch(e) {
    info.textContent = '동기화 실패';
    showToast('동기화 실패: ' + e.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.classList.remove('syncing');
  }
}

/* ── 팩스 결과코드 설명 ── */
function faxResultDesc(code) {
  const map = {
    '0':'전송 성공', '-1':'결과 없음',
    '2':'수신 거부', '3':'전화번호 오류', '4':'전화기 꺼짐',
    '5':'전화기 오류', '6':'통화중', '7':'링 없음',
    '8':'팩스 수신 불가', '9':'수신지 지원 불가',
    '10':'전화국 없음', '11':'통신 장애', '12':'기타 오류',
  };
  const c = String(code ?? '');
  return map[c] ? `결과코드 ${c}: ${map[c]}` : `결과코드 ${c}`;
}

function renderPager(total, page, perPage) {
  const pager = document.getElementById('hist-pager');
  const pages = Math.ceil(total / perPage);
  if (pages <= 1) { pager.style.display = 'none'; return; }

  pager.style.display = 'flex';
  document.getElementById('pager-info').textContent = `총 ${total.toLocaleString()}건`;

  const btns   = [];
  const start  = Math.max(1, page - 2);
  const end    = Math.min(pages, page + 2);

  if (page > 1) btns.push(`<button class="pager-btn" onclick="loadHistory(${page-1})">‹</button>`);
  for (let p = start; p <= end; p++) {
    btns.push(`<button class="pager-btn ${p===page?'active':''}" onclick="loadHistory(${p})">${p}</button>`);
  }
  if (page < pages) btns.push(`<button class="pager-btn" onclick="loadHistory(${page+1})">›</button>`);

  document.getElementById('pager-btns').innerHTML = btns.join('');
}

/* ── 상세 모달 ── */
async function openDetail(receiptNum) {
  document.getElementById('detail-modal').classList.add('open');
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>';

  try {
    const corpNum = CORP_NUM.value.trim();
    const res  = await fetch(`${FAX_BASE}/messages?corp_num=${corpNum}&receipt_num=${receiptNum}`, { headers: HEADERS });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '상세 조회 실패');

    // GetFaxDetail 은 FaxState[] 반환 — 각 원소가 수신자별 상태
    const arr   = Array.isArray(data) ? data : [data];
    const first = arr[0] || {};
    const stMap = { '0':'wait','1':'send','2':'ok','3':'fail','4':'cancel' };
    const txMap = { '0':'대기','1':'전송중','2':'성공','3':'실패','4':'취소' };

    // 접수일시 / 예약일시
    const fmt = s => s ? s.replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1-$2-$3 $4:$5:$6') : '';
    const receiptDT = fmt(first.receiptDT) || fmt(first.sendDT) || '—';
    const reserveDT = fmt(first.reserveDT) || '';

    // 페이지 수 집계 (sendPageCnt 있으면 활용, 없으면 수신자 수 기반)
    const totalPages  = first.sendPageCnt  ?? arr.length;
    const okPages     = first.successPageCnt ?? arr.filter(r => r.state == 2).length;
    const failPages   = first.failPageCnt    ?? arr.filter(r => r.state == 3).length;
    const cancelPages = first.cancelPageCnt  ?? arr.filter(r => r.state == 4).length;
    const waitPages   = Math.max(0, totalPages - okPages - failPages - cancelPages);

    // 변환 상태
    const convState = first.convState;
    const convOk    = convState == null ? arr.some(r => r.state > 0) : convState == 1;
    const convTxt   = convOk ? `<span class="fax-stat-blue">변환성공</span>` : `<span class="fax-stat-red">변환실패</span>`;

    const rcvRows = arr.length
      ? arr.map(r => {
          const rCls  = stMap[String(r.state??'')] ?? 'wait';
          const rTxt  = txMap[String(r.state??'')] ?? '—';
          const rDesc = r.state == 3 ? `<br><span style="font-size:10px;color:var(--text-muted);">${faxResultDesc(r.result)}</span>` : '';
          return `<tr><td>${r.receiveNum??'—'}</td><td>${r.receiveName??'—'}</td><td><span class="fax-badge ${rCls}">${rTxt}</span>${rDesc}</td></tr>`;
        }).join('')
      : '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">수신자 정보 없음</td></tr>';

    document.getElementById('detail-body').innerHTML = `
      <div class="fax-info-area">
        <div>
          <span class="fax-api-badge">API</span>
          <p><span class="fax-info-title">접수번호</span>${receiptNum}</p>
        </div>
        <p>
          <span class="fax-info-title">전송결과</span>
          <span>전체 ${totalPages}장</span>
          <span>대기 ${waitPages}장</span>
          <span>성공 <span class="fax-stat-blue">${okPages}</span>장</span>
          <span>실패 <span class="fax-stat-red">${failPages}</span>장</span>
          <span>취소 ${cancelPages}장</span>
        </p>
      </div>
      <table class="fax-normal-table">
        <colgroup><col width="15%"><col width="35%"><col width="15%"><col width="35%"></colgroup>
        <tbody>
          <tr style="height:40px;">
            <th>접수일시</th><td>${receiptDT}</td>
            <th>예약일시</th><td>${reserveDT}</td>
          </tr>
          <tr style="height:40px;">
            <th>구분</th><td>팩스</td>
            <th>발신번호</th><td>${first.sendNum ?? '—'}</td>
          </tr>
          <tr style="height:40px;">
            <th>변환상태</th>
            <td colspan="3">
              <div class="fax-conv-row">
                ${convTxt}
                <span>[<button class="fax-dl-btn" type="button">${receiptNum}.tif <i class="bx bx-download"></i></button>]</span>
                <button class="fax-preview-btn" type="button">미리보기</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="di-label" style="margin-bottom:6px;font-size:11px;font-weight:700;color:var(--gray-600);">수신자 목록</div>
      <table class="rcv-table">
        <thead><tr><th>팩스번호</th><th>수신자명</th><th>상태 / 결과</th></tr></thead>
        <tbody>${rcvRows}</tbody>
      </table>`;
  } catch(e) {
    document.getElementById('detail-body').innerHTML = `<div style="text-align:center;padding:30px;color:var(--danger);">${e.message}</div>`;
  }
}

function closeModal() {
  document.getElementById('detail-modal').classList.remove('open');
}
document.getElementById('detail-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('detail-modal')) closeModal();
});
</script>
@endpush
