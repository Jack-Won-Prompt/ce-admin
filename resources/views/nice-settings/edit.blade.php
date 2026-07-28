@extends('layouts.app')

@section('title', '본인확인 설정')
@section('page-title', 'NICE 본인확인 설정')
@section('breadcrumb', '홈 / 설정 / 본인확인 설정')

@push('styles')
<style>
  .ns-form { max-width:860px; }
  .ns-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
  .ns-card h3 { margin:0 0 16px; font-size:14px; font-weight:800; color:var(--primary);
    padding-bottom:10px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .ns-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  @media (max-width:720px) { .ns-grid { grid-template-columns:1fr; } }
  .ns-field { display:flex; flex-direction:column; gap:5px; }
  .ns-field.full { grid-column:1 / -1; }
  .ns-field label { font-size:12.5px; font-weight:700; color:var(--text-secondary); }
  .ns-field input[type=text], .ns-field input[type=password], .ns-field input[type=url] {
    padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; }
  .ns-field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  .ns-hint { font-size:11px; color:var(--text-muted); line-height:1.6; }
  .ns-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:11px 14px; font-size:12.5px; color:var(--text-secondary); margin-bottom:16px; line-height:1.6; }
  .ns-warn { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px;
    padding:11px 14px; font-size:12.5px; margin-bottom:16px; line-height:1.6; }
  .status-ok { background:#eafaf1; border:1px solid #a3e4c1; color:#15803d; border-radius:8px;
    padding:11px 14px; font-size:13.5px; margin-bottom:16px; font-weight:600; }
  .status-err { background:#fdecea; border:1px solid #f5c6c0; color:#c0392b; border-radius:8px;
    padding:11px 14px; font-size:13.5px; margin-bottom:16px; font-weight:600; }
  .badge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; }
  .badge-on  { background:#eafaf1; color:#15803d; }
  .badge-off { background:#fdecea; color:#c0392b; }
  .ns-check { display:flex; gap:10px; align-items:flex-start; border:1.5px solid var(--border);
    border-radius:10px; padding:12px 14px; margin-bottom:10px; cursor:pointer; }
  .ns-check input { margin-top:2px; width:17px; height:17px; flex-shrink:0; }
  .ns-check .t { font-size:13.5px; font-weight:700; color:var(--text-primary); }
  .ns-check .d { font-size:12px; color:var(--text-secondary); margin-top:3px; line-height:1.6; }
  .ns-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .btn-save { background:var(--primary); color:#fff; border:none; border-radius:8px; padding:11px 22px;
    font-size:14px; font-weight:700; cursor:pointer; }
  .btn-test { background:#fff; color:var(--primary); border:1.5px solid var(--primary); border-radius:8px;
    padding:10px 18px; font-size:14px; font-weight:700; cursor:pointer; }
  .btn-test:disabled { opacity:.6; cursor:default; }
  #testResult { font-size:12.5px; font-weight:600; line-height:1.6; }
  #testResult.ok  { color:#15803d; }
  #testResult.err { color:#c0392b; }
</style>
@endpush

@section('content')
<div class="ns-form">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="status-err">입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}</div>
  @endif

  <div class="ns-note">
    <i class="bx bx-info-circle"></i> 위임동의 서명 페이지에서 <b>서명 전 휴대폰 본인확인(NICE)</b>에 사용할 자격증명입니다.
    여기 저장한 값이 서버 <code>.env</code> 설정보다 <b>우선 적용</b>되며, 비밀키는 DB에 암호화되어 저장됩니다.
  </div>

  @if(! $configured)
    <div class="ns-warn">
      <i class="bx bx-error-circle"></i> <b>자격증명이 아직 완성되지 않았습니다.</b>
      client_id / client_secret / productID 3개가 모두 채워질 때까지 본인확인은 <b>비활성</b> 상태이며,
      서명 페이지는 기존 흐름(본인확인 없이 서명)대로 동작합니다.
    </div>
  @endif

  <form method="POST" action="{{ route('nice-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="ns-card">
      <h3>
        <i class="bx bx-key"></i> 자격증명
        <span class="badge {{ $configured ? 'badge-on' : 'badge-off' }}">{{ $configured ? '활성' : '미설정' }}</span>
        @if($setting->tested_at)
          <span class="ns-hint" style="margin-left:auto;font-weight:600;">
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
        <div class="ns-field">
          <label>productID</label>
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
          <div class="d">본인확인 결과의 성명이 처방전 환자명과 다르면 본인확인을 거부합니다.</div>
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
      <h3><i class="bx bx-link-alt"></i> 엔드포인트 <span class="ns-hint" style="font-weight:600;">(비워 두면 기본값 사용)</span></h3>
      <div class="ns-grid">
        <div class="ns-field">
          <label>API 서버</label>
          <input type="url" name="api_base" value="{{ old('api_base', $setting->api_base) }}"
                 placeholder="{{ $defaultApiBase }}">
          <span class="ns-hint">기관토큰·암호화토큰 발급 서버</span>
        </div>
        <div class="ns-field">
          <label>표준창 URL</label>
          <input type="url" name="standard_url" value="{{ old('standard_url', $setting->standard_url) }}"
                 placeholder="{{ $defaultStdUrl }}">
          <span class="ns-hint">휴대폰 본인확인 팝업(표준창) 주소</span>
        </div>
      </div>
    </div>

    <div class="ns-card">
      <div class="ns-actions">
        <button type="submit" class="btn-save"><i class="bx bx-save"></i> 저장</button>
        <button type="button" class="btn-test" id="btnTest" onclick="runNiceTest()">
          <i class="bx bx-plug"></i> 연결 테스트
        </button>
        <span id="testResult"></span>
      </div>
      <div class="ns-hint" style="margin-top:10px;">
        연결 테스트는 <b>저장된</b> 자격증명으로 기관토큰·암호화토큰 발급까지만 확인합니다.
        표준창을 열지 않으므로 본인확인 건당 요금은 발생하지 않습니다. 값을 바꿨다면 먼저 저장하세요.
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
