@extends('layouts.app')

@section('title', 'OCR 설정')
@section('page-title', '처방전 OCR 설정')
@section('breadcrumb', '홈 - 설정 - OCR 설정')

@push('styles')
<style>
  .ocr-form { max-width:720px; }
  .ocr-card { background:var(--gray-0); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; }
  /* 섹션 제목 = 14px/700 */
  .ocr-card h3 { margin:0 0 16px; font-size:14px; font-weight:700; line-height:22px; color:var(--primary);
    padding-bottom:12px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  .ocr-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; color:var(--text-secondary); margin-bottom:16px; line-height:18px; }
  /* 주의·오류는 alert 램프로만 표현한다(시안에 주황·초록이 없다) */
  .ocr-warn { background:var(--alert-50); border:1px solid var(--alert-100); color:var(--alert-500); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; margin-bottom:16px; line-height:18px; }
  .status-ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; margin-bottom:16px; }
  .opt { display:flex; gap:12px; align-items:flex-start; border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; margin-bottom:12px; cursor:pointer; transition:border-color .15s, background .15s; }
  .opt:hover { border-color:var(--primary); }
  .opt input { margin-top:2px; width:16px; height:16px; flex-shrink:0; }
  .opt.sel { border-color:var(--primary); background:var(--primary-light); }
  .opt .t { font-size:14px; font-weight:700; line-height:22px; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
  .opt .d { font-size:12px; font-weight:400; color:var(--text-secondary); margin-top:4px; line-height:18px; }
  /* 전역 .badge(h22 · radius 6 · 11px/500)를 그대로 쓴다 — 재정의하면 이 화면만 알약 모양으로 남는다 */
  .badge-on  { background:var(--primary-light); color:var(--primary); }
  .badge-off { background:var(--alert-50);      color:var(--alert-500); }
  .badge-def { background:var(--primary); color:var(--gray-0); }
  .ocr-actions { margin-top:16px; display:flex; gap:8px; }
  /* 전역 .btn 규격(h32 · pad 5/12 · r8 · 13px/500)에 맞춘다 */
  .btn-save { display:inline-flex; align-items:center; gap:6px;
    background:var(--primary); color:var(--gray-0); border:1px solid var(--primary); border-radius:8px;
    padding:5px 12px; font-size:13px; font-weight:500; line-height:20px; cursor:pointer; font-family:inherit; }
</style>
@endpush

@section('content')
<div class="ocr-form fill-rest fill-col">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="ocr-warn">입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}</div>
  @endif

  <div class="ocr-note">
    <i class="bx bx-info-circle"></i> 처방전 이미지의 문자 인식(OCR)은 <b>AWS Textract</b> 로 처리합니다.
    이 화면에서 현재 연동 상태를 확인할 수 있습니다.
  </div>

  @if(! $textractEnabled)
    <div class="ocr-warn">
      <i class="bx bx-error-circle"></i> <b>AWS Textract 자격증명이 설정되지 않아 OCR 이 동작하지 않습니다.</b>
      서버 <code>.env</code> 에 <code>AWS_ACCESS_KEY_ID</code> / <code>AWS_SECRET_ACCESS_KEY</code> /
      <code>AWS_DEFAULT_REGION</code>(현재 <code>{{ $textractRegion }}</code>) 를 채워 주세요.
      자격증명이 없으면 처방전 업로드 시 OCR 오류가 발생합니다.
    </div>
  @endif

  <div class="ocr-card fill-rest">
    <h3><i class="bx bx-scan"></i> OCR 공급자</h3>

    <div class="opt sel" style="cursor:default;">
      <i class="bx bx-scan" style="font-size:16px;color:var(--primary);flex-shrink:0;margin-top:3px;"></i>
      <div>
        <div class="t">
          AWS Textract
          <span class="badge {{ $textractEnabled ? 'badge-on' : 'badge-off' }}">
            {{ $textractEnabled ? '자격증명 설정됨' : '자격증명 미설정 — OCR 불가' }}
          </span>
        </div>
        <div class="d">
          처방전 이미지에서 문자를 추출합니다. 숫자(주민번호·처방일·전화)·레이아웃 인식에 강하지만
          <b>한글(이름·병원명·상병명)은 미지원</b>이라 해당 필드는 비거나 부정확할 수 있어
          <b>검수 화면에서 사람이 보정</b>합니다.
        </div>
        <div class="d" style="margin-top:8px;">
          리전 <code>{{ $textractRegion }}</code> ·
          지원 형식 <code>PNG</code> <code>JPEG</code>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
