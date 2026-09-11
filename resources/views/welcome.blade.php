<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  {{-- 프레임 안에서 열리면 창 전체를 이 주소로 옮긴다.
       화면 탭이 iframe 이라, 세션이 끊긴 뒤에는 이 화면이 워크스페이스 안에 조각처럼
       박혀 보인다. 서버(BreakFrameOnGuest)가 대부분 막지만, 브라우저가 뒤로가기로
       캐시에서 되살리는 경우처럼 서버를 거치지 않는 길도 있어 화면에도 한 겹 둔다. --}}
  <script>
    if (window.self !== window.top) {
      try { window.top.location.replace(window.location.href); }
      catch (e) { window.location.replace(window.location.href); }
    }
  </script>

  {{-- 시안은 1920 한 장이다(Figma Website › 인트로 505:5574).
       1760 이상은 폭만 늘고, 1024~1759 는 1760 판을 통째로 줄여 비율을 지킨다.
       움직임을 줄이도록 한 사람에게는 효과를 걸지 않는다 — 그때가 곧 시안의 멈춘 모습이다. --}}
  <script>
    (function (d) {
      var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
      d.className += ' wl-js' + (still ? '' : ' wl-motion');
      function fit() {
        var w = d.clientWidth;
        d.style.setProperty('--wl-zoom', (w < 1024 || w >= 1760) ? 1 : +(w / 1760).toFixed(4));
      }
      fit();
      addEventListener('resize', fit);
      if (window.ResizeObserver) new ResizeObserver(fit).observe(d);
      /* 스크립트가 어디서 멈추더라도 글이 가려진 채로 남지 않게 */
      setTimeout(function () {
        if (!document.body || !document.body.classList.contains('is-ready')) d.classList.remove('wl-motion');
      }, 4000);
    })(document.documentElement);
  </script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 처방전 관리 플랫폼</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/@kfonts/nexon-lv2-gothic-otf@0.2.0/NEXON_Lv2_Gothic_OTF_Medium.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('images/website/intro/hero-illustration.webp') }}" as="image">
  <style>
