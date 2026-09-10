@extends('layouts.app')

@section('title', '웹훅 로그')
@section('page-title', '웹훅 전송·수신 로그')
@section('breadcrumb', '홈 - 설정 - 웹훅 관리 - 로그')

@section('content')

{{-- 낱장으로 열었을 때의 자리. 본체는 웹훅 관리 화면의 옆 탭에 박히는 그 조각이다
     (2026-09-10 지시) — 한 벌만 두어야 두 자리가 갈리지 않는다. --}}
{{-- 얼개는 웹훅 관리 화면과 같다 — 거르개가 맨 위, 그 아래 카드에 표 하나. --}}
@include('webhooks._log-filter', ['보내는곳' => route('webhooks.logs'), 'tab' => 'logs'])

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <a href="{{ route('webhooks.index') }}" class="pnl-tab"
         style="text-decoration:none;">
        <i class="fa-solid fa-arrows-rotate"></i> 웹훅 목록
      </a>
      <span class="pnl-tab active">
        <i class="fa-solid fa-clock-rotate-left"></i> 전송·수신 로그
        <span class="pnl-tab-cnt">(총 {{ $logCounts['all'] }}건)</span>
      </span>
      <span style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <span class="wl-chip wl-ok">성공 {{ $logCounts['ok'] }}</span>
        <span class="wl-chip wl-fail">실패 {{ $logCounts['fail'] }}</span>
        <button type="button" class="ds-btn" onclick="window.__wlGrid?.downloadExcel()">엑셀 다운</button>
      </span>
    </div>
    <div style="padding:16px;">
      @include('webhooks._logs')
    </div>
  </div>
</div>

@endsection
