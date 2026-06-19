@extends('layouts.app')

@section('title', $notice->title)
@section('page-title', '공지사항')
@section('breadcrumb', '홈 / 공지사항 / 상세')

@section('content')
<div style="max-width:900px;">

  <div class="card">
    {{-- 공지 헤더 --}}
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <div style="flex:1;">
          @if($notice->is_pinned)
            <span class="badge badge-danger" style="margin-bottom:8px;">공지</span>
          @endif
          <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);line-height:1.4;">
            {{ $notice->title }}
          </h2>
          <div style="display:flex;gap:16px;margin-top:10px;font-size:12px;color:var(--text-muted);">
            <span><i class="bx bx-user" style="margin-right:3px;font-size:14px;"></i>{{ $notice->author->name ?? '-' }}</span>
            <span><i class="bx bx-calendar" style="margin-right:3px;font-size:14px;"></i>{{ $notice->created_at->format('Y년 m월 d일 H:i') }}</span>
            <span><i class="bx bx-show" style="margin-right:3px;font-size:14px;"></i>{{ number_format($notice->views) }}회</span>
          </div>
        </div>
        @if(Auth::user()->role === 'admin')
          <a href="{{ route('notices.edit', $notice) }}" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-pen"></i> 수정
          </a>
        @endif
      </div>
    </div>

    {{-- 본문 --}}
    <div style="padding:24px;min-height:200px;font-size:14px;line-height:1.8;color:var(--text-primary);white-space:pre-wrap;">{{ $notice->content }}</div>
  </div>

  {{-- 이전/다음 --}}
  <div class="card mt-4" style="overflow:hidden;">
    @if($next)
    <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;border-bottom:1px solid var(--border-light);">
      <span style="font-size:11px;font-weight:700;color:var(--text-muted);width:36px;flex-shrink:0;">다음</span>
      <a href="{{ route('notices.show', $next) }}" style="font-size:13px;font-weight:500;color:var(--text-primary);text-decoration:none;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        {{ $next->title }}
      </a>
      <span style="font-size:12px;color:var(--text-muted);">{{ $next->created_at->format('Y.m.d') }}</span>
    </div>
    @endif
    @if($prev)
    <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;">
      <span style="font-size:11px;font-weight:700;color:var(--text-muted);width:36px;flex-shrink:0;">이전</span>
      <a href="{{ route('notices.show', $prev) }}" style="font-size:13px;font-weight:500;color:var(--text-primary);text-decoration:none;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        {{ $prev->title }}
      </a>
      <span style="font-size:12px;color:var(--text-muted);">{{ $prev->created_at->format('Y.m.d') }}</span>
    </div>
    @endif
    @if(!$prev && !$next)
    <div style="padding:16px 18px;font-size:13px;color:var(--text-muted);">이전/다음 공지사항이 없습니다.</div>
    @endif
  </div>

  <div style="margin-top:14px;">
    <a href="{{ route('notices.index') }}" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-list"></i> 목록으로
    </a>
  </div>

</div>
@endsection
