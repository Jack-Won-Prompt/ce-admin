/*
 * 날짜 칸에 직접 쳐 넣기 · 붙여넣기.
 *
 * 브라우저의 날짜 칸(input[type=date])은 달력으로 고르거나 년·월·일을 칸칸이 치는
 * 것만 받는다. 다른 표에서 「2026-08-01」을 복사해 붙이면 아무 일도 일어나지 않고,
 * 구분자(-, ., /)를 치면 그 자리에서 입력이 끊긴다. 날짜를 옮겨 적는 일이 잦은
 * 화면에서는 그때마다 세 칸을 따로 쳐야 했다.
 *
 * 칸의 종류를 바꾸지는 않는다 — 달력도 그대로 쓰고, 화면마다 걸어 둔 모양(css)도
 * 그대로 산다. 붙여넣기를 가로채 값을 넣어 주고, 구분자 키만 삼켜 숫자가 이어지게 한다.
 *
 * 받는 꼴: 2026-08-01 · 2026.8.1 · 2026/08/01 · 20260801 · 26-08-01 · 2026년 8월 1일
 */
(function () {
  'use strict';

  var SEPARATORS = ['-', '.', '/', ',', ' '];

  /**
   * 사람이 적어 준 글을 YYYY-MM-DD 로 옮긴다. 못 읽으면 null.
   *
   * 구분자를 먼저 본다 — 「2026.8.1」처럼 월ㆍ일이 한 자리인 것을 숫자만 남겨 읽으면
   * 202681 이 되어 스무여섯째 달이 된다. 구분자가 그 경계를 알려 준다.
   */
  function parse(text) {
    if (!text) return null;

    var t = String(text).trim();
    var m;

    // 2026-08-01 · 2026.8.1 · 2026/8/1 · 2026년 8월 1일
    m = t.match(/^(\d{4})\D+(\d{1,2})\D+(\d{1,2})\D*$/);
    if (m) return check(m[1], pad(m[2]), pad(m[3]));

    // 26-08-01 — 두 자리 해
    m = t.match(/^(\d{2})\D+(\d{1,2})\D+(\d{1,2})\D*$/);
    if (m) return check(century(m[1]), pad(m[2]), pad(m[3]));

    var digits = t.replace(/[^0-9]/g, '');

    if (digits.length === 8) {
      return check(digits.slice(0, 4), digits.slice(4, 6), digits.slice(6, 8));
    }

    /* 여섯 자리는 260801(연-월-일)로만 읽는다. 202608(연-월)은 날이 없어
       날짜가 못 되고, 어차피 스무여섯째 달로 걸린다. */
    if (digits.length === 6) {
      return check(century(digits.slice(0, 2)), digits.slice(2, 4), digits.slice(4, 6));
    }

    return null;
  }

  function pad(n) {
    return n.length === 1 ? '0' + n : n;
  }

  /* 두 자리 해 — 오늘로부터 앞뒤 50년 안으로 본다. 처방전에는 지난 날짜가
     오고 위임 종료일에는 앞으로의 날짜가 온다. */
  function century(yy) {
    var n    = parseInt(yy, 10);
    var here = new Date().getFullYear();
    var a    = 2000 + n;
    var b    = 1900 + n;

    return String(Math.abs(a - here) <= 50 ? a : b);
  }

  /** 있는 날인가 — 2026-02-31 같은 것을 걸러 낸다 */
  function check(y, m, d) {
    var date = new Date(+y, +m - 1, +d);

    if (date.getFullYear() !== +y || date.getMonth() !== +m - 1 || date.getDate() !== +d) {
      return null;
    }

    return y + '-' + m + '-' + d;
  }

  /** 값을 넣고 화면에 알린다 — 바뀐 값을 보고 셈하는 자리들이 있다 */
  function apply(input, iso) {
    input.value = iso;
    input.dispatchEvent(new Event('input',  { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function isDateInput(el) {
    return el && el.tagName === 'INPUT' && el.type === 'date' && !el.readOnly && !el.disabled;
  }

  /* 붙여넣기 — 날짜 칸에서도 paste 는 온다. 가로채 우리가 넣는다. */
  document.addEventListener('paste', function (e) {
    if (!isDateInput(e.target)) return;

    var text = (e.clipboardData || window.clipboardData);
    var iso  = parse(text && text.getData('text'));

    if (!iso) return;   // 못 읽으면 브라우저에게 그대로 넘긴다

    e.preventDefault();
    apply(e.target, iso);
  }, true);

  /* 끌어다 놓기도 같은 길로 — 표에서 칸을 끌어 오는 사람이 있다 */
  document.addEventListener('drop', function (e) {
    if (!isDateInput(e.target)) return;

    var iso = parse(e.dataTransfer && e.dataTransfer.getData('text'));
    if (!iso) return;

    e.preventDefault();
    apply(e.target, iso);
  }, true);

  /* 구분자 키는 삼킨다.
     「2026-08-01」을 치면 브라우저는 2026 까지 받고 「-」에서 멈춘다. 그 키를 없애면
     숫자만 이어져 브라우저가 알아서 년 → 월 → 일로 넘어간다 — 곧 「20260801」을
     친 것과 같아진다. */
  document.addEventListener('keydown', function (e) {
    if (!isDateInput(e.target)) return;
    if (e.ctrlKey || e.metaKey || e.altKey) return;

    if (SEPARATORS.indexOf(e.key) !== -1) {
      e.preventDefault();
    }
  }, true);
})();
