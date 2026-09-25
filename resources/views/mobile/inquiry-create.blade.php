{{-- 문의 등록 — 앱의 inquiry_create_screen (2026-09-25 지시).
     갈래 넷(구매ㆍ처방전ㆍ앱 이용ㆍ기타)ㆍ제목ㆍ내용ㆍ첨부 한 장. --}}
@extends('layouts.mobile')

@section('title', '문의 등록')
@section('back', true)

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
      <input class="m-input" id="icTitle" placeholder="제목을 입력해 주십시오" maxlength="120">
    </div>

    <div class="m-field">
      <label class="m-label" for="icBody">내용</label>
      <textarea class="m-textarea" id="icBody" placeholder="내용을 입력해 주십시오"></textarea>
    </div>

    <div class="m-field">
      <label class="m-label">첨부 (선택)</label>
      <div style="display:flex; gap:8px;">
        <button class="m-btn ghost" onclick="document.getElementById('icCam').click()">
          <i class="bx bx-camera"></i> 카메라
        </button>
        <button class="m-btn ghost" onclick="document.getElementById('icPic').click()">
          <i class="bx bx-image"></i> 갤러리에서 선택
        </button>
      </div>
      <div id="icFile" style="font-size:12.5px; color:var(--m-sub); margin-top:8px;"></div>
      <input type="file" id="icCam" accept="image/*" capture="environment" hidden onchange="icFilePicked(this)">
      <input type="file" id="icPic" accept="image/*,application/pdf"      hidden onchange="icFilePicked(this)">
    </div>
  </div>

  <button class="m-btn" id="icBtn" onclick="icSave()"><i class="bx bx-check"></i> 등록</button>
  <div style="height:24px;"></div>
@endsection

@push('scripts')
<script>
  const 목록길 = @json(route('m.inquiries'));
  let icCat = 'purchase', icAttach = null;

  function icPick(b) {
    document.querySelectorAll('#icCat .m-chip').forEach(x => x.classList.toggle('on', x === b));
    icCat = b.dataset.c;
  }

  function icFilePicked(input) {
    icAttach = (input.files || [])[0] || null;
    const 통 = document.getElementById('icFile');
    통.innerHTML = icAttach
      ? `<i class="bx bx-paperclip"></i> ${mEsc(icAttach.name)}
         <button style="margin-left:8px; background:none; border:0; color:var(--m-danger); cursor:pointer;"
                 onclick="icAttach=null; this.parentElement.innerHTML='';">교체</button>`
      : '';
  }

  async function icSave() {
    const 제목 = document.getElementById('icTitle').value.trim();
    const 내용 = document.getElementById('icBody').value.trim();
    if (!제목 || !내용) { mTell('제목과 내용을 모두 입력해 주십시오.', 'warn'); return; }

    const 단추 = document.getElementById('icBtn');
    단추.disabled = true;

    const fd = new FormData();
    fd.append('title', 제목);
    fd.append('category', icCat);
    fd.append('body', 내용);
    if (icAttach) fd.append('attachment', icAttach, icAttach.name);

    try {
      const d = await mApi('/inquiries', { method: 'POST', body: fd });
      mTell('문의를 등록했습니다.', 'ok');
      const id = d.inquiry_id ?? d.id;
      setTimeout(() => location.assign(id ? '/m/inquiries/' + id : 목록길), 800);
    } catch (e) {
      mTell(e.message, 'bad');
      단추.disabled = false;
    }
  }
</script>
@endpush
