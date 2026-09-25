{{-- 처방전 상세 — 앱의 prescription_detail_screen 과 1:1 (2026-09-25 정합성 검증).

     앱에 있는 것을 하나도 빠뜨리지 않는다:
       고른 서류 크게 보기 · 등록 서류(처방전 줄 포함) · 줄마다 재업로드 요청 표시
       · 재업로드 요청 묶음 · 업로드 대기 서류 · 서류 추가 · 검수 재요청
       · 환자 정보 · 의료기관 · 상병 정보 · 처방 내용

     2026-09-25 정합성 검증으로 고친 것:
       · 처방전 번호 열쇠는 **prescription_id** 다 — rx_number 를 보던 탓에 번호가
         빈칸으로 나왔다
       · 처방전 그림도 한 줄로 세운다. 누르면 위에서 크게 보이고, 올린 사람이면
         🗑 로 지운다(DELETE /prescriptions/{rx}/image) — 웹에는 아예 없던 길이다
       · 등록 서류 건수는 처방전 그림까지 센다
       · 줄마다 「재업로드 요청」 표와 사유ㆍ비고ㆍ요청자를 적는다
       · 지우기는 앱과 같은 물음판으로 묻는다 (브라우저 confirm 이 아니다)
       · 대기 서류는 처방전을 앞세워 올리고, 막히면 거기서 멈춘다. 올라간 것만
         덜어 내고 남은 것은 이어서 올린다
       · 처방전ㆍ등록신청서는 한 건에 한 장 — 담을 때 막는다
       · 검수 재요청 문구는 남은 요청 수에 따라 달라진다

     칸 이름은 앱 모델이 읽는 열쇠 그대로 쓴다(ocr_result 아래). --}}
@extends('layouts.mobile')

@section('title', '처방전 상세')
@section('subtitle', $rxNumber)
@section('back', true)

@section('body')
  <div id="rxDetail"><div class="m-spin"></div></div>
@endsection

@push('scripts')
{{-- 서류 추가 — 앱의 갈래 고르기 + 카메라ㆍ사진 선택 --}}
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
  <input type="file" id="camIn" accept="image/*" capture="environment" hidden onchange="rx담기(this)">
  <input type="file" id="picIn" accept="image/*"                       hidden onchange="rx담기(this)">
</div>

{{-- 검수 재요청 — 앱의 「검수자 전달 메모 (선택)」 --}}
<div class="m-sheet" id="revSheet">
  <div class="m-grab"></div>
  <h2>검수 재요청</h2>
  <p class="desc" id="revLead"></p>
  <div class="m-field">
    <label class="m-label" for="revMemo">검수자 전달 메모 (선택)</label>
    <textarea class="m-textarea" id="revMemo" maxlength="500" style="min-height:84px;"></textarea>
  </div>
  <div style="display:flex; gap:8px;">
    <button class="m-btn ghost" onclick="mSheetClose()">취소</button>
    <button class="m-btn" id="revBtn" onclick="rx검수재요청()">재요청</button>
  </div>
</div>

