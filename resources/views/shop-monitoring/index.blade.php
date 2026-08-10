@extends('layouts.app')

@section('title', 'CE샵 모니터링')
@section('page-title', 'CE샵 사용자 모니터링')
@section('breadcrumb', '홈 / CE샵 모니터링')

@push('styles')
<style>
  .mono { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; }
  .section-divider { height: 1px; background: var(--border); margin: 22px 0; }
  /* 전역 .status-dot.online::before (app.blade.php:967) 가 --success 초록 점(7×7)과
     초록 링을 이 span 위에 덧그린다. 아래 인라인 background 만 primary 로 바꾸면
     화면에는 초록이 그대로 남는다 — pseudo 까지 같은 램프로 맞춘다.
     링은 아래 span 이 이미 primary-light 로 그리고 있어 pseudo 쪽은 none 으로 지운다. */
  .page-body .status-dot.online::before { background: var(--primary); box-shadow: none; }
</style>
@endpush

@section('content')

{{-- 요약 카드 — 블록 사이 간격은 .page-body 의 gap 12 가 만든다(margin 중복 제거) --}}
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon primary"><i class="bx bx-group"></i></div>
    <div class="stat-info">
      <div class="stat-val">{{ $sessions->count() }}</div>
      <div class="stat-label">전체 접속자</div>
    </div>
  </div>
  {{-- 초록(--success)·파랑(--info)은 시안에 없다. 강조는 primary 램프 하나로만 표현한다 --}}
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon primary"><i class="bx bx-wifi"></i></div>
    <div class="stat-info">
      <div class="stat-val" style="color:var(--primary);">{{ $sessions->where('online', true)->count() }}</div>
      <div class="stat-label">현재 온라인 <span style="font-size:10px;font-weight:500;color:var(--gray-600);">(5분 이내)</span></div>
    </div>
  </div>
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon primary"><i class="bx bx-show"></i></div>
    <div class="stat-info">
      <div class="stat-val" style="color:var(--primary);">{{ $todayCount }}</div>
      <div class="stat-label">오늘 상품 조회</div>
    </div>
  </div>
</div>

{{-- 사용자 로그인 현황 --}}
<div class="card">
  <div class="card-header">
    {{-- 접속 표시 점 — 초록(--success)은 시안에 없어 primary 램프로 옮겼다. r999 --}}
    <span class="status-dot online" style="width:8px;height:8px;min-width:8px;flex-shrink:0;border-radius:999px;background:var(--primary);box-shadow:0 0 0 2px var(--primary-light);margin-right:6px;"></span>
    <span class="card-header-title">사용자 로그인 현황</span>
    <a href="{{ route('shop-monitoring.index') }}" class="btn btn-outline btn-sm ms-auto">
      <i class="bx bx-refresh"></i> 새로고침
    </a>
  </div>
  <div class="card-body" style="padding:12px 16px;">
    <div id="loginStatusGrid"></div>
  </div>
</div>

{{-- 상품 조회 로그 --}}
<div class="card">
  <div class="card-header">
    <i class="bx bx-list-check" style="font-size:16px;color:var(--primary);"></i>
    <span class="card-header-title">상품 조회 로그</span>
    <span class="card-header-sub">(최근 50건)</span>
    <form method="GET" action="{{ route('shop-monitoring.index') }}" style="display:flex;gap:8px;margin-left:auto;">
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input name="search" value="{{ request('search') }}" placeholder="사용자·상품 검색..."
               class="form-control" style="width:200px;">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">검색</button>
      @if(request('search'))
        <a href="{{ route('shop-monitoring.index') }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
  </div>
  <div class="card-body" style="padding:12px 16px;">
    {{-- 안내문 규격 — 12px/500 · gray-600 --}}
    <div style="margin-bottom:8px;font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);">
      <i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 해당 사용자 로그만 필터링합니다.
    </div>
    <div id="productLogGrid"></div>
  </div>
  @if($logs->hasPages())
  <div class="card-footer" style="display:flex;justify-content:flex-end;">
    {{ $logs->links() }}
  </div>
  @endif
</div>

@endsection

@push('scripts')
<script>
(function () {
  // ── 사용자 로그인 현황 (읽기 전용) ──
  const loginEl = document.getElementById('loginStatusGrid');
  if (loginEl) {
    new wwGrid({
      el: loginEl,
      height: 360, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
      footer: { total: true, selected: false, modified: false },
      columns: [
        { header: '상태',        name: 'status',        width: 80,  align: 'center', sortable: true },
        { header: '이름',        name: 'name',          width: 110, sortable: true },
        { header: '이메일',      name: 'email',         width: 200, sortable: true },
        { header: '권한',        name: 'role',          width: 100, align: 'center', sortable: true },
        { header: '마지막 로그인', name: 'last_login',    width: 110, align: 'center' },
        { header: '마지막 활동',  name: 'last_activity', width: 130, align: 'center' },
        { header: '로그아웃',     name: 'last_logout',   width: 110, align: 'center' },
        { header: 'IP',          name: 'ip',            width: 120 },
      ],
      data: @json($loginStatusGrid ?? []),
    });
  }

  // ── 상품 조회 로그 (읽기 전용, 더블클릭 시 사용자 필터) ──
  const logEl = document.getElementById('productLogGrid');
  if (logEl) {
    const LOG_BASE = @json(route('shop-monitoring.index'));
    const logGrid = new wwGrid({
      el: logEl,
      height: 360, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
      footer: { total: true, selected: false, modified: false },
      columns: [
        { header: '날짜',     name: 'log_date',     width: 110, align: 'center', sortable: true },
        { header: '이름',     name: 'name',         width: 120, sortable: true },
        { header: '이메일',   name: 'email',        width: 200, sortable: true },
        { header: '상품명',   name: 'product_name', width: 220, sortable: true },
        { header: '상품 ID',  name: 'product_id',   width: 100, align: 'center' },
      ],
      data: @json($productLogGrid ?? []),
    });
    // 더블클릭 → 해당 사용자 로그만 필터
    logEl.addEventListener('dblclick', function (e) {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = logGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row && row.shop_user_id) {
        window.location.href = LOG_BASE + '?user_id=' + encodeURIComponent(row.shop_user_id);
      }
    });
  }
})();
</script>
@endpush
