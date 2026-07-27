@extends('layouts.app')

@section('title', 'OCR 설정')
@section('page-title', '처방전 OCR 설정')
@section('breadcrumb', '홈 / 설정 / OCR 설정')

@push('styles')
<style>
  .ocr-form { max-width:720px; }
  .ocr-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
  .ocr-card h3 { margin:0 0 16px; font-size:14px; font-weight:800; color:var(--primary);
    padding-bottom:10px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:7px; }
  .ocr-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:11px 14px; font-size:12.5px; color:var(--text-secondary); margin-bottom:16px; line-height:1.6; }
  .ocr-warn { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px;
    padding:11px 14px; font-size:12.5px; margin-bottom:16px; line-height:1.6; }
  .status-ok { background:#eafaf1; border:1px solid #a3e4c1; color:#15803d; border-radius:8px;
    padding:11px 14px; font-size:13.5px; margin-bottom:16px; font-weight:600; }
  .opt { display:flex; gap:12px; align-items:flex-start; border:1.5px solid var(--border); border-radius:10px;
    padding:14px 16px; margin-bottom:12px; cursor:pointer; transition:border-color .15s, background .15s; }
  .opt:hover { border-color:var(--primary); }
  .opt input { margin-top:3px; width:18px; height:18px; flex-shrink:0; }
  .opt.sel { border-color:var(--primary); background:var(--primary-light); }
  .opt .t { font-size:14px; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
  .opt .d { font-size:12.5px; color:var(--text-secondary); margin-top:4px; line-height:1.6; }
  .badge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; }
  .badge-on  { background:#eafaf1; color:#15803d; }
  .badge-off { background:#fdecea; color:#c0392b; }
  .badge-def { background:var(--primary); color:#fff; }
  .ocr-actions { margin-top:18px; display:flex; gap:10px; }
  .btn-save { background:var(--primary); color:#fff; border:none; border-radius:8px; padding:11px 22px;
    font-size:14px; font-weight:700; cursor:pointer; }
</style>
@endpush

@section('content')
<div class="ocr-form">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="ocr-warn">입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}</div>
  @endif

  <div class="ocr-note">
    <i class="bx bx-info-circle"></i> 처방전 이미지의 문자 인식(OCR)에 사용할 공급자를 선택합니다.
    설정은 <b>업로드·재분석 전체</b>에 즉시 적용됩니다.
  </div>

  @if(! $textractEnabled)
    <div class="ocr-warn">
      <i class="bx bx-error-circle"></i> <b>AWS Textract 자격증명이 아직 설정되지 않았습니다.</b>
      Textract 를 선택해도 자격증명이 없으면 실제로는 AI OCR 로 자동 폴백되어 처리됩니다.
      서버 <code>.env</code> 에 <code>AWS_ACCESS_KEY_ID</code> / <code>AWS_SECRET_ACCESS_KEY</code> /
      <code>AWS_DEFAULT_REGION</code>(현재 <code>{{ $textractRegion }}</code>) 를 채운 뒤 활성화됩니다.
    </div>
  @endif

  <form method="POST" action="{{ route('ocr-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="ocr-card">
      <h3><i class="bx bx-scan"></i> OCR 공급자</h3>

      @php $cur = old('provider', $setting->provider); @endphp

      <label class="opt {{ $cur === 'textract' ? 'sel' : '' }}" id="opt-textract">
        <input type="radio" name="provider" value="textract" {{ $cur === 'textract' ? 'checked' : '' }}>
        <div>
          <div class="t">
            AWS Textract
            <span class="badge badge-def">기본값</span>
            <span class="badge {{ $textractEnabled ? 'badge-on' : 'badge-off' }}">
              {{ $textractEnabled ? '자격증명 설정됨' : '미설정 → AI 폴백' }}
            </span>
          </div>
          <div class="d">
            AWS Textract 로 문자를 추출합니다. 숫자(주민번호·처방일·전화)·레이아웃 인식에 강하지만
            <b>한글(환자명·병원명·상병명)은 미지원</b>이라 해당 필드는 비거나 부정확할 수 있어
            검수 화면에서 보정합니다.
          </div>
        </div>
      </label>

      <label class="opt {{ $cur === 'ai' ? 'sel' : '' }}" id="opt-ai">
        <input type="radio" name="provider" value="ai" {{ $cur === 'ai' ? 'checked' : '' }}>
        <div>
          <div class="t">AI OCR (Claude / OpenAI)</div>
          <div class="d">
            AI Vision 모델로 인식합니다. 한글 필드까지 구조화 추출이 가능해 정확도가 높지만
            호출 비용이 발생합니다. (Claude 우선 → OpenAI 폴백)
          </div>
        </div>
      </label>

      <div class="ocr-actions">
        <button type="submit" class="btn-save"><i class="bx bx-save"></i> 저장</button>
      </div>
    </div>
  </form>
</div>

<script>
  document.querySelectorAll('.opt input[name=provider]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.opt').forEach(function (o) { o.classList.remove('sel'); });
      this.closest('.opt').classList.add('sel');
    });
  });
</script>
@endsection
