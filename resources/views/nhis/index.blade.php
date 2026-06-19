{{-- resources/views/nhis/index.blade.php --}}
@extends('layouts.app')

@section('title', 'NHIS 청구 관리')
@section('page-title', 'NHIS 청구 관리')
@section('breadcrumb', '홈 / NHIS 청구')

@section('help-title', 'NHIS 청구 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>건강보험심사평가원(NHIS) 청구 현황을 관리하고 팩스 전송을 처리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">청구 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">청구 대기</span>
    <span class="badge badge-info">팩스 전송됨</span>
    <span class="badge badge-success">승인 완료</span>
    <span class="badge badge-danger">반려</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">처리 순서</div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">1</div>
    <div class="help-item-text">청구 대상 주문을 선택</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">2</div>
    <div class="help-item-text">처방전 팩스 전송 (e-Fax)</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);min-width:30px;font-weight:700;">3</div>
    <div class="help-item-text">심사 결과 수신 후 환급금 입력</div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
  @media(max-width:900px){ .summary-grid { grid-template-columns: repeat(2,1fr); } }

  .summary-card {
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 18px 20px; display: flex; align-items: center; gap: 16px;
    box-shadow: var(--shadow);
  }
  .summary-card .sc-icon {
    width: 48px; height: 48px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
  }
  .summary-card .s-label { font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 3px; }
  .summary-card .s-value { font-size: 24px; font-weight: 800; line-height: 1; }
  .summary-card .s-sub   { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  .summary-card.blue  .sc-icon { background: var(--primary-light); color: var(--primary); }
  .summary-card.green .sc-icon { background: var(--success-light); color: var(--success); }
  .summary-card.red   .sc-icon { background: var(--danger-light);  color: var(--danger); }
  .summary-card.gray  .sc-icon { background: var(--border-light);  color: var(--text-muted); }
  .summary-card.blue  .s-value { color: var(--primary); }
  .summary-card.green .s-value { color: var(--success); }
  .summary-card.red   .s-value { color: var(--danger); }

  /* Vuexy pill tabs */
  .nhis-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .nhis-tab  {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .nhis-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .nhis-tab.active { border-color: var(--primary); background: var(--primary); color: #fff; }

  .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }

  .order-number { font-size: 12px; font-weight: 700; color: var(--primary); }
  .amount-cell  { text-align: right; font-variant-numeric: tabular-nums; }

  .check-col { width: 36px; text-align: center; }
  .bulk-bar  {
    display: none; align-items: center; gap: 10px;
    padding: 10px 16px; background: var(--primary-light);
    border: 1px solid var(--primary-accent); border-radius: var(--radius);
    margin-bottom: 12px;
  }
  .bulk-bar.show { display: flex; }

  /* 결과 등록 모달 */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); z-index: 1000;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal-box {
    background: #fff; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); width: 480px; max-width: 95vw; max-height: 90vh;
    overflow-y: auto;
  }
  .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
  }
  .modal-title { font-size: 14px; font-weight: 700; }
  .modal-body  { padding: 18px; }
  .modal-footer { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
  .btn-close-modal { background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text-muted); }

  /* 팩스 로그 패널 */
  .log-panel {
    display: none; background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 12px; margin-top: 8px;
  }
  .log-panel.open { display: block; }
  .log-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 0; border-bottom: 1px solid var(--border-light); font-size: 12px;
  }
  .log-item:last-child { border-bottom: none; }

  /* 청구서 미리보기 모달 */
  .preview-content {
    white-space: pre; font-family: 'Courier New', monospace;
    font-size: 12px; line-height: 1.6;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 16px;
    max-height: 60vh; overflow-y: auto;
  }

  .efax-status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 20px;
  }
  .efax-sent     { background: var(--success-light); color: var(--success); }
  .efax-failed   { background: var(--danger-light);  color: var(--danger); }
  .efax-queued   { background: var(--border-light);  color: var(--text-secondary); }
  .efax-sending  { background: var(--warning-light); color: var(--warning); }
</style>
@endpush

@section('header-actions')
<button class="btn btn-primary btn-sm" onclick="openBulkSend()">
  <i class="fa-solid fa-paper-plane"></i> 일괄 청구 송신
