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
 * 그 규칙은 `MessageTemplate::채널마다` 한 곳에 있다 — 설정에서 「모두 보냄」으로
 * 바꿀 수도 있다 (2026-09-30 지시).
 */
class PaymentLinkService
{
    /** 링크를 며칠 열어 둘 것인가 — 지나면 결제 페이지가 열리지 않는다 */
    private const VALID_DAYS = 7;

    /** 결제 안내 알림톡 틀의 코드 — 쓰던 이름이 둘이라 둘 다 찾는다 */
    private const 알림톡코드 = ['payment_request', 'payment_guide'];

    /* 문자 본문도 메시지 관리에서 고친다 (2026-09-25 무한테스트에서 드러남).

       여태 결제 안내 문자는 본문이 이 파일에 박혀 있었다. 담당자는 메시지 관리에서
       고칠 수 없었고, 발송 이력의 「유형」 칸도 빈칸으로 남아 무슨 문자였는지 본문을
       읽어야 알 수 있었다. 세 갈래를 각자 유형으로 세운다. */
    public const 유형_링크     = 'payment_link_guide';
    public const 유형_계좌     = 'payment_bank_guide';
    public const 유형_가상계좌 = 'payment_va_guide';

    /** 이 링크가 어느 유형으로 나가는가 */
    public static function 문자유형(PaymentLink $link): string
    {
        return $link->method === PaymentLink::METHOD_BANK ? self::유형_계좌 : self::유형_링크;
    }

    public function __construct(private readonly MessageSender $sender) {}

