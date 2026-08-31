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
  .rto-sec-hd .hint { margin-left: auto; font-size: 11px; font-weight: 500; color: var(--gray-700); }

  /* 찾는 자리 — 목록 화면의 검색 카드와 같은 규격 */
  .rto-filter { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;
                padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px;
                background: var(--gray-50); margin-bottom: 10px; }
  .rto-fld { display: flex; flex-direction: column; gap: 4px; }
  .rto-fld label { font-size: 12px; font-weight: 500; color: var(--gray-700); }
  .rto-fld input { height: 32px; width: 150px; }
  .rto-fld.wide input { width: 190px; }

  /* 물을 것을 한 줄에 늘어놓는다 — 종류·사유·주문번호·주문일. 줄이 나뉘면
     아래로 눈이 오르내려야 하고, 무엇까지 적었는지 놓친다.
     상세 사유만 아래에 넓게 둔다. 한 칸으로는 문장이 안 들어간다. */
  .rto-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 14px; }
  .rto-f { display: flex; flex-direction: column; gap: 4px; }
  .rto-f.span2 { grid-column: span 2; }
  .rto-f.span4 { grid-column: 1 / -1; }
  .rto-f label { font-size: 12px; font-weight: 500; color: var(--gray-700); }
  .rto-note { font-size: 11px; color: var(--gray-700); }
  .rto-only { display: none; }
  .rto-only.on { display: flex; }

  /* 좁은 화면에서는 한 줄을 고집하지 않는다 — 칸이 눌려 글자가 안 보이는 편이 나쁘다 */
  @media (max-width: 1100px) { .rto-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<form method="POST" action="{{ route('order-returns.store') }}" class="rto-wrap" id="rtoForm">
  @csrf
  <input type="hidden" name="order_id" id="rtoOrderId" value="">

  {{-- ① 원 주문 찾기 --}}
  <div class="rto-sec">
    <div class="rto-sec-hd">
      <span class="step">1</span> 원 주문 찾기
      <span class="hint" id="rtoPickedNote">환자나 주문번호로 찾아 고르십시오</span>
    </div>

    <div class="rto-filter">
      <div class="rto-fld"><label>환자</label>
        <div style="display:flex;gap:6px;">
          <input type="text" id="rtoName" class="form-control" maxlength="50" placeholder="이름">
          {{-- 이름만 적어 넣게 두면 동명이인을 가릴 수 없다. 고르면 생년월일·전화번호가
               함께 채워져 그다음 조회가 한 사람으로 좁혀진다. --}}
          <button type="button" class="ds-btn" style="flex-shrink:0;"
                  id="rtoPatientBtn" onclick="rtoPickPatient(this)">조회</button>
        </div></div>
      <div class="rto-fld"><label>생년월일</label>
        <input type="date" id="rtoBirth" class="form-control"></div>
      <div class="rto-fld"><label>전화번호</label>
        <input type="text" id="rtoPhone" class="form-control" maxlength="20" placeholder="010-0000-0000"></div>
      <div class="rto-fld wide"><label>주문번호</label>
        <input type="text" id="rtoNo" class="form-control" maxlength="50" placeholder="주문번호 · 판매번호"></div>
      <button type="button" class="ds-btn ds-btn-primary" id="rtoFindBtn" onclick="rtoFind(this)">검색</button>
      <span class="rto-note" id="rtoFindNote"></span>
      {{-- 끝내는 단추는 찾는 자리와 나란히 둔다. 아래에 두면 제품 목록이 길어질수록
           멀어져, 접수하려고 화면을 끝까지 굴려 내려야 한다. --}}
      <span style="margin-left:auto;display:flex;gap:8px;">
        <button type="button" class="ds-btn" onclick="rtnPanel('list')">종료</button>
        <button type="submit" class="ds-btn ds-btn-primary">접수</button>
      </span>
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
      <div class="rto-f rto-only" id="rtoSubtypeWrap">
        {{-- 취소는 두 갈래다. 아직 나가지 않은 것을 무르는 것과, 자격이 바뀌어 이미 받은
             돈만 되돌리는 것(일반 환불). 둘은 창고에 보내는 것도 단계도 다르다. --}}
        <label>취소 유형</label>
        <select name="subtype" id="rtoSubtype" class="form-control form-select">
          @foreach(\App\Models\OrderReturn::SUBTYPES as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="rto-f">
        <label>사유</label>
        <select name="reason_code" id="rtoReason" class="form-control form-select" required>
          @foreach(\App\Models\OrderReturn::reasons() as $k => $r)
            <option value="{{ $k }}">{{ $r['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="rto-f">
        {{-- 고른 주문을 신청 내용 안에서도 보이게 둔다 — 아래를 적는 동안 위를 다시
             올려다보지 않아도 무엇에 대한 신청인지 알 수 있다. --}}
        <label>주문번호</label>
        <input type="text" id="rtoOrderNo" class="form-control" readonly
               style="background:var(--gray-50);" placeholder="주문을 고르면 채워집니다">
      </div>
      <div class="rto-f">
        <label>주문일</label>
        <input type="text" id="rtoOrderDate" class="form-control" readonly
               style="background:var(--gray-50);" placeholder="—">
      </div>
      <div class="rto-f span4">
        {{-- 갈래가 정해지면 단계도 배송비도 승인자도 정해진다. 접수하는 사람이 그것을
             미리 알아야 고객에게 무엇을 안내할지 정할 수 있다. --}}
        <div id="rtoFlowNote" style="padding:8px 12px;background:var(--gray-50);
                    border:1px solid var(--border);border-radius:8px;font-size:12px;
                    color:var(--gray-700);line-height:1.6;"></div>
      </div>
      <div class="rto-f span4" id="rtoPreShipWrap" style="display:none;">
        {{-- 아직 나가지 않은 주문이면 종류와 상관없이 판매주문 취소로 나간다.
             접수하고 나서 알면 늦다 — 고르는 자리에서 미리 알린다. --}}
        <div style="padding:8px 12px;background:var(--primary-light);border:1px solid var(--primary-200);
                    border-radius:8px;font-size:12px;color:var(--primary);">
          아직 나가지 않은 주문입니다 — 창고에는 <b>판매주문 취소</b>로 넘어갑니다.
          되돌려 받을 물건이 없어 수거·검수 단계를 두지 않습니다.
          발행된 계산서·현금영수증이 있으면 함께 취소하고, 청구 대상에서도 뺍니다.
        </div>
      </div>
      <div class="rto-f span4">
        <label>상세 사유</label>
        <input type="text" name="reason_text" class="form-control" maxlength="500"
               placeholder="고객이 말한 내용을 그대로 적어 두면 나중에 판단이 쉽습니다">
      </div>
    </div>

    {{-- 교환 --}}
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
  const SEARCH_URL  = @json(route('order-returns.orderSearch'));

  /* 없을 때 그냥 「없다」고만 하면 검색이 고장 난 줄 안다 — 창고에 선 건만 고를 수
     있다는 것을 그 자리에서 알려 준다. */
  const NONE_NOTE = '창고에 전달된 주문이 없습니다 — 교환ㆍ반품ㆍ취소는 창고에 넘긴 건만 접수합니다';
  /* 갈래별 단계·승인자는 모델이 정한다. 화면에 두 벌로 적으면 한쪽만 고쳐진다. */
  const FLOWS = @json(collect(\App\Models\OrderReturn::FLOWS)->map(
      fn ($f) => collect($f)->map(fn ($s) => \App\Models\OrderReturn::STATUS_LABELS[$s])->all()));
  const SC_LABELS = @json(\App\Models\OrderReturn::SCENARIO_LABELS);
  const APPROVERS = @json(\App\Models\OrderReturn::APPROVERS);
  const DEFECTS   = @json(\App\Models\OrderReturn::DEFECT_REASONS);
  const PATIENT_URL = @json(route('order-returns.patientSearch'));

  const $ = (id) => document.getElementById(id);
  let rows = [];      // 마지막 조회 결과
  let picked = null;

  /* 주문 제품은 처음부터 자리를 잡아 둔다. 빈 표라도 보이면 무엇이 채워질 자리인지
     알 수 있고, 고른 뒤에 갑자기 나타나 아래가 밀리지 않는다. */
  /* 되돌릴 수량만 고칠 수 있다 — 부분 취소가 여기서 산다. 주문 수량을 함께 보여 주어
     몇 개 가운데 몇 개인지 한눈에 보이게 한다. 0 을 넣으면 그 줄은 되돌리지 않는다. */
  const itemGrid = new wwGrid({
    el: $('rtoItemGrid'),
    height: 'auto', editable: true, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '제품코드', name: 'product_code', width: 120, editable: false },
      { header: '제품명',   name: 'product_name', width: 260, editable: false },
      { header: '주문수량', name: 'ordered_quantity', width: 90, align: 'right', editor: 'number', editable: false },
      { header: '되돌릴 수량', name: 'quantity', width: 110, align: 'right', editor: 'number' },
      /* 돈은 자릿점을 찍어 보여 준다 — 12000 과 120000 을 눈으로 가리기 어렵다.
         다른 목록 화면과 같은 방식이다(editor: 'number' → ko-KR 자릿점). */
      { header: '단가',     name: 'unit_price',   width: 110, align: 'right', editor: 'number',
        editable: false, summary: false },  // 한 개 값이라 더하지 않는다
      { header: '환자부담', name: 'copay',        width: 110, align: 'right', editor: 'number', editable: false },
    ],
    data: [],
  });
  window.__rtoItemGrid = itemGrid;

  /* 환자 조회 — 누른 칸 옆에 붙는 팝오버로 연다. 고르면 이름·생년월일·전화번호가
     채워지고 곧바로 그 사람의 주문을 찾는다. */
  const modal    = new GridModal();   // 환자 조회
  /* 찾은 주문은 화면에 깔지 않고 검색 단추 옆에 띄운다. 고르고 나면 쓸 일이 없는
     목록이라, 화면에 남겨 두면 아래 적는 자리를 계속 밀어낸다.
     창을 나눠 둔다 — 환자 팝오버가 닫히면서 곧 열릴 결과 팝오버까지 닫지 않도록. */
  const hitModal = new GridModal();
  let pRows = {};

  window.rtoPickPatient = function (btn) {
    modal.open({
      title: '환자 조회', width: 420, height: 320, mode: 'popover', anchor: btn,
      onSearch: async (q) => {
        const res = await fetch(PATIENT_URL + '?q=' + encodeURIComponent(q ?? ''), {
          headers: { 'Accept': 'application/json' },
        });
        const { rows } = await res.json();
        pRows = {};
        (rows ?? []).forEach(r => { pRows[r.id] = r; });
        return (rows ?? []).map(r => ({
          value: r.id,
          label: r.name + (r.birth ? ' · ' + r.birth : ''),
          sub:   r.phone || '연락처 없음',
        }));
      },
      onConfirm: (v) => {
        const r = pRows[v];
        if (!r) return;
        $('rtoName').value  = r.name;
        $('rtoBirth').value = r.birth || '';
        $('rtoPhone').value = r.phone || '';
        rtoFind($('rtoFindBtn'));
      },
    });
  };

  /* 원 주문 찾기.
     조건 없이 눌러도 최근 것을 보여 준다 — 방금 만든 주문을 되돌리는 일이 잦아
     그때는 칠 검색어가 없다. */
  window.rtoFind = async function (btn) {
    const q = new URLSearchParams({
      patient_name: $('rtoName').value.trim(),
      birth_date:   $('rtoBirth').value,
      phone:        $('rtoPhone').value.trim(),
      order_no:     $('rtoNo').value.trim(),
    });

    $('rtoFindNote').textContent = '찾는 중…';
    try {
      /* 되쓰지 않는다 — 같은 주소로 다시 물으면 브라우저가 앞서 받아 둔 답을 그대로
         내주는 일이 있어, 고른 조건이 달라졌는데 옛 목록이 보일 수 있다. */
      const res = await fetch(SEARCH_URL + '?' + q.toString(),
                              { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      rows = (await res.json()).rows ?? [];
      picked = null;
      $('rtoFindNote').textContent = rows.length ? rows.length + '건' : NONE_NOTE;

      /* 몇 건이 나오든 창을 띄운다. 한 건일 때만 조용히 앉히면 눌렀는데 아무 창도
         안 뜬 것처럼 보이고, 무엇이 골라졌는지 확인할 자리도 없다.
         없을 때도 띄운다 — 찾은 자리에서 「없다」고 알려 주는 편이 낫다. */
      showHits(btn);
    } catch (e) {
      // 지난 결과를 그대로 띄우면 방금 찾은 것으로 읽힌다
      rows = [];
      $('rtoFindNote').textContent = '찾지 못했습니다';
      showHits(btn);
    }
  };

  /* 다른 화면에서 주문번호를 들고 들어온 경우. 무엇을 되돌릴지는 이미 정해져 있으니
     찾아서 그대로 앉힌다 — 고를 것이 없는데 창을 띄우면 한 번 더 누르게 한다.
     그 주문이 아닌 것이 섞여 나오면 그때는 창을 띄워 고르게 둔다. */
  window.rtoPreset = async function (orderNo) {
    if (!orderNo) return;
    $('rtoNo').value = orderNo;
    const q = new URLSearchParams({ patient_name: '', birth_date: '', phone: '', order_no: orderNo });

    $('rtoFindNote').textContent = '찾는 중…';
    try {
      /* 되쓰지 않는다 — 같은 주소로 다시 물으면 브라우저가 앞서 받아 둔 답을 그대로
         내주는 일이 있어, 고른 조건이 달라졌는데 옛 목록이 보일 수 있다. */
      const res = await fetch(SEARCH_URL + '?' + q.toString(),
                              { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      rows = (await res.json()).rows ?? [];
      picked = null;
      $('rtoFindNote').textContent = rows.length ? rows.length + '건' : NONE_NOTE;

      const only = rows.findIndex(r => r.order_no === orderNo);
      if (only >= 0)            pick(only);
      else if (rows.length === 1) pick(0);
      else if (rows.length)     showHits();
    } catch (e) {
      rows = [];
      $('rtoFindNote').textContent = '찾지 못했습니다';
    }
  };

  /* 찾은 주문을 고르는 창. 이름·주문번호·주문일만 보인다 — 고를 때 필요한 것은 그 셋이다. */
  function showHits(btn) {
    hitModal.open({
      title: '판매 주문 선택 · ' + (rows.length ? rows.length + '건' : '없음'),
      width: 420, height: 340,
      mode: 'popover', anchor: btn || $('rtoFindBtn'),
      items: rows.map((r, i) => ({
        value: i,
        label: r.patient + ' · ' + r.order_no,
        sub:   [r.order_date, r.status, r.returns > 0 ? '접수 ' + r.returns + '건' : null]
                 .filter(Boolean).join(' · '),
      })),
      onConfirm: (i) => pick(i),
    });
  }

  function pick(i) {
    picked = i;
    const r = rows[i];

    $('rtoOrderId').value   = r.id;
    $('rtoOrderNo').value   = r.order_no;
    $('rtoOrderDate').value = r.order_date || '';
    $('rtoPickedNote').textContent =
      '고른 주문 ' + r.order_no + ' · ' + r.patient
      + (r.so_no ? ' · ' + r.so_no : '') + ' · ' + r.status
      + (r.returns > 0 ? ' · 접수 ' + r.returns + '건 있음' : '');

    // 환불 금액과 재배송지는 원 주문에서 끌어 온다 — 대개 그대로다
    if (!$('rtoRefundAmount').value) $('rtoRefundAmount').value = r.amount || '';

    // 아직 나가지 않은 주문이면 무엇으로 나가는지 미리 알린다
    $('rtoPreShipWrap').style.display = r.shipped ? 'none' : '';

    /* 처음에는 다 되돌리는 것으로 앉힌다 — 대개 그렇고, 부분이면 수량만 줄이면 된다. */
    itemGrid.setData((r.items ?? []).map(i => ({ ...i, ordered_quantity: i.quantity })));
    $('rtoItemNote').textContent = (r.items?.length ?? 0)
      + '개 품목 · ' + r.order_no + ' — 일부만 되돌리려면 「되돌릴 수량」을 줄이십시오';
  }

  /* 종류에 따라 물을 것이 다르다. */
  function syncType() {
    const t = $('rtoType').value;
    /* 교환은 따로 물을 것이 없다 — 무엇을 되돌리는지는 아래 주문 제품에 나와 있고,
       바꿔 보낼 물건과 보낼 곳은 창고가 수거·검수를 마친 뒤 정해진다.
       환불은 교환이 아닐 때만 묻는다. */
    $('rtoRefundSec').style.display = t === 'exchange' ? 'none' : 'block';
    // 취소일 때만 갈래를 묻는다 — 교환·반품에는 갈래가 없다
    $('rtoSubtypeWrap').classList.toggle('on', t === 'cancel');
    syncReason();
    syncRefundMethod();
    syncNote();
  }

  /* 자격 변경은 되돌려 받을 물건이 없다 — 언제나 일반 환불이다.
     고른 갈래와 사유가 어긋나면 접수한 뒤에 흐름이 엉뚱하게 잡힌다. */
  /* 지금 고른 것이 절차서의 어느 갈래인가 — 모델의 scenario() 와 같은 규칙이다. */
  function scenarioOf() {
    const t = $('rtoType').value;
    const r = $('rtoReason').value;
    if (t === 'exchange') return DEFECTS.includes(r) ? 'exchange_defect' : 'exchange_mind';
    if (t === 'return')   return 'return_refund';
    return (r === 'eligibility' || $('rtoSubtype').value === 'refund_only')
      ? 'refund_only' : 'cancel_before_ship';
  }

  const RULES = {
    exchange_mind: '환자가 3PL 로 보내면 배송비는 <b>환자 부담</b>입니다. '
      + '검수·승인·입금 확인을 마쳐야 다시 나갑니다.',
    exchange_defect: '3PL 이 회수하고 택배비도 <b>3PL 이 뭅니다</b>. '
      + '<b>동일 제품만</b> 바꿔 드리고, 불량 lot 은 빼고 출고합니다. '
      + '최초 일자 기준으로 청구하며 재결제는 하지 않습니다. '
      + '회수·교환 기간에 쓸 제품은 따로 보냅니다.',
    return_refund: '물건을 받아 검수한 뒤 결제를 취소하고 마이너스로 발행합니다.',
    cancel_before_ship: '아직 나가지 않은 주문입니다 — 수거·검수가 없습니다.',
    refund_only: '되돌려 받을 물건이 없습니다. 창고에는 알리지 않고, '
      + '승인·결제취소 뒤 <b>전산판매(금액조정)</b> 주문을 세웁니다.',
  };

  function syncNote() {
    const sc = scenarioOf();
    $('rtoFlowNote').innerHTML =
      '<b>' + SC_LABELS[sc] + '</b> · 승인 ' + APPROVERS[sc]
      + '<br>' + (FLOWS[sc] || []).join(' → ')
      + '<br>' + RULES[sc];
  }

  function syncReason() {
    if ($('rtoType').value !== 'cancel') return;
    if ($('rtoReason').value === 'eligibility') {
      $('rtoSubtype').value = 'refund_only';
      $('rtoSubtype').disabled = true;
    } else {
      $('rtoSubtype').disabled = false;
    }
  }

  function syncRefundMethod() {
    const on = $('rtoRefundMethod').value === 'account';
    $('rtoAccountWrap').classList.toggle('on', on);
    $('rtoAccountWrap2').classList.toggle('on', on);
  }

  $('rtoType').addEventListener('change', syncType);
  $('rtoRefundMethod').addEventListener('change', syncRefundMethod);
  $('rtoReason').addEventListener('change', () => { syncReason(); syncNote(); });
  $('rtoSubtype').addEventListener('change', syncNote);
  ['rtoName', 'rtoBirth', 'rtoPhone', 'rtoNo'].forEach(id =>
    $(id).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); rtoFind(); } }));

  /* 그리드는 폼 칸이 아니다 — 보낼 때 숨은 칸으로 옮겨 싣는다.
     되돌릴 수량이 0 인 줄은 빼고, 하나도 남지 않으면 접수를 막는다. */
  $('rtoForm').addEventListener('submit', (e) => {
    if (!$('rtoOrderId').value) { e.preventDefault(); alert('원 주문을 먼저 고르십시오.'); return; }

    document.querySelectorAll('.rto-item-field').forEach(el => el.remove());

    const rows = (itemGrid.getData() ?? []).filter(r => Number(r.quantity) > 0);

    if (!rows.length) {
      e.preventDefault();
      alert('되돌릴 수량이 하나도 없습니다. 「되돌릴 수량」을 적으십시오.');
      return;
    }

    rows.forEach((r, i) => {
      Object.entries({
        order_item_id:    r.order_item_id ?? '',
        product_code:     r.product_code ?? '',
        product_name:     r.product_name ?? '',
        ordered_quantity: r.ordered_quantity ?? 0,
        quantity:         r.quantity ?? 0,
        unit_price:       r.unit_price ?? 0,
        copay:            r.copay ?? 0,
      }).forEach(([k, v]) => {
        const el = document.createElement('input');
        el.type = 'hidden';
        el.className = 'rto-item-field';
        el.name = `items[${i}][${k}]`;
        el.value = v;
        $('rtoForm').appendChild(el);
      });
    });
  });

  syncType();
})();
</script>
@endpush
