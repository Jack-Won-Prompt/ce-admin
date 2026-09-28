@extends('layouts.app')

@section('title', '교환/반품/취소')
@section('page-title', '교환/반품/취소')
@section('breadcrumb', '홈 - 주문 - 교환/반품/취소')

@section('help-title', '교환/반품/취소 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>배송된 제품의 교환ㆍ반품과 출고 전 주문 취소를 처리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">사유별 단계 (Unicorn 교환·반품 절차)</div>
  @foreach(\App\Models\OrderReturn::FLOWS as $sc => $flow)
    <div class="help-item"><div class="help-item-text">
      <strong>{{ \App\Models\OrderReturn::SCENARIO_LABELS[$sc] }}</strong>
      {{ collect($flow)->map(fn ($st) => \App\Models\OrderReturn::STATUS_LABELS[$st])->implode(' → ') }}
    </div></div>
  @endforeach
</div>
<div class="help-section">
  <div class="help-section-title">기한</div>
  <div class="help-tip"><i class="bx bx-time"></i>창고 입고일로부터 검수 {{ config('returns.inspect_days') }}영업일 ·
    출고(반품은 발행) {{ config('returns.ship_days') }}영업일입니다. 넘긴 건은 목록에 붉게 뜹니다.
    휴무일은 설정 › 서비스 설정 › 교환·반품에서 변경합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">전자 승인</div>
  <div class="help-tip"><i class="bx bx-lock-alt"></i>검수 확정과 전자 승인은 승인 권한이 있어야 누릅니다.
    설정 › 권한 그룹의 「교환·반품 전자 승인」을 주면 됩니다.</div>
</div>
@endsection

@section('content')

{{-- 결과를 말하는 자리 (2026-09-28 시험에서 드러남).

     목록에서 바로 서명할 수 있게 한 날까지 이 화면에는 안내말을 세우는 자리가
     없었다. 서명하면 225,000원이 환불되는데 화면은 아무 말도 하지 않아, 눌린
     것인지조차 알 수 없었다. 상세 화면과 같은 모양으로 둔다. --}}
@if(session('status'))
  <div style="background:var(--primary-light);border:1px solid var(--primary-200);color:var(--primary);
              border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:12px;font-weight:700;">
    {{ session('status') }}
  </div>
@endif
@if($errors->any())
  <div style="background:var(--danger-light);border:1px solid var(--alert-100);color:var(--danger);
              border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:12px;font-weight:700;">
    @foreach($errors->all() as $말)<p style="margin:0;">{{ $말 }}</p>@endforeach
  </div>
@endif

@php $curType = request('type'); @endphp
{{-- 종류는 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}

<form method="GET" action="{{ route('order-returns.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 종류가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">종류</label>
      <select name="type" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $counts->sum() }})</option>
        @foreach(\App\Models\OrderReturn::TYPES as $key => $label)
          <option value="{{ $key }}" {{ $curType === $key ? 'selected' : '' }}>
            {{ $label }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="접수번호ㆍ주문번호ㆍ이름">
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select">
        <option value="">전체 상태</option>
        @foreach(\App\Models\OrderReturn::STATUS_LABELS as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    {{-- 승인할 사람은 「내가 누를 것」을 한 번에 보길 원한다. 상태 거르개만 있을 때는
         「검수 확정」과 「전자 승인」을 따로 곱라 보아야 했고, 그래도 자격 변경처럼
         접수 직후가 승인인 건은 어느 묶음에도 들지 않았다. --}}
    <label class="ds-btn" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;
           {{ request()->boolean('pending') ? 'background:#FEF3C7;border-color:#B54708;color:#B54708;font-weight:600;' : '' }}">
      <input type="checkbox" name="pending" value="1" @checked(request()->boolean('pending'))
             onchange="this.form.submit()" style="accent-color:#B54708;margin:0;">
      승인 대기@if($pendingCount) <b>{{ $pendingCount }}</b>@endif
    </label>
    {{-- 창고가 검수 승인을 청했는데 아직 아무도 보지 않은 건 (2026-09-28 지시).

         여태 그 건들은 「검수중」 한 상태로 섞여 있었다 — 창고가 올린 것인지 담당자가
         손으로 옮긴 것인지 가릴 수 없어, 지금 봐야 할 건이 묻혔다. --}}
    <label class="ds-btn" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;
           {{ request()->boolean('wh_inspect') ? 'background:#E0F2FE;border-color:#0369A1;color:#0369A1;font-weight:600;' : '' }}">
      <input type="checkbox" name="wh_inspect" value="1" @checked(request()->boolean('wh_inspect'))
             onchange="this.form.submit()" style="accent-color:#0369A1;margin:0;">
      창고 검수 요청@if($whInspectCount) <b>{{ $whInspectCount }}</b>@endif
    </label>
    {{-- 결재가 멈춰 있는 자리 셋. 건수가 0 이면 세우지 않는다 —
         늘 서 있으면 「지금 볼 것이 있다」는 뜻이 옅어진다. --}}
    @foreach([['sign_sent','서명 대기',$signWaitCount,'#B54708'],
              ['refund_failed','환불 실패',$refundFailCount,'#B42318'],
              ['topup_sent','차액 미납',$topupWaitCount,'#B54708']] as [$값,$글,$셈,$빛])
      @if($셈)
        <a href="{{ request()->fullUrlWithQuery(['stage' => request('stage') === $값 ? null : $값]) }}"
           class="ds-btn" style="{{ request('stage') === $값 ? "background:{$빛}18;border-color:{$빛};color:{$빛};font-weight:600;" : '' }}">
          {{ $글 }} <b>{{ $셈 }}</b>
        </a>
      @endif
    @endforeach
    {{-- 늘 세워 둔다 — 거르고 있을 때만 나타나면 단추가 들락날락해
         옆에 붙은 「검색」이 자리를 옮긴다. 누를 것은 항상 같은 자리에 있어야 한다. --}}
    <a href="{{ route('order-returns.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 승인은 하루에 여러 건을 본다. 한 건씩 열어 진행 단계 탭까지 들어가게
         두면 스무 건이면 스무 번을 오간다. 목록에서 골라 한 번에 누른다. --}}
    @perm('order-returns', 'approve')
      <button type="button" class="ds-btn" style="border-color:#B54708;color:#B54708;font-weight:600;"
              onclick="rtnAskApprove()">반품 승인</button>
    @endperm
    {{-- 접수는 찾는 일과 나란히 둔다. 네비바에 두었더니 탭 안에서 통째로 사라졌고,
         찾다가 없으면 바로 접수하는 흐름과도 맞지 않았다. --}}
    <button type="button" class="ds-btn ds-btn-primary" onclick="rtnPanel('new')">
      <i class="bx bx-plus"></i> 신규 접수
    </button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__rtnGrid?.downloadExcel()">엑셀 다운</button>
  </div>
