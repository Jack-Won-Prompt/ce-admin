{{-- 공지사항 — 앱의 notice_list_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 날짜 열쇠는 date 다 — created_at 이 아니다(빈칸으로 나왔다)
       · 안 읽은 줄은 바탕을 옅게 칠하고 앞에 점을 찍는다
       · 고정 공지는 압정 그림, 아닌 것은 확성기
       · 글쓴이ㆍ날짜ㆍ조회수를 적는다
       · 한 쪽 20건, 끝에서 200px 앞서 더 부른다
       · 머리글에 「총 N개」와 새로 고침 --}}
@extends('layouts.mobile')
@section('title', '공지사항')
@section('subtitle', '총 0개')
@section('back', true)

@section('head-actions')
  <button class="m-head-btn" onclick="ntLoad(true)" aria-label="새로 고침"><i class="bx bx-refresh"></i></button>
@endsection

@section('body')
  <div id="ntList"><div class="m-spin"></div></div>
  <div id="ntMore" style="display:none; padding:14px 0 24px; text-align:center;"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<style>
  .nt-card { background:#fff; border:1px solid #E0E6F0; border-radius:16px; padding:14px;
             margin-bottom:10px; display:flex; gap:12px; align-items:flex-start;
             box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .nt-card.new { background:rgba(21,101,192,.04); border-color:rgba(21,101,192,.2); }
  .nt-ico { width:40px; height:40px; border-radius:12px; flex:0 0 40px; color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:18px; }
  .nt-ti  { display:flex; align-items:flex-start; gap:6px; }
  .nt-dot { width:7px; height:7px; border-radius:50%; background:var(--m-primary);
            flex:0 0 7px; margin-top:6px; }
  .nt-ti b { font-size:14px; font-weight:600; color:#0D1B3E; line-height:1.45;
             display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .nt-card.new .nt-ti b { font-weight:800; }
  .nt-meta { display:flex; align-items:center; gap:3px; margin-top:6px;
             font-size:11px; color:#90A4AE; }
  .nt-meta .gap { width:10px; }
  .nt-meta .sp { flex:1; }
</style>
<script>
  let ntPage = 1, ntLast = 1, ntBusy = false, ntCount = 0;

  function ntCard(n) {
    const 읽음 = !!n.is_read;
    return `
      <div class="nt-card ${읽음 ? '' : 'new'} tap" onclick="location.assign('/m/notices/${n.id}')">
        <div class="nt-ico" style="background:${n.is_pinned
            ? 'linear-gradient(135deg,#F57C00,#FB8C00)'
            : 'linear-gradient(135deg,#0277BD,#0288D1)'};">
          <i class="bx ${n.is_pinned ? 'bxs-pin' : 'bx-broadcast'}"></i>
        </div>
        <div style="flex:1; min-width:0;">
          <div class="nt-ti">${읽음 ? '' : '<span class="nt-dot"></span>'}<b>${mEsc(n.title)}</b></div>
          <div class="nt-meta">
            <i class="bx bx-user"></i><span>${mEsc(n.author || '-')}</span>
            <span class="gap"></span>
            <i class="bx bx-calendar"></i><span>${mEsc(n.date || '')}</span>
            <span class="sp"></span>
            <i class="bx bx-show"></i><span>${Number(n.views || 0)}</span>
          </div>
        </div>
      </div>`;
  }

  async function ntLoad(처음) {
    if (ntBusy) return;
    ntBusy = true;
    if (처음) { ntPage = 1; ntCount = 0; document.getElementById('ntList').innerHTML = '<div class="m-spin"></div>'; }

    try {
      const d = await mApi('/notices?page=' + ntPage);
      const 줄 = d.data || [];
      ntLast = d.meta?.last_page ?? 1;
      ntCount += 줄.length;

      /* 앱은 지금까지 받아 온 개수를 적는다 */
      const 밑글 = document.querySelector('.m-head .sub');
      if (밑글) 밑글.textContent = `총 ${ntCount}개`;

      const 통 = document.getElementById('ntList');
      const html = 줄.map(ntCard).join('');
      if (처음) {
        통.innerHTML = html || `<div class="m-empty"><i class="bx bx-broadcast"></i>공지사항이 없습니다.</div>`;
      } else {
        통.insertAdjacentHTML('beforeend', html);
      }
      ntPage++;
      document.getElementById('ntMore').style.display = (ntPage <= ntLast) ? '' : 'none';
    } catch (e) {
      document.getElementById('ntList').innerHTML =
        `<div class="m-empty"><i class="bx bx-error-circle"></i>${mEsc(e.message)}</div>`;
      document.getElementById('ntMore').style.display = 'none';
    } finally { ntBusy = false; }
  }

  window.addEventListener('scroll', () => {
    if (ntBusy || ntPage > ntLast) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) ntLoad(false);
  });

  ntLoad(true);
</script>
@endpush
