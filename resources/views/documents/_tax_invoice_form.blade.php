{{-- 전자세금계산서 — 국세청 별지 제11호 서식(공급자 보관용) 그대로.

     서식 원본은 「서식 파일/청구서 정보/p07_e_tax_invoice.pdf」다. 붉은 인쇄선ㆍ칸
     차례ㆍ글귀를 그 종이에서 옮겼다. 182mm × 128mm 자리에 앉는 서식이라 A4 위쪽에
     그대로 놓는다 — 원본도 그렇게 나온다.

     이 조각은 홀로도 쓰이고(내려받는 PDF) 공단 팩스 합본 안에도 끼워진다. 그래서
     모든 선택자를 .ti-form 아래로 넣었다 — 합본의 글꼴ㆍ표 모양을 건드리면 안 된다.

     dompdf 로 그리며 지킨 것(거래명세서에서 얻은 것과 같다):
       · table-layout:fixed 를 쓰지 않는다 — 칸 폭을 잘못 잡는다
       · word-wrap:break-word 를 쓰지 않는다 — 긴 글월을 한 글자씩 세로로 쌓는다
       · td 에 height 를 주지 않는다 — 글 높이에 더해져 칸이 부푼다. 여백으로 잡는다
       · 폭은 칸에 몫(%)으로 적는다 — mm 로 적으면 안쪽 여백만큼 부푼다

     붉은 선과 붉은 글자는 서식이 미리 박아 둔 것이고, 검은 글자가 우리가 채운 값이다.
     종이 원본이 그렇다. --}}
