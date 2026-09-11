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
      <label class="ds-field-label">서명 여부</label>
      <select name="signed" class="form-control form-select">
        <option value="">전체</option>
        <option value="y" @selected(request('signed') === 'y')>서명함</option>
        <option value="n" @selected(request('signed') === 'n')>아직</option>
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
    @perm('delegation-signs', 'create')
      <button type="button" class="ds-btn" onclick="document.getElementById('dlgCsv').click()">명단 올리기</button>
      <input type="file" id="dlgCsv" accept=".csv,text/csv" style="display:none;" onchange="dlgImport(this)">
    @endperm
    <button type="button" class="ds-btn" onclick="window.__dlgGrid?.downloadExcel()">엑셀 다운</button>
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
  <div id="dlgGrid" style="height:calc(100vh - 380px);"></div>
  {{-- 한 쪽에 백 줄씩. 삼천 줄을 한 번에 그리면 화면이 한참 멎는다. --}}
  <div style="padding:10px 12px;border-top:1px solid var(--border);">{{ $쪽->links() }}</div>
</div>

{{-- ── 발송 팝오버 ──────────────────────────────────────────
     주문 등록의 「서명 동의 SMS 발송」 창과 같은 모양이되 코드는 따로다.
     그쪽은 처방전에 묶여 있어 거래처만으로는 설 수 없다. --}}
<div id="dlgSendBack" class="modal-overlay" style="display:none;">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-head">
      <span>📨 위임장 서명 발송</span>
      <button type="button" class="modal-x" onclick="dlgSendClose()">×</button>
    </div>
    <div class="modal-body">
      <div class="rt-kv"><span>거래처</span><span id="dlgCustomer" style="font-weight:700;"></span></div>

      <div class="rt-kv"><span>판매처</span><span id="dlgDealer"></span></div>
      <div class="rt-kv"><span>받을 번호</span><span id="dlgPhone" style="font-weight:700;"></span></div>

      <div style="margin-top:12px;">
        <label class="ds-field-label">이름</label>
        <input type="text" id="dlgName" class="form-control" maxlength="100">
      </div>

      <div style="margin-top:12px;">
        <label class="ds-field-label">보낼 글</label>
        <pre id="dlgPreview" style="margin:4px 0 0;padding:9px 11px;background:var(--gray-50);
             border:1px solid var(--border);border-radius:8px;font-size:12px;white-space:pre-wrap;"></pre>
      </div>

      <div id="dlgWarn" style="display:none;margin-top:10px;padding:8px 11px;border-radius:8px;
           background:var(--danger-light);border:1px solid var(--alert-100);color:var(--danger);
           font-size:12px;font-weight:700;"></div>
    </div>
    <div class="modal-foot">
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
    height: 'fit', editable: false, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: 'No',            name: 'no',       width: 64,  align: 'right', sortable: true },
      { header: '거래처명',       name: 'customer', width: 120, sortable: true },
      { header: '전화번호',       name: 'phone',    width: 130 },
      {
        header: '위임장 발송', name: 'send', width: 90, align: 'center',
        renderer: (v, row) => 단추('발송', () => dlgSendOpen(row.id), !row.can_send),
      },
      { header: '위임장 서명 여부',     name: 'delegation', width: 118, align: 'center', renderer: 여부 },
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
      { header: '상태',                  name: 'status',    width: 84,  align: 'center', sortable: true },

      /* ── 명단에 딸려 온 칸 (2026-09-11 보탬) ──────────────
         보낼 차례를 정하는 근거다 — 다음 재구매가 가까운 사람부터, 진행중인 건은
         뒤로. 여태 표에만 있고 화면에는 없어 엑셀을 따로 열어 보아야 했다. */
      { header: '판매처',          name: 'dealer',     width: 150, sortable: true },
      { header: '다음재구매가능일', name: 'repurchase', width: 126, align: 'center', sortable: true },
      { header: '마지막 등록일',    name: 'registered', width: 118, align: 'center', sortable: true },
      { header: '처방기간',        name: 'rx_days',    width: 80,  align: 'right',  sortable: true },
      { header: '마지막 구매확정일', name: 'confirmed',  width: 128, align: 'center', sortable: true },
      { header: 'Status',          name: 'src_status', width: 80,  align: 'center', sortable: true },
      { header: '처방여부',        name: 'rx_type',    width: 110, align: 'center', sortable: true },
      { header: '자격',            name: 'benefit',    width: 74,  align: 'center', sortable: true },
      { header: '마지막 판매상태',  name: 'sale',       width: 118, align: 'center', sortable: true },
    ],
    data: 줄,
  });
  window.__dlgGrid = grid;

  // ── 명단 올리기 ─────────────────────────────────────────
  window.dlgImport = async function (input) {
    const f = input.files?.[0];
    if (!f) return;
    input.value = '';

    const fd = new FormData();
    fd.append('file', f);

    try {
      const res = await fetch(@json(route('delegation-signs.import')), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
        body: fd,
      });
      const out = await res.json();

      if (!out.success) { showToast(out.message || '올리지 못했습니다.', 'danger', 6000); return; }

      showToast(out.message, 'success', 6000);
      if (out.errors?.length) showToast(out.errors.join(' / '), 'warning', 9000);
      setTimeout(() => location.reload(), 1200);
    } catch (e) {
      showToast('올리지 못했습니다 — ' + e.message, 'danger', 6000);
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
    document.getElementById('dlgDealer').textContent = d.dealer || '—';
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
    document.getElementById('dlgSendBack').style.display = 'flex';
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
    document.getElementById('dlgSendBack').style.display = 'none';
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
