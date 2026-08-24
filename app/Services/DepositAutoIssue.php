<?php

namespace App\Services;

use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Support\BillingStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 입금이 확인되면 세무 서류를 스스로 발행한다.
 *
 * 담당자가 통장을 보고 세우든, 토스 웹훅이 알려 오든, 「돈이 들어왔다」는 하나다.
 * 그 순간 청구전략이 정한 대로 현금영수증과 세금계산서를 낸다. 발행된 서류는
 * 발행 경로가 이미 PDF 로 만들어 서류 관리에 넣는다 — 우리는 부르기만 한다.
 *
 * 무엇을 내는가는 청구전략이 정한다(App\Support\BillingStrategy).
 *   · 현금영수증 — 환자가 낸 몫(본인부담금). 가상계좌ㆍ무통장입금일 때만 낸다.
 *     카드결제는 카드매출전표가 증빙이라, 현금영수증까지 내면 같은 금액이 두 번 신고된다.
 *   · 세금계산서 — 기관이 내는 몫. 공급받는자는 환자 개인이며 번호는 비워 보낸다
 *     (발행 경로가 처방전의 주민등록번호로 채운다).
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
     * @return array{cash:?string, tax:?string, skipped:array<string>}
     */
    public function run(Order $order, string $cause = ''): array
    {
        $out = ['cash' => null, 'tax' => null, 'skipped' => []];

        if (!$this->enabled()) {
            $out['skipped'][] = '자동 발행이 꺼져 있음';
            return $out;
        }

        $order->loadMissing(['patient', 'prescription', 'tossPayment']);

        if (!$order->isDepositConfirmed()) {
            $out['skipped'][] = '입금이 확인되지 않음';
            return $out;
        }

        $rx       = $order->prescription;
        $strategy = BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class);

        /* 전략이 정해지지 않았거나 확인중이면 아무것도 내지 않는다.
           모르는 채로 낸 신고는 되돌리는 데 더 큰 품이 든다. */
        if (!empty($strategy['pending'])) {
            $out['skipped'][] = '청구전략이 정해지지 않음(' . ($strategy['note'] ?: '확인중') . ')';
            $this->log($order, $cause, $out);

            return $out;
        }

        $out['cash'] = $this->cashReceipt($order, $strategy, $out);
        $out['tax']  = $this->taxInvoice($order, $strategy, $out);

        $this->log($order, $cause, $out);

        return $out;
    }

    // ──────────────────────────────────────────────────────────

    /** 현금영수증 — 환자가 낸 몫. 카드로 받았으면 내지 않는다. */
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

        $amount = (int) ($order->deposit_amount ?: $order->expectedDeposit());
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

    /** 세금계산서 — 기관이 내는 몫. 공급받는자는 환자 개인이다. */
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

        $amount = (int) ($order->nhis_amount ?? 0);
        if ($amount <= 0) {
            $out['skipped'][] = '세금계산서: 기관 부담금이 0원';
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
        ]);

        activity()->performedOn($order)->log(
            '입금 확인 자동 발행' . ($cause ? "({$cause})" : '') . ': '
            . ($done ? implode(' · ', $done) : '발행 없음')
            . ($out['skipped'] ? ' — ' . implode(' / ', $out['skipped']) : '')
        );
    }
}
