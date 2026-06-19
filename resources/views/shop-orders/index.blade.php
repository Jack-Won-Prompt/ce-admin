@extends('layouts.app')

@section('title', 'CE샵 주문')
@section('page-title', 'CE샵 주문관리')
@section('breadcrumb', '홈 / CE샵 주문')

@push('styles')
<style>
  .shop-status-badge {
    display:inline-flex; align-items:center; padding:2px 8px;
    border-radius:6px; font-size:11px; font-weight:700;
  }
  .shop-status-badge.confirmed  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.processing { background:var(--info-light);    color:var(--info); }
  .shop-status-badge.shipped    { background:var(--warning-light); color:var(--warning); }
  .shop-status-badge.delivered  { background:var(--success-light); color:var(--success); }
  .shop-status-badge.cancelled  { background:var(--border-light);  color:var(--text-muted); }
  .order-num { font-size:12px; font-weight:700; color:var(--primary); }
</style>
@endpush

@section('content')

{{-- 상태 탭 --}}
@php
  $statuses = ['all'=>'전체','confirmed'=>'주문확인','processing'=>'처리중','shipped'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소'];
  $total = $statusCounts->sum();
  $cur = request('status', '');
@endphp
<div class="tab-pills" style="margin-bottom:16px;">
  @foreach($statuses as $key => $label)
    @php $isActive = ($key === 'all') ? !$cur : ($cur === $key); @endphp
    <a href="{{ route('shop-orders.index', array_merge(request()->except('status','page'), $key !== 'all' ? ['status'=>$key] : [])) }}"
       class="tab-pill {{ $isActive ? 'active' : '' }}">
      {{ $label }}
      <span style="font-size:10.5px;font-weight:700;margin-left:4px;padding:1px 5px;border-radius:20px;background:rgba(27,102,245,.12);color:var(--primary);">
        {{ $key==='all' ? $total : ($statusCounts[$key] ?? 0) }}
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

{{-- 목록 --}}
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>주문번호</th>
          <th style="width:130px;">주문일시</th>
          <th>고객</th>
          <th>상품</th>
          <th style="text-align:right;width:110px;">결제금액</th>
          <th style="text-align:center;width:60px;">배송</th>
          <th style="text-align:center;width:90px;">상태</th>
          <th style="text-align:center;width:120px;">Withworks</th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $order)
        <tr style="cursor:pointer;" onclick="location.href='{{ route('shop-orders.show', $order) }}'">
          <td><span class="order-num">{{ $order->order_number }}</span></td>
          <td style="font-size:12px;color:var(--text-muted);">{{ $order->created_at->format('Y-m-d H:i') }}</td>
          <td>
            <div style="font-weight:600;">{{ $order->customer_name }}</div>
            @if($order->customer_company)
              <div style="font-size:11px;color:var(--text-muted);">{{ $order->customer_company }}</div>
            @endif
          </td>
          <td style="font-size:12px;color:var(--text-muted);">
            @php $items = $order->items; @endphp
            {{ $items[0]['product_name'] ?? '' }}
            @if(count($items) > 1)
              <span style="color:var(--primary);font-weight:600;">+{{ count($items)-1 }}</span>
            @endif
          </td>
          <td style="text-align:right;font-weight:700;">{{ number_format($order->total_amount) }}원</td>
          <td style="text-align:center;font-size:12px;color:var(--text-muted);">{{ $order->delivery_method === 'quick' ? '퀵' : '택배' }}</td>
          <td style="text-align:center;">
            @php
              $shopStatusCls = match($order->status) {
                'confirmed'  => 'confirmed',
                'processing' => 'processing',
                'shipped'    => 'shipped',
                'delivered'  => 'delivered',
                default      => 'cancelled',
              };
            @endphp
            <span class="shop-status-badge {{ $shopStatusCls }}">{{ $order->statusLabel() }}</span>
          </td>
          <td style="text-align:center;">
            @if($order->withworks_so_no)
              <span class="badge badge-success" style="font-size:11px;">{{ $order->withworks_so_no }}</span>
            @else
              <span style="color:var(--text-muted);font-size:12px;">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <i class="bx bx-cart"></i>
              <p>주문이 없습니다.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($orders->hasPages())
  <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:12px;color:var(--text-muted);">
      총 {{ $orders->total() }}건 ({{ $orders->currentPage() }} / {{ $orders->lastPage() }} 페이지)
    </span>
    {{ $orders->links() }}
  </div>
  @endif
</div>

@endsection
