{{-- ── 주소 관리 창 ───────────────────────────────────────────────
     거래처 하나에 주소 여러 벌(2026-09-08 확인요청 6쪽).

     거래처 상세와 거래처 수정 창(주문 등록)이 함께 쓴다 — 두 벌로 두면 한쪽만
     고쳐진다. 어느 거래처의 주소인가는 openAddrManager(id) 로 열 때 정해진다.

     쓰는 곳: patients/show.blade.php · patients/_editor-modal.blade.php --}}
<style>
  /* ── 주소 관리 (2026-09-08 확인요청 6쪽) ── */
  .am-back { position:fixed; inset:0; z-index:1200; background:rgba(0,0,0,.35);
             display:flex; align-items:center; justify-content:center; }
  .am-box  { width:min(620px, calc(100vw - 32px)); max-height:calc(100vh - 80px);
             display:flex; flex-direction:column;
             background:var(--gray-0); border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.25); }
  .am-head { display:flex; align-items:center; gap:10px; padding:14px 18px;
             border-bottom:1px solid var(--gray-200); font-size:14px; }
  .am-x    { margin-left:auto; border:0; background:none; font-size:22px; line-height:1;
             color:var(--text-muted); cursor:pointer; }
  .am-body { flex:1; overflow:auto; padding:8px 18px; min-height:120px; }
  .am-row  { display:flex; align-items:center; gap:10px; padding:10px 0;
             border-bottom:1px solid var(--gray-100); font-size:12.5px; }
  .am-row:last-child { border-bottom:0; }
  .am-row .am-full { flex:1; min-width:0; }
  .am-row .am-when { color:var(--text-muted); font-size:11px; white-space:nowrap; }
  .am-now  { display:inline-block; margin-right:6px; padding:1px 6px; border-radius:4px;
             background:var(--primary-light); color:var(--primary); font-size:10px; font-weight:700; }
  .am-mini { border:1px solid var(--gray-200); background:var(--gray-0); border-radius:6px;
             padding:3px 8px; font-size:11px; cursor:pointer; white-space:nowrap; }
  .am-mini.danger { border-color:var(--danger); color:var(--danger); }
  .am-new  { border-top:1px solid var(--gray-200); padding:14px 18px; }
  .am-new-cap { font-size:12px; font-weight:700; margin-bottom:8px; }
  .am-line { display:flex; gap:8px; }
  .am-line .form-control { flex:1; min-width:0; }
  .am-acts { display:flex; gap:8px; justify-content:flex-end; margin-top:10px; }
</style>

{{-- ── 주소 관리 (2026-09-08 확인요청 6쪽) ──
     거래처 하나에 주소 여러 벌. 여태 거래처 칸이 바뀔 때 저절로 쌓이기만 하고
     손볼 길이 없었다 — 집과 직장을 미리 넣어 둘 수도, 잘못 적힌 한 줄을 고칠 수도
     없었다. --}}
