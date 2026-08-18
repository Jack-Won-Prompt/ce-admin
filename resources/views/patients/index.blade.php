@extends('layouts.app')

{{-- 화면 이름은 시안 114:4778 · 114:6131 · 120:352 헤더 기준 '거래처 관리'.
     워크스페이스 탭 이름도 .page-title 을 그대로 읽어 간다(layouts/app.blade.php). --}}
@section('title', '거래처 관리')
@section('page-title', '거래처 관리')
@section('breadcrumb', '홈 - 거래처 관리')

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
  .patient-table tbody tr:hover td { background:rgba(40,121,139,.04); cursor:pointer; }
  .patient-table tbody tr:last-child td { border-bottom:none; }

  /* soft badges — 현재 마크업 사용처는 없으나 개발 자산이라 남겨 둔다.
     시안 배지 규격(h22 · r6 · pad 2/6 · 11px/500 · lh18)과 DS 토큰으로 맞춰 뒀다.
     초록(--success)과 분홍은 시안에 없어 primary/회색 램프로 접었다. */
  .nhis-badge   { display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:500;line-height:18px; }
  .nhis-yes     { background:var(--primary-light);color:var(--primary); }
  .nhis-no      { background:var(--border-light);color:var(--text-muted); }
  .gender-badge { display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:500;line-height:18px; }
  .gender-male  { background:var(--primary-light);color:var(--primary); }
  .gender-female{ background:var(--gray-100);color:var(--gray-700); }
  .rx-count-badge { display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:700;line-height:18px;background:var(--primary-light);color:var(--primary); }

  /* ── Modal (Vuexy style) ── */
  /* 가림막 색은 전역 .modal-overlay 와 같은 중성 먹빛으로 맞췄다.
     본디 rgba(67,56,202,.3) 남보라였는데 시안·DS 램프에 없는 색이다. */
  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(13,27,42,.45);backdrop-filter:blur(2px);z-index:200;align-items:center;justify-content:center; }
  .modal-overlay.show { display:flex; }
  /* 상자 — 시안 120:917 Frame 48101489: 960×902 · r12 · bg 흰색 · bd 1px gray-200.
     .modal-box 는 layouts/app.blade.php 도 쓰는 전역 이름이라 #addModal 안으로 묶는다.
     묶지 않으면 이 화면이 열려 있는 동안 전역 확인창(.modal-box.sm)에도 테두리가 붙는다. */
  #addModal .modal-box { background:var(--bg-card);border:1px solid var(--border);border-radius:12px;width:960px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(75,70,92,.25); }
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

  /* 패널 탭(조회결과/상세내용) */
  /* 기간 라디오 — Figma 114:4778: pill 146×32 · r8 · bd 1px gray-200 · pad 0/12 · gap 8,
     원 12×12(선택 primary-500 / 비선택 gray-300) 안에 6×6 흰 점, 라벨 13/400 */
  /* 그리드 셀 안의 작은 표시·버튼 (서명여부·미성년·신분증) */
  .pt-chip { display:inline-flex; align-items:center; padding:1px 8px; border-radius:999px;
             font-size:11px; font-weight:700; line-height:18px; white-space:nowrap;
             background:var(--gray-100); color:var(--gray-600); border:1px solid var(--gray-200); }
  .pt-chip.on   { background:var(--primary-50); color:var(--primary); border-color:var(--primary-200); }
  .pt-chip.warn { background:var(--alert-50);   color:var(--alert-500); border-color:var(--alert-100); }
  button.pt-chip.clickable { cursor:pointer; }
  button.pt-chip.clickable:hover { border-color:var(--primary); color:var(--primary); }

  /* 이미지 보기 — 서명·신분증 */
  #ptImgBackdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:10300; display:none; }
  #ptImgModal { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); display:none;
                max-width:min(92vw,760px); background:var(--gray-0); border-radius:12px;
                box-shadow:0 16px 48px rgba(0,0,0,.24); z-index:10301; overflow:hidden; }
  #ptImgHead { display:flex; align-items:center; gap:8px; height:40px; padding:0 8px 0 14px;
               background:var(--gray-50); border-bottom:1px solid var(--gray-200); }
  #ptImgTitle { flex:1; min-width:0; font-size:13px; font-weight:700; color:var(--gray-900);
                overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  #ptImgBody { padding:16px; background:var(--gray-100); text-align:center; max-height:74vh; overflow:auto; }
  #ptImgBody img { max-width:100%; display:block; margin:0 auto; background:#fff; border-radius:8px; }

  /* 알약 셋이 span-3 한 칸을 나눠 쓴다. 시안(114:4778)에 없는 개발 추가 항목이라
     칸을 더 줄 자리가 없고, 좁은 폭에서는 '재구매일 10일 이내'(143px)가 칸(1440→99px)을
     넘겨 글자가 잘렸다(1600·1440·1280 에서 29·47·65px 부족 — 옛 gap 12 에서도 같았다).
     줄을 바꾸게 두면 시안 폭 1920 에서는 한 줄 그대로이고 좁은 폭에서만 접힌다. */
  .pt-radios { display:flex; flex-wrap:wrap; gap:8px; }
  .pt-radio {
    /* flex:1 + min-width:0 이면 칸이 좁아질 때 알약이 글자보다 작아져 잘린다.
       글자폭을 지키고(shrink 0) 자리가 모자라면 위 flex-wrap 으로 줄을 바꾼다. */
    display:inline-flex; align-items:center; gap:8px; flex:1 0 auto; min-width:max-content;
    height:32px; padding:0 12px; border-radius:8px;
    background:var(--gray-0); border:1px solid var(--gray-200);
    font-size:13px; font-weight:400; line-height:21px; color:var(--gray-1000);
    text-decoration:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    transition:var(--transition);
  }
  .pt-radio:hover { border-color:var(--primary); }
  .pt-radio-dot {
    width:12px; height:12px; border-radius:999px; flex-shrink:0;
    background:var(--gray-300);
    display:inline-flex; align-items:center; justify-content:center;
  }
  .pt-radio-dot::after { content:''; width:6px; height:6px; border-radius:999px; background:var(--gray-0); }
  .pt-radio.on .pt-radio-dot { background:var(--primary); }
  /* 상세내용 탭 안 이력 카드(전체폭) — Figma 114:6131 Frame 48101490 (1536×441 · r12 · bd 1px gray-200) */
  .pt-detail { background:var(--gray-0); border:1px solid var(--border);
    border-radius:var(--radius-lg); display:flex; flex-direction:column; overflow:hidden;
    /* 이력 행 오른쪽 chevron-right 14×14(bd 1.5px gray-1000). 마크업을 안 건드리려고 mask 로 그린다 —
       전역 --icon-alert-circle 과 같은 방식이다. */
    --pt-icon-chevron: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'><path d='M7.7 4.3L16.3 12 7.7 19.7'/></svg>"); }
  /* 머리 — 시안 Frame 48101479: 1536×44 · pad 8/16 · SPACE_BETWEEN · 하단 1px gray-200.
     왼쪽 묶음(20×20 아이콘 + 이름 13/700 lh21) gap 8.
     ── 요청 1차 4쪽: '전체 상세 버튼이 앞쪽으로 오면 좋겠음 (Ex 박일성 이력 아래)' ──
     시안은 '전체 상세'를 머리줄 오른쪽 끝에 두지만(실측 x1801 · 머리 h44),
     배치는 요청서를 따라 이름 바로 아래·앞쪽(이름과 같은 x)으로 내린다.
     마크업 차례(아이콘·이름·버튼)를 한 글자도 안 건드리려고 flex 를 2행 grid 로 바꾼다 —
     1행 [아이콘 20][이름 13/700 lh21], 2행 [빈칸][전체 상세 69×28].
     세로 사이는 머리가 이미 쓰는 gap 8 을 그대로 쓴다.
     머리 높이는 8+21+8+28+8 = 73 이 되어 시안 44 보다 29 높아진다(요청서 우선). */
  .pt-detail-head { display:grid; grid-template-columns:20px 1fr; column-gap:8px; row-gap:8px;
    align-items:center; padding:8px 16px; border-bottom:1px solid var(--border); }
  .pt-detail-head > .bx { grid-column:1; grid-row:1; width:20px; height:20px; line-height:20px; text-align:center; }
  .pt-detail-head > #pdName { grid-column:2; grid-row:1; }
  /* 이름 아래 같은 칸(2열) 왼쪽 끝. 알약 폭은 시안 69 그대로 — justify-self 로 늘어나지 않게 막는다. */
  .pt-detail-head > #pdMore { grid-column:2; grid-row:2; justify-self:start; }
  /* '전체 상세' — 시안 69×28 · r8 · pad 0/12 · 12px/500 lh19 · 글자 gray-1000 · bd 1px gray-200 */
  #pdMore.btn { height:28px; padding:0 12px; border-radius:8px;
    font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000);
    background:var(--gray-0); border:1px solid var(--border);
    display:inline-flex; align-items:center; justify-content:center; }
  #pdMore.btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  /* 이력 탭 — 시안 Frame 48101484: 세그먼트 컨트롤 424×33 · r8 · pad 2 · bg gray-200,
     칸 140×29 고정 · r6 · pad 4/0 · 가운데정렬 · 칸 사이 gap 0.
     본문(pad 12/16) 안에서 첫 행과 gap 12 — 아래 .pt-pane 의 padding-top 이 그 12다. */
  .pt-detail .tab-bar { display:inline-flex; gap:0; padding:2px; border-radius:8px;
    background:var(--gray-200); border-bottom:none; margin:12px 16px 0;
    width:max-content; max-width:calc(100% - 32px); overflow-x:auto; }
  .pt-detail .tab-btn { flex:0 0 140px; width:140px; height:29px; padding:4px 0; border-radius:6px;
    font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700);
    border:none; background:none; cursor:pointer; margin-bottom:0;
    display:inline-flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; }
  .pt-detail .tab-btn:hover { color:var(--gray-1000); }
  .pt-detail .tab-btn.active { background:var(--gray-0); color:var(--gray-1000); }
  /* 건수 — 시안은 배지가 아니라 라벨과 같은 13/500 lh21 평문, 라벨과 같은 색 · gap 8 */
  .pt-detail .tab-btn .cnt { display:inline; min-width:0; height:auto; padding:0; border-radius:0;
    background:none; color:inherit; font-size:13px; font-weight:500; line-height:21px; }
  /* 본문 — 시안 Frame 48101511: pad 12/16, 안쪽 세로 gap 12 */
  .pt-pane { display:none; padding:12px 16px; overflow-y:auto; max-height:calc(100vh - 300px); }
  .pt-pane.active { display:flex; flex-direction:column; gap:12px; }
  /* 이력 행 — 시안 Frame 48101492: 1504×73 · r12 · pad 12/16 · gap 24 · bd 1px gray-200 · bg 흰색 */
  .pt-hrow { display:flex; align-items:center; gap:24px; padding:12px 16px;
    border:1px solid var(--border); border-radius:var(--radius-lg); background:var(--gray-0);
    font-size:13px; line-height:21px; cursor:pointer; }
  .pt-hrow:hover { background:var(--bg); border-color:var(--primary-200); }
  /* 안쪽 세로 gap 8 (1줄 22 + 8 + 2줄 19 = 49) */
  .pt-hrow .pt-h-main { flex:1; min-width:0; display:flex; flex-direction:column; gap:8px; }
  /* 1줄 처방전 번호 — 시안 14/700 lh22 gray-1000. JS 템플릿의 인라인 font-weight:500 을
     마크업을 건드리지 않고 덮는다. */
  .pt-hrow .pt-h-main > div:first-child { font-size:14px; font-weight:700 !important; line-height:22px; color:var(--text-primary); }
  /* 2줄 병원 · 날짜 — 시안 12/500 lh19 gray-700 */
  .pt-hrow .pt-h-sub { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-700); margin-top:0; }
  /* 오른쪽 chevron — 행이 실제로 열리는(onclick 이 붙은) 행에만 그린다 */
  .pt-hrow[onclick]::after { content:''; flex:0 0 14px; width:14px; height:14px; align-self:center;
    background-color:var(--gray-1000);
    -webkit-mask:var(--pt-icon-chevron) center / 14px 14px no-repeat;
            mask:var(--pt-icon-chevron) center / 14px 14px no-repeat; }
  .pt-empty { text-align:center; color:var(--text-muted); padding:36px 12px; font-size:12px; font-weight:400; line-height:19px; }
  /* 주의 — 이 뷰에는 .card-footer 마크업이 없는데 전역 클래스명을 재정의하고 있다.
     @stack('styles') 가 전역 <style> 뒤에 실려서, 이 화면이 열려 있는 동안
     다른 화면의 카드 푸터 배경·글자색이 함께 바뀐다. 개발 자산이라 그대로 두되
     정리 여부는 로직 담당과 상의가 필요하다. */
  .card-footer { padding:12px 18px;border-top:1px solid var(--border);background:var(--bg);border-radius:0 0 var(--radius-lg) var(--radius-lg); }
