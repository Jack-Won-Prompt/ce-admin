{{-- resources/views/fax/index.blade.php --}}
@extends('layouts.app')

@section('title', '공단 팩스 발송')
@section('page-title', '공단 팩스 발송')
{{-- 시안 324:10697 — 빵부스러기 구분자는 하이픈이다: '홈 - 팩스 발송'('홈' x367 · '-' x386 · '팩스 발송' x400) --}}
@section('breadcrumb', '홈 - 공단 팩스 발송')

@section('help-title', '공단 팩스 발송 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>팝빌 API를 통해 PDF·이미지 파일을 팩스로 전송하고, 전송 내역을 조회하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">지원 파일 형식</div>
  <div class="help-item"><div class="help-item-icon"><i class="bx bx-file"></i></div><div class="help-item-text">PDF, TIFF, JPG, PNG, GIF — 건당 최대 10MB</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">전송 상태</div>
  <div class="help-badge-row">
    <span class="badge badge-secondary">대기</span>
    <span class="badge badge-info">전송중</span>
    <span class="badge badge-success">성공</span>
    <span class="badge badge-danger">실패</span>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">유의사항</div>
  <div class="help-item"><div class="help-item-icon warn"><i class="bx bx-error"></i></div><div class="help-item-text">발신번호는 팝빌 포털에서 사전 등록·인증 후 사용 가능합니다.</div></div>
  <div class="help-item"><div class="help-item-icon warn"><i class="bx bx-error"></i></div><div class="help-item-text">테스트 환경에서는 실제 팩스가 발송되지 않습니다.</div></div>
</div>
@endsection

@push('styles')
<style>
  /* ── 레이아웃 ──
     .page-body 가 flex column · gap 12 라 최상위 블록 사이 여백은 gap 이 만든다.
     시안 324:10697 · 324:11379 는 요약 카드 · 검색 카드 · 결과바를 그리드 카드 '밖'에 두고
     두 탭에서 계속 보여 준다. 탭바만 카드 안 맨 위에서 판(전송 내역 / 팩스 발송)을 바꾼다. */
  .fax-pane { display:flex; flex-direction:column; gap:12px; }
  /* 전송 내역 판 — tiTab() 이 display 인라인 값을 지우면 이 flex 로 돌아온다 */
  .fax-grid-pane { display:flex; flex-direction:column; flex:1; min-height:0; }

  /* 탭바 — 시안 Frame 48101484 는 탭바를 흰 카드 안 맨 위에 둔다.
     1568×44 · pad 0/16 · 탭 65×44(pad 0/8) · 탭 사이 gap 8 · 하단 1px #E8EAEC ·
     활성 13/500 #28798B + 밑줄 1px #28798B, 비활성 13/500 #656C74.
     이 규격은 전역 .pnl-tabs / .pnl-tab 이 그대로 갖고 있어 마크업에 함께 붙였다.
     .titab-bar · .titab 이름은 tiTab() 이 잡는 앵커라 남긴다(선언은 전역에 맡긴다). */
  .titab-bar { flex-wrap:wrap; }

  /* 결과바 '선택 N건' 은 전역(layouts/app.blade.php 542줄)에 .ds-grid-sel 로 이미 있다.
     이 화면에는 규칙이 없다 — 예전 주석이 남아 있던 것을 걷어냈다. */

  /* 동기화 문구(#sync-status) — 시안 Frame 48101548: 알약 배지 239×23 · r999 · pad 2/6 ·
     gap 4 · bg #D3F1F7(primary-100), 앞 12×12 check 아이콘과 글자 모두 #28798B.
     전역 .ds-grid-hint 의 alert-circle ::before 를 check 로 갈아 끼운다.
     전역 .ds-grid-bar-right 는 flex-shrink:0 이고 .content-wrapper 는 overflow-x:clip 이라
     문구가 길어지면 '엑셀 저장' 버튼이 화면 밖으로 밀려 눌리지 않는다. 2열 폭(298px)에서
     말줄임하고 전문은 syncPending() 의 토스트가 보여 준다.
     비어 있을 때는 결과바 gap 만 남으므로 숨긴다. */
  #sync-status {
    /* check-contained — 채운 원에서 체크를 파낸 모양(fill-rule evenodd) */
    --icon-check-contained: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='black' fill-rule='evenodd' d='M12 3a9 9 0 100 18 9 9 0 000-18zM10.75 15.6L7.4 12.25l1.4-1.4 1.95 1.95 4.45-4.45 1.4 1.4z'/></svg>");
    max-width:298px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    height:23px; padding:2px 6px; border-radius:999px;
    background:var(--primary-100); color:var(--primary);
  }
  #sync-status::before {
    -webkit-mask:var(--icon-check-contained) center / contain no-repeat;
            mask:var(--icon-check-contained) center / contain no-repeat;
  }
  #sync-status:empty { display:none; }

  /* 상태 동기화 버튼 — 시안 84×32, 글자만 primary(테두리 없음은 전역 .ds-grid-bar .ds-btn 이 처리) */
  #sync-btn { color:var(--primary); }

  /* ── 요약 카드 ──
     시안 Frame 48101550 — 흰 카드 한 장 1568×75 · r12 · pad 12/0 · 가로.
     안에 3칸이 각 523×51(pad 4/12 · gap 2 · 주CENTER 교CENTER)로 균등하게 들어가고
     칸 사이에 0×51 세로 구분선 1px --gray-200 이 두 줄 있다.
     칸 높이 51 = pad 4 + 43 + pad 4, 43 = 라벨 19 + gap 2 + 값 22.
     taxinvoice/index.blade.php 의 .ti-summary 와 같은 컴포넌트라 값도 같이 맞춘다. */

  /* ── 발송 폼 판 ──
     시안 Frame 48101521 — 탭바와 같은 흰 카드 안, pad 16 · gap 24 세로.
     위는 504×301 카드 세 장(기본 정보 / 수신자 목록 / 첨부 파일)이 gap 12 로 가로 3열,
     아래는 1536×36 버튼 줄. 판 자체는 카드 안이라 배경·모서리를 또 갖지 않는다. */
  .send-card { display:flex; flex-direction:column; background:var(--gray-0); }
  /* 판 머리 '팩스 발송' 은 시안에 없지만 개발에서 넣은 줄이라 그대로 둔다 */
  .send-card-head {
    padding:12px 16px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:8px;
    font-size:14px; font-weight:700; line-height:22px; color:var(--text-primary);
  }
  .send-card-head i { font-size:16px; color:var(--primary); }
  .send-card-body { padding:16px; display:flex; flex-direction:column; gap:24px; }

  /* 3열 — 카드 504×301 · r12 · bd 1px #E8EAEC · pad 12/16 · gap 12 */
  .send-cols { display:flex; align-items:stretch; gap:12px; flex-wrap:wrap; }
  .send-col {
    flex:1 1 320px; min-width:0;
    display:flex; flex-direction:column; gap:12px;
    padding:12px 16px; border:1px solid var(--gray-200); border-radius:12px; background:var(--gray-0);
  }
  /* 수신자 목록 카드만 표가 카드 폭을 꽉 채운다 — pad 12/0, 머리줄만 좌우 16 */
  .send-col.tight { padding:12px 0; }
  .send-col.tight .send-col-head { padding:0 16px; }
  .send-col-head { display:flex; align-items:center; gap:8px; min-height:28px; flex-wrap:wrap; }
  .send-col-title { font-size:14px; font-weight:700; line-height:22px; color:var(--gray-1000); }
  /* 필수 별표 — 시안 TEXT 노드의 문자 스타일 override 가 '*' 한 글자만 #28798B 로 준다.
     ('발신 팩스번호 *' 13/500 #474D54 + ovr #28798B, 표 머리 '팩스번호 *' 13/700 #656C74 + ovr #28798B)
     라벨과 같은 색으로 두면 필수 표시가 글자에 묻힌다. 크기·굵기는 앞 글자를 따르므로 색만 준다. */
  .req-star { color:var(--primary); }
  /* '첨부 파일 *' 의 별표는 14/700 제목 옆에 홀로 서 있어 크기를 따로 준다 */
  .send-req { font-size:13px; font-weight:500; line-height:21px; color:var(--primary); }
  /* 머리 알약 배지 — r999 · pad 2/8 · bg #F3F5F7 · 11/500 · #333940 */
  .send-tag {
    display:inline-flex; align-items:center; height:22px; padding:2px 8px; border-radius:999px;
    background:var(--gray-100); font-size:11px; font-weight:500; line-height:18px;
    color:var(--gray-800); white-space:nowrap;
  }
  .send-col-fields { display:flex; flex-direction:column; gap:8px; }

  /* 행 472×32 — 라벨 100×32(13/500 · lh16 · #474D54) 왼쪽, 컨트롤 364×32 오른쪽, gap 8 */
  .form-row { display:flex; align-items:center; gap:8px; }
  /* gap 4 — flex 로 만들면 '발신 팩스번호' 와 '*' 사이의 띄어쓰기가 접혀
     '발신 팩스번호*' 로 붙는다. 시안 텍스트도 '발신 팩스번호 *' 로 한 칸 떨어져 있다. */
  .form-row > .ds-field-label {
    flex:0 0 100px; width:100px; height:32px;
    display:flex; align-items:center; gap:4px; line-height:16px;
  }
  .form-row > *:not(.ds-field-label) { flex:1 1 auto; min-width:0; }
  /* 예약 전송 토글은 라벨 자리를 대신 쓴다 — 라벨 칸(100)에 맞춰 고정한다 */
  .send-col .form-row > .reserve-toggle { flex:0 0 100px; width:100px; }
  /* 입력 — Figma: h32 · r8 · pad 5/12 · 13/400 · lh20 · bd 1px gray-200 (.form-control 과 같은 규격).
     addReceiver() 가 만드는 행이 .form-input 을 쓰므로 클래스명은 그대로 둔다. */
  .form-input {
    width:100%; height:32px; padding:5px 12px;
    border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px;
    color:var(--text-primary); background:var(--gray-0);
    font-family:inherit; transition:var(--transition);
  }
  .form-input::placeholder { color:var(--gray-500); }
  .form-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(40,121,139,.12); }
  .form-input.error { border-color:var(--danger); }
  select.form-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238B95A1' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; }

  /* 수신자 섹션 — 시안 Frame 48101520: 머리행 있는 2열 표 504×237 · bd 1px #E8EAEC.
     카드가 pad 12/0 이라 표가 카드 폭을 꽉 채운다 → 좌우 테두리는 카드 테두리와 겹치므로 뺀다.
     머리행 45 = pad 12 + 21 + pad 12 · bg #F9FAFC · 셀 13/700 #656C74.
     본문행 48 = pad 10 + 28 + pad 10 · 셀 폭 226/226/52 · 입력 202×28. */
  .receivers-box { border:1px solid var(--gray-200); border-left:0; border-right:0; border-radius:0; }
  .receiver-head {
    display:grid; grid-template-columns:minmax(0,1fr) 52px;
    background:var(--gray-50); border-bottom:1px solid var(--gray-200);
  }
  .receiver-head-cells { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
  /* 직계 자식만 — 안쪽 별표 <span class="req-star"> 까지 pad 12 를 먹으면 머리행이 벌어진다 */
  .receiver-head > span,
  .receiver-head-cells > span { padding:12px; font-size:13px; font-weight:700; line-height:21px; color:var(--gray-600); }
  .receiver-row {
    display:grid; grid-template-columns:minmax(0,1fr) 52px;
    gap:0; padding:0; border-bottom:1px solid var(--gray-100);
    align-items:center;
  }
  /* 세로 → 가로. addReceiver() 가 만드는 행에도 같은 클래스라 함께 먹는다 */
  .receiver-inputs { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:0; }
  .receiver-inputs .form-input {
    width:auto; height:28px; margin:10px 12px; padding:0 12px;
    font-size:12px; font-weight:400; line-height:19px;
  }
  .receiver-row:last-child { border-bottom:none; }
  /* 추가 버튼은 카드 머리줄 오른쪽 끝 — 79×28 · r8 · pad 0/12 · gap 6 · bd 1px #E8EAEC · 12/500 #101317 */
  .receiver-add-btn {
    margin-left:auto; flex-shrink:0;
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    height:28px; padding:0 12px; border-radius:8px;
    font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000);
    background:var(--gray-0); border:1px solid var(--gray-200); cursor:pointer;
    transition:var(--transition);
  }
  .receiver-add-btn:hover { background:var(--gray-50); }
  /* 삭제 버튼 — 28×28 · r8 · bg #FFFFFF · bd 1px #E8EAEC · 아이콘 12 · #101317 */
  .receiver-del-btn {
    width:28px; height:28px; margin:10px 12px; border-radius:8px;
    border:1px solid var(--gray-200);
    background:var(--gray-0); color:var(--gray-1000); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:12px;
    transition:var(--transition); flex-shrink:0;
  }
  .receiver-del-btn:hover { background:var(--gray-50); }

  /* 파일 드롭존 — 시안 Frame 48101694: 472 폭 안에 152×140 타일이 3열 · gap 8 로 깔리고
     첫 칸이 드롭존이다. 드롭존 타일 r8 · bd 1px 실선 #28798B · pad 0/12 · gap 8 ·
     가로세로 가운데 · plus 아이콘 24 #28798B · 글자 13/500 · lh18 · 가운데 · #28798B. */
  .attach-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; align-content:start; }
  /* height 가 아니라 min-height 다 — 타일 폭은 3열 1fr 이라 시안 152 보다 좁아질 수 있고
     (사이드바 320 이 있는 1600 화면에서 116), 그러면 안내 글자가 세 줄로 늘어 153 이 된다.
     height:140 으로 못 박으면 글자가 타일 테두리 위아래로 6.5px 씩 삐져나온다. */
  .drop-zone {
    min-height:140px; border:1px solid var(--primary); border-radius:8px;
    padding:0 12px; text-align:center; cursor:pointer;
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
    transition:var(--transition); background:var(--gray-0);
  }
  .drop-zone.drag-over { background:var(--primary-light); }
  /* 시안 아이콘은 plus-02 24×24 지만 개발이 넣은 업로드 아이콘을 지우지 않고 크기·색만 맞췄다 */
  .drop-zone .dz-icon { font-size:24px; line-height:24px; color:var(--primary); }
  .drop-zone .dz-text { font-size:13px; font-weight:500; line-height:18px; color:var(--primary); }
  .drop-zone .dz-sub  { font-size:12px; font-weight:400; line-height:19px; color:var(--gray-500); margin-top:0; }
  /* 첨부된 파일 목록은 타일 아래 한 줄을 통째로 쓴다.
     시안은 드롭존과 같은 152×140 미리보기 타일이지만, 미리보기 이미지를 만드는 코드가 없어
     (renderFileList() 가 FileReader·objectURL 을 쓰지 않는다) 목록 모양은 그대로 뒀다. */
  .file-list { grid-column:1/-1; display:flex; flex-direction:column; gap:6px; }
  .file-list:empty { display:none; }
  .file-item {
    display:flex; align-items:center; gap:8px;
    padding:5px 12px; background:var(--gray-0); border:1px solid var(--gray-200);
    border-radius:8px; font-size:13px; font-weight:400; line-height:20px;
  }
  .file-item i { color:var(--primary); font-size:16px; }
  .file-item .fi-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .file-item .fi-size { color:var(--gray-500); white-space:nowrap; }
  .file-item .fi-del  { color:var(--danger); cursor:pointer; margin-left:4px; font-size:16px; }

  /* 예약 전송 토글 */
  .reserve-toggle { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); cursor:pointer; }
  .reserve-toggle input[type=checkbox] { width:16px; height:16px; accent-color:var(--primary); cursor:pointer; }

  /* 전송 버튼 — 시안 하단 버튼 줄 1536×36: '팩스 전송' 이 남는 폭 전부를 쓴다.
     36 · r8 · pad 0/16 · bg #28798B · 14/500 · lh22 · #FFFFFF */
  .send-btn {
    height:36px; padding:0 16px;
    background:var(--primary); color:var(--gray-0);
    border:1px solid var(--primary); border-radius:8px;
    font-size:14px; font-weight:500; line-height:22px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:var(--transition);
  }
  .send-btn:hover:not(:disabled) { background:var(--primary-dark); border-color:var(--primary-dark); }
  .send-btn:disabled { opacity:.6; cursor:not-allowed; }

  /* ── 전송 내역 ──
     껍데기(.hist-card/.hist-head/.hist-filter/.btn-search)는 표준 컴포넌트로 갈아탔다.
     검색줄 → .ds-filter-card · 결과바 → .ds-grid-bar · 표 → .ds-grid-card.
     '상태 동기화' 는 .ds-btn 위에 회전 애니메이션만 얹는다(syncPending() 이 .syncing 을 토글한다). */
  .btn-sync:disabled { opacity:.5; cursor:not-allowed; }
  .btn-sync.syncing i { animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }

  /* 아래 표 스타일은 지금 붙는 마크업이 없다(목록은 wwGrid 가 그린다).
     개발 자산이라 지우지 않고 값만 시안 규격으로 맞춰 둔다. */
  .hist-table { width:100%; border-collapse:collapse; font-size:13px; }
  .hist-table th {
    padding:10px 14px; background:var(--gray-50); font-size:13px; font-weight:700; line-height:21px;
    color:var(--gray-600); text-align:left; border-bottom:1px solid var(--border); white-space:nowrap;
  }
  .hist-table td {
    padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle;
    font-size:13px; font-weight:400; line-height:21px;
  }
  .hist-table tr:last-child td { border-bottom:none; }
  .hist-table tr:hover td { background:var(--gray-50); }
  .hist-table .mono { font-family:monospace; font-size:11px; }
  .btn-detail {
    height:28px; padding:3px 10px; font-size:13px; font-weight:500; line-height:20px;
    background:var(--primary-light); color:var(--primary); border:none;
    border-radius:8px; cursor:pointer; white-space:nowrap;
  }
  .btn-detail:hover { background:var(--primary-100); }
  .hist-empty { padding:40px; text-align:center; color:var(--gray-500); font-size:13px; }

  /* 상태 배지 — Figma: r6 · pad 2/6 · 11px/500 · lh18 */
  .fax-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; white-space:nowrap; }
  .fax-badge.wait   { background:var(--gray-100); color:var(--gray-600); }
  .fax-badge.send   { background:var(--primary-light); color:var(--primary); }
  .fax-badge.ok     { background:var(--primary-light); color:var(--primary); }
  .fax-badge.fail   { background:var(--danger-light); color:var(--danger); }
  .fax-badge.cancel { background:var(--gray-200); color:var(--gray-700); }

  /* 상세 모달 */
  .nd-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
  .nd-modal-overlay.open { display:flex; }
  /* 시안 Frame 48101489 — 960 고정 · r12 · bd 1px #E8EAEC · 그림자 0 4px 24px rgba(153,158,164,.24).
     머리 960×54 = pad 16/24 + 제목 22, 제목 14/700 lh22 #101317, 닫기 16×16 #101317. */
  .nd-modal { background:var(--gray-0); border-radius:12px; border:1px solid var(--gray-200); box-shadow:0 4px 24px rgba(153,158,164,.24); width:960px; max-width:92vw; max-height:85vh; display:flex; flex-direction:column; }
  .nd-modal-head { padding:16px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; flex-shrink:0; }
  .nd-modal-head h3 { flex:1; min-width:0; font-size:14px; font-weight:700; line-height:22px; margin:0; color:var(--gray-1000); }
  .nd-modal-close { display:flex; align-items:center; justify-content:center; width:24px; height:24px; flex-shrink:0; padding:0; border:none; border-radius:6px; background:none; font-size:16px; line-height:1; color:var(--gray-500); cursor:pointer; }
  /* 시안 165:1320 — 모달 본문 pad 24 */
  .nd-modal-body { padding:24px; overflow-y:auto; flex:1; }

  /* 아래는 openDetail() 이 만드는 마크업이 쓰는 클래스라 이름을 그대로 둔다. */
  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .detail-item .di-label { font-size:11px; font-weight:700; line-height:18px; color:var(--gray-600); margin-bottom:4px; }
  .detail-item .di-val   { font-size:13px; font-weight:500; line-height:21px; }
  .detail-item.full { grid-column:1/-1; }
  .rcv-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:8px; }
  .rcv-table th { padding:6px 10px; background:var(--gray-50); font-size:13px; font-weight:700; line-height:21px; color:var(--gray-600); border-bottom:1px solid var(--border); }
  .rcv-table td { padding:6px 10px; border-bottom:1px solid var(--border); font-size:13px; font-weight:400; line-height:21px; }
  .rcv-table tr:last-child td { border-bottom:none; }

  /* 상세 모달 - 정보 영역 */
  .fax-info-area {
    background:var(--gray-50); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;
  }
  .fax-info-area > div { display:flex; align-items:center; gap:8px; }
  .fax-info-area p { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:0; font-size:13px; font-weight:400; line-height:21px; }
  .fax-api-badge {
    display:inline-flex; align-items:center; padding:2px 6px;
    background:var(--primary); color:var(--gray-0); border-radius:6px;
    font-size:11px; font-weight:700; line-height:18px; flex-shrink:0;
  }
  .fax-info-title { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-right:4px; }
  .fax-stat-blue { color:var(--primary); font-weight:700; }
  .fax-stat-red  { color:var(--danger);  font-weight:700; }

  /* 상세 모달 - 일반 테이블 */
  .fax-normal-table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:12px; border:1px solid var(--border); }
  .fax-normal-table th {
    background:var(--gray-50); padding:0 14px; font-size:13px; font-weight:700; line-height:21px;
    color:var(--gray-600); text-align:center; border:1px solid var(--border); white-space:nowrap;
  }
  .fax-normal-table td { padding:0 14px; border:1px solid var(--border); font-size:13px; font-weight:400; line-height:21px; color:var(--text-primary); }
  .fax-conv-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .fax-dl-btn {
    height:20px; line-height:20px; padding:0 4px; background:none; border:none;
    color:var(--primary); font-size:11px; font-weight:500; cursor:pointer; text-decoration:underline;
    display:inline-flex; align-items:center; gap:4px;
  }
  /* 작은 버튼 규격 — h28 · r8 · pad 3/10 · 13/500 */
  .fax-preview-btn {
    display:inline-flex; align-items:center; height:28px; padding:3px 10px;
    background:var(--primary-light); color:var(--primary);
    border:1px solid var(--primary-200); border-radius:8px;
    font-size:13px; font-weight:500; line-height:20px; cursor:pointer;
  }
  .fax-preview-btn:hover { background:var(--primary-100); }

  /* 페이지네이션 — 그리드 카드 하단 줄(1568×52 · pad 12 · 상단 1px #E8EAEC).
     버튼 28×28 · r6 · bd 1px #E8EAEC · bg #FFFFFF · 13/500 #101317 · 버튼 사이 gap 6.
     현재 쪽은 bg #E9F9FB · 글자 primary 이고 테두리는 그대로 #E8EAEC 다.
     왼쪽 '총 N건'(#pager-info)은 시안에 없지만 화면에 있던 글자라 그대로 둔다.
     taxinvoice/index.blade.php 도 같은 값을 들고 있다 — 전역 한 벌이 필요한 자리. */
  /* 건수와 쪽 단추를 **한 줄**에 둔다 (2026-10-03 지시 「공단 팩스 전송 그리드 전체
     건수와 페이징이 분리되어 있음」).

     여태 표 아랫줄이 「전체 N건」을 따로 적고, 그 아래에 쪽 줄이 또 섰다. 같은 말이
     두 줄로 선 셈이다. 표의 아랫줄을 끄고(footer:false) 이 줄 하나가 건수와 쪽
     번호를 함께 진다 — 다른 목록 화면(ce-pager)과 같은 모양이다.

     세 칸으로 나눈다. 양 끝이 같은 몫(flex:1)을 가지므로 단추 묶음이 **판 한가운데**
     선다 — 왼쪽 글자가 길어져도 가운데가 밀리지 않는다. */
  .hist-pager { padding:12px; border-top:1px solid var(--gray-200); display:flex; align-items:center; justify-content:center; gap:8px; flex-shrink:0; }
  .pager-info, .pager-spacer { flex:1 1 0; min-width:0; }
  .pager-info { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .pager-btns { display:flex; gap:6px; flex:none; }
  .pager-btn {
    height:28px; min-width:28px; padding:0 6px; border:1px solid var(--gray-200); border-radius:6px;
    background:var(--gray-0); font-size:13px; font-weight:500; line-height:21px; cursor:pointer; color:var(--gray-1000);
    transition:var(--transition);
  }
  .pager-btn:hover { border-color:var(--primary); color:var(--primary); }
  .pager-btn.active { background:var(--primary-50); color:var(--primary); border-color:var(--gray-200); }
  .pager-btn:disabled { opacity:.4; cursor:not-allowed; }
</style>
@endpush

@section('content')

{{-- 요약 3칸(잔여 포인트·오늘 발송·이번 달 발송)은 두지 않는다. 화면을 열 때마다
     팝빌을 세 번 더 부르면서도 그 숫자로 하는 일이 없었다 — 보낸 것은 아래 내역에서 본다.
     포인트가 필요하면 팝빌에서 본다. --}}

{{-- ── 검색 카드와 결과바는 그리드 카드 바깥·탭바 위다.
     시안 324:10697(전송 내역)·324:11379(팩스 발송) 둘 다 이 두 줄을 그대로 두고
     탭바만 카드 안에서 판을 바꾼다. ── --}}
<div class="fax-pane">

  {{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16), 라벨 위 · 컨트롤 아래 --}}
  <div class="ds-filter-card">
    <div class="ds-filter-fields">
      <div class="ds-filter-field span-2">
        <label class="ds-field-label">전송 기간</label>
        <div class="ds-field-range">
          <input type="date" id="f-start" class="form-control" value="{{ date('Ymd', strtotime('-30 days')) }}">
          <span class="ds-field-sep">~</span>
          <input type="date" id="f-end"   class="form-control" value="{{ date('Ymd') }}">
        </div>
      </div>
    </div>
    <div class="ds-filter-actions">
      {{-- 동기화 진행 문구 — 결과바에 있던 알약을 단추 옆으로 옛겼다(비어 있으면 안 보인다) --}}
      <span class="ds-grid-hint" id="sync-status"></span>
      <button type="button" class="ds-btn ds-btn-primary" onclick="loadHistory(1)">검색</button>
      {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
      <button type="button" class="ds-btn" onclick="window.__faxGrid?.downloadExcel()">엑셀 다운</button>
      <button type="button" class="ds-btn" onclick="faxRowAction('detail')"><i class="bx bx-show"></i> 선택 상세</button>
      <button type="button" class="ds-btn btn-sync" id="sync-btn" onclick="syncPending()" title="미완료 건 팝빌 상태 동기화">
        <i class="bx bx-refresh"></i> 상태 동기화
      </button>
    </div>
  </div>

  {{-- 결과바는 걷어냈다(499d611) — 건수는 탭 이름에, 단추는 찾는 줄에 있다 --}}

</div>

{{-- ── 탭바와 두 탭 본문은 같은 흰 카드 안이다 (시안 Frame 48101484) ── --}}
{{-- 카드를 .ds-grid-section 안에 둔다. 밖에 두면 전역 .ds-grid-card 의 flex:1 이 살아
     카드가 본문 남은 높이를 다 먹었다 — 표는 266 인데 카드는 1003 이라 아래로 흰 바닥이
     693 남았다(.ds-grid-section > .ds-grid-card 만 flex:0 1 auto 로 눌러 준다).
     다른 스물한 화면이 모두 이 구조다. --}}
<div class="ds-grid-section">
<div class="ds-grid-card">

  {{-- 탭: 공단 전송 내역 / 공단 팩스 발송 (내역 먼저) --}}
  <div class="pnl-tabs titab-bar">
    <button type="button" class="pnl-tab titab active" data-tab="hist" onclick="tiTab('hist')"><i class="fa-solid fa-list"></i> 공단 전송 내역<span class="pnl-tab-cnt">(총 <b id="fax-total-count">0</b>건)</span></button>
    <button type="button" class="pnl-tab titab" data-tab="issue" onclick="tiTab('issue')"><i class="bx bx-printer"></i> 공단 팩스 발송</button>
  </div>

  {{-- ── 전송 내역 그리드 — 탭바와 같은 카드 안이다 ── --}}
  <div class="fax-grid-pane" data-titab="hist">
    <div id="faxHistGrid"></div>

    {{-- 건수와 쪽 단추가 한 줄이다. 오른쪽 빈 칸은 단추를 가운데에 세우는 몫이다 --}}
    <div class="hist-pager" id="hist-pager" style="display:none;">
      <div class="pager-info" id="pager-info"></div>
      <div class="pager-btns" id="pager-btns"></div>
      <div class="pager-spacer"></div>
    </div>
  </div>

  {{-- ── 팩스 발송 폼 ──
       처음에는 접어 둔다(.hist-pager 와 같은 방식). tiTab('hist') 가 아래 스크립트에서
       이 판을 감추는데, 그리드는 그보다 먼저 만들어진다. 그때 이 판이 펼쳐져 있으면
       wwGrid 의 height:'fit' 이 '페이지가 넘친다'고 보고 표 높이를 그 판 높이만큼
       깎아 버려(1600×1000 에서 640→473, 1280×720 에서 360→140) 카드 아래가 크게 빈다. --}}
  <div class="send-card" data-titab="issue" style="display:none;">
    <div class="send-card-head">
      <i class="bx bx-printer"></i>
      <span>팩스 발송</span>
    </div>
    <div class="send-card-body">

      <div class="send-cols">

        {{-- 기본 정보 --}}
        <div class="send-col">
          <div class="send-col-head"><span class="send-col-title">기본 정보</span></div>
          <div class="send-col-fields">

            {{-- 사업자번호 --}}
            <div class="form-row">
              <label class="ds-field-label">사업자번호</label>
              <input id="corp-num" class="form-input" type="text" value="{{ $corpNum }}" placeholder="1234567890">
            </div>

            {{-- 발신번호 --}}
            <div class="form-row">
              <label class="ds-field-label">발신 팩스번호 <span class="req-star">*</span></label>
              <div class="ds-field-range">
                <select id="sender-select" class="form-input" style="flex:1;">
                  <option value="">— 발신번호 선택 —</option>
                </select>
                <button type="button" onclick="loadSenderNumbers()" class="ds-btn" style="white-space:nowrap;">
                  <i class="bx bx-refresh"></i> 새로고침
                </button>
              </div>
            </div>

            {{-- 발신자명 --}}
            <div class="form-row">
              <label class="ds-field-label">발신자명</label>
              <input id="sender-name" class="form-input" type="text" placeholder="(선택) 발신자명">
            </div>

            {{-- 제목 — 시안에는 없지만 sendFax() 가 title 로 실제 전송한다. 자리만 옮겼다 --}}
            <div class="form-row">
              <label class="ds-field-label">팩스 제목</label>
              <input id="fax-title" class="form-input" type="text" placeholder="(선택) 팩스 제목">
            </div>

            {{-- 예약 전송 — 시안에는 없지만 sendFax() 가 reserve_dt 로 실제 전송한다 --}}
            <div class="form-row">
              <label class="reserve-toggle">
                <input type="checkbox" id="reserve-chk" onchange="toggleReserve()">
                예약 전송
              </label>
              <input id="reserve-dt" class="form-input" type="datetime-local" style="display:none;">
            </div>

          </div>
        </div>

        {{-- 수신자 목록 --}}
        <div class="send-col tight">
          <div class="send-col-head">
            <span class="send-col-title">수신자 목록</span>
            <button type="button" class="receiver-add-btn" onclick="addReceiver()">
              <i class="bx bx-plus"></i> 수신자 추가
            </button>
          </div>
          <div class="receivers-box" id="receivers-box">
            <div class="receiver-head">
              <div class="receiver-head-cells"><span>팩스번호 <span class="req-star">*</span></span><span>수신자명</span></div>
              <span></span>
            </div>
            <div class="receiver-row" data-idx="0">
              <div class="receiver-inputs">
                <input class="form-input rcv-num"  type="text" placeholder="팩스번호 (숫자만)" value="{{ $receiverFax }}" data-phone>
                <input class="form-input rcv-name" type="text" placeholder="수신자명 (선택)">
              </div>
              <button type="button" class="receiver-del-btn" onclick="removeReceiver(this)" style="visibility:hidden;">
                <i class="bx bx-x"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- 첨부 파일 --}}
        <div class="send-col">
          <div class="send-col-head">
            <span class="send-col-title">첨부 파일</span>
            <span class="send-req">*</span>
            <span class="send-tag">(PDF·TIFF·JPG·PNG·GIF, 최대 10MB)</span>
          </div>
          <div class="attach-grid">
            <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
              <input type="file" id="file-input" accept=".pdf,.tif,.tiff,.jpg,.jpeg,.gif,.png" multiple style="display:none">
              <div class="dz-icon"><i class="bx bx-cloud-upload"></i></div>
              <div class="dz-text">클릭하거나 파일을 드래그하십시오</div>
              <div class="dz-sub">PDF, TIFF, JPG, PNG, GIF</div>
            </div>
            <div class="file-list" id="file-list"></div>
          </div>
        </div>

      </div>

      {{-- 전송 버튼 — 시안 하단 줄. '초기화' 는 폼을 비우는 함수가 없어 만들지 않았다 --}}
      <button class="send-btn" id="send-btn" onclick="sendFax()">
        <i class="bx bx-send"></i> 팩스 전송
      </button>

    </div>
  </div>

