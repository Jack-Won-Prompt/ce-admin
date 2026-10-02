{{-- resources/views/duplicate-payments/index.blade.php --}}
@extends('layouts.app')

@section('title', '중복 결제')
@section('page-title', '중복 결제')
@section('breadcrumb', '홈 - 중복 결제')

@push('styles')
<style>
  /* 한 주문이 한 칸이고, 그 안에 결제가 줄로 선다 — 어느 쪽을 돌려줄지 사람이 고른다 */
  .dp-card { border:1px solid var(--border-color); border-radius:10px; margin-bottom:12px;
             background:var(--card-bg); overflow:hidden; }
  .dp-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;
             padding:12px 14px; background:var(--gray-50); border-bottom:1px solid var(--border-color); }
  .dp-who  { font-weight:700; font-size:14px; }
  .dp-sub  { font-size:12px; color:var(--text-muted); }
  .dp-sums { display:flex; gap:16px; flex-wrap:wrap; font-size:13px; }
  .dp-sums b { font-variant-numeric:tabular-nums; }
  .dp-excess { color:var(--danger); font-weight:700; }

  .dp-pay  { display:grid; grid-template-columns: 1fr 130px 110px 150px 160px; gap:10px;
             align-items:center; padding:10px 14px; border-top:1px solid var(--border-color);
             font-size:13px; }
  .dp-pay:first-of-type { border-top:0; }
  .dp-key  { font-family:ui-monospace, Menlo, Consolas, monospace; font-size:12px; word-break:break-all; }
  .dp-amt  { text-align:right; font-variant-numeric:tabular-nums; font-weight:600; }
  .dp-note { font-size:12px; color:var(--text-muted); }
  .dp-ledger { font-size:12px; color:var(--text-muted); }

  .dp-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; }
  .dp-badge.is-ledger  { background:var(--gray-100); color:var(--text-muted); }
  .dp-badge.is-extra   { background:#fdecea; color:#b3261e; }
  .dp-badge.is-waiting { background:#fff4e5; color:#8a5300; }
  .dp-badge.is-done    { background:#e7f5ec; color:#1b6b38; }

  .dp-empty { padding:28px; text-align:center; color:var(--text-muted); font-size:13px; }
  .dp-sec-title { font-size:14px; font-weight:700; margin:22px 0 10px; }
  .dp-wait-table { width:100%; border-collapse:collapse; font-size:13px; }
  .dp-wait-table th, .dp-wait-table td { padding:9px 10px; border-bottom:1px solid var(--border-color); text-align:left; }
  .dp-wait-table th { font-size:12px; color:var(--text-muted); font-weight:600; background:var(--gray-50); }
  .dp-wait-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

  @media (max-width: 900px) {
    .dp-pay { grid-template-columns: 1fr; gap:4px; }
    .dp-amt { text-align:left; }
  }
</style>
@endpush

@section('content')

{{-- 기간을 고르고 눌러야 토스에 간다. 화면을 열 때마다 저쪽을 부르면
     하루에도 수십 번 묻게 되고, 그만큼 기다린다. --}}
<div class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">결제 기간</label>
      <div class="ds-field-range">
        <input type="date" id="dpFrom" value="{{ $from }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" id="dpTo" value="{{ $to }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">&nbsp;</label>
      <button type="button" class="btn btn-primary" id="dpScanBtn">조회</button>
    </div>
  </div>
  <div class="dp-sub" style="padding:0 14px 12px;">
    토스에 직접 물어 한 주문에 두 번 이상 들어온 결제를 찾습니다. 우리 장부에는
    뒤 결제가 앞 결제를 덮어 남지 않으므로, 이 화면이 보는 것은 토스의 기록입니다.
    한 번에 31일까지 볼 수 있습니다.
  </div>
</div>

<div id="dpResult"></div>

@if($pending->isNotEmpty())
<div class="dp-sec-title">승인 대기 {{ $pending->count() }}건</div>
<div class="dp-card">
  <table class="dp-wait-table">
    <thead>
      <tr>
        <th>주문번호</th><th>고객</th><th class="num">환불 금액</th>
        <th>결제키</th><th>올린 사람</th><th style="width:170px;">처리</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pending as $p)
      <tr data-refund="{{ $p->id }}">
        <td>{{ $p->order?->order_number ?? '-' }}</td>
        <td>{{ $p->order?->patient?->name ?? '-' }}</td>
        <td class="num">{{ number_format((int) $p->amount) }}원</td>
        <td class="dp-key">{{ $p->payment_key }}</td>
        <td>{{ $p->requestedBy?->name ?? '-' }}<div class="dp-sub">{{ $p->requested_at?->format('m-d H:i') }}</div></td>
        <td>
          @if($canApprove)
            <button type="button" class="btn btn-sm btn-primary dp-approve">승인·환불</button>
            <button type="button" class="btn btn-sm btn-outline-secondary dp-reject">반려</button>
          @else
            <span class="dp-note">최종승인자만 처리할 수 있습니다</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@if($recent->isNotEmpty())
<div class="dp-sec-title">최근 처리</div>
<div class="dp-card">
  <table class="dp-wait-table">
    <thead>
      <tr>
        <th>주문번호</th><th>고객</th><th class="num">금액</th>
        <th>상태</th><th>승인</th><th>고객 안내</th>
      </tr>
    </thead>
    <tbody>
      @foreach($recent as $p)
      <tr>
        <td>{{ $p->order?->order_number ?? '-' }}</td>
        <td>{{ $p->order?->patient?->name ?? '-' }}</td>
        <td class="num">{{ number_format((int) $p->amount) }}원</td>
        <td>
          <span class="dp-badge {{ $p->status === 'done' ? 'is-done' : ($p->status === 'rejected' ? 'is-ledger' : 'is-extra') }}">
            {{ $p->상태말() }}
          </span>
          @if($p->reject_reason)<div class="dp-sub">{{ $p->reject_reason }}</div>@endif
        </td>
        <td>{{ $p->approvedBy?->name ?? '-' }}<div class="dp-sub">{{ $p->approved_at_by?->format('m-d H:i') }}</div></td>
        <td class="dp-sub">{{ $p->notify_result ?: '-' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection

@push('scripts')
<script>
(() => {
  const SCAN_URL    = @json(route('duplicate-payments.scan'));
  const REQUEST_URL = @json(route('duplicate-payments.request'));
  const CAN_REQUEST = @json((bool) $canRequest);
  const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  const box  = document.getElementById('dpResult');
  const btn  = document.getElementById('dpScanBtn');
  const 돈   = n => (Number(n) || 0).toLocaleString('ko-KR');
  const 에스 = s => String(s ?? '').replace(/[&<>"']/g, c =>
                  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const 때 = s => {
    if (!s) return '-';
    // 2026-10-01T13:34:27+09:00 → 10-01 13:34:27
    const m = String(s).match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2}:\d{2})/);
    return m ? `${m[2]}-${m[3]} ${m[4]}` : s;
  };

  async function 조회() {
    const from = document.getElementById('dpFrom').value;
    const to   = document.getElementById('dpTo').value;

    if (!from || !to) { ceAlert('기간을 골라 주십시오.', { title: '중복 결제' }); return; }

    btn.disabled = true;
    const 옛글 = btn.textContent;
    btn.textContent = '토스에 묻는 중…';
    box.innerHTML = '<div class="dp-card"><div class="dp-empty">토스에서 거래를 가져오는 중입니다. 몇 초 걸립니다.</div></div>';

    try {
      const res  = await fetch(`${SCAN_URL}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
                               { headers: { 'Accept': 'application/json' } });
      const data = await res.json();

      if (!data.success) {
        box.innerHTML = `<div class="dp-card"><div class="dp-empty">${에스(data.message)}</div></div>`;
        return;
      }

      그리기(data);
    } catch (e) {
      box.innerHTML = '<div class="dp-card"><div class="dp-empty">조회하지 못했습니다. 잠시 뒤 다시 눌러 주십시오.</div></div>';
    } finally {
      btn.disabled = false;
      btn.textContent = 옛글;
    }
  }

  function 그리기(data) {
    if (!data.rows.length) {
      box.innerHTML = `<div class="dp-card"><div class="dp-empty">
        중복으로 들어온 결제가 없습니다.<br>
        <span style="font-size:12px;">토스 거래 ${data.scanned}건을 보고, 그 가운데 ${data.queried}건을 자세히 확인했습니다.</span>
      </div></div>`;
      return;
    }

    box.innerHTML = data.rows.map(r => `
      <div class="dp-card">
        <div class="dp-head">
          <div>
            <div class="dp-who">${에스(r.patient)} · ${에스(r.order_number)}</div>
            <div class="dp-sub">처방 ${에스(r.rx_number)}</div>
          </div>
          <div class="dp-sums">
            <span>받을 돈 <b>${돈(r.expected)}원</b></span>
            <span>토스에 살아 있는 승인 <b>${돈(r.paid_sum)}원</b></span>
            <span>우리 장부 <b>${돈(r.ledger_sum)}원</b></span>
            <span class="dp-excess">더 들어온 돈 ${돈(r.excess)}원</span>
          </div>
        </div>
        ${r.payments.map(p => 결제줄(r, p)).join('')}
      </div>`).join('');
  }

  function 결제줄(r, p) {
    /* 장부가 아는 결제는 돌려줄 수 없다 — 무르면 받은 돈이 0으로 읽혀
       정산과 증빙이 어긋난다. 버튼 자체를 세우지 않는다. */
    let 오른쪽;

    if (p.in_ledger) {
      오른쪽 = `<span class="dp-ledger">주문의 결제로 장부에 적혀 있어 환불할 수 없습니다</span>`;
    } else if (p.refund_status) {
      오른쪽 = `<span class="dp-badge ${p.refund_status === 'done' ? 'is-done' : 'is-waiting'}">${에스(p.refund_label)}</span>`;
    } else if (!CAN_REQUEST) {
      오른쪽 = `<span class="dp-note">환불을 요청할 권한이 없습니다</span>`;
    } else {
      오른쪽 = `<button type="button" class="btn btn-sm btn-danger dp-req"
                  data-order="${r.order_id}" data-key="${에스(p.payment_key)}"
                  data-toss="${에스(p.toss_order_id)}" data-method="${에스(p.method)}"
                  data-amount="${p.amount}" data-approved="${에스(p.approved_at)}"
                  data-who="${에스(r.patient)}">환불 요청</button>`;
    }

    return `
      <div class="dp-pay">
        <div class="dp-key">${에스(p.payment_key)}</div>
        <div>${때(p.approved_at)}</div>
        <div class="dp-amt">${돈(p.amount)}원</div>
        <div>
          <span class="dp-badge ${p.in_ledger ? 'is-ledger' : 'is-extra'}">
            ${p.in_ledger ? '장부에 있음' : '장부에 없음'}
          </span>
          <span class="dp-note">${에스(p.method || '')}</span>
        </div>
        <div>${오른쪽}</div>
      </div>`;
  }

  box.addEventListener('click', async (e) => {
    const b = e.target.closest('.dp-req');
    if (!b) return;

    const 금액 = Number(b.dataset.amount) || 0;

    const 예 = await ceConfirm(
      `${b.dataset.who}님의 중복 결제 ${돈(금액)}원을 환불 요청합니다.\n\n` +
      `최종승인자가 승인해야 실제로 환불됩니다.\n결제키 ${b.dataset.key}`,
      { title: '환불 요청', tone: 'warning', confirmText: '요청' });

    if (!예) return;

    b.disabled = true;

    try {
      const res = await fetch(REQUEST_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          order_id:      Number(b.dataset.order),
          payment_key:   b.dataset.key,
          toss_order_id: b.dataset.toss || null,
          method:        b.dataset.method || null,
          amount:        금액,
          approved_at:   b.dataset.approved || null,
        }),
      });
      const data = await res.json();

      await ceAlert(data.message, { title: '환불 요청', tone: data.success ? 'default' : 'warning' });

      if (data.success) location.reload();
      else b.disabled = false;
    } catch (err) {
      b.disabled = false;
      ceAlert('요청하지 못했습니다.', { title: '환불 요청', tone: 'warning' });
    }
  });

  /* 승인ㆍ반려 — 최종승인자에게만 단추가 서 있다 */
  document.addEventListener('click', async (e) => {
    const 승인 = e.target.closest('.dp-approve');
    const 반려 = e.target.closest('.dp-reject');
    if (!승인 && !반려) return;

    const tr = e.target.closest('tr[data-refund]');
    const id = tr?.dataset.refund;
    if (!id) return;

    if (승인) {
      const 예 = await ceConfirm(
        `지금 토스에서 실제로 환불이 나갑니다. 되돌릴 수 없습니다.\n\n` +
        `환불이 끝나면 고객에게 안내가 함께 발송됩니다.`,
        { title: '환불 승인', tone: 'danger', confirmText: '승인하고 환불' });

      if (!예) return;

      승인.disabled = true;
      const res  = await fetch(`/duplicate-payments/${id}/approve`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      });
      const data = await res.json();
      await ceAlert(data.message, { title: '환불 승인', tone: data.success ? 'default' : 'warning' });
      location.reload();
      return;
    }

    const 까닭 = await cePrompt('반려하는 까닭', { placeholder: '예) 고객이 추가 주문으로 쓰기로 함', confirmText: '반려' });
    if (!까닭) return;

    const res  = await fetch(`/duplicate-payments/${id}/reject`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ reason: 까닭 }),
    });
    const data = await res.json();
    await ceAlert(data.message, { title: '환불 반려', tone: data.success ? 'default' : 'warning' });
    location.reload();
  });

  btn.addEventListener('click', 조회);
})();
</script>
@endpush
