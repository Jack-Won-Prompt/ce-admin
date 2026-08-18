{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('title', '대시보드')
@section('page-title', '대시보드')

@section('help-title', '대시보드 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>CE Admin의 시작 화면입니다. 주요 현황을 한눈에 파악할 수 있습니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 구성</div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-file-blank"></i></div>
    <div class="help-item-text"><strong>처방전 현황 카드</strong>처리 대기, 검수 필요, 주문 미등록 건수를 실시간으로 표시합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-cart"></i></div>
    <div class="help-item-text"><strong>주문 현황</strong>배송 중, 배송 완료 건수와 오늘 생성된 주문을 확인합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-line-chart"></i></div>
    <div class="help-item-text"><strong>최근 활동</strong>최근 처방전 업로드 및 주문 내역을 확인합니다.</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">빠른 시작</div>
  <div class="help-item">
    <div class="help-item-icon info"><i class="bx bx-upload"></i></div>
    <div class="help-item-text"><strong>처방전 업로드</strong>좌측 메뉴 <b>환자ㆍ처방 → 처방자료 업로드</b>에서 이미지를 업로드하면 OCR이 자동 처리됩니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link"></i></div>
    <div class="help-item-text"><strong>Withworks 연계</strong>주문 화면에서 처방전을 검수한 뒤 주문 연계 탭에서 Withworks 판매주문을 자동 생성합니다.</div>
  </div>
</div>
@endsection
{{-- 시안 382:107 의 빵부스러기에는 날짜가 없다. 오늘 날짜를 덧붙이던 조각을 뺀다. --}}
@section('breadcrumb', '홈 / 대시보드')

@push('scripts')
<script>
(function () {
  const el = document.getElementById('recentRxGrid');
  if (!el) return;
  const RX_BASE = @json(url('prescriptions'));   // + '/{rx_number}'
  const grid = new wwGrid({
    el: el,
    // 시안 382:107 — 표 안쪽 스크롤이 없다(행이 전부 보인다). 높이를 주지 않으면 내용만큼 자란다.
    // 360 으로 못 박으면 10건이 찰 때 105px 이 잘려 나갔다.
    editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '처방번호',  name: 'rx_number', width: 130, sortable: true },
      { header: '이름',    name: 'patient',   width: 100, sortable: true },
      { header: '생년월일',  name: 'birth',     width: 110, align: 'center' },
      { header: '상태',      name: 'ocr',       width: 110, align: 'center', sortable: true },
      { header: '주문',      name: 'order',     width: 90,  align: 'center' },
      { header: '청구',      name: 'claim',     width: 90,  align: 'center' },
      { header: '담당',      name: 'manager',   width: 90 },
    ],
    data: @json($recentRxGrid ?? []),
  });
  /* 더블클릭 → 그 처방전의 주문 화면을 '새 탭'으로 연다.
     워크스페이스 안에서는 대시보드 탭을 그대로 두고 별도 탭이 열리고(ceOpenTab),
     단독 페이지로 열려 있으면 브라우저 새 탭으로 대체된다.
     (처방전 목록·서류 관리·주문 상세와 같은 방식) */
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.rx_number) return;

    const url = RX_BASE + '/' + encodeURIComponent(row.rx_number);
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(url, '주문 - ' + row.rx_number, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  });
})();
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.stat-grid', title: '현황 요약 카드', body: '오늘 접수·검수 대기·주문 미등록 등 핵심 수치를 한눈에 확인합니다. 카드를 클릭하면 해당 목록으로 바로 이동합니다.' },
  { selector: '.stat-card', title: '통계 카드', body: '각 카드는 클릭 가능한 링크입니다. 숫자를 클릭하면 해당 상태로 필터된 목록이 열립니다.' },
  { selector: '.layout-menu .menu-inner', title: '사이드바 메뉴', body: '처방전·환자·주문·청구·정산 등 모든 기능을 여기서 이동합니다. 좌측 상단 화살표로 메뉴를 접을 수 있습니다.' },
  { selector: '#helpToggleBtn', title: '도움말 항상 여기에', body: '어느 화면에서든 ? 버튼을 누르면 해당 페이지 설명과 투어를 다시 시작할 수 있습니다.' },
];
</script>
@endpush

