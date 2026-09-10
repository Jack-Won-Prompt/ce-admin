@extends('layouts.app')

@section('title', '웹훅 관리')
@section('page-title', '웹훅 관리')
@section('breadcrumb', '홈 - 설정 - 웹훅 관리')

@push('styles')
<style>
  /* 파라미터 표 — 창 안에서 줄을 더하고 지운다 */
  .wh-params { width:100%; border-collapse:collapse; font-size:12px; }
  .wh-params th { background:var(--gray-50); color:var(--gray-600); font-weight:700;
                  padding:7px 8px; border:1px solid var(--gray-300); text-align:center; white-space:nowrap; }
  .wh-params td { padding:4px; border:1px solid var(--gray-200); }
  .wh-params td input[type=text] { width:100%; border:1px solid var(--gray-200); border-radius:6px;
                                   padding:5px 7px; font-size:12px; font-family:inherit; }
  .wh-params td select { width:100%; border:1px solid var(--gray-200); border-radius:6px;
                         padding:5px 7px; font-size:12px; font-family:inherit; background:#fff; }
  .wh-params td.center { text-align:center; }
  .wh-row-del { border:none; background:none; cursor:pointer; color:var(--alert-500); font-size:15px; line-height:1; }
  .wh-field label { display:block; font-size:12px; font-weight:500; color:var(--text-secondary); margin-bottom:4px; }
  .wh-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px 12px; }
  .wh-grid2 .wide { grid-column:1 / -1; }
  .wh-hint { font-size:11.5px; color:var(--text-muted); line-height:1.6; }
</style>
@endpush

@section('content')

