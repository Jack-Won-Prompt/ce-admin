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

    /**
     * 저절로 나가는 자리의 문구 (2026-09-18 지시).
     *
     * 여태 환자에게 가는 말은 상세 화면의 ［안내 보내기］ 하나뿐이었다. 누르지 않으면
     * 환자는 아무 연락도 받지 못하고, 안 보냈다는 표시도 화면에 없었다 — 접수했는지,
     * 돈이 돌아갔는지, 더 내야 하는지를 환자가 알 길이 없다.
     *
     * 세 자리는 환자가 반드시 알아야 하는 자리라 저절로 보낸다. 나머지 걸음
     * (수거중ㆍ검수중ㆍ오더 확정…)은 그대로 사람이 골라 보낸다.
     */
    public const 접수     = 'return_received';
    public const 환불     = 'return_refunded';
    public const 추가입금 = 'return_extra_payment';

    /** 자리마다 끄고 켜는 설정 — 설정 › 서비스 설정 › 교환·반품 */
    public const 설정 = [
        self::접수     => 'returns.notice_on_received',
        self::환불     => 'returns.notice_on_refunded',
        self::추가입금 => 'returns.notice_on_extra_payment',
    ];

    public function __construct(private readonly MessageSender $sender) {}

    /**
     * @return array{sent: bool, message: string}
     */
    public function send(OrderReturn $return, ?string $extra = null, string $code = self::TEMPLATE): array
    {
        $return->loadMissing('order.patient');

        $mobile = preg_replace('/\D/', '', (string) ($return->order?->patient?->mobile ?? ''));

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return ['sent' => false, 'message' => '환자 연락처가 없어 보내지 못했습니다.'];
        }

        [$channel, $templateCode] = $this->pickChannel($code);

        $text = $this->compose($return, $channel, $extra, $code);

        try {
            $res = $this->sender->sendBulk(
                $channel,
                [[
                    'rcv'        => $mobile,
                    'rcvnm'      => \App\Models\Patient::bare($return->order?->patient?->name),
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
            /* 조사를 낱말에 맞춘다 — 「문자을 보냈습니다」로 나갔다 */
            ? ['sent' => true,  'message' => $channel === 'alimtalk' ? '알림톡을 보냈습니다.' : '문자를 보냈습니다.']
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
    private function pickChannel(string $code = self::TEMPLATE): array
    {
        $alimtalk = MessageTemplate::channel('alimtalk')->active()
            ->where('code', $code)
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
    public function compose(OrderReturn $return, string $channel = 'sms',
                            ?string $extra = null, string $code = self::TEMPLATE): string
    {
        $body = MessageTemplate::channel($channel)->active()
            ->where('code', $code)->value('body');

        /* 유형을 아직 안 올린 서버에서도 말이 나가야 한다 — 틀이 없다고 입을 다물면
           환자는 아무것도 못 듣는다. 마스터에 올려 두면 그쪽이 이긴다. */
        if (!$body) {
            $기본 = self::기본문구();
            $body = $기본[$code] ?? $기본[self::TEMPLATE];
        }

        $text = strtr($body, [
            '#{고객명}'   => \App\Models\Patient::bare($return->order?->patient?->name) ?: '고객',
            '#{유형}'     => $return->typeLabel(),
            '#{상태}'     => $return->statusLabel(),
            '#{접수번호}' => (string) $return->receipt_no,
            '#{주문번호}' => (string) ($return->order?->order_number ?? ''),
            '#{환불금액}' => number_format((int) ($return->refund_amount ?? $return->adjust_amount ?? 0)),
            '#{조정금액}' => number_format((int) ($return->adjust_amount ?? 0)),
            '#{환불수단}' => OrderReturn::REFUND_METHODS[$return->refund_method] ?? '',
        ]);

        // 접수자가 덧붙일 말이 있으면 뒤에 붙인다 — 건마다 사정이 다르다
        return $extra ? rtrim($text) . "\n" . trim($extra) : $text;
    }

    /**
     * 마스터에 유형이 없을 때 쓰는 말.
     *
     * 접수는 「받았습니다」까지만 알린다 — 언제 끝나는지는 검수를 해 봐야 알고, 미리
     * 날짜를 약속하면 그 날이 지날 때마다 전화가 온다.
     *
     * @return array<string, string>
     */
    public static function 기본문구(): array
    {
        return [
            self::TEMPLATE => "[콜로플라스트] #{고객명}님, 접수하신 #{유형} 건이 #{상태} 상태입니다.\n"
                            . '접수번호: #{접수번호}',

            self::접수     => "[콜로플라스트] #{고객명}님, #{유형} 신청이 접수되었습니다.\n"
                            . "접수번호: #{접수번호}\n"
                            . '제품이 도착해 검수가 끝나면 다시 안내드리겠습니다.',

            self::환불     => "[콜로플라스트] #{고객명}님, #{유형} 건의 환불을 처리했습니다.\n"
                            . "접수번호: #{접수번호}\n"
                            . "환불 금액: #{환불금액}원\n"
                            . '카드사 사정에 따라 입금까지 2~3영업일이 걸릴 수 있습니다.',

            self::추가입금 => "[콜로플라스트] #{고객명}님, #{유형} 처리 결과 추가로 내실 금액이 있습니다.\n"
                            . "접수번호: #{접수번호}\n"
                            . "추가 금액: #{조정금액}원\n"
                            . '내시는 방법은 담당자가 따로 안내드리겠습니다.',
        ];
    }

    /**
     * 저절로 보내는 자리 — 못 보내도 하던 일은 그대로 간다 (2026-09-18 지시).
     *
     * 접수ㆍ환불ㆍ추가 입금은 환자가 반드시 알아야 하는 자리다. 그렇다고 문자가 실패했다고
     * 접수를 무르거나 단계를 되돌리면, 받은 신청이 사라지고 이미 처리한 환불이 안 한 일이
     * 된다. 보낸 결과만 한 줄로 돌려주어 화면 알림에 곁들인다.
     */
    public function 자동안내(OrderReturn $return, string $code): string
    {
        /* 꺼 두었으면 말도 하지 않는다 — 「보내지 못했습니다」는 못 보낸 것이지 안 보낸
           것이 아니다. 끈 줄 알면서 그 말을 보면 무엇이 잘못됐나 찾게 된다. */
        if (! config(self::설정[$code] ?? '', true)) {
            return '';
        }

        try {
            $out = $this->send($return, null, $code);
        } catch (\Throwable $e) {
            Log::warning('[반품] 자동 안내 실패', [
                'receipt' => $return->receipt_no, 'code' => $code, 'error' => $e->getMessage(),
            ]);

            return ' 환자 안내는 보내지 못했습니다.';
        }

        return $out['sent']
            ? ' 환자에게 ' . $out['message']
            : ' 환자 안내는 보내지 못했습니다 — ' . $out['message'];
    }
}
