{{-- 국민건강보험법 시행규칙 [별지 제19호의7서식] 요양비 지급청구 위임장 --}}
@php
    $patient = $consent->prescription?->patient;
    $name    = $consent->patient_name ?? $patient?->name ?? '';
    $rrn     = $patient?->resident_no ?? '';
    $phone   = $consent->patient_mobile ?? $patient?->mobile ?? '';
    $signDate = $consent->responded_at ?? now();
    $y = $signDate->format('Y');
    $m = $signDate->format('n');
    $d = $signDate->format('j');
    // 이 서비스는 자가도뇨(카테터) 요양비 위임이므로 ④-4) 항목 체크
    $chk   = '[✔]';
    $unchk = '[ ]';

    // ② 준요양기관 / ③ 수령계좌 (config/delegation.php ← .env)
    $prov = config('delegation.provider');
    $acct = config('delegation.account');

    // ⑤ 위임기간: 서명일부터 N년 (최장 5년)
    $periodYears = min(5, max(1, (int) config('delegation.period_years', 5)));
    $endDate = $signDate->copy()->addYears($periodYears);
    $sy = $signDate->format('Y'); $sm = $signDate->format('n'); $sd = $signDate->format('j');
    $ey = $endDate->format('Y');  $em = $endDate->format('n');  $ed = $endDate->format('j');
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 18mm 15mm; }
    * { font-family: 'NanumGothic', sans-serif; box-sizing: border-box; }
    body { font-size: 10px; color: #000; line-height: 1.4; }
    .form-header { font-size: 9.5px; margin-bottom: 4px; }
    .form-header .rev { color: #000; }
    .form-title { text-align: center; font-size: 19px; font-weight: bold; letter-spacing: 8px; margin: 6px 0 10px; }
    .guide { font-size: 8.5px; margin-bottom: 6px; }
    .guide .side { float: right; }
    table.frm { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.frm td { border: 0.7px solid #000; padding: 4px 5px; vertical-align: middle; }
    .sec  { text-align: center; font-weight: bold; width: 62px; }
    .sub  { text-align: center; width: 78px; }
    .lbl  { width: 118px; }
    .val  { min-height: 20px; }
    .center { text-align: center; }
    .items td { border: 0.7px solid #000; }
    .items .cell { padding: 1px 0; }
    .legal { margin: 12px 2px 0; font-size: 10px; line-height: 1.6; }
    .dateline { text-align: right; margin: 12px 40px 0 0; letter-spacing: 2px; }
    /* 서명란: 위임인 [서명] (서명 또는 인) 을 같은 기준선(baseline)에 정렬 */
    .sign-tbl  { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .sign-tbl td { border: 0; padding: 0; }
    .sign-cell { text-align: right; white-space: nowrap; width: 1%; vertical-align: bottom; padding-right: 34px; }
    .sign-name   { vertical-align: bottom; font-size: 11px; }
    .sign-suffix { vertical-align: bottom; font-size: 11px; }
    .sign-slot { display: inline-block; width: 160px; text-align: center; vertical-align: bottom; }
    .sign-slot img { height: 42px; vertical-align: bottom; margin-bottom: -2px; }
    .footer-note { font-size: 8.5px; text-align: right; margin-top: 10px; }
</style>
</head>
<body>

    <div class="form-header">
        ■ 국민건강보험법 시행규칙 [별지 제19호의7서식] <span class="rev">&lt;개정 2026. 3. 25.&gt;</span>
    </div>

    <div class="form-title">요양비 지급청구 위임장</div>

    <div class="guide">
        <span class="side">(앞쪽)</span>
        ※ 뒤쪽의 유의사항을 읽고 작성해 주시기 바라며, [ ]에는 해당되는 곳에 √ 표시를 합니다.
    </div>

    <table class="frm">
        {{-- ① 위임인 --}}
        <tr>
            <td class="sec" rowspan="6">① 위임인</td>
            <td class="sub" rowspan="2">가입자 또는<br>피부양자</td>
            <td class="lbl">성명</td>
            <td class="val">{{ $name }}</td>
        </tr>
        <tr>
            <td class="lbl">주민(외국인)등록번호</td>
            <td class="val">{{ $rrn }}</td>
        </tr>
        <tr>
            <td class="sub" rowspan="3">법정대리인<br>또는 가족</td>
            <td class="lbl">성명</td>
            <td class="val"></td>
        </tr>
        <tr>
            <td class="lbl">생년월일</td>
            <td class="val"></td>
        </tr>
        <tr>
            <td class="lbl">가입자ㆍ피부양자와의 관계</td>
            <td class="val"></td>
        </tr>
        <tr>
            <td class="sub">전화번호<br><span style="font-size:8px;">(위임사항 및<br>지급내역 수신용)</span></td>
            <td class="lbl" colspan="2">
                {{ $phone }}
                <div style="margin-top:3px;">{{ $unchk }} 문자메시지 수신동의</div>
                <div style="font-size:8px;color:#333;margin-top:2px;">
                    ※ 위임처리결과 및 지급내역 등을 전송받을 연락처로 정확히 적어 주시기 바랍니다.<br>
                    (문자메시지 수신을 동의한 경우에만 발송)
                </div>
            </td>
        </tr>

        {{-- ② 준요양기관 --}}
        <tr>
            <td class="sec" rowspan="4">② 준요양기관</td>
            <td class="lbl" colspan="2">상호</td>
            <td class="val">{{ $prov['name'] }}</td>
        </tr>
        <tr>
            <td class="lbl" colspan="2">사업자등록번호<br>(법인등록번호)</td>
            <td class="val">{{ $prov['biz_no'] }}</td>
        </tr>
        <tr>
            <td class="lbl" colspan="2">대표자</td>
            <td class="val">{{ $prov['ceo'] }}</td>
        </tr>
        <tr>
            <td class="lbl" colspan="2">전화번호<br>(휴대전화번호)</td>
            <td class="val">{{ $prov['phone'] }}</td>
        </tr>

        {{-- ③ 요양비 수령계좌 --}}
        <tr>
            <td class="sec" rowspan="4">③ 요양비<br>수령계좌</td>
            <td class="sub" colspan="2">수령자</td>
            <td class="val">{{ $acct['receiver'] }}</td>
        </tr>
        <tr>
            <td class="sub" rowspan="3">수령계좌</td>
            <td class="lbl">금융기관명</td>
            <td class="val">{{ $acct['bank'] }}</td>
        </tr>
        <tr>
            <td class="lbl">예금주</td>
            <td class="val">{{ $acct['holder'] }}</td>
        </tr>
        <tr>
            <td class="lbl">계좌번호</td>
            <td class="val">{{ $acct['number'] }}</td>
        </tr>

        {{-- ④ 위임사항 --}}
        <tr>
            <td class="sec">④ 위임사항</td>
            <td colspan="3" style="padding:6px 8px;">
                <table class="items" style="width:100%; border:0; border-collapse:collapse;">
                    <tr>
                        <td class="cell" style="border:0;">1) {{ $unchk }} 자동복막투석 소모성 재료</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 복막관류액</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">2) {{ $unchk }} 가정용 산소발생기</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 휴대용 산소발생기</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">3) {{ $unchk }} 당뇨병 소모성 재료</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 연속혈당측정용 전극(센서)</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;"><b>4) {{ $chk }} 자가도뇨 소모성 재료</b></td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">5) {{ $unchk }} 인공호흡기 및 기본소모품</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 선택소모품</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">6) {{ $unchk }} 기침유발기</td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">7) {{ $unchk }} 양압기 및 소모품</td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">8) {{ $unchk }} 연속혈당측정기</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 인슐린자동주입기</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">9) {{ $unchk }} 산소포화도측정기</td>
                        <td class="cell" style="border:0;">{{ $unchk }} 소모품</td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">10) {{ $unchk }} 기도흡인기</td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">11) {{ $unchk }} 경장영양주입펌프</td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                    <tr>
                        <td class="cell" style="border:0;">12) {{ $unchk }} 출산비</td>
                        <td class="cell" style="border:0;"></td>
                    </tr>
                </table>
                <div style="font-size:8px; margin-top:2px;">※ [✔]표시, 중복 표시 가능</div>
            </td>
        </tr>

        {{-- ⑤ 위임기간 --}}
        <tr>
            <td class="sec">⑤ 위임기간</td>
            <td colspan="3" class="center">
                {{ $sy }} 년 &nbsp; {{ $sm }} 월 &nbsp; {{ $sd }} 일부터 &nbsp;&nbsp;
                {{ $ey }} 년 &nbsp; {{ $em }} 월 &nbsp; {{ $ed }} 일까지 &nbsp; (최장 5년)
            </td>
        </tr>
    </table>

    <div class="legal">
        「국민건강보험법」 제49조제3항 및 같은 법 시행규칙 제23조제4항에 따라 요양비 지급 청구에 관한
        사항을 위와 같이 위임합니다.
    </div>

    <div class="dateline">{{ $y }} 년 &nbsp; {{ $m }} 월 &nbsp; {{ $d }} 일</div>

    <table class="sign-tbl">
        <tr>
            <td></td>
            <td class="sign-cell">
                <span class="sign-name">위임인</span>
                <span class="sign-slot">
                    @if($consent->signature_data)
                        <img src="{{ $consent->signature_data }}" alt="서명">
                    @endif
                </span>
                <span class="sign-suffix">(서명 또는 인)</span>
            </td>
        </tr>
    </table>

    <div class="footer-note">210㎜×297㎜[백상지(80g/㎡) 또는 중질지(80g/㎡)]</div>

</body>
</html>
