@extends('layouts.app')

@section('title', '재구매 관리')
@section('page-title', '재구매 관리')
@section('breadcrumb', '홈 / 재구매 관리')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#ymTrigger', title: '월 선택', body: '조회할 연월을 선택합니다. 클릭하면 년/월 선택 팝업이 열립니다.' },
  { selector: '#calGrid', title: '재구매 캘린더', body: '각 날짜 칸에 재구매 예정 환자 수가 표시됩니다. 숫자가 있는 날짜를 클릭하면 해당일 대상자 목록이 캘린더 아래에 펼쳐집니다.' },
  { selector: '.cal-cell:not(.cal-empty)', title: '날짜 셀 클릭', body: '숫자가 표시된 날짜를 클릭하면 재구매 대상 환자 목록이 나타납니다. 목록에서 카카오 알림톡 또는 SMS를 바로 발송할 수 있습니다.' },
];
</script>
@endpush

@section('content')
<style>
/* ── 뷰 전환 탭 (Vuexy segmented control) ── */
.view-tabs {
  display:flex; gap:0;
  border:1.5px solid var(--primary); border-radius:8px; overflow:hidden;
  box-shadow:0 2px 8px rgba(27,102,245,.15);
}
.view-tab  {
  display:flex; align-items:center; gap:6px;
  padding:7px 18px; font-size:12.5px; font-weight:600;
  background:var(--bg-card); color:var(--text-secondary);
  cursor:pointer; border:none; transition:var(--transition);
  text-decoration:none;
}
.view-tab:first-child { border-right:1.5px solid var(--primary); }
.view-tab.active      { background:var(--primary); color:#fff; }
.view-tab:not(.active):hover { background:var(--primary-light); color:var(--primary); }

/* ── 월 네비 ── */
.month-nav {
  display:flex; align-items:center; gap:10px;
}
.month-nav .month-label {
  font-size:16px; font-weight:700; color:var(--text-primary);
  min-width:110px; text-align:center;
  cursor:pointer; padding:4px 8px; border-radius:var(--radius);
  transition:var(--transition); user-select:none;
  display:flex; align-items:center; gap:5px;
}
.month-nav .month-label:hover { background:var(--primary-light); color:var(--primary); }
.month-nav .month-label i { font-size:11px; opacity:.6; }
.month-nav .btn-icon {
  width:32px; height:32px; border-radius:var(--radius);
  border:1px solid var(--border); background:var(--bg-card);
  display:flex; align-items:center; justify-content:center;
  color:var(--text-secondary); cursor:pointer; font-size:13px;
  transition:var(--transition);
}
.month-nav .btn-icon:hover { background:var(--primary-light); color:var(--primary); border-color:var(--primary-accent); }

/* ── 년월 피커 팝오버 ── */
#ymPicker {
  display:none; position:absolute; z-index:200;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); box-shadow:var(--shadow-lg);
  padding:14px; width:260px;
}
.ym-year-row {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:12px;
}
.ym-year-label {
  font-size:15px; font-weight:700; color:var(--text-primary);
  cursor:pointer; padding:2px 8px; border-radius:var(--radius);
  transition:var(--transition);
}
.ym-year-label:hover { background:var(--primary-light); color:var(--primary); }
.ym-year-btn {
  width:28px; height:28px; border-radius:var(--radius);
  border:1px solid var(--border); background:var(--bg-card);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:12px; color:var(--text-secondary);
  transition:var(--transition);
}
.ym-year-btn:hover { background:var(--primary-light); color:var(--primary); border-color:var(--primary-accent); }
.ym-months {
  display:grid; grid-template-columns:repeat(4,1fr); gap:6px;
}
.ym-month {
  padding:7px 0; text-align:center; border-radius:var(--radius);
  font-size:13px; font-weight:600; cursor:pointer;
  border:1px solid transparent; transition:var(--transition);
  color:var(--text-secondary);
}
.ym-month:hover  { background:var(--primary-light); color:var(--primary); border-color:var(--primary-accent); }
.ym-month.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.ym-month.today  { border-color:var(--primary-accent); color:var(--primary); }
#ymOverlay { display:none; position:fixed; inset:0; z-index:199; }

