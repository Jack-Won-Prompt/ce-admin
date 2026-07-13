@extends('privacy.layout')
@section('title', '장루 개인정보 수집·이용 동의서')

@section('content')
<div class="hero">
  <h1>개인정보 수집·이용 동의서</h1>
  <p>장루 환자 지원 · 콜로플라스트 코리아</p>
</div>

<form method="POST" action="{{ route('privacy.submit', ['type' => 'stoma']) }}" class="container" id="consentForm">
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
      <label>연락처1 <span class="req">*</span></label>
      <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="'-' 없이 숫자만" required>
    </div>

    <div class="field">
      <label>연락처2 <span class="opt">(선택)</span></label>
      <input type="tel" name="phone2" value="{{ old('phone2') }}" placeholder="보호자 등 추가 연락처">
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
      <label>이메일 <span class="opt">(선택)</span></label>
      <input type="email" name="email" value="{{ old('email') }}" placeholder="example@email.com">
    </div>

    <div class="field">
      <label>생년월일 <span class="req">*</span></label>
      <input type="date" name="birth" value="{{ old('birth') }}" required>
    </div>

    <div class="field">
      <label>사용 제품 <span class="opt">(선택)</span></label>
      <div class="radio-group">
        @foreach(['미오','센슈라','기타','모름'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="prd{{ $i }}" name="product" value="{{ $v }}" {{ old('product')===$v?'checked':'' }}>
            <label for="prd{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
    </div>

    <div class="field">
      <label>수술 병원 <span class="opt">(선택)</span></label>
      <input type="text" name="hospital" value="{{ old('hospital') }}" placeholder="수술 받은 병원명">
    </div>

    <div class="field">
      <label>수술일자 <span class="opt">(선택)</span></label>
      <input type="date" name="surgery_date" value="{{ old('surgery_date') }}">
    </div>

    <div class="field">
      <label>장루 타입 <span class="opt">(선택)</span></label>
      <div class="radio-group" style="margin-bottom:8px;">
        @foreach(['영구 장루','임시 장루','모름'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="st{{ $i }}" name="stoma_type" value="{{ $v }}" {{ old('stoma_type')===$v?'checked':'' }}>
            <label for="st{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
      <div class="radio-group">
        @foreach(['결장루','회장루','요루'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="sk{{ $i }}" name="stoma_kind" value="{{ $v }}" {{ old('stoma_kind')===$v?'checked':'' }}>
            <label for="sk{{ $i }}">{{ $v }}</label>
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
        <div class="radio-chip"><input type="radio" data-agree="1" id="s1y" name="agree_general" value="동의함" {{ old('agree_general')==='동의함'?'checked':'' }} required><label for="s1y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s1n" name="agree_general" value="동의하지 않음" {{ old('agree_general')==='동의하지 않음'?'checked':'' }}><label for="s1n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 일반 개인정보의 수집 및 이용 목적
· 환자의 신원 확인 및 정보전달, 샘플 및 제품 배송
· 제품 관련된 문의 및 불만의 처리, 제품 사용법 교육
· 구매 및 상담 등에 대한 전산관리
· 환자의 DB 구축, 회사에 부과되는 법적·행정적 의무의 이행
2. 수집 및 이용 항목 : 성명, 성별, 생년월일, 연락처, 주소, 이메일
3. 보유 및 이용기간 : 관계 법령에 따라 보존해야 하는 경우가 아닌 한 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 수집·이용을 거부할 수 있습니다. 다만 거부 시 위 목적에 따른 회사의 지원이 제한될 수 있습니다.</div>
    </div>

    {{-- 2) 민감정보 (필수) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag must">필수</span> 민감정보의 수집·이용에 대한 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="s2y" name="agree_sensitive" value="동의함" {{ old('agree_sensitive')==='동의함'?'checked':'' }} required><label for="s2y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s2n" name="agree_sensitive" value="동의하지 않음" {{ old('agree_sensitive')==='동의하지 않음'?'checked':'' }}><label for="s2n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 민감정보의 수집 및 이용 목적
· 환자의 신원 확인 및 정보전달, 샘플 및 제품 배송, 제품 관련 문의·불만 처리, 사용법 교육, 전산관리, DB 구축, 법적·행정적 의무 이행
2. 수집 및 이용 항목 : 수술병원, 장루종류, 사용제품, 건강상태, 처방관련 항목, 수술/상처부위 사진
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
3. 보유 및 이용기간 : 관계 법령에 따라 보존해야 하는 경우가 아닌 한 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 수집·이용을 거부할 수 있습니다. 다만 거부 시 위 목적에 따른 회사의 지원이 제한될 수 있습니다.</div>
    </div>

    {{-- 3) 일반 마케팅 (선택) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag opt">선택</span> 일반 개인정보의 마케팅 목적 수집·이용 및 광고성 정보 전송 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="s3y" name="agree_marketing" value="동의함" {{ old('agree_marketing')==='동의함'?'checked':'' }}><label for="s3y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s3n" name="agree_marketing" value="동의하지 않음" {{ old('agree_marketing')==='동의하지 않음'?'checked':'' }}><label for="s3n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 수집항목 : 성명, 생년월일, 연락처, 이메일
2. 이용목적 : 뉴스레터, 새로운 제품 소개, 재처방 예정일 등에 관한 정보 전달 및 그 외 마케팅 목적의 홍보 연락
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택 항목의 수집·이용을 거부할 수 있으며, 거부 시 뉴스레터·제품 소개·재처방 예정일 등 정보를 제공받을 수 없습니다.</div>
    </div>

    {{-- 4) 민감정보 마케팅 (선택) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag opt">선택</span> 민감정보에 대한 마케팅 목적 수집·이용 동의</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="s4y" name="agree_marketing_sensitive" value="동의함" {{ old('agree_marketing_sensitive')==='동의함'?'checked':'' }}><label for="s4y">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s4n" name="agree_marketing_sensitive" value="동의하지 않음" {{ old('agree_marketing_sensitive')==='동의하지 않음'?'checked':'' }}><label for="s4n">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 수집항목 : 수술부위사진, 상처부위 사진, 질병정보
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
2. 이용목적 : 제품 홍보를 위한 의학적 자료 수집·이용, 심포지엄 등 학술대회 자료 활용
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택적 민감정보 수집 및 마케팅 목적 이용을 거부할 수 있습니다.</div>
    </div>

    {{-- 5) 제3자 제공 (선택) --}}
    <div class="agree-item">
      <div class="agree-head"><span class="tag opt">선택</span> 일반 개인정보 및 민감정보의 제3자 제공(공개) 동의</div>
      <div style="font-size:12.5px;font-weight:600;margin-top:10px;color:var(--text);">· 일반 개인정보의 제3자 제공(공개)</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="s5ay" name="agree_third_party" value="동의함" {{ old('agree_third_party')==='동의함'?'checked':'' }}><label for="s5ay">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s5an" name="agree_third_party" value="동의하지 않음" {{ old('agree_third_party')==='동의하지 않음'?'checked':'' }}><label for="s5an">동의하지 않음</label></div>
      </div>
      <div style="font-size:12.5px;font-weight:600;margin-top:12px;color:var(--text);">· 민감정보의 선택적 제3자 제공(공개)</div>
      <div class="agree-radios">
        <div class="radio-chip"><input type="radio" data-agree="1" id="s5by" name="agree_third_sensitive" value="동의함" {{ old('agree_third_sensitive')==='동의함'?'checked':'' }}><label for="s5by">동의함</label></div>
        <div class="radio-chip"><input type="radio" id="s5bn" name="agree_third_sensitive" value="동의하지 않음" {{ old('agree_third_sensitive')==='동의하지 않음'?'checked':'' }}><label for="s5bn">동의하지 않음</label></div>
      </div>
      <button type="button" class="detail-toggle" onclick="toggleDetail(this)">자세히보기 ▼</button>
      <div class="detail-box">1. 제공받는 자 : 회사 주최 심포지엄 등 학술대회에서 의학적 자료를 전달받는 보건의료전문가
2. 이용목적 : 수술·처방 등 의료 과정에서 회사 제품 활용 시 참조
3. 제공항목
· 일반 개인정보 : 성별, 나이
· 민감정보 : 수술부위사진, 상처부위 사진, 질병정보
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
4. 보유 및 이용기간 : 해당 보건의료전문가의 이용 목적 달성 시까지
5. 귀하는 위 제3자 제공을 거부할 수 있습니다.</div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">동의서 작성 완료</button>
  <p class="note">* 표시는 필수 입력·동의 항목입니다.</p>
</form>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@endpush
