{{-- resources/views/taxinvoice/index.blade.php --}}
@extends('layouts.app')

@section('title', '세금계산서 발행')
@section('page-title', '세금계산서 발행')
@section('breadcrumb', '홈 / 세금계산서 발행')

@section('help-title', '세금계산서 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>팝빌 API를 통해 전자세금계산서를 즉시 발행하고 내역을 조회·취소하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">세금종류</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);"><i class="bx bx-percent"></i></div><div class="help-item-text"><b>과세</b> — 공급가액의 10% 부가세</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-100);color:var(--primary-600);"><i class="bx bx-circle"></i></div><div class="help-item-text"><b>영세</b> — 영세율(0%) 적용</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--gray-100);color:var(--gray-500);"><i class="bx bx-minus-circle"></i></div><div class="help-item-text"><b>면세</b> — 부가세 없음</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">발행 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">임시저장</span>
    <span class="badge badge-primary">발행완료</span>
    <span class="badge badge-success">국세청 완료</span>
    <span class="badge badge-danger">취소</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">유의사항</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--danger-light);color:var(--danger);"><i class="bx bx-error"></i></div><div class="help-item-text">발행 후 취소하려면 "발행 취소" 버튼을 사용하세요. 국세청 신고 후에는 취소가 제한될 수 있습니다.</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--danger-light);color:var(--danger);"><i class="bx bx-error"></i></div><div class="help-item-text">테스트 환경에서는 실제 국세청으로 전송되지 않습니다.</div></div>
</div>
@endsection

@push('styles')
<style>
/* ── 레이아웃 ── */
/* 발행 내역 / 즉시발행 탭 구성. 화면 사이 간격은 .page-body 의 gap 16 이 만든다 */
.ti-layout { display:flex; flex-direction:column; gap:12px; }
/* 탭이 켠 쪽 한 묶음 — tiTab() 이 display 인라인 값을 지우면 이 flex 로 돌아온다 */
.ti-panel  { display:flex; flex-direction:column; gap:12px; }
/* 탭바 — 표준 패널 탭 규격(h44 · pad 0/16 · gap 16 · 하단 1px --border · 활성 밑줄 1px) */
.titab-bar { display:flex; align-items:center; gap:16px; padding:0 16px; flex-wrap:wrap;
  background:var(--gray-0); border-radius:12px; border-bottom:1px solid var(--border); }
.titab { height:44px; padding:0 8px; font-size:13px; font-weight:500; line-height:21px; border:none; background:none; cursor:pointer;
  color:var(--text-muted); border-bottom:1px solid transparent; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
.titab:hover { color:var(--primary); }
.titab.active { color:var(--primary); border-bottom-color:var(--primary); }
/* 결과바 '선택 N건' — 전역(layouts/app.blade.php)에 .ds-grid-sel 이 없어 화면에서 정의한다.
   다른 목록 화면들도 같은 값을 각자 들고 있다. 전역으로 올려야 할 항목. */

/* ── 요약 카드 ── */
.ti-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media(max-width:900px){ .ti-summary { grid-template-columns:1fr 1fr; } }
.sum-card { background:var(--gray-0); border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; }
.sum-card .sc-icon { width:36px; height:36px; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:16px; }
.sum-card .sc-label { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-bottom:4px; }
.sum-card .sc-val   { font-size:16px; font-weight:700; line-height:22px; color:var(--gray-800); }
/* 시안에 초록·주황이 없다. 네 칸을 primary 램프 두 단계 + alert + gray 로 나눈다 */
.sum-card.blue  .sc-icon { background:var(--primary-50);  color:var(--primary-500); }
.sum-card.green .sc-icon { background:var(--primary-100); color:var(--primary-600); }
.sum-card.red   .sc-icon { background:var(--alert-50);    color:var(--alert-500); }
.sum-card.gray  .sc-icon { background:var(--gray-100);    color:var(--gray-500); }

/* ── 발행 폼 카드 ── */
.ti-card { background:var(--gray-0); border-radius:12px; overflow:hidden; }
.ti-card-head { padding:11px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px;
  font-size:14px; font-weight:700; line-height:22px; color:var(--gray-800); }
.ti-card-head i { font-size:16px; color:var(--primary); }
.ti-card-body { padding:16px; display:flex; flex-direction:column; gap:0; }

/* 섹션 */
.form-section { padding:12px 0; border-bottom:1px solid var(--border); }
.form-section:last-child { border-bottom:none; }
/* 공급자(을)·공급받는자(갑) 좌우 배치 */
.sb-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 24px; border-bottom:1px solid var(--border); }
.sb-grid > .form-section { border-bottom:none; }
@media(max-width:820px){ .sb-grid { grid-template-columns:1fr; } }
/* .section-title 은 전역 클래스다(11/700 · uppercase · letter-spacing .6).
   여기서 선언하지 않은 속성은 전역 값이 그대로 살아남으므로 명시적으로 되돌린다. */
.section-title { font-size:13px; font-weight:700; line-height:21px; color:var(--primary); margin-bottom:12px; display:flex; align-items:center; gap:6px;
  text-transform:none; letter-spacing:0; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }

/* 라벨 위 · 컨트롤 아래 — 라벨 21 + gap 8 + 입력 32 = 필드 61 */
.form-row  { display:flex; flex-direction:column; gap:8px; }
/* .form-label 은 전역 클래스라 이 화면 안으로만 한정한다 */
.ti-card-body .form-label, .nd-modal-body .form-label {
  display:block; margin-bottom:0; letter-spacing:0;
  font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700);
}
.form-input {
  width:100%; height:32px; border:1px solid var(--gray-200); border-radius:8px;
  padding:5px 12px; font-size:13px; font-weight:400; line-height:20px;
  color:var(--text-primary); background:var(--gray-0); transition:border-color .15s;
}
.form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(40,121,139,.12); }
.form-input::placeholder { color:var(--gray-500); }
select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238B95A1' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; }
.form-input-full { grid-column:1/-1; }

/* 금액 행 */
.amount-box {
  background:var(--primary-50); border-radius:8px; padding:12px;
  display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:12px;
}
.amount-box .ab-label { font-size:12px; font-weight:500; line-height:19px; color:var(--primary); margin-bottom:4px; }
.amount-box .ab-val   { font-size:14px; font-weight:700; line-height:22px; color:var(--primary); }
/* .ab-total* 는 지금 마크업 사용처가 없다. 개발 자산이라 남기고 규격만 맞춘다 */
.amount-box .ab-total { grid-column:1/-1; border-top:1px solid var(--primary-200); padding-top:12px; display:flex; justify-content:space-between; align-items:center; }
.amount-box .ab-total-label { font-size:13px; font-weight:700; line-height:21px; color:var(--primary); }
.amount-box .ab-total-val   { font-size:16px; font-weight:700; line-height:26px; color:var(--primary); }

/* 품목 테이블 */
.detail-wrap { border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-top:8px; }
.detail-table { width:100%; border-collapse:collapse; font-size:13px; }
.detail-table th {
  padding:6px 8px; background:var(--gray-100); font-size:12px; font-weight:700; line-height:19px;
  color:var(--gray-600); text-align:center; border-bottom:1px solid var(--border); white-space:nowrap;
}
.detail-table td { padding:4px; border-bottom:1px solid var(--border); }
.detail-table tr:last-child td { border-bottom:none; }
.detail-table input {
  width:100%; height:28px; border:1px solid transparent; border-radius:6px;
  padding:3px 8px; font-size:13px; font-weight:400; line-height:20px;
  text-align:right; background:transparent; color:var(--text-primary);
}
.detail-table input:focus { outline:none; border-color:var(--primary); background:var(--gray-0); box-shadow:0 0 0 2px rgba(40,121,139,.1); }
.detail-table input.text-left { text-align:left; }
.detail-del { width:24px; height:24px; background:var(--alert-50); color:var(--alert-500); border:none; border-radius:6px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; margin:auto; }
.detail-del:hover { background:var(--alert-100); }
.detail-add-btn {
  width:100%; height:32px; padding:5px 12px; background:var(--gray-50); border:none; border-top:1px solid var(--border);
  color:var(--primary); font-size:13px; font-weight:500; line-height:20px; cursor:pointer; display:flex; align-items:center;
  justify-content:center; gap:6px; transition:background .15s;
}
.detail-add-btn:hover { background:var(--primary-light); }

