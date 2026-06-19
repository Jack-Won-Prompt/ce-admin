@extends('layouts.app')

@section('title', 'CE샵 주문 상세')
@section('page-title', 'CE샵 주문 상세')
@section('breadcrumb')홈 / <a href="{{ route('shop-orders.index') }}">CE샵 주문</a> / {{ $shopOrder->order_number }}@endsection

@push('styles')
<style>
  .show-grid { display:grid; grid-template-columns:1fr 320px; gap:16px; }
  @media(max-width:900px){ .show-grid{grid-template-columns:1fr;} }
  .info-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; }
  @media(max-width:600px){ .info-grid-2{grid-template-columns:1fr;} }
  .info-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
  .info-val   { font-size:13px; font-weight:500; }
  .ww-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:6px; font-size:11.5px; font-weight:700; }
  .ww-02 { background:var(--primary-light); color:var(--primary); }
  .ww-03, .ww-51 { background:var(--info-light); color:var(--info); }
  .ww-04, .ww-52 { background:var(--warning-light); color:var(--warning); }
  .ww-05 { background:var(--success-light); color:var(--success); }
  .ww-06, .ww-99 { background:var(--border-light); color:var(--text-muted); }
  .shop-status-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:700; }
  .shop-status-badge.confirmed  { background:var(--primary-light); color:var(--primary); }
  .shop-status-badge.processing { background:var(--info-light);    color:var(--info); }
  .shop-status-badge.shipped    { background:var(--warning-light); color:var(--warning); }
  .shop-status-badge.delivered  { background:var(--success-light); color:var(--success); }
  .shop-status-badge.cancelled  { background:var(--border-light);  color:var(--text-muted); }
  .amount-row { display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid var(--border-light); font-size:13px; }
  .amount-row:last-child { border-bottom:none; }
  .amount-total { font-size:15px; font-weight:800; color:var(--primary); }
</style>
@endpush

@section('header-actions')
<a href="{{ route('shop-orders.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록
</a>
@endsection

@php
  $statusCls = match($shopOrder->status) {
    'confirmed'  => 'confirmed',
    'processing' => 'processing',
    'shipped'    => 'shipped',
    'delivered'  => 'delivered',
    default      => 'cancelled',
  };
@endphp

