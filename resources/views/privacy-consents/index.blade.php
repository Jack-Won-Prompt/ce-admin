@extends('layouts.app')

@section('title', '개인정보동의')
@section('page-title', '개인정보 수집·이용 동의')
@section('breadcrumb', '홈 / 개인정보동의')

@push('styles')
<style>
  .pc-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
  .pc-tab { padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:600;
    border:1.5px solid var(--border); background:#fff; color:var(--text-secondary);
    text-decoration:none; transition:var(--transition); }
  .pc-tab:hover { border-color:var(--primary); color:var(--primary); }
  .pc-tab.active { border-color:var(--primary); background:var(--primary); color:#fff; }
  .pc-tab .cnt { opacity:.75; margin-left:4px; font-weight:700; }
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:end; margin-bottom:16px;
    background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:14px; }
  .filter-bar .fg { display:flex; flex-direction:column; gap:4px; }
  .filter-bar label { font-size:11px; font-weight:700; color:var(--text-muted); }
  .filter-bar input { padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:13px; }
  .badge-type { display:inline-block; padding:2px 9px; border-radius:12px; font-size:11px; font-weight:700; }
  .badge-type.catheter { background:#e0f2fe; color:#0369a1; }
  .badge-type.stoma { background:#f0fdf4; color:#15803d; }
  .pc-table { width:100%; border-collapse:collapse; background:#fff; }
  .pc-table th, .pc-table td { padding:11px 12px; border-bottom:1px solid var(--border); font-size:13px; text-align:left; }
  .pc-table th { background:#f8fafb; font-weight:700; color:var(--text-secondary); font-size:12px; }
  .pc-table tr:hover td { background:#fbfcfe; }
  .req-ok { color:var(--success); font-weight:700; }
  .req-no { color:var(--danger); font-weight:700; }
</style>
@endpush

@section('content')
<div class="pc-tabs">
  @php $mk = fn($t)=>request('type',($t==='all'?'all':null))===$t || (request('type')===null && $t==='all'); @endphp
  <a href="{{ route('privacy-consents.index') }}" class="pc-tab {{ (request('type','all')==='all')?'active':'' }}">전체 <span class="cnt">{{ $counts['all'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'catheter']) }}" class="pc-tab {{ request('type')==='catheter'?'active':'' }}">카테터 <span class="cnt">{{ $counts['catheter'] }}</span></a>
  <a href="{{ route('privacy-consents.index',['type'=>'stoma']) }}" class="pc-tab {{ request('type')==='stoma'?'active':'' }}">장루 <span class="cnt">{{ $counts['stoma'] }}</span></a>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="type" value="{{ request('type','all') }}">
  <div class="fg"><label>검색 (성명/연락처/이메일)</label><input type="text" name="search" value="{{ $search }}" placeholder="검색어"></div>
  <div class="fg"><label>시작일</label><input type="date" name="from" value="{{ $from }}"></div>
  <div class="fg"><label>종료일</label><input type="date" name="to" value="{{ $to }}"></div>
  <button type="submit" class="btn btn-primary btn-sm">조회</button>
  <a href="{{ route('privacy-consents.export', request()->query()) }}" class="btn btn-outline btn-sm">
    <i class="bx bx-download"></i> 엑셀(CSV) 다운로드
  </a>
</form>

<div class="card" style="overflow-x:auto;">
  <table class="pc-table">
    <thead>
      <tr>
        <th>유형</th><th>성명</th><th>연락처</th><th>이메일</th><th>주소</th>
        <th style="text-align:center;">필수동의</th><th>마케팅</th><th>제출일시</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td><span class="badge-type {{ $r->type }}">{{ $r->type_label }}</span></td>
          <td style="font-weight:700;">{{ $r->name }}</td>
          <td style="font-family:monospace;">{{ $r->phone }}</td>
          <td style="color:var(--text-muted);">{{ $r->email ?: '-' }}</td>
          <td style="color:var(--text-muted);font-size:12px;">{{ \Illuminate\Support\Str::limit($r->full_address ?: '-', 24) }}</td>
          <td style="text-align:center;">
            @if($r->required_agreed)<span class="req-ok">완료</span>@else<span class="req-no">미완</span>@endif
          </td>
          <td>{{ $r->agree_marketing === '동의함' ? '동의' : '-' }}</td>
          <td style="font-size:12px;color:var(--text-muted);">{{ $r->submitted_at?->format('Y-m-d H:i') }}</td>
          <td><a href="{{ route('privacy-consents.show', $r) }}" class="btn btn-outline btn-sm">상세</a></td>
        </tr>
      @empty
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:36px;">
          <i class="bx bx-file" style="font-size:26px;display:block;margin-bottom:8px;opacity:.4;"></i>
          작성된 동의서가 없습니다.
        </td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div style="margin-top:16px;">{{ $rows->links() }}</div>
@endsection
