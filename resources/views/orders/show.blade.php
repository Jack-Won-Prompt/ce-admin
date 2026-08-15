{{-- resources/views/orders/show.blade.php --}}
@extends($layout ?? 'layouts.app')

@section('title', '주문 상세 — ' . $order->order_number)
@section('page-title', '주문 상세')
@section('breadcrumb', '홈 / 주문관리 / ' . $order->order_number)

@push('styles')
<style>
  .order-grid { display: grid; grid-template-columns: 1fr 340px; gap: 16px; }
  @media(max-width:900px){ .order-grid { grid-template-columns:1fr; } }

  /* 시안 148:6407 — 제목 줄(1536×33)과 세그먼트 컨트롤이 한 줄.
     탭 바는 스크립트가 제목 줄 뒤에 끼워 넣으므로 흐름에서 빼 제목 줄 오른쪽 끝에 겹쳐 놓는다. */
  .od-detail { position: relative; }
  .od-title-row {
    display:flex; align-items:center; gap:12px;
    min-height:33px; margin:0 0 12px; padding-right:576px;
  }

  /* 상세 내부 탭(스크립트로 카드 그룹핑) — 시안 세그먼트 트랙 564×33 · r8 · pad 2 · bg gray-200 */
  .od-tabs {
    position:absolute; top:0; right:0; z-index:2;
    display:flex; flex-wrap:nowrap; gap:0;
    width:564px; max-width:100%; height:33px; padding:2px; margin:0;
    border:none; border-radius:var(--radius); background:var(--gray-200);
  }
  /* 칸 140×29 · r6 · 13px/500 lh21 — 활성만 흰 배경 */
  .od-tab {
    flex:1 1 0; min-width:0; height:29px; padding:0 8px; margin:0;
    display:inline-flex; align-items:center; justify-content:center; gap:4px;
    border:none; background:none; cursor:pointer; border-radius:6px; white-space:nowrap;
    font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700);
  }
  .od-tab:hover { color:var(--gray-1000); }
  .od-tab.active { background:var(--gray-0); color:var(--gray-1000); border-bottom-color:transparent; }
  @media(max-width:1200px){
    .od-title-row { padding-right:0; }
    .od-tabs { position:static; width:100%; margin:0 0 12px; }
  }

  /* 탭 사용 시 카드는 가로 1행 — 시안 3열(504×3) / 2열(762×2), gap 12 */
  .order-grid.od-flat { display:flex; flex-wrap:wrap; align-items:stretch; gap:12px; }
  .order-grid.od-flat > div { display:contents; }
  /* 한 탭에 카드가 1장뿐일 때(메모가 비면 메모/정보 탭이 그렇다) 카드가 1568 로 늘어나
     라벨과 값이 1500px 떨어진다. 시안의 2열 폭(762 ≒ 50%-6)을 최대치로 둔다.
     3열일 때의 자연폭 (100%-24)/3 은 이 값보다 작아 영향이 없다. */
  .order-grid.od-flat > div > .card {
    flex:1 1 0; min-width:280px; max-width:calc(50% - 6px); margin-bottom:0;
    display:flex; flex-direction:column;
  }
  .order-grid.od-flat > div > .card > .card-body { flex:1 1 auto; }

  /* 카드 사이 간격 — 시안 카드 gap 12 (죽은 유틸리티 mb-4 를 대신한다) */
  .od-mb { margin-bottom: 12px; }

  /* 시안 — 카드 pad 12/16/12/16 · 머리글 28 · 구분선 없음 · 머리글↔본문 12 */
  /* 머리글 줄 자체가 28 (pad 12 포함 40) — 보조 버튼이 있든 없든 높이가 같다 */
  .od-detail .card-header { padding:12px 16px 0; border-bottom:none; min-height:40px; gap:8px; }
  .od-detail .card-header-title { font-size:14px; font-weight:700; line-height:22px; letter-spacing:0; color:var(--gray-1000); }
  .od-detail .card-body { padding:12px 16px; }
  /* 머리글 오른쪽 보조 버튼 — 시안 h28 · 12px/500 lh19 */
  .od-detail .card-header .btn { height:28px; padding:0 10px; font-size:12px; font-weight:500; line-height:19px; }
  .od-detail .card-header .btn.w-full { width:auto; }
  /* 머리글 오른쪽 안내문 — 시안 12px/500 lh19 gray-600 */
  .od-head-note { margin-left:auto; display:inline-flex; align-items:center; gap:4px;
    font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); text-align:right; }

  /* 시안 — 라벨/값 한 쌍이 회색 상자 하나(r8 · pad 12 · gap 8 · bg gray-100 · h74).
     dt = 12 + 21, dd = 8 + 21 + 12 → 두 조각이 이어 붙어 74 가 된다. 값은 오른쪽 정렬. */
  .info-rows { margin: 0; }
  .info-rows dt {
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700);
    background: var(--gray-100); border-radius: var(--radius) var(--radius) 0 0;
    margin: 0; padding: 12px 12px 0;
  }
  .info-rows dd {
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000);
    background: var(--gray-100); border-radius: 0 0 var(--radius) var(--radius);
    margin: 0 0 8px; padding: 8px 12px 12px; text-align: right;
  }
  .info-rows dd:last-child { margin-bottom: 0; }

  /* 시안 회색 상자 — 라벨 위(왼쪽) · 값 아래(오른쪽) */
  .od-box {
    background: var(--gray-100); border-radius: var(--radius);
    padding: 12px; display: flex; flex-direction: column; gap: 8px;
  }
  .od-boxes { display: flex; flex-direction: column; gap: 8px; }
  .od-box-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .od-box-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  /* 제품 상자 — 1행 제품명, 2행 수량|보험가|본인부담 (사이 1px gray-300 세로선 h12) */
  .od-prod-row { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; line-height: 21px; }
  .od-prod-label { color: var(--gray-700); }
  .od-prod-value { color: var(--gray-1000); }
  .od-prod-metrics { gap: 0; flex-wrap: wrap; }
  .od-prod-metrics > span { display: inline-flex; align-items: center; gap: 8px; }
  .od-prod-metrics > span + span::before {
    content: ''; display: inline-block; width: 1px; height: 12px;
    background: var(--gray-300); margin: 0 6px;
  }
  .od-box-value { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000); text-align: right; }

  /* 시안 148:6407 환자 정보 — 파란 카드가 아니라 회색 상자 3개(성명 전폭 · 주민등록번호/전화번호 2열).
     전역 .patient-card / .patient-avatar / .patient-name / .patient-detail 값을 이 화면에서만 되돌린다. */
  .od-detail .patient-card {
    display: flex; flex-direction: column; align-items: stretch; gap: 8px;
    padding: 0; margin: 0; border: none; border-radius: 0; background: none;
  }
  .od-detail .patient-avatar { width: 20px; height: 20px; font-size: 12px; }
  .od-detail .patient-name {
    display: flex; align-items: center; justify-content: flex-end; gap: 6px;
    font-size: 13px; font-weight: 500; line-height: 21px; letter-spacing: 0; color: var(--gray-1000);
  }
  .od-detail .patient-detail { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000); margin-top: 0; }

  /* 전역 .section-title 이 uppercase · letter-spacing .6px · ::after 채움선을 걸어 둔다.
     여기서 값만 덮으면 그 셋이 살아남아 필드 라벨에 줄이 두 개 생긴다 — 함께 끈다. */
  .od-detail .section-title {
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700);
    text-transform: none; letter-spacing: 0;
    padding-bottom: 8px; border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
  }
  .od-detail .section-title::after { content: none; }
  /* 회색 상자 안에서는 라벨 줄이므로 구분선·여백을 없앤다 */
  .od-detail .od-box .section-title { padding: 0; border-bottom: none; margin: 0; }

  .od-detail .amount-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  /* 시안 148:6407 — 금액 박스 r8 · pad 12 · gap 8 · bg gray-100 · 라벨/값 13px/500 */
  .od-detail .amount-box {
    background: var(--gray-100); border: none;
    border-radius: var(--radius); padding: 12px; text-align: left;
  }
  .od-detail .amount-box .label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  .od-detail .amount-box .value { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000); margin-top: 8px; text-align: right; }
  /* 시안 — '총 결제금액' 도 배경은 같은 회색이고 글자만 primary */
  .od-detail .amount-box.highlight { background: var(--gray-100); border: none; }
  .od-detail .amount-box.highlight .label { color: var(--primary); }
  .od-detail .amount-box.highlight .value { color: var(--primary); }

  /* 시안 148:6407 — 단계 묶음은 가로 가운데로 뭉쳐 놓는다(주축 center).
     단계 사이 = 16 + 연결선 100 + 16 = 132. 카드 높이 12+8+53+8+12 = 93 (시안 93). */
  /* 시안 148:6407 — 스테퍼 카드 1568×93 = pad 12/16 + 단계 69. 여백을 더 두면 카드가 커진다 */
  .status-flow { display: flex; align-items: flex-start; justify-content: center; gap: 0; margin: 0; flex-wrap: wrap; }
  .status-step {
    flex: 0 0 auto; min-width: 48px; text-align: center;
    font-size: 13px; font-weight: 700; line-height: 21px;
    color: var(--gray-400); position: relative;
    --od-line: var(--gray-300);
  }
  .status-step:not(:last-child) { margin-right: 132px; }
  /* 연결선 100×4 — 4×4 원 + 88×1 선 + 4×4 원 */
  .status-step::after {
    content: '';
    position: absolute; left: calc(100% + 16px); top: 18px;   /* 그림 40px 의 세로 가운데 */
    width: 100px; height: 4px; z-index: 0;
    background-image:
      radial-gradient(circle closest-side, var(--od-line) 100%, transparent 100%),
      radial-gradient(circle closest-side, var(--od-line) 100%, transparent 100%),
      linear-gradient(var(--od-line), var(--od-line));
    background-repeat: no-repeat;
    background-size: 4px 4px, 4px 4px, 88px 1px;
    background-position: left center, right center, center center;
  }
  .status-step.done, .status-step.current { --od-line: var(--primary); }
  .status-step:last-child::after { display:none; }
  /* 시안 148:6407 Frame 48101629 — 단계 48×69 = 그림 40×40 + gap 8 + 라벨 21.
     그림 자체가 상태를 색으로 나르므로 원형 배경은 두지 않는다. */
  .status-step .dot {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 8px; position: relative; z-index: 1;
  }
  .status-step .dot img { width: 40px; height: 40px; display: block; }
  .status-step.done    { color: var(--primary); }
  .status-step.current { color: var(--primary); }
  /* 취소는 시안에 없는 상태다 — 그림이 없어 라벨 색으로만 알린다 */
  .status-step.cancelled { color: var(--danger); }

  /* 시안 158:577 — 청구 항목은 테두리 없는 회색 상자(r8 · pad 12 · gap 8 · h74), 값은 오른쪽 정렬 평문 */
  .nhis-box { border: none; padding: 0; display: flex; flex-direction: column; gap: 8px; }
  .nhis-row {
    display: flex; flex-direction: column; align-items: stretch; gap: 8px; margin: 0;
    background: var(--gray-100); border-radius: var(--radius); padding: 12px;
  }
  .nhis-row:last-child { margin-bottom: 0; }
  .nhis-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  .nhis-value { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000); text-align: right; }
  /* 시안에 초록이 없다 — 전역 .text-success(#12B76A) 를 이 화면에서만 되돌린다 */
  .od-detail .nhis-value.text-success { color: var(--gray-1000) !important; }
  /* 값 자리에 놓인 배지는 시안에서 평문 13px/500 오른쪽 정렬이다 */
  .nhis-row .badge, .od-box .badge {
    align-self: flex-end; background: none; padding: 0; border-radius: 0;
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000);
  }
  .nhis-row .badge-danger, .od-box .badge-danger { color: var(--alert-500); }

  /* 시안 158:171 — 미연동 안내는 상자 없이 카드 본문 가운데 */
  .ww-empty {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    height: 100%; min-height: 120px; text-align: center;
    font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600);
  }

  /* 시안 158:171 — 상태 변경 막대 2개, 각 h80 · r8 · pad 12 · 가운데 정렬 */
  .od-status-actions .btn {
    height: 80px; border: none; border-radius: var(--radius); padding: 12px;
    justify-content: center; gap: 8px;
    font-size: 13px; font-weight: 500; line-height: 21px;
  }
  .od-status-actions .btn-primary,
  .od-status-actions .btn-primary:hover,
  .od-status-actions .btn-outline,
  .od-status-actions .btn-outline:hover { background: var(--primary-100); color: var(--primary); }
  .od-status-actions .btn-danger,
  .od-status-actions .btn-danger:hover { background: var(--alert-100); color: var(--alert-500); }

  /* 하단 고정 바 제거 → 일반 흐름 바로 화면에 표시 */
  .action-footer {
    margin-top: 16px;
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 12px 20px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
  }

  /* 운송장 상자가 카드 폭의 절반(1920 에서 236)이라 좁은 폭에서 입력칸이 눌린다.
     1440 에서 57px · 1280 에서 30px 까지 줄어 값이 보이지 않았다.
     [저장] 이 옆에 못 들어가면 아래로 내려보내고 입력칸은 상자 폭을 다 쓴다.
     1920(폭 236) 에서는 137px 한 줄 그대로다. */
  .tracking-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .tracking-row .form-control { flex: 1 1 120px; min-width: 0; }
  /* 시안 158:171 — 상자 안 [저장] 은 흰 바탕 · 1px gray-200 · 글자 13px/500 gray-1000 */
  .od-box-tracking .btn-primary,
  .od-box-tracking .btn-primary:hover {
    background: var(--gray-0); border: 1px solid var(--gray-200); color: var(--gray-1000);
  }

  /* ── 세금계산서 / 현금영수증 ── */
  /* 시안 158:577 — 절마다 회색 상자 하나(라벨 위 · 상태 값 아래 오른쪽), 상자 사이 8 · 구분선 없음 */
  .od-receipt-group { display: flex; flex-direction: column; gap: 8px; }
  .od-receipt-group > .btn { align-self: flex-end; }
  /* 시안 158:577 발행 버튼 — 100×28 · r8 · 흰 바탕 · 1px gray-200 · 12px/500 lh19 gray-1000.
     (시안은 카드 머리글에 두지만 마크업이 조건 분기 안에 있어 자리는 본문에 남긴다) */
  .od-receipt-group .btn { height: 28px; padding: 0 10px; font-size: 12px; font-weight: 500; line-height: 19px; }
  .od-receipt-group .btn-primary,
  .od-receipt-group .btn-primary:hover {
    background: var(--gray-0); border: 1px solid var(--gray-200); color: var(--gray-1000);
  }
  .od-receipt { gap: 8px; }
  .od-receipt-title { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  .od-receipt-sep { border: none; margin: 4px 0; height: 0; }

  /* 시안 158:933 — 메모는 카드 높이를 채우는 회색 상자(r8 · pad 12 · 13px/500 lh21) */
  .od-memo {
    background: var(--gray-100); border-radius: var(--radius); padding: 12px; margin: 0;
    height: 100%; box-sizing: border-box; min-height: 132px;
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000);
    white-space: pre-wrap;
  }

  .receipt-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 1px solid var(--border-light);
    font-size: 13px;
  }
  .receipt-row:last-child { border-bottom: none; }
  .receipt-label { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-700); }
  .receipt-value { font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000); }
  .receipt-issued   { color: var(--primary); }
  .receipt-cancelled{ color: var(--danger); }
  .receipt-none     { color: var(--text-muted); }

  /* 모달 */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); z-index: 1000;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal-box {
    background: #fff; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); width: 460px; max-width: 95vw;
    max-height: 90vh; overflow-y: auto;
  }
  .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
  }
  .modal-title  { font-size: 14px; font-weight: 700; }
  .modal-body   { padding: 18px; }
  .modal-footer { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
  .btn-close-modal { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-muted); }

  .amount-hint { font-size: 12px; font-weight: 400; line-height: 19px; color: var(--gray-600); margin-top: 4px; }
  .tax-calc-row { background: var(--bg); border-radius: var(--radius); padding: 10px 12px; margin-top: 8px; font-size: 12px; }
  .tax-calc-row b { color: var(--primary); }

  .ww-badge { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }
  .ww-02 { background:var(--primary-light); color:var(--primary); }
  .ww-03,.ww-51 { background:var(--primary-light); color:var(--primary); }
  .ww-04,.ww-52 { background:var(--alert-100); color:var(--alert-500); }
  .ww-05 { background:var(--primary-light); color:var(--primary); }
  .ww-06,.ww-99 { background:var(--border-light); color:var(--text-muted); }
