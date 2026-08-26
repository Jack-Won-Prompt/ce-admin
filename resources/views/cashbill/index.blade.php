{{-- resources/views/cashbill/index.blade.php --}}
@extends('layouts.app')

@section('title', '현금영수증 발행')
{{-- 시안 324:4656 — 제목 '현금영수증', 브레드크럼은 '홈 - 현금영수증' (구분자가 '-' 다) --}}
@section('page-title', '현금영수증')
@section('breadcrumb', '홈 - 현금영수증')

@section('help-title', '현금영수증 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>팝빌 API를 통해 현금영수증을 즉시 발행하고, 발행 내역을 조회·취소하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">거래 유형</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-100);color:var(--primary-600);"><i class="bx bx-check"></i></div><div class="help-item-text"><b>승인거래</b> — 일반 현금결제 시 발행</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--danger-light);color:var(--danger);"><i class="bx bx-x"></i></div><div class="help-item-text"><b>취소거래</b> — 기발행 현금영수증 취소</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">사용 용도</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);"><i class="bx bx-user"></i></div><div class="help-item-text"><b>소득공제용</b> — 개인 소비자 (주민번호·휴대폰번호)</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--gray-100);color:var(--gray-700);"><i class="bx bx-buildings"></i></div><div class="help-item-text"><b>지출증빙용</b> — 사업자 (사업자등록번호)</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">국세청 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">전송전</span>
    <span class="badge badge-info">전송중</span>
    <span class="badge badge-success">전송성공</span>
    <span class="badge badge-danger">전송실패</span>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* ── 탭바 ── */
  /* 시안 324:4656 Frame 48101484 — 탭줄은 그리드 카드 안 첫 줄이다(카드 밖에 뜬 띠가 아니다).
     1568×44 · pad 0/16 · 탭 사이 8 · 아래 1px gray-200. 탭 pad 0/8 · 13/500 lh21 · 활성 밑줄 1px primary */
  .titab-bar { display:flex; align-items:center; gap:8px; padding:0 16px;
    border-bottom:1px solid var(--border); flex-wrap:wrap; flex-shrink:0; }
  .titab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
    color:var(--text-muted); border-bottom:1px solid transparent; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
  .titab:hover { color:var(--primary); }
  .titab.active { color:var(--primary); border-bottom-color:var(--primary); }
  .titab i { font-size:16px; }
  /* 카드 머리줄('발행 내역')은 탭 이름과 같은 글자라 줄을 접고,
     그 줄에만 있던 '마지막 동기화: …' 를 탭줄 오른쪽 끝으로 옮겼다. */
  .titab-bar .sync-badge { margin-left:auto; }

  /* 탭 본문 두 장은 카드 안에서 남은 높이를 채운다.
     tiTab() 이 활성 쪽 style.display 를 빈 값으로 되돌리므로 여기 값이 산다. */
  .ds-grid-card > [data-titab] { display:flex; flex-direction:column; flex:1 1 auto; min-height:0; }

  /* 결과바로 옮긴 '팝빌 동기화' 는 처리 중 비활성이 된다.
     전역 .ds-btn 에는 비활성 상태 규칙이 없어 이 화면에서 얹는다(전역에 필요). */
  .ds-btn:disabled { opacity:.6; cursor:not-allowed; }
  /* 결과바에 버튼이 5개라 좁은 폭에서는 한 줄에 들어가지 않는다 */
  @media(max-width:1280px){ .ds-grid-bar { height:auto; flex-wrap:wrap; gap:8px; } }
  /* 결과바 '선택 N건' — 전역에는 .ds-grid-total/.ds-grid-hint 만 있고 이 규칙이 없다(전역에 필요) */

  /* ── 요약 카드 ── */
  /* 시안 324:4656 Frame 48101550 — 카드 4장이 아니라 한 장이다.
     1568×75 · r12 · pad 12/0 · bg 흰색. 안에 폭이 같은 열 4개(h51 · pad 4/12 · gap 2 · 가운데 정렬)와
     열 사이 세로선 1px gray-200(0×51). */

  /* ── 즉시발행 탭 ── */
  /* 시안 324:6158 Frame 48101521 — 탭 본문 pad 16 · gap 24.
     위쪽에 504 폭 카드 3장(gap 12 · r12 · pad 12/16 · 안쪽 gap 12 · bd 1px gray-200), 아래에 버튼 줄 36. */
  .cb-issue { padding:16px; gap:24px; overflow-y:auto; }
  .cb-fcards { display:flex; align-items:stretch; gap:12px; flex-wrap:wrap; }
  .cb-fcard { flex:1 1 320px; min-width:0; display:flex; flex-direction:column; gap:12px;
    padding:12px 16px; border:1px solid var(--gray-200); border-radius:12px; background:var(--gray-0); }
  .cb-fcard-head { display:flex; align-items:center; gap:8px; min-height:28px; }
  .cb-fcard-title { font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000); }
  /* 시안 카드 제목이 '총 금액(자동계산)' 이라 자리를 내준 '금액 입력 *' 라벨은 같은 줄 오른쪽에 남긴다 */
  .cb-fcard-note { margin-left:auto; font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .cb-frows { display:flex; flex-direction:column; gap:8px; }

  /* 라벨은 가로 배치다 — 라벨 100 · gap 8 · 컨트롤이 나머지 (시안 472 = 100 + 8 + 364).
     입력은 전역 .form-control(h32 · r8 · pad 5/12 · 13/400) 을 그대로 쓴다. */
  .cb-frow { display:flex; align-items:flex-start; gap:8px; }
  .cb-frow > .ds-field-label { flex:0 0 100px; min-width:0; height:32px;
    display:flex; align-items:center; line-height:16px; }
  .cb-fctrl { flex:1 1 auto; min-width:0; display:flex; align-items:center; gap:8px; }
  .cb-fctrl.col { flex-direction:column; align-items:stretch; }
  .cb-fctrl > .form-control { flex:1 1 auto; min-width:0; }
  .cb-fctrl > .ds-btn { flex-shrink:0; }

  /* 라벨 위·입력 아래로 쌓는 세로 배치 — 취소 모달이 쓴다 */
  .form-row { display:flex; flex-direction:column; gap:8px; }

  /* 금액 — 시안 232×85 타일 4개가 2×2(gap 8). 앞 셋은 bg gray-100, 합계는 primary-light */
  .amount-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
  .amount-tile { display:flex; flex-direction:column; gap:8px; padding:12px; border-radius:8px; background:var(--gray-100); }
  .amount-tile .at-row { display:flex; align-items:center; gap:8px; }
  .amount-tile .at-row .form-control { flex:1 1 auto; min-width:0; }
  .amount-tile .at-unit { flex-shrink:0; font-size:13px; font-weight:500; line-height:21px; color:var(--gray-1000); }
  .amount-total-row {
    display:flex; flex-direction:column; gap:8px;
    padding:12px; background:var(--primary-light); border-radius:8px;
  }
  .amount-total-row .at-label { font-size:13px; font-weight:500; line-height:21px; color:var(--primary); }
  .amount-total-row .at-val   { font-size:13px; font-weight:500; line-height:21px; color:var(--primary); text-align:right; }

  /* 신분확인번호 타입 — 시안은 라디오 알약 116×32 · r8 · pad 0/12 · gap 8 · bd 1px gray-200.
     선택돼도 배경은 흰색 그대로고 앞 점(12×12, 안에 흰 점 6×6)만 primary 로 찬다. */
  /* 좁은 폭에서는 알약이 62px 까지 눌려 '휴대폰번호' 가 글자 한두 자만 남았다.
     줄바꿈을 허용하고 최소 폭을 글자 폭으로 잡아 낱말이 잘리지 않게 한다.
     시안 폭(컨트롤 364)에서는 세 알약이 116 씩 한 줄에 그대로 들어간다. */
  .id-type-tabs { display:flex; flex-wrap:wrap; gap:8px; }
  .id-type-tab {
    flex:1 1 0; min-width:fit-content; height:32px; padding:0 12px;
    display:inline-flex; align-items:center; gap:8px;
    border:1px solid var(--gray-200); border-radius:8px;
    background:var(--gray-0); font-size:13px; font-weight:400; line-height:21px; cursor:pointer; color:var(--gray-1000);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    transition:var(--transition);
  }
  .id-type-tab::before {
    content:''; flex-shrink:0; width:12px; height:12px; border-radius:999px;
    background:var(--gray-300) radial-gradient(circle at center, var(--gray-0) 3px, rgba(0,0,0,0) 3px);
  }
  .id-type-tab:hover { border-color:var(--primary); }
  .id-type-tab.active { background:var(--gray-0); color:var(--gray-1000); border-color:var(--gray-200); }
  .id-type-tab.active::before { background-color:var(--primary); }

  /* 발행 버튼 — 폼 하단 주 동작(시안 h36 · r8 · pad 0/16 · 14/500) */
  .cb-issue-actions { display:flex; align-items:center; gap:8px; }
  .cb-issue-actions .issue-btn { flex:1 1 auto; }
  .issue-btn {
    height:36px; padding:0 16px; background:var(--primary); color:var(--gray-0); border:none; border-radius:8px;
    font-size:14px; font-weight:500; line-height:22px; cursor:pointer; display:flex; align-items:center;
    justify-content:center; gap:8px; transition:var(--transition);
  }
  .issue-btn:hover:not(:disabled) { background:var(--primary-dark); }
  .issue-btn:disabled { opacity:.6; cursor:not-allowed; }

  /* ── 목록 패널 ── */
  /* 카드 껍데기는 전역 .ds-grid-card 를 쓴다. 시안은 탭줄 아래가 바로 표이고
     표가 카드 바닥(페이저 위)까지 찬다 — wwGrid 가 인라인으로 박는 고정 높이를 되돌린다. */
  .cb-pane-hist > #cbHistGrid { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; }
  .cb-pane-hist #cbHistGrid > .cg-wrap { flex:1 1 auto; min-height:0; }
  .sync-badge { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }

  /* 조회·동기화 버튼은 검색 카드와 결과바의 .ds-btn 으로 옮겼다.
     아래 두 규칙은 개발 자산이라 남기되 버튼 규격(h32 · r8 · pad 5/12 · 13/500)에 맞춰 둔다. */
  .btn-search { height:32px; padding:5px 12px; background:var(--primary); color:var(--gray-0); border:none; border-radius:8px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; white-space:nowrap; }
  .btn-search:hover { background:var(--primary-dark); }
  .btn-sync { height:32px; padding:5px 12px; background:var(--gray-0); color:var(--primary); border:1px solid var(--primary); border-radius:8px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; }
  .btn-sync:hover:not(:disabled) { background:var(--primary-light); }
  .btn-sync:disabled { opacity:.6; cursor:not-allowed; }

  /* 목록 표 — 지금 마크업은 wwGrid 를 쓴다. 개발 자산이라 규칙만 남기고 글자 규격을 맞춘다. */
  .hist-table { width:100%; border-collapse:collapse; font-size:13px; }
  .hist-table th { padding:10px 12px; background:var(--gray-100); font-weight:700; font-size:13px; line-height:21px; color:var(--gray-700); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
  .hist-table td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
  .hist-table tr:last-child td { border-bottom:none; }
  .hist-table tr:hover td { background:rgba(40,121,139,.03); }
  .hist-empty { padding:40px; text-align:center; color:var(--gray-500); font-size:13px; line-height:21px; }

  /* 상태 배지 — r6 · pad 2/6 · 11px/500 · lh18 */
  .cb-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; }
  .cb-badge.issued  { background:var(--primary-light); color:var(--primary); }
  .cb-badge.cancel  { background:var(--alert-100);    color:var(--alert-500); }
  .cb-badge.draft   { background:var(--gray-100);     color:var(--gray-500); }
  .cb-badge.nts-ok  { background:var(--primary-light); color:var(--primary); }
  .cb-badge.nts-err { background:var(--alert-100);    color:var(--alert-500); }
  .cb-badge.income  { background:var(--primary-light); color:var(--primary); }
  .cb-badge.expense { background:var(--gray-100);     color:var(--gray-700); }
  .cb-badge.src-popbill { background:var(--primary-100); color:var(--primary-600); }
  .cb-badge.src-order   { background:var(--gray-100);    color:var(--gray-700); }

  /* 액션 버튼 — 전역 헤더 아이콘 버튼(.btn-icon 32×32) 과 이름이 겹치므로
     영수증 화면 안쪽으로 한정한다. 작은 버튼 규격 h28 · r8 · pad 3/10 · 13/500. */
  .cbv-layout .btn-icon {
    /* 전역 .btn-icon 은 32×32 정사각형이다. width 를 되돌리지 않으면
       글자가 든 이 버튼(인쇄)이 32px 에 갇혀 내용이 삐져나온다. */
    width:auto; height:28px; padding:3px 10px; font-size:13px; font-weight:500; line-height:20px; border:none;
    border-radius:8px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; gap:6px;
  }
  .cbv-layout .btn-icon.view    { background:var(--primary-light); color:var(--primary); }
  .cbv-layout .btn-icon.print   { background:var(--gray-100);  color:var(--gray-700); }
  .cbv-layout .btn-icon.cancel  { background:var(--alert-100); color:var(--alert-500); }
  .cbv-layout .btn-icon:hover   { filter:brightness(.93); }

  /* 페이지네이션 — 그리드 카드 하단 줄. 시안 1568×52 · pad 12 · 위 1px gray-200,
     버튼 묶음은 오른쪽 끝. 버튼 28×28 · r6 · bd 1px gray-200 · 13/500 · 사이 6,
     현재 쪽은 bg primary-light · 글자 primary (단색 채움이 아니다). */
  .hist-pager { padding:12px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0; }
  .pager-info { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .pager-btns { display:flex; gap:6px; margin-left:auto; }
  .pager-btn { height:28px; min-width:28px; padding:0 6px; border:1px solid var(--gray-200); border-radius:6px; background:var(--gray-0); font-size:13px; font-weight:500; line-height:21px; cursor:pointer; color:var(--gray-1000); transition:var(--transition); }
  .pager-btn:hover { border-color:var(--primary); color:var(--primary); }
  .pager-btn.active { background:var(--primary-light); color:var(--primary); border-color:var(--gray-200); }
  .pager-btn:disabled { opacity:.4; cursor:not-allowed; }

  /* ── 모달 ── */
  .nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal { background:var(--gray-0); border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.18); width:640px; max-width:92vw; max-height:88vh; display:flex; flex-direction:column; }
  /* 시안 165:1316 — 모달 머리 pad 16/24 · gap 12 */
  .nd-modal-head { padding:16px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .nd-modal-head h3 { flex:1; font-size:14px; font-weight:700; line-height:22px; margin:0; }
  .nd-modal-close { display:flex; align-items:center; justify-content:center; width:24px; height:24px; flex-shrink:0; padding:0; border:none; border-radius:6px; background:none; font-size:16px; line-height:1; color:var(--gray-500); cursor:pointer; }
  .nd-modal-body  { padding:24px; overflow-y:auto; flex:1; }

  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
  .detail-item .di-label { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-bottom:4px; }
  .detail-item .di-val   { font-size:13px; font-weight:500; line-height:21px; }
  .detail-item.full { grid-column:1/-1; }
  .detail-sep { grid-column:1/-1; border:none; border-top:1px dashed var(--border); }
  .detail-amount { grid-column:1/-1; background:var(--primary-light); border-radius:8px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
  .detail-amount .da-label { font-size:13px; font-weight:500; line-height:21px; color:var(--primary); }
  .detail-amount .da-val   { font-size:16px; font-weight:700; line-height:22px; color:var(--primary); }

  /* 취소 모달 */
  .cancel-note { background:var(--alert-50); border-radius:8px; padding:12px; font-size:12px; font-weight:400; line-height:19px; color:var(--alert-500); margin-bottom:12px; display:flex; gap:8px; }

  /* ── 현금영수증 영수증 뷰 ── */
  .cbv-layout { font-size:13px; color:var(--gray-1000); }
  .cbv-header { text-align:center; padding:12px 0 10px; border-bottom:2px solid var(--gray-1000); margin-bottom:0; }
  .cbv-header p { font-size:16px; font-weight:700; line-height:26px; margin:0; letter-spacing:1px; }
  .cbv-body { padding:0; }
  .cbv-body table { width:100%; border-collapse:collapse; margin-bottom:0; }
  .cbv-body table td {
    padding:7px 10px; border:1px solid var(--gray-200); vertical-align:middle; font-size:12px; line-height:19px;
  }
  .cbv-body table td:first-child,
  .cbv-body table td:nth-child(3) { background:var(--gray-100); font-weight:700; color:var(--gray-700); white-space:nowrap; }
  .cbv-red { color:var(--alert-500); font-weight:700; }
  .cbv-sub-row { display:flex; border-bottom:1px solid var(--gray-200); border-top:1px solid var(--gray-200); }
  .cbv-sub-title { flex:1; padding:6px 10px; font-weight:700; font-size:12px; line-height:19px; background:var(--gray-50); }
  .cbv-sub-title + .cbv-sub-title { border-left:1px solid var(--gray-200); }
  .cbv-footer { border-top:1px solid var(--gray-200); padding:10px 12px; font-size:11px; font-weight:400; line-height:18px; color:var(--gray-600); }
  .cbv-footer p { margin:0; }
  .cbv-print-row { padding:12px 0 0; display:flex; gap:8px; justify-content:flex-end; }
</style>
@endpush

@section('content')

{{-- 요약 4칸(잔여 포인트·이번 달 발행·취소·합계금액)은 두지 않는다. 화면을 열 때마다
     팝빌을 두 번 더 부르면서도 그 숫자로 하는 일이 없었다 — 발행과 취소는 아래에서 한다.
     포인트가 필요하면 팝빌에서 본다. --}}

{{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16). 라벨 위 · 컨트롤 아래, 9열 그리드.
     조회는 AJAX 라서 <form> 이 아니다(엔터로 페이지가 새로 뜨면 안 된다).
     시안 324:6158 은 즉시발행 탭에서도 이 카드와 결과바가 그대로 보인다 — 탭 밖에 둔다. --}}
<div class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" id="f-start" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" id="f-end" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">거래 유형</label>
      <select id="f-trade-type" class="form-control form-select">
        <option value="">전체 유형</option>
        <option value="승인거래">승인거래</option>
        <option value="취소거래">취소거래</option>
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    <button type="button" class="ds-btn ds-btn-primary" onclick="loadHistory(1)">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__cashbillGrid?.downloadExcel()">엑셀 저장</button>
    <button type="button" class="ds-btn" onclick="cbRowAction('detail')"><i class="bx bx-show"></i> 선택 상세</button>
    <button type="button" class="ds-btn" onclick="cbRowAction('print')"><i class="bx bx-printer"></i> 선택 인쇄</button>
    <button type="button" class="ds-btn" style="color:var(--danger);" onclick="cbRowAction('cancel')"><i class="bx bx-x"></i> 선택 취소</button>
    <button type="button" class="ds-btn" id="sync-btn" style="color:var(--primary);" onclick="syncFromPopbill()" title="팝빌에서 최신 데이터 가져오기"><i class="bx bx-refresh"></i> 팝빌 동기화</button>
  </div>
</div>

{{-- 그리드 카드(r12). 그리드 툴바·하단 상태바는 껐고
     엑셀 저장·선택 액션·팝빌 동기화를 전부 이 줄로 옮겼다.
     버튼 차례는 시안대로 엑셀 저장이 맨 앞이다. --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 탭줄은 카드 안 첫 줄이다(시안 324:4656 · 324:6158 공통) --}}
    <div class="titab-bar">
      <button type="button" class="titab active" data-tab="hist" onclick="tiTab('hist')"><i class="fa-solid fa-list"></i> 발행 내역<span class="pnl-tab-cnt">(총 <b id="cb-total-count">0</b>건)</span></button>
      <button type="button" class="titab" data-tab="issue" onclick="tiTab('issue')"><i class="bx bx-receipt"></i> 현금영수증 즉시발행</button>
      <span class="sync-badge" id="last-sync-label"></span>
    </div>

    {{-- ── 발행 내역 ── --}}
    <div class="cb-pane-hist" data-titab="hist">
      <div id="cbHistGrid"></div>
      <div class="hist-pager" id="hist-pager" style="display:none;">
        <div class="pager-info" id="pager-info"></div>
        <div class="pager-btns" id="pager-btns"></div>
      </div>
    </div>

    {{-- ── 발행 폼 ── --}}
    {{-- 시안 324:6158 Frame 48101521 — 카드 3장(기본 정보 · 부가 정보 · 총 금액)이 가로로,
         라벨은 왼쪽 100 폭, 컨트롤이 오른쪽. 점선 구분선 대신 카드가 구획을 나눈다. --}}
    <div class="cb-issue" data-titab="issue">
      <div class="cb-fcards">

        {{-- 기본 정보 --}}
        <div class="cb-fcard">
          <div class="cb-fcard-head"><span class="cb-fcard-title">기본 정보</span></div>
          <div class="cb-frows">
            <div class="cb-frow">
              <label class="ds-field-label">사업자번호</label>
              <div class="cb-fctrl">
                <input id="corp-num" class="form-control" type="text" value="{{ $corpNum }}" placeholder="1234567890">
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">관리번호 <span style="color:var(--danger)">*</span></label>
              <div class="cb-fctrl">
                {{-- 안내문 '최대 24자, 영문·숫자·특수' 는 시안 자리인 placeholder 로 옮겼다 --}}
                <input id="mgt-key" class="form-control" type="text" placeholder="최대 24자, 영문·숫자·특수">
                <button type="button" onclick="genMgtKey()" class="ds-btn" title="자동 생성">
                  <i class="bx bx-refresh"></i> 새로고침
                </button>
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">거래 유형 <span style="color:var(--danger)">*</span></label>
              <div class="cb-fctrl">
                <select id="trade-type" class="form-control form-select">
                  <option value="승인거래">승인거래</option>
                  <option value="취소거래">취소거래</option>
                </select>
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">사용 용도 <span style="color:var(--danger)">*</span></label>
              <div class="cb-fctrl">
                <select id="trade-usage" class="form-control form-select">
                  <option value="소득공제용">소득공제용</option>
                  <option value="지출증빙용">지출증빙용</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- 부가 정보 --}}
        <div class="cb-fcard">
          <div class="cb-fcard-head"><span class="cb-fcard-title">부가 정보</span></div>
          <div class="cb-frows">
            <div class="cb-frow">
              <label class="ds-field-label">신분확인번호 <span style="color:var(--danger)">*</span></label>
              <div class="cb-fctrl col">
                <div class="id-type-tabs">
                  <button type="button" class="id-type-tab active" onclick="setIdType('phone', this)">휴대폰번호</button>
                  <button type="button" class="id-type-tab" onclick="setIdType('rrn', this)">주민번호</button>
                  <button type="button" class="id-type-tab" onclick="setIdType('biz', this)">사업자번호</button>
                </div>
                <input id="identity-num" class="form-control" type="text" placeholder="휴대폰번호(010XXXXXXXX)">
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">고객명</label>
              <div class="cb-fctrl">
                <input id="customer-name" class="form-control" type="text" placeholder="고객명(선택)">
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">품목명</label>
              <div class="cb-fctrl">
                <input id="item-name" class="form-control" type="text" placeholder="품목명(선택) 예: 의료용품">
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">이메일</label>
              <div class="cb-fctrl">
                <input id="email" class="form-control" type="email" placeholder="이메일(선택)">
              </div>
            </div>
            <div class="cb-frow">
              <label class="ds-field-label">휴대폰</label>
              <div class="cb-fctrl">
                <input id="hp" class="form-control" type="text" placeholder="010XXXXXXXX" data-phone>
              </div>
            </div>
          </div>
        </div>

        {{-- 총 금액 --}}
        <div class="cb-fcard">
          <div class="cb-fcard-head">
            <span class="cb-fcard-title">총 금액(자동계산)</span>
            <span class="cb-fcard-note">금액 입력 <span style="color:var(--danger)">*</span></span>
          </div>
          <div class="amount-grid">
            <div class="amount-tile">
              <label class="ds-field-label">공급가액</label>
              <div class="at-row">
                <input id="supply-cost" class="form-control" type="number" min="0" value="0" placeholder="공급가액 입력" oninput="calcAmount()">
                <span class="at-unit">원</span>
              </div>
            </div>
            <div class="amount-tile">
              <label class="ds-field-label">부가세</label>
              <div class="at-row">
                <input id="tax" class="form-control" type="number" min="0" value="0" placeholder="부가세 입력" oninput="calcAmount()">
                <span class="at-unit">원</span>
              </div>
            </div>
            <div class="amount-tile">
              <label class="ds-field-label">봉사료</label>
              <div class="at-row">
                <input id="service-fee" class="form-control" type="number" min="0" value="0" placeholder="봉사료 입력" oninput="calcAmount()">
                <span class="at-unit">원</span>
              </div>
            </div>
            <div class="amount-total-row">
              <span class="at-label">합계</span>
              <span class="at-val" id="total-display">0 원</span>
              <input type="hidden" id="total-amount" value="0">
            </div>
          </div>
        </div>

      </div>

      {{-- 발행 버튼 --}}
      <div class="cb-issue-actions">
        <button class="issue-btn" id="issue-btn" onclick="issueCashbill()">
          <i class="bx bx-check-circle"></i> 현금영수증 발행
        </button>
      </div>
    </div>

  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal">
    <div class="nd-modal-head">
      <i class="bx bx-receipt" style="color:var(--primary);font-size:16px;"></i>
      <h3>현금영수증 상세</h3>
      <button class="nd-modal-close" onclick="closeModal('detail-modal')">&times;</button>
    </div>
    <div class="nd-modal-body" id="detail-body">
      <div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>
    </div>
  </div>
