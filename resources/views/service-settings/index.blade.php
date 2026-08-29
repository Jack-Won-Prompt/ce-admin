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

@push('styles')
<style>
  /* 탭이 열한 개라 좁은 폭에서 줄이 모자란다 — 1920 은 911/1536, 1280 은 911/912 로
     겨우 든다. 카드가 overflow:hidden 이라 넘치면 뒤쪽 탭이 잘려 누를 수 없다.
     줄을 접게 두면 잘리지 않는다(1920·1280 에서는 한 줄 그대로다).
     접힌 줄 사이는 0 이라야 밑줄이 탭 바로 아래에 붙는다. */
  #ssTabs { flex-wrap: wrap; row-gap: 0; }

  /* ── 판 몸통 ──────────────────────────────────────────────────────
     탭줄 아래가 카드의 안쪽이다. 표준 카드 안여백 12/16 을 여기 한 번만 준다
     (전에는 .ss-card 가 16 을 따로 갖고, 판이 흰 판이라 안내문·단추 줄에만
     좌우 16 을 덧대고 있었다 — 세 곳에 흩어져 있던 여백을 한 곳으로 모은다).
     카드가 overflow:hidden 이라 긴 판은 이 안에서 스스로 구른다. */
  .ss-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 12px 16px; gap: 12px; }

  .ss-panel { display: none; flex-direction: column; gap: 12px; }
  .ss-panel.active { display: flex; }

  /* 판은 이미 흰 카드(.ds-grid-card) 안이다 — 그리드가 그렇듯 제 바탕·모서리·안여백을
     또 갖지 않는다. 흰 것 위에 흰 것을 얹으면 안여백만 16+16 으로 겹쳤다. */
  .ss-card { background: transparent; border-radius: 0; padding: 0; }
  .ss-card-head { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
  .ss-card-title { font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-900); }
  /* 설명은 시안 안내문 규격 — 12/500 lh19 · gray-600 (gray-500 은 흰 바탕 대비 4.05:1) */
  .ss-card-desc  { font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600); }

  .ss-fields { display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 16px; }
  .ss-field  { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
  .ss-help   { font-size: 11px; font-weight: 400; line-height: 18px; color: var(--gray-600); }
  /* 체크박스 칸도 입력칸과 같은 높이여야 라벨 아래 줄이 어긋나지 않는다
     (.form-control 은 5+20+5+테두리 2 = 32 다. 34 면 그 칸만 2 내려앉는다). */
  .ss-check  { display: inline-flex; align-items: center; gap: 8px; height: 32px; font-size: 13px; color: var(--gray-800); cursor: pointer; }
  .ss-actions { display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap; }
  /* 시험 결과는 단추 왼쪽에 그대로 적는다 — 토스트로 띄우면 사유가 길어 잘린다 */
  /* 굵기 600 은 시안에 없다(400·500·700 셋뿐) — 500 으로 내린다. 색은 의미색이라 그대로 둔다. */
  .ss-test-out { margin-right: auto; font-size: 12px; font-weight: 500; line-height: 19px; }
  .ss-test-out.ok  { color: var(--success); }
  .ss-test-out.err { color: var(--danger); }

  /* ── 안내문 ────────────────────────────────────────────────────
     시안에 파란 상자는 없다. 12/500 · gray-600 · 앞에 12×12 alert-circle · gap 4 다.
     전역 .ds-grid-hint 를 그대로 쓰지 못하는 까닭 — 그쪽은 결과바 한 줄에 서는 부품이라
     nowrap + ellipsis 다. 여기 연결 테스트 안내문은 110 자라 그대로 쓰면 문장이 잘린다.
     그래서 규격만 같이 두고 줄바꿈만 살린다. */
  .ss-note, .ss-flash { font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600); }
  .ss-note::before, .ss-flash::before {
    content: ''; display: inline-block; vertical-align: top;
    width: 12px; height: 19px; margin-right: 4px;
    background: currentColor;
    -webkit-mask: var(--icon-alert-circle) center / 12px 12px no-repeat;
            mask: var(--icon-alert-circle) center / 12px 12px no-repeat;
  }

  /* 위드웍스 조각은 제 화면(/settings/withworks)에서는 혼자 서는 흰 판이라
     스스로 바탕·모서리·안여백 16 을 갖는다. 이 화면에서는 표준 카드 안이라
     그 셋을 여기서만 끈다 — 조각을 고치지 않으므로 /settings/withworks 는 그대로다. */
  .ss-body .ws-shell { background: transparent; border-radius: 0; padding: 0; }
