@extends('layouts.app')

@section('title', '재구매 관리')
@section('page-title', '재구매 관리')
{{-- 시안(243:53 · 243:433) Frame 48101452 — 「홈 - 재구매 관리」 두 마디.
     홈 x336(w11) · 구분자 '-' x355(w6) · 화면명 x369(w55), 12/500 · 마디 사이 8.
     마디로 세우는 일은 이제 레이아웃이 한다 — 여기서는 낱말만 적는다. --}}
@section('breadcrumb', '홈 - 재구매 관리')

@push('styles')
<style>
/* ═══════════════════════════════════════════════════════════
   재구매 관리 — Figma 243:433(테이블뷰) · 243:53(캘린더뷰)
   전역 컴포넌트(.ds-btn h32·r8·13px/500, 카드 r12, .ds-filter-card)를
   그대로 쓰고, 이 화면에만 있는 캘린더·날짜 패널 규격만 여기 둔다.
   옛 값(12.5px 글자, 1.5px 테두리, 그림자, 주황 today 배경)은 걷어냈다.
═══════════════════════════════════════════════════════════ */

/* ── 상단 컨트롤 줄 (243:433 Frame 48101583 — h32 · gap 10) ── */
.rp-topbar      { display:flex; align-items:center; justify-content:space-between; gap:10px 12px; flex-wrap:wrap; min-height:32px; }
.rp-topbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
/* 시안 상단바 버튼(icon-header-setting 61×32 · 47×32 · 91×32)은 전부 bg #FFFFFF · r8 이고
   테두리(bd) 항목이 없다. 전역 .ds-btn 의 bd 1px gray-200 · min-width 60 을 이 영역에서만 걷어낸다.
   결과바(.ds-grid-bar)·검색 카드에는 닿지 않는다. */
.rp-topbar .ds-btn { border-color:transparent; min-width:0; }
/* hover 배경은 전역 .ds-btn:hover(gray-50) · .ds-btn-primary:hover(primary-light) 를 그대로 쓴다.
   여기서 .rp-topbar .ds-btn:hover 로 덮으면 '오늘'(ds-btn-primary) 의 primary hover 까지 회색이 된다. */

/* ── 월 네비 — 시안 311×32, 버튼 사이 gap 8 ── */
.month-nav { display:flex; align-items:center; gap:8px; }
/* 시안 '이전 월'·'다음 월' 은 61×32 로 텍스트(37×21) 하나뿐 — 화살표 아이콘이 없다.
   아이콘은 보존 규칙상 지우지 않고 gap 8→4 · 12px→11px 로 줄여 79→약 74 로 좁힌다. */
.month-nav .ds-btn   { gap:4px; }
.month-nav .ds-btn i { font-size:11px; }
/* 월 라벨 — 시안 118×26 · pad 0/4 · gap 8 (글자묶음 86 + chevron 16×16) · 16px/700 · lh26 */
.month-nav .month-label {
  display:inline-flex; align-items:center; gap:8px;
  height:26px; padding:0 4px; border-radius:8px;
  font-size:16px; font-weight:700; line-height:26px; color:var(--gray-1000);
  cursor:pointer; user-select:none; white-space:nowrap;
  transition:var(--transition);
}
.month-nav .month-label:hover { background:var(--primary-light); color:var(--primary); }
/* 시안 글자묶음 86×26 = "2026년"(55) + gap 6 + "8월"(25) */
.month-nav .month-label-text { display:inline-flex; align-items:center; gap:6px; }
/* 시안 chevron 자리 16×16 */
.month-nav .month-label i {
  display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
  width:16px; height:16px; font-size:12px;
}
/* 월 네비와 건수 사이 구분점 — 시안 4×4 정원 */
.rp-sep { width:4px; height:4px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
/* 이달 건수 — 시안 45×21 · 13px/500, 숫자만 primary-400 */
.rp-month-count        { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-600); white-space:nowrap; }
.rp-month-count strong { font-weight:500; color:var(--primary-400); }

