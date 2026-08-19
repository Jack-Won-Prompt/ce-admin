@extends('layouts.app')

@section('title', $patient->name . ' — 환자 상세')
@section('page-title', '환자 상세')
@section('breadcrumb', '홈 / 환자 관리 / ' . $patient->name)

@push('styles')
<style>
  /* 고객 정보가 위, 주문 이력이 아래다. 좌우로 두었더니 왼쪽 칸이 340px 로 눌려
     이름과 단추가 접히고, 오른쪽은 반이 비었다. */
  .detail-layout { display:flex; flex-direction:column; gap:14px; }

  /* 개인정보는 세로로 길게 쌓지 않고 한 줄에 여럿 눕힌다 — 여섯 항목이 한두 줄에 들어온다.
     주소는 길어 두 칸을 쓴다. */
  .view-panel { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:2px 20px; }
  .info-row { display:flex; align-items:baseline; gap:8px; padding:9px 0;
              border-bottom:1px solid var(--border-light, var(--border)); min-width:0; }
  .info-row.wide { grid-column:span 2; }
  .info-label { font-size:11px; font-weight:700; color:var(--text-muted); flex-shrink:0;
                white-space:nowrap; }
  .info-value { font-size:13px; color:var(--text-primary); flex:1; min-width:0;
                overflow-wrap:anywhere; }
  @media(max-width:700px) { .info-row.wide { grid-column:auto; } }

  /* 고칠 때도 보던 틀 그대로다. 예전에는 조회 패널을 감추고 두 칸짜리 입력 폼을 대신
     띄워, 수정을 누르는 순간 칸이 뒤섞이고 어디를 고치는지 다시 찾아야 했다.
     이제는 같은 grid 를 그대로 두고 값 자리만 입력칸으로 바꾼다. */
  .edit-only { display:none; }
  .is-editing .view-only { display:none; }
  .is-editing .edit-only { display:block; }
  .is-editing .edit-only.inline { display:flex; gap:6px; align-items:center; }
  .is-editing .edit-only.addr-box { display:flex; }
  /* 상세 주소도 같은 줄에 둔다 — 줄을 나누면 그만큼 아래 칸이 밀린다 */
  .addr-box > .form-control { flex:1 1 150px; min-width:0; }
  /* 글줄 안에서 바꿔 끼우는 칸 — 줄을 새로 만들지 않아야 아래가 밀리지 않는다 */
  .is-editing .edit-only.inline-mini { display:inline-flex; gap:6px; align-items:center; vertical-align:middle; }

  .info-value .form-control { width:100%; height:30px; padding:2px 8px; font-size:13px; }
  .info-value textarea.form-control { height:auto; }
  /* 나란히 놓는 칸은 100% 를 물려받으면 서로 밀어낸다 — 제 글자만큼만 잡는다 */
  .info-value .inline .form-control { width:auto; }
  .info-value select.form-control { padding-right:22px; }
  /* 주소는 주문 등록과 같은 칸으로 나눈다 — 우편번호 · 도로명 · 상세.
     한 칸에 몰아 적어 두면 주문을 낼 때 사람이 다시 갈라 옮겨 적어야 했다. */
  .addr-box { display:flex; gap:6px; align-items:center; width:100%; }
  .addr-line { display:flex; gap:6px; align-items:center; flex:1 1 auto; min-width:0; }
  .addr-line .form-control { flex:1; min-width:0; }
  .addr-line .form-control.zip { flex:0 0 92px; width:92px; }
  /* 찾아서 채우는 칸이라 손으로 고치지 않는다 — 시안대로 바탕을 눕힌다 */
  .addr-line .form-control[readonly] { background:var(--gray-50); cursor:default; }
  .addr-line .btn { flex:0 0 auto; white-space:nowrap; }
  .info-hint { display:block; font-size:11px; color:var(--text-muted); margin-top:2px; }
  /* 이름은 그 자리에서 고친다 — 글자 크기까지 같게 두어야 자리가 흔들리지 않는다 */
  #e-name { font-size:17px; font-weight:700; height:27px; padding:1px 8px; }

  .rx-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); cursor:pointer; }
  .rx-row:last-child { border-bottom:none; }
  .rx-row:hover { background:var(--bg); border-radius:var(--radius); padding-left:8px; }
  /* 표 안의 처방번호 — 누를 수 있는 것처럼 보여야 한다 */
  .rx-link { border:none; background:none; padding:0; font:inherit; color:var(--primary);
             font-weight:600; cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
  .rx-link:hover { color:var(--primary-dark, var(--primary)); }

  .rx-status { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }

  /* Vuexy underline tabs */
  .tab-bar {
    display:flex; border-bottom:2px solid var(--border); margin-bottom:18px;
  }
  .tab-btn {
    padding:10px 20px; font-size:13px; font-weight:600;
    color:var(--text-muted); border:none; background:transparent;
    border-bottom:1px solid transparent; margin-bottom:-1px;
    cursor:pointer; transition:var(--transition);
  }
  .tab-btn:hover { color:var(--primary); }
  .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
  .tab-pane { display:none; }
  .tab-pane.active { display:block; }


</style>
@endpush

@section('content')

{{-- 위쪽 이름 띠는 두지 않는다. 바로 아래 카드가 같은 이름을 다시 적고 있어 화면을
     열면 이름이 두 번 보였다. 손댈 단추(수정·상담내역)는 그 이름 옆으로 옮겼다. --}}

<div class="detail-layout">

  {{-- 위: 고객 정보 --}}
  <div>
    <div class="card">
      <div class="card-body">

        {{-- 아이콘 + 이름 --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;">
            <i class="fa-solid fa-user"></i>
          </div>
          {{-- 이름 칸이 줄어들다 못해 0 이 되면 한 글자씩 접힌다. 줄어드는 대신 단추를
               아랫줄로 내린다 — 이름은 끊기더라도 한 줄로 읽혀야 한다. --}}
          <div style="flex:1 1 220px;min-width:160px;">
            <div class="view-only" style="font-size:18px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $patient->name }}</div>
            <input type="text" class="form-control edit-only" id="e-name" value="{{ $patient->name }}"
                   data-orig="{{ $patient->name }}" placeholder="환자명" />
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
              환자 #{{ $patient->id }} · 등록 {{ $patient->created_at->format('Y-m-d') }}
              <span class="view-only" style="display:inline;">
                @if($patient->birth_date) · {{ $patient->birth_date->format('Y-m-d') }} 만 {{ $patient->age }}세 @endif
                @if($patient->gender) · {{ $patient->gender === 'male' ? '남' : '여' }} @endif
              </span>
              {{-- 생일·성별은 적혀 있던 그 자리에서 고친다. 줄을 하나 더 만들면
                   그만큼 아래 칸이 통째로 밀려, 고치기 전과 다른 화면이 된다. --}}
              <span class="edit-only inline-mini">
                <input type="date" class="form-control" id="e-birth" style="width:132px;height:26px;font-size:12px;padding:1px 6px;"
                       value="{{ $patient->birth_date?->format('Y-m-d') }}" data-orig="{{ $patient->birth_date?->format('Y-m-d') }}" />
                <select class="form-control" id="e-gender" style="width:74px;height:26px;font-size:12px;padding:1px 6px;"
                        data-orig="{{ $patient->gender }}">
                  <option value="">성별</option>
                  <option value="male"   @selected($patient->gender==='male')>남</option>
                  <option value="female" @selected($patient->gender==='female')>여</option>
                </select>
              </span>
            </div>
          </div>

          {{-- 이 사람에게 할 일은 이름 옆에 둔다. 고치는 동안에는 수정 단추 오른쪽에
               저장·취소가 나란히 붙는다 — 누른 자리에서 끝맺을 수 있어야 한다. --}}
          <div style="margin-left:auto;display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;align-items:center;">
            <button class="btn btn-outline btn-sm" id="btn-edit" onclick="toggleEdit(true)">
              <i class="fa-solid fa-pen"></i> 수정
            </button>
            <button class="btn btn-warning btn-sm edit-only" id="btn-save" onclick="savePatient()">
              <i class="fa-solid fa-floppy-disk"></i> 저장
            </button>
            <button class="btn btn-outline btn-sm edit-only" id="btn-cancel" onclick="toggleEdit(false)">취소</button>
            <button class="btn btn-outline btn-sm" onclick="openCounselTab()">
              <i class="bx bx-conversation"></i> 상담내역
            </button>
          </div>
        </div>

        {{-- 개인정보 — 보는 것과 고치는 것이 한 자리다 --}}
        <div class="view-panel" id="view-panel">
          <div class="info-row">
            <span class="info-label">주민번호</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->masked_resident_no ?? '-' }}</span>
              {{-- 마스킹만 보여준다. 그대로 두면 기존 값이 유지되고, 바꿀 때만 전체를 새로 입력한다 --}}
              <span class="edit-only">
                <input type="text" class="form-control" id="e-resident"
                       value="{{ $patient->masked_resident_no }}"
                       data-masked="{{ $patient->masked_resident_no }}"
                       data-orig="{{ $patient->masked_resident_no }}"
                       placeholder="XXXXXX-XXXXXXX" />
              </span>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">휴대폰</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->mobile ?? '-' }}</span>
              <input type="text" class="form-control edit-only" id="e-mobile" data-phone
                     value="{{ $patient->mobile }}" data-orig="{{ $patient->mobile }}" placeholder="010-XXXX-XXXX" />
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">일반 전화</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->phone ?? '-' }}</span>
              <input type="text" class="form-control edit-only" id="e-phone" data-phone
                     value="{{ $patient->phone }}" data-orig="{{ $patient->phone }}" placeholder="02-XXXX-XXXX" />
            </span>
          </div>
          <div class="info-row wide">
            <span class="info-label">주소</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->full_address ?: '-' }}</span>
              {{-- 주문 등록과 같은 구성이다 — 우편번호·도로명은 찾아서 채우고(손으로 고치지
                   않는다), 상세 주소만 사람이 적는다. --}}
              <span class="edit-only addr-box">
                <span class="addr-line">
                  <input type="text" class="form-control zip" id="e-postcode" readonly
                         value="{{ $patient->postcode }}" data-orig="{{ $patient->postcode }}"
                         placeholder="우편번호" />
                  <input type="text" class="form-control" id="e-address" readonly
                         value="{{ $patient->address }}" data-orig="{{ $patient->address }}"
                         placeholder="도로명 주소" />
                  <button type="button" class="btn btn-outline btn-sm" onclick="findAddress()">
                    <i class="fa-solid fa-magnifying-glass"></i> 주소 검색
                  </button>
                </span>
                <input type="text" class="form-control" id="e-address-detail"
                       value="{{ $patient->address_detail }}" data-orig="{{ $patient->address_detail }}"
                       placeholder="상세 주소" />
              </span>
            </span>
          </div>
          {{-- 건강보험번호·급여 적용은 이 화면에서 보지 않는다. 급여 여부는 주문
               한 건마다 정해지는 것이라, 사람에 붙여 두면 실제와 어긋난다. --}}
          {{-- 메모 칸은 두지 않는다. 사람에 붙은 한 줄 메모는 어느 주문 이야기인지
               알 수 없어 적어 두어도 다음 사람이 쓰지 못했다 — 통화 기록은 상담내역에 남는다.
               이미 적혀 있는 메모는 지우지 않는다(화면에서 보내지 않을 뿐이다). --}}
        </div>

      </div>
    </div>
  </div>

  {{-- 아래: 주문 이력 --}}
  <div>
    <div class="card">
      <div class="card-body">
        <div class="tab-bar">
          <button class="tab-btn active" onclick="switchTab(this,'tab-rx')">
            <i class="fa-solid fa-file-medical"></i> 주문 이력
            <span style="background:var(--primary-light);color:var(--primary);border-radius:12px;padding:1px 7px;font-size:11px;margin-left:4px;">{{ $patient->prescriptions->count() }}</span>
          </button>
          {{-- 한 건을 열면 그 주문의 제품 줄이 이 옆 탭에 펼쳐진다. 목록에 제품명 칸을
               두었더니 여러 줄짜리 주문은 첫 줄만 보였다 — 아예 제 자리를 준다. --}}
          <button class="tab-btn" id="tab-btn-items" style="display:none;" onclick="switchTab(this,'tab-items')">
            <i class="fa-solid fa-boxes-stacked"></i> <span id="tab-items-label">주문 제품</span>
          </button>
          <span style="margin-left:auto;font-size:11.5px;color:var(--text-muted);">처방번호를 누르면 주문 등록 화면이, 행을 더블클릭하면 그 주문의 제품이 열립니다.</span>
        </div>

        {{-- 다른 목록 화면과 같은 표를 쓴다. 손으로 그린 줄은 정렬도 엑셀 저장도 없어,
             건수가 늘면 훑을 방법이 눈뿐이었다. --}}
        <div class="tab-pane active" id="tab-rx">
          @if($rxRows->isEmpty())
            <div style="text-align:center;padding:48px 20px;color:var(--text-muted);">
              <i class="fa-solid fa-file-medical" style="font-size:28px;opacity:.3;display:block;margin-bottom:10px;"></i>
              주문 이력이 없습니다.
            </div>
          @else
            <div id="rxGrid"></div>
          @endif
        </div>

        <div class="tab-pane" id="tab-items">
          <div class="ds-grid-hint" id="itemsNote" style="margin-bottom:8px;"></div>
          <div id="itemsGrid"></div>
        </div>

      </div>
    </div>
  </div>

