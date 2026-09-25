{{-- 채팅 목록 — 앱의 chat_list_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 열쇠를 바로잡았다 — latest_body ㆍ latest_time ㆍ unread
         (last_message / last_message_at / unread_count 는 없다. 미리보기ㆍ시각ㆍ
          안 읽은 수가 한 번도 나오지 않았다)
       · 앱에 없는 회사ㆍ고객 거르개를 걷었다 — 앱은 방을 모두 한 줄로 본다
       · 대화 상대는 /chat/rooms 가 함께 준다. /chat/users 는 없는 길이었다
       · 방을 만들면 답은 room_id 다 — 그 방으로 바로 들어간다
       · 새 채팅은 1:1ㆍ그룹을 고르고, 그룹일 때만 이름을 받는다.
         1:1 에서는 한 사람만 골린다
       · 동그라미에 이름 첫 글자, 그룹이면 앞에 무리 그림 --}}
@extends('layouts.mobile')

@section('title', '채팅')
@section('subtitle', '0개의 대화방')

@section('head-actions')
  <button class="m-head-btn" onclick="chNew()" aria-label="새 채팅"><i class="bx bx-edit"></i></button>
@endsection

@section('body')
  <div id="chList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="m-sheet" id="newChat">
  <div class="m-grab"></div>
  <h2>새 채팅</h2>

  <div style="display:flex; gap:8px; margin-bottom:14px;">
    <button class="ch-type on" data-t="direct" onclick="chType(this)">1:1 채팅</button>
    <button class="ch-type"    data-t="group"  onclick="chType(this)">그룹 채팅</button>
  </div>

  <div class="m-field" id="ncNameBox" style="display:none;">
    <label class="m-label" for="ncName">그룹 이름</label>
    <input class="m-input" id="ncName" placeholder="그룹 이름" autocomplete="off">
  </div>

  <div class="m-label" style="margin-bottom:8px;">대화 상대</div>
  <div id="ncPeople" style="max-height:200px; overflow-y:auto;"></div>

  <button class="m-btn" id="ncGo" onclick="chCreate()" style="margin-top:14px;" disabled>
    <i class="bx bx-message-rounded"></i> <span id="ncGoTxt">채팅 시작</span>
  </button>
</div>

