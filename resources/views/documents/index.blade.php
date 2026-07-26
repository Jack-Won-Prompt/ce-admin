{{-- resources/views/documents/index.blade.php --}}
@extends('layouts.app')

@section('title', '서류 관리')
@section('page-title', '서류 관리')
@section('breadcrumb', '홈 / 서류 관리')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=2">
<style>
  .type-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .type-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .type-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .type-tab.active { border-color: var(--primary); background: var(--primary); color: #fff; }
  .type-tab .cnt {
    min-width: 20px; padding: 0 5px; height: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 20px; font-size: 10.5px; font-weight: 700;
    background: rgba(255,255,255,.25);
  }
  .type-tab:not(.active) .cnt { background: var(--border-light); color: var(--text-muted); }

  .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
  .filename-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
  .table-scroll-wrap { overflow-x: auto; }
  .table-scroll-wrap thead th { position: sticky; top: 0; z-index: 5; background: var(--bg); }
</style>
@endpush

@section('content')

@php
  $types = [
    'consent'      => '위임동의서',
    'delegation'   => '요양비위임장',
    'fax'          => '팩스통합본',
    'cash_receipt' => '현금영수증',
    'tax_invoice'  => '세금계산서',
  ];
  $totalAll = $typeCounts->sum();
  $curType  = request('type');
@endphp

{{-- ── 유형 탭 ── --}}
<div class="type-tabs">
  <a href="{{ route('documents.index', request()->except('type', 'page')) }}"
     class="type-tab {{ !$curType ? 'active' : '' }}">
    전체 <span class="cnt">{{ $totalAll }}</span>
  </a>
  @foreach($types as $key => $label)
    <a href="{{ route('documents.index', array_merge(request()->except('type','page'), ['type' => $key])) }}"
       class="type-tab {{ $curType === $key ? 'active' : '' }}">
      {{ $label }}
      @if(($typeCounts[$key] ?? 0) > 0)
        <span class="cnt">{{ $typeCounts[$key] }}</span>
      @endif
    </a>
  @endforeach
</div>

{{-- ── 검색 필터 ── --}}
<form method="GET" action="{{ route('documents.index') }}" class="filter-bar mb-4">
  @if($curType)
    <input type="hidden" name="type" value="{{ $curType }}">
  @endif
  <input type="text" name="q" value="{{ request('q') }}" class="form-control"
         placeholder="파일명 · 환자명" style="width:220px;">
  <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="width:145px;">
  <span style="color:var(--text-muted);font-size:13px;">~</span>
  <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="width:145px;">
  <select name="per_page" class="form-control" style="width:90px;" onchange="this.form.submit()">
    @foreach([10,20,50,100] as $n)
      <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }}건</option>
    @endforeach
  </select>
  <button type="submit" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-magnifying-glass"></i> 검색
  </button>
  @if(request('q') || request('date_from') || request('date_to'))
    <a href="{{ route('documents.index', array_filter(['type' => $curType])) }}"
       class="btn btn-outline btn-sm">초기화</a>
  @endif
</form>

{{-- ── 서류 목록 (wwGrid) ── --}}
<div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
  <i class="bx bx-folder-open" style="font-size:18px;color:var(--primary);"></i>
  <span class="card-header-title">서류 목록</span>
  <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}건</span>
</div>
<div id="documentGrid"></div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=2"></script>
<script>
(function () {
  const grid = new wwGrid({
    el: document.getElementById('documentGrid'),
    height: 620, editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '유형',       name: 'type',      width: 110, sortable: true, align: 'center' },
      { header: '생성유형',   name: 'source',    width: 120, sortable: true },
      { header: '환자명',     name: 'patient',   width: 100, sortable: true },
      { header: '처방번호',   name: 'rx_number', width: 150, sortable: true },
      { header: '파일명',     name: 'filename',  width: 260 },
      { header: '생성자',     name: 'creator',   width: 100, sortable: true },
      { header: '생성일',     name: 'created',   width: 140, sortable: true },
      { header: '다운로드경로', name: 'download',  width: 260 },
    ],
    data: @json($gridData),
  });
  window.__documentGrid = grid;
})();
</script>
@endpush
