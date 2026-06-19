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
  <div class="help-item"><div class="help-item-icon" style="background:var(--success-light);color:var(--success);"><i class="bx bx-circle"></i></div><div class="help-item-text"><b>영세</b> — 영세율(0%) 적용</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--border-light);color:var(--text-muted);"><i class="bx bx-minus-circle"></i></div><div class="help-item-text"><b>면세</b> — 부가세 없음</div></div>
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
  <div class="help-item"><div class="help-item-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bx bx-error"></i></div><div class="help-item-text">발행 후 취소하려면 "발행 취소" 버튼을 사용하세요. 국세청 신고 후에는 취소가 제한될 수 있습니다.</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bx bx-error"></i></div><div class="help-item-text">테스트 환경에서는 실제 국세청으로 전송되지 않습니다.</div></div>
</div>
@endsection

@push('styles')
<style>
/* ── 레이아웃 ── */
.ti-layout { display:grid; grid-template-columns:500px 1fr; gap:20px; align-items:start; }
@media(max-width:1200px){ .ti-layout { grid-template-columns:1fr; } }

/* ── 요약 카드 ── */
.ti-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
@media(max-width:900px){ .ti-summary { grid-template-columns:1fr 1fr; } }
.sum-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow); }
.sum-card .sc-icon { width:44px; height:44px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:20px; }
.sum-card .sc-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
.sum-card .sc-val   { font-size:22px; font-weight:800; line-height:1; }
.sum-card.blue  .sc-icon { background:var(--primary-light); color:var(--primary); }
.sum-card.green .sc-icon { background:var(--success-light); color:var(--success); }
.sum-card.red   .sc-icon { background:var(--danger-light);  color:var(--danger); }
.sum-card.gray  .sc-icon { background:var(--border-light);  color:var(--text-muted); }

/* ── 발행 폼 카드 ── */
.ti-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
.ti-card-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; font-weight:700; font-size:14px; }
.ti-card-head i { font-size:18px; color:var(--primary); }
.ti-card-body { padding:18px; display:flex; flex-direction:column; gap:0; }

/* 섹션 */
.form-section { padding:14px 0; border-bottom:1px solid var(--border); }
.form-section:last-child { border-bottom:none; }
.section-title { font-size:12px; font-weight:700; color:var(--primary); margin-bottom:12px; display:flex; align-items:center; gap:6px; text-transform:uppercase; letter-spacing:.5px; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; }

.form-row  { display:flex; flex-direction:column; gap:3px; }
.form-label { font-size:11px; font-weight:600; color:var(--text-muted); }
.form-input {
  height:36px; border:1px solid var(--border); border-radius:var(--radius);
  padding:0 10px; font-size:12.5px; color:var(--text); background:#fff; transition:border-color .15s;
}
.form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(27,102,245,.12); }
select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; padding-right:24px; }
.form-input-full { grid-column:1/-1; }

/* 금액 행 */
.amount-box {
  background:var(--primary-light); border-radius:var(--radius); padding:12px 14px;
  display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:10px;
}
.amount-box .ab-label { font-size:10.5px; font-weight:600; color:var(--primary); opacity:.8; margin-bottom:2px; }
.amount-box .ab-val   { font-size:14px; font-weight:700; color:var(--primary); }
.amount-box .ab-total { grid-column:1/-1; border-top:1px solid rgba(27,102,245,.2); padding-top:10px; display:flex; justify-content:space-between; align-items:center; }
.amount-box .ab-total-label { font-size:12px; font-weight:700; color:var(--primary); }
.amount-box .ab-total-val   { font-size:20px; font-weight:900; color:var(--primary); }

