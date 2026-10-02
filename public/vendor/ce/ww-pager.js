/**
 * 목록 표의 쪽 나누기 — 모든 목록 화면이 같은 모양으로 쓴다 (2026-10-02 지시).
 *
 *   「모든 화면 메인 wwGrid에서 페이징이 없으면 페이징 똑같이 구현」
 *   「목록 전부, 화면에서 탭으로 구성된 내부의 wwGrid는 제외」
 *   「합계와 엑셀은 둘 다 전체로 지킵니다」
 *
 * 쓰는 법 — 표를 세운 뒤 한 줄이면 된다.
 *
 *   const grid = new wwGrid({ ... , data: ROWS });
 *   cePager.붙이기(grid, { el: 'orderPager' });
 *
 * ## 왜 겉에서 감싸는가
 *
 * wwGrid 를 고치지 않는다. 쉰 화면이 같은 표를 쓰고 있어, 표 쪽을 건드리면 쪽을
 * 나누지 않는 화면까지 함께 흔들린다. 표가 내준 자리(`setData`ㆍ`downloadExcel`ㆍ
 * `_sortData`ㆍ`_updateFooter`)만 감싸 쓴다.
 *
 * ## 전체를 쥐고 한 쪽만 보여 준다
 *
 * 표에는 지금 쪽만 넣는다. 그래서 그냥 두면 세 가지가 어긋난다 — 셋 다 막았다.
 *
 *   ① 아랫줄 「전체 N건」  : 표는 `this.data.length` 를 센다 → 500 이라 적힌다
 *   ② 엑셀 다운           : `getData()` 를 담는다 → 한 쪽만 담긴다
 *   ③ 칸 제목 눌러 정렬    : `this.data` 를 정렬한다 → 보던 쪽 안에서만 정렬된다
 *
 * ## 쪽이 하나뿐이어도 건수는 보인다
 *
 * 500건이 안 되는 화면이 대부분이다. 그때 줄 전체를 감추면 총 건수까지 사라진다 —
 * 아무것도 없을 때만 감추고, 한 쪽일 때는 건수만 두고 단추를 비운다.
 */
