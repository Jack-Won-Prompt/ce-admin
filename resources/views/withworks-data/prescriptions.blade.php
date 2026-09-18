@extends('layouts.app')

@section('title', '처방전 정보')
@section('page-title', '처방전 정보')
@section('breadcrumb', '홈 - 운영 데이터 - 처방전 정보')

@section('help-title', '처방전 정보 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">무엇이 담긴 화면인가</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>
    위드웍스의 <span style="font-family:monospace;">account_add_informations</span> 를
    우리 표로 옮겨 담은 것입니다. <b>보기만 합니다</b> — 여기서 고쳐도 위드웍스로 가지 않고,
    우리 처방전ㆍ주문과도 이어지지 않습니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">udf 칸</div>
  <div class="help-item"><div class="help-item-text">
    위드웍스는 자유 칸(udf1~udf50)에 값을 담습니다. 우리가 아는 짝은 머리글에 함께
    적었습니다 — 우리가 그쪽으로 <b>보낼 때</b> 쓰는 자리와 같습니다.</div></div>
  <div class="help-item"><div class="help-item-text">
    짝을 모르는 칸은 이름 그대로 둡니다. 값을 보고 뜻이 가려지면 그때 이름을 붙입니다.</div></div>
</div>
<div class="help-section">
  <div class="help-section-title">대리점</div>
  <div class="help-tip"><i class="bx bx-error-circle"></i>
    이 표에는 <b>다른 대리점의 자료도 함께</b> 있습니다(전체 열네 곳). 콜로플라스트 것만
    보려면 위의 대리점 고르개를 쓰십시오.</div>
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
</style>
@endpush

@section('content')
<div class="ds-grid-card">

  <form method="GET" class="wd-bar">
    <div class="wd-f">
      <span class="ds-field-label">찾기</span>
      <input type="text" class="form-control" name="찾기" value="{{ request('찾기') }}"
             placeholder="번호ㆍ내용ㆍ의사ㆍ병원ㆍ지사" style="width:230px;">
    </div>
    <div class="wd-f">
      <span class="ds-field-label">대리점</span>
      <select name="대리점" class="form-control" style="width:230px;">
        <option value="">전체</option>
        @foreach ($대리점 as $d)
          <option value="{{ $d['id'] }}" @selected((string) request('대리점') === (string) $d['id'])>
            {{ $d['name'] }} ({{ number_format($d['n']) }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="wd-f">
      <span class="ds-field-label">등록일</span>
      <div style="display:flex;align-items:center;gap:6px;">
        <input type="date" class="form-control" name="부터" value="{{ request('부터') }}" style="width:150px;">
        <span style="color:var(--text-muted);">~</span>
        <input type="date" class="form-control" name="까지" value="{{ request('까지') }}" style="width:150px;">
      </div>
    </div>
    <div class="wd-f">
      <span class="ds-field-label">&nbsp;</span>
      <div style="display:flex;gap:6px;">
        <a href="{{ route('ww-data.prescriptions') }}" class="ds-btn">초기화</a>
        <button type="submit" class="ds-btn ds-btn-primary">검색</button>
      </div>
    </div>

    <div class="wd-sum">
      담긴 때 {{ $현황['마지막담은때'] ?: '아직 없음' }}
    </div>
  </form>

  @if ($줄->total() === 0)
    <div class="wd-none">담긴 자료가 없습니다 — 설정 › 위드웍스 자료 가져오기에서 먼저 가져오십시오.</div>
  @else
    <div class="wd-scroll">
      <table class="wd-table">
        <thead>
          <tr>
            @php
              /* 앞에 세울 칸 — 나머지는 그 뒤에 이름 차례로 선다 */
              $앞 = ['ww_id' => '위드웍스 번호', 'add_no' => '부가번호', 'reg_date' => '등록일',
                     'type' => '갈래', 'status' => '상태', 'descr' => '내용'];
              $뺄것 = ['id', 'imported_at'];
              $모든칸 = array_keys((array) $줄->first());
              $뒤 = array_values(array_diff($모든칸, array_keys($앞), $뺄것));
            @endphp
            @foreach ($앞 as $칸 => $이름)
              <th>{{ $이름 }}<small>{{ $칸 }}</small></th>
            @endforeach
            @foreach ($뒤 as $칸)
              <th>{{ $짝[$칸] ?? $칸 }}@if(isset($짝[$칸]))<small>{{ $칸 }}</small>@endif</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach ($줄 as $r)
            <tr>
              @foreach (array_keys($앞) as $칸)
                <td>{{ $r->{$칸} }}</td>
              @endforeach
              @foreach ($뒤 as $칸)
                <td>{{ $r->{$칸} }}</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @include('partials._pager', ['쪽' => $줄, '이름' => '줄'])
  @endif
</div>
@endsection
