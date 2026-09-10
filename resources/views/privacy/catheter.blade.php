@extends('privacy.layout')
@section('title', '카테터 개인정보 수집·이용 동의서')

@section('content')
<div class="hero">
  <h1>개인정보 수집·이용 동의서</h1>
  <p>카테터(자가도뇨) 지원 신청 · 콜로플라스트 코리아</p>
</div>

<form method="POST" action="{{ route('privacy.submit', ['type' => 'catheter']) }}" class="container" id="consentForm">
  @csrf

  @if($errors->any())
    <div class="errbox">
      입력 내용을 확인해 주세요.
      <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- 인적사항 --}}
  <div class="card">
    <h2>신청자 정보</h2>

    <div class="field">
      <label>성명 <span class="req">*</span></label>
      <input type="text" name="name" value="{{ old('name') }}" placeholder="성명" required>
    </div>

    <div class="field">
      <label>주소 <span class="req">*</span></label>
      <div class="row" style="margin-bottom:8px;">
        <input type="text" name="zip" value="{{ old('zip') }}" placeholder="우편번호" style="flex:0 0 40%;" readonly onclick="findZip()">
        <button type="button" class="btn btn-line" style="flex:1;padding:11px;font-size:14px;" onclick="findZip()">주소 검색</button>
      </div>
      <input type="text" name="addr1" value="{{ old('addr1') }}" placeholder="기본주소" style="margin-bottom:8px;">
      <input type="text" name="addr2" value="{{ old('addr2') }}" placeholder="상세주소">
    </div>

    <div class="field">
      <label>연락처 <span class="req">*</span></label>
      <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="'-' 없이 숫자만" required>
    </div>

    <div class="field">
      <label>이메일 <span class="opt">(선택)</span></label>
      <input type="email" name="email" value="{{ old('email') }}" placeholder="example@email.com">
    </div>

    <div class="field">
      <label>보험 <span class="req">*</span></label>
      <div class="radio-group">
        @foreach(['일반','보훈','산업재해'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="ins{{ $i }}" name="insurance" value="{{ $v }}" {{ old('insurance')===$v?'checked':'' }} required>
            <label for="ins{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
    </div>

    <div class="field">
      <label>지원 자격 <span class="opt">(선택)</span></label>
      <div class="radio-group">
        @foreach(['일반','차상위경감대상자','기초생활수급자'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="sup{{ $i }}" name="support_qualify" value="{{ $v }}" {{ old('support_qualify')===$v?'checked':'' }}>
            <label for="sup{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- 동의 항목 --}}
  <div class="card">
    <h2>개인정보 수집·이용 동의</h2>

    <label class="checkall">
      <input type="checkbox" onclick="checkAll(this)"> 아래 동의 항목에 모두 동의합니다.
    </label>

    {{-- 다섯 영역 — 본문과 차례는 App\Support\ConsentTerms 한 곳에 있다.
         예전에는 셋만 받았고 글도 줄여 적은 요약이었다 (2026-09-10 지시). --}}
    @include('privacy._agree-items', [
      'idp'       => 'ic',
      'chip'      => true,
      'detail'    => 'detail-toggle',
      'box'       => 'detail-box',
      'useOld'    => true,
      'agreeAttr' => 'data-agree="1"',
    ])

  </div>

  <button type="submit" class="btn btn-primary">동의서 작성 완료</button>
  <p class="note">* 표시는 필수 입력·동의 항목입니다.</p>
</form>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@endpush
