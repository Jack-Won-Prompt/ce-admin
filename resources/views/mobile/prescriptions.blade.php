{{-- 처방전 목록 — 앱의 prescription_list_screen 을 그대로 옮긴다 (2026-09-25 지시).

     앱에 있는 것: 상태 거르개 다섯ㆍ이름 찾기ㆍ날짜 범위ㆍ「처방전 조회」(이름+생년월일로
     남이 올린 건 찾기)ㆍ끝까지 내리면 더 불러오기. 하나도 빠뜨리지 않는다. --}}
@extends('layouts.mobile')

@section('title', '내 처방전')
@section('subtitle', '본인이 등록한 처방전')
{{-- 건수는 불러온 뒤 화면이 채운다 — 앱은 「본인이 등록한 처방전 N건」으로 적는다 --}}

@section('head-actions')
  <button class="m-head-btn" onclick="location.assign('{{ route('m.notifications') }}')" aria-label="알림">
    <i class="bx bx-bell"></i>
  </button>
@endsection

@section('body')
  {{-- 상태 거르개 — 앱과 같은 다섯 --}}
  <div class="m-chips" id="rxChips">
    <button class="m-chip on" data-s=""                 onclick="rxStatus(this)">전체</button>
    <button class="m-chip"    data-s="review_needed"    onclick="rxStatus(this)">검수 필요</button>
    <button class="m-chip"    data-s="review_requested" onclick="rxStatus(this)">검수 요청</button>
    <button class="m-chip"    data-s="approved"         onclick="rxStatus(this)">검수 완료</button>
    <button class="m-chip"    data-s="rejected"         onclick="rxStatus(this)">반려</button>
    <button class="m-chip"    data-s="ordered"          onclick="rxStatus(this)">주문 완료</button>
  </div>

  {{-- 이름 찾기 + 「처방전 조회」 (남이 올린 건) --}}
  <div style="display:flex; gap:8px; margin-bottom:10px;">
    <input class="m-input" id="rxName" placeholder="이름" style="flex:1;"
           oninput="rxNameChanged()" autocomplete="off">
    <button class="m-btn ghost" style="width:auto; padding:0 14px; white-space:nowrap;"
            onclick="lookupOpen()">
      <i class="bx bx-search"></i> 처방전 조회
    </button>
  </div>

  {{-- 날짜 범위 --}}
  <div style="display:flex; gap:8px; align-items:center; margin-bottom:12px;">
    <input class="m-input" id="rxFrom" type="date" style="flex:1;" onchange="rxLoad(true)">
    <span style="color:var(--m-mute); font-size:13px;">~</span>
    <input class="m-input" id="rxTo"   type="date" style="flex:1;" onchange="rxLoad(true)">
    <button class="m-head-btn" style="background:var(--m-primary); flex:0 0 38px;"
            onclick="rxClearDates()" aria-label="날짜 지우기"><i class="bx bx-x"></i></button>
  </div>

  <div id="rxList"></div>
  <div id="rxMore" style="display:none; padding:8px 0 24px;">
    <button class="m-btn ghost" onclick="rxLoad(false)">더 보기</button>
  </div>
@endsection

{{-- 「처방전 조회」 — 이름ㆍ생년월일이 모두 맞아야 나온다 (앱과 같은 잣대) --}}
@push('scripts')
<div class="m-sheet" id="lookupSheet">
  <div class="m-grab"></div>
  <h2>처방전 조회</h2>
  <p class="desc">이름과 생년월일이 모두 일치해야 조회됩니다.<br>다른 담당자가 등록한 처방전에도 서류를 추가할 수 있습니다.</p>

  <div class="m-field">
    <label class="m-label" for="lkName">환자 이름</label>
    <input class="m-input" id="lkName" placeholder="이름" autocomplete="off">
  </div>
  <div class="m-field">
    <label class="m-label" for="lkBirth">생년월일</label>
    <input class="m-input" id="lkBirth" type="date">
  </div>

  <button class="m-btn" id="lkBtn" onclick="lookupGo()"><i class="bx bx-search"></i> 찾기</button>
  <div id="lkResult" style="margin-top:14px;"></div>
</div>

