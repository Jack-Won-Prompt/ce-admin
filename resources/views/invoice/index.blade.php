{{-- resources/views/invoice/index.blade.php --}}
@extends('layouts.app')

@section('title', '계산서 발행')
@section('page-title', '계산서 발행')
@section('breadcrumb', '홈 / 계산서 발행')

@push('styles')
<style>
/* ── 요약 카드 (Vuexy icon stat card) ── */
.summary-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:1100px){ .summary-grid{grid-template-columns:repeat(3,1fr);} }
@media(max-width:700px) { .summary-grid{grid-template-columns:repeat(2,1fr);} }
.s-card {
  background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
  padding:18px 20px; box-shadow:var(--shadow);
  display:flex; align-items:center; gap:16px;
}
.s-card .sc-icon { width:48px;height:48px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px; }
.s-card.blue  .sc-icon { background:var(--primary-light); color:var(--primary); }
.s-card.green .sc-icon { background:var(--success-light); color:var(--success); }
.s-card.teal  .sc-icon { background:var(--info-light);    color:var(--info); }
.s-card.gray  .sc-icon { background:var(--border-light);  color:var(--text-muted); }
.s-card .s-label { font-size:11.5px;font-weight:600;color:var(--text-muted);margin-bottom:3px; }
.s-card .s-value { font-size:22px;font-weight:800;line-height:1;color:var(--text-primary); }
.s-card .s-sub   { font-size:11px;color:var(--text-muted);margin-top:3px; }
.s-card.blue  .s-value { color:var(--primary); }
.s-card.green .s-value { color:var(--success); }

/* ── Vuexy pill tabs ── */
.inv-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.inv-tab {
  display:inline-flex; align-items:center; gap:6px;
  padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
  border:1.5px solid var(--border); background:#fff;
  color:var(--text-secondary); text-decoration:none;
  transition:var(--transition); cursor:pointer;
}
.inv-tab:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
.inv-tab.active { border-color:var(--primary); background:var(--primary); color:#fff; }
.inv-tab .cnt { font-size:10.5px;font-weight:700;padding:0 5px;border-radius:20px;background:rgba(255,255,255,.25); }

/* ── 패널 탭 (발행현황 / 상세보기) ── */
.panel-tabs {
  display:flex; border-bottom:2px solid var(--border);
  margin-bottom:0; background:#fff;
  border-radius:var(--radius-lg) var(--radius-lg) 0 0;
  overflow:hidden;
}
.panel-tab-btn {
  display:inline-flex; align-items:center; gap:8px;
  padding:11px 22px; font-size:13px; font-weight:600;
  color:var(--text-secondary); border:none; background:none;
  cursor:pointer; border-bottom:3px solid transparent;
  margin-bottom:-2px; transition:var(--transition);
  white-space:nowrap;
}
.panel-tab-btn:hover { color:var(--primary); background:var(--primary-light); }
.panel-tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); background:#fff; }
.panel-tab-btn .badge-cnt {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:18px; height:18px; padding:0 5px;
  background:var(--primary); color:#fff;
  border-radius:20px; font-size:10px; font-weight:700;
}
.panel-tab-btn.active .badge-cnt { background:var(--primary); }

/* ── 패널 콘텐츠 ── */
.panel-body { display:none; }
.panel-body.active { display:block; }

/* ── 필터바 ── */
.filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:center;
  padding:12px 16px; border-bottom:1px solid var(--border); background:var(--bg); }

/* ── 테이블 ── */
.table-scroll { max-height:calc(100vh - 400px); overflow-y:auto; }
.table-scroll thead th { position:sticky;top:0;z-index:5;background:var(--bg); }
.order-row { cursor:pointer; transition:background .12s; }
.order-row:hover { background:var(--primary-light) !important; }
.order-row.selected { background:var(--primary-light) !important; }
.order-no { font-size:12px;font-weight:700;color:var(--primary); }
.amount-r { text-align:right;font-variant-numeric:tabular-nums; }

/* ── 상태 셀 ── */
.inv-cell { display:flex; flex-direction:column; align-items:flex-start; gap:4px; }
.inv-issued    { color:var(--success);font-size:11px;font-weight:700; }
.inv-none      { color:var(--text-muted);font-size:11px; }
.inv-cancelled { color:var(--danger);font-size:11px;font-weight:600; }
.type-badge {
  display:inline-flex; align-items:center; gap:3px;
  padding:2px 7px; border-radius:20px; font-size:10px; font-weight:700;
}
.type-tax  { background:var(--success-light); color:var(--success); }
.type-cash { background:var(--info-light);    color:var(--info); }
.type-none { background:var(--border-light);  color:var(--text-muted); }

/* ── 상세보기 패널 ── */
.detail-empty {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:60px 20px; color:var(--text-muted); gap:14px;
}
.detail-empty i { font-size:48px; opacity:.3; }
.detail-empty p { font-size:13px; }

.detail-wrap { padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:900px){ .detail-wrap{grid-template-columns:1fr;} }

.detail-section {
  background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
  overflow:hidden;
}
.detail-section-hd {
  display:flex; align-items:center; gap:8px;
  padding:11px 16px; background:var(--bg); border-bottom:1px solid var(--border);
  font-size:13px; font-weight:700;
}
.detail-section-hd i { font-size:14px; }
.detail-section-bd { padding:16px; }