</form>

<div class="ds-grid-section">
  {{-- 서류 관리와 같은 얼개다 — 흰 카드 한 장 안에 탭줄과 판이 들어간다.
       탭줄을 카드 밖에 두었더니 그 줄만 회색 바탕 위에 떠 있었다. --}}
  <div class="ds-grid-card">
  {{-- 목록과 접수를 한 화면에 나란히 둔다. 접수하려고 다른 화면으로 건너가면
       방금 무엇을 보고 있었는지가 끊긴다. --}}
  <div class="pnl-tabs">
    <button type="button" id="rtnTabList" class="pnl-tab active" onclick="rtnPanel('list')"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b>{{ $total }}</b>건@if($pendingCount) · <b style="color:#B54708;">승인 대기 {{ $pendingCount }}</b>@endif @if($lateCount) · <b style="color:#B54708;">기한 초과 {{ $lateCount }}</b>@endif)</span></button>
    {{-- 고른 건은 목록 바로 옆에서 본다. 다른 화면으로 건너가면 어떤 조건으로 찾고
         있었는지가 끊기고, 돌아오려면 다시 찾아야 한다. --}}
    <button type="button" id="rtnTabShow" class="pnl-tab" onclick="rtnPanel('show')">상세내용</button>
    <button type="button" id="rtnTabNew"  class="pnl-tab" onclick="rtnPanel('new')">신규 접수</button>
  </div>

  <div id="rtnPaneList">
    <div id="rtnGrid"></div>
  </div>

  <div id="rtnPaneShow" style="display:none;">
    {{-- 상세는 이미 한 화면으로 있다. 그 화면을 그대로 들여온다 — 두 벌로 만들면
         한쪽만 고쳐져 서로 다른 것을 보여 주게 된다.
         액자 안에서는 사이드바·네비가 스스로 숨는다(is-framed). --}}
    <div id="rtnShowEmpty" style="padding:28px 16px;text-align:center;font-size:12px;color:var(--gray-700);">
      목록에서 행을 더블클릭하면 여기에 나옵니다.
    </div>
    <iframe id="rtnShowFrame" title="상세내용" style="display:none;width:100%;border:0;
            height:calc(100vh - 300px);min-height:520px;"></iframe>
  </div>

  <div id="rtnPaneNew" style="display:none;">
    @include('order-returns._form')
  </div>
  </div>{{-- /.ds-grid-card --}}
</div>

{{-- 승인하기 전에 무엇을 승인하는지 보여 준다. 목록의 한 줄만으로는 금액이
     어떻게 움직이는지 알 수 없어, 승인하고 나서야 상세를 열어 확인하게 된다. --}}
