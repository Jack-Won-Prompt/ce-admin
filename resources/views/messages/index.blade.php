{{-- resources/views/messages/index.blade.php --}}
@extends('layouts.app')

@section('title', '메시지 관리')
@section('page-title', '메시지 관리')
{{-- 시안(352:84) Frame 48101452 — 「홈 - 메시지 관리」 두 마디.
     홈 x336(w11) · 구분자 '-' x355(w6) · 화면명 x369(w55), 12/500 · 마디 사이 8.
     마디로 세우는 일은 이제 레이아웃이 한다 — 여기서는 낱말만 적는다. --}}
@section('breadcrumb', '홈 - 메시지 관리')

@push('styles')
<style>
  /* ── 탭 칩 (352:84 Frame 48101549) ───────────────────────────────
     시안 h31 · r999 · pad 6/10 · 12px/700 lh19 · 테두리 없음 · 칩 사이 gap 8.
     전역 .ds-chip 과 같은 규격이라 버튼에 .ds-chip 을 함께 붙였다.
     .ms-tab 이름은 msTab()·msChannel() 이 찾으므로 그대로 둔다.
     칩줄은 시안에서 카드다 —
       발송 탭   : 칩줄 + 구분선 + 검색줄이 한 카드 (1568×140)
       유형·이력 : 칩줄만 든 카드 (1568×55 = pad 12 + 31 + pad 12) */
  .ms-tabs { display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
             padding: 12px 16px; border-radius: 12px; background: var(--gray-0); }
  .ms-tabs:has(+ #pnlSend.active) {
    border-radius: 12px 12px 0 0;
    margin-bottom: -12px;               /* .page-body 의 gap 12 를 지운다 */
  }
  #pnlSend.active > .ds-filter-card {
    border-radius: 0 0 12px 12px;
    /* 시안 Vector 4 는 1536×1 — 카드 전폭이 아니라 좌우 16 안쪽에만 그어진다.
       테두리 대신 배경으로 그어야 카드 높이 140 이 그대로 나온다. */
    background-image: linear-gradient(var(--gray-100), var(--gray-100));
    background-repeat: no-repeat;
    background-position: 16px 0;
    background-size: calc(100% - 32px) 1px;
  }
  .ms-panel { display: none; }
  .ms-panel.active { display: block; }

  /* ── 발송 수단 라디오 (352:84 Frame 48101490) ─────────────────────
     탭 칩과 클래스는 같지만 시안에서는 전혀 다른 부품이다 —
     175×32 · r8 · pad 0/12 · gap 8 · bd 1px gray-200 · 라벨 13/400,
     12×12 정원 안에 6×6 흰 점. 고른 쪽도 테두리·배경은 그대로고 점 색만 바뀐다.
     patients 의 .pt-radio 와 같은 규격이다. */
  .ms-radios { display: flex; gap: 8px; }
  .ms-radios .ms-tab {
    display: inline-flex; align-items: center; gap: 8px; flex: 1; min-width: 0;
    height: 32px; padding: 0 12px; border-radius: 8px;
    background: var(--gray-0); border: 1px solid var(--gray-200);
    font-size: 13px; font-weight: 400; line-height: 21px; color: var(--gray-1000);
    white-space: nowrap; cursor: pointer; transition: var(--transition);
  }
  .ms-radios .ms-tab:hover { border-color: var(--primary); }
  /* 6×6 흰 점은 테두리로 만든다(12 - 3 - 3 = 6) */
  .ms-radio-dot { width: 12px; height: 12px; border-radius: 999px; flex-shrink: 0;
                  box-sizing: border-box; background: var(--gray-0);
                  border: 3px solid var(--gray-300); }
  .ms-radios .ms-tab.active .ms-radio-dot { border-color: var(--primary); }

  /* ── 발송 탭 2단 배치 (352:84 Frame 48101696) ─────────────────────
     왼쪽 389 · 오른쪽 1167 · gap 12. 두 열 모두 같은 높이까지 늘어난다. */
  .ms-send-grid { display: grid; grid-template-columns: 389px minmax(0, 1fr); gap: 12px; }
  @media (max-width: 1100px) { .ms-send-grid { grid-template-columns: 1fr; } }

  /* 왼쪽 카드 (Frame 48101496) — r12 · pad 12/16 · gap 12 */
  .ms-send-card { background: var(--gray-0); border-radius: 12px; padding: 12px 16px;
                  display: flex; flex-direction: column; gap: 12px; }
  /* 카드 제목 — 시안 357×28 · 14px/700 lh22 */
  .ms-send-title { display: flex; align-items: center; height: 28px;
                   font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-1000); }
  /* 필드 묶음 사이 16 · 라벨→컨트롤 8 (Frame 48101577) */
  .ms-fields { display: flex; flex-direction: column; gap: 16px; }
  .ms-field  { display: flex; flex-direction: column; gap: 8px; }
  /* 시안 필드 라벨 줄높이는 16 이다 (전역 .ds-field-label 은 21) */
  .ms-field > .ds-field-label { line-height: 16px; }

  /* ── 메시지 유형 고르기 · 발송 탭 (352:84 Frame 48101490) ──────────
     175×55 카드를 2열로 깐다 · gap 8 · r8 · pad 8/12 · bd 1px gray-200.
     5개면 3줄 181px 이라 스크롤이 없다. 고른 항목도 테두리·배경은 그대로다. */
  .ms-tpl { display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; cursor: pointer;
            border: 1px solid var(--gray-200); border-radius: 8px; background: var(--gray-0); }
  .ms-tpl-edit { margin-left: auto; display: flex; gap: 4px; flex-shrink: 0; }

  #msTplList { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
  #msTplList > div:only-child { grid-column: 1 / -1; }
  #msTplList .ms-tpl { align-items: center; }
  /* 고른 표시는 이제 이 점 하나가 전부 진다(시안엔 항목 테두리·배경 차이가 없다).
     시안은 12×12 정원 · 선택 #28798B · 비선택 #C2C5C8 안에 6×6 흰 점 —
     발송 수단 라디오(.ms-radio-dot)와 같은 부품이다. 네이티브 라디오는 13×13 에
     꺼진 쪽이 브라우저 기본 얇은 회색이라 시안과 다르다.
     msRenderTpl() 의 인라인은 accent-color·margin-top 뿐이라 모양은 여기서 잡는다. */
  #msTplList input[type="radio"] {
    appearance: none; -webkit-appearance: none;
    width: 12px; height: 12px; border-radius: 999px; flex-shrink: 0;
    box-sizing: border-box; background: var(--gray-0);
    border: 3px solid var(--gray-300);
  }
  #msTplList input[type="radio"]:checked { border-color: var(--primary); }
  #msTplList .ms-tpl-name { font-size: 13px; font-weight: 400; line-height: 21px; color: var(--gray-1000); }
  #msTplList .ms-tpl-desc { font-size: 11px; font-weight: 400; line-height: 18px; color: var(--gray-700); }

  /* ── 본문 칸 (352:84 Frame 48101690) — 357×200 · r8 · pad 8/12 ──── */
  /* 라벨줄은 SPACE_BETWEEN — 왼쪽 '본문', 오른쪽 끝에 바이트 표시 */
  .ms-body-label { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
  .ms-count { font-size: 11px; font-weight: 400; line-height: 13px; color: var(--gray-600); }
  textarea.ms-textarea { min-height: 200px; padding: 8px 12px; font-size: 13px; line-height: 21px; }

  /* 안내문 — 시안은 앞에 12×12 alert-circle, 아이콘↔글자 4, 글자 12/500 lh19.
     전역 .ds-grid-hint 와 같은 부품이지만 결과바 밖에서도 쓰므로 따로 둔다. */
  .ms-hint { display: flex; align-items: center; gap: 4px;
             font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600); }
  .ms-hint::before {
    content: ''; flex-shrink: 0; width: 12px; height: 12px; background: currentColor;
    -webkit-mask: var(--icon-alert-circle) center / contain no-repeat;
            mask: var(--icon-alert-circle) center / contain no-repeat;
  }

  /* ── 결과바 (352:84 Frame 48101582) ──────────────────────────────
     시안 결과바의 주 버튼은 꽉 찬 primary 다. 전역 .ds-btn-primary 는
     흰 배경 + primary 테두리라(app.blade.php 525줄) 이 화면 안에서만 덮는다.
     — 전역에 있어야 할 규칙이다. 보고서 globalCssNeeded 참고. */
  .ms-panel .ds-grid-bar .ds-btn-primary {
    background: var(--primary); border-color: var(--primary); color: var(--gray-0);
  }
  .ms-panel .ds-grid-bar .ds-btn-primary:hover {
    background: var(--primary-dark); border-color: var(--primary-dark);
  }
  /* '선택 발송' — 흰 배경 · 테두리 없음 · primary 글자 (시안 73×32) */
  .ms-panel .ds-grid-bar .ms-btn-quiet { color: var(--primary); }

  /* ── 메시지 유형 탭 (352:772) ────────────────────────────────────
     시안엔 바깥 큰 카드가 없다 — 흰 카드 9장(515×가변 · r12 · pad 12/16 · 안 gap 8)을
     3열(gap 12)로 페이지 바탕 위에 바로 깐다. 마크업을 옮기는 대신
     감싼 카드의 배경만 지운다. */
  #pnlTpl .ds-grid-card { background: transparent; border-radius: 0; overflow: visible; }
  #msTplManage { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
                 gap: 12px; align-items: start; }
  /* 0건·불러오는 중·실패 안내만 3열을 다 쓴다.
     유형이 딱 1건일 때 그 카드까지 1568 로 늘어나면 안 되므로 카드는 뺀다. */
  #msTplManage > div:only-child:not(.ms-tpl) { grid-column: 1 / -1; }
  #msTplManage .ms-tpl { border: none; border-radius: 12px; padding: 12px 16px; gap: 8px; }
  #msTplManage .ms-tpl > div:first-child { display: flex; flex-direction: column; gap: 8px; }
  /* 제목줄 — 이름 14/700 lh22 옆에 채널·코드 배지.
     배지 사이 여백은 배지 자신의 margin-left(6·4)가 이미 갖고 있어 가로 gap 은 0 이다. */
  #msTplManage .ms-tpl-name { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 0;
                              font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-1000); }
  /* 채널 배지(문자·알림톡) — 인라인이 line-height 를 잡지 않아 .ms-tpl-name 의 22 를
     물려받고 1+22+1 = 24 가 된다. 바로 옆 코드 배지는 22 라 알약 둘 높이가 어긋난다
     (시안은 둘 다 22). 인라인이 잡지 않은 line-height 만 준다 —
     크기·굵기·색은 인라인이라 손대지 못한다(보고서 참고). */
  #msTplManage .ms-tpl-name > span[style*="border-radius:999px"] { line-height: 20px; }
  /* 코드 배지 — 시안은 채널 배지와 같은 알약(h22 · r999 · pad 2/8 · bg gray-100)이다 */
  #msTplManage .ms-tpl-name > span[style*="monospace"] {
    padding: 2px 8px; border-radius: 999px; background: var(--gray-100); line-height: 18px;
  }
  #msTplManage .ms-tpl-desc { font-size: 13px; font-weight: 400; line-height: 21px; color: var(--gray-1000); }
  /* 본문 미리보기는 시안에서 별도 상자다 — r8 · pad 12 · bg gray-100 · 13/500 lh21.
     (미리보기 조각만 인라인 style 을 갖는다) */
  #msTplManage .ms-tpl-desc[style] { border-radius: 8px; padding: 12px; background: var(--gray-100);
                                     font-weight: 500; }

  /* '수정' — 시안 45×28 · r8 · pad 0/12 · bd 1px gray-200 · 12px/500 lh19 */
  .ms-mini { display: inline-flex; align-items: center; justify-content: center;
             height: 28px; padding: 0 12px; border-radius: 8px;
             border: 1px solid var(--gray-200); background: var(--gray-0);
             font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-1000); cursor: pointer; }
  .ms-mini:hover { border-color: var(--primary); color: var(--primary); }

  /* ── 메시지 유형 편집 창 (352:1444 수정 · 352:2176 추가) ───────────
     520×750 · r12 · bd 1px gray-200
     머리 520×54 pad 16/24 · 본문 pad 24 gap 16 · 바닥 520×72 pad 16/24 gap 8 */
  .ms-modal-head { display: flex; align-items: center; gap: 12px;
                   padding: 16px 24px; border-bottom: 1px solid var(--gray-200); }
  .ms-modal-title { flex: 1; min-width: 0; font-size: 14px; font-weight: 700; line-height: 22px;
                    color: var(--gray-1000); }
  /* 모달 닫기 규격은 24×24 · r6 · 16px 이다(전역 .modal-close 와 같은 부품) */
  .ms-modal-x { display:flex; align-items:center; justify-content:center;
                width:24px; height:24px; flex-shrink:0; padding:0;
                border:none; border-radius:6px; background:none;
                font-size:16px; line-height:1; color:var(--gray-500); cursor:pointer; }
  .ms-modal-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
  /* 시안 창은 750 이다. 세로가 짧은 화면(1280×720 · 노트북 1366×768)에서는 창이
     화면 밖으로 나가는데 position:fixed 라 페이지를 굴려도 저장·취소에 닿지 않는다.
     머리·바닥은 붙여 두고 본문만 스스로 구르게 한다 (176 = 머리 55 + 바닥 73 + 여유 48). */
  .ms-modal-body { max-height: calc(100vh - 176px); overflow-y: auto; }
  .ms-modal-foot { display: flex; align-items: center; justify-content: flex-end; gap: 8px;
                   padding: 16px 24px; border-top: 1px solid var(--gray-200); }
  /* 바닥 버튼 — 시안 65×40 / 120×40 · r8 · pad 0/20 · 14px/500 lh22
     (8 + 22 + 8 + 테두리 2 = 40) */
  .ms-modal-foot .btn { padding: 8px 20px; border-radius: 8px;
                        font-size: 14px; font-weight: 500; line-height: 22px; }
  .ms-modal-foot .btn-outline { color: var(--gray-1000); }
  .ms-modal-foot .btn-primary { min-width: 120px; justify-content: center; }

  /* '사용' 체크 — 시안 16×16 · r6. 켜짐은 테두리·체크·라벨 모두 primary,
     꺼짐은 테두리 gray-300 · 라벨 gray-500 */
  .ms-check { display: flex; align-items: center; gap: 6px; cursor: pointer;
              font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-500); }
  .ms-check input[type="checkbox"] {
    appearance: none; -webkit-appearance: none; margin: 0; flex-shrink: 0;
    width: 16px; height: 16px; border-radius: 6px; cursor: pointer;
    border: 1px solid var(--gray-300); background: var(--gray-0) no-repeat center;
  }
  .ms-check input[type="checkbox"]:checked {
    border-color: var(--primary);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2328798B' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l5 5 9-10'/%3E%3C/svg%3E");
    background-size: 10px 10px;
  }
  .ms-check:has(input:checked) { color: var(--primary); }
