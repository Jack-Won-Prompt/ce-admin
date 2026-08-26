{{-- resources/views/prescriptions/list.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 목록')
@section('page-title', '처방전 목록')
{{-- 시안 128:1744 빵부스러기는 '홈 - 처방전 목록' 이다(구분자 하이픈). --}}
@section('breadcrumb', '홈 - 처방전 목록')

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
    <div class="help-item-text"><strong>검수 필요</strong>담당자가 적어야 하는 처방전입니다. 우선 처리하세요.</div>
  </div>
  {{-- 「OCR 처리중」 안내는 두지 않는다 — OCR 을 쓰지 않는다(담당자 수기 입력) --}}
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
    <div class="help-item-text"><strong>담당자 지정</strong>각 행의 검수 담당자 셀렉트박스에서 즉시 변경 가능합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link-external"></i></div>
    <div class="help-item-text"><strong>주문/SO 번호</strong>Withworks 판매주문번호(SO)가 연계된 경우 파란 모노스페이스 폰트로 표시됩니다.</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .filter-bar .form-control { height: 32px; font-size: 13px; }
  .filter-bar .btn { height: 32px; white-space: nowrap; }

  /* ── Vuexy pill status tabs ── */
  .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 999px; font-size: 12px; font-weight: 500;
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

  {{-- 상태는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
       고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}
  @php $curAcc = request('acc_type'); @endphp

  {{-- 검색 필터 — Figma 128:1744: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래 --}}
  <form method="GET" action="{{ route('prescriptions.index') }}" class="ds-filter-card">
    <div class="ds-filter-fields">
      {{-- 검색어 143px(1열) · 기간 298px(2열) — 시안 실측 --}}
      <div class="ds-filter-field">
        <label class="ds-field-label">상태</label>
        <select name="status" class="form-control form-select" onchange="this.form.submit()">
          @php
            $curSt = request('status');
            /* 흐름대로 늘어놓는다 — 검수 필요 → 검수 요청 → 검수 완료.
               「OCR 처리중」은 더 생기지 않아 고르는 자리에서 걷었다. */
            $sts = ['' => '전체', 'review_needed' => '검수 필요', 'review_requested' => '검수 요청',
                    'approved' => '검수 완료', 'no_order' => '주문 미등록', 'ordered' => '주문 완료',
                    'rejected' => '반려'];
          @endphp
          @foreach($sts as $code => $label)
            @php $cnt = $statusCounts[$code === '' ? 'all' : $code] ?? 0; @endphp
            <option value="{{ $code }}" {{ (string) $curSt === (string) $code ? 'selected' : '' }}>
              {{ $label }}@if($cnt > 0) ({{ $cnt }})@endif
            </option>
          @endforeach
        </select>
      </div>
      <div class="ds-filter-field">
        <label class="ds-field-label">검색어</label>
        <input type="text" name="search" class="form-control"
               placeholder="처방번호ㆍ이름ㆍ병원명" value="{{ request('search') }}">
      </div>
      {{-- 두 칸(298)에서는 날짜가 「2026-06-…」로 잘렸다 — 달력 아이콘까지 서야 해서
           한 칸이 150 은 있어야 한다. 세 칸을 준다(이 화면은 아홉 칸 중 여섯만 쓴다). --}}
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
        {{-- 처방 유형 — 원내·원외·처방외. 위쪽 칩은 진행 상태 하나만 두고,
             나머지 갈래는 여기서 고른다. 칩이 두 줄이면 무엇이 무엇인지 헷갈린다. --}}
        <label class="ds-field-label">처방유형</label>
        <select name="acc_type" class="form-control form-select" onchange="this.form.submit()">
          <option value="">전체</option>
          @foreach(\App\Models\Prescription::ACC_TYPES as $code => $label)
            {{-- 배열 키가 정수로 바뀌므로 문자열로 되돌려 견준다 --}}
            <option value="{{ $code }}" {{ $curAcc === (string) $code ? 'selected' : '' }}>
              {{ $label }}@if(($accCounts[$code] ?? 0) > 0) ({{ $accCounts[$code] }})@endif
            </option>
          @endforeach
        </select>
      </div>
      {{-- 「표시 건수」 칸은 두지 않는다. 목록은 wwGrid 가 한 번에 다 받아 그리고
           (컨트롤러가 ->get() 으로 통째로 넘긴다) 페이지를 나누지 않는다 —
           이 칸은 아무 일도 하지 않으면서 「10개씩」이라 적어 거짓을 말하고 있었다. --}}
    </div>
    <div class="ds-filter-actions">
      {{-- 초기화 — 시안 128:1744 은 검색 왼쪽에 늘 세워 둔다. 검색 조건이 있을 때만
           내보내던 조건을 걷었다. 링크는 그대로 이 화면의 라우트로 되돌아간다
           (지금 보고 있는 상태 칩·표시 건수는 유지). --}}
      <a href="{{ route('prescriptions.index', request()->only('status')) }}" class="ds-btn">초기화</a>
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
      {{-- 찾는 자리 옆에 둔다. 네비바에 두면 탭 안에서 사라진다.
           올릴 권한이 없는 사람에게는 보이지 않아야 하므로 @perm 을 그대로 둔다. --}}
      @perm('prescription-upload', 'create')
      <a href="{{ route('prescriptions.upload') }}" class="ds-btn ds-btn-primary"
         data-ce-tab="처방자료 업로드" data-ce-icon="bx-upload">
        <i class="fa-solid fa-upload"></i> 처방전 업로드
      </a>
      @endperm
      {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
      <button type="button" class="ds-btn" onclick="window.__rxGrid?.downloadExcel()">엑셀 저장</button>
      <button type="button" class="ds-btn" onclick="prescriptionViewDetail()">선택 상세</button>
    </div>
  </form>

  {{-- Figma 128:1744 — 흰 카드(r12) 안에 그리드.
       건수는 거래처 관리와 같은 자리에서 읽는다 — 카드 첫 줄의 탭 이름 뒤 괄호다. --}}
  <div class="ds-grid-section">
    <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
      <div id="rxGrid"></div>
    </div>
  </div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  {
    /* 상단 상태 칩은 main c99af13 이 걷고 검색 필터의 첫 칸으로 옮겼다 —
       .status-tabs 는 마크업에 없어 아무것도 가리키지 못했다. */
    selector: '.ds-filter-field:has(select[name="status"]), select[name="status"]',
    title: '상태 고르기',
    body: '처방전을 상태별로 필터링합니다. <b>검수 필요</b> 탭을 먼저 확인하여 처리 대기 중인 처방전을 처리하세요.'
  },
  {
    selector: '.ds-filter-card',
    title: '검색 및 필터',
    body: '이름, 처방번호, 병원명으로 검색하거나 날짜 범위를 지정해 조회할 수 있습니다.'
  },
  {
    selector: '#rxGrid',
    title: '목록 그리드',
    body: '행을 <b>더블클릭</b>하면 주문 화면이 <b>새 탭</b>으로 열립니다(목록 탭은 그대로 유지). <b>판매유형</b>과 <b>Withworks SO</b> 컬럼에서 주문 연계 상태를 한눈에 확인할 수 있고, 컬럼 헤더를 클릭해 정렬할 수 있습니다.'
  },
  {
    selector: 'button[onclick="prescriptionViewDetail()"]',
    title: '선택 상세',
    body: '행을 체크한 뒤 <b>선택 상세</b> 버튼을 눌러도 동일하게 주문 화면이 새 탭으로 열립니다.'
  },
];

</script>
<script>
(function () {
  const DETAIL_BASE = @json(url('prescriptions'));
  const grid = new wwGrid({
    el: document.getElementById('rxGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '처방번호',      name: 'rx_number',  width: 150, sortable: true },
      { header: '출처',          name: 'source',     width: 70,  align: 'center', sortable: true },
      { header: '이름',        name: 'patient',    width: 100, sortable: true },
      { header: '병원',          name: 'hospital',   width: 150, sortable: true },
      { header: '발행일',        name: 'issued',     width: 100, align: 'center', sortable: true },
      { header: '상태',          name: 'status',     width: 90,  align: 'center', sortable: true },
      { header: '처방유형',      name: 'acc_type',   width: 110, align: 'center', sortable: true },
      { header: '판매유형',      name: 'so_type',    width: 90,  align: 'center', sortable: true },
      { header: '주문번호',      name: 'order_no',   width: 140, sortable: true },
      // 표기는 시안 128:1744 대로 'WithWorks So'. 요청서에 이 낱말이 없어 시안이 기준이다.
      // name·width·sortable 은 그대로 둔다(엑셀 머리글도 이 header 를 그대로 쓴다).
      { header: 'WithWorks So',  name: 'so_no',      width: 130, sortable: true },
      { header: '검수 담당자',   name: 'assignee',   width: 90,  align: 'center', sortable: true },
      { header: '접수일시',      name: 'created',    width: 130, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  // 결과바의 '엑셀 저장' 버튼이 부를 수 있게 인스턴스를 노출한다(그리드 내장 툴바 대체).
  window.__rxGrid = grid;
  window.dsBindSelCount(grid, 'rxSelCount');

  /* 주문 화면을 '새 탭'으로 연다.
     워크스페이스 안에서는 목록 탭을 그대로 두고 별도 탭이 열리고(ceOpenTab),
     단독 페이지로 열려 있으면 브라우저 새 탭으로 대체된다. */
  function openReviewTab(rxNumber) {
    const url = DETAIL_BASE + '/' + encodeURIComponent(rxNumber);
    if (typeof window.ceOpenTab === 'function') {
      {{-- 그 화면의 제목이 「주문」이다. 탭 이름이 화면 이름과 다르면
           어느 탭이 무엇인지 알 수 없다. --}}
      window.ceOpenTab(url, '주문 - ' + rxNumber, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  }

  // 행 더블클릭 → 주문 화면을 새 탭으로 열기(목록은 그대로 유지)
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
