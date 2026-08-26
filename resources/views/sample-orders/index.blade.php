{{-- resources/views/sample-orders/index.blade.php --}}
@extends('layouts.app')

@section('title', 'CE 샘플주문')
@section('page-title', 'CE 샘플주문')
@section('breadcrumb', '홈 - 주문 - CE 샘플주문')

@section('help-title', 'CE 샘플주문 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>처방 없이 나가는 샘플을 등록하고 창고에 넘기는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">유형</div>
  <div class="help-badge-row">
    @foreach(\App\Models\SampleOrder::TYPES as $code => $label)
      <span class="badge badge-secondary">{{ $code }} · {{ $label }}</span>
    @endforeach
  </div>
</div>
@endsection

@push('styles')
<style>
  /* 상세·신규 판 — 목록과 같은 카드 안에서 탭으로 갈린다 */
  .smp-pane { padding: 14px 16px 18px; }
  .smp-sec { margin-bottom: 16px; }
  .smp-sec-hd { font-size: 13px; font-weight: 700; color: var(--gray-1000); margin-bottom: 8px;
                display: flex; align-items: center; gap: 8px; }
  .smp-sec-hd .step { display:inline-flex; align-items:center; justify-content:center;
                      width:18px; height:18px; border-radius:999px; background:var(--primary);
                      color:#fff; font-size:11px; font-weight:700; }

  .smp-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px 14px; }
  .smp-f { display: flex; flex-direction: column; gap: 4px; }
  .smp-f.span2 { grid-column: span 2; }
  .smp-f.span4 { grid-column: span 4; }
  .smp-f label { font-size: 12px; font-weight: 600; color: var(--gray-700); }
  @media (max-width: 1100px) { .smp-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
                               .smp-f.span4 { grid-column: span 2; } }

  /* 제품 담기 — 위드웍스처럼 찾아서 줄로 쌓는다 */
  .smp-find { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
  .smp-find input { max-width: 300px; }
  .smp-hits { border: 1px solid var(--border); border-radius: 8px; max-height: 180px;
              overflow-y: auto; margin-bottom: 10px; }
  .smp-hit { display: flex; gap: 10px; align-items: center; padding: 7px 12px; font-size: 12px;
             border-bottom: 1px solid var(--border-light); cursor: pointer; }
  .smp-hit:last-child { border-bottom: none; }
  .smp-hit:hover { background: var(--primary-light); }
  .smp-hit .code { width: 90px; flex-shrink: 0; font-family: monospace; font-weight: 700; color: var(--primary); }
  .smp-hit .name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .smp-hit .price { width: 90px; text-align: right; font-variant-numeric: tabular-nums; }
  .smp-empty { padding: 12px; font-size: 12px; color: var(--gray-700); text-align: center; }

  .smp-items { width: 100%; border-collapse: collapse; font-size: 13px; }
  .smp-items th, .smp-items td { padding: 7px 10px; border-bottom: 1px solid var(--border-light); text-align: left; }
  .smp-items th { font-size: 12px; font-weight: 600; color: var(--gray-700); background: var(--gray-50); }
  .smp-items td.num, .smp-items th.num { text-align: right; font-variant-numeric: tabular-nums; }
  .smp-items input { width: 90px; height: 28px; padding: 0 8px; border: 1px solid var(--border);
                     border-radius: 6px; font-size: 13px; text-align: right; }
  .smp-items .del { color: var(--danger); cursor: pointer; font-weight: 700; }
  .smp-none { padding: 18px; text-align: center; font-size: 13px; color: var(--gray-700); }

  .smp-kv { display: flex; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border-light);
            font-size: 13px; }
  .smp-kv:last-child { border-bottom: none; }
  .smp-kv > span:first-child { width: 96px; flex-shrink: 0; color: var(--gray-700); font-weight: 500; }
  .smp-kv > span:last-child { flex: 1; font-weight: 500; }

  .smp-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;
                 padding-top: 12px; border-top: 1px solid var(--border); }
</style>
@endpush

@section('content')

