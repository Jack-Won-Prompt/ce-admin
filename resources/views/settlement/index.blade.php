{{-- resources/views/settlement/index.blade.php --}}
@extends('layouts.app')

@section('title', '정산/회계')
@section('page-title', '정산/회계')
{{-- 시안 324:60 빵부스러기는 '홈 - 정산/회계' 두 마디다(구분자 하이픈). --}}
@section('breadcrumb', '홈 - 정산/회계')

@push('styles')
<style>
  /* 탭 칩(정산 현황·가상계좌 매칭)은 전역 .ds-chip 규격(h31 · r999 · pad 6/10 · 12/700)을 그대로 쓴다.
     .settle-tabs/.settle-tab 은 예전 선택자라 앵커로만 남기고 별도 스타일은 주지 않는다. */

  /* 요약 4칸 — 시안 324:60 Frame 48101550: 카드 넉 장이 아니라 흰 카드 한 장이다.
     1568×75 = pad 12/0 + 칸 51. 카드 테두리는 없고, 칸 사이만 1px 세로 구분선(Vector 34 · #E8EAEC · h51).
     칸 하나 392×51 · pad 4/12 · gap 2 · 가로세로 가운데 정렬.
     아래 여백은 .page-body 의 gap 12 가 만든다(margin 을 따로 주지 않는다). */
  .summary-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    background: var(--gray-0); border-radius: 12px; padding: 12px 0;
  }
  @media (max-width: 900px) {
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    /* 2열로 접히면 둘째 줄 첫 칸(3번)의 왼쪽 구분선은 지운다.
       .sum-card + .sum-card 와 특정성이 같으면(둘 다 0-2-0) 뒤에 오는 쪽이 이겨서
       선이 그대로 남는다 — .summary-grid 를 앞에 붙여 0-3-0 으로 올린다. */
    .summary-grid .sum-card:nth-child(3)   { border-left: 0; }
    .summary-grid .sum-card:nth-child(n+3) { border-top: 1px solid var(--gray-200); }
  }
  /* 칸 — 1줄 [라벨 gap4 보조] · gap 2 · 2줄 [값]. 값에 width:100% 를 줘서 줄을 나눈다
     (마크업 순서는 라벨 → 값 → 보조 그대로 두고 order 로만 자리를 바꾼다). */
  .sum-card {
    display: flex; flex-wrap: wrap; align-items: center; align-content: center;
    justify-content: center; text-align: center;
    gap: 2px 4px; padding: 4px 12px; min-width: 0;
  }
  .sum-card + .sum-card { border-left: 1px solid var(--gray-200); }
  /* 아이콘 슬롯은 현재 마크업에 없지만 개발 자산이라 남긴다 (쓰게 되면 32×32 · r8) */
  .sum-card-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
    background: var(--gray-100); color: var(--gray-600);
  }
  .sum-card-label { order: 1; font-size: 12px; font-weight: 700; line-height: 19px; color: var(--gray-800); }
  .sum-card-sub   { order: 2; font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600); }
  .sum-card-val   { order: 3; width: 100%; font-size: 14px; font-weight: 700; line-height: 22px; color: var(--primary); }
  /* 값 색은 시안에서 네 칸 모두 primary(#28798B) 한 가지다 — 칸마다 색을 바꾸지 않는다.
     .blue/.green/.orange/.red 클래스는 마크업에 남겨 두고 아이콘 슬롯 색만 구분한다.
     단, 가상계좌 탭은 이번 시안에 없다 — 개발자가 넣은 값 색 구분을 그대로 둔다(.va-summary). */
  .va-summary .sum-card.blue   .sum-card-val { color: var(--primary); }
  .va-summary .sum-card.green  .sum-card-val { color: var(--primary-600); }
  .va-summary .sum-card.orange .sum-card-val { color: var(--gray-800); }
  .va-summary .sum-card.red    .sum-card-val { color: var(--alert-500); }
  .sum-card.blue   .sum-card-icon { background: var(--primary-50);  color: var(--primary); }
  .sum-card.green  .sum-card-icon { background: var(--primary-100); color: var(--primary-600); }
  .sum-card.orange .sum-card-icon { background: var(--gray-100);    color: var(--gray-700); }
  .sum-card.red    .sum-card-icon { background: var(--alert-100);   color: var(--alert-500); }

  /* 보조 합계 4개 — 시안 324:60 Frame 48101508: 별도 줄이 아니라 결과바 왼쪽 인라인이다.
     [전체 N건] —12— ● —12— [라벨 값단위] —12— ● … (● = 4×4 원형 점 · gray-300)
     항목 안 라벨↔값 간격은 2. 묶음 542×26. */
  .sum-mini-row { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 12px; min-width: 0; }
  .sum-mini { display: inline-flex; align-items: center; gap: 2px; min-width: 0; }
  .sum-mini::before {
    /* 4×4 정원 (시안 Rectangle 11~14 · #C2C5C8) — 전역 '전체↔선택' 구분점과 같은 점이다 */
    content: ''; flex-shrink: 0; width: 4px; aspect-ratio: 1; border-radius: 999px;
    background: var(--gray-300); margin-right: 10px;   /* 10 + gap 2 = 시안 12 */
  }
  .sum-mini-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-600); white-space: nowrap; }
  .sum-mini-val   { font-size: 13px; font-weight: 500; line-height: 21px; white-space: nowrap; }
  /* 결과바가 한 줄에 안 들어갈 때 — 기준은 뷰포트 폭이 아니라 '내용 폭'이다.
     왼쪽 묶음은 금액 자릿수만큼 넓어진다(데모 1건 601.7 → 실데이터 자릿수 806.5).
     뷰포트 breakpoint 로 막으면 자릿수가 커지는 순간 뚫린다 — 실제로 1280 에서
     '처방 상세(선택)' 버튼이 카드 밖으로 111px 밀려 .content-wrapper(overflow-x:clip)에 잘렸고
     안내문은 폭 0 이 됐다. 전역 .ds-grid-bar-left 가 flex-shrink:0 이고
     .sum-mini-* 가 nowrap 이라 왼쪽이 한 치도 양보하지 않는 것이 원인이다.
     보조 합계가 든 결과바(이 화면)에서만 접히게 풀고, 좌우 묶음 사이 최소 12 를 남긴다.
     1568(시안)에서는 한 줄 · h32 · 좌우 간격 342.8 그대로다. */
  .ds-grid-bar:has(.sum-mini-row) {
    height: auto; min-height: 32px; flex-wrap: wrap; row-gap: 8px; column-gap: 12px;
  }
  .ds-grid-bar:has(.sum-mini-row) .ds-grid-bar-left { flex-shrink: 1; flex-wrap: wrap; row-gap: 4px; }
  /* '전체 N건'·'선택 N건'은 줄이지 않는다 — 줄바꿈은 보조 합계 쪽에서만 일어난다 */
  .ds-grid-bar:has(.sum-mini-row) .ds-grid-bar-left > .ds-grid-total,
  .ds-grid-bar:has(.sum-mini-row) .ds-grid-bar-left > .ds-grid-sel { flex-shrink: 0; }
  /* 둘째 줄로 내려가도 오른쪽 묶음은 오른쪽 끝에 붙는다(space-between 은 한 줄에서만 먹는다) */
  .ds-grid-bar:has(.sum-mini-row) .ds-grid-bar-right { margin-left: auto; }

  /* 패널 탭(조회 결과/상세 내용) — 그리드 카드 안 상단 h44 */
  /* '조회결과로' 버튼은 시안에 없다. 상세 내용 패널 맨 위에 두면 본문이 44px 밀려
     시안(탭줄 바로 아래 pad 16 에서 ORD 머리줄 시작)과 어긋난다.
     지우지 않고 탭줄 오른쪽 끝으로 옮기고, 상세 내용 탭이 활성일 때만 보이게 한다.
     (활성 표시는 pnlShow() 가 붙이는 .active 클래스를 그대로 읽는다 — JS 는 건드리지 않는다) */
  /* 결과바 '선택 N건' — 13/500 · gray-600, 숫자만 primary-400 */
  /* 기간 구분자 — 예전 선택자(.filter-sep)를 함께 남기되, 전역 .filter-sep 은
     세로 구분선(1×20px · 회색 배경)이라 여기서 그 형태를 되돌려 놓는다.
     되돌리지 않으면 '~' 글자가 1px 폭 회색 막대 밖으로 삐져나온다.
     색·글자는 이제 전역 .ds-field-sep 이 시안대로(13/400 · #101317) 갖는다 —
     여기서는 세로막대 형태만 되돌린다. */
  .filter-sep { width: auto; height: auto; background: none; margin: 0;
    color: var(--gray-1000); font-size: 13px; font-weight: 400; line-height: 21px; flex-shrink: 0; }
  /* 기간 두 입력 높이 — 시안 324:60 은 검색어·주문 상태와 똑같은 135×32 다.
     전역 .form-control 에 height 선언이 없어 높이가 내용에서 나오는데,
     input[type=date] 는 크롬 섀도 DOM 안쪽이 22px 라 5+22+5+2 = 34 가 된다.
     같은 줄의 검색어 input·주문 상태 select 는 5+20+5+2 = 32 라 두 상자만 2px 크다.
     그 2px 이 필드(61→63) · 검색 카드(140→142) · 조회 버튼 기준선까지 밀고 있었다.
     전역 .form-control 은 손대지 않고 이 화면 기간 입력만 32 로 눌러 맞춘다
     (scrollHeight 30 = clientHeight 30 — 글자가 잘리지 않는다). */
  .ds-field-range input[type="date"].form-control, .ds-field-range .ce-date-wrap { height: 32px; }

  /* ── 아래 선택자들은 현재 마크업에 사용처가 없다(목록을 wwGrid 가 텍스트로 그린다).
        개발 자산이라 남기되 값만 시안 규격에 맞춰 둔다. ── */
  /* API 상태 카드 */
  .toss-api-card { background: var(--gray-0); border: 1px solid var(--gray-200); border-radius: 12px; padding: 12px 16px; }
  .api-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
  .api-dot { width: 12px; height: 12px; border-radius: 999px; flex-shrink: 0; }
  .api-dot.connected    { background: var(--primary);   box-shadow: 0 0 0 3px var(--primary-50); }
  .api-dot.disconnected { background: var(--alert-500); box-shadow: 0 0 0 3px var(--alert-100); }
  .api-dot.unknown      { background: var(--gray-400);  box-shadow: 0 0 0 3px var(--gray-100); }
  .api-name { font-size: 13px; font-weight: 700; line-height: 21px; }
  .api-desc { font-size: 11px; font-weight: 500; line-height: 18px; color: var(--gray-500); }
  .api-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; }
  .api-meta-item { font-size: 11px; font-weight: 500; line-height: 18px; display: flex; justify-content: space-between; padding: 5px 8px; background: var(--gray-100); border-radius: 6px; }
  .api-meta-key   { color: var(--gray-500); }
  .api-meta-val   { font-weight: 700; font-family: monospace; }

  /* VA 상태 배지 — r6 · pad 2/6 · 11/500 · lh18 */
  .va-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 6px; font-size: 11px; font-weight: 500; line-height: 18px; }
  .va-badge.done      { background: var(--primary-50);  color: var(--primary-600); }
  .va-badge.waiting   { background: var(--alert-100);   color: var(--alert-500); }
  .va-badge.ready     { background: var(--primary-50);  color: var(--primary); }
  .va-badge.expired   { background: var(--gray-100);    color: var(--gray-500); }
  .va-badge.none      { background: var(--gray-100);    color: var(--gray-500); }

  /* 금액 셀 */
  .amount-cell { font-family: monospace; font-size: 12px; font-weight: 500; line-height: 19px; text-align: right; }
  .amount-cell.primary { color: var(--primary); font-weight: 700; }
  .amount-cell.success { color: var(--primary-600); }
  .amount-cell.muted   { color: var(--gray-500); }
  .order-num { font-family: monospace; font-size: 12px; font-weight: 700; line-height: 19px; color: var(--primary); }

  /* 발급 버튼 — 작은 버튼 h28 · r8 · pad 3/10 · 13/500 */
  .btn-issue-va { height: 28px; padding: 3px 10px; border-radius: 8px; font-size: 13px; font-weight: 500; line-height: 20px; }

  /* 기간 — 두 칸을 한 줄에 세운다.
     9열 그리드의 span-2 는 이 화면에서 약 241 이다(1920 화면 · 단추 넷이 오른쪽을 넓게 쓴다).
     표준값인 날짜 칸 132 로는 132+8+~+8+132 = 287 이라 들어가지 못해 둘째 칸이 아래로 접혔다.
     좌우 여백을 12 → 8, 폭 하한을 132 → 108, 사이 간격을 8 → 6 으로 줄여
     108+6+~+6+108 = 235 로 맞춘다.
     108 은 임의값이 아니라 실측 하한이다 — 여백 8 에서 「2026-06-01」과 달력 아이콘이
     잘리지 않는 가장 좁은 폭이 108 이고, 106 부터 끝자리가 잘린다(여백 12 일 때는 116). */
  .ds-filter-card .ds-field-range { gap: 6px; }
  .ds-filter-card .ds-field-range input[type="date"], .ds-filter-card .ds-field-range .ce-date-wrap { min-width: 108px; padding-left: 8px; padding-right: 8px; }
