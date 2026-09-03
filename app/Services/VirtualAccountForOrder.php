<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\TossPayment;
use App\Services\TossPayments\TossApiException;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Support\Facades\Log;

/**
 * 주문의 가상계좌 — 발급하고, 거래처에 적어 두고, 문자로 보낸다(2026-09-03 지시).
 *
 * 여태 가상계좌는 정산/회계 화면에서만 발급할 수 있었다. 주문을 연계할 때 보내는
 * 안내는 설정 하나로 정해져 어느 환자든 같은 계좌가 나갔다.
 *
 * 「되살린다」는 것에 대하여 — 토스 가상계좌는 금액과 유효시간(72시간)에 묶여
 * 발급된다. 그래서 앞 주문의 계좌를 다음 주문에 그대로 쓸 수 없다. 금액이 다르면
 * 입금이 맞춰지지 않고, 기한이 지나면 그 계좌로 넣어도 들어가지 않는다.
 *
 * 되살리는 것은 「같은 주문의, 아직 살아 있는 계좌」다. 고객이 문자를 지우고
 * 「계좌번호가 뭐였죠」라 물을 때 새로 발급하면, 앞 계좌로 들어온 돈이 뜬다.
 * 그래서 있으면 그것을 다시 보낸다.
 *
 * 사람에게 남는 것은 결제 방식이다 — 가상계좌로 내는 사람은 다음 주문에서도
 * 가상계좌로 열린다.
 */
final class VirtualAccountForOrder
{
    public function __construct(
        private VirtualAccountService $va,
        private PaymentLinkService $links,
    ) {}

    /**
     * 가상계좌를 마련해 문자로 보낸다.
     *
     * @return array{sent: bool, reused: bool, message: string, payment: ?TossPayment}
     */
    public function issueAndNotify(Order $order): array
    {
        $order->loadMissing('patient');

        if ((int) $order->expectedDeposit() <= 0) {
            return ['sent' => false, 'reused' => false, 'payment' => null,
                    'message' => '본인부담금이 없어 가상계좌를 발급하지 않았습니다.'];
        }

        $reused  = false;
        $payment = $this->living($order);

        if ($payment) {
            $reused = true;
        } else {
            if (! $this->va->isConfigured()) {
                return ['sent' => false, 'reused' => false, 'payment' => null,
                        'message' => '가상계좌 설정이 없어 발급하지 못했습니다 — 설정 › 토스페이먼츠를 확인해 주십시오.'];
            }

            try {
                $payment = $this->va->issueVirtualAccount($order);
            } catch (TossApiException|\Throwable $e) {
                Log::error('[가상계좌] 발급 실패', ['order' => $order->order_number, 'error' => $e->getMessage()]);

                /* 주문은 살린다. 창고에는 이미 나간 뒤라 되돌리면 더 나쁘다 —
                   못 보냈다는 것만 알리고 정산/회계에서 다시 하게 둔다. */
                return ['sent' => false, 'reused' => false, 'payment' => null,
                        'message' => '가상계좌를 발급하지 못했습니다 — 정산/회계에서 다시 시도해 주십시오. (' . $e->getMessage() . ')'];
            }
        }

        $this->remember($order, $payment);

        $res = $this->notify($order, $payment);

        return [
            'sent'    => (bool) ($res['sent'] ?? false),
            'reused'  => $reused,
            'payment' => $payment,
            'message' => ($reused ? '이미 발급된 계좌를 다시 보냈습니다 — ' : '가상계좌를 발급했습니다 — ')
                       . ($res['message'] ?? ''),
        ];
    }

    /**
     * 아직 쓸 수 있는 계좌 — 없으면 null.
     *
     * 기한이 지났거나 금액이 달라졌으면 쓸 수 없다. 금액이 달라지는 일이 있다 —
     * 검수를 마친 뒤 수량을 고치면 본인부담이 바뀐다.
     */
    private function living(Order $order): ?TossPayment
    {
        $p = TossPayment::where('order_id', $order->id)
            ->where('method', 'VIRTUAL_ACCOUNT')
            ->whereNotNull('account_number')
            ->latest('id')
            ->first();

        if (! $p || trim((string) $p->account_number) === '') {
            return null;
        }

        if ($p->deposited_at) {
            return null;                                  // 이미 들어왔다
        }

        if ($p->due_date && $p->due_date->isPast()) {
            return null;                                  // 기한이 지났다
        }

        if ((int) $p->amount !== (int) round($order->expectedDeposit())) {
            return null;                                  // 받을 돈이 달라졌다
        }

        return $p;
    }

    /** 거래처에 적어 둔다 — 결제 방식과, 마지막으로 발급한 계좌 */
    private function remember(Order $order, TossPayment $p): void
    {
        $order->patient?->forceFill([
            'pay_method' => PaymentLink::METHOD_VIRTUAL,
            'va_bank'    => $p->bank,
            'va_account' => $p->account_number,
            'va_holder'  => $p->customer_name,
            'va_due_at'  => $p->due_date,
            'va_order_id' => $order->id,
        ])->save();
    }

    /** 계좌를 문자로 보낸다 — 문구는 결제전송이 쓰는 것과 같다 */
    private function notify(Order $order, TossPayment $p): array
    {
        /* 문자를 보내는 길이 PaymentLink 를 거친다. 이력이 그 표에 쌓여야 담당자가
           「무엇이 언제 나갔나」를 한자리에서 본다. */
        $link = PaymentLink::create([
            'order_id'   => $order->id,
            'token'      => \Illuminate\Support\Str::random(40),
            'method'     => PaymentLink::METHOD_VIRTUAL,
            'amount'     => (int) $p->amount,
            'status'     => 'sent',
            'receiver'   => preg_replace('/\D/', '', (string) ($order->patient?->mobile ?? '')),
            'sent_at'    => now(),
            'expires_at' => $p->due_date,
            'created_by' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return $this->links->sendVirtualAccount($link, [
            'bank'          => $p->bank,
            'bankCode'      => $p->bank,
            'accountNumber' => $p->account_number,
            'customerName'  => $p->customer_name,
            'dueDate'       => $p->due_date?->toIso8601String(),
        ]);
    }
}
