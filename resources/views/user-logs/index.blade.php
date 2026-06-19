{{-- resources/views/user-logs/index.blade.php --}}
@extends('layouts.app')

@section('title', '사용자 로그')
@section('page-title', '사용자 로그')
@section('breadcrumb', '홈 / 시스템 / 사용자 로그')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar', title: '검색 필터', body: '사용자, 활동유형, 날짜 범위로 로그를 조회합니다.' },
  { selector: '.log-table-wrap', title: '활동 로그 목록', body: '로그인 시각, 사용자, IP 주소, 실행 메뉴명을 확인합니다.' },
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
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
  .log-table-wrap { overflow-x:auto; }
  .log-table-wrap thead th { position:sticky; top:0; z-index:5; background:var(--bg); }
  .type-badge-login { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; background:var(--success-light); color:var(--success); }
  .type-badge-page  { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; background:var(--primary-light); color:var(--primary); }
  .ip-cell  { font-family:monospace; font-size:12px; color:var(--text-secondary); }
  .url-cell { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; color:var(--text-muted); }
  .ua-cell  { max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px; color:var(--text-muted); cursor:help; }
  .stat-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
  .stat-chip { display:flex; align-items:center; gap:8px; padding:10px 16px; border-radius:var(--radius); background:#fff; border:1px solid var(--border); font-size:13px; }
  .stat-chip strong { font-size:18px; font-weight:700; color:var(--primary); }
</style>
@endpush

@section('content')

{{-- ── 통계 칩 ── --}}
@php
  $totalCount  = $logs->total();
  $loginCount  = \App\Models\UserActivityLog::where('type','login')->count();
  $pageCount   = \App\Models\UserActivityLog::where('type','page')->count();
  $userCount   = \App\Models\UserActivityLog::distinct('user_id')->count('user_id');
@endphp
<div class="stat-row">
  <div class="stat-chip">
    <i class="bx bx-list-check" style="font-size:20px;color:var(--primary);"></i>
    <div><div style="font-size:11px;color:var(--text-muted);">전체 로그</div><strong>{{ number_format($loginCount + $pageCount) }}</strong></div>
  </div>
  <div class="stat-chip">
    <i class="bx bx-log-in-circle" style="font-size:20px;color:var(--success);"></i>
    <div><div style="font-size:11px;color:var(--text-muted);">로그인</div><strong style="color:var(--success);">{{ number_format($loginCount) }}</strong></div>
  </div>
  <div class="stat-chip">
    <i class="bx bx-window-alt" style="font-size:20px;color:var(--info);"></i>
    <div><div style="font-size:11px;color:var(--text-muted);">페이지 방문</div><strong style="color:var(--info);">{{ number_format($pageCount) }}</strong></div>
  </div>
  <div class="stat-chip">
    <i class="bx bx-group" style="font-size:20px;color:var(--warning);"></i>
    <div><div style="font-size:11px;color:var(--text-muted);">활동 사용자</div><strong style="color:var(--warning);">{{ number_format($userCount) }}</strong></div>
  </div>
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('user-logs.index') }}" class="filter-bar">
  <select name="user_id" class="form-control" style="width:160px;">
    <option value="">전체 사용자</option>
    @foreach($users as $u)
      <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
        {{ $u->name }} ({{ $u->email }})
      </option>
    @endforeach
  </select>
  <select name="type" class="form-control" style="width:110px;">
    <option value="">전체 유형</option>
    <option value="login" {{ request('type') === 'login' ? 'selected' : '' }}>로그인</option>
    <option value="page"  {{ request('type') === 'page'  ? 'selected' : '' }}>페이지 방문</option>
  </select>
  <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:140px;" placeholder="시작일">
  <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="form-control" style="width:140px;" placeholder="종료일">
  <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="사용자명 · IP · 메뉴명" style="width:180px;">
  <select name="per_page" class="form-control" style="width:80px;" onchange="this.form.submit()">
    @foreach([20,50,100] as $n)
      <option value="{{ $n }}" {{ request('per_page',20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
    @endforeach
  </select>
  <button type="submit" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-magnifying-glass"></i> 검색
  </button>
  @if(request()->hasAny(['user_id','type','date_from','date_to','q']))
    <a href="{{ route('user-logs.index') }}" class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- ── 로그 테이블 ── --}}
<div class="card">
  <div class="card-header">
    <i class="bx bx-list-check" style="font-size:18px;color:var(--primary);"></i>
    <span class="card-header-title">활동 로그</span>
    <span class="badge bg-label-primary ms-auto">{{ number_format($logs->total()) }}건</span>
  </div>
  <div class="log-table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:150px;">일시</th>
          <th style="width:110px;">유형</th>
          <th style="width:130px;">사용자</th>
          <th style="width:120px;">IP 주소</th>
          <th>실행 메뉴</th>
          <th style="width:200px;">URL</th>
          <th style="width:160px;">브라우저</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
          <tr>
            <td style="font-size:12px;white-space:nowrap;color:var(--text-secondary);">
              {{ $log->created_at->format('Y-m-d H:i:s') }}
            </td>
            <td>
              @if($log->type === 'login')
                <span class="type-badge-login"><i class="bx bx-log-in-circle"></i>로그인</span>
              @else
                <span class="type-badge-page"><i class="bx bx-window-alt"></i>방문</span>
              @endif
            </td>
            <td>
              <div style="font-weight:600;font-size:13px;">{{ $log->user?->name ?? '-' }}</div>
              <div style="font-size:11px;color:var(--text-muted);">{{ $log->user?->email ?? '' }}</div>
            </td>
            <td class="ip-cell">{{ $log->ip_address ?? '-' }}</td>
            <td>
              <span style="font-weight:600;font-size:13px;">{{ $log->menu_name ?? '-' }}</span>
              @if($log->route_name && $log->route_name !== $log->menu_name)
                <div style="font-size:10px;color:var(--text-muted);font-family:monospace;">{{ $log->route_name }}</div>
              @endif
            </td>
            <td class="url-cell" title="{{ $log->url }}">{{ $log->url ?? '-' }}</td>
            <td class="ua-cell" title="{{ $log->user_agent }}">{{ $log->user_agent ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
              로그가 없습니다.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="padding:12px 16px;border-top:1px solid var(--border);">
    {{ $logs->links() }}
  </div>
</div>

@endsection