/* ── 뷰 전환 — 시안 91×32 버튼 두 개 · gap 8 (pad 0/12 · 아이콘 14×14 · 라벨 45×21) ── */
.view-tabs  { display:flex; align-items:center; gap:8px; }
/* 시안 아이콘 칸(labour-day · layout-01)은 둘 다 14×14 인데 글리프 폭은 12.25/14 로 제각각이라
   칸을 14 로 못박아 글자 시작을 시안(x1748 · x1847)에 맞춘다. */
.view-tab i { font-size:14px; display:inline-block; width:14px; text-align:center; }
/* 시안은 두 탭이 똑같이 91×32 (묶음 190×32) 다 — pad 12 + 아이콘 14 + gap 8 + 글자 45 + pad 12 = 91.
   시안 프레임에는 stroke 가 없으므로 전역 .ds-btn 의 1px 테두리도 폭에서 뺀다
   (위 .rp-topbar 규칙이 색만 투명으로 만들어 2px 이 남아 있었다).
   위 .rp-topbar .ds-btn{min-width:0} 을 이기려면 같은 (0,2,0) 이어야 한다. */
.rp-topbar .view-tab { min-width:91px; border-width:0; }
/* 시안(243:53 · 243:433)에는 활성 뷰 구분이 없다 — 테두리는 시안대로 지우고(위 .rp-topbar 규칙),
   어느 뷰인지 알 수 없어지지 않도록 배경·글자색만 남긴다. 디자이너 확인 대상. */
.view-tab.active,
.view-tab.active:hover { background:var(--primary-light); color:var(--primary); }

/* ── 년월 피커 팝오버 (시안에 없는 화면 전용 UI — DS 토큰으로만 정리) ── */
#ymPicker {
  display:none; position:absolute; z-index:200;
  background:var(--gray-0); border:1px solid var(--gray-200);
  border-radius:12px; box-shadow:var(--shadow-lg);
  padding:12px; width:260px;
}
.ym-year-row   { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.ym-year-label {
  font-size:13px; font-weight:700; line-height:21px; color:var(--gray-1000);
  cursor:pointer; padding:0 8px; border-radius:8px; transition:var(--transition);
}
.ym-year-label:hover { background:var(--primary-light); color:var(--primary); }
.ym-year-btn {
  width:28px; height:28px; border-radius:8px;
  border:1px solid var(--gray-200); background:var(--gray-0);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:12px; color:var(--gray-800);
  transition:var(--transition);
}
.ym-year-btn:hover { background:var(--primary-light); color:var(--primary); border-color:var(--primary); }
.ym-months { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
.ym-month {
  height:32px; display:flex; align-items:center; justify-content:center;
  border-radius:8px; font-size:13px; font-weight:500; line-height:21px;
  color:var(--gray-800); border:1px solid transparent;
  cursor:pointer; transition:var(--transition);
}
.ym-month:hover  { background:var(--primary-light); color:var(--primary); border-color:var(--primary-accent); }
.ym-month.active { background:var(--primary); color:var(--gray-0); border-color:var(--primary); }
.ym-month.today  { border-color:var(--primary-accent); color:var(--primary); }
#ymOverlay { display:none; position:fixed; inset:0; z-index:199; }

/* ── 캘린더 + 날짜 패널 (시안 243:53 — 좌 1169 / 우 390 · gap 12) ── */
.rp-cal-layout { display:flex; align-items:stretch; gap:12px; }
.rp-cal-card   { min-width:0; }

/* 캘린더 — 요일 헤더 h44, 날짜 칸 min 144 (시안 167×144 · pad 8 · gap 8) */
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); }
.cal-header-cell {
  display:flex; align-items:center; height:44px; padding:0 8px;
  background:var(--gray-50); color:var(--gray-1000);
  font-size:13px; font-weight:500; line-height:21px;
  border-right:1px solid var(--gray-100); border-bottom:1px solid var(--gray-200);
}
.cal-header-cell:first-child   { color:var(--alert-500); }   /* 일요일 */
.cal-header-cell:nth-child(7)  { border-right:none; }

