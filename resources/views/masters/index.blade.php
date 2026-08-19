{{-- resources/views/masters/index.blade.php --}}
@extends('layouts.app')

@section('title', '마스터 관리')
@section('page-title', '마스터 관리')
@section('breadcrumb', '홈 - 설정 - 마스터 관리')

@push('styles')
<style>
  /* 카테고리 탭 — 화면 하나에 카테고리가 여럿이다 */
  .ms-cat { display: flex; gap: 8px; flex-wrap: wrap; }
  .ms-cat a {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border: 1px solid var(--gray-200); border-radius: 8px;
    background: var(--gray-0); font-size: 13px; font-weight: 500; color: var(--gray-700);
    text-decoration: none;
  }
  .ms-cat a.active { border-color: var(--primary); color: var(--primary); font-weight: 700; }
  .ms-cat .cnt { font-size: 11px; font-weight: 700; padding: 0 6px; border-radius: 999px;
                 background: var(--gray-100); color: var(--gray-600); }
  .ms-cat a.active .cnt { background: var(--primary-50); color: var(--primary); }

  .ms-chip { display:inline-flex; align-items:center; padding:1px 8px; border-radius:999px;
             font-size:11px; font-weight:700; line-height:18px;
             background:var(--gray-100); color:var(--gray-600); border:1px solid var(--gray-200); }
  .ms-chip.on { background:var(--primary-50); color:var(--primary); border-color:var(--primary-200); }
  .ms-mini { border:1px solid var(--gray-200); background:var(--gray-0); border-radius:6px;
             padding:1px 8px; font-size:11px; font-weight:700; line-height:18px;
             color:var(--gray-700); cursor:pointer; }
  .ms-mini:hover { border-color:var(--primary); color:var(--primary); }
</style>
@endpush

@section('content')

@php $cat = $categories[$current]; @endphp

{{-- ── 카테고리 탭 ── --}}
<div class="ms-cat">
  @foreach($categories as $key => $c)
    <a href="{{ route('masters.index', ['cat' => $key]) }}" class="{{ $key === $current ? 'active' : '' }}">
      {{ $c['label'] }} <span class="cnt">{{ number_format($counts[$key] ?? 0) }}</span>
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 (탭 안) ── --}}
<form method="GET" action="{{ route('masters.index') }}" class="ds-filter-card" style="margin-top:12px;">
  <input type="hidden" name="cat" value="{{ $current }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-4">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control"
             placeholder="{{ implode('ㆍ', array_slice(array_column($cat['fields'], 'label'), 0, 4)) }}">
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">사용 여부</label>
      <select name="active_only" class="form-control">
        <option value="">전체</option>
        <option value="1" {{ $onlyActive ? 'selected' : '' }}>사용 중만</option>
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if($q || $onlyActive)
      <a href="{{ route('masters.index', ['cat' => $current]) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__masterGrid?.downloadExcel()">엑셀 저장</button>
    <button type="button" class="ds-btn ds-btn-primary" onclick="msNew()">{{ $cat['label'] }} 등록</button>
  </div>
</form>

{{-- ── 목록 ── --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format(count($gridData)) }}건)</span></button>
      </div>
    <div id="masterGrid"></div>
  </div>
</div>

