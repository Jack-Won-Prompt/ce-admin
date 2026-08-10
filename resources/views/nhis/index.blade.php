{{-- resources/views/nhis/index.blade.php --}}
@extends('layouts.app')

@section('title', 'NHIS 청구 관리')
@section('page-title', 'NHIS 청구 관리')
@section('breadcrumb', '홈 / NHIS 청구')

@section('help-title', 'NHIS 청구 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>건강보험심사평가원(NHIS) 청구 현황을 관리하고 팩스 전송을 처리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">청구 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">청구 대기</span>
    <span class="badge badge-info">팩스 전송됨</span>
    <span class="badge badge-success">승인 완료</span>
    <span class="badge badge-danger">반려</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">처리 순서</div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">1</div>
    <div class="help-item-text">청구 대상 주문을 선택</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">2</div>
    <div class="help-item-text">처방전 팩스 전송 (e-Fax)</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">3</div>
    <div class="help-item-text">심사 결과 수신 후 환급금 입력</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* 패널 탭(목록/상세보기) — 그리드 카드 안 상단.
     Figma 282:2299: h44 · pad 0/16 · gap 16 · 하단 1px, 활성 밑줄 1px primary */

  /* 결과바 보조 표기 — Figma 282:53 실측
     선택 13/500 gray-600(숫자 primary-400) · 이번달 청구액 13/500 gray-600(숫자 gray-800) */
  .ds-grid-meta   { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-600); }
  .ds-grid-meta b { color:var(--gray-800); font-weight:500; }

  /* 요약 카드 — 시안(282:53)에는 없는 블록이다(칩 라벨로 흡수돼 있다).
     개발이 넣은 정보라 남기고, 카드 규격(r12 · pad 12/16 · bd 1px gray-200)만 맞춘다.
     아래 여백은 .page-body 의 gap 이 만든다. */
  .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
  @media(max-width:900px){ .summary-grid { grid-template-columns: repeat(2,1fr); } }

  .summary-card {
    background: var(--gray-0); border: 1px solid var(--gray-200); border-radius: 12px;
    padding: 12px 16px; display: flex; flex-direction: column; align-items: flex-start; gap: 4px;
  }
  .summary-card .sc-icon {
    width: 48px; height: 48px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
  }
  .summary-card .s-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-600); }
  .summary-card .s-value { font-size: 16px; font-weight: 700; line-height: 26px; }
  .summary-card .s-sub   { font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600); }
  .summary-card.blue  .sc-icon { background: var(--primary-light); color: var(--primary); }
  .summary-card.green .sc-icon { background: var(--success-light); color: var(--success); }
  .summary-card.red   .sc-icon { background: var(--danger-light);  color: var(--danger); }
  .summary-card.gray  .sc-icon { background: var(--border-light);  color: var(--text-muted); }
  .summary-card.blue  .s-value { color: var(--primary); }
  .summary-card.green .s-value { color: var(--success); }
  .summary-card.red   .s-value { color: var(--danger); }

  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }

  .order-number { font-size: 12px; font-weight: 700; color: var(--primary); }
  .amount-cell  { text-align: right; font-variant-numeric: tabular-nums; }

  .check-col { width: 36px; text-align: center; }
  .bulk-bar  {
    display: none; align-items: center; gap: 10px;
    padding: 10px 16px; background: var(--primary-light);
    border: 1px solid var(--primary-accent); border-radius: var(--radius);
    margin-bottom: 12px;
  }
  .bulk-bar.show { display: flex; }

  /* 결과 등록 모달 */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); z-index: 1000;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal-box {
    background: #fff; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); width: 480px; max-width: 95vw; max-height: 90vh;
    overflow-y: auto;
  }
  .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
  }
  .modal-title { font-size: 14px; font-weight: 700; }
  .modal-body  { padding: 18px; }
  .modal-footer { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
  .btn-close-modal { background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text-muted); }

  /* 팩스 로그 패널 */
  .log-panel {
    display: none; background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px; margin-top: 8px;
  }
  .log-panel.open { display: block; }
  .log-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 0; border-bottom: 1px solid var(--border-light); font-size: 12px;
  }
  .log-item:last-child { border-bottom: none; }

  /* 청구서 미리보기 모달 */
  .preview-content {
    white-space: pre; font-family: 'Courier New', monospace;
    font-size: 12px; line-height: 1.6;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 16px;
    max-height: 60vh; overflow-y: auto;
  }

  .efax-status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 500; line-height: 18px;
    padding: 2px 6px; border-radius: 6px;
  }
  .efax-sent     { background: var(--success-light); color: var(--success); }
  .efax-failed   { background: var(--danger-light);  color: var(--danger); }
  .efax-queued   { background: var(--border-light);  color: var(--text-secondary); }
  .efax-sending  { background: var(--warning-light); color: var(--warning); }
