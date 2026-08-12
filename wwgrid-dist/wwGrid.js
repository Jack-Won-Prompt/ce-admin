/**
 * wwGrid — Withworks Grid JavaScript 그리드
 * 컬럼 에디터: text | number | date | checkbox | combo | popup
 * 헤더 그루핑, 수정 추적, 서버 전송용 데이터 추출
 */

/* ══════════════════════════════════════════════════════════
   GridModal — 팝업 모달
══════════════════════════════════════════════════════════ */
class GridModal {
  constructor() {
    this._overlay   = null;
    this._escBind   = null;
    this._onConfirm = null;
  }

  /**
   * @param {object} opts
   * @param {string}   opts.title         - 모달 제목
   * @param {number}   [opts.width=480]   - 너비(px)
   * @param {number}   [opts.height=420]  - 최대 높이(px)
   * @param {Array}    [opts.items]       - 내장 리스트 [{value, label}]
   * @param {Function} [opts.render]      - 커스텀 렌더러 (container, currentValue, done) => void
   * @param {*}        opts.currentValue  - 현재 선택된 값
   * @param {Function} opts.onConfirm     - (value, label) => void
   */
  open(opts) {
    this.close();
    const { title, width = 480, height = 420, items, render, onSearch,
            currentValue, onConfirm, popupTheme } = opts;
    this._onConfirm = onConfirm;

    // ── 오버레이 & 모달 ──
    const overlay = document.createElement('div');
    overlay.className = 'cg-modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'cg-modal';
    modal.style.width    = width + 'px';
    modal.style.maxHeight = height + 'px';

    // 헤더
    const hdr = document.createElement('div');
    hdr.className = 'cg-modal-header';
    hdr.innerHTML = `
      <span class="cg-modal-title">${title || '선택'}</span>
      <button class="cg-modal-close" title="닫기">✕</button>`;

    // 바디
    const body = document.createElement('div');
    body.className = 'cg-modal-body';

    modal.appendChild(hdr);
    modal.appendChild(body);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    this._overlay = overlay;

    // ── 팝업 색상 테마 인라인 적용 ──
    this._applyPopupTheme(modal, hdr, body, popupTheme || {});

    // ── 내용 렌더링 ──
    const done = (value, label) => {
      if (this._onConfirm) this._onConfirm(value, label);
      this.close();
    };

    if (render) {
      render(body, currentValue, done);
    } else if (onSearch) {
      this._renderRemoteSearch(body, onSearch, currentValue, done);
    } else if (items) {
      this._renderItemList(body, items, currentValue, done);
    }

    // ── 이벤트 ──
    hdr.querySelector('.cg-modal-close').onclick = () => this.close();
    overlay.addEventListener('mousedown', e => { if (e.target === overlay) this.close(); });
    this._escBind = e => { if (e.key === 'Escape') this.close(); };
    document.addEventListener('keydown', this._escBind);
  }

  /**
   * 외부 API 검색 모드
   * @param {HTMLElement} body
   * @param {Function}    onSearch  - async (query: string) => Array<{value, label, sub?}>
   * @param {*}           currentValue
   * @param {Function}    done      - (value, label) => void
   */
  _renderRemoteSearch(body, onSearch, currentValue, done) {
    // ── 검색창 ──
    const searchWrap = document.createElement('div');
    searchWrap.className = 'cg-modal-search-wrap';
    const search = document.createElement('input');
    search.className = 'cg-modal-search';
    search.placeholder = '코드 또는 이름으로 검색...';
    searchWrap.appendChild(search);
    body.appendChild(searchWrap);

    // ── 상태 배지 ──
    const status = document.createElement('div');
    status.className = 'cg-modal-counter';
    body.appendChild(status);

    // ── 목록 ──
    const list = document.createElement('div');
    list.className = 'cg-modal-list';
    list.innerHTML = '<div class="cg-modal-hint">코드나 이름을 입력하면 검색합니다.</div>';
    body.appendChild(list);

    let _timer   = null;
    let _seq     = 0;   // 요청 순서 추적 (응답 역전 방지)

    const renderItems = (results, q) => {
      list.innerHTML = '';
      results.forEach(it => {
        const value = typeof it === 'object' ? it.value : it;
        const label = typeof it === 'object' ? it.label : String(it);
        const el    = document.createElement('div');
        el.className = 'cg-modal-item' + (value === currentValue ? ' selected' : '');
        // 검색어 하이라이트
        const hl = q
          ? label.replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'),
                          '<mark>$1</mark>')
          : label;
        el.innerHTML = `<span>${hl}</span>`;
        if (it.sub) {
          const sub = document.createElement('small');
          sub.className = 'cg-modal-item-sub';
          sub.textContent = it.sub;
          el.appendChild(sub);
        }
        el.onclick = () => done(value, label);
        list.appendChild(el);
      });
      if (!results.length) {
        list.innerHTML = '<div class="cg-modal-empty">검색 결과가 없습니다.</div>';
      }
    };

    const doSearch = async (q) => {
      const mySeq = ++_seq;
      // 로딩 표시
      status.innerHTML = '<span class="cg-modal-loading"><span class="cg-spinner"></span>검색 중...</span>';
      list.innerHTML   = '';
      try {
        const results = await onSearch(q);
        if (mySeq !== _seq) return;  // 뒤늦게 도착한 응답 무시
        status.textContent = `${results.length}건`;
        renderItems(results, q);
      } catch (err) {
        if (mySeq !== _seq) return;
        status.textContent = '';
        list.innerHTML = '<div class="cg-modal-empty cg-modal-error">검색 중 오류가 발생했습니다.</div>';
        console.error('[wwGrid popup onSearch]', err);
      }
    };

    search.addEventListener('input', () => {
      const q = search.value.trim();
      clearTimeout(_timer);
      if (!q) {
        _seq++;
        status.textContent = '';
        list.innerHTML = '<div class="cg-modal-hint">코드나 이름을 입력하면 검색합니다.</div>';
        return;
      }
      status.innerHTML = '<span style="color:#aaa;font-size:11px;">입력 중...</span>';
      _timer = setTimeout(() => doSearch(q), 300);  // 300ms 디바운스
    });

    // Enter 키로 즉시 검색
    search.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const q = search.value.trim();
        if (q) { clearTimeout(_timer); doSearch(q); }
      }
    });

    setTimeout(() => search.focus(), 50);
  }

  /** 내장 검색 가능 리스트 */
  _renderItemList(body, items, currentValue, done) {
    // 검색창 (아이콘 래퍼 포함)
    const searchWrap = document.createElement('div');
    searchWrap.className = 'cg-modal-search-wrap';
    const search = document.createElement('input');
    search.className = 'cg-modal-search';
    search.placeholder = '이름, 코드로 검색...';
    searchWrap.appendChild(search);
    body.appendChild(searchWrap);

    // 아이템 수 배지
    const counter = document.createElement('div');
    counter.className = 'cg-modal-counter';
    body.appendChild(counter);

    // 리스트
    const list = document.createElement('div');
    list.className = 'cg-modal-list';
    body.appendChild(list);

    const renderList = (q = '') => {
      const lower = q.toLowerCase();
      const filtered = q
        ? items.filter(it => {
            const l = typeof it === 'object' ? it.label : String(it);
            const v = typeof it === 'object' ? String(it.value) : String(it);
            return l.toLowerCase().includes(lower) || v.toLowerCase().includes(lower);
          })
        : items;

      counter.textContent = q
        ? `${filtered.length}건 검색됨 (전체 ${items.length}건)`
        : `전체 ${items.length}건`;
      list.innerHTML = '';

      filtered.forEach(it => {
        const value = typeof it === 'object' ? it.value : it;
        const label = typeof it === 'object' ? it.label : it;
        const el = document.createElement('div');
        el.className = 'cg-modal-item' + (value === currentValue ? ' selected' : '');
        el.innerHTML = q
          ? `<span>${label.replace(new RegExp(`(${q})`, 'gi'), '<mark>$1</mark>')}</span>`
          : `<span>${label}</span>`;
        if (it.sub) {
          const sub = document.createElement('small');
          sub.className = 'cg-modal-item-sub';
          sub.textContent = it.sub;
          el.appendChild(sub);
        }
        el.onclick = () => done(value, label);
        list.appendChild(el);
      });

      if (!filtered.length) {
        list.innerHTML = '<div class="cg-modal-empty">검색 결과가 없습니다.</div>';
      }
    };

    renderList();
    search.addEventListener('input', () => renderList(search.value));
    setTimeout(() => search.focus(), 50);
  }

  /** popup 색상 테마 인라인 적용 */
  _applyPopupTheme(modal, hdr, body, t) {
    const titleEl = hdr.querySelector('.cg-modal-title');
    const closeEl = hdr.querySelector('.cg-modal-close');

    // 헤더 배경
    if (t.headerBg) hdr.style.background = t.headerBg;

    // 헤더 텍스트 & 닫기 버튼 색
    if (t.headerText) {
      titleEl.style.color = t.headerText;
      closeEl.style.color = t.headerText;
      closeEl.style.borderColor = t.headerText + '44';
      closeEl.style.background  = t.headerText + '22';
    }

    // 바디 배경·텍스트
    if (t.bg)   { modal.style.background = t.bg; body.style.background = t.bg; }
    if (t.text) { body.style.color = t.text; }

    // 항목 hover·선택 배경 — 스코프 스타일 주입
    if (t.itemHoverBg || t.itemSelBg || t.itemSelText) {
      const uid = 'cg-pt-' + Date.now();
      modal.dataset.pt = uid;
      let css = '';
      if (t.itemHoverBg) css += `[data-pt="${uid}"] .cg-modal-item:hover{background:${t.itemHoverBg}!important;}\n`;
      if (t.itemSelBg)   css += `[data-pt="${uid}"] .cg-modal-item.selected{background:${t.itemSelBg}!important;}\n`;
      if (t.itemSelText) css += `[data-pt="${uid}"] .cg-modal-item.selected{color:${t.itemSelText}!important;}\n`;
      this._themeStyleEl = document.createElement('style');
      this._themeStyleEl.textContent = css;
      document.head.appendChild(this._themeStyleEl);
    }
  }

  close() {
    if (this._overlay)      { this._overlay.remove();      this._overlay      = null; }
    if (this._escBind)      { document.removeEventListener('keydown', this._escBind); this._escBind = null; }
    if (this._themeStyleEl) { this._themeStyleEl.remove(); this._themeStyleEl = null; }
    this._onConfirm = null;
  }

  get isOpen() { return !!this._overlay; }
}

