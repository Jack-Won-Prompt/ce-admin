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

  <div class="ss-note">
    <i class="bx bx-info-circle"></i>
    Microsoft Entra ID(OIDC)로 CE Admin 에 로그인하는 설정입니다.
    <b>여기 담긴 값은 .env 나 코드에 남지 않습니다</b> — Client Secret 은 암호화해 저장하고
    화면에는 뒤 넉 자만 보입니다.
  </div>

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

    <div class="ss-card">
      <h3>
        <i class="bx bx-key"></i> Entra 자격증명
        <span class="badge {{ $usable ? 'badge-on' : 'badge-off' }}">{{ $usable ? '사용 중' : '미사용' }}</span>
      </h3>

      <div class="ss-grid">
        <div class="ss-field">
          <label>Tenant ID</label>
          <input type="text" name="tenant_id" autocomplete="off"
                 value="{{ old('tenant_id', $tenantId) }}"
                 placeholder="Coloplast HQ 테넌트 ID (GUID)">
          <span class="ss-hint">HQ 가 App Registration 을 만든 뒤 알려 줍니다.</span>
        </div>
        <div class="ss-field">
          <label>Client ID</label>
          <input type="text" name="client_id" autocomplete="off"
                 value="{{ old('client_id', $clientId) }}"
                 placeholder="CE Admin 전용 Application (client) ID">
          <span class="ss-hint">SR App 과 다른 값입니다 — 앱마다 따로 등록합니다.</span>
        </div>
        <div class="ss-field full">
          <label>Client Secret</label>
          <input type="password" name="client_secret" autocomplete="new-password"
                 placeholder="{{ $secretMasked ? '저장됨 ' . $secretMasked . ' — 바꿀 때만 입력하십시오' : 'HQ 가 발급한 Client Secret' }}">
          <span class="ss-hint">
            암호화해 담으므로 원문은 화면에 보이지 않습니다. 비워 두고 저장하면
            <b>담긴 값이 그대로 유지</b>됩니다.
          </span>
        </div>
        <div class="ss-field full">
          <label>Redirect URI</label>
          <input type="url" name="redirect_uri" autocomplete="off"
                 value="{{ old('redirect_uri', $redirectUri ?: $suggestRedirect) }}"
                 placeholder="{{ $suggestRedirect }}">
          <span class="ss-hint">
            HQ 의 App Registration 에 등록한 것과 <b>한 글자도 다르면 안 됩니다.</b>
          </span>
        </div>
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
        Entra 가 거부합니다. 개발서버는 <code>https://ceadmin.co.kr</code> 로 등록하기로
        했습니다(2026-09-09). 운영 도메인은 따로 정해진 뒤에 다시 등록합니다.
      </div>
    </div>

    <div class="ss-card fill-rest">
      <div class="ss-actions">
        <div class="ss-hint ss-actions-note">
          연동 테스트는 <b>저장된 Tenant ID</b> 로 Microsoft 의 OIDC 설정 문서를 읽어
          그 테넌트가 있는지만 봅니다. Client Secret 이 맞는지는 실제로 로그인해 봐야 압니다.
          값을 바꿨다면 먼저 저장하십시오.
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

async function runSsoTest() {
  const btn = document.getElementById('btnSsoTest');
  const out = document.getElementById('ssoTestOut');
  btn.disabled = true;
  out.className = '';
  out.textContent = '확인 중…';

  try {
    const res = await fetch(SSO_TEST_URL, {
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
