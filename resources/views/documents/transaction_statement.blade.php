{{-- 거래명세서 — 위드웍스(medical)의 거래명세서와 같은 틀이다.

     원본 서식
       withworks/resources/views/main/medical/standard/print/transaction_statement_doc.blade.php
       — 그 파일의 첫 줄이 「CE Admin + 대리점=콜로플라스트 주문 전용」이라 적고 있다.
         곧 이 서류는 우리 건을 위해 저쪽이 만든 것이고, 두 시스템이 같은 종이를 내야 한다.

     원본은 브라우저에서 자바스크립트가 그리는 문서다. 여기서는 서버가 그린다 —
     PDF 로 만들 때 스크립트가 돌지 않기 때문이다. 생김새를 바꾸지 않으려고 자리ㆍ폭ㆍ
     글자 크기는 원본 값을 그대로 옮겼고, dompdf 가 알아듣지 못하는 것만 바꿨다:

       · CSS 변수(--line 따위) → 값을 직접 적는다
       · flex → 표와 자리잡기로
       · ::before 의 「- 」 → 글자로 직접
       · SVG 바코드 → 검은 칸을 나란히 세워 그린다(막대 하나가 칸 하나)

     원본이 지킨 것도 그대로 지킨다 — 품목 열 줄 고정, LOT 이 다르면 줄을 나눔,
     열한 건부터 장을 나눔.

     2026-09-17 지시로 원본과 한 자 한 자 맞췄다 :
       · 사용인감 — 원본 파일(assets/colo_print/images/stamp.png)을 그대로 가져오고
         크기ㆍ자리도 원본 값(19.5mm · right:-6mm)으로
       · 품목ㆍ합계 줄 높이 9.4mm
       · 합계 금액 글자색 남색 → 검정
       · 교환ㆍ반품 안내 둘째 줄 「제품 수령일로부터 7일」 → 「결제일로부터 14일」 --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>거래명세서_{{ $doc['documentNo'] }}</title>
<style>
  @page { margin: 0; }
  * { box-sizing: border-box; font-family: 'NanumGothic', sans-serif; }
  html, body { margin: 0; padding: 0; color: #111111; }

  /* 종이 크기는 dompdf 가 A4 로 잡는다. 여기서 210×297 을 다시 못 박으면 여백만큼
     넘쳐 한 장짜리 문서가 네 장으로 벌어진다 — 안쪽 여백만 준다. */
  /* 아래 여백은 6mm 다. 원본(12mm)보다 좁힌 것은 보이는 자리가 아니라 **장을 넘기는
     잣대**라서다 — 종이에 실제로 남는 아래 여백은 40mm 안팎이고, 여기 적은 값은
     dompdf 가 「이만큼 남겨야 한다」고 보는 몫일 뿐이다. 12mm 로 두면 품명이 두 줄로
     접히는 건(가장 긴 품명이 열 줄)이 열한 번째 줄도 없이 둘째 장으로 넘어갔다. */
  .sheet { padding: 13mm 12mm 6mm; position: relative; page-break-after: always; }
  .sheet.last { page-break-after: auto; }

  /* ── 문서 머리 ───────────────────────────── */
  /* 머리 판의 높이는 발행일이 설 자리를 정한다. 발행일은 왼쪽 아래에 붙이므로
     (아래 .issue-date) 판이 낮으면 발행일만 위로 올라가 바코드 번호와 어긋난다 —
     원본은 그 둘이 같은 줄에 선다. 35.6mm 일 때 둘이 같은 높이(47.6mm)에 놓인다. */
  .doc-head { text-align: center; position: relative; height: 35.6mm; }
  .doc-title { font-size: 31pt; font-weight: 700; letter-spacing: .28em; text-indent: .28em;
               color: #0b2d5b; line-height: 1.1; margin: 0 0 3mm; }
  /* 원본은 머리 판의 왼쪽 아래에 붙인다 — 바코드 번호와 같은 높이다 */
  .issue-date { position: absolute; left: 0; bottom: 0; font-size: 9.5pt; color: #333333; }

  /* 바코드 — 막대 하나가 검은 칸 하나다 */
  .barcode { height: 13mm; text-align: center; font-size: 0; line-height: 0; }
  .barcode span { display: inline-block; height: 13mm; vertical-align: top; }
  .barcode-no { font-family: 'Consolas', monospace; font-size: 8pt; letter-spacing: .16em;
                color: #333333; margin-top: 1mm; }

  /* ── 공통 표 ────────────────────────────── */
  /* table-layout:fixed 는 쓰지 않는다. dompdf 의 고정 배치는 칸 폭을 잘못 잡아
     긴 글월을 몇 글자씩 끊어 쌓는다 — 한 장짜리 서식이 세 장으로 벌어진다.
     칸 폭은 colgroup 이 잡아 주므로 auto 로도 서식대로 나온다. */
  table { width: 100%; border-collapse: collapse; table-layout: auto; }
  /* word-wrap:break-word 는 쓰지 않는다. dompdf 는 칸에 안 들어가는 글월을 만나면
     그것을 한 글자씩 세로로 쌓아 버려, 한 장짜리 문서를 세 장으로 벌린다.
     여기 들어가는 긴 글월은 띄어쓰기가 있는 주소뿐이라 보통 줄바꿈으로 넉넉하다. */
  td, th { border: 0.4mm solid #5b5b5b; padding: 1.6mm 2mm; font-size: 9pt; line-height: 1.35;
           vertical-align: middle; }
  .tbl-outer { border: 0.7mm solid #1a1a1a; }
  th { background: #f1f2f4; font-weight: 700; text-align: center; letter-spacing: .06em; }

  /* ── 거래 당사자 ─────────────────────────── */
  /* ── 거래 당사자 ─────────────────────────────
     원본은 공급받는자ㆍ공급자를 **서로 다른 표 둘**로 나누고 flex 로 나란히 세운다
     (줄 수가 셋ㆍ넷으로 달라도 되게). dompdf 는 flex 를 모르므로 칸 둘짜리 표로
     같은 자리를 만든다 — 가운데 3mm 는 원본의 gap 이다. */
  .parties { margin-top: 5mm; position: relative; }
  .party-wrap { width: 100%; }
  .party-wrap > tbody > tr > td { border: 0; padding: 0; vertical-align: top; width: 50%; }
  .party-wrap .left  { padding-right: 1.5mm; }
  .party-wrap .right { padding-left: 1.5mm; }

  /* 폭은 몫으로 적는다. dompdf 에 mm 로 적으면 안쪽 여백만큼 부풀어 값 칸이 좁아진다.
     표 하나가 91.5mm 이므로 7mm → 7.65%, 22mm → 24.04% 다. */
  .party-band  { width: 7.65%; background: #f1f2f4; font-weight: 700; font-size: 8pt;
                 text-align: center; padding: 0; line-height: 1.18; }
  .party-label { width: 24.04%; background: #f1f2f4; font-weight: 700; text-align: center;
                 padding-left: 0; padding-right: 0; }
  /* 원본은 낱자를 15mm 폭에 고루 편다(flex space-between). dompdf 는 flex 를
     모르므로 낱자마다 칸 하나인 표로 같은 모양을 만든다. */
  .lbl       { width: 15mm; margin: 0 auto; }
  .lbl td    { border: 0; padding: 0; text-align: center; font-size: 9pt;
               font-weight: 700; line-height: 1.35; }
  .party-value { text-align: left; }
  /* 공급받는자(세 줄)와 공급자(네 줄)는 **아래가 같은 줄에서 끝나야 한다** (2026-09-17 지시).
     원본은 둘을 flex 로 나란히 세워 짧은 쪽이 저절로 늘어난다(align-items:stretch).
     dompdf 는 flex 도 height:100% 도 쓸 수 없어(둘 다 넣어 보니 장이 넷으로 벌어졌다)
     줄 여백으로 높이를 맞춘다 — 만든 PDF 에서 두 표를 재어 3.1mm 로 잡았다
     (공급받는자 41.06mm · 공급자 41.11mm).

     여백은 **바로 아래 칸에만 건다.** 그냥 `td` 로 걸면 이름표 안에 든 낱자 표(.lbl)의
     칸까지 걸려(순위가 같아 뒤에 적은 이 줄이 이긴다) 한 줄이 17mm 로 부풀었다 —
     공급받는자 표만 10.8mm 길어져 있었다.

     공급자 쪽은 늘 같은 글(등록번호ㆍ상호ㆍ주소ㆍ대표번호)이라 높이가 정해져 있다.
     받는 분 주소가 길어 두 줄로 접히면 왼쪽이 그만큼 길어진다 — 원본은 flex 가
     오른쪽을 늘려 맞추지만 여기서는 맞출 길이 없다. */
  .party-recipient > tbody > tr > td { padding-top: 3.1mm; padding-bottom: 3.1mm; }

  /* 사용인감 — 상호ㆍ주소 칸 위에 겹쳐 찍는다.
     원본과 같은 자리ㆍ같은 크기다(right:-6mm · top:10mm · 19.5mm 네모). 오른쪽으로
     6mm 넘겨 찍는 것은 원본이 그렇다 — 표 바깥으로 반쯤 걸쳐야 도장으로 보인다.
     그림도 원본이 쓰는 파일을 그대로 가져왔다(여백을 잘라 낸 판이라 같은 네모에서
     도장이 더 크게 보인다 — 그래서 24mm 가 아니라 19.5mm 다). */
  .seal { position: absolute; right: -6mm; top: 10mm; width: 19.5mm; height: 19.5mm; }
  .seal img { width: 19.5mm; height: 19.5mm; }

  /* ── 품목 ──────────────────────────────── */
  .items { margin-top: 4mm; }
  /* 줄 높이는 안쪽 여백으로 잡는다. dompdf 에서 height 는 글 높이에 더해져
     서식이 정한 9.4mm 줄이 14mm 로 부푼다.

     서식이 정한 줄 높이는 9.4mm 다. 여백 1.8mm 일 때 dompdf 가 그리는 줄 간격이
     9.42mm 로, 서식과 같다(만들어 낸 PDF 에서 글 놓인 자리를 재어 맞췄다 —
     dompdf 의 줄 상자는 line-height 로 셈한 값보다 높아 손으로는 맞출 수 없었다).
     여백을 1mm 로 두었을 때는 줄 간격이 7.6mm 라 원본보다 표가 18mm 짧았다. */
  .items td { padding: 1.8mm 2mm; }
  .items .c { text-align: center; }
  .items .r { text-align: right; }
  /* 원본과 같은 9pt 다. 긴 품명은 원본에서도 두 줄로 접힌다 — 접히라고 둔 칸이다
     (word-break:keep-all · overflow-wrap:break-word). */
  .items .name { text-align: left; padding-left: 2.5mm; word-break: keep-all; }
  .items .lot { font-family: 'Consolas', monospace; }
  .items tr.lot-split td { border-top: 0.25mm dotted #b6b9bd; }
  .items tr.lot-split .name { color: #3f3f3f; }

  .sum-qty td { background: #fafbfc; font-weight: 700; }
  .sum-amt td { background: #f1f2f4; font-weight: 700; padding: 0; }
  /* 합계 줄도 품목 줄과 같은 9.4mm 다 — 여백은 위와 같은 값이다 */
  .sum-inner td { border: 0; border-right: 0.4mm solid #5b5b5b; background: #f1f2f4;
                  font-weight: 700; padding: 1.8mm 2mm; }
  .sum-inner .last { border-right: 0; }
  .sum-inner .c { text-align: center; letter-spacing: .06em; }
  .sum-inner .r { text-align: right; font-size: 9.5pt; }
  /* 합계 금액은 검정이다 — 원본도 제목만 남색(#0b2d5b)이고 금액은 본문색이다 */
  .sum-inner .grand { font-size: 11pt; color: #111111; }

  /* ── 비고 (교환·반품 안내) ─────────────────── */
  .notice { margin-top: 4mm; }
  .notice .n-label { width: 11.8%; background: #f1f2f4; font-weight: 700; text-align: center;
                     white-space: nowrap; }
  .notice p { margin: 0; font-size: 8.6pt; line-height: 1.6; padding-left: 3mm; text-indent: -3mm; }
  .notice b { font-weight: 700; }

  /* ── 꼬리말 ────────────────────────────── */
  .foot { margin-top: 4mm; font-size: 8pt; color: #6b7280; }
  .foot .r { float: right; }
</style>
</head>
<body>

@foreach($pages as $pi => $items)
  @php $isLast = $pi === count($pages) - 1; @endphp
  <div class="sheet{{ $isLast ? ' last' : '' }}">

    {{-- 머리 — 발행일 · 제목 · 바코드(출고번호) --}}
    <div class="doc-head">
      <div class="issue-date">{{ $doc['issueDate'] }}</div>
      <h1 class="doc-title">거 래 명 세 서</h1>
      <div class="barcode">
        @foreach($barcode as $bar)
          <span style="width:{{ $bar['w'] }}mm;background:{{ $bar['on'] ? '#000000' : '#ffffff' }};"></span>
        @endforeach
      </div>
      <div class="barcode-no">{{ $doc['documentNo'] }}@if(count($pages) > 1)  ({{ $pi + 1 }}/{{ count($pages) }})@endif</div>
    </div>

    {{-- 거래 당사자 — 공급받는자(셋 줄) · 공급자(넷 줄).
         **공급받는자에 주민등록번호 칸은 없다** — 원본에도 없고, 종이에 실려 나가서도
         안 되는 값이다(P0-1). --}}
    <div class="parties">
      <table class="party-wrap">
        <tbody>
          <tr>
            <td class="left">
              <table class="tbl-outer party-recipient">
                <tbody>
                  <tr>
                    <td class="party-band" rowspan="3">공<br>급<br>받<br>는<br>자</td>
                    <td class="party-label"><table class="lbl"><tr><td>성</td><td>명</td></tr></table></td>
                    <td class="party-value">{{ $recipient['name'] }}</td>
                  </tr>
                  <tr>
                    <td class="party-label"><table class="lbl"><tr><td>주</td><td>소</td></tr></table></td>
                    <td class="party-value">{{ $recipient['address'] }}</td>
                  </tr>
                  <tr>
                    <td class="party-label"><table class="lbl"><tr><td>연</td><td>락</td><td>처</td></tr></table></td>
                    <td class="party-value">{{ $recipient['phone'] }}</td>
                  </tr>
                </tbody>
              </table>
            </td>
            <td class="right">
              <table class="tbl-outer party-supplier">
                <tbody>
                  <tr>
                    <td class="party-band" rowspan="4">공<br>급<br>자</td>
                    <td class="party-label"><table class="lbl"><tr><td>등</td><td>록</td><td>번</td><td>호</td></tr></table></td>
                    <td class="party-value">{{ $supplier['regNo'] }}</td>
                  </tr>
                  <tr>
                    <td class="party-label"><table class="lbl"><tr><td>상</td><td>호</td></tr></table></td>
                    <td class="party-value">{{ $supplier['company'] }}</td>
                  </tr>
                  <tr>
                    <td class="party-label"><table class="lbl"><tr><td>주</td><td>소</td></tr></table></td>
                    <td class="party-value">{{ $supplier['address'] }}</td>
                  </tr>
                  <tr>
                    <td class="party-label"><table class="lbl"><tr><td>연</td><td>락</td><td>처</td></tr></table></td>
                    <td class="party-value">{{ $supplier['phone'] }}</td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="seal"><img src="{{ $sealPath }}" alt="사용인감"></div>
    </div>

    {{-- 품목 — 열 줄 고정 --}}
    <table class="tbl-outer items">
      {{-- 폭은 <colgroup> 으로 일러둔다. 서식이 정한 비율이다.

           칸에 직접 폭을 박지 않는 까닭이 있다. 그러면 dompdf 가 그 폭을 그대로 지켜,
           장비코드ㆍLOT 이 비어 있어도 품명 칸은 46mm 에 묶인다 — 실제로 쓰이는 가장
           긴 품명(39자)이 두 줄로 접히고, 열 건짜리 장이 A4 를 넘어선다.
           colgroup 은 일러두기라, 빈 칸이 있으면 그 폭이 품명으로 흘러간다. --}}
      <colgroup>
        <col style="width:9%"><col style="width:25%"><col style="width:16%"><col style="width:13%">
        <col style="width:6%"><col style="width:8%"><col style="width:10%"><col style="width:13%">
      </colgroup>
      <thead>
        <tr>
          <th>규 격</th><th>품 명</th><th>장비코드</th><th>LOT</th>
          <th>단위</th><th>수 량</th><th>단 가</th><th>금 액</th>
        </tr>
      </thead>
      <tbody>
        @php $prevKey = null; @endphp
        @foreach($items as $it)
          @php
            $key   = $it['spec'] . '|' . $it['deviceCode'];
            $split = $key === $prevKey ? ' class="lot-split"' : '';
            $prevKey = $key;
          @endphp
          <tr{!! $split !!}>
            <td class="c">{{ $it['spec'] }}</td>
            <td class="name">{{ $it['name'] }}</td>
            <td class="c">{{ $it['deviceCode'] }}</td>
            <td class="c lot">{{ $it['lot'] }}</td>
            <td class="c">{{ $it['unit'] }}</td>
            <td class="r">{{ number_format($it['qty']) }}</td>
            <td class="r">{{ number_format($it['price']) }}</td>
            <td class="r">{{ number_format($it['qty'] * $it['price']) }}</td>
          </tr>
        @endforeach
        @for($i = count($items); $i < $rows; $i++)
          <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
      </tbody>
      <tfoot>
        @if($isLast)
          <tr class="sum-qty">
            <td colspan="5" class="r">합계 수량</td>
            <td class="r">{{ number_format($totals['qty']) }}</td>
            <td colspan="2"></td>
          </tr>
          <tr class="sum-amt">
            <td colspan="8">
              <table class="sum-inner">
                <colgroup>
                  <col style="width:13%"><col style="width:20.33%">
                  <col style="width:13%"><col style="width:20.33%">
                  <col style="width:13%"><col style="width:20.34%">
                </colgroup>
                <tbody><tr>
                  <td class="c">공급가액</td><td class="r">{{ number_format($totals['supply']) }}</td>
                  <td class="c">부가가치세</td><td class="r">{{ number_format($totals['vat']) }}</td>
                  <td class="c">합 계</td><td class="r grand last">{{ number_format($totals['amount']) }}</td>
                </tr></tbody>
              </table>
            </td>
          </tr>
        @else
          <tr class="sum-qty"><td colspan="8" class="r">다음 장에 계속</td></tr>
        @endif
      </tfoot>
    </table>

    {{-- 비고 — 교환ㆍ반품 안내(원본 문구 그대로) --}}
    @if($isLast)
    <table class="tbl-outer notice">
      <tbody><tr>
        <td class="n-label">교환·반품 안내</td>
        <td>
          <p>- 교환·반품 요청을 하는 제품은 구매한 제품과 <b>동일한 LOT</b>인 경우에만 가능합니다.</p>
          <p>- 결제일로부터 <b>14일 이내</b>에 신청한 경우에만 가능합니다.</p>
          <p>- 고객의 단순 변심에 의한 교환 및 반품의 경우, 왕복 배송비는 <b>고객 부담</b>입니다.</p>
          <p>- 소비자의 부주의로 제품이 훼손 또는 파손된 경우, 최소 포장 단위의 수량이 맞지 않는 경우,
             사용 또는 일부 소비로 가치가 감소한 경우에는 교환 및 반품이 <b>불가</b>합니다.</p>
        </td>
      </tr></tbody>
    </table>
    @endif

    <div class="foot">
      <span>{{ $doc['footNote'] ?? '' }}</span>
      <span class="r">@if($doc['saleNo'])판매번호 {{ $doc['saleNo'] }}  ·  @endif{{ $pi + 1 }} / {{ count($pages) }}</span>
    </div>
  </div>
@endforeach

</body>
</html>
