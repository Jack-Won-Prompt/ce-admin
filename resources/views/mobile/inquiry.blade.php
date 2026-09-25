{{-- 문의 상세 — 앱의 inquiry_detail_screen (2026-09-25 지시).
     주고받은 글ㆍ재문의ㆍ첨부. 아래 탭은 감추고 보내는 칸을 그 자리에 둔다.

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 첨부 열쇠는 attachment_path ㆍ attachment_name ㆍ is_image
         (attachment_url 은 없다 — 첨부가 한 번도 보이지 않았다)
       · 내 글인지는 **user_id 가 나와 같고 관리자가 아닐 때**다 (앱과 같은 잣대)
       · 남의 글에는 이름을 적는다 — 관리자면 「관리자」
       · 그림 첨부는 말풍선 안에 그림으로 보인다
       · 머리글에 제목ㆍ갈래ㆍ답변 상태
       · 보내는 칸 안내말은 답변이 끝났으면 「추가 문의 내용을 입력해 주십시오 (재문의)」,
         아니면 「내용을 입력해 주십시오…」
       · 고른 첨부는 보내기 전에 보이고 지울 수 있다
       · 못 불러오면 「다시 시도」 --}}
@extends('layouts.mobile')

@section('title', '문의 상세')
@section('back', true)

