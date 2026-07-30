{{-- resources/views/documents/index.blade.php --}}
@extends('layouts.app')

@section('title', '서류 관리')
@section('page-title', '서류 관리')
@section('breadcrumb', '홈 / 서류 관리')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=4">
<style>
  .type-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .type-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .type-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .type-tab.active { border-color: var(--primary); background: var(--primary); color: #fff; }
  .type-tab .cnt {
    min-width: 20px; padding: 0 5px; height: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 20px; font-size: 10.5px; font-weight: 700;
    background: rgba(255,255,255,.25);
  }
  .type-tab:not(.active) .cnt { background: var(--border-light); color: var(--text-muted); }

  .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
  .filename-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }

  /* ── 패널 탭(조회결과 / 서류 등록) ── */
  .pnl-tabs { display:flex; gap:4px; margin-bottom:16px; border-bottom:2px solid var(--border); }
  .pnl-tab { padding:9px 20px; font-size:13.5px; font-weight:700; border:none; background:none; cursor:pointer;
    color:var(--text-secondary); border-bottom:3px solid transparent; margin-bottom:-2px;
    display:inline-flex; align-items:center; gap:6px; }
  .pnl-tab:hover { color:var(--primary); }
  .pnl-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .pnl-empty { text-align:center; padding:56px 24px; color:var(--text-muted); font-size:13px; }

  /* ── 서류 등록 패널 ── */
  .reg-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:var(--primary-light); border:1px solid var(--border); border-radius:var(--radius);
    padding:12px 16px; margin-bottom:16px; }
  .reg-head .rx { font-family:monospace; font-size:13px; font-weight:800; color:var(--primary); }
  .reg-head .meta { font-size:12px; color:var(--text-secondary); }
  .reg-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start; }
  @media (max-width:900px) { .reg-grid { grid-template-columns:1fr; } }
  .reg-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:16px 18px; }
  .reg-card h4 { margin:0 0 12px; font-size:13px; font-weight:800; color:var(--primary);
    padding-bottom:9px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .reg-card h4 .n { margin-left:auto; font-size:11px; font-weight:700; color:var(--text-muted); }
  .doc-row { display:flex; align-items:center; gap:9px; padding:9px 0; border-bottom:1px dashed var(--border-light); }
  .doc-row:last-child { border-bottom:none; }
  .doc-row .ic { font-size:15px; flex-shrink:0; width:18px; text-align:center; }
  .doc-row .nm { flex:1; min-width:0; }
  .doc-row .nm b { display:block; font-size:12.5px; font-weight:700; color:var(--text-primary);
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .doc-row .nm span { font-size:11px; color:var(--text-muted); }
  .doc-tag { font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px;
    background:var(--primary-light); color:var(--primary); flex-shrink:0; }
  .doc-tag.att { background:var(--warning-light); color:var(--warning); }
  .doc-dl { font-size:11px; color:var(--primary); text-decoration:none; flex-shrink:0; white-space:nowrap; }
  .reg-field { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; }
  .reg-field label { font-size:12px; font-weight:700; color:var(--text-secondary); }
  .reg-field select, .reg-field input[type=file] {
    padding:9px 11px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:#fff; }
  .reg-hint { font-size:11px; color:var(--text-muted); line-height:1.6; }
</style>
@endpush

@section('content')

@php
  $totalAll = $typeCounts->sum();
  $curType  = request('type');
@endphp

{{-- ── 유형 탭 ── --}}
<div class="type-tabs">
  <a href="{{ route('documents.index', request()->except('type', 'page')) }}"
     class="type-tab {{ !$curType ? 'active' : '' }}">
    전체 <span class="cnt">{{ $totalAll }}</span>
  </a>
  @foreach($types as $key => $label)
    <a href="{{ route('documents.index', array_merge(request()->except('type','page'), ['type' => $key])) }}"
       class="type-tab {{ $curType === $key ? 'active' : '' }}">
      {{ $label }}
      @if(($typeCounts[$key] ?? 0) > 0)
        <span class="cnt">{{ $typeCounts[$key] }}</span>
      @endif
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('documents.index') }}" class="filter-bar mb-4">
  @if($curType)
    <input type="hidden" name="type" value="{{ $curType }}">
  @endif
  <input type="text" name="q" value="{{ request('q') }}" class="form-control"
         placeholder="파일명 · 환자명" style="width:220px;">
  <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:145px;">
  <span style="color:var(--text-muted);font-size:13px;">~</span>
  <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="width:145px;">
  <select name="per_page" class="form-control" style="width:90px;" onchange="this.form.submit()">
    @foreach([10,20,50,100] as $n)
      <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
    @endforeach
  </select>
  <button type="submit" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-magnifying-glass"></i> 검색
  </button>
  @if(request('q') || request('date_from') || request('date_to'))
    <a href="{{ route('documents.index', array_filter(['type' => $curType])) }}"
       class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- ── 패널 탭: 조회결과 / 서류 등록 ── --}}