</div>

@endsection

@push('scripts')

<script>
  /* 주문 이력 표 — 행을 더블클릭하면 그 주문의 제품을 옆 탭에서 펼친다.
     wwGrid 에는 on() 이 없어 셀에서 행 번호를 읽는다. */
  (function () {
    const el = document.getElementById('rxGrid');
    if (!el || typeof wwGrid === 'undefined') return;

    const rows = @json($rxRows);
    const grid = new wwGrid({
      el,
      height: 'auto', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
      columns: [
        { header: '주문번호', name: 'order_no',  width: 130, sortable: true },
        /* 처방번호를 누르면 그 건의 주문 등록 화면을 화면 탭으로 열어 준다 —
           보던 환자 화면은 그대로 남아 돌아올 자리가 있다. */
        { header: '처방번호', name: 'rx_number', width: 150, sortable: true,
          renderer: (v, row) => {
            if (!v) return '';
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'rx-link';
            a.textContent = v;
            a.title = '주문 등록 화면을 엽니다';
            a.addEventListener('click', (e) => {
              e.stopPropagation();
              if (row.url) ceOpenTab(row.url, '주문 - ' + (row.order_no || v), 'file-edit-02');
            });
            return a;
          } },
        { header: '병원',     name: 'hospital',  width: 160, sortable: true },
        { header: '금액',     name: 'amount',    width: 110, align: 'right', editor: 'number' },
        { header: '접수일',   name: 'date',      width: 100, align: 'center', sortable: true },
        { header: '상태',     name: 'status',    width: 90,  align: 'center', sortable: true },
      ],
      data: rows,
    });
    window.__rxGrid = grid;

    /* 제품 표는 한 번만 만들고 내용만 갈아 끼운다 — 열 때마다 새로 만들면 정렬해 둔 것이 풀린다 */
    let itemsGrid = null;

    function showItems(row) {
      const items = row.items || [];
      const label = row.order_no || row.rx_number || '주문';

      document.getElementById('tab-items-label').textContent = '주문 제품 - ' + label;
      document.getElementById('tab-btn-items').style.display = '';
      document.getElementById('itemsNote').textContent = items.length
        ? label + ' · ' + items.length + '건'
        : label + ' — 등록된 제품 줄이 없습니다.';

      const gridEl = document.getElementById('itemsGrid');
      if (!itemsGrid) {
        itemsGrid = new wwGrid({
          el: gridEl,
          height: 'auto', editable: false, rowNumber: true, toolbar: false, summary: false, footer: false,
          columns: [
            { header: '제품명',     name: 'name',       width: 300 },
            { header: '제품코드',   name: 'code',       width: 130 },
            { header: '수량',       name: 'qty',        width: 70,  align: 'right', editor: 'number' },
            { header: '단가',       name: 'unit_price', width: 110, align: 'right', editor: 'number' },
            { header: '공단부담',   name: 'nhis',       width: 110, align: 'right', editor: 'number' },
            { header: '환자부담',   name: 'copay',      width: 110, align: 'right', editor: 'number' },
            { header: '금액',       name: 'total',      width: 110, align: 'right', editor: 'number' },
          ],
          data: items,
        });
        window.__itemsGrid = itemsGrid;
      } else {
        itemsGrid.setData(items);
      }

      switchTab(document.getElementById('tab-btn-items'), 'tab-items');
    }

    el.addEventListener('dblclick', (e) => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (!row) return;
      // 상담만 적고 아직 주문이 없는 줄도 있다 — 그때는 펼칠 것이 없다
      if (!row.order_no) { showToast('아직 주문이 없는 건입니다.', 'warning'); return; }
      showItems(row);
    });
  })();
