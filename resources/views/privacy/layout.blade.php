<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>@yield('title', '개인정보 수집·이용 동의서') | 콜로플라스트 코리아</title>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preload" href="https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF_Medium.woff2" as="font" type="font/woff2" crossorigin>
{{-- 시안: Figma Website › 개인정보 수집 이용 동의서 (513:2572 선택 · 513:800 카테터 · 513:1946 장루).
     청록 판 위에 둥근 흰 카드 하나, 그 아래 띠에 로고. 1920 에서 시안 그대로이고, 좁아지면 칸이 한 줄로 쌓인다. --}}
<style>
@include('partials._design-tokens')
@include('partials._website-font')

  *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
  body{background:var(--primary-500);color:var(--gray-1000);word-break:keep-all;
    font-family:'NEXON Lv2 Gothic OTF','Apple SD Gothic Neo','Malgun Gothic',sans-serif;
    font-size:14px;line-height:1.7;-webkit-text-size-adjust:100%;-webkit-font-smoothing:antialiased;}
  a{color:inherit;text-decoration:none;}
  button,input,select,textarea{font:inherit;color:inherit;}

  /* ── 판 · 카드 · 로고 띠 ── */
  .pv-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;gap:24px;padding:16px;}
  .pv-card{position:relative;align-self:stretch;display:flex;flex-direction:column;align-items:center;gap:60px;
    padding:60px;border-radius:40px;background:var(--gray-0);overflow:hidden;}
  .pv-card--fill{flex:1 1 auto;justify-content:center;}
  .pv-logo{flex:none;display:block;width:106.75px;height:28px;}
  .pv-corp{padding:8px 16px 24px;text-align:center;font-size:12px;line-height:1.7;color:var(--gray-0);opacity:.6;}
  .pv-corp b{font-weight:500;}

  /* ── 머리 — 이전 · 제목 · 부제 · 완료 ── */
  .pv-head{position:relative;align-self:stretch;display:flex;flex-direction:column;align-items:center;gap:20px;}
  .pv-title{font-size:32px;font-weight:500;line-height:45px;color:var(--gray-1000);text-align:center;white-space:nowrap;}
  .pv-title em{font-style:normal;color:var(--primary-500);}
  .pv-chip{padding:8px 16px;border-radius:12px;background:var(--gray-100);
    font-size:14px;font-weight:500;line-height:24px;color:var(--gray-600);white-space:nowrap;}
  .pv-hbtn{position:absolute;top:0;height:40px;display:inline-flex;align-items:center;justify-content:center;gap:12px;
    padding:0 16px;border-radius:12px;font-size:16px;font-weight:500;line-height:27px;white-space:nowrap;cursor:pointer;
    transition:background-color .2s,border-color .2s,box-shadow .2s;}
  .pv-hbtn--line{left:0;border:1px solid var(--gray-200);background:var(--gray-0);color:var(--gray-1000);}
  .pv-hbtn--line:hover{border-color:var(--gray-300);background:var(--gray-50);}
  .pv-hbtn--done{right:0;border:0;background:var(--primary-500);color:var(--gray-0);}
  .pv-hbtn--done:hover{background:var(--primary-600);box-shadow:0 8px 20px rgba(11,92,110,.24);}
  .pv-hbtn--home{top:60px;left:60px;}

  /* 아이콘은 시안에서 받은 선 하나를 가면으로 쓰고 색은 글자색을 따른다 */
  .pv-ico{flex:none;display:inline-block;background:currentColor;
    -webkit-mask:var(--ico) center/contain no-repeat;mask:var(--ico) center/contain no-repeat;}
  .pv-ico--20{width:20px;height:20px;}
  .pv-ico--16{width:16px;height:16px;}
  .pv-ico--back{transform:scaleX(-1);}

  /* ── 구획 ── */
  .pv-form{align-self:stretch;display:flex;flex-direction:column;align-items:stretch;gap:60px;}
  .pv-sec{display:flex;flex-direction:column;gap:16px;}
  .pv-sec h2{font-size:16px;font-weight:500;line-height:27px;color:var(--gray-1000);}
  /* 시안의 구분선은 높이 0 에 1px 선이다 — 자리를 먹지 않게 반 픽셀씩 당긴다 */
  .pv-rule{height:1px;margin:-.5px 0;background:var(--gray-200);}

  .pv-cols{display:flex;align-items:flex-start;gap:24px;}
  .pv-col{flex:1 1 0;min-width:0;display:flex;flex-direction:column;gap:8px;}
  .pv-field{display:flex;align-items:flex-start;gap:8px;}
  .pv-label{flex:none;width:100px;height:36px;display:flex;align-items:center;
    font-size:14px;font-weight:500;line-height:24px;color:var(--gray-700);white-space:nowrap;}
  .pv-label .req{margin-left:.25em;color:var(--primary-500);}
  .pv-label .opt{margin-left:.25em;color:var(--gray-500);}
  .pv-ctrl{flex:1 1 0;min-width:0;display:flex;flex-direction:column;gap:8px;}
  .pv-line{display:flex;gap:8px;}
  .pv-line > input{flex:1 1 0;min-width:0;}

  input[type=text],input[type=tel],input[type=email],input[type=date]{
    width:100%;height:36px;padding:0 12px;border:1px solid var(--gray-200);border-radius:8px;background:var(--gray-0);
    font-size:14px;font-weight:400;line-height:24px;color:var(--gray-1000);transition:border-color .15s,box-shadow .15s;}
  input::placeholder{color:var(--gray-500);opacity:1;}
  input:focus{outline:none;border-color:var(--primary-500);box-shadow:0 0 0 3px rgba(40,121,139,.12);}
  input[readonly]{cursor:pointer;}
  /* 날짜 칸 — 비어 있을 때 글자는 자리표시 색, 오른쪽 달력은 시안 아이콘(calendar-07) */
  input[type=date]{position:relative;}
  input[type=date].is-empty::-webkit-datetime-edit{color:var(--gray-500);}
  input[type=date]::-webkit-calendar-picker-indicator{width:16px;height:16px;margin:0;padding:0;cursor:pointer;opacity:1;
    background:url('{{ asset('images/website/icons/calendar-16.svg') }}') center/16px no-repeat;}

  .pv-btn-addr{flex:none;height:36px;padding:0 12px;border:1px solid var(--primary-500);border-radius:8px;background:var(--gray-0);
    font-size:14px;font-weight:400;line-height:24px;color:var(--primary-500);white-space:nowrap;cursor:pointer;transition:background-color .15s;}
  .pv-btn-addr:hover{background:var(--primary-50);}

  /* ── 라디오 칸 — 동그라미는 회색 고리, 고르면 청록 ── */
  .radio-group{flex:1 1 0;min-width:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;}
  .radio-group--2{grid-template-columns:repeat(2,minmax(0,1fr));}
  .radio-group--pair{display:contents;}
  .radio-chip{position:relative;min-width:0;}
  .radio-chip input{position:absolute;opacity:0;pointer-events:none;}
  .radio-chip label{height:36px;display:flex;align-items:center;gap:8px;padding:0 12px;
    border:1px solid var(--gray-200);border-radius:8px;background:var(--gray-0);cursor:pointer;
    font-size:14px;font-weight:400;line-height:24px;color:var(--gray-1000);white-space:nowrap;transition:border-color .15s,background-color .15s;}
  .radio-chip label::before{content:'';flex:none;width:14px;height:14px;border-radius:50%;
    background:radial-gradient(circle,var(--gray-0) 3.5px,transparent 3.6px),var(--gray-300);transition:background-color .15s;}
  .radio-chip label:hover{border-color:var(--gray-300);}
  .radio-chip input:checked + label{border-color:var(--primary-500);}
  .radio-chip input:checked + label::before{background:radial-gradient(circle,var(--gray-0) 3.5px,transparent 3.6px),var(--primary-500);}
  .radio-chip input:focus-visible + label{box-shadow:0 0 0 3px rgba(40,121,139,.2);}

  /* ── 전체 동의 막대 — 안 고르면 회색(카테터 시안), 고르면 청록(장루 시안) ── */
  .checkall{height:40px;display:flex;align-items:center;gap:8px;padding:0 16px;border-radius:8px;background:var(--gray-100);
    font-size:14px;font-weight:500;line-height:24px;color:var(--gray-500);cursor:pointer;transition:background-color .2s,color .2s;}
  .checkall input{position:absolute;opacity:0;pointer-events:none;}
  .checkall:has(input:checked){background:var(--primary-100);color:var(--primary-500);}
  .checkall:has(input:focus-visible){box-shadow:0 0 0 3px rgba(40,121,139,.2);}

  /* ── 동의 항목 — 제목 · 펼치기는 왼쪽, 동의함 · 동의하지 않음은 오른쪽(시안 한 줄 40) ──
     항목 마크업은 privacy/_agree-items 와 장루 화면이 같이 쓰는 모양이라 여기서는 칸만 옮긴다 */
  .agree-list{display:flex;flex-direction:column;gap:8px;}
  .agree-item{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;align-items:center;column-gap:12px;row-gap:8px;}
  .agree-head{grid-row:1;grid-column:1;font-size:14px;font-weight:500;line-height:24px;color:var(--gray-700);white-space:nowrap;}
  .agree-head .tag{color:var(--gray-500);}
  .agree-head .tag.must{color:var(--primary-500);}
  .agree-head .tag::before{content:'(';} .agree-head .tag::after{content:')';}
  .detail-toggle{grid-row:1;grid-column:2;justify-self:start;padding:0;border:0;border-bottom:1px solid var(--gray-400);background:none;
    font-size:12px;font-weight:500;line-height:20px;color:var(--gray-400);cursor:pointer;}
  .detail-toggle:hover{color:var(--gray-600);border-color:var(--gray-600);}
  /* 묻는 글은 둘째 줄에 못 박는다 — 그래야 동의 라디오가 제목 줄 오른쪽에 선다 */
  .agree-ask{grid-row:2;grid-column:1 / -1;font-size:13px;line-height:22px;color:var(--gray-700);}
  .agree-subline{grid-column:1 / 4;padding-left:12px;font-size:14px;font-weight:500;line-height:24px;color:var(--gray-700);}
  .agree-radios{grid-column:4;display:flex;gap:8px;}
  .agree-radios .radio-chip{width:320px;}
  .agree-radios .radio-chip label{height:40px;}
  .detail-box{grid-column:1 / -1;display:none;padding:12px 16px;border:1px solid var(--gray-200);border-radius:8px;background:var(--gray-50);
    font-size:13px;font-weight:400;line-height:22px;color:var(--gray-700);white-space:pre-line;}
  .detail-box.open{display:block;}
  .agree-table-wrap{overflow-x:auto;margin:8px 0;-webkit-overflow-scrolling:touch;}
  .agree-table{border-collapse:collapse;width:100%;min-width:560px;white-space:pre-line;}
  .agree-table th,.agree-table td{border:1px solid var(--gray-200);padding:7px 8px;font-size:12px;line-height:1.6;vertical-align:top;text-align:left;}
  .agree-table th{background:var(--gray-100);color:var(--gray-800);font-weight:500;}

  .pv-note{text-align:center;font-size:14px;font-weight:400;line-height:24px;color:var(--gray-500);white-space:nowrap;}
  .errbox{padding:12px 16px;border:1px solid var(--alert-100);border-left:3px solid var(--alert-500);border-radius:8px;background:var(--alert-50);
    font-size:14px;line-height:1.7;color:var(--gray-900);}
  .errbox ul{margin:4px 0 0;padding-left:18px;}

  /* ── 좁은 화면 — 시안 없는 폭. 칸을 한 줄로 쌓는다(문자로 받은 동의서 주소는 대개 휴대폰에서 열린다) ── */
  @media (max-width: 1100px){
    .pv-cols{flex-direction:column;align-items:stretch;}
    .agree-item{grid-template-columns:minmax(0,1fr) auto;}
    .agree-head{white-space:normal;}
    .agree-radios{grid-column:1 / -1;}
    .agree-radios .radio-chip{flex:1 1 0;width:auto;}
    .agree-subline{grid-column:1 / -1;padding-left:0;}
  }
  @media (max-width: 640px){
    .pv-page{padding:12px;gap:20px;}
    .pv-card{padding:28px 20px;border-radius:28px;gap:36px;}
    .pv-head{padding-top:56px;}
    .pv-hbtn--home{top:28px;left:20px;}
    .pv-title{font-size:24px;line-height:1.4;white-space:normal;}
    .pv-chip{white-space:normal;text-align:center;}
    .pv-field{flex-direction:column;gap:4px;}
    .pv-label{height:auto;}
    .pv-ctrl{align-self:stretch;}
    .pv-line{flex-wrap:wrap;}
    .pv-line > input{flex:1 1 40%;}
    .radio-group{grid-template-columns:repeat(2,minmax(0,1fr));}
    .pv-note{white-space:normal;}
  }
