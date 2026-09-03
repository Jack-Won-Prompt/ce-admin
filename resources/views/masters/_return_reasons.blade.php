{{-- ── 반품 사유 ────────────────────────────────────────────
     요청서 6쪽. 사유가 정해지면 두 가지가 함께 정해진다 —
     금액을 조정하는가, 발행 내역에 넣는가. 담당자가 매번 판단하면 사람마다 달라지고
     고객에게 안내한 내용도 갈린다.

     코드 안의 배열에서 표로 옮긴 까닭은 하나다 — 이 규칙은 앞으로도 바뀔 값이고,
     바뀔 때마다 배포를 기다리게 두면 결국 아무도 안 고친다. --}}
<style>
  .rr-note { font-size:12px; color:var(--text-secondary); line-height:1.7;
             padding:12px 16px; background:var(--gray-50); border:1px solid var(--border);
             border-radius:10px; margin-bottom:12px; }
  .rr-tbl { width:100%; border-collapse:collapse; font-size:13px; }
  .rr-tbl th, .rr-tbl td { padding:10px 12px; border-bottom:1px solid var(--border-light); text-align:left; }
  .rr-tbl th { font-size:12px; color:var(--text-muted); font-weight:600; white-space:nowrap; }
  .rr-tbl td.mid { text-align:center; }
  .rr-tbl input[type=text] { height:30px; font-size:13px; }
  .rr-tbl select { height:30px; font-size:13px; }
  .rr-code { font-family:monospace; font-size:12px; color:var(--text-muted); }
</style>

<div class="rr-note">
  <b>사유가 정하는 것</b> — 금액을 조정하는가 · 발행 내역에 넣는가.<br>
  <b>금액조정</b>을 끄면 반품 시 금액조정 주문을 생성하지 않습니다. 제품만 교환하는
  교환은 돈이 그대로라 조정할 것이 없습니다.<br>
  <b>발행포함</b>을 끄면 세금계산서·현금영수증을 취소하지 않습니다. 처음부터 발행에
  들지 않은 건을 취소하면 멀쩡한 발행이 국세청까지 취소되어 갑니다.
</div>

<div class="ds-grid-card">
  <form method="POST" action="{{ route('masters.returnReasons') }}">
    @csrf @method('PATCH')
    <div style="overflow-x:auto;">
      <table class="rr-tbl">
        <thead>
          <tr>
            <th style="width:150px;">코드</th>
            <th style="width:170px;">이름</th>
            <th style="width:100px;" class="mid">금액조정</th>
            <th style="width:100px;" class="mid">발행포함</th>
            <th style="width:80px;"  class="mid">사용</th>
            <th style="width:80px;"  class="mid">차례</th>
          </tr>
        </thead>
        <tbody>
          @foreach($reasons as $r)
            <tr>
              <td><span class="rr-code">{{ $r->code }}</span>
                  <input type="hidden" name="rows[{{ $loop->index }}][code]" value="{{ $r->code }}"></td>
              <td><input type="text" class="form-control" maxlength="60"
                         name="rows[{{ $loop->index }}][label]" value="{{ $r->label }}"></td>
              <td class="mid"><input type="checkbox" name="rows[{{ $loop->index }}][adjusts_amount]"
                                     value="1" @checked($r->adjusts_amount)></td>
              <td class="mid"><input type="checkbox" name="rows[{{ $loop->index }}][includes_issue]"
                                     value="1" @checked($r->includes_issue)></td>
              <td class="mid"><input type="checkbox" name="rows[{{ $loop->index }}][is_active]"
                                     value="1" @checked($r->is_active)></td>
              <td class="mid"><input type="text" class="form-control" style="width:60px;text-align:center;"
                                     name="rows[{{ $loop->index }}][sort_order]" value="{{ $r->sort_order }}"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:flex-end;padding:12px 16px;">
      <button type="submit" class="ds-btn ds-btn-primary">저장</button>
    </div>
  </form>
</div>
