@extends('layouts.app')

@section('title', $patient->name . ' — 환자 상세')
@section('page-title', '환자 상세')
@section('breadcrumb', '홈 / 환자 관리 / ' . $patient->name)

@push('styles')
<style>
  .detail-layout { display:grid; grid-template-columns:340px 1fr; gap:18px; }
  .info-row { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
  .info-row:last-child { border-bottom:none; }
  .info-label { font-size:11px; font-weight:700; color:var(--text-muted); width:100px; flex-shrink:0; padding-top:2px; }
  .info-value { font-size:13px; color:var(--text-primary); flex:1; }

  .edit-form { display:none; }
  .edit-form.active { display:block; }
  .view-panel { display:block; }
  .view-panel.hidden { display:none; }

  .form-group { margin-bottom:12px; }
  .form-label { font-size:12px; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:4px; }
  .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

  .rx-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); cursor:pointer; }
  .rx-row:last-child { border-bottom:none; }
  .rx-row:hover { background:var(--bg); border-radius:var(--radius); padding-left:8px; }
  .rx-status { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }

  /* Vuexy underline tabs */
  .tab-bar {
    display:flex; border-bottom:2px solid var(--border); margin-bottom:18px;
  }
  .tab-btn {
    padding:10px 20px; font-size:13px; font-weight:600;
    color:var(--text-muted); border:none; background:transparent;
    border-bottom:1px solid transparent; margin-bottom:-1px;
    cursor:pointer; transition:var(--transition);
  }
  .tab-btn:hover { color:var(--primary); }
  .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
  .tab-pane { display:none; }
  .tab-pane.active { display:block; }

  @media(max-width:900px) { .detail-layout { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

{{-- 위쪽 이름 띠는 두지 않는다. 바로 아래 카드가 같은 이름을 다시 적고 있어 화면을
     열면 이름이 두 번 보였다. 손댈 단추(수정·삭제·상담내역)는 그 이름 옆으로 옮겼다. --}}

<div class="detail-layout">

  {{-- 좌측: 환자 정보 --}}
  <div>
    <div class="card">
      <div class="card-body">

        {{-- 아이콘 + 이름 --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;">
            <i class="fa-solid fa-user"></i>
          </div>
          <div style="min-width:0;">
            <div style="font-size:18px;font-weight:700;">{{ $patient->name }}</div>
            <div style="font-size:12px;color:var(--text-muted);">
              환자 #{{ $patient->id }} · 등록 {{ $patient->created_at->format('Y-m-d') }}
              @if($patient->birth_date) · {{ $patient->birth_date->format('Y-m-d') }} 만 {{ $patient->age }}세 @endif
              @if($patient->gender) · {{ $patient->gender === 'male' ? '남' : '여' }} @endif
            </div>
          </div>

          {{-- 이 사람에게 할 일은 이름 옆에 둔다 — 고치기, 통화 기록 보기, 지우기 순이다.
               지우기는 되돌릴 수 없어 한 칸 떼어 놓는다. --}}
          <div style="margin-left:auto;display:flex;gap:6px;flex-shrink:0;">
            <button class="btn btn-outline btn-sm" id="btn-edit" onclick="toggleEdit(true)">
              <i class="fa-solid fa-pen"></i> 수정
            </button>
            <button class="btn btn-outline btn-sm" onclick="openCounselTab()">
              <i class="bx bx-conversation"></i> 상담내역
            </button>
            <button class="btn btn-danger btn-sm" onclick="deletePatient()" title="삭제"
                    style="margin-left:6px;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>

        {{-- 조회 패널 --}}
        <div class="view-panel" id="view-panel">
          <div class="info-row">
            <span class="info-label">주민번호</span>
            <span class="info-value">{{ $patient->masked_resident_no ?? '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">휴대폰</span>
            <span class="info-value">{{ $patient->mobile ?? '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">일반 전화</span>
            <span class="info-value">{{ $patient->phone ?? '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">주소</span>
            <span class="info-value">{{ $patient->address ?? '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">건강보험번호</span>
            <span class="info-value">{{ $patient->health_insurance_no ?? '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">급여 적용</span>
            <span class="info-value">
              @if($patient->is_nhis_eligible)
                <span style="color:var(--success);font-weight:700;"><i class="fa-solid fa-check-circle"></i> 급여 {{ $patient->nhis_coverage_rate }}%</span>
              @else
                <span style="color:var(--text-muted);">비급여</span>
              @endif
            </span>
          </div>
          @if($patient->note)
          <div class="info-row">
            <span class="info-label">메모</span>
            <span class="info-value" style="white-space:pre-wrap;">{{ $patient->note }}</span>
          </div>
          @endif
        </div>

        {{-- 편집 폼 --}}
        <div class="edit-form" id="edit-form">
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">환자명 <span style="color:red;">*</span></label>
              <input type="text" class="form-control" id="e-name" value="{{ $patient->name }}" />
            </div>
            <div class="form-group">
              <label class="form-label">주민등록번호</label>
              {{-- 마스킹만 보여준다. 그대로 두면 기존 값이 유지되고, 바꿀 때만 전체를 새로 입력한다(P0-1) --}}
              <input type="text" class="form-control" id="e-resident"
                     value="{{ $patient->masked_resident_no }}"
                     data-masked="{{ $patient->masked_resident_no }}"
                     placeholder="XXXXXX-XXXXXXX" />
              <small style="font-size:11px;color:var(--text-muted);">변경할 때만 전체 번호를 새로 입력하세요. 그대로 두면 기존 값이 유지됩니다.</small>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">생년월일</label>
              <input type="date" class="form-control" id="e-birth" value="{{ $patient->birth_date?->format('Y-m-d') }}" />
            </div>
            <div class="form-group">
              <label class="form-label">성별</label>
              <select class="form-control" id="e-gender">
                <option value="">선택</option>
                <option value="male"   @selected($patient->gender==='male')>남</option>
                <option value="female" @selected($patient->gender==='female')>여</option>
              </select>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">휴대폰</label>
              <input type="text" class="form-control" id="e-mobile" value="{{ $patient->mobile }}" placeholder="010-XXXX-XXXX" data-phone />
            </div>
            <div class="form-group">
              <label class="form-label">일반 전화</label>
              <input type="text" class="form-control" id="e-phone" value="{{ $patient->phone }}" placeholder="02-XXXX-XXXX" data-phone />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">주소</label>
            <input type="text" class="form-control" id="e-address" value="{{ $patient->address }}" />
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">건강보험번호</label>
              <input type="text" class="form-control" id="e-insurance-no" value="{{ $patient->health_insurance_no }}" />
            </div>
            <div class="form-group">
              <label class="form-label">급여 적용</label>
              <select class="form-control" id="e-nhis">
                <option value="0" @selected(!$patient->is_nhis_eligible)>비급여</option>
                <option value="1" @selected($patient->is_nhis_eligible)>급여 대상</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">급여율 (%)</label>
            <input type="number" class="form-control" id="e-coverage" value="{{ $patient->nhis_coverage_rate }}" min="0" max="100" />
          </div>
          <div class="form-group">
            <label class="form-label">메모</label>
            <textarea class="form-control" id="e-note" rows="3">{{ $patient->note }}</textarea>
          </div>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <button class="btn btn-outline flex-1" onclick="toggleEdit(false)">취소</button>
            <button class="btn btn-warning flex-1" id="btn-save" onclick="savePatient()">
              <i class="fa-solid fa-floppy-disk"></i> 저장
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- 우측: 처방 이력 --}}
  <div>
    <div class="card">
      <div class="card-body">
        <div class="tab-bar">
          <button class="tab-btn active" onclick="switchTab(this,'tab-rx')">
            <i class="fa-solid fa-file-medical"></i> 처방 이력
            <span style="background:var(--primary-light);color:var(--primary);border-radius:12px;padding:1px 7px;font-size:11px;margin-left:4px;">{{ $patient->prescriptions->count() }}</span>
          </button>
        </div>

        <div class="tab-pane active" id="tab-rx">
          @forelse($patient->prescriptions as $rx)
          <div class="rx-row" onclick="ceOpenTab('{{ route('prescriptions.show', $rx) }}', '주문 - {{ $rx->rx_number }}', 'file-edit-02')">
            <div style="width:36px;height:36px;border-radius:var(--radius);background:var(--bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-file-waveform" style="color:var(--primary);font-size:14px;"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:600;">{{ $rx->rx_number }}</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                {{ $rx->hospital_name ?? '-' }} · {{ $rx->created_at->format('Y-m-d') }}
              </div>
            </div>
            <span class="rx-status badge badge-{{ $rx->status_badge }}">{{ $rx->status_label }}</span>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);font-size:11px;"></i>
          </div>
          @empty
          <div style="text-align:center;padding:48px 20px;color:var(--text-muted);">
            <i class="fa-solid fa-file-medical" style="font-size:28px;opacity:.3;display:block;margin-bottom:10px;"></i>
            처방 이력이 없습니다.
          </div>
          @endforelse
        </div>

      </div>
    </div>
  </div>

</div>

@endsection

@push('scripts')

<script>
  /* 상담내역은 거래처 관리 화면의 탭에서 본다. 이 화면이 그 안에 액자로 들어가 있으면
     바깥에 열라고 알리고, 혼자 열려 있으면 목록 화면으로 옮겨 그 탭을 연다. */
  function openCounselTab() {
    const msg = { source: 'ce-patient', action: 'counsel',
                  id: @json($patient->id), name: @json($patient->name) };

    if (window.parent && window.parent !== window) {
      try {
        window.parent.postMessage(msg, window.location.origin);
        return;
      } catch (e) { /* 다른 곳에서 온 창이면 아래로 간다 */ }
    }

    location.href = @json(route('patients.index')) + '?counsel=' + @json($patient->id);
  }
</script>
<script>
  function switchTab(btn, id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(id).classList.add('active');
  }

  function toggleEdit(on) {
    document.getElementById('view-panel').classList.toggle('hidden', on);
    document.getElementById('edit-form').classList.toggle('active', on);
    document.getElementById('btn-edit').style.display = on ? 'none' : '';
  }

  async function savePatient() {
    const name = document.getElementById('e-name').value.trim();
    if (!name) { showToast('환자명은 필수입니다.', 'warning'); return; }

    const btn = document.getElementById('btn-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      // 마스킹 그대로면 '변경 없음' — 보낸 값이 없으면 서버가 기존 값을 건드리지 않는다
      resident_no:         (function (el) {
                             const v = el.value.trim();
                             return (v === '' || v === el.dataset.masked) ? undefined : v;
                           })(document.getElementById('e-resident')),
      birth_date:          document.getElementById('e-birth').value               || null,
      gender:              document.getElementById('e-gender').value              || null,
      mobile:              document.getElementById('e-mobile').value.trim()       || null,
      phone:               document.getElementById('e-phone').value.trim()        || null,
      address:             document.getElementById('e-address').value.trim()      || null,
      health_insurance_no: document.getElementById('e-insurance-no').value.trim()|| null,
      is_nhis_eligible:    document.getElementById('e-nhis').value === '1',
      nhis_coverage_rate:  parseInt(document.getElementById('e-coverage').value)  || 0,
      note:                document.getElementById('e-note').value.trim()         || null,
      _method:             'PUT',
    };

    const res = await apiRequest(`/patients/{{ $patient->id }}`, 'POST', payload);

    if (res.success) {
      BtnState.success(btn, '저장 완료');
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 700);
    } else {
      BtnState.error(btn, '저장 실패');
      showToast(res.message || '저장 실패', 'danger');
    }
  }

  async function deletePatient() {
    if (!await ceConfirm('"{{ $patient->name }}" 환자를 삭제하시겠습니까?', { tone: 'danger', confirmText: '삭제' })) return;
    const res = await apiRequest(`/patients/{{ $patient->id }}`, 'DELETE');
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.href = `${BASE_URL}/patients`, 800);
    }
  }
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#view-panel', title: '환자 기본 정보', body: '환자의 이름, 연락처, 주민번호, 보험 정보를 확인합니다.' },
  { selector: '#btn-edit', title: '정보 편집', body: '클릭하면 환자 정보를 직접 수정할 수 있는 입력 폼이 나타납니다.' },
  { selector: '.card', title: '처방·주문 이력', body: '이 환자의 처방전 업로드 이력과 주문 내역을 확인합니다. 처방번호 클릭 시 상세 화면으로 이동합니다.' },
];
</script>
@endpush
