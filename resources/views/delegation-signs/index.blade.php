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
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control"
             placeholder="거래처명ㆍ판매처ㆍ전화번호">
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ route('delegation-signs.index') }}" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    {{-- 엑셀 받기는 보고 있는 백 줄이 아니라 걸러 낸 전부를 내려받는다
         (2026-09-11 지시). 그래서 화면의 wwGrid 가 아니라 서버로 간다. --}}
    <a class="ds-btn" href="{{ route('delegation-signs.export', request()->query()) }}" data-no-loading>엑셀 다운</a>
  </div>
</form>

<div class="ds-grid-card">
  <div class="ds-grid-head">
    <span class="ds-grid-title">
      조회 결과(총 {{ number_format($쪽->total()) }}건)
      <span style="color:var(--text-muted);font-weight:400;">
        · {{ number_format($쪽->firstItem() ?? 0) }}–{{ number_format($쪽->lastItem() ?? 0) }} 보는 중
        ({{ $쪽->currentPage() }}/{{ $쪽->lastPage() }}쪽)
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
<div id="dlgSendBack" class="modal-overlay">
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

  const 단추 = (글, 눌림, 잠김) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'ds-btn ds-btn-sm';
    b.textContent = 글;
    b.style.height = '22px';
    b.style.padding = '0 8px';
    b.style.fontSize = '11px';
    if (잠김) { b.disabled = true; b.style.opacity = '.45'; }
    else      { b.onclick = (e) => { e.stopPropagation(); 눌림(); }; }
    return b;
  };

  const grid = new wwGrid({
    el: document.getElementById('dlgGrid'),
    height: 'fit', editable: false, rowNumber: false, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: 'No',            name: 'no',       width: 64,  align: 'right', sortable: true },
      { header: '거래처명',       name: 'customer', width: 120, sortable: true },
      { header: '전화번호',       name: 'phone',    width: 130 },
      {
        header: '위임장 발송', name: 'send', width: 90, align: 'center',
        renderer: (v, row) => 단추('발송', () => dlgSendOpen(row.id), !row.can_send),
      },

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
      { header: '위임장 서명 여부',     name: 'delegation', width: 118, align: 'center', renderer: 여부 },

      /* 발송 상태는 서명 여부 바로 뒤에 (2026-09-11 지시). 「—」로 비어 있는
         까닭이 「아직 안 보냈다」인지 「보냈는데 안 했다」인지 옆에서 읽힌다. */
      { header: '상태',                 name: 'status',     width: 84,  align: 'center', sortable: true },

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

  window.dlgSendOpen = async function (id) {
    const res = await fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } });
    const d = await res.json();
    if (!d.success) { showToast('불러오지 못했습니다.', 'danger'); return; }

    지금 = d;
    document.getElementById('dlgCustomer').textContent = d.customer;
    document.getElementById('dlgPhone').textContent = d.phone || '—';
    document.getElementById('dlgName').value = d.customer;

    const 경고 = document.getElementById('dlgWarn');
    if (d.signed) {
      경고.style.display = '';
      경고.textContent = '이미 서명을 받은 건입니다. 다시 보내도 받아 둔 서명은 지워지지 않고, 새 서명이 들어오면 그때 바뀝니다.';
    } else {
      경고.style.display = 'none';
    }

    미리보기();
    document.getElementById('dlgName').oninput = 미리보기;
    document.getElementById('dlgSendBack').classList.add('open');
  };

  function 미리보기() {
    const 이름 = document.getElementById('dlgName').value.trim() || (지금?.customer ?? '');
    document.getElementById('dlgPreview').textContent =
      '[콜로플라스트] ' + 이름 + '님\n'
      + '요양비 청구 위임장 전자서명 요청입니다.\n'
      + '서명 링크(30분 유효):\n'
      + '(발송할 때 만들어집니다)';
  }

  window.dlgSendClose = function () {
    document.getElementById('dlgSendBack').classList.remove('open');
    지금 = null;
  };

  window.dlgSend = async function () {
    if (!지금) return;
    const btn = document.getElementById('dlgSendBtn');
    BtnState.loading(btn, '보내는 중...');
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

      if (!out.success) { BtnState.reset(btn); showToast(out.message, 'danger', 6000); return; }

      showToast(out.message + ' ' + out.expires_at + '까지 열려 있습니다.', 'success', 6000);
      dlgSendClose();
      setTimeout(() => location.reload(), 1000);
    } catch (e) {
      BtnState.reset(btn);
      showToast('보내지 못했습니다 — ' + e.message, 'danger', 6000);
    }
  };
})();
</script>
@endpush
