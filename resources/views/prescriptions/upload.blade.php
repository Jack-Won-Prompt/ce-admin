{{-- resources/views/prescriptions/upload.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 업로드')
@section('page-title', '처방전 업로드')
@section('breadcrumb')
홈 / 처방전 / 업로드 &nbsp;·&nbsp; {{ now()->format('Y-m-d') }}
@endsection

{{-- 검수 대기·처방전 목록은 시안(128:3167)대로 업로드 카드 헤더로 옮겼다.
     상단 헤더의 옛 건수는 화면에 뿌리는 최근 5건 안에서만 세어, 실제보다 적게 나왔다. --}}

@push('styles')
<style>
  /* ── 단계 표시 (Figma 128:724) ──
     번호 원과 좌우 선 대신, 아이콘·제목·부제를 세로로 쌓고 점선으로 잇는다.
     흰 카드 radius 12, 테두리·그림자 없음. 지난 단계와 앞으로 올 단계는 회색. */
  .steps-bar { display:flex; justify-content:center; align-items:center; gap:16px;
               background:var(--gray-0); border-radius:12px; padding:16px; }
  .step { display:flex; flex-direction:column; align-items:center; gap:8px; }
  /* 제목과 부제는 붙여 놓는다 — 아이콘과의 간격(8)만 벌린다 (Figma 128:731) */
  .step-text { display:flex; flex-direction:column; align-items:center; }
  .step-icon  { width:24px; height:24px; display:flex; align-items:center; justify-content:center;
                font-size:18px; color:var(--gray-400); }
  .step-label { font-size:13px; font-weight:700; line-height:1.6; color:var(--gray-400); }
  .step-sub   { font-size:11px; font-weight:500; line-height:1.6; color:var(--gray-400); }
  .step.active .step-icon, .step.active .step-label, .step.active .step-sub { color:var(--primary); }
  /* 단계 사이 연결자 — 점 4px · 선 1px, 폭 100 (Figma 128:734) */
  .step-link { display:flex; align-items:center; justify-content:center; gap:2px; width:100px; height:4px; flex-shrink:0; }
  .step-link i { width:4px; height:4px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
  .step-link u { flex:1; height:1px; background:var(--gray-300); }
  .step-link.done i { background:var(--primary); }
  .step-link.done u { background:var(--primary); }

  /* ── Layout (Figma 128:768) — 3 : 1, gap 12 ── */
  .upload-layout { display:grid; grid-template-columns:minmax(0,3fr) minmax(0,1fr); gap:12px; align-items:start; }
  @media(max-width:960px){ .upload-layout { grid-template-columns:1fr; } }

  /* ── 카드 (Figma 128:769 / 128:827) — 흰 카드 radius 12, 테두리·그림자 없음 ── */
  .up-card { display:flex; flex-direction:column; background:var(--gray-0); border-radius:12px; }
  .up-card-head { display:flex; align-items:center; gap:24px; height:44px; padding:0 16px;
                  border-bottom:1px solid var(--gray-200); flex-shrink:0; }
  .up-card-title { font-size:13px; font-weight:700; line-height:1.6; color:var(--gray-1000); }
  .up-card-body { padding:16px; display:flex; flex-direction:column; gap:24px; }
  /* 헤더 우측 작은 버튼 — 높이 28, radius 8 */
  .up-head-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px;
                 height:28px; padding:0 12px; border-radius:8px;
                 background:var(--gray-0); border:1px solid var(--gray-200);
                 font-size:12px; font-weight:500; color:var(--gray-1000);
                 text-decoration:none; cursor:pointer; white-space:nowrap; }
  .up-head-btn:hover { background:var(--gray-50); }
  /* 검수 대기 알림 */
  .up-head-alert { display:inline-flex; align-items:center; gap:4px;
                   font-size:12px; font-weight:500; color:var(--alert-500, #D73D3F); white-space:nowrap; }
  /* 본문 안 구획 제목 */
  .up-sec { display:flex; flex-direction:column; gap:16px; }
  .up-sec-head { display:flex; align-items:center; justify-content:space-between; }
  .up-sec-title { font-size:14px; font-weight:700; line-height:1.6; color:var(--gray-800); }
  /* 하단 버튼 — 초기화 80×36, 등록은 남은 폭 (Figma 128:822) */
  .up-foot { display:flex; align-items:center; gap:8px; }
  .up-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px;
            height:36px; padding:0 16px; border-radius:8px;
            font-size:14px; font-weight:500; cursor:pointer; white-space:nowrap; }
  .up-btn-ghost { width:80px; flex-shrink:0; background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000); }
  .up-btn-ghost:hover { background:var(--gray-50); }
  .up-btn-primary { flex:1; background:var(--primary); border:1px solid var(--primary); color:var(--gray-0); }
  .up-btn-primary:disabled { opacity:.45; cursor:not-allowed; }

  /* ── Drop Zone ── */
  .drop-zone { border:2px dashed var(--border); border-radius:var(--radius-lg); padding:48px 24px; text-align:center; cursor:pointer; transition:var(--transition); background:var(--bg); position:relative; }
  .drop-zone:hover, .drop-zone.dragover { border-color:var(--primary); background:var(--primary-light); }
  .drop-zone.has-file { border-color:var(--success); background:var(--success-light); }
  .drop-zone.uploading { border-color:var(--primary); background:var(--primary-light); cursor:not-allowed; }
  .drop-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
  .drop-icon { font-size:48px; margin-bottom:14px; display:block; color:var(--primary); }
  .drop-zone.has-file .drop-icon { color:var(--success); }
  .drop-zone.uploading .drop-icon { color:var(--primary); animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  .drop-title { font-size:15px; font-weight:700; color:var(--text-primary); }
  .drop-desc  { font-size:12px; color:var(--text-muted); margin-top:6px; }
  .fmt-tags   { display:inline-flex; gap:5px; margin-top:12px; flex-wrap:wrap; justify-content:center; }
  .fmt-tag    { padding:2px 9px; border-radius:20px; background:var(--bg); border:1px solid var(--border); font-size:10.5px; color:var(--text-secondary); font-weight:600; }

  /* ── File list ── */
  .file-list { margin-top:12px; display:flex; flex-direction:column; gap:6px; }
  .file-item { display:flex; align-items:center; gap:10px; padding:8px 12px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); }
  .file-item-name { flex:1; font-size:12px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .file-item-size { font-size:11px; color:var(--text-muted); flex-shrink:0; }
  .file-item-remove { background:none; border:none; cursor:pointer; color:var(--danger); padding:2px 4px; line-height:1; }

  /* ── Doc type selector ── */
  .doc-type-sel { font-size:11px; font-weight:600; border:1px solid var(--border); border-radius:var(--radius); padding:3px 8px; background:var(--bg-card); color:var(--text-primary); cursor:pointer; flex-shrink:0; min-width:90px; }
  .doc-type-sel.type-prescription { border-color:var(--primary); background:var(--primary-light); color:var(--primary); }
  .doc-type-sel.type-id_card      { border-color:var(--info);    background:#e0f7fa;               color:#0284c7; }
  .doc-type-sel.type-delegation   { border-color:var(--warning); background:#fef3c7;               color:#b45309; }
  .doc-type-sel.type-other        { border-color:var(--border);  background:var(--bg);             color:var(--text-secondary); }

  /* ── Patient search ── */
  .patient-search-wrap { position:relative; }
  .patient-search-drop { position:absolute; top:calc(100%+4px); left:0; right:0; background:#fff; border:1px solid var(--primary); border-radius:var(--radius); box-shadow:0 6px 20px rgba(0,0,0,.13); z-index:500; max-height:240px; overflow-y:auto; display:none; }
  .patient-search-drop.open { display:block; }
  .ps-item { padding:9px 12px; cursor:pointer; border-bottom:1px solid var(--border); font-size:12px; display:flex; align-items:center; gap:8px; transition:background .1s; }
  .ps-item:last-child { border-bottom:none; }
  .ps-item:hover, .ps-item.active { background:var(--primary-light); }
  .ps-item-name { font-weight:700; }
  .ps-item-meta { font-size:11px; color:var(--text-muted); }
  .ps-no-result { padding:12px; font-size:12px; color:var(--text-muted); text-align:center; }

  /* ── 최근 업로드 이력 항목 (Figma 128:834) ── */
  .history-list { display:flex; flex-direction:column; gap:8px; }
  .history-item { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px;
                  background:var(--gray-0); border:1px solid var(--gray-200); cursor:pointer; transition:var(--transition); }
  .history-item:hover { background:var(--gray-50); border-color:var(--primary); }
  .history-thumb { width:28px; height:28px; display:flex; align-items:center; justify-content:center;
                   font-size:16px; flex-shrink:0; }
  .history-body  { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; }
  .history-name  { font-size:12px; font-weight:500; color:var(--gray-1000); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .history-meta  { font-size:11px; font-weight:500; color:var(--gray-500); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .history-badge { padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; white-space:nowrap; flex-shrink:0; }

  /* mobile upload */
  .mobile-upload-card { display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--primary-light); border-radius:12px; margin-bottom:12px; }

  /* ── Progress overlay ── */
  .progress-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.55); align-items:center; justify-content:center; }
  .progress-overlay.active { display:flex; }
  .progress-box { background:#fff; border-radius:var(--radius-lg); padding:36px 40px; text-align:center; min-width:300px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .progress-spinner { width:56px; height:56px; border:5px solid var(--primary-light); border-top-color:var(--primary); border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 18px; }
  .progress-title { font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:6px; }
  .progress-sub   { font-size:12px; color:var(--text-muted); }
</style>
@endpush

@section('content')

{{-- ── 단계 표시 (Figma 128:724) ── --}}
<div class="steps-bar">
  <div class="step active" id="step1">
    <div class="step-icon"><i class="fa-solid fa-file-arrow-up"></i></div>
    <div class="step-text">
      <div class="step-label">파일 선택</div>
      <div class="step-sub">처방전 및 첨부 문서</div>
    </div>
  </div>
  <div class="step-link done"><i></i><u></u><i></i></div>
  <div class="step" id="step2">
    <div class="step-icon"><i class="fa-solid fa-expand"></i></div>
    <div class="step-text">
      <div class="step-label">OCR 분석</div>
      <div class="step-sub">자동 텍스트 추출</div>
    </div>
  </div>
  <div class="step-link"><i></i><u></u><i></i></div>
  <div class="step" id="step3">
    <div class="step-icon"><i class="fa-solid fa-file-prescription"></i></div>
    <div class="step-text">
      <div class="step-label">처방자료 확인</div>
      <div class="step-sub">내용 검토 및 주문 연결</div>
    </div>
  </div>
</div>

{{-- ── 업로드 레이아웃 ── --}}
<div class="upload-layout">
  <div>
    {{-- 모바일 대기 알림 --}}
    @if($mobilePending->isNotEmpty())
    <div class="mobile-upload-card">
      <div style="font-size:26px;color:var(--info);"><i class="fa-solid fa-mobile-screen-button"></i></div>
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:700;">모바일 업로드 대기 {{ $mobilePending->count() }}건</div>
        <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">최근: {{ $mobilePending->first()?->patient_name_ocr ?? '환자' }} — {{ $mobilePending->first()?->created_at->format('H:i') }}</div>
      </div>
      <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> 대기</span>
    </div>
    @endif

    <div class="up-card">
      <div class="up-card-head">
        <span class="up-card-title" style="flex:1;">처방자료 업로드</span>
        @if($reviewPending > 0)
        <span class="up-head-alert">
          <i class="fa-solid fa-triangle-exclamation" style="font-size:12px;"></i>검수 대기 {{ $reviewPending }}건
        </span>
        @endif
        <a href="{{ route('prescriptions.index') }}" class="up-head-btn">처방전 목록</a>
      </div>
      <div class="up-card-body">
        <form id="uploadForm" method="POST" action="{{ route('prescriptions.store') }}" enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap:24px;">
          @csrf
          <input type="hidden" name="assigned_user_id" id="h_assigned_user_id">
          <input type="hidden" name="admin_note"       id="h_admin_note">
          <input type="hidden" name="patient_id"       id="h_patient_id">

          {{-- ── 파일 업로드 (Figma 128:3175) ── --}}
          <div class="up-sec">
            <div class="up-sec-head">
              <span class="up-sec-title">파일 업로드</span>
              <span style="font-size:12px;font-weight:500;color:var(--gray-500);">최대 50MB · JPG / PNG / PDF / HEIC · 최대 10개</span>
            </div>
            <div>{{-- 기존 입력 흐름 그대로 --}}

          {{-- ── 환자 선택 ── --}}
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label" style="font-weight:700;">
              <i class="fa-solid fa-user-check" style="color:var(--primary);margin-right:5px;"></i>환자 선택 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(선택 시 OCR 자동 연결 건너뜀)</span>
            </label>
            <div class="patient-search-wrap">
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="patientSearchInput" class="form-control"
                       placeholder="이름 또는 연락처로 검색..."
                       autocomplete="off"
                       style="flex:1;" />
                <div id="patientSelectedBadge" style="display:none;align-items:center;gap:6px;padding:4px 10px;background:var(--primary-light);border:1px solid var(--primary);border-radius:var(--radius);font-size:12px;font-weight:700;color:var(--primary);white-space:nowrap;">
                  <i class="fa-solid fa-user"></i>
                  <span id="patientSelectedName"></span>
                  <button type="button" onclick="clearPatient()" style="background:none;border:none;cursor:pointer;color:var(--danger);padding:0;font-size:14px;line-height:1;" title="선택 해제">×</button>
                </div>
              </div>
              <div class="patient-search-drop" id="patientDrop"></div>
            </div>
          </div>

          {{-- Drop Zone --}}
          <div class="drop-zone" id="dropArea">
            <input type="file" id="fileInput" name="prescription_images[]"
                   accept=".jpg,.jpeg,.png,.pdf,.heic" multiple />
            <i class="fa-regular fa-file-image drop-icon" id="dropIcon"></i>
            <div class="drop-title" id="dropTitle">파일을 여기에 끌어다 놓거나 클릭하여 선택</div>
            <div class="drop-desc" id="dropDesc">처방전 · 주민등록증 · 위임장 등 — 한 번에 최대 10개</div>
            <div class="fmt-tags">
              <span class="fmt-tag">JPG</span><span class="fmt-tag">PNG</span>
              <span class="fmt-tag">PDF</span><span class="fmt-tag">HEIC</span>
            </div>
          </div>

          {{-- 파일 선택 중 프로그레스 바 --}}
          <div id="fileProgressWrap" style="display:none;margin-top:14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:5px;">
              <span id="fileProgressLabel" style="font-weight:600;display:flex;align-items:center;gap:5px;">
                <i class="fa-solid fa-spinner" style="animation:spin .7s linear infinite;"></i> 파일 확인 중...
              </span>
              <span id="fileProgressPct" style="font-weight:700;color:var(--primary);">0%</span>
            </div>
            <div style="height:7px;background:var(--border);border-radius:4px;overflow:hidden;">
              <div id="fileProgressBar"
                   style="height:100%;width:0%;background:linear-gradient(90deg,var(--primary),#818cf8);border-radius:4px;transition:width .45s cubic-bezier(.4,0,.2,1);"></div>
            </div>
          </div>

          {{-- 파일 목록 --}}
          <div class="file-list" id="fileList"></div>

          {{-- 파일 유형 안내 --}}
          <div id="typeGuide" style="display:none;margin-top:10px;padding:10px 13px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);font-size:12px;color:var(--text-secondary);">
            <i class="fa-solid fa-circle-info" style="color:var(--info);"></i>
            각 파일의 <strong>유형</strong>을 선택해 주세요.
            <span style="margin-left:6px;"><span style="color:var(--primary);font-weight:700;">처방전</span>은 OCR 분석,
            <span style="color:#0284c7;font-weight:700;">주민등록증·위임장</span> 등은 이미지 그대로 첨부 문서로 저장됩니다.</span>
          </div>

            </div>{{-- /파일 업로드 흐름 --}}
          </div>{{-- /up-sec 파일 업로드 --}}

          {{-- ── 처방전 설정 (Figma 128:3341) — 시안에서 이 카드 안으로 들어왔다 ── --}}
          <div class="up-sec">
            <span class="up-sec-title">처방전 설정</span>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
              <div class="form-group mb-0" style="flex:1; min-width:200px;">
                <label class="form-label">담당자</label>
                <select class="form-control form-select" id="sideAssignedUser">
                  <option value="">담당자 선택</option>
                  @foreach($managers as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group mb-0" style="flex:2; min-width:240px;">
                <label class="form-label">메모</label>
                <textarea class="form-control" id="sideAdminNote" rows="2" placeholder="처방전 관련 메모..."></textarea>
              </div>
            </div>
          </div>

          {{-- ── 초기화 / 등록 (Figma 128:822) ── --}}
          <div class="up-foot">
            <button type="button" class="up-btn up-btn-ghost" onclick="resetFiles()">초기화</button>
            <button type="submit" class="up-btn up-btn-primary" id="submitBtn" disabled>등록</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Right: 최근 업로드 이력 --}}
  <div class="up-card">
    <div class="up-card-head" style="padding:8px 16px; justify-content:space-between; gap:12px;">
      <span class="up-card-title">최근 업로드 이력</span>
      <a href="{{ route('prescriptions.index') }}" class="up-head-btn">전체</a>
    </div>
    <div style="padding:16px;">
      <div class="history-list">
        @forelse($prescriptions as $rx)
        <div class="history-item" onclick="ceOpenTab('{{ route('prescriptions.show', $rx) }}', '처방전 관리 - {{ $rx->rx_number }}', 'file-edit-02')">
          <div class="history-thumb">
            @if(strtolower(pathinfo($rx->image_original_name, PATHINFO_EXTENSION)) === 'pdf')
              <i class="fa-regular fa-file-pdf" style="color:var(--danger);"></i>
            @else
              <i class="fa-regular fa-file-image" style="color:var(--primary);"></i>
            @endif
          </div>
          <div class="history-body">
            <span class="history-name">{{ $rx->image_original_name ?? $rx->rx_number }}</span>
            <span class="history-meta">{{ $rx->rx_number }} · {{ $rx->patient_name_ocr ?? '-' }} · {{ $rx->created_at->format('H:i') }}</span>
          </div>
          <span class="history-badge badge-{{ $rx->status_badge }}">{{ $rx->status_label }}</span>
        </div>
        @empty
        <div style="text-align:center;color:var(--gray-500);font-size:12px;padding:12px;">업로드 이력이 없습니다.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- OCR 처리 중 전체화면 오버레이 --}}
<div class="progress-overlay" id="progressOverlay">
  <div class="progress-box">
    <div class="progress-spinner"></div>
    <div class="progress-title">OCR 분석 중...</div>
    <div class="progress-sub" id="progressSub">처방전 텍스트를 인식하고 있습니다</div>
    <div style="margin-top:14px;font-size:11px;color:var(--text-muted);">10~30초 소요됩니다. 창을 닫지 마세요.</div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ── 환자 데이터 ──────────────────────────────────────────
const PATIENTS = @json($patientsJson);

let selectedPatientId = null;

const patientInput = document.getElementById('patientSearchInput');
const patientDrop  = document.getElementById('patientDrop');
const patientBadge = document.getElementById('patientSelectedBadge');

patientInput.addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  if (!q) { patientDrop.classList.remove('open'); patientDrop.innerHTML = ''; return; }

  const results = PATIENTS.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.mobile && p.mobile.replace(/-/g,'').includes(q.replace(/-/g,'')))
  ).slice(0, 10);

  if (!results.length) {
    patientDrop.innerHTML = '<div class="ps-no-result">검색 결과 없음</div>';
  } else {
    patientDrop.innerHTML = results.map(p =>
      `<div class="ps-item" onclick="selectPatient(${p.id}, '${escHtml(p.name)}')">
        <i class="fa-solid fa-user" style="color:var(--primary);font-size:13px;"></i>
        <div>
          <div class="ps-item-name">${escHtml(p.name)}</div>
          <div class="ps-item-meta">${escHtml(p.mobile || '')}${p.rn ? ' · ' + escHtml(p.rn) : ''}</div>
        </div>
      </div>`
    ).join('');
  }
  patientDrop.classList.add('open');
});