</button>
@endsection

@php
  $nhisStatusLabels = [
    'pending'   => ['미청구',    'secondary'],
    'submitted' => ['청구완료',  'primary'],
    'approved'  => ['승인',      'success'],
    'rejected'  => ['거부',      'danger'],
  ];
  $curNhisStatus = request('nhis_status');
@endphp

@section('content')

{{-- DB 마이그레이션 미실행 안내 --}}
@if(!$faxTableExists)
<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;">
  <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
  <div>
    <strong>DB 마이그레이션 필요</strong> — e-Fax 기능을 사용하려면 phpMyAdmin에서
    <code>database/migrations/nhis_fax_logs_SQL.txt</code> 내용을 실행해주세요.
    (현재는 기본 청구 상태만 표시됩니다.)
  </div>
</div>
@endif

{{-- ── 요약 카드 ── --}}
<div class="summary-grid">
  <div class="summary-card gray">
    <div class="s-label">미청구 건수</div>
    <div class="s-value" style="color:var(--text-secondary);">{{ $counts['pending'] ?? 0 }}</div>
    <div class="s-sub">청구 대기 중인 주문</div>
  </div>
  <div class="summary-card blue">
    <div class="s-label">청구 완료</div>
    <div class="s-value" style="color:var(--primary);">{{ $counts['submitted'] ?? 0 }}</div>
    <div class="s-sub">결과 대기 중</div>
  </div>
  <div class="summary-card green">
    <div class="s-label">승인</div>
    <div class="s-value" style="color:var(--success);">{{ $counts['approved'] ?? 0 }}</div>
    <div class="s-sub">이번달 {{ number_format($monthlyApproved) }}원 환급</div>
  </div>
  <div class="summary-card red">
    <div class="s-label">거부</div>
    <div class="s-value" style="color:var(--danger);">{{ $counts['rejected'] ?? 0 }}</div>
    <div class="s-sub">재청구 필요</div>
  </div>
</div>

{{-- ── NHIS 상태 탭 ── --}}
<div class="nhis-tabs">
  <a href="{{ route('nhis.index', array_merge(request()->except('nhis_status','page'), [])) }}"
     class="nhis-tab {{ !$curNhisStatus ? 'active' : '' }}">
    전체 <span>{{ $counts->sum() }}</span>
  </a>
  @foreach($nhisStatusLabels as $key => $statusInfo)
    <a href="{{ route('nhis.index', array_merge(request()->except('nhis_status','page'), ['nhis_status' => $key])) }}"
       class="nhis-tab {{ $curNhisStatus === $key ? 'active' : '' }}">
      {{ $statusInfo[0] }}
      @if(($counts[$key] ?? 0) > 0)
        <span>({{ $counts[$key] }})</span>
      @endif
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('nhis.index') }}" class="filter-bar">
  @if($curNhisStatus)<input type="hidden" name="nhis_status" value="{{ $curNhisStatus }}">@endif
  <input type="text" name="q" value="{{ request('q') }}" class="form-control"
         placeholder="주문번호 · 환자명" style="width:200px;">
  <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:140px;" title="배송완료 시작일">
  <span style="color:var(--text-muted);font-size:12px;">~</span>
  <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="form-control" style="width:140px;" title="배송완료 종료일">
  <button type="submit" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-magnifying-glass"></i> 검색
  </button>
  @if(request('q') || request('date_from') || request('date_to'))
    <a href="{{ route('nhis.index', $curNhisStatus ? ['nhis_status'=>$curNhisStatus] : []) }}"
       class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- ── 일괄 선택 바 ── --}}
<div class="bulk-bar" id="bulkBar">
  <i class="fa-solid fa-check-square" style="color:var(--primary);"></i>
  <span id="bulkCount" style="font-size:13px;font-weight:700;"></span>건 선택됨
  <button class="btn btn-primary btn-sm" onclick="sendBulkSelected()">
    <i class="fa-solid fa-paper-plane"></i> 선택 일괄 청구
  </button>
  <button class="btn btn-outline btn-sm" onclick="clearSelection()">선택 해제</button>
