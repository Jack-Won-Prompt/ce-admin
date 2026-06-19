<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'NanumGothic', sans-serif; }
body { font-family: 'NanumGothic', sans-serif; font-size: 12px; color: #111; background:#fff; line-height:1.6; }
th, td, strong, b, h1, h2, h3, h4, h5, h6 { font-family: 'NanumGothic', sans-serif; }

/* ── 페이지 구분 ── */
.page-break { page-break-after: always; }

/* ── 위임장 스타일 ── */
.doc-title {
  text-align:center; font-size:18px; font-weight:800; letter-spacing:3px;
  border-top:3px solid #111; border-bottom:3px solid #111;
  padding:10px 0; margin-bottom:14px;
}
.doc-sub { text-align:center; font-size:10px; color:#555; margin-bottom:20px; letter-spacing:1px; }

.section { margin-bottom:14px; }
.section-title {
  font-size:12px; font-weight:700; background:#f0f0f0;
  padding:4px 8px; border-left:4px solid #333; margin-bottom:6px;
}
table.info-tbl { width:100%; border-collapse:collapse; font-size:11px; }
table.info-tbl td { padding:4px 7px; border:1px solid #bbb; vertical-align:middle; }
table.info-tbl td.label { width:26%; background:#fafafa; font-weight:600; }

.content-box { border:2px solid #333; padding:12px 14px; font-size:12px; line-height:2; text-align:justify; }
.content-box b { border-bottom:1px solid #111; }

.sign-area { margin-top:20px; }
.sign-date { text-align:center; font-size:13px; margin-bottom:16px; }
.sign-row { display:table; width:100%; }
.sign-block { display:table-cell; width:50%; border:1px solid #bbb; padding:12px 14px; min-height:90px; vertical-align:top; }
.sign-block + .sign-block { border-left:none; }
.sign-label { font-size:10px; font-weight:700; margin-bottom:6px; border-bottom:1px solid #ddd; padding-bottom:3px; }
.sign-blank { border-bottom:1px solid #555; width:120px; display:inline-block; height:14px; }
.sign-img { max-width:150px; max-height:55px; }

.note-box { margin-top:18px; padding:8px 12px; border:1px solid #ccc; background:#fafafa; font-size:10px; color:#555; }
.note-box li { list-style:disc; margin-left:14px; }

.badge-auto   { font-size:9px; background:#fffbeb; color:#b45309; border:1px solid #fcd34d; border-radius:2px; padding:1px 4px; }
.badge-signed { font-size:9px; background:#f0fdf4; color:#166634; border:1px solid #86efac; border-radius:2px; padding:1px 4px; }

/* ── 구매내역 스타일 ── */
.purchase-title { text-align:center; font-size:16px; font-weight:800; margin-bottom:12px; letter-spacing:2px; border-top:3px solid #111; border-bottom:3px solid #111; padding:8px 0; }
.purchase-meta { font-size:11px; margin-bottom:10px; }
table.purchase-tbl { width:100%; border-collapse:collapse; font-size:11px; }
table.purchase-tbl th { background:#333; color:#fff; padding:5px 8px; text-align:center; }
table.purchase-tbl td { padding:4px 8px; border:1px solid #bbb; }
table.purchase-tbl td.num { text-align:right; }
table.purchase-tbl td.center { text-align:center; }
.purchase-total { text-align:right; font-size:13px; font-weight:700; margin-top:8px; }

/* ── 처방전 커버 스타일 ── */
.rx-cover-title { text-align:center; font-size:16px; font-weight:700; margin-bottom:12px; border-top:3px solid #111; border-bottom:3px solid #111; padding:8px 0; }
.rx-section { page-break-inside: avoid; }
.rx-img-wrap { text-align:center; margin-top:8px; padding:0 50px; }
.rx-img-wrap img { width:100%; max-height:900px; }

/* ── 현금영수증 스타일 ── */
.cr-title { text-align:center; font-size:16px; font-weight:800; margin-bottom:12px; letter-spacing:2px; border-top:3px solid #111; border-bottom:3px solid #111; padding:8px 0; }
.cr-box { border:1px solid #bbb; border-radius:4px; padding:16px 20px; margin-top:12px; }
.cr-row { display:table; width:100%; margin-bottom:6px; font-size:12px; }
.cr-label { display:table-cell; width:30%; font-weight:700; color:#555; }
.cr-value { display:table-cell; font-weight:600; }
.cr-amount { text-align:center; font-size:22px; font-weight:800; padding:14px 0; border-top:1px solid #eee; border-bottom:1px solid #eee; margin:14px 0; }
.cr-note { font-size:10px; color:#777; margin-top:12px; }
</style>
</head>
<body>

@php
  $hasRx          = in_array('prescription', $docs) && $rxImageDataUri;
  $hasPurchase    = in_array('purchase_history', $docs) && $order;
  $hasCashReceipt = in_array('cash_receipt', $docs) && $order?->cash_receipt_status === 'issued';
  $hasAttachments = !empty($attachmentDataUris);
@endphp

{{-- ① 위임장 --}}
@if(in_array('authorization', $docs))
<div @if($hasRx || $hasPurchase || $hasCashReceipt || $hasAttachments) class="page-break" @endif>
  <div class="doc-title">건 강 보 험 요 양 급 여 청 구 위 임 장</div>
  <div class="doc-sub">건강보험법 시행규칙 제22조에 의거 요양급여비용의 청구 및 수령을 위임합니다.</div>

  <div class="section">
    <div class="section-title">① 위임인 (환자)</div>
    <table class="info-tbl">
      <tr>
        <td class="label">성 명</td>
        <td>{{ $patient->name ?? $prescription->patient_name_ocr ?? '—' }}</td>
        <td class="label">생년월일</td>
        <td>{{ $prescription->resident_no_ocr ? substr(preg_replace('/[^0-9]/','',$prescription->resident_no_ocr),0,6) : '—' }}</td>
      </tr>
      <tr>
        <td class="label">주민등록번호</td>
        <td colspan="3">
          @php
            $rn = preg_replace('/[^0-9]/', '', $prescription->resident_no_ocr ?? '');
          @endphp
          {{ $rn ? substr($rn,0,6).' - '.substr($rn,6,1).'●●●●●●' : '—' }}
        </td>
      </tr>
      <tr>
        <td class="label">주 소</td>
        <td colspan="3">{{ $patient->address ?? $prescription->address_ocr ?? '—' }}</td>
      </tr>
      <tr>
        <td class="label">연락처</td>
        <td>{{ $patient->mobile ?? $prescription->mobile_ocr ?? '—' }}</td>
        <td class="label">건강보험번호</td>
        <td>{{ $patient->health_insurance_no ?? '—' }}</td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">② 수임인 (요양기관)</div>
    <table class="info-tbl">
      <tr>
        <td class="label">기 관 명</td>
        <td>{{ config('nhis.institution.name', 'CE(씨이) 의료기기') }}</td>
        <td class="label">요양기관기호</td>
        <td>{{ config('nhis.institution.code', '—') }}</td>
      </tr>
      <tr>
        <td class="label">사업자번호</td>
        <td>{{ config('nhis.institution.biz_no') ?: '—' }}</td>
        <td class="label">대 표 자</td>
        <td>{{ config('popbill.company.ceo_name', '—') }}</td>
      </tr>
      <tr>
        <td class="label">주 소</td>
        <td colspan="3">{{ config('popbill.company.addr', '—') }}</td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">③ 처방 정보</div>
    <table class="info-tbl">
      <tr>
        <td class="label">처방전 번호</td>
        <td>{{ $prescription->rx_number }}</td>
        <td class="label">처방 발행일</td>
        <td>{{ $prescription->issued_date?->format('Y년 m월 d일') ?? '—' }}</td>
      </tr>
      <tr>
        <td class="label">발행 의료기관</td>
        <td>{{ $prescription->hospital_name ?? '—' }}</td>
        <td class="label">담당 의사</td>
        <td>{{ $prescription->doctor_name ?? '—' }}</td>
      </tr>
      <tr>
        <td class="label">상병명(코드)</td>
        <td colspan="3">{{ $prescription->disease_name ?? '—' }}{{ $prescription->disease_code ? ' ('.$prescription->disease_code.')' : '' }}</td>
      </tr>
      <tr>
        <td class="label">처방일수 / 총수량</td>
        <td colspan="3">{{ $prescription->total_days ? $prescription->total_days.'일' : '—' }} / {{ $prescription->total_count ? $prescription->total_count.'개' : '—' }}</td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">④ 위임 내용</div>
    <div class="content-box">
      본인 <b>{{ $patient->name ?? $prescription->patient_name_ocr ?? '위임인' }}</b>은(는)
      위의 수임인에게 상기 처방전에 의한
      <b>건강보험 요양급여비용의 청구 및 수령에 관한 일체의 권한</b>을 위임합니다.<br><br>
      위임 범위: 건강보험 급여 대상 보조기기(의료기기)의 급여비용 청구, 공단 부담금 수령, 관련 서류 제출 및 기타 청구에 필요한 모든 행위
    </div>
  </div>

  <div class="sign-area">
    <div class="sign-date">{{ now()->format('Y') }}년 {{ now()->format('m') }}월 {{ now()->format('d') }}일</div>
    <div class="sign-row">
      <div class="sign-block">
        <div class="sign-label">
          위임인 서명
          @if($consent && $consent->status === 'agreed')
            <span class="badge-signed">전자서명 완료</span>
          @else
            <span class="badge-auto">서명 없음</span>
          @endif
        </div>
        <div>성명: {{ $patient->name ?? $prescription->patient_name_ocr ?? '—' }} (인)</div>
        @if($consent?->signature_data)
          <br><img src="{{ $consent->signature_data }}" class="sign-img" alt="서명">
        @else
          <br><br><span class="sign-blank"></span>
        @endif
      </div>
      <div class="sign-block">
        <div class="sign-label">수임인 확인</div>
        <div>기관명: {{ config('nhis.institution.name', 'CE(씨이) 의료기기') }}</div>
        <div>대표: {{ config('popbill.company.ceo_name', '—') }} (인)</div>
        <br><span class="sign-blank"></span>
      </div>
    </div>
  </div>

  <div class="note-box">
    <ul>
      <li>본 위임장은 건강보험법 제47조 및 시행규칙 제22조에 근거합니다.</li>
      <li>위임 기간: 상기 처방전에 의한 급여비용 청구 완료 시까지</li>
      <li>본 문서는 {{ ($consent?->signature_data) ? '환자의 전자서명이 포함된 문서입니다.' : '시스템에 의해 자동 생성된 문서입니다.' }}</li>
    </ul>
  </div>
</div>
@endif

{{-- ② 처방전 이미지 --}}
@if(in_array('prescription', $docs) && $rxImageDataUri)
<div class="rx-section{{ ($hasPurchase || $hasCashReceipt || $hasAttachments) ? ' page-break' : '' }}">
  <div class="rx-cover-title">처 방 전</div>
  <table class="info-tbl" style="margin-bottom:10px;">
    <tr>
      <td class="label">처방전 번호</td><td>{{ $prescription->rx_number }}</td>
      <td class="label">환자명</td><td>{{ $patient->name ?? $prescription->patient_name_ocr ?? '—' }}</td>
    </tr>
    <tr>
      <td class="label">발행일</td><td>{{ $prescription->issued_date?->format('Y-m-d') ?? '—' }}</td>
      <td class="label">병원명</td><td>{{ $prescription->hospital_name ?? '—' }}</td>
    </tr>
  </table>
  <div class="rx-img-wrap">
    <img src="{{ $rxImageDataUri }}" alt="처방전 이미지">
  </div>
</div>
@endif

{{-- ③ 제품 구매내역 --}}
@if(in_array('purchase_history', $docs) && $order)
<div @if($hasCashReceipt || $hasAttachments) class="page-break" @endif>
  <div class="purchase-title">제 품 구 매 내 역 서</div>
  <div class="purchase-meta">
    주문번호: {{ $order->order_number ?? '—' }} &nbsp;|&nbsp;
    환자명: {{ $patient->name ?? $prescription->patient_name_ocr ?? '—' }} &nbsp;|&nbsp;
    처방전: {{ $prescription->rx_number }} &nbsp;|&nbsp;
    발행일: {{ $prescription->issued_date?->format('Y-m-d') ?? '—' }}
  </div>
  @php
    $rxItems    = $prescription->items ?? collect();
    $itemsTotal = $rxItems->sum(fn($i) => ($i->insurance_price ?? $i->product_price ?? 0) * ($i->quantity ?? 1));
    // 주문 연계 탭의 recalcAllItems() → calcItem() 공식과 동일하게 계산
    $nhisTotal  = $rxItems->sum(function ($i) {
        $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
        $qty  = (int)($i->quantity ?? 1);
        $rate = match ($i->nhis_status ?? 'eligible') {
            'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
        };
        return round($base * $rate * $qty);
    });
    $copayTotal = $rxItems->sum(function ($i) {
        $base = (float)($i->insurance_price ?? $i->product_price ?? 0);
        $qty  = (int)($i->quantity ?? 1);
        $rate = match ($i->nhis_status ?? 'eligible') {
            'eligible' => 0.9, 'partial' => 0.5, default => 0.0,
        };
        $nhis = round($base * $rate * $qty);
        return round($base * $qty) - $nhis;
    });
  @endphp
  <table class="purchase-tbl">
    <thead>
      <tr>
        <th style="width:40%">제품명</th>
        <th style="width:18%">제품코드</th>
        <th style="width:10%">수량</th>
        <th style="width:16%">단가(원)</th>
        <th style="width:16%">금액(원)</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rxItems as $item)
      @php
        $unitPrice = (float)($item->insurance_price ?? $item->product_price ?? 0);
        $lineTotal = $unitPrice * ($item->quantity ?? 1);
      @endphp
      <tr>
        <td>{{ $item->product_name }}</td>
        <td class="center">{{ $item->product_code }}</td>
        <td class="center">{{ $item->quantity }}</td>
        <td class="num">{{ number_format($unitPrice) }}</td>
        <td class="num">{{ number_format($lineTotal) }}</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;color:#999;">구매 내역 없음</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="margin-top:10px;border-top:1px solid #bbb;padding-top:8px;">
    <div style="display:table;width:100%;font-size:11px;margin-bottom:4px;">
      <span style="display:table-cell;color:#555;">급여 청구 금액</span>
      <span style="display:table-cell;text-align:right;font-weight:600;">{{ number_format((float)$nhisTotal) }}원</span>
    </div>
    <div style="display:table;width:100%;font-size:11px;margin-bottom:6px;">
      <span style="display:table-cell;color:#555;">환자부담 (급여 적용 후)</span>
      <span style="display:table-cell;text-align:right;font-weight:600;">{{ number_format((float)$copayTotal) }}원</span>
    </div>
  </div>
  <div class="purchase-total">
    합 계: {{ number_format($itemsTotal) }}원
  </div>
</div>
@endif


{{-- ④ 현금영수증 --}}
@if($hasCashReceipt)
@php
  $crTypeLabel = $order->cash_receipt_type === 'income_deduction' ? '소득공제' : '지출증빙';
  $crIdentifier = $order->cash_receipt_type === 'income_deduction'
    ? ($order->cash_receipt_phone ?? '—')
    : ($order->cash_receipt_biz_no ?? '—');
  $crIdentifierLabel = $order->cash_receipt_type === 'income_deduction' ? '휴대폰번호' : '사업자번호';
@endphp
<div @if($hasAttachments) class="page-break" @endif>
  <div class="cr-title">현 금 영 수 증</div>
  <div class="cr-box">
    <div class="cr-row">
      <span class="cr-label">발행일</span>
      <span class="cr-value">{{ $order->cash_receipt_issued_at?->format('Y년 m월 d일') ?? now()->format('Y년 m월 d일') }}</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">승인번호</span>
      <span class="cr-value" style="font-family:monospace;">{{ $order->cash_receipt_no ?? '—' }}</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">유형</span>
      <span class="cr-value">{{ $crTypeLabel }}</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">{{ $crIdentifierLabel }}</span>
      <span class="cr-value">{{ $crIdentifier }}</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">환자명</span>
      <span class="cr-value">{{ $patient->name ?? $prescription->patient_name_ocr ?? '—' }}</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">주문번호</span>
      <span class="cr-value">{{ $order->order_number ?? '—' }}</span>
    </div>
    <div class="cr-amount">
      {{ number_format((int)$order->cash_receipt_amount) }} 원
    </div>
    <div class="cr-row">
      <span class="cr-label">공급가액</span>
      <span class="cr-value">{{ number_format((int)round($order->cash_receipt_amount / 1.1)) }}원</span>
    </div>
    <div class="cr-row">
      <span class="cr-label">부가세</span>
      <span class="cr-value">{{ number_format((int)round($order->cash_receipt_amount - $order->cash_receipt_amount / 1.1)) }}원</span>
    </div>
    <div class="cr-note">
      본 현금영수증은 국세청 홈택스(www.hometax.go.kr)에서 확인하실 수 있습니다.
    </div>
  </div>
</div>
@endif

{{-- ⑤ 첨부파일 이미지 --}}
@if(!empty($attachmentDataUris))
@foreach($attachmentDataUris as $att)
<div @if(!$loop->last) class="page-break" @endif>
  <div class="rx-img-wrap" style="margin-top:0;padding:0;">
    <img src="{{ $att['dataUri'] }}" alt="{{ $att['label'] }}">
  </div>
</div>
@endforeach
@endif

</body>
</html>
