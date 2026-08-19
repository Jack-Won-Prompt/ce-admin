@extends('layouts.app')

@section('title', '환자 정보')
@section('page-title', '환자 관리')
@section('breadcrumb', '홈 / 환자 관리')

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
{{-- ── 상담 창 — 화면 안에 떠 있는 창 ────────────────────
     전화를 받으며 적는 자리다. 브라우저 창을 따로 띄우면 보던 목록이 뒤로 숨고
     팝업 차단에 막히기도 한다. 화면 안에 띄우되 뒤를 덮지 않는다 — 상담을 적는 동안에도
     목록을 훑고 다른 탭을 눌러야 하는 일이 있다. 머리를 잡아 옮기고 모서리로 크기를 바꾼다. --}}
<div class="cs-win" id="csModal" style="display:none;" role="dialog" aria-labelledby="csTitle">
  <div class="cs-box">
    <div class="cs-head" id="csHead">
      <i class="bx bx-conversation"></i>
      <span id="csTitle">상담하기</span>
      <button type="button" onclick="csClose()" aria-label="닫기">&times;</button>
    </div>

    <div class="cs-body">
      <div class="cs-row two">
        <div class="cs-f">
          <label>상담일시 *</label>
          <input type="date" id="csDate" class="form-control">
        </div>
        <div class="cs-f">
          <label>통화번호</label>
          <input type="text" id="csCallNo" class="form-control" maxlength="30" placeholder="010-0000-0000">
        </div>
      </div>

      <div class="cs-row two">
        <div class="cs-f">
          <label>상담 유형</label>
          <select id="csType" class="form-control form-select">
            <option value="">선택</option>
            <option value="1013">구매</option>
            <option value="1016">개인구매</option>
            <option value="1020">반품</option>
            <option value="1030">문의</option>
            <option value="1050">기타</option>
          </select>
        </div>
        <div class="cs-f">
          <label>상담 상태</label>
          <select id="csStatus" class="form-control form-select" onchange="csSyncReDate()">
            <option value="02">등록</option>
            <option value="50">재상담</option>
            <option value="95">확정</option>
            <option value="99">취소</option>
          </select>
        </div>
      </div>

      <div class="cs-row" id="csReDateWrap" style="display:none;">
        <div class="cs-f">
          <label>재상담일</label>
          <input type="date" id="csReDate" class="form-control">
        </div>
      </div>

      <div class="cs-row">
        <div class="cs-f">
          {{-- 이 창의 본디 목적이다 — 통화한 내용을 그대로 적는다 --}}
          <label>상담 내용 *</label>
          <textarea id="csContents" class="form-control" rows="8" maxlength="2000"
                    placeholder="고객이 말한 내용을 그대로 적어 두면 다음 사람이 이어받기 쉽습니다."></textarea>
          <span class="cs-hint"><b id="csLen">0</b>/2000자</span>
        </div>
      </div>
    </div>

    <div class="cs-foot">
      <span class="cs-hint" id="csNote">적은 내용은 저장을 눌러야 남습니다.</span>
      <button type="button" class="ds-btn" onclick="csClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" id="csSaveBtn" onclick="csSave(this)">저장</button>
    </div>
    {{-- 오른쪽 아래 모서리를 잡아 크기를 바꾼다 --}}
    <div class="cs-grip" id="csGrip" title="크기 조절"></div>
  </div>
</div>

@endsection

