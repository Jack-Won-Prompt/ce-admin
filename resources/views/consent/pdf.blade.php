<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'NanumGothic', sans-serif; }
    body {
      font-family: 'NanumGothic', sans-serif;
      font-size: 11px;
      color: #1a1a1a;
      padding: 26px 38px;
      line-height: 1.4;
    }

    /* ── 헤더 ── */
    .doc-header {
      text-align: center;
      border-bottom: 2px solid #1B66F5;
      padding-bottom: 8px;
      margin-bottom: 12px;
    }
    .doc-header .org {
      font-size: 9px;
      color: #6b7280;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .doc-header h1 {
      font-size: 16px;
      font-weight: 700;
      color: #1B66F5;
    }
    .doc-header .sub {
      font-size: 9px;
      color: #6b7280;
      margin-top: 2px;
    }

    /* ── 정보 테이블 ── */
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
      border: 1px solid #d1d5db;
    }
    .info-table th {
      background: #f3f4f6;
      font-size: 9px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      padding: 5px 10px;
      text-align: left;
      border-bottom: 1px solid #d1d5db;
      width: 110px;
    }
    .info-table td {
      padding: 5px 10px;
      border-bottom: 1px solid #e5e7eb;
      font-size: 11px;
    }
    .info-table tr:last-child th,
    .info-table tr:last-child td { border-bottom: none; }

    /* ── 동의 내용 박스 ── */
    .consent-box {
      border: 1px solid #c7dcff;
      background: #f4f8ff;
      border-radius: 6px;
      padding: 10px 14px;
      margin-bottom: 12px;
      font-size: 11px;
      line-height: 1.65;
    }
    .consent-box .title {
      font-size: 10px;
      font-weight: 700;
      color: #1B66F5;
      margin-bottom: 6px;
    }

    /* ── 서명 영역 ── */
    .sig-section {
      margin-bottom: 12px;
    }
    .sig-section .label {
      font-size: 10px;
      font-weight: 700;
      color: #374151;
      margin-bottom: 6px;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 4px;
    }
    .sig-box {
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 8px;
      background: #fff;
      text-align: center;
    }
    .sig-box img {
      max-width: 300px;
      max-height: 110px;
    }

    /* ── 확인 도장 행 ── */
    .stamp-row {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    .stamp-cell {
      border: 1px solid #d1d5db;
      padding: 8px 12px;
      text-align: center;
      width: 50%;
    }
    .stamp-cell .role {
      font-size: 9px;
      color: #9ca3af;
      margin-bottom: 3px;
    }
    .stamp-cell .name {
      font-size: 12px;
      font-weight: 700;
    }
    .stamp-cell .stamp-area {
      height: 34px;
      border: 1px dashed #d1d5db;
      border-radius: 3px;
      margin-top: 6px;
      line-height: 34px;
      font-size: 9px;
      color: #d1d5db;
    }

    /* ── 푸터 ── */
    .doc-footer {
      border-top: 1px solid #e5e7eb;
      padding-top: 8px;
      font-size: 9px;
      color: #9ca3af;
      text-align: center;
    }

    .badge-agreed {
      display: inline-block;
      background: #dcfce7;
      color: #15803d;
      border: 1px solid #86efac;
      border-radius: 3px;
      padding: 1px 6px;
      font-size: 10px;
      font-weight: 700;
    }
  </style>
</head>
<body>

  {{-- ── 헤더 ── --}}
  <div class="doc-header">
    <div class="org">CE ADMIN · 건강보험 급여 관리 시스템</div>
    <h1>건강보험 급여 위임동의서</h1>
    <div class="sub">Health Insurance Benefit Delegation Consent</div>
  </div>

  {{-- ── 동의 현황 ── --}}
  <table class="info-table">
    <tr>
      <th>처리 상태</th>
      <td><span class="badge-agreed">동의 완료</span></td>
    </tr>
    <tr>
      <th>환자명</th>
      <td><strong>{{ $consent->patient_name }}</strong></td>
    </tr>
    <tr>
      <th>연락처</th>
      <td>{{ $consent->patient_mobile }}</td>
    </tr>
    @if($consent->prescription)
    <tr>
      <th>처방전 번호</th>
      <td>{{ $consent->prescription->rx_number ?? '-' }}</td>
    </tr>
    @endif
    <tr>
      <th>서명 일시</th>
      <td>{{ $consent->responded_at?->format('Y년 m월 d일 H:i:s') ?? '-' }}</td>
    </tr>
    <tr>
      <th>문서 생성</th>
      <td>{{ now()->format('Y년 m월 d일 H:i:s') }}</td>
    </tr>
  </table>

  {{-- ── 동의 내용 ── --}}
  <div class="consent-box">
    <div class="title">■ 위임동의 내용</div>
    본인 <strong>{{ $consent->patient_name }}</strong>은(는) 건강보험 요양급여비용 청구와 관련하여
    콜로플라스트 코리아(주)가 건강보험공단에 제출하는 서류에 대한
    <strong>급여 위임청구 동의</strong>를 합니다.<br><br>
    위임 내용: 건강보험 급여 대상 보조기기의 급여비용 청구 및 수령에 관한 일체의 행위<br><br>
    본인은 위 내용을 충분히 이해하였으며, 자유의사에 따라 동의합니다.
  </div>

  {{-- ── 서명 ── --}}
  <div class="sig-section">
    <div class="label">■ 본인 서명</div>
    <div class="sig-box">
      @if($consent->signature_data)
        <img src="{{ $consent->signature_data }}" alt="서명" />
      @else
        <span style="color:#9ca3af;font-size:12px;">서명 이미지 없음</span>
      @endif
    </div>
  </div>

  {{-- ── 확인란 ── --}}
  <table class="stamp-row">
    <tr>
      <td class="stamp-cell" style="margin-right:10px;">
        <div class="role">동의인</div>
        <div class="name">{{ $consent->patient_name }}</div>
        <div class="stamp-area">서명 위 참조</div>
      </td>
      <td style="width:20px;"></td>
      <td class="stamp-cell">
        <div class="role">수령인 (담당자)</div>
        <div class="name">&nbsp;</div>
        <div class="stamp-area">인</div>
      </td>
    </tr>
  </table>

  {{-- ── 푸터 ── --}}
  <div class="doc-footer">
    본 문서는 CE Admin 시스템에서 자동 생성된 위임동의서입니다.
    위변조 방지를 위해 원본 서명 이미지가 포함되어 있습니다.<br>
    Generated: {{ now()->toIso8601String() }}
  </div>

</body>
</html>
