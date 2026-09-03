{{-- 문자로 보낸 주소가 여는 자리. 로그인 없이 그 건의 증빙만 보인다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>주문 {{ $order->order_number }} 증빙</title>
  <style>
    :root { --ink:#101317; --sub:#6b7280; --line:#e5e7eb; --brand:#28798B; --bg:#f6f7f9; }
    * { box-sizing:border-box; }
    body { margin:0; padding:24px 16px; background:var(--bg); color:var(--ink);
           font-family:'Pretendard','Malgun Gothic',system-ui,sans-serif; }
    .wrap { max-width:520px; margin:0 auto; background:#fff; border:1px solid var(--line);
            border-radius:14px; overflow:hidden; }
    .hd { padding:18px 20px; border-bottom:1px solid var(--line); }
    .hd h1 { margin:0 0 4px; font-size:17px; }
    .hd p { margin:0; font-size:13px; color:var(--sub); }
    .list { padding:8px 0; }
    .row { display:flex; align-items:center; gap:12px; padding:13px 20px; border-bottom:1px solid var(--line); }
    .row:last-child { border-bottom:none; }
    .row .name { flex:1; min-width:0; }
    .row .name b { display:block; font-size:14px; font-weight:600; }
    .row .name span { font-size:12px; color:var(--sub); word-break:break-all; }
    .row a { flex-shrink:0; padding:7px 14px; border-radius:8px; background:var(--brand);
             color:#fff; font-size:13px; font-weight:600; text-decoration:none; }
    .empty { padding:36px 20px; text-align:center; color:var(--sub); font-size:14px; }
    .ft { padding:14px 20px; background:#fafbfc; font-size:12px; color:var(--sub); line-height:1.7; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hd">
      <h1>{{ $order->patient?->name ?? '고객' }}님 증빙</h1>
      <p>주문 {{ $order->order_number }}{{ $order->saleNoSuffix() }}</p>
    </div>

    @if($docs)
      <div class="list">
        @foreach($docs as $d)
          <div class="row">
            <div class="name">
              <b>{{ $d['label'] }}</b>
              <span>{{ $d['file'] }}{{ $d['at'] ? ' · ' . $d['at'] : '' }}</span>
            </div>
            <a href="{{ route('orders.docs.file', ['order' => $order->id, 'key' => $d['key']]) }}">받기</a>
          </div>
        @endforeach
      </div>
    @else
      <div class="empty">보낼 수 있는 증빙이 아직 없습니다.</div>
    @endif

    <div class="ft">
      이 주소는 {{ $days }}일 뒤에는 열리지 않습니다.<br>
      콜로플라스트 코리아 주식회사
    </div>
  </div>
</body>
</html>
