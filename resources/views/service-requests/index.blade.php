{{-- resources/views/service-requests/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SR 관리')
@section('page-title', 'SR 관리')
@section('breadcrumb', '홈 / 지원 / SR 관리')

@push('styles')
<style>
  .status-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
  .status-tab {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
    border:1.5px solid var(--border); background:#fff;
    color:var(--text-secondary); cursor:pointer; text-decoration:none; transition:var(--transition);
  }
  .status-tab:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  .status-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
  .status-tab .cnt {
    min-width:20px; padding:0 5px; height:18px; border-radius:20px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:10.5px; font-weight:700; background:rgba(255,255,255,.25);
  }
  .status-tab:not(.active) .cnt { background:var(--border-light); color:var(--text-muted); }


  .pnl-tabs { display:flex; gap:16px; margin-bottom:16px; border-bottom:1px solid var(--border); }
  .pnl-tab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
    color:var(--text-secondary); border-bottom:1px solid transparent; margin-bottom:-1px;
    display:inline-flex; align-items:center; gap:7px; }
  .pnl-tab:hover { color:var(--primary); }
  .pnl-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .pnl-empty { text-align:center; padding:56px 24px; color:var(--text-muted); font-size:13px; }

  .srx-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; }
  .srx-grid2 { display:grid; grid-template-columns:1.15fr .85fr; gap:16px; align-items:start; }
  @media (max-width:1000px) { .srx-grid2 { grid-template-columns:1fr; } }
  .srx-card h4 { margin:0 0 14px; font-size:13px; font-weight:800; color:var(--primary);
    padding-bottom:9px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .srx-field { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; }
  .srx-field label { font-size:12px; font-weight:700; color:var(--text-secondary); }
  .srx-field input[type=text], .srx-field select, .srx-field textarea {
    padding:9px 11px; border:1px solid var(--border); border-radius:8px; font-size:13.5px; font-family:inherit; }
  .srx-field textarea { min-height:120px; resize:vertical; }
  .srx-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .srx-hint { font-size:11px; color:var(--text-muted); line-height:1.6; }
  .srx-meta { font-size:11.5px; color:var(--text-muted); margin-bottom:10px; }
  .srx-body { font-size:13px; line-height:1.85; white-space:pre-wrap; color:var(--text-primary); }
  .srx-answer { margin-top:12px; padding:12px 14px; background:var(--primary-light);
    border:1px solid var(--border); border-radius:8px; }
  .srx-answer .lbl { font-size:10.5px; font-weight:800; color:var(--primary); margin-bottom:5px; }
  .sr-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px; }
  .sr-b-open        { background:var(--warning-light); color:var(--warning); }
  .sr-b-in_progress { background:var(--primary-light);  color:var(--primary); }
  .sr-b-answered    { background:var(--success-light);  color:var(--success); }
  .sr-b-closed      { background:var(--border-light);   color:var(--text-muted); }
</style>
@endpush

@section('content')

@php $curStatus = request('status'); @endphp

{{-- 상태 탭 --}}
<div class="status-tabs">
  <a href="{{ route('sr.index', request()->except(['status','page'])) }}"
     class="status-tab {{ !$curStatus ? 'active' : '' }}">
    전체 <span class="cnt">{{ $counts['all'] }}</span>
  </a>
  @foreach($statuses as $key => $label)
    <a href="{{ route('sr.index', array_merge(request()->except(['status','page']), ['status' => $key])) }}"
       class="status-tab {{ $curStatus === $key ? 'active' : '' }}">
      {{ $label }}
      @if(($counts[$key] ?? 0) > 0)<span class="cnt">{{ $counts[$key] }}</span>@endif
    </a>
  @endforeach
</div>

{{-- 검색 --}}
<form method="GET" action="{{ route('sr.index') }}" class="filter-bar">
  @if($curStatus)<input type="hidden" name="status" value="{{ $curStatus }}">@endif
  <input type="text" name="q" value="{{ request('q') }}" class="form-control"
         placeholder="제목 · 내용" style="width:240px;">
  <select name="category" class="form-control" style="width:150px;">
    <option value="">전체 구분</option>
    @foreach($categories as $k => $v)
      <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
    @endforeach
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> 검색</button>
  @if(request('q') || request('category'))
    <a href="{{ route('sr.index', array_filter(['status' => $curStatus])) }}" class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- 패널 탭 --}}
<div class="pnl-tabs">
  <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">
    <i class="fa-solid fa-list"></i> SR 목록
  </button>
  <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">
    <i class="fa-solid fa-comments"></i> 상세 · 답변
    <span id="pnlDetailTitle" style="font-size:11px;color:var(--text-muted);font-weight:600;"></span>
  </button>
  @perm('service-requests', 'create')
  <button type="button" id="pnlBtnNew" class="pnl-tab" onclick="pnlShow('new')">
    <i class="fa-solid fa-plus"></i> 신규 등록
  </button>
  @endperm
