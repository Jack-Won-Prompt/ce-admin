{{-- resources/views/prescription-consents/index.blade.php --}}
@extends('layouts.app')

@section('title', '위임장 서명')
@section('page-title', '위임장 서명')
{{-- 시안(266:66) Frame 48101452 — 「홈 - 위임장 서명」 두 마디.
     홈 x336(w11) · 구분자 '-' x355(w6) · 화면명 x369(w55), 12/500 · 마디 사이 8.
     구분자는 이 @section 안에 있어 이 파일이 고칠 자리다(전역 app.blade.php 에는 규칙이 없다).
     한 덩어리 글월로 두면 화면명이 x357.5 로 11.5 짧게 붙는다 — 마디를 나눠 gap 8 을 준다.
     @section 의 인자형은 Laravel 이 e() 로 escape 하므로 블록형으로 쓴다. --}}
@section('breadcrumb')<span class="bc-trail"><span>홈</span><span>-</span><span>위임장 서명</span></span>@endsection

@push('styles')
<style>
  /* 빵부스러기 마디 사이 8 — 시안 266:66 Frame 48101452 (홈 x336 · '-' x355 · 화면명 x369) */
  .page-breadcrumb .bc-trail { display: inline-flex; align-items: center; gap: 8px; vertical-align: middle; }
  /* 검색 카드 3필드 — 이 시안(266:66)은 9열 균등 분배가 아니다.
     Frame 48101591/48101593/48101592 실측: 검색어 295 · 기간(서명일) 295 · 서명 여부 140,
     필드 간격 16, 세 필드가 왼쪽에 몰리고 필드 영역(1384)의 오른쪽은 빈칸으로 남는다.
     전역 9열 그리드(span-3/4/2 · gap 12)로는 453/608/298 이 되어 시안보다 훨씬 넓다. */
  .ds-filter-card .ds-filter-fields { display: flex; gap: 16px; }
  .ds-filter-card .ds-filter-field.span-3 { flex: 0 1 295px; }
  .ds-filter-card .ds-filter-field.span-4 { flex: 0 1 295px; }
  .ds-filter-card .ds-filter-field.span-2 { flex: 0 1 140px; }

  /* 행 안의 작은 버튼 — 시안 266:66 실측(Frame 74×28)에 맞춘다.
     h28 · r8 · pad 0/12 · gap 6 · 12px/500 lh19 · 글자 gray-1000 · bd 1px gray-200 · bg 흰색.
     버튼이 28 이 되면서 본문 행도 10+28+10 = 48(시안값)로 따라온다. */
  .pc-cellbtns { display: flex; gap: 6px; align-items: center; }
  /* 컬럼 폭이 시안보다 좁다 — 다운로드 275(시안 320) · 위임동의 95(시안 100).
     내용폭이 약 1406 아래로 내려가면 컬럼이 정의폭까지 좁아지는데,
     그 폭에서 시안 규격 버튼 묶음(275.2)은 셀 안쪽 250 을 25.2 넘고
     '재발송'(75.1)은 셀 안쪽 70 을 5.1 넘는다. 셀은 overflow:hidden 이라 그대로 잘린다.
     (1440·1600 노트북에서 사이드바를 펼치면 바로 이 폭이 된다.)
     컬럼 정의는 퍼블리셔가 손대는 자리가 아니므로, 잘리는 대신 접히게만 둔다 —
     묶음은 다음 줄로 넘기고, 홀버튼은 셀 안쪽까지만 차지한다.
     내용폭 1568(시안값)에서는 여유 34.8 이라 아무 것도 달라지지 않는다.
     제대로 고치려면 컬럼 폭 275 → 320 · 95 → 100 이 필요하다(로직 담당). */
  .pc-cellbtns { flex-wrap: wrap; }
  .pc-cellbtn {
    display: inline-flex; align-items: center; gap: 6px;
    min-width: 0; max-width: 100%; overflow: hidden;
    height: 28px; padding: 0 12px;
    font-size: 12px; font-weight: 500; line-height: 19px;
    white-space: nowrap; border: 1px solid var(--gray-200); border-radius: 8px;
    background: var(--gray-0); color: var(--gray-1000); cursor: pointer; text-decoration: none;
  }
  /* 아이콘 12×12 — 크기를 안 박으면 버튼 글자 크기를 그대로 물려받는다 */
  .pc-cellbtn i { font-size: 12px; line-height: 1; flex-shrink: 0; }
  .pc-cellbtn:hover  { border-color: var(--primary); color: var(--primary); }
  /* 원래 서명 PNG=초록 · PDF=빨강 · SMS=남색이었다. 시안 색은 primary/alert 두 램프뿐이라
     '받기' 세 개는 중립(gray-1000)으로 두고, 동작 버튼(발송)만 글자를 primary 로 남긴다.
     테두리는 시안에서 다섯 개 모두 gray-200 으로 같다. */
  .pc-cellbtn.is-png { color: var(--gray-1000); border-color: var(--gray-200); }
  .pc-cellbtn.is-pdf { color: var(--gray-1000); border-color: var(--gray-200); }
  .pc-cellbtn.is-sms { color: var(--primary); border-color: var(--gray-200); }
  /* 받을 수 없는 서류는 눌리지 않게 두되, 왜 없는지 title 로 알린다.
     시안 만료 행은 투명도를 쓰지 않는다 — 바탕만 gray-50, 글자·아이콘만 gray-300 이고
     테두리는 gray-200 그대로다(opacity 를 주면 테두리까지 같이 흐려진다). */
  .pc-cellbtn[disabled] {
    background: var(--gray-50); border-color: var(--gray-200); color: var(--gray-300);
    cursor: not-allowed;
  }
  .pc-cellbtn[disabled]:hover { background: var(--gray-50); border-color: var(--gray-200); color: var(--gray-300); }
