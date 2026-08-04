@extends('layouts.app')

@section('title', '문의하기')
@section('page-title', '문의하기')
@section('breadcrumb', '홈 / 문의하기')

@push('styles')
@endpush

@section('content')
<div style="max-width:960px;">

  {{-- 상단 액션 --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <select name="status" class="form-control form-select" style="width:120px;">
        <option value="">전체 상태</option>
        <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>답변 대기</option>
        <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>답변 완료</option>
      </select>
      <select name="category" class="form-control form-select" style="width:130px;">
        <option value="">전체 분류</option>
        <option value="general"   {{ request('category') === 'general'   ? 'selected' : '' }}>일반</option>
        <option value="technical" {{ request('category') === 'technical' ? 'selected' : '' }}>기술</option>
        <option value="billing"   {{ request('category') === 'billing'   ? 'selected' : '' }}>청구/결제</option>
        <option value="other"     {{ request('category') === 'other'     ? 'selected' : '' }}>기타</option>
      </select>
      <div style="position:relative;">
        <i class="bx bx-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 검색..." style="padding-left:34px;max-width:220px;">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="bx bx-search"></i> 검색</button>
      @if(request('status') || request('category') || request('search'))
        <a href="{{ route('inquiries.index') }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
    <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">
      <i class="bx bx-pencil"></i> 문의 작성
    </a>
  </div>

  {{-- ── 목록 (wwGrid) ── --}}
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
    <button type="button" class="btn btn-outline btn-sm" onclick="inquiryViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 상세로 이동</span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="inquiryGrid"></div>

</div>
@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#inquiryGrid', title: '문의 목록', body: '접수된 문의 목록입니다. 행을 체크한 뒤 <b>선택 상세</b> 버튼으로 상세 내용과 답변을 확인할 수 있습니다.' },
  { selector: '.btn-primary, [onclick*="Create"], [onclick*="create"]', title: '문의 작성', body: '<b>문의 작성</b> 버튼으로 새 문의를 등록합니다. 답변은 이메일 또는 이 화면에서 확인할 수 있습니다.' },
];
</script>
<script>
(function () {
  const DETAIL_BASE = @json(url('inquiries'));
  const columns = [
    { header: '분류',   name: 'category', width: 100, align: 'center', sortable: true },
    { header: '제목',   name: 'title',    width: 360, sortable: true },
    @if(Auth::user()->role === 'admin')
    { header: '작성자', name: 'user',     width: 110, align: 'center', sortable: true },
    @endif
    { header: '상태',   name: 'status',   width: 90,  align: 'center', sortable: true },
    { header: '작성일', name: 'date',     width: 110, align: 'center', sortable: true },
  ];
  const grid = new wwGrid({
    el: document.getElementById('inquiryGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: columns,
    data: @json($gridData),
  });
  window.inquiryViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    // 목록 탭을 그대로 두고 상세를 새 탭으로 (워크스페이스 밖이면 브라우저 새 탭)
    ceOpenTab(DETAIL_BASE + '/' + c[0].id, '문의하기 - ' + c[0].id, 'bubble-chat-edit');
  };
})();
</script>
@endpush