</div>

{{-- 목록 --}}
<div id="pnlList">
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;flex-wrap:wrap;">
    @perm('service-requests', 'delete')
    <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"
            onclick="srDeleteSelected()">
      <i class="bx bx-trash"></i> 선택 삭제
    </button>
    @endperm
    <span style="font-size:12px;color:var(--text-muted);">
      <i class="bx bx-info-circle"></i> 행을 <b>클릭</b>하면 상세 · 답변 탭이 열립니다.
    </span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="srxGrid"></div>
</div>

{{-- 상세 · 답변 --}}
<div id="pnlDetail" style="display:none;">
  <div id="srxEmpty" class="pnl-empty">
    <i class="bx bx-hand-pointer" style="font-size:26px;opacity:.35;display:block;margin-bottom:8px;"></i>
    목록에서 SR 을 <b>클릭</b>하면 내용과 답변이 여기에 표시됩니다.
  </div>
  <div id="srxBody" style="display:none;" class="srx-grid2">
    <div class="srx-card">
      <h4><i class="bx bx-detail"></i> 요청 내용</h4>
      <div id="srxDetail"></div>
    </div>
    <div class="srx-card">
      <h4><i class="bx bx-message-check"></i> 답변</h4>
      @perm('service-requests', 'update')
      <div class="srx-field">
        <label>답변 내용</label>
        <textarea id="srxAnswer" maxlength="5000" placeholder="처리 결과나 안내를 적어 주세요."></textarea>
      </div>
      <div class="srx-row2">
        <div class="srx-field">
          <label>상태</label>
          <select id="srxStatus">
            @foreach($statuses as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
          </select>
        </div>
        <div class="srx-field" style="justify-content:flex-end;">
          <button type="button" class="btn btn-primary btn-sm" id="srxAnswerBtn" onclick="srxSaveAnswer()" style="height:38px;">
            <i class="bx bx-save"></i> 답변 저장
          </button>
        </div>
      </div>
      @else
      <div class="srx-hint">답변 권한이 없어 조회만 가능합니다.</div>
      @endperm
    </div>
  </div>
</div>

{{-- 신규 등록 --}}
@perm('service-requests', 'create')
<div id="pnlNew" style="display:none;">
  <div class="srx-card" style="max-width:720px;">
    <h4><i class="bx bx-plus"></i> SR 신규 등록</h4>
    <div class="srx-row2">
      <div class="srx-field">
        <label>구분</label>
        <select id="srxCategory">
          @foreach($categories as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
      </div>
      <div class="srx-field">
        <label>우선순위</label>
        <select id="srxPriority">
          @foreach($priorities as $k => $v)
            <option value="{{ $k }}" {{ $k === 'normal' ? 'selected' : '' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="srx-field">
      <label>제목 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="srxTitle" maxlength="200" placeholder="예) 처방전 목록에 발행일 필터 추가">
    </div>
    <div class="srx-field">
      <label>내용 <span style="color:var(--danger);">*</span></label>
      <textarea id="srxContent" maxlength="5000" placeholder="어떤 화면에서 무엇이 어떻게 되면 좋을지 적어 주세요."></textarea>
    </div>
    <div class="srx-field">
      <label>대상 화면</label>
      <input type="text" id="srxPageLabel" maxlength="100" placeholder="예) 처방전 목록">
      <span class="srx-hint">비워 두면 기록되지 않습니다. 상단 SR 패널로 등록하면 보고 있던 화면이 자동 기록됩니다.</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="srxSubmitBtn" onclick="srxSubmit()"
            style="width:100%;height:40px;">
      <i class="bx bx-send"></i> 등록
    </button>
  </div>
</div>
@endperm

@endsection

@push('scripts')
<script>
(function () {
  const BASE  = @json(url('sr'));
  const CSRF  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const CLS   = { open:'sr-b-open', in_progress:'sr-b-in_progress', answered:'sr-b-answered', closed:'sr-b-closed' };
  let _rows = @json($gridData);
  let _sel  = null;

  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  const grid = new wwGrid({
    el: document.getElementById('srxGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '상태',      name: 'statusLabel',   width: 90,  align: 'center', sortable: true },
      { header: '구분',      name: 'categoryLabel', width: 100, align: 'center', sortable: true },
      { header: '우선순위',  name: 'priorityLabel', width: 80,  align: 'center', sortable: true },
      { header: '제목',      name: 'title',         width: 300 },
      { header: '대상 화면', name: 'page',          width: 150 },
      { header: '등록자',    name: 'writer',        width: 100, sortable: true },
      { header: '등록일',    name: 'created',       width: 140, align: 'center', sortable: true },
      { header: '답변자',    name: 'answerer',      width: 100 },
    ],
    data: _rows,
  });
  window.__srxGrid = grid;

  window.pnlShow = function (which) {
    [['list','pnlList','pnlBtnList'], ['detail','pnlDetail','pnlBtnDetail'], ['new','pnlNew','pnlBtnNew']]
      .forEach(([k, paneId, btnId]) => {
        const pane = document.getElementById(paneId), btn = document.getElementById(btnId);
        if (pane) pane.style.display = (k === which) ? '' : 'none';
        if (btn)  btn.classList.toggle('active', k === which);
      });
  };

  document.getElementById('srxGrid').addEventListener('click', function (e) {
    if (e.target.closest('input, button, a, select, textarea')) return;
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) selectRow(row.id);
  });

  function selectRow(id) {
    const r = _rows.find(x => x.id === id);
    if (!r) return;
    _sel = r;
    document.getElementById('srxEmpty').style.display = 'none';
    document.getElementById('srxBody').style.display  = '';
    document.getElementById('pnlDetailTitle').textContent = r.title;

    document.getElementById('srxDetail').innerHTML = `
      <div style="font-size:15px;font-weight:800;margin-bottom:4px;">${esc(r.title)}</div>
      <div class="srx-meta">
        <span class="sr-badge ${CLS[r.status] || ''}">${esc(r.statusLabel)}</span>
        · ${esc(r.categoryLabel)} · 우선순위 ${esc(r.priorityLabel)}
        · ${esc(r.writer)} · ${esc(r.created)}
        ${r.page ? ' · 대상: ' + esc(r.page) : ''}
      </div>
      <div class="srx-body">${esc(r.content)}</div>
      ${r.answer ? `<div class="srx-answer">
        <div class="lbl">답변 · ${esc(r.answerer)} · ${esc(r.answered_at)}</div>
        <div class="srx-body">${esc(r.answer)}</div>
      </div>` : ''}`;

    const a = document.getElementById('srxAnswer'); if (a) a.value = r.answer || '';
    const s = document.getElementById('srxStatus'); if (s) s.value = r.status;
    pnlShow('detail');
  }

  window.srxSaveAnswer = async function () {
    if (!_sel) { showToast('SR 을 먼저 선택하세요.', 'warning'); return; }
    const answer = document.getElementById('srxAnswer').value.trim();
    if (!answer) { ceAlert('답변 내용을 입력해 주세요.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srxAnswerBtn');
    btn.disabled = true;
    try {
      const res = await fetch(`${BASE}/${_sel.id}/answer`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ answer, status: document.getElementById('srxStatus').value }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) { ceAlert(d.message || '저장하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      // 목록·상세 반영
      _rows = _rows.map(x => x.id === d.row.id ? d.row : x);
      grid.setData(_rows);
      selectRow(d.row.id);
    } catch (e) {
      ceAlert('저장 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };

  window.srxSubmit = async function () {
    const title   = document.getElementById('srxTitle').value.trim();
    const content = document.getElementById('srxContent').value.trim();
    if (!title || !content) { ceAlert('제목과 내용을 모두 입력해 주세요.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srxSubmitBtn');
    btn.disabled = true;
    try {
      const res = await fetch(BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
          title, content,
          category:   document.getElementById('srxCategory').value,
          priority:   document.getElementById('srxPriority').value,
          page_label: document.getElementById('srxPageLabel').value.trim(),
        }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) {
        ceAlert(d.message || Object.values(d.errors ?? {}).flat().join('\n') || '등록하지 못했습니다.', { tone: 'danger' });
        return;
      }
      showToast(d.message, 'success');
      _rows = [d.row, ...(_rows ?? [])];
      grid.setData(_rows);
      document.getElementById('srxTitle').value = '';
      document.getElementById('srxContent').value = '';
      pnlShow('list');
    } catch (e) {
      ceAlert('등록 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };

  window.srDeleteSelected = async function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('삭제할 SR 을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    if (!await ceConfirm(`'${c[0].title}' 을 삭제하시겠습니까?`, { tone: 'danger', confirmText: '삭제' })) return;

    try {
      const res = await fetch(`${BASE}/${c[0].id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      });
      const d = await res.json();
      if (!d.success) { ceAlert(d.message || '삭제하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      _rows = _rows.filter(x => x.id !== c[0].id);
      grid.setData(_rows);
      if (_sel && _sel.id === c[0].id) {
        _sel = null;
        document.getElementById('srxBody').style.display  = 'none';
        document.getElementById('srxEmpty').style.display = '';
      }
    } catch (e) { ceAlert('삭제 중 오류가 발생했습니다.', { tone: 'danger' }); }
  };
})();
</script>
@endpush