</style>
@endpush

@section('content')

<div class="ds-chips ms-tabs">
  <button type="button" class="ds-chip ms-tab active" data-panel="pnlSend"  onclick="msTab(this)">발송</button>
  <button type="button" class="ds-chip ms-tab"        data-panel="pnlTpl"   onclick="msTab(this)">메시지 유형</button>
  <button type="button" class="ds-chip ms-tab"        data-panel="pnlHist"  onclick="msTab(this)">발송 이력({{ count($histories) }})</button>
  {{-- 결과바에 있던 단추 — 판마다 쓰는 것이 달라 그 판을 보고 있을 때만 나온다 --}}
  <button type="button" class="ds-btn ds-btn-primary ms-panel-act" data-for="pnlTpl"
          style="margin-left:auto;display:none;" onclick="msTplNew()">유형 추가</button>
  <button type="button" class="ds-btn ms-panel-act" data-for="pnlHist"
          style="margin-left:auto;display:none;" onclick="window.__msHistGrid?.downloadExcel()">엑셀 저장</button>
</div>

{{-- ══ 발송 ══ --}}
<div id="pnlSend" class="ms-panel active">

  <form method="GET" action="{{ route('messages.index') }}" class="ds-filter-card">
    {{-- 시안 295px = 9열 중 2열(143.1×2 + 12) — 두 필드 폭이 같다 --}}
    <div class="ds-filter-fields">
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">거래처 검색</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="이름ㆍ전화번호">
      </div>
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">번호</label>
        <select name="has_mobile" class="form-control form-select">
          <option value="">전체 상태</option>
          <option value="1" {{ request('has_mobile') ? 'selected' : '' }}>번호가 있는 곳만</option>
        </select>
      </div>
    </div>
    <div class="ds-filter-actions">
      {{-- 시안(352:84)은 검색 왼쪽에 초기화를 늘 보여준다 — 조건을 걷어낸다.
           같은 라우트로 되돌아가는 링크라 조건 없이도 하는 일이 같다 --}}
      <a href="{{ route('messages.index') }}" class="ds-btn">초기화</a>
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
      {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 --}}
      <button type="button" class="ds-btn" onclick="window.__msGrid?.downloadExcel()">엑셀 저장</button>
      <button type="button" class="ds-btn ms-btn-quiet" onclick="msSend('selected')">선택 발송</button>
      <button type="button" class="ds-btn ds-btn-primary" onclick="msSend('all')">조건 전체 발송</button>
    </div>
  </form>

  <div class="ms-send-grid" style="margin-top:12px;">
    {{-- 왼쪽 — 무엇을 보낼지 --}}
    <div class="ms-send-card">
      <div class="ms-send-title">발송 수단</div>
      <div class="ms-fields">
        <div class="ms-field">
          <label class="ds-field-label">발송 수단</label>
          <div class="ms-radios">
            <button type="button" class="ms-tab active" data-ch="sms"      onclick="msChannel(this)"><span class="ms-radio-dot"></span>문자(SMS)</button>
            <button type="button" class="ms-tab"        data-ch="alimtalk" onclick="msChannel(this)"><span class="ms-radio-dot"></span>알림톡</button>
          </div>
        </div>

        <div class="ms-field">
          <label class="ds-field-label">메시지 유형</label>
          <div id="msTplList"></div>
        </div>

        <div id="msBodyWrap" class="ms-field">
          <label class="ds-field-label ms-body-label">본문 <span id="msLen" class="ms-count"></span></label>
          <textarea id="msBody" class="form-control ms-textarea" rows="7"
                    oninput="msUpdateLen()" placeholder="보낼 내용을 입력하세요"></textarea>
          <div class="ms-hint">
            #{고객명} 을 쓰면 받는 분 이름으로 바뀝니다.
          </div>
        </div>
        <div id="msAlimNote" style="display:none;font-size:12px;color:var(--gray-600);line-height:1.7;
             background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:10px 12px;">
          알림톡 본문은 <b>카카오에 등록된 템플릿</b>이 정합니다. 여기서 고친 내용은 나가지 않습니다.
        </div>
      </div>
    </div>

    {{-- 오른쪽 — 누구에게 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-card">
        <div class="pnl-tabs">
          <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
          {{-- 번호가 없는 곳은 발송에서 빠진다 — 골라 놓고 왜 덜 나갔는지 묻지 않게 적어 둔다 --}}
          <span class="ds-grid-hint" style="margin-left:auto;">번호 있는 곳 {{ number_format($sendable) }}곳</span>
        </div>
        <div id="msGrid"></div>
      </div>
    </div>
  </div>
</div>

{{-- ══ 메시지 유형 ══ --}}
<div id="pnlTpl" class="ms-panel">
  <div class="ds-grid-section">
    <div class="ds-grid-card">
      <div id="msTplManage"></div>
    </div>
  </div>
</div>

{{-- ══ 발송 이력 ══ --}}
<div id="pnlHist" class="ms-panel">
  <div class="ds-grid-section">
    <div class="ds-grid-card">
      <div id="msHistGrid"></div>
    </div>
  </div>
</div>

{{-- ── 메시지 유형 편집 창 ── --}}
<div id="msTplBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="msTplClose()"></div>
<div id="msTplModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:520px;max-width:94vw;background:var(--bg-card);border:1px solid var(--gray-200);
     border-radius:var(--radius-lg);box-shadow:0 4px 24px rgba(153,158,164,.24);z-index:1191;">
  <div class="ms-modal-head">
    <span id="msTplTitle" class="ms-modal-title">메시지 유형</span>
    <button onclick="msTplClose()" class="ms-modal-x">&#215;</button>
  </div>
  <div class="ms-modal-body">
    <div class="ms-field">
      <label class="ds-field-label">발송 수단</label>
      <select id="msTplChannel" class="form-control form-select">
        <option value="sms">문자(SMS)</option>
        <option value="alimtalk">카카오 알림톡</option>
      </select>
    </div>
    <div class="ms-field">
      <label class="ds-field-label">코드</label>
      <input type="text" id="msTplCode" class="form-control" maxlength="60" placeholder="order_confirmed" />
    </div>
    <div id="msTplCodeNote" style="display:none;font-size:11px;color:var(--alert-500);line-height:1.6;">
      알림톡 코드는 <b>카카오에 등록한 템플릿코드</b >와 같아야 실제로 발송됩니다.
    </div>
    <div class="ms-field">
      <label class="ds-field-label">이름</label>
      <input type="text" id="msTplLabel" class="form-control" maxlength="100" placeholder="주문 확정" />
    </div>
    <div class="ms-field">
      <label class="ds-field-label">설명</label>
      <input type="text" id="msTplDesc" class="form-control" maxlength="200" placeholder="언제 쓰는 유형인지" />
    </div>
    <div class="ms-field">
      <label class="ds-field-label">본문</label>
      <textarea id="msTplBody" class="form-control ms-textarea" rows="6"></textarea>
      <div class="ms-hint">
        #{고객명} #{처방번호} #{주문번호} #{본인부담금} #{금액} #{운송장번호} 를 쓸 수 있습니다.
      </div>
    </div>
    <label class="ms-check">
      <input type="checkbox" id="msTplActive" checked /> 사용
    </label>
    <div id="msTplResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
  </div>
  <div class="ms-modal-foot">
    <button type="button" class="btn btn-outline btn-sm" onclick="msTplClose()">취소</button>
    <button type="button" class="btn btn-outline btn-sm" id="msTplDelete" onclick="msTplDelete()"
            style="color:var(--alert-500);border-color:var(--gray-200);">삭제</button>
    <button type="button" class="btn btn-primary btn-sm" id="msTplSave" onclick="msTplSave()">저장</button>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const TPL      = @json($templates);
  const SEND_URL = @json(route('messages.send'));
  const TPL_URL  = @json(route('messages.templates'));
  let channel    = 'sms';
  let tplCode    = null;
  let manageRows = [];

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ── 거래처 그리드 ─────────────────────────────────────
  const grid = new wwGrid({
    el: document.getElementById('msGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: false,
    columns: [
      { header: '거래처명', name: 'name',     width: 140, sortable: true },
      { header: '전화번호', name: 'mobile',   width: 140, sortable: true },
      { header: '처방 건수', name: 'rx_count', width: 90,  sortable: true, align: 'right' },
      { header: '최근 처방', name: 'last_rx',  width: 120, sortable: true },
      { header: '등록일',   name: 'created',  width: 120, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__msGrid = grid;
  window.dsBindSelCount(grid, 'msSelCount');

  // ── 발송 이력 그리드 ──────────────────────────────────
  window.__msHistGrid = new wwGrid({
    el: document.getElementById('msHistGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: false,
    footer: false,
    columns: [
      { header: '일시',   name: 'at',      width: 150, sortable: true },
      { header: '수단',   name: 'ch',      width: 110, sortable: true },
      { header: '유형',   name: 'tpl',     width: 150, sortable: true },
      { header: '대상',   name: 'total',   width: 70,  align: 'right' },
      { header: '성공',   name: 'ok',      width: 70,  align: 'right' },
      { header: '실패',   name: 'ng',      width: 70,  align: 'right' },
      { header: '결과',   name: 'result',  width: 110 },
      { header: '보낸 사람', name: 'by',   width: 110 },
      { header: '내용',   name: 'content', width: 400 },
    ],
    data: @json($histories),
  });

  // ── 탭 ───────────────────────────────────────────────
  window.msTab = function (btn) {
    document.querySelectorAll('.ms-tabs .ms-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.ms-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(btn.dataset.panel).classList.add('active');
    // 판마다 다른 단추는 그 판일 때만 보인다
    document.querySelectorAll('.ms-panel-act').forEach(b => {
      b.style.display = b.dataset.for === btn.dataset.panel ? '' : 'none';
    });
    if (btn.dataset.panel === 'pnlTpl') msTplLoad();
  };

  window.msChannel = function (btn) {
    btn.parentElement.querySelectorAll('.ms-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    channel = btn.dataset.ch;
    tplCode = null;
    // 알림톡 본문은 카카오가 정한다 — 여기서 쓴 글이 나간다고 오해하지 않게 칸을 감춘다
    document.getElementById('msBodyWrap').style.display  = channel === 'sms' ? '' : 'none';
    document.getElementById('msAlimNote').style.display  = channel === 'sms' ? 'none' : 'block';
    msRenderTpl();
  };

  // ── 발송 화면의 유형 목록 ─────────────────────────────
  function msRenderTpl() {
    const list = document.getElementById('msTplList');
    const rows = Object.entries(TPL[channel] ?? {});
    if (!rows.length) {
      list.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--gray-500);">'
        + '등록된 유형이 없습니다. 위 <b>메시지 유형</b> 탭에서 추가하세요.</div>';
      return;
    }
    list.innerHTML = rows.map(([code, t]) => `
      <label class="ms-tpl ${code === tplCode ? 'on' : ''}" data-code="${esc(code)}" onclick="msPickTpl('${esc(code)}')">
        <input type="radio" name="ms_tpl" ${code === tplCode ? 'checked' : ''} style="accent-color:var(--primary);margin-top:2px;" />
        <div style="min-width:0;">
          <div class="ms-tpl-name">${esc(t.label)}</div>
          <div class="ms-tpl-desc">${esc(t.desc)}</div>
        </div>
      </label>`).join('');
  }

  window.msPickTpl = function (code) {
    tplCode = code;
    const t = (TPL[channel] ?? {})[code];
    if (channel === 'sms' && t) {
      document.getElementById('msBody').value = t.text ?? '';
      msUpdateLen();
    }
    msRenderTpl();
  };

  /* 문자는 한글이 2바이트다. 90바이트를 넘으면 장문(LMS)으로 나간다 — 요금이 다르다. */
  window.msUpdateLen = function () {
    const v = document.getElementById('msBody').value;
    let bytes = 0;
    for (const ch of v) bytes += ch.charCodeAt(0) > 127 ? 2 : 1;
    document.getElementById('msLen').textContent = `${bytes}바이트 · ${bytes > 90 ? 'LMS(장문)' : 'SMS(단문)'}`;
  };

  // ── 발송 ─────────────────────────────────────────────
  window.msSend = async function (scope) {
    const checked = grid.getCheckedRows();
    const body    = document.getElementById('msBody').value.trim();

    if (scope === 'selected' && !checked.length) { showToast('보낼 거래처를 체크하세요.', 'warning'); return; }
    if (channel === 'sms'  && !body)             { showToast('본문을 입력하세요.', 'warning'); return; }
    if (channel === 'alimtalk' && !tplCode)      { showToast('메시지 유형을 고르세요.', 'warning'); return; }

    const n = scope === 'all'
      ? {{ $sendable }}
      : checked.filter(r => r.raw).length;
    if (!n) { showToast('번호가 있는 거래처가 없습니다.', 'warning'); return; }

    // 이 대화상자는 글자를 그대로 보여준다(태그를 쓰지 않는다). 줄바꿈으로 나눈다.
    const what = channel === 'sms' ? '문자' : '알림톡';
    const ok = await ceConfirm(
      `${scope === 'all' ? '조건에 걸린 전체' : '선택한'} ${n.toLocaleString()}곳에 ${what}를 보냅니다.\n`
      + '실제로 발송되며 되돌릴 수 없습니다.',
      { title: `${what} 발송`, confirmText: '발송', tone: 'danger' }
    );
    if (!ok) return;

    const payload = {
      channel, scope,
      template_code: tplCode,
      content: channel === 'sms' ? body : '',
      patient_ids: scope === 'selected' ? checked.map(r => r.id) : [],
      // '조건 전체' 는 지금 화면의 검색 조건을 서버에서 다시 건다
      q: @json(request('q')), has_mobile: @json(request('has_mobile')),
    };

    try {
      const res = await fetch(SEND_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify(payload),
      });
      const d = await res.json();
      if (d.success) {
        showToast(d.message, 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(d.message ?? '발송 실패', 'error');
      }
    } catch (e) {
      showToast('네트워크 오류가 발생했습니다.', 'error');
    }
  };

  // ── 메시지 유형 관리 ──────────────────────────────────
  async function msTplLoad() {
    const box = document.getElementById('msTplManage');
    box.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--gray-500);">불러오는 중...</div>';
    try {
      const res = await fetch(TPL_URL, { headers: { 'Accept': 'application/json' } });
      const d   = await res.json();
      manageRows = d.templates ?? [];
      if (!manageRows.length) { box.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--gray-500);">등록된 유형이 없습니다.</div>'; return; }
      box.innerHTML = manageRows.map((t, i) => `
        <div class="ms-tpl" style="cursor:default;">
          <div style="min-width:0;flex:1;">
            <div class="ms-tpl-name">
              ${esc(t.label)}
              <span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;margin-left:6px;
                    background:var(--gray-100);color:var(--gray-600);">${t.channel === 'sms' ? '문자' : '알림톡'}</span>
              <span style="font-family:monospace;font-size:11px;color:var(--gray-500);margin-left:4px;">${esc(t.code)}</span>
              ${t.is_active ? '' : '<span style="font-size:10px;color:var(--alert-500);margin-left:6px;">사용 안 함</span>'}
            </div>
            <div class="ms-tpl-desc">${esc(t.description ?? '')}</div>
            ${t.body ? `<div class="ms-tpl-desc" style="white-space:pre-wrap;margin-top:4px;color:var(--gray-600);">${esc(String(t.body).slice(0, 120))}</div>` : ''}
          </div>
          <div class="ms-tpl-edit"><button type="button" class="ms-mini" onclick="msTplEdit(${i})">수정</button></div>
        </div>`).join('');
    } catch (e) {
      box.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--alert-500);">불러오지 못했습니다.</div>';
    }
  }

  let editingId = null;

  window.msTplNew = function () {
    editingId = null;
    document.getElementById('msTplTitle').textContent = '메시지 유형 추가';
    document.getElementById('msTplChannel').value = channel;
    ['msTplCode', 'msTplLabel', 'msTplDesc', 'msTplBody'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('msTplActive').checked = true;
    document.getElementById('msTplDelete').style.display = 'none';
    _msTplOpen();
  };

  window.msTplEdit = function (i) {
    const t = manageRows[i];
    if (!t) return;
    editingId = t.id;
    document.getElementById('msTplTitle').textContent = '메시지 유형 수정';
    document.getElementById('msTplChannel').value = t.channel;
    document.getElementById('msTplCode').value    = t.code;
    document.getElementById('msTplLabel').value   = t.label;
    document.getElementById('msTplDesc').value    = t.description ?? '';
    document.getElementById('msTplBody').value    = t.body ?? '';
    document.getElementById('msTplActive').checked = !!t.is_active;
    document.getElementById('msTplDelete').style.display = '';
    _msTplOpen();
  };

  function _msTplOpen() {
    document.getElementById('msTplResult').style.display = 'none';
    _msTplChannelNote();
    document.getElementById('msTplBackdrop').style.display = 'block';
    document.getElementById('msTplModal').style.display    = 'block';
    document.getElementById('msTplLabel').focus();
  }

  window.msTplClose = function () {
    document.getElementById('msTplBackdrop').style.display = 'none';
    document.getElementById('msTplModal').style.display    = 'none';
  };

  function _msTplChannelNote() {
    document.getElementById('msTplCodeNote').style.display =
      document.getElementById('msTplChannel').value === 'alimtalk' ? 'block' : 'none';
  }
  document.getElementById('msTplChannel').addEventListener('change', _msTplChannelNote);

  function _msTplSay(msg, ok) {
    const box = document.getElementById('msTplResult');
    box.style.display  = 'block';
    box.style.background = ok ? 'var(--primary-50)' : 'var(--danger-light)';
    box.style.color      = ok ? 'var(--primary)'    : 'var(--danger)';
    box.style.border     = '1px solid ' + (ok ? 'var(--primary-200)' : 'var(--alert-100)');
    box.textContent = msg;
  }

  window.msTplSave = async function () {
    const body = {
      channel:     document.getElementById('msTplChannel').value,
      code:        document.getElementById('msTplCode').value.trim(),
      label:       document.getElementById('msTplLabel').value.trim(),
      description: document.getElementById('msTplDesc').value.trim(),
      body:        document.getElementById('msTplBody').value,
      is_active:   document.getElementById('msTplActive').checked,
    };
    if (!body.code || !body.label) { _msTplSay('코드와 이름은 반드시 입력해야 합니다.', false); return; }

    const url    = editingId ? `{{ url('/messages/templates') }}/${editingId}` : `{{ route('messages.templates.store') }}`;
    const method = editingId ? 'PUT' : 'POST';
    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify(body),
      });
      const d = await res.json();
      if (d.success) {
        _msTplSay('저장했습니다. 화면을 다시 불러옵니다.', true);
        setTimeout(() => location.reload(), 900);
      } else {
        _msTplSay(d.message ?? (Object.values(d.errors ?? {})[0]?.[0]) ?? '저장 실패', false);
      }
    } catch (e) { _msTplSay('네트워크 오류가 발생했습니다.', false); }
  };

  window.msTplDelete = async function () {
    if (!editingId) return;
    const ok = await ceConfirm('이 메시지 유형을 지웁니다. 되돌릴 수 없습니다.',
      { title: '유형 삭제', confirmText: '삭제', tone: 'danger' });
    if (!ok) return;
    try {
      const res = await fetch(`{{ url('/messages/templates') }}/${editingId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      });
      const d = await res.json();
      if (d.success) { _msTplSay('지웠습니다.', true); setTimeout(() => location.reload(), 700); }
      else           { _msTplSay(d.message ?? '삭제 실패', false); }
    } catch (e) { _msTplSay('네트워크 오류가 발생했습니다.', false); }
  };

  msRenderTpl();
  msUpdateLen();
})();
</script>
@endpush
