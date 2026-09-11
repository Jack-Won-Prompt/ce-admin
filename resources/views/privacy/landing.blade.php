@extends('privacy.layout')
@section('title', '개인정보 수집·이용 동의서')
@section('card-class', 'pv-card--fill')

@section('content')
{{-- 시안 513:2572 — 제목 · 부제 / 갈래 둘 / 안내 한 줄, 왼쪽 위에 홈페이지 --}}
<style>
  .pl-list{display:flex;flex-direction:column;gap:12px;width:440px;max-width:100%;}
  .pl-row{height:50px;display:flex;align-items:center;gap:12px;padding:0 16px;border:1px solid var(--gray-200);border-radius:12px;background:var(--gray-0);
    font-size:16px;font-weight:500;line-height:27px;color:var(--gray-1000);transition:border-color .2s,background-color .2s,transform .2s;}
  .pl-row span{flex:1 1 0;min-width:0;}
  .pl-row:hover{border-color:var(--primary-500);background:var(--primary-50);}
  .pl-row:hover .pv-ico{transform:translateX(3px);}
  .pl-row .pv-ico{transition:transform .2s;}
  @media (max-width:640px){ .pl-list{width:100%;} }
</style>

<a class="pv-hbtn pv-hbtn--line pv-hbtn--home" href="{{ route('welcome') }}">
  <span class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/home-20.svg') }}')"></span>홈페이지
</a>

<div class="pv-head">
  <h1 class="pv-title">개인정보 <em>수집·이용 동의서</em></h1>
  <p class="pv-chip">해당하는 항목을 선택해 주세요 · 콜로플라스트 코리아</p>
</div>

<nav class="pl-list" aria-label="동의서 갈래">
  <a class="pl-row" href="{{ route('privacy.catheter') }}">
    <span>카테터(자가도뇨) 동의서 작성</span>
    <i class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/chevron-right-20.svg') }}')"></i>
  </a>
  <a class="pl-row" href="{{ route('privacy.stoma') }}">
    <span>장루 동의서 작성</span>
    <i class="pv-ico pv-ico--20" style="--ico:url('{{ asset('images/website/icons/chevron-right-20.svg') }}')"></i>
  </a>
</nav>

<p class="pv-note">※ 작성하신 정보는 콜로플라스트 코리아의 환자 지원 목적으로만 이용됩니다.</p>
@endsection
