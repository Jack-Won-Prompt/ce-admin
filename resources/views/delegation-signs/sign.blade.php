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

  /* 보호자 칸 — 주문 등록의 서명 화면과 같은 모양 (2026-09-15) */
  .g-field { margin-top:10px; }
  .g-field label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:4px; }
  .g-field input, .g-field select {
    width:100%; height:38px; padding:0 10px; font-size:13px;
    border:1px solid #C7CDD4; border-radius:8px; background:#fff; box-sizing:border-box;
  }
  .g-field input[readonly] { background:#F3F4F6; color:#6B7280; }
  .g-upload {
    display:flex; align-items:center; justify-content:center; min-height:96px; cursor:pointer;
    border:1px dashed #C7CDD4; border-radius:9px; background:#FAFBFC;
    font-size:13px; color:#6B7280; padding:10px;
  }
  canvas#gsig { width:100%; height:180px; border:1px dashed #C7CDD4; border-radius:9px;
                background:#fff; touch-action:none; }
  canvas#sig { width:100%; height:180px; border:1px dashed #C7CDD4; border-radius:9px;
               background:#fff; touch-action:none; display:block; }
  .sig-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:7px; }

  .btn { height:44px; border-radius:10px; border:1px solid var(--border); background:#fff;
         font-size:15px; font-weight:700; cursor:pointer; }
  .btn-primary { background:var(--primary); border-color:var(--primary); color:#fff; }
  .btn:disabled { opacity:.45; cursor:not-allowed; }
  /* 채우지 않은 칸 — 「동의」를 한 번 누른 뒤부터 짚는다 (2026-09-16 지시).
     처음부터 온통 붉으면 아직 적기도 전에 나무라는 꼴이 된다. */
  .빠짐 { outline:2px solid #ef4444 !important; outline-offset:2px;
          border-color:#ef4444 !important; border-radius:8px; }
  .빠짐-묶음 { outline:2px solid #ef4444 !important; outline-offset:4px; border-radius:8px; }
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
      {{-- 환자가 보는 자리다 — 사업부 표시 (E) 는 떼고 적는다 (2026-09-14 지시) --}}
      본인 이름 확인 · <b>{{ $sign->이름() }}</b><br>
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
          본인 <strong>{{ $sign->이름() }}</strong>은(는) 건강보험 요양급여비용 청구와 관련하여
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

  {{-- ── 보호자(법정대리인) — 미성년일 때만 (2026-09-15 지시) ──────────────

       만 19세 미만의 위임은 법정대리인이 한다. 주문 등록의 서명 링크는 진작 그렇게
       받고 있었는데(consent/sign) 이 화면에는 그 자리가 없어 **본인 서명란 하나**만
       서 있었다 — 미성년에게 그렇게 받은 서명은 위임장으로 쓸 수 없다.

       성년ㆍ미성년은 주민등록번호로 가른다. 명단에 주민등록번호가 없으면 세우지
       않는다 — 「모른다」를 「미성년」으로 보면 성인에게 보호자를 요구하게 된다. --}}
  @if($sign->미성년인가())
  <div class="card" id="guardianCard">
    <div class="sig-head" style="display:block;">
      <span style="font-weight:700;">보호자(법정대리인) 확인
        <span style="color:#ef4444;font-size:11px;">* 필수</span></span>
      <div class="lead" style="margin-top:4px;">
        위임인이 만 {{ (int) config('delegation.minor_age', 19) }}세 미만인 경우
        법정대리인(보호자)의 확인이 필요합니다.
      </div>
    </div>

    <div class="g-field">
      <label>위임인 성명</label>
      <input type="text" value="{{ $sign->이름() }}" readonly>
    </div>
    <div class="g-field">
      <label>위임인 생년월일</label>
      <input type="text" value="{{ $sign->birth_date?->format('Y-m-d') }}" readonly>
    </div>
    <div class="g-field">
      <label>가입자ㆍ피부양자와의 관계 <span style="color:#ef4444;">*</span></label>
      <select id="gRelation" onchange="다시셈()">
        <option value="">선택</option>
        @foreach(config('delegation.guardian_relations', ['부','모','조부','조모','법정대리인']) as $r)
          <option value="{{ $r }}" @selected($sign->guardian_relation === $r)>{{ $r }}</option>
        @endforeach
      </select>
    </div>
    <div class="g-field">
      <label>법정대리인 또는 가족 성명 <span style="color:#ef4444;">*</span></label>
      {{-- 명단에 적어 둔 것이 있으면 미리 채운다 — 담당자가 통화로 받아 적어 둔 값이다 --}}
      <input type="text" id="gName" maxlength="50" placeholder="법정대리인 또는 가족 성명"
             value="{{ $sign->guardian_name }}" oninput="다시셈()">
    </div>
    <div class="g-field">
      <label>보호자 전화번호</label>
      <input type="text" id="gPhone" maxlength="20" placeholder="010-XXXX-XXXX"
             value="{{ $sign->guardian_phone }}">
    </div>
    <div class="g-field">
      <label>법정대리인 또는 가족 생년월일 <span style="color:#ef4444;">*</span></label>
      <input type="text" id="gBirth" maxlength="10" placeholder="YYYY-MM-DD" inputmode="numeric"
             value="{{ $sign->guardian_birth_date?->format('Y-m-d') }}" oninput="생년꼴(this)">
    </div>

    <div class="sig-head" style="margin-top:14px;">
      <span style="font-weight:700;">보호자 서명
        <span style="color:#ef4444;font-size:11px;">* 필수</span></span>
      <button type="button" class="btn" style="height:28px;padding:0 10px;font-size:12px;"
              onclick="보호자서명지우기()">지우기</button>
    </div>
    <div class="lead" style="margin-bottom:6px;">
      위임인이 미성년자인 경우, 본 전자서명은 위임인과 법정대리인 각각의 서명란에
      동일하게 적용됩니다.
    </div>
    <canvas id="gsig"></canvas>

    <div class="sig-head" style="margin-top:14px;display:block;">
      <span style="font-weight:700;">법정대리인 또는 가족 신분증
        <span style="color:#ef4444;font-size:11px;">* 필수</span></span>
      <div class="lead" style="margin-top:4px;">
        법정대리인 또는 가족의 신분증(주민등록증 또는 운전면허증) 사진을 업로드해 주십시오.
        생년월일 확인이 가능한 신분증만 제출 가능합니다.<br>(JPG, PNG, HEIC 형식, 최대 10MB)
      </div>
    </div>
    <label class="g-upload" id="gIdDrop">
      <input type="file" id="gIdFile" accept="image/jpeg,image/png,image/heic,image/heif"
             capture="environment" style="display:none;" onchange="신분증고름(this)">
      <span id="gIdEmpty">신분증 사진 올리기</span>
      <img id="gIdPreview" style="display:none;max-width:100%;max-height:220px;border-radius:8px;" alt="">
    </label>
    <div id="gIdName" class="lead" style="display:none;margin-top:6px;text-align:center;"></div>
  </div>
  @endif

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
      <button type="button" class="btn btn-primary" id="btnAgree" onclick="보내기('agreed')">동의</button>
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

/* 크기가 바뀌어도 그린 것을 잃지 않는다.

   휴대폰을 돌리거나 주소창이 접히기만 해도 resize 가 온다. 그때 캔버스를 다시
   세우면 그림이 지워지는데, 화면은 아무 말도 하지 않아 서명한 사람은 지워진 줄
   모른 채 ［동의］가 다시 잠긴 것만 본다. 그려 둔 것을 옮겨 담는다. */
let 다시세우기예약 = null;
window.addEventListener('resize', () => {
  clearTimeout(다시세우기예약);
  다시세우기예약 = setTimeout(() => {
    const 옛것 = 칠함 ? cv.toDataURL('image/png') : null;
    const 옛폭 = cv.getBoundingClientRect().width;

    캔버스세우기();

    if (!옛것) { 다시셈(); return; }

    const img = new Image();
    img.onload = () => {
      /* 가로가 달라졌으면 비율을 지켜 줄여 그린다 — 늘려 그리면 서명이 뭉개진다 */
      const 새폭 = cv.getBoundingClientRect().width;
      const 새높 = cv.getBoundingClientRect().height;
      const 배 = Math.min(새폭 / 옛폭, 1);
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

/* ── 보호자(법정대리인) — 미성년일 때만 (2026-09-15) ───────────────────

   주문 등록의 서명 화면과 같은 방식이다. 서명판만 하나 더 서고, 막는 잣대에
   보호자 칸이 더해진다. */
const 미성년 = @json((bool) $sign->미성년인가());
let gcv = null, gctx = null, g칠함 = false, 신분증 = null;

function 생년꼴(칸) {
  const d = 칸.value.replace(/\D/g, '').slice(0, 8);
  칸.value = d.length > 6 ? `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6)}`
           : d.length > 4 ? `${d.slice(0,4)}-${d.slice(4)}` : d;
  다시셈();
}

function 생년바른가() {
  const v = document.getElementById('gBirth')?.value ?? '';
  if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
  const d = new Date(v);
  return !isNaN(d) && d < new Date();
}

function 보호자서명지우기() {
  if (!gctx) return;
  gctx.clearRect(0, 0, gcv.width, gcv.height);
  g칠함 = false;
  다시셈();
}

function 신분증고름(칸) {
  const f = 칸.files?.[0];
  if (!f) return;

  if (f.size > 10 * 1024 * 1024) {
    alert('파일이 너무 큽니다. 10MB 이하로 올려 주십시오.');
    칸.value = ''; return;
  }

  const r = new FileReader();
  r.onload = () => {
    신분증 = r.result;
    const im = document.getElementById('gIdPreview');
    im.src = 신분증; im.style.display = '';
    document.getElementById('gIdEmpty').style.display = 'none';
    const 이름 = document.getElementById('gIdName');
    이름.textContent = f.name; 이름.style.display = '';
    다시셈();
  };
  r.onerror = () => alert('이미지를 읽지 못했습니다. 다른 파일로 시도해 주십시오.');
  r.readAsDataURL(f);
}

if (미성년) {
  gcv  = document.getElementById('gsig');
  gctx = gcv.getContext('2d');

  const 맞춤 = () => {
    const r = gcv.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    gcv.width = r.width * dpr; gcv.height = 180 * dpr;
    gctx.scale(dpr, dpr);
    gctx.lineWidth = 2.2; gctx.lineCap = 'round'; gctx.strokeStyle = '#111827';
  };
  맞춤();
  window.addEventListener('resize', () => { 맞춤(); g칠함 = false; 다시셈(); });

  let 그리는중 = false;
  const 자리 = (e) => {
    const r = gcv.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    return [t.clientX - r.left, t.clientY - r.top];
  };
  const 시작 = (e) => { e.preventDefault(); 그리는중 = true; const [x,y]=자리(e); gctx.beginPath(); gctx.moveTo(x,y); };
  const 이동 = (e) => { if(!그리는중) return; e.preventDefault(); const [x,y]=자리(e); gctx.lineTo(x,y); gctx.stroke(); g칠함 = true; };
  const 멈춤 = () => { if(!그리는중) return; 그리는중 = false; 다시셈(); };

  ['mousedown','touchstart'].forEach(n => gcv.addEventListener(n, 시작, {passive:false}));
  ['mousemove','touchmove'].forEach(n => gcv.addEventListener(n, 이동, {passive:false}));
  ['mouseup','mouseleave','touchend','touchcancel'].forEach(n => gcv.addEventListener(n, 멈춤));
}

/* 아직 채우지 않은 것을 모은다 — 칸까지 함께 들고 있는다.

   여태 이름만 모아 단추 아래 작은 글씨로 적고 단추를 잠갔다. 그런데 보호자 칸은
   화면 한참 위에 있어, 휴대전화로 열면 그 글씨가 보이지도 않고 단추는 눌리지도
   않는다 — 무엇을 빠뜨렸는지 알 길이 없었다 (2026-09-16 지시).

   이제 단추는 늘 눌린다. 누르면 빠뜨린 것을 창으로 알리고 그 칸으로 데려간다. */
function 남은것모으기() {
  const 남 = [];
  const 담다 = (말, 칸) => 남.push({ 말, 칸 });
  const 칸 = (id) => document.getElementById(id);

  if (NICE쓰나 && NICE강제 && !확인됨) {
    담다(미성년 ? '법정대리인(보호자) 휴대폰 본인확인' : '휴대폰 본인확인', 칸('btnVerify'));
  }
  if (고른값('agree_delegation') !== '1') {
    담다('요양비 청구 위임 동의', document.querySelector('input[name=agree_delegation]'));
  }
  if (고른값('agree_privacy') === null) {
    담다('개인정보 수집·이용 동의 선택', document.querySelector('input[name=agree_privacy]'));
  }
  if (고른값('agree_marketing') === null) {
    담다('마케팅 활용 동의 선택', document.querySelector('input[name=agree_marketing]'));
  }
  if (미성년) {
    if (!(칸('gRelation')?.value))              담다('가입자ㆍ피부양자와의 관계', 칸('gRelation'));
    if (!(칸('gName')?.value ?? '').trim())     담다('법정대리인 또는 가족 성명', 칸('gName'));
    if (!생년바른가())                          담다('법정대리인 또는 가족 생년월일', 칸('gBirth'));
    if (!g칠함)                                 담다('보호자 서명', 칸('gsig'));
  }
  if (!칠함) 담다('본인 서명', 칸('sig'));

  return 남;
}

/* 한 번 눌러 본 뒤부터 붉게 짚는다 */
let 짚을까 = false;

function 짚기(남은것) {
  document.querySelectorAll('.빠짐, .빠짐-묶음')
          .forEach(el => el.classList.remove('빠짐', '빠짐-묶음'));
  if (! 짚을까) return;

  남은것.forEach(n => {
    const el = n.칸;
    if (! el) return;
    /* 라디오 한 알에 테두리를 두르면 점 하나만 붉어져 눈에 안 띈다 — 묶음을 두른다 */
    const 묶음 = el.type === 'radio' ? el.closest('.card, .g-field, div') : null;
    if (묶음) { 묶음.classList.add('빠짐-묶음'); }
    else      { el.classList.add('빠짐'); }
  });
}

function 다시셈() {
  const 남은것 = 남은것모으기();
  const 말 = document.getElementById('왜막힘');
  if (말) 말.textContent = 남은것.length ? '미입력 항목 — ' + 남은것.map(n => n.말).join(' · ') : '';
  짚기(남은것);        // 채우는 대로 붉은 테두리가 하나씩 걷힌다
}

/* 빠뜨린 것을 창으로 알리고, 확인을 누르면 첫 칸으로 데려가 눈에 띄게 한다 */
function 빠진것알림(남은것) {
  const 덮개 = document.createElement('div');
  덮개.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;'
                     + 'display:flex;align-items:center;justify-content:center;padding:20px;';
  덮개.innerHTML =
      '<div style="background:#fff;border-radius:14px;max-width:360px;width:100%;'
    + 'box-shadow:0 12px 40px rgba(0,0,0,.25);overflow:hidden;">'
    + '<div style="padding:16px 18px 10px;font-size:16px;font-weight:700;color:#111;">'
    + '입력하지 않은 항목이 있습니다</div>'
    + '<div style="padding:0 18px 4px;font-size:13px;color:#555;line-height:1.7;">'
    + '다음 항목을 입력하셔야 동의를 제출할 수 있습니다.</div>'
    + '<ul style="margin:10px 18px 4px;padding-left:18px;font-size:14px;color:#111;line-height:1.9;">'
    + 남은것.map(n => '<li>' + n.말 + '</li>').join('')
    + '</ul>'
    + '<div style="padding:12px 18px 16px;text-align:right;">'
    + '<button type="button" id="빠진것확인" style="height:38px;padding:0 18px;border:0;border-radius:9px;'
    + 'background:#28798B;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">확인</button>'
    + '</div></div>';

  const 닫기 = () => {
    덮개.remove();
    const 첫칸 = 남은것[0]?.칸;
    if (! 첫칸) return;

    /* 붉은 테두리는 채울 때까지 남는다(짚기). 여기서는 데려가고 초점만 준다. */
    첫칸.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (typeof 첫칸.focus === 'function' && 첫칸.tagName !== 'CANVAS') {
      setTimeout(() => 첫칸.focus({ preventScroll: true }), 400);
    }
  };

  덮개.addEventListener('click', ev => { if (ev.target === 덮개) 닫기(); });
  document.body.appendChild(덮개);
  덮개.querySelector('#빠진것확인').addEventListener('click', 닫기);
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

  /* 동의로 보낼 때만 본다 — 「동의하지 않음」은 채울 것이 없다 */
  if (짓 === 'agreed') {
    const 남은것 = 남은것모으기();
    if (남은것.length) {
      짚을까 = true;          // 이제부터 채우지 않은 칸을 붉게 짚는다
      짚기(남은것);
      빠진것알림(남은것);
      return;
    }
  }

  if (짓 === 'declined' && !confirm('동의하지 않음으로 접수합니다. 계속할까요?')) return;

  btn.disabled = true;
  btn.textContent = '보내는 중...';

  const 몸 = { action: 짓 };
  if (짓 === 'agreed') {
    몸.agree_delegation = 고른값('agree_delegation') === '1';
    몸.agree_privacy    = 고른값('agree_privacy') === '1';
    몸.agree_marketing  = 고른값('agree_marketing') === '1';
    몸.signature        = cv.toDataURL('image/png');

    if (미성년) {
      몸.guardian_name       = (document.getElementById('gName').value ?? '').trim();
      몸.guardian_relation   = document.getElementById('gRelation').value;
      몸.guardian_birth_date = document.getElementById('gBirth').value;
      몸.guardian_phone      = (document.getElementById('gPhone').value ?? '').trim();
      몸.guardian_signature  = gcv.toDataURL('image/png');
      몸.guardian_id         = 신분증;
    }
  }

  /* 신분증이 없어도 서명까지는 받는다 (주문 등록 쪽과 같다).
     그 자리에 신분증이 없거나 사진이 흐려 못 올리는 사람이 있는데, 통째로 막으면
     받아 둘 수 있었던 서명마저 못 받는다. 없이 누르면 한 번 묻는다. */
  if (짓 === 'agreed' && 미성년 && !신분증) {
    if (!confirm('신분증은 필수 입니다. 그래도 저장하시겠습니까?\n담당자가 다시 연락을 드릴수 있습니다.')) {
      btn.disabled = false; btn.textContent = '동의'; 다시셈(); return;
    }
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

    if (!out.success) { alert(out.message || '보내지 못했습니다.'); btn.disabled = false; btn.textContent = '동의'; 다시셈(); return; }

    document.querySelector('.wrap').innerHTML =
      '<div class="card done"><div class="big">✅ 접수되었습니다</div>'
      + '<div class="lead">' + out.message + ' 이 창을 닫으셔도 됩니다.</div></div>';
  } catch (e) {
    alert('보내지 못했습니다 — ' + e.message);
    btn.disabled = false;
    btn.textContent = 짓 === 'agreed' ? '동의' : '동의하지 않음';
    다시셈();
  }
}
</script>
</body>
</html>
