{{-- 교환·반품·취소 접수 — 목록 옆 탭에서 그대로 쓴다.
     원 주문은 셀렉트에 통째로 붓지 않고 찾아서 고른다. 주문이 쌓이면 셀렉트로는
     찾을 수 없고, 목록 밖의 주문은 아예 고를 수도 없었다. --}}
<style>
  .rto-wrap { padding: 14px 16px 18px; }
  .rto-sec { margin-bottom: 16px; }
  .rto-sec-hd { font-size: 13px; font-weight: 700; color: var(--gray-1000); margin-bottom: 8px;
                display: flex; align-items: center; gap: 8px; }
  .rto-sec-hd .step { display: inline-flex; align-items: center; justify-content: center;
                      width: 18px; height: 18px; border-radius: 999px; background: var(--primary);
                      color: #fff; font-size: 11px; font-weight: 700; }
  .rto-find { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
  .rto-find input { max-width: 320px; }

  /* 찾은 주문 — 골라야 다음으로 간다. 목록은 다른 화면과 같은 wwGrid 로 그린다.
     상자(테두리·모서리·내부 스크롤)는 그리드의 .cg-wrap 이 갖는다 — 여기서 또 그리면 두 겹이 된다.
     다만 레이아웃의 `.ds-grid-card .cg-wrap { border:0 }` 이 이 자리까지 걷어 간다.
     그 규칙은 그리드가 카드 전체를 채울 때 이야기고, 이 목록은 폼 한가운데 놓인
     작은 상자다. 테두리를 되돌려 준다(렌더로 확인 — 안 주면 상자가 아예 사라진다). */
  /* 다섯 줄(머리 45.3 + 본문 41.8×5 + 테두리 2)까지만 서고 넘으면 상자 안에서 구른다.
     .ds-grid-card .cg-wrap 이 전역에서 테두리를 걷어 가므로 여기서 도로 그린다. */
  .rto-hits .cg-wrap { border: 1px solid var(--border); border-radius: 12px; max-height: 257px; }
  .rto-hits .cg-cell-inner { cursor: pointer; }
  .rto-hits .cg-cell-inner[data-col-name="order_no"] {
    font-weight: 700; color: var(--primary); font-variant-numeric: tabular-nums; }
  /* 이미 되돌린 적이 있는 주문. 쓰던 호박색은 DS 밖 값이라 alert 계열로 옮겼다. */
  .rto-hit-warn { color: var(--alert-500); font-weight: 700; }
  /* 0건일 때는 그리드를 숨기므로 상자를 이쪽이 대신 그린다 */
  .rto-empty { padding: 14px 12px; font-size: 12px; line-height: 19px; color: var(--gray-700);
               text-align: center; border: 1px solid var(--border); border-radius: 12px; }

  /* 고른 주문 */
  .rto-picked { display: none; align-items: center; gap: 10px; padding: 10px 12px; font-size: 13px;
                border: 1px solid var(--primary); background: var(--primary-light); border-radius: 8px; }
  .rto-picked.on { display: flex; }
  .rto-picked .no { font-weight: 700; color: var(--primary); font-variant-numeric: tabular-nums; }
  .rto-picked .meta { flex: 1; color: var(--gray-700); font-size: 12.5px; }

  .rto-body { display: none; }
  .rto-body.on { display: block; }

  .rto-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 14px; }
  .rto-f { display: flex; flex-direction: column; gap: 4px; }
  .rto-f.span2 { grid-column: span 2; }
  .rto-f.span4 { grid-column: span 4; }
  .rto-f label { font-size: 12px; font-weight: 600; color: var(--gray-700); }
  .rto-note { font-size: 11.5px; color: var(--gray-700); }
  .rto-only { display: none; }
  .rto-only.on { display: flex; }

  .rto-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;
                 padding-top: 12px; border-top: 1px solid var(--border); }
  @media (max-width: 1100px) { .rto-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                               .rto-f.span4 { grid-column: span 2; } }
</style>

