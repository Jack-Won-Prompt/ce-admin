{{-- 프래그먼트 레이아웃: 페이지 크롬(사이드바·네비·<html>) 없이 콘텐츠+스타일+스크립트만 출력.
     다른 화면의 '상세내용' 탭에 fetch로 가져와 직접 주입(inject)하기 위한 용도. --}}
@stack('styles')
@yield('content')
@stack('scripts')