/* ── 캘린더 ── */
.cal-grid {
  display:grid; grid-template-columns:repeat(7,1fr);
  gap:0; border-left:1px solid var(--border); border-top:1px solid var(--border);
}
.cal-header-cell {
  padding:8px 0; font-size:11px; font-weight:700; text-align:center;
  background:var(--bg); border-right:1px solid var(--border); border-bottom:1px solid var(--border);
  color:var(--text-muted);
}
.cal-header-cell:first-child { color:#ef4444; }
.cal-header-cell:last-child  { color:#3b82f6; }

.cal-cell {
  min-height:88px; padding:6px 8px;
  border-right:1px solid var(--border); border-bottom:1px solid var(--border);
  position:relative; cursor:pointer; transition:background .12s;
  background:var(--bg-card);
}
.cal-cell:hover:not(.cal-empty) { background:var(--primary-light); }
.cal-cell.cal-empty { background:var(--bg); cursor:default; }
.cal-cell.cal-today { background:var(--warning-light); }
.cal-cell.cal-today .cal-day { color:var(--primary); font-weight:700; }
.cal-cell.cal-selected { background:var(--primary-light); outline:2px solid var(--primary-accent); outline-offset:-2px; }
.cal-cell:first-child, .cal-cell:nth-child(7n+1) .cal-day { color:#ef4444; }
.cal-cell:nth-child(7n) .cal-day { color:#3b82f6; }

.cal-day { font-size:13px; font-weight:600; line-height:1; }
.cal-count {
  display:inline-flex; align-items:center; justify-content:center;
  margin-top:8px; width:28px; height:28px; border-radius:50%;
  background:var(--primary); color:#fff; font-size:12px; font-weight:700;
  box-shadow:0 2px 6px rgba(27,102,245,.35);
}
.cal-count-label {
  font-size:10px; color:var(--text-muted); margin-top:3px;
}

/* ── 날짜 상세 패널 ── */
#dayPanel {
  display:none; margin-top:16px;
  border:1px solid var(--border); border-radius:var(--radius-lg);
  background:var(--bg-card); box-shadow:var(--shadow-md);
  overflow:hidden;
}
.day-panel-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 16px; background:var(--primary); color:#fff;
}
.day-panel-title { font-size:14px; font-weight:700; }
.day-panel-close {
  background:none; border:none; color:#fff; font-size:18px; cursor:pointer; line-height:1; padding:2px 6px;
}
#dayPanelBody { padding:0; }

/* ── 목록 행 ── */
.rx-row {
  display:flex; align-items:center; gap:12px;
  padding:10px 16px; border-bottom:1px solid var(--border-light);
  transition:background .1s; text-decoration:none; color:inherit;
}
.rx-row:last-child { border-bottom:none; }
.rx-row:hover { background:var(--bg); }
.rx-row-num { font-size:12px; font-weight:700; color:var(--primary); min-width:120px; }
.rx-row-patient { font-size:13px; font-weight:600; min-width:80px; }
.rx-row-hospital { font-size:12px; color:var(--text-secondary); flex:1; }
.rx-row-badge { flex-shrink:0; }

/* ── 목록 뷰 검색 ── */
.search-bar { display:flex; gap:8px; }
.search-bar .form-control { max-width:260px; }
</style>

{{-- ── 상단 컨트롤 ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
  {{-- 월 네비 --}}
  <div class="month-nav">
    @php
      $prevYear  = $month === 1 ? $year - 1 : $year;
      $prevMonth = $month === 1 ? 12 : $month - 1;
      $nextYear  = $month === 12 ? $year + 1 : $year;
      $nextMonth = $month === 12 ? 1 : $month + 1;
    @endphp
    <a class="btn-icon" href="{{ route('repurchase.index', ['year'=>$prevYear,'month'=>$prevMonth,'view'=>$view]) }}">
      <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="position:relative;">
      <span class="month-label" id="ymTrigger" onclick="toggleYmPicker(this)">
        {{ $year }}년 {{ $month }}월
        <i class="fa-solid fa-caret-down"></i>
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
    <a class="btn-icon" href="{{ route('repurchase.index', ['year'=>$nextYear,'month'=>$nextMonth,'view'=>$view]) }}">
      <i class="fa-solid fa-chevron-right"></i>
    </a>
    @if($year !== now()->year || $month !== now()->month)
      <a class="btn btn-outline btn-sm" href="{{ route('repurchase.index') }}">오늘</a>
    @endif
  </div>

  {{-- 우측: 건수 + 뷰 전환 --}}
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:13px;color:var(--text-secondary);">
      이 달 <strong style="color:var(--primary);">{{ $totalCount }}</strong>건
    </span>
    <div class="view-tabs">
      <a class="view-tab {{ $view === 'calendar' ? 'active' : '' }}"
         href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'calendar']) }}">
        <i class="fa-regular fa-calendar"></i> 캘린더
      </a>
      <a class="view-tab {{ $view === 'list' ? 'active' : '' }}"
         href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'list']) }}">
        <i class="fa-solid fa-list"></i> 목록
      </a>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════ CALENDAR VIEW ══════════════════════════════════ --}}
