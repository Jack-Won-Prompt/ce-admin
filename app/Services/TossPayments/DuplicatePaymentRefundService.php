<?php

namespace App\Services\TossPayments;

use App\Models\DuplicatePaymentRefund;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\PaymentLink;
use App\Models\Patient;
use App\Services\MessageSender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 중복으로 받은 돈을 돌려준다 — **요청과 승인을 나눈다** (2026-10-02 지시).
 *
 * 결제 취소는 되돌릴 수 없고 곧 돈이 오가는 일이다. 담당자가 올리고 최종승인자가
 * 승인해야 저쪽에 간다. 반품 결재(OrderReturn::APPROVAL_PERMS)와 같은 결이다.
 *
 * ## 장부에 있는 결제는 무를 수 없다
 *
 * `toss_payments` 는 한 주문 한 줄이라, 거기 적힌 결제키가 곧 **그 주문이 받은
 * 돈**이다. 그것을 무르면 `받은금액()` 이 0 으로 읽혀 정산ㆍ증빙ㆍ정정이 모두
 * 어긋나고, 카드매출전표도 없는 결제를 가리키게 된다.
 *
 * 그래서 **올릴 때와 승인할 때 두 번 가린다.** 올린 뒤 장부가 바뀔 수 있기
 * 때문이다(그 사이에 정정으로 재결제가 일어나면 장부의 결제키가 갈린다).
 */
class DuplicatePaymentRefundService
{
    /** 문구를 어디서 가져오는가 — 담당자가 메시지 유형에서 고칠 수 있다 */
    public const 안내틀 = 'payment_refund';

    /** 발송 이력에 남는 이름 */
    public const SOURCE = 'duplicate-refund';

    public function __construct(
        private readonly TossClient $toss,
        private readonly MessageSender $sender,
    ) {}

    /** 마스터에 유형이 없을 때 쓰는 말 */
    public static function 기본문구(): string
    {
        return "[콜로플라스트] #{고객명}님, 중복으로 결제된 #{취소금액}원의 결제를 취소했습니다.
"
             . "주문번호: #{주문번호}\n"
             . '카드 취소는 카드사에 따라 영업일 기준 3~5일이 걸릴 수 있습니다.';
    }

    /* ── 걸음 ① 올리기 ─────────────────────────── */

    /**
     * @param  array  $결제  DuplicatePaymentFinder 가 돌려준 결제 한 줄
     * @return array{ok: bool, message: string, refund?: DuplicatePaymentRefund}
     */
    public function 요청(Order $order, array $결제, ?string $note = null): array
    {
        $키 = (string) ($결제['payment_key'] ?? '');

        if ($키 === '') {
            return ['ok' => false, 'message' => '결제키가 없습니다.'];
        }

        if ($막는말 = $this->장부검사($order, $키)) {
            return ['ok' => false, 'message' => $막는말];
        }

        if ($이미 = DuplicatePaymentRefund::살아있는것($키)) {
            return ['ok' => false, 'message' => '이미 올라와 있는 건입니다 — ' . $이미->상태말() . '.'];
        }

        $링크 = PaymentLink::where('payment_key', $키)->value('id');

        $건 = DuplicatePaymentRefund::create([
            'order_id'        => $order->id,
            'payment_link_id' => $링크,
            'payment_key'     => $키,
            'toss_order_id'   => $결제['toss_order_id'] ?? null,
            'method'          => $결제['method'] ?? null,
            'amount'          => (int) ($결제['amount'] ?? 0),
            'approved_at'     => $결제['approved_at'] ?? null,
            'status'          => DuplicatePaymentRefund::요청,
            'requested_by'    => Auth::id(),
            'requested_at'    => now(),
            'request_note'    => $note ? mb_substr($note, 0, 255) : null,
        ]);

        activity()->performedOn($order)->causedBy(Auth::user())->log(
            '중복 결제 취소 요청 — ' . number_format((int) $건->amount) . '원 (결제키 ' . $키 . ')'
        );

        return ['ok' => true, 'message' => '결제 취소 요청을 올렸습니다. 최종승인자의 승인이 필요합니다.', 'refund' => $건];
    }

    /* ── 걸음 ② 승인하고 실제로 무르기 ───────────── */

