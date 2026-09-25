{{-- 설정 — 앱의 settings_screen (2026-09-25 지시). 계정ㆍ서비스ㆍ로그아웃. --}}
@extends('layouts.mobile')
@section('title', '설정')

@section('body')
  <div class="m-card" style="display:flex; align-items:center; gap:12px;">
    <div style="width:48px; height:48px; border-radius:16px; background:var(--m-primary-l);
                display:flex; align-items:center; justify-content:center;">
      <i class="bx bxs-user" style="font-size:24px; color:var(--m-primary);"></i>
    </div>
    <div style="flex:1; min-width:0;">
      <b id="meName" style="font-size:15.5px;">…</b>
      <div id="meMail" style="font-size:12.5px; color:var(--m-sub);"></div>
    </div>
  </div>

  <div class="m-card" style="padding:4px 14px;">
    <div style="font-size:12px; font-weight:700; color:var(--m-mute); padding:10px 0 4px;">서비스</div>
    @foreach ([
      ['bx-package',  '주문 목록',  route('m.orders')],
      ['bx-bell',     '알림 이력',  route('m.notifications')],
      ['bx-bullhorn', '공지사항',   route('m.notices')],
      ['bx-help-circle', '문의하기', route('m.inquiries')],
    ] as [$아이콘, $이름, $길])
      <a href="{{ $길 }}" style="display:flex; align-items:center; gap:12px; padding:13px 0; border-top:1px solid var(--m-line);">
        <i class="bx {{ $아이콘 }}" style="font-size:21px; color:var(--m-sub);"></i>
        <span style="flex:1; font-size:14.5px; font-weight:600;">{{ $이름 }}</span>
        <i class="bx bx-chevron-right" style="color:var(--m-mute);"></i>
      </a>
    @endforeach
  </div>

  <div class="m-card" style="padding:4px 14px;">
    <div style="font-size:12px; font-weight:700; color:var(--m-mute); padding:10px 0 4px;">계정</div>
    <a href="{{ url('/') }}" style="display:flex; align-items:center; gap:12px; padding:13px 0; border-top:1px solid var(--m-line);">
      <i class="bx bx-desktop" style="font-size:21px; color:var(--m-sub);"></i>
      <span style="flex:1; font-size:14.5px; font-weight:600;">웹 관리자 화면</span>
      <i class="bx bx-chevron-right" style="color:var(--m-mute);"></i>
    </a>
    <form method="POST" action="{{ route('logout') }}" style="border-top:1px solid var(--m-line);">
      @csrf
      <button type="submit" style="display:flex; align-items:center; gap:12px; padding:13px 0; width:100%;
              background:none; border:0; cursor:pointer; color:var(--m-danger);">
        <i class="bx bx-log-out" style="font-size:21px;"></i>
        <span style="flex:1; font-size:14.5px; font-weight:700; text-align:left;">로그아웃</span>
      </button>
    </form>
  </div>

  <div style="text-align:center; font-size:11.5px; color:var(--m-mute); padding:10px 0 24px;">
    CE Admin · 모바일 웹
  </div>
@endsection

@push('scripts')
<script>
  mApi('/auth/me').then(d => {
    const u = d.user || d.data || {};
    document.getElementById('meName').textContent = u.name || '-';
    document.getElementById('meMail').textContent = u.email || '';
  }).catch(() => {});
</script>
@endpush
