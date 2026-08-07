{{-- resources/views/prescription-consents/index.blade.php --}}
@extends('layouts.app')

@section('title', '위임장 서명')
@section('page-title', '위임장 서명')
@section('breadcrumb', '홈 / 서류ㆍ동의 / 위임장 서명')

@section('content')

@php $curStatus = request('status'); @endphp

{{-- ── 상태 탭 ── --}}
<div class="ds-chips">
  <a href="{{ route('prescription-consents.index', request()->except('status', 'page')) }}"
     class="ds-chip {{ !$curStatus ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $statusCounts->sum() }}</span>
  </a>
  @foreach($statuses as $key => $label)
    <a href="{{ route('prescription-consents.index', array_merge(request()->except('status','page'), ['status' => $key])) }}"
       class="ds-chip {{ $curStatus === $key ? 'active' : '' }}">
      {{ $label }} <span class="ds-chip-count">{{ $statusCounts[$key] ?? 0 }}</span>
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('prescription-consents.index') }}" class="ds-filter-card">
  @if($curStatus)
    <input type="hidden" name="status" value="{{ $curStatus }}">
  @endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="이름 · 전화번호 · 처방번호">
    </div>
    <div class="ds-filter-field span-4">
      <label class="ds-field-label">기간 (서명일)</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">서명 여부</label>
      <select name="signed_only" class="form-control">
        <option value="">전체</option>
        <option value="1" {{ request('signed_only') ? 'selected' : '' }}>서명 있는 건만</option>
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('date_from') || request('date_to') || request('signed_only'))
      <a href="{{ route('prescription-consents.index', array_filter(['status' => $curStatus])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
      <span class="ds-grid-hint">행을 <b>더블클릭</b>하면 해당 처방전이 새 탭으로 열립니다.</span>
    </div>
  </div>

  {{-- 서류 버튼은 그리드 밖에 둔다 — wwGrid 셀은 글자만 담는다(HTML 을 넣을 수 없다).
       한 행을 체크하고 누르는 방식은 처방전 목록과 같다. --}}
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
    <button type="button" class="btn btn-outline btn-sm" onclick="pcDownload('png_url')">
      <i class="bx bx-image-alt"></i> 서명 PNG
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="pcDownload('consent_pdf')">
      <i class="bx bx-file"></i> 위임동의서 PDF
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="pcDownload('delegation_pdf')">
      <i class="bx bx-file-blank"></i> 요양비 위임장 PDF
    </button>
    <span style="font-size:12px;color:var(--text-muted);">
      <i class="bx bx-info-circle"></i> 받을 행을 <b>하나</b> 체크한 뒤 누르세요.
    </span>
  </div>

  <div id="consentGrid"></div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('consentGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '상태',      name: 'status',    width: 100, sortable: true, align: 'center' },
      { header: '서명자',    name: 'name',      width: 110, sortable: true },
      { header: '전화번호',  name: 'mobile',    width: 140, sortable: true },
      { header: '처방번호',  name: 'rx_number', width: 160, sortable: true },
      { header: '서명일시',  name: 'signed_at', width: 150, sortable: true },
      { header: '본인확인',  name: 'identity',  width: 110, align: 'center' },
      { header: '서명',      name: 'signature', width: 70,  align: 'center' },
      { header: '요청일시',  name: 'requested', width: 150, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__consentGrid = grid;

  const LABEL = {
    png_url:        '서명 PNG',
    consent_pdf:    '위임동의서 PDF',
    delegation_pdf: '요양비 위임장 PDF',
  };

  /* 체크한 한 행의 서류를 받는다. 없는 서류는 왜 없는지 알려 준다 —
     '동의 완료'가 아니거나, 서명이 없거나, 아직 만들어지지 않은 경우다. */
  window.pcDownload = function (key) {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('받을 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    const url = c[0][key];
    if (!url) { showToast(LABEL[key] + ' 이(가) 없는 건입니다.', 'warning'); return; }
    window.open(url, '_blank', 'noopener');
  };

  // 행 더블클릭 → 해당 처방전을 새 탭으로
  document.getElementById('consentGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.rx_number) return;
    window.getSelection()?.removeAllRanges();
    const url = @json(url('/prescriptions')) + '/' + encodeURIComponent(row.rx_number);
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(url, '처방전 관리 - ' + row.rx_number, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  });
})();
</script>
@endpush
