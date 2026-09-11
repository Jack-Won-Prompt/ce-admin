<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>요양비 청구 위임 동의</title>
<style>
  :root { --primary:#3C82C4; --primary-50:#EEF5FC; --border:#E3E7EC; --muted:#6B7280;
          --danger:#B54708; --danger-bg:#FEF3C7; }
  * { box-sizing:border-box; }
  body { margin:0; background:#F6F7F9; color:#1F2937; font-size:14px; line-height:1.55;
         font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',sans-serif; }
  .wrap { max-width:560px; margin:0 auto; padding:18px 14px 60px; }
  .card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:12px; }
  .brand { font-weight:800; letter-spacing:-.4px; color:var(--primary); }
  .lead  { color:var(--muted); font-size:13px; margin-top:6px; }
  .name-box { margin-top:12px; padding:11px 13px; background:var(--primary-50);
              border:1px solid #CFE1F4; border-radius:9px; }
  .name-box b { font-size:16px; }
  .warn { padding:11px 13px; background:var(--danger-bg); border:1px solid #F2C97D;
          border-radius:9px; color:var(--danger); font-weight:700; font-size:13px; }

  .doc-item { border:1px solid var(--border); border-radius:10px; margin-bottom:10px; overflow:hidden; }
  .doc-top  { display:flex; align-items:center; gap:8px; width:100%; padding:12px 13px;
              background:#fff; border:0; cursor:pointer; font-size:14px; text-align:left; }
  .doc-mark { color:var(--primary); font-weight:800; }
  .doc-name { flex:1; font-weight:700; }
  .doc-more { color:var(--muted); font-size:12px; flex-shrink:0; }
  .doc-note { padding:0 13px 10px 32px; color:var(--muted); font-size:12px; }
  .doc-body { display:none; padding:0 13px 14px; border-top:1px solid var(--border); }
  .doc-item.open .doc-body { display:block; }
  .doc-say  { margin-top:12px; padding:11px 13px; background:#FAFBFC;
              border:1px solid var(--border); border-radius:8px; font-size:13px; }
  .doc-text { margin-top:10px; padding:11px 13px; background:#FAFBFC; border:1px solid var(--border);
              border-radius:8px; font-size:12.5px; white-space:pre-wrap; color:#374151; }
  .must, .opt { display:inline-block; padding:1px 6px; border-radius:5px; font-size:11px; font-weight:700; }
  .must { background:#FEE4E2; color:#B42318; }
  .opt  { background:#EAECF0; color:#475467; }
  .pick { display:flex; gap:14px; margin-top:9px; }
  .pick label { display:flex; align-items:center; gap:5px; cursor:pointer; }

  .verify { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .ok-tag { color:var(--primary); font-weight:800; }

  canvas#sig { width:100%; height:180px; border:1px dashed #C7CDD4; border-radius:9px;
               background:#fff; touch-action:none; display:block; }
  .sig-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:7px; }

  .btn { height:44px; border-radius:10px; border:1px solid var(--border); background:#fff;
         font-size:15px; font-weight:700; cursor:pointer; }
  .btn-primary { background:var(--primary); border-color:var(--primary); color:#fff; }
  .btn:disabled { opacity:.45; cursor:not-allowed; }
  .row-btn { display:flex; gap:8px; margin-top:6px; }
  .row-btn .btn { flex:1; }

  .done { text-align:center; padding:40px 16px; }
  .done .big { font-size:20px; font-weight:800; margin-bottom:8px; }
</style>
</head>
<body>
<div class="wrap">

@if($닫힘)
  <div class="card done">
    <div class="big">열 수 없는 링크입니다</div>
    <div class="lead">{{ $닫힘 }}</div>
  </div>
@else

  <div class="card">
    <div class="brand">CE ADMIN</div>
    <div style="font-weight:800;font-size:17px;margin-top:4px;">요양비 청구 위임 동의</div>
    <div class="lead">
      아래 내용을 확인하신 뒤 전자서명을 해 주십시오.
      전자서명 하나가 아래 서류의 서명란에 함께 들어갑니다.
    </div>
    <div class="name-box">
      본인 이름 확인 · <b>{{ $sign->customer_name }}</b><br>
      <span style="font-size:12px;color:var(--muted);">위 이름이 본인과 다르면 동의하지 마십시오.</span>
    </div>
  </div>

  {{-- ── 본인확인 ────────────────────────────────────────── --}}
  @if($niceEnabled)
  <div class="card verify">
    <div>
      <div style="font-weight:700;">📱 휴대폰 본인확인</div>
      <div class="lead" id="verifyNote">
        {{ $verified ? '확인되었습니다.' : '서명 전에 본인확인을 해 주십시오.' }}
      </div>
    </div>
    <div>
      @if($verified)
        <span class="ok-tag" id="verifyOk">확인됨</span>
      @else
        <button type="button" class="btn" id="btnVerify" style="padding:0 14px;" onclick="본인확인()">본인확인</button>
      @endif
    </div>
  </div>
  @endif

  {{-- ── 서류 셋 ──────────────────────────────────────────
       주문 등록의 서명 화면과 같은 ［펼치기 ▼］ 방식이다. 그쪽은 위임장을
       PDF 로 그려 띄우는데 그 PDF 는 처방전의 병원ㆍ상병ㆍ처방일을 찍어 낸다 —
       여기에는 그 값이 없으므로 같은 글월을 화면 안에 편다. --}}
  <div class="card">

    {{-- ① 요양비 청구 위임 --}}
    <div class="doc-item" data-doc="delegation">
      <button type="button" class="doc-top" onclick="펼치기(this)">
        <span class="doc-mark">☑</span>
        <span class="doc-name"><span class="must">필수</span> 요양비 청구 위임</span>
        <span class="doc-more">펼치기 ▼</span>
      </button>
      <div class="doc-note">건강보험 급여비용을 회사가 대신 청구ㆍ수령하는 것에 동의합니다.</div>
      <div class="doc-body">
        <div class="doc-say">
          본인 <strong>{{ $sign->customer_name }}</strong>은(는) 건강보험 요양급여비용 청구와 관련하여
          콜로플라스트 코리아(주)가 건강보험공단에 제출하는 서류에 대한
          <strong>급여 위임청구 동의</strong>를 합니다.<br>
          위임 내용: 건강보험 급여 대상 보조기기의 급여비용 청구 및 수령에 관한 일체의 행위
        </div>
        <div class="pick">
          <label><input type="radio" name="agree_delegation" value="1" onchange="다시셈()"> 동의함</label>
          <label><input type="radio" name="agree_delegation" value="0" onchange="다시셈()"> 동의하지 않음</label>
        </div>
      </div>
    </div>

    {{-- ② 개인정보 수집·이용 동의 — 본문은 ConsentTerms 한 벌에서 온다 --}}
    @php
      $칸 = collect(\App\Support\ConsentTerms::카테터());
      $일반 = $칸->firstWhere('no', '1)');
      $마케 = $칸->firstWhere('no', '4)');
    @endphp
    <div class="doc-item" data-doc="privacy">
      <button type="button" class="doc-top" onclick="펼치기(this)">
        <span class="doc-mark">☑</span>
        <span class="doc-name"><span class="must">필수</span> 개인정보 수집·이용 동의</span>
        <span class="doc-more">펼치기 ▼</span>
      </button>
      <div class="doc-note">수집·이용 목적과 항목, 보유 기간을 확인하고 동의합니다.</div>
      <div class="doc-body">
        @foreach(($일반['blocks'] ?? []) as $ㅂ)
          @if(isset($ㅂ['text']))<div class="doc-text">{{ $ㅂ['text'] }}</div>@endif
        @endforeach
        <div class="pick">
          <label><input type="radio" name="agree_privacy" value="1" onchange="다시셈()"> 동의함</label>
          <label><input type="radio" name="agree_privacy" value="0" onchange="다시셈()"> 동의하지 않음</label>
        </div>
      </div>
    </div>

    {{-- ③ 마케팅 활용 동의 (선택) --}}
    <div class="doc-item" data-doc="marketing">
      <button type="button" class="doc-top" onclick="펼치기(this)">
        <span class="doc-mark">☑</span>
        <span class="doc-name"><span class="opt">선택</span> 마케팅 활용 동의</span>
        <span class="doc-more">펼치기 ▼</span>
      </button>
      <div class="doc-note">뉴스레터ㆍ제품 소개ㆍ재처방 안내를 받는 데 동의합니다. 동의하지 않아도 위임 청구에는 영향이 없습니다.</div>
      <div class="doc-body">
        @foreach(($마케['blocks'] ?? []) as $ㅂ)
          @if(isset($ㅂ['text']))<div class="doc-text">{{ $ㅂ['text'] }}</div>@endif
        @endforeach
        <div class="pick">
          <label><input type="radio" name="agree_marketing" value="1" onchange="다시셈()"> 동의함</label>
          <label><input type="radio" name="agree_marketing" value="0" onchange="다시셈()"> 동의하지 않음</label>
        </div>
      </div>
    </div>
  </div>

  {{-- ── 서명 ────────────────────────────────────────────── --}}
  <div class="card">
    <div class="sig-head">
      <span style="font-weight:700;">서명란 <span style="color:#ef4444;font-size:11px;">* 필수</span></span>
      <button type="button" class="btn" style="height:28px;padding:0 10px;font-size:12px;"
              onclick="서명지우기()">지우기</button>
    </div>
    <canvas id="sig"></canvas>

    <div class="row-btn" style="margin-top:14px;">
      <button type="button" class="btn" id="btnDecline" onclick="보내기('declined')">동의하지 않음</button>
      <button type="button" class="btn btn-primary" id="btnAgree" onclick="보내기('agreed')" disabled>동의</button>
    </div>
    <div class="lead" id="왜막힘" style="margin-top:8px;"></div>
  </div>

@endif
</div>

<script>
const TOKEN      = @json($sign->token);
const 보낼곳     = @json(route('delegation.submit', ['token' => $sign->token]));
const 인증시작   = @json(route('delegation.nice.start', ['token' => $sign->token]));
const NICE쓰나   = @json((bool) $niceEnabled);
const NICE강제   = @json((bool) $niceEnforce);
let   확인됨     = @json((bool) $verified);

function 펼치기(btn) {
  const it = btn.closest('.doc-item');
  const 열림 = it.classList.toggle('open');
  btn.querySelector('.doc-more').textContent = 열림 ? '접기 ▲' : '펼치기 ▼';
}

/* ── 서명 캔버스 ─────────────────────────────────────────── */
const cv = document.getElementById('sig');
let ctx = null, 그리는중 = false, 칠함 = false;

function 캔버스세우기() {
  if (!cv) return;
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
window.addEventListener('resize', () => { 캔버스세우기(); 칠함 = false; 다시셈(); });

function 자리(e) {
  const r = cv.getBoundingClientRect();
  const p = e.touches ? e.touches[0] : e;
  return { x: p.clientX - r.left, y: p.clientY - r.top };
}
function 시작(e) { e.preventDefault(); 그리는중 = true; const p = 자리(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
function 이동(e) { if (!그리는중) return; e.preventDefault(); const p = 자리(e); ctx.lineTo(p.x, p.y); ctx.stroke(); 칠함 = true; 다시셈(); }
function 끝(e)  { 그리는중 = false; }

if (cv) {
  cv.addEventListener('mousedown', 시작);   cv.addEventListener('mousemove', 이동);
  window.addEventListener('mouseup', 끝);
  cv.addEventListener('touchstart', 시작, { passive: false });
  cv.addEventListener('touchmove', 이동,  { passive: false });
  cv.addEventListener('touchend', 끝);
}

function 서명지우기() {
  if (!ctx) return;
  ctx.clearRect(0, 0, cv.width, cv.height);
  칠함 = false;
  다시셈();
}

/* ── 무엇이 남았는지 늘 적어 둔다 ─────────────────────────
   여태 동의 단추가 잠겨 있으면 까닭을 몰라 멈춰 섰다. */
function 고른값(이름) {
  const el = document.querySelector('input[name=' + 이름 + ']:checked');
  return el ? el.value : null;
}

function 다시셈() {
  const 남은것 = [];
  if (NICE쓰나 && NICE강제 && !확인됨) 남은것.push('휴대폰 본인확인');
  if (고른값('agree_delegation') !== '1') 남은것.push('요양비 청구 위임 동의');
  if (고른값('agree_privacy') === null)   남은것.push('개인정보 수집·이용 동의 고르기');
  if (고른값('agree_marketing') === null) 남은것.push('마케팅 활용 동의 고르기');
  if (!칠함) 남은것.push('서명');

  const btn = document.getElementById('btnAgree');
  if (btn) btn.disabled = 남은것.length > 0;
  const 말 = document.getElementById('왜막힘');
  if (말) 말.textContent = 남은것.length ? '남은 것 — ' + 남은것.join(' · ') : '';
}
다시셈();

/* ── 본인확인 ────────────────────────────────────────────── */
async function 본인확인() {
  const btn = document.getElementById('btnVerify');
  btn.disabled = true;
  btn.textContent = '여는 중...';

  try {
    const res = await fetch(인증시작, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
    });
    const out = await res.json();

    if (!out.success) { alert(out.message || '본인확인을 시작하지 못했습니다.'); btn.disabled = false; btn.textContent = '본인확인'; return; }

    if (out.simulated) { 인증됨(); return; }

    /* 표준창은 팝업으로 연다 — 돌아오면 스스로 닫으며 부모에게 알린다 */
    window.open(out.auth_url, 'nice_delegation', 'width=500,height=620,scrollbars=yes');
    btn.disabled = false;
    btn.textContent = '본인확인';
  } catch (e) {
    alert('본인확인을 시작하지 못했습니다 — ' + e.message);
    btn.disabled = false;
    btn.textContent = '본인확인';
  }
}

function 인증됨() {
  확인됨 = true;
  const btn = document.getElementById('btnVerify');
  if (btn) btn.outerHTML = '<span class="ok-tag">확인됨</span>';
  const note = document.getElementById('verifyNote');
  if (note) note.textContent = '확인되었습니다.';
  다시셈();
}

window.addEventListener('message', (e) => {
  if (e.data === 'nice-done') 인증됨();
});

/* ── 보내기 ──────────────────────────────────────────────── */
async function 보내기(짓) {
  const btn = document.getElementById(짓 === 'agreed' ? 'btnAgree' : 'btnDecline');

  if (짓 === 'declined' && !confirm('동의하지 않음으로 접수합니다. 계속할까요?')) return;

  btn.disabled = true;
  btn.textContent = '보내는 중...';

  const 몸 = { action: 짓 };
  if (짓 === 'agreed') {
    몸.agree_delegation = 고른값('agree_delegation') === '1';
    몸.agree_privacy    = 고른값('agree_privacy') === '1';
    몸.agree_marketing  = 고른값('agree_marketing') === '1';
    몸.signature        = cv.toDataURL('image/png');
  }

  try {
    const res = await fetch(보낼곳, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        'Accept': 'application/json',
      },
      body: JSON.stringify(몸),
    });
    const out = await res.json();

    if (!out.success) { alert(out.message || '보내지 못했습니다.'); btn.disabled = false; 다시셈(); return; }

    document.querySelector('.wrap').innerHTML =
      '<div class="card done"><div class="big">✅ 접수되었습니다</div>'
      + '<div class="lead">' + out.message + ' 이 창을 닫으셔도 됩니다.</div></div>';
  } catch (e) {
    alert('보내지 못했습니다 — ' + e.message);
    btn.disabled = false;
    다시셈();
  }
}
</script>
</body>
</html>
