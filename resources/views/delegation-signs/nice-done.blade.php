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
    <div style="color:#6B7280;">잠시 후 이 창이 닫힙니다.</div>
  @endif
</div>
<script>
  try { if (window.opener) window.opener.postMessage(@json($말 ? 'nice-fail' : 'nice-done'), '*'); } catch (e) {}
  @if(! $말)
    setTimeout(() => { try { window.close(); } catch (e) {} }, 900);
  @endif
</script>
</body>
</html>
