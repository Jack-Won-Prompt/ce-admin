{{-- resources/views/permission-groups/index.blade.php --}}
@extends('layouts.app')

@section('title', '권한 그룹')
@section('page-title', '권한 그룹')
@section('breadcrumb', '홈 - 설정 - 권한 그룹')

@push('styles')
<style>
  /* 패널 탭(그룹 목록 / 권한 편집) — 다른 화면과 동일 패턴 */

  .pg-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; color:var(--text-secondary); margin-bottom:16px; line-height:18px; }
  .pg-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
  /* 입력 h32 = pad 5 + lh 20 + pad 5 + 테두리 2 */
  .pg-head input[type=text] { height:32px; padding:0 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit; }
  .pg-head #pgName { width:200px; font-weight:700; }
  .pg-head #pgDesc { flex:1; min-width:220px; }
  /* 읽기 전용 안내 배지 — 주의는 alert 램프로만 표현한다(시안에 주황이 없다) */
  .pg-lock { display:inline-flex; align-items:center; font-size:11px; font-weight:500; line-height:18px; padding:2px 6px; border-radius:6px;
    background:var(--alert-50); color:var(--alert-500); }

  /* 권한 매트릭스 */
  .pg-sec { background:var(--gray-0); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:12px; overflow:hidden; }
  .pg-sec > h4 { margin:0; padding:11px 16px; font-size:13px; font-weight:700; line-height:21px; color:var(--primary);
    background:var(--primary-light); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .pg-tbl { width:100%; border-collapse:collapse; font-size:12px; }
  .pg-tbl th, .pg-tbl td { padding:8px 10px; border-bottom:1px solid var(--border-light); text-align:center; }
  .pg-tbl th { font-size:11px; font-weight:700; line-height:18px; color:var(--text-muted); background:var(--bg); white-space:nowrap; }
  .pg-tbl th:first-child, .pg-tbl td:first-child { text-align:left; width:38%; }
  .pg-tbl tr:last-child td { border-bottom:none; }
  .pg-tbl td.na { color:var(--gray-300); }
  .pg-tbl input[type=checkbox] { width:16px; height:16px; cursor:pointer; }
  .pg-rowall { font-size:10px; font-weight:500; line-height:16px; color:var(--primary); background:none; border:none; cursor:pointer; padding:0 0 0 6px; font-family:inherit; }
  .pg-actions { display:flex; gap:8px; align-items:center; margin-top:4px; }
  .pg-actions .spacer { margin-left:auto; }
</style>
@endpush

@section('content')

<div class="pnl-tabs">
  <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">
    <i class="fa-solid fa-layer-group"></i> 그룹 목록
  </button>
  <button type="button" id="pnlBtnEdit" class="pnl-tab" onclick="pnlShow('edit')">
    <i class="fa-solid fa-sliders"></i> 권한 편집
    <span id="pnlEditName" style="font-size:11px;color:var(--text-muted);font-weight:500;"></span>
  </button>
</div>

{{-- ══ 그룹 목록 ══ --}}
<div id="pnlList">
  <div class="pg-note">
    <i class="bx bx-info-circle"></i>
    권한 그룹을 만들어 사용자에게 부여하면, 그룹에 허용한 <b>페이지만 메뉴에 보이고</b>
    허용한 <b>동작(조회·등록·수정·삭제·발송)만 버튼으로 노출</b>됩니다.
    사용자별 부여는 <b>설정 &gt; 관리자 관리</b>에서 합니다.<br>
    <b>관리자(admin) 역할은 그룹과 무관하게 항상 전체 권한</b>을 가집니다 —
    권한 설정 실수로 시스템에서 잠기는 일을 막기 위한 안전장치입니다.
  </div>

  <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;flex-wrap:wrap;">
    <button type="button" class="btn btn-primary btn-sm" onclick="openCreate()">
      <i class="bx bx-plus"></i> 그룹 추가
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="editSelected()">
      <i class="bx bx-sliders"></i> 선택 권한 편집
    </button>
    <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"
            onclick="deleteSelected()">
      <i class="bx bx-trash"></i> 선택 삭제
    </button>
    <span style="font-size:12px;color:var(--text-muted);">
      <i class="bx bx-info-circle"></i> 행을 <b>클릭</b>하면 권한 편집 탭이 열립니다.
    </span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}개</span>
  </div>
  <div id="pgGrid"></div>
