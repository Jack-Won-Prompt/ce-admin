{{-- resources/views/nhis/index.blade.php --}}
@extends('layouts.app')

@section('title', '청구 관리')
@section('page-title', '청구 관리')
{{-- 시안 282:53 빵부스러기는 '홈'(x367) · '-'(x386) · 화면명(x400) 으로 구분자가 하이픈이다.
     마지막 조각의 낱말('청구')은 DIFF 가 짚지 않아 그대로 두고 구분자만 맞춘다. --}}
@section('breadcrumb', '홈 - 청구')

@section('help-title', '청구 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>건강보험공단 요양비 청구 현황을 관리하는 화면입니다. 청구 자체는 요양기관정보마당에 직접 입력·업로드합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">청구 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">청구 대기</span>
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
    <div class="help-item-text">요양기관정보마당에서 청구 입력·서류 업로드</div>
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

@php
  $nhisStatusLabels = [
    'pending'   => ['미청구',    'secondary'],
    'submitted' => ['청구완료',  'primary'],
    'approved'  => ['승인',      'success'],
    'rejected'  => ['거부',      'danger'],
  ];
  $curNhisStatus = request('nhis_status');

  /* 시안 282:53 실측 — 괄호 설명은 칩 밖 둘째 줄이 아니라 칩 라벨 안에 붙은 한 덩어리다.
       미청구(청구 대기 중)      98×19 · 칩 138×31
       청구완료(결과 대기 중)   109×19 · 칩 149×31
       승인(이번달 0원 환급)    106×19 · 칩 146×31
       거부(재청구 필요)         85×19 · 칩 125×31   (모두 12/700 #83888F)
     지표 카드 넉 장은 시안에 없지만 개발이 넣은 표시라 지우지 않고 그대로 둔다. */
  $nhisStatusNotes = [
    'pending'   => '청구 대기 중',
    'submitted' => '결과 대기 중',
    'approved'  => '이번달 ' . number_format($monthlyApproved) . '원 환급',
    'rejected'  => '재청구 필요',
  ];
@endphp

@section('content')

{{-- ── 요약 카드 ── --}}
<div class="summary-grid">
  <div class="summary-card gray">
    <div class="s-label">미청구 건수</div>
    <div class="s-value" style="color:var(--text-secondary);">{{ $counts['pending'] ?? 0 }}</div>
    {{-- 미청구 중에서도 지금 바로 할 수 있는 것이 몇 건인지가 실제로 궁금한 값이다 --}}
    <div class="s-sub">
      자료 완비 <b style="color:var(--success);">{{ $readyCount }}</b>건 ·
      대기 {{ max(0, ($counts['pending'] ?? 0) - $readyCount) }}건
    </div>
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

{{-- ── 청구 상태 칩 ── --}}
{{-- Figma 282:53: h31 · r999 · pad 6/10 · 12px/700 · gap 4, 건수 배지 16×16 정원 10px/700 --}}
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}


{{-- ── 검색 필터 ── --}}
{{-- Figma 282:53: 흰 카드(r12 · pad 12/16), 검색어 2열(295px) · 기간 2열(295px),
     버튼은 우측 하단에 초기화 → 검색 순서 --}}