/* 품목 테이블 */
.detail-wrap { border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-top:8px; }
.detail-table { width:100%; border-collapse:collapse; font-size:11.5px; }
.detail-table th {
  padding:7px 8px; background:var(--bg); font-size:10.5px; font-weight:600;
  color:var(--text-muted); text-align:center; border-bottom:1px solid var(--border); white-space:nowrap;
}
.detail-table td { padding:5px 4px; border-bottom:1px solid var(--border); }
.detail-table tr:last-child td { border-bottom:none; }
.detail-table input {
  width:100%; height:30px; border:1px solid transparent; border-radius:4px;
  padding:0 6px; font-size:11.5px; text-align:right; background:transparent; color:var(--text);
}
.detail-table input:focus { outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 2px rgba(27,102,245,.1); }
.detail-table input.text-left { text-align:left; }
.detail-del { width:26px; height:26px; background:var(--danger-light); color:var(--danger); border:none; border-radius:4px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; margin:auto; }
.detail-del:hover { background:rgba(239,68,68,.15); }
.detail-add-btn {
  width:100%; padding:8px; background:var(--bg); border:none; border-top:1px solid var(--border);
  color:var(--primary); font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center;
  justify-content:center; gap:5px; transition:background .15s;
}
.detail-add-btn:hover { background:var(--primary-light); }