/* ══════════════════════════════════════════════════════════
   Calendar Widget
══════════════════════════════════════════════════════════ */
class CalendarPopup {
  constructor() {
    this._popup = null;
    this._year = 0;
    this._month = 0;
    this._selected = null;
    this._onSelect = null;
    this._bindOutside = this._onOutsideClick.bind(this);
  }

  open(anchorEl, currentValue, onSelect) {
    this.close();
    this._onSelect = onSelect;

    const today = new Date();
    if (currentValue) {
      const parts = currentValue.split('-');
      this._year  = parseInt(parts[0], 10) || today.getFullYear();
      this._month = (parseInt(parts[1], 10) - 1) || today.getMonth();
      this._selected = currentValue;
    } else {
      this._year  = today.getFullYear();
      this._month = today.getMonth();
      this._selected = null;
    }

    this._popup = document.createElement('div');
    this._popup.className = 'cg-calendar-popup';
    document.body.appendChild(this._popup);
    this._render();

    const rect = anchorEl.getBoundingClientRect();
    let top  = rect.bottom + 2;
    let left = rect.left;
    if (top + 260 > window.innerHeight) top = rect.top - 265;
    if (left + 244 > window.innerWidth)  left = window.innerWidth - 248;
    this._popup.style.top  = top  + 'px';
    this._popup.style.left = left + 'px';

    setTimeout(() => document.addEventListener('mousedown', this._bindOutside), 0);
  }

  close() {
    if (this._popup) {
      this._popup.remove();
      this._popup = null;
      document.removeEventListener('mousedown', this._bindOutside);
    }
  }

  _onOutsideClick(e) {
    if (this._popup && !this._popup.contains(e.target)) this.close();
  }

  _render() {
    const MONTH_NAMES = ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'];
    const DAY_NAMES   = ['일','월','화','수','목','금','토'];
    const today       = new Date();
    const todayStr    = this._dateStr(today.getFullYear(), today.getMonth(), today.getDate());

    const firstDay = new Date(this._year, this._month, 1).getDay();
    const daysInMonth = new Date(this._year, this._month + 1, 0).getDate();
    const daysInPrev  = new Date(this._year, this._month, 0).getDate();

    let html = `
      <div class="cg-cal-header">
        <button class="cg-cal-nav" data-action="prev">&#8249;</button>
        <span class="cg-cal-title">${this._year}년 ${MONTH_NAMES[this._month]}</span>
        <button class="cg-cal-nav" data-action="next">&#8250;</button>
      </div>
      <div class="cg-cal-grid">
        ${DAY_NAMES.map(d => `<div class="cg-cal-day-name">${d}</div>`).join('')}
    `;

    // 이전 달 날짜
    for (let i = firstDay - 1; i >= 0; i--) {
      const d = daysInPrev - i;
      const m = this._month === 0 ? 11 : this._month - 1;
      const y = this._month === 0 ? this._year - 1 : this._year;
      const ds = this._dateStr(y, m, d);
      html += `<div class="cg-cal-day other-month" data-date="${ds}">${d}</div>`;
    }

    // 이번 달
    for (let d = 1; d <= daysInMonth; d++) {
      const ds = this._dateStr(this._year, this._month, d);
      let cls = 'cg-cal-day';
      if (ds === todayStr)       cls += ' today';
      if (ds === this._selected) cls += ' selected';
      html += `<div class="${cls}" data-date="${ds}">${d}</div>`;
    }

    // 다음 달 날짜
    const total = firstDay + daysInMonth;
    const remain = (7 - (total % 7)) % 7;
    for (let d = 1; d <= remain; d++) {
      const m = this._month === 11 ? 0 : this._month + 1;
      const y = this._month === 11 ? this._year + 1 : this._year;
      const ds = this._dateStr(y, m, d);
      html += `<div class="cg-cal-day other-month" data-date="${ds}">${d}</div>`;
    }

    html += `</div>
      <div class="cg-cal-footer">
        <button class="cg-cal-btn" data-action="clear">지우기</button>
        <button class="cg-cal-btn" data-action="today">오늘</button>
        <button class="cg-cal-btn primary" data-action="confirm">확인</button>
      </div>
    `;

    this._popup.innerHTML = html;

    this._popup.addEventListener('click', e => {
      const action = e.target.dataset.action;
      const date   = e.target.dataset.date;

      if (action === 'prev') {
        this._month--;
        if (this._month < 0) { this._month = 11; this._year--; }
        this._render();
      } else if (action === 'next') {
        this._month++;
        if (this._month > 11) { this._month = 0; this._year++; }
        this._render();
      } else if (action === 'today') {
        const t = new Date();
        this._selected = this._dateStr(t.getFullYear(), t.getMonth(), t.getDate());
        this._year  = t.getFullYear();
        this._month = t.getMonth();
        this._render();
      } else if (action === 'clear') {
        this._selected = null;
        if (this._onSelect) this._onSelect('');
        this.close();
      } else if (action === 'confirm') {
        if (this._onSelect) this._onSelect(this._selected || '');
        this.close();
      } else if (date) {
        this._selected = date;
        if (this._onSelect) this._onSelect(this._selected);
        this.close();
      }
    });
  }

