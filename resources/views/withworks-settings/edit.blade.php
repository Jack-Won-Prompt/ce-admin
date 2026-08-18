@extends('layouts.app')

@section('title', '위드웍스 연동 설정')
@section('page-title', '위드웍스 연동 설정')
@section('breadcrumb', '홈 - 설정 - 위드웍스 연동')

@section('content')
{{-- 서비스 연동 설정 화면의 탭과 같은 조각을 쓴다 — 두 곳의 모양이 갈리지 않는다 --}}
@include('withworks-settings._form')
@endsection
