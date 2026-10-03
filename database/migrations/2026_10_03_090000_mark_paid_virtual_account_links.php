<?php

use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 가상계좌로 받은 결제 요청을 「결제완료」로 맞춘다 (2026-10-03 지시).
 *
 * ## 무엇이 어긋나 있었나
 *
 * 가상계좌는 `PaymentLinkService::markPaid()` 를 지나지 않는다. 환자가 링크를 눌러
 * 계좌를 받고, 입금은 그 뒤에 들어온다 — 입금 웹훅이 `Order::남은링크거두기()` 로
 * 올 때 그 줄의 `paid_at` 은 아직 비어 있다. 그래서 「두 번 내는 것을 막는」 거두기가
 * **환자가 실제로 쓴 줄까지 「취소」로 거뒀다.**
 *
 *   EUD202610021341391 ((E)이명섭A) · 가상계좌 60,750원 10-02 18:35 입금
 *   링크 #84 cancelled 01053323136 17:36 발송
 *   링크 #86 cancelled 01053055285 17:42 발송  ← 이 줄로 냈다
 *            토스주문번호가 toss_payments 의 것과 같다
 *
 * 돈을 받았는지는 `toss_payments` 가 정하므로 미수로 읽히지는 않았다. 그러나 결제
 * 기록에서 **어느 줄로 냈는지** 알 수 없었고, 두 줄 모두 「취소」라 담당자는 보내기만
 * 하고 못 받은 건으로 읽는다. 운영에 **16건**이 그렇게 남아 있다(2026-10-03 확인).
 *
 * ## 어떻게 맞추나
 *
 * 앞으로 생기는 것은 `Order::남은링크거두기()` 가 거두기 전에 적는다. 여기서는
 * 이미 남은 것만 **같은 잣대로** 맞춘다 — `Order::결제된링크()` 를 그대로 불러
 * 운영에서 돌 때와 다른 결과가 나오지 않게 한다.
 *
 * **아직 입금 전인 줄은 건드리지 않는다.** `다받았나()` 를 먼저 보므로
 * `WAITING_FOR_DEPOSIT` 로 기다리는 줄(#56 EUD202610010953081)은 그대로 남는다 —
 * 그 줄은 아직 환자가 내야 하는 것이라 「결제완료」로 적으면 청구가 사라진다.
 *
 * 금액을 고치지 않는다. 지우지도 않는다. 상태 글자와 결제 시각만 적는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['toss_payments', 'payment_links', 'orders'] as $표) {
            if (! Schema::hasTable($표)) {
                return;
            }
        }

        $맞춘것 = 0;

        /* 토스가 받았다고 한 것만 본다. 담당자가 통장을 보고 확인한 건은 토스주문번호가
           없어 어느 줄로 냈는지 가릴 값이 없다 — 그런 건은 그대로 둔다. */
        foreach (TossPayment::whereNotNull('toss_order_id')
                     ->whereIn('status', ['DONE', 'PARTIAL_CANCELED'])
                     ->cursor() as $결제) {
            $order = Order::find($결제->order_id);

            if (! $order || ! $order->다받았나()) {
                continue;
            }

            if (! $줄 = $order->결제된링크()) {
                continue;
            }

            $줄->update([
                'status'        => 'paid',
                'paid_at'       => $order->paidAt() ?? $결제->deposited_at ?? now(),
                'payment_key'   => $줄->payment_key   ?: $결제->payment_key,
                'toss_order_id' => $줄->toss_order_id ?: $결제->toss_order_id,
            ]);

            $맞춘것++;
        }

        if ($맞춘것 > 0) {
            \Illuminate\Support\Facades\Log::info(
                '[결제요청] 가상계좌로 받은 줄을 「결제완료」로 맞췄습니다', ['건수' => $맞춘것]
            );
        }
    }

    /**
     * 되돌리지 않는다.
     *
     * 되돌린다면 「결제완료」를 다시 「취소」로 적어야 하는데, 그것은 **받은 돈을 받지
     * 않은 것으로 적는 일**이다. 어느 줄이 본래 취소였는지 가릴 값도 남지 않는다.
     * 잘못 적힌 것을 바로 적는 일이라 되돌릴 자리를 두지 않는다.
     */
    public function down(): void
    {
    }
};
