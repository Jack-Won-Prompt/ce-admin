{{-- resources/views/invoice/index.blade.php --}}
@extends('layouts.app')

@section('title', '계산서 발행')
@section('page-title', '계산서 발행')
{{-- 시안 282:934 빵부스러기는 '홈 - 계산서 발행' 이다(구분자 하이픈). --}}
@section('breadcrumb', '홈 - 계산서 발행')

@push('styles')

<style>
  /* 목록에서 바로 발행하는 창 — 화면 한가운데를 덮지 않고 누른 칸 옆에 붙는다 */
  .inv-pop { position: fixed; z-index: 1200; width: 320px; background: var(--bg-card);
             border: 1px solid var(--border); border-radius: var(--radius-lg);
             box-shadow: 0 12px 36px rgba(0,0,0,.2); }
  .inv-pop-hd { display: flex; align-items: center; gap: 8px; padding: 9px 12px;
                background: var(--primary); color: #fff; font-size: 13px; font-weight: 700;
                border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
  .inv-pop-hd span { flex: 1; }
  .inv-pop-hd button { background: none; border: none; color: #fff; font-size: 16px;
                       line-height: 1; cursor: pointer; }
  .inv-pop-bd { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
  .inv-pop-order { font-size: 11px; color: var(--text-muted); }
  .inv-pop-row { display: flex; flex-direction: column; gap: 3px; }
  .inv-pop-row.two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .inv-pop-row label { font-size: 11px; font-weight: 500; color: var(--gray-700); }
  .inv-pop-row .form-control { height: 30px; font-size: 12px; }
  .inv-pop-radio { display: inline-flex; align-items: center; gap: 4px; font-size: 12px;
                   font-weight: 500; color: var(--gray-1000); cursor: pointer; }
  .inv-pop-note { font-size: 11px; color: var(--text-muted); }
  .inv-pop-acts { display: flex; gap: 6px; justify-content: flex-end; margin-top: 2px; }
  /* 목록 칸의 작은 단추 — 미발행이면 누를 수 있다는 것이 보여야 한다 */
  /* 표 안 버튼 규격은 시안이 h28 · r8 · pad 0/12 · 12/500 이다(266:66 · 342:4037).
     위임장 서명의 .pc-cellbtn 과 같은 부품이라 같은 값으로 둔다. */
  .inv-cell-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px;
                  height: 28px; padding: 0 12px; font-size: 12px; font-weight: 500; line-height:19px;
                  border-radius: 8px; border: 1px solid var(--primary); color: var(--primary);
                  background: var(--bg-card); cursor: pointer; }
  .inv-cell-btn:hover { background: var(--primary-light); }
  .inv-cell-btn.off { border-color: var(--gray-300); color: var(--gray-700); }
  .inv-cell-done { font-weight: 700; color: var(--primary); margin-right: 4px; }
</style>
<style>
/* ── 상태 칩 줄 끝 안내 ──
   요약 카드(계산서 발행 대상 / 미발행 / 이번달 금액)에 달려 있던 설명 문구를
   시안대로 카드가 사라진 자리 대신 칩 줄 끝으로 옮겼다. 12px/500 · gray-600. */
.inv-chip-note { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }

/* ── 결과바 보조 표시 (Figma 282:934 — 전체 · 선택 · 이번달 금액) ── */
.inv-stat { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-600); white-space:nowrap; }
.inv-stat b { color:var(--gray-800); font-weight:500; }
/* 항목 사이 점 — Figma 4×4 · r999 · gray-300 */
.inv-sep { width:4px; height:4px; border-radius:999px; background:var(--gray-300); flex-shrink:0; }
@media(max-width:1400px){ .ds-grid-bar { flex-wrap:wrap; height:auto; row-gap:8px; } }

/* ── 패널 탭 (계산서 발행 현황 / 상세보기) — Figma 282:934: h44 · pad 0/16 · gap 16 ──
   switchPanel() 이 '.panel-tab-btn' 으로 찾으므로 클래스는 함께 붙여 둔다. */
/* 탭 안 건수 — 시안에는 없으나 개발이 넣은 표시라 남긴다 (r999 · 10px/700) */
.badge-cnt {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:16px; height:16px; padding:0 4px; border-radius:999px;
  background:var(--gray-100); color:var(--gray-600);
  font-size:10px; font-weight:700; line-height:1;
}
.pnl-tab.active .badge-cnt { background:var(--primary-light); color:var(--primary); }
/* 행을 고르기 전에는 #detail-tab-label 이 비어 있는데, 빈 span 도 flex 아이템이라
   탭 gap 6 이 남아 탭 상자가 5px 넓어진다. 비었을 때만 접는다(내용이 들어오면 다시 뜬다). */
.pnl-tab #detail-tab-label:empty { display:none; }
/* 비활성 탭 글자 — 시안 282:934 는 #656C74(gray-600)다. 전역 .pnl-tab 은
   var(--text-muted)(#999EA4)라 이 화면에서만 맞춘다(전역 교정은 따로 올렸다).
   전역 .pnl-tab:hover/.active 와 특정성이 같아 뒤에 실리면 이 규칙이 이기므로
   활성·호버 색도 함께 다시 적는다. */
.pnl-tabs .pnl-tab { color:var(--gray-600); }
.pnl-tabs .pnl-tab:hover,
.pnl-tabs .pnl-tab.active { color:var(--primary); }

/* ── 패널 콘텐츠 ── */
.panel-body { display:none; }
.panel-body.active { display:block; }

/* ── 테이블 (레거시 잔여 — selectOrder() 가 .order-row 를 찾는다) ── */
.table-scroll { max-height:calc(100vh - 400px); overflow-y:auto; }
.table-scroll thead th { position:sticky;top:0;z-index:5;background:var(--bg); }
.order-row { cursor:pointer; transition:background .12s; }
.order-row:hover { background:var(--primary-light) !important; }
.order-row.selected { background:var(--primary-light) !important; }
.order-no { font-size:12px;font-weight:700;color:var(--primary); }
.amount-r { text-align:right;font-variant-numeric:tabular-nums; }

/* ── 상태 셀 (개발 자산 — 현재 마크업 사용처 없음. 배지 규격 r6 · pad 2/6 · 11/500 · lh18)
   초록·하늘색은 이 디자인에 없어 DS 토큰으로 바꿨다. ── */
.inv-cell { display:flex; flex-direction:column; align-items:flex-start; gap:4px; }
.inv-issued    { color:var(--primary);font-size:11px;font-weight:700; }
.inv-none      { color:var(--text-muted);font-size:11px; }
.inv-cancelled { color:var(--danger);font-size:11px;font-weight:500; }
.type-badge {
  display:inline-flex; align-items:center; gap:3px;
  padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px;
}
.type-tax  { background:var(--primary-light); color:var(--primary); }
.type-cash { background:var(--primary-100);   color:var(--primary-600); }
.type-none { background:var(--border-light);  color:var(--text-muted); }

/* ── 상세보기 패널 ── */
.detail-empty {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:60px 20px; color:var(--text-muted); gap:14px;
}
.detail-empty i { font-size:48px; opacity:.3; }
.detail-empty p { font-size:13px; }

