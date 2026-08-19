{{-- resources/views/dispatch/index.blade.php --}}
@extends('layouts.app')

@section('title', '발송/발행 내역')
@section('page-title', '발송/발행 내역')
@section('breadcrumb', '홈 / 발송·발행 내역')

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
      @if($search || request()->hasAny(['date_from','date_to']))
        <a href="{{ route('dispatch.index', ['type'=>$type]) }}" class="ds-btn">
          <i class="fa-solid fa-xmark"></i> 초기화
        </a>
      @endif
      <button type="submit" class="ds-btn ds-btn-primary">
        <i class="fa-solid fa-magnifying-glass"></i> 검색
      </button>
      {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
      <button type="button" class="ds-btn" onclick="window.__dispatchGrid?.downloadExcel()">엑셀 저장</button>
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
