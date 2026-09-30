<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>본인확인</title>
<style>
  body { margin:0; display:flex; align-items:center; justify-content:center; height:100vh;
         background:#F6F7F9; color:#1F2937; font-size:14px;
         font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',sans-serif; }
  .box { text-align:center; padding:24px; }
  .big { font-size:18px; font-weight:800; margin-bottom:6px; }
  .no  { color:#B54708; }
</style>
</head>
<body>
{{-- NICE 표준창은 팝업으로 열린다. 결과를 부모 창에 건네고 스스로 닫는다 —
     사람이 이 화면을 볼 일은 잠깐이거나, 잘못됐을 때뿐이다. --}}
<div class="box">
  @if($말)
    <div class="big no">본인확인을 마치지 못했습니다</div>
    <div>{{ $말 }}</div>
    <div style="margin-top:10px;color:#6B7280;">이 창을 닫고 다시 시도해 주십시오.</div>
  @else
    <div class="big">본인확인이 끝났습니다</div>
    <div style="color:#6B7280;" id="닫힘안내">잠시 후 이 창이 닫힙니다.</div>
  @endif
</div>
<script>
  /* 부모 창에 알린다 — 다만 **여기에만 기대지 않는다.** PASS 인증은 앱으로 나갔다
     돌아오므로 모바일에서는 부모-자식 관계가 끊긴 채 돌아오는 일이 잦다. 그때는
     서명 화면이 스스로 물어 알아내지만(그 화면의 물어보기), 이 창은 스스로 닫히지
     못한다 — 사람에게 무엇을 하면 되는지 적어 준다. */
  var 부모있나 = false;
  try { 부모있나 = !!(window.opener && !window.opener.closed); } catch (e) { 부모있나 = false; }
  try { if (부모있나) window.opener.postMessage(@json($말 ? 'nice-fail' : 'nice-done'), '*'); } catch (e) {}

  @if(! $말)
  if (부모있나) {
    setTimeout(function () { try { window.close(); } catch (e) {} }, 900);
  } else {
    var 안내 = document.getElementById('닫힘안내');
    if (안내) 안내.innerHTML = '이 창을 닫고 <b>서명 화면으로 돌아가</b> 주십시오.<br>'
                            + '본인확인은 정상으로 처리되었습니다.';
  }
  @endif
</script>
</body>
</html>