/* 발행 버튼 — 표준 버튼 규격(h32 · r8 · pad 5/12 · 13/500), 카드 오른쪽 아래 정렬 */
.ti-form-actions { display:flex; justify-content:flex-end; margin-top:16px; }
.issue-btn {
  height:32px; padding:5px 12px; background:var(--primary); color:var(--gray-0);
  border:1px solid transparent; border-radius:8px;
  font-size:13px; font-weight:500; line-height:20px; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center; gap:6px;
  white-space:nowrap; transition:background .15s;
}
.issue-btn:hover:not(:disabled) { background:var(--primary-600); }
.issue-btn:disabled { opacity:.6; cursor:not-allowed; }

/* ── 내역 패널 ── */
/* 패널 제목 — 섹션 제목 14/700. 카드 껍데기(.hist-card/.hist-head/.hist-filter/.hist-body)는
   표준 .ds-filter-card · .ds-grid-bar · .ds-grid-card 로 바뀌어 규칙이 남아 있을 자리가 없다. */
.hist-head-title { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:700; line-height:22px; color:var(--gray-800); }
.hist-head-title i { font-size:16px; color:var(--primary); }
/* 관리번호 자동생성 버튼이 계속 쓴다 */
.btn-search { height:32px; padding:5px 12px; background:var(--primary); color:var(--gray-0); border:1px solid transparent; border-radius:8px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; white-space:nowrap; }
.btn-search:hover { background:var(--primary-600); }

