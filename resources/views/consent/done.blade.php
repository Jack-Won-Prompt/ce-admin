<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>처리 완료 — 위임동의</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Pretendard', sans-serif; background: #f0f4ff; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(27,102,245,.10); width: 100%; max-width: 440px; padding: 48px 32px; text-align: center; }
    .icon { font-size: 56px; margin-bottom: 18px; }
    h1 { font-size: 20px; font-weight: 800; margin-bottom: 10px; }
    p  { font-size: 14px; color: #6b7280; line-height: 1.7; }
    .agreed   { color: #12B76A; }
    .declined { color: #6b7280; }
  </style>
</head>
<body>
  <div class="card">
    @if($consent->status === 'agreed')
      <div class="icon">✅</div>
      <h1 class="agreed">동의가 완료되었습니다</h1>
      <p>건강보험 급여 위임동의가 정상적으로 접수되었습니다.<br>이 창을 닫으셔도 됩니다.</p>
    @else
      <div class="icon">❌</div>
      <h1 class="declined">거절 처리되었습니다</h1>
      <p>위임동의를 거절하셨습니다.<br>문의 사항은 담당자에게 연락주세요.</p>
    @endif
  </div>
</body>
</html>
