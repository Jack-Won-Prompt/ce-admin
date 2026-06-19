{{-- resources/views/cashbill/index.blade.php --}}
@extends('layouts.app')

@section('title', '현금영수증 발행')
@section('page-title', '현금영수증 발행')
@section('breadcrumb', '홈 / 현금영수증 발행')

@section('help-title', '현금영수증 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>팝빌 API를 통해 현금영수증을 즉시 발행하고, 발행 내역을 조회·취소하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">거래 유형</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--success-light);color:var(--success);"><i class="bx bx-check"></i></div><div class="help-item-text"><b>승인거래</b> — 일반 현금결제 시 발행</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--danger-light);color:var(--danger);"><i class="bx bx-x"></i></div><div class="help-item-text"><b>취소거래</b> — 기발행 현금영수증 취소</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">사용 용도</div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--primary-light);color:var(--primary);"><i class="bx bx-user"></i></div><div class="help-item-text"><b>소득공제용</b> — 개인 소비자 (주민번호·휴대폰번호)</div></div>
  <div class="help-item"><div class="help-item-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bx bx-buildings"></i></div><div class="help-item-text"><b>지출증빙용</b> — 사업자 (사업자등록번호)</div></div>
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
  /* ── 레이아웃 ── */
  .cb-layout { display:grid; grid-template-columns:420px 1fr; gap:20px; align-items:start; }
  @media(max-width:1100px){ .cb-layout { grid-template-columns:1fr; } }

  /* ── 요약 카드 ── */
  .cb-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
  @media(max-width:900px){ .cb-summary { grid-template-columns:1fr 1fr; } }
  .sum-card {
    background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
    padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow);
  }
  .sum-card .sc-icon { width:44px; height:44px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:20px; }
  .sum-card .sc-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
  .sum-card .sc-val   { font-size:22px; font-weight:800; line-height:1; }
  .sum-card.blue  .sc-icon { background:var(--primary-light); color:var(--primary); }
  .sum-card.green .sc-icon { background:var(--success-light); color:var(--success); }
  .sum-card.red   .sc-icon { background:var(--danger-light);  color:var(--danger); }
  .sum-card.gray  .sc-icon { background:var(--border-light);  color:var(--text-muted); }

  /* ── 발행 폼 ── */
  .cb-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
  .cb-card-head { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; font-weight:700; font-size:14px; }
  .cb-card-head i { font-size:18px; color:var(--primary); }
  .cb-card-body { padding:20px; display:flex; flex-direction:column; gap:14px; }

  .form-row  { display:flex; flex-direction:column; gap:4px; }
  .form-label { font-size:11.5px; font-weight:600; color:var(--text-muted); }
  .form-input {
    height:38px; border:1px solid var(--border); border-radius:var(--radius);
    padding:0 12px; font-size:13px; color:var(--text); background:#fff; transition:border-color .15s;
  }
  .form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(27,102,245,.12); }
  select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:28px; }

  /* 금액 행 */
  .amount-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; }
  .amount-total-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 12px; background:var(--primary-light); border-radius:var(--radius);
    font-weight:700;
  }
  .amount-total-row .at-label { font-size:12px; color:var(--primary); }
  .amount-total-row .at-val   { font-size:18px; color:var(--primary); }

  /* 구분선 */
  .form-divider { border:none; border-top:1px dashed var(--border); margin:2px 0; }

  /* 신분확인번호 타입 */
  .id-type-tabs { display:flex; gap:6px; }
  .id-type-tab {
    flex:1; height:32px; border:1px solid var(--border); border-radius:var(--radius);
    background:#fff; font-size:12px; font-weight:600; cursor:pointer; color:var(--text-muted);
    transition:all .15s;
  }
  .id-type-tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }

  /* 발행 버튼 */
  .issue-btn {
    height:44px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius);
    font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center;
    justify-content:center; gap:8px; transition:background .15s;
  }
  .issue-btn:hover:not(:disabled) { background:#1554d4; }
  .issue-btn:disabled { opacity:.6; cursor:not-allowed; }

  /* ── 목록 패널 ── */
  .hist-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
  .hist-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .hist-head-title { font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; flex:1; }
  .hist-head-title i { font-size:18px; color:var(--primary); }
  .hist-filter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .hist-filter input[type=date], .hist-filter select {
    height:34px; border:1px solid var(--border); border-radius:var(--radius);
    padding:0 10px; font-size:12px; color:var(--text); background:#fff;
  }
  .hist-filter input[type=date]:focus, .hist-filter select:focus { outline:none; border-color:var(--primary); }
  .btn-search { height:34px; padding:0 14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
  .btn-search:hover { background:#1554d4; }
  .btn-sync { height:34px; padding:0 14px; background:#fff; color:var(--primary); border:1px solid var(--primary); border-radius:var(--radius); font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
  .btn-sync:hover:not(:disabled) { background:var(--primary-light); }
  .btn-sync:disabled { opacity:.6; cursor:not-allowed; }
  .sync-badge { font-size:10px; color:var(--text-muted); margin-left:4px; }

  .hist-body { overflow-x:auto; }
  .hist-table { width:100%; border-collapse:collapse; font-size:12.5px; }
  .hist-table th { padding:10px 12px; background:var(--bg); font-weight:600; font-size:11.5px; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
  .hist-table td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
  .hist-table tr:last-child td { border-bottom:none; }
  .hist-table tr:hover td { background:rgba(27,102,245,.03); }
  .hist-empty { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

  /* 상태 배지 */
  .cb-badge { display:inline-flex; align-items:center; gap:3px; padding:3px 8px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
  .cb-badge.issued  { background:var(--success-light); color:var(--success); }
  .cb-badge.cancel  { background:var(--danger-light);  color:var(--danger); }
  .cb-badge.draft   { background:var(--border-light);  color:var(--text-muted); }
  .cb-badge.nts-ok  { background:var(--info-light);    color:var(--info); }
  .cb-badge.nts-err { background:var(--warning-light); color:var(--warning); }
  .cb-badge.income  { background:var(--primary-light); color:var(--primary); }
  .cb-badge.expense { background:var(--warning-light); color:var(--warning); }
  .cb-badge.src-popbill { background:#f0f4ff; color:#3b5bdb; }
  .cb-badge.src-order   { background:#f0fdf4; color:#16a34a; }

  /* 액션 버튼 */
  .btn-icon {
    height:26px; padding:0 9px; font-size:11px; font-weight:600; border:none;
    border-radius:5px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:3px;
  }
  .btn-icon.view    { background:var(--primary-light); color:var(--primary); }
  .btn-icon.print   { background:var(--border-light);  color:var(--text-muted); }
  .btn-icon.cancel  { background:var(--danger-light);  color:var(--danger); }
  .btn-icon:hover   { filter:brightness(.93); }

  /* 페이지네이션 */
  .hist-pager { padding:12px 16px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
  .pager-info { color:var(--text-muted); }
  .pager-btns { display:flex; gap:4px; }
  .pager-btn { height:30px; padding:0 10px; border:1px solid var(--border); border-radius:var(--radius); background:#fff; font-size:12px; cursor:pointer; color:var(--text); transition:border-color .15s,background .15s; }
  .pager-btn:hover { border-color:var(--primary); color:var(--primary); }
  .pager-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
  .pager-btn:disabled { opacity:.4; cursor:not-allowed; }

  /* ── 모달 ── */
  .nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal { background:#fff; border-radius:var(--radius-lg); box-shadow:0 20px 60px rgba(0,0,0,.18); width:640px; max-width:92vw; max-height:88vh; display:flex; flex-direction:column; }
  .nd-modal-head { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
  .nd-modal-head h3 { flex:1; font-size:15px; font-weight:700; margin:0; }
  .nd-modal-close { background:none; border:none; font-size:22px; color:var(--text-muted); cursor:pointer; line-height:1; }
  .nd-modal-body  { padding:22px; overflow-y:auto; flex:1; }

  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
  .detail-item .di-label { font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
  .detail-item .di-val   { font-size:13px; font-weight:500; }
  .detail-item.full { grid-column:1/-1; }
  .detail-sep { grid-column:1/-1; border:none; border-top:1px dashed var(--border); }
  .detail-amount { grid-column:1/-1; background:var(--primary-light); border-radius:var(--radius); padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
  .detail-amount .da-label { font-size:12px; font-weight:600; color:var(--primary); }
  .detail-amount .da-val   { font-size:20px; font-weight:800; color:var(--primary); }

  /* 취소 모달 */
  .cancel-note { background:var(--danger-light); border-radius:var(--radius); padding:12px 14px; font-size:12.5px; color:var(--danger); margin-bottom:14px; display:flex; gap:8px; }

  /* ── 현금영수증 영수증 뷰 ── */
  .cbv-layout { font-size:12.5px; color:#222; }
  .cbv-header { text-align:center; padding:14px 0 10px; border-bottom:2px solid #222; margin-bottom:0; }
  .cbv-header p { font-size:17px; font-weight:800; margin:0; letter-spacing:1px; }
  .cbv-body { padding:0; }
  .cbv-body table { width:100%; border-collapse:collapse; margin-bottom:0; }
  .cbv-body table td {
    padding:7px 10px; border:1px solid #ddd; vertical-align:middle; font-size:12px;
  }
  .cbv-body table td:first-child,
  .cbv-body table td:nth-child(3) { background:#f5f5f5; font-weight:600; color:#444; white-space:nowrap; }
  .cbv-red { color:#c00; font-weight:700; }
  .cbv-sub-row { display:flex; border-bottom:1px solid #ddd; border-top:1px solid #ddd; }
  .cbv-sub-title { flex:1; padding:6px 10px; font-weight:700; font-size:12px; background:#f9f9f9; }
  .cbv-sub-title + .cbv-sub-title { border-left:1px solid #ddd; }
  .cbv-footer { border-top:1px solid #ddd; padding:10px 12px; font-size:11px; color:#666; line-height:1.7; }
  .cbv-footer p { margin:0; }
  .cbv-print-row { padding:12px 0 0; display:flex; gap:8px; justify-content:flex-end; }
</style>
@endpush

@section('content')

{{-- 요약 카드 --}}
<div class="cb-summary">
  <div class="sum-card blue">
    <div class="sc-icon"><i class="bx bx-wallet"></i></div>
    <div>
      <div class="sc-label">잔여 포인트</div>
      <div class="sc-val" id="balance-val">—</div>
    </div>
  </div>
  <div class="sum-card green">
    <div class="sc-icon"><i class="bx bx-receipt"></i></div>
    <div>
      <div class="sc-label">이번 달 발행</div>
      <div class="sc-val" id="month-count-val">—</div>
    </div>
  </div>
  <div class="sum-card red">
    <div class="sc-icon"><i class="bx bx-x-circle"></i></div>
    <div>
      <div class="sc-label">이번 달 취소</div>
      <div class="sc-val" id="month-cancel-val">—</div>
    </div>
  </div>
  <div class="sum-card gray">
    <div class="sc-icon"><i class="bx bx-won"></i></div>
    <div>
      <div class="sc-label">이번 달 합계금액</div>
      <div class="sc-val" id="month-amount-val" style="font-size:16px;">—</div>
    </div>
  </div>
</div>

{{-- 본문 --}}
<div class="cb-layout">

  {{-- ── 좌측: 발행 폼 ── --}}
  <div class="cb-card">
    <div class="cb-card-head">
      <i class="bx bx-receipt"></i>
      <span>현금영수증 즉시발행</span>
    </div>
    <div class="cb-card-body">

      {{-- 사업자번호 --}}
      <div class="form-row">
        <label class="form-label">사업자번호</label>
        <input id="corp-num" class="form-input" type="text" value="{{ $corpNum }}" placeholder="1234567890">
      </div>

      {{-- 관리번호 --}}
      <div class="form-row">
        <label class="form-label">관리번호 <span style="color:var(--danger)">*</span> <span style="font-weight:400;color:var(--text-muted)">(최대 24자, 영문·숫자·특수)</span></label>
        <div style="display:flex;gap:8px;">
          <input id="mgt-key" class="form-input" type="text" style="flex:1;" placeholder="CB-20260508-001">
          <button type="button" onclick="genMgtKey()" class="btn-search" title="자동 생성">
            <i class="bx bx-refresh"></i>
          </button>
        </div>
      </div>

      <hr class="form-divider">

      {{-- 거래 유형 / 사용 용도 --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-row">
          <label class="form-label">거래 유형 <span style="color:var(--danger)">*</span></label>
          <select id="trade-type" class="form-input">
            <option value="승인거래">승인거래</option>
            <option value="취소거래">취소거래</option>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">사용 용도 <span style="color:var(--danger)">*</span></label>
          <select id="trade-usage" class="form-input">
            <option value="소득공제용">소득공제용</option>
            <option value="지출증빙용">지출증빙용</option>
          </select>
        </div>
      </div>

      <hr class="form-divider">

      {{-- 금액 --}}
      <div class="form-row">
        <label class="form-label">금액 입력 <span style="color:var(--danger)">*</span></label>
        <div class="amount-grid">
          <div class="form-row">
            <label class="form-label" style="font-size:10.5px;">공급가액</label>
            <input id="supply-cost" class="form-input" type="number" min="0" value="0" oninput="calcAmount()">
          </div>
          <div class="form-row">
            <label class="form-label" style="font-size:10.5px;">부가세</label>
            <input id="tax" class="form-input" type="number" min="0" value="0" oninput="calcAmount()">
          </div>
          <div class="form-row">
            <label class="form-label" style="font-size:10.5px;">봉사료</label>
            <input id="service-fee" class="form-input" type="number" min="0" value="0" oninput="calcAmount()">
          </div>
        </div>
        <div class="amount-total-row" style="margin-top:6px;">
          <span class="at-label">합계금액</span>
          <span class="at-val" id="total-display">0 원</span>
          <input type="hidden" id="total-amount" value="0">
        </div>
      </div>

      <hr class="form-divider">

      {{-- 신분확인번호 --}}
      <div class="form-row">
        <label class="form-label">신분확인번호 <span style="color:var(--danger)">*</span></label>
        <div class="id-type-tabs" style="margin-bottom:6px;">
          <button type="button" class="id-type-tab active" onclick="setIdType('phone', this)">휴대폰번호</button>
          <button type="button" class="id-type-tab" onclick="setIdType('rrn', this)">주민번호</button>
          <button type="button" class="id-type-tab" onclick="setIdType('biz', this)">사업자번호</button>
        </div>
        <input id="identity-num" class="form-input" type="text" placeholder="- 없이 숫자만">
      </div>

      {{-- 고객명 --}}
      <div class="form-row">
        <label class="form-label">고객명</label>
        <input id="customer-name" class="form-input" type="text" placeholder="(선택)">
      </div>

      {{-- 품목명 --}}
      <div class="form-row">
        <label class="form-label">품목명</label>
        <input id="item-name" class="form-input" type="text" placeholder="(선택) 예: 의료용품">
      </div>

      <hr class="form-divider">

      {{-- 이메일 / 휴대폰 --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-row">
          <label class="form-label">이메일</label>
          <input id="email" class="form-input" type="email" placeholder="(선택)">
        </div>
        <div class="form-row">
          <label class="form-label">휴대폰</label>
          <input id="hp" class="form-input" type="text" placeholder="010-XXXX-XXXX" data-phone>
        </div>
      </div>

      {{-- 발행 버튼 --}}
      <button class="issue-btn" id="issue-btn" onclick="issueCashbill()">
        <i class="bx bx-check-circle"></i> 현금영수증 발행
      </button>

    </div>
  </div>

  {{-- ── 우측: 발행 내역 ── --}}
  <div class="hist-card">
    <div class="hist-head">
      <div class="hist-head-title">
        <i class="bx bx-list-ul"></i> 발행 내역
        <span class="sync-badge" id="last-sync-label"></span>
      </div>
      <div class="hist-filter">
        <input type="date" id="f-start">
        <input type="date" id="f-end">
        <select id="f-trade-type" style="height:34px;border:1px solid var(--border);border-radius:var(--radius);padding:0 8px;font-size:12px;">
          <option value="">전체 유형</option>
          <option value="승인거래">승인거래</option>
          <option value="취소거래">취소거래</option>
        </select>
        <button class="btn-search" onclick="loadHistory(1)">조회</button>
        <button class="btn-sync" id="sync-btn" onclick="syncFromPopbill()" title="팝빌에서 최신 데이터 가져오기">
          <i class="bx bx-refresh"></i> 팝빌 동기화
        </button>
      </div>
    </div>
    <div class="hist-body">
      <table class="hist-table">
        <thead>
          <tr>
            <th>거래일시</th>
            <th>번호</th>
            <th>고객명</th>
            <th>합계금액</th>
            <th>유형</th>
            <th>용도</th>
            <th>국세청</th>
            <th>출처</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="hist-tbody">
          <tr><td colspan="9" class="hist-empty">조회 버튼을 눌러 내역을 불러오세요.</td></tr>
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
  <div class="nd-modal">
    <div class="nd-modal-head">
      <i class="bx bx-receipt" style="color:var(--primary);font-size:20px;"></i>
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
      <i class="bx bx-x-circle" style="color:var(--danger);font-size:20px;"></i>
      <h3>현금영수증 취소</h3>
      <button class="nd-modal-close" onclick="closeModal('cancel-modal')">&times;</button>
    </div>
    <div class="nd-modal-body">
      <div class="cancel-note">
        <i class="bx bx-error" style="font-size:18px;flex-shrink:0;"></i>
        <span>취소 현금영수증이 발행됩니다. 취소 후에는 되돌릴 수 없습니다.</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="form-row">
          <label class="form-label">취소 관리번호 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-mgt-key" class="form-input" type="text" placeholder="CB-REVOKE-001">
        </div>
        <div class="form-row">
          <label class="form-label">원본 국세청승인번호 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-org-confirm" class="form-input" type="text" placeholder="confirmNum">
        </div>
        <div class="form-row">
          <label class="form-label">원본 거래일자 <span style="color:var(--danger)">*</span></label>
          <input id="cancel-org-date" class="form-input" type="date">
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
  loadBalance();
  loadMonthStats();
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

/* ── 잔여포인트 ── */
async function loadBalance() {
  try {
    const res  = await fetch(`${CB_BASE}/balance?corp_num=${CORP_NUM.value}`, { headers: HEADERS });
    const data = await res.json();
    document.getElementById('balance-val').textContent =
      typeof data.balance === 'number' ? data.balance.toLocaleString() + ' P' : '—';
  } catch { document.getElementById('balance-val').textContent = '오류'; }
}

/* ── 월간 통계 (팝빌 + 처방전 합산) ── */
async function loadMonthStats() {
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth(), 1);
  const sd    = toApiDate(fmtDate(first));
  const ed    = toApiDate(fmtDate(today));
  const cn    = CORP_NUM.value;

  try {
    const [pbRes, ordRes] = await Promise.all([
      fetch(`${CB_BASE}/search?corp_num=${cn}&start_date=${sd}&end_date=${ed}&per_page=500`, { headers: HEADERS }),
      fetch(`${CB_BASE}/order-receipts?corp_num=${cn}&start_date=${sd}&end_date=${ed}`, { headers: HEADERS }),
    ]);
    const pbData  = pbRes.ok  ? await pbRes.json()  : { list: [], total: 0 };
    const ordData = ordRes.ok ? await ordRes.json() : { list: [], total: 0 };

    const pbList  = pbData.list  ?? [];
    const ordList = ordData.list ?? [];

    let totalAmt = 0, cancelCnt = 0;
    pbList.forEach(r => {
      if (r.tradeType === '취소거래') cancelCnt++;
      else totalAmt += parseInt(r.totalAmount ?? 0);
    });
    ordList.forEach(r => {
      if (r.status === 'cancelled') cancelCnt++;
      else totalAmt += parseInt(r.amount ?? 0);
    });

    const totalCount = pbList.length + ordList.filter(r => r.status !== 'cancelled').length;
    document.getElementById('month-count-val').textContent  = totalCount.toLocaleString();
    document.getElementById('month-cancel-val').textContent = cancelCnt.toLocaleString();
    document.getElementById('month-amount-val').textContent = totalAmt.toLocaleString() + '원';
  } catch {
    ['month-count-val','month-cancel-val','month-amount-val'].forEach(id =>
      document.getElementById(id).textContent = '오류'
    );
  }
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
    loadBalance();
    loadMonthStats();
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
  const tbody     = document.getElementById('hist-tbody');

  tbody.innerHTML = `<tr><td colspan="9" class="hist-empty"><i class="bx bx-loader-alt bx-spin" style="font-size:20px;"></i></td></tr>`;

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
    tbody.innerHTML = `<tr><td colspan="9" class="hist-empty" style="color:var(--danger);">조회 실패: ${e.message}</td></tr>`;
  }
}

function renderHistPage(page) {
  const perPage = 15;
  const tbody   = document.getElementById('hist-tbody');
  const start   = (page - 1) * perPage;
  const slice   = _allRows.slice(start, start + perPage);

  if (slice.length === 0) {
    tbody.innerHTML = `<tr><td colspan="9" class="hist-empty">발행 내역이 없습니다.</td></tr>`;
    document.getElementById('hist-pager').style.display = 'none';
    return;
  }

  tbody.innerHTML = slice.map(r => {
    const tradeDt = (r.tradeDT ?? r.issueDT ?? '').replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5');
    const stCls   = r.tradeType === '취소거래' ? 'cancel' : 'issued';
    const usageCls= r.tradeUsage === '소득공제용' ? 'income' : 'expense';
    const ntsCls  = { '0':'draft','1':'nts-err','2':'nts-ok','3':'nts-err' }[String(r.ntsresult??'0')] ?? 'draft';
    const ntsTxt  = r._source === 'order' ? '바로빌' : ({ '0':'전송전','1':'전송중','2':'성공','3':'실패' }[String(r.ntsresult??'0')] ?? '—');
    const amt     = parseInt(r.totalAmount ?? 0).toLocaleString();
    const numTxt  = r._source === 'order'
      ? `<span style="font-size:10px;">${r.orderNumber ?? ''}<br><span style="color:var(--text-muted);">${r.rxNumber ?? ''}</span></span>`
      : `<span style="font-size:11px;font-family:monospace;">${r.mgtKey ?? '—'}</span>`;
    const srcBadge = r._source === 'order'
      ? `<span class="cb-badge src-order"><i class="bx bx-file"></i> 처방전</span>`
      : `<span class="cb-badge src-popbill"><i class="bx bx-cloud"></i> 팝빌</span>`;
    const actions = r._source === 'order'
      ? `<a class="btn-icon view" href="/prescriptions/${r.rxNumber}" target="_blank" title="처방전 보기"><i class="bx bx-link-external"></i></a>`
      : `<button class="btn-icon view"  onclick="openDetail('${r.mgtKey}')"><i class="bx bx-show"></i></button>
         <button class="btn-icon print" onclick="openPrint('${r.mgtKey}')" style="margin-left:3px;" title="인쇄"><i class="bx bx-printer"></i></button>
         ${r.tradeType !== '취소거래' ? `<button class="btn-icon cancel" onclick="openCancelModal('${r.confirmNum}','${(r.tradeDT??r.issueDT??'').slice(0,8)}')" style="margin-left:3px;" title="취소"><i class="bx bx-x"></i></button>` : ''}`;

    return `<tr>
      <td style="white-space:nowrap;font-size:11.5px;">${tradeDt}</td>
      <td>${numTxt}</td>
      <td>${r.customerName ?? '—'}</td>
      <td style="text-align:right;font-weight:600;">${amt}원</td>
      <td><span class="cb-badge ${stCls}">${r.tradeType ?? '—'}</span></td>
      <td><span class="cb-badge ${usageCls}">${r.tradeUsage ?? '—'}</span></td>
      <td><span class="cb-badge ${ntsCls}">${ntsTxt}</span></td>
      <td>${srcBadge}</td>
      <td style="white-space:nowrap;">${actions}</td>
    </tr>`;
  }).join('');

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
    loadMonthStats();
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
          <button class="btn-icon print" style="height:32px;padding:0 14px;font-size:12px;" onclick="closeModal('detail-modal');openPrint('${mgtKey}')">
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
  body {
    font-family: '맑은 고딕', 'Malgun Gothic', AppleGothic, sans-serif;
    font-size: 13px; color: #111;
    padding: 20px 24px;
  }
  .receipt { width: 100%; }
  .r-header { text-align: center; padding: 14px 0 12px; border-bottom: 2px solid #111; margin-bottom: 0; }
  .r-header p { font-size: 20px; font-weight: 800; letter-spacing: 3px; }
  .r-body table { width: 100%; border-collapse: collapse; }
  .r-body table td {
    padding: 8px 10px; border: 1px solid #bbb; font-size: 13px; vertical-align: middle;
  }
  .r-body table td:first-child,
  .r-body table td:nth-child(3) { background: #f0f0f0; font-weight: 700; color: #333; white-space: nowrap; }
  .r-sub-row { display: flex; border: 1px solid #bbb; border-top: none; }
  .r-sub-title { flex: 1; padding: 6px 10px; font-weight: 700; font-size: 13px; background: #f5f5f5; }
  .r-sub-title + .r-sub-title { border-left: 1px solid #bbb; }
  .r-red { color: #aa0000; font-weight: 700; }
  .r-footer { border-top: 1px solid #bbb; padding: 10px 12px; font-size: 11.5px; color: #555; line-height: 1.8; }
  .r-footer p { margin: 0; }
  .no-print { text-align: right; padding: 14px 0 0; }
  .no-print button {
    padding: 8px 20px; background: #1b66f5; color: #fff; border: none;
    border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;
  }
  @media print {
    body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
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
    loadMonthStats();
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