@push('styles')
<style>
  body { padding-bottom:calc(74px + env(safe-area-inset-bottom, 0px)); }
  .m-tabs { display:none; }
  .iq-bar { position:fixed; left:0; right:0; bottom:0; z-index:45; background:#fff;
            border-top:1px solid var(--m-line);
            padding:8px 10px calc(8px + env(safe-area-inset-bottom, 0px)); }
  .iq-row { display:flex; gap:8px; align-items:flex-end; }
  .iq-row textarea { flex:1; max-height:120px; min-height:42px; padding:10px 14px; border-radius:12px;
                     border:0; background:#F5F7FA; font-family:inherit; font-size:14.5px; resize:none; }
  .iq-pre { display:flex; align-items:center; gap:9px; margin-bottom:8px; padding:8px;
            border:1px solid var(--m-line); border-radius:11px; }
  .iq-msg { display:flex; gap:8px; align-items:flex-end; margin-bottom:12px; }
  .iq-msg.mine { flex-direction:row-reverse; }
  .iq-av { width:30px; height:30px; border-radius:50%; flex:0 0 30px; color:#fff; font-size:13px;
           font-weight:800; display:flex; align-items:center; justify-content:center; }
  .iq-col { display:flex; flex-direction:column; max-width:72%; min-width:0; }
  .iq-msg.mine .iq-col { align-items:flex-end; }
  .iq-who { font-size:11px; color:#90A4AE; margin:0 4px 3px; }
  .iq-bub { padding:10px 14px; border-radius:16px 16px 16px 4px; background:#fff;
            border:1px solid #E0E6F0; font-size:14.5px; line-height:1.55; color:#0D1B3E;
            white-space:pre-wrap; word-break:break-word; box-shadow:0 2px 6px rgba(0,0,0,.05); }
  .iq-msg.mine .iq-bub { border-radius:16px 16px 4px 16px; border:0; color:#fff;
                         background:linear-gradient(135deg,#1565C0,#0288D1); }
  .iq-bub img { display:block; max-width:100%; border-radius:10px; margin-top:6px; }
  .iq-file { display:inline-flex; align-items:center; gap:5px; margin-top:6px; font-size:13px; }
  .iq-when { font-size:10.5px; color:#90A4AE; margin:4px 4px 0; }
</style>
@endpush

@section('body')
  <div id="iqHead"></div>
  <div id="iq"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="iq-bar">
  <div class="iq-pre" id="iqPre" style="display:none;"></div>
  <div class="iq-row">
    <button class="m-head-btn" style="background:var(--m-primary-l); color:var(--m-primary);"
            onclick="mSheetOpen('iqSheet')" aria-label="첨부"><i class="bx bx-paperclip"></i></button>
    <textarea id="iqIn" rows="1" placeholder="내용을 입력해 주십시오…"></textarea>
    <button class="m-head-btn" id="iqSend" style="background:var(--m-primary);"
            onclick="iqSend()" aria-label="보내기"><i class="bx bx-send"></i></button>
  </div>
  <input type="file" id="iqCam" accept="image/*" capture="environment" hidden onchange="iqPicked(this)">
  <input type="file" id="iqPic" accept="image/*,application/pdf"      hidden onchange="iqPicked(this)">
</div>

<div class="m-sheet" id="iqSheet">
  <div class="m-grab"></div>
  <h2>파일 첨부</h2>
  <button class="m-btn ghost" style="justify-content:flex-start; gap:10px; margin-bottom:8px;"
          onclick="mSheetClose(); document.getElementById('iqPic').click();">
    <i class="bx bx-image"></i> 갤러리에서 선택
  </button>
  <button class="m-btn ghost" style="justify-content:flex-start; gap:10px; margin-bottom:8px;"
          onclick="mSheetClose(); document.getElementById('iqCam').click();">
    <i class="bx bx-camera"></i> 카메라로 촬영
  </button>
  <button class="m-btn ghost" id="iqDrop" style="justify-content:flex-start; gap:10px; display:none;
          border-color:var(--m-danger); color:var(--m-danger);" onclick="iqDropFile()">
    <i class="bx bx-trash"></i> 첨부 제거
  </button>
</div>

<script>
  const IQ = @json($inquiryId);
  let iq첨부 = null, iq나 = null, iq답변끝 = false;

  /* 내 글인지 가리려면 내 번호를 알아야 한다 — 앱의 _loadUserId */
  mApi('/auth/me').then(d => { iq나 = (d.user || d.data || {}).id ?? null; iqLoad(); })
                  .catch(() => iqLoad());

  function iqPicked(i) { iq첨부 = (i.files || [])[0] || null; i.value = ''; iq첨부그리기(); }
  function iqDropFile() { iq첨부 = null; mSheetClose(); iq첨부그리기(); }

  function iq첨부그리기() {
    const 칸 = document.getElementById('iqPre');
    document.getElementById('iqDrop').style.display = iq첨부 ? 'flex' : 'none';
    if (!iq첨부) { 칸.style.display = 'none'; 칸.innerHTML = ''; return; }
    const 그림 = /^image\//.test(iq첨부.type);
    칸.style.display = 'flex';
    칸.innerHTML = `
      ${그림 ? `<img src="${URL.createObjectURL(iq첨부)}" style="width:40px; height:40px; object-fit:cover; border-radius:8px;">`
             : `<i class="bx bxs-file-pdf" style="font-size:26px; color:var(--m-sub);"></i>`}
      <span style="flex:1; min-width:0; font-size:13px; overflow:hidden; text-overflow:ellipsis;
                   white-space:nowrap;">${mEsc(iq첨부.name)}</span>
      <button style="background:none; border:0; color:var(--m-danger); font-size:20px; cursor:pointer;"
              onclick="iqDropFile()" aria-label="첨부 제거"><i class="bx bx-x"></i></button>`;
  }

  function iq그림깨짐(img) {
    img.style.display = 'none';
    if (img.nextElementSibling) img.nextElementSibling.style.display = 'inline-flex';
  }

  function iqBubble(m) {
    /* 앱과 같은 잣대: 내 번호와 같고, 관리자가 아닐 때만 내 글이다 */
    const 내글 = iq나 != null && m.user_id === iq나 && !m.is_admin;
    const 이름 = m.is_admin ? '관리자' : (m.user_name || '-');
    const 첫자 = m.is_admin ? '관' : (m.user_name || '-').substring(0, 1);

    let 붙임 = '';
    if (m.attachment_path) {
      const 붙임이름 = mEsc(m.attachment_name || (m.is_image ? '이미지' : '첨부파일'));
      붙임 = m.is_image
        /* 그림이 깨지면 이름만 남긴다 — 앱의 errorBuilder 와 같다 */
        ? `<img src="${mEsc(m.attachment_path)}" alt="${붙임이름}" onerror="iq그림깨짐(this)">
           <span class="iq-file" style="display:none;"><i class="bx bx-image-alt"></i>${붙임이름}</span>`
        : `<a class="iq-file" href="${mEsc(m.attachment_path)}" target="_blank" rel="noopener"
              style="color:inherit; text-decoration:underline;">
             <i class="bx bx-paperclip"></i>${붙임이름}</a>`;
    }

    return `
      <div class="iq-msg ${내글 ? 'mine' : ''}">
        ${내글 ? '' : `<div class="iq-av" style="background:${m.is_admin
            ? 'linear-gradient(135deg,#C62828,#E53935)'
            : 'linear-gradient(135deg,#1565C0,#0288D1)'};">${mEsc(첫자)}</div>`}
        <div class="iq-col">
          ${내글 ? '' : `<div class="iq-who">${mEsc(이름)}</div>`}
          <div class="iq-bub">${mEsc(m.body || '')}${붙임}</div>
          <div class="iq-when">${mEsc(m.created_at || '')}</div>
        </div>
      </div>`;
  }

  function iqDraw(q) {
    iq답변끝 = q.status === 'answered';
    document.getElementById('iqIn').placeholder =
      iq답변끝 ? '추가 문의 내용을 입력해 주십시오 (재문의)' : '내용을 입력해 주십시오…';

    document.getElementById('iqHead').innerHTML = `
      <div style="padding:2px 2px 14px;">
        <div style="display:flex; align-items:flex-start; gap:8px;">
          <div style="flex:1; min-width:0;">
            <div style="font-size:19px; font-weight:800; line-height:1.4; color:#0D1B3E;">${mEsc(q.title || '')}</div>
            <div style="font-size:12.5px; color:#90A4AE; margin-top:4px;">${mEsc(q.category_label || q.category || '')}</div>
          </div>
          <span style="padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap;
                background:${iq답변끝 ? 'rgba(46,125,50,.2)' : 'rgba(245,124,0,.2)'};
                border:1px solid ${iq답변끝 ? 'rgba(46,125,50,.4)' : 'rgba(245,124,0,.4)'};
                color:${iq답변끝 ? '#2E7D32' : '#F57C00'};">${iq답변끝 ? '답변완료' : '답변대기'}</span>
        </div>
      </div>`;

    const 글들 = q.messages || [];
    document.getElementById('iq').innerHTML = 글들.length
      ? 글들.map(iqBubble).join('')
      : `<div class="m-empty"><i class="bx bx-message-rounded"></i>메시지가 없습니다.</div>`;

    window.scrollTo(0, document.body.scrollHeight);
  }

  function iqLoad() {
    mApi('/inquiries/' + IQ)
      .then(d => iqDraw(d.data || {}))
      .catch(e => {
        document.getElementById('iq').innerHTML = `
          <div class="m-empty" style="color:var(--m-danger);">
            <i class="bx bx-error-circle"></i>
            <div style="font-size:13px;">${mEsc(e.message)}</div>
            <button class="m-btn" style="width:auto; margin:14px auto 0; padding:9px 20px;"
                    onclick="iqLoad()">다시 시도</button>
          </div>`;
      });
  }

  async function iqSend() {
    const el = document.getElementById('iqIn');
    const 글 = el.value.trim();
    /* 앱은 둘 다 비면 아무 일도 하지 않는다 — 알림도 띄우지 않는다 */
    if (!글 && !iq첨부) return;

    const 보내기 = document.getElementById('iqSend');
    보내기.disabled = true;

    const fd = new FormData();
    if (글)     fd.append('body', 글);
    if (iq첨부) fd.append('attachment', iq첨부, iq첨부.name);

    try {
      await mApi(`/inquiries/${IQ}/messages`, { method: 'POST', body: fd });
      el.value = '';
      iq첨부 = null;
      iq첨부그리기();
      iqLoad();
    } catch (e) {
      mTell('전송 실패: ' + e.message, 'bad');
    } finally { 보내기.disabled = false; }
  }

  /* 여러 줄이면 칸이 늘어난다 — 앱의 minLines 1 / maxLines 5 */
  document.getElementById('iqIn').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
</script>
@endpush
