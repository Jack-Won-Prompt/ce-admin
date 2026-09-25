{{-- 처방자료 업로드 — 앱의 prescription_upload_screen 을 그대로 옮긴다 (2026-09-25 지시).

     앱에 있는 것: 거래처 찾기ㆍ신규 등록ㆍ파일 여러 장 담기ㆍ장마다 갈래 지정ㆍ
     담긴 것 빼기ㆍ관리자 메모ㆍ한 번에 올리기ㆍ올린 뒤 결과 보기.

     **한 번 누르면 한 처방전이다** — 첫 장이 새 건을 열고(new_batch=1), 둘째 장부터는
     그 번호에 붙인다(rx_number). 앱과 같은 계약이라 결과가 갈릴 수 없다. --}}
@extends('layouts.mobile')

@section('title', '처방자료 업로드')
@section('subtitle', '거래처를 고르고 서류를 올립니다')

@section('body')
  {{-- ① 거래처 --}}
  <div class="m-card">
    <div style="font-weight:700; font-size:14.5px; margin-bottom:10px;">
      <i class="bx bx-user"></i> 거래처
    </div>

    <div id="picked" style="display:none; align-items:center; gap:10px; padding:11px; border-radius:11px;
         background:var(--m-primary-l); margin-bottom:10px;">
      <i class="bx bxs-user-circle" style="font-size:26px; color:var(--m-primary);"></i>
      <div style="flex:1; min-width:0;">
        <b id="pickedName" style="font-size:14.5px;"></b>
        <div id="pickedSub" style="font-size:12px; color:var(--m-sub);"></div>
      </div>
      <button class="m-head-btn" style="background:#fff; color:var(--m-sub);"
              onclick="거래처지우기()" aria-label="선택 해제"><i class="bx bx-x"></i></button>
    </div>

    <div id="pickWrap" style="display:flex; gap:8px;">
      <input class="m-input" id="ptQ" placeholder="이름 또는 연락처로 검색" style="flex:1;"
             oninput="ptChanged()" autocomplete="off">
      <button class="m-btn ghost" style="width:auto; padding:0 14px; white-space:nowrap;"
              onclick="신규열기()">신규</button>
    </div>
    <div id="ptList"></div>
  </div>

  {{-- ② 서류 --}}
  <div class="m-card">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
      <span style="font-weight:700; font-size:14.5px;"><i class="bx bx-paperclip"></i> 올릴 서류</span>
      <span class="m-badge gray" id="qCount">0건</span>
    </div>

    <div id="queue"></div>

    <div style="display:flex; gap:8px; margin-top:10px;">
      <button class="m-btn ghost" onclick="document.getElementById('upCam').click()">
        <i class="bx bx-camera"></i> 카메라
      </button>
      <button class="m-btn ghost" onclick="document.getElementById('upPic').click()">
        <i class="bx bx-image"></i> 갤러리
      </button>
    </div>
    <input type="file" id="upCam" accept="image/*" capture="environment" multiple hidden onchange="담기(this)">
    <input type="file" id="upPic" accept="image/*,application/pdf"      multiple hidden onchange="담기(this)">
  </div>

  {{-- ③ 메모 --}}
  <div class="m-card">
    <label class="m-label" for="upMemo">관리자 메모 (선택)</label>
    <textarea class="m-textarea" id="upMemo" placeholder="처방전 관련 메모"></textarea>
  </div>

  <button class="m-btn" id="upBtn" onclick="모두올리기()" disabled>
    <i class="bx bx-upload"></i> 등록
  </button>
  <div id="upState" style="text-align:center; font-size:13px; color:var(--m-sub); padding:10px 0 24px;"></div>
@endsection

@push('scripts')
{{-- 거래처 신규 --}}
<div class="m-sheet" id="newSheet">
  <div class="m-grab"></div>
  <h2>거래처 신규 등록</h2>
  <p class="desc">이름은 필수입니다. 주민등록번호는 13자리를 모두 적거나 비워 두십시오.</p>
  <div class="m-field">
    <label class="m-label" for="nwName">이름</label>
    <input class="m-input" id="nwName" placeholder="이름" autocomplete="off">
  </div>
  <div class="m-field">
    <label class="m-label" for="nwRn">주민등록번호 (선택)</label>
    <input class="m-input" id="nwRn" placeholder="9001011234567" inputmode="numeric" maxlength="14">
  </div>
  <button class="m-btn" id="nwBtn" onclick="신규등록()">등록하고 선택</button>