.dl { display:grid; grid-template-columns:90px 1fr; gap:8px 12px; font-size:13px; }
.dl dt { color:var(--text-muted); font-weight:600; font-size:12px; padding-top:1px; }
.dl dd { margin:0; font-weight:500; }

.inv-status-box {
  border-radius:var(--radius); padding:14px; margin-bottom:12px;
  border:1.5px solid var(--border);
}
.inv-status-box.status-issued    { border-color:var(--success); background:var(--success-light); }
.inv-status-box.status-cancelled { border-color:var(--danger);  background:var(--danger-light); }
.inv-status-box.status-none      { border-color:var(--border);  background:var(--bg); }

.inv-status-row {
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
}
.inv-status-label {
  display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700;
}
.inv-status-meta {
  font-size:11px; color:var(--text-muted); margin-top:6px;
  display:flex; flex-wrap:wrap; gap:4px 14px;
}
.inv-status-meta span { display:inline-flex; align-items:center; gap:4px; }

.detail-action-bar {
  display:flex; gap:8px; flex-wrap:wrap;
  padding:12px 16px; border-top:1px solid var(--border);
  background:var(--bg); justify-content:flex-end;
}

/* ── 모달 ── */
.modal-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(15,23,42,.55); z-index:1000;
  align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
  background:#fff; border-radius:var(--radius-lg);
  box-shadow:var(--shadow-lg); width:480px; max-width:95vw; max-height:90vh;
  overflow-y:auto;
}
.modal-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 18px; border-bottom:1px solid var(--border);
}
.modal-title  { font-size:14px;font-weight:700; }
.modal-body   { padding:18px; }
.modal-footer { padding:12px 18px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
.btn-close-modal { background:none;border:none;cursor:pointer;font-size:18px;color:var(--text-muted); }
.order-info-box {
  background:var(--primary-light); border:1px solid var(--primary-accent);
  border-radius:var(--radius); padding:10px 14px; margin-bottom:14px; font-size:12px;
}
.tax-calc-row {
  background:var(--bg); border-radius:var(--radius);
  padding:10px 12px; font-size:12px; margin-top:8px;
}
.tax-calc-row b { color:var(--primary); }
.amount-hint { font-size:11px;color:var(--text-muted);margin-top:3px; }
</style>
@endpush

@section('header-actions')
<a href="{{ route('orders.index') }}" class="btn btn-outline btn-sm">
  <i class="fa-solid fa-cart-shopping"></i> 주문 목록
</a>
@endsection

@php
  $statusFilterTabs = [
    'all'          => ['전체',            $counts['total'],        'gray'],
    'tax_pending'  => ['세금계산서 미발행', $counts['tax_pending'],  'blue'],
    'cash_pending' => ['현금영수증 미발행', $counts['cash_pending'], 'teal'],
    'tax_issued'   => ['세금계산서 발행',   $counts['tax_issued'],   'green'],
    'cash_issued'  => ['현금영수증 발행',   $counts['cash_issued'],  'green'],
  ];
@endphp

@section('content')

{{-- DB 마이그레이션 안내 --}}
@if(!$taxColExists)
<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
  <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
  <div>
    <strong>DB 마이그레이션 필요</strong> —
    <code>database/migrations/tax_receipt_SQL.txt</code> 를 phpMyAdmin에서 실행해야
    계산서 발행/조회가 가능합니다.
  </div>
</div>
@endif

{{-- ── 요약 카드 ── --}}
<div class="summary-grid">
  <div class="s-card gray">
    <div class="s-label">계산서 발행 대상</div>
    <div class="s-value">{{ $counts['total'] }}</div>
    <div class="s-sub">주문확정 · 배송중 · 배송완료</div>
  </div>
  <div class="s-card blue">
    <div class="s-label">세금계산서 미발행</div>
    <div class="s-value" style="color:var(--primary);">{{ $counts['tax_pending'] }}</div>
    <div class="s-sub">발행 대기 중</div>
  </div>
  <div class="s-card teal">
    <div class="s-label">현금영수증 미발행</div>
    <div class="s-value" style="color:var(--info);">{{ $counts['cash_pending'] }}</div>
    <div class="s-sub">발행 대기 중</div>
  </div>
  <div class="s-card green">
    <div class="s-label">이번달 세금계산서</div>
    <div class="s-value" style="color:var(--success);font-size:16px;">{{ number_format($monthlyTaxAmount) }}원</div>
    <div class="s-sub">{{ now()->format('m') }}월 공급가액 합계</div>
  </div>
  <div class="s-card green">
    <div class="s-label">이번달 현금영수증</div>
    <div class="s-value" style="color:var(--info);font-size:16px;">{{ number_format($monthlyCashAmount) }}원</div>
    <div class="s-sub">{{ now()->format('m') }}월 발행금액 합계</div>
  </div>
</div>

{{-- ── 상태 필터 탭 ── --}}
<div class="inv-tabs">
  @foreach($statusFilterTabs as $key => [$label, $count, $color])
    <a href="{{ route('invoice.index', array_merge(request()->except('tab','page'), ['tab'=>$key])) }}"
       class="inv-tab {{ $tab === $key ? 'active' : '' }}">
      {{ $label }}
      @if($count > 0)<span class="cnt">{{ $count }}</span>@endif
    </a>
  @endforeach
</div>

{{-- ══════════════════════════════════════════════════════════════
     패널 탭: 계산서 발행 현황 | 상세보기
══════════════════════════════════════════════════════════════ --}}
<div class="card" style="overflow:visible;">

  {{-- 패널 탭 헤더 --}}
  <div class="panel-tabs">
    <button class="panel-tab-btn active" id="btn-list" onclick="switchPanel('list')">
      <i class="fa-solid fa-list"></i> 계산서 발행 현황
      <span class="badge-cnt">{{ $orders->total() }}</span>
    </button>
    <button class="panel-tab-btn" id="btn-detail" onclick="switchPanel('detail')">
      <i class="fa-solid fa-file-magnifying-glass"></i> 상세보기
      <span id="detail-tab-label" style="font-size:11px;color:var(--text-muted);font-weight:500;"></span>
    </button>
  </div>

  {{-- ══ 패널 1: 발행 현황 리스트 ══ --}}
  <div class="panel-body active" id="panel-list">

    {{-- 검색 필터 --}}
    <form method="GET" action="{{ route('invoice.index') }}" class="filter-bar">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호 · 환자명" style="width:200px;">
      <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"
             style="width:140px;" title="시작일">
      <span style="color:var(--text-muted);font-size:12px;">~</span>
      <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"
             style="width:140px;" title="종료일">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-magnifying-glass"></i> 검색
      </button>
      @if(request('q') || request('date_from') || request('date_to'))
        <a href="{{ route('invoice.index', ['tab'=>$tab]) }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>

    {{-- 테이블 --}}
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>주문번호</th>
            <th>환자명</th>
            <th>제품명</th>
            <th class="amount-r">총금액</th>
            <th>주문상태</th>
            <th>주문일</th>
            <th>배송완료일</th>
            <th>세금계산서</th>
            <th>현금영수증</th>
            <th style="width:30px;text-align:center;">상세</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
            @php
              $tiStatus = $taxColExists ? ($order->tax_invoice_status ?? 'not_issued') : null;
              $crStatus = $taxColExists ? ($order->cash_receipt_status ?? 'not_issued') : null;
              $crTypes  = \App\Models\Order::CASH_RECEIPT_TYPE_LABELS;
              $osBadge  = match($order->status) {
                'confirmed' => ['주문확정','var(--primary)'],
                'shipping'  => ['배송중','var(--warning)'],
                'delivered' => ['배송완료','var(--success)'],
                default     => [$order->status,'var(--text-muted)'],
              };
              // JS에서 사용할 데이터 직렬화
              $jsData = json_encode([
                'id'                    => $order->id,
                'order_number'          => $order->order_number,
                'status'                => $order->status,
                'status_label'          => $osBadge[0],
                'status_color'          => $osBadge[1],
                'patient_name'          => $order->patient?->name ?? '-',
                'patient_mobile'        => $order->patient?->mobile ?? '',
                'product_name'          => $order->product_name ?? '-',
                'total_amount'          => $order->total_amount ?? 0,
                'delivered_at'          => $order->delivered_at?->format('Y-m-d') ?? '-',
                'created_at'            => $order->created_at?->format('Y-m-d') ?? '-',
                'tax_col_exists'        => $taxColExists,
                'ti_status'             => $tiStatus,
                'ti_no'                 => $order->tax_invoice_no ?? '',
                'ti_type'               => $order->tax_invoice_type ?? '',
                'ti_biz_name'           => $order->tax_invoice_biz_name ?? '',
                'ti_biz_no'             => $order->tax_invoice_biz_no ?? '',
                'ti_email'              => $order->tax_invoice_email ?? '',
                'ti_supply'             => $order->tax_invoice_supply ?? 0,
                'ti_vat'                => $order->tax_invoice_vat ?? 0,
                'ti_issued_at'          => $order->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '',
                'ti_cancelled_at'       => $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') ?? '',
                'cr_status'             => $crStatus,
                'cr_no'                 => $order->cash_receipt_no ?? '',
                'cr_type'               => $order->cash_receipt_type ?? '',
                'cr_type_label'         => isset($order->cash_receipt_type) ? ($crTypes[$order->cash_receipt_type] ?? '') : '',
                'cr_identifier'         => $order->cash_receipt_identifier ?? '',
                'cr_amount'             => $order->cash_receipt_amount ?? 0,
                'cr_issued_at'          => $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '',
                'cr_cancelled_at'       => $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') ?? '',
              ], JSON_UNESCAPED_UNICODE);
            @endphp
            <tr class="order-row" id="row-{{ $order->id }}"
                onclick='selectOrder({{ $jsData }})'>
              <td><span class="order-no">{{ $order->order_number }}</span></td>
              <td style="font-weight:600;">{{ $order->patient?->name ?? '-' }}</td>
              <td style="font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                  title="{{ $order->product_name }}">{{ $order->product_name ?? '-' }}</td>
              <td class="amount-r fw-bold">{{ number_format($order->total_amount) }}원</td>
              <td>
                <span style="font-size:11px;font-weight:700;color:{{ $osBadge[1] }};">{{ $osBadge[0] }}</span>
              </td>
              <td style="font-size:12px;color:var(--text-muted);">
                {{ $order->created_at->format('Y-m-d') }}
              </td>
              <td style="font-size:12px;color:var(--text-muted);">
                {{ $order->delivered_at?->format('Y-m-d') ?? '-' }}
              </td>

              {{-- 세금계산서 상태 --}}
              <td>
                @if(!$taxColExists)
                  <span class="type-badge type-none">-</span>
                @elseif($tiStatus === 'issued')
                  <span class="type-badge type-tax"><i class="fa-solid fa-check"></i> 발행완료</span>
                @elseif($tiStatus === 'cancelled')
                  <span class="inv-cancelled"><i class="fa-solid fa-ban"></i> 취소</span>
                @else
                  <span class="inv-none">미발행</span>
                @endif
              </td>

              {{-- 현금영수증 상태 --}}
              <td>
                @if(!$taxColExists)
                  <span class="type-badge type-none">-</span>
                @elseif($crStatus === 'issued')
                  <span class="type-badge type-cash"><i class="fa-solid fa-check"></i> 발행완료</span>
                @elseif($crStatus === 'cancelled')
                  <span class="inv-cancelled"><i class="fa-solid fa-ban"></i> 취소</span>
                @else
                  <span class="inv-none">미발행</span>
                @endif
              </td>

              <td style="text-align:center;">
                <button class="btn btn-outline btn-sm" style="padding:3px 8px;"
                        onclick='event.stopPropagation(); selectOrder({{ $jsData }})'>
                  <i class="fa-solid fa-chevron-right"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">
                <i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                조회된 주문이 없습니다.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($orders->hasPages())
      <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:center;">
        {{ $orders->links() }}
      </div>
    @endif
  </div>{{-- /panel-list --}}

  {{-- ══ 패널 2: 상세보기 ══ --}}
  <div class="panel-body" id="panel-detail">

    {{-- 선택 전 안내 --}}
    <div class="detail-empty" id="detail-empty">
      <i class="fa-solid fa-file-magnifying-glass"></i>
      <p>좌측 <strong>계산서 발행 현황</strong> 탭에서 항목을 클릭하면<br>상세 내용이 이곳에 표시됩니다.</p>
      <button class="btn btn-outline btn-sm" onclick="switchPanel('list')">
        <i class="fa-solid fa-list"></i> 목록으로 이동
      </button>
    </div>

    {{-- 상세 내용 (선택 후 표시) --}}
    <div id="detail-content" style="display:none;">

      {{-- 헤더바 --}}
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:14px 20px;border-bottom:1px solid var(--border);background:var(--bg);">
        <div style="display:flex;align-items:center;gap:10px;">
          <button class="btn btn-outline btn-sm" onclick="switchPanel('list')">
            <i class="fa-solid fa-arrow-left"></i> 목록
          </button>
          <span id="d-order-no" style="font-size:14px;font-weight:800;color:var(--primary);"></span>
          <span id="d-status-badge" style="font-size:11px;font-weight:700;padding:2px 8px;
                border-radius:12px;background:var(--primary-light);"></span>
        </div>
        <a id="d-order-link" href="#" class="btn btn-outline btn-sm" target="_blank">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> 주문 상세
        </a>
      </div>

      {{-- 상세 그리드 --}}
      <div class="detail-wrap">

        {{-- ── 좌측 컬럼 ── --}}
        <div style="display:flex;flex-direction:column;gap:16px;">

          {{-- 기본 정보 --}}
          <div class="detail-section">
            <div class="detail-section-hd">
              <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i> 주문 기본 정보
            </div>
            <div class="detail-section-bd">
              <dl class="dl">
                <dt>환자명</dt><dd id="d-patient-name" style="font-weight:700;"></dd>
                <dt>연락처</dt><dd id="d-patient-mobile"></dd>
                <dt>제품명</dt><dd id="d-product-name"></dd>
                <dt>총금액</dt><dd id="d-total-amount" style="font-weight:700;color:var(--primary);font-size:15px;"></dd>
                <dt>주문일</dt><dd id="d-created-at"></dd>
                <dt>배송완료일</dt><dd id="d-delivered-at"></dd>
              </dl>
            </div>
          </div>

          {{-- 세금계산서 --}}
          <div class="detail-section" id="d-ti-section">
            <div class="detail-section-hd">
              <i class="fa-solid fa-file-invoice" style="color:var(--success);"></i> 세금계산서
            </div>
            <div class="detail-section-bd">
              <div id="d-ti-box" class="inv-status-box status-none">
                <div class="inv-status-row">
                  <div class="inv-status-label" id="d-ti-status-label"></div>
                </div>
                <div class="inv-status-meta" id="d-ti-meta"></div>
              </div>
            </div>
            <div class="detail-action-bar" id="d-ti-actions"></div>
          </div>

        </div>

        {{-- ── 우측 컬럼 ── --}}
        <div style="display:flex;flex-direction:column;gap:16px;">

          {{-- 금액 요약 --}}
          <div class="detail-section">
            <div class="detail-section-hd">
              <i class="fa-solid fa-calculator" style="color:var(--warning);"></i> 금액 요약
            </div>
            <div class="detail-section-bd">
              <dl class="dl" id="d-amount-dl">
                <dt>총 결제금액</dt><dd id="d-total-fmt" style="font-weight:700;"></dd>
                <dt>공급가액</dt><dd id="d-ti-supply-fmt" style="color:var(--success);font-weight:600;"></dd>
                <dt>부가세(10%)</dt><dd id="d-ti-vat-fmt"></dd>
              </dl>
            </div>
          </div>

          {{-- 현금영수증 --}}
          <div class="detail-section" id="d-cr-section">
            <div class="detail-section-hd">
              <i class="fa-solid fa-receipt" style="color:var(--info);"></i> 현금영수증
            </div>
            <div class="detail-section-bd">
              <div id="d-cr-box" class="inv-status-box status-none">
                <div class="inv-status-row">
                  <div class="inv-status-label" id="d-cr-status-label"></div>
                </div>
                <div class="inv-status-meta" id="d-cr-meta"></div>
              </div>
            </div>
            <div class="detail-action-bar" id="d-cr-actions"></div>
          </div>

        </div>
      </div>
    </div>{{-- /detail-content --}}
  </div>{{-- /panel-detail --}}

</div>{{-- /card --}}


{{-- ══════════ 세금계산서 발행 모달 ══════════ --}}
<div class="modal-overlay" id="taxModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-file-invoice" style="color:var(--success);"></i> 세금계산서 발행</div>
      <button class="btn-close-modal" onclick="closeTaxModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="order-info-box">
        <div style="font-weight:700;font-size:13px;" id="tax_order_label">-</div>
        <div style="color:var(--text-secondary);margin-top:2px;" id="tax_amount_label">-</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 유형 <span>*</span></label>
        <select id="ti_type" class="form-control form-select" onchange="onTiTypeChange()">
          <option value="electronic">전자세금계산서</option>
          <option value="manual">일반세금계산서 (종이)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">사업자명 (공급받는자) <span>*</span></label>
        <input type="text" id="ti_biz_name" class="form-control" placeholder="○○ 주식회사">
      </div>
      <div class="form-group">
        <label class="form-label">사업자등록번호 <span>*</span></label>
        <input type="text" id="ti_biz_no" class="form-control" placeholder="000-00-00000"
               oninput="formatBizNo(this)" maxlength="12">
      </div>
      <div class="form-group" id="ti_email_wrap">
        <label class="form-label">이메일 (전자 발송용)</label>
        <input type="email" id="ti_email" class="form-control" placeholder="tax@company.com">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">공급가액 <span>*</span></label>
          <input type="number" id="ti_supply" class="form-control" placeholder="0" oninput="calcTiVat()">
        </div>
        <div class="form-group">
          <label class="form-label">부가세 (VAT 10%)</label>
          <input type="number" id="ti_vat" class="form-control" placeholder="0" oninput="updateTiCalc()">
        </div>
      </div>
      <div class="tax-calc-row">
        공급가액 <b id="ti_calc_supply">0</b>원 + 부가세 <b id="ti_calc_vat">0</b>원 = 합계 <b id="ti_calc_total">0</b>원
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeTaxModal()">취소</button>
      <button class="btn btn-success" onclick="submitTaxInvoice()">
        <i class="fa-solid fa-file-invoice"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 현금영수증 발행 모달 ══════════ --}}
