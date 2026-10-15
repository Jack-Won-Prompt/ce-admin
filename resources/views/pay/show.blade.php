{{-- 환자가 문자로 받은 주소를 눌러 여는 자리.
     로그인 없이 열리므로 우리 화면 껍데기를 쓰지 않는다 — 사이드바·메뉴가 보이면
     남의 시스템에 들어온 것처럼 읽힌다. 여기서는 낼 것과 낼 단추만 보인다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  {{-- 시험 자동 승인이 POST 로 부른다 — 이 화면은 독립 문서라 메타가 없었다 --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
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
    .ways { display:flex; flex-direction:column; gap:8px; }
    .way { display:flex; align-items:flex-start; gap:10px; padding:12px 14px;
           border:1px solid #E3E6E9; border-radius:10px; cursor:pointer; }
    .way:has(input:checked) { border-color:#28798B; background:#F0F7F8; }
    .way input { margin-top:3px; accent-color:#28798B; }
    .way span { display:block; font-size:12px; color:#6B7178; }
    .way b { display:block; font-size:14px; font-weight:700; color:#1F2329; }
    .closed { text-align:center; padding:28px 12px; }
    .closed .big { font-size:16px; font-weight:700; margin-bottom:6px; }
    .bank { background:#F0F7F8; border:1px solid #CFE3E7; border-radius:10px; padding:14px; margin-top:12px; }
    .bank .acc { font-size:17px; font-weight:800; letter-spacing:.3px; margin:4px 0; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    {{-- 「(E)」는 우리끼리 쓰는 표시다 — 환자가 보는 자리에는 내지 않는다 (2026-09-27 지시) --}}
    <h1>{{ \App\Models\Patient::실명($order->patient?->name) ?: '고객' }}님, 결제 안내입니다</h1>
    <p class="sub">{{ config('popbill.company.corp_name') ?: config('app.name') }}</p>

    <div class="row"><span class="k">주문번호</span><span class="v">{{ $order->order_number }}</span></div>
    <div class="row"><span class="k">제품</span><span class="v">{{ $order->product_name ?: '-' }}</span></div>
    <div class="row"><span class="k">결제 수단</span><span class="v">{{ $link->method_label }}</span></div>
    <div class="row"><span class="k">결제 금액</span><span class="v amount">{{ number_format($link->amount) }}원</span></div>
  </div>

  @if(!$link->is_open)
    {{-- 낼 수 없는 상태를 그 자리에서 알려 준다. 눌러도 안 되는 단추만 보이면 왜인지 알 수 없다. --}}
    <div class="card closed">
      <div class="big">
        @if($link->status === 'paid') 결제가 완료된 건입니다
        @elseif($link->status === 'expired') 결제 기한이 만료되었습니다
        @elseif($link->status === 'cancelled') 취소된 결제 요청입니다
        @else 현재 결제가 불가능합니다
        @endif
      </div>
      <div class="sub" style="margin:0;">담당자에게 문의해 주시기 바랍니다.</div>
    </div>

  @elseif($link->method === \App\Models\PaymentLink::METHOD_BANK)
    {{-- 무통장입금은 토스를 타지 않는다 — 계좌를 보여 주고 입금 확인은 사람이 한다 --}}
    <div class="card">
      <div style="font-weight:700;">아래 계좌로 입금해 주시기 바랍니다</div>
      <div class="bank">
        <div>{{ config('toss.virtual_account.fallback_bank') ?: '은행 정보 없음' }}</div>
        <div class="acc">{{ config('toss.virtual_account.fallback_account') ?: '-' }}</div>
        <div>예금주 {{ config('popbill.company.corp_name') ?: config('app.name') }}</div>
      </div>
      <p class="note">입금자명을 주문자 성함({{ \App\Models\Patient::실명($order->patient?->name) ?: '주문자' }})으로 입력해 주시면 신속하게 확인됩니다.
        입금 확인에는 다소 시간이 소요될 수 있습니다.</p>
    </div>

  @else
    <div class="card">
      {{-- **링크페이는 카드 결제만 한다** (2026-10-15 지시).

           여태 이 자리에서 가상계좌도 고를 수 있었다. 그래서 담당자가 「링크페이」로
           안내해도 환자가 가상계좌를 골라, 안내한 수단과 실제 받은 수단이 갈렸다.

           앞으로 상담 담당자가 환자에게 결제 수단을 물어 고른 뒤 보낸다 —
           링크페이는 카드, 가상계좌는 계좌 입금으로 한 가지씩만 연다.
           가상계좌로 받을 건은 결제전송에서 「가상계좌」를 골라 보낸다. --}}
      <div style="font-weight:700; margin-bottom:4px;">카드로 결제합니다</div>
      <p class="sub" style="margin:0;">아래 버튼을 누르시면 토스페이먼츠 결제창이 열립니다.</p>
      <button class="btn" id="payBtn">{{ number_format($link->amount) }}원 결제</button>
      <p class="note">결제는 토스페이먼츠를 통해 처리되며, 카드 정보는 당사 서버에 저장되지 않습니다.</p>
    </div>
  @endif
</div>

@if($link->is_open && $link->method !== \App\Models\PaymentLink::METHOD_BANK)
@php
  /* 품명이 「-」로만 적힌 건이 있다. 그대로 보내면 토스 결제창 상품명이 「-」가 된다 —
     무엇을 사는지 모르는 채로 카드를 꺼내게 된다. 빈 것으로 치고 주문번호를 적는다. */
  $rawName      = trim((string) $order->product_name, " 	-");
  $orderName    = mb_strimwidth($rawName !== '' ? $rawName : '주문 ' . $order->order_number, 0, 90, '…');
  /* 토스에 보내는 이름은 **고른 결제수단에 따라 뜻이 다르다** (2026-10-13 확인요청).

     가상계좌를 고르면 이 값이 그대로 **예금주**가 된다 — 환자가 은행 앱에서 보는
     「받는 사람」이다. 2026-10-12 지시로 여기에 **상호**를 보낸다(서버가 발급하는 길과
     같은 값 — VirtualAccountService::예금주). 그 전에는 환자 이름을 보냈고, 이 자리만
     bare() 를 써서 동명이인 꼬리가 남았다 — 홍영철A 님 건이 그랬다
     (주문 EUD202610121149412 · 예금주 「홍영철A」로 발급).

     **그런데 카드ㆍ간편결제에서는 같은 값이 「구매자명」이 된다.** 10-12 에 상호로 바꾸면서
     카드ㆍ간편결제까지 함께 바뀌어, 토스 「신용ㆍ체크카드 조회」의 구매자명이
     「콜로플라스트 코리아 주식회사」로 찍혔다 — 산 사람은 환자인데 회사 이름이 적힌 것이다.
     결제 내역을 사람으로 찾을 수 없고, 분쟁이 나면 누가 샀는지 토스 쪽 기록이 말해 주지
     않는다.

     그래서 둘을 갈라 짓는다. 가상계좌면 상호, 그 밖이면 환자 이름이다.
     환자 이름은 Patient::실명 으로 짓는다 — 동명이인 꼬리는 우리 표시이지 사람 이름이
     아니라 밖으로 내보내지 않는다(2026-10-06 · SR #90ㆍ#102). */
  $예금주이름 = \App\Support\PayNames::토스에보낼이름($order, 'VIRTUAL_ACCOUNT');
  $구매자이름 = \App\Support\PayNames::토스에보낼이름($order, 'CARD');
@endphp
<script>
  /* 결제창을 연다. 금액과 주문번호는 서버가 정한 값을 그대로 쓴다 —
     브라우저에서 만든 값으로 결제하면 얼마든지 바꿔 낼 수 있다.
     돌아온 뒤에도 서버가 승인(confirm)까지 마쳐야 낸 것으로 적는다.

     한때 결제위젯(widgets)으로 열었다. 이 상점의 위젯 설정에는 브랜드페이만 들어 있어
     「등록할 수 있는 결제 수단이 존재하지 않습니다」로 멈춰 섰다 — 그건 카드를 미리
     등록해 두고 쓰는 자리고, 우리는 이 건 하나를 지금 받는 자리다. 결제창(payment)은
     상점 쪽 화면 설정을 타지 않고 카드창을 바로 연다. */
  /* 시험 환경이면 결제창을 열지 않고 바로 승인한다 (2026-09-16 지시).

     결제창을 끝까지 지나려면 카드사 앱 인증과 보안프로그램 설치를 거쳐야 해서,
     결제 뒤에 도는 일(세무 서류ㆍ카드매출전표ㆍ창고 확정)을 화면으로 확인할 길이
     없었다. 서버가 사용 환경을 다시 보므로 운영에서는 이 길이 열리지 않는다. */
  @if($autoPay ?? false)
  (function () {
    const 자리 = document.querySelector('.pay-action') ?? document.body;
    const 알림 = document.createElement('p');
    알림.style.cssText = 'margin:10px 0 0;font-size:12px;color:#B54708;font-weight:600;';
    알림.textContent = '시험 환경입니다 — 결제창 없이 바로 승인합니다…';
    자리.appendChild(알림);

    fetch(@json(route('pay.simulate', ['token' => $link->token])), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
      },
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          알림.textContent = res.message + ' 잠시 뒤 결과 화면으로 넘어갑니다.';
          setTimeout(() => { location.href = res.url; }, 900);
        } else {
          알림.style.color = '#B42318';
          알림.textContent = res.message || '시험 승인에 실패했습니다.';
        }
      })
      .catch(() => {
        알림.style.color = '#B42318';
        알림.textContent = '시험 승인을 부르지 못했습니다.';
      });
  })();
  @else
  (function () {
    {{-- 토스에 줄 주문 이름은 길이 제한이 있다. 미리 잘라 둔다 —
         @json 은 인자를 flags·depth 로 읽어, 자르는 일을 여기서 하면 어긋난다. --}}
    const AMOUNT   = {{ (int) $link->amount }};
    /* 토스에 줄 주문번호. 우리 토큰을 그대로 쓰면 두 번째 시도가 막힌다 —
       가상계좌를 받아 두고 카드로 바꿔 내려 하거나, 한 번 실패한 뒤 다시 누르면
       「이미 승인 및 취소가 진행된 중복된 주문번호」로 되돌아온다. 시도마다 다른
       꼬리를 붙인다. 어느 링크의 시도인지는 앞자리로 그대로 읽힌다.
       (돌아온 뒤 어느 건인지는 주소의 토큰으로 찾으므로 이 번호에 기대지 않는다) */
    const ORDER_BASE = @json(substr($link->token, 0, 40));
    const newOrderId = () => ORDER_BASE + '-' + Math.random().toString(36).slice(2, 10);
    const btn      = document.getElementById('payBtn');
    const VA_HOURS = {{ (int) config('toss.virtual_account.valid_hours', 72) }};
    /* 가상계좌의 예금주와, 카드ㆍ간편결제의 구매자명은 서로 다른 값이다 */
    const VA_HOLDER  = @json($예금주이름);
    const BUYER_NAME = @json($구매자이름);

    let payment;
    try {
      /* 사람마다 하나씩 붙는 표를 준다 — 비회원으로 열면 저장해 둔 카드를 쓰지 못한다 */
      payment = TossPayments(@json($clientKey)).payment({ customerKey: @json($customerKey) });
    } catch (e) {
      btn.disabled = true;
      btn.textContent = '결제창을 열 수 없습니다';
      console.error(e);
      return;
    }

    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try {
        /* 링크페이는 카드 한 가지다 (2026-10-15 지시) — 고르는 칸이 없다 */
        const way = 'CARD';

        /* 고른 방법의 칸만 싣는다 — 가상계좌인데 card 를 함께 주면 토스가
           「card는 정의되지 않은 파라미터입니다」로 되돌린다 */
        const opts = {
          method:     way,
          amount:     { currency: 'KRW', value: AMOUNT },
          orderId:    newOrderId(),
          orderName:  @json($orderName),
          /* 가상계좌면 예금주(상호), 그 밖이면 산 사람의 이름 — 위 주석 참고 */
          customerName: (way === 'VIRTUAL_ACCOUNT') ? VA_HOLDER : BUYER_NAME,
          successUrl: @json(route('pay.done', $link->token)),
          failUrl:    @json(route('pay.done', $link->token)),
        };

        if (way === 'VIRTUAL_ACCOUNT') {
          /* 현금영수증은 우리가 발행한다(팝빌) — 토스에까지 맡기면 두 번 나간다 */
          opts.virtualAccount = { cashReceipt: { type: '미발행' }, validHours: VA_HOURS };
        } else {
          opts.card = { useEscrow: false, flowMode: 'DEFAULT', useCardPoint: false, useAppCardOnly: false };
        }

        /* **보낸 값을 적어 둔다** (2026-10-13 지시).

           토스는 승인 응답에 구매자명을 돌려주지 않는다. 결제창이 열리고 나면 무엇을
           보냈는지 되짚을 자리가 없어, 2026-10-12 에 구매자명이 상호로 나간 일을
           확인할 때 코드가 바뀐 시각으로 가려 세어야 했다.

           **결제를 막지 않는다.** 적는 일이 늦거나 실패해도 결제창은 그대로 열린다 —
           keepalive 로 띄워 두고 기다리지 않는다. 이름과 금액은 서버가 다시 짓는다. */
        try {
          fetch(@json(route('pay.attempt', $link->token)), {
            method:    'POST',
            keepalive: true,
            headers: {
              'Content-Type': 'application/json',
              'Accept':       'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            body: JSON.stringify({
              orderId:   opts.orderId,
              method:    way,
              orderName: opts.orderName,
            }),
          }).catch(() => {});
        } catch (e) { /* 적지 못해도 결제는 간다 */ }

        await payment.requestPayment(opts);
      } catch (e) {
        btn.disabled = false;
        /* 환자가 스스로 닫은 것은 잘못이 아니다 — 말없이 단추만 되살린다 */
        if (e && e.code === 'USER_CANCEL') return;
        ceAlert((e && e.message) || '결제를 시작할 수 없습니다.', { tone: 'danger' });
      }
    });
  })();
  @endif</script>
@endif

@include('partials.dialog', ['문구화면' => '결제(고객)'])
</body>
</html>
