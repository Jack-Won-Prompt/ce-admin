# wwGrid

의존성 없는 데이터 그리드. 파일 두 개를 복사해 넣으면 끝난다.
빌드 도구·프레임워크·CDN 이 필요 없고, 어떤 서버 언어와도 상관없다.

```
wwGrid.js        76KB   그리드 본체
wwGrid.css       22KB   기본 모양
example.html            가장 작은 예 — 브라우저로 바로 열어 본다
demo-full.html          기능을 다 보여주는 데모 (헤더 그룹 · popup · code 편집기 등)
```

지원: 정렬 · 컬럼 이동 · 컬럼 폭 조절 · 셀 편집 · 체크박스 · 합계 행 ·
엑셀 내려받기 · 헤더 그룹 · 헤더 고정.

> **이 폴더가 원본이다.** 고칠 일이 생기면 여기서 고치고, 아래 10장대로 서비스 쪽으로 복사한다.

---

## 1. 넣기

두 파일을 정적 파일 폴더에 복사한다. 두는 위치는 자유다.

```
public/vendor/wwgrid/wwGrid.css
public/vendor/wwgrid/wwGrid.js
```

`<head>` 에 CSS, `</body>` 앞에 JS 를 싣는다.

```html
<link rel="stylesheet" href="/vendor/wwgrid/wwGrid.css">
<script src="/vendor/wwgrid/wwGrid.js"></script>
```

`wwGrid` 는 전역에 놓인다. 모듈 번들러는 쓰지 않는다.

> **캐시 주의** — 파일을 고친 뒤 화면이 그대로면 브라우저가 옛 파일을 물고 있는 것이다.
> 주소에 파일 시각을 붙여 두면(`?v=1712...`) 그런 일이 없다.

---

## 2. 가장 작은 예

```html
<div id="myGrid"></div>

<script>
const grid = new wwGrid({
  el: document.getElementById('myGrid'),
  columns: [
    { header: '이름',   name: 'name',  width: 140 },
    { header: '전화번호', name: 'phone', width: 140 },
    { header: '수량',   name: 'qty',   width: 80, align: 'right' },
  ],
  data: [
    { name: '김철수', phone: '010-1111-2222', qty: 3 },
    { name: '이영희', phone: '010-3333-4444', qty: 1 },
  ],
});
</script>
```

`data` 는 **평평한 객체 배열**이다. 중첩된 값(`a.b.c`)은 읽지 못하므로 서버에서 미리 펴서 준다.

---

## 3. 그리드 옵션

| 옵션 | 기본 | 설명 |
|---|---|---|
| `el` | (필수) | 그릴 자리. `document.getElementById(...)` |
| `columns` | `[]` | 컬럼 정의. 아래 4장 |
| `data` | `[]` | 행 배열. 안에서 깊은 복사하므로 원본은 건드리지 않는다 |
| `columnGroups` | `[]` | 헤더 두 줄로 묶기 |
| `rowCheckbox` | `true` | 맨 앞 체크박스 열 |
| `rowNumber` | `true` | 행 번호 열 |
| `editable` | `true` | 전체 편집 허용. `false` 면 `editor` 를 줘도 편집되지 않는다 |
| `height` | `null` | 높이(px). `'fit'` 을 주면 화면 아래끝까지 채우고 창 크기에 따라 다시 맞춘다 |
| `toolbar` | `true` | 위쪽 줄. 지금은 **엑셀 저장** 버튼 하나뿐이다 |
| `footer` | `true` | 아래 상태줄. `{ total, selected, modified }` 로 항목별로 끌 수 있다 |
| `summary` | `false` | 합계 행 |
| `rowKey` | `null` | 행을 가리키는 고유 키 이름 |
| `theme` | `{}` | 색 바꾸기. 아래 8장 |

### 목록 화면에서 자주 쓰는 조합

```js
new wwGrid({
  el: document.getElementById('grid'),
  height: 'fit', editable: false, rowCheckbox: true, rowNumber: true,
  toolbar: true, summary: false,
  footer: { total: true, selected: true, modified: false },
  columns: [...],
  data: [...],
});
```

---

## 4. 컬럼 옵션

| 키 | 설명 |
|---|---|
| `header` | 머리글 |
| `name` | `data` 의 키 이름 |
| `width` | 폭(px). 없으면 자동 |
| `align` | `'center'` \| `'right'` (기본 왼쪽) |
| `sortable` | 정렬 가능 여부. 기본 `true` |
| `exportable` | `false` 면 엑셀에서 빠진다 |
| `summary` | `false` 면 합계에서 뺀다 |
| `editor` | 편집기 종류. 아래 표 |
| `options` | `combo` 편집기의 선택지 |
| `popup` | `popup` 편집기의 설정 |
| `renderer` | 셀을 직접 그린다. 아래 5장 |

### editor 종류

| 값 | 편집 방법 |
|---|---|
| `'text'` | 글자 |
| `'number'` | 숫자. 오른쪽 정렬되고 저장할 때 숫자로 바뀐다 |
| `'date'` | 달력 |
| `'checkbox'` | 켜기/끄기 |
| `'combo'` | 목록에서 고르기. `options: [{value, label}, ...]` 또는 `['A','B']` |
| `'code'` | 코드를 치면 바깥에서 이름을 찾아온다 |
| `'popup'` | 셀 오른쪽 `⌕` 를 눌러 창에서 고르기 |

---

## 5. 셀에 버튼·아이콘 넣기 — `renderer`

셀은 원래 **글자만** 담는다. 값에서 만든 HTML 을 붙이지 않기 때문에, 데이터에 태그가
섞여 있어도 그대로 그려지지 않는다(그래서 안전하다).

