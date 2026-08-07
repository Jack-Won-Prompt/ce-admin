{{-- resources/views/prescription-consents/index.blade.php --}}
@extends('layouts.app')

@section('title', '위임장 서명')
@section('page-title', '위임장 서명')
@section('breadcrumb', '홈 / 서류ㆍ동의 / 위임장 서명')

@push('styles')
<style>
  /* 행 안의 작은 버튼 — 셀 높이를 넘지 않게 둔다 */
  .pc-cellbtns { display: flex; gap: 4px; align-items: center; }
  .pc-cellbtn {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 7px; font-size: 11px; font-weight: 700; line-height: 1.5;
    white-space: nowrap; border: 1px solid var(--border); border-radius: 5px;
    background: #fff; color: var(--text-secondary); cursor: pointer; text-decoration: none;
  }
  .pc-cellbtn:hover  { border-color: var(--primary); color: var(--primary); }
  .pc-cellbtn.is-png { color: var(--success); border-color: #a7f3d0; }
  .pc-cellbtn.is-pdf { color: var(--danger);  border-color: #fecaca; }
  .pc-cellbtn.is-sms { color: #6366f1; border-color: #c7d2fe; }
  /* 받을 수 없는 서류는 눌리지 않게 두되, 왜 없는지 title 로 알린다 */
  .pc-cellbtn[disabled] { opacity: .38; cursor: not-allowed; border-color: var(--border); color: var(--text-muted); }
  .pc-cellbtn[disabled]:hover { border-color: var(--border); color: var(--text-muted); }
</style>
@endpush

@section('content')

@php $curStatus = request('status'); @endphp

{{-- ── 상태 탭 ── --}}
<div class="ds-chips">
  <a href="{{ route('prescription-consents.index', request()->except('status', 'page')) }}"
     class="ds-chip {{ !$curStatus ? 'active' : '' }}">
    전체 <span class="ds-chip-count">{{ $statusCounts->sum() }}</span>
  </a>
  @foreach($statuses as $key => $label)
    <a href="{{ route('prescription-consents.index', array_merge(request()->except('status','page'), ['status' => $key])) }}"
       class="ds-chip {{ $curStatus === $key ? 'active' : '' }}">
      {{ $label }} <span class="ds-chip-count">{{ $statusCounts[$key] ?? 0 }}</span>
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('prescription-consents.index') }}" class="ds-filter-card">
  @if($curStatus)
    <input type="hidden" name="status" value="{{ $curStatus }}">
  @endif
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="이름 · 전화번호 · 처방번호">
    </div>
    <div class="ds-filter-field span-4">
      <label class="ds-field-label">기간 (서명일)</label>
      <div class="ds-field-range">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">서명 여부</label>
      <select name="signed_only" class="form-control">
        <option value="">전체</option>
        <option value="1" {{ request('signed_only') ? 'selected' : '' }}>서명 있는 건만</option>
      </select>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('q') || request('date_from') || request('date_to') || request('signed_only'))
      <a href="{{ route('prescription-consents.index', array_filter(['status' => $curStatus])) }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-bar">
    <div class="ds-grid-bar-left">
      <span class="ds-grid-total">전체 <b>{{ number_format($total) }}</b>건</span>
      <span class="ds-grid-hint">
        각 행의 버튼으로 서류를 받고 위임동의를 다시 보냅니다.
        서류는 그 처방전의 <b>가장 최근 동의 건</b>으로 발행됩니다. 행을 <b>더블클릭</b>하면 해당 처방전이 새 탭으로 열립니다.
      </span>
    </div>
    <div class="ds-grid-bar-right">
      <button type="button" class="ds-btn ds-btn-primary" onclick="pcOpenNew()">
        <i class="fa-solid fa-paper-plane"></i> 신규 위임동의 전송
      </button>
    </div>
  </div>

  <div id="consentGrid"></div>
</div>

