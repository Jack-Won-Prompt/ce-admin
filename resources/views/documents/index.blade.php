{{-- resources/views/documents/index.blade.php --}}
@extends('layouts.app')

@section('title', '서류 관리')
@section('page-title', '서류 관리')
@section('breadcrumb', '홈 / 서류 관리')

@push('styles')
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

{{-- ── 서류 테이블 ── --}}
<div class="card">
  <div class="card-header">
    <i class="bx bx-folder-open" style="font-size:18px;color:var(--primary);"></i>
    <span class="card-header-title">서류 목록</span>
    <span class="badge bg-label-primary ms-auto">전체 {{ $documents->total() }}건</span>
  </div>
  <div class="table-scroll-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>유형</th>
          <th>생성유형</th>
          <th>환자명</th>
          <th>처방번호</th>
          <th>파일명</th>
          <th>생성자</th>
          <th>생성일</th>
          <th style="text-align:center;">미리보기</th>
          <th style="text-align:center;">다운로드</th>
        </tr>
      </thead>
      <tbody>
        @forelse($documents as $doc)
          @php
            $typeColors = ['consent' => 'primary', 'fax' => 'warning', 'cash_receipt' => 'success'];
            $color = $typeColors[$doc->type] ?? 'secondary';
          @endphp
          <tr>
            <td style="color:var(--text-muted);font-size:12px;">{{ $doc->id }}</td>
            <td>
              <span class="badge badge-{{ $color }}">{{ $doc->typeLabel() }}</span>
            </td>
            <td style="font-size:11px;color:var(--text-muted);">
              {{ $doc->sourceLabel() }}
            </td>
            <td style="font-weight:600;">
              {{ $doc->prescription?->patient?->name ?? '-' }}
            </td>
            <td>
              @if($doc->prescription)
                <a href="{{ route('prescriptions.show', $doc->prescription) }}"
                   style="color:var(--primary);font-size:12px;font-weight:600;">
                  {{ $doc->prescription->rx_number }}
                </a>
              @else
                -
              @endif
            </td>
            <td>
              <div class="filename-cell" title="{{ $doc->original_filename }}">
                {{ $doc->original_filename }}
              </div>
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              {{ $doc->creator?->name ?? '-' }}
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              {{ $doc->created_at->format('Y-m-d H:i') }}
            </td>
            <td style="text-align:center;">
              <button type="button"
                      class="btn btn-outline btn-sm btn-preview"
                      data-url="{{ route('documents.preview', $doc) }}"
                      data-name="{{ $doc->original_filename }}"
                      title="{{ $doc->original_filename }}">
                <i class="bx bx-show"></i> 미리보기
              </button>
            </td>
            <td style="text-align:center;">
              <a href="{{ route('documents.download', $doc) }}"
                 class="btn btn-outline btn-sm"
                 title="{{ $doc->original_filename }}">
                <i class="bx bx-download"></i> 다운로드
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="bx bx-folder-open" style="font-size:32px;display:block;margin-bottom:8px;"></i>
              저장된 서류가 없습니다.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($documents->hasPages())
    <div style="padding:14px 18px;border-top:1px solid var(--border);">
      {{ $documents->withQueryString()->links() }}
    </div>
  @endif
</div>

{{-- ── PDF 미리보기 모달 ── --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:860px;">
    <div class="modal-content" style="border-radius:12px;overflow:hidden;">
      <div class="modal-header" style="border-bottom:1px solid var(--border);padding:14px 20px;">
        <h6 class="modal-title" id="previewModalLabel" style="font-weight:600;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:680px;"></h6>
        <div style="display:flex;gap:8px;align-items:center;margin-left:auto;">
          <a id="previewDownloadBtn" href="#" class="btn btn-outline btn-sm" download>
            <i class="bx bx-download"></i> 다운로드
          </a>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body" style="padding:0;background:#525659;min-height:580px;display:flex;align-items:center;justify-content:center;">
        <div id="previewLoading" style="color:#fff;font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px;">
          <div class="spinner-border text-light" role="status" style="width:2rem;height:2rem;"></div>
          <span>PDF 로딩 중...</span>
        </div>
        <iframe id="previewFrame" src="" style="width:100%;height:680px;border:none;display:none;"
                allowfullscreen></iframe>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.btn-preview').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const url  = this.dataset.url;
        const name = this.dataset.name;
        const frame   = document.getElementById('previewFrame');
        const loading = document.getElementById('previewLoading');
        const label   = document.getElementById('previewModalLabel');
        const dlBtn   = document.getElementById('previewDownloadBtn');

        label.textContent = name;
        dlBtn.href = url.replace('/preview', '/download');
        frame.style.display  = 'none';
        loading.style.display = 'flex';
        frame.src = '';

        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();

        frame.onload = function() {
            loading.style.display = 'none';
            frame.style.display   = 'block';
        };
        frame.src = url;
    });
});

document.getElementById('previewModal').addEventListener('hidden.bs.modal', function() {
    const frame = document.getElementById('previewFrame');
    frame.src = '';
    frame.style.display = 'none';
    document.getElementById('previewLoading').style.display = 'flex';
});
</script>
@endpush

@endsection
