@extends('layouts.app')

@section('title', '서비스 연동 설정')
@section('page-title', '서비스 연동 설정')

@section('help-title', '서비스 연동 설정 도움말')
@section('help-body')
  <div class="help-section-title">화면 소개</div>
  <p>외부 서비스에 연결할 때 쓰는 키와 설정을 한곳에서 관리합니다. 서비스마다 탭이 나뉘어 있습니다.</p>
  <div class="help-section-title">저장 방식</div>
  <p>여기서 저장한 항목만 서버 설정을 덮어씁니다. 손대지 않은 항목은 서버의 <code>.env</code> 값을 그대로 씁니다.</p>
  <p>키·비밀번호는 암호화해서 보관하며, 화면에는 다시 내려보내지 않습니다. <b>바꿀 때만</b> 입력하고, 빈칸으로 저장하면 기존 값이 유지됩니다.</p>
  <div class="help-section-title">주의</div>
  <p>데이터베이스 접속 정보와 암호화 키는 이 화면에서 다루지 않습니다. 설정을 읽으려면 데이터베이스가 먼저 연결돼야 하고, 암호화 키를 데이터베이스에 두면 암호화가 의미를 잃기 때문입니다.</p>
@endsection

@section('content')
<style>
  .ss-panel { display: none; flex-direction: column; gap: 16px; }
  .ss-panel.active { display: flex; }
  .ss-card { background: var(--gray-0); border-radius: 12px; padding: 16px; }
  .ss-card-head { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
  .ss-card-title { font-size: 15px; font-weight: 700; color: var(--gray-900); }
  .ss-card-desc  { font-size: 12px; color: var(--gray-500); }
  .ss-fields { display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 16px; }
  .ss-field  { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
  .ss-help   { font-size: 11px; color: var(--gray-500); line-height: 1.5; }
  .ss-check  { display: inline-flex; align-items: center; gap: 8px; height: 34px; font-size: 13px; color: var(--gray-800); cursor: pointer; }
  .ss-actions { display: flex; justify-content: flex-end; gap: 8px; }
  .ss-note { font-size: 12px; color: var(--gray-500); line-height: 1.6; }
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

    <div class="ss-actions">
      <button type="submit" class="ds-btn ds-btn-primary">저장</button>
    </div>
  </form>
@endforeach

<div class="ss-note">
  데이터베이스 접속 정보와 암호화 키(<code>APP_KEY</code>, 주민번호 전용 키)는 이 화면에서 다루지 않습니다.
  설정을 읽으려면 데이터베이스가 먼저 연결돼야 하고, 데이터를 푸는 열쇠를 그 데이터 옆에 두면 암호화가 의미를 잃습니다.
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
</script>
@endsection
