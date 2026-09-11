@extends('privacy.layout')
@section('title', '카테터 개인정보 수집·이용 동의서')

@section('content')
{{-- 시안 513:800 — 머리(이전 · 제목 · 완료) / 신청자 정보 세 칸 / 동의 항목.
     동의 항목의 수와 글은 시안(셋)이 아니라 2026-09-10 지시(다섯 영역 · App\Support\ConsentTerms)를 따른다. --}}
<form method="POST" action="{{ route('privacy.submit', ['type' => 'catheter']) }}" class="pv-form" id="consentForm">
  @csrf

  <div class="pv-head">
    <a class="pv-hbtn pv-hbtn--line" href="{{ route('privacy.landing') }}">
      <span class="pv-ico pv-ico--20 pv-ico--back" style="--ico:url('{{ asset('images/website/icons/chevron-right-20.svg') }}')"></span>이전
    </a>
    <h1 class="pv-title">개인정보 <em>수집·이용 동의서</em></h1>
    <p class="pv-chip">카테터(자가도뇨) 지원 신청 · 콜로플라스트 코리아</p>
    <button type="submit" class="pv-hbtn pv-hbtn--done">
      <span class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/check-20.svg') }}')"></span>동의서 작성 완료
    </button>
  </div>

  @if($errors->any())
    <div class="errbox">
      입력 내용을 확인해 주세요.
      <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- 인적사항 --}}
  <section class="pv-sec">
    <h2>신청자 정보</h2>
    <div class="pv-rule"></div>

    <div class="pv-cols">
      <div class="pv-col">
        <div class="pv-field">
          <label class="pv-label">성명<span class="req">*</span></label>
          <div class="pv-ctrl"><input type="text" name="name" value="{{ old('name') }}" placeholder="성명" required></div>
        </div>
        <div class="pv-field">
          <label class="pv-label">주소<span class="req">*</span></label>
          <div class="pv-ctrl">
            <div class="pv-line">
              <input type="text" name="zip" value="{{ old('zip') }}" placeholder="우편번호" readonly onclick="findZip()">
              <input type="text" name="addr1" value="{{ old('addr1') }}" placeholder="기본주소">
              <button type="button" class="pv-btn-addr" onclick="findZip()">주소 검색</button>
            </div>
            <input type="text" name="addr2" value="{{ old('addr2') }}" placeholder="상세 주소">
          </div>
        </div>
      </div>

      <div class="pv-col">
        <div class="pv-field">
          <label class="pv-label">연락처<span class="req">*</span></label>
          <div class="pv-ctrl"><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="‘-’없이 숫자만" required></div>
        </div>
        <div class="pv-field">
          <label class="pv-label">이메일<span class="opt">(선택)</span></label>
          <div class="pv-ctrl"><input type="email" name="email" value="{{ old('email') }}" placeholder="example@email.com"></div>
        </div>
      </div>

      <div class="pv-col">
        {{-- 보험은 필수로 받는다(검증 규칙) — 시안에는 * 가 없지만 규칙대로 붙인다 --}}
        <div class="pv-field">
          <label class="pv-label">보험<span class="req">*</span></label>
          <div class="radio-group">
            @foreach(['일반','보훈','산업재해'] as $i => $v)
              <div class="radio-chip">
                <input type="radio" id="ins{{ $i }}" name="insurance" value="{{ $v }}" {{ old('insurance')===$v?'checked':'' }} required>
                <label for="ins{{ $i }}">{{ $v }}</label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="pv-field">
          <label class="pv-label">지원 자격<span class="opt">(선택)</span></label>
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

    {{-- 다섯 영역 — 본문과 차례는 App\Support\ConsentTerms 한 곳에 있다.
         예전에는 셋만 받았고 글도 줄여 적은 요약이었다 (2026-09-10 지시). --}}
    <div class="agree-list">
      @include('privacy._agree-items', [
        'idp'       => 'ic',
        'chip'      => true,
        'detail'    => 'detail-toggle',
        'box'       => 'detail-box',
        'useOld'    => true,
        'agreeAttr' => 'data-agree="1"',
      ])
    </div>
  </section>

  <p class="pv-note">* 표시는 필수 입력·동의 항목입니다.</p>
</form>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
@endpush
