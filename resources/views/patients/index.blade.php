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
@endsection

@push('styles')
<style>
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
          b.addEventListener('click', (e) => { e.stopPropagation(); ptLoad(row.id, 'counsel'); });
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