</style>
@endpush

@section('content')

{{-- 화면 제목·등록 건수. 시안에는 없지만 개발에서 넣은 블록이라 유지한다.
     '환자 추가' 버튼만 시안 위치인 결과바 우측으로 옮겼다. --}}
{{-- 아래 여백은 .page-body 의 gap 12 가 이미 만든다. margin 을 또 주면 24 가 되어
     칩줄이 검색 카드에 붙는 다른 화면들과 어긋난다(실측 24 → 12). --}}
<div style="display:flex;align-items:center;justify-content:space-between;">
  <div>
    <h5 style="font-size:16px;font-weight:700;line-height:26px;margin:0;color:var(--text-primary);">환자 정보</h5>
    <p style="font-size:12px;font-weight:400;line-height:19px;color:var(--text-muted);margin:4px 0 0;">
      총 <strong>{{ number_format($total) }}</strong>명 등록
    </p>
  </div>
</div>

{{-- 검색 필터 — Figma 114:4778: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래 --}}
<form method="GET" action="{{ route('patients.index') }}" class="ds-filter-card">
  {{-- 시안 114:4778 — 필드는 143px(9열 중 1열) 균일, 기간만 3열 --}}
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="이름 또는 전화번호">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">급여</label>
      <select name="nhis" class="form-control form-select">
        <option value="">급여 전체</option>
        <option value="1" @selected(request('nhis')==='1')>급여 대상</option>
        <option value="0" @selected(request('nhis')==='0')>비급여</option>
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">표시 건수</label>
      <select name="per_page" class="form-control form-select">
        <option value="10"  @selected(request('per_page','10')==='10')>10개씩</option>
        <option value="15"  @selected(request('per_page','10')==='15')>15개씩</option>
        <option value="30"  @selected(request('per_page','10')==='30')>30개씩</option>
      </select>
    </div>
    {{-- 기간 — 시안은 라디오 3개를 한 칸에 넣는다. 링크 이동 방식은 그대로 둔다. --}}
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">기간</label>
      <div class="pt-radios">
        @foreach([10 => '재구매일 10일 이내', 15 => '재구매일 15일 이내', 30 => '재구매일 30일 이내'] as $days => $label)
          <a href="{{ route('patients.index', array_merge(request()->except('repurchase_within','page'), ['repurchase_within' => $days])) }}"
             class="pt-radio {{ request('repurchase_within') == $days ? 'on' : '' }}">
            <span class="pt-radio-dot"></span>{{ $label }}
          </a>
        @endforeach
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request()->hasAny(['q','nhis','repurchase_within']))
      <a href="{{ route('patients.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

