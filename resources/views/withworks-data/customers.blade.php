@extends('layouts.app')

@section('title', '고객 정보')
@section('page-title', '고객 정보')
@section('breadcrumb', '홈 - 운영 데이터 - 고객 정보')

@section('help-title', '고객 정보 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">무엇이 담긴 화면인가</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>
    위드웍스의 <span style="font-family:monospace;">accounts</span> 가운데 콜로플라스트
    아래 거래처(account_type=30)를 옮겨 담은 것입니다. <b>보기만 합니다</b> — 우리 거래처와
    이어지지 않습니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">주소</div>
  <div class="help-item"><div class="help-item-text">
    주소는 따로 담습니다(<span style="font-family:monospace;">ww_customer_addresses</span>).
    한 고객에 여럿일 수 있어 평균 1.75개이고, 줄의 <b>［주소］</b>를 누르면 그 고객의
    주소가 모두 펼쳐집니다.</div></div>
  <div class="help-item"><div class="help-item-text">
    주소는 네 칸으로 쪼개져 있습니다(address_line_1~4). 화면에서는 3ㆍ4ㆍ1ㆍ2 차례로 이어
    한 줄로 보여 줍니다.</div></div>
</div>
@endsection

@push('styles')
<style>
  .wd-bar    { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
  .wd-f      { display:flex; flex-direction:column; gap:6px; }
  .wd-f .ds-field-label { font-size:12px; }
  .wd-sum    { margin-left:auto; font-size:12px; color:var(--text-muted); line-height:32px; }
  .wd-scroll { overflow-x:auto; }
  .wd-table  { width:max-content; min-width:100%; border-collapse:collapse; font-size:12px; }
  .wd-table th, .wd-table td { padding:7px 9px; border-bottom:1px solid var(--border);
                               white-space:nowrap; }
  .wd-table th { background:var(--gray-50); font-weight:700; color:var(--gray-700);
                 position:sticky; top:0; z-index:1; text-align:left; }
  .wd-table th small { display:block; font-weight:400; color:var(--text-muted); font-size:10px; }
  .wd-table tbody tr:hover { background:var(--gray-50); }
  .wd-none   { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

  /* 주소 줄 — 고객 줄 바로 아래에 편다 */
  .wd-addr td { background:var(--gray-50); padding:0; }
  .wd-addr-in { padding:10px 14px; }
  .wd-addr-row { display:flex; gap:10px; padding:5px 0; border-bottom:1px dashed var(--border);
                 font-size:12px; align-items:baseline; }
  .wd-addr-row:last-child { border-bottom:0; }
  .wd-addr-row b { min-width:120px; }
  .wd-addr-row .zip { color:var(--text-muted); min-width:64px; }
</style>
@endpush

@section('content')
<div class="ds-grid-card">

  <form method="GET" class="wd-bar">
    <div class="wd-f">
      <span class="ds-field-label">찾기</span>
      <input type="text" class="form-control" name="찾기" value="{{ request('찾기') }}"
             placeholder="코드ㆍ이름ㆍ전화ㆍ주민번호" style="width:260px;">
    </div>
    <div class="wd-f">
      <span class="ds-field-label">&nbsp;</span>
      <div style="display:flex;gap:6px;">
        <a href="{{ route('ww-data.customers') }}" class="ds-btn">초기화</a>
        <button type="submit" class="ds-btn ds-btn-primary">검색</button>
      </div>
    </div>

    <div class="wd-sum">
      찾은 것 <b>{{ number_format($전체) }}</b>명
      @if ($전체 > 1000) · 아래에는 앞 1,000명만 @endif
      · 주소 {{ number_format($현황['customer_addresses']['담긴줄']) }}줄
    </div>
  </form>

  @if ($줄->isEmpty())
    <div class="wd-none">담긴 자료가 없습니다 — 설정 › 위드웍스 자료 가져오기에서 먼저 가져오십시오.</div>
  @else
    <div class="wd-scroll">
      <table class="wd-table">
        <thead>
          <tr>
            <th style="width:60px;"></th>
            @php
              $앞 = ['ww_id' => '위드웍스 번호', 'account_code' => '코드', 'account_name' => '이름',
                     'phone_1' => '전화', 'resident_no' => '주민등록번호', 'use_yn' => '사용'];
              $뺄것 = ['id', 'imported_at'];
              $모든칸 = array_keys((array) $줄->first());
              $뒤 = array_values(array_diff($모든칸, array_keys($앞), $뺄것));
            @endphp
            @foreach ($앞 as $칸 => $이름)
              <th>{{ $이름 }}<small>{{ $칸 }}</small></th>
            @endforeach
            <th>주소 수</th>
            @foreach ($뒤 as $칸)
              <th>{{ $칸 }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach ($줄 as $r)
            @php $그주소 = $주소[$r->ww_id] ?? collect(); @endphp
            <tr>
              <td>
                @if ($그주소->isNotEmpty())
                  <button type="button" class="ds-btn" style="padding:2px 8px;font-size:11px;"
                          onclick="wdToggle({{ $r->ww_id }})">주소</button>
                @endif
              </td>
              @foreach (array_keys($앞) as $칸)
                <td>{{ $r->{$칸} }}</td>
              @endforeach
              <td style="text-align:right;">{{ $그주소->count() ?: '' }}</td>
              @foreach ($뒤 as $칸)
                <td>{{ $r->{$칸} }}</td>
              @endforeach
            </tr>

            @if ($그주소->isNotEmpty())
              <tr class="wd-addr" id="addr-{{ $r->ww_id }}" style="display:none;">
                <td colspan="{{ count($앞) + count($뒤) + 2 }}">
                  <div class="wd-addr-in">
                    @foreach ($그주소 as $a)
                      <div class="wd-addr-row">
                        <b>{{ $a->address_name ?: '(이름 없음)' }}</b>
                        <span class="zip">{{ $a->zipcode }}</span>
                        <span>{{ trim(implode(' ', array_filter([
                          $a->address_line_3, $a->address_line_4,
                          $a->address_line_1, $a->address_line_2,
                        ]))) }}</span>
                        @if ($a->phone1)
                          <span style="color:var(--text-muted);">☎ {{ $a->phone1 }}</span>
                        @endif
                        @if ($a->use_yn === 'N')
                          <span style="color:var(--danger);">안 씀</span>
                        @endif
                      </div>
                    @endforeach
                  </div>
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
  /* 주소는 이미 함께 그려 두었다 — 누르면 펴고 접는다.
     줄마다 서버에 다시 묻지 않는다. 천 줄이면 천 번이 된다. */
  function wdToggle(고객) {
    const 줄 = document.getElementById('addr-' + 고객);
    if (줄) { 줄.style.display = (줄.style.display === 'none' || !줄.style.display) ? '' : 'none'; }
  }
</script>
@endpush