/* 아래 두 묶음은 지금 마크업 사용처가 없다. 개발 자산이라 남기고 규격만 맞춘다 */
.hist-table { width:100%; border-collapse:collapse; font-size:13px; }
.hist-table th { padding:8px 10px; background:var(--bg); font-size:12px; font-weight:700; line-height:19px; color:var(--gray-600); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
.hist-table td { padding:8px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
.hist-table tr:last-child td { border-bottom:none; }
.hist-table tr:hover td { background:var(--gray-50); }
.hist-empty { padding:40px; text-align:center; color:var(--gray-500); font-size:13px; font-weight:400; line-height:21px; }

/* 상태 배지 — 배지 규격(r6 · pad 2/6 · 11px/500 · lh18) */
.ti-badge { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; }
/* 발행 단계는 임시저장 → 발행완료 → 국세청 완료 → 취소 네 칸이다.
   초록(--success)을 primary 로 접으면서 '발행완료'와 '국세청 완료'가 같은 색이 됐다.
   램프는 primary/alert 둘만 쓰되 국세청 완료만 한 단계 깊은 primary-100/600 을 준다
   (이 화면이 .sum-card.blue/.green 과 도움말 범례에서 이미 쓰는 두 단계다). */
.ti-badge.draft   { background:var(--gray-100);      color:var(--gray-600); }
.ti-badge.issued  { background:var(--primary-light); color:var(--primary); }
.ti-badge.nts     { background:var(--primary-100);   color:var(--primary-600); }
.ti-badge.cancel  { background:var(--danger-light);  color:var(--danger); }
.ti-badge.taxvat  { background:var(--primary-light); color:var(--primary); }
.ti-badge.taxzero { background:var(--primary-100);   color:var(--primary-600); }
.ti-badge.taxfree { background:var(--gray-100);      color:var(--gray-600); }
/* 도움말 범례의 '국세청 완료' 스와치도 같은 색으로 — 범례와 실제 배지가 어긋나지 않게 한다 */
.help-badge-row .badge-success { background:var(--primary-100); color:var(--primary-600); }
/* 시안에 보라가 없다 — primary 연톤으로 돌린다 */
.ti-badge.rx      { background:var(--primary-light); color:var(--primary); }

/* .btn-icon 은 전역(상단바 아이콘 버튼) 이름과 겹친다. 이 화면이 열린 동안
   상단바까지 같이 바뀌므로 상세 모달 안으로만 한정한다. 작은 버튼 규격(h28 · r8 · pad 3/10 · 13/500) */
.nd-modal-body .btn-icon { height:28px; padding:3px 10px; font-size:13px; font-weight:500; line-height:20px; border:none; border-radius:8px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.nd-modal-body .btn-icon.view   { background:var(--primary-light); color:var(--primary); }
.nd-modal-body .btn-icon.print  { background:var(--gray-100);      color:var(--gray-600); }
.nd-modal-body .btn-icon.cancel { background:var(--danger-light);  color:var(--danger); }
.nd-modal-body .btn-icon:hover  { filter:brightness(.93); }
.nd-modal-body .btn-icon + .btn-icon { margin-left:4px; }

/* 페이지네이션 — 그리드 카드 하단 줄(pad 12 · 상단 1px · gap 2) */
.hist-pager { padding:12px; border-top:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; gap:8px; }
.pager-info { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
.pager-btns { display:flex; gap:2px; }
.pager-btn { height:32px; min-width:32px; padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px; background:var(--gray-0); font-size:13px; font-weight:500; line-height:20px; cursor:pointer; color:var(--gray-800); transition:border-color .15s,background .15s; }
.pager-btn:hover { border-color:var(--primary); color:var(--primary); }
.pager-btn.active { background:var(--primary); color:var(--gray-0); border-color:var(--primary); }

/* ── 모달 ── */
.nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center; padding:8px; }
.nd-modal-overlay.open { display:flex; }
.nd-modal { background:var(--gray-0); border-radius:12px; box-shadow:0 24px 80px rgba(0,0,0,.22); width:640px; max-width:100%; max-height:calc(100vh - 16px); display:flex; flex-direction:column; }
.nd-modal.wide { width:min(1200px, calc(100vw - 16px)); max-height:calc(100vh - 16px); }
.nd-modal-head { padding:11px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; flex-shrink:0; }
.nd-modal-head h3 { flex:1; font-size:16px; font-weight:700; line-height:26px; margin:0; color:var(--gray-800); }
.nd-modal-close { width:24px; height:24px; flex-shrink:0; background:none; border:none; border-radius:6px; font-size:16px; color:var(--gray-500); cursor:pointer; line-height:1; padding:0; }
.nd-modal-close:hover { background:var(--gray-100); color:var(--gray-800); }
.nd-modal-body  { padding:16px; overflow-y:auto; flex:1; }

/* 취소 모달 */
.cancel-note { background:var(--danger-light); border-radius:8px; padding:10px 12px; font-size:12px; font-weight:400; line-height:19px; color:var(--danger); margin-bottom:12px; display:flex; gap:8px; align-items:flex-start; }

/* ── 세금계산서 문서 양식 (상세 모달 안 미리보기) ──
   서체는 Pretendard 하나로 통일하고, 색은 전부 DS 토큰으로 옮겼다.
   공급자=primary 계열 / 공급받는자=alert 계열로 좌우 구분은 그대로 유지한다.
   표 구조·colspan·행 수는 손대지 않았다. */
.ti-doc table { width:100%; border-collapse:collapse; table-layout:fixed; }
.ti-doc td { border:1px solid var(--gray-300); padding:5px 6px; font-size:12px; font-weight:400; line-height:19px; vertical-align:middle; overflow:hidden; }
.ti-doc .td-th { background:var(--gray-100); font-weight:700; font-size:12px; text-align:center; color:var(--gray-800); white-space:nowrap; }
.ti-main-title-cell { text-align:center; vertical-align:middle; }
.ti-main-title-cell strong { font-size:16px; font-weight:700; line-height:26px; letter-spacing:4px; }
.ti-book-label { font-size:11px; font-weight:500; line-height:18px; color:var(--gray-700); white-space:nowrap; }
.ti-book-sep { text-align:center; }
.ti-book-num { font-family:monospace; font-size:11px; overflow:hidden; }
.ti-book-unit { text-align:center; font-size:11px; white-space:nowrap; }
.invoicer { background:var(--primary-50); }
.invoicer.td-th { background:var(--primary-100); color:var(--primary-700); font-size:12px; white-space:nowrap; }
.invoicer.group-cell {
  background:var(--primary-500); color:var(--gray-0); font-weight:700; text-align:center; vertical-align:middle;
  font-size:11px; line-height:22px; word-break:break-all; white-space:normal; overflow:visible;
}
.invoicee { background:var(--alert-50); }
.invoicee.td-th { background:var(--alert-100); color:var(--alert-500); font-size:12px; white-space:nowrap; }
.invoicee.group-cell {
  background:var(--alert-500); color:var(--gray-0); font-weight:700; text-align:center; vertical-align:middle;
  font-size:11px; line-height:22px; word-break:break-all; white-space:normal; overflow:visible;
}
.ti-doc .center { text-align:center; white-space:nowrap; }
.ti-doc .right  { text-align:right;  white-space:nowrap; font-weight:500; }
.ti-doc .left   { text-align:left; }
.ti-doc .ti-purpose { text-align:center; font-size:13px; font-weight:700; vertical-align:middle; white-space:normal; }
.ti-doc .ti-cash { font-weight:700; text-align:right; background:var(--gray-50); white-space:nowrap; font-size:12px; }
.ti-footer-note { font-size:11px; font-weight:400; line-height:18px; color:var(--gray-600); margin-top:12px; }
</style>
@endpush

@section('content')

{{-- 요약 카드 — 시안에 없지만 개발에서 넣은 지표라 그대로 둔다 --}}
<div class="ti-summary">
  <div class="sum-card blue">
    <div class="sc-icon"><i class="bx bx-wallet"></i></div>
    <div><div class="sc-label">잔여 포인트</div><div class="sc-val" id="balance-val">—</div></div>
  </div>
  <div class="sum-card green">
    <div class="sc-icon"><i class="bx bx-file"></i></div>
    <div><div class="sc-label">이번 달 발행</div><div class="sc-val" id="month-count-val">—</div></div>
  </div>
  <div class="sum-card red">
    <div class="sc-icon"><i class="bx bx-x-circle"></i></div>
    <div><div class="sc-label">이번 달 취소</div><div class="sc-val" id="month-cancel-val">—</div></div>
  </div>
  <div class="sum-card gray">
    <div class="sc-icon"><i class="bx bx-won"></i></div>
    <div><div class="sc-label">이번 달 공급가액</div><div class="sc-val" id="month-amount-val">—</div></div>
  </div>
</div>

{{-- 탭: 발행 내역 / 즉시발행 (발행 내역 먼저) --}}
<div class="titab-bar">
  <button type="button" class="titab active" data-tab="hist" onclick="tiTab('hist')"><i class="bx bx-list-ul"></i> 발행 내역</button>
  <button type="button" class="titab" data-tab="issue" onclick="tiTab('issue')"><i class="bx bx-file"></i> 전자세금계산서 즉시발행</button>
</div>

<div class="ti-layout">

  {{-- ── 발행 폼 ── --}}
  <div class="ti-card" data-titab="issue">
    <div class="ti-card-head"><i class="bx bx-file"></i><span>전자세금계산서 즉시발행</span></div>
    <div class="ti-card-body">

      {{-- 기본 정보 --}}
      <div class="form-section">
        <div class="section-title"><i class="bx bx-cog"></i> 기본 정보</div>
        <div class="form-grid-2">
          <div class="form-row">
            <label class="form-label">사업자번호</label>
            <input id="corp-num" class="form-input" type="text" value="{{ $corpNum }}">
          </div>
          <div class="form-row">
            <label class="form-label">관리번호 <span style="color:var(--danger)">*</span></label>
            <div style="display:flex;gap:6px;">
              <input id="mgt-key" class="form-input" type="text" style="flex:1;" placeholder="TI-20260508-001">
              <button type="button" onclick="genMgtKey()" class="btn-search" title="자동생성"><i class="bx bx-refresh"></i></button>
            </div>
          </div>
          <div class="form-row">
            <label class="form-label">작성일자</label>
            <input id="write-date" class="form-input" type="date">
          </div>
          <div class="form-row">
            <label class="form-label">영수/청구</label>
            <select id="purpose-type" class="form-input">
              <option value="Receipt">영수</option>
              <option value="Request">청구</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">세금종류</label>
            <select id="tax-type" class="form-input" onchange="onTaxTypeChange()">
              <option value="ValueAdded">과세</option>
              <option value="ZeroTax">영세</option>
              <option value="FreeTax">면세</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">발행형태</label>
            <select id="issue-type" class="form-input">
              <option value="Normal">정발행</option>
              <option value="Blank">역발행</option>
            </select>
          </div>
        </div>
      </div>

      {{-- 공급자·공급받는자 좌우 배치 --}}
      <div class="sb-grid">
      {{-- 공급자 --}}
      <div class="form-section">
        <div class="section-title"><i class="bx bx-building"></i> 공급자 (을)</div>
        <div class="form-grid-2">
          <div class="form-row">
            <label class="form-label">사업자번호 <span style="color:var(--danger)">*</span></label>
            <input id="er-corp-num" class="form-input" type="text" placeholder="- 없이 10자리" value="{{ $corpNum }}">
          </div>
          <div class="form-row">
            <label class="form-label">상호 <span style="color:var(--danger)">*</span></label>
            <input id="er-corp-name" class="form-input" type="text" placeholder="공급자 상호">
          </div>
          <div class="form-row">
            <label class="form-label">대표자명</label>
            <input id="er-ceo-name" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">담당자명</label>
            <input id="er-contact" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">업태</label>
            <input id="er-biz-type" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">종목</label>
            <input id="er-biz-class" class="form-input" type="text">
          </div>
          <div class="form-row form-input-full" style="grid-column:1/-1;">
            <label class="form-label">주소</label>
            <input id="er-addr" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">전화번호</label>
            <input id="er-tel" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">이메일</label>
            <input id="er-email" class="form-input" type="email">
          </div>
        </div>
      </div>

      {{-- 공급받는자 --}}
      <div class="form-section">
        <div class="section-title"><i class="bx bx-user-circle"></i> 공급받는자 (갑)</div>
        <div class="form-grid-2">
          <div class="form-row">
            <label class="form-label">구분</label>
            <select id="ee-type" class="form-input">
              <option value="LGT">법인</option>
              <option value="PPL">개인</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">사업자번호 <span style="color:var(--danger)">*</span></label>
            <input id="ee-corp-num" class="form-input" type="text" placeholder="- 없이 10자리">
          </div>
          <div class="form-row">
            <label class="form-label">상호 <span style="color:var(--danger)">*</span></label>
            <input id="ee-corp-name" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">대표자명</label>
            <input id="ee-ceo-name" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">업태</label>
            <input id="ee-biz-type" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">종목</label>
            <input id="ee-biz-class" class="form-input" type="text">
          </div>
          <div class="form-row form-input-full" style="grid-column:1/-1;">
            <label class="form-label">주소</label>
            <input id="ee-addr" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">담당자명</label>
            <input id="ee-contact" class="form-input" type="text">
          </div>
          <div class="form-row">
            <label class="form-label">이메일</label>
            <input id="ee-email" class="form-input" type="email">
          </div>
        </div>
      </div>
      </div>{{-- /sb-grid --}}

      {{-- 품목 목록 --}}
      <div class="form-section">
        <div class="section-title"><i class="bx bx-list-ul"></i> 품목 목록</div>
        <div class="detail-wrap">
          <table class="detail-table" id="detail-table">
            <thead>
              <tr>
                <th style="width:48px;">월일</th>
                <th>품목</th>
                <th style="width:36px;">수량</th>
                <th style="width:64px;">단가</th>
                <th style="width:68px;">공급가액</th>
                <th style="width:56px;">세액</th>
                <th style="width:24px;"></th>
              </tr>
            </thead>
            <tbody id="detail-tbody"></tbody>
          </table>
          <button type="button" class="detail-add-btn" onclick="addDetailRow()">
            <i class="bx bx-plus"></i> 품목 추가
          </button>
        </div>

        {{-- 금액 합계 --}}
        <div class="amount-box" id="amount-box">
          <div>
            <div class="ab-label">공급가액</div>
            <div class="ab-val" id="sum-supply">0원</div>
          </div>
          <div>
            <div class="ab-label">세액</div>
            <div class="ab-val" id="sum-tax">0원</div>
          </div>
          <div>
            <div class="ab-label">합계</div>
            <div class="ab-val" id="sum-total">0원</div>
          </div>
        </div>
      </div>

      {{-- 비고 --}}
      <div class="form-section">
        <div class="section-title"><i class="bx bx-note"></i> 비고</div>
        <input id="remark1" class="form-input" type="text" placeholder="(선택) 비고란 1">
      </div>

      {{-- 발행 버튼 — 표준 버튼 규격에 맞춰 카드 오른쪽 아래로 --}}
      <div class="ti-form-actions">
        <button class="issue-btn" id="issue-btn" onclick="issueInvoice()">
          <i class="bx bx-check-circle"></i> 세금계산서 즉시발행
        </button>
      </div>

    </div>
  </div>

  {{-- ── 발행 내역 ── --}}
  <div class="ti-panel" data-titab="hist">

    <div class="hist-head-title"><i class="bx bx-list-ul"></i> 발행 내역</div>

    {{-- 검색 필터 — 흰 카드(r12 · pad 12/16) 안에 라벨 위 · 컨트롤 아래.
         GET 폼이 아니라 loadHistory() 가 읽어 가는 입력이라 <form> 으로 감싸지 않는다. --}}
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
          <label class="ds-field-label">세금종류</label>
          <select id="f-tax-type" class="form-control form-select">
            <option value="">전체</option>
            <option value="ValueAdded">과세</option>
            <option value="ZeroTax">영세</option>
            <option value="FreeTax">면세</option>
          </select>
        </div>
      </div>
      <div class="ds-filter-actions">
        <button type="button" class="ds-btn ds-btn-primary" onclick="loadHistory(1)">조회</button>
      </div>
    </div>

    {{-- 결과바(h32) 위, 그 아래 흰 카드(r12) 안에 그리드.
         그리드 툴바(엑셀 저장)와 행 선택 버튼들을 전부 여기로 올렸다. --}}
    <div class="ds-grid-section">
      <div class="ds-grid-bar">
        <div class="ds-grid-bar-left">
          <span class="ds-grid-sel">선택 <b id="taxSelCount">0</b>건</span>
        </div>
        <div class="ds-grid-bar-right">
          <span class="ds-grid-hint">행 <b>더블클릭</b> 또는 체크 후 버튼 →</span>
          <button type="button" class="ds-btn" onclick="window.__taxGrid?.downloadExcel()">엑셀 저장</button>
          <button type="button" class="ds-btn" onclick="taxRowAction('detail')"><i class="bx bx-show"></i> 선택 상세</button>
          <button type="button" class="ds-btn" onclick="taxRowAction('print')"><i class="bx bx-printer"></i> 선택 인쇄</button>
          <button type="button" class="ds-btn" style="color:var(--danger);border-color:var(--danger);" onclick="taxRowAction('cancel')"><i class="bx bx-x"></i> 선택 발행취소</button>
        </div>
      </div>

      <div class="ds-grid-card">
        <div id="taxHistGrid"></div>
        {{-- 페이지네이션은 카드 하단 줄로. renderPager() 가 display 를 켜고 끈다 --}}
        <div class="hist-pager" id="hist-pager" style="display:none;">
          <div class="pager-info" id="pager-info"></div>
          <div class="pager-btns" id="pager-btns"></div>
        </div>
      </div>
    </div>

  </div>

</div>

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal wide">
    <div class="nd-modal-head">
      <i class="bx bx-file" style="color:var(--primary);font-size:16px;"></i>
      <h3>세금계산서 상세</h3>
      <button class="nd-modal-close" onclick="closeModal('detail-modal')">&times;</button>
    </div>
    <div class="nd-modal-body" id="detail-body">
      <div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>
    </div>
  </div>
</div>

{{-- ── 발행 취소 모달 ── --}}
<div class="nd-modal-overlay" id="cancel-modal">
  <div class="nd-modal" style="width:420px;">
    <div class="nd-modal-head">
      <i class="bx bx-x-circle" style="color:var(--danger);font-size:16px;"></i>
      <h3>세금계산서 발행 취소</h3>
      <button class="nd-modal-close" onclick="closeModal('cancel-modal')">&times;</button>
    </div>
    <div class="nd-modal-body">
      <div class="cancel-note">
        <i class="bx bx-error" style="font-size:16px;flex-shrink:0;margin-top:1px;"></i>
        <span>발행 취소 후에는 되돌릴 수 없습니다. 국세청 신고 완료 후에는 취소가 제한될 수 있습니다.</span>
      </div>
      <div class="form-row" style="margin-bottom:12px;">
        <label class="form-label">관리번호</label>
        <input id="cancel-mgt-key" class="form-input" type="text" readonly style="background:var(--gray-100);">
      </div>
      <div class="form-row" style="margin-bottom:16px;">
        <label class="form-label">취소 사유 (선택)</label>
        <input id="cancel-memo" class="form-input" type="text" placeholder="예: 거래 취소">
      </div>
      <div class="ti-form-actions" style="margin-top:0;">
        <button class="issue-btn" style="background:var(--danger);" id="cancel-confirm-btn" onclick="confirmCancel()">
          <i class="bx bx-x-circle"></i> 발행 취소 확정
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
  const el = document.getElementById('taxHistGrid');
  if (!el) return;
  window.__taxGrid = new wwGrid({
    el: el,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — '선택 N건' 은 상단 결과바에 있다.
    height: 460, editable: false, rowCheckbox: true, rowNumber: true, toolbar: false, summary: false,
    footer: false,
    columns: [
      { header: '작성일',            name: 'date',   width: 100, sortable: true },
      { header: '관리번호/처방번호', name: 'mgt',    width: 170 },
      { header: '공급받는자/환자명', name: 'buyer',  width: 170, sortable: true },
      { header: '공급가액',          name: 'supply', width: 110, editor: 'number' },
      { header: '세액',              name: 'tax',    width: 90,  editor: 'number' },
      { header: '유형',              name: 'type',   width: 70,  align: 'center', sortable: true },
      { header: '상태',              name: 'status', width: 90,  align: 'center', sortable: true },
    ],
    data: [],
  });
  // 결과바 버튼(엑셀 저장)이 부르는 이름 — 기존 __taxGrid 와 같은 인스턴스다
  window.__taxinvoiceGrid = window.__taxGrid;
  window.dsBindSelCount(window.__taxGrid, 'taxSelCount');   // 결과바 '선택 N건'
  function taxOpenRow(r) {
    if (r.record_type === 'prescription') {
      // 워크스페이스 새 탭으로 (밖이면 브라우저 새 탭으로 폴백)
      ceOpenTab(BASE_URL + '/prescriptions/' + encodeURIComponent(r.rx_number),
                '처방전 관리 - ' + (r.rx_number || '신규'), 'file-edit-02');
    } else {
      openDetail('SELL', r.mgtKey);
    }
  }
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]'); if (!cell) return;
    const r = window.__taxGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (r) taxOpenRow(r);
  });
  window.taxRowAction = function (action) {
    const c = window.__taxGrid.getCheckedRows();
    if (!c.length)   { showToast('행을 먼저 체크하세요.', 'warning'); return; }
    if (c.length > 1){ showToast('한 건만 선택하세요.', 'warning'); return; }
    const r = c[0];
    if (action === 'detail') { taxOpenRow(r); return; }
    if (r.record_type === 'prescription') { showToast('처방전 항목은 인쇄/취소 대상이 아닙니다.', 'warning'); return; }
    if (action === 'print')  openPrint('SELL', r.mgtKey);
    if (action === 'cancel') { if (!r.canCancel) { showToast('이미 취소된 건입니다.', 'warning'); return; } openCancelModal(r.mgtKey); }
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
const CORP_NUM = document.getElementById('corp-num');
const TI_BASE  = BASE_URL + '/api/popbill/taxinvoice';
const HEADERS  = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };
let detailIdx = 0;
let histPage  = 1;
let cancelMgtKey = '';

