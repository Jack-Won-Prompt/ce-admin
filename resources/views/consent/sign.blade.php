<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>건강보험 급여 위임동의</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
      background: #f0f4ff;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px 16px 40px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(40,121,139,.10);
      width: 100%;
      max-width: 480px;
      overflow: hidden;
    }
    .card-header {
      background: linear-gradient(135deg, #28798B, #0B5C6E);
      color: #fff;
      padding: 24px 20px 20px;
      text-align: center;
    }
    .card-header .logo {
      font-size: 12px;
      font-weight: 600;
      opacity: .75;
      letter-spacing: .5px;
      margin-bottom: 8px;
    }
    .card-header h1 {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: -.3px;
    }
    .card-header p {
      font-size: 13px;
      opacity: .85;
      margin-top: 6px;
      line-height: 1.5;
    }
    .card-body { padding: 20px; }

    /* 타이머 */
    .timer-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff8e1;
      border: 1px solid #ffe082;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 18px;
      font-size: 13px;
      font-weight: 600;
      color: #e65100;
    }
    .timer-bar .timer-icon { font-size: 16px; }
    .timer-bar #countdown { font-family: monospace; font-size: 15px; }

    /* 환자 확인 */
    .patient-box {
      background: #f4f8ff;
      border: 1.5px solid #c7dcff;
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 18px;
    }
    .patient-box .label {
      font-size: 11px;
      font-weight: 600;
      color: #6b7280;
      margin-bottom: 4px;
    }
    .patient-box .name {
      font-size: 22px;
      font-weight: 800;
      color: #28798B;
      letter-spacing: -.3px;
    }
    .patient-box .sub {
      font-size: 12px;
      color: #6b7280;
      margin-top: 4px;
    }

    /* NICE 본인확인 */
    .verify-box {
      border: 1.5px solid #c7dcff;
      background: #f4f8ff;
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 18px;
    }
    .verify-box.verified { border-color: #a7f3d0; background: #ecfdf5; }
    .verify-row { display: flex; align-items: center; gap: 12px; }
    .verify-title { font-size: 13px; font-weight: 700; color: #1f2937; }
    .verify-desc  { font-size: 12px; color: #6b7280; margin-top: 3px; line-height: 1.5; }
    .verify-box.verified .verify-title { color: #047857; }
    .btn-verify {
      margin-left: auto; flex-shrink: 0;
      padding: 9px 14px; border: none; border-radius: 8px;
      background: #28798B; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
    }
    .btn-verify:disabled { background: #9ec3fb; cursor: default; }
    .verify-badge { margin-left: auto; flex-shrink: 0; font-size: 22px; }

    /* 동의 내용 */
    .consent-text {
      font-size: 13px;
      color: #374151;
      line-height: 1.7;
      background: #fafafa;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 14px;
      margin-bottom: 18px;
    }
    .consent-text strong { color: #111827; }

    /* 개인정보 수집·이용 동의 — 개인정보동의 페이지(privacy)와 같은 얼개다.
       그 페이지와 같은 항목ㆍ같은 문구를 받으므로 보이는 모습도 같게 둔다. */
    .agree-title {
      font-size: 13px; font-weight: 700; color: #374151; margin: 0 0 8px;
      display: flex; align-items: center; gap: 6px;
    }
    .agree-all {
      display: flex; align-items: center; gap: 9px; cursor: pointer;
      padding: 11px 13px; margin-bottom: 10px; border-radius: 9px;
      background: #eef7f9; border: 1px solid #cfe6ea;
      font-size: 13px; font-weight: 700; color: #1f6274;
    }
    .agree-all input { width: 18px; height: 18px; accent-color: #28798B; }
    .agree-item { border: 1px solid #e5e7eb; border-radius: 9px; padding: 12px 13px; margin-bottom: 10px; background: #fafafa; }
    .agree-head { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; font-weight: 700; color: #374151; }
    .agree-head .tag { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 5px; flex-shrink: 0; }
    .tag.must { background: #fdecea; color: #ef4444; }
    .tag.opt  { background: #eef1f5; color: #6b7280; }
    .agree-radios { display: flex; gap: 8px; margin-top: 10px; }
    .agree-radios > div { flex: 1; position: relative; }
    .agree-radios input { position: absolute; opacity: 0; pointer-events: none; }
    .agree-radios label {
      display: block; text-align: center; padding: 10px 8px; margin: 0;
      border: 1px solid #e5e7eb; border-radius: 8px; background: #fff;
      font-size: 13px; font-weight: 600; color: #6b7280; cursor: pointer;
    }
    .agree-radios input:checked + label { border-color: #28798B; background: #eef7f9; color: #1f6274; font-weight: 800; }
    .agree-detail {
      background: none; border: none; color: #28798B; font-size: 12px; font-weight: 700;
      cursor: pointer; padding: 8px 0 0; text-decoration: underline; font-family: inherit;
    }
    .agree-box {
      display: none; margin-top: 10px; padding: 12px; background: #fff;
      border: 1px dashed #e5e7eb; border-radius: 8px;
      font-size: 12px; line-height: 1.7; color: #6b7280; white-space: pre-line;
    }
    .agree-box.open { display: block; }

    /* 신청자 정보 — 개인정보동의 페이지의 「신청자 정보」 카드와 같은 칸들이다 */
    .pv-card { border: 1px solid #e5e7eb; border-radius: 9px; padding: 13px; margin-bottom: 12px; background: #fff; }
    .pv-sub {
      font-size: 12px; font-weight: 800; color: #1f6274;
      padding-bottom: 9px; margin-bottom: 12px; border-bottom: 1px solid #e5e7eb;
    }
    .pv-field { margin-bottom: 12px; }
    .pv-field:last-child { margin-bottom: 0; }
    .pv-field > label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; }
    .pv-req { color: #ef4444; font-size: 11px; margin-left: 2px; }
    .pv-opt { color: #6b7280; font-size: 11px; margin-left: 3px; font-weight: 500; }
    .pv-field input {
      width: 100%; padding: 10px 11px; border: 1px solid #e5e7eb; border-radius: 8px;
      font-size: 14px; background: #fbfcfe; color: #1f2937; font-family: inherit;
    }
    .pv-field input:focus { outline: none; border-color: #28798B; }
    .pv-field input[readonly] { background: #f3f4f6; color: #6b7280; }
    .pv-row { display: flex; gap: 8px; }
    .pv-btn-line {
      flex: 1; padding: 10px; border: 1.5px solid #28798B; border-radius: 8px;
      background: #fff; color: #28798B; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
    }
    .pv-subline { font-size: 12px; font-weight: 700; margin-top: 10px; color: #374151; }
    .pv-note { font-size: 11px; color: #6b7280; margin: 10px 0 0; }
    /* 고르지 않은 유형의 칸은 접는다 — 유형마다 받는 것이 다르다.
       인라인 style 로 여닫지 않는다. style.display='' 는 인라인 값을 지울 뿐이라
       아래 규칙이 다시 걸려 그대로 접혀 있었다 — 고른 유형을 껍데기에 적어 둔다. */
    #privacyBlock [data-only] { display: none; }
    #privacyBlock[data-type="catheter"] div[data-only="catheter"],
    #privacyBlock[data-type="stoma"]    div[data-only="stoma"]    { display: block; }
    #privacyBlock[data-type="catheter"] span[data-only="catheter"],
    #privacyBlock[data-type="stoma"]    span[data-only="stoma"]   { display: inline; }

    /* 서명란 */
    .sig-section {}
    .sig-label {
      font-size: 12px;
      font-weight: 700;
      color: #374151;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .sig-clear {
      font-size: 11px;
      color: #6b7280;
      background: none;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 2px 10px;
      cursor: pointer;
    }
    .sig-wrap {
      position: relative;
      border: 2px dashed #d1d5db;
      border-radius: 10px;
      overflow: hidden;
      touch-action: none;
      background: #fff;
      transition: border-color .2s;
    }
    .sig-wrap.active { border-color: #28798B; border-style: solid; }

    /* ── 보호자(법정대리인) 칸 ── */
    .g-field { margin-bottom: 10px; }
    .g-field label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px; }
    .g-field input, .g-field select {
      width: 100%; padding: 11px 12px; font-size: 15px; line-height: 1.4;
      border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #111827;
      -webkit-appearance: none; appearance: none;
    }
    .g-field input:focus, .g-field select:focus { outline: none; border-color: #28798B; }
    .g-field input[readonly] { background: #f3f4f6; color: #6b7280; }
    /* 신분증 올리기 — 손가락으로 누르기 쉬운 크기로 */
    .g-upload {
      display: flex; align-items: center; justify-content: center;
      min-height: 120px; padding: 14px;
      border: 2px dashed #d1d5db; border-radius: 10px; background: #fff;
      cursor: pointer; text-align: center; color: #9ca3af;
    }
    .g-upload:active { border-color: #28798B; }
    .g-upload.has-file { border-style: solid; border-color: #28798B; padding: 8px; }
    #gIdEmpty { display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 13px; }
    .sig-wrap canvas {
      display: block;
      width: 100%;
      cursor: crosshair;
    }
    .sig-placeholder {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      color: #d1d5db;
      font-size: 13px;
      gap: 6px;
      transition: opacity .2s;
    }
    .sig-placeholder svg { width: 32px; height: 32px; opacity: .5; }

    /* 버튼 */
    .btn-row {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 10px;
      margin-top: 22px;
    }
    .btn {
      padding: 14px 12px;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn:active { transform: scale(.97); }
    .btn-cancel { background: #f3f4f6; color: #4b5563; }
    .btn-agree  { background: #28798B; color: #fff; box-shadow: 0 4px 12px rgba(40,121,139,.3); }
    .btn-agree:disabled { background: #72BCCC; box-shadow: none; cursor: not-allowed; }

    /* 결과 화면 */
    .result-screen {
      display: none;
      text-align: center;
      padding: 40px 20px;
    }
    .result-screen .icon {
      font-size: 64px;
      margin-bottom: 16px;
    }
    .result-screen h2 { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
    .result-screen p  { font-size: 14px; color: #6b7280; line-height: 1.6; }

    /* 로딩 */
    .spinner {
      display: inline-block;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<div class="card" id="mainCard">
  <div class="card-header">
    <div class="logo">CE ADMIN</div>
    <h1>건강보험 급여 위임동의</h1>
    <p>아래 내용을 확인하신 후 서명해주세요.</p>
  </div>
  <div class="card-body">

    {{-- 타이머 --}}
    <div class="timer-bar">
      <span class="timer-icon">⏱</span>
      <span>링크 유효 시간</span>
      <span id="countdown" style="margin-left:auto;">--:--</span>
    </div>

    {{-- 환자 확인 --}}
    <div class="patient-box">
      <div class="label">본인 이름 확인</div>
      <div class="name">{{ $consent->patient_name }}</div>
      <div class="sub">위 이름이 본인과 다를 경우 동의하지 마세요.</div>
    </div>

    @if($niceEnabled)
    {{-- NICE 휴대폰 본인확인 --}}
    <div class="verify-box {{ $verified ? 'verified' : '' }}" id="verifyBox">
      <div class="verify-row">
        <div>
          <div class="verify-title" id="verifyTitle">{{ $verified ? '본인확인 완료' : '📱 휴대폰 본인확인' }}</div>
          <div class="verify-desc" id="verifyDesc">
            {{ $verified ? '본인확인이 완료되었습니다. 서명해 주세요.' : '서명 전 NICE 휴대폰 본인확인이 필요합니다.' }}
          </div>
        </div>
        @if($verified)
          <span class="verify-badge" id="verifyBadge">✅</span>
        @else
          <button type="button" class="btn-verify" id="btnVerify" onclick="startNice()">본인확인</button>
        @endif
      </div>
    </div>
    @endif

    {{-- 동의 내용 --}}
    <div class="consent-text">
      본인 <strong>{{ $consent->patient_name }}</strong>은(는) 건강보험 요양급여비용 청구와 관련하여
      콜로플라스트 코리아(주)가 건강보험공단에 제출하는 서류에 대한
      <strong>급여 위임청구 동의</strong>를 합니다.<br><br>
      위임 내용: 건강보험 급여 대상 보조기기의 급여비용 청구 및 수령에 관한 일체의 행위
    </div>

    {{-- ── 개인정보 수집·이용 동의 ────────────────────────────
         위임만 받고 개인정보 동의는 따로 받으러 다니던 것을 한 화면에서 끝낸다.
         신청 유형ㆍ신청자 정보ㆍ동의 항목까지 개인정보동의 페이지(privacy)의
         그 유형 폼과 같은 것을 받는다 — 두 곳에서 받은 동의가 같은 표
         (privacy_consents)에 같은 값으로 쌓여야 한 줄로 읽힌다.

         이미 동의를 받아 둔 사람에게는 이 영역이 아예 서지 않는다. 동의는 사람에게
         한 번 받으면 족하고, 처방전마다 다시 물으면 같은 것을 몇 번씩 읽히게 된다. --}}
    @if(! $privacyDone)
    <div class="sig-section" id="privacyBlock" style="margin-bottom:18px;">
      <div class="agree-title">개인정보 수집·이용 동의</div>

      {{-- 신청 유형 — 이 뒤의 칸과 동의 항목이 유형마다 다르다 --}}
      <div class="pv-field">
        <label>신청 유형 <span class="pv-req">*</span></label>
        <div class="agree-radios">
          <div><input type="radio" id="pvTypeC" name="privacy_type" value="catheter"
                      {{ ($privacyFill['type'] ?? '') === 'catheter' ? 'checked' : '' }}
                      onchange="onPrivacyType()"><label for="pvTypeC">카테터(자가도뇨)</label></div>
          <div><input type="radio" id="pvTypeS" name="privacy_type" value="stoma"
                      {{ ($privacyFill['type'] ?? '') === 'stoma' ? 'checked' : '' }}
                      onchange="onPrivacyType()"><label for="pvTypeS">장루</label></div>
        </div>
      </div>

      {{-- 신청자 정보 — 우리가 아는 것은 채워 둔다. 틀린 것만 고치면 된다. --}}
      <div class="pv-card" id="pvInfo">
        <div class="pv-sub">신청자 정보</div>

        <div class="pv-field">
          <label>성명 <span class="pv-req">*</span></label>
          <input type="text" id="pvName" maxlength="100" value="{{ $privacyFill['name'] ?? '' }}"
                 placeholder="성명" oninput="refreshAgree()">
        </div>

        <div class="pv-field">
          <label><span data-only="stoma">연락처1</span><span data-only="catheter">연락처</span> <span class="pv-req">*</span></label>
          <input type="tel" id="pvPhone" maxlength="30" value="{{ $privacyFill['phone'] ?? '' }}"
                 placeholder="010-0000-0000" oninput="refreshAgree()">
        </div>

        <div class="pv-field" data-only="stoma">
          <label>연락처2 <span class="pv-opt">(선택)</span></label>
          <input type="tel" id="pvPhone2" maxlength="30" value="{{ $privacyFill['phone2'] ?? '' }}"
                 placeholder="보호자 등 추가 연락처">
        </div>

        <div class="pv-field">
          <label>주소</label>
          <div class="pv-row">
            <input type="text" id="pvZip" maxlength="10" value="{{ $privacyFill['zip'] ?? '' }}"
                   placeholder="우편번호" readonly onclick="findPrivacyZip()" style="flex:0 0 42%;">
            <button type="button" class="pv-btn-line" onclick="findPrivacyZip()">주소 검색</button>
          </div>
          <input type="text" id="pvAddr1" maxlength="200" value="{{ $privacyFill['addr1'] ?? '' }}"
                 placeholder="기본주소" style="margin-top:8px;">
          <input type="text" id="pvAddr2" maxlength="200" value="{{ $privacyFill['addr2'] ?? '' }}"
                 placeholder="상세주소" style="margin-top:8px;">
        </div>

        <div class="pv-field">
          <label>이메일 <span class="pv-opt">(선택)</span></label>
          <input type="email" id="pvEmail" maxlength="150" value="{{ $privacyFill['email'] ?? '' }}"
                 placeholder="example@email.com">
        </div>

        {{-- 카테터 전용 --}}
        <div class="pv-field" data-only="catheter">
          <label>보험 <span class="pv-req">*</span></label>
          <div class="agree-radios">
            @foreach(['일반', '보훈', '산업재해'] as $i => $v)
              <div><input type="radio" id="pvIns{{ $i }}" name="pv_insurance" value="{{ $v }}" onchange="refreshAgree()"><label for="pvIns{{ $i }}">{{ $v }}</label></div>
            @endforeach
          </div>
        </div>

        <div class="pv-field" data-only="catheter">
          <label>지원 자격 <span class="pv-opt">(선택)</span></label>
          <div class="agree-radios">
            @foreach(['일반', '차상위경감대상자', '기초생활수급자'] as $i => $v)
              <div><input type="radio" id="pvSup{{ $i }}" name="pv_support" value="{{ $v }}"><label for="pvSup{{ $i }}">{{ $v }}</label></div>
            @endforeach
          </div>
        </div>

        {{-- 장루 전용 --}}
        <div class="pv-field" data-only="stoma">
          <label>생년월일 <span class="pv-req">*</span></label>
          <input type="date" id="pvBirth" value="{{ $privacyFill['birth'] ?? '' }}" onchange="refreshAgree()">
        </div>

        <div class="pv-field" data-only="stoma">
          <label>사용 제품 <span class="pv-opt">(선택)</span></label>
          <div class="agree-radios">
            @foreach(['미오', '센슈라', '기타', '모름'] as $i => $v)
              <div><input type="radio" id="pvPrd{{ $i }}" name="pv_product" value="{{ $v }}"><label for="pvPrd{{ $i }}">{{ $v }}</label></div>
            @endforeach
          </div>
        </div>

        <div class="pv-field" data-only="stoma">
          <label>수술 병원 <span class="pv-opt">(선택)</span></label>
          <input type="text" id="pvHospital" maxlength="100" placeholder="수술 받은 병원명">
        </div>

        <div class="pv-field" data-only="stoma">
          <label>수술일자 <span class="pv-opt">(선택)</span></label>
          <input type="date" id="pvSurgeryDate">
        </div>

        <div class="pv-field" data-only="stoma">
          <label>장루 타입 <span class="pv-opt">(선택)</span></label>
          <div class="agree-radios" style="margin-bottom:8px;">
            @foreach(['영구 장루', '임시 장루', '모름'] as $i => $v)
              <div><input type="radio" id="pvSt{{ $i }}" name="pv_stoma_type" value="{{ $v }}"><label for="pvSt{{ $i }}">{{ $v }}</label></div>
            @endforeach
          </div>
          <div class="agree-radios">
            @foreach(['결장루', '회장루', '요루'] as $i => $v)
              <div><input type="radio" id="pvSk{{ $i }}" name="pv_stoma_kind" value="{{ $v }}"><label for="pvSk{{ $i }}">{{ $v }}</label></div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- 동의 항목 --}}
      <label class="agree-all">
        <input type="checkbox" id="agreeAll" onclick="checkAllAgree(this)"> 아래 동의 항목에 모두 동의합니다.
      </label>

      {{-- 일반정보 (두 유형 모두 필수) --}}
      <div class="agree-item">
        <div class="agree-head"><span class="tag must">필수</span> 일반정보의 수집·이용에 대한 동의</div>
        <div class="agree-radios">
          <div><input type="radio" id="agGy" name="agree_general" value="동의함" onchange="refreshAgree()"><label for="agGy">동의함</label></div>
          <div><input type="radio" id="agGn" name="agree_general" value="동의하지 않음" onchange="refreshAgree()"><label for="agGn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 수집·이용 목적
· 환자의 신원 확인 및 정보전달, 샘플 및 제품 배송
· 제품 관련 문의·불만 처리, 제품 사용법 교육
· 구매 및 상담 등에 대한 전산관리, 환자 DB 구축
· 회사에 부과되는 법적·행정적 의무의 이행
2. 수집·이용 항목 : 성명, 성별, 생년월일, 연락처, 주소, 이메일
3. 보유 및 이용기간 : 관계 법령에 따라 보존해야 하는 경우가 아닌 한 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 수집·이용을 거부할 수 있습니다. 다만 거부 시 위 목적에 따른 회사의 지원이 제한될 수 있습니다.</div>
      </div>

      {{-- 제3자 제공 — 카테터는 필수, 장루는 선택이다 --}}
      <div class="agree-item" data-only="catheter">
        <div class="agree-head"><span class="tag must">필수</span> 개인정보의 제3자 제공에 대한 동의</div>
        <div class="agree-radios">
          <div><input type="radio" id="agTy" name="agree_third_party" value="동의함" onchange="refreshAgree()"><label for="agTy">동의함</label></div>
          <div><input type="radio" id="agTn" name="agree_third_party" value="동의하지 않음" onchange="refreshAgree()"><label for="agTn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 제공받는 자 : 요양비 지원·처방 관련 업무 수행 기관(준요양기관 등)
2. 이용목적 : 카테터 요양비 지원 신청 및 처리, 배송·상담
3. 제공항목 : 성명, 연락처, 주소, 보험·지원자격 정보
4. 보유 및 이용기간 : 제공 목적 달성 시까지
5. 귀하는 위 제3자 제공을 거부할 수 있습니다. 다만 거부 시 지원 신청 처리가 제한될 수 있습니다.</div>
      </div>

      {{-- 민감정보 (장루 필수) --}}
      <div class="agree-item" data-only="stoma">
        <div class="agree-head"><span class="tag must">필수</span> 민감정보의 수집·이용에 대한 동의</div>
        <div class="agree-radios">
          <div><input type="radio" id="agSy" name="agree_sensitive" value="동의함" onchange="refreshAgree()"><label for="agSy">동의함</label></div>
          <div><input type="radio" id="agSn" name="agree_sensitive" value="동의하지 않음" onchange="refreshAgree()"><label for="agSn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 민감정보의 수집 및 이용 목적
· 환자의 신원 확인 및 정보전달, 샘플 및 제품 배송, 제품 관련 문의·불만 처리, 사용법 교육, 전산관리, DB 구축, 법적·행정적 의무 이행
2. 수집 및 이용 항목 : 수술병원, 장루종류, 사용제품, 건강상태, 처방관련 항목, 수술/상처부위 사진
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
3. 보유 및 이용기간 : 관계 법령에 따라 보존해야 하는 경우가 아닌 한 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 수집·이용을 거부할 수 있습니다. 다만 거부 시 위 목적에 따른 회사의 지원이 제한될 수 있습니다.</div>
      </div>

      {{-- 마케팅 활용 (선택) --}}
      <div class="agree-item">
        <div class="agree-head"><span class="tag opt">선택</span> 개인정보의 마케팅 목적 수집·이용 및 광고성 정보 전송 동의</div>
        <div class="agree-radios">
          <div><input type="radio" id="agMy" name="agree_marketing" value="동의함" onchange="refreshAgree()"><label for="agMy">동의함</label></div>
          <div><input type="radio" id="agMn" name="agree_marketing" value="동의하지 않음" onchange="refreshAgree()"><label for="agMn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 수집항목 : 성명, 생년월일, 연락처, 이메일
2. 이용목적 : 뉴스레터, 새로운 제품 소개, 재처방 예정일 안내 등 마케팅 목적의 정보 전달
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택 항목의 수집·이용을 거부할 수 있으며, 거부 시 뉴스레터·제품 소개 등 정보를 제공받을 수 없습니다.</div>
      </div>

      {{-- 민감정보 마케팅 (장루 선택) --}}
      <div class="agree-item" data-only="stoma">
        <div class="agree-head"><span class="tag opt">선택</span> 민감정보에 대한 마케팅 목적 수집·이용 동의</div>
        <div class="agree-radios">
          <div><input type="radio" id="agMSy" name="agree_marketing_sensitive" value="동의함" onchange="refreshAgree()"><label for="agMSy">동의함</label></div>
          <div><input type="radio" id="agMSn" name="agree_marketing_sensitive" value="동의하지 않음" onchange="refreshAgree()"><label for="agMSn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 수집항목 : 수술부위사진, 상처부위 사진, 질병정보
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
2. 이용목적 : 제품 홍보를 위한 의학적 자료 수집·이용, 심포지엄 등 학술대회 자료 활용
3. 보유기간 : 수집일로부터 3년 또는 탈퇴 시까지 중 먼저 도래하는 기간까지
4. 귀하는 위 선택적 민감정보 수집 및 마케팅 목적 이용을 거부할 수 있습니다.</div>
      </div>

      {{-- 제3자 제공 (장루 선택 — 일반ㆍ민감을 따로 받는다) --}}
      <div class="agree-item" data-only="stoma">
        <div class="agree-head"><span class="tag opt">선택</span> 일반 개인정보 및 민감정보의 제3자 제공(공개) 동의</div>
        <div class="pv-subline">· 일반 개인정보의 제3자 제공(공개)</div>
        <div class="agree-radios">
          <div><input type="radio" id="agT2y" name="agree_third_party" value="동의함" onchange="refreshAgree()"><label for="agT2y">동의함</label></div>
          <div><input type="radio" id="agT2n" name="agree_third_party" value="동의하지 않음" onchange="refreshAgree()"><label for="agT2n">동의하지 않음</label></div>
        </div>
        <div class="pv-subline">· 민감정보의 제3자 제공(공개)</div>
        <div class="agree-radios">
          <div><input type="radio" id="agTSy" name="agree_third_sensitive" value="동의함" onchange="refreshAgree()"><label for="agTSy">동의함</label></div>
          <div><input type="radio" id="agTSn" name="agree_third_sensitive" value="동의하지 않음" onchange="refreshAgree()"><label for="agTSn">동의하지 않음</label></div>
        </div>
        <button type="button" class="agree-detail" onclick="toggleAgreeBox(this)">자세히보기 ▼</button>
        <div class="agree-box">1. 제공받는 자 : 회사 주최 심포지엄 등 학술대회에서 의학적 자료를 전달받는 보건의료전문가
2. 이용목적 : 수술·처방 등 의료 과정에서 회사 제품 활용 시 참조
3. 제공항목
· 일반 개인정보 : 성별, 나이
· 민감정보 : 수술부위사진, 상처부위 사진, 질병정보
※ 수술/상처부위 사진은 환자를 알아볼 수 없는 형태로 촬영·수집됩니다.
4. 보유 및 이용기간 : 해당 보건의료전문가의 이용 목적 달성 시까지
5. 귀하는 위 제3자 제공을 거부할 수 있습니다.</div>
      </div>

      <p class="pv-note">* 표시는 필수 입력·동의 항목입니다.</p>
    </div>
    @endif
    {{-- 서명란 --}}
    <div class="sig-section">
      <div class="sig-label">
        서명란 <span style="color:#ef4444;font-size:11px;">* 필수</span>
        <button class="sig-clear" type="button" onclick="clearSignature()">지우기</button>
      </div>
      <div class="sig-wrap" id="sigWrap">
        <canvas id="sigCanvas" height="180"></canvas>
        <div class="sig-placeholder" id="sigPlaceholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
          </svg>
          <span>이 곳에 서명하세요</span>
        </div>
      </div>
    </div>

    @if($consent->is_minor)
    {{-- ── 미성년자 — 법정대리인 ──────────────────────────────
         만 19세 미만은 혼자 위임할 수 없다. 보호자의 이름·관계·서명·신분증을 함께 받는다. --}}
    <div class="sig-section" id="guardianBlock">
      <div class="sig-label" style="display:block;margin-bottom:10px;">
        보호자(법정대리인) 확인 <span style="color:#ef4444;font-size:11px;">* 필수</span>
        <div style="font-size:12px;font-weight:400;color:#6b7280;line-height:1.7;margin-top:4px;">
          위임인이 만 {{ (int) config('delegation.minor_age', 19) }}세 미만이라 보호자 확인이 필요합니다.
        </div>
      </div>

      <div class="g-field">
        <label>환자 성명</label>
        <input type="text" id="gPatientName" value="{{ $consent->patient_name }}" readonly />
      </div>
      <div class="g-field">
        <label>환자 생년월일</label>
        <input type="text" id="gPatientBirth" value="{{ $consent->patient_birth_date?->format('Y-m-d') }}" readonly />
      </div>
      {{-- 담당자가 검수 화면에서 미리 적어 둔 값이 있으면 채워져 나온다 --}}
      <div class="g-field">
        <label>가입자ㆍ피부양자와의 관계 <span style="color:#ef4444;">*</span></label>
        <select id="gRelation" onchange="refreshAgree()">
          <option value="">선택</option>
          @foreach(config('delegation.guardian_relations', ['부','모','조부','조모','법정대리인']) as $r)
            <option value="{{ $r }}" {{ $consent->guardian_relation === $r ? 'selected' : '' }}>{{ $r }}</option>
          @endforeach
        </select>
      </div>
      <div class="g-field">
        <label>법정대리인 또는 가족 성명 <span style="color:#ef4444;">*</span></label>
        <input type="text" id="gName" maxlength="50" placeholder="법정대리인 또는 가족 성명"
               value="{{ $consent->guardian_name }}" oninput="refreshAgree()" />
      </div>
      <div class="g-field">
        <label>보호자 전화번호</label>
        <input type="text" id="gPhone" maxlength="20" placeholder="010-XXXX-XXXX"
               value="{{ $consent->guardian_phone }}" />
      </div>
      <div class="g-field">
        <label>법정대리인 또는 가족 생년월일 <span style="color:#ef4444;">*</span></label>
        <input type="text" id="gBirth" maxlength="10" placeholder="YYYY-MM-DD" inputmode="numeric"
               value="{{ $consent->guardian_birth_date?->format('Y-m-d') }}" oninput="onGuardianBirth(this)" />
      </div>

      <div class="sig-label" style="margin-top:14px;">
        보호자 서명 <span style="color:#ef4444;font-size:11px;">* 필수</span>
        <button class="sig-clear" type="button" onclick="clearGuardianSignature()">지우기</button>
      </div>
      <div class="sig-wrap" id="gSigWrap">
        <canvas id="gSigCanvas" height="180"></canvas>
        <div class="sig-placeholder" id="gSigPlaceholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
          </svg>
          <span>보호자가 이 곳에 서명하세요</span>
        </div>
      </div>

      <div class="sig-label" style="margin-top:14px;display:block;">
        법정대리인 또는 가족 신분증 <span style="color:#ef4444;font-size:11px;">* 필수</span>
        <div style="font-size:12px;font-weight:400;color:#6b7280;line-height:1.7;margin-top:4px;">
          주민등록증ㆍ운전면허증 등. 사진을 찍거나 파일을 고르세요. (JPGㆍPNGㆍHEIC, 최대 10MB)
        </div>
      </div>
      <label class="g-upload" id="gIdDrop">
        <input type="file" id="gIdFile" accept="image/jpeg,image/png,image/heic,image/heif"
               capture="environment" style="display:none;" onchange="onGuardianIdPick(this)" />
        <div id="gIdEmpty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:26px;height:26px;">
            <path d="M3 7a2 2 0 012-2h3l1.5-2h5L19 5h3a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            <circle cx="12" cy="12.5" r="3.5"/>
          </svg>
          <span>신분증 사진 올리기</span>
        </div>
        <img id="gIdPreview" style="display:none;max-width:100%;max-height:220px;border-radius:8px;" alt="" />
      </label>
      <div id="gIdName" style="display:none;font-size:12px;color:#6b7280;margin-top:6px;text-align:center;"></div>
    </div>
    @endif

    {{-- 버튼 --}}
    <div class="btn-row">
      <button class="btn btn-cancel" type="button" id="btnDecline" onclick="submitConsent('declined')">거절</button>
      <button class="btn btn-agree"  type="button" id="btnAgree"   onclick="submitConsent('agreed')" disabled>동의 서명</button>
    </div>

  </div>
</div>

{{-- 결과 화면 (동적 교체) --}}
<div class="card" id="resultCard" style="display:none;max-width:480px;">
  <div class="result-screen" id="resultScreen">
    <div class="icon" id="resultIcon"></div>
    <h2 id="resultTitle"></h2>
    <p id="resultMsg"></p>
  </div>
</div>

<script>
const TOKEN         = '{{ $consent->token }}';
const EXPIRES_AT    = new Date('{{ $consent->expires_at->toIso8601String() }}');
const SUBMIT_URL    = '{{ route('consent.submit', $consent->token) }}';
const NICE_ENABLED  = @json($niceEnabled);
const NICE_ENFORCE  = @json($niceEnforce);
const NICE_START_URL = '{{ route('consent.nice.start', $consent->token) }}';
let   identityVerified = @json($verified);

/* ── 카운트다운 ───────────────────────────────────────── */
function tick() {
  const diff = Math.max(0, Math.floor((EXPIRES_AT - Date.now()) / 1000));
  const m = String(Math.floor(diff / 60)).padStart(2, '0');
  const s = String(diff % 60).padStart(2, '0');
  const el = document.getElementById('countdown');
  el.textContent = m + ':' + s;
  el.style.color = diff <= 120 ? '#ef4444' : '#e65100';
  if (diff === 0) {
    showExpired();
  }
}
tick();
const timer = setInterval(tick, 1000);

function showExpired() {
  clearInterval(timer);
  showResult('⏰', '링크 만료', '서명 링크의 유효 시간(30분)이 지났습니다.\n담당자에게 재발송을 요청해주세요.', '#f59e0b');
}

/* ── 서명 패드 ────────────────────────────────────────── */
const canvas      = document.getElementById('sigCanvas');
const ctx         = canvas.getContext('2d');
const sigWrap     = document.getElementById('sigWrap');
const placeholder = document.getElementById('sigPlaceholder');
let drawing = false;
let hasSig  = false;

function resizeCanvas() {
  const w = sigWrap.clientWidth;
  canvas.width  = w * devicePixelRatio;
  canvas.height = 180 * devicePixelRatio;
  canvas.style.height = '180px';
  ctx.scale(devicePixelRatio, devicePixelRatio);
  ctx.strokeStyle = '#28798B';
  ctx.lineWidth   = 2.5;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

function getPos(e) {
  const rect = canvas.getBoundingClientRect();
  const src  = e.touches ? e.touches[0] : e;
  return { x: src.clientX - rect.left, y: src.clientY - rect.top };
}

function onStart(e) {
  e.preventDefault();
  drawing = true;
  const p = getPos(e);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
  sigWrap.classList.add('active');
  placeholder.style.opacity = '0';
}
function onMove(e) {
  if (!drawing) return;
  e.preventDefault();
  const p = getPos(e);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
  hasSig = true;
  refreshAgree();
}
function onEnd() { drawing = false; }

/* 동의 버튼 활성화 조건: 서명 완료 + (본인확인 강제 시) 본인확인 완료 */
/* ── 보호자(법정대리인) — 미성년자일 때만 화면에 있다 ────────── */
const IS_MINOR = @json((bool) $consent->is_minor);
let gHasSig = false, gIdData = null;

/* 생년월일 — 숫자 여덟 자리를 치면 YYYY-MM-DD 로 맞춘다.
   휴대폰에서 하이픈을 찾아 누르는 일이 없어야 한다. */
function onGuardianBirth(el) {
  const d = el.value.replace(/\D/g, '').slice(0, 8);
  el.value = d.length > 6 ? `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6)}`
           : d.length > 4 ? `${d.slice(0,4)}-${d.slice(4)}`
           : d;
  refreshAgree();
}

function guardianBirthOk() {
  const v = document.getElementById('gBirth')?.value ?? '';
  const m = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return false;
  const [, y, mo, d] = m.map(Number);
  const dt = new Date(y, mo - 1, d);
  // 없는 날(2월 30일 등)은 Date 가 다음 달로 넘긴다 — 되돌려 확인한다
  return dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d && dt <= new Date();
}

function guardianReady() {
  if (!IS_MINOR) return true;
  const name = (document.getElementById('gName')?.value ?? '').trim();
  const rel  = document.getElementById('gRelation')?.value ?? '';
  return !!(name && rel && guardianBirthOk() && gHasSig && gIdData);
}

/* ── 개인정보 수집·이용 동의 ───────────────────────────── */
function toggleAgreeBox(btn) {
  const box = btn.nextElementSibling;
  const on  = box.classList.toggle('open');
  btn.textContent = on ? '접기 ▲' : '자세히보기 ▼';
}

/* 이미 동의를 받아 둔 사람이면 이 영역이 화면에 없다 — 그때는 묻지도 보내지도 않는다 */
const PRIVACY_ASK = !!document.getElementById('privacyBlock');

function privacyType() {
  return document.querySelector('input[name="privacy_type"]:checked')?.value ?? '';
}

/* 유형을 고르면 그 유형의 칸과 동의 항목만 남긴다.
   카테터와 장루는 받는 것이 다르다 — 개인정보동의 페이지도 아예 다른 폼이다. */
function onPrivacyType() {
  const t = privacyType();
  document.getElementById('privacyBlock').dataset.type = t;
  // 접힌 쪽에 찍어 둔 답은 지운다. 보이지 않는 것이 서명을 열어 주면 안 된다.
  document.querySelectorAll('#privacyBlock [data-only] input').forEach(el => {
    if (el.closest('[data-only]').dataset.only !== t) el.checked = false;
  });
  refreshAgree();
}

/* 「모두 동의」는 보이는 항목만 켠다 — 접힌 유형의 것까지 켤 까닭이 없다 */
function checkAllAgree(cb) {
  document.querySelectorAll('#privacyBlock .agree-item').forEach(item => {
    if (item.dataset.only && item.dataset.only !== privacyType()) return;
    item.querySelectorAll('input[type=radio]').forEach(r => {
      r.checked = cb.checked && r.value === '동의함';
    });
  });
  refreshAgree();
}

function agreePicked(name) {
  const el = document.querySelector(`input[name="${name}"]:checked`);
  // 접힌 유형의 칸에 남은 답은 없는 것으로 친다
  const only = el?.closest('[data-only]')?.dataset.only;
  if (only && only !== privacyType()) return '';
  return el?.value ?? '';
}

function pvVal(id) {
  return (document.getElementById(id)?.value ?? '').trim();
}

/* 유형마다 필수가 다르다 — 개인정보동의 페이지의 그 폼과 같은 것을 본다.
   카테터 : 성명ㆍ연락처ㆍ보험 + 일반ㆍ제3자 동의
   장루   : 성명ㆍ연락처ㆍ생년월일 + 일반ㆍ민감 동의 */
function privacyReady() {
  if (!PRIVACY_ASK) return true;

  const t = privacyType();
  if (!t) return false;
  if (!pvVal('pvName') || !pvVal('pvPhone')) return false;

  if (t === 'stoma') {
    return !!pvVal('pvBirth')
        && agreePicked('agree_general')   === '동의함'
        && agreePicked('agree_sensitive') === '동의함';
  }
  return !!document.querySelector('input[name="pv_insurance"]:checked')
      && agreePicked('agree_general')     === '동의함'
      && agreePicked('agree_third_party') === '동의함';
}

function refreshAgree() {
  // 보이는 항목을 다 「동의함」으로 골라 두었으면 위의 「모두 동의」도 따라 켠다
  const all = document.getElementById('agreeAll');
  if (all) {
    const items = [...document.querySelectorAll('#privacyBlock .agree-item')]
      .filter(i => !i.dataset.only || i.dataset.only === privacyType());
    all.checked = items.length > 0 && items.every(i =>
      [...i.querySelectorAll('input[type=radio]')].some(r => r.checked && r.value === '동의함'));
  }
  const ok = hasSig && (!NICE_ENFORCE || identityVerified) && guardianReady() && privacyReady();
  document.getElementById('btnAgree').disabled = !ok;
}

/* 주소는 손으로 다 적으면 오타가 난다 — 개인정보동의 페이지와 같은 서비스로 찾는다 */
function findPrivacyZip() {
  if (typeof daum === 'undefined' || !daum.Postcode) {
    ceAlert('주소 찾기를 불러오지 못했습니다. 직접 적어 주십시오.', { tone: 'warning' });
    return;
  }
  new daum.Postcode({
    oncomplete: (data) => {
      document.getElementById('pvZip').value   = data.zonecode;
      document.getElementById('pvAddr1').value = data.roadAddress || data.jibunAddress;
      document.getElementById('pvAddr2').focus();
    },
  }).open();
}

// 첫 그림 — 미리 골라 둔 유형이 있으면 그 칸만 남는다
if (PRIVACY_ASK) onPrivacyType();

/* 보호자 서명판 — 위 서명판과 같은 방식이다. 캔버스만 다르다. */
let gCanvas = null, gCtx = null, gDrawing = false;

if (IS_MINOR) {
  gCanvas = document.getElementById('gSigCanvas');
  const gWrap = document.getElementById('gSigWrap');
  const gPh   = document.getElementById('gSigPlaceholder');

  const gResize = () => {
    const r   = gCanvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    gCanvas.width  = r.width * dpr;
    gCanvas.height = 180 * dpr;
    gCtx = gCanvas.getContext('2d');
    gCtx.scale(dpr, dpr);
    gCtx.strokeStyle = '#28798B';
    gCtx.lineWidth   = 2.5;
    gCtx.lineCap     = 'round';
    gCtx.lineJoin    = 'round';
  };
  gResize();
  window.addEventListener('resize', gResize);

  const gPos = (e) => {
    const rect = gCanvas.getBoundingClientRect();
    const src  = e.touches ? e.touches[0] : e;
    return { x: src.clientX - rect.left, y: src.clientY - rect.top };
  };
  const gStart = (e) => {
    e.preventDefault();
    gDrawing = true;
    const p = gPos(e);
    gCtx.beginPath(); gCtx.moveTo(p.x, p.y);
    gWrap.classList.add('active');
    gPh.style.opacity = '0';
  };
  const gMove = (e) => {
    if (!gDrawing) return;
    e.preventDefault();
    const p = gPos(e);
    gCtx.lineTo(p.x, p.y); gCtx.stroke();
    gHasSig = true;
    refreshAgree();
  };
  const gEnd = () => { gDrawing = false; };

  gCanvas.addEventListener('mousedown',  gStart);
  gCanvas.addEventListener('mousemove',  gMove);
  gCanvas.addEventListener('mouseup',    gEnd);
  gCanvas.addEventListener('mouseleave', gEnd);
  gCanvas.addEventListener('touchstart', gStart, { passive: false });
  gCanvas.addEventListener('touchmove',  gMove,  { passive: false });
  gCanvas.addEventListener('touchend',   gEnd);
}

function clearGuardianSignature() {
  if (!gCanvas) return;
  gCtx.clearRect(0, 0, gCanvas.width, gCanvas.height);
  gHasSig = false;
  document.getElementById('gSigWrap').classList.remove('active');
  document.getElementById('gSigPlaceholder').style.opacity = '1';
  refreshAgree();
}

/* 신분증 — 파일을 그대로 올리지 않고 브라우저에서 줄여 보낸다.
   요즘 휴대폰 사진은 한 장에 5MB 를 넘어 그대로 보내면 자주 실패한다. */
function onGuardianIdPick(input) {
  const file = input.files?.[0];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) {
    ceAlert('파일이 너무 큽니다. 10MB 이하로 올려주세요.', { tone: 'warning' });
    input.value = ''; return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    const img = new Image();
    img.onload = () => {
      const MAX = 1600;
      const scale = Math.min(1, MAX / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width  = Math.round(img.width  * scale);
      c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      gIdData = c.toDataURL('image/jpeg', 0.82);

      const prev = document.getElementById('gIdPreview');
      prev.src = gIdData;
      prev.style.display = '';
      document.getElementById('gIdEmpty').style.display = 'none';
      document.getElementById('gIdDrop').classList.add('has-file');
      const nm = document.getElementById('gIdName');
      nm.textContent = file.name + ' — 다시 누르면 바꿀 수 있습니다';
      nm.style.display = '';
      refreshAgree();
    };
    img.onerror = () => {
      ceAlert('이미지를 읽지 못했습니다. 다른 파일로 시도해주세요.', { tone: 'warning' });
      input.value = '';
    };
    img.src = reader.result;
  };
  reader.readAsDataURL(file);
}

canvas.addEventListener('mousedown',  onStart);
canvas.addEventListener('mousemove',  onMove);
canvas.addEventListener('mouseup',    onEnd);
canvas.addEventListener('mouseleave', onEnd);
canvas.addEventListener('touchstart', onStart, { passive: false });
canvas.addEventListener('touchmove',  onMove,  { passive: false });
canvas.addEventListener('touchend',   onEnd);

function clearSignature() {
  ctx.clearRect(0, 0, canvas.width / devicePixelRatio, canvas.height / devicePixelRatio);
  hasSig = false;
  placeholder.style.opacity = '1';
  sigWrap.classList.remove('active');
  refreshAgree();
}

/* ── NICE 휴대폰 본인확인 ─────────────────────────────────── */
let nicePopup = null;
let nicePopupWatch = null;

/* 팝업이 결과 없이 닫히면 버튼을 되살려 재시도할 수 있게 한다. */
function watchNicePopup() {
  clearInterval(nicePopupWatch);
  nicePopupWatch = setInterval(function () {
    if (!nicePopup || nicePopup.closed) {
      clearInterval(nicePopupWatch);
      nicePopupWatch = null;
      if (!identityVerified) resetVerifyBtn();
    }
  }, 800);
}

async function startNice() {
  const btn = document.getElementById('btnVerify');
  if (btn) { btn.disabled = true; btn.textContent = '요청 중...'; }

  // 팝업은 사용자 제스처 직후 먼저 연다(팝업 차단 회피)
  nicePopup = window.open('', 'nicePopup', 'width=460,height=640,scrollbars=yes');

  // 브라우저가 팝업을 막았으면 여기서 중단하고 안내한다(빈 탭이 열리는 것을 방지).
  if (!nicePopup || nicePopup.closed || typeof nicePopup.closed === 'undefined') {
    nicePopup = null;
    ceAlert('브라우저가 팝업을 차단했습니다.\n주소창의 팝업 차단을 해제한 뒤 다시 시도해 주세요.', { tone: 'warning' });
    resetVerifyBtn();
    return;
  }

  try {
    const res = await fetch(NICE_START_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept': 'application/json',
      },
      body: '{}',
    });
    const data = await res.json();
    if (!data.success) {
      if (nicePopup) nicePopup.close();
      ceAlert(data.message ?? '본인확인 요청에 실패했습니다.', { tone: 'danger' });
      resetVerifyBtn();
      return;
    }

    /* 시험용으로 통과시킨 경우다(NICE_SIMULATE) — 팝업을 열 곳이 없다. */
    if (data.simulated) {
      if (nicePopup) nicePopup.close();
      identityVerified = true;
      if (btn) { btn.textContent = '확인됨 (시험)'; btn.disabled = true; }
      refreshAgree();
      return;
    }

    /* 열어 둔 팝업을 받아 온 인증 주소로 보낸다.
       예전 표준창은 우리가 폼을 만들어 POST 해야 열렸는데, 통합인증은 건마다
       주소를 하나 만들어 주므로 그리로 보내기만 하면 된다. */
    if (!data.auth_url) {
      if (nicePopup) nicePopup.close();
      ceAlert('본인확인 주소를 받지 못했습니다.', { tone: 'danger' });
      resetVerifyBtn();
      return;
    }
    nicePopup.location.href = data.auth_url;

    if (btn) btn.textContent = '인증 진행 중...';
    watchNicePopup();
  } catch (e) {
    if (nicePopup) nicePopup.close();
    ceAlert('본인확인 요청 중 네트워크 오류가 발생했습니다.', { tone: 'danger' });
    resetVerifyBtn();
  }
}

function resetVerifyBtn() {
  const btn = document.getElementById('btnVerify');
  if (btn) { btn.disabled = false; btn.textContent = '본인확인'; }
}

/* 팝업(콜백 뷰)에서 결과 수신 */
window.addEventListener('message', function (e) {
  if (e.origin !== window.location.origin) return;
  const d = e.data;
  if (!d || d.source !== 'nice-identity') return;

  if (d.ok) {
    identityVerified = true;
    clearInterval(nicePopupWatch);
    nicePopupWatch = null;
    const box = document.getElementById('verifyBox');
    if (box) box.classList.add('verified');
    const t = document.getElementById('verifyTitle');
    if (t) t.textContent = '본인확인 완료';
    const desc = document.getElementById('verifyDesc');
    if (desc) desc.textContent = '본인확인이 완료되었습니다. 서명해 주세요.';
    const btn = document.getElementById('btnVerify');
    if (btn) btn.outerHTML = '<span class="verify-badge">✅</span>';
    refreshAgree();
  } else {
    clearInterval(nicePopupWatch);
    nicePopupWatch = null;
    ceAlert(d.message || '본인확인에 실패했습니다.', { tone: 'danger' });
    resetVerifyBtn();
  }
});

/* ── 제출 ─────────────────────────────────────────────── */
async function submitConsent(action) {
  if (action === 'agreed' && !hasSig) {
    ceAlert('서명을 먼저 해주세요.', { tone: 'warning' });
    return;
  }
  if (action === 'agreed' && IS_MINOR && !guardianReady()) {
    ceAlert('가입자ㆍ피부양자와의 관계, 법정대리인 또는 가족 성명ㆍ생년월일ㆍ서명ㆍ신분증을 모두 입력해주세요.', { tone: 'warning' });
    return;
  }
  if (action === 'agreed' && !privacyReady()) {
    ceAlert('개인정보 수집·이용의 신청 유형ㆍ필수 입력ㆍ필수 동의 항목을 모두 채워 주세요.', { tone: 'warning' });
    return;
  }

  const btnAgree   = document.getElementById('btnAgree');
  const btnDecline = document.getElementById('btnDecline');
  btnAgree.disabled = btnDecline.disabled = true;
  btnAgree.innerHTML = '<span class="spinner"></span> 처리 중...';

  const body = { action };
  if (action === 'agreed') {
    body.signature = canvas.toDataURL('image/png');
    if (PRIVACY_ASK) {
      const t = privacyType();
      const picked = (n) => document.querySelector(`input[name="${n}"]:checked`)?.value ?? '';

      body.privacy_type = t;
      body.name   = pvVal('pvName');
      body.phone  = pvVal('pvPhone');
      body.zip    = pvVal('pvZip');
      body.addr1  = pvVal('pvAddr1');
      body.addr2  = pvVal('pvAddr2');
      body.email  = pvVal('pvEmail');

      if (t === 'stoma') {
        body.phone2       = pvVal('pvPhone2');
        body.birth        = pvVal('pvBirth');
        body.hospital     = pvVal('pvHospital');
        body.surgery_date = pvVal('pvSurgeryDate');
        body.product      = picked('pv_product');
        body.stoma_type   = picked('pv_stoma_type');
        body.stoma_kind   = picked('pv_stoma_kind');
      } else {
        body.insurance       = picked('pv_insurance');
        body.support_qualify = picked('pv_support');
      }

      /* 갈래에서 묻는 것만 담는다.
         민감정보 셋은 장루에서만 묻는다 — 카테터에서는 화면에 뜨지도 않는데 예전에는
         「동의하지 않음」으로 채워 보냈다. 그러면 주문 등록의 개인정보동의 창에 그 줄이
         서서, 물어보지도 않은 것을 환자가 거절한 것처럼 보인다.
         고르지 않은 선택 항목만 「동의하지 않음」으로 남긴다 — 필수는 위에서 이미
         고르지 않으면 넘어가지 못한다. */
      const ASKED = t === 'stoma'
        ? ['agree_general', 'agree_sensitive', 'agree_third_party',
           'agree_third_sensitive', 'agree_marketing', 'agree_marketing_sensitive']
        : ['agree_general', 'agree_third_party', 'agree_marketing'];
      const REQUIRED = t === 'stoma'
        ? ['agree_general', 'agree_sensitive']
        : ['agree_general', 'agree_third_party'];

      ASKED.forEach(k => {
        const v = agreePicked(k);
        if (v) body[k] = v;
        else if (!REQUIRED.includes(k)) body[k] = '동의하지 않음';
      });
    }
    if (IS_MINOR) {
      body.guardian_name      = document.getElementById('gName').value.trim();
      body.guardian_relation  = document.getElementById('gRelation').value;
      body.guardian_birth     = document.getElementById('gBirth').value;
      body.guardian_phone     = document.getElementById('gPhone').value.trim();
      body.guardian_signature = gCanvas.toDataURL('image/png');
      body.guardian_id        = gIdData;
    }
  }

  try {
    const res = await fetch(SUBMIT_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept': 'application/json',
      },
      body: JSON.stringify(body),
    });
    const data = await res.json();

    if (data.success) {
      clearInterval(timer);
      if (action === 'agreed') {
        showResult('✅', '동의가 완료되었습니다', '건강보험 급여 위임동의가 정상적으로 접수되었습니다.\n이 창을 닫으셔도 됩니다.', '#12B76A');
      } else {
        showResult('❌', '거절 처리되었습니다', '위임동의를 거절하셨습니다.\n문의 사항은 담당자에게 연락주세요.', '#6b7280');
      }
    } else {
      ceAlert(data.message ?? '오류가 발생했습니다.', { tone: 'danger' });
      btnAgree.disabled = false;
      btnDecline.disabled = false;
      btnAgree.innerHTML = '동의 서명';
    }
  } catch (e) {
    ceAlert('네트워크 오류가 발생했습니다. 다시 시도해주세요.', { tone: 'danger' });
    btnAgree.disabled = false;
    btnDecline.disabled = false;
    btnAgree.innerHTML = '동의 서명';
  }
}

function showResult(icon, title, msg, color) {
  document.getElementById('mainCard').style.display = 'none';
  document.getElementById('resultCard').style.display = 'block';
  document.getElementById('resultIcon').textContent  = icon;
  document.getElementById('resultTitle').textContent = title;
  document.getElementById('resultTitle').style.color = color;
  document.getElementById('resultMsg').textContent   = msg;
  document.getElementById('resultScreen').style.display = 'block';
}
</script>

{{-- 커스텀 알림/확인 다이얼로그 (브라우저 기본 alert/confirm 대체) --}}
@include('partials.dialog')

{{-- CSRF hidden for fetch --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
</body>
</html>
