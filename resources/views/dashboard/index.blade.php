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
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
(function () {
  const el = document.getElementById('recentRxGrid');
  if (!el) return;
  const RX_BASE = @json(url('prescriptions'));   // + '/{rx_number}'
  const grid = new wwGrid({
    el: el,
    height: 320, editable: false, rowCheckbox: false, rowNumber: true, toolbar: false, summary: false,
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
  // 더블클릭 → 처방전 상세 이동
  el.addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row && row.rx_number) window.location.href = RX_BASE + '/' + encodeURIComponent(row.rx_number);
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
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=4">
<style>
  /* ── Stat Cards (Vuexy style) ── */
  .stat-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    padding: 20px 22px;
    display: flex; align-items: center; gap: 18px;
    cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
  }
  .stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); color: inherit; }
  .stat-icon {
    width: 52px; height: 52px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
  }
  .stat-icon.primary  { background: var(--primary-light);  color: var(--primary); }
  .stat-icon.success  { background: var(--success-light);  color: var(--success); }
  .stat-icon.warning  { background: var(--warning-light);  color: var(--warning); }
  .stat-icon.danger   { background: var(--danger-light);   color: var(--danger); }
  .stat-icon.info     { background: var(--info-light);     color: var(--info); }
  .stat-icon.purple   { background: #f3eeff;               color: var(--purple); }
  .stat-val   { font-size: 26px; font-weight: 800; line-height: 1; color: var(--text-primary); }
  .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

  /* ── Work Queue Boxes ── */
  .queue-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 22px; }
  .queue-box {
    background: #fff; border: 1px solid var(--border); border-radius: 10px;
    padding: 18px 16px; text-align: center; cursor: pointer;
    box-shadow: var(--shadow); transition: var(--transition);
    text-decoration: none; color: inherit; display: block;
    position: relative; overflow: hidden;
  }
  .queue-box::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: 10px 10px 0 0;
  }
  .queue-box:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); color: inherit; }
  .queue-box.red::before   { background: var(--danger); }
  .queue-box.blue::before  { background: var(--primary); }
  .queue-box.green::before { background: var(--success); }
  .queue-box .q-icon { font-size: 22px; margin-bottom: 6px; display: block; }
  .queue-box.red   .q-icon, .queue-box.red   .q-num { color: var(--danger); }
  .queue-box.blue  .q-icon, .queue-box.blue  .q-num { color: var(--primary); }
  .queue-box.green .q-icon, .queue-box.green .q-num { color: var(--success); }
  .queue-box .q-num   { font-size: 32px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
  .queue-box .q-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }

  /* ── Activity timeline (타이틀+시간 한 줄, 상세는 hover 팝오버) ── */
  .activity-item {
    display: flex; gap: 10px; align-items: center;
    padding: 8px 0; border-bottom: 1px solid var(--border-light);
    position: relative;
  }
  .activity-item:last-child { border-bottom: none; }
  .activity-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
  }
  .activity-main { min-width: 0; flex: 1; }
  .activity-title {
    font-size: 13px; font-weight: 600; color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .activity-time { font-size: 11px; color: var(--text-muted); margin-top: 1px; white-space: nowrap; }
  /* 팝오버(상세 전체 내용) — 사이드바 좌측으로 열림 */
  .activity-pop {
    display: none; position: absolute; right: calc(100% + 12px); top: -6px;
    width: 320px; max-width: 320px; background: #fff; border: 1px solid var(--border);
    border-radius: 10px; box-shadow: var(--shadow-lg); padding: 12px 14px;
    font-size: 12.5px; line-height: 1.65; color: var(--text-secondary);
    white-space: normal; word-break: break-all; z-index: 200;
  }
  .activity-item:hover { background: var(--primary-light); border-radius: 6px; }
  .activity-item:hover .activity-pop { display: block; }
  /* 팝오버가 카드 밖으로 나올 수 있게 클리핑 방지 */
  .recent-activity-body { overflow: visible; }

  /* ── Quick action buttons ── */
  .quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .qa-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 14px 8px; border-radius: 10px; border: 1.5px solid var(--border);
    background: var(--bg); cursor: pointer; transition: var(--transition);
    text-decoration: none; color: inherit;
  }
  .qa-btn:hover { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
  .qa-btn:hover .qa-icon { color: var(--primary); }
  .qa-icon { font-size: 22px; color: var(--text-muted); }
  .qa-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-align: center; }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }

  @media (max-width: 1100px) { .dash-grid { grid-template-columns: 1fr !important; } }
  @media (max-width: 640px)  { .queue-grid { grid-template-columns: 1fr 1fr; } .stat-grid { grid-template-columns: repeat(2,1fr) !important; } }
</style>
@endpush

@section('content')

{{-- ── Stat Strip (6 KPIs) ── --}}
<div class="stat-grid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:22px;">
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
<div class="dash-grid" style="display:grid;grid-template-columns:1fr 280px;gap:20px;">

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
        <i class="bx bx-file-medical" style="font-size:18px;color:var(--primary);"></i>
        <span class="card-header-title">최근 처방전 현황</span>
        <a href="{{ route('prescriptions.index') }}" class="btn btn-outline btn-sm ms-auto">
          <i class="bx bx-list-ul"></i> 전체보기
        </a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <div style="margin-bottom:8px;font-size:12px;color:var(--text-muted);">
          <i class="bx bx-info-circle"></i> 행을 <b>더블클릭</b>하면 처방전 상세로 이동합니다.
        </div>
        <div id="recentRxGrid"></div>
      </div>
    </div>
  </div>

  {{-- ── RIGHT COLUMN ── --}}
  <div>

    {{-- Quick Actions --}}
    <div class="card mb-4">
      <div class="card-header">
        <i class="bx bx-zap" style="font-size:18px;color:var(--warning);"></i>
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
        <i class="bx bx-time-five" style="font-size:18px;color:var(--primary);"></i>
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
        <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px 0;">
          <i class="bx bx-time" style="font-size:28px;display:block;margin-bottom:6px;opacity:.35;"></i>
          활동 내역이 없습니다.
        </div>
        @endforelse
      </div>
    </div>

  </div>
</div>

@endsection