</style>
@endpush

@section('header-actions')
<a href="{{ route('orders.index') }}" class="btn btn-outline btn-sm">
  <i class="bx bx-arrow-back"></i> 목록으로
</a>
@endsection

@php
  $meta     = \App\Models\Order::STATUS_LABELS[$order->status] ?? ['label'=>$order->status,'badge'=>'secondary'];
  $steps    = ['pending','confirmed','shipping','delivered'];
  $curIdx   = array_search($order->status, $steps);
@endphp

@section('content')
{{-- od-detail — 이 조각이 다른 화면의 '상세내용' 탭으로 주입되므로 공용 클래스 덮어쓰기를 여기로 가둔다 --}}
<div class="page-body-inner od-detail">

  {{-- 주문 번호 헤더 --}}
  <div class="od-title-row">
    <h2 style="font-size:16px;font-weight:700;line-height:26px;color:var(--gray-1000);">{{ $order->order_number }}</h2>
    <span class="badge badge-{{ $meta['badge'] }}">{{ $meta['label'] }}</span>
    @if($order->tracking_number)
      <span style="font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);">
        <i class="bx bx-truck"></i> 운송장: <strong>{{ $order->tracking_number }}</strong>
      </span>
    @endif
  </div>

  {{-- 진행 상태 표시 --}}
  @if($order->status !== 'cancelled')
  <div class="card od-mb" style="padding:12px 16px;">
    <div class="status-flow">
      @foreach(['pending'=>'주문 대기','confirmed'=>'주문 확정','shipping'=>'배송중','delivered'=>'배송 완료'] as $s => $lbl)
        @php
          $isDone    = $curIdx !== false && array_search($s,$steps) < $curIdx;
          $isCurrent = $order->status === $s;
        @endphp
        <div class="status-step {{ $isDone ? 'done' : ($isCurrent ? 'current' : '') }}">
          {{-- 시안 148:6407 — 단계마다 40×40 그림이 붙는다. 지나온·현재 단계는 primary 톤,
               앞으로 올 단계는 gray 톤. 시안에서 SVG 를 그대로 받아 두 벌로 두었다. --}}
          <div class="dot">
            <img src="@assetv('images/order-steps/' . $s . ($isDone || $isCurrent ? '-on' : '-off') . '.svg')"
                 width="40" height="40" alt="">
          </div>
          {{ $lbl }}
        </div>
      @endforeach
    </div>
  </div>
  @endif

  <div class="order-grid">
    {{-- ── 왼쪽: 주문 상세 ── --}}
    <div>

      {{-- 환자 정보 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-user" style="color:var(--primary);"></i>
          <span class="card-header-title">환자 정보</span>
            @if($order->patient)
              <a href="{{ route('patients.show', $order->patient) }}" class="btn btn-outline btn-sm" style="margin-left:auto;">
                환자 상세
              </a>
            @endif
        </div>
        <div class="card-body">
          <div class="patient-card">
            <div class="od-box">
              <div class="od-box-label">성명</div>
              <div class="od-box-value patient-name">
                <span class="patient-avatar"><i class="bx bx-user"></i></span>
                {{ $order->patient?->name ?? '-' }}
              </div>
            </div>
            <div class="od-box-pair">
              <div class="od-box">
                <div class="od-box-label">주민등록번호</div>
                <div class="od-box-value patient-detail">{{ $order->patient?->masked_resident_no ?? '' }}</div>
              </div>
                @if($order->patient?->mobile)
              <div class="od-box">
                <div class="od-box-label">전화번호</div>
                <div class="od-box-value">{{ $order->patient->mobile }}</div>
              </div>
                @endif
            </div>
          </div>
        </div>
      </div>

      {{-- 제품 / 처방전 정보 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-box" style="color:var(--primary);"></i>
          <span class="card-header-title">제품 정보</span>
          @if($order->prescription)
            <a href="{{ route('prescriptions.show', $order->prescription) }}" class="btn btn-outline btn-sm"
               style="margin-left:auto;" data-rx="{{ $order->prescription->rx_number }}"
               onclick="return orderOpenRxTab(event, this)">
              <i class="bx bx-file-medical"></i> 처방전 보기
            </a>
          @endif
        </div>
        <div class="card-body">
          @if($order->prescription?->items && $order->prescription->items->isNotEmpty())
            {{-- 다중 제품 — 시안 148:6407: 제품 1건이 회색 상자 1개(1행 제품명 · 2행 수량|보험가|본인부담) --}}
            <div class="od-boxes">
                @foreach($order->prescription->items as $item)
              <div class="od-box od-prod">
                <div class="od-prod-row">
                  <span class="od-prod-label">제품명</span>
                  <span class="od-prod-value">{{ $item->product_name }}</span>
                </div>
                <div class="od-prod-row od-prod-metrics">
                  <span><span class="od-prod-label">수량</span><span class="od-prod-value">{{ $item->quantity }}</span></span>
                  <span><span class="od-prod-label">보험가</span><span class="od-prod-value">{{ number_format($item->insurance_price ?? 0) }}원</span></span>
                  <span><span class="od-prod-label">본인부담</span><span class="od-prod-value">{{ number_format($item->patient_copay ?? 0) }}원</span></span>
                </div>
              </div>
                @endforeach
            </div>
          @else
            {{-- 단일 제품 --}}
            <div class="od-boxes">
              <div class="od-box od-prod">
                <div class="od-prod-row">
                  <span class="od-prod-label">제품명</span>
                  <span class="od-prod-value">{{ $order->product_name ?? '-' }}</span>
                </div>
                <div class="od-prod-row od-prod-metrics">
                  <span><span class="od-prod-label">제품코드</span><span class="od-prod-value">{{ $order->product_code ?? '-' }}</span></span>
                  <span><span class="od-prod-label">수량</span><span class="od-prod-value">{{ $order->quantity ?? 1 }}개</span></span>
                  <span><span class="od-prod-label">보험가 (단가)</span><span class="od-prod-value">{{ number_format($order->unit_price) }}원</span></span>
                </div>
              </div>
            </div>
          @endif
        </div>
      </div>

      {{-- 배송 정보 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-truck" style="color:var(--primary);"></i>
          <span class="card-header-title">배송 정보</span>
        </div>
        <div class="card-body">
          {{-- 시안 158:171 — 배송지 주소 전폭 상자, 그 아래 [예상 배송일][운송장 번호] 2열 --}}
          <div class="od-boxes">
            <div class="od-box">
              <div class="od-box-label">배송지 주소</div>
              <div class="od-box-value">{{ $order->shipping_address ?? '-' }}</div>
            </div>
            <div class="od-box-pair">
              <div class="od-box">
                <div class="od-box-label">예상 배송일</div>
                <div class="od-box-value">{{ $order->estimated_delivery?->format('Y-m-d') ?? '-' }}</div>
              </div>

              {{-- 운송장 입력 --}}
              <div class="od-box od-box-tracking">
                <div class="section-title">운송장 번호</div>
                <div class="tracking-row">
                  <input type="text" id="trackingInput" class="form-control"
                         value="{{ $order->tracking_number }}"
                         placeholder="운송장 번호 입력">
                  <button class="btn btn-primary btn-sm" onclick="saveTracking()">
                    <i class="bx bx-save"></i> 저장
                  </button>
                </div>
              </div>
            </div>
            @if($order->delivered_at)
            <div class="od-box">
              <div class="od-box-label">실제 배송 완료</div>
              <div class="od-box-value">{{ $order->delivered_at->format('Y-m-d H:i') }}</div>
            </div>
            @endif
          </div>
        </div>
      </div>

      {{-- 세금계산서 / 현금영수증 --}}
      @php
        $taxColExists  = \Illuminate\Support\Facades\Schema::hasColumn('orders','tax_invoice_status');
        $tiStatus      = $taxColExists ? ($order->tax_invoice_status ?? 'not_issued') : null;
        $crStatus      = $taxColExists ? ($order->cash_receipt_status ?? 'not_issued') : null;
        $tiInfo        = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$tiStatus] ?? ['미발행','secondary'];
        $crInfo        = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$crStatus] ?? ['미발행','secondary'];
        $crTypeLabel   = \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$order->cash_receipt_type ?? ''] ?? '';
      @endphp
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-receipt" style="color:var(--primary);"></i>
          <span class="card-header-title">세금계산서 / 현금영수증</span>
        </div>
        <div class="card-body">
          @if(!$taxColExists)
            <div class="alert alert-warning" style="font-size:12px;margin-bottom:0;">
              DB 마이그레이션 필요 — <code>tax_receipt_SQL.txt</code>를 실행해주세요.
            </div>
          @else

          {{-- ── 세금계산서 ── 시안 158:577: 라벨 위 · 상태 값 아래 오른쪽인 회색 상자 --}}
          <div class="od-receipt-group">
            <div class="od-box od-receipt">
              <div class="od-receipt-title">
                <i class="bx bx-receipt"></i> 세금계산서
              </div>
              <span class="badge badge-{{ $tiInfo[1] }}">{{ $tiInfo[0] }}</span>
            </div>

            @if($tiStatus === 'issued')
              <div class="receipt-row">
                <span class="receipt-label">세금계산서 번호</span>
                <span class="receipt-value receipt-issued">{{ $order->tax_invoice_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">사업자명</span>
                <span class="receipt-value">{{ $order->tax_invoice_biz_name }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">사업자번호</span>
                <span class="receipt-value">{{ $order->tax_invoice_biz_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">공급가액</span>
                <span class="receipt-value">{{ number_format($order->tax_invoice_supply) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">부가세</span>
                <span class="receipt-value">{{ number_format($order->tax_invoice_vat) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">발행일시</span>
                <span class="receipt-value">{{ $order->tax_invoice_issued_at?->format('Y-m-d H:i') }}</span>
              </div>
              <div style="margin-top:10px;display:flex;gap:6px;">
                <button class="btn btn-outline btn-sm" onclick="printDoc('tax')">
                  <i class="bx bx-printer"></i> 출력
                </button>
                <button class="btn btn-danger btn-sm" onclick="cancelTaxInvoice()">
                  <i class="bx bx-block"></i> 취소
                </button>
              </div>
            @elseif($tiStatus === 'cancelled')
              <div class="receipt-row">
                <span class="receipt-label">취소 일시</span>
                <span class="receipt-value receipt-cancelled">{{ $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') }}</span>
              </div>
              <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="openTaxModal()">
                <i class="bx bx-revision"></i> 재발행
              </button>
            @else
              <div style="font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);padding:6px 0;">세금계산서가 발행되지 않았습니다.</div>
              <button class="btn btn-primary btn-sm" style="margin-top:4px;" onclick="openTaxModal()">
                <i class="bx bx-receipt"></i> 세금계산서 발행
              </button>
            @endif
          </div>

          <hr class="od-receipt-sep">

          {{-- ── 현금영수증 ── --}}
          <div class="od-receipt-group">
            <div class="od-box od-receipt">
              <div class="od-receipt-title">
                <i class="bx bx-money"></i> 현금영수증
              </div>
              <span class="badge badge-{{ $crInfo[1] }}">{{ $crInfo[0] }}</span>
            </div>

            @if($crStatus === 'issued')
              <div class="receipt-row">
                <span class="receipt-label">승인번호</span>
                <span class="receipt-value receipt-issued">{{ $order->cash_receipt_no }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">구분</span>
                <span class="receipt-value">{{ $crTypeLabel }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">식별번호</span>
                <span class="receipt-value">{{ $order->cash_receipt_identifier }}</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">금액</span>
                <span class="receipt-value">{{ number_format($order->cash_receipt_amount) }}원</span>
              </div>
              <div class="receipt-row">
                <span class="receipt-label">발행일시</span>
                <span class="receipt-value">{{ $order->cash_receipt_issued_at?->format('Y-m-d H:i') }}</span>
              </div>
              <div style="margin-top:10px;display:flex;gap:6px;">
                <button class="btn btn-outline btn-sm" onclick="printDoc('cash')">
                  <i class="bx bx-printer"></i> 출력
                </button>
                <button class="btn btn-danger btn-sm" onclick="cancelCashReceipt()">
                  <i class="bx bx-block"></i> 취소
                </button>
              </div>
            @elseif($crStatus === 'cancelled')
              <div class="receipt-row">
                <span class="receipt-label">취소 일시</span>
                <span class="receipt-value receipt-cancelled">{{ $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') }}</span>
              </div>
              <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="openCashModal()">
                <i class="bx bx-revision"></i> 재발행
              </button>
            @else
              <div style="font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);padding:6px 0;">현금영수증이 발행되지 않았습니다.</div>
              <button class="btn btn-primary btn-sm" style="margin-top:4px;" onclick="openCashModal()">
                <i class="bx bx-money"></i> 현금영수증 발행
              </button>
            @endif
          </div>

          @endif {{-- taxColExists --}}
        </div>
      </div>

      {{-- 메모 --}}
      @if($order->note)
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-note" style="color:var(--primary);"></i>
          <span class="card-header-title">메모</span>
        </div>
        <div class="card-body">
          <p class="od-memo">{{ $order->note }}</p>
        </div>
      </div>
      @endif

    </div>

    {{-- ── 오른쪽: 금액 / NHIS / 상태변경 ── --}}
    <div>

      {{-- 금액 요약 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-won" style="color:var(--primary);"></i>
          <span class="card-header-title">금액 정보</span>
        </div>
        <div class="card-body">
          <div class="amount-grid">
            <div class="amount-box">
              <div class="label">건보 청구액</div>
              <div class="value">{{ number_format($order->nhis_amount) }}원</div>
            </div>
            <div class="amount-box">
              <div class="label">환자 본인부담</div>
              <div class="value">{{ number_format($order->patient_copay) }}원</div>
            </div>
            <div class="amount-box">
              <div class="label">배송비</div>
              <div class="value">{{ number_format($order->shipping_fee) }}원</div>
            </div>
            <div class="amount-box highlight">
              <div class="label">총 결제금액</div>
              <div class="value">{{ number_format($order->total_amount) }}원</div>
            </div>
          </div>
        </div>
      </div>

      {{-- NHIS 청구 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-hospital" style="color:var(--primary);"></i>
          <span class="card-header-title">건강보험 요양비 청구</span>
          {{-- 청구는 여기서 보내지 않는다 — 공단 사이트에 직접 입력·업로드한다. --}}
          @if($order->nhis_claim_status === 'pending')
          <div class="od-head-note" style="margin-left:auto;">
            <i class="bx bx-info-circle"></i> 청구는 요양기관정보마당에 직접 입력·업로드합니다.
          </div>
          @endif
        </div>
        <div class="card-body">
          <div class="nhis-box">
            <div class="nhis-row">
              <span class="nhis-label">청구 상태</span>
              @php
                $nhisLabels = ['pending'=>['미청구','secondary'],'submitted'=>['청구 완료','primary'],'approved'=>['승인됨','success'],'rejected'=>['거부됨','danger']];
                [$nhisLbl,$nhisBadge] = $nhisLabels[$order->nhis_claim_status] ?? [$order->nhis_claim_status,'secondary'];
              @endphp
              <span class="badge badge-{{ $nhisBadge }}">{{ $nhisLbl }}</span>
            </div>
            @if($order->nhis_submitted_at)
            <div class="nhis-row">
              <span class="nhis-label">청구 일시</span>
              <span class="nhis-value">{{ $order->nhis_submitted_at->format('Y-m-d H:i') }}</span>
            </div>
            @endif
            @if($order->nhis_approved_at)
            <div class="nhis-row">
              <span class="nhis-label">승인 일시</span>
              <span class="nhis-value">{{ $order->nhis_approved_at->format('Y-m-d H:i') }}</span>
            </div>
            @endif
            @if($order->nhis_reimbursement)
            <div class="nhis-row">
              <span class="nhis-label">환급액</span>
              <span class="nhis-value text-success">{{ number_format($order->nhis_reimbursement) }}원</span>
            </div>
            @endif
          </div>
        </div>
      </div>

      {{-- Withworks 연동 상태 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-link-alt" style="color:var(--primary);"></i>
          <span class="card-header-title">Withworks 출고 현황</span>
        </div>
        <div class="card-body">
          @if($order->withworks_so_no)
            <div style="margin-bottom:10px;">
              <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">SO 번호</div>
              <div style="font-size:13px;font-weight:700;line-height:21px;color:var(--primary);">{{ $order->withworks_so_no }}</div>
            </div>
            @if($withworksStatus)
              @php
                $wwCls = match(true) {
                  ($withworksStatus['status'] ?? '') === '02'               => 'ww-02',
                  in_array($withworksStatus['status'] ?? '', ['03','51'])   => 'ww-03',
                  in_array($withworksStatus['status'] ?? '', ['04','52'])   => 'ww-04',
                  ($withworksStatus['status'] ?? '') === '05'               => 'ww-05',
                  default                                                   => 'ww-06',
                };
                $wwShip = $withworksStatus['ship'] ?? null;
                $shipBadge = '';
                if ($wwShip) {
                  $shipBadge = match(true) {
                    in_array($wwShip['ship_status'] ?? '', ['02','14','15','17']) => 'secondary',
                    in_array($wwShip['ship_status'] ?? '', ['52','55'])           => 'info',
                    in_array($wwShip['ship_status'] ?? '', ['61','68'])           => 'warning',
                    ($wwShip['ship_status'] ?? '') === '95'                       => 'success',
                    default                                                       => 'secondary',
                  };
                }
              @endphp
              <div style="margin-bottom:10px;">
                <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">SO 상태</div>
                <span class="ww-badge {{ $wwCls }}">{{ $withworksStatus['status_label'] ?? '-' }}</span>
              </div>
              @if(!empty($withworksStatus['so_date']))
              <div style="margin-bottom:8px;">
                <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">주문일</div>
                <div style="font-size:13px;font-weight:500;">{{ $withworksStatus['so_date'] }}</div>
              </div>
              @endif
              @if(!empty($withworksStatus['delivery_date']))
              <div style="margin-bottom:8px;">
                <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">희망 배송일</div>
                <div style="font-size:13px;font-weight:500;">{{ $withworksStatus['delivery_date'] }}</div>
              </div>
              @endif
              @if(!empty($withworksStatus['so_amount']))
              <div style="margin-bottom:10px;">
                <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">SO 금액</div>
                <div style="font-size:13px;font-weight:700;">{{ number_format($withworksStatus['so_amount']) }}원</div>
              </div>
              @endif
              @if($wwShip)
              <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:4px;">
                <div style="font-size:13px;font-weight:700;line-height:21px;color:var(--gray-700);margin-bottom:8px;">출고 현황</div>
                <div style="margin-bottom:8px;">
                  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">출고번호</div>
                  <div style="font-size:13px;font-weight:700;color:var(--primary);">{{ $wwShip['ship_no'] }}</div>
                </div>
                <div style="margin-bottom:8px;">
                  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">출고 상태</div>
                  <span class="badge badge-{{ $shipBadge }}">{{ $wwShip['ship_status_label'] }}</span>
                </div>
                @if(!empty($wwShip['schedule_date']))
                <div style="margin-bottom:8px;">
                  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">출고 예정일</div>
                  <div style="font-size:13px;font-weight:500;">{{ $wwShip['schedule_date'] }}</div>
                </div>
                @endif
                @if(!empty($wwShip['ship_complete_date']))
                <div style="margin-bottom:8px;">
                  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">출고 완료일</div>
                  <div style="font-size:13px;font-weight:500;">{{ $wwShip['ship_complete_date'] }}</div>
                </div>
                @endif
                @if(!empty($wwShip['tracking_no']))
                <div>
                  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-700);margin-bottom:4px;">운송장번호</div>
                  <div style="font-size:13px;font-weight:700;font-family:monospace;">{{ $wwShip['tracking_no'] }}</div>
                </div>
                @endif
              </div>
              @endif
            @else
              <div style="padding:12px;background:var(--gray-100);border-radius:var(--radius);font-size:12px;font-weight:500;line-height:19px;color:var(--gray-600);">
                <i class="bx bx-info-circle"></i> Withworks 상태를 불러올 수 없습니다.
              </div>
            @endif
          @else
            {{-- 시안 158:171 — 미연동 안내는 상자 없이 본문 가운데, 12px/500 gray-600 (주황은 시안에 없다) --}}
            <div class="ww-empty">
              <i class="bx bx-time"></i> Withworks 미연동 상태입니다.
            </div>
          @endif
        </div>
      </div>

      {{-- 주문 상태 변경 --}}
      <div class="card od-mb">
        <div class="card-header">
          <i class="bx bx-refresh" style="color:var(--primary);"></i>
          <span class="card-header-title">상태 변경</span>
        </div>
        <div class="card-body">
          {{-- 시안 158:171 — 상태 변경 막대 h80 · r8 · pad 12 · 사이 8 --}}
          <div class="od-status-actions" style="display:flex;flex-direction:column;gap:8px;">
            @if($order->status === 'pending')
              <button class="btn btn-primary w-full" onclick="changeStatus('confirmed')">
                <i class="bx bx-check-circle"></i> 주문 확정
              </button>
            @endif
            @if($order->status === 'confirmed')
              <button class="btn btn-outline w-full" onclick="changeStatus('shipping')">
                <i class="bx bx-truck"></i> 배송 시작
              </button>
            @endif
            @if($order->status === 'shipping')
              <button class="btn btn-primary w-full" onclick="changeStatus('delivered')">
                <i class="bx bx-package"></i> 배송 완료 처리
              </button>
            @endif
            @if(!in_array($order->status, ['delivered','cancelled']))
              <button class="btn btn-danger w-full" onclick="changeStatus('cancelled')">
                <i class="bx bx-block"></i> 주문 취소
              </button>
            @endif
            @if($order->status === 'delivered')
              <div style="text-align:center;color:var(--primary);font-size:13px;font-weight:700;line-height:21px;padding:8px;">
                <i class="bx bx-check-circle"></i> 배송 완료된 주문입니다.
              </div>
            @endif
            @if($order->status === 'cancelled')
              <div style="text-align:center;color:var(--alert-500);font-size:13px;font-weight:700;line-height:21px;padding:8px;">
                <i class="bx bx-block"></i> 취소된 주문입니다.
              </div>
            @endif
          </div>
        </div>
      </div>

      {{-- 주문 메타 --}}
      <div class="card">
        {{-- 시안 158:933 — 카드 제목 '정보' --}}
        <div class="card-header">
          <span class="card-header-title">정보</span>
        </div>
        <div class="card-body">
          <div class="od-boxes">
            <div class="od-box">
              <div class="od-box-label">생성자</div>
              <div class="od-box-value">{{ $order->creator?->name ?? '-' }}</div>
            </div>
            <div class="od-box-pair">
              <div class="od-box">
                <div class="od-box-label">생성일시</div>
                <div class="od-box-value">{{ $order->created_at->format('Y-m-d H:i') }}</div>
              </div>
              <div class="od-box">
                <div class="od-box-label">최종 수정</div>
                <div class="od-box-value">{{ $order->updated_at->format('Y-m-d H:i') }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- ── 하단 고정 액션 바 ── --}}
<div class="action-footer">
  <div style="font-size:13px;font-weight:500;line-height:21px;color:var(--gray-800);">
    <span class="badge badge-{{ $meta['badge'] }}">{{ $meta['label'] }}</span>
    &nbsp; {{ $order->order_number }}
    &nbsp;&middot;&nbsp; {{ $order->patient?->name ?? '-' }}
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('orders.index') }}" class="btn btn-outline btn-sm">
      <i class="bx bx-list-ul"></i> 목록
    </a>
    @if($order->prescription)
      <a href="{{ route('prescriptions.show', $order->prescription) }}" class="btn btn-outline btn-sm"
         data-rx="{{ $order->prescription->rx_number }}" onclick="return orderOpenRxTab(event, this)">
        <i class="bx bx-file"></i> 처방전
      </a>
    @endif
  </div>
</div>

{{-- ══════════ 세금계산서 발행 모달 ══════════ --}}
<div class="modal-overlay" id="taxModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="bx bx-receipt" style="color:var(--primary);"></i> 세금계산서 발행</div>
      <button class="btn-close-modal" onclick="closeTaxModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;">
        <div style="font-weight:700;margin-bottom:2px;">주문 {{ $order->order_number }}</div>
        <div style="color:var(--text-secondary);">결제금액: {{ number_format($order->total_amount) }}원</div>
      </div>

      <div class="form-group">
        <label class="form-label">발행 유형 <span>*</span></label>
        <select id="ti_type" class="form-control form-select">
          <option value="electronic">전자세금계산서</option>
          <option value="manual">일반세금계산서</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">사업자명 (공급받는자) <span>*</span></label>
        <input type="text" id="ti_biz_name" class="form-control" placeholder="○○ 주식회사">
      </div>
      <div class="form-group">
        <label class="form-label">사업자등록번호 <span>*</span></label>
        <input type="text" id="ti_biz_no" class="form-control" placeholder="000-00-00000"
               oninput="formatBizNo(this)">
      </div>
      <div class="form-group" id="ti_email_group">
        <label class="form-label">이메일 (전자발송)</label>
        <input type="email" id="ti_email" class="form-control" placeholder="tax@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">공급가액 <span>*</span></label>
        <input type="number" id="ti_supply" class="form-control" placeholder="0"
               oninput="calcVat()" value="{{ round($order->total_amount / 1.1) }}">
        <div class="amount-hint">총 결제금액의 부가세 역산 기본 적용 (수정 가능)</div>
      </div>
      <div class="form-group">
        <label class="form-label">부가세 (VAT 10%) <span>*</span></label>
        <input type="number" id="ti_vat" class="form-control" placeholder="0"
               value="{{ $order->total_amount - round($order->total_amount / 1.1) }}">
      </div>
      <div class="tax-calc-row" id="taxCalcSummary">
        공급가액 <b id="calcSupply">{{ number_format(round($order->total_amount / 1.1)) }}</b>원
        + 부가세 <b id="calcVat">{{ number_format($order->total_amount - round($order->total_amount / 1.1)) }}</b>원
        = 합계 <b id="calcTotal">{{ number_format($order->total_amount) }}</b>원
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeTaxModal()">취소</button>
      <button class="btn btn-primary" onclick="submitTaxInvoice()">
        <i class="bx bx-receipt"></i> 발행
      </button>
    </div>
  </div>
</div>

{{-- ══════════ 현금영수증 발행 모달 ══════════ --}}
<div class="modal-overlay" id="cashModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title"><i class="bx bx-money" style="color:var(--primary);"></i> 현금영수증 발행</div>
      <button class="btn-close-modal" onclick="closeCashModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:var(--primary-light);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;">
        <div style="font-weight:700;margin-bottom:2px;">주문 {{ $order->order_number }}</div>
        <div style="color:var(--text-secondary);">결제금액: {{ number_format($order->total_amount) }}원</div>
      </div>

      <div class="form-group">
        <label class="form-label">발행 구분 <span>*</span></label>
        <div style="display:flex;gap:12px;margin-top:4px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
            <input type="radio" name="cr_type" id="cr_type_income" value="income_deduction" checked
                   onchange="onCrTypeChange()">
            소득공제 (개인)
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
            <input type="radio" name="cr_type" id="cr_type_biz" value="business_expense"
                   onchange="onCrTypeChange()">
            지출증빙 (사업자)
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" id="cr_id_label">휴대폰 번호 <span>*</span></label>
        <input type="text" id="cr_identifier" class="form-control"
               placeholder="010-0000-0000" data-phone>
        <div class="amount-hint" id="cr_id_hint">소득공제: 환자 휴대폰 번호 입력</div>
      </div>
      <div class="form-group">
        <label class="form-label">발행 금액 <span>*</span></label>
        <input type="number" id="cr_amount" class="form-control"
               placeholder="0" value="{{ $order->total_amount }}">
        <div class="amount-hint">총 결제금액 기본 적용 (수정 가능)</div>
      </div>

      {{-- 환자 번호 자동 채우기 --}}
      @if($order->patient?->mobile)
      <div style="margin-top:-6px;margin-bottom:12px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="fillPatientMobile()">
          <i class="bx bx-user"></i>
          환자 번호 자동 입력 ({{ $order->patient->mobile }})
        </button>
      </div>
      @endif
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeCashModal()">취소</button>
      <button class="btn btn-primary" onclick="submitCashReceipt()">
        <i class="bx bx-money"></i> 발행
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
const ORDER_ID   = {{ $order->id }};
const ORDER_URL  = BASE_URL + '/orders/' + ORDER_ID;

/* 처방전 버튼 → 처방전 검수 화면을 '새 탭'으로 연다(주문 화면은 그대로 유지).
   워크스페이스 밖이면 브라우저 새 탭으로 폴백. href 는 남겨 두어 가운데 클릭 등
   브라우저 기본 동작도 살린다. 상세내용 탭에 주입될 때도 부모 프레임의 헬퍼를 쓴다. */
window.orderOpenRxTab = function (ev, el) {
  ev.preventDefault();
  const url = el.getAttribute('href');
  if (!url) return false;
  const rx = el.dataset.rx ? '처방전 관리 - ' + el.dataset.rx : '처방전 관리';
  if (typeof window.ceOpenTab === 'function') {
    window.ceOpenTab(url, rx, 'file-edit-02');
  } else {
    window.open(url, '_blank', 'noopener');
  }
  return false;
};

// ── 상태 변경 ─────────────────────────────────────────────
async function changeStatus(status) {
  const labels = {
    confirmed: '주문을 확정하시겠습니까?',
    shipping:  '배송 시작 처리를 하시겠습니까?',
    delivered: '배송 완료 처리를 하시겠습니까?',
    cancelled: '정말 주문을 취소하시겠습니까?',
  };
  if (!await ceConfirm(labels[status] || '상태를 변경하시겠습니까?',
                       { tone: status === 'cancelled' ? 'danger' : 'default' })) return;

  const res = await apiRequest(ORDER_URL + '/status', 'POST', { status });
  if (res.success) {
    showToast('상태가 변경되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}


// ── 운송장 저장 ───────────────────────────────────────────
async function saveTracking() {
  const val = document.getElementById('trackingInput').value.trim();
  if (!val) { showToast('운송장 번호를 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/tracking', 'POST', { tracking_number: val });
  if (res.success) {
    showToast('운송장 번호가 저장되었습니다.', 'success');
  }
}

// ══════════ 세금계산서 ══════════════════════════════════════

function openTaxModal()  { document.getElementById('taxModal').classList.add('open'); }
function closeTaxModal() { document.getElementById('taxModal').classList.remove('open'); }

// 사업자번호 자동 포맷 000-00-00000
function formatBizNo(el) {
  let v = el.value.replace(/[^0-9]/g, '');
  if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
  else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
  el.value = v;
}

// 공급가액 변경 시 부가세 자동 계산
function calcVat() {
  const supply = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat    = Math.round(supply * 0.1);
  document.getElementById('ti_vat').value = vat;
  updateTaxCalcSummary();
}

function updateTaxCalcSummary() {
  const supply = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat    = parseInt(document.getElementById('ti_vat').value)    || 0;
  document.getElementById('calcSupply').textContent = supply.toLocaleString('ko-KR');
  document.getElementById('calcVat').textContent    = vat.toLocaleString('ko-KR');
  document.getElementById('calcTotal').textContent  = (supply + vat).toLocaleString('ko-KR');
}
document.getElementById('ti_vat')?.addEventListener('input', updateTaxCalcSummary);

// 발행 유형 변경 시 이메일 필드 표시
document.getElementById('ti_type')?.addEventListener('change', function() {
  const emailGroup = document.getElementById('ti_email_group');
  emailGroup.style.display = this.value === 'electronic' ? 'block' : 'none';
});

async function submitTaxInvoice() {
  const type    = document.getElementById('ti_type').value;
  const bizName = document.getElementById('ti_biz_name').value.trim();
  const bizNo   = document.getElementById('ti_biz_no').value.trim();
  const email   = document.getElementById('ti_email').value.trim();
  const supply  = parseInt(document.getElementById('ti_supply').value) || 0;
  const vat     = parseInt(document.getElementById('ti_vat').value)    || 0;

  if (!bizName) { showToast('사업자명을 입력해주세요.', 'warning'); return; }
  if (!bizNo)   { showToast('사업자등록번호를 입력해주세요.', 'warning'); return; }
  if (supply <= 0) { showToast('공급가액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/tax-invoice', 'POST', {
    tax_invoice_type:     type,
    tax_invoice_biz_name: bizName,
    tax_invoice_biz_no:   bizNo,
    tax_invoice_email:    email || null,
    tax_invoice_supply:   supply,
    tax_invoice_vat:      vat,
  });

  if (res.success) {
    showToast(`세금계산서 발행 완료 (${res.tax_invoice_no})`, 'success', 5000);
    closeTaxModal();
    setTimeout(() => location.reload(), 1000);
  }
}

async function cancelTaxInvoice() {
  if (!await ceConfirm('세금계산서를 취소하시겠습니까?', { tone: 'danger', confirmText: '취소 처리' })) return;
  const res = await apiRequest(ORDER_URL + '/tax-invoice', 'DELETE');
  if (res.success) {
    showToast('세금계산서가 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ══════════ 현금영수증 ══════════════════════════════════════

function openCashModal()  { document.getElementById('cashModal').classList.add('open'); }
function closeCashModal() { document.getElementById('cashModal').classList.remove('open'); }

function onCrTypeChange() {
  const isBiz = document.querySelector('input[name="cr_type"]:checked').value === 'business_expense';
  const label = document.getElementById('cr_id_label');
  const hint  = document.getElementById('cr_id_hint');
  const input = document.getElementById('cr_identifier');
  if (isBiz) {
    label.innerHTML = '사업자등록번호 <span>*</span>';
    hint.textContent = '지출증빙: 사업자등록번호 입력';
    input.placeholder = '000-00-00000';
    input.removeAttribute('data-phone');
  } else {
    label.innerHTML = '휴대폰 번호 <span>*</span>';
    hint.textContent = '소득공제: 환자 휴대폰 번호 입력';
    input.placeholder = '010-0000-0000';
    input.setAttribute('data-phone', '');
  }
  input.value = '';
}

function fillPatientMobile() {
  document.getElementById('cr_identifier').value = '{{ $order->patient?->mobile ?? "" }}';
}

async function submitCashReceipt() {
  const type       = document.querySelector('input[name="cr_type"]:checked').value;
  const identifier = document.getElementById('cr_identifier').value.trim();
  const amount     = parseFloat(document.getElementById('cr_amount').value) || 0;

  if (!identifier) { showToast('식별번호(휴대폰/사업자)를 입력해주세요.', 'warning'); return; }
  if (amount <= 0)  { showToast('금액을 입력해주세요.', 'warning'); return; }

  const res = await apiRequest(ORDER_URL + '/cash-receipt', 'POST', {
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

async function cancelCashReceipt() {
  if (!await ceConfirm('현금영수증을 취소하시겠습니까?', { tone: 'danger', confirmText: '취소 처리' })) return;
  const res = await apiRequest(ORDER_URL + '/cash-receipt', 'DELETE');
  if (res.success) {
    showToast('현금영수증이 취소되었습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

// ── 출력 ─────────────────────────────────────────────────
function printDoc(type) {
  const title = type === 'tax' ? '세금계산서' : '현금영수증';
  const no    = type === 'tax'
    ? '{{ $order->tax_invoice_no ?? "" }}'
    : '{{ $order->cash_receipt_no ?? "" }}';
  window.open(ORDER_URL + (type === 'tax' ? '/tax-invoice/print' : '/cash-receipt/print'), '_blank');
}

// 모달 외부 클릭 닫기
['taxModal','cashModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.status-flow', title: '주문 진행 상태', body: '주문 대기 → 주문 확정 → 배송 중 → 배송 완료 단계를 시각적으로 보여줍니다.' },
  { selector: '.card:nth-of-type(1)', title: '환자 정보', body: '이 주문과 연결된 환자의 기본 정보를 확인합니다.' },
  { selector: '.card:nth-of-type(2)', title: '제품 정보', body: '주문된 제품 목록, 수량, 가격, 보험 적용 금액을 확인합니다.' },
  { selector: '.card:nth-of-type(3)', title: '배송 정보', body: '운송장 번호를 입력하고 배송 상태를 관리합니다. 운송장 번호 입력 후 저장하면 배송 추적이 가능합니다.' },
  { selector: '.card:nth-of-type(4)', title: '세금계산서 / 현금영수증', body: '세금계산서 발행, 현금영수증 발행 및 취소를 여기서 처리합니다.' },
];
</script>
<script>
/* 주문 상세 내부 탭: 카드를 제목 기준으로 그룹핑해 탭으로 표시(주입/직접 렌더 모두 동작) */
(function initOrderDetailTabs() {
  const root = document.querySelector('.page-body-inner');
  if (!root || root.dataset.tabbed) return;
  const grid = root.querySelector('.order-grid');
  if (!grid) return;
  root.dataset.tabbed = '1';

  const TABS = [
    { key: '기본', label: '기본 정보',  icon: 'bx-info-circle' },
    { key: '배송', label: '배송/상태',  icon: 'bx-truck' },
    { key: '청구', label: '청구/발행',  icon: 'bx-receipt' },
    { key: '정보', label: '메모/정보',  icon: 'bx-note' },
  ];
  const MAP = {
    '환자 정보': '기본', '제품 정보': '기본', '금액 정보': '기본',
    '배송 정보': '배송', 'Withworks 출고 현황': '배송', '상태 변경': '배송',
    'NHIS 건강보험 청구': '청구', '세금계산서 / 현금영수증': '청구',
    '메모': '정보',
  };

  const cards = [];
  const statusCard = root.querySelector('.status-flow') ? root.querySelector('.status-flow').closest('.card') : null;
  if (statusCard) cards.push(statusCard);
  grid.querySelectorAll('.card').forEach(c => cards.push(c));

  grid.classList.add('od-flat');

  cards.forEach(c => {
    const t = c.querySelector('.card-header-title');
    c.dataset.otab = (c === statusCard) ? '기본' : (t ? (MAP[t.textContent.trim()] || '정보') : '정보');
  });

  const used = TABS.filter(t => cards.some(c => c.dataset.otab === t.key));
  if (used.length < 2) return;   // 탭이 1개뿐이면 그대로

  const bar = document.createElement('div');
  bar.className = 'od-tabs';
  used.forEach((t, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'od-tab' + (i === 0 ? ' active' : '');
    b.dataset.tab = t.key;
    b.innerHTML = '<i class="bx ' + t.icon + '"></i> ' + t.label;
    b.addEventListener('click', () => showTab(t.key));
    bar.appendChild(b);
  });
  const anchor = statusCard || grid;
  anchor.parentNode.insertBefore(bar, anchor);

  function showTab(key) {
    cards.forEach(c => { c.style.display = (c.dataset.otab === key) ? '' : 'none'; });
    bar.querySelectorAll('.od-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
  }
  showTab(used[0].key);
})();
</script>
@endpush