</style>
@endpush

@section('header-actions')
<button class="btn btn-primary btn-sm" onclick="openBulkSend()">
  <i class="fa-solid fa-paper-plane"></i> 일괄 청구 송신
</button>
@endsection

@php
  $nhisStatusLabels = [
    'pending'   => ['미청구',    'secondary'],
    'submitted' => ['청구완료',  'primary'],
    'approved'  => ['승인',      'success'],
    'rejected'  => ['거부',      'danger'],
  ];
  $curNhisStatus = request('nhis_status');
@endphp

@section('content')

{{-- DB 마이그레이션 미실행 안내 --}}
@if(!$faxTableExists)
<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;">
  <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
  <div>
    <strong>DB 마이그레이션 필요</strong> — e-Fax 기능을 사용하려면 phpMyAdmin에서
    <code>database/migrations/nhis_fax_logs_SQL.txt</code> 내용을 실행해주세요.
    (현재는 기본 청구 상태만 표시됩니다.)
  </div>
</div>
@endif

{{-- ── 요약 카드 ── --}}
<div class="summary-grid">
  <div class="summary-card gray">
    <div class="s-label">미청구 건수</div>
    <div class="s-value" style="color:var(--text-secondary);">{{ $counts['pending'] ?? 0 }}</div>
    <div class="s-sub">청구 대기 중인 주문</div>
  </div>
  <div class="summary-card blue">
    <div class="s-label">청구 완료</div>
    <div class="s-value" style="color:var(--primary);">{{ $counts['submitted'] ?? 0 }}</div>
    <div class="s-sub">결과 대기 중</div>
  </div>
  <div class="summary-card green">
    <div class="s-label">승인</div>
    <div class="s-value" style="color:var(--success);">{{ $counts['approved'] ?? 0 }}</div>
    <div class="s-sub">이번달 {{ number_format($monthlyApproved) }}원 환급</div>
  </div>
  <div class="summary-card red">
    <div class="s-label">거부</div>
    <div class="s-value" style="color:var(--danger);">{{ $counts['rejected'] ?? 0 }}</div>
    <div class="s-sub">재청구 필요</div>
  </div>
</div>

{{-- ── NHIS 상태 칩 ── --}}
{{-- Figma 282:53: h31 · r999 · pad 6/10 · 12px/700 · gap 4, 건수 배지 16×16 정원 10px/700 --}}
<div class="ds-chips">
  <a href="{{ route('nhis.index', array_merge(request()->except('nhis_status','page'), [])) }}"
     class="ds-chip {{ !$curNhisStatus ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $counts->sum() }}</span>
  </a>
  @foreach($nhisStatusLabels as $key => $statusInfo)
    <a href="{{ route('nhis.index', array_merge(request()->except('nhis_status','page'), ['nhis_status' => $key])) }}"
       class="ds-chip {{ $curNhisStatus === $key ? 'active' : '' }}">
      {{ $statusInfo[0] }}
      @if(($counts[$key] ?? 0) > 0)
        <span class="ds-chip-count">{{ $counts[$key] }}</span>
      @endif
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
{{-- Figma 282:53: 흰 카드(r12 · pad 12/16), 검색어 2열(295px) · 기간 2열(295px),
     버튼은 우측 하단에 초기화 → 검색 순서 --}}
<form method="GET" action="{{ route('nhis.index') }}" class="ds-filter-card">
  @if($curNhisStatus)<input type="hidden" name="nhis_status" value="{{ $curNhisStatus }}">@endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호 · 환자명">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" title="배송완료 시작일">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="form-control" title="배송완료 종료일">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('date_from') || request('date_to'))
      <a href="{{ route('nhis.index', $curNhisStatus ? ['nhis_status'=>$curNhisStatus] : []) }}"
         class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

