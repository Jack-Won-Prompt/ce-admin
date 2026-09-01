{{-- 「등기 발송」 — 지자체 청구는 서류를 등기로 부치고 그 자취를 적는 것이 전부다.
     화면을 꽉 채운 창을 열 까닭이 없어, 목록을 보던 자리에서 그대로 적는다.

     자리는 여기 미리 그려 둔다. 앞서 자바스크립트로 HTML 을 짜 넣었더니 화면이 멈췄다 —
     이제 여는 일과 닫는 일만 자바스크립트가 하고, 그리는 일은 서버가 한다.

     목록 화면 여럿이 함께 쓴다(청구 관리ㆍ주문 관리). --}}
<style>
  .ld-back { display:none; position:fixed; inset:0; z-index:1300;
             background:rgba(16,19,23,.35); align-items:center; justify-content:center; padding:24px; }
  .ld-back.open { display:flex; }
  .ld-box { width:min(360px,100%); background:var(--gray-0); border-radius:12px;
            box-shadow:0 12px 40px rgba(16,19,23,.24); overflow:hidden; }
  .ld-head { display:flex; align-items:center; gap:6px; padding:11px 15px;
             border-bottom:1px solid var(--gray-200);
             font-size:13px; font-weight:700; color:var(--gray-1000); }
  .ld-head button { margin-left:auto; border:none; background:none; font-size:19px;
                    line-height:1; color:var(--gray-600); cursor:pointer; }
  .ld-body { padding:13px 15px; max-height:66vh; overflow:auto; }
  .ld-sent { margin-bottom:11px; padding-bottom:11px; border-bottom:1px dashed var(--gray-200); }
  .ld-sent-row { display:flex; align-items:center; gap:8px; font-size:12px; padding:3px 0; }
  .ld-sent-row b { font-weight:600; color:var(--gray-1000); flex-shrink:0; }
  .ld-sent-row i { font-style:normal; color:var(--gray-600); flex:1; min-width:0;
                   overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .ld-sent-row a { color:var(--primary); text-decoration:none; font-weight:500; flex-shrink:0; }
  .ld-f { margin-bottom:9px; }
  .ld-f label { display:block; font-size:12px; font-weight:500; color:var(--gray-700); margin-bottom:4px; }
  .ld-f label em { font-style:normal; color:var(--danger); }
  .ld-f .form-control { width:100%; }
  .ld-acts { display:flex; justify-content:flex-end; gap:6px;
             padding:11px 15px; border-top:1px solid var(--gray-200); background:var(--gray-50); }
</style>

<div class="ld-back" id="ldBack">
  <div class="ld-box">
    <div class="ld-head">
      <i class="fa-solid fa-envelope"></i> 등기 발송
      <button type="button" onclick="closeLocalDispatchPop()" aria-label="닫기">&times;</button>
    </div>

    {{-- 보내는 곳은 하나다. action 은 어느 주문인지 정해질 때 자바스크립트가 적는다. --}}
    <form id="ldForm" method="POST" action="" enctype="multipart/form-data">
      @csrf
      <div class="ld-body">
        {{-- 이미 부친 것이 있으면 여기 선다 — 두 번 부치는 일을 막는다 --}}
        <div class="ld-sent" id="ldSent" style="display:none;"></div>

        <div class="ld-f">
          <label>발송일 <em>*</em></label>
          <input type="date" name="sent_date" id="ldDate" class="form-control" required>
        </div>
        <div class="ld-f">
          <label>등기번호</label>
          <input type="text" name="registered_no" maxlength="50" class="form-control"
                 placeholder="우체국 등기번호">
        </div>
        <div class="ld-f">
          <label>발송 영수증</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="form-control">
        </div>
        <div class="ld-f" style="margin-bottom:0;">
          <label>메모</label>
          <input type="text" name="memo" maxlength="500" class="form-control">
        </div>
      </div>
      <div class="ld-acts">
        <button type="button" class="ds-btn" onclick="closeLocalDispatchPop()">취소</button>
        <button type="submit" class="ds-btn ds-btn-primary">저장</button>
      </div>
    </form>
  </div>
</div>
<script>
/* 여는 일과 닫는 일만 한다 — 그리는 일은 서버가 이미 했다. */
function openLocalDispatchPop(orderId, anchor) {
  var back = document.getElementById('ldBack');
  var form = document.getElementById('ldForm');
  if (!back || !form) return;

  form.action = @js(url('/nhis/assist/claim')) + '/' + orderId + '/local-dispatch';
  form.reset();
  document.getElementById('ldDate').value = new Date().toISOString().slice(0, 10);

  var sent = document.getElementById('ldSent');
  sent.style.display = 'none';
  sent.innerHTML = '';

  back.classList.add('open');

  /* 이미 부친 것을 읽어 온다. 늦거나 막혀도 적는 일은 막지 않는다. */
  fetch(@js(url('/nhis/assist/claim')) + '/' + orderId + '/dispatches',
        { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (res) { return res.json(); })
    .then(function (j) {
      var rows = (j && j.data) || [];
      if (!rows.length) return;

      var html = '';
      for (var i = 0; i < rows.length; i++) {
        var d = rows[i];
        html += '<div class="ld-sent-row">';
        html += '<b>' + ldEsc(d.sent_date || '-') + '</b>';
        html += '<i>' + ldEsc(d.registered_no || '등기번호 없음') + '</i>';
        html += d.receipt_url
          ? '<a href="' + d.receipt_url + '" target="_blank">영수증</a>'
          : '<i style="flex:0 0 auto;color:var(--gray-400);">영수증 없음</i>';
        html += '</div>';
      }
      sent.innerHTML = html;
      sent.style.display = '';
    })
    .catch(function () { /* 못 읽어도 적는 것은 할 수 있다 */ });
}

function closeLocalDispatchPop() {
  var back = document.getElementById('ldBack');
  if (back) back.classList.remove('open');
}

function ldEsc(t) {
  return String(t == null ? '' : t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* 바깥을 누르면 닫는다 — 안을 누르면 그대로 둔다 */
document.addEventListener('click', function (e) {
  if (e.target && e.target.id === 'ldBack') closeLocalDispatchPop();
});
</script>
