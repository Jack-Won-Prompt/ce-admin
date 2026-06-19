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
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);"><i class="bx bx-file"></i></div><div class="help-item-text">PDF, TIFF, JPG, PNG, GIF — 건당 최대 10MB</div></div>
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
  <div class="help-item"><div class="help-item-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bx bx-error"></i></div><div class="help-item-text">발신번호는 팝빌 포털에서 사전 등록·인증 후 사용 가능합니다.</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bx bx-error"></i></div><div class="help-item-text">테스트 환경에서는 실제 팩스가 발송되지 않습니다.</div></div>
</div>
@endsection

@push('styles')
<style>
  /* ── 레이아웃 ── */
  .fax-layout { display: grid; grid-template-columns: 400px 1fr; gap: 20px; align-items: start; }
  @media(max-width:1100px){ .fax-layout { grid-template-columns: 1fr; } }

  /* ── 요약 카드 ── */
  .fax-summary { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
  @media(max-width:700px){ .fax-summary { grid-template-columns: 1fr 1fr; } }
  .sum-card {
    background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
    padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow);
  }
  .sum-card .sc-icon {
    width:44px; height:44px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:20px;
  }
  .sum-card .sc-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
  .sum-card .sc-val   { font-size:22px; font-weight:800; line-height:1; }
  .sum-card.blue  .sc-icon { background:var(--primary-light); color:var(--primary); }
  .sum-card.green .sc-icon { background:var(--success-light); color:var(--success); }
  .sum-card.gray  .sc-icon { background:var(--border-light);  color:var(--text-muted); }

  /* ── 발송 폼 카드 ── */
  .send-card {
    background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow); overflow:hidden;
  }
  .send-card-head {
    padding:16px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
    font-weight:700; font-size:14px;
  }
  .send-card-head i { font-size:18px; color:var(--primary); }
  .send-card-body { padding:20px; display:flex; flex-direction:column; gap:14px; }

  .form-row { display:flex; flex-direction:column; gap:4px; }
  .form-label { font-size:11.5px; font-weight:600; color:var(--text-muted); }
  .form-input {
    height:38px; border:1px solid var(--border); border-radius:var(--radius);
    padding:0 12px; font-size:13px; color:var(--text); background:#fff;
    transition:border-color .15s;
  }
  .form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(27,102,245,.12); }
  .form-input.error { border-color:var(--danger); }
  select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:28px; }

  /* 수신자 섹션 */
  .receivers-box { border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .receiver-row {
    display:grid; grid-template-columns:1fr auto;
    gap:8px; padding:10px 12px; border-bottom:1px solid var(--border);
    align-items:center;
  }
  .receiver-inputs { display:flex; flex-direction:column; gap:6px; }
  .receiver-row:last-child { border-bottom:none; }
  .receiver-add-btn {
    display:flex; align-items:center; justify-content:center; gap:6px;
    padding:9px; font-size:12px; font-weight:600; color:var(--primary);
    background:var(--primary-light); border:none; cursor:pointer;
    transition:background .15s;
  }
  .receiver-add-btn:hover { background:rgba(27,102,245,.12); }
  .receiver-del-btn {
    width:28px; height:28px; border-radius:6px; border:none;
    background:var(--danger-light); color:var(--danger); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:16px;
    transition:background .15s; flex-shrink:0;
  }
  .receiver-del-btn:hover { background:rgba(239,68,68,.15); }

  /* 파일 드롭존 */
  .drop-zone {
    border:2px dashed var(--border); border-radius:var(--radius-lg);
    padding:24px 16px; text-align:center; cursor:pointer;
    transition:border-color .2s, background .2s; background:var(--bg);
  }
  .drop-zone.drag-over { border-color:var(--primary); background:var(--primary-light); }
  .drop-zone .dz-icon { font-size:32px; color:var(--text-muted); margin-bottom:6px; }
  .drop-zone .dz-text { font-size:13px; color:var(--text-muted); }
  .drop-zone .dz-sub  { font-size:11px; color:var(--text-muted); margin-top:4px; }
  .file-list { display:flex; flex-direction:column; gap:6px; margin-top:10px; }
  .file-item {
    display:flex; align-items:center; gap:8px;
    padding:7px 10px; background:#fff; border:1px solid var(--border);
    border-radius:var(--radius); font-size:12px;
  }
  .file-item i { color:var(--primary); font-size:16px; }
  .file-item .fi-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .file-item .fi-size { color:var(--text-muted); white-space:nowrap; }
  .file-item .fi-del  { color:var(--danger); cursor:pointer; margin-left:4px; font-size:16px; }

  /* 예약 전송 토글 */
  .reserve-toggle { display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; }
  .reserve-toggle input[type=checkbox] { width:16px; height:16px; accent-color:var(--primary); cursor:pointer; }

  /* 전송 버튼 */
  .send-btn {
    height:44px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius);
    font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center;
    justify-content:center; gap:8px; transition:background .15s;
  }
  .send-btn:hover:not(:disabled) { background:#1554d4; }
  .send-btn:disabled { opacity:.6; cursor:not-allowed; }

  /* ── 전송 내역 패널 ── */
  .hist-card {
    background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow); overflow:hidden;
  }
  .hist-head {
    padding:16px 20px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  }
  .hist-head-title { font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; flex:1; }
  .hist-head-title i { font-size:18px; color:var(--primary); }
  .hist-filter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .hist-filter input[type=date], .hist-filter select {
    height:34px; border:1px solid var(--border); border-radius:var(--radius);
    padding:0 10px; font-size:12px; color:var(--text); background:#fff;
  }
  .hist-filter input[type=date]:focus, .hist-filter select:focus { outline:none; border-color:var(--primary); }
  .btn-search {
    height:34px; padding:0 14px; background:var(--primary); color:#fff; border:none;
    border-radius:var(--radius); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;
  }
  .btn-search:hover { background:#1554d4; }
  .btn-sync {
    height:34px; padding:0 12px; background:#fff; color:var(--text-muted);
    border:1px solid var(--border); border-radius:var(--radius);
    font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;
    display:inline-flex; align-items:center; gap:5px; transition:border-color .15s, color .15s;
  }
  .btn-sync:hover:not(:disabled) { border-color:var(--primary); color:var(--primary); }
  .btn-sync:disabled { opacity:.5; cursor:not-allowed; }
  .btn-sync.syncing i { animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }

  .hist-body { overflow-x:auto; }
  .hist-table { width:100%; border-collapse:collapse; font-size:12.5px; }
  .hist-table th {
    padding:10px 14px; background:var(--bg); font-weight:600; font-size:11.5px;
    color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap;
  }
  .hist-table td {
    padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle;
  }
  .hist-table tr:last-child td { border-bottom:none; }
  .hist-table tr:hover td { background:rgba(27,102,245,.03); }
  .hist-table .mono { font-family:monospace; font-size:11px; }
  .btn-detail {
    height:26px; padding:0 10px; font-size:11px; font-weight:600;
    background:var(--primary-light); color:var(--primary); border:none;
    border-radius:5px; cursor:pointer; white-space:nowrap;
  }
  .btn-detail:hover { background:rgba(27,102,245,.15); }
  .hist-empty { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

  /* 상태 배지 */
  .fax-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
  .fax-badge.wait   { background:var(--border-light); color:var(--text-muted); }
  .fax-badge.send   { background:var(--info-light);   color:var(--info); }
  .fax-badge.ok     { background:var(--success-light);color:var(--success); }
  .fax-badge.fail   { background:var(--danger-light); color:var(--danger); }
  .fax-badge.cancel { background:#fff3e0; color:#e65100; }

  /* 상세 모달 */
  .nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal { background:#fff; border-radius:var(--radius-lg); box-shadow:0 20px 60px rgba(0,0,0,.18); width:560px; max-width:92vw; max-height:85vh; display:flex; flex-direction:column; }
  .nd-modal-head { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
  .nd-modal-head h3 { flex:1; font-size:15px; font-weight:700; margin:0; }
  .nd-modal-close { background:none; border:none; font-size:20px; color:var(--text-muted); cursor:pointer; line-height:1; }
  .nd-modal-body { padding:22px; overflow-y:auto; flex:1; }

  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .detail-item .di-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
  .detail-item .di-val   { font-size:13px; font-weight:500; }
  .detail-item.full { grid-column:1/-1; }
  .rcv-table { width:100%; border-collapse:collapse; font-size:12.5px; margin-top:8px; }
  .rcv-table th { padding:7px 10px; background:var(--bg); font-size:11px; font-weight:600; color:var(--text-muted); border-bottom:1px solid var(--border); }
  .rcv-table td { padding:7px 10px; border-bottom:1px solid var(--border); }
  .rcv-table tr:last-child td { border-bottom:none; }

  /* 상세 모달 - 정보 영역 */
  .fax-info-area {
    background:var(--bg); border:1px solid var(--border); border-radius:var(--radius);
    padding:12px 16px; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;
  }
  .fax-info-area > div { display:flex; align-items:center; gap:8px; }
  .fax-info-area p { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin:0; font-size:12.5px; }
  .fax-api-badge {
    display:inline-flex; align-items:center; padding:2px 8px;
    background:var(--primary); color:#fff; border-radius:4px;
    font-size:11px; font-weight:700; flex-shrink:0;
  }
  .fax-info-title { font-weight:600; color:var(--text-muted); font-size:12px; margin-right:4px; }
  .fax-stat-blue { color:var(--primary); font-weight:700; }
  .fax-stat-red  { color:var(--danger);  font-weight:700; }

  /* 상세 모달 - 일반 테이블 */
  .fax-normal-table { width:100%; border-collapse:collapse; font-size:12.5px; margin-bottom:14px; border:1px solid var(--border); }
  .fax-normal-table th {
    background:var(--bg); padding:0 14px; font-size:12px; font-weight:600;
    color:var(--text-muted); text-align:center; border:1px solid var(--border); white-space:nowrap;
  }
  .fax-normal-table td { padding:0 14px; border:1px solid var(--border); color:var(--text); }
  .fax-conv-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .fax-dl-btn {
    height:20px; line-height:20px; padding:0 4px; background:none; border:none;
    color:var(--primary); font-size:11px; cursor:pointer; text-decoration:underline;
    display:inline-flex; align-items:center; gap:3px;
  }
  .fax-preview-btn {
    height:24px; line-height:24px; padding:0 10px;
    background:var(--primary-light); color:var(--primary);
    border:1px solid rgba(27,102,245,.3); border-radius:4px;
    font-size:11px; font-weight:600; cursor:pointer;
  }
  .fax-preview-btn:hover { background:rgba(27,102,245,.15); }

  /* 페이지네이션 */
  .hist-pager { padding:12px 16px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
  .pager-info { color:var(--text-muted); }
  .pager-btns { display:flex; gap:4px; }
  .pager-btn {
    height:30px; padding:0 10px; border:1px solid var(--border); border-radius:var(--radius);
    background:#fff; font-size:12px; cursor:pointer; color:var(--text);
    transition:border-color .15s, background .15s;
  }
  .pager-btn:hover { border-color:var(--primary); color:var(--primary); }
  .pager-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
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
<div class="fax-layout">

  {{-- ── 좌측: 발송 폼 ── --}}
  <div class="send-card">
    <div class="send-card-head">
      <i class="bx bx-printer"></i>
      <span>팩스 발송</span>
    </div>
    <div class="send-card-body">

      {{-- 사업자번호 --}}
      <div class="form-row">
        <label class="form-label">사업자번호</label>
        <input id="corp-num" class="form-input" type="text" value="{{ $corpNum }}" placeholder="1234567890">
      </div>

      {{-- 발신번호 --}}
      <div class="form-row">
        <label class="form-label">발신 팩스번호 <span style="color:var(--danger)">*</span></label>
        <div style="display:flex;gap:8px;">
          <select id="sender-select" class="form-input" style="flex:1;">
            <option value="">— 발신번호 선택 —</option>
          </select>
          <button type="button" onclick="loadSenderNumbers()" class="btn-search" style="white-space:nowrap;">
            <i class="bx bx-refresh"></i>
          </button>
        </div>
      </div>

      {{-- 발신자명 --}}
      <div class="form-row">
        <label class="form-label">발신자명</label>
        <input id="sender-name" class="form-input" type="text" placeholder="(선택) 발신자명">
      </div>

      {{-- 제목 --}}
      <div class="form-row">
        <label class="form-label">팩스 제목</label>
        <input id="fax-title" class="form-input" type="text" placeholder="(선택) 팩스 제목">
      </div>

      {{-- 수신자 --}}
      <div class="form-row">
        <label class="form-label">수신자 <span style="color:var(--danger)">*</span></label>
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
        <label class="form-label">첨부 파일 <span style="color:var(--danger)">*</span> <span style="font-weight:400;color:var(--text-muted)">(PDF·TIFF·JPG·PNG·GIF, 최대 10MB)</span></label>
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
        <input id="reserve-dt" class="form-input" type="datetime-local" style="display:none;margin-top:6px;">
      </div>

      {{-- 전송 버튼 --}}
      <button class="send-btn" id="send-btn" onclick="sendFax()">
        <i class="bx bx-send"></i> 팩스 전송
      </button>

    </div>
  </div>

  {{-- ── 우측: 전송 내역 ── --}}
  <div class="hist-card">
    <div class="hist-head">
      <div class="hist-head-title">
        <i class="bx bx-history"></i> 전송 내역
        <span id="sync-status" style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:4px;"></span>
      </div>
      <div class="hist-filter">
        <input type="date" id="f-start" value="{{ date('Ymd', strtotime('-30 days')) }}">
        <input type="date" id="f-end"   value="{{ date('Ymd') }}">
        <button class="btn-search" onclick="loadHistory(1)">조회</button>
        <button class="btn-sync" id="sync-btn" onclick="syncPending()" title="미완료 건 팝빌 상태 동기화">
          <i class="bx bx-refresh"></i> 상태 동기화
        </button>
      </div>
    </div>

    <div class="hist-body">
      <table class="hist-table">
        <thead>
          <tr>
            <th>전송일시</th>
            <th>발신번호</th>
            <th>수신번호</th>
            <th>제목</th>
            <th>상태</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="hist-tbody">
          <tr><td colspan="6" class="hist-empty">조회 버튼을 눌러 내역을 불러오세요.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="hist-pager" id="hist-pager" style="display:none;">
      <div class="pager-info" id="pager-info"></div>
      <div class="pager-btns" id="pager-btns"></div>
    </div>
  </div>

</div>

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal">
    <div class="nd-modal-head">
      <i class="bx bx-printer" style="color:var(--primary);font-size:20px;"></i>
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
  const tbody     = document.getElementById('hist-tbody');

  tbody.innerHTML = `<tr><td colspan="6" class="hist-empty"><i class="bx bx-loader-alt bx-spin" style="font-size:20px;"></i></td></tr>`;

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
      tbody.innerHTML = `<tr><td colspan="6" class="hist-empty">전송 내역이 없습니다.</td></tr>`;
      document.getElementById('hist-pager').style.display = 'none';
      return;
    }

    tbody.innerHTML = list.map(row => {
      const s        = String(row.state ?? 0);
      const badgeCls = { '0':'wait','1':'send','2':'ok','3':'fail','4':'cancel' }[s] ?? 'wait';
      const badgeTxt = { '0':'대기','1':'전송중','2':'성공','3':'실패','4':'취소' }[s] ?? '알수없음';
      const sentAt   = row.sendDT ? row.sendDT.replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5') : '—';
      const resultTip = row.state == 3 ? ` title="${faxResultDesc(row.result)}"` : '';
      const syncMark  = !row.syncedAt && row.state == 0
        ? `<i class="bx bx-time-five" style="color:var(--warning);font-size:12px;vertical-align:middle;margin-left:4px;" title="동기화 전"></i>`
        : '';
      return `<tr>
        <td>${sentAt}</td>
        <td>${row.sendNum ?? '—'}</td>
        <td>${row.receiveNum ?? '—'}</td>
        <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${row.title || '—'}</td>
        <td><span class="fax-badge ${badgeCls}"${resultTip}>${badgeTxt}</span>${syncMark}</td>
        <td><button class="btn-detail" onclick="openDetail('${row.receiptNum}')">상세</button></td>
      </tr>`;
    }).join('');

    renderPager(data.total ?? 0, page, 15);
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="6" class="hist-empty" style="color:var(--danger);">조회 실패: ${e.message}</td></tr>`;
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
      <div class="di-label" style="margin-bottom:6px;font-size:11px;font-weight:600;color:var(--text-muted);">수신자 목록</div>
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