<div class="modal-overlay" id="cashModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-receipt" style="color:var(--info);"></i> 현금영수증 발행</div>
      <button class="btn-close-modal" onclick="closeCashModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="order-info-box">
        <div style="font-weight:700;font-size:13px;" id="cash_order_label">-</div>
        <div style="color:var(--text-secondary);margin-top:2px;" id="cash_amount_label">-</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 구분 <span>*</span></label>
        <div style="display:flex;gap:16px;margin-top:6px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;">
            <input type="radio" name="cr_type_r" value="income_deduction" checked onchange="onCrTypeChange()">
            소득공제 (개인)
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;">
            <input type="radio" name="cr_type_r" value="business_expense" onchange="onCrTypeChange()">
            지출증빙 (사업자)
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" id="cr_id_label">휴대폰 번호 <span>*</span></label>
        <input type="text" id="cr_identifier" class="form-control" placeholder="010-0000-0000" data-phone>
        <div class="amount-hint" id="cr_id_hint">소득공제: 환자 휴대폰 번호</div>
      </div>
      <div id="cr_autofill_wrap" style="margin:-8px 0 12px;">
        <button type="button" class="btn btn-outline btn-sm" id="cr_autofill_btn" onclick="fillPatientMobile()" style="display:none;">
          <i class="fa-solid fa-user"></i> <span id="cr_autofill_text">환자 번호 자동 입력</span>
        </button>
      </div>
      <div class="form-group">
        <label class="form-label">발행 금액 <span>*</span></label>
        <input type="number" id="cr_amount" class="form-control" placeholder="0">
        <div class="amount-hint">총 결제금액 기준 (수정 가능)</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeCashModal()">취소</button>
      <button class="btn btn-primary" onclick="submitCashReceipt()">
        <i class="fa-solid fa-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// 패널 탭 전환
