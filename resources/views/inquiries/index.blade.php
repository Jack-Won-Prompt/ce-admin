@extends('layouts.app')

@section('title', '문의하기')
@section('page-title', '문의하기')
@section('breadcrumb', '홈 / 문의하기')

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  { selector: '.card-header', title: '문의 목록', body: '접수된 문의 목록입니다. 제목을 클릭하면 상세 내용과 답변을 확인할 수 있습니다.' },
  { selector: '.btn-primary, [onclick*="Create"], [onclick*="create"]', title: '문의 작성', body: '<b>문의 작성</b> 버튼으로 새 문의를 등록합니다. 답변은 이메일 또는 이 화면에서 확인할 수 있습니다.' },
];
</script>
@endpush

@section('content')
<div style="max-width:960px;">

  {{-- 상단 액션 --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <select name="status" class="form-control form-select" style="width:120px;">
        <option value="">전체 상태</option>
        <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>답변 대기</option>
        <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>답변 완료</option>
      </select>
      <select name="category" class="form-control form-select" style="width:130px;">
        <option value="">전체 분류</option>
        <option value="general"   {{ request('category') === 'general'   ? 'selected' : '' }}>일반</option>
        <option value="technical" {{ request('category') === 'technical' ? 'selected' : '' }}>기술</option>
        <option value="billing"   {{ request('category') === 'billing'   ? 'selected' : '' }}>청구/결제</option>
        <option value="other"     {{ request('category') === 'other'     ? 'selected' : '' }}>기타</option>
      </select>
      <div style="position:relative;">
        <i class="bx bx-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;"></i>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="제목 검색..." style="padding-left:34px;max-width:220px;">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="bx bx-search"></i> 검색</button>
      @if(request('status') || request('category') || request('search'))
        <a href="{{ route('inquiries.index') }}" class="btn btn-outline btn-sm">초기화</a>
      @endif
    </form>
    <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">
      <i class="bx bx-pencil"></i> 문의 작성
    </a>
  </div>

  {{-- 목록 --}}
  <div class="card">
    <div class="card-header">
      <i class="bx bx-support" style="font-size:18px;color:var(--primary);"></i>
      <span class="card-header-title">문의 목록</span>
      @if($inquiries->total())
        <span class="badge bg-label-primary ms-auto">{{ $inquiries->total() }}건</span>
      @endif
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:44px;">번호</th>
            <th style="width:90px;">분류</th>
            <th>제목</th>
            @if(Auth::user()->role === 'admin')
              <th style="width:90px;">작성자</th>
            @endif
            <th style="width:90px;">상태</th>
            <th style="width:100px;">작성일</th>
          </tr>
        </thead>
        <tbody>
          @forelse($inquiries as $inquiry)
          <tr>
            <td style="color:var(--text-muted);font-size:12px;">{{ $inquiry->id }}</td>
            <td>
              <span class="badge badge-secondary" style="font-size:11px;">{{ $inquiry->categoryLabel() }}</span>
            </td>
            <td>
              <a href="{{ route('inquiries.show', $inquiry) }}"
                 style="font-weight:600;color:var(--text-primary);text-decoration:none;">
                {{ $inquiry->title }}
              </a>
            </td>
            @if(Auth::user()->role === 'admin')
              <td style="font-size:12px;color:var(--text-secondary);">{{ $inquiry->user->name ?? '-' }}</td>
            @endif
            <td>
              @if($inquiry->isAnswered())
                <span class="badge badge-success"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> 답변완료</span>
              @else
                <span class="badge badge-warning"><i class="fa-solid fa-clock" style="font-size:10px;"></i> 대기중</span>
              @endif
            </td>
            <td style="font-size:12px;color:var(--text-muted);">{{ $inquiry->created_at->format('Y.m.d') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="{{ Auth::user()->role === 'admin' ? 6 : 5 }}" style="text-align:center;padding:40px;color:var(--text-muted);">
              <i class="fa-solid fa-headset" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
              등록된 문의가 없습니다.
              <div style="margin-top:12px;">
                <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">문의 작성하기</a>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- 페이지네이션 --}}
  @if($inquiries->hasPages())
    <div style="margin-top:16px;display:flex;justify-content:center;">
      {{ $inquiries->appends(request()->query())->links() }}
    </div>
  @endif

</div>
@endsection