@section('content')
<div class="show-grid">

  {{-- ── 좌측 ── --}}
  <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- 주문 기본 정보 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-receipt" style="font-size:18px;color:var(--primary);"></i>
        <span class="card-header-title">주문 정보</span>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
          <span class="shop-status-badge {{ $statusCls }}">{{ $shopOrder->statusLabel() }}</span>
          <select id="statusSelect" class="form-control form-select" style="width:auto;height:32px;font-size:12px;padding:4px 28px 4px 10px;">
            <option value="confirmed"  @selected($shopOrder->status==='confirmed')>주문확인</option>
            <option value="processing" @selected($shopOrder->status==='processing')>처리중</option>
            <option value="shipped"    @selected($shopOrder->status==='shipped')>배송중</option>
            <option value="delivered"  @selected($shopOrder->status==='delivered')>배송완료</option>
            <option value="cancelled"  @selected($shopOrder->status==='cancelled')>취소</option>
          </select>
          <button onclick="updateStatus()" class="btn btn-primary btn-sm">저장</button>
        </div>
      </div>
      <div style="padding:18px;">
        <div class="info-grid-2">
          <div><div class="info-label">주문번호</div><div class="info-val" style="font-weight:700;color:var(--primary);">{{ $shopOrder->order_number }}</div></div>
          <div><div class="info-label">주문일시</div><div class="info-val">{{ $shopOrder->created_at->format('Y-m-d H:i:s') }}</div></div>
          <div><div class="info-label">고객명</div><div class="info-val" style="font-weight:700;">{{ $shopOrder->customer_name }}</div></div>
          <div><div class="info-label">연락처</div><div class="info-val">{{ $shopOrder->customer_phone ?? '-' }}</div></div>
          @if($shopOrder->customer_company)
          <div><div class="info-label">소속기관</div><div class="info-val">{{ $shopOrder->customer_company }}</div></div>
          @endif
          <div><div class="info-label">배송방법</div><div class="info-val">{{ $shopOrder->delivery_method === 'quick' ? '퀵 배송' : '일반 택배' }}</div></div>
        </div>
      </div>
    </div>

    {{-- 주문 상품 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-package" style="font-size:18px;color:var(--success);"></i>
        <span class="card-header-title">주문 상품</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>상품명</th>
              <th style="text-align:center;width:120px;">모델번호</th>
              <th style="text-align:center;width:60px;">수량</th>
              <th style="text-align:right;width:100px;">단가</th>
              <th style="text-align:right;width:100px;">소계</th>
            </tr>
          </thead>
          <tbody>
            @foreach($shopOrder->items as $item)
            <tr>
              <td style="font-weight:500;">{{ $item['product_name'] }}</td>
              <td style="text-align:center;">
                @if(!empty($item['model_number']))
                  <code style="font-family:monospace;font-size:11px;background:var(--bg);padding:1px 6px;border-radius:4px;border:1px solid var(--border);">{{ $item['model_number'] }}</code>
                @else <span style="color:var(--text-muted);">-</span> @endif
              </td>
              <td style="text-align:center;">{{ $item['quantity'] }}</td>
              <td style="text-align:right;font-size:13px;">{{ number_format($item['unit_price'] ?? 0) }}원</td>
              <td style="text-align:right;font-weight:600;">{{ number_format($item['total_price'] ?? 0) }}원</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
        <div style="width:260px;">
          <div class="amount-row">
            <span style="color:var(--text-muted);">상품 합계</span>
            <span>{{ number_format($shopOrder->subtotal) }}원</span>
          </div>
          @if($shopOrder->discount_amount > 0)
          <div class="amount-row">
            <span style="color:var(--text-muted);">할인 ({{ $shopOrder->discount_rate }}%)</span>
            <span style="color:var(--danger);">-{{ number_format($shopOrder->discount_amount) }}원</span>
          </div>
          @endif
          <div class="amount-row">
            <span style="color:var(--text-muted);">배송비</span>
            <span>{{ $shopOrder->shipping_fee > 0 ? number_format($shopOrder->shipping_fee).'원' : '무료' }}</span>
          </div>
          <div class="amount-row" style="border-top:2px solid var(--border);padding-top:8px;margin-top:4px;">
            <span style="font-weight:700;">최종 결제금액</span>
            <span class="amount-total">{{ number_format($shopOrder->total_amount) }}원</span>
          </div>
        </div>
      </div>
    </div>

    {{-- 배송지 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-map-pin" style="font-size:18px;color:var(--warning);"></i>
        <span class="card-header-title">배송지</span>
      </div>
      <div style="padding:18px;">
        <div class="info-grid-2">
          <div><div class="info-label">받는 분</div><div class="info-val">{{ $shopOrder->delivery_name ?? '-' }}</div></div>
          <div><div class="info-label">연락처</div><div class="info-val">{{ $shopOrder->delivery_phone ?? '-' }}</div></div>
        </div>
        <div style="margin-top:12px;">
          <div class="info-label">주소</div>
          <div class="info-val">
            @if($shopOrder->delivery_zipcode)({{ $shopOrder->delivery_zipcode }}) @endif
            {{ $shopOrder->delivery_address ?? '-' }}
          </div>
        </div>
        @if($shopOrder->delivery_note)
        <div style="margin-top:10px;padding:10px 12px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
          <div class="info-label" style="margin-bottom:4px;">배송 요청사항</div>
          <div style="font-size:13px;">{{ $shopOrder->delivery_note }}</div>
        </div>
        @endif
        @if($shopOrder->buyer_note)
        <div style="margin-top:10px;padding:10px 12px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
          <div class="info-label" style="margin-bottom:4px;">구매자 메모</div>
          <div style="font-size:13px;">{{ $shopOrder->buyer_note }}</div>
        </div>
        @endif
      </div>
    </div>

  </div>

  {{-- ── 우측 ── --}}
  <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Withworks 연동 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-link-alt" style="font-size:18px;color:var(--purple);"></i>
        <span class="card-header-title">Withworks 연동</span>
        @if($shopOrder->withworks_so_no)
          <a href="{{ rtrim(config('services.todoworks.api_url'), '/') }}" target="_blank"
             class="btn btn-outline btn-sm ms-auto" style="font-size:11px;">바로가기</a>
        @endif
      </div>
      <div style="padding:16px;">
        @if($shopOrder->withworks_so_no)
          <div style="margin-bottom:10px;">
            <div class="info-label">SO 번호</div>
            <div style="font-size:14px;font-weight:800;color:var(--primary);">{{ $shopOrder->withworks_so_no }}</div>
          </div>
          @if($withworksStatus)
            <div style="margin-bottom:10px;">
              <div class="info-label">Withworks 상태</div>
              <div style="margin-top:4px;">
                @php
                  $wwCls = match(true) {
                    $withworksStatus['status'] === '02' => 'ww-02',
                    in_array($withworksStatus['status'], ['03','51']) => 'ww-03',
                    in_array($withworksStatus['status'], ['04','52']) => 'ww-04',
                    $withworksStatus['status'] === '05' => 'ww-05',
                    default => 'ww-06',
                  };
                @endphp
                <span class="ww-badge {{ $wwCls }}">{{ $withworksStatus['status_label'] }}</span>
              </div>
            </div>
            @if($withworksStatus['so_date'])
            <div style="margin-bottom:8px;"><div class="info-label">주문일</div><div class="info-val">{{ $withworksStatus['so_date'] }}</div></div>
            @endif
            @if($withworksStatus['delivery_date'])
            <div style="margin-bottom:8px;"><div class="info-label">희망 배송일</div><div class="info-val">{{ $withworksStatus['delivery_date'] }}</div></div>
            @endif
            @if($withworksStatus['so_amount'])
            <div><div class="info-label">SO 금액</div><div class="info-val" style="font-weight:700;">{{ number_format($withworksStatus['so_amount']) }}원</div></div>
            @endif
          @else
            <div style="padding:10px 12px;background:var(--border-light);border-radius:var(--radius);font-size:12px;color:var(--text-muted);">
              <i class="bx bx-info-circle"></i> Withworks 상태를 불러올 수 없습니다.
            </div>
          @endif
        @else
          <div style="padding:12px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:13px;color:var(--warning);font-weight:600;">
            <i class="bx bx-time"></i> Withworks 미연동 상태입니다.
          </div>
        @endif
      </div>
    </div>

    {{-- CE샵 연동정보 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-store" style="font-size:18px;color:var(--info);"></i>
        <span class="card-header-title">CE샵 연동정보</span>
      </div>
      <div style="padding:16px;">
        <div class="info-label">CE샵 주문 ID</div>
        <div style="font-size:14px;font-weight:700;font-family:monospace;">#{{ $shopOrder->shop_order_id }}</div>
      </div>
    </div>

    {{-- 관리자 메모 --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-edit-alt" style="font-size:18px;color:var(--text-muted);"></i>
        <span class="card-header-title">관리자 메모</span>
      </div>
      <div style="padding:16px;">
        <textarea id="adminMemo" class="form-control" rows="4"
          placeholder="내부 메모를 입력하세요" style="font-size:13px;">{{ $shopOrder->admin_memo }}</textarea>
        <button onclick="saveMemo()" class="btn btn-outline btn-sm" style="margin-top:10px;width:100%;">저장</button>
      </div>
    </div>

  </div>
</div>

@push('scripts')
<script>
async function updateStatus() {
  const status = document.getElementById('statusSelect').value;
  const res = await fetch('{{ route('shop-orders.updateStatus', $shopOrder) }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    body: JSON.stringify({ status }),
  });
  if ((await res.json()).success) location.reload();
}

async function saveMemo() {
  const admin_memo = document.getElementById('adminMemo').value;
  const res = await fetch('{{ route('shop-orders.updateMemo', $shopOrder) }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    body: JSON.stringify({ admin_memo }),
  });
  if ((await res.json()).success) {
    const btn = document.querySelector('[onclick="saveMemo()"]');
    btn.textContent = '저장됨 ✓';
    setTimeout(() => btn.textContent = '저장', 2000);
  }
}
</script>
@endpush
@endsection