/* ── 초기화 ── */
document.addEventListener('DOMContentLoaded', () => {
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth(), 1);
  document.getElementById('write-date').value = fmtDate(today);
  document.getElementById('f-start').value    = fmtDate(first);
  document.getElementById('f-end').value      = fmtDate(today);
  genMgtKey();
  addDetailRow();
  loadBalance();
  loadMonthStats();
  loadHistory(1);
});

function fmtDate(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function toApiDate(v) { return v.replace(/-/g,''); }

/* ── 관리번호 생성 ── */
function genMgtKey() {
  const now = new Date();
  const ts  = now.getFullYear() + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0')
            + String(now.getHours()).padStart(2,'0') + String(now.getMinutes()).padStart(2,'0') + String(now.getSeconds()).padStart(2,'0');
  document.getElementById('mgt-key').value = 'TI-' + ts;
}

/* ── 세금종류 변경: 면세는 세액 0 고정 ── */
function onTaxTypeChange() {
  recalc();
}

/* ── 품목 행 추가 ── */
function addDetailRow() {
  const tbody = document.getElementById('detail-tbody');
  const tr    = document.createElement('tr');
  tr.dataset.idx = detailIdx++;
  const today = new Date();
  const mm    = String(today.getMonth()+1).padStart(2,'0') + String(today.getDate()).padStart(2,'0');
  tr.innerHTML = `
    <td><input class="text-left" type="text" placeholder="${mm}" maxlength="4" style="width:100%;"></td>
    <td><input class="text-left" type="text" placeholder="품목명" style="min-width:80px;text-align:left;"></td>
    <td><input type="number" min="0" value="1" oninput="calcRow(this)"></td>
    <td><input type="number" min="0" value="0" oninput="calcRow(this)"></td>
    <td><input type="number" min="0" value="0" oninput="calcRow(this)" class="supply-cost"></td>
    <td><input type="number" min="0" value="0" class="tax-field"></td>
    <td><button type="button" class="detail-del" onclick="removeDetailRow(this)"><i class="bx bx-x"></i></button></td>`;
  tbody.appendChild(tr);
  // qty·unitCost → supply 자동계산
  const inputs = tr.querySelectorAll('input[type=number]');
  inputs[0].addEventListener('input', () => calcRow(inputs[0]));
  inputs[1].addEventListener('input', () => calcRow(inputs[1]));
}

function removeDetailRow(btn) {
  btn.closest('tr').remove();
  recalc();
}

function calcRow(input) {
  const tr    = input.closest('tr');
  const cells = tr.querySelectorAll('input[type=number]');
  const qty   = parseFloat(cells[0].value) || 0;
  const unit  = parseFloat(cells[1].value) || 0;
  const supply= qty * unit;
  cells[2].value = Math.round(supply);
  // 세액: 과세이면 10%, 나머지 0
  const taxType = document.getElementById('tax-type').value;
  cells[3].value = taxType === 'ValueAdded' ? Math.round(supply * 0.1) : 0;
  recalc();
}

function recalc() {
  const taxType = document.getElementById('tax-type').value;
  let sumSupply = 0, sumTax = 0;
  document.querySelectorAll('#detail-tbody tr').forEach(tr => {
    const cells = tr.querySelectorAll('input[type=number]');
    if (cells.length < 4) return;
    const sc = parseFloat(cells[2].value) || 0;
    let   tx = parseFloat(cells[3].value) || 0;
    if (taxType !== 'ValueAdded') { tx = 0; cells[3].value = 0; }
    sumSupply += sc;
    sumTax    += tx;
  });
  const total = sumSupply + sumTax;
  document.getElementById('sum-supply').textContent = sumSupply.toLocaleString() + '원';
  document.getElementById('sum-tax').textContent    = sumTax.toLocaleString() + '원';
  document.getElementById('sum-total').textContent  = total.toLocaleString() + '원';
}

/* ── 잔여포인트 ── */
async function loadBalance() {
  try {
    const res  = await fetch(`${TI_BASE}/balance?corp_num=${CORP_NUM.value}`, { headers: HEADERS });
    const data = await res.json();
    document.getElementById('balance-val').textContent =
      typeof data.balance === 'number' ? data.balance.toLocaleString() + ' P' : '—';
  } catch { document.getElementById('balance-val').textContent = '오류'; }
}

/* ── 월간 통계 ── */
async function loadMonthStats() {
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth(), 1);
  const sd    = toApiDate(fmtDate(first));
  const ed    = toApiDate(fmtDate(today));
  const cn    = CORP_NUM.value;

  try {
    const res  = await fetch(`${TI_BASE}/search?corp_num=${cn}&mgt_key_type=SELL&start_date=${sd}&end_date=${ed}&per_page=100`, { headers: HEADERS });
    const data = await res.json();
    const list = data.list ?? [];
    let totalSupply = 0, cancelCnt = 0;
    list.forEach(r => {
      // stateCode 500 = 취소
      if (parseInt(r.stateCode) === 500) cancelCnt++;
      else totalSupply += parseInt(r.supplyCostTotal ?? 0);
    });
    document.getElementById('month-count-val').textContent  = (data.total ?? 0).toLocaleString();
    document.getElementById('month-cancel-val').textContent = cancelCnt.toLocaleString();
    document.getElementById('month-amount-val').textContent = totalSupply.toLocaleString() + '원';
  } catch {
    ['month-count-val','month-cancel-val','month-amount-val'].forEach(id =>
      document.getElementById(id).textContent = '오류'
    );
  }
}

