{{-- 교환·반품·취소 접수 — 목록 옆 탭에서 그대로 쓴다.

     세 자리를 처음부터 모두 보여 준다 — 찾는 자리, 적는 자리, 무엇을 되돌리는지 보는 자리.
     예전에는 주문을 고르기 전까지 아래를 감췄는데, 무엇을 적어야 하는지 미리 볼 수 없어
     고르고 나서야 준비가 안 된 것을 알게 됐다. --}}
<style>
  .rto-wrap { padding: 14px 16px 18px; }
  .rto-sec { margin-bottom: 16px; }
  .rto-sec-hd { font-size: 13px; font-weight: 700; color: var(--gray-1000); margin-bottom: 8px;
                display: flex; align-items: center; gap: 8px; }
  .rto-sec-hd .step { display: inline-flex; align-items: center; justify-content: center;
                      width: 18px; height: 18px; border-radius: 999px; background: var(--primary);
                      color: #fff; font-size: 11px; font-weight: 700; }
  .rto-sec-hd .hint { margin-left: auto; font-size: 11.5px; font-weight: 500; color: var(--gray-700); }

  /* 찾는 자리 — 목록 화면의 검색 카드와 같은 규격 */
  .rto-filter { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;
                padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px;
                background: var(--gray-50); margin-bottom: 10px; }
  .rto-fld { display: flex; flex-direction: column; gap: 4px; }
  .rto-fld label { font-size: 12px; font-weight: 600; color: var(--gray-700); }
  .rto-fld input { height: 32px; width: 150px; }
  .rto-fld.wide input { width: 190px; }

  /* 찾은 주문 — 골라야 아래가 채워진다 */
  .rto-hits { border: 1px solid var(--border); border-radius: 8px; max-height: 190px; overflow-y: auto; }
  .rto-hit { display: flex; gap: 10px; align-items: center; padding: 8px 12px; font-size: 12.5px;
             border-bottom: 1px solid var(--border-light); cursor: pointer; }
  .rto-hit:last-child { border-bottom: none; }
  .rto-hit:hover { background: var(--primary-light); }
  .rto-hit.on { background: var(--primary-light); box-shadow: inset 3px 0 0 var(--primary); }
  .rto-hit .no { font-family: monospace; font-weight: 700; color: var(--primary); width: 110px; flex-shrink: 0; }
  .rto-hit .who { width: 76px; flex-shrink: 0; font-weight: 500; }
  .rto-hit .what { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--gray-700); }
  .rto-hit .amt { width: 90px; text-align: right; font-variant-numeric: tabular-nums; }
  .rto-hit .warn { color: #B54708; font-weight: 700; font-size: 11px; }
  .rto-empty { padding: 14px 12px; font-size: 12.5px; color: var(--gray-700); text-align: center; }

  .rto-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 14px; }
  .rto-f { display: flex; flex-direction: column; gap: 4px; }
  .rto-f.span2 { grid-column: span 2; }
  .rto-f.span4 { grid-column: span 4; }
  .rto-f label { font-size: 12px; font-weight: 600; color: var(--gray-700); }
  .rto-note { font-size: 11.5px; color: var(--gray-700); }
  .rto-only { display: none; }
  .rto-only.on { display: flex; }

  @media (max-width: 1100px) { .rto-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                               .rto-f.span4 { grid-column: span 2; } }
</style>

