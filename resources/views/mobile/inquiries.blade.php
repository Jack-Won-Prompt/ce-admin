{{-- 문의 목록 — 앱의 inquiry_list_screen (2026-09-25 지시). 답변대기ㆍ답변완료. --}}
@extends('layouts.mobile')

@section('title', '문의하기')
@section('back', true)

@section('head-actions')
  <button class="m-head-btn" onclick="location.assign(@js(route('m.inquiry.create')))" aria-label="문의 등록">
    <i class="bx bx-plus"></i>
  </button>
@endsection

@section('body')
  <div class="m-chips">
    <button class="m-chip on" data-s=""         onclick="iqS(this)">전체</button>
    <button class="m-chip"    data-s="open"     onclick="iqS(this)">답변대기</button>
    <button class="m-chip"    data-s="answered" onclick="iqS(this)">답변완료</button>
  </div>
  <div id="iqList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<script>
  const 등록길 = @json(route('m.inquiry.create'));
  let iqStatus = '';

  function iqS(b) {
    document.querySelectorAll('.m-chips .m-chip').forEach(x => x.classList.toggle('on', x === b));
    iqStatus = b.dataset.s;
    불러오기();
  }

  async function 불러오기() {
    const 통 = document.getElementById('iqList');
    통.innerHTML = '<div class="m-spin"></div>';
    try {
      const q = new URLSearchParams({ per_page: 30 });
      if (iqStatus) q.set('status', iqStatus);
      const d = await mApi('/inquiries?' + q.toString());
      const 줄 = d.data || d.inquiries || [];

      통.innerHTML = 줄.length ? 줄.map(i => `
        <div class="m-card tap" onclick="location.assign('/m/inquiries/${i.id}')">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
            <b style="flex:1; font-size:14.5px;">${mEsc(i.title)}</b>
            <span class="m-badge ${(i.status === 'answered' || i.answered_at) ? 'done' : 'req'}">
              ${(i.status === 'answered' || i.answered_at) ? '답변완료' : '답변대기'}</span>
          </div>
          <div style="font-size:13px; color:var(--m-sub); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            ${mEsc(i.body || '')}</div>
          <div style="font-size:11.5px; color:var(--m-mute); margin-top:4px;">
            ${mEsc(i.category_label || i.category || '')} · ${mEsc(mWhen(i.created_at))}</div>
        </div>`).join('')
        : `<div class="m-empty"><i class="bx bx-help-circle"></i>등록한 문의가 없습니다.
             <div style="margin-top:14px;">
               <button class="m-btn" style="width:auto; padding:10px 18px;"
                       onclick="location.assign(등록길)">문의 등록하기</button>
             </div>
           </div>`;
    } catch (e) {
      통.innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
    }
  }

  불러오기();
</script>
@endpush
