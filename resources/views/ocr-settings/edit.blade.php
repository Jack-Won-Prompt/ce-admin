@extends('layouts.app')

@section('title', 'OCR 설정')
@section('page-title', '처방전 OCR 설정')
@section('breadcrumb', '홈 / 설정 / OCR 설정')

@push('styles')
<style>
  .ocr-form { max-width:720px; }
  .ocr-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; }
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
  /* 전역 .badge(h22 · radius 6 · 11px/500)를 그대로 쓴다 — 재정의하면 이 화면만 알약 모양으로 남는다 */
  .badge-on  { background:var(--primary-light); color:var(--primary); }
  .badge-off { background:var(--alert-50);      color:var(--alert-500); }
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

  <div class="ocr-card">
    <h3><i class="bx bx-scan"></i> OCR 공급자</h3>

    <div class="opt sel" style="cursor:default;">
      <i class="bx bx-scan" style="font-size:20px;color:var(--primary);flex-shrink:0;margin-top:2px;"></i>
      <div>
        <div class="t">
          AWS Textract
          <span class="badge {{ $textractEnabled ? 'badge-on' : 'badge-off' }}">
            {{ $textractEnabled ? '자격증명 설정됨' : '자격증명 미설정 — OCR 불가' }}
          </span>
        </div>
        <div class="d">
          처방전 이미지에서 문자를 추출합니다. 숫자(주민번호·처방일·전화)·레이아웃 인식에 강하지만
          <b>한글(환자명·병원명·상병명)은 미지원</b>이라 해당 필드는 비거나 부정확할 수 있어
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
