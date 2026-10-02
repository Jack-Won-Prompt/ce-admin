@extends('layouts.app')

@section('title', '병원 관리')
@section('page-title', '병원 관리')
@section('breadcrumb', '홈 - 설정 - 병원 관리')

@section('content')

{{-- 병원(요양기관)을 보고 고치는 자리 (2026-10-02 지시).

     여태 이 표를 보는 화면이 없었다. 주문 등록에서 조회해 고르고 없으면 만들기만 했고,
     **고치는 길이 아예 없었다** — 이름과 요양기관번호가 어긋나도 손댈 수 없었고, 그
     번호로 다른 병원을 등록하려 하면 「이미 같은 요양기관번호를 쓰는 병원이 있습니다」
     로 막혔다.

     ## 겹친 번호를 먼저 보인다

     운영에 같은 요양기관번호를 두 곳 이상이 쓰는 경우가 24건이다. 겹치면 **청구가 남의
     병원으로 간다** — 가장 먼저 정리해야 할 자리라 거르개를 맨 앞에 둔다. --}}

@push('styles')
<style>
  .hp-chip  { display:inline-flex; align-items:center; height:22px; padding:0 9px; border-radius:999px;
              font-size:11px; font-weight:700; }
  .hp-dup   { background:var(--danger-light); color:var(--danger); }
  .hp-off   { background:var(--gray-100);     color:var(--text-muted); }
  .hp-on    { background:var(--primary-50);   color:var(--primary); }

  /* 고치는 창 */
  .hp-back  { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1190; }
  .hp-modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
              width:620px; max-width:96vw; max-height:92vh; overflow:auto; background:var(--bg-card);
              border:1px solid var(--primary); border-radius:var(--radius-lg);
              box-shadow:0 12px 40px rgba(0,0,0,.22); z-index:1191; }
  .hp-head  { background:var(--primary); border-radius:var(--radius-lg) var(--radius-lg) 0 0;
              padding:10px 14px; display:flex; align-items:center; gap:8px; position:sticky; top:0; z-index:2; }
  .hp-head span { font-size:13px; font-weight:700; color:#fff; flex:1; }
  .hp-head button { border:none; background:none; color:#fff; font-size:16px; line-height:1; cursor:pointer; }
  .hp-body  { padding:14px; display:grid; grid-template-columns:120px 1fr; gap:10px 12px; align-items:center; }
  .hp-body label { font-size:12px; font-weight:700; color:var(--text-muted); }
  .hp-body .wide { grid-column:1/-1; }
  .hp-foot  { padding:12px 14px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
  .hp-note  { grid-column:1/-1; font-size:11.5px; color:var(--text-muted); background:var(--gray-50);
              border:1px solid var(--gray-200); border-radius:8px; padding:9px 11px; line-height:1.7; }
</style>
@endpush

<form method="GET" action="{{ route('hospitals.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">병원명ㆍ요양기관번호</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control"
             placeholder="이름이나 번호의 일부를 적으십시오">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">거르개</label>
      <select name="dup" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 {{ number_format($total) }}곳</option>
        <option value="1" @selected($dupOnly)>번호가 겹친 것만 ({{ number_format($dupCount) }}개 번호)</option>
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('hospitals.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <span class="pnl-tab active">
        <i class="fa-solid fa-hospital"></i> 병원
        <span class="pnl-tab-cnt">(총 {{ number_format($total) }}곳)</span>
      </span>
      <span style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        @if($dupCount)
          <span class="hp-chip hp-dup">번호 겹침 {{ number_format($dupCount) }}개</span>
        @endif
        <button type="button" class="ds-btn" onclick="window.__hpGrid?.downloadExcel()">엑셀 다운</button>
      </span>
    </div>
    <div style="padding:16px;">
      <div id="hpGrid"></div>
      @unless($canEdit)
        <div style="margin-top:8px;font-size:11.5px;color:var(--text-muted);">
          고치려면 「병원 관리」 수정 권한이 필요합니다. 지금은 보기만 됩니다.
        </div>
      @endunless
    </div>
  </div>
</div>

{{-- 고치는 창 --}}
<div id="hpBack" class="hp-back" onclick="hpClose()"></div>
<div id="hpModal" class="hp-modal">
  <div class="hp-head">
    <i class="fa-solid fa-hospital" style="color:#fff;font-size:14px;"></i>
    <span id="hpTitle">병원 수정</span>
    <button type="button" onclick="hpClose()">&#215;</button>
  </div>
  <div class="hp-body">
    <label>병원명</label>
    <input type="text" id="hpName" class="form-control" maxlength="120">

    <label>요양기관번호</label>
    <input type="text" id="hpCode" class="form-control" maxlength="20" placeholder="여덟 자리 · 모르면 비워 두십시오">

    <label>진료과</label>
    <input type="text" id="hpDept" class="form-control" maxlength="60">

    <label>전화</label>
    <input type="text" id="hpTel" class="form-control" maxlength="30">

    <label>팩스</label>
    <input type="text" id="hpFax" class="form-control" maxlength="30">

    <label>주소</label>
    <input type="text" id="hpAddr" class="form-control" maxlength="255">

    <label>메모</label>
    <input type="text" id="hpMemo" class="form-control" maxlength="255">

    <label>사용</label>
    <div><label style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:500;color:var(--text-main);">
      <input type="checkbox" id="hpActive" style="accent-color:var(--primary);"> 목록과 조회에 보인다
    </label></div>

    <div class="hp-note" id="hpDupNote" style="display:none;"></div>
  </div>
  <div class="hp-foot">
    <button type="button" class="ds-btn" onclick="hpClose()">닫기</button>
    <button type="button" class="ds-btn ds-btn-primary" id="hpSave" onclick="hpSave()">저장</button>
  </div>
</div>

{{-- 합치는 창 --}}
<div id="hpMBack" class="hp-back" onclick="hpMergeClose()"></div>
<div id="hpMModal" class="hp-modal" style="width:560px;">
  <div class="hp-head">
    <i class="fa-solid fa-code-merge" style="color:#fff;font-size:14px;"></i>
    <span>겹친 병원 합치기</span>
    <button type="button" onclick="hpMergeClose()">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
    <div class="hp-note" style="grid-column:auto;">
      같은 요양기관번호를 쓰는 병원을 하나로 모읍니다.<br>
      <strong>남길 쪽</strong>의 이름과 번호로 처방전을 옮겨 적고,
      <strong>버릴 쪽</strong>은 지우지 않고 「사용 안 함」으로 둡니다 —
      지우면 그 줄을 보고 적어 둔 지난 자취를 되짚을 수 없습니다.
    </div>
    <div>
      <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:4px;">남길 병원</div>
      <select id="hpKeep" class="form-control form-select"></select>
    </div>
    <div>
      <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:4px;">버릴 병원</div>
      <select id="hpDrop" class="form-control form-select"></select>
    </div>
  </div>
  <div class="hp-foot">
    <button type="button" class="ds-btn" onclick="hpMergeClose()">닫기</button>
    <button type="button" class="ds-btn ds-btn-primary" id="hpMergeBtn" onclick="hpMergeRun()">합치기</button>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const ROWS     = @json($rows);
  const CAN_EDIT = @json($canEdit);
  const CSRF     = document.querySelector('meta[name=csrf-token]')?.content;

  /* 번호가 겹치는 줄은 한눈에 — 겹치면 청구가 남의 병원으로 간다 */
  const 겹침칸 = (v) => {
    const s = document.createElement('span');
    if (!v) { s.textContent = ''; return s; }
    s.className = 'hp-chip hp-dup';
    s.textContent = v;
    return s;
  };

  const 쓰임칸 = (v) => {
    const s = document.createElement('span');
    s.textContent = Number(v || 0).toLocaleString('ko-KR');
    if (!Number(v)) { s.style.color = 'var(--text-muted)'; }
    return s;
  };

  const 사용칸 = (v) => {
    const s = document.createElement('span');
    s.className = 'hp-chip ' + (v === '사용' ? 'hp-on' : 'hp-off');
    s.textContent = v;
    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('hpGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '요양기관번호', name: 'code',   width: 120, align: 'center', sortable: true },
      { header: '겹침',        name: 'dup',    width: 70,  align: 'center', sortable: true, renderer: 겹침칸 },
      { header: '병원명',      name: 'name',   width: 260, sortable: true },
      { header: '진료과',      name: 'department', width: 110, sortable: true },
      { header: '전화',        name: 'tel',    width: 120, sortable: true },
      { header: '팩스',        name: 'fax',    width: 120, sortable: true },
      { header: '주소',        name: 'address', width: 260, sortable: true },
      { header: '처방전',      name: 'used',   width: 90,  align: 'right', sortable: true, renderer: 쓰임칸 },
      { header: '사용',        name: 'active', width: 90,  align: 'center', sortable: true, renderer: 사용칸 },
      { header: '메모',        name: 'memo',   width: 200, sortable: true },
    ],
    data: ROWS,
  });
  window.__hpGrid = grid;
  cePager.붙이기(grid);

  /* 줄을 겹누르면 고치는 창을 편다 */
  document.getElementById('hpGrid').addEventListener('dblclick', (e) => {
    const 칸 = e.target.closest('[data-row-index]');
    if (!칸) return;
    const r = ROWS[Number(칸.dataset.rowIndex)];
    if (r) hpOpen(r.id);
  });

  const 찾기 = (id) => ROWS.find(r => r.id === id);
  let 지금줄 = null;

  window.hpOpen = function (id) {
    const r = 찾기(id);
    if (!r) return;
    지금줄 = r;

    document.getElementById('hpTitle').textContent = '병원 수정 — ' + r.name;
    document.getElementById('hpName').value = r.name;
    document.getElementById('hpCode').value = r.code;
    document.getElementById('hpDept').value = r.department;
    document.getElementById('hpTel').value  = r.tel;
    document.getElementById('hpFax').value  = r.fax;
    document.getElementById('hpAddr').value = r.address;
    document.getElementById('hpMemo').value = r.memo;
    document.getElementById('hpActive').checked = (r.active === '사용');

    /* 같은 번호를 쓰는 다른 병원이 있으면 그 자리에서 알린다 */
    const 같은번호 = r.code ? ROWS.filter(x => x.code === r.code && x.id !== r.id) : [];
    const 쪽지 = document.getElementById('hpDupNote');
    if (같은번호.length) {
      쪽지.style.display = '';
      쪽지.innerHTML = '같은 요양기관번호 <strong>' + 안전(r.code) + '</strong> 를 쓰는 병원이 '
        + 같은번호.length + '곳 더 있습니다 — '
        + 같은번호.map(x => 안전(x.name) + '(처방전 ' + Number(x.used).toLocaleString('ko-KR') + '건)').join(' · ')
        + '<br>겹치면 청구가 남의 병원으로 갑니다. 같은 곳이면 '
        + '<button type="button" class="ds-btn" style="height:24px;padding:0 8px;font-size:11px;" '
        + 'onclick="hpMergeOpen(\'' + 안전(r.code) + '\')">합치기</button> 로 모으십시오.';
    } else {
      쪽지.style.display = 'none';
    }

    const 저장 = document.getElementById('hpSave');
    저장.style.display = CAN_EDIT ? '' : 'none';
    ['hpName','hpCode','hpDept','hpTel','hpFax','hpAddr','hpMemo','hpActive']
      .forEach(k => document.getElementById(k).disabled = !CAN_EDIT);

    document.getElementById('hpBack').style.display  = 'block';
    document.getElementById('hpModal').style.display = 'block';
  };

  window.hpClose = function () {
    document.getElementById('hpBack').style.display  = 'none';
    document.getElementById('hpModal').style.display = 'none';
  };

  window.hpSave = async function () {
    if (!지금줄) return;
    const 단추 = document.getElementById('hpSave');
    단추.disabled = true;

    try {
      const res = await fetch('/hospitals/' + 지금줄.id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          name:       document.getElementById('hpName').value.trim(),
          code:       document.getElementById('hpCode').value.trim(),
          department: document.getElementById('hpDept').value.trim(),
          tel:        document.getElementById('hpTel').value.trim(),
          fax:        document.getElementById('hpFax').value.trim(),
          address:    document.getElementById('hpAddr').value.trim(),
          memo:       document.getElementById('hpMemo').value.trim(),
          is_active:  document.getElementById('hpActive').checked,
        }),
      });

      const d = await res.json().catch(() => ({}));

      if (!res.ok) {
        /* 번호가 겹치면 서버가 그 까닭을 적어 보낸다 — 그대로 보인다 */
        const 말 = d.message || (d.errors && Object.values(d.errors).flat().join(' · ')) || '고치지 못했습니다.';
        showToast(말, 'danger', 4000);
        return;
      }

      showToast(d.message || '고쳤습니다.', 'success');
      hpClose();
      location.reload();
    } catch (e) {
      showToast('고치지 못했습니다 — 잠시 뒤 다시 시도해 주십시오.', 'danger');
    } finally {
      단추.disabled = false;
    }
  };

  /* ── 합치기 ───────────────────────────────────────────────── */

  window.hpMergeOpen = function (code) {
    const 같은것 = ROWS.filter(r => r.code === code);
    if (같은것.length < 2) { showToast('같은 번호를 쓰는 병원이 둘 이상이어야 합니다.', 'warning'); return; }

    const 고르개 = (el, 목록) => {
      el.innerHTML = 목록.map(r =>
        '<option value="' + r.id + '">' + 안전(r.name)
        + ' (처방전 ' + Number(r.used).toLocaleString('ko-KR') + '건)</option>').join('');
    };

    /* 처방전이 많은 쪽을 남길 쪽의 처음 값으로 둔다 — 옮길 글자가 적다 */
    const 많은순 = 같은것.slice().sort((a, b) => Number(b.used) - Number(a.used));
    고르개(document.getElementById('hpKeep'), 많은순);
    고르개(document.getElementById('hpDrop'), 많은순.slice().reverse());

    hpClose();
    document.getElementById('hpMBack').style.display  = 'block';
    document.getElementById('hpMModal').style.display = 'block';
  };

  window.hpMergeClose = function () {
    document.getElementById('hpMBack').style.display  = 'none';
    document.getElementById('hpMModal').style.display = 'none';
  };

  window.hpMergeRun = async function () {
    const keep = Number(document.getElementById('hpKeep').value);
    const drop = Number(document.getElementById('hpDrop').value);

    if (!keep || !drop || keep === drop) {
      showToast('남길 병원과 버릴 병원을 다르게 고르십시오.', 'warning');
      return;
    }

    const 남 = 찾기(keep), 버 = 찾기(drop);
    const 물음 = '「' + (버?.name ?? '') + '」 을 「' + (남?.name ?? '') + '」 으로 합칩니다.\n'
      + '처방전에 적힌 이름과 번호가 남길 쪽으로 바뀝니다. 되돌리려면 손으로 고쳐야 합니다.';

    if (!await ceConfirm(물음, { tone: 'danger' })) return;

    const 단추 = document.getElementById('hpMergeBtn');
    단추.disabled = true;

    try {
      const res = await fetch('/hospitals/merge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ keep, drop }),
      });
      const d = await res.json().catch(() => ({}));

      if (!res.ok) {
        showToast(d.message || '합치지 못했습니다.', 'danger', 4000);
        return;
      }

      showToast(d.message || '합쳤습니다.', 'success', 4000);
      hpMergeClose();
      location.reload();
    } catch (e) {
      showToast('합치지 못했습니다 — 잠시 뒤 다시 시도해 주십시오.', 'danger');
    } finally {
      단추.disabled = false;
    }
  };

  const 안전 = (v) => String(v ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { hpClose(); hpMergeClose(); }
  });
})();
</script>
@endpush

@endsection
