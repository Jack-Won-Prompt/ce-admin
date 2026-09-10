@extends('layouts.app')

@section('title', $patient->name . ' — 거래처 관리')
{{-- 시안 114:6264 · 120:485 — 상세 화면의 헤더 제목도 「거래처 관리」다.
     요청서가 환자 → 거래처로 바꾼 낱말이 목록에만 반영돼 있었다. --}}
@section('page-title', '거래처 관리')
@section('breadcrumb', '홈 - 거래처 관리 - ' . $patient->name)

@push('styles')
<style>
  /* 구입 확인서 — 세 갈래(내려받기ㆍ문자ㆍ메일)를 단추 하나 아래 접어 둔다.
     이름 옆 단추 줄이 길어지면 정작 「수정」이 밀려난다. */
  .pc-menu { position: relative; }
  .pc-pop { display: none; position: absolute; right: 0; top: calc(100% + 4px); z-index: 30;
            min-width: 168px; background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 4px; }
  .pc-pop.open { display: block; }
  .pc-pop a, .pc-pop button { display: flex; align-items: center; gap: 6px; width: 100%;
            padding: 7px 10px; font-size: 12.5px; color: var(--text-primary);
            background: none; border: 0; border-radius: 7px; cursor: pointer; text-align: left; }
  .pc-pop a:hover, .pc-pop button:hover:not(:disabled) { background: var(--gray-50); }
  .pc-pop button:disabled { color: var(--text-muted); cursor: not-allowed; }
  .pc-pop form { margin: 0; }
</style>
@endpush

