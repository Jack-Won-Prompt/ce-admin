@extends('layouts.app')

@section('title', '시스템 모니터링')
@section('page-title', '시스템 모니터링')
@section('breadcrumb', '홈 - 설정 - 시스템 모니터링')

@section('content')

{{-- 기계ㆍ웹ㆍDBㆍAWS 과금을 한 화면에서 본다 (2026-10-02 지시).

     ## 빈 칸에 그럴듯한 숫자를 넣지 않는다

     감시 화면이 거짓을 보이면 없는 것보다 나쁘다 — 사람이 그것을 믿고 판단한다.
     AWS 권한이 없어 못 읽은 칸은 「권한 없음」과 **필요한 권한 이름**을 적는다.

     ## 어디서 온 값인지 칸마다 적는다

     같은 CPU 가 이 기계에서 잰 것인지 CloudWatch 가 준 것인지에 따라 뜻이 다르다
     (CloudWatch 는 1~5분 묵은 값이다). 꼬리말에 자리를 적어 둔다. --}}

@push('styles')
<style>
  .mon-grid   { display:grid; gap:14px; }
  .mon-4      { grid-template-columns: repeat(4, minmax(0,1fr)); }
  .mon-2      { grid-template-columns: repeat(2, minmax(0,1fr)); }
  @media (max-width: 1100px) { .mon-4 { grid-template-columns: repeat(2, minmax(0,1fr)); }
                               .mon-2 { grid-template-columns: 1fr; } }
  @media (max-width: 620px)  { .mon-4 { grid-template-columns: 1fr; } }

  .mon-card   { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg);
                padding:14px 16px; display:flex; flex-direction:column; gap:8px; min-width:0; }
  .mon-head   { display:flex; align-items:center; gap:8px; font-size:12px; font-weight:700; color:var(--text-muted); }
  .mon-head .sp { margin-left:auto; }
  .mon-big    { font-size:26px; font-weight:800; line-height:1.1; color:var(--text-main); letter-spacing:-.5px; }
  .mon-big .u { font-size:14px; font-weight:700; color:var(--text-muted); margin-left:2px; }
  .mon-sub    { font-size:11.5px; color:var(--text-muted); line-height:1.6; }

  .mon-bar    { height:6px; border-radius:999px; background:var(--gray-100); overflow:hidden; }
  .mon-bar > i { display:block; height:100%; border-radius:999px; background:var(--primary); transition:width .35s ease; }
  .mon-bar.warn > i { background:var(--warning); }
  .mon-bar.bad  > i { background:var(--danger); }

  .mon-dot    { width:8px; height:8px; border-radius:999px; flex:none; background:var(--success, #22c55e); }
  .mon-dot.warn { background:var(--warning); }
  .mon-dot.bad  { background:var(--danger); }

  .mon-chip   { display:inline-flex; align-items:center; gap:5px; height:22px; padding:0 9px; border-radius:999px;
                font-size:11px; font-weight:700; background:var(--gray-100); color:var(--gray-700); }
  .mon-chip.ok   { background:var(--primary-50); color:var(--primary); }
  .mon-chip.warn { background:var(--warning-light, #FFF4E5); color:var(--warning); }
  .mon-chip.bad  { background:var(--danger-light); color:var(--danger); }
  .mon-chip.muted{ background:var(--gray-100); color:var(--text-muted); }
  .mon-chip.success { background:var(--primary-50); color:var(--primary); }
  .mon-chip.danger  { background:var(--danger-light); color:var(--danger); }

  /* 두 줄짜리 수치표 — RDS·웹 칸 */
  .mon-rows   { display:flex; flex-direction:column; gap:7px; }
  .mon-row    { display:flex; align-items:baseline; gap:8px; font-size:12.5px; }
  .mon-row .k { color:var(--text-muted); }
  .mon-row .v { margin-left:auto; font-weight:700; color:var(--text-main); font-variant-numeric:tabular-nums; }
  .mon-row .v.na { font-weight:600; color:var(--text-muted); font-size:11.5px; }

  .mon-wrap   { overflow-x:auto; }
  .mon-tbl    { width:100%; border-collapse:collapse; font-size:12px; min-width:560px; }
  .mon-tbl th { text-align:left; font-weight:700; color:var(--text-muted); font-size:11px;
                padding:6px 8px; border-bottom:1px solid var(--border); white-space:nowrap; }
  .mon-tbl td { padding:7px 8px; border-bottom:1px solid var(--gray-100); color:var(--text-main); }
  .mon-tbl td.num { text-align:right; font-variant-numeric:tabular-nums; }
  .mon-tbl tr:last-child td { border-bottom:none; }
  .mon-tbl .path { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:11.5px; word-break:break-all; }

  .mon-note   { font-size:11.5px; color:var(--text-muted); background:var(--gray-50);
                border:1px solid var(--gray-200); border-radius:8px; padding:9px 11px; line-height:1.7; }
  .mon-note code { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:11px;
                   background:var(--bg-card); border:1px solid var(--border); border-radius:4px; padding:1px 4px; }

  .mon-deny   { display:flex; align-items:center; gap:7px; font-size:11.5px; color:var(--danger); }

  /* 그래프 — 라이브러리를 더 들이지 않고 SVG 로 그린다 */
  .mon-chart  { width:100%; height:170px; display:block; }
  .mon-legend { display:flex; gap:12px; font-size:11px; color:var(--text-muted); }
  .mon-legend i { display:inline-block; width:9px; height:3px; border-radius:2px; margin-right:4px;
                  vertical-align:middle; }
</style>
@endpush

<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
  <span class="mon-chip" id="monHealth"><span class="mon-dot"></span> 조회 중</span>
  <span class="mon-sub" id="monAt">—</span>
  <span style="margin-left:auto; display:flex; gap:8px; align-items:center;">
    <select id="monHours" class="form-control form-select" style="width:120px; height:32px; font-size:12px;">
      <option value="3">최근 3시간</option>
      <option value="12" selected>최근 12시간</option>
      <option value="24">최근 24시간</option>
      <option value="48">최근 48시간</option>
    </select>
    <button type="button" class="ds-btn" id="monReload">새로고침</button>
  </span>
</div>

{{-- ① 서버 — CPUㆍ메모리ㆍ디스크 --}}
<div class="mon-grid mon-4" style="margin-bottom:14px;">
  <div class="mon-card">
    <div class="mon-head">서버 <span class="sp"></span><span id="monSrvState" class="mon-chip muted">—</span></div>
    <div class="mon-big" id="monUptime">—</div>
    <div class="mon-sub" id="monSrvSub">가동 시간</div>
  </div>
  <div class="mon-card">
    <div class="mon-head">CPU</div>
    <div class="mon-big"><span id="monCpu">—</span><span class="u">%</span></div>
    <div class="mon-bar" id="monCpuBar"><i style="width:0"></i></div>
    <div class="mon-sub" id="monCpuSub">—</div>
  </div>
  <div class="mon-card">
    <div class="mon-head">메모리</div>
    <div class="mon-big"><span id="monMem">—</span><span class="u">%</span></div>
    <div class="mon-bar" id="monMemBar"><i style="width:0"></i></div>
    <div class="mon-sub" id="monMemSub">—</div>
  </div>
  <div class="mon-card">
    <div class="mon-head">디스크</div>
    <div class="mon-big"><span id="monDisk">—</span><span class="u">%</span></div>
    <div class="mon-bar" id="monDiskBar"><i style="width:0"></i></div>
    <div class="mon-sub" id="monDiskSub">—</div>
  </div>
</div>

{{-- ② 사용률 그래프 --}}
<div class="mon-card" style="margin-bottom:14px;">
  <div class="mon-head">
    CPUㆍ메모리 사용률
    <span class="sp"></span>
    <span class="mon-legend">
      <span><i style="background:var(--primary)"></i>CPU</span>
      <span><i style="background:var(--warning)"></i>메모리</span>
    </span>
  </div>
  <svg class="mon-chart" id="monChart" viewBox="0 0 1000 170" preserveAspectRatio="none"></svg>
  <div class="mon-sub" id="monChartSrc">—</div>
</div>

{{-- ③ DB 와 웹 요청 --}}
<div class="mon-grid mon-2" style="margin-bottom:14px;">
  <div class="mon-card">
    <div class="mon-head">데이터베이스 (Aurora) <span class="sp"></span><span class="mon-chip muted" id="monDbRole">—</span></div>
    <div class="mon-rows" id="monDbRows"></div>
    <div class="mon-sub" id="monDbSub">—</div>
  </div>
  <div class="mon-card">
    <div class="mon-head">웹 요청 (오늘) <span class="sp"></span><span class="mon-chip muted" id="monWebSrc">—</span></div>
    <div class="mon-rows" id="monWebRows"></div>
    <div class="mon-sub" id="monWebSub">—</div>
  </div>
</div>

{{-- ④ 요청 상위 주소 --}}
<div class="mon-card" style="margin-bottom:14px;">
  <div class="mon-head">요청 상위 주소 (오늘)</div>
  <div class="mon-wrap">
    <table class="mon-tbl" id="monTop">
      <thead><tr><th>주소</th><th style="width:110px; text-align:right;">요청 수</th></tr></thead>
      <tbody><tr><td colspan="2" class="mon-sub">조회 중</td></tr></tbody>
    </table>
  </div>
</div>

{{-- ⑤ AWS — 되는 것과 안 되는 것을 그대로 --}}
<div class="mon-card" style="margin-bottom:14px;">
  <div class="mon-head">AWS 연결 <span class="sp"></span><span class="mon-chip muted" id="monAwsState">—</span></div>
  <div class="mon-rows" id="monAwsRows"></div>
  <div id="monAwsNote"></div>
</div>

{{-- ⑥ 최근 장애 --}}
<div class="mon-card" style="margin-bottom:14px;">
  <div class="mon-head">최근 장애 <span class="sp"></span><span class="mon-sub">오류 이력 기준 · 최근 3일</span></div>
  <div class="mon-wrap">
    <table class="mon-tbl" id="monInc">
      <thead><tr>
        <th style="width:92px;">일시</th><th style="width:70px;">출처</th><th style="width:66px;">응답</th>
        <th>내용</th><th style="width:70px; text-align:right;">횟수</th><th style="width:90px;">처리 상태</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="mon-sub">조회 중</td></tr></tbody>
    </table>
  </div>
</div>

{{-- ⑦ 과금 — 화면 맨 아래 (2026-10-02 지시) --}}
<div class="mon-card">
  <div class="mon-head">AWS 사용 과금 <span class="sp"></span><span class="mon-chip muted" id="monCostMonth">—</span></div>
  <div class="mon-grid mon-4" style="gap:12px;">
    <div>
      <div class="mon-sub">이번 달 누적</div>
      <div class="mon-big" id="monCostTotal">—</div>
    </div>
    <div>
      <div class="mon-sub">일 평균</div>
      <div class="mon-big" id="monCostDay">—</div>
    </div>
    <div>
      <div class="mon-sub">월말 예상</div>
      <div class="mon-big" id="monCostFc">—</div>
    </div>
    <div>
      <div class="mon-sub">경과 일수</div>
      <div class="mon-big" id="monCostDays">—</div>
    </div>
  </div>
  <div class="mon-wrap" id="monCostWrap" style="display:none;">
    <table class="mon-tbl" id="monCostTbl">
      <thead><tr><th>서비스</th><th style="width:130px; text-align:right;">금액</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
  <div id="monCostNote"></div>
</div>

@push('scripts')
<script>
(function () {
  const 첫값   = @json($값);
  const 새로간격 = @json($refresh);
  const 선     = @json($선);

  const $ = (id) => document.getElementById(id);

  /* 숫자를 사람이 읽는 꼴로 — 값이 없으면 숫자 자리에 「—」 를 둔다.
     0 과 「모름」은 다르다. 0 을 「—」로 적으면 멀쩡한 것을 고장으로 읽는다. */
  const 셈 = (v, 꼴 = 0) => (v === null || v === undefined || v === '')
    ? '—'
    : Number(v).toLocaleString('ko-KR', { minimumFractionDigits: 꼴, maximumFractionDigits: 꼴 });

  const 돈 = (v, 단위) => (v === null || v === undefined)
    ? '—'
    : (단위 === 'USD' ? '$' : '') + Number(v).toLocaleString('ko-KR',
        { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (단위 && 단위 !== 'USD' ? ' ' + 단위 : '');

  /* 선을 넘었는지 — 화면의 띠 색을 정한다 */
  const 등급 = (값, 잣대) => {
    if (값 === null || 값 === undefined || !잣대) return '';
    if (값 >= 잣대.bad)  return 'bad';
    if (값 >= 잣대.warn) return 'warn';
    return '';
  };

  const 띠 = (바, 값, 잣대) => {
    const el = $(바);
    if (!el) return;
    el.className = 'mon-bar ' + 등급(값, 잣대);
    el.querySelector('i').style.width = (값 === null || 값 === undefined ? 0 : Math.min(100, 값)) + '%';
  };

  /* 값이 없는 칸은 왜 없는지 적는다 — 빈칸은 「0」으로 읽힌다 */
  const 줄 = (이름, 값, 꼬리 = '') => 값 === '—' || 값 === null
    ? `<div class="mon-row"><span class="k">${이름}</span><span class="v na">읽지 못했습니다</span></div>`
    : `<div class="mon-row"><span class="k">${이름}</span><span class="v">${값}${꼬리}</span></div>`;

  /* ── 그래프 — 라이브러리를 더 들이지 않는다 ─────────────────────
     점이 100개쯤이라 SVG 폴리라인으로 넉넉하다. 0~100% 를 세로로 쓴다. */
  function 그리기(점들) {
    const svg = $('monChart');
    const W = 1000, H = 170, 아래 = 24, 왼 = 34;
    svg.innerHTML = '';

    if (!점들 || !점들.length) {
      svg.innerHTML = `<text x="${W / 2}" y="${H / 2}" text-anchor="middle"
        fill="var(--text-muted)" font-size="13">결과가 없습니다</text>`;
      return;
    }

    const 높이 = H - 아래 - 8;
    const x = (i) => 왼 + (W - 왼 - 6) * (점들.length === 1 ? 0.5 : i / (점들.length - 1));
    const y = (v) => 8 + 높이 * (1 - Math.max(0, Math.min(100, v)) / 100);

    let 글 = '';

    /* 가로 눈금 — 0ㆍ40ㆍ80 은 보기와 같은 자리에 둔다 */
    [0, 40, 80, 100].forEach((v) => {
      글 += `<line x1="${왼}" y1="${y(v)}" x2="${W - 6}" y2="${y(v)}"
              stroke="var(--gray-200)" stroke-width="1" stroke-dasharray="3 4" />`;
      글 += `<text x="${왼 - 6}" y="${y(v) + 4}" text-anchor="end"
              fill="var(--text-muted)" font-size="10">${v}%</text>`;
    });

    const 길 = (키) => 점들.map((p, i) => (p[키] === null || p[키] === undefined)
      ? null : `${x(i).toFixed(1)},${y(p[키]).toFixed(1)}`).filter(Boolean).join(' ');

    const cpu = 길('cpu'), mem = 길('mem');
    if (mem) 글 += `<polyline points="${mem}" fill="none" stroke="var(--warning)" stroke-width="1.6" />`;
    if (cpu) 글 += `<polyline points="${cpu}" fill="none" stroke="var(--primary)" stroke-width="2" />`;

    /* 시각은 다 적으면 겹친다 — 여섯 자리만 */
    const 걸음 = Math.max(1, Math.round(점들.length / 6));
    점들.forEach((p, i) => {
      if (i % 걸음 && i !== 점들.length - 1) return;
      글 += `<text x="${x(i)}" y="${H - 7}" text-anchor="middle"
              fill="var(--text-muted)" font-size="10">${p.t}</text>`;
    });

    svg.innerHTML = 글;
  }

  /* ── 칸 채우기 ──────────────────────────────────────────────── */
  function 채우기(d) {
    /* 머리 — 상태 */
    const h = d.health || {};
    const 표 = $('monHealth');
    표.className = 'mon-chip ' + (h.level === 'bad' ? 'bad' : h.level === 'warn' ? 'warn' : 'ok');
    표.innerHTML = `<span class="mon-dot ${h.level === 'ok' ? '' : h.level}"></span> ${h.label || '—'}`;
    $('monAt').textContent = (d.checked_at || '') + (h.reasons && h.reasons.length
      ? ' · ' + h.reasons.map((r) => r.text).join(' · ') : '');

    /* ① 서버 */
    const s = d.server || {};
    $('monUptime').textContent = s.uptime || '—';
    $('monSrvSub').textContent = `가동 시간 · 코어 ${셈(s.cores)}개 · 부하 `
      + `${셈(s.load?.['1m'], 2)} / ${셈(s.load?.['5m'], 2)} / ${셈(s.load?.['15m'], 2)}`;
    const 서버딱 = $('monSrvState');
    서버딱.className = 'mon-chip ' + (h.level === 'bad' ? 'bad' : h.level === 'warn' ? 'warn' : 'ok');
    서버딱.textContent = h.label || '—';

    $('monCpu').textContent  = 셈(s.cpu, 1);
    띠('monCpuBar', s.cpu, 선.cpu);
    $('monCpuSub').textContent = s.cpu === null ? '서버 CPU 정보를 읽지 못했습니다'
      : `서버에서 직접 측정 (${s.at || ''})`;

    $('monMem').textContent  = 셈(s.memory?.percent, 1);
    띠('monMemBar', s.memory?.percent, 선.memory);
    $('monMemSub').textContent = s.memory?.total_mb
      ? `${셈(s.memory.used_mb)} / ${셈(s.memory.total_mb)} MB`
      : '서버 메모리 정보를 읽지 못했습니다';

    $('monDisk').textContent = 셈(s.disk?.percent, 1);
    띠('monDiskBar', s.disk?.percent, 선.disk);
    $('monDiskSub').textContent = s.disk?.total_gb
      ? `${셈(s.disk.used_gb, 1)} / ${셈(s.disk.total_gb, 1)} GB (/)`
      : '디스크 정보를 읽지 못했습니다';

    /* ② 그래프 */
    그리기(d.series?.points);
    $('monChartSrc').textContent = d.series?.source || '';

    /* ③ DB */
    const b = d.db || {};
    $('monDbRole').textContent = b.read_only ? '읽기 전용' : '쓰기 노드';
    $('monDbRows').innerHTML =
        줄('연결 수', b.connections === null ? null : `${셈(b.connections)} / ${셈(b.max_connections)}`
            + (b.conn_percent !== null ? ` (${셈(b.conn_percent, 1)}%)` : ''))
      + 줄('실행 중 질의', 셈(b.running))
      + 줄('최대 동시 연결', 셈(b.max_used))
      + 줄('초당 질의', 셈(b.qps, 1))
      + 줄('느린 질의', 셈(b.slow_queries))
      + 줄('버퍼 적중률', b.buffer_hit === null ? null : 셈(b.buffer_hit, 2), '%')
      + 줄('데이터 용량', b.size?.mb === null ? null : 셈(b.size?.mb, 1) + ' MB'
            + (b.size?.tables ? ` · 표 ${셈(b.size.tables)}개` : ''))
      /* RDS 의 CPU 와 남은 저장 공간은 DB 안에서 알 수 없다 — CloudWatch 만 안다 */
      + 줄('DB CPU', awsOr(d, 'rds_cpu', (v) => 셈(v, 1) + '%'))
      + 줄('남은 저장 공간', awsOr(d, 'rds_storage', (v) => 셈(v / 1073741824, 1) + ' GB'));
    $('monDbSub').textContent = [b.aurora_version ? 'Aurora ' + b.aurora_version : null,
      b.version ? 'MySQL ' + b.version : null, b.cluster].filter(Boolean).join(' · ');

    /* ③ 웹 */
    const w = d.web || {}, r = d.response || {};
    $('monWebSrc').textContent = w.source || '읽지 못했습니다';
    const e5등급 = 등급(w.by_class?.['5xx'], 선.error_5xx);
    $('monWebRows').innerHTML =
        줄('요청 수', 셈(w.total))
      + `<div class="mon-row"><span class="k">5xx 오류</span>`
      + `<span class="v" style="${e5등급 ? 'color:var(--' + (e5등급 === 'bad' ? 'danger' : 'warning') + ')' : ''}">`
      + `${셈(w.by_class?.['5xx'])}</span></div>`
      + 줄('4xx 오류', 셈(w.by_class?.['4xx']))
      + 줄('정상(2xx)', 셈(w.by_class?.['2xx']))
      + 줄('평균 응답', r.avg === null ? null : 셈(r.avg) + ' ms')
      + 줄('최대 응답 시간', r.max === null ? null : 셈(r.max) + ' ms');
    $('monWebSub').textContent = r.count
      ? `최근 1시간 ${셈(r.count)}건을 애플리케이션에서 직접 측정한 값입니다. nginx 기본 로그에는 응답 시간이 기록되지 않습니다.`
      : '응답 시간은 애플리케이션에서 수집합니다. 배포 후 몇 분이 지나면 표시됩니다.';

    /* ④ 많이 불린 주소 */
    const top = Object.entries(w.top || {});
    $('monTop').querySelector('tbody').innerHTML = top.length
      ? top.map(([p, n]) => `<tr><td class="path">${ 안전(p) }</td><td class="num">${셈(n)}</td></tr>`).join('')
      : `<tr><td colspan="2" class="mon-sub">${안전(w.note || '결과가 없습니다')}</td></tr>`;

    /* ⑤ AWS */
    const a = d.aws || {}, 신 = a.identity || {}, m = a.metrics || {};
    const aws딱 = $('monAwsState');
    aws딱.className = 'mon-chip ' + (신.ok ? (m.ok ? 'ok' : 'warn') : 'bad');
    aws딱.textContent = 신.ok ? (m.ok ? '지표 수집 중' : '인증만 확인됨') : '연결 안 됨';

    $('monAwsRows').innerHTML =
        줄('지역', 안전(신.region))
      + 줄('계정', 안전(신.account))
      + 줄('역할', 안전(신.role))
      + 줄('인스턴스', 안전(신.instance))
      + 줄('EC2 CPU (CloudWatch)', awsOr(d, 'ec2_cpu', (v) => 셈(v, 1) + '%'))
      /* 메모리ㆍ디스크 (2026-10-03 운영 조치로 열렸다).
      
         EC2 는 이 둘을 스스로 올리지 않아, 운영에서 CloudWatch 에이전트를 붙였다.
         위쪽의 「서버」 칸은 이 기계에서 바로 재는 값이고, 여기 값은 CloudWatch 에
         쌓인 값이다 — 두 값이 벌어지면 한쪽이 멈춘 것이라 바로 눈에 띈다.
      
         메모리는 두 값의 셈법이 달라 숫자가 같지 않다(에이전트 4.9% · 서버 10.2% ·
         2026-10-03 확인). 디스크는 16.2% 로 같다. 그래서 이름에 어디서 온 값인지
         적어 둔다 — 같은 이름으로 두 숫자가 서면 어느 쪽이 맞는지 묻게 된다. */
      + 줄('메모리 (CloudWatch 에이전트)', awsOr(d, 'ec2_mem', (v) => 셈(v, 1) + '%'))
      + 줄('디스크 (CloudWatch 에이전트)', awsOr(d, 'ec2_disk', (v) => 셈(v, 1) + '%'))
      + 줄('로드밸런서 요청', awsOr(d, 'alb_req', (v) => 셈(v)));

    const 쪽지 = [];
    if (!m.ok && m.error) {
      쪽지.push(`<div class="mon-deny"><i class="fa-solid fa-lock"></i>`
        + `CloudWatch 지표를 읽지 못했습니다 — ${안전(m.error)}</div>`);
    }
    if (m.denied || (d.cost && d.cost.denied)) {
      쪽지.push(`<div class="mon-note">`
        + `AWS 지표를 표시하려면 인스턴스 역할 <code>${안전(신.role || '-')}</code> 에 읽기 권한이 필요합니다.`
        + ` 아래 권한을 부여하면 이 화면의 모든 항목이 표시됩니다.<br>`
        + `<code>cloudwatch:GetMetricData</code> · <code>cloudwatch:ListMetrics</code>`
        + ` · <code>ce:GetCostAndUsage</code> · <code>rds:DescribeDBClusters</code><br>`
        + `권한 부여 후 별도 작업 없이 자동으로 표시됩니다.`
        + `</div>`);
    }
    if (!신.instance) {
      쪽지.push(`<div class="mon-note">대상 인스턴스를 확인하지 못했습니다. `
        + `<code>MONITOR_EC2_INSTANCE</code> 에 지정하면 해당 인스턴스를 조회합니다.</div>`);
    }
    쪽지.push(`<div class="mon-note">`
      + `로드밸런서가 <strong>구성되어 있지 않습니다.</strong> nginx 가 80ㆍ443 포트를 직접 처리합니다. `
      + `따라서 요청 수ㆍ5xxㆍ평균 응답은 ALB 지표가 아니라 <strong>nginx 로그와 애플리케이션 측정값</strong>입니다. `
      + `ALB 를 도입하는 경우 <code>MONITOR_ALB</code> 에 식별자를 지정하면 해당 항목이 표시됩니다.`
      + `</div>`);
    $('monAwsNote').innerHTML = 쪽지.join('');

    /* ⑥ 최근 장애 */
    const inc = d.incidents || [];
    $('monInc').querySelector('tbody').innerHTML = inc.length
      ? inc.map((i) => `<tr>
          <td>${안전(i.at)}</td>
          <td>${안전(i.source)}</td>
          <td>${i.code ? 안전(i.code) : '—'}</td>
          <td>${안전(i.text)}</td>
          <td class="num">${셈(i.hit)}</td>
          <td><span class="mon-chip ${안전(i.tone)}">${안전(i.state)}</span></td>
        </tr>`).join('')
      : '<tr><td colspan="6" class="mon-sub">최근 3일 내 발생한 장애가 없습니다</td></tr>';

    /* ⑦ 과금 */
    const c = d.cost || {};
    $('monCostMonth').textContent = c.month || '—';
    if (c.ok) {
      $('monCostTotal').textContent = 돈(c.total, c.currency);
      $('monCostDay').textContent   = 돈(c.per_day, c.currency);
      $('monCostFc').textContent    = 돈(c.forecast, c.currency);
      $('monCostDays').textContent  = `${셈(c.days)} / ${셈(c.days_total)}`;

      const 서비스 = Object.entries(c.services || {});
      $('monCostWrap').style.display = 서비스.length ? '' : 'none';
      $('monCostTbl').querySelector('tbody').innerHTML = 서비스
        .map(([n, v]) => `<tr><td>${안전(n)}</td><td class="num">${돈(v, c.currency)}</td></tr>`).join('');

      $('monCostNote').innerHTML = `<div class="mon-note">${안전(c.note || '')}<br>`
        + `「월말 예상」은 현재까지 사용 금액을 경과 일수로 나눈 뒤 해당 월 일수를 곱한 <strong>추정치</strong>입니다. `
        + `Cost Explorer 는 조회 1회당 0.01달러가 부과되어 6시간 간격으로만 조회합니다.</div>`;
    } else {
      ['monCostTotal', 'monCostDay', 'monCostFc', 'monCostDays'].forEach((k) => $(k).textContent = '—');
      $('monCostWrap').style.display = 'none';
      $('monCostNote').innerHTML = `<div class="mon-deny"><i class="fa-solid fa-lock"></i>`
        + `과금을 읽지 못했습니다 — ${안전(c.error || '알 수 없음')}</div>`
        + `<div class="mon-note">금액을 조회하려면 역할에 <code>ce:GetCostAndUsage</code> 권한이 필요하며, `
        + `결제 계정에서 <strong>Cost Explorer 를 활성화</strong>해야 합니다(계정당 1회).</div>`;
    }
  }

  /* CloudWatch 값이 있으면 그것을, 없으면 왜 없는지 */
  function awsOr(d, 키, 꼴) {
    const m = d.aws?.metrics;
    if (!m) return null;
    if (!m.ok) return null;
    const v = m.latest?.[키];
    return (v === null || v === undefined) ? null : 꼴(v);
  }

  const 안전 = (v) => String(v ?? '—').replace(/[<>&"]/g, (c) =>
    ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

  /* ── 다시 읽기 ───────────────────────────────────────────────
     12초마다 읽는 일은 하지 않는다 — 기본 60초이고 설정에서 늘릴 수 있다.
     앞의 요청이 아직 안 왔으면 새로 부르지 않는다. */
  let 부르는중 = false;

  async function 다시(수동 = false) {
    if (부르는중) return;
    부르는중 = true;

    const 단추 = $('monReload');
    if (수동) { 단추.disabled = true; 단추.textContent = '조회 중'; }

    try {
      const res = await fetch(`{{ route('monitoring.data') }}?hours=${$('monHours').value}`,
        { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      채우기(await res.json());
    } catch (e) {
      if (수동) showToast('지표를 불러오지 못했습니다.', 'danger');
    } finally {
      부르는중 = false;
      if (수동) { 단추.disabled = false; 단추.textContent = '새로고침'; }
    }
  }

  채우기(첫값);

  $('monReload').addEventListener('click', () => 다시(true));
  $('monHours').addEventListener('change', () => 다시(true));

  /* 화면을 보고 있을 때만 읽는다 — 탭을 묻어 두면 쉰다 */
  let 시계 = null;
  const 걸기 = () => {
    if (시계) clearInterval(시계);
    시계 = setInterval(() => { if (!document.hidden) 다시(); }, Math.max(30, 새로간격) * 1000);
  };
  걸기();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) 다시(); });
})();
</script>
@endpush

@endsection
