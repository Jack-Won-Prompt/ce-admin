@extends('layouts.app')

@section('title', '개인정보동의')
{{-- 시안 342:4037 머리(83×19 · 16/700 #333940)도, 바로 아래 빵부스러기 마지막 조각도 '개인정보동의' 다.
     제목만 '개인정보 수집·이용 동의' 라 한 화면에 두 이름이 보였다. 시안·빵부스러기 쪽으로 맞춘다.
     '개인정보 수집·이용 동의' 라는 낱말은 상세 탭 둘째 카드 제목에 그대로 남는다. --}}
@section('page-title', '개인정보동의')
{{-- 시안 342:4037 빵부스러기는 '홈'(x367) · '-'(x386) · '개인정보동의'(x400) 로 구분자가 하이픈이다. --}}
@section('breadcrumb', '홈 - 개인정보동의')

@push('styles')
<style>
  /* 결과바 '선택 N건' — 전역에 없어 화면마다 정의한다.
     Figma 342:4037: 13px/500 · lh21 · grayscale/600, 숫자만 primary/400 */

  /* 검색 카드 9열 그리드의 열 사이 16 은 이제 전역 .ds-filter-fields 가 갖는다
     (시안 전수 27장이 16, 표준 레이아웃 두 장 포함 — 여기서 덮을 필요가 없어졌다).
     열폭 (1384 - 16×8) / 9 = 139.6 → span-2 = 295.1 로 시안 검색어·기간 295 와 맞는다. */

  /* 패널 탭(조회 결과 / 상세 내용) — Figma 342:4037: 카드 안 상단 h44 · pad 0/16 · 하단 1px --border.
     비활성 탭 글자색은 시안이 #656C74(gray-600)인데 전역 .pnl-tab 은 var(--text-muted)(gray-400 #999EA4)라
     두 단계 연하다. 전역을 바꾸면 다른 화면에 함께 번지므로 이 화면에서만 덮는다(globalCssNeeded 참고). */
  .ds-grid-card .pnl-tab { color: var(--gray-600); }
  .ds-grid-card .pnl-tab:hover,
  .ds-grid-card .pnl-tab.active { color: var(--primary); }

  /* 이름 옆 유형 — 시안 342:4519 Frame 48101480 은 배지가 아니라 14px/700 · lh22 · #83888F 평문이다
     ('김스트' 16/700 gap 8 '카테터' 14/700). 낱말과 클래스명(JS 가 붙인다)은 그대로 두고 규격만 시안에 맞춘다.
     원래 배지 규격(r6 · pad 2/6 · 11px/500 · lh18 · primary-100/gray-100 바탕)이었다. */
  .badge-type { display:inline-flex; align-items:center; font-size:14px; font-weight:700; line-height:22px; color:var(--gray-500); }
  .badge-type.catheter,
  .badge-type.stoma { background:none; padding:0; border-radius:0; color:var(--gray-500); }

  /* 상세 탭은 .ds-grid-card(overflow:hidden · 높이가 뷰포트에 묶임) 안으로 들어왔다.
     내용이 카드보다 길면 잘려서 볼 수가 없으므로 이 블록만 스스로 스크롤하게 한다.
     (카드 높이가 내용보다 넉넉하면 스크롤바는 나오지 않는다) */
  /* 시안 342:4519 상세 본문은 pad 16 안에서 제목 줄부터 바로 시작한다(Frame 48101520 1568×705 · pad 16 · gap 12).
     '조회결과로' 버튼은 시안에 없지만 개발 기능이라 지우지 않고 같은 pad 16 줄의 오른쪽 끝으로 옮긴다. */
  #pcTabDetail { min-height:0; overflow-y:auto; position:relative; }
  .pc-detail-back { position:absolute; top:16px; right:16px; z-index:1; }

  /* ── 상세 내용 탭 — Figma 342:4519 ──
     제목 줄(1536×26)은 전체 폭, 그 아래 카드 3장이 한 줄(504×384 균등 · gap 12).
     시안은 세 카드 아래끝이 맞는다 — align-items 를 stretch(기본)로 둬 가장 긴 카드에 높이를 맞춘다. */
  .pc-memo-btn {
    border:1px solid var(--gray-200); background:var(--gray-0); border-radius:6px;
    padding:1px 8px; font-size:11px; font-weight:700; line-height:18px;
    color:var(--text-secondary); cursor:pointer; flex-shrink:0;
  }
  .pc-memo-btn:hover { border-color:var(--primary); color:var(--primary); }

  /* 관리자 메모 팝오버 — 누른 자리 옆에서 바로 고친다.
     화면을 덮는 모달은 어느 행을 고치는 중인지 가려서, 목록을 보며 적을 수가 없다. */
  .pc-memo-pop {
    display:none; position:absolute; z-index:900; width:340px;
    background:var(--bg-card); border:1px solid var(--primary);
    border-radius:var(--radius-lg); box-shadow:0 8px 32px rgba(0,0,0,.18);
  }
  .pc-memo-pop.open { display:block; }
  .pc-memo-pop-hd {
    background:var(--primary); border-radius:var(--radius-lg) var(--radius-lg) 0 0;
    padding:8px 12px; display:flex; align-items:center; gap:8px;
  }
  .pc-memo-pop-hd span { font-size:12px; font-weight:700; color:#fff; flex:1; }
  .pc-memo-pop-hd button { background:none; border:none; color:#fff; font-size:15px; line-height:1; cursor:pointer; }
  .pc-memo-pop-bd { padding:10px 12px; }
  .pc-memo-pop-bd textarea {
    width:100%; min-height:96px; resize:vertical; border:1px solid var(--border);
    border-radius:6px; padding:8px 9px; font-size:12px; font-family:inherit; line-height:1.6;
  }
  .pc-memo-pop-bd textarea:focus { outline:none; border-color:var(--primary); }
  .pc-memo-pop-ft {
    padding:0 12px 10px; display:flex; align-items:center; gap:6px;
  }
  .pc-memo-pop-ft .hint { flex:1; font-size:10px; color:var(--text-muted); }
  .pc-memo-pop-who { padding:0 12px 8px; font-size:10px; color:var(--text-muted); }

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
  /* 제목 줄 — 시안 342:4519 Frame 48101627/48101480(87×26):
     [이름 42×26 · 16/700 lh26 gray-1000] gap 8 [유형 37×22 · 14/700 lh22 gray-500] 순서다.
     유형 글자 규격은 위 .badge-type 에서 시안대로 맞췄다. */
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
{{-- 유형 칩 — Figma 342:4037: h31 · r999 · pad 6/10 · 12px/700, 건수 배지 16×16 정원 --}}
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}


{{-- 검색 필터 — Figma 342:4037 Frame 48101549: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래.
     9열 그리드 gap 16(위 스타일에서 덮음) → 열폭 139.6.
     시안은 검색어 span-2 = 295.1(시안 295) · 기간 묶음 311.1~606.2(시안 311~606) 로 바깥선이 맞는다.

     시안은 기간을 라벨 하나('기간') + [135] ~ [135] 한 칸으로 그렸지만, 그렇게 합치면
     '시작일'·'종료일' 두 낱말이 화면에서 사라진다(keep.mjs 가 손실로 잡는다).
     낱말 삭제 금지 규칙이 우선이라 1열씩 두 칸을 그대로 두고 바깥 폭만 시안에 맞췄다.
     안쪽 나눔은 시안 135/8/'~'9/8/135, 구현 139.6/16/139.6 이다 — 디자이너 확인이 필요하다.

     '검색어' 라벨도 같은 이유다. 시안 라벨은 '검색어'(34×21) 뿐이지만
     '(성명/연락처/이메일)' 을 떼면 그 낱말이 화면에서 없어진다(플레이스홀더는 'ㆍ' 로 이어진 다른 문자열이다). --}}
<form method="GET" action="{{ route('privacy-consents.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 종류가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">종류</label>
      <select name="type" class="form-control form-select" onchange="this.form.submit()">
        @php $curT = request('type', 'all'); @endphp
        <option value="all"      {{ $curT === 'all'      ? 'selected' : '' }}>전체 ({{ $counts['all'] }})</option>
        <option value="catheter" {{ $curT === 'catheter' ? 'selected' : '' }}>카테터 ({{ $counts['catheter'] }})</option>
        <option value="stoma"    {{ $curT === 'stoma'    ? 'selected' : '' }}>장루 ({{ $counts['stoma'] }})</option>
      </select>
    </div>
    {{-- 시안 342:4037 Frame 48101591 — 라벨은 '검색어' 한 마디고, 안내는 입력 안
         placeholder('성명ㆍ연락처ㆍ이메일')가 맡는다. 295×61. --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="성명ㆍ연락처ㆍ이메일">
    </div>
    {{-- 시안 342:4037 Frame 48101593 — 기간은 두 칸이 아니라 한 칸(295×61)이다.
         안쪽 Frame 48101490 이 [날짜 135] ~ [날짜 135] 로 gap 8 씩 벌어진다.
         name 은 from·to 그대로라 컨트롤러 질의는 손대지 않는다. --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="from" value="{{ $from }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    {{-- 시안 342:4037 Frame 48101589 — 초기화(60×32)는 검색 왼쪽에 늘 있다.
         유형 칩만 유지한 채 같은 라우트로 되돌아가는 링크라 조건만 걷었다. --}}
    <a href="{{ route('privacy-consents.index', request()->only('type')) }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__pcGrid?.downloadExcel()">엑셀 저장</button>
    <a href="{{ route('privacy-consents.export', request()->query()) }}" class="ds-btn">
      <i class="bx bx-download"></i> 엑셀(CSV) 다운로드
    </a>
  </div>
</form>

{{-- Figma 342:4037 — 흰 카드(r12) 안에 탭바와 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 뷰 전환 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단 --}}
    <div class="pnl-tabs">
      <button type="button" id="pcTabBtnList" class="pnl-tab active" onclick="pcShowTab('list')">
        <i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ number_format(count($gridData)) }}</b>건)</span></button>
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
    footer: false,          // 시안에 하단 상태바가 없다. 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다
    // 아래 폭은 옛 시안(266:66) 실측이다 — 체크 40 · No 60 · 주소 400 · 나머지 153 (합 1571 ≒ 카드 1568).
    // 새 시안 342:4037 은 컬럼 구성과 폭이 다르다 —
    //   체크 40 · No 60 · 구분 100 · 유형 62 · 성명 62 · 연락처 120 · 이메일 180 · 주소 360 ·
    //   보험 80 · 지원 자격 80 · 필수동의 72 · 마케팅 72 · 제출일시 140 · 관리자 메모 140 (합 1568).
    // width 는 엑셀 내려받기 열 폭으로도 나가고 '구분'·'관리자 메모' 는 데이터원 자체가 없어서
    // (privacy_consents 에 대응 컬럼 0건) 퍼블리셔가 손대지 않았다. 로직 담당 확인이 필요하다.
    // 시안은 머리글·본문 모두 왼쪽 정렬이라 align:'center' 를 두지 않는다.
    columns: [
      { header: '구분',     name: 'source',    width: 120, sortable: true },
      { header: '유형',     name: 'type',      width: 110, sortable: true },
      { header: '성명',     name: 'name',      width: 130, sortable: true },
      { header: '연락처',   name: 'phone',     width: 140 },
      { header: '이메일',   name: 'email',     width: 170 },
      { header: '주소',     name: 'address',   width: 320 },
      { header: '보험',     name: 'insurance_col',       width: 120, sortable: true },
      { header: '지원 자격', name: 'support_qualify_col', width: 140, sortable: true },
      { header: '필수동의', name: 'required',  width: 110, sortable: true },
      { header: '마케팅',   name: 'marketing', width: 100 },
      // 머리 낱말은 시안 342:4037 표 머리 실측 그대로다 — '제출일시'·'지원 자격'·'관리자 메모'.
      // name 은 그대로라 데이터·엑셀 열 키는 손대지 않았다.
      { header: '제출일시',   name: 'submitted', width: 150, sortable: true },
      // 메모는 그리드에서 바로 고친다 — 상세탭까지 들어갔다 나올 일이 아니다.
      { header: '관리자 메모', name: 'admin_memo', width: 220, sortable: false,
        renderer: (v, row) => {
          const wrap = document.createElement('div');
          wrap.style.cssText = 'display:flex;align-items:center;gap:6px;width:100%;';
          const txt = document.createElement('span');
          txt.style.cssText = 'flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
          txt.textContent = v || '';
          if (!v) { txt.style.color = 'var(--text-muted)'; txt.textContent = '—'; }
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'pc-memo-btn';
          btn.textContent = v ? '수정' : '입력';
          btn.addEventListener('click', (e) => { e.stopPropagation(); pcEditMemo(row, btn); });
          wrap.append(txt, btn);
          return wrap;
        } },
    ],
    data: @json($gridData),
  });
  window.__pcGrid = grid;
  window.__privacyconsentsGrid = grid;          // 결과바 버튼이 부르는 인스턴스
  window.dsBindSelCount(grid, 'pcSelCount');    // 결과바 '선택 N건' 표시 연결

  /* ── 관리자 메모 ──────────────────────────────────────────────
     누른 자리 옆에 붙는 팝오버로 고친다. 화면을 덮는 모달은 어느 행을 고치는 중인지 가려서,
     옆 행의 이름·전화를 보며 적을 수가 없다. 메모는 그 목록을 보며 쓰는 글이다. */
  let pcMemoRow = null;

  const pcPop = (() => {
    const el = document.createElement('div');
    el.className = 'pc-memo-pop';
    el.innerHTML = `
      <div class="pc-memo-pop-hd">
        <span>관리자 메모</span>
        <button type="button" data-act="close" aria-label="닫기">×</button>
      </div>
      <div class="pc-memo-pop-who" data-who></div>
      <div class="pc-memo-pop-bd">
        <textarea data-input maxlength="1000" placeholder="이 동의 건에 남길 메모"></textarea>
      </div>
      <div class="pc-memo-pop-ft">
        <span class="hint">Ctrl+Enter 저장 · Esc 닫기</span>
        <button type="button" class="btn btn-outline btn-sm" data-act="close">취소</button>
        <button type="button" class="btn btn-primary btn-sm" data-act="save">저장</button>
      </div>`;
    document.body.appendChild(el);
    return el;
  })();

  const pcInput = pcPop.querySelector('[data-input]');

  function pcCloseMemo() {
    pcPop.classList.remove('open');
    pcMemoRow = null;
  }

  /** 누른 버튼 아래에 붙이되, 화면 밖으로 나가면 안쪽으로 당긴다 */
  function pcPlaceMemo(anchor) {
    const r = anchor.getBoundingClientRect();
    const w = 340, margin = 8;
    let left = window.scrollX + r.left;
    let top  = window.scrollY + r.bottom + 6;

    if (left + w > window.scrollX + document.documentElement.clientWidth - margin) {
      left = window.scrollX + document.documentElement.clientWidth - w - margin;
    }
    // 아래로 넘치면 버튼 위쪽에 세운다
    if (r.bottom + pcPop.offsetHeight + margin > document.documentElement.clientHeight) {
      top = window.scrollY + r.top - pcPop.offsetHeight - 6;
    }
    pcPop.style.left = Math.max(margin, left) + 'px';
    pcPop.style.top  = Math.max(margin, top) + 'px';
  }

  window.pcEditMemo = function (row, anchor) {
    pcMemoRow = row;
    pcInput.value = row.admin_memo || '';
    pcPop.querySelector('[data-who]').textContent =
      [row.name, row.phone].filter(Boolean).join(' · ') || '';

    pcPop.classList.add('open');
    pcPlaceMemo(anchor || document.activeElement || document.body);
    pcInput.focus();
    pcInput.setSelectionRange(pcInput.value.length, pcInput.value.length);
  };

  async function pcSaveMemo() {
    if (!pcMemoRow) return;
    const row = pcMemoRow;
    const next = pcInput.value;

    const res = await fetch(`{{ url('/privacy-consents') }}/${row.id}/memo`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify({ admin_memo: next }),
    });
    if (!res.ok) { showToast('메모 저장에 실패했습니다.', 'danger'); return; }

    const data = await res.json();
    // 그리드 데이터를 직접 고치고 그 행만 다시 그린다 — 화면을 새로 읽지 않는다.
    const all = grid.getData();
    const idx = all.findIndex(r => r.id === row.id);
    if (idx >= 0) {
      all[idx].admin_memo = data.admin_memo;
      grid.setData ? grid.setData(all) : location.reload();
    }
    // 상세 탭을 보고 있었다면 그쪽 값도 같이 맞춘다
    if (window.__pcDetailRow && window.__pcDetailRow.id === row.id) {
      window.__pcDetailRow.admin_memo = data.admin_memo;
      window.pcRenderDetail?.(window.__pcDetailRow);
    }
    pcCloseMemo();
    showToast('메모를 저장했습니다.', 'success');
  }

  pcPop.addEventListener('click', (e) => {
    const act = e.target.dataset?.act;
    if (act === 'close') pcCloseMemo();
    if (act === 'save')  pcSaveMemo();
  });
  pcInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { e.preventDefault(); pcCloseMemo(); }
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); pcSaveMemo(); }
  });
  // 바깥을 누르면 닫는다. 팝오버를 연 버튼을 다시 누른 경우는 그 핸들러가 다시 연다.
  document.addEventListener('mousedown', (e) => {
    if (!pcPop.classList.contains('open')) return;
    if (pcPop.contains(e.target) || e.target.closest?.('.pc-memo-btn')) return;
    pcCloseMemo();
  });
  window.addEventListener('resize', () => pcCloseMemo());

  // ── 탭 전환 ──
  window.pcShowTab = function (which) {
    document.getElementById('pcTabList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pcTabDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pcTabBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pcTabBtnDetail').classList.toggle('active', which === 'detail');
  };

  // 메모 팝오버가 저장 뒤 상세 탭을 다시 그릴 때 부른다
  window.pcRenderDetail = (r) => {
    const body = document.getElementById('pcDetailBody');
    if (body) body.innerHTML = pcRenderDetail(r);
  };

  // ── 더블클릭 → 상세보기 탭 전환 + 상세 렌더 (페이지 이동 없음) ──
  document.getElementById('pcGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const ri  = parseInt(cell.dataset.rowIndex, 10);
    const row = grid.getData()[ri];
    if (!row) return;
    // 메모를 고치면 이 값도 같이 맞춰야 해서 지금 보고 있는 행을 남긴다
    window.__pcDetailRow = row;
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
  // 시안 342:4519 — '동의함 (필수)' 는 괄호까지 통째로 13/500 primary,
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
      drow('구분', val(r.source)) +
      drow('작성일', val(r.submitted_full || r.submitted)) +
      drow('관리자메모', (r.admin_memo ? esc(r.admin_memo).replace(/\n/g, '<br>') : '—')
        + ` <button type="button" class="pc-memo-btn" onclick='pcEditMemo(${JSON.stringify(r).replace(/'/g, "&#39;")}, this)'>`
        + (r.admin_memo ? '수정' : '입력') + '</button>') +
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