/* 발행 버튼 */
.issue-btn {
  height:44px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius);
  font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center;
  justify-content:center; gap:8px; transition:background .15s; margin-top:16px;
}
.issue-btn:hover:not(:disabled) { background:#1554d4; }
.issue-btn:disabled { opacity:.6; cursor:not-allowed; }

/* ── 내역 패널 ── */
.hist-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
.hist-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.hist-head-title { font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; flex:1; min-width:140px; }
.hist-head-title i { font-size:18px; color:var(--primary); }
.hist-filter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.hist-filter input[type=date], .hist-filter select {
  height:34px; border:1px solid var(--border); border-radius:var(--radius);
  padding:0 8px; font-size:12px; color:var(--text); background:#fff;
}
.btn-search { height:34px; padding:0 14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
.btn-search:hover { background:#1554d4; }

.hist-body { overflow-x:auto; }
.hist-table { width:100%; border-collapse:collapse; font-size:12px; }
.hist-table th { padding:9px 10px; background:var(--bg); font-weight:600; font-size:11px; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
.hist-table td { padding:9px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
.hist-table tr:last-child td { border-bottom:none; }
.hist-table tr:hover td { background:rgba(27,102,245,.03); }
.hist-empty { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

/* 상태 배지 */
.ti-badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:20px; font-size:10.5px; font-weight:600; white-space:nowrap; }
.ti-badge.draft   { background:var(--border-light);  color:var(--text-muted); }
.ti-badge.issued  { background:var(--primary-light); color:var(--primary); }
.ti-badge.nts     { background:var(--success-light); color:var(--success); }
.ti-badge.cancel  { background:var(--danger-light);  color:var(--danger); }
.ti-badge.taxvat  { background:var(--info-light);    color:var(--info); }
.ti-badge.taxzero { background:var(--success-light); color:var(--success); }
.ti-badge.taxfree { background:var(--border-light);  color:var(--text-muted); }
.ti-badge.rx      { background:#ede9fe; color:#7c3aed; }

.btn-icon { height:25px; padding:0 8px; font-size:11px; font-weight:600; border:none; border-radius:4px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:3px; }
.btn-icon.view   { background:var(--primary-light); color:var(--primary); }
.btn-icon.print  { background:var(--border-light);  color:var(--text-muted); }
.btn-icon.cancel { background:var(--danger-light);  color:var(--danger); }
.btn-icon:hover  { filter:brightness(.93); }
.btn-icon + .btn-icon { margin-left:3px; }

/* 페이지네이션 */
.hist-pager { padding:12px 16px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
.pager-info { color:var(--text-muted); }
.pager-btns { display:flex; gap:4px; }
.pager-btn { height:30px; padding:0 10px; border:1px solid var(--border); border-radius:var(--radius); background:#fff; font-size:12px; cursor:pointer; color:var(--text); transition:border-color .15s,background .15s; }
.pager-btn:hover { border-color:var(--primary); color:var(--primary); }
.pager-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }

/* ── 모달 ── */
.nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center; padding:8px; }
.nd-modal-overlay.open { display:flex; }
.nd-modal { background:#fff; border-radius:var(--radius-lg); box-shadow:0 24px 80px rgba(0,0,0,.22); width:640px; max-width:100%; max-height:calc(100vh - 16px); display:flex; flex-direction:column; }
.nd-modal.wide { width:min(1200px, calc(100vw - 16px)); max-height:calc(100vh - 16px); }
.nd-modal-head { padding:16px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; flex-shrink:0; }
.nd-modal-head h3 { flex:1; font-size:16px; font-weight:700; margin:0; }
.nd-modal-close { background:none; border:none; font-size:24px; color:var(--text-muted); cursor:pointer; line-height:1; padding:0 4px; }
.nd-modal-body  { padding:28px 32px; overflow-y:auto; flex:1; }

/* 취소 모달 */
.cancel-note { background:var(--danger-light); border-radius:var(--radius); padding:10px 12px; font-size:12px; color:var(--danger); margin-bottom:12px; display:flex; gap:8px; align-items:flex-start; }

/* ── 세금계산서 문서 양식 ── */
.ti-doc { font-family:'Malgun Gothic','맑은 고딕',sans-serif; }
.ti-doc table { width:100%; border-collapse:collapse; table-layout:fixed; }
.ti-doc td { border:1px solid #bbb; padding:5px 6px; font-size:12px; vertical-align:middle; overflow:hidden; }
.ti-doc .td-th { background:#efefef; font-weight:700; font-size:11.5px; text-align:center; color:#222; white-space:nowrap; }
.ti-main-title-cell { text-align:center; vertical-align:middle; }
.ti-main-title-cell strong { font-size:22px; font-weight:800; letter-spacing:4px; }
.ti-book-label { font-size:11px; color:#555; white-space:nowrap; }
.ti-book-sep { text-align:center; }
.ti-book-num { font-family:monospace; font-size:11px; overflow:hidden; }
.ti-book-unit { text-align:center; font-size:11px; white-space:nowrap; }
.invoicer { background:#eef2ff; }
.invoicer.td-th { background:#dbe4ff; font-size:11.5px; white-space:nowrap; }
.invoicer.group-cell {
  background:#c5d3ff; font-weight:900; text-align:center; vertical-align:middle;
  font-size:11px; line-height:2.4; word-break:break-all; white-space:normal; overflow:visible;
}
.invoicee { background:#fff5f5; }
.invoicee.td-th { background:#ffd6d6; font-size:11.5px; white-space:nowrap; }
.invoicee.group-cell {
  background:#ffbebe; font-weight:900; text-align:center; vertical-align:middle;
  font-size:11px; line-height:2.4; word-break:break-all; white-space:normal; overflow:visible;
}
.ti-doc .center { text-align:center; white-space:nowrap; }
.ti-doc .right  { text-align:right;  white-space:nowrap; font-weight:600; }
.ti-doc .left   { text-align:left; }
.ti-doc .ti-purpose { text-align:center; font-size:13px; font-weight:700; vertical-align:middle; white-space:normal; }
.ti-doc .ti-cash { font-weight:700; text-align:right; background:#fafafa; white-space:nowrap; font-size:12px; }
.ti-footer-note { font-size:11px; color:#666; margin-top:10px; line-height:1.6; }
</style>
@endpush

@section('content')

{{-- 요약 카드 --}}
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
    <div><div class="sc-label">이번 달 공급가액</div><div class="sc-val" id="month-amount-val" style="font-size:15px;">—</div></div>
  </div>
</div>

<div class="ti-layout">

  {{-- ── 좌측: 발행 폼 ── --}}
  <div class="ti-card">
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

      {{-- 발행 버튼 --}}
      <button class="issue-btn" id="issue-btn" onclick="issueInvoice()">
        <i class="bx bx-check-circle"></i> 세금계산서 즉시발행
      </button>

    </div>
  </div>

  {{-- ── 우측: 발행 내역 ── --}}
  <div class="hist-card">
    <div class="hist-head">
      <div class="hist-head-title"><i class="bx bx-list-ul"></i> 발행 내역</div>
      <div class="hist-filter">
        <input type="date" id="f-start">
        <input type="date" id="f-end">
        <select id="f-tax-type" style="height:34px;border:1px solid var(--border);border-radius:var(--radius);padding:0 8px;font-size:12px;">
          <option value="">전체</option>
          <option value="ValueAdded">과세</option>
          <option value="ZeroTax">영세</option>
          <option value="FreeTax">면세</option>
        </select>
        <button class="btn-search" onclick="loadHistory(1)">조회</button>
      </div>
    </div>
    <div class="hist-body">
      <table class="hist-table">
        <thead>
          <tr>
            <th>작성일</th>
            <th>관리번호 / 처방번호</th>
            <th>공급받는자 / 환자명</th>
            <th>공급가액</th>
            <th>세액</th>
            <th>유형</th>
            <th>상태</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="hist-tbody">
          <tr><td colspan="8" class="hist-empty">조회 버튼을 눌러 내역을 불러오세요.</td></tr>
        </tbody>
      </table>
    </div>
    <div class="hist-pager" id="hist-pager" style="display:none;">
      <div class="pager-info" id="pager-info"></div>
      <div class="pager-btns" id="pager-btns"></div>
    </div>
  </div>

</div>

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal wide">
    <div class="nd-modal-head">
      <i class="bx bx-file" style="color:var(--primary);font-size:20px;"></i>
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
      <i class="bx bx-x-circle" style="color:var(--danger);font-size:20px;"></i>
      <h3>세금계산서 발행 취소</h3>
      <button class="nd-modal-close" onclick="closeModal('cancel-modal')">&times;</button>
    </div>
    <div class="nd-modal-body">
      <div class="cancel-note">
        <i class="bx bx-error" style="font-size:18px;flex-shrink:0;margin-top:1px;"></i>
        <span>발행 취소 후에는 되돌릴 수 없습니다. 국세청 신고 완료 후에는 취소가 제한될 수 있습니다.</span>
      </div>
      <div class="form-row" style="margin-bottom:12px;">
        <label class="form-label">관리번호</label>
        <input id="cancel-mgt-key" class="form-input" type="text" readonly style="background:var(--bg);">
      </div>
      <div class="form-row" style="margin-bottom:16px;">
        <label class="form-label">취소 사유 (선택)</label>
        <input id="cancel-memo" class="form-input" type="text" placeholder="예: 거래 취소">
      </div>
      <button class="issue-btn" style="background:var(--danger);" id="cancel-confirm-btn" onclick="confirmCancel()">
        <i class="bx bx-x-circle"></i> 발행 취소 확정
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
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
  const tbody   = document.getElementById('hist-tbody');

  tbody.innerHTML = `<tr><td colspan="8" class="hist-empty"><i class="bx bx-loader-alt bx-spin" style="font-size:20px;"></i></td></tr>`;

  let url = `${TI_BASE}/search?corp_num=${cn}&mgt_key_type=SELL&start_date=${sd}&end_date=${ed}&page=${page}&per_page=15&order=D`;
  if (taxType) url += `&tax_type_code[]=${encodeURIComponent(taxType)}`;

  try {
    const res  = await fetch(url, { headers: HEADERS });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? '조회 실패');

    const list = data.list ?? [];
    if (list.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="hist-empty">발행 내역이 없습니다.</td></tr>`;
      document.getElementById('hist-pager').style.display = 'none';
      return;
    }

    tbody.innerHTML = list.map(r => {
      const wDate  = (r.writeDate ?? '').replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
      const supply = parseInt(r.supplyCostTotal ?? 0).toLocaleString();
      const tax    = parseInt(r.taxTotal ?? 0).toLocaleString();

      // ── 처방전 행 ──────────────────────────────────────────
      if (r.record_type === 'prescription') {
        const rxSCls = r.rx_status === 'ordered' ? 'nts' : 'issued';
        const rxSTxt = r.rx_status === 'ordered' ? '주문완료' : '검수완료';
        const rxNum  = r.rx_number ?? '—';
        return `<tr style="background:rgba(124,58,237,.03);border-left:3px solid #7c3aed;">
          <td style="white-space:nowrap;">${wDate}</td>
          <td style="font-size:10.5px;font-family:monospace;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${rxNum}">${rxNum}</td>
          <td>${r.invoiceeCorpName ?? '—'}</td>
          <td style="text-align:right;font-weight:600;">${supply}원</td>
          <td style="text-align:right;">${tax}원</td>
          <td><span class="ti-badge rx">처방전</span></td>
          <td><span class="ti-badge ${rxSCls}">${rxSTxt}</span></td>
          <td style="white-space:nowrap;">
            <button class="btn-icon view" onclick="window.open('/prescriptions/${rxNum}','_blank')" title="처방전 보기"><i class="bx bx-file-find"></i></button>
          </td>
        </tr>`;
      }

      // ── 세금계산서 행 ──────────────────────────────────────
      const sc      = parseInt(r.stateCode ?? 0);
      const sCls    = sc >= 500 ? 'cancel' : (sc >= 400 ? 'nts' : (sc >= 200 ? 'issued' : 'draft'));
      const sTxt    = { 100:'임시저장', 200:'발행완료', 220:'발행완료', 300:'국세청대기', 400:'국세청완료', 500:'취소', 600:'국세청취소' }[sc] ?? String(sc);
      const ttMap   = { ValueAdded:'taxvat', ZeroTax:'taxzero', FreeTax:'taxfree' };
      const ttCls   = ttMap[r.taxType] ?? 'draft';
      const ttTxt   = { ValueAdded:'과세', ZeroTax:'영세', FreeTax:'면세' }[r.taxType] ?? '—';
      const mgtKey  = r.invoicerMgtKey ?? r.invoiceeMgtKey ?? r.trusteeMgtKey ?? '';
      const canCancel = sc !== 500;

      return `<tr>
        <td style="white-space:nowrap;">${wDate}</td>
        <td style="font-size:10.5px;font-family:monospace;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${mgtKey}">${mgtKey || '—'}</td>
        <td>${r.invoiceeCorpName ?? '—'}</td>
        <td style="text-align:right;font-weight:600;">${supply}원</td>
        <td style="text-align:right;">${tax}원</td>
        <td><span class="ti-badge ${ttCls}">${ttTxt}</span></td>
        <td><span class="ti-badge ${sCls}">${sTxt}</span></td>
        <td style="white-space:nowrap;">
          <button class="btn-icon view"  onclick="openDetail('SELL','${mgtKey}')"><i class="bx bx-show"></i></button>
          <button class="btn-icon print" onclick="openPrint('SELL','${mgtKey}')" title="인쇄"><i class="bx bx-printer"></i></button>
          ${canCancel ? `<button class="btn-icon cancel" onclick="openCancelModal('${mgtKey}')" title="발행취소"><i class="bx bx-x"></i></button>` : ''}
        </td>
      </tr>`;
    }).join('');

    renderPager(data.total ?? 0, page, 15);
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="8" class="hist-empty" style="color:var(--danger);">조회 실패: ${e.message}</td></tr>`;
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
      + '<td colspan="17" class="ti-book-num" style="font-size:9px;">' + (r.ntsconfirmNum ?? '') + '</td>'
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
      + '<div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;align-items:center;">'
      + '<span class="ti-badge ' + sCls + '" style="font-size:12px;padding:4px 12px;">' + sTxt + '</span>'
      + '<button class="btn-icon print" style="height:32px;padding:0 14px;font-size:12px;" onclick="openPrint(\'' + mgtKeyType + '\',\'' + mgtKey + '\')">'
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
