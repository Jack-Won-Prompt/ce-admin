@extends('layouts.app')

@section('title', '오류 이력')
@section('page-title', '오류 이력')
@section('breadcrumb', '홈 - 설정 - 오류 이력')

@section('content')

{{-- 서버에서 난 잘못을 담당자가 화면에서 본다 (2026-09-11 지시).
     얼개는 웹훅 로그와 같다 — 거르개가 맨 위, 그 아래 카드에 표 하나, 줄을 누르면 창. --}}

@push('styles')
<style>
  .el-chip  { display:inline-flex; align-items:center; height:22px; padding:0 9px; border-radius:999px;
              font-size:11px; font-weight:700; }
  .el-open  { background:var(--danger-light);  color:var(--danger); }
  .el-check { background:var(--warning-light, #FFF4E5); color:var(--warning); }
  .el-fixed { background:var(--primary-50);    color:var(--primary); }
  .el-ign   { background:var(--gray-100);      color:var(--text-muted); }
  .el-hit   { display:inline-flex; align-items:center; height:20px; padding:0 7px; border-radius:6px;
              font-size:11px; font-weight:700; background:var(--gray-100); color:var(--gray-700); }
  .el-hot   { background:var(--danger-light); color:var(--danger); }
  .el-body  { margin:0; padding:11px 12px; background:var(--gray-50); border:1px solid var(--gray-200);
              border-radius:8px; font-size:11.5px; line-height:1.65; white-space:pre-wrap; word-break:break-all;
              max-height:340px; overflow:auto; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; }
  .el-label { font-size:12px; font-weight:700; color:var(--primary); margin:0 0 5px; }
  .el-sum   { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

  /* 목록에서 바로 처리 상태를 바꾼다 (2026-10-02 지시) — 딱지를 누르면 고르는 창이 뜬다 */
  .el-chip.el-pick { cursor:pointer; gap:4px; }
  .el-chip.el-pick:hover { filter:brightness(.94); }
  .el-chip.el-pick i { font-size:8px; opacity:.65; }
  .el-pop   { position:fixed; z-index:1200; min-width:132px; padding:5px;
              background:var(--bg-card); border:1px solid var(--border);
              border-radius:10px; box-shadow:0 10px 28px rgba(0,0,0,.16); }
  .el-pop button { display:flex; align-items:center; gap:7px; width:100%; padding:7px 9px;
                   border:none; background:none; border-radius:7px; cursor:pointer;
                   font-size:12px; color:var(--text-main); text-align:left; }
  .el-pop button:hover { background:var(--gray-100); }
  .el-pop button.on { font-weight:700; color:var(--primary); }
  .el-pop .dot { width:8px; height:8px; border-radius:999px; flex:none; }
</style>
@endpush

{{-- 거르개 — 한 줄로 세운다. 아홉 열 격자에 기간 2 · 나머지 다섯을 나눠 담는다
     (2026-09-11 지시). 다른 목록 화면과 같은 얼개(ds-filter-card)를 쓴다. --}}
<form method="GET" action="{{ route('error-logs.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="from" value="{{ $from }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">출처</label>
      <select name="source" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach($출처표 as $코 => $말)
          <option value="{{ $코 }}" @selected($source === $코)>{{ $말 }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">오류 유형</label>
      <select name="kind" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach($갈래들 as $k)
          <option value="{{ $k->kind }}" @selected($kind === $k->kind)>
            {{ $k->kind }} ({{ number_format($k->h) }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">응답코드</label>
      <select name="http_status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach([500, 419, 429, 403] as $c)
          <option value="{{ $c }}" @selected((string) $httpStatus === (string) $c)>{{ $c }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">처리 상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach($상태표 as $코 => $말)
          <option value="{{ $코 }}" @selected($status === $코)>{{ $말 }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control"
             placeholder="오류 내용ㆍ주소ㆍ파일ㆍ화면 이름ㆍ사용자">
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('error-logs.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <span class="pnl-tab active">
        <i class="fa-solid fa-triangle-exclamation"></i> 오류 이력
        <span class="pnl-tab-cnt">(총 {{ number_format($셈['all']) }}건)</span>
      </span>
      <span style="margin-left:auto;" class="el-sum">
        <span class="el-chip el-open">미확인 {{ number_format($셈['open']) }}</span>
        <span class="el-chip el-fixed">발생 횟수 {{ number_format($셈['hit']) }}</span>
        <span class="el-chip el-check">서버 {{ number_format($셈['server']) }}</span>
        <span class="el-chip el-ign">브라우저 {{ number_format($셈['browser']) }}</span>
        <button type="button" class="ds-btn" onclick="window.__elGrid?.downloadExcel()">엑셀 다운</button>
        @if((auth()->user()->role ?? '') === 'admin')
          <button type="button" class="ds-btn" onclick="elPurge()">오래된 기록 삭제</button>
        @endif
      </span>
    </div>
    <div style="padding:16px;">
      <div id="elGrid"></div>
      @if($셈['all'] >= (int) config('errors.list_limit', 1000))
        <div style="margin-top:8px;font-size:11.5px;color:var(--text-muted);">
          최근 {{ number_format((int) config('errors.list_limit', 1000)) }}건까지만 보여 줍니다. 더 보려면 기간을 좁히십시오.
        </div>
      @endif
    </div>
  </div>
</div>

{{-- 상세 --}}
<div id="elBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="elClose()"></div>
<div id="elModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:900px;max-width:96vw;max-height:92vh;overflow:auto;background:var(--bg-card);
     border:1px solid var(--danger);border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--danger);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;position:sticky;top:0;z-index:2;">
    <i class="fa-solid fa-triangle-exclamation" style="color:#fff;font-size:14px;"></i>
    <span id="elTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">오류</span>
    <button onclick="elClose()" style="border:none;background:none;color:#fff;font-size:16px;line-height:1;cursor:pointer;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;" id="elBody"></div>
</div>

@push('scripts')
<script>
(function () {
  const ROWS   = @json($rows);
  const 상태표 = @json($상태표);

  const 고칠수있나 = @json($고칠수있나);

  const 반 = { '미확인': 'el-open', '확인': 'el-check', '조치 완료': 'el-fixed', '보류': 'el-ign' };

  /* 보이는 글에서 저장할 코드를 되찾는 표 — 상태를 바꾼 뒤 다시 그릴 때 쓴다.
     wwGrid 의 renderer 는 getData() 가 떠 준 사본을 받으므로 row 에 담긴
     state_key 는 처음 값에 머문다. 칸에 적힌 글이 지금 값이다. */
  const 코드찾기 = {};
  Object.entries(상태표).forEach(([k, v]) => { 코드찾기[v] = k; });

  const 딱지 = (v, row) => {
    const s = document.createElement('span');
    s.className = 'el-chip ' + (반[v] || 'el-ign') + (고칠수있나 ? ' el-pick' : '');
    s.textContent = v;
    if (고칠수있나) {
      s.dataset.elId = row.id;
      s.insertAdjacentHTML('beforeend', ' <i class="fa-solid fa-chevron-down"></i>');
    }
    return s;
  };

  /* 서버에서 난 것과 브라우저에서 난 것은 손대는 자리가 다르다 — 한눈에 갈려야 한다 */
  const 출처칸 = (v) => {
    const s = document.createElement('span');
    s.className = 'el-chip ' + (v === '브라우저' ? 'el-ign' : 'el-check');
    s.textContent = v;
    return s;
  };

  /* 자주 난 것은 눈에 띄어야 한다 — 열 번 넘게 난 줄은 붉게 */
  const 횟수칸 = (v) => {
    const s = document.createElement('span');
    s.className = 'el-hit' + (Number(v) >= 10 ? ' el-hot' : '');
    s.textContent = Number(v).toLocaleString('ko-KR');
    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('elGrid'),
    height: 'auto', editable: false, rowCheckbox: false, rowNumber: true,
    toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '최근 발생 일시', name: 'at',      width: 160, align: 'center', sortable: true },
      { header: '출처',           name: 'source',  width: 80,  align: 'center', sortable: true, renderer: 출처칸 },
      { header: '오류 유형',      name: 'kind',    width: 170, sortable: true },
      { header: '응답코드',       name: 'status',  width: 90,  align: 'center', sortable: true },
      { header: '오류 내용',      name: 'message', width: 340, sortable: true },
      { header: '발생 위치',      name: 'where',   width: 280, sortable: true },
      { header: '화면',           name: 'route',   width: 160, sortable: true },
      { header: '사용자',         name: 'user',    width: 100, align: 'center', sortable: true },
      { header: '발생 횟수',      name: 'hit',     width: 90,  align: 'center', sortable: true, renderer: 횟수칸 },
      { header: '처리 상태',      name: 'state',   width: 100, align: 'center', sortable: true, renderer: 딱지 },
    ],
    data: ROWS,
  });
  window.__elGrid = grid;
  /* 쪽 나누기 — 모든 목록 화면이 한 모양으로 쓴다 (2026-10-02 지시).
     표 바로 아래에 줄이 선다. 합계ㆍ엑셀ㆍ정렬은 전체 기준을 지킨다. */
  cePager.붙이기(grid);

  /* 줄을 겹누르면 온 내용을 편다 */
  document.getElementById('elGrid').addEventListener('dblclick', (e) => {
    if (e.target.closest('.el-pick')) return;   // 상태 딱지는 고르는 자리다
    const 줄 = e.target.closest('[data-row-index]');
    if (!줄) return;
    const r = ROWS[Number(줄.dataset.rowIndex)];
    if (r) elOpen(r.id);
  });

  /* ── 목록에서 처리 상태 바꾸기 (2026-10-02 지시) ───────────────────────────
     고친 오류의 줄을 창까지 열지 않고 바로 「조치 완료」로 돌려놓을 수 있어야 한다. */
  let 열린창 = null;

  const 창닫기 = () => { 열린창?.remove(); 열린창 = null; };

  document.addEventListener('click', async (e) => {
    const 고르기 = e.target.closest('.el-pop button');
    if (고르기) {
      const 창 = 고르기.closest('.el-pop');
      await 상태저장(Number(창.dataset.elId), 고르기.dataset.key, 창.__칸);
      창닫기();
      return;
    }

    const 딱 = e.target.closest('.el-chip.el-pick');
    if (! 딱) { 창닫기(); return; }

    const 이미 = 열린창 && 열린창.dataset.elId === 딱.dataset.elId;
    창닫기();
    if (이미) return;   // 같은 딱지를 다시 누르면 닫는다

    const 칸  = 딱.closest('[data-row-index]');
    const 지금 = 코드찾기[딱.textContent.trim()] || 'open';

    const 창 = document.createElement('div');
    창.className = 'el-pop';
    창.dataset.elId = 딱.dataset.elId;
    창.__칸 = 칸;
    창.innerHTML = Object.entries(상태표).map(([k, v]) => `
      <button type="button" data-key="${k}" class="${k === 지금 ? 'on' : ''}">
        <span class="dot" style="background:var(--${{open:'danger',checked:'warning',fixed:'primary',ignored:'text-muted'}[k]});"></span>
        <span>${v}</span>
      </button>`).join('');
    document.body.appendChild(창);

    /* 화면 밖으로 넘지 않게 — 아래가 좁으면 딱지 위로 올려 띄운다 */
    const 자리 = 딱.getBoundingClientRect();
    const 높이 = 창.offsetHeight;
    창.style.left = Math.min(자리.left, window.innerWidth - 창.offsetWidth - 12) + 'px';
    창.style.top  = (자리.bottom + 높이 + 12 > window.innerHeight ? 자리.top - 높이 - 6 : 자리.bottom + 6) + 'px';

    열린창 = 창;
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') 창닫기(); });
  window.addEventListener('resize', 창닫기);

  async function 상태저장(id, 코드, 칸) {
    const res = await fetch(`/settings/error-logs/${id}/mark`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify({ status: 코드 }),   // 메모는 보내지 않는다 — 창에서 적은 것을 지우지 않게
    });

    if (! res.ok) { showToast('처리 상태를 저장하지 못했습니다.', 'danger'); return; }
    const d = await res.json();
    if (! d.success) { showToast('처리 상태를 저장하지 못했습니다.', 'danger'); return; }

    /* 그려 둔 칸을 그 자리에서 고친다 — 다시 읽지 않는다 */
    if (칸) {
      const i = Number(칸.dataset.rowIndex);
      grid.setValue(i, 'state', d.state);
      const r = ROWS[i];
      if (r) { r.state = d.state; r.state_key = 코드; }
    }
    showToast(d.message, 'success');
  }

  const 토막 = (제목, 값, 홑 = false) => {
    if (값 === null || 값 === undefined || 값 === '') return '';
    const 몸 = 홑
      ? `<div style="font-size:12.5px;line-height:1.7;word-break:break-all;">${값}</div>`
      : `<pre class="el-body">${String(값).replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</pre>`;
    return `<div><p class="el-label">${제목}</p>${몸}</div>`;
  };

  window.elOpen = async function (id) {
    const res = await fetch(`/settings/error-logs/${id}`, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) { showToast('기록을 불러오지 못했습니다.', 'danger'); return; }
    const d = await res.json();

    document.getElementById('elTitle').textContent =
      `${d.kind} · ${d.status ?? ''} · ${d.last_at ?? ''}`;

    const 고르개 = Object.entries(상태표)
      .map(([k, v]) => `<option value="${k}" ${k === d.state ? 'selected' : ''}>${v}</option>`).join('');

    document.getElementById('elBody').innerHTML =
      토막('오류 내용', d.message) +
      토막('발생 위치', `${d.file ?? '-'}:${d.line ?? ''}${d.col ? ':' + d.col : ''}`, true) +
      토막('출처', d.source, true) +
      토막('예외 클래스', d.exception, true) +
      토막('주소', `${d.method ?? ''} ${d.url ?? '-'}`, true) +
      토막('화면 이름', d.route, true) +
      토막('사용자 · 접속 IP', `${d.user ?? '-'} · ${d.ip ?? '-'}`, true) +
      토막('최초 발생 · 최근 발생 · 발생 횟수',
           `${d.first_at ?? '-'} · ${d.last_at ?? '-'} · ${Number(d.hit).toLocaleString('ko-KR')}회`, true) +
      토막('요청 값', d.input) +
      토막('호출 경로', d.trace) +
      `<div style="border-top:1px solid var(--border);padding-top:12px;">
         <p class="el-label">처리 상태</p>
         <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
           <select id="elState" class="form-control" style="width:130px;">${고르개}</select>
           <input type="text" id="elMemo" class="form-control" style="flex:1 1 240px;"
                  placeholder="처리 메모 (선택)" value="${(d.memo ?? '').replace(/"/g, '&quot;')}">
           <button type="button" class="ds-btn ds-btn-primary" onclick="elMark(${d.id})">저장</button>
           <button type="button" class="ds-btn" style="margin-left:auto;color:var(--danger);"
                   onclick="elDelete(${d.id})">이 기록 삭제</button>
         </div>
         ${d.checked ? `<div style="margin-top:6px;font-size:11.5px;color:var(--text-muted);">
            ${d.checked} · ${d.checked_at ?? ''}</div>` : ''}
       </div>`;

    document.getElementById('elBackdrop').style.display = 'block';
    document.getElementById('elModal').style.display = 'block';
  };

  window.elClose = function () {
    document.getElementById('elBackdrop').style.display = 'none';
    document.getElementById('elModal').style.display = 'none';
  };

  window.elMark = async function (id) {
    const res = await fetch(`/settings/error-logs/${id}/mark`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify({
        status: document.getElementById('elState').value,
        memo:   document.getElementById('elMemo').value,
      }),
    });
    const d = await res.json();
    if (d.success) { showToast(d.message, 'success'); elClose(); location.reload(); }
    else { showToast('저장하지 못했습니다.', 'danger'); }
  };

  /* 한 건만 삭제한다 — 오래된 기록 일괄 삭제로는 오늘 기록을 지울 수 없어
     시험용으로 남은 기록 하나를 치울 방법이 없었다 (2026-10-08) */
  window.elDelete = async function (id) {
    if (!await ceConfirm('이 오류 기록을 삭제합니다. 복구할 수 없습니다.', { tone: 'danger' })) return;
    const res = await fetch(`/settings/error-logs/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
    });
    const d = await res.json().catch(() => ({}));
    if (res.ok && d.success) { showToast(d.message, 'success'); elClose(); location.reload(); }
    else { showToast(d.message || '삭제하지 못했습니다.', 'danger'); }
  };

  window.elPurge = async function () {
    if (!await ceConfirm('180일보다 오래된 기록을 삭제합니다. 복구할 수 없습니다.', { tone: 'danger' })) return;
    const res = await fetch('/settings/error-logs/purge', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify({ days: 180 }),
    });
    const d = await res.json();
    if (d.success) { showToast(d.message, 'success'); location.reload(); }
  };
})();
</script>
@endpush

@endsection
