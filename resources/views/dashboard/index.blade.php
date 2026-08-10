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
    <div class="help-item-text"><strong>처방전 현황 카드</strong>OCR 처리 대기, 검수 필요, 주문 미등록 건수를 실시간으로 표시합니다.</div>
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
    <div class="help-item-text"><strong>처방전 업로드</strong>좌측 메뉴 <b>처방전 관리 → 업로드</b>에서 이미지를 업로드하면 OCR이 자동 처리됩니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link"></i></div>
    <div class="help-item-text"><strong>Withworks 연계</strong>처방전 검수 후 주문 탭에서 Withworks 판매주문을 자동 생성합니다.</div>
  </div>
</div>
@endsection
@section('breadcrumb', '홈 / 대시보드 · ' . now()->format('Y-m-d'))

@push('scripts')
<script>
(function () {
  const el = document.getElementById('recentRxGrid');
  if (!el) return;
  const RX_BASE = @json(url('prescriptions'));   // + '/{rx_number}'
  const grid = new wwGrid({
    el: el,
    // 최근 10건이 스크롤 없이 다 보이는 높이. 320 이면 25px 만 넘쳐 흔들리는 스크롤이 생긴다.
    height: 360, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '처방번호',  name: 'rx_number', width: 130, sortable: true },
      { header: '환자명',    name: 'patient',   width: 100, sortable: true },
      { header: '생년월일',  name: 'birth',     width: 110, align: 'center' },
      { header: 'OCR 상태',  name: 'ocr',       width: 110, align: 'center', sortable: true },
      { header: '주문',      name: 'order',     width: 90,  align: 'center' },
      { header: '청구',      name: 'claim',     width: 90,  align: 'center' },
      { header: '담당',      name: 'manager',   width: 90 },
    ],
    data: @json($recentRxGrid ?? []),
  });
  /* 더블클릭 → 처방전 검수 화면을 '새 탭'으로 연다.
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
      window.ceOpenTab(url, '처방전 관리 - ' + row.rx_number, 'file-edit-02');
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
  { selector: '.layout-menu .menu-inner', title: '사이드바 메뉴', body: '처방전·환자·주문·NHIS·정산 등 모든 기능을 여기서 이동합니다. 좌측 상단 화살표로 메뉴를 접을 수 있습니다.' },
  { selector: '#helpToggleBtn', title: '도움말 항상 여기에', body: '어느 화면에서든 ? 버튼을 누르면 해당 페이지 설명과 투어를 다시 시작할 수 있습니다.' },
];
</script>
@endpush

@push('styles')
<style>
  /* ── Stat Cards ── 시안 카드 = radius 12 · 흰 배경 · 그림자 없음 */
  /* 격자는 인라인 style= 이 아니라 여기에 둔다 — 그래야 미디어쿼리가 우선순위 강제 없이 이긴다 */
  .stat-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: 12px; margin-bottom: 16px; }
  .stat-card {
    background: var(--gray-0);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 16px;
    display: flex; align-items: center; gap: 12px;
    cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
    /* 전역 .stat-card 가 box-shadow:var(--shadow) 를 준다 — 선언을 빼면 그게 그대로 남는다.
       시안 카드는 평평하므로 여기서 명시적으로 꺼야 한다. */
    box-shadow: none;
  }
  /* 전역 .stat-card:hover 의 그림자와 -2px 들림도 같은 이유로 명시적으로 끈다 */
  .stat-card:hover { border-color: var(--primary); color: inherit; box-shadow: none; transform: none; }
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
  .stat-val   { font-size: 16px; font-weight: 700; line-height: 19px; color: var(--gray-1000); }
  .stat-label { font-size: 12px; line-height: 18px; color: var(--gray-600); margin-top: 2px; font-weight: 500; }

  /* ── Work Queue Boxes ── */
  .queue-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 16px; }
  .queue-box {
    background: var(--gray-0); border: 1px solid var(--border); border-radius: 12px;
    padding: 16px; text-align: center; cursor: pointer;
    transition: var(--transition);
    text-decoration: none; color: inherit; display: block;
    position: relative; overflow: hidden;
  }
  /* 상단 색띠 — 개발자가 넣은 구분 표시라 남긴다. 두께만 4 로, 모서리는 카드와 같은 12 로 맞춘다 */
  .queue-box::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    border-radius: 12px 12px 0 0;
  }
  .queue-box:hover { border-color: var(--primary); color: inherit; }
  .queue-box.red::before   { background: var(--alert-500); }
  .queue-box.blue::before  { background: var(--primary-500); }
  .queue-box.green::before { background: var(--primary-700); }
  .queue-box .q-icon { font-size: 16px; line-height: 16px; margin-bottom: 6px; display: block; }
  .queue-box.red   .q-icon, .queue-box.red   .q-num { color: var(--alert-500); }
  .queue-box.blue  .q-icon, .queue-box.blue  .q-num { color: var(--primary-500); }
  .queue-box.green .q-icon, .queue-box.green .q-num { color: var(--primary-700); }
  .queue-box .q-num   { font-size: 16px; font-weight: 700; line-height: 19px; margin-bottom: 2px; }
  .queue-box .q-label { font-size: 12px; line-height: 18px; color: var(--gray-600); font-weight: 500; }

  /* ── Activity timeline (타이틀+시간 한 줄, 상세는 hover 팝오버) ── */
  .activity-item {
    display: flex; gap: 10px; align-items: center;
    padding: 8px 0; border-bottom: 1px solid var(--border-light);
    position: relative;
  }
  .activity-item:last-child { border-bottom: none; }
  .activity-dot {
    width: 4px; height: 4px; border-radius: 999px; flex-shrink: 0;
  }
  .activity-main { min-width: 0; flex: 1; }
  .activity-title {
    font-size: 13px; font-weight: 500; line-height: 21px; color: var(--gray-1000);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .activity-time { font-size: 11px; line-height: 18px; color: var(--gray-500); margin-top: 1px; white-space: nowrap; }
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

  /* ── Quick action buttons ── */
  .quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .qa-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 12px 8px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--gray-50); cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
  }
  .qa-btn:hover { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
  .qa-btn:hover .qa-icon { color: var(--primary); }
  .qa-icon { font-size: 16px; line-height: 16px; color: var(--gray-500); }
  .qa-label { font-size: 12px; line-height: 18px; font-weight: 500; color: var(--gray-800); text-align: center; }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }

  .dash-grid { display: grid; grid-template-columns: 1fr 280px; gap: 16px; }

  /* 격자를 CSS 로 옮겨서 인라인 style= 과 싸울 일이 없어졌다 — 우선순위 강제를 걷어냈다 */
  @media (max-width: 1100px) { .dash-grid { grid-template-columns: 1fr; } }
  @media (max-width: 640px)  { .queue-grid { grid-template-columns: 1fr 1fr; } .stat-grid { grid-template-columns: repeat(2,1fr); } }
