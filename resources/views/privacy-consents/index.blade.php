@extends('layouts.app')

@section('title', '개인정보동의')
@section('page-title', '개인정보 수집·이용 동의')
@section('breadcrumb', '홈 / 개인정보동의')

@push('styles')
<style>
  /* 결과바 '선택 N건' — 전역에 없어 화면마다 정의한다.
     Figma 266:66: 13px/500 · lh21 · grayscale/600, 숫자만 primary/400 */

  /* 패널 탭(조회 결과 / 상세 내용) — Figma 266:66: 카드 안 상단 h44 · pad 0/16 · 하단 1px --border */

  /* 유형 배지 — 배지 규격(r6 · pad 2/6 · 11px/500 · lh18).
     원래 하늘색·초록 하드코딩이었다. 초록은 이 디자인에 없어 DS 토큰(primary-100/600 · gray-100/600)으로 바꿨다. */
  .badge-type { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }
  .badge-type.catheter { background:var(--primary-100); color:var(--primary-600); }
  .badge-type.stoma    { background:var(--gray-100);    color:var(--gray-600); }

  /* 상세 탭은 .ds-grid-card(overflow:hidden · 높이가 뷰포트에 묶임) 안으로 들어왔다.
     내용이 카드보다 길면 잘려서 볼 수가 없으므로 이 블록만 스스로 스크롤하게 한다.
     (카드 높이가 내용보다 넉넉하면 스크롤바는 나오지 않는다) */
  /* 시안 266:527 상세 본문은 pad 16 안에서 제목 줄부터 바로 시작한다(Frame 48101520 1568×846 · pad 16 · gap 12).
     '조회결과로' 버튼은 시안에 없지만 개발 기능이라 지우지 않고 같은 pad 16 줄의 오른쪽 끝으로 옮긴다. */
  #pcTabDetail { min-height:0; overflow-y:auto; position:relative; }
  .pc-detail-back { position:absolute; top:16px; right:16px; z-index:1; }

  /* ── 상세 내용 탭 — Figma 266:527 ──
     제목 줄(1536×26)은 전체 폭, 그 아래 카드 3장이 한 줄(504×384 균등 · gap 12).
     시안은 세 카드 아래끝이 맞는다 — align-items 를 stretch(기본)로 둬 가장 긴 카드에 높이를 맞춘다. */
  #pcDetailBody { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; }
  #pcDetailBody > :first-child { grid-column:1 / -1; }
  @media (max-width:1200px) { #pcDetailBody { grid-template-columns:1fr; } }

  /* 상세 카드 — 504×384 · r12 · pad 12/16 · bd 1px --gray-200 · 그림자 없음.
     시안 Frame 48101496: pad 12 → 제목 프레임 28 → gap 12 → 항목 묶음(gap 8) → pad 12.
     아래 h3 는 높이 28 + margin 4, 카드 gap 8 과 더해 제목↔첫 상자 12 가 된다(12+28+12 = 52). */
  .detail-card { display:flex; flex-direction:column; gap:8px; min-width:0;
    background:var(--gray-0); border:1px solid var(--gray-200); border-radius:12px; padding:12px 16px; }
  .detail-card h3 { margin:0 0 4px; padding:0; border:none; display:flex; align-items:center; gap:8px;
    height:28px; font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000); }
  /* 항목 한 칸 — r8 · pad 12 · gap 8 · bg --gray-100, 라벨 위 · 값 아래 */
  .drow { display:flex; flex-direction:column; gap:8px; min-width:0;
    padding:12px; border-radius:8px; background:var(--gray-100); }
  .drow .k { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  /* 값은 상자 안폭 전체를 쓰고 오른쪽 끝에 붙는다 (전폭 448 · 2열 208) */
  .drow .v { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-1000); word-break:break-word; text-align:right; }
  /* 한 줄에 상자 두 개 — 시안 Frame 48101685 472×74 · gap 8 (칸마다 232) */
  .dpair { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; min-width:0; }
  /* 제목 줄 — 시안 Frame 48101627: [이름 16/700 lh26 gray-1000] gap 8 [유형] 순서다.
     유형은 시안에서 배지가 아니라 14/700 gray-500 글자지만, 배지는 개발 자산이라 지우지 않고
     순서만 시안대로 이름 뒤로 옮겼다. */
  .pc-detail-name { font-size:16px; font-weight:700; line-height:26px; color:var(--gray-1000); }
  /* 결과바 안내문 강조 — 시안은 굵기가 아니라 색(primary-700)으로 강조한다.
     전역 .ds-grid-hint b 규칙이 없어 이 화면에서만 맞춘다(globalCssNeeded 참고). */
  .ds-grid-hint b { font-weight:500; color:var(--primary-700); }
  .agree-yes { color:var(--primary); font-weight:500; }
  .agree-no  { color:var(--alert-500); font-weight:500; }
  .pc-detail-empty { color:var(--text-muted); font-size:13px; line-height:21px; text-align:center; padding:60px 20px;
    background:var(--gray-0); border:1px dashed var(--border); border-radius:12px; }

  /* 아래 .pc-table / .req-* 는 지금 마크업 사용처가 없다(목록은 wwGrid 로 대체됐다).
     개발 자산이라 남기되, 하드코딩 흰색·연회색과 --success 만 DS 토큰으로 바꿨다. */
  .pc-table { width:100%; border-collapse:collapse; background:var(--gray-0); }
  .pc-table th, .pc-table td { padding:11px 12px; border-bottom:1px solid var(--border); font-size:13px; text-align:left; }
  .pc-table th { background:var(--gray-50); font-weight:700; color:var(--text-secondary); font-size:12px; }
  .pc-table tr:hover td { background:var(--gray-50); }
  .req-ok { color:var(--primary); font-weight:700; }
  .req-no { color:var(--alert-500); font-weight:700; }
