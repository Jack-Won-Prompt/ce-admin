{{-- 처방전 상세 — 앱의 prescription_detail_screen 과 1:1 (2026-09-25 정합성 검증).

     앱에 있는 것을 하나도 빠뜨리지 않는다:
       처방전 이미지 · 등록 서류(N건) · 재업로드 요청(사유ㆍ비고) · 업로드 대기 서류(N건)
       · 서류 추가(갈래 + 카메라ㆍ사진 선택) · 검수 재요청(검수자 전달 메모)
       · 환자 정보 · 의료기관 · 상병 정보 · 처방 내용

     칸 이름은 앱 모델이 읽는 열쇠 그대로 쓴다(ocr_result 아래). 같은 응답을 같은
     이름으로 읽으므로 앱과 보이는 값이 갈릴 수 없다. --}}
@extends('layouts.mobile')

@section('title', '처방전 상세')
@section('subtitle', $rxNumber)
@section('back', true)

@section('body')
  <div id="rxDetail"><div class="m-spin"></div></div>
@endsection

@push('scripts')
{{-- 서류 추가 — 앱의 갈래 칩 + 카메라ㆍ사진 선택 --}}
<div class="m-sheet" id="addSheet">
  <div class="m-grab"></div>
  <h2>서류 추가</h2>
  <p class="desc" id="addDesc"></p>
  <div class="m-chips" id="docTypes" style="flex-wrap:wrap; overflow:visible;"></div>
  <p class="desc" id="addNote" style="margin:10px 0 14px;"></p>
  <div style="display:flex; gap:8px;">
    <button class="m-btn ghost" onclick="document.getElementById('camIn').click()">
      <i class="bx bx-camera"></i> 카메라</button>
    <button class="m-btn" onclick="document.getElementById('picIn').click()">
      <i class="bx bx-image"></i> 사진 선택</button>
  </div>
  <input type="file" id="camIn" accept="image/*" capture="environment" multiple hidden onchange="담기(this)">
  <input type="file" id="picIn" accept="image/*,application/pdf"      multiple hidden onchange="담기(this)">
</div>

{{-- 검수 재요청 — 앱의 「검수자 전달 메모 (선택)」 --}}
<div class="m-sheet" id="revSheet">
  <div class="m-grab"></div>
  <h2>검수 재요청</h2>
  <p class="desc">되물은 서류를 모두 올린 뒤 다시 요청하십시오.</p>
  <div class="m-field">
    <label class="m-label" for="revMemo">검수자 전달 메모 (선택)</label>
    <textarea class="m-textarea" id="revMemo" placeholder="검수자에게 남길 말"></textarea>
  </div>
  <button class="m-btn" id="revBtn" onclick="검수재요청()">재요청</button>
</div>

{{-- 크게 보기 --}}
<div class="m-sheet-back" id="viewBack" style="background:rgba(0,0,0,.92);" onclick="viewClose()">
  <img id="viewImg" alt="" style="position:absolute; inset:0; margin:auto; max-width:100%; max-height:100%; object-fit:contain;">
</div>

