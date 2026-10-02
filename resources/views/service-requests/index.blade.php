{{-- resources/views/service-requests/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SR 관리')
@section('page-title', 'SR 관리')
@section('breadcrumb', '홈 - 지원 - SR 관리')

@push('styles')
<style>
  /* .status-tabs / .status-tab 은 예전 선택자다. 칩은 전역 .ds-chip 이 그리고,
     이 이름은 앵커로만 남긴다 — 별도 스타일은 주지 않는다.
     (스타일을 남겨 두면 gap 6 · radius 20 · 12.5px/600 이 전역 규격을 덮어쓴다.) */

  /* ── 목록 위 얇은 띠 — 검색 조건 단추와 목록 단추가 선다 ──────────── */
  .srx-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
  .srx-bar .spacer { flex:1; }
  /* 지금 걸려 있는 조건을 글로 적는다 — 팝오버를 닫으면 무엇으로 걸러진 목록인지
     알 수 없다. 조건이 없으면 이 자리도 서지 않는다. */
  .srx-cond { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;
    font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); }
  .srx-cond b { color:var(--gray-1000); font-weight:700; }

  /* ── 옮길 수 있는 팝오버 ──────────────────────────────────────────
     채팅창과 같은 방식이다(layouts/app.blade.php 의 initDrag) — 머리를 잡고 끌면
     움직이고, 둔 자리는 브라우저에 기억해 둔다. 두 팝오버가 같은 규격을 쓴다. */
  .srx-pop { position:fixed; z-index:1200; display:none;
    background:var(--gray-0); border:1px solid var(--gray-200); border-radius:12px;
    box-shadow:0 12px 40px rgba(0,0,0,.18); }
  .srx-pop.open { display:block; }
  .srx-pop.dragging { opacity:.92; }
  .srx-pop-head { display:flex; align-items:center; gap:8px; height:44px; padding:0 12px 0 16px;
    border-bottom:1px solid var(--gray-200); cursor:grab; user-select:none; }
  .srx-pop-head:active { cursor:grabbing; }
  .srx-pop-head .ttl { flex:1; font-size:14px; font-weight:700; line-height:22px;
    color:var(--gray-1000); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .srx-pop-head .x { width:28px; height:28px; border:0; background:none; cursor:pointer;
    color:var(--gray-600); font-size:16px; border-radius:6px; }
  .srx-pop-head .x:hover { background:var(--gray-100); color:var(--gray-1000); }
  .srx-pop-body { padding:16px; max-height:calc(100vh - 160px); overflow-y:auto; }
  .srx-pop-foot { display:flex; align-items:center; justify-content:flex-end; gap:8px;
    padding:12px 16px; border-top:1px solid var(--gray-200); }

  /* 등록ㆍ답변 창은 넓게 쓴다 (2026-10-01 지시).

     글을 적는 자리라 좁으면 줄이 잘게 끊기고, 답변 창은 요청 내용과 지난 답변까지
     함께 보여 주어 창 안에서 다시 굴려야 했다. 화면이 좁으면 화면 너비를 따른다.
     검색 창은 칸 몇 개뿐이라 그대로 둔다. */
  #srxPopSearch { width:420px; }
  #srxPopNew    { width:min(960px, calc(100vw - 48px)); }
  #srxPopAnswer { width:min(1080px, calc(100vw - 48px)); }

  /* 창이 커진 만큼 안쪽도 길게 쓴다 — 바닥줄(저장ㆍ닫기)이 밀려나지 않게 머리ㆍ바닥
     높이를 뺀 값으로 잡는다. */
  #srxPopNew .srx-pop-body,
  #srxPopAnswer .srx-pop-body { max-height:calc(100vh - 150px); }

  /* 글 적는 칸도 함께 키운다 — 창만 넓히고 칸이 그대로면 빈 자리만 넓어진다 */
  #srxPopNew .ce-quill .ql-editor    { min-height:260px; }
  #srxPopAnswer .ce-quill .ql-editor { min-height:300px; }

  @media (max-width:800px) {
    #srxPopSearch, #srxPopNew, #srxPopAnswer { width:calc(100vw - 24px); }
    #srxPopNew .ce-quill .ql-editor,
    #srxPopAnswer .ce-quill .ql-editor { min-height:180px; }
  }

  /* ── 팝오버 안의 필드 — .ds-filter-field 와 같은 규격(라벨 21 + gap 8 + 인풋 32) ── */
  .srx-field { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
  .srx-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  .srx-field input[type=text], .srx-field select, .srx-field textarea {
    padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; color:var(--gray-1000);
    background:var(--gray-0); font-family:inherit; width:100%; box-sizing:border-box; }
  .srx-field input[type=text], .srx-field select { height:32px; }
  /* 여러 줄 입력은 32px 규격을 그대로 쓰면 위아래가 눌린다 */
  .srx-field textarea { min-height:120px; resize:vertical; padding:9px 12px; line-height:21px; }
  .srx-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .srx-hint { font-size:12px; font-weight:400; line-height:19px; color:var(--gray-600); }

  /* ── 답변 팝오버의 요청 내용 ─────────────────────────────────── */
  .srx-sec { margin-bottom:16px; }
  .srx-sec h4 { margin:0 0 8px; font-size:13px; font-weight:700; line-height:21px; color:var(--gray-1000); }
  .srx-meta { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-bottom:8px; }
  /* 꾸밈글(Quill)이 들어오는 자리다 — `white-space:pre-wrap` 을 걷는다 (2026-10-01).

     글자만 담던 때의 규칙이었다. 지금은 <p>ㆍ<ul> 로 들어오는데, 이름표 사이의
     줄바꿈과 들여쓰기가 그대로 찍혀 문단마다 빈 줄이 벌어지고 목록이 어긋나 보였다.
     줄바꿈은 <br> 과 <p> 가 맡는다.

     `max-height` 도 걷는다. 창이 넓어지면서(960·1080) 안쪽은 창이 굴리면 되고,
     220px 로 잘라 두면 긴 답변이 작은 상자 안에서 다시 굴러야 한다. */
  .srx-body { font-size:13px; font-weight:400; line-height:21px;
    color:var(--gray-1000); background:var(--gray-50); border:1px solid var(--gray-200);
    border-radius:8px; padding:12px; }
  /* 담긴 글이 옛 글자뿐인 줄(이름표가 하나도 없는 것)은 줄바꿈을 살려 둔다 */
  .srx-body.is-plain { white-space:pre-wrap; }
  /* 등록자ㆍSR 담당자를 나란히 적는다 — 누가 올렸고 누가 답하는지가 이 창의 머리말이다 */
  .srx-who { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
  .srx-who .k { font-size:12px; font-weight:500; line-height:19px; color:var(--gray-600); margin-bottom:4px; }
  .srx-who .v { font-size:13px; font-weight:700; line-height:21px; color:var(--gray-1000); }
  .srx-answer { margin-top:12px; padding:12px; background:var(--primary-50);
    border:1px solid var(--gray-200); border-radius:8px; }
  .srx-answer .lbl { font-size:11px; font-weight:700; line-height:18px; color:var(--primary); margin-bottom:4px; }

  /* 상태 배지 — 배지 규격(r6 · pad 2/6 · 11px/500 · lh18).
     ★ 반드시 .srx-meta 로 한 단계 좁힌다. layouts/app.blade.php 에도 같은 이름의
     .sr-badge / .sr-b-* 가 있는데 그 <style> 은 <body> 안이고 @stack('styles') 는
     <head> 라서 특정성이 같으면 전역이 이긴다. 이름 그대로 두면 한 줄도 먹지 않는다. */
  .srx-meta .sr-badge { display:inline-flex; align-items:center; font-size:11px; font-weight:500; line-height:18px;
    padding:2px 6px; border-radius:6px; }
  /* 진행 상태 — 신규ㆍ진행중ㆍ완료ㆍ대기 (2026-10-01 지시) */
  .srx-meta .sr-b-new         { background:var(--alert-100);   color:var(--alert-500); }
  .srx-meta .sr-b-in_progress { background:var(--primary-100); color:var(--primary-600); }
  .srx-meta .sr-b-done        { background:var(--primary);     color:var(--gray-0); }
  .srx-meta .sr-b-hold        { background:var(--gray-100);    color:var(--gray-600); }
</style>
@endpush

@section('content')

@php
  $curStatus = request('status');
  /* 지금 걸린 조건을 글로 적어 둔다 — 팝오버를 닫은 뒤에도 무엇으로 걸렀는지 보여야 한다 */
  $걸린것 = [];
  if ($curStatus)            { $걸린것[] = ['상태',   $statuses[$curStatus] ?? $curStatus]; }
  if (request('category'))   { $걸린것[] = ['구분',   $categories[request('category')] ?? request('category')]; }
  if (request('q'))          { $걸린것[] = ['검색어', request('q')]; }
@endphp

{{-- 목록 위 얇은 띠 — 검색은 팝오버로 옮겼다 (2026-10-01 지시).

     여태 검색 필터가 흰 카드 한 장을 통째로 차지해, 목록을 보려면 늘 그만큼 아래로
     밀렸다. 조건을 고르는 일은 잠깐이고 목록을 보는 일이 오래다. --}}
<div class="srx-bar">
  @perm('service-requests', 'create')
  {{-- 탭이 아니라 띠의 단추로 둔다 (2026-10-01 지시 「신규 버튼이 안보임」).
       탭을 걷을 때 등록하는 자리까지 함께 걷은 것이 잘못이었다 — 상단 패널의
       작은 아이콘 하나로는 눈에 띄지 않는다. --}}
  <button type="button" class="ds-btn ds-btn-primary" onclick="srxPop.open('srxPopNew')">
    <i class="fa-solid fa-plus"></i> 신규 등록
  </button>
  @endperm
  <button type="button" class="ds-btn" onclick="srxPop.open('srxPopSearch')">
    <i class="fa-solid fa-magnifying-glass"></i> 검색 조건
  </button>

  @if($걸린것)
    <span class="srx-cond">
      @foreach($걸린것 as [$이름, $값])
        <span>{{ $이름 }} <b>{{ $값 }}</b></span>@if(! $loop->last)<span style="opacity:.4;">·</span>@endif
      @endforeach
    </span>
    <a href="{{ route('sr.index') }}" class="ds-btn">초기화</a>
  @endif

  <span class="spacer"></span>

  <button type="button" class="ds-btn" onclick="window.__srxGrid?.downloadExcel()">엑셀 다운</button>
  @perm('service-requests', 'delete')
  <button type="button" class="ds-btn" style="color:var(--alert-500);" onclick="srDeleteSelected()">
    <i class="bx bx-trash"></i> 선택 삭제
  </button>
  @endperm
</div>

{{-- 흰 카드(r12) 안에 머리줄과 그리드 --}}
<div class="ds-grid-section">
  <div class="ds-grid-card">
    {{-- 탭은 하나만 남는다 (2026-10-01 지시).

         「신규 등록」은 상단 SR 패널이 같은 일을 하고, 그쪽은 보고 있던 화면까지 함께
         적어 준다 — 두 자리에 두면 한쪽만 고치는 날이 온다.
         「상세ㆍ답변」은 목록 줄을 두 번 눌러 여는 팝오버로 옮겼다. --}}
    <div class="pnl-tabs">
      <button type="button" class="pnl-tab active" onclick="return false;">
        <i class="fa-solid fa-list"></i> SR 목록<span class="pnl-tab-cnt">(총 <b>{{ number_format($total) }}</b>건)</span>
      </button>
    </div>

    <div id="srxGrid"></div>
  </div>{{-- /.ds-grid-card --}}
</div>{{-- /.ds-grid-section --}}


{{-- ── 신규 등록 팝오버 — 머리를 잡고 끌어 옮길 수 있다 ───────────── --}}
@perm('service-requests', 'create')
<div class="srx-pop" id="srxPopNew" role="dialog" aria-label="SR 신규 등록">
  <div class="srx-pop-head" data-pop-drag>
    <i class="fa-solid fa-plus" style="font-size:13px;color:var(--gray-400);"></i>
    <span class="ttl">SR 신규 등록</span>
    <button type="button" class="x" onclick="srxPop.close('srxPopNew')" aria-label="닫기">
      <i class="bx bx-x"></i>
    </button>
  </div>
  <div class="srx-pop-body">
    <div class="srx-row2">
      <div class="srx-field">
        <label for="srxCategory">구분</label>
        <select id="srxCategory">
          @foreach($categories as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
      </div>
      <div class="srx-field">
        <label for="srxPriority">우선순위</label>
        <select id="srxPriority">
          @foreach($priorities as $k => $v)
            <option value="{{ $k }}" {{ $k === 'normal' ? 'selected' : '' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="srx-field">
      <label for="srxTitle">제목 <span style="color:var(--alert-500);">*</span></label>
      <input type="text" id="srxTitle" maxlength="200" placeholder="예) 처방전 목록에 발행일 필터 추가">
    </div>
    <div class="srx-field">
      <label>내용 <span style="color:var(--alert-500);">*</span></label>
      {{-- 답변 칸과 같은 편집기다 — 창을 처음 열 때 선다 --}}
      <div class="ce-quill"><div id="srxContent"
           data-ph="어떤 화면에서 무엇이 어떻게 되면 좋을지 입력해 주십시오."></div></div>
    </div>
    <div class="srx-field" style="margin-bottom:0;">
      <label for="srxPageLabel">대상 화면</label>
      <input type="text" id="srxPageLabel" maxlength="100" placeholder="예) 처방전 목록">
      <span class="srx-hint">비워 두면 기록되지 않습니다. 상단 SR 패널로 등록하면 보고 있던 화면이 자동 기록됩니다.</span>
    </div>
  </div>
  <div class="srx-pop-foot">
    <button type="button" class="ds-btn" onclick="srxPop.close('srxPopNew')">닫기</button>
    <button type="button" class="ds-btn ds-btn-primary" id="srxSubmitBtn" onclick="srxSubmit()">
      <i class="bx bx-send"></i> 등록
    </button>
  </div>
</div>
@endperm

{{-- ── 검색 조건 팝오버 — 머리를 잡고 끌어 옮길 수 있다 ───────────── --}}
<div class="srx-pop" id="srxPopSearch" role="dialog" aria-label="검색 조건">
  <div class="srx-pop-head" data-pop-drag>
    <i class="fa-solid fa-magnifying-glass" style="font-size:13px;color:var(--gray-400);"></i>
    <span class="ttl">검색 조건</span>
    <button type="button" class="x" onclick="srxPop.close('srxPopSearch')" aria-label="닫기">
      <i class="bx bx-x"></i>
    </button>
  </div>
  <form method="GET" action="{{ route('sr.index') }}">
    <div class="srx-pop-body">
      <div class="srx-field">
        {{-- 상태가 무엇을 볼지 가장 크게 가른다 — 첫 칸에 둔다 --}}
        <label for="srxFStatus">상태</label>
        <select name="status" id="srxFStatus" class="form-control form-select">
          <option value="">전체 ({{ $counts['all'] }})</option>
          @foreach($statuses as $key => $label)
            <option value="{{ $key }}" {{ $curStatus === $key ? 'selected' : '' }}>
              {{ $label }}@if(($counts[$key] ?? 0) > 0) ({{ $counts[$key] }})@endif
            </option>
          @endforeach
        </select>
      </div>
      <div class="srx-field">
        <label for="srxFCategory">구분</label>
        <select name="category" id="srxFCategory" class="form-control form-select">
          <option value="">전체 구분</option>
          @foreach($categories as $k => $v)
            <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="srx-field" style="margin-bottom:0;">
        <label for="srxFQ">검색어</label>
        <input type="text" name="q" id="srxFQ" value="{{ request('q') }}" class="form-control"
               placeholder="제목ㆍ내용">
      </div>
    </div>
    <div class="srx-pop-foot">
      <a href="{{ route('sr.index') }}" class="ds-btn">초기화</a>
      <button type="submit" class="ds-btn ds-btn-primary">
        <i class="fa-solid fa-magnifying-glass"></i> 검색
      </button>
    </div>
  </form>
</div>

{{-- ── 답변 팝오버 — 목록 줄을 두 번 누르면 열린다 ────────────────── --}}
<div class="srx-pop" id="srxPopAnswer" role="dialog" aria-label="SR 답변">
  <div class="srx-pop-head" data-pop-drag>
    <i class="fa-solid fa-comments" style="font-size:13px;color:var(--gray-400);"></i>
    <span class="ttl" id="srxPopTitle">SR 답변</span>
    <button type="button" class="x" onclick="srxPop.close('srxPopAnswer')" aria-label="닫기">
      <i class="bx bx-x"></i>
    </button>
  </div>
  <div class="srx-pop-body">
    {{-- 등록자ㆍSR 담당자 — 누가 올렸고 누가 답하는지 (2026-10-01 지시).
         SR 담당자는 답변을 적는 사람이다. 아직 답변이 없으면 지금 보고 있는 사람으로
         적어 둔다 — 저장하면 그 이름이 그대로 담긴다(answered_by). --}}
    <div class="srx-who">
      <div>
        <div class="k">등록자</div>
        <div class="v" id="srxWhoWriter">-</div>
      </div>
      <div>
        <div class="k">SR 담당자</div>
        <div class="v" id="srxWhoAnswerer">-</div>
      </div>
    </div>

    <div class="srx-sec">
      <h4>요청 내용</h4>
      <div class="srx-meta" id="srxMeta"></div>
      {{-- 담긴 글은 담길 때 걸러진 것이다(App\Support\RichText) — 꾸밈을 그대로 그린다 --}}
      <div class="srx-body ce-rich" id="srxContentBox"></div>
      <div id="srxPrevAnswer"></div>
    </div>

    @perm('service-requests', 'update')
    <div class="srx-sec" style="margin-bottom:0;">
      <h4>답변</h4>
      <div class="srx-field">
        <label>답변 내용</label>
        {{-- Quill 편집기 — 창을 처음 열 때 선다 (2026-10-01 지시) --}}
        <div class="ce-quill"><div id="srxAnswer"
             data-ph="처리 결과나 안내 사항을 입력해 주십시오."></div></div>
      </div>
      <div class="srx-field" style="margin-bottom:0;max-width:220px;">
        <label for="srxStatus">상태</label>
        <select id="srxStatus">
          @foreach($statuses as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </select>
      </div>
    </div>
    @else
    <div class="srx-hint">답변 권한이 없어 조회만 가능합니다.</div>
    @endperm
  </div>
  @perm('service-requests', 'update')
  <div class="srx-pop-foot">
    <button type="button" class="ds-btn" onclick="srxPop.close('srxPopAnswer')">닫기</button>
    <button type="button" class="ds-btn ds-btn-primary" id="srxAnswerBtn" onclick="srxSaveAnswer()">
      <i class="bx bx-save"></i> 답변 저장
    </button>
  </div>
  @endperm
</div>

@endsection

@push('scripts')
<script>
(function () {
  const BASE  = @json(url('sr'));
  const CSRF  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const CLS   = { new:'sr-b-new', in_progress:'sr-b-in_progress', done:'sr-b-done', hold:'sr-b-hold' };
  const ME    = @json(Auth::user()?->name ?? '');
  let _rows = @json($gridData);
  let _sel  = null;

  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  /* ── 옮길 수 있는 팝오버 ───────────────────────────────────────────
     채팅창(layouts/app.blade.php 의 initDrag)과 같은 방식이다 — pointer 사건으로
     끌고, setPointerCapture 로 창 밖으로 나가도 놓치지 않으며, 둔 자리를
     localStorage 에 적어 둔다.

     처음 여는 자리는 화면 가운데다. 끌어 옮긴 뒤에는 그 자리를 기억한다 — 옮겨 둔
     사람이 다시 열 때마다 가운데로 돌아가면 옮긴 뜻이 없다. */
  window.srxPop = (function () {
    const 자리열쇠 = id => 'srxPop.pos.' + id;

    function 안으로(el, left, top) {
      const b = el.getBoundingClientRect();
      const x = Math.max(8, Math.min(left, window.innerWidth  - b.width  - 8));
      const y = Math.max(8, Math.min(top,  window.innerHeight - b.height - 8));
      el.style.left = x + 'px';
      el.style.top  = y + 'px';
    }

    function 자리세우기(el) {
      let 둔자리 = null;
      try { 둔자리 = JSON.parse(localStorage.getItem(자리열쇠(el.id)) || 'null'); } catch (_) {}

      if (둔자리 && Number.isFinite(둔자리.left) && Number.isFinite(둔자리.top)) {
        안으로(el, 둔자리.left, 둔자리.top);
        return;
      }

      const b = el.getBoundingClientRect();
      안으로(el, (window.innerWidth - b.width) / 2, Math.max(64, (window.innerHeight - b.height) / 3));
    }

    function 끌기붙이기(el) {
      const 머리 = el.querySelector('[data-pop-drag]');
      if (!머리 || 머리.dataset.bound === '1') return;
      머리.dataset.bound = '1';

      let sx = 0, sy = 0, ox = 0, oy = 0, 끄는중 = false;

      머리.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || e.target.closest('button')) return;
        const b = el.getBoundingClientRect();
        sx = e.clientX; sy = e.clientY; ox = b.left; oy = b.top;
        끄는중 = true;
        el.classList.add('dragging');
        머리.setPointerCapture(e.pointerId);
      });

      머리.addEventListener('pointermove', (e) => {
        if (!끄는중) return;
        안으로(el, ox + (e.clientX - sx), oy + (e.clientY - sy));
      });

      const 멈춤 = (e) => {
        if (!끄는중) return;
        끄는중 = false;
        el.classList.remove('dragging');
        try { 머리.releasePointerCapture(e.pointerId); } catch (_) {}
        const b = el.getBoundingClientRect();
        localStorage.setItem(자리열쇠(el.id), JSON.stringify({ left: b.left, top: b.top }));
      };
      머리.addEventListener('pointerup', 멈춤);
      머리.addEventListener('pointercancel', 멈춤);
    }

    /* 창을 줄여 팝오버가 화면 밖으로 나가면 도로 끌어들인다 */
    window.addEventListener('resize', () => {
      document.querySelectorAll('.srx-pop.open').forEach((el) => {
        const b = el.getBoundingClientRect();
        안으로(el, b.left, b.top);
      });
    });

    /* 바깥을 눌러도 닫지 않는다 — 옮겨 둔 창이 목록을 누를 때마다 사라지면
       두 번 누르기로 여는 뜻이 없다. 닫는 길은 × 와 Esc 둘이다. */
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      const 열린것 = document.querySelector('.srx-pop.open');
      if (열린것) 닫기(열린것.id);
    });

    function 열기(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('open');
      끌기붙이기(el);
      자리세우기(el);

      /* 그 창에 편집기 자리가 있으면 세운다 — 열 때 세우므로 편집기를 쓰지 않는
         사람은 Quill 을 내려받지 않는다. 두 번째부터는 이미 선 것을 돌려준다.

         **Quill 이 만들어 놓은 것은 고르지 않는다** (2026-10-02 「답변 항목에 빈
         입력칸이 있고 아래 입력칸에 답변이 있음」).

         여태 `.ce-quill > div` 를 그대로 돌았다. 그런데 Quill 은 세워지면서 제
         도구막대를 **같은 자리 안에** 끼워 넣는다. 그래서 창을 두 번째로 열면 그
         도구막대까지 편집기로 만들어, 빈 칸이 하나씩 쌓였다 —

           한 번 열면   .ql-toolbar + #srxAnswer(.ql-container)        편집기 1
           두 번 열면   .ql-toolbar + .ql-toolbar.ql-container + …     편집기 2
           세 번 열면                                                   편집기 3

         위에 선 빈 칸에 글을 적어도 저장되지 않는다. 저장은 #srxAnswer 의 것을
         읽기 때문이다. 담긴 답변은 아래 칸에만 보인다 — 그것이 담당자가 본 모습이다.

         Quill 이 붙이는 이름표(ql-toolbar · ql-container)를 빼고 고른다. */
      el.querySelectorAll('.ce-quill > div:not(.ql-toolbar):not(.ql-container)').forEach((자리) => {
        window.ceQuill?.make(자리, 자리.dataset.ph || '')
          .catch(() => showToast('편집기를 불러오지 못했습니다.', 'warning'));
      });

      el.querySelector('input, select')?.focus();
    }

    function 닫기(id) {
      document.getElementById(id)?.classList.remove('open');
    }

    return { open: 열기, close: 닫기 };
  })();

  const grid = new wwGrid({
    el: document.getElementById('srxGrid'),
    height: 'fit', editable: false, rowCheckbox: true, rowNumber: true,
    // 엑셀 저장은 목록 위 띠로 옮겼다(동작은 downloadExcel() 동일).
    toolbar: false,
    // 하단 상태바는 시안에 없다 — 전체 건수는 탭 이름에 있다.
    footer: { total: true, selected: false, modified: false },
    columns: [
      { header: '상태',      name: 'statusLabel',   width: 90,  align: 'center', sortable: true },
      { header: '구분',      name: 'categoryLabel', width: 100, align: 'center', sortable: true },
      { header: '우선순위',  name: 'priorityLabel', width: 80,  align: 'center', sortable: true },
      { header: '제목',      name: 'title',         width: 300 },
      { header: '대상 화면', name: 'page',          width: 150 },
      { header: 'SR 담당자', name: 'answerer',      width: 100 },
      /* 자취(누가 언제)는 맨 끝에 둔다 — 눈이 먼저 닿아야 할 자리는 업무다.
         시ㆍ분ㆍ초까지 적는다 (2026-09-07 지시). */
      { header: '등록자',    name: 'writer',        width: 100, sortable: true },
      { header: '등록 일시', name: 'created',       width: 160, align: 'center', sortable: true },
    ],
    data: _rows,
  });
  window.__srxGrid = grid;
  /* 쪽 나누기 — 모든 목록 화면이 한 모양으로 쓴다 (2026-10-02 지시).
     표 바로 아래에 줄이 선다. 합계ㆍ엑셀ㆍ정렬은 전체 기준을 지킨다. */
  cePager.붙이기(grid);

  /* 두 번 누르면 답변 창이 열린다 (2026-10-01 지시).

     한 번 누르기로 열지 않는다 — 체크상자를 고르거나 칸을 훑는 동안 창이 열리면
     목록을 읽을 수 없다. 두 번 누르기는 「이 줄을 열겠다」는 분명한 뜻이다. */
  document.getElementById('srxGrid').addEventListener('dblclick', function (e) {
    if (e.target.closest('input, button, a, select, textarea')) return;
    const cell = e.target.closest('[data-row-index]');
    if (!cell) return;
    const row = grid.getData()[parseInt(cell.dataset.rowIndex, 10)];
    if (row) 답변창열기(row.id);
  });

  function 답변창열기(id) {
    const r = _rows.find(x => x.id === id);
    if (!r) return;
    _sel = r;

    document.getElementById('srxPopTitle').textContent = r.title;
    document.getElementById('srxWhoWriter').textContent = r.writer || '-';
    /* 아직 답변이 없으면 지금 보고 있는 사람이 담당자가 된다 — 저장하면 그 이름이
       answered_by 로 담긴다. 「(예정)」을 붙여 아직 담긴 값이 아님을 밝힌다. */
    document.getElementById('srxWhoAnswerer').textContent =
      r.answerer ? r.answerer : (ME ? ME + ' (예정)' : '-');

    document.getElementById('srxMeta').innerHTML = `
      <span class="sr-badge ${CLS[r.status] || ''}">${esc(r.statusLabel)}</span>
      · ${esc(r.categoryLabel)} · 우선순위 ${esc(r.priorityLabel)}
      · ${esc(r.created)}${r.page ? ' · 대상: ' + esc(r.page) : ''}`;

    /* 옛 글자뿐인 줄은 줄바꿈을 살려야 한다 — 이름표가 하나도 없으면 그렇게 본다.
       꾸밈글(Quill)은 <p>ㆍ<ul> 이 줄바꿈을 맡으므로 pre-wrap 을 걸면 빈 줄이 벌어진다
       (2026-10-01 「팝업 내용 틀이 깨져 보임」). */
    const 내용칸 = document.getElementById('srxContentBox');
    내용칸.innerHTML = r.content || '';
    내용칸.classList.toggle('is-plain', !/<[a-z][\s\S]*>/i.test(r.content || ''));

    document.getElementById('srxPrevAnswer').innerHTML = r.answer
      ? `<div class="srx-answer">
           <div class="lbl">답변 · ${esc(r.answerer)} · ${esc(r.answered_at)}</div>
           <div class="srx-body ce-rich${/<[a-z][\s\S]*>/i.test(r.answer || '') ? '' : ' is-plain'}" style="background:transparent;border:0;padding:0;">${r.answer}</div>
         </div>`
      : '';

    /* 답변 편집기 — 처음 열 때 세우고, 담긴 글을 넣는다. 두 번째부터는
       ceQuill.make 가 이미 선 것을 그대로 돌려준다. */
    const a = document.getElementById('srxAnswer');
    if (a) {
      ceQuill.make(a, '처리 결과나 안내 사항을 입력해 주십시오.')
             .then(q => ceQuill.set(q, r.answer))
             .catch(() => showToast('편집기를 불러오지 못했습니다.', 'warning'));
    }
    const s = document.getElementById('srxStatus'); if (s) s.value = r.status;

    srxPop.open('srxPopAnswer');
  }

  window.srxSaveAnswer = async function () {
    if (!_sel) { showToast('서비스 요청을 먼저 선택해 주십시오.', 'warning'); return; }
    const answer = ceQuill.html(document.getElementById('srxAnswer')?.__quill);
    if (!answer) { ceAlert('답변 내용을 입력해 주십시오.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srxAnswerBtn');
    btn.disabled = true;
    try {
      const res = await fetch(`${BASE}/${_sel.id}/answer`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ answer, status: document.getElementById('srxStatus').value }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) { ceAlert(d.message || '저장하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      // 목록을 고치고 창은 열어 둔다 — 담긴 값이 그 자리에서 보여야 한다
      _rows = _rows.map(x => x.id === d.row.id ? d.row : x);
      grid.setData(_rows);
      답변창열기(d.row.id);
    } catch (e) {
      ceAlert('저장 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };

  /* 신규 등록 (2026-10-01 지시) — 띠의 「신규 등록」이 여는 팝오버에서 보낸다 */
  window.srxSubmit = async function () {
    const title   = document.getElementById('srxTitle').value.trim();
    const content = ceQuill.html(document.getElementById('srxContent')?.__quill);
    if (!title || !content) { ceAlert('제목과 내용을 모두 입력해 주십시오.', { tone: 'warning' }); return; }

    const btn = document.getElementById('srxSubmitBtn');
    btn.disabled = true;
    try {
      const res = await fetch(BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
          title, content,
          category:   document.getElementById('srxCategory').value,
          priority:   document.getElementById('srxPriority').value,
          page_label: document.getElementById('srxPageLabel').value.trim(),
        }),
      });
      const d = await res.json();
      if (!res.ok || !d.success) {
        ceAlert(d.message || Object.values(d.errors ?? {}).flat().join('\n') || '등록하지 못했습니다.', { tone: 'danger' });
        return;
      }
      showToast(d.message, 'success');

      /* 담긴 줄을 목록 맨 위에 세우고 적던 것을 비운다 — 창은 닫는다.
         이어서 또 올릴 일이 흔하지 않고, 열어 둔 채 비우면 담긴 것인지 헷갈린다. */
      _rows = [d.row, ...(_rows ?? [])];
      grid.setData(_rows);
      document.getElementById('srxTitle').value = '';
      document.getElementById('srxPageLabel').value = '';
      ceQuill.set(document.getElementById('srxContent')?.__quill, '');
      srxPop.close('srxPopNew');
    } catch (e) {
      ceAlert('등록 중 오류가 발생했습니다.', { tone: 'danger' });
    } finally { btn.disabled = false; }
  };

  window.srDeleteSelected = async function () {
    const c = grid.getCheckedRows();
    if (!c.length)    { showToast('삭제할 서비스 요청을 선택해 주십시오.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택해 주십시오.', 'warning'); return; }
    if (!await ceConfirm(`'${c[0].title}' 을 삭제하시겠습니까?`, { tone: 'danger', confirmText: '삭제' })) return;

    try {
      const res = await fetch(`${BASE}/${c[0].id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      });
      const d = await res.json();
      if (!d.success) { ceAlert(d.message || '삭제하지 못했습니다.', { tone: 'danger' }); return; }
      showToast(d.message, 'success');
      _rows = _rows.filter(x => x.id !== c[0].id);
      grid.setData(_rows);
      if (_sel && _sel.id === c[0].id) { _sel = null; srxPop.close('srxPopAnswer'); }
    } catch (e) { ceAlert('삭제 중 오류가 발생했습니다.', { tone: 'danger' }); }
  };
})();
</script>
@endpush
