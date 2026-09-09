{{-- 거래명세서 — 위드웍스(medical)의 「출고 거래명세서」와 같은 틀이다.

     원본 서식
       화면  withworks/resources/views/main/medical/standard/print/account_salesorder_ship.blade.php
       모양  withworks/public/assets/colo_print/print.css (#colo_print_wrap · table_type01 · table_type02)

     같은 서류가 두 시스템에서 서로 다르게 생기면, 받는 쪽은 어느 것이 진짜인지
     묻게 된다. 칸 이름ㆍ차례ㆍ폭ㆍ글자 크기ㆍ테두리를 원본 값 그대로 옮겼다
     (2026-09-09 지시).

     dompdf 가 알아듣지 못하는 것만 바꿨다 — **생김새는 바꾸지 않는다**:

       · display:flex  → 표로. 원본의 .print_top(로고|제목|오른쪽)과
                         .table_layout01(공급자|공급받는자)이 flex 로 나란히 서는데,
                         dompdf 는 flex 를 모른다. 칸 둘ㆍ셋짜리 표로 같은 자리를 만든다.
       · rowspan="100%" → rowspan="5". 원본의 값은 HTML 에 없는 것이라 브라우저가
                         눈감아 주는 것이고, dompdf 는 눈감아 주지 않는다.
       · Pretendard    → NanumGothic. dompdf 에 심어 둔 글꼴이 이것뿐이다.
       · 도장 그림      → placeholder 를 부르지 않는다(setIsRemoteEnabled(false)).

     **우리에게 없는 값은 비운다 — 지어내지 않는다.** LOTㆍ유효기간ㆍ등급ㆍUDI 는
     창고가 아는 값이라 우리 주문 줄에 없다. 위드웍스에서 받아 올 길이 생기면
     그 칸만 채우면 된다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>거래명세서_{{ $doc['documentNo'] }}</title>
<style>
  /* ── 원본 print.css 에서 이 서식이 쓰는 것만 옮겼다 ────────── */

  /* 글꼴도 원본 그대로 Pretendard 다. 원본은 CDN 에서 woff 로 받아 쓰는데 dompdf 는
     woff 를 읽지 못하고 바깥을 부르지도 않는다(setIsRemoteEnabled(false)) —
     같은 글꼴을 ttf 로 바꿔 storage/fonts 에 심어 두었다(installed-fonts.json).
     글꼴이 다르면 글자 너비가 달라져 같은 칸에서 줄이 접히고 안 접히고가 갈린다. */

  @page { margin: 0; }
  * { box-sizing: border-box; }
  /* 원본 print.css 의 body 와 같다. break-word 가 있어야 보험코드처럼 띄어쓰기
     없는 긴 값이 칸 안에서 두 줄로 접힌다 — 없으면 칸을 밀고 나간다. */
  html, body { margin: 0; padding: 0; font-family: 'Pretendard', 'NanumGothic', sans-serif;
               word-break: keep-all; word-wrap: break-word; background: #fff; }
  table { border-collapse: collapse; border-spacing: 0; }

  /* #colo_print_wrap .page_con — 원본은 flex ＋ gap:12px 0 이다.
     dompdf 에서는 판마다 아래 여백으로 같은 사이를 준다. */
  .page_con { padding: 16px 20px; }
  .gap      { margin-bottom: 12px; }

  /* .print_top — 로고(180) | 제목(가운데) | 오른쪽(180), 높이 50 */
  .print_top      { width: 100%; height: 50px; }
  .print_top td   { vertical-align: middle; text-align: center; border: none; padding: 0; }
  .print_top .logo,
  .print_top .right { width: 180px; }
  .print_top em   { display: block; font-size: 18px; font-weight: 700; line-height: 21px; color: #000;
                    font-style: normal; }

  /* .table_layout01 — 공급자 표와 공급받는자 표가 나란히, 사이 12px */
  .layout01       { width: 100%; }
  .layout01 > tbody > tr > td { vertical-align: top; border: none; padding: 0; width: 50%; }
  .layout01 .left-cell  { padding-right: 6px; }
  .layout01 .right-cell { padding-left: 6px; }

  /* .table_type01 */
  .type01 { border-top: 1px solid #000; border-bottom: 1px solid #000;
            table-layout: fixed; width: 100%; }
  .type01 th { font-size: 10px; color: #000; line-height: 12px; font-weight: 700;
               background-color: #EEE; border: 1px dashed #DDD; padding: 6px 4px; }
  .type01 td { font-size: 10px; color: #222; line-height: 12px;
               border: 1px dashed #DDD; padding: 6px 4px; }
  .type01 tr.sizer td { height: 0; padding: 0; border: 0; font-size: 0; line-height: 0; }
  .type01 th.first, .type01 td.first { border-left: none; }
  .type01 th.last,  .type01 td.last  { border-right: none; }
  /* 원본은 p.price{position:relative;padding-left:20px} 에 span 을 왼쪽 끝에 붙여
     ￦ 는 칸의 왼쪽, 숫자는 오른쪽에 세운다. dompdf 는 absolute 로 그 자리를
     잡아 주지 못해, 같은 모양을 칸 둘짜리 표로 만든다. */
  .price       { width: 100%; }
  .price td    { border: none; padding: 0; font-size: 10px; color: #222; line-height: 12px; }
  .price .won  { text-align: left; width: 20px; }
  .price .num  { text-align: right; }

  /* .table_type02 */
  .type02 { border-top: 1px solid #000; table-layout: fixed; width: 100%; }
  .type02 thead { border-bottom: 1px solid #888; }
  .type02 th { font-size: 10px; color: #000; line-height: 12px; font-weight: 700;
               border: none; padding: 6px 4px; }
  .type02 tbody { border-bottom: 1px solid #000; }
  .type02 td { font-size: 10px; color: #222; text-align: center; line-height: 12px;
               border: 1px dashed #DDD; padding: 6px 4px; }
  .type02 td.first { border-left: none; }
  .type02 td.last  { border-right: none; }
  .type02 td.left  { text-align: left; }
  .type02 td.right { text-align: right; }
  .type02 tfoot td { border: none; font-weight: 600; color: #000; }
</style>
</head>
<body>
<div class="page_con">

  {{-- ── print_top ── --}}
  <table class="print_top gap">
    <tr>
      <td class="logo"></td>
      <td><em>거래명세서</em></td>
      <td class="right"></td>
    </tr>
  </table>

  {{-- ── table_layout01 — 공급자 · 공급받는자 ── --}}
  <table class="layout01 gap">
    <tr>
      <td class="left-cell">
        <table class="type01">
          {{-- 원본 40px·50px·flex·40px·flex 를 브라우저가 잡은 몫으로 옮긴 것 --}}
          <colgroup>
            <col width="6.006%"><col width="7.508%"><col width="40.24%">
            <col width="6.006%"><col width="40.24%">
          </colgroup>
          <tbody>
            {{-- 폭을 정하는 빈 줄. dompdf 는 첫 줄의 칸으로 열 폭을 정하는데,
                 이 표의 첫 줄은 rowspanㆍcolspan 이 섞여 다섯 칸을 다 짚지 못한다.
                 높이 0 이라 종이에는 보이지 않는다. --}}
            <tr class="sizer">
              <td style="width:7.473%"></td>
              <td style="width:9.341%"></td>
              <td style="width:37.856%"></td>
              <td style="width:7.473%"></td>
              <td style="width:37.856%"></td>
            </tr>
            <tr>
              <th class="first" rowspan="5">공급자</th>
              <th>등록번호</th>
              <td class="last" colspan="3">{{ $supplier['bizNo'] }}</td>
            </tr>
            <tr>
              <th>상호<br>(법인명)</th>
              <td>{{ $supplier['corpName'] }}</td>
              <th>성명</th>
              <td class="last">{{ $supplier['ceoName'] }}</td>
            </tr>
            <tr>
              <th>사업장<br>소재지</th>
              <td class="last" colspan="3">{{ $supplier['address'] }}</td>
            </tr>
            <tr>
              <th>업태</th>
              <td>{{ $supplier['bizType'] }}</td>
              <th>종목</th>
              <td class="last">{{ $supplier['bizClass'] }}</td>
            </tr>
            <tr>
              <th>TEL</th>
              <td>{{ $supplier['tel'] }}</td>
              <th>FAX</th>
              <td class="last">{{ $supplier['fax'] }}</td>
            </tr>
          </tbody>
        </table>
      </td>
      <td class="right-cell">
        <table class="type01">
          {{-- 원본 40px·50px·flex·40px·flex 를 브라우저가 잡은 몫으로 옮긴 것 --}}
          <colgroup>
            <col width="6.006%"><col width="7.508%"><col width="40.24%">
            <col width="6.006%"><col width="40.24%">
          </colgroup>
          <tbody>
            {{-- 폭을 정하는 빈 줄. dompdf 는 첫 줄의 칸으로 열 폭을 정하는데,
                 이 표의 첫 줄은 rowspanㆍcolspan 이 섞여 다섯 칸을 다 짚지 못한다.
                 높이 0 이라 종이에는 보이지 않는다. --}}
            <tr class="sizer">
              <td style="width:7.473%"></td>
              <td style="width:9.341%"></td>
              <td style="width:37.856%"></td>
              <td style="width:7.473%"></td>
              <td style="width:37.856%"></td>
            </tr>
            <tr>
              <th class="first" rowspan="5">공급<br>받는자</th>
              <th>등록번호</th>
              <td class="last" colspan="3">{{ $buyer['bizNo'] }}</td>
            </tr>
            <tr>
              <th>상호<br>(법인명)</th>
              <td>{{ $buyer['corpName'] }}</td>
              <th>성명</th>
              <td class="last">{{ $buyer['ceoName'] }}</td>
            </tr>
            <tr>
              <th>사업장<br>소재지</th>
              <td class="last" colspan="3">{{ $buyer['address'] }}</td>
            </tr>
            <tr>
              <th>업태</th>
              <td>{{ $buyer['bizType'] }}</td>
              <th>종목</th>
              <td class="last">{{ $buyer['bizClass'] }}</td>
            </tr>
            <tr>
              <th>TEL</th>
              <td>{{ $buyer['tel'] }}</td>
              <th>FAX</th>
              <td class="last">{{ $buyer['fax'] }}</td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </table>

  {{-- ── 합계금액 · 공급가액 · 세액 ── --}}
  <table class="type01 gap">
    <colgroup><col width="33.333%"><col width="33.333%"><col width="33.334%"></colgroup>
    <tbody>
      <tr>
        <th class="first">합계금액</th>
        <th>공급가액</th>
        <th class="last">세액</th>
      </tr>
      <tr>
        <td class="first">
          <table class="price"><tr><td class="won">￦</td><td class="num">{{ number_format($totals['amount']) }}</td></tr></table>
        </td>
        <td>
          <table class="price"><tr><td class="won">￦</td><td class="num">{{ number_format($totals['supply']) }}</td></tr></table>
        </td>
        <td class="last">
          <table class="price"><tr><td class="won">￦</td><td class="num">{{ number_format($totals['vat']) }}</td></tr></table>
        </td>
      </tr>
    </tbody>
  </table>

  {{-- ── 품목 ── --}}
  <table class="type02">
          {{-- 폭은 몫으로 적는다. 원본은 px 로 적고 남는 폭을 두 칸(품목명ㆍUDI 코드)이
               나눠 갖는데, dompdf 는 그 나눗셈을 다르게 해 품목명이 좁아져 글자가
               다섯 줄로 쌓였다. 브라우저가 원본을 그렸을 때의 몫을 그대로 옮긴다. --}}
    <colgroup>
      <col width="2.771%">
      <col width="6.467%">
      <col width="4.157%">
      <col width="18.822%">
      <col width="6.467%">
      <col width="6.467%">
      <col width="3.695%">
      <col width="3.695%">
      <col width="6.467%">
      <col width="6.467%">
      <col width="2.771%">
      <col width="5.543%">
      <col width="18.822%">
      <col width="2.771%">
      <col width="4.619%">
    </colgroup>
    <thead>
      <tr>
        <th style="width:2.771%">NO</th>
        <th style="width:6.467%">주문일자</th>
        <th style="width:4.157%">제품코드</th>
        <th style="width:18.822%">품목명</th>
        <th style="width:6.467%">LOT</th>
        <th style="width:6.467%">유효기간</th>
        <th style="width:3.695%">수량</th>
        <th style="width:3.695%">단가</th>
        <th style="width:6.467%">금액<br>(VAT 불포함)</th>
        <th style="width:6.467%">금액<br>(VAT 포함)</th>
        <th style="width:2.771%">등급</th>
        <th style="width:5.543%">보험코드</th>
        <th style="width:18.822%">UDI 코드</th>
        <th style="width:2.771%">UDI<br>수량</th>
        <th style="width:4.619%">UDI<br>단가</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $i => $it)
      <tr>
        <td class="first">{{ $i + 1 }}</td>
        <td>{{ $doc['issueDate'] }}</td>
        <td>{{ $it['code'] }}</td>
        <td class="left">{{ $it['name'] }}</td>
        <td>{{ $it['lot'] }}</td>
        <td>{{ $it['expiry'] }}</td>
        <td class="right">{{ number_format($it['qty']) }}</td>
        <td class="right">{{ number_format($it['price']) }}</td>
        <td class="right">{{ number_format($it['supply']) }}</td>
        <td class="right">{{ number_format($it['amount']) }}</td>
        <td>{{ $it['grade'] }}</td>
        <td>{{ $it['insuranceCode'] }}</td>
        <td class="left">{{ $it['udiCode'] }}</td>
        <td class="right">{{ $it['udiQty'] }}</td>
        <td class="last right">{{ $it['udiPrice'] }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td>합계</td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="right">{{ number_format($totals['qty']) }}</td>
        <td></td>
        <td class="right">{{ number_format($totals['supply']) }}</td>
        <td class="right">{{ number_format($totals['amount']) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
      </tr>
    </tfoot>
  </table>

</div>
</body>
</html>
