{{-- resources/views/prescriptions/upload.blade.php --}}
@extends('layouts.app')

{{-- 화면 이름은 시안 128:689 헤더 기준 '처방자료 업로드'.
     사이드바(layouts/app data-title)·워크스페이스 탭·카드 머리도 모두 같은 이름이다. --}}
@section('title', '처방자료 업로드')
@section('page-title', '처방자료 업로드')
{{-- 시안 128:691·128:693 — 「홈 - 처방전 목록」 두 마디, 마디 사이 8.
     제목(처방자료 업로드)과 마지막 마디가 다른 것은 시안이 일부러 그렇게 그린 자리다
     (128:688 에서 제목 128:689 = 처방자료 업로드, 마디 128:693 = 처방전 목록).
     마디로 세우는 일은 이제 레이아웃이 한다 — 여기서는 낱말만 적는다. --}}
@section('breadcrumb', '홈 - 처방전 목록')

{{-- 검수 대기·처방전 목록은 시안(128:3167)대로 업로드 카드 헤더로 옮겼다.
     상단 헤더의 옛 건수는 화면에 뿌리는 최근 5건 안에서만 세어, 실제보다 적게 나왔다. --}}

@push('styles')
<style>


  /* ── Layout (Figma 128:768) — 3 : 1, gap 12 ── */
  /* 시안 128:769 · 128:827 은 좌 1167×834 · 우 389×834 로 두 카드 높이가 같다.
     오른쪽 「최근 업로드 이력」은 내용이 373 뿐이고 나머지는 흰 여백으로 남는다.
     align-items:start 를 두면 카드가 제 내용만큼만 자라 아랫단이 어긋난다. */
  .upload-layout { display:grid; grid-template-columns:minmax(0,3fr) minmax(0,1fr); gap:12px; align-items:stretch; }
  /* 왼쪽 칸은 모바일 알림 + 카드를 담는 껍데기라, 카드가 남은 높이를 받게 한다 */
  .upload-layout > div { display:flex; flex-direction:column; }
  .upload-layout > div > .up-card { flex:1; }
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
                   font-size:12px; font-weight:500; color:var(--alert-500, #F17E64); white-space:nowrap; }
  /* 본문 안 구획 제목 */
  .up-sec { display:flex; flex-direction:column; gap:16px; }
  .up-sec-head { display:flex; align-items:center; justify-content:space-between; }
  .up-sec-title { font-size:14px; font-weight:700; line-height:1.6; color:var(--gray-800); }
  /* 구획 제목 옆 안내문 (Figma 128:784) — 561×19 한 덩어리 문장.
     문장을 inline-flex 로 담으면 <b> 마다 gap 4 가 끼어들어 「유형|을」 「처방전|은」
     「위임장|등은」 세 곳에서 낱말이 갈라진다. 보통 글줄로 두고 아이콘만 앞에 세운다.
     (전역 .ds-grid-hint 이 같은 이유로 같은 방식을 쓴다.) */
  .up-sec-note { display:inline-block; font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  /* 강조 3곳 — 시안은 굵기를 올리지 않고 색만 바꾼다(본문도 이미 500) */
  .up-sec-note b { font-weight:500; color:var(--primary-700); }
  /* 12×12 alert-circle(동그라미 안 느낌표) — 전역 --icon-alert-circle 을 마스크로 그린다.
     글줄 높이 19 만큼 상자를 잡고 그 안에서 12×12 를 가운데 놓아 글줄에 정확히 맞춘다. */
  .up-note-icon { display:inline-block; vertical-align:top; width:12px; height:19px; margin-right:4px;
                  background:currentColor;
                  -webkit-mask:var(--icon-alert-circle) center / 12px 12px no-repeat;
                          mask:var(--icon-alert-circle) center / 12px 12px no-repeat; }
  /* 처방전 설정 구획 — 시안은 1135×308 고정이지만 높이를 못박지 않는다.
     내용이 158 뿐이라 메모 아래로 150 이 흰 바닥으로 남았다. 초기화·등록은 내용 바로 밑에 선다. */
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
  /* 담당자 선택 상자 화살표 (Figma 128:814) — 14×14 chevron, 아래 방향, #101317.
     브라우저 기본 화살표 대신 직접 그린다. .fu-input 의 background 단축이
     background-image 를 지우므로 반드시 그 뒤에 온다. */
  select.fu-input {
    appearance:none; -webkit-appearance:none; padding-right:34px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23101317' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5.3 8.9 12 14.9 18.7 8.9'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center; background-size:14px 14px;
  }
  /* ── 파일 타일 그리드 (Figma 128:798) — 6열, gap 8 ── */
  .fu-grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; }
  @media(max-width:1400px){ .fu-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }
  @media(max-width:1100px){ .fu-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } }
  @media(max-width:700px) { .fu-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }

  /* 추가 타일 (Figma 128:799) — 높이 140, primary 테두리 */
  /* 넣는 자리 위의 서류명 고르는 칸 (128:796 옆) */
  .fu-pick { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
  .fu-pick-label { font-size:12px; font-weight:500; color:var(--gray-700); }
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
  /* 1px 선을 border 로 두면 겹판(inset:0)이 안쪽 상자까지만 덮어 타일 둘레에 회색 테가 남는다.
     overflow:hidden 은 안쪽 상자에서 자르므로 겹판을 밖으로 늘려도 소용이 없다.
     시안 Rectangle 9 는 165×140 타일 전체를 덮는다 — 선을 inset 그림자로 그려 겹판 밑에 둔다. */
  .fu-card { position:relative; height:140px; border-radius:8px; overflow:hidden;
             background:var(--gray-0); box-shadow:inset 0 0 0 1px var(--gray-200); }
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
  /* 시안 128:3213 은 12×12 상자 안의 아래 방향 chevron(벡터 8×4) 이다 */
  .fu-type i { width:12px; height:12px; display:flex; align-items:center; justify-content:center;
               font-size:10px; line-height:12px; color:var(--gray-0); }
  /* 파일명 띠 — 아래 가득, 반투명 검정 (Figma 128:3208) — 164×25, 파일명은 띠 한가운데 */
  .fu-name { position:absolute; left:0; right:0; bottom:0; padding:6px;
             background:rgba(0,0,0,.4); color:var(--gray-0); text-align:center;
             font-size:11px; font-weight:500; line-height:13px;
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  /* 크기 — 파일명 띠 윗선에서 4 띄운 오른쪽 (Figma 128:3210 — 타일 아래에서 29) */
  .fu-size { position:absolute; right:8px; bottom:29px; color:var(--gray-0); opacity:.4;
             font-size:10px; font-weight:400; line-height:1.2; }

  /* ── Patient search ── */
  .patient-search-wrap { position:relative; }
  /* 조회 단추 — 입력칸과 같은 키를 쓴다 */
  .fu-find { flex:0 0 auto; height:32px; padding:0 12px; border-radius:8px;
             background:var(--gray-0); border:1px solid var(--gray-200);
             font-size:13px; font-weight:500; color:var(--gray-1000); cursor:pointer; white-space:nowrap; }
  .fu-find:hover { border-color:var(--primary); color:var(--primary); }

  /* ── 이름 조회 창 ──
     뼈대와 글자 값은 전역 창 규칙(.modal-overlay/.modal-box/.modal-hd/.modal-bd/.modal-ft)이
     정한다. 여기서는 이 창에만 필요한 것만 적는다 — 창 폭, 본문 줄 사이, 두 줄의 안내. */
  #pkModal { z-index: 1300; }
  #pkModal .modal-box { max-width: 860px; max-height: 86vh; }
  #pkModal .modal-bd { display:flex; flex-direction:column; gap:12px; }
  /* 찾는 칸 셋은 여섯 열을 둘씩 나눠 쓰고, 단추는 다음 줄 오른쪽 끝에 선다 */
  #pkModal .ds-filter-fields { grid-template-columns: repeat(6, minmax(0, 1fr)); }
  #pkModal .ds-filter-actions { grid-column: span 6; }
  .pk-note { font-size:12px; line-height:19px; color:var(--gray-600); }
  .pk-hint { font-size:12px; line-height:19px; color:var(--gray-600); margin-right:auto; }
  /* 환자를 고르면 selectPatient() 가 입력칸만 display:none 으로 감춘다.
     입력을 감싸는 상자를 따로 두면 그 상자가 flex:1 로 필드 절반(517)을 계속 차지해
     채워진 상자가 필드 한가운데부터 시작한다 — 입력을 직접 flex 항목으로 둔다.
     시안 128:3186 은 채워진 상자 946 + 8 + 「다시 선택」 73 = 1027 로 필드를 꽉 채운다. */
  .patient-search-row { display:flex; gap:8px; align-items:center; }
  .patient-search-row > #patientSearchInput { flex:1; min-width:0; }
  /* 「조회」는 늘 오른쪽 끝이다 — 고르기 전에는 입력칸 뒤, 고른 뒤에는 이름 상자 뒤.
     마크업 차례가 [입력][조회][이름 상자] 라 그대로 두면 고른 뒤 조회가 가운데 낀다. */
  .patient-search-row > #patientFindBtn { order:2; }
  .patient-search-row > #patientSelectedBadge { order:1; }
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
  /* 시안 128:834 는 357×53 이고 그 안에 1px 선이 들어가 있다(pad 8/12 + 이름 19 + 메타 18).
     border 로 그리면 선 2px 이 밖에 더 붙어 55 가 된다 — inset 그림자로 안쪽에 그린다. */
  .history-item { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px;
                  background:var(--gray-0); box-shadow:inset 0 0 0 1px var(--gray-200); cursor:pointer; transition:var(--transition); }
  .history-item:hover { background:var(--gray-50); box-shadow:inset 0 0 0 1px var(--primary); }
  .history-thumb { width:28px; height:28px; display:flex; align-items:center; justify-content:center;
                   font-size:16px; flex-shrink:0; }
  .history-body  { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; }
  .history-name  { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .history-meta  { font-size:11px; font-weight:500; line-height:18px; color:var(--gray-500); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  /* 처방번호·이름·시각 사이 구분점 — 시안 128:845 는 글자 「·」가 아니라 2×2 정원이다 */
  .history-dot { display:inline-block; vertical-align:middle; width:2px; height:2px;
                 border-radius:999px; background:var(--gray-300); margin:0 4px; }
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
<div class="upload-layout fill-rest">
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

              {{-- 이름 선택 (128:789) — 적어서 고르거나, 창을 열어 찾아 고른다 --}}
              <div class="fu-row">
                <span class="fu-label">이름 선택</span>
                <div class="fu-field patient-search-wrap">
                  <div class="patient-search-row">
                    <input type="text" id="patientSearchInput" class="fu-input"
                           placeholder="이름 또는 연락처로 검색" autocomplete="off" />
                    {{-- 이름이 겹치거나 기억이 흐릴 때는 창을 열어 전화번호·생년월일까지 보고 고른다 --}}
                    <button type="button" class="fu-find" id="patientFindBtn" onclick="pkOpen()">
                      <i class="fa-solid fa-magnifying-glass"></i> 조회
                    </button>
                    {{-- 고른 뒤에도 「조회」는 같은 자리에 그대로 있다. 예전에는 그 자리에
                         「다시 선택」이 새로 생겨, 다시 고르려면 단추 두 개를 차례로
                         눌러야 했다 — 이제 「조회」 한 번으로 다시 고른다. --}}
                    <div id="patientSelectedBadge" style="display:none;align-items:center;gap:8px;flex:1;min-width:0;">
                      <span id="patientSelectedName" class="fu-input"
                            style="display:flex;align-items:center;background:var(--gray-50);color:var(--gray-800);"></span>
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

              {{-- 청구ㆍ기타 자료 자리는 두지 않는다. 이 화면(과 앱)은 처방 서류를 올리는
                   자리다 — 거래명세서·현금영수증 같은 청구 자료는 주문이 선 다음에
                   그 건에서 나오는 것이라, 처방을 올리는 자리에서 함께 받으면 어느 주문의
                   것인지가 정해지지 않는다. 서류 관리에서 그 처방전에 붙인다. --}}

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
          <div class="up-sec up-sec-setting">
            <span class="up-sec-title">처방전 설정</span>
            <div style="display:flex;flex-direction:column;gap:8px;">
              {{-- 담당자 — 이름을 쳐서 고른다. 바로 위 「이름 선택」과 같은 방식이다.
                   고르는 칸으로 두었더니 사람이 늘수록 굴려 내려가 찾아야 했고, 이름을
                   알고 있어도 목록에서 눈으로 짚어야 했다. --}}
              <div class="fu-row">
                <span class="fu-label">담당자</span>
                <div class="fu-field patient-search-wrap">
                  <input type="text" id="sideAssignedName" class="fu-input"
                         placeholder="담당자 이름으로 검색" autocomplete="off">
                  {{-- 저장하는 쪽은 이 값을 읽는다 — 이름이 아니라 누구인지가 실려야 한다 --}}
                  <input type="hidden" id="sideAssignedUser">
                  <div class="patient-search-drop" id="sideAssignedDrop"></div>
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
            <span class="history-meta">{{ $rx->rx_number }}<i class="history-dot"></i>{{ $rx->patient_name_ocr ?? '-' }}<i class="history-dot"></i>{{ $rx->created_at->format('H:i') }}</span>
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

{{-- ── 이름 조회 창 ─────────────────────────────────────
     위에서 찾고 아래 표에서 고른다. 같은 이름이 여럿일 때 전화번호·생년월일로 가른다.

     뼈대는 집 안의 다른 창과 같다 — 덮개(.modal-overlay) · 상자(.modal-box) ·
     머리(.modal-hd) · 본문(.modal-bd) · 바닥(.modal-ft). 찾는 자리도 다른 화면의
     검색 필터와 같은 짜임이다(.ds-filter-fields · 라벨 위, 단추 오른쪽 끝).
     예전에는 이 창만 청록 머리에 제 나름의 여백과 라벨 값을 갖고 있어, 같은 일을
     하는 창인데 혼자 다르게 보였다. --}}
<div class="modal-overlay" id="pkModal" style="display:none;">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="pkTitle">
    <div class="modal-hd">
      <i class="fa-solid fa-user" style="color:var(--primary);font-size:17px;"></i>
      <span class="modal-title" id="pkTitle">이름 조회</span>
      <button type="button" class="modal-close" onclick="pkClose()" aria-label="닫기">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="modal-bd">
      <div class="ds-filter-fields">
        <div class="ds-filter-field span-2">
          <label class="ds-field-label" for="pkName">이름</label>
          <input type="text" id="pkName" class="form-control" placeholder="이름" autocomplete="off">
        </div>
        <div class="ds-filter-field span-2">
          <label class="ds-field-label" for="pkPhone">전화번호</label>
          <input type="text" id="pkPhone" class="form-control" placeholder="010-0000-0000" autocomplete="off">
        </div>
        <div class="ds-filter-field span-2">
          <label class="ds-field-label" for="pkBirth">생년월일</label>
          <input type="text" id="pkBirth" class="form-control" placeholder="1982-01-08 또는 820108" autocomplete="off">
        </div>
        {{-- 주민등록번호 — 찾는 데도 쓰고, 없을 때 새로 만드는 데도 쓴다.
             처방 서류는 이 번호로 공단에 청구하므로, 이름만으로 만든 거래처는
             결국 누군가 다시 열어 번호를 채워야 한다. --}}
        <div class="ds-filter-field span-2">
          <label class="ds-field-label" for="pkRn">주민등록번호</label>
          <input type="text" id="pkRn" class="form-control" placeholder="900101-1234567"
                 maxlength="14" autocomplete="off">
        </div>
        <div class="ds-filter-actions">
          <button type="button" class="ds-btn" onclick="pkReset()">초기화</button>
          <button type="button" class="ds-btn ds-btn-primary" onclick="pkSearch()">검색</button>
        </div>
      </div>

      <div class="pk-note" id="pkNote"></div>
      <div id="pkGrid"></div>
    </div>

    <div class="modal-ft">
      <span class="pk-hint">줄을 더블클릭하거나 고른 뒤 「선택」을 누릅니다.</span>
      {{-- 「다시 선택」을 걷으면서 고른 것을 무를 길이 사라졌다 — 여기에 둔다 --}}
      {{-- 없는 사람을 찾고 나면 다음 걸음은 언제나 「그럼 새로 적자」다 —
           거래처 관리로 건너가 다시 찾게 하지 않는다. 찾았을 때는 숨는다:
           고를 것이 눈앞에 있는데 새로 만들라고 권하면 같은 사람이 둘로 갈라진다. --}}
      <button type="button" class="btn btn-outline btn-sm" id="pkNewBtn" style="display:none;"
              onclick="pkCreate(this)">
        <i class="fa-solid fa-user-plus"></i> 신규
      </button>
      <button type="button" class="btn btn-outline btn-sm" onclick="clearPatient(); pkClose();">선택 해제</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="pkClose()">닫기</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="pkPick()">선택</button>
    </div>
  </div>
</div>

{{-- 올리는 동안 덮는 화면. 예전에는 OCR 이 돌던 자리라 「OCR 분석 중」이라 적혀 있었는데,
     OCR 을 쓰지 않게 된 뒤로는 파일을 올리는 동안만 뜬다 — 하는 일 그대로 적는다. --}}
<div class="progress-overlay" id="progressOverlay">
  <div class="progress-box">
    <div class="progress-spinner"></div>
    <div class="progress-title">올리는 중...</div>
    <div class="progress-sub" id="progressSub">파일을 올리고 있습니다</div>
    <div style="margin-top:12px;font-size:11px;font-weight:500;line-height:18px;color:var(--gray-500);">창을 닫지 마세요.</div>
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

// ── 담당자 ───────────────────────────────────────────────
/* 환자와 같은 방식으로 쳐서 고른다. 다른 것은 하나뿐이다 — 담당자는 이름 말고
   견줄 것이 없어(전화번호도 주민번호도 여기 쓰지 않는다) 이름만 본다. */
const MANAGERS = @json($managers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values());

const mgInput = document.getElementById('sideAssignedName');
const mgHid   = document.getElementById('sideAssignedUser');
const mgDrop  = document.getElementById('sideAssignedDrop');

function mgRender(list) {
  mgDrop.innerHTML = list.length
    ? list.map(m =>
        `<div class="ps-item" onclick="mgPick(${m.id}, '${escHtml(m.name)}')">
           <i class="fa-solid fa-user-tie" style="color:var(--primary);font-size:13px;"></i>
           <div class="ps-item-name">${escHtml(m.name)}</div>
         </div>`).join('')
    : '<div class="ps-no-result">그런 담당자가 없습니다</div>';
  mgDrop.classList.add('open');
}

window.mgPick = function (id, name) {
  mgHid.value   = id;
  mgInput.value = name;
  mgDrop.classList.remove('open');
};

/* 손으로 고쳐 쓰면 고른 사람과 어긋난다 — 이어 둔 것을 푼다. 그러면 담당자 없이
   저장되지, 엉뚱한 사람에게 붙지 않는다. */
mgInput?.addEventListener('input', function () {
  mgHid.value = '';
  const q = this.value.trim().toLowerCase();
  mgRender(q ? MANAGERS.filter(m => m.name.toLowerCase().includes(q)).slice(0, 10)
             : MANAGERS.slice(0, 10));
});

/* 빈 칸을 눌러도 누가 있는지 보인다 — 이름을 모를 때 굴려 보던 것이 그 자리다 */
mgInput?.addEventListener('focus', function () {
  const q = this.value.trim().toLowerCase();
  mgRender(q ? MANAGERS.filter(m => m.name.toLowerCase().includes(q)).slice(0, 10)
             : MANAGERS.slice(0, 10));
});

document.addEventListener('click', e => {
  if (mgInput && !mgInput.contains(e.target) && !mgDrop.contains(e.target)) {
    mgDrop.classList.remove('open');
  }
});

/* ── 이름 조회 창 ──────────────────────────────────────
   적어서 고르는 길(위 자동완성)은 그대로 두고, 이름이 겹치거나 기억이 흐릴 때
   전화번호·생년월일까지 보고 고르는 길을 하나 더 둔다. 목록은 이미 화면에 실려
   있어(PATIENTS) 서버를 다시 부르지 않는다. */
let pkGrid = null;

function pkRows(list) {
  return list.map(p => ({
    id: p.id, name: p.name, mobile: p.mobile || p.phone || '', birth: p.birth || '', rn: p.rn || '',
  }));
}

/* 전화번호 칸은 치는 대로 붙임표가 놓인다. 숫자 열한 개가 붙어 나오면 어디까지가
   국번인지 눈으로 세야 하고, 목록에 적힌 것과 견주기도 어렵다.
   놓는 규칙은 layouts/app.blade.php 한 곳에 있다(ceFormatPhone) — 화면마다 따로
   적으면 어느 화면에서는 02 번호가 셋으로 갈리고 어느 화면에서는 안 갈린다. */
ceBindPhone(document.getElementById('pkPhone'));

window.pkOpen = function () {
  document.getElementById('pkModal').style.display = 'flex';
  /* 이미 고른 사람이 있으면 그 이름을 넣어 준다 — 「조회」로 다시 고르는 길이라
     매번 빈칸에서 다시 찾게 하지 않는다. */
  document.getElementById('pkName').value =
    patientInput.value.trim() || document.getElementById('patientSelectedName').textContent.trim();
  pkSearch();
  setTimeout(() => document.getElementById('pkName').focus(), 50);
};

window.pkClose = function () {
  document.getElementById('pkModal').style.display = 'none';
};

window.pkReset = function () {
  ['pkName','pkPhone','pkBirth','pkRn'].forEach(id => document.getElementById(id).value = '');
  pkSearch();
};

window.pkSearch = function () {
  const name  = document.getElementById('pkName').value.trim().toLowerCase();
  const phone = document.getElementById('pkPhone').value.replace(/\D/g, '');
  const birth = document.getElementById('pkBirth').value.replace(/\D/g, '');
  const rnq   = document.getElementById('pkRn').value.replace(/\D/g, '');

  const hit = PATIENTS.filter(p => {
    if (name  && !(p.name || '').toLowerCase().includes(name)) return false;
    if (phone && !((p.mobile || '') + (p.phone || '')).replace(/\D/g, '').includes(phone)) return false;
    if (birth) {
      // 1982-01-08 로도, 820108 로도 찾는다
      const b = (p.birth || '').replace(/\D/g, '');
      const rn = (p.rn || '').replace(/\D/g, '');
      if (!b.includes(birth) && !rn.startsWith(birth) && !b.slice(2).includes(birth)) return false;
    }
    /* 목록의 주민번호는 가려져 있다(900101-1******) — 앞 일곱 자리까지만 견준다.
       뒤를 다 쳐도 가린 자리와는 맞지 않으니, 그만큼만 보고 나머지는 눈으로 가린다. */
    if (rnq) {
      const rn = (p.rn || '').replace(/\D/g, '');
      if (!rn.startsWith(rnq.slice(0, 7))) return false;
    }
    return true;
  });

  const rows = pkRows(hit);
  document.getElementById('pkNote').textContent =
    rows.length ? `${rows.length}명` : '찾은 사람이 없습니다.';

  // 없을 때만 「신규」가 선다
  const nb = document.getElementById('pkNewBtn');
  if (nb) nb.style.display = rows.length ? 'none' : '';

  if (!pkGrid) {
    pkGrid = new wwGrid({
      el: document.getElementById('pkGrid'),
      height: 320, editable: false, rowCheckbox: false, rowNumber: true,
      toolbar: false, footer: { total: true, selected: false, modified: false },
      columns: [
        { header: '이름',     name: 'name',   width: 140, sortable: true },
        { header: '전화번호', name: 'mobile', width: 160, sortable: true },
        { header: '생년월일', name: 'birth',  width: 130, align: 'center', sortable: true },
        { header: '주민번호', name: 'rn',     width: 140, align: 'center' },
      ],
      data: rows,
    });
    document.getElementById('pkGrid').addEventListener('dblclick', (e) => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = pkGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row) { selectPatient(row.id, row.name); pkClose(); }
    });
    // 한 줄을 고른 표시 — 「선택」 단추가 그 줄을 쓴다
    document.getElementById('pkGrid').addEventListener('click', (e) => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      pkGrid._pickedIndex = parseInt(cell.dataset.rowIndex, 10);
      document.querySelectorAll('#pkGrid tr').forEach(tr => tr.classList.remove('cg-row-selected'));
      cell.closest('tr')?.classList.add('cg-row-selected');
    });
  } else {
    pkGrid._pickedIndex = null;
    pkGrid.setData(rows);
  }
};

/* 주민등록번호에도 붙임표를 놓는다 — 열세 자리가 붙어 나오면 앞뒤를 눈으로 세야 한다 */
(function () {
  const el = document.getElementById('pkRn');
  if (!el) return;
  el.addEventListener('input', function () {
    const pos = el.selectionStart, prev = el.value;
    const d = el.value.replace(/\D/g, '').slice(0, 13);
    el.value = d.length <= 6 ? d : d.slice(0, 6) + '-' + d.slice(6);
    const diff = el.value.length - prev.length;
    try { el.setSelectionRange(pos + diff, pos + diff); } catch (e) {}
  });
})();

/**
 * 찾은 사람이 없을 때 — 그 자리에서 거래처를 만든다.
 *
 * 이름과 주민등록번호 둘 다 있어야 한다. 처방 서류는 이 번호로 공단에 청구하므로,
 * 이름만으로 만들어 두면 결국 누군가 다시 열어 번호를 채워야 한다.
 *
 * 만들고 나면 그 사람을 이 화면에 골라 둔다 — 창을 닫고 다시 찾게 하지 않는다.
 * 고르고 나면 첨부파일을 올릴 수 있다.
 */
window.pkCreate = async function (btn) {
  const nameEl = document.getElementById('pkName');
  const rnEl   = document.getElementById('pkRn');
  const name   = nameEl.value.trim();
  const rn     = rnEl.value.replace(/\D/g, '');

  if (!name) { showToast('이름을 적어 주십시오.', 'warning'); nameEl.focus(); return; }
  if (rn.length !== 13) {
    showToast('주민등록번호 열세 자리를 적어 주십시오.', 'warning');
    rnEl.focus();
    return;
  }

  /* 같은 번호를 두 번 만들지 않는다. 목록의 번호는 가려져 있어 앞 일곱 자리까지만
     견줄 수 있다 — 그래도 같은 날 태어난 같은 이름이 아니면 대개 걸린다. */
  const 겹침 = PATIENTS.find(p =>
    (p.rn || '').replace(/\D/g, '').startsWith(rn.slice(0, 7)) &&
    (p.name || '').replace(/^\s*\(E\)\s*/, '') === name);
  if (겹침) {
    showToast(`${name} 님은 이미 있습니다 — 그 줄을 고르십시오.`, 'warning');
    document.getElementById('pkRn').value = '';
    pkSearch();
    return;
  }

  BtnState.loading(btn, '만드는 중...');
  const res = await apiRequest('/patients', 'POST', {
    name,
    resident_no: rnEl.value.trim(),
    mobile: document.getElementById('pkPhone').value.trim() || null,
  });

  if (!res?.success) { BtnState.error(btn, '실패'); return; }

  BtnState.success(btn, '만듦');

  /* 새로 만든 사람을 목록에도 넣어 둔다 — 화면을 다시 열지 않아도 다음 찾기에 걸린다 */
  PATIENTS.unshift({ id: res.id, name, mobile: document.getElementById('pkPhone').value.trim() || '',
                     phone: '', birth: '', rn: rnEl.value.trim() });

  selectPatient(res.id, name);
  pkClose();
  showToast(`${name} 님을 만들고 골랐습니다. 이제 파일을 올릴 수 있습니다.`, 'success');
};

window.pkPick = function () {
  const i = pkGrid?._pickedIndex;
  const row = (i === null || i === undefined) ? null : pkGrid.getData()[i];
  if (!row) { showToast('고를 줄을 눌러 주십시오.', 'warning'); return; }
  selectPatient(row.id, row.name);
  pkClose();
};

// 바깥을 누르거나 Esc 로도 닫는다
document.getElementById('pkModal')?.addEventListener('mousedown', (e) => {
  if (e.target.id === 'pkModal') pkClose();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.getElementById('pkModal')?.style.display === 'flex') pkClose();
  if (e.key === 'Enter'  && document.getElementById('pkModal')?.style.display === 'flex'
      && ['pkName','pkPhone','pkBirth'].includes(document.activeElement?.id)) pkSearch();
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

/* 고른 사람을 물린다. 예전에는 「다시 선택」 단추가 이걸 불렀는데 그 단추를 걷었다 —
   이제 다시 고르는 일은 「조회」가 맡고, 이 함수는 창 안의 「선택 해제」가 부른다. */
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
   배포가 필요했다. 자리는 처방 서류 하나다 — 등록신청서ㆍ처방전ㆍ결과지ㆍ신분증에
   위임장과 기타를 더해 고른다. */
const DOC_CODES  = @json($docTypes);
const GROUP_CODES = {
  rx: DOC_CODES.rx.concat(DOC_CODES.etc),
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
  inp.addEventListener('change', () => {
    const picked = inp.files;          // 아래에서 비우기 전에 잡아 둔다
    addFiles(picked, group);
    inp.value = '';
  });
});

/* 같은 이름·같은 크기의 파일이 이미 있으면 대개 두 번 고른 것이다. 다만 서류 유형이
   다르면 일부러 같은 종이를 두 이름으로 올리는 때가 있어(한 장이 처방전이자 결과지인
   경우), 그때는 묻고 넣는다. 유형까지 같으면 그냥 지나간다 — 물어볼 것이 없다. */
async function addFiles(fileObjs, group) {
  const allowed = ['jpg','jpeg','png','pdf','heic'];
  const added   = [];
  const dupes   = [];                    // 이름이 겹쳐 물어봐야 하는 것들
  const docType = pickedType(group);     // 이번에 넣는 파일들의 서류명
  Array.from(fileObjs).forEach(f => {
    if (selectedFiles.length + added.length >= 40) { showToast('최대 40개까지 선택할 수 있습니다.', 'warning'); return; }
    const ext = f.name.split('.').pop().toLowerCase();
    if (!allowed.includes(ext))  { showToast(f.name + ' — 지원하지 않는 형식', 'warning'); return; }
    if (f.size > 51200 * 1024)   { showToast(f.name + ' — 50MB 초과', 'warning'); return; }
    const same = selectedFiles.concat(added)
      .find(s => s.file.name === f.name && s.file.size === f.size);
    if (same) {
      if (same.docType === docType) return;    // 유형까지 같으면 같은 것이다
      dupes.push({ file: f, docType, group, ext, was: same.docType });
      return;
    }
    // 이미지면 타일에 미리보기를 깔아 준다. PDF 는 아이콘으로 대신한다.
    const url = /^(jpg|jpeg|png)$/.test(ext) ? URL.createObjectURL(f) : null;
    added.push({ file: f, docType, group, url });
  });

  // 이름이 겹치는 것들은 한 번에 묻는다 — 파일마다 창을 띄우면 넣기가 고역이다
  if (dupes.length) {
    const names = dupes.map(d => `${d.file.name} (${typeLabel(d.was)} → ${typeLabel(d.docType)})`).join('\n');
    const ok = await ceConfirm(`파일 이름이 중복되었습니다. 계속 진행하시겠습니까?\n\n${names}`,
                               { tone: 'warning', confirmText: '계속' });
    if (ok) {
      dupes.forEach(d => {
        const url = /^(jpg|jpeg|png)$/.test(d.ext) ? URL.createObjectURL(d.file) : null;
        added.push({ file: d.file, docType: d.docType, group: d.group, url });
      });
    }
  }

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

/** 코드에 붙은 이름 — 물어볼 때 사람이 읽는 말로 적는다 */
function typeLabel(code) {
  for (const list of Object.values(DOC_CODES)) {
    const hit = list.find(c => c.code === code);
    if (hit) return hit.label;
  }
  return code;
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

  ['rx'].forEach(group => {
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
             <i class="fa-solid fa-chevron-down"></i>
           </span>
           <button type="button" class="fu-del" onclick="removeFile(${i})" title="제거">
             <i class="fa-solid fa-minus"></i>
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
  { selector: '#patientSearchInput', title: '이름 선택', body: '이름이나 연락처를 적어 고르거나, 옆의 <b>조회</b>로 창을 열어 전화번호·생년월일까지 보고 고릅니다.' },
  { selector: '#grid-rx',  title: '처방 서류', body: '등록신청서·처방전·결과지·신분증을 넣습니다. 타일 왼쪽 위에서 서류명을 고치며, 목록은 <b>환경 설정 ▸ 서류 유형</b>에서 늘릴 수 있습니다.' },
  { selector: '#submitBtn', title: '등록 버튼', body: '환자를 고르고 파일을 넣은 뒤 누릅니다. 올리고 나면 <b>주문 등록 화면이 새 화면 탭</b>으로 열리고, 이 자리는 그대로 남아 다음 건을 이어 올릴 수 있습니다.' },
];
</script>
@endpush
