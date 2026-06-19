@extends('layouts.app')

@section('title', '기관 공지사항')

@section('help-content')
  <div class="help-tip"><i class="bx bx-info-circle"></i>보건복지부·심사평가원·국민건강보험공단의 정책 공지를 자동 수집합니다. 로그인 시 당일 데이터가 없으면 자동 크롤링되며, <strong>지금 수집</strong> 버튼으로 수동 실행할 수 있습니다.</div>
@endsection

@push('styles')
<style>
  .org-ext-link { position:absolute; right:8px; top:50%; transform:translateY(-50%); font-size:12px; color:var(--text-muted); text-decoration:none; opacity:0; transition:opacity .15s; z-index:2; }
  .nav-item:hover .org-ext-link { opacity:1; }
  .org-ext-link:hover { color:var(--primary); opacity:1 !important; }
  .impact-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
  .impact-high   { background:var(--danger-light); color:var(--danger); }
  .impact-medium { background:var(--warning-light); color:#B45309; }
  .impact-low    { background:var(--border-light); color:var(--text-muted); }
  #paginationWrap { display:none; padding:10px 18px; border-top:1px solid var(--border); justify-content:space-between; align-items:center; }
  #paginationWrap.visible { display:flex; }
  .nd-modal-overlay { display:none; position:fixed; inset:0; z-index:1000; background:rgba(13,27,42,.45); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal-box { background:var(--bg-card); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); width:100%; max-width:720px; max-height:90vh; display:flex; flex-direction:column; animation:fadeUp .18s ease; }
</style>
@endpush

@section('content')

{{-- 헤더 액션 --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div>
    <div style="font-size:13px;color:var(--text-muted);">보건복지부 · 건강보험심사평가원 · 국민건강보험공단 정책 수집</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span id="crawlStatus" class="badge badge-secondary d-none">크롤링 중...</span>
    <button class="btn btn-outline btn-sm" id="btnCrawl">
      <i class="bx bx-refresh"></i> 지금 수집
    </button>
  </div>
</div>

{{-- 통계 카드 --}}
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  @foreach([
    ['org'=>'MOHW', 'label'=>'보건복지부',        'icon'=>'bx-shield-quarter', 'cls'=>'danger',  'url'=>'https://www.mohw.go.kr/board.es?mid=a10503010100&bid=0027'],
    ['org'=>'HIRA', 'label'=>'건강보험심사평가원', 'icon'=>'bx-search-alt',     'cls'=>'warning', 'url'=>'https://www.hira.or.kr/bbsDummy.do?pgmid=HIRAA020002000100'],
    ['org'=>'NHIS', 'label'=>'국민건강보험공단',   'icon'=>'bx-health',          'cls'=>'info',    'url'=>'https://www.nhis.or.kr/nhis/minwon/wbhace10210m01.do'],
  ] as $card)
  <div class="stat-card" style="cursor:default;">
    <div class="stat-icon {{ $card['cls'] }}"><i class="bx {{ $card['icon'] }}"></i></div>
    <div class="stat-info">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
        <span style="font-size:13px;font-weight:700;color:var(--text-primary);">{{ $card['label'] }}</span>
        <a href="{{ $card['url'] }}" target="_blank" style="font-size:13px;color:var(--text-muted);opacity:.6;text-decoration:none;" title="{{ $card['label'] }} 바로가기">
          <i class="bx bx-link-external"></i>
        </a>
      </div>
      <div style="display:flex;align-items:baseline;gap:4px;">
        <span class="stat-val">{{ $counts[$card['org']] ?? 0 }}</span>
        <span style="font-size:12px;color:var(--text-muted);">건</span>
      </div>
      <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
        @if(!empty($latestDates[$card['org']]))
          최근: {{ \Carbon\Carbon::parse($latestDates[$card['org']])->format('Y-m-d') }}
        @else
          수집 없음
        @endif
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- 공지 목록 카드 --}}
<div class="card">
  {{-- 탭 + 필터 --}}
  <div class="card-header" style="padding:0;flex-direction:column;gap:0;">
    <div style="display:flex;align-items:center;padding:0 18px 0 0;border-bottom:1px solid var(--border);">
      <ul class="nav-tabs" id="orgTabs" style="border:none;margin-bottom:0;">
        <li class="nav-item" style="position:relative;">
          <button class="nav-link active" data-org="MOHW" type="button" style="padding:12px 34px 12px 16px;">
            <i class="bx bx-shield-quarter" style="margin-right:5px;color:var(--danger);"></i>보건복지부
          </button>
          <a href="https://www.mohw.go.kr/board.es?mid=a10503010100&bid=0027" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
        <li class="nav-item" style="position:relative;">
          <button class="nav-link" data-org="HIRA" type="button" style="padding:12px 34px 12px 16px;">
            <i class="bx bx-search-alt" style="margin-right:5px;color:var(--warning);"></i>심사평가원
          </button>
          <a href="https://www.hira.or.kr/bbsDummy.do?pgmid=HIRAA020002000100" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
        <li class="nav-item" style="position:relative;">
          <button class="nav-link" data-org="NHIS" type="button" style="padding:12px 34px 12px 16px;">
            <i class="bx bx-health" style="margin-right:5px;color:var(--info);"></i>건강보험공단
          </button>
          <a href="https://www.nhis.or.kr/nhis/minwon/wbhace10210m01.do" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
      </ul>
      <div style="display:flex;align-items:center;gap:8px;margin-left:auto;">
        <select id="filterImpact" class="form-control form-select" style="width:120px;padding:6px 28px 6px 10px;font-size:12.5px;">
          <option value="">영향도 전체</option>
          <option value="HIGH">HIGH</option>
          <option value="MEDIUM">MEDIUM</option>
          <option value="LOW">LOW</option>
        </select>
        <div class="search-wrap">
          <i class="bx bx-search"></i>
          <input type="text" id="searchInput" class="form-control" placeholder="제목 검색..." style="width:180px;">
        </div>
        <button class="btn btn-primary btn-sm" id="btnSearch"><i class="bx bx-search"></i></button>
      </div>
    </div>
  </div>

  {{-- 로딩 --}}
  <div id="listLoading" class="d-none" style="text-align:center;padding:48px;">
    <div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div>
    <div style="margin-top:10px;font-size:13px;color:var(--text-muted);">데이터를 불러오는 중...</div>
  </div>

  {{-- 테이블 --}}
  <div class="table-wrap" id="noticeTableWrap">
    <table id="noticeTable">
      <thead>
        <tr>
          <th style="width:80px;">영향도</th>
          <th style="width:110px;">유형</th>
          <th>제목</th>
          <th style="width:70px;text-align:center;">수가</th>
          <th style="width:110px;">날짜</th>
          <th style="width:60px;"></th>
        </tr>
      </thead>
      <tbody id="noticeTableBody">
        <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--text-muted);">탭을 선택하면 목록이 표시됩니다.</td></tr>
      </tbody>
    </table>
  </div>

  {{-- 빈 상태 --}}
  <div id="emptyState" class="d-none">
    <div class="empty-state">
      <i class="bx bx-data"></i>
      <p>수집된 공지사항이 없습니다.</p>
      <button class="btn btn-primary btn-sm" id="btnCrawlEmpty">
        <i class="bx bx-refresh"></i> 지금 수집 시작
      </button>
    </div>
  </div>

  {{-- 페이지네이션 --}}
  <div id="paginationWrap">
    <span style="font-size:12px;color:var(--text-muted);" id="paginationInfo"></span>
    <div id="paginationBtns" style="display:flex;gap:4px;"></div>
  </div>
