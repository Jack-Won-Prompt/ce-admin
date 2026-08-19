<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Popbill\CashbillService;
use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * 주문이 취소되면 그 뒤에 달린 것들도 함께 닫는다.
 *
 * 주문 하나가 취소돼도 청구·계산서·현금영수증·정산은 저마다 제 표를 보고 산다.
 * 주문만 닫아 두면 이미 발행한 계산서가 살아남아 실제로 없던 거래의 세금계산서가
 * 국세청에 남고, 청구 대기 목록에는 나가지도 않은 건이 계속 뜬다.
 *
 * 정산·계산서·청구 목록은 모두 주문 상태(confirmed·shipping·delivered)로 고르므로
 * 취소로 바뀌는 순간 목록에서는 빠진다. 여기서 하는 일은 「이미 발행·청구한 것」을
 * 되돌리는 것이다.
 *
 * 공단 청구는 우리가 취소할 수 없다 — 사람이 공단 사이트에서 해야 한다. 그래서
 * 청구까지 간 건은 자동으로 손대지 않고 남겨 알린다.
 */
class OrderCancellation
{
    /**
     * @return array{tax: string, cash: string, nhis: string, warnings: string[]}
     */
    public function close(Order $order, ?OrderReturn $return = null): array
    {
        $why = $return ? "{$return->receipt_no} " . $return->typeLabel() : '주문 취소';

        $out = [
            'tax'      => $this->closeTaxInvoice($order, $why),
            'cash'     => $this->closeCashReceipt($order, $why),
            'nhis'     => $this->closeClaim($order, $why),
            'warnings' => [],
        ];

        foreach (['tax', 'cash', 'nhis'] as $k) {
            if (str_starts_with($out[$k], '!')) {
                $out['warnings'][] = ltrim($out[$k], '!');
            }
        }

        // 청구 준비 여부를 다시 따진다 — 발행이 취소되면 모자란 자료가 달라진다
        app(ClaimReadiness::class)->refresh($order->refresh());

        if ($out['warnings']) {
            activity()->performedOn($order)->log(
                '취소 뒤처리에 사람 손이 필요합니다 — ' . implode(' / ', $out['warnings'])
            );
        }

        return $out;
    }

    /** 세금계산서 — 발행돼 있으면 취소한다. 없던 거래의 계산서가 남으면 안 된다. */
    private function closeTaxInvoice(Order $order, string $why): string
    {
        if ($order->tax_invoice_status !== 'issued') {
            return '해당 없음';
        }

        try {
            $mgtKey = 'TI' . $order->tax_invoice_issued_at?->format('Ymd')
                    . str_pad($order->id, 6, '0', STR_PAD_LEFT);

            app(TaxinvoiceService::class)->cancelIssue(
                config('popbill.test.corp_num'), 'SELL', $mgtKey, null, config('popbill.test.user_id')
            );

            $order->forceFill([
                'tax_invoice_status'       => 'cancelled',
                'tax_invoice_cancelled_at' => now(),
            ])->save();

            activity()->performedOn($order)->log("주문 취소로 세금계산서를 취소했습니다 ({$why})");

            return '취소함';
        } catch (\Throwable $e) {
            Log::error('[주문취소] 세금계산서 취소 실패', ['order' => $order->id, 'error' => $e->getMessage()]);

            return '!세금계산서(' . ($order->tax_invoice_no ?: '-') . ')를 취소하지 못했습니다: ' . $e->getMessage();
        }
    }

    /** 현금영수증 — 마찬가지다. 낸 적 없는 돈의 영수증이 남으면 안 된다. */
    private function closeCashReceipt(Order $order, string $why): string
    {
        if ($order->cash_receipt_status !== 'issued') {
            return '해당 없음';
        }

        try {
            app(CashbillService::class)->revokeRegistIssue(
                corpNum:      config('popbill.test.corp_num'),
                mgtKey:       'CRC' . now()->format('Ymd') . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                orgMgtKey:    $order->cash_receipt_no,
                orgTradeDate: $order->cash_receipt_issued_at?->format('Ymd') ?? '',
                userId:       config('popbill.test.user_id'),
            );

            $order->forceFill([
                'cash_receipt_status'       => 'cancelled',
                'cash_receipt_cancelled_at' => now(),
            ])->save();

            activity()->performedOn($order)->log("주문 취소로 현금영수증을 취소했습니다 ({$why})");

            return '취소함';
        } catch (\Throwable $e) {
            Log::error('[주문취소] 현금영수증 취소 실패', ['order' => $order->id, 'error' => $e->getMessage()]);

            return '!현금영수증(' . ($order->cash_receipt_no ?: '-') . ')을 취소하지 못했습니다: ' . $e->getMessage();
        }
    }

    /**
     * 공단 청구.
     *
     * 아직 청구 전이면 대상에서 뺀다. 이미 청구했으면 우리가 물릴 수 없다 —
     * 공단 사이트에서 사람이 취소해야 하므로, 그 사실을 남겨 알린다.
     */
    private function closeClaim(Order $order, string $why): string
    {
        $status = $order->nhis_claim_status;

        if (in_array($status, ['submitted', 'approved'], true)) {
            return '!공단에 이미 청구한 건입니다(' . ($status === 'approved' ? '승인' : '청구완료')
                 . '). 공단 사이트에서 청구를 취소해 주십시오';
        }

        if ($status === 'pending') {
            $order->forceFill([
                'nhis_claim_status'     => 'cancelled',
                'nhis_rejection_reason' => '주문 취소 (' . $why . ')',
            ])->save();

            return '청구 대상에서 뺐습니다';
        }

        return '해당 없음';
    }
}