</script>

{{-- 주문 등록 화면과 같은 카카오(다음) 우편번호 서비스 --}}
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
  /* 주소를 손으로 다 적으면 오타가 난다 — 우편번호·도로명은 찾아 넣고 상세만 적는다.
     주문 등록의 openAddressSearch() 와 같은 서비스·같은 순서다. */
  function findAddress() {
    if (typeof daum === 'undefined' || !daum.Postcode) {
      showToast('주소 찾기를 불러오지 못했습니다. 직접 적어 주십시오.', 'warning');
      return;
    }
    const W = 500, H = 600;
    new daum.Postcode({
      width: W, height: H,
      oncomplete: function (data) {
        document.getElementById('e-postcode').value = data.zonecode;
        document.getElementById('e-address').value  = data.roadAddress || data.jibunAddress;
        // 찾고 나면 남은 것은 상세 주소뿐이다
        const detail = document.getElementById('e-address-detail');
        detail.value = '';
        detail.focus();
      },
    }).open({
      left: Math.floor((window.screen.width  - W) / 2),
      top:  Math.floor((window.screen.height - H) / 2),
    });
  }
</script>
<script>
  /* 상담내역은 거래처 관리 화면의 탭에서 본다. 이 화면이 그 안에 액자로 들어가 있으면
     바깥에 열라고 알리고, 혼자 열려 있으면 목록 화면으로 옮겨 그 탭을 연다. */
  function openCounselTab() {
    const msg = { source: 'ce-patient', action: 'counsel',
                  id: @json($patient->id), name: @json($patient->name) };

    if (window.parent && window.parent !== window) {
      try {
        window.parent.postMessage(msg, window.location.origin);
        return;
      } catch (e) { /* 다른 곳에서 온 창이면 아래로 간다 */ }
    }

    location.href = @json(route('patients.index')) + '?counsel=' + @json($patient->id);
  }
