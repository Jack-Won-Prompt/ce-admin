{{-- resources/views/settlement/index.blade.php --}}
@extends('layouts.app')

@section('title', '정산/회계')
@section('page-title', '정산/회계')
@section('breadcrumb', '홈 / 청구·회계 / 정산')

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
  .pnl-tabs .pnl-back { margin-left: auto; align-self: center; display: none; }
  .pnl-tabs #pnlBtnDetail.active ~ .pnl-back { display: inline-flex; }
  /* 결과바 '선택 N건' — 13/500 · gray-600, 숫자만 primary-400 */
  /* 기간 구분자 — 예전 선택자(.filter-sep)를 함께 남기되, 전역 .filter-sep 은
     세로 구분선(1×20px · 회색 배경)이라 여기서 그 형태를 되돌려 놓는다.
     되돌리지 않으면 '~' 글자가 1px 폭 회색 막대 밖으로 삐져나온다.
     색은 시안 324:60 기준 #101317 (전역 .ds-field-sep 은 gray-400 이라 여기서만 덮는다). */
  .filter-sep { width: auto; height: auto; background: none; margin: 0;
    color: var(--gray-1000); font-size: 13px; font-weight: 400; line-height: 21px; flex-shrink: 0; }
  /* 기간 두 입력 높이 — 시안 324:60 은 검색어·주문 상태와 똑같은 135×32 다.
     전역 .form-control 에 height 선언이 없어 높이가 내용에서 나오는데,
     input[type=date] 는 크롬 섀도 DOM 안쪽이 22px 라 5+22+5+2 = 34 가 된다.
     같은 줄의 검색어 input·주문 상태 select 는 5+20+5+2 = 32 라 두 상자만 2px 크다.
     그 2px 이 필드(61→63) · 검색 카드(140→142) · 조회 버튼 기준선까지 밀고 있었다.
     전역 .form-control 은 손대지 않고 이 화면 기간 입력만 32 로 눌러 맞춘다
     (scrollHeight 30 = clientHeight 30 — 글자가 잘리지 않는다). */
  .ds-field-range input[type="date"].form-control { height: 32px; }

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
</style>
@endpush

