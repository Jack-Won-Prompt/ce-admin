@extends('layouts.app')

@section('title', '교환/반품/취소')
@section('page-title', '교환/반품/취소')
@section('breadcrumb', '홈 / 주문 / 교환·반품·취소')

@section('help-title', '교환/반품/취소 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>배송된 물건을 바꾸거나 돌려받고, 출고 전 주문을 무르는 자리입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">종류별 단계</div>
  <div class="help-item"><div class="help-item-text"><strong>교환</strong>접수 → 수거중 → 검수중 → 재발송 → 완료</div></div>
  <div class="help-item"><div class="help-item-text"><strong>반품</strong>접수 → 수거중 → 검수중 → 확인요청 → 환불승인 → 환불완료</div></div>
  <div class="help-item"><div class="help-item-text"><strong>취소</strong>보낸 물건이 없어 수거·검수를 건너뜁니다</div></div>
</div>
@endsection

@section('content')

@php $curType = request('type'); @endphp
<div class="ds-chips">
  <a href="{{ route('order-returns.index', request()->except(['type','page'])) }}"
     class="ds-chip {{ !$curType ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $counts->sum() }}</span>
  </a>
  @foreach(\App\Models\OrderReturn::TYPES as $key => $label)
    <a href="{{ route('order-returns.index', array_merge(request()->except(['type','page']), ['type' => $key])) }}"
       class="ds-chip {{ $curType === $key ? 'active' : '' }}">
      {{ $label }}
      @if(($counts[$key] ?? 0) > 0)<span class="ds-chip-count">{{ $counts[$key] }}</span>@endif
    </a>
  @endforeach
</div>

<form method="GET" action="{{ route('order-returns.index') }}" class="ds-filter-card">
  @if($curType)<input type="hidden" name="type" value="{{ $curType }}">@endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="접수번호 · 주문번호 · 환자명">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select">
        <option value="">전체 상태</option>
        @foreach(\App\Models\OrderReturn::STATUS_LABELS as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('status'))
      <a href="{{ route('order-returns.index', array_filter(['type' => $curType])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 접수는 찾는 일과 나란히 둔다. 네비바에 두었더니 탭 안에서 통째로 사라졌고,
         찾다가 없으면 바로 접수하는 흐름과도 맞지 않았다. --}}
    <button type="button" class="ds-btn ds-btn-primary" onclick="rtnPanel('new')">
      <i class="bx bx-plus"></i> 신규 접수
    </button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ $total }}</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint" id="rtnHint">행을 <b>더블클릭</b>하면 상세로 이동합니다.</span>
      <button type="button" class="ds-btn" onclick="window.__rtnGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>

  {{-- 목록과 접수를 한 화면에 나란히 둔다. 접수하려고 다른 화면으로 건너가면
       방금 무엇을 보고 있었는지가 끊긴다. --}}
  <div class="pnl-tabs">
    <button type="button" id="rtnTabList" class="pnl-tab active" onclick="rtnPanel('list')">조회 결과</button>
    <button type="button" id="rtnTabNew"  class="pnl-tab" onclick="rtnPanel('new')">신규 접수</button>
  </div>

  <div class="ds-grid-card" id="rtnPaneList">
    <div id="rtnGrid"></div>
  </div>

  <div class="ds-grid-card" id="rtnPaneNew" style="display:none;">
    @include('order-returns._form')
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const SHOW_BASE = @json(url('order-returns'));
  const grid = new wwGrid({
    el: document.getElementById('rtnGrid'),
    height: 'fit', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
    columns: [
      { header: '접수번호', name: 'receipt',  width: 140, sortable: true },
      { header: '종류',     name: 'type',     width: 70,  align: 'center', sortable: true },
      { header: '상태',     name: 'status',   width: 90,  align: 'center', sortable: true },
      { header: '주문번호', name: 'order_no', width: 120, sortable: true },
      { header: '원 판매주문', name: 'origin_so', width: 130, sortable: true },
      {
        // 창고에 알렸는가. 못 알린 건은 눈에 띄어야 다시 보낸다.
        header: '되돌림 주문', name: 'return_so', width: 130, sortable: true,
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          if (v === '실패' || v === '미전달') { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      { header: '환자명',   name: 'patient',  width: 90 },
      { header: '사유',     name: 'reason',   width: 100 },
      { header: '배송비',   name: 'burden',   width: 100, align: 'center' },
      { header: '환불금액', name: 'refund',   width: 100, align: 'right' },
      { header: '담당자',   name: 'assignee', width: 90 },
      { header: '접수일',   name: 'created',  width: 100, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__rtnGrid = grid;

  /* 목록 · 접수 탭. 접수 탭을 열면 원 주문 찾기에 바로 손이 가도록 커서를 옮긴다. */
  window.rtnPanel = function (which) {
    const isNew = which === 'new';
    document.getElementById('rtnPaneList').style.display = isNew ? 'none' : '';
    document.getElementById('rtnPaneNew').style.display  = isNew ? '' : 'none';
    document.getElementById('rtnTabList').classList.toggle('active', !isNew);
    document.getElementById('rtnTabNew').classList.toggle('active', isNew);
    document.getElementById('rtnHint').style.visibility = isNew ? 'hidden' : '';
    if (isNew) document.getElementById('rtoQ')?.focus();
  };
  /* wwGrid 에는 on() 이 없다. 그 자리에 optional chaining 을 써 두어 아무 일도
     일어나지 않았고, 화면은 「더블클릭하면 상세로 이동합니다」라고 알리고 있었다.
     다른 목록 화면과 같이 셀에서 행 번호를 읽는다. */
  document.getElementById('rtnGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row?.id) location.href = SHOW_BASE + '/' + row.id;
  });
})();
</script>
@endpush