</style>
@endpush

@section('content')

{{-- ── Stat Strip (6 KPIs) ── --}}
<div class="stat-grid">
  <a href="{{ route('prescriptions.index') }}" class="stat-card">
    <div class="stat-icon primary"><i class="bx bx-file-blank"></i></div>
    <div>
      <div class="stat-val">{{ $stats['total_today'] }}</div>
      <div class="stat-label">오늘 접수</div>
    </div>
  </a>
  <a href="{{ route('prescriptions.index', ['status'=>'review_needed']) }}" class="stat-card">
    <div class="stat-icon warning"><i class="bx bx-error-circle"></i></div>
    <div>
      <div class="stat-val">{{ $stats['review_needed'] }}</div>
      <div class="stat-label">검수 대기</div>
    </div>
  </a>
  <a href="{{ route('prescriptions.index', ['status'=>'approved']) }}" class="stat-card">
    <div class="stat-icon success"><i class="bx bx-check-shield"></i></div>
    <div>
      <div class="stat-val">{{ $stats['approved_today'] }}</div>
      <div class="stat-label">오늘 승인</div>
    </div>
  </a>
  <a href="{{ route('orders.index') }}" class="stat-card">
    <div class="stat-icon info"><i class="bx bx-cart-alt"></i></div>
    <div>
      <div class="stat-val">{{ $stats['orders_pending'] }}</div>
      <div class="stat-label">주문 대기</div>
    </div>
  </a>
  <a href="{{ route('nhis.index') }}" class="stat-card">
    <div class="stat-icon danger"><i class="bx bx-plus-medical"></i></div>
    <div>
      <div class="stat-val">{{ $stats['nhis_pending'] }}</div>
      <div class="stat-label">NHIS 청구대기</div>
    </div>
  </a>
  <a href="{{ route('repurchase.index') }}" class="stat-card">
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
      <a href="{{ route('prescriptions.index', ['status' => 'review_needed']) }}" class="queue-box red">
        <span class="q-icon"><i class="bx bx-error-alt"></i></span>
        <div class="q-num">{{ $stats['review_needed'] }}</div>
        <div class="q-label">검수 필요</div>
      </a>
      <a href="{{ route('prescriptions.index', ['status' => 'ocr_processing']) }}" class="queue-box blue">
        <span class="q-icon"><i class="bx bx-scan"></i></span>
        <div class="q-num">{{ $stats['ocr_processing'] }}</div>
        <div class="q-label">OCR 처리중</div>
      </a>
      <a href="{{ route('prescriptions.index', ['status' => 'approved']) }}" class="queue-box green">
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
        <a href="{{ route('prescriptions.index') }}" class="btn btn-outline btn-sm ms-auto">
          <i class="bx bx-list-ul"></i> 전체보기
        </a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <div style="margin-bottom:8px;font-size:12px;font-weight:500;line-height:18px;color:var(--gray-600);">
          <i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 처방전 상세로 이동합니다.
        </div>
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
        <div class="quick-grid">
          <a href="{{ route('prescriptions.upload') }}" class="qa-btn">
            <i class="bx bx-upload qa-icon"></i>
            <span class="qa-label">처방전<br>업로드</span>
          </a>
          <a href="{{ route('orders.index') }}" class="qa-btn">
            <i class="bx bx-clipboard qa-icon"></i>
            <span class="qa-label">주문<br>확인</span>
          </a>
          <a href="{{ route('nhis.index') }}" class="qa-btn">
            <i class="bx bx-file-blank qa-icon"></i>
            <span class="qa-label">NHIS<br>청구</span>
          </a>
          <a href="{{ route('patients.index') }}" class="qa-btn">
            <i class="bx bx-user-plus qa-icon"></i>
            <span class="qa-label">환자<br>등록</span>
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
      <div class="card-body recent-activity-body" style="padding:8px 18px;">
        @forelse($activities as $act)
        <div class="activity-item">
          <div class="activity-dot" style="background:var(--primary);"></div>
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