/* Figma 282:3064 — 머리줄(주문번호 16/700 + 상태 배지), 그 아래 카드 3열 gap 12 */
.inv-detail-head { display:flex; align-items:center; gap:12px; padding:16px 16px 0; flex-wrap:wrap; }
.inv-detail-no { font-size:16px; font-weight:700; line-height:26px; color:var(--gray-1000); }
/* 머리줄 오른쪽 묶음 — 시안은 '주문 상세' 하나지만 개발이 넣은 '목록' 을 함께 둔다 */
.inv-detail-head-actions { display:flex; align-items:center; gap:8px; margin-left:auto; }

/* 패널 pad 16 → 머리줄 32 → gap 12 → 카드줄. 머리줄이 위·좌우 16 을 이미 냈으므로
   카드줄은 위 12 만 준다(시안 282:3064 Frame 48101521 pad 16 · gap 12).
   카드 3장은 같은 높이(384)로 늘어난다 — align-items 는 stretch. */
.detail-wrap { padding:12px 16px 16px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; align-items:stretch; }
@media(max-width:1200px){ .detail-wrap{grid-template-columns:1fr;} }

/* 카드 — r12 · pad 12/16 · gap 12 · bd 1px gray-200, 그림자 없음 */
.detail-section {
  display:flex; flex-direction:column; gap:12px; min-width:0;
  background:var(--gray-0); border:1px solid var(--gray-200); border-radius:12px;
  padding:12px 16px;
}
.detail-section-hd {
  display:flex; align-items:center; gap:8px; min-height:28px;
  font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000);
}
.detail-section-bd { display:flex; flex-direction:column; gap:8px; min-width:0; }

/* 항목 상자 — Figma: r8 · pad 12 · gap 8 · bg gray-100, 라벨 위 / 값 오른쪽 아래 (h74) */
.dl { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:0; }
.dl-row { display:flex; flex-direction:column; gap:8px; padding:12px; border-radius:8px; background:var(--gray-100); min-width:0; }
.dl-row.wide { grid-column:span 2; }
/* 항목 라벨은 검색 필드 라벨과 같은 gray-700 (시안 #474D54) */
.dl dt { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
.dl dd { margin:0; font-size:13px; font-weight:500; line-height:21px; color:var(--gray-1000);
  text-align:right; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* 금액 요약 세 값 중 공급가액만 primary 로 강조한다 (시안 282:3064) */
#d-ti-supply-fmt { color:var(--primary); }

.inv-status-box {
  display:flex; flex-direction:column; gap:8px;
  border-radius:8px; padding:12px; background:var(--gray-100);
  border:1px solid transparent;
}
.inv-status-box.status-issued    { border-color:var(--primary-200); }
.inv-status-box.status-cancelled { border-color:var(--alert-100); }
.inv-status-box.status-none      { border-color:transparent; }

/* 항목상자 안 라벨 — 시안은 왼쪽 위 라벨 13/500 gray-700, 값은 오른쪽 아래 */
.inv-status-name { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
.inv-status-row {
  display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:8px;
}
.inv-status-label {
  display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500; line-height:21px;
}
.inv-status-meta {
  font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600);
  display:flex; flex-wrap:wrap; gap:4px 14px;
}
.inv-status-meta span { display:inline-flex; align-items:center; gap:4px; }

/* 발행·취소 버튼 묶음 — 시안 282:3064 은 카드 머리줄 우측에 두 개가 나란히(사이 7).
   JS 가 각 상자(#d-ti-actions · #d-cr-actions)를 innerHTML 로 채운다. */
.inv-issue-actions {
  display:flex; align-items:center; gap:7px; flex-wrap:wrap;
  margin-left:auto; justify-content:flex-end;
}
.detail-action-bar { display:flex; gap:7px; flex-wrap:wrap; justify-content:flex-end; }
/* 시안 버튼 — 100×28 · r8 · pad 0/12 · gap 6 · 흰 바탕 · bd 1px gray-200 · 12/500 lh19.
   전역 .btn-success(초록)·.btn-primary(칠한 primary)는 시안에 없다.
   특정성 (0,2,0) 으로 이 자리에서만 덮는다 — 클래스 이름은 JS 가 쓰므로 두었다. */
.detail-action-bar .btn {
  height:28px; padding:0 12px; gap:6px; border-radius:8px;
  background:var(--gray-0); border:1px solid var(--gray-200); color:var(--gray-1000);
  font-size:12px; font-weight:500; line-height:19px;
}
.detail-action-bar .btn:hover { background:var(--gray-50); }

/* ── 모달 ── */
.modal-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(13,27,42,.35); backdrop-filter:blur(3px); z-index:1000;
  align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
  background:var(--gray-0); border-radius:12px;
  box-shadow:var(--shadow-lg); width:480px; max-width:95vw; max-height:90vh;
  overflow-y:auto;
}
.modal-header {
  display:flex; align-items:center; justify-content:space-between;
  /* 시안 모달(165:1314 환자 추가) — 머리 pad 16/24 · 본문 pad 24 · 바닥 pad 16/24 다.
     화면마다 11/16 · 14/16 · 14/18 · 16/20 · 18/20 여섯 가지였다. */
  padding:16px 24px; border-bottom:1px solid var(--border);
}
.modal-title  { font-size:14px;font-weight:700; }
.modal-body   { padding:24px; }
.modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
/* 모달 하단 '발행' 버튼도 전역 .btn-success(초록)를 쓰고 있다. 시안에 초록이 없어
   클래스는 그대로 두고 이 화면 안에서만 primary 로 덮는다 (.detail-action-bar 와 같은 처리). */
.modal-footer .btn-success { background:var(--primary); border-color:var(--primary); color:var(--gray-0); }
.modal-footer .btn-success:hover { background:var(--primary-dark); color:var(--gray-0); }
/* 모달 닫기 규격은 24×24 · r6 · 16px 이다(전역 .modal-close 와 같은 부품) */
.btn-close-modal { display:flex; align-items:center; justify-content:center; width:24px; height:24px; flex-shrink:0; padding:0; border:none; border-radius:6px; background:none; font-size:16px; line-height:1; color:var(--gray-500); cursor:pointer; }
.order-info-box {
  background:var(--primary-light); border:1px solid var(--primary-accent);
  border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:12px;
}
.tax-calc-row {
  background:var(--gray-100); border-radius:8px;
  padding:10px 12px; font-size:12px; margin-top:8px;
}
.tax-calc-row b { color:var(--primary); }
.amount-hint { font-size:11px;color:var(--text-muted);margin-top:3px; }
</style>
@endpush

@php
  $statusFilterTabs = [
    'all'          => ['전체',            $counts['total'],        'gray'],
    'tax_pending'  => ['세금계산서 미발행', $counts['tax_pending'],  'blue'],
    'cash_pending' => ['현금영수증 미발행', $counts['cash_pending'], 'teal'],
    'tax_issued'   => ['세금계산서 발행',   $counts['tax_issued'],   'green'],
    'cash_issued'  => ['현금영수증 발행',   $counts['cash_issued'],  'green'],
  ];
  /* 칩 라벨 뒤 괄호 설명 — 시안 282:934 는 이 문구를 칩 안에 넣는다
     ("세금계산서 미발행(주문확정 · 배송중 · 배송완료)" 268×31,
      "현금영수증 미발행(발행 대기 중)" 193×31). 라벨 표만 늘리고 위 배열은 그대로 둔다. */
  $statusChipNotes = [
    'tax_pending'  => '(주문확정 · 배송중 · 배송완료)',
    'cash_pending' => '(발행 대기 중)',
  ];
@endphp

@section('content')

{{-- DB 마이그레이션 안내 --}}
@if(!$taxColExists)
<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
  <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
  <div>
    <strong>DB 마이그레이션 필요</strong> —
    <code>database/migrations/tax_receipt_SQL.txt</code> 를 phpMyAdmin에서 실행해야
    계산서 발행/조회가 가능합니다.
  </div>
</div>
@endif

{{-- ── 상태 칩 — Figma 282:934: h31 · r999 · pad 6/10 · 12/700, 건수 배지 16×16 정원 ──
     요약 카드 5개는 시안에 없다. 건수는 칩·결과바로, 설명 문구는 칩 줄 끝 안내로 옮겼다. --}}
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}
<div class="inv-chip-note">계산서 발행 대상 — 주문확정 · 배송중 · 배송완료 · 미발행 건은 발행 대기 중 · 이번달 금액은 공급가액 / 발행금액 합계</div>


