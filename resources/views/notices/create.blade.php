@extends('layouts.app')

@section('title', '공지 등록')
@section('page-title', '공지 등록')
@section('breadcrumb', '홈 - 공지사항 - 등록')

@push('styles')
<style>
  /* 하단 채우기 — 원래 카드가 595 에서 끝나 본문 바닥(1184)까지 회색이 589 드러났다.
     단추 줄은 카드 밖(아래)에 있어 카드만 늘려서는 흰 것이 46 모자란다(margin 14 + 줄 32).
     껍데기를 카드와 같은 흰 판(bg-card · r12, 카드에 테두리·그림자가 없어 이음매가 안 보인다)으로
     칠해 단추 줄까지 흰 판 안에 들어오고 판이 본문 바닥에 닿는다.
     아래 안여백 16 은 단추가 판 끝에 붙지 않게 두는 자리다.
     늘어나는 것은 카드의 빈 아래쪽이다 — textarea 는 rows=14(293) 그대로 둔다. */
  .notice-shell { background: var(--bg-card); border-radius: var(--radius-lg); padding-bottom: 16px; }
</style>
@endpush

@section('content')
<div class="notice-shell fill-rest fill-col" style="max-width:800px;">
  <form method="POST" action="{{ route('notices.store') }}" class="fill-rest fill-col">
    @csrf
    <div class="card fill-rest fill-col">
      <div class="card-header">
        <i class="bx bx-bell-plus" style="font-size:18px;color:var(--primary);"></i>
        <span class="card-header-title">새 공지사항 작성</span>
      </div>
      <div class="card-body fill-rest fill-col" style="display:flex;flex-direction:column;gap:16px;">

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
