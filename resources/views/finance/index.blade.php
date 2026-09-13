{{-- resources/views/finance/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Finance')
@section('page-title', 'Finance')
@section('breadcrumb', '홈 - Finance')

@section('content')

{{-- 거르는 줄은 하나다. 여섯 탭이 묻는 것은 「언제」와 「누구ㆍ무엇」 둘뿐이다. --}}
<form method="GET" class="ds-filter-card">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호ㆍ이름ㆍ제품명">
    </div>
    <div class="ds-filter-field span-2">
      {{-- 요청서 14쪽 공통확인사항 — 「조회기간 조건 검색 가능(일별, 월별)」.
           달을 고르는 단추를 옆에 둔다. 재무가 보는 자리라 대개 한 달치다. --}}
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <button type="button" class="ds-btn" onclick="finMonth(0)">이번 달</button>
    <button type="button" class="ds-btn" onclick="finMonth(-1)">지난 달</button>
    <a href="{{ route('finance.index', ['tab' => $tab]) }}" class="ds-btn">
      <i class="fa-solid fa-rotate-left"></i> 초기화
    </a>
    <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-search"></i> 검색</button>
  </div>
</form>

<div class="ds-grid-card">
  {{-- 여섯을 한 화면의 탭으로 둔다 — 묻는 것이 같고(기간), 여는 사람이 같고,
       무엇보다 여섯을 오가며 견주어 본다. 메뉴를 여섯으로 늘리면 그때마다 기간을
       다시 고르게 된다. --}}
  {{-- overflow-x:auto 를 걸어 두었더니 걸린 탭의 아래 선이 사라졌다. 탭은
       margin-bottom:-1px 로 제 아래 선을 탭줄의 아래 선 위에 겹쳐 놓는데,
       가로 넘침을 auto 로 두면 세로도 auto 가 되어 그 1px 이 잘린다.
       여섯이 한 줄에 서므로 넘칠 일이 없고, 좁아지면 접히게 둔다. --}}
  <div class="pnl-tabs" style="flex-wrap:wrap;">
    {{-- 탭을 눌러도 화면을 다시 열지 않는다 (2026-09-10 지시). 값만 받아 표를 그 자리에서
         다시 그린다 — 깜빡이지 않고, 고른 기간ㆍ검색어도 그대로다.

         주소는 그대로 남긴다(pushState). 새로고침하거나 링크를 건네도 그 탭이 열린다.
         스크립트가 죽어도 href 가 살아 있어 예전처럼 넘어간다. --}}
    @foreach(\App\Http\Controllers\FinanceController::TABS as $k => $label)
      <a href="{{ route('finance.index', array_filter(['tab' => $k, 'q' => request('q'), 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
         class="pnl-tab {{ $tab === $k ? 'active' : '' }}" style="white-space:nowrap;"
         data-tab="{{ $k }}" onclick="return finTab(event, '{{ $k }}')">
        {{ $label }}<span class="pnl-tab-cnt" {{ $tab === $k ? '' : 'hidden' }}>(총 <b>{{ number_format(count($gridData)) }}</b>건)</span>
      </a>
    @endforeach
    <div style="margin-left:auto;padding-right:12px;flex-shrink:0;">
      {{-- 요청서 14쪽 공통확인사항 — 「모든 메뉴는 엑셀 다운로드 가능」 --}}
      <button type="button" class="ds-btn" onclick="window.__financeGrid?.downloadExcel()">엑셀 다운</button>
    </div>
  </div>
  {{-- PG정산내역 안의 네 갈래 (2026-09-11 확인요청 6ㆍ7쪽).
       토스 화면과 같은 차례로 둔다 — 그 화면을 보던 사람이 그대로 찾을 수 있게.
       PG 탭일 때만 선다. --}}
  <div id="pgViews" class="pnl-tabs" style="flex-wrap:wrap;background:var(--gray-50);
       border-top:1px solid var(--border);{{ $tab === 'pg' ? '' : 'display:none;' }}">
    @foreach(\App\Http\Controllers\FinanceController::PG_VIEWS as $v => $이름)
      <a href="{{ route('finance.index', array_filter(['tab' => 'pg', 'view' => $v, 'q' => request('q'), 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
         class="pnl-tab {{ ($pgView ?? 'summary') === $v ? 'active' : '' }}" style="white-space:nowrap;"
         data-view="{{ $v }}" onclick="return finPgView(event, '{{ $v }}')">{{ $이름 }}</a>
    @endforeach
    <span style="margin-left:auto;padding:0 12px;align-self:center;font-size:11px;color:var(--text-muted);">
      결제내역은 우리 자료 · 나머지 넷은 토스페이먼츠에서 받아 옵니다
    </span>
  </div>

  <div id="financeGrid"></div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const money = (v) => {
    const n = Number(v || 0);
    if (!n) return '';
    const s = document.createElement('span');
    s.textContent = n.toLocaleString('ko-KR');
    return s;
  };

  /* 칸은 서버가 정한다(FinanceController::columnsFor) — 요청서 14~19쪽의 차례를
     그대로 옮긴 것이라, 화면에서 다시 적으면 두 벌이 갈린다. */
  const COLS = @json($columns);
  COLS.forEach(c => { if (c.editor === 'number') { delete c.editor; c.renderer = money; } });

  /* 표는 다시 그릴 수 있어야 한다 — 탭마다 칸이 다르므로 값만 갈아 끼울 수 없다 */
  const 표만들기 = (칸들, 줄들, 위드웍스붙일까 = true) => new wwGrid({
    el: document.getElementById('financeGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    /* 재무가 보는 칸을 먼저 세우고, 그 뒤에 **위드웍스 판매현황과 같은 차례**를 잇는다
       (2026-09-07 지시). 다른 다섯 목록이 이미 그 묶음을 쓰고 있었는데 Finance 만
       제 칸만 세우고 있었다 — 저쪽 화면을 보다 이리로 넘어오면 눈이 다시 배워야 했다.

       앞의 칸을 걷지 않는다. 재무는 매출ㆍ입금ㆍ미수를 세는 자리라, 그 값들이
       맨 앞에 서 있어야 한 눈에 읽힌다. 위드웍스 차례는 그 뒤에서 이어 본다.

       PG정산내역만은 잇지 않는다 (2026-09-11 확인요청 6ㆍ7쪽). 그 줄은 토스에서
       온 정산 자료라 판매주문ㆍ창고 값이 아예 없다 — 이으면 빈 칸 백 개가 따라붙는다. */
    columns: 위드웍스붙일까 ? [...칸들, ...ceWwCols()] : [...칸들],
    data: 줄들,
  });

  const 돈칸으로 = (칸들) => {
    칸들.forEach(c => { if (c.editor === 'number') { delete c.editor; c.renderer = money; } });
    return 칸들;
  };

  window.__financeGrid = 표만들기(COLS, @json($gridData), @json($tab !== 'pg'));

  /* ── 탭 ──

     화면을 다시 열지 않는다. 값만 받아 표를 새로 그린다 — 탭마다 칸이 달라
     표를 통째로 다시 세운다. 그 사이 옅게 흐려 두어 바뀌는 중임을 알린다. */
  let 부르는중 = false;

  window.finTab = function (e, 어느것) {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return true;  // 새 탭으로 여는 것은 그대로
    e.preventDefault();

    if (부르는중) return false;
    const 그줄 = e.currentTarget;
    if (그줄.classList.contains('active')) return false;

    부르는중 = true;
    const 칸 = document.getElementById('financeGrid');
    칸.style.opacity = '.45';

    const 주소 = new URL(그줄.href, location.origin);

    fetch(주소.toString() + (주소.search ? '&' : '?') + 'json=1',
          { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(d => {
        칸.innerHTML = '';
        window.__financeGrid = 표만들기(돈칸으로(d.columns), d.rows, d.tab !== 'pg');

        // 걸린 탭과 건수를 옮긴다
        document.querySelectorAll('.pnl-tabs .pnl-tab[data-tab]').forEach(a => {
          const 걸림 = a.dataset.tab === d.tab;
          a.classList.toggle('active', 걸림);
          const 수 = a.querySelector('.pnl-tab-cnt');
          if (수) {
            수.hidden = !걸림;
            if (걸림) 수.innerHTML = '(총 <b>' + Number(d.count).toLocaleString('ko-KR') + '</b>건)';
          }
        });

        /* PG 탭일 때만 갈래 줄이 선다 (2026-09-11 확인요청 6ㆍ7쪽) */
        const 갈래줄 = document.getElementById('pgViews');
        if (갈래줄) {
          갈래줄.style.display = d.tab === 'pg' ? '' : 'none';
          갈래줄.querySelectorAll('.pnl-tab[data-view]').forEach(a =>
            a.classList.toggle('active', a.dataset.view === (d.view || 'summary')));
        }

        // 거르개와 주소도 그 탭의 것으로 — 새로고침하거나 링크를 건네도 같은 자리다
        const 숨은칸 = document.querySelector('form.ds-filter-card input[name=tab]');
        if (숨은칸) 숨은칸.value = d.tab;
        history.pushState({ tab: d.tab }, '', 주소.toString());
      })
      .catch(() => { location.href = 그줄.href; })   // 못 받으면 예전처럼 넘어간다
      .finally(() => { 칸.style.opacity = ''; 부르는중 = false; });

    return false;
  };

  /* PG 안의 갈래도 탭과 하는 일이 같다 — 같은 길을 탄다 */
  window.finPgView = function (e, v) { return finTab(e, 'pg:' + v); };

  /* 뒤로 가기로 돌아오면 그 탭이 다시 서야 한다 — 화면을 다시 읽는다 */
  window.addEventListener('popstate', () => location.reload());

  /* 달로 고르기 — 재무가 보는 자리라 대개 한 달치다. 날짜 두 개를 손으로 맞추는
     것보다 단추 하나가 빠르다. */
  window.finMonth = function (offset) {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + offset);
    const first = new Date(d.getFullYear(), d.getMonth(), 1);
    const last  = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const fmt = (x) => `${x.getFullYear()}-${String(x.getMonth()+1).padStart(2,'0')}-${String(x.getDate()).padStart(2,'0')}`;
    document.querySelector('input[name=date_from]').value = fmt(first);
    document.querySelector('input[name=date_to]').value   = fmt(last);
    document.querySelector('.ds-filter-card').submit();
  };
})();
</script>
@endpush
