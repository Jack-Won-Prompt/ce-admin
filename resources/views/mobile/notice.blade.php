{{-- 공지 상세 — 앱의 notice_detail_screen (2026-09-25 지시). --}}
@extends('layouts.mobile')
@section('title', '공지사항')
@section('back', true)
@section('body')<div id="nt"><div class="m-spin"></div></div>@endsection

@push('scripts')
<script>
  mApi('/notices/' + @json($noticeId)).then(d => {
    const n = d.data || d.notice || {};
    document.getElementById('nt').innerHTML = `
      <div class="m-card">
        <b style="font-size:17px; line-height:1.4; display:block;">${mEsc(n.title)}</b>
        <div style="font-size:12px; color:var(--m-mute); margin:6px 0 14px;">${mEsc(mWhen(n.created_at))}</div>
        <div style="font-size:14.5px; line-height:1.75; white-space:pre-wrap;">${mEsc(n.body || n.content || '')}</div>
      </div>`;
  }).catch(e => {
    document.getElementById('nt').innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
  });
</script>
@endpush
