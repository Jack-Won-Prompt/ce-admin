{{-- 현금영수증 한 장짜리 문서 — 서식은 조각이 그린다.

     같은 조각이 공단 팩스 합본에도 끼워진다(resources/views/prescriptions/fax-pdf.blade.php).
     서식이 한 벌이어야 종이와 팩스가 같은 것을 보여 준다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>현금영수증_{{ $doc['approvalNo'] }}</title>
<style>
  @page { margin: 0; }
  html, body { margin: 0; padding: 0; }
  .sheet { padding: 12mm; }
</style>
</head>
<body>
<div class="sheet">
  @include('documents._cash_receipt_form')
</div>
</body>
</html>