{{-- ── 신규 위임동의: 이름과 전화번호를 등록해 보낸다 ── --}}
<div id="pcNewBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="pcCloseNew()"></div>
<div id="pcNewModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:400px;max-width:94vw;background:var(--bg-card);border:1px solid #6366f1;
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:#6366f1;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-file-signature" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:#fff;flex:1;">신규 위임동의 SMS 발송</span>
    <button onclick="pcCloseNew()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    <p style="font-size:12px;color:var(--text-secondary);margin:0;line-height:1.6;">
      환자에게 <strong>건강보험 급여 위임동의</strong> 링크를 SMS로 발송합니다.<br>
      입력한 이름과 번호로 <strong>처방전이 한 건 새로 만들어집니다.</strong><br>
      <span style="color:var(--warning);font-weight:700;">링크는 발송 후 30분간만 유효합니다.</span>
    </p>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">수신 번호</label>
      <input type="text" class="form-control" id="pcNewMobile" placeholder="010-XXXX-XXXX"
             style="font-size:13px;" oninput="pcNewPreview()" />
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">환자명</label>
      <input type="text" class="form-control" id="pcNewName" maxlength="50" placeholder="환자명"
             style="font-size:13px;" oninput="pcNewPreview()" />
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
      <div id="pcNewPreviewBox" style="background:#f8fafc;border:1px solid var(--border);border-radius:6px;
           padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:1.8;color:#374151;font-family:monospace;"></div>
    </div>
    <div id="pcNewResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:600;"></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button class="btn btn-outline btn-sm" onclick="pcCloseNew()">취소</button>
      <button class="btn btn-primary btn-sm" id="pcNewSend" onclick="pcSendNew()">
        <i class="fa-solid fa-paper-plane"></i> 발송
      </button>
    </div>
  </div>
</div>

{{-- ── 위임동의 SMS 발송 (처방전 검수 화면과 같은 내용) ── --}}
<div id="pcSmsBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1200;"
     onclick="pcCloseSms()"></div>
