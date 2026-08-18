{{-- resources/views/dispatch/index.blade.php --}}
@extends('layouts.app')

@section('title', '발송/발행 내역')
@section('page-title', '발송/발행 내역')
{{-- 시안 352:3206 — 빵부스러기는 '홈 - 발송/발행 내역' 이다('홈' x367 · '-' x386 · 화면명 x400).
     구분자는 하이픈이고 화면명도 제목과 같은 슬래시라 한 화면 안에서 낱말이 갈리지 않는다. --}}
@section('breadcrumb', '홈 - 발송/발행 내역')

@push('styles')
<style>
  /* 패널 탭(조회 결과/상세 내용) — Figma 탭바: h44 · pad 0/16 · gap 16 · 하단 1px border,
     탭 자체는 pad 0/8 · 13/500 · lh21 · 활성 밑줄 1px primary.
     카드 안 상단에 들어가므로 바깥 여백(margin-bottom)은 두지 않는다. */

  /* 비활성 탭 글자색 — 시안 352:3206 은 #656C74(gray-600)인데 전역 .pnl-tab 은
     var(--text-muted)(gray-400 #999EA4)라 두 단계 연하다. 전역을 바꾸면 다른 화면까지
     함께 번지므로 이 화면에서만 덮는다(globalCssNeeded 참고). 활성은 #28798B 로 이미 맞다. */
  .ds-grid-card .pnl-tab { color: var(--gray-600); }
  .ds-grid-card .pnl-tab:hover,
  .ds-grid-card .pnl-tab.active { color: var(--primary); }

  /* '조회결과로' 버튼은 시안에 없다. 상세 내용 패널 맨 위에 두면 본문이 44px(버튼 32 + 여백 12)
     밀려 시안(탭줄 바로 아래 pad 16 에서 TB 머리줄 시작)과 어긋난다.
     지우지 않고 탭줄 오른쪽 끝으로 옮기고, 상세 내용 탭이 활성일 때만 보이게 한다.
     (활성 표시는 pnlShow() 가 붙이는 .active 클래스를 그대로 읽는다 — JS 는 건드리지 않는다) */
  .pnl-tabs .pnl-back { margin-left: auto; align-self: center; display: none; }
  .pnl-tabs #pnlBtnDetail.active ~ .pnl-back { display: inline-flex; }

  /* 결과바 '선택 N건' — 13/500 · lh21 · gray-600, 숫자만 primary-400 */

  /* 상세 내용 탭 안내 박스 — 13/400 · lh21 · 카드와 같은 radius 12 */

  /* 상세 내용 패널이 .ds-grid-card(overflow:hidden · flex:1 · min-height:0) 안으로 들어오면서
     주입되는 상세(dispatch/show.blade.php, 자체 스크롤 없음)가 카드 높이를 넘으면
     아래쪽이 잘리고 스크롤도 안 된다. 카드가 남긴 높이를 채우고 그 안에서 스크롤시킨다.
     (basis auto 라 카드가 내용 높이로 잡히는 경우에도 접히지 않는다.) */
  #pnlDetail { flex:1 1 auto; min-height:0; overflow-y:auto; }

  /* 아래 세 묶음은 지금 이 화면 마크업에서 쓰이지 않지만 개발 자산이라 남긴다.
     (상세는 dispatch/show.blade.php 가 자기 스타일을 함께 실어 주입된다.)
     쓰게 될 때를 대비해 값만 시안 규격(글자 10~16px · 400/500/700 · DS 토큰)에 맞춰 뒀다. */
  .empty-state { text-align:center; padding:56px 24px; color:var(--text-muted); }
  .empty-state i { font-size:16px; margin-bottom:12px; display:block; opacity:.3; }
  .empty-state p { font-size:13px; font-weight:400; line-height:21px; margin:0; }
  .mono { font-family:monospace; font-size:12px; line-height:19px; color:var(--primary); font-weight:700; }
  .sub-text { font-size:11px; font-weight:500; line-height:18px; color:var(--text-muted); margin-top:2px; }

  /* ════════════════════════════════════════════════════════════════════════
     상세 내용 탭 — 주입되는 dispatch/show.blade.php 프래그먼트 규격
     시안 352:4165(가상계좌 발행) · 381:5745(세금계산서 발행) · 381:6280(현금영수증 발행)
     ────────────────────────────────────────────────────────────────────────
     상세는 fetch 로 받은 show.blade.php 조각을 #pnlDetailContent 에 innerHTML 로 넣는다.
     그 조각에는 show.blade.php 의 <style> 이 함께 실려 들어와 <head> 규칙보다 뒤에 놓이므로
     같은 특정성이면 조각 쪽이 이긴다. 그래서 여기서는 #pnlDetailContent 로 특정성을 올려
     덮는다. 조각에 인라인 style= 로 박혀 있는 값만 우선순위를 끝까지 올려 덮었다.
     (show.blade.php 는 이번 배정 파일이 아니라 읽기만 했다.)
     세 시안의 뼈대는 완전히 같고, 종류마다 다른 것은 금액 박스 개수·강조 위치와
     정보 박스 열 수·값 색뿐이다. 종류 구분은 카드 제목 앞 아이콘으로 한다
     (bx-bank=가상계좌 · bx-receipt=세금계산서 · bx-money=현금영수증 · bx-paper-plane=청구 발송). */

  /* ── 본문 2단 — 시안 Frame 48101628 = 1016 + 12 + 508 (2fr / 1fr, 열 간격 12) ── */
  #pnlDetailContent .dispatch-grid { grid-template-columns: 2fr 1fr; gap: 12px; }
  @media (max-width: 900px) { #pnlDetailContent .dispatch-grid { grid-template-columns: 1fr; } }
  /* 왼쪽·오른쪽 세로 스택의 카드 간격 12 (인라인 gap 16/14 를 덮는다) */
  #pnlDetailContent .dispatch-grid > div { gap: 12px !important; }

  /* ── 제목줄 — 시안 Frame 48101679: 껍데기 없이 1536×48 두 줄(세로 gap 4).
       1줄 = 발행번호 16/700 + gap 12 + 상태 배지, 2줄 = 메타 11/500 gray-500.
       48×48 아이콘 상자는 시안에 없지만 지우지 않는다 — 상자만 걷고 16px 글리프로 남긴다. ── */
  #pnlDetailContent .dispatch-header-card {
    display: grid;
    grid-template-columns: auto auto 1fr;
    column-gap: 12px; row-gap: 4px;
    align-items: center;
    padding: 0; border: 0; border-radius: 0; background: none;
    margin-bottom: 12px;
  }
  #pnlDetailContent .dispatch-header-icon {
    grid-column: 1; grid-row: 1 / span 2;
    width: auto; height: auto; border-radius: 0; font-size: 16px;
    background:none !important; color:var(--primary) !important;   /* 인라인 bgColor/iconColor 표를 덮는다 */
  }
  #pnlDetailContent .dispatch-header-meta { display: contents; }
  #pnlDetailContent .dispatch-header-no { grid-column: 2; grid-row: 1; }
  /* 상태 배지는 시안에서 발행번호 오른쪽에 gap 12 로 붙는다(카드 오른쪽 끝이 아니다) */
  #pnlDetailContent .dispatch-header-card > div:last-child { grid-column: 3; grid-row: 1; justify-self: start; }
  #pnlDetailContent .dispatch-header-sub {
    grid-column: 2 / -1; grid-row: 2; margin-top: 0;
    font-size: 11px; font-weight: 500; line-height: 18px; color: var(--gray-500);
  }
  /* 시안 메타줄은 주문번호도 500 굵기다 */
  #pnlDetailContent .dispatch-header-sub b { font-weight: 500; }

  /* ── 카드 안쪽 여백 — 시안 Frame 48101499·48101496: pad 12/16 ── */
  #pnlDetailContent .card-body { padding: 12px 16px; }

  /* ── 카드 제목줄 — 시안 984×28. 아래 구분선 없음, 본문까지 gap 12 ── */
  #pnlDetailContent .sec-title {
    min-height: 28px; align-items: center; gap: 8px;
    padding-bottom: 0; border-bottom: 0; margin-bottom: 12px;
  }

  /* ── 금액 박스 — 시안 74 = pad 12 + 21 + gap 8 + 21 + pad 12. 테두리 없다.
       라벨은 왼쪽 위, 값은 박스 오른쪽 끝. 금액 카드가 마지막 요소라 아래 여백 없음 ── */
  #pnlDetailContent .amount-row { gap: 8px; margin-bottom: 0; }
  #pnlDetailContent .amt-card { border: 0; padding: 12px; border-radius: 8px; }
  #pnlDetailContent .amt-card .avalue { text-align: right; }
  /* 세금계산서 금액 카드는 세 번째 '합계 금액'만 강조한다 — 첫 번째 '공급가액'은 회색으로 되돌린다 */
  #pnlDetailContent .card-body:has(.amt-card.success-hl) .amt-card.hl { background: var(--gray-100); }
  #pnlDetailContent .card-body:has(.amt-card.success-hl) .amt-card.hl .alabel { color: var(--gray-700); }
  #pnlDetailContent .card-body:has(.amt-card.success-hl) .amt-card.hl .avalue { color: var(--gray-1000); }

  /* ── 정보 값 박스 — 시안 240(3열이면 323)×74 · r8 · pad 12 · gap 8 · bg gray-100.
       라벨 13/500 gray-700 왼쪽 위 · 값 13/500 오른쪽 아래. 행·열 간격 8 ── */
  #pnlDetailContent .info-grid { gap: 8px; }
  #pnlDetailContent .info-cell {
    grid-column: auto !important;
    padding: 12px; min-height: 74px;
    background: var(--gray-100); border-radius: 8px; border-bottom: 0;
  }
  #pnlDetailContent .info-label { margin-bottom: 8px; }
  #pnlDetailContent .info-value:not(.large) {
    font-size: 13px !important; font-weight: 500; line-height: 21px; text-align: right;
  }
  /* 시안 값은 전부 Pretendard 13/500 이다 — monospace·자간·보조 색을 되돌린다 */
  #pnlDetailContent .info-value.mono {
    font-family: inherit; letter-spacing: 0 !important; color: var(--gray-1000);
  }
  /* 정보 박스 열 수 — 가상계좌·세금계산서 4열(240 폭), 현금영수증·청구 발송 3열 */
  #pnlDetailContent .card-body:has(.sec-title i.bx-bank) .info-grid,
  #pnlDetailContent .card-body:has(.sec-title i.bx-receipt) .info-grid {
    grid-template-columns: repeat(4, 1fr);
  }
  /* 시안이 primary 로 뽑는 값 — 가상계좌는 결제 키만, 세금계산서는 앞 4개, 현금영수증은 앞 3개 */
  #pnlDetailContent .card-body:has(.sec-title i.bx-bank) .info-cell:nth-child(6) .info-value,
  #pnlDetailContent .card-body:has(.sec-title i.bx-receipt) .info-cell:nth-child(-n+4) .info-value,
  #pnlDetailContent .card-body:has(.sec-title i.bx-money) .info-cell:nth-child(-n+3) .info-value {
    color: var(--primary);
  }
  /* 가상계좌 은행명은 '기업은행 (코드: IBK)' 통째로 13/500 gray-1000 이다 */
  #pnlDetailContent .card-body:has(.sec-title i.bx-bank) .info-cell:nth-child(1) .info-value span {
    font-size: 13px !important; color: inherit !important;
  }
  /* 가상계좌 입금 마감일 — 시안은 '2026-06-23 09:35 (만료)' 한 줄에서 '(만료)' 만 색이 다르다 */
  #pnlDetailContent .card-body:has(.sec-title i.bx-bank) .info-cell:nth-child(4) .badge {
    background: none; padding: 0; border-radius: 0; color: var(--alert-500);
    font-size: 13px; font-weight: 500; line-height: 21px;
  }
  /* 현금영수증 발급 유형 — 시안은 평문 '소득공제' 13/500 primary */
  #pnlDetailContent .card-body:has(.sec-title i.bx-money) .info-cell:nth-child(2) .badge {
    background: none; padding: 0; border-radius: 0; color: inherit;
    font-size: 13px; font-weight: 500; line-height: 21px;
  }

  /* ── 가상계좌 '가상계좌 정보' 제목줄은 시안에서 SPACE_BETWEEN 이고, 오른쪽 끝에
       '기업은행 X8011974797930 / 예금주: 김경한' 14/700 primary 한 줄이 붙는다.
       구현의 은행 강조 블록(제목 아래 pad 12/16 · bg primary-light · bd 1px 상자)을
       지우지 않고 제목줄 오른쪽으로 옮긴 뒤 껍데기(상자·44×44 아이콘 판)만 걷는다. ── */
  #pnlDetailContent .card-body:has(> .info-grid) {
    display: grid; grid-template-columns: max-content 1fr;
    align-items: center; column-gap: 8px; row-gap: 12px;
  }
  #pnlDetailContent .card-body:has(> .info-grid) > .sec-title { grid-column: 1; grid-row: 1; margin-bottom: 0; }
  #pnlDetailContent .card-body:has(> .info-grid) > .info-grid { grid-column: 1 / -1; grid-row: 2; }
  #pnlDetailContent .card-body:has(> .info-grid) > div:not(.sec-title):not(.info-grid) {
    grid-column: 2; grid-row: 1; justify-self: end; min-width: 0;
    padding:0 !important; margin:0 !important; border:0 !important; border-radius:0 !important; background:none !important; gap:6px !important; align-items:center !important;
  }
  /* 44×44 아이콘 판 → 14px 글리프(지우지 않고 크기만 줄인다) */
  #pnlDetailContent .card-body:has(> .info-grid) > div:not(.sec-title):not(.info-grid) > div:first-child {
    width:auto !important; height:auto !important; border-radius:0 !important; background:none !important; color:var(--primary) !important; font-size:14px !important;
  }
  /* 두 줄로 쌓여 있던 은행명/계좌번호·예금주/이름을 한 줄로 편다 */
  #pnlDetailContent .card-body:has(> .info-grid) > div:not(.sec-title):not(.info-grid) > div + div {
    display:flex !important; align-items:center !important; gap:6px !important; flex:0 0 auto !important; text-align:left !important;
  }
  #pnlDetailContent .card-body:has(> .info-grid) > div:not(.sec-title):not(.info-grid) > div + div > div {
    font-family:inherit !important; letter-spacing:0 !important; font-size:14px !important; font-weight:700 !important; line-height:22px !important; color:var(--primary) !important; margin:0 !important;
  }

  /* ── 처방 제품 내역 — 시안 Frame 48101498: 카드 pad 12/0/0/0, 제목줄만 pad 0/16,
       표는 카드 좌우 끝까지 닿는다 ── */
  #pnlDetailContent .card-body:has(.mini-table) { padding: 12px 0 0; }
  #pnlDetailContent .card-body:has(.mini-table) > .sec-title { padding-left: 16px; padding-right: 16px; }
  /* 표 둘레 1px 중 좌·우·아래는 카드 자신의 테두리가 대신한다(시안 Frame 48101520 은 카드와
     폭이 같은 1016 이라 두 획이 정확히 겹친다). 브라우저에서 둘 다 그리면 좌·우·아래가 2px 이 되고,
     카드의 r12 둥근 모서리를 표의 각진 모서리가 뚫고 나온다. 표는 위 획만 갖는다. */
  #pnlDetailContent .mini-table {
    font-size: 13px; table-layout: fixed;
    border: 0; border-top: 1px solid var(--border);
  }
  /* 머리행 45 = pad 12 + 21 + pad 12 · 13/700 gray-600 · bg gray-50 · 왼쪽 정렬 */
  #pnlDetailContent .mini-table th {
    padding: 12px; font-size: 13px; font-weight: 700; line-height: 21px;
    color: var(--gray-600); background: var(--gray-50);
    border-bottom: 1px solid var(--border); text-align: left !important;
  }
  /* 본문행 41 = pad 10 + 21 + pad 10 · 13/400 gray-1000 · 여섯 열 전부 왼쪽 정렬 */
  #pnlDetailContent .mini-table td {
    padding: 10px 12px; font-size: 13px; line-height: 21px;
    color: var(--gray-1000); border-bottom: 1px solid var(--border-light);
    font-weight:400 !important; text-align:left !important;
  }
  /* 마지막 행 아래는 카드 테두리가 대신한다 */
  #pnlDetailContent .mini-table tr:last-child td { border-bottom: 0; }
  /* 열 폭 — 시안 제품명 400 / 코드·수량·소비자가·보험가·환자부담 각 123 (합 1015) */
  #pnlDetailContent .mini-table th:first-child,
  #pnlDetailContent .mini-table td:first-child { width: 39.4%; }
  #pnlDetailContent .mini-table th + th,
  #pnlDetailContent .mini-table td + td { width: 12.12%; }
  /* 세로 구분선 — 시안은 본문 칸 사이에만 긋고 머리행에는 긋지 않는다(행 구분선과 같은 연한 색) */
  #pnlDetailContent .mini-table td + td { border-left: 1px solid var(--border-light); }
  /* 코드 열도 시안에서는 다른 열과 같은 13/400 gray-1000 이다 */
  #pnlDetailContent .mini-table td:nth-child(2) {
    font-family: inherit !important; font-size: 13px !important; color: var(--gray-1000) !important;
  }
  /* 환자부담 열만 13/700 primary — 굵기는 되살린다(색은 인라인 그대로) */
  #pnlDetailContent .mini-table td:last-child { font-weight: 700 !important; }

  /* ── 오른쪽 카드 머리 — 시안 508×44 · pad 8/16 · 제목 13/700 ── */
  #pnlDetailContent .card-header { min-height: 44px; padding: 8px 16px !important; }
  #pnlDetailContent .card-header span { font-size: 13px !important; line-height: 21px !important; }

  /* ── 오른쪽 카드 본문 — 시안 pad 16 · 행 사이 gap 4.
       한 행은 [라벨 140 · 13/500 gray-700][gap 8][값 13/500 gray-1000] 가로 배치(한 항목 21) ── */
  #pnlDetailContent .card-body:has(.side-info) { padding: 16px; }
  #pnlDetailContent .side-info {
    display: grid; grid-template-columns: 140px 1fr;
    column-gap: 8px; row-gap: 4px; align-items: start;
    margin: 0;   /* 부트스트랩 reboot 의 dl{margin-bottom:1rem} 이 카드 아래에 16px 을 더한다 */
  }
  #pnlDetailContent .side-info dt { grid-column: 1; margin: 0; }
  #pnlDetailContent .side-info dd { grid-column: 2; margin: 0; }
  /* 굵기도 우선순위를 끝까지 올린다 — 배송지·주소 dd 에 인라인 font-weight:400 이 박혀 있어
     그대로 두면 그 두 줄만 400 으로 남는다(시안은 오른쪽 카드 값이 전부 13/500). */
  #pnlDetailContent .side-info dd,
  #pnlDetailContent .side-info dd a {
    font-weight:500 !important;
    font-size:13px !important; line-height:21px !important; font-family:inherit !important;
  }
  /* 환자 정보 카드 마지막 행 '건강보험 급여 대상' 은 시안에서 평문 13/500 gray-1000 이다.
     (같은 화면의 주문 상태·처방전 상태는 시안에도 배지라 그대로 둔다) */
  #pnlDetailContent .card:has(.side-info a[href*="/patients/"]) .side-info dd:last-child .badge {
    background: none; padding: 0; border-radius: 0; color: var(--gray-1000);
    font-size: 13px; font-weight: 500; line-height: 21px;
  }
