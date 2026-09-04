{{-- 카드 매출전표 한 장짜리 문서.

     토스가 준 승인 내용을 그대로 옮겨 적는다(App\Support\CardSalesSlip).
     끝에 토스 영수증 주소를 함께 적어, 받은 쪽이 그 화면과 대조할 수 있게 한다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>카드매출전표_{{ $doc['approvalNo'] }}</title>
<style>
  @page { margin: 0; }
  html, body { margin: 0; padding: 0; font-family: 'NanumGothic', sans-serif; color: #111; }
  .sheet { padding: 14mm; }

  .title { font-size: 15pt; font-weight: bold; text-align: center; letter-spacing: 4pt; }
  .kind  { font-size: 10pt; text-align: center; margin-top: 3mm; }

  table { width: 100%; border-collapse: collapse; margin-top: 6mm; font-size: 9.5pt; }
  th, td { border: 1px solid #999; padding: 2.4mm 3mm; }
  th { background: #f2f2f2; width: 26%; text-align: left; font-weight: normal; color: #333; }
  td.num { text-align: right; }
  .total th, .total td { font-weight: bold; background: #fafafa; }

  .sec { font-size: 10pt; font-weight: bold; margin-top: 7mm; }
  .note { font-size: 8pt; color: #555; margin-top: 6mm; line-height: 1.6; }
  .url  { font-size: 7.5pt; color: #333; word-break: break-all; }
</style>
</head>
<body>
<div class="sheet">

  <div class="title">카드 매출전표</div>
  <div class="kind">{{ $doc['docKind'] }}</div>

  <div class="sec">승인 내용</div>
  <table>
    <tr><th>카드사</th><td>{{ $doc['issuer'] }}</td></tr>
    <tr><th>카드번호</th><td>{{ $doc['cardNo'] }}</td></tr>
    <tr><th>승인번호</th><td>{{ $doc['approvalNo'] }}</td></tr>
    <tr><th>승인일시</th><td>{{ $doc['approvedAt'] }}</td></tr>
    <tr><th>할부</th><td>{{ $doc['install'] }}</td></tr>
    <tr><th>카드구분</th><td>{{ $doc['cardType'] }}</td></tr>
  </table>

  <div class="sec">거래 내용</div>
  <table>
    <tr><th>주문번호</th><td>{{ $doc['orderNo'] }}</td></tr>
    <tr><th>구매자</th><td>{{ $doc['buyer'] }}</td></tr>
    <tr><th>품명</th><td>{{ $doc['productName'] }}</td></tr>
    <tr><th>공급가액</th><td class="num">{{ $doc['supply'] }} 원</td></tr>
    <tr><th>부가세</th><td class="num">{{ $doc['vat'] }} 원</td></tr>
    <tr class="total"><th>합계</th><td class="num">{{ $doc['amount'] }} 원</td></tr>
  </table>

  <div class="sec">가맹점</div>
  <table>
    <tr><th>상호</th><td>{{ $shop['name'] }}</td></tr>
    <tr><th>사업자등록번호</th><td>{{ $shop['bizNo'] }}</td></tr>
    <tr><th>대표자</th><td>{{ $shop['ceo'] }}</td></tr>
    <tr><th>전화번호</th><td>{{ $shop['tel'] }}</td></tr>
    <tr><th>주소</th><td>{{ $shop['addr'] }}</td></tr>
  </table>

  @if($doc['receiptUrl'])
  <div class="note">
    이 전표는 토스페이먼츠 승인 내용을 옮겨 적은 것입니다. 아래 주소에서 원본을 확인할 수 있습니다.<br>
    <span class="url">{{ $doc['receiptUrl'] }}</span>
  </div>
  @endif

</div>
</body>
</html>
