<?php

namespace App\Services;

use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Support\BillingStrategy;
use App\Support\CardSalesSlip;
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
 *   건강보험공단 일반(10/90)   → 세금계산서 90% + 현금영수증 10%
 *   건강보험공단 차상위경감    → 세금계산서 100%
 *   지자체(기초)               → 세금계산서 100%
 *   산재                       → 현금영수증 100%  (본인이 전액 낸다 — 근로복지공단에는 환자가 따로 받는다)
 *   자동차보험                 → 현금영수증 100%  (보험사에도 환자가 따로 받는다)
 *   처방외                     → 현금영수증 100%
 *
 * 현금영수증은 **가상계좌ㆍ무통장입금일 때만** 나간다 — 카드결제는 카드매출전표가
 * 그 자리의 증빙이고, 둘 다 내면 같은 금액이 두 번 신고된다. 그래서 본인이 낸 몫에는
 * 결제 갈래에 따라 증빙이 하나씩 붙는다: 카드면 매출전표, 현금이면 현금영수증.
 *
 * 이 글은 표(BillingStrategy::resolve)를 비추기만 한다 — 규칙은 저쪽 한 곳에 있다.
 *
 * 세금계산서의 공급받는자는 환자 개인이며 번호는 비워 보낸다 — 발행 경로가 처방전의
 * 주민등록번호로 채운다(열람 기록이 남는다).
 *
 * 발행이 끝나면 거래명세서를 서식대로 만들어 같은 주문의 첨부문서로 넣는다. 그것은
 * 신고 서류가 아니라 물건과 함께 나가는 종이라, 자동 발행 스위치와 상관없이 붙는다.
 *
 * 팝빌을 **운영**으로 돌릴 때 이 발행은 국세청 실신고다. 그래서 기본은 꺼져 있고
 * (config: billing.auto_issue), 켜 두어도 정해진 개시일 전에는 내지 않는다
 * (billing.auto_issue_start, 2026-10-01).
 * 팝빌이 **시험**이면 신고되는 곳이 없어 그 두 겹을 보지 않고 낸다(enabled 참고).
 * 어느 쪽이든 다음은 지킨다:
 *   · 이미 발행된 것은 다시 내지 않는다
 *   · 금액이 0 이면 내지 않는다
 *   · 청구전략이 「확인중」이면 손대지 않는다 — 모르는 채로 신고하지 않는다
 *   · 어떤 이유로 실패해도 입금 확인 자체는 되돌리지 않는다. 남는 것은 기록이다
 */
class DepositAutoIssue
{
    /**
     * 지금 자동 발행을 하는가.
     *
     * 스위치 두 겹(billing.auto_issue · billing.auto_issue_start)은 **국세청 실신고를
     * 막으려고** 둔 것이다. 그런데 팝빌을 시험 테스트베드로 돌리고 있으면 신고되는
     * 곳이 없다 — test.popbill.com 에 쌓일 뿐이다. 그 동안에도 스위치가 막고 있어
     * 시험에서는 세금계산서ㆍ현금영수증이 한 장도 나오지 않았다(2026-09-19 지시).
     *
     * 그래서 팝빌이 시험이면 스위치를 보지 않고 낸다. 운영으로 돌리는 그 순간
     * 두 겹이 다시 살아난다 — 잠금을 푸는 것이 아니라, 잠글 까닭이 없는 동안만 비킨다.
     */
    public function enabled(string $service = 'taxinvoice'): bool
    {
        /* 갈래는 서류마다 따로 본다 (2026-09-19 지시).

           세금계산서는 시험에 두고 현금영수증만 운영으로 올리는 일이 있다. 한 칸으로
           가리면 둘 가운데 하나가 늘 틀린 잠금 아래 놓인다. */
        if (! \App\Support\PopbillEnvironment::isLiveFor($service)) {
            return true;
        }

        return (bool) config('billing.auto_issue', false) && $this->started();
    }