<div id="addrModal" class="am-back" style="display:none;" onclick="if(event.target===this) closeAddrManager()">
  <div class="am-box">
    <div class="am-head">
      <b>주소 관리</b>
      <span style="color:var(--text-muted);font-size:12px;">［선택］을 누르면 지금 보고 있는 화면에 적힙니다</span>
      <button type="button" class="am-x" onclick="closeAddrManager()">&times;</button>
    </div>

    <div class="am-body" id="addrList"></div>

    {{-- 새로 넣는 자리. 우편번호ㆍ도로명은 찾아서 채우고 상세만 사람이 적는다 —
         거래처 정보 칸과 같은 방식이다. --}}
    <div class="am-new">
      <div class="am-new-cap" id="addrFormCap">주소 추가</div>
      <input type="hidden" id="addrEditId" value="">
      <div class="am-line">
        <input type="text" class="form-control" id="addrPostcode" readonly placeholder="우편번호" style="width:110px;flex:none;">
        <input type="text" class="form-control" id="addrRoad" readonly placeholder="도로명 주소">
        <button type="button" class="btn btn-outline btn-sm" onclick="addrFind()">
          <i class="fa-solid fa-magnifying-glass"></i> 주소 검색
        </button>
      </div>
      <input type="text" class="form-control" id="addrDetail" placeholder="상세 주소" style="margin-top:8px;">
      <div class="am-acts">
        {{-- 늘 서 있다. 「수정」을 누른 뒤에만 세워 두었더니, 고치다 말고 새 주소를
             넣으려는 사람이 빠져나올 길을 찾지 못했다(2026-09-09). --}}
        <button type="button" class="ds-btn" id="addrCancelBtn" onclick="addrFormReset()">초기화</button>
        <button type="button" class="ds-btn ds-btn-primary" id="addrSaveBtn" onclick="addrSave()">추가</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  /* ── 주소 관리 (2026-09-08 확인요청 6쪽) ─────────────
     거래처 하나에 주소 여러 벌. 맨 위가 지금 쓰는 주소이고, 서류ㆍ팩스ㆍ배송지가
     모두 그것을 읽는다. 여태 거래처 칸이 바뀔 때 저절로 쌓이기만 하고 손볼 길이
     없었다 — 집과 직장을 미리 넣어 둘 수도, 잘못 적힌 한 줄을 고칠 수도 없었다. */
  /* 어느 거래처의 주소인가는 **열 때** 정해진다. 거래처 수정 창(주문 등록)은
     누구를 고칠지 그때 알기 때문이다 — 화면을 세울 때 박아 두면 그 창에서는 늘
     엉뚱한 사람의 주소가 열린다. */
  let _amUrl  = '';
  let _amRows = [];

  const _amEsc = (v) => String(v ?? '').replace(/[&<>"']/g,
    c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

  window.openAddrManager = function (patientId) {
    if (!patientId) { showToast('먼저 거래처를 저장한 뒤에 주소를 관리할 수 있습니다.', 'warning'); return; }
    _amUrl = '{{ url('patients') }}/' + patientId + '/addresses';
    document.getElementById('addrModal').style.display = 'flex';
    addrFormReset();
    addrLoad();
  };

  window.closeAddrManager = function () {
    document.getElementById('addrModal').style.display = 'none';
  };

  async function addrLoad() {
    const box = document.getElementById('addrList');
    box.innerHTML = '<div style="padding:16px;color:var(--text-muted);font-size:12px;">불러오는 중…</div>';

    try {
      const res = await fetch(_amUrl, { headers: { Accept: 'application/json' } });
      const d = await res.json();
      _amRows = d.rows || [];
    } catch (e) {
      box.innerHTML = '<div style="padding:16px;color:var(--danger);font-size:12px;">주소를 불러오지 못했습니다.</div>';
      return;
    }

    const cnt = document.getElementById('addrCount');
    if (cnt) cnt.textContent = _amRows.length;

    if (!_amRows.length) {
      box.innerHTML = '<div style="padding:16px;color:var(--text-muted);font-size:12px;">등록된 주소가 없습니다.</div>';
      return;
    }

    /* 「현재」는 자리가 아니라 거래처 칸과 같은 줄에 붙는다 — 손으로 넣을 수 있게
       되면서 맨 윗줄이 곧 현재라는 잣대가 깨졌다(2026-09-09) */
    box.innerHTML = _amRows.map((r) => `
      <div class="am-row">
        <span class="am-full">${r.current ? '<b class="am-now">현재</b>' : ''}${_amEsc(r.full)}</span>
        <span class="am-when">${_amEsc(r.at)}${r.by ? ' · ' + _amEsc(r.by) : ''}</span>
        <button type="button" class="am-mini" onclick="addrPick(${r.id})">선택</button>
        <button type="button" class="am-mini" onclick="addrEdit(${r.id})">수정</button>
        <button type="button" class="am-mini danger" onclick="addrDelete(${r.id})">삭제</button>
      </div>`).join('');
  }

  /* 넣는 자리를 비운다 — 고치던 것을 그만둘 때도 여기로 돌아온다 */
  window.addrFormReset = function () {
    document.getElementById('addrEditId').value = '';
    ['addrPostcode', 'addrRoad', 'addrDetail'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('addrFormCap').textContent = '주소 추가';
    document.getElementById('addrSaveBtn').textContent = '추가';
  };

  window.addrEdit = function (id) {
    const r = _amRows.find(x => x.id === id);
    if (!r) return;
    document.getElementById('addrEditId').value    = id;
    document.getElementById('addrPostcode').value  = r.postcode || '';
    document.getElementById('addrRoad').value      = r.address  || '';
    document.getElementById('addrDetail').value    = r.detail   || '';
    document.getElementById('addrFormCap').textContent = '주소 수정';
    document.getElementById('addrSaveBtn').textContent = '저장';
  };

  /* 우편번호ㆍ도로명은 찾아서 채운다 — 손으로 적으면 공단에 낼 때 걸린다 */
  window.addrFind = function () {
    new daum.Postcode({
      oncomplete: (data) => {
        document.getElementById('addrPostcode').value = data.zonecode;
        document.getElementById('addrRoad').value     = data.roadAddress || data.jibunAddress;
        document.getElementById('addrDetail').focus();
      },
    }).open();
  };

  window.addrSave = async function () {
    const id   = document.getElementById('addrEditId').value;
    const body = {
      postcode:       document.getElementById('addrPostcode').value.trim() || null,
      address:        document.getElementById('addrRoad').value.trim(),
      address_detail: document.getElementById('addrDetail').value.trim() || null,
    };

    if (!body.address) { showToast('주소를 검색하여 입력하십시오.', 'warning'); return; }

    const btn = document.getElementById('addrSaveBtn');
    BtnState.loading(btn, '저장 중...');
    try {
      const res = await apiRequest(id ? `${_amUrl}/${id}` : _amUrl, id ? 'PUT' : 'POST', body);
      if (!res?.success) throw new Error(res?.message || '저장하지 못했습니다.');
      showToast(id ? '주소를 수정했습니다.' : '주소를 등록했습니다.', 'success');
      addrFormReset();
      await addrLoad();
    } catch (e) {
      showToast(e.message || '저장하지 못했습니다.', 'danger');
    } finally {
      BtnState.reset(btn);
    }
  };

  window.addrDelete = async function (id) {
    const r = _amRows.find(x => x.id === id);
    const NL = String.fromCharCode(10);
    if (!await ceConfirm('이 주소를 지우시겠습니까?' + NL + NL + (r ? r.full : ''),
                         { tone: 'danger', confirmText: '삭제', cancelText: '취소' })) return;
    try {
      const res = await apiRequest(`${_amUrl}/${id}`, 'DELETE');
      if (!res?.success) throw new Error(res?.message || '삭제하지 못했습니다.');
      showToast('주소를 삭제했습니다.', 'success');
      await addrLoad();
    } catch (e) {
      showToast(e.message || '삭제하지 못했습니다.', 'danger');
    }
  };

  /* 고른 것을 지금 쓰는 주소로 세운다 — 서류ㆍ팩스ㆍ배송지가 모두 이 값을 읽는다 */
  /**
   * 고른 주소를 지금 보고 있는 화면에 적용한다 (2026-09-15 지시).
   *
   * 이 창이 하는 일은 셋이다 — 고르고(선택), 고치고(수정), 지운다(삭제).
   * 「사용」은 걷었다 (2026-09-15 지시). 그것은 거래처의 **현재 주소**를 옮기는
   * 일이라 이 창의 결이 아니었다 — 여기서 바라는 것은 「이 건에 이 주소를 쓴다」
   * 하나인데, 누르면 다음에 그 사람에게 보낼 때도 따라와 뜻하지 않은 곳이 바뀌었다.
   * 현재 주소를 옮기는 일은 거래처 수정에서 한다(그쪽이 그 값의 정본이다).
   *
   * **화면을 새로 고치지 않는다.** 여태 「사용」이 location.reload() 를 불러, 주소
   * 하나 고르면 보고 있던 탭이 첫 탭으로 돌아갔다. 적을 것이 남은 채로 돌아가면
   * 무엇을 하던 중이었는지 다시 찾아야 한다.
   */
  window.addrPick = function (id) {
    const r = _amRows.find(x => x.id === id);
    if (!r) return;

    /* 어느 화면에서 열었느냐에 따라 채울 칸이 다르다.
         주문 제품 탭  shipping*      — 이 주문의 배송지
         상세 목록 탭  f-postcode …   — 거래처의 주소 칸
       열려 있는 쪽에만 적는다 — 둘 다 있으면 둘 다 맞춘다. */
    const 적기 = (id, 값) => {
      const el = document.getElementById(id);
      if (!el) return false;
      el.value = 값 ?? '';
      el.dispatchEvent(new Event('input',  { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    };

    const 주문쪽 = 적기('shippingPostcode', r.postcode)
                 | 적기('shippingAddr',     r.address)
                 | 적기('shippingAddrDetail', r.detail);

    const 상세쪽 = 적기('f-postcode',       r.postcode)
                 | 적기('f-address',        r.address)
                 | 적기('f-address-detail', r.detail);

    if (!주문쪽 && !상세쪽) {
      showToast('주소를 입력할 항목을 찾지 못했습니다.', 'warning');
      return;
    }

    /* 배송지가 바뀌면 연계 단추의 색도 다시 셈한다 — 주소가 생겼으므로 보낼 차례다 */
    if (typeof syncOrderStepBtns === 'function') syncOrderStepBtns();

    closeAddrManager();
    showToast('주소를 적용했습니다 — ' + (r.full || ''), 'success');
  };

})();
</script>
@endpush
