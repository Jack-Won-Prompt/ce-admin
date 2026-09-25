{{-- 공지사항 — 앱의 notice_list_screen (2026-09-25 지시). --}}
@extends('layouts.mobile')
@section('title', '공지사항')
@section('back', true)
@section('body')<div id="ntList"><div class="m-spin"></div></div>@endsection

@push('scripts')
<script>
  mApi('/notices?per_page=40').then(d => {
    const 줄 = d.data || [];
    document.getElementById('ntList').innerHTML = 줄.length
      ? `<div style="font-size:12.5px; color:var(--m-mute); padding:0 2px 8px;">총 ${줄.length}개</div>` +
        줄.map(n => `
        <div class="m-card tap" onclick="location.assign('/m/notices/${n.id}')">
          <div style="display:flex; align-items:center; gap:8px;">
            ${n.is_pinned ? '<span class="m-badge req">중요</span>' : ''}
            <b style="flex:1; font-size:14.5px;">${mEsc(n.title)}</b>
          </div>
          <div style="font-size:12px; color:var(--m-mute); margin-top:4px;">${mEsc(mWhen(n.created_at))}</div>
        </div>`).join('')
      : `<div class="m-empty"><i class="bx bx-bullhorn"></i>공지사항이 없습니다.</div>`;
  }).catch(e => {
    document.getElementById('ntList').innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
  });
</script>
@endpush