{{-- Figma 282:53 — 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 패널 탭과 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
      <span class="ds-grid-sel">선택 <b id="nhisSelCount">0</b>건</span>
      <span class="ds-grid-meta">이번달 청구액: <b>{{ number_format($monthlyTotal) }}</b>원</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint"><i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 상세내용 탭에서 주문 상세를 확인합니다.</span>
      <button type="button" class="ds-btn" onclick="window.__nhisGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>

  <div class="ds-grid-card">
    {{-- 패널 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단 --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과</button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세 내용</button>
    </div>

<div id="pnlList">
{{-- ── NHIS 청구 목록 (wwGrid) ── --}}
<div id="nhisGrid"></div>
</div>{{-- /pnlList --}}

{{-- ── 상세내용 탭 (주문 상세 콘텐츠를 같은 페이지에 직접 주입) — 같은 카드 안 ── --}}
<div id="pnlDetail" style="display:none;padding:16px;">
  <div style="margin-bottom:12px;">
    <button type="button" class="ds-btn" onclick="pnlShow('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
  </div>
  <div id="pnlEmpty" class="pnl-empty">조회결과에서 행을 <b>더블클릭</b>하면 주문 상세가 여기에 표시됩니다.</div>
  <div id="pnlDetailContent"></div>
</div>
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- ══════════ 결과 등록 모달 ══════════ --}}
<div class="modal-overlay" id="resultModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-clipboard-check" style="color:var(--success);"></i> NHIS 처리 결과 등록</div>
      <button class="btn-close-modal" onclick="closeResultModal()">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="resultOrderId">
      <div style="background:var(--primary-light);border:1px solid var(--primary-accent);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);">청구 주문</div>
        <div id="resultOrderInfo" style="font-size:14px;font-weight:700;margin-top:2px;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">처리 결과 <span>*</span></label>
        <select id="resultType" class="form-control form-select" onchange="onResultTypeChange()">
          <option value="">선택하세요</option>
          <option value="approved">승인</option>
          <option value="partial">부분 승인</option>
          <option value="rejected">거부</option>
        </select>
      </div>
      <div class="form-group" id="approvedAmountGroup" style="display:none;">
        <label class="form-label">승인 금액 (원)</label>
        <input type="number" id="approvedAmount" class="form-control" placeholder="0" min="0">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
          청구 건보액: <span id="claimAmountRef" style="font-weight:700;"></span>원
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">공단 메시지 / 거부 사유</label>
        <textarea id="nhisMessage" class="form-control" rows="3" placeholder="공단 처리 메시지 또는 거부 사유 입력"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeResultModal()">취소</button>
      <button class="btn btn-primary" onclick="submitResult()">
        <i class="fa-solid fa-save"></i> 결과 등록
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 청구서 미리보기 모달 ══════════ --}}
<div class="modal-overlay" id="previewModal">
  <div class="modal-box" style="width:600px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-file-lines" style="color:var(--primary);"></i> 청구서 미리보기</div>
      <button class="btn-close-modal" onclick="document.getElementById('previewModal').classList.remove('open')">&times;</button>
    </div>
    <div class="modal-body">
      <div id="previewOrderLabel" style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:10px;"></div>
      <div class="preview-content" id="previewContent">로딩 중...</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="document.getElementById('previewModal').classList.remove('open')">닫기</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const DETAIL_BASE = @json(url('orders'));
  const grid = new wwGrid({
    el: document.getElementById('nhisGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, summary: false,
    toolbar: false,  // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일)
    footer: false,   // 시안에 하단 상태바가 없다 — 전체·선택 건수는 상단 결과바로 옮겼다
    columns: [
      { header: '주문번호',    name: 'order_no',      width: 120, sortable: true },
      { header: '환자명',      name: 'patient',       width: 90,  sortable: true },
      { header: '제품명',      name: 'product',       width: 170 },
      { header: '건보청구액',  name: 'nhis_amount',   width: 110, editor: 'number' },
      { header: '환자부담',    name: 'patient_copay', width: 100, editor: 'number' },
      { header: '주문상태',    name: 'status',        width: 90,  align: 'center', sortable: true },
      { header: 'NHIS상태',    name: 'nhis_status',   width: 90,  align: 'center', sortable: true },
      { header: 'e-Fax 상태',  name: 'efax',          width: 100, align: 'center' },
      { header: '청구일시',    name: 'submitted_at',  width: 130, sortable: true },
      { header: '승인/거부',   name: 'result',        width: 110, align: 'center' },
    ],
    data: @json($gridData),
  });
  window.__nhisGrid = grid;                    // 결과바 '엑셀 저장' 버튼이 이걸 부른다
  window.dsBindSelCount(grid, 'nhisSelCount'); // 결과바 '선택 N건' 표시를 그리드 선택에 연결

  // 패널 탭 전환(조회결과/상세내용)
  window.pnlShow = function (which) {
    document.getElementById('pnlList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pnlDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pnlBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pnlBtnDetail').classList.toggle('active', which === 'detail');
  };

  // 상세 콘텐츠(크롬 없는 프래그먼트)를 fetch로 가져와 같은 페이지에 직접 주입(iframe 미사용)
  window.pnlLoadDetail = async function (url) {
    const empty = document.getElementById('pnlEmpty');
    const cont  = document.getElementById('pnlDetailContent');
    empty.style.display = 'none';
    cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted);"><i class="bx bx-loader-alt bx-spin" style="font-size:22px;"></i><div style="margin-top:8px;">불러오는 중...</div></div>';
    window.pnlShow('detail');
    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      cont.innerHTML = await res.text();
      cont.querySelectorAll('script').forEach(function (old) {
        const s = document.createElement('script');
        if (old.src) s.src = old.src; else s.textContent = old.textContent;
        old.parentNode.replaceChild(s, old);
      });
    } catch (e) {
      cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--danger);">상세를 불러오지 못했습니다.</div>';
    }
  };

  // 행 더블클릭 → 상세내용 탭에 주문 상세를 인페이지로 표시(페이지 이동 없음)
  document.getElementById('nhisGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.id) return;
    window.pnlLoadDetail(DETAIL_BASE + '/' + row.id + '?partial=1');
  });
})();
</script>
<script>
// ── 선택 / 일괄 처리 ─────────────────────────────────────
function getCheckedIds() {
  return [...document.querySelectorAll('.order-check:checked')].map(c => parseInt(c.value));
}

