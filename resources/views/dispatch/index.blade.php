{{-- resources/views/dispatch/index.blade.php --}}
@extends('layouts.app')

@section('title', '발송/발행 내역')
@section('page-title', '발송/발행 내역')
@section('breadcrumb', '홈 / 발송·발행 내역')

@push('styles')
<style>
  .type-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
  .type-tab {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
    border:1.5px solid var(--border); background:#fff;
    color:var(--text-secondary); cursor:pointer; text-decoration:none;
    transition:var(--transition);
  }
  .type-tab:hover  { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  .type-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
  .type-tab .tab-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:20px; height:18px; padding:0 6px;
    border-radius:20px; font-size:10.5px; font-weight:700;
    background:rgba(255,255,255,.25); color:inherit;
  }
  .type-tab:not(.active) .tab-count { background:var(--border-light); color:var(--text-muted); }

  .filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
  .filter-bar .form-control { height:36px; font-size:13px; }
  .filter-bar .btn { height:36px; white-space:nowrap; }

  .empty-state { text-align:center; padding:56px 24px; color:var(--text-muted); }
  .empty-state i { font-size:44px; margin-bottom:12px; display:block; opacity:.3; }
  .empty-state p { font-size:13px; margin:0; }

  .mono { font-family:monospace; font-size:12px; color:var(--primary); font-weight:700; }
  .sub-text { font-size:11px; color:var(--text-muted); margin-top:2px; }
</style>
@endpush

