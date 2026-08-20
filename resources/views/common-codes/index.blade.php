@extends('layouts.app')

@section('title', '환경 설정')
@section('page-title', '환경 설정')
@section('breadcrumb', '홈 / 설정 / 환경 설정')

@push('styles')
<style>
  /* 코드 목록은 마스터 관리와 같은 얼개다 — 탭으로 목록을 고르고, 표에서 고쳐 쓴다 */
  .cc-kind-chips { display:flex; gap:6px; flex-wrap:wrap; }
  .cc-kind-chip { display:inline-flex; align-items:center; gap:4px; height:24px; padding:0 8px;
                  border-radius:999px; background:var(--gray-100); color:var(--gray-700);
                  font-size:11.5px; font-weight:600; }
  .cc-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 12px; }
  .cc-form-grid .wide { grid-column:1 / -1; }
  .cc-field label { display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:4px; }
  .cc-tools { margin-left:auto; display:flex; align-items:center; gap:6px; }
  .cc-tool { width:28px; height:28px; border-radius:8px; border:1px solid var(--gray-200);
             background:var(--gray-0); color:var(--gray-700); font-size:16px; line-height:1;
             cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
  .cc-tool:hover { border-color:var(--primary); color:var(--primary); }
  .cc-dirty { font-size:12px; font-weight:600; color:var(--primary); min-width:60px; text-align:right; }
</style>
@endpush

@section('content')

{{-- 목록 고르기 — 마스터 관리와 같은 자리, 같은 모양 --}}
<form method="GET" action="{{ route('common-codes.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      <label class="ds-field-label">코드 목록</label>
      <select name="group" class="form-control form-select" onchange="this.form.submit()">
        @foreach($groups as $key => $g)
          <option value="{{ $key }}" @selected($key === $current)>
            {{ $g['label'] }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }}) @endif
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    <button type="button" class="ds-btn" onclick="window.__ccGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      @foreach($groups as $key => $g)
        <a href="{{ route('common-codes.index', ['group' => $key]) }}"
           class="pnl-tab {{ $key === $current ? 'active' : '' }}"
           style="display:inline-flex;align-items:center;text-decoration:none;">
          <i class="fa-solid fa-list"></i> {{ $g['label'] }}
          @if(($counts[$key] ?? 0) > 0)
            <span class="pnl-tab-cnt">(총 {{ $counts[$key] }}건)</span>
          @endif
        </a>
      @endforeach
      {{-- 줄을 더하고 지우는 자리는 표 바로 위 오른쪽이다. 고친 것은 저장을 눌러야 남는다. --}}
      <span class="cc-tools">
        @perm('common-codes', 'delete')
        <button type="button" class="cc-tool" onclick="ccRemove()" title="고른 줄 사용 중지">−</button>
        @endperm
        @perm('common-codes', 'create')
        <button type="button" class="cc-tool" onclick="ccAdd()" title="줄 추가">+</button>
        @endperm
        <span class="cc-dirty" id="ccDirty"></span>
        @perm('common-codes', 'update')
        <button type="button" class="ds-btn ds-btn-primary" id="ccSaveBtn" onclick="ccSave(this)">저장</button>
        @endperm
      </span>
    </div>

    <div id="pnlList" style="padding:16px;">
      <div id="ccGrid"></div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const ROWS  = @json($gridData);
  const GROUP = @json($current);
  const BASE  = @json(url('settings/common-codes'));

  const KINDS = @json($kinds);
  const KIND_OPTS = Object.entries(KINDS).map(([value, label]) => ({ value, label }));

  const grid = new wwGrid({
    el: document.getElementById('ccGrid'),
    height: 'auto', editable: true, rowCheckbox: true, rowNumber: true,
    toolbar: false, footer: false,
    columns: [
      { header: '유형',   name: 'kind_key',   width: 120, editor: 'combo', options: KIND_OPTS,
        defaultValue: KIND_OPTS[0]?.value ?? '' },
      { header: '코드',   name: 'code',       width: 180 },
      { header: '이름',   name: 'label',      width: 260 },
      { header: '차례',   name: 'sort_order', width: 80,  editor: 'number', align: 'right', defaultValue: 0 },
      { header: '사용',   name: 'is_active',  width: 80,  editor: 'checkbox', align: 'center', defaultValue: true },
      { header: '구분',   name: 'system',     width: 80,  align: 'center', editable: false },
      { header: '메모',   name: 'note',       width: 260 },
    ],
    data: ROWS,
  });
  window.__ccGrid = grid;

  /* 무엇을 손댔는지 알려 준다 — 저장을 누르지 않고 나가면 그대로 사라진다 */
  const removed = [];
  function ccMark() {
    const m = grid.getModifiedRows();
    const n = (m.added?.length ?? 0) + (m.updated?.length ?? 0) + removed.length;
    document.getElementById('ccDirty').textContent = n ? `고친 줄 ${n}` : '';
  }
  document.getElementById('ccGrid').addEventListener('change', ccMark);
  document.getElementById('ccGrid').addEventListener('blur', ccMark, true);

  window.ccAdd = function () {
    grid.addRow({ kind_key: KIND_OPTS[0]?.value ?? '', is_active: true, sort_order: 0, system: '' });
    ccMark();
  };

  window.ccRemove = function () {
    const rows = grid.getCheckedRows();
    if (!rows.length) { showToast('사용 중지할 줄을 고르십시오.', 'warning'); return; }
    const sys = rows.filter(r => r.is_system);
    if (sys.length) showToast('시스템 코드는 둡니다 — ' + sys.map(r => r.label).join(', '), 'warning', 5000);
    rows.filter(r => r.id && !r.is_system).forEach(r => removed.push(r.id));
    grid.removeCheckedRows();
    ccMark();
  };

  window.ccSave = async function (btn) {
    const data = grid.getData();
    const rows = data
      .filter(r => (r.code || '').trim() && (r.label || '').trim())
      .map(r => ({
        id:         r.id ?? null,
        kind:       r.kind_key,
        code:       (r.code || '').trim(),
        label:      (r.label || '').trim(),
        note:       (r.note || '').trim() || null,
        sort_order: parseInt(r.sort_order) || 0,
        is_active:  !!r.is_active,
      }));

    const empty = data.length - rows.length;
    if (empty > 0) showToast(`코드·이름이 비어 있는 ${empty}줄은 저장하지 않습니다.`, 'warning');
    if (!rows.length && !removed.length) { showToast('저장할 것이 없습니다.', 'warning'); return; }

    BtnState.loading(btn, '저장 중...');
    try {
      const res = await apiRequest(`${BASE}/bulk`, 'POST', { group: GROUP, rows, removed });
      if (!res.success) throw new Error(res.message || '저장하지 못했습니다.');
      showToast(res.message, 'success', 5000);
      setTimeout(() => location.reload(), 800);
    } catch (e) {
      BtnState.reset(btn);
      showToast(e.message || '저장하지 못했습니다.', 'danger', 6000);
    }
  };

  // 적다 만 채로 나가면 사라진다 — 나가기 전에 물어본다
  window.addEventListener('beforeunload', (e) => {
    const m = grid.getModifiedRows();
    if ((m.added?.length ?? 0) + (m.updated?.length ?? 0) + removed.length === 0) return;
    e.preventDefault();
    e.returnValue = '';
  });

})();
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.pnl-tabs', title: '코드 목록', body: '화면마다 고르는 목록을 여기서 고릅니다. 지금은 <b>서류 유형</b> 하나입니다.' },
  { selector: '.cc-tools', title: '줄 더하기·지우기', body: '<b>+</b> 로 줄을 더하고, 줄을 고른 뒤 <b>−</b> 로 사용 중지합니다. 고친 것은 <b>저장</b>을 눌러야 남습니다.' },
  { selector: '#ccGrid', title: '표에서 고치기', body: '칸을 눌러 바로 고칩니다. 시스템 코드는 이름·차례만 바뀝니다.' },
];
</script>
@endpush
