@extends('privacy.layout')
@section('title', '작성 완료')
@section('card-class', 'pv-card--fill')

@section('content')
{{-- 완료 화면은 시안이 없다 — 선택 화면(513:2572)의 판 · 글 규격을 그대로 빌린다 --}}
<style>
  .pd-mark{width:72px;height:72px;display:flex;align-items:center;justify-content:center;border-radius:50%;
    background:var(--primary-100);color:var(--primary-500);}
  .pd-mark .pv-ico{width:36px;height:36px;}
  .pd-desc{text-align:center;font-size:16px;line-height:27px;color:var(--gray-600);}
</style>

<div class="pv-head">
  <span class="pd-mark"><span class="pv-ico" style="--ico:url('{{ asset('images/website/icons/check-16.svg') }}')"></span></span>
  <h1 class="pv-title">작성이 <em>완료되었습니다</em></h1>
  <p class="pd-desc">{{ \App\Models\PrivacyConsent::TYPE_LABELS[$type] ?? '' }} 개인정보 수집·이용 동의서가<br>정상적으로 접수되었습니다. 감사합니다.</p>
</div>

<a href="{{ route('privacy.landing') }}" class="pv-hbtn pv-hbtn--line" style="position:static">처음으로</a>
@endsection