function updateBulkBar() {
  const ids   = getCheckedIds();
  const bar   = document.getElementById('bulkBar');
  const count = document.getElementById('bulkCount');
  count.textContent = ids.length;
  bar.classList.toggle('show', ids.length > 0);

  // 전체선택 체크박스 상태
  const all = document.querySelectorAll('.order-check');
  document.getElementById('checkAll').indeterminate = ids.length > 0 && ids.length < all.length;
  document.getElementById('checkAll').checked = ids.length === all.length && all.length > 0;
}

function toggleAll(cb) {
  document.querySelectorAll('.order-check').forEach(c => c.checked = cb.checked);
  updateBulkBar();
}

function clearSelection() {
  document.querySelectorAll('.order-check').forEach(c => c.checked = false);
  document.getElementById('checkAll').checked = false;
  updateBulkBar();
}

function openBulkSend() {
  const pending = document.querySelectorAll('.order-check');
  if (pending.length === 0) { showToast('청구 대상 주문이 없습니다.', 'warning'); return; }
  // 전체 미청구 선택
  pending.forEach(c => c.checked = true);
  updateBulkBar();
  showToast(`${pending.length}건이 선택되었습니다. 일괄 청구 버튼을 클릭하세요.`, 'info', 3000);
}

async function sendBulkSelected() {
  const ids = getCheckedIds();
  if (ids.length === 0) { showToast('선택된 주문이 없습니다.', 'warning'); return; }
  if (!await ceConfirm(`${ids.length}건을 NHIS e-Fax로 일괄 청구하시겠습니까?`, { confirmText: '일괄 청구' })) return;

  const btn = event.currentTarget;
  BtnState.loading(btn, '전송 중...');

  const res = await apiRequest(BASE_URL + '/nhis/bulk-send', 'POST', { order_ids: ids });
  if (res.success || res.success_count > 0) {
    BtnState.success(btn, '전송 완료');
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 1200);
  } else {
    BtnState.error(btn, '전송 실패');
  }
  if (res.failed?.length > 0) {
    res.failed.forEach(f => showToast(`${f.order_number}: ${f.error}`, 'danger', 7000));
  }
}

// ── 단건 e-Fax 청구 ─────────────────────────────────────
async function sendFax(orderId, orderNumber) {
  if (!await ceConfirm(`${orderNumber} 주문을 NHIS e-Fax로 청구하시겠습니까?`, { confirmText: '청구' })) return;

  const btn = event.currentTarget;
  BtnState.loading(btn, '청구 중...');

  const res = await apiRequest(`${BASE_URL}/nhis/${orderId}/send-fax`, 'POST');

  if (res.success) {
    BtnState.success(btn, '전송 완료');
    showToast(`청구 전송 완료 (참조번호: ${res.reference_no})`, 'success', 5000);
    setTimeout(() => location.reload(), 1500);
  } else {
    BtnState.error(btn, '전송 실패');
    if (res.message) showToast(res.message, 'danger');
  }
}

// ── 결과 등록 모달 ────────────────────────────────────────
let _resultOrderId = null;

function openResultModal(orderId, orderNumber, nhisAmount) {
  _resultOrderId = orderId;
  document.getElementById('resultOrderId').value   = orderId;
  document.getElementById('resultOrderInfo').textContent = orderNumber;
  document.getElementById('claimAmountRef').textContent  = nhisAmount.toLocaleString('ko-KR');
  document.getElementById('resultType').value    = '';
  document.getElementById('approvedAmount').value = '';
  document.getElementById('nhisMessage').value   = '';
  document.getElementById('approvedAmountGroup').style.display = 'none';
  document.getElementById('resultModal').classList.add('open');
}

