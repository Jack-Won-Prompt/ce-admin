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

        /* 팝빌에 낸 증빙의 종이도 함께 지운다 (2026-09-18 운영 시험에서 드러남).

           여태 첨부(PrescriptionAttachment)만 지웠다. 세금계산서ㆍ현금영수증 PDF 는
           서류함(PrescriptionDocument)에 따로 쌓이는데, 증빙을 물러도 옛 금액의 종이가
           그대로 남아 담당자가 그것을 환자ㆍ공단에 보낼 수 있었다. 다시 낼 때마다 한
           장씩 더 쌓이기도 했다 — 한 주문에 같은 이름의 계산서가 석 장 붙어 있었다.

           서류함은 처방전에 매달려 있어 주문 번호 칸이 없다. 파일이 놓인 자리
           (tax_invoices/{주문}/…)로 이 주문 것만 고른다 — 한 처방에 주문이 여럿이면
           옆 주문의 증빙까지 지우게 된다. */
        $증빙 = \App\Models\PrescriptionDocument::where('prescription_id', $order->prescription_id)
            ->whereIn('type', ['tax_invoice', 'cash_receipt'])
            ->where(function ($q) use ($order) {
                $q->where('file_path', 'like', 'tax_invoices/' . $order->id . '/%')
                  ->orWhere('file_path', 'like', 'cash_receipts/' . $order->id . '/%');
            })
            ->get();

        foreach ($증빙 as $d) {
            try {
                if ($d->file_path) {
                    \Illuminate\Support\Facades\Storage::delete($d->file_path);
                }
                $d->delete();
            } catch (\Throwable $e) {
                Log::warning('[정정] 증빙 서류를 지우지 못했다', [
                    'document' => $d->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $모두 = $지울것->count() + $증빙->count();

        if ($모두) {
            $이름 = $지울것->pluck('doc_label')
                ->merge($증빙->pluck('type')->map(fn ($t) => $t === 'tax_invoice' ? '세금계산서' : '현금영수증'))
                ->filter()->unique();

            activity()->performedOn($order)->log(
                "정정으로 다시 그릴 서류 {$모두}장을 지웠습니다 — " . $이름->implode(' · ')
            );
        }

        return $모두;
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

        $corpNum = config('popbill.test.corp_num');

        /* 취소 문서번호는 **발행 번호에서 만든다** (2026-09-22 확인요청 2쪽).

           여태 「CRC + 오늘 + 주문id」로 그때그때 지어, 같은 날 같은 주문을 두 번
           정정하면 같은 번호가 되어 팝빌이 「동일한 문서번호」로 거절했다 — 두 번째
           취소가 통째로 실패하고 옛 금액의 현금영수증이 살아남았다.

           발행 번호에는 이미 차례가 붙어 있으므로(OrderController::현금영수증문서번호),
           그것에서 만들면 정정을 거듭해도 취소마다 다른 번호가 된다. 손으로 누르는
           취소(OrderController::cancelCashReceipt)가 쓰는 규칙과 같다. */
        $cancelMgtKey = 'CRC' . substr(
            \App\Http\Controllers\OrderController::현금영수증문서번호($order, 새로: false), 2
        );

        try {
            app(CashbillService::class)->revokeRegistIssue(
                corpNum:      $corpNum,
                mgtKey:       $cancelMgtKey,
                orgMgtKey:    $order->cash_receipt_no,
                orgTradeDate: $order->cash_receipt_issued_at?->format('Ymd') ?? '',
                userId:       config('popbill.test.user_id'),
            );

            $order->forceFill([
                'cash_receipt_status'       => 'cancelled',
                'cash_receipt_cancelled_at' => now(),
            ])->save();

            /* 취소한 줄을 우리 표에 들인다 (2026-09-22 확인요청 2쪽).

               발행은 진작 들이고 있었는데 취소만 빠져 있었다. 그래서 현금영수증 화면은
               정정한 건을 「정정 후 발행」 한 줄로만 보여 주었다 — 확인요청 2쪽의
               「초과 금액 1개 라인으로 잘못 보임」이 그것이다. 취소 줄이 들어오면
               「정정 전 발행 / 취소 / 정정 후 발행」 셋이 선다. */
            try {
                app(\App\Services\Popbill\CashbillSyncService::class)->refreshOne($corpNum, $cancelMgtKey);
            } catch (\Throwable $e) {
                Log::warning('[주문취소] 현금영수증 취소 후 동기화 실패', [
                    'order' => $order->id, 'error' => $e->getMessage(),
                ]);
            }

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
