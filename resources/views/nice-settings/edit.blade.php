@extends('layouts.app')

@section('title', '본인확인 설정')
@section('page-title', 'NICE 본인확인 설정')
@section('breadcrumb', '홈 - 설정 - 본인확인 설정')

@push('styles')
<style>
  /* 카드는 본문 폭을 그대로 쓴다 — 1920 에서 336..1904(1568).
     여덟 화면을 재 보니 일곱(documents · masters · common-codes · delegation ·
     withworks · services · permission-groups)이 모두 336..1904 인데 이 화면만
     336..1196(860)이라 흰 판이 708 짧게 끊겨 「다른 페이지랑 다르다」로 보였다.
     설정 폼의 여러 칸도 이미 넓게 선다(delegation 2열 762+762 · services 501/329/329). */
  .ns-form { max-width:100%; }
  /* 시안 카드는 흰 채움에 테두리·그림자가 없고 안여백이 12/16 이다(148:6653 · 156:7261 · 382:383).
     전역 .card 와 목록 카드(.ds-grid-card)는 이미 그런데 이 화면만 1px·20 이라 같은 부품이 달라 보였다.
     카드 사이 간격도 .page-body 의 세로 gap 과 같은 12 로 맞춘다. */
  .ns-card { background:var(--gray-0); border:none; border-radius:var(--radius-lg); padding:12px 16px; margin-bottom:12px; }
  /* 내용이 짧을 때 남는 높이는 마지막 카드가 받는다 — 그 카드는 본문 바닥에 닿으므로 아래 여백을 지운다 */
  .ns-card.fill-rest { margin-bottom:0; }
  /* 섹션 제목 = 14px/700 (시안 '환자 추가 · 파일 업로드' 계열).
     전역 .card-header 와 같은 규격으로 — pad 12/16 이고 아래 1px 이 카드 폭 끝까지 간다.
     음수 여백은 카드 안여백(12/16)을 되돌려 제목줄만 가장자리까지 펴는 값이다. */
  .ns-card h3 { margin:-12px -16px 12px; padding:12px 16px; font-size:14px; font-weight:700; line-height:22px;
    color:var(--primary); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .ns-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  @media (max-width:720px) { .ns-grid { grid-template-columns:1fr; } }
  .ns-field { display:flex; flex-direction:column; gap:4px; }
  .ns-field.full { grid-column:1 / -1; }
  .ns-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  /* 입력 h32 = pad 5 + lh 20 + pad 5 + 테두리 2 */
  .ns-field input[type=text], .ns-field input[type=password], .ns-field input[type=url] {
    padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit; }
  .ns-field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  /* 안내문 규격 = 12/500 · lh19 · gray-600 (시안 전수). --text-muted 는 gray-400 이라
     흰 바탕 대비 2.70:1 로 읽기 어렵다 — 전역이 이미 gray-600(5.32:1)으로 옮겨 온 자리다. */
  .ns-hint { font-size:12px; font-weight:500; color:var(--gray-600); line-height:19px; }
  /* 안내문은 상자를 두르지 않는다 — 12/500 · gray-600 · 앞에 12 아이콘 · 사이 4.
     전역 .ds-grid-hint 를 그대로 붙이지는 않는다 — 그쪽은 결과바 한 줄용이라 nowrap·ellipsis 다.
     이 문장은 글자폭이 827 이라 좁아지는 순간 잘려 낱말이 사라진다. 상자만 걷고 규격을 옮긴다.
     아이콘은 개발자가 고른 info-circle 을 둔다(전역도 마크업에 아이콘이 있으면 제 ::before 를 접는다). */
  .ns-note { background:none; border:0; border-radius:0; padding:0;
    font-size:12px; font-weight:500; color:var(--gray-600); margin-bottom:12px; line-height:19px; }
  /* 글줄 높이 19 상자를 글줄 꼭대기에 맞추면 12 아이콘이 그 안에서 정확히 가운데 선다 */
  .ns-note > i { font-size:12px; line-height:19px; vertical-align:top; margin-right:4px; }
  /* 주의·오류는 alert 램프 한 가지로만 표현한다(시안에 주황·초록이 없다).
     주의는 연톤(50/100), 오류는 진톤(100/500)으로 세기를 나눈다. */
  .ns-warn { background:var(--alert-50); border:1px solid var(--alert-100); color:var(--alert-500); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; margin-bottom:12px; line-height:18px; }
  .status-ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; margin-bottom:12px; }
  .status-err { background:var(--alert-100); border:1px solid var(--alert-500); color:var(--alert-500); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; margin-bottom:12px; }
  /* 전역 .badge(h22 · radius 6 · 11px/500)를 그대로 쓴다 — 재정의하면 이 화면만 알약 모양으로 남는다 */
  .badge-on  { background:var(--primary-light); color:var(--primary); }
  .badge-off { background:var(--alert-50);      color:var(--alert-500); }
  .ns-check { display:flex; gap:8px; align-items:flex-start; border:1px solid var(--border);
    border-radius:8px; padding:12px 16px; margin-bottom:8px; cursor:pointer; }
  .ns-check input { margin-top:2px; width:16px; height:16px; flex-shrink:0; }
  .ns-check .t { font-size:13px; font-weight:700; line-height:21px; color:var(--text-primary); }
  .ns-check .d { font-size:12px; font-weight:400; color:var(--text-secondary); margin-top:4px; line-height:18px; }
  /* 단추는 줄 오른쪽 끝에 모인다 — 전역 .ds-filter-actions(margin-left:auto)·.ds-grid-bar-right 와 같다.
     설명글을 같은 줄 왼쪽에 두어 남는 자리를 먹게 하면 단추가 오른쪽 끝으로 밀린다. */
  .ns-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
  /* 설명글은 flex 아이템일 뿐 안쪽은 보통 글줄이다 — 낱말 사이 공백이 살아 있다 */
  .ns-actions-note { flex:1 1 420px; margin-right:auto; }
  /* 전역 .btn 규격(h32 · pad 5/12 · r8 · 13px/500 · 아이콘↔글자 8)에 맞춘다 */
  .btn-save { display:inline-flex; align-items:center; gap:8px;
    background:var(--primary); color:var(--gray-0); border:1px solid var(--primary); border-radius:8px;
    padding:5px 12px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; font-family:inherit; }
  .btn-test { display:inline-flex; align-items:center; gap:8px;
    background:var(--gray-0); color:var(--primary); border:1px solid var(--primary); border-radius:8px;
    padding:5px 12px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; font-family:inherit; }
  .btn-test:disabled { opacity:.6; cursor:default; }
  #testResult { font-size:12px; font-weight:500; line-height:19px; }
  #testResult.ok  { color:var(--primary); }
  #testResult.err { color:var(--alert-500); }
