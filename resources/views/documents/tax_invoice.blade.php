{{-- 전자세금계산서 — 서식은 조각이 그리고, 이 장은 **두 벌**을 얹는다.

     세금계산서는 원래 두 장이다 (2026-09-28 지시).
       위 — 공급자 보관용 (적색)
       아래 — 공급받는자 보관용 (청색)

     한 벌만 내면 받는 쪽이 제 보관용을 못 가진다. 종이 원본도 그 두 장을 한 면에
     위아래로 찍어 낸다.

     같은 조각이 공단 팩스 합본에도 끼워진다(resources/views/prescriptions/fax-pdf.blade.php).
     저쪽은 한 벌만 얹는다 — 공단에 내는 묶음이라 받는 쪽 보관용이 따로 필요 없고,
     팩스는 장수만큼 값을 치른다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>전자세금계산서_{{ $doc['ntsNo'] }}</title>
<style>
  @page { margin: 0; }
  html, body { margin: 0; padding: 0; }
  /* 서식은 182mm 짜리다. A4 왼쪽 위에 그대로 앉힌다 — 원본도 그렇게 나온다. */
  .sheet { padding: 9mm 12mm; }
  /* 두 벌 사이 — 가위로 자르는 자리다. 받아 본 원본이 여기에 점선을 그어 둔다.
     dompdf 는 border-style:dotted 를 점으로 그리지 못할 때가 있어, 원본과 같은
     성긴 점을 글자로 찍는다. 너무 붙이면 자를 때 아래 장의 머리가 잘린다. */
  .cut { width: 182mm; margin: 3.5mm 0 3.5mm; font-size: 6.5pt; color: #777777;
         letter-spacing: 1.6mm; white-space: nowrap; overflow: hidden; }
</style>
</head>
<body>
<div class="sheet">
  {{-- 공급자 보관용 (적색) — 스타일은 이 한 번만 낸다 --}}
  @include('documents._tax_invoice_form', ['keep' => '공급자'])

  {{-- 자르는 자리 --}}
  <div class="cut">{{ str_repeat('·', 120) }}</div>

  {{-- 공급받는자 보관용 (청색) — 값은 위와 같고 빛깔과 글귀만 갈린다.
       꼬리는 두지 않는다 — 받아 본 원본이 그렇다. --}}
  @include('documents._tax_invoice_form', ['keep' => '공급받는자', 'withStyle' => false, 'withTail' => false])
</div>
</body>
</html>
