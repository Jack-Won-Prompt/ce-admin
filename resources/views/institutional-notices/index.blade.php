@extends('layouts.app')

@section('title', '기관 공지사항')
{{-- page-title 이 없어 헤더에 기본값 '대시보드' 가 걸려 있었다 — 어느 화면에 있는지
     헤더만 보면 알 수 없었다. 사이드바·시안 사이드바(174:955)와 같은 이름으로 세운다. --}}
@section('page-title', '기관 공지사항')
{{-- 시안 391:332 은 「홈 - 기관 공지사항」 두 마디다. 같은 「지원」 묶음 형제 넷
     (391:7089 공지사항 · 391:7779 · 391:8218 · 391:9030 문의하기)도 전부 두 마디고,
     시안 사이드바가 지원 아래 두면서도 빵부스러기에는 지원을 넣지 않는다.
     어제 이 줄을 세 마디로 넣은 것을 바로잡는다. --}}
@section('breadcrumb', '홈 - 기관 공지사항')

@section('help-content')
  <div class="help-tip"><i class="bx bx-info-circle"></i>보건복지부·심사평가원·국민건강보험공단의 정책 공지를 자동 수집합니다. 로그인 시 당일 데이터가 없으면 자동 크롤링되며, <strong>지금 수집</strong> 버튼으로 수동 실행할 수 있습니다.</div>
@endsection

@push('styles')
<style>
  .org-ext-link { position:absolute; right:8px; top:50%; transform:translateY(-50%); font-size:12px; color:var(--text-muted); text-decoration:none; opacity:0; transition:opacity .15s; z-index:2; }
  .nav-item:hover .org-ext-link { opacity:1; }
  /* 바로 위 .nav-item:hover 규칙과 특정성이 같고 뒤에 있어 우선순위 표시 없이도 이긴다 */
  .org-ext-link:hover { color:var(--primary); opacity:1; }
  .impact-badge { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }
  /* 영향도 3단계 — 시안은 상태색을 alert 램프와 primary 램프 둘로만 그린다.
     HIGH=alert · MEDIUM=primary · LOW=회색. (기존 MEDIUM 의 주황 계열 글자색은 시안에 없다) */
  .impact-high   { background:var(--alert-50);   color:var(--alert-500); }
  .impact-medium { background:var(--primary-50); color:var(--primary-600); }
  .impact-low    { background:var(--gray-100);   color:var(--gray-600); }
  /* 기관 탭 — 부트스트랩 CDN 의 .nav-tabs .nav-link(0,2,0) · .nav-tabs .nav-link.active(0,3,0) 가
     전역 .nav-link(0,1,0) · .nav-link.active(0,2,0) 를 이겨, 활성 탭이 회색 테두리 박스(r6)에
     검정 글자 + 흰 밑줄로 그려진다. 시안 탭 활성 표시는 밑줄 1px primary 다. #orgTabs 로 이긴다. */
  #orgTabs .nav-link { border:none; border-bottom:1px solid transparent; border-radius:0; background:transparent; color:var(--text-muted); margin-bottom:-1px; }
  #orgTabs .nav-link:hover  { color:var(--primary); background:transparent; }
  #orgTabs .nav-link.active { color:var(--primary); background:transparent; border-bottom-color:var(--primary); }
  .nd-modal-overlay { display:none; position:fixed; inset:0; z-index:1000; background:rgba(13,27,42,.45); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px; }
  .nd-modal-overlay.open { display:flex; }
  .nd-modal-box { background:var(--bg-card); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); width:100%; max-width:720px; max-height:90vh; display:flex; flex-direction:column; animation:fadeUp .18s ease; }
</style>
@endpush

@section('content')

{{-- 헤더 액션 — 블록 사이 간격은 .page-body 의 gap 12 가 만든다(margin 중복 제거) --}}
<div style="display:flex;align-items:center;justify-content:space-between;">
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
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
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
      <div style="font-size:11px;font-weight:500;line-height:18px;color:var(--gray-500);margin-top:2px;">
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

{{-- 검색 필터 — 표준 필터 카드(r12 · pad 12/16). 검색은 JS 가 처리해 form 이 아니다.
     기존 카드 헤더 안에 있던 영향도·검색어·검색 버튼을 id 그대로 옮겨 왔다. --}}