</style>
@endpush

@section('content')
{{-- 표준 얼개 — .ds-grid-section(투명·flex 1) > .ds-grid-card(흰 r12·테두리 없음·overflow hidden)
     안에 패널 탭과 판을 담는다. 목록 화면 스물넷과 같은 뼈대다. --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">

    {{-- 탭줄 — 전에는 .ds-chips 알약(h31 · r999 · 12/700)이었다. 칩은 시안에서 검색 카드
         안쪽 첫 줄에 서는 「상태 필터」다(174:1185 · 미청구/청구완료/승인/거부 처럼 건수 배지를
         단다). 여기 열한 개는 거르는 것이 아니라 판을 갈아 끼우는 것이라 패널 탭(114:4778)이
         맡는 자리다 — 권한 그룹·위임장 설정과 같은 부품으로 맞춘다.
         data-tab 은 그대로다. --}}
    <div class="pnl-tabs" id="ssTabs">
      @foreach ($schema as $group => $def)
        <button type="button" class="pnl-tab {{ $group === $active ? 'active' : '' }}" data-tab="{{ $group }}">
          {{ $def['label'] }}
        </button>
      @endforeach
      {{-- 위드웍스는 저장 위치가 스키마(settings 표)가 아니라 전용 표라 폼이 따로 나간다.
           그래도 담당자에게는 같은 화면의 한 탭이어야 하므로 여기 나란히 세운다. --}}
      <button type="button" class="pnl-tab {{ $active === 'withworks' ? 'active' : '' }}" data-tab="withworks">
        위드웍스 연동
      </button>
    </div>

    <div class="ss-body fill-col">
      @if (session('success'))
        <div class="ss-flash">{{ session('success') }}</div>
      @endif

      @foreach ($schema as $group => $def)
        {{-- fill-rest/fill-col — 판이 남는 높이를 받아(.fill-rest) 안의 카드에 다시 나눠 준다(.fill-col).
             .fill-col 의 display:flex 가 숨은 판을 깨우지 않는다 — 특정성이 같은데
             이 화면의 .ss-panel{display:none} 이 전역 규칙보다 문서에서 뒤에 있어 이긴다. --}}
        <form method="POST" action="{{ route('service-settings.update', $group) }}"
              class="ss-panel fill-rest fill-col {{ $group === $active ? 'active' : '' }}" data-panel="{{ $group }}">
          @csrf
          @method('PUT')

          {{-- 카드가 남는 높이를 다 받는다. 안의 칸·글줄은 제 높이를 지키고,
               늘어나는 것은 빈 아래쪽뿐이다. 저장 단추 줄(.ss-actions)은 제 높이(32)
               그대로 아래에 남는다. --}}
          <div class="ss-card fill-rest">
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

                  @elseif ($type === 'textarea')
                    {{-- 여러 줄로 적는 값(쉬는 날 목록 따위) — 한 줄 칸에 넣으면 끝이 안 보인다 --}}
                    <textarea name="{{ $key }}" class="form-control" rows="3"
                              style="min-height:72px;line-height:1.5;">{{ $state['value'] }}</textarea>

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
            <span class="ss-note">{{ $def['test']['help'] ?? '' }}</span>
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
    </div>

  </div>
</div>

<script>
  (function () {
    const tabs = document.getElementById('ssTabs');
    if (!tabs) return;
    tabs.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-tab]');
      if (!btn) return;
      const name = btn.dataset.tab;
      tabs.querySelectorAll('.pnl-tab').forEach(c => c.classList.toggle('active', c === btn));
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
