{{-- 모바일 신규 신설 — 신규 신청자가 스스로 작성하는 화면 (2026-10-01 지시).

     **자료를 담는 코드는 여기 없다.** 제출은 기존 `privacy.submit` 으로 보낸다 —
     검증과 저장이 한 곳에만 있어야 공개 폼(/privacy/catheter)과 이 화면이
     서로 다른 것을 받는 일이 생기지 않는다. 화면만 모바일 규격으로 짓는다.

     **동의 본문도 여기 없다.** `privacy._agree-items` 를 그대로 포함하므로 문구는
     App\Support\ConsentTerms 한 곳에서 온다. 문구를 옮겨 적으면 그 순간부터 기존
     동의서와 달라지고, 나중에 어느 쪽이 맞는지 다투게 된다.

     겉모습은 /m/login 과 같은 규격이다(어두운 바탕·Pretendard·Boxicons). 다만
     포함해 쓰는 동의 항목 부분이 밝은 화면용 class 를 쓰므로, 그 class 의 모양을
     이 화면에서 어두운 바탕에 맞추어 다시 정한다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0B63CE">
  <title>신규 신청 — CE Admin</title>
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    :root { --m-primary:#0B63CE; --m-line:rgba(255,255,255,.18); --m-danger:#D32F2F; }
    * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html, body { margin:0; padding:0; width:100%; overflow-x:hidden; }
    body { min-height:100vh; font-family:'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif;
           background:linear-gradient(160deg, #0D1B3E 0%, #14306B 44%, #1565C0 100%);
           color:#fff; }

    /* 머리 — 뒤로 가기와 제목 */
    .top { position:sticky; top:0; z-index:20; display:flex; align-items:center; gap:10px;
           padding:12px 14px; background:rgba(9,20,48,.86); backdrop-filter:blur(14px);
           border-bottom:1px solid rgba(255,255,255,.12); }
    .top a { color:#fff; font-size:23px; line-height:1; text-decoration:none; padding:4px; }
    .top b { font-size:16px; font-weight:800; letter-spacing:-.3px; }

    .hero { padding:22px 20px 6px; }
    .hero h1 { margin:0; font-size:21px; font-weight:800; letter-spacing:-.4px; }
    .hero p  { margin:7px 0 0; font-size:13px; color:rgba(255,255,255,.64); line-height:1.6; }

    .container { padding:16px 16px 40px; }

    .card { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
            border-radius:18px; padding:18px 16px; backdrop-filter:blur(18px); margin-bottom:14px; }
    .card h2 { margin:0 0 15px; font-size:15.5px; font-weight:800; letter-spacing:-.3px;
               display:flex; align-items:center; gap:7px; }
    .card h2 i { font-size:18px; color:#7FB3F0; }

    .field { margin-bottom:15px; }
    .field > label { display:block; font-size:12.5px; font-weight:700;
                     color:rgba(255,255,255,.78); margin-bottom:7px; }
    .req { color:#FF9A9A; }
    .opt { color:rgba(255,255,255,.48); font-weight:600; }
    input[type=text], input[type=tel], input[type=email], input[type=date] {
      width:100%; padding:13px 14px; border-radius:12px; border:1px solid rgba(255,255,255,.24);
      background:rgba(255,255,255,.1); color:#fff; font-size:15.5px; font-family:inherit; }
    input::placeholder { color:rgba(255,255,255,.4); }
    input:focus { outline:0; border-color:#7FB3F0; background:rgba(255,255,255,.16); }
    .zip-row { display:flex; gap:8px; margin-bottom:8px; }
    .zip-row input { flex:0 0 42%; }
    .zip-row button { flex:1; border:1px solid rgba(255,255,255,.3); border-radius:12px;
                      background:rgba(255,255,255,.14); color:#fff; font-size:13.5px;
                      font-weight:700; font-family:inherit; cursor:pointer; }

    /* 고르는 칸 — 포함해 쓰는 동의 항목 부분도 이 class 를 쓴다 */
    .radio-group { display:flex; gap:8px; flex-wrap:wrap; }
    .radio-chip { flex:1; min-width:calc(33% - 8px); position:relative; }
    .radio-chip input { position:absolute; opacity:0; pointer-events:none; }
    .radio-chip label { display:block; text-align:center; padding:11px 8px; font-size:13.5px;
                        border:1px solid rgba(255,255,255,.26); border-radius:11px;
                        background:rgba(255,255,255,.08); color:rgba(255,255,255,.82); cursor:pointer; }
    .radio-chip input:checked + label { border-color:#7FB3F0; background:rgba(127,179,240,.28);
                                        color:#fff; font-weight:800; }

    /* 동의 항목 — privacy/_agree-items 가 쓰는 class 를 어두운 바탕에 맞춘다 */
    .checkall { display:flex; align-items:center; gap:9px; padding:12px 13px; margin-bottom:12px;
                border:1px solid rgba(255,255,255,.26); border-radius:12px;
                background:rgba(255,255,255,.08); font-size:13.5px; font-weight:700; cursor:pointer; }
    .checkall input { width:18px; height:18px; accent-color:#0B63CE; }
    .agree-item { border:1px solid rgba(255,255,255,.18); border-radius:12px; padding:12px 13px;
                  margin-bottom:10px; background:rgba(255,255,255,.06); }
    .agree-head { display:flex; align-items:flex-start; gap:8px; font-size:13px; font-weight:700;
                  line-height:1.55; }
    .agree-head .tag { font-size:10px; font-weight:800; padding:3px 6px; border-radius:5px; flex-shrink:0; }
    .tag.must { background:rgba(255,138,138,.24); color:#FFC9C9; }
    .tag.opt  { background:rgba(255,255,255,.14); color:rgba(255,255,255,.7); }
    .agree-radios { display:flex; gap:8px; margin-top:10px; }
    .agree-radios .radio-chip { min-width:0; }
    .agree-subline { font-size:12px; font-weight:700; color:#9EC6F5; margin-top:12px; }
    .agree-ask { font-size:12.5px; line-height:1.7; color:rgba(255,255,255,.82); margin-top:8px; }
    .detail-toggle { background:none; border:0; color:#9EC6F5; font-size:12px; font-weight:700;
                     padding:9px 0 0; cursor:pointer; font-family:inherit; }
    .detail-box { display:none; margin-top:10px; padding:12px; border-radius:10px;
                  background:rgba(0,0,0,.24); border:1px dashed rgba(255,255,255,.22);
                  font-size:12px; line-height:1.75; white-space:pre-line;
                  color:rgba(255,255,255,.84); max-height:300px; overflow-y:auto; }
    .detail-box.open { display:block; }
    .agree-table-wrap { overflow-x:auto; margin:10px 0; -webkit-overflow-scrolling:touch; }
    .agree-table { border-collapse:collapse; width:100%; min-width:560px; white-space:pre-line; }
    .agree-table th, .agree-table td { border:1px solid rgba(255,255,255,.22); padding:7px 8px;
                                       font-size:11.5px; line-height:1.6; text-align:left;
                                       vertical-align:top; color:rgba(255,255,255,.86); }
    .agree-table th { background:rgba(255,255,255,.12); color:#CFE2FA; font-weight:800; }
    .agree-table.narrow { min-width:0; }
    .entrust { margin-top:18px; padding-top:4px; }
    .entrust-title { margin:0 0 14px; font-size:15.5px; font-weight:800; letter-spacing:-.3px; color:#fff; }
    .entrust-intro { font-size:12.5px; line-height:1.7; color:rgba(255,255,255,.82); }

    .errbox { background:rgba(211,47,47,.2); border:1px solid rgba(255,138,138,.5); color:#FFD7D7;
              padding:12px 14px; border-radius:12px; font-size:13.5px; margin-bottom:14px; line-height:1.6; }
    .errbox ul { margin:8px 0 0; padding-left:18px; }

    .go { width:100%; padding:16px; border:0; border-radius:14px; font-size:16px; font-weight:800;
          font-family:inherit; cursor:pointer; color:#fff;
          background:linear-gradient(135deg,#1565C0,#0288D1);
          display:flex; align-items:center; justify-content:center; gap:8px; }
    .go:active { filter:brightness(.93); }
    .go[disabled] { opacity:.6; }
    .note { text-align:center; font-size:11.5px; color:rgba(255,255,255,.5); margin:12px 0 0; }

    .toast { position:fixed; left:50%; bottom:26px; transform:translate(-50%, 130%);
             background:#1B1F26; color:#fff; font-size:13.5px; padding:12px 16px;
             border-radius:11px; max-width:88vw; line-height:1.5; z-index:90;
             transition:transform .22s ease; box-shadow:0 8px 24px rgba(0,0,0,.34); }
    .toast.on { transform:translate(-50%, 0); }
  </style>
</head>
<body>

<div class="top">
  <a href="{{ route('m.login') }}" aria-label="로그인으로"><i class="bx bx-chevron-left"></i></a>
  <b>신규 신청</b>
</div>

<div class="hero">
  <h1>개인정보 수집·이용 동의서</h1>
  <p>자가도뇨(카테터) 지원 신청 · 콜로플라스트 코리아<br>
     작성하신 정보는 환자 지원 목적으로만 이용됩니다.</p>
</div>

{{-- 제출은 공개 폼과 같은 자리로 보낸다. from=m 이 붙으면 완료 화면도 모바일 것으로
     돌아온다 — /m/login 이 쓰는 표와 같은 이름이다(AuthController::모바일인가). --}}
<form method="POST" action="{{ route('privacy.submit', ['type' => 'catheter']) }}"
      class="container" id="regForm" novalidate>
  @csrf
  <input type="hidden" name="from" value="m">

  @if ($errors->any())
    <div class="errbox">
      입력 내용을 확인해 주십시오.
      <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="card">
    <h2><i class="bx bx-user"></i>신청자 정보</h2>

    <div class="field">
      <label>성명 <span class="req">*</span></label>
      <input type="text" name="name" value="{{ old('name') }}" placeholder="성명" autocomplete="name">
    </div>

    <div class="field">
      <label>연락처 <span class="req">*</span></label>
      <input type="tel" name="phone" value="{{ old('phone') }}" inputmode="numeric"
             placeholder="'-' 없이 숫자만" autocomplete="tel">
    </div>

    <div class="field">
      <label>주소</label>
      <div class="zip-row">
        <input type="text" name="zip" value="{{ old('zip') }}" placeholder="우편번호" readonly onclick="findZip()">
        <button type="button" onclick="findZip()"><i class="bx bx-search"></i> 우편번호 찾기</button>
      </div>
      <input type="text" name="addr1" value="{{ old('addr1') }}" placeholder="기본주소" style="margin-bottom:8px;">
      <input type="text" name="addr2" value="{{ old('addr2') }}" placeholder="상세주소">
    </div>

    <div class="field">
      <label>이메일 <span class="opt">(선택)</span></label>
      <input type="email" name="email" value="{{ old('email') }}" inputmode="email"
             placeholder="example@email.com" autocomplete="email">
    </div>

    <div class="field">
      <label>보험 <span class="req">*</span></label>
      <div class="radio-group">
        @foreach(['일반','보훈','산업재해'] as $i => $v)
          <div class="radio-chip">
            <input type="radio" id="ins{{ $i }}" name="insurance" value="{{ $v }}"
                   {{ old('insurance') === $v ? 'checked' : '' }}>
            <label for="ins{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
    </div>

    <div class="field" style="margin-bottom:0;">
      <label>지원 자격 <span class="opt">(선택)</span></label>
      <div class="radio-group">
        @foreach(['일반','차상위경감대상자','기초생활수급자'] as $i => $v)
          <div class="radio-chip" style="min-width:calc(50% - 8px);">
            <input type="radio" id="sup{{ $i }}" name="support_qualify" value="{{ $v }}"
                   {{ old('support_qualify') === $v ? 'checked' : '' }}>
            <label for="sup{{ $i }}">{{ $v }}</label>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card">
    <h2><i class="bx bx-shield-quarter"></i>개인정보 수집·이용 동의</h2>

    <label class="checkall">
      <input type="checkbox" onclick="checkAll(this)"> 아래 동의 항목에 모두 동의합니다.
    </label>

    {{-- 본문과 차례는 App\Support\ConsentTerms 한 곳에 있다 — 공개 폼과 같은 글이다 --}}
    @include('privacy._agree-items', [
      'idp'       => 'mic',
      'chip'      => true,
      'detail'    => 'detail-toggle',
      'box'       => 'detail-box',
      'useOld'    => true,
      'agreeAttr' => 'data-agree="1"',
    ])
  </div>

  <button type="submit" class="go" id="goBtn">
    <span id="goTxt">신청서 제출</span> <i class="bx bx-check"></i>
  </button>
  <p class="note">* 표시는 필수 입력·동의 항목입니다. · 문의 1588-7866</p>
</form>

<div class="toast" id="toast"></div>

<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
  let 알림때 = null;
  function 알림(글) {
    const el = document.getElementById('toast');
    el.textContent = 글;
    el.classList.add('on');
    clearTimeout(알림때);
    알림때 = setTimeout(() => el.classList.remove('on'), 3200);
  }

  /* 펼침ㆍ모두 동의ㆍ우편번호 — 공개 폼과 같은 이름으로 둔다. 포함해 쓰는
     동의 항목 부분이 toggleDetail·checkAll 을 그 이름으로 부른다. */
  function toggleDetail(btn) {
    const box = btn.nextElementSibling;
    box.classList.toggle('open');
    btn.textContent = box.classList.contains('open') ? '접기 ▲' : '자세히보기 ▼';
  }

  function checkAll(cb) {
    document.querySelectorAll('input[data-agree="1"][value="동의함"]')
      .forEach(r => { r.checked = cb.checked; });
  }

  function findZip() {
    if (typeof daum === 'undefined' || !daum.Postcode) {
      알림('우편번호는 직접 입력해 주십시오.');
      document.querySelector('[name=zip]').removeAttribute('readonly');
      return;
    }
    new daum.Postcode({ oncomplete: function (d) {
      document.querySelector('[name=zip]').value   = d.zonecode;
      document.querySelector('[name=addr1]').value = d.roadAddress || d.jibunAddress;
      document.querySelector('[name=addr2]').focus();
    }}).open();
  }

  /* 보내기 전에 한 번 본다 — 어느 칸이 비었는지 그 자리에서 알려 주어야
     사람이 긴 화면을 헤매지 않는다. 서버 검증은 그대로 남는다. */
  const 필수입력 = { name: '성명', phone: '연락처' };
  const 필수동의 = {
    agree_general:         '일반정보의 수집·이용 동의',
    agree_sensitive:       '민감정보의 수집·이용 동의',
    agree_third_party:     '일반 개인정보의 제3자 제공 동의',
    agree_third_sensitive: '민감정보의 제3자 제공 동의',
  };

  document.getElementById('regForm').addEventListener('submit', function (e) {
    for (const [칸, 이름] of Object.entries(필수입력)) {
      const 값 = this.querySelector(`[name=${칸}]`);
      if (!값.value.trim()) {
        e.preventDefault();
        알림(이름 + '을(를) 입력해 주십시오.');
        값.focus();
        return;
      }
    }

    if (!this.querySelector('input[name=insurance]:checked')) {
      e.preventDefault();
      알림('보험 구분을 선택해 주십시오.');
      return;
    }

    for (const [칸, 이름] of Object.entries(필수동의)) {
      const 고른것 = this.querySelector(`input[name=${칸}]:checked`);
      if (!고른것 || 고른것.value !== '동의함') {
        e.preventDefault();
        알림('필수 동의 항목입니다 — ' + 이름);
        (고른것 || this.querySelector(`input[name=${칸}]`))
          ?.closest('.agree-item')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
    }

    document.getElementById('goBtn').disabled = true;
    document.getElementById('goTxt').textContent = '제출 중…';
  });
</script>
</body>
</html>