    /** @return array{ok: bool, message: string} */
    public function 승인(DuplicatePaymentRefund $건): array
    {
        if ($건->status !== DuplicatePaymentRefund::요청) {
            return ['ok' => false, 'message' => '승인을 기다리는 건이 아닙니다 — 지금은 「' . $건->상태말() . '」입니다.'];
        }

        $order = $건->order;

        if (! $order) {
            return ['ok' => false, 'message' => '이어진 주문을 찾지 못했습니다.'];
        }

        /* 올린 뒤에 장부가 갈렸을 수 있다 — 승인하는 이 자리에서 다시 가린다 */
        if ($막는말 = $this->장부검사($order, $건->payment_key)) {
            return ['ok' => false, 'message' => $막는말];
        }

        /* 저쪽에 아직 살아 있는 결제인지 확인한다 — 그 사이 누가 토스 화면에서
           물렀을 수 있다. 없는 것을 또 무르면 토스가 거절하고, 그 말은 담당자에게
           뜻이 닿지 않는다. */
        try {
            $상세 = $this->toss->get('/v1/payments/' . urlencode($건->payment_key));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => '토스에서 결제를 조회하지 못했습니다 — ' . mb_substr($e->getMessage(), 0, 120)];
        }

        $무른것 = collect($상세['cancels'] ?? [])->sum(fn ($c) => (int) ($c['cancelAmount'] ?? 0));
        $남은것 = (int) ($상세['totalAmount'] ?? 0) - $무른것;

        if ($남은것 <= 0) {
            $건->update([
                'status'        => DuplicatePaymentRefund::완료,
                'approved_by'   => Auth::id(),
                'approved_at_by' => now(),
                'refunded_at'   => now(),
                'toss_response' => $상세,
            ]);

            return ['ok' => true, 'message' => '이미 전액 취소된 결제였습니다 — 그대로 완료로 적었습니다.'];
        }

        /* 가상계좌로 받은 돈은 왔던 길로 돌아가지 않는다 — 돌려줄 계좌가 있어야
           한다. 지금 화면은 그 계좌를 받지 않으므로 여기서 멈추고 알린다. */
        if (($상세['method'] ?? '') === '가상계좌' || ($건->method ?? '') === '가상계좌') {
            return ['ok' => false, 'message' => '가상계좌로 받은 건은 돌려줄 계좌가 있어야 합니다 — 정산/회계에서 처리해 주십시오.'];
        }

