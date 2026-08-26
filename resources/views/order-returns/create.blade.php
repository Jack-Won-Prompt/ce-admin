@extends('layouts.app')

@section('title', '교환/반품/취소 접수')
@section('page-title', '교환/반품/취소 접수')
@section('breadcrumb', '홈 - 주문 - 교환/반품/취소 - 접수')

@section('header-actions')
<a href="{{ route('order-returns.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록으로
</a>
@endsection

@push('styles')
<style>
  .rt-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:14px; }
  .rt-hd { padding:11px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700; }
  .rt-bd { padding:14px 16px; }
  .rt-row { display:flex; gap:12px; align-items:center; margin-bottom:11px; }
  .rt-row > label { width:110px; flex-shrink:0; font-size:13px; color:var(--text-secondary); }
  .rt-row .form-control { flex:1; min-width:0; }
  .rt-hint { font-size:11px; color:var(--text-muted); margin-top:3px; }
  .rt-only { display:none; }
  /* 하단 채우기 — 폼이 남는 높이를 받고(전역 .fill-rest/.fill-col) 마지막 카드가 바닥까지 내려온다.
     내용이 길어지면 카드가 눌리지 않고 페이지가 스크롤되도록 shrink 만 막아 둔다. */
  .rt-card, .rt-card.fill-rest, .rt-actions { flex-shrink:0; }
  /* 접수 단추 줄만 카드 밖(아래)에 있어 흰 것이 바닥에서 46 모자랐다 — 카드 아래 여백 14 + 단추 줄 32.
     단추 줄에 카드와 똑같은 흰 판(배경·1px 테두리·모서리 12)을 입혀 흰 것이 본문 바닥에 닿게 한다.
     안여백 12/16 은 DS 값이고, 오른쪽 16 이 카드 안 입력칸 오른쪽 끝과 맞는다.
     같은 손질을 문의 작성(.iqc-wrap)·공지 등록(.notice-shell)에서도 했다. */
  .rt-actions { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:12px 16px; }
</style>
@endpush

@section('content')

<form method="POST" action="{{ route('order-returns.store') }}" class="fill-rest fill-col">
  @csrf

  <div class="rt-card">
    <div class="rt-hd">원 주문</div>
    <div class="rt-bd">
      <div class="rt-row">
        <label>주문</label>
        <select name="order_id" id="rt-order" class="form-control form-select" required>
          <option value="">주문을 고르십시오</option>
          @foreach($orders as $o)
            <option value="{{ $o->id }}" @selected(old('order_id', $order?->id) == $o->id)
                    data-amount="{{ (int) $o->total_amount }}"
                    data-product="{{ $o->product_name }}"
                    data-address="{{ $o->shipping_address }}">
              {{ $o->order_number }} · {{ $o->patient?->name ?? '-' }} · {{ $o->product_name }}
              ({{ number_format($o->total_amount) }}원)
            </option>
          @endforeach
        </select>
      </div>
      @error('order_id')<div class="rt-hint" style="color:var(--danger);">{{ $message }}</div>@enderror
    </div>
  </div>

  <div class="rt-card fill-rest">
    <div class="rt-hd">신청 내용</div>
    <div class="rt-bd">
      <div class="rt-row">
        <label>종류</label>
        <select name="type" id="rt-type" class="form-control form-select" required>
          @foreach(\App\Models\OrderReturn::TYPES as $k => $label)
            <option value="{{ $k }}" @selected(old('type') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="rt-row">
        <label>사유</label>
        <select name="reason_code" id="rt-reason" class="form-control form-select" required>
          @foreach(\App\Models\OrderReturn::REASONS as $k => $r)
            <option value="{{ $k }}" @selected(old('reason_code') === $k)>{{ $r['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="rt-row" style="align-items:flex-start;">
        <label style="padding-top:7px;">상세 사유</label>
        <textarea name="reason_text" class="form-control" rows="2" maxlength="500"
                  placeholder="고객이 말한 내용을 그대로 적어 두면 나중에 판단이 쉽습니다">{{ old('reason_text') }}</textarea>
      </div>
    </div>
  </div>

  <div class="rt-card rt-only" data-for="return cancel">
    <div class="rt-hd">환불</div>
    <div class="rt-bd">
      <div class="rt-row">
        <label>환불 수단</label>
        <select name="refund_method" id="rt-refund-method" class="form-control form-select">
          <option value="">선택</option>
          @foreach(\App\Models\OrderReturn::REFUND_METHODS as $k => $label)
            <option value="{{ $k }}" @selected(old('refund_method') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="rt-row">
        <label>환불 금액</label>
        <input type="number" name="refund_amount" id="rt-refund-amount" class="form-control" min="0"
               value="{{ old('refund_amount') }}">
      </div>
      <div class="rt-row rt-only" data-for-refund="account">
        <label>환불 계좌</label>
        <input type="text" name="refund_bank" class="form-control" maxlength="50" style="max-width:130px;"
               value="{{ old('refund_bank') }}" placeholder="은행">
        <input type="text" name="refund_account" class="form-control" maxlength="50"
               value="{{ old('refund_account') }}" placeholder="계좌번호">
        <input type="text" name="refund_holder" class="form-control" maxlength="50" style="max-width:110px;"
               value="{{ old('refund_holder') }}" placeholder="예금주">
      </div>
      <div class="rt-hint" id="rt-card-hint" style="display:none;color:var(--warn,#b45309);">
        카드 결제가 연동돼 있지 않아 결제취소는 자동으로 되지 않습니다 — 수단만 기록됩니다.
      </div>
    </div>
  </div>

  <div class="rt-actions" style="text-align:right;">
    <button type="submit" class="btn btn-primary">접수</button>
  </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
  const type   = document.getElementById('rt-type');
  const order  = document.getElementById('rt-order');

  /* 종류마다 필요한 칸이 다르다 — 취소·반품은 환불을 묻고, 교환은 묻지 않는다. */
  function syncType() {
    document.querySelectorAll('.rt-only[data-for]').forEach(el => {
      el.style.display = el.dataset.for.split(' ').includes(type.value) ? '' : 'none';
    });
  }

  function syncOrder() {
    const opt = order.selectedOptions[0];
    if (!opt?.value) return;
    const amount = document.getElementById('rt-refund-amount');
    if (amount && !amount.value) amount.value = opt.dataset.amount || '';
  }

  function syncRefund() {
    const m = document.getElementById('rt-refund-method')?.value;
    document.querySelectorAll('[data-for-refund]').forEach(el => {
      el.style.display = el.dataset.forRefund === m ? '' : 'none';
    });
    document.getElementById('rt-card-hint').style.display = m === 'card' ? '' : 'none';
  }

  type.addEventListener('change', syncType);
  order.addEventListener('change', syncOrder);
  document.getElementById('rt-refund-method')?.addEventListener('change', syncRefund);

  syncType(); syncOrder(); syncRefund();
})();
</script>
@endpush
