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
     * @param  array|null  $refundAccount  가상계좌로 받은 돈을 돌려줄 계좌
     *         (['bank'=>…, 'accountNumber'=>…, 'holderName'=>…]).
     *         **꼴을 고쳤다** (2026-10-07) — 여태 ?string 으로 받았는데 두 호출자가
     *         배열을 넘긴다(OrderReturnController:1022ㆍReturnFinalApproval:150의
     *         돌려줄계좌(): ?array). 그 길로 들어오면 TypeError 로 죽는다.
     * @return array{ok:bool, message:string, status?:string, canceled?:int, payment?:TossPayment}
     */
    public function cancel(Order $order, string $reason, ?int $amount = null, ?array $refundAccount = null): array
    {
        $payment = $this->paymentOf($order);

        if (!$payment) {
            return ['ok' => false, 'message' => '토스 결제 내역이 없습니다 — 취소할 대상이 없습니다.'];
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

        /* 시험 환경 자동 결제는 토스에 없다 — 우리 장부에서만 무른다
           (2026-09-17 시험에서 드러남).

           config('toss.env') === 'test' 인 동안 결제 링크를 열면 토스를 부르지 않고
           낸 것으로 적는다(PaymentLinkController::시험승인). 그 결제키는 TEST_ 로
           시작하고 토스에는 없는 번호라, 무르려 하면 [NOT_FOUND_PAYMENT] 존재하지
           않는 결제 정보 로 거절당했다. 그래서 주문 정정이 「받은 돈을 무르지
           못했습니다」로 끝나고, 주문은 취소인데 입금 금액은 그대로 남았다.

           돌려줄 돈이 애초에 오간 적이 없으므로 저쪽에 청할 것이 없다. 우리 표만
           무른 것으로 닫으면 뒤따르는 재청구ㆍ증빙이 실제 결제와 같은 길로 간다. */
        if (str_starts_with((string) $payment->payment_key, 'TEST_')) {
            $무른금액 = $amount ?? (int) $payment->amount;

            /* 부분 취소는 그 몫만 쌓는다 (2026-09-18 운영 시험에서 드러남).

               여태 얼마를 무르든 취소 금액에 결제 금액을 통째로 적고 상태도
               CANCELED 로 닫았다. 810,000원 가운데 300,000원만 돌려준 건이 장부에는
               전액 취소로 남아, 화면이 알린 금액과 표가 어긋났다. 남은 610,000원은
               다시 무를 수도 없었다 — 이미 다 무른 것으로 보이기 때문이다.

               실제 토스가 하는 셈과 같게 둔다: 무른 몫을 더해 쌓고, 전액을 채웠을
               때만 CANCELED 로 닫는다(그 전에는 PARTIAL_CANCELED). */
            $쌓인금액 = (int) $payment->cancel_amount + $무른금액;
            $전액     = (int) $payment->amount;

            $payment->forceFill([
                'status'        => $쌓인금액 >= $전액 ? 'CANCELED' : 'PARTIAL_CANCELED',
                'canceled_at'   => now(),
                'cancel_amount' => min($쌓인금액, $전액),
                'cancel_reason' => mb_substr($reason, 0, 200),
            ])->save();

            Log::info('[Toss] 시험 자동 결제를 장부에서만 취소', [
                'order' => $order->order_number, 'key' => $payment->payment_key,
                'amount' => $무른금액,
            ]);

            $this->결제링크를환불로($payment, $order, $무른금액);

            activity()->performedOn($order)->log(sprintf(
                '시험 환경 자동 결제 취소 — %s원 (토스에 요청하지 않았습니다)',
                number_format($무른금액)
            ));

            return [
                'ok'       => true,
                'message'  => sprintf('%s원을 환불 처리했습니다 (시험 환경 자동 결제라 PG를 호출하지 않았습니다).',
                    number_format($무른금액)),
                'status'   => 'CANCELED',
                'canceled' => $무른금액,
                'payment'  => $payment->fresh(),
            ];
        }

        $body = ['cancelReason' => mb_substr($reason, 0, 200)];

        if ($amount !== null) {
            if ($amount <= 0) {
                return ['ok' => false, 'message' => '취소 금액은 0보다 커야 합니다.'];
            }
            $body['cancelAmount'] = $amount;
        }

        /* 가상계좌로 **받은** 돈은 돌려줄 계좌를 함께 보내야 한다 — 카드처럼 왔던 길로
           되돌아가지 않기 때문이다. 계좌가 없으면 토스가 거절하므로 미리 막는다.

           **입금 전에는 묻지 않는다** (2026-10-07 지시 · 토스 문서 확인). 아직 들어온
           돈이 없으니 돌려줄 것도 없고, 토스도 그 값을 요구하지 않는다. 결제전송에서
           수단을 바꿀 때 발급해 둔 계좌를 닫는 길이 이 자리를 지나는데, 계좌를 묻고
           막으면 닫을 수가 없다.

           **조건도 고쳤다.** 여태 이렇게 적혀 있었다 —

             $payment->method_is_virtual_account ?? ($payment->method === '가상계좌')

           그런 속성은 없고(없는 속성은 null 이라 ?? 가 오른쪽으로 넘어간다), 저장값은
           `VIRTUAL_ACCOUNT` 다 — 사람이 읽는 이름(`가상계좌`)은 method_label 쪽이다.
           그래서 이 분기는 **한 번도 참이 된 적이 없고**, 호출자가 환불 계좌를 넘겨도
           본문에 실리지 않았다. 가상계좌 환불을 토스가 거절해 온 자리다. */
        $가상계좌 = (string) $payment->method === 'VIRTUAL_ACCOUNT';

        if ($가상계좌 && $payment->deposited_at) {
            if (!$refundAccount) {
                return ['ok' => false, 'message' => '가상계좌 입금액은 환불 계좌(은행ㆍ계좌번호ㆍ예금주)가 등록되어 있어야 환불할 수 있습니다.'];
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

        $this->결제링크를환불로($payment, $order, $canceled);

        Log::info('[Toss] 결제 취소', [
            'order' => $order->order_number, 'status' => $res['status'] ?? '?',
            'canceled' => $canceled,
        ]);

        return [
            'ok'       => true,
            'message'  => sprintf('%s원을 환불 처리했습니다 (%s).',
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
            return ['ok' => false, 'message' => '취소할 결제가 없습니다.', 'balance' => 0];
        }

        /* 시험 자동 결제는 토스에 없다 — 물어도 없는 번호라 거절당한다.
           우리 표에 적힌 것으로 답한다 (cancel() 과 같은 갈래). */
        if (str_starts_with((string) $payment->payment_key, 'TEST_')) {
            $총액 = (int) $payment->amount;
            $무른것 = (int) ($payment->cancel_amount ?? 0);

            return [
                'ok'       => true,
                'status'   => $payment->status,
                'label'    => self::STATUS_LABELS[$payment->status ?? ''][0] ?? ($payment->status ?? ''),
                'method'   => $payment->method,
                'total'    => $총액,
                'canceled' => $무른것,
                'balance'  => max(0, $총액 - $무른것),
                'message'  => '시험 환경 자동 결제입니다 — 토스에 요청하지 않습니다.',
            ];
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
    /**
     * 받았다가 돌려준 결제 링크를 「환불」로 옮긴다 (2026-09-28 지시).
     *
     * 여태 이 자리가 없어 환불한 링크도 `paid` 로 남았다. 현금ㆍ카드영수증 화면은
     * `paid` 를 더해 세므로 받지 않은 돈이 합계에 들어갔다 — 27,000원을 물리고
     * 22,500원을 다시 받은 건이 49,500원으로 섰다.
     *
     * **방금 무른 그 결제의 링크만** 옮긴다(payment_key 로 가린다). 같은 주문의 다른
     * 링크 — 새로 보낸 것, 아직 안 낸 것, 앞서 받았다 돌려준 것 — 은 건드리지 않는다.
     *
     * 값은 `toss_payments` 로 가리지 않는다. 그 표는 **한 주문에 한 줄**이라 재결제가
     * 덮어쓴다(2026-09-23). 27,000원을 물리고 22,500원을 다시 받은 건에서 그 줄의
     * payment_key 는 **지금 들고 있는 22,500원짜리**다 — 그것으로 가리면 환불된 줄이
     * 아니라 살아 있는 줄에 「환불」이 찍힌다. 여기서는 방금 무른 결제를 손에 들고
     * 있으므로 그 열쇠를 그대로 쓴다.
     *
     * **이번에 무른 몫이 그 링크를 덮을 때만** 옮긴다. 일부만 돌려준 것을 적을 자리가
     * 링크에는 없다 — 그 건은 승인으로 두고 환불 내역이 따로 남는다.
     */
    private function 결제링크를환불로(
        \App\Models\TossPayment $payment,
        \App\Models\Order $order,
        int $무른금액,
    ): void {
        if (! $payment->payment_key) {
            return;
        }

        $링크 = \App\Models\PaymentLink::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_key', $payment->payment_key)
            ->first();

        if (! $링크 || $무른금액 < (int) $링크->amount) {
            return;
        }

        $링크->forceFill(['status' => 'refunded'])->save();

        Log::info('[Toss] 결제 링크를 환불로 옮김', [
            'order' => $order->order_number, 'link' => $링크->id, 'amount' => $링크->amount,
        ]);
    }
}