    /**
     * 만들고 곧바로 보낸다.
     *
     * @return array{link: PaymentLink, sent: bool, channel: ?string, message: string}
     */
    /**
     * 결제 링크를 내고 보낸다.
     *
     * $금액 을 주면 그 금액으로 낸다 (2026-09-28). 여태 늘 주문 금액 전액이었는데,
     * 교환의 차액처럼 **주문 금액이 아닌 돈**을 청할 자리가 생겼다 — 전액으로 열면
     * 이미 낸 사람에게 처음부터 다시 내라는 링크가 간다(4,500원을 청해야 하는데
     * 42,000원 링크가 나갔다). 결제 화면과 문자는 둘 다 링크의 금액을 읽는다.
     */
    public function issue(Order $order, string $method, ?string $mobile = null, ?int $금액 = null): array
    {
        $mobile = $this->digits($mobile ?: ($order->patient?->mobile ?? ''));

        $link = PaymentLink::create([
            'order_id'   => $order->id,
            'token'      => PaymentLink::newToken(),
            'method'     => $method,
            'amount'     => $금액 !== null ? max(0, $금액) : (int) $order->total_amount,
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
        ['보낸채널' => $보낸채널, '못보낸말' => $못보낸말] =
            \App\Models\MessageTemplate::채널마다(self::알림톡코드,
                fn (string $channel, ?string $templateCode) => $this->send(
                    $channel, $order, $mobile, $text,
                    $channel === 'sms' ? self::문자유형($link) : $templateCode),
                문자는틀없이도: true);

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

    /**
     * 환자에게 적어 보낼 배송지 한 줄.
     *
     * `shipping_address` 에는 저장할 때 상세주소까지 합쳐 담는다. 그런데 이어 붙지
     * 않은 옛 건이 있어 상세주소 칸을 따로 본다 — 이미 들어 있으면 덧대지 않는다.
     */
    private static function 주소한줄(Order $order): string
    {
        $주소   = trim((string) ($order->shipping_address ?? ''));
        $상세   = trim((string) ($order->shipping_address_detail ?? ''));

        if ($상세 === '' || $주소 === '' || str_contains($주소, $상세)) {
            return $주소;
        }

        return trim($주소 . ' ' . $상세);
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

            return \App\Models\MessageTemplate::문구(self::유형_계좌, [
                '#{회사}' => $holder, '#{고객명}' => $name, '#{제품}' => $item,
                '#{취소줄}' => $취소줄, '#{금액}' => $amount, '#{입금처}' => $where,
            ], '[' . $holder . '] ' . $name . '님, ' . $item . ' 결제 안내입니다.' . chr(10)
             . $취소줄
             . '금액: ' . $amount . '원' . chr(10)
             . '입금: ' . $where . chr(10)
             . '입금자명은 주문자 성함과 동일하게 기재해 주시기 바랍니다.');
        }

        /* 무엇으로 내는지는 링크를 열면 그 자리에 적혀 있다 (2026-09-10 지시).
           예전에는 「아래 주소에서 카드로 결제해 주십시오」라 적었는데, 가상계좌도
           같은 틀을 써서 「가상계좌로 결제」라는 어색한 말이 나갔다.
           보내는 것은 링크 하나이므로 그 하나만 가리킨다. */
        /* 처방 건에만 붙는 보험 유의사항 (2026-09-30 지시 · 메시지 템플릿 V4).

           V4 는 글을 「(처방)」과 「(처방외)」 두 벌로 나누어 왔는데, 실제로 다른 것은
           **이 한 덩이뿐**이다. 문자는 카카오 승인을 받는 글이 아니므로 두 벌로 나눌
           까닭이 없다 — 한 벌에 두고 자격이 정한다(2026-09-30 「나누지 않고 내용만
           구분」 지시).

           붙는 자격 — 일반ㆍ산재ㆍ**차상위경감ㆍ기초** (2026-09-30 확인).
           붙지 않는 자격 — 자동차보험ㆍ처방외. 그 둘은 요양비를 받지 않아 환수될
           일이 없다.

           자격을 읽지 못하면 붙이지 않는다 — 해당 없는 사람에게 「지급된 요양비가
           환수될 수 있습니다」라고 적는 편이 더 나쁘다. */
        $자격  = (string) ($order->prescription?->benefit_class ?? '');
        $유형  = (string) ($order->prescription?->counsel_acc_add_type ?? '');
        $처방건 = $유형 !== '20'
               && in_array($자격, ['일반', '산재', '차상위경감', '기초'], true);

        $유의사항 = $처방건 ? implode(chr(10), [
            '',
            '<자가도뇨 소모성 재료 보험 구매 유의사항 안내>',
            '총 처방기간 내에 아래 사항에 해당하는 경우, 지급된 요양비는 환수될 수 있습니다',
            '아래 사항 해당되시거나, 보험 자격 변경 시, 연락주시기 바랍니다.',
            '',
            '1. 요양병원에 입원 중인 경우',
            '2. 일반 병원에 입원해 병원에서 제공한 카테터를 사용하는 경우',
            '3. 의료진에 의해 도뇨 처치를 받고 있는 경우',
            '4. 해외 체류 중 카테터를 구매한 경우',
            '',
        ]) : '';

        return \App\Models\MessageTemplate::문구(self::유형_링크, [
            '#{회사}' => $this->company(), '#{고객명}' => $name, '#{이름}' => $name,
            '#{제품}' => $item,
            '#{제품번호}' => (string) ($order->product_code ?? ''),
            '#{제품명}' => (string) ($order->product_name ?? ''),
            '#{주문수량}' => (string) ((int) ($order->quantity ?? 0)),
            /* 배송지 칸에는 **이미 상세주소까지 담겨 있다** — 저장할 때 합쳐 넣는다.
               여기서 또 이으면 「…배재정동빌딩 B동 10층 배재정동빌딩 B동 10층」처럼
               두 번 적힌다(2026-09-30 확인). 상세주소는 이어 붙지 않은 옛 건에만
               덧댄다. */
            '#{주소}' => self::주소한줄($order),
            '#{유의사항}' => $유의사항,
            '#{취소줄}' => $취소줄, '#{금액}' => $amount, '#{링크}' => $link->url,
            '#{유효일}' => (string) self::VALID_DAYS,
        ], '[' . $this->company() . '] ' . $name . '님, ' . $item . ' 결제 안내입니다.' . chr(10)
         . $취소줄
         . '금액: ' . $amount . '원' . chr(10)
         . '아래 링크에서 결제해 주시기 바랍니다.' . chr(10)
         . $link->url . chr(10)
         . '링크는 ' . self::VALID_DAYS . '일간 유효합니다.');
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

        /* 결제 안내와 같은 자리다 — 발송 방식은 채널마다 한 곳이 정한다 (2026-09-30) */
        ['보낸채널' => $보낸채널, '못보낸말' => $못보낸말] =
            \App\Models\MessageTemplate::채널마다(self::알림톡코드,
                fn (string $channel, ?string $templateCode) => $this->send(
                    $channel, $order, $mobile, $text,
                    $channel === 'sms' ? self::유형_가상계좌 : $templateCode),
                문자는틀없이도: true);

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

        /* 예금주도 「(E)」를 뗀다 (2026-09-27 지시).

           고객명은 진작 떼고 있었는데 **예금주는 토스에 적힌 값을 그대로 썼다.**
           그 값이 곧 우리가 토스에 보낸 이름이라, 보내는 쪽을 고치기 전에 발급된
           계좌는 「(E)오지혜」로 남아 있다 — 문자에도 그대로 실렸다.
           여기서 한 번 더 떼면 옛 계좌를 다시 안내할 때도 깨끗하게 나간다. */
        $holder = \App\Models\Patient::bare(trim((string) ($va['customerName'] ?? '')));

        $lines = [
            '[' . $this->company() . '] ' . $name . '님, 입금하실 계좌입니다.',
            trim($bank . ' ' . $va['accountNumber']),
        ];

        if ($holder !== '') $lines[] = '예금주 ' . $holder;

        $lines[] = '금액 ' . $amount . '원';

        /* 기한이 지나면 그 계좌로 넣어도 들어가지 않는다 — 반드시 적는다 */
        $기한 = '';
        if (!empty($va['dueDate'])) {
            try {
                $기한 = '입금 기한 ' . \Illuminate\Support\Carbon::parse($va['dueDate'])->format('Y-m-d H:i');
                $lines[] = $기한;
            } catch (\Throwable) { /* 꼴이 뜻밖이면 적지 않는다 */ }
        }

        return \App\Models\MessageTemplate::문구(self::유형_가상계좌, [
            '#{회사}' => $this->company(), '#{고객명}' => $name,
            '#{계좌}' => trim($bank . ' ' . $va['accountNumber']),
            '#{예금주줄}' => $holder !== '' ? '예금주 ' . $holder . chr(10) : '',
            '#{금액}' => $amount,
            '#{기한줄}' => $기한 !== '' ? chr(10) . $기한 : '',
        ], implode(chr(10), $lines));
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
                $channel === 'alimtalk' ? ($templateCode ?: $this->alimtalkTemplate()) : $templateCode,
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
