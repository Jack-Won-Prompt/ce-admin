{{-- 위드웍스 연동 설정 폼.
     서비스 연동 설정 화면의 탭 하나로 들어간다. iframe 이 아니라 같은 문서에 직접 그린다 —
     프레임이면 높이를 따로 맞춰야 하고, 저장 뒤 부모 화면의 탭 자리를 잃는다.

     이 탭만 저장 대상이 스키마(settings 표)가 아니라 전용 표(withworks_settings)라
     폼이 제 라우트로 따로 나간다. 그래서 generic 렌더러에 얹지 않고 조각으로 뺐다. --}}

<style>
/* ── 얼개 ───────────────────────────────────────────────────────────
   목록 화면 스물넷과 같은 얼개로 세운다 — 회색 바탕 위에 흰 카드가 gap 12 로 선다.
   전에는 흰 판(.ws-shell · r12 · pad 16) 안에 테두리 1px 짜리 흰 카드 다섯을 넣어
   「카드 안의 카드」가 됐다. 시안 카드에는 테두리가 없고(148:6653 · 156:7261 · 382:383),
   화면 쉰둘을 재도 카드 안에 든 카드는 하나도 없다.
   판을 투명으로 걷으면 회색 바탕이 카드 사이를 갈라 테두리 없이도 경계가 산다. */
.ws-shell { display:flex; flex-direction:column; gap:12px; background:transparent; padding:0; }
.ws-shell > form { margin:0; }
/* 카드 다섯은 저장 폼 안에 있다 — 판의 gap 이 거기까지 닿지 않아 폼도 같은 세로 flex 로 둔다 */
.ws-form { display:flex; flex-direction:column; gap:12px; }

.ws-card { background:var(--bg-card); border:none; border-radius:var(--radius-lg); overflow:hidden; }
  /* 카드 안여백은 표준 12/16 이다. pad 11/16 · 14/16 은 간격 어휘 밖이었다. */
  /* 줄높이를 못박는다 — 비워 두면 normal(22.4)이 나와 어휘(…18·19·21·22·26) 밖으로 떨어진다.
     색·크기·줄높이 모두 형제 탭의 .ss-card-title(14/700 · lh22 · gray-900)과 같은 값이다. */
  .ws-hd { padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; font-weight:700;
           line-height:22px; color:var(--gray-900);
           display:flex; align-items:center; gap:8px; }
  .ws-hd .grow { flex:1; }
  .ws-bd { padding:12px 16px; }
  /* 마지막 줄의 아래 여백까지 더해지면 카드 아래 안여백이 24 가 된다 — 12 로 맞춘다 */
  .ws-bd > *:last-child { margin-bottom:0; }
  .ws-row { display:flex; gap:12px; align-items:center; margin-bottom:12px; }
  /* 항목 이름은 검색 카드의 .ds-field-label 과 같은 규격이다 */
  .ws-row > label { width:120px; flex-shrink:0; font-size:13px; font-weight:500; line-height:21px;
                    color:var(--gray-700); }
  /* 안내문 — 시안 규격 12/500 · lh19 · gray-600. gray-400 은 흰 바탕 대비 2.70:1 이라 읽기 어렵다. */
  .ws-hint { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-top:4px; }

  /* 지금 어디에 붙어 있는지가 이 화면에서 가장 먼저 보여야 한다 */
  .ws-mode { display:flex; gap:12px; flex-wrap:wrap; }
  .ws-opt { flex:1; min-width:240px; cursor:pointer; }
  .ws-opt input { position:absolute; opacity:0; }
  .ws-opt > span {
    display:block; border:1px solid var(--border); border-radius:var(--radius-lg);
    padding:12px 16px; background:var(--gray-0);
  }
  .ws-opt input:checked + span { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary-100); }
  .ws-opt .t { font-size:13px; font-weight:700; display:flex; align-items:center; gap:6px; }
  .ws-opt .d { font-size:11px; color:var(--gray-600); margin-top:4px; line-height:18px; }
  .ws-opt.prod input:checked + span { border-color:var(--alert-500); box-shadow:0 0 0 2px var(--alert-100); }
  .ws-opt.prod .t { color:var(--alert-500); }

  /* 줄높이 14 → 배지 높이 4+14+4 = 22. 전역 .badge 와 같은 22 라야 이 배지가 든 첫 카드의
     머리가 나머지 넷과 같은 47 이 된다(줄높이를 비워 두면 19 라 그 카드만 52 였다). */
  .ws-now { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700;
            line-height:14px; padding:4px 10px; border-radius:999px; }
  .ws-now.test { background:var(--primary-100); color:var(--primary-600); }
  .ws-now.prod { background:var(--alert-100); color:var(--alert-500); }
  /* 알림 띠 셋 — 형제 설정 화면(설정·본인확인 / 설정·OCR)의 같은 자리 상자와 규격을 맞춘다.
     그쪽은 안여백 12/16 인데 여기만 10/12 였다. 아래 여백은 .page-body 의 gap 12 가 만든다. */
  .ws-bar { border-radius:8px; padding:12px 16px; font-size:12px; font-weight:400; line-height:18px; }
  /* .status-ok 와 같은 값 — primary-50 / primary-200 / primary-700 · 13/500 · lh21 */
  .ws-bar.ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700);
               font-size:13px; font-weight:500; line-height:21px; }
  .ws-bar.info { background:var(--gray-100); border:1px solid var(--gray-200); color:var(--gray-700);
                 font-size:13px; font-weight:500; line-height:21px; }
  /* 결측 알림 — 전에는 마크업의 인라인 style 로 alert-200 테두리를 주고 있었는데
     alert 램프는 50·100·500 셋뿐이라 var() 가 무효가 되어 테두리가 통째로 사라졌다
     (실측 border-width 0px). .ns-warn·.ocr-warn 과 같은 연톤 한 쌍으로 맞춘다. */
  .ws-bar.warn { background:var(--alert-50); border:1px solid var(--alert-100); color:var(--alert-500); }