<style>
  .ti-form { width: 182mm; }
  .ti-form * { box-sizing: border-box; font-family: 'NanumGothic', sans-serif; }

  /* ── 서식 머리 (테두리 밖) ─────────────────── */
  .ti-form .above { width: 182mm; margin-bottom: 1mm; border-collapse: collapse; }
  .ti-form .above td { border: 0; padding: 0; font-size: 7pt; color: #d8232a; vertical-align: bottom; }
  .ti-form .above .no-label { text-align: right; padding-right: 2mm; }
  .ti-form .above .no-value { width: 38%; color: #111111; font-size: 8.5pt; letter-spacing: .02em; }

  /* ── 공통 표 ────────────────────────────── */
  .ti-form table { width: 100%; border-collapse: collapse; table-layout: auto; }
  .ti-form td { border: 0.25mm solid #d8232a; padding: 0.8mm 1mm; font-size: 7.5pt; line-height: 1.25;
                color: #111111; vertical-align: middle; text-align: left; }
  .ti-form .lbl { color: #d8232a; font-weight: 700; text-align: center; }
  .ti-form .box { border: 0.6mm solid #d8232a; }

  /* ── 제목 줄 ────────────────────────────── */
  .ti-form .t-title { font-size: 15pt; font-weight: 700; color: #d8232a; text-align: center;
                      letter-spacing: .12em; padding: 2.2mm 0; }
  .ti-form .t-keep  { color: #d8232a; font-weight: 700; text-align: center; font-size: 8pt;
                      line-height: 1.3; letter-spacing: .18em; }
  .ti-form .t-book  { color: #d8232a; font-weight: 700; font-size: 7.5pt; line-height: 1.5; }
  .ti-form .t-book .sp { display: inline-block; width: 14mm; }

  /* ── 거래 당사자 ─────────────────────────── */
  .ti-form .band { width: 2.6%; color: #d8232a; font-weight: 700; text-align: center; font-size: 7.5pt;
                   padding: 0; line-height: 1.25; }
  .ti-form .p-l1 { width: 9.2%; }    /* 등록번호ㆍ상호ㆍ사업장 주소ㆍ업태 */
  .ti-form .p-v1 { width: 20.8%; }
  .ti-form .p-l2 { width: 7.4%; }    /* 종사업장 */
  .ti-form .p-v2 { width: 7.4%; }
  .ti-form .p-l2n { width: 2.6%; padding: 0; }   /* 「성 명」ㆍ「종 목」처럼 두 줄로 세우는 칸 */

  /* ── 작성일자ㆍ공급가액ㆍ세액 ───────────────── */
  .ti-form .amt td { padding: 0.7mm 0.4mm; font-size: 7pt; text-align: center; }
  .ti-form .amt .d { font-size: 8pt; }
  .ti-form .amt .ymd-y { width: 5.6%; }
  .ti-form .amt .ymd-m, .ti-form .amt .ymd-d { width: 3.2%; }
  .ti-form .amt .blankcnt { width: 6.4%; }

  /* ── 품목 ──────────────────────────────── */
  .ti-form .items td { padding: 1mm; }
  .ti-form .items .i-m, .ti-form .items .i-d { width: 3.2%; text-align: center; }
  .ti-form .items .i-name { width: 27%; }
  .ti-form .items .i-spec { width: 9%; text-align: center; }
  .ti-form .items .i-qty { width: 8%; text-align: right; }
  .ti-form .items .i-price { width: 9%; text-align: right; }
  .ti-form .items .i-sup { width: 13.5%; text-align: right; }
  .ti-form .items .i-vat { width: 11%; text-align: right; }
  .ti-form .items .i-note { width: 16.1%; }
  .ti-form .items .lbl { text-align: center; }

  /* ── 합계 ──────────────────────────────── */
  .ti-form .sum .s-cell { width: 16%; text-align: right; }
  .ti-form .sum .s-last { width: 20%; color: #d8232a; font-weight: 700; text-align: center; font-size: 8pt; }
  .ti-form .sum .s-last b { color: #111111; }

  /* ── 서식 꼬리 (테두리 밖) ─────────────────── */
  .ti-form .below { width: 182mm; margin-top: 0.8mm; }
  .ti-form .below td { border: 0; padding: 0; font-size: 6.5pt; color: #d8232a; line-height: 1.4; }
  .ti-form .below .rt { text-align: right; }
  .ti-form .note { width: 182mm; margin-top: 0.5mm; font-size: 6.5pt; color: #d8232a; line-height: 1.4; }
</style>

<div class="ti-form">

  {{-- 테두리 위 — 서식 이름과 국세청승인번호 --}}
  <table class="above">
    <tr>
      <td>[별지 제11호 서식] (96.3.30. 개정)</td>
      <td class="no-label">국세청승인번호:</td>
      <td class="no-value">{{ $doc['ntsNo'] }}</td>
    </tr>
  </table>

  <table class="box">
    <tr>
      <td colspan="6" style="padding:0;border:0">

        {{-- 제목 줄 --}}
        <table>
          <tr>
            <td class="t-title" style="width:58%;border-left:0;border-top:0">전자세금계산서</td>
            <td class="t-keep" style="width:13%;border-top:0">공 급 자<br>(보 관 용)</td>
            <td class="t-book" style="width:29%;border-right:0;border-top:0">
              책번호:<span class="sp"></span>권<span class="sp"></span>호<br>일련번호:
            </td>
          </tr>
        </table>

        {{-- 공급자 · 공급받는자 --}}
        <table>
          @foreach($parties as $i => $row)
          <tr>
            @if($i === 0)<td class="band" rowspan="4">공<br>급<br>자</td>@endif

            @if($row['wide'])
              <td class="lbl p-l1">{{ $row['label'] }}</td>
              <td colspan="3" class="p-v1">{{ $row['supplier'] }}</td>
            @else
              <td class="lbl p-l1">{{ $row['label'] }}</td>
              <td class="p-v1">{{ $row['supplier'] }}</td>
              <td class="lbl {{ $row['narrow'] ? 'p-l2n' : 'p-l2' }}">{!! $row['label2'] !!}</td>
              <td class="p-v2">{{ $row['supplier2'] }}</td>
            @endif

            @if($i === 0)<td class="band" rowspan="4">공<br>급<br>받<br>는<br>자</td>@endif

            @if($row['wide'])
              <td class="lbl p-l1">{{ $row['label'] }}</td>
              <td colspan="3" class="p-v1">{{ $row['buyer'] }}</td>
            @else
              <td class="lbl p-l1">{{ $row['label'] }}</td>
              <td class="p-v1">{{ $row['buyer'] }}</td>
              <td class="lbl {{ $row['narrow'] ? 'p-l2n' : 'p-l2' }}">{!! $row['label2'] !!}</td>
              <td class="p-v2">{{ $row['buyer2'] }}</td>
            @endif
          </tr>
          @endforeach
        </table>

        {{-- 작성일자 · 공급가액 · 세액 --}}
        <table class="amt">
          <tr>
            <td class="lbl" colspan="3">작성일자</td>
            <td class="lbl" colspan="{{ count($supplyHead) + 1 }}">공급가액</td>
            <td class="lbl" colspan="{{ count($vatHead) }}">세액</td>
          </tr>
          <tr>
            <td class="lbl ymd-y">년</td><td class="lbl ymd-m">월</td><td class="lbl ymd-d">일</td>
            <td class="lbl blankcnt">공란수</td>
            @foreach($supplyHead as $h)<td class="lbl">{{ $h }}</td>@endforeach
            @foreach($vatHead as $h)<td class="lbl">{{ $h }}</td>@endforeach
          </tr>
          <tr>
            <td class="d">{{ $doc['year'] }}</td><td class="d">{{ $doc['month'] }}</td><td class="d">{{ $doc['day'] }}</td>
            <td class="d">{{ $doc['blankCount'] }}</td>
            @foreach($supplyDigits as $g)<td class="d">{{ $g }}</td>@endforeach
            @foreach($vatDigits as $g)<td class="d">{{ $g }}</td>@endforeach
          </tr>
        </table>

        {{-- 비고 --}}
        <table>
          <tr>
            <td class="lbl" style="width:11.8%">비고</td>
            <td>{{ $doc['remark'] }}</td>
          </tr>
        </table>

        {{-- 품목 — 네 줄 고정 --}}
        <table class="items">
          <tr>
            <td class="lbl i-m">월</td><td class="lbl i-d">일</td>
            <td class="lbl i-name">품목</td><td class="lbl i-spec">규격</td>
            <td class="lbl i-qty">수량</td><td class="lbl i-price">단가</td>
            <td class="lbl i-sup">공급가액</td><td class="lbl i-vat">세액</td>
            <td class="lbl i-note">비고</td>
          </tr>
          @foreach($items as $it)
          <tr>
            <td class="i-m">{{ $it['month'] }}</td><td class="i-d">{{ $it['day'] }}</td>
            <td class="i-name">{{ $it['name'] }}</td><td class="i-spec">{{ $it['spec'] }}</td>
            <td class="i-qty">{{ $it['qty'] }}</td><td class="i-price">{{ $it['price'] }}</td>
            <td class="i-sup">{{ $it['supply'] }}</td><td class="i-vat">{{ $it['vat'] }}</td>
            <td class="i-note">{{ $it['note'] }}</td>
          </tr>
          @endforeach
          @for($i = count($items); $i < $rows; $i++)
          <tr>
            <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
          </tr>
          @endfor
        </table>

        {{-- 합계 --}}
        <table class="sum">
          <tr>
            <td class="lbl">합계금액</td><td class="lbl">현금</td><td class="lbl">수표</td>
            <td class="lbl">어음</td><td class="lbl">외상미수금</td>
            <td class="s-last" rowspan="2" style="border-right:0">이 금액을 <b>[{{ $doc['purpose'] }}]</b> 함</td>
          </tr>
          <tr>
            <td class="s-cell" style="border-bottom:0">{{ $doc['total'] }}</td>
            <td class="s-cell" style="border-bottom:0">{{ $doc['cash'] }}</td>
            <td class="s-cell" style="border-bottom:0"></td>
            <td class="s-cell" style="border-bottom:0"></td>
            <td class="s-cell" style="border-bottom:0">{{ $doc['credit'] }}</td>
          </tr>
        </table>

      </td>
    </tr>
  </table>

  {{-- 테두리 아래 — 서식 번호와 종이 규격 --}}
  <table class="below">
    <tr>
      <td>22226-28131일 1996.2.27 개정</td>
      <td class="rt">182mm x 128mm (인쇄용지(특급) 34g/㎡)</td>
    </tr>
  </table>
  <div class="note">
    주의 : 본 세금계산서는 국세청고시 기준에 따라 {{ $doc['issuer'] }}에서 발행된 전자세금계산서로
    공동인증기관의 공동인증서를 사용하여 전자서명되어 인감날인이 없어도 법적 효력을 갖습니다.
  </div>

</div>
