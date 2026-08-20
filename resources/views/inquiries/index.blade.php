@extends('layouts.app')

@section('title', '문의하기')
@section('page-title', '문의하기')
@section('breadcrumb', '홈 - 문의하기')

@push('styles')
@endpush

@section('content')

{{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 13/500 위 · 입력 h32 아래 --}}
<form method="GET" action="{{ route('inquiries.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    {{-- 1열(1280 뷰포트에서 78.5px · 안쪽 34.5px)이면 '답변 대기'(약 51px)가 잘린다 — 2열로 준다 --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select">
        <option value="">전체 상태</option>
        <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>답변 대기</option>
        <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>답변 완료</option>
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">분류</label>
      <select name="category" class="form-control form-select">
        <option value="">전체 분류</option>
        <option value="general"   {{ request('category') === 'general'   ? 'selected' : '' }}>일반</option>
        <option value="technical" {{ request('category') === 'technical' ? 'selected' : '' }}>기술</option>
        <option value="billing"   {{ request('category') === 'billing'   ? 'selected' : '' }}>청구/결제</option>
        <option value="other"     {{ request('category') === 'other'     ? 'selected' : '' }}>기타</option>
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    {{-- 시안은 초기화를 검색 왼쪽에 늘 세워 둔다(캐시 42장 전수) — 조건을 걷어낸다.
         같은 라우트로 되돌아가는 링크라 조건이 없어도 하는 일이 같다. --}}
    <a href="{{ route('inquiries.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-search"></i> 검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="inquiryViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <button type="button" class="ds-btn" onclick="window.__inquiryGrid?.downloadExcel()">엑셀 저장</button>
    <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">
      <i class="bx bx-pencil"></i> 문의 작성
    </a>
  </div>
</form>

{{-- ── 목록 (wwGrid) — 결과바(h32) 위, 흰 카드(r12) 안에 그리드 ── --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
    <div id="inquiryGrid"></div>
  </div>
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
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: columns,
    data: @json($gridData),
  });
  window.__inquiryGrid = grid;                      // 결과바의 엑셀 저장 버튼이 이걸 부른다
  window.dsBindSelCount(grid, 'inquirySelCount');   // 결과바 '선택 N건' 표시를 연결한다
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