@push('styles')
<style>
  /* ── Stat Cards ── 시안 382:107 실측: 251×73 · r12 · pad 16 · gap 16 · 흰 배경 · 그림자 없음 */
  /* 격자는 인라인 style= 이 아니라 여기에 둔다 — 그래야 미디어쿼리가 우선순위 강제 없이 이긴다 */
  /* 아래 여백은 .page-body 의 flex gap 12 하나로만 만든다(시안 통계줄 y188 → 다음 줄 y200) */
  .stat-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: 12px; margin-bottom: 0; }
  .stat-card {
    background: var(--gray-0);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 16px;
    display: flex; align-items: center; gap: 16px;
    cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
    /* 전역 .stat-card 가 box-shadow:var(--shadow) 를 준다 — 선언을 빼면 그게 그대로 남는다.
       시안 카드는 평평하므로 여기서 명시적으로 꺼야 한다. */
    box-shadow: none;
  }
  /* 전역 .stat-card:hover 의 그림자와 -2px 들림도 같은 이유로 명시적으로 끈다 */
  .stat-card:hover { border-color: var(--primary); color: inherit; box-shadow: none; transform: none; }
  /* 시안 글자 묶음 45×41 = 라벨(19) 위 + 값(22) 아래. 마크업은 값이 먼저라 CSS 로 뒤집는다
     (조건식이 든 라벨 블록을 옮기지 않으려는 것 — 낱말·조건식은 그대로 둔다) */
  .stat-card > div:last-child { display: flex; flex-direction: column-reverse; min-width: 0; }
  /* 32×32 · r8 · 아이콘 16 — 헤더 아이콘 버튼과 같은 규격 */
  .stat-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
  }
  /* 시안에는 초록·주황·남색이 없다. 강조는 primary 램프, 처리 대기는 alert 램프로만 나눈다.
     구분은 램프 안의 명도로 준다(클래스 이름은 그대로 둔다 — 마크업이 이 이름으로 붙는다). */
  .stat-icon.primary  { background: var(--primary-50);  color: var(--primary-500); }
  .stat-icon.success  { background: var(--primary-50);  color: var(--primary-700); }
  .stat-icon.warning  { background: var(--alert-50);    color: var(--alert-500); }
  .stat-icon.danger   { background: var(--alert-50);    color: var(--alert-500); }
  .stat-icon.info     { background: var(--primary-50);  color: var(--primary-400); }
  .stat-icon.purple   { background: var(--primary-50);  color: var(--primary-600); }
  /* 시안 값 14/700 lh22 gray-1000 · 라벨 12/500 lh19 gray-800 (합 41 = 카드 안쪽 73−32) */
  .stat-val   { font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-1000); }
  .stat-label { font-size: 12px; line-height: 19px; color: var(--gray-800); margin-top: 0; font-weight: 500; }

  /* ── Work Queue Boxes ──
     시안: 흰 카드 1167×54 · r12 · pad 12/0 한 장 안에 389×30 알약 셋이 간격 0 으로 붙고,
     칸 경계마다 1px gray-200 세로선이 선다. 알약 안은 가로 한 줄 가운데 정렬 —
     라벨 14/700 lh22 + gap 8 + 18×18 정원 배지(11/700 · 흰 글자). */
  .queue-grid {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 0;
    background: var(--gray-0); border: 1px solid var(--border); border-radius: 12px;
    padding: 12px 0;
    margin-bottom: 10px;    /* 시안 상태줄 y254 → 표 카드 y264 */
  }
  .queue-box {
    background: transparent; border: 0; border-radius: 999px;
    height: 30px; padding: 4px 12px; cursor: pointer;
    transition: var(--transition);
    text-decoration: none; color: inherit;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    position: relative; overflow: visible;
  }
  /* 위쪽 색띠였던 ::before 를 칸 사이 세로 구분선으로 돌려 쓴다 (시안 x725 · x1114) */
  .queue-box::before {
    content: ''; position: absolute; top: 0; bottom: 0; left: 0; right: auto;
    width: 1px; height: auto; border-radius: 0; background: var(--border);
  }
  .queue-box:first-child::before { display: none; }
  .queue-box:hover { background: var(--gray-50); color: inherit; }
  /* 마크업 순서는 아이콘 → 숫자 → 라벨. 시안 순서(라벨 → 배지)로 CSS 에서만 세운다 */
  /* 시안 경고칩 안은 [라벨 · gap 8 · 18 배지] 둘뿐이고, 그 묶음이 389 칸 한가운데 놓여
     라벨이 x492 / 872 / 1256 에서 시작한다. 렌더는 앞에 아이콘(16 + gap 8 = 24)이 하나 더
     들어가 묶음 가운데가 12px 오른쪽으로 밀렸다. 아이콘은 남기고 폭+gap 만 상쇄해
     라벨·배지가 시안대로 가운데에 서게 한다(−24 + 16 + gap 8 = 0).
     왼쪽으로 빼야 한다 — 오른쪽으로 빼면 아이콘이 라벨 글자 위에 겹쳐 그려진다.
     이렇게 두면 아이콘은 라벨 8px 앞(x468 등)에 그대로 보인다. */
  .queue-box .q-icon  { order: 1; font-size: 16px; line-height: 16px; margin: 0 0 0 -24px; display: block; flex-shrink: 0; }
  .queue-box .q-label { order: 2; font-size: 14px; font-weight: 700; line-height: 22px; white-space: nowrap; }
  .queue-box .q-num {
    order: 3; width: 18px; height: 18px; border-radius: 999px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; line-height: 18px; margin: 0; color: var(--gray-0);
  }
  .queue-box.red   .q-icon, .queue-box.red   .q-label { color: var(--alert-500); }
  .queue-box.blue  .q-icon, .queue-box.blue  .q-label { color: var(--primary-500); }
  .queue-box.green .q-icon, .queue-box.green .q-label { color: var(--gray-1000); }
  .queue-box.red   .q-num { background: var(--alert-500); }
  .queue-box.blue  .q-num { background: var(--primary-500); }
  .queue-box.green .q-num { background: var(--gray-1000); }

  /* ── Activity timeline (시각·주체·내용이 한 줄, 상세는 hover 팝오버) ──
     시안 한 줄 35 = pad 8/0 + 글자 19 · gap 4 · 줄 사이 1px gray-200 · 마지막 줄만 없음 */
  .activity-item {
    display: flex; gap: 4px; align-items: center;
    padding: 8px 0; position: relative;
    /* 줄 사이 선은 안쪽에 그린다 — 테두리로 주면 한 줄이 36 이 되어 네 줄이 시안(140)보다 3 커진다 */
    border-bottom: 0; box-shadow: inset 0 -1px 0 var(--border);
  }
  .activity-item:last-child { border-bottom: none; box-shadow: none; }
  /* 시안은 이 자리가 14×14 시계 아이콘이다 */
  .activity-dot {
    width: 14px; height: 14px; flex-shrink: 0;
    font-size: 14px; line-height: 14px; color: var(--gray-500);
  }
  /* 마크업은 제목이 먼저라 CSS 로 뒤집어 [시각·주체][내용] 한 줄로 세운다 */
  .activity-main {
    min-width: 0; flex: 1;
    display: flex; flex-direction: row-reverse; align-items: center; gap: 4px;
  }
  .activity-title {
    font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-1000);
    flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .activity-time {
    font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-500);
    margin-top: 0; white-space: nowrap; flex-shrink: 0;
    /* 시안 시각 48 + gap 4 + 주체 48 = 100. 다만 폭을 100 으로 못 박으면 nowrap 인 글자가
       상자 밖으로 흘러 오른쪽 내용 글자와 겹친다(주체가 다섯 자 이상이면 실제로 겹쳤다).
       최소폭으로 두면 보통 이름은 시안대로 100 이고, 긴 이름은 상자가 늘고 내용 쪽이 말줄임된다. */
    min-width: 100px;
  }
  /* 팝오버(상세 전체 내용) — 사이드바 좌측으로 열림 */
  .activity-pop {
    display: none; position: absolute; right: calc(100% + 12px); top: -6px;
    width: 320px; max-width: 320px; background: var(--gray-0); border: 1px solid var(--border);
    border-radius: 12px; box-shadow: var(--shadow-lg); padding: 12px;
    font-size: 12px; line-height: 19px; color: var(--gray-800);
    white-space: normal; word-break: break-all; z-index: 200;
  }
  .activity-item:hover { background: var(--primary-light); border-radius: 6px; }
  .activity-item:hover .activity-pop { display: block; }
  /* 팝오버가 카드 밖으로 나올 수 있게 클리핑 방지 */
  .recent-activity-body { overflow: visible; }

  /* ── Quick action buttons ──
     시안 175×37 · r8 · pad 8/12 · 가로 · gap 8 · 흰 배경 ·
     [16 아이콘 primary][라벨 13/500 lh21 gray-1000 한 줄][오른쪽 끝 chevron 14] */
  .quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .qa-btn {
    display: flex; flex-direction: row; align-items: center; gap: 8px;
    /* 시안 바깥 높이 37 = 위아래 7 + 라벨 21 + 테두리 두 줄 (시안 pad 8 은 테두리를 안쪽으로 먹는 값) */
    padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--gray-0); cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
  }
  .qa-btn:hover { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
  .qa-btn:hover .qa-icon { color: var(--primary); }
  .qa-icon { font-size: 16px; line-height: 16px; color: var(--primary); flex-shrink: 0; }
  .qa-label {
    font-size: 13px; line-height: 21px; font-weight: 500; color: var(--gray-1000); text-align: left;
    flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .qa-chev { font-size: 14px; line-height: 14px; color: var(--gray-1000); flex-shrink: 0; }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }

  /* 시안 왼쪽 1167 : 오른쪽 389 = 3 : 1 · gap 12 (?frame=1 에서도 같은 비율로 늘어난다) */
  .dash-grid { display: grid; grid-template-columns: 3fr 1fr; gap: 12px; }
  /* 표가 든 왼쪽 단은 격자 칸의 자동 최소폭(min-width:auto = 표의 min-content 781)을 풀어 준다.
     풀지 않으면 사이드바를 편 1280·1366 폭에서 왼쪽 단이 783 으로 버텨 오른쪽 단(빠른 실행·최근 활동)이
     화면 밖으로 155·69px 밀려 나가는데, .content-wrapper 가 overflow-x: clip 이라 스크롤조차 생기지 않아
     아예 닿을 수 없게 된다. 0 으로 풀면 표는 .cg-wrap 안에서 가로로 스크롤된다(다른 그리드 화면과 같다). */
  .dash-grid > div:first-child { min-width: 0; }

  /* ── 카드 테두리 ── 시안 382:107: 본문 큰 카드 셋(1167×797 · 389×158 · 389×691)은
     r12 흰 바탕에 stroke 가 없다. 구분선은 머리 프레임 아래 1px #E8EAEC 하나뿐이다.
     전역 .card 의 사방 1px 을 이 화면 안에서만 끈다 — 머리 아래 선(.card-header 의
     border-bottom)은 그대로 남는다. 사방 테두리가 있으면 머리가 x337 로 1px 밀린다. */
  .dash-grid .card { border: 0; }

  /* ── 카드 머리 ── 시안 세 카드 모두 h44 · pad 0/16 · 제목 13/700 lh21 gray-1000 */
  .dash-grid .card-header { height: 44px; padding: 0 16px; gap: 4px; }
  /* 시안 머리에는 아이콘이 없고 제목이 pad 16 자리(x352 · x1531)에서 바로 시작한다.
     개발이 넣은 앞머리 아이콘은 남기되, 그 뒤 gap 4 만큼 제목이 밀리던 것을 되돌린다.
     bx-file-medical·bx-zap 은 이 boxicons 판에 글리프가 없어 폭 0 으로 그려진다 —
     아무것도 안 보이는 자리가 뒤 gap 4 만 먹고 제목을 밀고 있었으므로 그 둘만 상쇄한다.
     '최근 활동'의 bx-time-five 는 실제로 16px 로 그려지므로 손대지 않는다. 왼쪽으로 빼면
     그 시계 혼자 카드 안쪽 여백선(x1531)을 4px 넘어서서, 화면에서 유일하게 눈에 보이는
     머리 아이콘이 다른 요소들의 왼쪽 줄을 깬다. 그 카드 제목을 x1531 까지 당기려면
     아이콘을 빼야 하는데 지우는 일이라 디자이너 판단으로 남긴다. */
  .dash-grid .card-header > i.bx-file-medical:first-child,
  .dash-grid .card-header > i.bx-zap:first-child { margin-right: -4px; }
  .dash-grid .card-header-title { font-size: 13px; font-weight: 700; line-height: 21px; color: var(--gray-1000); letter-spacing: 0; }
  /* 제목 옆 건수 — 시안 13/700 primary, gap 4 */
  .dash-grid .card-header .rx-count { font-size: 13px; font-weight: 700; line-height: 21px; color: var(--primary); }
  /* 표 위 안내문 — 시안에는 없지만 더블클릭이 실제로 동작하므로 남긴다. 카드 머리로 옮겼다 */
  .dash-grid .card-header .dash-hint {
    font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600);
    white-space: nowrap; margin-left: 12px;
    /* 좁은 화면에서 전체보기 버튼을 밀어내지 않게 안내문 쪽이 먼저 줄어들게 둔다 */
    min-width: 0; overflow: hidden; text-overflow: ellipsis;
  }
  /* 전체보기 — 시안 66×28 · r8 · pad 0/12 · 12/500 (아이콘은 남긴다) */
  .dash-grid .card-header .btn { height: 28px; padding: 0 12px; font-size: 12px; font-weight: 500; line-height: 19px; border-radius: 8px; gap: 6px; }
  /* ── 카드 본문 ── 시안 빠른 실행·최근 활동 모두 pad 16 (표 카드만 0 — 인라인으로 준다) */
  .dash-grid .card-body { padding: 16px; }

  /* ── 최근 처방전 표 ── 시안: 표가 카드 좌우 끝까지 붙고 안쪽 스크롤이 없다.
     행 41 = pad 10/12 + lh21, 구분선 1px 은 셀 안쪽에 그린다(테두리로 주면 42 가 된다) */
  #recentRxGrid .cg-wrap { border: 0; border-radius: 0; }
  /* 머리행 45 = pad 12 + 21 + 12. 아래 1px 은 이미 배경 그라디언트로 그려져 있어 테두리는 뺀다
     (테두리를 두면 collapse 가 그 1px 을 첫 행에 얹어 첫 줄만 42 가 된다) */
  #recentRxGrid .cg-thead th { border-bottom: 0; }
  #recentRxGrid .cg-th-inner { line-height: 21px; }
  #recentRxGrid .cg-cell-inner { line-height: 21px; }
  #recentRxGrid .cg-tbody tr,
  #recentRxGrid .cg-tbody tr:last-child { border-bottom: 0; }
  /* 전역 tbody td 의 border-bottom 1px 도 이 표를 잡는다 — 그것까지 걷어야 42 가 41 이 된다 */
  #recentRxGrid .cg-tbody td { border-bottom: 0; box-shadow: inset 0 -1px 0 var(--gray-100); }
  /* 하단 '전체 N건' 띠 — 시안 페이저가 놓인 자리. pad 12/16 · 위 1px gray-200 · 흰 배경 */
  #recentRxGrid .cg-footer {
    background: var(--gray-0); border-top: 1px solid var(--border);
    padding: 12px 16px; font-size: 12px; font-weight: 500; line-height: 19px;
    color: var(--gray-600); border-radius: 0 0 12px 12px;
  }

  /* 격자를 CSS 로 옮겨서 인라인 style= 과 싸울 일이 없어졌다 — 우선순위 강제를 걷어냈다 */
  @media (max-width: 1100px) { .dash-grid { grid-template-columns: 1fr; } }
  @media (max-width: 640px)  {
    .queue-grid { grid-template-columns: 1fr 1fr; }
    .queue-box::before { display: none; }
    .stat-grid { grid-template-columns: repeat(2,1fr); }
  }
