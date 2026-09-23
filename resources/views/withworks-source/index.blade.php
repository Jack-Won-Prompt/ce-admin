@extends('layouts.app')

@section('title', '위드웍스 자료 가져오기')
@section('page-title', '위드웍스 자료 가져오기')
@section('breadcrumb', '홈 - 설정 - 위드웍스 자료 가져오기')

@section('help-title', '위드웍스 자료 가져오기 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">무엇을 하는 화면인가</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>
    위드웍스 운영 DB 의 처방전 정보ㆍ고객 정보ㆍ고객 주소를 우리 표로 옮겨 담습니다.
    <b>저쪽 DB 는 읽기만 합니다</b> — 이어 붙는 그 순간 읽기 전용으로 선언하므로,
    우리 쪽에서 실수로 쓰기가 나가도 DB 가 거절합니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">마지막 번호</div>
  <div class="help-item"><div class="help-item-text">
    어디까지 가져왔는지 기록하는 값입니다. 다시 가져오면 <b>이 번호 이후만</b> 읽습니다 —
    상대 시스템이 운영 중이므로 전체를 조회하면 해당 DB에 부하가 발생합니다.</div></div>
  <div class="help-item"><div class="help-item-text">
    <b>0으로 설정하면 처음부터 다시 읽습니다.</b> 상대 시스템에서 기존 행을 수정했거나 항목을 새로
    추가했을 때 사용합니다. 이미 가져온 행은 덮어씁니다.</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">차례</div>
  <div class="help-tip"><i class="bx bx-error-circle"></i>
    <b>고객 정보를 먼저 가져와야 고객 주소를 가져올 수 있습니다.</b> 고객은 admin,
    주소는 warehouse 에 있어 주소를 거를 때 담아 둔 고객 목록을 봅니다.</div>
</div>
@endsection

@push('styles')
<style>
  .ws-card   { background: transparent; border-radius: 0; padding: 0; margin-bottom: 22px; }
  .ws-title  { font-size: 14px; font-weight: 700; line-height: 22px; color: var(--gray-900); }
  .ws-desc   { font-size: 12px; font-weight: 500; line-height: 19px; color: var(--gray-600);
               margin: 4px 0 14px; }
  .ws-fields { display: grid; grid-template-columns: repeat(10, minmax(0, 1fr)); gap: 16px; }
  .ws-field  { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
  .ws-help   { font-size: 11px; line-height: 18px; color: var(--gray-600); }

  .ws-table  { width: 100%; border-collapse: collapse; font-size: 13px; }
  .ws-table th, .ws-table td { padding: 9px 10px; border-bottom: 1px solid var(--border);
                               text-align: left; vertical-align: middle; }
  .ws-table th { font-size: 12px; font-weight: 700; color: var(--gray-700);
                 background: var(--gray-50); white-space: nowrap; }
  .ws-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
  .ws-table input { width: 130px; text-align: right; font-variant-numeric: tabular-nums; }

  .ws-note { margin-top: 10px; padding: 9px 12px; border-radius: 8px;
             background: var(--warning-light); border: 1px solid var(--border);
             color: var(--gray-800); font-size: 12px; line-height: 1.6; }
</style>
@endpush

@section('content')
<div class="ds-grid-card">
  <form method="POST" action="{{ route('withworks-source.save') }}">
    @csrf
    @method('PUT')

    {{-- ── 담긴 자료와 마지막 번호 ── --}}
    <div class="ws-card">
      <div class="ws-title">담긴 자료</div>
      <div class="ws-desc">
        다시 가져오면 마지막 번호 이후만 읽습니다. 0으로 설정하면 처음부터 다시 읽습니다.
      </div>

      <table class="ws-table">
        <thead>
          <tr>
            <th>구분</th><th>원천</th><th>우리 표</th>
            <th style="text-align:right;">저장 건수</th>
            <th style="text-align:right;">마지막 번호</th>
            <th>최종 반영 일시</th><th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($현황 as $열쇠 => $h)
            <tr>
              <td><b>{{ $h['이름'] }}</b></td>
              <td style="font-family:monospace;font-size:12px;">{{ $h['원천'] }}</td>
              <td style="font-family:monospace;font-size:12px;">{{ $h['우리표'] }}</td>
              <td class="num" id="cnt-{{ $열쇠 }}">{{ number_format($h['담긴줄']) }}</td>
              <td class="num">
                <input type="number" class="form-control" name="{{ $열쇠 }}_last_id"
                       id="last-{{ $열쇠 }}" value="{{ $h['마지막'] }}" min="0">
              </td>
              <td style="font-size:12px;color:var(--text-muted);" id="at-{{ $열쇠 }}">
                {{ $h['마지막담은때'] ?: '아직 없음' }}
              </td>
              <td>
                <button type="button" class="ds-btn" onclick="wsImport('{{ $열쇠 }}', this)">가져오기</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="ws-note">
        <b>가져오기는 저장된 번호가 아니라 현재 반영된 번호를 기준으로 실행됩니다.</b> 번호를 수정했다면
        먼저 ［저장］을 누르십시오. 최초 적재처럼 대량 건을 처리할 때는 명령으로 실행하는 것을
        권장합니다 — <span style="font-family:monospace;">php artisan withworks:import</span>
      </div>
    </div>

    {{-- ── 접속 정보 ── --}}
    @foreach (\App\Support\WithworksSource::갈래 as $갈래 => $이름)
      <div class="ws-card">
        <div class="ws-title">{{ $이름 }} 접속</div>
        <div class="ws-desc">
          읽기 전용으로만 씁니다. 이어 붙는 그 순간
          <span style="font-family:monospace;">SET SESSION TRANSACTION READ ONLY</span> 를 겁니다.
        </div>

        <div class="ws-fields">
          <div class="ws-field" style="grid-column: span 4;">
            <span class="ds-field-label">주소(host)</span>
            <input type="text" class="form-control" name="{{ $갈래 }}_host"
                   value="{{ $계정[$갈래]['host']['value'] }}">
          </div>
          <div class="ws-field" style="grid-column: span 1;">
            <span class="ds-field-label">포트</span>
            <input type="text" class="form-control" name="{{ $갈래 }}_port"
                   value="{{ $계정[$갈래]['port']['value'] }}">
          </div>
          <div class="ws-field" style="grid-column: span 2;">
            <span class="ds-field-label">DB 이름</span>
            <input type="text" class="form-control" name="{{ $갈래 }}_database"
                   value="{{ $계정[$갈래]['database']['value'] }}">
          </div>
          <div class="ws-field" style="grid-column: span 2;">
            <span class="ds-field-label">아이디</span>
            <input type="text" class="form-control" name="{{ $갈래 }}_username"
                   value="{{ $계정[$갈래]['username']['value'] }}">
          </div>
          <div class="ws-field" style="grid-column: span 3;">
            <span class="ds-field-label">비밀번호</span>
            {{-- 원문을 화면에 내려보내지 않는다. 바꿀 때만 적는다. --}}
            <input type="password" class="form-control" name="{{ $갈래 }}_password"
                   autocomplete="new-password"
                   placeholder="{{ $계정[$갈래]['password']['filled'] ? '설정됨 — 변경할 때만 입력' : '미설정' }}">
          </div>
          <div class="ws-field" style="grid-column: span 3;justify-content:flex-end;">
            <button type="button" class="ds-btn" onclick="wsTest('{{ $갈래 }}', this)">연결 시험</button>
          </div>
          <div class="ws-field" style="grid-column: span 10;">
            <span class="ws-help" id="test-{{ $갈래 }}"></span>
          </div>
        </div>
      </div>
    @endforeach

    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button type="submit" class="ds-btn ds-btn-primary">저장</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  const WS_TEST_URL   = @json(route('withworks-source.test', ['갈래' => '__G__']));
  const WS_IMPORT_URL = @json(route('withworks-source.import'));

  async function wsTest(갈래, btn) {
    const 자리 = document.getElementById('test-' + 갈래);
    BtnState.loading(btn, '시험 중...');
    자리.textContent = '';

    /* 시험은 화면의 입력칸이 아니라 **저장된** 값으로 돈다 — 서버에 든 것이 실제로
       쓰이는 값이라, 고치는 중인 값으로 시험하면 결과가 거짓이 된다. */
    try {
      const res = await fetch(WS_TEST_URL.replace('__G__', 갈래), {
        headers: { 'Accept': 'application/json' },
      });
      const d = await res.json();
      자리.textContent = (d.success ? '○ ' : '✕ ') + d.message;
      자리.style.color = d.success ? 'var(--success)' : 'var(--danger)';
    } catch (e) {
      자리.textContent = '✕ ' + e.message;
      자리.style.color = 'var(--danger)';
    }
    BtnState.reset(btn, '연결 시험');
  }

  async function wsImport(열쇠, btn) {
    if (!await ceConfirm('현재 등록된 마지막 번호 이후부터 가져옵니다. 계속하시겠습니까?')) return;

    BtnState.loading(btn, '가져오는 중...');

    try {
      const res = await apiRequest(WS_IMPORT_URL, 'POST', { 대상: 열쇠 });

      if (res.success) {
        showToast(res.message, 'success', 6000);
        /* 그 줄만 다시 그린다 — 화면을 새로 열면 적다 만 값이 날아간다 */
        const h = res.현황;
        if (h) {
          document.getElementById('cnt-'  + 열쇠).textContent = Number(h.담긴줄).toLocaleString('ko-KR');
          document.getElementById('last-' + 열쇠).value       = h.마지막;
          document.getElementById('at-'   + 열쇠).textContent = h.마지막담은때 ?? '아직 없음';
        }
      }
    } catch (e) {
      showToast(e.message || '가져오지 못했습니다.', 'danger', 8000);
    }

    BtnState.reset(btn, '가져오기');
  }
</script>
@endpush