.cal-cell {
  display:flex; flex-direction:column; gap:8px;
  min-height:144px; padding:8px;
  background:var(--gray-0); cursor:pointer;
  border-right:1px solid var(--gray-100); border-bottom:1px solid var(--gray-100);
  transition:background .12s;
}
.cal-cell:nth-child(7n) { border-right:none; }
.cal-cell:hover:not(.cal-empty) { background:var(--gray-50); }
.cal-cell.cal-empty     { background:var(--gray-200); cursor:default; }  /* 시안: 지난달·다음달 칸 */
.cal-cell.cal-selected  { background:var(--primary-light); }
.cal-day { font-size:13px; font-weight:400; line-height:21px; color:var(--gray-1000); }
.cal-grid > .cal-cell:nth-child(7n+1) .cal-day { color:var(--alert-500); }   /* 일요일 */
/* 지난달·다음달 칸도 시안은 날짜 숫자를 13px/400 lh21 gray-400 으로 보여준다 (일요일 빨강보다 뒤) */
.cal-grid > .cal-cell.cal-empty .cal-day { color:var(--gray-400); }
/* 오늘 — 시안은 배경 대신 날짜 옆 'Today' 알약(r999 · pad 0/6 · 11px/700) */
.cal-cell.cal-today .cal-day::after {
  content:'Today'; display:inline-flex; align-items:center;
  margin-left:6px; height:18px; padding:0 6px; border-radius:999px;
  background:var(--primary); color:var(--gray-0);
  font-size:11px; font-weight:700; line-height:18px;
}
/* 건수 알약 — 시안 38×25 · r6 · pad 2/8 · gap 2 · bd 1px gray-200, 숫자 13/700 primary.
   시안 25 는 테두리 포함 높이라 pad 2 를 1 로 낮춰 1+21+1+2 = 25 로 맞춘다. */
.cal-count-pill {
  display:inline-flex; align-items:center; gap:2px; align-self:flex-start;
  height:25px; box-sizing:border-box;
  padding:1px 8px; border-radius:6px;
  background:var(--gray-0); border:1px solid var(--gray-200);
}
.cal-count       { font-size:13px; font-weight:700; line-height:21px; color:var(--primary); }
.cal-count-label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-1000); }

/* ── 날짜 상세 패널 (시안 390×905 · r12 — 캘린더 오른쪽) ── */
#dayPanel {
  display:none; width:390px; flex-shrink:0; max-width:100%;
  background:var(--gray-0); border-radius:12px; overflow:hidden;
}
/* 헤더 — 시안 h44 · pad 8/16 · 하단 1px gray-200 (옛 primary 배경 제거) */
.day-panel-header {
  display:flex; align-items:center; justify-content:space-between; gap:8px;
  height:44px; padding:8px 16px; border-bottom:1px solid var(--gray-200);
}
.day-panel-title { font-size:13px; font-weight:700; line-height:21px; color:var(--gray-1000);
  min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* 닫기 — 시안 45×28 · r8 · pad 0/12 · 12px/500 (작은 버튼 규격) */
.day-panel-close {
  display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
  height:28px; padding:0 12px; border-radius:8px;
  background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000);
  font-size:12px; font-weight:500; line-height:19px;
  cursor:pointer; transition:var(--transition); white-space:nowrap;
}
.day-panel-close:hover { background:var(--gray-50); }
/* 본문 — 시안 pad 16 · 행 간격 8 */
#dayPanelBody { display:flex; flex-direction:column; gap:8px; padding:16px; }

/* ── 날짜 패널 목록 행 — 시안 358×53 · r8 · pad 8/12 · gap 8 · bd 1px gray-200 ──
   시안은 가로 3단이다: 아바타 28×28 / 세로 스택 237×37 / 상태 배지 53×22.
   세로 스택은 1줄 19(이름 · RX) + 2줄 18(병원 · 2×2 점 · 일시), 줄 사이 간격 0. */
