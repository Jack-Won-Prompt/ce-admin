{{-- 「직접 처리」 팝오버 — 공단에도 지자체에도 내지 않는 건(처방외ㆍ산재ㆍ자동차보험)에
     쓴다. 그 건은 환자가 보험사나 근로복지공단에 직접 내므로, 우리가 발행한 증빙을
     거래처로 보내 주는 것이 우리가 할 일의 전부다.

     목록 화면 여럿이 함께 쓴다(청구 관리ㆍ주문 관리). 이 파일 하나만 include 하면 된다. --}}
<style>
  /* 증빙 보내기 팝오버 — 목록을 보던 자리에서 그대로 보낸다 */
  .dd-pop { position:fixed; z-index:1300; width:340px; background:var(--gray-0);
            border:1px solid var(--gray-200); border-radius:12px;
            box-shadow:0 12px 32px rgba(16,19,23,.18); }
  .dd-head { display:flex; align-items:center; gap:6px; padding:10px 14px;
             border-bottom:1px solid var(--gray-200);
             font-size:13px; font-weight:700; color:var(--gray-1000); }
  .dd-head button { margin-left:auto; border:none; background:none; font-size:18px;
                    line-height:1; color:var(--gray-600); cursor:pointer; }
  .dd-body { padding:12px 14px; max-height:60vh; overflow:auto; }
  .dd-to { font-size:12px; color:var(--gray-600); line-height:1.7; margin-bottom:10px; }
  .dd-to b { color:var(--gray-1000); font-weight:600; }
  .dd-doc { display:flex; align-items:center; gap:8px; padding:6px 0; font-size:12px; }
  .dd-doc b { font-weight:600; color:var(--gray-1000); }
  .dd-doc span { color:var(--gray-600); flex:1; min-width:0; overflow:hidden;
                 text-overflow:ellipsis; white-space:nowrap; }
  .dd-none { padding:14px 0; text-align:center; font-size:12px; color:var(--gray-600); }
  .dd-acts { display:flex; justify-content:flex-end; gap:6px; margin-top:12px;
             padding-top:10px; border-top:1px solid var(--gray-200); }
</style>
<script>
/* ── 증빙 보내기 ────────────────────────────────────────────
   문자에는 파일을 붙일 수 없어 열어 볼 수 있는 주소를 보내고, 메일에는 파일을 그대로
   붙인다. 메일은 주소가 있을 때만 고를 수 있다. */
window.__ddPop = null;

function closeDirectSendPop() {
  if (window.__ddPop) { window.__ddPop.remove(); window.__ddPop = null; }
  document.removeEventListener('mousedown', window.__ddOutside, true);
}

window.__ddOutside = function (e) {
  if (window.__ddPop && !window.__ddPop.contains(e.target)) closeDirectSendPop();
};

function openDirectSendPop(orderId, anchor, opts) {
  closeDirectSendPop();
  opts = opts || {};

  const esc = function (t) {
    return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };

  const box = document.createElement('div');
  box.className = 'dd-pop';
  box.innerHTML =
    '<div class="dd-head">증빙 보내기'
    + '<button type="button" aria-label="닫기">&times;</button></div>'
    + '<div class="dd-body"><div class="dd-none">불러오는 중…</div></div>';
  document.body.appendChild(box);
  window.__ddPop = box;

  box.querySelector('.dd-head button').addEventListener('click', closeDirectSendPop);
  box.addEventListener('mousedown', function (e) { e.stopPropagation(); });
  setTimeout(function () {
    document.addEventListener('mousedown', window.__ddOutside, true);
  }, 0);

  const r = anchor.getBoundingClientRect();
  box.style.top  = Math.max(12, Math.min(window.innerHeight - box.offsetHeight - 12, r.bottom + 6)) + 'px';
  box.style.left = Math.max(12, Math.min(window.innerWidth  - box.offsetWidth  - 12, r.left)) + 'px';

  const base = @js(url('/orders'));

  fetch(base + '/' + orderId + '/docs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (res) { return res.json(); })
    .then(function (j) {
      if (!window.__ddPop) return;
      const docs = (j && j.data) || [];
      const body = window.__ddPop.querySelector('.dd-body');

      const to = '<div class="dd-to">받는 곳 · <b>' + esc(opts.name || '거래처') + '</b><br>'
               + (opts.mobile ? esc(opts.mobile) : '<span style="color:var(--gray-400)">연락처 없음</span>')
               + ' · '
               + (opts.email ? esc(opts.email) : '<span style="color:var(--gray-400)">이메일 없음</span>')
               + '</div>';

      if (!docs.length) {
        body.innerHTML = to + '<div class="dd-none">보낼 증빙이 아직 없습니다 — 먼저 발행해 주십시오.</div>';
        return;
      }

      const rows = docs.map(function (d) {
        return '<div class="dd-doc"><b>' + esc(d.label) + '</b>'
             + '<span>' + esc(d.file) + '</span></div>';
      }).join('');

      body.innerHTML = to + rows
        + '<div class="dd-acts">'
        +   '<button type="button" class="ds-btn" onclick="closeDirectSendPop()">닫기</button>'
        +   '<button type="button" class="ds-btn" '
        +     (opts.email ? '' : 'disabled title="이메일이 없습니다" ')
        +     'onclick="sendDirectDocs(' + orderId + ', \'email\', this)">이메일</button>'
        +   '<button type="button" class="ds-btn ds-btn-primary" '
        +     (opts.mobile ? '' : 'disabled title="연락처가 없습니다" ')
        +     'onclick="sendDirectDocs(' + orderId + ', \'sms\', this)">문자</button>'
        + '</div>';
    })
    .catch(function () {
      if (!window.__ddPop) return;
      window.__ddPop.querySelector('.dd-body').innerHTML =
        '<div class="dd-none">서류를 불러오지 못했습니다.</div>';
    });
}

function sendDirectDocs(orderId, channel, btn) {
  btn.disabled = true;

  fetch(@js(url('/orders')) + '/' + orderId + '/docs/send', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || '',
    },
    body: JSON.stringify({ channel: channel }),
  })
    .then(function (res) { return res.json(); })
    .then(function (j) {
      if (j && j.success) {
        showToast(j.message || '보냈습니다.', 'success', 5000);
        closeDirectSendPop();
      } else {
        showToast((j && j.message) || '보내지 못했습니다.', 'danger', 6000);
        btn.disabled = false;
      }
    })
    .catch(function () {
      showToast('보내는 중 오류가 발생했습니다.', 'danger');
      btn.disabled = false;
    });
}
</script>