<div id="rtnApWrap" style="display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,23,42,.34);"
     onclick="if(event.target===this) rtnApClose()">
  <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(560px,92vw);
              max-height:82vh;display:flex;flex-direction:column;background:var(--bg-card,#fff);
              border-radius:12px;box-shadow:0 18px 48px rgba(15,23,42,.24);overflow:hidden;">
    <div style="padding:13px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;">
      <span style="font-size:14px;font-weight:700;">반품 승인</span>
      <span id="rtnApCount" style="font-size:12px;color:var(--text-muted);"></span>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn ds-btn-sm" onclick="rtnApClose()">✕</button>
    </div>
    <div id="rtnApBody" style="padding:6px 16px 14px;overflow-y:auto;font-size:13px;"></div>
    <div style="padding:11px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="ds-btn" onclick="rtnApClose()">닫기</button>
      <button type="button" id="rtnApGo" class="ds-btn ds-btn-primary" onclick="rtnApSubmit()">승인하기</button>
    </div>
  </div>
</div>

{{-- 최종승인자 서명 팝오버 — 목록에서 바로 연다 (2026-09-28 지시).

     상세의 결재 판에 있는 것과 같은 자리다. 무엇에 서명하는지(검수 결과ㆍ차감 금액ㆍ
     환불/차액 두 항목)를 함께 세우고 그 아래에서 서명받는다 — 금액만 보고 누르게
     두지 않는다. --}}
<div id="rtSignWrap" style="display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,23,42,.34);"
     onclick="if(event.target===this) rtSignClose()">
  <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(560px,94vw);
              max-height:88vh;display:flex;flex-direction:column;background:var(--bg-card,#fff);
              border-radius:12px;box-shadow:0 18px 48px rgba(15,23,42,.24);overflow:hidden;">
    <div style="padding:13px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;">
      <span style="font-size:14px;font-weight:700;">최종승인자 서명</span>
      <span id="rtSignNo" style="font-size:12px;color:var(--text-muted);"></span>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn ds-btn-sm" onclick="rtSignClose()">✕</button>
    </div>
    <div id="rtSignBody" style="padding:8px 16px 14px;overflow-y:auto;font-size:13px;"></div>
    <div style="padding:11px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
      <span id="rtSignWhy" style="flex:1;font-size:12px;color:var(--danger);font-weight:700;align-self:center;"></span>
      <button type="button" class="ds-btn" onclick="rtSignClose()">닫기</button>
      <button type="button" id="rtSignGo" class="ds-btn ds-btn-primary" style="display:none;"
              onclick="rtSignSubmit()">서명하고 승인</button>
    </div>
  </div>
</div>

<form id="rtSignForm" method="POST" action="" style="display:none;">
  @csrf
  <input type="hidden" name="signature" id="rtSignData">
</form>

<form id="rtnApForm" method="POST" action="{{ route('order-returns.bulkApprove') }}" style="display:none;">
  @csrf
  <div id="rtnApIds"></div>
</form>

@endsection

@push('scripts')
<script>
(function () {
  const SHOW_BASE = @json(url('order-returns'));
  /* 「결재」 단추는 그리드가 그릴 때 곧바로 불린다 — **그리드보다 먼저** 서 있어야
     한다. 값을 담는 함수는 호이스팅되지 않아, 아래에 두면 첫 그림에서 죽는다
     (2026-09-28 시험에서 ReferenceError 로 드러남). */
  window.rtSignBtn = function (v, row) {
    const box = document.createElement('div');
    box.style.cssText = 'display:flex;align-items:center;justify-content:center;';

    if (!v) { box.textContent = ''; return box; }

    /* 지금 누가 무엇을 할 차례인가를 말한다 — 검수 · 서명 · 서명 확인 */
    const 꼴 = {
      inspect: { 글: '검수',      귀띔: '책임자 검수를 합니다 — 결재 판으로 갑니다',
                 색: 'border:1px solid #0369A1;background:#E0F2FE;color:#0369A1;' },
      sign:    { 글: '서명',      귀띔: '이 자리에서 최종승인자 서명을 받습니다',
                 색: 'border:1px solid var(--primary);background:var(--primary-light);color:var(--primary);' },
      view:    { 글: '서명 확인', 귀띔: '받은 서명을 확인합니다',
                 색: 'border:1px solid var(--border);background:#fff;color:var(--text-muted);' },
    }[v];

    if (!꼴) { box.textContent = ''; return box; }

    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = 꼴.글;
    b.title = 꼴.귀띔;
    b.style.cssText = 'height:22px;padding:0 9px;font-size:11px;font-weight:700;cursor:pointer;'
      + 'border-radius:999px;line-height:1;' + 꼴.색;
    b.onclick = (ev) => {
      ev.stopPropagation();
      /* 검수는 적을 것이 많아 팝오버에 담지 않는다 — 결재 판으로 곧바로 보낸다 */
      if (v === 'inspect') { location.href = '/order-returns/' + row.id + '?tab=appr'; return; }
      rtSignOpen(row.id);
    };
    box.appendChild(b);
    return box;
  };


  const grid = new wwGrid({
    el: document.getElementById('rtnGrid'),
    height: 'fit', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '접수번호', name: 'receipt',  width: 140, sortable: true },
      { header: '주문번호', name: 'order_no', width: 120, sortable: true },
      { header: '이름',   name: 'patient',  width: 90 },
      { header: '유형',     name: 'type',     width: 60,  align: 'center', sortable: true },
      // 같은 「교환」이라도 변심과 불량은 승인자도 청구 방식도 다르다 — 사유를 세운다
      /* 절차서가 정한 처리 유형이다 — 고객 변심 교환ㆍ불량 교환ㆍ반품 및 환불ㆍ
         불량 반품ㆍ출고 전 취소ㆍ자격 변경. 옆의 「유형」(교환ㆍ반품ㆍ취소)을 사유로
         한 번 더 나눈 것이라, 단계ㆍ승인자ㆍ청구 방식이 여기서 갈린다.

         고객이 말한 신청 사유는 「신청 사유」 칸에 따로 있다 — 둘 다 「사유」라
         부르면 가릴 수 없었다(2026-09-28 검증). */
      { header: '처리 유형', name: 'scenario', width: 120, align: 'center', sortable: true },
      { header: '상태',     name: 'status',   width: 90,  align: 'center', sortable: true },
      // 창고가 어디까지 했는가 — 우리 단계와 다른 것을 잰다(요청서 4쪽)
      { header: '3PL 상태', name: 'pl3',      width: 100, align: 'center', sortable: true },
      /* 창고가 검수 승인을 청했는가 (2026-09-28 지시) — 「요청」은 아직 아무도 보지
         않은 것이라 눈에 띄어야 한다. 「확인」은 담당자가 열어 본 것이다.
         결재가 어디까지 왔는지는 옆의 「결재 단계」 칸이 말한다. */
      {
        header: '창고 검수 요청', name: 'wh_inspect', width: 110, align: 'center', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '';
          if (v === '요청') { s.style.cssText = 'font-weight:700;color:#0369A1;'; }
          if (v === '확인') { s.style.color = 'var(--text-muted)'; }
          return s;
        },
      },
      /* 결재가 어디까지 왔는가 — 옆의 「상태」(절차 단계)와 다른 것을 잰다.
         이 칸은 검수 상태가 아니라 **결재 단계**다(2026-09-28 검증에서 이름을 바로잡음). */
      {
        header: '결재 단계', name: 'appr_stage', width: 110, align: 'center', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '';
          if (/반려/.test(v))      { s.style.cssText = 'font-weight:700;color:#B42318;'; }
          else if (/실패/.test(v)) { s.style.cssText = 'font-weight:700;color:#B42318;'; }
          else if (/대기|요청/.test(v)) { s.style.cssText = 'font-weight:700;color:#B54708;'; }
          return s;
        },
      },
      /* 목록에서 바로 서명한다 (2026-09-28 지시).
         건마다 상세를 열어 들어가면 결재가 몇 건 쌓인 날에는 그것만으로 한나절이 간다. */
      {
        header: '결재', name: 'sign_btn', width: 90, align: 'center', exportable: false,
        renderer: (v, row) => rtSignBtn(v, row),
      },
      /* 사람과 때를 나눈다 — 한 칸에 뭉치면 날짜로 정렬도 셈도 못 한다 */
      { header: '최종승인자', name: 'final_signer', width: 100, sortable: true },
      { header: '서명일시', name: 'final_signed_at', width: 150, align: 'center', sortable: true },
      /* 금액이 바뀐 건만 선다 — 금액 변경이 없으면 증빙은 손대지 않는 것이 규칙이다
         (2026-09-28 지시). 빈칸이 곧 「증빙은 그대로다」라는 말이다. */
      { header: '증빙 재발행', name: 'docs_reissued', width: 150, align: 'center', sortable: true },
      /* 글 자체가 아니라 있다ㆍ없다만 싣는다 — 읽는 자리는 상세다. 이름이 「비고」면
         글이 뜰 것으로 읽혀, 「유무」를 붙여 못박는다. */
      { header: '창고 검수 비고 유무', name: 'pl3_note', width: 130, align: 'center', sortable: true },
      {
        // 절차서의 기한을 넘긴 건. 묻히면 기한을 둔 뜻이 없다.
        header: '기한', name: 'overdue', width: 110, align: 'center', sortable: true,
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v || '';
          if (v) { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          return el;
        },
      },
      { header: '범위',     name: 'partial',  width: 60,  align: 'center', sortable: true },
      { header: '원 판매주문', name: 'origin_so', width: 130, sortable: true },
      {
        // 창고에 알렸는가. 못 알린 건은 눈에 띄어야 다시 보낸다.
        header: '반품 주문번호', name: 'return_so', width: 130, sortable: true,
        renderer: (v) => {
          const el = document.createElement('span');
          el.textContent = v ?? '';
          /* 「실패」만 붉게 — 「해당없음」은 아직 보낼 일이 없다는 뜻이라 문제가 아니다 */
          if (v === '실패')          { el.style.color = '#B54708'; el.style.fontWeight = '700'; }
          else if (v === '해당없음') { el.style.color = 'var(--gray-400)'; }
          return el;
        },
      },
      { header: '신청 사유', name: 'reason',   width: 110 },
      /* 접수할 때 담당자가 적어 둔 값이다 — 결재가 정한 금액은 아래 두 칸이다.
         이름을 가르지 않으면 교환 건에서 서로 반대로 말한다(2026-09-28 검증). */
      { header: '환불금액 (접수)', name: 'refund',   width: 110, align: 'right' },
      /* 결재로 정해진 금액은 방향을 갈라 둔다 (2026-09-28 지시) — 위의 「환불금액」은
         접수할 때 적어 둔 값이고, 아래 둘은 결재가 정한 실제로 움직일(움직인) 돈이다. */
      {
        header: '환불 (지급)', name: 'refund_out', width: 110, align: 'right', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '';
          if (v) { s.style.cssText = 'font-weight:700;color:#3C82C4;'; }
          return s;
        },
      },
      {
        header: '차액 입금 (청구)', name: 'topup_in', width: 120, align: 'right', sortable: true,
        renderer: (v) => {
          const s = document.createElement('span');
          s.textContent = v || '';
          if (v) { s.style.cssText = 'font-weight:700;color:#B54708;'; }
          return s;
        },
      },
      { header: '담당자',   name: 'assignee', width: 90 },
      // 접수한 사람과 승인한 사람은 다르다 — 절차서가 그렇게 나눈다
      { header: '접수자',   name: 'taker',    width: 90 },
      /* approved_by 는 「전자 승인」 단계를 누른 사람이다. 최종승인자 서명으로 왔을
         수도, 금액 변동이 없어 진행 단계에서 옮긴 것일 수도 있다 — 「반품」이라는
         말은 교환 건에도 떠서 헷갈렸다(2026-09-28 검증). */
      { header: '전자 승인자', name: 'approver', width: 100 },
      // 시ㆍ분ㆍ초까지 적는다 — 같은 날 두 번 오간 건은 날짜만으로 가릴 수 없다
      { header: '전자 승인일시', name: 'approved_at', width: 150, align: 'center', sortable: true },

      /* ── 무엇이 얼마나 되돌아왔는가 (요청서 4쪽) ────────── */
      { header: '원판매 주문수량', name: 'qty_ordered',  width: 120, align: 'right' },
      { header: '반품 수량',      name: 'qty_returned', width: 90,  align: 'right' },
      { header: '반품 Lot',       name: 'rt_lot',       width: 140 },
      { header: '수거 송장',      name: 'collect_no',   width: 130 },

      /* ── 어떻게 돌려줬는가 ──────────────────────────────── */
      { header: '환불수단',   name: 'refund_method', width: 110, align: 'center', sortable: true },
      { header: '환불일자',   name: 'refunded_at',   width: 100, align: 'center', sortable: true },
      { header: '환불은행',   name: 'refund_bank',   width: 100 },
      { header: '예금주',     name: 'refund_holder', width: 90 },
      { header: '환불계좌',   name: 'refund_acct',   width: 140 },
      { header: '카드사',     name: 'card_issuer',   width: 90 },
      { header: '유효기간',   name: 'card_expiry',   width: 90,  align: 'center' },
      { header: '승인번호',   name: 'approval_no',   width: 120 },
      { header: '취급점',     name: 'handling',      width: 120 },
      { header: '환불기관정보', name: 'refund_agency', width: 160 },
      { header: '환불 현금영수증번호', name: 'rt_cash_no',   width: 150 },
      { header: '환불 영수증 구분',   name: 'rt_cash_type', width: 120, align: 'center' },

      /* ── 원 주문의 가상계좌 (토스가 발급한 것) ───────────── */
      { header: '가상계좌번호',     name: 'va_no',     width: 140 },
      { header: '가상계좌은행',     name: 'va_bank',   width: 100 },
      { header: '가상계좌 예금주명', name: 'va_holder', width: 120 },

      /* ── 무엇을 물렸는가 ────────────────────────────────── */
      { header: '현금영수증 취소',   name: 'cr_cancel',   width: 120, align: 'center', sortable: true },
      { header: '전자세금계산서 취소', name: 'ti_cancel', width: 140, align: 'center', sortable: true },
      { header: '카드결제 취소',     name: 'card_cancel', width: 120, align: 'center', sortable: true },
      { header: '무통장결제 취소',   name: 'bank_cancel', width: 120, align: 'center', sortable: true },

      /* ── 기한과 적바림 ──────────────────────────────────── */
      // 입고일에서 셈해 나온다 — 입력한 값이 아니라 늘 규칙과 맞는다
      { header: '검수 기한',  name: 'due_inspect', width: 100, align: 'center', sortable: true },
      { header: '처리 기한',  name: 'due_final',   width: 100, align: 'center', sortable: true },
      // 적요는 통장에 찍히는 글자, 담당자메모는 우리끼리 보는 글이다
      { header: '적요',       name: 'memo',        width: 180 },
      { header: '담당자메모', name: 'staff_memo',  width: 200 },
      // 정산 — 「언제 접수했고 얼마였나」는 나란히 본다
      ...ceMoneyCols(),
      { header: '접수일',   name: 'created',  width: 100, sortable: true },

      /* 누구의 무슨 건인가 — 지금까지는 이름 하나뿐이라 상세를 열어야 알았다 */
      { header: '주민등록번호', name: 'resident_no',  width: 130 },
      { header: '환자 전화번호', name: 'mobile',       width: 140 },


      // 네 목록 화면이 함께 쓰는 칸 — 위드웍스 판매주문 현황의 차례다
      ...ceWwCols({ rrn: false }),
    ],
    data: @json($gridData),
  });
  window.__rtnGrid = grid;

  /* ── 반품 승인 ──────────────────────────────────────────────
     목록에서 고른 줄을 한 번에 승인한다. 누르기 전에 무엇을 승인하는지 보여 준다 —
     주문 금액과 본인부담, 조정이 붙는 건은 얼마가 움직이는지까지. 승인하고 나서
     상세를 열어 확인하게 두면 이미 늦다. */
  const 원 = n => (n || n === 0) ? Number(n).toLocaleString('ko-KR') + '원' : '—';

  window.rtnAskApprove = function () {
    const rows = grid.getCheckedRows();

    if (!rows.length) {
      showToast('승인할 건을 목록에서 선택해 주십시오.', 'warning');
      return;
    }

    const 될것 = rows.filter(r => r.ap_next);
    const 안될것 = rows.filter(r => !r.ap_next);

    document.getElementById('rtnApCount').textContent =
      될것.length + '건' + (안될것.length ? ' · 건너뜀 ' + 안될것.length + '건' : '');

    const 줄 = r => `
      <div style="padding:10px 0;border-bottom:1px solid var(--border-light);">
        <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:5px;">
          <b>${r.receipt}</b>
          <span style="color:var(--text-muted);">${r.patient} · ${r.type} · ${r.scenario}</span>
          <span style="flex:1;"></span>
          <span style="font-size:11px;color:#B54708;font-weight:600;">${r.ap_next} →</span>
        </div>
        <div style="display:grid;grid-template-columns:auto 1fr auto 1fr;gap:3px 10px;font-size:12px;">
          <span style="color:var(--text-muted);">주문번호</span><span>${r.order_no}</span>
          <span style="color:var(--text-muted);">사유</span><span>${r.reason}</span>
          <span style="color:var(--text-muted);">주문 금액</span><span>${원(r.ap_order_amt)}</span>
          <span style="color:var(--text-muted);">본인부담</span><span>${원(r.ap_copay)}</span>
          <span style="color:var(--text-muted);">수량</span><span>${r.qty_returned || '—'} / ${r.qty_ordered || '—'}${r.partial === '부분' ? ' (부분)' : ''}</span>
          <span style="color:var(--text-muted);">승인 주체</span><span>${r.ap_role || '—'}</span>
          ${r.ap_adjust === null || r.ap_adjust === undefined ? '' : `
            <span style="color:var(--text-muted);">조정 금액</span>
            <span style="grid-column:span 3;">
              <b>${r.ap_adjust_dir} ${원(r.ap_adjust)}</b>
              <span style="font-size:11px;color:${r.ap_saved ? 'var(--text-muted)' : '#B54708'};margin-left:6px;">
                ${r.ap_saved ? '입력한 값' : '아직 적지 않았습니다 — 줄에서 셈한 값입니다'}
              </span>
            </span>`}
        </div>
      </div>`;

    const 건너뜀 = 안될것.length ? `
      <div style="margin-top:10px;padding:9px 11px;background:var(--bg-muted,#F8FAFC);border-radius:8px;
                  font-size:12px;color:var(--text-muted);">
        승인을 기다리지 않는 ${안될것.length}건은 건너뜁니다 —
        ${안될것.slice(0, 5).map(r => r.receipt + ' (' + r.status + ')').join(', ')}${안될것.length > 5 ? ' 외' : ''}
      </div>` : '';

    document.getElementById('rtnApBody').innerHTML =
      (될것.length ? 될것.map(줄).join('') : '<div style="padding:14px 0;color:var(--text-muted);">승인할 건이 없습니다.</div>')
      + 건너뜀;

    document.getElementById('rtnApGo').disabled = 될것.length === 0;
    document.getElementById('rtnApIds').innerHTML =
      될것.map(r => `<input type="hidden" name="ids[]" value="${r.id}">`).join('');

    document.getElementById('rtnApWrap').style.display = 'block';
  };

  window.rtnApClose  = () => { document.getElementById('rtnApWrap').style.display = 'none'; };
  window.rtnApSubmit = () => {
    const b = document.getElementById('rtnApGo');
    b.disabled = true; b.textContent = '승인 중...';
    document.getElementById('rtnApForm').submit();
  };


  /* 목록 · 상세 · 접수 탭. 접수 탭을 열면 원 주문 찾기에 바로 손이 가도록 커서를 옮긴다. */
  const PANES = { list: 'rtnPaneList', show: 'rtnPaneShow', new: 'rtnPaneNew' };
  const TABS  = { list: 'rtnTabList',  show: 'rtnTabShow',  new: 'rtnTabNew'  };

  /* ── 목록의 「결재」 단추와 서명 팝오버 (2026-09-28 지시) ──────────────── */

  let rtSignId = null, rtSignCv = null, rtSignCtx = null, rtSign칠함 = false, rtSign보냄 = false;

  window.rtSignClose = function () {
    document.getElementById('rtSignWrap').style.display = 'none';
    rtSignId = null; rtSignCv = null; rtSign칠함 = false;
  };

  window.rtSignOpen = async function (id) {
    rtSignId = id; rtSign칠함 = false; rtSign보냄 = false;

    const wrap = document.getElementById('rtSignWrap');
    const body = document.getElementById('rtSignBody');
    document.getElementById('rtSignGo').style.display = 'none';
    document.getElementById('rtSignWhy').textContent = '';
    document.getElementById('rtSignNo').textContent = '';
    body.innerHTML = '<div style="padding:18px 0;color:var(--text-muted);">불러오는 중입니다…</div>';
    wrap.style.display = 'block';

    try {
      const res = await fetch('/order-returns/' + id + '/approval-detail', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const j = await res.json();
      rtSignDraw(j);
    } catch (e) {
      body.innerHTML = '<div style="padding:18px 0;color:var(--danger);">'
                     + '내용을 조회하지 못했습니다 — 다시 열어 주십시오.</div>';
    }
  };

  function rtSignDraw(j) {
    document.getElementById('rtSignNo').textContent = j.receipt || '';

    const 줄 = (라벨, 값, 굵게, 빛) => (값 ||값 === 0)
      ? '<div class="rt-kv" style="display:flex;padding:6px 0;border-bottom:1px solid var(--border-light);">'
        + '<span style="width:110px;flex-shrink:0;color:var(--text-muted);">' + 라벨 + '</span>'
        + '<span style="flex:1;font-weight:' + (굵게 ? '700' : '500') + ';'
        + (빛 ? 'color:' + 빛 + ';' : '') + '">' + 값 + '</span></div>'
      : '';

    const 돈 = (n) => Number(n || 0).toLocaleString();

    let h = '';
    h += 줄('구분', j.type);
    h += 줄('주문번호', j.order_no);
    h += 줄('고객', j.patient);
    h += 줄('입고 검수', j.result + (j.defect && j.defect_qty ? ' · ' + j.defect_qty + '개' : ''),
            true, j.defect ? '#B42318' : '');
    if (j.defect && j.defect_note) { h += 줄('하자 내용', j.defect_note); }
    if (j.deduct)                  { h += 줄('차감 금액', 돈(j.deduct) + '원', true, '#B42318'); }
    h += 줄('책임자 승인', (j.manager || '') + (j.manager_at ? ' · ' + j.manager_at : ''));
    h += 줄('결재 단계', j.stage);

    /* 나가는 돈ㆍ들어오는 돈을 나란히 — 한 칸에 담으면 어느 쪽인지 가릴 수 없다 */
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0;">'
       + rtSign돈칸('환불 (고객에게 지급)', j.refund_out, 'give')
       + rtSign돈칸('차액 입금 (고객에게 청구)', j.topup_in, 'take')
       + '</div>';

    if (j.signed) {
      h += 줄('서명', (j.signer || '') + (j.signed_at ? ' · ' + j.signed_at : ''), true);
      if (j.sign_ip) { h += 줄('서명 IP', j.sign_ip); }
      if (j.sign_image) {
        h += '<div style="margin-top:8px;"><img src="' + j.sign_image + '" alt="최종승인자 서명"'
           + ' style="max-width:240px;max-height:90px;border:1px solid var(--border);'
           + 'border-radius:6px;background:#fff;"></div>';
      }
    } else if (j.can_sign) {
      h += '<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">'
         + '아래에 서명하시면 즉시 처리됩니다.</div>'
         + '<canvas id="rtSignCanvas" style="width:100%;height:150px;border:1px dashed var(--border);'
         + 'border-radius:8px;background:#fff;touch-action:none;display:block;margin-top:6px;"></canvas>'
         + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">'
         + '<span style="font-size:12px;color:var(--text-muted);">화면을 터치하거나 마우스로 서명해 주십시오.</span>'
         + '<button type="button" class="ds-btn ds-btn-sm" onclick="rtSign지우기()">지우기</button></div>';
    } else {
      h += '<div style="font-size:12px;color:var(--text-muted);margin-top:6px;">'
         + '이 건은 여기서 서명할 수 없습니다 — 책임자 검수 승인이 끝나야 하고,'
         + ' 최종승인자 권한이 있어야 합니다.</div>';
    }

    document.getElementById('rtSignBody').innerHTML = h;

    const go = document.getElementById('rtSignGo');
    if (! j.signed && j.can_sign) {
      document.getElementById('rtSignForm').action = j.sign_url;
      go.style.display = '';
      go.disabled = true;
      document.getElementById('rtSignWhy').textContent = '서명란에 서명해 주십시오.';
      requestAnimationFrame(rtSign캔버스);
    } else {
      go.style.display = 'none';
    }
  }

  function rtSign돈칸(이름, 값, 갈래) {
    const 있다 = Number(값 || 0) > 0;
    const 바탕 = 있다 ? (갈래 === 'take' ? '#FEF3C7' : 'var(--primary-light)') : 'var(--gray-50,#fafbfc)';
    const 테   = 있다 ? (갈래 === 'take' ? '#F2C97D' : 'var(--primary-200)') : 'var(--border)';
    const 글빛 = 있다 ? (갈래 === 'take' ? '#B54708' : 'var(--primary)') : 'var(--text-muted)';
    return '<div style="border:1px solid ' + 테 + ';border-radius:9px;padding:10px 12px;background:' + 바탕 + ';">'
         + '<div style="font-size:11.5px;font-weight:700;color:var(--text-muted);">' + 이름 + '</div>'
         + '<div style="font-size:17px;font-weight:800;margin-top:3px;color:' + 글빛 + ';">'
         + (있다 ? Number(값).toLocaleString() + '원' : '해당 없음') + '</div></div>';
  }

  /* 서명판 — 상세의 결재 판과 같은 방식이다 */
  function rtSign캔버스() {
    rtSignCv = document.getElementById('rtSignCanvas');
    if (!rtSignCv) return;

    const r = rtSignCv.getBoundingClientRect();
    const d = window.devicePixelRatio || 1;
    rtSignCv.width  = Math.round(r.width  * d);
    rtSignCv.height = Math.round(r.height * d);
    rtSignCtx = rtSignCv.getContext('2d');
    rtSignCtx.scale(d, d);
    rtSignCtx.lineWidth = 2.2;
    rtSignCtx.lineCap = 'round';
    rtSignCtx.lineJoin = 'round';
    rtSignCtx.strokeStyle = '#111827';
    rtSign칠함 = false;
    rtSign셈();

    let 그리는중 = false;
    const 자리 = (e) => {
      const b = rtSignCv.getBoundingClientRect();
      const p = e.touches ? e.touches[0] : e;
      return { x: p.clientX - b.left, y: p.clientY - b.top };
    };
    rtSignCv.onmousedown = (e) => { e.preventDefault(); 그리는중 = true; const p = 자리(e); rtSignCtx.beginPath(); rtSignCtx.moveTo(p.x, p.y); };
    rtSignCv.onmousemove = (e) => { if (!그리는중) return; e.preventDefault(); const p = 자리(e); rtSignCtx.lineTo(p.x, p.y); rtSignCtx.stroke(); rtSign칠함 = true; rtSign셈(); };
    window.addEventListener('mouseup', () => { 그리는중 = false; });
    rtSignCv.addEventListener('touchstart', (e) => { e.preventDefault(); 그리는중 = true; const p = 자리(e); rtSignCtx.beginPath(); rtSignCtx.moveTo(p.x, p.y); }, { passive: false });
    rtSignCv.addEventListener('touchmove', (e) => { if (!그리는중) return; e.preventDefault(); const p = 자리(e); rtSignCtx.lineTo(p.x, p.y); rtSignCtx.stroke(); rtSign칠함 = true; rtSign셈(); }, { passive: false });
    rtSignCv.addEventListener('touchend', () => { 그리는중 = false; });
  }

  window.rtSign지우기 = function () {
    if (!rtSignCtx) return;
    rtSignCtx.clearRect(0, 0, rtSignCv.width, rtSignCv.height);
    rtSign칠함 = false;
    rtSign셈();
  };

  function rtSign셈() {
    const go = document.getElementById('rtSignGo');
    if (go) go.disabled = !rtSign칠함 || rtSign보냄;
    document.getElementById('rtSignWhy').textContent =
      rtSign칠함 ? '서명하면 즉시 처리됩니다.' : '서명란에 서명해 주십시오.';
  }

  window.rtSignSubmit = function () {
    if (rtSign보냄 || !rtSign칠함) { rtSign셈(); return; }

    ceConfirm('서명하시겠습니까? 서명하는 즉시 환불 또는 차액 청구가 처리됩니다.').then(ok => {
      if (!ok) return;
      rtSign보냄 = true;
      rtSign셈();
      document.getElementById('rtSignData').value = rtSignCv.toDataURL('image/png');
      if (window.ceProgress) window.ceProgress('결재를 처리하고 있습니다');
      document.getElementById('rtSignForm').submit();
    });
  };

  window.rtnPanel = function (which) {
    if (!PANES[which]) which = 'list';
    Object.keys(PANES).forEach(k => {
      document.getElementById(PANES[k]).style.display = k === which ? '' : 'none';
      document.getElementById(TABS[k]).classList.toggle('active', k === which);
    });
    if (which === 'new') document.getElementById('rtoQ')?.focus();
  };

  /* 다른 화면에서 「신청 등록」으로 들어오면 접수 탭을 펴고 원 주문을 앉힌다.
     이 스크립트는 접수 폼보다 뒤에 돌아 rtnPanel·rtoPreset 이 모두 준비돼 있다. */
  (function () {
    const p = new URLSearchParams(location.search);
    const orderNo = p.get('order_no');
    if (!p.get('new') && !orderNo) return;
    rtnPanel('new');
    if (orderNo) window.rtoPreset?.(orderNo);
  })();

  /* 고른 건을 상세 탭에 들여온다. 탭 이름에 접수번호를 붙여 둔다 —
     탭을 여럿 오가다 보면 무엇을 열어 두었는지 잊는다. */
  function rtnShow(row) {
    const frame = document.getElementById('rtnShowFrame');
    /* frame=1 — 액자 안이라는 것을 서버에도 알린다. 레이아웃은 self!==top 으로도
       알아채지만 그것은 화면이 그려진 뒤라, 「목록으로」를 아예 내보내지 않으려면
       서버가 알아야 한다. */
    const url   = SHOW_BASE + '/' + row.id + '?frame=1';
    if (frame.dataset.url !== url) {
      frame.src = url;
      frame.dataset.url = url;
    }
    frame.style.display = '';
    document.getElementById('rtnShowEmpty').style.display = 'none';
    document.getElementById('rtnTabShow').textContent =
      '상세 내용' + (row.receipt ? ' · ' + row.receipt : '');
    rtnPanel('show');
  }
  /* wwGrid 에는 on() 이 없다 — 다른 목록 화면과 같이 셀에서 행 번호를 읽는다. */
  document.getElementById('rtnGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row?.id) rtnShow(row);
  });
})();
</script>
@endpush