(function () {
  'use strict';

  /** 한 쪽에 몇 줄 (2026-10-02 지시) */
  const 기본쪽크기 = 500;

  /** 쪽 번호를 몇 개까지 늘어놓나 — 지금 쪽 양옆으로 둘씩 */
  const 양옆 = 2;

  /* 모양은 현금/카드영수증ㆍ전자세금계산서 화면과 같다. 그 화면들은 자기 스타일을
     이미 들고 있어, 여기서 넣는 것은 아직 없는 화면을 위한 것이다. 같은 이름이면
     화면 쪽 스타일이 이긴다(뒤에 선다). */
  const 스타일 = `
.ce-pager { padding:12px; border-top:1px solid var(--border, #e5e7eb); display:flex;
            align-items:center; justify-content:center; gap:8px; flex-shrink:0; }
.ce-pager-info, .ce-pager-spacer { flex:1 1 0; min-width:0; }
.ce-pager-info { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600, #4b5563); }
.ce-pager-btns { display:flex; gap:6px; flex:none; }
.ce-pager-btn { height:28px; min-width:28px; padding:0 6px; border:1px solid var(--gray-200, #e5e7eb);
                border-radius:6px; background:var(--gray-0, #fff); font-size:13px; font-weight:500;
                line-height:21px; cursor:pointer; color:var(--gray-1000, #111827); }
.ce-pager-btn:hover:not(:disabled) { border-color:var(--primary, #0a3d62); color:var(--primary, #0a3d62); }
.ce-pager-btn.on { background:var(--primary-light, #e8eef4); color:var(--primary, #0a3d62); }
.ce-pager-btn:disabled { opacity:.4; cursor:not-allowed; }
`;

  let 스타일넣었나 = false;

  function 스타일세우기() {
    if (스타일넣었나) return;
    스타일넣었나 = true;
    const s = document.createElement('style');
    s.id = 'ce-pager-style';
    s.textContent = 스타일;
    document.head.appendChild(s);
  }

  /**
   * 쪽 나누기를 붙인다.
   *
   * @param {object} grid  wwGrid 인스턴스
   * @param {object} 설정
   *   el       쪽 줄을 그릴 자리(id 글자 또는 요소). 없으면 표 바로 아래에 만든다
   *   perPage  한 쪽에 몇 줄 (기본 500)
   */
  function 붙이기(grid, 설정 = {}) {
    if (!grid || grid.__cePager) return grid && grid.__cePager;

    스타일세우기();

    const 쪽크기 = Math.max(1, 설정.perPage || 기본쪽크기);
    const 자리   = 자리찾기(grid, 설정.el);
    if (!자리) return null;

    /* 표가 이미 들고 있던 줄이 전체다 — `data:` 로 세운 화면이 그렇다 */
    let 전체 = Array.isArray(grid.data) ? grid.data.slice() : [];
    let 쪽   = 1;

    const 나 = {
      get 전체수() { return 전체.length; },
      get 쪽번호() { return 쪽; },
    };

    /* ── 표가 내준 자리를 감싼다 ───────────────────────────────── */

    const 원래넣기 = grid.setData.bind(grid);
    const 원래엑셀 = grid.downloadExcel.bind(grid);
    const 원래정렬 = grid._sortData.bind(grid);
    const 원래합계 = grid._updateFooter.bind(grid);

    /** 지금 쪽을 표에 올린다 — 표에는 늘 한 쪽만 들어간다 */
    function 쪽올리기(새쪽) {
      const 쪽수 = Math.max(1, Math.ceil(전체.length / 쪽크기));
      쪽 = Math.min(Math.max(1, 새쪽 | 0), 쪽수);

      const 첫 = (쪽 - 1) * 쪽크기;

      /* 줄 번호를 이어 센다 — 표가 `rowNumberStart` 를 그대로 더해 그린다.
         주지 않으면 3쪽에서도 1부터 세어, 「1,001~1,500」을 보면서 번호는 1부터다. */
      grid.rowNumberStart = 첫;

      원래넣기(전체.slice(첫, 첫 + 쪽크기));
      그리기();
    }

    /* 화면이 `setData` 로 새 결과를 넣으면 그것이 새 전체다. 쪽은 처음으로 돌아간다 —
       거르개를 바꿔 조회했는데 7쪽에 머물러 있으면 빈 화면이 보인다. */
    grid.setData = function (rows) {
      전체 = Array.isArray(rows) ? rows.slice() : [];
      쪽올리기(1);
      /* 표 안쪽이 다시 그려졌으니 높이를 다시 잰다 — 탭처럼 숨었다 나타난 표가
         `height:'fit'` 을 0 으로 읽어 두는 일이 있다 */
      if (typeof grid.fit === 'function') { grid.fit(); }
    };

    /** 엑셀은 **전체**를 담는다 — 보던 쪽만 담으면 받은 사람이 빠진 줄을 모른다 */
    grid.downloadExcel = function (opts) {
      const 보던것 = grid.data;
      grid.data = 전체;
      try {
        return 원래엑셀(opts);
      } finally {
        grid.data = 보던것;
      }
    };

    /** 정렬은 **전체**를 줄 세운 뒤 첫 쪽부터 다시 본다 */
    grid._sortData = function () {
      const { colName, direction } = grid._sortState || {};
      if (!colName) { return 원래정렬(); }

      전체.sort((a, b) => {
        const va = a[colName], vb = b[colName];
        if (va === vb) return 0;
        const cmp = va < vb ? -1 : 1;
        return direction === 'asc' ? cmp : -cmp;
      });

      쪽올리기(1);
      if (typeof grid._updateSortIcons === 'function') { grid._updateSortIcons(); }
    };

    /** 아랫줄의 「전체 N건」은 **전체** 건수다 */
    grid._updateFooter = function () {
      원래합계();
      const 칸 = grid._footerEl && grid._footerEl.querySelector('span strong');
      if (칸) { 칸.textContent = 전체.length.toLocaleString('ko-KR'); }
    };

    /* ── 쪽 줄 그리기 ─────────────────────────────────────────── */

    자리.innerHTML =
      '<div class="ce-pager-info"></div>' +
      '<div class="ce-pager-btns"></div>' +
      '<div class="ce-pager-spacer"></div>';

    const 건수칸 = 자리.querySelector('.ce-pager-info');
    const 단추칸 = 자리.querySelector('.ce-pager-btns');

    자리.addEventListener('click', (e) => {
      const b = e.target.closest('button[data-page]');
      if (b && !b.disabled) { 쪽올리기(Number(b.dataset.page)); }
    });

    function 그리기() {
      const 쪽수 = Math.ceil(전체.length / 쪽크기);

      if (전체.length === 0) { 자리.style.display = 'none'; return; }

      자리.style.display = 'flex';
      건수칸.textContent = '전체 ' + 전체.length.toLocaleString('ko-KR') + '건'
        + (쪽수 > 1 ? ' · ' + 쪽 + '/' + 쪽수 + '쪽' : '');

      if (쪽수 <= 1) { 단추칸.innerHTML = ''; return; }

      const 글 = [];
      const 첫 = Math.max(1, 쪽 - 양옆);
      const 끝 = Math.min(쪽수, 쪽 + 양옆);

      글.push(단추(1, '«', 쪽 === 1));
      글.push(단추(쪽 - 1, '‹', 쪽 === 1));
      /* 첫 쪽이 멀면 그 자리를 알려 준다 — 1 로 돌아가는 길이 늘 보여야 한다 */
      if (첫 > 1) { 글.push(단추(1, '1', false)); if (첫 > 2) { 글.push('<span class="ce-pager-btn" style="border:none;background:none;cursor:default;">…</span>'); } }
      for (let p = 첫; p <= 끝; p++) { 글.push(단추(p, String(p), false, p === 쪽)); }
      if (끝 < 쪽수) { if (끝 < 쪽수 - 1) { 글.push('<span class="ce-pager-btn" style="border:none;background:none;cursor:default;">…</span>'); } 글.push(단추(쪽수, String(쪽수), false)); }
      글.push(단추(쪽 + 1, '›', 쪽 === 쪽수));
      글.push(단추(쪽수, '»', 쪽 === 쪽수));

      단추칸.innerHTML = 글.join('');
    }

    const 단추 = (p, 글자, 막나, 지금 = false) =>
      '<button type="button" class="ce-pager-btn' + (지금 ? ' on' : '') + '"'
      + ' data-page="' + p + '"' + (막나 ? ' disabled' : '') + '>' + 글자 + '</button>';

    /* 세운 그 자리에서 첫 쪽을 보인다 — 표가 `data:` 로 받아 둔 것이 전체다 */
    쪽올리기(1);

    grid.__cePager = 나;

    return 나;
  }

  /**
   * 쪽 줄을 그릴 자리를 찾는다 — 없으면 표 바로 아래에 만든다.
   *
   * 화면마다 자리를 손으로 넣게 하면 쉰 곳에 쉰 번 넣어야 하고, 한 곳을 빠뜨리면
   * 그 화면만 쪽 줄이 없다. 적어 준 자리가 없으면 표를 담은 칸 다음에 세운다.
   */
  function 자리찾기(grid, 적힌것) {
    if (적힌것) {
      const el = typeof 적힌것 === 'string' ? document.getElementById(적힌것) : 적힌것;
      if (el) { el.classList.add('ce-pager'); return el; }
    }

    /* `grid.el` 은 DOM 요소다(wwGrid 가 options.el 을 그대로 쥔다) */
    const 표칸 = grid.el && grid.el.nodeType === 1 ? grid.el : null;
    if (!표칸) return null;

    const 이미 = 표칸.parentElement && 표칸.parentElement.querySelector(':scope > .ce-pager');
    if (이미) return 이미;

    const 새것 = document.createElement('div');
    새것.className = 'ce-pager';
    새것.style.display = 'none';
    표칸.insertAdjacentElement('afterend', 새것);

    return 새것;
  }

  window.cePager = { 붙이기, 기본쪽크기 };
})();
