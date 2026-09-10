{{-- resources/views/orders/index.blade.php --}}
@extends('layouts.app')

{{-- 화면 이름 — 시안 148:5526 은 제목도 빵부스러기도 「주문 관리」다(제목 x336 16/700 #333940).
     사이드바 메뉴 이름만 「주문현황」으로 남는다 — 그 위에 「주문 등록」이 따로 있어
     이름이 겹치지 않게 둔 것이고(app.blade.php 1317행 주석), 그 파일은 이번 배정이 아니다. --}}
@section('title', '주문 관리')
@section('page-title', '주문 관리')
{{-- 브레드크럼 — Figma 148:5526 Frame 48101452: 홈 · - · 주문 관리, gap 8 (12px/500 lh14).
     마디로 세우는 일은 이제 레이아웃이 한다 — 여기서는 낱말만 적는다. --}}
@section('breadcrumb', '홈 - 주문 관리')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.ds-filter-card', title: '검색 필터', body: '상태·유형을 고르고 주문번호, 이름, 제품명으로 찾습니다. 기간으로도 좁힐 수 있습니다.' },
  { selector: '#orderGrid', title: '주문 목록', body: '각 행에서 주문번호·이름·판매번호·배송 상태를 확인합니다. 행을 클릭하면 주문 상세로 이동합니다.' },
];
</script>
@endpush

