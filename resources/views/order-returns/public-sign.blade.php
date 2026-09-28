<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<title>교환ㆍ반품 최종 승인</title>
<style>
  :root { --primary:#3C82C4; --primary-50:#EEF5FC; --border:#E3E7EC; --muted:#6B7280;
          --danger:#B42318; --danger-bg:#FEE4E2; --warn:#B54708; --warn-bg:#FEF3C7;
          --ok:#067647; --ok-bg:#ECFDF3; }
  * { box-sizing:border-box; }
  body { margin:0; background:#F6F7F9; color:#1F2937; font-size:14px; line-height:1.55;
         font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',sans-serif; }
  .wrap { max-width:560px; margin:0 auto; padding:18px 14px 60px; }
  .card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:12px; }
  .brand { font-weight:800; letter-spacing:-.4px; color:var(--primary); }
  .lead  { color:var(--muted); font-size:13px; margin-top:6px; }
  h2 { font-size:15px; margin:0 0 10px; }

  .kv { display:flex; gap:10px; padding:7px 0; border-bottom:1px dashed var(--border); font-size:13px; }
  .kv:last-child { border-bottom:0; }
  .kv dt { width:100px; flex-shrink:0; color:var(--muted); margin:0; }
  .kv dd { margin:0; flex:1; font-weight:600; word-break:break-all; }

  .amt { margin-top:12px; padding:14px; border-radius:10px; text-align:center; }
  .amt.give { background:var(--primary-50); border:1px solid #CFE1F4; }
  .amt.take { background:var(--warn-bg); border:1px solid #F2C97D; }
  .amt .t { font-size:12.5px; font-weight:700; color:var(--muted); }
  .amt .n { font-size:28px; font-weight:800; letter-spacing:-1px; margin-top:4px; }
  .amt.give .n { color:var(--primary); }
  .amt.take .n { color:var(--warn); }
  .amt .s { font-size:12px; color:var(--muted); margin-top:5px; }

  .chip { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11.5px; font-weight:700; }
  .chip.ok  { background:var(--ok-bg); color:var(--ok); }
  .chip.bad { background:var(--danger-bg); color:var(--danger); }

  table.it { width:100%; border-collapse:collapse; font-size:12.5px; margin-top:4px; }
  table.it th, table.it td { padding:6px 4px; border-bottom:1px solid var(--border); text-align:left; }
  table.it th { color:var(--muted); font-weight:700; background:#FAFBFC; }
  table.it td.r, table.it th.r { text-align:right; }

  .note { margin-top:10px; padding:11px 13px; background:#FAFBFC; border:1px solid var(--border);
          border-radius:8px; font-size:12.5px; white-space:pre-wrap; }
  .shut { padding:20px 14px; text-align:center; }
  .shut .big { font-size:15px; font-weight:800; margin-bottom:6px; }

  canvas#sig { width:100%; height:180px; border:1px dashed #C7CDD4; border-radius:9px;
               background:#fff; touch-action:none; display:block; margin-top:8px; }
  .sig-bar { display:flex; justify-content:space-between; align-items:center; margin-top:7px; }
  .sig-bar .hint { font-size:12px; color:var(--muted); }

  .btn { display:block; width:100%; height:50px; border:0; border-radius:10px; font-size:15.5px;
         font-weight:800; cursor:pointer; margin-top:10px; }
  .btn.go { background:var(--primary); color:#fff; }
  .btn.go:disabled { background:#C7D6E6; cursor:not-allowed; }
  .btn.no { background:#fff; color:var(--danger); border:1px solid #F3B5AD; height:44px; font-size:14px; }
  .btn.sm { height:32px; width:auto; padding:0 12px; font-size:12.5px; margin:0;
            background:#fff; border:1px solid var(--border); color:#374151; font-weight:700; }

  #why { margin-top:8px; font-size:12.5px; color:var(--danger); font-weight:700; min-height:18px; }
  #rej { display:none; margin-top:10px; }
  #rej textarea { width:100%; min-height:76px; padding:9px 10px; font-size:13px; border-radius:8px;
                  border:1px solid var(--border); font-family:inherit; resize:vertical; }
  .done { text-align:center; padding:24px 14px; }
  .done .big { font-size:17px; font-weight:800; margin-bottom:8px; }
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div class="brand">콜로플라스트 · 교환ㆍ반품 최종 승인</div>
    <div class="lead">아래 내용을 확인하시고 서명해 주십시오. <b>서명하시면 즉시 처리됩니다.</b></div>
  </div>

@if ($닫힘)
  {{-- 링크가 닫힌 자리. 까닭을 적는다 — 「열 수 없습니다」만 보이면 담당자에게
       전화해도 무엇을 물어야 할지 서로 모른다. --}}
  <div class="card shut">
    <div class="big">서명할 수 없습니다</div>
    <div class="lead">{{ $닫힘 }}</div>
  </div>
@else

  <div class="card">
    <h2>승인 요청 내용</h2>
    <dl style="margin:0">
      <div class="kv"><dt>접수번호</dt><dd>{{ $r->receipt_no }}</dd></div>
      <div class="kv"><dt>구분</dt><dd>{{ $r->typeLabel() }}</dd></div>
      <div class="kv"><dt>주문번호</dt><dd>{{ $r->order?->order_number ?? '-' }}</dd></div>
      <div class="kv"><dt>고객</dt><dd>{{ $r->order?->patient?->name ?? '-' }}</dd></div>
      <div class="kv"><dt>입고 검수</dt><dd>
        @if ($r->inspect_result === \App\Models\OrderReturn::RESULT_DEFECT)
          <span class="chip bad">하자ㆍ수량 차이</span>
        @else
          <span class="chip ok">이상 없음</span>
        @endif
      </dd></div>
      <div class="kv"><dt>책임자 승인</dt><dd>
        {{ $r->inspectConfirmer?->name ?? '-' }}
        <span style="color:var(--muted);font-weight:400">{{ $r->inspect_confirmed_at?->format('Y-m-d H:i') }}</span>
      </dd></div>
    </dl>

    {{-- 서명하면 무슨 일이 일어나는지 한 줄로. 최종승인자가 보는 것은 이 숫자다. --}}
    @php $내주나 = $r->refundRoute() === \App\Models\OrderReturn::ROUTE_TOPUP; @endphp
    <div class="amt {{ $내주나 ? 'take' : 'give' }}">
      <div class="t">{{ $r->refundRouteLabel() }}</div>
      <div class="n">{{ number_format($금액) }}원</div>
      <div class="s">
        @if ($내주나)
          고객에게 <b>추가 청구</b>할 금액입니다. 서명 후 담당자가 전화로 안내한 다음 결제 링크를 발송합니다.
        @elseif ($r->refundRoute() === \App\Models\OrderReturn::ROUTE_PARTIAL)
          수납 금액 {{ number_format($받은것) }}원에서 차감 {{ number_format((int) $r->inspect_deduct_amount) }}원을 뺀 금액입니다.
        @else
          수납 금액 전액을 환불해 드립니다.
        @endif
      </div>
    </div>

    @if ($r->inspect_result === \App\Models\OrderReturn::RESULT_DEFECT)
      <div class="note"><b>차감 사유</b>@if ($r->inspect_defect_qty) · 수량 차이 {{ $r->inspect_defect_qty }}개@endif
{{ chr(10) }}{{ $r->inspect_defect_note ?: '(입력 내용 없음)' }}</div>
    @endif

    @if ($r->reason_text || $r->reason_code)
      <div class="note"><b>신청 사유</b>
{{ $r->reason_text ?: $r->reason_code }}</div>
    @endif
  </div>

  @if ($r->items->isNotEmpty())
  <div class="card">
    <h2>대상 품목</h2>
    <table class="it">
      <thead><tr><th>제품</th><th class="r">수량</th></tr></thead>
      <tbody>
      @foreach ($r->items as $it)
        <tr>
          <td>{{ $it->product_name }}@if ($it->product_code) <span style="color:var(--muted)">({{ $it->product_code }})</span>@endif</td>
          <td class="r">{{ number_format((int) $it->quantity) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @endif

  <div class="card">
    <h2>최종승인자 서명</h2>
    <div class="lead" style="margin-top:0">
      {{ $r->finalSignTarget?->name ?? '' }} 님, 아래 칸에 서명해 주십시오.
      @if ($r->final_sign_expires_at)
        <br>이 링크는 <b>{{ $r->final_sign_expires_at->format('m월 d일 H:i') }}</b> 까지 유효합니다.
      @endif
    </div>
    <canvas id="sig"></canvas>
    <div class="sig-bar">
      <span class="hint">화면을 터치하거나 마우스로 서명해 주십시오.</span>
      <button type="button" class="btn sm" onclick="서명지우기()">지우기</button>
    </div>

    <div id="why"></div>

    <button type="button" id="go" class="btn go" disabled onclick="보내기('sign')">서명하고 승인 — {{ number_format($금액) }}원</button>
    <button type="button" class="btn no" onclick="반려펴기()">반려하기</button>

    <div id="rej">
      <textarea id="reason" placeholder="반려 사유를 입력해 주십시오. 창고로 반송하여 재검수를 요청합니다."></textarea>
      <button type="button" class="btn no" onclick="보내기('reject')">반려로 보내기</button>
    </div>
  </div>

  <div class="card done" id="done" style="display:none">
    <div class="big" id="doneBig"></div>
    <div class="lead" id="doneMsg"></div>
  </div>

@endif
</div>

@unless ($닫힘)
<script>
/* ── 서명 캔버스 ───────────────────────────────────────────

   위임장 서명 화면과 같은 방식이다. 창이 바뀌어도 그린 것을 잃지 않는다 — 폰에서
   주소창이 접히기만 해도 resize 가 오고, 그때 다시 세우면 그림이 조용히 지워진다.
   서명한 사람은 단추가 다시 잠긴 것만 보고 까닭을 모른다. */
const cv = document.getElementById('sig');
let ctx = null, 그리는중 = false, 칠함 = false, 보내는중 = false;

function 캔버스세우기() {
  const r = cv.getBoundingClientRect();
  const d = window.devicePixelRatio || 1;
  cv.width  = Math.round(r.width  * d);
  cv.height = Math.round(r.height * d);
  ctx = cv.getContext('2d');
  ctx.scale(d, d);
  ctx.lineWidth = 2.2;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#111827';
}
캔버스세우기();

let 예약 = null;
window.addEventListener('resize', () => {
  clearTimeout(예약);
  예약 = setTimeout(() => {
    const 옛것 = 칠함 ? cv.toDataURL('image/png') : null;
    const 옛폭 = cv.getBoundingClientRect().width;
    캔버스세우기();
    if (!옛것) { 다시셈(); return; }
    const img = new Image();
    img.onload = () => {
      const 새폭 = cv.getBoundingClientRect().width;
      const 새높 = cv.getBoundingClientRect().height;
      const 배   = Math.min(새폭 / 옛폭, 1);
      ctx.drawImage(img, 0, 0, 새폭 * 배, 새높 * 배);
      다시셈();
    };
    img.src = 옛것;
  }, 150);
});

function 자리(e) {
  const r = cv.getBoundingClientRect();
  const p = e.touches ? e.touches[0] : e;
  return { x: p.clientX - r.left, y: p.clientY - r.top };
}
function 시작(e) { e.preventDefault(); 그리는중 = true; const p = 자리(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
function 이동(e) { if (!그리는중) return; e.preventDefault(); const p = 자리(e); ctx.lineTo(p.x, p.y); ctx.stroke(); 칠함 = true; 다시셈(); }
function 끝()   { 그리는중 = false; }

cv.addEventListener('mousedown', 시작);  cv.addEventListener('mousemove', 이동);
window.addEventListener('mouseup', 끝);
cv.addEventListener('touchstart', 시작, { passive:false });
cv.addEventListener('touchmove',  이동, { passive:false });
cv.addEventListener('touchend', 끝);

function 서명지우기() { ctx.clearRect(0, 0, cv.width, cv.height); 칠함 = false; 다시셈(); }

/* 무엇이 남았는지 늘 적어 둔다 — 단추가 잠겨 있으면 까닭을 몰라 멈춰 선다 */
function 다시셈() {
  document.getElementById('go').disabled = !칠함 || 보내는중;
  document.getElementById('why').textContent = 칠함 ? '' : '서명란에 서명해 주십시오.';
}
다시셈();

function 반려펴기() {
  const el = document.getElementById('rej');
  el.style.display = el.style.display === 'block' ? 'none' : 'block';
  if (el.style.display === 'block') document.getElementById('reason').focus();
}

async function 보내기(무엇) {
  if (보내는중) return;

  const 몸 = { action: 무엇 };

  if (무엇 === 'sign') {
    if (!칠함) { 다시셈(); return; }
    몸.signature = cv.toDataURL('image/png');
  } else {
    몸.reason = document.getElementById('reason').value.trim();
    if (!몸.reason) { document.getElementById('why').textContent = '반려 사유를 입력해 주십시오.'; return; }
    if (!confirm('반려하시겠습니까? 창고로 반송하여 재검수를 요청합니다.')) return;
  }

  보내는중 = true;
  다시셈();
  document.getElementById('why').textContent = '처리하고 있습니다 — 창을 닫지 마십시오.';

  try {
    const res = await fetch(@json(route('return-sign.submit', $r->final_sign_token)), {
      method : 'POST',
      headers: {
        'Content-Type' : 'application/json',
        'X-CSRF-TOKEN' : document.querySelector('meta[name=csrf-token]').content,
        'Accept'       : 'application/json',
      },
      body: JSON.stringify(몸),
    });
    const j = await res.json();

    if (!j.success) {
      보내는중 = false;
      다시셈();
      document.getElementById('why').textContent = j.message || '처리하지 못했습니다.';
      return;
    }

    /* 끝났으면 서명판을 걷는다. 남겨 두면 한 번 더 누른다 — 두 번째는 닫혀 있고,
       그 화면만 보면 서명이 안 들어간 줄 안다. */
    document.querySelectorAll('.card').forEach(el => { if (el.id !== 'done') el.style.display = 'none'; });
    document.getElementById('done').style.display = 'block';
    document.getElementById('doneBig').textContent =
      무엇 === 'reject' ? '반려했습니다' : (j.warn ? '서명은 접수되었습니다' : '승인이 끝났습니다');
    document.getElementById('doneMsg').textContent = j.message || '';
  } catch (e) {
    보내는중 = false;
    다시셈();
    document.getElementById('why').textContent = '통신이 끊겼습니다 — 잠시 뒤 다시 눌러 주십시오.';
  }
}
</script>
@endunless
</body>
</html>