</script>
<script>
  function switchTab(btn, id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(id).classList.add('active');
  }

  /* 고치기로 들어가고 나오는 길. 칸을 갈아 끼우지 않고 표시만 바꾼다 —
     보던 자리가 그대로 있어야 무엇을 고치는지 눈으로 따라갈 수 있다. */
  function toggleEdit(on) {
    document.querySelector('.detail-layout').classList.toggle('is-editing', on);
    // 그만두면 손댄 것은 없던 일로 한다
    if (!on) {
      document.querySelectorAll('.detail-layout [data-orig]').forEach(el => {
        el.value = el.dataset.orig ?? '';
      });
    }
    if (on) setTimeout(() => document.getElementById('e-name')?.focus(), 30);
  }

  async function savePatient() {
    const name = document.getElementById('e-name').value.trim();
    if (!name) { showToast('환자명은 필수입니다.', 'warning'); return; }

    const btn = document.getElementById('btn-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      // 마스킹 그대로면 '변경 없음' — 보낸 값이 없으면 서버가 기존 값을 건드리지 않는다
      resident_no:         (function (el) {
                             const v = el.value.trim();
                             return (v === '' || v === el.dataset.masked) ? undefined : v;
                           })(document.getElementById('e-resident')),
      birth_date:          document.getElementById('e-birth').value               || null,
      gender:              document.getElementById('e-gender').value              || null,
      mobile:              document.getElementById('e-mobile').value.trim()       || null,
      phone:               document.getElementById('e-phone').value.trim()        || null,
      address:             document.getElementById('e-address').value.trim()      || null,
      postcode:            document.getElementById('e-postcode').value.trim()     || null,
      address_detail:      document.getElementById('e-address-detail').value.trim()|| null,
      // 건강보험번호·급여 항목은 화면에서 걷어냈다 — 보내지 않으면 저장된 값은 그대로 남는다
      _method:             'PUT',
    };

    const res = await apiRequest(`/patients/{{ $patient->id }}`, 'POST', payload);

    if (res.success) {
      BtnState.success(btn, '저장 완료');
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 700);
    } else {
      BtnState.error(btn, '저장 실패');
      showToast(res.message || '저장 실패', 'danger');
    }
  }

  /* 삭제 단추는 두지 않는다. 환자에는 처방·주문·상담·서류가 달려 있어, 지우면 그
     기록들이 어디에도 이어지지 않는 채로 남는다 — 지울 일은 관리자 손으로 따로 한다.
     서버의 삭제 경로는 그대로 있다(다른 자리에서 쓰고 있고, 길을 막을 이유는 없다). */
</script>
<script>
window.HELP_TOUR_STEPS = [
  { selector: '#view-panel', title: '환자 기본 정보', body: '환자의 이름, 연락처, 주민번호, 주소를 확인합니다.' },
  { selector: '#btn-edit', title: '정보 편집', body: '보던 그 자리에서 바로 고칩니다. 수정 오른쪽에 저장·취소가 나타납니다.' },
  { selector: '.card', title: '처방·주문 이력', body: '이 환자의 처방전 업로드 이력과 주문 내역을 확인합니다. 처방번호 클릭 시 상세 화면으로 이동합니다.' },
];
</script>
@endpush
