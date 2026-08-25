{{-- 현금영수증 — 받은 서식(「현금영수증_템플릿.html」) 그대로.

     홈택스가 보여 주는 그 종이다. 예전에는 「국세청 현금영수증 발행 확인증」이라는
     두 칸짜리 표를 우리가 지어 냈는데, 받는 쪽이 아는 종이가 아니었다.

     이 조각은 홀로도 쓰이고(내려받는 PDF) 공단 팩스 합본 안에도 끼워진다. 그래서
     모든 선택자를 .cr-form 아래로 넣었다 — 합본의 글꼴ㆍ표 모양을 건드리면 안 된다.

     원본에서 바꾼 것은 dompdf 가 못 알아듣는 것뿐이다:
       · CSS 변수 → 값을 직접 적는다
       · grid 로 나란히 세우던 구역 제목 → 표 한 줄로
       · ::before 의 파란 막대 → 칸의 왼쪽 테두리로
       · 그러데이션 → 한 가지 색으로 (dompdf 는 흉내만 내다 만다)
       · td 의 height → 안쪽 여백으로 (dompdf 에서 height 는 글 높이에 더해진다)
       · table-layout:fixed 를 쓰지 않는다 — dompdf 가 칸 폭을 잘못 잡는다 --}}
<style>
  .cr-form * { box-sizing: border-box; font-family: 'NanumGothic', sans-serif; }
  .cr-form { color: #151515; font-size: 9.5pt; line-height: 1.35; }

  .cr-form .cr-page { border: 0.3mm solid #7e8791; background: #f5f7fb; padding: 5mm 6mm 4mm; }
  .cr-form .cr-title { margin: 0 0 2.5mm; font-size: 18pt; font-weight: 700; letter-spacing: -0.04em; }
  .cr-form .cr-body { border: 0.3mm solid #d7d7d7; background: #ffffff; padding: 5mm 5mm 4.5mm; }

  .cr-form table { width: 100%; border-collapse: collapse; }
  .cr-form .cr-tbl { border-top: 0.3mm solid #8eb2e0; }
  .cr-form .cr-tbl td { padding: 2.2mm 3mm; vertical-align: middle;
                        border-bottom: 0.25mm solid #dcdcdc; letter-spacing: -0.02em; }
  .cr-form .cr-tbl td.lbl { width: 15%; background: #f1f6ff; font-weight: 700; }
  .cr-form .cr-tbl td.val { width: 35%; }
  .cr-form .cr-tbl td.pay { width: 15%; background: #fff7f7; font-weight: 700; }
  .cr-form .cr-tbl td.amt { width: 35%; text-align: right; }
  .cr-form .cr-tbl td.tall { padding-top: 5.5mm; padding-bottom: 5.5mm; }

  /* 구역 제목 — 왼쪽에 파란 막대, 아래에 파란 선 */
  .cr-form .cr-head { margin-top: 4mm; }
  .cr-form .cr-head td { padding: 0.5mm 0 1.6mm 2.6mm; border-left: 0.8mm solid #447cf1;
                         border-bottom: 0.25mm solid #7ea4d8; font-size: 11pt; font-weight: 700;
                         letter-spacing: -0.03em; }

  .cr-form .cr-foot { padding-top: 2.5mm; font-size: 9pt; letter-spacing: -0.03em; }
  .cr-form .cr-foot p { margin: 0; }
  .cr-form .cr-foot .second { margin-top: 6mm; }
  .cr-form .cr-foot b { font-weight: 700; }
</style>

<div class="cr-form">
  <div class="cr-page">
    <div class="cr-title">현금영수증</div>

    <div class="cr-body">

      {{-- 발행 정보 --}}
      <table class="cr-tbl">
        <tbody>
          <tr>
            <td class="lbl">식별번호</td><td class="val">{{ $doc['identifier'] }}</td>
            <td class="lbl">문서형태</td><td class="val">{{ $doc['docKind'] }}</td>
          </tr>
          <tr>
            <td class="lbl">거래구분</td><td class="val">{{ $doc['purpose'] }}</td>
            <td class="lbl">거래유형</td><td class="val">{{ $doc['dealType'] }}</td>
          </tr>
          <tr>
            <td class="lbl">거래일시</td><td class="val">{{ $doc['issuedAt'] }}</td>
            <td class="lbl" rowspan="2">국세청<br>승인번호</td>
            <td class="val" rowspan="2">{{ $doc['approvalNo'] }}</td>
          </tr>
          <tr>
            <td class="lbl">전송일자</td><td class="val">{{ $doc['sentAt'] }}</td>
          </tr>
        </tbody>
      </table>

      {{-- 구매정보 · 결제정보 --}}
      <table class="cr-head">
        <tbody><tr><td style="width:50%">구매정보</td><td style="width:50%">결제정보</td></tr></tbody>
      </table>
      <table class="cr-tbl">
        <tbody>
          <tr>
            <td class="lbl">구매자명</td><td class="val">{{ $doc['buyer'] }}</td>
            <td class="pay">거래금액</td><td class="amt">{{ $doc['amount'] }}원</td>
          </tr>
          <tr>
            <td class="lbl">주문번호</td><td class="val">{{ $doc['orderNo'] }}</td>
            <td class="pay">공급가액</td><td class="amt">{{ $doc['supply'] }}원</td>
          </tr>
          <tr>
            <td class="lbl" rowspan="2">주문<br>상품명</td>
            <td class="val" rowspan="2">{{ $doc['productName'] }}</td>
            <td class="pay">부가세</td><td class="amt">{{ $doc['vat'] }}원</td>
          </tr>
          <tr>
            <td class="pay">봉사료</td><td class="amt">{{ $doc['tip'] }}원</td>
          </tr>
        </tbody>
      </table>

      {{-- 가맹점 --}}
      <table class="cr-head" style="margin-top:5mm;">
        <tbody><tr><td>현금영수증 가맹점</td></tr></tbody>
      </table>
      <table class="cr-tbl">
        <tbody>
          <tr>
            <td class="lbl">상호</td><td class="val" colspan="3">{{ $shop['name'] }}</td>
          </tr>
          <tr>
            <td class="lbl">사업자번호</td><td class="val">{{ $shop['bizNo'] }}</td>
            <td class="lbl">종사업장</td><td class="val">{{ $shop['subBiz'] }}</td>
          </tr>
          <tr>
            <td class="lbl">대표자</td><td class="val">{{ $shop['ceo'] }}</td>
            <td class="lbl">전화번호</td><td class="val">{{ $shop['tel'] }}</td>
          </tr>
          <tr>
            <td class="lbl tall">주소</td><td class="val tall" colspan="3">{{ $shop['addr'] }}</td>
          </tr>
        </tbody>
      </table>

    </div>

    <div class="cr-foot">
      <p>본 현금영수증은 발행 익일 오전 9시부터 국세청 홈택스(www.hometax.go.kr)에서 확인 가능합니다.</p>
      <p class="second">현금영수증 문의 : <b>☎ 126-1-1(국세상담센터)</b></p>
    </div>
  </div>
</div>