</div>

{{-- 상세 모달 --}}
<div class="nd-modal-overlay" id="noticeDetailModal">
  <div class="nd-modal-box">
    <div class="modal-hd">
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
          <span id="modalOrg" class="badge badge-secondary"></span>
          <span id="modalType" class="badge badge-secondary" style="background:var(--border-light);color:var(--text-muted);"></span>
          <span id="modalImpact" class="impact-badge"></span>
          <span id="modalFee" class="badge badge-warning d-none">수가연동</span>
        </div>
        <div class="modal-title" id="modalTitle"></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:3px;" id="modalDate"></div>
      </div>
      <button class="modal-close" onclick="document.getElementById('noticeDetailModal').classList.remove('open')">
        <i class="bx bx-x"></i>
      </button>
    </div>
    <div class="modal-bd">
      <div id="modalContentLoading" style="text-align:center;padding:32px;">
        <div class="spinner-border" style="color:var(--primary);width:1.5rem;height:1.5rem;"></div>
        <div style="margin-top:10px;font-size:13px;color:var(--text-muted);">내용 불러오는 중...</div>
      </div>
      <div id="modalContent" class="d-none">
        <p id="modalContentText" style="font-size:13.5px;line-height:1.85;white-space:pre-wrap;word-break:break-word;max-height:380px;overflow-y:auto;color:var(--text-primary);"></p>
        <div id="modalAttachments" class="d-none" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
          <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;">
            <i class="bx bx-paperclip"></i> 첨부파일
          </div>
          <ul id="modalAttachList" style="list-style:none;padding:0;margin:0;font-size:13px;"></ul>
        </div>
      </div>
    </div>
    <div class="modal-ft">
      <a id="modalLink" href="#" target="_blank" class="btn btn-outline btn-sm">
        <i class="bx bx-link-external"></i> 원문 보기
      </a>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('noticeDetailModal').classList.remove('open')">닫기</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const LIST_URL   = '{{ route("institutional-notices.list") }}';
  const SHOW_URL   = '{{ route("institutional-notices.show", 0) }}'.replace('/0', '/');
  const CRAWL_URL  = '{{ route("institutional-notices.crawl") }}';
  const CHECK_URL  = '{{ route("institutional-notices.checkToday") }}';

  let currentOrg  = 'MOHW';
  let currentPage = 1;
  let totalPages  = 1;

  const ORG_LABELS = { MOHW: '보건복지부', HIRA: '심사평가원', NHIS: '건강보험공단' };
  const ORG_COLORS = { MOHW: 'danger', HIRA: 'warning', NHIS: 'info' };
  const IMPACT_COLORS = { HIGH: 'danger', MEDIUM: 'warning', LOW: 'secondary' };

  // ── 탭 클릭 ──
  document.querySelectorAll('#orgTabs .nav-link').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#orgTabs .nav-link').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentOrg  = btn.dataset.org;
      currentPage = 1;
      loadList();
    });
  });

  // ── 검색 ──
  document.getElementById('btnSearch').addEventListener('click', () => { currentPage = 1; loadList(); });
  document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadList(); } });
  document.getElementById('filterImpact').addEventListener('change', () => { currentPage = 1; loadList(); });

  // ── 크롤링 ──
  function startCrawl() {
    const status = document.getElementById('crawlStatus');
    status.textContent = '크롤링 중...';
    status.className = 'badge bg-warning text-dark';
    status.classList.remove('d-none');
    document.getElementById('btnCrawl').disabled = true;

    fetch(CRAWL_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        status.textContent = data.message ?? '완료';
        status.className = 'badge bg-success';
        setTimeout(() => { status.classList.add('d-none'); }, 4000);
        loadList();
      })
      .catch(() => {
        status.textContent = '수집 실패';
        status.className = 'badge bg-danger';
      })
      .finally(() => { document.getElementById('btnCrawl').disabled = false; });
  }

  document.getElementById('btnCrawl').addEventListener('click', startCrawl);
  document.getElementById('btnCrawlEmpty')?.addEventListener('click', startCrawl);

  // ── 목록 로드 ──
  function loadList() {
    const tbody   = document.getElementById('noticeTableBody');
    const loading = document.getElementById('listLoading');
    const empty   = document.getElementById('emptyState');
    const pWrap   = document.getElementById('paginationWrap');

    tbody.innerHTML = '';
    loading.classList.remove('d-none');
    empty.classList.add('d-none');
    pWrap.style.display = 'none';

    const q      = document.getElementById('searchInput').value.trim();
    const impact = document.getElementById('filterImpact').value;

    const params = new URLSearchParams({ org: currentOrg, page: currentPage });
    if (q)      params.set('q', q);
    if (impact) params.set('impact', impact);

    fetch(`${LIST_URL}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(res => {
        loading.classList.add('d-none');
        const items = res.data ?? [];
        totalPages = res.last_page ?? 1;

        if (!items.length) {
          empty.classList.remove('d-none');
          return;
        }

        const IMPACT_CLASS = { HIGH: 'impact-high', MEDIUM: 'impact-medium', LOW: 'impact-low' };
        items.forEach(item => {
          const impactCls = IMPACT_CLASS[item.policy_impact] ?? '';
          const date = item.notice_date ? item.notice_date.substr(0, 10) : '-';
          const feeTag = item.fee_impact
            ? '<span class="badge badge-warning" style="font-size:10px;">수가</span>'
            : '';

          const tr = document.createElement('tr');
          tr.style.cursor = 'pointer';
          tr.innerHTML = `
            <td><span class="impact-badge ${impactCls}">${item.policy_impact}</span></td>
            <td style="font-size:12px;color:var(--text-muted);">${escHtml(item.notice_type ?? '-')}</td>
            <td style="font-weight:500;font-size:13px;">${escHtml(item.title)}</td>
            <td style="text-align:center;">${feeTag}</td>
            <td style="font-size:12px;color:var(--text-muted);">${date}</td>
            <td><button class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:12px;" data-id="${item.id}">보기</button></td>
          `;
          tr.addEventListener('click', () => openDetail(item.id));
          tbody.appendChild(tr);
        });

        renderPagination(res.total, res.current_page, res.last_page);
      })
      .catch(err => {
        loading.classList.add('d-none');
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">오류: ${err.message}</td></tr>`;
      });
  }

  // ── 페이지네이션 ──
  function renderPagination(total, cur, last) {
    const wrap = document.getElementById('paginationWrap');
    const info = document.getElementById('paginationInfo');
    const btns = document.getElementById('paginationBtns');

    info.textContent = `총 ${total}건`;
    btns.innerHTML = '';

    if (last <= 1) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';

    const addBtn = (label, page, disabled, active) => {
      const b = document.createElement('button');
      b.className = `btn btn-sm ${active ? 'btn-primary' : 'btn-outline'}`;
      b.textContent = label;
      b.disabled = disabled;
      if (!disabled) b.addEventListener('click', () => { currentPage = page; loadList(); });
      btns.appendChild(b);
    };

    addBtn('«', 1, cur === 1, false);
    addBtn('‹', cur - 1, cur === 1, false);

    const start = Math.max(1, cur - 2);
    const end   = Math.min(last, cur + 2);
    for (let p = start; p <= end; p++) addBtn(p, p, false, p === cur);

    addBtn('›', cur + 1, cur === last, false);
    addBtn('»', last, cur === last, false);
  }

  // ── 상세 모달 ──
  function openDetail(id) {
    document.getElementById('noticeDetailModal').classList.add('open');

    document.getElementById('modalTitle').textContent = '불러오는 중...';
    document.getElementById('modalContentLoading').classList.remove('d-none');
    document.getElementById('modalContent').classList.add('d-none');
    document.getElementById('modalContentText').textContent = '';
    document.getElementById('modalAttachments').classList.add('d-none');

    fetch(`${SHOW_URL}${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(item => {
        const impactColor = IMPACT_COLORS[item.policy_impact] ?? 'secondary';
        const orgColor    = ORG_COLORS[item.source_org] ?? 'secondary';

        document.getElementById('modalTitle').textContent = item.title;
        document.getElementById('modalDate').textContent  = item.notice_date ? item.notice_date.substr(0, 10) : '';
        const ORG_BADGE_CLS = { danger: 'badge badge-danger', warning: 'badge badge-warning', info: 'badge badge-info' };
        document.getElementById('modalOrg').textContent   = ORG_LABELS[item.source_org] ?? item.source_org;
        document.getElementById('modalOrg').className     = ORG_BADGE_CLS[orgColor] ?? 'badge badge-secondary';
        document.getElementById('modalType').textContent  = item.notice_type ?? '';
        document.getElementById('modalImpact').textContent = item.policy_impact;
        document.getElementById('modalImpact').className   = `impact-badge ${IMPACT_CLASS[item.policy_impact] ?? ''}`;
        document.getElementById('modalLink').href          = item.url;

        const feeEl = document.getElementById('modalFee');
        item.fee_impact ? feeEl.classList.remove('d-none') : feeEl.classList.add('d-none');

        document.getElementById('modalContentLoading').classList.add('d-none');
        document.getElementById('modalContent').classList.remove('d-none');
        document.getElementById('modalContentText').textContent = item.content || '내용을 가져올 수 없습니다. 원문 보기를 이용해 주세요.';

        if (item.attachments && item.attachments.length) {
          const ul = document.getElementById('modalAttachList');
          ul.innerHTML = item.attachments.map(a =>
            `<li><a href="${escHtml(a.url)}" target="_blank"><i class="bx bx-file me-1"></i>${escHtml(a.name)}</a></li>`
          ).join('');
          document.getElementById('modalAttachments').classList.remove('d-none');
        }
      })
      .catch(err => {
        document.getElementById('modalContentLoading').classList.add('d-none');
        document.getElementById('modalContent').classList.remove('d-none');
        document.getElementById('modalContentText').textContent = '오류: ' + err.message;
      });
  }

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ── 초기 로드 ──
  loadList();

  // ── 오늘 크롤링 체크 후 자동 실행 (대시보드 진입 시 실행됨) ──
  // (로그인 시 AuthController에서도 처리)

})();
</script>
@endpush
