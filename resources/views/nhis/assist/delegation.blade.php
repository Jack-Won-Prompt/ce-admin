{{-- 공단 요양비청구위임내역등록(2225) 입력 지원 --}}
@extends('nhis.assist._base')

@section('windowTitle', '공단 위임 등록 지원 — ' . ($prescription->patient?->name ?? $prescription->patient_name_ocr))
@section('title', '공단 위임 등록 입력 지원')
@section('subtitle')
  요양비 &gt; 요양비청구 &gt; 2225 요양비청구위임내역등록 ·
  {{ $prescription->patient?->name ?? $prescription->patient_name_ocr }} · {{ $prescription->rx_number }}
@endsection
@section('steps')
  입력 순서 — <b>① 내용입력</b> → <b>② 저장</b> → <b>③ 파일첨부</b>(위임장 · 위임인 신분증) → <b>④ 저장 및 최종제출</b>
@endsection

@section('body')

  @unless($consent)
    <div class="banner">
      <b>서명된 위임동의가 없습니다.</b> 위임자 정보를 채울 수 없어 일부 항목이 비어 있습니다.
      위임장 서명을 먼저 받으십시오.
    </div>
  @endunless

  @foreach($groups as $groupName => $rows)
    @include('nhis.assist._group', ['name' => $groupName, 'rows' => $rows])
  @endforeach

@endsection