</div>

{{-- ── 주문 테이블 ── --}}
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-hospital" style="color:var(--purple);"></i>
    <span class="card-header-title">NHIS 청구 목록</span>
    <span class="card-header-sub">전체 {{ $orders->total() }}건</span>
    <div style="margin-left:auto;font-size:12px;color:var(--text-muted);">
      이번달 청구액:
      <strong style="color:var(--primary);">{{ number_format($monthlyTotal) }}원</strong>
    </div>
  </div>
  <div class="table-scroll-wrap">
    <table>
      <thead>
        <tr>
          <th class="check-col">
            <input type="checkbox" id="checkAll" onchange="toggleAll(this)" title="전체선택">
          </th>
          <th>주문번호</th>
          <th>환자명</th>
          <th>제품명</th>
          <th class="amount-cell">건보청구액</th>
          <th class="amount-cell">환자부담</th>
          <th>주문상태</th>
          <th>NHIS상태</th>
          <th>e-Fax 상태</th>
          <th>청구일시</th>
          <th>승인/거부</th>
          <th>액션</th>
        </tr>
      </thead>
      <tbody id="orderTableBody">
        @forelse($orders as $order)
          @php
            $statusMeta   = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary'];
            $nhisInfo     = $nhisStatusLabels[$order->nhis_claim_status] ?? [$order->nhis_claim_status,'secondary'];
            $nhisLbl      = $nhisInfo[0];
            $nhisBadge    = $nhisInfo[1];
            $faxLog       = $faxTableExists ? $order->latestFaxLog : null;
            $faxStatusMap = \App\Models\NhisFaxLog::STATUS_LABELS;
            $faxMeta      = $faxLog ? ($faxStatusMap[$faxLog->status] ?? ['label'=>$faxLog->status,'badge'=>'secondary']) : null;
            $rejReason    = $faxTableExists ? ($order->nhis_rejection_reason ?? null) : null;
          @endphp
          <tr id="row-{{ $order->id }}">
            <td class="check-col">
              @if($order->nhis_claim_status === 'pending')
                <input type="checkbox" class="order-check" value="{{ $order->id }}" onchange="updateBulkBar()">
              @endif
            </td>
            <td>
              <a href="{{ route('orders.show', $order) }}" class="order-number">
                {{ $order->order_number }}
              </a>
            </td>
            <td style="font-weight:600;">{{ $order->patient?->name ?? '-' }}</td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;"
                title="{{ $order->product_name }}">
              {{ $order->product_name ?? '-' }}
            </td>
            <td class="amount-cell">{{ number_format($order->nhis_amount) }}원</td>
            <td class="amount-cell">{{ number_format($order->patient_copay) }}원</td>
            <td>
              <span class="badge badge-{{ $statusMeta['badge'] }}">{{ $statusMeta['label'] }}</span>
            </td>
            <td>
              <span class="badge badge-{{ $nhisBadge }}">{{ $nhisLbl }}</span>
              @if($order->nhis_claim_status === 'rejected' && $rejReason)
                <div style="font-size:10px;color:var(--danger);margin-top:2px;"
                     title="{{ $rejReason }}">
                  {{ mb_substr($rejReason, 0, 20) }}…
                </div>
              @endif
            </td>
            <td>
              @if($faxLog)
                <span class="efax-status-badge efax-{{ $faxLog->status }}">
                  <i class="fa-solid {{ $faxLog->status === 'sent' ? 'fa-check' : ($faxLog->status === 'failed' ? 'fa-xmark' : 'fa-clock') }}"></i>
                  {{ $faxMeta['label'] ?? $faxLog->status }}
                </span>
                @if($faxLog->reference_no)
                  <div style="font-size:10px;color:var(--text-muted);">{{ $faxLog->reference_no }}</div>
                @endif
              @else
                <span style="font-size:11px;color:var(--text-muted);">-</span>
              @endif
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              {{ $order->nhis_submitted_at?->format('m/d H:i') ?? '-' }}
            </td>
            <td>
              @if($order->nhis_claim_status === 'approved')
                <div style="font-size:12px;color:var(--success);font-weight:700;">
                  <i class="fa-solid fa-circle-check"></i>
                  {{ number_format($order->nhis_reimbursement) }}원
                </div>
              @elseif($order->nhis_claim_status === 'rejected')
                <div style="font-size:12px;color:var(--danger);font-weight:700;">
                  <i class="fa-solid fa-circle-xmark"></i> 거부
                </div>
              @else
                -
              @endif
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                {{-- 청구 버튼 --}}
                @if(in_array($order->nhis_claim_status, ['pending','rejected']))
                  <button class="btn btn-primary btn-sm"
                          onclick="sendFax({{ $order->id }}, '{{ $order->order_number }}')"
                          title="e-Fax 청구 송신">
                    <i class="fa-solid fa-fax"></i> 청구
                  </button>
                @endif
                {{-- 결과등록 --}}
                @if($order->nhis_claim_status === 'submitted')
                  <button class="btn btn-outline btn-sm"
                          onclick="openResultModal({{ $order->id }}, '{{ $order->order_number }}', {{ $order->nhis_amount }})"
                          title="공단 처리 결과 등록">
                    <i class="fa-solid fa-clipboard-check"></i> 결과
                  </button>
                @endif
                {{-- 청구서 미리보기 --}}
                <button class="btn btn-outline btn-sm"
                        onclick="previewDoc({{ $order->id }}, '{{ $order->order_number }}')"
                        title="청구서 미리보기">
                  <i class="fa-solid fa-eye"></i>
                </button>
                {{-- 팩스 이력 --}}
                @if($order->nhis_claim_status !== 'pending')
                  <button class="btn btn-outline btn-sm"
                          onclick="toggleFaxLog({{ $order->id }})"
                          title="팩스 발송 이력">
                    <i class="fa-solid fa-list"></i>
                  </button>
                @endif
              </div>
              {{-- 팩스 로그 패널 --}}
              <div class="log-panel" id="logPanel-{{ $order->id }}"></div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="12" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
              청구 대상 주문이 없습니다.
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
</div>

