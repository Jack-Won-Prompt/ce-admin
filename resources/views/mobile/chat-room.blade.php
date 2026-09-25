{{-- 채팅방 — 앱의 chat_room_screen (2026-09-25 지시).
     메시지ㆍ파일 보내기ㆍ이전 메시지 보기ㆍ읽음 알리기. --}}
@extends('layouts.mobile')

@section('title', '채팅')
@section('back', true)

@push('styles')
<style>
  body { padding-bottom:calc(64px + env(safe-area-inset-bottom, 0px)); }
  .m-tabs { display:none; }
  .cm-wrap { padding:12px 12px 8px; }
  .cm { display:flex; margin-bottom:10px; gap:8px; }
  .cm.me { flex-direction:row-reverse; }
  .cm .bub { max-width:76%; padding:10px 13px; border-radius:14px; font-size:14.5px; line-height:1.5;
             background:#fff; border:1px solid var(--m-line); word-break:break-word; }
  .cm.me .bub { background:var(--m-primary); color:#fff; border-color:var(--m-primary); }
  .cm .who  { font-size:11.5px; color:var(--m-mute); margin-bottom:3px; }
  .cm .when { font-size:10.5px; color:var(--m-mute); align-self:flex-end; }
  .cm.sys { justify-content:center; }
  .cm.sys .bub { background:#EEF1F5; border:0; color:var(--m-sub); font-size:12.5px; max-width:90%; text-align:center; }
  .cm-bar { position:fixed; left:0; right:0; bottom:0; z-index:45; background:#fff;
            border-top:1px solid var(--m-line); padding:8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
            display:flex; gap:8px; align-items:flex-end; }
  .cm-bar textarea { flex:1; max-height:110px; min-height:42px; padding:10px 12px; border-radius:12px;
                     border:1px solid var(--m-line); font-family:inherit; font-size:14.5px; resize:none; }
</style>
@endpush

@section('body')
  <div id="cmMore" style="text-align:center; padding:4px 0 10px; display:none;">
    <button class="m-btn ghost" style="width:auto; padding:8px 16px; font-size:13px;"
            onclick="이전보기()">이전 메시지 보기</button>
  </div>
  <div id="cmList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="cm-bar">
  <button class="m-head-btn" style="background:var(--m-primary-l); color:var(--m-primary);"
          onclick="document.getElementById('cmFile').click()" aria-label="파일"><i class="bx bx-paperclip"></i></button>
  <textarea id="cmIn" rows="1" placeholder="메시지를 입력해 주십시오"
            oninput="크기맞추기(this)" onkeydown="엔터(event)"></textarea>
  <button class="m-head-btn" style="background:var(--m-primary);" onclick="보내기()" aria-label="보내기">
    <i class="bx bx-send"></i></button>
  <input type="file" id="cmFile" multiple hidden onchange="파일보내기(this)">
</div>

<script>
  const ROOM = @json($roomId);
  let 메시지 = [], 다음쪽 = 1, 더있나 = false, 나 = null;

  function 크기맞추기(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 110) + 'px'; }
  function 엔터(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); 보내기(); } }

  function 줄(m) {
    if (!m.user_id) return `<div class="cm sys"><div class="bub">${mEsc(m.body)}</div></div>`;
    const 내것 = 나 && m.user_id === 나;
    const 파일 = (m.attachments || []).map(a => /\.(png|jpe?g|gif|webp)$/i.test(a.url || '')
      ? `<img src="${mEsc(a.url)}" alt="" style="max-width:100%; border-radius:10px; margin-top:6px;">`
      : `<a href="${mEsc(a.url)}" target="_blank" style="display:block; margin-top:6px; font-size:13px; text-decoration:underline;">
           <i class="bx bx-file"></i> ${mEsc(a.name || '파일')}</a>`).join('');
    return `
      <div class="cm ${내것 ? 'me' : ''}">
        <div>
          ${내것 ? '' : `<div class="who">${mEsc(m.user_name || '')}</div>`}
          <div class="bub">${mEsc(m.body || '')}${파일}</div>
        </div>
        <span class="when">${mEsc(mWhen(m.created_at))}</span>
      </div>`;
  }

  function 그리기(아래로) {
    document.getElementById('cmList').innerHTML = 메시지.map(줄).join('');
    document.getElementById('cmMore').style.display = 더있나 ? '' : 'none';
    if (아래로) window.scrollTo(0, document.body.scrollHeight);
  }

  async function 불러오기(쪽) {
    try {
      const d = await mApi(`/chat/rooms/${ROOM}/messages?` + new URLSearchParams({ page: 쪽 || 1 }));
      const 새것 = d.messages || d.data || [];
      나 = d.me ?? 나;
      더있나 = !!(d.meta ? (d.meta.current_page < d.meta.last_page) : d.has_more);
      if (쪽 && 쪽 > 1) { 메시지 = 새것.concat(메시지); 그리기(false); }
      else              { 메시지 = 새것; 그리기(true); }
      다음쪽 = (쪽 || 1) + 1;
      mApi(`/chat/rooms/${ROOM}/read`, { method: 'POST' }).catch(() => {});
    } catch (e) {
      document.getElementById('cmList').innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
    }
  }

  function 이전보기() { 불러오기(다음쪽); }

  async function 보내기() {
    const el = document.getElementById('cmIn');
    const 글 = el.value.trim();
    if (!글) return;
    el.value = ''; 크기맞추기(el);
    try {
      await mApi(`/chat/rooms/${ROOM}/messages`, { method: 'POST', body: { body: 글 } });
      불러오기(1);
    } catch (e) { mTell(e.message, 'bad'); el.value = 글; }
  }

  async function 파일보내기(input) {
    const 파일들 = Array.from(input.files || []);
    input.value = '';
    for (const f of 파일들) {
      const fd = new FormData();
      fd.append('files[]', f, f.name);
      try { await mApi(`/chat/rooms/${ROOM}/messages`, { method: 'POST', body: fd }); }
      catch (e) { mTell(e.message, 'bad'); }
    }
    불러오기(1);
  }

  불러오기(1);
  setInterval(() => { if (!document.hidden) 불러오기(1); }, 12000);
</script>
@endpush