  _dateStr(y, m, d) {
    return `${y}-${String(m + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
  }
}

/* ══════════════════════════════════════════════════════════
   ExcelExporter — Excel XML (.xls) 다운로드
══════════════════════════════════════════════════════════ */
class ExcelExporter {

  /** XML 특수문자 이스케이프 */
  static _esc(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /**
   * 엑셀 다운로드
   * @param {wwGrid} grid
   * @param {object} opts
   * @param {string}  [opts.filename='grid_export'] - 파일명 (확장자 제외)
   * @param {string}  [opts.sheetName='Sheet1']     - 워크시트명
   * @param {boolean} [opts.checkedOnly=false]      - 체크된 행만 출력
   * @param {boolean} [opts.includeSummary]         - Sum 행 포함 (기본: grid.summary 설정값)
   */
  static download(grid, opts = {}) {
    const cols = grid.columns.filter(c => c.exportable !== false);

    // 데이터 추출 (_rowIndex 제거)
    const rawData = opts.checkedOnly
      ? grid.getCheckedRows().map(({ _rowIndex, ...r }) => r)
      : grid.getData();

    const sheetName      = opts.sheetName || 'Sheet1';
    const includeSummary = opts.includeSummary !== undefined
      ? opts.includeSummary : !!grid.summary;
    const hasGroups      = grid.columnGroups.length > 0;

    const xml = [
      '<?xml version="1.0" encoding="UTF-8"?>',
      '<?mso-application progid="Excel.Sheet"?>',
      '<Workbook',
      '  xmlns="urn:schemas-microsoft-com:office:spreadsheet"',
      '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"',
      '  xmlns:x="urn:schemas-microsoft-com:office:excel">',
      this._styles(grid),
      `<Worksheet ss:Name="${this._esc(sheetName)}">`,
      '<Table>',
      this._colWidths(cols),
      this._headerRows(cols, grid.columnGroups),
      this._dataRows(cols, rawData, grid),
      includeSummary ? this._summaryRow(cols, rawData) : '',
      '</Table>',
      this._freezePane(hasGroups ? 2 : 1),
      '</Worksheet>',
      '</Workbook>',
    ].join('\n');

    const blob = new Blob(['\uFEFF' + xml], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const a    = Object.assign(document.createElement('a'), {
      href:     URL.createObjectURL(blob),
      download: (opts.filename || 'grid_export') + '.xls',
    });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  }

  /** 셀 스타일 정의 */
  static _styles(grid) {
    const t   = grid.theme || {};
    const hBg = (t.headerBg      || '#F0F3F7').toUpperCase();
    const gBg = (t.headerGroupBg || '#D8E3EF').toUpperCase();
    const sBg = (t.summaryBg     || '#EEF2F8').toUpperCase();

    const border = (color = '#C0C8D0', w = 1) =>
      ['Bottom','Right'].map(p =>
        `<Border ss:Position="${p}" ss:LineStyle="Continuous" ss:Weight="${w}" ss:Color="${color}"/>`
      ).join('');

    return `<Styles>
<Style ss:ID="Default">
  <Alignment ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10"/>
  <Borders>${border()}</Borders>
</Style>
<Style ss:ID="cgH">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10" ss:Bold="1"/>
  <Interior ss:Color="${hBg}" ss:Pattern="Solid"/>
  <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#8898AA"/>
    <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C0C8D0"/>
  </Borders>
</Style>
<Style ss:ID="cgGH">
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10" ss:Bold="1"/>
  <Interior ss:Color="${gBg}" ss:Pattern="Solid"/>
  <Borders>${border()}</Borders>
</Style>
<Style ss:ID="cgN">
  <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10"/>
  <Borders>${border('#E4E8ED')}</Borders>
  <NumberFormat ss:Format="#,##0"/>
</Style>
<Style ss:ID="cgS">
  <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10" ss:Bold="1"/>
  <Interior ss:Color="${sBg}" ss:Pattern="Solid"/>
  <Borders>
    <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#8898AA"/>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C0C8D0"/>
    <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C0C8D0"/>
  </Borders>
</Style>
<Style ss:ID="cgSN">
  <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
  <Font ss:FontName="맑은 고딕" ss:Size="10" ss:Bold="1"/>
  <Interior ss:Color="${sBg}" ss:Pattern="Solid"/>
  <Borders>
    <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#8898AA"/>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C0C8D0"/>
    <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#C0C8D0"/>
  </Borders>
  <NumberFormat ss:Format="#,##0"/>
</Style>
</Styles>`;
  }

  /** 컬럼 너비 */
  static _colWidths(cols) {
    return cols.map(c => `<Column ss:Width="${c.width || 100}"/>`).join('\n');
  }

  /** 헤더 행 (그루핑 지원) */
  static _headerRows(cols, groups) {
    if (!groups.length) {
      return '<Row ss:Height="24">\n'
        + cols.map(c => `<Cell ss:StyleID="cgH"><Data ss:Type="String">${this._esc(c.header)}</Data></Cell>`).join('\n')
        + '\n</Row>';
    }

    const inAnyGroup = name => groups.some(g => g.children.includes(name));
    let row1 = '<Row ss:Height="24">\n';
    let row2 = '<Row ss:Height="22">\n';
    let i = 0;

    while (i < cols.length) {
      const col = cols[i];
      const grp = groups.find(g => g.children[0] === col.name);

      if (grp) {
        const n  = grp.children.length;
        const ma = n > 1 ? ` ss:MergeAcross="${n - 1}"` : '';
        row1 += `<Cell ss:StyleID="cgGH"${ma}><Data ss:Type="String">${this._esc(grp.header)}</Data></Cell>\n`;
        grp.children.forEach(cName => {
          const c = cols.find(x => x.name === cName);
          if (c) row2 += `<Cell ss:StyleID="cgH"><Data ss:Type="String">${this._esc(c.header)}</Data></Cell>\n`;
        });
        i += n;
      } else if (inAnyGroup(col.name)) {
        i++; // 그룹의 첫 번째가 아닌 컬럼 — 이미 처리됨
      } else {
        // 독립 컬럼 → rowspan 2
        row1 += `<Cell ss:StyleID="cgH" ss:MergeDown="1"><Data ss:Type="String">${this._esc(col.header)}</Data></Cell>\n`;
        i++;
      }
    }

    return row1 + '</Row>\n' + row2 + '</Row>';
  }

  /** 데이터 행 */
  static _dataRows(cols, data, grid) {
    if (!data.length) return '';
    return data.map(row => {
      let xml = '<Row ss:Height="20">\n';
      cols.forEach(col => {
        const val = row[col.name];
        if (col.editor === 'number' && val !== '' && val !== null && val !== undefined) {
          const n = Number(val);
          if (!isNaN(n)) { xml += `<Cell ss:StyleID="cgN"><Data ss:Type="Number">${n}</Data></Cell>\n`; return; }
        }
        if (col.editor === 'checkbox') { xml += `<Cell><Data ss:Type="String">${val ? '✓' : ''}</Data></Cell>\n`; return; }
        xml += `<Cell><Data ss:Type="String">${this._esc(grid._formatDisplay(val, col))}</Data></Cell>\n`;
      });
      return xml + '</Row>';
    }).join('\n');
  }

  /** Sum 행 */
  static _summaryRow(cols, data) {
    let xml = '<Row ss:Height="22">\n';
    let firstNonNum = true;
    cols.forEach(col => {
      if (col.editor === 'number' && col.summary !== false) {
        const total = data.reduce((s, r) => { const v = Number(r[col.name]); return s + (isNaN(v) ? 0 : v); }, 0);
        xml += `<Cell ss:StyleID="cgSN"><Data ss:Type="Number">${total}</Data></Cell>\n`;
      } else if (firstNonNum) {
        xml += `<Cell ss:StyleID="cgS"><Data ss:Type="String">합계</Data></Cell>\n`;
        firstNonNum = false;
      } else {
        xml += `<Cell ss:StyleID="cgS"><Data ss:Type="String"></Data></Cell>\n`;
      }
    });
    return xml + '</Row>';
  }

  /** 헤더 고정 (틀 고정) */
  static _freezePane(rows) {
    return `<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
<FreezePanes/><FrozenNoSplit/>
<SplitHorizontal>${rows}</SplitHorizontal>
<TopRowBottomPane>${rows}</TopRowBottomPane>
<ActivePane>2</ActivePane>
</WorksheetOptions>`;
  }
}

/* ══════════════════════════════════════════════════════════
   wwGrid
══════════════════════════════════════════════════════════ */
class wwGrid {
  /**
   * @param {object} options
   * @param {HTMLElement}  options.el            - 마운트 대상
   * @param {object[]}     options.columns       - 컬럼 정의 배열
   * @param {object[]}     [options.columnGroups]- 헤더 그루핑
   * @param {object[]}     [options.data]        - 초기 데이터
   * @param {boolean}      [options.rowCheckbox] - 행 체크박스 표시 여부 (기본 true)
   * @param {boolean}      [options.rowNumber]   - 행 번호 표시 여부 (기본 true)
   * @param {boolean}      [options.editable]    - 전체 편집 가능 여부 (기본 true)
   * @param {number}       [options.height]      - 그리드 높이 (px)
   */
  constructor(options) {
    this.el           = options.el;
    this.columns      = options.columns || [];
    this.columnGroups = options.columnGroups || [];
    this.data         = options.data ? JSON.parse(JSON.stringify(options.data)) : [];
    this.originalData = options.data ? JSON.parse(JSON.stringify(options.data)) : [];
    this.rowCheckbox  = options.rowCheckbox !== false;
    this.rowNumber    = options.rowNumber   !== false;
    this.editable     = options.editable    !== false;
    this.height       = options.height || null;
    this.summary      = options.summary     || false;
    this.theme        = options.theme       || {};
    // toolbar: true(기본, 엑셀 버튼 표시) | false(툴바 숨김)
    this.toolbar = options.toolbar !== undefined ? options.toolbar : true;
    // footer: true(기본, 전체 표시) | false(숨김) | { total, selected, modified }
    this.footer  = options.footer  !== undefined ? options.footer  : true;

    // 상태
    this._modifiedRows  = new Map();  // rowIndex -> { original, current, changed, isNew? }
    this._checkedRows   = new Set();  // rowIndex
    this._addedRows     = [];         // 새로 추가된 행 인덱스
    this._deletedRows   = [];         // 삭제된 원본 행 데이터
    this._editingCell   = null;       // { rowIndex, colName, td, editorEl }
    this._sortState     = { colName: null, direction: 'asc' };
    this._dragColName   = null;       // 드래그 중인 컬럼 이름
    this._colMap        = {};         // colName → <col> 엘리먼트
    this._resizing      = null;
    this._calendar      = new CalendarPopup();
    this._modal         = new GridModal();
    this._rowKey        = options.rowKey || null;
    // 최초 원본 데이터 (resetModified 기준)
    this._initialData   = options.data ? JSON.parse(JSON.stringify(options.data)) : [];
    // code 에디터 조회 캐시: 'colName:value' → 표시 label
    this._lookupCache   = {};

    this._build();
  }

  /* ── 초기 빌드 ──────────────────────────────── */
  /* ── CSS 변수 → theme 옵션 매핑 ──────────────── */
  static get _themeMap() {
    return {
      accent:         '--cg-accent',
      accentDark:     '--cg-accent-dark',
      headerBg:       '--cg-header-bg',
      headerGroupBg:  '--cg-header-group-bg',
      headerHoverBg:  '--cg-header-hover-bg',
      footerBg:       '--cg-footer-bg',
      selectedBg:     '--cg-selected-bg',
      selectedMixBg:  '--cg-selected-mix-bg',
      modifiedBg:     '--cg-modified-bg',
      newBg:          '--cg-new-bg',
      summaryBg:      '--cg-summary-bg',
      border:         '--cg-border',      // 헤더·외곽·푸터·달력 테두리
      rowBorder:      '--cg-row-border',  // 행·셀 구분선
      borderSub:      '--cg-border-sub',  // 버튼·스크롤바·Sum 상단선
      // popup 전용 (CSS 변수 미사용 — open() 시 인라인 스타일로 직접 적용)
      popupHeaderBg:    null,  // 팝업 헤더 배경색
      popupHeaderText:  null,  // 팝업 헤더 텍스트/아이콘 색
      popupBg:          null,  // 팝업 바디 배경색
      popupText:        null,  // 팝업 텍스트 색
      popupItemHoverBg: null,  // 항목 hover 배경색
      popupItemSelBg:   null,  // 항목 선택 배경색
      popupItemSelText: null,  // 항목 선택 텍스트 색
    };
  }

  _applyTheme() {
    const map = wwGrid._themeMap;
    Object.entries(this.theme).forEach(([key, val]) => {
      if (map[key] && val) this.el.style.setProperty(map[key], val);
    });
  }

  /* ── theme 런타임 변경 API ─────────────────── */
  setTheme(theme) {
    this.theme = Object.assign({}, this.theme, theme);
    this._applyTheme();
  }

  _build() {
    this.el.innerHTML = '';
    this.el.classList.add('cg-root');
    this._applyTheme();

    // Toolbar (엑셀 저장 버튼만 내장)
    // toolbar: true(기본, 표시) | false(숨김)
    this._toolbarEl = document.createElement('div');
    this._toolbarEl.className = 'cg-toolbar';

    if (this.toolbar !== false) {
      this._toolbarEl.innerHTML =
        '<span class="cg-toolbar-sep"></span>' +
        '<button class="cg-btn cg-btn-excel" data-action="excel">&#9660; 엑셀 저장</button>';
      this.el.appendChild(this._toolbarEl);
    }

    // Wrap (scrollable)
    this._wrapEl = document.createElement('div');
    this._wrapEl.className = 'cg-wrap';
    // 고정 높이(숫자): 결과가 없어도 리스트 영역이 그대로 표시되도록 height 고정(내용 많으면 내부 스크롤)
    // 'fit': 뷰포트 하단까지 자동으로 채움(_applyFitHeight, 아래 _build 끝에서 계산)
    if (typeof this.height === 'number') this._wrapEl.style.height = this.height + 'px';
    this.el.appendChild(this._wrapEl);

    // Table
    this._tableEl = document.createElement('table');
    this._tableEl.className = 'cg-table';
    this._wrapEl.appendChild(this._tableEl);

    // THead / TBody
    this._theadEl = document.createElement('thead');
    this._theadEl.className = 'cg-thead';
    this._tbodyEl = document.createElement('tbody');
    this._tbodyEl.className = 'cg-tbody';
    this._colgroupEl = document.createElement('colgroup');
    this._tfootEl    = document.createElement('tfoot');
    this._tfootEl.className = 'cg-tfoot';
    this._tableEl.appendChild(this._colgroupEl);
    this._tableEl.appendChild(this._theadEl);
    this._tableEl.appendChild(this._tbodyEl);
    this._tableEl.appendChild(this._tfootEl);

    // Footer
    this._footerEl = document.createElement('div');
    this._footerEl.className = 'cg-footer';
    this.el.appendChild(this._footerEl);

    this._renderHeader();
    this._buildColgroup();
    this._renderBody();
    this._renderSummary();
    this._updateFooter();
    this._bindEvents();

    // height:'fit' — 뷰포트 하단까지 채워 페이지 스크롤 없이 그리드 내부 스크롤
    this._applyFitHeight();
    if (this.height === 'fit' && !this._fitBound) {
      this._fitBound = true;
      window.addEventListener('resize', () => this._applyFitHeight());
    }
  }

  /* ── height:'fit' 계산: 페이지 스크롤이 없어지도록 래퍼 높이를 뷰포트에 맞춤 ── */
  _applyFitHeight() {
    if (this.height !== 'fit' || !this._wrapEl) return;
    // 1차: 래퍼 상단부터 뷰포트 하단까지 대략 채움
    const top = this._wrapEl.getBoundingClientRect().top; // 뷰포트 기준
    let h = Math.max(160, Math.floor(window.innerHeight - top - 16));
    this._wrapEl.style.height = h + 'px';
    // 2차: 그래도 페이지가 넘치면(푸터·레이아웃 하단여백 등) 넘친 만큼 줄여 페이지 스크롤 제거
    const overflow = document.documentElement.scrollHeight - window.innerHeight;
    if (overflow > 0) {
      h = Math.max(140, h - overflow - 2);
      this._wrapEl.style.height = h + 'px';
    }
  }

  /* ── 헤더 렌더링 ────────────────────────────── */
  _renderHeader() {
    this._theadEl.innerHTML = '';
    const hasGroups = this.columnGroups.length > 0;

    // 그루핑 없을 때: 단일 행 헤더
    if (!hasGroups) {
      const tr = document.createElement('tr');
      if (this.rowCheckbox) tr.appendChild(this._makeHeaderCheckCell());
      if (this.rowNumber)   tr.appendChild(this._makeHeaderCell('No', 'cg-col-rownum', 2, 1, false));
      this.columns.forEach(col => {
        tr.appendChild(this._makeHeaderCell(col.header, '', 1, 1, col.sortable !== false, col));
      });
      this._theadEl.appendChild(tr);
      return;
    }

    // 그루핑 있을 때: 2행 헤더
    // 1행: 그룹 헤더 + 그룹 없는 개별 컬럼 (rowspan=2)
    // 2행: 그룹 내 하위 컬럼

    // 그룹에 속한 컬럼명 집합
    const groupedCols = new Set();
    this.columnGroups.forEach(g => g.children.forEach(c => groupedCols.add(c)));

    const tr1 = document.createElement('tr');
    const tr2 = document.createElement('tr');

    if (this.rowCheckbox) {
      const th = this._makeHeaderCheckCell();
      th.rowSpan = 2;
      tr1.appendChild(th);
    }
    if (this.rowNumber) {
      tr1.appendChild(this._makeHeaderCell('No', 'cg-col-rownum', 2, 1, false));
    }

    // 컬럼 순서대로 그룹/단독 판단
    let colIndex = 0;
    while (colIndex < this.columns.length) {
      const col = this.columns[colIndex];
      // 이 컬럼이 어느 그룹의 첫 컬럼인가?
      const grp = this.columnGroups.find(g => g.children[0] === col.name);

      if (grp) {
        // 그룹 헤더 (colspan = children.length)
        const th = this._makeHeaderCell(grp.header, 'cg-group-header', 1, grp.children.length, false);
        tr1.appendChild(th);
        // 하위 컬럼 → tr2
        grp.children.forEach(cName => {
          const c = this.columns.find(x => x.name === cName);
          if (c) tr2.appendChild(this._makeHeaderCell(c.header, '', 1, 1, c.sortable !== false, c));
        });
        colIndex += grp.children.length;
      } else if (!groupedCols.has(col.name)) {
        // 그룹에 속하지 않은 단독 컬럼 → rowspan=2
        tr1.appendChild(this._makeHeaderCell(col.header, '', 2, 1, col.sortable !== false, col));
        colIndex++;
      } else {
        // 그룹 내 컬럼이지만 첫 번째가 아님 → 이미 처리됨
        colIndex++;
      }
    }

    this._theadEl.appendChild(tr1);
    this._theadEl.appendChild(tr2);
  }

  /* ── Colgroup (컬럼 너비 관리) ──────────────── */
  _buildColgroup() {
    this._colgroupEl.innerHTML = '';
    this._colMap = {};

    const addCol = (w) => {
      const col = document.createElement('col');
      if (w) col.style.width = w + 'px';
      this._colgroupEl.appendChild(col);
      return col;
    };

    if (this.rowCheckbox) addCol(40);
    if (this.rowNumber)   addCol(60);   // Figma No 컬럼 60px
    this.columns.forEach(c => {
      this._colMap[c.name] = addCol(c.width || null);
    });
  }

  _makeHeaderCheckCell() {
    const th = document.createElement('th');
    th.className = 'cg-col-check';
    th.innerHTML = `<div class="cg-th-inner"><input type="checkbox" class="cg-header-check"></div>`;
    return th;
  }

  _makeHeaderCell(label, extraClass, rowspan, colspan, sortable, col) {
    const th = document.createElement('th');
    if (extraClass)  th.className += ' ' + extraClass;
    if (rowspan > 1) th.rowSpan = rowspan;
    if (colspan > 1) th.colSpan = colspan;
    // 너비는 colgroup <col>이 관리 — th.style.width 미사용

    const inner = document.createElement('div');
    // 헤더 제목은 컬럼 정렬과 무관하게 항상 가운데로 둔다(운영 요청).
    // Figma 시안은 좌측이지만, 컬럼 수가 많은 목록에서 제목이 가운데인 쪽이
    // 열 경계를 읽기 쉽다는 판단이다.
    inner.className = 'cg-th-inner' + (sortable ? ' sortable' : '');
    inner.innerHTML = `<span>${label}</span><span class="cg-sort-icon"></span>`;

    if (col) {
      inner.dataset.colName = col.name;   // 정렬 + 드래그 공용
      inner.draggable = true;             // 리프 컬럼만 드래그 가능
    }

    th.appendChild(inner);

    // 리사이즈 핸들 (리프 컬럼만)
    if (col) {
      const handle = document.createElement('div');
      handle.className = 'cg-resize-handle';
      handle.dataset.colName = col.name;
      th.appendChild(handle);
    }

    return th;
  }

  /* ── 바디 렌더링 ────────────────────────────── */
  _renderBody() {
    this._tbodyEl.innerHTML = '';
    this.data.forEach((row, rowIndex) => {
      this._tbodyEl.appendChild(this._makeRow(row, rowIndex));
    });
  }

  _makeRow(row, rowIndex) {
    const tr = document.createElement('tr');
    tr.dataset.rowIndex = rowIndex;
    const _mod = this._modifiedRows.get(rowIndex);
    if (this._checkedRows.has(rowIndex)) tr.classList.add('cg-row-selected');
    if (_mod) {
      if (_mod.isNew) tr.classList.add('cg-row-new');
      else            tr.classList.add('cg-row-modified');
    }

    if (this.rowCheckbox) {
      const td = document.createElement('td');
      td.className = 'cg-col-check';
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.className = 'cg-row-check';
      chk.checked = this._checkedRows.has(rowIndex);
      chk.style.cursor = 'pointer';
      chk.style.accentColor = '#3c82c4';
      td.appendChild(chk);
      tr.appendChild(td);
    }

    if (this.rowNumber) {
      const td = document.createElement('td');
      td.className = 'cg-col-rownum';
      const inner = document.createElement('div');
      inner.className = 'cg-cell-inner';
      inner.style.justifyContent = 'flex-end';
      inner.textContent = rowIndex + 1;
      td.appendChild(inner);
      tr.appendChild(td);
    }

    this.columns.forEach(col => {
      const td = this._makeCell(row, rowIndex, col);
      tr.appendChild(td);
    });

    return tr;
  }

  _makeCell(row, rowIndex, col) {
    const td = document.createElement('td');
    td.dataset.rowIndex = rowIndex;
    td.dataset.colName  = col.name;

    const cellValue = row[col.name] !== undefined ? row[col.name] : '';
    const isEditable = this.editable && col.editor;

    const inner = document.createElement('div');
    const _alignCls = col.editor === 'number' || col.align === 'right'  ? ' cg-align-right'
                    : col.align === 'center' ? ' cg-align-center' : '';
    inner.className = 'cg-cell-inner' + (isEditable ? ' editable' : '') + _alignCls;
    inner.dataset.rowIndex = rowIndex;
    inner.dataset.colName  = col.name;

    if (col.editor === 'checkbox') {
      // 체크박스
      const wrap = document.createElement('div');
      wrap.className = 'cg-checkbox-display';
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = !!cellValue;
      chk.dataset.rowIndex = rowIndex;
      chk.dataset.colName  = col.name;
      wrap.appendChild(chk);
      inner.appendChild(wrap);
    } else {
      // 수정됨 표시 (신규 행 제외)
      const _cellMod = this._modifiedRows.get(rowIndex);
      if (_cellMod && !_cellMod.isNew && _cellMod.changed && _cellMod.changed[col.name] !== undefined) {
        const dot = document.createElement('span');
        dot.className = 'cg-modified-dot';
        dot.title = `원본: ${_cellMod.original[col.name]}`;
        inner.appendChild(dot);
      }
      /* 셀을 직접 그리는 컬럼 — renderer(value, row, rowIndex, col) 가 노드를 준다.
         셀은 원래 글자만 담는다. 행마다 버튼을 두어야 하는 목록이 생겨 열어 둔다.
         값에서 만든 HTML 문자열을 붙이지 않고 호출한 쪽이 만든 노드만 받으므로,
         데이터가 태그를 물고 있어도 그대로 그려지지 않는다. */
      if (typeof col.renderer === 'function') {
        const node = col.renderer(cellValue, row, rowIndex, col);
        if (node instanceof Node) {
          inner.appendChild(node);
        } else if (node !== null && node !== undefined && node !== false) {
          inner.appendChild(document.createTextNode(String(node)));
        }
      } else {
        inner.appendChild(document.createTextNode(this._formatDisplay(cellValue, col)));
      }

      // popup 셀 — 오른쪽 끝에 트리거 아이콘
      if (col.editor === 'popup' && isEditable) {
        const icon = document.createElement('span');
        icon.className = 'cg-popup-trigger';
        icon.title = '클릭하여 선택';
        icon.innerHTML = '&#x2315;'; // ⌕
        inner.appendChild(icon);
      }
    }

    td.appendChild(inner);
    return td;
  }

  _formatDisplay(value, col) {
    if (col.editor === 'combo' && col.options) {
      const opt = col.options.find(o => (typeof o === 'object' ? o.value : o) === value);
      if (opt) return typeof opt === 'object' ? opt.label : opt;
    }
    if (col.editor === 'popup' && col.popup && col.popup.items) {
      const opt = col.popup.items.find(o => (typeof o === 'object' ? o.value : o) === value);
      if (opt) return typeof opt === 'object' ? opt.label : opt;
    }
    // code 에디터: 캐시에서 라벨 조회
    if (col.editor === 'code' && value !== '' && value != null) {
      const cached = this._lookupCache[`${col.name}:${value}`];
      if (cached) return cached;
    }
    if (value === null || value === undefined || value === '') return '';
    if (col.editor === 'number' && value !== '') {
      const n = Number(value);
      if (!isNaN(n)) return n.toLocaleString('ko-KR');
    }
    return String(value);
  }

  /* ── 이벤트 바인딩 ──────────────────────────── */
  _bindEvents() {
    // 헤더 체크 (전체 선택)
    this._theadEl.addEventListener('change', e => {
      if (e.target.classList.contains('cg-header-check')) {
        const checked = e.target.checked;
        this.data.forEach((_, i) => {
          if (checked) this._checkedRows.add(i);
          else         this._checkedRows.delete(i);
        });
        this._tbodyEl.querySelectorAll('.cg-row-check').forEach(c => c.checked = checked);
        this._tbodyEl.querySelectorAll('tr').forEach(tr => {
          tr.classList.toggle('cg-row-selected', checked);
        });
        this._updateFooter();
      }
    });

    // 행 체크박스
    this._tbodyEl.addEventListener('change', e => {
      if (e.target.classList.contains('cg-row-check')) {
        const tr = e.target.closest('tr');
        const ri = parseInt(tr.dataset.rowIndex, 10);
        if (e.target.checked) {
          this._checkedRows.add(ri);
          tr.classList.add('cg-row-selected');
        } else {
          this._checkedRows.delete(ri);
          tr.classList.remove('cg-row-selected');
        }
        this._updateFooter();
      }

      // 셀 내 체크박스 (editor: checkbox)
      if (e.target.dataset.colName) {
        const ri  = parseInt(e.target.dataset.rowIndex, 10);
        const col = e.target.dataset.colName;
        this._commitValue(ri, col, e.target.checked);
      }
    });

    // 셀 클릭 → 편집 시작 (이미 편집 중인 셀 재진입 방지)
    this._tbodyEl.addEventListener('click', e => {
      const inner = e.target.closest('.cg-cell-inner.editable');
      if (!inner) return;
      if (inner.classList.contains('cg-cell-editing')) return;
      const ri     = parseInt(inner.dataset.rowIndex, 10);
      const col    = inner.dataset.colName;
      const colDef = this.columns.find(c => c.name === col);
      if (!colDef || colDef.editor === 'checkbox') return;

      // popup 에디터 → 모달 직접 오픈 (인라인 편집 없음)
      if (colDef.editor === 'popup') {
        if (this._editingCell) this._commitEdit();
        this._openPopup(ri, col, colDef);
        return;
      }

      this._startEdit(ri, col, inner.closest('td'));
    });

    // 툴바 버튼
    this._toolbarEl.addEventListener('click', e => {
      const action = e.target.dataset.action;
      if (action === 'addRow')        this.addRow();
      if (action === 'removeRow')     this.removeCheckedRows();
      if (action === 'resetModified') this.resetModified();
      if (action === 'getModified')   this._showModifiedDialog();
      if (action === 'excel')         this.downloadExcel();
    });

    // 정렬
    this._theadEl.addEventListener('click', e => {
      const inner = e.target.closest('.cg-th-inner.sortable');
      if (!inner) return;
      const col = inner.dataset.colName;
      if (!col) return;
      if (this._sortState.colName === col) {
        this._sortState.direction = this._sortState.direction === 'asc' ? 'desc' : 'asc';
      } else {
        this._sortState = { colName: col, direction: 'asc' };
      }
      this._sortData();
    });

    // 컬럼 리사이즈 (colgroup <col> 업데이트)
    this._theadEl.addEventListener('mousedown', e => {
      const handle = e.target.closest('.cg-resize-handle');
      if (!handle) return;
      e.preventDefault();
      e.stopPropagation();

      const colName = handle.dataset.colName;
      const colEl   = this._colMap[colName];
      const th      = handle.closest('th');
      const startX  = e.clientX;
      const startW  = th.offsetWidth;

      const onMove = ev => {
        const newW = Math.max(40, startW + ev.clientX - startX);
        if (colEl) colEl.style.width = newW + 'px';
      };
      const onUp = () => {
        // 변경된 너비를 columns 배열에도 반영 (재렌더 후에도 유지)
        if (colEl) {
          const finalW = parseInt(colEl.style.width) || startW;
          const colDef = this.columns.find(c => c.name === colName);
          if (colDef) colDef.width = finalW;
        }
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      };
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });

    // 컬럼 드래그 순서 변경
    this._theadEl.addEventListener('dragstart', e => {
      const inner = e.target.closest('.cg-th-inner[data-col-name]');
      if (!inner) { e.preventDefault(); return; }
      this._dragColName = inner.dataset.colName;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', this._dragColName);
      // ghost 캡처 후 반투명 적용 (setTimeout으로 ghost 캡처 이후 처리)
      setTimeout(() => inner.classList.add('cg-th-dragging'), 0);
    });

    this._theadEl.addEventListener('dragover', e => {
      if (!this._dragColName) return;
      const inner = e.target.closest('.cg-th-inner[data-col-name]');
      if (!inner || inner.dataset.colName === this._dragColName) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      // 이전 인디케이터 제거 후 현재 타겟에 표시
      this._theadEl.querySelectorAll('.cg-th-inner').forEach(el =>
        el.classList.remove('cg-drop-left', 'cg-drop-right'));
      const rect = inner.getBoundingClientRect();
      inner.classList.add(e.clientX < rect.left + rect.width / 2 ? 'cg-drop-left' : 'cg-drop-right');
    });

    this._theadEl.addEventListener('dragleave', e => {
      // relatedTarget이 같은 th-inner 안에 있으면 무시
      const inner = e.target.closest('.cg-th-inner');
      if (inner && !inner.contains(e.relatedTarget)) {
        inner.classList.remove('cg-drop-left', 'cg-drop-right');
      }
    });

    this._theadEl.addEventListener('drop', e => {
      e.preventDefault();
      const inner = e.target.closest('.cg-th-inner[data-col-name]');
      if (!inner || !this._dragColName) { this._cleanDrag(); return; }
      const toName = inner.dataset.colName;
      if (toName !== this._dragColName) {
        const rect = inner.getBoundingClientRect();
        const insertBefore = e.clientX < rect.left + rect.width / 2;
        this._reorderColumn(this._dragColName, toName, insertBefore);
      }
      this._cleanDrag();
    });

    this._theadEl.addEventListener('dragend', () => this._cleanDrag());

    // 편집 중 외부 클릭 → 커밋 (달력·모달 팝업 내부 클릭은 제외)
    document.addEventListener('mousedown', e => {
      if (!this._editingCell) return;
      const calPopup   = this._calendar._popup;
      const modalPopup = this._modal._overlay;
      if (calPopup   && calPopup.contains(e.target))   return;
      if (modalPopup && modalPopup.contains(e.target)) return;
      const td = this._editingCell.td;
      if (!td.contains(e.target)) this._commitEdit();
    });
  }

  /* ── 편집 시작 ──────────────────────────────── */
  _startEdit(rowIndex, colName, td) {
    if (this._editingCell) this._commitEdit();

    const colDef = this.columns.find(c => c.name === colName);
    if (!colDef) return;

    const inner = td.querySelector('.cg-cell-inner');
    const currentValue = this.data[rowIndex][colName];

    inner.classList.add('cg-cell-editing');
    inner.innerHTML = '';

    let editorEl;

    switch (colDef.editor) {
      case 'text':
        editorEl = document.createElement('input');
        editorEl.type = 'text';
        editorEl.className = 'cg-editor-text';
        editorEl.value = currentValue !== undefined ? currentValue : '';
        inner.appendChild(editorEl);
        editorEl.focus();
        editorEl.select();
        editorEl.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this._commitEdit(); }
          if (e.key === 'Escape') this._cancelEdit();
        });
        break;

      case 'number':
        editorEl = document.createElement('input');
        editorEl.type = 'number';
        editorEl.className = 'cg-editor-number';
        editorEl.value = currentValue !== undefined ? currentValue : '';
        if (colDef.min !== undefined) editorEl.min = colDef.min;
        if (colDef.max !== undefined) editorEl.max = colDef.max;
        if (colDef.step !== undefined) editorEl.step = colDef.step;
        inner.appendChild(editorEl);
        editorEl.focus();
        editorEl.select();
        editorEl.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this._commitEdit(); }
          if (e.key === 'Escape') this._cancelEdit();
        });
        break;

      case 'combo': {
        editorEl = document.createElement('select');
        editorEl.className = 'cg-editor-select';
        const opts = colDef.options || [];
        opts.forEach(o => {
          const v = typeof o === 'object' ? o.value : o;
          const l = typeof o === 'object' ? o.label : o;
          const opt = document.createElement('option');
          opt.value = v;
          opt.textContent = l;
          if (v === currentValue) opt.selected = true;
          editorEl.appendChild(opt);
        });
        inner.appendChild(editorEl);
        editorEl.focus();
        editorEl.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this._commitEdit(); }
          if (e.key === 'Escape') this._cancelEdit();
        });
        editorEl.addEventListener('change', () => this._commitEdit());
        break;
      }

      case 'date': {
        editorEl = document.createElement('input');
        editorEl.type = 'text';
        editorEl.className = 'cg-editor-date';
        editorEl.readOnly = true;
        editorEl.placeholder = 'YYYY-MM-DD';
        editorEl.value = currentValue || '';
        inner.appendChild(editorEl);

        this._editingCell = { rowIndex, colName, td, editorEl };
        this._calendar.open(td, currentValue, (val) => {
          if (this._editingCell && this._editingCell.editorEl === editorEl) {
            editorEl.value = val;
            this._commitEdit();
          }
        });

        editorEl.addEventListener('keydown', e => {
          if (e.key === 'Escape') { this._calendar.close(); this._cancelEdit(); }
        });
        break;
      }

      case 'code': {
        // 코드 직접 입력 → 외부 API 조회 에디터
        editorEl = document.createElement('input');
        editorEl.type = 'text';
        editorEl.className = 'cg-editor-text cg-editor-code';
        // 편집창에는 raw 코드값 표시 (라벨 아닌 코드)
        editorEl.value = currentValue !== undefined ? String(currentValue) : '';
        editorEl.placeholder = colDef.placeholder || '코드 입력...';
        inner.appendChild(editorEl);
        editorEl.focus();
        editorEl.select();
        editorEl.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this._commitEdit(); }
          if (e.key === 'Escape') this._cancelEdit();
        });
        break;
      }

      default:
        return;
    }

    this._editingCell = { rowIndex, colName, td, editorEl };
  }

  /* ── 편집 커밋 ──────────────────────────────── */
  _commitEdit() {
    if (!this._editingCell) return;
    const { rowIndex, colName, td, editorEl } = this._editingCell;
    this._editingCell = null;

    const colDef = this.columns.find(c => c.name === colName);

    // code 에디터 → 비동기 API 조회 분기
    if (colDef && colDef.editor === 'code' && colDef.onLookup) {
      this._calendar.close();
      this._commitCodeLookup(rowIndex, colName, td, editorEl.value.trim(), colDef);
      return;
    }

    let value = editorEl.value;

    if (colDef.editor === 'number') {
      value = value === '' ? '' : Number(value);
    } else if (colDef.editor === 'combo' && Array.isArray(colDef.options)) {
      // <select>.value 는 항상 문자열 → 옵션의 원래 타입(숫자 등) 값으로 복원.
      // 그래야 셀 표시(라벨 해석)·변경 감지·저장이 원본 데이터 타입과 일치한다.
      const opt = colDef.options.find(o => String(typeof o === 'object' ? o.value : o) === value);
      if (opt) value = typeof opt === 'object' ? opt.value : opt;
    }

    this._calendar.close();
    this._commitValue(rowIndex, colName, value);
  }

  /* ── code 에디터 — 비동기 조회 ─────────────── */
  async _commitCodeLookup(rowIndex, colName, td, code, colDef) {
    const inner = td.querySelector('.cg-cell-inner');
    if (!inner) return;

    // 빈 입력 → 빈 값으로 커밋
    if (!code) {
      this._commitValue(rowIndex, colName, '');
      return;
    }

    // 로딩 상태 표시
    inner.className = 'cg-cell-inner';
    inner.dataset.rowIndex = rowIndex;
    inner.dataset.colName  = colName;
    inner.innerHTML = `<span class="cg-code-loading"><span class="cg-spinner"></span><span>조회 중...</span></span>`;

    try {
      const result = await colDef.onLookup(code);

      if (result && result.value != null) {
        // 성공: 라벨 캐시에 저장 후 값 커밋
        this._lookupCache[`${colName}:${result.value}`] = result.label || String(result.value);
        this._commitValue(rowIndex, colName, result.value);
      } else {
        // 실패: 에러 메시지 표시 후 복원
        this._showCellLookupError(rowIndex, colName, td, inner, code,
          colDef.errorMessage || '코드를 찾을 수 없습니다');
      }
    } catch (err) {
      this._showCellLookupError(rowIndex, colName, td, inner, code,
        colDef.errorMessage || '조회 중 오류가 발생했습니다');
      console.error('[wwGrid onLookup]', err);
    }
  }

  _showCellLookupError(rowIndex, colName, td, inner, code, message) {
    inner.className = 'cg-cell-inner editable cg-code-error';
    inner.dataset.rowIndex = rowIndex;
    inner.dataset.colName  = colName;
    inner.innerHTML =
      `<span class="cg-code-error-code">${code}</span>` +
      `<span class="cg-code-error-msg">${message}</span>`;

    // 2.5초 후 이전 값으로 복원 (편집 중이면 건너뜀)
    setTimeout(() => {
      const editing = this._editingCell;
      if (editing && editing.rowIndex === rowIndex && editing.colName === colName) return;
      this._refreshCell(rowIndex, colName);
    }, 2500);
  }

  _cancelEdit() {
    if (!this._editingCell) return;
    const { rowIndex, colName, td } = this._editingCell;
    this._editingCell = null;
    this._calendar.close();
    this._refreshCell(rowIndex, colName);
  }

  _commitValue(rowIndex, colName, value) {
    const original = this.originalData[rowIndex];
    const origValue = original ? original[colName] : undefined;

    this.data[rowIndex][colName] = value;

    if (!this._modifiedRows.has(rowIndex)) {
      this._modifiedRows.set(rowIndex, {
        rowIndex,
        original: original ? { ...original } : {},
        current: { ...this.data[rowIndex] },
        changed: {}
      });
    }

    const mod = this._modifiedRows.get(rowIndex);
    mod.current = { ...this.data[rowIndex] };

    if (JSON.stringify(value) !== JSON.stringify(origValue)) {
      mod.changed[colName] = value;
    } else {
      delete mod.changed[colName];
      // 신규 행(isNew)은 changed가 비어도 추적 유지
      if (!mod.isNew && Object.keys(mod.changed).length === 0) {
        this._modifiedRows.delete(rowIndex);
      }
    }

    this._refreshRow(rowIndex);
    this._renderSummary();
    this._updateFooter();
  }

  /* ── 행/셀 갱신 ─────────────────────────────── */
  _refreshRow(rowIndex) {
    const tr = this._tbodyEl.querySelector(`tr[data-row-index="${rowIndex}"]`);
    if (!tr) return;

    const rowMod = this._modifiedRows.get(rowIndex);
    tr.classList.toggle('cg-row-new',      !!(rowMod && rowMod.isNew));
    tr.classList.toggle('cg-row-modified', !!(rowMod && !rowMod.isNew));

    this.columns.forEach(col => {
      this._refreshCell(rowIndex, col.name);
    });

    if (this.rowNumber) {
      const numCell = tr.querySelector('.cg-col-rownum .cg-cell-inner');
      if (numCell) numCell.textContent = rowIndex + 1;
    }
  }

  _refreshCell(rowIndex, colName) {
    const td = this._tbodyEl.querySelector(`td[data-row-index="${rowIndex}"][data-col-name="${colName}"]`);
    if (!td) return;

    const colDef = this.columns.find(c => c.name === colName);
    if (!colDef) return;

    const value = this.data[rowIndex][colName];
    const isEditable = this.editable && colDef.editor;
    const inner = td.querySelector('.cg-cell-inner') || document.createElement('div');
    const _rcAlignCls = colDef.editor === 'number' || colDef.align === 'right'  ? ' cg-align-right'
                      : colDef.align === 'center' ? ' cg-align-center' : '';
    inner.className = 'cg-cell-inner' + (isEditable ? ' editable' : '') + _rcAlignCls;
    inner.dataset.rowIndex = rowIndex;
    inner.dataset.colName  = colName;
    inner.innerHTML = '';

    if (colDef.editor === 'checkbox') {
      const wrap = document.createElement('div');
      wrap.className = 'cg-checkbox-display';
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = !!value;
      chk.dataset.rowIndex = rowIndex;
      chk.dataset.colName  = colName;
      wrap.appendChild(chk);
      inner.appendChild(wrap);
    } else {
      // 수정 점 표시 (신규 행 제외)
      const _rcMod = this._modifiedRows.get(rowIndex);
      if (_rcMod && !_rcMod.isNew && _rcMod.changed && _rcMod.changed[colName] !== undefined) {
        const dot = document.createElement('span');
        dot.className = 'cg-modified-dot';
        dot.title = `원본: ${_rcMod.original[colName]}`;
        inner.appendChild(dot);
      }
      /* 셀을 직접 그리는 컬럼 — _makeCell 과 같은 규칙을 쓴다.
         이 경로를 빠뜨리면 한 칸을 고쳤을 때 그 행의 버튼이 글자로 되돌아간다. */
      if (typeof colDef.renderer === 'function') {
        const node = colDef.renderer(value, this.data[rowIndex], rowIndex, colDef);
        if (node instanceof Node) {
          inner.appendChild(node);
        } else if (node !== null && node !== undefined && node !== false) {
          inner.appendChild(document.createTextNode(String(node)));
        }
      } else {
        inner.appendChild(document.createTextNode(this._formatDisplay(value, colDef)));
      }

      // popup 트리거 아이콘
      if (colDef.editor === 'popup' && isEditable) {
        const icon = document.createElement('span');
        icon.className = 'cg-popup-trigger';
        icon.title = '클릭하여 선택';
        icon.innerHTML = '&#x2315;';
        inner.appendChild(icon);
      }
    }

    if (!td.contains(inner)) {
      td.innerHTML = '';
      td.appendChild(inner);
    }
  }

  /* ── Popup 에디터 ───────────────────────────── */
  _openPopup(rowIndex, colName, colDef) {
    const currentValue = this.data[rowIndex][colName];
    const opts         = colDef.popup || {};

    this._modal.open({
      title:        opts.title    || colDef.header,
      width:        opts.width    || 480,
      height:       opts.height   || 420,
      items:        opts.items    || null,
      render:       opts.render   || null,
      onSearch:     opts.onSearch || null,
      currentValue,
      // 그리드 테마에서 popup 색상 추출하여 전달
      popupTheme: {
        headerBg:    this.theme.popupHeaderBg    || null,
        headerText:  this.theme.popupHeaderText  || null,
        bg:          this.theme.popupBg          || null,
        text:        this.theme.popupText        || null,
        itemHoverBg: this.theme.popupItemHoverBg || null,
        itemSelBg:   this.theme.popupItemSelBg   || null,
        itemSelText: this.theme.popupItemSelText || null,
      },
      onConfirm: (value, label) => {
        this._commitValue(rowIndex, colName, value);
      }
    });
  }

  /* ── 드래그 정리 ────────────────────────────── */
  _cleanDrag() {
    this._dragColName = null;
    this._theadEl.querySelectorAll('.cg-th-inner').forEach(el =>
      el.classList.remove('cg-th-dragging', 'cg-drop-left', 'cg-drop-right'));
  }

  /* ── 컬럼 순서 변경 ─────────────────────────── */
  _reorderColumn(fromName, toName, insertBefore) {
    const fromIdx = this.columns.findIndex(c => c.name === fromName);
    if (fromIdx === -1) return;

    const [moved] = this.columns.splice(fromIdx, 1);
    let toIdx = this.columns.findIndex(c => c.name === toName);
    if (toIdx === -1) {
      this.columns.push(moved);
    } else {
      this.columns.splice(insertBefore ? toIdx : toIdx + 1, 0, moved);
    }

    // columnGroups 재검증: 순서가 틀어진 그룹은 해제
    const colNames = this.columns.map(c => c.name);
    this.columnGroups = this.columnGroups
      .map(g => {
        const indices = g.children
          .map(n => colNames.indexOf(n))
          .filter(i => i !== -1)
          .sort((a, b) => a - b);
        // 연속성 검사 — 끊기면 그룹 해제
        for (let i = 1; i < indices.length; i++) {
          if (indices[i] !== indices[i - 1] + 1) return null;
        }
        return { ...g, children: indices.map(i => colNames[i]) };
      })
      .filter(Boolean);

    this._buildColgroup();
    this._renderHeader();
    this._renderBody();
    this._renderSummary();
  }

  /* ── 정렬 ───────────────────────────────────── */
  _sortData() {
    const { colName, direction } = this._sortState;
    this.data.sort((a, b) => {
      const va = a[colName]; const vb = b[colName];
      if (va === vb) return 0;
      const cmp = va < vb ? -1 : 1;
      return direction === 'asc' ? cmp : -cmp;
    });
    // 정렬 후 수정/선택 상태 초기화 (단순화)
    this._modifiedRows.clear();
    this._checkedRows.clear();
    this._renderBody();
    this._renderSummary();
    this._updateFooter();
    this._updateSortIcons();
  }

  _updateSortIcons() {
    this._theadEl.querySelectorAll('.cg-th-inner.sortable').forEach(inner => {
      inner.classList.remove('cg-sort-asc', 'cg-sort-desc');
      const icon = inner.querySelector('.cg-sort-icon');
      if (icon) icon.textContent = '⇅';
      if (inner.dataset.colName === this._sortState.colName) {
        inner.classList.add(this._sortState.direction === 'asc' ? 'cg-sort-asc' : 'cg-sort-desc');
        if (icon) icon.textContent = this._sortState.direction === 'asc' ? '↑' : '↓';
      }
    });
  }

  /* ── 행 추가/삭제 ───────────────────────────── */
  addRow(rowData) {
    const empty = {};
    this.columns.forEach(col => {
      empty[col.name] = col.defaultValue !== undefined ? col.defaultValue :
                        (col.editor === 'checkbox' ? false :
                         col.editor === 'number'   ? 0    : '');
    });
    const newRow = Object.assign(empty, rowData || {});
    const newIndex = this.data.length;
    this.data.push(newRow);
    this.originalData.push({ ...newRow });
    this._addedRows.push(newIndex);

    // 수정된 행으로 표시 (추가된 행)
    this._modifiedRows.set(newIndex, {
      rowIndex: newIndex,
      original: null,
      current: { ...newRow },
      changed: { ...newRow },
      isNew: true
    });

    const tr = this._makeRow(newRow, newIndex);
    this._tbodyEl.appendChild(tr);
    this._renderSummary();
    this._updateFooter();

    // 새 행이 보이도록 스크롤
    this._wrapEl.scrollTop = this._wrapEl.scrollHeight;

    // 첫 편집 가능 컬럼으로 자동 포커스
    const firstEditCol = this.columns.find(c => c.editor && c.editor !== 'checkbox');
    if (firstEditCol) {
      const td = tr.querySelector(`td[data-col-name="${firstEditCol.name}"]`);
      if (td) setTimeout(() => this._startEdit(newIndex, firstEditCol.name, td), 0);
    }
  }

  removeCheckedRows() {
    if (this._checkedRows.size === 0) {
      alert('삭제할 행을 선택하세요.');
      return;
    }

    const deletedSet  = new Set(this._checkedRows);
    const sortedDesc  = Array.from(deletedSet).sort((a, b) => b - a);

    // 삭제된 원본 행만 _deletedRows에 저장 (신규 행은 제외)
    sortedDesc.forEach(ri => {
      const m = this._modifiedRows.get(ri);
      if (!m || !m.isNew) {
        this._deletedRows.push({ ...this._initialData[ri] });
      }
      this.data.splice(ri, 1);
      this.originalData.splice(ri, 1);
    });

    // _modifiedRows 재인덱싱: 삭제된 행은 제거, 나머지는 shift
    const newModifiedRows = new Map();
    this._modifiedRows.forEach((mod, ri) => {
      if (deletedSet.has(ri)) return;
      const shift = sortedDesc.filter(d => d < ri).length;
      const newRi  = ri - shift;
      mod.rowIndex = newRi;
      newModifiedRows.set(newRi, mod);
    });
    this._modifiedRows = newModifiedRows;

    // _addedRows 재인덱싱
    this._addedRows = this._addedRows
      .filter(ri => !deletedSet.has(ri))
      .map(ri => {
        const shift = sortedDesc.filter(d => d < ri).length;
        return ri - shift;
      });

    this._checkedRows.clear();
    this._renderBody();
    this._renderSummary();
    this._updateFooter();
  }

  /* ── 변경 취소 ──────────────────────────────── */
  resetModified() {
    // 최초 원본(_initialData) 기준으로 복원
    this.data         = JSON.parse(JSON.stringify(this._initialData));
    this.originalData = JSON.parse(JSON.stringify(this._initialData));
    this._modifiedRows.clear();
    this._checkedRows.clear();
    this._addedRows  = [];
    this._deletedRows = [];
    this._renderBody();
    this._renderSummary();
    this._updateFooter();
  }

  /* ── 수정 데이터 추출 ───────────────────────── */
  /**
   * 수정된 행 데이터 반환
   * @returns {{ updated: object[], added: object[], deleted: object[] }}
   */
  getModifiedRows() {
    const updated = [];
    const added   = [];

    this._modifiedRows.forEach((mod, ri) => {
      if (mod.isNew) {
        added.push({ ...mod.current });
      } else {
        updated.push({
          original: mod.original,
          current:  mod.current,
          changed:  mod.changed
        });
      }
    });

    return {
      updated,
      added,
      deleted: [...this._deletedRows]
    };
  }

  /** 체크된 행 데이터 반환 */
  getCheckedRows() {
    return Array.from(this._checkedRows).map(i => ({ ...this.data[i], _rowIndex: i }));
  }

  /** 전체 데이터 반환 */
  getData() {
    return JSON.parse(JSON.stringify(this.data));
  }

  /**
   * 엑셀 다운로드
   * @param {object} [opts]
   * @param {string}  [opts.filename='grid_export'] - 저장 파일명 (확장자 제외)
   * @param {string}  [opts.sheetName='Sheet1']     - 워크시트 이름
   * @param {boolean} [opts.checkedOnly=false]      - 체크된 행만 출력
   * @param {boolean} [opts.includeSummary]         - Sum 행 포함 여부
   */
  downloadExcel(opts = {}) {
    ExcelExporter.download(this, opts);
  }

  /** 서버 전송용 JSON 문자열 */
  getModifiedJSON() {
    return JSON.stringify(this.getModifiedRows(), null, 2);
  }

  /* ── 수정 데이터 팝업 ───────────────────────── */
  _showModifiedDialog() {
    const mod = this.getModifiedRows();
    const json = JSON.stringify(mod, null, 2);
    const dlg = document.createElement('div');
    dlg.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:99999;
      display:flex;align-items:center;justify-content:center;`;
    dlg.innerHTML = `
      <div style="background:#fff;border-radius:8px;padding:20px;max-width:600px;width:90%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <h3 style="margin:0 0 12px;font-size:15px;color:#333;">수정 데이터 (서버 전송용)</h3>
        <pre style="flex:1;overflow:auto;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:12px;font-size:12px;margin:0 0 12px;">${json}</pre>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button id="cg-copy-btn" style="padding:6px 16px;border-radius:3px;border:1px solid #b0bcc8;background:#fff;cursor:pointer;font-size:13px;">클립보드 복사</button>
          <button id="cg-close-btn" style="padding:6px 16px;border-radius:3px;border:1px solid #3c82c4;background:#3c82c4;color:#fff;cursor:pointer;font-size:13px;">닫기</button>
        </div>
      </div>
    `;
    document.body.appendChild(dlg);
    dlg.querySelector('#cg-close-btn').onclick = () => dlg.remove();
    dlg.querySelector('#cg-copy-btn').onclick = () => {
      navigator.clipboard.writeText(json).then(() => alert('복사되었습니다.'));
    };
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.remove(); });
  }

  /* ── 푸터 갱신 ──────────────────────────────── */
  /* ── Sum 행 렌더링 ─────────────────────────── */
  _renderSummary() {
    this._tfootEl.innerHTML = '';
    if (!this.summary) return;

    // summary 대상 컬럼: editor=number 이고 col.summary !== false
    const hasSumCol = this.columns.some(c => c.editor === 'number' && c.summary !== false);
    if (!hasSumCol) return;

    const tr = document.createElement('tr');
    tr.className = 'cg-summary-row';

    // 체크박스 컬럼
    if (this.rowCheckbox) {
      const td = document.createElement('td');
      td.className = 'cg-col-check';
      tr.appendChild(td);
    }

    // 행 번호 컬럼 → "합계" 레이블
    if (this.rowNumber) {
      const td = document.createElement('td');
      td.className = 'cg-col-rownum';
      const inner = document.createElement('div');
      inner.className = 'cg-cell-inner cg-summary-label';
      inner.textContent = '합계';
      td.appendChild(inner);
      tr.appendChild(td);
    }

    let firstNonNum = !this.rowNumber; // rowNumber 없으면 첫 컬럼에 레이블
    this.columns.forEach(col => {
      const td = document.createElement('td');
      const inner = document.createElement('div');
      inner.className = 'cg-cell-inner cg-align-right';

      if (col.editor === 'number' && col.summary !== false) {
        // 합계 계산
        const total = this.data.reduce((acc, row) => {
          const v = Number(row[col.name]);
          return acc + (isNaN(v) ? 0 : v);
        }, 0);
        inner.textContent = total.toLocaleString('ko-KR');
        inner.classList.add('cg-summary-value');
      } else if (firstNonNum) {
        // 행 번호 없을 때 첫 컬럼에 합계 레이블
        inner.textContent = '합계';
        inner.className = 'cg-cell-inner cg-summary-label';
        firstNonNum = false;
      }

      td.appendChild(inner);
      tr.appendChild(td);
    });

    this._tfootEl.appendChild(tr);
  }

  _updateFooter() {
    if (this.footer === false) {
      this._footerEl.style.display = 'none';
      return;
    }
    this._footerEl.style.display = '';

    // footer: true → 전체 표시, object → 항목별 제어
    const fo = (this.footer === true || this.footer == null)
      ? { total: true, selected: true, modified: true }
      : this.footer;

    const total    = this.data.length;
    const checked  = this._checkedRows.size;
    const modified = this._modifiedRows.size;
    let html = '';
    if (fo.total    !== false) html += `<span>전체 <strong>${total}</strong>건</span>`;
    if (fo.selected !== false) html += `<span>선택 <strong>${checked}</strong>건</span>`;
    if (fo.modified !== false) html += `<span>수정 <strong>${modified}</strong>건</span>`;
    this._footerEl.innerHTML = html;
  }

  /* ── 외부 API ───────────────────────────────── */
  setData(data) {
    this.data         = JSON.parse(JSON.stringify(data));
    this.originalData = JSON.parse(JSON.stringify(data));
    this._initialData = JSON.parse(JSON.stringify(data));
    this._modifiedRows.clear();
    this._checkedRows.clear();
    this._addedRows = [];
    this._deletedRows = [];
    this._renderBody();
    this._renderSummary();
    this._updateFooter();
  }

  setValue(rowIndex, colName, value) {
    this._commitValue(rowIndex, colName, value);
  }
}

// 전역 노출
window.wwGrid = wwGrid;
