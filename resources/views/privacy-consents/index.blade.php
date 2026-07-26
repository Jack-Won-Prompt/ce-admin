@extends('layouts.app')

@section('title', '개인정보동의')
@section('page-title', '개인정보 수집·이용 동의')
@section('breadcrumb', '홈 / 개인정보동의')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=4">
<style>
  .pc-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
  .pc-tab { padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
    border:1.5px solid var(--border); background:#fff; color:var(--text-secondary);
    text-decoration:none; transition:var(--transition); }
  .pc-tab:hover { border-color:var(--primary); color:var(--primary); }
  .pc-tab.active { border-color:var(--primary); background:var(--primary); color:#fff; }
  .pc-tab .cnt { opacity:.75; margin-left:4px; font-weight:700; }
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:end; margin-bottom:16px;
    background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:14px; }
  .filter-bar .fg { display:flex; flex-direction:column; gap:4px; }
  .filter-bar label { font-size:11px; font-weight:700; color:var(--text-muted); }
  .filter-bar input { padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:13px; }
  .badge-type { display:inline-block; padding:2px 9px; border-radius:12px; font-size:11px; font-weight:700; }
  .badge-type.catheter { background:#e0f2fe; color:#0369a1; }
  .badge-type.stoma { background:#f0fdf4; color:#15803d; }
  .pc-table { width:100%; border-collapse:collapse; background:#fff; }
  .pc-table th, .pc-table td { padding:11px 12px; border-bottom:1px solid var(--border); font-size:13px; text-align:left; }
  .pc-table th { background:#f8fafb; font-weight:700; color:var(--text-secondary); font-size:12px; }
  .pc-table tr:hover td { background:#fbfcfe; }
  .req-ok { color:var(--success); font-weight:700; }
  .req-no { color:var(--danger); font-weight:700; }
  /* 뷰 전환 탭(리스트/상세보기) */
  .pc-vtabs { display:flex; gap:4px; margin-bottom:16px; border-bottom:2px solid var(--border); }
  .pc-vtab { padding:9px 20px; font-size:13.5px; font-weight:700; border:none; background:none; cursor:pointer;
    color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-2px; display:inline-flex; align-items:center; gap:6px; }
  .pc-vtab:hover { color:var(--primary); }
  .pc-vtab.active { color:var(--primary); border-bottom-color:var(--primary); }
  /* 상세보기 카드 */
  .detail-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
  .detail-card h3 { margin:0 0 14px; font-size:14px; font-weight:800; color:var(--primary);
    padding-bottom:10px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .drow { display:grid; grid-template-columns:140px 1fr; gap:10px; padding:9px 0; border-bottom:1px solid #f1f4f8; font-size:13.5px; }
  .drow:last-child { border-bottom:none; }
  .drow .k { color:var(--text-muted); font-weight:700; }
  .drow .v { color:var(--text-primary); }
  .agree-yes { color:var(--success); font-weight:700; }
  .agree-no { color:var(--danger); font-weight:700; }
  .pc-detail-empty { color:var(--text-muted); font-size:13.5px; text-align:center; padding:60px 20px;
    background:#fff; border:1px dashed var(--border); border-radius:var(--radius); }
</style>
@endpush

@section('content')
{{-- 유형 필터 탭 + 검색 필터 (항상 표시) --}}
<div class="pc-tabs">
  <a href="{{ route('privacy-consents.index') }}" class="pc-tab {{ (request('type','all')==='all')?'active':'' }}">전체 <span class="cnt">{{ $counts['all'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'catheter']) }}" class="pc-tab {{ request('type')==='catheter'?'active':'' }}">카테터 <span class="cnt">{{ $counts['catheter'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'stoma']) }}" class="pc-tab {{ request('type')==='stoma'?'active':'' }}">장루 <span class="cnt">{{ $counts['stoma'] }}</span></a>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="type" value="{{ request('type','all') }}">
  <div class="fg"><label>검색 (성명/연락처/이메일)</label><input type="text" name="search" value="{{ $search }}" placeholder="검색어"></div>
  <div class="fg"><label>시작일</label><input type="date" name="from" value="{{ $from }}"></div>
  <div class="fg"><label>종료일</label><input type="date" name="to" value="{{ $to }}"></div>
  <button type="submit" class="btn btn-primary btn-sm">조회</button>
  <a href="{{ route('privacy-consents.export', request()->query()) }}" class="btn btn-outline btn-sm">
    <i class="bx bx-download"></i> 엑셀(CSV) 다운로드
  </a>
</form>

{{-- 뷰 전환 탭: 조회결과 / 상세내용 (검색 필터 아래) --}}
<div class="pc-vtabs">
  <button type="button" id="pcTabBtnList" class="pc-vtab active" onclick="pcShowTab('list')">
    <i class="bx bx-list-ul"></i> 조회결과
  </button>
  <button type="button" id="pcTabBtnDetail" class="pc-vtab" onclick="pcShowTab('detail')">
    <i class="bx bx-detail"></i> 상세내용
  </button>
</div>

{{-- ── 조회결과 탭 ── --}}
<div id="pcTabList">
  <div style="margin-bottom:10px;font-size:12px;color:var(--text-muted);">
    <i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 상세내용 탭으로 전환되어 상세 내용을 확인합니다.
  </div>
  <div id="pcGrid"></div>
</div>

{{-- ── 상세보기 탭 ── --}}
<div id="pcTabDetail" style="display:none;">
  <div style="margin-bottom:16px;">
    <button type="button" class="btn btn-outline btn-sm" onclick="pcShowTab('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
  </div>
  <div id="pcDetailBody">
    <div class="pc-detail-empty">리스트 탭에서 행을 더블클릭하면 상세 내용이 여기에 표시됩니다.</div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('pcGrid'),
    height: 'fit',
    editable: false,        // 읽기전용 표시
    rowCheckbox: true,
    rowNumber: true,
    toolbar: true,          // wwGrid 엑셀 버튼
    summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '유형',     name: 'type',      width: 80,  sortable: true, align: 'center' },
      { header: '성명',     name: 'name',      width: 100, sortable: true },
      { header: '연락처',   name: 'phone',     width: 130 },
      { header: '이메일',   name: 'email',     width: 190 },
      { header: '주소',     name: 'address',   width: 260 },
      { header: '필수동의', name: 'required',  width: 80,  sortable: true, align: 'center' },
      { header: '마케팅',   name: 'marketing', width: 70,  align: 'center' },
      { header: '제출일시', name: 'submitted', width: 130, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__pcGrid = grid;

  // ── 탭 전환 ──
  window.pcShowTab = function (which) {
    document.getElementById('pcTabList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pcTabDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pcTabBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pcTabBtnDetail').classList.toggle('active', which === 'detail');
  };

  // ── 더블클릭 → 상세보기 탭 전환 + 상세 렌더 (페이지 이동 없음) ──
  document.getElementById('pcGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const ri  = parseInt(cell.dataset.rowIndex, 10);
    const row = grid.getData()[ri];
    if (!row) return;
    document.getElementById('pcDetailBody').innerHTML = pcRenderDetail(row);
    window.pcShowTab('detail');
  });

  // ── 상세 HTML 생성(기존 상세 화면 내용을 인페이지로) ──
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }
  function val(v) { return (v === '' || v == null) ? '-' : esc(v); }
  function agree(v) {
    if (v === '동의함') return '<span class="agree-yes">동의함</span>';
    if (v)            return '<span class="agree-no">' + esc(v) + '</span>';
    return '<span style="color:#94a3b8;">-</span>';
  }
  function drow(k, vHtml) { return '<div class="drow"><span class="k">' + k + '</span><span class="v">' + vHtml + '</span></div>'; }
  const REQ = ' <span style="font-size:11px;color:#e74c3c;">(필수)</span>';
  const OPT = ' <span style="font-size:11px;color:#94a3b8;">(선택)</span>';

  function pcRenderDetail(r) {
    const isStoma = r.type_raw === 'stoma';

    // 신청자 정보
    let applicant =
      drow('성명', val(r.name)) +
      drow('연락처', val(r.phone)) +
      (r.phone2 ? drow('연락처2', esc(r.phone2)) : '') +
      drow('이메일', val(r.email)) +
      drow('주소', val(r.address));
    if (isStoma) {
      const stoma = [r.stoma_type, r.stoma_kind].filter(Boolean).join(' ');
      applicant +=
        drow('생년월일', val(r.birth)) +
        drow('사용 제품', val(r.product)) +
        drow('수술 병원', val(r.hospital)) +
        drow('수술일자', val(r.surgery_date)) +
        drow('장루 타입', stoma ? esc(stoma) : '-');
    } else {
      applicant +=
        drow('보험', val(r.insurance)) +
        drow('지원 자격', val(r.support_qualify));
    }

    // 동의 내역
    let consent = drow('일반정보 수집·이용', agree(r.agree_general) + REQ);
    if (isStoma) {
      consent +=
        drow('민감정보 수집·이용', agree(r.agree_sensitive) + REQ) +
        drow('일반 마케팅', agree(r.agree_marketing) + OPT) +
        drow('민감정보 마케팅', agree(r.agree_marketing_sensitive) + OPT) +
        drow('일반 제3자 제공', agree(r.agree_third_party) + OPT) +
        drow('민감 제3자 제공', agree(r.agree_third_sensitive) + OPT);
    } else {
      consent +=
        drow('제3자 제공', agree(r.agree_third_party) + REQ) +
        drow('마케팅 수집·이용', agree(r.agree_marketing) + OPT);
    }

    // 제출 정보
    const submit =
      drow('제출일시', val(r.submitted_full || r.submitted)) +
      drow('IP', val(r.ip)) +
      drow('User-Agent', '<span style="font-size:11px;color:var(--text-muted);word-break:break-all;">' + val(r.user_agent) + '</span>');

    return '' +
      '<div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">' +
        '<span class="badge-type ' + (isStoma ? 'stoma' : 'catheter') + '">' + esc(r.type) + '</span>' +
        '<span style="font-weight:800;font-size:16px;">' + esc(r.name) + '</span>' +
      '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-user"></i> 신청자 정보</h3>' + applicant + '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-check-shield"></i> 개인정보 수집·이용 동의</h3>' + consent + '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-info-circle"></i> 제출 정보</h3>' + submit + '</div>';
  }
})();
</script>
@endpush