// ════════════════════════════════════════════════════════════
function switchPanel(name) {
  document.querySelectorAll('.panel-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.panel-body').forEach(p => p.classList.remove('active'));
  document.getElementById('btn-' + name).classList.add('active');
  document.getElementById('panel-' + name).classList.add('active');
}

// ════════════════════════════════════════════════════════════
// 행 선택 → 상세보기 패널 채우기
// ════════════════════════════════════════════════════════════
let _curOrder = null;

function selectOrder(data) {
  _curOrder = data;

  // 행 강조
  document.querySelectorAll('.order-row').forEach(r => r.classList.remove('selected'));
  const row = document.getElementById('row-' + data.id);
  if (row) row.classList.add('selected');

  // 탭 레이블 업데이트
  document.getElementById('detail-tab-label').textContent = '— ' + data.order_number;

  // 패널 전환
  switchPanel('detail');

  // 안내 숨기고 내용 표시
  document.getElementById('detail-empty').style.display   = 'none';
  document.getElementById('detail-content').style.display = 'block';

  // ── 헤더 ──
  document.getElementById('d-order-no').textContent = data.order_number;
  const statusBadge = document.getElementById('d-status-badge');
  statusBadge.textContent  = data.status_label;
  statusBadge.style.color  = data.status_color;
  const orderLink = document.getElementById('d-order-link');
  orderLink.href = BASE_URL + '/orders/' + data.id;

  // ── 기본 정보 ──
  document.getElementById('d-patient-name').textContent   = data.patient_name;
  document.getElementById('d-patient-mobile').textContent = data.patient_mobile || '-';
  document.getElementById('d-product-name').textContent   = data.product_name;
  document.getElementById('d-total-amount').textContent   = fmt(data.total_amount) + '원';
  document.getElementById('d-created-at').textContent     = data.created_at;
  document.getElementById('d-delivered-at').textContent   = data.delivered_at;

  // ── 금액 요약 ──
  const supply = Math.round(data.total_amount / 1.1);
  const vat    = data.total_amount - supply;
  document.getElementById('d-total-fmt').textContent    = fmt(data.total_amount) + '원';
  document.getElementById('d-ti-supply-fmt').textContent = fmt(supply) + '원';
  document.getElementById('d-ti-vat-fmt').textContent   = fmt(Math.round(vat)) + '원';

  // ── 세금계산서 ──
  renderTiSection(data);

  // ── 현금영수증 ──
  renderCrSection(data);
}