/* ── 세금계산서 즉시발행 ── */
async function issueInvoice() {
  const mgtKey   = document.getElementById('mgt-key').value.trim();
  const corpNum  = CORP_NUM.value.trim();
  const erCorpNum= document.getElementById('er-corp-num').value.trim().replace(/\D/g,'');
  const erName   = document.getElementById('er-corp-name').value.trim();
  const eeCorpNum= document.getElementById('ee-corp-num').value.trim().replace(/\D/g,'');
  const eeName   = document.getElementById('ee-corp-name').value.trim();

  if (!mgtKey)    { showToast('관리번호를 입력하세요.', 'danger'); return; }
  if (!erCorpNum || !erName) { showToast('공급자 사업자번호와 상호를 입력하세요.', 'danger'); return; }
  if (!eeCorpNum || !eeName) { showToast('공급받는자 사업자번호와 상호를 입력하세요.', 'danger'); return; }

  // 품목 수집
  const details = [];
  document.querySelectorAll('#detail-tbody tr').forEach(tr => {
    const text   = tr.querySelectorAll('input.text-left, input[type=text]');
    const nums   = tr.querySelectorAll('input[type=number]');
    if (nums.length < 4) return;
    details.push({
      purchase_dt : text[0]?.value ?? '',
      item_name   : text[1]?.value ?? '',
      qty         : nums[0].value,
      unit_cost   : nums[1].value,
      supply_cost : nums[2].value,
      tax         : nums[3].value,
    });
  });

  // 합계
  let sumSupply = 0, sumTax = 0;
  details.forEach(d => { sumSupply += parseInt(d.supply_cost)||0; sumTax += parseInt(d.tax)||0; });

  const btn = document.getElementById('issue-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 발행 중…';

  try {
    const res  = await fetch(`${TI_BASE}/regist-issue`, {
      method: 'POST',
      headers: HEADERS,
      body: JSON.stringify({
        corp_num:              corpNum,
        invoicer_mgt_key:      mgtKey,
        write_date:            toApiDate(document.getElementById('write-date').value),
        tax_type:              document.getElementById('tax-type').value,
        issue_type:            document.getElementById('issue-type').value,
        purpose_type:          document.getElementById('purpose-type').value,
        invoicer_corp_num:     erCorpNum,
        invoicer_corp_name:    erName,
        invoicer_ceo_name:     document.getElementById('er-ceo-name').value,
        invoicer_contact_name: document.getElementById('er-contact').value,
        invoicer_biz_type:     document.getElementById('er-biz-type').value,
        invoicer_biz_class:    document.getElementById('er-biz-class').value,
        invoicer_addr:         document.getElementById('er-addr').value,
        invoicer_tel:          document.getElementById('er-tel').value,
        invoicer_email:        document.getElementById('er-email').value,
        invoicee_type:         document.getElementById('ee-type').value,
        invoicee_corp_num:     eeCorpNum,
        invoicee_corp_name:    eeName,
        invoicee_ceo_name:     document.getElementById('ee-ceo-name').value,
        invoicee_contact_name: document.getElementById('ee-contact').value,
        invoicee_biz_type:     document.getElementById('ee-biz-type').value,
        invoicee_biz_class:    document.getElementById('ee-biz-class').value,
        invoicee_addr:         document.getElementById('ee-addr').value,
        invoicee_email:        document.getElementById('ee-email').value,
        supply_cost_total:     String(sumSupply),
        tax_total:             String(sumTax),
        total_amount:          String(sumSupply + sumTax),
        remark1:               document.getElementById('remark1').value,
        details,
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '발행 실패');

    showToast(`세금계산서 발행 완료! 국세청승인번호: ${data.ntsConfirmNum ?? data.confirmNum ?? '확인중'}`, 'success', 7000);
    genMgtKey();
    loadHistory(1);
    loadBalance();
    loadMonthStats();
  } catch(e) {
    showToast('발행 실패: ' + e.message, 'danger', 8000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-check-circle"></i> 세금계산서 즉시발행';
  }
}

/* ── 발행 내역 조회 ── */
async function loadHistory(page = 1) {
  histPage = page;
  const cn      = CORP_NUM.value;
  const sd      = toApiDate(document.getElementById('f-start').value);
  const ed      = toApiDate(document.getElementById('f-end').value);
  const taxType = document.getElementById('f-tax-type').value;
  window.__taxGrid && window.__taxGrid.setData([]);

  let url = `${TI_BASE}/search?corp_num=${cn}&mgt_key_type=SELL&start_date=${sd}&end_date=${ed}&page=${page}&per_page=15&order=D`;
  if (taxType) url += `&tax_type_code[]=${encodeURIComponent(taxType)}`;

  try {
    const res  = await fetch(url, { headers: HEADERS });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '조회 실패');

    const list = data.list ?? [];
    if (list.length === 0) {
      window.__taxGrid.setData([]);
      document.getElementById('hist-pager').style.display = 'none';
      return;
    }

    const rows = list.map(r => {
      const wDate  = (r.writeDate ?? '').replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
      const supply = parseInt(r.supplyCostTotal ?? 0);
      const tax    = parseInt(r.taxTotal ?? 0);
      if (r.record_type === 'prescription') {
        return {
          date: wDate, mgt: (r.rx_number ?? '—'), buyer: (r.invoiceeCorpName ?? '—'),
          supply, tax, type: '처방전',
          status: (r.rx_status === 'ordered' ? '주문완료' : '검수완료'),
          record_type: 'prescription', rx_number: (r.rx_number ?? ''), mgtKey: '', canCancel: false,
        };
      }
      const sc = parseInt(r.stateCode ?? 0);
      const sTxt = { 100:'임시저장', 200:'발행완료', 220:'발행완료', 300:'국세청대기', 400:'국세청완료', 500:'취소', 600:'국세청취소' }[sc] ?? String(sc);
      const ttTxt = { ValueAdded:'과세', ZeroTax:'영세', FreeTax:'면세' }[r.taxType] ?? '—';
      const mgtKey = r.invoicerMgtKey ?? r.invoiceeMgtKey ?? r.trusteeMgtKey ?? '';
      return {
        date: wDate, mgt: (mgtKey || '—'), buyer: (r.invoiceeCorpName ?? '—'),
        supply, tax, type: ttTxt, status: sTxt,
        record_type: 'tax', mgtKey, canCancel: sc !== 500,
      };
    });
    window.__taxGrid.setData(rows);

    renderPager(data.total ?? 0, page, 15);
  } catch(e) {
    window.__taxGrid && window.__taxGrid.setData([]);
    showToast('조회 실패: ' + e.message, 'error');
  }
}

function renderPager(total, page, perPage) {
  const pager = document.getElementById('hist-pager');
  const pages = Math.ceil(total / perPage);
  if (pages <= 1) { pager.style.display = 'none'; return; }
  pager.style.display = 'flex';
  document.getElementById('pager-info').textContent = `총 ${total.toLocaleString()}건`;
  const btns  = [];
  const start = Math.max(1, page-2), end = Math.min(pages, page+2);
  if (page > 1) btns.push(`<button class="pager-btn" onclick="loadHistory(${page-1})">‹</button>`);
  for (let p = start; p <= end; p++) btns.push(`<button class="pager-btn ${p===page?'active':''}" onclick="loadHistory(${p})">${p}</button>`);
  if (page < pages) btns.push(`<button class="pager-btn" onclick="loadHistory(${page+1})">›</button>`);
  document.getElementById('pager-btns').innerHTML = btns.join('');
}

/* ── 상세 보기 ── */
async function openDetail(mgtKeyType, mgtKey) {
  document.getElementById('detail-modal').classList.add('open');
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>';

  try {
    const cn  = CORP_NUM.value;
    const res = await fetch(`${TI_BASE}/info?corp_num=${cn}&mgt_key_type=${mgtKeyType}&mgt_key=${encodeURIComponent(mgtKey)}`, { headers: HEADERS });
    const r   = await res.json();
    if (!res.ok) throw new Error(r.message ?? '조회 실패');

    const sc   = parseInt(r.stateCode ?? 0);
    const sCls = sc >= 500 ? 'cancel' : (sc >= 400 ? 'nts' : (sc >= 200 ? 'issued' : 'draft'));
    const sTxt = { 100:'임시저장', 200:'발행완료', 220:'발행완료', 300:'국세청대기', 400:'국세청완료', 500:'취소', 600:'국세청취소' }[sc] ?? String(sc);
    const writeDate  = (r.writeDate ?? '').replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
    const issueDate  = (r.issueDT  ?? '').replace(/(\d{4})(\d{2})(\d{2}).*/, '$1-$2-$3') || writeDate;
    const supplyNum  = parseInt(r.supplyCostTotal ?? 0);
    const taxNum     = parseInt(r.taxTotal ?? 0);
    const totalNum   = parseInt(r.totalAmount ?? 0) || (supplyNum + taxNum);
    const supply     = supplyNum.toLocaleString();
    const tax        = taxNum.toLocaleString();
    const total      = totalNum.toLocaleString();
    const purposeTxt = r.purposeType === 'Receipt' ? '영수' : '청구';

    // 품목 행 (최소 4행) — detailList가 객체/배열 모두 대응
    const rawList = r.detailList ?? r.DetailList ?? [];
    const details = Array.isArray(rawList) ? [...rawList] : Object.values(rawList);
    while (details.length < 4) details.push({});
    const detailRows = details.map(d => {
      const dt = d.purchaseDT ?? '';
      const mm = dt.length >= 2 ? dt.slice(0, 2) : '';
      const dd = dt.length >= 4 ? dt.slice(2, 4) : '';
      const sc = (d.supplyCost != null && d.supplyCost !== '') ? parseInt(d.supplyCost).toLocaleString() : '';
      const tx = (d.tax        != null && d.tax !== '')        ? parseInt(d.tax).toLocaleString()        : '';
      const uc = (d.unitCost   != null && d.unitCost !== '')   ? parseInt(d.unitCost).toLocaleString()   : '';
      return '<tr>'
        + '<td class="center" colspan="5">'  + mm                                         + '</td>'
        + '<td class="center" colspan="5">'  + dd                                         + '</td>'
        + '<td class="left"   colspan="18">' + (d.itemName ?? '')                         + '</td>'
        + '<td colspan="10">'                + (d.spec ?? '')                             + '</td>'
        + '<td colspan="10">'                + (d.qty != null && d.qty !== '' ? d.qty:'') + '</td>'
        + '<td class="right" colspan="12">'  + uc                                         + '</td>'
        + '<td class="right" colspan="16">'  + sc                                         + '</td>'
        + '<td class="right" colspan="12">'  + tx                                         + '</td>'
        + '<td class="left"  colspan="12">'  + (d.remark ?? '')                           + '</td>'
        + '</tr>';
    }).join('');

    const cols = Array(100).fill('<col width="1%">').join('');

    document.getElementById('detail-body').innerHTML =
      '<div class="ti-doc">'
      + '<table><colgroup>' + cols + '</colgroup>'
      // ── 제목 행 ──
      + '<tbody>'
      + '<tr>'
      + '<td rowspan="2" colspan="50" class="ti-main-title-cell"><strong>전자세금계산서</strong></td>'
      + '<td rowspan="2" colspan="26"></td>'
      + '<td colspan="6" class="ti-book-label td-th">책 번호</td>'
      + '<td colspan="1" class="ti-book-sep">:</td>'
      + '<td colspan="6" class="ti-book-num"></td>'
      + '<td colspan="2" class="ti-book-unit">권</td>'
      + '<td colspan="6" class="ti-book-num"></td>'
      + '<td colspan="2" class="ti-book-unit">호</td>'
      + '<td colspan="1"></td>'
      + '</tr>'
      + '<tr>'
      + '<td colspan="6" class="ti-book-label td-th">일련번호</td>'
      + '<td colspan="1" class="ti-book-sep">:</td>'
      + '<td colspan="17" class="ti-book-num" style="font-size:10px;">' + (r.ntsconfirmNum ?? '') + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 공급자 / 공급받는자 ──
      + '<tbody>'
      + '<tr>'
      + '<td class="invoicer group-cell" rowspan="6" colspan="2">공<br>급<br>자</td>'
      + '<td class="invoicer td-th" colspan="8">등록번호</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerCorpNum ?? '') + '</td>'
      + '<td class="invoicer td-th" colspan="8">종사업장</td>'
      + '<td class="invoicer"       colspan="16"></td>'
      + '<td class="invoicee group-cell" rowspan="6" colspan="2">공<br>급<br>받<br>는<br>자</td>'
      + '<td class="invoicee td-th" colspan="8">등록번호</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeCorpNum ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">종사업장</td>'
      + '<td class="invoicee"       colspan="16"></td>'
      + '</tr>'
      + '<tr>'
      + '<td class="invoicer td-th" colspan="8">상호</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerCorpName ?? '') + '</td>'
      + '<td class="invoicer td-th" colspan="8">성명</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerCeoName ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">상호</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeCorpName ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">성명</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeCeoName ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="invoicer td-th" colspan="8">주소</td>'
      + '<td class="invoicer"       colspan="40">' + (r.invoicerAddr ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">주소</td>'
      + '<td class="invoicee"       colspan="40">' + (r.invoiceeAddr ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="invoicer td-th" colspan="8">업태</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerBizType ?? '') + '</td>'
      + '<td class="invoicer td-th" colspan="8">종목</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerBizClass ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">업태</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeBizType ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">종목</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeBizClass ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="invoicer td-th" colspan="8">담당자명</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerContactName ?? '') + '</td>'
      + '<td class="invoicer td-th" colspan="8">연락처</td>'
      + '<td class="invoicer"       colspan="16">' + (r.invoicerTEL ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">담당자명</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeContactName ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">연락처</td>'
      + '<td class="invoicee"       colspan="16">' + (r.invoiceeTEL ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="invoicer td-th" colspan="8">이메일</td>'
      + '<td class="invoicer"       colspan="40">' + (r.invoicerEmail ?? '') + '</td>'
      + '<td class="invoicee td-th" colspan="8">이메일</td>'
      + '<td class="invoicee"       colspan="40">' + (r.invoiceeEmail ?? '') + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 작성일자 / 공급가액 / 세액 ──
      + '<tbody>'
      + '<tr>'
      + '<td class="td-th center" colspan="10">작성일자</td>'
      + '<td class="td-th center" colspan="45">공급가액</td>'
      + '<td class="td-th center" colspan="45">세액</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="center" colspan="10">' + writeDate + '</td>'
      + '<td class="right"  colspan="45">' + supply    + '</td>'
      + '<td class="right"  colspan="45">' + tax       + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 비고 ──
      + '<tbody>'
      + '<tr>'
      + '<td class="td-th center" colspan="10">비고</td>'
      + '<td class="left" colspan="90">' + (r.remark1 ?? '') + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 품목 헤더 ──
      + '<tbody>'
      + '<tr>'
      + '<td class="td-th center" colspan="5">월</td>'
      + '<td class="td-th center" colspan="5">일</td>'
      + '<td class="td-th center" colspan="18">품목</td>'
      + '<td class="td-th center" colspan="10">규격</td>'
      + '<td class="td-th center" colspan="10">수량</td>'
      + '<td class="td-th center" colspan="12">단가</td>'
      + '<td class="td-th center" colspan="16">공급가액</td>'
      + '<td class="td-th center" colspan="12">세액</td>'
      + '<td class="td-th center" colspan="12">비고</td>'
      + '</tr>'
      + detailRows
      + '</tbody>'
      // ── 합계 / 영수청구 ──
      + '<tbody>'
      + '<tr>'
      + '<td class="td-th center" colspan="16">합계금액</td>'
      + '<td class="td-th center" colspan="15">현금</td>'
      + '<td class="td-th center" colspan="15">수표</td>'
      + '<td class="td-th center" colspan="15">어음</td>'
      + '<td class="td-th center" colspan="15">외상미수금</td>'
      + '<td class="ti-purpose" colspan="24" rowspan="2">이 금액을 &nbsp;[&nbsp;<b>' + purposeTxt + '</b>&nbsp;]&nbsp; 함</td>'
      + '</tr>'
      + '<tr>'
      + '<td class="ti-cash right" colspan="16">' + total + '</td>'
      + '<td class="ti-cash" colspan="15"></td>'
      + '<td class="ti-cash" colspan="15"></td>'
      + '<td class="ti-cash" colspan="15"></td>'
      + '<td class="ti-cash" colspan="15"></td>'
      + '</tr>'
      + '</tbody>'
      + '</table>'
      + '<div class="ti-footer-note">※ 본 전자세금계산서는 국세청고시에 따라 전자서명하여 팝빌에서 발행 되었습니다. (발행일자 : ' + issueDate + ')</div>'
      + '</div>'
      + '<div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;align-items:center;">'
      + '<span class="ti-badge ' + sCls + '">' + sTxt + '</span>'
      + '<button class="btn-icon print" onclick="openPrint(\'' + mgtKeyType + '\',\'' + mgtKey + '\')">'
      + '<i class="bx bx-printer"></i> 인쇄</button>'
      + '</div>';
  } catch(e) {
    document.getElementById('detail-body').innerHTML =
      `<div style="text-align:center;padding:30px;color:var(--danger);">${e.message}</div>`;
  }
}

/* ── 인쇄 (자체 출력) ── */
async function openPrint(mgtKeyType, mgtKey) {
  try {
    const cn  = CORP_NUM.value;
    const res = await fetch(`${TI_BASE}/info?corp_num=${cn}&mgt_key_type=${mgtKeyType}&mgt_key=${encodeURIComponent(mgtKey)}`, { headers: HEADERS });
    const r   = await res.json();
    if (!res.ok) throw new Error(r.message ?? '조회 실패');

    const win = window.open('', '_blank', 'width=960,height=800,scrollbars=yes');
    win.document.open();
    win.document.write(buildPrintHtml(r));
    win.document.close();
    win.addEventListener('load', () => { win.focus(); win.print(); });
  } catch(e) {
    showToast('인쇄 실패: ' + e.message, 'danger');
  }
}

function buildPrintHtml(r) {
  const writeDate  = (r.writeDate ?? '').replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
  const issueDate  = (r.issueDT  ?? '').replace(/(\d{4})(\d{2})(\d{2}).*/, '$1-$2-$3') || writeDate;
  const supplyNum  = parseInt(r.supplyCostTotal ?? 0);
  const taxNum     = parseInt(r.taxTotal ?? 0);
  const totalNum   = parseInt(r.totalAmount ?? 0) || (supplyNum + taxNum);
  const supply     = supplyNum.toLocaleString();
  const tax        = taxNum.toLocaleString();
  const total      = totalNum.toLocaleString();
  const purposeTxt = r.purposeType === 'Receipt' ? '영수' : '청구';
  const ntsNum     = r.ntsconfirmNum ?? '';

  const rawList = r.detailList ?? r.DetailList ?? [];
  const details = Array.isArray(rawList) ? [...rawList] : Object.values(rawList);
  while (details.length < 4) details.push({});

  const detailRows = details.map(d => {
    const dt = d.purchaseDT ?? '';
    const mm = dt.length >= 2 ? dt.slice(0,2) : '';
    const dd = dt.length >= 4 ? dt.slice(2,4) : '';
    const sc = (d.supplyCost != null && d.supplyCost !== '') ? parseInt(d.supplyCost).toLocaleString() : '';
    const tx = (d.tax        != null && d.tax !== '')        ? parseInt(d.tax).toLocaleString()        : '';
    const uc = (d.unitCost   != null && d.unitCost !== '')   ? parseInt(d.unitCost).toLocaleString()   : '';
    return '<tr>'
      + '<td colspan="3"  class="c">' + mm                                                + '</td>'
      + '<td colspan="3"  class="c">' + dd                                                + '</td>'
      + '<td colspan="26">'           + (d.itemName ?? '')                                + '</td>'
      + '<td colspan="12">'           + (d.spec ?? '')                                    + '</td>'
      + '<td colspan="8"  class="n">' + (d.qty != null && d.qty !== '' ? d.qty : '')     + '</td>'
      + '<td colspan="9"  class="n">' + uc                                                + '</td>'
      + '<td colspan="17" class="n">' + sc                                                + '</td>'
      + '<td colspan="14" class="n">' + tx                                                + '</td>'
      + '<td colspan="8"></td>'
      + '</tr>';
  }).join('');

  const cols = Array(100).fill('<col width="1%">').join('');

  function tbl(copyLabel) {
    return '<table><colgroup>' + cols + '</colgroup>'
      // ── 상단 헤더 ──
      + '<tbody>'
      + '<tr><th colspan="50" class="top-head">전자세금계산서</th>'
      + '<th colspan="50" class="top-head l">국세청승인번호 : <span class="mono">' + ntsNum + '</span></th></tr>'
      + '</tbody>'
      // ── 제목 + 보관용 + 책번호 ──
      + '<tbody>'
      + '<tr>'
      + '<th rowspan="2" colspan="50" class="main-title"><strong>전자세금계산서</strong></th>'
      + '<th rowspan="2" colspan="20" class="copy-label">' + copyLabel + '</th>'
      + '<th colspan="8" class="h">책번호</th><th colspan="1" class="c">:</th>'
      + '<td colspan="8"></td><td colspan="2" class="c">권</td>'
      + '<td colspan="8"></td><td colspan="2" class="c">호</td><td colspan="1"></td>'
      + '</tr>'
      + '<tr>'
      + '<th colspan="8" class="h">일련번호</th><th colspan="1" class="c">:</th>'
      + '<td colspan="21" class="mono">' + ntsNum + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 공급자 / 공급받는자 ──
      + '<tbody>'
      + '<tr>'
      + '<th colspan="3" rowspan="5" class="group-er">공<br>급<br>자</th>'
      + '<th colspan="8" class="h">등록번호</th><td colspan="23" class="c">' + (r.invoicerCorpNum ?? '') + '</td>'
      + '<th colspan="8" class="h">종사업장</th><td colspan="8" class="c"></td>'
      + '<th colspan="3" rowspan="5" class="group-ee">공<br>급<br>받<br>는<br>자</th>'
      + '<th colspan="8" class="h ee">등록번호</th><td colspan="23" class="c ee">' + (r.invoiceeCorpNum ?? '') + '</td>'
      + '<th colspan="8" class="h ee">종사업장</th><td colspan="8" class="c ee"></td>'
      + '</tr>'
      + '<tr>'
      + '<th colspan="8" class="h">상호</th><td colspan="23">' + (r.invoicerCorpName ?? '') + '</td>'
      + '<th colspan="4" class="h">성명</th><td colspan="12">' + (r.invoicerCeoName ?? '') + '</td>'
      + '<th colspan="8" class="h ee">상호</th><td colspan="23" class="ee">' + (r.invoiceeCorpName ?? '') + '</td>'
      + '<th colspan="4" class="h ee">성명</th><td colspan="12" class="ee">' + (r.invoiceeCeoName ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<th colspan="8" class="h">주소</th><td colspan="39">' + (r.invoicerAddr ?? '') + '</td>'
      + '<th colspan="8" class="h ee">주소</th><td colspan="39" class="ee">' + (r.invoiceeAddr ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<th colspan="8" class="h">업태</th><td colspan="17">' + (r.invoicerBizType ?? '') + '</td>'
      + '<th colspan="4" class="h">종목</th><td colspan="18">' + (r.invoicerBizClass ?? '') + '</td>'
      + '<th colspan="8" class="h ee">업태</th><td colspan="17" class="ee">' + (r.invoiceeBizType ?? '') + '</td>'
      + '<th colspan="4" class="h ee">종목</th><td colspan="18" class="ee">' + (r.invoiceeBizClass ?? '') + '</td>'
      + '</tr>'
      + '<tr>'
      + '<th colspan="8" class="h">이메일</th><td colspan="39">' + (r.invoicerEmail ?? '') + '</td>'
      + '<th colspan="8" class="h ee">이메일</th><td colspan="39" class="ee">' + (r.invoiceeEmail ?? '') + '</td>'
      + '</tr>'
      + '</tbody>'
      // ── 작성일자 / 공급가액 / 세액 ──
      + '<tbody>'
      + '<tr><th colspan="11" class="h">작성일자</th><th colspan="45" class="h">공급가액</th><th colspan="44" class="h">세액</th></tr>'
      + '<tr><td colspan="11" class="c">' + writeDate + '</td><td colspan="45" class="n">' + supply + '</td><td colspan="44" class="n">' + tax + '</td></tr>'
      + '</tbody>'
      // ── 비고 ──
      + '<tbody>'
      + '<tr><th colspan="11" class="h">비고</th><td colspan="89">' + (r.remark1 ?? '') + '</td></tr>'
      + '</tbody>'
      // ── 품목 ──
      + '<tbody>'
      + '<tr>'
      + '<th colspan="3"  class="h">월</th><th colspan="3"  class="h">일</th>'
      + '<th colspan="26" class="h">품목</th><th colspan="12" class="h">규격</th>'
      + '<th colspan="8"  class="h">수량</th><th colspan="9"  class="h">단가</th>'
      + '<th colspan="17" class="h">공급가액</th><th colspan="14" class="h">세액</th>'
      + '<th colspan="8"  class="h">비고</th>'
      + '</tr>'
      + detailRows
      + '</tbody>'
      // ── 합계 ──
      + '<tbody>'
      + '<tr>'
      + '<th colspan="16" class="h">합계금액</th><th colspan="16" class="h">현금</th>'
      + '<th colspan="16" class="h">수표</th><th colspan="15" class="h">어음</th>'
      + '<th colspan="15" class="h">외상미수금</th>'
      + '<td colspan="22" rowspan="2" class="purpose">이 금액을 <strong>[&nbsp;' + purposeTxt + '&nbsp;]</strong> 함</td>'
      + '</tr>'
      + '<tr>'
      + '<td colspan="16" class="n">' + total + '</td><td colspan="16" class="n"></td>'
      + '<td colspan="16" class="n"></td><td colspan="15" class="n"></td><td colspan="15" class="n"></td>'
      + '</tr>'
      + '</tbody>'
      // ── 하단 안내 ──
      + '<tbody>'
      + '<tr><td colspan="100" class="footer-note">※ 본 전자세금계산서는 국세청고시에 따라 전자서명하여 팝빌에서 발행 되었습니다. (발행일자 : ' + issueDate + ')</td></tr>'
      + '</tbody>'
      + '</table>';
  }

  return `<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"><title>전자세금계산서 인쇄</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Malgun Gothic','맑은 고딕',sans-serif;background:#fff;font-size:9pt;}
.paper{padding:8mm 10mm;}
.copy{page-break-inside:avoid;margin-bottom:4mm;}
.copy+.copy{border-top:1.5px dashed #999;padding-top:4mm;}
.copy-lbl{font-size:7.5pt;color:#777;margin-bottom:1.5mm;}
table{width:100%;border-collapse:collapse;table-layout:fixed;}
td,th{border:.5pt solid #888;padding:2pt 3pt;font-size:8.5pt;vertical-align:middle;font-weight:normal;text-align:left;overflow:hidden;}
th{background:#efefef;font-weight:600;}
.h{text-align:center;font-size:8pt;}
.c{text-align:center;}
.n{text-align:right;font-weight:600;}
.l{text-align:left;}
.mono{font-family:monospace;font-size:7.5pt;}
.top-head{background:#e8e8e8;font-size:8pt;padding:3pt 4pt;}
.main-title{text-align:center;vertical-align:middle;}
.main-title strong{font-size:17pt;font-weight:800;letter-spacing:3px;}
.copy-label{text-align:center;vertical-align:middle;font-size:8.5pt;font-weight:700;background:#f5f5f5;line-height:1.8;}
.group-er{background:#c8d8ff;font-weight:800;text-align:center;font-size:9pt;line-height:2;}
.group-ee{background:#ffc8c8;font-weight:800;text-align:center;font-size:9pt;line-height:2;}
.ee{background:#fff5f5;}
.purpose{text-align:center;font-size:10pt;font-weight:600;vertical-align:middle;}
.footer-note{border:none;font-size:7pt;color:#666;padding-top:2pt;}
tbody tr{height:18pt;}
tbody:nth-child(1) tr{height:14pt;}
tbody:nth-child(2) tr{height:26pt;}
tbody:nth-child(3) tr{height:18pt;}
tbody:nth-child(7) tr{height:20pt;}
tbody:nth-child(8) tr{height:18pt;}
@media print{
  body{margin:0;}
  .paper{padding:5mm 7mm;}
  @page{size:A4 portrait;margin:5mm;}
}
</style></head>
<body><div class="paper">
  <div class="copy"><p class="copy-lbl">▶ 공급자 보관용</p>` + tbl('공 급 자<br>(보 관 용)') + `</div>
  <div class="copy"><p class="copy-lbl">▶ 공급받는자 보관용</p>` + tbl('공급받는자<br>(보 관 용)') + `</div>
</div></body></html>`;
}

/* ── 발행 취소 모달 ── */
function openCancelModal(mgtKey) {
  cancelMgtKey = mgtKey;
  document.getElementById('cancel-mgt-key').value = mgtKey;
  document.getElementById('cancel-memo').value    = '';
  document.getElementById('cancel-modal').classList.add('open');
}

async function confirmCancel() {
  const btn = document.getElementById('cancel-confirm-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 처리 중…';

  try {
    const res  = await fetch(`${TI_BASE}/cancel-issue`, {
      method: 'POST',
      headers: HEADERS,
      body: JSON.stringify({
        corp_num:      CORP_NUM.value,
        mgt_key_type:  'SELL',
        mgt_key:       cancelMgtKey,
        memo:          document.getElementById('cancel-memo').value,
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '취소 실패');

    closeModal('cancel-modal');
    showToast('세금계산서 발행이 취소되었습니다.', 'success', 5000);
    loadHistory(histPage);
    loadMonthStats();
  } catch(e) {
    showToast('취소 실패: ' + e.message, 'danger', 7000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-x-circle"></i> 발행 취소 확정';
  }
}

/* ── 모달 공통 ── */
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
['detail-modal','cancel-modal'].forEach(id => {
  document.getElementById(id).addEventListener('click', e => {
    if (e.target === document.getElementById(id)) closeModal(id);
  });
});
</script>
@endpush