{{-- Figma 114:4778 — 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 탭바와 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b id="total-count">{{ number_format($total) }}</b>건</span>
      <span class="ds-grid-sel">선택 <b id="sel-count">0</b>건</span>
    </div>
    <div class="ds-grid-bar-right">
      <span class="ds-grid-hint">환자 행을 <b>더블클릭</b>하면 상세내용 탭에서 처방전·상담·구매 이력을 확인합니다.</span>
      <button type="button" class="ds-btn" onclick="window.__patientGrid?.downloadExcel()">엑셀 저장</button>
      @perm('patients', 'create')
      <button type="button" class="ds-btn ds-btn-primary" onclick="openAddModal()">환자 추가</button>
      @endperm
    </div>
  </div>

  <div class="ds-grid-card">
    {{-- 탭바는 카드 안 상단. 시안은 아이콘 없이 텍스트만 --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')">조회 결과</button>
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">상세 내용</button>
    </div>
    <div id="pnlList">
      <div id="patientGrid"></div>
    </div>

{{-- ── 상세내용 탭 — 같은 카드 안 ── --}}
<div id="pnlDetail" style="display:none;padding:16px;">
  <div style="margin-bottom:12px;">
    <button type="button" class="ds-btn" onclick="pnlShow('list')">조회결과로</button>
  </div>
  <div id="pdEmpty" class="pnl-empty">조회결과에서 환자 행을 <b>더블클릭</b>하면 이력이 여기에 표시됩니다.</div>
  <div class="pt-detail" id="patientDetail" style="display:none;">
    <div class="pt-detail-head">
      {{-- 시안은 20×20 3색 이력 일러스트(primary-200/300/500). 그 원본 자산이 없어
           개발이 넣은 boxicons 글리프를 그대로 두고 크기만 20 으로 맞췄다. --}}
      <i class="bx bx-user-pin" style="color:var(--primary);font-size:20px;"></i>
      <span id="pdName" style="font-weight:700;font-size:13px;line-height:21px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">-</span>
      {{-- 요청 1차 4쪽 '전체 상세 버튼이 앞쪽으로 오면 좋겠음 (Ex 박일성 이력 아래)' 를 따라
           이름(#pdName '박일성 이력') 바로 아래·앞쪽으로 내렸다. 자리는 CSS 의
           .pt-detail-head grid 2행이 잡는다(마크업 차례는 그대로).
           시안 114:6131 Frame 48101479 는 오른쪽 끝(SPACE_BETWEEN)이라 이 한 자리가 어긋난다.
           href 는 아래 ptLoad() 가 id 로 채운다 — 옮겨도 그대로 돈다. --}}
      <a id="pdMore" href="#" class="btn btn-outline btn-sm" style="white-space:nowrap;">전체 상세</a>
    </div>
    <div class="tab-bar">
      <button type="button" class="tab-btn active" data-tab="rx"       onclick="ptTab('rx')"><i class="fa-solid fa-file-medical"></i> 처방전 이력 <span class="cnt" id="pdCntRx">0</span></button>
      <button type="button" class="tab-btn"        data-tab="counsel"  onclick="ptTab('counsel')"><i class="fa-solid fa-comments"></i> 상담 이력 <span class="cnt" id="pdCntCs">0</span></button>
      <button type="button" class="tab-btn"        data-tab="purchase" onclick="ptTab('purchase')"><i class="fa-solid fa-cart-shopping"></i> 구매 이력 <span class="cnt" id="pdCntPu">0</span></button>
    </div>
    <div class="pt-pane active" id="pd-rx"></div>
    <div class="pt-pane" id="pd-counsel"></div>
    <div class="pt-pane" id="pd-purchase"></div>
  </div>
</div>{{-- /#pnlDetail --}}
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- 환자 추가 모달 --}}
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i>
      <h3>환자 추가</h3>
      <button onclick="closeAddModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:16px;line-height:1;padding:0;">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          {{-- 필수 표시는 전역 .form-label span 이 var(--danger) 로 그린다.
               인라인 color:red 는 그 규칙을 덮어 DS 밖 빨강이 되므로 걷어냈다. --}}
          <label class="form-label">환자명 <span>*</span></label>
          <input type="text" class="form-control" id="add-name" placeholder="홍길동" />
        </div>
        <div class="form-group">
          <label class="form-label">주민등록번호</label>
          <input type="text" class="form-control" id="add-resident" placeholder="XXXXXX-XXXXXXX" />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
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
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">휴대폰</label>
          <input type="text" class="form-control" id="add-mobile" placeholder="010-XXXX-XXXX" data-phone />
        </div>
        <div class="form-group">
          <label class="form-label">일반 전화</label>
          <input type="text" class="form-control" id="add-phone" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>
      <div class="form-group" style="margin-bottom:8px;">
        <label class="form-label">주소</label>
        <input type="text" class="form-control" id="add-address" placeholder="주소 입력" />
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">건강보험번호</label>
          <input type="text" class="form-control" id="add-insurance-no" placeholder="건강보험 번호" />
        </div>
        <div class="form-group">
          <label class="form-label">급여 적용</label>
          <select class="form-control" id="add-nhis">
            <option value="0">비급여</option>
            <option value="1">급여 대상</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:8px;">
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

{{-- ── 서명ㆍ신분증 보기 ────────────────────────────────────
     이미지는 목록에 실어 보내지 않는다(한 장에 수십 KB). 누를 때만 권한을 거쳐 불러온다. --}}
<div id="ptImgBackdrop" onclick="ptCloseImage()"></div>
<div id="ptImgModal">
  <div id="ptImgHead">
    <span id="ptImgTitle"></span>
    <a id="ptImgOpen" href="#" target="_blank" rel="noopener"
       style="font-size:11px;color:var(--primary);text-decoration:none;white-space:nowrap;">새 탭</a>
    <button type="button" onclick="ptCloseImage()"
            style="background:none;border:none;cursor:pointer;font-size:16px;line-height:1;color:var(--gray-700);">&#215;</button>
  </div>
  <div id="ptImgBody">
    <div id="ptImgLoading" style="padding:40px;font-size:13px;color:var(--gray-500);">불러오는 중...</div>
    <img id="ptImgEl" alt="" style="display:none;" />
  </div>
</div>

@endsection

@push('scripts')
<script>
/* 서명ㆍ신분증 보기 — 셀 버튼이 부른다 */
function ptShowImage(title, url) {
  document.getElementById('ptImgTitle').textContent = title;
  document.getElementById('ptImgOpen').href = url;
  const img  = document.getElementById('ptImgEl');
  const load = document.getElementById('ptImgLoading');
  img.style.display = 'none';
  load.style.display = '';
  load.textContent = '불러오는 중...';
  img.onload  = () => { load.style.display = 'none'; img.style.display = ''; };
  img.onerror = () => { load.textContent = '이미지를 불러오지 못했습니다.'; };
  img.src = url;
  document.getElementById('ptImgBackdrop').style.display = 'block';
  document.getElementById('ptImgModal').style.display    = 'block';
}

function ptCloseImage() {
  document.getElementById('ptImgBackdrop').style.display = 'none';
  document.getElementById('ptImgModal').style.display    = 'none';
  document.getElementById('ptImgEl').removeAttribute('src');
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.getElementById('ptImgModal').style.display === 'block') ptCloseImage();
});

(function () {
  const DETAIL_BASE = @json(url('patients'));
  const grid = new wwGrid({
    el: document.getElementById('patientGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,   // 시안에 하단 상태바가 없다. 전체·선택 건수는 상단 결과바로 옮겼다
    columns: [
      { header: '환자명',       name: 'name',            width: 110, sortable: true },
      { header: '주민등록번호', name: 'resident_no',     width: 130 },
      { header: '생년월일',     name: 'birth_date',      width: 160, sortable: true },
      { header: '성별',         name: 'gender',          width: 60,  align: 'center', sortable: true },
      { header: '휴대폰',       name: 'mobile',          width: 130 },
      { header: '급여',         name: 'nhis',            width: 90,  align: 'center', sortable: true },
      // ── 위임 서명 ── 가장 최근 동의 건 기준
      { header: '서명여부',   name: 'signed',   width: 90, align: 'center', sortable: true,
        renderer: (v, row) => {
          if (!v) return '';
          const el = document.createElement(row.sign_url ? 'button' : 'span');
          el.className = 'pt-chip' + (v === '동의 완료' ? ' on' : '');
          el.textContent = v;
          if (row.sign_url) {
            el.type = 'button';
            el.title = '서명 이미지 보기';
            el.classList.add('clickable');
            el.addEventListener('click', (e) => { e.stopPropagation();
              ptShowImage('위임인 서명 — ' + row.name, row.sign_url); });
          }
          return el;
        } },
      { header: '미성년',     name: 'minor',     width: 80, align: 'center', sortable: true,
        renderer: (v) => {
          if (!v) return '';
          const s = document.createElement('span');
          s.className = 'pt-chip' + (v === '미성년' ? ' warn' : '');
          s.textContent = v;
          return s;
        } },
      // 머리글이 길어져(요청 1차 4쪽) 90·100·120 으로는 잘린다 — 실측 글자폭 144·140·163·151
      // + 좌우 padding 12+12 + 정렬 화살표 자리(gap 6 + 아이콘 10.5 — 한 번이라도 정렬을
      // 누르면 모든 머리글에 ⇅ 가 붙는다) 이라 190·190·210·200 으로 넓힌다.
      // name·align 은 그대로다.
      { header: '가입자ㆍ피부양자와의 관계', name: 'g_relation', width: 190, align: 'center' },
      { header: '법정대리인 또는 가족 성명', name: 'g_name',     width: 190 },
      { header: '법정대리인 또는 가족 생년월일', name: 'g_birth', width: 210 },
      { header: '법정대리인 또는 가족 신분증', name: 'g_id', width: 200, align: 'center', exportable: false,
        renderer: (v, row) => {
          if (!row.g_id_url) return '';
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'pt-chip clickable'; b.textContent = '보기';
          b.title = '법정대리인 또는 가족 신분증 보기';
          b.addEventListener('click', (e) => { e.stopPropagation();
            ptShowImage('법정대리인 또는 가족 신분증 — ' + (row.g_name || row.name), row.g_id_url); });
          return b;
        } },

      { header: '처방건수',     name: 'rx_count',        width: 80,  editor: 'number', align: 'center', sortable: true },
      { header: '재구매일',     name: 'repurchase_date', width: 160, sortable: true },
      // 요청 1차 3쪽 '등록일 -> 신환 Master 등록일'. 시안 114:4778 은 아직 '생성일'이지만
      // 낱말은 요청서를 따른다. 같은 칸을 요청 1차 14·16쪽이 '거래처관리에서 등록일과 연결'
      // 이라 부르고 27쪽 주문 관리 목록도 '신환Master 등록일'로 적는다.
      // name 'created'(created_at) 는 그대로 두고, 머리글 실측 121.7 + padding 12+12
      // + 정렬 화살표(gap 6 + 10.5) = 162.2 라 110 → 170 으로 넓힌다.
      { header: '신환 Master 등록일', name: 'created',    width: 170, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__patientGrid = grid;
  window.dsBindSelCount(grid, 'sel-count');

  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
  // 이력 행 클릭 → 해당 상세(처방전 검수·주문)를 워크스페이스 새 탭으로 (환자 목록 유지)
  // 탭 제목은 '여는 화면' 이름으로 짓는다 — 출발 화면(거래처 관리)이 아니라 도착 화면 기준.
  const hrow = (main, sub, right, url, tabTitle) =>
    '<div class="pt-hrow" '
      + (url ? 'onclick="ceOpenTab(\'' + url + '\', \'' + (tabTitle || '상세') + '\', \'file-edit-02\')"' : '')
      + '>' +
      '<div class="pt-h-main"><div style="font-weight:500;">' + main + '</div><div class="pt-h-sub">' + sub + '</div></div>' +
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
        ? d.prescriptions.map(r => hrow(esc(r.rx_number), esc(r.hospital) + ' · ' + esc(r.date), '<span class="badge bg-label-primary">' + esc(r.status) + '</span>', r.url, '주문 - ' + esc(r.rx_number))).join('')
        : emptyBox('처방전 이력이 없습니다.');

      document.getElementById('pd-counsel').innerHTML = d.counseling.length
        ? d.counseling.map(c => hrow(esc(c.counsel_no), esc(c.rx_number) + ' · ' + esc(c.date) + (c.note ? ' · ' + esc(c.note) : ''), '', c.url, '주문 - ' + esc(c.rx_number))).join('')
        : emptyBox('상담 이력이 없습니다.');

      document.getElementById('pd-purchase').innerHTML = d.purchases.length
        ? d.purchases.map(o => hrow(esc(o.order_number), esc(o.product) + ' · ' + esc(o.date), '<div>' + Number(o.amount).toLocaleString() + '원</div><div class="pt-h-sub">' + esc(o.status) + '</div>', o.url, '주문 관리 - ' + esc(o.order_number))).join('')
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
  { selector: '.ds-filter-card', title: '환자 검색', body: '이름, 전화번호, 주민번호 앞자리로 검색합니다. 엔터 또는 검색 버튼을 누르세요.' },
  { selector: '#patientGrid', title: '환자 목록', body: '등록된 환자 목록입니다. 행을 체크한 뒤 <b>선택 상세</b> 버튼을 누르면 처방·주문 이력이 포함된 상세 화면으로 이동합니다.' },
  { selector: '[onclick="openAddModal()"]', title: '환자 신규 등록', body: '<b>환자 추가</b> 버튼을 클릭하면 이름·연락처·주민번호 등을 입력하는 등록 폼이 열립니다.' },
];
</script>
@endpush
