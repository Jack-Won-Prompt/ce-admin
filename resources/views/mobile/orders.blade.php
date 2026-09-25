{{-- 주문 목록 — 앱의 order_list_screen (2026-09-25 지시). --}}
@extends('layouts.mobile')
@section('title', '주문 목록')
@section('back', true)

@section('body')
  <div class="m-chips">
    <button class="m-chip on" data-s="" onclick="odS(this)">전체</button>
    <button class="m-chip" data-s="pending"   onclick="odS(this)">주문 대기</button>
    <button class="m-chip" data-s="shipping"  onclick="odS(this)">배송 중</button>
    <button class="m-chip" data-s="delivered" onclick="odS(this)">배송 완료</button>
  </div>
  <div id="odList"><div class="m-spin"></div></div>
@endsection

@push('scripts')
<script>
  let odStatus = '';
  function odS(b) {
    document.querySelectorAll('.m-chips .m-chip').forEach(x => x.classList.toggle('on', x === b));
    odStatus = b.dataset.s; 불러오기();
  }
  async function 불러오기() {
    const 통 = document.getElementById('odList');
    통.innerHTML = '<div class="m-spin"></div>';
    try {
      const q = new URLSearchParams({ per_page: 30 });
      if (odStatus) q.set('status', odStatus);
      const d = await mApi('/orders?' + q.toString());
      const 줄 = d.data || [];
      통.innerHTML = 줄.length ? 줄.map(o => `
        <div class="m-card">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
            <b style="font-size:14.5px;">${mEsc(o.order_number)}</b>
            <span style="flex:1"></span>
            <span class="m-badge ${o.status === 'delivered' ? 'done' : (o.status === 'pending' ? 'gray' : 'req')}">
              ${mEsc(o.status_label || o.status)}</span>
          </div>
          <div style="font-size:13.5px; color:var(--m-sub);">
            ${mEsc(o.patient_name || '')}${o.rx_number ? ' · ' + mEsc(o.rx_number) : ''}</div>
          <div style="font-size:12px; color:var(--m-mute); margin-top:4px;">
            ${mEsc(mWhen(o.created_at))}${o.tracking_no ? ' · 운송장 ' + mEsc(o.tracking_no) : ''}</div>
        </div>`).join('')
        : `<div class="m-empty"><i class="bx bx-package"></i>주문이 없습니다.</div>`;
    } catch (e) { 통.innerHTML = `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`; }
  }
  불러오기();
</script>
@endpush
