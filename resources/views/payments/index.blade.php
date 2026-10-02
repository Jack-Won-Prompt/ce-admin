{{-- resources/views/payments/index.blade.php --}}
@extends('layouts.app')

@section('title', 'PG정산내역')
@section('page-title', 'PG정산내역')
@section('breadcrumb', '홈 - PG정산내역')

@section('content')

<form method="GET" class="ds-filter-card">
  <div class="ds-filter-fields">
    {{-- 상태가 무엇을 볼지를 가장 크게 가른다 — 첫 칸에 둔다. 위쪽에 칩 줄을 따로
         두면 그 줄만 회색 바탕 위에 띄어 있고, 고르는 일은 여기서도 할 수 있다
         (정산/회계ㆍ서류 함ㆍ서비스 요청과 같은 결로 맞춘다). --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ number_format(array_sum($statusCounts)) }})</option>
        @foreach(\App\Services\TossPayments\TossClient::STATUS_LABELS as $k => [$label, $badge])
          @if(($statusCounts[$k] ?? 0) > 0)
            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }} ({{ number_format($statusCounts[$k]) }})</option>
          @endif
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호ㆍ이름ㆍ예금주ㆍ계좌번호">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">결제수단</label>
      <select name="method" class="form-control form-select">
        <option value="">전체</option>
        @foreach(\App\Models\TossPayment::METHOD_LABELS as $k => $label)
          <option value="{{ $k }}" @selected(request('method') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('payments.index') }}" class="ds-btn"><i class="fa-solid fa-rotate-left"></i> 초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-search"></i> 검색</button>
  </div>
</form>

<div class="ds-grid-card">
  <div class="pnl-tabs">
    <button type="button" class="pnl-tab active" onclick="return false;">
      <i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ number_format(count($gridData)) }}</b>건)</span>
    </button>
    <div style="margin-left:auto;padding-right:12px;">
      <button type="button" class="ds-btn" onclick="window.__paymentGrid?.downloadExcel()">엑셀 다운</button>
    </div>
  </div>
  <div id="paymentGrid"></div>
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

  /* 카드 매입 상태 배지 (2026-10-02 지시).

     취소에 걸리는 시간이 여기서 갈려서, 담당자가 「지금 취소하면 바로 되는가」를
     한눈에 봐야 한다(토스 고객센터 안내).

       매입 전 취소(전체) : 결제 당일에만 가능 · 즉시
       매입 전 취소(부분) : 영업일 3~4일
       매입 후 취소       : 영업일 3~4일

     언제 물어본 값인지는 도움말로 붙인다 — 토스에 다시 물어 받은 값이라,
     오래된 것을 「지금 그렇다」고 읽지 않게 한다. */
  /* 도움말의 줄바꿈 — 글 안에 그대로 적으면 블레이드를 지나 JS 가 깨진다
     (2026-10-02 11:26 에 주문등록 화면을 2분 멈춰 세운 그 자리다). */
  const BR = String.fromCharCode(10);

  const 매입칸 = (v, row) => {
    const s = document.createElement('span');

    if (!v || v === '해당 없음') {
      s.textContent = '—';
      s.style.color = 'var(--text-muted)';
      return s;
    }

    const 빛 = {
      warning:   ['var(--warning-light, #FFF4E5)', 'var(--warning)'],
      success:   ['var(--primary-50)',             'var(--primary)'],
      info:      ['var(--primary-50)',             'var(--primary)'],
      secondary: ['var(--gray-100)',               'var(--gray-700)'],
      muted:     ['var(--gray-100)',               'var(--text-muted)'],
    }[row.acquire_tone] || ['var(--gray-100)', 'var(--text-muted)'];

    s.textContent = v;
    s.style.cssText = 'display:inline-flex;align-items:center;height:22px;padding:0 9px;'
      + 'border-radius:999px;font-size:11px;font-weight:700;'
      + 'background:' + 빛[0] + ';color:' + 빛[1] + ';';

    s.title = row.acquire_at
      ? ('토스에 ' + row.acquire_at + ' 에 물어본 값입니다.' + BR
         + '매입 전 전체 취소는 결제 당일에만 가능하며 즉시 처리됩니다.' + BR
         + '매입 후 취소와 부분 취소는 영업일 기준 3~4일이 걸립니다.')
      : '아직 토스에 물어보지 않았습니다. 목록을 다시 열면 채워집니다.';

    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('paymentGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '발급일시',   name: 'issued_at', width: 140, sortable: true },
      { header: '주문번호',   name: 'order_no',  width: 120, sortable: true },
      { header: '이름',       name: 'patient',   width: 90,  sortable: true },
      { header: '결제수단',   name: 'method',    width: 100, align: 'center', sortable: true },
      { header: '상태',       name: 'status',    width: 90,  align: 'center', sortable: true },
      { header: '매입 상태',  name: 'acquire',   width: 100, align: 'center', sortable: true, renderer: 매입칸 },
      { header: '금액',       name: 'amount',    width: 110, align: 'right', sortable: true, renderer: money },
      // 가상계좌로 받은 건만 값이 선다 — 카드는 계좌가 없다
      { header: '가상계좌은행', name: 'bank',    width: 100 },
      { header: '가상계좌번호', name: 'account', width: 150 },
      { header: '예금주명',   name: 'holder',    width: 100 },
      { header: '입금기한',   name: 'due_date',  width: 100, align: 'center', sortable: true },
      { header: '입금일시',   name: 'paid_at',   width: 140, sortable: true },
      // 토스 화면과 맞춰 볼 때 쓰는 번호
      { header: '토스 주문번호', name: 'toss_no', width: 170 },
      { header: '결제키',     name: 'pay_key',   width: 200 },

      /* 네 화면이 함께 쓰던 칸(요청서 3쪽). 주문이 붙은 줄에만 값이 선다. */
      ...ceMoneyCols(),
      ...ceWwCols(),
    ],
    data: @json($gridData),
  });
  window.__paymentGrid = grid;
  /* 쪽 나누기 — 모든 목록 화면이 한 모양으로 쓴다 (2026-10-02 지시).
     표 바로 아래에 줄이 선다. 합계ㆍ엑셀ㆍ정렬은 전체 기준을 지킨다. */
  cePager.붙이기(grid);
  window.dsBindSelCount?.(grid, 'paySelCount');
})();
</script>
@endpush
