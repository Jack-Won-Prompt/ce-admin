{{-- 의료용품 구입 확인서 (서식 01 · A4 가로)
     받은 서식(의료용품-구입-확인서_Layout.html) 그대로다. 한 사람이 지금까지 무엇을
     얼마에 샀는지를 한 장에 모은다 — 주문 한 건이 아니라 사람의 내역이다.

     dompdf 가 그린다. 쓸 수 있는 CSS 가 좁아 표와 인라인 스타일로만 짠다 —
     flex·grid 는 무시되고 자리가 무너진다. --}}
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<style>
  @page { size: A4 landscape; margin: 12mm 12mm 14mm; }
  body { font-family: 'NanumGothic', sans-serif; font-size: 10pt; color: #111; }
  h1 { font-size: 17pt; text-align: center; margin: 0 0 2mm; letter-spacing: 2px; }
  .sub { text-align: center; font-size: 8.5pt; color: #666; margin-bottom: 6mm; }
  .sec { font-size: 10.5pt; font-weight: bold; margin: 4mm 0 1.5mm; }
  table { width: 100%; border-collapse: collapse; }
  .party td { border: 0.6pt solid #444; padding: 2mm 2.5mm; font-size: 9.5pt; }
  .party td.k { background: #f2f2f2; width: 22mm; font-weight: bold; text-align: center; }
  .items th, .items td { border: 0.6pt solid #444; padding: 1.8mm 2mm; font-size: 9pt; }
  .items th { background: #f2f2f2; font-weight: bold; text-align: center; }
  .items td.c { text-align: center; }
  .items td.r { text-align: right; }
  .items tr.sum td { background: #fafafa; font-weight: bold; }
  .foot { margin-top: 7mm; font-size: 9.5pt; line-height: 1.9; }
  .sign { margin-top: 4mm; text-align: right; font-size: 10pt; }
  .pageno { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center;
            font-size: 8.5pt; color: #666; }
</style>
</head>
<body>

<h1>의료용품 구입 확인서</h1>
<div class="sub">서식 01</div>

<div class="sec">1. 공급자</div>
<table class="party">
  <tr>
    <td class="k">상호</td><td>{{ $supplier['corp_name'] ?? '' }}</td>
    <td class="k">사업자번호</td><td>{{ $supplier['biz_no'] ?? '' }}</td>
  </tr>
  <tr>
    <td class="k">주소</td><td colspan="3">{{ $supplier['addr'] ?? '' }}</td>
  </tr>
</table>

<div class="sec">2. 공급받는자</div>
<table class="party">
  <tr>
    <td class="k">성명</td><td>{{ $patient->name }}</td>
    {{-- 가린 채로 적는다. 이 종이는 환자에게 나가지만 우편ㆍ메일로도 도는데,
         뒷자리까지 찍어 두면 그 경로 어디서든 새어 나간다. --}}
    <td class="k">주민등록번호</td><td>{{ $residentNo }}</td>
  </tr>
  <tr>
    <td class="k">주소</td><td colspan="3">{{ $address }}</td>
  </tr>
</table>

<div class="sec">3. 구입내역</div>
<table class="items">
  <thead>
    <tr>
      <th style="width:12mm;">번호</th>
      <th style="width:28mm;">날짜</th>
      <th>품명</th>
      <th style="width:34mm;">환자부담금</th>
      <th style="width:40mm;">건강보험공단지원금</th>
      <th style="width:34mm;">총금액</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $i => $r)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td class="c">{{ $r['date'] }}</td>
        <td>{{ $r['name'] }}</td>
        <td class="r">{{ number_format($r['copay']) }}</td>
        <td class="r">{{ number_format($r['nhis']) }}</td>
        <td class="r">{{ number_format($r['total']) }}</td>
      </tr>
    @empty
      <tr><td class="c" colspan="6" style="padding:8mm 0;color:#888;">구입내역이 없습니다.</td></tr>
    @endforelse
    @if(count($rows))
      <tr class="sum">
        <td class="c" colspan="3">합계</td>
        <td class="r">{{ number_format(collect($rows)->sum('copay')) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('nhis')) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('total')) }}</td>
      </tr>
    @endif
  </tbody>
</table>

<div class="foot">
  위와 같이 의료용품을 구입하였음을 확인합니다.
</div>
<div class="sign">
  {{ $today }}<br>
  확인자 : {{ $patient->name }} &nbsp;(서명 또는 인)
</div>

<div class="pageno">— <span class="pagenum"></span> —</div>

</body>
</html>