</style>
@endpush

@section('content')

  {{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
       고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}

  {{-- 검색 필터 — Figma 174:1210: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래.
       9열 그리드에 검색어 2열 · 기간 2열 · 표시 건수 1열. 입력·버튼은 전역 규격(h32 · r8 · 13px). --}}
  <form method="GET" action="{{ route('dispatch.index') }}" class="ds-filter-card">
    <div class="ds-filter-fields">
      <div class="ds-filter-field">
        {{-- 무엇을 보낸 것인지가 가장 크게 가른다 — 첫 칸에 둔다 --}}
        <label class="ds-field-label">종류</label>
        <select name="type" class="form-control form-select" onchange="this.form.submit()">
          @foreach(['virtual_account' => '가상계좌 발행', 'tax_invoice' => '세금계산서 발행',
                    'cash_receipt' => '현금영수증 발행', 'nhis' => '청구 발송'] as $k => $label)
            <option value="{{ $k }}" {{ $type === $k ? 'selected' : '' }}>
              {{ $label }} ({{ number_format($counts[$k]) }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">검색어</label>
        <input type="text" name="search" class="form-control"
               placeholder="주문번호 · 이름 · 발행번호" value="{{ $search }}">
      </div>
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">기간</label>
        <div class="ds-field-range">
          <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
          <span class="ds-field-sep">~</span>
          <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
        </div>
      </div>
      <div class="ds-filter-field">
        <label class="ds-field-label">표시 건수</label>
        <select name="per_page" class="form-control form-select"
                onchange="this.form.submit()">
          @foreach([10, 20, 50, 100] as $n)
            <option value="{{ $n }}" {{ $perPage === $n ? 'selected' : '' }}>{{ $n }}개씩</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="ds-filter-actions">
      {{-- 시안 352:3206 은 검색 왼쪽(x1804)에 초기화를 늘 보여준다 — 검색 조건이 없을 때도 마찬가지다.
           조건부(@if)를 걷어 첫 진입에서도 보이게 했다. 링크는 그대로 같은 라우트로 되돌아간다
           (검색이 GET 폼이라 이 링크 하나로 조건이 비워진다 — 폼을 비우는 JS 는 만들지 않았다).
           시안 Frame 48101589(128×61)는 초기화·검색 둘 다 글자만 든 60×32 다 — 아이콘이 없다.
           늘 보이게 만든 뒤 아이콘 때문에 초기화 77×32 · 검색 69×32 로 벌어져 두 아이콘을 뺐다.
           낱말 '초기화'·'검색' 은 그대로다. 같은 자리의 다른 여덟 화면(환자·주문·청구·서류·
           재구매·개인정보동의·메시지·NHIS)도 글자만 든 60×32 라 이 화면만 어긋나 있었다. --}}
      <a href="{{ route('dispatch.index', ['type'=>$type]) }}" class="ds-btn">초기화</a>
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    </div>
  </form>

  {{-- Figma 174:1241 — 흰 카드(r12) 안에 탭바와 그리드.
       검색 카드 오른쪽에 있던 '총 N건'과 그리드 위 '전체 N건' 배지는
       시안 자리인 결과바 왼쪽 '전체 N건'(16/700) 한 곳으로 합쳤다. --}}
  <div class="ds-grid-section">
    <div class="ds-grid-card">
      {{-- 패널 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단 --}}
      <div class="pnl-tabs">
        <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(<b>{{ number_format($total) }}</b>)</span></button>
        <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세 내용</button>
        {{-- 상세 내용 패널 맨 위에 있던 '조회결과로' 버튼을 여기로 옮겼다(상세 내용 탭일 때만 보인다) --}}
        <button type="button" class="ds-btn pnl-back" onclick="pnlShow('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
      </div>

      <div id="pnlList">
        {{-- ── 발송 내역 (wwGrid) ── --}}
        <div id="dispatchGrid"></div>
      </div>{{-- /pnlList --}}

      {{-- ── 상세내용 탭 (상세 콘텐츠를 같은 페이지에 직접 주입) — 같은 카드 안 ── --}}
      <div id="pnlDetail" style="display:none;padding:16px;">
        {{-- '조회결과로' 버튼은 위 탭줄 오른쪽 끝으로 옮겼다 — 시안은 pad 16 바로 아래에서 본문이 시작한다 --}}
        <div id="pnlEmpty" class="pnl-empty">조회결과에서 행을 <b>더블클릭</b>하면 상세가 여기에 표시됩니다.</div>
        <div id="pnlDetailContent"></div>
      </div>
    </div>{{-- /.ds-grid-card --}}
  </div>{{-- /.ds-grid-section --}}

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '발송 내역 검색', body: '팩스·이메일·SMS 발송 내역을 날짜·수신자·상태로 조회합니다.' },
  { selector: '#dispatchGrid', title: '발송 목록', body: '청구서·영수증·알림 발송 이력 전체를 확인합니다.' },
];
</script>
<script>
(function () {
  const DETAIL_BASE = @json(url('dispatch'));   // + '/{type}/{id}'
  const TYPE = @json($type);
  const grid = new wwGrid({
    el: document.getElementById('dispatchGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일)
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    summary: true,   /* 수량·금액은 맨 아래에서 합계를 낸다 */
    footer: false,   // 시안에 하단 상태바가 없다. 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다
    columns: @json($gridColumns),
    data: @json($gridData),
  });
  window.__dispatchGrid = grid;
  window.dsBindSelCount(grid, 'dispatchSelCount');

  // 패널 탭 전환(조회결과/상세내용)
  window.pnlShow = function (which) {
    document.getElementById('pnlList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pnlDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pnlBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pnlBtnDetail').classList.toggle('active', which === 'detail');
  };

  // 상세 콘텐츠(크롬 없는 프래그먼트)를 fetch로 가져와 같은 페이지에 직접 주입(iframe 미사용)
  window.pnlLoadDetail = async function (url) {
    const empty = document.getElementById('pnlEmpty');
    const cont  = document.getElementById('pnlDetailContent');
    empty.style.display = 'none';
    cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted);"><i class="bx bx-loader-alt bx-spin" style="font-size:16px;"></i><div style="margin-top:8px;font-size:13px;line-height:21px;">불러오는 중...</div></div>';
    window.pnlShow('detail');
    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      cont.innerHTML = await res.text();
      cont.querySelectorAll('script').forEach(function (old) {
        const s = document.createElement('script');
        if (old.src) s.src = old.src; else s.textContent = old.textContent;
        old.parentNode.replaceChild(s, old);
      });
    } catch (e) {
      cont.innerHTML = '<div style="text-align:center;padding:48px;font-size:13px;line-height:21px;color:var(--danger);">상세를 불러오지 못했습니다.</div>';
    }
  };

  // 행 더블클릭 → 상세내용 탭에 상세를 인페이지로 표시(페이지 이동 없음)
  document.getElementById('dispatchGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.id) return;
    window.pnlLoadDetail(DETAIL_BASE + '/' + TYPE + '/' + row.id + '?partial=1');
  });
})();
</script>
@endpush
