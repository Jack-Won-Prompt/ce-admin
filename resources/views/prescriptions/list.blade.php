{{-- resources/views/prescriptions/list.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 목록')
@section('page-title', '처방전 목록')
{{-- 시안 128:1744 빵부스러기는 '홈 - 처방전 목록' 이다(구분자 하이픈). --}}
@section('breadcrumb', '홈 - 처방전 목록')

@section('help-title', '처방전 목록 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>모바일/웹에서 업로드된 처방전을 조회하고 검수·주문 연계를 관리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">상태 탭 설명</div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-error"></i></div>
    <div class="help-item-text"><strong>검수 필요</strong>담당자가 입력해야 하는 처방전입니다. 우선 처리하십시오.</div>
  </div>
  {{-- 「OCR 처리중」 안내는 두지 않는다 — OCR 을 쓰지 않는다(담당자 수기 입력) --}}
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-check-circle"></i></div>
    <div class="help-item-text"><strong>검수 완료</strong>확인된 처방전입니다. 주문 연계 대기 상태입니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-cart-alt"></i></div>
    <div class="help-item-text"><strong>주문 미등록</strong>검수는 완료됐지만 위드웍스 주문이 아직 없는 건입니다.</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 기능</div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-user-check"></i></div>
    <div class="help-item-text"><strong>담당자 지정</strong>각 행의 검수 담당자 셀렉트박스에서 즉시 변경 가능합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link-external"></i></div>
    <div class="help-item-text"><strong>주문번호ㆍ위드웍스 판매번호</strong>위드웍스 판매번호가 이어진 건은 파란 모노스페이스 글꼴로 보입니다.</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .filter-bar .form-control { height: 32px; font-size: 13px; }
  .filter-bar .btn { height: 32px; white-space: nowrap; }

  /* ── Vuexy pill status tabs ── */
  .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 999px; font-size: 12px; font-weight: 500;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .status-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .status-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
  .status-tab .tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 16px; height: 16px; padding: 0;
    border-radius: 999px; font-size: 10px; font-weight: 700; line-height: 1;
    background: var(--gray-0); color: var(--primary);
  }
  .status-tab:not(.active) .tab-count { background: var(--border-light); color: var(--text-muted); }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }
  .rx-date { font-size: 11px; color: var(--text-muted); }
  .ocr-bar { display: flex; align-items: center; gap: 6px; }
  .ocr-bar-track { flex: 1; height: 5px; background: var(--border); border-radius: 3px; min-width: 40px; overflow: hidden; }
  .ocr-bar-fill { height: 100%; border-radius: 3px; }
  .ocr-pct { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
  .table-actions { display: flex; gap: 4px; }
  .empty-state { text-align: center; padding: 56px 24px; color: var(--text-muted); }
  .empty-state i { font-size: 44px; margin-bottom: 12px; display: block; opacity: .3; }
  .empty-state p { font-size: 13px; margin: 0; }

  /* 담당자 인라인 셀렉트 */
  .assign-select {
    font-size: 12px; padding: 3px 24px 3px 10px; height: 28px;
    border: 1.5px dashed var(--border); border-radius: 20px;
    background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23a5a3ae'/%3E%3C/svg%3E") no-repeat right 8px center;
    background-size: 8px 5px;
    color: var(--text-muted);
    cursor: pointer; max-width: 120px;
    appearance: none; -webkit-appearance: none;
    transition: border-color .15s, background-color .15s, color .15s, box-shadow .15s;
  }
  .assign-select.assigned {
    border-style: solid; border-color: var(--primary);
    background-color: var(--primary-light);
    color: var(--primary); font-weight: 600;
  }
  .assign-select:hover {
    border-color: var(--primary); border-style: solid;
    background-color: var(--primary-light); color: var(--primary);
    box-shadow: 0 0 0 3px rgba(40,121,139,.12);
  }
  .assign-select:focus { outline: none; border-color: var(--primary); border-style: solid;
    box-shadow: 0 0 0 3px rgba(40,121,139,.2); }
  .assign-select.saving { opacity: .5; pointer-events: none; }
</style>
@endpush

@section('content')

  {{-- 상태는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
       고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}
  @php $curAcc = request('acc_type'); @endphp

  {{-- 검색 필터 — Figma 128:1744: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래 --}}
  <form method="GET" action="{{ route('prescriptions.index') }}" class="ds-filter-card">
    <div class="ds-filter-fields">
      {{-- 검색어 143px(1열) · 기간 298px(2열) — 시안 실측 --}}
      <div class="ds-filter-field">
        <label class="ds-field-label">상태</label>
        <select name="status" class="form-control form-select" onchange="this.form.submit()">
          @php
            $curSt = request('status');
            /* 흐름대로 늘어놓는다 — 검수 필요 → 검수 요청 → 검수 완료.
               「OCR 처리중」은 더 생기지 않아 고르는 자리에서 걷었다. */
            $sts = ['' => '전체', 'review_needed' => '검수 필요', 'review_requested' => '검수 요청',
                    'approved' => '검수 완료', 'no_order' => '주문 미등록', 'ordered' => '주문 완료',
                    'rejected' => '반려'];
          @endphp
          @foreach($sts as $code => $label)
            @php $cnt = $statusCounts[$code === '' ? 'all' : $code] ?? 0; @endphp
            <option value="{{ $code }}" {{ (string) $curSt === (string) $code ? 'selected' : '' }}>
              {{ $label }}@if($cnt > 0) ({{ $cnt }})@endif
            </option>
          @endforeach
        </select>
      </div>
      <div class="ds-filter-field">
        <label class="ds-field-label">검색어</label>
        <input type="text" name="search" class="form-control"
               placeholder="처방번호ㆍ이름ㆍ병원명ㆍ요양기관코드" value="{{ request('search') }}">
      </div>
      {{-- 두 칸(298)에서는 날짜가 「2026-06-…」로 잘렸다 — 달력 아이콘까지 서야 해서
           한 칸이 150 은 있어야 한다. 세 칸을 준다(이 화면은 아홉 칸 중 여섯만 쓴다). --}}
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">기간</label>
        <div class="ds-field-range">
          <input type="date" name="date_from" class="form-control"
                 value="{{ request('date_from', now()->subDays(60)->format('Y-m-d')) }}">
          <span class="ds-field-sep">~</span>
          <input type="date" name="date_to" class="form-control"
                 value="{{ request('date_to', now()->format('Y-m-d')) }}">
        </div>
      </div>
      <div class="ds-filter-field">
        {{-- 처방 유형 — 원내·원외·처방외. 위쪽 칩은 진행 상태 하나만 두고,
             나머지 갈래는 여기서 고른다. 칩이 두 줄이면 무엇이 무엇인지 헷갈린다. --}}
        <label class="ds-field-label">처방유형</label>
        <select name="acc_type" class="form-control form-select" onchange="this.form.submit()">
          <option value="">전체</option>
          @foreach(\App\Models\Prescription::ACC_TYPES as $code => $label)
            {{-- 배열 키가 정수로 바뀌므로 문자열로 되돌려 견준다 --}}
            <option value="{{ $code }}" {{ $curAcc === (string) $code ? 'selected' : '' }}>
              {{ $label }}@if(($accCounts[$code] ?? 0) > 0) ({{ $accCounts[$code] }})@endif
            </option>
          @endforeach
        </select>
      </div>
      {{-- 「표시 건수」 칸은 두지 않는다. 목록은 wwGrid 가 한 번에 다 받아 그리고
           (컨트롤러가 ->get() 으로 통째로 넘긴다) 페이지를 나누지 않는다 —
           이 칸은 아무 일도 하지 않으면서 「10개씩」이라 적어 거짓을 말하고 있었다. --}}
    </div>
    <div class="ds-filter-actions">
      {{-- 초기화 — 시안 128:1744 은 검색 왼쪽에 늘 세워 둔다. 검색 조건이 있을 때만
           내보내던 조건을 걷었다. 링크는 그대로 이 화면의 라우트로 되돌아간다
           (지금 보고 있는 상태 칩·표시 건수는 유지). --}}
      <a href="{{ route('prescriptions.index', request()->only('status')) }}" class="ds-btn">초기화</a>
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
      {{-- 「처방전 업로드」 단추는 걷었다 (2026-09-10 지시).
           올리는 자리는 왼쪽 메뉴의 「처방자료 업로드」다 — 화면과 경로는 그대로다. --}}
      {{-- 「검수할 자료」 — 아직 검수하지 않은 건만 모아 본다 (2026-09-10 확인요청 4쪽).

           올린 자료는 여기서 검수한다. 상태 칸에서 「검수 필요」를 골라도 같은 곳에
           닿지만, 올리고 곧장 들어오는 걸음이라 한 번에 갈 자리를 둔다.
           남은 건수를 함께 적는다 — 0 이면 오늘 할 일이 없다는 뜻이다. --}}
      @php $_검수필요 = (int) ($statusCounts['review_needed'] ?? 0); @endphp
      <a href="{{ route('prescriptions.index', ['status' => 'review_needed']) }}"
         class="ds-btn{{ request('status') === 'review_needed' ? ' ds-btn-primary' : '' }}"
         title="아직 검수하지 않은 처방전만 봅니다">
        검수할 자료@if($_검수필요 > 0) ({{ $_검수필요 }})@endif
      </a>
      {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
      <button type="button" class="ds-btn" onclick="window.__rxGrid?.downloadExcel()">엑셀 다운</button>
      <button type="button" class="ds-btn" onclick="prescriptionViewDetail()">선택 상세</button>
    </div>
  </form>

  {{-- Figma 128:1744 — 흰 카드(r12) 안에 그리드.
       건수는 거래처 관리와 같은 자리에서 읽는다 — 카드 첫 줄의 탭 이름 뒤 괄호다. --}}
  <div class="ds-grid-section">
    <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
      <div id="rxGrid"></div>
    </div>
  </div>

{{-- 검수 창 그림 도구 (2026-09-12 지시).

     주문 등록의 뷰어(prescriptions/_viewer)와 같은 도구를 그림마다 붙인다.
     그쪽 부분틀은 칸 이름이 고정이라(prescCanvasㆍimgCanvas) 한 화면에 하나만
     설 수 있는데, 검수 창은 그림을 여러 장 늘어놓는다 — 그래서 모양만 같이 하고
     자리는 그림마다 따로 잡는다. --}}
<style>
  .rv-stage { position:relative; height:560px; overflow:hidden; background:var(--gray-100);
              display:flex; align-items:center; justify-content:center; }
  .rv-stage img { display:block; max-width:100%; max-height:100%; object-fit:contain;
                  transform-origin:center center; cursor:grab; user-select:none; }
  .rv-stage img:active { cursor:grabbing; }

  /* 그림 왼쪽에 반투명 세로 띠 — 그림이 보이는 넓이를 잃지 않는다 */
  .rv-tools { position:absolute; top:8px; left:8px; bottom:8px; z-index:2;
              display:flex; flex-direction:column; justify-content:space-between;
              align-items:center; gap:8px; padding:8px; border-radius:8px;
              background:rgba(255,255,255,.4); }
  .rv-tool-group { display:flex; flex-direction:column; align-items:center; gap:8px; }
  .rv-tool { width:32px; height:32px; display:flex; align-items:center; justify-content:center;
             border-radius:8px; background:var(--gray-0); border:none; padding:0;
             font-size:13px; color:var(--gray-800); cursor:pointer; transition:var(--transition); }
  .rv-tool:hover { color:var(--primary); }
  .rv-zoom { font-size:12px; font-weight:500; line-height:1.2; color:var(--gray-1000); text-align:center; }

  /* 밝기ㆍ명암 — 파일은 건드리지 않는다. 맞춰 둔 값은 공단 팩스에도 그대로 간다. */
  .rv-tune { position:absolute; right:8px; bottom:8px; z-index:6;
             display:none; flex-direction:column; gap:6px; width:186px;
             padding:10px 12px; border-radius:10px;
             background:rgba(255,255,255,.96); border:1px solid var(--gray-200);
             box-shadow:0 4px 16px rgba(0,0,0,.12); }
  .rv-tune.on { display:flex; }
  .rv-tune-row { display:flex; align-items:center; gap:8px; font-size:11px; color:var(--gray-700); }
  .rv-tune-row > span:first-child { width:28px; flex:none; }
  .rv-tune-row input[type=range] { flex:1; min-width:0; accent-color:var(--primary); }
  .rv-tune-row > b { width:30px; flex:none; text-align:right; font-weight:600;
                     font-variant-numeric:tabular-nums; color:var(--gray-1000); }
  .rv-tune-acts { display:flex; gap:6px; margin-top:2px; }
  .rv-tune-acts button { flex:1; padding:5px 0; font-size:11px; border-radius:6px;
                         border:1px solid var(--gray-200); background:var(--gray-0);
                         color:var(--gray-1000); cursor:pointer; }
  .rv-tune-acts button.pri { background:var(--primary); border-color:var(--primary); color:#fff; }
</style>

{{-- ── 파일 검수 창 (2026-09-10 지시) ────────────────────────────────

     올린 것을 한자리에서 내리읽고, 다 보았으면 그 자리에서 검수를 마친다.
     여태 검수하려면 주문 등록 화면을 열어 뷰어에서 한 장씩 넘겨야 했다.

     아래 단추 줄은 창에 붙여 둔다 — 그림이 스무 장이어도 「검수 확인」이 늘
     같은 자리에 있어야 한다. --}}
<div id="rvBackdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1190;"
     onclick="rvClose()"></div>
<div id="rvModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:900px;max-width:96vw;height:88vh;background:var(--bg-card);border:1px solid var(--primary);
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.24);z-index:1191;
     display:none;flex-direction:column;overflow:hidden;">

  <div style="background:var(--primary);padding:11px 14px;display:flex;align-items:center;gap:8px;flex-shrink:0;">
    <i class="fa-solid fa-file-magnifying-glass" style="color:#fff;font-size:14px;"></i>
    <span id="rvTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">파일 검수</span>
    <span id="rvCount" style="font-size:11.5px;color:rgba(255,255,255,.85);"></span>
    <button onclick="rvClose()" style="border:none;background:none;color:#fff;font-size:17px;line-height:1;cursor:pointer;">&#215;</button>
  </div>

  {{-- 스크롤은 이 칸에서만 인다 --}}
  <div id="rvBody" style="flex:1;overflow-y:auto;padding:14px;background:var(--gray-50);"></div>

  <div style="flex-shrink:0;border-top:1px solid var(--gray-300);background:var(--bg-card);
              padding:11px 14px;display:flex;align-items:center;gap:8px;">
    <span id="rvNote" style="font-size:12px;color:var(--text-muted);flex:1;"></span>
    <button type="button" class="ds-btn" onclick="rvClose()">닫기</button>
    <button type="button" class="ds-btn ds-btn-primary" id="rvApprove" onclick="rvApprove(this)">검수 확인</button>
  </div>
</div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  {
    /* 상단 상태 칩은 main c99af13 이 걷고 검색 필터의 첫 칸으로 옮겼다 —
       .status-tabs 는 마크업에 없어 아무것도 가리키지 못했다. */
    selector: '.ds-filter-field:has(select[name="status"]), select[name="status"]',
    title: '상태 고르기',
    body: '처방전을 상태별로 필터링합니다. <b>검수 필요</b> 탭을 먼저 확인하여 처리 대기 중인 처방전을 처리하세요.'
  },
  {
    selector: '.ds-filter-card',
    title: '검색 및 필터',
    body: '이름, 처방번호, 병원명으로 검색하거나 날짜 범위를 지정해 조회할 수 있습니다.'
  },
  {
    selector: '#rxGrid',
    title: '목록 그리드',
    body: '행을 <b>더블클릭</b>하면 주문 화면이 <b>새 탭</b>으로 열립니다(목록 탭은 그대로 유지). <b>판매유형</b>과 <b>위드웍스 판매번호</b> 칸에서 주문 연계 상태를 한눈에 확인할 수 있고, 컬럼 헤더를 클릭해 정렬할 수 있습니다.'
  },
  {
    selector: 'button[onclick="prescriptionViewDetail()"]',
    title: '선택 상세',
    body: '행을 체크한 뒤 <b>선택 상세</b> 버튼을 눌러도 동일하게 주문 화면이 새 탭으로 열립니다.'
  },
];

</script>
<script>
(function () {
  const DETAIL_BASE = @json(url('prescriptions'));

  /* 올린 파일 수 — 없는 건은 빈칸이 아니라 0 으로 적는다. 빈칸은 「모른다」로 읽힌다. */
  const 파일수칸 = (v) => {
    const n = Number(v || 0);
    const s = document.createElement('span');
    s.textContent = n ? n + '장' : '없음';
    if (!n) s.style.color = 'var(--text-muted)';
    return s;
  };

  /* 「파일 검수」 단추 — 이미 마친 건은 눌러도 다시 승인하지 않는다. */
  const 검수칸 = (v, row) => {
    const 마쳤나 = v === 'approved' || v === 'ordered';
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'ds-btn' + (마쳤나 ? '' : ' ds-btn-primary');
    b.style.height = '24px';
    b.style.minWidth = '0';
    b.style.padding = '0 10px';
    b.style.fontSize = '11.5px';
    b.textContent = 마쳤나 ? '검수 완료' : '파일 검수';
    b.onclick = (e) => { e.stopPropagation(); rvOpen(row.rx_number, row.id); };
    return b;
  };
  /* 「요청 여부」 — 아직 안 닫힌 다시 올리기 요청이 있나 (2026-09-12 지시).
     자료가 다시 올라오면 스스로 닫히므로, 여기 숫자가 남아 있다는 것은
     아직 못 받았다는 뜻이다. */
  const 요청칸 = (v) => {
    const n = Number(v || 0);
    const s = document.createElement('span');
    s.textContent = n ? '요청 중 (' + n + ')' : '—';
    s.style.fontSize = '11.5px';
    if (n) { s.style.color = 'var(--danger)'; s.style.fontWeight = '700'; }
    else   { s.style.color = 'var(--gray-400)'; }
    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('rxGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '처방번호',      name: 'rx_number',  width: 150, sortable: true },
      { header: '출처',          name: 'source',     width: 70,  align: 'center', sortable: true },
      { header: '이름',        name: 'patient',    width: 100, sortable: true },
      { header: '병원',          name: 'hospital',   width: 150, sortable: true },
      // 요양기관코드 — 공단과 맞출 때 쓰는 병원 번호(2026-09-08 확인요청 7쪽)
      { header: '요양기관코드',  name: 'hosp_code',  width: 120, align: 'center', sortable: true },
      { header: '발행일',        name: 'issued',     width: 100, align: 'center', sortable: true },
      { header: '상태',          name: 'status',     width: 90,  align: 'center', sortable: true },
      /* 올린 파일과 그것을 보는 단추 (2026-09-10 지시).
         상태 바로 옆에 둔다 — 「무엇이 올라왔나」와 「검수했나」는 잇대어 읽는 값이다. */
      { header: '업로드 파일',   name: 'files',      width: 100, align: 'center', sortable: true,
        renderer: 파일수칸 },
      { header: '파일 검수',     name: 'review',     width: 110, align: 'center',
        exportable: false, renderer: 검수칸 },
      /* 검수 바로 옆 — 「검수했나」와 「되물었나」는 잇대어 읽는 값이다 (2026-09-12 지시) */
      { header: '요청 여부',     name: 'reupload',   width: 100, align: 'center',
        sortable: true, renderer: 요청칸 },
      { header: '처방유형',      name: 'acc_type',   width: 110, align: 'center', sortable: true },
      { header: '판매유형',      name: 'so_type',    width: 90,  align: 'center', sortable: true },
      { header: '주문번호',      name: 'order_no',   width: 140, sortable: true },
      // 시안은 'WithWorks So' 였으나 화면 낱말을 우리말로 맞춘다(2026-09-11 지시).
      // name 은 그대로 둔다(엑셀 머리글은 이 header 를 그대로 쓴다).
      { header: '위드웍스 판매번호', name: 'so_no',   width: 150, sortable: true },
      { header: '검수 담당자',   name: 'assignee',   width: 90,  align: 'center', sortable: true },
      // 요청서 6쪽 — 주민등록번호ㆍ업로드 담당자ㆍ검수 일자ㆍ검수 메모
      { header: '주민등록번호',  name: 'resident_no', width: 130 },
      { header: '업로드 담당자', name: 'uploader',   width: 110, align: 'center', sortable: true },
      { header: '검수 일자',     name: 'reviewed_at', width: 130, align: 'center', sortable: true },
      { header: '검수 요청 메모', name: 'review_request_memo', width: 200 },
      { header: '참고 사항',     name: 'review_memo', width: 240 },
      { header: '접수일시',      name: 'created',    width: 130, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  // 결과바의 '엑셀 저장' 버튼이 부를 수 있게 인스턴스를 노출한다(그리드 내장 툴바 대체).
  window.__rxGrid = grid;
  window.dsBindSelCount(grid, 'rxSelCount');

  /* 주문 화면을 '새 탭'으로 연다.
     워크스페이스 안에서는 목록 탭을 그대로 두고 별도 탭이 열리고(ceOpenTab),
     단독 페이지로 열려 있으면 브라우저 새 탭으로 대체된다. */
  function openReviewTab(rxNumber) {
    const url = DETAIL_BASE + '/' + encodeURIComponent(rxNumber);
    if (typeof window.ceOpenTab === 'function') {
      {{-- 그 화면의 제목이 「주문」이다. 탭 이름이 화면 이름과 다르면
           어느 탭이 무엇인지 알 수 없다. --}}
      window.ceOpenTab(url, '주문 - ' + rxNumber, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  }

  // 행 더블클릭 → 주문 화면을 새 탭으로 열기(목록은 그대로 유지)
  document.getElementById('rxGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.rx_number) return;
    window.getSelection()?.removeAllRanges();   // 더블클릭 텍스트 선택 해제
    openReviewTab(row.rx_number);
  });

  window.prescriptionViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    openReviewTab(c[0].rx_number);
  };

  /* ── 파일 검수 창 (2026-09-10 지시) ─────────────────────────────

     올린 것을 한자리에서 내리읽고, 다 보았으면 그 자리에서 마친다.
     여태 검수하려면 주문 등록 화면을 열어 뷰어에서 한 장씩 넘겨야 했다. */
  /* 주소는 처방번호로 짚는다 — 이 화면의 다른 길과 같다(라우트 열쇠가 rx_number).
     표의 줄은 id 로 찾는다 — 그 줄만 고쳐 세우려면 번호가 있어야 한다. */
  let _rv = { rx: null, id: null, 마쳤나: false, 사유: {} };

  window.rvOpen = async function (rx, id) {
    _rv = { rx, id, 마쳤나: false };

    document.getElementById('rvTitle').textContent = '파일 검수';
    document.getElementById('rvCount').textContent = '';
    document.getElementById('rvNote').textContent  = '';
    document.getElementById('rvBody').innerHTML =
      '<div style="text-align:center;padding:60px;color:var(--text-muted);font-size:12.5px;">불러오는 중…</div>';

    document.getElementById('rvBackdrop').style.display = 'block';
    document.getElementById('rvModal').style.display    = 'flex';

    try {
      const res = await fetch(DETAIL_BASE + '/' + encodeURIComponent(rx) + '/files', {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const d = await res.json();

      _rv.마쳤나 = d.status === 'approved' || d.status === 'ordered';

      document.getElementById('rvTitle').textContent =
        (d.patient ? d.patient + ' · ' : '') + d.rx + ' · ' + d.label;
      document.getElementById('rvCount').textContent = (d.files?.length ?? 0) + '장';

      const 단추 = document.getElementById('rvApprove');
      단추.disabled    = _rv.마쳤나;
      단추.textContent = _rv.마쳤나 ? '검수 완료' : '검수 확인';
      document.getElementById('rvNote').textContent = _rv.마쳤나
        ? '이미 검수를 마친 처방전입니다.'
        : '모두 확인하셨으면 「검수 확인」을 누르십시오 — 상태가 검수 완료로 바뀝니다.';

      _rv.사유 = d.reasons ?? {};

      document.getElementById('rvBody').innerHTML = (d.files ?? []).length
        ? (d.files ?? []).map(f => `
            <div style="background:#fff;border:1px solid var(--gray-300);border-radius:10px;
                        margin-bottom:12px;overflow:hidden;">
              <div style="display:flex;align-items:center;gap:8px;padding:8px 11px;
                          border-bottom:1px solid var(--gray-200);font-size:12px;">
                <b style="color:var(--primary);">${_esc(f.label)}</b>
                ${f.by ? `<span style="color:var(--text-primary);background:var(--gray-100);
                          border-radius:4px;padding:1px 6px;font-size:11px;white-space:nowrap;"
                          title="이 파일을 올린 사람">${_esc(f.by)}</span>` : ''}
                <span style="color:var(--text-muted);flex:1;">${_esc(f.name ?? '')}</span>
                <button type="button" class="ds-btn" data-rq="open" data-file="${f.id}"
                        style="height:22px;min-width:0;padding:0 9px;font-size:11px;">다시 올리기 요청</button>
              </div>

              ${_rq요청칸(f)}
              ${_rq이력(f)}

              ${f.isPdf
                ? `<iframe src="${_esc(f.url)}" style="width:100%;height:560px;border:none;background:#fff;"></iframe>`
                : _rv무대(f)}
            </div>`).join('')
        : '<div style="text-align:center;padding:60px;color:var(--text-muted);font-size:12.5px;">올라온 파일이 없습니다.</div>';
    } catch (e) {
      document.getElementById('rvBody').innerHTML =
        '<div style="text-align:center;padding:60px;color:var(--danger);font-size:12.5px;">파일을 불러오지 못했습니다.</div>';
    }
  };

  const _esc = (v) => String(v ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

  /* ── 다시 올리기 요청 (2026-09-12 지시) ────────────────────────

     그림이 흐려 글씨가 안 읽히거나 처방전 자리에 다른 서류가 올라와 있을 때,
     그 파일 하나를 짚어 올린 사람의 앱으로 알린다. 여태는 전화를 걸거나 검수
     메모에 적어 두는 수밖에 없었고, 올린 사람은 무엇을 다시 올려야 하는지
     알 길이 없었다. */
  function _rq요청칸(f) {
    const 고르개 = Object.entries(_rv.사유 ?? {})
      .map(([k, v]) => `<option value="${_esc(k)}">${_esc(v)}</option>`).join('');

    return `
      <div data-rq-box="${f.id}" style="display:none;padding:10px 11px;background:var(--gray-50);
           border-bottom:1px solid var(--gray-200);">
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <select data-rq-reason="${f.id}" class="form-control form-select"
                  style="width:190px;height:28px;font-size:11.5px;padding:0 8px;">${고르개}</select>
          <input type="text" data-rq-memo="${f.id}" class="form-control" maxlength="500"
                 placeholder="덧붙일 말 (그 밖의 사유를 고르셨으면 반드시)"
                 style="flex:1;min-width:200px;height:28px;font-size:11.5px;padding:0 8px;">
          <button type="button" class="ds-btn ds-btn-primary" data-rq="send" data-file="${f.id}"
                  style="height:28px;min-width:0;padding:0 11px;font-size:11.5px;">보내기</button>
          <button type="button" class="ds-btn" data-rq="cancel" data-file="${f.id}"
                  style="height:28px;min-width:0;padding:0 11px;font-size:11.5px;">취소</button>
        </div>
        <div style="margin-top:5px;font-size:11px;color:var(--text-muted);">
          이 파일을 올린 사람의 앱으로 알립니다. 처방전은 「검수 필요」로 돌아갑니다.
        </div>
      </div>`;
  }

  /* 이력은 지우지 않는다 — 같은 자료를 몇 번 되물었는지가 그대로 남는다 */
  function _rq이력(f) {
    const 줄 = f.requests ?? [];
    if (!줄.length) return '';

    return `
      <div data-rq-log="${f.id}" style="padding:8px 11px;border-bottom:1px solid var(--gray-200);
           background:#FFFBEB;font-size:11.5px;line-height:1.7;">
        ${줄.map(r => `
          <div style="display:flex;gap:7px;align-items:baseline;">
            <span style="color:var(--text-muted);white-space:nowrap;">${_esc(r.at)}</span>
            <b>${_esc(r.label)}</b>
            <span style="flex:1;color:var(--text-primary);">${_esc(r.memo ?? '')}</span>
            <span style="color:var(--text-muted);white-space:nowrap;">
              ${_esc(r.by ?? '')} → ${_esc(r.to ?? '받을 사람 없음')}
            </span>
            <span style="white-space:nowrap;font-weight:700;color:${r.resolved ? 'var(--success)' : (r.sent ? 'var(--primary)' : '#B54708')};"
                  title="${_esc(r.error ?? '')}">
              ${r.resolved ? '받음 ' + _esc(r.resolved) : (r.sent ? '알림 보냄' : '알림 못 감')}
            </span>
          </div>`).join('')}
      </div>`;
  }

  /* ── 그림 도구 (2026-09-12 지시) ───────────────────────────────

     회전ㆍ복원ㆍ밝기명암ㆍ확대축소와 바퀴 굴림 확대. 주문 등록 뷰어와 같은
     것을 그림마다 하나씩 세운다. 값은 무대(.rv-stage)의 dataset 에 담는다 —
     그림이 스무 장이어도 서로 섞이지 않는다. */
  function _rv무대(f) {
    return `
      <div class="rv-stage" data-rv-stage="${f.id}" data-key="${_esc(f.key ?? '')}"
           data-zoom="1" data-rot="0" data-tx="0" data-ty="0"
           data-bright="${Number(f.bright || 0)}" data-contrast="${Number(f.contrast || 0)}">
        <div class="rv-tools">
          <div class="rv-tool-group">
            <button type="button" class="rv-tool" data-rv="rotate" title="회전"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="rv-tool" data-rv="reset" title="처음으로 복원"><i class="fa-solid fa-arrows-rotate"></i></button>
            <button type="button" class="rv-tool" data-rv="tune" title="밝기ㆍ명암"><i class="fa-solid fa-circle-half-stroke"></i></button>
          </div>
          <div class="rv-tool-group">
            <button type="button" class="rv-tool" data-rv="out" title="축소"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <span class="rv-zoom" data-rv-zoom>100%</span>
            <button type="button" class="rv-tool" data-rv="in" title="확대"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          </div>
        </div>

        <div class="rv-tune">
          <div class="rv-tune-row">
            <span>밝기</span>
            <input type="range" data-rv-bright min="-100" max="100" step="5" value="${Number(f.bright || 0)}">
            <b data-rv-bright-val>${Number(f.bright || 0)}</b>
          </div>
          <div class="rv-tune-row">
            <span>명암</span>
            <input type="range" data-rv-contrast min="-100" max="100" step="5" value="${Number(f.contrast || 0)}">
            <b data-rv-contrast-val>${Number(f.contrast || 0)}</b>
          </div>
          <div class="rv-tune-acts">
            <button type="button" data-rv="tune-reset">원본</button>
            <button type="button" class="pri" data-rv="tune-save">저장</button>
          </div>
        </div>

        <img src="${_esc(f.url)}" alt="${_esc(f.label)}" loading="lazy" draggable="false">
      </div>`;
  }

  /* 담아 둔 값을 그림에 입힌다. 밝기ㆍ명암은 주문 등록과 같은 셈을 쓴다 —
     -100~100 을 CSS filter 의 배수로 옮긴다. */
  function _rv그리기(무대) {
    const img = 무대.querySelector('img');
    if (!img) return;

    const z = Number(무대.dataset.zoom || 1);
    const r = Number(무대.dataset.rot || 0);
    const x = Number(무대.dataset.tx || 0);
    const y = Number(무대.dataset.ty || 0);
    const b = Number(무대.dataset.bright || 0);
    const c = Number(무대.dataset.contrast || 0);

    img.style.transform = `translate(${x}px, ${y}px) rotate(${r}deg) scale(${z})`;
    img.style.filter    = (b || c)
      ? `brightness(${1 + b / 100}) contrast(${1 + c / 100})`
      : '';

    무대.querySelector('[data-rv-zoom]').textContent = Math.round(z * 100) + '%';
  }

  function _rv배(무대, 배) {
    const z = Math.min(8, Math.max(0.2, Number(무대.dataset.zoom || 1) * 배));
    무대.dataset.zoom = z;
    _rv그리기(무대);
  }

  /* 바퀴를 굴리면 확대ㆍ축소한다. 창 자체가 굴러 내려가지 않게 막는다 —
     그림 위에서 굴렸는데 목록이 지나가면 보던 자리를 잃는다. */
  document.getElementById('rvBody').addEventListener('wheel', (e) => {
    const 무대 = e.target.closest('.rv-stage');
    if (!무대) return;
    e.preventDefault();
    _rv배(무대, e.deltaY < 0 ? 1.12 : 1 / 1.12);
  }, { passive: false });

  /* 키워 놓은 그림을 끌어 옮긴다 */
  (function () {
    let 잡음 = null, x0 = 0, y0 = 0, tx0 = 0, ty0 = 0;

    document.getElementById('rvBody').addEventListener('mousedown', (e) => {
      const 무대 = e.target.closest('.rv-stage');
      if (!무대 || !e.target.matches('img')) return;
      잡음 = 무대; x0 = e.clientX; y0 = e.clientY;
      tx0 = Number(무대.dataset.tx || 0); ty0 = Number(무대.dataset.ty || 0);
      e.preventDefault();
    });

    window.addEventListener('mousemove', (e) => {
      if (!잡음) return;
      잡음.dataset.tx = tx0 + (e.clientX - x0);
      잡음.dataset.ty = ty0 + (e.clientY - y0);
      _rv그리기(잡음);
    });

    window.addEventListener('mouseup', () => { 잡음 = null; });
  })();

  /* 밝기ㆍ명암 손잡이 — 끄는 즉시 보이고, 저장을 눌러야 문서에 남는다 */
  document.getElementById('rvBody').addEventListener('input', (e) => {
    const 무대 = e.target.closest('.rv-stage');
    if (!무대) return;

    if (e.target.matches('[data-rv-bright]')) {
      무대.dataset.bright = e.target.value;
      무대.querySelector('[data-rv-bright-val]').textContent = e.target.value;
    } else if (e.target.matches('[data-rv-contrast]')) {
      무대.dataset.contrast = e.target.value;
      무대.querySelector('[data-rv-contrast-val]').textContent = e.target.value;
    } else {
      return;
    }

    _rv그리기(무대);
  });

  /* 창 안의 단추는 한 자리에서 받는다 — 파일이 스무 장이어도 듣는 이는 하나다 */
  document.getElementById('rvBody').addEventListener('click', async (e) => {
    const 도구 = e.target.closest('[data-rv]');
    if (도구) {
      const 무대 = 도구.closest('.rv-stage');
      const 무엇 = 도구.dataset.rv;

      if (무엇 === 'rotate') { 무대.dataset.rot = (Number(무대.dataset.rot || 0) + 90) % 360; _rv그리기(무대); return; }
      if (무엇 === 'in')     { _rv배(무대, 1.2); return; }
      if (무엇 === 'out')    { _rv배(무대, 1 / 1.2); return; }
      if (무엇 === 'tune')   { 무대.querySelector('.rv-tune').classList.toggle('on'); return; }

      if (무엇 === 'reset') {
        /* 배율ㆍ회전ㆍ위치만 되돌린다. 밝기ㆍ명암은 문서에 적어 둔 값이라
           여기서 함께 지우면 저장해 둔 것을 잃는다 — 그쪽은 ［원본］이 있다. */
        Object.assign(무대.dataset, { zoom: 1, rot: 0, tx: 0, ty: 0 });
        _rv그리기(무대);
        return;
      }

      if (무엇 === 'tune-reset') {
        무대.dataset.bright = 0; 무대.dataset.contrast = 0;
        무대.querySelector('[data-rv-bright]').value = 0;
        무대.querySelector('[data-rv-contrast]').value = 0;
        무대.querySelector('[data-rv-bright-val]').textContent = '0';
        무대.querySelector('[data-rv-contrast-val]').textContent = '0';
        _rv그리기(무대);
        return;
      }

      if (무엇 === 'tune-save') {
        BtnState.loading(도구, '저장 중...');
        try {
          const res = await apiRequest(
            DETAIL_BASE + '/' + encodeURIComponent(_rv.rx) + '/image-tune', 'POST',
            { key: 무대.dataset.key,
              brightness: Number(무대.dataset.bright || 0),
              contrast:   Number(무대.dataset.contrast || 0) });
          if (!res.success) throw new Error(res.message || '저장하지 못했습니다.');

          /* 저장했으면 할 일이 끝났다 — 칸이 계속 떠 있으면 그림을 가린다.
             주문 등록 뷰어도 이렇게 닫는다(_viewer 의 tuneSave). */
          무대.querySelector('.rv-tune').classList.remove('on');
          showToast('밝기ㆍ명암을 저장했습니다. 팩스와 서류에도 적용됩니다.', 'success');
        } catch (err) {
          showToast(err.message || '저장하지 못했습니다.', 'danger', 5000);
        } finally {
          BtnState.reset(도구);
        }
        return;
      }
      return;
    }

    const b = e.target.closest('[data-rq]');
    if (!b) return;

    const id  = b.dataset.file;
    const box = document.querySelector(`[data-rq-box="${id}"]`);

    if (b.dataset.rq === 'open')   { box.style.display = 'block'; box.querySelector('select').focus(); return; }
    if (b.dataset.rq === 'cancel') { box.style.display = 'none'; return; }
    if (b.dataset.rq !== 'send')   return;

    const 사유 = document.querySelector(`[data-rq-reason="${id}"]`).value;
    const 메모 = document.querySelector(`[data-rq-memo="${id}"]`).value.trim();

    if (사유 === 'etc' && !메모) {
      showToast('그 밖의 사유를 고르셨으면 내용을 적어 주십시오.', 'warning');
      return;
    }

    BtnState.loading(b, '보내는 중...');
    try {
      const res = await apiRequest(
        DETAIL_BASE + '/' + encodeURIComponent(_rv.rx) + '/reupload-request', 'POST',
        { file_id: Number(id), reason: 사유, memo: 메모 });

      if (!res.success) throw new Error(res.message || '요청하지 못했습니다.');

      showToast(res.message, res.sent ? 'success' : 'warning', res.sent ? 4000 : 7000);

      /* 표의 그 줄만 고쳐 세운다 — 목록을 통째로 다시 읽지 않는다 */
      const 줄들 = grid.getData();
      const i = 줄들.findIndex(r => r.id === _rv.id);
      if (i >= 0) {
        grid.setValue(i, 'reupload', Number(줄들[i].reupload || 0) + 1);
        grid.setValue(i, 'status', res.status_label ?? '검수 필요');
        grid.setValue(i, 'review', res.status ?? 'review_needed');
      }

      /* 이력을 다시 읽어 방금 남긴 것을 그 자리에서 보여 준다 */
      rvOpen(_rv.rx, _rv.id);
    } catch (err) {
      showToast(err.message || '요청하지 못했습니다.', 'danger', 5000);
    } finally {
      BtnState.reset(b);
    }
  });

  window.rvClose = function () {
    document.getElementById('rvBackdrop').style.display = 'none';
    document.getElementById('rvModal').style.display    = 'none';
    document.getElementById('rvBody').innerHTML = '';   // 그림을 물고 있지 않는다
  };

  window.rvApprove = async function (btn) {
    if (!_rv.rx || _rv.마쳤나) return;

    BtnState.loading(btn, '처리 중...');
    try {
      const res = await apiRequest(DETAIL_BASE + '/' + encodeURIComponent(_rv.rx) + '/approve', 'POST', {});
      if (!res.success) throw new Error(res.message || '검수를 마치지 못했습니다.');

      showToast('검수 완료로 바꿨습니다.', 'success');

      /* 표의 그 줄만 고쳐 세운다 — 목록을 통째로 다시 읽지 않는다 */
      const 줄들 = grid.getData();
      const i = 줄들.findIndex(r => r.id === _rv.id);
      if (i >= 0) {
        grid.setValue(i, 'status', res.status_label ?? '검수 완료');
        grid.setValue(i, 'review', res.status ?? 'approved');
        grid.setValue(i, 'reviewed_at', res.reviewed_at ?? '');
      }

      rvClose();
    } catch (e) {
      showToast(e.message || '검수를 마치지 못했습니다.', 'danger', 5000);
    } finally {
      BtnState.reset(btn);
    }
  };
})();
</script>
@endpush
