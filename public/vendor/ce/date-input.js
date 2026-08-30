/*
 * 날짜 칸에 직접 쳐 넣기 · 붙여넣기.
 *
 * 브라우저의 날짜 칸(input[type=date])은 년ㆍ월ㆍ일을 칸칸이 나눠 받는다. 그런데 연도
 * 칸이 여섯 자리까지 삼켜, 「20260101」을 이어 치면 연도가 202601 이 되고 일이 비어
 * 「202601-01-일」로 남는다. 구분자(-)는 아예 무시되어 「2026-01-01」도 같은 꼴이 된다.
 * 다른 표에서 복사해 온 날짜를 붙여넣는 것도 되지 않았다.
 *
 * 그래서 보이는 칸을 글자 칸으로 바꾼다 — 사람이 치는 대로 받고, 칸을 떠날 때
 * YYYY-MM-DD 로 맞춘다. 달력은 그대로다: 옆 단추가 숨은 날짜 칸의 달력을 연다.
 *
 * 담기는 값은 전과 같은 YYYY-MM-DD 라, 이 값을 읽고 쓰는 화면 코드와 서버 검증은
 * 손대지 않아도 된다.
 *
 * 받는 꼴: 2026-08-01 · 2026.8.1 · 2026/08/01 · 20260801 · 26-08-01 · 2026년 8월 1일
 */
(function () {
  'use strict';

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

  /** 바뀐 값을 보고 셈하는 자리들이 있다 — 알려 준다 */
  function fire(el) {
    el.dispatchEvent(new Event('input',  { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  /**
   * 이 칸을 바꿔도 되는가.
   *
   * wwGrid 가 칸 안에서 만들어 쓰는 날짜 칸은 건드리지 않는다 — 표는 제 달력
   * (CalendarPopup)과 제 편집 흐름을 갖고 있어, 여기서 종류를 바꾸면 그것이 끊긴다.
   */
  function eligible(el) {
    return el
        && el.tagName === 'INPUT'
        && el.type === 'date'
        && el.dataset.ceDate !== '1'
        && !el.closest('.cg-root');
  }

  /** 날짜 칸 하나를 글자 칸으로 바꾸고, 옆에 달력 단추를 붙인다 */
  function upgrade(el) {
    if (!eligible(el)) return;

    var value = el.value;                 // 종류를 바꾸기 전에 챙긴다

    el.type           = 'text';
    el.dataset.ceDate = '1';
    el.value          = value;
    el.autocomplete   = 'off';
    el.inputMode      = 'numeric';
    el.maxLength      = 10;
    if (!el.placeholder) el.placeholder = 'YYYY-MM-DD';

    /* 달력을 여는 칸. 값을 담지도 이름을 갖지도 않고 오직 달력만 연다 —
       담기는 값은 원래 칸 하나에만 있어야 저장하는 쪽이 헷갈리지 않는다. */
    var picker = document.createElement('input');
    picker.type     = 'date';
    picker.tabIndex = -1;
    picker.setAttribute('aria-hidden', 'true');
    picker.style.cssText = 'position:absolute;left:0;bottom:0;width:1px;height:1px;'
                         + 'padding:0;border:0;opacity:0;pointer-events:none;';

    var btn = document.createElement('button');
    btn.type      = 'button';
    btn.className = 'ce-date-btn';
    btn.tabIndex  = -1;
    btn.title     = '달력에서 고르기';
    btn.innerHTML = '<i class="bx bx-calendar"></i>';

    var wrap = document.createElement('span');
    wrap.className = 'ce-date-wrap';

    /* 칸에 걸어 둔 표(.edit-only 같은 것)를 껍데기에도 옮긴다.
       감싸고 나면 화면의 「보기 모드에서는 숨긴다」 규칙이 칸에만 닿아, 칸은 숨고
       껍데기와 달력 단추만 그대로 떠 있었다 — 고치기를 누르지도 않았는데 달력
       아이콘이 줄줄이 보였다.
       form-control 만 뺀다: 그것은 칸의 생김새라 껍데기가 쓰면 테두리가 두 겹이 된다. */
    for (var ci = 0; ci < el.classList.length; ci++) {
      var cls = el.classList[ci];
      if (cls !== 'form-control') wrap.classList.add(cls);
    }

    /* 칸을 껍데기로 감싸면 줄에서 자리를 잡던 주체가 칸에서 껍데기로 바뀐다.
       칸에 걸려 있던 자리 규칙(flex·width)을 껍데기로 옮기고, 칸은 껍데기를 채운다 —
       그러지 않으면 「flex:1」로 늘어나던 칸이 제 글자만큼만 서서 줄이 어그러진다. */
    ['flex', 'flexGrow', 'flexShrink', 'flexBasis', 'width', 'minWidth', 'maxWidth', 'gridColumn']
      .forEach(function (k) {
        var v = el.style[k];
        if (v) { wrap.style[k] = v; el.style[k] = ''; }
      });
    el.style.width = '100%';

    el.parentNode.insertBefore(wrap, el);
    wrap.appendChild(el);
    wrap.appendChild(btn);
    wrap.appendChild(picker);

    if (el.readOnly || el.disabled) {
      btn.style.display = 'none';
    }

    btn.addEventListener('click', function () {
      picker.value = parse(el.value) || '';
      // showPicker 는 사람이 누른 그 자리에서만 열린다 — 없는 브라우저는 그냥 지나간다
      if (typeof picker.showPicker === 'function') {
        try { picker.showPicker(); } catch (e) { /* 못 열면 그만이다 — 손으로 칠 수 있다 */ }
      }
    });

    picker.addEventListener('change', function () {
      if (!picker.value) return;
      el.value = picker.value;
      fire(el);
    });

    /* 칸을 떠날 때 꼴을 맞춘다. 치는 동안 고쳐 대면 커서가 튀어 다음 자리를 못 친다.
       못 읽을 글은 지운다 — 남겨 두면 저장할 때 서버가 거절하고, 무엇이 잘못인지는
       그 화면에서 알 수 없다. */
    el.addEventListener('blur', function () {
      var raw = el.value.trim();
      if (raw === '') return;

      var iso = parse(raw);
      if (iso === raw) return;

      el.value = iso || '';
      fire(el);
    });
  }

  /** 붙여넣기 — 어떤 꼴이든 읽어 넣는다 */
  document.addEventListener('paste', function (e) {
    var el = e.target;
    if (!el || el.dataset == null || el.dataset.ceDate !== '1') return;
    if (el.readOnly || el.disabled) return;

    var data = e.clipboardData || window.clipboardData;
    var iso  = parse(data && data.getData('text'));
    if (!iso) return;

    e.preventDefault();
    el.value = iso;
    fire(el);
  }, true);

  /* 화면에 있는 것을 바꾸고, 나중에 생기는 것(창ㆍ표ㆍ탭에서 그려지는 칸)도 따라 바꾼다.
     한 화면에 날짜 칸이 스물 넘게 있고 그 가운데 여럿이 눌러야 나타난다. */
  function sweep(root) {
    var list = (root || document).querySelectorAll('input[type="date"]');
    for (var i = 0; i < list.length; i++) upgrade(list[i]);
  }

  function start() {
    sweep(document);

    new MutationObserver(function (records) {
      for (var i = 0; i < records.length; i++) {
        var added = records[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var n = added[j];
          if (n.nodeType !== 1) continue;
          if (n.matches && n.matches('input[type="date"]')) upgrade(n);
          else sweep(n);
        }
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