</style>
@endpush

@section('content')

  {{-- 탭 — 표준 상태 칩(h31 · r999 · 12/700), 건수는 16×16 정원 배지 --}}

  @if($tab === 'settlement')
  {{-- ══════════════ 정산 현황 ══════════════ --}}

    {{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래 · 9열 그리드 --}}
    <form method="GET" action="{{ route('settlement.index') }}" class="ds-filter-card">
      <input type="hidden" name="tab" value="settlement">
      {{-- 시안 324:60 — 검색어(span-2 · 295) → 기간(span-2 · 295) → 주문 상태(1열 · 140) 순으로
           왼쪽에 몰리고 오른쪽 4열은 비운다. 기간 두 입력은 span-2 안에서 135+8+'~'+8+135 로 나뉜다. --}}
      <div class="ds-filter-fields">
        {{-- 무엇을 볼지가 가장 크게 가른다 — 첫 칸에 둔다. 위쪽 칩 줄을 따로 두면
             그 줄만 회색 바탕 위에 떠 있고, 고르는 일은 여기서도 할 수 있다. --}}
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">보기</label>
          <select class="form-control form-select"
                  onchange="location.href = this.value;">
            <option value="{{ route('settlement.index', ['tab' => 'settlement', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    @selected($tab === 'settlement')>정산 현황 ({{ $summary['total_orders'] }})</option>
            <option value="{{ route('settlement.index', ['tab' => 'virtual_account']) }}"
                    @selected($tab === 'virtual_account')>가상계좌 매칭 ({{ $vaStats['waiting'] ?? 0 }})</option>
          </select>
        </div>
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">검색어</label>
          <input type="text" name="search" class="form-control" placeholder="주문번호·이름·제품명" value="{{ request('search') }}">
        </div>
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">기간</label>
          <div class="ds-field-range">
            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            <span class="filter-sep ds-field-sep">~</span>
            <input type="date" name="date_to"   class="form-control" value="{{ $dateTo }}">
          </div>
        </div>
        <div class="ds-filter-field">
          <label class="ds-field-label">주문 상태</label>
          <select name="status" class="form-control form-select">
            <option value="">전체 상태</option>
            <option value="pending"   {{ request('status')==='pending'   ? 'selected':'' }}>주문 대기</option>
            <option value="confirmed" {{ request('status')==='confirmed' ? 'selected':'' }}>주문 확정</option>
            <option value="shipping"  {{ request('status')==='shipping'  ? 'selected':'' }}>배송 중</option>
            <option value="delivered" {{ request('status')==='delivered' ? 'selected':'' }}>배송 완료</option>
            <option value="cancelled" {{ request('status')==='cancelled' ? 'selected':'' }}>취소</option>
          </select>
        </div>
        {{-- 원내·원외·처방외는 정산 방식과 필요한 서류가 갈리는 값이라 나눠 봐야 한다 --}}
        <div class="ds-filter-field">
          <label class="ds-field-label">처방유형</label>
          <select name="acc_type" class="form-control form-select">
            <option value="">전체 유형</option>
            @foreach(\App\Models\Prescription::ACC_TYPES as $v => $label)
              {{-- 배열 키가 정수로 바뀌므로 문자열로 되돌려 견준다 — 그냥 두면 고른 값이 표시되지 않는다 --}}
              <option value="{{ $v }}" @selected(request('acc_type') === (string) $v)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="ds-filter-actions">
        <a href="{{ route('settlement.index', ['tab'=>'settlement']) }}" class="ds-btn"><i class="fa-solid fa-rotate-left"></i> 초기화</a>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> 조회</button>
        {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
        <button type="button" class="ds-btn" onclick="window.__settlementGrid?.downloadExcel()">엑셀 저장</button>
        <button type="button" class="ds-btn" onclick="settlementViewRx()">
          <i class="fa-solid fa-file-medical"></i> 주문 보기
        </button>

      </div>
    </form>

    {{-- 흰 카드(r12) 안에 패널 탭과 그리드 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-card">
        {{-- 패널 탭: 조회 결과 / 상세 내용 --}}
        <div class="pnl-tabs">
          <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ number_format($total) }}</b>건)</span></button>
          <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세 내용</button>
        </div>

        <div id="pnlList">
          {{-- ── 정산 목록 (wwGrid) ── --}}
          <div id="settlementGrid"></div>
        </div>{{-- /pnlList --}}

        {{-- ── 상세내용 탭 (주문 상세를 같은 페이지에 직접 주입) ── --}}
        <div id="pnlDetail" style="display:none;padding:16px;">
          <div id="pnlEmpty" class="pnl-empty">조회결과에서 행을 <b>더블클릭</b>하면 주문 상세가 여기에 표시됩니다.</div>
          <div id="pnlDetailContent"></div>
        </div>
      </div>{{-- /.ds-grid-card --}}
    </div>{{-- /.ds-grid-section --}}

  @elseif($tab === 'virtual_account')
  {{-- ══════════════ 가상계좌 매칭 (Toss Payments) ══════════════ --}}

    {{-- 검색/필터 — 정산 현황 탭과 같은 표준 필터 카드(r12 · pad 12/16) --}}
    <form method="GET" action="{{ route('settlement.index') }}" class="ds-filter-card">
      <input type="hidden" name="tab" value="virtual_account">
      <div class="ds-filter-fields">
        {{-- 무엇을 볼지가 가장 크게 가른다 — 첫 칸에 둔다. 위쪽 칩 줄을 따로 두면
             그 줄만 회색 바탕 위에 떠 있고, 고르는 일은 여기서도 할 수 있다. --}}
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">보기</label>
          <select class="form-control form-select"
                  onchange="location.href = this.value;">
            <option value="{{ route('settlement.index', ['tab' => 'settlement', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    @selected($tab === 'settlement')>정산 현황 ({{ $summary['total_orders'] }})</option>
            <option value="{{ route('settlement.index', ['tab' => 'virtual_account']) }}"
                    @selected($tab === 'virtual_account')>가상계좌 매칭 ({{ $vaStats['waiting'] ?? 0 }})</option>
          </select>
        </div>
        {{-- 폭은 정산 현황 탭에서 확정된 규격을 따른다 — 검색어 span-2(295) · 선택 1열(140) --}}
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">검색어</label>
          <input type="text" name="va_search" class="form-control" placeholder="주문번호·이름·계좌번호" value="{{ request('va_search') }}">
        </div>
        <div class="ds-filter-field">
          <label class="ds-field-label">입금 상태</label>
          <select name="va_status" class="form-control form-select">
            <option value="">전체</option>
            <option value="not_issued" {{ request('va_status')==='not_issued' ? 'selected':'' }}>미발급</option>
            <option value="issued"     {{ request('va_status')==='issued'     ? 'selected':'' }}>발급완료</option>
            <option value="waiting"    {{ request('va_status')==='waiting'    ? 'selected':'' }}>입금대기</option>
            <option value="done"       {{ request('va_status')==='done'       ? 'selected':'' }}>입금완료</option>
          </select>
        </div>
      </div>
      <div class="ds-filter-actions">
        <a href="{{ route('settlement.index', ['tab'=>'virtual_account']) }}" class="ds-btn"><i class="fa-solid fa-rotate-left"></i> 초기화</a>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> 검색</button>
        {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
        @if($tossConfigured)
        @perm('settlement', 'send')
        <button type="button" class="ds-btn ds-btn-primary" onclick="vaIssueSelected(this)">
          <i class="fa-solid fa-plus"></i> 선택 발급
        </button>
        @endperm
        <button type="button" class="ds-btn" onclick="vaCheckSelected(this)">
          <i class="fa-solid fa-rotate"></i> 토스 조회
        </button>

        @perm('settlement', 'send')
        <button type="button" class="ds-btn" onclick="vaResendSelected(this)">
          <i class="fa-solid fa-comment-sms"></i> 선택 SMS재전송
        </button>
        @endperm
        @endif
        <button type="button" class="ds-btn" onclick="window.__settlementGrid?.downloadExcel()">엑셀 저장</button>
      </div>
    </form>

    {{-- VA 요약 카드 --}}
    <div class="summary-grid va-summary">
      <div class="sum-card blue">
        <div class="sum-card-label">가상계좌 대상</div>
        <div class="sum-card-val">{{ number_format($vaStats['total']) }}<span>건</span></div>
        <div class="sum-card-sub">본인부담금 > 0</div>
      </div>
      <div class="sum-card green">
        <div class="sum-card-label"><i class="fa-solid fa-circle-check"></i> 입금 완료</div>
        <div class="sum-card-val">{{ number_format($vaStats['done']) }}<span>건</span></div>
        <div class="sum-card-sub">DONE 확인</div>
      </div>
      <div class="sum-card orange">
        <div class="sum-card-label"><i class="fa-solid fa-clock"></i> 입금 대기</div>
        <div class="sum-card-val">{{ number_format($vaStats['waiting']) }}<span>건</span></div>
        <div class="sum-card-sub">발급 후 미입금</div>
      </div>
      <div class="sum-card red">
        <div class="sum-card-label"><i class="fa-solid fa-won-sign"></i> 대기 금액</div>
        <div class="sum-card-val">{{ number_format($vaStats['pending_amount']) }}<span>원</span></div>
        <div class="sum-card-sub">미수 본인부담 합계</div>
      </div>
    </div>

    {{-- 흰 카드(r12) 안에 그리드 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-card">
        <div class="pnl-tabs">
          <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
        </div>
        {{-- ── 가상계좌 목록 (wwGrid) ── --}}
        <div id="vaGrid"></div>
      </div>{{-- /.ds-grid-card --}}
    </div>{{-- /.ds-grid-section --}}

  @endif

@endsection

@push('modals')
{{-- ══ 처방전 상세 팝업 ══ --}}
<div id="rxModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border-radius:var(--radius-lg);box-shadow:0 20px 60px rgba(0,0,0,.25);width:680px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;">
      <i class="fa-solid fa-file-medical" style="color:var(--primary);font-size:16px;"></i>
      <span style="font-size:14px;font-weight:700;line-height:22px;" id="rxModalTitle">처방전 상세</span>
      <button onclick="closeModal('rxModal')" style="margin-left:auto;display:flex;align-items:center;justify-content:center;width:24px;height:24px;flex-shrink:0;padding:0;border:none;border-radius:6px;background:none;font-size:16px;line-height:1;cursor:pointer;color:var(--gray-500);">×</button>
    </div>
    <div style="overflow-y:auto;padding:20px;" id="rxModalBody">
      <div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:8px;" id="rxModalFooter">
      <button onclick="closeModal('rxModal')" class="btn btn-outline btn-sm">닫기</button>
    </div>
  </div>
</div>

{{-- ══ 주문 상세 팝업 ══ --}}
<div id="orderModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border-radius:var(--radius-lg);box-shadow:0 20px 60px rgba(0,0,0,.25);width:720px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;">
      <i class="fa-solid fa-cart-shopping" style="color:var(--primary);font-size:16px;"></i>
      <span style="font-size:14px;font-weight:700;line-height:22px;" id="orderModalTitle">주문 상세</span>
      <button onclick="closeModal('orderModal')" style="margin-left:auto;display:flex;align-items:center;justify-content:center;width:24px;height:24px;flex-shrink:0;padding:0;border:none;border-radius:6px;background:none;font-size:16px;line-height:1;cursor:pointer;color:var(--gray-500);">×</button>
    </div>
    <div style="overflow-y:auto;padding:20px;" id="orderModalBody">
      <div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:8px;">
      <button onclick="closeModal('orderModal')" class="btn btn-outline btn-sm">닫기</button>
    </div>
  </div>
</div>
{{-- ══ 증빙 미리보기 팝업 ══
     발행된 세금계산서ㆍ현금영수증을 그 자리에서 펼쳐 본다. 예전에는 이 목록에서
     증빙이 나갔는지 보려면 주문 화면까지 들어가야 했다. --}}
<div id="proofModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border-radius:var(--radius-lg);box-shadow:0 20px 60px rgba(0,0,0,.25);width:900px;max-width:95vw;height:88vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;">
      <i id="proofModalIcon" class="fa-solid fa-receipt" style="color:var(--primary);font-size:16px;"></i>
      <span style="font-size:14px;font-weight:700;line-height:22px;" id="proofModalTitle">증빙</span>
      <span style="font-size:12px;color:var(--text-muted);" id="proofModalNo"></span>
      <button onclick="closeModal('proofModal')" style="margin-left:auto;display:flex;align-items:center;justify-content:center;width:24px;height:24px;flex-shrink:0;padding:0;border:none;border-radius:6px;background:none;font-size:16px;line-height:1;cursor:pointer;color:var(--gray-500);">×</button>
    </div>
    <div style="flex:1;min-height:0;background:var(--gray-100);">
      <iframe id="proofFrame" style="width:100%;height:100%;border:none;background:#fff;"></iframe>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:8px;">
      <a id="proofOpen" href="#" target="_blank" rel="noopener" class="btn btn-outline btn-sm">새 창으로</a>
      <button onclick="closeModal('proofModal')" class="btn btn-outline btn-sm">닫기</button>
    </div>
  </div>
</div>
@endpush

@push('scripts')
<script>
  // ── 모달 공통 ──────────────────────────────────────────────
  function openModal(id)  { const m = document.getElementById(id); m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  function closeModal(id) { document.getElementById(id).style.display = 'none'; document.body.style.overflow = ''; }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal('rxModal'); closeModal('orderModal'); closeProofModal(); }
  });
  document.addEventListener('click', e => {
    if (e.target.id === 'rxModal')    closeModal('rxModal');
    if (e.target.id === 'orderModal') closeModal('orderModal');
    if (e.target.id === 'proofModal') closeProofModal();
  });

  /* 창을 닫으면 파일도 놓아 준다 — src='' 로 두면 브라우저가 현재 주소를 다시 부른다 */
  function closeProofModal() {
    const f = document.getElementById('proofFrame');
    if (f) f.removeAttribute('src');
    closeModal('proofModal');
  }

  /** 발행된 증빙을 펼쳐 본다 — kind 는 'tax' 아니면 'cash' */
  window.openProofModal = function (row, kind) {
    const isTax = kind === 'tax';
    const url   = isTax ? row.tax_url : row.cash_url;
    if (!url) {
      showToast(isTax ? '발행된 세금계산서가 없습니다.' : '발행된 현금영수증이 없습니다.', 'warning');
      return;
    }

    document.getElementById('proofModalTitle').textContent = isTax ? '세금계산서' : '현금영수증';
    document.getElementById('proofModalIcon').className    = isTax ? 'fa-solid fa-file-invoice' : 'fa-solid fa-receipt';
    document.getElementById('proofModalNo').textContent    =
      (row.order_no || '') + (isTax ? (row.tax_no ? ' · 승인 ' + row.tax_no : '')
                                    : (row.cash_no ? ' · 승인 ' + row.cash_no : ''));
    document.getElementById('proofOpen').href  = url;
    document.getElementById('proofFrame').src  = url;
    openModal('proofModal');
  };

  /* 팝업 안의 「주문 보기」도 탭으로 연다 — 새 브라우저 창으로 튀지 않는다. */
  window.settlementOpenRxTab = function (ev, el, rxNo) {
    ev.preventDefault();
    const url = el.getAttribute('href');
    if (!url) return false;
    closeModal('rxModal');
    if (typeof window.ceOpenTab === 'function') window.ceOpenTab(url, '주문 - ' + (rxNo || ''), 'file-edit-02');
    else window.open(url, '_blank', 'noopener');
    return false;
  };

  // ── 처방전 상세 팝업 ────────────────────────────────────────
  document.querySelectorAll('.rx-popup-link').forEach(el => {
    el.addEventListener('click', () => openRxModal(el.dataset.url));
  });

  async function openRxModal(url) {
    document.getElementById('rxModalBody').innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';
    openModal('rxModal');
    try {
      const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const d    = await res.json();
      document.getElementById('rxModalTitle').textContent = `처방전 상세 — ${d.rx_number}`;

      // 배지 — 시안 규격(r6 · pad 2/6 · 11/500 · lh18). 색은 DS 토큰만 쓴다(초록·주황은 이 디자인에 없다)
      const badgeTone = {
        success:   ['var(--primary-50)',  'var(--primary-600)'],
        danger:    ['var(--alert-100)',   'var(--alert-500)'],
        warning:   ['var(--alert-100)',   'var(--alert-500)'],
        info:      ['var(--primary-50)',  'var(--primary)'],
        primary:   ['var(--primary-50)',  'var(--primary)'],
        secondary: ['var(--gray-100)',    'var(--gray-600)'],
      };
      const badge = (label, type) => {
        const [bg, fg] = badgeTone[type] || badgeTone.secondary;
        return `<span style="display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:500;line-height:18px;background:${bg};color:${fg};">${label}</span>`;
      };

      const row = (label, value) => `<div style="display:flex;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:12px;">
        <span style="width:110px;flex-shrink:0;color:var(--text-muted);">${label}</span>
        <span style="flex:1;font-weight:500;">${value ?? '-'}</span></div>`;

      const itemsHtml = d.items?.length ? `
        <table style="width:100%;border-collapse:collapse;font-size:11px;margin-top:4px;">
          <thead><tr style="background:var(--bg);">
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">제품명</th>
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">코드</th>
            <th style="padding:6px 8px;text-align:center;border:1px solid var(--border);">수량</th>
            <th style="padding:6px 8px;text-align:center;border:1px solid var(--border);">급여</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">단가</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">본인부담</th>
          </tr></thead>
          <tbody>${d.items.map(i => `<tr>
            <td style="padding:5px 8px;border:1px solid var(--border);">${i.product_name}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);font-family:monospace;">${i.product_code||'-'}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:center;">${i.quantity}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:center;">${i.nhis_status}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;">${fmt(i.insurance_price)}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary);">${fmt(i.patient_copay)}</td>
          </tr>`).join('')}</tbody>
        </table>` : '<div style="color:var(--text-muted);font-size:12px;padding:8px 0;">처방 품목 없음</div>';

      document.getElementById('rxModalBody').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">처방전 정보</div>
            ${row('처방번호', `<span style="font-family:monospace;color:var(--primary);">${d.rx_number}</span>`)}
            ${row('상태', badge(d.status_label, d.status_badge))}
            ${row('발행일', d.issued_date)}
            ${row('접수경로', d.upload_source)}
            ${row('접수일시', d.created_at)}
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">환자 정보</div>
            ${row('이름', `<b>${d.patient_name}</b>`)}
            ${row('생년월일', d.patient_birth)}
            ${row('주민번호', d.resident_no)}
            ${row('연락처', d.patient_mobile)}
          </div>
        </div>
        <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">병원·진료</div>
            ${row('병원명', d.hospital_name)}
            ${row('의사명', d.doctor_name)}
            ${row('진료과', d.department)}
            ${row('상병명', d.disease_name)}
            ${row('상병코드', d.disease_code)}
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">처방 수량</div>
            ${row('1일 투여량', d.daily_count != null ? d.daily_count + '회' : '-')}
            ${row('투여 일수', d.total_days  != null ? d.total_days  + '일' : '-')}
            ${row('총 수량', d.total_count != null ? d.total_count + '개' : '-')}
            ${row('담당자', d.assigned_user)}
          </div>
        </div>
        <div style="margin-top:16px;">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;">처방 품목</div>
          ${itemsHtml}
        </div>
        ${d.admin_note ? `<div style="margin-top:12px;background:var(--bg);border-radius:var(--radius);padding:10px 12px;font-size:12px;color:var(--text-secondary);"><b>메모:</b> ${d.admin_note}</div>` : ''}
      `;

      const footer = document.getElementById('rxModalFooter');
      footer.innerHTML = `
        <a href="${BASE_URL}/prescriptions/${d.rx_number ?? d.id}" class="btn btn-outline btn-sm"
           onclick="return settlementOpenRxTab(event, this, '${d.rx_number ?? ''}')"><i class="fa-solid fa-arrow-up-right-from-square"></i> 주문 보기</a>
        <button onclick="closeModal('rxModal')" class="btn btn-primary btn-sm">닫기</button>
      `;
    } catch(e) {
      document.getElementById('rxModalBody').innerHTML = '<div style="text-align:center;color:var(--danger);padding:24px;">불러오기 실패</div>';
    }
  }

  // ── 주문 상세 팝업 ────────────────────────────────────────
  document.querySelectorAll('.order-popup-link').forEach(el => {
    el.addEventListener('click', () => openOrderModal(el.dataset.url));
  });

  async function openOrderModal(url) {
    document.getElementById('orderModalBody').innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>';
    openModal('orderModal');
    try {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const d   = await res.json();
      document.getElementById('orderModalTitle').textContent = `주문 상세 — ${d.order_number}`;

      // 배지 — 시안 규격(r6 · pad 2/6 · 11/500 · lh18). 색은 DS 토큰만 쓴다(초록·주황은 이 디자인에 없다)
      const badgeTone = {
        success:   ['var(--primary-50)',  'var(--primary-600)'],
        danger:    ['var(--alert-100)',   'var(--alert-500)'],
        warning:   ['var(--alert-100)',   'var(--alert-500)'],
        info:      ['var(--primary-50)',  'var(--primary)'],
        primary:   ['var(--primary-50)',  'var(--primary)'],
        secondary: ['var(--gray-100)',    'var(--gray-600)'],
      };
      const badge = (label, type) => {
        const [bg, fg] = badgeTone[type] || badgeTone.secondary;
        return `<span style="display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:500;line-height:18px;background:${bg};color:${fg};">${label}</span>`;
      };
      const row   = (label, value) => `<div style="display:flex;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:12px;">
        <span style="width:110px;flex-shrink:0;color:var(--text-muted);">${label}</span>
        <span style="flex:1;font-weight:500;">${value ?? '-'}</span></div>`;
      const amtRow = (label, val, color='var(--text-primary)') => `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px;">
        <span style="color:var(--text-muted);">${label}</span>
        <span style="font-family:monospace;font-weight:500;color:${color};">${fmt(val)}원</span></div>`;

      const itemsHtml = d.items?.length ? `
        <table style="width:100%;border-collapse:collapse;font-size:11px;margin-top:4px;">
          <thead><tr style="background:var(--bg);">
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">제품명</th>
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">코드</th>
            <th style="padding:6px 8px;text-align:center;border:1px solid var(--border);">수량</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">단가</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">청구</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">본인부담</th>
          </tr></thead>
          <tbody>${d.items.map(i => `<tr>
            <td style="padding:5px 8px;border:1px solid var(--border);">${i.product_name}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);font-family:monospace;">${i.product_code}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:center;">${i.quantity}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;">${fmt(i.unit_price)}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary-600);">${fmt(i.nhis_amount)}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary);">${fmt(i.patient_copay)}</td>
          </tr>`).join('')}
          <tr style="background:var(--bg);font-weight:700;">
            <td colspan="4" style="padding:6px 8px;border:1px solid var(--border);text-align:right;">합계</td>
            <td style="padding:6px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary-600);">${fmt(d.items.reduce((s,i)=>s+(i.nhis_amount||0),0))}</td>
            <td style="padding:6px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary);">${fmt(d.items.reduce((s,i)=>s+(i.patient_copay||0),0))}</td>
          </tr></tbody>
        </table>` : '<div style="color:var(--text-muted);font-size:12px;padding:8px 0;">주문 품목 없음</div>';

      const tossHtml = d.toss_payment ? `
        <div style="margin-top:14px;">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;">가상계좌 (토스페이먼츠)</div>
          <div style="background:var(--bg);border-radius:var(--radius);padding:10px 14px;">
            ${row('상태', badge(d.toss_payment.status_label, d.toss_payment.status_badge))}
            ${row('은행', d.toss_payment.bank_name)}
            ${row('계좌번호', `<span style="font-family:monospace;font-weight:700;">${d.toss_payment.account_number}</span>`)}
            ${row('금액', `<span style="font-family:monospace;">${fmt(d.toss_payment.amount)}원</span>`)}
            ${row('만료일시', d.toss_payment.due_date || '-')}
            ${row('입금확인', d.toss_payment.deposited_at ? `<span style="color:var(--primary-600);">${d.toss_payment.deposited_at}</span>` : '-')}
          </div>
        </div>` : '';

      document.getElementById('orderModalBody').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">주문 정보</div>
            ${row('주문번호', `<span style="font-family:monospace;color:var(--primary);">${d.order_number}</span>`)}
            ${row('주문상태', badge(d.status_label, d.status_badge))}
            ${row('청구', d.nhis_status)}
            ${row('접수일시', d.created_at)}
            ${row('배송완료일', d.delivered_at || '-')}
            ${row('담당자', d.creator)}
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">환자 정보</div>
            ${row('이름', `<b>${d.patient_name}</b>`)}
            ${row('연락처', d.patient_mobile)}
            ${row('배송주소', d.shipping_address)}
            ${row('송장번호', d.tracking_number)}
          </div>
        </div>
        <div style="margin-top:14px;">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">금액 정보</div>
          <div style="background:var(--bg);border-radius:var(--radius);padding:10px 14px;">
            ${amtRow('단가',        d.unit_price)}
            ${amtRow('청구액', d.nhis_amount,    'var(--primary-600)')}
            ${amtRow('본인부담금', d.patient_copay, 'var(--primary)')}
            ${amtRow('배송비',      d.shipping_fee)}
            ${amtRow('환급',   d.nhis_reimb,    'var(--primary-600)')}
            <div style="display:flex;justify-content:space-between;padding:8px 0 2px;font-size:13px;font-weight:700;line-height:21px;">
              <span>총 주문금액</span>
              <span style="font-family:monospace;color:var(--primary);">${fmt(d.total_amount)}원</span>
            </div>
          </div>
        </div>
        <div style="margin-top:16px;">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;">주문 품목</div>
          ${itemsHtml}
        </div>
        ${tossHtml}
        ${d.note ? `<div style="margin-top:12px;background:var(--bg);border-radius:var(--radius);padding:10px 12px;font-size:12px;color:var(--text-secondary);"><b>메모:</b> ${d.note}</div>` : ''}
      `;
    } catch(e) {
      document.getElementById('orderModalBody').innerHTML = '<div style="text-align:center;color:var(--danger);padding:24px;">불러오기 실패</div>';
    }
  }

  function fmt(v) { return v != null ? Number(v).toLocaleString('ko-KR') : '0'; }

  // 가상계좌 발급
  async function issueVA(orderId, btn) {
    if (!await ceConfirm('가상계좌를 발급하시겠습니까?', { confirmText: '발급' })) return;
    BtnState.loading(btn, '발급 중...');
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        BtnState.success(btn, '발급 완료');
        const smsNote = data.sms_sent ? ' · 안내 SMS 발송됨' : ' · ⚠️ SMS 미발송';
        showToast(`✅ ${data.bank_name} ${data.account_number} 발급 완료${smsNote}`, data.sms_sent ? 'success' : 'warning');
        setTimeout(() => location.reload(), 1400);
      } else {
        showToast(data.message || '발급 실패', 'danger');
        BtnState.error(btn, '발급 실패');
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      BtnState.error(btn, '오류');
    }
  }

  // 가상계좌 안내 SMS 재발송
  async function resendVaSms(orderId, btn) {
    if (!await ceConfirm('환자에게 가상계좌 안내 SMS를 재발송하시겠습니까?', { confirmText: '재발송' })) return;
    BtnState.loading(btn, '발송 중...');
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
      });
      const data = await res.json();
      showToast(data.message || (data.success ? '발송 완료' : '발송 실패'), data.success ? 'success' : 'danger');
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  }

  // 입금 상태 실시간 조회
  async function checkStatus(orderId, btn) {
    BtnState.loading(btn, '확인 중...');
    try {
      const res = await fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (data.success) {
        const msg = data.status === 'DONE' ? '✅ 입금이 확인되었습니다.' : `현재 상태: ${data.status_label}`;
        showToast(msg, data.status === 'DONE' ? 'success' : 'info');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.message || '조회 실패', 'danger');
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  }
</script>

{{-- ── wwGrid 마운트 + 외부 액션 버튼 (활성 탭 기준) ── --}}
<script>
(function () {
  const TAB       = @json($tab);
  const GRID_DATA = @json($gridData);
  const GRID_COLS = @json($gridColumns);

  /* 네 화면이 함께 쓰던 칸을 정산 탭에 잇는다(요청서 3쪽 — 「모든 화면의 항목이 최종으로
     보이게」). 가상계좌 탭은 주문이 아니라 발급 건을 세는 자리라 잇지 않는다.
     여기서 이어야 아래의 renderer 붙이기(결제수단 칸)가 이 칸들에도 닿는다. */
  if (TAB === 'settlement') GRID_COLS.push(...ceMoneyCols(), ...ceWwCols());
  const ORDERS_BASE = @json(url('settlement/orders'));   // + '/{id}/virtual-account' 등

  const mountEl = document.getElementById(TAB === 'virtual_account' ? 'vaGrid' : 'settlementGrid');
  if (!mountEl) return;

  /* 입금 확인은 목록 안에서 한다.
     줄을 체크하고 위쪽 단추로 누르게 두면, 통장을 한 줄씩 맞춰 보는 일과 손이 어긋난다 —
     보고 있는 그 줄에서 바로 세운다.

     토스가 확인한 것은 단추를 두지 않는다. 사람이 손댈 일이 아니다. */
  const CAN_CONFIRM = @json(auth()->user()?->can('settlement.send') ?? true);

  function depositCell(v, row, rowIndex) {
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;align-items:center;gap:6px;justify-content:flex-end;';

    const amount = document.createElement('span');
    amount.textContent = (v === undefined || v === null || v === '') ? '-' : v;
    box.appendChild(amount);

    if (row.deposit_done && !row.deposit_hand) {
      const tag = document.createElement('span');
      tag.textContent = '토스';
      tag.style.cssText = 'font-size:10px;color:var(--text-muted);flex-shrink:0;';
      box.appendChild(tag);
      return box;
    }

    if (!CAN_CONFIRM) return box;

    /* 목록 안 단추다. 위쪽 단추줄과 같은 클래스(.ds-btn)를 주면 「단추줄의 단추」를
       찾는 코드에 줄 수만큼 끼어든다 — 생김새만 같게 두고 클래스는 따로 쓴다. */
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dep-cell-btn';
    btn.style.cssText = 'height:22px;padding:0 8px;font-size:11px;flex-shrink:0;cursor:pointer;'
                      + 'border:1px solid var(--gray-200);border-radius:6px;background:var(--gray-0);'
                      + 'color:var(--gray-1000);line-height:1;';
    btn.textContent = row.deposit_hand ? '취소' : '입금 확인';
    if (row.deposit_hand) btn.style.color = 'var(--danger)';
    btn.onclick = (ev) => { ev.stopPropagation(); depositAct(row, rowIndex, btn); };
    box.appendChild(btn);

    return box;
  }

  /* 정산 탭은 「입금확인」 칸에서, 가상계좌 탭은 「확인」 칸에서 세운다 —
     둘 다 그 줄의 입금을 말하는 자리다. */
  const _actCol = TAB === 'virtual_account' ? 'deposit_by' : 'deposit';
  GRID_COLS.forEach(c => { if (c.name === _actCol) c.renderer = depositCell; });

  /* ── 증빙 — 입금 확인 다음 칸 ────────────────────────────────
     입금이 확인되면 청구전략대로 세금계산서나 현금영수증이 자동으로 나간다. 나갔는지
     보려면 지금까지는 주문 화면까지 들어가야 했다. 그 줄에서 바로 펼쳐 본다.
     발행되지 않은 것은 흐린 채로 서 있다 — 칸을 비워 두면 왜 없는지 알 수 없다. */
  function proofBtn(label, on, onClick, offTitle) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'proof-cell-btn';
    b.textContent = label;
    b.style.cssText = 'height:22px;padding:0 7px;font-size:10px;line-height:1;border-radius:6px;'
                    + 'border:1px solid var(--gray-200);background:var(--gray-0);white-space:nowrap;'
                    + (on ? 'color:var(--gray-1000);cursor:pointer;'
                          : 'color:var(--gray-400);cursor:default;border-style:dashed;');
    b.title = on ? label + ' 미리보기' : offTitle;
    if (on) b.onclick = (ev) => { ev.stopPropagation(); onClick(); };
    else    b.onclick = (ev) => { ev.stopPropagation(); };
    return b;
  }

  function proofCell(v, row) {
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;align-items:center;gap:4px;justify-content:center;';
    box.appendChild(proofBtn('세금계산서', !!row.tax_issued,
      () => openProofModal(row, 'tax'),  '발행된 세금계산서가 없습니다'));
    box.appendChild(proofBtn('현금영수증', !!row.cash_issued,
      () => openProofModal(row, 'cash'), '발행된 현금영수증이 없습니다'));
    return box;
  }

  GRID_COLS.forEach(c => { if (c.name === 'proof') c.renderer = proofCell; });

  /* 정산 상태 — 눌러서 옮긴다(요청서 12쪽). 마감은 셈을 닫는 것이고 확정은 잠그는
     것이라, 확정된 건은 더 누를 것이 없다. */
  const SETTLE = @json(\App\Models\Order::SETTLE_STATUS_LABELS);
  const SETTLE_TONE = { open:'', closed:'var(--primary)', confirmed:'var(--success)',
                        rejected:'var(--danger)', on_hold:'var(--warning)', cancelled:'var(--text-muted)' };

  function settleCell(v, row) {
    const wrap = document.createElement('div');
    const tag = document.createElement('span');
    tag.textContent = v || '';
    tag.style.cssText = 'font-weight:700;font-size:12px;color:' + (SETTLE_TONE[row.settle_key] || 'var(--text-secondary)');

    if (row.settle_key === 'confirmed') { wrap.appendChild(tag); return wrap; }

    const sel = document.createElement('select');
    sel.className = 'form-control';
    sel.style.cssText = 'height:26px;font-size:11px;padding:0 4px;min-width:78px;';
    Object.entries(SETTLE).forEach(([k, label]) => {
      const o = document.createElement('option');
      o.value = k; o.textContent = label; o.selected = k === row.settle_key;
      sel.appendChild(o);
    });
    sel.addEventListener('click', (e) => e.stopPropagation());
    sel.addEventListener('change', () => settleMove(row, sel.value, sel));
    wrap.appendChild(sel);
    return wrap;
  }
  GRID_COLS.forEach(c => { if (c.name === 'settle') c.renderer = settleCell; });

  async function settleMove(row, status, sel) {
    /* 확정은 되돌릴 수 없다 — 누르기 전에 한 번 묻는다. 그 밖의 상태는 다시 옮길 수
       있으므로 묻지 않는다. */
    if (status === 'confirmed'
        && !confirm(`${row.order_no} 을(를) 확정합니다. 확정하면 되돌릴 수 없습니다. 계속할까요?`)) {
      sel.value = row.settle_key; return;
    }

    let reason = null;

    const send = async (r) => {
      const res = await fetch(`${BASE_URL}/settlement/orders/${row.id}/settle`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: JSON.stringify({ status, reason: r }),
      });
      return [res.status, await res.json()];
    };

    try {
      let [code, d] = await send(reason);

      /* 받을 돈이 남았는데 닫으려 하면 서버가 까닭을 묻는다. 막는 것이 아니라
         적어 달라는 것이라(3PL 샘플로 입고 잡고 닫는 일이 있다), 그 자리에서 받는다. */
      if (code === 422) {
        reason = prompt(d.message + '\n\n사유:');
        if (!reason) { sel.value = row.settle_key; return; }
        [code, d] = await send(reason);
      }

      showToast(d.message || (d.success ? '변경했습니다.' : '변경하지 못했습니다.'),
                d.success ? 'success' : 'danger');
      if (d.success) setTimeout(() => location.reload(), 600);
      else sel.value = row.settle_key;
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      sel.value = row.settle_key;
    }
  }

  /* ── 결제 방식 — 고르는 순간이 곧 입금 확인이다 ──────────────
     입금이 확인되기 전에는 「무엇으로 받았는가」를 말할 수 없다. 그래서 칸은 「-」로
     비어 있고, 통장ㆍ단말을 보고 방식을 고르는 순간 그 건은 받은 것이 된다.
     이미 받은 건은 글자만 선다 — 낸 방식은 지난 일이고, 고치면 기록과 어긋난다. */
  const PAY_METHODS = @json(\App\Models\PaymentLink::METHODS);

  function payMethodCell(v, row, rowIndex) {
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;align-items:center;justify-content:center;';

    if (row.deposit_done || !CAN_CONFIRM) {
      box.textContent = v || '-';
      return box;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pay-cell-btn';
    btn.style.cssText = 'height:22px;padding:0 8px;font-size:11px;cursor:pointer;line-height:1;'
                      + 'border:1px dashed var(--gray-300);border-radius:6px;background:transparent;'
                      + 'color:var(--gray-1000);';
    btn.textContent = '-';
    btn.title = '무엇으로 받았는지 고르면 입금 확인됩니다';
    btn.onclick = (ev) => { ev.stopPropagation(); payMethodPick(ev.currentTarget, row, rowIndex); };
    box.appendChild(btn);
    return box;
  }

  GRID_COLS.forEach(c => { if (c.name === 'pay_method') c.renderer = payMethodCell; });

  let _payPop = null;

  function payMethodClose() {
    if (_payPop) { _payPop.remove(); _payPop = null; }
  }
  document.addEventListener('click', payMethodClose);

  function payMethodPick(anchor, row, rowIndex) {
    payMethodClose();

    const pop = document.createElement('div');
    _payPop = pop;
    pop.style.cssText = 'position:fixed;z-index:1200;background:var(--bg-card);border:1px solid var(--primary);'
                      + 'border-radius:var(--radius);box-shadow:0 8px 24px rgba(0,0,0,.18);padding:4px;'
                      + 'display:flex;flex-direction:column;gap:2px;min-width:130px;';
    pop.onclick = (ev) => ev.stopPropagation();

    Object.entries(PAY_METHODS).forEach(([key, label]) => {
      const item = document.createElement('button');
      item.type = 'button';
      const on = key === row.pay_method_key;
      item.style.cssText = 'text-align:left;padding:6px 10px;font-size:12px;border:none;cursor:pointer;'
                         + 'border-radius:6px;background:' + (on ? 'var(--primary-light)' : 'transparent')
                         + ';color:' + (on ? 'var(--primary)' : 'var(--gray-1000)')
                         + ';font-weight:' + (on ? '700' : '400') + ';';
      item.textContent = label;
      item.onclick = () => payMethodSet(row, rowIndex, key);
      pop.appendChild(item);
    });

    document.body.appendChild(pop);

    /* 칸 바로 아래에 붙인다. 화면 아래끝을 넘으면 위로 뒤집는다 —
       목록 끝줄에서 고르려면 창이 화면 밖에 서기 때문이다. */
    const r = anchor.getBoundingClientRect();
    const h = pop.offsetHeight;
    const top = (r.bottom + h + 8 > window.innerHeight) ? r.top - h - 4 : r.bottom + 4;
    pop.style.left = Math.min(r.left, window.innerWidth - pop.offsetWidth - 8) + 'px';
    pop.style.top  = Math.max(8, top) + 'px';
  }

  /* 바뀐 값을 그 줄에만 입힌다 — 화면을 다시 부르지 않는다.
     칸 둘(결제 방식ㆍ입금확인)과 위쪽 요약 카드가 함께 움직여야 한 화면이 한 말을 한다. */
  function applyDeposit(row, rowIndex, { done, label, methodKey, amount }) {
    row.deposit_done = done;
    row.deposit_hand = done;
    row.deposit      = done ? Number(amount || 0).toLocaleString() : '-';
    row.deposit_by   = done ? '담당자 확인' : '-';
    row.pay_method     = done ? (label ?? row.pay_method) : '-';
    row.pay_method_key = done ? (methodKey ?? row.pay_method_key) : null;
    if ('va_status' in row) row.va_status = done ? '입금완료' : (row.va_account === '미발급' ? '미발급' : '입금대기');

    ['pay_method', 'deposit', 'deposit_by', 'va_status'].forEach(name => {
      if (GRID_COLS.some(c => c.name === name)) grid._refreshCell(rowIndex, name);
    });

    sumCardShift(done ? 1 : -1, Number(amount || 0));
  }

  /* 요약 카드(입금 완료ㆍ입금 대기ㆍ대기 금액)를 그만큼 옮긴다. 가상계좌 탭에만 있다. */
  function sumCardShift(dir, amount) {
    const pick = (cls) => document.querySelector('.sum-card.' + cls + ' .sum-card-val');
    const bump = (el, delta) => {
      if (!el) return;
      const n = Number((el.firstChild?.textContent ?? el.textContent).replace(/[^0-9-]/g, '')) || 0;
      const next = Math.max(0, n + delta);
      if (el.firstChild) el.firstChild.textContent = next.toLocaleString();
      else el.textContent = next.toLocaleString();
    };
    bump(pick('green'),  dir);
    bump(pick('orange'), -dir);
    bump(pick('red'),    -dir * amount);
  }

  async function payMethodSet(row, rowIndex, method) {
    payMethodClose();
    if (method === row.pay_method_key) return;

    try {
      const res = await fetch(ORDERS_BASE + '/' + row.id + '/pay-method', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: JSON.stringify({ method }),
      });
      const d = await res.json();
      if (!d.success) { showToast(d.message || '바꾸지 못했습니다.', 'danger'); return; }

      applyDeposit(row, rowIndex, { done: true, label: d.label, methodKey: d.method, amount: d.amount });
      showToast(d.message, 'success');
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    }
  }

  const grid = new wwGrid({
    el: mountEl,
    // 엑셀 저장은 결과바 버튼으로 옮겼다(동작은 downloadExcel() 동일).
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    footer: { total: true, selected: false, modified: false },
    columns: GRID_COLS,
    data: GRID_DATA,
  });
  window.__settlementGrid = grid;                  // 결과바의 엑셀 저장 버튼이 이걸 부른다
  window.dsBindSelCount(grid, 'settleSelCount');   // 결과바 '선택 N건' 표시를 그리드 선택에 연결

  // ── 조회결과/상세내용 패널 탭 + 더블클릭 주문상세(iframe 없이 인페이지 주입) ──
  const ORDER_SHOW_BASE = @json(url('orders'));
  window.pnlShow = function (which) {
    const list = document.getElementById('pnlList'), det = document.getElementById('pnlDetail');
    if (!list || !det) return;
    list.style.display = which === 'detail' ? 'none' : '';
    det.style.display  = which === 'detail' ? '' : 'none';
    const bl = document.getElementById('pnlBtnList'), bd = document.getElementById('pnlBtnDetail');
    bl && bl.classList.toggle('active', which !== 'detail');
    bd && bd.classList.toggle('active', which === 'detail');
  };
  window.pnlLoadDetail = async function (url) {
    const cont = document.getElementById('pnlDetailContent');
    if (!cont) return;
    const empty = document.getElementById('pnlEmpty'); empty && (empty.style.display = 'none');
    cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i><div style="margin-top:8px;">불러오는 중...</div></div>';
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
      cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--danger);">상세를 불러오지 못했습니다.</div>';
    }
  };
  // 행 더블클릭 → 상세내용 탭에 주문 상세(내부 탭 포함) 인페이지 표시
  mountEl.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row && row.id) window.pnlLoadDetail(ORDER_SHOW_BASE + '/' + row.id + '?partial=1');
  });

  // 한 건만 체크됐는지 검증 후 해당 행 반환 (아니면 경고 후 null)
  function oneChecked() {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('대상 행을 체크하세요.', 'warning'); return null; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return null; }
    return c[0];
  }

  // ── 정산 탭: 상세 팝업(로직 보존 — openRxModal/openOrderModal 재사용) ──
  /* 골라 둔 줄의 주문 등록 화면을 탭으로 연다.
     예전에는 팝업으로 요약만 보여 주었다 — 거기서 고칠 수 있는 것이 없어, 결국
     다시 주문 화면을 찾아 열어야 했다. 처음부터 그 화면으로 보낸다. */
  window.settlementViewRx = function () {
    const r = oneChecked(); if (!r) return;
    if (!r.rx_open_url) { showToast('처방전이 없는 주문입니다.', 'warning'); return; }
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(r.rx_open_url, '주문 - ' + (r.rx_number || r.order_no || ''), 'file-edit-02');
    } else {
      window.open(r.rx_open_url, '_blank', 'noopener');
    }
  };
  window.settlementViewOrder = function () {
    const r = oneChecked(); if (!r) return;
    if (!r.order_url) { showToast('주문 상세를 열 수 없습니다.', 'warning'); return; }
    openOrderModal(r.order_url);
  };

  // ── 가상계좌 탭: 기존 함수(issueVA/checkStatus/resendVaSms) 로직·URL·CSRF 보존, 트리거만 외부버튼+체크행 ──
  // 기존 함수는 btn.dataset.url 로 fetch 하므로, 체크된 행 id 로 라우트 URL 을 구성해 주입 후 호출한다.
  window.vaIssueSelected = function (btn) {
    const r = oneChecked(); if (!r) return;
    btn.dataset.url = ORDERS_BASE + '/' + r.id + '/virtual-account';   // settlement.issue-va (POST)
    issueVA(r.id, btn);
  };
  window.vaCheckSelected = function (btn) {
    const r = oneChecked(); if (!r) return;
    btn.dataset.url = ORDERS_BASE + '/' + r.id + '/payment-status';    // settlement.check-status (GET)
    checkStatus(r.id, btn);
  };
  /* 담당자가 통장을 보고 세우는 입금 확인.
     이미 세워 둔 건이면 되돌린다 — 잘못 누른 것을 풀 길이 있어야 한다. */
  async function depositAct(r, rowIndex, btn) {
    if (r.deposit_done && !r.deposit_hand) {
      showToast('토스에서 이미 입금이 확인된 주문입니다.', 'warning');
      return;
    }

    const base = ORDERS_BASE + '/' + r.id + '/confirm-deposit';
    const due  = Number(r.deposit_due || 0);

    if (r.deposit_hand) {
      /* 입금이 없던 일이 되면 그 돈으로 나간 증빙도 없던 일이 된다 — 함께 취소한다.
         묻고 고르게 두면 「입금은 없던 일인데 신고는 살아 있는」 줄이 남는 길이 열린다.

         다만 팝빌 취소는 국세청 실취소이고 되살릴 수 없다. 고르게 하지 않는 대신,
         무엇이 함께 사라지는지 되돌리기 전에 그대로 적어 둔다. */
      const issued = [
        r.tax_issued  ? '세금계산서' + (r.tax_no  ? ` (승인 ${r.tax_no})`  : '') : '',
        r.cash_issued ? '현금영수증' + (r.cash_no ? ` (승인 ${r.cash_no})` : '') : '',
      ].filter(Boolean);

      const ok = await ceConfirm(
        `${r.order_no} 의 입금 확인을 취소합니다.
다시 「입금 대기」로 돌아갑니다.` + (issued.length ? `

이 건에 나가 있는 ${issued.join(' 과 ')} 도 함께 취소됩니다.
※ 팝빌로 국세청에 취소 신고가 들어가고, 주문에 첨부된 증빙 PDF도 함께 삭제됩니다.
   취소한 증빙은 되살릴 수 없고 다시 발행해야 합니다.` : ''),
        { title: '입금 확인 취소', confirmText: '취소하기', tone: 'danger' });
      if (!ok) return;

      await vaDepositCall(btn, base, 'DELETE', null, r, rowIndex, 0);
      return;
    }

    /* 무엇으로 받았는지 모르는 채로 세우지 않는다.
       결제 방식은 이후 절차를 가른다 — 현금영수증은 가상계좌ㆍ무통장입금에만 나가고
       카드결제는 카드매출전표가 증빙이다. 방식이 비어 있으면 그 자리에서 고르게 한다. */
    if (!r.pay_method_key) {
      showToast('결제 방식을 먼저 고르십시오.', 'warning');
      const cell = btn.closest('tr')?.querySelector('.pay-cell-btn');
      if (cell) payMethodPick(cell, r, rowIndex);
      return;
    }

    /* 묻지 않고 바로 세운다. 통장을 한 줄씩 맞춰 보는 일이라 확인 창이 매번 끼면
       손이 끊긴다 — 잘못 눌러도 같은 자리에서 「취소」로 되돌린다. */
    await vaDepositCall(btn, base, 'POST', { amount: due }, r, rowIndex, due);
  };

  async function vaDepositCall(btn, url, method, body, row, rowIndex, amount) {
    BtnState.loading(btn, '처리 중...');
    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: body ? JSON.stringify(body) : null,
      });
      const d = await res.json();
      BtnState.reset(btn);
      if (!d.success) { showToast(d.message || '처리하지 못했습니다.', 'danger'); return; }
      applyDeposit(row, rowIndex, { done: method === 'POST', amount });

      /* 증빙이 취소됐으면 그 줄의 단추도 그 자리에서 흐려져야 한다 —
         화면을 다시 읽지 않는 것이 이 목록의 약속이다. */
      if ('tax_issued' in d)  { row.tax_issued  = d.tax_issued;  }
      if ('cash_issued' in d) { row.cash_issued = d.cash_issued; }
      if ('tax_issued' in d || 'cash_issued' in d) {
        if (GRID_COLS.some(c => c.name === 'proof')) grid._refreshCell(rowIndex, 'proof');
      }

      showToast(d.message, (d.mismatch || d.doc_failed) ? 'warning' : 'success');
    } catch (e) {
      BtnState.reset(btn);
      showToast('오류가 발생했습니다.', 'danger');
    }
  }

  window.vaResendSelected = function (btn) {
    const r = oneChecked(); if (!r) return;
    btn.dataset.url = ORDERS_BASE + '/' + r.id + '/resend-va-sms';     // settlement.resend-va-sms (POST)
    resendVaSms(r.id, btn);
  };
})();
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '정산 조회 필터', body: '기간과 상태로 정산 대상 주문을 조회합니다. 엑셀 다운로드도 이 화면에서 가능합니다.' },
  { selector: '#settlementGrid, #vaGrid', title: '정산/가상계좌 목록', body: '주문 기준 정산 현황과 토스페이먼츠 가상계좌 현황입니다. 행을 체크한 뒤 상단 버튼으로 상세·발급·입금확인·SMS를 실행합니다.' },
];
</script>
@endpush