/* ── 단추 줄 ────────────────────────────────────────────────────────
   다른 설정 화면(서비스 연동 설정의 나머지 탭 열)과 같은 자리다 —
   카드 아래 한 줄, 오른쪽 끝, 사이 8, 「연결 확인」 다음에 「저장」.
   전에는 두 줄로 갈라져 있었다(저장 y1437 · 연결 확인 y1481).

   저장 폼과 연결 확인 폼은 라우트가 다르고 저장 쪽은 @method('PUT') 을 실어 나가므로
   한 폼에 담을 수 없다. 폼을 겹치는 대신 단추 하나만 form= 으로 제 폼을 가리킨다 —
   폼은 서로 남남이고 단추 둘만 한 줄에 선다.
   줄 자체는 저장 폼 안에 둔다. 전역 「처리 중…」 잠금(layouts/app.blade.php)이
   제출한 폼의 「자손」 submit 단추만 찾기 때문에, DB 를 쓰는 저장이 그 잠금을 가져야 한다.

   이 줄이 판의 마지막 흰 것이라 카드와 같은 흰 띠(pad 12/16 · r12)로 둔다.
   그래야 흰 것이 본문 바닥에 닿는다(회색 띠 0). */
.ws-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap;
              padding:12px 16px; background:var(--bg-card); border-radius:var(--radius-lg); }
/* 연결 확인 폼은 _token 하나만 나르는 빈 폼이다 — 자리를 차지하지 않는다 */
#wwTestForm { display:none; }

/* ── 표준 카드 안에 들어갔을 때 ─────────────────────────────────────
   이 조각은 두 곳에 들어간다. 제 화면(/settings/withworks)에서는 회색 바탕 위라
   위처럼 흰 카드 다섯으로 선다. 서비스 연동 설정에서는 이미 표준 카드(.ds-grid-card)
   안이라 흰 것 위의 흰 것이 되어 경계가 사라지고 안여백도 16+16 으로 겹친다.
   전역 규칙 `.ds-grid-card .cg-wrap { border:0; border-radius:0 }` 과 같은 이치로,
   카드 안에서는 카드 노릇을 그만두고 구획(제목 + 아래 1px)으로 선다.
   좌우 안여백은 카드가 이미 갖고 있으니 여기서는 위아래만 준다. */
.ds-grid-card .ws-card { background:transparent; border-radius:0; overflow:visible; }
.ds-grid-card .ws-hd,
.ds-grid-card .ws-bd { padding-left:0; padding-right:0; }
.ds-grid-card .ws-actions { background:transparent; border-radius:0; padding:0; }
</style>

@if(session('status'))<div class="ws-bar ok">{{ session('status') }}</div>@endif
@if(session('test_result'))<div class="ws-bar info">{{ session('test_result') }}</div>@endif

{{-- 이 값들은 .env 가 아니라 여기서만 온다. 비어 있으면 연동이 조용히 멈추므로 알린다. --}}
@php
  $missing = [];
  if (!$s->apiUrl())        { $missing[] = ($s->isProduction() ? '운영' : '테스트') . ' 주소'; }
  if (!$s->apiToken())      { $missing[] = ($s->isProduction() ? '운영' : '테스트') . ' API 토큰'; }
  if (!$s->accountId())     { $missing[] = '콜로 거래처'; }
  if (!$s->webhook_secret)  { $missing[] = '콜백 공유 비밀'; }
