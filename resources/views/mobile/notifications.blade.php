{{-- 알림 이력 — 앱의 notification_list_screen (2026-09-25 지시). --}}
@extends('layouts.mobile')
@section('title', '알림 이력')
@section('back', true)
@section('head-actions')
  <button class="m-head-btn" onclick="모두읽음()" aria-label="모두 읽음"><i class="bx bx-check-double"></i></button>
@endsection

@section('body')<div id="nfList"><div class="m-spin"></div></div>@endsection

@push('scripts')
<script>
  async function 불러오기() {
    const 통 = document.getElementById('nfList');
    try {
      const d = await mApi('/notifications?per_page=40');
      const 줄 = d.data || d.notifications || [];
      통.innerHTML = 줄.length ? 줄.map(n => `
        <div class="m-card tap" style="display:flex; gap:11px; ${n.read_at ? 'opacity:.62;' : ''}"
             onclick="읽음(${n.id})">
          <i class="bx ${/재요청|reupload/.test(n.type || '') ? 'bx-refresh' : (/chat/.test(n.type || '') ? 'bx-message-rounded' : 'bx-bell')}"
             style="font-size:21px; color:var(--m-primary);"></i>
          <div style="flex:1; min-width:0;">
            <b style="font-size:14px;">${mEsc(n.title || '')}</b>
            <div style="font-size:13px; color:var(--m-sub); margin-top:2px;">${mEsc(n.body || '')}</div>
            <div style="font-size:11.5px; color:var(--m-mute); margin-top:4px;">${mEsc(mWhen(n.created_at))}</div>
          </div>
          ${n.read_at ? '' : '<span class="m-badge need">새 알림</span>'}
        </div>`).join('')
        : `<div class="m-empty"><i class="bx bx-bell"></i>받은 알림이 없습니다.</div>`;
    } catch (e) { 통.innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`; }
  }
  async function 읽음(id) { try { await mApi(`/notifications/${id}/read`, { method:'POST' }); 불러오기(); } catch (e) {} }
  async function 모두읽음() {
    try { await mApi('/notifications/read-all', { method:'POST' }); mTell('모두 읽음으로 바꿨습니다.', 'ok'); 불러오기(); }
    catch (e) { mTell(e.message, 'bad'); }
  }
  불러오기();
</script>
@endpush
