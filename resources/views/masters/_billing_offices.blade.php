{{-- 청구처 — 마스터 관리의 한 탭.

     공단 지사ㆍ지자체 부서와 담당자, 그리고 관할 읍ㆍ면ㆍ동을 쌓아 둔다.
     화면을 따로 두었던 것을 마스터 관리로 들였다 — 병원ㆍ기관과 마찬가지로 「어디에
     연락하는가」를 적어 두는 자리라, 찾으러 갈 곳이 둘일 까닭이 없다.

     담는 것이 병원ㆍ기관과 다르다(구분ㆍ부서ㆍ담당업무, 그리고 관할 읍ㆍ면ㆍ동 여러 줄).
     그래서 config/masters.php 의 틀을 쓰지 않고 이 조각이 스스로 그린다.

     미리 다 채우는 자리가 아니다. 건을 처리하며 한 번 찾은 것을 쌓아 두고, 여기서는
     그 쌓인 것을 보고 고친다. 그래서 「공단 지사찾기」를 위에 둔다 — 찾는 일은
     여전히 공단 사이트에서 하고, 옮겨 적는 일만 여기서 한다. --}}

{{-- 모양은 그 자리에 그대로 둔다 — 이 조각은 본문 안에서 그려지므로, 머리의 styles
     자리에 밀어 넣지 않는다(다른 떠 있는 조각들도 같은 방식이다). --}}