function fmt(n) { return Number(n||0).toLocaleString('ko-KR'); }

// ─── 세금계산서 섹션 렌더 ───
function renderTiSection(data) {
  const box    = document.getElementById('d-ti-box');
  const label  = document.getElementById('d-ti-status-label');
  const meta   = document.getElementById('d-ti-meta');
  const actions = document.getElementById('d-ti-actions');

  box.className = 'inv-status-box';

  if (!data.tax_col_exists) {
    box.classList.add('status-none');
    label.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> DB 마이그레이션 필요';
    meta.innerHTML  = '';
    actions.innerHTML = '';
    return;
  }

  if (data.ti_status === 'issued') {
    box.classList.add('status-issued');
    label.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
      <span style="color:var(--success);">발행 완료</span>`;
    meta.innerHTML = `
      <span><i class="fa-solid fa-hashtag"></i> ${data.ti_no}</span>
      <span><i class="fa-solid fa-building"></i> ${data.ti_biz_name} (${data.ti_biz_no})</span>
      <span><i class="fa-solid fa-calendar"></i> ${data.ti_issued_at}</span>
      <span><i class="fa-solid fa-won-sign"></i> 공급가액 ${fmt(data.ti_supply)}원 + VAT ${fmt(data.ti_vat)}원</span>
      ${data.ti_email ? `<span><i class="fa-solid fa-envelope"></i> ${data.ti_email}</span>` : ''}
    `;
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm" style="color:var(--danger);"
              onclick="cancelTax(${data.id}, '${data.order_number}')">
        <i class="fa-solid fa-ban"></i> 세금계산서 취소
      </button>`;
  } else if (data.ti_status === 'cancelled') {
    box.classList.add('status-cancelled');
    label.innerHTML = `<i class="fa-solid fa-ban" style="color:var(--danger);"></i>
      <span style="color:var(--danger);">취소됨</span>`;
    meta.innerHTML = data.ti_cancelled_at
      ? `<span><i class="fa-solid fa-calendar"></i> 취소일시: ${data.ti_cancelled_at}</span>` : '';
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm"
              onclick="openTaxModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount})">
        <i class="fa-solid fa-file-invoice"></i> 재발행
      </button>`;
  } else {
    box.classList.add('status-none');
    label.innerHTML = `<i class="fa-solid fa-minus" style="color:var(--text-muted);"></i>
      <span style="color:var(--text-muted);">미발행</span>`;
    meta.innerHTML  = '<span style="font-size:12px;color:var(--text-muted);">세금계산서가 발행되지 않았습니다.</span>';
    actions.innerHTML = `
      <button class="btn btn-success btn-sm"
              onclick="openTaxModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount})">
        <i class="fa-solid fa-file-invoice"></i> 세금계산서 발행
      </button>`;
  }
}

// ─── 현금영수증 섹션 렌더 ───
function renderCrSection(data) {
  const box    = document.getElementById('d-cr-box');
  const label  = document.getElementById('d-cr-status-label');
  const meta   = document.getElementById('d-cr-meta');
  const actions = document.getElementById('d-cr-actions');

  box.className = 'inv-status-box';

  if (!data.tax_col_exists) {
    box.classList.add('status-none');
    label.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> DB 마이그레이션 필요';
    meta.innerHTML  = '';
    actions.innerHTML = '';
    return;
  }

  if (data.cr_status === 'issued') {
    box.classList.add('status-issued');
    label.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
      <span style="color:var(--success);">발행 완료</span>`;
    meta.innerHTML = `
      <span><i class="fa-solid fa-hashtag"></i> ${data.cr_no}</span>
      <span><i class="fa-solid fa-tag"></i> ${data.cr_type_label}</span>
      <span><i class="fa-solid fa-phone"></i> ${data.cr_identifier}</span>
      <span><i class="fa-solid fa-won-sign"></i> ${fmt(data.cr_amount)}원</span>
      <span><i class="fa-solid fa-calendar"></i> ${data.cr_issued_at}</span>
    `;
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm" style="color:var(--danger);"
              onclick="cancelCash(${data.id}, '${data.order_number}')">
        <i class="fa-solid fa-ban"></i> 현금영수증 취소
      </button>`;
  } else if (data.cr_status === 'cancelled') {
    box.classList.add('status-cancelled');
    label.innerHTML = `<i class="fa-solid fa-ban" style="color:var(--danger);"></i>
      <span style="color:var(--danger);">취소됨</span>`;
    meta.innerHTML = data.cr_cancelled_at
      ? `<span><i class="fa-solid fa-calendar"></i> 취소일시: ${data.cr_cancelled_at}</span>` : '';
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm"
              onclick="openCashModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount}, '${data.patient_mobile}')">
        <i class="fa-solid fa-receipt"></i> 재발행
      </button>`;
  } else {
    box.classList.add('status-none');
    label.innerHTML = `<i class="fa-solid fa-minus" style="color:var(--text-muted);"></i>
      <span style="color:var(--text-muted);">미발행</span>`;
    meta.innerHTML = '<span style="font-size:12px;color:var(--text-muted);">현금영수증이 발행되지 않았습니다.</span>';
    actions.innerHTML = `
      <button class="btn btn-primary btn-sm"
              onclick="openCashModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount}, '${data.patient_mobile}')">
        <i class="fa-solid fa-receipt"></i> 현금영수증 발행
      </button>`;
  }
}