@section('content')

  {{-- 타입 탭 --}}
  <div class="type-tabs">
    <a href="{{ route('dispatch.index', ['type'=>'virtual_account'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='virtual_account' ? 'active' : '' }}">
      <i class="bx bx-credit-card"></i> 가상계좌 발행
      <span class="tab-count">{{ number_format($counts['virtual_account']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'tax_invoice'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='tax_invoice' ? 'active' : '' }}">
      <i class="bx bx-receipt"></i> 세금계산서 발행
      <span class="tab-count">{{ number_format($counts['tax_invoice']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'cash_receipt'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='cash_receipt' ? 'active' : '' }}">
      <i class="bx bx-money"></i> 현금영수증 발행
      <span class="tab-count">{{ number_format($counts['cash_receipt']) }}</span>
    </a>
    <a href="{{ route('dispatch.index', ['type'=>'nhis'] + request()->except('type','page')) }}"
       class="type-tab {{ $type==='nhis' ? 'active' : '' }}">
      <i class="bx bx-paper-plane"></i> NHIS 청구 발송
      <span class="tab-count">{{ number_format($counts['nhis']) }}</span>
    </a>
  </div>

  {{-- 검색 필터 --}}
  <form method="GET" action="{{ route('dispatch.index') }}" class="filter-bar">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="text" name="search" class="form-control" style="width:220px;"
           placeholder="주문번호 · 환자명 · 발행번호" value="{{ $search }}">
    <input type="date" name="date_from" class="form-control" style="width:150px;" value="{{ $dateFrom }}">
    <span style="font-size:13px;color:var(--text-muted);flex-shrink:0;">~</span>
    <input type="date" name="date_to" class="form-control" style="width:150px;" value="{{ $dateTo }}">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-magnifying-glass"></i> 검색
    </button>
    @if($search || request()->hasAny(['date_from','date_to']))
      <a href="{{ route('dispatch.index', ['type'=>$type]) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark"></i> 초기화
      </a>
    @endif
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
      <select name="per_page" class="form-control form-select" style="width:100px;height:36px;font-size:13px;"
              onchange="this.form.submit()">
        @foreach([10, 20, 50, 100] as $n)
          <option value="{{ $n }}" {{ $perPage === $n ? 'selected' : '' }}>{{ $n }}개씩</option>
        @endforeach
      </select>
      <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
        총 {{ number_format($rows->total()) }}건
      </span>
    </div>
  </form>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">

        {{-- ══ 가상계좌 발행 ══ --}}
        @if($type === 'virtual_account')
        <table>
          <thead>
            <tr>
              <th>발행일시</th>
              <th>주문번호</th>
              <th>환자명</th>
              <th>은행</th>
              <th>계좌번호</th>
              <th>금액</th>
              <th>만료일</th>
              <th>상태</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $tp)
            @php
              $order   = $tp->order;
              $patient = $order?->patient ?? $order?->prescription?->patient;
            @endphp
            <tr>
              <td>
                <div style="font-size:12px;">{{ $tp->created_at->format('Y-m-d') }}</div>
                <div class="sub-text">{{ $tp->created_at->format('H:i') }}</div>
              </td>
              <td>
                <span class="mono">{{ $order?->order_number ?? '-' }}</span>
              </td>
              <td><b>{{ $patient?->name ?? $tp->customer_name ?? '-' }}</b></td>
              <td>{{ $tp->bank_name }}</td>
              <td><span class="mono" style="letter-spacing:.5px;">{{ $tp->account_number ?? '-' }}</span></td>
              <td><b>₩{{ number_format($tp->amount) }}</b></td>
              <td>
                @if($tp->due_date)
                  <div style="font-size:12px;">{{ $tp->due_date->format('Y-m-d') }}</div>
                  @if($tp->is_expired)
                    <span class="badge badge-danger" style="font-size:10px;">만료</span>
                  @endif
                @else -
                @endif
              </td>
              <td><span class="badge badge-{{ $tp->status_badge }}">{{ $tp->status_label }}</span></td>
              <td>
                <a href="{{ route('dispatch.show', ['type'=>'virtual_account','id'=>$tp->id]) }}"
                   class="btn btn-sm btn-outline">상세</a>
              </td>
            </tr>
            @empty
            <tr><td colspan="9"><div class="empty-state">
              <i class="bx bx-credit-card"></i>
              <p>가상계좌 발행 내역이 없습니다.</p>
            </div></td></tr>
            @endforelse
          </tbody>
        </table>
        @endif

        {{-- ══ 세금계산서 발행 ══ --}}
        @if($type === 'tax_invoice')
        <table>
          <thead>
            <tr>
              <th>발행일시</th>
              <th>주문번호</th>
              <th>환자명</th>
              <th>사업자번호</th>
              <th>상호</th>
              <th>공급가액</th>
              <th>부가세</th>
              <th>계산서번호</th>
              <th>상태</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $order)
            @php
              $patient = $order->patient ?? $order->prescription?->patient;
            @endphp
            <tr>
              <td>
                <div style="font-size:12px;">{{ $order->tax_invoice_issued_at?->format('Y-m-d') ?? '-' }}</div>
                <div class="sub-text">{{ $order->tax_invoice_issued_at?->format('H:i') }}</div>
              </td>
              <td><span class="mono">{{ $order->order_number }}</span></td>
              <td><b>{{ $patient?->name ?? '-' }}</b></td>
              <td style="font-family:monospace;font-size:12px;">{{ $order->tax_invoice_biz_no ?? '-' }}</td>
              <td>{{ $order->tax_invoice_biz_name ?? '-' }}</td>
              <td>₩{{ number_format($order->tax_invoice_supply) }}</td>
              <td>₩{{ number_format($order->tax_invoice_vat) }}</td>
              <td><span class="mono">{{ $order->tax_invoice_no ?? '-' }}</span></td>
              <td>
                @php $si = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$order->tax_invoice_status] ?? ['미발행','secondary']; @endphp
                <span class="badge badge-{{ $si[1] }}">{{ $si[0] }}</span>
              </td>
              <td>
                <a href="{{ route('dispatch.show', ['type'=>'tax_invoice','id'=>$order->id]) }}"
                   class="btn btn-sm btn-outline">상세</a>
              </td>
            </tr>
            @empty
            <tr><td colspan="10"><div class="empty-state">
              <i class="bx bx-receipt"></i>
              <p>세금계산서 발행 내역이 없습니다.</p>
            </div></td></tr>
            @endforelse
          </tbody>
        </table>
        @endif

        {{-- ══ 현금영수증 발행 ══ --}}
        @if($type === 'cash_receipt')
        <table>
          <thead>
            <tr>
              <th>발행일시</th>
              <th>주문번호</th>
              <th>환자명</th>
              <th>종류</th>
              <th>식별번호</th>
              <th>발행금액</th>
              <th>영수증번호</th>
              <th>상태</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $order)
            @php
              $patient = $order->patient ?? $order->prescription?->patient;
              $crType  = \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$order->cash_receipt_type] ?? '-';
            @endphp
            <tr>
              <td>
                <div style="font-size:12px;">{{ $order->cash_receipt_issued_at?->format('Y-m-d') ?? '-' }}</div>
                <div class="sub-text">{{ $order->cash_receipt_issued_at?->format('H:i') }}</div>
              </td>
              <td><span class="mono">{{ $order->order_number }}</span></td>
              <td><b>{{ $patient?->name ?? '-' }}</b></td>
              <td><span class="badge badge-info" style="font-size:11px;">{{ $crType }}</span></td>
              <td style="font-family:monospace;font-size:12px;">{{ $order->cash_receipt_identifier ?? '-' }}</td>
              <td>₩{{ number_format($order->cash_receipt_amount) }}</td>
              <td><span class="mono">{{ $order->cash_receipt_no ?? '-' }}</span></td>
              <td>
                @php $ci = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$order->cash_receipt_status] ?? ['미발행','secondary']; @endphp
                <span class="badge badge-{{ $ci[1] }}">{{ $ci[0] }}</span>
              </td>
              <td>
                <a href="{{ route('dispatch.show', ['type'=>'cash_receipt','id'=>$order->id]) }}"
                   class="btn btn-sm btn-outline">상세</a>
              </td>
            </tr>
            @empty
            <tr><td colspan="9"><div class="empty-state">
              <i class="bx bx-money"></i>
              <p>현금영수증 발행 내역이 없습니다.</p>
            </div></td></tr>
            @endforelse
          </tbody>
        </table>
        @endif

        {{-- ══ NHIS 청구 발송 ══ --}}
        @if($type === 'nhis')
        <table>
          <thead>
            <tr>
              <th>발송일시</th>
              <th>주문번호</th>
              <th>환자명</th>
              <th>발송 팩스</th>
              <th>청구금액</th>
              <th>NHIS 부담</th>
              <th>참조번호</th>
              <th>전송상태</th>
              <th>심사결과</th>
              <th>발송자</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $log)
            @php
              $order   = $log->order;
              $patient = $order?->patient ?? $order?->prescription?->patient;
            @endphp
            <tr>
              <td>
                <div style="font-size:12px;">{{ $log->created_at->format('Y-m-d') }}</div>
                <div class="sub-text">{{ $log->created_at->format('H:i') }}</div>
              </td>
              <td>
                <span class="mono">{{ $order?->order_number ?? '-' }}</span>
              </td>
              <td><b>{{ $patient?->name ?? '-' }}</b></td>
              <td style="font-family:monospace;font-size:12px;">{{ $log->fax_number ?? '-' }}</td>
              <td>₩{{ number_format($log->claim_amount) }}</td>
              <td>₩{{ number_format($log->nhis_amount) }}</td>
              <td><span class="mono">{{ $log->reference_no ?? '-' }}</span></td>
              <td>
                @php $sl = \App\Models\NhisFaxLog::STATUS_LABELS[$log->status] ?? ['label'=>$log->status,'badge'=>'secondary']; @endphp
                <span class="badge badge-{{ $sl['badge'] }}">{{ $sl['label'] }}</span>
              </td>
              <td>
                @if($log->nhis_result)
                  @php $rl = \App\Models\NhisFaxLog::NHIS_RESULT_LABELS[$log->nhis_result] ?? ['label'=>$log->nhis_result,'badge'=>'secondary']; @endphp
                  <span class="badge badge-{{ $rl['badge'] }}">{{ $rl['label'] }}</span>
                  @if($log->approved_amount)
                    <div class="sub-text">₩{{ number_format($log->approved_amount) }}</div>
                  @endif
                @else
                  <span class="sub-text">-</span>
                @endif
              </td>
              <td style="font-size:12px;">{{ $log->sender?->name ?? '-' }}</td>
              <td>
                <a href="{{ route('dispatch.show', ['type'=>'nhis','id'=>$log->id]) }}"
                   class="btn btn-sm btn-outline">상세</a>
              </td>
            </tr>
            @empty
            <tr><td colspan="11"><div class="empty-state">
              <i class="bx bx-paper-plane"></i>
              <p>NHIS 청구 발송 내역이 없습니다.</p>
            </div></td></tr>
            @endforelse
          </tbody>
        </table>
        @endif

      </div>
    </div>

    @if($rows->hasPages())
    <div class="card-footer" style="padding:12px 16px;border-top:1px solid var(--border);">
      {{ $rows->links() }}
    </div>
    @endif
  </div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar', title: '발송 내역 검색', body: '팩스·이메일·SMS 발송 내역을 날짜·수신자·상태로 조회합니다.' },
  { selector: 'table, .card', title: '발송 목록', body: '청구서·영수증·알림 발송 이력 전체를 확인합니다. 실패 건은 빨간 배지로 표시됩니다.' },
];
</script>
@endpush
