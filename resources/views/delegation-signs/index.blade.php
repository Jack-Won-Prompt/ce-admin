@extends('layouts.app')

@section('title', '위임장 서명')
@section('page-title', '위임장 서명')
@section('breadcrumb', '홈 - 운영 데이터 - 위임장 서명')

@section('help-title', '위임장 서명 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>
    처방ㆍ주문과 잇지 않고 위임장 서명만 따로 받아 모으는 화면입니다.
    거래처 관리와도 이어지지 않아, 이름과 전화번호를 이 화면이 스스로 들고 있습니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">밟는 차례</div>
  <div class="help-item"><div class="help-item-text"><strong>① 명단 올리기</strong>
    받은 명단(위임 필요 리스트)을 그대로 올립니다. 첫 줄의 머리글을 읽어 칸을 맞추므로
    차례가 달라도 됩니다. 쉼표ㆍ탭 어느 쪽으로 나뉘어도 읽고, 한글 인코딩도 가립니다.
    같은 이름ㆍ같은 번호가 이미 있으면 줄을 새로 세우지 않고 명단 값만 새로 적습니다.</div></div>
  <div class="help-item"><div class="help-item-text"><strong>② 발송</strong>
    줄의 ［발송］을 누르면 보낼 글을 미리 보여 줍니다. 문자가 나가고 30분 동안 열립니다.</div></div>
  <div class="help-item"><div class="help-item-text"><strong>③ 서명</strong>
    환자가 휴대폰 본인확인을 마치고 서류 셋을 읽은 뒤 서명합니다.</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">다시 보낼 때</div>
  <div class="help-tip"><i class="bx bx-history"></i>
    이미 서명을 받은 줄에 다시 보내도 <b>받아 둔 서명은 지우지 않습니다.</b>
    새 서명이 들어오는 그때 덮습니다 — 보내다 만 건에서 서명이 사라지면 되돌릴 수 없습니다.</div>
</div>
@endsection

@push('styles')
<style>
  /* 발송 창의 라벨 줄 — 라벨과 값이 붙어 나오지 않게 (2026-09-11) */
  .dlg-kv { display:flex; align-items:baseline; gap:10px; padding:6px 0;
            border-bottom:1px dashed var(--border); font-size:13px; }
  .dlg-kv:last-of-type { border-bottom:0; }
  .dlg-kv > span:first-child { width:76px; flex-shrink:0; color:var(--text-muted); font-size:12px; }
  .dlg-field { margin-top:14px; }
  .dlg-help  { margin-top:4px; color:var(--text-muted); font-size:11px; }
  .dlg-pre   { margin:4px 0 0; padding:10px 12px; background:var(--gray-50);
               border:1px solid var(--border); border-radius:8px; font-size:12px;
               line-height:1.6; white-space:pre-wrap; word-break:break-all;
               font-family:inherit; color:var(--text-primary); }
  .dlg-warn  { margin-top:14px; padding:9px 12px; border-radius:8px;
               background:var(--danger-light); border:1px solid var(--alert-100);
               color:var(--danger); font-size:12px; font-weight:700; line-height:1.5; }

  /* ── 팝오버 (2026-09-14 지시) ─────────────────────────────────────────
     여태 이 화면의 창 셋은 공통 모달이었다. 모달은 화면 전체를 45% 어둡게 덮는데,
     보내기 전에 뒤의 표를 함께 보고 싶은 자리라 그 어둠이 걸리적거렸다.

     껍데기만 바꾼다 — 안쪽 뼈대(.modal-hd/.modal-bd/.modal-ft)는 그대로 두어
     생김새가 다른 화면의 창과 어긋나지 않게 한다.

     자리는 자바스크립트가 fixed 로 잡는다. 표의 굴림 자리(.cg-wrap)에 overflow:auto
     가 걸려 있어 absolute 로 두면 표 밖으로 나오지 못하고 잘린다. */
  .dlg-pop { display: none; position: fixed; z-index: 1000; }
  .dlg-pop.open { display: block; }
  .dlg-pop > .modal-box {
    max-width: none; width: 380px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 34px rgba(13,27,42,.20);
    max-height: calc(100vh - 40px);
  }
  /* 어느 줄에서 열린 창인지 꼬리로 가리킨다 — 표에 백 줄이 서 있어 필요하다.
     꼬리의 가로 자리는 자바스크립트가 --arrow-x 로 넣는다. */
  .dlg-pop > .modal-box::before {
    content: ''; position: absolute; left: var(--arrow-x, 24px);
    width: 12px; height: 12px; background: var(--bg-card);
    border-left: 1px solid var(--border); border-top: 1px solid var(--border);
    transform: rotate(45deg);
  }
  .dlg-pop.below > .modal-box::before { top: -7px; }
  .dlg-pop.above > .modal-box::before { bottom: -7px; transform: rotate(225deg); }

  /* 쪽 넘김 줄 (2026-09-11) — 부트스트랩 것을 쓰지 못해 이 화면에서 그린다 */
  /* 단추는 줄 가운데, 건수는 왼쪽 끝 (2026-09-11 지시). 건수를 흐름에서 빼야
     단추가 줄의 참가운데에 선다 — 함께 두면 건수 폭만큼 오른쪽으로 밀린다. */
  .dlg-pager { position:relative; display:flex; align-items:center; gap:6px;
               flex-wrap:wrap; justify-content:center;
               padding:10px 12px; border-top:1px solid var(--border); }
  .dlg-pager-info { position:absolute; left:12px; top:50%; transform:translateY(-50%);
                    font-size:12px; color:var(--text-muted);
                    font-variant-numeric:tabular-nums; }
  .dlg-pg { min-width:34px; padding:0 10px; font-variant-numeric:tabular-nums; }
  .dlg-pg.is-now { border-color:var(--primary); color:var(--primary);
                   background:var(--primary-light); font-weight:700; cursor:default; }
  .dlg-pg.is-off { color:var(--gray-400); background:var(--gray-50); cursor:default; }

  /* 상태 칸 색 표시 (2026-09-13) — 발송 전ㆍ서명 대기ㆍ서명 완료ㆍ동의 거절을
     글자만으로 읽지 않고 한눈에 가린다. 오류 기록 화면의 알약 모양과 같은 꼴. */
  .dlg-st { display:inline-flex; align-items:center; height:22px; padding:0 9px;
            border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
  .dlg-st-pending  { background:var(--gray-100);    color:var(--gray-600); }
  .dlg-st-sent     { background:var(--warning-50);  color:#B54708; }
  .dlg-st-signed   { background:var(--primary-50);  color:var(--primary); }
  .dlg-st-declined { background:var(--danger-light); color:var(--danger); }
</style>
@endpush

@section('content')

<form method="GET" action="{{ route('delegation-signs.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간 (등록일)</label>
      <div style="display:flex;align-items:center;gap:4px;">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span style="color:var(--text-muted);">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select">
        <option value="">전체 상태</option>
        @foreach(\App\Models\DelegationSign::상태 as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">판매처</label>
      <select name="dealer" class="form-control form-select">
        <option value="">전체 판매처</option>
        @foreach($판매처 as $ㅍ)
          <option value="{{ $ㅍ }}" @selected(request('dealer') === $ㅍ)>{{ $ㅍ }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">전송 담당자</label>
      <select name="sender" class="form-control form-select">
        <option value="">전체 담당자</option>
        @foreach($보낸이 as $ㅅ)
          <option value="{{ $ㅅ->sent_by_id }}" @selected((string) request('sender') === (string) $ㅅ->sent_by_id)>
            {{ $ㅅ->sent_by_name }}
          </option>
        @endforeach
      </select>
    </div>
    {{-- 명단에서 온 줄과 손으로 보낸 줄을 갈라 본다 (2026-09-14 지시) --}}
    <div class="ds-filter-field">
      <label class="ds-field-label">구분</label>
      <select name="source" class="form-control form-select">
        <option value="">전체 구분</option>
        @foreach(\App\Models\DelegationSign::갈래 as $k => $label)
          <option value="{{ $k }}" @selected(request('source') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="거래처명ㆍ판매처ㆍ전화번호">
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('delegation-signs.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 받는 사람에게 무엇이 가는지는 제 번호로 한 번 받아 보는 것이 가장 확실하다
         (2026-09-14 지시). 명단에 없는 번호로도 보낼 수 있어야 하므로 목록의 줄과
         묶지 않고 필터 줄에 세운다. --}}
    <button type="button" id="btnDlgPreview" class="ds-btn" onclick="dlgDirectOpen(this)">미리 보기</button>
    {{-- 엑셀 받기는 보고 있는 백 줄이 아니라 걸러 낸 전부를 내려받는다
         (2026-09-11 지시). 그래서 화면의 wwGrid 가 아니라 서버로 간다. --}}
    <a class="ds-btn" href="{{ route('delegation-signs.export', request()->query()) }}" data-no-loading>엑셀 다운</a>
  </div>
</form>

<div class="ds-grid-card">
  <div class="ds-grid-head">
    <span class="ds-grid-title">
      조회 결과
      {{-- 「몇째 줄부터 몇째 줄까지 / 모두 몇 줄」 (2026-09-11 지시). 총 건수를
           괄호로 앞에 또 적으면 한 줄에 같은 수가 두 번 나온다. --}}
      <span style="color:var(--text-muted);font-weight:400;">
        · {{ number_format($쪽->firstItem() ?? 0) }}–{{ number_format($쪽->lastItem() ?? 0) }}
        / {{ number_format($쪽->total()) }}
      </span>
    </span>
  </div>
  <div id="dlgGrid" style="height:calc(100vh - 434px);"></div>

  {{-- 한 쪽에 백 줄씩. 삼천 줄을 한 번에 그리면 화면이 한참 멎는다.

       라라벨이 주는 쪽 넘김 그림(부트스트랩 5)은 좁은 화면 것과 넓은 화면 것
       두 벌을 함께 내보내고 d-sm-flex 로 하나만 세운다. 그런데 공통 레이아웃이
       부트스트랩 뒤에서 .d-none 을 !important 로 다시 적어 두어, 미디어 쿼리
       안의 d-sm-flex 가 밀리고 두 벌 모두 숨은 채로 높이 0 이 된다.
       그래서 쪽 단추는 이 화면의 .ds-btn 으로 직접 그린다. --}}
  @php
    $이번쪽 = $쪽->currentPage();
    $끝쪽   = $쪽->lastPage();
    /* 가운데를 이번 쪽에 맞추되 다섯 칸을 늘 채운다 — 끝머리에서도 폭이 안 흔들린다 */
    $첫칸 = max(1, min($이번쪽 - 2, $끝쪽 - 4));
    $끝칸 = min($끝쪽, $첫칸 + 4);
  @endphp
  <div class="dlg-pager">
    <span class="dlg-pager-info">
      {{ number_format($쪽->firstItem() ?? 0) }}–{{ number_format($쪽->lastItem() ?? 0) }}
      / 총 {{ number_format($쪽->total()) }}건
    </span>

    @if ($쪽->onFirstPage())
      <span class="ds-btn dlg-pg is-off">처음</span>
      <span class="ds-btn dlg-pg is-off">이전</span>
    @else
      <a class="ds-btn dlg-pg" href="{{ $쪽->url(1) }}">처음</a>
      <a class="ds-btn dlg-pg" href="{{ $쪽->previousPageUrl() }}">이전</a>
    @endif

    @for ($ㅉ = $첫칸; $ㅉ <= $끝칸; $ㅉ++)
      @if ($ㅉ === $이번쪽)
        <span class="ds-btn dlg-pg is-now">{{ $ㅉ }}</span>
      @else
        <a class="ds-btn dlg-pg" href="{{ $쪽->url($ㅉ) }}">{{ $ㅉ }}</a>
      @endif
    @endfor

    @if ($쪽->hasMorePages())
      <a class="ds-btn dlg-pg" href="{{ $쪽->nextPageUrl() }}">다음</a>
      <a class="ds-btn dlg-pg" href="{{ $쪽->url($끝쪽) }}">마지막</a>
    @else
      <span class="ds-btn dlg-pg is-off">다음</span>
      <span class="ds-btn dlg-pg is-off">마지막</span>
    @endif
  </div>
</div>

{{-- ── 발송 팝오버 ──────────────────────────────────────────
     주문 등록의 「서명 동의 SMS 발송」 창과 같은 모양이되 코드는 따로다.
     그쪽은 처방전에 묶여 있어 거래처만으로는 설 수 없다. --}}
<div id="dlgSendBack" class="dlg-pop">
  <div class="modal-box sm">
    <div class="modal-hd">
      <span class="modal-title">위임장 서명 발송</span>
      <button type="button" class="modal-close" onclick="dlgSendClose()" aria-label="닫기">&times;</button>
    </div>
    <div class="modal-bd">
      <div class="dlg-kv"><span>거래처</span><b id="dlgCustomer"></b></div>
      {{-- 판매처는 늘 콜로플라스트 코리아다 (2026-09-11 지시). 명단의 판매처는
           하이메드ㆍ(주)서호메디코로 갈리지만, 링크를 받은 사람이 보는 위임 상대는
           한 곳뿐이다 — 보내기 전 창에도 그 한 곳을 적어 말이 어긋나지 않게 한다. --}}
      <div class="dlg-kv"><span>판매처</span><span id="dlgDealer">{{ \App\Models\DelegationSign::위임받는곳 }}</span></div>
      <div class="dlg-kv"><span>받을 번호</span><b id="dlgPhone"></b></div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgName">이름</label>
        <input type="text" id="dlgName" class="form-control" maxlength="100">
        <div class="dlg-help">환자가 보는 문자에 그대로 적힙니다.</div>
      </div>

      <div class="dlg-field">
        <label class="ds-field-label">보낼 글</label>
        <pre id="dlgPreview" class="dlg-pre"></pre>
      </div>

      <div id="dlgWarn" class="dlg-warn" style="display:none;"></div>
    </div>
    <div class="modal-ft">
      <button type="button" class="ds-btn" onclick="dlgSendClose()">취소</button>
      <button type="button" class="ds-btn ds-btn-primary" id="dlgSendBtn" onclick="dlgSend()">발송</button>
    </div>
  </div>
</div>

{{-- ── 연락처 수정 (2026-09-14 지시) ──────────────────────────────────────
     명단에는 보호자 번호가 없다. 환자가 문자를 받지 못하는 것은 담당자가 통화로
     알게 되므로, 그 자리에서 고쳐 바로 다시 보낼 수 있어야 한다. --}}
<div id="dlgContactBack" class="dlg-pop">
  <div class="modal-box sm">
    <div class="modal-hd">
      <span class="modal-title">연락처 수정</span>
      <button type="button" class="modal-close" onclick="dlgContactClose()" aria-label="닫기">&times;</button>
    </div>
    <div class="modal-bd">
      <div class="dlg-kv"><span>거래처</span><b id="dlgCtCustomer"></b></div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgCtPhone">환자 전화번호</label>
        <input type="tel" id="dlgCtPhone" class="form-control" maxlength="20" placeholder="010-0000-0000">
      </div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgCtGuardian">보호자 전화번호</label>
        <input type="tel" id="dlgCtGuardian" class="form-control" maxlength="20" placeholder="010-0000-0000">
      </div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgCtMain">Main contact</label>
        <select id="dlgCtMain" class="form-control form-select">
          @foreach(\App\Models\DelegationSign::연락 as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
        <div class="dlg-help">여기서 선택한 쪽의 번호로 서명 링크가 발송됩니다.</div>
      </div>

      <div id="dlgCtWarn" class="dlg-warn" style="display:none;"></div>
    </div>
    <div class="modal-ft">
      <button type="button" class="ds-btn" onclick="dlgContactClose()">취소</button>
      <button type="button" class="ds-btn ds-btn-primary" id="dlgCtBtn" onclick="dlgContactSave()">저장</button>
    </div>
  </div>
</div>

{{-- ── 미리 보기 — 이름ㆍ번호를 적어 직접 보낸다 (2026-09-14 지시) ──────────
     위 발송 창과 같은 모양이되, 목록의 줄을 받지 않고 두 칸을 직접 받는다.
     받는 사람이 무엇을 보는지 확인하려는 것이라 **정말로 문자가 나간다**. --}}
<div id="dlgDirectBack" class="dlg-pop">
  <div class="modal-box sm">
    <div class="modal-hd">
      <span class="modal-title">위임장 서명 미리 보기</span>
      <button type="button" class="modal-close" onclick="dlgDirectClose()" aria-label="닫기">&times;</button>
    </div>
    <div class="modal-bd">
      <div class="dlg-kv"><span>판매처</span><span>{{ \App\Models\DelegationSign::위임받는곳 }}</span></div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgDirectName">이름</label>
        <input type="text" id="dlgDirectName" class="form-control" maxlength="100" placeholder="홍길동">
        <div class="dlg-help">받는 사람이 보는 문자와 서명 화면에 그대로 적힙니다.</div>
      </div>

      <div class="dlg-field">
        <label class="ds-field-label" for="dlgDirectPhone">받을 번호</label>
        <input type="tel" id="dlgDirectPhone" class="form-control" maxlength="20" placeholder="010-0000-0000">
        <div class="dlg-help">이 번호로 서명 링크가 발송됩니다. 확인하려면 본인 번호를 입력하십시오.</div>
      </div>

      <div class="dlg-field">
        <label class="ds-field-label">보낼 글</label>
        <pre id="dlgDirectPreview" class="dlg-pre"></pre>
      </div>

      {{-- 명단은 검증된 번호지만 여기는 손으로 친다. 지금 설정이 무엇인지 모르고
           누르는 것이 가장 위험하므로 화면에 적어 둔다. --}}
      @php $문자갈래 = config('popbill.sms_mode'); @endphp
      <div class="dlg-warn" style="display:block;">
        @if($문자갈래 === 'live')
          지금 문자 발송이 <b>실제</b>입니다 — 입력한 번호로 실제 발송됩니다.
        @elseif($문자갈래 === 'redirect')
          문자 발송이 <b>우리에게만</b>이지만, 여기서 입력한 번호로는 <b>그대로 발송됩니다</b> —
          직접 입력한 번호라 변경하지 않습니다.
        @else
          지금 문자 발송이 <b>시뮬레이션</b>입니다 — 문자가 발송되지 않습니다.
        @endif
      </div>

      <div id="dlgDirectWarn" class="dlg-warn" style="display:none;"></div>
    </div>
    <div class="modal-ft">
      <button type="button" class="ds-btn" onclick="dlgDirectClose()">취소</button>
      <button type="button" class="ds-btn ds-btn-primary" id="dlgDirectBtn" onclick="dlgDirectSend()">전송</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const 줄 = @json($줄);
  const BASE = @json(url('delegation-signs'));

  /* 여부 셋은 ○/× 대신 읽을 수 있는 말로 세운다 — 표를 읽는 사람은 기호를
     다시 뜻으로 옮겨야 한다. */
  const 여부 = (v) => {
    const el = document.createElement('span');
    el.textContent = v;
    if (v === '동의함')            { el.style.color = 'var(--primary)'; el.style.fontWeight = '700'; }
    else if (v === '동의하지 않음') { el.style.color = '#B54708'; }
    else                            { el.style.color = 'var(--gray-400)'; }
    return el;
  };

  /* ── 팝오버 자리잡기 (2026-09-14 지시) ──────────────────────────────────
     창 셋이 함께 쓴다. 모달이 아니라 누른 단추 옆에 붙으므로, 단추가 움직이면
     창도 따라가야 한다 — 표를 굴리면 줄이 위아래로 움직인다.

     wwGrid 는 가상 스크롤이 아니라 백 줄이 모두 DOM 에 있다. 굴려도 단추가
     사라지지 않으므로 따라가게 만들 수 있다. 다만 표 밖으로 나가면 가리킬 것이
     없어지므로 그때는 닫는다. */
  const 팝 = (() => {
    let 창 = null, 기준 = null, 보내는중 = false;
    const 틈 = 8;

    function 자리() {
      if (!창 || !기준) return;
      const r = 기준.getBoundingClientRect();

      /* 단추가 표 밖으로 굴러 나갔으면 닫는다 — 엉뚱한 줄을 가리키게 된다 */
      if (r.bottom < 0 || r.top > window.innerHeight) { 닫기(); return; }

      const box = 창.querySelector('.modal-box');
      const w = box.offsetWidth || 380;
      const h = box.offsetHeight || 300;

      /* 아래에 자리가 없으면 위로 뒤집는다 — 표 아랫줄에서 열면 화면 밖으로 나간다 */
      const 아래여유 = window.innerHeight - r.bottom - 틈;
      const 위 = 아래여유 < h && r.top - 틈 > 아래여유;
      창.classList.toggle('above', 위);
      창.classList.toggle('below', !위);
      창.style.top = 위 ? Math.max(틈, r.top - 틈 - h) + 'px' : (r.bottom + 틈) + 'px';

      /* 가로는 단추 가운데에 맞추되 화면을 넘지 않게 당긴다. 꼬리는 그만큼 되민다. */
      const 가운데 = r.left + r.width / 2;
      const left = Math.min(Math.max(틈, 가운데 - w / 2), window.innerWidth - w - 틈);
      창.style.left = left + 'px';
      box.style.setProperty('--arrow-x', Math.min(Math.max(가운데 - left - 6, 14), w - 26) + 'px');
    }

    function 열기(id, 단추요소) {
      닫기();
      창 = document.getElementById(id);
      /* 단추를 넘겨받지 못했으면(직접 부른 자리) 필터 줄의 ［미리 보기］에 붙인다 */
      기준 = 단추요소 || document.getElementById('btnDlgPreview');
      창.classList.add('open');
      자리();
      /* 그린 뒤 실제 높이로 한 번 더 — 처음에는 높이가 0 이라 뒤집기를 잘못 셈한다 */
      requestAnimationFrame(자리);
    }

    function 닫기() {
      if (보내는중) return;            // 보내는 중에는 닫지 않는다
      document.querySelectorAll('.dlg-pop.open').forEach(e => e.classList.remove('open'));
      창 = null; 기준 = null;
    }

    /* 보내는 동안에는 바깥을 눌러도 닫히지 않게 잠근다 — 문자가 나가는 일이라
       중간에 창이 사라지면 무엇이 어찌 되었는지 알 길이 없다. */
    const 잠금 = (v) => { 보내는중 = v; };

    window.addEventListener('resize', 자리);
    window.addEventListener('scroll', 자리, true);   // 표 안쪽 굴림도 받는다

    document.addEventListener('click', (e) => {
      if (!창) return;
      if (창.contains(e.target)) return;
      if (기준 && (기준 === e.target || 기준.contains(e.target))) return;
      닫기();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') 닫기(); });

    return { 열기, 닫기, 잠금 };
  })();

  const 단추 = (글, 눌림, 잠김) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'ds-btn ds-btn-sm';
    b.textContent = 글;
    b.style.height = '22px';
    b.style.padding = '0 8px';
    b.style.fontSize = '11px';
    if (잠김) { b.disabled = true; b.style.opacity = '.45'; }
    else      { b.onclick = (e) => { e.stopPropagation(); 눌림(b); }; }
    return b;
  };

  const grid = new wwGrid({
    el: document.getElementById('dlgGrid'),
    height: 'fit', editable: false, rowNumber: false, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: 'No',            name: 'no',       width: 64,  align: 'right', sortable: true },
      { header: '거래처명',       name: 'customer', width: 120, sortable: true },

      /* 번호가 둘이 되었다 (2026-09-14 지시). 환자가 문자를 받지 못하면 보호자로
         돌려 보내므로, 어느 쪽으로 보내는지(Main contact)를 두 번호 옆에 세운다. */
      { header: '환자 전화번호',   name: 'phone',    width: 130 },
      { header: '보호자 전화번호', name: 'guardian', width: 134 },

      {{-- 주민등록번호와 성년 구분 (2026-09-15 지시).

           위임은 만 19세 미만이면 법정대리인이 대신 한다. 그런데 명단에는 이름과
           번호뿐이라 보내기 전에 성년인지 알 수 없었다 — 미성년에게 보낸 링크는
           보호자 칸을 요구하며 그 자리에서 멈춘다.

           주민등록번호는 **가린 값**만 온다. 성년ㆍ미성년은 그것만으로 갈린다 —
           뒷자리 첫 숫자가 1800ㆍ1900ㆍ2000년대를 말해 주기 때문이다. --}}
      { header: '주민등록번호',   name: 'resident', width: 124 },
      {
        header: '성년/미성년', name: 'adult', width: 96, align: 'center', sortable: true,
        renderer: (v) => {
          if (!v) return '';                       // 주민등록번호가 없으면 빈칸이다
          const el = document.createElement('span');
          el.className = 'badge badge-' + (v === '미성년' ? 'warning' : 'secondary');
          el.textContent = v;
          return el;
        },
      },
      { header: '나이',           name: 'age',        width: 64,  align: 'right',  sortable: true },
      { header: '보호자 성명',     name: 'g_name',     width: 100, sortable: true },
      { header: '관계',           name: 'g_relation', width: 74,  align: 'center', sortable: true },
      { header: '보호자 생년월일', name: 'g_birth',    width: 118, align: 'center', sortable: true },
      {
        header: 'Main contact', name: 'contact', width: 104, align: 'center',
        renderer: (v, row) => {
          const el = document.createElement('span');
          el.textContent = v;
          /* 보호자로 돌려 둔 줄은 눈에 띄어야 한다 — 기본(환자)이 아니기 때문이다 */
          if (row.contact_code === 'guardian') { el.style.fontWeight = '700'; el.style.color = 'var(--bs-primary, #3C82C4)'; }
          return el;
        },
      },
      {
        header: '연락처 수정', name: 'edit', width: 92, align: 'center',
        renderer: (v, row) => 단추('수정', (b) => dlgContactOpen(row.id, b)),
      },
      {
        header: '위임장 발송', name: 'send', width: 90, align: 'center',
        renderer: (v, row) => 단추('발송', (b) => dlgSendOpen(row.id, b), !row.can_send),
      },

      /* 서명 여부는 ［발송］ 바로 뒤에 (2026-09-12 지시). 보냈는지와 받았는지를
         한자리에서 본다. */
      { header: '위임장 서명 여부',     name: 'delegation', width: 118, align: 'center', renderer: 여부 },

      /* ── 보낼지 말지를 가리는 근거 (2026-09-11 지시로 앞으로 옮김) ────────
         다음 재구매가 가까운 사람부터, 진행중인 건은 뒤로 — 그 판단을 ［발송］
         바로 옆에서 한다. 오른쪽 끝에 두었더니 볼 때마다 굴려야 했다. */
      { header: '판매처',          name: 'dealer',     width: 150, sortable: true },
      { header: '다음재구매가능일', name: 'repurchase', width: 126, align: 'center', sortable: true },
      { header: '마지막 등록일',    name: 'registered', width: 118, align: 'center', sortable: true },
      { header: '처방기간',        name: 'rx_days',    width: 80,  align: 'right',  sortable: true },
      { header: '마지막 구매확정일', name: 'confirmed',  width: 128, align: 'center', sortable: true },
      { header: '처방여부',        name: 'rx_type',    width: 110, align: 'center', sortable: true },
      { header: '자격',            name: 'benefit',    width: 74,  align: 'center', sortable: true },
      { header: '마지막 판매상태',  name: 'sale',       width: 118, align: 'center', sortable: true },

      /* ── 받은 결과 ──────────────────────────────────────────────────── */
      /* 발송 상태 (2026-09-11 지시로 서명 여부 뒤에 두었으나, 2026-09-12 서명
         여부만 ［발송］ 옆으로 옮기고 이 칸은 제자리에 둔다). */
      {
        header: '상태', name: 'status', width: 84, align: 'center', sortable: true,
        renderer: (v, row) => {
          const el = document.createElement('span');
          el.className = 'dlg-st dlg-st-' + (row.status_code || 'pending');
          el.textContent = v;
          return el;
        },
      },

      { header: '개인정보동의 서명 여부', name: 'privacy',   width: 138, align: 'center', renderer: 여부 },
      { header: '마케팅 활용 동의 여부', name: 'marketing', width: 138, align: 'center', renderer: 여부 },
      {
        header: '위임장 서명 이미지 확인', name: 'image', width: 146, align: 'center',
        renderer: (v, row) => 단추('이미지 보기',
          () => window.open(BASE + '/' + row.id + '/image', 'dlg_sign_' + row.id,
                            'width=620,height=420,scrollbars=yes,resizable=yes'),
          !row.has_sign),
      },
      { header: '위임장 서명 일자',       name: 'signed_at', width: 126, align: 'center', sortable: true },
      { header: '위임장 서명 전송 담당자', name: 'sender',    width: 128, align: 'center', sortable: true },

      /* 명단이 적어 보낸 Status — 지금은 모두 Active 다. 맨 뒤에 둔다. */
      { header: 'Status',          name: 'src_status', width: 80,  align: 'center', sortable: true },
@perm('delegation-signs', 'delete')

      /* 잘못 올라온 줄을 걷는 자리 (2026-09-11 지시) */
      {
        header: '삭제', name: 'del', width: 70, align: 'center',
        renderer: (v, row) => 단추('삭제', () => dlgDelete(row.id, row.customer, row.has_sign)),
      },
@endperm
    ],
    data: 줄,
  });
  window.__dlgGrid = grid;

  // ── 줄 삭제 ─────────────────────────────────────────────
  /* 되돌릴 수 없다. 서명까지 받은 줄이면 그림도 함께 사라진다는 것을 먼저 알린다. */
  window.dlgDelete = async function (id, 이름, 서명있나) {
    const 덧말 = 서명있나
      ? '\n\n이 줄에는 받아 둔 서명이 있습니다. 서명 그림도 함께 지웁니다.'
      : '';

    if (!await ceConfirm(이름 + ' 줄을 지웁니다.' + 덧말 + '\n\n되돌릴 수 없습니다.',
                         { title: '줄 삭제', tone: 'danger', confirmText: '삭제' })) return;

    try {
      const res = await fetch(BASE + '/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
      });
      const out = await res.json();

      if (!res.ok || !out.ok) { showToast(out.말 || '지우지 못했습니다.', 'danger', 6000); return; }

      showToast(out.말, 'success', 4000);
      setTimeout(() => location.reload(), 800);
    } catch (e) {
      showToast('지우지 못했습니다 — ' + e.message, 'danger', 6000);
    }
  };

  // ── 발송 팝오버 ─────────────────────────────────────────
  let 지금 = null;

  window.dlgSendOpen = async function (id, 단추요소) {
    const res = await fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } });
    const d = await res.json();
    if (!d.success) { showToast('불러오지 못했습니다.', 'danger'); return; }

    지금 = d;
    document.getElementById('dlgCustomer').textContent = d.customer;
    /* 어느 쪽 번호로 가는지 함께 적는다 — 보호자로 돌려 둔 줄이 섞여 있다 (2026-09-14) */
    document.getElementById('dlgPhone').textContent =
      d.phone ? d.phone + ' (' + d.contact + ')' : '—';
    /* 문자에 들어갈 이름이라 (E) 를 뗀 것을 세운다 (2026-09-14 지시) */
    document.getElementById('dlgName').value = d.name;

    const 경고 = document.getElementById('dlgWarn');
    if (d.signed) {
      경고.style.display = '';
      경고.textContent = '이미 서명을 받은 건입니다. 다시 보내도 받아 둔 서명은 지워지지 않고, 새 서명이 들어오면 그때 바뀝니다.';
    } else {
      경고.style.display = 'none';
    }

    미리보기();
    document.getElementById('dlgName').oninput = 미리보기;
    팝.열기('dlgSendBack', 단추요소);
  };

  /* 보낼 글은 서버가 지은 틀을 쓴다 (2026-09-14).
     여기서 같은 글을 따로 적어 두었더니 서버 문구와 따로 놀았고, 「30분」도 글자로
     박혀 있어 유효시간을 바꾸면 화면만 옛말을 했다. */
  const 문자틀 = @json($문자틀);
  /* 서버도 나가는 자리에서 (E) 를 떼므로 미리 보기도 같이 뗀다 —
     그러지 않으면 창에 보인 글과 실제로 나간 글이 다르다 (2026-09-14) */
  const 글짓기 = (이름) => 문자틀.replace('{이름}', String(이름).replace(/^\s*\(E\)\s*/, ''));

  function 미리보기() {
    const 이름 = document.getElementById('dlgName').value.trim() || (지금?.name ?? '');
    document.getElementById('dlgPreview').textContent = 글짓기(이름);
  }

  window.dlgSendClose = function () {
    팝.닫기();
    지금 = null;
  };

  window.dlgSend = async function () {
    if (!지금) return;
    const btn = document.getElementById('dlgSendBtn');
    BtnState.loading(btn, '보내는 중...');
    팝.잠금(true);
    try {
      const res = await fetch(BASE + '/' + 지금.id + '/send', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ name: document.getElementById('dlgName').value.trim() }),
      });
      const out = await res.json();

      if (!out.success) { 팝.잠금(false); BtnState.reset(btn); showToast(out.message, 'danger', 6000); return; }

      showToast(out.message + ' ' + out.expires_at + '까지 열려 있습니다.', 'success', 6000);
      팝.잠금(false);
      dlgSendClose();
      setTimeout(() => location.reload(), 1000);
    } catch (e) {
      팝.잠금(false);
      BtnState.reset(btn);
      showToast('보내지 못했습니다 — ' + e.message, 'danger', 6000);
    }
  };

  // ── 연락처 수정 ─────────────────────────────────────────
  let 고칠줄 = null;

  window.dlgContactOpen = async function (id, 단추요소) {
    const res = await fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } });
    const d = await res.json();
    if (!d.success) { showToast('불러오지 못했습니다.', 'danger'); return; }

    고칠줄 = d;
    document.getElementById('dlgCtCustomer').textContent = d.customer;
    document.getElementById('dlgCtPhone').value    = 번호꼴(d.patient_phone || '');
    document.getElementById('dlgCtGuardian').value = 번호꼴(d.guardian_phone || '');
    document.getElementById('dlgCtMain').value     = d.main_contact || 'patient';
    document.getElementById('dlgCtWarn').style.display = 'none';

    for (const id2 of ['dlgCtPhone', 'dlgCtGuardian']) {
      document.getElementById(id2).oninput = (e) => { e.target.value = 번호꼴(e.target.value); };
    }
    팝.열기('dlgContactBack', 단추요소);
  };

  window.dlgContactClose = function () {
    팝.닫기();
    고칠줄 = null;
  };

  window.dlgContactSave = async function () {
    if (!고칠줄) return;
    const 경고 = document.getElementById('dlgCtWarn');
    const btn = document.getElementById('dlgCtBtn');
    BtnState.loading(btn, '저장하는 중...');
    팝.잠금(true);
    try {
      const res = await fetch(BASE + '/' + 고칠줄.id + '/contact', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          phone:          document.getElementById('dlgCtPhone').value.replace(/\D/g, ''),
          guardian_phone: document.getElementById('dlgCtGuardian').value.replace(/\D/g, ''),
          main_contact:   document.getElementById('dlgCtMain').value,
        }),
      });
      const out = await res.json();

      if (!out.success) {
        팝.잠금(false);
        BtnState.reset(btn);
        경고.style.display = '';
        경고.textContent = out.message || '저장하지 못했습니다.';
        return;
      }

      showToast(out.message, 'success', 5000);
      팝.잠금(false);
      dlgContactClose();
      /* 고친 번호로 ［발송］이 열려야 한다 — 목록을 다시 그려 잠금을 푼다 */
      setTimeout(() => location.reload(), 800);
    } catch (e) {
      팝.잠금(false);
      BtnState.reset(btn);
      경고.style.display = '';
      경고.textContent = '저장하지 못했습니다 — ' + e.message;
    }
  };

  // ── 미리 보기 — 이름ㆍ번호를 적어 직접 보낸다 ───────────
  const 번호칸 = () => document.getElementById('dlgDirectPhone');
  const 이름칸 = () => document.getElementById('dlgDirectName');

  /* 치는 동안 010-0000-0000 꼴로 세운다 — 목록이 그 꼴로 보여 주므로
     적을 때도 같은 모양이어야 눈이 헷갈리지 않는다. */
  function 번호꼴(값) {
    const n = 값.replace(/\D/g, '').slice(0, 11);
    if (n.length < 4)  return n;
    if (n.length < 8)  return n.slice(0, 3) + '-' + n.slice(3);
    if (n.length < 11) return n.slice(0, 3) + '-' + n.slice(3, 6) + '-' + n.slice(6);
    return n.slice(0, 3) + '-' + n.slice(3, 7) + '-' + n.slice(7);
  }

  function 미리보기2() {
    document.getElementById('dlgDirectPreview').textContent =
      글짓기(이름칸().value.trim() || '○○○');
  }

  window.dlgDirectOpen = function (단추요소) {
    이름칸().value = '';
    번호칸().value = '';
    document.getElementById('dlgDirectWarn').style.display = 'none';
    미리보기2();
    이름칸().oninput = 미리보기2;
    번호칸().oninput = (e) => { e.target.value = 번호꼴(e.target.value); };
    팝.열기('dlgDirectBack', 단추요소);
    이름칸().focus();
  };

  window.dlgDirectClose = function () {
    팝.닫기();
  };

  window.dlgDirectSend = async function () {
    const 이름 = 이름칸().value.trim();
    const 번호 = 번호칸().value.replace(/\D/g, '');
    const 경고 = document.getElementById('dlgDirectWarn');

    if (!이름) { 경고.style.display = ''; 경고.textContent = '이름을 입력해 주십시오.'; 이름칸().focus(); return; }
    if (번호.length < 9 || 번호.length > 11) {
      경고.style.display = ''; 경고.textContent = '전화번호를 숫자 9~11자리로 입력해 주십시오.';
      번호칸().focus(); return;
    }
    경고.style.display = 'none';

    const btn = document.getElementById('dlgDirectBtn');
    BtnState.loading(btn, '보내는 중...');
    팝.잠금(true);
    try {
      const res = await fetch(BASE + '/send-direct', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ name: 이름, phone: 번호 }),
      });
      const out = await res.json();

      if (!out.success) {
        팝.잠금(false);
        BtnState.reset(btn);
        showToast(out.message || '보내지 못했습니다.', 'danger', 6000);
        return;
      }

      showToast(out.message + ' ' + out.expires_at + '까지 열려 있습니다.', 'success', 6000);
      팝.잠금(false);
      dlgDirectClose();
      setTimeout(() => location.reload(), 1000);
    } catch (e) {
      팝.잠금(false);
      BtnState.reset(btn);
      showToast('보내지 못했습니다 — ' + e.message, 'danger', 6000);
    }
  };
})();
</script>
@endpush