</div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}

{{-- ── 상세 모달 ── --}}
<div class="nd-modal-overlay" id="detail-modal">
  <div class="nd-modal">
    <div class="nd-modal-head">
      <i class="bx bx-printer" style="color:var(--primary);font-size:16px;"></i>
      <h3>팩스 전송 상세</h3>
      <button class="nd-modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="nd-modal-body" id="detail-body">
      <div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// 전송 내역 wwGrid (조회 결과를 setData로 주입)
(function () {
  const el = document.getElementById('faxHistGrid');
  if (!el) return;
  window.__faxGrid = new wwGrid({
    el: el,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 그대로).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    // 시안은 표가 카드 남은 높이를 채운다(1568×858 = 탭 44 + 표 762 + 페이저 52).
    // 460 고정이면 카드 아래가 빈다 — 기준 구현 patients/index.blade.php 와 같이 'fit' 을 쓴다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    /* 표의 아랫줄은 끈다 (2026-10-03 지시).

       이 화면은 서버가 한 쪽씩(15줄) 내려 준다. 그래서 표가 세는 「전체 N건」은 늘
       **보고 있는 쪽의 줄 수**였다 — 1,200건짜리 조회에도 「전체 15건」이라 적혔다.
       참된 건수는 서버가 함께 주는 `total` 이고, 그것은 아래 쪽 줄이 적는다.

       같은 말이 두 줄로 서던 것도 이것으로 없어진다 — 건수와 쪽 번호가 한 줄이다. */
    footer: false,
    columns: [
      { header: '전송일시', name: 'sentAt',     width: 150, sortable: true },
      { header: '발신번호', name: 'sendNum',    width: 120 },
      { header: '수신번호', name: 'receiveNum', width: 120 },
      { header: '제목',     name: 'title',      width: 220 },
      { header: '상태',     name: 'status',     width: 90, align: 'center', sortable: true },
    ],
    data: [],
  });
  // 하단 상태바에 있던 '선택 N건' 을 결과바로 잇는다(전역 헬퍼).
  window.dsBindSelCount(window.__faxGrid, 'fax-sel-count');
  /* 탭 이름 옆의 「(총 N건)」은 **서버가 준 전체 건수**다 (2026-10-03 고침).

     여태 표가 들고 있는 줄 수(`getData().length`)를 적었다. 이 화면은 서버가 한
     쪽씩 15줄만 내려 주므로, 1,200건짜리 조회에도 「총 15건」이라 적혔다.
     참된 건수는 조회가 돌아올 때 `renderPager` 가 함께 적는다. */
  function faxOpenRow(r) {
    if (!r || !r.receiptNum) return;
    openDetail(r.receiptNum);
  }
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]'); if (!cell) return;
    const r = window.__faxGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (r) faxOpenRow(r);
  });
  window.faxRowAction = function (action) {
    const c = window.__faxGrid.getCheckedRows();
    if (!c.length)    { showToast('행을 먼저 선택해 주십시오.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택해 주십시오.', 'warning'); return; }
    const r = c[0];
    if (action === 'detail') { faxOpenRow(r); return; }
  };
})();
</script>
<script>
// 탭 전환(전송 내역 / 팩스 발송) — 기본은 전송 내역
function tiTab(name) {
  document.querySelectorAll('[data-titab]').forEach(el => { el.style.display = (el.dataset.titab === name) ? '' : 'none'; });
  document.querySelectorAll('.titab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
}
tiTab('hist');
</script>
<script>
const CORP_NUM   = document.getElementById('corp-num');
const FAX_BASE   = BASE_URL + '/api/popbill/fax';
const SMS_BASE   = BASE_URL + '/api/popbill/message';
const HEADERS    = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' };

let selectedFiles = [];
let histPage = 1;
let histMeta = {};

/* 화면에 세우는 바닥 날짜 — 서버와 같은 값을 본다(FaxHistory::보이는바닥).
   여기에 글자를 또 적어 두면 한쪽만 고쳐지는 날이 온다. */
const FAX_FLOOR = @json(\App\Models\FaxHistory::보이는바닥);

/* ── 초기화 ── */
document.addEventListener('DOMContentLoaded', () => {
  // 날짜 input을 먼저 초기화한 후 조회 실행
  const today = new Date();
  const ago30 = new Date(today);
  ago30.setDate(today.getDate() - 30);

  /* 조회 시작일은 **바닥보다 앞서지 않는다** (2026-10-03 지시).

     2026-10-01 이전 건은 화면에 세우지 않는다(FAX_FLOOR). 한 달 전으로 열어 두면
     9월이 걸리는데 그 자리는 서버가 걸러 버리므로, 담당자는 「분명 보낸 건이 있는데
     목록이 비었다」로 읽는다. 처음부터 바닥부터 보여 준다.

     달력 자체도 그 앞을 고를 수 없게 막는다(min) — 고를 수 있는데 결과가 없으면
     화면이 고장 난 것으로 보인다. */
  const 시작 = fmtDate(ago30) < FAX_FLOOR ? FAX_FLOOR : fmtDate(ago30);

  const 시작칸 = document.getElementById('f-start');
  시작칸.value = 시작;
  시작칸.min   = FAX_FLOOR;
  document.getElementById('f-end').min = FAX_FLOOR;

  document.getElementById('f-end').value   = fmtDate(today);

  loadSenderNumbers();
  initDropZone();
  loadHistory(1);
});

function fmtDate(d) {
  return d.getFullYear() + '-' +
    String(d.getMonth()+1).padStart(2,'0') + '-' +
    String(d.getDate()).padStart(2,'0');
}
function toApiDate(v) { return v.replace(/-/g,''); }

/* ── 발신번호 로드 ── */
async function loadSenderNumbers() {
  const sel = document.getElementById('sender-select');
  sel.innerHTML = '<option value="">불러오는 중…</option>';
  try {
    const res  = await fetch(`${FAX_BASE}/sender-numbers?corp_num=${CORP_NUM.value}`, { headers: HEADERS });
    const list = await res.json();
    if (!Array.isArray(list) || list.length === 0) {
      sel.innerHTML = '<option value="">등록된 발신번호 없음</option>';
      return;
    }
    sel.innerHTML = '<option value="">— 발신번호 선택 —</option>' +
      list.map(n => `<option value="${n.number}">${n.number}${n.state == 1 ? '' : ' (미인증)'}</option>`).join('');
  } catch {
    sel.innerHTML = '<option value="">로드 실패</option>';
  }
}

/* ── 수신자 관리 ── */
let rcvIdx = 0;
function addReceiver() {
  rcvIdx++;
  const box = document.getElementById('receivers-box');
  const row = document.createElement('div');
  row.className = 'receiver-row';
  row.dataset.idx = rcvIdx;
  row.innerHTML = `
    <div class="receiver-inputs">
      <input class="form-input rcv-num"  type="text" placeholder="팩스번호 (숫자만)" data-phone>
      <input class="form-input rcv-name" type="text" placeholder="수신자명 (선택)">
    </div>
    <button type="button" class="receiver-del-btn" onclick="removeReceiver(this)">
      <i class="bx bx-x"></i>
    </button>`;
  box.appendChild(row);
  updateDelBtns();
}

function removeReceiver(btn) {
  btn.closest('.receiver-row').remove();
  updateDelBtns();
}

function updateDelBtns() {
  const rows = document.querySelectorAll('#receivers-box .receiver-row');
  rows.forEach(r => {
    const btn = r.querySelector('.receiver-del-btn');
    btn.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
  });
}

/* ── 드롭존 ── */
function initDropZone() {
  const zone  = document.getElementById('drop-zone');
  const input = document.getElementById('file-input');

  input.addEventListener('change', () => addFiles(input.files));
  zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop',      e => {
    e.preventDefault(); zone.classList.remove('drag-over');
    addFiles(e.dataTransfer.files);
  });
}