<form method="POST" action="{{ route('order-returns.store') }}" class="rto-wrap" id="rtoForm">
  @csrf
  <input type="hidden" name="order_id" id="rtoOrderId" value="">

  {{-- ① 원 주문 찾기 --}}
  <div class="rto-sec">
    <div class="rto-sec-hd">
      <span class="step">1</span> 원 주문 찾기
      <span class="hint" id="rtoPickedNote">찾은 주문을 눌러 고르십시오</span>
    </div>

    <div class="rto-filter">
      <div class="rto-fld"><label>환자명</label>
        <input type="text" id="rtoName" class="form-control" maxlength="50" placeholder="이름"></div>
      <div class="rto-fld"><label>생년월일</label>
        <input type="date" id="rtoBirth" class="form-control"></div>
      <div class="rto-fld"><label>전화번호</label>
        <input type="text" id="rtoPhone" class="form-control" maxlength="20" placeholder="010-0000-0000"></div>
      <div class="rto-fld wide"><label>주문번호</label>
        <input type="text" id="rtoNo" class="form-control" maxlength="50" placeholder="주문번호 · 판매주문번호"></div>
      <button type="button" class="ds-btn ds-btn-primary" onclick="rtoFind()">검색</button>
      <span class="rto-note" id="rtoFindNote"></span>
      {{-- 끝내는 단추는 찾는 자리와 나란히 둔다. 아래에 두면 제품 목록이 길어질수록
           멀어져, 접수하려고 화면을 끝까지 굴려 내려야 한다. --}}
      <span style="margin-left:auto;display:flex;gap:8px;">
        <button type="button" class="ds-btn" onclick="rtnPanel('list')">종료</button>
        <button type="submit" class="ds-btn ds-btn-primary">접수</button>
      </span>
    </div>

    <div class="rto-hits" id="rtoHits">
      <div class="rto-empty">환자나 주문번호로 찾으십시오. 조건 없이 눌러도 최근 주문을 보여 줍니다.</div>
    </div>
  </div>

  {{-- ② 신청 내용 — 주문을 고르기 전에도 보인다 --}}
  <div class="rto-sec">
    <div class="rto-sec-hd"><span class="step">2</span> 신청 내용</div>
    <div class="rto-grid">
      <div class="rto-f">
        <label>종류</label>
        <select name="type" id="rtoType" class="form-control form-select" required>
          @foreach(\App\Models\OrderReturn::TYPES as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="rto-f">
        <label>사유</label>
        <select name="reason_code" id="rtoReason" class="form-control form-select" required>
          @foreach(\App\Models\OrderReturn::REASONS as $k => $r)
            <option value="{{ $k }}" data-burden="{{ $r['burden'] }}">{{ $r['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="rto-f">
        <label>배송비 부담</label>
        <select name="shipping_burden" id="rtoBurden" class="form-control form-select">
          <option value="">사유에 따름</option>
          @foreach(\App\Models\OrderReturn::BURDENS as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
        <span class="rto-note" id="rtoBurdenNote"></span>
      </div>
      <div class="rto-f rto-only" id="rtoCollectWrap">
        <label>수거 방법</label>
        <select name="collect_method" class="form-control form-select">
          <option value="">선택</option>
          @foreach(\App\Models\OrderReturn::COLLECT_METHODS as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="rto-f span4">
        <label>상세 사유</label>
        <input type="text" name="reason_text" class="form-control" maxlength="500"
               placeholder="고객이 말한 내용을 그대로 적어 두면 나중에 판단이 쉽습니다">
      </div>
    </div>

    {{-- 교환 --}}
    <div class="rto-only" id="rtoExchangeSec" style="display:none;flex-direction:column;gap:8px;margin-top:12px;">
      <div class="rto-grid">
        <div class="rto-f span2">
          <label>바꿔 보낼 제품</label>
          <input type="text" name="exchange_product" class="form-control" maxlength="200"
                 placeholder="사이즈 등">
        </div>
        <div class="rto-f">
          <label>수량</label>
          <input type="number" name="exchange_quantity" class="form-control" min="1">
        </div>
        <div class="rto-f span4">
          <label>재배송지</label>
          <input type="text" name="reship_address" id="rtoReship" class="form-control" maxlength="300"
                 placeholder="비우면 원 주문 배송지로 보냅니다">
        </div>
      </div>
    </div>

    {{-- 반품 · 취소 --}}
    <div class="rto-only" id="rtoRefundSec" style="display:block;margin-top:12px;">
      <div class="rto-grid">
        <div class="rto-f">
          <label>환불 수단</label>
          <select name="refund_method" id="rtoRefundMethod" class="form-control form-select">
            <option value="">선택</option>
            @foreach(\App\Models\OrderReturn::REFUND_METHODS as $k => $label)
              <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="rto-f">
          <label>환불 금액</label>
          <input type="number" name="refund_amount" id="rtoRefundAmount" class="form-control" min="0">
        </div>
        <div class="rto-f rto-only" id="rtoAccountWrap">
          <label>환불 계좌</label>
          <input type="text" name="refund_bank" class="form-control" maxlength="50" placeholder="은행">
        </div>
        <div class="rto-f rto-only" id="rtoAccountWrap2">
          <label>계좌번호 · 예금주</label>
          <div style="display:flex;gap:6px;">
            <input type="text" name="refund_account" class="form-control" maxlength="50" placeholder="계좌번호">
            <input type="text" name="refund_holder" class="form-control" maxlength="50"
                   style="max-width:100px;" placeholder="예금주">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ③ 주문 제품 — 무엇을 되돌리는지 보는 자리 --}}
  <div class="rto-sec">
    <div class="rto-sec-hd">
      <span class="step">3</span> 주문 제품
      <span class="hint" id="rtoItemNote">주문을 고르면 그 주문의 제품이 나옵니다</span>
    </div>
    <div id="rtoItemGrid"></div>
  </div>
</form>

{{-- 스크립트는 본문이 아니라 스크립트 자리로 보낸다.
     본문 안에 두면 레이아웃이 wwGrid 를 싣기 전에 돌아 「wwGrid is not defined」로 죽는다. --}}
@push('scripts')
<script>
(function () {
  const SEARCH_URL = @json(route('order-returns.orderSearch'));
  const BURDENS    = @json(\App\Models\OrderReturn::BURDENS);

  const $ = (id) => document.getElementById(id);
  let rows = [];      // 마지막 조회 결과
  let picked = null;

  /* 주문 제품은 처음부터 자리를 잡아 둔다. 빈 표라도 보이면 무엇이 채워질 자리인지
     알 수 있고, 고른 뒤에 갑자기 나타나 아래가 밀리지 않는다. */
  const itemGrid = new wwGrid({
    el: $('rtoItemGrid'),
    height: 'auto', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
    columns: [
      { header: '제품코드', name: 'product_code', width: 120 },
      { header: '제품명',   name: 'product_name', width: 300 },
      { header: '수량',     name: 'quantity',     width: 80,  align: 'right' },
      { header: '단가',     name: 'unit_price',   width: 110, align: 'right' },
      { header: '환자부담', name: 'copay',        width: 110, align: 'right' },
    ],
    data: [],
  });
  window.__rtoItemGrid = itemGrid;

  /* 원 주문 찾기.
     조건 없이 눌러도 최근 것을 보여 준다 — 방금 만든 주문을 되돌리는 일이 잦아
     그때는 칠 검색어가 없다. */
  window.rtoFind = async function () {
    const q = new URLSearchParams({
      patient_name: $('rtoName').value.trim(),
      birth_date:   $('rtoBirth').value,
      phone:        $('rtoPhone').value.trim(),
      order_no:     $('rtoNo').value.trim(),
    });

    $('rtoFindNote').textContent = '찾는 중…';
    try {
      const res = await fetch(SEARCH_URL + '?' + q.toString(), { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      rows = (await res.json()).rows ?? [];
      drawHits();
      $('rtoFindNote').textContent = rows.length ? rows.length + '건' : '';

      // 한 건이면 고르는 수고를 덜어 준다
      if (rows.length === 1) pick(0);
    } catch (e) {
      $('rtoFindNote').textContent = '찾지 못했습니다';
    }
  };

  function drawHits() {
    const box = $('rtoHits');
    box.innerHTML = '';

    if (!rows.length) {
      box.innerHTML = '<div class="rto-empty">맞는 주문이 없습니다. 조건을 줄여 보십시오.</div>';
      return;
    }

    rows.forEach((r, i) => {
      const el = document.createElement('div');
      el.className = 'rto-hit' + (picked === i ? ' on' : '');
      el.dataset.idx = i;

      const no   = mk('span', 'no',   r.order_no);
      const who  = mk('span', 'who',  r.patient + (r.birth ? '' : ''));
      const what = mk('span', 'what', [r.birth, r.phone, r.product].filter(Boolean).join(' · '));
      const amt  = mk('span', 'amt',  (r.amount || 0).toLocaleString() + '원');
      el.append(no, who, what, amt);

      if (r.returns > 0) el.appendChild(mk('span', 'warn', '접수 ' + r.returns + '건'));

      el.addEventListener('click', () => pick(i));
      box.appendChild(el);
    });
  }

  function mk(tag, cls, text) {
    const e = document.createElement(tag);
    e.className = cls;
    e.textContent = text ?? '';
    return e;
  }

  function pick(i) {
    picked = i;
    const r = rows[i];

    $('rtoOrderId').value = r.id;
    $('rtoPickedNote').textContent =
      '고른 주문 ' + r.order_no + ' · ' + r.patient
      + (r.so_no ? ' · ' + r.so_no : '') + ' · ' + r.status;

    // 환불 금액과 재배송지는 원 주문에서 끌어 온다 — 대개 그대로다
    if (!$('rtoRefundAmount').value) $('rtoRefundAmount').value = r.amount || '';
    $('rtoReship').placeholder = r.address || '비우면 원 주문 배송지로 보냅니다';

    itemGrid.setData(r.items ?? []);
    $('rtoItemNote').textContent = (r.items?.length ?? 0) + '개 품목 · ' + r.order_no;

    drawHits();
  }

  /* 종류에 따라 물을 것이 다르다. 취소는 보낸 물건이 없어 수거를 묻지 않고,
     교환은 환불이 아니라 다시 보낼 것을 묻는다. */
  function syncType() {
    const t = $('rtoType').value;
    $('rtoCollectWrap').classList.toggle('on', t !== 'cancel');
    $('rtoExchangeSec').style.display = t === 'exchange' ? 'block' : 'none';
    $('rtoRefundSec').style.display   = t === 'exchange' ? 'none'  : 'block';
    syncRefundMethod();
  }

  function syncRefundMethod() {
    const on = $('rtoRefundMethod').value === 'account';
    $('rtoAccountWrap').classList.toggle('on', on);
    $('rtoAccountWrap2').classList.toggle('on', on);
  }

  /* 사유가 정해지면 누가 무는지도 정해진다. 담당자마다 다르게 안내하지 않도록. */
  function syncBurden() {
    const b = $('rtoReason').selectedOptions[0]?.dataset.burden || '';
    $('rtoBurdenNote').textContent = b ? '사유에 따라 ' + (BURDENS[b] ?? b) : '정해진 것이 없습니다';
  }

  $('rtoType').addEventListener('change', syncType);
  $('rtoReason').addEventListener('change', syncBurden);
  $('rtoRefundMethod').addEventListener('change', syncRefundMethod);
  ['rtoName', 'rtoBirth', 'rtoPhone', 'rtoNo'].forEach(id =>
    $(id).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); rtoFind(); } }));

  $('rtoForm').addEventListener('submit', (e) => {
    if (!$('rtoOrderId').value) { e.preventDefault(); alert('원 주문을 먼저 고르십시오.'); }
  });

  syncBurden();
  syncType();
})();
</script>
@endpush
