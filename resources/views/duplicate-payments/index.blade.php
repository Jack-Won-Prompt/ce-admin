{{-- resources/views/duplicate-payments/index.blade.php --}}
@extends('layouts.app')

@section('title', '중복 결제')
@section('page-title', '중복 결제')
@section('breadcrumb', '홈 - 중복 결제')

@push('styles')
<style>
  /* 구분 배지 — 어느 쪽을 돌려줄 수 있는지가 한눈에 갈려야 한다 */
  .dp-badge { display:inline-block; padding:2px 8px; border-radius:999px;
              font-size:11px; font-weight:700; white-space:nowrap; }
  .dp-badge.is-ledger  { background:var(--gray-100, #f3f4f6); color:var(--text-muted, #6b7280); }
  .dp-badge.is-extra   { background:#fdecea; color:#b3261e; }
  .dp-badge.is-waiting { background:#fff4e5; color:#8a5300; }
  .dp-badge.is-done    { background:#e7f5ec; color:#1b6b38; }
  .dp-badge.is-failed  { background:#fdecea; color:#b3261e; }

  /* 누를 수 있는 자리는 단추로 보여야 한다 — 글자만 두면 누르는 자리인지 모른다 */
  .dp-act { display:inline-flex; align-items:center; gap:4px; cursor:pointer;
            padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700;
            border:1px solid transparent; }
  .dp-act.is-refund  { background:#fdecea; color:#b3261e; border-color:#f5c2bd; }
  .dp-act.is-approve { background:var(--primary-50, #eef2ff); color:var(--primary, #2563eb);
                       border-color:#c7d2fe; }
  .dp-act.is-reject  { background:var(--gray-100, #f3f4f6); color:var(--text-muted, #6b7280);
                       border-color:var(--gray-200, #e5e7eb); margin-left:4px; }
  .dp-act:hover { filter:brightness(0.97); }
  .dp-act.is-busy { opacity:.5; pointer-events:none; }

  .dp-hint { font-size:12px; color:var(--text-muted, #6b7280); padding:0 2px 10px; line-height:19px; }
  .dp-sum  { font-size:13px; font-weight:600; padding:8px 2px; }
  .dp-sum .n { font-variant-numeric:tabular-nums; }
  .dp-sec   { margin-top:22px; }
  .dp-sec-title { font-size:14px; font-weight:700; margin:0 0 10px; }
</style>
@endpush

@section('content')

{{-- 기간을 고르고 눌러야 토스에 간다. 화면을 열 때마다 저쪽을 부르면 하루에도
     수십 번 묻게 되고, 그만큼 담당자가 기다린다. --}}
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
</div>

<div class="dp-hint">
  한 주문에 두 번 이상 들어온 결제를 토스 거래 내역에서 찾습니다. 우리 기록에는
  뒤 결제가 앞 결제를 덮어 남지 않으므로, 이 목록이 보는 것은 토스의 거래 내역입니다.
  <strong>「주문 결제」</strong>는 이 주문의 결제로 기록된 건이라 환불할 수 없고,
  <strong>「초과 결제」</strong>가 중복으로 더 들어온 돈입니다. 한 번에 31일까지 조회합니다.
</div>

<div class="dp-sum" id="dpSum" style="display:none;"></div>
<div id="dpGrid"></div>

<div class="dp-sec">
  <div class="dp-sec-title">환불 처리 내역</div>
  <div id="dpWorkGrid"></div>
</div>

@endsection

@push('scripts')
<script>
(() => {
  const SCAN_URL    = @json(route('duplicate-payments.scan'));
  const REQUEST_URL = @json(route('duplicate-payments.request'));
  const CAN_APPROVE = @json((bool) $canApprove);
  const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  const 돈 = n => (Number(n) || 0).toLocaleString('ko-KR');

  /* ── 칸을 그리는 함수들 — 서버는 이름만 주고 여기서 바꿔 끼운다 ──────── */

  /* 주문번호를 누르면 주문 관리가 새 탭으로 열리며 그 주문만 조회된다
     (2026-10-02 지시).

     `data-ce-tab` 을 붙이면 워크스페이스가 가로채 탭을 연다 — 지금 보던 목록이
     그대로 남는다(layouts/app 의 위임 처리). 액자 밖이면 브라우저 새 탭이 된다.

     날짜는 주지 않는다. 주문 관리는 date 가 있으면 그날로 좁히는데, 중복 결제는
     며칠 지난 건도 올라오므로 날짜를 걸면 빈 목록이 나온다. */
  function dpOrderLink(value) {
    if (!value || value === '-') return null;

    const a = document.createElement('a');
    a.href = '/orders?q=' + encodeURIComponent(value);
    a.dataset.ceTab  = '주문 관리 · ' + value;
    a.dataset.ceIcon = 'bx-cart';
    a.textContent = value;
    a.style.cssText = 'color:var(--primary,#2563eb);text-decoration:underline;cursor:pointer;';
    a.title = '주문 관리에서 이 주문을 엽니다';
    return a;
  }

  function dpKindBadge(value, row) {
    const b = document.createElement('span');
    b.className = 'dp-badge ' + (value === 'ledger' ? 'is-ledger' : 'is-extra');
    b.textContent = row?.kind_label || (value === 'ledger' ? '주문 결제' : '초과 결제');
    if (value === 'ledger') {
      b.title = '이 주문의 결제로 기록된 건입니다. 무르면 결제 금액이 0으로 읽혀 '
              + '정산과 증빙이 어긋나므로 환불할 수 없습니다.';
    }
    return b;
  }

  function dpStatusBadge(value, row) {
    const 결 = { '환불 완료': 'is-done', '승인 대기': 'is-waiting',
                 '환불 실패': 'is-failed', '반려': 'is-ledger' }[value] || 'is-waiting';
    const b = document.createElement('span');
    b.className = 'dp-badge ' + 결;
    b.textContent = value || '-';
    if (row?.reject) b.title = row.reject;
    return b;
  }

  /* 환불 요청 — 세울 수 있는 줄인지는 서버가 정한다(act) */
  function dpRefundBadge(value, row) {
    if (!value) {
      if (row?.refund_label) return dpStatusBadge(row.refund_label, row);
      if (row?.kind === 'ledger') {
        const s = document.createElement('span');
        s.style.cssText = 'font-size:11px;color:var(--text-muted,#6b7280);';
        s.textContent = '환불 불가';
        return s;
      }
      return null;
    }
    const b = document.createElement('span');
    b.className = 'dp-act is-refund';
    b.innerHTML = '<i class="fa-solid fa-rotate-left"></i> 환불 요청';
    return b;
  }

  /* 승인ㆍ반려 — 최종승인자에게만 세운다 */
  function dpApproveBadge(value) {
    if (!value || !CAN_APPROVE) {
      if (value && !CAN_APPROVE) {
        const s = document.createElement('span');
        s.style.cssText = 'font-size:11px;color:var(--text-muted,#6b7280);';
        s.textContent = '최종승인자만 처리';
        return s;
      }
      return null;
    }
    const wrap = document.createElement('span');
    const ok = document.createElement('span');
    ok.className = 'dp-act is-approve';
    ok.dataset.act = 'approve';
    ok.innerHTML = '<i class="fa-solid fa-check"></i> 승인';
    const no = document.createElement('span');
    no.className = 'dp-act is-reject';
    no.dataset.act = 'reject';
    no.textContent = '반려';
    wrap.append(ok, no);
    return wrap;
  }

  /* ── 목록 둘 ────────────────────────────────────────────── */

  const 조회칸 = @json($scanColumns).map(c => ({
    ...c,
    renderer: { dpKindBadge, dpRefundBadge, dpStatusBadge, dpOrderLink }[c.renderer] ?? c.renderer,
  }));

  const 처리칸 = @json($workColumns).map(c => ({
    ...c,
    renderer: { dpApproveBadge, dpStatusBadge, dpOrderLink }[c.renderer] ?? c.renderer,
  }));

  /* 높이를 숫자로 준다 — 줄이 없어도 목록 자리가 그대로 선다(2026-10-02 지시).
     'fit' 은 화면 아래까지 채우는 값이라, 한 화면에 목록이 둘이면 서로 다툰다. */
  const 처음말 = '기간을 고르고 [조회]를 눌러 주십시오.';

  const grid = new wwGrid({
    el: document.getElementById('dpGrid'),
    height: 420, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    emptyText: 처음말,
    columns: 조회칸,
    data: [],
  });

  const workGrid = new wwGrid({
    el: document.getElementById('dpWorkGrid'),
    height: 300, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    emptyText: '환불 처리 내역이 없습니다.',
    columns: 처리칸,
    data: @json($workData),
  });

  /* ── 조회 ──────────────────────────────────────────────── */

  const btn = document.getElementById('dpScanBtn');
  const 요약 = document.getElementById('dpSum');

  async function 조회() {
    const from = document.getElementById('dpFrom').value;
    const to   = document.getElementById('dpTo').value;

    if (!from || !to) { ceAlert('기간을 골라 주십시오.', { title: '중복 결제' }); return; }

    btn.disabled = true;
    const 옛글 = btn.textContent;
    btn.textContent = '조회 중…';
    요약.style.display = '';
    요약.textContent = '조회 중입니다. 몇 초 걸립니다.';

    try {
      const res  = await fetch(`${SCAN_URL}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
                               { headers: { 'Accept': 'application/json' } });
      const data = await res.json();

      if (!data.success) {
        grid.emptyText = 처음말;
        grid.setData([]);
        요약.textContent = data.message || '조회하지 못했습니다.';
        return;
      }

      /* 빈 표에 적을 말도 그때그때 바꾼다 — 「조회를 눌러 주십시오」가 조회한
         뒤에도 남아 있으면, 찾아봤는데 없는 것인지 아직 안 찾은 것인지 모른다. */
      grid.emptyText = '중복으로 들어온 결제가 없습니다.';
      grid.setData(data.rows);

      const 중복금액 = data.rows
        .filter(r => r.kind === 'extra')
        .reduce((s, r) => s + (Number(r.amount) || 0), 0);

      요약.innerHTML = data.rows.length
        ? `중복 결제 <span class="n">${data.orders}</span>건 · `
          + `중복 결제 금액 <span class="n" style="color:var(--danger,#dc2626);">${돈(중복금액)}원</span>`
          + ` <span style="font-weight:400;color:var(--text-muted,#6b7280);">`
          + `(토스 거래 ${돈(data.scanned)}건을 보고 ${돈(data.queried)}건을 자세히 확인했습니다)</span>`
        : `중복으로 들어온 결제가 없습니다. `
          + `<span style="font-weight:400;color:var(--text-muted,#6b7280);">`
          + `(토스 거래 ${돈(data.scanned)}건 확인)</span>`;
    } catch (e) {
      grid.emptyText = 처음말;
      grid.setData([]);
      요약.textContent = '조회하지 못했습니다. 잠시 뒤 다시 눌러 주십시오.';
    } finally {
      btn.disabled = false;
      btn.textContent = 옛글;
    }
  }

  btn.addEventListener('click', 조회);

  /* ── 환불 요청 ─────────────────────────────────────────── */

  document.getElementById('dpGrid').addEventListener('click', async (e) => {
    const 단추 = e.target.closest('.dp-act.is-refund');
    if (!단추) return;

    const cell = 단추.closest('[data-row-index]');
    if (!cell) return;

    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.act) return;

    const 예 = await ceConfirm(
      `${row.patient}님의 초과 결제 ${돈(row.amount)}원을 환불 요청합니다.\n\n`
      + `최종승인자가 승인해야 실제로 환불됩니다.\n`
      + `승인 ${row.approved_at} · 결제키 ${row.payment_key}`,
      { title: '환불 요청', tone: 'warning', confirmText: '요청' });

    if (!예) return;

    단추.classList.add('is-busy');

    try {
      const res = await fetch(REQUEST_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          order_id:      Number(row.order_id),
          payment_key:   row.payment_key,
          toss_order_id: row.toss_order_id || null,
          method:        row.method === '-' ? null : row.method,
          amount:        Number(row.amount),
        }),
      });
      const data = await res.json();

      await ceAlert(data.message, { title: '환불 요청', tone: data.success ? 'default' : 'warning' });

      if (data.success) location.reload();
      else 단추.classList.remove('is-busy');
    } catch (err) {
      단추.classList.remove('is-busy');
      ceAlert('요청하지 못했습니다.', { title: '환불 요청', tone: 'warning' });
    }
  });

  /* ── 승인ㆍ반려 ────────────────────────────────────────── */

  document.getElementById('dpWorkGrid').addEventListener('click', async (e) => {
    const 단추 = e.target.closest('.dp-act[data-act]');
    if (!단추) return;

    const cell = 단추.closest('[data-row-index]');
    if (!cell) return;

    const row = workGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row?.id) return;

    if (단추.dataset.act === 'approve') {
      const 예 = await ceConfirm(
        `${row.patient}님의 ${돈(row.amount)}원을 지금 환불합니다.\n\n`
        + `토스에서 실제로 돈이 나가며 되돌릴 수 없습니다.\n`
        + `환불이 끝나면 고객에게 안내가 함께 발송됩니다.`,
        { title: '환불 승인', tone: 'danger', confirmText: '승인하고 환불' });

      if (!예) return;

      단추.classList.add('is-busy');

      const res  = await fetch(`/duplicate-payments/${row.id}/approve`, {
        method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      });
      const data = await res.json();
      await ceAlert(data.message, { title: '환불 승인', tone: data.success ? 'default' : 'warning' });
      location.reload();
      return;
    }

    const 까닭 = await cePrompt('반려하는 까닭', {
      placeholder: '예) 고객이 추가 주문으로 쓰기로 함', confirmText: '반려' });
    if (!까닭) return;

    const res  = await fetch(`/duplicate-payments/${row.id}/reject`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ reason: 까닭 }),
    });
    const data = await res.json();
    await ceAlert(data.message, { title: '환불 반려', tone: data.success ? 'default' : 'warning' });
    location.reload();
  });
})();
</script>
@endpush
