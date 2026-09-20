<?php

namespace App\Services;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;

/**
 * 결제가 끝났음을 환자에게 알린다 (2026-09-18 운영 시험에서 드러남).
 *
 * 결제 완료 화면은 「영수증은 문자로 안내드립니다」라고 적어 두었는데, 정작 그 문자를
 * 보내는 자리가 어디에도 없었다. 환자는 문자를 기다리다 담당자에게 전화했고,
 * 담당자는 화면에서 「결제 완료」만 보고 무엇이 잘못됐는지 알 수 없었다.
 *
 * 문자에는 무엇을 얼마에 냈는지만 적는다. 증빙을 어디서 받는지는 적지 않는다 —
 * 카드매출전표ㆍ현금영수증은 담당자가 건마다 다르게 전하므로, 한 가지로 못 박아
 * 두면 그와 다르게 처리하는 건에서 환자가 헛걸음한다 (2026-09-18 지시).
 *
 * **한 건에 한 번만 보낸다.** 결제 웹훅은 같은 건으로 두 번 올 수 있고, 시험 승인과
 * 실제 승인이 겹칠 수도 있다 — 발송 이력으로 가린다.
 */
class PaymentDoneNotice
{
    /** 발송 이력에 남는 이름 — 두 번 보내지 않으려고 이것으로 견준다 */
    public const SOURCE = 'payment-done';

    /** 문구를 어디서 가져오는가 — 담당자가 메시지 유형에서 고칠 수 있다 */
    public const TEMPLATE = 'payment_done';

    public function __construct(private readonly MessageSender $sender) {}

    /** 마스터에 유형이 없을 때 쓰는 말 */
    public static function 기본문구(): string
    {
        return "[콜로플라스트] #{고객명}님, 결제가 정상 처리되었습니다.\n"
             . "주문번호: #{주문번호}\n"
             . '결제 금액: #{결제금액}원 (#{결제수단})';
    }

    /**
     * @return array{sent: bool, message: string}
     */
    public function send(Order $order): array
    {
        if (! config('order.payment_done_notice', true)) {
            return ['sent' => false, 'message' => '결제 완료 안내가 비활성 상태입니다.'];
        }

        $order->loadMissing('patient', 'prescription', 'tossPayment');

        /* 이미 알린 건은 지나간다 — 웹훅이 두 번 와도 문자는 한 번이다 */
        if (MessageHistory::where('source', self::SOURCE)
                ->where('prescription_id', $order->prescription_id)
                ->where('content', 'like', '%' . $order->order_number . '%')
                ->where('success_count', '>', 0)
                ->exists()) {
            return ['sent' => false, 'message' => '이미 안내한 건입니다.'];
        }

        $mobile = preg_replace('/\D/', '', (string) ($order->patient?->mobile ?? ''));

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return ['sent' => false, 'message' => '환자 연락처가 없어 보내지 못했습니다.'];
        }

        // 환자가 받는 문자다 — 이름 앞의 (E) 를 뗀다
        $name = Patient::bare($order->patient?->name) ?: '고객';

        $body = MessageTemplate::channel('sms')->active()
            ->where('code', self::TEMPLATE)->value('body') ?: self::기본문구();

        $금액 = (int) ($order->tossPayment?->amount ?? $order->deposit_amount ?? $order->expectedDeposit());

        $text = strtr($body, [
            '#{고객명}'   => $name,
            '#{주문번호}' => (string) $order->order_number,
            '#{결제금액}' => number_format($금액),
            '#{결제수단}' => $this->수단($order),
            '#{처방번호}' => (string) ($order->prescription?->rx_number ?? ''),
        ]);

        try {
            $res = $this->sender->sendBulk(
                'sms',
                [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $order->patient_id]],
                $text,
                null,
                ['source' => self::SOURCE, 'prescription_id' => $order->prescription_id],
            );
        } catch (\Throwable $e) {
            Log::warning('[결제 완료 안내] 보내지 못했다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => '보내지 못했습니다 — ' . $e->getMessage()];
        }

        if ($res['success'] ?? false) {
            activity()->performedOn($order)->log("결제 완료 안내 발송 → {$mobile}");

            return ['sent' => true, 'message' => '결제 완료 안내를 보냈습니다.'];
        }

        return ['sent' => false, 'message' => $res['message'] ?? '보내지 못했습니다.'];
    }

    /** 무엇으로 냈는가 — 토스가 알려 준 것이 있으면 그것을 쓴다 */
    private function 수단(Order $order): string
    {
        $토스 = trim((string) ($order->tossPayment?->method ?? ''));

        return match (true) {
            str_contains($토스, '카드') || strtoupper($토스) === 'CARD' => '카드',
            str_contains($토스, '가상계좌')                              => '가상계좌',
            str_contains($토스, '계좌')                                  => '계좌이체',
            default => match ((string) $order->payMethod()) {
                'card' => '카드',
                'va'   => '가상계좌',
                default => '결제',
            },
        };
    }
}
