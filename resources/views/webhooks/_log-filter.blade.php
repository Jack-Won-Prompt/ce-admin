{{-- 로그 거르개 — 목록 쪽 거르개와 **같은 자리에 같은 모양**으로 선다 (2026-09-10 지시).

     탭을 바꿀 때 얼개가 달라지면 안 된다. 거르개가 위 카드에 있다가 카드 안쪽으로
     옮겨 가면 화면이 통째로 흔들린다 — 둘 다 맨 위 카드에 두고 하나만 보인다. --}}

@php($_보낼곳 = $보내는곳 ?? route('webhooks.index'))

<form method="GET" action="{{ $_보낼곳 }}" class="ds-filter-card" id="whLogFilterCard"
      style="display:{{ ($tab ?? 'logs') === 'logs' ? '' : 'none' }};">
  {{-- 찾고 나서도 이 탭에 그대로 있어야 한다 --}}
  <input type="hidden" name="tab" value="logs">

  <div class="ds-filter-fields">
    {{-- 기간은 두 칸을 쓴다 — 날짜 둘과 「~」가 한 칸에 들어가면 서로 눌려
         「2026-06-01」과 달력 아이콘이 잘린다(2026-10-02 지시).
         폭을 손으로 적지 않고 표준 틀(ds-field-range)에 맡긴다. --}}
    <div class="ds-filter-field span-2">
      <label class="ds-field-label">기간</label>
      <div class="ds-field-range">
        <input type="date" name="log_from" value="{{ $logFrom }}" class="form-control">
        <span class="ds-field-sep">~</span>
        <input type="date" name="log_to" value="{{ $logTo }}" class="form-control">
      </div>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">구분</label>
      <select name="log_provider" class="form-control form-select">
        <option value="">전체</option>
        @foreach(config('webhooks.providers') as $k => $label)
          <option value="{{ $k }}" @selected($logProvider === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">방향</label>
      <select name="log_direction" class="form-control form-select">
        <option value="">전체</option>
        @foreach(config('webhooks.directions') as $k => $label)
          <option value="{{ $k }}" @selected($logDirection === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="ds-filter-field">
      <label class="ds-field-label">결과</label>
      <select name="log_result" class="form-control form-select">
        <option value="">전체</option>
        <option value="ok"   @selected($logResult === 'ok')>성공</option>
        <option value="fail" @selected($logResult === 'fail')>실패</option>
      </select>
    </div>
    {{-- 필터 줄은 아홉 칸 그리드다 — flex:1 은 아무 일도 하지 않아 검색어가
         한 칸(1/9)으로 눌려 있었다. 칸 수로 적는다. --}}
    <div class="ds-filter-field span-3">
      <label class="ds-field-label">검색어</label>
      <input type="text" name="log_search" value="{{ $logSearch }}" class="form-control"
             placeholder="이벤트 · 주문번호 · 주소 · 본문">
    </div>
  </div>
  <div class="ds-filter-actions">
    <a href="{{ $_보낼곳 }}?tab=logs" class="ds-btn">초기화</a>
    <button type="submit" class="ds-btn ds-btn-primary">검색</button>
    <button type="button" class="ds-btn" onclick="window.__wlGrid?.downloadExcel()">엑셀 다운</button>
  </div>
</form>
