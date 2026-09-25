{{-- 채팅방 — 앱의 chat_room_screen (2026-09-25 지시).
     메시지ㆍ파일 보내기ㆍ이전 메시지 보기ㆍ읽음 알리기.

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 내 번호를 /auth/me 로 알아 둔다. 답에는 me 가 없어 **모든 말풍선이 남의
         것으로 보였다**
       · 첨부 열쇠는 attachment_path ㆍ attachment_name ㆍ is_image 다
         (attachments[] 는 없다). 주소는 /storage/ 를 앞에 붙인다
       · 파일은 attachment 로 올린다 — files[] 는 서버가 보지 않는다
       · 시각은 서버가 준 time_label 을 그대로 적는다
       · 보낸 사람이 없는 줄(알림ㆍCE샵 고객)은 이름을 그대로 적는다
       · 지운 메시지는 「삭제된 메시지입니다」 자리만 남긴다
       · 머리글에 방 이름과 첫 글자 동그라미, 새로 고침
       · 고른 파일은 보내기 전에 보이고 지울 수 있다 (앱의 _pendingFile)

     새 글은 **Pusher 로 받는다** — 앱과 같은 private-chat.{방번호} 채널이다
     (2026-09-25 지시: 「12초마다 다시 읽기 절대 안됨, 웹도 Pusher」). --}}
@extends('layouts.mobile')

@section('title', '채팅')
@section('back', true)