</div>

{{-- ── 취소 모달 ── --}}
<div class="nd-modal-overlay" id="cancel-modal">
  <div class="nd-modal" style="width:440px;">
    <div class="nd-modal-head">
      <i class="bx bx-x-circle" style="color:var(--danger);font-size:16px;"></i>
      <h3>현금영수증 취소</h3>
      <button class="nd-modal-close" onclick="closeModal('cancel-modal')">&times;</button>
    </div>
    <div class="nd-modal-body">
      <div class="cancel-note">
        <i class="bx bx-error" style="font-size:16px;flex-shrink:0;"></i>
        <span>취소 현금영수증이 발행됩니다. 취소 후에는 되돌릴 수 없습니다.</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="form-row">
          <label class="ds-field-label">취소 관리번호 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-mgt-key" class="form-control" type="text" placeholder="CB-REVOKE-001">
        </div>
        <div class="form-row">
          <label class="ds-field-label">원본 국세청승인번호 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-org-confirm" class="form-control" type="text" placeholder="confirmNum">
        </div>
        <div class="form-row">
          <label class="ds-field-label">원본 거래일자 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-org-date" class="form-control" type="date">
        </div>
        <button class="issue-btn" style="background:var(--danger);" id="cancel-confirm-btn" onclick="confirmRevoke()">
          <i class="bx bx-x-circle"></i> 취소 발행 확정
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// 발행 내역 wwGrid (조회 결과를 setData로 주입)
(function () {
  const el = document.getElementById('cbHistGrid');
  if (!el) return;
  window.__cbGrid = new wwGrid({
    el: el,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    // 시안은 표가 카드 바닥(페이저 위)까지 찬다 — 남는 높이는 .cb-pane-hist 의 flex 가 채운다
    // (1920×1221 에서 래퍼 808 로 자란다. 화면 상단 스타일 블록 참조).
    // height 를 아예 빼면 래퍼의 flex-basis 가 '내용 높이' 가 되는데,
    // 레이아웃 바깥(.layout-wrapper)이 min-height:100vh 라 상한이 없어 줄어들지 않는다.
    // 그러면 15행이 뷰포트를 넘는 화면(높이 1073 미만 — 1080p·노트북 전부)에서
    // 표가 아니라 페이지 전체가 스크롤되고 머리행 고정도 풀린다. 기준값 460 을 남긴다.
    height: 460, editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      // 시안 324:4656 x479 머리글은 '거래 일시' 다 — 띄어쓰기가 있다(name·width 는 그대로)
      { header: '거래 일시', name: 'tradeDt',  width: 150, sortable: true },
      { header: '번호',     name: 'num',       width: 170 },
      { header: '고객명',   name: 'customer',  width: 110, sortable: true },
      { header: '합계금액', name: 'amount',    width: 110, editor: 'number' },
      { header: '유형',     name: 'tradeType', width: 80,  align: 'center', sortable: true },
      { header: '용도',     name: 'usage',     width: 90,  align: 'center', sortable: true },
      { header: '국세청',   name: 'nts',       width: 80,  align: 'center' },
      { header: '출처',     name: 'source',    width: 80,  align: 'center', sortable: true },
    ],
    data: [],
  });
  window.__cashbillGrid = window.__cbGrid;                 // 결과바 '엑셀 저장' 버튼이 이걸 부른다
  window.dsBindSelCount(window.__cbGrid, 'cb-sel-count');  // 결과바 '선택 N건' 표시를 연결한다
  function cbOpenRow(r) {
    if (r._source === 'order') {
      // 워크스페이스 새 탭으로 (밖이면 브라우저 새 탭으로 폴백)
      ceOpenTab(BASE_URL + '/prescriptions/' + encodeURIComponent(r.rxNumber),
                '주문 - ' + (r.rxNumber || '신규'), 'file-edit-02');
    } else {
      openDetail(r.mgtKey);
    }
  }
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]'); if (!cell) return;
    const r = window.__cbGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (r) cbOpenRow(r);
  });
  window.cbRowAction = function (action) {
    const c = window.__cbGrid.getCheckedRows();
    if (!c.length)   { showToast('행을 먼저 체크하세요.', 'warning'); return; }
    if (c.length > 1){ showToast('한 건만 선택하세요.', 'warning'); return; }
    const r = c[0];
    if (action === 'detail') { cbOpenRow(r); return; }
    if (r._source === 'order') { showToast('처방전 항목은 인쇄/취소 대상이 아닙니다.', 'warning'); return; }
    if (action === 'print')  openPrint(r.mgtKey);
    if (action === 'cancel') {
      if (r.tradeType === '취소거래') { showToast('이미 취소된 건입니다.', 'warning'); return; }
      openCancelModal(r.confirmNum, r.cancelDate);
    }
  };
})();
</script>
<script>
// 탭 전환(발행 내역 / 즉시발행) — 기본은 발행 내역
function tiTab(name) {
  document.querySelectorAll('[data-titab]').forEach(el => { el.style.display = (el.dataset.titab === name) ? '' : 'none'; });
  document.querySelectorAll('.titab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
}
tiTab('hist');
</script>
<script>
const CORP_NUM  = document.getElementById('corp-num');
const CB_BASE   = BASE_URL + '/api/popbill/cashbill';
const HEADERS   = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };
let histPage = 1;

