{{-- 문의 등록 — 앱의 inquiry_create_screen (2026-09-25 지시).
     갈래 넷(구매ㆍ처방전ㆍ앱 이용ㆍ기타)ㆍ제목ㆍ내용ㆍ첨부 한 장.

     2026-09-25 1:1 정합성 검증으로 고친 것:
       · 제목은 255자까지 (120 이 아니다 — 앱은 maxLength 255)
       · 내용이 비어도 **첨부가 있으면 등록된다** (앱의 validator 와 서버 잣대가 같다)
       · 안내ㆍ오류ㆍ성공 문구를 앱과 같은 말로
       · 첨부는 갈래 시트(갤러리ㆍ카메라ㆍ첨부 제거)로 고른다
       · 등록하면 그 문의 상세로 간다 --}}
@extends('layouts.mobile')

@section('title', '문의 등록')
@section('back', true)

@section('head-actions')
  <button class="m-head-btn" style="width:auto; padding:0 12px; font-size:13.5px; font-weight:700;"
          id="icTop" onclick="icSave()">등록</button>
@endsection

@section('body')
  <div class="m-card">
    <div class="m-field">
      <label class="m-label">유형</label>
      <div class="m-chips" id="icCat" style="flex-wrap:wrap; overflow:visible;">
        <button class="m-chip on" data-c="purchase"     onclick="icPick(this)">구매</button>
        <button class="m-chip"    data-c="prescription" onclick="icPick(this)">처방전</button>
        <button class="m-chip"    data-c="app"          onclick="icPick(this)">앱 이용</button>
        <button class="m-chip"    data-c="other"        onclick="icPick(this)">기타</button>
      </div>
    </div>

    <div class="m-field">
      <label class="m-label" for="icTitle">제목</label>
      <input class="m-input" id="icTitle" placeholder="문의 제목을 입력해 주십시오" maxlength="255">
      <div class="m-err" id="icTitleErr"></div>
    </div>

    <div class="m-field">
      <label class="m-label" for="icBody">내용</label>
      <textarea class="m-textarea" id="icBody" rows="7" style="min-height:150px;"
                placeholder="문의 내용을 자세히 입력해 주십시오"></textarea>
      <div class="m-err" id="icBodyErr"></div>
    </div>

    <div class="m-field">
      <label class="m-label">파일 첨부</label>
      <button class="m-btn ghost" id="icBox" onclick="mSheetOpen('icSheet')"
              style="display:flex; align-items:center; gap:10px; justify-content:flex-start;
                     padding:16px 14px; text-align:left;">
        <i class="bx bx-paperclip" style="font-size:20px;"></i>
        <span id="icBoxTxt" style="flex:1;">파일을 첨부하려면 누르십시오</span>
      </button>
      <div id="icPrev" style="margin-top:10px;"></div>
      <input type="file" id="icCam" accept="image/*" capture="environment" hidden onchange="icFilePicked(this)">
      <input type="file" id="icPic" accept="image/*,application/pdf"      hidden onchange="icFilePicked(this)">
    </div>
  </div>

  <button class="m-btn" id="icBtn" onclick="icSave()"><i class="bx bx-check"></i> <span id="icBtnTxt">등록</span></button>
  <div style="height:24px;"></div>
@endsection

@push('scripts')
<div class="m-sheet" id="icSheet">
  <div class="m-grab"></div>
  <h2>파일 첨부</h2>
  <button class="m-btn ghost" style="justify-content:flex-start; gap:10px; margin-bottom:8px;"
          onclick="mSheetClose(); document.getElementById('icPic').click();">
    <i class="bx bx-image"></i> 갤러리에서 선택
  </button>
  <button class="m-btn ghost" style="justify-content:flex-start; gap:10px; margin-bottom:8px;"
          onclick="mSheetClose(); document.getElementById('icCam').click();">
    <i class="bx bx-camera"></i> 카메라로 촬영
  </button>
  <button class="m-btn ghost" id="icDrop" style="justify-content:flex-start; gap:10px; display:none;
          border-color:var(--m-danger); color:var(--m-danger);" onclick="icRemove()">
    <i class="bx bx-trash"></i> 첨부 제거
  </button>
