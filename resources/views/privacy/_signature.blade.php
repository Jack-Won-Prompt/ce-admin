{{-- 공개 동의서의 서명판 — 카테터ㆍ장루 두 폼이 함께 쓴다 (2026-10-02 지시).

     「개인정보동의서에 서명내용 있으면 CE admin 서명확인완료(위임, 개인정보 모두)로
      보이게 / 처방 구매 안 하는 사람들은 저 링크에서만 서명동의 받아서 구매 진행」

     여태 이 동의서는 체크만 받았다. 처방을 끼지 않고 사는 사람은 이 링크 하나로
     끝나야 하므로, 그 자리에서 서명까지 받는다. 그 서명은 **위임 서명으로도**
     인정된다(2026-10-02 결정 · DelegationGate::공개동의서서명).

     ## 손가락으로 그린다

     환자는 거의 휴대폰으로 연다. 그래서 마우스가 아니라 **손가락**이 기준이다 —
     `touch-action:none` 으로 그리는 동안 화면이 따라 움직이지 않게 막고, 포인터
     이벤트 하나로 손가락ㆍ펜ㆍ마우스를 함께 받는다.

     ## 빈 판을 보내지 않는다

     아무것도 그리지 않고 넘기면 위임 서명이 빈 채로 인정될 수 있다. 제출 전에
     그렸는지 보고, 받는 쪽도 그림 크기로 한 번 더 가린다
     (PrivacyConsentWebhookController::서명다듬기). --}}

<div class="card" id="signCard">
  <h2>서명 <span class="req">*</span></h2>
  <p class="note" style="margin:0 0 10px;">
    아래 칸에 손가락이나 마우스로 서명해 주십시오.
    이 서명은 개인정보 수집ㆍ이용 동의와 요양비 위임 동의의 증빙으로 사용됩니다.
  </p>

  <div id="signWrap" style="position:relative;border:1.5px dashed var(--line,#cbd5e1);border-radius:11px;
       background:#fff;overflow:hidden;">
    <canvas id="signPad" style="display:block;width:100%;height:190px;touch-action:none;cursor:crosshair;"></canvas>
    <div id="signHint" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
         pointer-events:none;color:#94a3b8;font-size:15px;font-weight:600;">여기에 서명해 주십시오</div>
  </div>

  <div style="display:flex;gap:9px;margin-top:10px;">
    <button type="button" class="btn btn-line" style="padding:11px;font-size:14px;" onclick="signClear()">다시 서명</button>
  </div>

  <input type="hidden" name="signature" id="signData">
</div>

@push('scripts')
<script>
(function () {
  const pad  = document.getElementById('signPad');
  const hint = document.getElementById('signHint');
  const data = document.getElementById('signData');
  const form = document.getElementById('consentForm');
  if (!pad || !form) return;

  const ctx = pad.getContext('2d');
  let 그렸나 = false;
  let 긋는중 = false;

  /* 화면 밀도에 맞춰 실제 점 수를 잡는다 — 이 일을 안 하면 서명이 흐릿하게 뭉갠다.
     크기를 바꿀 때마다 다시 잡아야 하므로, 그려 둔 것을 옮겨 담고 되돌린다. */
  function 크기맞추기() {
    const 비율 = Math.min(3, window.devicePixelRatio || 1);
    const 네모 = pad.getBoundingClientRect();
    if (!네모.width) return;

    const 그려둔것 = 그렸나 ? pad.toDataURL('image/png') : null;

    pad.width  = Math.round(네모.width  * 비율);
    pad.height = Math.round(네모.height * 비율);
    ctx.setTransform(비율, 0, 0, 비율, 0, 0);

    ctx.lineWidth   = 2.4;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    ctx.strokeStyle = '#111827';

    if (그려둔것) {
      const 그림 = new Image();
      그림.onload = () => ctx.drawImage(그림, 0, 0, 네모.width, 네모.height);
      그림.src = 그려둔것;
    }
  }

  const 자리 = (e) => {
    const 네모 = pad.getBoundingClientRect();
    return { x: e.clientX - 네모.left, y: e.clientY - 네모.top };
  };

  pad.addEventListener('pointerdown', (e) => {
    긋는중 = true;
    그렸나 = true;
    hint.style.display = 'none';
    pad.setPointerCapture(e.pointerId);
    const p = 자리(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    /* 톡 눌렀다 떼기만 해도 점이 남아야 한다 — 선만 그리면 아무것도 안 보인다 */
    ctx.lineTo(p.x + 0.1, p.y + 0.1);
    ctx.stroke();
  });

  pad.addEventListener('pointermove', (e) => {
    if (!긋는중) return;
    const p = 자리(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  });

  ['pointerup', 'pointercancel', 'pointerleave'].forEach((이름) =>
    pad.addEventListener(이름, () => { 긋는중 = false; }));

  window.signClear = function () {
    ctx.clearRect(0, 0, pad.width, pad.height);
    그렸나 = false;
    data.value = '';
    hint.style.display = 'flex';
  };

  /* 제출할 때 그림을 글로 바꿔 숨은 칸에 담는다. 그리지 않았으면 보내지 않는다 —
     빈 서명이 위임 증빙으로 인정되는 일을 앞에서 막는다. */
  form.addEventListener('submit', function (e) {
    if (!그렸나) {
      e.preventDefault();
      alert('서명을 입력해 주십시오.');
      document.getElementById('signCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }
    data.value = pad.toDataURL('image/png');
  });

  크기맞추기();
  window.addEventListener('resize', 크기맞추기);
  window.addEventListener('orientationchange', () => setTimeout(크기맞추기, 250));
})();
</script>
@endpush
