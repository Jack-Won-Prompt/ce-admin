{{-- resources/views/finance/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Finance')
@section('page-title', 'Finance')
@section('breadcrumb', '홈 - Finance')

@section('content')

{{-- 거르는 줄은 하나다. 여섯 탭이 묻는 것은 「언제」와 「누구ㆍ무엇」 둘뿐이다. --}}
<form method="GET" class="ds-filter-card">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호ㆍ이름ㆍ제품명">
    </div>
    <div class="ds-filter-field span-2">
      {{-- 요청서 14쪽 공통확인사항 — 「조회기간 조건 검색 가능(일별, 월별)」.
           달을 고르는 단추를 옆에 둔다. 재무가 보는 자리라 대개 한 달치다. --}}
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <button type="button" class="ds-btn" onclick="finMonth(0)">이번 달</button>
    <button type="button" class="ds-btn" onclick="finMonth(-1)">지난 달</button>
    <a href="{{ route('finance.index', ['tab' => $tab]) }}" class="ds-btn">
      <i class="fa-solid fa-rotate-left"></i> 초기화
    </a>
    <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-search"></i> 검색</button>
  </div>
</form>

<div class="ds-grid-card">
  {{-- 여섯을 한 화면의 탭으로 둔다 — 묻는 것이 같고(기간), 여는 사람이 같고,
       무엇보다 여섯을 오가며 견주어 본다. 메뉴를 여섯으로 늘리면 그때마다 기간을
       다시 고르게 된다. --}}
  {{-- overflow-x:auto 를 걸어 두었더니 걸린 탭의 아래 선이 사라졌다. 탭은
       margin-bottom:-1px 로 제 아래 선을 탭줄의 아래 선 위에 겹쳐 놓는데,
       가로 넘침을 auto 로 두면 세로도 auto 가 되어 그 1px 이 잘린다.
       여섯이 한 줄에 서므로 넘칠 일이 없고, 좁아지면 접히게 둔다. --}}
  <div class="pnl-tabs" style="flex-wrap:wrap;">
    @foreach(\App\Http\Controllers\FinanceController::TABS as $k => $label)
      <a href="{{ route('finance.index', array_filter(['tab' => $k, 'q' => request('q'), 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
         class="pnl-tab {{ $tab === $k ? 'active' : '' }}" style="white-space:nowrap;">
        {{ $label }}@if($tab === $k)<span class="pnl-tab-cnt">(총 <b>{{ number_format(count($gridData)) }}</b>건)</span>@endif
      </a>
    @endforeach
    <div style="margin-left:auto;padding-right:12px;flex-shrink:0;">
      {{-- 요청서 14쪽 공통확인사항 — 「모든 메뉴는 엑셀 다운로드 가능」 --}}
      <button type="button" class="ds-btn" onclick="window.__financeGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>
  <div id="financeGrid"></div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const money = (v) => {
    const n = Number(v || 0);
    if (!n) return '';
    const s = document.createElement('span');
    s.textContent = n.toLocaleString('ko-KR');
    return s;
  };

  /* 칸은 서버가 정한다(FinanceController::columnsFor) — 요청서 14~19쪽의 차례를
     그대로 옮긴 것이라, 화면에서 다시 적으면 두 벌이 갈린다. */
  const COLS = @json($columns);
  COLS.forEach(c => { if (c.editor === 'number') { delete c.editor; c.renderer = money; } });

  const grid = new wwGrid({
    el: document.getElementById('financeGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: COLS,
    data: @json($gridData),
  });
  window.__financeGrid = grid;

  /* 달로 고르기 — 재무가 보는 자리라 대개 한 달치다. 날짜 두 개를 손으로 맞추는
     것보다 단추 하나가 빠르다. */
  window.finMonth = function (offset) {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + offset);
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const last  = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const fmt = (x) => `${x.getFullYear()}-${String(x.getMonth()+1).padStart(2,'0')}-${String(x.getDate()).padStart(2,'0')}`;
    document.querySelector('input[name=date_from]').value = fmt(first);
    document.querySelector('input[name=date_to]').value   = fmt(last);
    document.querySelector('.ds-filter-card').submit();
  };
})();
</script>
@endpush
