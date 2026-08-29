@extends('layouts.app')

@section('title', '문의 접수')
@section('page-title', '문의 접수')
@section('breadcrumb', '홈 - 지원 - 환자 문의 - 접수')

@push('styles')
<style>
  /* 내용이 짧아도 아래가 회색으로 남지 않게 —
     껍데기(.iqc-wrap)가 흰 판이 되어 바닥까지 내려오고, 그 안의 카드는 fill-rest 로 늘어난다.
     단추줄은 그 흰 판의 아래쪽(카드 푸터 자리)에 앉는다. */
  .iqc-wrap { background: var(--bg-card); border-radius: var(--radius-lg); }
  /* 단추가 카드 안 입력칸(안여백 16)과 오른쪽 끝을 맞춘다. */
  .iqc-actions { padding: 0 16px 16px; }
</style>
@endpush

@section('content')
<div class="iqc-wrap fill-rest fill-col" style="max-width:800px;">
  <form method="POST" action="{{ route('inquiries.store') }}" enctype="multipart/form-data" class="fill-rest fill-col">
    @csrf
    <div class="card fill-rest fill-col">
      <div class="card-header">
        <i class="bx bx-headphone" style="color:var(--primary);"></i>
        <span class="card-header-title">환자 문의 접수</span>
        <span class="card-header-sub">전화·유선으로 받은 문의를 대신 적습니다.</span>
      </div>
      <div class="card-body fill-rest fill-col" style="display:flex;flex-direction:column;gap:16px;">

        {{-- 문의자는 환자다. 이름만 적어 넣게 두면 동명이인을 가릴 수 없어, 찾아서 고른다.
             고객이 아직 환자로 등록되지 않았으면 비워 두고 이름만 적어도 된다. --}}
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">환자</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="patient_id" id="iqcPatientId" value="{{ old('patient_id') }}">
            <input type="text" id="iqcPatientName" class="form-control" readonly
                   style="max-width:220px;background:var(--gray-50);" placeholder="조회해서 고르십시오">
            <button type="button" class="ds-btn" id="iqcPickBtn" onclick="iqcPickPatient(this)">환자 조회</button>
            <button type="button" class="ds-btn" onclick="iqcClearPatient()">지우기</button>
          </div>
          @error('patient_id')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;">
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
            <label class="form-label">분류 <span>*</span></label>
            <select name="category" class="form-control form-select" required>
              <option value="">분류를 선택하세요</option>
              @foreach(\App\Models\Inquiry::CATEGORIES as $k => $label)
                <option value="{{ $k }}" @selected(old('category') === $k)>{{ $label }}</option>
              @endforeach
            </select>
            @error('category')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
          </div>

          {{-- 회신방식은 접수하며 고른다. 나중에 물으면 다시 전화해야 한다. --}}
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
            <label class="form-label">회신방식 <span>*</span></label>
            <select name="reply_channel" class="form-control form-select" required>
              @foreach(\App\Models\Inquiry::CHANNELS as $k => $label)
                <option value="{{ $k }}" @selected(old('reply_channel', 'phone') === $k)>{{ $label }}</option>
              @endforeach
            </select>
            @error('reply_channel')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
          </div>

          <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
            <label class="form-label">연락처</label>
            <input type="text" name="contact" id="iqcContact" class="form-control" maxlength="30"
                   value="{{ old('contact') }}" placeholder="환자를 고르면 채워집니다">
            @error('contact')<div style="color:var(--danger);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
          </div>
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
          여기 적은 「내용」은 환자가 말한 것을 그대로 옮기는 자리입니다. 답변과 조치사항은 목록에서 행을 더블클릭해 적습니다.
        </div>

      </div>
    </div>

    <div class="iqc-actions" style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
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
@push('scripts')
<script>
(function () {
  /* 환자 조회 — 누른 칸 옆에 붙는 팝오버로 연다(교환·반품 접수와 같은 방식).
     고르면 이름과 연락처가 함께 채워진다. */
  const PATIENT_URL = @json(route('inquiries.patientSearch'));
  const modal = new GridModal();
  let rows = {};

  window.iqcPickPatient = function (btn) {
    modal.open({
      title: '환자 조회', width: 420, height: 320, mode: 'popover', anchor: btn,
      onSearch: async (q) => {
        const res = await fetch(PATIENT_URL + '?q=' + encodeURIComponent(q ?? ''),
                                { headers: { Accept: 'application/json' } });
        const data = await res.json();
        rows = {};
        (data.rows ?? []).forEach(r => { rows[r.id] = r; });
        return (data.rows ?? []).map(r => ({
          value: r.id,
          label: r.name + (r.birth ? ' · ' + r.birth : ''),
          sub:   r.phone || '연락처 없음',
        }));
      },
      onConfirm: (v) => {
        const r = rows[v];
        if (!r) return;
        document.getElementById('iqcPatientId').value   = r.id;
        document.getElementById('iqcPatientName').value = r.name + (r.birth ? ' · ' + r.birth : '');
        if (!document.getElementById('iqcContact').value) {
          document.getElementById('iqcContact').value = r.phone || '';
        }
      },
    });
  };

  window.iqcClearPatient = function () {
    document.getElementById('iqcPatientId').value   = '';
    document.getElementById('iqcPatientName').value = '';
  };
})();
</script>
@endpush


