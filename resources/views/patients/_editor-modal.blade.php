{{-- ── 거래처 등록ㆍ수정 창 ────────────────────────────────
     거래처 관리와 주문 등록, 두 화면이 함께 쓴다.

     주문 등록에서는 처방전을 보면서 고쳐야 한다. 화면 탭으로 열면 탭을 오가며
     적어야 해서, 방금 읽은 처방전 값을 외워 옮기게 된다 — 그래서 창으로 연다.

     부르는 법은 openPatientEditor() 주석에 적어 두었다. --}}
<style>
  /* 가림막 색은 전역 .modal-overlay 와 같은 중성 먹빛으로 맞췄다.
     본디 rgba(67,56,202,.3) 남보라였는데 시안·DS 램프에 없는 색이다. */
  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(13,27,42,.45);backdrop-filter:blur(2px);z-index:200;align-items:center;justify-content:center; }
  .modal-overlay.show { display:flex; }
  /* 상자 — 시안 120:917 Frame 48101489: 960×902 · r12 · bg 흰색 · bd 1px gray-200.
     .modal-box 는 layouts/app.blade.php 도 쓰는 전역 이름이라 #addModal 안으로 묶는다.
     묶지 않으면 이 화면이 열려 있는 동안 전역 확인창(.modal-box.sm)에도 테두리가 붙는다. */
  #addModal .modal-box { background:var(--bg-card);border:1px solid var(--border);border-radius:12px;width:960px;max-width:95vw;max-height:95vh;overflow-y:auto;box-shadow:0 8px 40px rgba(75,70,92,.25); }
  /* 머리·본문·바닥 규격은 Figma 120:917(환자 추가 모달) 실측 —
     머리 960×54 pad 16/24 · gap 12 · 제목 14px/700 lh22,
     본문 pad 24, 바닥 960×72 pad 16/24 · gap 8 */
  .modal-header { padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px; }
  .modal-header h3 { font-size:14px;font-weight:700;line-height:22px;margin:0;flex:1;color:var(--text-primary); }
  .modal-body   { padding:24px; }
  .modal-footer { padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--gray-0);border-radius:0 0 12px 12px; }
  /* 시안 하단 버튼은 65×40 / 120×40 · r8 · pad 0/20 · 14px/500 lh22
     (본문 버튼 h32 · 13px/500 과 다른 유일한 자리). */
  .modal-footer .btn { height:40px;padding:0 20px;font-size:14px;font-weight:500;line-height:22px;
    display:inline-flex;align-items:center;justify-content:center;gap:8px; }
  .modal-footer #btn-add-save { min-width:120px; }
  /* 아래 폼 규칙은 전부 #addModal 안으로 묶는다.
     .form-group · .form-label · .form-control 은 layouts/app.blade.php 가 쓰는 전역 이름이고,
     이 화면이 열려 있는 동안 전역 문의하기 옆판(.side-panel .sp-form .form-group)까지
     라벨 100px 가로 배치로 바뀌어 버린다(실측 확인: 라벨 위 → 라벨 왼쪽). */
  /* 본문 2단 — 시안 912 = 444 + gap 24 + 444, 줄 사이 gap 8 */
  #addModal .form-grid-2  { display:grid;grid-template-columns:1fr 1fr;column-gap:24px;row-gap:8px; }
  /* 한 줄 444×32 = 라벨 100 고정(13/500 lh16 gray-700) + gap 8 + 컨트롤 336×32.
     라벨이 입력 위가 아니라 왼쪽에 붙는다.
     전역 .form-group 의 margin-bottom:10px 을 걷어낸다 — 걷지 않으면 2단 묶음 안 칸에도
     10px 이 붙어 줄 사이가 8 이 아니라 18 이 된다(시안 Frame 48101644 gap 8). */
  #addModal .form-group   { display:flex;flex-direction:row;align-items:center;gap:8px;margin-bottom:0; }
  /* 전역 .form-label 은 display:block · margin-bottom:5px 을 갖고 있다.
     가로 배치에서는 그 여백이 라벨을 위로 밀어 올리므로 걷어낸다. */
  #addModal .form-group .form-label { flex:0 0 100px;width:100px;margin-bottom:0;
    font-size:13px;font-weight:500;line-height:16px;color:var(--gray-700); }
  #addModal .form-group > .form-control { flex:1 1 auto;min-width:0; }
  /* 여러 줄 입력(메모)은 라벨을 첫 줄에 맞춰 위로 붙인다 */
  #addModal .form-group:has(textarea) { align-items:flex-start; }
  #addModal .form-group:has(textarea) .form-label { padding-top:8px; }
  /* 2단 밖에 홀로 선 줄도 시안과 같은 444 폭을 지킨다(912 의 절반 - gap 12) */
  #addModal .modal-body > .form-group { width:calc(50% - 12px); }
  #addModal .modal-body > .form-group:has(textarea) { width:100%; }
  /* 주소는 두 열을 다 쓴다. 절반(444)에 우편번호ㆍ도로명ㆍ찾기 단추를 함께 넣으면
     도로명 칸이 150 남짓으로 눌려, 찾아 넣은 주소가 늘 끝에서 잘려 보였다. */
  #addModal .modal-body > .form-group.wide { width:100%; }
