@extends('layouts.app')

@section('title', $inquiry->title)
@section('page-title', '문의 상세')
@section('breadcrumb', '홈 / 문의하기 / 상세')

@section('content')
<div style="max-width:900px;">

  {{-- 문의 내용 --}}
  <div class="card">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <span class="badge badge-secondary">{{ $inquiry->categoryLabel() }}</span>
            @if($inquiry->isAnswered())
              <span class="badge badge-success"><i class="bx bx-check-circle" style="font-size:10px;"></i> 답변완료</span>
            @else
              <span class="badge badge-warning"><i class="bx bx-time" style="font-size:10px;"></i> 답변 대기중</span>
            @endif
          </div>
          <h2 style="font-size:17px;font-weight:700;color:var(--text-primary);line-height:1.4;">
            {{ $inquiry->title }}
          </h2>
          <div style="display:flex;gap:16px;margin-top:10px;font-size:12px;color:var(--text-muted);">
            <span><i class="bx bx-user" style="margin-right:4px;"></i>{{ $inquiry->user->name ?? '-' }}</span>
            <span><i class="bx bx-calendar" style="margin-right:4px;"></i>{{ $inquiry->created_at->format('Y년 m월 d일 H:i') }}</span>
          </div>
        </div>
        @if(Auth::user()->role === 'admin' || $inquiry->user_id === Auth::id())
          <form method="POST" action="{{ route('inquiries.destroy', $inquiry) }}"
                onsubmit="return confirm('이 문의를 삭제하시겠습니까?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">
              <i class="bx bx-trash"></i> 삭제
            </button>
          </form>
        @endif
      </div>
    </div>

    {{-- 문의 본문 --}}
    <div style="padding:24px;border-bottom:{{ $inquiry->isAnswered() ? '2px solid var(--border)' : 'none' }};font-size:14px;line-height:1.8;color:var(--text-primary);white-space:pre-wrap;">{{ $inquiry->content }}</div>

    {{-- 답변 영역 --}}
    @if($inquiry->isAnswered())
    <div style="padding:20px 24px;background:var(--success-light);">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--success);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
          <i class="bx bx-headphone"></i>
        </div>
        <div>
          <div style="font-size:13px;font-weight:700;color:var(--success);">답변</div>
          <div style="font-size:11px;color:var(--text-muted);">
            {{ $inquiry->answeredBy->name ?? '관리자' }}
            · {{ $inquiry->answered_at?->format('Y년 m월 d일 H:i') }}
          </div>
        </div>
      </div>
      <div style="font-size:14px;line-height:1.8;color:var(--text-primary);white-space:pre-wrap;padding-left:40px;">{{ $inquiry->answer }}</div>
    </div>
    @endif
  </div>

  {{-- 관리자 답변 폼 --}}
  @if(Auth::user()->role === 'admin')
  <div class="card mt-4">
    <div class="card-header">
      <i class="bx bx-reply" style="color:var(--primary);"></i>
      <span class="card-header-title">{{ $inquiry->isAnswered() ? '답변 수정' : '답변 작성' }}</span>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('inquiries.reply', $inquiry) }}">
        @csrf
        <div class="form-group">
          <textarea name="answer" class="form-control" rows="7"
                    placeholder="답변 내용을 입력하세요..." required>{{ old('answer', $inquiry->answer) }}</textarea>
          @error('answer')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-success">
            <i class="bx bx-send"></i>
            {{ $inquiry->isAnswered() ? '답변 수정' : '답변 등록' }}
          </button>
        </div>
      </form>
    </div>
  </div>
  @elseif($inquiry->isPending())
  <div style="margin-top:14px;padding:14px 18px;background:var(--warning-light);border:1px solid #fde68a;border-radius:var(--radius);font-size:13px;color:var(--warning);">
    <i class="bx bx-time" style="margin-right:8px;"></i>
    답변을 준비 중입니다. 영업일 기준 1~2일 내에 답변드리겠습니다.
  </div>
  @endif

  <div style="margin-top:14px;">
    <a href="{{ route('inquiries.index') }}" class="btn btn-outline btn-sm">
      <i class="bx bx-list-ul"></i> 목록으로
    </a>
  </div>

</div>
@endsection