document.addEventListener('click', e => {
  if (!patientInput.contains(e.target) && !patientDrop.contains(e.target)) {
    patientDrop.classList.remove('open');
  }
});

function selectPatient(id, name) {
  selectedPatientId = id;
  document.getElementById('h_patient_id').value = id;
  document.getElementById('patientSelectedName').textContent = name;
  patientBadge.style.display = 'flex';
  patientInput.value = '';
  patientInput.style.display = 'none';
  patientDrop.classList.remove('open');
}

function clearPatient() {
  selectedPatientId = null;
  document.getElementById('h_patient_id').value = '';
  patientBadge.style.display = 'none';
  patientInput.style.display = '';
  patientInput.value = '';
}

// ── 파일 업로드 ─────────────────────────────────────────
const dropArea  = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');
const submitBtn = document.getElementById('submitBtn');
const form      = document.getElementById('uploadForm');

const DOC_TYPES = {
  prescription: { label: '처방전',    cls: 'type-prescription', icon: 'fa-file-medical' },
  id_card:      { label: '주민등록증', cls: 'type-id_card',      icon: 'fa-id-card' },
  delegation:   { label: '위임장',    cls: 'type-delegation',   icon: 'fa-file-signature' },
  other:        { label: '기타',      cls: 'type-other',        icon: 'fa-file' },
};

