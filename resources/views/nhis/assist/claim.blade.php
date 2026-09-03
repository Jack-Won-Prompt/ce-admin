{{-- 공단 요양비지급청구서등록(2221) 입력 지원 --}}
{{--
  공단 서식과 칸의 자리·이름·크기를 그대로 맞춘 화면이다. 담당자가 두 화면을 좌우로 놓고
  눈을 옮길 때 같은 자리를 보게 하려는 것이 전부다. 칸을 누르면 그 값이 복사된다.

  껍데기는 본 화면과 같은 디자인(layouts.popup)을 쓴다. 사이드바만 없을 뿐, 색·모서리·글꼴이
  갈리면 우리 화면이 아닌 것처럼 보인다.
--}}
@extends('layouts.popup')

@section('windowTitle', '요양비청구등록 — ' . ($order->patient?->name ?? $prescription?->patient_name_ocr ?? $order->order_number))

@push('styles')
<style>
  /* ── 우리 도구 막대 — 공단 서식이 아니라 우리 것임이 한눈에 보여야 한다 ── */
  .tools {
    position:sticky; top:0; z-index:20; display:flex; align-items:center; gap:10px;
    background:var(--primary-700, #1f5b68); color:var(--gray-0);
    padding:8px 14px; flex-wrap:wrap;
  }
  .tools b { font-size:12px; font-weight:700; }
  .tools .grow { flex:1; }
  .tools .miss { color:var(--alert-200, #ffb4b4); font-weight:700; font-size:11px; }
  .tbtn {
    border:1px solid rgba(255,255,255,.45); background:transparent; color:var(--gray-0);
    border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; cursor:pointer;
    font-family:inherit;
  }
  .tbtn:hover { background:rgba(255,255,255,.15); }
  .tbtn.on { background:var(--gray-0); color:var(--primary-700, #1f5b68); }
  .pbar { width:150px; height:5px; background:rgba(255,255,255,.3); border-radius:99px; overflow:hidden; }
  .pfill { height:100%; background:var(--primary-200); width:0; transition:width .2s; }

  /* ── 여기서부터 공단 서식과 같은 구조 ──
     서식은 원래 넓다. 창을 좌우로 반씩 쓰면 그대로는 잘리므로 폭에 맞춰 통째로 줄인다.
     칸의 자리와 비율은 그대로라 공단 화면과 눈으로 대조하는 데는 지장이 없다. */
  .stage { overflow:hidden; }
  .sheet { width:1180px; padding:12px 14px 40px; transform-origin:top left; background:var(--gray-0); }

  .sec { display:flex; align-items:center; gap:8px; margin:14px 0 5px; }
  .sec:first-child { margin-top:4px; }
  .sec-name { font-size:13px; font-weight:700; color:var(--gray-1000); }
  .sec-help { font-size:11px; color:var(--alert-500); font-weight:500; }
  .sec-right { margin-left:auto; font-size:11px; color:var(--text-muted); }
  .red-r { color:var(--alert-500); font-size:11px; text-align:right; line-height:1.7; }

  table.form { width:100%; border-collapse:collapse; table-layout:fixed;
               border:1px solid var(--gray-200); border-radius:8px; overflow:hidden; }
  table.form th, table.form td { border:1px solid var(--gray-200); padding:0; height:34px; }
  table.form th {
    background:var(--gray-100); font-weight:500; font-size:12px; text-align:left;
    padding:0 8px; color:var(--gray-700);
  }
  table.form th small { display:block; font-size:10px; color:var(--gray-500); font-weight:400; }
  table.form td { padding:4px 6px; }

  /* 값 칸 — 본 화면 입력칸과 같은 규격(r8 · 1px --border)이되 누르면 복사된다 */
  .fld {
    display:inline-flex; align-items:center; min-height:26px; padding:3px 8px;
    border:1px solid var(--border); background:var(--gray-0); border-radius:8px;
    font-size:12px; font-weight:500; color:var(--gray-1000);
    cursor:pointer; user-select:none; max-width:100%;
  }
  .fld:hover { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary-100); }
  .fld.w-sm { width:92px; } .fld.w-md { width:148px; }
  .fld.w-lg { width:200px; } .fld.w-full { width:100%; }
  /* 칸은 상태와 상관없이 흰 바탕에 실선 하나로 통일한다. 점선·색 바탕을 섞으면 공단
     서식의 빈칸과 모양이 달라지고, 화면이 값보다 상태를 먼저 말하게 된다.
     복사한 칸만 테두리 색으로 표가 나는데, 어디까지 옮겼는지는 남아야 하기 때문이다. */
  .fld.fixed { background:var(--gray-0); }
  .fld.none, .fld.ask { cursor:not-allowed; }
  .fld.done { border-color:var(--primary-400); }
  .fld-v { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-height:16px; }

  /* 설명은 한 줄로 묶는다. 여러 줄이 되면 칸이 밀려 공단 서식과 행 높이가 어긋난다. */
  .hint { font-size:10px; color:var(--text-muted); margin-top:2px; line-height:1.4;
          white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .wmark { color:var(--warning-500, #b45309); font-weight:700; cursor:help; margin-left:4px; }
  .unit { font-size:11px; color:var(--gray-600); margin:0 3px; }
  .btnish { display:inline-block; border:1px solid var(--border); background:var(--gray-50);
            border-radius:6px; padding:3px 9px; font-size:11px; color:var(--gray-600); }

  /* 국세청자료 */
  table.tax { width:100%; border-collapse:collapse; table-layout:fixed;
              border:1px solid var(--gray-200); border-radius:8px; overflow:hidden; }
  table.tax th, table.tax td { border:1px solid var(--gray-200); height:32px; padding:4px 8px; font-size:11px; }
  table.tax th { background:var(--gray-100); font-weight:500; text-align:center; color:var(--gray-700); }
  table.tax td { text-align:center; }
  table.tax td.l { text-align:left; }
  .tax-empty { color:var(--alert-500); }

  /* 제출서류 */
  table.docs { width:100%; border-collapse:collapse; border:1px solid var(--gray-200);
               border-radius:8px; overflow:hidden; }
  table.docs th, table.docs td { border:1px solid var(--gray-200); padding:7px 10px; font-size:11px; vertical-align:top; }
  table.docs th { background:var(--gray-100); font-weight:500; text-align:center; color:var(--gray-700); }
  .have { color:var(--primary-600); font-weight:700; }
  .havent { color:var(--alert-500); font-weight:700; }
  .dl { color:var(--primary); font-weight:700; text-decoration:none; }
  .dl:hover { text-decoration:underline; }

  .cautions { font-size:11px; color:var(--text-muted); line-height:1.9; padding:8px 2px; }
</style>
@endpush

@section('body')
@php
  /**
   * 칸 하나를 그린다. 공단 서식의 입력 상자와 같은 자리에 선다.
   *   $k  칸 이름(키) — 복사 기록도 이 이름으로 남는다
   *   $w  너비 (sm / md / lg / full)
   *
   * 값이 없으면 칸만 세운다. 「없음」·「계산식 미확인」 같은 말을 칸 안에 적으면 그것이
   * 값처럼 보여 옮겨 적힐 수 있고, 공단 서식의 빈칸과 모양도 달라진다. 왜 비었는지는
   * 마우스를 올리면 나온다.
   */
  $box = function (string $k, string $w = 'md') use ($f) {
    $r = $f[$k] ?? ['value' => null];
    $v = $r['value'] ?? null;

    $can = ($r['copy'] ?? true) && $v !== null;
    $cls = 'fld w-' . $w;
    if ($r['fixed'] ?? false) { $cls .= ' fixed'; }
    if ($v === null)          { $cls .= ($r['ask'] ?? false) ? ' ask' : ' none'; }

    // 비어 있는 까닭은 칸 안이 아니라 툴팁에 둔다
    $tip = $can ? '누르면 복사됩니다' : trim(($r['blank'] ?? '') . ' ' . ($r['note'] ?? ''));

    $html = '<span class="' . $cls . '" data-key="' . $k . '" data-copy="' . ($can ? 1 : 0) . '"'
          . (($r['reveal'] ?? false) ? ' data-reveal="1"' : '')
          . ' title="' . e($tip ?: '값이 없습니다') . '" onclick="copyBox(this)">'
          . '<span class="fld-v" data-val>' . e($v ?? '') . '</span></span>';

    if ($r['warn'] ?? null) {
      $html .= '<span class="wmark" title="' . e($r['warn']) . '">⚠</span>';
    }
    // 설명은 값이 있는 칸에만 붙인다 — 계산 근거처럼 실제로 도움이 되는 것들이다
    if ($v !== null && ($r['note'] ?? null)) {
      $html .= '<div class="hint" title="' . e($r['note']) . '">' . e($r['note']) . '</div>';
    }

    return $html;
  };
@endphp

{{-- 우리 도구 막대 — 공단 서식이 아니라 우리 것임이 한눈에 보여야 한다 --}}
<div class="tools">
  <b>CE Admin · 청구 원본</b>
  {{-- 창고와 이야기할 때 쓰는 번호는 판매번호다 — 주문번호 옆에 함께 적는다(2026-09-03) --}}
  <span style="font-size:11px;opacity:.85">{{ $order->order_number }}{{ $order->saleNoSuffix() }}@if($prescription) · {{ $prescription->rx_number }}@endif</span>
  <div class="pbar"><div class="pfill" id="pfill"></div></div>
  <span id="ptext" style="font-size:11px">0 / 0</span>
  @if($missing > 0)<span class="miss">값 없음 {{ $missing }}</span>@endif
  <div class="grow"></div>
  <button class="tbtn" id="fitBtn" onclick="toggleFit()">폭 맞춤 <span id="fitPct"></span></button>
  <button class="tbtn" onclick="resetAll()">복사 기록 지우기</button>
  {{-- 프레임 안에서 열렸을 때는 다시 프레임을 열 이유가 없다 --}}
  <button class="tbtn on" id="splitBtn" onclick="openSplit()">좌우 분할</button>
  <button class="tbtn" onclick="openPortal()">공단 새 창</button>

  {{-- 공단 사이트에서 제출을 마치고 나면 그것을 여기 적는다. 적지 않으면 목록에서는
       여태 「청구 전」이라, 담당자가 낸 건을 다시 열어 보게 된다(지자체는 등기 발송을
       적으면서 저절로 남는다 — 그 짝이다). --}}
  @if(in_array($order->nhis_claim_status, ['submitted', 'approved'], true))
    <span class="tbtn" style="cursor:default;border-color:var(--success);color:var(--success);">
      청구함 {{ $order->nhis_submitted_at?->format('Y-m-d') }}
    </span>
  @else
    <form method="POST" action="{{ route('nhis.assist.markClaimed', $order) }}" style="display:inline;">
      @csrf
      <button type="submit" class="tbtn"
              onclick="return confirm('공단에 제출을 마쳤습니까? 청구 상태를 「청구완료」로 적습니다.');">
        청구 완료로 표시
      </button>
    </form>
  @endif
</div>

{{-- ───────────────────────── 여기서부터 공단 2221 서식 구조 ───────────────────────── --}}
{{--
  공단 서식의 머리말(제목·빨간 안내·버튼 줄)과 우리 안내 배너는 두지 않는다. 오른쪽 공단
  화면에 그대로 있어 두 번 읽을 것이 되고, 정작 옮겨 적을 칸이 아래로 밀린다.
  확인해야 할 것은 칸 옆 ⚠ 에 남아 있다 — 마우스를 올리면 내용이 보인다.
--}}
<div class="stage" id="stage">
<div class="sheet" id="sheet">

  {{-- 수진자 정보 --}}
  <div class="sec"><span class="sec-name">수진자 정보</span></div>
  <table class="form">
    <colgroup><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col></colgroup>
    <tr>
      <th>요양비종류</th><td>{!! $box('kind', 'lg') !!}</td>
      <th>주민번호</th><td>{!! $box('rrn_front', 'sm') !!} <span class="unit">-</span> {!! $box('rrn_back', 'md') !!}</td>
      <th>성명</th><td>{!! $box('name', 'md') !!}</td>
    </tr>
    <tr>
      <th>처리지사</th><td>{!! $box('branch', 'md') !!}</td>
      <th>한시적</th><td>{!! $box('temporary', 'md') !!}</td>
      <th>처리상태</th><td>{!! $box('state', 'md') !!}</td>
    </tr>
  </table>

  {{-- 처방정보 --}}
  <div class="sec">
    <span class="sec-name">자가도뇨 소모성재료 처방정보</span>
    <span class="sec-help">※ 전자처방전에 한하며, 처방전등록번호 입력 후 청구하시기 바랍니다.</span>
    <span class="sec-right">처방전첨부파일조회</span>
  </div>
  <table class="form">
    <colgroup><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col></colgroup>
    <tr>
      <th>처방전등록번호</th><td>{!! $box('rx_reg_no', 'lg') !!} <span class="btnish">검색</span></td>
      <th>처방전발행일</th><td>{!! $box('rx_issued', 'md') !!}</td>
      <th>상병구분</th><td>{!! $box('disease_cls', 'md') !!}</td>
    </tr>
    <tr>
      <th>1일처방개수</th><td>{!! $box('daily_count', 'sm') !!}<span class="unit">개</span></td>
      <th>총처방기간<small>(일수)</small></th><td>{!! $box('total_days', 'sm') !!}<span class="unit">일</span></td>
      <th>총계<small>(처방총계)</small></th><td>{!! $box('rx_total', 'sm') !!}<span class="unit">개</span></td>
    </tr>
    <tr>
      <th>의사면허번호</th><td>{!! $box('license_no', 'md') !!} <span class="btnish">검색</span></td>
      <th>의사명</th><td>{!! $box('doctor_name', 'md') !!}</td>
      <th>요양기관</th><td>{!! $box('hospital', 'md') !!}</td>
    </tr>
    <tr>
      <th>전문의번호</th><td>{!! $box('specialist_no', 'md') !!}</td>
      <th>전문과목<small>(진료과목)</small></th><td>{!! $box('specialty', 'md') !!}</td>
      <th>상병</th><td><span class="btnish">검색</span> {!! $box('disease_code', 'md') !!}</td>
    </tr>
  </table>

  {{-- 구입정보 --}}
  <div class="sec">
    <span class="sec-name">자가도뇨 소모성재료 구입정보</span>
    <span class="sec-help">※ 입력 시 제품등록은 필수사항입니다. 제품등록 후 저장하세요</span>
    <span class="sec-right">제품등록내역등록</span>
  </div>
  <table class="form">
    <colgroup><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col></colgroup>
    <tr>
      <th>구입일</th><td>{!! $box('buy_date', 'md') !!}</td>
      <th>사용개시일</th><td>{!! $box('use_start', 'md') !!}</td>
      <th>1일지급개수<small>(1일사용개수)</small></th><td>{!! $box('daily_pay', 'sm') !!}</td>
      <th>총계<small>(급여총수량)</small></th><td colspan="3">{!! $box('pay_total', 'sm') !!}</td>
    </tr>
    <tr>
      <th>사업자등록번호</th><td>{!! $box('biz_no', 'md') !!} <span class="btnish">검색</span></td>
      <th>업체명</th><td colspan="7">{!! $box('biz_name', 'lg') !!}</td>
    </tr>
    <tr>
      <th>구입금액</th><td>{!! $box('buy_amount', 'sm') !!}</td>
      <th>구입수량</th><td>{!! $box('buy_qty', 'sm') !!}</td>
      <th>급여종료일</th><td>{!! $box('pay_end', 'md') !!}</td>
      <th>실지급일수</th><td>{!! $box('pay_days', 'sm') !!}</td>
      <th>기준금액(일)</th><td>{!! $box('base_daily', 'sm') !!}</td>
    </tr>
    <tr>
      <th>산정기준금액</th><td>{!! $box('base_calc', 'sm') !!}</td>
      <th>본인부담금</th><td>{!! $box('copay', 'sm') !!}</td>
      <th>실본인부담금</th><td>{!! $box('copay_real', 'sm') !!}</td>
      <th>공단부담금</th><td>{!! $box('nhis_pay', 'sm') !!}</td>
      <th>기준금액</th><td>{!! $box('base_amt', 'sm') !!}</td>
    </tr>
  </table>

  <div style="color:#d21414;font-size:11px;font-weight:700;line-height:1.8;margin-top:5px;">
    ※1일사용개수 계산법 : 구입총수량 / 총 처방기간(일수), 소수점 입력(0.0)~소수점 첫째자리까지<br>
    ※급여총수량 계산법 ■ 처방총계&lt;=구입총계 : 처방총계로 입력 ■ 처방총계 &gt; 구입총계 : 구입총계로 입력
  </div>

  {{-- 국세청자료 --}}
  <div class="sec">
    <span class="sec-name">국세정자료</span>
    <span class="sec-right">행추가 &nbsp; 행삭제</span>
  </div>
  <table class="tax">
    <colgroup><col style="width:32px"><col style="width:70px"><col style="width:130px"><col style="width:130px"><col><col style="width:150px"><col style="width:130px"></colgroup>
    <thead>
      <tr><th></th><th>순번</th><th>문서종류</th><th>작성일자</th><th>승인번호</th><th>합계금액</th><th>검증결과</th></tr>
    </thead>
    <tbody>
      @forelse($taxRows as $i => $t)
        <tr>
          <td></td>
          <td>{{ $i + 1 }}</td>
          <td><span class="fld w-full" data-key="tax-{{ $i }}-kind" data-copy="1" onclick="copyBox(this)"><span class="fld-v" data-val>{{ $t['kind'] }}</span></span></td>
          <td><span class="fld w-full" data-key="tax-{{ $i }}-date" data-copy="{{ $t['date'] ? 1 : 0 }}" onclick="copyBox(this)"><span class="fld-v" data-val>{{ $t['date'] ?? '없음' }}</span></span></td>
          <td class="l"><span class="fld w-full" data-key="tax-{{ $i }}-no" data-copy="1" onclick="copyBox(this)"><span class="fld-v" data-val>{{ $t['no'] }}</span></span></td>
          <td><span class="fld w-full" data-key="tax-{{ $i }}-amt" data-copy="{{ $t['amount'] ? 1 : 0 }}" onclick="copyBox(this)"><span class="fld-v" data-val>{{ $t['amount'] ?? '없음' }}</span></span></td>
          <td></td>
        </tr>
      @empty
        <tr><td colspan="7" class="tax-empty">발행된 세금계산서·현금영수증이 없습니다. 청구 전에 발행하십시오.</td></tr>
      @endforelse
      <tr>
        <th colspan="2">등록건수:</th><td>{{ count($taxRows) }}</td>
        <th>총합계:</th><td colspan="3" class="l">{{ number_format(collect($taxRows)->sum(fn ($t) => (int) $t['amount'])) }}</td>
      </tr>
    </tbody>
  </table>
  <div class="red-r" style="margin-top:3px;">* 임시저장 자료는 청구자료가 임시저장/청구 시 저장됩니다.</div>

  {{-- 계좌 정보 --}}
  <div class="sec">
    <span class="sec-name">계좌 정보</span>
    <span class="sec-help">※ 수령인이 판매업자인 경우 예금주관계는 '기타'로 선택바랍니다.</span>
    <span class="sec-right">제출 서류 첨부</span>
  </div>
  <table class="form">
    <colgroup><col style="width:130px"><col><col style="width:130px"><col><col style="width:130px"><col></colgroup>
    <tr>
      <th>수령인</th><td>{!! $box('acc_receiver', 'md') !!}</td>
      <th>금융기관</th><td>{!! $box('acc_bank', 'md') !!}</td>
      <th>계좌번호</th><td>{!! $box('acc_no', 'md') !!}</td>
    </tr>
    <tr>
      <th>예금주관계</th><td>{!! $box('acc_relation', 'md') !!}</td>
      <th>예금주 주민/<br>사업자 번호</th><td>{!! $box('acc_biz_no', 'md') !!}</td>
      <th>예금주명</th><td>{!! $box('acc_holder', 'md') !!}</td>
    </tr>
    <tr>
      <th>압류방지통장</th><td colspan="5">{!! $box('acc_protect', 'md') !!}</td>
    </tr>
    <tr>
      <th>청구인관계</th><td>{!! $box('clm_relation', 'md') !!}</td>
      <th>청구인 주민/<br>사업자번호</th><td>{!! $box('clm_biz_no', 'md') !!}</td>
      <th>청구인명</th><td>{!! $box('clm_name', 'md') !!}</td>
    </tr>
    <tr>
      <th>SMS수신동의</th><td>{!! $box('sms_agree', 'sm') !!}</td>
      <th>SMS송신번호<small>(연락처)</small></th>
      <td>{!! $box('sms_no1', 'sm') !!} <span class="unit">-</span> {!! $box('sms_no2', 'sm') !!} <span class="unit">-</span> {!! $box('sms_no3', 'sm') !!}</td>
      <th>카드승인번호</th><td>{!! $box('card_no', 'md') !!}</td>
    </tr>
  </table>

  {{-- 제출서류 등록 --}}
  <div class="sec">
    <span class="sec-name" style="color:#d21414;">제출서류 등록</span>
    <span class="sec-right red">※ 제출서류를 반드시 등록하시기 바랍니다.
      {{-- 공단은 사이트에 올리므로 인쇄할 일이 드물다. 다만 「간혹 프린트 필요」라
           하셨고(요청서 10쪽), 그때 하나씩 눌러 받게 두면 빠뜨린다. --}}
      <a href="{{ route('nhis.assist.bundle', $order) }}" target="_blank" rel="noopener"
         style="margin-left:8px;color:#1a5fb4;font-weight:600;text-decoration:underline;">
        한 묶음으로 인쇄
      </a>
    </span>
  </div>
  <table class="docs">
    <colgroup><col style="width:150px"><col><col style="width:240px"></colgroup>
    <thead><tr><th>급여종류</th><th>준요양기관/업체</th><th>우리 보유</th></tr></thead>
    <tbody>
      <tr>
        <td style="text-align:center;vertical-align:middle;">자가도뇨<br>소모성재료</td>
        <td>
          @foreach($documents as $i => $d)
            <div style="padding:2px 0;">{{ $i + 1 }}) {{ $d['name'] }}
              @if($d['note'])<span style="color:#888;">— {{ $d['note'] }}</span>@endif
            </div>
          @endforeach
        </td>
        <td>
          @foreach($documents as $d)
            <div style="padding:2px 0;">
              @if($d['url'])
                <span class="have">보유</span>
                <a class="dl" href="{{ $d['url'] }}" target="_blank" rel="noopener">내려받기</a>
              @else
                <span class="havent">미보유</span>
              @endif
            </div>
          @endforeach
        </td>
      </tr>
    </tbody>
  </table>

</div>
</div>
@endsection

@push('scripts')
<script>
const CSRF   = document.querySelector('meta[name=csrf-token]').content;
const REVEAL = @js($revealUrl);
const PORTAL = @js($portalUrl);
/* 복사 기록은 브라우저에만 둔다. 한 번 청구하는 동안만 쓰는 값이라 서버에 남길 값어치가 없다. */
const STORE  = @js($storeKey);
let   copied = new Set(JSON.parse(sessionStorage.getItem(STORE) || '[]'));

function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('on');
  clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('on'), 1500);
}

/* HTTPS 가 아니거나 브라우저가 막으면 클립보드 API 가 없다. 값을 못 옮기면 화면이 무의미하므로
   보이지 않는 textarea 로 대신한다. */
async function toClipboard(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text); return true;
    }
  } catch (_) {}
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;top:-1000px;opacity:0;';
  document.body.appendChild(ta); ta.select();
  const ok = document.execCommand('copy');
  ta.remove();
  return ok;
}

async function copyBox(el) {
  if (el.dataset.copy !== '1') {
    toast(el.title || '값이 없습니다.');
    return;
  }

  let val = el.querySelector('[data-val]').textContent.trim();

  // 주민번호 뒷자리는 미리 내려보내지 않는다. 누르는 이 순간에만 서버에서 열고 기록을 남긴다.
  if (el.dataset.reveal === '1' && !el.dataset.revealed) {
    let data;
    try {
      const res = await fetch(REVEAL, { method:'POST', headers:{ 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' } });
      data = await res.json();
    } catch (_) {
      // 로그인이 풀렸거나 통신이 끊기면 HTML 이 돌아온다. 값 없이 넘어가면 안 되므로 알린다.
      toast('주민등록번호를 열지 못했습니다 — 로그인이 유지되고 있는지 확인하십시오.');
      return;
    }
    if (!data.ok) { toast(data.message || '주민등록번호를 열 수 없습니다.'); return; }
    el.querySelector('[data-val]').textContent = data.back;
    el.dataset.revealed = '1';
    val = data.back;
  }

  if (!await toClipboard(val)) { toast('복사하지 못했습니다.'); return; }

  el.classList.add('done');
  copied.add(el.dataset.key);
  sessionStorage.setItem(STORE, JSON.stringify([...copied]));
  progress();
  toast('복사했습니다 — 공단 화면 같은 자리에 붙여넣으십시오');
}

function progress() {
  const all  = document.querySelectorAll('.fld[data-copy="1"]');
  const done = document.querySelectorAll('.fld[data-copy="1"].done');
  document.getElementById('ptext').textContent = `${done.length} / ${all.length}`;
  document.getElementById('pfill').style.width = all.length ? (done.length / all.length * 100) + '%' : '0';
}

function resetAll() {
  copied.clear();
  sessionStorage.removeItem(STORE);
  document.querySelectorAll('.fld.done').forEach(e => e.classList.remove('done'));
  progress();
  toast('복사 기록을 지웠습니다');
}

/* 좌우 프레임으로 전환. 공단 사이트가 프레임을 막지 않아 한 창에 나란히 놓을 수 있다.
   값을 대신 넣어 주는 것은 아니고, 복사와 붙여넣기를 한 창에서 하게 하는 것이다. */
function openSplit() {
  location.href = @js($splitUrl);
}

// 이미 프레임 안이면 그 버튼은 쓸모가 없다
if (window.self !== window.top) {
  const b = document.getElementById('splitBtn');
  if (b) { b.remove(); }
}

/* 프레임 안에서 공단 로그인이 풀릴 때의 퇴로 — 창을 따로 세워 준다.
   창 위치를 옮기는 것은 브라우저가 막을 수 있으므로 실패해도 창은 열리게 둔다. */
function openPortal() {
  const w = screen.availWidth, h = screen.availHeight, half = Math.floor(w / 2);
  const win = window.open(PORTAL, 'nhis_portal', `width=${half},height=${h},left=${half},top=0,scrollbars=yes,resizable=yes`);
  if (!win) { toast('팝업이 막혔습니다 — 이 사이트의 팝업을 허용해 주십시오'); return; }
  try {
    window.moveTo(0, 0);
    window.resizeTo(half, h);
    win.moveTo(half, 0);
    win.resizeTo(half, h);
  } catch (_) {}
  win.focus();
}

/* 폭 맞춤 — 서식을 통째로 줄여 창 폭에 넣는다. 칸의 자리·비율은 그대로다.
   창을 반으로 쓸 때가 기본이라 켜 두고, 잔글씨가 작으면 꺼서 원래 크기로 볼 수 있다. */
let fit = sessionStorage.getItem(STORE + ':fit') !== '0';

function applyFit() {
  const sheet = document.getElementById('sheet');
  const stage = document.getElementById('stage');
  // 세로 스크롤바가 차지하는 만큼을 빼야 오른쪽이 잘리지 않는다.
  // 도구 막대가 창보다 넓으면 stage 도 같이 넓어지므로 실제 보이는 폭으로 잰다.
  sheet.style.transform = 'none';
  const natural = Math.max(sheet.scrollWidth, sheet.offsetWidth);
  const avail   = Math.min(stage.clientWidth, document.documentElement.clientWidth) - 2;
  // 더 줄이면 글자를 못 읽는다. 하한 아래로는 줄이지 말고 가로로 밀어 보게 둔다.
  const MIN     = 0.62;
  const scale   = fit ? Math.max(MIN, Math.min(1, avail / natural)) : 1;

  sheet.style.transform = `scale(${scale})`;
  // 줄인 만큼 자리도 줄어야 아래쪽에 빈 공간이 남지 않는다
  stage.style.height = (sheet.offsetHeight * scale) + 'px';
  // 하한에 걸려 아직 넘치면 가로로 밀어 볼 수 있어야 한다
  stage.style.overflowX = (natural * scale > avail) ? 'auto' : 'hidden';

  document.getElementById('fitBtn').classList.toggle('on', fit);
  document.getElementById('fitPct').textContent = fit ? Math.round(scale * 100) + '%' : '';
}

function toggleFit() {
  fit = !fit;
  sessionStorage.setItem(STORE + ':fit', fit ? '1' : '0');
  applyFit();
}

window.addEventListener('resize', applyFit);

// 창을 벗어났다 돌아와도 어디까지 했는지 남아 있어야 한다
document.querySelectorAll('.fld').forEach(el => {
  if (copied.has(el.dataset.key)) { el.classList.add('done'); }
});
progress();
applyFit();
</script>
@endpush
