{{-- 공단 입력 지원 창의 공통 껍데기 --}}
{{-- 공단 사이트와 나란히 놓고 보는 창이라 사이드바 없이 혼자 선다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('windowTitle')</title>
<style>
  :root {
    --pri:#28798B; --pri-50:#eef6f8; --pri-200:#b9d8de;
    --ink:#111827; --sub:#6b7280; --line:#e5e7eb; --bg:#f7f8fa;
    --warn:#b45309; --warn-bg:#fffbeb; --danger:#b91c1c; --danger-bg:#fef2f2;
    --ok:#166534; --ok-bg:#f0fdf4;
  }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Malgun Gothic','맑은 고딕',sans-serif; background:var(--bg); color:var(--ink); font-size:13px; }

  .hd { position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid var(--line); padding:12px 18px; }
  .hd-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .hd-title { font-size:15px; font-weight:700; }
  .hd-sub { font-size:12px; color:var(--sub); }
  .grow { flex:1; }
  .btn { border:1px solid var(--line); background:#fff; border-radius:7px; padding:6px 12px;
         font-size:12px; font-weight:700; cursor:pointer; color:var(--ink); }
  .btn:hover { border-color:var(--pri); color:var(--pri); }
  .btn-pri { background:var(--pri); border-color:var(--pri); color:#fff; }
  .btn-pri:hover { opacity:.9; color:#fff; }

  .prog { display:flex; align-items:center; gap:8px; margin-top:9px; font-size:12px; }
  .prog-bar { flex:1; height:6px; background:var(--line); border-radius:99px; overflow:hidden; max-width:340px; }
  .prog-fill { height:100%; background:var(--pri); width:0; transition:width .2s; }
  .miss { color:var(--danger); font-weight:700; }

  .steps { margin-top:9px; font-size:11px; color:var(--sub); }
  .steps b { color:var(--pri); }

  .wrap { padding:16px 18px 40px; max-width:900px; margin:0 auto; }
  .grp { background:#fff; border:1px solid var(--line); border-radius:10px; margin-bottom:14px; overflow:hidden; }
  .grp-hd { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#fafbfc; border-bottom:1px solid var(--line); }
  .grp-name { font-size:13px; font-weight:700; flex:1; }

  .row { display:flex; align-items:center; gap:10px; padding:9px 14px; border-bottom:1px solid #f2f4f6; }
  .row:last-child { border-bottom:none; }
  .row.done { background:var(--ok-bg); }
  .row.empty { background:var(--danger-bg); }
  .lbl { width:210px; flex-shrink:0; font-size:12px; color:var(--sub); }
  .val { flex:1; min-width:0; font-weight:700; word-break:break-all; }
  .val.none { color:var(--danger); font-weight:400; }
  .note { font-size:11px; color:var(--sub); font-weight:400; margin-top:2px; }
  .note.warn { color:var(--warn); font-weight:700; }
  .tag { font-size:10px; font-weight:700; border-radius:5px; padding:1px 6px; margin-left:6px;
         background:var(--pri-50); color:var(--pri); border:1px solid var(--pri-200); }
  .tag.ask { background:var(--warn-bg); color:var(--warn); border-color:#fcd34d; }
  .cbtn { border:1px solid var(--line); background:#fff; border-radius:6px; padding:4px 10px;
          font-size:11px; font-weight:700; cursor:pointer; color:var(--sub); flex-shrink:0; min-width:56px; }
  .cbtn:hover:not(:disabled) { border-color:var(--pri); color:var(--pri); }
  .cbtn:disabled { opacity:.4; cursor:not-allowed; }
  .cbtn.done { background:var(--ok-bg); border-color:#86efac; color:var(--ok); }

  .banner { background:var(--warn-bg); border:1px solid #fcd34d; color:var(--warn);
            border-radius:8px; padding:9px 12px; font-size:12px; margin-bottom:14px; line-height:1.6; }
  .banner.info { background:var(--pri-50); border-color:var(--pri-200); color:var(--pri); }
  .toast { position:fixed; left:50%; bottom:26px; transform:translateX(-50%);
           background:var(--ink); color:#fff; padding:9px 16px; border-radius:8px; font-size:12px;
           opacity:0; transition:opacity .15s; pointer-events:none; z-index:99; }
  .toast.on { opacity:1; }
</style>
@stack('style')
</head>
<body>

<div class="hd">
  <div class="hd-row">
    <div>
      <div class="hd-title">@yield('title')</div>
      <div class="hd-sub">@yield('subtitle')</div>
    </div>
    <div class="grow"></div>
    <button class="btn" onclick="resetAll()">복사 기록 지우기</button>
    <button class="btn btn-pri" onclick="window.open(@js($portalUrl),'_blank','noopener')">공단 사이트 열기</button>
  </div>

  <div class="prog">
    <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
    <span id="progText">0 / 0 복사</span>
    @if(($missing ?? 0) > 0)<span class="miss">· 값 없음 {{ $missing }}건</span>@endif
  </div>

  <div class="steps">@yield('steps')</div>
</div>

<div class="wrap">@yield('body')</div>

<div class="toast" id="toast"></div>

<script>
const CSRF   = document.querySelector('meta[name=csrf-token]').content;
const REVEAL = @js($revealUrl ?? null);
/* 복사 기록은 브라우저에만 둔다. 한 번 청구하는 동안만 쓰는 값이라 서버에 남길 값어치가 없다. */
const STORE  = @js($storeKey);
let   copied = new Set(JSON.parse(sessionStorage.getItem(STORE) || '[]'));

function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('on');
  clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('on'), 1600);
}

/* HTTPS 가 아니거나 브라우저가 막으면 클립보드 API 가 없다. 값을 못 옮기면 화면이 무의미하므로
   보이지 않는 textarea 로 대신한다. */
async function toClipboard(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch (_) {}
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;top:-1000px;opacity:0;';
  document.body.appendChild(ta); ta.select();
  const ok = document.execCommand('copy');
  ta.remove();
  return ok;
}

async function valueOf(row) {
  if (row.dataset.reveal === '1' && !row.dataset.revealed) {
    const res  = await fetch(REVEAL, { method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' } });
    const data = await res.json();
    if (!data.ok) { toast(data.message || '주민등록번호를 열 수 없습니다.'); return null; }
    row.querySelector('[data-val]').textContent = data.back;
    row.dataset.revealed = '1';
    return data.back;
  }
  return row.querySelector('[data-val]').textContent.trim();
}

async function copyRow(btn) {
  const row = btn.closest('.row');
  if (row.dataset.copy !== '1') return;

  const val = await valueOf(row);
  if (val === null) return;
  if (!await toClipboard(val)) { toast('복사하지 못했습니다.'); return; }

  mark(row);
  btn.textContent = '복사됨';
  btn.classList.add('done');
  toast('복사했습니다 — 공단 화면 같은 자리에 붙여넣으십시오');
}

/* 공단 화면에서 Tab 으로 옮겨 가며 연속 입력할 때 쓴다. 다만 탭 순서가 화면 배치와 다를 수
   있으므로 항목 단위 복사가 기본이다. */
async function copyGroup(btn) {
  const scope = btn.closest('[data-copy-scope]') || btn.closest('.grp');
  const rows  = [...scope.querySelectorAll('.row')].filter(r => r.dataset.copy === '1');
  const vals  = [];
  for (const r of rows) {
    const v = await valueOf(r);
    if (v !== null) { vals.push(v); mark(r); }
  }
  if (!vals.length) { toast('복사할 값이 없습니다.'); return; }
  if (!await toClipboard(vals.join('\n'))) { toast('복사하지 못했습니다.'); return; }

  scope.querySelectorAll('.row[data-copy="1"] .cbtn')
       .forEach(b => { b.textContent = '복사됨'; b.classList.add('done'); });
  // 국세청자료처럼 항목이 숨어 있는 묶음은 눌린 버튼 자체에 표시가 남아야 한다
  btn.classList.add('done');
  toast(vals.length + '개 항목을 줄바꿈으로 복사했습니다');
}

function mark(row) {
  row.classList.add('done');
  copied.add(row.dataset.key);
  sessionStorage.setItem(STORE, JSON.stringify([...copied]));
  progress();
}

function progress() {
  const total = document.querySelectorAll('.row[data-copy="1"]').length;
  const done  = document.querySelectorAll('.row[data-copy="1"].done').length;
  document.getElementById('progText').textContent = `${done} / ${total} 복사`;
  document.getElementById('progFill').style.width = total ? (done / total * 100) + '%' : '0';
}

function resetAll() {
  copied.clear();
  sessionStorage.removeItem(STORE);
  document.querySelectorAll('.row').forEach(r => r.classList.remove('done'));
  document.querySelectorAll('.cbtn').forEach(b => {
    if (b.textContent === '복사됨') { b.textContent = '복사'; b.classList.remove('done'); }
  });
  progress();
  toast('복사 기록을 지웠습니다');
}

// 창을 벗어났다 돌아와도 어디까지 했는지 남아 있어야 한다
document.querySelectorAll('.row').forEach(row => {
  if (copied.has(row.dataset.key)) {
    row.classList.add('done');
    const b = row.querySelector('.cbtn');
    if (b) { b.textContent = '복사됨'; b.classList.add('done'); }
  }
});
// 항목이 숨어 있는 묶음은 묶음 버튼에만 표시가 남으므로 따로 되살린다
document.querySelectorAll('[data-copy-scope]').forEach(scope => {
  const rows = [...scope.querySelectorAll('.row[data-copy="1"]')];
  if (rows.length && rows.every(r => r.classList.contains('done'))) {
    scope.querySelectorAll('.cbtn').forEach(b => b.classList.add('done'));
  }
});
progress();
</script>
@stack('script')
</body>
</html>