.rx-row {
  display:flex; align-items:center; gap:8px;
  /* 시안 53 은 테두리 포함 높이다 — pad 8 을 7 로 낮춰 1+7+37+7+1 = 53 으로 맞춘다 */
  padding:7px 12px; border:1px solid var(--gray-200); border-radius:8px;
  background:var(--gray-0); color:var(--gray-1000); text-decoration:none;
  transition:background .1s;
}
.rx-row:hover      { background:var(--gray-50); }
/* 아바타 — 시안 28×28 · r6 · bg gray-100 · 안쪽 24×24 환자 아이콘 */
.rx-row-avatar {
  display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
  width:28px; height:28px; border-radius:6px;
  background:var(--gray-100); color:var(--gray-500); font-size:14px;
}
.rx-row-main       { display:flex; flex-direction:column; flex:1; min-width:0; }
.rx-row-line1      { display:flex; align-items:center; gap:4px; min-width:0; overflow:hidden; }
.rx-row-line2      { display:flex; align-items:center; gap:4px; min-width:0; }
.rx-row-num        { font-size:12px; font-weight:500; line-height:19px; color:var(--primary); flex-shrink:0; }
/* 이름이 길면(OCR 값이라 길이가 들쭉날쭉하다) 스스로 줄인다 — 병원명과 같은 처리.
   flex-shrink:0 이면 RX 번호와 상태 배지 위로 글자가 겹쳐 나온다. */
.rx-row-patient    { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000);
  min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.rx-row-hospital   { font-size:11px; font-weight:500; line-height:18px; color:var(--gray-500);
  min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* 병원과 일시 사이 구분점 — 시안 Rectangle 2×2 · r999 · gray-300 */
.rx-row-dot        { width:2px; height:2px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
/* 일시 — 시안 11px/500 · lh18 · gray-500 (옛 11px/400 gray-400 에서 교정) */
.rx-row-date       { font-size:11px; font-weight:500; line-height:18px; color:var(--gray-500);
  flex-shrink:0; white-space:nowrap; }
.rx-row-badge      { flex-shrink:0; }
/* 시안에 없는 이동 화살표 — 보존 규칙상 남기고 배지 오른쪽 끝으로 뺀다 */
.rx-row-go         { flex-shrink:0; font-size:11px; color:var(--gray-400); }

/* 결과바 '전체 N건' — 시안 16px/700. 부트스트랩 b{font-weight:bolder} 가 700 을 900 으로 올린다.
   전역 .ds-grid-total b 는 색만 잡고 있어 이 화면에서만 굵기를 되돌린다(전역 보고 대상). */
.ds-grid-total b { font-weight:700; }

@media (max-width: 1279px) {
  .rp-cal-layout { flex-direction:column; }
  #dayPanel      { width:100%; }
  .cal-cell      { min-height:110px; }
}
</style>
@endpush

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#ymTrigger', title: '월 선택', body: '조회할 연월을 선택합니다. 클릭하면 년/월 선택 팝업이 열립니다.' },
  { selector: '#calGrid', title: '재구매 캘린더', body: '각 날짜 칸에 재구매 가능 환자 수가 표시됩니다. 숫자가 있는 날짜를 클릭하면 해당일 대상자 목록이 캘린더 오른쪽에 펼쳐집니다.' },
  { selector: '.cal-cell:not(.cal-empty)', title: '날짜 셀 클릭', body: '숫자가 표시된 날짜를 클릭하면 재구매 대상 환자 목록이 나타납니다. 목록에서 카카오 알림톡 또는 SMS를 바로 발송할 수 있습니다.' },
];
</script>
<script>
(function () {
  const el = document.getElementById('repurchaseGrid');
  if (!el) return;   // 목록 뷰에서만 존재(캘린더 뷰엔 없음)
  const RX_BASE = @json(url('prescriptions'));   // + '/{rx_number}'
  const grid = new wwGrid({
    el: el,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — 전체 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: false,
    columns: [
      { header: '재구매 가능일', name: 'repurchase', width: 130, sortable: true },
      { header: '처방전 번호',   name: 'rx_number',  width: 140, sortable: true },
      { header: '이름',        name: 'patient',    width: 110, sortable: true },
      { header: '병원',          name: 'hospital',   width: 200 },
      { header: '상태',          name: 'status',     width: 100, align: 'center', sortable: true },
      { header: '등록일',        name: 'created',    width: 120, align: 'center', sortable: true },
    ],
    data: @json($listGrid ?? []),
  });
  window.__repurchaseGrid = grid;   // 결과바의 '엑셀 저장' 버튼이 이걸 부른다
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row && row.rx_number) window.location.href = RX_BASE + '/' + encodeURIComponent(row.rx_number);
  });
})();
</script>
@endpush

