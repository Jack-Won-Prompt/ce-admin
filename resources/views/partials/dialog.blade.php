{{-- resources/views/partials/dialog.blade.php
     레이아웃(layouts.app)을 쓰지 않는 단독 공개 페이지용 커스텀 알림/확인 다이얼로그.
     관리자 레이아웃과 동일한 API 를 제공한다:
       await ceAlert('메시지', { tone: 'warning' })
       if (!await ceConfirm('메시지')) return;
     레이아웃이 이미 정의했다면(중복 include) 덮어쓰지 않는다. --}}
<style>
  .ce-dlg-ov {
    position: fixed; inset: 0; z-index: 99999;
    background: rgba(15, 23, 42, .45);
    display: flex; align-items: center; justify-content: center; padding: 20px;
    -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px);
  }
  /* 이 조각은 layouts.app 바깥(동의서 서명·개인정보 판형)에서도 실린다.
     그쪽에는 DS CSS 변수가 없으므로 색은 var() 가 아니라 DS 램프 값 그대로 적는다. */
  .ce-dlg {
    background: #FFFFFF; border-radius: 12px; width: 100%; max-width: 360px;
    box-shadow: 0 18px 48px rgba(0, 0, 0, .22); overflow: hidden;
    animation: ceDlgIn .16s ease;
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', sans-serif;
  }
  @keyframes ceDlgIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
  /* 섹션 제목 규격 = 14/700 */
  .ce-dlg-hd {
    display: flex; align-items: center; gap: 8px;
    padding: 16px 16px 0; font-size: 14px; font-weight: 700; line-height: 22px; color: #101317;
  }
  .ce-dlg-ic { font-size: 16px; line-height: 16px; }
  .ce-dlg-bd {
    padding: 8px 16px 16px; font-size: 13px; line-height: 21px; color: #474D54;
    max-height: 52vh; overflow-y: auto; word-break: break-word;
  }
  .ce-dlg-ft { display: flex; gap: 8px; padding: 0 16px 16px; }
  /* 모달 하단 버튼 규격 = h36 · r8 · 14/500 */
  .ce-dlg-btn {
    flex: 1; height: 36px; border-radius: 8px; border: none;
    font-size: 14px; font-weight: 500; line-height: 22px; cursor: pointer; font-family: inherit;
  }
  .ce-dlg-ok     { background: #28798B; color: #FFFFFF; }
  /* 시안의 상태색은 primary 램프와 alert 램프 둘뿐이다.
     둘의 배분은 layouts.app 의 같은 대화상자(TONE 표)를 그대로 따른다:
       danger  → alert  (btn-danger)  · 되돌릴 수 없는 실패·차단
       warning → primary(btn-primary) · 진행을 멈추는 주의
     같은 ceAlert 호출이 관리자 판형과 단독 판형에서 다른 색으로 보이면 안 된다.
     (consent/sign 한 화면에서 두 tone 이 함께 쓰인다 — 같은 색으로 모으면 구분이 사라진다) */
  .ce-dlg-ok.danger  { background: #D73D3F; }
  .ce-dlg-ok.warning { background: #28798B; }
  .ce-dlg-cancel { background: #F3F5F7; color: #474D54; }
</style>
<script>
(function () {
  if (window.ceAlert && window.ceConfirm) return;   // 레이아웃 버전이 이미 있으면 유지

  var TONE = {
    info:    { ic: 'ℹ️', cls: '' },
    default: { ic: '❓', cls: '' },
    warning: { ic: '⚠️', cls: 'warning' },
    danger:  { ic: '⛔', cls: 'danger' },
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function open(kind, msg, opts) {
    opts = opts || {};
    var tone = TONE[opts.tone] || TONE[kind === 'alert' ? 'info' : 'default'];
    var ov = document.createElement('div');
    ov.className = 'ce-dlg-ov';
    ov.innerHTML =
      '<div class="ce-dlg" role="dialog" aria-modal="true">'
      + '<div class="ce-dlg-hd"><span class="ce-dlg-ic">' + tone.ic + '</span>'
      + esc(opts.title || (kind === 'alert' ? '알림' : '확인')) + '</div>'
      + '<div class="ce-dlg-bd">' + esc(msg).replace(/\n/g, '<br>') + '</div>'
      + '<div class="ce-dlg-ft">'
      + (kind === 'confirm'
          ? '<button type="button" class="ce-dlg-btn ce-dlg-cancel" data-ce="cancel">' + esc(opts.cancelText || '취소') + '</button>'
          : '')
      + '<button type="button" class="ce-dlg-btn ce-dlg-ok ' + tone.cls + '" data-ce="ok">' + esc(opts.confirmText || '확인') + '</button>'
      + '</div></div>';
    document.body.appendChild(ov);

    var okBtn     = ov.querySelector('[data-ce="ok"]');
    var cancelBtn = ov.querySelector('[data-ce="cancel"]');

    return new Promise(function (resolve) {
      var settled = false;
      function done(v) {
        if (settled) return;
        settled = true;
        document.removeEventListener('keydown', onKey, true);
        ov.remove();
        resolve(v);
      }
      function onKey(e) {
        if (e.key === 'Escape')      { e.preventDefault(); done(kind === 'alert' ? undefined : false); }
        else if (e.key === 'Enter')  { e.preventDefault(); done(kind === 'alert' ? undefined : true); }
      }
      okBtn.addEventListener('click', function () { done(kind === 'alert' ? undefined : true); });
      if (cancelBtn) cancelBtn.addEventListener('click', function () { done(false); });
      ov.addEventListener('click', function (e) { if (e.target === ov) done(kind === 'alert' ? undefined : false); });
      document.addEventListener('keydown', onKey, true);
      okBtn.focus();
    });
  }

  window.ceAlert   = function (msg, opts) { return open('alert', msg, opts); };
  window.ceConfirm = function (msg, opts) { return open('confirm', msg, opts); };
  window.ceConfirmSubmit = function (form, msg, opts) {
    window.ceConfirm(msg, opts).then(function (ok) { if (ok) form.submit(); });
    return false;
  };
})();
</script>