<form method="POST" action="{{ route('order-returns.store') }}" class="rto-wrap" id="rtoForm">
  @csrf
  <input type="hidden" name="order_id" id="rtoOrderId" value="">

  {{-- ① 원 주문 찾기 --}}
  <div class="rto-sec">
    <div class="rto-sec-hd"><span class="step">1</span> 원 주문</div>
    <div class="rto-find">
      <input type="text" id="rtoQ" class="form-control"
             placeholder="주문번호ㆍ환자명ㆍ제품명ㆍ판매주문번호">
      <button type="button" class="ds-btn ds-btn-primary" onclick="rtoFind()">조회</button>
      <span class="rto-note" id="rtoFindNote"></span>
    </div>
    <div class="rto-hits" id="rtoHits" style="display:none;">
      <div id="rtoHitsGrid"></div>
      <div class="rto-empty" id="rtoHitsEmpty" style="display:none;">맞는 주문이 없습니다. 검색어를 줄여 보십시오.</div>
    </div>
    <div class="rto-picked" id="rtoPicked">
      <span class="no" id="rtoPickedNo"></span>
      <span class="meta" id="rtoPickedMeta"></span>
      <button type="button" class="ds-btn" onclick="rtoUnpick()">다시 고르기</button>
    </div>
  </div>

  {{-- ② 고른 뒤에만 나머지를 보여 준다. 주문 없이 채워 봐야 저장되지 않는다. --}}
  <div class="rto-body" id="rtoBody">
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
    </div>

    {{-- 교환 --}}
    <div class="rto-sec rto-only" id="rtoExchangeSec" style="display:none;">
      <div class="rto-sec-hd"><span class="step">3</span> 다시 보낼 것</div>
      <div class="rto-grid">
        <div class="rto-f span2">
          <label>제품</label>
          <input type="text" name="exchange_product" class="form-control" maxlength="200"
                 placeholder="바꿔 보낼 제품(사이즈 등)">
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
    <div class="rto-sec rto-only" id="rtoRefundSec">
      <div class="rto-sec-hd"><span class="step">3</span> 환불</div>
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

    <div class="rto-actions">
      <button type="button" class="ds-btn" onclick="rtnPanel('list')">그만두기</button>
      <button type="submit" class="ds-btn ds-btn-primary">접수</button>
    </div>
  </div>
</form>

