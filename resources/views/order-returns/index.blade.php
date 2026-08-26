@extends('layouts.app')

@section('title', '교환/반품/취소')
@section('page-title', '교환/반품/취소')
@section('breadcrumb', '홈 - 주문 - 교환/반품/취소')

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
{{-- 종류는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}

<form method="GET" action="{{ route('order-returns.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 종류가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">종류</label>
      <select name="type" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $counts->sum() }})</option>
        @foreach(\App\Models\OrderReturn::TYPES as $key => $label)
          <option value="{{ $key }}" {{ $curType === $key ? 'selected' : '' }}>
            {{ $label }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="접수번호ㆍ주문번호ㆍ이름">
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
      <a href="{{ route('order-returns.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 접수는 찾는 일과 나란히 둔다. 네비바에 두었더니 탭 안에서 통째로 사라졌고,
         찾다가 없으면 바로 접수하는 흐름과도 맞지 않았다. --}}
    <button type="button" class="ds-btn ds-btn-primary" onclick="rtnPanel('new')">
      <i class="bx bx-plus"></i> 신규 접수
    </button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__rtnGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

<div class="ds-grid-section">
  {{-- 서류 관리와 같은 얼개다 — 흰 카드 한 장 안에 탭줄과 판이 들어간다.
       탭줄을 카드 밖에 두었더니 그 줄만 회색 바탕 위에 떠 있었다. --}}
  <div class="ds-grid-card">
  {{-- 목록과 접수를 한 화면에 나란히 둔다. 접수하려고 다른 화면으로 건너가면
       방금 무엇을 보고 있었는지가 끊긴다. --}}
  <div class="pnl-tabs">
    <button type="button" id="rtnTabList" class="pnl-tab active" onclick="rtnPanel('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ $total }}</b>건)</span></button>
    {{-- 고른 건은 목록 바로 옆에서 본다. 다른 화면으로 건너가면 어떤 조건으로 찾고
         있었는지가 끊기고, 돌아오려면 다시 찾아야 한다. --}}
    <button type="button" id="rtnTabShow" class="pnl-tab" onclick="rtnPanel('show')">상세내용</button>
    <button type="button" id="rtnTabNew"  class="pnl-tab" onclick="rtnPanel('new')">신규 접수</button>
  </div>

  <div id="rtnPaneList">
    <div id="rtnGrid"></div>
  </div>

  <div id="rtnPaneShow" style="display:none;">
    {{-- 상세는 이미 한 화면으로 있다. 그 화면을 그대로 들여온다 — 두 벌로 만들면
         한쪽만 고쳐져 서로 다른 것을 보여 주게 된다.
         액자 안에서는 사이드바·네비가 스스로 숨는다(is-framed). --}}
    <div id="rtnShowEmpty" style="padding:28px 16px;text-align:center;font-size:12px;color:var(--gray-700);">
      목록에서 행을 더블클릭하면 여기에 나옵니다.
    </div>
    <iframe id="rtnShowFrame" title="상세내용" style="display:none;width:100%;border:0;
            height:calc(100vh - 300px);min-height:520px;"></iframe>
  </div>

  <div id="rtnPaneNew" style="display:none;">
    @include('order-returns._form')
  </div>
  </div>{{-- /.ds-grid-card --}}
</div>

@endsection

@push('scripts')
<script>
(function () {
  const SHOW_BASE = @json(url('order-returns'));
  const grid = new wwGrid({
    el: document.getElementById('rtnGrid'),
    height: 'fit', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '접수번호', name: 'receipt',  width: 140, sortable: true },
      { header: '종류',     name: 'type',     width: 70,  align: 'center', sortable: true },
      { header: '상태',     name: 'status',   width: 90,  align: 'center', sortable: true },
      { header: '주문번호', name: 'order_no', width: 120, sortable: true },
      { header: '원 판매주문', name: 'origin_so', width: 130, sortable: true },
      {
        // 창고에 알렸는가. 못 알린 건은 눈에 띄어야 다시 보낸다.
        header: '반품 주문', name: 'return_so', width: 130, sortable: true,
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          if (v === '실패' || v === '미전달') { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      { header: '이름',   name: 'patient',  width: 90 },
      { header: '사유',     name: 'reason',   width: 100 },
      { header: '배송비',   name: 'burden',   width: 100, align: 'center' },
      { header: '환불금액', name: 'refund',   width: 100, align: 'right' },
      { header: '담당자',   name: 'assignee', width: 90 },
      { header: '접수일',   name: 'created',  width: 100, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__rtnGrid = grid;

  /* 목록 · 상세 · 접수 탭. 접수 탭을 열면 원 주문 찾기에 바로 손이 가도록 커서를 옮긴다. */
  const PANES = { list: 'rtnPaneList', show: 'rtnPaneShow', new: 'rtnPaneNew' };
  const TABS  = { list: 'rtnTabList',  show: 'rtnTabShow',  new: 'rtnTabNew'  };

  window.rtnPanel = function (which) {
    if (!PANES[which]) which = 'list';
    Object.keys(PANES).forEach(k => {
      document.getElementById(PANES[k]).style.display = k === which ? '' : 'none';
      document.getElementById(TABS[k]).classList.toggle('active', k === which);
    });
    if (which === 'new') document.getElementById('rtoQ')?.focus();
  };

  /* 다른 화면에서 「신청 등록」으로 들어오면 접수 탭을 펴고 원 주문을 앉힌다.
     이 스크립트는 접수 폼보다 뒤에 돌아 rtnPanel·rtoPreset 이 모두 준비돼 있다. */
  (function () {
    const p = new URLSearchParams(location.search);
    const orderNo = p.get('order_no');
    if (!p.get('new') && !orderNo) return;
    rtnPanel('new');
    if (orderNo) window.rtoPreset?.(orderNo);
  })();

  /* 고른 건을 상세 탭에 들여온다. 탭 이름에 접수번호를 붙여 둔다 —
     탭을 여럿 오가다 보면 무엇을 열어 두었는지 잊는다. */
  function rtnShow(row) {
    const frame = document.getElementById('rtnShowFrame');
    /* frame=1 — 액자 안이라는 것을 서버에도 알린다. 레이아웃은 self!==top 으로도
       알아채지만 그것은 화면이 그려진 뒤라, 「목록으로」를 아예 내보내지 않으려면
       서버가 알아야 한다. */
    const url   = SHOW_BASE + '/' + row.id + '?frame=1';
    if (frame.dataset.url !== url) {
      frame.src = url;
      frame.dataset.url = url;
    }
    frame.style.display = '';
    document.getElementById('rtnShowEmpty').style.display = 'none';
    document.getElementById('rtnTabShow').textContent =
      '상세 내용' + (row.receipt ? ' · ' + row.receipt : '');
    rtnPanel('show');
  }
  /* wwGrid 에는 on() 이 없다 — 다른 목록 화면과 같이 셀에서 행 번호를 읽는다. */
  document.getElementById('rtnGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row?.id) rtnShow(row);
  });
})();
</script>
@endpush
