<?php

namespace App\Services;

use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Support\BillingStrategy;
use App\Support\TransactionStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 입금이 확인되면 세무 서류를 스스로 발행한다.
 *
 * 담당자가 통장을 보고 세우든, 토스 웹훅이 알려 오든, 「돈이 들어왔다」는 하나다.
 * 그 순간 청구전략이 정한 대로 현금영수증과 세금계산서를 낸다. 발행된 서류는
 * 발행 경로가 이미 PDF 로 만들어 서류 관리에 넣는다 — 우리는 부르기만 한다.
 *
 * 무엇을 얼마로 내는가는 청구전략이 정한다(App\Support\BillingStrategy).
 *
 *   건강보험공단 일반(10/90)   → 세금계산서 90%
 *   건강보험공단 차상위경감    → 세금계산서 100%
 *   지자체(기초)               → 세금계산서 100%
 *   산재                       → 세금계산서 100%  (본인부담 100% 인데도 그렇다)
 *   처방외                     → 현금영수증 100%
 *
 * 본인부담이 있다고 그 몫으로 현금영수증을 내지는 않는다 — 일반은 세금계산서 하나로
 * 끝난다. 현금영수증이 나가는 것은 처방외뿐이고, 그것도 가상계좌ㆍ무통장입금일 때만이다
 * (카드결제는 카드매출전표가 증빙이라, 현금영수증까지 내면 같은 금액이 두 번 신고된다).
 *
 * 세금계산서의 공급받는자는 환자 개인이며 번호는 비워 보낸다 — 발행 경로가 처방전의
 * 주민등록번호로 채운다(열람 기록이 남는다).
 *
 * 발행이 끝나면 거래명세서를 서식대로 만들어 같은 주문의 첨부문서로 넣는다. 그것은
 * 신고 서류가 아니라 물건과 함께 나가는 종이라, 자동 발행 스위치와 상관없이 붙는다.
 *
 * 이 발행은 국세청 실신고다. 그래서 기본은 꺼져 있고(config: billing.auto_issue),
 * 켠 뒤에도 다음을 지킨다:
 *   · 이미 발행된 것은 다시 내지 않는다
 *   · 금액이 0 이면 내지 않는다
 *   · 청구전략이 「확인중」이면 손대지 않는다 — 모르는 채로 신고하지 않는다
 *   · 어떤 이유로 실패해도 입금 확인 자체는 되돌리지 않는다. 남는 것은 기록이다
 */
class DepositAutoIssue
{
    public function enabled(): bool
    {
        return (bool) config('billing.auto_issue', false);
    }

    /**
     * @param  string  $cause  무엇이 불렀는지 — 기록에 남는다('담당자 확인' · '토스 웹훅')
     * @return array{cash:?string, tax:?string, statement:?string, skipped:array<string>}
     */
    public function run(Order $order, string $cause = ''): array
    {
        $out = ['cash' => null, 'tax' => null, 'statement' => null, 'skipped' => []];

        $order->loadMissing(['patient', 'prescription', 'items', 'tossPayment']);

        if (!$order->isDepositConfirmed()) {
            $out['skipped'][] = '입금이 확인되지 않음';
            return $out;
        }

        /* 출고 전에는 아무것도 내지 않는다 (요청서 8ㆍ9쪽 「입금 및 출고 되어야」,
           2026-08-31 회신).

           예전에는 입금만 보고 냈다. 그러면 물건이 아직 창고에 있는데 국세청 신고가
           끝나 있고, 그 뒤 주문이 취소되면 취소 신고를 다시 해야 한다.

           여기서 물러나도 잃는 것은 없다 — 창고가 출고를 알려 올 때 다시 부른다
           (WithworksSync::apply). */
        if (!$order->isShipped()) {
            $out['skipped'][] = '아직 출고 전 — 출고되면 그때 냅니다';

            return $out;
        }

        if ($this->enabled()) {
            $rx       = $order->prescription;
            $strategy = BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class);

            /* 전략이 정해지지 않았거나 확인중이면 아무것도 내지 않는다.
               모르는 채로 낸 신고는 되돌리는 데 더 큰 품이 든다. */
            if (!empty($strategy['pending'])) {
                $out['skipped'][] = '청구전략이 정해지지 않음(' . ($strategy['note'] ?: '확인중') . ')';
            } else {
                $out['cash'] = $this->cashReceipt($order, $strategy, $out);
                $out['tax']  = $this->taxInvoice($order, $strategy, $out);
            }
        } else {
            $out['skipped'][] = '자동 발행이 꺼져 있음';
        }

        /* 거래명세서는 세무 서류가 아니라 물건과 함께 나가는 종이다. 국세청에 신고되는
           것이 없으니 자동 발행 스위치와 상관없이, 입금이 확인되면 붙인다. */
        $out['statement'] = $this->statement($order, $out);

        $this->log($order, $cause, $out);

