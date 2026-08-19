@extends('layouts.app')

@section('title', 'CE샵 주문')
@section('page-title', 'CE샵 주문관리')
@section('breadcrumb', '홈 / CE샵 주문')

@push('styles')
<style>
  /* 상태 배지 — 시안 배지 규격: r6 · pad 2/6 · 11px/500 · lh18.
     지금 그리드는 상태를 글자로만 그려 이 클래스를 쓰는 마크업이 없지만
     개발 자산이라 남긴다. 색은 DS 토큰(primary 램프·grayscale)만 쓴다. */
  .shop-status-badge {
    display:inline-flex; align-items:center; padding:2px 6px;
    border-radius:6px; font-size:11px; font-weight:500; line-height:18px;
  }
  .shop-status-badge.confirmed  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.processing { background:var(--primary-100);   color:var(--primary-600); }
  /* 배송중은 주황(--warning)이었으나 시안 팔레트에 주황이 없다 → primary 램프로 옮겼다 */
  .shop-status-badge.shipped    { background:var(--primary-200);   color:var(--primary-700); }
  .shop-status-badge.delivered  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.cancelled  { background:var(--border-light);  color:var(--text-muted); }
  /* 12/700 — 시안의 강조 셀 규격 */
  .order-num { font-size:12px; font-weight:700; color:var(--primary); }

  /* 결과바 '선택 N건' — 13/500 · lh21 · gray-600, 숫자만 primary-400 */

  /* 검색 아이콘(.search-wrap)을 살린 채 9열 그리드의 한 칸을 꽉 채운다 */
  .ds-filter-field .search-wrap { display:flex; width:100%; }
  .ds-filter-field .search-wrap .form-control { width:100%; min-width:0; }
</style>
@endpush

@section('content')

{{-- 상태 칩 — 표준 규격: h31 · r999 · pad 6/10 · 12/700, 건수 배지 16×16 정원.
     아래 여백은 .page-body 의 gap 16 이 만든다. --}}
@php
  $statuses = ['all'=>'전체','confirmed'=>'주문확인','processing'=>'처리중','shipped'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소'];
  $allCount = $statusCounts->sum();
  $cur = request('status', '');
@endphp
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}


{{-- 검색 필터 — 흰 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래, 검색어 2열 --}}
<form method="GET" action="{{ route('shop-orders.index') }}" class="ds-filter-card">
  @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        @foreach($statuses as $key => $label)
          @php $sel = ($key === 'all') ? !$cur : ($cur === $key); @endphp
          <option value="{{ $key === 'all' ? '' : $key }}" {{ $sel ? 'selected' : '' }}>
            {{ $label }} ({{ $key === 'all' ? $allCount : ($statusCounts[$key] ?? 0) }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="주문번호, 고객명, 회사명"
               class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
      {{-- 결과바를 걷어낸 자리의 건수 — 이 화면에는 탭이 없어 여기 적는다 --}}
      <span class="ds-filter-total">조회 결과(<b>{{ number_format($total) }}</b>)</span>
    @if(request('q'))
      <a href="{{ route('shop-orders.index', $cur ? ['status'=>$cur] : []) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__shopOrderGrid?.downloadExcel()">엑셀 저장</button>
    <button type="button" class="ds-btn ds-btn-primary" onclick="shopOrderViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
  </div>
</form>

{{-- ── 결과바(h32) + 목록 카드(r12) ── --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div id="shopOrderGrid"></div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const DETAIL_BASE = @json(url('shop-orders'));
  const grid = new wwGrid({
    el: document.getElementById('shopOrderGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, summary: false,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    toolbar: false,
    // 시안에 하단 상태바가 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    footer: false,
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
  window.dsBindSelCount(grid, 'shopOrderSelCount');
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
