{{-- resources/views/prescriptions/list.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 목록')
@section('page-title', '처방전 목록')
@section('breadcrumb', '홈 / 처방전 목록')

@section('help-title', '처방전 목록 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>모바일/웹에서 업로드된 처방전을 조회하고 검수·주문 연계를 관리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">상태 탭 설명</div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-error"></i></div>
    <div class="help-item-text"><strong>검수 필요</strong>OCR 결과를 사람이 직접 확인해야 하는 처방전입니다. 우선 처리하세요.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon info"><i class="bx bx-scan"></i></div>
    <div class="help-item-text"><strong>OCR 처리중</strong>자동 인식이 진행 중입니다. 잠시 후 새로고침하세요.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-check-circle"></i></div>
    <div class="help-item-text"><strong>검수 완료</strong>확인된 처방전입니다. 주문 연계 대기 상태입니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-cart-alt"></i></div>
    <div class="help-item-text"><strong>주문 미등록</strong>검수는 완료됐지만 Withworks 주문이 아직 없는 건입니다.</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 기능</div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-user-check"></i></div>
    <div class="help-item-text"><strong>담당자 지정</strong>각 행의 담당 셀렉트박스에서 즉시 변경 가능합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link-external"></i></div>
    <div class="help-item-text"><strong>주문/SO 번호</strong>Withworks 판매주문번호(SO)가 연계된 경우 파란 모노스페이스 폰트로 표시됩니다.</div>
  </div>
</div>
@endsection

@section('header-actions')
  @perm('prescription-upload', 'create')
  <a href="{{ route('prescriptions.upload') }}" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-upload"></i> 처방전 업로드
  </a>
  @endperm
@endsection

@push('styles')
<style>
  .filter-bar .form-control { height: 32px; font-size: 13px; }
  .filter-bar .btn { height: 32px; white-space: nowrap; }

  /* ── Vuexy pill status tabs ── */
  .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .status-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .status-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
  .status-tab .tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 16px; height: 16px; padding: 0;
    border-radius: 999px; font-size: 10px; font-weight: 700; line-height: 1;
    background: var(--gray-0); color: var(--primary);
  }
  .status-tab:not(.active) .tab-count { background: var(--border-light); color: var(--text-muted); }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }
  .rx-date { font-size: 11px; color: var(--text-muted); }
  .ocr-bar { display: flex; align-items: center; gap: 6px; }
  .ocr-bar-track { flex: 1; height: 5px; background: var(--border); border-radius: 3px; min-width: 40px; overflow: hidden; }
  .ocr-bar-fill { height: 100%; border-radius: 3px; }
  .ocr-pct { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
  .table-actions { display: flex; gap: 4px; }
  .empty-state { text-align: center; padding: 56px 24px; color: var(--text-muted); }
  .empty-state i { font-size: 44px; margin-bottom: 12px; display: block; opacity: .3; }
  .empty-state p { font-size: 13px; margin: 0; }

  /* 담당자 인라인 셀렉트 */
  .assign-select {
    font-size: 12px; padding: 3px 24px 3px 10px; height: 28px;
    border: 1.5px dashed var(--border); border-radius: 20px;
    background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23a5a3ae'/%3E%3C/svg%3E") no-repeat right 8px center;
    background-size: 8px 5px;
    color: var(--text-muted);
    cursor: pointer; max-width: 120px;
    appearance: none; -webkit-appearance: none;
    transition: border-color .15s, background-color .15s, color .15s, box-shadow .15s;
  }
  .assign-select.assigned {
    border-style: solid; border-color: var(--primary);
    background-color: var(--primary-light);
    color: var(--primary); font-weight: 600;
  }
  .assign-select:hover {
    border-color: var(--primary); border-style: solid;
    background-color: var(--primary-light); color: var(--primary);
    box-shadow: 0 0 0 3px rgba(40,121,139,.12);
  }
  .assign-select:focus { outline: none; border-color: var(--primary); border-style: solid;
    box-shadow: 0 0 0 3px rgba(40,121,139,.2); }
  .assign-select.saving { opacity: .5; pointer-events: none; }
</style>
@endpush

@section('content')

  {{-- 상태 탭 --}}
  <div class="ds-chips">
    <a href="{{ route('prescriptions.index') }}"
       class="ds-chip {{ !request('status') ? 'active' : '' }}">
      전체 <span class="ds-chip-count">{{ $statusCounts['all'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'review_needed'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'review_needed' ? 'active' : '' }}"
       style="{{ !request('status') || request('status') === 'review_needed' ? '' : '' }}">
      검수 필요 <span class="ds-chip-count">{{ $statusCounts['review_needed'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'ocr_processing'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'ocr_processing' ? 'active' : '' }}">
      OCR 처리중 <span class="ds-chip-count">{{ $statusCounts['ocr_processing'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'approved'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'approved' ? 'active' : '' }}">
      검수 완료 <span class="ds-chip-count">{{ $statusCounts['approved'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'no_order'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'no_order' ? 'active' : '' }}">
      주문 미등록 <span class="ds-chip-count">{{ $statusCounts['no_order'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'ordered'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'ordered' ? 'active' : '' }}">
      주문 완료 <span class="ds-chip-count">{{ $statusCounts['ordered'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'rejected'] + request()->except('status', 'page')) }}"
       class="ds-chip {{ request('status') === 'rejected' ? 'active' : '' }}">
      반려 <span class="ds-chip-count">{{ $statusCounts['rejected'] }}</span>
    </a>
  </div>

  {{-- 검색 필터 — Figma 128:1744: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래 --}}
  <form method="GET" action="{{ route('prescriptions.index') }}" class="ds-filter-card">
    @if(request('status'))
      <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <div class="ds-filter-fields">
      {{-- 검색어 143px(1열) · 기간 298px(2열) — 시안 실측 --}}
      <div class="ds-filter-field">
        <label class="ds-field-label">검색어</label>
        <input type="text" name="search" class="form-control"
               placeholder="처방번호 · 환자명 · 병원명" value="{{ request('search') }}">
      </div>
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">기간</label>
        <div class="ds-field-range">
          <input type="date" name="date_from" class="form-control"
                 value="{{ request('date_from', now()->subDays(60)->format('Y-m-d')) }}">
          <span class="ds-field-sep">~</span>
          <input type="date" name="date_to" class="form-control"
                 value="{{ request('date_to', now()->format('Y-m-d')) }}">
        </div>
      </div>
      <div class="ds-filter-field">
        <label class="ds-field-label">표시 건수</label>
        <select name="per_page" class="form-control form-select" onchange="this.form.submit()">
          @foreach([10, 20, 50, 100] as $n)
            <option value="{{ $n }}" {{ (int)request('per_page', 10) === $n ? 'selected' : '' }}>
              {{ $n }}개씩
            </option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="ds-filter-actions">
      @if(request()->hasAny(['search', 'date_from', 'date_to']))
        <a href="{{ route('prescriptions.index', request()->only('status', 'per_page')) }}" class="ds-btn">초기화</a>
      @endif
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    </div>
  </form>

  {{-- Figma 128:1744 — 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 그리드 --}}
  <div class="ds-grid-section">
    <div class="ds-grid-bar">
      <div class="ds-grid-bar-left">
        <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
        <span class="ds-grid-sel">선택 <b id="rxSelCount">0</b>건</span>
      </div>
      <div class="ds-grid-bar-right">
        <span class="ds-grid-hint">행을 <b>더블클릭</b>하면 <b>처방전 검수 화면이 새 탭</b>으로 열립니다. (체크 후 <b>선택 상세</b>도 동일)</span>
        <button type="button" class="ds-btn" onclick="window.__rxGrid?.downloadExcel()">엑셀 저장</button>
        <button type="button" class="ds-btn" onclick="prescriptionViewDetail()">선택 상세</button>
      </div>
    </div>
    <div class="ds-grid-card">
      <div id="rxGrid"></div>
    </div>
  </div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  {
    selector: '.status-tabs',
    title: '상태 탭',
    body: '처방전을 상태별로 필터링합니다. <b>검수 필요</b> 탭을 먼저 확인하여 처리 대기 중인 처방전을 처리하세요.'
  },
  {
    selector: '.filter-bar',
    title: '검색 및 필터',
    body: '환자명, 처방번호, 병원명으로 검색하거나 날짜 범위를 지정해 조회할 수 있습니다.'
  },
  {
    selector: '#rxGrid',
    title: '목록 그리드',
    body: '행을 <b>더블클릭</b>하면 처방전 검수 화면이 <b>새 탭</b>으로 열립니다(목록 탭은 그대로 유지). <b>판매유형</b>과 <b>Withworks SO</b> 컬럼에서 주문 연계 상태를 한눈에 확인할 수 있고, 컬럼 헤더를 클릭해 정렬할 수 있습니다.'
  },
  {
    selector: 'button[onclick="prescriptionViewDetail()"]',
    title: '선택 상세',
    body: '행을 체크한 뒤 <b>선택 상세</b> 버튼을 눌러도 동일하게 검수 화면이 새 탭으로 열립니다.'
  },
];

</script>
<script>
(function () {
  const DETAIL_BASE = @json(url('prescriptions'));
  const grid = new wwGrid({
    el: document.getElementById('rxGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 상단 결과바에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: [
      { header: '처방번호',      name: 'rx_number',  width: 150, sortable: true },
      { header: '출처',          name: 'source',     width: 70,  align: 'center', sortable: true },
      { header: '환자명',        name: 'patient',    width: 100, sortable: true },
      { header: '병원',          name: 'hospital',   width: 150, sortable: true },
      { header: '발행일',        name: 'issued',     width: 100, align: 'center', sortable: true },
      { header: '상태',          name: 'status',     width: 90,  align: 'center', sortable: true },
      { header: '판매유형',      name: 'so_type',    width: 90,  align: 'center', sortable: true },
      { header: '주문번호',      name: 'order_no',   width: 140, sortable: true },
      { header: 'Withworks SO',  name: 'so_no',      width: 130, sortable: true },
      { header: '담당',          name: 'assignee',   width: 90,  align: 'center', sortable: true },
      { header: '접수일시',      name: 'created',    width: 130, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  // 결과바의 '엑셀 저장' 버튼이 부를 수 있게 인스턴스를 노출한다(그리드 내장 툴바 대체).
  window.__rxGrid = grid;

  /* 검수 화면을 '새 탭'으로 연다.
     워크스페이스 안에서는 목록 탭을 그대로 두고 별도 탭이 열리고(ceOpenTab),
     단독 페이지로 열려 있으면 브라우저 새 탭으로 대체된다. */
  function openReviewTab(rxNumber) {
    const url = DETAIL_BASE + '/' + encodeURIComponent(rxNumber);
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(url, '처방전 관리 - ' + rxNumber, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  }

  // 행 더블클릭 → 처방전 검수 화면을 새 탭으로 열기(목록은 그대로 유지)
  document.getElementById('rxGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.rx_number) return;
    window.getSelection()?.removeAllRanges();   // 더블클릭 텍스트 선택 해제
    openReviewTab(row.rx_number);
  });

  window.prescriptionViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    openReviewTab(c[0].rx_number);
  };
})();
</script>
@endpush
