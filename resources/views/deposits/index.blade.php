{{-- resources/views/deposits/index.blade.php --}}
@extends('layouts.app')

@section('title', '입금 내역')
@section('page-title', '입금 내역')
@section('breadcrumb', '홈 - 입금 내역')

@push('styles')
<style>
  /* 나누는 창 — 표 한 줄이 환자 한 사람 몫이다 */
  #splitRows { display:flex; flex-direction:column; gap:8px; }
  .sp-row { display:grid; grid-template-columns: 150px 120px 1fr 1fr 32px; gap:8px; align-items:center; }
  .sp-row .form-control { height:32px; font-size:13px; }
  .sp-head { font-size:12px; color:var(--text-muted); }
  .sp-sum { display:flex; justify-content:space-between; align-items:center;
            padding:10px 12px; margin-top:10px; border-radius:8px; background:var(--gray-50);
            font-size:13px; font-weight:600; }
  .sp-left-bad { color:var(--danger); }
  .sp-left-ok  { color:var(--success); }
</style>
@endpush

@section('content')

@if(!$configured)
  {{-- 계좌가 팝빌에 등록되기 전에는 긁어 올 것이 없다. 빈 화면만 보이면 고장으로 읽힌다. --}}
  <div class="ds-filter-card" style="border-color:var(--warning-light);background:var(--warning-light);margin-bottom:12px;">
    <div style="font-size:13px;color:var(--warning);font-weight:600;">
      <i class="bx bx-info-circle"></i>
      계좌조회 설정이 아직 없습니다 — 팝빌에 계좌를 등록하고
      <code>BANK_ACCOUNT_BANK_CODE</code>ㆍ<code>BANK_ACCOUNT_NUMBER</code> 를 적어 주십시오.
    </div>
  </div>
@endif

{{-- 거르는 줄은 하나다. 이 화면이 묻는 것은 「언제」와 「누구」 둘뿐이라
     두 줄을 쓸 까닭이 없다. --}}
<form method="GET" class="ds-filter-card">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="입금자명ㆍ적요ㆍ주문번호ㆍ이름">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('deposits.index', ['tab' => $tab]) }}" class="ds-btn">
      <i class="fa-solid fa-rotate-left"></i> 초기화
    </a>
    <button type="submit" class="ds-btn ds-btn-primary"><i class="fa-solid fa-search"></i> 검색</button>
  </div>
</form>

<div class="ds-grid-card">
  {{-- 요청서 5쪽의 세 탭. 다른 화면의 「조회 결과ㆍ상세 내용」과 같은 규격으로 세운다 —
       화면마다 탭이 다른 모양이면 어디를 눌러야 갈리는지를 매번 다시 익혀야 한다.

       건수는 지금 걸린 기간과 상관없이 전체를 센다. 여기서 묻는 것은 「이 달에 몇 건인가」가
       아니라 「할 일이 얼마나 남았는가」다. --}}
  <div class="pnl-tabs">
    @foreach(\App\Http\Controllers\DepositController::TABS as $k => $label)
      <a href="{{ route('deposits.index', array_filter(['tab' => $k, 'q' => request('q'), 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
         class="pnl-tab {{ $tab === $k ? 'active' : '' }}">
        {{ $label }}<span class="pnl-tab-cnt">(총 <b>{{ number_format($counts[$k] ?? 0) }}</b>건)</span>
      </a>
    @endforeach
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center;padding-right:12px;">
      {{-- 서른 분마다 저절로 돌지만, 방금 들어온 돈은 곧바로 봐야 할 때가 있다 --}}
      <form method="POST" action="{{ route('deposits.pull') }}" style="display:inline;">
        @csrf
        <button type="submit" class="ds-btn" @disabled(!$configured)
                title="팝빌 계좌조회로 지금 가져옵니다 — 다 모일 때까지 잠시 기다립니다">
          <i class="fa-solid fa-cloud-arrow-down"></i> 지금 가져오기
        </button>
      </form>
      <button type="button" class="ds-btn" onclick="window.__depositGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>
  <div id="depositGrid"></div>
</div>

