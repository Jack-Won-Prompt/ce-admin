{{-- 처방자료 업로드 — 앱의 prescription_upload_screen 을 그대로 옮긴다 (2026-09-25 지시).

     **한 번 누르면 한 처방전이다** — 첫 장이 새 건을 열고(new_batch=1), 둘째 장부터는
     그 번호에 붙인다(rx_number).

     2026-09-25 1:1 정합성 검증으로 고친 것 — 아래 첫 줄이 가장 크다:
       · 새 번호는 답의 **prescription_id** 에 담겨 온다. rx_number 를 보던 탓에
         번호를 못 쥐고, **장마다 new_batch=1 로 올라가 한 장에 한 건씩 갈렸다**
       · 처방전을 맨 앞에 세워 올린다 — 그 건의 본 그림이 처방전이라야 한다
       · 한 장이라도 실패하면 거기서 멈춘다. 올라간 것만 목록에서 덜어 내고,
         남은 것은 같은 번호로 이어서 올린다
       · 다 올라가면 화면을 모두 비운다(환자ㆍ서류ㆍ메모) — 앞사람 이름이 남으면
         다음 사람 서류가 엉뚱한 환자에게 붙는다. 상세로 넘어가지 않는다
       · 유형을 먼저 고르고 담는다 — 담은 뒤 줄마다 고치는 것이 아니다
       · 처방전ㆍ등록신청서는 한 번에 한 건, 모두 40건까지 (웹 업로드와 같은 잣대)
       · 이름은 두 자 이상, 「검색」을 눌러 찾는다. 찾은 사람은 아래 판에서 고른다
       · 앱과 같은 말을 쓴다 — 이 화면에서는 「환자」다 --}}
@extends('layouts.mobile')

@section('title', '처방전 업로드')
@section('subtitle', '카메라 촬영 또는 갤러리 선택')

@section('body')
  {{-- ① 환자 --}}
  <div class="m-card">
    <div class="up-h">이름</div>

    <div id="upPicked" style="display:none; align-items:center; gap:8px; padding:10px 12px;
         border-radius:11px; background:rgba(21,101,192,.08); margin-bottom:10px;">
      <i class="bx bx-check-circle" style="font-size:19px; color:var(--m-primary);"></i>
      <b id="upPickedName" style="flex:1; font-size:13.5px; color:var(--m-primary);"></b>
      <button style="background:none; border:0; color:var(--m-sub); font-size:20px; cursor:pointer;"
              onclick="upClearPatient()" aria-label="선택 해제"><i class="bx bx-x"></i></button>
    </div>

    <div style="display:flex; gap:8px;">
      <input class="m-input" id="upName" placeholder="환자 이름으로 검색" style="flex:1;"
             autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();upSearch();}">
      <button class="m-btn" id="upFind" style="width:auto; padding:0 18px;" onclick="upSearch()">
        <span id="upFindTxt">검색</span>
      </button>
    </div>
  </div>

  {{-- ② 서류 유형 --}}
  <div class="m-card">
    <div class="up-h">서류 유형</div>
    <select class="m-select" id="upType" onchange="upTypeChanged()"></select>
  </div>

  {{-- ③ 찍어 담는 자리 --}}
  <div class="m-card" style="text-align:center; padding:20px 14px;">
    <div style="font-size:14px; font-weight:700; color:#0D1B3E;" id="upAddTi">서류로 추가합니다</div>
    <div style="font-size:12px; color:var(--m-mute); margin:4px 0 14px;">유형을 변경하며 여러 건을 추가할 수 있습니다</div>
    <div style="display:flex; gap:8px;">
      <button class="m-btn ghost" onclick="document.getElementById('upCam').click()">
        <i class="bx bx-camera"></i> 카메라
      </button>
      <button class="m-btn ghost" onclick="document.getElementById('upPic').click()">
        <i class="bx bx-image"></i> 갤러리
      </button>
    </div>
    <input type="file" id="upCam" accept="image/*" capture="environment" hidden onchange="upAdd(this)">
    <input type="file" id="upPic" accept="image/*"                       hidden onchange="upAdd(this)">
  </div>

  {{-- ④ 담아 둔 서류 --}}
  <div class="m-card" id="upQueueCard" style="display:none;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
      <span class="up-h" style="margin:0; flex:1;">추가된 서류</span>
      <span class="m-badge gray" id="upCount">0건</span>
    </div>
    <div id="upQueue"></div>
  </div>

  {{-- ⑤ 메모 --}}
  <div class="m-card">
    <div class="up-h">관리자 메모</div>
    <textarea class="m-textarea" id="upMemo" rows="3" maxlength="500"
              placeholder="담당자에게 전달할 내용을 입력해 주십시오&#10;예) 청구 관련 특이사항을 기재해 주십시오"></textarea>
  </div>

  <button class="m-btn" id="upBtn" onclick="upAll()" disabled>
    <i class="bx bx-cloud-upload"></i> <span id="upBtnTxt">업로드</span>
  </button>
  <div id="upState" style="text-align:center; font-size:13px; color:var(--m-sub); padding:10px 0 4px;"></div>
  <div id="upFail" style="font-size:13px; color:var(--m-danger); line-height:1.6; padding:0 2px 24px;"></div>
