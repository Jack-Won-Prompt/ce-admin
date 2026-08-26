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
  /* 상세 탭은 안에 든 화면만큼만 높다. 카드가 flex:1 로 남는 자리를 다 먹고
     있어, 주문 이력 아래로 흰 바닥이 한 참 이어졌다. */
  /* 기간 두 칸은 한 줄에서 읽는다. 전역 규칙(min-width 132)으로는 258px 칸에
     두 칸이 못 들어가 뒤 칸이 아래로 떨어졌다 — 정산/회계에서 쓴 값과 같게 줄인다. */
  .ds-filter-card .ds-field-range { gap: 6px; }
  .ds-filter-card .ds-field-range input[type="date"] { min-width: 108px; padding-left: 8px; padding-right: 8px; }

  /* 어디까지가 이 화면의 몫인지 한 줄로 알린다 — 카드가 아니라 안내다 */
  .pt-scope-note   { font-size:12px; color:var(--text-muted); margin:0 0 8px; display:flex;
                     align-items:center; gap:6px; flex-wrap:wrap; }
  .pt-scope-note a { color:var(--primary); text-decoration:none; }
  .pt-scope-note a:hover { text-decoration:underline; }

  /* 상세 내용 탭도 카드가 바닥까지 내려온다. 전에는 「내용만큼만」이라 카드가 185 에서
     끝나고 그 아래 999 가 회색으로 드러났다. 판이 남는 높이를 받고, 넘치면 스스로 굴린다. */
  .ds-grid-section.is-fit .ds-grid-card { flex:1 1 auto; }
  .ds-grid-section.is-fit .ds-grid-card > #pnlDetail { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; }
  /* 액자가 제 내용만큼만 크면 그 아래 남은 판이 빈 카드처럼 따로 보인다.
     액자가 판을 채우게 두면 안쪽 문서가 이어서 바닥까지 흰색을 그린다. */
  .ds-grid-section.is-fit #pfFrame { flex:1 1 auto; min-height:0; height:auto !important; }
