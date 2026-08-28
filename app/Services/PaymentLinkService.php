<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentLink;
use Illuminate\Support\Facades\Log;

/**
 * 결제 요청을 만들어 환자에게 보낸다.
 *
 * 카드·가상계좌는 우리 결제 페이지 주소를 보내고(토스 결제위젯이 그 안에서 돈다),
 * 무통장입금은 우리 계좌를 적어 보낸다 — 토스를 타지 않으므로 입금 확인은 사람이 한다.
 *
 * 보내는 길은 알림톡을 먼저 쓰고 막히면 문자로 잇는다. 카카오는 채널을 막아 둔 사람에게
 * 닿지 않는데, 결제 안내는 못 받으면 그대로 멈추는 종류의 말이다.
 */
class PaymentLinkService
{
    /** 링크를 며칠 열어 둘 것인가 — 지나면 결제 페이지가 열리지 않는다 */
    private const VALID_DAYS = 7;

    public function __construct(private readonly MessageSender $sender) {}

    /**
     * 만들고 곧바로 보낸다.
     *
     * @return array{link: PaymentLink, sent: bool, channel: ?string, message: string}
     */
    public function issue(Order $order, string $method, ?string $mobile = null): array
    {
        $mobile = $this->digits($mobile ?: ($order->patient?->mobile ?? ''));

        $link = PaymentLink::create([
            'order_id'   => $order->id,
            'token'      => PaymentLink::newToken(),
            'method'     => $method,
            'amount'     => (int) $order->total_amount,
            'status'     => 'sent',
            'receiver'   => $mobile ?: null,
            'expires_at' => now()->addDays(self::VALID_DAYS),
            'created_by' => auth()->id(),
        ]);

        if (!$mobile) {
            $link->update(['status' => 'failed', 'error' => '환자 연락처가 없습니다.']);

            return ['link' => $link, 'sent' => false, 'channel' => null,
                    'message' => '환자 연락처가 없어 보내지 못했습니다.'];
        }

        $text = $this->compose($order, $link);

        /* 알림톡 → 문자 순으로 잇는다. 먼저 성공하면 거기서 멈춘다.
           알림톡은 승인된 템플릿이 있어야 나간다 — 등록해 두지 않았으면 문자로 바로 간다. */
        $channels = $this->alimtalkTemplate() ? ['alimtalk', 'sms'] : ['sms'];
        $last = null;

        foreach ($channels as $channel) {
            $res = $this->send($channel, $order, $mobile, $text);
            if ($res['success'] ?? false) {
                $link->update(['channel' => $channel, 'sent_at' => now(), 'error' => null]);

                return ['link' => $link->refresh(), 'sent' => true, 'channel' => $channel,
                        // 「문자으로」가 되지 않게 조사를 통째로 쥔다
                        'message' => ($channel === 'alimtalk' ? '알림톡으로' : '문자로') . ' 보냈습니다.'];
            }
            $last = $res['message'] ?? '보내지 못했습니다.';
        }

        $link->update(['status' => 'failed', 'error' => $last ?? null]);

        return ['link' => $link->refresh(), 'sent' => false, 'channel' => null,
                'message' => '보내지 못했습니다. ' . ($last ?? '')];
    }

    /** 보낼 말 — 무엇을 얼마나 어디서 내는지, 그 셋이면 된다 */
    public function compose(Order $order, PaymentLink $link): string
    {
        $name   = $order->patient?->name ?? '고객';
        $amount = number_format($link->amount);
        $item   = $order->product_name ?: '주문';

        if ($link->method === PaymentLink::METHOD_BANK) {
            $bank    = config('toss.virtual_account.fallback_bank');
            $account = config('toss.virtual_account.fallback_account');
            $holder  = $this->company();

            $where = $bank && $account
                ? "{$bank} {$account} ({$holder})"
                : '입금 계좌는 담당자에게 문의해 주십시오';

            return "[{$holder}] {$name}님, {$item} 결제 안내입니다.\n"
                 . "금액: {$amount}원\n"
                 . "입금: {$where}\n"
                 . "입금자명을 주문자 이름으로 적어 주시면 확인이 빠릅니다.";
        }

        $what = $link->method === PaymentLink::METHOD_VIRTUAL ? '가상계좌 발급' : '카드 결제';

        return "[" . $this->company() . "] {$name}님, {$item} 결제 안내입니다.\n"
             . "금액: {$amount}원\n"
             . "아래 주소에서 {$what}로 결제해 주십시오.\n"
             . $link->url . "\n"
             . "링크는 " . self::VALID_DAYS . "일간 열려 있습니다.";
    }

    /** 낸 것으로 표시한다 — 토스가 확인해 준 뒤에만 부른다 */
    public function markPaid(PaymentLink $link, string $paymentKey, ?string $tossOrderId = null): void
    {
        $link->update([
            'status'        => 'paid',
            'paid_at'       => now(),
            'payment_key'   => $paymentKey,
            'toss_order_id' => $tossOrderId,
        ]);
    }

    private function send(string $channel, Order $order, string $mobile, string $text): array
    {
        try {
            return $this->sender->sendBulk(
                $channel,
                [['rcv' => $mobile, 'rcvnm' => $order->patient?->name ?? '', 'patient_id' => $order->patient_id]],
                $text,
                $channel === 'alimtalk' ? $this->alimtalkTemplate() : null,
                ['source' => 'payment-link', 'prescription_id' => $order->prescription_id],
            );
        } catch (\Throwable $e) {
            Log::warning('[결제전송] 보내지 못함', ['channel' => $channel, 'order' => $order->order_number,
                                                    'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** 결제 안내로 쓸 알림톡 템플릿 — 없으면 null 이고, 그때는 문자로만 보낸다 */
    private function alimtalkTemplate(): ?string
    {
        return \App\Models\MessageTemplate::channel('alimtalk')
            ->whereIn('code', ['payment_request', 'payment_guide'])
            ->value('code');
    }

    /** 문자에 찍히는 우리 이름 — 설정에 적어 둔 상호를 쓴다 */
    private function company(): string
    {
        return config('popbill.company.corp_name') ?: config('app.name');
    }

    private function digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }
}
