<?php
// app/Services/TossPayments/PaymentCancelService.php
// 토스페이먼츠 결제 취소 — 카드ㆍ가상계좌를 무른다

namespace App\Services\TossPayments;

use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Support\Facades\Log;

/**
 * 낸 돈을 돌려준다.
 *
 * 여태 CE 는 되돌릴 때 세금계산서ㆍ현금영수증ㆍ공단청구만 물렸다. 정작 고객이 낸
 * 돈은 아무도 건드리지 않아, 담당자가 토스 콘솔에 따로 들어가 취소하고 그 자취를
 * 손으로 옮겨 적었다. 두 곳에 나눠 적으면 언젠가 갈린다 — 실제로 물린 금액과
 * 화면에 적힌 금액이 다른 건이 남는다.
 *
 * **사람이 누를 때만 돈다.** 단계를 옮기는 김에 저절로 부르지 않는다 — 돈을
 * 돌려주는 일은 되돌릴 수 없고, 시험 키에서 운영 키로 바뀌는 순간 진짜 돈이 움직인다.
 *
 * 부분 취소도 같은 자리에서 한다. 토스는 한 결제를 여러 번 나누어 무를 수 있어
 * (PARTIAL_CANCELED), 부분 반품이 두 번 일어나도 그때마다 그만큼만 돌려준다.
 */
class PaymentCancelService extends TossClient
{
    /**
     * 주문의 결제를 무른다.
     *
     * @param  int|null  $amount  부분 취소 금액. 비우면 남은 전액.
     * @return array{ok:bool, message:string, status?:string, canceled?:int, payment?:TossPayment}
     */
    public function cancel(Order $order, string $reason, ?int $amount = null, ?string $refundAccount = null): array
    {
        $payment = $this->paymentOf($order);

        if (!$payment) {
            return ['ok' => false, 'message' => '토스 결제 자취가 없습니다 — 무를 것이 없습니다.'];
        }

        if (!$payment->payment_key) {
            return ['ok' => false, 'message' => '결제키가 없습니다 — 아직 결제가 끝나지 않은 건입니다.'];
        }

        /* 이미 다 무른 건을 또 부르면 토스가 거절한다. 거절 자체는 안전하지만,
           담당자에게는 「왜 안 되는지」가 아니라 「이미 됐다」가 맞는 말이다.

           상태만 보면 놓친다. 부분 취소를 두 번 해 전액을 채우면 토스는 상태를
           PARTIAL_CANCELED 로 둔 채 남은 금액만 0 으로 내린다 — 그때 또 누르면
           「취소 할 수 없는 금액 입니다」라는 토스 말이 담당자에게 그대로 갔다.
           남은 금액으로 가린다. */
        $done = $payment->status === 'CANCELED'
             || ($payment->cancel_amount !== null && (int) $payment->cancel_amount >= (int) $payment->amount);

        if ($done) {
            return ['ok' => true, 'message' => '이미 전액 취소된 결제입니다.', 'status' => $payment->status];
        }

        $body = ['cancelReason' => mb_substr($reason, 0, 200)];

        if ($amount !== null) {
            if ($amount <= 0) {
                return ['ok' => false, 'message' => '취소 금액은 0보다 커야 합니다.'];
            }
            $body['cancelAmount'] = $amount;
        }

        /* 가상계좌로 받은 돈은 돌려줄 계좌를 함께 보내야 한다 — 카드처럼 왔던 길로
           되돌아가지 않기 때문이다. 계좌가 없으면 토스가 거절하므로 미리 막는다. */
        if ($payment->method_is_virtual_account ?? ($payment->method === '가상계좌')) {
            if (!$refundAccount) {
                return ['ok' => false, 'message' => '가상계좌로 받은 돈은 돌려줄 계좌(은행ㆍ번호ㆍ예금주)가 있어야 무를 수 있습니다.'];
            }
            $body['refundReceiveAccount'] = $refundAccount;
        }

        try {
            $res = $this->post('/v1/payments/' . $payment->payment_key . '/cancel', $body);
        } catch (TossApiException $e) {
            Log::warning('[Toss] 결제 취소 실패', [
                'order' => $order->order_number, 'key' => $payment->payment_key,
                'amount' => $amount, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        /* 응답에 취소 내역이 배열로 온다. 마지막 줄이 방금 무른 것이다. */
        $cancels  = $res['cancels'] ?? [];
        $last     = $cancels ? end($cancels) : null;
        $canceled = (int) ($last['cancelAmount'] ?? $amount ?? $payment->amount);

        $payment->forceFill([
            'status'         => $res['status'] ?? 'CANCELED',
            'canceled_at'    => now(),
            'cancel_amount'  => (int) collect($cancels)->sum('cancelAmount') ?: $canceled,
            'cancel_reason'  => mb_substr($reason, 0, 200),
            'raw_response'   => $res,   // 모델이 배열로 캐스팅한다
        ])->save();

        Log::info('[Toss] 결제 취소', [
            'order' => $order->order_number, 'status' => $res['status'] ?? '?',
            'canceled' => $canceled,
        ]);

        return [
            'ok'       => true,
            'message'  => sprintf('%s원을 돌려주었습니다 (%s).',
                number_format($canceled),
                self::STATUS_LABELS[$res['status'] ?? ''][0] ?? ($res['status'] ?? '')),
            'status'   => $res['status'] ?? null,
            'canceled' => $canceled,
            'payment'  => $payment->fresh(),
        ];
    }

    /**
     * 지금 무를 수 있는 금액.
     *
     * 토스에 물어 남은 액수를 본다 — 우리 표만 믿으면 다른 데서 무른 것을 모른다.
     */
    public function cancelable(Order $order): array
    {
        $payment = $this->paymentOf($order);

        if (!$payment?->payment_key) {
            return ['ok' => false, 'message' => '무를 결제가 없습니다.', 'balance' => 0];
        }

        try {
            $res = $this->get('/v1/payments/' . $payment->payment_key);
        } catch (TossApiException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'balance' => 0];
        }

        $total    = (int) ($res['totalAmount'] ?? 0);
        $canceled = (int) collect($res['cancels'] ?? [])->sum('cancelAmount');

        return [
            'ok'        => true,
            'status'    => $res['status'] ?? null,
            'label'     => self::STATUS_LABELS[$res['status'] ?? ''][0] ?? ($res['status'] ?? ''),
            'method'    => $res['method'] ?? null,
            'total'     => $total,
            'canceled'  => $canceled,
            'balance'   => max(0, $total - $canceled),
            'message'   => '',
        ];
    }

    /** 이 주문의 결제 자취 — 가장 마지막 것 */
    private function paymentOf(Order $order): ?TossPayment
    {
        return $order->tossPayment
            ?? TossPayment::where('order_id', $order->id)->latest('id')->first();
    }
}
