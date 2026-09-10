{{-- 신분증만 받는 공개 화면.

     위임동의 링크는 서명ㆍ개인정보 동의ㆍ신분증을 한자리에서 받는다. 그런데 신분증만
     빠진 채로 끝나는 건이 있다 — 그 자리에 신분증이 없거나 사진이 흐리거나.
     서명을 다시 받자고 위임동의를 새로 보낼 수는 없다(받아 둔 서명이 무효가 된다).
     그래서 이 화면은 사진 한두 장만 청한다(2026-09-09 지시).

     서명판도 개인정보 동의도 두지 않는다. 청한 적 없는 것을 화면에 세우면
     환자는 「또 처음부터 다 해야 하나」로 읽는다. --}}
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>신분증 제출</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
      background: #f0f4ff; min-height: 100dvh;
      display: flex; flex-direction: column; align-items: center;
      padding: 20px 16px 40px;
    }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(40,121,139,.10);
            width: 100%; max-width: 480px; overflow: hidden; }
    .card-header { background: linear-gradient(135deg, #28798B, #0B5C6E); color: #fff;
                   padding: 24px 20px 20px; text-align: center; }
    .card-header .logo { font-size: 12px; font-weight: 600; opacity: .75;
                         letter-spacing: .5px; margin-bottom: 8px; }
    .card-header h1 { font-size: 20px; font-weight: 800; letter-spacing: -.3px; }
    .card-header p  { font-size: 13px; opacity: .85; margin-top: 6px; line-height: 1.5; }

    .sender-bar { display: flex; align-items: center; justify-content: center; gap: 8px;
                  padding: 10px 16px; background: #F4F7F8; border-bottom: 1px solid #E3EAEC;
                  font-size: 12.5px; color: #4A5B60; line-height: 1.5; }
    .sender-bar b { color: #0B5C6E; font-weight: 700; }
    .sender-bar a { color: #0B5C6E; font-weight: 700; text-decoration: none; white-space: nowrap; }

    .card-body { padding: 20px; }

    /* 누구의 것인지 — 환자는 여러 사람의 일을 대신 보기도 한다 */
    .who { background: #F4F7F8; border: 1px solid #E3EAEC; border-radius: 10px;
           padding: 12px 14px; font-size: 13px; color: #37474F; line-height: 1.7;
           margin-bottom: 18px; }
    .who b { color: #0B5C6E; }

    .sec { margin-bottom: 22px; }
    .sec-label { display: block; font-size: 14px; font-weight: 700; color: #263238;
                 margin-bottom: 8px; }
    .sec-help  { font-size: 12px; font-weight: 400; color: #6b7280; line-height: 1.7;
                 margin-top: 4px; }

    /* 손가락으로 누르기 쉬운 크기로 */
    .upload { display: flex; flex-direction: column; align-items: center; justify-content: center;
              gap: 8px; width: 100%; min-height: 132px; padding: 16px;
              border: 2px dashed #B0BEC5; border-radius: 12px; background: #FAFCFD;
              color: #607D8B; font-size: 13px; cursor: pointer; text-align: center; }
    .upload.has-file { border-style: solid; border-color: #28798B; background: #fff; }
    .upload img { max-width: 100%; max-height: 220px; border-radius: 8px; }
    .file-name { display: none; font-size: 12px; color: #6b7280; margin-top: 6px; text-align: center; }

    /* 무엇이 남았는지 그대로 적는다 — 잠긴 단추는 아무 말도 하지 않는다 */
    .why { background: #FFF8E1; border: 1px solid #FFE082; border-radius: 10px;
           padding: 12px 14px; font-size: 12.5px; color: #8D6E00; line-height: 1.7;
           margin-bottom: 14px; }

    .btn-row { display: flex; gap: 10px; }
    .btn { flex: 1; height: 52px; border: 0; border-radius: 12px; font-size: 15px;
           font-weight: 700; cursor: pointer; }
    .btn-submit { background: #28798B; color: #fff; }
    .btn-submit:disabled { background: #CFD8DC; color: #90A4AE; cursor: not-allowed; }

    .result-screen { padding: 40px 24px; text-align: center; }
    .result-screen .icon { font-size: 46px; margin-bottom: 14px; }
    .result-screen h2 { font-size: 19px; font-weight: 800; color: #263238; margin-bottom: 8px; }
    .result-screen p  { font-size: 13.5px; color: #607D8B; line-height: 1.7; }
  </style>
</head>
<body>

<div class="card" id="mainCard">
  <div class="card-header">
    <div class="logo">CE ADMIN</div>
    <h1>신분증 제출</h1>
    <p>건강보험 등록에 필요한 신분증을 올려주세요.</p>
  </div>

  {{-- 누가 보냈는지 밝힌다. 모르는 번호에서 온 링크는 열지 않는 것이 옳다.

       회사 이름만 적는다 (2026-09-10 확인요청 12쪽). 담당자 이름을 함께 적었더니
       환자에게는 모르는 사람 이름이 하나 더 붙는 꼴이었고, 담당이 바뀌면 지난 링크에
       남의 이름이 남았다. 서명 화면도 같은 말로 세워 두었다. --}}
  <div class="sender-bar">
    <span>
      <b>콜로플라스트 코리아</b> 님이 보냈습니다.
    </span>
    @if($_tel = config('popbill.company.tel'))
      <a href="tel:{{ preg_replace('/[^0-9]/', '', $_tel) }}">{{ $_tel }}</a>
    @endif
  </div>

  <div class="card-body">
    <div class="who">
      <b>{{ $consent->patient_name }}</b> 님의 건강보험 등록 서류입니다.<br>
      주민등록증ㆍ운전면허증ㆍ여권 등 사진이 있는 신분증을 올려주세요.
    </div>

    {{-- 본인 신분증 --}}
    <div class="sec">
      <label class="sec-label">
        본인 신분증
        <div class="sec-help">사진을 찍거나 파일을 고르세요. (JPGㆍPNGㆍHEIC, 최대 10MB)</div>
      </label>
      <label class="upload" id="pDrop">
        <input type="file" id="pFile" accept="image/jpeg,image/png,image/heic,image/heif"
               capture="environment" style="display:none;" onchange="pick(this,'p')" />
        <div id="pEmpty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:26px;height:26px;">
            <path d="M3 7a2 2 0 012-2h3l1.5-2h5L19 5h3a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            <circle cx="12" cy="12.5" r="3.5"/>
          </svg>
          <span>신분증 사진 올리기</span>
        </div>
        <img id="pPreview" style="display:none;" alt="" />
      </label>
      <div class="file-name" id="pName"></div>
    </div>

    {{-- 보호자 신분증 — 미성년 건에만 청한다. 성인 건에 이 칸을 세우면
         「보호자가 없는데 무엇을 올리라는 것인가」를 되묻는 전화가 온다. --}}
    @if($consent->is_minor)
    <div class="sec">
      <label class="sec-label">
        법정대리인 또는 가족 신분증
        <div class="sec-help">
          {{ $consent->patient_name }} 님이 만 {{ (int) config('delegation.minor_age', 19) }}세 미만이라
          보호자 신분증이 함께 필요합니다.
        </div>
      </label>
      <label class="upload" id="gDrop">
        <input type="file" id="gFile" accept="image/jpeg,image/png,image/heic,image/heif"
               capture="environment" style="display:none;" onchange="pick(this,'g')" />
        <div id="gEmpty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:26px;height:26px;">
            <path d="M3 7a2 2 0 012-2h3l1.5-2h5L19 5h3a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            <circle cx="12" cy="12.5" r="3.5"/>
          </svg>
          <span>신분증 사진 올리기</span>
        </div>
        <img id="gPreview" style="display:none;" alt="" />
      </label>
      <div class="file-name" id="gName"></div>
    </div>
    @endif

    <div class="why" id="why" style="display:none;"></div>

    <div class="btn-row">
      <button class="btn btn-submit" type="button" id="btnSubmit" onclick="submitIdCard()" disabled>제출</button>
    </div>
  </div>
</div>

{{-- 결과 화면 (동적 교체) --}}
<div class="card" id="resultCard" style="display:none;max-width:480px;">
  <div class="result-screen">
    <div class="icon" id="resultIcon"></div>
    <h2 id="resultTitle"></h2>
    <p id="resultMsg"></p>
  </div>
</div>

<script>
const TOKEN    = @json($consent->token);
const IS_MINOR = @json((bool) $consent->is_minor);
const 그림     = { p: null, g: null };

/* 파일을 그대로 올리지 않고 브라우저에서 줄여 보낸다.
   요즘 휴대폰 사진은 한 장에 5MB 를 넘어 그대로 보내면 자주 실패한다. */
function pick(input, 자리) {
  const file = input.files?.[0];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) {
    ceAlert('파일이 너무 큽니다. 10MB 이하로 올려주세요.', { tone: 'warning' });
    input.value = ''; return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    const img = new Image();
    img.onload = () => {
      const MAX = 1600;
      const scale = Math.min(1, MAX / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width  = Math.round(img.width  * scale);
      c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      그림[자리] = c.toDataURL('image/jpeg', 0.82);

      const prev = document.getElementById(자리 + 'Preview');
      prev.src = 그림[자리];
      prev.style.display = '';
      document.getElementById(자리 + 'Empty').style.display = 'none';
      document.getElementById(자리 + 'Drop').classList.add('has-file');
      const nm = document.getElementById(자리 + 'Name');
      nm.textContent = file.name + ' — 다시 누르면 바꿀 수 있습니다';
      nm.style.display = '';
      refresh();
    };
    img.onerror = () => {
      ceAlert('이미지를 읽지 못했습니다. 다른 파일로 시도해주세요.', { tone: 'warning' });
      input.value = '';
    };
    img.src = reader.result;
  };
  reader.readAsDataURL(file);
}

/* 무엇이 남았는지 적는다 — 잠긴 단추는 아무 말도 하지 않는다 */
function refresh() {
  const 남은 = [];
  if (!그림.p)             남은.push('본인 신분증');
  if (IS_MINOR && !그림.g) 남은.push('법정대리인 또는 가족 신분증');

  const why = document.getElementById('why');
  /* 한 장이라도 있으면 보낼 수 있게 둔다. 둘 다 받는 것이 옳지만, 한 장만 있는
     사람을 아예 못 보내게 하면 그 한 장도 못 받는다 — 무엇이 빠졌는지만 적는다. */
  const 보낼수있다 = !!(그림.p || 그림.g);

  if (남은.length) {
    why.style.display = '';
    why.innerHTML = 보낼수있다
      ? '아직 올리지 않은 것: <b>' + 남은.join('</b>, <b>') + '</b><br>'
        + '지금 보내셔도 되지만, 담당자가 다시 연락드릴 수 있습니다.'
      : '올려주셔야 하는 것: <b>' + 남은.join('</b>, <b>') + '</b>';
  } else {
    why.style.display = 'none';
  }

  document.getElementById('btnSubmit').disabled = !보낼수있다;
}

async function submitIdCard() {
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.textContent = '제출 중…';

  try {
    const res = await fetch('/consent/' + TOKEN, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
      },
      body: JSON.stringify({ patient_id: 그림.p, guardian_id: 그림.g }),
    });
    const d = await res.json();

    if (!d.success) {
      ceAlert(d.message || '제출하지 못했습니다.', { tone: 'danger' });
      btn.disabled = false; btn.textContent = '제출';
      return;
    }

    document.getElementById('mainCard').style.display = 'none';
    const card = document.getElementById('resultCard');
    card.style.display = '';
    document.getElementById('resultIcon').textContent  = '✅';
    document.getElementById('resultTitle').textContent = '제출되었습니다';
    document.getElementById('resultMsg').textContent   =
      '신분증을 받았습니다. 확인 후 담당자가 안내드리겠습니다.';
  } catch (e) {
    ceAlert('제출하지 못했습니다. 잠시 후 다시 시도해 주세요.', { tone: 'danger' });
    btn.disabled = false; btn.textContent = '제출';
  }
}

refresh();
</script>

@include('partials.dialog')
</body>
</html>