        try {
            $res = $this->toss->post('/v1/payments/' . $건->payment_key . '/cancel', [
                'cancelReason' => '중복 결제 취소',
                'cancelAmount' => min($남은것, (int) $건->amount),
            ]);
        } catch (\Throwable $e) {
            $건->update([
                'status'         => DuplicatePaymentRefund::실패,
                'approved_by'    => Auth::id(),
                'approved_at_by' => now(),
                'toss_response'  => ['error' => mb_substr($e->getMessage(), 0, 500)],
            ]);

            Log::warning('[중복결제] 결제 취소 실패', [
                'refund' => $건->id, 'key' => $건->payment_key, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => '토스가 결제 취소를 거절했습니다 — ' . mb_substr($e->getMessage(), 0, 150)];
        }

        $건->update([
            'status'         => DuplicatePaymentRefund::완료,
            'approved_by'    => Auth::id(),
            'approved_at_by' => now(),
            'refunded_at'    => now(),
            'toss_response'  => $res,
        ]);

        /* 돈이 오간 걸음을 남긴다 — 이 표는 고치지 않고 쌓는다(payment_events).
           중복분은 장부(toss_payments)에 담을 자리가 없으므로 여기에만 남는다. */
        try {
            PaymentEvent::create([
                'order_id'        => $order->id,
                'payment_link_id' => $건->payment_link_id,
                'kind'            => 'refunded',
                'method'          => $건->method,
                'amount'          => -(int) $건->amount,
                'occurred_at'     => now(),
                'payment_key'     => $건->payment_key,
                'note'            => '중복 결제 취소',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[중복결제] 걸음을 적지 못함', ['refund' => $건->id, 'error' => $e->getMessage()]);
        }

        activity()->performedOn($order)->causedBy(Auth::user())->log(
            '중복 결제 취소 승인·처리 — ' . number_format((int) $건->amount) . '원 (결제키 ' . $건->payment_key . ')'
        );

        $알림 = $this->안내($건);

        return [
            'ok'      => true,
            'message' => number_format((int) $건->amount) . '원 결제를 취소했습니다. ' . $알림['message'],
        ];
    }

    /* ── 걸음 ③ 반려 ───────────────────────────── */

    public function 반려(DuplicatePaymentRefund $건, string $까닭): array
    {
        if (! $건->기다리는중인가()) {
            return ['ok' => false, 'message' => '되돌릴 수 있는 걸음이 아닙니다 — 지금은 「' . $건->상태말() . '」입니다.'];
        }

        $건->update([
            'status'         => DuplicatePaymentRefund::반려,
            'approved_by'    => Auth::id(),
            'approved_at_by' => now(),
            'reject_reason'  => mb_substr($까닭, 0, 255),
        ]);

        activity()->performedOn($건->order)->causedBy(Auth::user())->log(
            '중복 결제 취소 반려 — ' . number_format((int) $건->amount) . '원 · ' . $까닭
        );

        return ['ok' => true, 'message' => '반려했습니다.'];
    }

    /* ── 안쪽 ──────────────────────────────────── */

    /** 장부가 아는 결제면 무를 수 없다 — 막는 말을 돌려준다. 괜찮으면 null. */
    private function 장부검사(Order $order, string $paymentKey): ?string
    {
        $장부키 = $order->loadMissing('tossPayment')->tossPayment?->payment_key;

        if ($장부키 !== null && $장부키 === $paymentKey) {
            return '이 결제는 주문의 결제로 장부에 적혀 있습니다 — 무르면 받은 돈이 0으로 읽혀 '
                 . '정산과 증빙이 어긋납니다. 중복으로 더 들어온 쪽을 골라 주십시오.';
        }

        return null;
    }

    /** 결제를 취소했음을 고객에게 알린다 — 못 보내도 취소는 이미 끝난 일이다 */
    private function 안내(DuplicatePaymentRefund $건): array
    {
        $order  = $건->order;
        $mobile = preg_replace('/\D/', '', (string) ($order->patient?->mobile ?? ''));

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            $건->update(['notify_result' => '연락처가 없어 보내지 못했습니다']);

            return ['sent' => false, 'message' => '고객 연락처가 없어 안내는 보내지 못했습니다.'];
        }

        $name = Patient::bare($order->patient?->name) ?: '고객';

        $body = MessageTemplate::channel('sms')->active()
            ->where('code', self::안내틀)->value('body') ?: self::기본문구();

        $text = strtr($body, [
            '#{고객명}'   => $name,
            '#{이름}'     => $name,
            '#{주문번호}' => (string) $order->order_number,
            '#{취소금액}' => number_format((int) $건->amount),
            /* 옛 이름도 받아 준다 — 담당자가 고쳐 둔 문구가 이 이름을 쓸 수 있다 */
            '#{환불금액}' => number_format((int) $건->amount),
        ]);

        try {
            /* 발송 방식은 채널마다 한 곳이 정한다 — 알림톡 우선ㆍ실패 시 문자 */
            ['보낸채널' => $보낸채널, '못보낸말' => $못보낸말] =
                MessageTemplate::채널마다(self::안내틀,
                    fn (string $채널, ?string $틀코드) => $this->sender->sendBulk(
                        $채널,
                        [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $order->patient_id]],
                        $text,
                        $틀코드 ?: self::안내틀,
                        ['source' => self::SOURCE, 'prescription_id' => $order->prescription_id],
                    ),
                    /* 알림톡 유형이 아직 승인 전이라 서 있지 않다 — 그래도 문자는
                       나가야 하므로 틀 없이도 보낸다(PaymentDoneNotice 와 같다). */
                    문자는틀없이도: true);

            /* 둘 다 배열이다 — 보낸 채널들과 못 보낸 까닭들 */
            if ($보낸채널) {
                $이름들 = implode('·', array_map(
                    fn ($c) => MessageTemplate::채널이름($c), $보낸채널));

                $건->update(['notified_at' => now(), 'notify_result' => $이름들 . ' 발송']);

                activity()->performedOn($order)->log("중복 결제 취소 안내 발송 → {$mobile}");

                return ['sent' => true, 'message' => '고객에게 ' . $이름들 . '으로 안내했습니다.'];
            }

            $까닭 = implode(' / ', $못보낸말) ?: '보내지 못했습니다.';
            $건->update(['notify_result' => mb_substr($까닭, 0, 255)]);

            Log::warning('[중복결제] 취소 안내를 보내지 못했다', [
                'refund' => $건->id, 'error' => $까닭,
            ]);

            return ['sent' => false, 'message' => '안내를 보내지 못했습니다 — ' . $까닭];
        } catch (\Throwable $e) {
            Log::warning('[중복결제] 취소 안내 실패', ['refund' => $건->id, 'error' => $e->getMessage()]);
            $건->update(['notify_result' => mb_substr($e->getMessage(), 0, 255)]);

            return ['sent' => false, 'message' => '안내를 보내지 못했습니다.'];
        }
    }
}
