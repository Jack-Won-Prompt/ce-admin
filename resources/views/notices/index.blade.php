@extends('layouts.app')

@section('title', '공지사항')
@section('page-title', '공지사항')
@section('breadcrumb', '홈 / 공지사항')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=6">
@endpush

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.card-header', title: '공지사항 목록', body: '관리자가 올린 공지사항 목록입니다. 제목을 클릭하면 내용을 확인할 수 있습니다.' },
  { selector: '.btn-primary, [href*="create"]', title: '공지 작성', body: '관리자는 <b>공지 작성</b> 버튼으로 새 공지사항을 등록할 수 있습니다.' },
];
</script>
@endpush

@section('content')
<div style="max-width:900px;">

  {{-- 헤더 액션 --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
      <div style="position:relative;flex:1;max-width:320px;">
        <i class="bx bx-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 또는 내용 검색..." style="padding-left:34px;">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="bx bx-search"></i> 검색</button>
      @if(request('search'))
        <a href="{{ route('notices.index') }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
    {{-- NoticeController 가 서버측에서 admin 을 강제하므로 기존 role 게이트를 유지한다.
         (권한 그룹으로 매니저에게 열어 주려면 컨트롤러의 admin 체크부터 걷어내야 함) --}}
    @if(Auth::user()->role === 'admin')
      <a href="{{ route('notices.create') }}" class="btn btn-primary btn-sm">
        <i class="bx bx-plus"></i> 공지 등록
      </a>
    @endif
  </div>

  {{-- ── 목록 (wwGrid) ── --}}
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
    <button type="button" class="btn btn-outline btn-sm" onclick="noticeViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 상세로 이동</span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="noticeGrid"></div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=6"></script>
<script>
(function () {
  const DETAIL_BASE = @json(url('notices'));
  const grid = new wwGrid({
    el: document.getElementById('noticeGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
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
  window.noticeViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    // 목록 탭을 그대로 두고 상세를 새 탭으로 (워크스페이스 밖이면 브라우저 새 탭)
    ceOpenTab(DETAIL_BASE + '/' + c[0].id, '공지 상세', 'bx-bell');
  };
})();
</script>
@endpush