</style>
@endpush

@section('content')

{{-- ── Stat Strip (6 KPIs) ── --}}
<div class="stat-grid">
  {{-- 숫자를 눌러 그 목록으로 간다. 화면 탭으로 열어야 대시보드가 남는다 --}}
  <a href="{{ route('prescriptions.index') }}" class="stat-card"
     data-ce-tab="처방전 목록" data-ce-icon="bx-file">
    <div class="stat-icon primary"><i class="bx bx-file-blank"></i></div>
    <div>
      <div class="stat-val">{{ $stats['total_today'] }}</div>
      <div class="stat-label">오늘 접수</div>
    </div>
  </a>
  <a href="{{ route('prescriptions.index', ['status'=>'review_needed']) }}" class="stat-card"
     data-ce-tab="처방전 목록 - 검수 필요" data-ce-icon="bx-file">
    <div class="stat-icon warning"><i class="bx bx-error-circle"></i></div>
    <div>
      <div class="stat-val">{{ $stats['review_needed'] }}</div>
      <div class="stat-label">검수 대기</div>
    </div>
  </a>
  <a href="{{ route('prescriptions.index', ['status'=>'approved']) }}" class="stat-card"
     data-ce-tab="처방전 목록 - 검수 완료" data-ce-icon="bx-file">
    <div class="stat-icon success"><i class="bx bx-check-shield"></i></div>
    <div>
      <div class="stat-val">{{ $stats['approved_today'] }}</div>
      <div class="stat-label">오늘 승인</div>
    </div>
  </a>
  <a href="{{ route('orders.index') }}" class="stat-card"
     data-ce-tab="주문현황" data-ce-icon="bx-clipboard">
    <div class="stat-icon info"><i class="bx bx-cart-alt"></i></div>
    <div>
      <div class="stat-val">{{ $stats['orders_pending'] }}</div>
      <div class="stat-label">주문 대기</div>
    </div>
  </a>
  <a href="{{ route('nhis.index') }}" class="stat-card"
     data-ce-tab="청구 관리" data-ce-icon="bx-file-blank">
    <div class="stat-icon danger"><i class="bx bx-plus-medical"></i></div>
    <div>
      <div class="stat-val">{{ $stats['nhis_pending'] }}</div>
      <div class="stat-label">청구 대기</div>
    </div>
  </a>
  <a href="{{ route('repurchase.index') }}" class="stat-card"
     data-ce-tab="재구매 관리" data-ce-icon="bx-repeat">
    <div class="stat-icon purple"><i class="bx bx-refresh"></i></div>
    <div>
      <div class="stat-val">{{ $stats['repurchase_today'] }}</div>
      <div class="stat-label">오늘 재구매
        @if($stats['repurchase_upcoming'] > 0)
          <span class="badge badge-primary" style="font-size:10px;margin-left:3px;">7일내 {{ $stats['repurchase_upcoming'] }}</span>
        @endif
      </div>
    </div>
  </a>