// ════════════════════════════════════════════════════════════
// 세금계산서 모달
// ════════════════════════════════════════════════════════════
let _taxOrderId = null;

function openTaxModal(orderId, orderNo, patientName, totalAmount) {
  _taxOrderId = orderId;
  document.getElementById('tax_order_label').textContent  = `${orderNo}  —  ${patientName}`;
  document.getElementById('tax_amount_label').textContent = `총 결제금액: ${fmt(totalAmount)}원`;
  const supply = Math.round(totalAmount / 1.1);
  const vat    = totalAmount - supply;
  document.getElementById('ti_supply').value = supply;
  document.getElementById('ti_vat').value    = Math.round(vat);
  updateTiCalc();
  document.getElementById('ti_biz_name').value = '';
  document.getElementById('ti_biz_no').value   = '';
  document.getElementById('ti_email').value    = '';
  document.getElementById('taxModal').classList.add('open');
}
function closeTaxModal() { document.getElementById('taxModal').classList.remove('open'); }

function onTiTypeChange() {
  const isElec = document.getElementById('ti_type').value === 'electronic';
  document.getElementById('ti_email_wrap').style.display = isElec ? 'block' : 'none';
}
function formatBizNo(el) {
  let v = el.value.replace(/[^0-9]/g,'');
  if (v.length > 5)      v = v.slice(0,3)+'-'+v.slice(3,5)+'-'+v.slice(5,10);
  else if (v.length > 3) v = v.slice(0,3)+'-'+v.slice(3);
  el.value = v;
}
function calcTiVat() {
  const supply = parseInt(document.getElementById('ti_supply').value)||0;
  document.getElementById('ti_vat').value = Math.round(supply * 0.1);
  updateTiCalc();
}
function updateTiCalc() {
  const s = parseInt(document.getElementById('ti_supply').value)||0;
  const v = parseInt(document.getElementById('ti_vat').value)||0;
  document.getElementById('ti_calc_supply').textContent = s.toLocaleString('ko-KR');
  document.getElementById('ti_calc_vat').textContent    = v.toLocaleString('ko-KR');
  document.getElementById('ti_calc_total').textContent  = (s+v).toLocaleString('ko-KR');
}