<script>
  let rxPage = 1, rxLast = 1, rxBusy = false, rxStatusVal = '', rxNameTimer = null;

  function rxStatus(btn) {
    document.querySelectorAll('#rxChips .m-chip').forEach(b => b.classList.toggle('on', b === btn));
    rxStatusVal = btn.dataset.s;
    rxLoad(true);
  }

  /* 이름은 치는 대로 찾지 않는다 — 한 글자마다 서버를 부르면 목록이 깜빡인다 */
  function rxNameChanged() {
    clearTimeout(rxNameTimer);
    rxNameTimer = setTimeout(() => rxLoad(true), 420);
  }

  function rxClearDates() {
    document.getElementById('rxFrom').value = '';
    document.getElementById('rxTo').value   = '';
    rxLoad(true);
  }

  function rxCard(p) {
    const 뱃지 = { review_needed:['need','검수 필요'], review_requested:['req','검수 요청'],
                   approved:['done','검수 완료'], rejected:['need','반려'] }[p.status] || ['gray', p.status_label || p.status];
    const 서류 = (p.attachment_count ?? p.attachments_count ?? 0);
    return `
      <div class="m-card tap" onclick="location.assign('/m/prescriptions/${encodeURIComponent(p.rx_number)}')">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
          <b style="font-size:15.5px; letter-spacing:-.2px;">${mEsc(p.rx_number)}</b>
          <span style="flex:1"></span>
          <span class="m-badge ${뱃지[0]}">${mEsc(뱃지[1])}</span>
        </div>
        <div style="font-size:13.5px; color:var(--m-sub);">
          ${mEsc(p.patient_name || p.patient?.name || '이름 없음')}
          ${p.birth_date ? ' · ' + mEsc(p.birth_date) : ''}
          ${서류 ? ' · 서류 ' + 서류 + '장' : ''}
        </div>
        <div style="font-size:12px; color:var(--m-mute); margin-top:4px;">
          ${mEsc(p.created_at ? mWhen(p.created_at) : '')}
          ${p.creator_name ? ' · 등록자 ' + mEsc(p.creator_name) : ''}
        </div>
      </div>`;
  }

  async function rxLoad(처음) {
    if (rxBusy) return;
    rxBusy = true;
    if (처음) { rxPage = 1; document.getElementById('rxList').innerHTML = '<div class="m-spin"></div>'; }

    const q = new URLSearchParams({ page: rxPage, per_page: 20 });
    if (rxStatusVal) q.set('status', rxStatusVal);
    const 이름 = document.getElementById('rxName').value.trim();
    const 부터 = document.getElementById('rxFrom').value;
    const 까지 = document.getElementById('rxTo').value;
    if (이름) q.set('search', 이름);
    if (부터) q.set('date_from', 부터);
    if (까지) q.set('date_to', 까지);

    try {
      const d = await mApi('/prescriptions?' + q.toString());
      const 줄 = d.data || [];
      rxLast = d.meta?.last_page ?? 1;

      /* 앱과 같이 머리글에 건수를 적는다 */
      const 총 = d.meta?.total;
      const 밑글 = document.querySelector('.m-head .sub');
      if (밑글 && 총 != null) 밑글.textContent = `본인이 등록한 처방전 ${총}건`;

      const 통 = document.getElementById('rxList');
      const html = 줄.map(rxCard).join('');
      if (처음) {
        통.innerHTML = html || `<div class="m-empty"><i class="bx bx-file"></i>등록한 처방전이 없습니다.</div>`;
      } else {
        통.insertAdjacentHTML('beforeend', html);
      }
      document.getElementById('rxMore').style.display = (rxPage < rxLast) ? '' : 'none';
      rxPage++;
    } catch (e) {
      document.getElementById('rxList').innerHTML =
        `<div class="m-empty"><i class="bx bx-error"></i>${mEsc(e.message)}</div>`;
      mTell(e.message, 'bad');
    } finally { rxBusy = false; }
  }

  /* ── 처방전 조회 (이름 + 생년월일) ───────────────── */
  function lookupOpen() {
    document.getElementById('lkResult').innerHTML = '';
    document.getElementById('lkName').value = document.getElementById('rxName').value.trim();
    mSheetOpen('lookupSheet');
  }

  async function lookupGo() {
    const 이름 = document.getElementById('lkName').value.trim();
    const 생일 = document.getElementById('lkBirth').value;
    const 통   = document.getElementById('lkResult');

    if (!이름 || !생일) {
      통.innerHTML = `<div style="color:var(--m-danger); font-size:13.5px;">이름과 생년월일을 모두 입력해 주십시오.</div>`;
      return;
    }

    const 단추 = document.getElementById('lkBtn');
    단추.disabled = true;
    통.innerHTML = '<div class="m-spin"></div>';

    try {
      const d = await mApi('/prescriptions/lookup?' + new URLSearchParams({ name: 이름, birth: 생일 }));
      const 줄 = d.data || [];
      통.innerHTML = 줄.length
        ? 줄.map(rxCard).join('')
        : `<div style="color:var(--m-sub); font-size:13.5px; line-height:1.6;">
             조회된 처방전이 없습니다. 이름과 생년월일을 다시 확인해 주십시오.<br>
             검수가 완료된 처방전은 조회되지 않습니다.</div>`;
    } catch (e) {
      통.innerHTML = `<div style="color:var(--m-danger); font-size:13.5px;">${mEsc(e.message)}</div>`;
    } finally { 단추.disabled = false; }
  }

  /* 끝까지 내리면 더 부른다 — 앱의 _onScroll */
  window.addEventListener('scroll', () => {
    if (rxBusy || rxPage > rxLast) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 240) rxLoad(false);
  });

  rxLoad(true);
</script>
@endpush