{{-- ── 검색 필터 — Figma 282:934: 흰 카드(r12 · pad 12/16), 검색어 2열 · 기간 2열 ── --}}
<form method="GET" action="{{ route('invoice.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 발행 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">발행 상태</label>
      <select name="tab" class="form-control form-select" onchange="this.form.submit()">
        @foreach($statusFilterTabs as $key => [$label, $count, $color])
          <option value="{{ $key }}" {{ $tab === $key ? 'selected' : '' }}>
            {{ $label }}{{ $statusChipNotes[$key] ?? '' }}@if($count > 0) ({{ $count }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="주문번호ㆍ이름">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" title="시작일">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" title="종료일">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    {{-- 시안 282:934 — 초기화(60×32)는 검색 왼쪽에 늘 있다.
         상태 칩(tab)만 유지한 채 같은 라우트로 되돌아가는 링크라 조건만 걷었다. --}}
    <a href="{{ route('invoice.index', ['tab'=>$tab]) }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 찾는 자리 옆에 둔다. 네비바에 두면 탭 안에서 사라진다.
         data-ce-tab 이 붙어 지금 탭을 갈아치우지 않고 새 화면 탭으로 열린다. --}}
    <a href="{{ route('orders.index') }}" class="ds-btn"
       data-ce-tab="주문현황" data-ce-icon="bx-cart">
      <i class="fa-solid fa-cart-shopping"></i> 주문 목록
    </a>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__invoiceGrid?.downloadExcel()">엑셀 저장</button>
    <button type="button" class="ds-btn" onclick="invoiceViewDetail()">선택 상세</button>
  </div>
</form>

{{-- ══════════════════════════════════════════════════════════════
     결과바(h32) + 흰 카드(r12) — 카드 안 상단이 패널 탭(발행 현황 / 상세보기)
══════════════════════════════════════════════════════════════ --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">

    {{-- 패널 탭 헤더 — 시안은 아이콘 없이 텍스트만 --}}
    <div class="pnl-tabs">
      <button type="button" class="pnl-tab panel-tab-btn active" id="btn-list" onclick="switchPanel('list')">
        <i class="fa-solid fa-list"></i> 계산서 발행 현황
        <span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span>
      </button>
      <button type="button" class="pnl-tab panel-tab-btn" id="btn-detail" onclick="switchPanel('detail')">
        상세 내용
        <span id="detail-tab-label" style="font-size:11px;color:var(--gray-600);font-weight:500;"></span>
      </button>
    </div>

  {{-- ══ 패널 1: 발행 현황 리스트 ══ --}}
  <div class="panel-body active" id="panel-list">
    <div id="invoiceGrid"></div>
  </div>{{-- /panel-list --}}

  {{-- ══ 패널 2: 상세보기 ══ --}}
  <div class="panel-body" id="panel-detail">

    {{-- 선택 전 안내 --}}
    <div class="detail-empty" id="detail-empty">
      <i class="fa-solid fa-file-magnifying-glass"></i>
      <p>좌측 <strong>계산서 발행 현황</strong> 탭에서 항목을 클릭하면<br>상세 내용이 이곳에 표시됩니다.</p>
    </div>

    {{-- 상세 내용 (선택 후 표시) --}}
    <div id="detail-content" style="display:none;">

      {{-- 헤더바 — Figma 282:3064: 주문번호 16/700 + 상태 배지, 우측 주문 상세 --}}
      <div class="inv-detail-head">
        <span id="d-order-no" class="inv-detail-no"></span>
        <span id="d-status-badge" class="badge badge-secondary"></span>
        {{-- 시안 머리줄은 왼쪽이 주문번호, 오른쪽이 '주문 상세'(95×32 · 뒤에 chevron 14×14)뿐이다.
             개발이 넣었던 '목록' 버튼은 걷었다 — 돌아가는 길은 위의 「계산서 발행 현황」 탭이다. --}}
        <div class="inv-detail-head-actions">
          {{-- 새 창이 아니라 워크스페이스 화면 탭으로 연다. 창이 따로 뜨면 보고 있던
               발행 화면과 오가기가 번거롭고, 탭 목록에도 남지 않는다. --}}
          <a id="d-order-link" href="#" class="ds-btn"
             data-ce-tab="주문" data-ce-icon="bx-cart">주문 상세 <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        </div>
      </div>

      {{-- 상세 그리드 — 시안은 3열(주문 기본 정보 / 금액 요약 / 세금계산서·현금영수증) --}}
      <div class="detail-wrap">

        {{-- 기본 정보 --}}
        <div class="detail-section">
          <div class="detail-section-hd">주문 기본 정보</div>
          <div class="detail-section-bd">
            <dl class="dl">
              <div class="dl-row"><dt>이름</dt><dd id="d-patient-name"></dd></div>
              <div class="dl-row"><dt>연락처</dt><dd id="d-patient-mobile"></dd></div>
              <div class="dl-row wide"><dt>제품명</dt><dd id="d-product-name"></dd></div>
              <div class="dl-row wide"><dt>총금액</dt><dd id="d-total-amount"></dd></div>
              <div class="dl-row"><dt>주문일</dt><dd id="d-created-at"></dd></div>
              <div class="dl-row"><dt>배송완료일</dt><dd id="d-delivered-at"></dd></div>
            </dl>
          </div>
        </div>

        {{-- 금액 요약 --}}
        <div class="detail-section">
          <div class="detail-section-hd">금액 요약</div>
          <div class="detail-section-bd">
            <dl class="dl" id="d-amount-dl">
              <div class="dl-row wide"><dt>총 결제금액</dt><dd id="d-total-fmt"></dd></div>
              <div class="dl-row wide"><dt>공급가액</dt><dd id="d-ti-supply-fmt"></dd></div>
              <div class="dl-row wide"><dt>부가세(10%)</dt><dd id="d-ti-vat-fmt"></dd></div>
            </dl>
          </div>
        </div>

        {{-- 세금계산서 / 현금영수증 — 시안 282:3064 은 카드 두 장이 아니라 한 장(504×384)이다.
             제목은 '세금계산서 / 현금영수증', 발행 버튼 둘은 머리줄 우측(사이 7),
             본문은 항목상자 두 개(각 472×74 · 라벨 위 / 값 오른쪽 아래).
             카드 제목이던 '세금계산서'·'현금영수증' 은 각 상자의 라벨로 옮겼다. --}}
        <div class="detail-section" id="d-ti-section">
          <div class="detail-section-hd">
            세금계산서 / 현금영수증
            <div class="inv-issue-actions">
              <div class="detail-action-bar" id="d-ti-actions"></div>
              <div class="detail-action-bar" id="d-cr-actions"></div>
            </div>
          </div>
          <div class="detail-section-bd">
            <div id="d-ti-box" class="inv-status-box status-none">
              <div class="inv-status-name">세금계산서</div>
              <div class="inv-status-row">
                <div class="inv-status-label" id="d-ti-status-label"></div>
              </div>
              <div class="inv-status-meta" id="d-ti-meta"></div>
            </div>
            <div id="d-cr-section">
              <div id="d-cr-box" class="inv-status-box status-none">
                <div class="inv-status-name">현금영수증</div>
                <div class="inv-status-row">
                  <div class="inv-status-label" id="d-cr-status-label"></div>
                </div>
                <div class="inv-status-meta" id="d-cr-meta"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>{{-- /detail-content --}}
  </div>{{-- /panel-detail --}}

  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}


{{-- ══════════ 세금계산서 발행 모달 ══════════ --}}
<div class="modal-overlay" id="taxModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-file-invoice" style="color:var(--primary);"></i> 세금계산서 발행</div>
      <button class="btn-close-modal" onclick="closeTaxModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="order-info-box">
        <div style="font-weight:700;font-size:13px;" id="tax_order_label">-</div>
        <div style="color:var(--text-secondary);margin-top:2px;" id="tax_amount_label">-</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 유형 <span>*</span></label>
        <select id="ti_type" class="form-control form-select" onchange="onTiTypeChange()">
          <option value="electronic">전자세금계산서</option>
          <option value="manual">일반세금계산서 (종이)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">사업자명 (공급받는자) <span>*</span></label>
        <input type="text" id="ti_biz_name" class="form-control" placeholder="○○ 주식회사">
      </div>
      <div class="form-group">
        {{-- 서버가 대표자를 받아야 발행한다. 이 칸이 없어 여기서 발행하면 늘 막혔다. --}}
        <label class="form-label">대표자 <span>*</span></label>
        <input type="text" id="ti_ceo_name" class="form-control" placeholder="홍길동">
      </div>
      <div class="form-group">
        <label class="form-label">사업자등록번호 <span>*</span></label>
        <input type="text" id="ti_biz_no" class="form-control" placeholder="000-00-00000"
               oninput="formatBizNo(this)" maxlength="12">
      </div>
      <div class="form-group" id="ti_email_wrap">
        <label class="form-label">이메일 (전자 발송용)</label>
        <input type="email" id="ti_email" class="form-control" placeholder="tax@company.com">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">공급가액 <span>*</span></label>
          <input type="number" id="ti_supply" class="form-control" placeholder="0" oninput="calcTiVat()">
        </div>
        <div class="form-group">
          <label class="form-label">부가세 (VAT 10%)</label>
          <input type="number" id="ti_vat" class="form-control" placeholder="0" oninput="updateTiCalc()">
        </div>
      </div>
      <div class="tax-calc-row">
        공급가액 <b id="ti_calc_supply">0</b>원 + 부가세 <b id="ti_calc_vat">0</b>원 = 합계 <b id="ti_calc_total">0</b>원
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeTaxModal()">취소</button>
      <button class="btn btn-success" onclick="submitTaxInvoice()">
        <i class="fa-solid fa-file-invoice"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 현금영수증 발행 모달 ══════════ --}}
