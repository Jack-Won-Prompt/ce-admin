@extends('layouts.app')

@section('title', '환자 문의')
@section('page-title', '환자 문의')
@section('breadcrumb', '홈 - 지원 - 환자 문의')

@push('styles')
<style>
  /* 팝업 — 목록에서 더블클릭하면 그 자리에서 연다. 다른 화면으로 건너가면 어떤 조건으로
     찾고 있었는지가 끊기고, 돌아오려면 다시 찾아야 한다. */
  .iq-back { position:fixed; inset:0; background:rgba(17,24,39,.45); z-index:1200;
             display:none; align-items:center; justify-content:center; padding:24px; }
  .iq-back.on { display:flex; }
  .iq-modal { background:var(--bg-card); border-radius:var(--radius-lg); width:min(760px, 100%);
              max-height:calc(100vh - 48px); overflow:auto; box-shadow:0 20px 48px rgba(0,0,0,.24); }
  .iq-hd { padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; font-weight:700;
           display:flex; align-items:center; gap:8px; position:sticky; top:0; background:var(--bg-card); }
  .iq-hd .grow { flex:1; }
  .iq-bd { padding:14px 16px 18px; }

  /* 환자가 앱에서 올린 것은 읽기만 한다(시안의 하늘색). 담당자가 적는 자리와 색으로 가른다. */
  .iq-grid { display:grid; grid-template-columns:96px 1fr 96px 1fr; gap:1px; background:var(--border);
             border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-bottom:14px; }
  .iq-grid > div { background:var(--bg-card); padding:8px 10px; font-size:13px; min-height:36px; }
  .iq-grid .k { background:#EAF2FB; font-weight:600; color:var(--gray-1000); }
  .iq-grid .v { background:#F5F9FE; }
  .iq-grid .full { grid-column:2 / -1; }
  .iq-grid .wide { grid-column:1 / -1; }

  .iq-work { display:flex; flex-direction:column; gap:10px; }
  .iq-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .iq-row label { font-size:12px; font-weight:600; color:var(--gray-700); min-width:64px; }
  .iq-work textarea { width:100%; min-height:84px; line-height:1.6; }
  .iq-hint { font-size:11px; color:var(--text-muted); }
</style>
@endpush

@section('content')

{{-- 검색 필터 — 시안의 조회키: 날짜(from~to) · 이름 · 분류 --}}
<form method="GET" action="{{ route('inquiries.index') }}" class="ds-filter-card">
  <div class="ds-filter-fields">
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">일시</label>
      <div style="display:flex;gap:6px;align-items:center;">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        <span style="color:var(--text-muted);">~</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">이름</label>
      <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="환자 이름">
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">분류</label>
      <select name="category" class="form-control form-select">
        <option value="">전체 분류</option>
        @foreach(\App\Models\Inquiry::CATEGORIES as $k => $label)
          <option value="{{ $k }}" @selected(request('category') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">상태</label>
      <select name="status" class="form-control form-select">
        <option value="">전체 상태</option>
        @foreach(\App\Models\Inquiry::STATUSES as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">검색어</label>
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 · 내용">
      </div>
    </div>
  </div>
  <div class="ds-filter-actions">
    @if(request('status') || request('category') || request('search') || request('name') || request('date_from') || request('date_to'))
      <a href="{{ route('inquiries.index') }}" class="ds-btn">초기화</a>
    @endif
    <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-search"></i> 검색</button>
    <button type="button" class="ds-btn" onclick="window.__inquiryGrid?.downloadExcel()">엑셀 저장</button>
    <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">
      <i class="bx bx-pencil"></i> 문의 접수
    </a>
  </div>
</form>

<div class="ds-grid-section">
  <div class="ds-grid-card">
      <div class="pnl-tabs">
        <button type="button" class="pnl-tab active" onclick="return false;">
          <i class="fa-solid fa-list"></i> 조회 결과<span class="pnl-tab-cnt">(총 {{ number_format($total) }}건@if($pendingCount) · 접수 {{ $pendingCount }}@endif)</span>
        </button>
      </div>
    <div id="inquiryGrid"></div>
  </div>
</div>

{{-- 처리 팝업 ────────────────────────────────────────── --}}
<div class="iq-back" id="iqBack">
  <div class="iq-modal">
    <div class="iq-hd">환자 문의 <span class="grow"></span>
      <button type="button" class="ds-btn ds-btn-sm" onclick="iqClose()">닫기</button>
    </div>
    <div class="iq-bd">
      {{-- 환자가 올린 것 — 읽기만 한다 --}}
      <div class="iq-grid">
        <div class="k">일시</div>      <div class="v" id="iqDate"></div>
        <div class="k">이름</div>      <div class="v" id="iqAsker"></div>
        <div class="k">회신방식</div>  <div class="v" id="iqChannel"></div>
        <div class="k">연락처</div>    <div class="v" id="iqContact"></div>
        <div class="k">분류</div>      <div class="v" id="iqCategory"></div>
        <div class="k">파일</div>      <div class="v" id="iqFiles"></div>
        <div class="k">제목</div>      <div class="v full" id="iqTitle"></div>
        <div class="k">문의내용</div>  <div class="v full" id="iqBody" style="white-space:pre-wrap;"></div>
      </div>

      {{-- 담당자가 적는 자리 --}}
      <div class="iq-work">
        <div class="iq-row">
          <label>상태</label>
          <select id="iqStatus" class="form-control form-select" style="max-width:140px;">
            @foreach(\App\Models\Inquiry::STATUSES as $k => $label)
              <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
          </select>
          <label style="min-width:48px;">처리자</label>
          <span id="iqHandler" style="font-size:13px;"></span>
          <label style="min-width:60px;">처리일시</label>
          <span id="iqHandledAt" style="font-size:13px;"></span>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);">답변</label>
          <textarea id="iqAnswer" class="form-control"
                    placeholder="환자 앱·웹에 그대로 보입니다"></textarea>
          <div class="iq-hint">환자 앱·웹에 노출됩니다.</div>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);">조치사항</label>
          <textarea id="iqAction" class="form-control"
                    placeholder="예: 전화 요청하여 통화로 상세 내용 설명드림"></textarea>
          <div class="iq-hint">담당자 메모입니다 — 환자에게는 보이지 않습니다.</div>
        </div>
        <div class="iq-row" style="justify-content:flex-end;">
          <button type="button" class="ds-btn" onclick="iqClose()">취소</button>
          <button type="button" class="ds-btn ds-btn-primary" onclick="iqSave(this)">저장</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#inquiryGrid', title: '환자 문의 목록', body: '환자가 앱에서 올린 문의와 담당자가 대신 접수한 문의가 함께 섭니다. 행을 <b>더블클릭</b>하면 처리 팝업이 열립니다.' },
  { selector: '.btn-primary', title: '문의 접수', body: '전화로 받은 문의는 <b>문의 접수</b>로 대신 적습니다. 이때 회신방식(앱·문자·전화)을 함께 고릅니다.' },
];
</script>
<script>
(function () {
  const DETAIL = @json(url('inquiries'));
  const CSRF   = document.querySelector('meta[name="csrf-token"]')?.content;

  /* 시안의 칸 그대로 — 일시 · 문의자(ID · 이름) · 분류 · 제목 · 문의사항 · 회신방식 ·
     파일첨부 · 연락처 · 답변 · 처리자 · 처리일시. 상태는 그 사이에 끼워 둔다. */
  const grid = new wwGrid({
    el: document.getElementById('inquiryGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true, toolbar: false,
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '일시',     name: 'date',       width: 130, align: 'center', sortable: true },
      { header: '문의자 ID', name: 'asker_id',  width: 150, sortable: true },
      { header: '이름',     name: 'asker',      width: 90,  align: 'center', sortable: true },
      { header: '분류',     name: 'category',   width: 80,  align: 'center', sortable: true },
      { header: '제목',     name: 'title',      width: 200, sortable: true },
      { header: '문의사항', name: 'body',       width: 260 },
      { header: '회신방식', name: 'channel',    width: 80,  align: 'center', sortable: true },
      { header: '파일',     name: 'files',      width: 60,  align: 'center' },
      { header: '연락처',   name: 'contact',    width: 120, align: 'center' },
      { header: '답변',     name: 'answer',     width: 240 },
      { header: '상태',     name: 'status',     width: 70,  align: 'center', sortable: true },
      { header: '처리자',   name: 'handler',    width: 90,  align: 'center', sortable: true },
      { header: '처리일시', name: 'handled_at', width: 130, align: 'center', sortable: true },
    ],
    data: @json($gridData),
  });
  window.__inquiryGrid = grid;

  let openId = null;

  /* wwGrid 에는 on() 이 없다 — 다른 목록 화면과 같이 셀에서 행 번호를 읽는다. */
  document.getElementById('inquiryGrid').addEventListener('dblclick', function (e) {
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row?.id) iqOpen(row.id);
  });

  async function iqOpen(id) {
    const res = await fetch(DETAIL + '/' + id + '/detail', { headers: { Accept: 'application/json' } });
    if (!res.ok) { showToast('문의를 불러오지 못했습니다.', 'error'); return; }
    const d = await res.json();
    openId = id;

    const put = (el, v) => { document.getElementById(el).textContent = v || '—'; };
    put('iqDate', d.date);
    put('iqAsker', d.asker + (d.asker_id ? ' (' + d.asker_id + ')' : ''));
    put('iqChannel', d.channel);
    put('iqContact', d.contact);
    put('iqCategory', d.category);
    put('iqTitle', d.title);
    put('iqBody', d.body);
    put('iqHandler', d.handler);
    put('iqHandledAt', d.handled_at);

    const files = document.getElementById('iqFiles');
    files.innerHTML = '';
    if (!d.files.length) files.textContent = '—';
    d.files.forEach(f => {
      const a = document.createElement('a');
      a.href = f.url; a.target = '_blank'; a.textContent = f.name || '첨부';
      a.style.cssText = 'color:var(--primary);margin-right:8px;';
      files.appendChild(a);
    });

    document.getElementById('iqStatus').value = d.status;
    document.getElementById('iqAnswer').value = d.answer ?? '';
    document.getElementById('iqAction').value = d.action_note ?? '';
    document.getElementById('iqBack').classList.add('on');
  }

  window.iqClose = () => { document.getElementById('iqBack').classList.remove('on'); openId = null; };

  window.iqSave = async function (btn) {
    if (!openId) return;
    btn.disabled = true;
    try {
      const res = await fetch(DETAIL + '/' + openId + '/handle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: JSON.stringify({
          status:      document.getElementById('iqStatus').value,
          answer:      document.getElementById('iqAnswer').value,
          action_note: document.getElementById('iqAction').value,
        }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      showToast('저장했습니다.', 'success');
      // 목록의 그 줄만 고치는 것보다 다시 읽는 편이 낫다 — 처리자·처리일시가 서버에서 정해진다
      location.reload();
    } catch (e) {
      showToast('저장하지 못했습니다.', 'error');
      btn.disabled = false;
    }
  };

  // 바깥을 누르거나 Esc 로 닫는다 — 팝업에 갇히지 않게
  document.getElementById('iqBack').addEventListener('click', (e) => {
    if (e.target.id === 'iqBack') iqClose();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') iqClose(); });
})();
</script>
@endpush
