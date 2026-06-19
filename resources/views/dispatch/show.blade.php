{{-- resources/views/dispatch/show.blade.php --}}
@extends('layouts.app')

@php
  $typeLabels = [
    'virtual_account' => ['가상계좌 발행', 'bx-credit-card',    'primary'],
    'tax_invoice'     => ['세금계산서 발행','bx-receipt',        'success'],
    'cash_receipt'    => ['현금영수증 발행','bx-money',          'info'],
    'nhis'            => ['NHIS 청구 발송', 'bx-paper-plane',   'warning'],
  ];
  $tl = $typeLabels[$type] ?? ['발행 상세', 'bx-file', 'secondary'];

  // 공통 식별자 (목록 링크용)
  $listUrl = route('dispatch.index', ['type' => $type]);
@endphp

@section('title', $tl[0] . ' 상세')
@section('page-title', $tl[0] . ' 상세')
@section('breadcrumb', '홈 / 발송·발행 내역 / ' . $tl[0])

@section('header-actions')
  <a href="{{ $listUrl }}" class="btn btn-outline btn-sm">
    <i class="bx bx-arrow-back"></i> 목록으로
  </a>
@endsection

@push('styles')
<style>
  .dispatch-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 16px;
    align-items: start;
  }
  @media(max-width:900px){ .dispatch-grid { grid-template-columns:1fr; } }

  /* 섹션 타이틀 */
  .sec-title {
    font-size: 11px; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .6px;
    padding-bottom: 8px; border-bottom: 1px solid var(--border);
    margin-bottom: 14px; display:flex; align-items:center; gap:6px;
  }
  .sec-title i { font-size:14px; color:var(--primary); }

  /* 정보 그리드 */
  .info-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0;
  }
  .info-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
  .info-cell {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-light);
  }
  .info-cell:nth-last-child(-n+2) { border-bottom: none; }
  .info-label {
    font-size: 11px; font-weight: 600; color: var(--text-muted);
    margin-bottom: 4px;
  }
  .info-value {
    font-size: 13px; font-weight: 600; color: var(--text-primary);
    word-break: break-all;
  }
  .info-value.mono { font-family: monospace; font-size: 12px; color: var(--primary); }
  .info-value.large { font-size: 16px; font-weight: 800; }

  /* 금액 카드 */
  .amount-row {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
    margin-bottom: 16px;
  }
  .amt-card {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px 14px; text-align: center;
  }
  .amt-card .alabel { font-size: 10px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
  .amt-card .avalue { font-size: 15px; font-weight: 800; }
  .amt-card.hl      { border-color: var(--primary); background: var(--primary-light); }
  .amt-card.hl .avalue { color: var(--primary); }
  .amt-card.success-hl { border-color: var(--success); background: var(--success-light); }
  .amt-card.success-hl .avalue { color: var(--success); }

  /* 헤더 배너 */
  .dispatch-header-card {
    display: flex; align-items: center; gap: 16px;
    padding: 16px 20px; border-radius: var(--radius-lg);
    margin-bottom: 16px;
    border: 1.5px solid var(--border);
    background: #fff;
  }
  .dispatch-header-icon {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
  }
  .dispatch-header-meta { flex: 1; min-width: 0; }
  .dispatch-header-no   { font-size: 18px; font-weight: 800; letter-spacing: -.3px; }
  .dispatch-header-sub  { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

  /* 사이드 정보 카드 */
  .side-info dt {
    font-size: 11px; font-weight: 600; color: var(--text-muted);
    margin-top: 10px; margin-bottom: 2px;
  }
  .side-info dt:first-child { margin-top: 0; }
  .side-info dd { font-size: 13px; font-weight: 600; margin: 0; }

  /* NHIS 타임라인 */
  .timeline { position: relative; padding-left: 24px; }
  .timeline::before {
    content: '';
    position: absolute; left: 7px; top: 6px; bottom: 6px;
    width: 2px; background: var(--border);
  }
  .tl-item { position: relative; padding: 0 0 20px 16px; }
  .tl-item:last-child { padding-bottom: 0; }
  .tl-dot {
    position: absolute; left: -17px; top: 2px;
    width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff;
    box-shadow: 0 0 0 2px var(--border);
  }
  .tl-dot.sent    { background: var(--success); box-shadow: 0 0 0 2px var(--success); }
  .tl-dot.failed  { background: var(--danger);  box-shadow: 0 0 0 2px var(--danger); }
  .tl-dot.queued  { background: var(--text-muted); }
  .tl-dot.current { background: var(--primary);  box-shadow: 0 0 0 2px var(--primary); }
  .tl-time  { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
  .tl-title { font-size: 13px; font-weight: 700; }
  .tl-sub   { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

  /* 처방 제품 테이블 */
  .mini-table { width:100%; border-collapse:collapse; font-size:12px; }
  .mini-table th {
    padding:6px 10px; font-size:10px; font-weight:700; color:var(--text-muted);
    background:var(--bg); border-bottom:1px solid var(--border); text-align:left;
  }
  .mini-table td { padding:8px 10px; border-bottom:1px solid var(--border-light); }
  .mini-table tr:last-child td { border-bottom:none; }
</style>
@endpush

@section('content')

@php
  // 공통: 발행 식별자/날짜 계산
  $dispatchNo = match($type) {
    'virtual_account' => $record->toss_order_id ?? ('-'),
    'tax_invoice'     => $order->tax_invoice_no ?? '-',
    'cash_receipt'    => $order->cash_receipt_no ?? '-',
    'nhis'            => $record->reference_no ?? '-',
  };
  $issuedAt = match($type) {
    'virtual_account' => $record->created_at,
    'tax_invoice'     => $order->tax_invoice_issued_at,
    'cash_receipt'    => $order->cash_receipt_issued_at,
    'nhis'            => $record->sent_at ?? $record->created_at,
  };
  $statusBadge = match($type) {
    'virtual_account' => [$record->status_label, $record->status_badge],
    'tax_invoice'     => [\App\Models\Order::TAX_INVOICE_STATUS_LABELS[$order->tax_invoice_status][0] ?? '-',
                          \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$order->tax_invoice_status][1] ?? 'secondary'],
    'cash_receipt'    => [\App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$order->cash_receipt_status][0] ?? '-',
                          \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$order->cash_receipt_status][1] ?? 'secondary'],
    'nhis'            => [\App\Models\NhisFaxLog::STATUS_LABELS[$record->status]['label'] ?? $record->status,
                          \App\Models\NhisFaxLog::STATUS_LABELS[$record->status]['badge'] ?? 'secondary'],
  };
  $iconColor = ['primary'=>'var(--primary)','success'=>'var(--success)','info'=>'var(--info)','warning'=>'var(--warning)'];
  $bgColor   = ['primary'=>'var(--primary-light)','success'=>'var(--success-light)','info'=>'var(--info-light)','warning'=>'var(--warning-light)'];
  $col = $tl[2];
@endphp

{{-- 헤더 배너 --}}
<div class="dispatch-header-card">
  <div class="dispatch-header-icon"
       style="background:{{ $bgColor[$col] ?? 'var(--primary-light)' }};color:{{ $iconColor[$col] ?? 'var(--primary)' }};">
    <i class="bx {{ $tl[1] }}"></i>
  </div>
  <div class="dispatch-header-meta">
    <div class="dispatch-header-no">{{ $dispatchNo }}</div>
    <div class="dispatch-header-sub">
      {{ $tl[0] }}
      &nbsp;·&nbsp;
      {{ $issuedAt?->format('Y-m-d H:i') ?? '-' }}
      @if($order)
        &nbsp;·&nbsp; 주문 <b>{{ $order->order_number }}</b>
      @endif
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-{{ $statusBadge[1] }}" style="font-size:13px;padding:6px 14px;">
      {{ $statusBadge[0] }}
    </span>
  </div>
</div>

<div class="dispatch-grid">

  {{-- ══ 왼쪽 메인 ══ --}}
  <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- ── 가상계좌 발행 상세 ── --}}
    @if($type === 'virtual_account')

    {{-- 금액 요약 --}}
    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-won"></i> 금액 정보</div>
        <div class="amount-row">
          <div class="amt-card hl">
            <div class="alabel">발행 금액</div>
            <div class="avalue">₩{{ number_format($record->amount) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">환자 본인부담</div>
            <div class="avalue">₩{{ number_format($order?->patient_copay ?? 0) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">배송비</div>
            <div class="avalue">₩{{ number_format($order?->shipping_fee ?? 0) }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- 계좌 상세 --}}
    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-bank"></i> 가상계좌 정보</div>
        {{-- 은행명 + 계좌번호 강조 블록 --}}
        <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;
                    background:var(--primary-light);border:1.5px solid var(--primary);
                    border-radius:var(--radius-lg);margin-bottom:16px;">
          <div style="width:44px;height:44px;border-radius:12px;background:var(--primary);
                      color:#fff;display:flex;align-items:center;justify-content:center;
                      font-size:18px;flex-shrink:0;">
            <i class="bx bx-bank"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:600;color:var(--primary);margin-bottom:3px;">
              {{ $record->bank_name }}
            </div>
            <div style="font-size:20px;font-weight:800;font-family:monospace;letter-spacing:2px;color:var(--text-primary);">
              {{ $record->account_number ?? '-' }}
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">예금주</div>
            <div style="font-size:14px;font-weight:700;">{{ $record->customer_name ?? '-' }}</div>
          </div>
        </div>

        <div class="info-grid cols-3">
          <div class="info-cell">
            <div class="info-label">은행명</div>
            <div class="info-value">{{ $record->bank_name }} <span style="font-size:11px;color:var(--text-muted);">(코드: {{ $record->bank }})</span></div>
          </div>
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">계좌번호</div>
            <div class="info-value mono" style="font-size:15px;letter-spacing:1px;">
              {{ $record->account_number ?? '-' }}
            </div>
          </div>
          <div class="info-cell">
            <div class="info-label">예금주</div>
            <div class="info-value">{{ $record->customer_name ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">입금 마감일</div>
            <div class="info-value">
              {{ $record->due_date?->format('Y-m-d H:i') ?? '-' }}
              @if($record->is_expired)
                <span class="badge badge-danger" style="font-size:10px;margin-left:4px;">만료</span>
              @endif
            </div>
          </div>
          <div class="info-cell">
            <div class="info-label">입금 확인일시</div>
            <div class="info-value">{{ $record->deposited_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">결제 키 (Payment Key)</div>
            <div class="info-value mono" style="font-size:11px;word-break:break-all;">
              {{ $record->payment_key ?? '-' }}
            </div>
          </div>
          <div class="info-cell" style="grid-column:span 1;">
            <div class="info-label">토스 주문 ID</div>
            <div class="info-value mono" style="font-size:11px;">{{ $record->toss_order_id ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">결제 수단</div>
            <div class="info-value">{{ $record->method ?? '가상계좌' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발행일시</div>
            <div class="info-value">{{ $record->created_at->format('Y-m-d H:i:s') }}</div>
          </div>
        </div>
      </div>
    </div>

    @endif {{-- /virtual_account --}}

    {{-- ── 세금계산서 발행 상세 ── --}}
    @if($type === 'tax_invoice')

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-won"></i> 금액 정보</div>
        <div class="amount-row">
          <div class="amt-card hl">
            <div class="alabel">공급가액</div>
            <div class="avalue">₩{{ number_format($order->tax_invoice_supply ?? 0) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">부가세 (10%)</div>
            <div class="avalue">₩{{ number_format($order->tax_invoice_vat ?? 0) }}</div>
          </div>
          <div class="amt-card success-hl">
            <div class="alabel">합계 금액</div>
            <div class="avalue">₩{{ number_format(($order->tax_invoice_supply ?? 0) + ($order->tax_invoice_vat ?? 0)) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-receipt"></i> 세금계산서 정보</div>
        <div class="info-grid cols-3">
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">계산서 번호</div>
            <div class="info-value mono" style="font-size:15px;">{{ $order->tax_invoice_no }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발행 유형</div>
            <div class="info-value">{{ $order->tax_invoice_type === 'electronic' ? '전자세금계산서' : '수기' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">공급받는 자 상호</div>
            <div class="info-value">{{ $order->tax_invoice_biz_name ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">사업자등록번호</div>
            <div class="info-value mono">{{ $order->tax_invoice_biz_no ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">이메일</div>
            <div class="info-value" style="font-size:12px;">{{ $order->tax_invoice_email ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발행일시</div>
            <div class="info-value">{{ $order->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">취소일시</div>
            <div class="info-value">{{ $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">상태</div>
            <div class="info-value">
              @php $si = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$order->tax_invoice_status] ?? ['-','secondary']; @endphp
              <span class="badge badge-{{ $si[1] }}">{{ $si[0] }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    @endif {{-- /tax_invoice --}}

    {{-- ── 현금영수증 발행 상세 ── --}}
    @if($type === 'cash_receipt')

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-won"></i> 금액 정보</div>
        <div class="amount-row" style="grid-template-columns:1fr 1fr;">
          <div class="amt-card hl">
            <div class="alabel">발행 금액</div>
            <div class="avalue">₩{{ number_format($order->cash_receipt_amount ?? 0) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">주문 총액</div>
            <div class="avalue">₩{{ number_format($order->total_amount ?? 0) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-money"></i> 현금영수증 정보</div>
        <div class="info-grid cols-3">
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">영수증 번호</div>
            <div class="info-value mono" style="font-size:15px;">{{ $order->cash_receipt_no }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발급 유형</div>
            @php $crType = \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$order->cash_receipt_type] ?? '-'; @endphp
            <div class="info-value"><span class="badge badge-info">{{ $crType }}</span></div>
          </div>
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">식별번호 (휴대폰 / 사업자번호)</div>
            <div class="info-value mono" style="font-size:14px;letter-spacing:1px;">
              {{ $order->cash_receipt_identifier ?? '-' }}
            </div>
          </div>
          <div class="info-cell">
            <div class="info-label">발행일시</div>
            <div class="info-value">{{ $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">취소일시</div>
            <div class="info-value">{{ $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">상태</div>
            @php $ci = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$order->cash_receipt_status] ?? ['-','secondary']; @endphp
            <div class="info-value"><span class="badge badge-{{ $ci[1] }}">{{ $ci[0] }}</span></div>
          </div>
        </div>
      </div>
    </div>

    @endif {{-- /cash_receipt --}}

    {{-- ── NHIS 청구 발송 상세 ── --}}
    @if($type === 'nhis')

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-won"></i> 청구 금액</div>
        <div class="amount-row">
          <div class="amt-card hl">
            <div class="alabel">총 청구금액</div>
            <div class="avalue">₩{{ number_format($record->claim_amount) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">건보 부담금</div>
            <div class="avalue">₩{{ number_format($record->nhis_amount) }}</div>
          </div>
          <div class="amt-card">
            <div class="alabel">환자 부담금</div>
            <div class="avalue">₩{{ number_format($record->patient_copay) }}</div>
          </div>
        </div>
        @if($record->approved_amount !== null)
        <div style="margin-top:8px;padding:10px 14px;background:var(--success-light);border-radius:var(--radius);border:1px solid var(--success);display:flex;align-items:center;gap:10px;">
          <i class="bx bx-check-circle" style="color:var(--success);font-size:20px;flex-shrink:0;"></i>
          <div>
            <div style="font-size:12px;font-weight:700;color:var(--success);">심사 승인금액</div>
            <div style="font-size:16px;font-weight:800;color:var(--success);">₩{{ number_format($record->approved_amount) }}</div>
          </div>
        </div>
        @endif
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-paper-plane"></i> 발송 정보</div>
        <div class="info-grid cols-3">
          <div class="info-cell" style="grid-column:span 2;">
            <div class="info-label">청구 문서명</div>
            <div class="info-value">{{ $record->document_title ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">참조번호</div>
            <div class="info-value mono">{{ $record->reference_no ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">수신 팩스 (공단)</div>
            <div class="info-value mono">{{ $record->fax_number ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발신 팩스</div>
            <div class="info-value mono">{{ $record->sender_number ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">재시도 횟수</div>
            <div class="info-value">{{ $record->retry_count }}회</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발송일시</div>
            <div class="info-value">{{ $record->sent_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">전송 확인일시</div>
            <div class="info-value">{{ $record->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</div>
          </div>
          <div class="info-cell">
            <div class="info-label">발송자</div>
            <div class="info-value">{{ $record->sender?->name ?? '-' }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- NHIS 심사 결과 --}}
    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-check-shield"></i> 심사 결과</div>
        @php
          $nhisResultLabels = \App\Models\NhisFaxLog::NHIS_RESULT_LABELS;
          $rl = $nhisResultLabels[$record->nhis_result] ?? ['label'=>'-','badge'=>'secondary'];
        @endphp
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
          <span class="badge badge-{{ $rl['badge'] }}" style="font-size:14px;padding:7px 18px;">{{ $rl['label'] }}</span>
          @if($record->nhis_result_at)
            <span style="font-size:12px;color:var(--text-muted);">{{ $record->nhis_result_at->format('Y-m-d H:i') }}</span>
          @endif
        </div>
        @if($record->nhis_message)
          <div style="padding:10px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);font-size:13px;color:var(--text-secondary);">
            {{ $record->nhis_message }}
          </div>
        @endif
        @if(!$record->nhis_message && $record->nhis_result === 'pending')
          <p style="font-size:13px;color:var(--text-muted);margin:0;">아직 심사 결과가 등록되지 않았습니다.</p>
        @endif
      </div>
    </div>

    {{-- 발송 이력 타임라인 --}}
    @if(isset($allLogs) && $allLogs->count() > 1)
    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-time-five"></i> 발송 이력 ({{ $allLogs->count() }}건)</div>
        <div class="timeline">
          @foreach($allLogs as $log)
          @php
            $dotClass = $log->status === 'sent' ? 'sent' : ($log->status === 'failed' ? 'failed' : 'queued');
            if ($log->id === $record->id) $dotClass .= ' current';
          @endphp
          <div class="tl-item">
            <div class="tl-dot {{ $dotClass }}"></div>
            <div class="tl-time">{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
            <div class="tl-title" style="{{ $log->id === $record->id ? 'color:var(--primary)' : '' }}">
              @php $sl = \App\Models\NhisFaxLog::STATUS_LABELS[$log->status] ?? ['label'=>$log->status,'badge'=>'secondary']; @endphp
              <span class="badge badge-{{ $sl['badge'] }}" style="font-size:11px;">{{ $sl['label'] }}</span>
              {{ $log->document_title ?? '' }}
              @if($log->id === $record->id)
                <span style="font-size:11px;color:var(--primary);font-weight:400;">(현재)</span>
              @endif
            </div>
            <div class="tl-sub">
              팩스: {{ $log->fax_number ?? '-' }}
              &nbsp;·&nbsp; 참조: {{ $log->reference_no ?? '-' }}
              @if($log->retry_count > 0)
                &nbsp;·&nbsp; 재시도 {{ $log->retry_count }}회
              @endif
              @if($log->sender)
                &nbsp;·&nbsp; {{ $log->sender->name }}
              @endif
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    @endif {{-- /nhis --}}

    {{-- ── 관련 주문 처방 제품 ── --}}
    @if($order && $order->prescription && $order->prescription->items->count() > 0)
    <div class="card">
      <div class="card-body">
        <div class="sec-title"><i class="bx bx-package"></i> 처방 제품 내역</div>
        <table class="mini-table">
          <thead>
            <tr>
              <th>제품명</th>
              <th>코드</th>
              <th style="text-align:right;">수량</th>
              <th style="text-align:right;">소비자가</th>
              <th style="text-align:right;">보험가</th>
              <th style="text-align:right;">환자부담</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->prescription->items as $item)
            <tr>
              <td style="font-weight:600;">{{ $item->product_name ?? '-' }}</td>
              <td style="font-family:monospace;font-size:11px;color:var(--text-muted);">{{ $item->product_code ?? '-' }}</td>
              <td style="text-align:right;">{{ $item->quantity ?? '-' }}</td>
              <td style="text-align:right;">₩{{ number_format($item->product_price ?? 0) }}</td>
              <td style="text-align:right;">₩{{ number_format($item->nhis_amount ?? 0) }}</td>
              <td style="text-align:right;font-weight:700;color:var(--primary);">₩{{ number_format($item->patient_copay ?? 0) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

  </div>

  {{-- ══ 오른쪽 사이드 ══ --}}
  <div style="display:flex;flex-direction:column;gap:14px;">

    {{-- 관련 주문 --}}
    @if($order)
    <div class="card">
      <div class="card-header" style="padding:12px 16px;border-bottom:1px solid var(--border);">
        <span style="font-size:13px;font-weight:700;">관련 주문</span>
      </div>
      <div class="card-body">
        <dl class="side-info">
          <dt>주문번호</dt>
          <dd>
            <a href="{{ route('orders.show', $order) }}"
               style="color:var(--primary);font-family:monospace;font-size:13px;">
              {{ $order->order_number }}
            </a>
          </dd>
          <dt>주문 상태</dt>
          <dd>
            @php $ol = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary']; @endphp
            <span class="badge badge-{{ $ol['badge'] }}">{{ $ol['label'] }}</span>
          </dd>
          <dt>주문 총액</dt>
          <dd>₩{{ number_format($order->total_amount ?? 0) }}</dd>
          @if($order->shipping_address)
          <dt>배송지</dt>
          <dd style="font-size:12px;font-weight:400;line-height:1.5;">{{ $order->shipping_address }}</dd>
          @endif
          @if($order->tracking_number)
          <dt>운송장번호</dt>
          <dd style="font-family:monospace;font-size:12px;">{{ $order->tracking_number }}</dd>
          @endif
          @if($order->delivered_at)
          <dt>배송 완료</dt>
          <dd>{{ $order->delivered_at->format('Y-m-d') }}</dd>
          @endif
        </dl>
      </div>
    </div>
    @endif

    {{-- 환자 정보 --}}
    @if($patient)
    <div class="card">
      <div class="card-header" style="padding:12px 16px;border-bottom:1px solid var(--border);">
        <span style="font-size:13px;font-weight:700;">환자 정보</span>
      </div>
      <div class="card-body">
        <dl class="side-info">
          <dt>환자명</dt>
          <dd>
            <a href="{{ route('patients.show', $patient) }}"
               style="color:var(--text-primary);text-decoration:none;">
              <b>{{ $patient->name }}</b>
            </a>
          </dd>
          <dt>생년월일</dt>
          <dd>{{ $patient->birth_date ? \Carbon\Carbon::parse($patient->birth_date)->format('Y-m-d') : '-' }}</dd>
          <dt>성별</dt>
          <dd>{{ $patient->gender === 'M' ? '남성' : ($patient->gender === 'F' ? '여성' : '-') }}</dd>
          <dt>연락처</dt>
          <dd>{{ $patient->mobile ?? '-' }}</dd>
          @if($patient->address)
          <dt>주소</dt>
          <dd style="font-size:12px;font-weight:400;line-height:1.5;">{{ $patient->address }}</dd>
          @endif
          <dt>건강보험 급여 대상</dt>
          <dd>
            @if($patient->is_nhis_eligible)
              <span class="badge badge-success">급여 대상</span>
              <span style="font-size:11px;color:var(--text-muted);margin-left:4px;">{{ $patient->nhis_coverage_rate }}% 적용</span>
            @else
              <span class="badge badge-secondary">비급여</span>
            @endif
          </dd>
        </dl>
      </div>
    </div>
    @endif

    {{-- 처방전 정보 --}}
    @if($prescription)
    <div class="card">
      <div class="card-header" style="padding:12px 16px;border-bottom:1px solid var(--border);">
        <span style="font-size:13px;font-weight:700;">처방전 정보</span>
      </div>
      <div class="card-body">
        <dl class="side-info">
          <dt>처방번호</dt>
          <dd>
            <a href="{{ route('prescriptions.show', $prescription) }}"
               style="color:var(--primary);font-family:monospace;font-size:13px;">
              {{ $prescription->rx_number }}
            </a>
          </dd>
          <dt>병원명</dt>
          <dd>{{ $prescription->hospital_name ?? '-' }}</dd>
          <dt>담당의</dt>
          <dd>{{ $prescription->doctor_name ?? '-' }}</dd>
          <dt>진료과</dt>
          <dd>{{ $prescription->department ?? $prescription->specialty ?? '-' }}</dd>
          @if($prescription->disease_name)
          <dt>상병명</dt>
          <dd style="font-size:12px;">{{ $prescription->disease_name }}</dd>
          @endif
          <dt>발행일</dt>
          <dd>{{ $prescription->issued_date?->format('Y-m-d') ?? '-' }}</dd>
          <dt>처방전 상태</dt>
          <dd>
            <span class="badge badge-{{ $prescription->status_badge ?? 'secondary' }}">
              {{ $prescription->status_label ?? $prescription->status }}
            </span>
          </dd>
        </dl>
      </div>
    </div>
    @endif

  </div>{{-- /side --}}

</div>{{-- /dispatch-grid --}}

@endsection
