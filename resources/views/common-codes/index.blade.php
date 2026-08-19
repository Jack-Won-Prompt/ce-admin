@extends('layouts.app')

@section('title', '환경 설정')
@section('page-title', '환경 설정')
@section('breadcrumb', '홈 / 설정 / 환경 설정')

@push('styles')
<style>
  /* 코드 목록은 마스터 관리와 같은 얼개다 — 탭으로 목록을 고르고, 표에서 고쳐 쓴다 */
  .cc-hint { font-size:12px; color:var(--gray-600); line-height:1.7; }
  .cc-kind-chips { display:flex; gap:6px; flex-wrap:wrap; }
  .cc-kind-chip { display:inline-flex; align-items:center; gap:4px; height:24px; padding:0 8px;
                  border-radius:999px; background:var(--gray-100); color:var(--gray-700);
                  font-size:11.5px; font-weight:600; }
  .cc-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 12px; }
  .cc-form-grid .wide { grid-column:1 / -1; }
  .cc-field label { display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:4px; }
  .cc-lock { font-size:11.5px; color:var(--warning, #d08700); margin-top:6px; }
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
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">쓰는 곳</label>
      <div class="cc-hint">{{ $groups[$current]['hint'] ?? '' }}</div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <span class="ds-filter-total">{{ $groups[$current]['label'] }}({{ count($gridData) }})</span>
    <button type="button" class="ds-btn" onclick="window.__ccGrid?.downloadExcel()">엑셀 저장</button>
    @perm('common-codes', 'create')
    <button type="button" class="ds-btn ds-btn-primary" onclick="ccNew()">코드 등록</button>
    @endperm
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      @foreach($groups as $key => $g)
        <a href="{{ route('common-codes.index', ['group' => $key]) }}"
           class="pnl-tab {{ $key === $current ? 'active' : '' }}"
           style="display:inline-flex;align-items:center;text-decoration:none;">
          {{ $g['label'] }}
          @if(($counts[$key] ?? 0) > 0)
            <span class="pnl-tab-cnt">({{ $counts[$key] }})</span>
          @endif
        </a>
      @endforeach
      <span class="cc-kind-chips" style="margin-left:auto;align-items:center;">
        @foreach($kinds as $kk => $kl)
          <span class="cc-kind-chip" title="{{ $groups[$current]['kinds'][$kk]['hint'] ?? '' }}">{{ $kl }}</span>
        @endforeach
      </span>
    </div>

    <div id="pnlList" style="padding:16px;">
      <div id="ccGrid"></div>
    </div>
  </div>
</div>

{{-- 등록·수정 창 --}}
<div class="modal-overlay" id="ccModal">
  <div class="modal-content" style="max-width:560px;">
    <div class="modal-header">
      <h3 id="ccModalTitle">코드 등록</h3>
      <button type="button" class="modal-close" onclick="ccClose()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="cc-form-grid">
        <div class="cc-field">
          <label>유형 <span style="color:var(--danger);">*</span></label>
          <select class="form-control form-select" id="cc-kind">
            @foreach($kinds as $kk => $kl)
              <option value="{{ $kk }}">{{ $kl }}</option>
            @endforeach
          </select>
        </div>
        <div class="cc-field">
          <label>코드 <span style="color:var(--danger);">*</span></label>
          <input type="text" class="form-control" id="cc-code" maxlength="60" placeholder="tax_invoice">
          <div class="cc-hint">저장에 쓰는 값입니다. 영문 소문자·숫자·밑줄만 씁니다.</div>
        </div>
        <div class="cc-field wide">
          <label>이름 <span style="color:var(--danger);">*</span></label>
          <input type="text" class="form-control" id="cc-label" maxlength="100" placeholder="세금계산서(주민등록번호)">
          <div class="cc-hint">화면에서 고를 때 보이는 이름입니다.</div>
        </div>
        <div class="cc-field">
          <label>차례</label>
          <input type="number" class="form-control" id="cc-sort" min="0" max="9999" value="0">
        </div>
        <div class="cc-field">
          <label>사용</label>
          <select class="form-control form-select" id="cc-active">
            <option value="1">사용</option>
            <option value="0">사용 안 함</option>
          </select>
        </div>
        <div class="cc-field wide">
          <label>메모</label>
          <input type="text" class="form-control" id="cc-note" maxlength="200" placeholder="언제 쓰는 서류인지">
        </div>
      </div>
      <div class="cc-lock" id="ccLock" style="display:none;">
        시스템이 쓰는 코드입니다 — 이름·차례·메모만 고칠 수 있습니다.
      </div>
    </div>
    <div class="modal-footer">
      @perm('common-codes', 'delete')
      <button type="button" class="ds-btn" id="ccDelBtn" style="margin-right:auto;color:var(--alert-500);"
              onclick="ccDelete()">사용 중지</button>
      @endperm
      <button type="button" class="ds-btn" onclick="ccClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" id="ccSaveBtn" onclick="ccSave(this)">저장</button>
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

  const grid = new wwGrid({
    el: document.getElementById('ccGrid'),
    height: 'auto', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
    columns: [
      { header: '유형',   name: 'kind',       width: 110, sortable: true },
      { header: '코드',   name: 'code',       width: 180, sortable: true },
      { header: '이름',   name: 'label',      width: 260, sortable: true },
      { header: '차례',   name: 'sort_order', width: 70,  align: 'right' },
      { header: '사용',   name: 'active',     width: 90,  align: 'center', sortable: true },
      { header: '구분',   name: 'system',     width: 80,  align: 'center' },
      { header: '메모',   name: 'note',       width: 260 },
    ],
    data: ROWS,
  });
  window.__ccGrid = grid;

  /* 고칠 것은 그 줄을 두 번 눌러 연다 — 목록 화면들과 같은 몸짓이다 */
  document.getElementById('ccGrid').addEventListener('dblclick', (e) => {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    ccEdit(grid.getData()[parseInt(cell.dataset.rowIndex, 10)]);
  });

  let editing = null;

  window.ccNew = function () {
    editing = null;
    document.getElementById('ccModalTitle').textContent = '코드 등록';
    document.getElementById('cc-code').value  = '';
    document.getElementById('cc-label').value = '';
    document.getElementById('cc-note').value  = '';
    document.getElementById('cc-sort').value  = 0;
    document.getElementById('cc-active').value = '1';
    document.getElementById('cc-code').disabled   = false;
    document.getElementById('cc-kind').disabled   = false;
    document.getElementById('cc-active').disabled = false;
    document.getElementById('ccLock').style.display = 'none';
    const del = document.getElementById('ccDelBtn');
    if (del) del.style.display = 'none';
    document.getElementById('ccModal').classList.add('show');
    setTimeout(() => document.getElementById('cc-label').focus(), 50);
  };

  window.ccEdit = function (row) {
    if (!row) return;
    editing = row;
    document.getElementById('ccModalTitle').textContent = row.label + ' 수정';
    document.getElementById('cc-kind').value   = row.kind_key ?? '';
    document.getElementById('cc-code').value   = row.code;
    document.getElementById('cc-label').value  = row.label;
    document.getElementById('cc-note').value   = row.note ?? '';
    document.getElementById('cc-sort').value   = row.sort_order ?? 0;
    document.getElementById('cc-active').value = row.is_active ? '1' : '0';

    // 시스템 코드는 값과 유형를 잠근다 — 바꾸면 이미 쌓인 서류가 이름을 잃는다
    document.getElementById('cc-code').disabled   = !!row.is_system;
    document.getElementById('cc-kind').disabled   = !!row.is_system;
    document.getElementById('cc-active').disabled = !!row.is_system;
    document.getElementById('ccLock').style.display = row.is_system ? '' : 'none';
    const del = document.getElementById('ccDelBtn');
    if (del) del.style.display = row.is_system ? 'none' : '';

    document.getElementById('ccModal').classList.add('show');
  };

  window.ccClose = function () {
    document.getElementById('ccModal').classList.remove('show');
  };

  window.ccSave = async function (btn) {
    const label = document.getElementById('cc-label').value.trim();
    const code  = document.getElementById('cc-code').value.trim();
    if (!label) { showToast('이름을 적어 주십시오.', 'warning'); return; }
    if (!editing && !code) { showToast('코드를 적어 주십시오.', 'warning'); return; }

    const payload = {
      group:      GROUP,
      kind:       document.getElementById('cc-kind').value,
      code:       code || editing?.code,
      label,
      note:       document.getElementById('cc-note').value.trim() || null,
      sort_order: parseInt(document.getElementById('cc-sort').value) || 0,
      is_active:  document.getElementById('cc-active').value === '1',
    };

    BtnState.loading(btn, '저장 중...');
    try {
      const res = editing
        ? await apiRequest(`${BASE}/${editing.id}`, 'PUT',  payload)
        : await apiRequest(BASE,                    'POST', payload);
      if (!res.success) throw new Error(res.message || '저장하지 못했습니다.');
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 600);
    } catch (e) {
      BtnState.reset(btn);
      showToast(e.message || '저장하지 못했습니다.', 'danger', 5000);
    }
  };

  window.ccDelete = async function () {
    if (!editing) return;
    const ok = await ceConfirm(`${editing.label} 을(를) 사용 안 함으로 바꿀까요?\n이미 그 유형으로 올린 서류의 이름은 그대로 남습니다.`,
                               { tone: 'warning', confirmText: '사용 중지' });
    if (!ok) return;
    const res = await apiRequest(`${BASE}/${editing.id}`, 'DELETE');
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 600);
    } else {
      showToast(res.message || '바꾸지 못했습니다.', 'danger', 5000);
    }
  };
})();
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.pnl-tabs', title: '코드 목록', body: '화면마다 고르는 목록을 여기서 고릅니다. 지금은 <b>서류 유형</b> 하나입니다.' },
  { selector: '[onclick="ccNew()"]', title: '코드 등록', body: '새 서류명을 더하면 처방자료 업로드에서 바로 고를 수 있습니다.' },
  { selector: '#ccGrid', title: '고치기', body: '줄을 <b>더블클릭</b>하면 이름·차례를 고칩니다. 시스템 코드는 이름만 고칠 수 있습니다.' },
];
</script>
@endpush
