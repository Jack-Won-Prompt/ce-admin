@if ($paginator->hasPages())
<nav style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:4px 0;">

  {{-- 건수 표시 --}}
  <div style="font-size:12.5px;color:var(--text-muted);">
    @if ($paginator->firstItem())
      <strong style="color:var(--text-primary);">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</strong>
      / 총 <strong style="color:var(--text-primary);">{{ number_format($paginator->total()) }}</strong>건
    @else
      총 <strong style="color:var(--text-primary);">{{ number_format($paginator->total()) }}</strong>건
    @endif
  </div>

  {{-- 페이지 버튼 --}}
  <div style="display:flex;gap:4px;align-items:center;">

    {{-- 이전 --}}
    @if ($paginator->onFirstPage())
      <span class="pg-btn disabled"><i class="bx bx-chevron-left"></i></span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-btn">
        <i class="bx bx-chevron-left"></i>
      </a>
    @endif

    {{-- 페이지 번호 --}}
    @foreach ($elements as $element)
      @if (is_string($element))
        <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;font-size:13px;color:var(--text-muted);">
          {{ $element }}
        </span>
      @endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="pg-btn active">{{ $page }}</span>
          @else
            <a href="{{ $url }}" class="pg-btn">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    {{-- 다음 --}}
    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-btn">
        <i class="bx bx-chevron-right"></i>
      </a>
    @else
      <span class="pg-btn disabled"><i class="bx bx-chevron-right"></i></span>
    @endif
  </div>

</nav>

<style>
  .pg-btn {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:34px; height:34px; padding:0 6px;
    border-radius:6px; font-size:13px; font-weight:600;
    border:1px solid var(--border); background:#fff;
    color:var(--text-secondary); text-decoration:none;
    transition:all .18s ease; cursor:pointer;
  }
  .pg-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
  .pg-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; box-shadow:0 3px 10px rgba(27,102,245,.3); }
  .pg-btn.disabled { opacity:.45; cursor:not-allowed; pointer-events:none; }
  .pg-btn .bx { font-size:18px; line-height:1; }
</style>
@endif