{{-- ══════════ 결과 등록 모달 ══════════ --}}
<div class="modal-overlay" id="resultModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-clipboard-check" style="color:var(--success);"></i> NHIS 처리 결과 등록</div>
      <button class="btn-close-modal" onclick="closeResultModal()">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="resultOrderId">
      <div style="background:var(--primary-light);border:1px solid var(--primary-accent);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);">청구 주문</div>
        <div id="resultOrderInfo" style="font-size:14px;font-weight:700;margin-top:2px;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">처리 결과 <span>*</span></label>
        <select id="resultType" class="form-control form-select" onchange="onResultTypeChange()">
          <option value="">선택하세요</option>
          <option value="approved">승인</option>
          <option value="partial">부분 승인</option>
          <option value="rejected">거부</option>
        </select>
      </div>
      <div class="form-group" id="approvedAmountGroup" style="display:none;">
        <label class="form-label">승인 금액 (원)</label>
        <input type="number" id="approvedAmount" class="form-control" placeholder="0" min="0">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
          청구 건보액: <span id="claimAmountRef" style="font-weight:700;"></span>원
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">공단 메시지 / 거부 사유</label>
        <textarea id="nhisMessage" class="form-control" rows="3" placeholder="공단 처리 메시지 또는 거부 사유 입력"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeResultModal()">취소</button>
      <button class="btn btn-primary" onclick="submitResult()">
        <i class="fa-solid fa-save"></i> 결과 등록
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 청구서 미리보기 모달 ══════════ --}}
<div class="modal-overlay" id="previewModal">
  <div class="modal-box" style="width:600px;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-file-lines" style="color:var(--primary);"></i> 청구서 미리보기</div>
      <button class="btn-close-modal" onclick="document.getElementById('previewModal').classList.remove('open')">&times;</button>
    </div>
    <div class="modal-body">
      <div id="previewOrderLabel" style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:10px;"></div>
      <div class="preview-content" id="previewContent">로딩 중...</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="document.getElementById('previewModal').classList.remove('open')">닫기</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ── 선택 / 일괄 처리 ─────────────────────────────────────
