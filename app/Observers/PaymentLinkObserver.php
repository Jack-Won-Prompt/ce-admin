<?php

namespace App\Observers;

use App\Models\PaymentEvent;
use App\Models\PaymentLink;

/**
 * 결제 요청의 상태가 바뀌는 그 순간에 걸음을 적는다 (2026-09-30 지시).
 *
 * 결제 링크의 상태를 바꾸는 자리가 열한 군데다 — 화면의 승인, 토스 웹훅, 주문 정정,
 * 주문 취소, 반품 환불, 기한 넘김 따위. 자리마다 「적어 두기」를 넣으면 반드시
 * 빠뜨리고, 새로 생기는 자리는 애초에 모른다.
 *
 * **상태가 바뀌는 길목은 하나다** — 표에 쓰는 순간이다. 여기서 한 번 적는다.
 */
class PaymentLinkObserver
{
    /** 링크를 세운 순간 — 아직 보내기 전일 수 있다 */
    public function created(PaymentLink $link): void
    {
        if ($link->sent_at) {
            PaymentEvent::적기($link, PaymentEvent::KIND_SENT);
        }
    }

    public function updated(PaymentLink $link): void
    {
        /* 보낸 때가 이제 적혔으면 보낸 걸음이다 */
        if ($link->wasChanged('sent_at') && $link->sent_at) {
            PaymentEvent::적기($link, PaymentEvent::KIND_SENT);
        }

        if (! $link->wasChanged('status')) {
            return;
        }

        $이전 = $link->getOriginal('status');

        /* 받기 전에 거둔 것인가, 받은 뒤에 돌려준 것인가.

           상태값만 보면 갈리지 않는다 — 정정은 받은 링크도 `cancelled` 로 거둘 수
           있기 때문이다. **받은 자취(paid_at)가 있으면 환불**이다. 그 갈림은
           PaymentEvent::적기() 가 금액의 부호로 함께 적는다. */
        $갈래 = match ($link->status) {
            'paid'      => PaymentEvent::KIND_PAID,
            'refunded'  => PaymentEvent::KIND_REFUNDED,
            'cancelled' => PaymentEvent::KIND_CANCELLED,
            'failed'    => PaymentEvent::KIND_FAILED,
            'expired'   => PaymentEvent::KIND_EXPIRED,
            'sent'      => PaymentEvent::KIND_SENT,
            default     => null,
        };

        if (! $갈래) {
            return;
        }

        PaymentEvent::적기($link, $갈래, "{$이전} → {$link->status}");
    }
}
