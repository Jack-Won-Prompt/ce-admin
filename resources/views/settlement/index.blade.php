{{-- resources/views/settlement/index.blade.php --}}
@extends('layouts.app')

@section('title', '정산/회계')
@section('page-title', '정산/회계')
@section('breadcrumb', '홈 / 청구·회계 / 정산')

@push('styles')
<style>
  /* Vuexy underline tabs */
  .settle-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 22px; }
  .settle-tab  {
    padding: 11px 22px; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: var(--transition);
  }
  .settle-tab:hover { color: var(--primary); }
  .settle-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

  /* 요약 카드 */
  .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
  @media (max-width: 900px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
  .sum-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 16px;
  }
  .sum-card-icon {
    width: 48px; height: 48px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
  }
  .sum-card-label { font-size: 11.5px; color: var(--text-muted); font-weight: 500; margin-bottom: 4px; }
  .sum-card-val   { font-size: 22px; font-weight: 800; line-height: 1; color: var(--text-primary); }
  .sum-card-sub   { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
  .sum-card.blue   .sum-card-icon { background: var(--primary-light); color: var(--primary); }
  .sum-card.blue   .sum-card-val  { color: var(--primary); }
  .sum-card.green  .sum-card-icon { background: var(--success-light); color: var(--success); }
  .sum-card.green  .sum-card-val  { color: var(--success); }
  .sum-card.orange .sum-card-icon { background: var(--warning-light); color: var(--warning); }
  .sum-card.orange .sum-card-val  { color: var(--warning); }
  .sum-card.red    .sum-card-icon { background: var(--danger-light);  color: var(--danger); }
  .sum-card.red    .sum-card-val  { color: var(--danger); }

  /* 필터 바 */
  .filter-bar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
  .filter-bar .form-control { height: 34px; font-size: 12px; }
  .filter-bar .btn { height: 34px; font-size: 12px; white-space: nowrap; }
  .filter-sep { color: var(--text-muted); font-size: 12px; }

  /* API 상태 카드 */
  .toss-api-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px 18px; box-shadow: var(--shadow); }
  .api-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
  .api-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .api-dot.connected    { background: var(--success); box-shadow: 0 0 0 3px var(--success-light); }
  .api-dot.disconnected { background: var(--danger);  box-shadow: 0 0 0 3px var(--danger-light); }
  .api-dot.unknown      { background: var(--warning); box-shadow: 0 0 0 3px var(--warning-light); }
  .api-name { font-size: 13px; font-weight: 700; }
  .api-desc { font-size: 11px; color: var(--text-muted); }
  .api-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; }
  .api-meta-item { font-size: 11px; display: flex; justify-content: space-between; padding: 5px 8px; background: var(--bg); border-radius: var(--radius); }
  .api-meta-key   { color: var(--text-muted); }
  .api-meta-val   { font-weight: 600; font-family: monospace; }

  /* VA 상태 */
  .va-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .va-badge.done      { background: var(--success-light); color: var(--success); }
  .va-badge.waiting   { background: var(--warning-light); color: var(--warning); }
  .va-badge.ready     { background: var(--info-light,#e0f4fb); color: var(--info,#3b82f6); }
  .va-badge.expired   { background: var(--border); color: var(--text-muted); }
  .va-badge.none      { background: var(--border); color: var(--text-muted); }

  /* 금액 셀 */
  .amount-cell { font-family: monospace; font-size: 12px; text-align: right; }
  .amount-cell.primary { color: var(--primary); font-weight: 600; }
  .amount-cell.success { color: var(--success); }
  .amount-cell.muted   { color: var(--text-muted); }
  .order-num { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 600; }

  /* 발급 버튼 */
  .btn-issue-va { font-size: 11px; padding: 3px 10px; }
</style>
@endpush

@section('content')

  {{-- 탭 --}}
  <div class="settle-tabs">
    <a href="{{ route('settlement.index', ['tab' => 'settlement', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
       class="settle-tab {{ $tab === 'settlement' ? 'active' : '' }}">
      <i class="fa-solid fa-calculator"></i> 정산 현황
    </a>
    <a href="{{ route('settlement.index', ['tab' => 'virtual_account']) }}"
       class="settle-tab {{ $tab === 'virtual_account' ? 'active' : '' }}">
      <i class="fa-solid fa-building-columns"></i> 가상계좌 매칭
      @if(($vaStats['waiting'] ?? 0) > 0)
        <span class="badge badge-warning" style="margin-left:6px;font-size:10px;">{{ $vaStats['waiting'] }}</span>
      @endif
    </a>
  </div>

  @if($tab === 'settlement')
  {{-- ══════════════ 정산 현황 ══════════════ --}}

    <form method="GET" action="{{ route('settlement.index') }}" class="filter-bar">
      <input type="hidden" name="tab" value="settlement">
      <label style="font-size:12px;color:var(--text-muted);white-space:nowrap;">기간</label>
      <input type="date" name="date_from" class="form-control" style="width:140px;" value="{{ $dateFrom }}">
      <span class="filter-sep">–</span>
      <input type="date" name="date_to"   class="form-control" style="width:140px;" value="{{ $dateTo }}">
      <input type="text" name="search" class="form-control" style="width:180px;" placeholder="주문번호·환자명·제품명" value="{{ request('search') }}">
      <select name="status" class="form-control form-select" style="width:120px;">
        <option value="">전체 상태</option>
        <option value="pending"   {{ request('status')==='pending'   ? 'selected':'' }}>주문 대기</option>
        <option value="confirmed" {{ request('status')==='confirmed' ? 'selected':'' }}>주문 확정</option>
        <option value="shipping"  {{ request('status')==='shipping'  ? 'selected':'' }}>배송 중</option>
        <option value="delivered" {{ request('status')==='delivered' ? 'selected':'' }}>배송 완료</option>
        <option value="cancelled" {{ request('status')==='cancelled' ? 'selected':'' }}>취소</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> 조회</button>
      <a href="{{ route('settlement.index', ['tab'=>'settlement']) }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-rotate-left"></i></a>
      <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">{{ $dateFrom }} ~ {{ $dateTo }}</span>
    </form>

    <div class="summary-grid">
      <div class="sum-card blue">
        <div class="sum-card-label"><i class="fa-solid fa-cart-shopping"></i> 총 주문 건수</div>
        <div class="sum-card-val">{{ number_format($summary['total_orders']) }}<span style="font-size:13px;font-weight:500;">건</span></div>
        <div class="sum-card-sub">조회 기간 내</div>
      </div>
      <div class="sum-card blue">
        <div class="sum-card-label"><i class="fa-solid fa-won-sign"></i> 총 주문 금액</div>
        <div class="sum-card-val">{{ number_format($summary['total_amount']) }}<span style="font-size:13px;font-weight:500;">원</span></div>
        <div class="sum-card-sub">배송비 포함</div>
      </div>
      <div class="sum-card green">
        <div class="sum-card-label"><i class="fa-solid fa-hospital"></i> NHIS 청구 금액</div>
        <div class="sum-card-val">{{ number_format($summary['nhis_amount']) }}<span style="font-size:13px;font-weight:500;">원</span></div>
        <div class="sum-card-sub">급여 청구분</div>
      </div>
      <div class="sum-card orange">
        <div class="sum-card-label"><i class="fa-solid fa-user"></i> 환자 본인부담금</div>
        <div class="sum-card-val">{{ number_format($summary['patient_copay']) }}<span style="font-size:13px;font-weight:500;">원</span></div>
        <div class="sum-card-sub">가상계좌 수납 대상</div>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
      @foreach([['NHIS 환급 확정', number_format($summary['nhis_reimb']).'원', 'var(--success)'], ['배송비 합계', number_format($summary['shipping_fee']).'원', 'var(--text-primary)'], ['대기 중 주문', $statusCounts['pending'].'건', 'var(--warning)'], ['배송 완료', $statusCounts['delivered'].'건', 'var(--success)']] as [$lbl, $val, $color])
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:10px 16px;font-size:12px;min-width:160px;">
        <span style="color:var(--text-muted);">{{ $lbl }}</span>
        <span style="font-weight:700;color:{{ $color }};float:right;margin-left:16px;">{{ $val }}</span>
      </div>
      @endforeach
    </div>

    <div class="card">
      <div class="card-header">
        <i class="fa-solid fa-table-list" style="color:var(--primary);"></i>
        <span class="card-header-title">정산 목록</span>
        <span class="card-header-sub">주문 기준</span>
        <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">총 {{ number_format($orders->total()) }}건</span>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>주문번호</th><th>환자명</th><th>처방번호</th><th>제품명</th>
                <th style="text-align:right;">총 주문금액</th>
                <th style="text-align:right;">NHIS 청구</th>
                <th style="text-align:right;">주문금액</th>
                <th style="text-align:right;">본인부담</th>
                <th style="text-align:right;">배송비</th>
                <th style="text-align:center;">가상계좌</th>
                <th style="text-align:right;">입금확인</th>
                <th>주문상태</th><th>NHIS</th><th>접수일</th>
              </tr>
            </thead>
            <tbody>
              @forelse($orders as $order)
              <tr>
                <td><span class="order-num">{{ $order->order_number }}</span></td>
                <td><b>{{ $order->patient?->name ?? '-' }}</b></td>
                <td>
                  @if($order->prescription)
                    <span class="rx-popup-link" style="font-size:11px;font-family:monospace;color:var(--primary);cursor:pointer;text-decoration:underline dotted;"
                          data-url="{{ route('settlement.prescription-detail', $order->prescription) }}">
                      {{ $order->prescription->rx_number }}
                    </span>
                  @else <span style="color:var(--text-muted);font-size:11px;">-</span> @endif
                </td>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;">
                  @if($order->product_name)
                    <span class="order-popup-link" style="cursor:pointer;color:var(--text-primary);text-decoration:underline dotted;"
                          data-url="{{ route('settlement.order-detail', $order) }}">
                      {{ $order->product_name }}
                    </span>
                  @else <span style="color:var(--text-muted);">-</span> @endif
                </td>
                <td class="amount-cell primary">{{ number_format($order->total_amount ?? 0) }}</td>
                <td class="amount-cell success">{{ number_format($order->nhis_amount ?? 0) }}</td>
                <td class="amount-cell muted">{{ number_format($order->unit_price ?? 0) }}</td>
                <td class="amount-cell {{ ($order->patient_copay??0)>0?'primary':'muted' }}">{{ number_format($order->patient_copay ?? 0) }}</td>
                <td class="amount-cell muted">{{ number_format($order->shipping_fee ?? 0) }}</td>
                <td style="text-align:center;">
                  @if($order->tossPayment)
                    @if($order->tossPayment->is_done)
                      <span class="badge badge-success" style="font-size:10px;">입금완료</span>
                    @elseif($order->tossPayment->is_expired)
                      <span class="badge badge-secondary" style="font-size:10px;">만료</span>
                    @else
                      <span class="badge badge-warning" style="font-size:10px;">대기중</span>
                    @endif
                  @else
                    <span style="font-size:11px;color:var(--text-muted);">미발급</span>
                  @endif
                </td>
                <td class="amount-cell {{ ($order->tossPayment?->is_done) ? 'success' : 'muted' }}">
                  @if($order->tossPayment?->is_done)
                    {{ number_format($order->tossPayment->amount ?? 0) }}
                  @else
                    <span style="font-size:11px;">-</span>
                  @endif
                </td>
                <td>
                  @php $sl = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary']; @endphp
                  <span class="badge badge-{{ $sl['badge'] }}">{{ $sl['label'] }}</span>
                </td>
                <td>
                  @php $nhisMap=['pending'=>['대기','secondary'],'submitted'=>['청구완료','info'],'approved'=>['승인','success'],'rejected'=>['반려','danger']]; [$nl,$nb]=$nhisMap[$order->nhis_claim_status??'pending']??['대기','secondary']; @endphp
                  <span class="badge badge-{{ $nb }}">{{ $nl }}</span>
                </td>
                <td style="font-size:11px;color:var(--text-muted);">{{ $order->created_at->format('Y-m-d') }}</td>
              </tr>
              @empty
              <tr><td colspan="14" style="text-align:center;color:var(--text-muted);padding:32px;">
                <i class="fa-regular fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.35;"></i>
                해당 기간의 정산 데이터가 없습니다.
              </td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if($orders->hasPages())
      <div class="card-footer" style="padding:12px 16px;border-top:1px solid var(--border);">{{ $orders->links() }}</div>
      @endif
    </div>

  @elseif($tab === 'virtual_account')
  {{-- ══════════════ 가상계좌 매칭 (Toss Payments) ══════════════ --}}

    {{-- VA 요약 카드 --}}
    <div class="summary-grid" style="margin-bottom:16px;">
      <div class="sum-card blue">
        <div class="sum-card-label">가상계좌 대상</div>
        <div class="sum-card-val">{{ number_format($vaStats['total']) }}<span style="font-size:13px;font-weight:500;">건</span></div>
        <div class="sum-card-sub">본인부담금 > 0</div>
      </div>
      <div class="sum-card green">
        <div class="sum-card-label"><i class="fa-solid fa-circle-check"></i> 입금 완료</div>
        <div class="sum-card-val">{{ number_format($vaStats['done']) }}<span style="font-size:13px;font-weight:500;">건</span></div>
        <div class="sum-card-sub">DONE 확인</div>
      </div>
      <div class="sum-card orange">
        <div class="sum-card-label"><i class="fa-solid fa-clock"></i> 입금 대기</div>
        <div class="sum-card-val">{{ number_format($vaStats['waiting']) }}<span style="font-size:13px;font-weight:500;">건</span></div>
        <div class="sum-card-sub">발급 후 미입금</div>
      </div>
      <div class="sum-card red">
        <div class="sum-card-label"><i class="fa-solid fa-won-sign"></i> 대기 금액</div>
        <div class="sum-card-val" style="font-size:18px;">{{ number_format($vaStats['pending_amount']) }}<span style="font-size:12px;font-weight:500;">원</span></div>
        <div class="sum-card-sub">미수 본인부담 합계</div>
      </div>
    </div>

    {{-- 검색/필터 --}}
    <form method="GET" action="{{ route('settlement.index') }}" class="filter-bar">
      <input type="hidden" name="tab" value="virtual_account">
      <input type="text" name="va_search" class="form-control" style="width:200px;" placeholder="주문번호·환자명·계좌번호" value="{{ request('va_search') }}">
      <select name="va_status" class="form-control form-select" style="width:130px;">
        <option value="">전체</option>
        <option value="not_issued" {{ request('va_status')==='not_issued' ? 'selected':'' }}>미발급</option>
        <option value="issued"     {{ request('va_status')==='issued'     ? 'selected':'' }}>발급완료</option>
        <option value="waiting"    {{ request('va_status')==='waiting'    ? 'selected':'' }}>입금대기</option>
        <option value="done"       {{ request('va_status')==='done'       ? 'selected':'' }}>입금완료</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> 검색</button>
      <a href="{{ route('settlement.index', ['tab'=>'virtual_account']) }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-rotate-left"></i></a>
      <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">총 {{ number_format($vaOrders->total()) }}건</span>
    </form>

    {{-- 가상계좌 목록 --}}
    <div class="card">
      <div class="card-header">
        <i class="fa-solid fa-building-columns" style="color:var(--primary);"></i>
        <span class="card-header-title">가상계좌 목록</span>
        <span class="card-header-sub">토스페이먼츠 연동</span>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>주문번호</th>
                <th>환자명</th>
                <th>연락처</th>
                <th style="text-align:right;">본인부담금</th>
                <th>주문상태</th>
                <th>가상계좌 발급</th>
                <th>입금 상태</th>
                <th>만료일시</th>
                <th>입금확인일</th>
                <th>액션</th>
              </tr>
            </thead>
            <tbody>
              @forelse($vaOrders as $order)
              @php $tp = $order->tossPayment; @endphp
              <tr id="va-row-{{ $order->id }}">
                <td><span class="order-num">{{ $order->order_number }}</span></td>
                <td><b>{{ $order->patient?->name ?? '-' }}</b></td>
                <td style="font-size:11px;color:var(--text-muted);">{{ $order->patient?->mobile ?? '-' }}</td>
                <td class="amount-cell primary">{{ number_format($order->patient_copay) }}원</td>
                <td>
                  @php $sl = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary']; @endphp
                  <span class="badge badge-{{ $sl['badge'] }}">{{ $sl['label'] }}</span>
                </td>
                <td>
                  @if($tp)
                    <div style="font-size:11px;">
                      <b>{{ $tp->bank_name }}</b> {{ $tp->account_number }}
                    </div>
                  @else
                    <span style="font-size:11px;color:var(--text-muted);">미발급</span>
                  @endif
                </td>
                <td id="va-status-{{ $order->id }}">
                  @if(!$tp)
                    <span class="va-badge none"><i class="fa-solid fa-minus"></i> 미발급</span>
                  @elseif($tp->is_done)
                    <span class="va-badge done"><i class="fa-solid fa-circle-check"></i> 입금완료</span>
                  @elseif($tp->is_expired)
                    <span class="va-badge expired"><i class="fa-solid fa-clock"></i> 만료</span>
                  @elseif($tp->status === 'WAITING_FOR_DEPOSIT')
                    <span class="va-badge waiting"><i class="fa-solid fa-hourglass-half"></i> 입금대기</span>
                  @else
                    <span class="va-badge ready"><i class="fa-solid fa-circle-dot"></i> {{ $tp->status_label }}</span>
                  @endif
                </td>
                <td style="font-size:11px;color:var(--text-muted);">
                  {{ $tp?->due_date?->format('Y-m-d H:i') ?? '-' }}
                </td>
                <td style="font-size:11px;color:var(--success);">
                  {{ $tp?->deposited_at?->format('Y-m-d H:i') ?? '-' }}
                </td>
                <td>
                  <div style="display:flex;gap:4px;">
                    @if(!$tp && $tossConfigured)
                      <button class="btn btn-primary btn-sm btn-issue-va"
                              onclick="issueVA({{ $order->id }}, this)"
                              data-url="{{ route('settlement.issue-va', $order) }}">
                        <i class="fa-solid fa-plus"></i> 발급
                      </button>
                    @elseif($tp && !$tp->is_done && $tossConfigured)
                      <button class="btn btn-outline btn-sm btn-issue-va"
                              onclick="checkStatus({{ $order->id }}, this)"
                              data-url="{{ route('settlement.check-status', $order) }}">
                        <i class="fa-solid fa-rotate"></i> 확인
                      </button>
                    @elseif(!$tossConfigured)
                      <span style="font-size:11px;color:var(--text-muted);">API 미설정</span>
                    @endif
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px;">
                  <i class="fa-solid fa-building-columns" style="font-size:24px;display:block;margin-bottom:8px;opacity:.35;"></i>
                  가상계좌 대상 주문이 없습니다.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if($vaOrders->hasPages())
      <div class="card-footer" style="padding:12px 16px;border-top:1px solid var(--border);">
        {{ $vaOrders->links() }}
      </div>
      @endif
    </div>

  @endif

@endsection

{{-- ══ 처방전 상세 팝업 ══ --}}
<div id="rxModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border-radius:var(--radius-lg);box-shadow:0 20px 60px rgba(0,0,0,.25);width:680px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;">
      <i class="fa-solid fa-file-medical" style="color:var(--primary);font-size:16px;"></i>
      <span style="font-size:15px;font-weight:700;" id="rxModalTitle">처방전 상세</span>
      <button onclick="closeModal('rxModal')" style="margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted);line-height:1;">×</button>
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
      <span style="font-size:15px;font-weight:700;" id="orderModalTitle">주문 상세</span>
      <button onclick="closeModal('orderModal')" style="margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted);line-height:1;">×</button>
    </div>
    <div style="overflow-y:auto;padding:20px;" id="orderModalBody">
      <div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> 불러오는 중...</div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:8px;">
      <button onclick="closeModal('orderModal')" class="btn btn-outline btn-sm">닫기</button>
    </div>
  </div>
</div>

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

      const badgeColor = { success:'#16a34a', danger:'#dc2626', warning:'#d97706', info:'#0077b6', secondary:'#64748b' };
      const badge = (label, type) => `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:${badgeColor[type]||'#64748b'}20;color:${badgeColor[type]||'#64748b'};">${label}</span>`;

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
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">보험금액</th>
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
            ${row('OCR 신뢰도', d.ocr_confidence != null ? d.ocr_confidence + '%' : '-')}
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

      const badgeColor = { success:'#16a34a', danger:'#dc2626', warning:'#d97706', info:'#0077b6', secondary:'#64748b', primary:'#0077b6' };
      const badge = (label, type) => `<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:${badgeColor[type]||'#64748b'}20;color:${badgeColor[type]||'#64748b'};">${label}</span>`;
      const row   = (label, value) => `<div style="display:flex;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:12px;">
        <span style="width:110px;flex-shrink:0;color:var(--text-muted);">${label}</span>
        <span style="flex:1;font-weight:500;">${value ?? '-'}</span></div>`;
      const amtRow = (label, val, color='var(--text-primary)') => `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);font-size:12px;">
        <span style="color:var(--text-muted);">${label}</span>
        <span style="font-family:monospace;font-weight:600;color:${color};">${fmt(val)}원</span></div>`;

      const itemsHtml = d.items?.length ? `
        <table style="width:100%;border-collapse:collapse;font-size:11px;margin-top:4px;">
          <thead><tr style="background:var(--bg);">
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">제품명</th>
            <th style="padding:6px 8px;text-align:left;border:1px solid var(--border);">코드</th>
            <th style="padding:6px 8px;text-align:center;border:1px solid var(--border);">수량</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">단가</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">NHIS청구</th>
            <th style="padding:6px 8px;text-align:right;border:1px solid var(--border);">본인부담</th>
          </tr></thead>
          <tbody>${d.items.map(i => `<tr>
            <td style="padding:5px 8px;border:1px solid var(--border);">${i.product_name}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);font-family:monospace;">${i.product_code}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:center;">${i.quantity}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;">${fmt(i.unit_price)}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--success);">${fmt(i.nhis_amount)}</td>
            <td style="padding:5px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--primary);">${fmt(i.patient_copay)}</td>
          </tr>`).join('')}
          <tr style="background:var(--bg);font-weight:700;">
            <td colspan="4" style="padding:6px 8px;border:1px solid var(--border);text-align:right;">합계</td>
            <td style="padding:6px 8px;border:1px solid var(--border);text-align:right;font-family:monospace;color:var(--success);">${fmt(d.items.reduce((s,i)=>s+(i.nhis_amount||0),0))}</td>
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
            ${row('입금확인', d.toss_payment.deposited_at ? `<span style="color:var(--success);">${d.toss_payment.deposited_at}</span>` : '-')}
          </div>
        </div>` : '';

      document.getElementById('orderModalBody').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;">주문 정보</div>
            ${row('주문번호', `<span style="font-family:monospace;color:var(--primary);">${d.order_number}</span>`)}
            ${row('주문상태', badge(d.status_label, d.status_badge))}
            ${row('NHIS 청구', d.nhis_status)}
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
            ${amtRow('NHIS 청구액', d.nhis_amount,    'var(--success)')}
            ${amtRow('환자 본인부담', d.patient_copay, 'var(--primary)')}
            ${amtRow('배송비',      d.shipping_fee)}
            ${amtRow('NHIS 환급',   d.nhis_reimb,    'var(--success)')}
            <div style="display:flex;justify-content:space-between;padding:8px 0 2px;font-size:13px;font-weight:800;">
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
    if (!confirm('가상계좌를 발급하시겠습니까?')) return;
    BtnState.loading(btn, '발급 중...');
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        BtnState.success(btn, '발급 완료');
        showToast(`✅ ${data.bank_name} ${data.account_number} 발급 완료`, 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(data.message || '발급 실패', 'danger');
        BtnState.error(btn, '발급 실패');
      }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
      BtnState.error(btn, '오류');
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
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar', title: '정산 조회 필터', body: '기간과 상태로 정산 대상 주문을 조회합니다. 엑셀 다운로드도 이 화면에서 가능합니다.' },
  { selector: '.card-header', title: '정산 목록', body: '주문 기준 정산 현황입니다. 건강보험 환급금, 본인부담금, 세금계산서 발행 여부를 확인합니다.' },
  { selector: '[id*="va"], .card:nth-of-type(2)', title: '가상계좌 목록', body: '토스페이먼츠로 발급된 가상계좌 현황입니다. 미입금 건을 확인하고 독촉 알림을 보낼 수 있습니다.' },
];
</script>
@endpush