function getCheckedIds() {
  return [...document.querySelectorAll('.order-check:checked')].map(c => parseInt(c.value));
}

function updateBulkBar() {
  const ids   = getCheckedIds();
  const bar   = document.getElementById('bulkBar');
  const count = document.getElementById('bulkCount');
  count.textContent = ids.length;
  bar.classList.toggle('show', ids.length > 0);

  // 전체선택 체크박스 상태
  const all = document.querySelectorAll('.order-check');
  document.getElementById('checkAll').indeterminate = ids.length > 0 && ids.length < all.length;
  document.getElementById('checkAll').checked = ids.length === all.length && all.length > 0;
}

function toggleAll(cb) {
  document.querySelectorAll('.order-check').forEach(c => c.checked = cb.checked);
  updateBulkBar();
}

function clearSelection() {
  document.querySelectorAll('.order-check').forEach(c => c.checked = false);
  document.getElementById('checkAll').checked = false;
  updateBulkBar();
}

function openBulkSend() {
  const pending = document.querySelectorAll('.order-check');
  if (pending.length === 0) { showToast('청구 대상 주문이 없습니다.', 'warning'); return; }
  // 전체 미청구 선택
  pending.forEach(c => c.checked = true);
  updateBulkBar();
  showToast(`${pending.length}건이 선택되었습니다. 일괄 청구 버튼을 클릭하세요.`, 'info', 3000);
}

async function sendBulkSelected() {
  const ids = getCheckedIds();
  if (ids.length === 0) { showToast('선택된 주문이 없습니다.', 'warning'); return; }
  if (!confirm(`${ids.length}건을 NHIS e-Fax로 일괄 청구하시겠습니까?`)) return;

  const btn = event.currentTarget;
  BtnState.loading(btn, '전송 중...');

  const res = await apiRequest(BASE_URL + '/nhis/bulk-send', 'POST', { order_ids: ids });
  if (res.success || res.success_count > 0) {
    BtnState.success(btn, '전송 완료');
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 1200);
  } else {
    BtnState.error(btn, '전송 실패');
  }
  if (res.failed?.length > 0) {
    res.failed.forEach(f => showToast(`${f.order_number}: ${f.error}`, 'danger', 7000));
  }
}

// ── 단건 e-Fax 청구 ─────────────────────────────────────
async function sendFax(orderId, orderNumber) {
  if (!confirm(`${orderNumber} 주문을 NHIS e-Fax로 청구하시겠습니까?`)) return;

  const btn = event.currentTarget;
  BtnState.loading(btn, '청구 중...');

  const res = await apiRequest(`${BASE_URL}/nhis/${orderId}/send-fax`, 'POST');

  if (res.success) {
    BtnState.success(btn, '전송 완료');
    showToast(`청구 전송 완료 (참조번호: ${res.reference_no})`, 'success', 5000);
    setTimeout(() => location.reload(), 1500);
  } else {
    BtnState.error(btn, '전송 실패');
    if (res.message) showToast(res.message, 'danger');
  }
}

// ── 결과 등록 모달 ────────────────────────────────────────
let _resultOrderId = null;

function openResultModal(orderId, orderNumber, nhisAmount) {
  _resultOrderId = orderId;
  document.getElementById('resultOrderId').value   = orderId;
  document.getElementById('resultOrderInfo').textContent = orderNumber;
  document.getElementById('claimAmountRef').textContent  = nhisAmount.toLocaleString('ko-KR');
  document.getElementById('resultType').value    = '';
  document.getElementById('approvedAmount').value = '';
  document.getElementById('nhisMessage').value   = '';
  document.getElementById('approvedAmountGroup').style.display = 'none';
  document.getElementById('resultModal').classList.add('open');
}

function closeResultModal() {
  document.getElementById('resultModal').classList.remove('open');
  _resultOrderId = null;
}

function onResultTypeChange() {
  const val = document.getElementById('resultType').value;
  document.getElementById('approvedAmountGroup').style.display =
    (val === 'approved' || val === 'partial') ? 'block' : 'none';
}