@push('styles')
<style>
  /* 고객 정보가 위, 주문 이력이 아래다. 좌우로 두었더니 왼쪽 칸이 340px 로 눌려
     이름과 단추가 접히고, 오른쪽은 반이 비었다. */
  /* 내용이 짧아도 바닥까지 — 마지막 카드(주문 이력)가 남는 높이를 받는다.
     이 화면은 거래처 관리의 「상세 내용」 탭 안 액자로도 그려져, 여기가 안 차면
     그 탭에 빈 흰 칸이 따로 생긴 것처럼 보인다. */
  .detail-layout { display:flex; flex-direction:column; gap:14px; flex:1 1 auto; min-height:0; }
  .detail-layout > :last-child { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; }
  /* 밑변까지 내려온 표가 카드 모서리 밖으로 새지 않게 한다 */
  .detail-layout > :last-child > .card { flex:1 1 auto; min-height:0; overflow:hidden;
                                         display:flex; flex-direction:column; }
  /* 카드 안도 세로 flex 여야 탭 판이 남는 높이를 받는다. 이것이 없으면 판은 제
     내용만큼만 서고, 넘치는 만큼은 카드의 overflow:hidden 이 잘라 낸다 —
     고치는 중에 칸이 커지면 아래쪽 줄이 통째로 사라져 보였다. */
  .detail-layout > :last-child > .card > .card-body {
    flex:1 1 auto; min-height:0; display:flex; flex-direction:column;
  }

  /* 개인정보는 세로로 길게 쌓지 않고 한 줄에 여럿 눕힌다 — 여섯 항목이 한두 줄에 들어온다.
     주소는 길어 두 칸을 쓴다. */
  .view-panel { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:2px 20px; }
  .info-row { display:flex; align-items:baseline; gap:8px; padding:9px 0;
              border-bottom:1px solid var(--border-light, var(--border)); min-width:0; }
  /* 주소는 네 칸을 쓴다 — 우편번호·도로명·찾기·상세가 한 줄에 들어가야 한다 */
  .info-row.wide { grid-column:span 4; }
  @media(max-width:1200px) { .info-row.wide { grid-column:span 2; } }
  /* 이름이 긴 칸은 두 칸을 쓴다. 라벨은 줄지 않으므로(nowrap) 길면 길수록 값이
     설 자리를 먹는다 — 「기초(의료급여) 재평가 기한」은 라벨만 139 이라 한 칸(246)에서
     값에게 99 밖에 남지 않았고, 날짜칸은 YYYY-MM-DD 와 달력 단추를 넣는 데 150 이
     필요해 적는 족족 글자가 잘렸다. 짝지어 쓰는 줄은 둘을 함께 넘겨 나란히 선다. */
  .info-row.w2 { grid-column:span 2; }
  /* 두 칸을 받았다고 칸까지 두 배로 늘이지는 않는다 — 날짜나 예/아니오를 받는
     칸이 혼자 길면 옆 줄과 밑선이 안 맞아 줄마다 길이가 다른 표처럼 보인다. */
  .info-row.w2 .info-value .form-control { max-width:240px; }
  @media(max-width:700px) { .info-row.w2 { grid-column:auto; } }
  /* 글자 값(13/500 · lh21 · gray-700)은 전역을 그대로 쓴다. 여기서는 줄에서 자리를
     잡는 것만 정한다 — 라벨은 줄지 않고 줄바꿈도 하지 않는다. */
  .info-label { flex-shrink:0; white-space:nowrap; margin-bottom:0; }
  .info-value { font-size:13px; color:var(--text-primary); flex:1; min-width:0;
                overflow-wrap:anywhere; }
  @media(max-width:700px) { .info-row.wide { grid-column:auto; } }

  /* 고칠 때도 보던 틀 그대로다. 예전에는 조회 패널을 감추고 두 칸짜리 입력 폼을 대신
     띄워, 수정을 누르는 순간 칸이 뒤섞이고 어디를 고치는지 다시 찾아야 했다.
     이제는 같은 grid 를 그대로 두고 값 자리만 입력칸으로 바꾼다. */
  .edit-only { display:none; }
  .is-editing .view-only { display:none; }
  .is-editing .edit-only { display:block; }
  .is-editing input.edit-only { display:inline-block; }
  .is-editing .edit-only.inline { display:flex; gap:6px; align-items:center; }
  .is-editing .edit-only.addr-box { display:flex; }
  /* 상세 주소도 같은 줄에 둔다 — 줄을 나누면 그만큼 아래 칸이 밀린다 */
  .addr-box > .form-control { flex:1 1 150px; min-width:0; }
  /* 글줄 안에서 바꿔 끼우는 칸 — 줄을 새로 만들지 않아야 아래가 밀리지 않는다 */
  .is-editing .edit-only.inline-mini { display:inline-flex; gap:6px; align-items:center; vertical-align:middle; }

  .info-value .form-control { width:100%; height:30px; padding:2px 8px; font-size:13px; }
  /* 적는 길이가 정해져 있는 칸은 그만큼만 잡는다 — 남는 자리는 주소가 쓴다 */
  #e-resident, #e-mobile, #e-phone { max-width:148px; }

  /* ── 변경 이력 (2026-09-08 확인요청 3ㆍ5쪽) ──
     주문 등록의 「저장 이력」과 같은 모양이다 — 두 화면이 다르게 생기면 같은 것을
     보고 있다는 것을 알아채기 어렵다. */
  .pl-tabs { display:flex; gap:4px; margin-bottom:10px; border-bottom:1px solid var(--gray-200); }
  .pl-tab  { height:30px; padding:0 14px; border:0; background:none; cursor:pointer;
             font-size:12px; font-weight:500; color:var(--text-secondary);
             border-bottom:2px solid transparent; }
  .pl-tab.active   { color:var(--primary); border-bottom-color:var(--primary); font-weight:700; }
  .pl-tab:disabled { color:var(--gray-300); cursor:default; }

  .pl-head { display:flex; align-items:center; gap:16px; flex-wrap:wrap;
             padding:10px 14px; margin-bottom:10px; font-size:12px;
             background:var(--gray-50); border-radius:8px; }
  .pl-head b { font-weight:700; }
  .pl-head-k { color:var(--text-muted); margin-right:6px; }

  .pl-diff     { border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; }
  .pl-row      { display:grid; grid-template-columns:1fr 180px 1fr; align-items:stretch;
                 border-top:1px solid var(--gray-200); }
  .pl-row:first-child { border-top:0; }
  .pl-cap      { background:var(--gray-50); font-size:11px; font-weight:700;
                 color:var(--text-secondary); text-align:center; }
  .pl-cap > div { padding:8px 12px; }
  .pl-k        { padding:10px 12px; text-align:center; font-size:12px; font-weight:600;
                 background:var(--gray-50); color:var(--gray-700);
                 border-left:1px solid var(--gray-200); border-right:1px solid var(--gray-200); }
  .pl-v        { padding:10px 14px; font-size:12px; word-break:break-all;
                 display:flex; align-items:center; }
  .pl-v-before { justify-content:flex-end; text-align:right; color:var(--text-muted);
                 text-decoration:line-through; }
  .pl-v-after  { font-weight:600; }
  .pl-v-empty  { display:inline-block; color:var(--gray-300); text-decoration:none; }
  .pl-none     { padding:24px; text-align:center; font-size:12px; color:var(--text-muted); }
  /* 주 연락처 표시 — 이름 옆에 작게 붙는다 */
  .mc-flag { display:inline-block; margin-left:6px; padding:1px 6px; border-radius:4px;
             background:var(--primary-light); color:var(--primary); font-size:10px; font-weight:700; }

  .info-value textarea.form-control { height:auto; }
  /* 나란히 놓는 칸은 100% 를 물려받으면 서로 밀어낸다 — 제 글자만큼만 잡는다 */
  .info-value .inline .form-control { width:auto; }
  .info-value select.form-control { padding-right:22px; }
  /* 주소는 주문 등록과 같은 칸으로 나눈다 — 우편번호 · 도로명 · 상세.
     한 칸에 몰아 적어 두면 주문을 낼 때 사람이 다시 갈라 옮겨 적어야 했다. */
  /* display 는 여기서 정하지 않는다 — 뒤에 오는 이 규칙이 .edit-only{display:none} 을
     덮어, 보기 모드에서도 주소 입력칸이 그대로 서 있었다. 펴는 것은 .is-editing 이 한다. */
  .addr-box { gap:6px; align-items:center; width:100%; }
  .addr-line { display:flex; gap:6px; align-items:center; flex:1 1 auto; min-width:0; }
  .addr-line .form-control { flex:1; min-width:0; }
  .addr-line .form-control.zip { flex:0 0 92px; width:92px; }
  /* 찾아서 채우는 칸이라 손으로 고치지 않는다 — 시안대로 바탕을 눕힌다 */
  .addr-line .form-control[readonly] { background:var(--gray-50); cursor:default; }
  .addr-line .btn { flex:0 0 auto; white-space:nowrap; }
  .info-hint { display:block; font-size:11px; color:var(--text-muted); margin-top:2px; }
  /* 이름은 그 자리에서 고친다 — 글자 크기까지 같게 두어야 자리가 흔들리지 않는다 */
  /* 이름은 그 자리에서 고친다 — 시안(120:680) 16/700 lh26 과 같게 둔다 */
  #e-name { font-size:16px; font-weight:700; height:26px; padding:0 8px; }

  .rx-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); cursor:pointer; }
  .rx-row:last-child { border-bottom:none; }
  .rx-row:hover { background:var(--bg); border-radius:var(--radius); padding-left:8px; }
  /* 표 안의 처방번호 — 누를 수 있는 것처럼 보여야 한다 */
  .rx-link { border:none; background:none; padding:0; font:inherit; color:var(--primary);
             font-weight:500; cursor:pointer; text-decoration:underline; text-underline-offset:2px; }
  .rx-link:hover { color:var(--primary-dark, var(--primary)); }

  .rx-status { display:inline-flex; align-items:center; padding:2px 6px; border-radius:6px; font-size:11px; font-weight:500; line-height:18px; }

  /* Vuexy underline tabs */
  .tab-bar {
    display:flex; border-bottom:2px solid var(--border); margin-bottom:18px;
  }
  .tab-btn {
    padding:10px 20px; font-size:13px; font-weight:500;
    color:var(--text-muted); border:none; background:transparent;
    border-bottom:1px solid transparent; margin-bottom:-1px;
    cursor:pointer; transition:var(--transition);
  }
  .tab-btn:hover { color:var(--primary); }
  .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
  /* 탭 판은 받은 높이를 표에 그대로 넘긴다 — 표가 카드 밑변까지 서고 「전체 N건」
     띠가 그 바닥에 붙는다. display:block 이던 시절에는 표가 제 내용만큼만 서서
     띠가 표 밑에 떠 있고, 그 아래로 흰 바닥이 한 참 남았다. */
  .tab-bar { flex:0 0 auto; }
  /* 접힌 판은 `:not(.active)` 로 접는다. 그냥 `.tab-pane` 이면 레이아웃의
     `.card *:has(.cg-root)`(표를 품은 껍데기를 세로 flex 로 만드는 규칙)보다 약해
     져, 표가 한 번 만들어진 판은 접어도 다시 펴졌다 — 주문 제품을 한 번 연 뒤
     주문 이력으로 돌아가면 두 표가 함께 보였다. */
  .tab-pane:not(.active) { display:none; }
  .tab-pane.active { display:flex; flex-direction:column; min-height:0; flex:1 1 auto; }
  /* 표가 든 판은 줄 수만큼만 차지한다. 판이 남는 높이를 다 받으면 표 안이 비고
     합계줄이 카드 밖으로 밀려난다 — 한 줄짜리 주문에서 그 빈칸이 화면 절반이었다. */
  #tab-rx.tab-pane.active, #tab-items.tab-pane.active { flex:0 0 auto; }
  /* 개인정보는 고칠 때 칸이 커져 판을 넘긴다. 카드가 잘라 내게 두지 않고
     판 안에서 굴린다 — 잘리면 아래쪽 줄이 있는 줄도 모른다. */
  #tab-info.tab-pane.active { overflow-y:auto; }

  /* 표는 카드 안쪽 여백(12/16)을 넘어 양옆·밑변까지 간다 — 띠가 카드 폭을 다 쓴다.
     조회 결과 목록의 아래끝이 그렇게 생겼고, 같은 자리에서 같게 읽혀야 한다.
     카드가 이미 흰 판이니 표는 제 테두리·모서리를 또 두르지 않는다. */
  #tab-rx, #tab-items { margin:0 -16px -12px; }
  #itemsNote { padding:0 16px; }
  #tab-rx .cg-wrap, #tab-items .cg-wrap { border:0; border-radius:0; }


</style>
@endpush

@section('content')

{{-- 위쪽 이름 띠는 두지 않는다. 바로 아래 카드가 같은 이름을 다시 적고 있어 화면을
     열면 이름이 두 번 보였다. 손댈 단추(수정·상담내역)는 그 이름 옆으로 옮겼다. --}}

