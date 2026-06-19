{{-- resources/views/orders/show.blade.php --}}
@extends('layouts.app')

@section('title', '주문 상세 — ' . $order->order_number)
@section('page-title', '주문 상세')
@section('breadcrumb', '홈 / 주문관리 / ' . $order->order_number)

@push('styles')
<style>
  .order-grid { display: grid; grid-template-columns: 1fr 340px; gap: 16px; }
  @media(max-width:900px){ .order-grid { grid-template-columns:1fr; } }

  .info-rows dt {
    font-size: 11px; font-weight: 600; color: var(--text-muted);
    margin-bottom: 2px; margin-top: 10px;
  }
  .info-rows dt:first-child { margin-top: 0; }
  .info-rows dd { font-size: 13px; font-weight: 600; margin: 0; }

  .section-title {
    font-size: 12px; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .5px;
    padding-bottom: 8px; border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
  }

  .amount-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .amount-box {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px 12px; text-align: center;
  }
  .amount-box .label { font-size: 10px; color: var(--text-muted); font-weight: 600; }
  .amount-box .value { font-size: 15px; font-weight: 800; margin-top: 4px; }
  .amount-box.highlight { border-color: var(--primary); background: var(--primary-light); }
  .amount-box.highlight .value { color: var(--primary); }

  .status-flow { display: flex; align-items: center; gap: 0; margin: 14px 0; }
  .status-step {
    flex: 1; text-align: center; font-size: 11px; font-weight: 600;
    color: var(--text-muted); position: relative;
  }
  .status-step::after {
    content: '';
    position: absolute; left: 50%; top: 12px;
    width: 100%; height: 2px; background: var(--border); z-index: 0;
  }
  .status-step:last-child::after { display:none; }
  .status-step .dot {
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--border); color: #fff; font-size: 10px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 4px; position: relative; z-index: 1;
  }
  .status-step.done .dot   { background: var(--success); }
  .status-step.done        { color: var(--success); }
  .status-step.current .dot { background: var(--primary); }
  .status-step.current     { color: var(--primary); }
  .status-step.cancelled .dot { background: var(--danger); }

  .nhis-box {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 12px;
  }
  .nhis-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
  .nhis-row:last-child { margin-bottom: 0; }
  .nhis-label { font-size: 12px; color: var(--text-muted); font-weight: 600; }
  .nhis-value { font-size: 13px; font-weight: 700; }

  .action-footer {
    position: fixed; left: 260px; right: 0; bottom: 40px; z-index: 100;
    background: #fff; border-top: 1px solid var(--border);
    padding: 10px 24px;
    display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 -2px 8px rgba(0,0,0,.06);
    transition: left .28s cubic-bezier(.4,0,.2,1);
  }
  body.menu-collapsed .action-footer { left: 68px; }
  .page-body-inner { padding-bottom: 70px; }

  .tracking-row { display: flex; gap: 8px; align-items: center; }
  .tracking-row .form-control { flex: 1; }

  /* ── 세금계산서 / 현금영수증 ── */
  .receipt-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 1px solid var(--border-light);
    font-size: 13px;
  }
  .receipt-row:last-child { border-bottom: none; }
  .receipt-label { font-size: 11px; color: var(--text-muted); font-weight: 600; }
  .receipt-value { font-weight: 700; }
  .receipt-issued   { color: var(--success); }
  .receipt-cancelled{ color: var(--danger); }
  .receipt-none     { color: var(--text-muted); }

  /* 모달 */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); z-index: 1000;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal-box {
    background: #fff; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); width: 460px; max-width: 95vw;
    max-height: 90vh; overflow-y: auto;
  }
  .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
  }
  .modal-title  { font-size: 14px; font-weight: 700; }
  .modal-body   { padding: 18px; }
  .modal-footer { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
  .btn-close-modal { background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text-muted); }

  .amount-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
  .tax-calc-row { background: var(--bg); border-radius: var(--radius); padding: 10px 12px; margin-top: 8px; font-size: 12px; }
  .tax-calc-row b { color: var(--primary); }

  .ww-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:6px; font-size:11.5px; font-weight:700; }
  .ww-02 { background:var(--primary-light); color:var(--primary); }
  .ww-03,.ww-51 { background:var(--info-light); color:var(--info); }
  .ww-04,.ww-52 { background:var(--warning-light); color:var(--warning); }
  .ww-05 { background:var(--success-light); color:var(--success); }
  .ww-06,.ww-99 { background:var(--border-light); color:var(--text-muted); }
</style>
@endpush

@section('header-actions')
<a href="{{ route('orders.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록으로
</a>
@endsection

@php
  $meta     = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary'];
  $steps    = ['pending','confirmed','shipping','delivered'];
  $curIdx   = array_search($order->status, $steps);
@endphp

