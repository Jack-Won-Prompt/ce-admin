{{-- resources/views/sample-orders/index.blade.php --}}
@extends('layouts.app')

@section('title', 'CE 샘플주문')
@section('page-title', 'CE 샘플주문')
@section('breadcrumb', '홈 / 주문 / CE 샘플주문')

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
  .smp-hit { display: flex; gap: 10px; align-items: center; padding: 7px 12px; font-size: 12.5px;
             border-bottom: 1px solid var(--border-light); cursor: pointer; }
  .smp-hit:last-child { border-bottom: none; }
  .smp-hit:hover { background: var(--primary-light); }
  .smp-hit .code { width: 90px; flex-shrink: 0; font-family: monospace; font-weight: 700; color: var(--primary); }
  .smp-hit .name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .smp-hit .price { width: 90px; text-align: right; font-variant-numeric: tabular-nums; }
  .smp-empty { padding: 12px; font-size: 12.5px; color: var(--gray-700); text-align: center; }

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

@php $curType = request('type'); @endphp
<div class="ds-chips">
  <a href="{{ route('sample-orders.index', request()->except(['type','page'])) }}"
     class="ds-chip {{ !$curType ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $counts->sum() }}</span>
  </a>
  @foreach(\App\Models\SampleOrder::TYPE_SHORT as $code => $label)
    <a href="{{ route('sample-orders.index', array_merge(request()->except(['type','page']), ['type' => $code])) }}"
       class="ds-chip {{ $curType === (string) $code ? 'active' : '' }}">
      {{ $label }}
      @if(($counts[$code] ?? 0) > 0)<span class="ds-chip-count">{{ $counts[$code] }}</span>@endif
    </a>
  @endforeach
</div>

