@extends('layouts.app')

@section('title', $r->receipt_no)
@section('page-title', $r->typeLabel() . ' · ' . $r->receipt_no)
@section('breadcrumb', '홈 - 주문 - 교환/반품/취소 - 상세')

{{-- 「목록으로」는 액자 밖에서만 둔다. 교환·반품·취소 화면의 상세 내용 탭은 이 화면을
     그대로 들여오는데, 거기서는 옆의 「조회 결과」 탭이 이미 돌아가는 길이라
     같은 일을 하는 단추가 하나 더 서 있었다. 주문 상세·창고 알림에서 곧장 들어온
     경우에는 돌아갈 길이 이것뿐이라 그대로 둔다. --}}
@section('header-actions')
@unless(request()->boolean('frame'))
<a href="{{ route('order-returns.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록으로
</a>
@endunless
@endsection

@push('styles')
<style>
  .rt-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:14px; }
  /* 가로 탭 — 판 아홉을 여섯 묶음으로 세웠다. 탭줄은 전역 .pnl-tabs 를 쓴다. */
  .rt-tabwrap { padding-bottom:14px; }
  .rt-pane { display:none; padding:14px 16px 0; }
  .rt-pane.active { display:block; }
  /* 탭 안에서는 카드가 다시 테두리를 두르지 않는다 — 판 안의 판이 되어 겹쳐 보인다 */
  .rt-pane .rt-card { border:none; border-radius:0; margin-bottom:14px; background:transparent; }
  .rt-pane .rt-card:last-child { margin-bottom:0; }
  .rt-pane .rt-hd { padding:0 0 9px; border-bottom:1px solid var(--border); }
  .rt-pane .rt-bd { padding:12px 0 0; }
  .rt-hd { padding:11px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700;
           display:flex; align-items:center; gap:8px; }
  .rt-hd .grow { flex:1; }
  .rt-bd { padding:14px 16px; }
  .rt-kv { display:flex; padding:7px 0; border-bottom:1px solid var(--border-light); font-size:13px; }
  .rt-kv:last-child { border-bottom:none; }
  .rt-kv > span:first-child { width:120px; flex-shrink:0; color:var(--text-muted); }
  .rt-kv > span:last-child  { flex:1; font-weight:500; }
  /* 환불 처리 자취 — 두 칸으로 세우고, 긴 글은 한 줄을 다 쓴다 */
  .rt-refund { display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; }
  .rt-rf { display:flex; flex-direction:column; gap:4px; min-width:0; }
  .rt-rf-wide { grid-column:1 / -1; }
  .rt-rf > label { font-size:12px; color:var(--text-muted); }
  .rt-rf .form-control { height:32px; font-size:13px; }
  .rt-rf-actions { grid-column:1 / -1; display:flex; justify-content:flex-end; }
  @media (max-width: 720px) { .rt-refund { grid-template-columns:1fr; } }

  .log { display:flex; gap:10px; padding:8px 0; border-bottom:1px solid var(--border-light); font-size:12px; }
  .log:last-child { border-bottom:none; }
  .log-when { width:120px; flex-shrink:0; color:var(--text-muted); }
  .log-move { width:150px; flex-shrink:0; font-weight:700; }
  .ok-bar { background:var(--primary-light); border:1px solid var(--primary-200); color:var(--primary);
            border-radius:8px; padding:9px 12px; font-size:12px; margin-bottom:14px; font-weight:700; }

  /* 진행 단계 — 절차서의 칸 하나가 칩 하나다 */
  .rt-steps { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
  .rt-step { border:1px solid var(--border); border-radius:8px; padding:6px 10px; min-width:92px;
             background:var(--gray-50); }
  .rt-step b { display:block; font-size:12px; font-weight:700; color:var(--gray-700); }
  .rt-step span { display:block; font-size:10px; color:var(--text-muted); margin-top:2px; }
  .rt-step.done { background:var(--primary-light); border-color:var(--primary-200); }
  .rt-step.done b { color:var(--primary); }
  .rt-step.now { background:var(--primary); border-color:var(--primary); }
  .rt-step.now b, .rt-step.now span { color:#fff; }
  .rt-late { color:#B54708; font-weight:700; font-size:12px; }
  .rt-go { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:12px;
           padding-top:12px; border-top:1px solid var(--border-light); }
  .rt-go input[type=text] { flex:1; min-width:180px; height:32px; }
  .rt-locked { font-size:12px; color:#B54708; }
</style>
@endpush

@section('content')

@if(session('status'))<div class="ok-bar">{{ session('status') }}</div>@endif

{{-- 판이 아홉이라 세로로 늘어놓으면 아래 것을 보려고 계속 굴려야 했다. 붙어 있는
     것끼리 묶어 가로 탭으로 세운다 — 차례는 그대로다. 뒤 판에서 정한 값을 앞 판이
     쓰는 자리가 있어, 차례를 바꾸면 화면이 통째로 죽는다. --}}
<div class="ds-grid-card rt-tabwrap">
  <div class="pnl-tabs">
      <button type="button" class="pnl-tab active" data-rtab="apply" onclick="rtTab(this)">신청 내용</button>
      <button type="button" class="pnl-tab" data-rtab="notice" onclick="rtTab(this)">환자 안내ㆍ환불</button>
      <button type="button" class="pnl-tab" data-rtab="flow" onclick="rtTab(this)">진행 단계</button>
      <button type="button" class="pnl-tab" data-rtab="items" onclick="rtTab(this)">반품 품목</button>
      <button type="button" class="pnl-tab" data-rtab="issue" onclick="rtTab(this)">발행ㆍ연계</button>
      <button type="button" class="pnl-tab" data-rtab="logs" onclick="rtTab(this)">처리 이력</button>
  </div>

  <div class="rt-pane active" data-rtab="apply">
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
    <div class="rt-kv"><span>이름</span><span>{{ $r->order?->patient?->name ?? '—' }}</span></div>
    <div class="rt-kv"><span>제품</span><span>{{ $r->order?->product_name ?? '—' }}</span></div>
    <div class="rt-kv"><span>주문 금액</span><span>{{ $r->order ? number_format($r->order->total_amount) . '원' : '—' }}</span></div>
    <div class="rt-kv"><span>배송지</span><span>{{ $r->order?->shipping_address ?? '—' }}</span></div>
  </div>
</div>
<div class="rt-card">
  <div class="rt-hd">신청 내용</div>
  <div class="rt-bd">
    <div class="rt-kv"><span>접수번호</span><span>{{ $r->receipt_no }}</span></div>
    {{-- 진행 단계 카드를 걷어냈으므로 지금 어느 단계인지는 여기에 남긴다.
         어디까지 왔는지 모르면 다음에 무엇을 할지 정할 수 없다. --}}
    <div class="rt-kv"><span>상태</span><span>{{ \App\Models\OrderReturn::STATUS_LABELS[$r->status] ?? $r->status }}</span></div>
    {{-- 창고가 어디까지 했는가 — 우리 단계와 다른 것을 잰다(요청서 4쪽).
         창고가 알려 주기 전에는 빈칸이라 아예 세우지 않는다. --}}
    @if($r->pl3_status_label)
      <div class="rt-kv"><span>3PL 상태</span><span>{{ $r->pl3_status_label }}{{ $r->pl3_status_at ? ' · ' . $r->pl3_status_at->format('Y-m-d H:i') : '' }}</span></div>
    @endif
    <div class="rt-kv"><span>사유</span><span>{{ $r->scenarioLabel() }}{{ $r->is_partial ? ' · 부분' : '' }}</span></div>
    <div class="rt-kv"><span>신청 사유</span><span>{{ \App\Models\OrderReturn::reasonLabel($r->reason_code) }}
      @php $rr = \App\Models\ReturnReason::table()[$r->reason_code] ?? null; @endphp
      @if($rr)
        {{-- 사유가 정하는 것 — 담당자가 다음에 무엇이 일어날지 미리 안다(요청서 6쪽) --}}
        <span style="font-size:11px;color:var(--text-muted);margin-left:6px;">
          금액조정 {{ $rr->adjusts_amount ? '있음' : '없음' }} ·
          발행 {{ $rr->includes_issue ? '포함' : '불포함' }}
        </span>
      @endif
    </span></div>
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
    <div class="rt-kv"><span>접수자</span><span>{{ $r->creator?->name ?? '—' }}</span></div>
    <div class="rt-kv"><span>승인자</span><span>{{ $r->approver?->name ?? '—' }}{{ $r->approved_at ? ' · ' . $r->approved_at->format('Y-m-d') : '' }}</span></div>
    {{-- 원 주문이 언제 나갔는가 — 청구 기한도 반품 기한도 이 날에서 센다 --}}
    <div class="rt-kv"><span>출고일자</span><span>{{ $r->order?->shipped_at?->format('Y-m-d') ?? '—' }}</span></div>
    @if($r->collect_tracking_no)
      <div class="rt-kv"><span>수거 송장</span><span>{{ $r->collect_tracking_no }}</span></div>
    @endif
    {{-- 원 주문의 가상계좌 — 토스가 발급한 것이라 여기 옮겨 적지 않고 그대로 본다 --}}
    @if($r->order?->tossPayment?->account_number)
      <div class="rt-kv"><span>가상계좌</span><span>{{ $r->order->tossPayment->bank }} {{ $r->order->tossPayment->account_number }} {{ $r->order->tossPayment->customer_name }}</span></div>
    @endif
    {{-- 무엇을 물렸는가 — 발행은 주문이 적고 있어 그 값을 그대로 본다 --}}
    @if($r->order?->cash_receipt_cancelled_at || $r->order?->tax_invoice_cancelled_at)
      <div class="rt-kv"><span>발행 취소</span><span>
        @if($r->order?->cash_receipt_cancelled_at)현금영수증 {{ $r->order->cash_receipt_cancelled_at->format('Y-m-d') }}@endif
        @if($r->order?->tax_invoice_cancelled_at) · 세금계산서 {{ $r->order->tax_invoice_cancelled_at->format('Y-m-d') }}@endif
      </span></div>
    @endif
  </div>
</div>

{{-- 환불을 실제로 처리한 자취 ───────────────────────────
     요청서 4쪽. 카드 취소 승인번호ㆍ통장을 물린 날ㆍ환불분 현금영수증 번호는 팝빌과
     토스 화면을 보며 담당자가 옮겨 적는 값이다. 우리가 만들 수 없어 적는 자리를 둔다.
     단계는 여기서 옮기지 않는다 — 그것은 아래 「진행 단계」가 절차서대로 한다. --}}
  </div>

  <div class="rt-pane" data-rtab="notice">
{{-- 환자에게 알린다 ────────────────────────────────────
     절차서의 「접수자 → 환자 inform」. 창고 사건마다 저절로 보내지 않는다 — 밖으로
     나가는 말이라 무를 수 없고, 검수중ㆍ입고중처럼 환자가 알 까닭이 없는 걸음도 있다.
     무엇을 알릴지는 접수자가 정한다. --}}
<div class="rt-card">
  <div class="rt-hd">환자 안내</div>
  <div class="rt-bd">
    @if($r->order?->patient?->mobile)
      <div class="rt-kv"><span>보낼 곳</span><span>{{ $r->order->patient->name }} · {{ $r->order->patient->mobile }}</span></div>
      <div class="rt-kv" style="border-bottom:none;"><span>보낼 말</span><span style="white-space:pre-line;color:var(--text-secondary);font-weight:400;">{{ app(\App\Services\ReturnPatientNotice::class)->compose($r) }}</span></div>
      <form method="POST" action="{{ route('order-returns.notifyPatient', $r) }}" class="rt-go" style="margin-top:10px;">
        @csrf
        <input type="text" name="extra" class="form-control" maxlength="200"
               placeholder="덧붙일 말 (없으면 비워 둡니다)">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('환자에게 실제로 발송됩니다. 계속할까요?');">
          <i class="bx bx-send"></i> 안내 보내기
        </button>
      </form>
    @else
      <div style="font-size:13px;color:var(--text-muted);">환자 연락처가 없어 보낼 수 없습니다.</div>
    @endif
  </div>
</div>
<div class="rt-card">
  <div class="rt-hd">환불 처리 이력</div>
  <form method="POST" action="{{ route('order-returns.update', $r) }}" class="rt-refund rt-bd">
    @csrf @method('PATCH')
    <div class="rt-rf"><label>카드사</label>
      <input type="text" name="card_issuer" class="form-control" maxlength="50" value="{{ old('card_issuer', $r->card_issuer) }}"></div>
    <div class="rt-rf"><label>유효기간</label>
      <input type="text" name="card_expiry" class="form-control" maxlength="7" placeholder="MM/YY" value="{{ old('card_expiry', $r->card_expiry) }}"></div>
    <div class="rt-rf"><label>승인번호</label>
      <input type="text" name="refund_approval_no" class="form-control" maxlength="50" value="{{ old('refund_approval_no', $r->refund_approval_no) }}"></div>
    <div class="rt-rf"><label>카드결제 취소</label>
      <input type="date" name="card_cancelled_at" class="form-control" value="{{ old('card_cancelled_at', $r->card_cancelled_at?->format('Y-m-d')) }}"></div>
    <div class="rt-rf"><label>무통장결제 취소</label>
      <input type="date" name="bank_cancelled_at" class="form-control" value="{{ old('bank_cancelled_at', $r->bank_cancelled_at?->format('Y-m-d')) }}"></div>
    <div class="rt-rf"><label>취급점</label>
      <input type="text" name="handling_branch" class="form-control" maxlength="100" placeholder="콜로 → 환자 환불" value="{{ old('handling_branch', $r->handling_branch) }}"></div>
    <div class="rt-rf"><label>환불기관정보</label>
      <input type="text" name="refund_agency" class="form-control" maxlength="200" value="{{ old('refund_agency', $r->refund_agency) }}"></div>
    <div class="rt-rf"><label>환불 현금영수증번호</label>
      <input type="text" name="refund_cash_receipt_no" class="form-control" maxlength="50" value="{{ old('refund_cash_receipt_no', $r->refund_cash_receipt_no) }}"></div>
    <div class="rt-rf"><label>환불 영수증 구분</label>
      <select name="refund_cash_receipt_type" class="form-control form-select">
        <option value="">선택</option>
        @foreach(\App\Models\OrderReturn::REFUND_RECEIPT_TYPES as $k => $label)
          <option value="{{ $k }}" @selected(old('refund_cash_receipt_type', $r->refund_cash_receipt_type) === $k)>{{ $label }}</option>
        @endforeach
      </select></div>
    {{-- 적요는 통장에 찍히는 글자, 담당자메모는 우리끼리 보는 글이다 --}}
    <div class="rt-rf rt-rf-wide"><label>적요</label>
      <input type="text" name="memo" class="form-control" maxlength="500" value="{{ old('memo', $r->memo) }}"></div>
    <div class="rt-rf rt-rf-wide"><label>담당자메모</label>
      <input type="text" name="staff_memo" class="form-control" maxlength="500" value="{{ old('staff_memo', $r->staff_memo) }}"></div>
    <div class="rt-rf-actions">
      <button type="submit" class="btn btn-primary">저장</button>
    </div>
  </form>
</div>
  </div>

  <div class="rt-pane" data-rtab="flow">
{{-- 진행 단계 ─────────────────────────────────────────
     「Unicorn 교환·반품 절차」의 칸 하나가 단계 하나다. 어디까지 왔고 다음은 누가
     무엇을 하는지가 보이지 않으면 담당자는 매번 절차서를 열어 봐야 한다.
     검수 확정과 전자 승인은 승인 권한이 있어야 눌린다. --}}
@php
  $flow       = $r->flow();
  $od         = $r->overdue();
  $canApprove = perm('order-returns', 'approve');
  $nexts      = $r->nextStatuses();
@endphp
<div class="rt-card">
  <div class="rt-hd">
    진행 단계 · {{ $r->scenarioLabel() }}
    <span class="grow"></span>
    @if($r->is_partial)<span style="font-size:11px;color:var(--text-muted);">부분 취소</span>@endif
    @if($od)<span class="rt-late">{{ $od[0] }} 기한 {{ $od[1] }}영업일 초과</span>@endif
  </div>
  <div class="rt-bd">
    <div class="rt-steps">
      @foreach($flow as $st)
        <div class="rt-step {{ $r->status === $st ? 'now' : ($r->reached($st) ? 'done' : '') }}">
          <b>{{ \App\Models\OrderReturn::STATUS_LABELS[$st] ?? $st }}</b>
          <span>{{ \App\Models\OrderReturn::STATUS_ACTORS[$st] ?? '' }}</span>
        </div>
      @endforeach
    </div>

    @if($r->hasDeadlines())
      {{-- 기한은 창고 입고일부터 센다. 접수일부터 세면 고객이 늦게 보낸 날까지
           창고가 뒤집어쓴다. --}}
      <div class="rt-kv"><span>창고 입고</span><span>
        {{ $r->arrived_at?->format('Y-m-d H:i') ?? '미입고 — 「검수중」으로 변경하는 시점이 입고일이 됩니다' }}
      </span></div>
      @if($r->arrived_at)
        <div class="rt-kv"><span>검수 기한</span><span>
          {{ $r->inspectDueAt()?->format('Y-m-d') }}
          <span style="color:var(--text-muted);font-weight:400;">
            (입고 +{{ config('returns.inspect_days') }}영업일 · 검수 확정까지)
          </span>
        </span></div>
        <div class="rt-kv"><span>{{ $r->type === \App\Models\OrderReturn::TYPE_EXCHANGE ? '출고' : '발행' }} 기한</span><span>
          {{ $r->finalDueAt()?->format('Y-m-d') }}
          <span style="color:var(--text-muted);font-weight:400;">
            (입고 +{{ config('returns.ship_days') }}영업일)
          </span>
        </span></div>
      @endif
    @endif

    <div class="rt-kv"><span>검수 확정</span><span>
      {{ $r->inspect_confirmed_at?->format('Y-m-d H:i') ?? '—' }}
      {{ $r->inspectConfirmer?->name ? '· ' . $r->inspectConfirmer->name : '' }}
    </span></div>
    <div class="rt-kv"><span>전자 승인</span><span>
      {{ $r->approved_at?->format('Y-m-d H:i') ?? '—' }}
      {{ $r->approver?->name ? '· ' . $r->approver->name : '' }}
      <span style="color:var(--text-muted);font-weight:400;">(승인 주체 {{ $r->approverRole() }})</span>
    </span></div>
    @if($r->scenario() === \App\Models\OrderReturn::SC_EXCHANGE_MIND)
      <div class="rt-kv"><span>입금 확인</span><span>{{ $r->payment_checked_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
    @endif
    @if($r->order_confirmed_at)
      <div class="rt-kv"><span>오더 확정</span><span>{{ $r->order_confirmed_at->format('Y-m-d H:i') }}</span></div>
    @endif

    {{-- 다음으로 갈 곳. 흐름에 없는 곳으로는 건너뛸 수 없다 —
         검수도 안 했는데 환불완료가 되는 식이면 단계를 둔 뜻이 없다. --}}
    @if($nexts)
      <form method="POST" action="{{ route('order-returns.advance', $r) }}" class="rt-go">
        @csrf
        <input type="text" name="reason" class="form-control" maxlength="500"
               placeholder="변경 사유 (선택 · 이력에 남습니다)">
        @foreach($nexts as $st)
          @php $locked = \App\Models\OrderReturn::needsApproval($st) && !$canApprove; @endphp
          <button type="submit" name="to_status" value="{{ $st }}"
                  class="ds-btn {{ $st === 'cancelled' ? '' : 'ds-btn-primary' }}"
                  @disabled($locked)
                  @if(in_array($st, ['credited', 'adjusted'], true))
                    onclick="return confirm('세금계산서·현금영수증이 실제로 처리됩니다. 계속할까요?');"
                  @endif>
            {{ \App\Models\OrderReturn::STATUS_LABELS[$st] ?? $st }}@if($locked) 🔒 @endif
          </button>
        @endforeach
      </form>
      @if(!$canApprove && collect($nexts)->contains(fn ($st) => \App\Models\OrderReturn::needsApproval($st)))
        <div class="rt-locked" style="margin-top:6px;">
          이 단계는 승인 권한({{ $r->approverRole() }})이 있어야 누를 수 있습니다 —
          설정 › 권한 그룹에서 「교환·반품 전자 승인」을 받으십시오.
        </div>
      @endif
    @else
      <div style="font-size:12px;color:var(--text-muted);margin-top:10px;">더 갈 단계가 없습니다.</div>
    @endif
  </div>
</div>
  </div>

  <div class="rt-pane" data-rtab="items">
{{-- 되돌리는 품목 — 부분 취소면 몇 개 가운데 몇 개인지가 여기서 보인다 --}}
@if($r->items->isNotEmpty())
<div class="rt-card">
  <div class="rt-hd">반품 품목 <span class="grow"></span>
    <span style="font-size:11px;font-weight:500;color:var(--text-muted);">
      {{ $r->is_partial ? '부분 — 기관 청구 서류는 최종 청구분에 반영합니다' : '전체' }}
    </span>
  </div>
  <div class="rt-bd">
    @foreach($r->items as $it)
      <div class="rt-kv">
        <span>{{ $it->product_code ?: '—' }}@if($d = \App\Support\DeviceCode::for($it->product_code))<em style="font-style:normal;color:var(--text-muted);margin-left:5px;">{{ $d }}</em>@endif</span>
        <span>{{ $it->product_name ?: '—' }}
          · {{ number_format($it->quantity) }}@if($it->ordered_quantity)/{{ number_format($it->ordered_quantity) }}@endif개
          @if($it->copay) · 환불 {{ number_format($it->refundAmount()) }}원 @endif
        </span>
      </div>
    @endforeach
  </div>
</div>
@endif
  </div>

  <div class="rt-pane" data-rtab="issue">
{{-- 마이너스 발행 · 금액조정 ───────────────────────────
     절차서의 마지막 칸이다. 팝빌은 운영으로 붙어 있어 여기서 부르는 취소·발행은
     국세청 신고까지 간다 — 사람이 누를 때만 돈다. --}}
@if($r->credit_issued_at || $r->credit_note || $r->adjust_so_no || $r->reached('refunded'))
<div class="rt-card">
  <div class="rt-hd">
    마이너스 발행
    @if($r->credit_note && perm('order-returns', 'send'))
      <form method="POST" action="{{ route('order-returns.issueCredit', $r) }}" style="margin-left:auto;">
        @csrf
        <button type="submit" class="ds-btn ds-btn-sm"
                onclick="return confirm('세금계산서·현금영수증을 실제로 처리합니다. 계속할까요?');">
          다시 시도
        </button>
      </form>
    @endif
  </div>
  <div class="rt-bd">
    <div class="rt-kv"><span>발행 처리</span><span>{{ $r->credit_issued_at?->format('Y-m-d H:i') ?? '아직' }}</span></div>
    <div class="rt-kv"><span>내용</span><span>{{ $r->credit_note ?: '—' }}</span></div>
    @if($r->needsAdjust())
      <div class="rt-kv"><span>금액조정 주문</span><span>
        {{ $r->adjust_so_no ?: '아직' }}
        {{ $r->adjusted_at ? '· ' . $r->adjusted_at->format('Y-m-d H:i') : '' }}
      </span></div>

      {{-- 조정 금액 — 부분 교환ㆍ부분 반품ㆍ자격 변경 셋이 여기 선다(2026-09-02 유형표).
           적어 두지 않으면 나중에 청구할 사람이 원 주문과 반품 줄을 놓고 다시 셈한다. --}}
      <form method="POST" action="{{ route('order-returns.adjust-amount', $r) }}"
            style="display:flex;align-items:center;gap:6px;padding:7px 0 0;border-top:1px dashed var(--border);margin-top:7px;">
        @csrf
        <span style="font-size:12px;color:var(--text-muted);flex-shrink:0;width:84px;">조정 금액</span>
        <select name="adjust_direction" class="form-control" style="width:132px;height:30px;font-size:12px;padding:2px 6px;">
          @foreach(\App\Models\OrderReturn::ADJ_DIRECTIONS as $k => $v)
            <option value="{{ $k }}" @selected(($r->adjust_direction ?? 'refund') === $k)>{{ $v }}</option>
          @endforeach
        </select>
        <input type="number" name="adjust_amount" min="0" step="1" class="form-control"
               style="width:120px;height:30px;font-size:12px;padding:2px 6px;text-align:right;"
               value="{{ $r->adjust_amount ?? $r->adjustedAmount() }}"
               placeholder="원">
        <button type="submit" class="ds-btn ds-btn-sm">저장</button>
        @if($r->adjust_amount === null)
          <span style="font-size:11px;color:var(--text-muted);">줄에서 셈한 값입니다 — 확인 후 저장하십시오.</span>
        @endif
      </form>
    @endif
  </div>
</div>
@endif
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
    @elseif($r->hasReturnSo())
      {{-- 검수는 3PL 이 한다. 그 결과를 눈으로 옮겨 적으면 잘못 적히고 언제 받았는지도
           남지 않는다 — 받아 온 뒤 확정은 Care team manager 가 누른다. --}}
      <form method="POST" action="{{ route('order-returns.pullInspection', $r) }}" style="margin-left:auto;display:inline;">
        @csrf
        <button type="submit" class="ds-btn ds-btn-sm">검수 결과 받기</button>
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
        원 판매주문을 취소했습니다 — 반품 제품이 없어 반품 주문을 생성하지 않습니다
      </span></div>
    @else
      <div class="rt-kv"><span>전달</span><span style="color:#B54708;font-weight:600;">
        {{ $r->withworks_error ?: '아직 알리지 못했습니다' }}
      </span></div>
    @endif
    <div class="rt-kv"><span>전달 시각</span><span>{{ $r->withworks_sent_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
  </div>
</div>
  </div>

  <div class="rt-pane" data-rtab="logs">
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
  </div>
</div>

@endsection

@push('scripts')
<script>
/* 탭 하나만 보인다. 어느 탭이었는지는 주소에 남겨, 걸음을 옮겨 화면이 다시 그려져도
   보던 자리로 돌아온다. */
function rtTab(btn) {
  const key = btn.dataset.rtab;
  document.querySelectorAll('.rt-tabwrap .pnl-tab').forEach(b => b.classList.toggle('active', b === btn));
  document.querySelectorAll('.rt-pane').forEach(p => p.classList.toggle('active', p.dataset.rtab === key));
  try { history.replaceState(null, '', location.pathname + '?tab=' + key); } catch (e) {}
}

(function () {
  const want = new URLSearchParams(location.search).get('tab');
  if (!want) return;
  const btn = document.querySelector('.rt-tabwrap .pnl-tab[data-rtab="' + want + '"]');
  if (btn) rtTab(btn);
})();
</script>
@endpush
