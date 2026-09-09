{{-- ── 처방전 뷰어 ─────────────────────────────────────────
     그림도 PDF 도 한 장의 그림처럼 본다 — 확대ㆍ회전ㆍ끌기ㆍ쪽 넘기기가 둘 다에 걸린다.

     주문 등록과 거래처 관리, 두 화면이 함께 쓴다. 복사해 두면 두 벌이 갈리고 한쪽만
     고치는 날이 온다.

     쓰는 법 —
       @include('prescriptions._viewer', ['vwDoc' => ['url' => …, 'mime' => …]])
       @include('prescriptions._viewer')                     빈 채로 열고 뒤에 showDoc()

     보여 줄 것을 바꿀 때는 showDoc({ url, isPdf, name }) 을 부른다.
     한 화면에 하나만 둔다 — 칸 이름(prescCanvasㆍpdfCanvasㆍimgCanvas)이 고정이다. --}}
@php $vwDoc = $vwDoc ?? null; @endphp

<style>
  /* ── 이미지 영역 (시안 137:839) — 높이 340 고정 ── */
  .img-viewer { position:relative; height:340px; background:var(--gray-0);
                display:flex; flex-direction:column; overflow:hidden; }

  /* ── 이미지 위에 얹는 도구 패널 (시안 137:901) ──
     예전에는 어두운 가로 툴바가 이미지 위아래를 차지했다. 시안은 이미지 왼쪽에
     반투명 세로 띠를 얹어 이미지가 보이는 넓이를 잃지 않는다. */
  .vw-tools { position:absolute; top:8px; left:8px; bottom:8px; z-index:2;
              display:flex; flex-direction:column; justify-content:space-between; align-items:center;
              gap:8px; padding:8px; border-radius:8px; background:rgba(255,255,255,.4); }
  .vw-tool-group { display:flex; flex-direction:column; align-items:center; gap:8px; }
  .vw-tool { width:32px; height:32px; display:flex; align-items:center; justify-content:center;
             border-radius:8px; background:var(--gray-0); border:none; padding:0;
             font-size:13px; color:var(--gray-800); cursor:pointer; transition:var(--transition); }
  .vw-tool:hover { color:var(--primary); }
  .vw-zoom { font-size:12px; font-weight:500; line-height:1.2; color:var(--gray-1000); text-align:center; }
  .img-viewer-canvas { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
  /* PDF 한 쪽을 그리는 데 잠깐 걸린다(첫 장은 일꾼을 깨우느라 더). 아무 표시가 없으면
     눌렸는지 안 눌렸는지 알 수 없어 같은 자리를 또 누르게 된다. */
  .vw-busy { position:absolute; inset:0; z-index:3; display:none; align-items:center; justify-content:center;
             gap:8px; background:rgba(255,255,255,.62); font-size:12px; color:var(--gray-700); }
  .vw-busy.on { display:flex; }
  .vw-busy i { font-size:15px; animation:vwspin 1s linear infinite; }
  @keyframes vwspin { to { transform:rotate(360deg); } }
  /* ── 밝기ㆍ명암 (2026-09-09 지시) ──
     휴대폰으로 찍은 종이는 한쪽이 어둡고 바탕이 잿빛으로 나온다. 그대로 공단에 내면
     글씨가 묻힌다. 파일은 건드리지 않고 여기서 맞춰 두면 팩스에도 그대로 간다. */
  .vw-tune { position:absolute; right:8px; bottom:8px; z-index:6;
             display:none; flex-direction:column; gap:6px; width:186px;
             padding:10px 12px; border-radius:10px;
             background:rgba(255,255,255,.96); border:1px solid var(--gray-200);
             box-shadow:0 4px 16px rgba(0,0,0,.12); }
  .vw-tune.on { display:flex; }
  .vw-tune-row { display:flex; align-items:center; gap:8px; font-size:11px; color:var(--gray-700); }
  .vw-tune-row > span:first-child { width:28px; flex:none; }
  .vw-tune-row input[type=range] { flex:1; min-width:0; accent-color:var(--primary); }
  .vw-tune-row > b { width:30px; flex:none; text-align:right; font-weight:600;
                     font-variant-numeric:tabular-nums; color:var(--gray-1000); }
  .vw-tune-acts { display:flex; gap:6px; margin-top:2px; }
  .vw-tune-acts button { flex:1; padding:5px 0; font-size:11px; border-radius:6px;
                         border:1px solid var(--gray-200); background:var(--gray-0);
                         color:var(--gray-1000); cursor:pointer; }
  .vw-tune-acts button.pri { background:var(--primary); border-color:var(--primary); color:#fff; }
  .vw-tune-acts button[disabled] { opacity:.45; cursor:default; }

  .img-placeholder { text-align: center; color: var(--gray-700); }
  .img-placeholder i { font-size: 56px; margin-bottom: 10px; display: block; opacity: .4; }
  .img-placeholder p { font-size: 13px; opacity: .6; }
</style>

      <div class="img-viewer">
        {{-- 이미지 위에 얹는 도구 — 위는 회전·복원, 아래는 확대·축소 (시안 137:901) --}}
        <div class="vw-tools">
          <div class="vw-tool-group">
            <button type="button" class="vw-tool" onclick="rotateImg()" title="회전"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="vw-tool" onclick="resetImg()" title="처음으로 복원"><i class="fa-solid fa-arrows-rotate"></i></button>
            {{-- 밝기ㆍ명암도 그림을 매만지는 일이라 회전ㆍ복원과 한 묶음에 둔다.
                 묶음을 따로 세웠더니 .vw-tools 의 space-between 이 도구줄 전체를
                 다시 벌려 놓았다 — 기존 모양이 흐트러졌다(2026-09-09). --}}
            <button type="button" class="vw-tool" id="tuneToggleBtn" onclick="toggleTune()"
                    title="밝기ㆍ명암" style="display:none;"><i class="fa-solid fa-circle-half-stroke"></i></button>
          </div>
          {{-- 여러 쪽짜리 서류의 쪽 넘기기 — PDF 를 볼 때만 선다 --}}
          <div class="vw-tool-group" id="pdfPager" style="display:none;">
            <button type="button" class="vw-tool" onclick="pdfPage(-1)" title="이전 쪽"><i class="fa-solid fa-chevron-up"></i></button>
            <span id="pdfPageLabel" class="vw-zoom">1/1</span>
            <button type="button" class="vw-tool" onclick="pdfPage(1)" title="다음 쪽"><i class="fa-solid fa-chevron-down"></i></button>
          </div>
          <div class="vw-tool-group">
            <button type="button" class="vw-tool" onclick="zoomOut()" title="축소"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <span id="zoomLabel" class="vw-zoom">100%</span>
            <button type="button" class="vw-tool" onclick="zoomIn()" title="확대"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          </div>
        </div>

        {{-- 밝기ㆍ명암 — 끄는 즉시 화면에 보이고, 저장을 눌러야 문서에 남는다.
             파일은 건드리지 않는다. 팩스ㆍ서류를 만들 때 서버가 같은 값을 입힌다. --}}
        <div class="vw-tune" id="tunePanel">
          <div class="vw-tune-row">
            <span>밝기</span>
            <input type="range" id="tuneBright" min="-100" max="100" step="5" value="0" oninput="onTuneInput()">
            <b id="tuneBrightVal">0</b>
          </div>
          <div class="vw-tune-row">
            <span>명암</span>
            <input type="range" id="tuneContrast" min="-100" max="100" step="5" value="0" oninput="onTuneInput()">
            <b id="tuneContrastVal">0</b>
          </div>
          <div class="vw-tune-acts">
            <button type="button" onclick="tuneReset()">원본</button>
            <button type="button" class="pri" id="tuneSaveBtn" onclick="tuneSave()">저장</button>
          </div>
        </div>
        <div class="img-viewer-canvas" id="imgCanvas">
          <div class="vw-busy" id="viewerBusy"><i class="fa-solid fa-circle-notch"></i><span>여는 중…</span></div>
          @php $vwIsPdf = str_contains($vwDoc['mime'] ?? '', 'pdf'); @endphp
          @if(($vwDoc['url'] ?? null) && $vwIsPdf)
            <img id="prescCanvas" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="" draggable="false" />
            <iframe id="pdfCanvas" src="{{ ($vwDoc['url'] ?? null) }}" style="width:100%;height:100%;border:none;background:#fff;"></iframe>
          @elseif(($vwDoc['url'] ?? null))
            <img id="prescCanvas" src="{{ ($vwDoc['url'] ?? null) }}" style="max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="처방전 이미지" draggable="false" />
            <iframe id="pdfCanvas" src="" style="display:none;width:100%;height:100%;border:none;background:#fff;"></iframe>
          @else
            {{-- 볼 것이 없을 때만 서 있는 자리표. 문서를 고르면 걷는다(switchViewerDoc). --}}
            <div class="img-placeholder" id="viewerPlaceholder">
              <i class="fa-regular fa-file-image"></i>
              <p>이미지 없음</p>
            </div>
            <img id="prescCanvas" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;cursor:grab;user-select:none;" alt="" draggable="false" />
            <iframe id="pdfCanvas" src="" style="display:none;width:100%;height:100%;border:none;background:#fff;"></iframe>
          @endif
        </div>
        {{-- 이전·다음과 처방번호는 카드 머리로 올라갔다 (시안 137:883) --}}
      </div>

@push('scripts')
<script>

/* -- PDF 도 그림처럼 본다 ----------------------------------
   PDF 를 <iframe> 에 띄우면 브라우저의 PDF 뷰어가 통째로 맡는다. 그 안에서는 우리
   휠도 끌기도 닿지 않아, 처방전 그림에는 되는 확대ㆍ이동이 서류에는 안 됐다.
   pdf.js 로 한 쪽씩 그려 그림 한 장으로 만들면 이미 있는 손놀림이 그대로 걸린다 --
   새 기능을 만든 것이 아니라 쓰던 것을 쓰게 한 것이다.

   그리다 실패하면(pdf.js 가 안 실려 있거나 파일이 깨졌거나) 예전처럼 <iframe> 으로
   떨어진다. 보이지 않는 것보다는 확대가 안 되는 편이 낫다. */
const PDF_VIEW = { url: null, doc: null, page: 1, total: 0, blobUrl: null, seq: 0 };

function _pdfLib() {
  const lib = window.pdfjsLib;
  if (!lib) return null;
  lib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
  return lib;
}

function _pdfBusy(on) {
  const el = document.getElementById('viewerBusy');
  if (el) el.classList.toggle('on', !!on);
}

function _pdfPagerSync() {
  const pager = document.getElementById('pdfPager');
  const label = document.getElementById('pdfPageLabel');
  if (!pager) return;
  // 한 쪽짜리는 넘길 것이 없다
  pager.style.display = PDF_VIEW.total > 1 ? '' : 'none';
  if (label) label.textContent = PDF_VIEW.page + '/' + PDF_VIEW.total;
}

/** 예전 방식 -- 브라우저 PDF 뷰어에 맡긴다 */
function _pdfFallback(url) {
  const img   = document.getElementById('prescCanvas');
  const frame = document.getElementById('pdfCanvas');
  if (img)   { img.style.display = 'none'; img.removeAttribute('src'); }
  if (frame && url) { frame.src = url; frame.style.display = ''; }
  PDF_VIEW.doc = null; PDF_VIEW.total = 0;
  _pdfPagerSync();
}

async function openPdfInViewer(url) {
  const lib = _pdfLib();
  if (!lib) { _pdfFallback(url); return; }

  const mine = ++PDF_VIEW.seq;          // 빨리 여러 번 고르면 마지막 것만 그린다
  _pdfBusy(true);
  try {
    const doc = await lib.getDocument({ url: url, withCredentials: true }).promise;
    if (mine !== PDF_VIEW.seq) return;
    PDF_VIEW.url = url; PDF_VIEW.doc = doc; PDF_VIEW.total = doc.numPages;
    await renderPdfPage(1, mine);
  } catch (e) {
    if (mine === PDF_VIEW.seq) { _pdfBusy(false); _pdfFallback(url); }
  }
}

async function renderPdfPage(n, seq) {
  if (!PDF_VIEW.doc) return;
  const mine = (seq === undefined) ? PDF_VIEW.seq : seq;
  PDF_VIEW.page = Math.min(Math.max(1, n), PDF_VIEW.total);
  _pdfBusy(true);

  try {
    const page = await PDF_VIEW.doc.getPage(PDF_VIEW.page);
    if (mine !== PDF_VIEW.seq) return;

    /* 그리는 크기 -- 보이는 칸의 두 배로 그려 둔다. 100% 로 볼 때 또렷하고,
       키워도 한동안 버틴다. 그림도 키우면 흐려지므로 거기까지가 같은 값이다. */
    const box   = document.getElementById('imgCanvas');
    const wantW = Math.max(900, Math.min(2200, ((box && box.clientWidth) || 700) * 2));
    const base  = page.getViewport({ scale: 1 });
    const view  = page.getViewport({ scale: wantW / base.width });

    const cv = document.createElement('canvas');
    cv.width  = Math.round(view.width);
    cv.height = Math.round(view.height);
    await page.render({ canvasContext: cv.getContext('2d'), viewport: view }).promise;
    if (mine !== PDF_VIEW.seq) return;

    const blob = await new Promise(r => cv.toBlob(r, 'image/png'));
    if (mine !== PDF_VIEW.seq || !blob) return;
    if (PDF_VIEW.blobUrl) URL.revokeObjectURL(PDF_VIEW.blobUrl);
    PDF_VIEW.blobUrl = URL.createObjectURL(blob);

    const img   = document.getElementById('prescCanvas');
    const frame = document.getElementById('pdfCanvas');
    if (frame) { frame.style.display = 'none'; frame.removeAttribute('src'); }
    if (img)   { img.src = PDF_VIEW.blobUrl; img.style.display = ''; }
    _pdfBusy(false);
    _pdfPagerSync();
    resetImg();

    // 크게 보기 창이 떠 있으면 같은 쪽을 보여 준다
    const bv = document.getElementById('bigViewer');
    if (bv && bv.style.display !== 'none') {
      const bvImg   = document.getElementById('bvImg');
      const bvFrame = document.getElementById('bvFrame');
      if (bvFrame) { bvFrame.style.display = 'none'; bvFrame.removeAttribute('src'); }
      if (bvImg)   { bvImg.src = PDF_VIEW.blobUrl; bvImg.style.display = ''; bvFit(); }
    }
  } catch (e) {
    if (mine === PDF_VIEW.seq) { _pdfBusy(false); _pdfFallback(PDF_VIEW.url); }
  }
}

function pdfPage(delta) {
  if (!PDF_VIEW.doc) return;
  const next = PDF_VIEW.page + delta;
  if (next < 1 || next > PDF_VIEW.total) return;
  renderPdfPage(next);
}

/** 고른 문서를 뷰어에 세운다 -- 그림이든 PDF 든 여기 하나를 지난다 */
function showDoc(doc) {
  if (!doc) return;

  const prescImg = document.getElementById('prescCanvas');
  const pdfFrame = document.getElementById('pdfCanvas');
  const badge    = document.getElementById('viewerBadge');
  const openBtn  = document.getElementById('viewerOpenBtn');

  /* 처방전 그림 없이 열린 건은 「이미지 없음」 자리표가 서 있다. 문서를 고르면 그림이
     그 위에 얹혀 둘이 함께 보였다 -- 볼 것이 생겼으니 자리표는 걷는다. */
  const holder = document.getElementById('viewerPlaceholder');
  if (holder) holder.style.display = 'none';

  if (badge) { badge.textContent = doc.name || ''; badge.style.display = doc.name ? '' : 'none'; }

  /* 밝기ㆍ명암은 문서마다 따로다 — 고른 문서의 값을 싣는다.
     PDF 와 시스템이 만든 서류에는 맞출 자리가 없다(tuneKey 가 없다). */
  setViewerTune(doc.tuneKey || null, doc.bright || 0, doc.contrast || 0);

  if (doc.isPdf) {
    openPdfInViewer(doc.url);
  } else {
    PDF_VIEW.seq++; PDF_VIEW.doc = null; PDF_VIEW.total = 0;
    _pdfBusy(false);
    _pdfPagerSync();
    if (pdfFrame) { pdfFrame.style.display = 'none'; pdfFrame.removeAttribute('src'); }
    if (prescImg) { prescImg.src = doc.url; prescImg.style.display = ''; }
    resetImg();
  }

  if (openBtn) { openBtn.href = doc.url || '#'; openBtn.style.display = ''; }
  // 처음에 볼 것이 없어 숨겨 두었더라도, 문서를 고른 이상 열 수 있어야 한다
  ['btnBigViewer', 'btnResetView'].forEach(function (id) {
    const b = document.getElementById(id);
    if (b) b.style.display = '';
  });
}
</script>
<script>
(function () {
  // ── 이미지 조작 ────────────────────────────────────────
  let zoomLevel = 100, rotation = 0;
  let _tx = 0, _ty = 0;           // 드래그 누적 이동량 (px)
  let _drag = false, _sx = 0, _sy = 0; // 드래그 시작점

  /* 도구 단추의 onclick 이 부른다 — 인라인 handler 는 전역에서만 이름을 찾으므로
     감싸 둔 함수 안에 두면 「is not defined」로 죽는다. showDoc 도 이 넷을 쓴다. */
  window.zoomIn    = function () { zoomLevel = Math.min(zoomLevel+100, 500); applyTransform(); };
  window.zoomOut   = function () { zoomLevel = Math.max(zoomLevel-100, 100); applyTransform(); };
  window.rotateImg = function () { rotation  = (rotation+90)%360;           applyTransform(); };
  window.resetImg  = function () { zoomLevel = 100; rotation = 0; _tx = 0; _ty = 0; applyTransform(); };

  function applyTransform() {
    document.getElementById('zoomLabel').textContent = zoomLevel + '%';
    const img = document.getElementById('prescCanvas');
    if (img) img.style.transform = `translate(${_tx}px,${_ty}px) scale(${zoomLevel/100}) rotate(${rotation}deg)`;
  }


  /* ── 밝기ㆍ명암 (2026-09-09 지시) ─────────────────────

     예전 사진 보정은 **올릴 때 파일을 그 자리에서 고쳤다.** 스캐너로 곧게 뜬 것까지
     나빠졌고, 되돌릴 길이 없어 걷어냈다.

     이번에는 파일에 손대지 않는다. 화면은 CSS filter 로 그 자리에서 보여 주고,
     저장하면 숫자 둘만 문서에 남는다. 공단 팩스와 서류를 만들 때 서버가 같은 값을
     GD 로 입힌다 — 그래서 언제든 0 으로 되돌리면 원본이다.

     둘 다 -100 ~ 100, 0 이 원본이다. */
  let _tuneKey = null;                 // 지금 보고 있는 문서. null 이면 맞출 수 없다
  let _tuneSaved = { b: 0, c: 0 };     // 문서에 적혀 있는 값 — 저장 단추를 가리는 잣대

  /* 볼 것이 바뀔 때마다 부른다 (showDoc). 첫 화면은 서버가 심어 둔 값으로 선다. */
  window.setViewerTune = function (key, b, c) {
    _tuneKey = key || null;
    _tuneSaved = { b: +b || 0, c: +c || 0 };

    /* 저장할 자리를 아는 화면에서만 세운다 — 거래처 관리처럼 보기만 하는 화면에서는
       맞춰 봐야 남길 곳이 없어 헛일이 된다. */
    const 맞출수있나 = !!_tuneKey && typeof TUNE_SAVE_URL !== 'undefined';
    const wrap = document.getElementById('tuneToggleBtn');
    if (wrap) wrap.style.display = 맞출수있나 ? '' : 'none';
    if (!맞출수있나) {
      document.getElementById('tunePanel')?.classList.remove('on');
    }

    _tuneSet(_tuneSaved.b, _tuneSaved.c);
  };

  function _tuneSet(b, c) {
    const eb = document.getElementById('tuneBright');
    const ec = document.getElementById('tuneContrast');
    if (eb) eb.value = b;
    if (ec) ec.value = c;
    onTuneInput();
  }

  /* 슬라이더를 끌 때마다 부른다 — 그림에 그대로 얹는다 */
  window.onTuneInput = function () {
    const b = +(document.getElementById('tuneBright')?.value ?? 0);
    const c = +(document.getElementById('tuneContrast')?.value ?? 0);

    const lb = document.getElementById('tuneBrightVal');
    const lc = document.getElementById('tuneContrastVal');
    if (lb) lb.textContent = b;
    if (lc) lc.textContent = c;

    const img = document.getElementById('prescCanvas');
    if (img) {
      img.style.filter = (b || c)
        ? `brightness(${1 + b / 100}) contrast(${1 + c / 100})`
        : '';
    }

    /* 적혀 있는 값과 같으면 저장할 것이 없다 */
    const btn = document.getElementById('tuneSaveBtn');
    if (btn) btn.disabled = (b === _tuneSaved.b && c === _tuneSaved.c);
  };

  window.toggleTune = function () {
    document.getElementById('tunePanel')?.classList.toggle('on');
  };

  /* 원본으로 — 아직 저장한 것은 아니다. 저장을 눌러야 문서에서도 지워진다. */
  window.tuneReset = function () { _tuneSet(0, 0); };

  window.tuneSave = async function () {
    if (!_tuneKey || typeof TUNE_SAVE_URL === 'undefined') return;

    const b = +(document.getElementById('tuneBright')?.value ?? 0);
    const c = +(document.getElementById('tuneContrast')?.value ?? 0);
    const btn = document.getElementById('tuneSaveBtn');

    if (btn) { btn.disabled = true; btn.textContent = '저장 중...'; }
    try {
      const res = await apiRequest(TUNE_SAVE_URL, 'POST',
        { key: _tuneKey, brightness: b, contrast: c });
      if (!res?.success) throw new Error(res?.message || '저장하지 못했습니다.');

      _tuneSaved = { b, c };
      /* 목록이 들고 있는 값도 함께 맞춘다 — 다른 문서를 봤다 돌아와도 그대로다 */
      if (typeof ALL_DOCS !== 'undefined') {
        const d = ALL_DOCS.find(x => x && x.tuneKey === _tuneKey);
        if (d) { d.bright = b; d.contrast = c; }
      }
      /* 저장했으면 할 일이 끝났다 — 칸이 계속 떠 있으면 그림을 가린다 */
      document.getElementById('tunePanel')?.classList.remove('on');
      showToast('밝기ㆍ명암을 저장했습니다. 팩스와 서류에도 적용됩니다.', 'success');
    } catch (e) {
      showToast(e.message || '저장하지 못했습니다.', 'danger');
    } finally {
      if (btn) { btn.textContent = '저장'; }
      onTuneInput();
    }
  };

  // 드래그 이벤트 초기화 (DOMContentLoaded 이후 실행)
  document.addEventListener('DOMContentLoaded', function () {
    const img = document.getElementById('prescCanvas');
    if (!img) return;

    img.addEventListener('mousedown', function (e) {
      if (e.button !== 0) return;
      _drag = true;
      _sx   = e.clientX - _tx;
      _sy   = e.clientY - _ty;
      img.style.cursor = 'grabbing';
      e.preventDefault();
    });

    document.addEventListener('mousemove', function (e) {
      if (!_drag) return;
      _tx = e.clientX - _sx;
      _ty = e.clientY - _sy;
      applyTransform();
    });

    document.addEventListener('mouseup', function () {
      if (!_drag) return;
      _drag = false;
      const img = document.getElementById('prescCanvas');
      if (img) img.style.cursor = 'grab';
    });

    // 더블클릭으로 위치 초기화
    img.addEventListener('dblclick', function () {
      _tx = 0; _ty = 0; applyTransform();
    });

    // 스크롤(휠)로 확대/축소 — 커서 위치 기준
    const canvas = document.getElementById('imgCanvas');
    if (canvas) {
      canvas.addEventListener('wheel', function (e) {
        e.preventDefault();
        const step    = 30;
        const prevZoom = zoomLevel;
        if (e.deltaY < 0) {
          zoomLevel = Math.min(zoomLevel + step, 500);
        } else {
          zoomLevel = Math.max(zoomLevel - step, 20);
        }
        // 커서 위치 기준으로 이동량 보정
        const rect    = canvas.getBoundingClientRect();
        const cx      = e.clientX - rect.left - rect.width  / 2;
        const cy      = e.clientY - rect.top  - rect.height / 2;
        const scale   = zoomLevel / prevZoom;
        _tx = cx + (_tx - cx) * scale;
        _ty = cy + (_ty - cy) * scale;
        applyTransform();
      }, { passive: false });
    }
  });
})();
</script>
@endpush
