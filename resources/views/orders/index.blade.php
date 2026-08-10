{{-- resources/views/orders/index.blade.php --}}
@extends('layouts.app')

@section('title', '주문관리')
@section('page-title', '주문관리')
@section('breadcrumb', '홈 / 주문관리')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.status-tabs', title: '주문 상태 탭', body: '전체·대기·확정·배송중·배송완료·취소 탭으로 주문을 상태별 필터링합니다.' },
  { selector: '.ds-filter-card', title: '검색 필터', body: '주문번호, 환자명, SO번호로 검색하거나 날짜 범위로 조회합니다.' },
  { selector: '#orderGrid', title: '주문 목록', body: '각 행에서 주문번호·환자명·Withworks SO번호·배송 상태를 확인합니다. 행을 클릭하면 주문 상세로 이동합니다.' },
];
</script>
@endpush

@section('help-title', '주문 관리 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>생성된 모든 주문을 조회하고 배송·NHIS·영수증 상태를 관리하는 화면입니다.</div>
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
    <div class="help-item-text"><strong>Withworks SO</strong>주문 목록에서 Withworks 판매주문번호를 바로 확인합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-receipt"></i></div>
    <div class="help-item-text"><strong>세금계산서/현금영수증</strong>주문 상세에서 발행 및 취소를 처리합니다.</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* 패널 탭(목록/상세보기) */
  .pnl-tabs { display:flex; gap:16px; padding:0 16px; border-bottom:1px solid var(--border); flex-shrink:0; }
  .pnl-tab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
    color:var(--text-muted); border-bottom:1px solid transparent; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
  .pnl-tab:hover { color:var(--primary); }
  .pnl-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .ds-grid-sel { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-600); }
  .ds-grid-sel b { color:var(--primary-400); }
  .pnl-empty { color:var(--text-muted); font-size:13.5px; text-align:center; padding:60px 20px;
    background:#fff; border:1px dashed var(--border); border-radius:var(--radius-lg); }
  /* Vuexy pill tabs */
  .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .status-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .status-tab.active { border-color: var(--primary); background: var(--primary); color: #fff; }
  .status-tab .cnt {
    min-width: 20px; padding: 0 5px; height: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 20px; font-size: 10.5px; font-weight: 700;
    background: rgba(255,255,255,.25);
  }
  .status-tab:not(.active) .cnt { background: var(--border-light); color: var(--text-muted); }

  .order-number { font-size: 12px; font-weight: 700; color: var(--primary); letter-spacing: .5px; font-family: monospace; }
  .patient-name-cell { font-weight: 600; }
  .product-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
  .btn-row { display: flex; gap: 6px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }
</style>
@endpush

@section('header-actions')
<a href="{{ route('prescriptions.index') }}" class="btn btn-outline btn-sm">
  <i class="fa-solid fa-file-medical"></i> 처방전 목록
</a>
@endsection

@section('content')

{{-- ── 상태별 탭 ── --}}
@php
  $statuses = \App\Models\Order::STATUS_LABELS;
  $totalAll  = $statusCounts->sum();
  $curStatus = request('status');
@endphp

{{-- 상태 칩 — Figma 148:5526: h31 · r999 · pad 6/10 · 12/700, 건수 배지 16×16 정원 --}}
<div class="ds-chips status-tabs">
  <a href="{{ route('orders.index', array_merge(request()->except('status','page'), [])) }}"
     class="ds-chip {{ !$curStatus ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $totalAll }}</span>
  </a>
  @foreach($statuses as $key => $meta)
    <a href="{{ route('orders.index', array_merge(request()->except('status','page'), ['status' => $key])) }}"
       class="ds-chip {{ $curStatus === $key ? 'active' : '' }}">
      {{ $meta['label'] }}
      @if(($statusCounts[$key] ?? 0) > 0)
        <span class="ds-chip-count">{{ $statusCounts[$key] }}</span>
      @endif
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
{{-- 검색 필터 — Figma 148:5526: 흰 카드(r12 · pad 12/16), 검색어 2열 · 기간 2열 · 기준/정렬 1열 --}}
<form method="GET" action="{{ route('orders.index') }}" class="ds-filter-card">
  @if($curStatus)
    <input type="hidden" name="status" value="{{ $curStatus }}">
  @endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호 · 환자명 · 제품명">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <input type="date" name="date" value="{{ request('date') }}" class="form-control">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">기준/정렬</label>
      <select name="per_page" class="form-control form-select" onchange="this.form.submit()">
        @foreach([10,20,50,100] as $n)
          <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('date'))
      <a href="{{ route('orders.index', array_filter(['status'=>$curStatus])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

{{-- 패널 탭: 조회 결과 / 상세 내용 — 시안은 카드 안 상단, 텍스트만 --}}
<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ $statusCounts->sum() }}</b>건</span>
      <span class="ds-grid-sel">선택 <b id="orderSelCount">0</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint">행을 <b>더블클릭</b>하면 상세내용 탭에서 확인합니다.</span>
      <button type="button" class="ds-btn" onclick="window.__orderGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>

  <div class="ds-grid-card">
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">조회 결과</button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">상세 내용</button>
    </div>
    <div id="pnlList">
      <div id="orderGrid"></div>
</div>{{-- /pnlList --}}

{{-- ── 상세내용 탭 (기존 상세 페이지 콘텐츠를 같은 페이지에 직접 주입) — 같은 카드 안 ── --}}
<div id="pnlDetail" style="display:none;padding:16px;">
  <div style="margin-bottom:12px;">
    <button type="button" class="ds-btn" onclick="pnlShow('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
  </div>
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
          <th>환자명</th>
          <th>제품명</th>
          <th>수량</th>
          <th class="amount-cell">환자부담금</th>
          <th class="amount-cell">배송비</th>
          <th class="amount-cell">총금액</th>
          <th>배송지</th>
          <th>주문유형</th>
          <th>상태</th>
          <th style="text-align:center;min-width:110px;">Withworks</th>
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
            <td class="amount-cell">
              {{ number_format($order->shipping_fee) }}원
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
<script>
(function () {
  const DETAIL_BASE = @json(url('orders'));
  const grid = new wwGrid({
    el: document.getElementById('orderGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 상단 결과바에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: [
      { header: '주문번호',   name: 'order_no',  width: 120, sortable: true },
      { header: '환자명',     name: 'patient',   width: 90,  sortable: true },
      { header: '제품명',     name: 'product',   width: 160 },
      { header: '수량',       name: 'qty',       width: 60,  editor: 'number', align: 'center' },
      { header: '환자부담금', name: 'copay',     width: 100, editor: 'number' },
      { header: '배송비',     name: 'shipping',  width: 80,  editor: 'number' },
      { header: '총금액',     name: 'total',     width: 100, editor: 'number' },
      { header: '배송지',     name: 'address',   width: 180 },
      { header: '주문유형',   name: 'so_type',   width: 90,  align: 'center' },
      { header: '상태',       name: 'status',    width: 90,  sortable: true, align: 'center' },
      { header: 'Withworks',  name: 'withworks', width: 170 },
      { header: '생성일',     name: 'created',   width: 130, sortable: true },
    ],
    data: @json($gridData),
  });
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
