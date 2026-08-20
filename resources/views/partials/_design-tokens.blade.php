{{-- 디자인 토큰 — Figma 「콜로플라스트_CE Admin_pc · Design System」 원본 램프와 역할 토큰.
     레이아웃(app)과 팝업(popup)이 같은 것을 봐야 색·모서리·간격이 갈리지 않아 여기로 뺐다. --}}
    :root {
      /* ── Figma DS 원본 램프 ────────────────────────────────
         파일: 콜로플라스트_CE Admin_pc · 🔒 Design System > colors
         이 블록은 DS 정의값을 그대로 옮긴 것이다. 여기 값은 임의로 고치지 않는다.
         화면에서 쓰는 토큰은 아래 '역할 토큰'에서 이 램프를 가리키게 한다. */
      --primary-50:   #E9F9FB;
      --primary-100:  #D3F1F7;
      --primary-200:  #A9DCE7;
      --primary-300:  #72BCCC;
      --primary-400:  #4898A9;
      --primary-500:  #28798B;   /* DS default */
      --primary-600:  #0B5C6E;
      --primary-700:  #044456;
      --primary-800:  #003847;
      --primary-900:  #022C3A;
      --primary-1000: #02202A;

      --gray-0:    #FFFFFF;
      --gray-50:   #F9FAFC;
      --gray-100:  #F3F5F7;
      --gray-200:  #E8EAEC;
      --gray-300:  #C2C5C8;
      --gray-400:  #999EA4;
      --gray-500:  #83888F;
      --gray-600:  #656C74;
      --gray-700:  #474D54;
      --gray-800:  #333940;
      --gray-900:  #25292F;
      --gray-1000: #101317;

      /* ── Alert — 2026-08-20 DS 개정 ──────────────────────
         디자이너가 Alert 를 램프 셋으로 늘렸다(1:84 «colors» · 파일 수정 2026-08-19).
         전에는 error 한 줄(50·100·500)뿐이었고 warning·success 는 DS 밖이었다.
         이제 셋 다 50·100·500 을 갖는다 — 확장 요청서의 회신이 이것이다.

         견본 아래 글자 라벨은 디자이너가 갱신하지 않아 error·success 의 50·100 이
         둘 다 «#FFEDED» 로 적혀 있다. 실제 fill 값이 기준이다(옛 시안도 같은 모양이었고
         우리는 그때도 fill 을 따랐다). grayscale 100 도 라벨은 «#F0F2F4» 인데
         fill 은 #F3F5F7 이다 — 옛 시안부터 그랬다. 디자이너 확인 대상. */
      --alert-50:  #FBEEEF;
      --alert-100: #FBE3E4;
      --alert-500: #F17E64;      /* 개정 전 #D73D3F */

      --warning-50:  #FFF6E8;
      --warning-100: #FFEDD4;
      --warning-500: #FBAB61;

      --success-50:  #EEFBEF;
      --success-100: #DAF7DD;
      --success-500: #88BE75;

      /* ── 역할 토큰 ─────────────────────────────────────────
         화면 코드는 항상 이쪽을 쓴다. DS 값이 바뀌면 위 램프만 고치면 된다. */
      --primary:        var(--primary-500);
      --primary-light:  var(--primary-50);
      --primary-dark:   var(--primary-600);
      --primary-accent: var(--primary-300);

      /* 의미색 — 2026-08-20 부로 success·warning 이 DS 안으로 들어왔다.
         확장 요청서(docs/DS-extension-request_semantic-colors.md)의 회신이다.
         이제 셋 다 위 램프를 가리킨다 — 값을 여기서 직접 적지 않는다.

         **대비는 떨어졌다.** 흰 바탕 위 글자로 재면
           error   #D73D3F 4.54:1(AA 통과) → #F17E64 **2.66:1**(미달)
           warning #F59E0B 2.15:1          → #FBAB61 **1.89:1**
           success #12B76A 2.62:1          → #88BE75 **2.17:1**
         --danger 는 화면에서 224곳이 글자색이고 바탕색은 19곳뿐이라 이 값이 그대로 글자에 실린다.
         시안이 기준이므로 값은 그대로 옮겼다 — 디자이너 확인 대상. */
      --success:        var(--success-500);  --success-light: var(--success-50);
      --warning:        var(--warning-500);  --warning-light: var(--warning-50);
      --danger:         var(--alert-500);    --danger-light:  var(--alert-50);
      /* info·purple 은 이번 개정에도 DS 에 없다 — 여전히 확장 요청 대상이다 */
      --info:           #0EA5E9;  --info-light:    #F0F9FF;
      --purple:         #7C3AED;

      --bg:             var(--gray-100);
      --bg-card:        var(--gray-0);
      --border:         var(--gray-200);
      --border-light:   var(--gray-100);
      --text-primary:   var(--gray-1000);
      --text-secondary: var(--gray-800);
      --text-muted:     var(--gray-400);
      /* 아래 두 줄은 정의가 없는데 화면에서 이미 쓰이고 있었다.
         정의가 없으면 color: var(--text) 는 무효가 되어 글자색이 상속으로 떨어진다
         (처방전 검수 6곳 · 읽기전용 입력칸 4곳). 쓰이는 대로 값을 준다. */
      --text:           var(--gray-1000);
      --bg-secondary:   var(--gray-50);
      --shadow:    0 1px 3px rgba(13,27,42,.06), 0 1px 2px rgba(13,27,42,.04);
      --shadow-md: 0 4px 12px rgba(13,27,42,.08), 0 2px 6px rgba(13,27,42,.04);
      --shadow-lg: 0 16px 32px rgba(13,27,42,.10), 0 4px 8px rgba(13,27,42,.04);
      --radius:    8px;
      --radius-lg: 12px;
      --transition: all .18s ease;
      --menu-bg:     var(--gray-0);
      --menu-color:  var(--gray-600);
      --menu-active: var(--primary);
      --nav-h: 68px;          /* Figma header/로고영역 높이 */
      --sidebar-w: 320px;     /* Figma sidebar 폭 */
      --sidebar-collapsed-w: 64px;   /* 아이콘만 남기는 접힘 폭 */
      --content-pad: 16px;    /* Figma container padding — 본문 여백의 단일 출처 */
      /* 시안 결과바 안내문 앞 12×12 alert-circle. 마크업을 안 건드리려고 mask 로 그린다. */
      --icon-alert-circle: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round'><circle cx='12' cy='12' r='9'/><path d='M12 7.5v5'/><path d='M12 16.2h.01'/></svg>");
    }
