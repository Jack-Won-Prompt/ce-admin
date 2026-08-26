{{-- resources/views/user-logs/index.blade.php --}}
@extends('layouts.app')

@section('title', '사용자 로그')
@section('page-title', '사용자 로그')
@section('breadcrumb', '홈 - 시스템 - 사용자 로그')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '검색 필터', body: '사용자, 활동유형, 날짜 범위로 로그를 조회합니다.' },
  { selector: '#logGrid', title: '활동 로그 목록', body: '로그인 시각, 사용자, IP 주소, 실행 메뉴명을 확인합니다.' },
];
</script>
@endpush

@section('help-title', '사용자 로그 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>모든 사용자의 로그인 이력과 메뉴 접근 기록을 조회합니다. 관리자(admin@ce-admin.co.kr)만 열람 가능합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">활동 유형</div>
  <div class="help-badge-row">
    <span class="badge badge-success">로그인</span>
    <span class="badge badge-primary">페이지 방문</span>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* .stat-row · .stat-chip · .filter-bar 는 레이아웃의
     ds-chips / ds-chip / ds-chip-count / ds-filter-card 로 대체됨.
     아래 규칙들은 wwGrid 이전 <table> 시절 자산이라 마크업 사용처는 없지만 남겨 둔다. */
  .log-table-wrap { overflow-x:auto; }
  .log-table-wrap thead th { position:sticky; top:0; z-index:5; background:var(--bg); }
  /* 배지 규격 — r6 · pad 2/6 · 11px/500 · lh18 (그대로 규격에 맞아 손대지 않았다) */
  .type-badge-login { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; background:var(--primary-light); color:var(--primary); }
  .type-badge-page  { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; background:var(--primary-light); color:var(--primary); }
  /* 서체는 Pretendard 하나다 — IP 자릿수 정렬은 monospace 대신 tabular-nums 로 낸다 */
  .ip-cell  { font-variant-numeric:tabular-nums; font-size:12px; font-weight:500; line-height:19px; color:var(--text-secondary); }
  .url-cell { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; font-weight:500; line-height:18px; color:var(--text-muted); }
  .ua-cell  { max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; font-weight:500; line-height:18px; color:var(--text-muted); cursor:help; }

  /* 통계 칩은 링크가 아니라 표시 전용이다 — 커서와 hover 색만 죽인다 */
  .ds-chip.is-static { cursor:default; }
  .ds-chip.is-static:hover { color:var(--gray-500); }
</style>
@endpush

@section('content')

{{-- ── 통계 칩 ── --}}
@php
  $totalCount  = $total;
  $loginCount  = \App\Models\UserActivityLog::where('type','login')->count();
  $pageCount   = \App\Models\UserActivityLog::where('type','page')->count();
  $userCount   = \App\Models\UserActivityLog::distinct('user_id')->count('user_id');
@endphp
{{-- 칩 규격 — h31 · r999 · pad 6/10 · 12px/700, 건수 배지 16×16 정원 · 10px/700 --}}
<div class="ds-chips">
  <span class="ds-chip is-static">
    <i class="bx bx-list-check" style="font-size:16px;"></i>
    전체 로그 <span class="ds-chip-count">{{ number_format($loginCount + $pageCount) }}</span>
  </span>
  <span class="ds-chip is-static">
    <i class="bx bx-log-in-circle" style="font-size:16px;"></i>
    로그인 <span class="ds-chip-count">{{ number_format($loginCount) }}</span>
  </span>
  <span class="ds-chip is-static">
    <i class="bx bx-window-alt" style="font-size:16px;"></i>
    페이지 방문 <span class="ds-chip-count">{{ number_format($pageCount) }}</span>
  </span>
  <span class="ds-chip is-static">
    <i class="bx bx-group" style="font-size:16px;"></i>
    활동 사용자 <span class="ds-chip-count">{{ number_format($userCount) }}</span>
  </span>
</div>

{{-- ── 검색 필터 — 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래, 9열 그리드 ── --}}
<form method="GET" action="{{ route('user-logs.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">사용자</label>
      <select name="user_id" class="form-control form-select">
        <option value="">전체 사용자</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
            {{ $u->name }} ({{ $u->email }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">활동 유형</label>
      <select name="type" class="form-control form-select">
        <option value="">전체 유형</option>
        <option value="login" {{ request('type') === 'login' ? 'selected' : '' }}>로그인</option>
        <option value="page"  {{ request('type') === 'page'  ? 'selected' : '' }}>페이지 방문</option>
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" placeholder="시작일">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="form-control" placeholder="종료일">
      </div>
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="사용자명ㆍIPㆍ메뉴명">
    </div>
    {{-- 표시 건수는 컨트롤러가 아직 읽지 않지만 개발 자산이라 그대로 둔다 --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">표시 건수</label>
      <select name="per_page" class="form-control form-select" onchange="this.form.submit()">
        @foreach([20,50,100] as $n)
          <option value="{{ $n }}" {{ request('per_page',20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request()->hasAny(['user_id','type','date_from','date_to','q']))
      <a href="{{ route('user-logs.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">
      <i class="fa-solid fa-magnifying-glass"></i> 검색
    </button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__userlogsGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

{{-- ── 흰 카드(r12) 안에 그리드 ── --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
    <div id="logGrid"></div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('logGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — 전체 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '일시',      name: 'created', width: 150, sortable: true },
      { header: '유형',      name: 'type',    width: 80,  sortable: true, align: 'center' },
      { header: '사용자',    name: 'user',    width: 110, sortable: true },
      { header: '이메일',    name: 'email',   width: 180 },
      { header: 'IP 주소',   name: 'ip',      width: 120 },
      { header: '실행 메뉴', name: 'menu',    width: 160, sortable: true },
      { header: '라우트',    name: 'route',   width: 140 },
      { header: 'URL',       name: 'url',     width: 220 },
      { header: '브라우저',  name: 'ua',      width: 200 },
    ],
    data: @json($gridData),
  });
  // 결과바의 '엑셀 저장' 버튼이 이 인스턴스를 부른다.
  // 체크박스가 없는 그리드라 '선택 N건'은 두지 않았고 dsBindSelCount 도 부르지 않는다.
  window.__userlogsGrid = grid;
})();
</script>
@endpush
