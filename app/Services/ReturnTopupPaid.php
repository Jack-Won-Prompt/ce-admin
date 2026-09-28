<?php

namespace App\Services;

use App\Models\OrderReturn;
use App\Models\PaymentLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 교환 차액이 실제로 들어왔다 (2026-09-28 지시).
 *
 * 「금액이 바뀐 건은 고객이 결제해서 웹훅으로 전달받으면 증빙을 전부 다시 발행한다.」
 *
 * 차액 결제는 **toss_payments 에 담지 않는다.** 그 표는 한 주문 한 줄이고 order_id 가
 * 유일이라, 차액이 들어오면 원 결제 줄을 덮어쓴다 — 37,500원 결제가 4,500원으로 바뀌고
 * 원 결제키가 사라져, 받은 돈이 4,500원으로 읽히고 원 결제를 무를 수도 없게 된다.
 * 차액은 접수에 따로 적어 원 결제를 건드리지 않는다.
 *
 * **한 번만 돈다.** 결제 화면은 새로고침으로 같은 자리에 두 번 들어올 수 있어, 두 번
 * 돌면 본인부담금이 두 번 올라간다.
 */
class ReturnTopupPaid
{
    /**
     * @return string 사람이 읽을 한 줄
     */
    public function 받음(OrderReturn $return, PaymentLink $link, array $res = []): string
    {
        if ($return->topup_paid_at) {
            return '이미 차액 입금을 처리한 건입니다.';
        }

        $order = $return->order;

        if (! $order) {
            return '! 주문을 찾을 수 없습니다.';
        }

        $몫 = (int) ($res['totalAmount'] ?? $link->amount);

        if ($몫 <= 0) {
            return '! 들어온 금액이 0원입니다.';
        }

        /* 원 주문에 바뀐 내역을 반영한다.

           증빙이 읽는 금액은 본인부담금 + 공단부담금이다(DepositAutoIssue). 차액은
           고객이 더 낸 돈이므로 본인부담금에 더한다. 주문 금액도 함께 올린다 —
           목록ㆍ정산이 그 칸을 본다.

           **여기서 한 번만 올린다.** 링크를 낼 때 올리지 않은 까닭이 이것이다 —
           고객이 내지 않으면 금액만 올라 있는 주문이 남는다. */
        DB::transaction(function () use ($return, $order, $link, $res, $몫) {
            $order->forceFill([
                'patient_copay' => (int) $order->patient_copay + $몫,
                'total_amount'  => (int) $order->total_amount  + $몫,
            ])->save();

            $return->forceFill([
                'topup_paid_at'     => now(),
                'topup_payment_key' => $res['paymentKey'] ?? $link->payment_key,
                'topup_amount'      => $몫,
                'refund_stage'      => 'topup_paid',
            ])->save();

            $link->forceFill(['status' => 'paid'])->save();

            \App\Models\OrderReturnLog::create([
                'order_return_id' => $return->id,
                'from_status'     => $return->status,
                'to_status'       => $return->status,
                'reason'          => '차액 입금 확인 — ' . number_format($몫) . '원',
            ]);
        });

        /* 증빙을 전부 다시 낸다 — 먼저 남김없이 무르고 그 다음에 낸다 */
        $다시 = app(ReturnDocsReissue::class)
            ->재발행($return->fresh(['order.patient', 'order.prescription']), null,
                    $return->receipt_no . ' 차액 입금 ' . number_format($몫) . '원');

        app(ReturnNotice::class)->tellTaker($return->fresh(),
            '차액 ' . number_format($몫) . '원이 입금되어 증빙을 다시 냈습니다 — ' . $다시['note'],
            $다시['ok'] ? 'success' : 'warning');

        if (! $다시['ok']) {
            Log::warning('[차액 입금] 증빙 재발행에 손볼 것이 남았다', [
                'receipt' => $return->receipt_no, 'note' => $다시['note'],
            ]);
        }

        return '차액 ' . number_format($몫) . '원을 받았습니다 — ' . $다시['note'];
    }
}
