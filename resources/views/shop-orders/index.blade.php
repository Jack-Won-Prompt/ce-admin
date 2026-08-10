@extends('layouts.app')

@section('title', 'CE샵 주문')
@section('page-title', 'CE샵 주문관리')
@section('breadcrumb', '홈 / CE샵 주문')

@push('styles')
<style>
  .shop-status-badge {
    display:inline-flex; align-items:center; padding:2px 6px;
    border-radius:6px; font-size:11px; font-weight:500; line-height:18px;
  }
  .shop-status-badge.confirmed  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.processing { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.shipped    { background:var(--warning-light); color:var(--warning); }
  .shop-status-badge.delivered  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.cancelled  { background:var(--border-light);  color:var(--text-muted); }
  .order-num { font-size:12px; font-weight:700; color:var(--primary); }
</style>
@endpush

@section('content')

{{-- 상태 탭 --}}
@php
  $statuses = ['all'=>'전체','confirmed'=>'주문확인','processing'=>'처리중','shipped'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소'];
  $allCount = $statusCounts->sum();
  $cur = request('status', '');
@endphp
<div class="tab-pills" style="margin-bottom:16px;">
  @foreach($statuses as $key => $label)
    @php $isActive = ($key === 'all') ? !$cur : ($cur === $key); @endphp
    <a href="{{ route('shop-orders.index', array_merge(request()->except('status','page'), $key !== 'all' ? ['status'=>$key] : [])) }}"
       class="tab-pill {{ $isActive ? 'active' : '' }}">
      {{ $label }}
      <span style="font-size:10.5px;font-weight:700;margin-left:4px;padding:1px 5px;border-radius:20px;background:rgba(40,121,139,.12);color:var(--primary);">
        {{ $key==='all' ? $allCount : ($statusCounts[$key] ?? 0) }}
      </span>
    </a>
  @endforeach
</div>

{{-- 검색 필터 --}}
<form method="GET" class="filter-bar" style="margin-bottom:16px;">
  @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
  <div class="search-wrap">
    <i class="bx bx-search"></i>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="주문번호, 고객명, 회사명"
           class="form-control" style="width:240px;">
  </div>
  <button class="btn btn-primary btn-sm">검색</button>
  @if(request('q'))
    <a href="{{ route('shop-orders.index', $cur ? ['status'=>$cur] : []) }}" class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- ── 목록 (wwGrid) ── --}}
<div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
  <button type="button" class="btn btn-outline btn-sm" onclick="shopOrderViewDetail()">
    <i class="bx bx-detail"></i> 선택 상세
  </button>
  <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 상세로 이동</span>
  <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
</div>
<div id="shopOrderGrid"></div>

@endsection

@push('scripts')
<script>
(function () {
  const DETAIL_BASE = @json(url('shop-orders'));
  const grid = new wwGrid({
    el: document.getElementById('shopOrderGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '주문번호',  name: 'order_no',  width: 130, sortable: true },
      { header: '주문일시',  name: 'created',   width: 130, sortable: true },
      { header: '고객',      name: 'customer',  width: 100, sortable: true },
      { header: '회사명',    name: 'company',   width: 120 },
      { header: '상품',      name: 'product',   width: 180 },
      { header: '결제금액',  name: 'amount',    width: 110, editor: 'number' },
      { header: '배송',      name: 'delivery',  width: 70,  align: 'center' },
      { header: '상태',      name: 'status',    width: 90,  align: 'center', sortable: true },
      { header: 'Withworks', name: 'withworks', width: 120, align: 'center' },
    ],
    data: @json($gridData),
  });
  window.__shopOrderGrid = grid;
  window.shopOrderViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    // 목록 탭을 그대로 두고 상세를 새 탭으로 (워크스페이스 밖이면 브라우저 새 탭)
    ceOpenTab(DETAIL_BASE + '/' + c[0].id, 'CE샵 주문 - ' + c[0].id, 'handle-with-care');
  };
})();
</script>
@endpush