    /** 둘 가운데 하나라도 낼 수 있는가 — 전략을 셈할지 가릴 때 쓴다 */
    private function 낼것이있나(): bool
    {
        return $this->enabled('cashbill') || $this->enabled('taxinvoice');
    }

    /**
     * 자동 발행을 시작하는 날이 되었는가.
     *
     * 스위치와 따로 둔다. 켜 두고 화면을 돌려 보는 동안에도 국세청에는 아무것도 가지
     * 않아야 하고, 정해진 날이 오면 아무도 손대지 않아도 시작되어야 한다.
     *
     * 날이 비어 있으면 보지 않는다 — 스위치만으로 정해진다.
     */
    public function started(): bool
    {
        $start = config('billing.auto_issue_start');

        if (!$start) {
            return true;
        }

        try {
            return now()->startOfDay()->gte(\Illuminate\Support\Carbon::parse($start)->startOfDay());
        } catch (\Throwable) {
            /* 날을 못 읽으면 내지 않는 쪽으로 눕는다 — 실신고다 */
            Log::warning('[자동 발행] 시작일을 읽지 못했습니다', ['value' => $start]);

            return false;
        }
    }

    /**
     * @param  string  $cause  무엇이 불렀는지 — 기록에 남는다('담당자 확인' · '토스 웹훅')
     * @return array{cash:?string, tax:?string, statement:?string, skipped:array<string>}
     */
    public function run(Order $order, string $cause = ''): array
    {
        $out = ['cash' => null, 'tax' => null, 'statement' => null, 'confirm' => null, 'skipped' => []];

        $order->loadMissing(['patient', 'prescription', 'items', 'tossPayment']);

        /* 받을 돈이 있는 건만 입금을 기다린다 (2026-09-16 지시).

           본인부담금이 0원인 건 — 차상위ㆍ기초ㆍ산재처럼 기관이 전액을 내는 건 —
           은 환자에게 받을 돈이 없어 입금 확인이 영영 서지 않는다. 그래서 출고까지
           끝났는데 세금계산서도 거래명세서도 만들어지지 않았다. 공단부담금은
           멀쩡히 있으므로 낼 것이 없는 건이 아니다.

           기준은 처음부터 이러했다 —

             본인부담금 있음  토스 웹훅으로 결제가 확인되면 낸다
             본인부담금 없음  주문을 창고로 보내는 그 자리에서 바로 낸다

           부르는 자리는 그대로 두고 이 관문만 푼다. 받을 돈이 없는 건은 어느
           자리에서 불러도 지나가고, 있는 건은 여태처럼 입금을 기다린다. */
        if ((int) $order->expectedDeposit() > 0 && ! $order->isDepositConfirmed()) {
            $out['skipped'][] = '입금이 확인되지 않음';
            return $out;
        }

        /* 결제일을 채운다 (2026-09-17 지시).

           여태 결제 시각과 입금 확인일은 채워지는데 결제일 칸만 비어 있었다. 그
           칸에서 구입일ㆍ사용 개시일ㆍ급여 종료일ㆍ다음 재구매 가능일이 함께
           서므로, 비어 있으면 그 넷도 서지 않는다 — 담당자가 병원ㆍ처방 정보를
           저장해 주어야 그때 채워졌다.

           돈이 들어온 날이 결제일이다. 적혀 있으면 덮지 않는다 — 담당자가 고쳐
           둔 날짜가 정본이고, 부를 때마다 오늘로 밀리면 급여 기간이 조용히 늘어난다. */
        $rx = $order->prescription;

        if ($rx && trim((string) $rx->pay_date) === '') {
            $낸날 = $order->deposit_confirmed_at
                    ?? $order->tossPayment?->deposited_at
                    ?? now();

            \App\Support\BenefitDates::apply($rx, \Illuminate\Support\Carbon::parse($낸날)->toDateString());
        }

        /* 돈이 들어온 그때 낸다(2026-09-03 확정 · 테스트 시나리오 3.1.1ㆍ3.2.1).

           한때는 출고까지 기다렸다. 물건이 아직 창고에 있는데 국세청 신고가 끝나
           있으면, 그 뒤 주문이 취소될 때 취소 신고를 다시 해야 하기 때문이었다.

           이제 입금 시점으로 옮긴다 — 시나리오가 「입금 → 서류 → 창고 확정 → 출고」
           차례이고, 입금이 확인되면 그 자리에서 확정까지 보내므로 출고 전에 취소될
           틈이 좁아졌다. 그래도 취소는 있을 수 있고, 그때는 OrderCancellation 이
           발행을 되돌린다. */

        if ($this->낼것이있나()) {
            $rx       = $order->prescription;
            $strategy = BillingStrategy::resolve($rx?->counsel_acc_add_type, $rx?->benefit_class);

            /* 전략이 정해지지 않았거나 확인중이면 아무것도 내지 않는다.
               모르는 채로 낸 신고는 되돌리는 데 더 큰 품이 든다. */
            if (!empty($strategy['pending'])) {
                $out['skipped'][] = '청구전략이 정해지지 않음(' . ($strategy['note'] ?: '확인중') . ')';
            } else {
                /* 서류마다 제 갈래의 잠금을 본다 — 하나가 잠겨 있어도 다른 하나는 나간다 */
                if ($this->enabled('cashbill')) {
                    $out['cash'] = $this->cashReceipt($order, $strategy, $out);
                } else {
                    $out['skipped'][] = '현금영수증: 운영 자동 발행이 잠겨 있음';
                }

                if ($this->enabled('taxinvoice')) {
                    $out['tax'] = $this->taxInvoice($order, $strategy, $out);
                } else {
                    $out['skipped'][] = '세금계산서: 운영 자동 발행이 잠겨 있음';
                }
            }
        } elseif (!config('billing.auto_issue', false)) {
            $out['skipped'][] = '자동 발행이 꺼져 있음';
        } else {
            $out['skipped'][] = '자동 발행 개시일(' . config('billing.auto_issue_start') . ') 전';
        }

        /* 거래명세서는 세무 서류가 아니라 물건과 함께 나가는 종이다. 국세청에 신고되는
           것이 없으니 자동 발행 스위치와 상관없이, 입금이 확인되면 붙인다. */
        $out['statement'] = $this->statement($order, $out);

        /* 카드로 받았으면 매출전표를 붙인다(2026-09-04 확정).
           카드 건은 현금영수증을 내지 않으므로(위 cashReceipt 참고) 이것이 그 자리의
           증빙이다. 여태 만드는 곳이 없어 카드 건에는 증빙이 하나도 남지 않았다.
           토스가 준 승인 내용을 그대로 옮겨 그린다. */
        $out['card_slip'] = CardSalesSlip::attach($order)?->file_original_name;

        if (! $out['card_slip']) {
            /* 왜 못 그렸는지를 남긴다 (2026-09-16 지시).

               여태 applies() 가 참일 때만 적었다. 그래서 **토스 결제 줄이 아예 없는
               건**은 아무 말 없이 지나갔다 — 담당자는 왜 전표가 없는지 알 수 없었다.

               카드로 적혀 있는데 승인 기록이 없는 건은 링크를 보내 놓고 담당자가 손으로
               입금을 확인한 건이다. 실제로 카드가 긁히지 않았으므로 그릴 재료가 없다.
               그 사실을 적어 두면 나중에 그런 건을 찾아낼 수 있다.

               다만 본인 부담금이 0원인 청구 전략(차상위경감ㆍ기초)은 결제 자체가 없다.
               그런 건에까지 「손으로 입금 확인」이라 적으면 사실과 다르다(2026-09-21 확인). */
            $out['skipped'][] = CardSalesSlip::applies($order)
                ? '카드매출전표: 만들지 못함'
                : ($order->expectedDeposit() <= 0
                    ? '카드매출전표: 본인 부담금이 없는 건이라 결제가 없습니다'
                    : ($order->payMethod() === \App\Models\PaymentLink::METHOD_CARD
                        ? '카드매출전표: 토스 승인 내역이 없어 그리지 못했습니다(손으로 입금 확인한 건)'
                        : ''));

            $out['skipped'] = array_values(array_filter($out['skipped']));
        }

        /* 창고에 확정을 보낸다(2026-09-03 확정 · 시나리오 3.3). 여태 판매주문은
           「등록」으로만 서 있어, 돈이 들어와도 담당자가 위드웍스 화면에 들어가
           손으로 확정을 눌러야 출고가 시작됐다.

           재고가 모자라 확정되지 않아도 여기서 물러나지 않는다 — 돈은 이미 들어왔고
           발행도 끝났다. 못 했다는 것만 남기고 담당자가 잇는다. */
        $confirm = app(\App\Services\WithworksConfirm::class)->confirm($order);
        $out['confirm'] = $confirm['message'];
        if (! $confirm['ok']) {
            $out['skipped'][] = $confirm['message'];
        }

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

    /** 현금영수증 — 청구전략이 정한 몫만큼. 카드로 받았으면 내지 않는다(매출전표가 증빙). */
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
           배송비는 없다(2026-09-03 확정) — 더할 것이 없다. */
        $amount = $this->share($order, (int) $strategy['cash_receipt']);
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

        $번호2 = $this->call($order, 'issueCashReceipt', [
            'cash_receipt_type'       => 'income_deduction',
            'cash_receipt_identifier' => $identifier,
            'cash_receipt_amount'     => $amount,
        ], '현금영수증', $out);

        /* 세금계산서와 같은 까닭 — 신고가 막혀도 종이는 남긴다 (2026-09-16 지시) */
        if (! $번호2) {
            \App\Support\CashReceiptForm::attach($order, [
                'cash_receipt_amount' => $amount,
            ]);

            $out['skipped'][] = '현금영수증: 양식만 첨부했습니다(신고 안 됨)';
        }

        return $번호2;
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

        $name = $order->patient?->bare_name ?? '';
        if ($name === '') {
            $out['skipped'][] = '세금계산서: 환자 이름이 없음';
            return null;
        }

        $supply = (int) round($amount / 1.1);

        $번호 = $this->call($order, 'issueTaxInvoice', [
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

        /* 팝빌로 실제 신고가 나가지 않았어도 종이는 남긴다 (2026-09-16 지시).

           시험 환경ㆍ발행 시뮬레이션ㆍ인증서 미등록처럼 신고가 막히는 자리가 있다.
           그때 아무것도 붙지 않으면 화면을 처음부터 끝까지 훑어도 「서류가 붙는가」를
           볼 수 없다. 신고된 승인번호는 없지만 금액과 당사자는 정해져 있으므로,
           그 값으로 서식을 그려 첨부문서로 둔다.

           실제로 발행된 건은 발행 경로가 팝빌이 준 PDF 를 이미 붙이므로 손대지 않는다. */
        if (! $번호) {
            $supply = (int) round($amount / 1.1);

            \App\Support\TaxInvoiceForm::attach($order, [
                'tax_invoice_supply'  => $supply,
                'tax_invoice_vat'     => $amount - $supply,
                'tax_invoice_purpose' => \App\Support\TaxInvoiceForm::PURPOSE,
            ]);

            $out['skipped'][] = '세금계산서: 양식만 첨부했습니다(신고 안 됨)';
        }

        return $번호;
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
            ($out['card_slip'] ?? null) ? '카드매출전표' : null,
        ]);

        activity()->performedOn($order)->log(
            '입금 확인 자동 발행' . ($cause ? "({$cause})" : '') . ': '
            . ($done ? implode(' · ', $done) : '발행 없음')
            . ($out['skipped'] ? ' — ' . implode(' / ', $out['skipped']) : '')
        );
    }
}