<style>
  .ch-type { flex:1; padding:10px; border-radius:20px; border:1px solid var(--m-line);
             background:#F5F7FA; color:var(--m-sub); font-size:13.5px; font-weight:600;
             font-family:inherit; cursor:pointer; }
  .ch-type.on { background:linear-gradient(135deg,#1565C0,#0288D1); border-color:transparent;
                color:#fff; font-weight:700; box-shadow:0 3px 8px rgba(21,101,192,.3); }
  .ch-row { display:flex; align-items:center; gap:12px; padding:13px; background:#fff;
            border:1px solid #E0E6F0; border-radius:16px; margin-bottom:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .ch-av  { width:48px; height:48px; border-radius:16px; flex:0 0 48px; color:#fff;
            font-size:19px; font-weight:800;
            display:flex; align-items:center; justify-content:center; }
  .ch-ti  { display:flex; align-items:center; gap:5px; min-width:0; }
  .ch-ti b{ font-size:14.5px; font-weight:700; color:#0D1B3E; overflow:hidden;
            text-overflow:ellipsis; white-space:nowrap; }
  .ch-ti i{ font-size:13px; color:#90A4AE; flex:0 0 auto; }
  .ch-last{ font-size:13px; color:#546E7A; margin-top:3px; overflow:hidden;
            text-overflow:ellipsis; white-space:nowrap; }
  .ch-rt  { display:flex; flex-direction:column; align-items:flex-end; gap:5px; flex:0 0 auto; }
  .ch-when{ font-size:11px; color:#90A4AE; }
  .ch-un  { background:var(--m-danger); color:#fff; font-size:11px; font-weight:700;
            padding:3px 7px; border-radius:999px; }
  .nc-p   { display:flex; align-items:center; gap:10px; padding:9px 2px;
            border-top:1px solid var(--m-line); }
</style>

<script>
  let ch방들 = [], ch사람들 = [], ch갈래 = 'direct';
  const ch고른 = new Set();

  function chAvatar(r) {
    const 첫자 = (r.name || '?').trim().charAt(0).toUpperCase() || '?';
    const 무리 = r.type === 'group';
    return `<div class="ch-av" style="background:${무리
        ? 'linear-gradient(135deg,#26C6DA,#4DD0E1)'
        : 'linear-gradient(135deg,#0288D1,#26C6DA)'};">${mEsc(첫자)}</div>`;
  }

  function chRow(r) {
    return `
      <div class="ch-row tap" onclick="location.assign('/m/chat/${r.id}')">
        ${chAvatar(r)}
        <div style="flex:1; min-width:0;">
          <div class="ch-ti">${r.type === 'group' ? '<i class="bx bx-group"></i>' : ''}<b>${mEsc(r.name || '대화')}</b></div>
          ${r.latest_body ? `<div class="ch-last">${mEsc(r.latest_body)}</div>` : ''}
        </div>
        <div class="ch-rt">
          ${r.latest_time ? `<span class="ch-when">${mEsc(r.latest_time)}</span>` : ''}
          ${r.unread > 0 ? `<span class="ch-un">${r.unread}</span>` : ''}
        </div>
      </div>`;
  }

  function chDraw() {
    const 밑글 = document.querySelector('.m-head .sub');
    if (밑글) 밑글.textContent = `${ch방들.length}개의 대화방`;

    document.getElementById('chList').innerHTML = ch방들.length
      ? ch방들.map(chRow).join('')
      : `<div class="m-empty">
           <i class="bx bx-message-rounded"></i>대화가 없습니다.
           <div style="margin-top:16px;">
             <button class="m-btn" style="width:auto; margin:0 auto; padding:12px 20px;" onclick="chNew()">
               <i class="bx bx-plus"></i> 새 채팅 시작
             </button>
           </div>
         </div>`;
  }

  async function chLoad() {
    try {
      const d = await mApi('/chat/rooms');
      ch방들   = d.rooms || [];
      ch사람들 = d.users || [];
      chDraw();
    } catch (e) {
      document.getElementById('chList').innerHTML =
        `<div class="m-empty" style="color:var(--m-danger);"><i class="bx bx-error-circle"></i>${mEsc(e.message)}</div>`;
    }
  }

  /* ── 새 채팅 ─────────────────────────────────────── */
  function chNew() {
    ch고른.clear();
    ch갈래 = 'direct';
    document.querySelectorAll('.ch-type').forEach(b => b.classList.toggle('on', b.dataset.t === 'direct'));
    document.getElementById('ncNameBox').style.display = 'none';
    document.getElementById('ncName').value = '';
    chPeople();
    chGo();
    mSheetOpen('newChat');
  }

  function chType(btn) {
    document.querySelectorAll('.ch-type').forEach(b => b.classList.toggle('on', b === btn));
    ch갈래 = btn.dataset.t;
    document.getElementById('ncNameBox').style.display = (ch갈래 === 'group') ? '' : 'none';
    /* 1:1 로 돌아오면 여럿 고른 것을 한 사람으로 줄인다 */
    if (ch갈래 === 'direct' && ch고른.size > 1) {
      const 첫 = [...ch고른][0];
      ch고른.clear();
      ch고른.add(첫);
      chPeople();
    }
    chGo();
  }

  function chPeople() {
    document.getElementById('ncPeople').innerHTML = ch사람들.length
      ? ch사람들.map(u => `
        <label class="nc-p">
          <input type="checkbox" value="${u.id}" ${ch고른.has(u.id) ? 'checked' : ''}
                 onchange="chTick(this)" style="width:19px; height:19px; accent-color:var(--m-primary);">
          <div style="flex:1; min-width:0;">
            <div style="font-size:13.5px; font-weight:600;">${mEsc(u.name)}</div>
            <div style="font-size:11.5px; color:var(--m-mute);">${mEsc(u.role || '')}</div>
          </div>
        </label>`).join('')
      : `<div style="padding:14px 0; color:var(--m-mute); font-size:13.5px;">고를 수 있는 사람이 없습니다.</div>`;
  }

  function chTick(el) {
    const id = Number(el.value);
    if (el.checked) {
      /* 1:1 은 한 사람만 — 앱과 같다 */
      if (ch갈래 === 'direct') ch고른.clear();
      ch고른.add(id);
      if (ch갈래 === 'direct') chPeople();
    } else {
      ch고른.delete(id);
    }
    chGo();
  }

  function chGo() {
    document.getElementById('ncGo').disabled = (ch고른.size === 0);
  }

  async function chCreate() {
    if (!ch고른.size) return;
    const 이름 = document.getElementById('ncName').value.trim();
    if (ch갈래 === 'group' && !이름) return;

    const 단추 = document.getElementById('ncGo');
    단추.disabled = true;
    document.getElementById('ncGoTxt').textContent = '만드는 중…';

    try {
      const d = await mApi('/chat/rooms', {
        method: 'POST',
        body: { type: ch갈래, user_ids: [...ch고른], name: ch갈래 === 'group' ? 이름 : null },
      });
      mSheetClose();
      if (d.room_id) location.assign('/m/chat/' + d.room_id); else chLoad();
    } catch (e) {
      mTell(e.message, 'bad');
    } finally {
      단추.disabled = false;
      document.getElementById('ncGoTxt').textContent = '채팅 시작';
    }
  }

  chLoad();
</script>
@endpush