{{-- 통으로 들어온 입금을 환자별로 나누는 창 (요청서 5쪽) --}}
<div id="splitModal" class="modal" style="display:none;">
  <div class="modal-dialog" style="max-width:820px;">
    <div class="modal-header">
      <span class="modal-title">입금 나누기</span>
      <button type="button" class="modal-close" onclick="spClose()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">
        지자체가 여러 환자 건을 통으로 보낸 입금입니다. 환자별로 나눠 적으면 각 주문에 걸립니다.
        원본 줄은 통장이 준 그대로 남습니다.
      </div>
      <div class="sp-row sp-head">
        <span>주문번호</span><span>금액</span><span>적요 메모</span><span>담당자메모</span><span></span>
      </div>
      <div id="splitRows"></div>
      <button type="button" class="ds-btn" style="margin-top:8px;" onclick="spAdd()">
        <i class="fa-solid fa-plus"></i> 줄 추가
      </button>
      <div class="sp-sum">
        <span>입금액 <b id="spTotal">0</b>원</span>
        <span>나눈 합 <b id="spSum">0</b>원 · 남은 금액 <b id="spLeft">0</b>원</span>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="ds-btn" onclick="spClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" onclick="spSave()">저장</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const TAB   = @json($tab);
  const BASE  = @json(url('deposits'));
  const money = (v) => {
    const n = Number(v || 0);
    if (!n) return '';
    const s = document.createElement('span');
    s.textContent = n.toLocaleString('ko-KR');
    return s;
  };

  const grid = new wwGrid({
    el: document.getElementById('depositGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '입금일시',   name: 'traded_at', width: 130, sortable: true },
      { header: '입금액',     name: 'amount',    width: 110, align: 'right', sortable: true, renderer: money },
      // 돈을 보낸 사람은 환자와 다른 일이 잦다(보호자가 보낸다)
      { header: '실제 입금자명', name: 'sender',  width: 110, sortable: true },
      { header: '적요',       name: 'remark',    width: 110 },
      { header: '취급점',     name: 'branch',    width: 130 },
      { header: '구분',       name: 'kind',      width: 90,  align: 'center', sortable: true },
      { header: '주문번호',   name: 'order_no',  width: 120, sortable: true },
      { header: '이름',       name: 'patient',   width: 90,  sortable: true },
      {
        /* 맞추기 — 주문번호를 적으면 그 자리에서 걸린다. 상세로 들어갔다 나오는
           걸음을 없앤다. 비우고 저장하면 맺음이 풀린다. */
        header: '맞추기', name: 'match', width: 150, sortable: false, exportable: false,
        renderer: (v, row) => matchCell(row),
      },
      {
        // 통으로 온 입금을 환자별로 나눈다(요청서 5쪽)
        header: '나누기', name: 'split_n', width: 120, align: 'center', sortable: true,
        renderer: (v, row) => splitCell(row),
      },
      { header: '나눈 합',    name: 'split_sum',  width: 100, align: 'right', renderer: money },
      { header: '남은 금액',  name: 'split_left', width: 100, align: 'right', renderer: money },
      { header: '거래후 잔액', name: 'balance',   width: 110, align: 'right', renderer: money },
      { header: '콜로 모 계좌', name: 'acct',     width: 140 },
      { header: '입금 번호',  name: 'deposit_no', width: 150 },
      { header: '통장 메모',  name: 'bank_memo',  width: 150 },
      { header: '맞춘 날',    name: 'matched',    width: 100, align: 'center', sortable: true },
      { header: '담당자메모', name: 'staff_memo', width: 200 },

      /* 네 화면이 함께 쓰던 칸을 여기에도 세운다(요청서 3쪽). 주문이 붙은 줄에만
         값이 선다 — 아직 맞추지 않은 입금은 빈칸이고, 그 빈칸이 곧 「할 일」이다. */
      ...ceMoneyCols(),
      ...ceWwCols(),
    ],
    data: @json($gridData),
  });
  window.__depositGrid = grid;
  window.dsBindSelCount?.(grid, 'depSelCount');

  /* ── 맞추기 ────────────────────────────────────────── */
  function matchCell(row) {
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;gap:4px;align-items:center;';

    const inp = document.createElement('input');
    inp.type = 'text';
    inp.className = 'form-control';
    inp.style.cssText = 'height:26px;font-size:11px;padding:2px 6px;min-width:0;flex:1;';
    inp.value = row.order_no || '';
    inp.placeholder = '주문번호';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ds-btn';
    btn.style.cssText = 'height:26px;padding:0 8px;font-size:11px;flex-shrink:0;';
    btn.textContent = '맞춤';
    btn.onclick = () => save(row.id, inp.value.trim());

    box.append(inp, btn);
    return box;
  }

  async function save(id, orderNo) {
    try {
      const res = await fetch(`${BASE}/${id}/match`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: JSON.stringify({ order_number: orderNo || null,
                               kind: TAB === 'agency' ? 'agency' : 'copay' }),
      });
      const d = await res.json();
      showToast(d.message || (d.success ? '맞췄습니다.' : '맞추지 못했습니다.'),
                d.success ? 'success' : 'danger');
      if (d.success) setTimeout(() => location.reload(), 600);
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    }
  }

  /* ── 나누기 ────────────────────────────────────────── */
  let spId = null, spAmount = 0;

  function splitCell(row) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ds-btn';
    btn.style.cssText = 'height:26px;padding:0 8px;font-size:11px;';
    btn.textContent = row.split_n ? `${row.split_n}건 나눔` : '나누기';
    if (row.split_left && Number(row.split_left) !== 0) {
      btn.style.color = 'var(--danger)';
      btn.style.fontWeight = '700';
      btn.title = '나눈 합이 입금액과 맞지 않습니다';
    }
    btn.onclick = () => spOpen(row.id, Number(row.amount || 0));
    return btn;
  }

  window.spOpen = async function (id, amount) {
    spId = id; spAmount = amount;
    document.getElementById('spTotal').textContent = amount.toLocaleString('ko-KR');
    document.getElementById('splitRows').innerHTML = '';

    try {
      const res = await fetch(`${BASE}/${id}/splits`, { headers: { 'Accept': 'application/json' } });
      const d   = await res.json();
      (d.rows || []).forEach(r => spAdd(r));
    } catch (e) { /* 못 읽으면 빈 줄로 연다 — 새로 적으면 된다 */ }

    if (!document.getElementById('splitRows').children.length) spAdd();
    spRecalc();
    document.getElementById('splitModal').style.display = 'flex';
  };

  window.spClose = function () { document.getElementById('splitModal').style.display = 'none'; };

  window.spAdd = function (r) {
    const row = document.createElement('div');
    row.className = 'sp-row';
    row.innerHTML =
      '<input type="text"   class="form-control sp-no"   placeholder="ORD-0000">' +
      '<input type="number" class="form-control sp-amt"  placeholder="0" min="1">' +
      '<input type="text"   class="form-control sp-memo" placeholder="청구했던 기관 등">' +
      '<input type="text"   class="form-control sp-smemo" placeholder="확인할 것">' +
      '<button type="button" class="ds-btn" style="height:32px;padding:0 8px;">&times;</button>';

    if (r) {
      row.querySelector('.sp-no').value    = r.order_number ?? '';
      row.querySelector('.sp-amt').value   = r.amount ?? '';
      row.querySelector('.sp-memo').value  = r.memo ?? '';
      row.querySelector('.sp-smemo').value = r.staff_memo ?? '';
    }

    row.querySelector('button').onclick = () => { row.remove(); spRecalc(); };
    row.querySelector('.sp-amt').addEventListener('input', spRecalc);
    document.getElementById('splitRows').appendChild(row);
    spRecalc();
  };

  /* 남은 금액이 0 이 아니면 아직 다 나누지 못한 것이다 — 저장은 막지 않는다.
     한 번에 다 못 나누는 날이 있고, 그때 적어 둔 것까지 잃으면 처음부터 다시 한다. */
  function spRecalc() {
    let sum = 0;
    document.querySelectorAll('#splitRows .sp-amt').forEach(el => { sum += Number(el.value || 0); });
    const left = spAmount - sum;
    document.getElementById('spSum').textContent  = sum.toLocaleString('ko-KR');
    const el = document.getElementById('spLeft');
    el.textContent = left.toLocaleString('ko-KR');
    el.className = left === 0 ? 'sp-left-ok' : 'sp-left-bad';
  }

  window.spSave = async function () {
    const rows = [...document.querySelectorAll('#splitRows .sp-row')].map(r => ({
      order_number: r.querySelector('.sp-no').value.trim(),
      amount:       Number(r.querySelector('.sp-amt').value || 0),
      memo:         r.querySelector('.sp-memo').value.trim() || null,
      staff_memo:   r.querySelector('.sp-smemo').value.trim() || null,
    })).filter(r => r.order_number && r.amount > 0);

    try {
      const res = await fetch(`${BASE}/${spId}/split`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        body: JSON.stringify({ rows }),
      });
      const d = await res.json();
      showToast(d.message || (d.success ? '나눴습니다.' : '나누지 못했습니다.'),
                d.success ? 'success' : 'danger');
      if (d.success) { spClose(); setTimeout(() => location.reload(), 600); }
    } catch (e) {
      showToast('오류가 발생했습니다.', 'danger');
    }
  };
})();
</script>
@endpush
