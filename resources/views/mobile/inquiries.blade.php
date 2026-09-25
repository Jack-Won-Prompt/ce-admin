{{-- 문의 목록 — 앱의 inquiry_list_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 앱에 없는 상태 거르개(전체ㆍ답변대기ㆍ답변완료)를 걷었다
       · 목록이 주지 않는 body 를 적으려던 줄을 걷었다 (늘 빈칸이었다)
       · 갈래표 + 상태표 + 제목 + 등록일시, 앞에 상태 그림 타일
       · 머리글에 「총 N개」와 새로 고침, 아래에 떠 있는 「문의 등록」
       · 한 쪽 20건, 끝에서 200px 앞서 더 부른다 --}}
@extends('layouts.mobile')

@section('title', '문의하기')
@section('subtitle', '총 0개')
@section('back', true)

@section('head-actions')
  <button class="m-head-btn" onclick="iqLoad(true)" aria-label="새로 고침"><i class="bx bx-refresh"></i></button>
@endsection

@section('body')
  <div id="iqList"><div class="m-spin"></div></div>
  <div id="iqMore" style="display:none; padding:14px 0 24px; text-align:center;"><div class="m-spin"></div></div>
  <div style="height:56px;"></div>

  {{-- 앱의 FloatingActionButton.extended --}}
  <a class="iq-fab" href="{{ route('m.inquiry.create') }}">
    <i class="bx bx-edit-alt"></i> 문의 등록
  </a>
@endsection

@push('scripts')
<style>
  .iq-card { background:#fff; border:1px solid #E0E6F0; border-radius:16px; padding:14px;
             margin-bottom:10px; display:flex; gap:12px; align-items:flex-start;
             box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .iq-ico  { width:38px; height:38px; border-radius:12px; flex:0 0 38px; color:#fff;
             display:flex; align-items:center; justify-content:center; font-size:18px; }
  .iq-tags { display:flex; gap:6px; margin-bottom:6px; }
  .iq-tag  { padding:3px 8px; border-radius:8px; font-size:11px; font-weight:700; }
  .iq-ti   { font-size:14px; font-weight:700; color:#0D1B3E; line-height:1.45;
             display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .iq-when { margin-top:6px; font-size:11px; color:#90A4AE; }
  .iq-ch   { font-size:20px; color:#90A4AE; align-self:center; }
  .iq-fab  { position:fixed; right:16px; bottom:calc(var(--m-tab) + 16px); z-index:30;
             display:inline-flex; align-items:center; gap:8px; padding:14px 20px;
             border-radius:999px; background:var(--m-primary); color:#fff;
             font-size:14px; font-weight:700; box-shadow:0 8px 22px rgba(11,99,206,.38); }
  .iq-fab i { font-size:18px; }
</style>
<script>
  const iq등록길 = @json(route('m.inquiry.create'));
  let iqPage = 1, iqLast = 1, iqBusy = false, iqCount = 0;

  function iqCard(i) {
    const 답변 = i.status === 'answered';
    return `
      <div class="iq-card tap" onclick="location.assign('/m/inquiries/${i.id}')">
        <div class="iq-ico" style="background:${답변
            ? 'linear-gradient(135deg,#2E7D32,#43A047)'
            : 'linear-gradient(135deg,#F57C00,#FB8C00)'};">
          <i class="bx ${답변 ? 'bx-check-circle' : 'bx-hourglass'}"></i>
        </div>
        <div style="flex:1; min-width:0;">
          <div class="iq-tags">
            <span class="iq-tag" style="background:rgba(144,164,174,.12); color:#546E7A;">${mEsc(i.category_label || i.category || '')}</span>
            <span class="iq-tag" style="background:${답변 ? 'rgba(46,125,50,.1)' : 'rgba(245,124,0,.1)'};
                         color:${답변 ? '#2E7D32' : '#F57C00'};">${답변 ? '답변완료' : '답변대기'}</span>
          </div>
          <div class="iq-ti">${mEsc(i.title || '')}</div>
          <div class="iq-when">${mEsc(i.created_at || '')}</div>
        </div>
        <i class="bx bx-chevron-right iq-ch"></i>
      </div>`;
  }

  async function iqLoad(처음) {
    if (iqBusy) return;
    iqBusy = true;
    if (처음) { iqPage = 1; iqCount = 0; document.getElementById('iqList').innerHTML = '<div class="m-spin"></div>'; }

    try {
      const d = await mApi('/inquiries?page=' + iqPage);
      const 줄 = d.data || [];
      iqLast = d.meta?.last_page ?? 1;
      iqCount += 줄.length;

      const 밑글 = document.querySelector('.m-head .sub');
      if (밑글) 밑글.textContent = `총 ${iqCount}개`;

      const 통 = document.getElementById('iqList');
      const html = 줄.map(iqCard).join('');
      if (처음) {
        통.innerHTML = html || `
          <div class="m-empty">
            <i class="bx bx-support"></i>등록된 문의가 없습니다.
            <div style="margin-top:16px;">
              <button class="m-btn" style="width:auto; margin:0 auto; padding:12px 20px;"
                      onclick="location.assign(iq등록길)">
                <i class="bx bx-edit-alt"></i> 문의 등록하기
              </button>
            </div>
          </div>`;
      } else {
        통.insertAdjacentHTML('beforeend', html);
      }
      iqPage++;
      document.getElementById('iqMore').style.display = (iqPage <= iqLast) ? '' : 'none';
    } catch (e) {
      document.getElementById('iqList').innerHTML =
        `<div class="m-empty" style="color:var(--m-danger);"><i class="bx bx-error-circle"></i>${mEsc(e.message)}</div>`;
      document.getElementById('iqMore').style.display = 'none';
    } finally { iqBusy = false; }
  }

  window.addEventListener('scroll', () => {
    if (iqBusy || iqPage > iqLast) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) iqLoad(false);
  });

  iqLoad(true);
</script>
@endpush