@section('help-title', '주문 관리 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>생성된 모든 주문을 조회하고 배송·청구·영수증 상태를 관리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">주문 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">주문 대기</span>
    <span class="badge badge-primary">주문 확정</span>
    <span class="badge badge-info">배송 중</span>
    <span class="badge badge-success">배송 완료</span>
    <span class="badge badge-danger">취소</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 기능</div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-link"></i></div>
    <div class="help-item-text"><strong>판매번호</strong>위드웍스 판매주문번호입니다. 목록에서 바로 확인합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-receipt"></i></div>
    <div class="help-item-text"><strong>세금계산서/현금영수증</strong>주문 상세에서 발행 및 취소를 처리합니다.</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* ── 첨부 · 팩스 팝오버 ───────────────────────────────
     화면을 덮지 않는다 — 누른 자리 옆에 붙어 뜨고, 뒤의 목록이 그대로 보인다.
     줄을 훑다가 열고 닫는 자리라 가운데 창은 흐름을 끊는다(2026-09-09 지시). */
  .att-fax-back {
    display: none; position: fixed; inset: 0; z-index: 1400;
    background: transparent;   /* 덮지 않는다 — 바깥을 눌러 닫는 자리로만 쓴다 */
  }
  .att-fax-back.show { display: block; }
  .att-fax {
    position: fixed; width: 440px; max-height: 460px;
    display: flex; flex-direction: column;
    background: var(--bg-card); border: 1px solid var(--primary);
    border-radius: var(--radius-lg); box-shadow: 0 8px 32px rgba(0,0,0,.18); overflow: hidden;
  }
  .att-fax-hd {
    display: flex; align-items: center; gap: 8px; padding: 9px 12px;
    background: var(--primary); color: #fff; font-size: 12px;
  }
  .att-fax-hd b { flex: 1; font-weight: 700; }
  .att-fax-x { background: none; border: none; color: #fff; font-size: 17px; line-height: 1; cursor: pointer; }
  .att-fax-note {
    padding: 9px 14px; font-size: 11px; line-height: 1.6;
    background: var(--danger-light); color: var(--danger); border-bottom: 1px solid var(--border);
  }
  .att-fax-bd { flex: 1; overflow-y: auto; padding: 8px 12px; display: flex; flex-direction: column; gap: 2px; }
  .att-fax-row {
    display: grid; grid-template-columns: 16px 92px 1fr auto; align-items: center; gap: 7px;
    padding: 5px 8px; border: 1px solid var(--border); border-radius: var(--radius);
    font-size: 11.5px; cursor: pointer; background: var(--bg-card);
  }
  .att-fax-row:hover { border-color: var(--primary); }
  /* 실리지 않는 것 — 고를 수 없다는 것이 한눈에 보여야 한다 */
  .att-fax-row.is-off { opacity: .55; cursor: not-allowed; background: var(--bg); }
  .att-fax-row.is-off:hover { border-color: var(--border); }
  .att-fax-lb { font-weight: 700; }
  .att-fax-nm { color: var(--text-muted); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  /* 미리 만들어 둔 파일이 없는 서식 — 발송할 때 그려 넣는다 */
  .att-fax-auto {
    justify-self: start; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 999px;
    background: var(--primary-light); color: var(--primary);
  }
  .att-fax-why { color: var(--danger); font-size: 10.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .att-fax-guide {
    padding: 6px 12px; font-size: 10.5px; color: var(--text-muted);
    background: var(--bg); border-top: 1px solid var(--border); line-height: 1.5;
  }
  .att-fax-at { color: var(--text-muted); font-size: 10px; font-variant-numeric: tabular-nums; }
  .att-fax-empty { padding: 18px 0; text-align: center; color: var(--text-muted); font-size: 12px; }
  .att-fax-to { padding: 9px 12px; border-top: 1px solid var(--border); }
  .att-fax-to label { display: block; font-size: 11px; font-weight: 500; color: var(--text-muted); margin-bottom: 5px; }
  .att-fax-to input {
    width: 100%; height: 32px; padding: 0 10px; font-size: 12px;
    border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-card); color: var(--gray-1000);
  }
  .att-fax-to input:focus { outline: none; border-color: var(--primary); }
  .att-fax-hint { margin-top: 5px; font-size: 10px; color: var(--text-muted); line-height: 1.5; }
  .att-fax-ft { display: flex; justify-content: flex-end; gap: 6px; padding: 9px 12px; border-top: 1px solid var(--border); }
  .att-fax-btn {
    height: 32px; padding: 0 14px; font-size: 12px; font-weight: 700; cursor: pointer;
    border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-card); color: var(--gray-1000);
  }
  .att-fax-btn.is-main { background: var(--primary); border-color: var(--primary); color: #fff; }
  .att-fax-btn:disabled { opacity: .5; cursor: not-allowed; }

  .order-number { font-size: 12px; font-weight: 700; color: var(--primary); letter-spacing: .5px; font-family: monospace; }
  .patient-name-cell { font-weight: 500; }
  .product-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
  .btn-row { display: flex; gap: 6px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }


  /* 아래 규칙들은 전역으로 올렸다 —
       구분선 안쪽 여백 · 결과바 위 4px · 패널 탭 gap 8 · 그리드 카드 테두리 제거
         → resources/views/layouts/app.blade.php
       머리행 세로선 제거·왼쪽 정렬 · 본문 셀 왼쪽 정렬 · 체크박스 16×16 r6
         → public/vendor/wwgrid/wwGrid.css
     한 화면에만 두면 목록 화면 열두 개의 그리드가 서로 달라 보인다. */
</style>
@endpush

@section('content')

{{-- ── 상태별 탭 ── --}}
@php
  $statuses = \App\Models\Order::STATUS_LABELS;
  $totalAll  = $statusCounts->sum();
  $curStatus = request('status');
@endphp

{{-- 상태 칩 — Figma 148:5526: h31 · r999 · pad 6/10 · 12/700, 건수 배지 16×16 정원 --}}
{{-- 상태는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}

@php $curDeal = request('deal'); @endphp

{{-- ── 검색 필터 ── --}}
{{-- 검색 필터 — Figma 148:5526: 흰 카드(r12 · pad 12/16), 검색어 2열 · 기간 2열 · 기준/정렬 1열 --}}
<form method="GET" action="{{ route('orders.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $totalAll }})</option>
        @foreach($statuses as $key => $meta)
          <option value="{{ $key }}" {{ $curStatus === $key ? 'selected' : '' }}>
            {{ $meta['label'] }}@if(($statusCounts[$key] ?? 0) > 0) ({{ $statusCounts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호ㆍ이름ㆍ제품명">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">등록일자</label>
      <input type="date" name="date" value="{{ request('date') }}" class="form-control">
    </div>
    <div class="ds-filter-field">
      {{-- 유형 — 판매·교환·반품·취소.
           칩으로 한 줄을 더 쓰면 상태 칩과 섞여 무엇이 무엇인지 헷갈린다.
           위쪽 칩은 진행 상태 하나만 두고, 나머지 갈래는 여기서 고른다. --}}
      <label class="ds-field-label">유형</label>
      <select name="deal" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(['sale' => '판매'] + \App\Models\OrderReturn::TYPES as $key => $label)
          <option value="{{ $key }}" {{ $curDeal === (string) $key ? 'selected' : '' }}>
            {{ $label }}@if(($dealCounts[$key] ?? 0) > 0) ({{ $dealCounts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      {{-- 처방 유형 — 원내·원외·처방외 --}}
      <label class="ds-field-label">처방유형</label>
      <select name="acc_type" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(\App\Models\Prescription::ACC_TYPES as $code => $label)
          {{-- 배열 키가 정수로 바뀌므로 문자열로 되돌려 견준다 --}}
          <option value="{{ $code }}" {{ request('acc_type') === (string) $code ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
      {{-- 「표시 건수」 칸은 두지 않는다. 목록은 wwGrid 가 한 번에 다 받아 그리고
           (컨트롤러가 ->get() 으로 통째로 넘긴다) 페이지를 나누지 않는다 —
           이 칸은 아무 일도 하지 않으면서 「10개씩」이라 적어 거짓을 말하고 있었다. --}}
  </div>
  <div class="ds-filter-actions">
    {{-- 초기화 — 시안 148:5526 은 검색 왼쪽에 늘 세워 둔다. 검색어·등록일자가 있을 때만
         내보내던 조건을 걷었다. 링크는 그대로 이 화면의 라우트로 되돌아간다
         (지금 보고 있는 상태 칩·거래·처방유형은 유지). --}}
    <a href="{{ route('orders.index', array_filter(['status'=>$curStatus, 'deal'=>$curDeal, 'acc_type'=>request('acc_type')])) }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 찾는 일과 나란히 둔다. 네비바에 두었더니 탭 안에서 통째로 사라졌다.
         data-ce-tab 이 붙어 있어 지금 탭을 갈아치우지 않고 새 화면 탭으로 열린다. --}}
    <a href="{{ route('prescriptions.index') }}" class="ds-btn"
       data-ce-tab="처방전 목록" data-ce-icon="bx-file">
      <i class="fa-solid fa-file-medical"></i> 처방전 목록
    </a>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__orderGrid?.downloadExcel()">엑셀 다운</button>
  </div>
</form>

{{-- 패널 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단, 텍스트만 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ count($gridData) }}</b>건)</span></button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">상세 내용</button>
    </div>
    <div id="pnlList">
      <div id="orderGrid"></div>
</div>{{-- /pnlList --}}

{{-- ── 상세내용 탭 (기존 상세 페이지 콘텐츠를 같은 페이지에 직접 주입) — 같은 카드 안 ── --}}
<div id="pnlDetail" style="display:none;padding:16px;">
  {{-- 「조회결과로」 단추는 두지 않는다 — 바로 위 탭줄의 「조회 결과」가 같은 일을 한다. --}}
  <div id="pnlEmpty" class="pnl-empty">조회결과에서 행을 <b>더블클릭</b>하면 상세 내용이 여기에 표시됩니다.</div>
  <div id="pnlDetailContent"></div>
</div>
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

@php /* 이하 원본 테이블 마크업은 wwGrid로 대체되어 미사용 */ @endphp
@if(false)
<div class="card">
  <div class="card-header">
    <i class="bx bx-cart-alt" style="font-size:18px;color:var(--primary);"></i>
    <span class="card-header-title">주문 목록</span>
  </div>
  <div class="table-scroll-wrap">
    <table>
      <thead>
        <tr>
          <th>주문번호</th>
          <th>이름</th>
          <th>제품명</th>
          <th>수량</th>
          <th class="amount-cell">본인 부담금</th>
          <th class="amount-cell">총금액</th>
          <th>배송지</th>
          <th>주문유형</th>
          <th>상태</th>
          <th style="text-align:center;min-width:110px;">판매번호</th>
          <th>생성일</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $order)
          @php $meta = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary']; @endphp
          <tr>
            <td>
              <a href="{{ route('orders.show', $order) }}" class="order-number">
                {{ $order->order_number }}
              </a>
            </td>
            <td class="patient-name-cell">
              {{ $order->patient?->name ?? '-' }}
            </td>
            <td>
              <div class="product-cell" title="{{ $order->product_name }}">
                {{ $order->product_name ?? '-' }}
              </div>
              @if($order->quantity > 1)
                <div style="font-size:11px;color:var(--text-muted);">×{{ $order->quantity }}</div>
              @endif
            </td>
            <td>{{ $order->quantity ?? 1 }}</td>
            <td class="amount-cell">
              {{ number_format($order->patient_copay) }}원
            </td>
            <td class="amount-cell fw-bold">
              {{ number_format($order->total_amount) }}원
            </td>
            <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;">
              {{ $order->shipping_address ? mb_substr($order->shipping_address,0,20).(mb_strlen($order->shipping_address)>20?'…':'') : '-' }}
            </td>
            <td>
              @php $soType = \App\Models\Order::SO_TYPE_LABELS[$order->so_type] ?? null; @endphp
              @if($soType)
                <span class="badge badge-{{ $soType[1] }}" style="font-size:11px;">{{ $soType[0] }}</span>
              @else
                <span style="color:var(--text-muted);font-size:12px;">-</span>
              @endif
            </td>
            <td>
              <span class="badge badge-{{ $meta['badge'] }}">{{ $meta['label'] }}</span>
            </td>
            <td style="text-align:center;" id="ww-cell-{{ $order->id }}">
              @if($order->withworks_so_no)
                @php
                  $soIdx = $order->withworks_status ?? '';
                  $soBadge = match(true) {
                    $soIdx === '02'               => 'primary',
                    in_array($soIdx, ['03','51']) => 'info',
                    in_array($soIdx, ['04','52']) => 'warning',
                    $soIdx === '05'               => 'success',
                    in_array($soIdx, ['06','99']) => 'secondary',
                    default                       => 'secondary',
                  };
                  $shipIdx = $order->withworks_ship_status ?? '';
                  $shipBadge = match(true) {
                    in_array($shipIdx, ['02','14','15','17']) => 'secondary',
                    in_array($shipIdx, ['52','55'])           => 'info',
                    in_array($shipIdx, ['61','68'])           => 'warning',
                    $shipIdx === '95'                         => 'success',
                    in_array($shipIdx, ['16','53','92'])      => 'info',
                    default                                   => 'secondary',
                  };
                @endphp
                <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:3px;">{{ $order->withworks_so_no }}</div>
                @if($order->withworks_status_label)
                  <span class="badge badge-{{ $soBadge }}" style="font-size:10px;">{{ $order->withworks_status_label }}</span>
                  @if($order->withworks_ship_no)
                    <div style="margin-top:4px;border-top:1px dashed var(--border);padding-top:3px;">
                      <div style="font-size:10px;color:var(--text-muted);margin-bottom:2px;">출고 {{ $order->withworks_ship_no }}</div>
                      <span class="badge badge-{{ $shipBadge }}" style="font-size:10px;">{{ $order->withworks_ship_status_label }}</span>
                      @if($order->withworks_tracking_no)
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ $order->withworks_tracking_no }}</div>
                      @endif
                    </div>
                  @endif
                  <button onclick="fetchWwStatus({{ $order->id }}, '{{ route('orders.fetchWithworksStatus', $order) }}')"
                          style="display:block;margin:4px auto 0;font-size:10px;padding:1px 7px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;color:var(--text-muted);">
                    새로고침
                  </button>
                @else
                  <span class="badge badge-success" style="font-size:10px;">등록</span>
                  <button onclick="fetchWwStatus({{ $order->id }}, '{{ route('orders.fetchWithworksStatus', $order) }}')"
                          style="display:block;margin:3px auto 0;font-size:10px;padding:1px 7px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;color:var(--primary);">
                    상태 조회
                  </button>
                @endif
              @else
                <span style="color:var(--text-muted);font-size:12px;">-</span>
              @endif
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              {{ $order->created_at->format('m/d H:i') }}
            </td>
            <td>
              <a href="{{ route('orders.show', $order) }}" class="btn btn-outline btn-sm">
                상세
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="13" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
              주문이 없습니다.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>
@endif

@endsection

@push('scripts')
@include('nhis.assist._button')
@include('nhis.assist._direct')
@include('nhis.assist._dispatch')
<script>
(function () {
  const DETAIL_BASE = @json(url('orders'));
  const grid = new wwGrid({
    el: document.getElementById('orderGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '주문번호',   name: 'order_no',  width: 120, sortable: true },
      { header: '이름',     name: 'patient',   width: 90,  sortable: true },
      {
        // 판매인지, 되돌아온 건인지. 되돌아온 건은 눈에 띄어야 한다.
        // renderer 는 노드를 돌려줘야 한다 — 문자열을 주면 글자 그대로 찍힌다.
        header: '유형', name: 'deal', width: 100, sortable: true, align: 'center',
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          if (v && v !== '판매') { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      {
        // 교환·반품·취소가 어디까지 왔는지. 판매 건은 빈칸이다 — 옆의 '상태'가 그 자리다.
        header: '등록 상태', name: 'deal_state', width: 90, sortable: true, align: 'center',
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          if (v) { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      /* 제품명·수량·환자부담금·총금액·배송지는 목록에서 뺐다. 한 줄에 열여섯 칸이
         들어가 가로로 밀어 봐야 했고, 정작 훑을 때 필요한 것은 누구의 무슨 건이 어디까지
         왔는가다. 뺀 값들은 행을 더블클릭하면 상세 내용에서 그대로 본다. */
      { header: '주문유형',   name: 'so_type',   width: 90,  align: 'center' },
      { header: '상태',       name: 'status',    width: 90,  sortable: true, align: 'center' },
      {{-- 판 날과 되돌아온 날. 되돌아오지 않은 건은 뒤 칸이 비어 있다. --}}
      // 정산 — 「언제 팔았고 얼마였나」는 나란히 본다
      ...ceMoneyCols(),
      { header: '판매일자',   name: 'sold_at',   width: 100, sortable: true, align: 'center' },
      { header: '교환/반품/취소일자', name: 'deal_at', width: 130, sortable: true, align: 'center' },
      {
        /* 청구처에 따라 하는 일이 다르다 — 공단은 사이트에 옮겨 적고, 지자체는 등기로
           부치고, 낼 곳이 없는 건은 증빙을 거래처로 보낸다. 「도우미」라 부르면 무엇을
           하는 자리인지 알 수 없어 「청구 진행」으로 적는다. */
        header: '청구 진행', name: 'nhis_assist', width: 100, sortable: false, exportable: false,
        renderer: (v, row) => nhisAssistBtn(row.id, { agency: row.agency_code,
                                                       name: row.patient, mobile: row.send_mobile, email: row.send_email }),
      },
      {
        /* 냈는가 안 냈는가만 본다. 상태 일곱 가지는 옆의 「청구」 칸이 따로 말한다. */
        header: '청구 여부', name: 'claim_done', width: 90, align: 'center', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '';
          if (v === '청구 완료') s.style.color = 'var(--primary)';
          else if (v === '미청구') s.style.color = 'var(--danger)';
          else s.style.color = 'var(--gray-400)';
          s.style.fontWeight = '600';
          return s;
        },
      },

      {
        /* 첨부 — 몇 장인지 세우고, 누르면 골라 팩스로 보낸다.
           여태 건마다 상세로 들어가야 했다. 위드웍스 차례(ceWwCols) 앞에 둔다 —
           그 뒤는 여섯 화면이 같은 순서로 쓰는 자리라 끼어들면 약속이 흔들린다. */
        header: '첨부', name: 'att_count', width: 70, align: 'center', sortable: true,
        exportable: false,
        renderer: (v, row) => attFaxBtn(row),
      },

      /* 제품명ㆍ수량은 목록에 두지 않는다. 한 줄이 이미 길어 가로로 밀어야
         하고, 훑을 때 필요한 것은 누구의 무슨 건이 어디까지 왔는가다 — 무엇을 얼마나
         보냈는지는 줄을 더블클릭해 상세에서 본다.
         배송지는 남긴다 — 어디로 가는지는 훑으면서 가리는 값이다. */
      { header: '배송지',   name: 'address',  width: 240 },


      // 네 목록 화면이 함께 쓰는 칸 — 위드웍스 판매주문 현황의 차례다
      ...ceWwCols(),
    ],
    data: @json($gridData),
  });

  /* ── 첨부 칸 · 팩스 팝업 ─────────────────────────────────
     목록에서 그 건의 서류를 골라 팩스로 보낸다. 보내는 길은 상세의 팩스 창과 같은
     것을 쓴다(prescriptions.faxSend) — 두 자리가 다른 규칙으로 보내면 나중에 무엇이
     나갔는지 맞춰 볼 수 없다.

     세 가지를 지킨다.
       · 지자체 건은 잠근다 — 등기로 부치는 건이라 팩스로 보내면 안 된다.
       · **보내기 전에 한 번 묻는다** — 목록은 상세보다 누르기 쉽고, 팩스는 정말 나간다.
       · 실리지 않는 것(PDF 첨부)은 고를 수 없게 하고 까닭을 적는다. */
  const FAX_DOCS_URL = @json(url('/orders'));

  let _attAnchor = null;

  function attFaxBtn(row) {
    const n = Number(row.att_count || 0);
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;align-items:center;justify-content:center;';

    if (!n) { box.textContent = '-'; return box; }

    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = String(n);
    b.title = '첨부파일을 선택하여 팩스로 발송합니다';
    b.style.cssText = 'height:22px;min-width:34px;padding:0 8px;font-size:11px;font-weight:700;'
                    + 'cursor:pointer;border:1px solid var(--primary);border-radius:999px;'
                    + 'background:var(--primary-light);color:var(--primary);line-height:1;';
    b.onclick = (ev) => { ev.stopPropagation(); _attAnchor = ev.currentTarget; openAttFax(row); };
    box.appendChild(b);
    return box;
  }

  let _attPop = null, _attRows = [], _attSendUrl = '';

  function attFaxClose() { if (_attPop) { _attPop.classList.remove('show'); } }

  function attFaxBuild() {
    const back = document.createElement('div');
    back.className = 'att-fax-back';
    back.innerHTML = `
      <div class="att-fax" role="dialog" aria-modal="true">
        <div class="att-fax-hd"><b id="attFaxTitle">첨부파일</b>
          <button type="button" class="att-fax-x" aria-label="닫기">&times;</button></div>
        <div class="att-fax-note" id="attFaxBlocked" style="display:none;"></div>
        <div class="att-fax-bd" id="attFaxList"></div>
        <div class="att-fax-guide">선택한 서류는 하나의 PDF로 합쳐 발송합니다.</div>
        <div class="att-fax-to">
          <label>받는 팩스번호</label>
          <input data-phone type="text" id="attFaxNo" placeholder="02-0000-0000" autocomplete="off">
          <div class="att-fax-hint" id="attFaxHint"></div>
        </div>
        <div class="att-fax-ft">
          <button type="button" class="att-fax-btn" id="attFaxCancel">닫기</button>
          <button type="button" class="att-fax-btn is-main" id="attFaxSend">팩스 전송</button>
        </div>
      </div>`;
    document.body.appendChild(back);

    back.querySelector('.att-fax-x').onclick = attFaxClose;
    back.querySelector('#attFaxCancel').onclick = attFaxClose;
    back.onclick = (e) => { if (e.target === back) attFaxClose(); };
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && back.classList.contains('show')) attFaxClose();
    });
    /* 목록을 굴리면 붙어 있던 자리가 어긋난다 — 그때는 닫는다.
       따라 움직이게 하면 창이 화면 밖으로 미끄러져 나가는 편이 더 나쁘다.

       **창 안을 굴린 것은 그대로 둔다.** 서류가 스물여덟 줄이라 안쪽 목록을
       굴려 내려가는데, 그때도 닫혀 고를 수가 없었다. */
    window.addEventListener('scroll', (e) => {
      const t = e.target;
      if (t && t.nodeType === 1 && back.contains(t)) return;
      attFaxClose();
    }, true);
    window.addEventListener('resize', attFaxClose);
    back.querySelector('#attFaxSend').onclick = attFaxSend;
    return back;
  }

  /* 누른 단추 옆에 앉힌다 — 아래가 좁으면 위로, 오른쪽이 좁으면 왼쪽으로 붙인다 */
  function attFaxPlace(anchor) {
    const pop = _attPop.querySelector('.att-fax');
    const r   = anchor.getBoundingClientRect();
    const gap = 6;
    const w   = pop.offsetWidth  || 440;
    const h   = pop.offsetHeight || 460;

    let top  = r.bottom + gap;
    if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - gap);

    let left = r.left;
    if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);

    pop.style.top  = top  + 'px';
    pop.style.left = left + 'px';
  }

  async function openAttFax(row) {
    if (!_attPop) _attPop = attFaxBuild();
    const list = _attPop.querySelector('#attFaxList');
    const blk  = _attPop.querySelector('#attFaxBlocked');
    _attPop.querySelector('#attFaxTitle').textContent = '첨부파일 · ' + (row.patient || '');
    list.innerHTML = '<div class="att-fax-empty">불러오는 중…</div>';
    blk.style.display = 'none';
    _attPop.classList.add('show');
    if (_attAnchor) attFaxPlace(_attAnchor);

    let d;
    try {
      const res = await fetch(FAX_DOCS_URL + '/' + row.id + '/fax-docs', { headers: { Accept: 'application/json' } });
      d = await res.json();
    } catch (e) { d = { success: false, message: '불러오지 못했습니다.' }; }

    if (!d.success) { list.innerHTML = '<div class="att-fax-empty">' + (d.message || '불러오지 못했습니다.') + '</div>'; return; }

    _attRows    = d.rows || [];
    _attSendUrl = d.send_url || '';

    _attPop.querySelector('#attFaxTitle').textContent =
      '첨부파일 · ' + (d.patient || '') + (d.rx_number ? ' · ' + d.rx_number : '');

    if (d.blocked) { blk.textContent = d.blocked; blk.style.display = ''; }

    const no = _attPop.querySelector('#attFaxNo');
    no.value = d.office?.fax || '';
    _attPop.querySelector('#attFaxHint').textContent =
      d.office?.name ? ('관할 청구처 ' + d.office.name + ' — 다른 곳으로 보내려면 번호를 변경하십시오.')
                     : '받는 곳의 팩스번호를 입력하십시오.';

    /* 줄이 채워져 높이가 달라졌다 — 다시 앉힌다 */
    setTimeout(() => { if (_attAnchor) attFaxPlace(_attAnchor); }, 0);

    /* 가운데 칸은 **파일 이름 자리**다. 미리 만들어 둔 파일이 없는 서식은 그 자리에
       「자동 생성」 딱지를 세우고, 보낼 수 없는 것은 그 사유를 붉게 적는다. */
    list.innerHTML = _attRows.length
      ? _attRows.map((r, i) => {
          const 가운데 = r.why
            ? `<span class="att-fax-why">${dsEsc(r.why)}</span>`
            : (r.auto ? '<span class="att-fax-auto">자동 생성</span>'
                      : `<span class="att-fax-nm">${dsEsc(r.name || '')}</span>`);
          return `
        <label class="att-fax-row${r.ok ? '' : ' is-off'}">
          <input type="checkbox" data-i="${i}" ${r.ok ? '' : 'disabled'}>
          <span class="att-fax-lb">${dsEsc(r.label)}</span>
          ${가운데}
          <span class="att-fax-at">${dsEsc(r.at || '')}</span>
        </label>`;
        }).join('')
      : '<div class="att-fax-empty">발송할 수 있는 서류가 없습니다.</div>';

    _attPop.querySelector('#attFaxSend').disabled = !!d.blocked;
  }

  function dsEsc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  async function attFaxSend() {
    const picked = [..._attPop.querySelectorAll('#attFaxList input[type=checkbox]:checked')]
                     .map(c => _attRows[Number(c.dataset.i)]);
    if (!picked.length) { showToast('발송할 서류를 하나 이상 선택하십시오.', 'warning'); return; }

    const faxNo = (_attPop.querySelector('#attFaxNo').value || '').trim();
    if (!/^[0-9-]{7,20}$/.test(faxNo)) { showToast('받는 팩스번호를 정확히 입력하십시오.', 'warning'); return; }

    /* 팩스는 정말 나간다 — 무엇을 어디로 보내는지 보이고 한 번 묻는다 */
    const 이름들 = picked.map(r => r.label).join(' · ');
    if (!await ceConfirm(`${faxNo} 로 다음 서류를 발송합니다.\n\n${이름들}\n\n선택한 서류는 하나의 PDF로 합쳐 보냅니다.`,
                         { title: '팩스 전송', confirmText: '발송' })) return;

    const btn = _attPop.querySelector('#attFaxSend');
    btn.disabled = true; btn.textContent = '보내는 중…';

    try {
      const res = await fetch(_attSendUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: JSON.stringify({
          recipient_type: 'custom',
          fax_no:         faxNo,
          documents:      picked.filter(r => r.kind === 'doc').map(r => r.code),
          attachment_ids: picked.filter(r => r.kind === 'att').map(r => r.id),
        }),
      });
      const d = await res.json();
      if (d.success) { showToast(d.message || '팩스를 보냈습니다.', 'success'); attFaxClose(); }
      else           { showToast(d.message || '보내지 못했습니다.', 'danger'); }
    } catch (e) {
      showToast('보내지 못했습니다.', 'danger');
    } finally {
      btn.disabled = false; btn.textContent = '팩스 전송';
    }
  }

  window.__orderGrid = grid;
  window.dsBindSelCount(grid, 'orderSelCount');

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
    cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted);"><i class="bx bx-loader-alt bx-spin" style="font-size:22px;"></i><div style="margin-top:8px;">불러오는 중...</div></div>';
    window.pnlShow('detail');
    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      cont.innerHTML = await res.text();
      // 주입된 <script>는 innerHTML로는 실행되지 않으므로 재생성해 실행
      cont.querySelectorAll('script').forEach(function (old) {
        const s = document.createElement('script');
        if (old.src) s.src = old.src; else s.textContent = old.textContent;
        old.parentNode.replaceChild(s, old);
      });
    } catch (e) {
      cont.innerHTML = '<div style="text-align:center;padding:48px;color:var(--danger);">상세를 불러오지 못했습니다.</div>';
    }
  };

  // 행 더블클릭 → 상세내용 탭에 주문 상세를 인페이지로 표시(페이지 이동 없음)
  document.getElementById('orderGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.id) return;
    window.pnlLoadDetail(DETAIL_BASE + '/' + row.id + '?partial=1');
  });
})();
</script>
<script>
async function fetchWwStatus(orderId, url) {  /* (미사용) */
  const cell = document.getElementById('ww-cell-' + orderId);
  const btn  = cell.querySelector('button');
  if (btn) { btn.textContent = '...'; btn.disabled = true; }

  try {
    const res  = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
    });
    const data = await res.json();

    if (data.success) {
      const soBadges = {
        '02':'primary','03':'info','51':'info',
        '04':'warning','52':'warning','05':'success',
        '06':'secondary','99':'secondary',
      };
      const shipBadges = {
        '02':'secondary','14':'secondary','15':'secondary','17':'secondary',
        '52':'info','55':'info','61':'warning','68':'warning',
        '95':'success','16':'info','53':'info','92':'info',
      };

      // Rebuild cell content
      const soNo = cell.querySelector('div[style*="font-weight:700"]');
      // Remove everything except SO number div
      Array.from(cell.children).forEach(el => { if (el !== soNo) el.remove(); });

      // SO status badge
      const soBadge = soBadges[data.status] ?? 'secondary';
      const soSpan = document.createElement('span');
      soSpan.className = 'badge badge-' + soBadge;
      soSpan.style.fontSize = '10px';
      soSpan.textContent = data.status_label;
      cell.appendChild(soSpan);

      // Ship info section
      if (data.ship) {
        const shipBadge = shipBadges[data.ship.ship_status] ?? 'secondary';
        const shipDiv = document.createElement('div');
        shipDiv.style.cssText = 'margin-top:4px;border-top:1px dashed var(--border);padding-top:3px;';
        shipDiv.innerHTML =
          '<div style="font-size:10px;color:var(--text-muted);margin-bottom:2px;">출고 ' + data.ship.ship_no + '</div>' +
          '<span class="badge badge-' + shipBadge + '" style="font-size:10px;">' + data.ship.ship_status_label + '</span>' +
          (data.ship.tracking_no ? '<div style="font-size:10px;color:var(--text-muted);margin-top:2px;">' + data.ship.tracking_no + '</div>' : '');
        cell.appendChild(shipDiv);
      }

      // Refresh button
      const newBtn = document.createElement('button');
      newBtn.onclick = function() { fetchWwStatus(orderId, url); };
      newBtn.style.cssText = 'display:block;margin:4px auto 0;font-size:10px;padding:1px 7px;border:1px solid var(--border);border-radius:4px;background:#fff;cursor:pointer;color:var(--text-muted);';
      newBtn.textContent = '새로고침';
      cell.appendChild(newBtn);
    } else {
      if (btn) { btn.textContent = '재시도'; btn.disabled = false; }
    }
  } catch (e) {
    if (btn) { btn.textContent = '재시도'; btn.disabled = false; }
  }
}
</script>
@endpush