</style>
@endpush

@section('content')
{{-- 유형 칩 — Figma 266:66: h31 · r999 · pad 6/10 · 12px/700, 건수 배지 16×16 정원 --}}
<div class="ds-chips">
  <a href="{{ route('privacy-consents.index') }}" class="ds-chip {{ (request('type','all')==='all')?'active':'' }}">전체 <span class="ds-chip-count">{{ $counts['all'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'catheter']) }}" class="ds-chip {{ request('type')==='catheter'?'active':'' }}">카테터 <span class="ds-chip-count">{{ $counts['catheter'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'stoma']) }}" class="ds-chip {{ request('type')==='stoma'?'active':'' }}">장루 <span class="ds-chip-count">{{ $counts['stoma'] }}</span></a>
</div>

{{-- 검색 필터 — Figma 266:66: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래.
     시안은 검색어 2열(295px) + 기간 2열(135 ~ 135). 기간은 '시작일/종료일' 이름을 지우지 않으려고
     9열 그리드에서 1열씩 두 칸으로 뒀다(143+12+143=298 ≒ 시안 295). --}}
<form method="GET" action="{{ route('privacy-consents.index') }}" class="ds-filter-card">
  <input type="hidden" name="type" value="{{ request('type','all') }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어 (성명/연락처/이메일)</label>
      <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="성명ㆍ연락처ㆍ이메일">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">시작일</label>
      <input type="date" name="from" value="{{ $from }}" class="form-control">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">종료일</label>
      <input type="date" name="to" value="{{ $to }}" class="form-control">
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request()->hasAny(['search','from','to']))
      <a href="{{ route('privacy-consents.index', request()->only('type')) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

