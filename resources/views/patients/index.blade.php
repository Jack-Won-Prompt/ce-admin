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
  /* 껍데기에는 여백을 주지 않는다 — 그만큼 폭이 더 필요해져 두 칸이 아래로 접힌다.
     달력 아이콘 자리(오른쪽 26)는 안쪽 칸이 이미 비켜 두었다. */
  .ds-filter-card .ds-field-range .ce-date-wrap { min-width: 108px; }
  .ds-filter-card .ds-field-range .ce-date-wrap > input[data-ce-date] { padding-left: 8px; }

  /* 단추 묶음은 둘째 줄에 남은 다섯 열을 받아 오른쪽 끝에 선다 */
  .ds-filter-card .ds-filter-fields > .ds-filter-actions { grid-column: span 5; }
  /* 그 「오른쪽 끝」은 카드의 끝이어야 한다. 격자를 시안 폭 1384 로 묶어 두면
     넓은 화면(1600)에서 오른쪽에 88 이 남아, 그 안에 앉은 단추가 카드 끝에
     닿지 못하고 아래 목록의 오른쪽 끝과도 어긋났다. 카드 폭을 다 쓴다. */
  .ds-filter-card .ds-filter-fields { width: 100%; }

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

  {{-- 재구매일 알약(.pt-radios/.pt-radio)은 고르는 칸으로 바뀌어 쓰지 않는다 --}}
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
  /* 이은 주문번호 — 글자 그대로 보이되 누르면 그 주문 등록 화면이 열린다 */
  .pc-order-no { padding:0; border:none; background:none; font:inherit; color:var(--primary);
                 cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
  .pc-order-no:hover { color:var(--primary-dark); }

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

{{-- 「개인(환자) 거래처만 다룹니다」 안내는 걷었다. 화면 이름과 담긴 것이 이미
     그 말을 하고 있어, 볼 때마다 한 줄을 읽히고 자리를 차지할 뿐이었다.
     마스터 관리로 가는 길은 왼쪽 메뉴에 그대로 있다. --}}


{{-- 검색 필터 — Figma 114:4778: 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래 --}}
<form method="GET" action="{{ route('patients.index') }}" class="ds-filter-card">
  {{-- 시안 114:4778 — 필드는 143px(9열 중 1열) 균일, 기간만 3열 --}}
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
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
    {{-- 환자구분 — 환자에 붙는 값이다. 선택지는 주문 등록 화면의 것과 같다(Patient::SB_SCI).
         거르는 값에는 옛 SB·SCI 도 둔다 — 이미 그렇게 적힌 사람을 찾을 수 없으면
         「유지한다」고 해 놓고 못 보게 막는 셈이다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">환자구분</label>
      <select name="sb_sci" class="form-control form-select">
        <option value="">전체</option>
        @foreach(array_merge(\App\Models\Patient::SB_SCI, ['SB', 'SCI']) as $v)
          <option value="{{ $v }}" @selected(request('sb_sci') === $v)>{{ $v }}</option>
        @endforeach
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
      <label class="ds-field-label">건보 위임장 동의</label>
      <select name="nhis_consent" class="form-control form-select">
        <option value="">전체</option>
        <option value="y" @selected(request('nhis_consent') === 'y')>동의</option>
        <option value="n" @selected(request('nhis_consent') === 'n')>없음</option>
      </select>
    </div>
    {{-- 재구매일 — 알약 셋 대신 고르는 칸 하나로 둔다. 알약은 누르는 즉시 화면이
         옮겨 가서, 옆의 다른 조건을 적어 두었어도 그것만으로 다시 찾아 왔다.
         이제 다른 칸과 함께 「검색」으로 걸린다. --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">재구매일</label>
      <select name="repurchase_within" class="form-control form-select">
        <option value="">전체</option>
        @foreach([10 => '10일 이내', 15 => '15일 이내', 30 => '30일 이내'] as $days => $label)
          <option value="{{ $days }}" @selected((string) request('repurchase_within') === (string) $days)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    {{-- 단추 묶음도 격자 안에 둔다. 밖에 두면 카드가 줄바꿈으로 배치하는 탓에
         늘 셋째 줄로 접혔다 — 위의 고르는 칸 넷을 한 열씩으로 줄여 비운 다섯 열에
         앉히면 찾는 자리가 두 줄로 끝난다. --}}
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
  {{-- 높이는 CSS 가 잡는다 — 판을 채우고, 안쪽이 길면 안에서 굴린다. --}}
  <iframe id="pfFrame" title="환자 상세"
          style="display:none;width:100%;border:0;vertical-align:top;"></iframe>
</div>{{-- /#pnlDetail --}}

{{-- 상담내역 탭은 사람마다 하나씩 만들어 붙인다(pcEnsureTab) — 두 사람을 견주며
     일하는 때가 있어 한 자리를 돌려 쓰면 방금 보던 것이 사라진다. --}}
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- 거래처 등록ㆍ수정 창 — 주문 등록 화면과 함께 쓴다 --}}
@include('patients._editor-modal')

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
      /* 생년월일 셋 — 위드웍스 표는 「1982. 11. 11.」, 우리 표는 「1982-11-11」, 해만
         맞춰 보는 자리도 있다. 만 나이는 요청대로 따로 세운다(요청서 2쪽). */
      { header: '생년월일(1)', name: 'birth_dotted',   width: 120, align: 'center', sortable: true },
      { header: '생년월일(2)', name: 'birth_iso',      width: 110, align: 'center', sortable: true },
      { header: '생년',        name: 'birth_year',     width: 70,  align: 'center', sortable: true },
      { header: '나이',        name: 'age',            width: 80,  align: 'center' },
      { header: '연락 상태',   name: 'contact_status', width: 110, align: 'center', sortable: true },
      { header: '연락 선호 방식', name: 'contact_channel', width: 140, align: 'center', sortable: true },
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
      // 요청서 3쪽 «휴대폰->전화번호1으로 변경 / 전화번호2 추가»
      { header: '전화번호1',   name: 'mobile',   width: 130 },
      { header: '전화번호2',   name: 'phone2',   width: 130 },
      { header: 'Email',       name: 'email',    width: 180 },
      { header: 'Fax',         name: 'fax',      width: 120 },
      { header: '주소',        name: 'address',  width: 280 },
      // 이 주소가 언제부터인가 — 이력의 맨 윗줄이 적힌 날이다
      { header: '주소 변경일', name: 'address_at', width: 110, align: 'center', sortable: true },
      { header: '송금자명',    name: 'remitter', width: 100 },
      { header: '현금영수증',  name: 'deduction', width: 100, align: 'center', sortable: true },
      { header: '현금영수증 번호', name: 'cash_receipt_no', width: 130 },
      { header: '환자구분',     name: 'sb_sci',  width: 110, align: 'center', sortable: true },
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
      {
        /* 의료용품 구입 확인서 — 거래처 본인이 달라고 할 때 내준다(요청서 회신).
           목록에서 곧바로 내려받는다. 문자ㆍ메일로 보내는 것은 상세에서 한다 —
           밖으로 나가는 것이라 누구에게 가는지를 보고 눌러야 한다. */
        header: '구입 확인서', name: 'purchase_confirm', width: 110, align: 'center',
        sortable: false, exportable: false,
        renderer: (v, row) => {
          const a = document.createElement('a');
          a.href = `${DETAIL_BASE}/${row.id}/purchase-confirm`;
          a.className = 'ds-btn';
          a.style.cssText = 'height:24px;padding:0 8px;font-size:11px;';
          a.textContent = '내려받기';
          a.addEventListener('click', (e) => e.stopPropagation());
          return a;
        },
      },
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

      // ── 공단ㆍ기초 (요청서 3쪽) ──
      { header: '건보등록',     name: 'nhis_reg',       width: 110, align: 'center', sortable: true },
      { header: '건보등록일',   name: 'nhis_reg_date',  width: 110, align: 'center', sortable: true },
      { header: '건보재등록 대상자', name: 'nhis_renew', width: 140, align: 'center' },
      { header: '건보재등록 기한',   name: 'nhis_renew_due', width: 130, align: 'center', sortable: true },
      { header: '건보위임동의 시작일', name: 'agree_start', width: 140, align: 'center', sortable: true },
      { header: '건보위임동의 종료일', name: 'agree_end',   width: 140, align: 'center', sortable: true },
      { header: '기초(의료급여) 재평가 대상자', name: 'basic_reeval', width: 200, align: 'center' },
      { header: '기초(의료급여) 재평가 기한',   name: 'basic_due',    width: 190, align: 'center', sortable: true },

      { header: '처방건수',     name: 'rx_count',        width: 80,  editor: 'number', align: 'center', sortable: true },
      { header: '재구매일',     name: 'repurchase_date', width: 160, sortable: true },
      { header: '메모',         name: 'memo',            width: 240 },
      // 요청 1차 3쪽 '등록일 -> 신환 Master 등록일'. 시안 114:4778 은 아직 '생성일'이지만
      // 낱말은 요청서를 따른다. 같은 칸을 요청 1차 14·16쪽이 '거래처관리에서 등록일과 연결'
      // 이라 부르고 27쪽 주문 관리 목록도 '신환Master 등록일'로 적는다.
      // name 'created'(created_at) 는 그대로 두고, 머리글 실측 121.7 + padding 12+12
      // + 정렬 화살표(gap 6 + 10.5) = 162.2 라 110 → 170 으로 넓힌다.
      { header: '신환 Master 등록일', name: 'created',    width: 170, sortable: true },
      // 누가 만들고 누가 마지막으로 고쳤는가 (요청서 2쪽)
      { header: '등록자',   name: 'creator', width: 90,  align: 'center', sortable: true },
      { header: '수정자',   name: 'updater', width: 90,  align: 'center', sortable: true },
      { header: '수정일자', name: 'updated', width: 140, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  window.__patientGrid = grid;

  /* ── 거래처가 고쳐지면 이 목록도 따라온다 ─────────────────
     고치는 자리는 상세 화면이다(이 화면의 「상세 내용」 탭도 그 화면을 액자로 들인다).
     거기서 저장해도 뒤에 있는 이 목록은 옛 값을 그대로 보여 주고 있어, 담당자가 화면을
     다시 열어야 방금 고친 것이 보였다 — 그러면 찾아 둔 조건도 열어 둔 상담내역 탭도
     함께 사라졌다.
     줄만 새로 받는다. 찾는 조건은 지금 주소에 그대로 실려 있으므로 그대로 얹는다. */
  async function ptReloadRows() {
    const url = new URL(location.href);
    url.searchParams.set('json', '1');
    try {
      const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      if (!Array.isArray(data?.rows)) return;
      grid.setData(data.rows);
      const cnt = document.getElementById('total-count');
      if (cnt) cnt.textContent = Number(data.total ?? data.rows.length).toLocaleString('ko-KR');
    } catch (e) {
      console.error('[거래처] 목록을 다시 읽지 못했습니다', e);
    }
  }

  try {
    const _ptCh = new BroadcastChannel('ce-patient');
    _ptCh.onmessage = (e) => {
      if (e.data?.action !== 'saved') return;
      ptReloadRows();
      /* 그 사람의 상담내역 탭을 열어 두었으면 이름도 이력도 다시 읽는다 —
         탭 이름에 옛 이름이 남아 있으면 어느 사람의 탭인지 어긋난다. */
      const id = e.data.id;
      if (typeof pcTabs !== 'undefined' && pcTabs[id]) {
        pcLoad(id, e.data.name || pcTabs[id].name);
      }
    };
  } catch (e) { /* 못 하는 브라우저면 예전처럼 화면을 다시 열어야 한다 */ }
  window.dsBindSelCount(grid, 'sel-count');

  /* 찾는 자리의 「상담하기」 — 체크해 둔 한 사람과 상담한다.

     창은 주문 등록에서 여는 것과 같은 것이다(partials/counsel-window). 지난 상담을
     먼저 표로 보여 주고, 줄을 고르면 그 상담을 이어 적고, 「신규 상담」이면
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
  /* 액자 높이는 CSS 가 잡는다 — 판을 채우고, 안쪽이 길면 안에서 굴린다
     (.ds-grid-section.is-fit #pfFrame).

     예전에는 여기서 안쪽 키를 재어 액자를 늘렸다(pfFit). 안쪽 문서가 제 내용만큼만
     크던 시절에는 그것이 맞았는데, 상세 화면이 「내용이 짧아도 바닥까지」 채우도록
     바뀌면서 서로 쫓는 꼴이 됐다 —

       액자가 커진다 → 안쪽 문서가 그만큼 늘어난다 → 잰 키가 더 커진다 → 액자를 더
       늘린다 → …

     ResizeObserver 가 그 한 바퀴마다 다시 불려 화면이 끝없이 떨렸다. 두 쪽이 서로
     「상대만큼 커지겠다」고 하면 멈출 자리가 없다. 재는 쪽을 걷어 한 방향으로 만든다. */

  document.getElementById('pfFrame').addEventListener('load', function () {
    const frameUrl = this.dataset.url;
    const frame    = this;
    try {
      const d = this.contentDocument;
      if (!d) return;

      // 지난 화면에서 늘려 둔 값이 남아 있으면 걷는다
      frame.style.minHeight = '';

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

  /* 화면 탭 이름 — 붙일 번호가 하나도 없으면 꼬리표(「- 」)를 달지 않는다.
     상담은 상담번호가 먼저다. 주문에 이어 두지 않은 상담이 대부분이라
     주문번호로 지으면 거의 모든 탭이 「상담 - 」로 끝났다. */
  function pcTabLabel(prefix, row) {
    /* 서버가 빈 상담번호를 「-」로 채워 보낸다(histories) — 그대로 쓰면 「상담 - -」가
       된다. 번호가 아닌 것은 없는 것으로 본다. counsel_id 는 처방번호다. */
    const clean = (v) => { const t = String(v ?? '').trim(); return (t && t !== '-') ? t : ''; };
    const no = clean(row?.counsel_no) || clean(row?.order_no)
            || clean(row?.counsel_id) || clean(row?.rx_number);
    return no ? prefix + ' - ' + no : prefix;
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
        order_url: c.order_url || '',
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
            /* 이어 둔 주문 — 상담일시 바로 다음이다. 「언제 무슨 건으로 이야기했나」가
               한 눈에 이어져 읽힌다. 번호를 누르면 그 주문을 만든 주문 등록 화면이
               탭으로 열리고, 잘못 이었으면 옆 단추로 그 자리에서 다시 고른다. */
            { header: '주문번호', name: 'order_no', width: 160, sortable: true, exportable: true,
              renderer: (v, row) => {
                const wrap = document.createElement('span');
                wrap.style.cssText = 'display:inline-flex;align-items:center;gap:6px;';
                let txt;
                if (v && row.order_url) {
                  txt = document.createElement('button');
                  txt.type = 'button';
                  txt.className = 'pc-order-no';
                  txt.title = '주문 등록 화면 열기';
                  txt.textContent = v;
                  txt.addEventListener('click', (e) => { e.stopPropagation();
                    window.ceOpenTab(row.order_url, '주문 - ' + v, 'file-edit-02'); });
                } else {
                  txt = document.createElement('span');
                  txt.textContent = v || '연결 안 됨';
                  if (!v) txt.style.color = 'var(--text-muted)';
                }
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'pt-chip clickable';
                b.textContent = v ? '변경' : '연결';
                b.addEventListener('click', (e) => { e.stopPropagation();
                  csEditOrder(b, row.counsel_id, pcActive()?.id); });
                wrap.append(txt, b);
                return wrap;
              } },
            { header: '상태',      name: 'status',    width: 80,  sortable: true, align: 'center' },
            { header: '재상담일',  name: 're_date',   width: 100, sortable: true, align: 'center' },
            /* 「갈래」라고 묶어 두었더니 무엇을 담은 칸인지 이름만으로 서지 않았다.
               상담 유형과 통화번호는 다른 것이므로 각자 칸을 준다. */
            { header: '상담 유형', name: 'type',      width: 90,  sortable: true, align: 'center' },
            { header: '통화번호',  name: 'call_no',   width: 130, sortable: true },
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
          /* 상담 창을 그 자리에서 편다. 예전에는 주문 등록 화면을 새 탭으로 열었는데,
             한 줄을 고른 사람의 뜻은 「이 통화를 이어 적겠다」이지 「이 주문을 보겠다」가
             아니었다 — 주문을 볼 길은 이 창의 「주문 연결」과 주문번호 칸에 따로 있다. */
          const p = pcActive();
          if (row) window.csOpenFor(p?.id ?? pcTabs[id]?.id ?? id,
                                    p?.name ?? pcTabs[id]?.name ?? '',
                                    pcTabs[id]?.mobile ?? '',
                                    row.counsel_id || row.counsel_no || '');
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
        { value: 'open',  label: '상담 건', sub: '그 처방전을 화면 탭으로 엽니다' },
      ],
      onConfirm: (v) => {
        if (v === 'order') { csEditOrder(btn, row.counsel_id, p?.id); return; }
        if (v === 'open' && row.url) {
          window.ceOpenTab(row.url, pcTabLabel('주문', row), 'file-edit-02');
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