@push('styles')
<style>
  body { padding-bottom:calc(74px + env(safe-area-inset-bottom, 0px)); }
  .m-tabs { display:none; }
  .cm { display:flex; flex-direction:column; margin-bottom:12px; }
  .cm.me { align-items:flex-end; }
  .cm .who { font-size:11px; color:#90A4AE; margin:0 0 3px 44px; }
  .cm .row { display:flex; gap:8px; align-items:flex-end; max-width:100%; }
  .cm.me .row { flex-direction:row-reverse; }
  .cm .av { width:36px; height:36px; border-radius:50%; flex:0 0 36px; color:#fff;
            font-size:14px; font-weight:800;
            display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#0288D1,#26C6DA); }
  .cm.me .av { background:linear-gradient(135deg,#1565C0,#0288D1); }
  .cm .bub { padding:9px 12px; border-radius:16px 16px 16px 4px; background:#fff;
             border:1px solid #E0E6F0; font-size:14px; line-height:1.4; color:#0D1B3E;
             white-space:pre-wrap; word-break:break-word; max-width:72vw;
             box-shadow:0 2px 6px rgba(0,0,0,.05); }
  .cm.me .bub { border-radius:16px 16px 4px 16px; border:0; color:#fff;
                background:linear-gradient(135deg,#1565C0,#0288D1); }
  .cm .bub img { display:block; max-width:100%; border-radius:10px; margin-top:6px; }
  .cm .file { display:inline-flex; align-items:center; gap:5px; font-size:13.5px; }
  .cm .when { font-size:10.5px; color:#90A4AE; margin:3px 0 0 44px; }
  .cm.me .when { margin:3px 44px 0 0; }
  .cm .gone { font-style:italic; color:#90A4AE; }
  .cm-bar { position:fixed; left:0; right:0; bottom:0; z-index:45; background:#fff;
            border-top:1px solid var(--m-line);
            padding:8px 10px calc(8px + env(safe-area-inset-bottom, 0px)); }
  .cm-row { display:flex; gap:8px; align-items:flex-end; }
  .cm-row textarea { flex:1; max-height:110px; min-height:42px; padding:10px 14px; border-radius:12px;
                     border:0; background:#F5F7FA; font-family:inherit; font-size:14.5px; resize:none; }
  .cm-pre { display:flex; align-items:center; gap:9px; margin-bottom:8px; padding:8px;
            border:1px solid var(--m-line); border-radius:11px; }
</style>
@endpush

@section('body')
  <div id="cmMore" style="text-align:center; padding:4px 0 10px; display:none;">
    <button class="m-btn ghost" style="width:auto; padding:7px 14px; font-size:12.5px;"
            onclick="cmOlder()">이전 메시지 보기</button>
  </div>
  <div id="cmList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="cm-bar">
  <div class="cm-pre" id="cmPre" style="display:none;"></div>
  <div class="cm-row">
    <button class="m-head-btn" style="background:var(--m-primary-l); color:var(--m-primary);"
            onclick="document.getElementById('cmFile').click()" aria-label="파일">
      <i class="bx bx-paperclip"></i></button>
    <textarea id="cmIn" rows="1" placeholder="메시지를 입력해 주십시오"
              oninput="cmGrow(this)" onkeydown="cmEnter(event)"></textarea>
    <button class="m-head-btn" id="cmSend" style="background:var(--m-primary);"
            onclick="cmSend()" aria-label="보내기"><i class="bx bx-send"></i></button>
  </div>
  <input type="file" id="cmFile" accept="image/*" hidden onchange="cmPicked(this)">
</div>

<script>
  const ROOM = @json($roomId);
  let cm글 = [], cm다음 = 2, cm더 = false, cm나 = null, cm첨부 = null, cm바쁨 = false;

  function cmGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 110) + 'px'; }
  function cmEnter(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cmSend(); } }

  function cmPicked(i) { cm첨부 = (i.files || [])[0] || null; i.value = ''; cm첨부그리기(); }
  function cmDrop()    { cm첨부 = null; cm첨부그리기(); }

  function cm첨부그리기() {
    const 칸 = document.getElementById('cmPre');
    if (!cm첨부) { 칸.style.display = 'none'; 칸.innerHTML = ''; return; }
    칸.style.display = 'flex';
    칸.innerHTML = `
      <img src="${URL.createObjectURL(cm첨부)}" style="width:40px; height:40px; object-fit:cover; border-radius:8px;">
      <span style="flex:1; min-width:0; font-size:13px; overflow:hidden; text-overflow:ellipsis;
                   white-space:nowrap;">${mEsc(cm첨부.name)}</span>
      <button style="background:none; border:0; color:var(--m-danger); font-size:20px; cursor:pointer;"
              onclick="cmDrop()" aria-label="첨부 제거"><i class="bx bx-x"></i></button>`;
  }

  function cmBubble(m) {
    const 내것 = cm나 != null && m.user_id === cm나;
    const 이름 = m.user_name || '알림';
    const 첫자 = 이름.trim().charAt(0).toUpperCase() || '?';

    let 속 = '';
    if (m.is_deleted) {
      속 = '<span class="gone">삭제된 메시지입니다.</span>';
    } else {
      if (m.body) 속 += mEsc(m.body);
      if (m.attachment_path) {
        const 주소 = /^https?:\/\//.test(m.attachment_path)
          ? m.attachment_path : '/storage/' + m.attachment_path;
        속 += m.is_image
          ? `<img src="${mEsc(주소)}" alt="${mEsc(m.attachment_name || '이미지')}">`
          : `<a class="file" href="${mEsc(주소)}" target="_blank" rel="noopener"
                style="color:inherit; text-decoration:underline;">
               <i class="bx bx-file"></i>${mEsc(m.attachment_name || '파일')}</a>`;
      }
    }

    return `
      <div class="cm ${내것 ? 'me' : ''}">
        ${내것 ? '' : `<div class="who">${mEsc(이름)}</div>`}
        <div class="row">
          <div class="av">${mEsc(첫자)}</div>
          <div class="bub">${속}</div>
        </div>
        <div class="when">${mEsc(m.time_label || '')}</div>
      </div>`;
  }

  function cmDraw(아래로) {
    document.getElementById('cmList').innerHTML = cm글.length
      ? cm글.map(cmBubble).join('')
      : `<div class="m-empty"><i class="bx bx-message-rounded"></i>메시지가 없습니다.</div>`;
    document.getElementById('cmMore').style.display = cm더 ? '' : 'none';
    if (아래로) window.scrollTo(0, document.body.scrollHeight);
  }

  async function cmLoad(쪽, 처음) {
    if (cm바쁨) return;
    cm바쁨 = true;
    try {
      const d = await mApi(`/chat/rooms/${ROOM}/messages?page=${쪽 || 1}`);
      const 새것 = d.messages || [];
      cm더 = !!d.has_more;

      if (쪽 && 쪽 > 1) {
        cm글 = 새것.concat(cm글);
        cm다음 = 쪽 + 1;
        cmDraw(false);
      } else {
        /* 줄 수와 마지막 글이 같으면 다시 그리지 않는다 — 읽던 자리를 잃지 않게 */
        const 그대로 = cm글.length === 새것.length &&
                       cm글.length > 0 && cm글[cm글.length - 1].id === 새것[새것.length - 1].id;
        cm글 = 새것;
        cm다음 = 2;
        if (!그대로) cmDraw(처음 !== false);
      }
      mApi(`/chat/rooms/${ROOM}/read`, { method: 'POST' }).catch(() => {});
    } catch (e) {
      if (!cm글.length) {
        document.getElementById('cmList').innerHTML =
          `<div class="m-empty" style="color:var(--m-danger);"><i class="bx bx-error-circle"></i>${mEsc(e.message)}</div>`;
      }
    } finally { cm바쁨 = false; }
  }

  function cmOlder() { cmLoad(cm다음); }

  async function cmSend() {
    const el = document.getElementById('cmIn');
    const 글 = el.value.trim();
    if (!글 && !cm첨부) return;

    const 단추 = document.getElementById('cmSend');
    단추.disabled = true;

    try {
      /* 앱은 파일을 먼저 한 줄로 보내고, 글이 있으면 따로 보낸다 */
      if (cm첨부) {
        const fd = new FormData();
        fd.append('attachment', cm첨부, cm첨부.name);
        await mApi(`/chat/rooms/${ROOM}/messages`, { method: 'POST', body: fd });
        cm첨부 = null;
        cm첨부그리기();
      }
      if (글) {
        el.value = '';
        cmGrow(el);
        await mApi(`/chat/rooms/${ROOM}/messages`, { method: 'POST', body: { body: 글 } });
      }
      await cmLoad(1);
      window.scrollTo(0, document.body.scrollHeight);
    } catch (e) {
      mTell('메시지를 전송하지 못했습니다. 잠시 후 다시 시도해 주십시오.', 'bad');
    } finally { 단추.disabled = false; }
  }

  /* 머리글에 방 이름을 적는다 — 앱은 목록에서 들고 온 이름을 쓴다 */
  mApi('/chat/rooms').then(d => {
    const 방 = (d.rooms || []).find(r => String(r.id) === String(ROOM));
    if (!방) return;
    const h1 = document.querySelector('.m-head h1');
    if (h1) h1.childNodes[0].nodeValue = 방.name || '채팅';
    document.title = (방.name || '채팅') + ' — CE Admin';
  }).catch(() => {});

  /* 내 번호를 먼저 알아야 내 말풍선을 가린다 */
  mApi('/auth/me')
    .then(d => { cm나 = (d.user || d.data || {}).id ?? null; window.mMe = cm나; })
    .catch(() => {})
    .then(() => cmLoad(1, true));

  /* ── 실시간 — 앱의 activeRoomId ㆍ onActiveRoomMessage 와 같다 ──
     다시 읽지 않는다. 온 글만 그 자리에 붙인다. */
  window.mChatRoom = ROOM;
  if (window.mChatSubscribe) window.mChatSubscribe(Number(ROOM));

  window.addEventListener('m:chat', e => {
    if (Number(e.detail.room) !== Number(ROOM)) return;
    const 글 = e.detail.msg;
    if (cm글.some(m => m.id === 글.id)) return;      /* 같은 글을 두 번 붙이지 않는다 */
    cm글.push(글);
    cmDraw(true);
    mApi(`/chat/rooms/${ROOM}/read`, { method: 'POST' }).catch(() => {});
  });

  /* 상대가 고치거나 지운 것 — 그 줄만 바로잡는다 */
  window.addEventListener('m:chat-changed', e => {
    if (Number(e.detail.room) !== Number(ROOM)) return;
    const d = e.detail.data || {};
    const m = cm글.find(x => x.id === d.id);
    if (!m) return;
    if (d.action === 'deleted') { m.is_deleted = true; m.body = null; m.attachment_path = null; }
    else if (d.action === 'edited') { m.body = d.body; }
    cmDraw(false);
  });

  /* 이 방을 떠나면 「보고 있는 방」 표시를 지운다 */
  window.addEventListener('pagehide', () => { window.mChatRoom = null; });
</script>
@endpush
