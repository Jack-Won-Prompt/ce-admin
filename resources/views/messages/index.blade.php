{{-- resources/views/messages/index.blade.php --}}
@extends('layouts.app')

@section('title', '메시지 관리')
@section('page-title', '메시지 관리')
@section('breadcrumb', '홈 / 발송ㆍ내역 / 메시지 관리')

@push('styles')
<style>
  .ms-tabs { display: flex; gap: 8px; }
  .ms-tab { padding: 6px 14px; border: 1px solid var(--gray-200); border-radius: 8px; background: var(--gray-0);
            font-size: 13px; font-weight: 500; color: var(--gray-700); cursor: pointer; }
  .ms-tab.active { border-color: var(--primary); color: var(--primary); font-weight: 700; }
  .ms-panel { display: none; }
  .ms-panel.active { display: block; }

  /* 유형 목록 — 고른 것만 테두리로 알린다 */
  .ms-tpl { display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px; cursor: pointer;
            border: 1px solid var(--gray-200); border-radius: 8px; background: var(--gray-0); }
  .ms-tpl.on { border-color: var(--primary); background: var(--primary-50); }
  .ms-tpl-name { font-size: 12.5px; font-weight: 700; color: var(--gray-900); }
  .ms-tpl-desc { font-size: 11px; color: var(--gray-500); margin-top: 2px; }
  .ms-tpl-edit { margin-left: auto; display: flex; gap: 4px; flex-shrink: 0; }
  .ms-mini { border: 1px solid var(--gray-200); background: var(--gray-0); border-radius: 6px;
             padding: 1px 6px; font-size: 11px; color: var(--gray-700); cursor: pointer; line-height: 18px; }
  .ms-mini:hover { border-color: var(--primary); color: var(--primary); }

  .ms-send-card { background: var(--gray-0); border-radius: 12px; padding: 16px;
                  display: flex; flex-direction: column; gap: 12px; }
  .ms-send-grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 16px; align-items: start; }
  @media (max-width: 1100px) { .ms-send-grid { grid-template-columns: 1fr; } }
  .ms-count { font-size: 12px; color: var(--gray-600); }
  .ms-count b { color: var(--primary); }
</style>
@endpush

@section('content')

<div class="ds-chips ms-tabs">
  <button type="button" class="ms-tab active" data-panel="pnlSend"  onclick="msTab(this)">발송</button>
  <button type="button" class="ms-tab"        data-panel="pnlTpl"   onclick="msTab(this)">메시지 유형</button>
  <button type="button" class="ms-tab"        data-panel="pnlHist"  onclick="msTab(this)">발송 이력</button>
</div>

{{-- ══ 발송 ══ --}}
<div id="pnlSend" class="ms-panel active">

  <form method="GET" action="{{ route('messages.index') }}" class="ds-filter-card">
    <div class="ds-filter-fields">
      <div class="ds-filter-field span-4">
        <label class="ds-field-label">거래처 검색</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="이름 · 전화번호">
      </div>
      <div class="ds-filter-field span-3">
        <label class="ds-field-label">번호</label>
        <select name="has_mobile" class="form-control">
          <option value="">전체</option>
          <option value="1" {{ request('has_mobile') ? 'selected' : '' }}>번호가 있는 곳만</option>
        </select>
      </div>
    </div>
    <div class="ds-filter-actions">
      @if(request('q') || request('has_mobile'))
        <a href="{{ route('messages.index') }}" class="ds-btn">초기화</a>
      @endif
      <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    </div>
  </form>

  <div class="ms-send-grid" style="margin-top:12px;">
    {{-- 왼쪽 — 무엇을 보낼지 --}}
    <div class="ms-send-card">
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:6px;">발송 수단</label>
        <div style="display:flex;gap:6px;">
          <button type="button" class="ms-tab active" data-ch="sms"      onclick="msChannel(this)">문자(SMS)</button>
          <button type="button" class="ms-tab"        data-ch="alimtalk" onclick="msChannel(this)">알림톡</button>
        </div>
      </div>

      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:6px;">메시지 유형</label>
        <div id="msTplList" style="display:flex;flex-direction:column;gap:4px;max-height:260px;overflow-y:auto;"></div>
      </div>

      <div id="msBodyWrap">
        <label class="ds-field-label" style="display:block;margin-bottom:6px;">
          본문 <span id="msLen" class="ms-count"></span>
        </label>
        <textarea id="msBody" class="form-control" rows="7" style="font-size:13px;line-height:1.7;"
                  oninput="msUpdateLen()" placeholder="보낼 내용을 입력하세요"></textarea>
        <div class="ms-count" style="margin-top:4px;">
          #{고객명} 을 쓰면 받는 분 이름으로 바뀝니다.
        </div>
      </div>
      <div id="msAlimNote" style="display:none;font-size:12px;color:var(--gray-600);line-height:1.7;
           background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:10px 12px;">
        알림톡 본문은 <b>카카오에 등록된 템플릿</b>이 정합니다. 여기서 고친 내용은 나가지 않습니다.
      </div>
    </div>

    {{-- 오른쪽 — 누구에게 --}}
    <div class="ds-grid-section">
      <div class="ds-grid-bar">
        <div class="ds-grid-bar-left">
          <span class="ds-grid-total">거래처 <b>{{ number_format($total) }}</b>곳</span>
          <span class="ds-grid-hint">
            번호가 있는 곳 <b>{{ number_format($sendable) }}</b>곳.
            @if($total > $sendable) 번호가 없는 {{ number_format($total - $sendable) }}곳은 발송에서 빠집니다. @endif
          </span>
        </div>
        <div class="ds-grid-bar-right">
          <button type="button" class="ds-btn" onclick="msSend('selected')">선택 발송</button>
          <button type="button" class="ds-btn ds-btn-primary" onclick="msSend('all')">조건 전체 발송</button>
        </div>
      </div>
      <div class="ds-grid-card">
        <div id="msGrid"></div>
      </div>
    </div>
  </div>
