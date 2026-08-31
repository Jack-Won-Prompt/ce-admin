<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\Log;

/**
 * 교환·반품·취소가 어디까지 왔는지 환자에게 알린다 (요청서 4쪽 Case 표, 2026-08-31).
 *
 * 절차서의 「접수자 → 환자 inform」이다. 알림톡ㆍSMS 로 보낸다(2026-08-31 회신).
 *
 * 창고 사건이 올 때마다 저절로 보내지 않는다. 밖으로 나가는 말이라 한 번 보내면 무를 수
 * 없고, 검수중ㆍ입고중처럼 환자가 알 까닭이 없는 걸음도 있다. 접수자가 상세 화면에서
 * 눌러 보낸다 — 무엇을 알릴지는 사람이 정한다.
 *
 * 알림톡을 먼저 본다. 팝빌에 올려 둔 알림톡 유형이 있으면 그것으로 보내고, 없으면
 * 문자로 보낸다 — 알림톡은 팝빌에 등록한 틀이 있어야 나가므로, 틀 없이 부르면 실패한다.
 */
class ReturnPatientNotice
{
    /** 발송 이력에 남는 이름 */
    public const SOURCE = 'return-notice';

    /** 문구를 어디서 가져오는가 — 담당자가 메시지 유형에서 고칠 수 있다 */
    public const TEMPLATE = 'return_progress';

    public function __construct(private readonly MessageSender $sender) {}

    /**
     * @return array{sent: bool, message: string}
     */
    public function send(OrderReturn $return, ?string $extra = null): array
    {
        $return->loadMissing('order.patient');

        $mobile = preg_replace('/\D/', '', (string) ($return->order?->patient?->mobile ?? ''));

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return ['sent' => false, 'message' => '환자 연락처가 없어 보내지 못했습니다.'];
        }

        [$channel, $templateCode] = $this->pickChannel();

        $text = $this->compose($return, $channel, $extra);

        try {
            $res = $this->sender->sendBulk(
                $channel,
                [[
                    'rcv'        => $mobile,
                    'rcvnm'      => $return->order?->patient?->name ?? '',
                    'patient_id' => $return->order?->patient_id,
                ]],
                $text,
                $templateCode,
                ['source' => self::SOURCE, 'prescription_id' => $return->order?->prescription_id],
            );
        } catch (\Throwable $e) {
            Log::warning('[반품] 환자 안내 실패', [
                'receipt' => $return->receipt_no, 'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => '보내지 못했습니다 — ' . $e->getMessage()];
        }

        return ($res['success'] ?? false)
            ? ['sent' => true,  'message' => ($channel === 'alimtalk' ? '알림톡' : '문자') . '을 보냈습니다.']
            : ['sent' => false, 'message' => $res['message'] ?? '보내지 못했습니다.'];
    }

    /**
     * 알림톡으로 보낼 수 있으면 알림톡, 아니면 문자.
     *
     * 알림톡은 팝빌에 올려 둔 틀이 있어야 나간다. 틀을 아직 안 올렸으면 문자로 보낸다 —
     * 못 보내는 것보다는 문자로라도 닿는 편이 낫다.
     *
     * @return array{0: string, 1: ?string}
     */
    private function pickChannel(): array
    {
        $alimtalk = MessageTemplate::channel('alimtalk')->active()
            ->where('code', self::TEMPLATE)
            ->whereNotNull('ats_template_code')
            ->first();

        return $alimtalk ? ['alimtalk', $alimtalk->code] : ['sms', null];
    }

    /**
     * 보낼 말.
     *
     * 메시지 유형에 적어 둔 글을 쓴다 — 담당자가 화면에서 고칠 수 있어야 하고, 손으로
     * 보낼 때와 문구가 갈리지 않아야 한다. 유형이 비어 있으면 코드에 둔 말로 대신한다.
     */
    public function compose(OrderReturn $return, string $channel = 'sms', ?string $extra = null): string
    {
        $body = MessageTemplate::channel($channel)->active()
            ->where('code', self::TEMPLATE)->value('body');

        if (!$body) {
            $body = "[콜로플라스트] #{고객명}님, 접수하신 #{유형} 건이 #{상태} 상태입니다.\n"
                  . "접수번호: #{접수번호}";
        }

        $text = strtr($body, [
            '#{고객명}'   => $return->order?->patient?->name ?: '고객',
            '#{유형}'     => $return->typeLabel(),
            '#{상태}'     => $return->statusLabel(),
            '#{접수번호}' => (string) $return->receipt_no,
            '#{주문번호}' => (string) ($return->order?->order_number ?? ''),
        ]);

        // 접수자가 덧붙일 말이 있으면 뒤에 붙인다 — 건마다 사정이 다르다
        return $extra ? rtrim($text) . "\n" . trim($extra) : $text;
    }
}