let selectedFiles = []; // [{file, docType}]

['dragenter','dragover'].forEach(e =>
  dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.classList.add('dragover'); }));
['dragleave','drop'].forEach(e =>
  dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.classList.remove('dragover'); }));
dropArea.addEventListener('drop', ev => addFiles(ev.dataTransfer.files));
fileInput.addEventListener('change', () => { addFiles(fileInput.files); fileInput.value = ''; });

function addFiles(fileObjs) {
  const allowed = ['jpg','jpeg','png','pdf','heic'];
  const added = [];
  Array.from(fileObjs).forEach(f => {
    if (selectedFiles.length + added.length >= 10) { showToast('최대 10개까지 선택할 수 있습니다.', 'warning'); return; }
    const ext = f.name.split('.').pop().toLowerCase();
    if (!allowed.includes(ext))  { showToast(f.name + ' — 지원하지 않는 형식', 'warning'); return; }
    if (f.size > 51200 * 1024)   { showToast(f.name + ' — 50MB 초과', 'warning'); return; }
    if (selectedFiles.find(s => s.file.name === f.name && s.file.size === f.size)) return;
    added.push({ file: f, docType: 'prescription' });
  });
  if (!added.length) return;
  showFileProgress(added.map(a => a.file), () => {
    added.forEach(a => selectedFiles.push(a));
    renderFileList();
  });
}

