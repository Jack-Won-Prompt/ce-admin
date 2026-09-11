@extends('layouts.app')

@section('title', '오류 기록')
@section('page-title', '오류 기록')
@section('breadcrumb', '홈 - 설정 - 오류 기록')

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
</style>
@endpush

{{-- 거르개 --}}
<div class="ds-filter-card">
  <form method="GET" action="{{ route('error-logs.index') }}" class="ds-filter-form">
    <div class="ds-filter-row">
      <div class="ds-field">
        <label>기간</label>
        <div style="display:flex;align-items:center;gap:6px;">
          <input type="date" name="from" value="{{ $from }}" class="form-control">
          <span style="color:var(--text-muted);">~</span>
          <input type="date" name="to" value="{{ $to }}" class="form-control">
        </div>
      </div>
      <div class="ds-field">
        <label>출처</label>
        <select name="source" class="form-control">
          <option value="">전체 출처</option>
          @foreach($출처표 as $코 => $말)
            <option value="{{ $코 }}" @selected($source === $코)>{{ $말 }}</option>
          @endforeach
        </select>
      </div>
      <div class="ds-field">
        <label>오류 유형</label>
        <select name="kind" class="form-control">
          <option value="">전체 유형</option>
          @foreach($갈래들 as $k)
            <option value="{{ $k->kind }}" @selected($kind === $k->kind)>
              {{ $k->kind }} ({{ number_format($k->h) }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="ds-field">
        <label>응답코드</label>
        <select name="http_status" class="form-control">
          <option value="">전체</option>
          @foreach([500, 419, 429, 403] as $c)
            <option value="{{ $c }}" @selected((string) $httpStatus === (string) $c)>{{ $c }}</option>
          @endforeach
        </select>
      </div>
      <div class="ds-field">
        <label>처리 상태</label>
        <select name="status" class="form-control">
          <option value="">전체 상태</option>
          @foreach($상태표 as $코 => $말)
            <option value="{{ $코 }}" @selected($status === $코)>{{ $말 }}</option>
          @endforeach
        </select>
      </div>
      <div class="ds-field" style="flex:1 1 240px;">
        <label>검색어</label>
        <input type="text" name="q" value="{{ $q }}" class="form-control"
               placeholder="오류 내용ㆍ주소ㆍ파일ㆍ화면 이름ㆍ사용자">
      </div>
      <div class="ds-field ds-field-btns">
        <a href="{{ route('error-logs.index') }}" class="ds-btn"><i class="fa-solid fa-rotate-left"></i> 초기화</a>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> 조회</button>
      </div>
    </div>
  </form>
</div>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <span class="pnl-tab active">
        <i class="fa-solid fa-triangle-exclamation"></i> 오류 기록
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

  const 딱지 = (v) => {
    const s = document.createElement('span');
    const 반 = { '미확인': 'el-open', '확인': 'el-check', '조치 완료': 'el-fixed', '보류': 'el-ign' };
    s.className = 'el-chip ' + (반[v] || 'el-ign');
    s.textContent = v;
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

  /* 줄을 겹누르면 온 내용을 편다 */
  document.getElementById('elGrid').addEventListener('dblclick', (e) => {
    const 줄 = e.target.closest('[data-row-index]');
    if (!줄) return;
    const r = ROWS[Number(줄.dataset.rowIndex)];
    if (r) elOpen(r.id);
  });

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

  window.elPurge = async function () {
    if (!confirm('180일보다 오래된 기록을 삭제합니다. 되돌릴 수 없습니다.')) return;
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
