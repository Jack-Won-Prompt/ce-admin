{{-- resources/views/dispatch/index.blade.php --}}
@extends('layouts.app')

@section('title', '발송/발행 내역')
@section('page-title', '발송/발행 내역')
@section('breadcrumb', '홈 / 발송·발행 내역')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=4">
<style>
  .type-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
  .type-tab {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
    border:1.5px solid var(--border); background:#fff;
    color:var(--text-secondary); cursor:pointer; text-decoration:none;
    transition:var(--transition);
  }
  .type-tab:hover  { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  .type-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
  .type-tab .tab-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:20px; height:18px; padding:0 6px;
    border-radius:20px; font-size:10.5px; font-weight:700;
    background:rgba(255,255,255,.25); color:inherit;
  }
  .type-tab:not(.active) .tab-count { background:var(--border-light); color:var(--text-muted); }

  .filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
  .filter-bar .form-control { height:36px; font-size:13px; }
  .filter-bar .btn { height:36px; white-space:nowrap; }

  .empty-state { text-align:center; padding:56px 24px; color:var(--text-muted); }
  .empty-state i { font-size:44px; margin-bottom:12px; display:block; opacity:.3; }
  .empty-state p { font-size:13px; margin:0; }

  .mono { font-family:monospace; font-size:12px; color:var(--primary); font-weight:700; }
  .sub-text { font-size:11px; color:var(--text-muted); margin-top:2px; }
</style>
@endpush

@section('content')

  {{-- 타입 탭 --}}
  <div class="type-tabs">
    <a href="{{ route('dispatch.index', ['type'=>'virtual_account'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='virtual_account' ? 'active' : '' }}">
      <i class="bx bx-credit-card"></i> 가상계좌 발행
      <span class="tab-count">{{ number_format($counts['virtual_account']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'tax_invoice'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='tax_invoice' ? 'active' : '' }}">
      <i class="bx bx-receipt"></i> 세금계산서 발행
      <span class="tab-count">{{ number_format($counts['tax_invoice']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'cash_receipt'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='cash_receipt' ? 'active' : '' }}">
      <i class="bx bx-money"></i> 현금영수증 발행
      <span class="tab-count">{{ number_format($counts['cash_receipt']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'nhis'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='nhis' ? 'active' : '' }}">
      <i class="bx bx-paper-plane"></i> NHIS 청구 발송
      <span class="tab-count">{{ number_format($counts['nhis']) }}</span>
    </a>
  </div>

  {{-- 검색 필터 --}}
  <form method="GET" action="{{ route('dispatch.index') }}" class="filter-bar">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="text" name="search" class="form-control" style="width:220px;"
           placeholder="주문번호 · 환자명 · 발행번호" value="{{ $search }}">
    <input type="date" name="date_from" class="form-control" style="width:150px;" value="{{ $dateFrom }}">
    <span style="font-size:13px;color:var(--text-muted);flex-shrink:0;">~</span>
    <input type="date" name="date_to" class="form-control" style="width:150px;" value="{{ $dateTo }}">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-magnifying-glass"></i> 검색
    </button>
    @if($search || request()->hasAny(['date_from','date_to']))
      <a href="{{ route('dispatch.index', ['type'=>$type]) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark"></i> 초기화
      </a>
    @endif
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
      <select name="per_page" class="form-control form-select" style="width:100px;height:36px;font-size:13px;"
              onchange="this.form.submit()">
        @foreach([10, 20, 50, 100] as $n)
          <option value="{{ $n }}" {{ $perPage === $n ? 'selected' : '' }}>{{ $n }}개씩</option>
        @endforeach
      </select>
      <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
        총 {{ number_format($total) }}건
      </span>
    </div>
  </form>

  {{-- ── 발송 내역 (wwGrid) ── --}}
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
    <button type="button" class="btn btn-outline btn-sm" onclick="dispatchViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 상세로 이동</span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="dispatchGrid"></div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar', title: '발송 내역 검색', body: '팩스·이메일·SMS 발송 내역을 날짜·수신자·상태로 조회합니다.' },
  { selector: '#dispatchGrid', title: '발송 목록', body: '청구서·영수증·알림 발송 이력 전체를 확인합니다.' },
];
</script>
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
(function () {
  const DETAIL_BASE = @json(url('dispatch'));   // + '/{type}/{id}'
  const TYPE = @json($type);
  const grid = new wwGrid({
    el: document.getElementById('dispatchGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: @json($gridColumns),
    data: @json($gridData),
  });
  window.dispatchViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    window.location.href = DETAIL_BASE + '/' + TYPE + '/' + c[0].id;
  };
})();
</script>
@endpush
