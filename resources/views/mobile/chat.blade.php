{{-- 채팅 목록 — 앱의 chat_list_screen (2026-09-25 지시).
     1:1ㆍ그룹 갈래, 안 읽은 수, 새 채팅 시작. --}}
@extends('layouts.mobile')

@section('title', '채팅')

@section('head-actions')
  <button class="m-head-btn" onclick="새채팅()" aria-label="새 채팅"><i class="bx bx-plus"></i></button>
@endsection

@section('body')
  <div class="m-chips">
    <button class="m-chip on" data-c="company"  onclick="chCat(this)">회사</button>
    <button class="m-chip"    data-c="customer" onclick="chCat(this)">고객</button>
  </div>
  <div id="chList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="m-sheet" id="newChat">
  <div class="m-grab"></div>
  <h2>새 채팅 시작</h2>
  <p class="desc">대화 상대를 고르십시오. 여러 명을 고르면 그룹 채팅이 됩니다.</p>
  <div class="m-field">
    <label class="m-label" for="ncName">그룹 이름 (여러 명일 때)</label>
    <input class="m-input" id="ncName" placeholder="그룹 이름" autocomplete="off">
  </div>
  <div id="ncPeople" style="max-height:40vh; overflow-y:auto;"></div>
  <button class="m-btn" onclick="채팅만들기()" style="margin-top:12px;">시작</button>
</div>

<script>
  let 갈래 = 'company', 방들 = [], 사람들 = [], 고른사람 = new Set();

  function chCat(btn) {
    document.querySelectorAll('.m-chips .m-chip').forEach(b => b.classList.toggle('on', b === btn));
    갈래 = btn.dataset.c;
    그리기();
  }

  function 그리기() {
    const 줄 = 방들.filter(r => (r.category || 'company') === 갈래);
    document.getElementById('chList').innerHTML = 줄.length ? 줄.map(r => `
      <div class="m-card tap" style="display:flex; align-items:center; gap:12px; padding:13px;"
           onclick="location.assign('/m/chat/${r.id}')">
        <div style="width:44px; height:44px; border-radius:14px; background:var(--m-primary-l);
                    display:flex; align-items:center; justify-content:center; flex:0 0 44px;">
          <i class="bx ${r.type === 'group' ? 'bx-group' : 'bx-user'}"
             style="font-size:22px; color:var(--m-primary);"></i>
        </div>
        <div style="flex:1; min-width:0;">
          <div style="display:flex; align-items:center; gap:6px;">
            <b style="font-size:14.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${mEsc(r.name || '대화')}</b>
            <span style="flex:1"></span>
            <span style="font-size:11.5px; color:var(--m-mute);">${mEsc(mWhen(r.last_message_at))}</span>
          </div>
          <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
            <span style="flex:1; font-size:13px; color:var(--m-sub); overflow:hidden;
                         text-overflow:ellipsis; white-space:nowrap;">${mEsc(r.last_message || '')}</span>
            ${r.unread_count ? `<span class="m-badge need">${r.unread_count}</span>` : ''}
          </div>
        </div>
      </div>`).join('')
      : `<div class="m-empty"><i class="bx bx-message-rounded"></i>대화가 없습니다.</div>`;
  }

  async function 불러오기() {
    try {
      const d = await mApi('/chat/rooms');
      방들 = d.rooms || d.data || [];
      그리기();
    } catch (e) {
      document.getElementById('chList').innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
    }
  }

  async function 새채팅() {
    고른사람.clear();
    document.getElementById('ncPeople').innerHTML = '<div class="m-spin"></div>';
    mSheetOpen('newChat');
    try {
      const d = await mApi('/chat/users');
      사람들 = d.users || d.data || [];
    } catch (e) { 사람들 = []; }
    document.getElementById('ncPeople').innerHTML = 사람들.length ? 사람들.map(u => `
      <label style="display:flex; align-items:center; gap:10px; padding:10px 0; border-top:1px solid var(--m-line);">
        <input type="checkbox" value="${u.id}" onchange="사람고르기(this)" style="width:20px; height:20px;">
        <div style="flex:1;"><b style="font-size:14px;">${mEsc(u.name)}</b>
          <div style="font-size:12px; color:var(--m-sub);">${mEsc(u.email || '')}</div></div>
      </label>`).join('')
      : `<div style="padding:14px 0; color:var(--m-mute); font-size:13.5px;">고를 수 있는 사람이 없습니다.</div>`;
  }

  function 사람고르기(el) { el.checked ? 고른사람.add(+el.value) : 고른사람.delete(+el.value); }

  async function 채팅만들기() {
    const ids = [...고른사람];
    if (!ids.length) { mTell('대화 상대를 선택해 주십시오.', 'warn'); return; }
    const 이름 = document.getElementById('ncName').value.trim();
    if (ids.length > 1 && !이름) { mTell('그룹 이름을 입력해 주십시오.', 'warn'); return; }
    try {
      const d = await mApi('/chat/rooms', { method: 'POST',
        body: { user_ids: ids, type: ids.length > 1 ? 'group' : 'direct', name: 이름 || null, category: 갈래 } });
      mSheetClose();
      const id = d.room?.id ?? d.id;
      if (id) location.assign('/m/chat/' + id); else 불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  불러오기();
</script>
@endpush