</style>
</head>
<body>
  <div class="pv-page">
    <main class="pv-card @yield('card-class')">
      @yield('content')
    </main>
    <img class="pv-logo" src="{{ asset('images/website/brand/logo-light.png') }}" width="107" height="28" alt="CE Admin · Coloplast Korea">
  </div>
  {{-- 사업자 정보는 시안에 없지만 개인정보를 받는 화면이라 걷지 않는다 — 첫 화면(시안 판) 밖, 로고 띠 아래에 작게 둔다 --}}
  <p class="pv-corp">
    <b>상호</b> : 콜로플라스트 코리아 주식회사 &nbsp;|&nbsp; <b>대표이사</b> : 이선우 &nbsp;|&nbsp; <b>소재지</b> : 서울시 마포구 마포대로 86 창강빌딩 9층 910호<br>
    <b>사업자등록번호</b> : 101-86-34660 &nbsp;|&nbsp; <b>대표번호</b> : 1588-7866 &nbsp;|&nbsp; <b>개인정보관리책임자</b> : 이선우<br>
    Copyright ⓒ Coloplast Korea. All Rights Reserved.
  </p>
  <script>
    function toggleDetail(btn){
      var box = btn.nextElementSibling;
      box.classList.toggle('open');
      btn.textContent = box.classList.contains('open') ? '접기' : '펼치기';
    }
    function checkAll(cb){
      document.querySelectorAll('input[data-agree="1"][value="동의함"]').forEach(function(r){ r.checked = cb.checked; });
    }
    // 우편번호 검색 (Daum 우편번호 서비스가 있으면 사용, 없으면 수동입력)
    function findZip(){
      if (typeof daum !== 'undefined' && daum.Postcode){
        new daum.Postcode({ oncomplete:function(d){
          document.querySelector('[name=zip]').value = d.zonecode;
          document.querySelector('[name=addr1]').value = d.roadAddress || d.jibunAddress;
          document.querySelector('[name=addr2]').focus();
        }}).open();
      } else {
        ceAlert('우편번호는 직접 입력해 주세요.', { tone: 'warning' });
      }
    }
    /* 시안의 낱말은 「펼치기 / 접기」다. 항목 조각(_agree-items)은 서명 화면과 같이 써서 글을 거기서 바꾸지 않고 여기서 고친다. */
    document.querySelectorAll('.detail-toggle').forEach(function(b){
      var box = b.nextElementSibling;
      b.textContent = box && box.classList.contains('open') ? '접기' : '펼치기';
    });
    /* 날짜 칸이 비었는지 — 빈 칸만 자리표시 색으로 보인다 */
    document.querySelectorAll('input[type=date]').forEach(function(el){
      var mark = function(){ el.classList.toggle('is-empty', !el.value); };
      mark(); el.addEventListener('input', mark); el.addEventListener('change', mark);
    });
  </script>

  {{-- 커스텀 알림/확인 다이얼로그 (브라우저 기본 alert/confirm 대체) --}}
  @include('partials.dialog')

  @stack('scripts')
</body>
</html>
