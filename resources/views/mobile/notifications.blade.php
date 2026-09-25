{{-- 알림 이력 — 앱의 notification_list_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 읽음 열쇠는 is_read 다 — read_at 이 아니다(모두 안 읽음으로 보였다)
       · 갈래표(채팅 · 자료 재요청)와 안 읽음 점
       · 기기로 닿지 못한 알림은 그 사실을 적는다 (sent=false)
       · 눌러서 갈 곳이 있는 것만 눌린다 — 눌러도 아무 일이 없으면 고장으로 읽힌다
       · 「모두 읽음」은 안 읽은 것이 있을 때만 보인다
       · 한 쪽 20건, 끝에서 200px 앞서 더 부른다 --}}
@extends('layouts.mobile')
@section('title', '알림 이력')
@section('back', true)

@section('head-actions')
  <button class="m-head-btn" id="nfAll" style="display:none; width:auto; padding:0 11px; font-size:13px; font-weight:700;"
          onclick="nfAllRead()">모두 읽음</button>
@endsection

@section('body')
  <div id="nfList"><div class="m-spin"></div></div>
  <div id="nfMore" style="display:none; padding:14px 0 24px; text-align:center;"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<style>
  .nf-card { border:1px solid #E0E6F0; border-radius:14px; padding:14px; margin-bottom:8px;
             display:flex; gap:12px; align-items:flex-start; background:#fff; }
  .nf-card.new { background:rgba(21,101,192,.05); border-color:rgba(21,101,192,.25); }
  .nf-ico { width:36px; height:36px; border-radius:11px; flex:0 0 36px; font-size:18px;
            background:rgba(21,101,192,.1); color:var(--m-primary);
            display:flex; align-items:center; justify-content:center; }
  .nf-top { display:flex; align-items:center; gap:6px; }
  .nf-kind{ padding:1px 6px; border-radius:6px; background:#F5F7FA; color:#546E7A;
            font-size:10px; font-weight:700; }
  .nf-new { width:6px; height:6px; border-radius:50%; background:var(--m-primary); }
  .nf-when{ margin-left:auto; font-size:11px; color:#90A4AE; }
  .nf-ti  { margin-top:6px; font-size:14px; font-weight:700; color:#0D1B3E; }
  .nf-card.new .nf-ti { font-weight:800; }
  .nf-bd  { margin-top:3px; font-size:13px; line-height:1.5; color:#546E7A; white-space:pre-line; }
  .nf-bad { margin-top:6px; font-size:11px; color:var(--m-danger); }
  .nf-ch  { font-size:18px; color:#90A4AE; margin-top:2px; }
</style>
<script>
  /* 앱과 같은 갈래표 — 모르는 갈래는 글만 보여 준다 */
  const NF_ICON  = { chat:'bx-message-rounded', rx_reupload:'bx-file-blank' };
  const NF_LABEL = { chat:'채팅', rx_reupload:'자료 재요청' };

  let nfPage = 1, nfLast = 1, nfBusy = false;

  function nf갈곳(n) {
    if (!n.target_key) return null;
    if (n.type === 'chat')        return '/m/chat/' + encodeURIComponent(n.target_key);
    if (n.type === 'rx_reupload') return '/m/prescriptions/' + encodeURIComponent(n.target_key);
    return null;
  }

  function nfCard(n) {
    const 안읽음 = !n.is_read;
    const 길   = nf갈곳(n);
    const 갈래 = NF_LABEL[n.type];
    return `
      <div class="nf-card ${안읽음 ? 'new' : ''} ${길 ? 'tap' : ''}"
           ${길 ? `onclick="nfOpen(${n.id}, '${길}')"` : ''}>
        <div class="nf-ico"><i class="bx ${NF_ICON[n.type] || 'bx-bell'}"></i></div>
        <div style="flex:1; min-width:0;">
          <div class="nf-top">
            ${갈래 ? `<span class="nf-kind">${mEsc(갈래)}</span>` : ''}
            ${안읽음 ? '<span class="nf-new"></span>' : ''}
            <span class="nf-when">${mEsc(n.created_at || '')}</span>
          </div>
          <div class="nf-ti">${mEsc(n.title || '알림')}</div>
          ${n.body ? `<div class="nf-bd">${mEsc(n.body)}</div>` : ''}
          ${n.sent === false ? '<div class="nf-bad">이 알림은 기기로 전달되지 못했습니다.</div>' : ''}
        </div>
        ${길 ? '<i class="bx bx-chevron-right nf-ch"></i>' : ''}
      </div>`;
  }

  async function nfLoad(처음) {
    if (nfBusy) return;
    nfBusy = true;
    if (처음) { nfPage = 1; document.getElementById('nfList').innerHTML = '<div class="m-spin"></div>'; }

    try {
      const d = await mApi('/notifications?page=' + nfPage);
      const 줄 = d.data || [];
      nfLast = d.meta?.last_page ?? 1;

      /* 「모두 읽음」은 안 읽은 것이 있을 때만 — 앱과 같다 */
      document.getElementById('nfAll').style.display = (d.meta?.unread ?? 0) > 0 ? 'inline-flex' : 'none';

      const 통 = document.getElementById('nfList');
      const html = 줄.map(nfCard).join('');
      if (처음) {
        통.innerHTML = html || `<div class="m-empty"><i class="bx bx-bell"></i>받은 알림이 없습니다.</div>`;
      } else {
        통.insertAdjacentHTML('beforeend', html);
      }
      nfPage++;
      document.getElementById('nfMore').style.display = (nfPage <= nfLast) ? '' : 'none';
    } catch (e) {
      document.getElementById('nfList').innerHTML =
        `<div class="m-empty" style="color:var(--m-danger);"><i class="bx bx-error-circle"></i>${mEsc(e.message)}</div>`;
      document.getElementById('nfMore').style.display = 'none';
    } finally { nfBusy = false; }
  }

  /* 누르면 읽음으로 바꾸고 그 화면으로 간다 — 앱의 _open */
  async function nfOpen(id, 길) {
    try { await mApi(`/notifications/${id}/read`, { method: 'POST' }); } catch (e) {}
    location.assign(길);
  }

  async function nfAllRead() {
    try {
      await mApi('/notifications/read-all', { method: 'POST' });
      nfLoad(true);
    } catch (e) { mTell(e.message, 'bad'); }
  }

  window.addEventListener('scroll', () => {
    if (nfBusy || nfPage > nfLast) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) nfLoad(false);
  });

  nfLoad(true);
</script>
@endpush
