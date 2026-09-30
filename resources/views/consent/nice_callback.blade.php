<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>본인확인 결과</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Pretendard', sans-serif;
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      min-height:100dvh; margin:0; background:#f0f4ff; color:#374151; text-align:center; padding:24px; }
    .icon { font-size:56px; margin-bottom:12px; }
    h1 { font-size:18px; margin:0 0 6px; }
    p  { font-size:13px; color:#6b7280; line-height:1.6; margin:0; }
  </style>
</head>
<body>
  @if($ok)
    <div class="icon">✅</div>
    <h1>본인확인 완료</h1>
    <p>{{ $name ?? '' }}님, 본인확인이 완료되었습니다.<br>잠시 후 서명 화면으로 돌아갑니다.</p>
  @else
    <div class="icon">⚠️</div>
    <h1>본인확인 실패</h1>
    <p>{{ $message ?? '본인확인에 실패했습니다.' }}<br>서명 화면에서 다시 시도해 주십시오.</p>
  @endif

  <script>
    (function () {
      var payload = {
        source: 'nice-identity',
        ok: @json($ok),
        message: @json($ok ? ($name ?? '') : ($message ?? '')),
      };
      /* 부모 창에 알린다 — 다만 **여기에만 기대지 않는다.** PASS 인증은 앱으로
         나갔다 돌아오므로 모바일에서는 부모-자식 관계가 끊긴 채 돌아오는 일이
         잦다. 그때는 서명 화면이 스스로 물어 알아내지만, 이 창은 스스로 닫히지
         못한다 — 사람에게 무엇을 하면 되는지 적어 준다. */
      var 부모있나 = false;
      try { 부모있나 = !!(window.opener && !window.opener.closed); } catch (e) { 부모있나 = false; }

      try {
        if (부모있나) window.opener.postMessage(payload, window.location.origin);
      } catch (e) { /* noop */ }

      if (부모있나) {
        setTimeout(function () { try { window.close(); } catch (e) {} }, 1200);
      } else if (payload.ok) {
        var 알림 = document.createElement('div');
        알림.style.cssText = 'margin-top:12px;color:#6B7280;font-size:13px;line-height:1.6;';
        알림.innerHTML = '이 창을 닫고 <b>서명 화면으로 돌아가</b> 주십시오.<br>'
                       + '본인확인은 정상으로 처리되었습니다.';
        document.body.appendChild(알림);
      }
    })();
  </script>
</body>
</html>