<div class="detail-layout fill-rest">

  {{-- 위: 고객 정보 --}}
  <div>
    <div class="card">
      <div class="card-body">

        {{-- 아이콘 + 이름 --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;">
            <i class="fa-solid fa-user"></i>
          </div>
          {{-- 이름과 딸린 것들은 한 줄로 읽는다 — 두 줄로 쌓아 두니 아이콘 옆이
               위아래로 벌어져, 정작 짧은 두 마디가 자리를 두 배로 썼다.
               이름은 끊기더라도(ellipsis) 줄을 바꾸지 않는다. --}}
          <div style="flex:1 1 260px;min-width:180px;display:flex;align-items:baseline;gap:8px;min-height:32px;">
            <span class="view-only" style="font-size:16px;font-weight:700;line-height:26px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">{{ $patient->name }}</span>
            <input type="text" class="form-control edit-only" id="e-name" value="{{ $patient->name }}"
                   data-orig="{{ $patient->name }}" placeholder="이름" style="flex:0 1 200px;" />
            <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
              환자 #{{ $patient->id }} · 등록 {{ $patient->created_at->format('Y-m-d') }}
              <span class="view-only" style="display:inline;">
                @if($patient->birth_date) · {{ $patient->birth_date->format('Y-m-d') }} 만 {{ $patient->age }}세 @endif
              </span>
            </span>
            {{-- 생일은 적혀 있던 그 자리에서 고친다. 성별은 두지 않는다 —
                 주민번호에 이미 들어 있고, 쓰는 곳도 없었다. --}}
            <input type="date" class="form-control edit-only" id="e-birth"
                   style="width:132px;height:26px;font-size:12px;padding:1px 6px;flex:0 0 132px;"
                   value="{{ $patient->birth_date?->format('Y-m-d') }}" data-orig="{{ $patient->birth_date?->format('Y-m-d') }}" />
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

            {{-- 의료용품 구입 확인서 — 거래처 본인이 달라고 할 때 내준다(요청서 회신).
                 내려받아 건네거나, 문자ㆍ메일로 링크를 보낸다. 링크는 이레만 산다. --}}
            <div class="pc-menu">
              <button type="button" class="btn btn-outline btn-sm" onclick="pcToggle(event)">
                <i class="bx bx-file"></i> 구입 확인서
              </button>
              <div class="pc-pop" id="pcPop">
                <a href="{{ route('documents.purchaseConfirm', $patient) }}">
                  <i class="bx bx-download"></i> 내려받기
                </a>
                <form method="POST" action="{{ route('documents.purchaseConfirm.send', $patient) }}">
                  @csrf
                  <input type="hidden" name="channel" value="sms">
                  <button type="submit" @disabled(blank($patient->mobile))
                          title="{{ blank($patient->mobile) ? '연락처가 없습니다' : $patient->mobile }}">
                    <i class="bx bx-message-detail"></i> 문자로 보내기
                  </button>
                </form>
                {{-- 메일은 주소가 있을 때만 고를 수 있다(요청서 회신 — 「이메일 존재하면」) --}}
                <form method="POST" action="{{ route('documents.purchaseConfirm.send', $patient) }}">
                  @csrf
                  <input type="hidden" name="channel" value="email">
                  <button type="submit" @disabled(blank($patient->email))
                          title="{{ blank($patient->email) ? '이메일이 없습니다' : $patient->email }}">
                    <i class="bx bx-envelope"></i> 메일로 보내기
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

        {{-- 가로 탭 — 개인정보ㆍ주문 이력ㆍ주문 제품이 한 카드 안에서 갈린다.
             예전에는 개인정보가 일곱 줄을 먹고 그 아래에 주문 이력 카드가 따로 서서,
             개인정보를 다 읽고 나서야 주문이 보였다. 이 화면을 여는 까닭은 대개
             「이 사람이 무엇을 언제 샀나」인데 그것이 늘 화면 밖에 있었다.
             이름 줄은 탭 밖이다 — 어느 탭에 있든 누구를 보고 있는지는 보여야 한다. --}}
        <div class="tab-bar">
          <button class="tab-btn active" id="tab-btn-info" onclick="switchTab(this,'tab-info')">
            <i class="fa-solid fa-id-card"></i> 개인정보
          </button>
          <button class="tab-btn" id="tab-btn-rx" onclick="switchTab(this,'tab-rx')">
            <i class="fa-solid fa-file-medical"></i> 주문 이력
            <span style="background:var(--primary-light);color:var(--primary);border-radius:12px;padding:1px 7px;font-size:11px;margin-left:4px;">{{ $patient->prescriptions->count() }}</span>
          </button>
          {{-- 무엇이 무엇으로 바뀌었는지(2026-09-08 확인요청 3ㆍ5쪽). 수정자ㆍ수정일자는
               아래 줄에 이미 서 있었지만 **무엇이** 바뀌었는지는 없었다 — 전화번호를
               고쳐도 화면에는 아무 자취가 없어 저장이 됐는지조차 알 수 없었다. --}}
          <button class="tab-btn" id="tab-btn-log" onclick="switchTab(this,'tab-log')">
            <i class="fa-solid fa-clock-rotate-left"></i> 변경 이력
          </button>
          {{-- 한 건을 열면 그 주문의 제품 줄이 이 옆 탭에 펼쳐진다. 목록에 제품명 칸을
               두었더니 여러 줄짜리 주문은 첫 줄만 보였다 — 아예 제 자리를 준다. --}}
          <button class="tab-btn" id="tab-btn-items" style="display:none;" onclick="switchTab(this,'tab-items')">
            <i class="fa-solid fa-boxes-stacked"></i> <span id="tab-items-label">주문 제품</span>
          </button>
          <span id="rxTabHint" style="margin-left:auto;font-size:11px;color:var(--text-muted);display:none;">처방번호를 누르면 주문 등록 화면이, 행을 더블클릭하면 그 주문의 제품이 열립니다.</span>
        </div>

        {{-- 개인정보 — 보는 것과 고치는 것이 한 자리다 --}}
        <div class="tab-pane active" id="tab-info">
        <div class="view-panel" id="view-panel">
          {{-- 사업부를 맨 앞에 둔다 — IC 냐 OC 냐에 따라 다루는 물건도, 붙는 서류도
               갈린다. IC 로 두면 저장되는 이름 앞에 (E) 가 붙는다(위드웍스 표기). --}}
          <div class="info-row">
            <span class="info-label">사업부</span>
            <span class="info-value">
              <span class="view-only">{{ \App\Models\Patient::CARE_TYPES[$patient->care_type] ?? '-' }}</span>
              <select class="form-control edit-only" id="e-care-type"
                      data-orig="{{ $patient->care_type }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::CARE_TYPES as $code => $label)
                  <option value="{{ $code }}" @selected($patient->care_type === $code)>{{ $label }}</option>
                @endforeach
              </select>
            </span>
          </div>
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
            <span class="info-label">환자 전화번호</span>
            <span class="info-value">
              {{-- 먼저 거는 번호에는 표를 둔다 — 두 번호를 놓고 어느 쪽인지 다시 묻지 않게 --}}
              <span class="view-only">{{ \App\Support\PhoneNo::format($patient->mobile) ?: '-' }}@if($patient->main_contact === 'mobile')<b class="mc-flag">Main</b>@endif</span>
              <input type="text" class="form-control edit-only" id="e-mobile" data-phone
                     value="{{ \App\Support\PhoneNo::format($patient->mobile) }}" data-orig="{{ \App\Support\PhoneNo::format($patient->mobile) }}" placeholder="010-XXXX-XXXX" />
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">보호자 전화번호</span>
            <span class="info-value">
              <span class="view-only">{{ \App\Support\PhoneNo::format($patient->phone) ?: '-' }}@if($patient->main_contact === 'guardian')<b class="mc-flag">Main</b>@endif</span>
              <input type="text" class="form-control edit-only" id="e-phone" data-phone
                     value="{{ \App\Support\PhoneNo::format($patient->phone) }}" data-orig="{{ \App\Support\PhoneNo::format($patient->phone) }}" placeholder="02-XXXX-XXXX" />
            </span>
          </div>
          {{-- 먼저 거는 번호는 하나다 — 두 칸에 표를 두는 대신 한 칸에서 고른다 --}}
          <div class="info-row">
            <span class="info-label">Main contact</span>
            <span class="info-value">
              <span class="view-only">{{ ['mobile' => '환자', 'guardian' => '보호자'][$patient->main_contact] ?? '-' }}</span>
              <select class="form-control edit-only" id="e-main-contact">
                <option value="">선택</option>
                <option value="mobile"   @selected($patient->main_contact === 'mobile')>환자</option>
                <option value="guardian" @selected($patient->main_contact === 'guardian')>보호자</option>
              </select>
            </span>
          </div>
          <div class="info-row wide">
            <span class="info-label">주소</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->full_address ?: '-' }}
                {{-- 주소는 한 벌이 아니다 — 집과 직장을 번갈아 쓰는 사람이 있고,
                     이사한 뒤에도 지난 주문이 어디로 갔는지 되짚어야 한다
                     (2026-09-08 확인요청 6쪽). --}}
                <button type="button" class="btn btn-outline btn-sm" style="margin-left:8px;"
                        onclick="openAddrManager({{ $patient->id }})">
                  <i class="fa-solid fa-location-dot"></i> 주소 관리
                  <span id="addrCount" style="margin-left:4px;color:var(--text-muted);">{{ $patient->addresses->count() }}</span>
                </button>
              </span>
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
          {{-- ── 요청서(2026-08-27) 2·3쪽의 나머지 칸 ──────────────
               조회결과와 상세 내용이 같은 것을 보여야 한다는 것이 재요청이었다.
               여기서 고친 값이 곧 다른 화면이 읽는 정본이다. --}}
          <div class="info-row">
            <span class="info-label">생년월일</span>
            {{-- 고칠 때도 그대로 보인다. 「위 이름 옆 칸에서 고칩니다」라는 안내를 두었더니
                 값이 있던 자리에 설명만 남아, 고치는 동안 생년월일이 무엇이었는지 볼 수
                 없었다 — 고치는 칸은 이름 줄에 있으니 여기서는 읽기만 하면 된다. --}}
            <span class="info-value">
              {{ $patient->birth_dotted ?: '-' }}
              @if($patient->birth_iso) · {{ $patient->birth_iso }} · {{ $patient->birth_year }}
                @if($patient->age !== null) · 만 {{ $patient->age }}세 @endif
              @endif
            </span>
          </div>
          {{-- 성별 — 주민번호가 있으면 거기서 저절로 서고, 없으면 여기서 고른다
               (2026-09-08 확인요청 4쪽).

               예전에는 이 칸을 두지 않았다. 주민번호에 이미 들어 있고 쓰는 곳도 없다는
               까닭이었는데, 이제 주민번호 없이도 거래처를 만들 수 있게 되어(처방외)
               그런 사람의 성별을 적어 둘 자리가 없어졌다. --}}
          <div class="info-row">
            <span class="info-label">성별</span>
            <span class="info-value">
              <span class="view-only">{{ ['male' => '남', 'female' => '여'][$patient->gender] ?? '-' }}</span>
              <select class="form-control edit-only" id="e-gender" data-orig="{{ $patient->gender }}">
                <option value="">선택</option>
                <option value="male"   @selected($patient->gender === 'male')>남</option>
                <option value="female" @selected($patient->gender === 'female')>여</option>
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">환자구분</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->sb_sci ?: '-' }}</span>
              <select class="form-control edit-only" id="e-sb-sci" data-orig="{{ $patient->sb_sci }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::sbSciOptions($patient->sb_sci) as $v)
                  <option value="{{ $v }}" @selected($patient->sb_sci === $v)>{{ $v }}</option>
                @endforeach
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">연락 상태</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->contactStatusLabel() ?: '-' }}</span>
              <select class="form-control edit-only" id="e-contact-status" data-orig="{{ $patient->contact_status }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::CONTACT_STATUSES as $k => $label)
                  <option value="{{ $k }}" @selected($patient->contact_status === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </span>
          </div>
          {{-- 마케팅 동의 (2026-09-08 확인요청 4쪽).

               동의는 개인정보동의서에서 받는다. 그런데 「동의 안 함」으로 낸 사람이
               나중에 통화에서 동의하는 일이 있다. 동의서를 고칠 수는 없다 — 본인이
               서명해 낸 것이고 무엇에 동의했는지가 그대로 남아 있어야 한다.
               여기서 덧쓰고, 어디서 온 답인지를 함께 적는다. --}}
          @php $_mk = $patient->marketing_state; @endphp
          <div class="info-row">
            <span class="info-label">마케팅 동의</span>
            <span class="info-value">
              <span class="view-only">
                {{ $_mk['value'] ?: '-' }}
                @if($_mk['source'])
                  <span style="font-size:11px;color:var(--text-muted);margin-left:6px;">
                    {{ $_mk['source'] }}@if($_mk['at']) · {{ $_mk['at'] }}@endif @if($_mk['by'])· {{ $_mk['by'] }}@endif
                  </span>
                @endif
                @if($_mk['source'] === '거래처' && $_mk['origin'] && $_mk['origin'] !== $_mk['value'])
                  {{-- 동의서에 적힌 것과 다르면 그것도 함께 밝힌다 — 뒤에 따져 물을 수
                       있는 값이라 「원래 무엇이었나」가 보여야 한다 --}}
                  <span style="font-size:11px;color:var(--text-muted);">
                    (개인정보동의: {{ $_mk['origin'] }})
                  </span>
                @endif
              </span>
              <select class="form-control edit-only" id="e-marketing-consent"
                      data-orig="{{ $patient->marketing_consent }}">
                <option value="">따로 정하지 않음 (개인정보 동의서를 따름@if($_mk['origin']) · 지금 {{ $_mk['origin'] }}@endif)</option>
                <option value="동의함"   @selected($patient->marketing_consent === '동의함')>동의함</option>
                <option value="동의안함" @selected($patient->marketing_consent === '동의안함')>동의안함</option>
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">연락 선호 방식</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->contactChannelLabel() ?: '-' }}</span>
              <select class="form-control edit-only" id="e-contact-channel" data-orig="{{ $patient->contact_channel }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::CONTACT_CHANNELS as $k => $label)
                  <option value="{{ $k }}" @selected($patient->contact_channel === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->email ?: '-' }}</span>
              <input type="email" class="form-control edit-only" id="e-email"
                     value="{{ $patient->email }}" data-orig="{{ $patient->email }}" placeholder="name@example.com" />
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Fax</span>
            <span class="info-value">
              <span class="view-only">{{ \App\Support\PhoneNo::format($patient->fax) ?: '-' }}</span>
              <input type="text" class="form-control edit-only" id="e-fax" data-phone
                     value="{{ \App\Support\PhoneNo::format($patient->fax) }}" data-orig="{{ \App\Support\PhoneNo::format($patient->fax) }}" placeholder="02-XXXX-XXXX" />
            </span>
          </div>

          {{-- 「지난 주소」 다섯 줄은 걷었다 — 「주소 관리」 창이 그 일을 한다.
               거기서는 등록ㆍ수정ㆍ삭제도 되고, 다섯에서 잘리지도 않는다. --}}

          {{-- 미성년 보호자 (2026-09-07 · 결함 ㉕) — 위임동의에서 받아 둔 사람.
               미성년이거나 이미 적어 둔 것이 있을 때만 세운다. --}}
          @if($patient->is_minor || $patient->guardian_name)
          <div class="info-row">
            <span class="info-label">보호자</span>
            <span class="info-value">
              <span class="view-only">
                @if($patient->guardian_name)
                  {{ $patient->guardian_name }}
                  @if($patient->guardian_relation) ({{ $patient->guardian_relation }}) @endif
                  @if($patient->guardian_birth_date) · {{ $patient->guardian_birth_date->format('Y-m-d') }} @endif
                  @if($patient->guardian_phone) · {{ $patient->guardian_phone }} @endif
                @else
                  -
                @endif
              </span>
              <span class="edit-only" style="display:flex;gap:6px;flex-wrap:wrap;">
                <select class="form-control" id="e-guardian-relation" style="flex:0 0 104px;"
                        data-orig="{{ $patient->guardian_relation }}">
                  <option value="">관계</option>
                  @foreach(config('delegation.guardian_relations', ['부','모','조부','조모','법정대리인']) as $r)
                    <option value="{{ $r }}" @selected($patient->guardian_relation === $r)>{{ $r }}</option>
                  @endforeach
                </select>
                <input type="text" class="form-control" id="e-guardian-name" style="flex:1 1 110px;"
                       value="{{ $patient->guardian_name }}" data-orig="{{ $patient->guardian_name }}"
                       placeholder="보호자 성명" />
                <input type="date" class="form-control" id="e-guardian-birth" style="flex:0 0 148px;"
                       value="{{ $patient->guardian_birth_date?->format('Y-m-d') }}"
                       data-orig="{{ $patient->guardian_birth_date?->format('Y-m-d') }}" />
                <input type="text" class="form-control" id="e-guardian-phone" style="flex:1 1 130px;"
                       value="{{ $patient->guardian_phone }}" data-orig="{{ $patient->guardian_phone }}"
                       placeholder="010-XXXX-XXXX" data-phone />
              </span>
            </span>
          </div>
          @endif

          <div class="info-row">
            <span class="info-label">송금자명</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->remitter_name ?: '-' }}</span>
              <input type="text" class="form-control edit-only" id="e-remitter"
                     value="{{ $patient->remitter_name }}" data-orig="{{ $patient->remitter_name }}"
                     placeholder="입금자명이 다르면 입력합니다" />
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">현금영수증</span>
            <span class="info-value">
              <span class="view-only">
                {{ $patient->deduction ?: '-' }}
                @if($patient->cash_receipt_no) · {{ $patient->cash_receipt_no }} @endif
              </span>
              {{-- 인라인 display 는 .edit-only{display:none} 을 덮는다 — 보는 중에도 칸이 서 버린다.
                   그래서 .inline 반은 클래스로 준다(위 40~42줄 규칙). --}}
              {{-- 이 줄은 값 칸이 175px 밖에 안 된다. select 120 을 고정으로 잡아 두어
                     번호 칸에 49px 만 남았고, 열세 자 번호가 31px 안에 들어갈 리 없었다.
                     번호가 쓸 폭을 먼저 잡고 모자라면 아래로 내려 앉힌다. --}}
              <span class="edit-only inline" style="flex-wrap:wrap;">
                <select class="form-control" id="e-deduction" data-orig="{{ $patient->deduction }}"
                        style="flex:1 1 124px;min-width:124px;">
                  <option value="">선택</option>
                  @foreach(\App\Models\Patient::DEDUCTION_TYPES as $t)
                    <option value="{{ $t }}" @selected($patient->deduction === $t)>{{ $t }}</option>
                  @endforeach
                </select>
                <input type="text" class="form-control" id="e-cash-receipt" data-phone
                       value="{{ $patient->cash_receipt_no }}" data-orig="{{ $patient->cash_receipt_no }}"
                       placeholder="010-XXXX-XXXX" style="flex:1 1 132px;min-width:132px;" />
              </span>
            </span>
          </div>

          {{-- ── 공단 · 기초 ────────────────────────────── --}}
          <div class="info-row">
            <span class="info-label">건보등록</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_reg_status ?: '-' }}</span>
              <select class="form-control edit-only" id="e-nhis-reg" data-orig="{{ $patient->nhis_reg_status }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::NHIS_REG_STATUSES as $v)
                  <option value="{{ $v }}" @selected($patient->nhis_reg_status === $v)>{{ $v }}</option>
                @endforeach
                @if($patient->nhis_reg_status && !in_array($patient->nhis_reg_status, \App\Models\Patient::NHIS_REG_STATUSES, true))
                  {{-- 옛 값(진행중ㆍ완료)이 담긴 건은 그 값을 잃지 않게 함께 세운다 --}}
                  <optgroup label="기존 값">
                    <option value="{{ $patient->nhis_reg_status }}" selected>{{ $patient->nhis_reg_status }}</option>
                  </optgroup>
                @endif
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">건보등록일</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_reg_date ?: '-' }}</span>
              <input type="date" class="form-control edit-only" id="e-nhis-reg-date"
                     value="{{ $patient->nhis_reg_date }}" data-orig="{{ $patient->nhis_reg_date }}" />
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">건보 재등록 대상자</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_renew ?: '-' }}</span>
              <select class="form-control edit-only" id="e-nhis-renew" data-orig="{{ $patient->nhis_renew }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::YN as $v)
                  <option value="{{ $v }}" @selected($patient->nhis_renew === $v)>{{ $v }}</option>
                @endforeach
              </select>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">건보 재등록 기한</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_renew_due ?: '-' }}</span>
              <input type="date" class="form-control edit-only" id="e-nhis-renew-due"
                     value="{{ $patient->nhis_renew_due }}" data-orig="{{ $patient->nhis_renew_due }}" />
            </span>
          </div>
          {{-- 급여 종료일 — 이 사람이 언제까지 쓰는가(2026-09-09 지시).

               건마다 서는 값이라 여기서 고치지 않는다. 가장 나중 날짜를 가진 건에서
               읽어 보여 준다 — 담아 두면 두 곳이 어긋나고 어느 쪽이 맞는지 가려낼 길이
               없다. 재구매 안내도 재등록 안내도 이 날짜를 보고 건다. --}}
          @php $_be = $patient->benefit_end; @endphp
          <div class="info-row">
            <span class="info-label">급여 종료일</span>
            <span class="info-value">
              {{ $_be['date'] ?: '-' }}
              @if($_be['rx'])
                <span style="font-size:11px;color:var(--text-muted);margin-left:6px;">{{ $_be['rx'] }}</span>
              @endif
            </span>
          </div>
          <div class="info-row w2">
            <span class="info-label">건보위임동의 시작일</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_agree_start ?: '-' }}</span>
              {{-- 시작일을 적으면 종료일이 ＋5년 −1일로 선다 — 위임장이 세는 잣대와 같다 --}}
              <input type="date" class="form-control edit-only" id="e-agree-start"
                     onchange="psAutoAgreeEnd(this.value)"
                     value="{{ $patient->nhis_agree_start }}" data-orig="{{ $patient->nhis_agree_start }}" />
            </span>
          </div>
          <div class="info-row w2">
            <span class="info-label">건보위임동의 종료일</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->nhis_agree_end ?: '-' }}</span>
              <input type="date" class="form-control edit-only" id="e-agree-end"
                     value="{{ $patient->nhis_agree_end }}" data-orig="{{ $patient->nhis_agree_end }}" />
            </span>
          </div>
          <div class="info-row w2">
            <span class="info-label">기초(의료급여) 재평가 대상자</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->basic_reeval ?: '-' }}</span>
              <select class="form-control edit-only" id="e-basic-reeval" data-orig="{{ $patient->basic_reeval }}">
                <option value="">선택</option>
                @foreach(\App\Models\Patient::YN as $v)
                  <option value="{{ $v }}" @selected($patient->basic_reeval === $v)>{{ $v }}</option>
                @endforeach
              </select>
            </span>
          </div>
          <div class="info-row w2">
            <span class="info-label">기초(의료급여) 재평가 기한</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->basic_reeval_due ?: '-' }}</span>
              <input type="date" class="form-control edit-only" id="e-basic-due"
                     value="{{ $patient->basic_reeval_due }}" data-orig="{{ $patient->basic_reeval_due }}" />
            </span>
          </div>

          <div class="info-row wide">
            <span class="info-label">메모</span>
            <span class="info-value">
              <span class="view-only" style="white-space:pre-wrap;">{{ $patient->note ?: '-' }}</span>
              <textarea class="form-control edit-only" id="e-note" rows="2"
                        data-orig="{{ $patient->note }}" placeholder="특이사항 등">{{ $patient->note }}</textarea>
            </span>
          </div>
          {{-- 신환 Master 등록일은 담당자가 적는 업무 값이다. 아래 자취 줄에 이어
               붙여 두었더니 「수정일자 다음에 오는 또 다른 날짜」로 읽혔다 — 성격이
               다른 값이라 제 칸으로 세운다(2026-09-09 지시). --}}
          <div class="info-row">
            <span class="info-label">신환 Master 등록일</span>
            <span class="info-value">
              <span class="view-only">{{ $patient->new_patient_date ?: '-' }}</span>
              <input type="date" class="form-control edit-only" id="e-new-patient-date"
                     value="{{ $patient->new_patient_date }}" data-orig="{{ $patient->new_patient_date }}" />
            </span>
          </div>

          {{-- 마지막으로 손댄 사람 하나만 적는다(2026-09-09 지시).

               고친 적이 없으면 등록한 사람이 곧 마지막이다 — 그때 「수정 -」을 함께
               적으면 빈 자리를 읽게 한다. 고친 적이 있으면 등록한 사람은 이 줄에서
               할 말이 없다: 누가 만들었는지까지 알아야 하면 「변경 이력」 탭이 처음
               줄부터 다 보여 준다.

               라벨은 두지 않는다 — 뒤따르는 글이 스스로 무엇인지 말한다. --}}
          @php
            $_고쳤나 = $patient->updater
                        && $patient->updated_at
                        && $patient->created_at
                        && $patient->updated_at->gt($patient->created_at);
          @endphp
          <div class="info-row wide">
            <span class="info-value" style="font-size:12px;color:var(--text-muted);">
              @if($_고쳤나)
                수정 : {{ $patient->updater->name }} · {{ $patient->updated_at->format('Y-m-d H:i') }}
              @else
                등록 : {{ $patient->creator?->name ?: '-' }} · {{ $patient->created_at?->format('Y-m-d H:i') ?: '-' }}
              @endif
            </span>
          </div>

          {{-- 건강보험번호·급여 적용은 이 화면에서 보지 않는다. 급여 여부는 주문
               한 건마다 정해지는 것이라, 사람에 붙여 두면 실제와 어긋난다. --}}
          {{-- 메모 칸은 두지 않는다. 사람에 붙은 한 줄 메모는 어느 주문 이야기인지
               알 수 없어 적어 두어도 다음 사람이 쓰지 못했다 — 통화 기록은 상담내역에 남는다.
               이미 적혀 있는 메모는 지우지 않는다(화면에서 보내지 않을 뿐이다). --}}
        </div>
        </div>{{-- /tab-info --}}

        {{-- 다른 목록 화면과 같은 표를 쓴다. 손으로 그린 줄은 정렬도 엑셀 저장도 없어,
             건수가 늘면 훑을 방법이 눈뿐이었다. --}}
        {{-- 처음 열면 개인정보가 선다. 주문 이력은 옆 탭이다. --}}
        <div class="tab-pane" id="tab-rx">
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

        {{-- 변경 이력 — 목록과 상세가 같은 자리를 나눠 쓴다. 주문 등록의 「저장 이력」과
             같은 모양이고, 세는 일도 같은 것을 쓴다(App\Support\SaveHistory). --}}
        <div class="tab-pane" id="tab-log">
          <div class="pl-tabs">
            <button type="button" class="pl-tab active" data-view="list"
                    onclick="plView('list')">목록</button>
            <button type="button" class="pl-tab" data-view="detail" id="plDetailTab"
                    onclick="plView('detail')" disabled>상세 보기</button>
          </div>

          <div id="plList">
            <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px;">
              <div style="flex:1;min-width:180px;">
                <label class="ds-field-label">검색어</label>
                <input type="text" id="pl-q" class="form-control"
                       placeholder="항목ㆍ값ㆍ설명" oninput="plFilter()">
              </div>
              <div style="width:150px;">
                <label class="ds-field-label">작업자</label>
                <select id="pl-who" class="form-control form-select" onchange="plFilter()">
                  <option value="">전체</option>
                </select>
              </div>
              <button type="button" class="ds-btn" onclick="plReset()">초기화</button>
              <button type="button" class="ds-btn" onclick="loadPatientLog(true)">새로고침</button>
            </div>

            <div class="ds-grid-hint" id="plNote" style="margin-bottom:6px;"></div>
            <div id="plGrid" style="min-height:220px;"></div>
          </div>

          <div id="plDetail" style="display:none;">
            <div class="pl-head" id="plHead"></div>
            <div class="pl-diff" id="plDiff"></div>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>

