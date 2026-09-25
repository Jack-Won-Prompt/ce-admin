{{-- 설정 — 앱의 settings_screen (2026-09-25 지시).

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 밑글 「앱 설정 및 계정 관리」
       · 프로필 동그라미는 이름 첫 글자 (앱과 같다)
       · 서비스 차례를 앱과 같이 공지사항 · 알림 이력 · 문의하기로 두고,
         앱에 없는 「주문 목록」ㆍ「웹 관리자 화면」은 걷었다
         (앱의 order_list_screen 은 어디에서도 열리지 않는다 — 길이 없다)
       · 공지사항에 안 읽은 수 뱃지 (99 넘으면 99+)
       · 「앱 정보」 구역
       · 로그아웃은 물어보고 나간다 --}}
@extends('layouts.mobile')
@section('title', '설정')
@section('subtitle', '앱 설정 및 계정 관리')

@section('body')
  {{-- 프로필 --}}
  <div class="m-card" style="display:flex; align-items:center; gap:14px;">
    <div id="meAvatar"
         style="width:52px; height:52px; border-radius:16px; flex:0 0 52px;
                background:linear-gradient(135deg,#1565C0,#0288D1); color:#fff;
                display:flex; align-items:center; justify-content:center;
                font-size:22px; font-weight:800;">?</div>
    <div style="flex:1; min-width:0;">
      <b id="meName" style="font-size:16px; font-weight:800;">-</b>
      <div id="meMail" style="font-size:13px; color:var(--m-mute); margin-top:3px;">-</div>
    </div>
  </div>

  {{-- 서비스 --}}
  <div class="m-sec"><span class="bar" style="background:linear-gradient(135deg,#1565C0,#0288D1);"></span>서비스</div>
  <div class="m-card" style="padding:0;">
    <a class="m-menu" href="{{ route('m.notices') }}">
      <span class="ico" style="background:linear-gradient(135deg,#0277BD,#0288D1);"><i class="bx bx-broadcast"></i></span>
      <span class="ttl">공지사항</span>
      <span class="m-dot" id="noticeUnread" style="display:none;"></span>
      <i class="bx bx-chevron-right ch"></i>
    </a>
    <div class="m-menu-line"></div>
    <a class="m-menu" href="{{ route('m.notifications') }}">
      <span class="ico" style="background:linear-gradient(135deg,#0288D1,#26C6DA);"><i class="bx bx-bell"></i></span>
      <span class="ttl">알림 이력</span>
      <i class="bx bx-chevron-right ch"></i>
    </a>
    <div class="m-menu-line"></div>
    <a class="m-menu" href="{{ route('m.inquiries') }}">
      <span class="ico" style="background:linear-gradient(135deg,#26C6DA,#4DD0E1);"><i class="bx bx-support"></i></span>
      <span class="ttl">문의하기</span>
      <i class="bx bx-chevron-right ch"></i>
    </a>
  </div>

  {{-- 앱 정보 --}}
  <div class="m-sec"><span class="bar" style="background:linear-gradient(135deg,#0288D1,#26C6DA);"></span>앱 정보</div>
  <div class="m-card" style="display:flex; align-items:center; gap:14px;">
    <div style="width:46px; height:46px; border-radius:14px; flex:0 0 46px;
                background:linear-gradient(135deg,#1565C0,#0288D1); color:#fff;
                display:flex; align-items:center; justify-content:center; font-size:22px;">
      <i class="bx bx-plus-medical"></i>
    </div>
    <div>
      <b style="font-size:14px; font-weight:700;">Coloplast CE Admin</b>
      {{-- 앱은 스스로의 판을 적는다. 모바일 웹은 늘 서버와 같은 판이므로,
           환경 설정 ▸ 모바일 앱 ▸ 최신 판을 적는다. --}}
      <div style="font-size:12px; color:var(--m-mute); margin-top:2px;">v{{ ltrim(trim((string) config('mobile.latest_version')) ?: '1.3.2', 'vV') }}</div>
    </div>
  </div>

  {{-- 계정 --}}
  <div class="m-sec"><span class="bar" style="background:linear-gradient(135deg,#C62828,#E53935);"></span>계정</div>
  <div class="m-card" style="padding:0;">
    <form method="POST" action="{{ route('logout') }}" id="outForm">
      @csrf
      <button type="submit" class="m-menu" style="width:100%; background:none; border:0; cursor:pointer; font-family:inherit;"
              onclick="return mConfirmClick(this, '로그아웃 하시겠습니까?', { title:'로그아웃', ok:'로그아웃' })">
        <span class="ico" style="background:linear-gradient(135deg,#C62828,#E53935);"><i class="bx bx-log-out"></i></span>
        <span class="ttl" style="color:var(--m-danger); text-align:left;">로그아웃</span>
        <i class="bx bx-chevron-right ch"></i>
      </button>
    </form>
  </div>

  <div style="height:16px;"></div>
@endsection

@push('scripts')
<style>
  .m-sec { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:800;
           color:#0D1B3E; margin:20px 2px 10px; }
  .m-sec .bar { width:4px; height:14px; border-radius:2px; display:inline-block; }
  .m-menu { display:flex; align-items:center; gap:14px; padding:14px 16px; }
  .m-menu .ico { width:38px; height:38px; border-radius:11px; flex:0 0 38px; color:#fff;
                 display:flex; align-items:center; justify-content:center; font-size:18px; }
  .m-menu .ttl { flex:1; font-size:14px; font-weight:600; color:#0D1B3E; }
  .m-menu .ch  { font-size:20px; color:#90A4AE; }
  .m-menu-line { height:1px; background:#E0E6F0; margin-left:68px; margin-right:16px; }
  .m-dot { background:var(--m-danger); color:#fff; font-size:11px; font-weight:700;
           padding:3px 8px; border-radius:10px; }
</style>
<script>
  mApi('/auth/me').then(d => {
    const u = d.user || d.data || {};
    const 이름 = u.name || '';
    document.getElementById('meName').textContent = 이름 || '-';
    document.getElementById('meMail').textContent = u.email || '-';
    /* 앱은 이름 첫 글자를 보인다 */
    document.getElementById('meAvatar').textContent = 이름 ? 이름.substring(0, 1) : '?';
  }).catch(() => {});

  /* 공지사항 안 읽은 수 — 앱의 noticeListProvider.unreadCount 와 같은 자리 */
  mApi('/notices?page=1').then(d => {
    const n = d.meta?.unread_count ?? 0;
    if (n > 0) {
      const 점 = document.getElementById('noticeUnread');
      점.textContent = n > 99 ? '99+' : n;
      점.style.display = 'inline-block';
    }
  }).catch(() => {});
</script>
@endpush
