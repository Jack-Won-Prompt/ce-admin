{{-- 공단 요양비청구위임내역등록(2225) 입력 지원 --}}
{{-- 공단 사이트와 나란히 놓고 보는 창이라 사이드바 없이 혼자 선다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>공단 위임 등록 지원 — {{ $prescription->patient?->name ?? $prescription->patient_name_ocr }}</title>
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
  .cbtn { border:1px solid var(--line); background:#fff; border-radius:6px; padding:4px 10px;
          font-size:11px; font-weight:700; cursor:pointer; color:var(--sub); flex-shrink:0; min-width:56px; }
  .cbtn:hover:not(:disabled) { border-color:var(--pri); color:var(--pri); }
  .cbtn:disabled { opacity:.4; cursor:not-allowed; }
  .cbtn.done { background:var(--ok-bg); border-color:#86efac; color:var(--ok); }

  .banner { background:var(--warn-bg); border:1px solid #fcd34d; color:var(--warn);
            border-radius:8px; padding:9px 12px; font-size:12px; margin-bottom:14px; line-height:1.6; }
  .toast { position:fixed; left:50%; bottom:26px; transform:translateX(-50%);
           background:var(--ink); color:#fff; padding:9px 16px; border-radius:8px; font-size:12px;
           opacity:0; transition:opacity .15s; pointer-events:none; z-index:99; }
  .toast.on { opacity:1; }
</style>
</head>
<body>

@php
  $total = 0;
  foreach ($groups as $rows) {
    foreach ($rows as $r) { if (($r['copy'] ?? true) && ($r['value'] ?? null) !== null) { $total++; } }
  }
  $missing = 0;
  foreach ($groups as $rows) {
    foreach ($rows as $r) { if (($r['copy'] ?? true) && ($r['value'] ?? null) === null) { $missing++; } }
  }
@endphp

<div class="hd">
  <div class="hd-row">
    <div>
      <div class="hd-title">공단 위임 등록 입력 지원</div>
      <div class="hd-sub">
        요양비 &gt; 요양비청구 &gt; 2225 요양비청구위임내역등록 ·
        {{ $prescription->patient?->name ?? $prescription->patient_name_ocr }} · {{ $prescription->rx_number }}
      </div>
    </div>
    <div class="grow"></div>
    <button class="btn" onclick="resetAll()">복사 기록 지우기</button>
    <button class="btn btn-pri" onclick="window.open(@js($portalUrl),'_blank','noopener')">공단 사이트 열기</button>
  </div>

  <div class="prog">
    <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
    <span id="progText">0 / {{ $total }} 복사</span>
    @if($missing > 0)<span class="miss">· 값 없음 {{ $missing }}건</span>@endif
  </div>

  <div class="steps">
    입력 순서 — <b>① 내용입력</b> → <b>② 저장</b> → <b>③ 파일첨부</b>(위임장 · 위임인 신분증) → <b>④ 저장 및 최종제출</b>
  </div>
</div>

<div class="wrap">

  @unless($consent)
    <div class="banner">
      <b>서명된 위임동의가 없습니다.</b> 위임자 정보를 채울 수 없어 일부 항목이 비어 있습니다.
      위임장 서명을 먼저 받으십시오.
    </div>
  @endunless

  @foreach($groups as $groupName => $rows)
    <div class="grp">
      <div class="grp-hd">
        <span class="grp-name">{{ $groupName }}</span>
        <button class="cbtn" onclick="copyGroup(this)">그룹 복사</button>
      </div>

      @foreach($rows as $i => $r)
        @php
          $key      = \Illuminate\Support\Str::slug($groupName).'-'.$i;
          $canCopy  = ($r['copy'] ?? true) && ($r['value'] ?? null) !== null;
          $isEmpty  = ($r['copy'] ?? true) && ($r['value'] ?? null) === null;
        @endphp
        <div class="row {{ $isEmpty ? 'empty' : '' }}" data-key="{{ $key }}"
             data-copy="{{ $canCopy ? '1' : '0' }}"
             @if($r['reveal'] ?? false) data-reveal="1" @endif>
          <div class="lbl">{{ $r['label'] }}</div>
          <div>
            <div class="val {{ $isEmpty ? 'none' : '' }}" data-val>{{ $r['value'] ?? '데이터 없음' }}</div>
            @if($r['note'] ?? null)<div class="note">{{ $r['note'] }}</div>@endif
            @if($r['warn'] ?? null)<div class="note warn">⚠ {{ $r['warn'] }}</div>@endif
          </div>
          @if($r['fixed'] ?? false)<span class="tag">고정</span>@endif
          <div class="grow"></div>
          @if(($r['copy'] ?? true))
            <button class="cbtn" onclick="copyRow(this)" {{ $canCopy ? '' : 'disabled' }}>복사</button>
          @endif
        </div>
      @endforeach
    </div>
  @endforeach

</div>

<div class="toast" id="toast"></div>

<script>
const RX       = @js($prescription->rx_number);
const REVEAL   = @js(route('nhis.assist.rrn', $prescription));
const CSRF     = document.querySelector('meta[name=csrf-token]').content;
/* 복사 기록은 브라우저에만 둔다. 한 번 등록하는 동안만 쓰는 값이라 서버에 남길 값어치가 없다. */
const STORE    = 'nhis-delegation:' + RX;
let   copied   = new Set(JSON.parse(sessionStorage.getItem(STORE) || '[]'));

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
  const rows = [...btn.closest('.grp').querySelectorAll('.row')].filter(r => r.dataset.copy === '1');
  const vals = [];
  for (const r of rows) {
    const v = await valueOf(r);
    if (v !== null) { vals.push(v); mark(r); }
  }
  if (!vals.length) { toast('복사할 값이 없습니다.'); return; }
  if (!await toClipboard(vals.join('\n'))) { toast('복사하지 못했습니다.'); return; }

  btn.closest('.grp').querySelectorAll('.row[data-copy="1"] .cbtn')
     .forEach(b => { b.textContent = '복사됨'; b.classList.add('done'); });
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
progress();
</script>
</body>
</html>
