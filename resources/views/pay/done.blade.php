{{-- 결제창에서 돌아온 뒤. 성공했다고 그대로 믿지 않고 서버가 승인까지 마친 결과를 보여 준다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $ok ? '결제 완료' : '결제 실패' }} — {{ $link->order?->order_number }}</title>
  <style>
    body { margin:0; padding:24px 16px; background:#F4F5F7; color:#1F2329;
           font-family:'Pretendard','Apple SD Gothic Neo',sans-serif; font-size:14px; line-height:1.6; }
    .wrap { max-width:420px; margin:0 auto; }
    .card { background:#fff; border-radius:14px; padding:24px 20px; box-shadow:0 2px 12px rgba(0,0,0,.06); text-align:center; }
    .mark { width:56px; height:56px; border-radius:999px; margin:0 auto 12px; display:flex;
            align-items:center; justify-content:center; font-size:26px; font-weight:800; color:#fff; }
    .ok   { background:#28798B; }
    .no   { background:#D4304A; }
    h1 { font-size:18px; font-weight:700; margin:0 0 6px; }
    p  { margin:0; color:#6B7178; font-size:13px; }
    .rows { margin-top:18px; text-align:left; }
    .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #EEF0F2; }
    .row:last-child { border-bottom:none; }
    .row .k { color:#6B7178; }
    .row .v { font-weight:600; text-align:right; word-break:break-all; }
    .va { background:#F0F7F8; border:1px solid #CFE3E7; border-radius:10px; padding:14px; margin-top:14px; text-align:left; }
    .va .acc { font-size:17px; font-weight:800; margin:4px 0; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="mark {{ $ok ? 'ok' : 'no' }}">{{ $ok ? '✓' : '!' }}</div>
    <h1>{{ $ok ? '결제가 끝났습니다' : '결제하지 못했습니다' }}</h1>
    <p>{{ $ok ? '영수증은 문자로 안내드립니다.' : ($message ?: '다시 시도하시거나 담당자에게 문의해 주십시오.') }}</p>

    <div class="rows">
      <div class="row"><span class="k">주문번호</span><span class="v">{{ $link->order?->order_number }}</span></div>
      <div class="row"><span class="k">금액</span><span class="v">{{ number_format($link->amount) }}원</span></div>
      @if($ok)
        <div class="row"><span class="k">결제 시각</span><span class="v">{{ $link->paid_at?->format('Y-m-d H:i') }}</span></div>
      @endif
    </div>

    {{-- 가상계좌는 이 자리에서 계좌를 알려 줘야 한다 — 결제창을 닫으면 다시 볼 곳이 없다 --}}
    @php $va = $toss['virtualAccount'] ?? null; @endphp
    @if($ok && $va)
      <div class="va">
        <div style="font-weight:700;">아래 계좌로 입금해 주십시오</div>
        <div>{{ $va['bank'] ?? $va['bankCode'] ?? '' }}</div>
        <div class="acc">{{ $va['accountNumber'] ?? '' }}</div>
        <div>예금주 {{ $va['customerName'] ?? '' }}</div>
        @if(!empty($va['dueDate']))
          <div style="font-size:12px;color:#6B7178;margin-top:6px;">
            입금 기한 {{ \Illuminate\Support\Carbon::parse($va['dueDate'])->format('Y-m-d H:i') }}
          </div>
        @endif
      </div>
    @endif
  </div>
</div>
</body>
</html>