/* ── 초기화 ── */
document.addEventListener('DOMContentLoaded', () => {
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth(), 1);
  document.getElementById('f-start').value = fmtDate(first);
  document.getElementById('f-end').value   = fmtDate(today);
  document.getElementById('cancel-org-date').value = fmtDate(today);
  genMgtKey();
  loadHistory(1);
});

function fmtDate(d) {
  return d.getFullYear() + '-' +
    String(d.getMonth()+1).padStart(2,'0') + '-' +
    String(d.getDate()).padStart(2,'0');
}
function toApiDate(v) { return v.replace(/-/g,''); }

/* ── 관리번호 자동생성 ── */
function genMgtKey() {
  const now = new Date();
  const ts  = now.getFullYear()
    + String(now.getMonth()+1).padStart(2,'0')
    + String(now.getDate()).padStart(2,'0')
    + String(now.getHours()).padStart(2,'0')
    + String(now.getMinutes()).padStart(2,'0')
    + String(now.getSeconds()).padStart(2,'0');
  document.getElementById('mgt-key').value = 'CB-' + ts;
}

/* ── 금액 계산 ── */
function calcAmount() {
  const s = parseInt(document.getElementById('supply-cost').value) || 0;
  const t = parseInt(document.getElementById('tax').value) || 0;
  const f = parseInt(document.getElementById('service-fee').value) || 0;
  const total = s + t + f;
  document.getElementById('total-amount').value = total;
  document.getElementById('total-display').textContent = total.toLocaleString() + ' 원';
}