function addFiles(fileList) {
  const allowed = ['application/pdf','image/tiff','image/jpeg','image/gif','image/png'];
  Array.from(fileList).forEach(f => {
    if (f.size > 10 * 1024 * 1024) { showToast(`${f.name}: 10MB 초과`, 'danger'); return; }
    if (selectedFiles.some(s => s.name === f.name && s.size === f.size)) return;
    selectedFiles.push(f);
  });
  renderFileList();
}

function renderFileList() {
  const list = document.getElementById('file-list');
  list.innerHTML = selectedFiles.map((f, i) => `
    <div class="file-item">
      <i class="bx bx-file-blank"></i>
      <span class="fi-name">${f.name}</span>
      <span class="fi-size">${(f.size/1024).toFixed(0)} KB</span>
      <i class="bx bx-x fi-del" onclick="removeFile(${i})"></i>
    </div>`).join('');
}

function removeFile(idx) {
  selectedFiles.splice(idx, 1);
  renderFileList();
}

/* ── 예약 전송 토글 ── */
function toggleReserve() {
  const chk = document.getElementById('reserve-chk');
  document.getElementById('reserve-dt').style.display = chk.checked ? 'block' : 'none';
}

/* ── 팩스 전송 ── */
async function sendFax() {
  const corpNum    = CORP_NUM.value.trim();
  const sender     = document.getElementById('sender-select').value;
  const senderName = document.getElementById('sender-name').value.trim();
  const title      = document.getElementById('fax-title').value.trim();

  // 유효성 검사
  if (!sender) { showToast('발신번호를 선택해 주십시오.', 'danger'); return; }
  if (selectedFiles.length === 0) { showToast('전송할 파일을 첨부해 주십시오.', 'danger'); return; }

  const receivers = [];
  document.querySelectorAll('#receivers-box .receiver-row').forEach(row => {
    const num  = row.querySelector('.rcv-num').value.trim().replace(/\D/g,'');
    const name = row.querySelector('.rcv-name').value.trim();
    if (num) receivers.push({ rcv: num, rcvnm: name });
  });
  if (receivers.length === 0) { showToast('수신 팩스번호를 입력해 주십시오.', 'danger'); return; }

  const fd = new FormData();
  fd.append('corp_num', corpNum);
  fd.append('sender',   sender);
  if (senderName) fd.append('sender_name', senderName);
  if (title)      fd.append('title',       title);
  receivers.forEach((r, i) => {
    fd.append(`receivers[${i}][rcv]`,   r.rcv);
    fd.append(`receivers[${i}][rcvnm]`, r.rcvnm);
  });
  selectedFiles.forEach(f => fd.append('files[]', f));

  const reserveChk = document.getElementById('reserve-chk').checked;
  if (reserveChk) {
    const dt = document.getElementById('reserve-dt').value;
    if (!dt) { showToast('예약 일시를 입력해 주십시오.', 'danger'); return; }
    fd.append('reserve_dt', dt.replace(/[-T:]/g,'').slice(0,14));
  }

  const btn = document.getElementById('send-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 전송 중…';

  try {
    const res  = await fetch(`${FAX_BASE}/send`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '전송 실패');

    showToast(`팩스 전송을 완료했습니다. 접수번호: ${data.receipt_num}`, 'success', 6000);
    selectedFiles = [];
    renderFileList();
    loadHistory(1);
        // 30초 후 자동 동기화 (전송 결과 반영)
    setTimeout(() => syncPending(), 30000);
  } catch(e) {
    showToast('전송하지 못했습니다: ' + e.message, 'danger', 6000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-send"></i> 팩스 전송';
  }
}

/* ── 전송 내역 조회 (DB 기반, 조회 시 팝빌 자동 동기화) ── */
async function loadHistory(page = 1) {
  histPage = page;
  const corpNum   = CORP_NUM.value.trim();
  const startDate = toApiDate(document.getElementById('f-start').value);
  const endDate   = toApiDate(document.getElementById('f-end').value);

  window.__faxGrid && window.__faxGrid.setData([]);

  // 팝빌에서 해당 기간 내역 먼저 동기화 (신규 저장 + 상태 변경 반영)
  try {
    await fetch(`${FAX_BASE}/sync-from-popbill`, {
      method: 'POST',
      headers: { ...HEADERS, 'Content-Type': 'application/json' },
      body: JSON.stringify({ corp_num: corpNum, start_date: startDate, end_date: endDate }),
    });
  } catch(_) { /* 동기화 실패해도 DB 조회는 계속 */ }

  try {
    const url = `${FAX_BASE}/history?corp_num=${corpNum}&start_date=${startDate}&end_date=${endDate}&page=${page}&per_page=15`;
    const res  = await fetch(url, { headers: HEADERS });
    const data = await res.json();

    if (!res.ok) throw new Error(data.message || '조회 실패');

    histMeta = { total: data.total ?? 0, page, perPage: 15 };
    const list = data.list ?? [];

    if (list.length === 0) {
      window.__faxGrid.setData([]);
      /* 쪽 줄도 건수도 함께 0 으로 — 탭 이름에 지난 조회의 건수가 남지 않게 한다 */
      renderPager(0, 1, 15);
      return;
    }

    const rows = list.map(row => {
      const s          = String(row.state ?? 0);
      /* 팝빌 상태 그대로다 — 0 접수 · 1 변환중 · 2 전송중 · 3 완료 · 4 취소.
         성공ㆍ실패는 상태가 아니라 결과코드(result)가 정한다: 100 이 성공이다.
         여태 2 를 「성공」ㆍ3 을 「실패」로 적어, 정상 전송된 팩스가 실패로 보였다
         (2026-09-30 실전 시험에서 바로잡음). */
      /* 실패면 **결과코드를 함께 적는다** (2026-10-03 지시).
         여태 「전송 실패」 넉 자뿐이라, 왜 못 갔는지 알려면 건마다 상세를 열어야 했다. */
      const statusTxt  = String(row.state) === '3'
        ? (Number(row.result) === 100 ? '전송 성공' : '전송 실패 (' + (row.result ?? '-') + ')')
        : ({ '0':'접수','1':'변환 중','2':'전송 중','4':'취소' }[s] ?? '알 수 없음');
      const sentAt     = row.sendDT ? row.sendDT.replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,'$1-$2-$3 $4:$5') : '—';
      return {
        sentAt,
        sendNum:    row.sendNum ?? '—',
        receiveNum: row.receiveNum ?? '—',
        title:      row.title || '—',
        status:     statusTxt,
        receiptNum: row.receiptNum,
      };
    });
    window.__faxGrid.setData(rows);

    renderPager(data.total ?? 0, page, 15);
  } catch(e) {
    window.__faxGrid && window.__faxGrid.setData([]);
    showToast('조회하지 못했습니다: ' + e.message, 'error');
  }
}

/* ── 미완료 건 팝빌 동기화 ── */
async function syncPending() {
  const btn  = document.getElementById('sync-btn');
  const info = document.getElementById('sync-status');
  btn.disabled = true;
  btn.classList.add('syncing');
  info.textContent = '동기화 중…';

  try {
    const res  = await fetch(`${FAX_BASE}/sync-pending`, {
      method: 'POST',
      headers: { ...HEADERS, 'Content-Type': 'application/json' },
      body: JSON.stringify({ corp_num: CORP_NUM.value.trim() }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '동기화 실패');

    /* 서버가 주는 이름은 `checked`ㆍ`synced`ㆍ`errors` 다 (2026-10-03 고침).

       여태 없는 이름(`total`)을 읽어 화면에 「총 undefined건 중 0건 갱신」이라
       떴다. 숫자가 비면 담당자는 동기화가 깨진 것으로 읽는다.

       말도 함께 고친다. 이 단추가 묻는 것은 **아직 끝나지 않은 건**이지 전체가
       아니다(`FaxSyncService::syncPending` 이 `pending()` 만 모은다). 「총 N건」이라
       적으면 전체 이력을 다 물어본 것으로 읽힌다. */
    const 확인 = Number(data.checked ?? 0);
    const 갱신 = Number(data.synced ?? 0);

    const msg = 확인 === 0
      ? '동기화 완료: 상태를 확인할 미완료 건이 없습니다'
      : `동기화 완료: 미완료 ${확인}건 확인 · ${갱신}건 갱신`
        + (data.errors > 0 ? ` · 오류 ${data.errors}건` : '');
    info.textContent = msg;
    showToast(msg, data.errors > 0 ? 'warning' : 'success');
    loadHistory(histPage);
  } catch(e) {
    info.textContent = '동기화 실패';
    showToast('동기화하지 못했습니다: ' + e.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.classList.remove('syncing');
  }
}

/* ── 팩스 결과코드 설명 ── */
/* 결과코드를 사람 말로 (2026-10-03 지시 「실패시 오류 메세지도 확인되어야 함」).

   여기 적혀 있던 표는 **우리가 받는 코드와 맞지 않았다.** 0~12 를 적어 두었는데
   운영이 실제로 받은 것은 100ㆍ513ㆍ499ㆍ505ㆍ517ㆍ999 다 — 한 개도 걸리지 않아
   화면에는 늘 「결과코드 513」처럼 숫자만 떴다.

   **뜻을 지어내지 않는다.** 우리가 확인한 것은 100 이 성공이라는 것 하나뿐이다
   (FaxHistory::RESULT_OK · 2026-09-30 에 이 잣대로 성공을 실패로 적던 것을
   바로잡았다). 나머지는 숫자를 그대로 보이고, 몇 장이 닿았는지를 함께 적는다 —
   그것은 팝빌이 숫자로 주는 **사실**이라 지어낼 것이 없다. */
function faxResultDesc(code) {
  const c = String(code ?? '');
  if (c === '') { return '결과 없음'; }
  if (c === '100') { return '결과코드 100 · 전송 성공'; }
  return '결과코드 ' + c + ' · 전송 실패';
}

/** 몇 장이 닿았나 — 팝빌이 숫자로 준다. 실패한 건에서 가장 먼저 보는 값이다 */
function faxPageDesc(r) {
  const 전체 = Number(r.sendPageCnt ?? 0);
  if (!전체) { return ''; }
  const 성공 = Number(r.successPageCnt ?? 0);
  const 실패 = Number(r.failPageCnt ?? 0);
  const 환불 = Number(r.refundPageCnt ?? 0);
  return 전체 + '장 중 ' + 성공 + '장 닿음'
       + (실패 ? ' · ' + 실패 + '장 실패' : '')
       + (환불 ? ' · ' + 환불 + '장 환불' : '');
}

function renderPager(total, page, perPage) {
  const pager = document.getElementById('hist-pager');
  const pages = Math.ceil(total / perPage);

  /* 탭 이름 옆의 건수도 여기서 적는다 — 참된 전체 건수를 아는 자리가 여기다 */
  const 탭건수 = document.getElementById('fax-total-count');
  if (탭건수) { 탭건수.textContent = Number(total || 0).toLocaleString(); }

  /* **쪽이 하나뿐이어도 건수는 보인다** (2026-10-03 지시).

     여태 쪽이 하나면 줄 전체를 감췄다. 표의 아랫줄이 건수를 적고 있어 그래도 됐지만,
     그 줄을 껐으므로 이제 감추면 건수까지 사라진다. 아무것도 없을 때만 감추고,
     한 쪽일 때는 건수만 두고 단추를 비운다 — 다른 목록 화면과 같은 규칙이다. */
  if (!total) { pager.style.display = 'none'; return; }

  pager.style.display = 'flex';

  /* 쪽이 여럿이면 몇 쪽 가운데 몇 쪽인지도 함께 적는다 */
  document.getElementById('pager-info').textContent =
    `전체 ${total.toLocaleString()}건` + (pages > 1 ? ` · ${page}/${pages}쪽` : '');

  if (pages <= 1) { document.getElementById('pager-btns').innerHTML = ''; return; }

  const btns   = [];
  const start  = Math.max(1, page - 2);
  const end    = Math.min(pages, page + 2);

  if (page > 1) btns.push(`<button class="pager-btn" onclick="loadHistory(${page-1})">‹</button>`);
  for (let p = start; p <= end; p++) {
    btns.push(`<button class="pager-btn ${p===page?'active':''}" onclick="loadHistory(${p})">${p}</button>`);
  }
  if (page < pages) btns.push(`<button class="pager-btn" onclick="loadHistory(${page+1})">›</button>`);

  document.getElementById('pager-btns').innerHTML = btns.join('');
}

/* ── 상세 모달 ── */
async function openDetail(receiptNum) {
  document.getElementById('detail-modal').classList.add('open');
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">불러오는 중…</div>';

  try {
    const corpNum = CORP_NUM.value.trim();
    const res  = await fetch(`${FAX_BASE}/messages?corp_num=${corpNum}&receipt_num=${receiptNum}`, { headers: HEADERS });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || '상세 조회 실패');

    // GetFaxDetail 은 FaxState[] 반환 — 각 원소가 수신자별 상태
    const arr   = Array.isArray(data) ? data : [data];
    const first = arr[0] || {};
    /* 팝빌 상태(0 접수 · 1 변환중 · 2 전송중 · 3 완료 · 4 취소)에 결과코드를 함께
       보아야 닿았는지 알 수 있다 — 완료(3)라도 결과코드가 100 이 아니면 실패다. */
    const _완료 = String(first.state) === '3';
    const _닿음 = Number(first.result) === 100;
    const stMap = { '0':'wait','1':'send','2':'send','3': _닿음 ? 'ok' : 'fail','4':'cancel' };
    const txMap = { '0':'접수','1':'변환 중','2':'전송 중',
                    '3': _닿음 ? '전송 성공' : '전송 실패','4':'취소' };

    // 접수일시 / 예약일시
    const fmt = s => s ? s.replace(/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1-$2-$3 $4:$5:$6') : '';
    const receiptDT = fmt(first.receiptDT) || fmt(first.sendDT) || '—';
    const reserveDT = fmt(first.reserveDT) || '';

    // 페이지 수 집계 (sendPageCnt 있으면 활용, 없으면 수신자 수 기반)
    const totalPages  = first.sendPageCnt  ?? arr.length;
    const okPages     = first.successPageCnt ?? arr.filter(r => r.state == 2).length;
    const failPages   = first.failPageCnt    ?? arr.filter(r => r.state == 3).length;
    const cancelPages = first.cancelPageCnt  ?? arr.filter(r => r.state == 4).length;
    const waitPages   = Math.max(0, totalPages - okPages - failPages - cancelPages);

    // 변환 상태
    const convState = first.convState;
    const convOk    = convState == null ? arr.some(r => r.state > 0) : convState == 1;
    const convTxt   = convOk ? `<span class="fax-stat-blue">변환성공</span>` : `<span class="fax-stat-red">변환실패</span>`;

    const rcvRows = arr.length
      ? arr.map(r => {
          const rCls  = stMap[String(r.state??'')] ?? 'wait';
          const rTxt  = txMap[String(r.state??'')] ?? '—';
          /* 결과코드와 함께 **몇 장이 닿았는지**를 적는다 — 실패한 건에서 가장 먼저
             보는 값이고, 팝빌이 숫자로 주므로 지어낼 것이 없다 */
          const _장 = faxPageDesc(r);
          const rDesc = r.state == 3
            ? `<br><span style="font-size:10px;color:var(--text-muted);">${faxResultDesc(r.result)}${_장 ? ' · ' + _장 : ''}</span>`
            : '';
          return `<tr><td>${r.receiveNum??'—'}</td><td>${r.receiveName??'—'}</td><td><span class="fax-badge ${rCls}">${rTxt}</span>${rDesc}</td></tr>`;
        }).join('')
      : '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">수신자 정보 없음</td></tr>';

    document.getElementById('detail-body').innerHTML = `
      <div class="fax-info-area">
        <div>
          <span class="fax-api-badge">API</span>
          <p><span class="fax-info-title">접수번호</span>${receiptNum}</p>
        </div>
        <p>
          <span class="fax-info-title">전송결과</span>
          <span>전체 ${totalPages}장</span>
          <span>대기 ${waitPages}장</span>
          <span>성공 <span class="fax-stat-blue">${okPages}</span>장</span>
          <span>실패 <span class="fax-stat-red">${failPages}</span>장</span>
          <span>취소 ${cancelPages}장</span>
        </p>
        ${_닿음 ? '' : `
        <p style="margin-top:6px;padding:7px 10px;border-radius:6px;
                  background:#FEF3F2;border:1px solid #FECDCA;color:#B42318;font-size:12px;">
          <b>전송 실패</b>
          · ${faxResultDesc(first.result)}
          ${first.sendResult != null ? ' · 전송결과 ' + first.sendResult : ''}
          ${Number(first.refundPageCnt ?? 0) ? ' · ' + first.refundPageCnt + '장 환불' : ''}
          ${fmt(first.resultDT) ? ' · 결과 ' + fmt(first.resultDT) : ''}
        </p>`}
      </div>
      <table class="fax-normal-table">
        <colgroup><col width="15%"><col width="35%"><col width="15%"><col width="35%"></colgroup>
        <tbody>
          <tr style="height:40px;">
            <th>접수일시</th><td>${receiptDT}</td>
            <th>예약일시</th><td>${reserveDT}</td>
          </tr>
          <tr style="height:40px;">
            <th>구분</th><td>팩스</td>
            <th>발신번호</th><td>${first.sendNum ?? '—'}</td>
          </tr>
          <tr style="height:40px;">
            <th>변환상태</th>
            <td colspan="3">
              <div class="fax-conv-row">
                ${convTxt}
                <span>[<button class="fax-dl-btn" type="button">${receiptNum}.tif <i class="bx bx-download"></i></button>]</span>
                <button class="fax-preview-btn" type="button">미리보기</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="di-label" style="margin-bottom:6px;font-size:11px;font-weight:700;color:var(--gray-600);">수신자 목록</div>
      <table class="rcv-table">
        <thead><tr><th>팩스번호</th><th>수신자명</th><th>상태 / 결과</th></tr></thead>
        <tbody>${rcvRows}</tbody>
      </table>`;
  } catch(e) {
    document.getElementById('detail-body').innerHTML = `<div style="text-align:center;padding:30px;color:var(--danger);">${e.message}</div>`;
  }
}

function closeModal() {
  document.getElementById('detail-modal').classList.remove('open');
}
document.getElementById('detail-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('detail-modal')) closeModal();
});
</script>
@endpush
