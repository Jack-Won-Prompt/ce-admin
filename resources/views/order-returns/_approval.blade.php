{{-- 두 걸음 결재 (2026-09-28 지시) ───────────────────────────────────────

     창고가 입고 검수를 마치고 승인을 청하면 ① 책임자가 보고 승인하거나 반려하고,
     ② 승인된 건은 최종승인자가 서명한다. **서명이 곧 실행이다.**

     단계(status)는 늘리지 않았다 — 흐름의 「검수 확정」이 책임자 승인이고 「전자
     승인」이 최종승인자 서명이다. 그래서 이 판에서 누르는 단추가 「진행 단계」의
     같은 걸음을 옮긴다. 두 자리에서 같은 걸음을 옮기게 둔 까닭은, 결재하는 사람이
     보아야 하는 것(검수 결과ㆍ차감 금액ㆍ서명)이 진행 단계 카드에 들어가지 않기
     때문이다.

     보이는 것은 권한에 따라 갈린다 — 책임자 칸은 책임자에게, 서명 칸은
     최종승인자에게. 눌러도 안 되는 단추를 세워 두면 까닭을 몰라 멈춰 선다. --}}
@php
  $책임자 = \App\Models\OrderReturn::canApproveStep('inspected');
  $최종   = \App\Models\OrderReturn::canApproveStep('approved');
  $결재됨 = (bool) $r->inspect_confirmed_at;
  $서명됨 = (bool) $r->final_signed_at;
  $길     = $r->refundRoute();
  /* 끝난 건은 다시 셈하지 않는다 — 실제로 오간 돈을 보인다 */
  $움직임 = $r->결재금액();
  $끝났나 = $r->refund_stage === 'refunded';
  /* 나가는 돈과 들어오는 돈을 가른다 (2026-09-28 지시) — 한 건에 둘이 함께 서지 않는다 */
  $환불액 = $r->환불금액();
  $차액   = $r->차액금액();
  $하자   = $r->inspect_result === \App\Models\OrderReturn::RESULT_DEFECT;
@endphp

{{-- 창고가 청한 검수 요청 ─────────────────────────────────
     여태 상태가 「검수중」으로만 남아, 창고가 올린 것인지 우리가 손으로 옮긴 것인지
     가릴 수 없었다. 창고가 청한 자리를 눈에 띄게 세운다. --}}
