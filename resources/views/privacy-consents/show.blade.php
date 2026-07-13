@extends('layouts.app')

@section('title', '동의서 상세')
@section('page-title', '개인정보 동의서 상세')
@section('breadcrumb', '홈 / 개인정보동의 / 상세')

@push('styles')
<style>
  .detail-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
  .detail-card h3 { margin:0 0 14px; font-size:14px; font-weight:800; color:var(--primary);
    padding-bottom:10px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .drow { display:grid; grid-template-columns:140px 1fr; gap:10px; padding:9px 0; border-bottom:1px solid #f1f4f8; font-size:13.5px; }
  .drow:last-child { border-bottom:none; }
  .drow .k { color:var(--text-muted); font-weight:700; }
  .drow .v { color:var(--text-primary); }
  .agree-yes { color:var(--success); font-weight:700; }
  .agree-no { color:var(--danger); font-weight:700; }
  .badge-type { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:700; }
  .badge-type.catheter { background:#e0f2fe; color:#0369a1; }
  .badge-type.stoma { background:#f0fdf4; color:#15803d; }
</style>
@endpush

@section('content')
@php
  function _agree($v){ if($v==='동의함') return '<span class="agree-yes">동의함</span>'; if($v) return '<span class="agree-no">'.$v.'</span>'; return '<span style="color:#94a3b8;">-</span>'; }
@endphp

<div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
  <a href="{{ route('privacy-consents.index') }}" class="btn btn-outline btn-sm"><i class="bx bx-arrow-back"></i> 목록</a>
  <span class="badge-type {{ $row->type }}">{{ $row->type_label }}</span>
  <span style="font-weight:800;font-size:16px;">{{ $row->name }}</span>
</div>

<div class="detail-card">
  <h3><i class="bx bx-user"></i> 신청자 정보</h3>
  <div class="drow"><span class="k">성명</span><span class="v">{{ $row->name }}</span></div>
  <div class="drow"><span class="k">연락처</span><span class="v">{{ $row->phone ?: '-' }}</span></div>
  @if($row->phone2)<div class="drow"><span class="k">연락처2</span><span class="v">{{ $row->phone2 }}</span></div>@endif
  <div class="drow"><span class="k">이메일</span><span class="v">{{ $row->email ?: '-' }}</span></div>
  <div class="drow"><span class="k">주소</span><span class="v">{{ $row->full_address ?: '-' }}</span></div>

  @if($row->type === 'catheter')
    <div class="drow"><span class="k">보험</span><span class="v">{{ $row->insurance ?: '-' }}</span></div>
    <div class="drow"><span class="k">지원 자격</span><span class="v">{{ $row->support_qualify ?: '-' }}</span></div>
  @else
    <div class="drow"><span class="k">생년월일</span><span class="v">{{ $row->birth ?: '-' }}</span></div>
    <div class="drow"><span class="k">사용 제품</span><span class="v">{{ $row->product ?: '-' }}</span></div>
    <div class="drow"><span class="k">수술 병원</span><span class="v">{{ $row->hospital ?: '-' }}</span></div>
    <div class="drow"><span class="k">수술일자</span><span class="v">{{ $row->surgery_date ?: '-' }}</span></div>
    <div class="drow"><span class="k">장루 타입</span><span class="v">{{ trim(($row->stoma_type ?? '').' '.($row->stoma_kind ?? '')) ?: '-' }}</span></div>
  @endif
</div>

<div class="detail-card">
  <h3><i class="bx bx-check-shield"></i> 개인정보 수집·이용 동의</h3>
  <div class="drow"><span class="k">일반정보 수집·이용</span><span class="v">{!! _agree($row->agree_general) !!} <span style="font-size:11px;color:#e74c3c;">(필수)</span></span></div>
  @if($row->type === 'stoma')
    <div class="drow"><span class="k">민감정보 수집·이용</span><span class="v">{!! _agree($row->agree_sensitive) !!} <span style="font-size:11px;color:#e74c3c;">(필수)</span></span></div>
    <div class="drow"><span class="k">일반 마케팅</span><span class="v">{!! _agree($row->agree_marketing) !!} <span style="font-size:11px;color:#94a3b8;">(선택)</span></span></div>
    <div class="drow"><span class="k">민감정보 마케팅</span><span class="v">{!! _agree($row->agree_marketing_sensitive) !!} <span style="font-size:11px;color:#94a3b8;">(선택)</span></span></div>
    <div class="drow"><span class="k">일반 제3자 제공</span><span class="v">{!! _agree($row->agree_third_party) !!} <span style="font-size:11px;color:#94a3b8;">(선택)</span></span></div>
    <div class="drow"><span class="k">민감 제3자 제공</span><span class="v">{!! _agree($row->agree_third_sensitive) !!} <span style="font-size:11px;color:#94a3b8;">(선택)</span></span></div>
  @else
    <div class="drow"><span class="k">제3자 제공</span><span class="v">{!! _agree($row->agree_third_party) !!} <span style="font-size:11px;color:#e74c3c;">(필수)</span></span></div>
    <div class="drow"><span class="k">마케팅 수집·이용</span><span class="v">{!! _agree($row->agree_marketing) !!} <span style="font-size:11px;color:#94a3b8;">(선택)</span></span></div>
  @endif
</div>

<div class="detail-card">
  <h3><i class="bx bx-info-circle"></i> 제출 정보</h3>
  <div class="drow"><span class="k">제출일시</span><span class="v">{{ $row->submitted_at?->format('Y-m-d H:i:s') ?: '-' }}</span></div>
  <div class="drow"><span class="k">IP</span><span class="v">{{ $row->ip ?: '-' }}</span></div>
  <div class="drow"><span class="k">User-Agent</span><span class="v" style="font-size:11px;color:var(--text-muted);word-break:break-all;">{{ $row->user_agent ?: '-' }}</span></div>
</div>
@endsection
