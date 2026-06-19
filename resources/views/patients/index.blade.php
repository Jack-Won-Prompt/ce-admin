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
  .patient-table tbody tr:hover td { background:rgba(27,102,245,.04); cursor:pointer; }
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
  .card-footer { padding:12px 18px;border-top:1px solid var(--border);background:var(--bg);border-radius:0 0 var(--radius-lg) var(--radius-lg); }
</style>
@endpush

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
  <div>
    <h5 style="font-size:18px;font-weight:700;margin:0;color:var(--text-primary);">환자 정보</h5>
    <p style="font-size:13px;color:var(--text-muted);margin:4px 0 0;">
      총 <strong id="total-count">{{ $patients->total() }}</strong>명 등록
    </p>
  </div>
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="bx bx-user-plus"></i> 환자 추가
  </button>
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

{{-- 목록 --}}
<div class="card" style="overflow:hidden;">
  <div class="table-wrapper" id="table-wrapper">
    <table class="patient-table">
      <thead>
        <tr>
          <th>환자명</th>
          <th>주민등록번호</th>
          <th>생년월일</th>
          <th>성별</th>
          <th>휴대폰</th>
          <th>건보</th>
          <th>처방건수</th>
          <th>재구매일</th>
          <th>등록일</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="patient-tbody">
        @forelse($patients as $p)
        <tr onclick="location.href='{{ route('patients.show', $p) }}'">
          <td>
            <div style="font-weight:600;">{{ $p->name }}</div>
            @if($p->note)
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ Str::limit($p->note, 30) }}</div>
            @endif
          </td>
          <td style="color:var(--text-muted);font-size:12px;">{{ $p->masked_resident_no ?? '-' }}</td>
          <td style="font-size:12px;">
            @if($p->birth_date)
              {{ $p->birth_date->format('Y-m-d') }}
              <span style="color:var(--text-muted);">(만 {{ $p->age }}세)</span>
            @else -
            @endif
          </td>
          <td>
            @if($p->gender === 'male')
              <span class="gender-badge gender-male">남</span>
            @elseif($p->gender === 'female')
              <span class="gender-badge gender-female">여</span>
            @else <span style="color:var(--text-muted);">-</span>
            @endif
          </td>
          <td style="font-size:12px;">{{ $p->mobile ?? $p->phone ?? '-' }}</td>
          <td>
            @if($p->is_nhis_eligible)
              <span class="nhis-badge nhis-yes"><i class="fa-solid fa-check"></i> 급여 {{ $p->nhis_coverage_rate }}%</span>
            @else
              <span class="nhis-badge nhis-no">비급여</span>
            @endif
          </td>
          <td><span class="rx-count-badge">{{ $p->prescriptions_count }}건</span></td>
          <td style="font-size:12px;">
            @php $rd = $p->prescriptions_max_repurchase_date; @endphp
            @if($rd)
              @php $rdDate = \Carbon\Carbon::parse($rd); $diff = today()->diffInDays($rdDate, false); @endphp
              <span style="font-weight:600;color:{{ $diff < 0 ? 'var(--text-muted)' : ($diff <= 10 ? 'var(--danger)' : ($diff <= 15 ? 'var(--warning)' : 'var(--success)')) }};">
                {{ $rdDate->format('Y-m-d') }}
              </span>
              @if($diff >= 0)
                <span style="font-size:10px;color:var(--text-muted);margin-left:4px;">D-{{ $diff }}</span>
              @else
                <span style="font-size:10px;color:var(--text-muted);margin-left:4px;">D+{{ abs($diff) }}</span>
              @endif
            @else
              <span style="color:var(--text-muted);">-</span>
            @endif
          </td>
          <td style="font-size:11px;color:var(--text-muted);">{{ $p->created_at->format('Y-m-d') }}</td>
          <td onclick="event.stopPropagation();">
            <button class="btn btn-outline btn-sm" onclick="deletePatient({{ $p->id }}, '{{ addslashes($p->name) }}')"
                    style="color:var(--danger);border-color:var(--danger);padding:2px 8px;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
        @empty
        <tr id="empty-row">
          <td colspan="10" style="text-align:center;padding:60px;color:var(--text-muted);">
            <i class="fa-solid fa-users" style="font-size:32px;margin-bottom:12px;display:block;opacity:.3;"></i>
            등록된 환자가 없습니다.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($patients->hasPages())
  <div class="card-footer" style="padding:12px 16px;border-top:1px solid var(--border);">
    {{ $patients->links() }}
  </div>
  @endif
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
    if (!confirm(`"${name}" 환자를 삭제하시겠습니까?`)) return;
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
  { selector: '#table-wrapper', title: '환자 목록 테이블', body: '등록된 환자 목록입니다. 환자 이름을 클릭하면 처방·주문 이력이 포함된 상세 화면으로 이동합니다.' },
  { selector: '[onclick="openAddModal()"]', title: '환자 신규 등록', body: '<b>환자 추가</b> 버튼을 클릭하면 이름·연락처·주민번호 등을 입력하는 등록 폼이 열립니다.' },
];
</script>
@endpush