</div>

<script>
  let 고른거래처 = null;
  let 담긴것 = [];           // { file, 이름, 갈래 }
  let 갈래들 = [{ code:'prescription', label:'처방전' }, { code:'registration_form', label:'등록신청서' },
                { code:'test_result', label:'결과지' }, { code:'id_card', label:'신분증' },
                { code:'privacy_consent', label:'개인정보 동의서' }, { code:'other', label:'기타' }];
  let ptTimer = null;

  (async () => {
    try { const d = await mApi('/prescriptions/doc-types'); if (d?.data?.length) 갈래들 = d.data; }
    catch (e) { /* 못 받아도 기본 목록으로 버틴다 */ }
    줄그리기();
  })();

  /* ── 거래처 ───────────────────────────────────── */
  function ptChanged() {
    clearTimeout(ptTimer);
    ptTimer = setTimeout(거래처찾기, 380);
  }

  async function 거래처찾기() {
    const q = document.getElementById('ptQ').value.trim();
    const 통 = document.getElementById('ptList');
    if (q.length < 2) { 통.innerHTML = ''; return; }
    통.innerHTML = '<div class="m-spin" style="margin:16px auto;"></div>';
    try {
      const d = await mApi('/patients/search?' + new URLSearchParams({ q }));
      const 줄 = d.data || d.patients || [];
      통.innerHTML = 줄.length ? 줄.map(p => `
        <div style="display:flex; align-items:center; gap:10px; padding:11px 0; border-top:1px solid var(--m-line);"
             onclick="거래처고르기(${p.id}, ${JSON.stringify(p.name)}, ${JSON.stringify(p.mobile || p.phone || '')})">
          <i class="bx bx-user-circle" style="font-size:24px; color:var(--m-mute);"></i>
          <div style="flex:1;"><b style="font-size:14.5px;">${mEsc(p.name)}</b>
            <div style="font-size:12px; color:var(--m-sub);">${mEsc(p.mobile || p.phone || '-')}</div></div>
          <i class="bx bx-chevron-right" style="color:var(--m-mute);"></i>
        </div>`).join('')
        : `<div style="padding:14px 0; color:var(--m-mute); font-size:13.5px;">
             찾은 사람이 없습니다. 「신규」로 등록하십시오.</div>`;
    } catch (e) { 통.innerHTML = `<div style="padding:12px 0; color:var(--m-danger); font-size:13px;">${mEsc(e.message)}</div>`; }
  }

  function 거래처고르기(id, 이름, 번호) {
    고른거래처 = { id, 이름, 번호 };
    document.getElementById('pickedName').textContent = 이름;
    document.getElementById('pickedSub').textContent  = 번호 || '-';
    document.getElementById('picked').style.display   = 'flex';
    document.getElementById('pickWrap').style.display = 'none';
    document.getElementById('ptList').innerHTML = '';
    단추살피기();
  }

  function 거래처지우기() {
    고른거래처 = null;
    document.getElementById('picked').style.display   = 'none';
    document.getElementById('pickWrap').style.display = 'flex';
    document.getElementById('ptQ').value = '';
    단추살피기();
  }

  function 신규열기() {
    document.getElementById('nwName').value = document.getElementById('ptQ').value.trim();
    document.getElementById('nwRn').value = '';
    mSheetOpen('newSheet');
  }

  async function 신규등록() {
    const 이름 = document.getElementById('nwName').value.trim();
    const 주민 = document.getElementById('nwRn').value.replace(/\D/g, '');
    if (!이름) { mTell('이름을 입력해 주십시오.', 'warn'); return; }
    if (주민 && 주민.length !== 13) { mTell('주민등록번호는 13자리를 모두 입력해 주십시오.', 'warn'); return; }

    const 단추 = document.getElementById('nwBtn');
    단추.disabled = true;
    try {
      const d = await mApi('/patients', { method: 'POST', body: 주민 ? { name: 이름, resident_no: 주민 } : { name: 이름 } });
      const p = d.patient || d.data;
      mSheetClose();
      거래처고르기(p.id, p.name, p.mobile || '');
      mTell(`${p.name} 님을 등록하고 선택했습니다.`, 'ok');
    } catch (e) { mTell(e.message, 'bad'); }
    finally { 단추.disabled = false; }
  }

  /* ── 서류 담기 ────────────────────────────────── */
  function 담기(input) {
    Array.from(input.files || []).forEach(f => 담긴것.push({ file: f, 이름: f.name, 갈래: '처방전' === '' ? '' : 짐작(f.name) }));
    input.value = '';
    줄그리기();
  }

  /* 파일 이름으로 갈래를 짐작한다 — 담당자가 고쳐 주면 그것이 이긴다 */
  function 짐작(이름) {
    const n = String(이름);
    if (/등록신청|신청서/.test(n)) return 'registration_form';
    if (/결과지|결과/.test(n))     return 'test_result';
    if (/신분증|주민|면허/.test(n)) return 'id_card';
    if (/동의/.test(n))            return 'privacy_consent';
    if (/처방/.test(n))            return 'prescription';
    return 'prescription';
  }

  function 줄그리기() {
    const 통 = document.getElementById('queue');
    document.getElementById('qCount').textContent = 담긴것.length + '건';
    통.innerHTML = 담긴것.length ? 담긴것.map((d, i) => `
      <div style="display:flex; align-items:center; gap:10px; padding:11px 0; border-top:1px solid var(--m-line);">
        <i class="bx ${/pdf$/i.test(d.이름) ? 'bxs-file-pdf' : 'bx-image'}"
           style="font-size:24px; color:var(--m-mute);"></i>
        <div style="flex:1; min-width:0;">
          <div style="font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${mEsc(d.이름)}</div>
          <select class="m-select" style="padding:7px 9px; font-size:13px; margin-top:5px;"
                  onchange="담긴것[${i}].갈래 = this.value">
            ${갈래들.map(t => `<option value="${mEsc(t.code)}" ${t.code === d.갈래 ? 'selected' : ''}>${mEsc(t.label)}</option>`).join('')}
          </select>
        </div>
        <button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger);"
                onclick="빼기(${i})" aria-label="빼기"><i class="bx bx-x"></i></button>
      </div>`).join('')
      : `<div style="padding:16px 0; color:var(--m-mute); font-size:13.5px;">담긴 서류가 없습니다.</div>`;
    단추살피기();
  }

  function 빼기(i) { 담긴것.splice(i, 1); 줄그리기(); }

  function 단추살피기() {
    document.getElementById('upBtn').disabled = !(고른거래처 && 담긴것.length);
  }

  /* ── 한 번에 올리기 ───────────────────────────── */
  async function 모두올리기() {
    if (!고른거래처 || !담긴것.length) return;

    const 단추 = document.getElementById('upBtn');
    const 알림 = document.getElementById('upState');
    단추.disabled = true;

    let 묶음번호 = null;          // 첫 장이 새 건을 연다
    const 못한것 = [];
    const 메모 = document.getElementById('upMemo').value.trim();

    for (let i = 0; i < 담긴것.length; i++) {
      const d = 담긴것[i];
      알림.textContent = `올리는 중… ${i + 1}/${담긴것.length}`;

      const fd = new FormData();
      fd.append('prescription_image', d.file, d.이름);
      fd.append('patient_id', 고른거래처.id);
      fd.append('doc_type', d.갈래);
      if (묶음번호) fd.append('rx_number', 묶음번호); else fd.append('new_batch', '1');
      if (메모 && i === 0) fd.append('memo', 메모);

      try {
        const r = await mApi('/prescriptions/upload', { method: 'POST', body: fd });
        if (!묶음번호) 묶음번호 = r?.rx_number || r?.data?.rx_number || null;
      } catch (e) { 못한것.push(`${d.이름} — ${e.message}`); }
    }

    단추.disabled = false;
    알림.textContent = '';

    if (묶음번호) {
      mTell(`${묶음번호} 업로드 완료`, 'ok');
      담긴것 = []; document.getElementById('upMemo').value = ''; 줄그리기();
      setTimeout(() => location.assign('/m/prescriptions/' + encodeURIComponent(묶음번호)), 900);
    } else {
      mTell(못한것[0] || '업로드하지 못했습니다.', 'bad');
    }
  }
</script>
@endpush