function showFileProgress(files, onDone) {
  const wrap  = document.getElementById('fileProgressWrap');
  const bar   = document.getElementById('fileProgressBar');
  const pctEl = document.getElementById('fileProgressPct');
  const label = document.getElementById('fileProgressLabel');

  label.innerHTML = `<i class="fa-solid fa-spinner" style="animation:spin .7s linear infinite;"></i> ${files.length > 1 ? files.length+'개 파일 확인 중...' : '파일 확인 중...'}`;
  bar.style.transition = 'none';
  bar.style.width = '0%';
  pctEl.textContent = '0%';
  wrap.style.display = 'block';

  requestAnimationFrame(() => requestAnimationFrame(() => {
    bar.style.transition = 'width .48s cubic-bezier(.4,0,.2,1)';
    bar.style.width = '100%';
    let p = 0;
    const step = setInterval(() => {
      p = Math.min(p + 5, 100);
      pctEl.textContent = p + '%';
      if (p >= 100) clearInterval(step);
    }, 24);
    setTimeout(() => {
      wrap.style.display = 'none';
      bar.style.width = '0%';
      pctEl.textContent = '0%';
      if (onDone) onDone();
    }, 520);
  }));
}

function changeDocType(idx, val) {
  selectedFiles[idx].docType = val;
  const sel = document.querySelector(`[data-file-idx="${idx}"] .doc-type-sel`);
  if (sel) {
    sel.className = 'doc-type-sel type-' + val;
    sel.value = val;
  }
}

