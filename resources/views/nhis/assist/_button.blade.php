{{-- 공단 청구 지원 창을 여는 버튼. 여러 목록 화면이 같은 것을 쓴다. --}}
{{-- 넘기는 것은 주문 ID 하나뿐이고 나머지는 지원 화면이 스스로 조회한다. --}}
<style>
  .nhis-assist-btn {
    display:inline-flex; align-items:center; gap:4px;
    padding:4px 9px; border:1px solid #d0d7de; background:#fff; border-radius:6px;
    font-size:11px; font-weight:700; color:#57606a; cursor:pointer; white-space:nowrap;
  }
  .nhis-assist-btn:hover:not(:disabled) { border-color:#28798B; color:#28798B; }
  .nhis-assist-btn:disabled { opacity:.45; cursor:not-allowed; }
</style>
<script>
window.nhisAssistBtn = function (orderId, opts) {
  opts = opts || {};
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'nhis-assist-btn';
  btn.innerHTML = '<i class="fa-solid fa-clipboard-list"></i> 공단 청구';

  if (!orderId) {
    btn.disabled = true;
    btn.title = opts.reason || '연결된 주문이 없습니다';
    return btn;
  }

  btn.title = '공단 요양비지급청구서등록(2221) 화면에 붙여넣을 값을 순서대로 보여 줍니다';
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    window.open(@js(url('/nhis/assist/claim')) + '/' + orderId,
                'nhis_claim_' + orderId,
                'width=980,height=1000,scrollbars=yes,resizable=yes');
  });
  return btn;
};
</script>