</div>

{{-- ── Main Grid ── --}}
<div class="dash-grid">

  <div>
    {{-- Work Queue --}}
    <div class="queue-grid">
      <a href="{{ route('prescriptions.index', ['status' => 'review_needed']) }}" class="queue-box red"
         data-ce-tab="처방전 목록 - 검수 필요" data-ce-icon="bx-file">
        <span class="q-icon"><i class="bx bx-error-alt"></i></span>
        <div class="q-num">{{ $stats['review_needed'] }}</div>
        <div class="q-label">검수 필요</div>
      </a>
      <a href="{{ route('prescriptions.index', ['status' => 'ocr_processing']) }}" class="queue-box blue"
         data-ce-tab="처방전 목록 - 처리중" data-ce-icon="bx-file">
        <span class="q-icon"><i class="bx bx-scan"></i></span>
        <div class="q-num">{{ $stats['ocr_processing'] }}</div>
        <div class="q-label">처리중</div>
      </a>
      <a href="{{ route('prescriptions.index', ['status' => 'approved']) }}" class="queue-box green"
         data-ce-tab="처방전 목록 - 검수 완료" data-ce-icon="bx-file">
        <span class="q-icon"><i class="bx bx-check-circle"></i></span>
        <div class="q-num">{{ $stats['approved_today'] }}</div>
        <div class="q-label">오늘 승인 완료</div>
      </a>
    </div>

    {{-- Recent Prescriptions Table --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-file-medical" style="font-size:16px;color:var(--primary);"></i>
        <span class="card-header-title">최근 처방전 현황</span>
        <span class="rx-count">{{ count($recentRxGrid ?? []) }}</span>
        <span class="dash-hint"><i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 처방전 상세로 이동합니다.</span>
        {{-- 목록은 화면 탭으로 열어 대시보드를 남긴다(오늘 틀) --}}
        <a href="{{ route('prescriptions.index') }}" class="btn btn-outline btn-sm ms-auto"
           data-ce-tab="처방전 목록" data-ce-icon="bx-file">
          <i class="bx bx-list-ul"></i> 전체보기
        </a>
      </div>
      <div class="card-body" style="padding:0;">
        <div id="recentRxGrid"></div>
      </div>
    </div>
  </div>

  {{-- ── RIGHT COLUMN ── --}}
  <div>

    {{-- Quick Actions --}}
    <div class="card" style="margin-bottom:12px;">
      <div class="card-header">
        <i class="bx bx-zap" style="font-size:16px;color:var(--primary);"></i>
        <span class="card-header-title">빠른 실행</span>
      </div>
      <div class="card-body">
        {{-- 화면 탭으로 연다. 그냥 링크면 대시보드가 그 화면으로 통째로 바뀌어,
             보고 있던 오늘 현황이 사라지고 돌아오려면 대시보드를 다시 열어야 했다.
             data-ce-tab 은 탭 이름이다 — 메뉴에 적힌 이름과 같게 맞춘다. --}}
        <div class="quick-grid">
          <a href="{{ route('prescriptions.upload') }}" class="qa-btn"
             data-ce-tab="처방자료 업로드" data-ce-icon="bx-upload">
            <i class="bx bx-upload qa-icon"></i>
            <span class="qa-label">처방전 업로드</span>
            <i class="bx bx-chevron-right qa-chev"></i>
          </a>
          <a href="{{ route('orders.index') }}" class="qa-btn"
             data-ce-tab="주문현황" data-ce-icon="bx-clipboard">
            <i class="bx bx-clipboard qa-icon"></i>
            <span class="qa-label">주문 확인</span>
            <i class="bx bx-chevron-right qa-chev"></i>
          </a>
          <a href="{{ route('nhis.index') }}" class="qa-btn"
             data-ce-tab="청구 관리" data-ce-icon="bx-file-blank">
            <i class="bx bx-file-blank qa-icon"></i>
            <span class="qa-label">요양비 청구</span>
            <i class="bx bx-chevron-right qa-chev"></i>
          </a>
          <a href="{{ route('patients.index') }}" class="qa-btn"
             data-ce-tab="거래처 관리" data-ce-icon="bx-user-plus">
            <i class="bx bx-user-plus qa-icon"></i>
            <span class="qa-label">환자 등록</span>
            <i class="bx bx-chevron-right qa-chev"></i>
          </a>
        </div>
      </div>
    </div>

    {{-- Recent Activity --}}
    <div class="card">
      <div class="card-header">
        <i class="bx bx-time-five" style="font-size:16px;color:var(--primary);"></i>
        <span class="card-header-title">최근 활동</span>
      </div>
      <div class="card-body recent-activity-body" style="padding:16px;">
        @forelse($activities as $act)
        <div class="activity-item">
          <i class="bx bx-time-five activity-dot"></i>
          <div class="activity-main">
            <div class="activity-title">{{ \Illuminate\Support\Str::before($act->description, ' | ') ?: $act->description }}</div>
            <div class="activity-time">{{ $act->created_at->format('H:i') }} · {{ $act->causer?->name ?? '시스템' }}</div>
          </div>
          <div class="activity-pop">{{ $act->description }}</div>
        </div>
        @empty
        <div style="text-align:center;color:var(--gray-500);font-size:13px;padding:16px 0;">
          <i class="bx bx-time" style="font-size:16px;display:block;margin-bottom:6px;opacity:.35;"></i>
          활동 내역이 없습니다.
        </div>
        @endforelse
      </div>
    </div>

  </div>
</div>

@endsection