</style>

{{-- 거래처 등록 모달 --}}
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i>
      <h3 id="addModalTitle">거래처 등록</h3>
      <button onclick="closeAddModal()" style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;flex-shrink:0;padding:0;border:none;border-radius:6px;background:none;font-size:16px;line-height:1;cursor:pointer;color:var(--gray-500);">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          {{-- 사업부를 먼저 고른다 — IC 면 저장되는 이름 앞에 (E) 가 붙는다(위드웍스 표기). --}}
          <label class="form-label">사업부</label>
          <select class="form-control" id="add-care-type">
            <option value="">선택</option>
            <option value="IC">IC (카테터)</option>
            <option value="OC">OC (장루)</option>
          </select>
        </div>
        <div class="form-group">
          {{-- 필수 표시는 전역 .form-label span 이 var(--danger) 로 그린다.
               인라인 color:red 는 그 규칙을 덮어 DS 밖 빨강이 되므로 걷어냈다. --}}
          <label class="form-label">이름 <span>*</span></label>
          <input type="text" class="form-control" id="add-name" placeholder="홍길동" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">주민등록번호</label>
          <input type="text" class="form-control" id="add-resident" placeholder="XXXXXX-XXXXXXX" />
        </div>
        <div class="form-group">
          <label class="form-label">생년월일</label>
          <input type="date" class="form-control" id="add-birth" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">성별</label>
          <select class="form-control" id="add-gender">
            <option value="">선택</option>
            <option value="male">남</option>
            <option value="female">여</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">구분(SB/SCI)</label>
          <select class="form-control" id="add-sb-sci">
            <option value="">선택</option>
            <option value="SB">SB</option>
            <option value="SCI">SCI</option>
          </select>
        </div>
      </div>

      {{-- ── 연락 ──────────────────────────────────────────
           어디로 거는 것이 나은지와 지금 닿는지를 함께 적는다. 적어 두지 않으면
           담당자가 바뀔 때마다 처음부터 다시 알아내야 한다(요청서 2쪽). --}}
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">연락 상태</label>
          <select class="form-control" id="add-contact-status">
            <option value="">선택</option>
            @foreach(\App\Models\Patient::CONTACT_STATUSES as $k => $label)
              <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">연락 선호 방식</label>
          <select class="form-control" id="add-contact-channel">
            <option value="">선택</option>
            @foreach(\App\Models\Patient::CONTACT_CHANNELS as $k => $label)
              <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">전화번호1</label>
          <input type="text" class="form-control" id="add-mobile" placeholder="010-XXXX-XXXX" data-phone />
        </div>
        <div class="form-group">
          <label class="form-label">전화번호2</label>
          <input type="text" class="form-control" id="add-phone" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="add-email" placeholder="name@example.com" />
        </div>
        <div class="form-group">
          <label class="form-label">Fax</label>
          <input type="text" class="form-control" id="add-fax" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>

      {{-- 주소는 주문 등록·환자 상세와 같은 구성이다 — 우편번호·도로명은 찾아서 채우고
           상세만 사람이 적는다. 한 칸에 몰아 적으면 주문 낼 때 다시 갈라야 한다. --}}
      {{-- 이 줄만 두 열을 다 쓴다. 라벨은 왼쪽에 두고 오른쪽을 세로로 쌓아 두 줄을
           만든다 — .form-group 이 가로 flex 라, 안의 줄에 자리를 정해 주지 않으면
           도로명 칸이 40px 까지 눌리고 상세 주소가 같은 줄로 올라온다. --}}
      <div class="form-group wide" style="margin-bottom:8px;align-items:flex-start;">
        <label class="form-label" style="padding-top:8px;">주소</label>
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;">
          <div style="display:flex;gap:6px;align-items:center;">
            <input type="text" class="form-control" id="add-postcode" readonly placeholder="우편번호"
                   style="flex:0 0 92px;background:var(--gray-50);cursor:default;" />
            <input type="text" class="form-control" id="add-address" readonly placeholder="도로명 주소"
                   style="flex:1;min-width:0;background:var(--gray-50);cursor:default;" />
            <button type="button" class="ds-btn" onclick="addFindAddress()" style="flex:0 0 auto;">
              <i class="fa-solid fa-magnifying-glass"></i> 주소 검색
            </button>
          </div>
          <input type="text" class="form-control" id="add-address-detail" placeholder="상세 주소" />
        </div>
      </div>

      {{-- ── 돈 ──────────────────────────────────────────
           보내는 사람이 환자와 다른 일이 잦다(보호자가 보낸다). 입금자명이 달라
           맞춰 보지 못하는 일을 막으려고 미리 적어 둔다. --}}
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">송금자명</label>
          <input type="text" class="form-control" id="add-remitter" placeholder="입금자명이 다르면 적습니다" />
        </div>
        <div class="form-group">
          <label class="form-label">현금영수증</label>
          <div style="display:flex;gap:6px;">
            {{-- 주문 등록의 같은 칸과 값이 같아야 한다 — 두 화면이 서로 다른 말을 하면 안 된다.
                 자진발급이면 번호가 정해져 있어 자동으로 채운다. --}}
            <select class="form-control" id="add-deduction" style="flex:0 0 120px;">
              <option value="">선택</option>
              @foreach(\App\Models\Patient::DEDUCTION_TYPES as $t)
                <option value="{{ $t }}">{{ $t }}</option>
              @endforeach
            </select>
            <input type="text" class="form-control" id="add-cash-receipt"
                   placeholder="010-XXXX-XXXX" data-phone style="flex:1;min-width:0;" />
          </div>
        </div>
      </div>

      {{-- ── 공단 · 기초 (요청서 3쪽) ────────────────────── --}}
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">건보등록</label>
          <select class="form-control" id="add-nhis-reg">
            <option value="">선택</option>
            @foreach(\App\Models\Patient::NHIS_REG_STATUSES as $v)
              <option value="{{ $v }}">{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">건보등록일</label>
          <input type="date" class="form-control" id="add-nhis-reg-date" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">건보 재등록 대상자</label>
          <select class="form-control" id="add-nhis-renew">
            <option value="">선택</option>
            <option value="Y">Y</option>
            <option value="N">N</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">건보 재등록 기한</label>
          <input type="date" class="form-control" id="add-nhis-renew-due" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">건보위임동의 시작일</label>
          <input type="date" class="form-control" id="add-agree-start" />
        </div>
        <div class="form-group">
          <label class="form-label">건보위임동의 종료일</label>
          <input type="date" class="form-control" id="add-agree-end" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">기초(의료급여) 재평가 대상자</label>
          <select class="form-control" id="add-basic-reeval">
            <option value="">선택</option>
            <option value="Y">Y</option>
            <option value="N">N</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">기초(의료급여) 재평가 기한</label>
          <input type="date" class="form-control" id="add-basic-due" />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">메모</label>
        <textarea class="form-control" id="add-note" rows="2" placeholder="특이사항 등"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeAddModal()">취소</button>
      <button class="btn btn-primary" id="btn-add-save" onclick="savePatient()">
        <i class="fa-solid fa-floppy-disk"></i> 저장
      </button>
    </div>
  </div>
</div>
@push('scripts')
<script>
(function () {
  /* 지금 무엇을 하고 있는가 — 등록인가 수정인가, 누구를, 끝나면 무엇을 할 것인가.
     창은 하나뿐이라 이 셋만 갈아 끼우면 두 가지 일을 다 한다. */
  let _peMode = 'create', _peId = null, _peDone = null;

  /* 이 셋은 창의 onclick 이 부른다 — 인라인 handler 는 전역에서만 이름을 찾으므로
     감싸 둔 함수 안에 두면 「is not defined」로 죽는다. */
  window.openAddModal  = function () { openPatientEditor(); };
  window.closeAddModal = function () { document.getElementById('addModal').classList.remove('show'); };

  /**
   * 거래처 등록ㆍ수정 창을 연다.
   *
   * 처방전을 보면서 고쳐야 하는 자리가 있어(주문 등록) 화면을 옮기지 않고 이 창으로 한다.
   *
   *   openPatientEditor()                                  거래처 관리의 「거래처 등록」
   *   openPatientEditor({ prefill:{name:'임윤아'} })         이름 조회에서 못 찾았을 때
   *   openPatientEditor({ id: 7, onSaved:fn })              고치기
   */
  window.openPatientEditor = async function (opts = {}) {
    _peMode = opts.id ? 'edit' : 'create';
    _peId   = opts.id ?? null;
    _peDone = opts.onSaved ?? null;

    peClear();
    document.getElementById('addModalTitle').textContent =
      _peMode === 'edit' ? '거래처 수정' : '거래처 등록';

    if (_peMode === 'edit') {
      /* 고칠 것을 먼저 읽어 채운다. 빈 창을 띄우고 나중에 채우면, 그 사이에 사람이
         손을 대기 시작해 방금 친 것이 서버 값으로 덮인다. */
      const res = await apiRequest(`/patients/${_peId}/detail`, 'GET');
      if (!res?.account) { showToast('거래처를 불러오지 못했습니다.', 'danger'); return; }
      peFill(res.account);

      /* 주민번호는 암호로 담겨 있어 원문을 내주지 않는다. 가린 것을 보여 주고,
         그대로면 저장 때 아예 보내지 않는다 — 빈 값을 보내면 적혀 있던 번호가 지워진다.
         거래처 상세 화면이 쓰는 수와 같다. */
      const rn = document.getElementById('add-resident');
      rn.value = res.resident_masked ?? '';
      rn.dataset.masked = rn.value;
    } else if (opts.prefill) {
      peFill(opts.prefill);
    }

    document.getElementById('addModal').classList.add('show');
    setTimeout(() => document.getElementById('add-name')?.focus(), 50);
  };

  /* 창은 하나를 돌려 쓴다 — 지난번에 적은 것이 남아 있으면 새 사람에 그것이 붙는다 */
  function peClear() {
    document.querySelectorAll('#addModal input, #addModal textarea').forEach(el => { el.value = ''; });
    document.querySelectorAll('#addModal select').forEach(el => { el.selectedIndex = 0; });
    delete document.getElementById('add-resident').dataset.masked;
  }

  /* 열쇠 이름이 곧 칸 이름이다(add-<열쇠>, 밑줄은 붙임표로) — 짝이 없으면 지나간다 */
  function peFill(data) {
    Object.entries(data || {}).forEach(([k, v]) => {
      if (v === null || v === undefined) return;
      const el = document.getElementById('add-' + k.replace(/_/g, '-'));
      if (el) el.value = v;
    });
    // 칸 이름이 열쇠와 다른 것만 따로 맞춘다
    const 짝 = { resident_no: 'add-resident', birth_date: 'add-birth',
                 remitter_name: 'add-remitter', cash_receipt_no: 'add-cash-receipt',
                 nhis_reg_status: 'add-nhis-reg', nhis_reg_date: 'add-nhis-reg-date',
                 nhis_agree_start: 'add-agree-start', nhis_agree_end: 'add-agree-end',
                 basic_reeval_due: 'add-basic-due', phone: 'add-phone' };
    Object.entries(짝).forEach(([k, id]) => {
      const el = document.getElementById(id);
      if (el && data?.[k] != null) el.value = data[k];
    });
  }

  window.savePatient = async function () {
    const name = document.getElementById('add-name').value.trim();
    if (!name) { showToast('이름은 필수입니다.', 'warning'); return; }

    const btn = document.getElementById('btn-add-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      care_type:           document.getElementById('add-care-type').value            || null,
      // 가린 것 그대로면 「고치지 않았다」 — 보내지 않으면 서버가 건드리지 않는다
      resident_no:         (function (el) {
                             const v = el.value.trim();
                             return (v === '' || v === el.dataset.masked) ? undefined : v;
                           })(document.getElementById('add-resident')),
      birth_date:          document.getElementById('add-birth').value               || null,
      gender:              document.getElementById('add-gender').value               || null,
      mobile:              document.getElementById('add-mobile').value.trim()        || null,
      phone:               document.getElementById('add-phone').value.trim()         || null,
      address:             document.getElementById('add-address').value.trim()       || null,
      postcode:            document.getElementById('add-postcode').value.trim()      || null,
      address_detail:      document.getElementById('add-address-detail').value.trim()|| null,
      note:                document.getElementById('add-note').value.trim()          || null,

      // ── 화면 확정요청 2026-08-27 (2ㆍ3쪽) ──
      sb_sci:          val('add-sb-sci'),
      contact_status:  val('add-contact-status'),
      contact_channel: val('add-contact-channel'),
      email:           val('add-email'),
      fax:             val('add-fax'),
      remitter_name:   val('add-remitter'),
      deduction:       val('add-deduction'),
      cash_receipt_no: val('add-cash-receipt'),
      nhis_reg_status:  val('add-nhis-reg'),
      nhis_reg_date:    val('add-nhis-reg-date'),
      nhis_renew:       val('add-nhis-renew'),
      nhis_renew_due:   val('add-nhis-renew-due'),
      nhis_agree_start: val('add-agree-start'),
      nhis_agree_end:   val('add-agree-end'),
      basic_reeval:     val('add-basic-reeval'),
      basic_reeval_due: val('add-basic-due'),
    };

    const res = _peMode === 'edit'
      ? await apiRequest(`/patients/${_peId}`, 'PUT', payload)
      : await apiRequest('/patients', 'POST', payload);

    if (res.success) {
      BtnState.success(btn, '저장 완료');
      closeAddModal();
      showToast(res.message, 'success');
      const id = res.id ?? _peId;
      ptTell({ action: 'saved', id, name, created: _peMode === 'create' });

      /* 부른 쪽이 뒤를 잇는다. 잇는 말이 없으면 예전대로 그 사람의 상세로 간다 —
         거래처 관리에서 등록했을 때의 걸음이다. */
      if (_peDone) _peDone({ id, name, created: _peMode === 'create' });
      else setTimeout(() => location.href = `${BASE_URL}/patients/${id}`, 800);
    } else {
      BtnState.error(btn, '저장 실패');
      showToast(res.message || '저장 실패', 'danger');
    }
  };

  /* 자진발급이면 번호가 정해져 있다(010-000-1234). 저장한 뒤에야 채워지면 담당자는
     비어 있는 줄 알고 손으로 적는다 — 고르는 그 자리에서 채운다. */
  document.getElementById('add-deduction')?.addEventListener('change', function () {
    const no = document.getElementById('add-cash-receipt');
    if (this.value === '자진발급' && !no.value.trim()) no.value = '010-000-1234';
  });
})();
</script>
@endpush