{{-- 유형은 CE 샘플주문 하나뿐이라 칩으로 가를 것이 없다. 진행 상태를 둔다. --}}
@php $curStatus = request('status'); @endphp
{{-- 상태는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}

<form method="GET" action="{{ route('sample-orders.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $counts->sum() }})</option>
        @foreach(\App\Models\SampleOrder::STATUS_LABELS as $k => $meta)
          <option value="{{ $k }}" {{ $curStatus === $k ? 'selected' : '' }}>
            {{ $meta[0] }}@if(($counts[$k] ?? 0) > 0) ({{ $counts[$k] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="샘플번호ㆍ고객ㆍ받는 사람ㆍ판매주문번호">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">주문일</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request()->hasAny(['q','date_from','date_to']))
      <a href="{{ route('sample-orders.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 접수는 찾는 일과 나란히 둔다 --}}
    <button type="button" class="ds-btn ds-btn-primary" onclick="smpPane('new')">
      <i class="bx bx-plus"></i> 신규
    </button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__smpGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</form>

<div class="ds-grid-section">
  {{-- 서류 관리와 같은 얼개다 — 흰 카드 한 장 안에 탭줄과 판이 들어간다.
       탭줄을 카드 밖에 두었더니 그 줄만 회색 바탕 위에 떠 있었다. --}}
  <div class="ds-grid-card">
  <div class="pnl-tabs">
    <button type="button" id="smpTabList"   class="pnl-tab active" onclick="smpPane('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ $total }}</b>건)</span></button>
    <button type="button" id="smpTabDetail" class="pnl-tab" onclick="smpPane('detail')">상세보기</button>
    <button type="button" id="smpTabNew"    class="pnl-tab" onclick="smpPane('new')">신규등록</button>
  </div>

  <div id="smpPaneList">
    <div id="smpGrid"></div>
  </div>

  {{-- 상세 내용 — 머리 정보와 제품 목록 --}}
  <div id="smpPaneDetail" style="display:none;">
    <div class="smp-pane">
      <div id="smpDetailEmpty" class="smp-none">목록에서 행을 더블클릭하면 여기에 나옵니다.</div>
      <div id="smpDetailBody" style="display:none;">
        <div class="smp-sec">
          <div class="smp-sec-hd" id="smpDetailTitle"></div>
          <div id="smpDetailHead"></div>
        </div>
        <div class="smp-sec">
          <div class="smp-sec-hd">제품</div>
          <table class="smp-items">
            <thead>
              <tr><th style="width:110px;">제품코드</th><th>제품명</th>
                  <th class="num" style="width:80px;">수량</th>
                  <th class="num" style="width:100px;">단가</th>
                  <th class="num" style="width:110px;">금액</th></tr>
            </thead>
            <tbody id="smpDetailItems"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- 신규 등록 --}}
  <div id="smpPaneNew" style="display:none;">
    <form class="smp-pane" id="smpForm">
      <div class="smp-sec">
        <div class="smp-sec-hd"><span class="step">1</span> 받는 곳</div>
        <div class="smp-grid">
          {{-- 고객을 고르면 배송지가 따라온다. 이름만 적어 두면 같은 사람을 두 번 적을 때
               갈리고, 이 사람에게 몇 번 보냈는지 셀 수 없다.
               등록되지 않은 사람에게 보내는 일이 있어 그냥 적는 길도 남긴다. --}}
          <div class="smp-f">
            <label>고객 *</label>
            <div style="display:flex;gap:6px;">
              <input type="text" id="smpAccount" class="form-control" maxlength="100"
                     placeholder="조회해서 고르거나 그대로 적으십시오">
              <button type="button" class="ds-btn" style="flex-shrink:0;" onclick="smpPickCustomer(this)">조회</button>
            </div>
            <input type="hidden" id="smpPatientId" value="">
            <span class="ds-grid-hint" id="smpCustKind"></span>
          </div>
          <div class="smp-f">
            <label>받는 사람 *</label>
            <input type="text" id="smpRecipient" class="form-control" maxlength="100"
                   placeholder="고객과 같으면 그대로 둡니다">
          </div>
          <div class="smp-f">
            <label>연락처</label>
            <input type="text" id="smpMobile" class="form-control" maxlength="30" placeholder="010-0000-0000">
          </div>
          <div class="smp-f">
            <label>우편번호</label>
            <input type="text" id="smpPostcode" class="form-control" maxlength="10">
          </div>
          <div class="smp-f span2">
            <label>주소 *</label>
            <input type="text" id="smpAddress" class="form-control" maxlength="300">
          </div>
          <div class="smp-f">
            <label>상세주소</label>
            <input type="text" id="smpAddressDetail" class="form-control" maxlength="200">
          </div>
          <div class="smp-f">
            <label>주문일 *</label>
            <input type="date" id="smpOrderDate" class="form-control" value="{{ now()->format('Y-m-d') }}">
          </div>
          <div class="smp-f">
            {{-- 샘플은 대개 영업 담당자가 달라고 하고 사무실에서 대신 넣는다.
                 등록한 사람만 남으면 나중에 「이 샘플 누가 요청했나」를 물을 곳이 없다.
                 손으로 적게 두지 않는다 — 같은 사람이 여러 이름으로 남는다. --}}
            <label>요청자</label>
            <div style="display:flex;gap:6px;">
              <input type="text" id="smpRequester" class="form-control" readonly
                     style="background:var(--gray-50);" placeholder="담당자를 고르십시오">
              <button type="button" class="ds-btn" style="flex-shrink:0;"
                      onclick="smpPickRequester(this)">조회</button>
            </div>
            <input type="hidden" id="smpRequesterId" value="">
          </div>
          <div class="smp-f">
            <label>배송요청일</label>
            <input type="date" id="smpDeliveryDate" class="form-control">
          </div>
          <div class="smp-f span2">
            <label>용도</label>
            <input type="text" id="smpPurpose" class="form-control" maxlength="200"
                   placeholder="무엇에 쓰는 샘플인지 적어 두면 나중에 판단이 쉽습니다">
          </div>
          <div class="smp-f span4">
            <label>비고</label>
            <input type="text" id="smpNote" class="form-control" maxlength="500">
          </div>
        </div>
      </div>

      <div class="smp-sec">
        <div class="smp-sec-hd">
          <span class="step">2</span> 제품
          {{-- 줄을 더하고 던다. 제품코드·제품명 칸을 누르면 조회 창이 열린다. --}}
          <span style="margin-left:auto;display:flex;gap:6px;">
            <span class="ds-grid-hint" id="smpSumNote">0줄 · 0개 · 0원</span>
            <button type="button" class="ds-btn" onclick="smpDelRow()" title="고른 줄을 던다">−</button>
            <button type="button" class="ds-btn" onclick="smpAddRow()" title="줄을 더한다">+</button>
          </span>
        </div>
        <div id="smpItemGrid"></div>
      </div>

      <div class="smp-actions">
        <button type="button" class="ds-btn" onclick="smpPane('list')">그만두기</button>
        <button type="submit" class="ds-btn ds-btn-primary">저장</button>
      </div>
    </form>
  </div>
  </div>{{-- /.ds-grid-card --}}
</div>

@endsection

@push('scripts')
<script>
(function () {
  const SHOW_BASE  = @json(url('sample-orders'));
  const STORE_URL  = @json(route('sample-orders.store'));
  const SEARCH_URL = @json(url('products/search'));
  const CUST_URL   = @json(route('sample-orders.customerSearch'));
  const USER_URL  = @json(route('sample-orders.userSearch'));
  const CSRF       = document.querySelector('meta[name=csrf-token]')?.content ?? '';

  const $ = (id) => document.getElementById(id);

  const grid = new wwGrid({
    el: $('smpGrid'),
    height: 'fit', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '샘플번호',   name: 'sample_no', width: 150, sortable: true },
      { header: '상태',       name: 'status',    width: 90,  align: 'center', sortable: true },
      { header: '고객',       name: 'customer',  width: 110, sortable: true },
      { header: '받는 사람',  name: 'recipient', width: 100 },
      { header: '연락처',     name: 'mobile',    width: 120 },
      { header: '배송지',     name: 'address',   width: 220 },
      { header: '수량',       name: 'qty',       width: 70,  align: 'right', editor: 'number' },
      { header: '금액',       name: 'amount',    width: 100, align: 'right' },
      {
        // 창고에 넘겼는가. 못 넘긴 건은 눈에 띄어야 다시 보낸다.
        header: '판매주문', name: 'so_no', width: 130, sortable: true,
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          if (v === '실패' || v === '미전달') { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      { header: '주문일',     name: 'order_date', width: 100, sortable: true },
      { header: '등록자',     name: 'creator',    width: 90 },
    ],
    data: @json($gridData),
  });
  window.__smpGrid = grid;

  /* 목록 · 상세 내용 · 신규 등록 세 판을 오간다 */
  window.smpPane = function (which) {
    const map = { list: 'smpPaneList', detail: 'smpPaneDetail', new: 'smpPaneNew' };
    Object.entries(map).forEach(([k, id]) => { $(id).style.display = (k === which) ? '' : 'none'; });
    $('smpTabList').classList.toggle('active', which === 'list');
    $('smpTabDetail').classList.toggle('active', which === 'detail');
    $('smpTabNew').classList.toggle('active', which === 'new');
    if (which === 'new') $('smpRecipient')?.focus();
  };

  /* 행을 더블클릭하면 목록 옆 상세 내용 탭에 제품까지 편다 */
  $('smpGrid').addEventListener('dblclick', async function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row?.id) return;
    await loadDetail(row.id);
    smpPane('detail');
  });

  async function loadDetail(id) {
    try {
      const res = await fetch(SHOW_BASE + '/' + id, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const { head, items } = await res.json();

      $('smpDetailTitle').textContent = head.sample_no + ' · ' + head.type;

      const kv = (k, v) => `<div class="smp-kv"><span>${k}</span><span>${v ?? '-'}</span></div>`;
      $('smpDetailHead').innerHTML =
        kv('상태', head.status)
        + kv('고객', head.customer + (head.customer_kind ? ' · ' + head.customer_kind : ''))
        + kv('받는 사람', head.recipient + (head.mobile && head.mobile !== '-' ? ' · ' + head.mobile : ''))
        + kv('배송지', head.address)
        + kv('요청자', head.requester)
        + kv('주문일', head.order_date + (head.delivery_date ? ' · 배송요청 ' + head.delivery_date : ''))
        + kv('용도', head.purpose)
        + kv('비고', head.note)
        + kv('판매주문', head.so_no
            ? head.so_no + (head.so_status ? ' · ' + head.so_status : '')
            : '<span style="color:#B54708;font-weight:600;">' + (head.error || '아직 넘기지 못했습니다') + '</span>')
        + kv('등록자', head.creator);

      $('smpDetailItems').innerHTML = items.length
        ? items.map(i => `<tr>
            <td style="font-family:monospace;">${esc(i.product_code)}</td>
            <td>${esc(i.product_name)}</td>
            <td class="num">${i.quantity.toLocaleString()}</td>
            <td class="num">${i.unit_price.toLocaleString()}</td>
            <td class="num">${i.amount.toLocaleString()}</td>
          </tr>`).join('')
          + `<tr><td colspan="2" style="font-weight:700;">합계</td>
               <td class="num" style="font-weight:700;">${head.total_qty.toLocaleString()}</td>
               <td></td>
               <td class="num" style="font-weight:700;">${head.total_amount.toLocaleString()}</td></tr>`
        : '<tr><td colspan="5" class="smp-none">담긴 제품이 없습니다.</td></tr>';

      $('smpDetailEmpty').style.display = 'none';
      $('smpDetailBody').style.display  = '';
    } catch (err) {
      $('smpDetailEmpty').textContent = '상세를 불러오지 못했습니다.';
      $('smpDetailEmpty').style.display = '';
      $('smpDetailBody').style.display  = 'none';
    }
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  // ── 고객 조회 (팝업) ──────────────────────────────────
  const modal = new GridModal();
  let custRows = {};   // 고른 뒤 이름 말고 나머지도 써야 해서 들고 있는다

  /* 고객 조회는 누른 칸 옆에 붙는 팝오버로 연다 — 화면 한가운데를 덮으면 지금까지
     적어 둔 것이 가려져, 무엇을 채우던 중이었는지 놓친다. */
  window.smpPickCustomer = function (btn) {
    modal.open({
      title: '고객 조회',
      width: 460,
      height: 340,
      mode: 'popover',
      anchor: btn,
      currentValue: $('smpPatientId').value || null,
      onSearch: async (q) => {
        const res = await fetch(CUST_URL + '?q=' + encodeURIComponent(q ?? ''), {
          headers: { 'Accept': 'application/json' },
        });
        const { rows } = await res.json();
        custRows = {};
        (rows ?? []).forEach(r => { custRows[r.id] = r; });
        return (rows ?? []).map(r => ({
          value: r.id,
          label: r.name + (r.mobile ? ' · ' + r.mobile : ''),
          sub:   r.address || '주소 없음',
        }));
      },
      onConfirm: (value) => {
        const r = custRows[value];
        if (!r) return;
        $('smpPatientId').value = r.id;
        $('smpAccount').value   = r.name;
        $('smpCustKind').textContent = '환자로 이어 둡니다';
        // 배송지는 고객을 고르면 따라온다 — 옮겨 적게 두면 어긋난다
        if (!$('smpRecipient').value.trim()) $('smpRecipient').value = r.name;
        $('smpMobile').value  = r.mobile  || $('smpMobile').value;
        $('smpAddress').value = r.address || $('smpAddress').value;
      },
    });
  };

  /* 요청자 조회 — 고객 조회와 같은 팝오버다. CE-Admin 에 등록된 담당자만 나온다. */
  const reqModal = new GridModal();
  let reqRows = {};

  window.smpPickRequester = function (btn) {
    reqModal.open({
      title: '요청자 조회',
      width: 420,
      height: 340,
      mode: 'popover',
      anchor: btn,
      currentValue: $('smpRequesterId').value || null,
      onSearch: async (q) => {
        const res = await fetch(USER_URL + '?q=' + encodeURIComponent(q ?? ''), {
          headers: { 'Accept': 'application/json' },
        });
        const { rows } = await res.json();
        reqRows = {};
        (rows ?? []).forEach(r => { reqRows[r.id] = r; });
        return (rows ?? []).map(r => ({
          value: r.id,
          label: r.name,
          sub:   [r.role, r.email, r.phone].filter(Boolean).join(' · '),
        }));
      },
      onConfirm: (value) => {
        const r = reqRows[value];
        if (!r) return;
        $('smpRequesterId').value = r.id;
        $('smpRequester').value   = r.name;
      },
    });
  };

  // 고객 이름을 손으로 고치면 환자 연결을 끊는다 — 이름과 연결이 어긋나면 안 된다
  $('smpAccount').addEventListener('input', () => {
    if ($('smpPatientId').value) {
      $('smpPatientId').value = '';
      $('smpCustKind').textContent = '직접 적은 이름입니다';
    }
  });

  // ── 제품 그리드 ──────────────────────────────────────
  let prodRows = {};   // 팝업에서 고른 제품의 나머지 정보

  async function searchProducts(q) {
    const res  = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q ?? ''), {
      headers: { 'Accept': 'application/json' },
    });
    const body = await res.json();
    const rows = body.data ?? [];
    prodRows = {};
    rows.forEach(r => {
      const code = r.item_code ?? r.product_code ?? '';
      prodRows[code] = {
        code,
        name:  r.item_name ?? r.product_name ?? '',
        price: Number(r.price ?? r.insurance_price ?? r.product_price ?? 0),
      };
    });
    return rows.map(r => {
      const code = r.item_code ?? r.product_code ?? '';
      const p    = prodRows[code];
      return { value: code, label: p.name || code, sub: code + ' · ' + p.price.toLocaleString() + '원' };
    });
  }

  /* 제품을 고르면 코드·이름·단가가 함께 정해진다. 한 칸만 채우고 나머지를 사람이
     옮겨 적게 두면 어긋난다 — 고른 것으로 줄 전체를 채운다. */
  function fillProduct(rowIndex, code, _label, g) {
    const p = prodRows[code];
    if (!p) return;
    const row = g.getData()[rowIndex];
    const qty = Number(row.quantity) || 1;
    g.setValue?.(rowIndex, 'product_code', p.code);
    g.setValue?.(rowIndex, 'product_name', p.name);
    g.setValue?.(rowIndex, 'unit_price', p.price);
    g.setValue?.(rowIndex, 'quantity', qty);
    g.setValue?.(rowIndex, 'amount', qty * p.price);
    syncSum();
  }

  /* 제품 조회는 팝오버로 연다 — 누른 칸 옆에 붙어, 어느 줄을 고치던 중이었는지
     화면이 가려지지 않는다. */
  const popupOpts = {
    title: '제품 조회', width: 420, height: 320, mode: 'popover',
    onSearch: searchProducts, onSelect: fillProduct,
  };

  const itemGrid = new wwGrid({
    el: $('smpItemGrid'),
    height: 'auto', editable: true, rowCheckbox: true, rowNumber: true,
    toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '제품코드', name: 'product_code', width: 130, editor: 'popup', popup: popupOpts },
      { header: '제품명',   name: 'product_name', width: 280, editor: 'popup', popup: popupOpts },
      { header: '수량',     name: 'quantity',     width: 90,  editor: 'number', align: 'right', defaultValue: 1 },
      { header: '단가',     name: 'unit_price',   width: 110, editor: 'number', align: 'right', defaultValue: 0 },
      { header: '금액',     name: 'amount',       width: 120, align: 'right' },
    ],
    data: [],
  });
  window.__smpItemGrid = itemGrid;

  window.smpAddRow = function () { itemGrid.addRow({ quantity: 1, unit_price: 0, amount: 0 }); syncSum(); };
  window.smpDelRow = function () { itemGrid.removeCheckedRows(); syncSum(); };

  /* 수량·단가를 고치면 금액이 따라야 한다. 눈에 보이는 숫자끼리 어긋나면 어느 쪽이
     맞는지 알 수 없다. */
  $('smpItemGrid').addEventListener('change', recalc, true);
  $('smpItemGrid').addEventListener('blur', recalc, true);

  function recalc() {
    setTimeout(() => {
      itemGrid.getData().forEach((r, i) => {
        const amt = (Number(r.quantity) || 0) * (Number(r.unit_price) || 0);
        if (Number(r.amount) !== amt) itemGrid.setValue?.(i, 'amount', amt);
      });
      syncSum();
    }, 0);
  }

  function syncSum() {
    const rows = itemGrid.getData().filter(r => r.product_code);
    const qty  = rows.reduce((s, r) => s + (Number(r.quantity) || 0), 0);
    const amt  = rows.reduce((s, r) => s + (Number(r.amount) || 0), 0);
    $('smpSumNote').textContent =
      rows.length + '줄 · ' + qty.toLocaleString() + '개 · ' + amt.toLocaleString() + '원';
  }


  // ── 저장 ─────────────────────────────────────────────
  $('smpForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    /* 코드가 없는 줄은 빈 줄이다 — 더하기만 누르고 두고 간 것이라 보낼 것이 아니다 */
    const items = itemGrid.getData()
      .filter(r => r.product_code)
      .map(r => ({
        product_code: String(r.product_code),
        product_name: String(r.product_name || r.product_code),
        quantity:     Math.max(1, Number(r.quantity) || 1),
        unit_price:   Math.max(0, Number(r.unit_price) || 0),
      }));

    if (!items.length)                 { alert('제품을 담으십시오.'); return; }
    if (!$('smpAccount').value.trim())   { alert('고객을 넣으십시오.'); return; }
    if (!$('smpRecipient').value.trim()) { $('smpRecipient').value = $('smpAccount').value.trim(); }
    if (!$('smpAddress').value.trim())   { alert('주소를 넣으십시오.'); return; }

    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = '저장 중…';

    try {
      const res = await fetch(STORE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          patient_id:     $('smpPatientId').value || null,
          account_name:   $('smpAccount').value.trim(),
          recipient_name: $('smpRecipient').value.trim(),
          mobile:         $('smpMobile').value.trim() || null,
          postcode:       $('smpPostcode').value.trim() || null,
          address:        $('smpAddress').value.trim(),
          address_detail: $('smpAddressDetail').value.trim() || null,
          requester_id:   $('smpRequesterId').value || null,
          // 이름도 함께 보낸다 — 계정이 지워져도 그때 누구였는지는 남아야 한다
          requester_name: $('smpRequester').value.trim() || null,
          order_date:     $('smpOrderDate').value,
          delivery_date:  $('smpDeliveryDate').value || null,
          purpose:        $('smpPurpose').value.trim() || null,
          note:           $('smpNote').value.trim() || null,
          items:          items,
        }),
      });
      const body = await res.json();
      if (!res.ok || !body.success) throw new Error(body.message || 'HTTP ' + res.status);
      location.href = '{{ route('sample-orders.index') }}';
    } catch (err) {
      alert('저장하지 못했습니다: ' + err.message);
      btn.disabled = false;
      btn.textContent = '저장';
    }
  });

  syncSum();
})();
</script>
@endpush
