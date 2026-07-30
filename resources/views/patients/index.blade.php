@extends('layouts.app')

@section('title', '환자 정보')
@section('page-title', '환자 관리')
@section('breadcrumb', '홈 / 환자 관리')

@section('help-title', '환자 관리 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>등록된 환자 목록을 조회하고 처방 이력을 관리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 기능</div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-search"></i></div>
    <div class="help-item-text"><strong>환자 검색</strong>이름, 전화번호, 진단코드로 검색할 수 있습니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-history"></i></div>
    <div class="help-item-text"><strong>처방 이력</strong>환자 상세 화면에서 처방 및 주문 이력 전체를 확인합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon info"><i class="bx bx-repeat"></i></div>
    <div class="help-item-text"><strong>재구매 알림</strong>처방 주기에 따른 재구매 대상자를 확인할 수 있습니다.</div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=5">
<style>
  /* ── Patient table ── */
  .patient-table { width:100%; border-collapse:collapse; }
  .patient-table thead th {
    position: sticky; top: 0; z-index: 5;
    background: var(--bg); font-size:11px; font-weight:700; color:var(--text-muted);
    text-transform:uppercase; padding:11px 14px; letter-spacing:.6px;
    border-bottom: 2px solid var(--border); text-align:left; white-space:nowrap;
  }
  .patient-table td { padding:11px 14px; border-bottom:1px solid var(--border-light); font-size:13px; vertical-align:middle; }
  .patient-table tbody tr:hover td { background:rgba(0,176,202,.04); cursor:pointer; }
  .patient-table tbody tr:last-child td { border-bottom:none; }

  /* Vuexy-style soft badges */
  .nhis-badge   { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600; }
  .nhis-yes     { background:var(--success-light);color:var(--success); }
  .nhis-no      { background:var(--border-light);color:var(--text-muted); }
  .gender-badge { display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600; }
  .gender-male  { background:var(--primary-light);color:var(--primary); }
  .gender-female{ background:#fce7f3;color:#c026a0; }
  .rx-count-badge { display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:var(--primary-light);color:var(--primary); }

  /* ── Modal (Vuexy style) ── */
  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(67,56,202,.3);backdrop-filter:blur(2px);z-index:200;align-items:center;justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:var(--bg-card);border-radius:12px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(75,70,92,.25); }
  .modal-header { padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px; }
  .modal-header h3 { font-size:15px;font-weight:700;margin:0;flex:1;color:var(--text-primary); }
  .modal-body   { padding:22px; }
  .modal-footer { padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;background:var(--bg);border-radius:0 0 12px 12px; }
  .form-grid-2  { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
  .form-group   { display:flex;flex-direction:column;gap:5px; }

  .filter-bar { display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap; }
  /* 패널 탭(조회결과/상세내용) */
  .pnl-tabs { display:flex; gap:4px; margin-bottom:16px; border-bottom:2px solid var(--border); }
  .pnl-tab { padding:9px 18px; font-size:13.5px; font-weight:700; border:none; background:none; cursor:pointer;
    color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-2px; display:inline-flex; align-items:center; gap:6px; }
  .pnl-tab:hover { color:var(--primary); }
  .pnl-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .pnl-empty { color:var(--text-muted); font-size:13.5px; text-align:center; padding:60px 20px;
    background:#fff; border:1px dashed var(--border); border-radius:var(--radius); }
  /* 상세내용 탭 안 이력 카드(전체폭) */
  .pt-detail { background:#fff; border:1px solid var(--border);
    border-radius:var(--radius-lg); display:flex; flex-direction:column; overflow:hidden; }
  .pt-detail-head { display:flex; align-items:center; gap:8px; padding:11px 14px; border-bottom:1px solid var(--border); }
  .pt-detail .tab-bar { display:flex; border-bottom:1px solid var(--border); padding:0 6px; overflow-x:auto; }
  .pt-detail .tab-btn { padding:10px 11px; font-size:12.5px; font-weight:700; color:var(--text-muted);
    border:none; background:none; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px;
    display:inline-flex; align-items:center; gap:5px; white-space:nowrap; }
  .pt-detail .tab-btn:hover { color:var(--primary); }
  .pt-detail .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
  .pt-detail .tab-btn .cnt { background:var(--bg); color:var(--text-secondary); border-radius:10px; padding:0 6px; font-size:10.5px; }
  .pt-pane { display:none; padding:8px 14px 14px; overflow-y:auto; max-height:calc(100vh - 300px); }
  .pt-pane.active { display:block; }
  .pt-hrow { display:flex; align-items:center; gap:10px; padding:9px 4px; border-bottom:1px solid var(--border-light); font-size:12.5px; cursor:pointer; }
  .pt-hrow:last-child { border-bottom:none; }
  .pt-hrow:hover { background:var(--bg); border-radius:6px; }
  .pt-hrow .pt-h-main { flex:1; min-width:0; }
  .pt-hrow .pt-h-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }
  .pt-empty { text-align:center; color:var(--text-muted); padding:36px 12px; font-size:12.5px; }
  .card-footer { padding:12px 18px;border-top:1px solid var(--border);background:var(--bg);border-radius:0 0 var(--radius-lg) var(--radius-lg); }
</style>
@endpush

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
  <div>
    <h5 style="font-size:18px;font-weight:700;margin:0;color:var(--text-primary);">환자 정보</h5>
    <p style="font-size:13px;color:var(--text-muted);margin:4px 0 0;">
      총 <strong id="total-count">{{ number_format($total) }}</strong>명 등록
    </p>
  </div>
  @perm('patients', 'create')
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="bx bx-user-plus"></i> 환자 추가
  </button>
  @endperm
</div>

{{-- 필터 --}}
<form method="GET" action="{{ route('patients.index') }}" class="filter-bar">
  <div style="position:relative;flex:1;min-width:200px;">
    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;"></i>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="이름 또는 전화번호"
           class="form-control" style="padding-left:30px;" />
  </div>
  <select name="nhis" class="form-control" style="width:130px;">
    <option value="">건보 전체</option>
    <option value="1" @selected(request('nhis')==='1')>급여 대상</option>
    <option value="0" @selected(request('nhis')==='0')>비급여</option>
  </select>
  <select name="per_page" class="form-control" style="width:100px;">
    <option value="10"  @selected(request('per_page','10')==='10')>10개씩</option>
    <option value="15"  @selected(request('per_page','10')==='15')>15개씩</option>
    <option value="30"  @selected(request('per_page','10')==='30')>30개씩</option>
  </select>
  <button type="submit" class="btn btn-outline">검색</button>
  @if(request()->hasAny(['q','nhis','repurchase_within']))
    <a href="{{ route('patients.index') }}" class="btn btn-outline">초기화</a>
  @endif

  {{-- 재구매일 기간 필터 --}}
  <div style="display:flex;gap:6px;margin-left:auto;">
    @foreach([10 => '재구매일 10일 이내', 15 => '재구매일 15일 이내', 30 => '재구매일 30일 이내'] as $days => $label)
      <a href="{{ route('patients.index', array_merge(request()->except('repurchase_within','page'), ['repurchase_within' => $days])) }}"
         class="btn btn-sm {{ request('repurchase_within') == $days ? 'btn-primary' : 'btn-outline' }}"
         style="white-space:nowrap;">
        <i class="fa-solid fa-calendar-check"></i> {{ $label }}
      </a>
    @endforeach
  </div>
</form>

{{-- 패널 탭: 조회결과 / 상세내용 (검색 필터 아래) --}}
<div class="pnl-tabs">
  <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회결과</button>
  <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')"><i class="fa-solid fa-file-lines"></i> 상세내용</button>
</div>

<div id="pnlList">
  <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
    <span style="font-size:12px;color:var(--text-muted);"><i class="bx bx-info-circle"></i> 환자 행을 <b>더블클릭</b>하면 상세내용 탭에서 처방전·상담·구매 이력을 확인합니다.</span>
    <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
  </div>
  <div id="patientGrid"></div>
</div>

{{-- ── 상세내용 탭 ── --}}
<div id="pnlDetail" style="display:none;">
  <div style="margin-bottom:12px;">
    <button type="button" class="btn btn-outline btn-sm" onclick="pnlShow('list')"><i class="bx bx-arrow-back"></i> 조회결과로</button>
  </div>
  <div id="pdEmpty" class="pnl-empty">조회결과에서 환자 행을 <b>더블클릭</b>하면 이력이 여기에 표시됩니다.</div>
  <div class="pt-detail" id="patientDetail" style="display:none;">
    <div class="pt-detail-head">
      <i class="bx bx-user-pin" style="color:var(--primary);font-size:18px;"></i>
      <span id="pdName" style="font-weight:800;font-size:15px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">-</span>
      <a id="pdMore" href="#" class="btn btn-outline btn-sm" style="margin-left:auto;white-space:nowrap;">전체 상세</a>
    </div>
    <div class="tab-bar">
      <button type="button" class="tab-btn active" data-tab="rx"       onclick="ptTab('rx')"><i class="fa-solid fa-file-medical"></i> 처방전 이력 <span class="cnt" id="pdCntRx">0</span></button>
      <button type="button" class="tab-btn"        data-tab="counsel"  onclick="ptTab('counsel')"><i class="fa-solid fa-comments"></i> 상담이력 <span class="cnt" id="pdCntCs">0</span></button>
      <button type="button" class="tab-btn"        data-tab="purchase" onclick="ptTab('purchase')"><i class="fa-solid fa-cart-shopping"></i> 구매이력 <span class="cnt" id="pdCntPu">0</span></button>
    </div>
    <div class="pt-pane active" id="pd-rx"></div>
    <div class="pt-pane" id="pd-counsel"></div>
    <div class="pt-pane" id="pd-purchase"></div>
  </div>
</div>

{{-- 환자 추가 모달 --}}
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i>
      <h3>환자 추가</h3>
      <button onclick="closeAddModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">환자명 <span style="color:red;">*</span></label>
          <input type="text" class="form-control" id="add-name" placeholder="홍길동" />
        </div>
        <div class="form-group">
          <label class="form-label">주민등록번호</label>
          <input type="text" class="form-control" id="add-resident" placeholder="XXXXXX-XXXXXXX" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">생년월일</label>
          <input type="date" class="form-control" id="add-birth" />
        </div>
        <div class="form-group">
          <label class="form-label">성별</label>
          <select class="form-control" id="add-gender">
            <option value="">선택</option>
            <option value="male">남</option>
            <option value="female">여</option>
          </select>
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">휴대폰</label>
          <input type="text" class="form-control" id="add-mobile" placeholder="010-XXXX-XXXX" data-phone />
        </div>
        <div class="form-group">
          <label class="form-label">일반 전화</label>
          <input type="text" class="form-control" id="add-phone" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">주소</label>
        <input type="text" class="form-control" id="add-address" placeholder="주소 입력" />
      </div>
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">건강보험번호</label>
          <input type="text" class="form-control" id="add-insurance-no" placeholder="건강보험 번호" />
        </div>
        <div class="form-group">
          <label class="form-label">건보 적용</label>
          <select class="form-control" id="add-nhis">
            <option value="0">비급여</option>
            <option value="1">급여 대상</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">급여율 (%)</label>
        <input type="number" class="form-control" id="add-coverage" value="90" min="0" max="100" />
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

@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=5"></script>
<script>
(function () {
  const DETAIL_BASE = @json(url('patients'));
  const grid = new wwGrid({
    el: document.getElementById('patientGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '환자명',       name: 'name',            width: 110, sortable: true },
      { header: '주민등록번호', name: 'resident_no',     width: 130 },
      { header: '생년월일',     name: 'birth_date',      width: 160, sortable: true },
      { header: '성별',         name: 'gender',          width: 60,  align: 'center', sortable: true },
      { header: '휴대폰',       name: 'mobile',          width: 130 },
      { header: '건보',         name: 'nhis',            width: 90,  align: 'center', sortable: true },
      { header: '처방건수',     name: 'rx_count',        width: 80,  editor: 'number', align: 'center', sortable: true },
      { header: '재구매일',     name: 'repurchase_date', width: 160, sortable: true },
      { header: '등록일',       name: 'created',         width: 110, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__patientGrid = grid;

  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
  // 이력 행 클릭 → 해당 상세(처방전 검수·주문)를 워크스페이스 새 탭으로 (환자 목록 유지)
  const hrow = (main, sub, right, url, label) =>
    '<div class="pt-hrow" '
      + (url ? 'onclick="ceOpenTab(\'' + url + '\', \'' + (label || '상세') + '\', \'bx-scan\')"' : '')
      + '>' +
      '<div class="pt-h-main"><div style="font-weight:600;">' + main + '</div><div class="pt-h-sub">' + sub + '</div></div>' +
      (right ? '<div style="white-space:nowrap;text-align:right;">' + right + '</div>' : '') +
    '</div>';
  const emptyBox = t => '<div class="pt-empty">' + t + '</div>';

  window.ptTab = function (name) {
    document.querySelectorAll('.pt-detail .tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.pt-pane').forEach(p => p.classList.toggle('active', p.id === 'pd-' + name));
  };
  // 패널 탭 전환(조회결과/상세내용)
  window.pnlShow = function (which) {
    document.getElementById('pnlList').style.display   = which === 'detail' ? 'none' : '';
    document.getElementById('pnlDetail').style.display = which === 'detail' ? '' : 'none';
    document.getElementById('pnlBtnList').classList.toggle('active', which !== 'detail');
    document.getElementById('pnlBtnDetail').classList.toggle('active', which === 'detail');
  };

  async function ptLoad(id) {
    document.getElementById('pdEmpty').style.display = 'none';
    const panel = document.getElementById('patientDetail');
    panel.style.display = 'flex';
    window.pnlShow('detail');
    document.getElementById('pdName').textContent = '불러오는 중...';
    ['pd-rx', 'pd-counsel', 'pd-purchase'].forEach(i => document.getElementById(i).innerHTML = emptyBox('불러오는 중...'));
    try {
      const res = await fetch(DETAIL_BASE + '/' + id + '/histories', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const d = await res.json();
      document.getElementById('pdName').textContent = d.name + ' 이력';
      document.getElementById('pdMore').setAttribute('href', DETAIL_BASE + '/' + id);
      document.getElementById('pdCntRx').textContent = d.prescriptions.length;
      document.getElementById('pdCntCs').textContent = d.counseling.length;
      document.getElementById('pdCntPu').textContent = d.purchases.length;

      document.getElementById('pd-rx').innerHTML = d.prescriptions.length
        ? d.prescriptions.map(r => hrow(esc(r.rx_number), esc(r.hospital) + ' · ' + esc(r.date), '<span class="badge bg-label-primary">' + esc(r.status) + '</span>', r.url, esc(r.rx_number) + ' 검수')).join('')
        : emptyBox('처방전 이력이 없습니다.');

      document.getElementById('pd-counsel').innerHTML = d.counseling.length
        ? d.counseling.map(c => hrow(esc(c.counsel_no), esc(c.rx_number) + ' · ' + esc(c.date) + (c.note ? ' · ' + esc(c.note) : ''), '', c.url, esc(c.rx_number) + ' 검수')).join('')
        : emptyBox('상담 이력이 없습니다.');

      document.getElementById('pd-purchase').innerHTML = d.purchases.length
        ? d.purchases.map(o => hrow(esc(o.order_number), esc(o.product) + ' · ' + esc(o.date), '<div>' + Number(o.amount).toLocaleString() + '원</div><div class="pt-h-sub">' + esc(o.status) + '</div>', o.url, esc(o.order_number) + ' 주문')).join('')
        : emptyBox('구매 이력이 없습니다.');

      window.ptTab('rx');
    } catch (e) {
      document.getElementById('pdName').textContent = '불러오기 실패';
      ['pd-rx', 'pd-counsel', 'pd-purchase'].forEach(i => document.getElementById(i).innerHTML = emptyBox('불러오지 못했습니다.'));
    }
  }

  // 행 더블클릭 → 우측에 이력 상세 표시
  document.getElementById('patientGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row && row.id) ptLoad(row.id);
  });
})();
</script>
<script>
  // ── 모달 ──────────────────────────────────────────────
  function openAddModal()  { document.getElementById('addModal').classList.add('show'); }
  function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

  async function savePatient() {
    const name = document.getElementById('add-name').value.trim();
    if (!name) { showToast('환자명은 필수입니다.', 'warning'); return; }

    const btn = document.getElementById('btn-add-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      resident_no:         document.getElementById('add-resident').value.trim()     || null,
      birth_date:          document.getElementById('add-birth').value               || null,
      gender:              document.getElementById('add-gender').value               || null,
      mobile:              document.getElementById('add-mobile').value.trim()        || null,
      phone:               document.getElementById('add-phone').value.trim()         || null,
      address:             document.getElementById('add-address').value.trim()       || null,
      health_insurance_no: document.getElementById('add-insurance-no').value.trim() || null,
      is_nhis_eligible:    document.getElementById('add-nhis').value === '1',
      nhis_coverage_rate:  parseInt(document.getElementById('add-coverage').value)   || 0,
      note:                document.getElementById('add-note').value.trim()          || null,
    };

    const res = await apiRequest('/patients', 'POST', payload);

    if (res.success) {
      BtnState.success(btn, '저장 완료');
      closeAddModal();
      showToast(res.message, 'success');
      setTimeout(() => location.href = `${BASE_URL}/patients/${res.id}`, 800);
    } else {
      BtnState.error(btn, '저장 실패');
      showToast(res.message || '저장 실패', 'danger');
    }
  }

  async function deletePatient(id, name) {
    if (!await ceConfirm(`"${name}" 환자를 삭제하시겠습니까?`, { tone: 'danger', confirmText: '삭제' })) return;
    const res = await apiRequest(`/patients/${id}`, 'DELETE');
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 600);
    }
  }

  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAddModal(); });
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.filter-bar', title: '환자 검색', body: '이름, 전화번호, 주민번호 앞자리로 검색합니다. 엔터 또는 검색 버튼을 누르세요.' },
  { selector: '#patientGrid', title: '환자 목록', body: '등록된 환자 목록입니다. 행을 체크한 뒤 <b>선택 상세</b> 버튼을 누르면 처방·주문 이력이 포함된 상세 화면으로 이동합니다.' },
  { selector: '[onclick="openAddModal()"]', title: '환자 신규 등록', body: '<b>환자 추가</b> 버튼을 클릭하면 이름·연락처·주민번호 등을 입력하는 등록 폼이 열립니다.' },
];
</script>
@endpush
