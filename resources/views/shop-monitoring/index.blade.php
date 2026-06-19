@extends('layouts.app')

@section('title', 'CE샵 모니터링')
@section('page-title', 'CE샵 사용자 모니터링')
@section('breadcrumb', '홈 / CE샵 모니터링')

@push('styles')
<style>
  .role-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: var(--border-light); color: var(--text-secondary); }
  .role-badge.super  { background: #F3EEFF; color: var(--purple); }
  .role-badge.admin  { background: var(--primary-light); color: var(--primary); }
  .log-row:hover td { background: #FAFBFD; }
  .user-link { font-weight: 600; color: var(--text-primary); text-decoration: none; }
  .user-link:hover { color: var(--primary); }
  .mono { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; }
  .section-divider { height: 1px; background: var(--border); margin: 22px 0; }
</style>
@endpush

@section('content')

{{-- 요약 카드 --}}
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon primary"><i class="bx bx-group"></i></div>
    <div class="stat-info">
      <div class="stat-val">{{ $sessions->count() }}</div>
      <div class="stat-label">전체 접속자</div>
    </div>
  </div>
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon success"><i class="bx bx-wifi"></i></div>
    <div class="stat-info">
      <div class="stat-val" style="color:var(--success);">{{ $sessions->where('online', true)->count() }}</div>
      <div class="stat-label">현재 온라인 <span style="font-size:10px;color:var(--text-muted);">(5분 이내)</span></div>
    </div>
  </div>
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon info"><i class="bx bx-show"></i></div>
    <div class="stat-info">
      <div class="stat-val" style="color:var(--info);">{{ $todayCount }}</div>
      <div class="stat-label">오늘 상품 조회</div>
    </div>
  </div>
</div>

{{-- 사용자 로그인 현황 --}}
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="status-dot online" style="width:8px;height:8px;min-width:8px;flex-shrink:0;border-radius:50%;background:var(--success);box-shadow:0 0 0 2px var(--success-light);margin-right:6px;"></span>
    <span class="card-header-title">사용자 로그인 현황</span>
    <a href="{{ route('shop-monitoring.index') }}" class="btn btn-outline btn-sm ms-auto">
      <i class="bx bx-refresh"></i> 새로고침
    </a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:90px;">상태</th>
          <th>이름</th>
          <th>이메일</th>
          <th style="width:110px;">권한</th>
          <th style="width:110px;">마지막 로그인</th>
          <th style="width:130px;">마지막 활동</th>
          <th style="width:110px;">로그아웃</th>
          <th style="width:120px;">IP</th>
        </tr>
      </thead>
      <tbody>
        @php
          $roleMap = [
            'super_admin'     => ['label'=>'슈퍼관리자', 'class'=>'super'],
            'operations_admin'=> ['label'=>'운영관리자', 'class'=>'admin'],
            'company_admin'   => ['label'=>'회사관리자', 'class'=>'admin'],
            'approver'        => ['label'=>'승인자',      'class'=>''],
            'caregiver'       => ['label'=>'보호자',      'class'=>''],
            'patient'         => ['label'=>'환자',        'class'=>''],
          ];
        @endphp
        @forelse($sessions as $s)
        <tr>
          <td>
            @if($s->online)
              <span class="status-dot online">온라인</span>
            @else
              <span class="status-dot offline">오프라인</span>
            @endif
          </td>
          <td><strong>{{ $s->shop_user_name ?: '-' }}</strong></td>
          <td style="color:var(--text-muted);font-size:12.5px;">{{ $s->shop_user_email ?: '-' }}</td>
          <td>
            @php $rInfo = $roleMap[$s->shop_user_role] ?? ['label'=>($s->shop_user_role ?: '-'), 'class'=>'']; @endphp
            <span class="role-badge {{ $rInfo['class'] }}">{{ $rInfo['label'] }}</span>
          </td>
          <td style="font-size:12px;color:var(--text-muted);">
            {{ $s->last_login_at ? \Carbon\Carbon::parse($s->last_login_at,'UTC')->setTimezone('Asia/Seoul')->format('m/d H:i') : '-' }}
          </td>
          <td style="font-size:12px;color:var(--text-muted);">
            {{ $s->last_activity_at ? \Carbon\Carbon::parse($s->last_activity_at,'UTC')->setTimezone('Asia/Seoul')->format('m/d H:i:s') : '-' }}
          </td>
          <td style="font-size:12px;color:var(--text-muted);">
            {{ $s->last_logout_at ? \Carbon\Carbon::parse($s->last_logout_at,'UTC')->setTimezone('Asia/Seoul')->format('m/d H:i') : '-' }}
          </td>
          <td class="mono" style="color:var(--text-muted);">{{ $s->ip ?: '-' }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <i class="bx bx-user-x"></i>
              <p>접속 기록이 없습니다.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- 상품 조회 로그 --}}
<div class="card">
  <div class="card-header">
    <i class="bx bx-list-check" style="font-size:18px;color:var(--primary);"></i>
    <span class="card-header-title">상품 조회 로그</span>
    <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">(최근 50건)</span>
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
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:110px;">날짜</th>
          <th>이름</th>
          <th>이메일</th>
          <th>상품명</th>
          <th style="width:100px;">상품 ID</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
        <tr class="log-row">
          <td style="font-size:12px;color:var(--text-muted);">
            {{ $log->log_date ?? \Carbon\Carbon::parse($log->created_at,'UTC')->setTimezone('Asia/Seoul')->format('Y-m-d') }}
          </td>
          <td>
            <a href="{{ route('shop-monitoring.index', ['user_id' => $log->shop_user_id]) }}" class="user-link">
              {{ $log->shop_user_name ?: '-' }}
            </a>
          </td>
          <td style="font-size:12.5px;color:var(--text-muted);">{{ $log->shop_user_email ?: '-' }}</td>
          <td style="font-weight:500;">{{ $log->product_name ?: '-' }}</td>
          <td class="mono" style="color:var(--primary);">{{ $log->product_id }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="5">
            <div class="empty-state">
              <i class="bx bx-data"></i>
              <p>조회 로그가 없습니다.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($logs->hasPages())
  <div class="card-footer" style="display:flex;justify-content:flex-end;">
    {{ $logs->links() }}
  </div>
  @endif
</div>

@endsection
