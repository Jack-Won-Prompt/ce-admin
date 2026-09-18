{{-- 쪽 넘김 — 한 쪽 100줄, 쪽 번호는 열 개씩 묶어 보여 준다 (2026-09-18 지시).

     라라벨이 딸려 주는 것을 쓰지 않는 까닭은 두 가지다. 그것은 tailwind 꼴이라 이
     화면들의 생김새와 어긋나고, 앞뒤로 몇 개를 보일지만 정할 수 있어 「1~10, 11~20」
     처럼 **묶음으로 끊어 주지** 못한다. 쪽이 천 개면 지금이 몇 번째 묶음인지가 더
     중요하다.

     $쪽   : LengthAwarePaginator
     $이름 : 줄을 세는 낱말 (「줄」·「명」)  --}}
@php
  $한묶음 = 10;
  $지금   = $쪽->currentPage();
  $끝     = $쪽->lastPage();
  $묶음   = (int) floor(($지금 - 1) / $한묶음);
  $처음쪽 = $묶음 * $한묶음 + 1;
  $끝쪽   = min($처음쪽 + $한묶음 - 1, $끝);
@endphp

@if ($쪽->total() > 0)
  <div class="pg-wrap">
    <div class="pg-sum">
      {{ number_format($쪽->total()) }}{{ $이름 ?? '줄' }} 가운데
      <b>{{ number_format($쪽->firstItem() ?? 0) }}~{{ number_format($쪽->lastItem() ?? 0) }}</b>
      · {{ number_format($지금) }} / {{ number_format($끝) }}쪽
    </div>

    @if ($끝 > 1)
      <nav class="pg">
        {{-- 맨 앞ㆍ앞 묶음 --}}
        <a class="pg-b {{ $지금 <= 1 ? 'off' : '' }}"
           href="{{ $지금 <= 1 ? 'javascript:void(0)' : $쪽->url(1) }}" title="맨 앞">«</a>
        <a class="pg-b {{ $처음쪽 <= 1 ? 'off' : '' }}"
           href="{{ $처음쪽 <= 1 ? 'javascript:void(0)' : $쪽->url($처음쪽 - 1) }}" title="앞 열 쪽">‹</a>

        @for ($i = $처음쪽; $i <= $끝쪽; $i++)
          <a class="pg-b {{ $i === $지금 ? 'on' : '' }}" href="{{ $쪽->url($i) }}">{{ $i }}</a>
        @endfor

        {{-- 뒤 묶음ㆍ맨 뒤 --}}
        <a class="pg-b {{ $끝쪽 >= $끝 ? 'off' : '' }}"
           href="{{ $끝쪽 >= $끝 ? 'javascript:void(0)' : $쪽->url($끝쪽 + 1) }}" title="뒤 열 쪽">›</a>
        <a class="pg-b {{ $지금 >= $끝 ? 'off' : '' }}"
           href="{{ $지금 >= $끝 ? 'javascript:void(0)' : $쪽->url($끝) }}" title="맨 뒤">»</a>
      </nav>
    @endif
  </div>
@endif

@once
  @push('styles')
  <style>
    .pg-wrap { display:flex; align-items:center; gap:14px; flex-wrap:wrap;
               margin-top:14px; padding-top:12px; border-top:1px solid var(--border); }
    .pg-sum  { font-size:12px; color:var(--text-muted); }
    .pg-sum b { color:var(--text-primary); font-variant-numeric:tabular-nums; }
    .pg      { display:flex; gap:4px; margin-left:auto; flex-wrap:wrap; }
    .pg-b    { min-width:30px; height:30px; padding:0 8px; display:inline-flex;
               align-items:center; justify-content:center; font-size:12px;
               border:1px solid var(--border); border-radius:var(--radius);
               background:var(--bg-card); color:var(--text-primary); text-decoration:none;
               font-variant-numeric:tabular-nums; transition:var(--transition); }
    .pg-b:hover  { border-color:var(--primary); color:var(--primary); }
    .pg-b.on     { background:var(--primary, #28798B); border-color:var(--primary, #28798B);
                   color:#fff; font-weight:700; }
    .pg-b.off    { color:var(--gray-400); pointer-events:none; }
  </style>
  @endpush
@endonce