<form method="GET" action="{{ route('nhis.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">청구 상태</label>
      <select name="nhis_status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $counts->sum() }})</option>
        @foreach($nhisStatusLabels as $key => $statusInfo)
          <option value="{{ $key }}" {{ $curNhisStatus === $key ? 'selected' : '' }}>
            {{ $statusInfo[0] }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호 · 이름">
    </div>
    {{-- 2차 요청(R2-09) — 주민번호는 마스킹된 값에서 부분검색한다. 평문은 화면에 내려보내지 않는다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">주민등록번호</label>
      <input type="text" name="rrn" value="{{ request('rrn') }}" class="form-control"
             placeholder="앞 6자리" maxlength="14" inputmode="numeric">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">현금영수증</label>
      <select name="cash_receipt" class="form-control form-select">
        <option value="">전체</option>
        <option value="issued"     @selected(request('cash_receipt') === 'issued')>발행</option>
        <option value="not_issued" @selected(request('cash_receipt') === 'not_issued')>미발행</option>
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" title="출고일 시작">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="form-control" title="출고일 종료">
      </div>
    </div>
    {{-- 위쪽 칩은 청구 진행 상태 하나만 둔다. 나머지 갈래를 칩으로 늘어놓으면
         한 줄에 네 가지 기준이 섞여 무엇이 켜져 있는지 알 수 없었다.
         한 묶음 안에 두어야 칸 너비가 고르게 나뉜다 — 묶음을 나누면 라벨이 눌린다. --}}
    <div class="ds-filter-field">
      {{-- 신환은 공단 등록이 먼저다. 섞어 두면 청구부터 하려다 반려된다. --}}
      <label class="ds-field-label">신환/구환</label>
      <select name="patient_type" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(\App\Models\Order::PATIENT_TYPE_LABELS as $v => $label)
          <option value="{{ $v }}" {{ request('patient_type') === (string) $v ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">청구처</label>
      <select name="agency" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach([\App\Support\ClaimAgency::NHIS, \App\Support\ClaimAgency::LOCAL] as $v)
          <option value="{{ $v }}" {{ request('agency') === (string) $v ? 'selected' : '' }}>
            {{ \App\Support\ClaimAgency::LABELS[$v] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      {{-- 자료가 갖춰진 것만 따로 볼 수 있어야 청구를 몰아서 한다 --}}
      <label class="ds-field-label">자료</label>
      <select name="ready" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        <option value="y" {{ request('ready') === 'y' ? 'selected' : '' }}>완비 ({{ $readyCount }})</option>
        <option value="n" {{ request('ready') === 'n' ? 'selected' : '' }}>부족</option>
      </select>
    </div>
  </div>

  <div class="ds-filter-actions">
    {{-- 시안 282:53 Frame 48101589 — 초기화(60×32)는 검색 왼쪽에 늘 있다.
         상태 칩만 유지한 채 같은 라우트로 되돌아가는 링크라 조건만 걷었다. --}}
    <a href="{{ route('nhis.index', $curNhisStatus ? ['nhis_status'=>$curNhisStatus] : []) }}"
       class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__nhisGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

{{-- Figma 282:53 — 흰 카드(r12) 안에 패널 탭과 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 패널 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단 --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(<b>{{ number_format($total) }}</b>)</span></button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세 내용</button>
    </div>

<div id="pnlList">
{{-- ── 청구 목록 (wwGrid) ── --}}
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
      <div class="modal-title"><i class="fa-solid fa-clipboard-check" style="color:var(--success);"></i> 청구 처리 결과 등록</div>
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
          청구 청구액: <span id="claimAmountRef" style="font-weight:700;"></span>원
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
@include('nhis.assist._button')
<script>
(function () {
  const DETAIL_BASE = @json(url('orders'));
  const grid = new wwGrid({
    el: document.getElementById('nhisGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, summary: true,   /* 수량·금액은 맨 아래에서 합계를 낸다 */
    toolbar: false,  // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일)
    footer: false,   // 시안에 하단 상태바가 없다 — 전체·선택 건수는 상단 결과바로 옮겼다
    columns: [
      { header: '주문번호',    name: 'order_no',      width: 120, sortable: true },
      { header: '이름',      name: 'patient',       width: 90,  sortable: true },
      { header: '신환/구환',   name: 'patient_type',  width: 90,  align: 'center', sortable: true },
      { header: '제품명',      name: 'product',       width: 170 },
      { header: '청구액',  name: 'nhis_amount',   width: 110, editor: 'number' },
      { header: '본인부담금',  name: 'patient_copay', width: 100, editor: 'number' },
      { header: '주문상태',    name: 'status',        width: 90,  align: 'center', sortable: true },
      { header: '청구상태',    name: 'nhis_status',   width: 90,  align: 'center', sortable: true },
      {
        // 공단이냐 지자체냐 — 서류도 보내는 법도 다르다
        header: '청구처', name: 'agency', width: 130, align: 'center', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '건강보험공단';
          if (v && v !== '건강보험공단') { s.style.cssText = 'color:var(--warning,#b45309);font-weight:700;'; }
          return s;
        },
      },
      { header: '청구일시',    name: 'submitted_at',  width: 130, sortable: true },
      {
        // 무엇이 빠졌는지까지 보여 준다. 「안 됨」만 알면 다시 열어 봐야 한다.
        header: '청구 자료', name: 'claim_missing', width: 200, sortable: true,
        renderer: (v, row) => {
          const s = document.createElement('span');
          if (row.claim_na) {
            s.textContent = v || '공단 청구 건 아님';
            s.style.cssText = 'color:var(--text-muted);font-size:11px;';
          } else if (row.claim_ready) {
            s.textContent = '완비';
            s.style.cssText = 'color:var(--success);font-weight:700;';
          } else {
            s.textContent = v || '확인 전';
            s.style.cssText = 'color:var(--danger);font-size:11px;';
            s.title = v || '';
          }
          return s;
        },
      },
      { header: '승인/거부',   name: 'result',        width: 110, align: 'center' },
      {
        // 공단 사이트에 옮겨 적는 것을 돕는 창. 값을 늘어놓고 항목마다 복사 버튼을 준다.
        header: '공단 청구', name: 'nhis_assist', width: 100, sortable: false, exportable: false,
        renderer: (v, row) => nhisAssistBtn(row.id),
      },
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

/* 청구를 여기서 보내던 기능(단건·일괄 e-Fax)은 걷어냈다.
   공단 요양비 청구는 요양기관정보마당에 직접 입력하고 서류를 업로드한다. */

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


// 모달 외부 클릭 시 닫기
['resultModal','previewModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '청구 검색 필터', body: '기간, 상태, 이름으로 청구 대상을 조회합니다.' },
  { selector: '#nhisGrid', title: '청구 목록', body: '주문별 청구 현황을 보여줍니다. 상태가 <b>청구 대기</b>인 항목을 공단 사이트에서 청구하고, 결과를 여기에 기록합니다.' },
];
</script>
@endpush