        return $out;
    }

    /** 거래명세서 — 받은 서식대로 만들어 주문의 첨부문서로 넣는다. */
    private function statement(Order $order, array &$out): ?string
    {
        $att = TransactionStatement::attach($order);

        if (!$att) {
            $out['skipped'][] = '거래명세서: 만들지 못함';
            return null;
        }

        return $att->file_original_name;
    }

    // ──────────────────────────────────────────────────────────

    /** 현금영수증 — 처방외에만. 카드로 받았으면 내지 않는다. */
    private function cashReceipt(Order $order, array $strategy, array &$out): ?string
    {
        if (($strategy['cash_receipt'] ?? 0) <= 0) {
            $out['skipped'][] = '현금영수증: 청구전략에 없음';
            return null;
        }
        if ($order->cash_receipt_status === 'issued') {
            $out['skipped'][] = '현금영수증: 이미 발행됨';
            return null;
        }

        $method = $order->payMethod();
        if (!in_array($method, [PaymentLink::METHOD_VIRTUAL, PaymentLink::METHOD_BANK], true)) {
            $out['skipped'][] = '현금영수증: 카드결제 건(카드매출전표가 증빙)';
            return null;
        }

        /* 금액은 비율이 정한다. 제품 금액(본인부담 + 기관부담)에 비율을 곱하고,
           배송비는 그대로 더한다 — 환자가 실제로 낸 돈이다. */
        $amount = $this->share($order, (int) $strategy['cash_receipt']) + (int) ($order->shipping_fee ?? 0);
        if ($amount <= 0) {
            $out['skipped'][] = '현금영수증: 금액이 0원';
            return null;
        }

        /* 식별번호 — 환자가 적어 둔 현금영수증 번호가 먼저다. 없으면 휴대폰번호.
           둘 다 없으면 낼 수 없다(무기명으로 내지 않는다). */
        $identifier = preg_replace('/\D/', '',
            (string) ($order->patient?->cash_receipt_no ?: $order->patient?->mobile));

        if ($identifier === '') {
            $out['skipped'][] = '현금영수증: 식별번호(휴대폰ㆍ현금영수증번호)가 없음';
            return null;
        }

        return $this->call($order, 'issueCashReceipt', [
            'cash_receipt_type'       => 'income_deduction',
            'cash_receipt_identifier' => $identifier,
            'cash_receipt_amount'     => $amount,
        ], '현금영수증', $out);
    }

    /** 세금계산서 — 청구전략이 정한 비율만큼. 공급받는자는 환자 개인이다. */
    private function taxInvoice(Order $order, array $strategy, array &$out): ?string
    {
        if (($strategy['tax_invoice'] ?? 0) <= 0) {
            $out['skipped'][] = '세금계산서: 청구전략에 없음';
            return null;
        }
        if ($order->tax_invoice_status === 'issued') {
            $out['skipped'][] = '세금계산서: 이미 발행됨';
            return null;
        }

        /* 기관 부담금이 아니라 비율이 정한다 — 산재는 본인부담 100% 인데도
           세금계산서는 100% 로 나간다(기관 부담금은 0 이다). */
        $amount = $this->share($order, (int) $strategy['tax_invoice']);
        if ($amount <= 0) {
            $out['skipped'][] = '세금계산서: 금액이 0원';
            return null;
        }

        $name = $order->patient?->name ?? '';
        if ($name === '') {
            $out['skipped'][] = '세금계산서: 환자 이름이 없음';
            return null;
        }

        $supply = (int) round($amount / 1.1);

        return $this->call($order, 'issueTaxInvoice', [
            'tax_invoice_type'     => 'electronic',
            'tax_invoice_invoicee' => '개인',
            'tax_invoice_biz_name' => $name,
            'tax_invoice_ceo_name' => $name,
            // 비워 보낸다 — 발행 경로가 처방전의 주민등록번호로 채운다(열람 기록이 남는다)
            'tax_invoice_biz_no'   => '',
            'tax_invoice_email'    => $order->patient?->email ?? '',
            'tax_invoice_supply'   => $supply,
            'tax_invoice_vat'      => $amount - $supply,
        ], '세금계산서', $out);
    }

    /**
     * 제품 금액에서 비율만큼.
     *
     * 밑돈은 본인부담 + 기관부담이다(= 제품 금액). 총액 칸을 쓰지 않는 까닭은 그 칸에
     * 배송비가 섞여 있는 건이 있어서다 — 세금계산서에 배송비를 얹으면 금액이 어긋난다.
     */
    private function share(Order $order, int $percent): int
    {
        if ($percent <= 0) {
            return 0;
        }

        $base = (int) ($order->patient_copay ?? 0) + (int) ($order->nhis_amount ?? 0);

        return (int) round($base * $percent / 100);
    }

    /**
     * 화면이 쓰는 발행 경로를 그대로 부른다.
     *
     * 발행ㆍ기록ㆍPDF 저장ㆍ서류 관리 등록이 이미 그 안에 있다. 같은 일을 여기에 다시
     * 적으면 두 곳이 서로 다르게 자란다 — 손대지 않고 부른다.
     */
    private function call(Order $order, string $action, array $payload, string $what, array &$out): ?string
    {
        try {
            $res = app(OrderController::class)->{$action}(new Request($payload), $order);
            $body = json_decode($res->getContent(), true) ?: [];

            if (!($body['success'] ?? false)) {
                $out['skipped'][] = "{$what}: " . ($body['message'] ?? '발행 실패');
                return null;
            }

            $order->refresh();

            return $what === '현금영수증' ? $order->cash_receipt_no : $order->tax_invoice_no;
        } catch (\Throwable $e) {
            Log::warning("[자동발행] {$what} 실패", [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);
            $out['skipped'][] = "{$what}: " . $e->getMessage();

            return null;
        }
    }

    private function log(Order $order, string $cause, array $out): void
    {
        $done = array_filter([
            $out['cash'] ? "현금영수증 {$out['cash']}" : null,
            $out['tax']  ? "세금계산서 {$out['tax']}"  : null,
            $out['statement'] ? '거래명세서' : null,
        ]);

        activity()->performedOn($order)->log(
            '입금 확인 자동 발행' . ($cause ? "({$cause})" : '') . ': '
            . ($done ? implode(' · ', $done) : '발행 없음')
            . ($out['skipped'] ? ' — ' . implode(' / ', $out['skipped']) : '')
        );
    }
}
