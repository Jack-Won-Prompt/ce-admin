@extends('privacy.layout')
@section('title', '작성 완료')

@section('content')
<div class="container" style="padding-top:60px;">
  <div class="card" style="text-align:center;padding:40px 20px;">
    <div style="width:72px;height:72px;border-radius:50%;background:#eafaf1;color:var(--ok);
      display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:38px;font-weight:800;">✓</div>
    <h2 style="justify-content:center;border:none;font-size:19px;color:var(--brand);">작성이 완료되었습니다</h2>
    <p style="color:var(--muted);font-size:14px;margin:6px 0 22px;">
      {{ \App\Models\PrivacyConsent::TYPE_LABELS[$type] ?? '' }} 개인정보 수집·이용 동의서가<br>정상적으로 접수되었습니다. 감사합니다.
    </p>
    <a href="{{ route('privacy.landing') }}" class="btn btn-line">처음으로</a>
  </div>
</div>
@endsection
