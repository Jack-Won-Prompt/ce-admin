{{-- 처방전 상세 — 앱의 prescription_detail_screen 을 그대로 옮긴다 (2026-09-25 지시).

     앱에 있는 것: 등록 서류 목록ㆍ크게 보기ㆍ서류 추가(사진ㆍ카메라)ㆍ본인이 올린 것만
     삭제ㆍ검수 재요청ㆍ되물은 서류 안내ㆍ환자 정보. 하나도 빠뜨리지 않는다.

     서류를 더할 때는 `/prescriptions/upload` 에 **rx_number 를 함께** 보낸다 —
     그래야 새 건이 서지 않고 이 건에 붙는다(앱의 addFile 과 같은 계약). --}}
@extends('layouts.mobile')

@section('title', $rxNumber)
@section('back', true)

@section('body')
  <div id="rxDetail"><div class="m-spin"></div></div>
@endsection

@push('scripts')
{{-- 서류 추가 판 — 앱의 갈래 고르기 + 카메라ㆍ사진 --}}
<div class="m-sheet" id="addSheet">
  <div class="m-grab"></div>
  <h2>서류 추가</h2>
  <p class="desc" id="addDesc"></p>
  <div class="m-chips" id="docTypes" style="flex-wrap:wrap; overflow:visible;"></div>
  <p class="desc" id="addNote" style="margin:10px 0 14px;"></p>

  <div style="display:flex; gap:8px;">
    <button class="m-btn ghost" onclick="document.getElementById('camIn').click()">
      <i class="bx bx-camera"></i> 카메라
    </button>
    <button class="m-btn" onclick="document.getElementById('picIn').click()">
      <i class="bx bx-image"></i> 사진 선택
    </button>
  </div>
  <input type="file" id="camIn" accept="image/*" capture="environment" multiple hidden onchange="addPicked(this)">
  <input type="file" id="picIn" accept="image/*,application/pdf"      multiple hidden onchange="addPicked(this)">
</div>

{{-- 크게 보기 --}}
<div class="m-sheet-back" id="viewBack" style="background:rgba(0,0,0,.92);" onclick="viewClose()">
  <img id="viewImg" alt="" style="position:absolute; inset:0; margin:auto; max-width:100%; max-height:100%; object-fit:contain;">
</div>