</div>

{{-- ══ 권한 편집 ══ --}}
<div id="pnlEdit" style="display:none;">
  <div id="editEmpty" class="pnl-empty">
    <i class="bx bx-hand-pointer" style="font-size:16px;opacity:.35;display:block;margin-bottom:8px;"></i>
    그룹 목록에서 그룹을 <b>클릭</b>하면 권한 매트릭스가 여기에 표시됩니다.
  </div>

  <div id="editBody" style="display:none;">
    <div class="pg-head">
      <input type="text" id="pgName" placeholder="그룹명" maxlength="60">
      <input type="text" id="pgDesc" placeholder="설명 (선택)" maxlength="200">
      <span id="pgLock" class="pg-lock" style="display:none;">기본 그룹 — 읽기 전용</span>
      <span id="pgUsers" style="font-size:12px;color:var(--text-muted);"></span>
    </div>

    <div id="pgMatrix"></div>

    <div class="pg-actions">
      <button type="button" class="btn btn-outline btn-sm" onclick="pnlShow('list')">
        <i class="bx bx-arrow-back"></i> 목록으로
      </button>
      <span class="spacer"></span>
      <button type="button" class="btn btn-outline btn-sm" onclick="checkAll(true)">전체 허용</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="checkAll(false)">전체 해제</button>
      <button type="button" class="btn btn-primary btn-sm" id="pgSaveBtn" onclick="saveMatrix()">
        <i class="bx bx-save"></i> 저장
      </button>
    </div>
  </div>
</div>

