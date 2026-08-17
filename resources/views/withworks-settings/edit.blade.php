@extends('layouts.app')

@section('title', '위드웍스 연동 설정')
@section('page-title', '위드웍스 연동 설정')
@section('breadcrumb', '홈 / 설정 / 위드웍스 연동')

@section('help-title', '위드웍스 연동 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>주문을 넘길 위드웍스가 테스트인지 운영인지 고르고, 주소·토큰·거래처를 관리합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">주의</div>
  <div class="help-tip"><i class="bx bx-error"></i>운영으로 바꾸면 이후 만드는 주문이 실제 물류로 넘어갑니다.</div>
</div>
@endsection

@push('styles')
<style>
  .ws-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:14px; }
  .ws-hd { padding:11px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700;
           display:flex; align-items:center; gap:8px; }
  .ws-hd .grow { flex:1; }
  .ws-bd { padding:14px 16px; }
  .ws-row { display:flex; gap:12px; align-items:center; margin-bottom:11px; }
  .ws-row > label { width:120px; flex-shrink:0; font-size:13px; color:var(--text-secondary); }
  .ws-hint { font-size:11px; color:var(--text-muted); margin-top:3px; }

  /* 지금 어디에 붙어 있는지가 이 화면에서 가장 먼저 보여야 한다 */
  .ws-mode { display:flex; gap:10px; flex-wrap:wrap; }
  .ws-opt { flex:1; min-width:240px; cursor:pointer; }
  .ws-opt input { position:absolute; opacity:0; }
  .ws-opt > span {
    display:block; border:1px solid var(--border); border-radius:var(--radius-lg);
    padding:12px 14px; background:var(--gray-0);
  }
  .ws-opt input:checked + span { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary-100); }
  .ws-opt .t { font-size:13px; font-weight:700; display:flex; align-items:center; gap:6px; }
  .ws-opt .d { font-size:11px; color:var(--text-muted); margin-top:4px; line-height:1.6; }
  .ws-opt.prod input:checked + span { border-color:var(--alert-500); box-shadow:0 0 0 2px var(--alert-100); }
  .ws-opt.prod .t { color:var(--alert-500); }

  .ws-now { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700;
            padding:4px 10px; border-radius:999px; }
  .ws-now.test { background:var(--primary-100); color:var(--primary-600); }
  .ws-now.prod { background:var(--alert-100); color:var(--alert-500); }
  .ws-bar { border-radius:8px; padding:10px 13px; font-size:12px; margin-bottom:14px; font-weight:700; }
  .ws-bar.ok { background:var(--primary-light); border:1px solid var(--primary-200); color:var(--primary); }
  .ws-bar.info { background:var(--gray-100); border:1px solid var(--gray-200); color:var(--gray-700); }
</style>
@endpush

@section('content')

@if(session('status'))<div class="ws-bar ok">{{ session('status') }}</div>@endif
@if(session('test_result'))<div class="ws-bar info">{{ session('test_result') }}</div>@endif

<form method="POST" action="{{ route('withworks-settings.update') }}">
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

  <div class="ws-card">
    <div class="ws-hd">판매유형</div>
    <div class="ws-bd">
      <div class="ws-row">
        <label>연동 유형</label>
        <select name="so_type" class="form-control form-select" style="max-width:260px;">
          {{-- PHP 가 '5001' 같은 숫자 문자열 키를 정수로 바꾸므로 비교 전에 되돌린다 --}}
          @foreach(\App\Models\Order::SALE_SO_TYPES as $code)
            @php $meta = \App\Models\Order::SO_TYPE_LABELS[$code]; @endphp
            <option value="{{ $code }}" @selected(old('so_type', $s->so_type) === (string) $code)>{{ $code }} · {{ $meta[0] }}</option>
          @endforeach
        </select>
      </div>
      <div class="ws-hint" style="margin-bottom:12px;">
        위드웍스로 넘기는 주문은 모두 이 유형으로 나갑니다. 다른 유형으로 넘기면 저쪽 콜백
        대상에서 빠져 진행 상태를 받지 못합니다.
      </div>

      {{-- 되돌리는 것은 판매와 코드가 다르다. 한 칸으로 두면 반품이 판매 유형으로 나간다. --}}
      <div class="ws-row">
        <label>교환·반품·취소</label>
        <select name="return_so_type" class="form-control form-select" style="max-width:260px;">
          @foreach(\App\Models\Order::RETURN_SO_TYPES as $code)
            @php $meta = \App\Models\Order::SO_TYPE_LABELS[$code]; @endphp
            <option value="{{ $code }}" @selected(old('return_so_type', $s->return_so_type) === (string) $code)>
              {{ $code }} · {{ $meta[0] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="ws-hint">
        교환·반품·취소를 위드웍스로 넘길 때 쓰는 유형입니다. 역물류 연계는 아직 붙지 않았고,
        붙으면 이 값으로 나갑니다.
      </div>
    </div>
  </div>

  <div style="display:flex;gap:8px;justify-content:flex-end;">
    <button type="submit" class="btn btn-primary">저장</button>
  </div>
</form>

<form method="POST" action="{{ route('withworks-settings.test') }}" style="margin-top:10px;text-align:right;">
  @csrf
  <button type="submit" class="btn btn-outline">지금 설정으로 연결 확인</button>
</form>

@endsection