@section('content')

  {{-- 탭 — 표준 상태 칩(h31 · r999 · 12/700), 건수는 16×16 정원 배지 --}}
  <div class="ds-chips settle-tabs">
    <a href="{{ route('settlement.index', ['tab' => 'settlement', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
       class="ds-chip settle-tab {{ $tab === 'settlement' ? 'active' : '' }}">
      <i class="fa-solid fa-calculator"></i> 정산 현황
      <span class="ds-chip-count">{{ $summary['total_orders'] }}</span>
    </a>
    <a href="{{ route('settlement.index', ['tab' => 'virtual_account']) }}"
       class="ds-chip settle-tab {{ $tab === 'virtual_account' ? 'active' : '' }}">
      <i class="fa-solid fa-building-columns"></i> 가상계좌 매칭
      @if(($vaStats['waiting'] ?? 0) > 0)
        <span class="ds-chip-count">{{ $vaStats['waiting'] }}</span>
      @endif
    </a>
  </div>

  @if($tab === 'settlement')
  {{-- ══════════════ 정산 현황 ══════════════ --}}

    {{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래 · 9열 그리드 --}}
    <form method="GET" action="{{ route('settlement.index') }}" class="ds-filter-card">
      <input type="hidden" name="tab" value="settlement">
      {{-- 시안 324:60 — 검색어(span-2 · 295) → 기간(span-2 · 295) → 주문 상태(1열 · 140) 순으로
           왼쪽에 몰리고 오른쪽 4열은 비운다. 기간 두 입력은 span-2 안에서 135+8+'~'+8+135 로 나뉜다. --}}
      <div class="ds-filter-fields">
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">검색어</label>
          <input type="text" name="search" class="form-control" placeholder="주문번호·환자명·제품명" value="{{ request('search') }}">
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
      </div>
    </form>

    <div class="summary-grid">
      <div class="sum-card blue">
        <div class="sum-card-label"><i class="fa-solid fa-cart-shopping"></i> 총 주문 건수</div>
        <div class="sum-card-val">{{ number_format($summary['total_orders']) }}<span>건</span></div>
        <div class="sum-card-sub">조회 기간 내</div>
      </div>
      <div class="sum-card blue">
        <div class="sum-card-label"><i class="fa-solid fa-won-sign"></i> 총 주문 금액</div>
        <div class="sum-card-val">{{ number_format($summary['total_amount']) }}<span>원</span></div>
        <div class="sum-card-sub">배송비 포함</div>
      </div>
      <div class="sum-card green">
        <div class="sum-card-label"><i class="fa-solid fa-hospital"></i> 청구 금액</div>
        <div class="sum-card-val">{{ number_format($summary['nhis_amount']) }}<span>원</span></div>
        <div class="sum-card-sub">급여 청구분</div>
      </div>
      <div class="sum-card orange">
        <div class="sum-card-label"><i class="fa-solid fa-user"></i> 본인부담금</div>
        <div class="sum-card-val">{{ number_format($summary['patient_copay']) }}<span>원</span></div>
        <div class="sum-card-sub">가상계좌 수납 대상</div>
      </div>
    </div>

    {{-- 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 패널 탭과 그리드 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-bar">
        <div class="ds-grid-bar-left">
          <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
          <span class="ds-grid-sel">선택 <b id="settleSelCount">0</b>건</span>
          {{-- 보조 합계 4개 — 시안에서는 독립된 카드 줄이 아니라 결과바 왼쪽 인라인이다.
               블록은 그대로 옮기기만 했고, 배열의 셋째 칸(값 색)만 시안 값으로 바꿨다.
               시안 324:60 Frame 48101508 은 네 항목 모두 값 숫자가 #333940(gray-800) 한 가지다.
               원래 있던 var(--success)(초록) · var(--warning)(주황) 은 DS 26색 밖이고 시안 어디에도 없다.
               var(--text-primary) 도 gray-1000 이라 시안보다 두 톤 어둡다.
               (단위 글자 '원·건'은 시안이 #656C74 지만 값이 한 문자열이라 나누지 못했다 — 숫자 색에 맞췄다) --}}
          <div class="sum-mini-row">
            @foreach([['환급 확정', number_format($summary['nhis_reimb']).'원', 'var(--gray-800)'], ['배송비 합계', number_format($summary['shipping_fee']).'원', 'var(--gray-800)'], ['대기 중 주문', $statusCounts['pending'].'건', 'var(--gray-800)'], ['배송 완료', $statusCounts['delivered'].'건', 'var(--gray-800)']] as [$lbl, $val, $color])
            <div class="sum-mini">
              <span class="sum-mini-label">{{ $lbl }}</span>
              <span class="sum-mini-val" style="color:{{ $color }};">{{ $val }}</span>
            </div>
            @endforeach
          </div>
        </div>
        <div class="ds-grid-bar-right">
          {{-- 조회 기간 표시는 검색 카드 오른쪽 끝에 있던 것을 결과바 안내문으로 합쳤다. --}}
          <span class="ds-grid-hint">{{ $dateFrom }} ~ {{ $dateTo }} · 행을 <b>더블클릭</b>하면 상세내용 탭에서 주문 상세 확인</span>
          {{-- 그리드 툴바에 있던 엑셀 저장을 결과바로 옮겼다(동작은 downloadExcel() 동일) --}}
          <button type="button" class="ds-btn" onclick="window.__settlementGrid?.downloadExcel()">엑셀 저장</button>
          <button type="button" class="ds-btn" onclick="settlementViewRx()">
            <i class="fa-solid fa-file-medical"></i> 처방 상세(선택)
          </button>
        </div>
      </div>

      <div class="ds-grid-card">
        {{-- 패널 탭: 조회 결과 / 상세 내용 --}}
        <div class="pnl-tabs">
          <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과</button>
          <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세 내용</button>
          {{-- 상세 내용 패널 맨 위에 있던 '조회결과로' 버튼을 여기로 옮겼다(상세 내용 탭일 때만 보인다) --}}
          <button type="button" class="ds-btn pnl-back" onclick="pnlShow('list')"><i class="fa-solid fa-arrow-left"></i> 조회결과로</button>
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
        {{-- 폭은 정산 현황 탭에서 확정된 규격을 따른다 — 검색어 span-2(295) · 선택 1열(140) --}}
        <div class="ds-filter-field span-2">
          <label class="ds-field-label">검색어</label>
          <input type="text" name="va_search" class="form-control" placeholder="주문번호·환자명·계좌번호" value="{{ request('va_search') }}">
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

    {{-- 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 그리드 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-bar">
        <div class="ds-grid-bar-left">
          <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
          <span class="ds-grid-sel">선택 <b id="settleSelCount">0</b>건</span>
        </div>
        <div class="ds-grid-bar-right">
          @if($tossConfigured)
            <span class="ds-grid-hint">행 체크 후 실행</span>
            {{-- 가상계좌 발급·SMS 재전송은 외부로 나가는 동작이라 send 권한으로 통제 --}}
            @perm('settlement', 'send')
            <button type="button" class="ds-btn ds-btn-primary" onclick="vaIssueSelected(this)">
              <i class="fa-solid fa-plus"></i> 선택 발급
            </button>
            @endperm
            <button type="button" class="ds-btn" onclick="vaCheckSelected(this)">
              <i class="fa-solid fa-rotate"></i> 선택 입금확인
            </button>
            @perm('settlement', 'send')
            <button type="button" class="ds-btn" onclick="vaResendSelected(this)">
              <i class="fa-solid fa-comment-sms"></i> 선택 SMS재전송
            </button>
            @endperm
          @else
            {{-- 경고 삼각형이 앞에 있으면 전역 .ds-grid-hint::before 는 그리지 않는다 — 아이콘 하나만 선다 --}}
            <span class="ds-grid-hint"><i class="fa-solid fa-triangle-exclamation"></i> 토스 API 미설정 — 발급/입금확인/SMS 불가</span>
          @endif
          {{-- 그리드 툴바에 있던 엑셀 저장을 결과바로 옮겼다(동작은 downloadExcel() 동일) --}}
          <button type="button" class="ds-btn" onclick="window.__settlementGrid?.downloadExcel()">엑셀 저장</button>
        </div>
      </div>

      <div class="ds-grid-card">
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
      <button onclick="closeModal('rxModal')" style="margin-left:auto;background:none;border:none;font-size:16px;cursor:pointer;color:var(--text-muted);line-height:1;">×</button>
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
      <button onclick="closeModal('orderModal')" style="margin-left:auto;background:none;border:none;font-size:16px;cursor:pointer;color:var(--text-muted);line-height:1;">×</button>
    </div>
    <div style="overflow-y:auto;padding:20px;" id="orderModalBody">
      <div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:8px;">
      <button onclick="closeModal('orderModal')" class="btn btn-outline btn-sm">닫기</button>
    </div>
  </div>
</div>
@endpush

@push('scripts')
<script>
  // ── 모달 공통 ──────────────────────────────────────────────
  function openModal(id)  { const m = document.getElementById(id); m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  function closeModal(id) { document.getElementById(id).style.display = 'none'; document.body.style.overflow = ''; }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('rxModal'); closeModal('orderModal'); } });
  document.addEventListener('click', e => {
    if (e.target.id === 'rxModal')    closeModal('rxModal');
    if (e.target.id === 'orderModal') closeModal('orderModal');
  });

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
            ${row('환자명', `<b>${d.patient_name}</b>`)}
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
        <a href="./prescriptions/${d.id}" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> 처방전 상세 페이지</a>
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
            ${row('환자명', `<b>${d.patient_name}</b>`)}
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
  const ORDERS_BASE = @json(url('settlement/orders'));   // + '/{id}/virtual-account' 등

  const mountEl = document.getElementById(TAB === 'virtual_account' ? 'vaGrid' : 'settlementGrid');
  if (!mountEl) return;

  const grid = new wwGrid({
    el: mountEl,
    // 엑셀 저장은 결과바 버튼으로 옮겼다(동작은 downloadExcel() 동일).
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 상단 결과바에 있다.
    footer: false,
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
  window.settlementViewRx = function () {
    const r = oneChecked(); if (!r) return;
    if (!r.rx_url) { showToast('처방전이 없는 주문입니다.', 'warning'); return; }
    openRxModal(r.rx_url);
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
