<!DOCTYPE html>
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>CE Admin &mdash; 관리자 초대</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap');

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: #EEEDF8;
      font-family: 'Noto Sans KR', 'Apple SD Gothic Neo', -apple-system, 'Helvetica Neue', Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      color: #1A1A2E;
    }

    .shell {
      max-width: 600px;
      margin: 0 auto;
      padding: 48px 20px 60px;
    }

    /* ── Pre-header label ── */
    .pre-header {
      text-align: center;
      font-size: 11px;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      color: #7367F0;
      font-weight: 500;
      margin-bottom: 24px;
    }

    /* ── Card ── */
    .card {
      background: #ffffff;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 2px 40px rgba(115,103,240,.10), 0 1px 4px rgba(0,0,0,.04);
    }

    /* ── Hero ── */
    .hero {
      background: #1A1A2E;
      padding: 52px 56px 44px;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 220px; height: 220px;
      border-radius: 50%;
      background: rgba(115,103,240,.15);
    }
    .hero::after {
      content: '';
      position: absolute;
      bottom: -40px; left: 30px;
      width: 130px; height: 130px;
      border-radius: 50%;
      background: rgba(115,103,240,.08);
    }
    .hero-eyebrow {
      font-size: 10px;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: #7367F0;
      font-weight: 500;
      margin-bottom: 16px;
    }
    .hero h1 {
      font-size: 28px;
      font-weight: 700;
      color: #ffffff;
      line-height: 1.3;
      letter-spacing: -0.5px;
      margin-bottom: 12px;
    }
    .hero-sub {
      font-size: 14px;
      color: rgba(255,255,255,.5);
      font-weight: 300;
      line-height: 1.6;
    }

    /* ── Accent bar ── */
    .accent-bar {
      height: 3px;
      background: linear-gradient(90deg, #7367F0 0%, #A89AF5 50%, #7367F0 100%);
    }

    /* ── Body ── */
    .body {
      padding: 44px 56px;
    }

    .greeting {
      font-size: 15px;
      line-height: 1.75;
      color: #374151;
      margin-bottom: 32px;
    }
    .greeting strong {
      color: #1A1A2E;
      font-weight: 700;
    }

    /* ── Personal message ── */
    .personal-msg {
      position: relative;
      margin: 0 0 36px;
      padding: 22px 24px 22px 32px;
      background: #F7F6FF;
      border-left: 3px solid #7367F0;
      border-radius: 0 8px 8px 0;
    }
    .personal-msg-label {
      font-size: 10px;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #7367F0;
      font-weight: 600;
      margin-bottom: 10px;
    }
    .personal-msg p {
      font-size: 14px;
      color: #374151;
      line-height: 1.75;
      font-style: italic;
    }
    .personal-msg-from {
      margin-top: 12px;
      font-size: 12px;
      color: #9CA3AF;
    }

    /* ── Info table ── */
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 36px;
    }
    .info-table td {
      padding: 14px 0;
      border-bottom: 1px solid #F3F4F6;
      vertical-align: middle;
    }
    .info-table tr:last-child td {
      border-bottom: none;
    }
    .info-table .label {
      font-size: 11px;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: #9CA3AF;
      font-weight: 500;
      width: 110px;
    }
    .info-table .value {
      font-size: 14px;
      color: #1A1A2E;
      font-weight: 600;
    }
    .role-pill {
      display: inline-block;
      background: #EDE9FE;
      color: #7367F0;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 12px;
      border-radius: 20px;
      letter-spacing: .3px;
    }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #F3F4F6;
      margin: 0 0 36px;
    }

    /* ── CTA ── */
    .cta-wrap {
      text-align: center;
      margin-bottom: 32px;
    }
    .cta-btn {
      display: inline-block;
      background-color: #7367F0;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
      padding: 16px 52px;
      border-radius: 2px;
    }

    /* ── Expire notice ── */
    .expire {
      text-align: center;
      font-size: 12px;
      color: #9CA3AF;
      line-height: 1.7;
      margin-bottom: 0;
    }
    .expire strong {
      color: #6B7280;
    }

    /* ── Footer ── */
    .footer {
      padding: 28px 56px;
      background: #F9FAFB;
      border-top: 1px solid #F0F0F0;
    }
    .footer-brand {
      font-size: 12px;
      font-weight: 700;
      color: #7367F0;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 8px;
    }
    .footer-text {
      font-size: 11px;
      color: #9CA3AF;
      line-height: 1.7;
    }
  </style>
</head>
<body>
<div class="shell">

  <p class="pre-header">CE Admin &middot; 관리자 초대장</p>

  <div class="card">

    {{-- Hero --}}
    <div class="hero">
      <p class="hero-eyebrow">Invitation</p>
      <h1>관리자 시스템에<br>초대되었습니다</h1>
      <p class="hero-sub">{{ $inviter->name }}님이 CE Admin 관리자 시스템 참여를 요청했습니다.</p>
    </div>
    <div class="accent-bar"></div>

    {{-- Body --}}
    <div class="body">

      <p class="greeting">
        안녕하세요,<br>
        <strong>{{ $inviter->name }}</strong>님으로부터 CE Admin 관리자 시스템 초대를 받으셨습니다.<br>
        아래 초대 정보를 확인하신 후 버튼을 클릭해 계정을 설정해 주세요.
      </p>

      @if($personalMessage)
      <div class="personal-msg">
        <p class="personal-msg-label">{{ $inviter->name }}님의 메시지</p>
        <p>{{ $personalMessage }}</p>
        <p class="personal-msg-from">&mdash; {{ $inviter->name }}</p>
      </div>
      @endif

      {{-- CTA --}}
      <div class="cta-wrap">
        <a href="{{ route('admin.invite.accept', $invitation->token) }}"
           style="display:inline-block;background-color:#7367F0;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:16px 52px;border-radius:2px;font-family:'Noto Sans KR','Apple SD Gothic Neo',Arial,sans-serif;">
          초대 수락하기
        </a>
      </div>

      <p class="expire">
        이 링크는 <strong>72시간</strong> 후 만료됩니다.<br>
        본인이 요청하지 않은 초대라면 이 메일을 무시하셔도 됩니다.
      </p>

    </div>

    {{-- Footer --}}
    <div class="footer">
      <p class="footer-brand">CE Admin</p>
      <p class="footer-text">
        이 메일은 CE Admin 시스템에서 자동 발송된 공식 초대 메일입니다.<br>
        문의 사항은 시스템 관리자에게 연락해 주세요.
      </p>
    </div>

  </div>
</div>
</body>
</html>
