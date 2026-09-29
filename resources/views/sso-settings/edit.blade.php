{{-- SSO 설정 — 시스템 설정 › SSO 설정 (지시서 LTL-UNICORN-20260909-02 §3.2).

     값은 .env 가 아니라 settings 표에 담는다. Client Secret 은 Setting 이 암호화해
     담고, 이 화면에는 **뒤 넉 자만** 보인다 — 원문은 내려보내지 않는다.

     모양은 본인확인 설정(nice-settings/edit)과 같은 규격을 쓴다. 설정 화면이
     저마다 다르게 보이면 담당자가 화면마다 다시 익혀야 한다. --}}
@extends('layouts.app')

@section('title', 'SSO 설정')
@section('page-title', 'SSO 설정')
@section('breadcrumb', '홈 - 설정 - SSO 설정')

@push('styles')
<style>
  /* 시험ㆍ운영 탭 (2026-09-29) */
  .ss-envnow  { margin-left:auto; font-size:12px; font-weight:400; color:var(--text-muted); }
  .ss-envnow b { color:var(--primary); font-weight:700; }
  .ss-envtabs { display:flex; gap:6px; margin:4px 0 14px; }
  .ss-envtab  { display:inline-flex; align-items:center; gap:7px; padding:7px 14px;
                font-size:13px; font-weight:600; color:var(--text-secondary);
                background:var(--gray-50); border:1px solid var(--gray-200);
                border-radius:8px; cursor:pointer; }
  .ss-envtab:hover { border-color:var(--primary); color:var(--primary); }
  .ss-envtab.on    { background:var(--primary); border-color:var(--primary); color:#fff; }
  .ss-envdot  { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
  .ss-envdot.ok { background:#22a06b; }
  .ss-envdot.no { background:var(--gray-400); }
  .ss-envtab.on .ss-envdot.no { background:rgba(255,255,255,.55); }

  /* 어느 벌로 로그인할까 */
  .ss-envpick { display:flex; gap:10px; flex-wrap:wrap; }
  .ss-envopt  { flex:1 1 280px; display:flex; gap:10px; align-items:flex-start;
                padding:12px 14px; border:1px solid var(--gray-200); border-radius:10px;
                cursor:pointer; background:var(--gray-0); }
  .ss-envopt.on { border-color:var(--primary); box-shadow:0 0 0 2px rgba(40,121,139,.12); }
  .ss-envopt input { margin-top:3px; flex-shrink:0; }
  .ss-envopt .t { font-size:13px; font-weight:700; color:var(--text-primary); }
  .ss-envopt .d { font-size:12px; color:var(--text-muted); margin-top:3px; line-height:1.6; }
  .ss-card { background:var(--gray-0); border:none; border-radius:var(--radius-lg);
             padding:12px 16px; margin-bottom:12px; }
  .ss-card.fill-rest { margin-bottom:0; }
  .ss-card h3 { margin:-12px -16px 12px; padding:12px 16px; font-size:14px; font-weight:700;
                line-height:22px; color:var(--primary); border-bottom:1px solid var(--border);
                display:flex; align-items:center; gap:8px; }
  .ss-grid  { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  @media (max-width:720px) { .ss-grid { grid-template-columns:1fr; } }
  .ss-field { display:flex; flex-direction:column; gap:4px; }
  .ss-field.full { grid-column:1 / -1; }
  .ss-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  .ss-field input[type=text], .ss-field input[type=password], .ss-field input[type=url] {
    padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit; }
  .ss-field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  .ss-hint { font-size:12px; font-weight:500; color:var(--gray-600); line-height:19px; }
  .ss-note { font-size:12px; font-weight:500; color:var(--gray-600); margin-bottom:12px; line-height:19px; }
  .ss-note > i { font-size:12px; line-height:19px; vertical-align:top; margin-right:4px; }
  .ss-warn { background:var(--alert-50); border:1px solid var(--alert-100); color:var(--alert-500);
             border-radius:8px; padding:12px 16px; font-size:12px; font-weight:400;
             margin-bottom:12px; line-height:18px; }
  .badge-on  { background:var(--primary-light); color:var(--primary); }
  .badge-off { background:var(--alert-50);      color:var(--alert-500); }
  .ss-check { display:flex; gap:8px; align-items:flex-start; border:1px solid var(--border);
              border-radius:8px; padding:12px 16px; cursor:pointer; }
  .ss-check input { margin-top:2px; width:16px; height:16px; flex-shrink:0; }
  .ss-check .t { font-size:13px; font-weight:700; line-height:21px; color:var(--text-primary); }
  .ss-check .d { font-size:12px; font-weight:400; color:var(--text-secondary); margin-top:4px; line-height:18px; }
  .ss-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
  .ss-actions-note { flex:1 1 420px; margin-right:auto; }
  /* 우리가 만들어 내는 주소 — HQ 에 등록해야 하는 값이라 그대로 집어 갈 수 있게 둔다 */
  .ss-url { font-family:'Consolas','Menlo',monospace; font-size:12px; color:var(--gray-800);
            background:var(--gray-50); border:1px solid var(--gray-200); border-radius:6px;
            padding:6px 10px; word-break:break-all; }
  #ssoTestOut.ok  { color:var(--primary);     font-size:12px; font-weight:600; }
  #ssoTestOut.err { color:var(--alert-500);   font-size:12px; font-weight:600; }
</style>
@endpush

@section('content')
<div class="ss-form">

  @if(session('success'))
    <div class="alert alert-success" style="margin-bottom:12px;">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="ss-warn"><i class="bx bx-error-circle"></i> {{ $errors->first() }}</div>
  @endif

  @unless($usable)
    <div class="ss-warn">
      <i class="bx bx-error-circle"></i> <b>SSO 가 아직 켜지지 않았습니다.</b>
      켜지 않은 동안 <code>/auth/entra/*</code> 는 열리지 않고, 로그인은 지금까지처럼
      아이디ㆍ비밀번호로만 합니다. 기존 로그인은 이 설정과 무관하게 그대로 동작합니다.
    </div>
  @endunless

  <form method="POST" action="{{ route('sso-settings.update') }}" class="fill-rest fill-col">
    @csrf
    @method('PUT')

    {{-- 시험ㆍ운영 두 벌 (2026-09-29 지시).

         한 벌만 두면 운영으로 넘길 때 시험 값을 지워야 하고, 되돌릴 일이 생기면
         그것을 다시 받아 넣어야 한다 — 그 사이에는 아무도 들어오지 못한다.
         팝빌ㆍ토스ㆍ위드웍스가 쓰는 것과 같은 짜임이다.

         한 번에 **한 벌만** 저장한다. 두 벌을 한 폼에 담으면 어느 쪽 Secret 을
         보낸 것인지 서버가 가릴 수 없다 — 탭마다 제 환경을 실어 보낸다. --}}
    <div class="ss-card">
      <h3>
        <i class="bx bx-key"></i> Entra 자격증명
        <span class="badge {{ $usable ? 'badge-on' : 'badge-off' }}">{{ $usable ? '사용 중' : '미사용' }}</span>
        <span class="ss-envnow">지금 쓰는 것 — <b>{{ $환경들[$고른환경] ?? $고른환경 }}</b></span>
      </h3>

      <div class="ss-envtabs">
        @foreach($환경들 as $env => $말)
          <button type="button" class="ss-envtab {{ $env === $고른환경 ? 'on' : '' }}"
                  data-env="{{ $env }}" onclick="ssoEnvTab('{{ $env }}')">
            {{ $말 }} 설정
            <span class="ss-envdot {{ $값[$env]['채워짐'] ? 'ok' : 'no' }}"
                  title="{{ $값[$env]['채워짐'] ? '네 칸이 모두 채워졌습니다' : '아직 덜 채워졌습니다' }}"></span>
          </button>
        @endforeach
      </div>

      {{-- 어느 벌을 고치는지 — 눌린 탭이 정한다 --}}
      <input type="hidden" name="env" id="ssoEnvField" value="{{ old('env', $고른환경) }}">

      @foreach($환경들 as $env => $말)
        <div class="ss-envpane" data-env="{{ $env }}"
             @if($env !== old('env', $고른환경)) style="display:none" @endif>
          <div class="ss-grid">
            <div class="ss-field">
              <label>Tenant ID</label>
              <input type="text" name="tenant_id__{{ $env }}" autocomplete="off"
                     value="{{ old('env') === $env ? old('tenant_id') : $값[$env]['tenant_id'] }}"
                     placeholder="Coloplast HQ 테넌트 ID (GUID)">
              <span class="ss-hint">HQ 가 App Registration 을 만든 뒤 알려 줍니다.</span>
            </div>
            <div class="ss-field">
              <label>Client ID</label>
              <input type="text" name="client_id__{{ $env }}" autocomplete="off"
                     value="{{ old('env') === $env ? old('client_id') : $값[$env]['client_id'] }}"
                     placeholder="CE Admin 전용 Application (client) ID">
              <span class="ss-hint">SR App 과 다른 값입니다 — 앱마다 따로 등록합니다.</span>
            </div>
            <div class="ss-field full">
              <label>Client Secret</label>
              <input type="password" name="client_secret__{{ $env }}" autocomplete="new-password"
                     placeholder="{{ $값[$env]['secretMasked']
                          ? '저장됨 ' . $값[$env]['secretMasked'] . ' — 바꿀 때만 입력하십시오'
                          : 'HQ 가 발급한 Client Secret' }}">
              <span class="ss-hint">
                암호화해 담으므로 원문은 화면에 보이지 않습니다. 비워 두고 저장하면
                <b>기존 값이 그대로 유지</b>됩니다.
              </span>
            </div>
            <div class="ss-field full">
              <label>Redirect URI</label>
              <input type="url" name="redirect_uri__{{ $env }}" autocomplete="off"
                     value="{{ old('env') === $env ? old('redirect_uri') : ($값[$env]['redirect_uri'] ?: ($env === 'test' ? $suggestRedirect : '')) }}"
                     placeholder="{{ $suggestRedirect }}">
              <span class="ss-hint">
                HQ 의 App Registration 에 등록한 것과 <b>한 글자도 다르면 안 됩니다.</b>
                운영은 운영 서버의 주소라 이 화면의 주소와 다릅니다.
              </span>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- 어느 벌로 로그인할까 — 켜고 끄는 것과 다른 물음이라 따로 둔다 --}}
    <div class="ss-card">
      <h3><i class="bx bx-transfer"></i> 사용 환경</h3>
      <div class="ss-envpick">
        @foreach($환경들 as $env => $말)
          <label class="ss-envopt {{ old('use_env', $고른환경) === $env ? 'on' : '' }}">
            <input type="radio" name="use_env" value="{{ $env }}"
                   {{ old('use_env', $고른환경) === $env ? 'checked' : '' }}>
            <div>
              <div class="t">{{ $말 }} 설정 사용</div>
              <div class="d">
                @if($env === 'test')
                  시험용 App Registration 으로 로그인합니다. 운영 자격은 손대지 않습니다.
                @else
                  운영 App Registration 으로 로그인합니다.
                  <b>실제 임직원 계정이 이 자격으로 들어옵니다.</b>
                @endif
              </div>
            </div>
          </label>
        @endforeach
      </div>
    </div>

    <div class="ss-card">
      <h3><i class="bx bx-toggle-left"></i> 사용 여부</h3>

      <label class="ss-check">
        <input type="checkbox" name="enabled" value="1" {{ old('enabled', $enabled) ? 'checked' : '' }}>
        <div>
          <div class="t">Entra SSO 로그인 사용</div>
          <div class="d">
            켜면 로그인 화면에 「Microsoft 계정으로 로그인」이 열립니다.
            <b>기존 아이디ㆍ비밀번호 로그인은 그대로</b> 둡니다 — 어느 쪽으로도 들어올 수 있습니다.
            Tenant IDㆍClient IDㆍClient SecretㆍRedirect URI 가 모두 채워져야 켤 수 있습니다.
          </div>
        </div>
      </label>
    </div>

    <div class="ss-card">
      <h3><i class="bx bx-link-alt"></i> HQ 에 등록할 주소</h3>
      <div class="ss-grid">
        <div class="ss-field full">
          <label>Redirect URI</label>
          <div class="ss-url">{{ $suggestRedirect }}</div>
        </div>
        <div class="ss-field full">
          <label>Front-channel logout URL</label>
          <div class="ss-url">{{ $suggestLogout }}</div>
        </div>
      </div>
      <div class="ss-hint" style="margin-top:8px;">
        <b>지금 열고 있는 주소를 기준으로 만든 값입니다.</b>
        이 서버는 <code>ceadmin.co.kr</code> 과 <code>www.ceadmin.co.kr</code> 을 둘 다 받지만,
        OIDC 의 Redirect URI 는 <b>문자열이 똑같아야</b> 합니다 — <code>www</code> 하나만 달라도
        Entra 가 거부합니다. <b>이 서버의 정본은 <code>www</code></b> 이므로
        <code>https://www.ceadmin.co.kr</code> 로 등록합니다(2026-09-09 확정).
        <b><code>www</code> 를 붙여 들어와 이 값을 집어 가십시오.</b>
        운영 도메인은 따로 정해진 뒤에 다시 등록합니다.
      </div>
    </div>

    <div class="ss-card fill-rest">
      <div class="ss-actions">
        <div class="ss-hint ss-actions-note">
          연동 테스트는 <b>지금 보고 있는 탭에 저장된 Tenant ID</b> 로 Microsoft 의 OIDC
          설정 문서를 읽어 그 테넌트가 있는지만 봅니다. Client Secret 이 맞는지는 실제로 로그인해 봐야 압니다.
          값을 변경했다면 먼저 저장하십시오.
        </div>
        <span id="ssoTestOut"></span>
        <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-save"></i> 저장</button>
        <button type="button" class="ds-btn" id="btnSsoTest" onclick="runSsoTest()">
          <i class="bx bx-plug"></i> 연동 테스트
        </button>
      </div>
    </div>
  </form>
</div>

<script>
const SSO_TEST_URL = @json(route('sso-settings.test'));

/* 탭 바꾸기 — 보이는 벌과 「고친 환경」을 함께 옮긴다.
   숨긴 벌의 값도 함께 실려 가지만 서버는 고친 환경의 것만 읽는다. */
function ssoEnvTab(env) {
  document.getElementById('ssoEnvField').value = env;
  document.querySelectorAll('.ss-envtab').forEach(b => b.classList.toggle('on', b.dataset.env === env));
  document.querySelectorAll('.ss-envpane').forEach(p => { p.style.display = (p.dataset.env === env) ? '' : 'none'; });
  const o = document.getElementById('ssoTestOut');
  if (o) { o.textContent = ''; o.className = ''; }
}

/* 「사용 환경」을 고르면 테두리도 따라 움직인다 — 무엇을 골랐는지 눈으로 보여야 한다 */
document.querySelectorAll('.ss-envopt input[name="use_env"]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.ss-envopt').forEach(l =>
      l.classList.toggle('on', l.querySelector('input').checked));
  });
});

async function runSsoTest() {
  const btn = document.getElementById('btnSsoTest');
  const out = document.getElementById('ssoTestOut');
  btn.disabled = true;
  out.className = '';
  out.textContent = '확인 중…';

  try {
    /* 지금 보고 있는 탭의 자격으로 시험한다 — 고른 환경이 아니라 보는 환경이다.
       운영 값을 넣어 두고 아직 넘기지 않은 동안에도 그것이 맞는지 봐야 한다. */
    const env = document.getElementById('ssoEnvField').value;

    const res = await fetch(SSO_TEST_URL + '?env=' + encodeURIComponent(env), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        'Accept': 'application/json',
      },
    });
    const d = await res.json();
    out.className = d.success ? 'ok' : 'err';
    out.textContent = (d.success ? '✅ ' : '⚠️ ') + (d.message || '');
    if (d.success && d.issuer) out.title = 'issuer: ' + d.issuer;
  } catch (e) {
    out.className = 'err';
    out.textContent = '⚠️ 확인 요청 중 오류가 발생했습니다.';
  } finally {
    btn.disabled = false;
  }
}
</script>
@endsection