@if($view === 'calendar')
<div class="card">
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

    {{-- 앞 빈칸 --}}
    @for($i = 0; $i < $firstDow; $i++)
      <div class="cal-cell cal-empty"></div>
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
          <div><span class="cal-count">{{ $cnt }}</span></div>
          <div class="cal-count-label">건</div>
        @endif
      </div>
    @endfor

    {{-- 뒤 빈칸 --}}
    @php
      $lastDow  = (int)$endOfMonth->dayOfWeek;
      $trailing = $lastDow === 6 ? 0 : 6 - $lastDow;
    @endphp
    @for($i = 0; $i < $trailing; $i++)
      <div class="cal-cell cal-empty"></div>
    @endfor
  </div>
</div>

{{-- 날짜 클릭 시 상세 패널 --}}
<div id="dayPanel">
  <div class="day-panel-header">
    <span class="day-panel-title" id="dayPanelTitle"></span>
    <button class="day-panel-close" onclick="closeDay()">×</button>
  </div>
  <div id="dayPanelBody">
    <div style="padding:24px;text-align:center;color:var(--text-muted);">
      <i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중…
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
    `${y}년 ${parseInt(m)}월 ${parseInt(d)}일 재구매 예정`;

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
      body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);">해당 날짜의 재구매 예정 건이 없습니다.</div>';
      return;
    }

    const statusColors = {
      pending:'#9ca3af', ocr_processing:'#f57c00', ocr_done:'#0288d1',
      review_needed:'#c62828', approved:'#2e7d32', rejected:'#b71c1c', ordered:'#1565c0'
    };

    body.innerHTML = data.map(item => `
      <a class="rx-row" href="${item.url}">
        <span class="rx-row-num"><i class="fa-solid fa-file-medical" style="color:var(--primary);margin-right:4px;"></i>${item.rx_number}</span>
        <span class="rx-row-patient">${item.patient_name}</span>
        <span class="rx-row-hospital">${item.hospital}</span>
        <span class="rx-row-badge">
          <span class="badge" style="background:${statusColors[item.status] ?? '#9ca3af'}22;color:${statusColors[item.status] ?? '#9ca3af'};">
            ${item.status_label}
          </span>
        </span>
        <span style="font-size:11px;color:var(--text-muted);flex-shrink:0;">${item.created_at}</span>
        <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);font-size:11px;flex-shrink:0;"></i>
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
<div class="card">
  {{-- 검색 --}}
  <div class="card-body" style="border-bottom:1px solid var(--border);padding:12px 16px;">
    <form method="GET" action="{{ route('repurchase.index') }}" class="search-bar">
      <input type="hidden" name="year"  value="{{ $year }}">
      <input type="hidden" name="month" value="{{ $month }}">
      <input type="hidden" name="view"  value="list">
      <input type="text" name="search" class="form-control"
             placeholder="처방전번호·환자명·병원명 검색"
             value="{{ request('search') }}">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-magnifying-glass"></i> 검색
      </button>
      @if(request('search'))
        <a href="{{ route('repurchase.index', ['year'=>$year,'month'=>$month,'view'=>'list']) }}"
           class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>재구매 예정일</th>
          <th>처방전 번호</th>
          <th>환자명</th>
          <th>병원</th>
          <th>상태</th>
          <th>등록일</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($listItems as $p)
          <tr style="cursor:pointer;" onclick="window.location='{{ route('prescriptions.show', $p->rx_number) }}'">
            <td>
              <span style="font-weight:700;color:var(--primary);">
                <i class="fa-regular fa-calendar-check" style="margin-right:4px;"></i>
                {{ $p->repurchase_date->format('Y-m-d') }}
              </span>
            </td>
            <td><span style="font-size:12px;font-weight:600;">{{ $p->rx_number }}</span></td>
            <td>{{ $p->patient_name_ocr ?? $p->patient?->name ?? '-' }}</td>
            <td style="color:var(--text-secondary);">{{ $p->hospital_name ?? '-' }}</td>
            <td>
              @php
                $badgeMap = [
                  'pending'        => 'secondary',
                  'ocr_processing' => 'warning',
                  'ocr_done'       => 'info',
                  'review_needed'  => 'danger',
                  'approved'       => 'success',
                  'rejected'       => 'danger',
                  'ordered'        => 'primary',
                ];
              @endphp
              <span class="badge badge-{{ $badgeMap[$p->status] ?? 'secondary' }}">
                {{ $p->status_label }}
              </span>
            </td>
            <td style="color:var(--text-muted);font-size:12px;">{{ $p->created_at->format('Y-m-d') }}</td>
            <td>
              <a href="{{ route('prescriptions.show', $p->rx_number) }}"
                 class="btn btn-outline btn-sm" onclick="event.stopPropagation()">
                상세
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="fa-regular fa-calendar-xmark" style="font-size:32px;display:block;margin-bottom:10px;"></i>
              {{ $year }}년 {{ $month }}월 재구매 예정 건이 없습니다.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($listItems->hasPages())
    <div style="padding:12px 16px;border-top:1px solid var(--border);">
      {{ $listItems->links() }}
    </div>
  @endif
</div>
@endif
@endsection