async function submitResult() {
  if (!_resultOrderId) return;
  const resultType = document.getElementById('resultType').value;
  if (!resultType) { showToast('처리 결과를 선택해주세요.', 'warning'); return; }

  const data = {
    nhis_result:     resultType,
    approved_amount: document.getElementById('approvedAmount').value || null,
    nhis_message:    document.getElementById('nhisMessage').value || null,
  };

  const res = await apiRequest(`${BASE_URL}/nhis/${_resultOrderId}/record-result`, 'POST', data);
  if (res.success) {
    showToast('결과가 등록되었습니다.', 'success');
    closeResultModal();
    setTimeout(() => location.reload(), 800);
  }
}

// ── 청구서 미리보기 ───────────────────────────────────────
async function previewDoc(orderId, orderNumber) {
  document.getElementById('previewOrderLabel').textContent = `주문번호: ${orderNumber}`;
  document.getElementById('previewContent').textContent   = '로딩 중...';
  document.getElementById('previewModal').classList.add('open');

  try {
    const res = await fetch(`${BASE_URL}/nhis/${orderId}/preview`, {
      headers: { 'Accept': 'text/plain', 'X-CSRF-TOKEN': CSRF_TOKEN },
    });
    const text = await res.text();
    document.getElementById('previewContent').textContent = text;
  } catch(e) {
    document.getElementById('previewContent').textContent = '미리보기 로딩 실패';
  }
}

// ── 팩스 이력 토글 ────────────────────────────────────────
const _loadedLogs = new Set();

async function toggleFaxLog(orderId) {
  const panel = document.getElementById(`logPanel-${orderId}`);
  const isOpen = panel.classList.contains('open');

  if (isOpen) { panel.classList.remove('open'); return; }

  if (!_loadedLogs.has(orderId)) {
    panel.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px;">로딩 중...</div>';
    panel.classList.add('open');
    const res = await apiRequest(`${BASE_URL}/nhis/${orderId}/fax-logs`, 'GET');
    if (res.success && res.logs.length > 0) {
      const nhisResultBadgeMap = {
        pending:  ['결과대기', 'secondary'],
        approved: ['승인', 'success'],
        rejected: ['거부', 'danger'],
        partial:  ['부분승인', 'warning'],
      };
      panel.innerHTML = res.logs.map(log => {
        const [rLbl, rBadge] = nhisResultBadgeMap[log.nhis_result] ?? [log.nhis_result, 'secondary'];
        return `<div class="log-item">
          <span class="efax-status-badge efax-${log.status}">${log.status_label}</span>
          <span style="color:var(--text-muted);">${log.sent_at ?? '-'}</span>
          <span style="font-size:11px;">${log.reference_no ?? ''}</span>
          <span class="badge badge-${rBadge}">${rLbl}</span>
          ${log.nhis_result_at ? `<span style="font-size:11px;color:var(--text-muted);">${log.nhis_result_at}</span>` : ''}
          ${log.approved_amount ? `<span style="font-weight:700;color:var(--success);">${Number(log.approved_amount).toLocaleString('ko-KR')}원</span>` : ''}
          ${log.error_message ? `<span style="font-size:11px;color:var(--danger);">${log.error_message}</span>` : ''}
        </div>`;
      }).join('');
      _loadedLogs.add(orderId);
    } else {
      panel.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px;">팩스 발송 이력이 없습니다.</div>';
    }
  } else {
    panel.classList.add('open');
  }
}

// 모달 외부 클릭 시 닫기
['resultModal','previewModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar, form.filter-bar', title: '청구 검색 필터', body: '기간, 상태, 환자명으로 NHIS 청구 대상을 조회합니다.' },
  { selector: 'table, .card', title: '청구 목록', body: '주문별 청구 현황을 보여줍니다. 상태가 <b>청구 대기</b>인 항목을 팩스로 청구하세요.' },
  { selector: '.btn-primary, [onclick*="Bulk"], [onclick*="bulk"]', title: '일괄 청구 송신', body: '여러 건을 한 번에 e-Fax로 전송합니다. 전송 후 상태가 <b>팩스 전송됨</b>으로 변경됩니다.' },
];
</script>
@endpush