<div class="pnl-tabs">
  <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">
    <i class="fa-solid fa-list"></i> 조회결과
  </button>
  <button type="button" id="pnlBtnReg" class="pnl-tab" onclick="pnlShow('reg')">
    <i class="fa-solid fa-folder-plus"></i> 서류 등록
    <span id="pnlRegRx" style="font-family:monospace;font-size:11px;color:var(--text-muted);"></span>
  </button>
</div>

<div id="pnlList">
  {{-- ── 서류 목록 (wwGrid) ── --}}
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
    <i class="bx bx-folder-open" style="font-size:18px;color:var(--primary);"></i>
    <span class="card-header-title">서류 목록</span>
    <span style="font-size:12px;color:var(--text-muted);">
      <i class="bx bx-info-circle"></i> 행을 <b>클릭</b>하면 해당 처방전의 <b>모든 서류</b>를 확인하고 추가 등록할 수 있습니다.
    </span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="documentGrid"></div>
</div>

{{-- ── 서류 등록 탭 ── --}}
<div id="pnlReg" style="display:none;">
  <div id="regEmpty" class="pnl-empty">
    <i class="bx bx-hand-pointer" style="font-size:26px;opacity:.35;display:block;margin-bottom:8px;"></i>
    조회결과에서 서류 행을 <b>클릭</b>하면 해당 처방전의 서류 목록과 등록 폼이 여기에 표시됩니다.
  </div>

  <div id="regBody" style="display:none;">
    <div class="reg-head">
      <i class="fa-solid fa-file-prescription" style="color:var(--primary);"></i>
      <span class="rx" id="regRxNo"></span>
      <span class="meta" id="regMeta"></span>
      {{-- 워크스페이스 새 탭으로 연다(서류 관리 탭은 그대로 유지). href 를 유지해
           가운데 클릭·새 탭으로 열기 같은 브라우저 기본 동작도 살린다. --}}
      <a id="regRxLink" class="btn btn-outline btn-sm" href="#" style="margin-left:auto;"
         onclick="return openRxTab(event)">
        <i class="bx bx-link-external"></i> 처방전 검수 화면
      </a>
      <button type="button" class="btn btn-outline btn-sm" onclick="pnlShow('list')">
        <i class="bx bx-arrow-back"></i> 조회결과로
      </button>
    </div>

    <div class="reg-grid">
      {{-- 좌: 해당 처방전의 모든 서류 --}}
      <div class="reg-card">
        <h4><i class="bx bx-folder-open"></i> 이 처방전의 모든 서류 <span class="n" id="regDocCount"></span></h4>
        <div id="regDocList"></div>

        <h4 style="margin-top:20px;"><i class="bx bx-paperclip"></i> 첨부 서류 <span class="n" id="regAttCount"></span></h4>
        <div id="regAttList"></div>
      </div>

      {{-- 우: 서류 등록 --}}
      @perm('documents', 'create')
      <div class="reg-card">
        <h4><i class="bx bx-upload"></i> 서류 등록</h4>
        <form id="regForm" onsubmit="return regSubmit(event)">
          <div class="reg-field">
            <label>서류 유형</label>
            <select id="regType" required>
              @foreach($types as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="reg-field">
            <label>파일</label>
            <input type="file" id="regFile" accept=".pdf,.jpg,.jpeg,.png,.heic" required>
            <span class="reg-hint">PDF · JPG · PNG · HEIC, 최대 50MB</span>
          </div>
          <button type="submit" id="regSubmitBtn" class="btn btn-primary btn-sm" style="width:100%;height:38px;">
            <i class="bx bx-upload"></i> 등록
          </button>
          <div class="reg-hint" style="margin-top:10px;">
            등록한 서류는 <b>서류 관리 목록</b>과 유형별 건수에 즉시 반영되며,
            생성유형은 <b>직접 등록</b>으로 표시됩니다.
          </div>
        </form>
      </div>
      @endperm
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('documentGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '유형',       name: 'type',      width: 110, sortable: true, align: 'center' },
      { header: '생성유형',   name: 'source',    width: 120, sortable: true },
      { header: '환자명',     name: 'patient',   width: 100, sortable: true },
      { header: '처방번호',   name: 'rx_number', width: 150, sortable: true },
      { header: '파일명',     name: 'filename',  width: 260 },
      { header: '생성자',     name: 'creator',   width: 100, sortable: true },
      { header: '생성일',     name: 'created',   width: 140, sortable: true },
      { header: '다운로드경로', name: 'download',  width: 260 },
    ],
    data: @json($gridData),
  });
  window.__documentGrid = grid;

  const BY_RX_URL  = @json(url('documents/by-prescription'));
  const STORE_URL  = @json(route('documents.store'));
  const CSRF       = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  let   _regRxId   = null;   // 처방전 PK (등록 폼 전송용)
  let   _regRxNo   = null;   // 처방번호 (조회 URL — Prescription 의 라우트 키)

  const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  /* ── 패널 탭 전환 ─────────────────────────────────────── */
  window.pnlShow = function (which) {
    const reg = which === 'reg';
    document.getElementById('pnlList').style.display = reg ? 'none' : '';
    document.getElementById('pnlReg').style.display  = reg ? '' : 'none';
    document.getElementById('pnlBtnList').classList.toggle('active', !reg);
    document.getElementById('pnlBtnReg').classList.toggle('active', reg);
  };

  /* ── 행 클릭 → 해당 처방전의 모든 서류 로드 ──────────────── */
  document.getElementById('documentGrid').addEventListener('click', function (e) {
    if (e.target.closest('input, button, a, select, textarea')) return;   // 체크박스·컨트롤 클릭은 무시
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row) return;
    if (!row.rx_number) { showToast('이 서류는 연결된 처방전이 없습니다.', 'warning'); return; }
    loadPrescriptionDocs(row.rx_number);
  });

  // Prescription 의 라우트 키는 rx_number 이므로 처방번호로 조회한다
  async function loadPrescriptionDocs(rxNumber) {
    pnlShow('reg');
    document.getElementById('regEmpty').style.display = 'none';
    document.getElementById('regBody').style.display  = '';
    document.getElementById('regDocList').innerHTML =
      '<div style="padding:14px 0;font-size:12px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';
    document.getElementById('regAttList').innerHTML = '';

    try {
      const res = await fetch(`${BY_RX_URL}/${encodeURIComponent(rxNumber)}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const d = await res.json();
      if (!d.success) throw new Error('load failed');

      _regRxId = d.prescription.id;
      _regRxNo = d.prescription.rx_number;
      document.getElementById('regRxNo').textContent = d.prescription.rx_number;
      document.getElementById('regMeta').textContent =
        `${d.prescription.patient} · ${d.prescription.hospital} · ${d.prescription.status}`;
      document.getElementById('regRxLink').href = d.prescription.url;
      document.getElementById('pnlRegRx').textContent = d.prescription.rx_number;

      renderDocs(d.documents);
      renderAtts(d.attachments);
    } catch (err) {
      document.getElementById('regDocList').innerHTML =
        '<div style="padding:14px 0;font-size:12px;color:var(--danger);">서류를 불러오지 못했습니다.</div>';
    }
  }

  function renderDocs(docs) {
    document.getElementById('regDocCount').textContent = docs.length ? `${docs.length}건` : '';
    document.getElementById('regDocList').innerHTML = docs.length
      ? docs.map(x => `
        <div class="doc-row">
          <span class="ic">📄</span>
          <span class="doc-tag">${esc(x.typeLabel)}</span>
          <span class="nm">
            <b title="${esc(x.filename)}">${esc(x.filename || '(파일명 없음)')}</b>
            <span>${esc(x.source)} · ${esc(x.created)}${x.creator ? ' · ' + esc(x.creator) : ''}</span>
          </span>
          <a class="doc-dl" href="${esc(x.download)}"><i class="bx bx-download"></i> 다운로드</a>
        </div>`).join('')
      : '<div style="padding:12px 0;font-size:12px;color:var(--text-muted);">등록된 생성 서류가 없습니다.</div>';
  }

  function renderAtts(atts) {
    document.getElementById('regAttCount').textContent = atts.length ? `${atts.length}건` : '';
    document.getElementById('regAttList').innerHTML = atts.length
      ? atts.map(x => `
        <div class="doc-row">
          <span class="ic">${x.isPdf ? '📕' : '🖼️'}</span>
          <span class="doc-tag att">${esc(x.typeLabel)}</span>
          <span class="nm">
            <b title="${esc(x.filename)}">${esc(x.filename || '(파일명 없음)')}</b>
            <span>${esc(x.created)}</span>
          </span>
          <a class="doc-dl" href="${esc(x.url)}" target="_blank" rel="noopener"><i class="bx bx-link-external"></i> 열기</a>
        </div>`).join('')
      : '<div style="padding:12px 0;font-size:12px;color:var(--text-muted);">첨부된 서류가 없습니다.</div>';
  }

  /* ── 처방전 검수 화면을 '새 탭'으로 열기 ───────────────────
     워크스페이스 안에서는 서류 관리 탭을 그대로 두고 별도 탭이 열리고,
     단독 페이지로 열려 있으면 브라우저 새 탭으로 대체된다. */
  window.openRxTab = function (ev) {
    ev.preventDefault();
    const url = document.getElementById('regRxLink').getAttribute('href');
    if (!url || url === '#') return false;
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(url, (_regRxNo || '처방전') + ' 검수', 'bx-scan');
    } else {
      window.open(url, '_blank', 'noopener');
    }
    return false;
  };

  /* ── 서류 등록 ────────────────────────────────────────── */
  window.regSubmit = async function (ev) {
    ev.preventDefault();
    if (!_regRxId) { showToast('처방전을 먼저 선택하세요.', 'warning'); return false; }

    const fileEl = document.getElementById('regFile');
    if (!fileEl.files.length) { ceAlert('등록할 파일을 선택해 주세요.', { tone: 'warning' }); return false; }

    const btn  = document.getElementById('regSubmitBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 등록 중...';

    const fd = new FormData();
    fd.append('prescription_id', _regRxId);
    fd.append('type', document.getElementById('regType').value);
    fd.append('file', fileEl.files[0]);

    try {
      const res = await fetch(STORE_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: fd,
      });
      const d = await res.json();

      if (!res.ok || !d.success) {
        const msg = d.message || Object.values(d.errors ?? {}).flat().join('\n') || '등록에 실패했습니다.';
        ceAlert(msg, { tone: 'danger' });
        return false;
      }

      showToast(d.message, 'success');
      fileEl.value = '';
      await loadPrescriptionDocs(_regRxNo);   // 좌측 목록 갱신
      addToGrid(d.document);                  // 조회결과 그리드·유형 건수 반영
    } catch (e) {
      ceAlert('등록 중 네트워크 오류가 발생했습니다.', { tone: 'danger' });
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
    return false;
  };

  /* 등록된 서류를 조회결과 그리드 맨 위에 반영 + 상단 유형 탭 건수 증가.
     (현재 유형 필터와 맞지 않으면 그리드에는 넣지 않는다) */
  function addToGrid(doc) {
    const curType = @json($curType);
    if (!curType || curType === doc.type) {
      grid.setData([{
        id: doc.id, type: doc.typeLabel, source: doc.source,
        patient: doc.patient, rx_number: doc.rx_number, filename: doc.filename,
        creator: doc.creator, created: doc.created, download: doc.download,
        prescription_id: doc.prescription_id,
      }, ...grid.getData()]);
    }

    // 유형 탭 건수 +1 (전체 탭 포함)
    document.querySelectorAll('.type-tab').forEach(function (tab) {
      const href = tab.getAttribute('href') || '';
      const isAll = !/[?&]type=/.test(href);
      if (!isAll && !href.includes('type=' + doc.type)) return;
      let cnt = tab.querySelector('.cnt');
      if (!cnt) {
        cnt = document.createElement('span');
        cnt.className = 'cnt';
        cnt.textContent = '0';
        tab.appendChild(cnt);
      }
      cnt.textContent = String((parseInt(cnt.textContent, 10) || 0) + 1);
    });
  }
})();
</script>
@endpush