@section('content')
<div class="page-body-inner">

  {{-- 주문 번호 헤더 --}}
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <h2 style="font-size:20px;font-weight:800;letter-spacing:.5px;">{{ $order->order_number }}</h2>
    <span class="badge badge-{{ $meta['badge'] }}" style="font-size:13px;padding:4px 12px;">{{ $meta['label'] }}</span>
    @if($order->tracking_number)
      <span style="font-size:12px;color:var(--text-muted);">
        <i class="bx bx-truck"></i> 운송장: <strong>{{ $order->tracking_number }}</strong>
      </span>
    @endif
  </div>

  {{-- 진행 상태 표시 --}}
  @if($order->status !== 'cancelled')
  <div class="card mb-4" style="padding:16px 24px;">
    <div class="status-flow">
      @foreach(['pending'=>'주문대기','confirmed'=>'주문확정','shipping'=>'배송중','delivered'=>'배송완료'] as $s => $lbl)
        @php
          $isDone    = $curIdx !== false && array_search($s,$steps) < $curIdx;
          $isCurrent = $order->status === $s;
        @endphp
        <div class="status-step {{ $isDone ? 'done' : ($isCurrent ? 'current' : '') }}">
          <div class="dot">
            @if($isDone) <i class="bx bx-check" style="font-size:11px;"></i>
            @elseif($isCurrent) <i class="bx bxs-circle" style="font-size:7px;"></i>
            @else <i class="bx bxs-circle" style="font-size:7px;opacity:.3;"></i>
            @endif
          </div>
          {{ $lbl }}
        </div>
      @endforeach
    </div>
  </div>
  @endif

  <div class="order-grid">
    {{-- ── 왼쪽: 주문 상세 ── --}}
    <div>

      {{-- 환자 정보 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-user" style="color:var(--primary);"></i>
          <span class="card-header-title">환자 정보</span>
        </div>
        <div class="card-body">
          <div class="patient-card">
            <div class="patient-avatar"><i class="bx bx-user"></i></div>
            <div>
              <div class="patient-name">{{ $order->patient?->name ?? '-' }}</div>
              <div class="patient-detail">
                {{ $order->patient?->masked_resident_no ?? '' }}
                @if($order->patient?->mobile)
                  &nbsp;·&nbsp; {{ $order->patient->mobile }}
                @endif
              </div>
            </div>
            @if($order->patient)
              <a href="{{ route('patients.show', $order->patient) }}" class="btn btn-outline btn-sm" style="margin-left:auto;">
                환자 상세
              </a>
            @endif
          </div>
        </div>
      </div>

      {{-- 제품 / 처방전 정보 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-box" style="color:var(--primary);"></i>
          <span class="card-header-title">제품 정보</span>
          @if($order->prescription)
            <a href="{{ route('prescriptions.show', $order->prescription) }}" class="btn btn-outline btn-sm" style="margin-left:auto;">
              <i class="bx bx-file-medical"></i> 처방전 보기
            </a>
          @endif
        </div>
        <div class="card-body">
          @if($order->prescription?->items && $order->prescription->items->isNotEmpty())
            {{-- 다중 제품 --}}
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr style="background:var(--bg);">
                  <th style="padding:8px 10px;font-size:11px;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border);">제품명</th>
                  <th style="padding:8px 10px;font-size:11px;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:center;">수량</th>
                  <th style="padding:8px 10px;font-size:11px;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:right;">보험가</th>
                  <th style="padding:8px 10px;font-size:11px;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:right;">본인부담</th>
                </tr>
              </thead>
              <tbody>
                @foreach($order->prescription->items as $item)
                <tr>
                  <td style="padding:8px 10px;font-size:13px;">{{ $item->product_name }}</td>
                  <td style="padding:8px 10px;font-size:13px;text-align:center;">{{ $item->quantity }}</td>
                  <td style="padding:8px 10px;font-size:13px;text-align:right;">{{ number_format($item->insurance_price ?? 0) }}원</td>
                  <td style="padding:8px 10px;font-size:13px;text-align:right;">{{ number_format($item->patient_copay ?? 0) }}원</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          @else
            {{-- 단일 제품 --}}
            <dl class="info-rows">
              <dt>제품명</dt><dd>{{ $order->product_name ?? '-' }}</dd>
              <dt>제품코드</dt><dd>{{ $order->product_code ?? '-' }}</dd>
              <dt>수량</dt><dd>{{ $order->quantity ?? 1 }}개</dd>
              <dt>보험가 (단가)</dt><dd>{{ number_format($order->unit_price) }}원</dd>
            </dl>
          @endif
        </div>
      </div>

      {{-- 배송 정보 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-truck" style="color:var(--primary);"></i>
          <span class="card-header-title">배송 정보</span>
        </div>
        <div class="card-body">
          <dl class="info-rows">
            <dt>배송지 주소</dt>
            <dd>{{ $order->shipping_address ?? '-' }}</dd>
            <dt>예상 배송일</dt>
            <dd>{{ $order->estimated_delivery?->format('Y-m-d') ?? '-' }}</dd>
            @if($order->delivered_at)
              <dt>실제 배송 완료</dt>
              <dd>{{ $order->delivered_at->format('Y-m-d H:i') }}</dd>
            @endif
          </dl>

          {{-- 운송장 입력 --}}
          <div class="section-title" style="margin-top:16px;">운송장 번호</div>
          <div class="tracking-row">
            <input type="text" id="trackingInput" class="form-control"
                   value="{{ $order->tracking_number }}"
                   placeholder="운송장 번호 입력">
            <button class="btn btn-primary btn-sm" onclick="saveTracking()">
              <i class="bx bx-save"></i> 저장
            </button>
          </div>
        </div>
      </div>

      {{-- 세금계산서 / 현금영수증 --}}
      @php
        $taxColExists  = \Illuminate\Support\Facades\Schema::hasColumn('orders','tax_invoice_status');
        $tiStatus      = $taxColExists ? ($order->tax_invoice_status ?? 'not_issued') : null;
        $crStatus      = $taxColExists ? ($order->cash_receipt_status ?? 'not_issued') : null;
        $tiInfo        = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$tiStatus] ?? ['미발행','secondary'];
        $crInfo        = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$crStatus] ?? ['미발행','secondary'];
        $crTypeLabel   = \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$order->cash_receipt_type ?? ''] ?? '';
      @endphp
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-receipt" style="color:var(--success);"></i>
          <span class="card-header-title">세금계산서 / 현금영수증</span>
        </div>
        <div class="card-body">
          @if(!$taxColExists)
            <div class="alert alert-warning" style="font-size:12px;margin-bottom:0;">
              DB 마이그레이션 필요 — <code>tax_receipt_SQL.txt</code>를 실행해주세요.
            </div>
          @else

          {{-- ── 세금계산서 ── --}}
          <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
              <div style="font-size:12px;font-weight:700;color:var(--text-secondary);">
                <i class="bx bx-receipt"></i> 세금계산서
              </div>
              <span class="badge badge-{{ $tiInfo[1] }}">{{ $tiInfo[0] }}</span>
            </div>

            @if($tiStatus === 'issued')
              <div class="receipt-row">
                <span class="receipt-label">세금계산서 번호</span>
                <span class="receipt-value receipt-issued">{{ $order->tax_invoice_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">사업자명</span>
                <span class="receipt-value">{{ $order->tax_invoice_biz_name }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">사업자번호</span>
                <span class="receipt-value">{{ $order->tax_invoice_biz_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">공급가액</span>
                <span class="receipt-value">{{ number_format($order->tax_invoice_supply) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">부가세</span>
                <span class="receipt-value">{{ number_format($order->tax_invoice_vat) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">발행일시</span>
                <span class="receipt-value">{{ $order->tax_invoice_issued_at?->format('Y-m-d H:i') }}</span>
              </div>
              <div style="margin-top:10px;display:flex;gap:6px;">
                <button class="btn btn-outline btn-sm" onclick="printDoc('tax')">
                  <i class="bx bx-printer"></i> 출력
                </button>
                <button class="btn btn-danger btn-sm" onclick="cancelTaxInvoice()">
                  <i class="bx bx-block"></i> 취소
                </button>
              </div>
            @elseif($tiStatus === 'cancelled')
              <div class="receipt-row">
                <span class="receipt-label">취소 일시</span>
                <span class="receipt-value receipt-cancelled">{{ $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') }}</span>
              </div>
              <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="openTaxModal()">
                <i class="bx bx-revision"></i> 재발행
              </button>
            @else
              <div style="font-size:12px;color:var(--text-muted);padding:6px 0;">세금계산서가 발행되지 않았습니다.</div>
              <button class="btn btn-primary btn-sm" style="margin-top:4px;" onclick="openTaxModal()">
                <i class="bx bx-receipt"></i> 세금계산서 발행
              </button>
            @endif
          </div>

          <hr style="border:none;border-top:1px solid var(--border);margin:12px 0;">

          {{-- ── 현금영수증 ── --}}
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
              <div style="font-size:12px;font-weight:700;color:var(--text-secondary);">
                <i class="bx bx-money"></i> 현금영수증
              </div>
              <span class="badge badge-{{ $crInfo[1] }}">{{ $crInfo[0] }}</span>
            </div>

            @if($crStatus === 'issued')
              <div class="receipt-row">
                <span class="receipt-label">승인번호</span>
                <span class="receipt-value receipt-issued">{{ $order->cash_receipt_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">구분</span>
                <span class="receipt-value">{{ $crTypeLabel }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">식별번호</span>
                <span class="receipt-value">{{ $order->cash_receipt_identifier }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">금액</span>
                <span class="receipt-value">{{ number_format($order->cash_receipt_amount) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">발행일시</span>
                <span class="receipt-value">{{ $order->cash_receipt_issued_at?->format('Y-m-d H:i') }}</span>
              </div>
              <div style="margin-top:10px;display:flex;gap:6px;">
                <button class="btn btn-outline btn-sm" onclick="printDoc('cash')">
                  <i class="bx bx-printer"></i> 출력
                </button>
                <button class="btn btn-danger btn-sm" onclick="cancelCashReceipt()">
                  <i class="bx bx-block"></i> 취소
                </button>
              </div>
            @elseif($crStatus === 'cancelled')
              <div class="receipt-row">
                <span class="receipt-label">취소 일시</span>
                <span class="receipt-value receipt-cancelled">{{ $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') }}</span>
              </div>
              <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="openCashModal()">
                <i class="bx bx-revision"></i> 재발행
              </button>
            @else
              <div style="font-size:12px;color:var(--text-muted);padding:6px 0;">현금영수증이 발행되지 않았습니다.</div>
              <button class="btn btn-primary btn-sm" style="margin-top:4px;" onclick="openCashModal()">
                <i class="bx bx-money"></i> 현금영수증 발행
              </button>
            @endif
          </div>

          @endif {{-- taxColExists --}}
        </div>
      </div>

      {{-- 메모 --}}
      @if($order->note)
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-note" style="color:var(--warning);"></i>
          <span class="card-header-title">메모</span>
        </div>
        <div class="card-body">
          <p style="font-size:13px;line-height:1.6;">{{ $order->note }}</p>
        </div>
      </div>
      @endif

    </div>

    {{-- ── 오른쪽: 금액 / NHIS / 상태변경 ── --}}
    <div>

      {{-- 금액 요약 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-won" style="color:var(--success);"></i>
          <span class="card-header-title">금액 정보</span>
        </div>
        <div class="card-body">
          <div class="amount-grid">
            <div class="amount-box">
              <div class="label">건보 청구액</div>
              <div class="value" style="color:var(--info);">{{ number_format($order->nhis_amount) }}원</div>
            </div>
            <div class="amount-box">
              <div class="label">환자 본인부담</div>
              <div class="value" style="color:var(--warning);">{{ number_format($order->patient_copay) }}원</div>
            </div>
            <div class="amount-box">
              <div class="label">배송비</div>
              <div class="value">{{ number_format($order->shipping_fee) }}원</div>
            </div>
            <div class="amount-box highlight">
              <div class="label">총 결제금액</div>
              <div class="value">{{ number_format($order->total_amount) }}원</div>
            </div>
          </div>
        </div>
      </div>

      {{-- NHIS 청구 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-hospital" style="color:var(--purple);"></i>
          <span class="card-header-title">NHIS 건강보험 청구</span>
        </div>
        <div class="card-body">
          <div class="nhis-box">
            <div class="nhis-row">
              <span class="nhis-label">청구 상태</span>
              @php
                $nhisLabels = ['pending'=>['미청구','secondary'],'submitted'=>['청구 완료','primary'],'approved'=>['승인됨','success'],'rejected'=>['거부됨','danger']];
                [$nhisLbl,$nhisBadge] = $nhisLabels[$order->nhis_claim_status] ?? [$order->nhis_claim_status,'secondary'];
              @endphp
              <span class="badge badge-{{ $nhisBadge }}">{{ $nhisLbl }}</span>
            </div>
            @if($order->nhis_submitted_at)
            <div class="nhis-row">
              <span class="nhis-label">청구 일시</span>
              <span class="nhis-value">{{ $order->nhis_submitted_at->format('Y-m-d H:i') }}</span>
            </div>
            @endif
            @if($order->nhis_approved_at)
            <div class="nhis-row">
              <span class="nhis-label">승인 일시</span>
              <span class="nhis-value">{{ $order->nhis_approved_at->format('Y-m-d H:i') }}</span>
            </div>
            @endif
            @if($order->nhis_reimbursement)
            <div class="nhis-row">
              <span class="nhis-label">환급액</span>
              <span class="nhis-value text-success">{{ number_format($order->nhis_reimbursement) }}원</span>
            </div>
            @endif
          </div>

          @if($order->nhis_claim_status === 'pending' && $order->status === 'delivered')
          <button class="btn btn-primary w-full" style="margin-top:10px;" onclick="submitNhis()">
            <i class="bx bx-send"></i> NHIS 청구 송신
          </button>
          @elseif($order->nhis_claim_status === 'pending' && $order->status !== 'delivered')
          <div style="margin-top:10px;font-size:12px;color:var(--text-muted);text-align:center;">
            배송 완료 후 NHIS 청구가 가능합니다.
          </div>
          @endif
        </div>
      </div>

      {{-- Withworks 연동 상태 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-link-alt" style="color:var(--purple);"></i>
          <span class="card-header-title">Withworks 출고 현황</span>
        </div>
        <div class="card-body">
          @if($order->withworks_so_no)
            <div style="margin-bottom:10px;">
              <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:3px;">SO 번호</div>
              <div style="font-size:14px;font-weight:800;color:var(--primary);">{{ $order->withworks_so_no }}</div>
            </div>
            @if($withworksStatus)
              @php
                $wwCls = match(true) {
                  ($withworksStatus['status'] ?? '') === '02'               => 'ww-02',
                  in_array($withworksStatus['status'] ?? '', ['03','51'])   => 'ww-03',
                  in_array($withworksStatus['status'] ?? '', ['04','52'])   => 'ww-04',
                  ($withworksStatus['status'] ?? '') === '05'               => 'ww-05',
                  default                                                   => 'ww-06',
                };
                $wwShip = $withworksStatus['ship'] ?? null;
                $shipBadge = '';
                if ($wwShip) {
                  $shipBadge = match(true) {
                    in_array($wwShip['ship_status'] ?? '', ['02','14','15','17']) => 'secondary',
                    in_array($wwShip['ship_status'] ?? '', ['52','55'])           => 'info',
                    in_array($wwShip['ship_status'] ?? '', ['61','68'])           => 'warning',
                    ($wwShip['ship_status'] ?? '') === '95'                       => 'success',
                    default                                                       => 'secondary',
                  };
                }
              @endphp
              <div style="margin-bottom:10px;">
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:3px;">SO 상태</div>
                <span class="ww-badge {{ $wwCls }}">{{ $withworksStatus['status_label'] ?? '-' }}</span>
              </div>
              @if(!empty($withworksStatus['so_date']))
              <div style="margin-bottom:8px;">
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">주문일</div>
                <div style="font-size:13px;font-weight:500;">{{ $withworksStatus['so_date'] }}</div>
              </div>
              @endif
              @if(!empty($withworksStatus['delivery_date']))
              <div style="margin-bottom:8px;">
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">희망 배송일</div>
                <div style="font-size:13px;font-weight:500;">{{ $withworksStatus['delivery_date'] }}</div>
              </div>
              @endif
              @if(!empty($withworksStatus['so_amount']))
              <div style="margin-bottom:10px;">
                <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">SO 금액</div>
                <div style="font-size:13px;font-weight:700;">{{ number_format($withworksStatus['so_amount']) }}원</div>
              </div>
              @endif
              @if($wwShip)
              <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:4px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">출고 현황</div>
                <div style="margin-bottom:8px;">
                  <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:3px;">출고번호</div>
                  <div style="font-size:13px;font-weight:700;color:var(--primary);">{{ $wwShip['ship_no'] }}</div>
                </div>
                <div style="margin-bottom:8px;">
                  <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:3px;">출고 상태</div>
                  <span class="badge badge-{{ $shipBadge }}">{{ $wwShip['ship_status_label'] }}</span>
                </div>
                @if(!empty($wwShip['schedule_date']))
                <div style="margin-bottom:8px;">
                  <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">출고 예정일</div>
                  <div style="font-size:13px;font-weight:500;">{{ $wwShip['schedule_date'] }}</div>
                </div>
                @endif
                @if(!empty($wwShip['ship_complete_date']))
                <div style="margin-bottom:8px;">
                  <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">출고 완료일</div>
                  <div style="font-size:13px;font-weight:500;">{{ $wwShip['ship_complete_date'] }}</div>
                </div>
                @endif
                @if(!empty($wwShip['tracking_no']))
                <div>
                  <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:2px;">운송장번호</div>
                  <div style="font-size:13px;font-weight:700;font-family:monospace;">{{ $wwShip['tracking_no'] }}</div>
                </div>
                @endif
              </div>
              @endif
            @else
              <div style="padding:10px 12px;background:var(--border-light);border-radius:var(--radius);font-size:12px;color:var(--text-muted);">
                <i class="bx bx-info-circle"></i> Withworks 상태를 불러올 수 없습니다.
              </div>
            @endif
          @else
            <div style="padding:10px 12px;background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius);font-size:12px;color:var(--warning);font-weight:600;">
              <i class="bx bx-time"></i> Withworks 미연동 상태입니다.
            </div>
          @endif
        </div>
      </div>

      {{-- 주문 상태 변경 --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bx bx-refresh" style="color:var(--warning);"></i>
          <span class="card-header-title">상태 변경</span>
        </div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:8px;">
            @if($order->status === 'pending')
              <button class="btn btn-primary w-full" onclick="changeStatus('confirmed')">
                <i class="bx bx-check-circle"></i> 주문 확정
              </button>
            @endif
            @if($order->status === 'confirmed')
              <button class="btn btn-outline w-full" onclick="changeStatus('shipping')">
                <i class="bx bx-truck"></i> 배송 시작
              </button>
            @endif
            @if($order->status === 'shipping')
              <button class="btn btn-success w-full" onclick="changeStatus('delivered')">
                <i class="bx bx-package"></i> 배송 완료 처리
              </button>
            @endif
            @if(!in_array($order->status, ['delivered','cancelled']))
              <button class="btn btn-danger w-full" onclick="changeStatus('cancelled')"
                      style="margin-top:4px;">
                <i class="bx bx-block"></i> 주문 취소
              </button>
            @endif
            @if($order->status === 'delivered')
              <div style="text-align:center;color:var(--success);font-size:13px;font-weight:700;padding:8px;">
                <i class="bx bx-check-circle"></i> 배송 완료된 주문입니다.
              </div>
            @endif
            @if($order->status === 'cancelled')
              <div style="text-align:center;color:var(--danger);font-size:13px;font-weight:700;padding:8px;">
                <i class="bx bx-block"></i> 취소된 주문입니다.
              </div>
            @endif
          </div>
        </div>
      </div>

      {{-- 주문 메타 --}}
      <div class="card">
        <div class="card-body">
          <dl class="info-rows">
            <dt>생성자</dt><dd>{{ $order->creator?->name ?? '-' }}</dd>
            <dt>생성일시</dt><dd>{{ $order->created_at->format('Y-m-d H:i') }}</dd>
            <dt>최종 수정</dt><dd>{{ $order->updated_at->format('Y-m-d H:i') }}</dd>
          </dl>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- ── 하단 고정 액션 바 ── --}}
<div class="action-footer">
  <div style="font-size:13px;font-weight:600;color:var(--text-secondary);">
    <span class="badge badge-{{ $meta['badge'] }}">{{ $meta['label'] }}</span>
    &nbsp; {{ $order->order_number }}
    &nbsp;&middot;&nbsp; {{ $order->patient?->name ?? '-' }}
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('orders.index') }}" class="btn btn-outline btn-sm">
      <i class="bx bx-list-ul"></i> 목록
    </a>
    @if($order->prescription)
      <a href="{{ route('prescriptions.show', $order->prescription) }}" class="btn btn-outline btn-sm">
        <i class="bx bx-file"></i> 처방전
      </a>
    @endif
  </div>
</div>

{{-- ══════════ 세금계산서 발행 모달 ══════════ --}}
<div class="modal-overlay" id="taxModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="bx bx-receipt" style="color:var(--success);"></i> 세금계산서 발행</div>
      <button class="btn-close-modal" onclick="closeTaxModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;">
        <div style="font-weight:700;margin-bottom:2px;">주문 {{ $order->order_number }}</div>
        <div style="color:var(--text-secondary);">결제금액: {{ number_format($order->total_amount) }}원</div>
      </div>

      <div class="form-group">
        <label class="form-label">발행 유형 <span>*</span></label>
        <select id="ti_type" class="form-control form-select">
          <option value="electronic">전자세금계산서</option>
          <option value="manual">일반세금계산서</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">사업자명 (공급받는자) <span>*</span></label>
        <input type="text" id="ti_biz_name" class="form-control" placeholder="○○ 주식회사">
      </div>
      <div class="form-group">
        <label class="form-label">사업자등록번호 <span>*</span></label>
        <input type="text" id="ti_biz_no" class="form-control" placeholder="000-00-00000"
               oninput="formatBizNo(this)">
      </div>
      <div class="form-group" id="ti_email_group">
        <label class="form-label">이메일 (전자발송)</label>
        <input type="email" id="ti_email" class="form-control" placeholder="tax@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">공급가액 <span>*</span></label>
        <input type="number" id="ti_supply" class="form-control" placeholder="0"
               oninput="calcVat()" value="{{ round($order->total_amount / 1.1) }}">
        <div class="amount-hint">총 결제금액의 부가세 역산 기본 적용 (수정 가능)</div>
      </div>
      <div class="form-group">
        <label class="form-label">부가세 (VAT 10%) <span>*</span></label>
        <input type="number" id="ti_vat" class="form-control" placeholder="0"
               value="{{ $order->total_amount - round($order->total_amount / 1.1) }}">
      </div>
      <div class="tax-calc-row" id="taxCalcSummary">
        공급가액 <b id="calcSupply">{{ number_format(round($order->total_amount / 1.1)) }}</b>원
        + 부가세 <b id="calcVat">{{ number_format($order->total_amount - round($order->total_amount / 1.1)) }}</b>원
        = 합계 <b id="calcTotal">{{ number_format($order->total_amount) }}</b>원
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeTaxModal()">취소</button>
      <button class="btn btn-success" onclick="submitTaxInvoice()">
        <i class="bx bx-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 현금영수증 발행 모달 ══════════ --}}
<div class="modal-overlay" id="cashModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="bx bx-money" style="color:var(--info);"></i> 현금영수증 발행</div>
      <button class="btn-close-modal" onclick="closeCashModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;">
        <div style="font-weight:700;margin-bottom:2px;">주문 {{ $order->order_number }}</div>
        <div style="color:var(--text-secondary);">결제금액: {{ number_format($order->total_amount) }}원</div>
      </div>

      <div class="form-group">
        <label class="form-label">발행 구분 <span>*</span></label>
        <div style="display:flex;gap:12px;margin-top:4px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
            <input type="radio" name="cr_type" id="cr_type_income" value="income_deduction" checked
                   onchange="onCrTypeChange()">
            소득공제 (개인)
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
            <input type="radio" name="cr_type" id="cr_type_biz" value="business_expense"
                   onchange="onCrTypeChange()">
            지출증빙 (사업자)
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" id="cr_id_label">휴대폰 번호 <span>*</span></label>
        <input type="text" id="cr_identifier" class="form-control"
               placeholder="010-0000-0000" data-phone>
        <div class="amount-hint" id="cr_id_hint">소득공제: 환자 휴대폰 번호 입력</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 금액 <span>*</span></label>
        <input type="number" id="cr_amount" class="form-control"
               placeholder="0" value="{{ $order->total_amount }}">
        <div class="amount-hint">총 결제금액 기본 적용 (수정 가능)</div>
      </div>

      {{-- 환자 번호 자동 채우기 --}}
      @if($order->patient?->mobile)
      <div style="margin-top:-6px;margin-bottom:12px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="fillPatientMobile()">
          <i class="bx bx-user"></i>
          환자 번호 자동 입력 ({{ $order->patient->mobile }})
        </button>
      </div>
      @endif
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeCashModal()">취소</button>
      <button class="btn btn-primary" onclick="submitCashReceipt()">
        <i class="bx bx-money"></i> 발행
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
const ORDER_ID   = {{ $order->id }};
const ORDER_URL  = BASE_URL + '/orders/' + ORDER_ID;

// ── 상태 변경 ─────────────────────────────────────────────
async function changeStatus(status) {
  const labels = {
    confirmed: '주문을 확정하시겠습니까?',
    shipping:  '배송 시작 처리를 하시겠습니까?',
    delivered: '배송 완료 처리를 하시겠습니까?',
    cancelled: '정말 주문을 취소하시겠습니까?',
  };
  if (!confirm(labels[status] || '상태를 변경하시겠습니까?')) return;

  const res = await apiRequest(ORDER_URL + '/status', 'POST', { status });
  if (res.success) {
    showToast('상태가 변경되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ── NHIS 청구 송신 ─────────────────────────────────────────
async function submitNhis() {
  if (!confirm('NHIS 청구를 송신하시겠습니까?')) return;
  const res = await apiRequest(ORDER_URL + '/nhis', 'POST');
  if (res.success) {
    showToast(res.message || 'NHIS 청구 송신 완료', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ── 운송장 저장 ───────────────────────────────────────────
async function saveTracking() {
  const val = document.getElementById('trackingInput').value.trim();
  if (!val) { showToast('운송장 번호를 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/tracking', 'POST', { tracking_number: val });
  if (res.success) {
    showToast('운송장 번호가 저장되었습니다.', 'success');
  }
}

// ══════════ 세금계산서 ══════════════════════════════════════

function openTaxModal()  { document.getElementById('taxModal').classList.add('open'); }
function closeTaxModal() { document.getElementById('taxModal').classList.remove('open'); }

// 사업자번호 자동 포맷 000-00-00000
function formatBizNo(el) {
  let v = el.value.replace(/[^0-9]/g, '');
  if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
  else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
  el.value = v;
}

// 공급가액 변경 시 부가세 자동 계산
function calcVat() {
  const supply = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat    = Math.round(supply * 0.1);
  document.getElementById('ti_vat').value = vat;
  updateTaxCalcSummary();
}

function updateTaxCalcSummary() {
  const supply = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat    = parseInt(document.getElementById('ti_vat').value)    || 0;
  document.getElementById('calcSupply').textContent = supply.toLocaleString('ko-KR');
  document.getElementById('calcVat').textContent    = vat.toLocaleString('ko-KR');
  document.getElementById('calcTotal').textContent  = (supply + vat).toLocaleString('ko-KR');
}
document.getElementById('ti_vat')?.addEventListener('input', updateTaxCalcSummary);

// 발행 유형 변경 시 이메일 필드 표시
document.getElementById('ti_type')?.addEventListener('change', function() {
  const emailGroup = document.getElementById('ti_email_group');
  emailGroup.style.display = this.value === 'electronic' ? 'block' : 'none';
});

async function submitTaxInvoice() {
  const type    = document.getElementById('ti_type').value;
  const bizName = document.getElementById('ti_biz_name').value.trim();
  const bizNo   = document.getElementById('ti_biz_no').value.trim();
  const email   = document.getElementById('ti_email').value.trim();
  const supply  = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat     = parseInt(document.getElementById('ti_vat').value)    || 0;

  if (!bizName) { showToast('사업자명을 입력해주세요.', 'warning'); return; }
  if (!bizNo)   { showToast('사업자등록번호를 입력해주세요.', 'warning'); return; }
  if (supply <= 0) { showToast('공급가액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/tax-invoice', 'POST', {
    tax_invoice_type:     type,
    tax_invoice_biz_name: bizName,
    tax_invoice_biz_no:   bizNo,
    tax_invoice_email:    email || null,
    tax_invoice_supply:   supply,
    tax_invoice_vat:      vat,
  });

  if (res.success) {
    showToast(`세금계산서 발행 완료 (${res.tax_invoice_no})`, 'success', 5000);
    closeTaxModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelTaxInvoice() {
  if (!confirm('세금계산서를 취소하시겠습니까?')) return;
  const res = await apiRequest(ORDER_URL + '/tax-invoice', 'DELETE');
  if (res.success) {
    showToast('세금계산서가 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ══════════ 현금영수증 ══════════════════════════════════════

function openCashModal()  { document.getElementById('cashModal').classList.add('open'); }
function closeCashModal() { document.getElementById('cashModal').classList.remove('open'); }

function onCrTypeChange() {
  const isBiz = document.querySelector('input[name="cr_type"]:checked').value === 'business_expense';
  const label = document.getElementById('cr_id_label');
  const hint  = document.getElementById('cr_id_hint');
  const input = document.getElementById('cr_identifier');
  if (isBiz) {
    label.innerHTML = '사업자등록번호 <span>*</span>';
    hint.textContent = '지출증빙: 사업자등록번호 입력';
    input.placeholder = '000-00-00000';
    input.removeAttribute('data-phone');
  } else {
    label.innerHTML = '휴대폰 번호 <span>*</span>';
    hint.textContent = '소득공제: 환자 휴대폰 번호 입력';
    input.placeholder = '010-0000-0000';
    input.setAttribute('data-phone', '');
  }
  input.value = '';
}

function fillPatientMobile() {
  document.getElementById('cr_identifier').value = '{{ $order->patient?->mobile ?? "" }}';
}

async function submitCashReceipt() {
  const type       = document.querySelector('input[name="cr_type"]:checked').value;
  const identifier = document.getElementById('cr_identifier').value.trim();
  const amount     = parseFloat(document.getElementById('cr_amount').value) || 0;

  if (!identifier) { showToast('식별번호(휴대폰/사업자)를 입력해주세요.', 'warning'); return; }
  if (amount <= 0)  { showToast('금액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/cash-receipt', 'POST', {
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

async function cancelCashReceipt() {
  if (!confirm('현금영수증을 취소하시겠습니까?')) return;
  const res = await apiRequest(ORDER_URL + '/cash-receipt', 'DELETE');
  if (res.success) {
    showToast('현금영수증이 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ── 출력 ─────────────────────────────────────────────────
function printDoc(type) {
  const title = type === 'tax' ? '세금계산서' : '현금영수증';
  const no    = type === 'tax'
    ? '{{ $order->tax_invoice_no ?? "" }}'
    : '{{ $order->cash_receipt_no ?? "" }}';
  window.open(ORDER_URL + (type === 'tax' ? '/tax-invoice/print' : '/cash-receipt/print'), '_blank');
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
  { selector: '.status-flow', title: '주문 진행 상태', body: '주문 대기 → 주문 확정 → 배송 중 → 배송 완료 단계를 시각적으로 보여줍니다.' },
  { selector: '.card:nth-of-type(1)', title: '환자 정보', body: '이 주문과 연결된 환자의 기본 정보를 확인합니다.' },
  { selector: '.card:nth-of-type(2)', title: '제품 정보', body: '주문된 제품 목록, 수량, 가격, 보험 적용 금액을 확인합니다.' },
  { selector: '.card:nth-of-type(3)', title: '배송 정보', body: '운송장 번호를 입력하고 배송 상태를 관리합니다. 운송장 번호 입력 후 저장하면 배송 추적이 가능합니다.' },
  { selector: '.card:nth-of-type(4)', title: '세금계산서 / 현금영수증', body: '세금계산서 발행, 현금영수증 발행 및 취소를 여기서 처리합니다.' },
];
</script>
@endpush
