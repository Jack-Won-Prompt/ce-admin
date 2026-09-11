@extends('privacy.layout')
@section('title', '장루 개인정보 수집·이용 동의서')

@section('content')
{{-- 시안 513:1946 — 머리(이전 · 제목 · 완료) / 신청자 정보 세 칸 / 동의 항목.
     동의 항목의 차례와 글은 그대로다. 시안의 여섯 줄 중 5 · 6 은 5) 제3자 제공의 두 칸을 따로 그린 것이다. --}}
<form method="POST" action="{{ route('privacy.submit', ['type' => 'stoma']) }}" class="pv-form" id="consentForm">
  @csrf

  <div class="pv-head">
    <a class="pv-hbtn pv-hbtn--line" href="{{ route('privacy.landing') }}">
      <span class="pv-ico pv-ico--20 pv-ico--back" style="--ico:url('{{ asset('images/website/icons/chevron-right-20.svg') }}')"></span>이전
    </a>
    <h1 class="pv-title">개인정보 <em>수집·이용 동의서</em></h1>
    <p class="pv-chip">장루 환자 지원 · 콜로플라스트 코리아</p>
    <button type="submit" class="pv-hbtn pv-hbtn--done">
      <span class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/check-20.svg') }}')"></span>동의서 작성 완료
    </button>
  </div>

  @if($errors->any())
    <div class="errbox" id="errbox" role="alert" tabindex="-1">
      입력 내용을 확인해 주세요.
      <ul>@foreach(array_unique($errors->all()) as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- 인적사항 --}}
  <section class="pv-sec">
    <h2>신청자 정보</h2>
    <div class="pv-rule"></div>

    <div class="pv-cols">
      <div class="pv-col">
        <div class="pv-field">
          <label class="pv-label" for="pv-name">성명<span class="req">*</span></label>
          <div class="pv-ctrl"><input type="text" id="pv-name" name="name" value="{{ old('name') }}" placeholder="성명" autocomplete="name" required @error('name') aria-invalid="true" @enderror></div>
        </div>
        <div class="pv-field">
          <label class="pv-label" for="pv-phone">연락처1<span class="req">*</span></label>
          <div class="pv-ctrl"><input type="tel" id="pv-phone" name="phone" value="{{ old('phone') }}" placeholder="‘-’없이 숫자만" autocomplete="tel" required @error('phone') aria-invalid="true" @enderror></div>
        </div>
        <div class="pv-field">
          <label class="pv-label" for="pv-phone2">연락처2<span class="opt">(선택)</span></label>
          <div class="pv-ctrl"><input type="tel" id="pv-phone2" name="phone2" value="{{ old('phone2') }}" placeholder="보호자 등 추가 연락처"></div>
        </div>
        <div class="pv-field">
          <label class="pv-label" for="pv-addr1">주소<span class="req">*</span></label>
          <div class="pv-ctrl">
            <div class="pv-line">
              <input type="text" name="zip" value="{{ old('zip') }}" placeholder="우편번호" aria-label="우편번호" readonly onclick="findZip()" @error('zip') aria-invalid="true" @enderror>
              <input type="text" id="pv-addr1" name="addr1" value="{{ old('addr1') }}" placeholder="기본주소" autocomplete="address-line1" @error('addr1') aria-invalid="true" @enderror>
              <button type="button" class="pv-btn-addr" onclick="findZip()">주소 검색</button>
            </div>
            <input type="text" name="addr2" value="{{ old('addr2') }}" placeholder="상세 주소" aria-label="상세 주소" autocomplete="address-line2" @error('addr2') aria-invalid="true" @enderror>
          </div>
        </div>
      </div>

      <div class="pv-col">
        <div class="pv-field">
          <label class="pv-label" for="pv-email">이메일<span class="opt">(선택)</span></label>
          <div class="pv-ctrl"><input type="email" id="pv-email" name="email" value="{{ old('email') }}" placeholder="example@email.com" autocomplete="email" @error('email') aria-invalid="true" @enderror></div>
        </div>
        <div class="pv-field">
          <label class="pv-label" for="pv-birth">생년월일<span class="req">*</span></label>
          <div class="pv-ctrl"><input type="date" id="pv-birth" name="birth" value="{{ old('birth') }}" autocomplete="bday" required @error('birth') aria-invalid="true" @enderror></div>
        </div>
        {{-- 라디오 줄의 이름표는 가리킬 칸이 하나가 아니라 label 대신 묶음 이름(aria-labelledby)으로 쓴다 --}}
        <div class="pv-field">
          <span class="pv-label" id="pv-prd-l">사용 제품<span class="opt">(선택)</span></span>
          <div class="radio-group radio-group--2" role="radiogroup" aria-labelledby="pv-prd-l">
            @foreach(['미오','센슈라','기타','모름'] as $i => $v)
              <div class="radio-chip">
                <input type="radio" id="prd{{ $i }}" name="product" value="{{ $v }}" {{ old('product')===$v?'checked':'' }}>
                <label for="prd{{ $i }}">{{ $v }}</label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="pv-field">
          <label class="pv-label" for="pv-hospital">수술 병원<span class="opt">(선택)</span></label>
          <div class="pv-ctrl"><input type="text" id="pv-hospital" name="hospital" value="{{ old('hospital') }}" placeholder="수술 받은 병원명"></div>
        </div>
      </div>

      <div class="pv-col">
        <div class="pv-field">
          <label class="pv-label" for="pv-surgery">수술 일자<span class="opt">(선택)</span></label>
          <div class="pv-ctrl"><input type="date" id="pv-surgery" name="surgery_date" value="{{ old('surgery_date') }}"></div>
        </div>
        {{-- 장루 타입은 두 묶음(stoma_type · stoma_kind)이다. 시안은 한 격자에 이어 그렸다 — 칸만 잇고 묶음은 그대로 둔다 --}}
        <div class="pv-field">
          <span class="pv-label" id="pv-st-l">장루 타입<span class="opt">(선택)</span></span>
          <div class="radio-group radio-group--2" role="group" aria-labelledby="pv-st-l">
            <div class="radio-group--pair" role="radiogroup" aria-label="장루 기간">
              @foreach(['영구 장루','임시 장루','모름'] as $i => $v)
                <div class="radio-chip">
                  <input type="radio" id="st{{ $i }}" name="stoma_type" value="{{ $v }}" {{ old('stoma_type')===$v?'checked':'' }}>
                  <label for="st{{ $i }}">{{ $v }}</label>
                </div>
              @endforeach
            </div>
            <div class="radio-group--pair" role="radiogroup" aria-label="장루 종류">
              @foreach(['결장루','회장루','요루'] as $i => $v)
                <div class="radio-chip">
                  <input type="radio" id="sk{{ $i }}" name="stoma_kind" value="{{ $v }}" {{ old('stoma_kind')===$v?'checked':'' }}>
                  <label for="sk{{ $i }}">{{ $v }}</label>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- 동의 항목 --}}
  <section class="pv-sec">
    <h2>개인정보 수집·이용 동의</h2>
    <div class="pv-rule"></div>

    <label class="checkall">
      <input type="checkbox" onclick="checkAll(this)">
      <span class="pv-ico pv-ico--16" style="--ico:url('{{ asset('images/website/icons/check-16.svg') }}')"></span>
      아래 동의 항목에 모두 동의합니다.
    </label>

    <div class="agree-list">
      {{-- 1) 일반정보 (필수) --}}
      <div class="agree-item">
        <div class="agree-head"><span class="tag must">필수</span> 일반정보의 수집·이용에 대한 동의</div>
        <div class="agree-radios">
          <div class="radio-chip"><input type="radio" data-agree="1" id="s1y" name="agree_general" value="동의함" {{ old('agree_general')==='동의함'?'checked':'' }} required><label for="s1y">동의함</label></div>
          <div class="radio-chip"><input type="radio" id="s1n" name="agree_general" value="동의하지 않음" {{ old('agree_general')==='동의하지 않음'?'checked':'' }}><label for="s1n">동의하지 않음</label></div>
        </div>
        <button type="button" class="detail-toggle" onclick="toggleDetail(this)">펼치기</button>
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
        <button type="button" class="detail-toggle" onclick="toggleDetail(this)">펼치기</button>
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
        <button type="button" class="detail-toggle" onclick="toggleDetail(this)">펼치기</button>
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
        <button type="button" class="detail-toggle" onclick="toggleDetail(this)">펼치기</button>
        <div class="detail-box">1. 수집항목 : 수술부위사진, 상처부위 사진, 질병정보
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
2. 이용목적 : 제품 홍보를 위한 의학적 자료 수집·이용, 심포지엄 등 학술대회 자료 활용
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택적 민감정보 수집 및 마케팅 목적 이용을 거부할 수 있습니다.</div>
      </div>

      {{-- 5) 제3자 제공 (선택) --}}
      <div class="agree-item">
        <div class="agree-head"><span class="tag opt">선택</span> 일반 개인정보 및 민감정보의 제3자 제공(공개) 동의</div>
        <div class="agree-subline">· 일반 개인정보의 제3자 제공(공개)</div>
        <div class="agree-radios">
          <div class="radio-chip"><input type="radio" data-agree="1" id="s5ay" name="agree_third_party" value="동의함" {{ old('agree_third_party')==='동의함'?'checked':'' }}><label for="s5ay">동의함</label></div>
          <div class="radio-chip"><input type="radio" id="s5an" name="agree_third_party" value="동의하지 않음" {{ old('agree_third_party')==='동의하지 않음'?'checked':'' }}><label for="s5an">동의하지 않음</label></div>
        </div>
        <div class="agree-subline">· 민감정보의 선택적 제3자 제공(공개)</div>
        <div class="agree-radios">
          <div class="radio-chip"><input type="radio" data-agree="1" id="s5by" name="agree_third_sensitive" value="동의함" {{ old('agree_third_sensitive')==='동의함'?'checked':'' }}><label for="s5by">동의함</label></div>
          <div class="radio-chip"><input type="radio" id="s5bn" name="agree_third_sensitive" value="동의하지 않음" {{ old('agree_third_sensitive')==='동의하지 않음'?'checked':'' }}><label for="s5bn">동의하지 않음</label></div>
        </div>
        <button type="button" class="detail-toggle" onclick="toggleDetail(this)">펼치기</button>
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
  </section>

  <p class="pv-note">* 표시는 필수 입력·동의 항목입니다.</p>

  {{-- 칸이 쌓이는 폭(≤1279)에서만 보이는 아래 제출 — 휴대폰에서는 위 버튼이 마지막 질문보다 한참 위다 --}}
  <button type="submit" class="pv-hbtn pv-hbtn--done pv-submit-end">
    <span class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/check-20.svg') }}')"></span>동의서 작성 완료
  </button>
</form>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@endpush
