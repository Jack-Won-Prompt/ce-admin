@extends('layouts.app')

@section('title', '공지 수정')
@section('page-title', '공지 수정')
@section('breadcrumb', '홈 / 공지사항 / 수정')

@section('content')
<div style="max-width:800px;">
  <form method="POST" action="{{ route('notices.update', $notice) }}">
    @csrf
    @method('PUT')
    <div class="card">
      <div class="card-header">
        <i class="bx bx-edit" style="color:var(--primary);"></i>
        <span class="card-header-title">공지사항 수정</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">제목 <span>*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $notice->title) }}" required>
          @error('title')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">내용 <span>*</span></label>
          <textarea name="content" class="form-control" rows="14" required>{{ old('content', $notice->content) }}</textarea>
          @error('content')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:24px;padding:12px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned', $notice->is_pinned) ? 'checked' : '' }}
                   style="width:16px;height:16px;cursor:pointer;">
            <span><i class="bx bx-pin" style="color:var(--danger);margin-right:4px;font-size:12px;"></i>상단 고정</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $notice->is_active) ? 'checked' : '' }}
                   style="width:16px;height:16px;cursor:pointer;">
            <span><i class="bx bx-show" style="color:var(--success);margin-right:4px;font-size:12px;"></i>게시 중</span>
          </label>
        </div>

      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;justify-content:space-between;">
      <form method="POST" action="{{ route('notices.destroy', $notice) }}" onsubmit="return confirm('이 공지사항을 삭제하시겠습니까?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">
          <i class="bx bx-trash"></i> 삭제
        </button>
      </form>
      <div style="display:flex;gap:8px;">
        <a href="{{ route('notices.show', $notice) }}" class="btn btn-outline">취소</a>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save"></i> 저장
        </button>
      </div>
    </div>
  </form>
</div>
@endsection
