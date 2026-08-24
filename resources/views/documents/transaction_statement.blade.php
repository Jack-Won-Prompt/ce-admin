{{-- 거래명세서 — 받은 서식(화면정의서 「서식 파일/거래명세서.html」)을 그대로 옮긴 것.

     원본은 브라우저에서 자바스크립트가 그리는 문서다. 여기서는 서버가 그린다 —
     PDF 로 만들 때 스크립트가 돌지 않기 때문이다. 생김새를 바꾸지 않으려고 자리ㆍ폭ㆍ
     글자 크기는 원본 값을 그대로 옮겼고, dompdf 가 알아듣지 못하는 것만 바꿨다:

       · CSS 변수(--line 따위) → 값을 직접 적는다
       · flex → 표와 자리잡기로
       · ::before 의 「- 」 → 글자로 직접
       · SVG 바코드 → 검은 칸을 나란히 세워 그린다(막대 하나가 칸 하나)

     원본이 지킨 것도 그대로 지킨다 — 품목 열 줄 고정, LOT 이 다르면 줄을 나눔,
     열한 건부터 장을 나눔. --}}
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
  .sheet { padding: 13mm 12mm 12mm; position: relative; page-break-after: always; }
  .sheet.last { page-break-after: auto; }

  /* ── 문서 머리 ───────────────────────────── */
  .doc-head { text-align: center; position: relative; height: 30mm; }
  .doc-title { font-size: 31pt; font-weight: 700; letter-spacing: .28em; text-indent: .28em;
               color: #0b2d5b; line-height: 1.1; margin: 0 0 3mm; }
  .issue-date { position: absolute; left: 0; top: 8mm; font-size: 9.5pt; color: #333333; }

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
  .parties { margin-top: 5mm; position: relative; }
  /* 폭은 몫으로 적는다. dompdf 에 mm 로 적으면 안쪽 여백만큼 부풀어, 서식이 좌우
     같게 잡아 둔 두 값 칸이 29mm 와 84mm 로 갈라진다. 186mm 기준 몫이다. */
  .party-band { width: 3.8%; background: #f1f2f4; font-weight: 700; font-size: 8pt;
                text-align: center; padding: 0; line-height: 1.18; }
  .party-label { width: 11.8%; background: #f1f2f4; font-weight: 700; text-align: center;
                 letter-spacing: .1em; }
  .party-gap { width: 1.6%; border: 0 !important; padding: 0 !important; }
  .party-value { width: 33.6%; text-align: left; }
  .party-value.has-seal { padding-right: 27mm; }

  .seal { position: absolute; right: 4mm; top: 10mm; width: 24mm; height: 24mm; }
  .seal img { width: 24mm; height: 24mm; }
  .seal .seal-ph { width: 24mm; height: 24mm; border: 0.35mm dashed #c9ccd1; border-radius: 12mm;
                   color: #b9bdc4; font-size: 7.5pt; letter-spacing: .08em; text-align: center;
                   padding-top: 10mm; }

  /* ── 품목 ──────────────────────────────── */
  .items { margin-top: 4mm; }
  /* 줄 높이는 안쪽 여백으로 잡는다. dompdf 에서 height 는 글 높이에 더해져
     서식이 정한 9.4mm 줄이 14mm 로 부푼다. */
  .items td { padding: 1mm 2mm; }
  .items .c { text-align: center; }
  .items .r { text-align: right; }
  /* 품명은 8pt 로 적는다. 9pt 면 실제로 쓰이는 가장 긴 품명(39자)이 두 줄로 접혀
     열 건짜리 장이 A4 를 넘어선다. */
  .items .name { text-align: left; padding-left: 2.5mm; font-size: 8pt; }
  .items .lot { font-family: 'Consolas', monospace; }
  .items tr.lot-split td { border-top: 0.25mm dotted #b6b9bd; }
  .items tr.lot-split .name { color: #3f3f3f; }

  .sum-qty td { background: #fafbfc; font-weight: 700; }
  .sum-amt td { background: #f1f2f4; font-weight: 700; padding: 0; }
  .sum-inner td { border: 0; border-right: 0.4mm solid #5b5b5b; background: #f1f2f4;
                  font-weight: 700; padding: 1.6mm 2mm; }
  .sum-inner .last { border-right: 0; }
  .sum-inner .c { text-align: center; letter-spacing: .06em; }
  .sum-inner .r { text-align: right; font-size: 9.5pt; }
  .sum-inner .grand { font-size: 11pt; color: #0b2d5b; }

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

    {{-- 거래 당사자 --}}
    <div class="parties">
      <table class="tbl-outer">
        <tbody>
          @foreach($partyRows as $i => $row)
          <tr>
            @if($i === 0)<td class="party-band" rowspan="4">공<br>급<br>받<br>는<br>자</td>@endif
            <td class="party-label">{{ $row[0] }}</td>
            <td class="party-value">{{ $row[1] }}</td>
            <td class="party-gap"></td>
            @if($i === 0)<td class="party-band" rowspan="4">공<br>급<br>자</td>@endif
            <td class="party-label">{{ $row[2] }}</td>
            <td class="party-value{{ $i === 1 || $i === 2 ? ' has-seal' : '' }}">{{ $row[3] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div class="seal">
        @if(!empty($doc['sealPath']))
          <img src="{{ $doc['sealPath'] }}" alt="사용인감">
        @else
          <div class="seal-ph">사용인감</div>
        @endif
      </div>
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
          <p>- 제품 수령일로부터 <b>7일 이내</b>에 신청한 경우에만 가능합니다.</p>
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