@endsection

@push('scripts')
{{-- 찾은 사람 고르기 --}}
<div class="m-sheet" id="upFound">
  <div class="m-grab"></div>
  <h2 id="upFoundTi">이미 등록된 환자</h2>
  <p class="desc">생년월일로 동일인 여부를 확인한 뒤 선택해 주십시오.</p>
  <div id="upFoundList" style="max-height:46vh; overflow-y:auto;"></div>
  <button class="m-btn ghost" style="margin-top:12px; border-color:var(--m-primary); color:var(--m-primary);"
          onclick="mSheetClose(); upNewOpen();">
    <i class="bx bx-user-plus"></i> 해당 환자가 없습니다 — 신규 등록
  </button>
</div>

{{-- 환자 등록 --}}
<div class="m-sheet" id="upNew">
  <div class="m-grab"></div>
  <h2>환자 등록</h2>
  <p class="desc">검색된 환자가 없습니다. 신규 등록하시겠습니까?</p>
  <div class="m-field">
    <label class="m-label" for="nwName">이름</label>
    <input class="m-input" id="nwName" autocomplete="off">
  </div>
  <div class="m-field">
    <label class="m-label" for="nwRn">주민등록번호</label>
    <input class="m-input" id="nwRn" placeholder="XXXXXX-XXXXXXX" inputmode="numeric" maxlength="14">
  </div>
  <div style="display:flex; gap:8px;">
    <button class="m-btn ghost" onclick="mSheetClose()">취소</button>
    <button class="m-btn" id="nwBtn" onclick="upNewSave()">등록</button>
  </div>
</div>