{{-- Figma 266:66 — 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 탭바와 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ number_format(count($gridData)) }}</b>건</span>
      <span class="ds-grid-sel">선택 <b id="pcSelCount">0</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      {{-- 안내문 앞 12×12 alert-circle 은 전역 .ds-grid-hint::before 가 이미 그린다.
           마크업에 아이콘을 또 두면 두 개가 되므로 기준 구현(patients·orders)처럼 두지 않는다. --}}
      <span class="ds-grid-hint">
        행을 <b>더블클릭</b>하면 상세내용 탭으로 전환되어 상세 내용을 확인합니다.
      </span>
      {{-- 그리드 내장 툴바(엑셀 저장)를 여기로 옮겼다. 동작은 downloadExcel() 그대로. --}}
      <button type="button" class="ds-btn" onclick="window.__pcGrid?.downloadExcel()">엑셀 저장</button>
      {{-- 서버 CSV 내려받기는 그리드 엑셀과 다른 기능이라 함께 남긴다. --}}
      <a href="{{ route('privacy-consents.export', request()->query()) }}" class="ds-btn">
        <i class="bx bx-download"></i> 엑셀(CSV) 다운로드
      </a>
    </div>
  </div>

  <div class="ds-grid-card">
    {{-- 뷰 전환 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단 --}}
    <div class="pnl-tabs">
      <button type="button" id="pcTabBtnList" class="pnl-tab active" onclick="pcShowTab('list')">
        <i class="bx bx-list-ul"></i> 조회 결과
      </button>
      <button type="button" id="pcTabBtnDetail" class="pnl-tab" onclick="pcShowTab('detail')">
        <i class="bx bx-detail"></i> 상세 내용
      </button>
    </div>

    {{-- ── 조회 결과 탭 ── --}}
    <div id="pcTabList">
      <div id="pcGrid"></div>
    </div>

    {{-- ── 상세 내용 탭 — 같은 카드 안 ── --}}
    <div id="pcTabDetail" style="display:none;padding:16px;">
      <div class="pc-detail-back">
        <button type="button" class="ds-btn" onclick="pcShowTab('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
      </div>
      <div id="pcDetailBody">
        <div class="pc-detail-empty">리스트 탭에서 행을 더블클릭하면 상세 내용이 여기에 표시됩니다.</div>
      </div>
    </div>
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}
@endsection