{{-- ── 등록·수정 창 ── --}}
<div id="msBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="msClose()"></div>
<div id="msModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:560px;max-width:94vw;max-height:90vh;overflow:auto;background:var(--bg-card);
     border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;position:sticky;top:0;">
    <span id="msTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">{{ $cat['label'] }}</span>
    <button onclick="msClose()" style="background:none;border:none;cursor:pointer;color:var(--gray-0);font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    @foreach($cat['fields'] as $key => $f)
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:4px;">
          {{ $f['label'] }}
          @if($f['required'] ?? false)<span style="color:var(--alert-500);">*</span>@endif
        </label>
        @if($key === 'note' || $key === 'address')
          <textarea class="form-control" id="ms-{{ $key }}" rows="2" style="font-size:13px;"></textarea>
        @else
          <input type="text" class="form-control" id="ms-{{ $key }}" />
        @endif
      </div>
    @endforeach
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-700);">
      <input type="checkbox" id="ms-active" checked /> 사용
    </label>
    <div id="msResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
    <div style="display:flex;justify-content:space-between;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" id="msDelete" onclick="msDelete()"
              style="color:var(--alert-500);border-color:var(--alert-100);">삭제</button>
      <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="msClose()">취소</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="msSave()">저장</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const CATEGORY = @json($current);
  const FIELDS   = @json(array_keys($cat['fields']));
  const LABEL    = @json($cat['label']);
  const STORE    = @json(route('masters.store'));
  const BASE     = @json(url('/settings/masters'));

  const cols = [
    @foreach($cat['fields'] as $key => $f)
    { header: @json($f['label']), name: @json($key), width: {{ $f['width'] ?? 140 }}, sortable: true },
    @endforeach
    { header: '사용', name: 'use', width: 70, align: 'center', sortable: true,
      renderer: (v) => {
        const s = document.createElement('span');
        s.className = 'ms-chip' + (v === '사용' ? ' on' : '');
        s.textContent = v;
        return s;
      } },
    { header: '', name: '_edit', width: 70, align: 'center', sortable: false, exportable: false,
      renderer: (v, row) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'ms-mini'; b.textContent = '수정';
        b.addEventListener('click', (e) => { e.stopPropagation(); msEdit(row); });
        return b;
      } },
  ];

  const grid = new wwGrid({
    el: document.getElementById('masterGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
    footer: { total: true, selected: false, modified: false },
    columns: cols,
    data: @json($gridData),
  });
  window.__masterGrid = grid;

  document.getElementById('masterGrid').addEventListener('dblclick', function (e) {
    if (e.target.closest('button, a, input')) return;
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) { window.getSelection()?.removeAllRanges(); msEdit(row); }
  });

  let editingId = null;

  window.msNew = function () {
    editingId = null;
    document.getElementById('msTitle').textContent = LABEL + ' 등록';
    FIELDS.forEach(f => { const el = document.getElementById('ms-' + f); if (el) el.value = ''; });
    document.getElementById('ms-active').checked = true;
    document.getElementById('msDelete').style.display = 'none';
    msOpen();
  };

  window.msEdit = function (row) {
    editingId = row.id;
    document.getElementById('msTitle').textContent = LABEL + ' 수정';
    FIELDS.forEach(f => { const el = document.getElementById('ms-' + f); if (el) el.value = row[f] ?? ''; });
    document.getElementById('ms-active').checked = row.use === '사용';
    document.getElementById('msDelete').style.display = '';
    msOpen();
  };

  function msOpen() {
    document.getElementById('msResult').style.display = 'none';
    document.getElementById('msBackdrop').style.display = 'block';
    document.getElementById('msModal').style.display    = 'block';
    const first = document.getElementById('ms-' + FIELDS[0]);
    if (first) first.focus();
  }

  window.msClose = function () {
    document.getElementById('msBackdrop').style.display = 'none';
    document.getElementById('msModal').style.display    = 'none';
  };

  function say(msg, ok) {
    const box = document.getElementById('msResult');
    box.style.display    = 'block';
    box.style.background = ok ? 'var(--primary-50)' : 'var(--danger-light)';
    box.style.color      = ok ? 'var(--primary)'    : 'var(--danger)';
    box.style.border     = '1px solid ' + (ok ? 'var(--primary-200)' : '#fca5a5');
    box.textContent = msg;
  }

  window.msSave = async function () {
    const body = { category: CATEGORY, is_active: document.getElementById('ms-active').checked };
    FIELDS.forEach(f => { const el = document.getElementById('ms-' + f); if (el) body[f] = el.value.trim(); });
    if (!body.name) { say('이름은 반드시 입력해야 합니다.', false); return; }

    try {
      const res = await fetch(editingId ? `${BASE}/${editingId}` : STORE, {
        method: editingId ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify(body),
      });
      const d = await res.json();
      if (d.success) { say('저장했습니다.', true); setTimeout(() => location.reload(), 700); }
      else { say(d.message ?? (Object.values(d.errors ?? {})[0]?.[0]) ?? '저장 실패', false); }
    } catch (e) { say('네트워크 오류가 발생했습니다.', false); }
  };

  window.msDelete = async function () {
    if (!editingId) return;
    const ok = await ceConfirm('이 항목을 지웁니다.\n지난 자료가 가리키던 이름은 그대로 남습니다.',
      { title: LABEL + ' 삭제', confirmText: '삭제', tone: 'danger' });
    if (!ok) return;
    try {
      const res = await fetch(`${BASE}/${editingId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      });
      const d = await res.json();
      if (d.success) { say('지웠습니다.', true); setTimeout(() => location.reload(), 600); }
      else { say(d.message ?? '삭제 실패', false); }
    } catch (e) { say('네트워크 오류가 발생했습니다.', false); }
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('msModal').style.display === 'block') msClose();
  });
})();
</script>
@endpush
