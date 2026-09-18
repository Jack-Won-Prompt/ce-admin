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
                '취소 후처리에 수동 조치가 필요합니다 — ' . implode(' / ', $out['warnings'])
            );
        }

        return $out;
    }

    /**
     * 증빙만 무른다 — 주문 정정에 쓴다 (2026-09-16 지시).
     *
     * close() 와 다른 점은 **공단 청구를 건드리지 않는다**는 것이다. 정정은 주문을
     * 되돌리는 일이 아니라 내용을 고치는 일이라, 청구는 그대로 가고 금액만 바뀐다.
     *
     * 여태 정정에서 증빙을 그대로 두었다. 그래서 금액이 바뀌어도 옛 금액의 세금계산서가
     * 살아 있었고, 다시 결제해도 「이미 발행됨」으로 걸러져 새 금액이 영영 나가지
     * 않았다 — 받은 돈과 신고한 금액이 어긋난 채 남았다.
     *
     * **우리가 만든 서류도 함께 지운다.** 양식ㆍ거래명세서ㆍ카드매출전표는 한 번
     * 그려 두면 다시 그리지 않으므로(각 attach 의 «이미» 검사), 지우지 않으면 옛
     * 금액이 적힌 종이가 그대로 남는다. 사람이 올린 파일은 건드리지 않는다.
     *
     * @return array{tax: string, cash: string, docs: int, warnings: string[]}
     */
    public function 증빙무르기(Order $order, string $why): array
    {
        $out = [
            'tax'      => $this->closeTaxInvoice($order, $why),
            'cash'     => $this->closeCashReceipt($order, $why),
            'docs'     => 0,
            'warnings' => [],
        ];

        foreach (['tax', 'cash'] as $k) {
            if (str_starts_with($out[$k], '!')) {
                $out['warnings'][] = ltrim($out[$k], '!');
            }
        }

        $out['docs'] = $this->만든서류지우기($order);

        app(ClaimReadiness::class)->refresh($order->refresh());

        if ($out['warnings']) {
            activity()->performedOn($order)->log(
                '정정 후처리에 수동 조치가 필요합니다 — ' . implode(' / ', $out['warnings'])
            );
        }

        return $out;
    }

    /**
     * 우리가 만든 서류를 지운다 — 바뀐 금액으로 다시 그리게 한다.
     *
     * 사람이 올린 파일(처방전ㆍ신분증ㆍ등록신청서…)은 건드리지 않는다.
     */
    private function 만든서류지우기(Order $order): int
    {
        if (! $order->prescription_id) {
            return 0;
        }

        $지울것 = \App\Models\PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->whereIn('doc_type', array_keys(\App\Models\PrescriptionAttachment::만든서류))
            ->get();

        foreach ($지울것 as $a) {
            try {
                if ($a->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($a->file_path);
                }
                $a->delete();
            } catch (\Throwable $e) {
                Log::warning('[정정] 만든 서류를 지우지 못했다', [
                    'attachment' => $a->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        if ($지울것->count()) {
            activity()->performedOn($order)->log(
                "정정으로 다시 그릴 서류 {$지울것->count()}장을 지웠습니다 — "
                . $지울것->pluck('doc_label')->filter()->implode(' · ')
            );
        }

        return $지울것->count();
    }

    /** 세금계산서 — 발행돼 있으면 취소한다. 없던 거래의 계산서가 남으면 안 된다. */
    private function closeTaxInvoice(Order $order, string $why): string
    {
        if ($order->tax_invoice_status !== 'issued') {
            return '해당 없음';
        }

        try {
            /* 낼 때 적어 둔 번호로 부른다 — 다시 만들면 재발행 건에서 어긋난다
               (2026-09-18 운영 시험에서 드러남) */
            $mgtKey = \App\Http\Controllers\OrderController::세금계산서문서번호($order, 새로: false);

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
