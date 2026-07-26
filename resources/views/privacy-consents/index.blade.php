@extends('layouts.app')

@section('title', '개인정보동의')
@section('page-title', '개인정보 수집·이용 동의')
@section('breadcrumb', '홈 / 개인정보동의')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=3">
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
</style>
@endpush

@section('content')
<div class="pc-tabs">
  @php $mk = fn($t)=>request('type',($t==='all'?'all':null))===$t || (request('type')===null && $t==='all'); @endphp
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

{{-- ── wwGrid 파일럿 ── --}}
<div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
  <button type="button" class="btn btn-outline btn-sm" onclick="pcViewDetail()">
    <i class="bx bx-detail"></i> 선택 행 상세보기
  </button>
  <span style="font-size:12px;color:var(--text-muted);">← 행을 체크한 뒤 눌러 상세를 엽니다.</span>
</div>
<div id="pcGrid"></div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=3"></script>
<script>
(function () {
  const DETAIL_BASE = @json(url('privacy-consents'));   // + '/{id}'
  const grid = new wwGrid({
    el: document.getElementById('pcGrid'),
    height: 'fit',
    editable: false,        // 읽기전용 표시
    rowCheckbox: true,      // 상세보기 선택용
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

  window.pcViewDetail = function () {
    const checked = grid.getCheckedRows();
    if (!checked.length)     { showToast('상세를 볼 행을 먼저 체크하세요.', 'warning'); return; }
    if (checked.length > 1)  { showToast('한 행만 선택하세요.', 'warning'); return; }
    window.location.href = DETAIL_BASE + '/' + checked[0].id;
  };
})();
</script>
@endpush
