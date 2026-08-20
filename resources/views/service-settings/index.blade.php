@extends('layouts.app')

@section('title', '서비스 연동 설정')
@section('page-title', '서비스 연동 설정')
{{-- 빵부스러기가 없어 기본값 '홈' 하나만 나왔다 — 다른 설정 화면 여섯과 같은 세 마디로.
     낱말은 사이드바·시안 사이드바(174:955)와 같다. --}}
@section('breadcrumb', '홈 - 설정 - 서비스 연동 설정')

@section('help-title', '서비스 연동 설정 도움말')
@section('help-body')
  <div class="help-section-title">화면 소개</div>
  <p>외부 서비스에 연결할 때 쓰는 키와 설정을 한곳에서 관리합니다. 서비스마다 탭이 나뉘어 있습니다.</p>
  <div class="help-section-title">저장 방식</div>
  <p>여기서 저장한 항목만 서버 설정을 덮어씁니다. 손대지 않은 항목은 서버의 <code>.env</code> 값을 그대로 씁니다.</p>
  <p>키·비밀번호는 암호화해서 보관하며, 화면에는 다시 내려보내지 않습니다. <b>바꿀 때만</b> 입력하고, 빈칸으로 저장하면 기존 값이 유지됩니다.</p>
@endsection

@section('content')
<style>
  .ss-panel { display: none; flex-direction: column; gap: 16px; }
  .ss-panel.active { display: flex; }
  .ss-card { background: var(--gray-0); border-radius: 12px; padding: 16px; }
  .ss-card-head { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
  .ss-card-title { font-size: 14px; font-weight: 700; color: var(--gray-900); }
  .ss-card-desc  { font-size: 12px; color: var(--gray-500); }
  .ss-fields { display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 16px; }
  .ss-field  { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
  .ss-help   { font-size: 11px; color: var(--gray-500); line-height: 1.5; }
  .ss-check  { display: inline-flex; align-items: center; gap: 8px; height: 34px; font-size: 13px; color: var(--gray-800); cursor: pointer; }
  .ss-actions { display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap; }
  /* 시험 결과는 단추 왼쪽에 그대로 적는다 — 토스트로 띄우면 사유가 길어 잘린다 */
  .ss-test-out { margin-right: auto; font-size: 12px; line-height: 1.5; }
  .ss-test-out.ok  { color: var(--success, #12805c); font-weight: 600; }
  .ss-test-out.err { color: var(--danger, #b42318); font-weight: 600; }
  .ss-flash { padding: 10px 14px; border-radius: 8px; background: var(--primary-light); color: var(--primary); font-size: 13px; font-weight: 600; }
</style>

@if (session('success'))
  <div class="ss-flash">{{ session('success') }}</div>
@endif

<div class="ds-chips" id="ssTabs">
  @foreach ($schema as $group => $def)
    <button type="button" class="ds-chip {{ $group === $active ? 'active' : '' }}" data-tab="{{ $group }}">
      {{ $def['label'] }}
    </button>
  @endforeach
  {{-- 위드웍스는 저장 위치가 스키마(settings 표)가 아니라 전용 표라 폼이 따로 나간다.
       그래도 담당자에게는 같은 화면의 한 탭이어야 하므로 여기 나란히 세운다. --}}
  <button type="button" class="ds-chip {{ $active === 'withworks' ? 'active' : '' }}" data-tab="withworks">
    위드웍스 연동
  </button>
</div>

@foreach ($schema as $group => $def)
  <form method="POST" action="{{ route('service-settings.update', $group) }}"
        class="ss-panel {{ $group === $active ? 'active' : '' }}" data-panel="{{ $group }}">
    @csrf
    @method('PUT')

    <div class="ss-card">
      <div class="ss-card-head">
        <span class="ss-card-title">{{ $def['label'] }}</span>
        @if (!empty($def['desc']))
          <span class="ss-card-desc">{{ $def['desc'] }}</span>
        @endif
      </div>

      <div class="ss-fields">
        @foreach ($def['fields'] as $key => $f)
          @php
            $type   = $f['type'] ?? 'text';
            $span   = ['1' => 2, '2' => 3, '3' => 6][(string) ($f['width'] ?? 2)] ?? 3;
            $state  = $values[$group][$key] ?? ['value' => null, 'filled' => false];
            $secret = $type === 'password';
          @endphp
          <div class="ss-field" style="grid-column: span {{ $span }};">
            <span class="ds-field-label">{{ $f['label'] }}</span>

            @if ($type === 'bool')
              <label class="ss-check">
                <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) $state['value'])>
                <span>사용</span>
              </label>

            @elseif ($type === 'select')
              <select name="{{ $key }}" class="form-control">
                @foreach ($f['options'] ?? [] as $val => $lbl)
                  <option value="{{ $val }}" @selected((string) $state['value'] === (string) $val)>{{ $lbl }}</option>
                @endforeach
              </select>

            @elseif ($secret)
              {{-- 원문을 화면에 내려보내지 않는다. 바꿀 때만 입력한다. --}}
              <input type="password" name="{{ $key }}" class="form-control" autocomplete="new-password"
                     placeholder="{{ $state['filled'] ? '설정됨 — 바꿀 때만 입력' : '미설정' }}">

            @else
              <input type="{{ $type === 'int' ? 'number' : 'text' }}" name="{{ $key }}" class="form-control"
                     value="{{ $state['value'] }}">
            @endif

            @if (!empty($f['help']))
              <span class="ss-help">{{ $f['help'] }}</span>
            @endif
          </div>
        @endforeach
      </div>
    </div>

    @if (!empty($def['test']['route']))
      {{-- 시험은 화면의 입력칸이 아니라 「저장된」 값으로 돈다. 서버에 든 것이 실제로
           나가는 값이라, 고치는 중인 값으로 시험하면 결과가 거짓이 된다. --}}
      <span class="ss-help">{{ $def['test']['help'] ?? '' }}</span>
    @endif

    <div class="ss-actions">
      @if (!empty($def['test']['route']))
        <span class="ss-test-out" id="ssTestOut-{{ $group }}"></span>
        <button type="button" class="ds-btn ss-test" data-url="{{ route($def['test']['route']) }}" data-group="{{ $group }}">
          <i class="bx bx-plug"></i> {{ $def['test']['label'] ?? '연결 테스트' }}
        </button>
      @endif
      <button type="submit" class="ds-btn ds-btn-primary">저장</button>
    </div>
  </form>
@endforeach

{{-- iframe 을 쓰지 않는다. 프레임이면 높이를 따로 맞춰야 하고, 저장하고 돌아올 때
     부모 화면의 탭 자리를 잃는다. 같은 문서에 그리면 그런 것이 없다. --}}
<div class="ss-panel {{ $active === 'withworks' ? 'active' : '' }}" data-panel="withworks">
  @include('withworks-settings._form', ['s' => \App\Models\WithworksSetting::current()])
</div>

<script>
  (function () {
    const tabs = document.getElementById('ssTabs');
    if (!tabs) return;
    tabs.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-tab]');
      if (!btn) return;
      const name = btn.dataset.tab;
      tabs.querySelectorAll('.ds-chip').forEach(c => c.classList.toggle('active', c === btn));
      document.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('active', p.dataset.panel === name));
      // 저장 후 같은 탭으로 돌아오도록 주소만 바꾼다(이동은 하지 않는다)
      const url = new URL(window.location.href);
      url.searchParams.set('tab', name);
      history.replaceState(null, '', url);
    });
  })();

  /* 연결 테스트 — 스키마에 test.route 를 적어 둔 묶음에만 단추가 서 있다.
     묶음이 늘어도 이 조각은 그대로다. 폼 안에 있는 단추라 type="button" 이어야
     저장이 딸려 나가지 않는다. */
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.ss-test');
    if (!btn) return;

    const out = document.getElementById('ssTestOut-' + btn.dataset.group);
    btn.disabled = true;
    if (out) { out.className = 'ss-test-out'; out.textContent = '테스트 중…'; }

    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
          'Accept': 'application/json',
        },
      });
      const d = await res.json();
      if (out) {
        out.className = 'ss-test-out ' + (d.ok ? 'ok' : 'err');
        out.textContent = (d.ok ? '✅ ' : '⚠️ ') + (d.message || '') + (d.detail ? ' (' + d.detail + ')' : '');
      }
    } catch (err) {
      if (out) { out.className = 'ss-test-out err'; out.textContent = '⚠️ 테스트 요청 중 오류가 발생했습니다.'; }
    } finally {
      btn.disabled = false;
    }
  });
</script>
@endsection
