{{-- 지자체 청구 — 등기 발송 --}}
{{--
  공단은 사이트에 입력하고 올리지만 지자체는 서류를 등기로 보낸다. 위임 절차가 없고 서류
  목록도 다르다. 그래서 공단 서식을 그대로 보여 주는 대신, 보낼 서류를 모아 주고 보낸
  기록을 남기는 자리로 만든다.
--}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>지자체 청구 — {{ $order->order_number }}</title>
<style>
  :root {
    --ink:#111827; --sub:#6b7280; --line:#e5e7eb; --bg:#f7f8fa; --pri:#28798B;
    --ok:#166534; --ok-bg:#f0fdf4; --danger:#b91c1c; --danger-bg:#fef2f2;
    --warn:#b45309; --warn-bg:#fffbeb;
  }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Malgun Gothic','맑은 고딕',sans-serif; background:var(--bg); color:var(--ink); font-size:13px; }
  .wrap { max-width:760px; margin:0 auto; padding:18px 16px 40px; }

  .hd { background:#fff; border:1px solid var(--line); border-radius:10px; padding:14px 16px; margin-bottom:14px; }
  .hd h1 { font-size:16px; margin-bottom:3px; }
  .hd .sub { font-size:12px; color:var(--sub); }
  .tags { margin-top:10px; display:flex; gap:6px; flex-wrap:wrap; }
  .tag { font-size:11px; font-weight:700; border-radius:6px; padding:3px 9px;
         background:var(--warn-bg); border:1px solid #f0d9ae; color:var(--warn); }

  .card { background:#fff; border:1px solid var(--line); border-radius:10px; margin-bottom:14px; overflow:hidden; }
  .card-hd { padding:10px 14px; background:#fafbfc; border-bottom:1px solid var(--line);
             font-size:13px; font-weight:700; display:flex; align-items:center; gap:8px; }
  .card-hd .grow { flex:1; }
  .card-bd { padding:12px 14px; }

  .doc { display:flex; align-items:center; gap:10px; padding:8px 14px; border-bottom:1px solid #f2f4f6; }
  .doc:last-child { border-bottom:none; }
  .doc-name { font-weight:700; }
  .doc-note { font-size:11px; color:var(--sub); font-weight:400; margin-top:2px; }
  .have { font-size:11px; font-weight:700; color:var(--ok); }
  .havent { font-size:11px; font-weight:700; color:var(--danger); }
  .grow { flex:1; }
  .lnk { color:var(--pri); font-weight:700; font-size:11px; text-decoration:none; }
  .lnk:hover { text-decoration:underline; }

  .row { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
  .row label { width:96px; flex-shrink:0; font-size:12px; color:var(--sub); }
  input[type=text], input[type=date], input[type=file], textarea {
    flex:1; min-width:0; border:1px solid #c9d1d9; border-radius:6px; padding:6px 9px;
    font-size:13px; font-family:inherit; background:#fff;
  }
  textarea { resize:vertical; min-height:52px; }
  .btn { border:1px solid var(--pri); background:var(--pri); color:#fff; border-radius:7px;
         padding:7px 15px; font-size:12px; font-weight:700; cursor:pointer; }
  .btn:hover { opacity:.9; }

  table.hist { width:100%; border-collapse:collapse; font-size:12px; }
  table.hist th, table.hist td { border-bottom:1px solid #f2f4f6; padding:8px 14px; text-align:left; }
  table.hist th { background:#fafbfc; font-size:11px; color:var(--sub); font-weight:700; }
  .empty { padding:14px; color:var(--sub); font-size:12px; }
  .ok-bar { background:var(--ok-bg); border:1px solid #86efac; color:var(--ok);
            border-radius:8px; padding:9px 12px; font-size:12px; margin-bottom:14px; font-weight:700; }
</style>
</head>
<body>
<div class="wrap">

  @if(session('status'))
    <div class="ok-bar">{{ session('status') }}</div>
  @endif

  <div class="hd">
    <h1>지자체 청구</h1>
    <div class="sub">
      {{ $order->patient?->name ?? '—' }} · {{ $order->order_number }}@if($prescription) · {{ $prescription->rx_number }}@endif
    </div>
    <div class="tags">
      <span class="tag">{{ $agencyLabel }}</span>
      @if($prescription?->local_gov)
        <span class="tag">{{ $prescription->local_gov }}</span>
      @else
        <span class="tag">관할 지자체가 지정되지 않았습니다</span>
      @endif
      @if($prescription?->benefit_class)
        <span class="tag">급여구분 {{ $prescription->benefit_class }}</span>
      @endif
    </div>
  </div>

  {{-- 공단과 달리 위임 절차가 없다. 그 사실을 적어 두지 않으면 담당자가 찾는다. --}}
  <div class="card">
    <div class="card-hd">보낼 서류 <span class="grow"></span>
      <span style="font-weight:400;font-size:11px;color:var(--sub);">위임 등록 절차는 없습니다</span>
      {{-- 하나씩 눌러 내려받으면 다섯 번 누르는 동안 한둘을 빠뜨린다. 빠진 채로 부치면
           반려되어 돌아오고, 그때는 이미 우편 값이 나간 뒤다(요청서 10쪽). --}}
      <a class="lnk" style="margin-left:8px;font-weight:600;"
         href="{{ route('nhis.assist.bundle', $order) }}" target="_blank" rel="noopener">
        한 묶음으로 인쇄
      </a>
    </div>
    @foreach($documents as $d)
      <div class="doc">
        <div>
          <div class="doc-name">{{ $d['name'] }}</div>
          @if($d['note'])<div class="doc-note">{{ $d['note'] }}</div>@endif
        </div>
        <div class="grow"></div>
        @if($d['url'])
          <span class="have">보유</span>
          <a class="lnk" href="{{ $d['url'] }}" target="_blank" rel="noopener">내려받기</a>
        @else
          <span class="havent">미보유</span>
        @endif
      </div>
    @endforeach
  </div>

  {{-- 보냈다는 증거. 나중에 「안 왔다」는 말을 받았을 때 댈 것이 이것뿐이다. --}}
  <div class="card">
    <div class="card-hd">등기 발송 기록</div>
    <div class="card-bd">
      <form method="POST" action="{{ route('nhis.assist.localDispatch', $order) }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
          <label>발송일</label>
          <input type="date" name="sent_date" value="{{ old('sent_date', now()->format('Y-m-d')) }}" required>
        </div>
        <div class="row">
          <label>등기번호</label>
          <input type="text" name="registered_no" value="{{ old('registered_no') }}"
                 placeholder="우체국 등기번호" maxlength="50">
        </div>
        <div class="row">
          <label>발송 영수증</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
        </div>
        <div class="row" style="align-items:flex-start;">
          <label style="padding-top:6px;">메모</label>
          <textarea name="memo" maxlength="500">{{ old('memo') }}</textarea>
        </div>
        @error('receipt')<div style="color:var(--danger);font-size:11px;margin-bottom:8px;">{{ $message }}</div>@enderror
        <div style="text-align:right;">
          <button class="btn" type="submit">저장</button>
        </div>
      </form>
    </div>

    <table class="hist">
      <thead>
        <tr><th style="width:110px">발송일</th><th style="width:150px">등기번호</th><th>메모</th><th style="width:110px">영수증</th></tr>
      </thead>
      <tbody>
        @forelse($dispatches as $d)
          <tr>
            <td>{{ $d->sent_date?->format('Y-m-d') ?? '—' }}</td>
            <td>{{ $d->registered_no ?: '—' }}</td>
            <td>{{ $d->memo ?: '—' }}</td>
            <td>
              @if($d->receipt_path)
                <a class="lnk" href="{{ route('nhis.assist.localReceipt', $d) }}" target="_blank" rel="noopener">내려받기</a>
              @else
                <span style="color:var(--sub);font-size:11px;">없음</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="empty">아직 보낸 기록이 없습니다.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