<div class="ds-filter-card">
  <div class="ds-filter-fields">
    {{-- 1열(1280 뷰포트에서 78.5px)이면 '영향도 전체'(약 63px)가 잘린다 — 2열로 준다 --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">영향도</label>
      <select id="filterImpact" class="form-control form-select">
        <option value="">영향도 전체</option>
        <option value="HIGH">HIGH</option>
        <option value="MEDIUM">MEDIUM</option>
        <option value="LOW">LOW</option>
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" id="searchInput" class="form-control" placeholder="제목 검색...">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    <button type="button" class="ds-btn ds-btn-primary" id="btnSearch"><i class="bx bx-search"></i> 검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="institutionalNoticeViewDetail()">
      <i class="bx bx-detail"></i> 선택 상세
    </button>
    <button type="button" class="ds-btn" onclick="window.__noticeGrid?.downloadExcel()">엑셀 저장</button>
  </div>
</div>

{{-- 결과바(h32) + 공지 목록 카드(r12) --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 <b id="noticeTotalBadge">0</b>건)</span></button>
      </div>
    {{-- 기관 탭 — 카드 안 상단, 탭 높이 44 --}}
    <div style="display:flex;align-items:center;border-bottom:1px solid var(--border);flex-shrink:0;">
      <ul class="nav-tabs" id="orgTabs" style="border:none;margin-bottom:0;">
        <li class="nav-item" style="position:relative;">
          <button class="nav-link active" data-org="MOHW" type="button" style="height:44px;padding:0 34px 0 16px;">
            <i class="bx bx-shield-quarter" style="margin-right:5px;color:var(--alert-500);"></i>보건복지부
          </button>
          <a href="https://www.mohw.go.kr/board.es?mid=a10503010100&bid=0027" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
        <li class="nav-item" style="position:relative;">
          <button class="nav-link" data-org="HIRA" type="button" style="height:44px;padding:0 34px 0 16px;">
            <i class="bx bx-search-alt" style="margin-right:5px;color:var(--primary-700);"></i>심사평가원
          </button>
          <a href="https://www.hira.or.kr/bbsDummy.do?pgmid=HIRAA020002000100" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
        <li class="nav-item" style="position:relative;">
          <button class="nav-link" data-org="NHIS" type="button" style="height:44px;padding:0 34px 0 16px;">
            <i class="bx bx-health" style="margin-right:5px;color:var(--primary-400);"></i>건강보험공단
          </button>
          <a href="https://www.nhis.or.kr/nhis/minwon/wbhace10210m01.do" target="_blank"
             onclick="event.stopPropagation()" class="org-ext-link">
            <i class="bx bx-link-external"></i>
          </a>
        </li>
      </ul>
    </div>

    {{-- 로딩 --}}
    <div id="listLoading" class="d-none" style="text-align:center;padding:48px;">
      <div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div>
      <div style="margin-top:10px;font-size:13px;color:var(--text-muted);">데이터를 불러오는 중...</div>
    </div>

    {{-- 목록 (wwGrid) --}}
    <div id="noticeGridWrap" style="padding:12px 16px;display:none;">
      <div id="noticeGrid"></div>
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
        <p id="modalContentText" style="font-size:13px;line-height:22px;white-space:pre-wrap;word-break:break-word;max-height:380px;overflow-y:auto;color:var(--text-primary);"></p>
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

  const ORG_LABELS = { MOHW: '보건복지부', HIRA: '심사평가원', NHIS: '건강보험공단' };
  const ORG_COLORS = { MOHW: 'danger', HIRA: 'warning', NHIS: 'info' };
  const IMPACT_COLORS = { HIGH: 'danger', MEDIUM: 'warning', LOW: 'secondary' };
  const IMPACT_CLASS  = { HIGH: 'impact-high', MEDIUM: 'impact-medium', LOW: 'impact-low' };

  // ── wwGrid 초기화 ──
  const grid = new wwGrid({
    el: document.getElementById('noticeGrid'),
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: false,
    columns: [
      { header: '영향도', name: 'policy_impact', width: 90,  align: 'center', sortable: true },
      { header: '유형',   name: 'notice_type',  width: 130, sortable: true },
      { header: '제목',   name: 'title',        width: 460 },
      { header: '수가',   name: 'fee',          width: 80,  align: 'center', sortable: true },
      { header: '날짜',   name: 'notice_date',  width: 120, align: 'center', sortable: true },
    ],
    data: [],
  });
  window.__noticeGrid = grid;                      // 결과바의 엑셀 저장 버튼이 이걸 부른다
  window.dsBindSelCount(grid, 'noticeSelCount');   // 결과바 '선택 N건' 표시를 연결한다

  window.institutionalNoticeViewDetail = function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('상세를 볼 공지를 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    openDetail(c[0].id);
  };

  // ── 탭 클릭 ──
  document.querySelectorAll('#orgTabs .nav-link').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#orgTabs .nav-link').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentOrg = btn.dataset.org;
      loadList();
    });
  });

  // ── 검색 ──
  document.getElementById('btnSearch').addEventListener('click', loadList);
  document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') loadList(); });
  document.getElementById('filterImpact').addEventListener('change', loadList);

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

  // ── 목록 로드 (wwGrid) ──
  function loadList() {
    const loading  = document.getElementById('listLoading');
    const empty    = document.getElementById('emptyState');
    const gridWrap = document.getElementById('noticeGridWrap');

    loading.classList.remove('d-none');
    empty.classList.add('d-none');
    gridWrap.style.display = 'none';

    const q      = document.getElementById('searchInput').value.trim();
    const impact = document.getElementById('filterImpact').value;

    const params = new URLSearchParams({ org: currentOrg });
    if (q)      params.set('q', q);
    if (impact) params.set('impact', impact);

    fetch(`${LIST_URL}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(res => {
        loading.classList.add('d-none');
        const items = res.data ?? [];
        grid.setData(items);
        document.getElementById('noticeTotalBadge').textContent = (res.total ?? items.length);

        if (!items.length) {
          empty.classList.remove('d-none');
          gridWrap.style.display = 'none';
        } else {
          gridWrap.style.display = '';
        }
      })
      .catch(err => {
        loading.classList.add('d-none');
        grid.setData([]);
        empty.classList.remove('d-none');
        showToast('오류: ' + err.message, 'error');
      });
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
