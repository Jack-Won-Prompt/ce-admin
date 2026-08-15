{{-- 좌우 프레임 — 왼쪽은 우리 서식, 오른쪽은 공단 사이트 --}}
{{--
  공단 사이트는 프레임을 막지 않아 이렇게 나란히 놓을 수 있다. 다만 다른 출처라 우리가 그 안에
  값을 넣어 줄 수는 없다 — 복사는 왼쪽에서, 붙여넣기는 사람이 오른쪽에 한다.

  로그인 세션 쿠키에 SameSite 가 없어 브라우저가 Lax 로 다루면 프레임 안에서는 쿠키가 실리지
  않는다. 그러면 로그인이 풀리거나 아예 안 된다. 그때를 대비해 「공단을 새 창으로」를 늘 옆에
  둔다 — 담당자가 한 번 눌러 창 방식으로 넘어가면 일이 멈추지 않는다.
--}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>요양비청구등록 (좌우) — {{ $order->patient?->name ?? $order->order_number }}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  html, body { height:100%; overflow:hidden; }
  body { font-family:'Malgun Gothic','맑은 고딕',sans-serif; font-size:12px; display:flex; flex-direction:column; }

  .bar { display:flex; align-items:center; gap:8px; background:#1f3d45; color:#fff; padding:6px 10px; flex-shrink:0; }
  .bar b { font-size:12px; }
  .bar .grow { flex:1; }
  .bar .warn { color:#ffd9a0; font-size:11px; }
  .bbtn { border:1px solid rgba(255,255,255,.45); background:transparent; color:#fff;
          border-radius:5px; padding:4px 10px; font-size:11px; font-weight:700; cursor:pointer; }
  .bbtn:hover { background:rgba(255,255,255,.15); }

  .panes { flex:1; display:flex; min-height:0; }
  .pane { display:flex; flex-direction:column; min-width:0; }
  .pane-hd { background:#eef1f4; border-bottom:1px solid #b9c0c8; padding:3px 8px;
             font-size:11px; font-weight:700; color:#333; display:flex; align-items:center; gap:6px; flex-shrink:0; }
  .pane-hd .grow { flex:1; }
  .pane-hd a { color:#28798B; font-size:11px; }
  iframe { flex:1; width:100%; border:0; }

  /* 가운데를 끌어 좌우 비율을 바꾼다. 서식 폭과 공단 화면 폭이 건마다 달라 고정하면 불편하다. */
  .split { width:6px; background:#c8ced4; cursor:col-resize; flex-shrink:0; }
  .split:hover { background:#28798B; }
  .dragging iframe { pointer-events:none; }
</style>
</head>
<body>

<div class="bar">
  <b>CE Admin · 공단 요양비 청구</b>
  <span style="font-size:11px;opacity:.85">{{ $order->order_number }}</span>
  <span class="warn">왼쪽 칸을 눌러 복사 → 오른쪽 같은 자리에 붙여넣기</span>
  <div class="grow"></div>
  <button class="bbtn" onclick="reloadPortal()">공단 새로고침</button>
  <button class="bbtn" onclick="portalToWindow()">공단을 새 창으로</button>
  <button class="bbtn" onclick="location.href=@js($soloUrl)">프레임 해제</button>
</div>

<div class="panes" id="panes">
  <div class="pane" id="left" style="width:52%">
    <div class="pane-hd">
      <span>우리 청구 원본 — 2221 서식과 같은 구조</span>
      <div class="grow"></div>
      <a href="{{ $soloUrl }}" target="_blank" rel="noopener">따로 열기</a>
    </div>
    <iframe src="{{ $soloUrl }}" id="mine"></iframe>
  </div>

  <div class="split" id="split"></div>

  <div class="pane" id="right" style="flex:1">
    <div class="pane-hd">
      <span>공단 요양기관정보마당</span>
      <div class="grow"></div>
      <span style="font-weight:400;color:#777">로그인이 풀리면 「공단을 새 창으로」를 누르십시오</span>
    </div>
    <iframe src="{{ $portalUrl }}" id="portal"></iframe>
  </div>
</div>

<script>
const PORTAL = @js($portalUrl);

function reloadPortal() {
  // 다른 출처라 location 을 읽을 수는 없어도 src 를 다시 넣는 것은 된다
  document.getElementById('portal').src = PORTAL;
}

/* 프레임 안에서 로그인이 안 될 때의 퇴로. 공단만 창으로 빼고 우리 서식은 이 창에 남긴다. */
function portalToWindow() {
  const w = screen.availWidth, h = screen.availHeight, half = Math.floor(w / 2);
  const win = window.open(PORTAL, 'nhis_portal', `width=${half},height=${h},left=${half},top=0,scrollbars=yes,resizable=yes`);
  if (!win) { alert('팝업이 막혔습니다 — 이 사이트의 팝업을 허용해 주십시오.'); return; }
  document.getElementById('right').style.display = 'none';
  document.getElementById('split').style.display = 'none';
  document.getElementById('left').style.width = '100%';
  try { window.moveTo(0, 0); window.resizeTo(half, h); } catch (_) {}
  win.focus();
}

// 좌우 비율 — 한 번 맞춰 놓으면 다음에도 그대로여야 한다
const KEY = 'nhis-split-ratio';
const left = document.getElementById('left');
const saved = parseFloat(sessionStorage.getItem(KEY));
if (saved > 15 && saved < 85) { left.style.width = saved + '%'; }

let dragging = false;
document.getElementById('split').addEventListener('mousedown', () => {
  dragging = true;
  document.body.classList.add('dragging');
});
document.addEventListener('mousemove', (e) => {
  if (!dragging) return;
  const pct = Math.min(85, Math.max(15, e.clientX / document.getElementById('panes').clientWidth * 100));
  left.style.width = pct + '%';
});
document.addEventListener('mouseup', () => {
  if (!dragging) return;
  dragging = false;
  document.body.classList.remove('dragging');
  sessionStorage.setItem(KEY, parseFloat(left.style.width));
});
</script>
</body>
</html>
