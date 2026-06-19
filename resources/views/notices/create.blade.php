@extends('layouts.app')

@section('title', '공지 등록')
@section('page-title', '공지 등록')
@section('breadcrumb', '홈 / 공지사항 / 등록')

@section('content')
<div style="max-width:800px;">
  <form method="POST" action="{{ route('notices.store') }}">
    @csrf
    <div class="card">
      <div class="card-header">
        <i class="bx bx-bell-plus" style="font-size:18px;color:var(--primary);"></i>
        <span class="card-header-title">새 공지사항 작성</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">제목 <span>*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="공지사항 제목을 입력하세요" required>
          @error('title')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">내용 <span>*</span></label>
          <textarea name="content" class="form-control" rows="14" placeholder="공지사항 내용을 입력하세요" required>{{ old('content') }}</textarea>
          @error('content')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:24px;padding:12px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }}
                   style="width:16px;height:16px;cursor:pointer;">
            <span><i class="bx bx-pin" style="color:var(--danger);margin-right:4px;font-size:14px;"></i>상단 고정</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                   style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);">
            <span><i class="bx bx-show" style="color:var(--success);margin-right:4px;font-size:14px;"></i>즉시 게시</span>
          </label>
        </div>

      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
      <a href="{{ route('notices.index') }}" class="btn btn-outline">취소</a>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-save"></i> 등록
      </button>
    </div>
  </form>
</div>
@endsection