@section('content')

{{-- ── 상단 컨트롤 — Figma 243:433 Frame 48101583(h32): 좌 월 네비 + 이달 건수 / 우 뷰 전환 ── --}}
<div class="rp-topbar">
  <div class="rp-topbar-left">
    {{-- 월 네비 --}}
    <div class="month-nav">
      @php
        $prevYear  = $month === 1 ? $year - 1 : $year;
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $nextYear  = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
      @endphp
      <a class="ds-btn" href="{{ route('repurchase.index', ['year'=>$prevYear,'month'=>$prevMonth,'view'=>$view]) }}">
        <i class="fa-solid fa-chevron-left"></i> 이전 월
      </a>
      <div style="position:relative;">
        <span class="month-label" id="ymTrigger" onclick="toggleYmPicker(this)">
          <span class="month-label-text"><span>{{ $year }}년</span><span>{{ $month }}월</span></span>
          <i class="fa-solid fa-chevron-down"></i>
        </span>
        {{-- 년월 피커 --}}
        <div id="ymPicker">
          <div class="ym-year-row">
            <button class="ym-year-btn" onclick="changePickerYear(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="ym-year-label" id="ymYearLabel"></span>
            <button class="ym-year-btn" onclick="changePickerYear(1)"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
          <div class="ym-months" id="ymMonths"></div>
        </div>
      </div>
      <div id="ymOverlay" onclick="closeYmPicker()"></div>
      <a class="ds-btn" href="{{ route('repurchase.index', ['year'=>$nextYear,'month'=>$nextMonth,'view'=>$view]) }}">
        다음 월 <i class="fa-solid fa-chevron-right"></i>
      </a>
      @if($year !== now()->year || $month !== now()->month)
        <a class="ds-btn ds-btn-primary" href="{{ route('repurchase.index') }}">오늘</a>
      @endif
    </div>
    <span class="rp-sep"></span>
    <span class="rp-month-count">이달 <strong>{{ $totalCount }}</strong>건</span>
  </div>

  {{-- 뷰 전환 --}}
  <div class="view-tabs">
    <a class="ds-btn view-tab {{ $view === 'calendar' ? 'active' : '' }}"
       href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'calendar']) }}">
      {{-- 시안(243:53 · 243:433) 라벨 그대로 '캘린더뷰' — 45×21 · 13/500 · 글자 x1748 --}}
      <i class="fa-regular fa-calendar"></i> 캘린더뷰
    </a>
    <a class="ds-btn view-tab {{ $view === 'list' ? 'active' : '' }}"
       href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'list']) }}">
      {{-- 시안(243:53 · 243:433) 라벨 그대로 '테이블뷰' — 45×21 · 13/500 · 글자 x1847 --}}
      <i class="fa-solid fa-list"></i> 테이블뷰
    </a>
  </div>
</div>

