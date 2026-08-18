@extends('layouts.app')

@section('title', $r->receipt_no)
@section('page-title', $r->typeLabel() . ' · ' . $r->receipt_no)
@section('breadcrumb', '홈 / 주문 / 교환·반품·취소 / 상세')

@section('header-actions')
<a href="{{ route('order-returns.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록으로
</a>
@endsection

@push('styles')
<style>
  .rt-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:14px; }
  .rt-hd { padding:11px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700;
           display:flex; align-items:center; gap:8px; }
  .rt-hd .grow { flex:1; }
  .rt-bd { padding:14px 16px; }
  .rt-kv { display:flex; padding:7px 0; border-bottom:1px solid var(--border-light); font-size:13px; }
  .rt-kv:last-child { border-bottom:none; }
  .rt-kv > span:first-child { width:120px; flex-shrink:0; color:var(--text-muted); }
  .rt-kv > span:last-child  { flex:1; font-weight:500; }

  /* 단계 — 어디까지 왔는지 한눈에 보여야 한다 */
  .steps { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  .step { font-size:12px; font-weight:700; padding:5px 11px; border-radius:99px;
          background:var(--gray-100); color:var(--text-muted); }
  .step.done { background:var(--primary-light); color:var(--primary); }
  .step.now  { background:var(--primary); color:#fff; }
  .step.off  { background:var(--alert-100); color:var(--alert-500); }
  .step-sep { color:var(--text-muted); font-size:11px; }

  .log { display:flex; gap:10px; padding:8px 0; border-bottom:1px solid var(--border-light); font-size:12px; }
  .log:last-child { border-bottom:none; }
  .log-when { width:120px; flex-shrink:0; color:var(--text-muted); }
  .log-move { width:150px; flex-shrink:0; font-weight:700; }
  .ok-bar { background:var(--primary-light); border:1px solid var(--primary-200); color:var(--primary);
            border-radius:8px; padding:9px 12px; font-size:12px; margin-bottom:14px; font-weight:700; }
</style>
@endpush

@section('content')

@if(session('status'))<div class="ok-bar">{{ session('status') }}</div>@endif

<div class="rt-card">
  <div class="rt-hd">
    진행 단계
    <div class="grow"></div>
    <span style="font-weight:400;font-size:11px;color:var(--text-muted);">{{ $r->typeLabel() }}</span>
  </div>
  <div class="rt-bd">
    @php
      $flow = \App\Models\OrderReturn::FLOWS[$r->type] ?? [];
      $at   = array_search($r->status, $flow, true);
    @endphp
    <div class="steps">
      @foreach($flow as $i => $s)
        @if($i > 0)<span class="step-sep">›</span>@endif
        <span class="step {{ $at !== false && $i < $at ? 'done' : ($r->status === $s ? 'now' : '') }}">
          {{ \App\Models\OrderReturn::STATUS_LABELS[$s] ?? $s }}
        </span>
      @endforeach
      @if($r->status === 'cancelled')
        <span class="step-sep">›</span><span class="step off">취소</span>
      @endif
    </div>

    @if($r->nextStatuses())
      <form method="POST" action="{{ route('order-returns.advance', $r) }}"
            style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        @csrf
        <select name="to_status" class="form-control form-select" style="max-width:170px;">
          @foreach($r->nextStatuses() as $s)
            <option value="{{ $s }}">{{ \App\Models\OrderReturn::STATUS_LABELS[$s] ?? $s }}(으)로</option>
          @endforeach
        </select>
        {{-- 단계마다 사유를 받는다. 마지막 사유만 남기면 왜 그렇게 흘러왔는지 알 수 없다. --}}
        <input type="text" name="reason" class="form-control" style="flex:1;min-width:200px;"
               maxlength="500" placeholder="이 단계로 옮기는 사유 (선택)">
        <button class="btn btn-primary btn-sm" type="submit">단계 옮기기</button>
      </form>
      @error('to_status')<div style="color:var(--danger);font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror
    @else
      <div style="margin-top:12px;font-size:12px;color:var(--text-muted);">더 옮길 단계가 없습니다.</div>
    @endif
  </div>
</div>

<div class="rt-card">
  <div class="rt-hd">원 주문</div>
  <div class="rt-bd">
    <div class="rt-kv"><span>주문번호</span><span>
      @if($r->order)
        {{-- 원 주문은 다른 화면이라 새 탭으로 연다 — 보고 있던 접수가 사라지면 안 된다 --}}
        <a href="{{ route('orders.show', $r->order) }}" style="color:var(--primary);"
           data-ce-tab="주문 - {{ $r->order->order_number }}" data-ce-icon="bx-cart">{{ $r->order->order_number }}</a>
      @else — @endif
    </span></div>
    <div class="rt-kv"><span>환자명</span><span>{{ $r->order?->patient?->name ?? '—' }}</span></div>
    <div class="rt-kv"><span>제품</span><span>{{ $r->order?->product_name ?? '—' }}</span></div>
    <div class="rt-kv"><span>주문 금액</span><span>{{ $r->order ? number_format($r->order->total_amount) . '원' : '—' }}</span></div>
    <div class="rt-kv"><span>배송지</span><span>{{ $r->order?->shipping_address ?? '—' }}</span></div>
  </div>
</div>

<div class="rt-card">
  <div class="rt-hd">신청 내용</div>
  <div class="rt-bd">
    <div class="rt-kv"><span>접수번호</span><span>{{ $r->receipt_no }}</span></div>
    <div class="rt-kv"><span>사유</span><span>{{ \App\Models\OrderReturn::REASONS[$r->reason_code]['label'] ?? $r->reason_code }}</span></div>
    <div class="rt-kv"><span>상세 사유</span><span>{{ $r->reason_text ?: '—' }}</span></div>
    <div class="rt-kv"><span>배송비 부담</span><span>{{ \App\Models\OrderReturn::BURDENS[$r->shipping_burden] ?? '—' }}</span></div>
    {{-- 접수 때는 수거 방법을 묻지 않는다 — 예전에 받아 둔 건에만 남아 있으니 있을 때만 --}}
    @if($r->collect_method)
      <div class="rt-kv"><span>수거 방법</span><span>{{ \App\Models\OrderReturn::COLLECT_METHODS[$r->collect_method] ?? $r->collect_method }}</span></div>
    @endif
    @if($r->type === \App\Models\OrderReturn::TYPE_EXCHANGE)
      {{-- 접수 때는 더 묻지 않는다 — 무엇을 되돌리는지는 아래 주문 제품에 있고, 바꿔 보낼
           물건과 보낼 곳은 창고가 수거·검수를 마친 뒤 정해진다. 예전에 받아 둔 건에만
           값이 남아 있으니, 있을 때만 보여 준다. --}}
      @if($r->exchange_product || $r->exchange_quantity)
        <div class="rt-kv"><span>교환 제품</span><span>{{ $r->exchange_product ?: '—' }} {{ $r->exchange_quantity ? '× ' . $r->exchange_quantity : '' }}</span></div>
      @endif
      @if($r->reship_address)
        <div class="rt-kv"><span>재배송지</span><span>{{ $r->reship_address }}</span></div>
      @endif
    @else
      <div class="rt-kv"><span>환불 수단</span><span>{{ \App\Models\OrderReturn::REFUND_METHODS[$r->refund_method] ?? '—' }}</span></div>
      <div class="rt-kv"><span>환불 금액</span><span>{{ $r->refund_amount ? number_format($r->refund_amount) . '원' : '—' }}</span></div>
      @if($r->refund_method === 'account')
        <div class="rt-kv"><span>환불 계좌</span><span>{{ trim(($r->refund_bank ?? '') . ' ' . ($r->refund_account ?? '') . ' ' . ($r->refund_holder ?? '')) ?: '—' }}</span></div>
      @endif
      <div class="rt-kv"><span>환불 완료</span><span>{{ $r->refunded_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
    @endif
    <div class="rt-kv"><span>담당자</span><span>{{ $r->assignee?->name ?? $r->creator?->name ?? '—' }}</span></div>
  </div>
</div>

{{-- 창고 연계 ─────────────────────────────────────────
     되돌리는 건이 CEAdmin 안에서만 돌면 창고는 물건이 돌아온다는 것을 모른다.
     알렸는지, 창고가 어디까지 했는지를 여기서 본다. --}}
<div class="rt-card">
  <div class="rt-hd">
    위드웍스 연계
    {{-- 아직 못 보낸 건에는 보낼 길이 있어야 한다. 까닭이 남은 것만 다시 보내게 두면,
         연동을 켜기 전에 접수한 건은 영영 창고에 알려지지 않는다. --}}
    @if(!$r->sentToWithworks())
      <form method="POST" action="{{ route('order-returns.resend', $r) }}" style="margin-left:auto;display:inline;">
        @csrf
        <button type="submit" class="ds-btn ds-btn-sm">
          {{ $r->withworks_error ? '다시 보내기' : '위드웍스로 보내기' }}
        </button>
      </form>
    @endif
  </div>
  <div class="rt-bd">
    <div class="rt-kv"><span>원 판매주문</span><span>{{ $r->order?->withworks_so_no ?: '—' }}</span></div>
    @if($r->hasReturnSo())
      <div class="rt-kv"><span>반품 주문</span><span>
        {{ $r->withworks_so_no }}
        @php $meta = \App\Models\Order::SO_TYPE_LABELS[$r->withworks_so_type] ?? null; @endphp
        @if($meta) · {{ $r->withworks_so_type }} {{ $meta[0] }} @endif
      </span></div>
      <div class="rt-kv"><span>창고 상태</span><span>
        {{ $r->withworks_status_label ?: ($r->withworks_status ?: '—') }}
      </span></div>
    @elseif($r->sentToWithworks())
      {{-- 출고 전 취소는 반품 주문을 세우지 않는다 — 원 주문을 취소한다 --}}
      <div class="rt-kv"><span>처리</span><span>
        원 판매주문을 취소했습니다 — 되돌릴 물건이 없어 새 주문을 세우지 않습니다
      </span></div>
    @else
      <div class="rt-kv"><span>전달</span><span style="color:#B54708;font-weight:600;">
        {{ $r->withworks_error ?: '아직 알리지 못했습니다' }}
      </span></div>
    @endif
    <div class="rt-kv"><span>전달 시각</span><span>{{ $r->withworks_sent_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
  </div>
</div>

<div class="rt-card">
  <div class="rt-hd">처리 이력</div>
  <div class="rt-bd">
    @forelse($r->logs as $log)
      <div class="log">
        <span class="log-when">{{ $log->created_at?->format('m-d H:i') }}</span>
        <span class="log-move">
          {{ $log->from_status ? (\App\Models\OrderReturn::STATUS_LABELS[$log->from_status] ?? $log->from_status) . ' → ' : '' }}
          {{ \App\Models\OrderReturn::STATUS_LABELS[$log->to_status] ?? $log->to_status }}
        </span>
        <span style="flex:1;">{{ $log->reason ?: '—' }}</span>
        <span style="color:var(--text-muted);">{{ $log->creator?->name ?? '' }}</span>
      </div>
    @empty
      <div style="font-size:12px;color:var(--text-muted);">이력이 없습니다.</div>
    @endforelse
  </div>
</div>

@endsection