<div class="modal-overlay" id="cashModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-receipt" style="color:var(--primary);"></i> 현금영수증 발행</div>
      <button class="btn-close-modal" onclick="closeCashModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="order-info-box">
        <div style="font-weight:700;font-size:13px;" id="cash_order_label">-</div>
        <div style="color:var(--text-secondary);margin-top:2px;" id="cash_amount_label">-</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 구분 <span>*</span></label>
        <div style="display:flex;gap:16px;margin-top:6px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;">
            <input type="radio" name="cr_type_r" value="income_deduction" checked onchange="onCrTypeChange()">
            소득공제 (개인)
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;">
            <input type="radio" name="cr_type_r" value="business_expense" onchange="onCrTypeChange()">
            지출증빙 (사업자)
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" id="cr_id_label">휴대폰 번호 <span>*</span></label>
        <input type="text" id="cr_identifier" class="form-control" placeholder="010-0000-0000" data-phone>
        <div class="amount-hint" id="cr_id_hint">소득공제: 환자 휴대폰 번호</div>
      </div>
      <div id="cr_autofill_wrap" style="margin:-8px 0 12px;">
        <button type="button" class="btn btn-outline btn-sm" id="cr_autofill_btn" onclick="fillPatientMobile()" style="display:none;">
          <i class="fa-solid fa-user"></i> <span id="cr_autofill_text">환자 번호 자동 입력</span>
        </button>
      </div>
      <div class="form-group">
        <label class="form-label">발행 금액 <span>*</span></label>
        <input type="number" id="cr_amount" class="form-control" placeholder="0">
        <div class="amount-hint">총 결제금액 기준 (수정 가능)</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeCashModal()">취소</button>
      <button class="btn btn-primary" onclick="submitCashReceipt()">
        <i class="fa-solid fa-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- ── 목록에서 바로 발행하는 창 ─────────────────────────────
     지금까지는 행을 골라 상세로 들어가야 발행할 수 있었다. 목록을 훑다가 미발행을
     보면 그 자리에서 끝내는 편이 빠르다 — 누른 칸 옆에 붙어 열린다. --}}
