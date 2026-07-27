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
    <p>{{ $message ?? '본인확인에 실패했습니다.' }}<br>서명 화면에서 다시 시도해 주세요.</p>
  @endif

  <script>
    (function () {
      var payload = {
        source: 'nice-identity',
        ok: @json($ok),
        message: @json($ok ? ($name ?? '') : ($message ?? '')),
      };
      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage(payload, window.location.origin);
        }
      } catch (e) { /* noop */ }
      setTimeout(function () { try { window.close(); } catch (e) {} }, 1200);
    })();
  </script>
</body>
</html>
