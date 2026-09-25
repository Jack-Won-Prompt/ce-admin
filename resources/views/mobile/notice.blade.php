{{-- 공지 상세 — 앱의 notice_detail_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 열쇠는 content ㆍ author ㆍ date ㆍ views (created_at/body 가 아니다)
       · 고정 공지면 머리글에 「공지」 표
       · 글 밑에 다음 글ㆍ이전 글로 넘어가는 자리
       · 못 불러오면 「다시 시도」 --}}
@extends('layouts.mobile')
@section('title', '공지사항')
@section('back', true)
@section('body')<div id="nt"><div class="m-spin"></div></div>@endsection

@push('scripts')
<style>
  .nd-head { padding:2px 2px 12px; }
  .nd-head h2 { margin:0 0 8px; font-size:20px; font-weight:800; line-height:1.4; color:#0D1B3E; }
  .nd-meta { display:flex; align-items:center; gap:3px; font-size:12px; color:#90A4AE; }
  .nd-meta .gap { width:12px; }
  .nd-meta .sp { flex:1; }
  .nd-pin { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:999px;
            background:#FFF6E8; border:1px solid #F5C77E; color:#F57C00;
            font-size:12px; font-weight:700; margin-bottom:10px; }
  .nd-body { font-size:14.5px; line-height:1.8; white-space:pre-wrap; color:#1B1F26; }
  .nd-nav { display:flex; align-items:center; gap:10px; padding:14px 16px; }
  .nd-nav .lb { font-size:12px; font-weight:700; color:var(--m-primary); flex:0 0 46px; }
  .nd-nav .tt { flex:1; min-width:0; font-size:13.5px; color:#546E7A;
                overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .nd-nav i { font-size:20px; color:#90A4AE; }
  .nd-line { height:1px; background:#E0E6F0; margin:0 16px; }
</style>
<script>
  const 공지번호 = @json($noticeId);

  function ndDraw(n) {
    const 넘기기 = [];
    if (n.next) 넘기기.push(`
      <a class="nd-nav" href="/m/notices/${n.next.id}">
        <i class="bx bx-chevron-up"></i><span class="lb">다음 글</span>
        <span class="tt">${mEsc(n.next.title)}</span>
      </a>`);
    if (n.next && n.prev) 넘기기.push('<div class="nd-line"></div>');
    if (n.prev) 넘기기.push(`
      <a class="nd-nav" href="/m/notices/${n.prev.id}">
        <i class="bx bx-chevron-down"></i><span class="lb">이전 글</span>
        <span class="tt">${mEsc(n.prev.title)}</span>
      </a>`);

    document.getElementById('nt').innerHTML = `
      <div class="nd-head">
        ${n.is_pinned ? '<span class="nd-pin"><i class="bx bxs-pin"></i>공지</span>' : ''}
        <h2>${mEsc(n.title)}</h2>
        <div class="nd-meta">
          <i class="bx bx-user"></i><span>${mEsc(n.author || '-')}</span>
          <span class="gap"></span>
          <i class="bx bx-calendar"></i><span>${mEsc(n.date || '')}</span>
          <span class="sp"></span>
          <i class="bx bx-show"></i><span>${Number(n.views || 0)}</span>
        </div>
      </div>
      <div class="m-card"><div class="nd-body">${mEsc(n.content || '')}</div></div>
      ${넘기기.length ? `<div class="m-card" style="padding:0;">${넘기기.join('')}</div>` : ''}`;
  }

  function ndLoad() {
    document.getElementById('nt').innerHTML = '<div class="m-spin"></div>';
    mApi('/notices/' + 공지번호)
      .then(d => ndDraw(d.data || {}))
      .catch(e => {
        document.getElementById('nt').innerHTML = `
          <div class="m-empty" style="color:var(--m-danger);">
            <i class="bx bx-error-circle"></i>
            <div style="font-size:13px;">${mEsc(e.message || '불러오기 실패')}</div>
            <button class="m-btn" style="width:auto; margin:14px auto 0; padding:9px 20px;"
                    onclick="ndLoad()">다시 시도</button>
          </div>`;
      });
  }

  ndLoad();
</script>
@endpush