<div id="invIssuePop" class="inv-pop" style="display:none;">
  <div class="inv-pop-hd">
    <span id="invPopTitle">발행</span>
    <button type="button" onclick="closeIssuePop()" aria-label="닫기">&times;</button>
  </div>
  <div class="inv-pop-bd">
    <div class="inv-pop-order" id="invPopOrder">-</div>

    {{-- 세금계산서 --}}
    <div id="invPopTax" style="display:none;">
      <div class="inv-pop-row">
        <label>공급받는자</label>
        <div style="display:flex;gap:10px;">
          <label class="inv-pop-radio"><input type="radio" name="ip_invoicee" value="사업자" checked
                 onchange="onIpInvoicee()"> 사업자</label>
          <label class="inv-pop-radio"><input type="radio" name="ip_invoicee" value="개인"
                 onchange="onIpInvoicee()"> 개인</label>
        </div>
      </div>
      <div class="inv-pop-row"><label>상호 *</label>
        <input type="text" id="ip_biz_name" class="form-control" maxlength="100"></div>
      <div class="inv-pop-row"><label>대표자 *</label>
        <input type="text" id="ip_ceo_name" class="form-control" maxlength="50"></div>
      <div class="inv-pop-row"><label id="ip_no_label">사업자번호 *</label>
        <input type="text" id="ip_biz_no" class="form-control" maxlength="14"
               placeholder="000-00-00000"></div>
      <div class="inv-pop-row"><label>이메일</label>
        <input type="email" id="ip_email" class="form-control" maxlength="100"></div>
      <div class="inv-pop-row two">
        <div><label>공급가액 *</label>
          <input type="number" id="ip_supply" class="form-control" oninput="onIpSupply()"></div>
        <div><label>부가세</label>
          <input type="number" id="ip_vat" class="form-control"></div>
      </div>
      <div class="inv-pop-note" id="ip_person_note" style="display:none;">
        번호를 비우면 처방전에 적힌 주민등록번호로 발행합니다.
      </div>
    </div>

    {{-- 현금영수증 --}}
    <div id="invPopCash" style="display:none;">
      <div class="inv-pop-row">
        <label>거래 구분</label>
        <div style="display:flex;gap:10px;">
          <label class="inv-pop-radio"><input type="radio" name="ip_cr_type" value="income_deduction" checked
                 onchange="onIpCrType()"> 소득공제</label>
          <label class="inv-pop-radio"><input type="radio" name="ip_cr_type" value="business_expense"
                 onchange="onIpCrType()"> 지출증빙</label>
        </div>
      </div>
      <div class="inv-pop-row"><label id="ip_cr_id_label">휴대폰 번호 *</label>
        <input type="text" id="ip_cr_identifier" class="form-control" maxlength="20"></div>
      <div class="inv-pop-row"><label>발행 금액 *</label>
        <input type="number" id="ip_cr_amount" class="form-control"></div>
    </div>

    <div class="inv-pop-acts">
      <button type="button" class="ds-btn" onclick="closeIssuePop()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" id="invPopSubmit" onclick="submitIssuePop(this)">발행</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// 패널 탭 전환
// ════════════════════════════════════════════════════════════
function switchPanel(name) {
  document.querySelectorAll('.panel-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.panel-body').forEach(p => p.classList.remove('active'));
  document.getElementById('btn-' + name).classList.add('active');
  document.getElementById('panel-' + name).classList.add('active');
}

// ════════════════════════════════════════════════════════════
// 행 선택 → 상세보기 패널 채우기
// ════════════════════════════════════════════════════════════
let _curOrder = null;

function selectOrder(data) {
  _curOrder = data;

  // 행 강조
  document.querySelectorAll('.order-row').forEach(r => r.classList.remove('selected'));
  const row = document.getElementById('row-' + data.id);
  if (row) row.classList.add('selected');

  // 탭 레이블 업데이트
  document.getElementById('detail-tab-label').textContent = '— ' + data.order_number;

  // 패널 전환
  switchPanel('detail');

  // 안내 숨기고 내용 표시
  document.getElementById('detail-empty').style.display   = 'none';
  document.getElementById('detail-content').style.display = 'block';

  // ── 헤더 ──
  document.getElementById('d-order-no').textContent = data.order_number;
  const statusBadge = document.getElementById('d-status-badge');
  statusBadge.textContent  = data.status_label;
  statusBadge.style.color  = data.status_color;
  const orderLink = document.getElementById('d-order-link');
  orderLink.href = BASE_URL + '/orders/' + data.id;
  // 탭 이름에 주문번호를 붙인다 — 여러 개를 열어 두면 무엇이 무엇인지 알 수 없다
  orderLink.dataset.ceTab = '주문 - ' + data.order_number;

  // ── 기본 정보 ──
  document.getElementById('d-patient-name').textContent   = data.patient_name;
  document.getElementById('d-patient-mobile').textContent = data.patient_mobile || '-';
  document.getElementById('d-product-name').textContent   = data.product_name;
  document.getElementById('d-total-amount').textContent   = fmt(data.total_amount) + '원';
  document.getElementById('d-created-at').textContent     = data.created_at;
  document.getElementById('d-delivered-at').textContent   = data.delivered_at;

  // ── 금액 요약 ──
  const supply = Math.round(data.total_amount / 1.1);
  const vat    = data.total_amount - supply;
  document.getElementById('d-total-fmt').textContent    = fmt(data.total_amount) + '원';
  document.getElementById('d-ti-supply-fmt').textContent = fmt(supply) + '원';
  document.getElementById('d-ti-vat-fmt').textContent   = fmt(Math.round(vat)) + '원';

  // ── 세금계산서 ──
  renderTiSection(data);

  // ── 현금영수증 ──
  renderCrSection(data);
}

function fmt(n) { return Number(n||0).toLocaleString('ko-KR'); }