<script>
  const RX   = @json($rxNumber);
  let 상세   = null;
  let 갈래들 = [{ code:'registration_form', label:'등록신청서' }, { code:'test_result', label:'결과지' },
                { code:'id_card', label:'신분증' }, { code:'privacy_consent', label:'개인정보 동의서' },
                { code:'other', label:'기타' }];
  let 고른갈래 = 'registration_form';

  function viewOpen(url) {
    document.getElementById('viewImg').src = url;
    document.getElementById('viewBack').classList.add('on');
  }
  function viewClose() { document.getElementById('viewBack').classList.remove('on'); }

  async function 갈래불러오기() {
    try {
      const d = await mApi('/prescriptions/doc-types');
      if (d?.data?.length) 갈래들 = d.data;
    } catch (e) { /* 못 받아도 기본 목록으로 버틴다 — 앱과 같다 */ }
  }

  function 서류줄(a, 지울수있나) {
    const 그림 = a.is_pdf
      ? `<div style="width:52px;height:52px;border-radius:10px;background:#FEF0F0;display:flex;align-items:center;justify-content:center;">
           <i class="bx bxs-file-pdf" style="font-size:26px;color:var(--m-danger);"></i></div>`
      : `<img src="${mEsc(a.url)}" alt="" loading="lazy"
              style="width:52px;height:52px;border-radius:10px;object-fit:cover;background:#F2F4F7;">`;
    return `
      <div style="display:flex; align-items:center; gap:12px; padding:11px 0; border-top:1px solid var(--m-line);"
           onclick="${a.is_pdf ? `window.open('${mEsc(a.url)}','_blank')` : `viewOpen('${mEsc(a.url)}')`}">
        ${그림}
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; font-size:14.5px;">${mEsc(a.doc_type_label || a.type_label || '서류')}</div>
          <div style="font-size:12px; color:var(--m-mute); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            ${mEsc(a.original_name || a.file_name || '')}</div>
        </div>
        ${지울수있나 ? `<button class="m-head-btn" style="background:#FEF0F0; color:var(--m-danger);"
             onclick="event.stopPropagation(); 서류지우기(${a.id}, ${JSON.stringify(a.doc_type_label || '서류')})"
             aria-label="삭제"><i class="bx bx-trash"></i></button>` : ''}
      </div>`;
  }

  function 그리기() {
    const p = 상세;
    const 뱃지 = { review_needed:['need','검수 필요'], review_requested:['req','검수 요청'],
                   approved:['done','검수 완료'], rejected:['need','반려'] }[p.status] || ['gray', p.status_label || p.status];

    const 서류 = p.attachments || [];
    const 내것 = id => (p.can_delete_ids || []).includes(id);
    const 남의건 = p.creator_name && !p.is_mine;

    const 되물음 = (p.reupload_requests || []).filter(r => !r.closed_at);

    document.getElementById('rxDetail').innerHTML = `
      <div class="m-card">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
          <b style="font-size:17px;">${mEsc(p.rx_number)}</b>
          <span style="flex:1"></span>
          <span class="m-badge ${뱃지[0]}">${mEsc(뱃지[1])}</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--m-sub);">
          <i class="bx bx-paperclip"></i> 등록 서류
          <span class="m-badge gray">${서류.length}건</span>
        </div>
        ${서류.length ? 서류.map(a => 서류줄(a, 내것(a.id))).join('')
                      : `<div style="padding:18px 0; color:var(--m-mute); font-size:13.5px;">등록된 서류가 없습니다.</div>`}
      </div>

      ${남의건 ? `<div class="m-card" style="background:var(--m-primary-l); border-color:#CFE2F8;">
        <div style="display:flex; gap:8px; font-size:13.5px; line-height:1.6;">
          <i class="bx bx-group" style="font-size:18px; color:var(--m-primary);"></i>
          <div><b>${mEsc(p.creator_name)}</b> 님이 등록한 처방전입니다.
               서류를 추가할 수 있으며, 본인이 등록한 서류만 삭제할 수 있습니다.</div>
        </div></div>` : ''}

      ${되물음.length ? `<div class="m-card" style="background:#FFF6E8; border-color:#F5D8A8;">
        <div style="font-weight:700; font-size:14px; margin-bottom:6px; color:var(--m-warn);">
          <i class="bx bx-refresh"></i> 다시 올려야 할 서류</div>
        ${되물음.map(r => `<div style="font-size:13.5px; line-height:1.6;">
          · ${mEsc(r.doc_label)} — ${mEsc(r.reason_label || r.reason || '')}</div>`).join('')}
      </div>` : ''}

      <button class="m-btn ghost" onclick="addOpen()" style="margin-bottom:10px;">
        <i class="bx bx-image-add"></i> 서류 추가 (카메라 · 사진 선택)
      </button>

      ${p.can_request_review !== false ? `<button class="m-btn" onclick="검수재요청()" style="margin-bottom:10px;">
        <i class="bx bx-check-shield"></i> 검수 재요청
      </button>` : ''}

      <div class="m-card">
        <div style="font-weight:700; font-size:14.5px; margin-bottom:8px;">
          <i class="bx bx-user"></i> 환자 정보</div>
        ${[['이름', p.patient_name], ['생년월일', p.birth_date], ['연락처', p.phone || p.mobile],
           ['병원', p.hospital_name], ['1일 횟수', p.daily_count]]
          .filter(([, v]) => v).map(([k, v]) => `
            <div style="display:flex; padding:6px 0; font-size:13.5px;">
              <span style="width:76px; color:var(--m-mute);">${k}</span>
              <b>${mEsc(v)}</b></div>`).join('') || '<div style="color:var(--m-mute); font-size:13.5px;">정보가 없습니다.</div>'}
      </div>`;
  }

  async function 불러오기() {
    try {
      const d = await mApi('/prescriptions/' + encodeURIComponent(RX));
      상세 = d.data;
      그리기();
    } catch (e) {
      document.getElementById('rxDetail').innerHTML =
        `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
    }
  }

  /* ── 서류 추가 ─────────────────────────────────── */
  function addOpen() {
    document.getElementById('addDesc').textContent =
      RX + '에 추가할 서류 유형을 선택해 주십시오.';
    /* 처방전은 이 자리에서 올리지 않는다 — 앱과 같은 잣대 */
    document.getElementById('addNote').textContent = 상세?.creator_name && !상세?.is_mine
      ? `처방전은 ${상세.creator_name} 님만 등록할 수 있습니다. 그 외 서류는 추가할 수 있습니다.`
      : '';
    document.getElementById('docTypes').innerHTML = 갈래들
      .filter(t => t.code !== 'prescription')
      .map(t => `<button class="m-chip ${t.code === 고른갈래 ? 'on' : ''}" data-c="${mEsc(t.code)}"
                   onclick="갈래고르기(this)">${mEsc(t.label)}</button>`).join('');
    mSheetOpen('addSheet');
  }

  function 갈래고르기(btn) {
    고른갈래 = btn.dataset.c;
    document.querySelectorAll('#docTypes .m-chip').forEach(b => b.classList.toggle('on', b === btn));
  }

  async function addPicked(input) {
    const 파일들 = Array.from(input.files || []);
    input.value = '';
    if (!파일들.length) return;

    mSheetClose();
    let 됨 = 0; const 못한것 = [];

    for (const f of 파일들) {
      mTell(`올리는 중… ${됨 + 1}/${파일들.length}`);
      const fd = new FormData();
      fd.append('prescription_image', f, f.name);
      fd.append('rx_number', RX);          // 이 건에 붙인다 — 새 건이 서지 않게
      fd.append('doc_type', 고른갈래);
      try {
        await mApi('/prescriptions/upload', { method: 'POST', body: fd });
        됨++;
      } catch (e) { 못한것.push(f.name + ' — ' + e.message); }
    }

    if (됨)         mTell(`${됨}장을 올렸습니다.`, 'ok');
    if (못한것.length) mTell(못한것[0], 'bad');
    불러오기();
  }

  /* ── 서류 삭제 — 본인이 올린 것만 ──────────────── */
  async function 서류지우기(id, 이름) {
    if (!confirm(`「${이름}」 서류를 삭제합니다.\n삭제한 자료는 복구할 수 없으며, 다시 업로드해야 합니다.`)) return;
    try {
      const d = await mApi(`/prescriptions/${encodeURIComponent(RX)}/attachments/${id}`, { method: 'DELETE' });
      mTell(d?.message || '삭제했습니다.', 'ok');
      불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  /* ── 검수 재요청 ───────────────────────────────── */
  async function 검수재요청() {
    const 메모 = prompt('검수 담당자에게 남길 말이 있으면 적어 주십시오. (없으면 비워 두십시오)') ?? null;
    if (메모 === null) return;
    try {
      const d = await mApi(`/prescriptions/${encodeURIComponent(RX)}/request-review`,
        { method: 'POST', body: 메모 ? { memo: 메모 } : {} });
      mTell(d?.message || '검수를 다시 요청했습니다.', 'ok');
      불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  갈래불러오기().then(불러오기);
</script>
@endpush