<script>
(function () {
  const SEARCH_URL = @json(route('order-returns.orderSearch'));
  const BURDENS    = @json(\App\Models\OrderReturn::BURDENS);
  let picked = null;

  const $ = (id) => document.getElementById(id);

  /* 원 주문 찾기. 비워 두고 눌러도 최근 것을 보여 준다 — 방금 만든 주문을
     되돌리는 일이 잦아 그때 검색어를 칠 일이 없다. */
  window.rtoFind = async function () {
    const q = $('rtoQ').value.trim();
    $('rtoFindNote').textContent = '찾는 중…';
    try {
      const res = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
        headers: { 'Accept': 'application/json' },
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const { rows } = await res.json();
      drawHits(rows);
      $('rtoFindNote').textContent = rows.length ? rows.length + '건' : '';
    } catch (e) {
      $('rtoFindNote').textContent = '찾지 못했습니다';
    }
  };

  /* 목록은 다른 화면과 같은 wwGrid 로 그린다.
     다만 이 <script> 는 레이아웃의 wwGrid.js 보다 먼저 실린다. 여기서 바로
     new wwGrid 를 부르면 ReferenceError 로 이 IIFE 가 통째로 죽어 rtoFind·syncType
     까지 사라진다. 그래서 처음 조회할 때 — 스크립트가 다 실린 뒤에 — 만든다. */
  let hitsGrid = null;

  function ensureHitsGrid() {
    if (hitsGrid) return hitsGrid;

    hitsGrid = new wwGrid({
      el: $('rtoHitsGrid'),
      // 숫자 높이 — 이 폼은 숨은 탭 안에서 만들어져 'fit' 은 상자가 무너진다.
      // 머리행 45.3 + 본문행 41.8 × 5 = 254.3 에 테두리 두 줄을 더해 257px.
      // (254 로는 3px 이 모자라 다섯 번째 줄에서 스크롤바가 났다.)
      // 숫자를 주면 wwGrid 가 래퍼에 height 를 못박아(wwGrid.js:818) 한 건만 찾아도
      // 257px 상자가 서서 168px 이 빈 흰칸으로 남는다. 숫자가 아니면 그 줄을 건너뛰므로
      // 높이는 내용이 정하고, 다섯 줄 상한만 CSS 의 max-height 로 건다.
      // 행 번호는 켠다 — 이 집 그리드 서른셋이 모두 켜 두었고, 바로 옆 탭의
      // 목록(#rtnGrid)에도 있다. 한쪽만 없으면 탭을 오갈 때 표가 어긋나 보인다.
      // 체크박스는 끈다 — pick() 은 행 하나만 받는다(여럿 고르는 시늉이 된다).
      height: 'auto', editable: false, rowCheckbox: false, rowNumber: true,
      toolbar: false, summary: false, footer: false,
      columns: [
        { header: '주문번호',     name: 'order_no',    width: 120, sortable: false },
        { header: '환자명',       name: 'patient',     width: 90,  sortable: false },
        { header: '제품명',       name: 'product',     sortable: false },
        { header: '판매주문번호', name: 'so_no',       width: 130, sortable: false },
        // align:'right' 는 이웃 목록(환불금액·총금액)이 쓰는 대로 적어 둔다.
        // 지금 화면에서는 wwGrid.css:178 이 이 옵션을 시안값(왼쪽)으로 되돌린다.
        { header: '금액',         name: 'amount_text', width: 100, align: 'right', sortable: false },
        {
          // 이미 되돌린 적이 있으면 알려 준다 — 같은 주문을 두 번 접수하는 일이 있다
          header: '기존 접수', name: 'return_text', width: 90, sortable: false,
          renderer: (v) => {
            const el = document.createElement('span');
            el.textContent = v ?? '';
            if (v) el.className = 'rto-hit-warn';
            return el;
          },
        },
      ],
      data: [],
    });

    /* 한 번 눌러 고른다 — 지금까지 쓰던 손버릇 그대로다.
       잘못 골라도 바로 옆 「다시 고르기」로 되돌린다. */
    $('rtoHitsGrid').addEventListener('click', (e) => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = hitsGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row) pick(row);
    });

    return hitsGrid;
  }

  function drawHits(rows) {
    $('rtoHits').style.display = '';

    // 그리드에는 빈 상태가 없다 — 0건이면 그리드를 숨기고 안내문을 대신 보인다
    const empty = !rows.length;
    $('rtoHitsEmpty').style.display = empty ? '' : 'none';
    $('rtoHitsGrid').style.display  = empty ? 'none' : '';
    if (empty) return;

    // 원본 아홉 필드를 그대로 둔 채 보여 줄 두 칸만 얹는다 — pick(r) 이 원본을 읽는다
    ensureHitsGrid().setData(rows.map((r) => Object.assign({}, r, {
      amount_text: (r.amount || 0).toLocaleString() + '원',
      return_text: r.returns > 0 ? '접수 ' + r.returns + '건' : '',
    })));
  }

  function pick(r) {
    picked = r;
    $('rtoOrderId').value = r.id;
    $('rtoPickedNo').textContent = r.order_no;
    $('rtoPickedMeta').textContent =
      r.patient + ' · ' + r.product + ' · ' + (r.amount || 0).toLocaleString() + '원'
      + (r.so_no ? ' · ' + r.so_no : '') + ' · ' + r.status;
    $('rtoPicked').classList.add('on');
    $('rtoHits').style.display = 'none';
    $('rtoBody').classList.add('on');

    // 환불 금액과 재배송지는 원 주문에서 끌어 온다 — 대개 그대로다
    if (!$('rtoRefundAmount').value) $('rtoRefundAmount').value = r.amount || '';
    $('rtoReship').placeholder = r.address || '비우면 원 주문 배송지로 보냅니다';
    syncType();
  }

  window.rtoUnpick = function () {
    picked = null;
    $('rtoOrderId').value = '';
    $('rtoPicked').classList.remove('on');
    $('rtoBody').classList.remove('on');
    $('rtoHits').style.display = '';
    $('rtoQ').focus();
  };

  /* 종류에 따라 물을 것이 다르다. 취소는 보낸 물건이 없어 수거를 묻지 않고,
     교환은 환불이 아니라 다시 보낼 것을 묻는다. */
  function syncType() {
    const t = $('rtoType').value;
    $('rtoCollectWrap').classList.toggle('on', t !== 'cancel');
    $('rtoExchangeSec').style.display = t === 'exchange' ? '' : 'none';
    $('rtoRefundSec').style.display   = t === 'exchange' ? 'none' : '';
    syncRefundMethod();
  }

  function syncRefundMethod() {
    const on = $('rtoRefundMethod').value === 'account';
    $('rtoAccountWrap').classList.toggle('on', on);
    $('rtoAccountWrap2').classList.toggle('on', on);
  }

  /* 사유가 정해지면 누가 무는지도 정해진다. 담당자마다 다르게 안내하지 않도록. */
  function syncBurden() {
    const opt = $('rtoReason').selectedOptions[0];
    const b   = opt?.dataset.burden || '';
    $('rtoBurdenNote').textContent = b ? '사유에 따라 ' + (BURDENS[b] ?? b) : '정해진 것이 없습니다';
  }

  $('rtoType').addEventListener('change', syncType);
  $('rtoReason').addEventListener('change', syncBurden);
  $('rtoRefundMethod').addEventListener('change', syncRefundMethod);
  $('rtoQ').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); rtoFind(); } });

  $('rtoForm').addEventListener('submit', (e) => {
    if (!$('rtoOrderId').value) { e.preventDefault(); alert('원 주문을 먼저 고르십시오.'); }
  });

  syncBurden();
  syncType();
})();
</script>