function closeResultModal() {
  document.getElementById('resultModal').classList.remove('open');
  _resultOrderId = null;
}

function onResultTypeChange() {
  const val = document.getElementById('resultType').value;
  document.getElementById('approvedAmountGroup').style.display =
    (val === 'approved' || val === 'partial') ? 'block' : 'none';
}

async function submitResult() {
  if (!_resultOrderId) return;
  const resultType = document.getElementById('resultType').value;
  if (!resultType) { showToast('처리 결과를 선택해주세요.', 'warning'); return; }

  const data = {
    nhis_result:     resultType,
    approved_amount: document.getElementById('approvedAmount').value || null,
    nhis_message:    document.getElementById('nhisMessage').value || null,
  };

  const res = await apiRequest(`${BASE_URL}/nhis/${_resultOrderId}/record-result`, 'POST', data);
  if (res.success) {
    showToast('결과가 등록되었습니다.', 'success');
    closeResultModal();
    setTimeout(() => location.reload(), 800);
  }
}

// ── 청구서 미리보기 ───────────────────────────────────────
async function previewDoc(orderId, orderNumber) {
  document.getElementById('previewOrderLabel').textContent = `주문번호: ${orderNumber}`;
  document.getElementById('previewContent').textContent   = '로딩 중...';
  document.getElementById('previewModal').classList.add('open');

  try {
    const res = await fetch(`${BASE_URL}/nhis/${orderId}/preview`, {
      headers: { 'Accept': 'text/plain', 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
    const text = await res.text();
    document.getElementById('previewContent').textContent = text;
  } catch(e) {
    document.getElementById('previewContent').textContent = '미리보기 로딩 실패';
  }
}

// ── 팩스 이력 토글 ────────────────────────────────────────
const _loadedLogs = new Set();

async function toggleFaxLog(orderId) {
  const panel = document.getElementById(`logPanel-${orderId}`);
  const isOpen = panel.classList.contains('open');

  if (isOpen) { panel.classList.remove('open'); return; }

  if (!_loadedLogs.has(orderId)) {
    panel.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px;">로딩 중...</div>';
    panel.classList.add('open');
    const res = await apiRequest(`${BASE_URL}/nhis/${orderId}/fax-logs`, 'GET');
    if (res.success && res.logs.length > 0) {
      const nhisResultBadgeMap = {
        pending:  ['결과대기', 'secondary'],
        approved: ['승인', 'success'],
        rejected: ['거부', 'danger'],
        partial:  ['부분승인', 'warning'],
      };
      panel.innerHTML = res.logs.map(log => {
        const [rLbl, rBadge] = nhisResultBadgeMap[log.nhis_result] ?? [log.nhis_result, 'secondary'];
        return `<div class="log-item">
          <span class="efax-status-badge efax-${log.status}">${log.status_label}</span>
          <span style="color:var(--text-muted);">${log.sent_at ?? '-'}</span>
          <span style="font-size:11px;">${log.reference_no ?? ''}</span>
          <span class="badge badge-${rBadge}">${rLbl}</span>
          ${log.nhis_result_at ? `<span style="font-size:11px;color:var(--text-muted);">${log.nhis_result_at}</span>` : ''}
          ${log.approved_amount ? `<span style="font-weight:700;color:var(--success);">${Number(log.approved_amount).toLocaleString('ko-KR')}원</span>` : ''}
          ${log.error_message ? `<span style="font-size:11px;color:var(--danger);">${log.error_message}</span>` : ''}
        </div>`;
      }).join('');
      _loadedLogs.add(orderId);
    } else {
      panel.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px;">팩스 발송 이력이 없습니다.</div>';
    }
  } else {
    panel.classList.add('open');
  }
}

// 모달 외부 클릭 시 닫기
['resultModal','previewModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '청구 검색 필터', body: '기간, 상태, 환자명으로 NHIS 청구 대상을 조회합니다.' },
  { selector: '#nhisGrid', title: '청구 목록', body: '주문별 청구 현황을 보여줍니다. 상태가 <b>청구 대기</b>인 항목을 팩스로 청구하세요.' },
  { selector: '.btn-primary, [onclick*="Bulk"], [onclick*="bulk"]', title: '일괄 청구 송신', body: '여러 건을 한 번에 e-Fax로 전송합니다. 전송 후 상태가 <b>팩스 전송됨</b>으로 변경됩니다.' },
];
</script>
@endpush
