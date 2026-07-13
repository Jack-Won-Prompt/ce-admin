@extends('privacy.layout')
@section('title', '개인정보 수집·이용 동의서')

@section('content')
<div class="hero">
  <h1>개인정보 수집·이용 동의서</h1>
  <p>해당하는 항목을 선택해 주세요 · 콜로플라스트 코리아</p>
</div>

<div class="container">
  <div class="card" style="text-align:center;">
    <h2 style="justify-content:center;">신청 유형 선택</h2>
    <a href="{{ route('privacy.catheter') }}" class="btn btn-primary" style="margin-bottom:12px;">
      카테터(자가도뇨) 동의서 작성
    </a>
    <a href="{{ route('privacy.stoma') }}" class="btn btn-line">
      장루 동의서 작성
    </a>
    <p class="note" style="margin-top:14px;">작성하신 정보는 콜로플라스트 코리아의 환자 지원 목적으로만 이용됩니다.</p>
  </div>
</div>
@endsection