<style>
  .rx-view { background:#F2F4F7; border:1px solid var(--m-line); border-radius:14px;
             overflow:hidden; margin-bottom:10px; }
  .rx-view img { width:100%; display:block; }
  .rx-view .none { padding:44px 16px; text-align:center; color:var(--m-mute); font-size:13.5px; }
  .ft { display:flex; align-items:flex-start; gap:12px; padding:11px 0; border-top:1px solid var(--m-line); }
  .ft:first-of-type { border-top:0; }
  .ft .th { width:52px; height:52px; border-radius:10px; flex:0 0 52px; object-fit:cover;
            background:#F2F4F7; display:flex; align-items:center; justify-content:center;
            font-size:24px; color:var(--m-mute); border:2px solid transparent; }
  .ft.on .th { border-color:var(--m-primary); }
  .ft .lb { font-size:14px; font-weight:700; color:#0D1B3E; }
  .ft.on .lb { color:var(--m-primary); }
  .ft .sb { font-size:12px; color:var(--m-mute); margin-top:2px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .ft .rq { display:inline-block; padding:2px 6px; border-radius:6px; background:#FEF0F0;
            color:var(--m-danger); font-size:10px; font-weight:700; margin-left:6px; }
  .rq-txt { font-size:12px; color:var(--m-danger); margin-top:4px; line-height:1.55; }
  .rq-txt .memo { color:var(--m-sub); }
  .rq-txt .who  { font-size:11px; color:var(--m-mute); }
  .rx-sec { font-weight:700; font-size:14px; display:flex; align-items:center; gap:6px; margin-bottom:8px; }
  .rx-kv  { display:flex; padding:6px 0; font-size:13.5px; gap:10px; }
  .rx-kv span { width:86px; flex:0 0 86px; color:var(--m-mute); }
  .rx-kv b { flex:1; word-break:break-word; }
</style>

<script>
  const RX = @json($rxNumber);
  let 상세 = null, rx대기 = [], rx고른갈래 = null, rx보는것 = null, rx올리는중 = false;
  /* 앱의 _fallbackDocTypes 와 같다 */
  let rx갈래들 = [
    { code:'registration_form', label:'등록신청서' },
    { code:'prescription',      label:'처방전' },
    { code:'test_result',       label:'결과지' },
    { code:'id_card',           label:'신분증' },
  ];

  function rx이름표(code) {
    const t = rx갈래들.find(x => x.code === code);
    return t ? t.label : code;
  }

  /* 값이 있는 줄만 세운다 — 앱의 _Section 과 같다 */
  function rx묶음(제목, 그림, 줄들) {
    const 있는것 = 줄들.filter(([, v]) => v !== null && v !== undefined && String(v).trim() !== '');
    if (!있는것.length) return '';
    return `
      <div class="m-card">
        <div class="rx-sec"><i class="bx ${그림}"></i> ${mEsc(제목)}</div>
        ${있는것.map(([k, v]) => `<div class="rx-kv"><span>${mEsc(k)}</span><b>${mEsc(v)}</b></div>`).join('')}
      </div>`;
  }

  function rx요청찾기(첨부번호) {
    return (상세?.reupload_requests || []).find(r =>
      첨부번호 == null ? (r.attachment_id == null) : (r.attachment_id === 첨부번호)) || null;
  }

  function rx요청글(r, 이름표붙임) {
    if (!r) return '';
    const 누가 = [r.requested_by, r.requested_at].filter(Boolean).join(' · ');
    return `
      <div class="rq-txt">
        ${mEsc(이름표붙임 ? `${r.doc_label} — ${r.reason}` : r.reason)}
        ${r.memo ? `<div class="memo">비고: ${mEsc(r.memo)}</div>` : ''}
        ${누가 ? `<div class="who">${mEsc(누가)}</div>` : ''}
      </div>`;
  }

  /* 서류 한 줄 — 누르면 위에 크게 보이고, 🗑 로 지운다 */
  function rx줄(칸) {
    const 골랐나 = (rx보는것 === 칸.id);
    const r = rx요청찾기(칸.id);
    const 얼굴 = 칸.thumb
      ? `<img class="th" src="${mEsc(칸.thumb)}" alt="" loading="lazy">`
      : `<div class="th"><i class="bx ${칸.pdf ? 'bxs-file-pdf' : 'bx-file'}"></i></div>`;
    return `
      <div class="ft ${골랐나 ? 'on' : ''}" onclick="rx고르기(${칸.id === null ? 'null' : 칸.id})">
        ${얼굴}
        <div style="flex:1; min-width:0;">
          <div><span class="lb">${mEsc(칸.label)}</span>${r ? '<span class="rq">재업로드 요청</span>' : ''}</div>
          <div class="sb">${mEsc(칸.sub || '')}</div>
          ${rx요청글(r, false)}
        </div>
        ${칸.del ? `<button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger); flex:0 0 38px;"
             onclick="event.stopPropagation(); ${칸.delFn}" aria-label="삭제"><i class="bx bx-trash"></i></button>` : ''}
      </div>`;
  }

  function rx고르기(id) { rx보는것 = id; rx그리기(); }

  function rx그리기() {
    const p = 상세, o = p.ocr_result || {};
    const 서류 = p.attachments || [];
    const 되물음 = p.reupload_requests || [];
    const 그림있나 = !!p.image_url;

    /* 위에서 크게 볼 것 — 고른 줄을 따른다 */
    let 볼것 = null;
    if (rx보는것 == null) 볼것 = 그림있나 ? { url: p.image_url, pdf: false } : null;
    else {
      const a = 서류.find(x => x.id === rx보는것);
      if (a) 볼것 = { url: a.url, pdf: !!a.is_pdf };
    }

    const 줄들 = [];
    if (그림있나) {
      줄들.push({ id: null, label: '처방전', sub: p.image_name || '처방전 이미지',
                  thumb: null, pdf: false,
                  del: !!p.editable, delFn: 'rx그림지우기()' });
    }
    for (const a of 서류) {
      줄들.push({ id: a.id, label: a.doc_label || '서류', sub: a.file_name || '',
                  thumb: a.is_pdf ? null : a.url, pdf: !!a.is_pdf,
                  del: !!a.can_delete, delFn: `rx서류지우기(${a.id})` });
    }

    document.getElementById('rxDetail').innerHTML = `
      ${볼것 ? (볼것.pdf
          ? `<div class="rx-view"><div class="none">
               <i class="bx bxs-file-pdf" style="font-size:40px; display:block; margin-bottom:8px; color:var(--m-danger);"></i>
               <a href="${mEsc(볼것.url)}" target="_blank" rel="noopener"
                  style="color:var(--m-primary); text-decoration:underline;">PDF 파일 열기</a></div></div>`
          : `<div class="rx-view"><img src="${mEsc(볼것.url)}" alt="처방전" loading="lazy"
                 onerror="rx그림깨짐(this)"><div class="none" style="display:none;">이미지를 불러올 수 없습니다.</div></div>`)
        : ''}

      <div class="m-card">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
          <b style="font-size:16px;">${mEsc(p.prescription_id || RX)}</b>
          <span style="flex:1"></span>
          <span class="m-badge ${rx뱃지색(p.status)}">${mEsc(p.status_label || p.status)}</span>
        </div>
        <div class="rx-sec" style="color:var(--m-sub); font-size:13px;">
          <i class="bx bx-paperclip"></i> 등록 서류
          <span class="m-badge gray">${줄들.length}건</span>
        </div>
        ${줄들.length ? 줄들.map(rx줄).join('')
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
        <div class="m-card" style="background:#FEF0F0; border-color:#F6D3D3;">
          <div class="rx-sec" style="color:var(--m-danger);">
            <i class="bx bx-upload"></i> 재업로드 요청 ${되물음.length}건</div>
          ${되물음.map(r => rx요청글(r, true)).join('')}
          <div style="font-size:11px; color:var(--m-mute); margin-top:8px; line-height:1.6;">
            아래 「서류 추가」로 추가한 뒤 「서류 업로드」를 선택하면 요청이 완료 처리됩니다.
            잘못 등록한 서류는 🗑로 삭제합니다.</div>
        </div>` : ''}

      ${rx대기.length ? `
        <div class="m-card" style="border-color:var(--m-primary); border-style:dashed;">
          <div class="rx-sec">
            <i class="bx bx-time-five" style="color:var(--m-primary);"></i> 업로드 대기 서류
            <span class="m-badge gray">${rx대기.length}건</span>
          </div>
          <div style="font-size:11.5px; color:var(--m-mute); margin-bottom:6px;">
            아래 「서류 업로드」를 선택하면 한 번에 업로드됩니다.</div>
          ${rx대기.map((d, i) => `
            <div class="ft">
              <div class="th"><i class="bx bx-image"></i></div>
              <div style="flex:1; min-width:0;">
                <div class="lb" style="font-size:13px;">${mEsc(d.이름표)}</div>
                <div class="sb">${mEsc(d.이름)}</div>
              </div>
              <button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger); flex:0 0 38px;"
                      ${rx올리는중 ? 'disabled' : ''} onclick="rx제외(${i})" aria-label="제외">
                <i class="bx bx-x"></i></button>
            </div>`).join('')}
          <button class="m-btn" id="rxUpBtn" onclick="rx대기올리기()" style="margin-top:10px;"
                  ${rx올리는중 ? 'disabled' : ''}>
            <i class="bx bx-upload"></i> 서류 업로드 (${rx대기.length}건)</button>
        </div>` : ''}

      ${p.can_add !== false ? `
        <button class="m-btn ghost" onclick="rxAddOpen()" style="margin-bottom:10px;">
          <i class="bx bx-image-add"></i> 서류 추가</button>` : ''}

      ${p.can_request_review ? `
        <button class="m-btn" onclick="rx재요청열기()" style="margin-bottom:10px;">
          <i class="bx bx-check-shield"></i> 검수 재요청</button>` : ''}

      ${rx묶음('환자 정보', 'bx-user', [
        ['성명', o.patient_name], ['주민번호', o.resident_no],
        ['전화', o.phone || o.mobile],
        ['재발행', o.is_reissue ? '예' : ''],
      ])}
      ${rx묶음('의료기관', 'bx-plus-medical', [
        ['병원명', o.hospital_name], ['병원 코드', o.hospital_code],
        ['의사', o.doctor_name], ['진료과', o.department || o.specialty],
        ['면허번호', o.license_no], ['전문의번호', o.specialist_no],
      ])}
      ${rx묶음('상병 정보', 'bx-clipboard', [
        ['상병명', o.disease_name], ['상병 코드', o.disease_code],
      ])}
      ${rx묶음('처방 내용', 'bx-receipt', [
        ['처방 기간', o.usage_period], ['1일 횟수', o.daily_count],
        ['총 일수', o.total_days], ['총 수량', o.total_count],
        ['발급일', o.issued_date], ['처방전 번호', o.registration_no], ['일련번호', o.serial_no],
      ])}
      <div style="height:16px;"></div>`;
  }

  function rx뱃지색(s) {
    return { review_needed:'need', review_requested:'req', approved:'done',
             rejected:'need', ordered:'gray' }[s] || 'gray';
  }

  function rx그림깨짐(img) {
    img.style.display = 'none';
    if (img.nextElementSibling) img.nextElementSibling.style.display = 'block';
  }

  async function rx갈래불러오기() {
    try {
      const d = await mApi('/prescriptions/doc-types');
      if (d?.data?.length) rx갈래들 = d.data;
    } catch (e) { /* 못 받아도 기본 목록으로 버틴다 — 앱과 같다 */ }
  }

  async function rx불러오기() {
    try {
      const d = await mApi('/prescriptions/' + encodeURIComponent(RX));
      상세 = d.data;
      rx그리기();
    } catch (e) {
      document.getElementById('rxDetail').innerHTML = `
        <div class="m-empty" style="color:var(--m-danger);">
          <i class="bx bx-error-circle"></i>불러오기 실패
          <div style="font-size:13px; margin-top:6px;">${mEsc(e.message)}</div>
          <button class="m-btn" style="width:auto; margin:14px auto 0; padding:9px 20px;"
                  onclick="rx불러오기()">다시 시도</button>
        </div>`;
    }
  }

  /* ── 서류 추가 — 앱처럼 먼저 담고, 한 번에 올린다 ── */
  function rxAddOpen() {
    /* 남의 건에는 처방전을 올릴 수 없다 */
    const 고를것 = rx갈래들.filter(t => t.code !== 'prescription' || 상세?.is_mine !== false);
    if (!고를것.some(t => t.code === rx고른갈래)) rx고른갈래 = 고를것.length ? 고를것[0].code : null;

    document.getElementById('addDesc').textContent =
      `${상세?.prescription_id || RX}에 추가할 서류 유형을 선택해 주십시오. ` +
      `목록에 추가한 뒤 아래 「서류 업로드」로 한 번에 업로드합니다.`;
    document.getElementById('addNote').textContent =
      (상세?.owner_name && 상세?.is_mine === false)
        ? `처방전은 ${상세.owner_name} 님만 등록할 수 있습니다. 그 외 서류는 추가할 수 있습니다.` : '';
    document.getElementById('docTypes').innerHTML = 고를것
      .map(t => `<button class="m-chip ${t.code === rx고른갈래 ? 'on' : ''}" data-c="${mEsc(t.code)}"
                   onclick="rx갈래고르기(this)">${mEsc(t.label)}</button>`).join('');
    mSheetOpen('addSheet');
  }

  function rx갈래고르기(b) {
    rx고른갈래 = b.dataset.c;
    document.querySelectorAll('#docTypes .m-chip').forEach(x => x.classList.toggle('on', x === b));
  }

  function rx담기(input) {
    const f = (input.files || [])[0];
    input.value = '';
    if (!f) return;
    mSheetClose();

    /* 처방전ㆍ등록신청서는 한 건에 한 장 — 앱과 같은 잣대 */
    if (rx대기.some(d => d.갈래 === rx고른갈래)) {
      if (rx고른갈래 === 'prescription')      { mTell('처방전은 1건만 업로드할 수 있습니다.'); return; }
      if (rx고른갈래 === 'registration_form') { mTell('등록신청서는 1건만 업로드할 수 있습니다.'); return; }
    }

    rx대기.push({ file: f, 이름: f.name, 갈래: rx고른갈래, 이름표: rx이름표(rx고른갈래) });
    rx그리기();
  }

  function rx제외(i) { rx대기.splice(i, 1); rx그리기(); }

  async function rx대기올리기() {
    if (!rx대기.length || rx올리는중) return;

    /* 처방전을 먼저 — 그 건의 본 그림이 먼저 들어간다 */
    const 차례 = [
      ...rx대기.filter(d => d.갈래 === 'prescription'),
      ...rx대기.filter(d => d.갈래 !== 'prescription'),
    ];

    rx올리는중 = true;
    const 단추 = document.getElementById('rxUpBtn');
    if (단추) 단추.disabled = true;

    const 보낸것 = [];
    let 탈 = null;

    for (const d of 차례) {
      if (단추) 단추.innerHTML = `업로드 중… ${보낸것.length + 1}/${차례.length}`;
      const fd = new FormData();
      fd.append('prescription_image', d.file, d.이름);
      fd.append('rx_number', RX);              /* 이 건에 붙인다 — 새 건이 서지 않게 */
      fd.append('doc_type', d.갈래);
      try {
        await mApi('/prescriptions/upload', { method: 'POST', body: fd });
        보낸것.push(d);
      } catch (e) {
        탈 = `${d.이름표} — ${e.message}`;
        break;                                  /* 앱과 같이 거기서 멈춘다 */
      }
    }

    rx대기 = rx대기.filter(d => !보낸것.includes(d));
    rx올리는중 = false;

    mTell(탈 == null
      ? `${보낸것.length}건을 업로드했습니다.`
      : `${보낸것.length}건 업로드 후 중단되었습니다: ${탈}. 남은 ${rx대기.length}건은 목록에 유지됩니다.`,
      탈 == null ? 'ok' : 'bad');

    rx불러오기();
  }

  /* ── 지우기 — 앱과 같은 물음판 ── */
  async function rx묻고지우기(무엇, 보내기) {
    const 예 = await mConfirm('삭제하시겠습니까?',
      `${무엇}을(를) 삭제합니다.\n삭제한 자료는 복구할 수 없으며, 다시 업로드해야 합니다.`, '삭제');
    if (!예) return;
    try {
      const d = await 보내기();
      rx보는것 = null;          /* 지운 것을 보고 있었을 수 있다 */
      mTell(d?.message || '삭제했습니다.', 'ok');
      rx불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  function rx서류지우기(id) {
    const a = (상세?.attachments || []).find(x => x.id === id);
    rx묻고지우기(a?.doc_label || '서류',
      () => mApi(`/prescriptions/${encodeURIComponent(RX)}/attachments/${id}`, { method: 'DELETE' }));
  }

  function rx그림지우기() {
    rx묻고지우기('처방전 이미지',
      () => mApi(`/prescriptions/${encodeURIComponent(RX)}/image`, { method: 'DELETE' }));
  }

  /* ── 검수 재요청 ── */
  function rx재요청열기() {
    const 남음 = (상세?.reupload_requests || []).length;
    const 안내 = document.getElementById('revLead');
    안내.textContent = 남음 > 0
      ? `재업로드하지 않은 요청이 ${남음}건 있습니다. 검수를 재요청하시겠습니까?`
      : '재업로드한 서류로 검수를 요청합니다.';
    안내.style.color = 남음 > 0 ? 'var(--m-danger)' : 'var(--m-text)';
    mSheetOpen('revSheet');
  }

  async function rx검수재요청() {
    const 단추 = document.getElementById('revBtn');
    단추.disabled = true;
    const 메모 = document.getElementById('revMemo').value.trim();
    try {
      const d = await mApi(`/prescriptions/${encodeURIComponent(RX)}/request-review`,
        { method: 'POST', body: 메모 ? { memo: 메모 } : {} });
      mSheetClose();
      document.getElementById('revMemo').value = '';
      mTell(d?.message || '검수를 다시 요청했습니다.', 'ok');
      rx불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
    finally { 단추.disabled = false; }
  }

  rx갈래불러오기().then(rx불러오기);
</script>
@endpush
