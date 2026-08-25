{{-- 상담 창 — 통화한 내용을 그 자리에서 적는 창.

     거래처 관리와 주문 등록 두 곳에서 같은 창을 띄운다. 두 벌로 두면 한쪽만 고쳐져
     어느 화면에서 적었느냐에 따라 물어보는 것이 달라진다. 창ㆍ모양ㆍ여닫는 법을
     여기 한 벌만 두고, 부르는 화면은 csOpen(환자id, 이름, 통화번호) 만 부른다.

     이 창이 기대는 것은 전역으로 있는 것들뿐이다(GridModal · apiRequest · showToast ·
     ceConfirm · BtnState). 거래처 관리에만 있는 상담내역 탭(pcTabs · pcActive · pcLoad)은
     있을 때만 쓴다 — 없으면 없는 대로 연다. --}}

{{-- 모양은 그 자리에 그대로 둔다 — 이 조각은 화면 본문 안에서 그려지고,
     머리의 styles 자리는 그때 이미 지나가 있다. --}}
@once
<style>
  /* 상담 창 — 뒤를 덮지 않고 떠 있는다. 뒤 화면은 그대로 쓸 수 있다. */
  .cs-win { position: fixed; z-index: 1100; display: none; }
  .cs-box { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); box-shadow: 0 20px 60px rgba(0,0,0,.28); overflow: hidden; }
  .cs-head { display: flex; align-items: center; gap: 8px; padding: 11px 14px;
             background: var(--primary); color: #fff; font-size: 13px; font-weight: 700;
             cursor: move; user-select: none; }
  /* 옮기는 동안에는 글자가 잡히지 않게 — 끌다가 문장이 파랗게 뒤집히면 성가시다 */
  .cs-win.is-moving, .cs-win.is-moving * { user-select: none; }
  .cs-grip { position: absolute; right: 0; bottom: 0; width: 16px; height: 16px;
             cursor: nwse-resize; }
  .cs-grip::after { content: ''; position: absolute; right: 3px; bottom: 3px; width: 8px; height: 8px;
                    border-right: 2px solid var(--gray-300); border-bottom: 2px solid var(--gray-300); }
  .cs-head span { flex: 1; }
  /* 닫기 규격은 24×24 · r6 · 16px 이다(17 은 시안 글자 규격 밖) */
  .cs-head button { display: flex; align-items: center; justify-content: center;
                    width: 24px; height: 24px; flex-shrink: 0; padding: 0;
                    background: none; border: none; border-radius: 6px; color: #fff;
                    font-size: 16px; line-height: 1; cursor: pointer; }
  .cs-body { flex: 1; min-height: 0; padding: 14px; display: flex; flex-direction: column;
             gap: 10px; overflow-y: auto; }
  /* 창이 커지면 적는 자리가 함께 커져야 한다 — 칸만 남고 여백이 늘면 뜻이 없다 */
  .cs-body .cs-row:last-child { flex: 1; min-height: 0; }
  .cs-body .cs-row:last-child .cs-f { flex: 1; min-height: 0; }
  .cs-f textarea#csContents { flex: 1; min-height: 120px; }
  .cs-row { display: flex; flex-direction: column; gap: 10px; }
  .cs-row.two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .cs-f { display: flex; flex-direction: column; gap: 4px; }
  .cs-f label { font-size: 11px; font-weight: 500; color: var(--gray-700); }
  .cs-f textarea.form-control { height: auto; padding: 8px 10px; line-height: 1.7; resize: vertical; }
  .cs-hint { font-size: 11px; color: var(--text-muted); }
  .cs-foot { display: flex; align-items: center; gap: 6px; padding: 10px 14px;
             border-top: 1px solid var(--border); }
  .cs-foot .cs-hint { margin-right: auto; }

  /* ── 지난 상담 고르기 ──
     표를 세우지 않고 줄로 늘어놓는다. 이 창은 580 폭이라 칸을 나누면 상담 내용이
     먼저 잘리는데, 어느 상담이었는지 가리는 것은 대개 그 내용 첫 줄이다. */
  .cs-list { display: flex; flex-direction: column; gap: 6px; }
  .cs-item { padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px;
             background: var(--gray-0); cursor: pointer; }
  .cs-item:hover { border-color: var(--primary); background: var(--gray-50); }
  .cs-item-top { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700;
                 color: var(--gray-1000); }
  .cs-item-no { color: var(--primary); }
  .cs-item-tag { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px;
                 background: var(--gray-100); color: var(--gray-700); }
  .cs-item-tag.re { background: #fff4e5; color: #b26a00; }
  .cs-item-note { margin-top: 3px; font-size: 11px; color: var(--text-muted);
                  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .cs-empty { padding: 22px 12px; text-align: center; font-size: 12px; color: var(--text-muted); }
</style>
@endonce

{{-- ── 상담 창 — 화면 안에 떠 있는 창 ────────────────────
     전화를 받으며 적는 자리다. 브라우저 창을 따로 띄우면 보던 목록이 뒤로 숨고
     팝업 차단에 막히기도 한다. 화면 안에 띄우되 뒤를 덮지 않는다 — 상담을 적는 동안에도
     목록을 훑고 다른 탭을 눌러야 하는 일이 있다. 머리를 잡아 옮기고 모서리로 크기를 바꾼다. --}}
<div class="cs-win" id="csModal" style="display:none;" role="dialog" aria-labelledby="csTitle">
  <div class="cs-box">
    <div class="cs-head" id="csHead">
      <i class="bx bx-conversation"></i>
      <span id="csTitle">상담하기</span>
      <button type="button" onclick="csClose()" aria-label="닫기">&times;</button>
    </div>

    {{-- ① 지난 상담 고르기 — 이어 갈 것이 있으면 먼저 보여 준다 --}}
    <div class="cs-body" id="csStep1" style="display:none;">
      <div class="cs-hint" id="csListNote">불러오는 중…</div>
      <div class="cs-list" id="csList"></div>
    </div>
    <div class="cs-foot" id="csFoot1" style="display:none;">
      <span class="cs-hint">이어 갈 상담을 고르거나, 새 상담을 시작합니다.</span>
      <button type="button" class="ds-btn" onclick="csClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" onclick="csNew()">신규로 상담하기</button>
    </div>

    {{-- ② 적는 자리 — 새 상담이든 이어 가는 상담이든 같은 칸을 쓴다 --}}
    <div class="cs-body" id="csStep2">
      <div class="cs-row two">
        <div class="cs-f">
          <label>상담일시 *</label>
          <input type="date" id="csDate" class="form-control">
        </div>
        <div class="cs-f">
          <label>통화번호</label>
          <input type="text" id="csCallNo" class="form-control" maxlength="30" placeholder="010-0000-0000">
        </div>
      </div>

      <div class="cs-row two">
        <div class="cs-f">
          <label>상담 유형</label>
          <select id="csType" class="form-control form-select">
            <option value="">선택</option>
            <option value="1013">구매</option>
            <option value="1016">개인구매</option>
            <option value="1020">반품</option>
            <option value="1030">문의</option>
            <option value="1050">기타</option>
          </select>
        </div>
        <div class="cs-f">
          <label>상담 상태</label>
          <select id="csStatus" class="form-control form-select" onchange="csSyncReDate()">
            <option value="02">등록</option>
            <option value="50">재상담</option>
            <option value="95">확정</option>
            <option value="99">취소</option>
          </select>
        </div>
      </div>

      <div class="cs-row" id="csReDateWrap" style="display:none;">
        <div class="cs-f">
          <label>재상담일</label>
          <input type="date" id="csReDate" class="form-control">
        </div>
      </div>

      <div class="cs-row">
        <div class="cs-f">
          {{-- 환자는 주문을 여러 번 한다(처방으로도, 처방 없이도) — 어느 건 이야기였는지
               골라서 잇는다. 주문 전 문의처럼 이을 건이 없으면 비워 둔다. --}}
          <label>주문번호</label>
          <div style="display:flex;gap:6px;">
            <input type="text" id="csOrderNo" class="form-control" readonly
                   style="background:var(--gray-50);" placeholder="주문조회에서 고르십시오 (없으면 비워 둡니다)">
            <button type="button" class="ds-btn" style="flex-shrink:0;"
                    onclick="csPickOrder(this)">주문조회</button>
          </div>
        </div>
      </div>

      <div class="cs-row">
        <div class="cs-f">
          {{-- 이 창의 본디 목적이다 — 통화한 내용을 그대로 적는다 --}}
          <label>상담 내용 *</label>
          <textarea id="csContents" class="form-control" rows="8" maxlength="2000"
                    placeholder="고객이 말한 내용을 그대로 적어 두면 다음 사람이 이어받기 쉽습니다."></textarea>
          <span class="cs-hint"><b id="csLen">0</b>/2000자</span>
        </div>
      </div>
    </div>

    <div class="cs-foot" id="csFoot2">
      <span class="cs-hint" id="csNote">적은 내용은 저장을 눌러야 남습니다.</span>
      {{-- 목록에서 들어왔을 때만 선다 — 곧장 새 상담으로 열린 건은 돌아갈 목록이 없다 --}}
      <button type="button" class="ds-btn" id="csBackBtn" style="display:none;" onclick="csBack()">목록</button>
      <button type="button" class="ds-btn" onclick="csClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" id="csSaveBtn" onclick="csSave(this)">저장</button>
    </div>
    {{-- 오른쪽 아래 모서리를 잡아 크기를 바꾼다 --}}
    <div class="cs-grip" id="csGrip" title="크기 조절"></div>
  </div>
</div>

@once
@push('scripts')
<script>
(function () {
  /* ── 상담 창 ────────────────────────────────────────────
     상담자가 고객과 통화한 내용을 그 자리에서 적는 자리다. 주문 등록 화면을 띄우던
     것을 그만둔다 — 그 화면은 처방과 주문을 다루는 자리라, 통화 한 통을 적기에는
     묻는 것이 너무 많았다.

     적다 만 채로 닫는 일이 잦아, 무엇이든 적혀 있으면 닫기 전에 물어본다. */
  // 상담을 적어 두는 곳. 이 조각이 스스로 안다 — 부르는 화면마다 다시 일러 줄 일이 없다.
  const CS_BASE = @json(url('patients'));

  let _csPatient = null;
  let _csDirty   = false;
  let _csOrder   = null;   // 이 상담을 이을 주문
  /* 이어 가는 상담. null 이면 새 상담이다 — 저장이 새로 세울지 고쳐 이을지 이 값이 가린다. */
  let _csEditing = null;
  let _csList    = [];     // 지난 상담(목록 걸음이 들고 있는 것)
  let _csMobile  = '';     // 이 사람의 통화번호 — 새 상담을 열 때 미리 채운다

  /* ── 주문 잇기 ──────────────────────────────────────────
     환자 한 사람이 주문을 여러 번 한다 — 처방을 받아 사는 때도, 처방 없이 사는 때도
     있다. 그래서 상담이 어느 건 이야기였는지는 골라서 이어야 한다.

     고르는 창은 누른 자리 옆에 붙는다. 상담을 적다가 여는 자리라 화면 한가운데를
     덮으면 적던 것이 가려진다. */
  const _ordModal = new GridModal();
  let _ordRows = {};

  /** @param onPick (order) => void — 고른 주문을 받는다. 「연결 안 함」이면 null 이 온다. */
  async function ordPick(anchor, patientId, onPick) {
    /* 주문을 먼저 받아 놓고 연다. 글자를 쳐야 찾으러 가는 창으로 두면 열자마자 빈 목록이
       보여, 이 사람은 주문이 없다고 읽힌다 — 한 사람의 주문은 많아야 수십 건이다. */
    let rows = [];
    try {
      const res = await fetch(`${CS_BASE}/${patientId}/orders`,
                              { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      rows = (await res.json()).rows ?? [];
    } catch (e) {
      showToast('주문을 불러오지 못했습니다.', 'danger');
      return;
    }

    _ordRows = {};
    rows.forEach(r => { _ordRows[r.id] = r; });

    /* 고를 때 필요한 것은 언제 한 주문인가와 그 번호, 둘이다 — 제품·금액·상태까지 적으면
       한 줄이 길어져 훑기 어렵다. 잘못 이었을 때 풀 길도 함께 둔다. */
    const items = [{ value: '', label: '— 연결 안 함 —', sub: '주문 전 문의처럼 이을 건이 없을 때' }]
      .concat(rows.map(r => ({
        value: r.id,
        label: `${r.date}   ${r.order_no}`,
      })));

    _ordModal.open({
      title: `주문조회 · ${rows.length}건`, width: 360, height: 340,
      mode: 'popover', anchor, items,
      onConfirm: (v) => onPick(v ? _ordRows[v] : null),
    });
  }

  // 거래처 관리의 「이어 둔 주문 고치기」도 이 고르개를 쓴다
  window.ordPick = ordPick;

  /* 상담 창에서 고른다 — 적는 중에도 이었다 풀었다 할 수 있다 */
  window.csPickOrder = function (btn) {
    if (!_csPatient) return;
    ordPick(btn, _csPatient.id, (order) => {
      _csOrder = order;
      _csDirty = true;
      csShowOrder();
    });
  };

  window.csShowOrder = function () {
    const el = document.getElementById('csOrderNo');
    if (!el) return;
    el.value = _csOrder ? `${_csOrder.date}   ${_csOrder.order_no}` : '';
    el.placeholder = '주문조회에서 고르십시오 (없으면 비워 둡니다)';
  };


  /* 창 자리와 크기 — 옮겼던 자리는 기억해 둔다. 두 번째부터는 놓아 둔 곳에서 열린다.
     화면 밖으로는 나가지 않게 붙든다. */
  let _csBox = null;

  function _csApplyBox(box) {
    const win = document.getElementById('csModal');
    const w = Math.max(380, Math.min(box.w, window.innerWidth  - 16));
    const h = Math.max(320, Math.min(box.h, window.innerHeight - 16));
    const left = Math.max(0, Math.min(box.left, window.innerWidth  - w));
    const top  = Math.max(0, Math.min(box.top,  window.innerHeight - h));

    win.style.left   = left + 'px';
    win.style.top    = top  + 'px';
    win.style.width  = w + 'px';
    win.style.height = h + 'px';
    _csBox = { left, top, w, h };
  }

  function _csDefaultBox() {
    const w = Math.min(580, Math.round(window.innerWidth  * 0.5));
    const h = Math.min(620, Math.round(window.innerHeight * 0.8));
    return {
      // 오른쪽에 둔다 — 왼쪽 목록을 보면서 적는 일이 많다
      left: Math.max(8, window.innerWidth - w - 24),
      top:  Math.max(8, Math.round((window.innerHeight - h) / 2)),
      w, h,
    };
  }

  /* 머리를 잡아 옮기고, 오른쪽 아래 모서리를 잡아 크기를 바꾼다.

     손잡이에 바로 걸지 않고 문서에서 받는다 — 이 스크립트가 창 마크업보다 먼저 도는
     자리라, 그때 손잡이를 찾으면 없다(예전에는 그래서 창이 꿈쩍도 하지 않았다).
     pointer 이벤트라 커서가 창 밖으로 나가도 놓을 때까지 따라온다. */
  (function () {
    let mode = null, sx = 0, sy = 0, start = null;

    document.addEventListener('pointerdown', (e) => {
      const win = document.getElementById('csModal');
      if (!win || win.style.display === 'none') return;

      const onHead = e.target.closest?.('#csHead');
      const onGrip = e.target.closest?.('#csGrip');
      if (!onHead && !onGrip) return;
      // 머리의 닫기 단추를 누른 것은 옮기려는 뜻이 아니다
      if (onHead && e.target.closest('button')) return;

      mode = onGrip ? 'size' : 'move';
      sx = e.clientX; sy = e.clientY;
      start = { ..._csBox };
      win.classList.add('is-moving');
      e.preventDefault();
    });

    document.addEventListener('pointermove', (e) => {
      if (!mode || !start) return;
      const dx = e.clientX - sx, dy = e.clientY - sy;
      _csApplyBox(mode === 'move'
        ? { ...start, left: start.left + dx, top: start.top + dy }
        : { ...start, w: start.w + dx, h: start.h + dy });
    });

    const end = () => {
      if (!mode) return;
      mode = null; start = null;
      document.getElementById('csModal')?.classList.remove('is-moving');
    };
    document.addEventListener('pointerup', end);
    document.addEventListener('pointercancel', end);

    // 화면 크기가 바뀌면 창이 밖에 나가 있을 수 있다
    window.addEventListener('resize', () => {
      const win = document.getElementById('csModal');
      if (win && win.style.display !== 'none' && _csBox) _csApplyBox(_csBox);
    });
  })();

  /**
   * 상담 창을 연다.
   *
   * 거래처 관리에서는 보고 있는 상담내역 탭의 사람으로, 주문 등록에서는 처방전에
   * 이어 둔 사람으로 연다. 그래서 누구인지를 받되, 안 주면 탭에서 찾는다 —
   * 탭이라는 것이 없는 화면도 있으므로 있을 때만 묻는다.
   *
   * @param {number|string} id   환자 id
   * @param {string}        name 창 머리에 적을 이름
   * @param {string}        [mobile] 통화번호로 미리 채울 번호
   */
  window.csOpen = async function (id, name, mobile) {
    const p = id ? { id, name } : (typeof pcActive === 'function' ? pcActive() : null);
    if (!p) { showToast('먼저 환자를 고르십시오.', 'warning'); return; }

    _csPatient = p;
    _csDirty   = false;
    _csMobile  = (typeof pcTabs !== 'undefined' ? pcTabs[p.id]?.mobile : '') || mobile || '';

    document.getElementById('csTitle').textContent = (p.name || '') + ' 상담하기';

    const win = document.getElementById('csModal');
    win.style.display = 'block';
    _csApplyBox(_csBox ?? _csDefaultBox());

    /* 지난 상담이 있으면 먼저 보여 준다 — 다시 걸어 온 통화를 새 건으로 세우면
       같은 이야기가 둘로 갈라진다. 이을 것이 없으면 곧장 새 상담으로 연다. */
    _csStep(1);
    document.getElementById('csListNote').textContent = '지난 상담을 불러오는 중…';
    document.getElementById('csList').innerHTML = '';

    try {
      const res  = await fetch(`${CS_BASE}/${p.id}/counsels`,
                               { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      _csList = (await res.json()).rows ?? [];
    } catch (e) {
      _csList = [];
    }

    if (!_csList.length) { csNew({ fromList: false }); return; }
    csRenderList();
  };

  /** 걸음 바꾸기 — ① 지난 상담 고르기 · ② 적는 자리 */
  function _csStep(n) {
    document.getElementById('csStep1').style.display = n === 1 ? '' : 'none';
    document.getElementById('csFoot1').style.display = n === 1 ? '' : 'none';
    document.getElementById('csStep2').style.display = n === 2 ? '' : 'none';
    document.getElementById('csFoot2').style.display = n === 2 ? '' : 'none';
  }

  function csRenderList() {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    document.getElementById('csListNote').textContent =
      `지난 상담 ${_csList.length}건 — 이어 갈 상담을 누르십시오.`;

    document.getElementById('csList').innerHTML = _csList.map((c, i) => {
      const tags = [
        c.type_label ? `<span class="cs-item-tag">${esc(c.type_label)}</span>` : '',
        c.status_label
          ? `<span class="cs-item-tag${c.status === '50' ? ' re' : ''}">${esc(c.status_label)}</span>` : '',
        c.order_no ? `<span class="cs-item-tag">${esc(c.order_no)}</span>` : '',
      ].join('');
      const first = (c.contents || '').split(/\r?\n/)[0] || '(내용 없음)';
      const re    = c.status === '50' && c.re_date ? ` · 재상담 ${esc(c.re_date)}` : '';
      return `<div class="cs-item" onclick="csPick(${i})">
                <div class="cs-item-top">
                  <span class="cs-item-no">${esc(c.counsel_no)}</span>
                  <span>${esc(c.date)}${re}</span>
                  <span style="margin-left:auto;display:flex;gap:4px;">${tags}</span>
                </div>
                <div class="cs-item-note">${esc(first)}</div>
              </div>`;
    }).join('');
  }

  /** 지난 상담을 이어 간다 — 적어 둔 것을 그대로 띄우고, 저장하면 그 건이 고쳐진다 */
  window.csPick = function (i) {
    const c = _csList[i];
    if (!c) return;

    _csEditing = c;
    _csDirty   = false;
    _csOrder   = c.order_id ? { id: c.order_id, order_no: c.order_no } : null;

    document.getElementById('csDate').value     = c.date || new Date().toISOString().slice(0, 10);
    document.getElementById('csCallNo').value   = c.call_no || _csMobile;
    document.getElementById('csType').value     = c.type   || '';
    document.getElementById('csStatus').value   = c.status || '02';
    document.getElementById('csReDate').value   = c.re_date || '';
    document.getElementById('csContents').value = c.contents || '';
    document.getElementById('csLen').textContent = String((c.contents || '').length);
    document.getElementById('csNote').textContent =
      `${c.counsel_no} 을 이어 적습니다 — 저장하면 이 상담이 고쳐집니다.`;
    document.getElementById('csBackBtn').style.display = '';

    csShowOrder();
    csSyncReDate();
    _csStep(2);

    // 커서는 적어 둔 것 끝에 — 이어 적는 자리다
    setTimeout(() => {
      const t = document.getElementById('csContents');
      t.focus();
      t.setSelectionRange(t.value.length, t.value.length);
      t.scrollTop = t.scrollHeight;
    }, 50);
  };

  /** 새 상담을 시작한다 */
  window.csNew = function (opts = {}) {
    const fromList = opts.fromList !== false;

    _csEditing = null;
    _csDirty   = false;
    _csOrder   = null;

    document.getElementById('csDate').value      = new Date().toISOString().slice(0, 10);
    document.getElementById('csCallNo').value    = _csMobile;
    document.getElementById('csType').value      = '';
    document.getElementById('csStatus').value    = '02';
    document.getElementById('csReDate').value    = '';
    document.getElementById('csContents').value  = '';
    document.getElementById('csLen').textContent = '0';
    document.getElementById('csNote').textContent = '적은 내용은 저장을 눌러야 남습니다.';
    document.getElementById('csBackBtn').style.display = fromList && _csList.length ? '' : 'none';

    csShowOrder();
    csSyncReDate();
    _csStep(2);
    setTimeout(() => document.getElementById('csContents').focus(), 50);
  };

  /** 목록으로 돌아간다 — 적다 만 것이 있으면 물어본다 */
  window.csBack = async function () {
    if (_csDirty) {
      const ok = await ceConfirm('적던 내용이 사라집니다. 목록으로 돌아갈까요?',
                                 { tone: 'warning', confirmText: '돌아가기', cancelText: '계속 적기' });
      if (!ok) return;
    }
    _csDirty = false;
    _csEditing = null;
    csRenderList();
    _csStep(1);
  };

  /* 주문 등록 화면의 「상담하기」로 들어오는 길.
     그 화면에는 이 창이 없어 여기로 보내며 누구와 상담할지 주소에 싣는다
     (…/patients?counsel=<환자id>&counsel_name=<이름>). 열고 나면 주소에서 지워
     새로고침이나 뒤로 가기에 창이 또 뜨지 않게 한다. */
  (function () {
    const q  = new URLSearchParams(location.search);
    const id = q.get('counsel');
    if (!id) return;
    const name = q.get('counsel_name') || '';
    q.delete('counsel'); q.delete('counsel_name');
    const rest = q.toString();
    history.replaceState(null, '', location.pathname + (rest ? '?' + rest : ''));
    // 화면이 다 그려진 뒤에 연다 — 창 마크업보다 이 조각이 먼저 도는 자리가 있다
    setTimeout(() => window.csOpen(parseInt(id, 10), name), 0);
  })();

  /* 재상담으로 두면 언제 다시 걸지가 곧 다음 일이 된다 — 그때만 날짜를 묻는다 */
  window.csSyncReDate = function () {
    const on = document.getElementById('csStatus').value === '50';
    document.getElementById('csReDateWrap').style.display = on ? '' : 'none';
  };

  window.csClose = async function () {
    if (_csDirty) {
      const ok = await ceConfirm('적은 내용을 저장하고 닫을까요?\n저장하지 않으면 적은 것이 사라집니다.',
                                 { tone: 'warning', confirmText: '저장하고 닫기', cancelText: '그냥 닫기' });
      if (ok) { await csSave(document.getElementById('csSaveBtn')); return; }
    }
    document.getElementById('csModal').style.display = 'none';
    _csDirty = false;
  };

  window.csSave = async function (btn) {
    const contents = document.getElementById('csContents').value.trim();
    if (!contents) { showToast('상담 내용을 적어 주십시오.', 'warning'); return; }

    BtnState.loading(btn, '저장 중...');
    try {
      /* 이어 가는 상담이면 그 건을 고친다. 다시 걸어 온 통화까지 새 건으로 세우면
         같은 이야기가 둘로 갈라진다. */
      const url    = _csEditing
        ? `${CS_BASE}/${_csPatient.id}/counsels/${_csEditing.id}`
        : `${CS_BASE}/${_csPatient.id}/counsels`;
      const method = _csEditing ? 'PATCH' : 'POST';

      const res = await apiRequest(url, method, {
        counsel_date:     document.getElementById('csDate').value,
        counsel_type:     document.getElementById('csType').value || null,
        counsel_status:   document.getElementById('csStatus').value || null,
        counsel_call_no:  document.getElementById('csCallNo').value.trim() || null,
        counsel_re_date:  document.getElementById('csStatus').value === '50'
                            ? (document.getElementById('csReDate').value || null) : null,
        counsel_contents: contents,
        counsel_order_id: _csOrder ? _csOrder.id : null,
      });
      if (!res.success) throw new Error(res.message || '저장하지 못했습니다.');

      showToast(`${_csEditing ? '상담을 이어 적었습니다' : '상담을 적어 두었습니다'} (${res.counsel_no})`,
                'success', 4000);
      _csDirty   = false;
      _csEditing = null;
      document.getElementById('csModal').style.display = 'none';
      // 방금 적은 것이 목록에 보여야 한다 — 그 목록이 있는 화면에서만
      if (typeof pcLoad === 'function') pcLoad(_csPatient.id, _csPatient.name);
    } catch (e) {
      showToast('저장하지 못했습니다: ' + (e.message || ''), 'danger', 6000);
    } finally {
      BtnState.reset(btn);
    }
  };

  /* 무엇이든 손댔으면 닫을 때 물어본다.
     칸마다 걸지 않고 창 전체에서 받는다 — 칸을 늘려도 따라오고, 스크립트가 창보다
     먼저 돌아도 놓치지 않는다. */
  document.addEventListener('input', (e) => {
    if (!e.target.closest?.('#csModal')) return;
    _csDirty = true;
    if (e.target.id === 'csContents') {
      document.getElementById('csLen').textContent = e.target.value.length;
    }
  });
  document.addEventListener('change', (e) => {
    if (e.target.closest?.('#csModal')) _csDirty = true;
  });

  /* 바깥을 눌러도 닫지 않는다 — 뒤 화면을 그대로 쓰라고 띄운 창이라, 목록을 한 번
     눌렀다고 적던 것이 사라지면 안 된다. 닫는 길은 닫기 단추와 Esc 둘이다.
     Esc 는 이 창 안에 손이 가 있을 때만 듣는다. */
  document.addEventListener('keydown', (e) => {
    const win = document.getElementById('csModal');
    if (e.key !== 'Escape' || !win || win.style.display === 'none') return;
    if (!win.contains(document.activeElement)) return;
    csClose();
  });

})();
</script>
@endpush
@endonce