{{-- 그룹 추가 모달 --}}
<div class="modal-overlay" id="createModal" style="z-index:10000;" onclick="if(event.target===this)closeCreate()">
  <div class="modal-box sm">
    <div class="modal-hd">
      <i class="fa-solid fa-layer-group" style="color:var(--primary);"></i>
      <span class="modal-title">권한 그룹 추가</span>
      <button class="modal-close" onclick="closeCreate()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-bd">
      <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:12px;">
        <label style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);">그룹명 <span style="color:var(--danger);">*</span></label>
        <input type="text" id="newName" maxlength="60" placeholder="예: 검수 담당"
               style="height:32px;padding:0 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:400;line-height:20px;font-family:inherit;">
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);">설명</label>
        <input type="text" id="newDesc" maxlength="200" placeholder="이 그룹의 역할을 적어 두세요"
               style="height:32px;padding:0 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:400;line-height:20px;font-family:inherit;">
      </div>
      <div style="font-size:12px;font-weight:400;color:var(--text-muted);margin-top:12px;line-height:18px;">
        만든 뒤 권한 편집 탭에서 페이지별 동작을 지정하세요. 처음에는 아무 권한도 없는 상태로 생성됩니다.
      </div>
    </div>
    <div class="modal-ft">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeCreate()">취소</button>
      <button type="button" class="btn btn-primary btn-sm" id="createBtn" onclick="createGroup()">
        <i class="bx bx-plus"></i> 추가
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const PAGE_DEFS   = @json($pageDefs);
  const MENU_GROUPS = @json($menuGroups);
  const ACTION_DEFS = @json($actionDefs);
  const BASE        = @json(url('settings/permission-groups'));
  const CSRF        = document.querySelector('meta[name=csrf-token]')?.content ?? '';

  let _cur = null;    // 편집 중인 그룹 {id, locked, ...}

  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  /* ── 그룹 목록 그리드 (wwGrid) ─────────────────────────── */
  const grid = new wwGrid({
    el: document.getElementById('pgGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '그룹명',   name: 'name',        width: 200, sortable: true },
      { header: '설명',     name: 'description', width: 320 },
      { header: '소속 인원', name: 'users',      width: 90,  align: 'right', sortable: true },
      { header: '구분',     name: 'locked',      width: 100, align: 'center' },
      { header: '수정일',   name: 'updated',     width: 140, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  window.__pgGrid = grid;

  /* ── 패널 탭 ──────────────────────────────────────────── */
  window.pnlShow = function (which) {
    const edit = which === 'edit';
    document.getElementById('pnlList').style.display = edit ? 'none' : '';
    document.getElementById('pnlEdit').style.display = edit ? '' : 'none';
    document.getElementById('pnlBtnList').classList.toggle('active', !edit);
    document.getElementById('pnlBtnEdit').classList.toggle('active', edit);
  };

  /* 행 클릭 → 권한 편집 */
  document.getElementById('pgGrid').addEventListener('click', function (e) {
    if (e.target.closest('input, button, a, select, textarea')) return;
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) loadGroup(row.id);
  });

  function pickOne(label) {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast(label + '할 그룹을 체크하세요.', 'warning'); return null; }
    if (c.length > 1) { showToast('한 개만 선택하세요.', 'warning'); return null; }
    return c[0];
  }
  window.editSelected   = function () { const r = pickOne('편집'); if (r) loadGroup(r.id); };
  window.deleteSelected = async function () {
    const r = pickOne('삭제');
    if (!r) return;
    if (!await ceConfirm(`'${r.name}' 그룹을 삭제하시겠습니까?`, { tone: 'danger', confirmText: '삭제' })) return;
    try {
      const res = await fetch(`${BASE}/${r.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      });
      const d = await res.json();
      if (!d.success) { ceAlert(d.message || '삭제하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      setTimeout(() => location.reload(), 700);
    } catch (e) { ceAlert('삭제 중 오류가 발생했습니다.', { tone: 'danger' }); }
  };

  /* ── 그룹 추가 ────────────────────────────────────────── */
  window.openCreate  = function () { document.getElementById('createModal').classList.add('open');
                                     document.getElementById('newName').focus(); };
  window.closeCreate = function () { document.getElementById('createModal').classList.remove('open'); };

  window.createGroup = async function () {
    const name = document.getElementById('newName').value.trim();
    if (!name) { ceAlert('그룹명을 입력해 주세요.', { tone: 'warning' }); return; }
    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    try {
      const res = await fetch(BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, description: document.getElementById('newDesc').value.trim() }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) {
        ceAlert(d.message || Object.values(d.errors ?? {}).flat().join('\n') || '추가하지 못했습니다.', { tone: 'danger' });
        return;
      }
      showToast(d.message, 'success');
      closeCreate();
      setTimeout(() => location.reload(), 700);
    } catch (e) {
      ceAlert('추가 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };

  /* ── 권한 편집 ────────────────────────────────────────── */
  async function loadGroup(id) {
    pnlShow('edit');
    document.getElementById('editEmpty').style.display = 'none';
    document.getElementById('editBody').style.display  = '';
    document.getElementById('pgMatrix').innerHTML =
      '<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;line-height:21px;">'
      + '<i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';

    try {
      const res = await fetch(`${BASE}/${id}`, { headers: { 'Accept': 'application/json' } });
      const d   = await res.json();
      if (!d.success) throw new Error();

      _cur = d.group;
      document.getElementById('pgName').value = d.group.name;
      document.getElementById('pgDesc').value = d.group.description;
      document.getElementById('pnlEditName').textContent = d.group.name;
      document.getElementById('pgUsers').textContent = `소속 ${d.group.users_count}명`;

      const locked = d.group.locked;
      document.getElementById('pgLock').style.display = locked ? '' : 'none';
      document.getElementById('pgName').disabled = locked;
      document.getElementById('pgDesc').disabled = locked;
      document.getElementById('pgSaveBtn').disabled = locked;

      renderMatrix(d.matrix, locked);
    } catch (e) {
      document.getElementById('pgMatrix').innerHTML =
        '<div style="padding:24px;text-align:center;color:var(--danger);font-size:13px;line-height:21px;">권한을 불러오지 못했습니다.</div>';
    }
  }

  /* 메뉴 그룹별 섹션 + 페이지 × 액션 체크박스 */
  function renderMatrix(matrix, locked) {
    const actionKeys = Object.keys(ACTION_DEFS);
    let html = '';

    for (const [gKey, gLabel] of Object.entries(MENU_GROUPS)) {
      const pages = Object.entries(PAGE_DEFS).filter(([, def]) => def.group === gKey && !def.admin_only);
      if (!pages.length) continue;

      html += `<div class="pg-sec"><h4><i class="bx bx-folder"></i> ${esc(gLabel)}</h4>
        <table class="pg-tbl"><thead><tr><th>페이지</th>`;
      actionKeys.forEach(a => {
        html += `<th>${esc(ACTION_DEFS[a])}<br>
          <button type="button" class="pg-rowall" data-col="${a}"${locked ? ' disabled' : ''}>전체</button></th>`;
      });
      html += '</tr></thead><tbody>';

      pages.forEach(([pKey, def]) => {
        const cur = matrix[pKey] || {};
        html += `<tr><td>${esc(def.label)}
          <button type="button" class="pg-rowall" data-row="${pKey}"${locked ? ' disabled' : ''}>전체</button></td>`;
        actionKeys.forEach(a => {
          if (!(def.actions || []).includes(a)) {
            html += '<td class="na">—</td>';
          } else {
            const on = locked ? true : !!cur[a];
            html += `<td><input type="checkbox" data-page="${pKey}" data-action="${a}"
                      ${on ? 'checked' : ''} ${locked ? 'disabled' : ''}></td>`;
          }
        });
        html += '</tr>';
      });
      html += '</tbody></table></div>';
    }

    const el = document.getElementById('pgMatrix');
    el.innerHTML = html;

    // 행/열 전체선택
    el.querySelectorAll('.pg-rowall').forEach(function (b) {
      b.addEventListener('click', function () {
        const row = b.dataset.row, col = b.dataset.col;
        const sel = row ? `input[data-page="${row}"]` : `input[data-action="${col}"]`;
        const boxes = Array.from(el.querySelectorAll(sel)).filter(x => !x.disabled);
        const allOn = boxes.every(x => x.checked);
        boxes.forEach(x => { x.checked = !allOn; });
      });
    });
  }

  window.checkAll = function (on) {
    if (_cur?.locked) return;
    document.querySelectorAll('#pgMatrix input[type=checkbox]').forEach(x => { if (!x.disabled) x.checked = on; });
  };

  window.saveMatrix = async function () {
    if (!_cur) return;
    if (_cur.locked) { ceAlert('기본 제공 그룹은 수정할 수 없습니다.', { tone: 'warning' }); return; }

    const name = document.getElementById('pgName').value.trim();
    if (!name) { ceAlert('그룹명을 입력해 주세요.', { tone: 'warning' }); return; }

    // { 페이지키: [액션, ...] }
    const matrix = {};
    document.querySelectorAll('#pgMatrix input[type=checkbox]:checked').forEach(function (x) {
      (matrix[x.dataset.page] ||= []).push(x.dataset.action);
    });

    const btn = document.getElementById('pgSaveBtn');
    btn.disabled = true;
    try {
      const res = await fetch(`${BASE}/${_cur.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ name, description: document.getElementById('pgDesc').value.trim(), matrix }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) {
        ceAlert(d.message || Object.values(d.errors ?? {}).flat().join('\n') || '저장하지 못했습니다.', { tone: 'danger' });
        return;
      }
      showToast(d.message, 'success');
      document.getElementById('pnlEditName').textContent = name;
    } catch (e) {
      ceAlert('저장 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };
})();
</script>
@endpush