</style>
@endpush

@section('content')

@php $curStatus = request('status'); @endphp

{{-- ── 상태 탭 ── --}}
{{-- 상단 칩 대신 검색 필터에서 고른다. 칩이 한 줄을 통째로 차지하면서도
     고르는 일은 필터가 함께 했다 — 같은 일을 두 자리에서 하고 있었다. --}}


{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('prescription-consents.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field">
      {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select" onchange="this.form.submit()">
        <option value="">전체 ({{ $statusCounts->sum() }})</option>
        @foreach($statuses as $key => $label)
          <option value="{{ $key }}" {{ $curStatus === $key ? 'selected' : '' }}>
            {{ $label }}@if(($statusCounts[$key] ?? 0) > 0) ({{ $statusCounts[$key] }})@endif
          </option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="이름ㆍ전화번호ㆍ처방번호">
    </div>
    <div class="ds-filter-field span-4">
      <label class="ds-field-label">기간(서명일)</label>
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
    {{-- 시안(266:66) Frame 48101589 — 초기화 60×32 x1760 · 검색 60×32 x1828 · gap 8, 초기화는 늘 있다.
         조건을 걷어낸다. 같은 라우트로 되돌아가는 링크라 조건 없이도 하는 일이 같다 --}}
    <a href="{{ route('prescription-consents.index', array_filter(['status' => $curStatus])) }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 결과바에 있던 단추를 찾는 자리로 옮겼다 — 목록 위에 띠를 하나 더 두지 않는다 --}}
    <button type="button" class="ds-btn" onclick="window.__consentGrid?.downloadExcel()">엑셀 저장</button>
    <button type="button" class="ds-btn ds-btn-primary" onclick="pcOpenNew()">
      <i class="fa-solid fa-paper-plane"></i> 신규 위임동의 전송
    </button>
  </div>
</form>

<div class="ds-grid-section">
  {{-- 시안은 그리드가 흰 카드(r12) 안에 들어간다 --}}
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;"><i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건)</span></button>
      </div>
    <div id="consentGrid"></div>
  </div>
</div>

{{-- ── 신규 위임동의: 이름과 전화번호를 등록해 보낸다 ── --}}
<div id="pcNewBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1190;"
     onclick="pcCloseNew()"></div>
<div id="pcNewModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
     width:400px;max-width:94vw;background:var(--bg-card);border:1px solid var(--primary);
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1191;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-file-signature" style="color:var(--gray-0);font-size:14px;flex-shrink:0;"></i>
    <span style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">신규 위임동의 SMS 발송</span>
    <button onclick="pcCloseNew()" style="background:none;border:none;cursor:pointer;color:var(--gray-0);font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    <p style="font-size:12px;color:var(--text-secondary);margin:0;line-height:19px;">
      환자에게 <strong>건강보험 급여 위임동의</strong> 링크를 SMS로 발송합니다.<br>
      입력한 이름과 번호로 <strong>처방전이 한 건 새로 만들어집니다.</strong><br>
      <span style="color:var(--alert-500);font-weight:700;">링크는 발송 후 30분간만 유효합니다.</span>
    </p>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">수신 번호</label>
      <input type="text" class="form-control" id="pcNewMobile" placeholder="010-XXXX-XXXX"
             oninput="pcNewPreview()" />
    </div>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">이름</label>
      <input type="text" class="form-control" id="pcNewName" maxlength="50" placeholder="이름"
             oninput="pcNewPreview()" />
    </div>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
      <div id="pcNewPreviewBox" style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;
           padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:18px;color:var(--gray-700);font-family:monospace;"></div>
    </div>
    <div id="pcNewResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
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
     width:400px;max-width:94vw;background:var(--bg-card);border:1px solid var(--primary);
     border-radius:var(--radius-lg);box-shadow:0 12px 40px rgba(0,0,0,.22);z-index:1201;">
  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:10px 14px;
       display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-file-signature" style="color:var(--gray-0);font-size:14px;flex-shrink:0;"></i>
    <span id="pcSmsTitle" style="font-size:13px;font-weight:700;color:var(--gray-0);flex:1;">위임동의 SMS 발송</span>
    <button onclick="pcCloseSms()" style="background:none;border:none;cursor:pointer;color:var(--gray-0);font-size:16px;line-height:1;">&#215;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
    {{-- 원래 주황 계열 하드코딩 경고 박스였다. 시안 경고색은 alert 램프 하나뿐이라 그쪽으로 옮겼다. --}}
    <div id="pcSmsNotice" style="display:none;background:var(--alert-50);border:1px solid var(--alert-100);border-radius:8px;
         padding:10px 12px;font-size:12px;color:var(--alert-500);line-height:19px;"></div>
    <p style="font-size:12px;color:var(--text-secondary);margin:0;line-height:19px;">
      환자에게 <strong>건강보험 급여 위임동의</strong> 링크를 SMS로 발송합니다.<br>
      <span style="color:var(--alert-500);font-weight:700;">링크는 발송 후 30분간만 유효합니다.</span>
    </p>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">처방번호</label>
      <input type="text" class="form-control" id="pcSmsRx" readonly
             style="background:var(--gray-100);font-family:monospace;" />
    </div>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">수신 번호</label>
      <input type="text" class="form-control" id="pcSmsMobile" placeholder="010-XXXX-XXXX"
             oninput="pcSmsPreview()" />
    </div>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">이름</label>
      <input type="text" class="form-control" id="pcSmsName" maxlength="50"
             oninput="pcSmsPreview()" />
    </div>
    <div>
      <label class="ds-field-label" style="margin-bottom:4px;display:block;">발송 메시지 미리보기</label>
      <div id="pcSmsPreviewBox" style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;
           padding:10px 12px;font-size:11px;white-space:pre-wrap;line-height:18px;color:var(--gray-700);font-family:monospace;"></div>
    </div>
    <div id="pcSmsResult" style="display:none;padding:10px 12px;border-radius:8px;font-size:12px;font-weight:500;"></div>
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
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, summary: false,
    // 엑셀 저장은 결과바로 옮겼다(동작은 downloadExcel() 동일).
    toolbar: false,
    // 하단 상태바는 시안에 없다 — 전체·선택 건수는 조회 결과 탭 이름과 검색 단추 줄에 있다.
    footer: false,
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
        // 서명이 끝난 건은 공단에 위임 등록을 해야 한다. 그 입력을 돕는 창을 연다.
        header: '공단 등록', name: 'nhis_assist', width: 100, sortable: false, exportable: false,
        renderer: (v, row) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'pc-cellbtn is-pdf';
          if (!row.rx_number || row.status_key !== 'agreed') {
            btn.disabled = true;
            btn.title = row.rx_number ? '서명이 끝난 뒤에 등록합니다' : '연결된 처방전이 없습니다';
          } else {
            btn.title = '공단 위임 등록 입력 지원 창을 엽니다';
            btn.addEventListener('click', (e) => {
              e.stopPropagation();
              window.open(`{{ url('/nhis/assist/delegation') }}/${row.rx_number}`,
                          'nhis_delegation_' + row.rx_number,
                          'width=980,height=1000,scrollbars=yes,resizable=yes');
            });
          }
          const i = document.createElement('i');
          i.className = 'fa-solid fa-clipboard-list';
          btn.appendChild(i);
          btn.appendChild(document.createTextNode(' 위임 등록'));
          return btn;
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
  window.dsBindSelCount(grid, 'pcSelCount');

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
    if (!name)   { showToast('이름을 입력해주세요.', 'warning'); return; }
    if (mobile.replace(/\D/g, '').length < 9) {
      showToast('수신 번호를 다시 확인해주세요.', 'warning'); return;
    }

    const btn = document.getElementById('pcNewSend');
    btn.disabled  = true;
    btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:999px;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

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
        // 초록(success)은 시안에 없다 — 성공 강조는 primary 램프로 표현한다.
        box.style.background = 'var(--primary-50)';
        box.style.color      = 'var(--primary-600)';
        box.style.border     = '1px solid var(--primary-200)';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지<br>`
          + `<span style="font-weight:500;">처방전 <b>${data.rx_number ?? ''}</b> 이(가) 만들어졌습니다. 잠시 후 목록을 새로 불러옵니다.</span>`;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        setTimeout(() => location.reload(), 1600);
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid var(--alert-100)';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      box.style.background = 'var(--danger-light)';
      box.style.color      = 'var(--danger)';
      box.style.border     = '1px solid var(--alert-100)';
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
    btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:999px;animation:spin .7s linear infinite;vertical-align:middle;"></span> 발송 중...';

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
        // 초록(success)은 시안에 없다 — 성공 강조는 primary 램프로 표현한다.
        box.style.background = 'var(--primary-50)';
        box.style.color      = 'var(--primary-600)';
        box.style.border     = '1px solid var(--primary-200)';
        box.innerHTML = `<i class="fa-solid fa-circle-check"></i> SMS 발송 완료 — 유효 시간: <b>${data.expires_at}</b>까지<br>`
          + '<span style="font-weight:500;">잠시 후 목록을 새로 불러옵니다.</span>';
        btn.innerHTML = '<i class="fa-solid fa-check"></i> 발송 완료';
        // 발송하면 '대기 중' 건이 하나 새로 생긴다. 목록에 반영하려면 다시 읽어야 한다.
        setTimeout(() => location.reload(), 1200);
      } else {
        box.style.background = 'var(--danger-light)';
        box.style.color      = 'var(--danger)';
        box.style.border     = '1px solid var(--alert-100)';
        box.textContent      = data.message ?? '발송 실패';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 재시도';
      }
    } catch (e) {
      box.style.background = 'var(--danger-light)';
      box.style.color      = 'var(--danger)';
      box.style.border     = '1px solid var(--alert-100)';
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
      window.ceOpenTab(url, '주문 - ' + row.rx_number, 'file-edit-02');
    } else {
      window.open(url, '_blank', 'noopener');
    }
  });
})();
</script>
@endpush
