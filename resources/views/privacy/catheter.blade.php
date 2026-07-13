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

    {{-- 1) 일반정보 (필수) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag must">필수</span> 일반정보의 수집·이용에 대한 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="ag1y" name="agree_general" value="동의함" {{ old('agree_general')==='동의함'?'checked':'' }} required><label for="ag1y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="ag1n" name="agree_general" value="동의하지 않음" {{ old('agree_general')==='동의하지 않음'?'checked':'' }}><label for="ag1n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 수집·이용 목적
· 환자의 신원 확인 및 정보전달, 샘플 및 제품 배송
· 제품 관련 문의·불만 처리, 제품 사용법 교육
· 구매 및 상담 등에 대한 전산관리, 환자 DB 구축
· 회사에 부과되는 법적·행정적 의무의 이행
2. 수집·이용 항목 : 성명, 성별, 생년월일, 연락처, 주소, 이메일
3. 보유 및 이용기간 : 관계 법령에 따라 보존해야 하는 경우가 아닌 한 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 수집·이용을 거부할 수 있습니다. 다만 거부 시 위 목적에 따른 회사의 지원이 제한될 수 있습니다.</div>
    </div>

    {{-- 3) 제3자 제공 (필수) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag must">필수</span> 개인정보의 제3자 제공에 대한 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="ag3y" name="agree_third_party" value="동의함" {{ old('agree_third_party')==='동의함'?'checked':'' }} required><label for="ag3y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="ag3n" name="agree_third_party" value="동의하지 않음" {{ old('agree_third_party')==='동의하지 않음'?'checked':'' }}><label for="ag3n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 제공받는 자 : 요양비 지원·처방 관련 업무 수행 기관(준요양기관 등)
2. 이용목적 : 카테터 요양비 지원 신청 및 처리, 배송·상담
3. 제공항목 : 성명, 연락처, 주소, 보험·지원자격 정보
4. 보유 및 이용기간 : 제공 목적 달성 시까지
5. 귀하는 위 제3자 제공을 거부할 수 있습니다. 다만 거부 시 지원 신청 처리가 제한될 수 있습니다.</div>
    </div>

    {{-- 4) 마케팅 (선택) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag opt">선택</span> 개인정보의 마케팅 목적 수집·이용에 대한 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="ag4y" name="agree_marketing" value="동의함" {{ old('agree_marketing')==='동의함'?'checked':'' }}><label for="ag4y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="ag4n" name="agree_marketing" value="동의하지 않음" {{ old('agree_marketing')==='동의하지 않음'?'checked':'' }}><label for="ag4n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 수집항목 : 성명, 생년월일, 연락처, 이메일
2. 이용목적 : 뉴스레터, 새로운 제품 소개, 재처방 예정일 안내 등 마케팅 목적의 정보 전달
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택 항목의 수집·이용을 거부할 수 있으며, 거부 시 뉴스레터·제품 소개 등 정보를 제공받을 수 없습니다.</div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">동의서 작성 완료</button>
  <p class="note">* 표시는 필수 입력·동의 항목입니다.</p>
</form>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@endpush
