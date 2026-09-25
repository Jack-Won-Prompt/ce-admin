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

    /** 결제 안내 알림톡 틀의 코드 — 쓰던 이름이 둘이라 둘 다 찾는다 */
    private const 알림톡코드 = ['payment_request', 'payment_guide'];

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

        /* 메시지 유형에서 켜 둔 채널로 **모두** 보낸다 (2026-09-19 지시).

           여태는 알림톡이 성공하면 거기서 멈췄다. 둘 다 켜 두어도 한쪽만 나갔고,
           알림톡을 읽지 않는 고객은 결제 안내를 받지 못했다. 채널을 고르는 기준은
           MessageTemplate::보낼채널들 한 곳에 있다. */
        $보낼것 = \App\Models\MessageTemplate::보낼채널들(self::알림톡코드, 문자는틀없이도: true);

        $보낸채널 = [];
        $못보낸말 = [];

        foreach ($보낼것 as [$channel, $templateCode]) {
            $res = $this->send($channel, $order, $mobile, $text, $templateCode);

            if ($res['success'] ?? false) {
                $보낸채널[] = $channel;
            } else {
                $못보낸말[] = self::채널이름($channel) . ': ' . ($res['message'] ?? '발송하지 못했습니다.');
            }
        }

        if (! $보낸채널) {
            $link->update(['status' => 'failed', 'error' => implode(' / ', $못보낸말) ?: null]);

            return ['link' => $link->refresh(), 'sent' => false, 'channel' => null,
                    'message' => '발송하지 못했습니다. ' . implode(' / ', $못보낸말)];
        }

        /* 링크에는 실제로 나간 채널을 적는다 — 둘 다 나갔으면 둘 다 적는다 */
        $link->update([
            'channel' => implode(',', $보낸채널),
            'sent_at' => now(),
            'error'   => $못보낸말 ? implode(' / ', $못보낸말) : null,
        ]);

        $보낸말 = implode('ㆍ', array_map([self::class, '채널이름'], $보낸채널)) . ' 발송했습니다.';

        return ['link' => $link->refresh(), 'sent' => true, 'channel' => $보낸채널[0],
                'message' => $못보낸말 ? $보낸말 . ' ' . implode(' / ', $못보낸말) : $보낸말];
    }

    /** 채널 이름 — 화면과 이력에 같은 말로 적는다 */
    public static function 채널이름(string $channel): string
    {
        return ['alimtalk' => '알림톡', 'sms' => '문자'][$channel] ?? $channel;
    }

    /** 보낼 말 — 무엇을 얼마나 어디서 내는지, 그 셋이면 된다 */
    public function compose(Order $order, PaymentLink $link): string
    {
        // 환자가 받는 문자다 — (E) 는 우리 쪽 사업부 표시라 뗀다(2026-09-10 지시)
        $name   = \App\Models\Patient::bare($order->patient?->name) ?: '고객';
        $amount = number_format($link->amount);
        $item   = $order->product_name ?: '주문';

        /* 정정으로 다시 내는 건이면 **먼저 물린 돈을 한 줄로 알린다** (2026-09-25 지시).

           여태는 새 금액의 링크만 갔다. 고객은 81,000원을 냈는데 67,500원 링크를 또
           받으니, 앞서 낸 돈이 어떻게 되었는지 알 길이 없어 두 번 내는 줄 알았다. */
        $물린것 = $order->tossPayment;
        $취소줄 = ($물린것 && (int) $물린것->cancel_amount > 0)
            ? '기존 결제 ' . number_format((int) $물린것->cancel_amount) . '원은 취소되었습니다.' . chr(10)
            : '';

        if ($link->method === PaymentLink::METHOD_BANK) {
            $bank    = config('toss.virtual_account.fallback_bank');
            $account = config('toss.virtual_account.fallback_account');
            $holder  = $this->company();

            $where = $bank && $account
                ? "{$bank} {$account} ({$holder})"
                : '입금 계좌는 담당자에게 문의해 주시기 바랍니다';

            return "[{$holder}] {$name}님, {$item} 결제 안내입니다.\n"
                 . $취소줄
                 . "금액: {$amount}원\n"
                 . "입금: {$where}\n"
                 . "입금자명은 주문자 성함과 동일하게 기재해 주시기 바랍니다.";
        }

        /* 무엇으로 내는지는 링크를 열면 그 자리에 적혀 있다 (2026-09-10 지시).
           예전에는 「아래 주소에서 카드로 결제해 주십시오」라 적었는데, 가상계좌도
           같은 틀을 써서 「가상계좌로 결제」라는 어색한 말이 나갔다.
           보내는 것은 링크 하나이므로 그 하나만 가리킨다. */
        return "[" . $this->company() . "] {$name}님, {$item} 결제 안내입니다.\n"
             . $취소줄
             . "금액: {$amount}원\n"
             . "아래 링크에서 결제해 주시기 바랍니다.\n"
             . $link->url . "\n"
             . "링크는 " . self::VALID_DAYS . "일간 유효합니다.";
    }

    /**
     * 발급된 가상계좌를 문자로 적어 보낸다.
     *
     * 링크페이는 토스 결제창을 여는 것이라 고객이 그 안에서 카드ㆍ가상계좌를 고른다.
     * 가상계좌를 고르면 은행ㆍ계좌번호ㆍ기한이 완료 화면에 뜨지만, 그 화면을 닫으면
     * 우리 쪽에는 다시 볼 곳이 없다 — 고객은 담당자에게 전화해 계좌를 다시 물었다.
     *
     * 못 보내도 결제 자체는 이미 선 것이라 막지 않는다 — 적어만 두고 지나간다.
     *
     * @param array $va 토스 승인 응답의 virtualAccount
     * @return array{sent: bool, channel: ?string, message: string}
     */
    public function sendVirtualAccount(PaymentLink $link, array $va): array
    {
        $order = $link->order;
        $mobile = $this->digits($link->receiver ?: ($order?->patient?->mobile ?? ''));

        if (!$order || !$mobile) {
            return ['sent' => false, 'channel' => null, 'message' => '받는 번호가 없습니다.'];
        }

        $account = trim((string) ($va['accountNumber'] ?? ''));
        if ($account === '') {
            return ['sent' => false, 'channel' => null, 'message' => '계좌번호가 없습니다.'];
        }

        $text = $this->composeVirtualAccount($link, $va);

        /* 결제 안내와 같은 자리다 — 켜 둔 채널로 모두 보낸다 (2026-09-19 지시) */
        $보낸채널 = [];
        $못보낸말 = [];

        foreach (\App\Models\MessageTemplate::보낼채널들(self::알림톡코드, 문자는틀없이도: true)
                 as [$channel, $templateCode]) {
            $res = $this->send($channel, $order, $mobile, $text, $templateCode);

            if ($res['success'] ?? false) {
                $보낸채널[] = $channel;
            } else {
                $못보낸말[] = self::채널이름($channel) . ': ' . ($res['message'] ?? '발송하지 못했습니다.');
            }
        }

        if ($보낸채널) {
            return ['sent' => true, 'channel' => $보낸채널[0],
                    'message' => implode('ㆍ', array_map([self::class, '채널이름'], $보낸채널)) . ' 발송했습니다.'];
        }

        Log::warning('[결제전송] 가상계좌 안내를 발송하지 못했습니다',
                     ['link' => $link->id, 'error' => implode(' / ', $못보낸말)]);

        return ['sent' => false, 'channel' => null,
                'message' => implode(' / ', $못보낸말) ?: '발송하지 못했습니다.'];
    }

    /** 계좌 안내에 적을 말 — 어디로 얼마를 언제까지, 그 셋이면 된다 */
    public function composeVirtualAccount(PaymentLink $link, array $va): string
    {
        // 환자가 받는 문자다 — (E) 를 뗀다(2026-09-10 지시)
        $name   = \App\Models\Patient::bare($link->order?->patient?->name) ?: '고객';
        $amount = number_format($link->amount);

        /* 은행은 코드로 온다(IBK · 003). 사람이 읽을 이름으로 바꾼다 — 코드만 적어
           보내면 어느 은행 앱을 열어야 할지 알 수 없다. */
        $code = $va['bankCode'] ?? $va['bank'] ?? '';
        $bank = \App\Services\TossPayments\TossClient::BANK_NAMES[$code] ?? ($code ?: '');

        $holder = trim((string) ($va['customerName'] ?? ''));

        $lines = [
            '[' . $this->company() . '] ' . $name . '님, 입금하실 계좌입니다.',
            trim($bank . ' ' . $va['accountNumber']),
        ];

        if ($holder !== '') $lines[] = '예금주 ' . $holder;

        $lines[] = '금액 ' . $amount . '원';

        /* 기한이 지나면 그 계좌로 넣어도 들어가지 않는다 — 반드시 적는다 */
        if (!empty($va['dueDate'])) {
            try {
                $lines[] = '입금 기한 ' . \Illuminate\Support\Carbon::parse($va['dueDate'])->format('Y-m-d H:i');
            } catch (\Throwable) { /* 꼴이 뜻밖이면 적지 않는다 */ }
        }

        return implode("\n", $lines);
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

    private function send(string $channel, Order $order, string $mobile, string $text,
                          ?string $templateCode = null): array
    {
        try {
            return $this->sender->sendBulk(
                $channel,
                [['rcv' => $mobile, 'rcvnm' => \App\Models\Patient::bare($order->patient?->name), 'patient_id' => $order->patient_id]],
                $text,
                $channel === 'alimtalk' ? ($templateCode ?: $this->alimtalkTemplate()) : null,
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