</style>
<style>

  /* 상담내역 탭 — 사람 이름이 붙고, 닫는 단추가 오른쪽에 붙는다 */
  /* 상담 내용 한 줄 — 글처럼 보이되 누를 수 있다. 표를 넓히지 않게 한 줄로 줄인다.
     이름을 pc-note 로 두면 판 위쪽 안내 글(.pc-note)까지 눌리므로 -cell 을 붙인다. */
  .pc-note-cell { max-width:100%; padding:0; border:none; background:none; cursor:pointer;
                  font:inherit; color:var(--text); text-align:left;
                  display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .pc-note-cell:hover { color:var(--primary); text-decoration:underline; }
  /* 펼친 전문 — 적힌 그대로, 줄바꿈까지 */
  .pc-note-meta { font-size:11px; color:var(--text-muted); margin-bottom:8px; }
  .pc-note-full { font-size:13px; line-height:1.75; color:var(--text);
                  white-space:pre-wrap; word-break:break-word; }

  /* 「관리」 칸의 펼침 단추 — 표 안에서 알약보다 작고 조용하게 */
  .pc-manage { width:24px; height:24px; border-radius:6px; border:1px solid var(--gray-200);
               background:var(--gray-0); color:var(--gray-600); cursor:pointer;
               display:inline-flex; align-items:center; justify-content:center; font-size:10px; }
  .pc-manage:hover { border-color:var(--primary); color:var(--primary); }

  .pnl-tab-closable { display: inline-flex; align-items: center; gap: 6px; }
  .pnl-tab-x { font-size: 15px; line-height: 1; color: var(--text-muted); cursor: pointer;
               padding: 0 2px; border-radius: 4px; }
  .pnl-tab-x:hover { background: var(--alert-100); color: var(--alert-500); }
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
  /* 표 안 상태 배지 규격은 시안이 pad 2/6 · r6 · 11/500 이다(148:7122 「주문 대기」 53×22).
     화면 곳곳의 .impact-badge · .rx-status 도 같은 값인데 이것만 알약(r999) 에 700 이었다. */
  /* 시안 배지(148:7122 「주문 대기」 53×22)에는 테두리가 없다 — 바탕색이 구분을 나른다.
     테두리 1px 이 있으면 22 가 아니라 24 가 되어 그 행만 2 두꺼워진다.
     같은 화면 안의 .impact-badge · .rx-status 도 테두리 없이 22 다. */
  .pt-chip { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px;
             font-size:11px; font-weight:500; line-height:18px; white-space:nowrap;
             background:var(--gray-100); color:var(--gray-600); border:none; }
  .pt-chip.on   { background:var(--primary-50); color:var(--primary); }
  .pt-chip.warn { background:var(--alert-50);   color:var(--alert-500); }
  /* 누를 수 있는 것은 색이 바뀌는 것으로 알린다(테두리를 주면 그 배지만 2 두꺼워진다) */
  button.pt-chip.clickable { cursor:pointer; }
  button.pt-chip.clickable:hover { background:var(--primary-100); color:var(--primary); }

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

  /* 알약 셋이 span-3 한 칸을 나눠 쓴다. 글자에서 「재구매일」을 걷어내 셋이 한 줄에
     들어간다(각 96 안팎 + gap 8). 그래도 모자라는 폭에서만 줄을 바꾼다. */
  .pt-radios { display:flex; flex-wrap:wrap; gap:8px; }
  .pt-radio {
    /* flex:1 + min-width:0 이면 칸이 좁아질 때 알약이 글자보다 작아져 잘린다.
       글자폭을 지키고(shrink 0) 자리가 모자라면 위 flex-wrap 으로 줄을 바꾼다. */
    display:inline-flex; align-items:center; gap:6px; flex:0 0 auto; min-width:max-content;
    height:32px; padding:0 10px; border-radius:8px;
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
    border-radius:var(--radius-lg); display:flex; flex-direction:column; overflow:hidden; }
  /* 좌우 여백은 카드 안 표준 16 — 아래 탭바(pad 0/16)·본문과 글자 시작선을 맞춘다 */
  .pt-detail-head { display:flex; align-items:center; gap:8px; padding:11px 16px; border-bottom:1px solid var(--border); }
  /* 상세 카드 안 탭바 — 전역 .pnl-tabs 와 같은 규격(h44 · pad 0/16 · gap 16 · 탭 13/500 lh21) */
  .pt-detail .tab-bar { display:flex; gap:16px; border-bottom:1px solid var(--border); padding:0 16px; overflow-x:auto; }
  .pt-detail .tab-btn { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; color:var(--text-muted);
    border:none; background:none; cursor:pointer; border-bottom:1px solid transparent; margin-bottom:-1px;
    display:inline-flex; align-items:center; gap:6px; white-space:nowrap; }
  .pt-detail .tab-btn:hover { color:var(--primary); }
  .pt-detail .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
  /* 건수 배지 — 시안 16×16 정원 · 10px/700 */
  .pt-detail .tab-btn .cnt { display:inline-flex; align-items:center; justify-content:center;
    min-width:16px; height:16px; padding:0 4px; border-radius:999px;
    background:var(--bg); color:var(--text-secondary); font-size:10px; font-weight:700; line-height:12px; }
  /* 이력은 그대로 늘어나게 둔다. 안쪽에 스크롤을 두면 화면 스크롤과 둘이 되어
     어느 쪽을 굴려야 할지 모르고, 짧은 이력에도 잘린 것처럼 보였다. */
  .pt-pane { display:none; padding:8px 16px 16px; }
  .pt-pane.active { display:block; }
  .pt-hrow { display:flex; align-items:center; gap:10px; padding:9px 4px; border-bottom:1px solid var(--border-light); font-size:13px; line-height:21px; cursor:pointer; }
  .pt-hrow:last-child { border-bottom:none; }
  .pt-hrow:hover { background:var(--bg); border-radius:6px; }
  .pt-hrow .pt-h-main { flex:1; min-width:0; }
  .pt-hrow .pt-h-sub { font-size:11px; font-weight:500; line-height:18px; color:var(--text-muted); margin-top:2px; }
  .pt-empty { text-align:center; color:var(--text-muted); padding:36px 12px; font-size:12px; font-weight:400; line-height:19px; }

  /* ── '환자 추가' 는 채운 주요 버튼 ── 시안 114:4778 icon-header-setting:
     73×32 · r8 · pad 0/12 · bg #28798B · 글자 13/500 #FFFFFF.
     전역 .ds-btn-primary 는 흰 바탕 + primary 테두리(외곽선)라 결과바 안에서만 덮는다.
     같은 화면의 '검색'(시안 60×32 · 흰 바탕 · bd 1px #28798B · 글자 #28798B)은
     외곽선이 맞으므로 .ds-filter-actions 쪽은 건드리지 않는다.
     /messages 의 '조건 전체 발송' 과 같은 손질이다 — 전역으로 올릴지는 판단 대기.
     이 뷰의 밀어넣은 스타일은 다른 화면이 탭으로 열려 있는 동안에도 살아 있으므로
     (바로 아래 .card-footer 주의 참고) 그 버튼 하나만 집도록 좁혀 쓴다. */
  .ds-grid-bar .ds-btn-primary[onclick="openAddModal()"] {
    background: var(--primary); border-color: var(--primary); color: var(--gray-0);
  }
  .ds-grid-bar .ds-btn-primary[onclick="openAddModal()"]:hover {
    background: var(--primary-dark); border-color: var(--primary-dark); color: var(--gray-0);
  }

  /* 주의 — 이 뷰에는 .card-footer 마크업이 없는데 전역 클래스명을 재정의하고 있다.
     @stack('styles') 가 전역 <style> 뒤에 실려서, 이 화면이 열려 있는 동안
     다른 화면의 카드 푸터 배경·글자색이 함께 바뀐다. 개발 자산이라 그대로 두되
     정리 여부는 로직 담당과 상의가 필요하다. */
  .card-footer { padding:12px 18px;border-top:1px solid var(--border);background:var(--bg);border-radius:0 0 var(--radius-lg) var(--radius-lg); }
</style>
@endpush

@section('content')

{{-- 제목과 등록 건수를 여기 두지 않는다. 화면 이름은 네비바가 이미 적고 있고,
     건수는 아래 결과바의 「전체 N건」과 같은 말이었다. --}}

{{-- 이 화면이 다루는 거래처는 개인(환자)뿐이다. 병원ㆍ기관은 담는 항목도 하는 일도
     달라 마스터 관리에 따로 있다 — 찾는 사람이 헤매지 않게 길을 적어 둔다. --}}
<div class="pt-scope-note">
  개인(환자) 거래처만 다룹니다.
  <a href="{{ route('masters.index') }}" onclick="event.preventDefault(); ceOpenTab(this.href, '마스터 관리');">병원ㆍ기관은 마스터 관리에서</a>
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
      {{-- 「표시 건수」 칸은 두지 않는다. 목록은 wwGrid 가 한 번에 다 받아 그리고
           (컨트롤러가 ->get() 으로 통째로 넘긴다) 페이지를 나누지 않는다 —
           이 칸은 아무 일도 하지 않으면서 「10개씩」이라 적어 거짓을 말하고 있었다. --}}
    {{-- 생성일자 — 한쪽만 채워도 걸린다(언제부터만ㆍ언제까지만 찾는 일이 잦다) --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">생성일자</label>
      <div class="ds-field-range">
        <input type="date" name="created_from" value="{{ request('created_from') }}" class="form-control">
        <span class="filter-sep ds-field-sep">~</span>
        <input type="date" name="created_to" value="{{ request('created_to') }}" class="form-control">
      </div>
    </div>
    {{-- 생년 — 네 자리 연도. 같은 이름이 여럿일 때 이것으로 가른다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">생년</label>
      <input type="number" name="birth_year" value="{{ request('birth_year') }}" class="form-control"
             placeholder="1984" min="1900" max="{{ date('Y') }}" step="1">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">건보 위임 종료일</label>
      <div class="ds-field-range">
        <input type="date" name="agree_end_from" value="{{ request('agree_end_from') }}" class="form-control">
        <span class="filter-sep ds-field-sep">~</span>
        <input type="date" name="agree_end_to" value="{{ request('agree_end_to') }}" class="form-control">
      </div>
    </div>
    {{-- 사업부는 검색 칸에 두지 않는다(요청). 목록 맨 앞 칸에서 눈으로 가른다.
         거르는 길은 서버에 그대로 있어 ?care_type=IC 로 들어오면 걸린다. --}}
    {{-- 상병타입 — 환자에 붙는 구분이다. 선택지는 주문 등록 화면의 「구분(SB/SCI)」과 같다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">상병타입</label>
      <select name="sb_sci" class="form-control form-select">
        <option value="">전체</option>
        <option value="SB"  @selected(request('sb_sci') === 'SB')>SB</option>
        <option value="SCI" @selected(request('sb_sci') === 'SCI')>SCI</option>
      </select>
    </div>
    {{-- 「아니오」는 「이어진 동의서가 없다」는 뜻이다. 개인정보 동의서는 밖에서 들어오는
         폼이라 이름ㆍ전화로 이어 두는데, 못 이은 것은 없는 것으로 보인다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">개인정보 동의</label>
      <select name="privacy_consent" class="form-control form-select">
        <option value="">전체</option>
        <option value="y" @selected(request('privacy_consent') === 'y')>동의</option>
        <option value="n" @selected(request('privacy_consent') === 'n')>없음</option>
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">공단 위임장 동의</label>
      <select name="nhis_consent" class="form-control form-select">
        <option value="">전체</option>
        <option value="y" @selected(request('nhis_consent') === 'y')>동의</option>
        <option value="n" @selected(request('nhis_consent') === 'n')>없음</option>
      </select>
    </div>
    {{-- 재구매일 — 시안은 라디오 3개를 한 칸에 넣는다. 링크 이동 방식은 그대로 둔다.
         「재구매일」을 알약마다 되풀이하니 셋이 한 줄에 들어가지 못했다 — 라벨이 이미
         그 말을 하고 있어 알약에서는 뺀다. --}}
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">재구매일</label>
      <div class="pt-radios">
        @foreach([10 => '10일 이내', 15 => '15일 이내', 30 => '30일 이내'] as $days => $label)
          <a href="{{ route('patients.index', array_merge(request()->except('repurchase_within','page'), ['repurchase_within' => $days])) }}"
             class="pt-radio {{ request('repurchase_within') == $days ? 'on' : '' }}">
            <span class="pt-radio-dot"></span>{{ $label }}
          </a>
        @endforeach
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    {{-- 초기화 — 시안 114:4778 은 검색 왼쪽(x1773)에 늘 세워 둔다. 검색 조건이 있을 때만
         내보내던 조건을 걷었다. 링크는 그대로 이 화면의 라우트로 되돌아간다. --}}
    <a href="{{ route('patients.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 통화를 받으며 여는 자리다. 목록에서 한 사람을 체크하고 누른다 —
         상담내역 칸의 「상담하기」까지 두 걸음 들어가지 않아도 된다. --}}
    <button type="button" class="ds-btn" onclick="ptCounsel()">상담하기</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__patientGrid?.downloadExcel()">엑셀 저장</button>
    @perm('patients', 'create')
    <button type="button" class="ds-btn ds-btn-primary" onclick="openAddModal()">거래처 등록</button>
    @endperm
  </div>
</form>

{{-- Figma 114:4778 — 흰 카드(r12) 안에 탭바와 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 탭바는 카드 안 상단. 시안은 아이콘 없이 텍스트만 --}}
    <div class="pnl-tabs">
      <button type="button" id="pnlBtnList" class="pnl-tab active" onclick="pnlShow('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b id="total-count">{{ number_format($total) }}</b>건)</span></button>
      {{-- 상세는 하나다. 이력만 간추린 판과 전체 상세를 따로 두었더니, 열어 보고 나서
           「여기 말고 저기」를 한 번 더 눌러야 했다. 환자 한 사람의 모든 것을 이 탭에서 본다. --}}
      <button type="button" id="pnlBtnDetail" class="pnl-tab" onclick="pnlShow('detail')">상세 내용</button>
    </div>
    <div id="pnlList">
      <div id="patientGrid"></div>
    </div>

{{-- ── 상세 내용 탭 — 환자 상세 화면을 그대로 들여온다 ──
     상세 화면은 이미 한 벌 있다. 두 벌로 만들면 한쪽만 고쳐져 서로 다른 것을 보여 준다.
     액자 안에서는 사이드바·네비가 스스로 숨는다(is-framed). --}}
<div id="pnlDetail" style="display:none;">
  <div id="pfEmpty" class="pnl-empty">조회결과에서 환자 행을 <b>더블클릭</b>하면 여기에 나옵니다.</div>
  {{-- 높이는 안에 들어온 화면이 정한다 — 고정값을 주었더니 내용이 짧은 사람은
       목록 아래로 흰 바닥이 한 참 남고, 긴 사람은 액자 안에서 또 스크롤해야 했다. --}}
  {{-- 높이는 CSS 가 판에 맞춘다. pfFit 은 판보다 내용이 길 때만 늘린다. --}}
  <iframe id="pfFrame" title="환자 상세"
          style="display:none;width:100%;border:0;vertical-align:top;"></iframe>
</div>{{-- /#pnlDetail --}}

{{-- 상담내역 탭은 사람마다 하나씩 만들어 붙인다(pcEnsureTab) — 두 사람을 견주며
     일하는 때가 있어 한 자리를 돌려 쓰면 방금 보던 것이 사라진다. --}}
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- 거래처 등록 모달 --}}
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i>
      <h3>거래처 등록</h3>
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
          <label class="form-label">휴대폰</label>
          <input type="text" class="form-control" id="add-mobile" placeholder="010-XXXX-XXXX" data-phone />
        </div>
      </div>
      <div class="form-grid-2" style="margin-bottom:8px;">
        <div class="form-group">
          <label class="form-label">일반 전화</label>
          <input type="text" class="form-control" id="add-phone" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>
      {{-- 주소는 주문 등록·환자 상세와 같은 구성이다 — 우편번호·도로명은 찾아서 채우고
           상세만 사람이 적는다. 한 칸에 몰아 적으면 주문 낼 때 다시 갈라야 한다. --}}
      <div class="form-group" style="margin-bottom:8px;">
        <label class="form-label">주소</label>
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
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
            style="display:flex;align-items:center;justify-content:center;width:24px;height:24px;flex-shrink:0;padding:0;border:none;border-radius:6px;background:none;font-size:16px;line-height:1;cursor:pointer;color:var(--gray-500);">&#215;</button>
  </div>
  <div id="ptImgBody">
    <div id="ptImgLoading" style="padding:40px;font-size:13px;color:var(--gray-500);">불러오는 중...</div>
    <img id="ptImgEl" alt="" style="display:none;" />
  </div>
</div>

{{-- 상담 창 — 거래처 관리와 같은 창이다(partials/counsel-window) --}}
@include('partials.counsel-window')

@endsection

@push('scripts')
{{-- 주문 등록·환자 상세와 같은 카카오(다음) 우편번호 서비스 --}}
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
  function addFindAddress() {
    if (typeof daum === 'undefined' || !daum.Postcode) {
      showToast('주소 찾기를 불러오지 못했습니다.', 'warning');
      return;
    }
    const W = 500, H = 600;
    new daum.Postcode({
      width: W, height: H,
      oncomplete: function (data) {
        document.getElementById('add-postcode').value = data.zonecode;
        document.getElementById('add-address').value  = data.roadAddress || data.jibunAddress;
        const detail = document.getElementById('add-address-detail');
        detail.value = '';
        detail.focus();
      },
    }).open({
      left: Math.floor((window.screen.width  - W) / 2),
      top:  Math.floor((window.screen.height - H) / 2),
    });
  }
</script>
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
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },   // 시안에 하단 상태바가 없다. 전체·선택 건수는 상단 결과바로 옮겼다
    columns: [
      { header: '사업부',     name: 'care_type',       width: 70, align: 'center', sortable: true },
      { header: '이름',       name: 'name',            width: 110, sortable: true },
      { header: '주민등록번호', name: 'resident_no',     width: 130 },
      { header: '생년월일',     name: 'birth_date',      width: 160, sortable: true },
      /* 성별 자리에 상담내역을 둔다. 성별은 훑을 때 쓰는 값이 아니고(필요하면 상세에 있다),
         목록에서 바로 하고 싶은 일은 「이 환자와 무슨 이야기를 했나」를 보는 것이다. */
      { header: '상담내역', name: 'counsel', width: 90, align: 'center', exportable: false,
        renderer: (v, row) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'pt-chip clickable';
          b.textContent = '상담내역';
          b.title = '이 환자의 상담 이력을 봅니다';
          b.addEventListener('click', (e) => { e.stopPropagation(); pcLoad(row.id, row.name, row.mobile); });
          return b;
        } },
      { header: '휴대폰',       name: 'mobile',          width: 130 },
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

  /* 찾는 자리의 「상담하기」 — 체크해 둔 한 사람과 상담한다.

     창은 주문 등록에서 여는 것과 같은 것이다(partials/counsel-window). 지난 상담을
     먼저 표로 보여 주고, 줄을 고르면 그 상담을 이어 적고, 「신규로 상담하기」면
     새로 시작한다. 두 화면이 같은 창을 쓰므로 물어보는 것이 어디서나 같다. */
  window.ptCounsel = function () {
    const rows = grid.getCheckedRows?.() ?? [];
    if (!rows.length)    { showToast('상담할 거래처를 목록에서 체크하십시오.', 'warning'); return; }
    if (rows.length > 1) { showToast('한 사람만 체크하십시오. 상담은 사람에게 답니다.', 'warning'); return; }

    const r = rows[0];
    csOpen(r.id, r.name, r.mobile);
  };

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

  // 패널 탭 전환(조회 결과 · 상세 내용 · 상담내역)
  /* 상담내역 탭이 사람마다 생기고 없어지므로 목록은 고정이 아니다 */
  const PANES = { list: 'pnlList', detail: 'pnlDetail' };
  const TABS  = { list: 'pnlBtnList', detail: 'pnlBtnDetail' };

  window.pnlShow = function (which) {
    if (!PANES[which]) which = 'list';
    Object.keys(PANES).forEach(k => {
      document.getElementById(PANES[k]).style.display = k === which ? '' : 'none';
      document.getElementById(TABS[k]).classList.toggle('active', k === which);
    });
    /* 목록은 화면을 가득 채워야 한 줄이라도 더 보이지만, 상세는 안에 든
       화면만큼만 높으면 된다 — 늘려 두면 그만큼 흰 바닥이 아래에 남는다. */
    document.querySelector('.ds-grid-section')?.classList.toggle('is-fit', which === 'detail');
  };

  /* 상세를 옆 탭에 들여온다. 다른 화면으로 건너가면 어떤 조건으로 찾고 있었는지가
     끊기고, 돌아오려면 처음부터 다시 찾아야 한다. */
  let _pfId = null;

  window.ptOpen = function (id) {
    if (id) _pfId = id;
    if (!_pfId) { pnlShow('detail'); return; }

    const frame = document.getElementById('pfFrame');
    const url   = DETAIL_BASE + '/' + _pfId;
    if (frame.dataset.url !== url) {
      frame.src = url;
      frame.dataset.url = url;
    }
    frame.style.display = 'block';   // inline 이면 글자 밑줄 자리만큼 아래가 뜨기게 된다
    document.getElementById('pfEmpty').style.display = 'none';
    pnlShow('detail');
  };

  /* 액자 안에서 다른 화면으로 건너가는 링크를 그대로 두면 그 작은 액자 안에 통째로
     열려 화면이 겹친다(환자 상세 안에 처방전 목록이 들어앉는 식이다).
     환자 목록으로 가는 링크는 바깥의 조회 결과 탭으로 돌리고, 그 밖의 화면은
     워크스페이스 탭으로 올려 보낸다. 같은 곳에서 온 문서라 안을 만질 수 있다. */
  /* 액자 높이를 안의 내용에 맞춴 둔다. 탭을 옥기거나 고치기로 들어가면
     안의 키가 바뀌므로, 한 번 재는 것으로는 모자란다 — 계속 따라간다. */
  /* 액자는 판을 채우는 것이 기본이다(CSS flex). 안쪽 내용이 판보다 길 때만 그만큼 늘려
     안팎으로 스크롤이 겹치지 않게 한다. */
  function pfFit(frame) {
    const d = frame.contentDocument;
    if (!d || !d.body) return;
    const h = Math.max(d.body.scrollHeight, d.documentElement.scrollHeight);
    const pane = frame.parentElement;
    const avail = pane ? pane.clientHeight : 0;
    frame.style.minHeight = (h && h > avail) ? h + 'px' : '';
  }

  document.getElementById('pfFrame').addEventListener('load', function () {
    const frameUrl = this.dataset.url;
    const frame    = this;
    try {
      const d = this.contentDocument;
      if (!d) return;

      pfFit(frame);
      if (window.ResizeObserver) {
        new ResizeObserver(() => pfFit(frame)).observe(d.body);
      }
      d.defaultView.addEventListener('resize', () => pfFit(frame));

      d.addEventListener('click', (ev) => {
        const a = ev.target.closest('a[href]');
        if (!a || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;

        const raw = a.getAttribute('href');
        if (!raw || raw.startsWith('#') || raw.startsWith('javascript')) return;
        if (!a.href.startsWith(location.origin)) return;      // 바깥 주소는 그대로 둔다
        if (a.href.split('#')[0] === frameUrl) return;        // 자기 자신이면 그대로

        ev.preventDefault();

        if (a.href.replace(/\/$/, '') === DETAIL_BASE.replace(/\/$/, '')) {
          pnlShow('list');
          return;
        }
        window.ceOpenTab(a.href, a.dataset.ceTab || a.textContent.trim(), a.dataset.ceIcon || '');
      });
    } catch (e) { /* 다른 곳에서 온 문서면 만지지 않는다 */ }
  });

  /* ── 상담내역 탭 ──────────────────────────────────────
     상담은 「이 사람과 언제 무슨 이야기를 했나」를 훑는 일이라 목록이 맞다.

     탭은 사람마다 하나씩 열린다. 두 사람을 견주며 일하는 때가 있어 한 자리를 돌려
     쓰면 방금 보던 것이 사라진다. 이미 열려 있는 사람을 다시 누르면 그 탭으로 간다 —
     같은 사람의 탭이 둘이 되면 어느 것이 최신인지 알 수 없다. */
  const pcTabs = {};     // { [환자id]: { name, mobile, grid, wired } }
  /* 상담 창은 따로 사는 조각이라(partials/counsel-window) 이 안을 들여다볼 수 없다.
     창이 「지금 보고 있는 사람」과 그 통화번호를 물어볼 수 있게 내어 둔다. */
  window.pcTabs = pcTabs;

  /* 목록에서 고친다 — 이어 둔 뒤에도 바꿀 수 있어야 한다 */
  window.csEditOrder = function (btn, counselId, patientId) {
    ordPick(btn, patientId, async (order) => {
      try {
        const res = await apiRequest(`${BASE_URL}/counsels/${counselId}/order`, 'PATCH',
                                     { counsel_order_id: order ? order.id : null });
        if (!res.success) throw new Error(res.message || '바꾸지 못했습니다.');
        showToast(res.message, 'success');
        const p = pcActive();
        if (p) pcLoad(p.id, p.name);
      } catch (e) {
        showToast('바꾸지 못했습니다: ' + (e.message || ''), 'danger', 5000);
      }
    });
  };



  /** 지금 보고 있는 상담내역 탭의 환자 */
  window.pcActive = pcActive;
  function pcActive() {
    const key = Object.keys(PANES).find(k => k.startsWith('counsel:')
      && document.getElementById(TABS[k])?.classList.contains('active'));
    if (!key) return null;
    const id = key.slice('counsel:'.length);
    return { id, name: pcTabs[id]?.name || '' };
  }

  /** 그 환자의 탭과 판을 만든다(이미 있으면 그대로 쓴다) */
  function pcEnsureTab(id, name) {
    const key = 'counsel:' + id;
    if (PANES[key]) return key;

    const tabId  = 'pnlBtnCounsel-' + id;
    const paneId = 'pnlCounsel-' + id;

    const tab = document.createElement('button');
    tab.type = 'button';
    tab.id   = tabId;
    tab.className = 'pnl-tab pnl-tab-closable';
    tab.onclick = () => pnlShow(key);
    tab.innerHTML = `<span class="pnl-tab-label"></span>`;

    // 닫는 단추 — 탭을 여는 길이 있으면 닫는 길도 있어야 한다
    const x = document.createElement('span');
    x.className = 'pnl-tab-x';
    x.title = '탭 닫기';
    x.textContent = '×';
    x.onclick = (e) => { e.stopPropagation(); pcCloseTab(id); };
    tab.appendChild(x);

    document.querySelector('.pnl-tabs').appendChild(tab);

    const pane = document.createElement('div');
    pane.id = paneId;
    pane.style.cssText = 'display:none;padding:16px;';
    pane.innerHTML = `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
        <i class="bx bx-conversation" style="color:var(--primary);font-size:16px;"></i>
        <span class="pc-name" style="font-weight:700;font-size:14px;"></span>
        <span class="ds-grid-hint pc-note" style="margin-left:auto;"></span>
        {{-- 상담은 전화를 받으며 적는 일이라, 보던 목록을 두고 창으로 띄운다 --}}
        <button type="button" class="ds-btn ds-btn-primary pc-new">상담하기</button>
      </div>
      <div class="pnl-empty pc-empty">불러오는 중…</div>
      <div class="pc-grid" style="display:none;"></div>`;
    document.querySelector('.ds-grid-card').appendChild(pane);
    pane.querySelector('.pc-new').onclick = () => csOpen(id, pcTabs[id]?.name);

    PANES[key] = paneId;
    TABS[key]  = tabId;
    pcTabs[id] = { name, mobile: '', grid: null, wired: false };

    return key;
  }

  function pcCloseTab(id) {
    const key = 'counsel:' + id;
    document.getElementById(TABS[key])?.remove();
    document.getElementById(PANES[key])?.remove();
    delete PANES[key];
    delete TABS[key];
    delete pcTabs[id];
    // 보고 있던 탭을 닫았으면 조회 결과로 돌아간다
    if (!document.querySelector('.pnl-tab.active')) pnlShow('list');
  }

  window.pcLoad = async function (id, name, mobile) {
    const key  = pcEnsureTab(id, name);
    if (mobile) pcTabs[id].mobile = mobile;   // 상담 창의 통화번호 기본값
    const pane = document.getElementById(PANES[key]);
    const tab  = document.getElementById(TABS[key]);

    pcTabs[id].name = name || pcTabs[id].name;
    tab.querySelector('.pnl-tab-label').textContent = '상담내역 - ' + (pcTabs[id].name || '');
    pane.querySelector('.pc-name').textContent = (pcTabs[id].name || '') + ' 상담내역';
    pane.querySelector('.pc-note').textContent = '불러오는 중…';
    pnlShow(key);

    try {
      const res = await fetch(DETAIL_BASE + '/' + id + '/histories',
                              { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const d = await res.json();

      /* 상담번호와 내용은 각자 칸이다. 한 칸에 「#번호 날짜 : 내용」으로 붙여 두었더니
         번호로 찾을 수도, 내용으로 훑을 수도 없었다 — 정렬도 번호 순으로만 걸렸다.
         나머지는 언제·어디까지·무슨 갈래·누가 순으로 둔다. */
      const rows = (d.counseling ?? []).map(c => ({
        note:    c.note || '',
        date:    c.date || '',
        status:  c.status || '',
        re_date: c.re_date || '',
        type:    c.type || '',
        call_no: c.call_no || '',
        order_no: c.order_no || '',
        order_id: c.order_id || null,
        counsel_id: c.key,
        counsel_no: c.counsel_no || '',
        by:      c.by || '',
        url:     c.url || '',
      }));

      if (d.name) {
        pcTabs[id].name = d.name;
        tab.querySelector('.pnl-tab-label').textContent = '상담내역 - ' + d.name;
        pane.querySelector('.pc-name').textContent = d.name + ' 상담내역';
      }

      const gridEl  = pane.querySelector('.pc-grid');
      const emptyEl = pane.querySelector('.pc-empty');

      if (!rows.length) {
        gridEl.style.display  = 'none';
        emptyEl.style.display = '';
        emptyEl.textContent   = '상담 이력이 없습니다.';
        pane.querySelector('.pc-note').textContent = '0건';
        return;
      }

      emptyEl.style.display = 'none';
      gridEl.style.display  = '';
      pane.querySelector('.pc-note').textContent =
        rows.length + '건 · 행을 더블클릭하면 그 처방전을 새 탭에서 엽니다.';

      if (!pcTabs[id].grid) {
        pcTabs[id].grid = new wwGrid({
          el: gridEl,
          height: 'auto', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
          columns: [
            { header: '상담번호',  name: 'counsel_no', width: 150, sortable: true },
            /* 상담 내용은 길다(최대 2000자). 표에서는 한 줄로 줄여 두고, 누르면
               전문을 팝오버로 편다 — 표를 넓히지 않고도 읽을 수 있어야 한다. */
            { header: '상담 내용', name: 'note',      width: 360, sortable: true,
              renderer: (v, row) => {
                const txt = (v || '').trim();
                if (!txt) {
                  const s = document.createElement('span');
                  s.textContent = '-';
                  s.style.color = 'var(--text-muted)';
                  return s;
                }
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'pc-note-cell';
                b.title = '눌러서 전문 보기';
                b.textContent = txt;
                b.addEventListener('click', (e) => { e.stopPropagation(); pcNote(b, row); });
                return b;
              } },
            { header: '상담일시',  name: 'date',      width: 110, sortable: true, align: 'center' },
            { header: '상태',      name: 'status',    width: 80,  sortable: true, align: 'center' },
            { header: '재상담일',  name: 're_date',   width: 100, sortable: true, align: 'center' },
            /* 「갈래」라고 묶어 두었더니 무엇을 담은 칸인지 이름만으로 서지 않았다.
               상담 유형과 통화번호는 다른 것이므로 각자 칸을 준다. */
            { header: '상담 유형', name: 'type',      width: 90,  sortable: true, align: 'center' },
            { header: '통화번호',  name: 'call_no',   width: 130, sortable: true },
            /* 이어 둔 주문. 잘못 이었으면 그 자리에서 다시 고른다 — 이력을 열어 놓고
               고칠 수 있어야 「이 상담이 무슨 건이었나」가 맞아 간다. */
            { header: '주문번호', name: 'order_no', width: 160, sortable: true, exportable: true,
              renderer: (v, row) => {
                const wrap = document.createElement('span');
                wrap.style.cssText = 'display:inline-flex;align-items:center;gap:6px;';
                const txt = document.createElement('span');
                txt.textContent = v || '연결 안 됨';
                if (!v) txt.style.color = 'var(--text-muted)';
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'pt-chip clickable';
                b.textContent = v ? '변경' : '연결';
                b.addEventListener('click', (e) => { e.stopPropagation();
                  csEditOrder(b, row.counsel_id, pcActive()?.id); });
                wrap.append(txt, b);
                return wrap;
              } },
            { header: '담당자',    name: 'by',        width: 90,  sortable: true },
            /* 한 줄에 할 수 있는 일은 이 칸에 모은다. 칸마다 단추를 흩어 두면
               표가 넓어지고, 쓰지 않는 날에도 자리를 차지한다. */
            { header: '관리',      name: 'manage',    width: 70,  align: 'center',
              renderer: (v, row) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'pc-manage';
                b.title = '이 상담으로 할 일';
                b.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
                b.addEventListener('click', (e) => { e.stopPropagation(); pcManage(b, row); });
                return b;
              } },
          ],
          data: rows,
        });

        /* 더블클릭하면 그 처방전으로 간다. 화면 탭으로 열어야 보고 있던 목록이 남는다.
           wwGrid 에는 on() 이 없어 셀에서 행 번호를 읽는다. */
        gridEl.addEventListener('dblclick', (e) => {
          const cell = e.target.closest('[data-row-index]');
          if (!cell) return;
          const row = pcTabs[id].grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
          if (row?.url) window.ceOpenTab(row.url, '상담 - ' + (row.order_no || ''), 'bx-conversation');
        });
      } else {
        pcTabs[id].grid.setData(rows);
      }
    } catch (e) {
      const gridEl  = pane.querySelector('.pc-grid');
      const emptyEl = pane.querySelector('.pc-empty');
      gridEl.style.display  = 'none';
      emptyEl.style.display = '';
      emptyEl.textContent   = '상담내역을 불러오지 못했습니다.';
      pane.querySelector('.pc-note').textContent = '';
    }
  };

  /* 상세(액자) 안에서 「상담내역」을 누르면 이 화면의 탭을 연다 */
  window.addEventListener('message', (e) => {
    if (e.origin !== location.origin) return;
    if (e.data?.source !== 'ce-patient' || e.data?.action !== 'counsel') return;
    pcLoad(e.data.id, e.data.name);
  });

  /* 주소에 counsel=환자 가 붙어 들어오면 그 사람의 상담내역부터 편다 —
     상세 화면을 혼자 열어 두고 상담내역을 누른 경우다. */
  (function () {
    const id = new URLSearchParams(location.search).get('counsel');
    if (id) pcLoad(id, '');
  })();

  /* 한 줄로 할 수 있는 일 — 누른 자리 옆에 붙는다.
     상태 값을 여기서 바꿀지는 아직 정하지 않았다. 지금은 이미 있는 두 가지만 건다. */
  const _pcManageModal = new GridModal();

  /* 상담 내용 전문 — 표의 한 줄을 눌러 그 자리에서 편다.
     화면 탭으로 처방전을 여는 것과 다르다. 「무슨 이야기였나」만 확인하려는 것이라,
     보던 목록을 두고 떠날 이유가 없다. */
  const _pcNoteModal = new GridModal();

  window.pcNote = function (btn, row) {
    _pcNoteModal.open({
      title: '상담 내용' + (row.counsel_no ? ' · ' + row.counsel_no : ''),
      width: 420, height: 360, mode: 'popover', anchor: btn,
      render: (body) => {
        body.innerHTML = '';

        // 언제·어디까지 왔나 — 전문을 읽기 전에 눈이 먼저 닿는 자리
        const meta = document.createElement('div');
        meta.className = 'pc-note-meta';
        meta.textContent = [row.date, row.status, row.type, row.call_no]
          .filter(Boolean).join(' · ') || '';
        if (meta.textContent) body.appendChild(meta);

        const txt = document.createElement('div');
        txt.className = 'pc-note-full';
        // 적힌 그대로 보인다 — 줄바꿈이 뜻을 나르는 때가 많다
        txt.textContent = row.note || '';
        body.appendChild(txt);
      },
    });
  };

  window.pcManage = function (btn, row) {
    const p = pcActive();
    _pcManageModal.open({
      title: '상담 관리', width: 240, height: 200, mode: 'popover', anchor: btn,
      items: [
        { value: 'order', label: row.order_no ? '주문 연결 변경' : '주문 연결',
          sub: row.order_no || '이은 주문이 없습니다' },
        { value: 'open',  label: '상담 건 열기', sub: '그 처방전을 화면 탭으로 엽니다' },
      ],
      onConfirm: (v) => {
        if (v === 'order') { csEditOrder(btn, row.counsel_id, p?.id); return; }
        if (v === 'open' && row.url) {
          window.ceOpenTab(row.url, '주문 - ' + (row.order_no || row.counsel_no || ''), 'file-edit-02');
        }
      },
    });
  };

  /* 상담 창이 저장하면 그 사람의 탭을 새로 읽는다 — 방금 적은 상담이 거기 보여야 한다 */
  window.addEventListener('message', (e) => {
    if (e.origin !== location.origin) return;
    if (e.data?.source !== 'ce-counsel' || e.data?.action !== 'saved') return;
    const p = pcActive();
    if (p) pcLoad(p.id, p.name);
  });

  // 행 더블클릭 → 그 환자의 상세를 옆 탭에 연다
  document.getElementById('patientGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row && row.id) ptOpen(row.id);
  });
})();
</script>
<script>
  // ── 모달 ──────────────────────────────────────────────
  function openAddModal()  { document.getElementById('addModal').classList.add('show'); }
  function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

  async function savePatient() {
    const name = document.getElementById('add-name').value.trim();
    if (!name) { showToast('이름은 필수입니다.', 'warning'); return; }

    const btn = document.getElementById('btn-add-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      care_type:           document.getElementById('add-care-type').value            || null,
      resident_no:         document.getElementById('add-resident').value.trim()     || null,
      birth_date:          document.getElementById('add-birth').value               || null,
      gender:              document.getElementById('add-gender').value               || null,
      mobile:              document.getElementById('add-mobile').value.trim()        || null,
      phone:               document.getElementById('add-phone').value.trim()         || null,
      address:             document.getElementById('add-address').value.trim()       || null,
      postcode:            document.getElementById('add-postcode').value.trim()      || null,
      address_detail:      document.getElementById('add-address-detail').value.trim()|| null,
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
  { selector: '[onclick="openAddModal()"]', title: '거래처 신규 등록', body: '<b>거래처 등록</b> 버튼을 누르면 이름·연락처·주민번호 등을 적는 폼이 열립니다.' },
];
</script>
@endpush