</style>
@endpush

@section('content')
<div class="ns-form fill-rest fill-col">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="status-err">입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}</div>
  @endif

  {{-- 앞 아이콘은 전역 .ds-grid-hint::before 가 그린다 — 나머지 설정 화면과 같은 부품 --}}
  <div class="ds-grid-hint ns-note">
    위임동의 서명 페이지에서 <b>서명 전 휴대폰 본인확인(NICE)</b>에 사용할 자격증명입니다.
    여기 저장한 값이 서버 <code>.env</code> 설정보다 <b>우선 적용</b>되며, 비밀키는 DB에 암호화되어 저장됩니다.
  </div>

  @if(! $configured)
    <div class="ns-warn">
      <i class="bx bx-error-circle"></i> <b>자격증명이 아직 완성되지 않았습니다.</b>
      client_id 와 client_secret 이 모두 채워질 때까지 본인확인은 <b>비활성</b> 상태이며,
      서명 페이지는 기존 흐름(본인확인 없이 서명)대로 동작합니다.
    </div>
  @endif

  <form method="POST" action="{{ route('nice-settings.update') }}" class="fill-rest fill-col">
    @csrf
    @method('PUT')

    <div class="ns-card">
      <h3>
        <i class="bx bx-key"></i> 자격증명
        <span class="badge {{ $configured ? 'badge-on' : 'badge-off' }}">{{ $configured ? '활성' : '미설정' }}</span>
        @if($setting->tested_at)
          <span class="ns-hint" style="margin-left:auto;font-weight:500;">
            마지막 연결 테스트 성공: {{ $setting->tested_at->format('Y-m-d H:i') }}
          </span>
        @endif
      </h3>

      <div class="ns-grid">
        <div class="ns-field">
          <label>client_id</label>
          <input type="text" name="client_id" autocomplete="off"
                 value="{{ old('client_id', $setting->client_id) }}" placeholder="NICE 발급 client_id">
        </div>
        {{-- 통합인증은 client_id·client_secret 둘로 부른다. productID 는 예전
             CheckPlus 표준창이 쓰던 값이라 이제 쓰이지 않는다 — 지우지는 않는다
             (되돌릴 일이 있을 때 다시 적기 번거롭다). --}}
        <div class="ns-field">
          <label>productID <span class="ns-hint" style="font-weight:500;">(옛 CheckPlus 값 · 지금은 쓰지 않음)</span></label>
          <input type="text" name="product_id" autocomplete="off"
                 value="{{ old('product_id', $setting->product_id) }}" placeholder="이용상품 ID (본인확인)">
        </div>
        <div class="ns-field full">
          <label>client_secret</label>
          <input type="password" name="client_secret" autocomplete="new-password"
                 placeholder="{{ $hasSecret ? '저장됨 — 변경할 때만 입력하세요' : 'NICE 발급 client_secret' }}">
          <span class="ns-hint">
            보안상 저장된 값은 화면에 표시하지 않습니다. 비워 두고 저장하면 <b>기존 비밀키가 그대로 유지</b>됩니다.
          </span>
        </div>
      </div>
    </div>

    <div class="ns-card">
      <h3><i class="bx bx-shield-quarter"></i> 적용 정책</h3>

      <label class="ns-check">
        <input type="checkbox" name="enforce" value="1" {{ old('enforce', $setting->enforce) ? 'checked' : '' }}>
        <div>
          <div class="t">서명 전 본인확인 강제</div>
          <div class="d">
            켜면 본인확인을 완료해야 동의 서명을 제출할 수 있습니다. 끄면 본인확인 버튼은 보이되 선택 사항이 됩니다.
            (자격증명이 미설정이면 이 설정과 무관하게 강제되지 않습니다.)
          </div>
        </div>
      </label>

      <label class="ns-check">
        <input type="checkbox" name="match_name" value="1" {{ old('match_name', $setting->match_name) ? 'checked' : '' }}>
        <div>
          <div class="t">이름 일치 필수</div>
          <div class="d">본인확인 결과의 성명이 처방전 이름과 다르면 본인확인을 거부합니다.</div>
        </div>
      </label>

      <label class="ns-check">
        <input type="checkbox" name="match_birth" value="1" {{ old('match_birth', $setting->match_birth) ? 'checked' : '' }}>
        <div>
          <div class="t">생년월일 일치 필수</div>
          <div class="d">
            환자 생년월일(없으면 처방전 OCR 주민번호 앞자리)과 본인확인 생년월일이 다르면 거부합니다.
            환자 생년월일 정보가 없으면 이 검사는 건너뜁니다.
          </div>
        </div>
      </label>
    </div>

    <div class="ns-card">
      <h3><i class="bx bx-link-alt"></i> 엔드포인트 <span class="ns-hint" style="font-weight:500;">(비워 두면 기본값 사용)</span></h3>
      <div class="ns-grid">
        <div class="ns-field">
          <label>API 서버</label>
          <input type="url" name="api_base" value="{{ old('api_base', $setting->api_base) }}"
                 placeholder="{{ $defaultApiBase }}">
          <span class="ns-hint">통합인증 서버 — 접근토큰ㆍ인증주소ㆍ인증결과 ({{ $defaultApiBase }})</span>
        </div>
        {{-- 표준창 주소는 이제 우리가 들고 있지 않다. 인증 주소 요청 API 가 건마다
             만들어 준다 — 적어 두어도 쓰이지 않으므로 칸을 두지 않는다. --}}
      </div>
    </div>

    <div class="ns-card fill-rest">
      <div class="ns-actions">
        {{-- 설명글은 단추와 같은 줄 왼쪽에 둔다 — 남는 자리를 먹어 단추를 오른쪽 끝으로 민다 --}}
        <div class="ns-hint ns-actions-note">
          연결 테스트는 <b>저장된</b> 자격증명으로 기관토큰·암호화토큰 발급까지만 확인합니다.
          표준창을 열지 않으므로 본인확인 건당 요금은 발생하지 않습니다. 값을 바꿨다면 먼저 저장하세요.
        </div>
        <span id="testResult"></span>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-save"></i> 저장</button>
        <button type="button" class="btn-test" id="btnTest" onclick="runNiceTest()">
          <i class="bx bx-plug"></i> 연결 테스트
        </button>
      </div>
    </div>
  </form>
</div>

<script>
const NICE_TEST_URL = '{{ route('nice-settings.test') }}';

async function runNiceTest() {
  const btn = document.getElementById('btnTest');
  const out = document.getElementById('testResult');
  btn.disabled = true;
  out.className = '';
  out.textContent = '테스트 중...';

  try {
    const res = await fetch(NICE_TEST_URL, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept': 'application/json',
      },
    });
    const d = await res.json();
    out.className = d.ok ? 'ok' : 'err';
    out.textContent = (d.ok ? '✅ ' : '⚠️ ') + (d.message || '') + (d.detail ? ' (' + d.detail + ')' : '');
  } catch (e) {
    out.className = 'err';
    out.textContent = '⚠️ 테스트 요청 중 오류가 발생했습니다.';
  } finally {
    btn.disabled = false;
  }
}
</script>
@endsection