@push('scripts')
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('pcGrid'),
    height: 'fit',
    editable: false,        // 읽기전용 표시
    rowCheckbox: true,
    rowNumber: true,
    toolbar: false,         // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일)
    summary: false,
    footer: false,          // 시안에 하단 상태바가 없다. 전체·선택 건수는 상단 결과바에 있다
    // 컬럼 폭·정렬은 Figma 266:66 실측 — 체크 40 · No 60 · 주소 400 · 나머지 153 (합 1571 ≒ 카드 1568).
    // 시안은 머리글·본문 모두 왼쪽 정렬이라 align:'center' 를 두지 않는다.
    columns: [
      { header: '유형',     name: 'type',      width: 153, sortable: true },
      { header: '성명',     name: 'name',      width: 153, sortable: true },
      { header: '연락처',   name: 'phone',     width: 153 },
      { header: '이메일',   name: 'email',     width: 153 },
      { header: '주소',     name: 'address',   width: 400 },
      { header: '필수동의', name: 'required',  width: 153, sortable: true },
      { header: '마케팅',   name: 'marketing', width: 153 },
      { header: '제출일시', name: 'submitted', width: 153, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__pcGrid = grid;
  window.__privacyconsentsGrid = grid;          // 결과바 버튼이 부르는 인스턴스
  window.dsBindSelCount(grid, 'pcSelCount');    // 결과바 '선택 N건' 표시 연결

  // ── 탭 전환 ──
  window.pcShowTab = function (which) {
    document.getElementById('pcTabList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pcTabDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pcTabBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pcTabBtnDetail').classList.toggle('active', which === 'detail');
  };

  // ── 더블클릭 → 상세보기 탭 전환 + 상세 렌더 (페이지 이동 없음) ──
  document.getElementById('pcGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const ri  = parseInt(cell.dataset.rowIndex, 10);
    const row = grid.getData()[ri];
    if (!row) return;
    document.getElementById('pcDetailBody').innerHTML = pcRenderDetail(row);
    window.pcShowTab('detail');
  });

  // ── 상세 HTML 생성(기존 상세 화면 내용을 인페이지로) ──
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }
  function val(v) { return (v === '' || v == null) ? '-' : esc(v); }
  function agree(v) {
    if (v === '동의함') return '<span class="agree-yes">동의함</span>';
    if (v)            return '<span class="agree-no">' + esc(v) + '</span>';
    return '<span style="color:var(--gray-400);">-</span>';
  }
  function drow(k, vHtml) { return '<div class="drow"><span class="k">' + k + '</span><span class="v">' + vHtml + '</span></div>'; }
  // 시안 266:527 — '동의함 (필수)' 는 괄호까지 통째로 13/500 primary,
  // '동의함 (선택)' 은 '(선택)' 부분만 13/500 gray-500 이다(값 글자 규격과 같은 13px).
  const REQ = ' <span style="font-size:13px;font-weight:500;line-height:21px;color:var(--primary);">(필수)</span>';
  const OPT = ' <span style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-500);">(선택)</span>';

  function pcRenderDetail(r) {
    const isStoma = r.type_raw === 'stoma';

    // 신청자 정보 — 시안 Frame 48101528(472×320): [성명|연락처] 한 줄, 이메일·주소 전폭,
    // [보험|지원 자격] 한 줄. 짝은 .dpair 로 감싼다(조건부 '연락처2' 가 끼어도 짝이 어긋나지 않는다).
    let applicant =
      '<div class="dpair">' +
        drow('성명', val(r.name)) +
        drow('연락처', val(r.phone)) +
      '</div>' +
      (r.phone2 ? drow('연락처2', esc(r.phone2)) : '') +
      drow('이메일', val(r.email)) +
      drow('주소', val(r.address));
    if (isStoma) {
      const stoma = [r.stoma_type, r.stoma_kind].filter(Boolean).join(' ');
      applicant +=
        drow('생년월일', val(r.birth)) +
        drow('사용 제품', val(r.product)) +
        drow('수술 병원', val(r.hospital)) +
        drow('수술일자', val(r.surgery_date)) +
        drow('장루 타입', stoma ? esc(stoma) : '-');
    } else {
      applicant +=
        '<div class="dpair">' +
          drow('보험', val(r.insurance)) +
          drow('지원 자격', val(r.support_qualify)) +
        '</div>';
    }

    // 동의 내역
    let consent = drow('일반정보 수집·이용', agree(r.agree_general) + REQ);
    if (isStoma) {
      consent +=
        drow('민감정보 수집·이용', agree(r.agree_sensitive) + REQ) +
        drow('일반 마케팅', agree(r.agree_marketing) + OPT) +
        drow('민감정보 마케팅', agree(r.agree_marketing_sensitive) + OPT) +
        drow('일반 제3자 제공', agree(r.agree_third_party) + OPT) +
        drow('민감 제3자 제공', agree(r.agree_third_sensitive) + OPT);
    } else {
      consent +=
        drow('제3자 제공', agree(r.agree_third_party) + REQ) +
        drow('마케팅 수집·이용', agree(r.agree_marketing) + OPT);
    }

    // 제출 정보
    const submit =
      drow('제출일시', val(r.submitted_full || r.submitted)) +
      drow('IP', val(r.ip)) +
      // User-Agent 도 다른 값과 같은 규격(13/500 · lh21 · gray-1000 · 오른쪽 정렬)이다.
      // 공백 없는 긴 문자열이라 줄바꿈만 break-all 로 둔다(시안 상자 95 = 12+21+8+42+12).
      drow('User-Agent', '<span style="word-break:break-all;">' + val(r.user_agent) + '</span>');

    return '' +
      // 시안 Frame 48101627(1536×26) — 이름이 먼저, 유형이 뒤(gap 8)
      '<div style="display:flex;gap:8px;align-items:center;">' +
        '<span class="pc-detail-name">' + esc(r.name) + '</span>' +
        '<span class="badge-type ' + (isStoma ? 'stoma' : 'catheter') + '">' + esc(r.type) + '</span>' +
      '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-user"></i> 신청자 정보</h3>' + applicant + '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-check-shield"></i> 개인정보 수집·이용 동의</h3>' + consent + '</div>' +
      '<div class="detail-card"><h3><i class="bx bx-info-circle"></i> 제출 정보</h3>' + submit + '</div>';
  }
})();
</script>
@endpush