행마다 버튼이 필요하면 `renderer` 로 **노드를 직접 만들어** 준다.

```js
{
  header: '다운로드', name: 'download', width: 200,
  sortable: false, exportable: false,
  renderer: (value, row, rowIndex, col) => {
    const a = document.createElement('a');
    a.href = row.file_url;
    a.target = '_blank';
    a.textContent = '내려받기';
    return a;                       // 노드를 돌려주면 그대로 셀에 들어간다
  },
}
```

- 돌려준 값이 **DOM 노드면** 그대로 넣고, **문자열이면** 글자로 넣는다(태그로 해석하지 않는다).
- `null` / `undefined` / `false` 를 돌려주면 아무것도 넣지 않는다.
- 정렬하거나 데이터를 바꾸면 다시 불린다 — 그 안에서 무거운 일을 하지 않는다.
- 버튼 자리 컬럼은 `sortable: false`, `exportable: false` 를 함께 준다.

> `innerHTML` 로 값을 붙이지 않는 것이 이 그리드의 규칙이다. `renderer` 도 문자열이 아니라
> 노드를 받는 이유가 그것이다.

---

## 6. 다루기

```js
grid.getData()                       // 지금 데이터 전체
grid.setData(rows)                   // 통째로 갈아끼우기
grid.getCheckedRows()                // 체크한 행들
grid.getModifiedRows()               // 고친 행들 [{ original, current, changed }]
grid.getModifiedJSON()               // 위를 JSON 문자열로
grid.setValue(rowIndex, 'name', '값') // 한 칸 바꾸기
grid.addRow({ name: '' })            // 행 추가
grid.removeCheckedRows()             // 체크한 행 지우기
grid.resetModified()                 // '고침' 표시 지우기 (저장 뒤에 부른다)
grid.downloadExcel()                 // 엑셀로 내려받기
```

### 행을 눌렀을 때

이벤트 API 가 따로 없다. 그린 자리에 이벤트를 걸고 `data-row-index` 로 어느 행인지 안다.

```js
document.getElementById('grid').addEventListener('dblclick', function (e) {
  if (e.target.closest('a, button, input')) return;   // 셀 안 버튼은 제외
  const cell = e.target.closest('[data-row-index]');
  if (!cell) return;
  const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
  if (!row) return;
  window.getSelection()?.removeAllRanges();           // 더블클릭 글자 선택 해제
  openDetail(row.id);
});
```

한 번 클릭도 같은 방식이다. 체크박스나 버튼을 누른 것까지 행 클릭으로 세지 않으려면
위의 `closest('a, button, input')` 줄을 꼭 둔다.

---

## 7. 알아둘 것

| | |
|---|---|
| **검색창이 없다** | 검색·기간은 화면의 폼으로 서버에 보내고, 결과 전체를 `data` 로 넘긴다 |
| **페이지 나눔이 없다** | 모든 행을 한 번에 그린다. 수천 행이면 서버에서 잘라 준다 |
| **정렬은 브라우저에서** | 받은 데이터 안에서만 정렬한다. 전체 정렬이 필요하면 서버에 맡긴다 |
| **툴바 = 엑셀 버튼** | 다른 버튼이 필요하면 그리드 바깥에 두고 `getCheckedRows()` 로 잇는다 |
| **셀은 글자만** | 버튼·아이콘은 `renderer`(5장) |
| **`height: 'fit'`** | 화면 아래끝까지 채운다. 페이지가 스크롤되지 않는 배치에서 잘 맞는다 |

---

## 8. 모양 바꾸기

CSS 변수로 색을 바꾼다. `theme` 옵션으로도 같은 값을 준다.

```css
:root {
  --cg-accent:       #28798B;   /* 주색 (선택·머리글 밑줄) */
  --cg-accent-dark:  #0B5C6E;
  --cg-header-bg:    #F3F5F7;
  --cg-selected-bg:  #EAF3F5;
  --cg-border:       #E8EAEC;
}
```

```js
new wwGrid({ ..., theme: { accent: '#28798B', headerBg: '#F3F5F7' } });
```

클래스 이름은 모두 `cg-` 로 시작한다. 기존 화면의 CSS 와 부딪히지 않는다.

---

## 9. 옮겨 심을 때

1. `wwGrid.js` · `wwGrid.css` 두 개를 복사한다.
2. CSS 는 `<head>`, JS 는 `</body>` 앞에 싣는다.
3. 서버는 **평평한 객체 배열**만 주면 된다. 형식 변환(날짜·금액·상태 이름)은
   서버에서 끝내고 오는 편이 화면이 단순해진다.
4. 목록 화면이라면 3장의 '자주 쓰는 조합' 을 그대로 가져다 쓴다.

---

## 10. 이 저장소(CE-Admin)에서 고칠 때

그리드는 두 자리에 있다.

| 자리 | 무엇 |
|---|---|
| `wwgrid-dist/` | **원본.** 여기서 고친다 |
| `public/vendor/wwgrid/` | 화면이 실제로 읽는 자리. 원본의 복사본이다 |

고쳤으면 반드시 옮긴다.

```bash
cp wwgrid-dist/wwGrid.js wwgrid-dist/wwGrid.css public/vendor/wwgrid/
```

옮긴 뒤 목록 화면 하나를 열어 확인한다. 화면이 그대로면 브라우저가 옛 파일을 물고 있는
것이니 `Ctrl+Shift+R`.

> 예전에는 사본이 셋이었고 서로 갈라져 있었다(CSS 62줄 · JS 5줄 차이). 어느 것이 진짜인지
> 알 수 없어 한쪽만 고쳐지는 일이 반복됐다. 지금은 원본 하나 · 복사본 하나다.
> **양쪽에서 따로 고치면 곧 다시 갈라진다.**
