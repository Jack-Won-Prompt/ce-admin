{{-- resources/views/documents/index.blade.php --}}
@extends('layouts.app')

@section('title', '서류 관리')
@section('page-title', '서류 관리')
@section('breadcrumb', '홈 / 서류 관리')

@push('styles')
<style>

  /* .type-tabs · .filter-bar 는 레이아웃의 ds-chip / ds-filter-card 로 대체됨.
     아래 두 규칙은 wwGrid 이전 <table> 시절 자산이라 마크업 사용처는 없지만 남겨 둔다. */
  .filename-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }

  /* 결과바 '선택 N건' — 전역(app.blade.php)에 규칙이 없어 화면에서 정의한다.
     PATTERN 규격: 13px/500 · lh21 · gray-600, 숫자만 primary-400 */
  .ds-grid-sel { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-600); }
  .ds-grid-sel b { color:var(--primary-400); }

  /* ── 패널 탭(조회 결과 / 서류 등록) ──
     Figma 248:2923 — 그리드 카드 안 상단 h44 · pad 0/16 · 활성 밑줄 1px primary */
  .pnl-tabs { display:flex; gap:16px; padding:0 16px; border-bottom:1px solid var(--border); flex-shrink:0; }
  .pnl-tab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
    color:var(--gray-600); border-bottom:1px solid transparent; margin-bottom:-1px;
    display:inline-flex; align-items:center; gap:6px; }
  .pnl-tab:hover { color:var(--primary); }
  .pnl-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .pnl-empty { text-align:center; padding:56px 24px; color:var(--gray-600); font-size:13px; }

  /* ── 서류 등록 패널 — Figma 248:3355 (카드 안쪽 pad 16 · gap 12) ── */
  #pnlReg { padding:16px; overflow-y:auto; min-height:0; }
  /* 머리줄: 처방번호 16/700 + 메타, 우측에 액션 버튼. 시안에 배경·테두리는 없다 */
  .reg-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
  .reg-head .rx { font-size:16px; font-weight:700; line-height:26px; color:var(--gray-1000); }
  .reg-head .meta { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  /* 좌우 2단 — 시안 762+12+762 = 1:1, gap 12 */
  .reg-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:start; }
  @media (max-width:900px) { .reg-grid { grid-template-columns:1fr; } }
  .reg-card { background:var(--gray-0); border:1px solid var(--gray-200); border-radius:12px; padding:12px 16px; }
  .reg-card h4 { margin:0 0 12px; font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000);
    display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  .reg-card h4 .n { font-size:14px; font-weight:700; color:var(--primary); }
  /* 두 번째 절(첨부 서류) 위 구분선 — 시안 730×1px gray-200 */
  .reg-card h4.reg-h4-2 { margin-top:16px; padding-top:16px; border-top:1px solid var(--gray-200); }

  /* 서류 한 줄 — 시안 r8 · pad 12 · gap 8 · bg gray-100 */
  .doc-row { display:flex; align-items:center; gap:8px; padding:12px; border-radius:8px; background:var(--gray-100); }
  .doc-row + .doc-row { margin-top:8px; }
  .doc-row .ic { font-size:18px; flex-shrink:0; width:28px; text-align:center; }
  .doc-row .nm { flex:1; min-width:0; }
  .doc-row .nm b { display:block; font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000);
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .doc-row .nm span { font-size:11px; font-weight:500; line-height:18px; color:var(--gray-500); }
  /* 유형 라벨 — 시안은 알약이 아니라 primary 글자다 (주황은 이 디자인에 없다) */
  .doc-tag { font-size:11px; font-weight:500; line-height:18px; color:var(--primary); flex-shrink:0; white-space:nowrap; }
  .doc-tag.att { color:var(--primary-400); }
  /* 다운로드/열기 — 시안 작은 버튼 h28 · r8 · pad 0/12 · gap 6 · 12px/500 */
  .doc-dl { display:inline-flex; align-items:center; justify-content:center; gap:6px;
    height:28px; padding:0 12px; border-radius:8px;
    background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000);
    font-size:12px; font-weight:500; line-height:19px;
    text-decoration:none; flex-shrink:0; white-space:nowrap; transition:var(--transition); }
  .doc-dl:hover { border-color:var(--primary); color:var(--primary); }

  /* 등록 폼 필드 — 시안 r8 · pad 12 · gap 8 · bg gray-100, 라벨 13/500 gray-700 */
  .reg-field { display:flex; flex-direction:column; gap:8px; margin-bottom:8px;
    padding:12px; border-radius:8px; background:var(--gray-100); }
  .reg-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  /* 파일 입력은 네이티브 컨트롤을 그대로 두고 껍데기만 입력 규격(h32 · r8 · bd 1px gray-200)에 맞춘다 */
  .reg-field input[type=file] {
    width:100%; height:32px; padding:0 12px 0 0; box-sizing:border-box;
    border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-50);
    font-size:13px; line-height:30px; color:var(--gray-800); font-family:inherit; }
  .reg-field input[type=file]::file-selector-button {
    height:30px; margin:0 12px 0 0; padding:0 12px;
    border:none; border-right:1px solid var(--gray-200); border-radius:8px 0 0 8px;
    background:var(--gray-0); color:var(--gray-1000);
    font-size:13px; font-weight:500; font-family:inherit; cursor:pointer; }
  /* 안내문 — 12px/500 gray-600 */
  .reg-hint { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .reg-hint-foot { margin-top:12px; }
  /* 허용 확장자·용량은 시안에서 제목 옆 알약 — r999 · pad 2/8 · 11px/500 */
  .reg-pill { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px;
    background:var(--gray-100); color:var(--gray-800); font-size:11px; font-weight:500; line-height:18px; }
</style>
@endpush

@section('content')

@php
  $totalAll = $typeCounts->sum();
  $curType  = request('type');
@endphp

{{-- ── 유형 탭 ── --}}
<div class="ds-chips">
  <a href="{{ route('documents.index', request()->except('type', 'page')) }}"
     class="ds-chip {{ !$curType ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $totalAll }}</span>
  </a>
  @foreach($types as $key => $label)
    <a href="{{ route('documents.index', array_merge(request()->except('type','page'), ['type' => $key])) }}"
       class="ds-chip {{ $curType === $key ? 'active' : '' }}">
      {{ $label }} <span class="ds-chip-count">{{ $typeCounts[$key] ?? 0 }}</span>
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
{{-- Figma 248:2923 — 흰 카드(r12 · pad 12/16) 안 9열 그리드, 버튼은 우측 하단.
     실측 폭: 검색어 295(2열) · 기간 295(2열 = 135+~+135) · 표시 건수 140(1열) --}}
<form method="GET" action="{{ route('documents.index') }}" class="ds-filter-card">
  @if($curType)
    <input type="hidden" name="type" value="{{ $curType }}">
  @endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="파일명 · 환자명">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">표시 건수</label>
      <select name="per_page" class="form-control form-select" onchange="this.form.submit()">
        @foreach([10,20,50,100] as $n)
          <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('date_from') || request('date_to'))
      <a href="{{ route('documents.index', array_filter(['type' => $curType])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

