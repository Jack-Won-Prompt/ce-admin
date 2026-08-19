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
  /* ── Layout (Figma 128:768) — 3 : 1, gap 12 ── */
  .upload-layout { display:grid; grid-template-columns:minmax(0,3fr) minmax(0,1fr); gap:12px; align-items:start; }
  @media(max-width:960px){ .upload-layout { grid-template-columns:1fr; } }

  /* ── 카드 (Figma 128:769 / 128:827) — 흰 카드 radius 12, 테두리·그림자 없음 ── */
  .up-card { display:flex; flex-direction:column; background:var(--gray-0); border-radius:12px; }
  .up-card-head { display:flex; align-items:center; gap:24px; height:44px; padding:0 16px;
                  border-bottom:1px solid var(--gray-200); flex-shrink:0; }
  .up-card-title { font-size:13px; font-weight:700; line-height:1.6; color:var(--gray-1000); }
  .up-card-body { padding:16px; display:flex; flex-direction:column; gap:24px; }
  /* 카드 머리 좌측 묶음 — Figma 128:2699: 제목과 제한 배지 사이 gap 12, 배지끼리 gap 6 */
  .up-head-left  { display:flex; align-items:center; gap:12px; flex:1; min-width:0; }
  .up-head-right { display:flex; align-items:center; gap:12px; flex-shrink:0; }
  .up-head-limits { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  /* 업로드 제한 배지 — h22 · r999 · pad 2/8 · 11/500 · gray-100 바탕 */
  .up-limit { display:inline-flex; align-items:center; height:22px; padding:2px 8px;
              border-radius:999px; background:var(--gray-100);
              font-size:11px; font-weight:500; line-height:18px; color:var(--gray-800);
              white-space:nowrap; }
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

  @keyframes spin { to { transform:rotate(360deg); } }

  /* ── 라벨 100 + 입력 (Figma 128:789) ── */
  .fu-row   { display:flex; align-self:stretch; gap:8px; }
  .fu-label { width:100px; min-height:32px; flex-shrink:0; display:flex; align-items:center;
              font-size:13px; font-weight:500; line-height:1.6; color:var(--gray-700); }
  .fu-field { flex:1; min-width:0; }
  /* 한 줄 입력·셀렉트 — 높이 32, radius 8 */
  .fu-input { width:100%; height:32px; padding:0 12px; border-radius:8px;
              background:var(--gray-0); border:1px solid var(--gray-200);
              font-size:13px; font-weight:400; line-height:1.6; color:var(--gray-1000); }
  .fu-input::placeholder { color:var(--gray-500); }
  .fu-input:focus { outline:none; border-color:var(--primary); }
  textarea.fu-input { height:80px; padding:12px; resize:vertical; }
  /* 환자 검색칸 위에 얹히는 안내 배지 (Figma 128:793) */

  /* ── 파일 타일 그리드 (Figma 128:798) — 6열, gap 8 ── */
  .fu-grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; }
  @media(max-width:1400px){ .fu-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }
  @media(max-width:1100px){ .fu-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } }
  @media(max-width:700px) { .fu-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }

  /* 추가 타일 (Figma 128:799) — 높이 140, primary 테두리 */
  /* 넣는 자리 위의 서류명 고르는 칸 (128:796 옆) */
  .fu-pick { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
  .fu-pick-label { font-size:12px; font-weight:600; color:var(--gray-700); }
  .fu-pick-sel { height:30px; min-width:220px; padding:2px 8px; font-size:13px;
                 border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-0);
                 color:var(--gray-1000); cursor:pointer; }
  .fu-pick-sel:focus { outline:none; border-color:var(--primary); }

  .fu-add { position:relative; display:flex; flex-direction:column; justify-content:center; align-items:center;
            gap:8px; height:140px; padding:0 12px; border-radius:8px;
            background:var(--gray-0); border:1px solid var(--primary); cursor:pointer;
            transition:var(--transition); text-align:center; }
  .fu-add:hover, .fu-add.dragover { background:var(--primary-light); }
  .fu-add input[type=file] { position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
  .fu-add-icon  { font-size:16px; line-height:16px; color:var(--primary); }
  .fu-add-title { font-size:13px; font-weight:500; line-height:1.4; color:var(--primary); }
  .fu-add-sub   { font-size:11px; font-weight:500; line-height:1.2; color:var(--gray-500); }
  .fu-add-text  { display:flex; flex-direction:column; align-items:center; gap:6px; }

  /* 선택된 파일 타일 (Figma 128:3202) — 미리보기 위에 어두운 겹판 */
  .fu-tile { display:flex; flex-direction:column; gap:6px; }
  .fu-card { position:relative; height:140px; border-radius:8px; overflow:hidden;
             background:var(--gray-0); border:1px solid var(--gray-200); }
  .fu-card img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
  .fu-card-veil { position:absolute; inset:0; background:rgba(0,0,0,.4); }
  /* 140 타일을 통째로 채우는 자리표시 아이콘이다. 16 으로 내리면 타일이 비어 보여 24 로 둔다 */
  .fu-card-doc  { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                  font-size:24px; color:rgba(255,255,255,.7); }
  /* 삭제 — 16×16 원 (Figma 128:3205) */
  .fu-del { position:absolute; top:8px; right:8px; width:16px; height:16px; border-radius:999px;
            background:var(--primary); border:none; color:var(--gray-0); cursor:pointer;
            display:flex; align-items:center; justify-content:center; font-size:10px; line-height:12px; padding:0; }
  /* 유형 선택 — 좌상단, 흰 글씨 + 화살표 (Figma 128:3211) */
  .fu-type { position:absolute; top:8px; left:8px; display:flex; align-items:center; gap:4px; }
  .fu-type select { appearance:none; -webkit-appearance:none; background:transparent; border:none;
                    color:var(--gray-0); font-size:12px; font-weight:400; line-height:1.6;
                    padding:0; cursor:pointer; }
  .fu-type select option { color:var(--gray-1000); }
  .fu-type i { font-size:10px; line-height:12px; color:var(--gray-0); }
  /* 파일명 띠 — 아래 가득, 반투명 검정 (Figma 128:3208) */
  .fu-name { position:absolute; left:0; right:0; bottom:0; padding:6px;
             background:rgba(0,0,0,.4); color:var(--gray-0);
             font-size:11px; font-weight:500; line-height:1.2;
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  /* 크기 — 파일명 띠 바로 위 오른쪽 (Figma 128:3210) */
  .fu-size { position:absolute; right:8px; bottom:31px; color:var(--gray-0); opacity:.4;
             font-size:10px; font-weight:400; line-height:1.2; }

  /* ── Patient search ── */
  .patient-search-wrap { position:relative; }
  /* calc 는 연산자 둘레에 공백이 없으면 통째로 무효가 된다 — 드롭다운이 제자리에 붙지 않았다 */
  .patient-search-drop { position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--gray-0); border:1px solid var(--primary); border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.13); z-index:500; max-height:240px; overflow-y:auto; display:none; }
  .patient-search-drop.open { display:block; }
  .ps-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border); font-size:12px; line-height:19px; display:flex; align-items:center; gap:8px; transition:background .1s; }
  .ps-item:last-child { border-bottom:none; }
  .ps-item:hover, .ps-item.active { background:var(--primary-light); }
  .ps-item-name { font-weight:700; }
  .ps-item-meta { font-size:11px; line-height:18px; color:var(--gray-500); }
  .ps-no-result { padding:12px; font-size:12px; line-height:19px; color:var(--gray-500); text-align:center; }

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
  .history-badge { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; flex-shrink:0; }

  /* mobile upload */
  .mobile-upload-card { display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--primary-light); border-radius:12px; margin-bottom:12px; }

  /* ── Progress overlay ── */
  .progress-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.55); align-items:center; justify-content:center; }
  .progress-overlay.active { display:flex; }
  .progress-box { background:var(--gray-0); border-radius:12px; padding:24px; text-align:center; min-width:300px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .progress-spinner { width:48px; height:48px; border:4px solid var(--primary-light); border-top-color:var(--primary); border-radius:999px; animation:spin .8s linear infinite; margin:0 auto 16px; }
  .progress-title { font-size:16px; font-weight:700; line-height:19px; color:var(--gray-1000); margin-bottom:6px; }
  .progress-sub   { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
</style>
@endpush

@section('content')

{{-- 단계 표시는 두지 않는다 — 파일을 고르고 등록하면 그다음은 화면이 알아서
     넘어간다. 늘 같은 그림이라 읽을 것이 없었다. --}}

{{-- ── 업로드 레이아웃 ── --}}
<div class="upload-layout">
  <div>
    {{-- 모바일 대기 알림 --}}
    @if($mobilePending->isNotEmpty())
    <div class="mobile-upload-card">
      <div style="font-size:16px;line-height:16px;color:var(--primary);"><i class="fa-solid fa-mobile-screen-button"></i></div>
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:700;line-height:21px;">모바일 업로드 대기 {{ $mobilePending->count() }}건</div>
        <div style="font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);margin-top:2px;">최근: {{ $mobilePending->first()?->patient_name_ocr ?? '환자' }} — {{ $mobilePending->first()?->created_at->format('H:i') }}</div>
      </div>
      <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> 대기</span>
    </div>
    @endif

    <div class="up-card">
      <div class="up-card-head">
        <div class="up-head-left">
          <span class="up-card-title">처방자료 업로드</span>
          {{-- 업로드 제한 배지 — 시안 128:2699. 문구는 실제 제한과 같다:
               컨트롤러 max:40 · max:50240(KB) · mimes:jpg,jpeg,png,pdf,heic,
               화면 JS 도 40개 / 50MB 에서 막는다. --}}
          <span class="up-head-limits">
            <span class="up-limit">최대 50MB</span>
            <span class="up-limit">JPG/PNG/PDF/HEIC</span>
            <span class="up-limit">최대 40개</span>
          </span>
        </div>
        <div class="up-head-right">
          @if($reviewPending > 0)
          <span class="up-head-alert">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:12px;"></i>검수 대기 {{ $reviewPending }}건
          </span>
          @endif
          <a href="{{ route('prescriptions.index') }}" class="up-head-btn">처방전 목록</a>
        </div>
      </div>
      <div class="up-card-body">
        <form id="uploadForm" method="POST" action="{{ route('prescriptions.store') }}" enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap:24px;">
          @csrf
          <input type="hidden" name="assigned_user_id" id="h_assigned_user_id">
          <input type="hidden" name="admin_note"       id="h_admin_note">
          <input type="hidden" name="patient_id"       id="h_patient_id">

          {{-- ── 파일 업로드 (Figma 128:781 / 128:3175) ── --}}
          <div class="up-sec">
            <div class="up-sec-head">
              <span class="up-sec-title">파일 업로드</span>
              {{-- 시안은 유형 안내를 이 자리에 둔다 (128:784) --}}
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:400;line-height:1.6;color:var(--gray-600);">
                <i class="fa-regular fa-circle-question" style="font-size:12px;"></i>
                넣는 자리 위에서 <b style="font-weight:500;color:var(--primary-700);">서류 유형</b>을 고르고 파일을 올립니다 — 파일마다 다르면 타일에서 고칩니다.
              </span>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;">

              {{-- 환자 선택 (128:789) --}}
              <div class="fu-row">
                <span class="fu-label">환자 선택</span>
                <div class="fu-field patient-search-wrap">
                  <div style="display:flex;gap:8px;align-items:center;position:relative;">
                    <div style="flex:1;min-width:0;position:relative;">
                      <input type="text" id="patientSearchInput" class="fu-input"
                             placeholder="이름 또는 연락처로 검색" autocomplete="off" />
                    </div>
                    <div id="patientSelectedBadge" style="display:none;align-items:center;gap:8px;flex:1;min-width:0;">
                      <span id="patientSelectedName" class="fu-input"
                            style="display:flex;align-items:center;background:var(--gray-50);color:var(--gray-800);"></span>
                      <button type="button" onclick="clearPatient()"
                              style="height:32px;padding:0 12px;border-radius:8px;background:var(--gray-0);border:1px solid var(--gray-200);font-size:13px;color:var(--gray-1000);cursor:pointer;white-space:nowrap;">다시 선택</button>
                    </div>
                  </div>
                  <div class="patient-search-drop" id="patientDrop"></div>
                </div>
              </div>

              {{-- 처방 서류 (128:796) — 고를 수 있는 서류명은 환경 설정이 정한다 --}}
              <div class="fu-row">
                <span class="fu-label">처방 서류</span>
                <div class="fu-field">
                  {{-- 올리기 전에 서류명을 먼저 고른다. 여기서 고른 것이 새로 넣는 파일의
                       서류명이 되고, 파일마다 다르면 타일에서 다시 고칠 수 있다. --}}
                  <div class="fu-pick">
                    <span class="fu-pick-label">서류 유형</span>
                    <select class="fu-pick-sel" id="pick-rx">
                      @foreach(array_merge($docTypes['rx'], $docTypes['etc']) as $t)
                        <option value="{{ $t['code'] }}">{{ $t['label'] }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="fu-grid" id="grid-rx">
                    <label class="fu-add" data-group="rx">
                      <input type="file" accept=".jpg,.jpeg,.png,.pdf,.heic" multiple>
                      <i class="fa-solid fa-plus fu-add-icon"></i>
                      <span class="fu-add-text">
                        <span class="fu-add-title">파일을 드래그 하거나<br>클릭하여 선택</span>
                        <span class="fu-add-sub">{{ collect($docTypes['rx'])->pluck('label')->take(3)->implode('ㆍ') }} 등</span>
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              {{-- 청구·기타 자료 (165:1609) --}}
              <div class="fu-row">
                <span class="fu-label">청구ㆍ기타 자료</span>
                <div class="fu-field">
                  <div class="fu-pick">
                    <span class="fu-pick-label">서류 유형</span>
                    <select class="fu-pick-sel" id="pick-etc">
                      @foreach(array_merge($docTypes['claim'], $docTypes['etc']) as $t)
                        <option value="{{ $t['code'] }}">{{ $t['label'] }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="fu-grid" id="grid-etc">
                    <label class="fu-add" data-group="etc">
                      <input type="file" accept=".jpg,.jpeg,.png,.pdf,.heic" multiple>
                      <i class="fa-solid fa-plus fu-add-icon"></i>
                      <span class="fu-add-text">
                        <span class="fu-add-title">파일을 드래그 하거나<br>클릭하여 선택</span>
                        <span class="fu-add-sub">{{ collect($docTypes['claim'])->pluck('label')->take(3)->implode('ㆍ') }} 등</span>
                      </span>
                    </label>
                  </div>
                </div>
              </div>

            </div>
          </div>

          {{-- 제출용 — 실제 전송은 여기에 담는다 --}}
          <input type="file" id="fileInput" name="prescription_images[]" multiple style="display:none;">

          {{-- 파일 선택 중 프로그레스 바 --}}
          <div id="fileProgressWrap" style="display:none;margin-top:14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;line-height:18px;color:var(--gray-500);margin-bottom:4px;">
              <span id="fileProgressLabel" style="font-weight:500;display:flex;align-items:center;gap:4px;">
                <i class="fa-solid fa-spinner" style="animation:spin .7s linear infinite;"></i> 파일 확인 중...
              </span>
              <span id="fileProgressPct" style="font-weight:700;color:var(--primary);">0%</span>
            </div>
            <div style="height:4px;background:var(--gray-200);border-radius:999px;overflow:hidden;">
              <div id="fileProgressBar"
                   style="height:100%;width:0%;background:linear-gradient(90deg,var(--primary),var(--primary-300));border-radius:999px;transition:width .45s cubic-bezier(.4,0,.2,1);"></div>
            </div>
          </div>

          {{-- ── 처방전 설정 (Figma 128:805) — 시안에서 이 카드 안으로 들어왔다 ── --}}
          <div class="up-sec">
            <span class="up-sec-title">처방전 설정</span>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div class="fu-row">
                <span class="fu-label">담당자</span>
                <div class="fu-field">
                  <select class="fu-input" id="sideAssignedUser">
                    <option value="">담당자 선택</option>
                    @foreach($managers as $m)
                      <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="fu-row">
                <span class="fu-label" style="align-items:flex-start;padding-top:8px;">메모</span>
                <div class="fu-field">
                  <textarea class="fu-input" id="sideAdminNote" placeholder="처방전 관련 메모"></textarea>
                </div>
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
        <div class="history-item" onclick="ceOpenTab('{{ route('prescriptions.show', $rx) }}', '주문 - {{ $rx->rx_number }}', 'file-edit-02')">
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
    <div style="margin-top:12px;font-size:11px;font-weight:500;line-height:18px;color:var(--gray-500);">10~30초 소요됩니다. 창을 닫지 마세요.</div>
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
// 시안(128:796 / 165:1609)은 문서 구분마다 업로드 영역을 따로 둔다.
// 처방 서류에 넣으면 처방전, 기타 자료에 넣으면 신분증으로 시작하고,
// 타일 왼쪽 위에서 유형을 바꿀 수 있다.
const fileInput = document.getElementById('fileInput');
const submitBtn = document.getElementById('submitBtn');
const form      = document.getElementById('uploadForm');

/* 서류명은 환경 설정 ▸ 서류 유형에서 정한다. 화면에 박아 두면 한 줄 늘리는 데도
   배포가 필요했다. 자리마다 고를 수 있는 것이 다르다 —
   처방 서류 자리에는 처방 서류를, 청구·기타 자리에는 청구 자료와 그 밖의 것을 둔다. */
const DOC_CODES  = @json($docTypes);
const GROUP_CODES = {
  rx:  DOC_CODES.rx.concat(DOC_CODES.etc),
  etc: DOC_CODES.claim.concat(DOC_CODES.etc),
};

/* 자리 위에서 고른 서류명이 새로 넣는 파일에 붙는다 — 같은 서류를 여러 장 올릴 때
   타일마다 다시 고르지 않아도 된다. 고르지 않았으면 그 자리의 첫 서류명이다. */
function pickedType(group) {
  const sel = document.getElementById('pick-' + group);
  return sel?.value || GROUP_CODES[group]?.[0]?.code || 'other';
}

let selectedFiles = []; // [{file, docType, group, url}]

document.querySelectorAll('.fu-add').forEach(box => {
  const group = box.dataset.group;
  ['dragenter','dragover'].forEach(e =>
    box.addEventListener(e, ev => { ev.preventDefault(); box.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(e =>
    box.addEventListener(e, ev => { ev.preventDefault(); box.classList.remove('dragover'); }));
  box.addEventListener('drop', ev => addFiles(ev.dataTransfer.files, group));
  const inp = box.querySelector('input[type=file]');
  inp.addEventListener('change', () => { addFiles(inp.files, group); inp.value = ''; });
});

function addFiles(fileObjs, group) {
  const allowed = ['jpg','jpeg','png','pdf','heic'];
  const added = [];
  Array.from(fileObjs).forEach(f => {
    if (selectedFiles.length + added.length >= 40) { showToast('최대 40개까지 선택할 수 있습니다.', 'warning'); return; }
    const ext = f.name.split('.').pop().toLowerCase();
    if (!allowed.includes(ext))  { showToast(f.name + ' — 지원하지 않는 형식', 'warning'); return; }
    if (f.size > 51200 * 1024)   { showToast(f.name + ' — 50MB 초과', 'warning'); return; }
    if (selectedFiles.find(s => s.file.name === f.name && s.file.size === f.size)) return;
    // 이미지면 타일에 미리보기를 깔아 준다. PDF 는 아이콘으로 대신한다.
    const url = /^(jpg|jpeg|png)$/.test(ext) ? URL.createObjectURL(f) : null;
    added.push({ file: f, docType: pickedType(group), group, url });
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
}

function removeFile(idx) {
  // 미리보기로 잡아 둔 주소를 놓아 준다
  if (selectedFiles[idx]?.url) URL.revokeObjectURL(selectedFiles[idx].url);
  selectedFiles.splice(idx, 1);
  renderFileList();
}

function renderFileList() {
  submitBtn.disabled = selectedFiles.length === 0;

  ['rx', 'etc'].forEach(group => {
    const grid = document.getElementById('grid-' + group);
    const add  = grid.querySelector('.fu-add');
    // 추가 타일만 남기고 지운 뒤 다시 그린다
    grid.querySelectorAll('.fu-tile').forEach(el => el.remove());

    selectedFiles.forEach((item, i) => {
      if (item.group !== group) return;
      const f    = item.file;
      const size = f.size > 1024*1024 ? (f.size/1024/1024).toFixed(1)+'MB' : (f.size/1024).toFixed(0)+'KB';
      const ext  = f.name.split('.').pop().toLowerCase();
      const list = GROUP_CODES[group] ?? [];
      // 예전에 올린 유형이 지금 목록에 없으면(사용 중지) 그 줄만 따로 붙여 둔다
      const opts = (list.some(c => c.code === item.docType) ? list : list.concat([{ code: item.docType, label: item.docType }]))
        .map(c => `<option value="${c.code}"${item.docType === c.code ? ' selected' : ''}>${escHtml(c.label)}</option>`)
        .join('');

      const tile = document.createElement('div');
      tile.className = 'fu-tile';
      tile.innerHTML =
        `<div class="fu-card">
           ${item.url ? `<img src="${item.url}" alt="">` : ''}
           <div class="fu-card-veil"></div>
           ${item.url ? '' : `<div class="fu-card-doc"><i class="fa-regular ${ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-image'}"></i></div>`}
           <span class="fu-type">
             <select onchange="changeDocType(${i}, this.value)">${opts}</select>
             <i class="fa-solid fa-chevron-right"></i>
           </span>
           <button type="button" class="fu-del" onclick="removeFile(${i})" title="제거">
             <i class="fa-solid fa-xmark"></i>
           </button>
           <span class="fu-size">${size}</span>
           <span class="fu-name" title="${escHtml(f.name)}">${escHtml(f.name)}</span>
         </div>`;
      grid.appendChild(tile);   // 추가 타일이 맨 앞이라 그대로 뒤에 쌓으면 순서가 맞는다
    });
  });
}

function resetFiles() {
  selectedFiles.forEach(s => { if (s.url) URL.revokeObjectURL(s.url); });
  selectedFiles = [];
  fileInput.value = '';
  renderFileList();
  setStep(1, 'active'); setStep(2); setStep(3);
}

// ── 폼 제출 ─────────────────────────────────────────────
form.addEventListener('submit', async function (e) {
  e.preventDefault();          // 보내는 일은 아래에서 우리가 한다

  if (selectedFiles.length === 0) return;

  // 누구의 처방인지 모른 채로는 올리지 않는다 — 나중에 잇는 일이 더 비싸다
  if (!document.getElementById('h_patient_id').value) {
    showToast('환자를 먼저 고르십시오.', 'warning');
    document.getElementById('patientSearchInput')?.focus();
    return;
  }

  const hasPrescription = selectedFiles.some(f => f.docType === 'prescription');
  if (!hasPrescription) {
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
  sub += ' 올리는 중...';
  document.getElementById('progressSub').textContent = sub;
  document.getElementById('progressOverlay').classList.add('active');

  setStep(1, 'done'); setStep(2, 'active');

  try {
    const res = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      throw new Error(data.message || Object.values(data.errors ?? {}).flat()[0] || '업로드하지 못했습니다.');
    }

    document.getElementById('progressOverlay').classList.remove('active');
    setStep(2, 'done'); setStep(3, 'done');
    showToast(data.message, 'success', 4000);

    /* 주문 등록 화면은 화면 탭으로 연다 — 올린 자리는 그대로 두어 다음 건을 잇달아
       올릴 수 있다. 워크스페이스 밖에서 열었으면 그 자리에서 옮겨 간다. */
    if (typeof ceOpenTab === 'function') {
      ceOpenTab(data.url, '주문 등록 - ' + (data.rx_number || ''), 'file-edit-02');
      resetFiles();
      form.querySelectorAll('input[name="file_doc_types[]"]').forEach(el => el.remove());
    } else {
      location.href = data.url;
    }
  } catch (err) {
    document.getElementById('progressOverlay').classList.remove('active');
    setStep(2); setStep(1, 'active');
    form.querySelectorAll('input[name="file_doc_types[]"]').forEach(el => el.remove());
    showToast(err.message || '업로드하지 못했습니다.', 'danger', 6000);
  }
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
  { selector: '#patientSearchInput', title: '환자 선택', body: '이름 또는 연락처로 검색하여 기존 환자를 선택하면 OCR 결과와 자동 연결됩니다.' },
  { selector: '#grid-rx',  title: '처방 서류', body: '등록신청서·처방전·결과지를 넣습니다. 여기에 넣은 파일은 처방전으로 시작하며 OCR 분석 대상이 됩니다.' },
  { selector: '#grid-etc', title: '청구ㆍ기타 자료', body: '거래명세서·현금영수증 등 청구 자료를 넣습니다. 타일 왼쪽 위에서 서류명을 고르며, 목록은 <b>환경 설정 ▸ 서류 유형</b>에서 늘릴 수 있습니다.' },
  { selector: '#submitBtn', title: '등록 버튼', body: '환자를 고르고 파일을 넣은 뒤 누릅니다. 올리고 나면 <b>주문 등록 화면이 새 화면 탭</b>으로 열리고, 이 자리는 그대로 남아 다음 건을 이어 올릴 수 있습니다.' },
];
</script>
@endpush
