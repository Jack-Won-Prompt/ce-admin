@extends('legal.layout')
@section('title', 'CE Admin 계정 삭제 안내')
@section('effective', '시행일 ' . $effectiveDate)

@section('body')
<div class="card">
  <h2>계정을 지우려면</h2>
  <div class="callout">
    <strong>CE Admin은 회사가 계정을 발급하는 업무용 앱입니다.</strong>
    앱 안에서 스스로 계정을 지우는 단추는 두지 않았습니다 — 계정이 업무 자취(누가 무엇을 접수하고
    고쳤는지)와 이어져 있어, 본인 확인 없이 지우면 그 자취의 주인을 알 수 없게 되기 때문입니다.
    아래 방법으로 요청하시면 확인 후 지워 드립니다.
  </div>

  <h3>방법 1. 이메일로 요청</h3>
  <p>아래 주소로 보내 주십시오.</p>
  <table>
    <tr><th>받는 곳</th><td><a href="mailto:{{ $company['email'] }}?subject=CE%20Admin%20계정%20삭제%20요청">{{ $company['email'] }}</a></td></tr>
    <tr><th>제목</th><td>CE Admin 계정 삭제 요청</td></tr>
    <tr><th>적을 내용</th><td>계정 이메일 주소, 이름, 연락처</td></tr>
  </table>

  <h3>방법 2. 전화로 요청</h3>
  <p>{{ $company['tel'] }} 로 전화하여 「CE Admin 계정 삭제」를 말씀해 주십시오.</p>

  <h3>방법 3. 앱에서 요청</h3>
  <p>앱의 <strong>설정 › 문의하기</strong>에서 계정 삭제를 요청하실 수 있습니다.</p>
</div>

<div class="card">
  <h2>처리 기간</h2>
  <p>요청을 받은 날부터 <strong>영업일 기준 10일 안에</strong> 처리하고, 알려 주신 연락처로 결과를 알려 드립니다.
     본인 확인이 필요한 경우 회사가 먼저 연락드릴 수 있습니다.</p>
</div>

<div class="card">
  <h2>무엇이 지워지고, 무엇이 남습니까</h2>

  <h3>바로 지우는 것</h3>
  <ul>
    <li>계정 정보 — 이메일, 비밀번호, 이름, 휴대전화번호</li>
    <li>로그인 상태와 발급된 접속 토큰 (앱에서 즉시 로그아웃됩니다)</li>
    <li>푸시 알림용 기기 토큰</li>
    <li>앱에서 주고받은 상담·문의 내용 중 본인이 쓴 것</li>
  </ul>

  <h3>지우지 않고 남기는 것</h3>
  <table>
    <tr>
      <th>개인정보 취급·접속 기록</th>
      <td>1년(고유식별정보를 다룬 기록은 2년)<br>
          <span class="muted">「개인정보의 안전성 확보조치 기준」이 보관을 정하고 있습니다.</span></td>
    </tr>
    <tr>
      <th>업무로 처리한 고객 자료</th>
      <td>관계 법령이 정한 기간<br>
          <span class="muted">처방 서류·청구·거래 자료는 <strong>고객(환자)의 정보</strong>이지 이용자의 정보가
          아닙니다. 담당자 계정을 지워도 그 자료는 남습니다. 다만 자료에 남은 담당자 이름은
          「탈퇴한 사용자」로 바뀝니다.</span></td>
    </tr>
  </table>

  <div class="callout">
    보관 기간이 정해진 자료는 그 기간이 지나면 되살릴 수 없는 방법으로 지웁니다.
    그때까지는 삭제 요청이 있어도 법령에 따라 보관합니다 — 그 까닭과 기간을 함께 알려 드립니다.
  </div>
</div>

<div class="card">
  <h2>되돌릴 수 있습니까</h2>
  <p><strong>지운 계정은 되살릴 수 없습니다.</strong> 다시 쓰시려면 회사 관리자에게 계정을 새로 발급받아야 하고,
     이 경우 예전 계정의 상담 내용은 이어지지 않습니다.</p>
</div>

<div class="card">
  <h2>고객(환자)이신 경우</h2>
  <p>이 앱의 계정을 가지고 계시지 않지만 회사가 보관 중인 <strong>본인의 개인정보를 지우고 싶으시다면</strong>,
     같은 연락처로 요청하실 수 있습니다. 자세한 내용은
     <a href="{{ route('legal.privacy') }}">개인정보처리방침</a>의 「정보주체의 권리」를 보십시오.</p>
  <table>
    <tr><th>전화</th><td>{{ $company['tel'] }}</td></tr>
    <tr><th>이메일</th><td><a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a></td></tr>
  </table>
</div>
@endsection
