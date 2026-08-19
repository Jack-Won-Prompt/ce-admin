@extends('layouts.app')

@section('title', '공지사항')
@section('page-title', '공지사항')
@section('breadcrumb', '홈 - 공지사항')

@push('styles')
@endpush

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#noticeGrid', title: '공지사항 목록', body: '관리자가 올린 공지사항 목록입니다. 제목을 클릭하면 내용을 확인할 수 있습니다.' },
  // 범위를 결과바로 좁힌다 — 전역 선택자였을 때 사이드바의 '처방전 관리'(/prescriptions/create)가 먼저 잡혔다
  { selector: '.ds-grid-bar .btn-primary, .ds-grid-bar [href*="create"]', title: '공지 작성', body: '관리자는 <b>공지 작성</b> 버튼으로 새 공지사항을 등록할 수 있습니다.' },
];
</script>
@endpush

@section('content')

{{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 13/500 위 · 입력 h32 아래 --}}
<form method="GET" action="{{ route('notices.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 또는 내용 검색...">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('search'))
      <a href="{{ route('notices.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-search"></i> 검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="noticeViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <button type="button" class="ds-btn" onclick="window.__noticeGrid?.downloadExcel()">엑셀 저장</button>
    @if(Auth::user()->role === 'admin')
    <a href="{{ route('notices.create') }}" class="btn btn-primary btn-sm">
      <i class="bx bx-plus"></i> 공지 등록
    </a>
    @endif
  </div>
</form>

{{-- ── 목록 (wwGrid) — 결과바(h32) 위, 흰 카드(r12) 안에 그리드 ── --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;">조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
    <div id="noticeGrid"></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const DETAIL_BASE = @json(url('notices'));
  const grid = new wwGrid({
    el: document.getElementById('noticeGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: [
      { header: '구분',   name: 'gubun',   width: 70,  align: 'center', sortable: true },
      { header: '번호',   name: 'no',      width: 70,  align: 'center', sortable: true },
      { header: '제목',   name: 'title',   width: 360, sortable: true },
      { header: '작성자', name: 'author',  width: 100, align: 'center', sortable: true },
      { header: '날짜',   name: 'created', width: 110, align: 'center', sortable: true },
      { header: '조회',   name: 'views',   width: 80,  align: 'right', editor: 'number', sortable: true },
    ],
    data: @json($gridData),
  });
  window.__noticeGrid = grid;                      // 결과바의 엑셀 저장 버튼이 이걸 부른다
  window.dsBindSelCount(grid, 'noticeSelCount');   // 결과바 '선택 N건' 표시를 연결한다
  window.noticeViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    // 목록 탭을 그대로 두고 상세를 새 탭으로 (워크스페이스 밖이면 브라우저 새 탭)
    ceOpenTab(DETAIL_BASE + '/' + c[0].id, '공지사항 - ' + c[0].id, 'notification-calendar');
  };
})();
</script>
@endpush