{{-- Figma 248:2923 — 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 탭바·그리드·등록 패널 --}}
<div class="ds-grid-section">
  {{-- 좌: 전체·선택 건수 / 우: 안내문 + 액션(그리드 내장 툴바에서 옮긴 엑셀 저장) --}}
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
      <span class="ds-grid-sel">선택 <b id="docSelCount">0</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint">행을 <b>클릭</b>하면 해당 처방전의 모든 서류를 확인하고 추가 등록할 수 있습니다.</span>
      <button type="button" class="ds-btn" onclick="window.__documentGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>

  <div class="ds-grid-card">
    {{-- ── 패널 탭: 조회 결과 / 서류 등록 — 시안은 카드 안 상단(h44) ── --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">
        <i class="fa-solid fa-list"></i> 조회 결과
      </button>
      <button type="button" id="pnlBtnReg" class="pnl-tab" onclick="pnlShow('reg')">
        <i class="fa-solid fa-folder-plus"></i> 서류 등록
        <span id="pnlRegRx" style="font-family:monospace;font-size:11px;color:var(--gray-500);"></span>
      </button>
    </div>

    <div id="pnlList">
      <div id="documentGrid"></div>
    </div>

{{-- ── 서류 등록 탭 — 같은 카드 안 ── --}}
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
      <a id="regRxLink" class="ds-btn" href="#" style="margin-left:auto;"
         onclick="return openRxTab(event)">
        <i class="bx bx-link-external"></i> 처방전 검수 화면
      </a>
      <button type="button" class="ds-btn" onclick="pnlShow('list')">
        <i class="bx bx-arrow-back"></i> 조회결과로
      </button>
    </div>

    <div class="reg-grid">
      {{-- 좌: 해당 처방전의 모든 서류 --}}
      <div class="reg-card">
        <h4><i class="bx bx-folder-open"></i> 이 처방전의 모든 서류 <span class="n" id="regDocCount"></span></h4>
        <div id="regDocList"></div>

        <h4 class="reg-h4-2"><i class="bx bx-paperclip"></i> 첨부 서류 <span class="n" id="regAttCount"></span></h4>
        <div id="regAttList"></div>
      </div>

      {{-- 우: 서류 등록 --}}
      @perm('documents', 'create')
      <div class="reg-card">
        {{-- 시안(248:3355)은 제목줄 오른쪽 끝에 '등록' 버튼이 붙는다.
             submit 이 계속 동작하도록 <form> 을 제목줄까지 감싸고 버튼은 그 안에 둔다. --}}
        <form id="regForm" onsubmit="return regSubmit(event)">
          <h4>
            <i class="bx bx-upload"></i> 서류 등록
            <span class="reg-hint reg-pill">PDF · JPG · PNG · HEIC, 최대 50MB</span>
            <button type="submit" id="regSubmitBtn" class="btn btn-primary btn-sm" style="margin-left:auto;">
              <i class="bx bx-upload"></i> 등록
            </button>
          </h4>
          <div class="reg-field">
            <label>서류 유형</label>
            <select id="regType" class="form-control form-select" required>
              @foreach($types as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="reg-field">
            <label>파일</label>
            <input type="file" id="regFile" accept=".pdf,.jpg,.jpeg,.png,.heic" required>
          </div>
          <div class="reg-hint reg-hint-foot">
            등록한 서류는 <b>서류 관리 목록</b>과 유형별 건수에 즉시 반영되며,
            생성유형은 <b>직접 등록</b>으로 표시됩니다.
          </div>
        </form>
      </div>
      @endperm
    </div>
  </div>
</div>{{-- /#pnlReg --}}
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

@endsection

@push('scripts')
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('documentGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 상단 결과바에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
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
  // 결과바의 '엑셀 저장' 버튼이 부를 수 있게 인스턴스를 노출한다(그리드 내장 툴바 대체).
  window.__documentGrid  = grid;
  window.__documentsGrid = grid;   // 표준 이름(화면 slug) 별칭
  window.dsBindSelCount(grid, 'docSelCount');

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
      window.ceOpenTab(url, '처방전 관리 - ' + (_regRxNo || '신규'), 'file-edit-02');
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
    document.querySelectorAll('.ds-chip').forEach(function (tab) {
      const href = tab.getAttribute('href') || '';
      const isAll = !/[?&]type=/.test(href);
      if (!isAll && !href.includes('type=' + doc.type)) return;
      let cnt = tab.querySelector('.ds-chip-count');
      if (!cnt) {
        cnt = document.createElement('span');
        cnt.className = 'ds-chip-count';
        cnt.textContent = '0';
        tab.appendChild(cnt);
      }
      cnt.textContent = String((parseInt(cnt.textContent, 10) || 0) + 1);
    });
  }
})();
</script>
@endpush