{{-- 주소 관리 창 — 거래처 수정 창(주문 등록)도 같은 것을 쓴다 --}}
@include('patients._address-modal')

@endsection

@push('scripts')
<script>
  /* 구입 확인서 메뉴 — 바깥을 누르면 닫는다 */
  window.pcToggle = function (e) {
    e.stopPropagation();
    document.getElementById('pcPop')?.classList.toggle('open');
  };
  document.addEventListener('click', () => document.getElementById('pcPop')?.classList.remove('open'));
</script>


<script>
  /* 주문 이력 표 — 행을 더블클릭하면 그 주문의 제품을 옆 탭에서 펼친다.
     wwGrid 에는 on() 이 없어 셀에서 행 번호를 읽는다. */
  (function () {
    const el = document.getElementById('rxGrid');
    if (!el || typeof wwGrid === 'undefined') return;

    const rows = @json($rxRows);
    const grid = new wwGrid({
      el,
      height: 'auto', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
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
      /* 제품값과 주문 총액은 같은 수가 아니다 — 표는 제품값만 적고,
         주문 총액은 이 줄에 적는다. */
      const won = (n) => Number(n || 0).toLocaleString() + '원';
      document.getElementById('itemsNote').textContent = items.length
        ? label + ' · ' + items.length + '건'
          + (row.total_amt ? ' · 주문 총액 ' + won(row.total_amt) : '')
        : label + ' — 적혀 있는 제품이 없습니다.';

      const gridEl = document.getElementById('itemsGrid');
      if (!itemsGrid) {
        itemsGrid = new wwGrid({
          el: gridEl,
          height: 'auto', editable: false, rowNumber: true, toolbar: false, footer: { total: true, selected: false, modified: false },
          columns: [
            { header: '제품명',     name: 'name',       width: 300 },
            { header: '제품코드',   name: 'code',       width: 130 },
            { header: '수량',       name: 'qty',        width: 70,  align: 'right', editor: 'number' },
            { header: '단가',       name: 'unit_price', width: 110, align: 'right', editor: 'number',
              summary: false },  // 단가는 한 개 값이라 더하지 않는다
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
      showToast('주소 찾기를 불러오지 못했습니다. 직접 입력하십시오.', 'warning');
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
  /* 같은 사람을 다른 자리에서 고치면 이 화면도 다시 읽는다.
     한 사람을 두 탭에 펴 놓고 한쪽에서 고치면, 다른 쪽은 옛 값을 보여 준 채로 남아
     거기서 저장하면 방금 고친 것을 되돌려 놓는다.
     제가 보낸 말은 제게 오지 않으므로(BroadcastChannel), 저장하고 다시 읽는 길과
     겹치지 않는다. */
  try {
    const _psCh = new BroadcastChannel('ce-patient');
    _psCh.onmessage = (e) => {
      if (e.data?.action !== 'saved') return;
      if (String(e.data.id) !== String(@json($patient->id))) return;
      location.reload();
    };
  } catch (e) { /* 못 하는 브라우저면 손으로 다시 열어야 한다 */ }
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

    /* 「처방번호를 누르면…」 안내는 표가 있는 탭에서만 뜻이 있다. 개인정보를 보는
       동안에도 떠 있으면 무엇을 누르라는 말인지 알 수 없다. */
    const hint = document.getElementById('rxTabHint');
    if (hint) hint.style.display = (id === 'tab-info') ? 'none' : '';

    /* 변경 이력은 열 때 한 번만 불러온다 — 화면을 세울 때마다 부르면 이 탭을 한 번도
       보지 않는 사람에게도 질의가 나간다 */
    if (id === 'tab-log') loadPatientLog();
  }


  /* ── 변경 이력 (2026-09-08 확인요청 3ㆍ5쪽) ─────────────
     세는 일은 서버가 한다(App\Support\SaveHistory) — 주문 등록의 「저장 이력」과
     같은 것을 쓴다. 여기서는 받아 세우고, 거르는 것만 그 자리에서 한다. */

  /* 건보위임동의 종료일 = 시작일 ＋N년 −1일. 요양비 지급청구 위임장이 세는 잣대와
     같다 — 두 곳이 다르게 세면 같은 환자가 서류와 화면에서 다른 날짜로 찍힌다. */
  const 위임기간_년 = {{ min(5, max(1, (int) config('delegation.period_years', 5))) }};

  window.psAutoAgreeEnd = function (startVal) {
    if (!startVal) return;
    const d = new Date(startVal);
    d.setFullYear(d.getFullYear() + 위임기간_년);
    d.setDate(d.getDate() - 1);
    const 끝 = document.getElementById('e-agree-end');
    if (!끝) return;
    끝.value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  };

  const PL_URL = @json(route('patients.changeLog', $patient));
  let _plLoaded = false, _plRows = [], _plShown = [], _plGrid = null;

  const _plEsc = (v) => String(v ?? '').replace(/[&<>"']/g,
    c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));

  async function loadPatientLog(force = false) {
    if (_plLoaded && !force) return;

    const note = document.getElementById('plNote');
    if (note) note.textContent = '불러오는 중…';

    let d;
    try {
      const res = await fetch(PL_URL, { headers: { Accept: 'application/json' } });
      d = await res.json();
    } catch (e) {
      if (note) note.textContent = '이력을 불러오지 못했습니다.';
      return;
    }
    if (!d.success) { if (note) note.textContent = d.message || '이력을 불러오지 못했습니다.'; return; }

    _plLoaded = true;
    _plRows = d.rows || [];

    /* 고르는 칸의 선택지는 받아 둔 줄에서 뽑는다 — 없는 값을 고르게 두지 않는다 */
    const sel = document.getElementById('pl-who');
    if (sel) {
      const keep = sel.value;
      sel.innerHTML = '<option value="">전체</option>';
      [...new Set(_plRows.map(r => r.who).filter(Boolean))].sort().forEach(v => {
        const o = document.createElement('option');
        o.value = v; o.textContent = v;
        sel.appendChild(o);
      });
      sel.value = keep;
    }

    plFilter();
  }

  function plFilter() {
    const q   = (document.getElementById('pl-q')?.value ?? '').trim().toLowerCase();
    const who = (document.getElementById('pl-who')?.value ?? '').trim();

    _plShown = _plRows.filter(r => {
      if (who && r.who !== who) return false;
      if (!q) return true;
      /* 검색어는 매달린 칸까지 뒤진다 — 「그 번호로 바꾼 저장이 언제였나」를 값으로
         찾을 수 있어야 한다 */
      return [r.summary, r.note, r.who,
              ...(r.fields || []).flatMap(f => [f.field, f.before, f.after])]
             .join(' ').toLowerCase().includes(q);
    });

    const note = document.getElementById('plNote');
    if (note) {
      note.textContent = _plRows.length === 0
        ? '아직 이력이 없습니다.'
        : (_plShown.length === _plRows.length
            ? `변경 ${_plRows.length}건입니다. 줄을 누르면 무엇이 바뀌었는지 견줍니다.`
            : `변경 ${_plRows.length}건 가운데 ${_plShown.length}건입니다.`);
    }

    plDraw();
  }

  function plReset() {
    ['pl-q', 'pl-who'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    plFilter();
  }

  function plDraw() {
    const box = document.getElementById('plGrid');
    if (!box || typeof wwGrid === 'undefined') return;

    /* 표는 한 번만 세우고 이후에는 줄만 갈아 끼운다 — 다시 세우면 담당자가 조정해 둔
       열너비와 정렬이 풀린다 */
    if (_plGrid) { _plGrid.setData(_plShown); return; }

    _plGrid = new wwGrid({
      el: box, height: 300, editable: false, rowCheckbox: false, rowNumber: false,
      footer: { total: true, selected: false, modified: false },
      emptyText: '아직 이력이 없습니다.',
      columns: [
        { header: 'No',        name: 'no',      width: 50,  align: 'center', sortable: true, summary: false },
        { header: '일시',      name: 'at',      width: 145, sortable: true },
        { header: '작업자',    name: 'who',     width: 90,  sortable: true },
        { header: '설명',      name: 'note',    width: 140, sortable: true },
        { header: '바뀐 항목', name: 'summary', width: 340, sortable: true,
          renderer: (v) => {
            const s = document.createElement('span');
            s.textContent = v || '내역이 없습니다.';
            if (!v) { s.style.color = 'var(--text-muted)'; s.style.fontSize = '11px'; }
            return s;
          } },
        { header: '건수',      name: 'count',   width: 60, align: 'center', sortable: true, summary: false },
      ],
      data: _plShown,
    });

    /* 줄을 누르면 그 저장을 견준다. 한 번 누름이다 — 이 표는 어디로 떠나지 않고 옆
       탭을 채울 뿐이라 되돌릴 것이 없다. */
    box.addEventListener('click', (e) => {
      const cell = e.target.closest('[data-row-index]');
      if (!cell) return;
      const row = _plGrid.getData()[parseInt(cell.dataset.rowIndex, 10)];
      if (row) plOpen(row);
    });
  }

  /* 고른 저장을 저장 전(왼쪽)ㆍ저장 후(오른쪽)로 세운다 */
  function plOpen(row) {
    const head = document.getElementById('plHead');
    const diff = document.getElementById('plDiff');
    if (!head || !diff) return;

    const 짝 = (k, v) => `<span><span class="pl-head-k">${k}</span><b>${_plEsc(v) || '-'}</b></span>`;
    head.innerHTML = 짝('일시', row.at) + 짝('작업자', row.who) + 짝('설명', row.note)
                   + 짝('바뀐 항목', row.count ? row.count + '개' : '없음');

    const 칸들 = row.fields || [];
    if (!칸들.length) {
      diff.innerHTML = '<div class="pl-none">내역이 없습니다.</div>';
    } else {
      const 값 = (v) => v ? _plEsc(v) : '<span class="pl-v-empty">(빈 값)</span>';
      diff.innerHTML =
        '<div class="pl-row pl-cap"><div>저장 전</div><div>항목</div><div>저장 후</div></div>'
        + 칸들.map(f => `<div class="pl-row">
             <div class="pl-v pl-v-before">${값(f.before)}</div>
             <div class="pl-k">${_plEsc(f.field)}</div>
             <div class="pl-v pl-v-after">${값(f.after)}</div>
           </div>`).join('');
    }

    document.getElementById('plDetailTab').disabled = false;
    plView('detail');
  }

  function plView(which) {
    document.querySelectorAll('.pl-tab').forEach(b =>
      b.classList.toggle('active', b.dataset.view === which));
    document.getElementById('plList').style.display   = which === 'list'   ? '' : 'none';
    document.getElementById('plDetail').style.display = which === 'detail' ? '' : 'none';
  }

  /* 고치기로 들어가고 나오는 길. 칸을 갈아 끼우지 않고 표시만 바꾼다 —
     보던 자리가 그대로 있어야 무엇을 고치는지 눈으로 따라갈 수 있다. */
  function toggleEdit(on) {
    /* 고치는 칸은 개인정보 탭에 있다. 주문 이력을 보다가 「수정」을 누르면 아무 일도
       일어나지 않은 것처럼 보이므로, 고칠 자리로 함께 옮겨 준다. */
    if (on) {
      const btn = document.getElementById('tab-btn-info');
      if (btn && !btn.classList.contains('active')) switchTab(btn, 'tab-info');
    }

    document.querySelector('.detail-layout').classList.toggle('is-editing', on);
    // 그만두면 손댄 것은 없던 일로 한다
    if (!on) {
      document.querySelectorAll('.detail-layout [data-orig]').forEach(el => {
        el.value = el.dataset.orig ?? '';
      });
    }
    if (on) setTimeout(() => document.getElementById('e-name')?.focus(), 30);
  }

  /* 빈 칸은 null 로 보낸다 — 빈 글자를 담으면 날짜 칸 검증에서 걸리고,
     「적었는데 비웠다」와 「안 적었다」가 구별되지 않는다. */
  const ev = (id) => (document.getElementById(id)?.value ?? '').trim() || null;

  /* 현금영수증 번호는 고른 갈래마다 다르다 (2026-09-10 확인요청 1쪽).

     소득공제 — 발행받는 사람의 전화번호로 낸다. 거래처 전화번호를 그 자리에서 채운다.
     자진발급 — 번호를 못 받았다는 표시로 국세청이 정한 자리(010-000-1234)를 쓴다.
     지출증빙 — 사업자번호로 내므로 이 칸을 쓰지 않는다. 비운다.

     고를 때마다 다시 셈한다. 2026-09-09 에 자진발급 자동 채우기를 걷었던 것은
     그 번호가 소득공제로 되돌린 뒤에도 남아 엉뚱한 번호로 발행될 수 있어서였다 —
     이제 갈래를 바꾸면 늘 그 갈래의 값으로 다시 서므로 남을 자리가 없다.

     비워 두어도 발행은 막히지 않는다 — 발행 쪽이 번호가 없으면 거래처 전화번호로
     대신한다(DepositAutoIssueㆍCashbillController). */
  /** 고른 갈래에 맞는 현금영수증 번호 — 없으면 빈 글자 */
  function ps현금영수증번호(갈래, phoneId) {
    if (갈래 === '자진발급') return '{{ \App\Models\Patient::SELF_ISSUE_NO }}';
    if (갈래 !== '소득공제') return '';

    /* 전화번호 칸은 화면 설정에 따라 고르는 칸일 수도, 적는 칸일 수도 있다 —
       어느 쪽이든 지금 값을 그대로 가져온다. */
    return [...document.querySelectorAll('#' + phoneId)]
      .map(e => (e.value || '').trim()).find(v => v) || '';
  }

  function psCashReceiptRule(sel, noId, phoneId) {
    const no = document.getElementById(noId);
    if (!no) return;
    no.value = ps현금영수증번호(sel.value, phoneId);
  }

  document.getElementById('e-deduction')?.addEventListener('change', function () {
    psCashReceiptRule(this, 'e-cash-receipt', 'e-mobile');
  });


  /**
   * 같은 번호를 쓰는 거래처가 있으면 한 번 묻는다 (2026-09-10 확인요청 1쪽).
   *
   * 막지 않는다 — 보호자 한 사람이 환자 둘을 맡아 같은 번호로 연락하는 일이 있다.
   * 다만 모르고 두 벌을 만드는 일도 잦아, 누구와 겹치는지 이름을 적어 보여 준다.
   *
   * 물어보지 못하면 그대로 저장한다 — 알림 하나 때문에 저장을 막을 까닭이 없다.
   *
   * @returns {Promise<boolean>}  저장을 이어 갈 것인가
   */
  async function ps전화번호겹침확인(mobile, phone, exceptId) {
    const 물음 = new URLSearchParams();
    if (mobile) 물음.set('mobile', mobile);
    if (phone)  물음.set('phone',  phone);
    if (exceptId) 물음.set('except', exceptId);

    if (!물음.has('mobile') && !물음.has('phone')) return true;

    let 겹친것 = [];

    try {
      const res = await fetch(@json(url('patients/phone-check')) + '?' + 물음.toString(),
                              { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return true;
      겹친것 = (await res.json()).found ?? [];
    } catch (e) {
      return true;
    }

    if (!겹친것.length) return true;

    const 목록 = 겹친것.map(p => `· ${p.name} (${p.mobile || p.phone || ''})`).join('\n');

    return await ceConfirm(
      '동일 전화번호가 있으니 확인 후 저장 바랍니다.\n\n' + 목록,
      { title: '같은 전화번호가 있습니다', tone: 'warning',
        confirmText: '확인했습니다 · 저장', cancelText: '다시 보기' });
  }

  async function savePatient() {
    const name = document.getElementById('e-name').value.trim();
    if (!name) { showToast('이름은 필수입니다.', 'warning'); return; }

    /* 같은 번호를 쓰는 거래처가 있으면 한 번 묻는다 — 막지는 않는다 */
    if (!await ps전화번호겹침확인(
          document.getElementById('e-mobile').value.trim(),
          document.getElementById('e-phone').value.trim(),
          @json($patient->id))) {
      return;
    }

    const btn = document.getElementById('btn-save');
    BtnState.loading(btn, '저장 중...');

    const payload = {
      name,
      // 마스킹 그대로면 '변경 없음' — 보낸 값이 없으면 서버가 기존 값을 건드리지 않는다
      resident_no:         (function (el) {
                             const v = el.value.trim();
                             return (v === '' || v === el.dataset.masked) ? undefined : v;
                           })(document.getElementById('e-resident')),
      care_type:           document.getElementById('e-care-type').value           || null,
      birth_date:          document.getElementById('e-birth').value               || null,
      gender:              document.getElementById('e-gender').value              || null,
      main_contact:        document.getElementById('e-main-contact').value || null,
      marketing_consent:   document.getElementById('e-marketing-consent').value || null,
      new_patient_date:    document.getElementById('e-new-patient-date').value || null,
      mobile:              document.getElementById('e-mobile').value.trim()       || null,
      phone:               document.getElementById('e-phone').value.trim()        || null,
      address:             document.getElementById('e-address').value.trim()      || null,
      postcode:            document.getElementById('e-postcode').value.trim()     || null,
      address_detail:      document.getElementById('e-address-detail').value.trim()|| null,

      // ── 화면 확정요청 2026-08-27 (2ㆍ3쪽) ──
      sb_sci:           ev('e-sb-sci'),
      contact_status:   ev('e-contact-status'),
      contact_channel:  ev('e-contact-channel'),
      email:            ev('e-email'),
      fax:              ev('e-fax'),
      remitter_name:    ev('e-remitter'),
      guardian_name:       ev('e-guardian-name'),
      guardian_relation:   ev('e-guardian-relation'),
      guardian_birth_date: ev('e-guardian-birth'),
      guardian_phone:      ev('e-guardian-phone'),
      deduction:        ev('e-deduction'),
      cash_receipt_no:  ev('e-cash-receipt'),
      nhis_reg_status:  ev('e-nhis-reg'),
      nhis_reg_date:    ev('e-nhis-reg-date'),
      nhis_renew:       ev('e-nhis-renew'),
      nhis_renew_due:   ev('e-nhis-renew-due'),
      nhis_agree_start: ev('e-agree-start'),
      nhis_agree_end:   ev('e-agree-end'),
      basic_reeval:     ev('e-basic-reeval'),
      basic_reeval_due: ev('e-basic-due'),
      note:             ev('e-note'),

      // 건강보험번호·급여 항목은 화면에서 걷어냈다 — 보내지 않으면 저장된 값은 그대로 남는다
      _method:             'PUT',
    };

    const res = await apiRequest(`/patients/{{ $patient->id }}`, 'POST', payload);

    if (res.success) {
      BtnState.success(btn, '저장 완료');
      showToast(res.message, 'success');
      /* 이 사람을 펴 놓은 다른 화면에 알린다 — 주문 등록 화면이 적어 둔 거래처 항목을
         그 자리에서 다시 읽는다. 같은 자리(origin)의 화면끼리만 오가는 말이다.
         받는 쪽이 없어도 그만이라 실패를 따지지 않는다. */
      try {
        const ch = new BroadcastChannel('ce-patient');
        ch.postMessage({ action: 'saved', id: @json($patient->id), name });
        ch.close();
      } catch (e) { /* 이 브라우저가 못 하면 예전처럼 화면을 다시 열어야 한다 */ }
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
  { selector: '#btn-edit', title: '정보 편집', body: '보던 자리에서 바로 수정합니다. 수정 오른쪽에 저장·취소가 나타납니다.' },
  { selector: '.card', title: '처방·주문 이력', body: '이 환자의 처방전 업로드 이력과 주문 내역을 확인합니다. 처방번호 클릭 시 상세 화면으로 이동합니다.' },
];
</script>
@endpush