function removeFile(idx) {
  selectedFiles.splice(idx, 1);
  renderFileList();
}

function renderFileList() {
  const count    = selectedFiles.length;
  const fileList = document.getElementById('fileList');
  const guide    = document.getElementById('typeGuide');

  if (count === 0) {
    dropArea.classList.remove('has-file');
    document.getElementById('dropIcon').className   = 'fa-regular fa-file-image drop-icon';
    document.getElementById('dropTitle').textContent = '파일을 여기에 끌어다 놓거나 클릭하여 선택';
    document.getElementById('dropDesc').textContent  = '처방전 · 주민등록증 · 위임장 등 — 한 번에 최대 10개';
    fileList.innerHTML = '';
    guide.style.display = 'none';
    submitBtn.disabled = true;
    return;
  }

  dropArea.classList.add('has-file');
  document.getElementById('dropIcon').className   = 'fa-solid fa-circle-check drop-icon';
  document.getElementById('dropTitle').textContent = count + '개 파일 선택됨';
  document.getElementById('dropDesc').textContent  = '추가하려면 다시 드래그하거나 클릭 · 최대 ' + (10 - count) + '개 더 가능';
  guide.style.display = 'block';
  submitBtn.disabled = false;

  fileList.innerHTML = selectedFiles.map((item, i) => {
    const f    = item.file;
    const size = f.size > 1024*1024 ? (f.size/1024/1024).toFixed(1)+'MB' : (f.size/1024).toFixed(0)+'KB';
    const ext  = f.name.split('.').pop().toLowerCase();
    const icon = ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
    const iclr = ext === 'pdf' ? 'var(--danger)' : 'var(--primary)';
    const typeOpts = Object.entries(DOC_TYPES).map(([v, t]) =>
      `<option value="${v}"${item.docType === v ? ' selected' : ''}>${t.label}</option>`
    ).join('');
    return `<div class="file-item" data-file-idx="${i}">
      <i class="fa-regular ${icon}" style="color:${iclr};font-size:18px;flex-shrink:0;"></i>
      <span class="file-item-name" title="${escHtml(f.name)}">${escHtml(f.name)}</span>
      <span class="file-item-size">${size}</span>
      <select class="doc-type-sel type-${item.docType}" onchange="changeDocType(${i}, this.value)">${typeOpts}</select>
      <button type="button" class="file-item-remove" onclick="removeFile(${i})" title="제거">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>`;
  }).join('');
}