<form method="GET" action="{{ route('webhooks.index') }}" class="ds-filter-card" id="whFilterCard"
      style="{{ $tab === 'logs' ? 'display:none;' : '' }}">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      <label class="ds-field-label">구분</label>
      <select name="provider" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(config('webhooks.providers') as $k => $label)
          <option value="{{ $k }}" @selected($provider === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">방향</label>
      <select name="direction" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체</option>
        @foreach(config('webhooks.directions') as $k => $label)
          <option value="{{ $k }}" @selected($direction === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field" style="flex:1;min-width:220px;">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="search" value="{{ $search }}" class="form-control"
             placeholder="웹훅 명 · 이벤트 · 주소">
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('webhooks.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    <button type="button" class="ds-btn" onclick="window.__whGrid?.downloadExcel()">엑셀 다운</button>
  </div>
</form>

{{-- 로그 거르개 — 위와 같은 자리, 같은 모양. 탭을 바꿔도 얼개가 흔들리지 않는다
     (2026-09-10 지시). 하나만 보인다. --}}
@include('webhooks._log-filter', ['보내는곳' => route('webhooks.index')])

<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 로그는 옆 탭이다 (2026-09-10 지시). 낱장으로 넘어가지 않고 이 자리에 박힌다 —
         정의를 고치다 「그래서 실제로 왔나」를 볼 때 화면을 떠나지 않아도 된다. --}}
    <div class="pnl-tabs">
      <button type="button" id="whTabList" class="pnl-tab {{ $tab === 'logs' ? '' : 'active' }}" onclick="whTab('list')">
        <i class="fa-solid fa-arrows-rotate"></i> 웹훅 목록
        <span class="pnl-tab-cnt">(총 {{ count($gridData) }}건)</span>
      </button>
      <button type="button" id="whTabLogs" class="pnl-tab {{ $tab === 'logs' ? 'active' : '' }}" onclick="whTab('logs')">
        <i class="fa-solid fa-clock-rotate-left"></i> 전송·수신 로그
        <span class="pnl-tab-cnt">(총 {{ $logCounts['all'] }}건)</span>
      </button>
      <span style="margin-left:auto;gap:6px;align-items:center;display:{{ $tab === 'logs' ? 'none' : 'flex' }};" id="whListTools">
        @perm('webhooks', 'create')
        <button type="button" class="ds-btn ds-btn-primary" onclick="whOpen()">웹훅 등록</button>
        @endperm
      </span>
      {{-- 로그 탭에서는 같은 자리에 성공ㆍ실패가 선다 — 줄이 늘지 않는다 --}}
      <span style="margin-left:auto;gap:8px;align-items:center;display:{{ $tab === 'logs' ? 'flex' : 'none' }};" id="whLogTools">
        <span class="wl-chip wl-ok">성공 {{ $logCounts['ok'] }}</span>
        <span class="wl-chip wl-fail">실패 {{ $logCounts['fail'] }}</span>
        <button type="button" class="ds-btn" onclick="window.__wlGrid?.downloadExcel()">엑셀 다운</button>
      </span>
    </div>
    <div style="padding:16px;{{ $tab === 'logs' ? 'display:none;' : '' }}" id="whListPanel">
      <div id="whGrid"></div>
    </div>
    <div style="padding:16px;{{ $tab === 'logs' ? '' : 'display:none;' }}" id="whLogsPanel">
      @include('webhooks._logs', ['보내는곳' => route('webhooks.index')])
    </div>
  </div>
</div>

{{-- 등록·수정 창 --}}
<div id="whBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="whClose()"></div>
<div id="whModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:860px;max-width:96vw;max-height:92vh;overflow:auto;background:var(--bg-card);
     border:1px solid var(--primary);border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;position:sticky;top:0;z-index:2;">
    <i class="fa-solid fa-arrows-rotate" style="color:#fff;font-size:14px;"></i>
    <span id="whTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">웹훅 등록</span>
    <button onclick="whClose()" style="border:none;background:none;color:#fff;font-size:16px;line-height:1;cursor:pointer;">&#215;</button>
  </div>

  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
    <input type="hidden" id="wh-id">

    <div class="wh-grid2">
      <div class="wh-field">
        <label>구분 <span style="color:var(--alert-500);">*</span></label>
        <select class="form-control form-select" id="wh-provider">
          @foreach(config('webhooks.providers') as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="wh-field">
        <label>Inbound / Outbound <span style="color:var(--alert-500);">*</span></label>
        <select class="form-control form-select" id="wh-direction" onchange="whDirChanged()">
          @foreach(config('webhooks.directions') as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="wh-field">
        <label>웹훅 명 <span style="color:var(--alert-500);">*</span></label>
        <input type="text" class="form-control" id="wh-name" placeholder="예) 가상계좌 입금">
      </div>
      <div class="wh-field">
        <label>이벤트 코드</label>
        <input type="text" class="form-control" id="wh-event" placeholder="예) DEPOSIT_CALLBACK">
      </div>

      <div class="wh-field wide">
        <label>URL <span style="color:var(--alert-500);">*</span></label>
        <div style="display:flex;gap:8px;">
          <select class="form-control form-select" id="wh-method" style="flex:0 0 110px;">
            @foreach(['POST', 'GET', 'PUT', 'PATCH', 'DELETE'] as $m)
              <option value="{{ $m }}">{{ $m }}</option>
            @endforeach
          </select>
          <input type="text" class="form-control" id="wh-url" style="flex:1;"
                 placeholder="받는 자리는 /toss/webhook 처럼, 보내는 자리는 https:// 로">
        </div>
        <div class="wh-hint" id="wh-url-hint" style="margin-top:5px;"></div>
      </div>

      <div class="wh-field">
        <label>비밀키가 있는 자리</label>
        <input type="text" class="form-control" id="wh-secret" placeholder="예) TOSS_WEBHOOK_SECRET">
        <div class="wh-hint" style="margin-top:4px;">열쇠 자체는 담지 않습니다. .env 의 이름만 적습니다.</div>
      </div>
      <div class="wh-field">
        <label>순서 · 사용</label>
        <div style="display:flex;gap:10px;align-items:center;">
          <input type="text" class="form-control" id="wh-sort" style="flex:0 0 100px;" placeholder="0">
          <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--gray-700);">
            <input type="checkbox" id="wh-active" checked> 사용
          </label>
        </div>
      </div>

      <div class="wh-field wide">
        <label>설명</label>
        <input type="text" class="form-control" id="wh-desc" placeholder="이 알림이 언제 오는지 한 줄로">
      </div>
      <div class="wh-field wide">
        <label>메모</label>
        <textarea class="form-control" id="wh-note" rows="2" style="font-size:13px;"></textarea>
      </div>
    </div>

    <div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="font-size:12.5px;font-weight:700;color:var(--primary);">파라미터</span>
        <span class="wh-hint" style="flex:1;">주고받는 값의 이름표입니다. 점(.)으로 묶음 안쪽을 적습니다 — data.orderId</span>
        <button type="button" class="ds-btn" onclick="whAddParam()">＋ 줄 추가</button>
      </div>
      <table class="wh-params">
        <thead>
          <tr>
            <th style="width:90px;">위치</th>
            <th style="width:180px;">이름</th>
            <th style="width:100px;">구분</th>
            <th style="width:60px;">필수</th>
            <th style="width:160px;">보기</th>
            <th>설명</th>
            <th style="width:34px;"></th>
          </tr>
        </thead>
        <tbody id="whParamBody"></tbody>
      </table>
    </div>

    <div id="whResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>

    <div style="display:flex;justify-content:space-between;gap:8px;">
      @perm('webhooks', 'delete')
      <button type="button" class="btn btn-outline btn-sm" id="whDelete" onclick="whDelete()"
              style="color:var(--alert-500);border-color:var(--alert-100);display:none;">삭제</button>
      @endperm
      <div style="display:flex;gap:8px;margin-left:auto;">
        <button type="button" class="btn btn-outline btn-sm" onclick="whClose()">취소</button>
        <button type="button" class="btn btn-primary btn-sm" id="whSaveBtn" onclick="whSave(this)">저장</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const ROWS      = @json($gridData);
  const SAVE_URL  = @json(route('webhooks.store'));
  const BASE_URL  = @json(url('/'));
  const POSITIONS = @json(config('webhooks.positions'));
  const TYPES     = @json(config('webhooks.types'));

  const grid = new wwGrid({
    el: document.getElementById('whGrid'),
    height: 'auto', editable: false, rowCheckbox: false, rowNumber: true,
    toolbar: false, footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '구분',        name: 'provider',   width: 120, align: 'center', sortable: true },
      { header: '웹훅 명',     name: 'name',       width: 180, sortable: true },
      { header: '이벤트 코드', name: 'event_code', width: 200, sortable: true },
      { header: '방향',        name: 'direction',  width: 80,  align: 'center', sortable: true },
      { header: '메서드',      name: 'method',     width: 80,  align: 'center' },
      { header: 'URL',         name: 'url',        width: 320 },
      { header: '사용',        name: 'active',     width: 70,  align: 'center', sortable: true },
      { header: '파라미터',    name: 'params',     width: 90,  align: 'right', sortable: true },
      { header: '로그',        name: 'logs',       width: 80,  align: 'right', sortable: true },
      { header: '비밀키 자리', name: 'secret_env', width: 170 },
      { header: '설명',        name: 'desc',       width: 320 },
    ],
    data: ROWS,
  });
  window.__whGrid = grid;

  /* 줄을 더블클릭하면 고치는 창이 열린다 — 다른 목록과 같은 손놀림이다 */
  document.getElementById('whGrid').addEventListener('dblclick', (e) => {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row?.raw) whOpen(row.raw);
  });

  /* ── 탭 ──

     두 칸이 한 화면에 함께 그려져 있다(따로 불러오지 않는다). 여기서는 보이고
     감추는 것만 한다 — 찾을 때는 폼이 tab=logs 를 달고 가서 이 탭으로 돌아온다. */
  window.whTab = function (어느것) {
    const 로그냐 = 어느것 === 'logs';

    document.getElementById('whListPanel').style.display  = 로그냐 ? 'none' : '';
    document.getElementById('whLogsPanel').style.display  = 로그냐 ? '' : 'none';
    document.getElementById('whListTools').style.display  = 로그냐 ? 'none' : 'flex';
    document.getElementById('whLogTools').style.display   = 로그냐 ? 'flex' : 'none';
    document.getElementById('whFilterCard').style.display    = 로그냐 ? 'none' : '';
    document.getElementById('whLogFilterCard').style.display = 로그냐 ? '' : 'none';
    document.getElementById('whTabList').classList.toggle('active', !로그냐);
    document.getElementById('whTabLogs').classList.toggle('active', 로그냐);

    /* 감춰진 채로 그려진 표는 높이를 제대로 잡지 못한다 — 보일 때 한 번 다시 재게 한다.
       wwGrid 는 창 크기가 바뀔 때 그 셈을 다시 하므로 그 길을 빌린다. */
    window.dispatchEvent(new Event('resize'));
  };

  /* ── 창 ── */
  const $ = (id) => document.getElementById(id);

  window.whDirChanged = function () {
    const 받나 = $('wh-direction').value === 'inbound';
    $('wh-url-hint').innerHTML = 받나
      ? `받는 자리입니다. 상대에게 알려 줄 주소는 <b>${BASE_URL}</b> + 적은 주소가 됩니다.`
      : '보내는 자리입니다. https:// 로 시작하는 상대 주소를 적습니다.';
  };

  window.whAddParam = function (p) {
    const tb = $('whParamBody');
    const tr = document.createElement('tr');
    const opt = (map, cur) => Object.entries(map)
      .map(([v, l]) => `<option value="${v}" ${v === cur ? 'selected' : ''}>${l}</option>`).join('');

    tr.innerHTML =
      `<td><select class="p-position">${opt(POSITIONS, p?.position ?? 'body')}</select></td>` +
      `<td><input type="text" class="p-name" value="${p?.name ?? ''}"></td>` +
      `<td><select class="p-type">${opt(TYPES, p?.data_type ?? 'string')}</select></td>` +
      `<td class="center"><input type="checkbox" class="p-required" ${p?.required ? 'checked' : ''}></td>` +
      `<td><input type="text" class="p-sample" value="${(p?.sample ?? '').replace(/"/g, '&quot;')}"></td>` +
      `<td><input type="text" class="p-desc" value="${(p?.description ?? '').replace(/"/g, '&quot;')}"></td>` +
      `<td class="center"><button type="button" class="wh-row-del" title="줄 지우기">&#215;</button></td>`;

    tr.querySelector('.wh-row-del').onclick = () => tr.remove();
    tb.appendChild(tr);
  };

  window.whOpen = function (raw) {
    $('wh-id').value       = raw?.id ?? '';
    $('wh-provider').value = raw?.provider ?? 'toss';
    $('wh-direction').value= raw?.direction ?? 'inbound';
    $('wh-name').value     = raw?.name ?? '';
    $('wh-event').value    = raw?.event_code ?? '';
    $('wh-method').value   = raw?.http_method ?? 'POST';
    $('wh-url').value      = raw?.url ?? '';
    $('wh-secret').value   = raw?.secret_env ?? '';
    $('wh-sort').value     = raw?.sort ?? 0;
    $('wh-active').checked = raw ? !!raw.is_active : true;
    $('wh-desc').value     = raw?.description ?? '';
    $('wh-note').value     = raw?.note ?? '';

    $('whParamBody').innerHTML = '';
    (raw?.params ?? []).forEach(p => whAddParam(p));

    $('whTitle').textContent = raw ? '웹훅 수정' : '웹훅 등록';
    const del = $('whDelete');
    if (del) del.style.display = raw ? '' : 'none';
    $('whResult').style.display = 'none';

    whDirChanged();
    $('whBackdrop').style.display = 'block';
    $('whModal').style.display    = 'block';
  };

  window.whClose = function () {
    $('whBackdrop').style.display = 'none';
    $('whModal').style.display    = 'none';
  };

  function 알림(말, 좋은가) {
    const box = $('whResult');
    box.style.display = 'block';
    box.textContent   = 말;
    box.style.background = 좋은가 ? 'var(--primary-50)'  : 'var(--danger-light)';
    box.style.color      = 좋은가 ? 'var(--primary)'     : 'var(--danger)';
  }

  window.whSave = async function (btn) {
    const params = [...$('whParamBody').querySelectorAll('tr')].map(tr => ({
      position:    tr.querySelector('.p-position').value,
      name:        tr.querySelector('.p-name').value.trim(),
      data_type:   tr.querySelector('.p-type').value,
      required:    tr.querySelector('.p-required').checked,
      sample:      tr.querySelector('.p-sample').value.trim() || null,
      description: tr.querySelector('.p-desc').value.trim() || null,
    })).filter(p => p.name);

    const body = {
      id:          $('wh-id').value || null,
      provider:    $('wh-provider').value,
      direction:   $('wh-direction').value,
      name:        $('wh-name').value.trim(),
      event_code:  $('wh-event').value.trim() || null,
      http_method: $('wh-method').value,
      url:         $('wh-url').value.trim(),
      secret_env:  $('wh-secret').value.trim() || null,
      sort:        parseInt($('wh-sort').value) || 0,
      is_active:   $('wh-active').checked,
      description: $('wh-desc').value.trim() || null,
      note:        $('wh-note').value.trim() || null,
      params,
    };

    if (!body.name) { 알림('웹훅 명을 적으십시오.', false); return; }
    if (!body.url)  { 알림('주소를 적으십시오.', false); return; }

    BtnState.loading(btn, '저장 중...');
    try {
      const res = await apiRequest(SAVE_URL, 'POST', body);
      if (!res.ok) throw new Error(res.message || '저장하지 못했습니다.');
      showToast('저장했습니다.', 'success');
      setTimeout(() => location.reload(), 600);
    } catch (e) {
      BtnState.reset(btn);
      알림(e.message || '저장하지 못했습니다.', false);
    }
  };

  window.whDelete = async function () {
    const id = $('wh-id').value;
    if (!id) return;
    if (!await ceConfirm('이 웹훅 정의를 지웁니다. 오간 로그는 그대로 남습니다.', { tone: 'danger' })) return;

    try {
      const res = await apiRequest(@json(url('settings/webhooks')) + '/' + id, 'DELETE');
      if (!res.ok) throw new Error(res.message || '지우지 못했습니다.');
      showToast('지웠습니다.', 'success');
      setTimeout(() => location.reload(), 600);
    } catch (e) {
      알림(e.message || '지우지 못했습니다.', false);
    }
  };
})();
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#whGrid', title: '웹훅 목록', body: '밖과 주고받는 알림을 한자리에서 봅니다. 줄을 <b>더블클릭</b>하면 고칠 수 있습니다.' },
  { selector: '.pnl-tabs', title: '웹훅 등록', body: '구분(토스ㆍ팝빌ㆍNICEㆍ위드웍스…)과 방향, 주소, 파라미터를 적어 둡니다.' },
  { selector: '#whTabLogs', title: '전송·수신 로그', body: '실제로 무엇이 오갔는지, 성공했는지, 언제였는지를 <b>같은 화면 옆 탭</b>에서 봅니다.' },
];
</script>
@endpush
