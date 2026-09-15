<?php

namespace App\Services\TossPayments;

use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\TossPayment;
use Illuminate\Support\Facades\Log;

/**
 * 토스에서 결제가 취소된 것을 우리 쪽에 반영한다 (2026-09-16 지시).
 *
 * 여태 웹훅은 DONE 만 다루고 취소는 그냥 버렸다. 주석은 「되돌리는 일은 담당자의 손을
 * 거쳐 도는 일이라 여기서 건드리지 않는다」고 적었는데, **담당자에게 알리는 자리가
 * 없었다** — 가상계좌는 서버 로그뿐이고 카드는 그마저 없었다.
 *
 * 그래서 환자는 돈을 돌려받았는데 화면은 「결제완료」라 말하고, 담당자는 다시 청구할
 * 수도 없었다(다받았나() 가 참이라 결제 링크 재발송이 막힌다).
 *
 * 하는 일은 **사실을 옮겨 적는 것**뿐이다. 판단은 우리가 하지 않고 토스가 준 값을
 * 그대로 쓴다.
 *
 *   · 결제 줄의 상태와 취소 금액을 적는다
 *   · 전액 취소면 「받은 것」 표시를 거둔다 — 남겨 두면 받은금액()이 계속 전액을
 *     돌려주어, 화면도 재발송 차단도 풀리지 않는다
 *   · 살아 있던 결제 링크를 해지한다 — 취소된 건의 링크로 또 결제되면 안 된다
 *   · 주문 이력에 남기고 담당자에게 알린다
 *
 * **증빙은 되돌리지 않는다.** 국세청 실신고라 취소 신고가 또 나간다 — 담당자가
 * 「세금계산서 취소」ㆍ「현금영수증 취소」로 직접 누르는 지금 규칙을 지키고, 알림에
 * 그 사실을 적어 잊지 않게 한다.
 */
class PaymentCancelSync
{
    /**
     * 토스가 준 결제 내용으로 우리 쪽을 맞춘다.
     *
     * @param  array $저쪽 토스에 재조회한 결제 한 벌 (status·cancels·totalAmount)
     * @return array{changed:bool, message:string}
     */
    public function 맞추기(TossPayment $결제, array $저쪽, string $까닭 = ''): array
    {
        $상태 = (string) ($저쪽['status'] ?? '');

        if (! in_array($상태, ['CANCELED', 'PARTIAL_CANCELED'], true)) {
            return ['changed' => false, 'message' => ''];
        }

        $무른것 = (int) collect($저쪽['cancels'] ?? [])->sum('cancelAmount');
        $전체   = (int) ($저쪽['totalAmount'] ?? $결제->amount);
        $전액인가 = $무른것 >= $전체 && $전체 > 0;

        /* 이미 같은 값으로 적혀 있으면 두 번 알리지 않는다 — 웹훅은 다시 온다. */
        if ($결제->status === $상태 && (int) $결제->cancel_amount === $무른것) {
            return ['changed' => false, 'message' => '이미 반영된 취소입니다.'];
        }

        $결제->forceFill([
            'status'        => $상태,
            'cancel_amount' => $무른것,
            'canceled_at'   => $결제->canceled_at ?? now(),
            'raw_response'  => $저쪽,
        ])->save();

        $order = $결제->order;

        if (! $order) {
            return ['changed' => true, 'message' => '이어진 주문이 없습니다.'];
        }

        $말 = $this->주문맞추기($order, $무른것, $전액인가, $까닭);

        return ['changed' => true, 'message' => $말];
    }

    /** 주문 쪽 — 받은 표시와 결제 링크를 사실에 맞춘다 */
    private function 주문맞추기(Order $order, int $무른것, bool $전액인가, string $까닭): string
    {
        $말 = [];

        /* 전액 취소면 「받은 것」을 거둔다.

           부분취소는 거두지 않는다 — 남은 금액이 있으면 그만큼은 받은 것이 맞고,
           모자란 만큼만 다시 청구하면 된다. */
        if ($전액인가 && $order->deposit_confirmed_at) {
            $order->forceFill([
                'deposit_confirmed_at' => null,
                'deposit_confirmed_by' => null,
                'deposit_amount'       => null,
            ])->save();

            $말[] = '입금 확인을 거뒀습니다';
        }

        /* 결제된 링크와 아직 살아 있는 링크를 함께 닫는다 — 취소된 건의 링크로 또
           결제되면 안 된다. 전액 취소일 때만 닫는다(부분취소는 남은 청구가 있다). */
        if ($전액인가) {
            $닫은수 = PaymentLink::where('order_id', $order->id)
                ->whereIn('status', ['sent', 'paid'])
                ->update(['status' => 'cancelled']);

            if ($닫은수) {
                $말[] = "결제 링크 {$닫은수}건을 해지했습니다";
            }
        }

        $증빙 = [];
        if ($order->tax_invoice_status === 'issued')  { $증빙[] = '세금계산서'; }
        if ($order->cash_receipt_status === 'issued') { $증빙[] = '현금영수증'; }

        if ($증빙) {
            $말[] = implode('ㆍ', $증빙) . '이(가) 발행된 건입니다 — 자동으로 취소되지 않으니 직접 취소해 주십시오';
        }

        $알림 = sprintf('결제가 취소되었습니다 (%s원%s)%s',
            number_format($무른것),
            $전액인가 ? ' · 전액' : ' · 부분',
            $까닭 ? " — {$까닭}" : '');

        $전체말 = $알림 . ($말 ? '. ' . implode('. ', $말) . '.' : '.');

        activity()->performedOn($order)->log($전체말);

        Log::warning('[Toss] 결제 취소를 반영했습니다', [
            'order'  => $order->order_number,
            'amount' => $무른것,
            'full'   => $전액인가,
            'note'   => $전체말,
        ]);

        $this->알리기($order, $전체말);

        return $전체말;
    }

    /**
     * 담당자에게 알린다 — 로그만으로는 아무도 모른다.
     *
     * 창고 소식이 도는 길을 그대로 쓴다. 알리지 못해도 반영은 이미 끝난 것이라
     * 되돌리지 않는다.
     */
    private function 알리기(Order $order, string $말): void
    {
        try {
            event(new \App\Events\WithworksStatusChanged(
                event: 'toss.cancelled',
                title: '결제 취소',
                body:  $order->order_number . ' — ' . $말,
                url:   null,
                tone:  'danger',
            ));
        } catch (\Throwable $e) {
            Log::info('[Toss] 결제 취소 알림을 보내지 못했습니다', ['error' => $e->getMessage()]);
        }
    }
}