{{-- ══════════════════════════════════ CALENDAR VIEW ══════════════════════════════════ --}}
@if($view === 'calendar')
{{-- 시안 243:53 — 캘린더 카드(좌) 와 날짜 상세 패널(우) 이 나란히, gap 12 --}}
<div class="rp-cal-layout">
  <div class="ds-grid-card rp-cal-card">
    {{-- 요일 헤더 --}}
    <div class="cal-grid" id="calGrid">
      @foreach(['일','월','화','수','목','금','토'] as $dow)
        <div class="cal-header-cell">{{ $dow }}</div>
      @endforeach

      @php
        $firstDow  = (int)$startOfMonth->dayOfWeek; // 0=Sun
        $daysInMonth = (int)$endOfMonth->day;
        $today = now()->toDateString();
      @endphp

      {{-- 앞 빈칸 — 시안은 지난달 날짜(26·27…31)를 gray-400 으로 보여준다 --}}
      @php $prevMonthLastDay = (int) $startOfMonth->copy()->subDay()->day; @endphp
      @for($i = 0; $i < $firstDow; $i++)
        <div class="cal-cell cal-empty"><div class="cal-day">{{ $prevMonthLastDay - $firstDow + 1 + $i }}</div></div>
      @endfor

      {{-- 날짜 셀 --}}
      @for($d = 1; $d <= $daysInMonth; $d++)
        @php
          $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $cnt     = $countsByDate[$dateStr] ?? 0;
          $isToday = $dateStr === $today;
        @endphp
        <div class="cal-cell {{ $isToday ? 'cal-today' : '' }}"
             data-date="{{ $dateStr }}"
             onclick="{{ $cnt > 0 ? 'loadDay(this)' : '' }}">
          <div class="cal-day">{{ $d }}</div>
          @if($cnt > 0)
            <div class="cal-count-pill"><span class="cal-count">{{ $cnt }}</span><span class="cal-count-label">건</span></div>
          @endif
        </div>
      @endfor

      {{-- 뒤 빈칸 — 시안은 다음달 날짜(1·2…)를 gray-400 으로 보여준다 --}}
      @php
        $lastDow  = (int)$endOfMonth->dayOfWeek;
        $trailing = $lastDow === 6 ? 0 : 6 - $lastDow;
      @endphp
      @for($i = 0; $i < $trailing; $i++)
        <div class="cal-cell cal-empty"><div class="cal-day">{{ $i + 1 }}</div></div>
      @endfor
    </div>
  </div>

  {{-- 날짜 클릭 시 상세 패널 --}}
  <div id="dayPanel">
    <div class="day-panel-header">
      <span class="day-panel-title" id="dayPanelTitle"></span>
      <button class="day-panel-close" onclick="closeDay()">닫기</button>
    </div>
    <div id="dayPanelBody">
      <div style="padding:24px;text-align:center;color:var(--text-muted);">
        <i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중…
      </div>
    </div>
  </div>
</div>

<script>
let _selectedCell = null;

