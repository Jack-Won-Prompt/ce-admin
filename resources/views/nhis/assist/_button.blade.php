{{-- 공단 청구 지원 창을 여는 버튼. 여러 목록 화면이 같은 것을 쓴다. --}}
{{-- 넘기는 것은 주문 ID 하나뿐이고 나머지는 지원 화면이 스스로 조회한다. --}}
<style>
  /* 표 안 버튼은 시안이 h28 · r8 · pad 0/12 · gap 6 · 12/500 이다
     (266:66 의 다운로드 묶음 74×28, 342:4037 의 365:135 116×28).
     31h · 11/700 이라 이 버튼이 든 행만 52 로 부풀어 있었다 — 시안 행은 48 이다.
     #d0d7de · #57606a 도 DS 램프 밖이라 gray-200 · gray-1000 으로. */
  .nhis-assist-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    height:28px; padding:0 12px; border:1px solid var(--gray-200); background:var(--gray-0); border-radius:8px;
    font-size:12px; font-weight:500; line-height:19px; color:var(--gray-1000); cursor:pointer; white-space:nowrap;
  }
  .nhis-assist-btn:hover:not(:disabled) { border-color:var(--primary); color:var(--primary); }
  .nhis-assist-btn:disabled { opacity:.45; cursor:not-allowed; }
</style>
<script>
window.nhisAssistBtn = function (orderId, opts) {
  opts = opts || {};
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'nhis-assist-btn';

  /* 공단은 사이트에 옮겨 적고, 지자체는 서류를 등기로 부친다 — 하는 일이 다르니
     단추 이름도 다르다. 「청구」라 적힌 단추를 지자체 건에서 누르면 공단 서식을
     기대하게 된다(요청서 10쪽). 부친 자취가 있으면 그것을 적어 준다. */
  const local = opts.agency && opts.agency !== 'nhis';
  btn.innerHTML = local
    ? '<i class="fa-solid fa-envelope"></i> ' + (opts.sent ? '등기 영수증' : '등기 발송')
    : '<i class="fa-solid fa-clipboard-list"></i> 청구';

  if (!orderId) {
    btn.disabled = true;
    btn.title = opts.reason || '연결된 주문이 없습니다';
    return btn;
  }

  btn.title = local
    ? '등기로 부친 것을 적고, 등기 영수증을 올립니다'
    : '왼쪽에 우리 청구 원본, 오른쪽에 공단 사이트를 나란히 엽니다';
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    // 좌우로 나란히 봐야 하는 창이라 화면을 꽉 채워 연다
    window.open(@js(url('/nhis/assist/claim')) + '/' + orderId + '?split=1',
                'nhis_claim_' + orderId,
                `width=${screen.availWidth},height=${screen.availHeight},left=0,top=0,scrollbars=yes,resizable=yes`);
  });
  return btn;
};
</script>
