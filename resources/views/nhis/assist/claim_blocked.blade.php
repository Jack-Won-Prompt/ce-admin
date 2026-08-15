{{-- 공단 청구 건이 아닐 때 --}}
{{--
  공단 서식을 그대로 보여 주면 담당자가 옮겨 적기 시작한다. 지자체 건은 청구처도 서류도
  보내는 방법도 다르므로, 화면을 여는 대신 여기서 멈추고 무엇이 다른지 알린다.
--}}
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>공단 청구 대상이 아닙니다 — {{ $order->order_number }}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Malgun Gothic','맑은 고딕',sans-serif; background:#f7f8fa; color:#111827;
         font-size:13px; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:24px; }
  .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; max-width:560px; width:100%;
          padding:26px 28px; }
  h1 { font-size:16px; margin-bottom:6px; }
  .sub { font-size:12px; color:#6b7280; margin-bottom:18px; }
  .badge { display:inline-block; background:#fff7e8; border:1px solid #f0d9ae; color:#a35c00;
           border-radius:6px; padding:3px 9px; font-size:12px; font-weight:700; }
  table { width:100%; border-collapse:collapse; margin:16px 0; }
  th, td { border-bottom:1px solid #f2f4f6; padding:8px 4px; text-align:left; font-size:12px; }
  th { width:110px; color:#6b7280; font-weight:400; }
  td { font-weight:700; }
  ul { margin:8px 0 0 18px; font-size:12px; line-height:1.9; }
  .note { background:#f9fafb; border:1px solid #eef1f4; border-radius:8px; padding:11px 13px;
          font-size:12px; color:#4b5563; line-height:1.8; margin-top:16px; }
  .btn { display:inline-block; margin-top:18px; border:1px solid #28798B; background:#28798B; color:#fff;
         border-radius:7px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; }
</style>
</head>
<body>
<div class="card">
  <h1>공단 청구 대상이 아닙니다</h1>
  <div class="sub">{{ $order->order_number }}@if($prescription) · {{ $prescription->rx_number }}@endif</div>

  <table>
    <tr><th>청구처</th><td><span class="badge">{{ $agencyLabel }}</span></td></tr>
    <tr><th>급여구분</th><td>{{ $prescription?->benefit_class ?: '—' }}</td></tr>
    @if($agency === \App\Support\ClaimAgency::LOCAL)
      <tr><th>관할 지자체</th><td>{{ $prescription?->local_gov ?: '지정되지 않았습니다' }}</td></tr>
    @endif
  </table>

  @if($agency === \App\Support\ClaimAgency::LOCAL)
    <div><b>지자체 청구는 공단 사이트가 아니라 등기로 보냅니다.</b></div>
    <ul>
      <li>위임 등록 절차가 없습니다</li>
      <li>보낼 서류 — 처방전 / 거래명세서 / 전자세금계산서(주민등록번호) / 의료용품구입확인서(지자체용)</li>
      <li>관할 시·군·구청으로 등기 발송</li>
    </ul>
    <div class="note">
      지자체 청구를 돕는 화면은 아직 없습니다. 의료용품구입확인서(지자체용) 서식과 지자체별
      주소를 아직 갖고 있지 않아 만들지 못했습니다 — 지금은 기존 방식대로 진행하십시오.
    </div>
  @else
    <div><b>이 건은 요양비 청구 대상이 아닙니다.</b></div>
    <div class="note">
      자동차보험·산재는 보험사와 근로복지공단 소관이라 공단·지자체 어느 쪽에도 요양비를
      청구하지 않습니다. 청구처가 잘못 지정된 것이라면 처방전 화면에서 고치십시오.
    </div>
  @endif

  <a class="btn" href="#" onclick="window.close();return false;">창 닫기</a>
</div>
</body>
</html>