</div>

{{-- ══ 메시지 유형 ══ --}}
<div id="pnlTpl" class="ms-panel">
  <div class="ds-grid-section">
    <div class="ds-grid-bar">
      <div class="ds-grid-bar-left">
        <span class="ds-grid-total">메시지 유형</span>
        <span class="ds-grid-hint">여기서 고친 내용은 처방전 검수 화면의 SMSㆍ알림톡 팝오버에도 그대로 나옵니다.</span>
      </div>
      <div class="ds-grid-bar-right">
        <button type="button" class="ds-btn ds-btn-primary" onclick="msTplNew()">유형 추가</button>
      </div>
    </div>
    <div class="ds-grid-card" style="padding:16px;">
      <div id="msTplManage" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>
  </div>
</div>

{{-- ══ 발송 이력 ══ --}}
<div id="pnlHist" class="ms-panel">
  <div class="ds-grid-section">
    <div class="ds-grid-bar">
      <div class="ds-grid-bar-left">
        <span class="ds-grid-total">최근 <b>{{ count($histories) }}</b>건</span>
        <span class="ds-grid-hint">한 줄이 '한 번 누른 발송' 입니다. 받는 사람이 여럿이면 함께 묶입니다.</span>
      </div>
    </div>
    <div class="ds-grid-card">
      <div id="msHistGrid"></div>
    </div>
  </div>
</div>

{{-- ── 메시지 유형 편집 창 ── --}}
<div id="msTplBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="msTplClose()"></div>
<div id="msTplModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:520px;max-width:94vw;background:var(--bg-card);border:1px solid var(--primary);
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <span id="msTplTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">메시지 유형</span>
    <button onclick="msTplClose()" style="background:none;border:none;cursor:pointer;color:var(--gray-0);font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:4px;">발송 수단</label>
        <select id="msTplChannel" class="form-control">
          <option value="sms">문자(SMS)</option>
          <option value="alimtalk">카카오 알림톡</option>
        </select>
      </div>
      <div>
        <label class="ds-field-label" style="display:block;margin-bottom:4px;">코드</label>
        <input type="text" id="msTplCode" class="form-control" maxlength="60" placeholder="order_confirmed" />
      </div>
    </div>
    <div id="msTplCodeNote" style="display:none;font-size:11px;color:var(--alert-500);line-height:1.6;">
      알림톡 코드는 <b>카카오에 등록한 템플릿코드</b >와 같아야 실제로 발송됩니다.
    </div>
    <div>
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">이름</label>
      <input type="text" id="msTplLabel" class="form-control" maxlength="100" placeholder="주문 확정" />
    </div>
    <div>
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">설명</label>
      <input type="text" id="msTplDesc" class="form-control" maxlength="200" placeholder="언제 쓰는 유형인지" />
    </div>
    <div>
      <label class="ds-field-label" style="display:block;margin-bottom:4px;">본문</label>
      <textarea id="msTplBody" class="form-control" rows="6" style="font-size:13px;line-height:1.7;"></textarea>
      <div class="ms-count" style="margin-top:4px;">
        #{고객명} #{처방번호} #{주문번호} #{본인부담금} #{금액} #{운송장번호} 를 쓸 수 있습니다.
      </div>
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-700);">
      <input type="checkbox" id="msTplActive" checked /> 사용
    </label>
    <div id="msTplResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
    <div style="display:flex;justify-content:space-between;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" id="msTplDelete" onclick="msTplDelete()"
              style="color:var(--alert-500);border-color:var(--alert-100);">삭제</button>
      <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="msTplClose()">취소</button>
        <button type="button" class="btn btn-primary btn-sm" id="msTplSave" onclick="msTplSave()">저장</button>
      </div>
    </div>
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
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
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

  // ── 발송 이력 그리드 ──────────────────────────────────
  new wwGrid({
    el: document.getElementById('msHistGrid'),
    height: 'fit', editable: false, rowCheckbox: false, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: false, modified: false },
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
    box.style.border     = '1px solid ' + (ok ? 'var(--primary-200)' : '#fca5a5');
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