async function submitTaxInvoice() {
  if (!_taxOrderId) return;
  const bizName = document.getElementById('ti_biz_name').value.trim();
  const bizNo   = document.getElementById('ti_biz_no').value.trim();
  const supply  = parseInt(document.getElementById('ti_supply').value)||0;
  const vat     = parseInt(document.getElementById('ti_vat').value)||0;
  if (!bizName) { showToast('사업자명을 입력해주세요.', 'warning'); return; }
  if (!bizNo)   { showToast('사업자등록번호를 입력해주세요.', 'warning'); return; }
  if (supply <= 0) { showToast('공급가액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(`${BASE_URL}/orders/${_taxOrderId}/tax-invoice`, 'POST', {
    tax_invoice_type:     document.getElementById('ti_type').value,
    tax_invoice_biz_name: bizName,
    tax_invoice_biz_no:   bizNo,
    tax_invoice_email:    document.getElementById('ti_email').value.trim() || null,
    tax_invoice_supply:   supply,
    tax_invoice_vat:      vat,
  });
  if (res.success) {
    showToast(`세금계산서 발행 완료 (${res.tax_invoice_no})`, 'success', 5000);
    closeTaxModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelTax(orderId, orderNo) {
  if (!confirm(`${orderNo} 주문의 세금계산서를 취소하시겠습니까?`)) return;
  const res = await apiRequest(`${BASE_URL}/orders/${orderId}/tax-invoice`, 'DELETE');
  if (res.success) {
    showToast('세금계산서가 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ════════════════════════════════════════════════════════════
// 현금영수증 모달
// ════════════════════════════════════════════════════════════
let _cashOrderId   = null;
let _patientMobile = '';

function openCashModal(orderId, orderNo, patientName, totalAmount, patientMobile) {
  _cashOrderId   = orderId;
  _patientMobile = patientMobile || '';
  document.getElementById('cash_order_label').textContent  = `${orderNo}  —  ${patientName}`;
  document.getElementById('cash_amount_label').textContent = `총 결제금액: ${fmt(totalAmount)}원`;
  document.getElementById('cr_amount').value     = totalAmount;
  document.getElementById('cr_identifier').value = '';
  const btn  = document.getElementById('cr_autofill_btn');
  const text = document.getElementById('cr_autofill_text');
  if (_patientMobile) {
    btn.style.display = 'inline-flex';
    text.textContent  = `환자 번호 자동 입력 (${_patientMobile})`;
  } else {
    btn.style.display = 'none';
  }
  document.querySelector('input[name="cr_type_r"][value="income_deduction"]').checked = true;
  onCrTypeChange();
  document.getElementById('cashModal').classList.add('open');
}
function closeCashModal() { document.getElementById('cashModal').classList.remove('open'); }

function onCrTypeChange() {
  const isBiz  = document.querySelector('input[name="cr_type_r"]:checked').value === 'business_expense';
  const idEl   = document.getElementById('cr_identifier');
  document.getElementById('cr_id_label').innerHTML = isBiz
    ? '사업자등록번호 <span>*</span>' : '휴대폰 번호 <span>*</span>';
  document.getElementById('cr_id_hint').textContent = isBiz
    ? '지출증빙: 사업자등록번호 입력' : '소득공제: 환자 휴대폰 번호';
  idEl.placeholder = isBiz ? '000-00-00000' : '010-0000-0000';
  idEl.value = '';
  if (isBiz) { idEl.removeAttribute('data-phone'); } else { idEl.setAttribute('data-phone', ''); }
  document.getElementById('cr_autofill_btn').style.display = (!isBiz && _patientMobile) ? 'inline-flex' : 'none';
}
function fillPatientMobile() {
  document.getElementById('cr_identifier').value = _patientMobile;
}

async function submitCashReceipt() {
  if (!_cashOrderId) return;
  const type       = document.querySelector('input[name="cr_type_r"]:checked').value;
  const identifier = document.getElementById('cr_identifier').value.trim();
  const amount     = parseFloat(document.getElementById('cr_amount').value)||0;
  if (!identifier) { showToast('식별번호(휴대폰/사업자)를 입력해주세요.', 'warning'); return; }
  if (amount <= 0)  { showToast('금액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(`${BASE_URL}/orders/${_cashOrderId}/cash-receipt`, 'POST', {
    cash_receipt_type:       type,
    cash_receipt_identifier: identifier,
    cash_receipt_amount:     amount,
  });
  if (res.success) {
    showToast(`현금영수증 발행 완료 (${res.cash_receipt_no})`, 'success', 5000);
    closeCashModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelCash(orderId, orderNo) {
  if (!confirm(`${orderNo} 주문의 현금영수증을 취소하시겠습니까?`)) return;
  const res = await apiRequest(`${BASE_URL}/orders/${orderId}/cash-receipt`, 'DELETE');
  if (res.success) {
    showToast('현금영수증이 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// 모달 외부 클릭 닫기
['taxModal','cashModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#btn-list', title: '주문 목록 탭', body: '세금계산서 또는 현금영수증을 발행할 주문을 여기서 선택합니다.' },
  { selector: '#btn-detail', title: '계산서 발행 탭', body: '주문을 선택하면 이 탭에서 세금계산서·현금영수증을 발행하고 취소할 수 있습니다.' },
  { selector: '.filter-bar', title: '필터', body: '기간, 발행 상태, 환자명으로 조회 범위를 좁힙니다.' },
];
</script>
@endpush