<style>
  .up-h { font-size:13px; font-weight:700; color:#546E7A; margin-bottom:8px; }
  .up-row { display:flex; align-items:center; gap:10px; padding:11px 0; border-top:1px solid var(--m-line); }
  .up-row:first-child { border-top:0; }
  .up-p { display:flex; align-items:center; gap:10px; padding:12px 0; border-top:1px solid var(--m-line); }
</style>

<script>
  /* 서버가 목록을 안 주면 이 넷으로 버틴다 — 앱의 _fallbackDocTypes 와 같다 */
  let up갈래들 = [
    { code:'registration_form', label:'등록신청서' },
    { code:'prescription',      label:'처방전' },
    { code:'test_result',       label:'결과지' },
    { code:'id_card',           label:'신분증' },
  ];
  /* 유형마다 한 번에 올릴 수 있는 수 — 웹 업로드(overDocLimit)와 같은 잣대 */
  const UP_LIMIT = { prescription: 1, registration_form: 1 };
  const UP_MAX   = 40;

  let up갈래 = 'registration_form';
  let up환자 = null;
  let up담긴것 = [];          // { file, 이름, 갈래, 이름표 }
  let up묶음 = null;          // 이번 업로드가 연 처방전 번호
  let up올리는중 = false;
  let up찾은것 = [];          // 마지막 검색 결과 — 고를 때 번호로 짚는다

  function up이름표(code) {
    const t = up갈래들.find(x => x.code === code);
    return t ? t.label : code;
  }

  /* 「처방전는」이 되지 않게 받침을 본다 — 앱의 _josa 와 같다 */
  function up조사(말, 있을때, 없을때) {
    if (!말) return 없을때;
    const c = 말.charCodeAt(말.length - 1);
    if (c < 0xAC00 || c > 0xD7A3) return 없을때;
    return ((c - 0xAC00) % 28 !== 0) ? 있을때 : 없을때;
  }

  function up갈래그리기() {
    const sel = document.getElementById('upType');
    sel.innerHTML = up갈래들.map(t =>
      `<option value="${mEsc(t.code)}" ${t.code === up갈래 ? 'selected' : ''}>${mEsc(t.label)}</option>`).join('');
    document.getElementById('upAddTi').textContent =
      `${up이름표(up갈래)}${up조사(up이름표(up갈래), '으로', '로')} 추가합니다`;
  }

  function upTypeChanged() {
    up갈래 = document.getElementById('upType').value;
    up갈래그리기();
  }

  (async () => {
    try {
      const d = await mApi('/prescriptions/doc-types');
      if (d?.data?.length) {
        up갈래들 = d.data;
        if (!up갈래들.some(t => t.code === up갈래)) up갈래 = up갈래들[0].code;
      }
    } catch (e) { /* 못 받아도 기본 목록으로 버틴다 */ }
    up갈래그리기();
  })();

  /* ── 환자 ─────────────────────────────────────── */
  async function upSearch() {
    const q = document.getElementById('upName').value.trim();
    if (q.length < 2) { mTell('이름을 두 자 이상 입력해 주십시오.'); return; }

    const 단추 = document.getElementById('upFind');
    단추.disabled = true;
    document.getElementById('upFindTxt').textContent = '검색 중';
    up환자 = null; up묶음 = null; up살피기();

    try {
      const d = await mApi('/patients/search?' + new URLSearchParams({ q }));
      const 줄 = d.patients || [];
      if (!줄.length) { upNewOpen(); return; }

      document.getElementById('upFoundTi').textContent = `이미 등록된 환자 ${줄.length}명`;
      up찾은것 = 줄;
      document.getElementById('upFoundList').innerHTML = 줄.map((p, i) => `
        <div class="up-p tap" onclick="upPickAt(${i})">
          <i class="bx bx-user-circle" style="font-size:26px; color:var(--m-mute);"></i>
          <div style="flex:1; min-width:0;">
            <div style="font-size:14px; font-weight:600;">${mEsc(p.name)}</div>
            <div style="font-size:12px; color:var(--m-sub); margin-top:2px;">
              <i class="bx bx-cake"></i> ${mEsc(p.birth_date || '생년월일 없음')}
              &nbsp;·&nbsp; ${mEsc(p.mobile || '-')}
            </div>
          </div>
          <i class="bx bx-chevron-right" style="color:var(--m-mute);"></i>
        </div>`).join('');
      mSheetOpen('upFound');
    } catch (e) {
      mTell('검색하지 못했습니다: ' + e.message, 'bad');
    } finally {
      단추.disabled = false;
      document.getElementById('upFindTxt').textContent = '검색';
    }
  }

  function upPickAt(i) {
    const p = up찾은것[i];
    if (p) upPick(p.id, p.name);
  }

  function upPick(id, 이름) {
    up환자 = { id, name: 이름 };
    document.getElementById('upName').value = 이름;
    document.getElementById('upPickedName').textContent = `${이름} 님 선택됨`;
    document.getElementById('upPicked').style.display = 'flex';
    mSheetClose();
    up살피기();
  }

  function upClearPatient() {
    up환자 = null;
    document.getElementById('upPicked').style.display = 'none';
    up살피기();
  }

  function upNewOpen() {
    document.getElementById('nwName').value = document.getElementById('upName').value.trim();
    document.getElementById('nwRn').value = '';
    mSheetOpen('upNew');
  }

  async function upNewSave() {
    const 이름 = document.getElementById('nwName').value.trim();
    const 주민 = document.getElementById('nwRn').value.replace(/\D/g, '');
    if (!이름) { mTell('이름을 입력해 주십시오.'); return; }

    const 단추 = document.getElementById('nwBtn');
    단추.disabled = true;
    try {
      const d = await mApi('/patients', {
        method: 'POST',
        body: 주민 ? { name: 이름, resident_no: 주민 } : { name: 이름 },
      });
      const p = d.patient || {};
      mSheetClose();
      upPick(p.id, p.name);
      mTell(d.message || `${p.name} 님이 등록되었습니다.`, 'ok');
    } catch (e) {
      mTell('등록하지 못했습니다: ' + e.message, 'bad');
    } finally { 단추.disabled = false; }
  }

  /* ── 서류 담기 ────────────────────────────────── */
  function upAdd(input) {
    const 파일들 = Array.from(input.files || []);
    input.value = '';
    for (const f of 파일들) {
      const 이름표 = up이름표(up갈래);

      if (up담긴것.length >= UP_MAX) { mTell(`한 번에 최대 ${UP_MAX}건까지 추가할 수 있습니다.`); break; }

      const 한도 = UP_LIMIT[up갈래];
      if (한도 != null) {
        const 이미 = up담긴것.filter(d => d.갈래 === up갈래).length;
        if (이미 >= 한도) {
          mTell(`${이름표}${up조사(이름표, '은', '는')} 한 번에 ${한도}건까지 업로드할 수 있습니다.`);
          continue;
        }
      }
      up담긴것.push({ file: f, 이름: f.name, 갈래: up갈래, 이름표: 이름표 });
    }
    up줄그리기();
  }

  function up줄그리기() {
    document.getElementById('upQueueCard').style.display = up담긴것.length ? '' : 'none';
    document.getElementById('upCount').textContent = up담긴것.length + '건';
    document.getElementById('upQueue').innerHTML = up담긴것.map((d, i) => `
      <div class="up-row">
        <i class="bx bx-image" style="font-size:24px; color:var(--m-mute);"></i>
        <div style="flex:1; min-width:0;">
          <div style="font-size:12px; font-weight:700; color:var(--m-primary);">${mEsc(d.이름표)}</div>
          <div style="font-size:12.5px; color:var(--m-sub); margin-top:2px; overflow:hidden;
                      text-overflow:ellipsis; white-space:nowrap;">${mEsc(d.이름)}</div>
        </div>
        <button style="background:none; border:0; color:var(--m-danger); font-size:21px; cursor:pointer;"
                ${up올리는중 ? 'disabled' : ''} onclick="upDrop(${i})" aria-label="빼기"><i class="bx bx-x"></i></button>
      </div>`).join('');
    up살피기();
  }

  function upDrop(i) {
    up담긴것.splice(i, 1);
    /* 남겨 둔 것을 모두 빼면 그 업로드는 끝난 것이다 — 번호를 쥔 채로 새 서류를
       담으면 앞 업로드의 처방전에 붙는다 */
    if (!up담긴것.length) up묶음 = null;
    up줄그리기();
  }

  function up살피기() {
    document.getElementById('upBtn').disabled = !(up환자 && up담긴것.length) || up올리는중;
  }

  function up비우기() {
    up담긴것 = [];
    up묶음 = null;
    up환자 = null;
    up갈래 = up갈래들[0].code;
    document.getElementById('upName').value = '';
    document.getElementById('upMemo').value = '';
    document.getElementById('upPicked').style.display = 'none';
    up갈래그리기();
    up줄그리기();
  }

  /* ── 한 번에 올리기 ───────────────────────────── */
  async function upAll() {
    if (!up환자 || !up담긴것.length || up올리는중) return;

    /* 처방전을 맨 앞에 세운다 — 그 건의 본 그림이 처방전이라야 한다 */
    const 차례 = [
      ...up담긴것.filter(d => d.갈래 === 'prescription'),
      ...up담긴것.filter(d => d.갈래 !== 'prescription'),
    ];

    up올리는중 = true;
    up살피기();
    document.getElementById('upFail').textContent = '';
    document.getElementById('upBtnTxt').textContent = '업로드 중…';

    const 알림 = document.getElementById('upState');
    const 메모 = document.getElementById('upMemo').value.trim();
    const 보낸것 = [];
    let 탈 = null;

    for (const d of 차례) {
      알림.textContent = `서류 업로드 중… ${보낸것.length + 1}/${차례.length}`;

      const fd = new FormData();
      fd.append('prescription_image', d.file, d.이름);
      fd.append('patient_id', up환자.id);
      fd.append('doc_type', d.갈래);
      if (up묶음 == null) fd.append('new_batch', '1'); else fd.append('rx_number', up묶음);
      /* 메모는 첫 건에만 싣는다 — 건마다 보내면 같은 말이 여러 장에 남는다 */
      if (메모 && !보낸것.length) fd.append('memo', 메모);

      try {
        const r = await mApi('/prescriptions/upload', { method: 'POST', body: fd });
        보낸것.push(d);
        /* 새 번호는 prescription_id 로 온다 */
        if (up묶음 == null) up묶음 = r?.prescription_id ?? null;
      } catch (e) {
        탈 = `${d.이름표}을(를) 업로드하지 못했습니다: ${e.message}`;
        break;   /* 앱과 같이 거기서 멈춘다 */
      }
    }

    /* 올라간 것만 덜어 낸다 — 그대로 두고 다시 누르면 같은 서류가 두 번 올라간다 */
    up담긴것 = up담긴것.filter(d => !보낸것.includes(d));
    up올리는중 = false;
    알림.textContent = '';
    document.getElementById('upBtnTxt').textContent = '업로드';

    if (!탈) {
      const rx = up묶음;
      up비우기();
      mTell(rx ? `처방전 ${rx} 에 ${보낸것.length}건을 등록했습니다.`
               : `${보낸것.length}건을 등록했습니다.`, 'ok');
      return;
    }

    up줄그리기();
    document.getElementById('upFail').textContent = 보낸것.length === 0
      ? 탈
      : `${차례.length}건 중 ${보낸것.length}건을 업로드했습니다. 남은 ${up담긴것.length}건은 목록에 유지됩니다. ` +
        `다시 업로드하면 같은 처방전(${up묶음})에 이어서 업로드됩니다 — ${탈}`;
  }

  up줄그리기();
</script>
@endpush