@push('styles')
<style>
  /* 상담 창 — 뒤를 덮지 않고 떠 있는다. 뒤 화면은 그대로 쓸 수 있다. */
  .cs-win { position: fixed; z-index: 1100; display: none; }
  .cs-box { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); box-shadow: 0 20px 60px rgba(0,0,0,.28); overflow: hidden; }
  .cs-head { display: flex; align-items: center; gap: 8px; padding: 11px 14px;
             background: var(--primary); color: #fff; font-size: 13px; font-weight: 700;
             cursor: move; user-select: none; }
  /* 옮기는 동안에는 글자가 잡히지 않게 — 끌다가 문장이 파랗게 뒤집히면 성가시다 */
  .cs-win.is-moving, .cs-win.is-moving * { user-select: none; }
  .cs-grip { position: absolute; right: 0; bottom: 0; width: 16px; height: 16px;
             cursor: nwse-resize; }
  .cs-grip::after { content: ''; position: absolute; right: 3px; bottom: 3px; width: 8px; height: 8px;
                    border-right: 2px solid var(--gray-300); border-bottom: 2px solid var(--gray-300); }
  .cs-head span { flex: 1; }
  .cs-head button { background: none; border: none; color: #fff; font-size: 17px;
                    line-height: 1; cursor: pointer; }
  .cs-body { flex: 1; min-height: 0; padding: 14px; display: flex; flex-direction: column;
             gap: 10px; overflow-y: auto; }
  /* 창이 커지면 적는 자리가 함께 커져야 한다 — 칸만 남고 여백이 늘면 뜻이 없다 */
  .cs-body .cs-row:last-child { flex: 1; min-height: 0; }
  .cs-body .cs-row:last-child .cs-f { flex: 1; min-height: 0; }
  .cs-f textarea#csContents { flex: 1; min-height: 120px; }
  .cs-row { display: flex; flex-direction: column; gap: 10px; }
  .cs-row.two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .cs-f { display: flex; flex-direction: column; gap: 4px; }
  .cs-f label { font-size: 11.5px; font-weight: 600; color: var(--gray-700); }
  .cs-f textarea.form-control { height: auto; padding: 8px 10px; line-height: 1.7; resize: vertical; }
  .cs-hint { font-size: 11px; color: var(--text-muted); }
  .cs-foot { display: flex; align-items: center; gap: 6px; padding: 10px 14px;
             border-top: 1px solid var(--border); }
  .cs-foot .cs-hint { margin-right: auto; }

  /* 상담내역 탭 — 사람 이름이 붙고, 닫는 단추가 오른쪽에 붙는다 */
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
  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(67,56,202,.3);backdrop-filter:blur(2px);z-index:200;align-items:center;justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:var(--bg-card);border-radius:12px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(75,70,92,.25); }
  /* 머리·본문·바닥 규격은 Figma 120:917(환자 추가 모달) 실측 —
     머리 pad 16/24 · 제목 14px/700 lh22, 본문 pad 24, 바닥 pad 16/24 · gap 8 */
  .modal-header { padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px; }
  .modal-header h3 { font-size:14px;font-weight:700;line-height:22px;margin:0;flex:1;color:var(--text-primary); }
  .modal-body   { padding:24px; }
  .modal-footer { padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--gray-0);border-radius:0 0 12px 12px; }
  /* 시안 하단 버튼 글자는 14px/500 (본문 버튼 13px/500 과 다른 유일한 자리).
     줄높이는 전역 .btn 의 20px 을 그대로 둬 버튼 높이 32 를 지킨다. */
  .modal-footer .btn { font-size:14px;font-weight:500; }
  .form-grid-2  { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
  .form-group   { display:flex;flex-direction:column;gap:8px; }
  /* 전역 .form-label 은 margin-bottom:5px 을 갖고 있다. 위 flex gap 8 과 더해지면
     라벨↔입력 간격이 13px 이 되어 시안(라벨 21 + gap 8 + 입력 32)과 어긋난다.
     gap 하나만 남긴다 — taxinvoice 의 .ti-card-body .form-label 과 같은 처리다. */
  .form-group .form-label { margin-bottom:0; }

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

  .pt-radios { display:flex; gap:8px; }
  .pt-radio {
    display:inline-flex; align-items:center; gap:8px; flex:1; min-width:0;
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
  /* 상세내용 탭 안 이력 카드(전체폭) */
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
      {{-- 환자 한 사람의 모든 것은 옆 탭에서 본다. 다른 화면으로 건너가면 어떤 조건으로
           찾고 있었는지가 끊기고, 돌아오려면 처음부터 다시 찾아야 한다. --}}
      <button type="button" id="pnlBtnFull" class="pnl-tab" onclick="pnlShow('full')">전체 상세</button>
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
      <i class="bx bx-user-pin" style="color:var(--primary);font-size:16px;"></i>
      <span id="pdName" style="font-weight:700;font-size:14px;line-height:22px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">-</span>
      {{-- 요청서 4쪽 '전체 상세 버튼이 앞쪽으로 오면 좋겠음' — 오른쪽 끝으로 밀지 않고
           이름 바로 뒤에 붙인다(margin-left:auto 제거). --}}
      <a id="pdMore" href="#" class="btn btn-outline btn-sm" style="white-space:nowrap;"
         onclick="pfOpen(event)">전체 상세</a>
    </div>
    <div class="tab-bar">
      <button type="button" class="tab-btn active" data-tab="rx"       onclick="ptTab('rx')"><i class="fa-solid fa-file-medical"></i> 처방전 이력 <span class="cnt" id="pdCntRx">0</span></button>
      <button type="button" class="tab-btn"        data-tab="counsel"  onclick="ptTab('counsel')"><i class="fa-solid fa-comments"></i> 상담이력 <span class="cnt" id="pdCntCs">0</span></button>
      <button type="button" class="tab-btn"        data-tab="purchase" onclick="ptTab('purchase')"><i class="fa-solid fa-cart-shopping"></i> 구매이력 <span class="cnt" id="pdCntPu">0</span></button>
    </div>
    <div class="pt-pane active" id="pd-rx"></div>
    <div class="pt-pane" id="pd-counsel"></div>
    <div class="pt-pane" id="pd-purchase"></div>
  </div>
</div>{{-- /#pnlDetail --}}

{{-- ── 전체 상세 탭 — 환자 상세 화면을 그대로 들여온다 ── --}}
<div id="pnlFull" style="display:none;">
  {{-- 상세 화면은 이미 한 벌 있다. 두 벌로 만들면 한쪽만 고쳐져 서로 다른 것을 보여 준다.
       액자 안에서는 사이드바·네비가 스스로 숨는다(is-framed). --}}
  <div id="pfEmpty" class="pnl-empty">조회결과에서 환자를 고른 뒤 <b>전체 상세</b>를 누르면 여기에 나옵니다.</div>
  <iframe id="pfFrame" title="환자 전체 상세" style="display:none;width:100%;border:0;
          height:calc(100vh - 300px);min-height:520px;"></iframe>
</div>{{-- /#pnlFull --}}

{{-- 상담내역 탭은 사람마다 하나씩 만들어 붙인다(pcEnsureTab) — 두 사람을 견주며
     일하는 때가 있어 한 자리를 돌려 쓰면 방금 보던 것이 사라진다. --}}
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
      <div class="form-grid-2" style="margin-bottom:12px;">
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
      <div class="form-grid-2" style="margin-bottom:12px;">
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
      <div class="form-grid-2" style="margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">휴대폰</label>
          <input type="text" class="form-control" id="add-mobile" placeholder="010-XXXX-XXXX" data-phone />
        </div>
        <div class="form-group">
          <label class="form-label">일반 전화</label>
          <input type="text" class="form-control" id="add-phone" placeholder="02-XXXX-XXXX" data-phone />
        </div>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">주소</label>
        <input type="text" class="form-control" id="add-address" placeholder="주소 입력" />
      </div>
      <div class="form-grid-2" style="margin-bottom:12px;">
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
      <div class="form-group" style="margin-bottom:12px;">
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
      { header: '보호자 관계', name: 'g_relation', width: 90,  align: 'center' },
      { header: '보호자 이름', name: 'g_name',     width: 100 },
      { header: '보호자 생년월일', name: 'g_birth', width: 120 },
      { header: '보호자 신분증', name: 'g_id', width: 100, align: 'center', exportable: false,
        renderer: (v, row) => {
          if (!row.g_id_url) return '';
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'pt-chip clickable'; b.textContent = '보기';
          b.title = '보호자 신분증 보기';
          b.addEventListener('click', (e) => { e.stopPropagation();
            ptShowImage('보호자 신분증 — ' + (row.g_name || row.name), row.g_id_url); });
          return b;
        } },

      { header: '처방건수',     name: 'rx_count',        width: 80,  editor: 'number', align: 'center', sortable: true },
      { header: '재구매일',     name: 'repurchase_date', width: 160, sortable: true },
      { header: '등록일',       name: 'created',         width: 110, sortable: true },
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
  /* 상담내역 탭이 사람마다 생기고 없어지므로 목록은 고정이 아니다 */
  const PANES = { list: 'pnlList', detail: 'pnlDetail', full: 'pnlFull' };
  const TABS  = { list: 'pnlBtnList', detail: 'pnlBtnDetail', full: 'pnlBtnFull' };

  window.pnlShow = function (which) {
    if (!PANES[which]) which = 'list';
    Object.keys(PANES).forEach(k => {
      document.getElementById(PANES[k]).style.display = k === which ? '' : 'none';
      document.getElementById(TABS[k]).classList.toggle('active', k === which);
    });
  };

  /* 「전체 상세」 — 다른 화면으로 건너가지 않고 옆 탭에 들여온다.
     아직 아무도 고르지 않았으면 누를 것이 없다. */
  let _pfId = null;

  window.pfOpen = function (e) {
    if (e) e.preventDefault();
    if (!_pfId) { pnlShow('full'); return; }

    const frame = document.getElementById('pfFrame');
    const url   = DETAIL_BASE + '/' + _pfId;
    if (frame.dataset.url !== url) {
      frame.src = url;
      frame.dataset.url = url;
    }
    frame.style.display = '';
    document.getElementById('pfEmpty').style.display = 'none';
    pnlShow('full');
  };

  /* 액자 안에서 다른 화면으로 건너가는 링크를 그대로 두면 그 작은 액자 안에 통째로
     열려 화면이 겹친다(환자 상세 안에 처방전 목록이 들어앉는 식이다).
     환자 목록으로 가는 링크는 바깥의 조회 결과 탭으로 돌리고, 그 밖의 화면은
     워크스페이스 탭으로 올려 보낸다. 같은 곳에서 온 문서라 안을 만질 수 있다. */
  document.getElementById('pfFrame').addEventListener('load', function () {
    const frameUrl = this.dataset.url;
    try {
      const d = this.contentDocument;
      if (!d) return;

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
  const pcTabs = {};     // { [환자id]: { name, grid, wired } }

  /* ── 상담 창 ────────────────────────────────────────────
     상담자가 고객과 통화한 내용을 그 자리에서 적는 자리다. 주문 등록 화면을 띄우던
     것을 그만둔다 — 그 화면은 처방과 주문을 다루는 자리라, 통화 한 통을 적기에는
     묻는 것이 너무 많았다.

     적다 만 채로 닫는 일이 잦아, 무엇이든 적혀 있으면 닫기 전에 물어본다. */
  let _csPatient = null;
  let _csDirty   = false;

  /* 창 자리와 크기 — 옮겼던 자리는 기억해 둔다. 두 번째부터는 놓아 둔 곳에서 열린다.
     화면 밖으로는 나가지 않게 붙든다. */
  let _csBox = null;

  function _csApplyBox(box) {
    const win = document.getElementById('csModal');
    const w = Math.max(380, Math.min(box.w, window.innerWidth  - 16));
    const h = Math.max(320, Math.min(box.h, window.innerHeight - 16));
    const left = Math.max(0, Math.min(box.left, window.innerWidth  - w));
    const top  = Math.max(0, Math.min(box.top,  window.innerHeight - h));

    win.style.left   = left + 'px';
    win.style.top    = top  + 'px';
    win.style.width  = w + 'px';
    win.style.height = h + 'px';
    _csBox = { left, top, w, h };
  }

  function _csDefaultBox() {
    const w = Math.min(580, Math.round(window.innerWidth  * 0.5));
    const h = Math.min(620, Math.round(window.innerHeight * 0.8));
    return {
      // 오른쪽에 둔다 — 왼쪽 목록을 보면서 적는 일이 많다
      left: Math.max(8, window.innerWidth - w - 24),
      top:  Math.max(8, Math.round((window.innerHeight - h) / 2)),
      w, h,
    };
  }

  /* 머리를 잡아 옮기고, 오른쪽 아래 모서리를 잡아 크기를 바꾼다.

     손잡이에 바로 걸지 않고 문서에서 받는다 — 이 스크립트가 창 마크업보다 먼저 도는
     자리라, 그때 손잡이를 찾으면 없다(예전에는 그래서 창이 꿈쩍도 하지 않았다).
     pointer 이벤트라 커서가 창 밖으로 나가도 놓을 때까지 따라온다. */
  (function () {
    let mode = null, sx = 0, sy = 0, start = null;

    document.addEventListener('pointerdown', (e) => {
      const win = document.getElementById('csModal');
      if (!win || win.style.display === 'none') return;

      const onHead = e.target.closest?.('#csHead');
      const onGrip = e.target.closest?.('#csGrip');
      if (!onHead && !onGrip) return;
      // 머리의 닫기 단추를 누른 것은 옮기려는 뜻이 아니다
      if (onHead && e.target.closest('button')) return;

      mode = onGrip ? 'size' : 'move';
      sx = e.clientX; sy = e.clientY;
      start = { ..._csBox };
      win.classList.add('is-moving');
      e.preventDefault();
    });

    document.addEventListener('pointermove', (e) => {
      if (!mode || !start) return;
      const dx = e.clientX - sx, dy = e.clientY - sy;
      _csApplyBox(mode === 'move'
        ? { ...start, left: start.left + dx, top: start.top + dy }
        : { ...start, w: start.w + dx, h: start.h + dy });
    });

    const end = () => {
      if (!mode) return;
      mode = null; start = null;
      document.getElementById('csModal')?.classList.remove('is-moving');
    };
    document.addEventListener('pointerup', end);
    document.addEventListener('pointercancel', end);

    // 화면 크기가 바뀌면 창이 밖에 나가 있을 수 있다
    window.addEventListener('resize', () => {
      const win = document.getElementById('csModal');
      if (win && win.style.display !== 'none' && _csBox) _csApplyBox(_csBox);
    });
  })();

  window.csOpen = function (id, name) {
    const p = id ? { id, name } : pcActive();
    if (!p) { showToast('먼저 환자를 고르십시오.', 'warning'); return; }

    _csPatient = p;
    _csDirty   = false;

    document.getElementById('csTitle').textContent  = (p.name || '') + ' 상담하기';
    document.getElementById('csDate').value         = new Date().toISOString().slice(0, 10);
    document.getElementById('csCallNo').value       = pcTabs[p.id]?.mobile || '';
    document.getElementById('csType').value         = '';
    document.getElementById('csStatus').value       = '02';
    document.getElementById('csReDate').value       = '';
    document.getElementById('csContents').value     = '';
    document.getElementById('csLen').textContent    = '0';
    document.getElementById('csNote').textContent   = '적은 내용은 저장을 눌러야 남습니다.';
    csSyncReDate();

    const win = document.getElementById('csModal');
    win.style.display = 'block';
    _csApplyBox(_csBox ?? _csDefaultBox());
    setTimeout(() => document.getElementById('csContents').focus(), 50);
  };

  /* 재상담으로 두면 언제 다시 걸지가 곧 다음 일이 된다 — 그때만 날짜를 묻는다 */
  window.csSyncReDate = function () {
    const on = document.getElementById('csStatus').value === '50';
    document.getElementById('csReDateWrap').style.display = on ? '' : 'none';
  };

  window.csClose = async function () {
    if (_csDirty) {
      const ok = await ceConfirm('적은 내용을 저장하고 닫을까요?\n저장하지 않으면 적은 것이 사라집니다.',
                                 { tone: 'warning', confirmText: '저장하고 닫기', cancelText: '그냥 닫기' });
      if (ok) { await csSave(document.getElementById('csSaveBtn')); return; }
    }
    document.getElementById('csModal').style.display = 'none';
    _csDirty = false;
  };

  window.csSave = async function (btn) {
    const contents = document.getElementById('csContents').value.trim();
    if (!contents) { showToast('상담 내용을 적어 주십시오.', 'warning'); return; }

    BtnState.loading(btn, '저장 중...');
    try {
      const res = await apiRequest(`${DETAIL_BASE}/${_csPatient.id}/counsels`, 'POST', {
        counsel_date:     document.getElementById('csDate').value,
        counsel_type:     document.getElementById('csType').value || null,
        counsel_status:   document.getElementById('csStatus').value || null,
        counsel_call_no:  document.getElementById('csCallNo').value.trim() || null,
        counsel_re_date:  document.getElementById('csStatus').value === '50'
                            ? (document.getElementById('csReDate').value || null) : null,
        counsel_contents: contents,
      });
      if (!res.success) throw new Error(res.message || '저장하지 못했습니다.');

      showToast(`상담을 적어 두었습니다 (${res.counsel_no})`, 'success', 4000);
      _csDirty = false;
      document.getElementById('csModal').style.display = 'none';
      // 방금 적은 것이 목록에 보여야 한다
      pcLoad(_csPatient.id, _csPatient.name);
    } catch (e) {
      showToast('저장하지 못했습니다: ' + (e.message || ''), 'danger', 6000);
    } finally {
      BtnState.reset(btn);
    }
  };

  /* 무엇이든 손댔으면 닫을 때 물어본다.
     칸마다 걸지 않고 창 전체에서 받는다 — 칸을 늘려도 따라오고, 스크립트가 창보다
     먼저 돌아도 놓치지 않는다. */
  document.addEventListener('input', (e) => {
    if (!e.target.closest?.('#csModal')) return;
    _csDirty = true;
    if (e.target.id === 'csContents') {
      document.getElementById('csLen').textContent = e.target.value.length;
    }
  });
  document.addEventListener('change', (e) => {
    if (e.target.closest?.('#csModal')) _csDirty = true;
  });

  /* 바깥을 눌러도 닫지 않는다 — 뒤 화면을 그대로 쓰라고 띄운 창이라, 목록을 한 번
     눌렀다고 적던 것이 사라지면 안 된다. 닫는 길은 닫기 단추와 Esc 둘이다.
     Esc 는 이 창 안에 손이 가 있을 때만 듣는다. */
  document.addEventListener('keydown', (e) => {
    const win = document.getElementById('csModal');
    if (e.key !== 'Escape' || !win || win.style.display === 'none') return;
    if (!win.contains(document.activeElement)) return;
    csClose();
  });

  /** 지금 보고 있는 상담내역 탭의 환자 */
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

      /* 한 줄이 곧 「무엇을 했나」다 — 상담번호와 날짜를 내용 앞에 붙여 한 칸으로 읽는다.
         나머지는 언제·어디까지·무슨 갈래·누가 순으로 둔다. */
      const rows = (d.counseling ?? []).map(c => ({
        action:  '#' + (c.counsel_no || '-') + (c.date ? ' ' + c.date : '')
                 + (c.note ? ' : ' + c.note : ''),
        date:    c.date || '',
        status:  c.status || '',
        re_date: c.re_date || '',
        channel: [c.type, c.call_no].filter(Boolean).join(' · '),
        rx_number: c.rx_number || '',
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
          height: 'auto', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
          columns: [
            { header: '상담 내용', name: 'action',    width: 420, sortable: true },
            { header: '상담일시',  name: 'date',      width: 110, sortable: true, align: 'center' },
            { header: '상태',      name: 'status',    width: 80,  sortable: true, align: 'center' },
            { header: '재상담일',  name: 're_date',   width: 100, sortable: true, align: 'center' },
            { header: '갈래',      name: 'channel',   width: 130, sortable: true },
            { header: '처방번호',  name: 'rx_number', width: 150, sortable: true },
            { header: '담당자',    name: 'by',        width: 90,  sortable: true },
          ],
          data: rows,
        });

        /* 더블클릭하면 그 처방전으로 간다. 화면 탭으로 열어야 보고 있던 목록이 남는다.
           wwGrid 에는 on() 이 없어 셀에서 행 번호를 읽는다. */
        gridEl.addEventListener('dblclick', (e) => {
          const cell = e.target.closest('[data-row-index]');
          if (!cell) return;
          const row = pcTabs[id].grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
          if (row?.url) window.ceOpenTab(row.url, '주문 - ' + (row.rx_number || ''), 'bx-scan');
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

  /* 상담 창이 저장하면 그 사람의 탭을 새로 읽는다 — 방금 적은 상담이 거기 보여야 한다 */
  window.addEventListener('message', (e) => {
    if (e.origin !== location.origin) return;
    if (e.data?.source !== 'ce-counsel' || e.data?.action !== 'saved') return;
    const p = pcActive();
    if (p) pcLoad(p.id, p.name);
  });

  async function ptLoad(id, tab = 'rx') {
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
      // 누르면 옆 탭에 들여온다. 주소는 남겨 둔다 — 가운데 클릭으로 새 창을 여는 길까지 막지 않는다.
      _pfId = id;
      const more = document.getElementById('pdMore');
      more.setAttribute('href', DETAIL_BASE + '/' + id);
      if (document.getElementById('pfFrame').dataset.url
          && document.getElementById('pfFrame').dataset.url !== DETAIL_BASE + '/' + id) {
        // 다른 환자를 골랐으면 들여둔 것을 비운다 — 이름과 내용이 어긋나면 안 된다
        document.getElementById('pfFrame').style.display = 'none';
        document.getElementById('pfFrame').removeAttribute('data-url');
        document.getElementById('pfEmpty').style.display = '';
      }
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

      // 상담내역 단추로 열었으면 그 탭을 먼저 보여 준다 — 한 번 더 누르게 하지 않는다
      window.ptTab(tab);
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
