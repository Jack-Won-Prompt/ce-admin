@extends('layouts.app')

@section('title', '웹훅 로그')
@section('page-title', '웹훅 전송·수신 로그')
@section('breadcrumb', '홈 - 설정 - 웹훅 관리 - 로그')

@section('content')

{{-- 낱장으로 열었을 때의 자리. 본체는 웹훅 관리 화면의 옆 탭에 박히는 그 조각이다
     (2026-09-10 지시) — 한 벌만 두어야 두 자리가 갈리지 않는다. --}}
<div style="margin-bottom:12px;">
  <a href="{{ route('webhooks.index') }}" class="ds-btn">웹훅 관리로</a>
</div>

<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div style="padding:16px;">
      @include('webhooks._logs', ['보내는곳' => route('webhooks.logs')])
    </div>
  </div>
</div>

@endsection
