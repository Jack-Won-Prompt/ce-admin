{{-- Website 시안 글꼴 — NEXON Lv2 Gothic OTF 세 굵기(Regular 400 · Medium 500 · Bold 700).
     Figma 가 쓰는 OTF 판과 같은 파일이다. 인트로 · 개인정보 동의서 · 로그인이 같은 것을 봐야 해서 여기로 뺐다.
     열린 <style> 안에서 @include 한다. 판을 올릴 때는 주소의 @0.2.0 만 바꾼다. --}}
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 400; font-display: swap;
      src: url('https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF.woff2') format('woff2');
    }
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 500; font-display: swap;
      src: url('https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF_Medium.woff2') format('woff2');
    }
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 700; font-display: swap;
      src: url('https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF_Bold.woff2') format('woff2');
    }
    /* 이 글꼴의 • (U+2022) · ● (U+25CF) · © (U+00A9) 는 폭만 있고 모양이 비어 있다 —
       동의서 약관의 글머리표와 비밀번호 점이 사라진다. 그 세 글자만 시스템 글꼴로 대신 그린다.
       굵기까지 같게 선언해야 뒤에 둔 이쪽이 이긴다. */
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 400;
      src: local('Malgun Gothic'), local('AppleSDGothicNeo-Regular'), local('Arial'), local('Helvetica');
      unicode-range: U+2022, U+25CF, U+00A9;
    }
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 500;
      src: local('Malgun Gothic'), local('AppleSDGothicNeo-Medium'), local('Arial'), local('Helvetica');
      unicode-range: U+2022, U+25CF, U+00A9;
    }
    @font-face {
      font-family: 'NEXON Lv2 Gothic OTF'; font-style: normal; font-weight: 700;
      src: local('Malgun Gothic Bold'), local('AppleSDGothicNeo-Bold'), local('Arial Bold'), local('Helvetica Bold');
      unicode-range: U+2022, U+25CF, U+00A9;
    }