<div id="pcSmsModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:400px;max-width:94vw;background:var(--bg-card);border:1px solid #6366f1;
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1201;">
  <div style="background:#6366f1;border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-file-signature" style="color:#fff;font-size:15px;flex-shrink:0;"></i>
    <span id="pcSmsTitle" style="font-size:13px;font-weight:700;color:#fff;flex:1;">위임동의 SMS 발송</span>
    <button onclick="pcCloseSms()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    <div id="pcSmsNotice" style="display:none;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;
         padding:10px 12px;font-size:12px;color:#c2410c;line-height:1.6;"></div>
    <p style="font-size:12px;color:var(--text-secondary);margin:0;line-height:1.6;">
      환자에게 <strong>건강보험 급여 위임동의</strong> 링크를 SMS로 발송합니다.<br>
      <span style="color:var(--warning);font-weight:700;">링크는 발송 후 30분간만 유효합니다.</span>
    </p>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">처방번호</label>
      <input type="text" class="form-control" id="pcSmsRx" readonly
             style="background:var(--bg-secondary,#f8f9fa);font-size:13px;font-family:monospace;" />
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">수신 번호</label>
      <input type="text" class="form-control" id="pcSmsMobile" placeholder="010-XXXX-XXXX"
             style="font-size:13px;" oninput="pcSmsPreview()" />
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">환자명</label>
      <input type="text" class="form-control" id="pcSmsName" maxlength="50"
             style="font-size:13px;" oninput="pcSmsPreview()" />
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
      <div id="pcSmsPreviewBox" style="background:#f8fafc;border:1px solid var(--border);border-radius:6px;
           padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:1.8;color:#374151;font-family:monospace;"></div>
    </div>
    <div id="pcSmsResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:600;"></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button class="btn btn-outline btn-sm" onclick="pcCloseSms()">취소</button>
      <button class="btn btn-primary btn-sm" id="pcSmsSend" onclick="pcSendSms()">
        <i class="fa-solid fa-paper-plane"></i> 발송
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const CONSENT_BASE = @json(rtrim(config('app.consent_public_url', config('app.url')), '/'));

  /* 셀 안의 버튼 하나. 받을 수 없으면 왜 없는지 title 로 알린다. */
  function cellBtn({ label, icon, cls, url, reason }) {
    const el = document.createElement(url ? 'a' : 'button');
    el.className = 'pc-cellbtn' + (cls ? ' ' + cls : '');
    if (url) {
      el.href = url;
      el.target = '_blank';
      el.rel = 'noopener';
      // 서류는 처방전 단위로 발행된다. 같은 처방전에 동의가 여러 건이면 최신 것이 나온다.
      el.title = label + ' 받기 (해당 처방전의 최신 동의 건)';
    } else {
      el.type = 'button';
      el.disabled = true;
      el.title = reason;
    }
    const i = document.createElement('i');
    i.className = icon;
    el.appendChild(i);
    el.appendChild(document.createTextNode(' ' + label));
    return el;
  }

  /* 받을 수 없는 이유 — 상태를 보고 고른다 */
  function why(row, kind) {
    if (row.status_key !== 'agreed') return '동의 완료 건이 아닙니다 (' + row.status + ')';
    if (kind === 'png') return '서명 이미지가 없습니다';
    if (kind === 'consent') return '위임동의서 PDF 가 아직 만들어지지 않았습니다';
    return '연결된 처방전이 없습니다';
  }

  const grid = new wwGrid({
    el: document.getElementById('consentGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '상태',      name: 'status',    width: 80,  sortable: true, align: 'center' },
      { header: '서명자',    name: 'name',      width: 90,  sortable: true },
      { header: '전화번호',  name: 'mobile',    width: 125, sortable: true },
      { header: '처방번호',  name: 'rx_number', width: 135, sortable: true },
      { header: '서명일시',  name: 'signed_at', width: 125, sortable: true },
      { header: '본인확인',  name: 'identity',  width: 75,  align: 'center' },
      { header: '서명',      name: 'signature', width: 55,  align: 'center' },
      {
        header: '다운로드', name: 'download', width: 275, sortable: false, exportable: false,
        renderer: (v, row) => {
          const wrap = document.createElement('div');
          wrap.className = 'pc-cellbtns';
          wrap.appendChild(cellBtn({ label: '서명 PNG', icon: 'bx bx-image-alt', cls: 'is-png',
            url: row.png_url, reason: why(row, 'png') }));
          wrap.appendChild(cellBtn({ label: '위임동의서', icon: 'bx bx-file', cls: 'is-pdf',
            url: row.consent_pdf, reason: why(row, 'consent') }));
          wrap.appendChild(cellBtn({ label: '위임장', icon: 'bx bx-file-blank', cls: 'is-pdf',
            url: row.delegation_pdf, reason: why(row, 'delegation') }));
          return wrap;
        },
      },
      {
        header: '위임동의', name: 'action', width: 95, sortable: false, exportable: false,
        renderer: (v, row) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'pc-cellbtn is-sms';
          if (!row.sms_url) {
            btn.disabled = true;
            btn.title = '연결된 처방전이 없습니다';
          } else {
            btn.title = row.status_key === 'agreed' ? '다시 발송합니다 (기존 서명은 남습니다)' : '위임동의 SMS 발송';
            btn.addEventListener('click', (e) => { e.stopPropagation(); pcOpenSms(row); });
          }
          const i = document.createElement('i');
          i.className = 'fa-solid fa-paper-plane';
          btn.appendChild(i);
          btn.appendChild(document.createTextNode(row.status_key === 'agreed' ? ' 재발송' : ' 발송'));
          return btn;
        },
      },
      { header: '요청일시',  name: 'requested', width: 125, sortable: true },
    ],
    data: @json($gridData),
  });
  window.__consentGrid = grid;

  // ── 신규 위임동의: 이름과 번호를 등록해 보낸다 ─────────
  const NEW_URL = @json(route('prescription-consents.store'));

  window.pcOpenNew = function () {
    document.getElementById('pcNewMobile').value = '';
    document.getElementById('pcNewName').value   = '';
    const res = document.getElementById('pcNewResult');
    res.style.display = 'none';
    const btn = document.getElementById('pcNewSend');
    btn.disabled  = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 발송';
    pcNewPreview();
    document.getElementById('pcNewBackdrop').style.display = 'block';
    document.getElementById('pcNewModal').style.display    = 'block';
    document.getElementById('pcNewMobile').focus();
  };

  window.pcCloseNew = function () {
    document.getElementById('pcNewBackdrop').style.display = 'none';
    document.getElementById('pcNewModal').style.display    = 'none';
  };

  window.pcNewPreview = function () {
    const name = (document.getElementById('pcNewName').value || '').trim() || '환자';
    const base = CONSENT_BASE.replace('http://', 'https://');
    document.getElementById('pcNewPreviewBox').textContent =
      `[콜로플라스트] ${name}님\n건강보험 급여 위임동의 서명 요청입니다.\n서명 링크(30분 유효):\n${base}/consent/(링크)`;
  };

  window.pcSendNew = async function () {
    const mobile = document.getElementById('pcNewMobile').value.trim();
    const name   = document.getElementById('pcNewName').value.trim();
    if (!name)   { showToast('환자명을 입력해주세요.', 'warning'); return; }
    if (mobile.replace(/\D/g, '').length < 9) {
      showToast('수신 번호를 다시 확인해주세요.', 'warning'); return;
    }

    const btn = document.getElementById('pcNewSend');
    btn.disabled  = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

    const box = document.getElementById('pcNewResult');
    box.style.display = 'block';
    try {
      const res = await fetch(NEW_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ mobile, name }),
      });
      const data = await res.json();
      if (data.success) {
        box.style.background = 'var(--success-light)';
        box.style.color      = 'var(--success)';
        box.style.border     = '1px solid #86efac';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지<br>`
          + `<span style="font-weight:500;">처방전 <b>${data.rx_number ?? ''}</b> 이(가) 만들어졌습니다. 잠시 후 목록을 새로 불러옵니다.</span>`;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        setTimeout(() => location.reload(), 1600);
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid #fca5a5';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      box.style.background = 'var(--danger-light)';
      box.style.color      = 'var(--danger)';
      box.style.border     = '1px solid #fca5a5';
      box.textContent      = '네트워크 오류가 발생했습니다.';
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
    }
  };

  // ── 위임동의 SMS ───────────────────────────────────────
  let _pcRow = null;

  window.pcOpenSms = function (row) {
    _pcRow = row;
    document.getElementById('pcSmsRx').value     = row.rx_number || '';
    document.getElementById('pcSmsMobile').value = row.mobile || '';
    document.getElementById('pcSmsName').value   = row.name || '';
    const notice = document.getElementById('pcSmsNotice');
    if (row.status_key === 'agreed') {
      notice.style.display = 'block';
      notice.innerHTML = '<i class="fa-solid fa-circle-info"></i> <strong>이미 서명이 완료된 건입니다.</strong><br>'
        + '새 링크를 보내면 새 동의 건이 생기고, 지금 서명은 그대로 남습니다.';
    } else if (row.status_key === 'expired') {
      notice.style.display = 'block';
      notice.innerHTML = '<i class="fa-solid fa-rotate-right"></i> <strong>이전 동의 링크가 만료되었습니다.</strong><br>'
        + '새로운 동의 링크를 발송합니다. 이전 링크는 더 이상 사용할 수 없습니다.';
    } else {
      notice.style.display = 'none';
    }
    // 행에서 눌렀으면 이미 있는 동의 건 위이므로 재발송, 신규 선택이면 첫 발송이다.
    document.getElementById('pcSmsTitle').textContent =
      row.status_key ? '위임동의 SMS 재발송' : '위임동의 SMS 발송';
    const res = document.getElementById('pcSmsResult');
    res.style.display = 'none';
    const btn = document.getElementById('pcSmsSend');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 발송';
    pcSmsPreview();
    document.getElementById('pcSmsBackdrop').style.display = 'block';
    document.getElementById('pcSmsModal').style.display    = 'block';
    document.getElementById('pcSmsMobile').focus();
  };

  window.pcCloseSms = function () {
    document.getElementById('pcSmsBackdrop').style.display = 'none';
    document.getElementById('pcSmsModal').style.display    = 'none';
  };

  window.pcSmsPreview = function () {
    const name = (document.getElementById('pcSmsName').value || '').trim() || '환자';
    const base = CONSENT_BASE.replace('http://', 'https://');
    document.getElementById('pcSmsPreviewBox').textContent =
      `[콜로플라스트] ${name}님\n건강보험 급여 위임동의 서명 요청입니다.\n서명 링크(30분 유효):\n${base}/consent/(링크)`;
  };

  window.pcSendSms = async function () {
    if (!_pcRow || !_pcRow.sms_url) return;
    const mobile = document.getElementById('pcSmsMobile').value.trim();
    const name   = document.getElementById('pcSmsName').value.trim();
    if (mobile.replace(/\D/g, '').length < 9) {
      showToast('수신 번호를 다시 확인해주세요.', 'warning'); return;
    }

    const btn = document.getElementById('pcSmsSend');
    btn.disabled  = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

    const box = document.getElementById('pcSmsResult');
    box.style.display = 'block';
    try {
      const res = await fetch(_pcRow.sms_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ mobile, name }),
      });
      const data = await res.json();
      if (data.success) {
        box.style.background = 'var(--success-light)';
        box.style.color      = 'var(--success)';
        box.style.border     = '1px solid #86efac';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지<br>`
          + '<span style="font-weight:500;">잠시 후 목록을 새로 불러옵니다.</span>';
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        // 발송하면 '대기 중' 건이 하나 새로 생긴다. 목록에 반영하려면 다시 읽어야 한다.
        setTimeout(() => location.reload(), 1200);
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid #fca5a5';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      box.style.background = 'var(--danger-light)';
      box.style.color      = 'var(--danger)';
      box.style.border     = '1px solid #fca5a5';
      box.textContent      = '네트워크 오류가 발생했습니다.';
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
    }
  };

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('pcSmsModal').style.display === 'block') { pcCloseSms(); return; }
    if (document.getElementById('pcNewModal').style.display === 'block') { pcCloseNew(); }
  });

  // 행 더블클릭 → 해당 처방전을 새 탭으로 (셀 안의 버튼 클릭은 제외)
  document.getElementById('consentGrid').addEventListener('dblclick', function (e) {
    if (e.target.closest('a, button, input')) return;
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (!row || !row.rx_number) return;
    window.getSelection()?.removeAllRanges();
    const url = @json(url('/prescriptions')) + '/' + encodeURIComponent(row.rx_number);
    if (typeof window.ceOpenTab === 'function') {
      window.ceOpenTab(url, '처방전 관리 - ' + row.rx_number, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  });
})();
</script>
@endpush
