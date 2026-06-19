@extends('layouts.app')

@section('title', '문의 작성')
@section('page-title', '문의 작성')
@section('breadcrumb', '홈 / 문의하기 / 작성')

@section('content')
<div style="max-width:800px;">
  <form method="POST" action="{{ route('inquiries.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card">
      <div class="card-header">
        <i class="bx bx-headphone" style="color:var(--primary);"></i>
        <span class="card-header-title">새 문의 작성</span>
        <span class="card-header-sub">빠른 시일 내에 답변 드리겠습니다.</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">분류 <span>*</span></label>
          <select name="category" class="form-control form-select" required>
            <option value="">분류를 선택하세요</option>
            <option value="general"   {{ old('category') === 'general'   ? 'selected' : '' }}>일반</option>
            <option value="technical" {{ old('category') === 'technical' ? 'selected' : '' }}>기술</option>
            <option value="other"     {{ old('category') === 'other'     ? 'selected' : '' }}>기타</option>
          </select>
          @error('category')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">제목 <span>*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="문의 제목을 입력하세요" required>
          @error('title')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">내용 <span>*</span></label>
          <textarea name="content" class="form-control" rows="12"
                    placeholder="문의 내용을 자세히 입력해주세요.&#10;&#10;문제가 발생한 상황, 오류 메시지 등을 포함하면 더욱 정확한 답변을 드릴 수 있습니다." required>{{ old('content') }}</textarea>
          @error('content')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        {{-- 파일 첨부 --}}
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">파일 첨부 <span style="font-weight:400;color:var(--text-muted);">(선택 · 최대 10MB)</span></label>
          <label id="attachDropZone" style="
            display:flex;align-items:center;gap:12px;
            border:2px dashed var(--border);border-radius:var(--radius);
            padding:14px 16px;cursor:pointer;
            background:var(--bg);transition:border-color .2s,background .2s;
          ">
            <i class="bx bx-paperclip" style="font-size:18px;color:var(--text-muted);flex-shrink:0;"></i>
            <div style="flex:1;min-width:0;">
              <div id="attachLabel" style="font-size:13px;color:var(--text-secondary);">
                클릭하거나 파일을 여기에 끌어다 놓으세요
              </div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                이미지, PDF, Word, Excel 등 지원 (최대 10MB)
              </div>
            </div>
            <button type="button" id="attachClear" onclick="clearAttach(event)"
              style="display:none;background:none;border:none;cursor:pointer;color:var(--danger);font-size:18px;line-height:1;padding:2px 4px;"
              title="첨부파일 제거">×</button>
            <input type="file" name="attachment" id="attachInput"
              accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
              style="display:none;">
          </label>
          {{-- 이미지 미리보기 --}}
          <div id="attachPreview" style="display:none;margin-top:8px;">
            <img id="attachImg" src="" alt="미리보기"
              style="max-height:160px;max-width:100%;border-radius:var(--radius);border:1px solid var(--border);">
          </div>
          @error('attachment')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        {{-- 안내 문구 --}}
        <div style="padding:12px 14px;background:var(--info-light);border:1px solid var(--primary-accent);border-radius:var(--radius);font-size:12px;color:var(--primary);">
          <i class="bx bx-info-circle" style="margin-right:6px;"></i>
          문의는 영업일 기준 1~2일 내에 답변드립니다. 긴급 문의는 직접 연락 부탁드립니다.
        </div>

      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
      <a href="{{ route('inquiries.index') }}" class="btn btn-outline">취소</a>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-send"></i> 문의 등록
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const zone  = document.getElementById('attachDropZone');
  const input = document.getElementById('attachInput');
  const label = document.getElementById('attachLabel');
  const clear = document.getElementById('attachClear');
  const prev  = document.getElementById('attachPreview');
  const img   = document.getElementById('attachImg');

  // 클릭 → 파일 선택
  zone.addEventListener('click', () => input.click());

  // 파일 선택 시
  input.addEventListener('change', () => {
    if (input.files[0]) applyFile(input.files[0]);
  });

  // 드래그 앤 드롭
  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.style.borderColor = 'var(--primary)';
    zone.style.background  = 'var(--primary-light)';
  });
  zone.addEventListener('dragleave', () => {
    zone.style.borderColor = '';
    zone.style.background  = '';
  });
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor = '';
    zone.style.background  = '';
    const file = e.dataTransfer.files[0];
    if (!file) return;
    // DataTransfer → input에 적용
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    applyFile(file);
  });

  function applyFile(file) {
    label.textContent = file.name + ' (' + formatBytes(file.size) + ')';
    label.style.color = 'var(--text-primary)';
    label.style.fontWeight = '500';
    clear.style.display = '';
    zone.style.borderStyle  = 'solid';
    zone.style.borderColor  = 'var(--primary)';

    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = ev => { img.src = ev.target.result; prev.style.display = ''; };
      reader.readAsDataURL(file);
    } else {
      prev.style.display = 'none';
    }
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  window.clearAttach = function (e) {
    e.stopPropagation();
    input.value = '';
    label.textContent = '클릭하거나 파일을 여기에 끌어다 놓으세요';
    label.style.color = '';
    label.style.fontWeight = '';
    clear.style.display = 'none';
    zone.style.borderStyle  = '';
    zone.style.borderColor  = '';
    prev.style.display = 'none';
    img.src = '';
  };
})();
</script>
@endsection