@include('partials._design-tokens')

    /* 시안 글꼴 — NEXON Lv2 Gothic OTF 세 굵기(Regular 400 · Medium 500 · Bold 700).
       Figma 가 쓰는 OTF 판과 같은 파일이다. 판을 올릴 때는 주소의 @0.2.0 을 바꾼다. */
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

    :root {
      --wl-font: 'NEXON Lv2 Gothic OTF', 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;
      --wl-ease: cubic-bezier(.22, 1, .36, 1);
      --wl-shadow-card: 0 0 80px 0 rgba(40, 121, 139, .2);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; scroll-padding-top: 96px; }
    body {
      font-family: var(--wl-font);
      color: var(--gray-1000);
      background: var(--gray-0);
      overflow-x: clip;
      word-break: keep-all;
      -webkit-font-smoothing: antialiased;
    }
    img { display: block; }
    a { color: inherit; text-decoration: none; }
    .wl { zoom: var(--wl-zoom, 1); }

    /* ─── 머리 (505:5577) ─────────────────────────────────── */
    .wl-hero { display: flex; flex-direction: column; gap: 12px; height: 1000px; padding: 16px; }
    .wl-header { position: relative; flex: none; height: 60px; z-index: 50; }
    .wl-header-bar {
      position: relative; display: flex; align-items: center; justify-content: space-between;
      height: 60px; padding: 0 24px;
    }
    .wl-logo { display: block; }
    .wl-logo img { width: 138px; height: 36px; }

    .wl-nav {
      position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
      display: flex; align-items: center; gap: 8px; padding: 8px;
      border-radius: 999px; background: rgba(232, 234, 236, .4); box-shadow: inset 0 0 0 1px var(--gray-0);
      -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
    }
    .wl-nav-tab {
      position: relative; z-index: 1; display: flex; align-items: center; justify-content: center;
      padding: 12px 16px; border-radius: 999px;
      font-size: 14px; font-weight: 500; line-height: 17px; color: var(--gray-1000); white-space: nowrap;
      transition: background-color .3s;
    }
    .wl-nav-tab.is-active { background: var(--gray-200); }
    /* Figma 는 글자 상자 폭을 정수로 올려 잡는다(52.45 → 53). 상자가 보이는 것들은 그 폭을 최소 폭으로 준다 —
       탭 85 · 머리 단추 124 · 배지 100 · 첫 화면 단추 139/191/144 · 통계 칸 166/157/164/138 · 시작 단추 124 */
    .wl-nav-tab { min-width: 85px; }
    /* 스크립트가 붙으면 칠한 칸 대신 알약 하나가 옮겨 다닌다 — 멈춘 모습은 같다 */
    .wl-nav-ind {
      position: absolute; z-index: 0; top: 8px; bottom: 8px; left: 0; width: 0;
      border-radius: 999px; background: var(--gray-200); opacity: 0;
      transition: transform .55s var(--wl-ease), width .55s var(--wl-ease), opacity .3s;
    }
    .wl-nav.has-ind .wl-nav-tab.is-active { background: transparent; }
    .wl-nav.has-ind .wl-nav-ind { opacity: 1; }

    .wl-header-end { display: flex; align-items: center; gap: 12px; }
    .wl-btn-line {
      display: inline-flex; align-items: center; justify-content: center;
      padding: 12px 16px; border: 1px solid var(--gray-1000); border-radius: 999px;
      font-size: 14px; font-weight: 500; line-height: 17px; color: var(--gray-1000); white-space: nowrap;
      transition: background-color .3s, color .3s;
    }
    .wl-btn-line { min-width: 124px; }
    .wl-btn-line:hover { background: var(--gray-1000); color: var(--gray-0); }
    .wl-user { display: block; width: 28px; height: 28px; border-radius: 50%; transition: transform .35s var(--wl-ease); }
    .wl-user img { width: 28px; height: 28px; }
    .wl-user:hover { transform: scale(1.12); }

    /* 스크롤하면 머리가 떠서 따라온다(시안에는 없는 움직임). 맨 위에서는 시안 그대로다. */
    .wl-header.is-stuck .wl-header-bar {
      position: fixed; top: 12px; left: 16px; right: 16px;
      border-radius: 999px; background: rgba(255, 255, 255, .78);
      -webkit-backdrop-filter: blur(20px) saturate(1.4); backdrop-filter: blur(20px) saturate(1.4);
      box-shadow: 0 12px 40px rgba(2, 32, 42, .12), inset 0 0 0 1px rgba(255, 255, 255, .8);
    }
    html.wl-motion .wl-header.is-stuck .wl-header-bar { animation: wlDrop .6s var(--wl-ease); }

    /* ─── 첫 화면 판 (505:5602) ───────────────────────────── */
    .wl-panel {
      position: relative; flex: 1 1 auto; isolation: isolate;
      display: flex; flex-direction: column; justify-content: center; align-items: flex-start;
      padding: 80px 100px; border-radius: 40px; background: var(--primary-500); overflow: hidden;
    }
    /* 그림 층(505:5603)은 오른쪽에 붙는다 — 화면이 넓어지면 왼쪽은 판 색으로 채워진다 */
    .wl-art { position: absolute; top: 0; right: 0; width: 1888px; height: 896px; z-index: 0; pointer-events: none; }
    .wl-art-grid { position: absolute; left: 0; top: 0; }
    .wl-art-grid img { width: 1888px; height: 896px; }
    .wl-illus { position: absolute; left: 920.97px; top: 44.21px; width: 968px; height: 808px; }
    .wl-illus img { width: 968px; height: 808px; }
    .wl-art-fade {
      position: absolute; left: 0; top: -2px; width: 1000px; height: 900px;
      background: linear-gradient(90deg, var(--primary-500) 20%, rgba(40, 121, 139, 0) 80%);
    }
    .wl-art-glow {
      position: absolute; inset: 0; opacity: 0; mix-blend-mode: screen; transition: opacity .6s;
      background: radial-gradient(560px circle at var(--gx, 70%) var(--gy, 40%), rgba(169, 220, 231, .26), rgba(169, 220, 231, 0) 70%);
    }
    .wl-panel.is-hover .wl-art-glow { opacity: 1; }
    .wl-spark { position: absolute; inset: 0; }
    .wl-spark i {
      position: absolute; width: 4px; height: 4px; border-radius: 50%;
      background: #fff; box-shadow: 0 0 10px 3px rgba(169, 220, 231, .75); opacity: 0;
    }

    .wl-hero-copy { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: flex-start; gap: 60px; }
    .wl-hero-main { display: flex; flex-direction: column; align-items: flex-start; gap: 32px; }
    .wl-hero-title { font-size: 68px; font-weight: 500; line-height: 95px; color: var(--gray-0); white-space: nowrap; }
    .wl-hero-title .ln, .wl-h2 .ln { display: block; }
    .wl-hero-desc { font-size: 20px; font-weight: 500; line-height: 34px; color: var(--gray-0); white-space: nowrap; }
    .wl-hero-cta { display: flex; align-items: center; gap: 12px; }

    .wl-btn {
      position: relative; isolation: isolate; overflow: hidden;
      display: inline-flex; align-items: center; justify-content: center;
      height: 50px; padding: 0 20px; border-radius: 16px;
      font-size: 18px; font-weight: 500; line-height: 31px; white-space: nowrap;
      transform: translate3d(var(--mx, 0px), calc(var(--my, 0px) + var(--lift, 0px)), 0);
      transition: transform .4s var(--wl-ease), box-shadow .4s var(--wl-ease);
    }
    .wl-btn::after {
      content: ''; position: absolute; z-index: -1; top: 0; left: -70%; width: 45%; height: 100%;
      background: linear-gradient(100deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .45), rgba(255, 255, 255, 0));
      transform: skewX(-20deg); transition: left .9s var(--wl-ease);
    }
    .wl-btn:hover { --lift: -3px; box-shadow: 0 14px 30px rgba(0, 24, 32, .28); }
    .wl-btn:hover::after { left: 130%; }
    .wl-btn--white { background: var(--gray-0); color: var(--primary-500); }
    .wl-btn--white::after { background: linear-gradient(100deg, rgba(40, 121, 139, 0), rgba(40, 121, 139, .18), rgba(40, 121, 139, 0)); }
    .wl-btn--deep { background: var(--primary-800); color: var(--gray-0); }
    .wl-btn--cta { min-width: 124px; background: var(--gray-0); color: var(--primary-600); }
    .wl-hero-cta .wl-btn:nth-child(1) { min-width: 139px; }
    .wl-hero-cta .wl-btn:nth-child(2) { min-width: 191px; }
    .wl-hero-cta .wl-btn:nth-child(3) { min-width: 144px; }
    .wl-btn--cta::after { background: linear-gradient(100deg, rgba(40, 121, 139, 0), rgba(40, 121, 139, .18), rgba(40, 121, 139, 0)); }

    .wl-stats {
      position: relative; display: flex; align-items: center; overflow: hidden;
      border: 1px solid rgba(255, 255, 255, .08); border-radius: 999px; background: rgba(255, 255, 255, .04);
      -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
    }
    .wl-stat {
      position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 4px; padding: 16px 40px; white-space: nowrap;
    }
    .wl-stat + .wl-stat::before {
      content: ''; position: absolute; left: -.5px; top: 0; bottom: 0; width: 1px; background: rgba(255, 255, 255, .08);
    }
    .wl-stat:nth-child(1) { min-width: 166px; }
    .wl-stat:nth-child(2) { min-width: 157px; }
    .wl-stat:nth-child(3) { min-width: 164px; }
    .wl-stat:nth-child(4) { min-width: 138px; }
    .wl-stat b { font-size: 24px; font-weight: 500; line-height: 41px; color: var(--gray-0); }
    .wl-stat span { font-size: 14px; font-weight: 500; line-height: 24px; color: var(--primary-300); }
    .wl-stats::after {
      content: ''; position: absolute; inset: 0; pointer-events: none; transform: translateX(-100%);
      background: linear-gradient(100deg, rgba(255, 255, 255, 0) 30%, rgba(255, 255, 255, .12) 50%, rgba(255, 255, 255, 0) 70%);
    }

    /* ─── 구획 머리 (배지 · 제목 · 한 줄 설명) ───────────────── */
    .wl-head { display: flex; flex-direction: column; align-items: flex-start; gap: 32px; }
    .wl-head--center { align-items: center; text-align: center; }
    .wl-badge {
      display: inline-flex; align-items: center; justify-content: center; min-width: 100px;
      padding: 8px 20px; border-radius: 999px; background: var(--primary-100);
      font-size: 16px; font-weight: 500; line-height: 27px; color: var(--primary-700); white-space: nowrap;
    }
    .wl-h2 { font-size: 60px; font-weight: 500; line-height: 84px; color: var(--gray-1000); white-space: nowrap; }
    .wl-h2 em { font-style: normal; color: var(--primary-500); }
    .wl-lead { font-size: 18px; font-weight: 500; line-height: 31px; color: var(--gray-800); white-space: nowrap; }

    /* ─── 핵심 기능 (505:7709) — 카드 줄은 왼쪽으로 끝없이 흐른다(시안 댓글 #20) ── */
    .wl-s01 { padding: 16px; }
    .wl-s01-inner { display: flex; flex-direction: column; gap: 100px; padding: 200px 100px; }
    .wl-marquee { margin: 0 -116px; padding-left: 116px; overflow-x: clip; }
    .wl-track { display: flex; align-items: flex-start; gap: 20px; width: max-content; will-change: transform; }
    .wl-fcard-wrap { flex: none; }
    .wl-fcard-wrap:nth-child(even) { padding-top: 100px; }
    .wl-fcard, .wl-step, .wl-pcard-in { position: relative; }
    .wl-fcard {
      width: 400px; overflow: hidden; border-radius: 32px; background: var(--gray-0); box-shadow: var(--wl-shadow-card);
      transition: transform .6s var(--wl-ease), box-shadow .6s var(--wl-ease);
    }
    .wl-fcard-img { width: 400px; height: 320px; overflow: hidden; }
    .wl-fcard-img img { width: 400px; height: 320px; transition: transform 1s var(--wl-ease); }
    .wl-fcard-body { display: flex; flex-direction: column; gap: 12px; padding: 32px; }
    .wl-fcard-body h3 { font-size: 20px; font-weight: 500; line-height: 28px; color: var(--gray-1000); white-space: nowrap; }
    .wl-fcard-body p { font-size: 18px; font-weight: 400; line-height: 29px; color: var(--gray-600); white-space: nowrap; }
    /* 시안이 카드 4~6 설명만 줄 간격 170% 로 두었다(1~3 은 160%) — 높이가 511 · 517 · 486 으로 갈린다 */
    .wl-fcard--tall .wl-fcard-body p { line-height: 31px; }
    .wl-fcard:hover { box-shadow: 0 24px 80px 0 rgba(40, 121, 139, .32); }
    .wl-fcard:hover .wl-fcard-img img { transform: scale(1.06); }

    /* 카드가 마우스를 따라 기우는 빛 */
    [data-tilt]::after {
      content: ''; position: absolute; inset: 0; z-index: 3; pointer-events: none; opacity: 0; border-radius: inherit;
      background: radial-gradient(circle at var(--gx, 50%) var(--gy, 50%), rgba(255, 255, 255, .42), rgba(255, 255, 255, 0) 55%);
      mix-blend-mode: soft-light; transition: opacity .4s;
    }
    [data-tilt].is-tilt::after { opacity: 1; }

    /* ─── 업무 흐름 (505:8529) ─────────────────────────────── */
    .wl-s02 { position: relative; height: 1447px; padding: 16px; overflow: hidden; }
    .wl-s02-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
    .wl-s02-bg img {
      width: 100%; height: 100%; object-fit: cover; object-position: center top;
      transform: translate3d(0, var(--bgy, 0px), 0) scale(var(--bgs, 1));
    }
    /* CSS 자간은 마지막 글자 뒤에도 붙는다(Figma 는 안 붙인다) — 그 -4px 만큼 상자를 밖으로 민다 */
    .wl-s02-wm {
      position: absolute; right: -4px; bottom: 0; z-index: 1; pointer-events: none;
      font-size: 200px; font-weight: 700; line-height: 240px; letter-spacing: -4px;
      color: var(--primary-500); opacity: .04; white-space: nowrap;
      transform: translate3d(var(--wmx, 0px), 0, 0);
    }
    .wl-s02-inner {
      position: relative; z-index: 2; height: 1415px;
      display: flex; justify-content: space-between; align-items: flex-start; padding: 200px 100px;
    }
    .wl-steps { display: flex; align-items: flex-start; gap: 20px; }
    .wl-steps-col { display: flex; flex-direction: column; gap: 20px; }
    .wl-steps-col--b { padding-top: 100px; }
    .wl-step {
      width: 440px; display: flex; flex-direction: column; align-items: center; gap: 32px; padding: 40px;
      overflow: hidden; border-radius: 32px; background: var(--gray-0); box-shadow: var(--wl-shadow-card);
      transition: transform .6s var(--wl-ease), box-shadow .6s var(--wl-ease);
    }
    .wl-step:hover { box-shadow: 0 24px 80px 0 rgba(40, 121, 139, .32); }
    .wl-step-icon { width: 160px; height: 160px; }
    .wl-step-icon img { width: 160px; height: 160px; transition: transform .8s var(--wl-ease); }
    .wl-step:hover .wl-step-icon img { transform: translateY(-6px) rotate(-4deg) scale(1.05); }
    .wl-step-txt { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
    .wl-step-txt h3 { font-size: 20px; font-weight: 500; line-height: 28px; color: var(--gray-1000); white-space: nowrap; }
    .wl-step-txt p { font-size: 18px; font-weight: 400; line-height: 31px; color: var(--gray-600); white-space: nowrap; }
    .wl-step-no {
      position: absolute; left: 40px; top: 40px; width: 36px; height: 36px;
      display: flex; align-items: center; justify-content: center; border-radius: 12px;
      background: var(--gray-200); font-size: 20px; font-weight: 500; line-height: 24px; color: var(--gray-800);
    }

    /* ─── 연동 기관 (505:8685) ─────────────────────────────── */
    .wl-s03 { padding: 16px; }
    .wl-s03-inner { display: flex; flex-direction: column; align-items: center; gap: 100px; padding: 200px 100px; }
    .wl-cluster { position: relative; width: 996.39px; height: 559.4px; }
    .wl-pcard { position: absolute; transform: translate3d(calc(var(--cx, 0px) * var(--depth, 1)), calc(var(--cy, 0px) * var(--depth, 1)), 0); }
    .wl-pcard-in {
      display: flex; justify-content: center; align-items: flex-start; padding: 22.6343px;
      overflow: hidden; border-radius: 32px; background: var(--gray-0); box-shadow: 0 0 99.273px 0 rgba(40, 121, 139, .2);
      transition: transform .6s var(--wl-ease), box-shadow .6s var(--wl-ease);
    }
    .wl-pcard-in img { height: 120px; }
    .wl-pcard:hover { z-index: 10; }
    .wl-pcard:hover .wl-pcard-in { box-shadow: 0 20px 90px 0 rgba(40, 121, 139, .34); }

    /* ─── 시작 안내 (505:8705) ─────────────────────────────── */
    .wl-s04 {
      position: relative; min-height: 1080px; padding: 16px; overflow: hidden;
      background: linear-gradient(180deg, var(--primary-1000) 0%, var(--primary-700) 100%);
    }
    .wl-s04-bg { position: absolute; left: 0; top: 0; width: 100%; height: 1080px; overflow: hidden; }
    .wl-s04-bg img {
      width: 100%; height: 100%; object-fit: cover; object-position: center top;
      transform-origin: 50% 70%; transform: scale(var(--kb, 1));
    }
    .wl-s04-sweep { position: absolute; inset: 0; pointer-events: none; opacity: 0; mix-blend-mode: screen; }
    .wl-s04 .wl-spark { top: 45%; }
    .wl-s04-inner { position: relative; z-index: 1; display: flex; justify-content: center; padding: 200px 100px; }
    .wl-s04-box { display: flex; flex-direction: column; align-items: center; gap: 48px; }
    .wl-s04-txt { display: flex; flex-direction: column; align-items: center; gap: 32px; text-align: center; color: var(--gray-0); }
    .wl-s04-txt h2 { font-size: 60px; font-weight: 500; line-height: 84px; white-space: nowrap; }
    .wl-s04-txt p { font-size: 18px; font-weight: 500; line-height: 31px; white-space: nowrap; }

    /* ─── 바닥 (505:9269) ─────────────────────────────────── */
    .wl-foot { position: relative; padding: 16px; overflow: hidden; color: var(--gray-0); background: var(--primary-700); }
    .wl-foot-bg { position: absolute; inset: 0; }
    .wl-foot-bg img { width: 100%; height: 100%; object-fit: cover; object-position: center top; }
    .wl-foot-inner { position: relative; display: flex; align-items: stretch; gap: 40px; padding: 60px; }
    .wl-foot-left { flex: none; display: flex; flex-direction: column; align-items: flex-start; gap: 40px; }
    .wl-foot-logo { width: 122px; height: 32px; opacity: .6; }
    .wl-foot-info { display: flex; flex-direction: column; justify-content: center; gap: 2px; }
    .wl-foot-row { display: flex; align-items: center; gap: 20px; }
    .wl-foot-row span, .wl-foot-meta p { font-size: 13px; font-weight: 400; line-height: 22px; opacity: .6; white-space: nowrap; }
    .wl-foot-sep { flex: none; width: 2px; height: 8px; margin: 0 -1px; background: var(--gray-200); opacity: .2; }
    .wl-foot-links { display: flex; align-items: center; gap: 32px; }
    .wl-foot-links a { font-size: 14px; font-weight: 500; line-height: 24px; opacity: .8; white-space: nowrap; transition: opacity .3s; }
    .wl-foot-links a:hover { opacity: 1; }
    .wl-foot-meta { flex: 1 1 0; min-width: 1px; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-end; }

    .wl-progress {
      position: fixed; left: 0; top: 0; z-index: 200; width: 100%; height: 3px; pointer-events: none;
      transform-origin: 0 50%; transform: scaleX(var(--sp, 0));
      background: linear-gradient(90deg, var(--primary-300), var(--primary-500));
    }

    /* ─── 움직임 — 멈춘 모습(효과가 끝난 자리)이 시안이다 ───────── */
    html.wl-motion [data-rv] { transition: opacity 1s var(--wl-ease), transform 1s var(--wl-ease); transition-delay: var(--rd, 0s); }
    html.wl-motion [data-rv="up"]:not(.is-in) { opacity: 0; transform: translate3d(0, 48px, 0); }
    html.wl-motion [data-rv="fade"]:not(.is-in) { opacity: 0; }
    html.wl-motion .wl-cluster[data-rv] { transition-duration: 1.6s; }

    html.wl-motion .wl-header-bar > * { transition: opacity .9s var(--wl-ease), transform .9s var(--wl-ease); }
    html.wl-motion .wl-header-bar > :nth-child(2) { transition-delay: .1s; }
    html.wl-motion .wl-header-bar > :nth-child(3) { transition-delay: .2s; }
    html.wl-motion body:not(.is-ready) .wl-header-bar > * { opacity: 0; transform: translate3d(0, -16px, 0); }
    html.wl-motion body:not(.is-ready) .wl-header-bar > .wl-nav { transform: translate(-50%, calc(-50% - 16px)); }

    html.wl-motion .wl-panel { clip-path: inset(0 round 40px); transition: clip-path 1.5s var(--wl-ease); }
    html.wl-motion body:not(.is-ready) .wl-panel { clip-path: inset(5% 3% 5% 3% round 40px); }
    html.wl-motion .wl-art-grid { transform-origin: 100% 50%; transition: opacity 1.8s var(--wl-ease) .2s, transform 2.2s var(--wl-ease) .2s; }
    html.wl-motion body:not(.is-ready) .wl-art-grid { opacity: 0; transform: scale(1.06); }
    html.wl-motion .wl-illus { transform: translate3d(var(--px, 0px), var(--py, 0px), 0); }
    html.wl-motion .wl-illus-in { transition: opacity 1.4s var(--wl-ease) .35s, transform 1.8s var(--wl-ease) .35s; }
    html.wl-motion body:not(.is-ready) .wl-illus-in { opacity: 0; transform: translate3d(90px, 40px, 0) scale(.96); }
    html.wl-motion body.is-ready .wl-illus-float { animation: wlFloat 7s ease-in-out 2.2s infinite; }
    html.wl-motion .wl-art-grid img { transform: translate3d(var(--qx, 0px), var(--qy, 0px), 0); }
    html.wl-motion body.is-ready .wl-stats::after { animation: wlSweep 7s var(--wl-ease) 2s infinite; }
    html.wl-motion body.is-ready .wl-spark i { animation: wlTwinkle var(--d, 4s) ease-in-out var(--dl, 0s) infinite; }

    html.wl-motion .wl-step-rv.is-in .wl-step-no { animation: wlNo 1.3s var(--wl-ease) calc(var(--rd, 0s) + .35s) both; }
    html.wl-motion .wl-step-rv.is-in .wl-step-icon { animation: wlPop 1s var(--wl-ease) calc(var(--rd, 0s) + .15s) both; }
    html.wl-motion .wl-cluster.is-in .wl-pcard-float { animation: wlBob var(--fd, 6s) ease-in-out var(--fl, 0s) infinite; }
    html.wl-motion .wl-s04-sweep {
      opacity: 1; background: linear-gradient(115deg, rgba(114, 188, 204, 0) 40%, rgba(114, 188, 204, .16) 50%, rgba(114, 188, 204, 0) 60%);
      background-size: 260% 100%; animation: wlBand 10s linear infinite;
    }

    @keyframes wlDrop { from { transform: translate3d(0, -140%, 0); } to { transform: none; } }
    @keyframes wlFloat { 0%, 100% { transform: translate3d(0, 0, 0); } 50% { transform: translate3d(0, -12px, 0); } }
    @keyframes wlSweep { 0% { transform: translateX(-100%); } 28%, 100% { transform: translateX(100%); } }
    @keyframes wlTwinkle { 0%, 100% { opacity: 0; transform: scale(.4); } 50% { opacity: .9; transform: scale(1); } }
    @keyframes wlNo {
      0% { background: var(--primary-500); color: var(--gray-0); transform: scale(.4); }
      55% { background: var(--primary-500); color: var(--gray-0); transform: scale(1.18); }
      100% { background: var(--gray-200); color: var(--gray-800); transform: scale(1); }
    }
    @keyframes wlPop { 0% { opacity: 0; transform: scale(.7) rotate(-6deg); } 100% { opacity: 1; transform: none; } }
    @keyframes wlBob { 0%, 100% { transform: translate3d(0, 0, 0); } 50% { transform: translate3d(0, -9px, 0); } }
    @keyframes wlBand { from { background-position: 130% 0; } to { background-position: -30% 0; } }

    /* ─── 좁은 화면 — 시안이 없는 폭이라 쌓아서 읽히게만 한다 ──────── */
    @media (max-width: 1023px) {
      html { scroll-padding-top: 80px; }
      .wl-hero { height: auto; padding: 12px; }
      .wl-header-bar { padding: 0 8px; }
      .wl-nav { display: none; }
      .wl-header.is-stuck .wl-header-bar { left: 12px; right: 12px; padding: 0 16px; }
      .wl-panel { padding: 56px 24px 400px; border-radius: 28px; }
      .wl-art { top: auto; bottom: -40px; right: -180px; transform: scale(.5); transform-origin: 100% 100%; }
      .wl-art-fade { display: none; }
      .wl-hero-copy { gap: 40px; }
      .wl-hero-main { gap: 24px; }
      .wl-hero-title { font-size: 40px; line-height: 1.35; white-space: normal; }
      .wl-hero-desc { font-size: 16px; line-height: 1.7; white-space: normal; }
      .wl-hero-desc br { display: none; }
      .wl-hero-cta { flex-wrap: wrap; }
      .wl-btn { height: 46px; font-size: 16px; }
      .wl-stats { flex-wrap: wrap; border-radius: 24px; }
      .wl-stat, .wl-stat:nth-child(n) { flex: 1 1 50%; min-width: 0; padding: 12px 16px; }
      .wl-stat:nth-child(3)::before { display: none; }

      .wl-s01-inner, .wl-s03-inner { gap: 48px; padding: 96px 20px; }
      .wl-h2, .wl-s04-txt h2 { font-size: 36px; line-height: 1.35; white-space: normal; }
      .wl-lead, .wl-s04-txt p { font-size: 16px; line-height: 1.7; white-space: normal; }
      .wl-marquee { margin: 0 -36px; padding-left: 36px; }
      .wl-fcard-wrap:nth-child(even) { padding-top: 40px; }
      .wl-fcard, .wl-fcard-img, .wl-fcard-img img { width: 300px; }
      .wl-fcard-img, .wl-fcard-img img { height: 240px; }
      .wl-fcard-body { padding: 24px; }
      .wl-fcard-body p, .wl-fcard--tall .wl-fcard-body p { font-size: 15px; line-height: 1.6; white-space: normal; }
      .wl-fcard-body p br { display: none; }

      .wl-s02 { height: auto; }
      .wl-s02-inner { height: auto; flex-direction: column; gap: 48px; padding: 96px 20px; }
      .wl-s02-wm { font-size: 96px; line-height: 1.2; letter-spacing: -2px; }
      .wl-steps { width: 100%; flex-direction: column; align-items: stretch; }
      .wl-steps-col { display: contents; }
      .wl-step-rv { order: var(--n); }
      .wl-step { width: 100%; padding: 28px; gap: 20px; }
      .wl-step-no { left: 20px; top: 20px; }
      .wl-step-icon, .wl-step-icon img { width: 120px; height: 120px; }
      .wl-step-txt p { font-size: 16px; white-space: normal; }

      .wl-cluster { width: 100%; height: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
      .wl-pcard { position: static; transform: none; }
      .wl-pcard-in { padding: 14px; border-radius: 20px; align-items: center; box-shadow: 0 0 40px rgba(40, 121, 139, .18); }
      .wl-pcard-in img { width: 100% !important; height: 64px; object-fit: contain; }

      .wl-s04 { min-height: 640px; }
      .wl-s04-bg { height: 100%; }
      .wl-s04-inner { padding: 120px 20px; }

      .wl-foot-inner { flex-direction: column; gap: 32px; padding: 40px 20px; }
      .wl-foot-row { flex-wrap: wrap; gap: 4px 16px; }
      .wl-foot-sep { display: none; }
      .wl-foot-row span, .wl-foot-meta p { white-space: normal; }
      .wl-foot-links { flex-wrap: wrap; gap: 12px 24px; }
      .wl-foot-meta { align-items: flex-start; }
    }
  </style>
</head>
<body>
  <div class="wl-progress" aria-hidden="true"></div>

  <div class="wl">
    {{-- 첫 화면 (505:5576) — 머리 · 판 --}}
    <section class="wl-hero">
      <header class="wl-header" id="wlHeader">
        <div class="wl-header-bar">
          <a class="wl-logo" href="{{ route('welcome') }}">
            <img src="{{ asset('images/website/brand/logo-dark.png') }}" width="138" height="36" alt="CE Admin · Coloplast Korea">
          </a>
          <nav class="wl-nav" id="wlNav" aria-label="구획 이동">
            <span class="wl-nav-ind" aria-hidden="true"></span>
            <a class="wl-nav-tab is-active" href="#features" data-spy="features">핵심 기능</a>
            <a class="wl-nav-tab" href="#workflow" data-spy="workflow">업무 흐름</a>
            <a class="wl-nav-tab" href="#partners" data-spy="partners">연동 기관</a>
          </nav>
          <div class="wl-header-end">
            <a class="wl-btn-line" href="{{ route('privacy.landing') }}">개인정보 동의서</a>
            <a class="wl-user" href="{{ route('login') }}" aria-label="로그인">
              <img src="{{ asset('images/website/icons/user-profile.svg') }}" width="28" height="28" alt="">
            </a>
          </div>
        </div>
      </header>

      <div class="wl-panel" id="wlPanel">
        <div class="wl-art" aria-hidden="true">
          <div class="wl-art-grid">
            <img src="{{ asset('images/website/intro/hero-grid.svg') }}" width="1888" height="896" alt="">
          </div>
          <div class="wl-illus">
            <div class="wl-illus-in">
              <div class="wl-illus-float">
                <img src="{{ asset('images/website/intro/hero-illustration.webp') }}" width="968" height="808" alt="" fetchpriority="high">
              </div>
            </div>
          </div>
          <span class="wl-art-fade"></span>
          <span class="wl-art-glow"></span>
          <div class="wl-spark" data-spark="16"></div>
        </div>

        <div class="wl-hero-copy">
          <div class="wl-hero-main">
            {{-- 제목 · 설명 · 버튼 줄이 저마다 떠오른다(시안 댓글 #19) --}}
            <h1 class="wl-hero-title">
              <span class="ln" data-rv="up" style="--rd:.45s">처방전 관리의</span>
              <span class="ln" data-rv="up" style="--rd:.58s">새로운 기준을 만듭니다</span>
            </h1>
            <p class="wl-hero-desc" data-rv="up" style="--rd:.74s">OCR 자동화ㆍ요양비 청구ㆍ실시간 주문 연계ㆍ기관 정책 모니터링까지 <br>병원 행정 업무를 하나의 플랫폼에서 처리하세요.</p>
            <div class="wl-hero-cta" data-rv="up" style="--rd:.88s">
              <a class="wl-btn wl-btn--white" href="{{ route('login') }}" data-magnet>지금 시작하기</a>
              <a class="wl-btn wl-btn--deep" href="{{ route('privacy.landing') }}" data-magnet>개인정보 동의서 작성</a>
              <a class="wl-btn wl-btn--deep" href="#features" data-magnet>주요 기능 보기</a>
            </div>
          </div>
          <div class="wl-stats" data-rv="up" style="--rd:1.02s">
            <div class="wl-stat"><b>AI</b><span>OCR 자동 인식</span></div>
            <div class="wl-stat"><b data-count="3">3</b><span>정부기관 연동</span></div>
            <div class="wl-stat"><b>실시간</b><span>주문·배송 추적</span></div>
            <div class="wl-stat"><b>24/7</b><span>자동 수집</span></div>
          </div>
        </div>
      </div>
    </section>

    {{-- 핵심 기능 (505:7709) --}}
    <section class="wl-s01" id="features">
      <div class="wl-s01-inner">
        <div class="wl-head">
          <span class="wl-badge" data-rv="up">핵심 기능</span>
          <h2 class="wl-h2">
            <span class="ln" data-rv="up" style="--rd:.1s">업무 전 과정을</span>
            <span class="ln" data-rv="up" style="--rd:.2s"><em>하나의 화면</em>으로</span>
          </h2>
          <p class="wl-lead" data-rv="up" style="--rd:.3s">처방 접수부터 건강보험 청구, 배송 완료까지 모든 단계를 자동화합니다.</p>
        </div>

        @php
          $features = [
            /* 줄 끝 공백은 좁은 화면에서 <br> 을 풀 때 낱말이 붙지 않게 둔 것이다 */
            ['ocr',       '#022C3A', '처방전 OCR 인식',          '처방전 이미지에서 주민번호·처방일 등 <br>항목을 자동 추출하고, 검수 화면에서 <br>확인·보정합니다.'],
            ['claim',     '#012E5B', '요양비 청구 연동',          '건강보험심사평가원 팩스 자동 전송, <br>청구 상태 추적, 급여 승인·반려 내역을 <br>통합 관리합니다.'],
            ['withworks', '#171E50', 'Withworks 주문 자동 연계',  '처방 승인 즉시 Withworks 판매주문(SO)을 <br>자동 생성하고 배송 현황을 실시간으로 <br>추적합니다.'],
            ['notice',    '#00423B', '기관 공지사항 자동 수집',    '보건복지부·심사평가원·국민건강보험공단 <br>정책 공지를 매일 자동 수집하여 수가 변경 등 <br>핵심 정보를 즉시 파악합니다.'],
            ['tax',       '#3A2102', '세금계산서·현금영수증 발행', 'API를 통해 전자 세금계산서·현금영수증을 <br>발행하고 발송 이력을 한곳에서 관리합니다.'],
            ['dashboard', '#023A05', '통합 대시보드',             '처방·주문·정산·청구 현황을 <br>실시간 카드·차트로 표시하고 <br>재구매 일정을 캘린더로 관리합니다.'],
          ];
        @endphp
        <div class="wl-marquee" data-rv="up" style="--rd:.35s">
          <div class="wl-track" id="wlTrack">
            {{-- 이음매 없이 돌도록 한 벌을 더 붙인다 — 뒤 벌은 읽기 도구에 감춘다 --}}
            @foreach([false, true] as $clone)
              @foreach($features as $i => [$key, $bg, $title, $desc])
                <div class="wl-fcard-wrap" @if($clone) aria-hidden="true" @endif>
                  <article class="wl-fcard{{ $i >= 3 ? ' wl-fcard--tall' : '' }}" data-tilt>
                    <div class="wl-fcard-img" style="background:{{ $bg }}">
                      <img src="{{ asset('images/website/intro/feature-' . $key . '.webp') }}" width="400" height="320" alt="" loading="lazy">
                    </div>
                    <div class="wl-fcard-body">
                      <h3>{{ $title }}</h3>
                      <p>{!! $desc !!}</p>
                    </div>
                  </article>
                </div>
              @endforeach
            @endforeach
          </div>
        </div>
      </div>
    </section>

    {{-- 업무 흐름 (505:8529) — 카드는 1→5 번호 차례로 떠오른다(시안 댓글 #21) --}}
    <section class="wl-s02" id="workflow">
      <div class="wl-s02-bg" aria-hidden="true">
        <img src="{{ asset('images/website/intro/s02-bg.webp') }}" width="1920" height="1447" alt="" loading="lazy">
      </div>
      <p class="wl-s02-wm" aria-hidden="true">Coloplast Korea</p>

      <div class="wl-s02-inner">
        <div class="wl-head">
          <span class="wl-badge" data-rv="up">업무 흐름</span>
          <h2 class="wl-h2">
            <span class="ln" data-rv="up" style="--rd:.1s">처방 접수부터 완료까지</span>
            <span class="ln" data-rv="up" style="--rd:.2s"><em>5단계</em>로 끝납니다</span>
          </h2>
          <p class="wl-lead" data-rv="up" style="--rd:.3s">복잡한 의료기기 급여 청구 업무를 표준화된 워크플로우로 처리하세요.</p>
        </div>

        {{-- 3번 설명은 「위드웍스 판매번호」(2026-08-31 지시 · 5f10e62). 시안은 옛 낱말 「Withworks SO」로 남아 있다. --}}
        @php
          $steps = [
            1 => ['처방전 업로드', '모바일·웹에서 이미지 업로드, AI가 자동 분석'],
            2 => ['OCR 검수',     '추출 데이터 확인 및 수정, 제품 매핑'],
            3 => ['주문 생성',     '위드웍스 판매번호 자동 생성, 배송 정보 연계'],
            4 => ['청구',          '급여 청구 팩스 전송, 결과 자동 수신'],
            5 => ['정산 완료',     '세금계산서 발행, 현금영수증, 정산 마감'],
          ];
        @endphp
        <div class="wl-steps">
          @foreach([[1, 3, 5], [2, 4]] as $c => $col)
            <div class="wl-steps-col{{ $c ? ' wl-steps-col--b' : '' }}">
              @foreach($col as $n)
                <div class="wl-step-rv" data-rv="up" style="--rd:{{ ($n - 1) * .16 }}s; --n:{{ $n }}">
                  <article class="wl-step" data-tilt>
                    <span class="wl-step-no">{{ $n }}</span>
                    <div class="wl-step-icon">
                      <img src="{{ asset('images/website/intro/step-' . $n . '.svg') }}" width="160" height="160" alt="" loading="lazy">
                    </div>
                    <div class="wl-step-txt">
                      <h3>{{ $steps[$n][0] }}</h3>
                      <p>{{ $steps[$n][1] }}</p>
                    </div>
                  </article>
                </div>
              @endforeach
            </div>
          @endforeach
        </div>
      </div>
    </section>

    {{-- 연동 기관 (505:8685) — 로고 무리가 한꺼번에 떠오른다(시안 댓글 #22) --}}
    <section class="wl-s03" id="partners">
      <div class="wl-s03-inner">
        <div class="wl-head wl-head--center">
          <span class="wl-badge" data-rv="up">연동 기관</span>
          <h2 class="wl-h2">
            <span class="ln" data-rv="up" style="--rd:.1s"><em>주요 의료 기관</em>과</span>
            <span class="ln" data-rv="up" style="--rd:.2s">직접 연결됩니다</span>
          </h2>
          <p class="wl-lead" data-rv="up" style="--rd:.3s">정부 기관의 공지사항을 실시간으로 수집해 정책 변경을 놓치지 않습니다.</p>
        </div>

        {{-- 자리 · 크기는 시안 Group 23 그대로(겹치는 차례까지). depth 는 마우스를 따라 움직이는 깊이. --}}
        @php
          $partners = [
            ['mohw',         '보건복지부',          0,      129.05, 321.76, 0.6, 6.4, 0],
            ['hira',         '건강보험심사평가원',   568.67, 245.53, 382.45, 0.9, 7.2, 1.1],
            ['withworks',    'WITHWORKS',          234.22, 394.13, 481.67, 1.2, 6.8, 2.3],
            ['tosspayments', '토스페이먼츠',         295.02, 0,      360.09, 0.5, 7.6, .6],
            ['popbill',      '팝빌',               50.56,  273,    247.98, 1.0, 6.0, 1.7],
            ['nhis',         '국민건강보험',         643.52, 95.18,  282.97, 0.8, 7.0, 2.9],
          ];
        @endphp
        <div class="wl-cluster" id="wlCluster" data-rv="fade" style="--rd:.3s">
          @foreach($partners as [$key, $name, $x, $y, $w, $depth, $fd, $fl])
            <div class="wl-pcard" style="left:{{ $x }}px; top:{{ $y }}px; --depth:{{ $depth }}">
              <div class="wl-pcard-float" style="--fd:{{ $fd }}s; --fl:-{{ $fl }}s">
                <div class="wl-pcard-in" data-tilt>
                  <img src="{{ asset('images/website/intro/partner-' . $key . '.png') }}" style="width:{{ $w }}px" height="120" alt="{{ $name }}" loading="lazy">
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    {{-- 시작 안내 (505:8705) — 제목 · 설명 · 버튼이 저마다 떠오른다(시안 댓글 #23) --}}
    <section class="wl-s04" id="login">
      <div class="wl-s04-bg" aria-hidden="true">
        <img src="{{ asset('images/website/intro/s04-bg.webp') }}" width="1920" height="1080" alt="" loading="lazy">
      </div>
      <span class="wl-s04-sweep" aria-hidden="true"></span>
      <div class="wl-spark" data-spark="14" aria-hidden="true"></div>
      <div class="wl-s04-inner">
        <div class="wl-s04-box">
          <div class="wl-s04-txt">
            <h2 data-rv="up">지금 바로 시작하세요</h2>
            {{-- 시안 첫 줄 끝에 줄바꿈 글자가 한 칸 폭으로 남아 가운데가 그만큼 왼쪽이다 — &nbsp; 한 칸으로 같게 둔다 --}}
            <p data-rv="up" style="--rd:.15s">CE Admin은 Coloplast Korea 임직원 전용 플랫폼입니다.&nbsp;<br>계정이 없으신 경우 IT 관리자에게 문의하세요.</p>
          </div>
          <div data-rv="up" style="--rd:.3s">
            <a class="wl-btn wl-btn--cta" href="{{ route('login') }}" data-magnet>로그인 하기</a>
          </div>
        </div>
      </div>
    </section>

    {{-- 기업 정보 — 값은 설정에 든 것을 그대로 읽는다(서비스 연동 설정 · .env).
         화면에 박아 두면 상호ㆍ주소가 바뀔 때 이 자리만 옛것으로 남는다. --}}
    @php
      /* 사업자등록번호는 팝빌 발행에 쓰는 번호가 곧 회사 번호다. 키 이름에 test 가 붙어 있지만
         운영 전환(IsTest=false) 뒤로는 실제 발행에 쓰는 번호라 따로 둘 이유가 없다. */
      $corpNum  = preg_replace('/^(\d{3})(\d{2})(\d{5})$/', '$1-$2-$3', (string) config('popbill.test.corp_num'));
      $corpName = config('popbill.company.corp_name');
      $corpRows = array_filter([
        '대표자'         => config('popbill.company.ceo_name'),
        '사업자등록번호' => $corpNum,
        '업태'           => config('popbill.company.biz_type'),
        '종목'           => config('popbill.company.biz_class'),
        '대표전화'       => config('popbill.company.tel'),
        '팩스'           => config('popbill.test.fax_sender'),
        '이메일'         => config('popbill.company.email'),
      ]);
    @endphp
    {{-- 시안(505:9301)은 기업 정보를 네 줄로 묶는다. 값은 위에서 읽은 그대로이고 여기서는 줄만 나눈다 —
         빈 값은 줄에서 빠지고, 다 빈 줄은 통째로 빠진다. --}}
    @php
      $footRows = array_values(array_filter([
        array_filter(['상호명' => $corpName, '대표자' => $corpRows['대표자'] ?? null, '사업자등록번호' => $corpRows['사업자등록번호'] ?? null]),
        array_filter(['업태' => $corpRows['업태'] ?? null, '종목' => $corpRows['종목'] ?? null]),
        array_filter(['대표전화' => $corpRows['대표전화'] ?? null, '팩스' => $corpRows['팩스'] ?? null, '이메일' => $corpRows['이메일'] ?? null]),
        array_filter(['주소' => config('popbill.company.addr')]),
      ]));
    @endphp

    {{-- 바닥 (505:9269) --}}
    <footer class="wl-foot">
      <div class="wl-foot-bg" aria-hidden="true">
        <img src="{{ asset('images/website/intro/footer-bg.webp') }}" width="1920" height="382" alt="" loading="lazy">
      </div>
      <div class="wl-foot-inner" data-rv="fade">
        <div class="wl-foot-left">
          <img class="wl-foot-logo" src="{{ asset('images/website/brand/logo-light.png') }}" width="122" height="32" alt="CE Admin · Coloplast Korea">
          @if($corpName)
          <div class="wl-foot-info">
            @foreach($footRows as $row)
              <div class="wl-foot-row">
                @foreach($row as $k => $v)
                  @if(!$loop->first)<i class="wl-foot-sep" aria-hidden="true"></i>@endif
                  <span>{{ $k }}: {{ $v }}</span>
                @endforeach
              </div>
            @endforeach
          </div>
          @endif
          <nav class="wl-foot-links" aria-label="바닥 링크">
            <a href="#">개인정보 처리방침</a>
            <a href="#">이용약관</a>
            <a href="#">IT 지원 문의</a>
          </nav>
        </div>
        <div class="wl-foot-meta">
          <p>Powered by Coloplast</p>
          <p>ⓒ {{ date('Y') }} Coloplast Korea · CE Admin v2.0</p>
        </div>
      </div>
    </footer>
  </div>

  <script>
    (function () {
      var root = document.documentElement, body = document.body;
      var motion = root.classList.contains('wl-motion');
      var $ = function (s, c) { return (c || document).querySelector(s); };
      var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
      var lerp = function (a, b, t) { return a + (b - a) * t; };

      /* ── 반짝이 — 판 오른쪽과 시작 안내 아래쪽에만 뿌린다 ── */
      $$('[data-spark]').forEach(function (box) {
        var n = +box.getAttribute('data-spark');
        for (var i = 0; i < n; i++) {
          var s = document.createElement('i');
          s.style.left = (box.closest('.wl-panel') ? 52 + Math.random() * 46 : 4 + Math.random() * 92) + '%';
          s.style.top = (6 + Math.random() * 88) + '%';
          s.style.setProperty('--d', (3 + Math.random() * 4).toFixed(2) + 's');
          s.style.setProperty('--dl', (Math.random() * 5).toFixed(2) + 's');
          box.appendChild(s);
        }
      });

      /* ── 떠오르기 — 화면에 들어온 것부터 ── */
      function startReveal() {
        var items = $$('[data-rv]');
        if (!motion || !('IntersectionObserver' in window)) { items.forEach(function (el) { el.classList.add('is-in'); }); return; }
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
        }, { rootMargin: '0px 0px -8% 0px', threshold: .12 });
        items.forEach(function (el) { io.observe(el); });
      }

      /* ── 숫자 올리기 ── */
      function countUp() {
        $$('[data-count]').forEach(function (el) {
          var to = +el.getAttribute('data-count'), t0 = null;
          if (!motion) return;
          el.textContent = '0';
          setTimeout(function () {
            requestAnimationFrame(function step(t) {
              if (t0 === null) t0 = t;
              var p = Math.min(1, (t - t0) / 1100);
              el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
              if (p < 1) requestAnimationFrame(step);
            });
          }, 1300);
        });
      }

      /* ── 머리 — 스크롤하면 떠서 따라오고, 지금 보는 구획을 알약이 가리킨다 ── */
      var header = $('#wlHeader'), nav = $('#wlNav'), ind = $('.wl-nav-ind', nav), tabs = $$('.wl-nav-tab', nav);
      var active = tabs[0], hovered = null;
      function placeInd(tab) {
        if (!tab) return;
        ind.style.width = tab.offsetWidth + 'px';
        ind.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
      }
      function setActive(tab) {
        if (tab === active) return;
        active.classList.remove('is-active'); active.removeAttribute('aria-current');
        active = tab; active.classList.add('is-active'); active.setAttribute('aria-current', 'true');
        if (!hovered) placeInd(active);
      }
      function initNav() {
        placeInd(active);
        requestAnimationFrame(function () { nav.classList.add('has-ind'); });
        tabs.forEach(function (t) {
          t.addEventListener('mouseenter', function () { hovered = t; placeInd(t); });
          t.addEventListener('mouseleave', function () { hovered = null; placeInd(active); });
        });
        addEventListener('resize', function () { placeInd(hovered || active); });
      }
      var spies = tabs.map(function (t) { return document.getElementById(t.getAttribute('data-spy')); });

      /* ── 스크롤에 걸린 것들 ── */
      var s02 = $('#workflow'), s04 = $('#login'), bar = $('.wl-progress');
      function onScroll() {
        var y = scrollY, vh = innerHeight, max = document.documentElement.scrollHeight - vh;
        bar.style.setProperty('--sp', max > 0 ? (y / max).toFixed(4) : 0);
        header.classList.toggle('is-stuck', y > 110);
        var cur = tabs[0];
        spies.forEach(function (sec, i) { if (sec && sec.getBoundingClientRect().top < vh * .45) cur = tabs[i]; });
        setActive(cur);
        if (!motion) return;
        var r = s02.getBoundingClientRect();
        if (r.bottom > 0 && r.top < vh) {
          var p = (vh - r.top) / (vh + r.height);           /* 0 → 1 */
          s02.style.setProperty('--wmx', ((.5 - p) * 360).toFixed(1) + 'px');
          s02.style.setProperty('--bgy', ((p - .5) * -80).toFixed(1) + 'px');
          s02.style.setProperty('--bgs', 1.08);
        }
        var q = s04.getBoundingClientRect();
        if (q.bottom > 0 && q.top < vh) {
          var k = Math.min(1, Math.max(0, (vh - q.top) / vh));
          s04.style.setProperty('--kb', (1.14 - .14 * k).toFixed(4));
        }
      }
      addEventListener('scroll', onScroll, { passive: true });
      addEventListener('resize', onScroll);

      if (motion) {
        /* ── 첫 화면 — 그림이 마우스 쪽으로 기울고 빛이 따라온다 ── */
        var panel = $('#wlPanel'), art = $('.wl-art', panel);
        var tx = 0, ty = 0, cx = 0, cy = 0, gx = 70, gy = 40;
        panel.addEventListener('pointermove', function (e) {
          var r = panel.getBoundingClientRect();
          var nx = (e.clientX - r.left) / r.width, ny = (e.clientY - r.top) / r.height;
          tx = (nx - .5) * 2; ty = (ny - .5) * 2;
          var a = art.getBoundingClientRect();
          gx = ((e.clientX - a.left) / a.width * 100); gy = ((e.clientY - a.top) / a.height * 100);
          panel.classList.add('is-hover');
        });
        panel.addEventListener('pointerleave', function () { tx = ty = 0; panel.classList.remove('is-hover'); });
        (function loop() {
          cx = lerp(cx, tx, .06); cy = lerp(cy, ty, .06);
          art.style.setProperty('--px', (cx * -16).toFixed(2) + 'px');
          art.style.setProperty('--py', (cy * -12).toFixed(2) + 'px');
          art.style.setProperty('--qx', (cx * 7).toFixed(2) + 'px');
          art.style.setProperty('--qy', (cy * 5).toFixed(2) + 'px');
          art.style.setProperty('--gx', gx.toFixed(1) + '%');
          art.style.setProperty('--gy', gy.toFixed(1) + '%');
          requestAnimationFrame(loop);
        })();

        /* ── 핵심 기능 카드 줄 — 왼쪽으로 흐르고, 스크롤을 굴리면 빨라지고, 마우스를 올리면 느려진다 ── */
        var track = $('#wlTrack');
        if (track) {
          var cards = track.children, half = cards.length / 2, setW = 0;
          var measure = function () {
            var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            setW = 0;
            for (var i = 0; i < half; i++) setW += parseFloat(getComputedStyle(cards[i]).width) + gap;
          };
          measure(); addEventListener('resize', measure);
          var x = 0, speed = 46, cur = speed, boost = 0, slow = false, seen = true, lastT = performance.now(), lastY = scrollY;
          track.addEventListener('pointerenter', function () { slow = true; });
          track.addEventListener('pointerleave', function () { slow = false; });
          addEventListener('scroll', function () { boost = Math.min(boost + Math.abs(scrollY - lastY) * 1.4, 1400); lastY = scrollY; }, { passive: true });
          new IntersectionObserver(function (e) { seen = e[0].isIntersecting; }).observe(track);
          requestAnimationFrame(function tick(t) {
            var dt = Math.min((t - lastT) / 1000, .05); lastT = t;
            boost *= Math.pow(.03, dt);
            cur = lerp(cur, (slow ? speed * .15 : speed) + boost, Math.min(1, dt * 5));
            if (seen && setW) {
              x -= cur * dt;
              if (x <= -setW) x += setW;
              track.style.transform = 'translate3d(' + x.toFixed(2) + 'px,0,0)';
            }
            requestAnimationFrame(tick);
          });
        }

        /* ── 연동 기관 — 로고 무리가 깊이마다 다르게 마우스를 따른다 ── */
        var cluster = $('#wlCluster'), s03 = $('#partners'), ctx = 0, cty = 0, ccx = 0, ccy = 0;
        s03.addEventListener('pointermove', function (e) {
          var r = s03.getBoundingClientRect();
          ctx = ((e.clientX - r.left) / r.width - .5) * 2; cty = ((e.clientY - r.top) / r.height - .5) * 2;
        });
        s03.addEventListener('pointerleave', function () { ctx = cty = 0; });
        (function loop2() {
          ccx = lerp(ccx, ctx, .05); ccy = lerp(ccy, cty, .05);
          cluster.style.setProperty('--cx', (ccx * -22).toFixed(2) + 'px');
          cluster.style.setProperty('--cy', (ccy * -16).toFixed(2) + 'px');
          requestAnimationFrame(loop2);
        })();

        /* ── 카드 기울기 ── */
        $$('[data-tilt]').forEach(function (el) {
          el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect(), nx = (e.clientX - r.left) / r.width, ny = (e.clientY - r.top) / r.height;
            el.style.transform = 'perspective(900px) rotateX(' + ((.5 - ny) * 8).toFixed(2) + 'deg) rotateY(' + ((nx - .5) * 10).toFixed(2) + 'deg) translate3d(0,-8px,0)';
            el.style.setProperty('--gx', (nx * 100).toFixed(1) + '%'); el.style.setProperty('--gy', (ny * 100).toFixed(1) + '%');
            el.classList.add('is-tilt');
          });
          el.addEventListener('pointerleave', function () { el.style.transform = ''; el.classList.remove('is-tilt'); });
        });

        /* ── 버튼이 마우스에 살짝 끌린다 ── */
        $$('[data-magnet]').forEach(function (el) {
          el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            el.style.setProperty('--mx', (((e.clientX - r.left) / r.width - .5) * 10).toFixed(2) + 'px');
            el.style.setProperty('--my', (((e.clientY - r.top) / r.height - .5) * 8).toFixed(2) + 'px');
          });
          el.addEventListener('pointerleave', function () { el.style.setProperty('--mx', '0px'); el.style.setProperty('--my', '0px'); });
        });
      }

      /* ── 글꼴이 들어온 뒤에 막을 올린다(글자 폭이 바뀐 뒤에 알약을 잰다) ── */
      var started = false;
      function ready() {
        if (started) return; started = true;
        /* 뒤쪽 탭에서 열리면 requestAnimationFrame 이 멈춰 있어 타이머로 건다 */
        setTimeout(function () {
          body.classList.add('is-ready');
          initNav(); startReveal(); countUp(); onScroll();
        }, 30);
      }
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(ready);
      setTimeout(ready, 1500);
    })();
  </script>
</body>
</html>