// ─── 세금계산서 섹션 렌더 ───
function renderTiSection(data) {
  const box    = document.getElementById('d-ti-box');
  const label  = document.getElementById('d-ti-status-label');
  const meta   = document.getElementById('d-ti-meta');
  const actions = document.getElementById('d-ti-actions');

  box.className = 'inv-status-box';

  if (!data.tax_col_exists) {
    box.classList.add('status-none');
    label.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> DB 마이그레이션 필요';
    meta.innerHTML  = '';
    actions.innerHTML = '';
    return;
  }

  if (data.ti_status === 'issued') {
    box.classList.add('status-issued');
    label.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
      <span style="color:var(--success);">발행 완료</span>`;
    meta.innerHTML = `
      <span><i class="fa-solid fa-hashtag"></i> ${data.ti_no}</span>
      <span><i class="fa-solid fa-building"></i> ${data.ti_biz_name} (${data.ti_biz_no})</span>
      <span><i class="fa-solid fa-calendar"></i> ${data.ti_issued_at}</span>
      <span><i class="fa-solid fa-won-sign"></i> 공급가액 ${fmt(data.ti_supply)}원 + VAT ${fmt(data.ti_vat)}원</span>
      ${data.ti_email ? `<span><i class="fa-solid fa-envelope"></i> ${data.ti_email}</span>` : ''}
    `;
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm" style="color:var(--danger);"
              onclick="cancelTax(${data.id}, '${data.order_number}')">
        <i class="fa-solid fa-ban"></i> 세금계산서 취소
      </button>`;
  } else if (data.ti_status === 'cancelled') {
    box.classList.add('status-cancelled');
    label.innerHTML = `<i class="fa-solid fa-ban" style="color:var(--danger);"></i>
      <span style="color:var(--danger);">취소됨</span>`;
    meta.innerHTML = data.ti_cancelled_at
      ? `<span><i class="fa-solid fa-calendar"></i> 취소일시: ${data.ti_cancelled_at}</span>` : '';
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm"
              onclick="openTaxModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount})">
        <i class="fa-solid fa-file-invoice"></i> 재발행
      </button>`;
  } else {
    box.classList.add('status-none');
    label.innerHTML = `<i class="fa-solid fa-minus" style="color:var(--text-muted);"></i>
      <span style="color:var(--text-muted);">미발행</span>`;
    meta.innerHTML  = '<span style="font-size:12px;color:var(--text-muted);">세금계산서가 발행되지 않았습니다.</span>';
    actions.innerHTML = `
      <button class="btn btn-success btn-sm"
              onclick="openTaxModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount})">
        <i class="fa-solid fa-file-invoice"></i> 세금계산서 발행
      </button>`;
  }
}

// ─── 현금영수증 섹션 렌더 ───
function renderCrSection(data) {
  const box    = document.getElementById('d-cr-box');
  const label  = document.getElementById('d-cr-status-label');
  const meta   = document.getElementById('d-cr-meta');
  const actions = document.getElementById('d-cr-actions');

  box.className = 'inv-status-box';

  if (!data.tax_col_exists) {
    box.classList.add('status-none');
    label.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> DB 마이그레이션 필요';
    meta.innerHTML  = '';
    actions.innerHTML = '';
    return;
  }

  if (data.cr_status === 'issued') {
    box.classList.add('status-issued');
    label.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
      <span style="color:var(--success);">발행 완료</span>`;
    meta.innerHTML = `
      <span><i class="fa-solid fa-hashtag"></i> ${data.cr_no}</span>
      <span><i class="fa-solid fa-tag"></i> ${data.cr_type_label}</span>
      <span><i class="fa-solid fa-phone"></i> ${data.cr_identifier}</span>
      <span><i class="fa-solid fa-won-sign"></i> ${fmt(data.cr_amount)}원</span>
      <span><i class="fa-solid fa-calendar"></i> ${data.cr_issued_at}</span>
    `;
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm" style="color:var(--danger);"
              onclick="cancelCash(${data.id}, '${data.order_number}')">
        <i class="fa-solid fa-ban"></i> 현금영수증 취소
      </button>`;
  } else if (data.cr_status === 'cancelled') {
    box.classList.add('status-cancelled');
    label.innerHTML = `<i class="fa-solid fa-ban" style="color:var(--danger);"></i>
      <span style="color:var(--danger);">취소됨</span>`;
    meta.innerHTML = data.cr_cancelled_at
      ? `<span><i class="fa-solid fa-calendar"></i> 취소일시: ${data.cr_cancelled_at}</span>` : '';
    actions.innerHTML = `
      <button class="btn btn-outline btn-sm"
              onclick="openCashModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount}, '${data.patient_mobile}')">
        <i class="fa-solid fa-receipt"></i> 재발행
      </button>`;
  } else {
    box.classList.add('status-none');
    label.innerHTML = `<i class="fa-solid fa-minus" style="color:var(--text-muted);"></i>
      <span style="color:var(--text-muted);">미발행</span>`;
    meta.innerHTML = '<span style="font-size:12px;color:var(--text-muted);">현금영수증이 발행되지 않았습니다.</span>';
    actions.innerHTML = `
      <button class="btn btn-primary btn-sm"
              onclick="openCashModal(${data.id}, '${data.order_number}', '${data.patient_name}', ${data.total_amount}, '${data.patient_mobile}')">
        <i class="fa-solid fa-receipt"></i> 현금영수증 발행
      </button>`;
  }
}

// ════════════════════════════════════════════════════════════
// 세금계산서 모달
// ════════════════════════════════════════════════════════════
let _taxOrderId = null;

function openTaxModal(orderId, orderNo, patientName, totalAmount) {
  _taxOrderId = orderId;
  document.getElementById('tax_order_label').textContent  = `${orderNo}  —  ${patientName}`;
  document.getElementById('tax_amount_label').textContent = `총 결제금액: ${fmt(totalAmount)}원`;
  const supply = Math.round(totalAmount / 1.1);
  const vat    = totalAmount - supply;
  document.getElementById('ti_supply').value = supply;
  document.getElementById('ti_vat').value    = Math.round(vat);
  updateTiCalc();
  document.getElementById('ti_biz_name').value = '';
  document.getElementById('ti_biz_no').value   = '';
  document.getElementById('ti_email').value    = '';
  document.getElementById('taxModal').classList.add('open');
}
function closeTaxModal() { document.getElementById('taxModal').classList.remove('open'); }

function onTiTypeChange() {
  const isElec = document.getElementById('ti_type').value === 'electronic';
  document.getElementById('ti_email_wrap').style.display = isElec ? 'block' : 'none';
}
function formatBizNo(el) {
  let v = el.value.replace(/[^0-9]/g,'');
  if (v.length > 5)      v = v.slice(0,3)+'-'+v.slice(3,5)+'-'+v.slice(5,10);
  else if (v.length > 3) v = v.slice(0,3)+'-'+v.slice(3);
  el.value = v;
}
function calcTiVat() {
  const supply = parseInt(document.getElementById('ti_supply').value)||0;
  document.getElementById('ti_vat').value = Math.round(supply * 0.1);
  updateTiCalc();
}
function updateTiCalc() {
  const s = parseInt(document.getElementById('ti_supply').value)||0;
  const v = parseInt(document.getElementById('ti_vat').value)||0;
  document.getElementById('ti_calc_supply').textContent = s.toLocaleString('ko-KR');
  document.getElementById('ti_calc_vat').textContent    = v.toLocaleString('ko-KR');
  document.getElementById('ti_calc_total').textContent  = (s+v).toLocaleString('ko-KR');
}

async function submitTaxInvoice() {
  if (!_taxOrderId) return;
  const bizName = document.getElementById('ti_biz_name').value.trim();
  const bizNo   = document.getElementById('ti_biz_no').value.trim();
  const supply  = parseInt(document.getElementById('ti_supply').value)||0;
  const vat     = parseInt(document.getElementById('ti_vat').value)||0;
  if (!bizName) { showToast('사업자명을 입력해주세요.', 'warning'); return; }
  if (!bizNo)   { showToast('사업자등록번호를 입력해주세요.', 'warning'); return; }
  if (supply <= 0) { showToast('공급가액을 입력해주세요.', 'warning'); return; }

  const ceoName = document.getElementById('ti_ceo_name').value.trim();
  if (!ceoName) { showToast('대표자를 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(`${BASE_URL}/orders/${_taxOrderId}/tax-invoice`, 'POST', {
    tax_invoice_type:     document.getElementById('ti_type').value,
    tax_invoice_biz_name: bizName,
    tax_invoice_ceo_name: ceoName,
    tax_invoice_biz_no:   bizNo,
    tax_invoice_email:    document.getElementById('ti_email').value.trim() || null,
    tax_invoice_supply:   supply,
    tax_invoice_vat:      vat,
  });
  if (res.success) {
    showToast(`세금계산서 발행 완료 (${res.tax_invoice_no})`, 'success', 5000);
    closeTaxModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelTax(orderId, orderNo) {
  if (!await ceConfirm(`${orderNo} 주문의 세금계산서를 취소하시겠습니까?`,
                       { tone: 'danger', confirmText: '취소 처리' })) return;
  const res = await apiRequest(`${BASE_URL}/orders/${orderId}/tax-invoice`, 'DELETE');
  if (res.success) {
    showToast('세금계산서가 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ════════════════════════════════════════════════════════════
// 현금영수증 모달
// ════════════════════════════════════════════════════════════
let _cashOrderId   = null;
let _patientMobile = '';

function openCashModal(orderId, orderNo, patientName, totalAmount, patientMobile) {
  _cashOrderId   = orderId;
  _patientMobile = patientMobile || '';
  document.getElementById('cash_order_label').textContent  = `${orderNo}  —  ${patientName}`;
  document.getElementById('cash_amount_label').textContent = `총 결제금액: ${fmt(totalAmount)}원`;
  document.getElementById('cr_amount').value     = totalAmount;
  document.getElementById('cr_identifier').value = '';
  const btn  = document.getElementById('cr_autofill_btn');
  const text = document.getElementById('cr_autofill_text');
  if (_patientMobile) {
    btn.style.display = 'inline-flex';
    text.textContent  = `환자 번호 자동 입력 (${_patientMobile})`;
  } else {
    btn.style.display = 'none';
  }
  document.querySelector('input[name="cr_type_r"][value="income_deduction"]').checked = true;
  onCrTypeChange();
  document.getElementById('cashModal').classList.add('open');
}
function closeCashModal() { document.getElementById('cashModal').classList.remove('open'); }

function onCrTypeChange() {
  const isBiz  = document.querySelector('input[name="cr_type_r"]:checked').value === 'business_expense';
  const idEl   = document.getElementById('cr_identifier');
  document.getElementById('cr_id_label').innerHTML = isBiz
    ? '사업자등록번호 <span>*</span>' : '휴대폰 번호 <span>*</span>';
  document.getElementById('cr_id_hint').textContent = isBiz
    ? '지출증빙: 사업자등록번호 입력' : '소득공제: 환자 휴대폰 번호';
  idEl.placeholder = isBiz ? '000-00-00000' : '010-0000-0000';
  idEl.value = '';
  if (isBiz) { idEl.removeAttribute('data-phone'); } else { idEl.setAttribute('data-phone', ''); }
  document.getElementById('cr_autofill_btn').style.display = (!isBiz && _patientMobile) ? 'inline-flex' : 'none';
}
function fillPatientMobile() {
  document.getElementById('cr_identifier').value = _patientMobile;
}

async function submitCashReceipt() {
  if (!_cashOrderId) return;
  const type       = document.querySelector('input[name="cr_type_r"]:checked').value;
  const identifier = document.getElementById('cr_identifier').value.trim();
  const amount     = parseFloat(document.getElementById('cr_amount').value)||0;
  if (!identifier) { showToast('식별번호(휴대폰/사업자)를 입력해주세요.', 'warning'); return; }
  if (amount <= 0)  { showToast('금액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(`${BASE_URL}/orders/${_cashOrderId}/cash-receipt`, 'POST', {
    cash_receipt_type:       type,
    cash_receipt_identifier: identifier,
    cash_receipt_amount:     amount,
  });
  if (res.success) {
    showToast(`현금영수증 발행 완료 (${res.cash_receipt_no})`, 'success', 5000);
    closeCashModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelCash(orderId, orderNo) {
  if (!await ceConfirm(`${orderNo} 주문의 현금영수증을 취소하시겠습니까?`,
                       { tone: 'danger', confirmText: '취소 처리' })) return;
  const res = await apiRequest(`${BASE_URL}/orders/${orderId}/cash-receipt`, 'DELETE');
  if (res.success) {
    showToast('현금영수증이 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

/* ════════════════════════════════════════════════════════════
   목록 칸에서 바로 발행·취소
   행을 골라 상세로 들어갔다 나오는 걸음을 없앤다. 창은 누른 칸 옆에 붙어 열린다.
   ════════════════════════════════════════════════════════════ */
let _ipKind = null, _ipRow = null;

/* 칸 그리기 — 미발행은 누를 수 있는 단추로, 발행된 건은 상태와 취소로.
   renderer 는 노드를 돌려줘야 한다. 문자열을 주면 글자 그대로 찍힌다. */
function issueCell(kind, value, row) {
  const wrap = document.createElement('span');
  wrap.style.cssText = 'display:inline-flex;align-items:center;gap:4px;';

  if (value === '발행완료') {
    const done = document.createElement('span');
    done.className = 'inv-cell-done';
    done.textContent = '발행완료';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'inv-cell-btn off';
    cancel.textContent = '취소';
    cancel.onclick = (e) => {
      e.stopPropagation();
      (kind === 'tax' ? cancelTax : cancelCash)(row.id, row.order_number);
    };
    wrap.append(done, cancel);
    return wrap;
  }

  if (value === '미발행') {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'inv-cell-btn';
    btn.textContent = '발행';
    btn.onclick = (e) => { e.stopPropagation(); openIssuePop(kind, row, btn); };
    wrap.appendChild(btn);
    return wrap;
  }

  wrap.textContent = value ?? '';
  return wrap;
}

function openIssuePop(kind, row, anchor) {
  _ipKind = kind;
  _ipRow  = row;

  const pop = document.getElementById('invIssuePop');
  document.getElementById('invPopTitle').textContent = kind === 'tax' ? '세금계산서 발행' : '현금영수증 발행';
  document.getElementById('invPopOrder').textContent =
    row.order_number + ' · ' + row.patient_name + ' · ' + fmt(row.total_amount) + '원';
  document.getElementById('invPopTax').style.display  = kind === 'tax'  ? '' : 'none';
  document.getElementById('invPopCash').style.display = kind === 'cash' ? '' : 'none';

  if (kind === 'tax') {
    // 총액에서 갈라 둔다. 공급가 = 총액/1.1(반올림), 나머지가 부가세다.
    const total  = Number(row.total_amount) || 0;
    const supply = Math.round(total / 1.1);
    document.querySelector('input[name="ip_invoicee"][value="사업자"]').checked = true;
    document.getElementById('ip_biz_name').value = row.ti_biz_name || '';
    document.getElementById('ip_ceo_name').value = '';
    document.getElementById('ip_biz_no').value   = row.ti_biz_no || '';
    document.getElementById('ip_email').value    = row.ti_email || '';
    document.getElementById('ip_supply').value   = supply;
    document.getElementById('ip_vat').value      = total - supply;
    onIpInvoicee();
  } else {
    document.querySelector('input[name="ip_cr_type"][value="income_deduction"]').checked = true;
    document.getElementById('ip_cr_identifier').value = row.patient_mobile || '';
    document.getElementById('ip_cr_amount').value     = Number(row.total_amount) || 0;
    onIpCrType();
  }

  pop.style.display = 'block';
  placeIssuePop(anchor);
}

/* 누른 칸 옆에 두되 화면 밖으로 나가지 않게 붙든다 */
function placeIssuePop(anchor) {
  const pop = document.getElementById('invIssuePop');
  const r   = anchor.getBoundingClientRect();
  const w   = pop.offsetWidth  || 320;
  const h   = pop.offsetHeight || 320;
  const gap = 6;

  let left = Math.min(r.left, window.innerWidth - w - 8);
  let top  = r.bottom + gap;
  if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - gap - h);

  pop.style.left = Math.max(8, left) + 'px';
  pop.style.top  = top + 'px';
}

function closeIssuePop() {
  document.getElementById('invIssuePop').style.display = 'none';
  _ipKind = null; _ipRow = null;
}

/* 개인은 사업자번호 대신 주민번호로 발행한다 — 비워 두면 처방전에 적힌 값을 쓴다 */
function onIpInvoicee() {
  const isPerson = document.querySelector('input[name="ip_invoicee"]:checked').value === '개인';
  document.getElementById('ip_no_label').textContent = isPerson ? '주민등록번호' : '사업자번호 *';
  document.getElementById('ip_biz_no').placeholder   = isPerson ? '비우면 처방전 값으로 발행합니다' : '000-00-00000';
  document.getElementById('ip_person_note').style.display = isPerson ? '' : 'none';
  if (isPerson && !document.getElementById('ip_biz_name').value.trim()) {
    document.getElementById('ip_biz_name').value = (_ipRow && _ipRow.patient_name) || '';
    document.getElementById('ip_ceo_name').value = (_ipRow && _ipRow.patient_name) || '';
  }
}

function onIpSupply() {
  const total  = Number(_ipRow && _ipRow.total_amount) || 0;
  const supply = parseInt(document.getElementById('ip_supply').value) || 0;
  document.getElementById('ip_vat').value = Math.max(0, total - supply);
}

function onIpCrType() {
  const isBiz = document.querySelector('input[name="ip_cr_type"]:checked').value === 'business_expense';
  document.getElementById('ip_cr_id_label').textContent = isBiz ? '사업자등록번호 *' : '휴대폰 번호 *';
  const el = document.getElementById('ip_cr_identifier');
  el.placeholder = isBiz ? '000-00-00000' : '010-0000-0000';
  el.value = isBiz ? '' : ((_ipRow && _ipRow.patient_mobile) || '');
}

async function submitIssuePop(btn) {
  if (!_ipRow) return;
  const id   = _ipRow.id;
  const kind = _ipKind;

  let url, payload;
  if (kind === 'tax') {
    const isPerson = document.querySelector('input[name="ip_invoicee"]:checked').value === '개인';
    const bizName  = document.getElementById('ip_biz_name').value.trim();
    const ceoName  = document.getElementById('ip_ceo_name').value.trim();
    const bizNo    = document.getElementById('ip_biz_no').value.trim();
    const supply   = parseInt(document.getElementById('ip_supply').value) || 0;
    const vat      = parseInt(document.getElementById('ip_vat').value) || 0;

    if (!bizName) { showToast('상호를 적어 주십시오.', 'warning'); return; }
    if (!ceoName) { showToast('대표자를 적어 주십시오.', 'warning'); return; }
    if (!isPerson && !bizNo) { showToast('사업자번호를 적어 주십시오.', 'warning'); return; }
    if (supply <= 0) { showToast('공급가액을 적어 주십시오.', 'warning'); return; }

    url = BASE_URL + '/orders/' + id + '/tax-invoice';
    payload = {
      tax_invoice_type:     'electronic',
      tax_invoice_invoicee: isPerson ? '개인' : '사업자',
      tax_invoice_biz_name: bizName,
      tax_invoice_ceo_name: ceoName,
      tax_invoice_biz_no:   bizNo || null,
      tax_invoice_email:    document.getElementById('ip_email').value.trim() || null,
      tax_invoice_supply:   supply,
      tax_invoice_vat:      vat,
    };
  } else {
    const identifier = document.getElementById('ip_cr_identifier').value.trim();
    const amount     = parseInt(document.getElementById('ip_cr_amount').value) || 0;
    if (!identifier) { showToast('식별번호를 적어 주십시오.', 'warning'); return; }
    if (amount <= 0) { showToast('금액을 적어 주십시오.', 'warning'); return; }

    url = BASE_URL + '/orders/' + id + '/cash-receipt';
    payload = {
      cash_receipt_type:       document.querySelector('input[name="ip_cr_type"]:checked').value,
      cash_receipt_identifier: identifier,
      cash_receipt_amount:     amount,
    };
  }

  BtnState.loading(btn, '발행 중...');
  try {
    const res = await apiRequest(url, 'POST', payload);
    if (!res.success) throw new Error(res.message || '발행하지 못했습니다.');
    showToast((kind === 'tax' ? '세금계산서' : '현금영수증') + ' 발행 완료', 'success', 4000);
    closeIssuePop();
    setTimeout(() => location.reload(), 800);
  } catch (e) {
    showToast('발행하지 못했습니다: ' + (e.message || ''), 'danger', 6000);
  } finally {
    BtnState.reset(btn);
  }
}

// 창 바깥을 누르면 닫는다 — 목록을 계속 보려는 뜻이다
document.addEventListener('mousedown', (e) => {
  const pop = document.getElementById('invIssuePop');
  if (!pop || pop.style.display === 'none') return;
  if (!pop.contains(e.target)) closeIssuePop();
});
window.addEventListener('resize', closeIssuePop);

// 모달 외부 클릭 닫기
['taxModal','cashModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#btn-list', title: '주문 목록 탭', body: '세금계산서 또는 현금영수증을 발행할 주문을 여기서 선택합니다.' },
  { selector: '#btn-detail', title: '계산서 발행 탭', body: '주문을 선택하면 이 탭에서 세금계산서·현금영수증을 발행하고 취소할 수 있습니다.' },
  { selector: '.ds-filter-card', title: '필터', body: '기간, 발행 상태, 이름으로 조회 범위를 좁힙니다.' },
];
</script>
@endpush

@push('scripts')
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('invoiceGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    toolbar: false,
    // 시안에 하단 상태바가 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    footer: false,
    columns: [
      { header: '주문번호',   name: 'order_number', width: 130, sortable: true },
      { header: '이름',     name: 'patient_name', width: 90,  sortable: true },
      { header: '총금액',     name: 'total_amount', width: 110, align: 'right', editor: 'number', sortable: true },
      { header: '주문상태',   name: 'status_label', width: 90,  align: 'center', sortable: true },
      { header: '주문일',     name: 'created_at',   width: 100, align: 'center', sortable: true },
      { header: '배송완료일', name: 'delivered_at', width: 100, align: 'center', sortable: true },
      /* 미발행이면 그 자리에서 발행한다 — 상세로 들어갔다 나오는 걸음을 없앤다.
         발행된 건에는 취소를 함께 둔다. */
      { header: '세금계산서', name: 'ti_display', width: 110, align: 'center', sortable: true,
        renderer: (v, row) => issueCell('tax', v, row) },
      { header: '현금영수증', name: 'cr_display', width: 110, align: 'center', sortable: true,
        renderer: (v, row) => issueCell('cash', v, row) },
    ],
    data: @json($gridData),
  });
  window.__invoiceGrid = grid;                    // 결과바의 '엑셀 저장' 이 이걸 부른다
  window.dsBindSelCount(grid, 'invSelCount');     // 결과바의 '선택 N건' 표시를 연결한다

  // 체크한 행 → 기존 상세보기 패널(selectOrder)로 이동
  window.invoiceViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 행을 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    selectOrder(c[0]);
  };

  // 행 더블클릭 → 상세보기 탭으로 전환(selectOrder가 패널 채우고 switchPanel('detail'))
  document.getElementById('invoiceGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) selectOrder(row);
  });
})();
</script>
@endpush
