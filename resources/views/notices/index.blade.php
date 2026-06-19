@extends('layouts.app')

@section('title', '공지사항')
@section('page-title', '공지사항')
@section('breadcrumb', '홈 / 공지사항')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.card-header', title: '공지사항 목록', body: '관리자가 올린 공지사항 목록입니다. 제목을 클릭하면 내용을 확인할 수 있습니다.' },
  { selector: '.btn-primary, [href*="create"]', title: '공지 작성', body: '관리자는 <b>공지 작성</b> 버튼으로 새 공지사항을 등록할 수 있습니다.' },
];
</script>
@endpush

@section('content')
<div style="max-width:900px;">

  {{-- 헤더 액션 --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
      <div style="position:relative;flex:1;max-width:320px;">
        <i class="bx bx-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 또는 내용 검색..." style="padding-left:34px;">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="bx bx-search"></i> 검색</button>
      @if(request('search'))
        <a href="{{ route('notices.index') }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
    @if(Auth::user()->role === 'admin')
      <a href="{{ route('notices.create') }}" class="btn btn-primary btn-sm">
        <i class="bx bx-plus"></i> 공지 등록
      </a>
    @endif
  </div>

  {{-- 목록 --}}
  <div class="card">
    <div class="card-header">
      <i class="bx bx-bell" style="font-size:18px;color:var(--primary);"></i>
      <span class="card-header-title">공지사항 목록</span>
      @if($notices->total())
        <span class="badge bg-label-primary ms-auto">{{ $notices->total() }}건</span>
      @endif
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:44px;">번호</th>
            <th>제목</th>
            <th style="width:90px;">작성자</th>
            <th style="width:100px;">날짜</th>
            <th style="width:60px;text-align:right;">조회</th>
          </tr>
        </thead>
        <tbody>
          @forelse($notices as $notice)
          <tr style="{{ $notice->is_pinned ? 'background:rgba(27,102,245,.04);' : '' }}">
            <td style="color:var(--text-muted);font-size:12px;">
              @if($notice->is_pinned)
                <span style="color:var(--danger);font-weight:700;"><i class="bx bx-pin" style="font-size:14px;"></i></span>
              @else
                {{ $notice->id }}
              @endif
            </td>
            <td>
              <a href="{{ route('notices.show', $notice) }}" style="font-weight:600;color:var(--text-primary);text-decoration:none;">
                @if($notice->is_pinned)
                  <span class="badge badge-danger" style="margin-right:6px;font-size:10px;">공지</span>
                @endif
                {{ $notice->title }}
              </a>
            </td>
            <td style="font-size:12px;color:var(--text-secondary);">{{ $notice->author->name ?? '-' }}</td>
            <td style="font-size:12px;color:var(--text-muted);">{{ $notice->created_at->format('Y.m.d') }}</td>
            <td style="font-size:12px;color:var(--text-muted);text-align:right;">{{ number_format($notice->views) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="5" style="text-align:center;padding:48px;color:var(--text-muted);">
              <i class="bx bx-bell-off" style="font-size:36px;opacity:.3;display:block;margin-bottom:10px;"></i>
              등록된 공지사항이 없습니다.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- 페이지네이션 --}}
  @if($notices->hasPages())
    <div style="margin-top:16px;display:flex;justify-content:center;">
      {{ $notices->links() }}
    </div>
  @endif

</div>
@endsection
