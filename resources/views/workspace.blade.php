@extends('layouts.app')

@section('title', '워크스페이스')
@section('page-title', 'CE Admin')
@section('breadcrumb', '홈')

@push('styles')
<style>
  /* 탭 워크스페이스가 콘텐츠 영역(네비 아래)을 가득 채우도록 page-body 패딩을 상쇄한다.
     예전에는 -14px -24px -20px 로 값을 박아 뒀는데, 본문 패딩이 바뀌자 위로 14px 올라가
     탭바가 고정 헤더에 가려졌다. 패딩과 같은 토큰에서 계산해 다시 어긋나지 않게 한다. */
  /* 탭바와 내용 사이는 12 — 시안 컨테이너(148:1475)의 gap 과 같다. */
  #wsRoot { display:flex; flex-direction:column; gap:12px; height:calc(100vh - var(--nav-h));
            margin:0 calc(var(--content-pad) * -1) calc(var(--content-pad) * -1); background:var(--bg); }
  /* 화면 탭 — 시안 207:1222.
     탭바는 흰 바탕에 radius 8, 위쪽만 2px 띄운다. 탭끼리는 붙어 있고 아래 선은 없다.
     고른 탭은 테두리나 밑줄 대신 바탕색(회색)으로 표시해, 아래 본문과 이어져 보인다. */
  .ws-tabs { display:flex; gap:0; align-items:flex-end; background:var(--gray-0); border-radius:8px;
    padding:2px 0 0; overflow-x:auto; flex-shrink:0; }
  .ws-tabs::-webkit-scrollbar { height:6px; }
  .ws-tab { display:inline-flex; align-items:center; justify-content:center; gap:4px; padding:6px 12px;
    font-size:13px; font-weight:500; line-height:1.6; color:var(--gray-600);
    background:transparent; border:none; border-radius:8px 8px 0 0;
    cursor:pointer; white-space:nowrap; max-width:220px; user-select:none; }
  .ws-tab:hover { color:var(--primary); }
  .ws-tab.active { color:var(--primary); font-weight:700; background:var(--gray-100); }
  .ws-tab .ws-tab-label { overflow:hidden; text-overflow:ellipsis; }
  .ws-tab .ws-tab-close { display:inline-flex; align-items:center; justify-content:center;
    width:12px; height:12px; border:none; background:none; cursor:pointer; color:var(--gray-500); font-size:12px;
    line-height:1; padding:0; border-radius:6px; flex-shrink:0; }
  .ws-tab .ws-tab-close:hover { background:var(--danger-light); color:var(--danger); }
  .ws-frames { position:relative; flex:1; min-height:0; background:#fff; }
  .ws-frames iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
</style>
@endpush

@section('content')
<div id="wsRoot">
  <div id="wsTabs" class="ws-tabs" role="tablist"></div>
  <div id="wsFrames" class="ws-frames"></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const HOME  = { url: @json(url('dashboard')) + '?frame=1', title: '대시보드', icon: 'home-04' };

  /* 탭에는 아이콘을 넣지 않는다 — 시안(207:1222)은 이름과 닫기 x 만 둔다.
     탭 이름이 '주문 - RX-…' 형태라 아이콘 없이도 무엇인지 알 수 있고,
     아이콘을 붙이면 좁은 폭에서 이름이 먼저 잘린다.
     openTab 은 icon 인자를 그대로 받아 둔다 — 부르는 쪽(layouts.app)이 넘기고 있고,
     나중에 되살릴 때 호출부를 다시 손대지 않기 위해서다. */
  const tabsEl   = document.getElementById('wsTabs');
  const framesEl = document.getElementById('wsFrames');
  const tabs = [];        // { id, url, title, icon, home }
  let active = null, seq = 0;

  const base = u => u.split('#')[0];
  const esc  = s => String(s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
  const stripFrame = u => u.replace(/[?&]frame=1\b/, '').replace(/\?$/, '');

  function render() {
    tabsEl.innerHTML = '';
    tabs.forEach(t => {
      const el = document.createElement('div');
      el.className = 'ws-tab' + (t.id === active ? ' active' : '');
      el.setAttribute('role', 'tab');
      el.innerHTML =
        '<span class="ws-tab-label">' + esc(t.title) + '</span>' +
        (t.home ? '' : '<button class="ws-tab-close" title="닫기">&times;</button>');
      el.addEventListener('click', e => { if (e.target.closest('.ws-tab-close')) return; activate(t.id); });
      const cl = el.querySelector('.ws-tab-close');
      if (cl) cl.addEventListener('click', e => { e.stopPropagation(); closeTab(t.id); });
      tabsEl.appendChild(el);
    });
    tabs.forEach(t => { const f = document.getElementById('wsF-' + t.id); if (f) f.style.display = (t.id === active ? 'block' : 'none'); });
    highlightMenu();
  }

  /* 액자는 그 탭을 볼 때 붙인다.
     새로 고치며 다섯 탭을 되살리면 다섯 화면이 한꺼번에 서버를 부른다 — 처방전 그림에
     주문 목록에 이력까지라, 첫 화면이 뜨는 데 한참 걸렸다. 보는 것 하나만 붙이고
     나머지는 눌렀을 때 붙인다. */
  function mount(t) {
    if (!t || document.getElementById('wsF-' + t.id)) return;
    const f = document.createElement('iframe');
    f.id = 'wsF-' + t.id; f.src = t.url; f.loading = 'lazy';
    framesEl.appendChild(f);
  }

  function openTab(url, title, icon, isHome) {
    const hit = tabs.find(t => base(t.url) === base(url));
    if (hit) { activate(hit.id); return; }
    const id = ++seq;
    const t  = { id, url, title, icon, home: !!isHome };
    tabs.push(t);
    mount(t);
    active = id;
    render();
    save();
  }
  function activate(id) {
    active = id;
    mount(tabs.find(t => t.id === id));
    render();
    save();
  }
  function closeTab(id) {
    const i = tabs.findIndex(t => t.id === id);
    if (i < 0 || tabs[i].home) return;
    const wasActive = active === id;
    const f = document.getElementById('wsF-' + id); if (f) f.remove();
    tabs.splice(i, 1);
    if (wasActive && tabs.length) active = (tabs[Math.max(0, i - 1)] || tabs[0]).id;
    mount(tabs.find(t => t.id === active));
    render();
    save();
  }
  function highlightMenu() {
    const t = tabs.find(x => x.id === active);
    const cur = t ? stripFrame(base(t.url)) : '';
    document.querySelectorAll('.layout-menu .menu-item').forEach(mi => {
      const a = mi.querySelector('a.menu-link');
      const href = a && a.getAttribute('href');
      mi.classList.toggle('active', !!href && cur.endsWith(new URL(href, location.origin).pathname));
    });
    // 활성 항목이 접힌 메뉴 그룹에 가려지지 않도록 그룹 상태를 다시 맞춘다
    if (typeof window.syncMenuGroupsActive === 'function') window.syncMenuGroupsActive();
  }

  /* 프레임 안(각 탭)에서 '새 탭으로 열기' 요청 수신.
     예: 처방전 목록에서 행을 더블클릭 → 검수 화면을 현재 탭에서 전환하지 않고 새 탭으로 띄운다.
     window.ceOpenTab(url, title, icon) 이 layouts.app 에서 이 메시지를 보낸다. */
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin) return;                 // 동일 출처만 허용
    const d = e.data;
    if (!d || d.source !== 'ce-workspace' || d.action !== 'open-tab') return;

    // 같은 출처의 절대경로로 정규화 (외부 URL 주입 방지)
    let target;
    try { target = new URL(String(d.url ?? ''), location.origin); } catch (_) { return; }
    if (target.origin !== location.origin) return;
    target.searchParams.set('frame', '1');

    openTab(target.pathname + target.search, String(d.title || '새 탭'), String(d.icon || ''), false);
  });

  // 사이드바 메뉴 클릭 → 탭으로 열기(페이지 이동 대신)
  document.addEventListener('click', function (e) {
    const a = e.target.closest('.layout-menu a.menu-link');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript')) return;
    e.preventDefault();
    const url = href + (href.indexOf('?') > -1 ? '&' : '?') + 'frame=1';
    openTab(url, a.dataset.title || a.textContent.trim(), a.dataset.icon || '', false);
  });

  /* ── 펴 둔 것을 기억한다 ─────────────────────────────────
     브라우저 탭마다 따로 둔다(sessionStorage). 두 창을 띄워 서로 다른 건을 보는
     사람이 있어, 한 곳에 모아 두면 서로의 자리를 덮는다. 창을 닫으면 함께 사라진다.
     사사로운 창(시크릿ㆍ쿠키 막음)에서는 저장이 막힐 수 있다 — 막히면 그냥 지나간다. */
  const STORE = 'ce-ws-tabs';
  const MAX   = 20;

  function save() {
    try {
      const i = tabs.findIndex(t => t.id === active);
      sessionStorage.setItem(STORE, JSON.stringify({
        v: 1,
        active: i < 0 ? 0 : i,
        tabs: tabs.slice(0, MAX).map(t => ({ url: t.url, title: t.title, icon: t.icon, home: !!t.home })),
      }));
    } catch (_) { /* 못 적어도 하던 일은 그대로다 */ }
  }

  /* 되살릴 때는 적어 둔 주소를 믿지 않는다. 남이 심어 둘 수 있는 자리라(같은 브라우저의
     다른 화면이 쓰는 저장소다), 우리 자리의 상대 주소만 받는다 —
     「//다른곳」이나 「javascript:」 같은 것은 걷어 낸다. */
  function restore() {
    let d;
    try { d = JSON.parse(sessionStorage.getItem(STORE) || 'null'); } catch (_) { return false; }
    if (!d || d.v !== 1 || !Array.isArray(d.tabs) || !d.tabs.length) return false;

    const safe = d.tabs.filter(t => typeof t?.url === 'string'
                                 && t.url.startsWith('/')
                                 && !t.url.startsWith('//'));
    if (!safe.length) return false;

    /* 대시보드는 늘 첫 자리에 있어야 한다 — 닫을 수 없는 탭이라, 없이 되살리면
       돌아갈 곳이 사라진다. */
    if (!safe.some(t => t.home)) safe.unshift({ url: HOME.url, title: HOME.title, icon: HOME.icon, home: true });

    safe.slice(0, MAX).forEach(t => {
      tabs.push({ id: ++seq, url: t.url, title: String(t.title || '새 탭'),
                  icon: String(t.icon || ''), home: !!t.home });
    });

    const cur = tabs[Math.min(Math.max(0, d.active | 0), tabs.length - 1)] || tabs[0];
    active = cur.id;
    mount(cur);           // 보는 것 하나만 붙인다 — 나머지는 눌렀을 때
    render();
    return true;
  }

  // 되살릴 것이 없으면 대시보드 하나로 시작한다
  if (!restore()) openTab(HOME.url, HOME.title, HOME.icon, true);
})();
</script>
@endpush
