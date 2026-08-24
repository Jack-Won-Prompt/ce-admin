{{-- 전자세금계산서 한 장짜리 문서 — 서식은 조각이 그린다.

     같은 조각이 공단 팩스 합본에도 끼워진다(resources/views/prescriptions/fax-pdf.blade.php).
     서식이 한 벌이어야 종이와 팩스가 같은 것을 보여 준다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>전자세금계산서_{{ $doc['ntsNo'] }}</title>
<style>
  @page { margin: 0; }
  html, body { margin: 0; padding: 0; }
  /* 서식은 182mm 짜리다. A4 왼쪽 위에 그대로 앉힌다 — 원본도 그렇게 나온다. */
  .sheet { padding: 11mm 12mm; }
</style>
</head>
<body>
<div class="sheet">
  @include('documents._tax_invoice_form')
</div>
</body>
</html>