</div>

<style>
  .m-err { color:var(--m-danger); font-size:12.5px; margin-top:6px; min-height:0; }
</style>
<script>
  const 목록길 = @json(route('m.inquiries'));
  let icCat = 'purchase', icAttach = null;

  function icPick(b) {
    document.querySelectorAll('#icCat .m-chip').forEach(x => x.classList.toggle('on', x === b));
    icCat = b.dataset.c;
  }

  function icFilePicked(input) {
    icAttach = (input.files || [])[0] || null;
    input.value = '';
    icGrid();
  }

  function icRemove() { icAttach = null; mSheetClose(); icGrid(); }

  function icGrid() {
    const 미리 = document.getElementById('icPrev');
    document.getElementById('icDrop').style.display = icAttach ? 'flex' : 'none';
    if (!icAttach) {
      document.getElementById('icBoxTxt').textContent = '파일을 첨부하려면 누르십시오';
      미리.innerHTML = '';
      return;
    }
    document.getElementById('icBoxTxt').textContent = '첨부 교체';
    const 크기 = (icAttach.size / 1024).toFixed(0);
    const 그림 = /^image\//.test(icAttach.type);
    미리.innerHTML = `
      <div style="display:flex; align-items:center; gap:10px; border:1px solid var(--m-line);
                  border-radius:12px; padding:10px;">
        ${그림 ? `<img src="${URL.createObjectURL(icAttach)}" style="width:52px; height:52px;
                      object-fit:cover; border-radius:9px;">`
               : `<div style="width:52px; height:52px; border-radius:9px; background:#F2F4F7;
                      display:flex; align-items:center; justify-content:center; font-size:24px;
                      color:var(--m-sub);"><i class="bx bxs-file-pdf"></i></div>`}
        <div style="flex:1; min-width:0;">
          <div style="font-size:13px; font-weight:600; overflow:hidden; text-overflow:ellipsis;
                      white-space:nowrap;">${mEsc(icAttach.name)}</div>
          <div style="font-size:11.5px; color:var(--m-mute); margin-top:2px;">${크기} KB</div>
        </div>
        <button style="background:none; border:0; color:var(--m-danger); cursor:pointer; font-size:20px;"
                onclick="icRemove()" aria-label="첨부 제거"><i class="bx bx-x"></i></button>
      </div>`;
  }

  function ic묶기(바쁨) {
    document.getElementById('icBtn').disabled = 바쁨;
    document.getElementById('icTop').disabled = 바쁨;
    document.getElementById('icBtnTxt').textContent = 바쁨 ? '등록 중…' : '등록';
  }

  async function icSave() {
    const 제목 = document.getElementById('icTitle').value.trim();
    const 내용 = document.getElementById('icBody').value.trim();

    /* 앱의 validator 와 같다 — 내용이 비어도 첨부가 있으면 넘어간다 */
    document.getElementById('icTitleErr').textContent = 제목 ? '' : '제목을 입력해 주십시오.';
    document.getElementById('icBodyErr').textContent =
      (!내용 && !icAttach) ? '내용을 입력하거나 파일을 첨부해 주십시오.' : '';
    if (!제목 || (!내용 && !icAttach)) return;

    ic묶기(true);

    const fd = new FormData();
    fd.append('title', 제목);
    fd.append('category', icCat);
    if (내용) fd.append('body', 내용);
    if (icAttach) fd.append('attachment', icAttach, icAttach.name);

    try {
      const d = await mApi('/inquiries', { method: 'POST', body: fd });
      mTell('문의가 등록되었습니다.', 'ok');
      const id = d.inquiry_id ?? d.id;
      setTimeout(() => location.assign(id ? '/m/inquiries/' + id : 목록길), 700);
    } catch (e) {
      mTell('등록하지 못했습니다: ' + e.message, 'bad');
      ic묶기(false);
    }
  }
</script>
@endpush