function loadDay(cell) {
  const date = cell.dataset.date;

  // 선택 표시
  if (_selectedCell) _selectedCell.classList.remove('cal-selected');
  cell.classList.add('cal-selected');
  _selectedCell = cell;

  // 패널 제목
  const [y, m, d] = date.split('-');
  document.getElementById('dayPanelTitle').textContent =
    `${y}년 ${parseInt(m)}월 ${parseInt(d)}일 재구매 가능`;

  const panel = document.getElementById('dayPanel');
  const body  = document.getElementById('dayPanelBody');
  panel.style.display = 'block';
  body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중…</div>';

  // 스크롤
  setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);

  fetch(`{{ route('repurchase.day') }}?date=${date}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(({ data }) => {
    if (!data.length) {
      body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);">해당 날짜의 재구매 가능 건이 없습니다.</div>';
      return;
    }

    // 상태색은 전역 배지 규칙(layouts/app.blade.php `.bg-label-*`)을 그대로 쓴다.
    // 시안에 초록·주황·남색 상태색이 0건이므로 정상 진행은 primary 연톤,
    // 주의(검수 필요·반려)만 alert 연톤, 대기·처리중은 중립 회색으로 그린다.
    // 상태 구분은 배지 안 라벨 문구(status_label)가 계속 담당한다.
    const statusBadge = {
      pending:'bg-label-secondary', ocr_processing:'bg-label-secondary', ocr_done:'bg-label-primary',
      review_requested:'bg-label-warning',
      review_needed:'bg-label-danger', approved:'bg-label-primary', rejected:'bg-label-danger', ordered:'bg-label-primary'
    };

    // 시안 358×53 3단 구조: 아바타 28×28 / 세로 스택(이름·RX / 병원·점·일시) / 상태 배지 53×22.
    // 파일 아이콘과 이동 화살표는 시안에 없지만 지우지 않고 자리만 옮긴다.
    body.innerHTML = data.map(item => `
      <a class="rx-row" href="${item.url}">
        <span class="rx-row-avatar"><i class="fa-solid fa-user"></i></span>
        <span class="rx-row-main">
          <span class="rx-row-line1">
            <span class="rx-row-patient">${item.patient_name}</span>
            <span class="rx-row-num"><i class="fa-solid fa-file-medical" style="color:var(--primary);margin-right:4px;"></i>${item.rx_number}</span>
          </span>
          <span class="rx-row-line2">
            <span class="rx-row-hospital">${item.hospital}</span>
            <span class="rx-row-dot"></span>
            <span class="rx-row-date">${item.created_at}</span>
          </span>
        </span>
        <span class="rx-row-badge">
          <span class="badge ${statusBadge[item.status] ?? 'bg-label-secondary'}">
            ${item.status_label}
          </span>
        </span>
        <i class="fa-solid fa-chevron-right rx-row-go"></i>
      </a>
    `).join('');
  })
  .catch(() => {
    body.innerHTML = '<div style="padding:16px;color:var(--danger);">불러오기 실패. 다시 시도해주세요.</div>';
  });
}

function closeDay() {
  document.getElementById('dayPanel').style.display = 'none';
  if (_selectedCell) { _selectedCell.classList.remove('cal-selected'); _selectedCell = null; }
}
</script>
@endif

<script>
// ── 년월 피커 ──────────────────────────────────────────
const YM = {
  curYear:  {{ $year }},
  curMonth: {{ $month }},
  pickerYear: {{ $year }},
  view: '{{ $view }}',
  baseUrl: '{{ route('repurchase.index') }}',
  todayYear:  {{ now()->year }},
  todayMonth: {{ now()->month }},
};

function toggleYmPicker(trigger) {
  const picker  = document.getElementById('ymPicker');
  const overlay = document.getElementById('ymOverlay');
  if (picker.style.display === 'block') {
    closeYmPicker(); return;
  }
  YM.pickerYear = YM.curYear;
  renderYmPicker();
  picker.style.display  = 'block';
  overlay.style.display = 'block';
  // 트리거 아래 위치
  const rect = trigger.getBoundingClientRect();
  picker.style.top  = (trigger.offsetHeight + 4) + 'px';
  picker.style.left = '0px';
}

function closeYmPicker() {
  document.getElementById('ymPicker').style.display  = 'none';
  document.getElementById('ymOverlay').style.display = 'none';
}

function changePickerYear(delta) {
  YM.pickerYear += delta;
  renderYmPicker();
}

function renderYmPicker() {
  document.getElementById('ymYearLabel').textContent = YM.pickerYear + '년';
  const grid = document.getElementById('ymMonths');
  const months = ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'];
  grid.innerHTML = months.map((label, i) => {
    const m = i + 1;
    const isActive = (YM.pickerYear === YM.curYear && m === YM.curMonth);
    const isToday  = (YM.pickerYear === YM.todayYear && m === YM.todayMonth);
    const cls = ['ym-month', isActive ? 'active' : '', (!isActive && isToday) ? 'today' : ''].join(' ');
    return `<div class="${cls}" onclick="gotoYm(${YM.pickerYear},${m})">${label}</div>`;
  }).join('');
}

function gotoYm(y, m) {
  closeYmPicker();
  window.location.href = `${YM.baseUrl}?year=${y}&month=${m}&view=${YM.view}`;
}
</script>

{{-- ══════════════════════════════════ LIST VIEW ══════════════════════════════════ --}}
@if($view === 'list')
{{-- 검색 — Figma 243:433 Frame 48101549: 흰 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래 --}}
<form method="GET" action="{{ route('repurchase.index') }}" class="ds-filter-card">
  <input type="hidden" name="year"  value="{{ $year }}">
  <input type="hidden" name="month" value="{{ $month }}">
  <input type="hidden" name="view"  value="list">
  <div class="ds-filter-fields">
    {{-- 시안 검색어 칸 295px = 9열 중 2열 --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="search" class="form-control"
             placeholder="처방전 번호ㆍ이름ㆍ병원명 검색"
             value="{{ request('search') }}">
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('search'))
      <a href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'list']) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__repurchaseGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

{{-- 그리드 카드(r12) — Figma 243:433 Frame 48101545 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ $listTotal }}건)</span></button>
      </div>
    <div id="repurchaseGrid"></div>
  </div>
</div>
@endif
@endsection