@endphp
@if($missing)
  <div class="ws-bar warn">
    비어 있는 항목 — {{ implode(' · ', $missing) }}.
    주소·토큰이 없으면 주문이 넘어가지 않고, 공유 비밀이 없으면 들어오는 콜백을 전부 거절합니다.
  </div>
@endif

<div class="ws-shell fill-rest fill-col">
{{-- 남는 높이는 판(.fill-col) → 폼 → 마지막 카드로 이어진다. 사슬이 끊기면
     단추 줄 아래로 회색이 드러난다(전에는 판이 흰 판이라 가려져 있었다). --}}
<form id="wwSaveForm" class="ws-form fill-rest fill-col"
      method="POST" action="{{ route('withworks-settings.update') }}">
  @csrf
  @method('PUT')

  <div class="ws-card">
    <div class="ws-hd">
      연결 대상
      <div class="grow"></div>
      <span class="ws-now {{ $s->isProduction() ? 'prod' : 'test' }}">
        지금 {{ $s->modeLabel() }}
      </span>
    </div>
    <div class="ws-bd">
      <div class="ws-mode">
        <label class="ws-opt">
          <input type="radio" name="mode" value="test" @checked(!$s->isProduction())>
          <span>
            <span class="t"><i class="bx bx-test-tube"></i> 테스트 (데모웍스)</span>
            <span class="d">
              {{ $s->test_api_url ?: '주소 없음' }}<br>
              콜로 거래처 {{ $s->test_account_id ?: '—' }}
            </span>
          </span>
        </label>
        <label class="ws-opt prod">
          <input type="radio" name="mode" value="production" @checked($s->isProduction())>
          <span>
            <span class="t"><i class="bx bx-error-circle"></i> 운영 (위드웍스)</span>
            <span class="d">
              {{ $s->prod_api_url ?: '주소 없음' }}<br>
              콜로 거래처 {{ $s->prod_account_id ?: '—' }} ·
              <b>여기로 두면 실제 물류로 넘어갑니다</b>
            </span>
          </span>
        </label>
      </div>
    </div>
  </div>

  <div class="ws-card">
    <div class="ws-hd">테스트 (데모웍스)</div>
    <div class="ws-bd">
      <div class="ws-row">
        <label>주소</label>
        <input type="url" name="test_api_url" class="form-control" maxlength="190"
               value="{{ old('test_api_url', $s->test_api_url) }}" placeholder="https://www.demoworks.co.kr">
      </div>
      <div class="ws-row">
        <label>콜로 거래처</label>
        <input type="text" name="test_account_id" class="form-control" maxlength="30" style="max-width:180px;"
               value="{{ old('test_account_id', $s->test_account_id) }}" placeholder="136155">
      </div>
      <div class="ws-row" style="align-items:flex-start;">
        <label style="padding-top:7px;">API 토큰</label>
        <div style="flex:1;">
          <textarea name="test_api_token" class="form-control" rows="2"
                    placeholder="{{ $s->test_api_token ? '저장돼 있습니다 — 바꿀 때만 입력하십시오' : '토큰을 붙여 넣으십시오' }}"></textarea>
          {{-- 토큰은 화면에 다시 내려보내지 않는다. 비워 두면 지금 값을 그대로 둔다. --}}
          <div class="ws-hint">보안상 저장된 값은 보여 주지 않습니다. 비워 두면 바뀌지 않습니다.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ws-card">
    <div class="ws-hd">운영 (위드웍스)</div>
    <div class="ws-bd">
      <div class="ws-row">
        <label>주소</label>
        <input type="url" name="prod_api_url" class="form-control" maxlength="190"
               value="{{ old('prod_api_url', $s->prod_api_url) }}" placeholder="https://www.withworks.co.kr">
      </div>
      <div class="ws-row">
        <label>콜로 거래처</label>
        <input type="text" name="prod_account_id" class="form-control" maxlength="30" style="max-width:180px;"
               value="{{ old('prod_account_id', $s->prod_account_id) }}" placeholder="148659">
      </div>
      <div class="ws-row" style="align-items:flex-start;">
        <label style="padding-top:7px;">API 토큰</label>
        <div style="flex:1;">
          <textarea name="prod_api_token" class="form-control" rows="2"
                    placeholder="{{ $s->prod_api_token ? '저장돼 있습니다 — 바꿀 때만 입력하십시오' : '토큰을 붙여 넣으십시오' }}"></textarea>
          <div class="ws-hint">보안상 저장된 값은 보여 주지 않습니다. 비워 두면 바뀌지 않습니다.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ws-card">
    <div class="ws-hd">콜백 수신</div>
    <div class="ws-bd">
      <div class="ws-row">
        <label>수신 주소</label>
        <input type="url" name="webhook_url" class="form-control" maxlength="190"
               value="{{ old('webhook_url', $s->webhook_url) }}">
      </div>
      <div class="ws-row">
        <label>공유 비밀</label>
        <input type="text" name="webhook_secret" class="form-control" maxlength="190"
               value="{{ old('webhook_secret', $s->webhook_secret) }}">
      </div>
      {{-- 콜백은 위드웍스가 우리를 부르는 것이라 환경과 상관없이 주소 하나다 --}}
      <div class="ws-hint">
        위드웍스 쪽 <code>CEADMIN_WEBHOOK_URL</code>·<code>CEADMIN_WEBHOOK_SECRET</code>에 같은 값이 들어가야 합니다.
        비밀이 비어 있으면 들어오는 콜백을 전부 거절합니다.
      </div>
    </div>
  </div>

  {{-- 남는 높이를 받는 카드 — 사슬의 마지막이다 --}}
  <div class="ws-card fill-rest">
    <div class="ws-hd">판매유형</div>
    <div class="ws-bd">
      {{-- 고르는 칸이 아니라 적는 칸이다. 코드는 위드웍스 code_list 가 정하고 그쪽에서
           바뀐다 — 우리가 목록으로 박아 두면 코드가 바뀔 때마다 손을 대야 하고,
           그 사이에는 없는 코드로 주문이 나간다. --}}
      <div class="ws-row">
        <label>연동 유형</label>
        <input type="text" name="so_type" class="form-control" style="max-width:260px;"
               maxlength="20" inputmode="numeric" placeholder="예: 1501"
               value="{{ old('so_type', $s->so_type) }}">
      </div>
      <div class="ws-hint" style="margin-bottom:12px;">
        위드웍스로 넘기는 주문은 모두 이 유형으로 나갑니다. 다른 유형으로 넘기면 저쪽 콜백
        대상에서 빠져 진행 상태를 받지 못합니다.
      </div>

      {{-- 되돌리는 것은 판매와 코드가 다르고, 셋끼리도 다르다.
           창고가 하는 일이 달라서다 — 반품은 넣고, 교환은 넣었다 내보내고, 취소는 안 움직인다.
           한 칸으로 묶으면 창고 담당자가 비고를 읽어야 무엇을 할지 알 수 있다. --}}
      @foreach([
        ['cancel_so_type',   '취소', '출고 뒤 취소일 때만 씁니다. 출고 전이면 원 판매주문을 취소합니다.', '위드웍스에 코드가 생기면 적습니다'],
        ['return_so_type',   '반품', '수거해서 창고에 넣습니다.', '예: 1505'],
        ['exchange_so_type', '교환', '수거해 넣고 다시 내보냅니다.', '위드웍스에 코드가 생기면 적습니다'],
        ['adjust_so_type',   '금액조정', '일반 환불(자격 변경 등)에 씁니다 — 물건은 움직이지 않고 금액만 마이너스로 맞춥니다.', '예: 1092'],
      ] as [$field, $label, $hint, $ph])
        <div class="ws-row">
          <label>{{ $label }}</label>
          <input type="text" name="{{ $field }}" class="form-control" style="max-width:260px;"
                 maxlength="20" inputmode="numeric" placeholder="{{ $ph }}"
                 value="{{ old($field, $s->$field) }}">
        </div>
        <div class="ws-hint" style="margin-bottom:12px;">{{ $hint }}</div>
      @endforeach
      <div class="ws-hint">
        위드웍스 code_list 가 정하는 값입니다. 그쪽에서 코드가 바뀌면 여기서 맞춰 주십시오.
        비워 두면 그 종류는 창고로 넘기지 않습니다 — 코드가 없는데 보내면 저쪽이 거절합니다.
      </div>
    </div>
  </div>

  <div class="ws-actions">
    {{-- 연결 확인은 저장과 라우트가 달라 제 폼으로 나간다. 폼은 겹칠 수 없으므로
         단추만 여기 세우고 form= 으로 아래 빈 폼을 가리킨다.
         data-no-loading — 전역 잠금이 저장 대신 이 단추를 붙잡지 않게 한다. --}}
    <button type="submit" form="wwTestForm" data-no-loading class="ds-btn"><i class="bx bx-plug"></i> 연결 확인</button>
    <button type="submit" class="ds-btn ds-btn-primary">저장</button>
  </div>
</form>

{{-- 연결 확인 폼 — 나르는 것은 _token 하나다 --}}
<form id="wwTestForm" method="POST" action="{{ route('withworks-settings.test') }}">
  @csrf
</form>
</div>{{-- /.ws-shell --}}