<form method="GET" action="{{ route('sample-orders.index') }}" class="ds-filter-card">
  @if($curType)<input type="hidden" name="type" value="{{ $curType }}">@endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="샘플번호 · 거래처 · 받는 사람 · 판매주문번호">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">주문일</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(\App\Models\SampleOrder::STATUS_LABELS as $k => $meta)
          <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $meta[0] }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request()->hasAny(['q','status','date_from','date_to']))
      <a href="{{ route('sample-orders.index', array_filter(['type' => $curType])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 접수는 찾는 일과 나란히 둔다 --}}
    <button type="button" class="ds-btn ds-btn-primary" onclick="smpPane('new')">
      <i class="bx bx-plus"></i> 신규
    </button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ $total }}</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint" id="smpHint">행을 <b>더블클릭</b>하면 상세보기 탭에서 제품을 확인합니다.</span>
      <button type="button" class="ds-btn" onclick="window.__smpGrid?.downloadExcel()">엑셀 저장</button>
    </div>
  </div>

  <div class="pnl-tabs">
    <button type="button" id="smpTabList"   class="pnl-tab active" onclick="smpPane('list')">조회 결과</button>
    <button type="button" id="smpTabDetail" class="pnl-tab" onclick="smpPane('detail')">상세보기</button>
    <button type="button" id="smpTabNew"    class="pnl-tab" onclick="smpPane('new')">신규등록</button>
  </div>

  <div class="ds-grid-card" id="smpPaneList">
    <div id="smpGrid"></div>
  </div>

  {{-- 상세보기 — 머리 정보와 제품 목록 --}}
  <div class="ds-grid-card" id="smpPaneDetail" style="display:none;">
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

  {{-- 신규등록 --}}
  <div class="ds-grid-card" id="smpPaneNew" style="display:none;">
    <form class="smp-pane" id="smpForm">
      <div class="smp-sec">
        <div class="smp-sec-hd"><span class="step">1</span> 받는 곳</div>
        <div class="smp-grid">
          <div class="smp-f">
            <label>유형</label>
            <select id="smpType" class="form-control form-select">
              @foreach(\App\Models\SampleOrder::TYPES as $code => $label)
                <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="smp-f">
            <label>거래처</label>
            <input type="text" id="smpAccount" class="form-control" maxlength="100" placeholder="병원·대리점 등">
          </div>
          <div class="smp-f">
            <label>받는 사람 *</label>
            <input type="text" id="smpRecipient" class="form-control" maxlength="100">
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
        <div class="smp-sec-hd"><span class="step">2</span> 제품</div>
        <div class="smp-find">
          <input type="text" id="smpQ" class="form-control" placeholder="제품명 또는 코드">
          <button type="button" class="ds-btn ds-btn-primary" onclick="smpFind()">조회</button>
          <span class="ds-grid-hint" id="smpFindNote"></span>
        </div>
        <div class="smp-hits" id="smpHits" style="display:none;"></div>
        <table class="smp-items">
          <thead>
            <tr><th style="width:110px;">제품코드</th><th>제품명</th>
                <th class="num" style="width:90px;">수량</th>
                <th class="num" style="width:110px;">단가</th>
                <th class="num" style="width:110px;">금액</th>
                <th style="width:40px;"></th></tr>
          </thead>
          <tbody id="smpItems"></tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="font-weight:700;">합계</td>
              <td class="num" id="smpSumQty" style="font-weight:700;">0</td>
              <td></td>
              <td class="num" id="smpSumAmount" style="font-weight:700;">0</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <div class="smp-none" id="smpItemsNone">제품을 찾아서 담으십시오.</div>
      </div>

      <div class="smp-actions">
        <button type="button" class="ds-btn" onclick="smpPane('list')">그만두기</button>
        <button type="submit" class="ds-btn ds-btn-primary">저장</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const SHOW_BASE  = @json(url('sample-orders'));
  const STORE_URL  = @json(route('sample-orders.store'));
  const SEARCH_URL = @json(url('products/search'));
  const CSRF       = document.querySelector('meta[name=csrf-token]')?.content ?? '';

  const $ = (id) => document.getElementById(id);

  const grid = new wwGrid({
    el: $('smpGrid'),
    height: 'fit', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
    columns: [
      { header: '샘플번호',   name: 'sample_no', width: 150, sortable: true },
      { header: '유형',       name: 'type',      width: 70,  align: 'center', sortable: true },
      { header: '상태',       name: 'status',    width: 90,  align: 'center', sortable: true },
      { header: '거래처',     name: 'account',   width: 140 },
      { header: '받는 사람',  name: 'recipient', width: 100 },
      { header: '연락처',     name: 'mobile',    width: 120 },
      { header: '배송지',     name: 'address',   width: 220 },
      { header: '수량',       name: 'qty',       width: 70,  align: 'right' },
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

  /* 목록 · 상세보기 · 신규등록 세 판을 오간다 */
  window.smpPane = function (which) {
    const map = { list: 'smpPaneList', detail: 'smpPaneDetail', new: 'smpPaneNew' };
    Object.entries(map).forEach(([k, id]) => { $(id).style.display = (k === which) ? '' : 'none'; });
    $('smpTabList').classList.toggle('active', which === 'list');
    $('smpTabDetail').classList.toggle('active', which === 'detail');
    $('smpTabNew').classList.toggle('active', which === 'new');
    $('smpHint').style.visibility = (which === 'list') ? '' : 'hidden';
    if (which === 'new') $('smpRecipient')?.focus();
  };

  /* 행을 더블클릭하면 목록 옆 상세보기 탭에 제품까지 편다 */
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
        + kv('거래처', head.account)
        + kv('받는 사람', head.recipient + (head.mobile && head.mobile !== '-' ? ' · ' + head.mobile : ''))
        + kv('배송지', head.address)
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

  // ── 제품 담기 ────────────────────────────────────────
  let items = [];

  window.smpFind = async function () {
    const q = $('smpQ').value.trim();
    if (!q) { $('smpFindNote').textContent = '검색어를 넣으십시오'; return; }
    $('smpFindNote').textContent = '찾는 중…';
    try {
      const res  = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
      const body = await res.json();
      const rows = body.data ?? [];
      drawHits(rows);
      $('smpFindNote').textContent = rows.length ? rows.length + '건' : '';
    } catch (e) {
      $('smpFindNote').textContent = '찾지 못했습니다';
    }
  };

  function drawHits(rows) {
    const box = $('smpHits');
    box.innerHTML = '';
    box.style.display = '';

    if (!rows.length) {
      box.innerHTML = '<div class="smp-empty">맞는 제품이 없습니다.</div>';
      return;
    }

    rows.forEach(r => {
      const code  = r.item_code ?? r.product_code ?? '';
      const name  = r.item_name ?? r.product_name ?? '';
      const price = Number(r.price ?? r.insurance_price ?? r.product_price ?? 0);

      const el = document.createElement('div');
      el.className = 'smp-hit';
      el.innerHTML = `<span class="code">${esc(code)}</span>`
        + `<span class="name">${esc(name)}</span>`
        + `<span class="price">${price.toLocaleString()}원</span>`;
      el.addEventListener('click', () => addItem(code, name, price));
      box.appendChild(el);
    });
  }

  function addItem(code, name, price) {
    // 같은 제품을 다시 담으면 수량만 올린다 — 같은 줄이 둘로 갈리면 세기 어렵다
    const hit = items.find(i => i.product_code === code);
    if (hit) { hit.quantity += 1; }
    else { items.push({ product_code: code, product_name: name, quantity: 1, unit_price: price }); }
    $('smpHits').style.display = 'none';
    $('smpQ').value = '';
    drawItems();
  }

  function drawItems() {
    const body = $('smpItems');
    body.innerHTML = items.map((i, idx) => `<tr>
        <td style="font-family:monospace;">${esc(i.product_code)}</td>
        <td>${esc(i.product_name)}</td>
        <td class="num"><input type="number" min="1" value="${i.quantity}" data-idx="${idx}" data-f="quantity"></td>
        <td class="num"><input type="number" min="0" value="${i.unit_price}" data-idx="${idx}" data-f="unit_price"></td>
        <td class="num">${(i.quantity * i.unit_price).toLocaleString()}</td>
        <td><span class="del" data-del="${idx}">×</span></td>
      </tr>`).join('');

    $('smpItemsNone').style.display = items.length ? 'none' : '';
    $('smpSumQty').textContent    = items.reduce((s, i) => s + i.quantity, 0).toLocaleString();
    $('smpSumAmount').textContent = items.reduce((s, i) => s + i.quantity * i.unit_price, 0).toLocaleString();
  }

  $('smpItems').addEventListener('input', (e) => {
    const t = e.target;
    if (!t.dataset.f) return;
    const v = Math.max(t.dataset.f === 'quantity' ? 1 : 0, parseInt(t.value || '0', 10));
    items[parseInt(t.dataset.idx, 10)][t.dataset.f] = v;
    drawItems();
  });

  $('smpItems').addEventListener('click', (e) => {
    const idx = e.target.dataset?.del;
    if (idx === undefined) return;
    items.splice(parseInt(idx, 10), 1);
    drawItems();
  });

  $('smpQ').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); smpFind(); } });

  // ── 저장 ─────────────────────────────────────────────
  $('smpForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!items.length)                 { alert('제품을 담으십시오.'); return; }
    if (!$('smpRecipient').value.trim()) { alert('받는 사람을 넣으십시오.'); return; }
    if (!$('smpAddress').value.trim())   { alert('주소를 넣으십시오.'); return; }

    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = '저장 중…';

    try {
      const res = await fetch(STORE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          type:           $('smpType').value,
          account_name:   $('smpAccount').value.trim() || null,
          recipient_name: $('smpRecipient').value.trim(),
          mobile:         $('smpMobile').value.trim() || null,
          postcode:       $('smpPostcode').value.trim() || null,
          address:        $('smpAddress').value.trim(),
          address_detail: $('smpAddressDetail').value.trim() || null,
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

  drawItems();
})();
</script>
@endpush
