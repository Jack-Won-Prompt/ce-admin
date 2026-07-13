<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>@yield('title', '개인정보 수집·이용 동의서') | 콜로플라스트 코리아</title>
<style>
  :root{
    --brand:#0a3d62; --brand-2:#1e5f8e; --accent:#2e86de;
    --bg:#f4f6f9; --card:#fff; --line:#e3e8ef; --text:#1f2d3d; --muted:#6b7a90;
    --danger:#e74c3c; --ok:#27ae60; --radius:12px;
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  body{margin:0;background:var(--bg);color:var(--text);
    font-family:'Noto Sans KR','Malgun Gothic',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;
    font-size:15px;line-height:1.6;-webkit-text-size-adjust:100%;}
  .wrap{max-width:560px;margin:0 auto;min-height:100vh;background:var(--bg);}
  .appbar{position:sticky;top:0;z-index:10;background:var(--brand);color:#fff;padding:14px 18px;
    display:flex;align-items:center;gap:10px;box-shadow:0 2px 10px rgba(10,61,98,.18);}
  .appbar .logo{font-weight:800;letter-spacing:-.3px;font-size:16px;}
  .appbar .sub{font-size:12px;opacity:.85;margin-left:auto;}
  .hero{background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#fff;padding:22px 18px 26px;}
  .hero h1{margin:0 0 4px;font-size:20px;font-weight:800;letter-spacing:-.5px;}
  .hero p{margin:0;font-size:13px;opacity:.9;}
  .container{padding:16px;}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
    padding:18px 16px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.03);}
  .card h2{margin:0 0 14px;font-size:15px;font-weight:800;color:var(--brand);
    display:flex;align-items:center;gap:7px;padding-bottom:10px;border-bottom:2px solid var(--line);}
  .field{margin-bottom:14px;}
  .field:last-child{margin-bottom:0;}
  .field label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:var(--text);}
  .req{color:var(--danger);font-size:12px;margin-left:2px;}
  .opt{color:var(--muted);font-size:11px;margin-left:4px;font-weight:500;}
  input[type=text],input[type=tel],input[type=email],input[type=date],select,textarea{
    width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:9px;font-size:15px;
    background:#fbfcfe;color:var(--text);font-family:inherit;transition:border-color .15s,box-shadow .15s;}
  input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(46,134,222,.12);background:#fff;}
  .row{display:flex;gap:8px;}
  .row > *{flex:1;}
  .radio-group{display:flex;gap:8px;flex-wrap:wrap;}
  .radio-chip{flex:1;min-width:calc(33% - 8px);position:relative;}
  .radio-chip input{position:absolute;opacity:0;pointer-events:none;}
  .radio-chip label{display:block;text-align:center;padding:11px 8px;border:1px solid var(--line);
    border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;color:var(--muted);background:#fbfcfe;margin:0;}
  .radio-chip input:checked + label{border-color:var(--accent);background:#eaf3fd;color:var(--brand);font-weight:800;}
  .agree-item{border:1px solid var(--line);border-radius:10px;padding:12px 13px;margin-bottom:10px;background:#fbfcfe;}
  .agree-head{display:flex;align-items:flex-start;gap:8px;font-size:13.5px;font-weight:700;}
  .agree-head .tag{font-size:10px;font-weight:800;padding:2px 6px;border-radius:5px;flex-shrink:0;}
  .tag.must{background:#fdecea;color:var(--danger);}
  .tag.opt{background:#eef1f5;color:var(--muted);}
  .agree-radios{display:flex;gap:8px;margin-top:10px;}
  .agree-radios .radio-chip{min-width:0;}
  .detail-toggle{background:none;border:none;color:var(--accent);font-size:12px;font-weight:700;
    cursor:pointer;padding:6px 0 0;text-decoration:underline;font-family:inherit;}
  .detail-box{display:none;margin-top:10px;padding:12px;background:#fff;border:1px dashed var(--line);
    border-radius:8px;font-size:12px;line-height:1.7;color:var(--muted);white-space:pre-line;}
  .detail-box.open{display:block;}
  .checkall{display:flex;align-items:center;gap:9px;padding:13px;background:#eaf3fd;border:1px solid #cfe4fb;
    border-radius:10px;margin-bottom:14px;font-weight:800;color:var(--brand);font-size:14px;cursor:pointer;}
  .checkall input{width:20px;height:20px;accent-color:var(--accent);}
  .btn{display:block;width:100%;padding:15px;border:none;border-radius:11px;font-size:16px;font-weight:800;
    cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;transition:filter .15s;}
  .btn-primary{background:var(--brand);color:#fff;box-shadow:0 4px 14px rgba(10,61,98,.25);}
  .btn-primary:active{filter:brightness(.92);}
  .btn-line{background:#fff;color:var(--brand);border:1.5px solid var(--brand);}
  .errbox{background:#fdecea;border:1px solid #f5c6c0;color:var(--danger);border-radius:9px;
    padding:11px 13px;font-size:13px;margin-bottom:14px;}
  .errbox ul{margin:4px 0 0;padding-left:18px;}
  .footer{padding:22px 18px;color:var(--muted);font-size:11px;line-height:1.7;border-top:1px solid var(--line);
    background:#eef1f5;}
  .footer b{color:var(--text);}
  .note{font-size:12px;color:var(--muted);margin-top:6px;}
</style>
</head>
<body>
  <div class="wrap">
    <div class="appbar">
      <span class="logo">Coloplast</span>
      <span class="sub">콜로플라스트 코리아</span>
    </div>
    @yield('content')
    <div class="footer">
      <b>상호</b> : 콜로플라스트 코리아 주식회사 &nbsp;|&nbsp; <b>대표이사</b> : 이선우<br>
      <b>소재지</b> : 서울시 마포구 마포대로 86 창강빌딩 9층 910호<br>
      <b>사업자등록번호</b> : 101-86-34660 &nbsp;|&nbsp; <b>대표번호</b> : 1588-7866<br>
      <b>개인정보관리책임자</b> : 이선우<br>
      <span style="opacity:.7;">Copyright &copy; Coloplast Korea. All Rights Reserved.</span>
    </div>
  </div>
  <script>
    function toggleDetail(btn){
      var box = btn.nextElementSibling;
      box.classList.toggle('open');
      btn.textContent = box.classList.contains('open') ? '접기 ▲' : '자세히보기 ▼';
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
        alert('우편번호는 직접 입력해 주세요.');
      }
    }
  </script>
  @stack('scripts')
</body>
</html>
