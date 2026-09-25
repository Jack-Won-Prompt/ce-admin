{{-- 문의 상세 — 앱의 inquiry_detail_screen (2026-09-25 지시).
     주고받은 글ㆍ재문의ㆍ첨부. 아래 탭은 감추고 보내는 칸을 그 자리에 둔다. --}}
@extends('layouts.mobile')

@section('title', '문의')
@section('back', true)

@push('styles')
<style>
  body { padding-bottom:calc(64px + env(safe-area-inset-bottom, 0px)); }
  .m-tabs { display:none; }
  .iq-bar { position:fixed; left:0; right:0; bottom:0; z-index:45; background:#fff;
            border-top:1px solid var(--m-line);
            padding:8px 10px calc(8px + env(safe-area-inset-bottom, 0px));
            display:flex; gap:8px; align-items:flex-end; }
  .iq-bar textarea { flex:1; max-height:110px; min-height:42px; padding:10px 12px; border-radius:12px;
                     border:1px solid var(--m-line); font-family:inherit; font-size:14.5px; resize:none; }
</style>
@endpush

@section('body')
  <div id="iq"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<div class="iq-bar">
  <button class="m-head-btn" style="background:var(--m-primary-l); color:var(--m-primary);"
          onclick="document.getElementById('iqFile').click()" aria-label="첨부">
    <i class="bx bx-paperclip"></i>
  </button>
  <textarea id="iqIn" rows="1" placeholder="추가 문의 내용을 입력해 주십시오 (재문의)"></textarea>
  <button class="m-head-btn" style="background:var(--m-primary);" onclick="보내기()" aria-label="보내기">
    <i class="bx bx-send"></i>
  </button>
  <input type="file" id="iqFile" accept="image/*,application/pdf" hidden onchange="첨부됨(this)">
</div>

<script>
  const IQ = @json($inquiryId);
  let 첨부 = null;

  function 첨부됨(i) {
    첨부 = (i.files || [])[0] || null;
    if (첨부) mTell('첨부: ' + 첨부.name);
  }

  function 줄(m) {
    const 관리자 = m.is_admin || m.user_role === 'admin';
    return `
      <div style="display:flex; ${관리자 ? '' : 'flex-direction:row-reverse;'} margin-bottom:10px; gap:8px;">
        <div style="max-width:78%; padding:11px 13px; border-radius:14px; font-size:14.5px; line-height:1.55;
             ${관리자 ? 'background:#fff; border:1px solid var(--m-line);' : 'background:var(--m-primary); color:#fff;'}">
          ${관리자 ? '<div style="font-size:11.5px; color:var(--m-mute); margin-bottom:3px;">관리자</div>' : ''}
          ${mEsc(m.body || '')}
          ${m.attachment_url ? `<a href="${mEsc(m.attachment_url)}" target="_blank"
             style="display:block; margin-top:6px; font-size:13px; text-decoration:underline;">
             <i class="bx bx-paperclip"></i> 첨부 보기</a>` : ''}
        </div>
        <span style="font-size:10.5px; color:var(--m-mute); align-self:flex-end;">${mEsc(mWhen(m.created_at))}</span>
      </div>`;
  }

  async function 불러오기() {
    try {
      const d = await mApi('/inquiries/' + IQ);
      const q  = d.data || d.inquiry || {};
      const 글들 = q.messages || d.messages || [];

      document.getElementById('iq').innerHTML = `
        <div class="m-card">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
            <b style="flex:1; font-size:15.5px;">${mEsc(q.title)}</b>
            <span class="m-badge ${(q.status === 'answered' || q.answered_at) ? 'done' : 'req'}">
              ${(q.status === 'answered' || q.answered_at) ? '답변완료' : '답변대기'}</span>
          </div>
          <div style="font-size:11.5px; color:var(--m-mute);">
            ${mEsc(q.category_label || q.category || '')} · ${mEsc(mWhen(q.created_at))}</div>
        </div>
        ${글들.length ? 글들.map(줄).join('')
          : '<div style="text-align:center; color:var(--m-mute); font-size:13.5px; padding:20px;">메시지가 없습니다.</div>'}`;

      window.scrollTo(0, document.body.scrollHeight);
    } catch (e) {
      document.getElementById('iq').innerHTML =
        `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
    }
  }

  async function 보내기() {
    const el = document.getElementById('iqIn');
    const 글 = el.value.trim();
    if (!글 && !첨부) { mTell('내용을 입력하거나 파일을 첨부해 주십시오.', 'warn'); return; }

    const fd = new FormData();
    if (글)   fd.append('body', 글);
    if (첨부) fd.append('attachment', 첨부, 첨부.name);

    try {
      await mApi(`/inquiries/${IQ}/messages`, { method: 'POST', body: fd });
      el.value = ''; 첨부 = null;
      불러오기();
    } catch (e) { mTell(e.message, 'bad'); }
  }

  불러오기();
</script>
@endpush
