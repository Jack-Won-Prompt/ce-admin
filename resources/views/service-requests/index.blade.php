{{-- resources/views/service-requests/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SR 관리')
@section('page-title', 'SR 관리')
@section('breadcrumb', '홈 - 지원 - SR 관리')

@push('styles')
<style>
  /* .status-tabs / .status-tab 은 예전 선택자다. 칩은 전역 .ds-chip 이 그리고,
     이 이름은 앵커로만 남긴다 — 별도 스타일은 주지 않는다.
     (스타일을 남겨 두면 gap 6 · radius 20 · 12.5px/600 이 전역 규격을 덮어쓴다.) */

  /* 상세 · 신규 등록 카드 — 흰 카드(r12 · pad 12/16 · bd 1px gray-200 · 그림자 없음) */
  .srx-card { background:var(--gray-0); border:1px solid var(--gray-200); border-radius:12px; padding:12px 16px; }
  .srx-grid2 { display:grid; grid-template-columns:1.15fr .85fr; gap:12px; align-items:start; }
  @media (max-width:1000px) { .srx-grid2 { grid-template-columns:1fr; } }
  /* 섹션 제목 — 14/700 · lh22 */
  .srx-card h4 { margin:0 0 12px; font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000);
    padding-bottom:8px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; gap:8px; }
  /* 필드 — 라벨 21 + gap 8 + 인풋 32 (.ds-filter-field 와 같은 규격) */
  .srx-field { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
  .srx-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  .srx-field input[type=text], .srx-field select, .srx-field textarea {
    padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; color:var(--gray-1000);
    background:var(--gray-0); font-family:inherit; }
  .srx-field input[type=text], .srx-field select { height:32px; }
  /* 여러 줄 입력은 32px 규격을 그대로 쓰면 위아래가 눌린다 — 전역 textarea 와 같은 여백을 준다 */
  .srx-field textarea { min-height:120px; resize:vertical; padding:9px 12px; line-height:21px; }
  .srx-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .srx-hint { font-size:12px; font-weight:400; line-height:19px; color:var(--gray-600); }
  .srx-meta { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-bottom:12px; }
  .srx-body { font-size:13px; font-weight:400; line-height:21px; white-space:pre-wrap; color:var(--gray-1000); }
  .srx-answer { margin-top:12px; padding:12px 16px; background:var(--primary-50);
    border:1px solid var(--gray-200); border-radius:8px; }
  .srx-answer .lbl { font-size:11px; font-weight:700; line-height:18px; color:var(--primary); margin-bottom:4px; }
  /* 상태 배지 — 배지 규격(r6 · pad 2/6 · 11px/500 · lh18).
     원래 미처리=주황 · 답변완료=초록이었다. 시안에는 주황·초록이 없어
     '손대야 하는 상태'는 alert, 진행·완료는 primary, 종료는 gray 로 옮겼다.

     ★ 반드시 .srx-meta 로 한 단계 좁힌다.
     layouts/app.blade.php 에도 같은 이름의 .sr-badge / .sr-b-*(10px/700 · pad 2/8 · r999 ·
     주황·초록)가 있는데, 그 <style> 은 <body> 안(2296~2616줄)이고 @stack('styles') 는
     <head>(1165줄)라서 특정성이 같으면 전역이 이긴다.
     이름 그대로 두면 여기 값이 한 줄도 먹지 않는다(전역 SR 플로팅 패널 배지는 그대로 둔다). */
  .srx-meta .sr-badge { display:inline-flex; align-items:center; font-size:11px; font-weight:500; line-height:18px;
    padding:2px 6px; border-radius:6px; }
  .srx-meta .sr-b-open        { background:var(--alert-100);   color:var(--alert-500); }
  .srx-meta .sr-b-in_progress { background:var(--primary-100); color:var(--primary-600); }
  .srx-meta .sr-b-answered    { background:var(--primary);     color:var(--gray-0); }
  .srx-meta .sr-b-closed      { background:var(--gray-100);    color:var(--gray-600); }
</style>
@endpush

@section('content')

@php $curStatus = request('status'); @endphp

{{-- 상태 칩 — h31 · r999 · pad 6/10 · 12/700, 건수 배지 16×16 정원 --}}
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}


{{-- 검색 필터 — 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래.
     폭은 인라인 style 대신 9열 그리드(검색어 3열 · 구분 2열)로 잡는다. --}}
<form method="GET" action="{{ route('sr.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $counts['all'] }})</option>
        @foreach($statuses as $key => $label)
          <option value="{{ $key }}" {{ $curStatus === $key ? 'selected' : '' }}>
            {{ $label }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="제목ㆍ내용">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">구분</label>
      <select name="category" class="form-control form-select">
        <option value="">전체 구분</option>
        @foreach($categories as $k => $v)
          <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('category'))
      <a href="{{ route('sr.index', array_filter(['status' => $curStatus])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> 검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__srxGrid?.downloadExcel()">엑셀 저장</button>
    @perm('service-requests', 'delete')
    <button type="button" class="ds-btn" style="color:var(--alert-500);"
      onclick="srDeleteSelected()">
      <i class="bx bx-trash"></i> 선택 삭제
    </button>
    @endperm
  </div>
</form>

{{-- 흰 카드(r12) 안에 탭바와 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 패널 탭은 카드 안 상단 (h44 · pad 0/16 · gap 16) --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">
        <i class="fa-solid fa-list"></i> SR 목록<span class="pnl-tab-cnt">(총 <b>{{ number_format($total) }}</b>건)</span></button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">
        <i class="fa-solid fa-comments"></i> 상세 · 답변
        <span id="pnlDetailTitle" style="font-size:12px;font-weight:500;color:var(--gray-600);"></span>
      </button>
      @perm('service-requests', 'create')
      <button type="button" id="pnlBtnNew" class="pnl-tab" onclick="pnlShow('new')">
        <i class="fa-solid fa-plus"></i> 신규 등록
      </button>
      @endperm
    </div>

{{-- 목록 --}}
<div id="pnlList">
  <div id="srxGrid"></div>
</div>

{{-- 상세 · 답변 --}}
<div id="pnlDetail" style="display:none;padding:16px;">
  <div id="srxEmpty" class="pnl-empty">
    <i class="bx bx-hand-pointer" style="font-size:16px;opacity:.35;display:block;margin-bottom:8px;"></i>
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
          <button type="button" class="btn btn-primary btn-sm" id="srxAnswerBtn" onclick="srxSaveAnswer()" style="height:32px;">
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
<div id="pnlNew" style="display:none;padding:16px;">
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
            style="width:100%;height:32px;">
      <i class="bx bx-send"></i> 등록
    </button>
  </div>
</div>
@endperm

  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

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
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, summary: false,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    toolbar: false,
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    footer: false,
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
  window.dsBindSelCount(grid, 'srxSelCount');

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
      <div style="font-size:14px;font-weight:700;line-height:22px;margin-bottom:4px;">${esc(r.title)}</div>
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