@if($r->warehouse_inspect_requested_at)
<div class="rt-card">
  <div class="rt-hd">
    창고 검수 요청
    <span class="grow"></span>
    @if($r->창고검수요청중())
      <span class="ap-chip ap-new">확인 전</span>
    @else
      <span class="ap-chip ap-seen">확인</span>
    @endif
  </div>
  <div class="rt-bd">
    <div class="rt-kv"><span>요청 일시</span><span>{{ $r->warehouse_inspect_requested_at->format('Y-m-d H:i') }}</span></div>
    <div class="rt-kv"><span>확인 일시</span><span>{{ $r->warehouse_inspect_seen_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
    @if($r->inspect_source)
      <div class="rt-kv"><span>검수 결과 출처</span><span>
        {{ $r->inspect_source === 'warehouse' ? '창고 전송' : '담당자 입력' }}
      </span></div>
    @endif
    <div class="rt-go">
      {{-- 창고가 실제로 무엇을 보냈는지는 웹훅 원문에만 있다 — 받은 수량ㆍ보낸 수량은
           우리 표에 담는 칸이 없어, 여태 위드웍스 화면을 따로 열어야 했다. --}}
      <button type="button" class="ds-btn ds-btn-sm" onclick="ap창고열기()">요청 내용 보기</button>
      @if($r->창고검수요청중())
        <form method="POST" action="{{ route('order-returns.seenInspection', $r) }}"
              style="display:inline-flex;gap:8px;align-items:center;margin:0;">
          @csrf
          <button type="submit" class="ds-btn ds-btn-sm">확인했습니다</button>
          <span style="font-size:12px;color:var(--text-muted);">
            목록의 「창고 검수 요청」 표시를 해제합니다. 결재 단계와는 무관합니다.
          </span>
        </form>
      @endif
    </div>
  </div>
</div>
@endif

{{-- ① 책임자 검수 ───────────────────────────────────────
     차감 금액이 여기서 굳는다. 최종승인자가 서명하는 숫자가 이 칸에서 나온다. --}}
<div class="rt-card">
  <div class="rt-hd">
    ① 책임자 검수
    <span class="grow"></span>
    @if($r->manager_rejected_at && ! $결재됨)
      <span class="ap-chip ap-bad">반려</span>
    @elseif($결재됨)
      <span class="ap-chip ap-ok">승인</span>
    @else
      <span class="ap-chip">대기</span>
    @endif
  </div>
  <div class="rt-bd">
    @if($결재됨)
      <div class="rt-kv"><span>승인</span><span>
        {{ $r->inspectConfirmer?->name ?? '—' }} ·
        {{ $r->inspect_confirmed_at->format('Y-m-d H:i') }}
      </span></div>
      <div class="rt-kv"><span>검수 결과</span><span>
        @if($하자)
          <span class="ap-chip ap-bad">하자ㆍ수량 차이</span>
        @else
          <span class="ap-chip ap-ok">이상 없음</span>
        @endif
      </span></div>
      @if($하자)
        <div class="rt-kv"><span>수량 차이</span><span>{{ $r->inspect_defect_qty !== null ? $r->inspect_defect_qty . '개' : '—' }}</span></div>
        <div class="rt-kv rt-note"><span>하자 내용</span><span>{{ $r->inspect_defect_note ?: '—' }}</span></div>
        <div class="rt-kv"><span>차감 금액</span><span style="color:var(--danger);font-weight:700;">
          {{ number_format((int) $r->inspect_deduct_amount) }}원
        </span></div>
      @endif
      <div class="rt-kv"><span>결재 경로</span><span><b>{{ $r->refundRouteLabel() }}</b></span></div>
      {{-- 나가는 돈과 들어오는 돈을 **다른 줄**에 둔다. 한 줄에 담으면 목록에서도
           상세에서도 그 숫자가 돌려줄 돈인지 더 받을 돈인지 가릴 수 없다. --}}
      <div class="rt-kv"><span>환불 (지급)</span><span>
        @if($환불액)
          <b style="color:var(--primary);">{{ number_format($환불액) }}원</b>
          <span style="color:var(--text-muted);font-weight:400;">고객에게 지급</span>
        @else — @endif
      </span></div>
      <div class="rt-kv"><span>차액 입금 (청구)</span><span>
        @if($차액)
          <b style="color:#B54708;">{{ number_format($차액) }}원</b>
          <span style="color:var(--text-muted);font-weight:400;">고객에게 청구</span>
        @else — @endif
      </span></div>
      @if($하자 && $길 === \App\Models\OrderReturn::ROUTE_PARTIAL)
        {{-- 차감 뒤의 금액만 보이면 「무엇에서 얼마를 뺐나」를 알 수 없다. 끝난 뒤에는
             받은 돈이 이미 줄어 있어 그 셈을 화면에서 되짚을 수도 없다.

             부분 환불에만 세운다. 차액 청구에서는 움직이는 돈이 차감액 그 자체라,
             더하면 4,500 + 4,500 = 9,000 처럼 뜻 없는 숫자가 선다. --}}
        <div class="rt-kv"><span>차감 전 금액</span><span style="font-weight:400;color:var(--text-muted);">
          {{ number_format($움직임 + (int) $r->inspect_deduct_amount) }}원
        </span></div>
      @endif

      {{-- 승인을 되돌리는 자리는 두지 않는다. 서명을 받은 뒤라면 돈이 이미 움직였고,
           받기 전이라면 최종승인자가 반려하면 창고로 되돌아간다. 되돌리는 단추를
           또 두면 「어느 것으로 되돌렸는가」가 이력에서 갈리지 않는다. --}}
    @else
      @if($r->manager_rejected_at)
        <div class="rt-kv rt-note"><span>이전 반려</span><span>
          {{ $r->manager_reject_reason }}
          <span class="rt-note-at">
            {{ $r->managerRejecter?->name }} · {{ $r->manager_rejected_at->format('Y-m-d H:i') }}
          </span>
        </span></div>
      @endif
      @if($r->final_rejected_at)
        <div class="rt-kv rt-note"><span>최종 반려</span><span>
          {{ $r->final_reject_reason }}
          <span class="rt-note-at">
            {{ $r->finalRejecter?->name }} · {{ $r->final_rejected_at->format('Y-m-d H:i') }}
          </span>
        </span></div>
      @endif

      @if(! $책임자)
        <div class="rt-locked">
          책임자 검수 권한이 있어야 누를 수 있습니다 —
          설정 › 권한 그룹에서 「책임자 검수 승인 · 반려」를 받으십시오.
        </div>
      @else
        {{-- 창고가 알려 준 값이 있으면 미리 골라 둔다. 위드웍스가 inspection 을 실어
             보내기 전까지는 빈칸이라, 책임자가 실물 검수 결과를 보고 고른다. --}}
        <form method="POST" action="{{ route('order-returns.managerApprove', $r) }}" id="apForm">
          @csrf
          <div class="ap-grid">
            <div class="ap-f ap-wide">
              <label>검수 결과</label>
              <div class="ap-pick">
                <label><input type="radio" name="inspect_result" value="ok" onchange="ap하자(false)"
                              @checked($r->inspect_result === \App\Models\OrderReturn::RESULT_OK)> 이상 없음</label>
                <label><input type="radio" name="inspect_result" value="defect" onchange="ap하자(true)"
                              @checked($하자)> 하자ㆍ수량 차이</label>
              </div>
              <div class="ap-hint" id="apWhat">검수 결과를 선택하면 결재 경로가 결정됩니다.</div>
            </div>

            <div class="ap-f ap-defect">
              <label>수량 차이 (개)</label>
              <input type="number" name="inspect_defect_qty" class="form-control" min="0" step="1"
                     value="{{ $r->inspect_defect_qty }}">
            </div>
            <div class="ap-f ap-defect">
              <label>차감 금액 (원)</label>
              <input type="number" name="inspect_deduct_amount" class="form-control" min="0" step="1"
                     id="apDeduct" value="{{ $r->inspect_deduct_amount }}"
                     oninput="ap셈()">
            </div>
            <div class="ap-f ap-wide ap-defect">
              <label>하자 내용</label>
              <input type="text" name="inspect_defect_note" class="form-control" maxlength="500"
                     value="{{ $r->inspect_defect_note }}"
                     placeholder="하자 내용을 구체적으로 입력해 주십시오 — 최종승인자가 이 내용을 확인하고 서명합니다.">
            </div>
          </div>

          <div class="ap-sum" id="apSum"></div>

          <div class="rt-go">
            <button type="submit" class="ds-btn ds-btn-primary"
                    onclick="return ceConfirmClick(this, '이 내용으로 승인하시겠습니까? 최종승인자가 이 금액에 서명합니다.');">
              책임자 승인
            </button>
            <button type="button" class="ds-btn" onclick="ap반려()">반려</button>
          </div>
        </form>

        <form method="POST" action="{{ route('order-returns.managerReject', $r) }}" id="apRej" style="display:none;">
          @csrf
          <div class="ap-f ap-wide" style="margin-top:10px;">
            <label>반려 사유</label>
            <input type="text" name="reason" class="form-control" maxlength="500" required
                   placeholder="창고로 반송하여 재검수를 요청합니다. 반려 사유를 입력해 주십시오.">
          </div>
          <div class="rt-go">
            <button type="submit" class="ds-btn"
                    onclick="return ceConfirmClick(this, '반려하시겠습니까? 창고로 반송하여 재검수를 요청합니다.');">
              반려로 보내기
            </button>
            <button type="button" class="ds-btn ds-btn-sm" onclick="ap반려(false)">취소</button>
          </div>
        </form>
      @endif
    @endif
  </div>
</div>

{{-- ② 최종승인자 서명 ────────────────────────────────────
     책임자가 승인한 뒤에만 선다. 돈이 움직이지 않는 건(이상 없는 교환)은 서명을
     받지 않는다 — 아무 일도 하지 않는 결재가 하나 늘 뿐이다. --}}
@if($결재됨)
<div class="rt-card">
  <div class="rt-hd">
    ② 최종승인자 서명
    <span class="grow"></span>
    @if($서명됨)
      <span class="ap-chip ap-ok">서명 완료</span>
    @elseif(! $r->needsFinalSign())
      <span class="ap-chip">해당 없음</span>
    @elseif($r->서명링크살았나())
      <span class="ap-chip ap-wait">서명 대기</span>
    @else
      <span class="ap-chip">미발송</span>
    @endif
  </div>
  <div class="rt-bd">
    @unless($r->needsFinalSign())
      <div style="font-size:13px;color:var(--text-muted);">
        금액 변동이 없는 건입니다 — 최종승인자 서명을 받지 않습니다.
        다음 걸음은 「진행 단계」에서 옮기십시오.
      </div>
    @else

      {{-- 서명하면 무슨 일이 일어나는가. 이 숫자가 결재의 알맹이다. --}}
      <div class="ap-amt {{ $차액 ? 'take' : 'give' }}">
        <div class="t">
          {{ $r->refundRouteLabel() }}
          · <b>{{ $차액 ? '고객에게 청구' : '고객에게 지급' }}</b>
        </div>
        <div class="n">{{ number_format($움직임) }}원</div>
        <div class="s">
          @if($끝났나)
            {{-- 끝난 건은 셈을 다시 적지 않는다. 받은 돈이 이미 줄어 있어 그 셈이
                 맞지 않고, 읽는 사람은 금액이 바뀐 줄 안다. --}}
            <b>실제로 환불한 금액입니다.</b>
            {{ $r->refunded_at?->format('Y-m-d H:i') }}
            @if($하자)
              · 차감 {{ number_format((int) $r->inspect_deduct_amount) }}원을 뺀 금액입니다.
            @endif
          @elseif($길 === \App\Models\OrderReturn::ROUTE_TOPUP)
            고객에게 <b>추가 청구</b>할 금액입니다. 서명 후 담당자가 전화로 안내한 다음
            ［차액 결제 링크 보내기］를 누릅니다 — 저절로 나가지 않습니다.
          @elseif($길 === \App\Models\OrderReturn::ROUTE_PARTIAL)
            수납 금액 {{ number_format((int) ($r->order?->받은금액() ?? 0)) }}원에서
            차감 {{ number_format((int) $r->inspect_deduct_amount) }}원을 뺀 금액입니다.
            <b>서명하면 즉시 환불됩니다.</b>
          @else
            수납 금액 전액입니다. <b>서명하면 즉시 환불됩니다.</b>
          @endif
        </div>
      </div>

      <div class="rt-kv"><span>결재 단계</span><span>{{ $r->결재단계말() ?: '—' }}</span></div>

      @if($서명됨)
        <div class="rt-kv"><span>서명</span><span>
          {{ $r->finalSigner?->name ?? $r->finalSignTarget?->name ?? '—' }} ·
          {{ $r->final_signed_at->format('Y-m-d H:i') }}
        </span></div>
        @if($r->final_sign_base64)
          <div class="rt-kv"><span>서명 이미지</span><span>
            <img src="{{ $r->final_sign_base64 }}" alt="최종승인자 서명" class="ap-sig-img">
          </span></div>
        @endif
        @if($r->final_sign_ip)
          <div class="rt-kv"><span>서명 IP</span><span style="font-weight:400;color:var(--text-muted);">
            {{ $r->final_sign_ip }}
          </span></div>
        @endif

        {{-- 환불이 막혔을 때. **서명은 그대로 둔다** — 서명을 무르면 최종승인자에게
             다시 받아야 하는데, 막힌 까닭은 대개 우리 쪽이 아니다. --}}
        @if($r->refund_stage === 'refund_failed')
          <div class="no-bar" style="margin:12px 0 0;">
            <p>환불하지 못했습니다 ({{ (int) $r->refund_attempts }}번 시도)</p>
            <p style="font-weight:400;">{{ $r->refund_last_error }}</p>
          </div>
          <form method="POST" action="{{ route('order-returns.retryRefund', $r) }}" class="rt-go">
            @csrf
            <button type="submit" class="ds-btn ds-btn-primary"
                    onclick="return ceConfirmClick(this, '환불을 다시 시도합니다. 계속하시겠습니까?');">
              환불 다시 시도
            </button>
            <span style="font-size:12px;color:var(--text-muted);">서명은 그대로 유지됩니다.</span>
          </form>
        @endif

        {{-- 교환의 차액 — 담당자가 전화한 뒤 누른다 --}}
        @if($길 === \App\Models\OrderReturn::ROUTE_TOPUP)
          @if($r->topup_sent_at)
            <div class="rt-kv"><span>차액 링크</span><span>
              {{ $r->topup_sent_at->format('Y-m-d H:i') }} 발송
              @if($r->refund_stage === 'topup_paid')
                · <span class="ap-chip ap-ok">입금 확인</span>
              @else
                · <span class="ap-chip ap-wait">미납</span>
              @endif
            </span></div>
          @endif
          @if($r->refund_stage !== 'topup_paid')
            <form method="POST" action="{{ route('order-returns.sendTopupLink', $r) }}" class="rt-go">
              @csrf
              <input type="text" name="mobile" class="form-control" maxlength="20"
                     value="{{ $r->order?->patient?->mobile }}" placeholder="받을 휴대폰 번호">
              <button type="submit" class="ds-btn ds-btn-primary"
                      onclick="return ceConfirmClick(this, '고객에게 전화로 안내하셨습니까? 차액 결제 링크를 문자로 발송합니다.');">
                {{ $r->topup_sent_at ? '차액 결제 링크 다시 보내기' : '차액 결제 링크 보내기' }}
              </button>
            </form>
            <div class="ap-hint">
              고객에게 전화로 안내한 뒤 눌러 주십시오 — 사전 안내 없이 링크만 발송하면 고객 문의가 발생합니다.
            </div>
          @endif
        @endif

      @else
        @if($r->final_sign_sent_at)
          <div class="rt-kv"><span>보낸 곳</span><span>
            {{ $r->finalSignTarget?->name ?? '—' }} · {{ $r->final_sign_sent_to }}
            <span style="color:var(--text-muted);font-weight:400;">
              {{ $r->final_sign_sent_at->format('Y-m-d H:i') }} 발송
            </span>
          </span></div>
          <div class="rt-kv"><span>링크 유효</span><span>
            @if($r->서명링크살았나())
              {{ $r->final_sign_expires_at?->format('Y-m-d H:i') }} 까지
            @else
              <span style="color:var(--danger);">만료 — 재발송이 필요합니다</span>
            @endif
          </span></div>
        @endif

        @if(! $최종)
          <div class="rt-locked" style="margin-top:10px;">
            최종승인자 권한이 있어야 서명할 수 있습니다 —
            설정 › 권한 그룹에서 「최종승인자 서명」을 받으십시오.
            서명 링크 발송도 같은 권한입니다.
          </div>
        @else
          {{-- 서명을 받는 길이 둘이다. SMS 는 최종승인자가 자리에 없을 때,
               화면 안 서명은 옆에 있거나 본인이 직접 볼 때. 담는 자리는 같다. --}}
          <div class="rt-go">
            <button type="button" class="ds-btn ds-btn-primary" onclick="ap서명열기()">화면에서 서명 받기</button>
            <button type="button" class="ds-btn" onclick="ap보내기열기()">서명 링크 문자 보내기</button>
            <button type="button" class="ds-btn" onclick="ap최종반려()">반려</button>
          </div>

          {{-- 문자 보내기 --}}
          <form method="POST" action="{{ route('order-returns.finalSignSend', $r) }}" id="apSend" style="display:none;">
            @csrf
            <div class="ap-grid" style="margin-top:10px;">
              <div class="ap-f">
                <label>최종승인자</label>
                <select name="user_id" class="form-control" id="apWho" onchange="ap번호채우기()" required>
                  <option value="">— 선택 —</option>
                </select>
              </div>
              <div class="ap-f">
                <label>휴대폰 번호</label>
                <input type="text" name="mobile" class="form-control" id="apNum" maxlength="20"
                       placeholder="비워 두면 사용자 정보의 번호로 발송">
              </div>
            </div>
            <div class="ap-hint">
              최종승인자 권한이 있는 사용자만 표시됩니다 — 권한이 없는 사용자는 링크를 열어도 서명할 수 없습니다.
              링크는 <b>24시간</b> 유효합니다.
            </div>
            <div class="rt-go">
              <button type="submit" class="ds-btn ds-btn-primary">문자 보내기</button>
              <button type="button" class="ds-btn ds-btn-sm" onclick="ap보내기열기(false)">취소</button>
            </div>
          </form>

          {{-- 화면 안 서명 — **확인 팝오버** (2026-09-28 지시).

               서명은 돈을 움직이는 결재다. 카드 안에 펼치면 무엇에 서명하는지가
               위아래로 흩어져, 서명하는 사람은 금액만 보고 누른다. 팝오버 한 자리에
               확인할 것을 모아 두고 그 아래에서 서명받는다. --}}
          <div id="apSignPop" style="display:none;position:fixed;inset:0;z-index:1200;
                                     background:rgba(15,23,42,.34);"
               onclick="if(event.target===this) ap서명열기(false)">
            <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
                        width:min(560px,94vw);max-height:88vh;display:flex;flex-direction:column;
                        background:var(--bg-card,#fff);border-radius:12px;
                        box-shadow:0 18px 48px rgba(15,23,42,.24);overflow:hidden;">
              <div style="padding:13px 16px;border-bottom:1px solid var(--border);
                          display:flex;align-items:center;gap:8px;">
                <span style="font-size:14px;font-weight:700;">최종승인자 서명</span>
                <span style="font-size:12px;color:var(--text-muted);">{{ $r->receipt_no }}</span>
                <span style="flex:1;"></span>
                <button type="button" class="ds-btn ds-btn-sm" onclick="ap서명열기(false)">✕</button>
              </div>

              <form method="POST" action="{{ route('order-returns.finalSign', $r) }}" id="apSign"
                    style="padding:12px 16px;overflow-y:auto;">
                @csrf
                <input type="hidden" name="signature" id="apSigData">

                {{-- 서명하기 전에 확인할 것 --}}
                <div class="rt-kv"><span>구분</span><span>{{ $r->typeLabel() }}</span></div>
                <div class="rt-kv"><span>주문번호</span><span>{{ $r->order?->order_number ?? '—' }}</span></div>
                <div class="rt-kv"><span>고객</span><span>{{ $r->order?->patient?->name ?? '—' }}</span></div>
                <div class="rt-kv"><span>입고 검수</span><span>
                  @if($하자)<span class="ap-chip ap-bad">하자ㆍ수량 차이</span>
                  @else<span class="ap-chip ap-ok">이상 없음</span>@endif
                  @if($하자 && $r->inspect_defect_qty)
                    <span style="color:var(--text-muted);font-weight:400;">· {{ $r->inspect_defect_qty }}개</span>
                  @endif
                </span></div>
                @if($하자)
                  <div class="rt-kv rt-note"><span>하자 내용</span><span>{{ $r->inspect_defect_note ?: '—' }}</span></div>
                  <div class="rt-kv"><span>차감 금액</span><span style="color:var(--danger);font-weight:700;">
                    {{ number_format((int) $r->inspect_deduct_amount) }}원
                  </span></div>
                @endif
                <div class="rt-kv"><span>책임자 승인</span><span>
                  {{ $r->inspectConfirmer?->name ?? '—' }}
                  <span style="color:var(--text-muted);font-weight:400;">
                    {{ $r->inspect_confirmed_at?->format('Y-m-d H:i') }}</span>
                </span></div>

                {{-- 서명하면 움직일 돈 — 두 항목을 나란히 두어 방향을 못박는다 --}}
                <div class="ap-two">
                  <div class="ap-one {{ $환불액 ? 'on give' : '' }}">
                    <div class="t">환불 (고객에게 지급)</div>
                    <div class="n">{{ $환불액 ? number_format($환불액) . '원' : '해당 없음' }}</div>
                  </div>
                  <div class="ap-one {{ $차액 ? 'on take' : '' }}">
                    <div class="t">차액 입금 (고객에게 청구)</div>
                    <div class="n">{{ $차액 ? number_format($차액) . '원' : '해당 없음' }}</div>
                  </div>
                </div>

                <div class="ap-f ap-wide" style="margin-top:10px;">
                  <label>서명</label>
                  <canvas id="apCanvas" class="ap-canvas"></canvas>
                  <div class="ap-sigbar">
                    <span class="ap-hint" id="apSigWhy">서명란에 서명해 주십시오.</span>
                    <button type="button" class="ds-btn ds-btn-sm" onclick="ap지우기()">지우기</button>
                  </div>
                </div>

                <div class="rt-go">
                  <button type="submit" class="ds-btn ds-btn-primary" id="apSigGo" disabled
                          onclick="return ap서명보내기(this);">
                    서명하고 승인
                  </button>
                  <button type="button" class="ds-btn ds-btn-sm" onclick="ap서명열기(false)">닫기</button>
                </div>
              </form>
            </div>
          </div>

          {{-- 최종 반려 --}}
          <form method="POST" action="{{ route('order-returns.finalReject', $r) }}" id="apFinRej" style="display:none;">
            @csrf
            <div class="ap-f ap-wide" style="margin-top:10px;">
              <label>반려 사유</label>
              <input type="text" name="reason" class="form-control" maxlength="500" required
                     placeholder="창고로 반송하여 재검수를 요청합니다. 반려 사유를 입력해 주십시오.">
            </div>
            <div class="rt-go">
              <button type="submit" class="ds-btn"
                      onclick="return ceConfirmClick(this, '반려하시겠습니까? 책임자 검수 승인을 취소하고 창고로 반송합니다.');">
                반려로 보내기
              </button>
              <button type="button" class="ds-btn ds-btn-sm" onclick="ap최종반려(false)">취소</button>
            </div>
          </form>
        @endif
      @endif
    @endunless
  </div>
</div>
@endif

{{-- 창고 검수 요청 원문 팝오버 (2026-09-28 지시) --}}
<div id="apWhPop" style="display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,23,42,.34);"
     onclick="if(event.target===this) ap창고열기(false)">
  <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
              width:min(560px,94vw);max-height:86vh;display:flex;flex-direction:column;
              background:var(--bg-card,#fff);border-radius:12px;
              box-shadow:0 18px 48px rgba(15,23,42,.24);overflow:hidden;">
    <div style="padding:13px 16px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;gap:8px;">
      <span style="font-size:14px;font-weight:700;">창고 검수 요청 내용</span>
      <span style="font-size:12px;color:var(--text-muted);">{{ $r->receipt_no }}</span>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn ds-btn-sm" onclick="ap창고열기(false)">✕</button>
    </div>
    <div id="apWhBody" style="padding:8px 16px 14px;overflow-y:auto;font-size:13px;"></div>
    <div style="padding:11px 16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
      <button type="button" class="ds-btn" onclick="ap창고열기(false)">닫기</button>
    </div>
  </div>
</div>

<script>
/* ── 책임자 검수 칸 ───────────────────────────────────────
   「이상 없음」을 골랐으면 하자 칸은 세우지 않는다 — 비워 두어도 컨트롤러가 버리지만,
   서 있으면 적어야 하는 줄 알고 적는다. 적힌 값이 남아 있으면 되묻게 된다. */
function ap하자(있나) {
  document.querySelectorAll('#apForm .ap-defect').forEach(el => el.style.display = 있나 ? '' : 'none');
  if (!있나) {
    document.querySelectorAll('#apForm .ap-defect input').forEach(el => el.value = '');
  }
  ap셈();
}

/* 고른 값으로 무엇이 일어날지 미리 적는다 — 승인 단추를 누르기 전에 보여야 한다 */
const ap받은것 = {{ (int) ($r->order?->받은금액() ?? 0) }};
const ap교환   = @json($r->type === \App\Models\OrderReturn::TYPE_EXCHANGE);

function ap셈() {
  const 칸 = document.getElementById('apSum');
  if (!칸) return;

  const 고름 = document.querySelector('#apForm input[name=inspect_result]:checked');
  if (!고름) { 칸.textContent = ''; 칸.className = 'ap-sum'; return; }

  const 하자 = 고름.value === 'defect';
  const 차감 = Math.max(0, parseInt(document.getElementById('apDeduct')?.value || '0', 10) || 0);

  if (ap교환) {
    칸.className = 'ap-sum ' + (하자 ? 'take' : 'none');
    칸.textContent = 하자
      ? '차액 청구 ' + 차감.toLocaleString() + '원 — 서명 후 담당자가 전화로 안내한 다음 결제 링크를 발송합니다.'
      : '금액 변동 없음 — 최종승인자 서명 없이 그대로 재발송합니다.';
    return;
  }

  const 줄것 = 하자 ? Math.max(0, ap받은것 - 차감) : ap받은것;
  칸.className = 'ap-sum give';
  칸.textContent = (하자 ? '부분 환불 ' : '전액 환불 ') + 줄것.toLocaleString() + '원'
    + (하자 ? ' (수납 ' + ap받은것.toLocaleString() + '원 − 차감 ' + 차감.toLocaleString() + '원)' : '')
    + ' — 최종승인자가 서명하면 즉시 환불됩니다.';
}

function ap반려(펼까 = true) {
  const f = document.getElementById('apRej');
  if (f) f.style.display = 펼까 ? 'block' : 'none';
}

(function () {
  const 고름 = document.querySelector('#apForm input[name=inspect_result]:checked');
  ap하자(고름 ? 고름.value === 'defect' : false);
})();

/* ── 최종승인자 서명 ─────────────────────────────────────── */
function ap보내기열기(펼까 = true) {
  const f = document.getElementById('apSend');
  if (!f) return;
  f.style.display = 펼까 ? 'block' : 'none';
  if (펼까) ap사람들();
}

function ap최종반려(펼까 = true) {
  const f = document.getElementById('apFinRej');
  if (f) f.style.display = 펼까 ? 'block' : 'none';
}

/* 고를 수 있는 사람은 권한이 있는 사람뿐이다 — 판정은 서버가 한다.
   화면에서 흉내 내면 규칙이 두 벌이 된다. */
let ap사람담김 = null;

async function ap사람들() {
  const sel = document.getElementById('apWho');
  if (!sel || ap사람담김) return;

  try {
    const res = await fetch(@json(route('order-returns.approverList', $r)), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const j = await res.json();
    ap사람담김 = j.approvers || [];

    if (!ap사람담김.length) {
      sel.innerHTML = '<option value="">최종승인자 권한이 있는 사용자가 없습니다</option>';
      return;
    }

    sel.innerHTML = '<option value="">— 선택 —</option>'
      + ap사람담김.map(u => '<option value="' + u.id + '" data-num="' + (u.mobile || '') + '">'
          + u.name + (u.group ? ' (' + u.group + ')' : '')
          + (u.mobile ? '' : ' · 번호 없음') + '</option>').join('');
  } catch (e) {
    sel.innerHTML = '<option value="">목록을 조회하지 못했습니다 — 다시 열어 주십시오</option>';
    ap사람담김 = null;
  }
}

function ap번호채우기() {
  const sel = document.getElementById('apWho');
  const num = document.getElementById('apNum');
  const opt = sel?.selectedOptions?.[0];
  if (num && opt) num.value = opt.dataset.num || '';
}

/* 서명판 — 공개 서명 화면과 같은 방식이다 */
let apCv = null, apCtx = null, ap그리는중 = false, ap칠함 = false;

function ap서명열기(펼까 = true) {
  const pop = document.getElementById('apSignPop');
  if (!pop) return;
  pop.style.display = 펼까 ? 'block' : 'none';
  /* 팝오버가 뜬 뒤라야 캔버스의 크기를 잴 수 있다 — 감춰진 자리에서 세우면
     너비가 0 이라 그어도 아무것도 남지 않는다. */
  if (펼까) { requestAnimationFrame(() => ap캔버스()); }
}

/* ── 창고 검수 요청 원문 ───────────────────────────────────
   카드에는 요청 일시와 출처만 선다. 창고가 실제로 보낸 값(받은 수량ㆍ보낸 수량)은
   웹훅 원문에만 있어 여기서 읽어 온다. */
let ap창고담김 = null;

async function ap창고열기(펼까 = true) {
  const pop = document.getElementById('apWhPop');
  if (!pop) return;

  pop.style.display = 펼까 ? 'block' : 'none';
  if (!펼까) return;

  const 몸 = document.getElementById('apWhBody');

  if (ap창고담김) { 몸.innerHTML = ap창고담김; return; }

  몸.innerHTML = '<div style="padding:16px 0;color:var(--text-muted);">불러오는 중입니다…</div>';

  try {
    const res = await fetch(@json(route('order-returns.inspectionDetail', $r)), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const j = await res.json();

    const 줄 = (라벨, 값, 굵게) => 값 || 값 === 0
      ? '<div class="rt-kv"><span>' + 라벨 + '</span><span'
        + (굵게 ? ' style="font-weight:700;"' : '') + '>' + String(값) + '</span></div>'
      : '';

    let h = '';
    h += 줄('접수번호', j.receipt);
    h += 줄('구분', j.type);
    h += 줄('주문번호', j.order_no);
    h += 줄('고객', j.patient);
    h += 줄('반품 주문번호', j.so_no);
    h += 줄('요청 일시', j.requested_at, true);
    h += 줄('확인 일시', j.seen_at || '—');
    h += 줄('창고 입고', j.arrived_at);
    h += 줄('검수 결과 출처', j.source);
    h += 줄('검수 결과', j.result, true);
    h += 줄('하자 수량', j.defect_qty != null ? j.defect_qty + '개' : '');

    /* 우리 표에 담는 칸이 없는 값 — 원문에서만 읽힌다 */
    if (j.received_qty != null || j.expected_qty != null) {
      h += 줄('창고 수량', (j.received_qty ?? '?') + ' / ' + (j.expected_qty ?? '?')
                            + ' (받은 수량 / 보낸 수량)');
    }
    if (j.defect_note) {
      h += '<div class="rt-kv rt-note"><span>하자 내용</span><span>' + j.defect_note + '</span></div>';
    }
    h += 줄('창고 상태', j.pl3_status);
    if (j.pl3_note) {
      h += '<div class="rt-kv rt-note"><span>창고 검수 비고</span><span>' + j.pl3_note
         + (j.pl3_note_at ? '<span class="rt-note-at">' + j.pl3_note_at + '</span>' : '')
         + '</span></div>';
    }

    if ((j.events || []).length) {
      h += '<div style="margin-top:10px;font-size:12px;font-weight:700;color:var(--text-muted);">'
         + '창고가 보낸 사건</div>';
      j.events.forEach(e => {
        h += '<div class="rt-kv"><span>' + (e.at || '') + '</span><span>'
           + e.event + (e.label ? ' · ' + e.label : '') + '</span></div>';
      });
    }

    if (!h) { h = '<div style="padding:16px 0;color:var(--text-muted);">창고가 보낸 내용이 없습니다.</div>'; }

    ap창고담김 = h;
    몸.innerHTML = h;
  } catch (e) {
    몸.innerHTML = '<div style="padding:16px 0;color:var(--danger);">'
                 + '내용을 조회하지 못했습니다 — 다시 열어 주십시오.</div>';
  }
}

function ap캔버스() {
  apCv = document.getElementById('apCanvas');
  if (!apCv) return;

  const r = apCv.getBoundingClientRect();
  const d = window.devicePixelRatio || 1;
  apCv.width  = Math.round(r.width  * d);
  apCv.height = Math.round(r.height * d);
  apCtx = apCv.getContext('2d');
  apCtx.scale(d, d);
  apCtx.lineWidth = 2.2;
  apCtx.lineCap = 'round';
  apCtx.lineJoin = 'round';
  apCtx.strokeStyle = '#111827';
  ap칠함 = false;
  ap서명셈();

  if (apCv.dataset.bound) return;
  apCv.dataset.bound = '1';

  const 자리 = e => {
    const b = apCv.getBoundingClientRect();
    const p = e.touches ? e.touches[0] : e;
    return { x: p.clientX - b.left, y: p.clientY - b.top };
  };
  const 시작 = e => { e.preventDefault(); ap그리는중 = true; const p = 자리(e); apCtx.beginPath(); apCtx.moveTo(p.x, p.y); };
  const 이동 = e => { if (!ap그리는중) return; e.preventDefault(); const p = 자리(e); apCtx.lineTo(p.x, p.y); apCtx.stroke(); ap칠함 = true; ap서명셈(); };
  const 끝   = () => { ap그리는중 = false; };

  apCv.addEventListener('mousedown', 시작);
  apCv.addEventListener('mousemove', 이동);
  window.addEventListener('mouseup', 끝);
  apCv.addEventListener('touchstart', 시작, { passive: false });
  apCv.addEventListener('touchmove',  이동, { passive: false });
  apCv.addEventListener('touchend', 끝);
}

function ap지우기() {
  if (!apCtx) return;
  apCtx.clearRect(0, 0, apCv.width, apCv.height);
  ap칠함 = false;
  ap서명셈();
}

/* 무엇이 남았는지 늘 적어 둔다 — 단추가 잠겨 있으면 까닭을 몰라 멈춰 선다 */
function ap서명셈() {
  const go = document.getElementById('apSigGo');
  const why = document.getElementById('apSigWhy');
  if (go) go.disabled = !ap칠함;
  if (why) why.textContent = ap칠함 ? '서명하면 즉시 처리됩니다.' : '서명란에 서명해 주십시오.';
}

/* 돈이 움직이는 자리라 두 번 누르면 안 된다. 토스가 늦으면 응답이 몇 초 걸리는데,
   그 동안 아무 일도 없는 것처럼 보여 다시 누른다.

   단추를 disabled 로 잠그지 않는다 — requestSubmit 보다 먼저 잠그면 보내기 자체가
   막힌다. 깃발 하나로 막고, 프로그래스 창이 화면을 덮어 두 번째 누름을 받는다. */
let ap보내는중 = false;

function ap서명보내기(btn) {
  if (ap보내는중) return false;
  if (!ap칠함) { ap서명셈(); return false; }

  /* 글에 숫자를 섞지 않는다 — 메시지 관리 사전은 **실행 때의 글**로 찾는다.
     숫자를 섞으면 등록된 줄과 글자가 달라 영영 찾지 못하고, 담당자가 고쳐도
     화면은 그대로다. 금액은 바로 위 칸에 크게 적혀 있다. */
  const 물음 = '서명하시겠습니까? 서명하는 즉시 환불 또는 차액 청구가 처리됩니다.';

  const 가자 = () => {
    ap보내는중 = true;
    document.getElementById('apSigData').value = apCv.toDataURL('image/png');
    if (window.ceProgress) window.ceProgress('결재를 처리하고 있습니다');
    const f = btn.form || btn.closest('form');
    if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }
  };

  /* 화면의 물음창을 쓴다 — 다른 단추와 같은 모양이어야 한다 */
  if (window.ceConfirm) {
    window.ceConfirm(물음).then(ok => { if (ok) 가자(); });
    return false;
  }

  if (!confirm(물음)) return false;
  가자();
  return false;
}
</script>