<style>
  .bo-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap;
             padding:12px 16px; border-radius:12px; background:var(--gray-0); }
  .bo-chip { height:31px; border-radius:999px; padding:6px 10px; border:none; cursor:pointer;
             font-size:12px; font-weight:700; background:var(--gray-100); color:var(--gray-700); }
  .bo-chip.on { background:var(--primary); color:#fff; }
  .bo-grow { flex:1; }
  .bo-card { background:var(--gray-0); border-radius:12px; padding:12px 16px; }
  .bo-field { display:flex; flex-direction:column; gap:4px; }
  .bo-grid  { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
  .bo-grid .full { grid-column:1 / -1; }
  .bo-hint { font-size:11px; color:var(--text-muted); line-height:1.6; }
  #boModal { display:none; position:fixed; z-index:1002; top:50%; left:50%; transform:translate(-50%,-50%);
             width:min(760px, 94vw); max-height:90vh; overflow:auto; background:var(--bg-card);
             border-radius:var(--radius-lg); box-shadow:0 12px 48px rgba(0,0,0,.24); }
  #boBackdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1001; }
  .bo-modal-head { background:var(--primary); color:#fff; padding:10px 14px; display:flex; align-items:center; gap:8px;
                   border-radius:var(--radius-lg) var(--radius-lg) 0 0; font-size:13px; font-weight:700; }
</style>

<div class="bo-head">
  <button type="button" class="bo-chip on" data-kind="" onclick="boKind(this,'')">전체</button>
  <button type="button" class="bo-chip" data-kind="nhis"  onclick="boKind(this,'nhis')">건강보험공단 {{ $boCounts['nhis'] ?: '' }}</button>
  <button type="button" class="bo-chip" data-kind="local" onclick="boKind(this,'local')">지자체 {{ $boCounts['local'] ?: '' }}</button>

  <input type="text" id="boQ" class="form-control" style="height:32px;width:260px;"
         placeholder="기관ㆍ부서ㆍ담당업무ㆍ읍면동" onkeydown="if(event.key==='Enter')boLoad()">
  <button type="button" class="ds-btn" onclick="boLoad()">검색</button>

  <span class="bo-grow"></span>

  {{-- 찾는 일은 공단 사이트에서 한다. 여기서는 옮겨 적기만 한다. --}}
  <button type="button" class="ds-btn" onclick="boOpenNhis()" title="공단 지사찾기 사이트를 새 창으로 엽니다">
    <i class="fa-solid fa-arrow-up-right-from-square"></i> 공단 지사찾기
  </button>
  <button type="button" class="ds-btn ds-btn-primary" onclick="boNew()">+ 청구처 추가</button>
</div>

<div class="bo-card" style="margin-top:12px;">
  <div id="boGrid"></div>
</div>

{{-- 등록ㆍ수정 --}}
<div id="boBackdrop" onclick="boClose()"></div>
<div id="boModal">
  <div class="bo-modal-head">
    <i class="fa-solid fa-building-columns"></i>
    <span id="boTitle" style="flex:1;">청구처 추가</span>
    <button onclick="boClose()" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer;">&times;</button>
  </div>
  <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
    <div class="bo-grid">
      <div class="bo-field">
        <label class="ds-field-label">구분</label>
        <select id="boKindSel" class="form-control">
          @foreach($kinds as $v => $label)
            <option value="{{ $v }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="bo-field">
        <label class="ds-field-label">지역본부 · 시도</label>
        <input type="text" id="boRegion" class="form-control" maxlength="40" placeholder="서울강원지역본부 / 서울특별시">
      </div>
      <div class="bo-field">
        <label class="ds-field-label">기관명 *</label>
        <input type="text" id="boOffice" class="form-control" maxlength="100" placeholder="마포지사 / 마포구청">
      </div>

      <div class="bo-field">
        <label class="ds-field-label">부서</label>
        <input type="text" id="boDept" class="form-control" maxlength="100" placeholder="보험급여부">
      </div>
      <div class="bo-field">
        <label class="ds-field-label">담당자</label>
        <input type="text" id="boManager" class="form-control" maxlength="40" placeholder="통화하며 알게 된 이름">
      </div>
      <div class="bo-field">
        <label class="ds-field-label">직책</label>
        <input type="text" id="boTitleF" class="form-control" maxlength="40" placeholder="주임 / 팀장">
      </div>
      <div class="bo-field">
        <label class="ds-field-label">담당업무</label>
        <input type="text" id="boDuty" class="form-control" maxlength="200" placeholder="본인부담금환급금, 현금급여비">
      </div>

      <div class="bo-field">
        <label class="ds-field-label">전화번호</label>
        <input type="text" id="boTel" class="form-control" maxlength="40" placeholder="02-3140-0189" data-phone>
      </div>
      <div class="bo-field">
        <label class="ds-field-label">팩스번호</label>
        <input type="text" id="boFax" class="form-control" maxlength="40" placeholder="02-3275-8268" data-phone>
      </div>
      <div class="bo-field">
        <label class="ds-field-label">사용</label>
        <select id="boActive" class="form-control">
          <option value="1">사용</option>
          <option value="0">사용 안 함</option>
        </select>
      </div>

      <div class="bo-field full">
        <label class="ds-field-label">주소</label>
        <input type="text" id="boAddr" class="form-control" maxlength="200" placeholder="[우.03929] 서울특별시 마포구 성암로 179, 6층">
      </div>
    </div>

    <div style="border-top:1px dashed var(--border);padding-top:12px;">
      <div class="bo-grid">
        <div class="bo-field">
          <label class="ds-field-label">관할 시도</label>
          <input type="text" id="boAreaSido" class="form-control" maxlength="30" placeholder="서울특별시">
        </div>
        <div class="bo-field">
          <label class="ds-field-label">관할 시군구</label>
          <input type="text" id="boAreaSigungu" class="form-control" maxlength="40" placeholder="마포구">
        </div>
        <div class="bo-field">
          <label class="ds-field-label">&nbsp;</label>
          <div class="bo-hint">읍ㆍ면ㆍ동 이름은 시군구가 달라도 겹칩니다(중동ㆍ신흥동…).
            시군구를 적어 두면 그것으로 먼저 가립니다.</div>
        </div>
        <div class="bo-field full">
          <label class="ds-field-label">관할 읍ㆍ면ㆍ동</label>
          <textarea id="boAreas" class="form-control" rows="2"
                    placeholder="용강동, 신수동, 대흥동 — 쉼표나 줄바꿈으로 나눠 적습니다"></textarea>
          <div class="bo-hint">주문 화면이 환자 주소에서 읍ㆍ면ㆍ동만 뽑아 여기서 찾습니다.
            한 번에 다 적을 필요는 없습니다 — 건을 처리하며 하나씩 늘려 가면 됩니다.</div>
        </div>
        <div class="bo-field full">
          <label class="ds-field-label">비고</label>
          <input type="text" id="boNote" class="form-control" maxlength="200">
        </div>
      </div>
    </div>

    <div id="boMsg" style="display:none;font-size:12px;padding:8px 10px;border-radius:var(--radius);"></div>

    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="ds-btn" id="boDelBtn" style="display:none;color:var(--danger);" onclick="boDelete()">삭제</button>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn" onclick="boClose()">닫기</button>
      <button type="button" class="ds-btn ds-btn-primary" onclick="boSave()">저장</button>
    </div>
  </div>
</div>

@push('scripts')
<script>
const BO_LIST   = @json(route('billing-offices.list'));
const BO_STORE  = @json(route('billing-offices.store'));
const BO_BASE   = @json(url('/billing-offices'));
/* 공단 지사찾기 — 검색은 그 사이트가 자바스크립트로 한다. 주소로 미리 채워 줄 수
   없어 창만 열어 준다(찾는 자리는 그대로, 옮겨 적는 자리만 우리 것이다). */
const BO_NHIS_URL = 'https://www.nhis.or.kr/nhis/about/retrieveBranchList.do';

let boGrid = null, boKindFilter = '', boEditId = null;

function boKind(btn, kind) {
  document.querySelectorAll('.bo-chip').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  boKindFilter = kind;
  boLoad();
}

function boOpenNhis() {
  window.open(BO_NHIS_URL, 'nhis_branch');
}

async function boLoad() {
  const q  = document.getElementById('boQ').value.trim();
  const qs = new URLSearchParams();
  if (boKindFilter) qs.set('kind', boKindFilter);
  if (q) qs.set('q', q);

  const res  = await fetch(BO_LIST + (qs.toString() ? '?' + qs : ''), { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const rows = (data.rows ?? []).map(r => ({ ...r, 사용: r.is_active ? '사용' : '중지' }));

  const cols = [
    { header: '구분',     name: 'kind_label',  width: 100, align: 'center' },
    { header: '지역본부', name: 'region',      width: 130 },
    { header: '기관명',   name: 'office_name', width: 140 },
    { header: '부서',     name: 'dept',        width: 130 },
    { header: '담당자',   name: 'manager_name', width: 90,  align: 'center' },
    { header: '직책',     name: 'title',       width: 70,  align: 'center' },
    { header: '담당업무', name: 'duty',        width: 220 },
    { header: '전화번호', name: 'tel',         width: 130, align: 'center' },
    { header: '팩스번호', name: 'fax',         width: 130, align: 'center' },
    { header: '관할 읍면동', name: 'areas_text', width: 220 },
    { header: '사용',     name: '사용',        width: 70,  align: 'center' },
  ];

  if (!boGrid) {
    boGrid = new wwGrid({
      el: document.getElementById('boGrid'),
      columns: cols, data: rows,
      height: 'auto', editable: false, rowNumber: true, rowCheckbox: false,
      toolbar: false, footer: { total: true, selected: false, modified: false },
    });
    document.getElementById('boGrid').addEventListener('dblclick', ev => {
      const cell = ev.target.closest('[data-row-index]');
      if (!cell) return;
      const row = boGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row) boEdit(row);
    });
  } else {
    boGrid.setData(rows);
  }
}

function boNew() {
  boEditId = null;
  document.getElementById('boTitle').textContent = '청구처 추가';
  document.getElementById('boDelBtn').style.display = 'none';
  ['boRegion','boOffice','boDept','boManager','boTitleF','boDuty','boTel','boFax','boAddr','boNote','boAreaSido','boAreaSigungu','boAreas']
    .forEach(id => document.getElementById(id).value = '');
  document.getElementById('boKindSel').value = boKindFilter || 'nhis';
  document.getElementById('boActive').value = '1';
  boOpen();
}

function boEdit(r) {
  boEditId = r.id;
  document.getElementById('boTitle').textContent = '청구처 수정';
  document.getElementById('boDelBtn').style.display = '';
  document.getElementById('boKindSel').value    = r.kind;
  document.getElementById('boRegion').value     = r.region ?? '';
  document.getElementById('boOffice').value     = r.office_name ?? '';
  document.getElementById('boDept').value       = r.dept ?? '';
  document.getElementById('boManager').value    = r.manager_name ?? '';
  document.getElementById('boTitleF').value     = r.title ?? '';
  document.getElementById('boDuty').value       = r.duty ?? '';
  document.getElementById('boTel').value        = r.tel ?? '';
  document.getElementById('boFax').value        = r.fax ?? '';
  document.getElementById('boAddr').value       = r.address ?? '';
  document.getElementById('boNote').value       = r.note ?? '';
  document.getElementById('boActive').value     = r.is_active ? '1' : '0';
  document.getElementById('boAreaSido').value   = r.area_sido ?? '';
  document.getElementById('boAreaSigungu').value= r.area_sigungu ?? '';
  document.getElementById('boAreas').value      = r.areas_text ?? '';
  boOpen();
}

function boOpen() {
  document.getElementById('boMsg').style.display = 'none';
  document.getElementById('boBackdrop').style.display = 'block';
  document.getElementById('boModal').style.display    = 'block';
  document.getElementById('boOffice').focus();
}

function boClose() {
  document.getElementById('boBackdrop').style.display = 'none';
  document.getElementById('boModal').style.display    = 'none';
}

function boSay(msg, ok) {
  const el = document.getElementById('boMsg');
  el.style.display  = 'block';
  el.style.background = ok ? 'var(--primary-50)' : 'var(--danger-light)';
  el.style.color      = ok ? 'var(--primary)'    : 'var(--danger)';
  el.textContent = msg;
}

async function boSave() {
  const body = {
    kind:        document.getElementById('boKindSel').value,
    region:      document.getElementById('boRegion').value.trim(),
    office_name: document.getElementById('boOffice').value.trim(),
    dept:        document.getElementById('boDept').value.trim(),
    manager_name:document.getElementById('boManager').value.trim(),
    title:       document.getElementById('boTitleF').value.trim(),
    duty:        document.getElementById('boDuty').value.trim(),
    tel:         document.getElementById('boTel').value.trim(),
    fax:         document.getElementById('boFax').value.trim(),
    address:     document.getElementById('boAddr').value.trim(),
    note:        document.getElementById('boNote').value.trim(),
    is_active:   document.getElementById('boActive').value === '1',
    area_sido:   document.getElementById('boAreaSido').value.trim(),
    area_sigungu:document.getElementById('boAreaSigungu').value.trim(),
    areas:       document.getElementById('boAreas').value,
  };
  if (!body.office_name) { boSay('기관명은 반드시 적어야 합니다.', false); return; }

  const url    = boEditId ? `${BO_BASE}/${boEditId}` : BO_STORE;
  const method = boEditId ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify(body),
    });
    const d = await res.json();
    if (d.success) { boClose(); boLoad(); showToast('저장했습니다.', 'success'); }
    else boSay(d.message ?? (Object.values(d.errors ?? {})[0]?.[0]) ?? '저장 실패', false);
  } catch (e) { boSay('네트워크 오류가 발생했습니다.', false); }
}

async function boDelete() {
  if (!boEditId) return;
  const ok = await ceConfirm('이 청구처를 지웁니다. 관할로 적어 둔 읍ㆍ면ㆍ동도 함께 사라집니다.',
    { title: '청구처 삭제', confirmText: '삭제', tone: 'danger' });
  if (!ok) return;

  const res = await fetch(`${BO_BASE}/${boEditId}`, {
    method: 'DELETE',
    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
  });
  const d = await res.json();
  if (d.success) { boClose(); boLoad(); showToast('삭제했습니다.', 'success'); }
  else boSay(d.message ?? '삭제 실패', false);
}

document.addEventListener('DOMContentLoaded', boLoad);
</script>
@endpush
