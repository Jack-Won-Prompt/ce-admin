{{-- 처방전 목록 — 앱의 prescription_list_screen 을 그대로 옮긴다 (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 기간 기본값을 오늘 하루로 둔다(앱의 PrescriptionListNotifier 와 같다)
       · 되돌리기(×)는 비우지 않고 오늘 하루로 되돌린다. 기본값이면 보이지 않는다
       · 고를 수 있는 날은 2년 전부터 오늘까지
       · 카드는 앱의 _PrescriptionCard 와 같은 다섯 줄(상태 타일·번호·상태칩 /
         환자·의료기관 / 상병명 / 구분선 / 발급일·등록일시)
       · 조회 결과는 목록 카드가 아니라 앱의 조회 카드(등록자를 적는다)
       · 한 쪽 15건, 이름 디바운스 400ms, 끝에서 200px 앞서 더 부른다
       · 알림 종은 두지 않는다 — 앱은 설정 ▸ 알림 이력으로 간다 --}}
@extends('layouts.mobile')

@section('title', '내 처방전')
@section('subtitle', '본인이 등록한 처방전')
{{-- 건수는 불러온 뒤 화면이 채운다 — 앱은 건수가 있을 때만 「N건」을 붙인다 --}}

@section('body')
  {{-- 상태 거르개 — 「검수 보류」를 더해 일곱 (2026-09-26 지시) --}}
  <div class="m-chips" id="rxChips">
    <button class="m-chip on" data-s=""                 onclick="rxStatus(this)">전체</button>
    <button class="m-chip"    data-s="review_needed"    onclick="rxStatus(this)">검수 필요</button>
    <button class="m-chip"    data-s="review_requested" onclick="rxStatus(this)">검수 요청</button>
    {{-- 다시 올려 달라고 해 둔 건 — 걸러 볼 자리가 없었다 (2026-09-26 지시) --}}
    <button class="m-chip"    data-s="review_hold"      onclick="rxStatus(this)">검수 보류</button>
    <button class="m-chip"    data-s="approved"         onclick="rxStatus(this)">검수 완료</button>
    <button class="m-chip"    data-s="rejected"         onclick="rxStatus(this)">반려</button>
    <button class="m-chip"    data-s="ordered"          onclick="rxStatus(this)">주문 완료</button>
  </div>

  {{-- 이름 찾기 + 「처방전 조회」 (남이 올린 건) --}}
  <div style="display:flex; gap:8px; margin-bottom:10px;">
    <input class="m-input" id="rxName" placeholder="이름" style="flex:1;"
           oninput="rxNameChanged()" autocomplete="off">
    <button class="m-btn ghost" style="width:auto; padding:0 12px; white-space:nowrap;
                   border-color:var(--m-primary); color:var(--m-primary);"
            onclick="lookupOpen()">
      <i class="bx bx-search"></i> 처방전 조회
    </button>
  </div>

  {{-- 업로드 날짜 범위 — 기본은 오늘 하루 --}}
  <div style="display:flex; gap:6px; align-items:center; margin-bottom:12px;">
    <input class="m-input" id="rxFrom" type="date" style="flex:1; min-width:0;" onchange="rxDateChanged()">
    <span style="color:var(--m-mute); font-size:13px;">~</span>
    <input class="m-input" id="rxTo" type="date" style="flex:1; min-width:0;" onchange="rxDateChanged()">
    {{-- 되돌리기는 칸 밖에 둔다 — 칸 안에 두면 달력 그림과 겹친다 --}}
    <button class="m-head-btn" id="rxClear" onclick="rxResetDates()" aria-label="기간 되돌리기"
            style="display:none; background:var(--m-primary-l); color:var(--m-primary); flex:0 0 38px;">
      <i class="bx bx-x"></i>
    </button>
  </div>

  <div id="rxList"></div>
  <div id="rxMore" style="display:none; padding:14px 0 24px; text-align:center;">
    <div class="m-spin"></div>
  </div>
@endsection

{{-- 「처방전 조회」 — 이름ㆍ생년월일이 모두 맞아야 나온다 (앱과 같은 잣대) --}}
@push('scripts')
<div class="m-sheet" id="lookupSheet">
  <div class="m-grab"></div>
  <h2>처방전 조회</h2>
  <p class="desc">이름과 생년월일이 모두 일치해야 조회됩니다. 다른 담당자가 등록한 처방전에도 서류를 추가할 수 있습니다.</p>

  <div class="m-field">
    <label class="m-label" for="lkName">환자 이름</label>
    <input class="m-input" id="lkName" placeholder="이름" autocomplete="off">
  </div>
  <div class="m-field">
    <label class="m-label" for="lkBirth">생년월일</label>
    <input class="m-input" id="lkBirth" type="date" max="{{ now()->toDateString() }}">
  </div>

  <button class="m-btn" id="lkBtn" onclick="lookupGo()"><i class="bx bx-search"></i> <span id="lkBtnTxt">찾기</span></button>
  <div id="lkResult" style="margin-top:14px;"></div>
</div>

<style>
  /* 앱의 _PrescriptionCard 와 같은 짜임 */
  .rx-card { background:#fff; border:1px solid var(--m-line); border-radius:16px;
             padding:14px; margin-bottom:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .rx-top  { display:flex; align-items:center; gap:12px; }
  .rx-ico  { width:40px; height:40px; border-radius:12px; display:flex;
             align-items:center; justify-content:center; font-size:20px; flex:0 0 40px; }
  .rx-no   { flex:1; font-size:14px; font-weight:800; color:#0D1B3E; min-width:0;
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .rx-badge{ padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;
             white-space:nowrap; }
  /* 다시 올릴 서류가 남은 건 — 상태 배지 앞에 세운다 (2026-09-26 지시) */
  .rx-redo{ display:inline-flex; align-items:center; gap:3px; padding:4px 9px;
            border-radius:20px; font-size:11px; font-weight:800; white-space:nowrap;
            background:#FFF3E0; color:#E65100; border:1px solid #FFCC80; }
  .rx-redo i{ font-size:13px; }
  .rx-rows { margin-top:12px; display:flex; flex-direction:column; gap:6px; }
  .rx-line { display:flex; gap:12px; align-items:center; }
  .rx-chip { display:flex; align-items:center; gap:5px; font-size:12.5px; color:#546E7A;
             min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .rx-chip i { font-size:14px; color:#90A4AE; flex:0 0 auto; }
  .rx-div  { height:1px; background:#E0E6F0; border:0; margin:10px 0; }
  .rx-foot { display:flex; align-items:center; gap:4px; font-size:11px; color:#90A4AE; }
  .rx-foot .sp { flex:1; }
  /* 조회 결과 카드 — 앱의 _LookupSheet 결과와 같다 */
  .lk-card { border:1px solid #E3E8EF; border-radius:12px; padding:12px; margin-bottom:8px; }
  .lk-top  { display:flex; align-items:center; gap:8px; }
  .lk-top b { flex:1; font-size:15px; font-weight:700; }
  .lk-top span { font-size:12px; color:#546E7A; }
  .lk-sum  { margin-top:4px; font-size:13px; color:#546E7A; }
  .lk-who  { margin-top:2px; font-size:12px; font-weight:600; }
</style>

<script>
  /* 앱과 같은 상태 빛깔ㆍ그림 (AppTheme) */
  /* 검수 보류ㆍ검수 요청ㆍ검수 재요청이 빠져 있었다 (2026-09-26 지시).
     빠진 상태는 회색 빈 동그라미로 떨어져, 다시 올려 달라는 건과 아직 손대지 않은
     건이 한 모양으로 보였다. */
  const RX_COLOR = {
    pending:'#9E9E9E', ocr_processing:'#F57C00', ocr_done:'#0288D1',
    review_needed:'#C62828', review_requested:'#F57C00', review_hold:'#EF6C00',
    review_resent:'#F57C00', approved:'#2E7D32', rejected:'#B71C1C', ordered:'#1565C0',
  };
  const RX_ICON = {
    pending:'bx-hourglass', ocr_processing:'bx-magic-wand', ocr_done:'bx-check-circle',
    review_needed:'bx-error', review_requested:'bx-time-five', review_hold:'bx-upload',
    review_resent:'bx-time-five', approved:'bxs-badge-check', rejected:'bx-x-circle',
    ordered:'bx-shopping-bag',
  };

  /* 앱은 기기 날짜를 쓴다 — 서버 날짜가 아니라 보는 사람의 오늘이다 */
  function 오늘() {
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  }
  function 두해전() {
    const d = new Date();
    return (d.getFullYear() - 2) + '-01-01';
  }

  let rxPage = 1, rxLast = 1, rxBusy = false, rxStatusVal = '', rxNameTimer = null;

  const 부터칸 = document.getElementById('rxFrom');
  const 까지칸 = document.getElementById('rxTo');

  /* 고를 수 있는 날은 2년 전부터 오늘까지 — 앱의 firstDate/lastDate */
  부터칸.min = 까지칸.min = 두해전();
  부터칸.max = 까지칸.max = 오늘();
  부터칸.value = 까지칸.value = 오늘();

  function 기본기간인가() {
    return 부터칸.value === 오늘() && 까지칸.value === 오늘();
  }

  /* 되돌리기는 되돌릴 것이 있을 때만 보인다 — 앱의 onClear 잣대 */
  function 되돌리기보이기() {
    document.getElementById('rxClear').style.display = 기본기간인가() ? 'none' : 'inline-flex';
  }

  function rxDateChanged() { 되돌리기보이기(); rxLoad(true); }

  /* 비우지 않는다 — 비우면 전체가 나와, 기본 기간을 둔 뜻이 사라진다 */
  function rxResetDates() {
    부터칸.value = 까지칸.value = 오늘();
    되돌리기보이기();
    rxLoad(true);
  }

  function rxStatus(btn) {
    document.querySelectorAll('#rxChips .m-chip').forEach(b => b.classList.toggle('on', b === btn));
    rxStatusVal = btn.dataset.s;
    rxLoad(true);
  }

  /* 이름은 치는 대로 찾지 않는다 — 앱과 같은 400ms */
  function rxNameChanged() {
    clearTimeout(rxNameTimer);
    rxNameTimer = setTimeout(() => rxLoad(true), 400);
  }

  /* 목록 카드 — 앱의 _PrescriptionCard */
  function rxCard(p) {
    const 빛 = RX_COLOR[p.status] || '#9E9E9E';
    const 그림 = RX_ICON[p.status] || 'bx-circle';
    const 상병 = p.disease_name
      ? `<div class="rx-line"><span class="rx-chip"><i class="bx bx-plus-medical"></i>${mEsc(p.disease_name)}</span></div>`
      : '';
    const 발급 = p.issued_date
      ? `<i class="bx bx-calendar"></i><span>발급 ${mEsc(p.issued_date)}</span>`
      : '';
    /* 다시 올려 달라는 것이 남아 있으면 목록에서 바로 보인다 (2026-09-26 지시).
       상태 배지만으로는 「검수 보류」라 적힐 뿐 몇 건을 다시 올려야 하는지 모른다. */
    const 되물음 = (p.reupload_open > 0)
      ? `<span class="rx-redo"><i class="bx bx-upload"></i>다시 올릴 서류 ${p.reupload_open}건</span>`
      : '';
    return `
      <div class="rx-card tap" onclick="location.assign('/m/prescriptions/${encodeURIComponent(p.rx_number)}')">
        <div class="rx-top">
          <div class="rx-ico" style="background:${빛}1A; color:${빛};"><i class="bx ${그림}"></i></div>
          <b class="rx-no">${mEsc(p.rx_number)}</b>
          ${되물음}
          <span class="rx-badge" style="background:${빛}1A; color:${빛}; border:1px solid ${빛}4D;">${mEsc(p.status_label || p.status)}</span>
        </div>
        <div class="rx-rows">
          <div class="rx-line">
            <span class="rx-chip"><i class="bx bx-user"></i>${mEsc(p.patient_name || '-')}</span>
            <span class="rx-chip" style="flex:1;"><i class="bx bx-building-house"></i>${mEsc(p.hospital || '-')}</span>
          </div>
          ${상병}
        </div>
        <hr class="rx-div">
        <div class="rx-foot">${발급}<span class="sp"></span><span>${mEsc(p.created_at || '')}</span></div>
      </div>`;
  }

  async function rxLoad(처음) {
    if (rxBusy) return;
    rxBusy = true;
    if (처음) { rxPage = 1; document.getElementById('rxList').innerHTML = '<div class="m-spin"></div>'; }

    /* 앱은 한 쪽에 15건을 받는다 */
    const q = new URLSearchParams({ page: rxPage, per_page: 15 });
    if (rxStatusVal) q.set('status', rxStatusVal);
    const 이름 = document.getElementById('rxName').value.trim();
    if (이름) q.set('search', 이름);
    if (부터칸.value) q.set('date_from', 부터칸.value);
    if (까지칸.value) q.set('date_to', 까지칸.value);

    try {
      const d = await mApi('/prescriptions?' + q.toString());
      const 줄 = d.data || [];
      rxLast = d.meta?.last_page ?? 1;

      /* 앱과 같이 머리글에 건수를 적는다 — 없으면 건수를 붙이지 않는다 */
      const 총 = d.meta?.total ?? 0;
      const 밑글 = document.querySelector('.m-head .sub');
      if (밑글) 밑글.textContent = 총 > 0 ? `본인이 등록한 처방전 ${총}건` : '본인이 등록한 처방전';

      const 통 = document.getElementById('rxList');
      const html = 줄.map(rxCard).join('');
      if (처음) {
        통.innerHTML = html ||
          `<div class="m-empty"><i class="bx bx-file"></i>등록한 처방전이 없습니다.</div>`;
      } else {
        통.insertAdjacentHTML('beforeend', html);
      }
      rxPage++;
      document.getElementById('rxMore').style.display = (rxPage <= rxLast) ? '' : 'none';
    } catch (e) {
      /* 앱과 같은 오류 자리 — 무엇이 잘못됐는지 적고 다시 시도를 준다 */
      document.getElementById('rxList').innerHTML = `
        <div class="m-empty" style="color:var(--m-danger);">
          <i class="bx bx-error-circle"></i>
          <div style="color:var(--m-mute); font-weight:600;">데이터를 불러오지 못했습니다.</div>
          <div style="font-size:11px; color:var(--m-danger); margin-top:6px;">${mEsc(e.message)}</div>
          <button class="m-btn" style="width:auto; margin:14px auto 0; padding:9px 20px;"
                  onclick="rxLoad(true)">다시 시도</button>
        </div>`;
      document.getElementById('rxMore').style.display = 'none';
    } finally { rxBusy = false; }
  }

  /* ── 처방전 조회 (이름 + 생년월일) ───────────────── */
  function lookupOpen() {
    document.getElementById('lkResult').innerHTML = '';
    document.getElementById('lkName').value = document.getElementById('rxName').value.trim();
    mSheetOpen('lookupSheet');
  }

  /* 조회 결과는 목록 카드와 다르다 — 누가 올렸는지가 여기서는 가장 중요하다 */
  function lkCard(p) {
    const 요약 = [p.patient_name, p.birth_date, p.file_count != null ? `서류 ${p.file_count}장` : null]
      .filter(Boolean).map(mEsc).join(' · ');
    const 내것 = (p.is_mine !== false);
    return `
      <div class="lk-card tap" onclick="location.assign('/m/prescriptions/${encodeURIComponent(p.rx_number)}')">
        <div class="lk-top"><b>${mEsc(p.rx_number)}</b><span>${mEsc(p.status_label || p.status)}</span></div>
        <div class="lk-sum">${요약}</div>
        <div class="lk-who" style="color:${내것 ? 'var(--m-primary)' : '#546E7A'};">
          ${내것 ? '본인이 등록한 처방전' : '등록자: ' + mEsc(p.owner_name || '-')}
        </div>
      </div>`;
  }

  async function lookupGo() {
    const 이름 = document.getElementById('lkName').value.trim();
    const 생일 = document.getElementById('lkBirth').value;
    const 통   = document.getElementById('lkResult');

    if (!이름 || !생일) {
      통.innerHTML = `<div style="color:var(--m-danger); font-size:13px;">이름과 생년월일을 모두 입력해 주십시오.</div>`;
      return;
    }

    const 단추 = document.getElementById('lkBtn');
    단추.disabled = true;
    document.getElementById('lkBtnTxt').textContent = '찾는 중';
    통.innerHTML = '<div class="m-spin"></div>';

    try {
      const d = await mApi('/prescriptions/lookup?' + new URLSearchParams({ name: 이름, birth: 생일 }));
      const 줄 = d.data || [];
      통.innerHTML = 줄.length
        ? 줄.map(lkCard).join('')
        : `<div style="color:#546E7A; font-size:13px; line-height:1.6;">
             조회된 처방전이 없습니다. 이름과 생년월일을 다시 확인해 주십시오.
             검수가 완료된 처방전은 조회되지 않습니다.</div>`;
    } catch (e) {
      통.innerHTML = `<div style="color:var(--m-danger); font-size:13px;">${mEsc(e.message)}</div>`;
    } finally {
      단추.disabled = false;
      document.getElementById('lkBtnTxt').textContent = '찾기';
    }
  }

  /* 끝까지 내리면 더 부른다 — 앱의 _onScroll (200px 앞서) */
  window.addEventListener('scroll', () => {
    if (rxBusy || rxPage > rxLast) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) rxLoad(false);
  });

  되돌리기보이기();
  rxLoad(true);
</script>
@endpush