function resetFiles() {
  selectedFiles = [];
  fileInput.value = '';
  renderFileList();
  setStep(1, 'active'); setStep(2); setStep(3);
}

// ── 폼 제출 ─────────────────────────────────────────────
form.addEventListener('submit', function (e) {
  if (selectedFiles.length === 0) { e.preventDefault(); return; }

  const hasPrescription = selectedFiles.some(f => f.docType === 'prescription');
  if (!hasPrescription) {
    e.preventDefault();
    showToast('처방전 파일을 최소 1개 이상 포함해야 합니다.', 'warning');
    return;
  }

  document.getElementById('h_assigned_user_id').value = document.getElementById('sideAssignedUser').value;
  document.getElementById('h_admin_note').value        = document.getElementById('sideAdminNote').value;

  // 파일을 prescription_images[] 에 담고, file_doc_types[] hidden input 생성
  const dt = new DataTransfer();
  selectedFiles.forEach((item, i) => {
    dt.items.add(item.file);

    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'file_doc_types[]';
    hidden.value = item.docType;
    form.appendChild(hidden);
  });
  fileInput.files = dt.files;

  const rxCount = selectedFiles.filter(f => f.docType === 'prescription').length;
  const attCount = selectedFiles.length - rxCount;
  let sub = rxCount + '개 처방전';
  if (attCount > 0) sub += ` + ${attCount}개 첨부 문서`;
  sub += ' OCR 분석 중...';
  document.getElementById('progressSub').textContent = sub;
  document.getElementById('progressOverlay').classList.add('active');

  setStep(1, 'done'); setStep(2, 'active');
});

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function setStep(num, state) {
  const el = document.getElementById('step' + num);
  if (!el) return;
  el.className = 'step' + (state ? ' ' + state : '');
}
</script>

<script>
window.HELP_TOUR_STEPS = [
  { selector: '.steps-bar', title: '업로드 진행 단계', body: '파일 선택 → 등록 버튼 클릭 → OCR 분석 → 처방전 확인 및 주문 연결 순서로 진행됩니다.' },
  { selector: '#patientSearchInput', title: '환자 선택', body: '이름 또는 연락처로 검색하여 기존 환자를 선택하면 OCR 결과와 자동 연결됩니다.' },
  { selector: '#dropArea', title: '파일 선택 영역', body: '처방전 외에 주민등록증·위임장 등도 함께 업로드할 수 있습니다. 각 파일의 유형을 선택하세요.' },
  { selector: '#submitBtn', title: '등록 버튼', body: '버튼 클릭 시 OCR 분석이 시작되며, 완료 후 처방전 확인 화면으로 이동합니다.' },
];
</script>
@endpush
