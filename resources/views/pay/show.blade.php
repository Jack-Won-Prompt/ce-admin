{{-- 환자가 문자로 받은 주소를 눌러 여는 자리.
     로그인 없이 열리므로 우리 화면 껍데기를 쓰지 않는다 — 사이드바·메뉴가 보이면
     남의 시스템에 들어온 것처럼 읽힌다. 여기서는 낼 것과 낼 단추만 보인다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>결제 — {{ $order->order_number }}</title>
  <script src="https://js.tosspayments.com/v2/standard"></script>
  <style>
    * { box-sizing: border-box; }
    body { margin:0; padding:24px 16px; background:#F4F5F7; color:#1F2329;
           font-family:'Pretendard','Apple SD Gothic Neo',sans-serif; font-size:14px; line-height:1.6; }
    .wrap { max-width:420px; margin:0 auto; }
    .card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
    .card + .card { margin-top:12px; }
    h1 { font-size:17px; font-weight:700; margin:0 0 4px; }
    .sub { font-size:12px; color:#6B7178; margin:0 0 16px; }
    .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #EEF0F2; }
    .row:last-child { border-bottom:none; }
    .row .k { color:#6B7178; flex-shrink:0; }
    .row .v { font-weight:500; text-align:right; word-break:break-all; }
    .amount { font-size:22px; font-weight:800; color:#28798B; }
    .btn { display:block; width:100%; margin-top:16px; padding:14px; border:none; border-radius:10px;
           background:#28798B; color:#fff; font-size:15px; font-weight:700; cursor:pointer; }
    .btn:disabled { background:#C2C5C8; cursor:default; }
    .note { font-size:12px; color:#6B7178; margin-top:12px; }
    .closed { text-align:center; padding:28px 12px; }
    .closed .big { font-size:16px; font-weight:700; margin-bottom:6px; }
    .bank { background:#F0F7F8; border:1px solid #CFE3E7; border-radius:10px; padding:14px; margin-top:12px; }
    .bank .acc { font-size:17px; font-weight:800; letter-spacing:.3px; margin:4px 0; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>{{ $order->patient?->name ?? '고객' }}님, 결제 안내입니다</h1>
    <p class="sub">{{ config('popbill.company.corp_name') ?: config('app.name') }}</p>

    <div class="row"><span class="k">주문번호</span><span class="v">{{ $order->order_number }}</span></div>
    <div class="row"><span class="k">제품</span><span class="v">{{ $order->product_name ?: '-' }}</span></div>
    <div class="row"><span class="k">결제 방법</span><span class="v">{{ $link->method_label }}</span></div>
    <div class="row"><span class="k">결제 금액</span><span class="v amount">{{ number_format($link->amount) }}원</span></div>
  </div>

  @if(!$link->is_open)
    {{-- 낼 수 없는 상태를 그 자리에서 알려 준다. 눌러도 안 되는 단추만 보이면 왜인지 알 수 없다. --}}
    <div class="card closed">
      <div class="big">
        @if($link->status === 'paid') 결제가 끝난 건입니다
        @elseif($link->status === 'expired') 결제 기한이 지났습니다
        @elseif($link->status === 'cancelled') 취소된 결제 요청입니다
        @else 지금은 결제할 수 없습니다
        @endif
      </div>
      <div class="sub" style="margin:0;">담당자에게 문의해 주십시오.</div>
    </div>

  @elseif($link->method === \App\Models\PaymentLink::METHOD_BANK)
    {{-- 무통장입금은 토스를 타지 않는다 — 계좌를 보여 주고 입금 확인은 사람이 한다 --}}
    <div class="card">
      <div style="font-weight:700;">아래 계좌로 입금해 주십시오</div>
      <div class="bank">
        <div>{{ config('toss.virtual_account.fallback_bank') ?: '은행 정보 없음' }}</div>
        <div class="acc">{{ config('toss.virtual_account.fallback_account') ?: '-' }}</div>
        <div>예금주 {{ config('popbill.company.corp_name') ?: config('app.name') }}</div>
      </div>
      <p class="note">입금자명을 주문자 이름({{ $order->patient?->name ?? '주문자' }})으로 적어 주시면 확인이 빠릅니다.
        입금 확인까지 시간이 걸릴 수 있습니다.</p>
    </div>

  @else
    <div class="card">
      <div id="payment-method"></div>
      <div id="agreement"></div>
      <button class="btn" id="payBtn" disabled>{{ number_format($link->amount) }}원 결제</button>
      <p class="note">결제창은 토스페이먼츠에서 열립니다. 카드 정보는 우리 서버에 남지 않습니다.</p>
    </div>
  @endif
</div>

@if($link->is_open && $link->method !== \App\Models\PaymentLink::METHOD_BANK)
@php
  $orderName    = mb_strimwidth($order->product_name ?: '주문', 0, 90, '…');
  $customerName = $order->patient?->name ?? '';
@endphp
<script>
  /* 결제위젯을 띄운다. 금액과 주문번호는 서버가 정한 값을 그대로 쓴다 —
     브라우저에서 만든 값으로 결제하면 얼마든지 바꿔 낼 수 있다.
     돌아온 뒤에도 서버가 승인(confirm)까지 마쳐야 낸 것으로 적는다. */
  (async function () {
    {{-- 토스에 줄 주문 이름은 길이 제한이 있다. 미리 잘라 둔다 —
         @json 은 인자를 flags·depth 로 읽어, 자르는 일을 여기서 하면 어긋난다. --}}
    const AMOUNT   = {{ (int) $link->amount }};
    const ORDER_ID = @json($link->token);         // 토스에 줄 주문번호 — 우리 토큰을 그대로 쓴다
    const btn      = document.getElementById('payBtn');

    try {
      const toss   = TossPayments(@json($clientKey));
      const widget = toss.widgets({ customerKey: TossPayments.ANONYMOUS });

      await widget.setAmount({ currency: 'KRW', value: AMOUNT });
      await Promise.all([
        // 카드든 가상계좌든 결제창은 하나다 — 고른 방법은 문자와 이력에만 적힌다
        widget.renderPaymentMethods({ selector: '#payment-method', variantKey: 'DEFAULT' }),
        widget.renderAgreement({ selector: '#agreement', variantKey: 'AGREEMENT' }),
      ]);

      btn.disabled = false;
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
          await widget.requestPayment({
            orderId:    ORDER_ID,
            orderName:  @json($orderName),
            customerName: @json($customerName),
            successUrl: @json(route('pay.done', $link->token)),
            failUrl:    @json(route('pay.done', $link->token)),
          });
        } catch (e) {
          btn.disabled = false;
          alert(e?.message || '결제를 시작하지 못했습니다.');
        }
      });
    } catch (e) {
      btn.textContent = '결제창을 열 수 없습니다';
      console.error(e);
    }
  })();
</script>
@endif
</body>
</html>