/* ── 신분확인번호 타입 ── */
function setIdType(type, btn) {
  document.querySelectorAll('.id-type-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const input = document.getElementById('identity-num');
  const hints = { phone:'휴대폰번호 (010XXXXXXXX)', rrn:'주민번호 13자리', biz:'사업자등록번호 10자리' };
  input.placeholder = hints[type];
  input.value = '';
}

/* ── 현금영수증 즉시발행 ── */
async function issueCashbill() {
  const mgtKey     = document.getElementById('mgt-key').value.trim();
  const tradeType  = document.getElementById('trade-type').value;
  const tradeUsage = document.getElementById('trade-usage').value;
  const supplyCost = document.getElementById('supply-cost').value;
  const tax        = document.getElementById('tax').value;
  const serviceFee = document.getElementById('service-fee').value;
  const totalAmt   = document.getElementById('total-amount').value;
  const identNum   = document.getElementById('identity-num').value.trim().replace(/\D/g,'');
  const custName   = document.getElementById('customer-name').value.trim();
  const itemName   = document.getElementById('item-name').value.trim();
  const email      = document.getElementById('email').value.trim();
  const hp         = document.getElementById('hp').value.trim();

  if (!mgtKey)    { showToast('관리번호를 입력하세요.', 'danger'); return; }
  if (!identNum)  { showToast('신분확인번호를 입력하세요.', 'danger'); return; }
  if (parseInt(totalAmt) <= 0) { showToast('합계금액은 0보다 커야 합니다.', 'danger'); return; }

  const btn = document.getElementById('issue-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 발행 중…';

  try {
    const res  = await fetch(`${CB_BASE}/regist-issue`, {
      method: 'POST',
      headers: HEADERS,
      body: JSON.stringify({
        corp_num:      CORP_NUM.value,
        mgt_key:       mgtKey,
        trade_type:    tradeType,
        trade_usage:   tradeUsage,
        supply_cost:   supplyCost,
        tax:           tax,
        service_fee:   serviceFee,
        total_amount:  totalAmt,
        identity_num:  identNum,
        customer_name: custName,
        item_name:     itemName,
        email:         email,
        hp:            hp,
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '발행 실패');

    showToast(`현금영수증 발행 완료! 승인번호: ${data.confirmNum ?? '확인중'}`, 'success', 6000);
    genMgtKey();
    loadHistory(1);
  } catch(e) {
    showToast('발행 실패: ' + e.message, 'danger', 7000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-check-circle"></i> 현금영수증 발행';
  }
}

/* ── 발행 내역 조회 (팝빌 + 처방전 통합) ── */
let _allRows = [];   // 병합된 전체 행 (클라이언트 페이지네이션용)

async function loadHistory(page = 1) {
  histPage = page;
  const cn        = CORP_NUM.value;
  const sd        = toApiDate(document.getElementById('f-start').value);
  const ed        = toApiDate(document.getElementById('f-end').value);
  const tradeType = document.getElementById('f-trade-type').value;

  window.__cbGrid && window.__cbGrid.setData([]);

  try {
    // 팝빌 현금영수증 (DB 기반, 전체 조회 후 클라이언트 페이지네이션)
    let popbillUrl = `${CB_BASE}/search?corp_num=${cn}&start_date=${sd}&end_date=${ed}&per_page=500&order=D`;
    if (tradeType) popbillUrl += `&trade_type=${encodeURIComponent(tradeType)}`;

    // 처방전 현금영수증 (orders 테이블)
    const orderUrl = `${CB_BASE}/order-receipts?corp_num=${cn}&start_date=${sd}&end_date=${ed}`;

    const [pbRes, ordRes] = await Promise.all([
      fetch(popbillUrl, { headers: HEADERS }),
      fetch(orderUrl,   { headers: HEADERS }),
    ]);

    const pbData  = pbRes.ok  ? await pbRes.json()  : { list: [] };
    const ordData = ordRes.ok ? await ordRes.json() : { list: [] };

    // 팝빌 행 정규화
    const pbRows = (pbData.list ?? []).map(r => ({
      _source:  'popbill',
      _sortKey: r.tradeDT ?? r.issueDT ?? '',
      ...r,
    }));

    // 처방전 행 정규화 (tradeType 필터 적용)
    let ordRows = (ordData.list ?? []).map(r => {
      const isCancel = r.status === 'cancelled';
      return {
        _source:    'order',
        _sortKey:   r.issuedAt ?? '',
        tradeType:  isCancel ? '취소거래' : '승인거래',
        tradeUsage: r.receiptTypeKey === 'income_deduction' ? '소득공제용' : '지출증빙용',
        totalAmount: r.amount,
        customerName: r.patientName,
        mgtKey:      r.orderNumber,
        tradeDT:     r.issuedAt,
        issueDT:     r.issuedAt,
        ntsresult:   null,
        confirmNum:  '',
        ...r,
      };
    });

    if (tradeType) {
      ordRows = ordRows.filter(r => r.tradeType === tradeType);
    }

    // 날짜 내림차순 병합
    _allRows = [...pbRows, ...ordRows].sort((a, b) =>
      (b._sortKey ?? '').localeCompare(a._sortKey ?? '')
    );

    renderHistPage(page);

    // 마지막 동기화 시각 표시
    const withSync = pbRows.find(r => r.syncedAt);
    if (withSync) {
      document.getElementById('last-sync-label').textContent = '마지막 동기화: ' + withSync.syncedAt.slice(0,16);
    }
  } catch(e) {
    window.__cbGrid && window.__cbGrid.setData([]);
    showToast('조회 실패: ' + e.message, 'error');
  }
}

function renderHistPage(page) {
  const perPage = 15;
  const start   = (page - 1) * perPage;
  const slice   = _allRows.slice(start, start + perPage);

  if (slice.length === 0) {
    window.__cbGrid && window.__cbGrid.setData([]);
    document.getElementById('hist-pager').style.display = 'none';
    return;
  }

  const rows = slice.map(r => {
    const tradeDt = (r.tradeDT ?? r.issueDT ?? '').replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5');
    const ntsTxt  = r._source === 'order' ? '바로빌' : ({ '0':'전송전','1':'전송중','2':'성공','3':'실패' }[String(r.ntsresult??'0')] ?? '—');
    const amount  = parseInt(r.totalAmount ?? 0);
    const num     = r._source === 'order'
      ? ((r.orderNumber ?? '') + (r.rxNumber ? ' / ' + r.rxNumber : ''))
      : (r.mgtKey ?? '—');
    const source  = r._source === 'order' ? '처방전' : '팝빌';
    return {
      // 표시 필드
      tradeDt, num, customer: (r.customerName ?? '—'), amount,
      tradeType: (r.tradeType ?? '—'), usage: (r.tradeUsage ?? '—'), nts: ntsTxt, source,
      // 액션용 숨김 필드
      _source: r._source, mgtKey: (r.mgtKey ?? ''), confirmNum: (r.confirmNum ?? ''),
      rxNumber: (r.rxNumber ?? ''), cancelDate: (r.tradeDT ?? r.issueDT ?? '').slice(0,8),
    };
  });
  window.__cbGrid && window.__cbGrid.setData(rows);

  renderPager(_allRows.length, page, perPage);
}

/* ── 팝빌 동기화 ── */
async function syncFromPopbill() {
  const sd  = toApiDate(document.getElementById('f-start').value);
  const ed  = toApiDate(document.getElementById('f-end').value);
  const btn = document.getElementById('sync-btn');

  if (!sd || !ed) { showToast('조회 기간을 먼저 설정하세요.', 'danger'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 동기화 중…';

  try {
    const res  = await fetch(`${CB_BASE}/sync`, {
      method: 'POST',
      headers: HEADERS,
      body: JSON.stringify({ corp_num: CORP_NUM.value, start_date: sd, end_date: ed }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '동기화 실패');

    const now = new Date().toISOString().slice(0,16).replace('T',' ');
    document.getElementById('last-sync-label').textContent = '마지막 동기화: ' + now;

    showToast(`동기화 완료 — 저장 ${data.synced}건, 상태갱신 ${data.updated}건`, 'success', 5000);
    loadHistory(histPage);
  } catch(e) {
    showToast('동기화 실패: ' + e.message, 'danger', 6000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-refresh"></i> 팝빌 동기화';
  }
}

function renderPager(total, page, perPage) {
  const pager = document.getElementById('hist-pager');
  const pages = Math.ceil(total / perPage);
  if (pages <= 1) { pager.style.display = 'none'; return; }

  pager.style.display = 'flex';
  document.getElementById('pager-info').textContent = `총 ${total.toLocaleString()}건`;

  const btns  = [];
  const start = Math.max(1, page - 2);
  const end   = Math.min(pages, page + 2);
  if (page > 1) btns.push(`<button class="pager-btn" onclick="renderHistPage(${page-1})">‹</button>`);
  for (let p = start; p <= end; p++) {
    btns.push(`<button class="pager-btn ${p===page?'active':''}" onclick="renderHistPage(${p})">${p}</button>`);
  }
  if (page < pages) btns.push(`<button class="pager-btn" onclick="renderHistPage(${page+1})">›</button>`);
  document.getElementById('pager-btns').innerHTML = btns.join('');
}

/* 결과바 '전체 N건' — 시안대로 그리드 하단 상태바를 껐다.
   목록 렌더가 이미 계산해 둔 전체 건수(_allRows)를 표시만 얹는다. 조회 로직은 그대로다. */
(function () {
  const el = document.getElementById('cb-total-count');
  if (!el) return;
  const orig = renderHistPage;
  renderHistPage = function (page) {
    orig(page);
    el.textContent = _allRows.length.toLocaleString();
  };
})();

/* ── 상세 보기 ── */
let _cbPrintData = null;   // 마지막 조회 데이터 캐시

async function openDetail(mgtKey) {
  document.getElementById('detail-modal').classList.add('open');
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>';

  try {
    const cn  = CORP_NUM.value;
    const res = await fetch(`${CB_BASE}/info?corp_num=${cn}&mgt_key=${encodeURIComponent(mgtKey)}`, { headers: HEADERS });
    const r   = await res.json();
    if (!res.ok) throw new Error(r.message ?? '조회 실패');

    _cbPrintData = r;   // 인쇄용 캐시

    const fmt6 = s => (s ?? '').replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5:$6');
    const tradeDt      = fmt6(r.tradeDT ?? r.issueDT ?? '');
    const ntsDt        = fmt6(r.ntsresultDT ?? '');
    const confirmNum   = r.confirmNum ?? r.ntsConfirmNum ?? '—';
    const identityMask = (r.identityNum ?? '').replace(/(\d{3})-?(\d{4})-?(\d{4})/, '$1-****-$3')
                          .replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2') || (r.identityNum ?? '—');
    const corpFmt = v => (v ?? '').replace(/(\d{3})(\d{2})(\d{5})/, '$1-$2-$3') || '—';
    const telFmt  = v => (v ?? '').replace(/(\d{2,3})(\d{3,4})(\d{4})/, '$1-$2-$3') || '—';
    const won     = v => parseInt(v ?? 0).toLocaleString() + '원';

    document.getElementById('detail-body').innerHTML = `
      <div class="cbv-layout">
        <div class="cbv-header"><p>현금영수증</p></div>
        <div class="cbv-body">
          <table><colgroup><col width="15%"><col width="35%"><col width="15%"><col width="35%"></colgroup>
            <tbody>
              <tr>
                <td>식별번호</td><td>${identityMask}</td>
                <td>문서형태</td><td>${r.tradeType ?? '—'}</td>
              </tr>
              <tr>
                <td>거래구분</td><td>${r.tradeUsage ?? '—'}</td>
                <td>거래유형</td><td>${r.taxationType ?? '일반'}</td>
              </tr>
              <tr>
                <td>거래일시</td><td>${tradeDt || '—'}</td>
                <td rowspan="2">국세청<br>승인번호</td>
                <td rowspan="2">${confirmNum}</td>
              </tr>
              <tr>
                <td>전송일자</td><td>${ntsDt || '—'}</td>
              </tr>
            </tbody>
          </table>

          <div class="cbv-sub-row">
            <div class="cbv-sub-title">구매정보</div>
            <div class="cbv-sub-title">결제정보</div>
          </div>
          <table><colgroup><col width="15%"><col width="35%"><col width="15%"><col width="35%"></colgroup>
            <tbody>
              <tr>
                <td>구매자명</td><td>${r.customerName ?? '—'}</td>
                <td class="cbv-red">거래금액</td><td>${won(r.totalAmount)}</td>
              </tr>
              <tr>
                <td>주문번호</td><td>${r.orderNumber ?? ''}</td>
                <td class="cbv-red">공급가액</td><td>${won(r.supplyCost)}</td>
              </tr>
              <tr>
                <td rowspan="2">주문<br>상품명</td>
                <td rowspan="2">${r.itemName ?? '—'}</td>
                <td class="cbv-red">부가세</td><td>${won(r.tax)}</td>
              </tr>
              <tr>
                <td class="cbv-red">봉사료</td><td>${won(r.serviceFee)}</td>
              </tr>
            </tbody>
          </table>

          <div class="cbv-sub-row">
            <div class="cbv-sub-title">현금영수증 가맹점</div>
          </div>
          <table><colgroup><col width="15%"><col width="35%"><col width="15%"><col width="35%"></colgroup>
            <tbody>
              <tr>
                <td>상호</td><td colspan="3">${r.franchiseCorpName ?? '—'}</td>
              </tr>
              <tr>
                <td>사업자번호</td><td>${corpFmt(r.franchiseCorpNum)}</td>
                <td>종사업장</td><td></td>
              </tr>
              <tr>
                <td>대표자</td><td>${r.franchiseCEOName ?? '—'}</td>
                <td>전화번호</td><td>${telFmt(r.franchiseTEL)}</td>
              </tr>
              <tr>
                <td style="height:50px;">주소</td>
                <td colspan="3" style="line-height:1.6;">${r.franchiseAddr ?? '—'}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="cbv-footer">
          <p>본 현금영수증은 발행 후 24시간 이내 국세청에서 확인하여 확정되며, 홈택스(www.hometax.go.kr)에서 전송내역을 확인할 수 있습니다.</p>
          <p>현금영수증 문의(국세청) : 126</p>
        </div>
        <div class="cbv-print-row">
          <button class="btn-icon print" onclick="closeModal('detail-modal');openPrint('${mgtKey}')">
            <i class="bx bx-printer"></i> 인쇄
          </button>
        </div>
      </div>`;
  } catch(e) {
    document.getElementById('detail-body').innerHTML =
      `<div style="text-align:center;padding:30px;color:var(--danger);">${e.message}</div>`;
  }
}

/* ── 인쇄 ── */
async function openPrint(mgtKey) {
  let r = _cbPrintData;

  // 캐시가 없거나 다른 관리번호이면 재조회
  if (!r || r.mgtKey !== mgtKey) {
    try {
      const cn  = CORP_NUM.value;
      const res = await fetch(`${CB_BASE}/info?corp_num=${cn}&mgt_key=${encodeURIComponent(mgtKey)}`, { headers: HEADERS });
      r = await res.json();
      if (!res.ok) throw new Error(r.message ?? '조회 실패');
      _cbPrintData = r;
    } catch(e) {
      showToast('인쇄 실패: ' + e.message, 'danger');
      return;
    }
  }

  const fmt6 = s => (s ?? '').replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5:$6');
  const tradeDt    = fmt6(r.tradeDT ?? r.issueDT ?? '');
  const ntsDt      = fmt6(r.ntsresultDT ?? '');
  const confirmNum = r.confirmNum ?? r.ntsConfirmNum ?? '—';
  const idMask     = (r.identityNum ?? '').replace(/(\d{3})-?(\d{4})-?(\d{4})/, '$1-****-$3')
                       .replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2') || (r.identityNum ?? '—');
  const corpFmt = v => (v ?? '').replace(/(\d{3})(\d{2})(\d{5})/, '$1-$2-$3') || '—';
  const telFmt  = v => (v ?? '').replace(/(\d{2,3})(\d{3,4})(\d{4})/, '$1-$2-$3') || '—';
  const won     = v => parseInt(v ?? 0).toLocaleString() + '원';

  const html = `<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>현금영수증</title>
<style>
  @page { margin: 12mm 15mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  /* 인쇄 판형은 앱 밖(window.open)이라 CSS 변수가 실리지 않는다.
     그래서 색은 변수 대신 DS 램프의 실제 값으로 적는다. */
  body {
    font-family: '맑은 고딕', 'Malgun Gothic', AppleGothic, sans-serif;
    font-size: 13px; color: #101317;
    padding: 20px 24px;
  }
  .receipt { width: 100%; }
  .r-header { text-align: center; padding: 14px 0 12px; border-bottom: 2px solid #101317; margin-bottom: 0; }
  .r-header p { font-size: 16px; font-weight: 700; letter-spacing: 3px; }
  .r-body table { width: 100%; border-collapse: collapse; }
  .r-body table td {
    padding: 8px 10px; border: 1px solid #C2C5C8; font-size: 13px; vertical-align: middle;
  }
  .r-body table td:first-child,
  .r-body table td:nth-child(3) { background: #F3F5F7; font-weight: 700; color: #474D54; white-space: nowrap; }
  .r-sub-row { display: flex; border: 1px solid #C2C5C8; border-top: none; }
  .r-sub-title { flex: 1; padding: 6px 10px; font-weight: 700; font-size: 13px; background: #F9FAFC; }
  .r-sub-title + .r-sub-title { border-left: 1px solid #C2C5C8; }
  .r-red { color: var(--alert-500); font-weight: 700; }
  .r-footer { border-top: 1px solid #C2C5C8; padding: 10px 12px; font-size: 11px; color: #656C74; line-height: 18px; }
  .r-footer p { margin: 0; }
  .no-print { text-align: right; padding: 14px 0 0; }
  .no-print button {
    padding: 5px 12px; height: 32px; background: #28798B; color: #FFFFFF; border: none;
    border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;
  }
  @media print {
    body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* 같은 특정성에 뒤에 오는 규칙이라 우선순위 표시 없이도 이긴다 */
    .no-print { display: none; }
  }
</style>
</head>
<body>
<div class="receipt">
  <div class="r-header"><p>현금영수증</p></div>
  <div class="r-body">
    <table><colgroup><col width="18%"><col width="32%"><col width="18%"><col width="32%"></colgroup>
      <tbody>
        <tr>
          <td>식별번호</td><td>${idMask}</td>
          <td>문서형태</td><td>${r.tradeType ?? '—'}</td>
        </tr>
        <tr>
          <td>거래구분</td><td>${r.tradeUsage ?? '—'}</td>
          <td>거래유형</td><td>${r.taxationType ?? '일반'}</td>
        </tr>
        <tr>
          <td>거래일시</td><td>${tradeDt || '—'}</td>
          <td rowspan="2">국세청<br>승인번호</td>
          <td rowspan="2">${confirmNum}</td>
        </tr>
        <tr>
          <td>전송일자</td><td>${ntsDt || '—'}</td>
        </tr>
      </tbody>
    </table>
    <div class="r-sub-row">
      <div class="r-sub-title">구매정보</div>
      <div class="r-sub-title">결제정보</div>
    </div>
    <table><colgroup><col width="18%"><col width="32%"><col width="18%"><col width="32%"></colgroup>
      <tbody>
        <tr>
          <td>구매자명</td><td>${r.customerName ?? '—'}</td>
          <td class="r-red">거래금액</td><td>${won(r.totalAmount)}</td>
        </tr>
        <tr>
          <td>주문번호</td><td>${r.orderNumber ?? ''}</td>
          <td class="r-red">공급가액</td><td>${won(r.supplyCost)}</td>
        </tr>
        <tr>
          <td rowspan="2">주문<br>상품명</td>
          <td rowspan="2">${r.itemName ?? '—'}</td>
          <td class="r-red">부가세</td><td>${won(r.tax)}</td>
        </tr>
        <tr>
          <td class="r-red">봉사료</td><td>${won(r.serviceFee)}</td>
        </tr>
      </tbody>
    </table>
    <div class="r-sub-row">
      <div class="r-sub-title">현금영수증 가맹점</div>
    </div>
    <table><colgroup><col width="18%"><col width="32%"><col width="18%"><col width="32%"></colgroup>
      <tbody>
        <tr>
          <td>상호</td><td colspan="3">${r.franchiseCorpName ?? '—'}</td>
        </tr>
        <tr>
          <td>사업자번호</td><td>${corpFmt(r.franchiseCorpNum)}</td>
          <td>종사업장</td><td></td>
        </tr>
        <tr>
          <td>대표자</td><td>${r.franchiseCEOName ?? '—'}</td>
          <td>전화번호</td><td>${telFmt(r.franchiseTEL)}</td>
        </tr>
        <tr>
          <td style="height:50px;">주소</td>
          <td colspan="3" style="line-height:1.6;">${r.franchiseAddr ?? '—'}</td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="r-footer">
    <p>본 현금영수증은 발행 후 24시간 이내 국세청에서 확인하여 확정되며, 홈택스(www.hometax.go.kr)에서 전송내역을 확인할 수 있습니다.</p>
    <p>현금영수증 문의(국세청) : 126</p>
  </div>
</div>
<div class="no-print">
  <button onclick="window.print()">🖨️ 인쇄</button>
</div>
</body>
</html>`;

  const w = window.open('', '_blank', 'width=780,height=920,scrollbars=yes');
  if (!w) { showToast('팝업이 차단되었습니다. 팝업 허용 후 다시 시도하세요.', 'danger', 5000); return; }
  w.document.write(html);
  w.document.close();
  w.focus();
  w.onload = () => w.print();
  if (w.document.readyState === 'complete') w.print();
}

/* ── 취소 모달 ── */
function openCancelModal(confirmNum, tradeDate) {
  genCancelMgtKey();
  document.getElementById('cancel-org-confirm').value = confirmNum ?? '';
  if (tradeDate && tradeDate.length === 8) {
    document.getElementById('cancel-org-date').value =
      tradeDate.slice(0,4) + '-' + tradeDate.slice(4,6) + '-' + tradeDate.slice(6,8);
  }
  document.getElementById('cancel-modal').classList.add('open');
}

function genCancelMgtKey() {
  const now = new Date();
  const ts  = now.getFullYear()
    + String(now.getMonth()+1).padStart(2,'0')
    + String(now.getDate()).padStart(2,'0')
    + String(now.getHours()).padStart(2,'0')
    + String(now.getMinutes()).padStart(2,'0')
    + String(now.getSeconds()).padStart(2,'0');
  document.getElementById('cancel-mgt-key').value = 'CBR-' + ts;
}

async function confirmRevoke() {
  const mgtKey    = document.getElementById('cancel-mgt-key').value.trim();
  const orgConfirm= document.getElementById('cancel-org-confirm').value.trim();
  const orgDate   = toApiDate(document.getElementById('cancel-org-date').value);

  if (!mgtKey)     { showToast('취소 관리번호를 입력하세요.', 'danger'); return; }
  if (!orgConfirm) { showToast('원본 국세청승인번호를 입력하세요.', 'danger'); return; }
  if (!orgDate)    { showToast('원본 거래일자를 입력하세요.', 'danger'); return; }

  const btn = document.getElementById('cancel-confirm-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 처리 중…';

  try {
    const res  = await fetch(`${CB_BASE}/revoke`, {
      method: 'POST',
      headers: HEADERS,
      body: JSON.stringify({
        corp_num:        CORP_NUM.value,
        mgt_key:         mgtKey,
        org_confirm_num: orgConfirm,
        org_trade_date:  orgDate,
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '취소 실패');

    closeModal('cancel-modal');
    showToast('취소 현금영수증이 발행되었습니다.', 'success', 5000);
    loadHistory(1);
  } catch(e) {
    showToast('취소 실패: ' + e.message, 'danger', 7000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-x-circle"></i> 취소 발행 확정';
  }
}

/* ── 모달 공통 ── */
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
['detail-modal','cancel-modal'].forEach(id => {
  document.getElementById(id).addEventListener('click', e => {
    if (e.target === document.getElementById(id)) closeModal(id);
  });
});
</script>
@endpush
