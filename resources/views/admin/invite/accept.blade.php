<!DOCTYPE html>
<html lang="ko" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CE Admin — 초대 수락</title>
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root {
      --primary:       #7367F0;
      --primary-dark:  #5A52C5;
      --primary-light: #F5F3FF;
      --danger:        #EF4444;
      --success:       #22C55E;
      --text-primary:  #1E1B4B;
      --text-muted:    #6B7280;
      --border:        #E5E7EB;
      --bg:            #F5F3FF;
    }
    html, body { height:100%; }
    body {
      font-family:'Pretendard Variable','Pretendard',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Noto Sans KR',sans-serif;
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      background:var(--bg); -webkit-font-smoothing:antialiased;
    }
    .card {
      background:#fff; border-radius:16px; width:100%; max-width:440px;
      box-shadow:0 8px 40px rgba(115,103,240,.12); padding:40px 36px; margin:20px;
    }
    .logo { text-align:center; margin-bottom:28px; }
    .logo-icon { width:56px; height:56px; border-radius:14px; background:var(--primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px; }
    .logo-icon i { font-size:28px; color:#fff; }
    .logo h1 { font-size:20px; font-weight:800; color:var(--text-primary); margin:0; }
    .logo p  { font-size:13px; color:var(--text-muted); margin:4px 0 0; }
    .invite-info { background:var(--primary-light); border-radius:10px; padding:14px 16px; margin-bottom:24px; }
    .invite-info p { font-size:13px; color:var(--text-primary); margin:0; }
    .invite-info .email { font-weight:700; color:var(--primary); font-size:14px; }
    .invite-info .role-badge { display:inline-block; background:var(--primary); color:#fff; font-size:11px; font-weight:700; padding:2px 10px; border-radius:20px; margin-top:6px; }
    .form-group { margin-bottom:18px; }
    .form-label { display:block; font-size:13px; font-weight:600; color:var(--text-primary); margin-bottom:6px; }
    .form-control {
      width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:8px;
      font-size:14px; font-family:inherit; color:var(--text-primary); background:#fff;
      outline:none; transition:border-color .15s;
    }
    .form-control:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(115,103,240,.12); }
    .form-control.is-invalid { border-color:var(--danger); }
    .invalid-feedback { font-size:12px; color:var(--danger); margin-top:4px; }
    .pw-wrap { position:relative; }
    .pw-wrap .form-control { padding-right:40px; }
    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:18px; padding:0; line-height:1; }
    .btn-primary {
      width:100%; padding:13px; background:var(--primary); color:#fff; border:none;
      border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit;
      transition:background .15s; margin-top:4px;
    }
    .btn-primary:hover { background:var(--primary-dark); }
    .btn-primary:disabled { opacity:.6; cursor:not-allowed; }
    .error-box { background:#FEF2F2; color:var(--danger); padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; display:none; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon"><i class="bx bx-shield-quarter"></i></div>
    <h1>CE Admin</h1>
    <p>관리자 계정을 설정해주세요</p>
  </div>

  <div class="invite-info">
    <p>초대된 이메일</p>
    <p class="email">{{ $invitation->email }}</p>
    <span class="role-badge">{{ $invitation->role === 'admin' ? '관리자 (Admin)' : '매니저 (Manager)' }}</span>
  </div>

  @if($errors->any())
  <div class="error-box" style="display:block;">
    @foreach($errors->all() as $error)
      {{ $error }}<br>
    @endforeach
  </div>
  @endif

  <div class="error-box" id="errBox"></div>

  <form method="POST" action="{{ route('admin.invite.confirm', $invitation->token) }}">
    @csrf

    <div class="form-group">
      <label class="form-label">이름 <span style="color:var(--danger);">*</span></label>
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
             value="{{ old('name') }}" placeholder="홍길동" required maxlength="100" autofocus>
      @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label class="form-label">비밀번호 <span style="color:var(--danger);">*</span></label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pw" class="form-control @error('password') is-invalid @enderror"
               placeholder="8자 이상" required minlength="8">
        <button type="button" class="pw-toggle" onclick="togglePw('pw','eye1')">
          <i class="bx bx-show" id="eye1"></i>
        </button>
      </div>
      @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-group">
      <label class="form-label">비밀번호 확인 <span style="color:var(--danger);">*</span></label>
      <div class="pw-wrap">
        <input type="password" name="password_confirmation" id="pw2" class="form-control"
               placeholder="비밀번호를 다시 입력하세요" required minlength="8">
        <button type="button" class="pw-toggle" onclick="togglePw('pw2','eye2')">
          <i class="bx bx-show" id="eye2"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-primary" id="submitBtn">
      <i class="bx bx-check-circle"></i> 계정 활성화
    </button>
  </form>
</div>

<script>
function togglePw(inputId, eyeId) {
  const input = document.getElementById(inputId);
  const eye   = document.getElementById(eyeId);
  if (input.type === 'password') { input.type = 'text';     eye.className = 'bx bx-hide'; }
  else                           { input.type = 'password'; eye.className = 'bx bx-show'; }
}

document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 처리 중...';
});
</script>
</body>
</html>
