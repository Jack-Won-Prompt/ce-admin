<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>건강보험 급여 위임동의</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
      background: #f0f4ff;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px 16px 40px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(27,102,245,.10);
      width: 100%;
      max-width: 480px;
      overflow: hidden;
    }
    .card-header {
      background: linear-gradient(135deg, #1B66F5, #1250C4);
      color: #fff;
      padding: 24px 20px 20px;
      text-align: center;
    }
    .card-header .logo {
      font-size: 12px;
      font-weight: 600;
      opacity: .75;
      letter-spacing: .5px;
      margin-bottom: 8px;
    }
    .card-header h1 {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: -.3px;
    }
    .card-header p {
      font-size: 13px;
      opacity: .85;
      margin-top: 6px;
      line-height: 1.5;
    }
    .card-body { padding: 20px; }

    /* 타이머 */
    .timer-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff8e1;
      border: 1px solid #ffe082;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 18px;
      font-size: 13px;
      font-weight: 600;
      color: #e65100;
    }
    .timer-bar .timer-icon { font-size: 16px; }
    .timer-bar #countdown { font-family: monospace; font-size: 15px; }

    /* 환자 확인 */
    .patient-box {
      background: #f4f8ff;
      border: 1.5px solid #c7dcff;
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 18px;
    }
    .patient-box .label {
      font-size: 11px;
      font-weight: 600;
      color: #6b7280;
      margin-bottom: 4px;
    }
    .patient-box .name {
      font-size: 22px;
      font-weight: 800;
      color: #1B66F5;
      letter-spacing: -.3px;
    }
    .patient-box .sub {
      font-size: 12px;
      color: #6b7280;
      margin-top: 4px;
    }

    /* 동의 내용 */
    .consent-text {
      font-size: 13px;
      color: #374151;
      line-height: 1.7;
      background: #fafafa;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 14px;
      margin-bottom: 18px;
    }
    .consent-text strong { color: #111827; }

    /* 서명란 */
    .sig-section {}
    .sig-label {
      font-size: 12px;
      font-weight: 700;
      color: #374151;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .sig-clear {
      font-size: 11px;
      color: #6b7280;
      background: none;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 2px 10px;
      cursor: pointer;
    }
    .sig-wrap {
      position: relative;
      border: 2px dashed #d1d5db;
      border-radius: 10px;
      overflow: hidden;
      touch-action: none;
      background: #fff;
      transition: border-color .2s;
    }
    .sig-wrap.active { border-color: #1B66F5; border-style: solid; }
    .sig-wrap canvas {
      display: block;
      width: 100%;
      cursor: crosshair;
    }
    .sig-placeholder {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      color: #d1d5db;
      font-size: 13px;
      gap: 6px;
      transition: opacity .2s;
    }
    .sig-placeholder svg { width: 32px; height: 32px; opacity: .5; }

    /* 버튼 */
    .btn-row {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 10px;
      margin-top: 22px;
    }
    .btn {
      padding: 14px 12px;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn:active { transform: scale(.97); }
    .btn-cancel { background: #f3f4f6; color: #4b5563; }
    .btn-agree  { background: #1B66F5; color: #fff; box-shadow: 0 4px 12px rgba(27,102,245,.3); }
    .btn-agree:disabled { background: #93c5fd; box-shadow: none; cursor: not-allowed; }

    /* 결과 화면 */
    .result-screen {
      display: none;
      text-align: center;
      padding: 40px 20px;
    }
    .result-screen .icon {
      font-size: 64px;
      margin-bottom: 16px;
    }
    .result-screen h2 { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
    .result-screen p  { font-size: 14px; color: #6b7280; line-height: 1.6; }

    /* 로딩 */
    .spinner {
      display: inline-block;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<div class="card" id="mainCard">
  <div class="card-header">
    <div class="logo">CE ADMIN</div>
    <h1>건강보험 급여 위임동의</h1>
    <p>아래 내용을 확인하신 후 서명해주세요.</p>
  </div>
  <div class="card-body">

    {{-- 타이머 --}}
    <div class="timer-bar">
      <span class="timer-icon">⏱</span>
      <span>링크 유효 시간</span>
      <span id="countdown" style="margin-left:auto;">--:--</span>
    </div>

    {{-- 환자 확인 --}}
    <div class="patient-box">
      <div class="label">본인 이름 확인</div>
      <div class="name">{{ $consent->patient_name }}</div>
      <div class="sub">위 이름이 본인과 다를 경우 동의하지 마세요.</div>
    </div>

    {{-- 동의 내용 --}}
    <div class="consent-text">
      본인 <strong>{{ $consent->patient_name }}</strong>은(는) 건강보험 요양급여비용 청구와 관련하여
      콜로플라스트 코리아(주)가 건강보험공단에 제출하는 서류에 대한
      <strong>급여 위임청구 동의</strong>를 합니다.<br><br>
      위임 내용: 건강보험 급여 대상 보조기기의 급여비용 청구 및 수령에 관한 일체의 행위
    </div>

    {{-- 서명란 --}}
    <div class="sig-section">
      <div class="sig-label">
        서명란 <span style="color:#ef4444;font-size:11px;">* 필수</span>
        <button class="sig-clear" type="button" onclick="clearSignature()">지우기</button>
      </div>
      <div class="sig-wrap" id="sigWrap">
        <canvas id="sigCanvas" height="180"></canvas>
        <div class="sig-placeholder" id="sigPlaceholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
          </svg>
          <span>이 곳에 서명하세요</span>
        </div>
      </div>
    </div>

    {{-- 버튼 --}}
    <div class="btn-row">
      <button class="btn btn-cancel" type="button" id="btnDecline" onclick="submitConsent('declined')">거절</button>
      <button class="btn btn-agree"  type="button" id="btnAgree"   onclick="submitConsent('agreed')" disabled>동의 서명</button>
    </div>

  </div>
</div>

{{-- 결과 화면 (동적 교체) --}}
<div class="card" id="resultCard" style="display:none;max-width:480px;">
  <div class="result-screen" id="resultScreen">
    <div class="icon" id="resultIcon"></div>
    <h2 id="resultTitle"></h2>
    <p id="resultMsg"></p>
  </div>
</div>

<script>
const TOKEN       = '{{ $consent->token }}';
const EXPIRES_AT  = new Date('{{ $consent->expires_at->toIso8601String() }}');
const SUBMIT_URL  = '{{ route('consent.submit', $consent->token) }}';

/* ── 카운트다운 ───────────────────────────────────────── */
function tick() {
  const diff = Math.max(0, Math.floor((EXPIRES_AT - Date.now()) / 1000));
  const m = String(Math.floor(diff / 60)).padStart(2, '0');
  const s = String(diff % 60).padStart(2, '0');
  const el = document.getElementById('countdown');
  el.textContent = m + ':' + s;
  el.style.color = diff <= 120 ? '#ef4444' : '#e65100';
  if (diff === 0) {
    showExpired();
  }
}
tick();
const timer = setInterval(tick, 1000);

function showExpired() {
  clearInterval(timer);
  showResult('⏰', '링크 만료', '서명 링크의 유효 시간(30분)이 지났습니다.\n담당자에게 재발송을 요청해주세요.', '#f59e0b');
}

/* ── 서명 패드 ────────────────────────────────────────── */
const canvas      = document.getElementById('sigCanvas');
const ctx         = canvas.getContext('2d');
const sigWrap     = document.getElementById('sigWrap');
const placeholder = document.getElementById('sigPlaceholder');
let drawing = false;
let hasSig  = false;

function resizeCanvas() {
  const w = sigWrap.clientWidth;
  canvas.width  = w * devicePixelRatio;
  canvas.height = 180 * devicePixelRatio;
  canvas.style.height = '180px';
  ctx.scale(devicePixelRatio, devicePixelRatio);
  ctx.strokeStyle = '#1B66F5';
  ctx.lineWidth   = 2.5;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

function getPos(e) {
  const rect = canvas.getBoundingClientRect();
  const src  = e.touches ? e.touches[0] : e;
  return { x: src.clientX - rect.left, y: src.clientY - rect.top };
}

function onStart(e) {
  e.preventDefault();
  drawing = true;
  const p = getPos(e);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
  sigWrap.classList.add('active');
  placeholder.style.opacity = '0';
}
function onMove(e) {
  if (!drawing) return;
  e.preventDefault();
  const p = getPos(e);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
  hasSig = true;
  document.getElementById('btnAgree').disabled = false;
}
function onEnd() { drawing = false; }

canvas.addEventListener('mousedown',  onStart);
canvas.addEventListener('mousemove',  onMove);
canvas.addEventListener('mouseup',    onEnd);
canvas.addEventListener('mouseleave', onEnd);
canvas.addEventListener('touchstart', onStart, { passive: false });
canvas.addEventListener('touchmove',  onMove,  { passive: false });
canvas.addEventListener('touchend',   onEnd);

function clearSignature() {
  ctx.clearRect(0, 0, canvas.width / devicePixelRatio, canvas.height / devicePixelRatio);
  hasSig = false;
  placeholder.style.opacity = '1';
  sigWrap.classList.remove('active');
  document.getElementById('btnAgree').disabled = true;
}

/* ── 제출 ─────────────────────────────────────────────── */
async function submitConsent(action) {
  if (action === 'agreed' && !hasSig) {
    alert('서명을 먼저 해주세요.');
    return;
  }

  const btnAgree   = document.getElementById('btnAgree');
  const btnDecline = document.getElementById('btnDecline');
  btnAgree.disabled = btnDecline.disabled = true;
  btnAgree.innerHTML = '<span class="spinner"></span> 처리 중...';

  const body = { action };
  if (action === 'agreed') {
    body.signature = canvas.toDataURL('image/png');
  }

  try {
    const res = await fetch(SUBMIT_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept': 'application/json',
      },
      body: JSON.stringify(body),
    });
    const data = await res.json();

    if (data.success) {
      clearInterval(timer);
      if (action === 'agreed') {
        showResult('✅', '동의가 완료되었습니다', '건강보험 급여 위임동의가 정상적으로 접수되었습니다.\n이 창을 닫으셔도 됩니다.', '#12B76A');
      } else {
        showResult('❌', '거절 처리되었습니다', '위임동의를 거절하셨습니다.\n문의 사항은 담당자에게 연락주세요.', '#6b7280');
      }
    } else {
      alert(data.message ?? '오류가 발생했습니다.');
      btnAgree.disabled = false;
      btnDecline.disabled = false;
      btnAgree.innerHTML = '동의 서명';
    }
  } catch (e) {
    alert('네트워크 오류가 발생했습니다. 다시 시도해주세요.');
    btnAgree.disabled = false;
    btnDecline.disabled = false;
    btnAgree.innerHTML = '동의 서명';
  }
}

function showResult(icon, title, msg, color) {
  document.getElementById('mainCard').style.display = 'none';
  document.getElementById('resultCard').style.display = 'block';
  document.getElementById('resultIcon').textContent  = icon;
  document.getElementById('resultTitle').textContent = title;
  document.getElementById('resultTitle').style.color = color;
  document.getElementById('resultMsg').textContent   = msg;
  document.getElementById('resultScreen').style.display = 'block';
}
</script>
{{-- CSRF hidden for fetch --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
</body>
</html>
