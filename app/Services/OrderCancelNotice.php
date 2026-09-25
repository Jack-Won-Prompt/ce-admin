<?php

namespace App\Services;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;

/**
 * 주문이 취소되었음을 환자에게 알린다 (2026-09-25 무한테스트에서 드러남).
 *
 * 주문을 취소하면 창고 판매주문이 취소되고, 받은 돈이 물리고, 발행한 증빙도 취소된다.
 * 그런데 **고객에게 가는 안내만 없었다.** 돈은 며칠 뒤 카드사를 거쳐 돌아오는데,
 * 그 사이 고객은 아무 말도 듣지 못해 「낸 돈이 어떻게 됐느냐」고 전화를 건다.
 *
 * 무엇이 취소되었고 얼마가 돌아가는지, 그 둘만 적는다. 언제 들어오는지는 적지
 * 않는다 — 카드사마다 달라 한 가지로 못 박으면 그와 다른 건에서 헛기다림이 된다.
 *
 * **한 번만 보낸다.** 취소는 되돌릴 수 없으므로 두 번 알릴 일이 없다.
 */
class OrderCancelNotice
{
    /** 발송 이력에 남는 이름 — 두 번 보내지 않으려고 이것으로 견준다 */
    public const SOURCE = 'order-cancel';

    /** 문구를 어디서 가져오는가 — 담당자가 메시지 유형에서 고칠 수 있다 */
    public const TEMPLATE = 'order_cancelled';

    public function __construct(private readonly MessageSender $sender) {}

    /** 마스터에 유형이 없을 때 쓰는 말 */
    public static function 기본문구(): string
    {
        return "[콜로플라스트] #{고객명}님, 주문이 취소되었습니다.\n"
             . "주문번호: #{주문번호}\n"
             . "취소 금액: #{취소금액}원\n"
             . '문의 1588-7866';
    }

    /**
     * @return array{sent: bool, message: string}
     */
    public function send(Order $order): array
    {
        $order->loadMissing('patient', 'prescription', 'tossPayment');

        /* 이미 알린 건은 지나간다 */
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

        /* 돌려주는 돈 — 토스가 물린 액수가 정본이다. 그것이 없으면(가상계좌를 아직
           받지 않았거나 담당자가 손으로 확인한 건) 받을 돈으로 갈음한다. */
        $취소금액 = (int) ($order->tossPayment?->cancel_amount ?: 0)
                  ?: (int) ($order->deposit_amount ?: $order->expectedDeposit());

        $text = strtr($body, [
            '#{고객명}'   => $name,
            '#{주문번호}' => (string) $order->order_number,
            '#{취소금액}' => number_format($취소금액),
            '#{처방번호}' => (string) ($order->prescription?->rx_number ?? ''),
        ]);

        try {
            $res = $this->sender->sendBulk(
                'sms',
                [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $order->patient_id]],
                $text,
                self::TEMPLATE,
                ['source' => self::SOURCE, 'prescription_id' => $order->prescription_id],
            );
        } catch (\Throwable $e) {
            Log::warning('[주문 취소 안내] 보내지 못했다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => '보내지 못했습니다 — ' . $e->getMessage()];
        }

        if ($res['success'] ?? false) {
            activity()->performedOn($order)->log("주문 취소 안내 발송 → {$mobile}");

            return ['sent' => true, 'message' => '주문 취소 안내를 보냈습니다.'];
        }

        return ['sent' => false, 'message' => $res['message'] ?? '보내지 못했습니다.'];
    }
}