<script>
  const RX = @json($rxNumber);
  let 상세 = null, 대기 = [], 고른갈래 = 'registration_form';
  let 갈래들 = [{ code:'registration_form', label:'등록신청서' }, { code:'test_result', label:'결과지' },
                { code:'id_card', label:'신분증' }, { code:'privacy_consent', label:'개인정보 동의서' },
                { code:'other', label:'기타' }];

  function viewOpen(u) { document.getElementById('viewImg').src = u; document.getElementById('viewBack').classList.add('on'); }
  function viewClose() { document.getElementById('viewBack').classList.remove('on'); }

  /* 값이 있는 줄만 세운다 — 앱의 _Section 과 같다 */
  function 묶음(제목, 아이콘, 줄들) {
    const 있는것 = 줄들.filter(([, v]) => v !== null && v !== undefined && String(v).trim() !== '');
    if (!있는것.length) return '';
    return `
      <div class="m-card">
        <div style="font-weight:700; font-size:14.5px; margin-bottom:8px;">
          <i class="bx ${아이콘}"></i> ${mEsc(제목)}</div>
        ${있는것.map(([k, v]) => `
          <div style="display:flex; padding:6px 0; font-size:13.5px; gap:10px;">
            <span style="width:82px; flex:0 0 82px; color:var(--m-mute);">${mEsc(k)}</span>
            <b style="flex:1; word-break:break-word;">${mEsc(v)}</b></div>`).join('')}
      </div>`;
  }

  function 서류줄(a, 지울수있나) {
    const 얼굴 = a.is_pdf
      ? `<div style="width:52px;height:52px;border-radius:10px;background:#FEF0F0;display:flex;align-items:center;justify-content:center;flex:0 0 52px;">
           <i class="bxs-file-pdf bx" style="font-size:26px;color:var(--m-danger);"></i></div>`
      : `<img src="${mEsc(a.url)}" alt="" loading="lazy"
              style="width:52px;height:52px;border-radius:10px;object-fit:cover;background:#F2F4F7;flex:0 0 52px;">`;
    return `
      <div style="display:flex; align-items:center; gap:12px; padding:11px 0; border-top:1px solid var(--m-line);"
           onclick="${a.is_pdf ? `window.open('${mEsc(a.url)}','_blank')` : `viewOpen('${mEsc(a.url)}')`}">
        ${얼굴}
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; font-size:14.5px;">${mEsc(a.doc_label || '서류')}</div>
          <div style="font-size:12px; color:var(--m-mute); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            ${mEsc(a.file_name || '')}${a.uploader ? ' · ' + mEsc(a.uploader) : ''}</div>
        </div>
        ${지울수있나 ? `<button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger); flex:0 0 38px;"
             onclick="event.stopPropagation(); 서류지우기(${a.id}, ${JSON.stringify(a.doc_label || '서류')})"
             aria-label="삭제"><i class="bx bx-trash"></i></button>` : ''}
      </div>`;
  }

  function 그리기() {
    const p = 상세, o = p.ocr_result || {};
    const 뱃지 = { review_needed:['need','검수 필요'], review_requested:['req','검수 요청'],
                   approved:['done','검수 완료'], rejected:['need','반려'], ordered:['gray','주문 완료'] }
                 [p.status] || ['gray', p.status_label || p.status];
    const 서류 = p.attachments || [];
    const 되물음 = p.reupload_requests || [];

    document.getElementById('rxDetail').innerHTML = `
      ${p.image_url ? `
        <div class="m-card" style="padding:0; overflow:hidden;" onclick="viewOpen(${JSON.stringify(p.image_url)})">
          <img src="${mEsc(p.image_url)}" alt="처방전" loading="lazy"
               style="width:100%; display:block; background:#F2F4F7;"
               onerror="this.outerHTML='<div style=&quot;padding:40px; text-align:center; color:var(--m-mute); font-size:13.5px;&quot;>이미지를 불러올 수 없습니다.</div>'">
        </div>` : ''}

      <div class="m-card">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
          <b style="font-size:16px;">${mEsc(p.rx_number)}</b>
          <span style="flex:1"></span>
          <span class="m-badge ${뱃지[0]}">${mEsc(뱃지[1])}</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--m-sub);">
          <i class="bx bx-paperclip"></i> 등록 서류
          <span class="m-badge gray">${서류.length}건</span>
        </div>
        ${서류.length ? 서류.map(a => 서류줄(a, !!a.can_delete)).join('')
                      : '<div style="padding:16px 0; color:var(--m-mute); font-size:13.5px;">등록된 서류가 없습니다.</div>'}
      </div>

      ${(p.owner_name && p.is_mine === false) ? `
        <div class="m-card" style="background:var(--m-primary-l); border-color:#CFE2F8;">
          <div style="display:flex; gap:8px; font-size:13.5px; line-height:1.6;">
            <i class="bx bx-group" style="font-size:18px; color:var(--m-primary);"></i>
            <div><b>${mEsc(p.owner_name)}</b> 님이 등록한 처방전입니다.
                 서류를 추가할 수 있으며, 본인이 등록한 서류만 삭제할 수 있습니다.</div>
          </div></div>` : ''}

      ${되물음.length ? `
        <div class="m-card" style="background:#FFF6E8; border-color:#F5D8A8;">
          <div style="font-weight:700; font-size:14px; margin-bottom:6px; color:var(--m-warn);">
            <i class="bx bx-refresh"></i> 재업로드 요청</div>
          ${되물음.map(r => `
            <div style="font-size:13.5px; line-height:1.65; padding:4px 0;">
              · ${mEsc(r.doc_label)} — ${mEsc(r.reason)}
              ${r.memo ? `<div style="color:var(--m-sub); font-size:12.5px;">비고: ${mEsc(r.memo)}</div>` : ''}
              ${r.requested_by ? `<div style="color:var(--m-mute); font-size:11.5px;">${mEsc(r.requested_by)} · ${mEsc(mWhen(r.requested_at))}</div>` : ''}
            </div>`).join('')}
        </div>` : ''}

      ${대기.length ? `
        <div class="m-card" style="border-color:var(--m-primary); border-style:dashed;">
          <div style="display:flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; margin-bottom:6px;">
            <i class="bx bx-time-five" style="color:var(--m-primary);"></i> 업로드 대기 서류
            <span class="m-badge gray">${대기.length}건</span>
          </div>
          ${대기.map((d, i) => `
            <div style="display:flex; align-items:center; gap:10px; padding:9px 0; border-top:1px solid var(--m-line);">
              <i class="bx ${/pdf$/i.test(d.이름) ? 'bxs-file-pdf' : 'bx-image'}" style="font-size:22px; color:var(--m-mute);"></i>
              <div style="flex:1; min-width:0;">
                <div style="font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${mEsc(d.이름)}</div>
                <select class="m-select" style="padding:6px 9px; font-size:12.5px; margin-top:4px;"
                        onchange="대기[${i}].갈래 = this.value">
                  ${갈래들.map(t => `<option value="${mEsc(t.code)}" ${t.code === d.갈래 ? 'selected' : ''}>${mEsc(t.label)}</option>`).join('')}
                </select>
              </div>
              <button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger); flex:0 0 38px;"
                      onclick="제외(${i})" aria-label="제외"><i class="bx bx-x"></i></button>
            </div>`).join('')}
          <button class="m-btn" id="upBtn" onclick="대기올리기()" style="margin-top:10px;">
            <i class="bx bx-upload"></i> 서류 업로드 (${대기.length}건)</button>
        </div>` : ''}

      ${p.can_add !== false ? `
        <button class="m-btn ghost" onclick="addOpen()" style="margin-bottom:10px;">
          <i class="bx bx-image-add"></i> 서류 추가 (카메라 · 사진 선택)</button>` : ''}

      ${p.can_request_review ? `
        <button class="m-btn" onclick="mSheetOpen('revSheet')" style="margin-bottom:10px;">
          <i class="bx bx-check-shield"></i> 검수 재요청</button>` : ''}

      ${묶음('환자 정보', 'bx-user', [
        ['성명', o.patient_name], ['주민번호', o.resident_no],
        ['전화', o.phone || o.mobile], ['생년월일', o.birth_date || p.birth_date],
        ['재발행', o.is_reissue ? '예' : ''],
      ])}
      ${묶음('의료기관', 'bx-plus-medical', [
        ['병원명', o.hospital_name || o.hospital], ['병원 코드', o.hospital_code],
        ['의사', o.doctor_name], ['진료과', o.department || o.specialty],
        ['면허번호', o.license_no], ['전문의번호', o.specialist_no],
      ])}
      ${묶음('상병 정보', 'bx-clipboard', [
        ['상병명', o.disease_name], ['상병 코드', o.disease_code],
      ])}
      ${묶음('처방 내용', 'bx-receipt', [
        ['처방 기간', o.usage_period], ['1일 횟수', o.daily_count],
        ['총 일수', o.total_days], ['총 수량', o.total_count],
        ['발급일', o.issued_date], ['처방전 번호', o.registration_no], ['일련번호', o.serial_no],
      ])}
      <div style="height:16px;"></div>`;
  }

  async function 갈래불러오기() {
    try { const d = await mApi('/prescriptions/doc-types'); if (d?.data?.length) 갈래들 = d.data; }
    catch (e) { /* 못 받아도 기본 목록으로 버틴다 — 앱과 같다 */ }
  }

  async function 불러오기() {
    try {
      const d = await mApi('/prescriptions/' + encodeURIComponent(RX));
      상세 = d.data;
      그리기();
    } catch (e) {
      document.getElementById('rxDetail').innerHTML =
        `<div class="m-empty"><i class="bx bx-error"></i>불러오기 실패<div style="font-size:13px; margin-top:6px;">${mEsc(e.message)}</div>
           <div style="margin-top:14px;"><button class="m-btn" style="width:auto; padding:10px 18px;"
             onclick="불러오기()">다시 시도</button></div></div>`;
    }
  }

  /* ── 서류 추가 — 앱처럼 먼저 담고, 한 번에 올린다 ── */
  function addOpen() {
    document.getElementById('addDesc').textContent = RX + '에 추가할 서류 유형을 선택해 주십시오.';
    document.getElementById('addNote').textContent =
      (상세?.owner_name && 상세?.is_mine === false)
        ? `처방전은 ${상세.owner_name} 님만 등록할 수 있습니다. 그 외 서류는 추가할 수 있습니다.` : '';
    document.getElementById('docTypes').innerHTML = 갈래들
      .filter(t => t.code !== 'prescription')
      .map(t => `<button class="m-chip ${t.code === 고른갈래 ? 'on' : ''}" data-c="${mEsc(t.code)}"
                   onclick="갈래고르기(this)">${mEsc(t.label)}</button>`).join('');
    mSheetOpen('addSheet');
  }

  function 갈래고르기(b) {
    고른갈래 = b.dataset.c;
    document.querySelectorAll('#docTypes .m-chip').forEach(x => x.classList.toggle('on', x === b));
  }

  function 담기(input) {
    Array.from(input.files || []).forEach(f => 대기.push({ file: f, 이름: f.name, 갈래: 고른갈래 }));
    input.value = '';
    mSheetClose();
    그리기();
  }

  function 제외(i) { 대기.splice(i, 1); 그리기(); }

  async function 대기올리기() {
    if (!대기.length) return;
    const 단추 = document.getElementById('upBtn');
    단추.disabled = true;
    let 됨 = 0; const 못한것 = [];

    for (let i = 0; i < 대기.length; i++) {
      단추.innerHTML = `업로드 중… ${i + 1}/${대기.length}`;
      const fd = new FormData();
      fd.append('prescription_image', 대기[i].file, 대기[i].이름);
      fd.append('rx_number', RX);              // 이 건에 붙인다 — 새 건이 서지 않게
      fd.append('doc_type', 대기[i].갈래);
      try { await mApi('/prescriptions/upload', { method: 'POST', body: fd }); 됨++; }
      catch (e) { 못한것.push(`「${대기[i].이름}」 서류를 업로드하지 못했습니다: ${e.message}`); }
    }

    대기 = [];
    if (됨) mTell(`${됨}장을 올렸습니다.`, 'ok');
    if (못한것.length) mTell(못한것[0], 'bad');
    불러오기();
  }

  /* ── 서류 삭제 — 본인이 올린 것만 ── */
  async function 서류지우기(id, 이름) {
    if (!confirm(`삭제하시겠습니까?\n「${이름}」 서류를 삭제합니다.\n삭제한 자료는 복구할 수 없으며, 다시 업로드해야 합니다.`)) return;
    try {
      const d = await mApi(`/prescriptions/${encodeURIComponent(RX)}/attachments/${id}`, { method: 'DELETE' });
      mTell(d?.message || '삭제했습니다.', 'ok');
      불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  /* ── 검수 재요청 ── */
  async function 검수재요청() {
    const 단추 = document.getElementById('revBtn');
    단추.disabled = true;
    const 메모 = document.getElementById('revMemo').value.trim();
    try {
      const d = await mApi(`/prescriptions/${encodeURIComponent(RX)}/request-review`,
        { method: 'POST', body: 메모 ? { memo: 메모 } : {} });
      mSheetClose();
      document.getElementById('revMemo').value = '';
      mTell(d?.message || '검수를 다시 요청했습니다.', 'ok');
      불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
    finally { 단추.disabled = false; }
  }

  갈래불러오기().then(불러오기);
</script>
@endpush
