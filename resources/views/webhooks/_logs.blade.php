{{-- 전송·수신 로그 — 웹훅 관리 화면의 옆 탭 (2026-09-10 지시).

     화면 안에서 함께 그린다. 따로 불러오지 않는다 — 화면이 하나면 새로고침ㆍ뒤로
     가기ㆍ주소 공유가 모두 그대로 돈다.

     거르개 이름은 log_ 로 시작한다. 웹훅 목록 쪽에도 구분ㆍ방향ㆍ검색어가 있어
     한 주소를 나눠 쓰면 이름이 부딪친다.

     받는 것 — $logRows · $logCounts · $logFrom · $logTo · $logProvider ·
               $logDirection · $logResult · $logSearch · $보내는곳(폼이 갈 자리) --}}

@php($_보낼곳 = $보내는곳 ?? route('webhooks.index'))

@push('styles')
<style>
  .wl-body { margin:0; padding:11px 12px; background:var(--gray-50); border:1px solid var(--gray-200);
             border-radius:8px; font-size:11.5px; line-height:1.65; white-space:pre-wrap; word-break:break-all;
             max-height:340px; overflow:auto; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; }
  .wl-label { font-size:12px; font-weight:700; color:var(--primary); margin:0 0 5px; }
  .wl-chip { display:inline-flex; align-items:center; height:22px; padding:0 9px; border-radius:999px;
             font-size:11px; font-weight:700; }
  .wl-ok   { background:var(--primary-50); color:var(--primary); }
  .wl-fail { background:var(--danger-light); color:var(--danger); }
  .wl-head { display:flex; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap; }
</style>
@endpush




<div id="wlGrid"></div>

@if($logCounts['all'] >= 1000)
  <div style="margin-top:8px;font-size:11.5px;color:var(--text-muted);">
    최근 1,000건까지만 보여 줍니다. 더 보려면 기간을 좁히십시오.
  </div>
@endif

{{-- 상세 --}}
<div id="wlBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="wlClose()"></div>
<div id="wlModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:820px;max-width:96vw;max-height:92vh;overflow:auto;background:var(--bg-card);
     border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;position:sticky;top:0;z-index:2;">
    <i class="fa-solid fa-clock-rotate-left" style="color:#fff;font-size:14px;"></i>
    <span id="wlTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">웹훅 기록</span>
    <button onclick="wlClose()" style="border:none;background:none;color:#fff;font-size:16px;line-height:1;cursor:pointer;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;" id="wlBody"></div>
</div>


@push('scripts')
<script>
(function () {
  const ROWS = @json($logRows);

  /* 성공ㆍ실패는 한눈에 갈려야 한다 — 실패한 줄을 찾으러 오는 화면이다 */
  const 결과칸 = (v) => {
    const s = document.createElement('span');
    s.className = 'wl-chip ' + (v === '성공' ? 'wl-ok' : 'wl-fail');
    s.textContent = v;
    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('wlGrid'),
    height: 'auto', editable: false, rowCheckbox: false, rowNumber: true,
    toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '일시',      name: 'at',        width: 160, align: 'center', sortable: true },
      { header: '구분',      name: 'provider',  width: 120, align: 'center', sortable: true },
      { header: '방향',      name: 'direction', width: 70,  align: 'center', sortable: true },
      { header: '웹훅 명',   name: 'name',      width: 150, sortable: true },
      { header: '이벤트',    name: 'event',     width: 200, sortable: true },
      { header: '결과',      name: 'result',    width: 80,  align: 'center', sortable: true, renderer: 결과칸 },
      { header: '응답코드',  name: 'status',    width: 90,  align: 'center', sortable: true },
      { header: '서명',      name: 'sign',      width: 70,  align: 'center' },
      { header: '걸린시간(ms)', name: 'ms',     width: 110, align: 'right', sortable: true },
      { header: '관련 번호', name: 'ref',       width: 160, sortable: true },
      { header: 'URL',       name: 'url',       width: 240 },
      { header: '보낸 곳',   name: 'ip',        width: 130 },
      { header: '오류',      name: 'error',     width: 300 },
    ],
    data: ROWS,
  });
  window.__wlGrid = grid;

  document.getElementById('wlGrid').addEventListener('dblclick', (e) => {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) wlOpen(row);
  });

  const esc = (v) => String(v ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

  window.wlOpen = function (row) {
    const r = row.raw ?? {};
    const 줄 = (이름, 값) => `<div><div class="wl-label">${이름}</div><pre class="wl-body">${esc(값 || '(없음)')}</pre></div>`;

    document.getElementById('wlTitle').textContent =
      `${row.provider} · ${row.name || row.event || '웹훅'} · ${row.at}`;

    document.getElementById('wlBody').innerHTML =
      `<div style="display:flex;gap:8px;flex-wrap:wrap;font-size:12px;color:var(--gray-700);">
         <span class="wl-chip ${row.result === '성공' ? 'wl-ok' : 'wl-fail'}">${esc(row.result)}</span>
         <span>응답코드 <b>${esc(row.status || '-')}</b></span>
         <span>서명 <b>${esc(row.sign || '없음')}</b></span>
         <span>걸린시간 <b>${esc(row.ms || '-')} ms</b></span>
         <span>보낸 곳 <b>${esc(row.ip || '-')}</b></span>
         <span>관련 번호 <b>${esc(row.ref || '-')}</b></span>
       </div>
       <div style="font-size:12px;color:var(--gray-700);">${esc(row.direction)} · <b>${esc(row.url)}</b></div>` +
      (r.error ? 줄('오류', r.error) : '') +
      줄('파라미터 값 (요청 본문)', r.payload) +
      줄('응답', r.response) +
      줄('요청 헤더', JSON.stringify(r.headers ?? {}, null, 2));

    document.getElementById('wlBackdrop').style.display = 'block';
    document.getElementById('wlModal').style.display    = 'block';
  };

  window.wlClose = function () {
    document.getElementById('wlBackdrop').style.display = 'none';
    document.getElementById('wlModal').style.display    = 'none';
  };
})();
</script>
@endpush
