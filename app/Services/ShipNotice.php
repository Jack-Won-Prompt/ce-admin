<?php

namespace App\Services;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * 출고했음을 환자에게 알린다.
 *
 * 창고가 물건을 내보내면(so.shipped) 그때 문자를 보낸다. 위드웍스는 배송 완료를 알려
 * 주지 않기로 했으므로 — 택배사 조회 연동이 없어 보낼 값이 없다 — 배송에 관해 우리가
 * 아는 마지막 시점이 출고다. 여기서 알리지 않으면 환자는 물건이 언제 오는지 모른 채
 * 담당자에게 전화한다.
 *
 * 보내는 자리에 사람이 없다. 화면 조작이 아니라 창고가 부르는 길이라, 실패해도 그
 * 자리에서 알릴 상대가 없다 — 로그와 발송 이력에만 남는다.
 */
class ShipNotice
{
    /** 이 안내를 발송 이력에 어떤 이름으로 남기는가 — 두 번 보내지 않으려고 이걸로 견준다 */
    public const SOURCE = 'ship-notice';

    /** 문구를 어디서 가져오는가 — 담당자가 메시지 유형에서 고칠 수 있다 */
    private const TEMPLATE = 'shipping_started';

    public function __construct(private readonly MessageSender $sender) {}

    /** 운송장을 얼마나 기다리는가 — 그 뒤에는 번호 없이라도 보낸다 */
    private const 운송장대기분 = 60;

    /**
     * @param bool $force 운송장을 더 기다리지 않는다 (훑기가 쓴다)
     *
     * @return array{sent: bool, reason: ?string}
     */
    public function send(Order $order, bool $force = false): array
    {
        $no = fn (?string $why) => ['sent' => false, 'reason' => $why];

        if (!config('order.ship_notice_on_shipped')) {
            return $no(null);                       // 꺼 두었으면 말도 하지 않는다
        }

        $order->loadMissing('patient', 'prescription');

        /* 운송장이 없어도 보낸다. 창고가 출고완료로 올리면서 송장을 아직 주지 않은 건이
           있는데(그런 건이 실제로 쌓여 있다), 그때 아무 말도 하지 않으면 환자는 물건이
           오는지조차 모른다. 번호가 없으면 그 줄만 빼고 보낸다. */
        $tracking = self::운송장정리($order->tracking_number ?: $order->withworks_tracking_no);

        /* 다만 잠깐은 기다린다 (2026-09-17 시험에서 드러남).

           운송장은 창고가 출고를 올린 **뒤에** 운송장 엑셀업로드로 들어온다
           (so.shipped 11:32 → so.invoiced 11:37). 출고 사건에 곧바로 보내면
           운송장 줄이 늘 빠져, 정상 순서로 일해도 환자는 번호 없는 문자만 받는다.

           운송장이 닿으면 그 사건이 이 자리를 다시 부른다. 끝내 오지 않으면 기다림이
           끝난 뒤 번호 없이 보낸다 — 늦게라도 알리는 편이 안 알리는 것보다 낫다. */
        if ($tracking === '' && ! $force && $this->운송장을기다리는중인가($order)) {
            return $no(null);
        }

        $mobile = preg_replace('/\D/', '', (string) ($order->patient?->mobile ?? ''));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return $no('연락처가 없어 배송 안내를 보내지 못했습니다.');
        }

        /* 두 번 보내지 않는다. 같은 사건이 다른 번호로 다시 오거나(웹훅), 10분마다 도는
           훑기가 같은 상태를 또 반영할 수 있다. 발송 이력으로 견준다 — 한 처방전에
           주문은 하나라 그것으로 가린다. */
        if ($this->alreadySent($order)) {
            return $no(null);
        }

        $text = $this->compose($order, $tracking);

        try {
            $res = $this->sender->sendBulk(
                'sms',
                [['rcv' => $mobile, 'rcvnm' => \App\Models\Patient::bare($order->patient?->name),
                  'patient_id' => $order->patient_id]],
                $text,
                self::TEMPLATE,
                ['source' => self::SOURCE, 'prescription_id' => $order->prescription_id],
            );
        } catch (\Throwable $e) {
            Log::warning('[배송 안내] 보내지 못했다', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return $no($e->getMessage());
        }

        if ($res['success'] ?? false) {
            activity()->performedOn($order)->log("배송 안내 발송 → {$mobile} ({$tracking})");

            return ['sent' => true, 'reason' => null];
        }

        Log::warning('[배송 안내] 보내지 못했다', ['order' => $order->order_number,
                                                  'message' => $res['message'] ?? null]);

        return $no($res['message'] ?? '보내지 못했습니다.');
    }

    /** 이미 이 건으로 배송 안내가 나갔는가 */
    private function alreadySent(Order $order): bool
    {
        if (!$order->prescription_id) {
            return false;   // 이어진 처방전이 없으면 견줄 것이 없다 — 보내고 만다
        }

        return MessageHistory::where('source', self::SOURCE)
            ->where('prescription_id', $order->prescription_id)
            ->where('success_count', '>', 0)
            ->exists();
    }

    /**
     * 보낼 말.
     *
     * 메시지 유형(SMS ▸ 배송 시작)에 적어 둔 글을 쓴다 — 담당자가 화면에서 고칠 수 있어야
     * 하고, 손으로 보낼 때와 문구가 갈리지 않아야 한다. 유형이 비어 있으면 코드에 둔 말로
     * 대신한다.
     */
    /**
     * 운송장 번호로 쓸 수 있는 값만 남긴다 (2026-09-17 시험에서 드러남).
     *
     * 창고에서 「-」ㆍ「.」처럼 자리만 채운 값이 올라오는 건이 있다. 그것을 번호로
     * 알고 그대로 실으면 문자에 「운송장: -」가 나간다 — 환자는 그것으로 조회한다.
     * 숫자가 섞이지 않은 값은 번호가 아니다.
     */
    public static function 운송장정리(?string $값): string
    {
        $값 = trim((string) $값);

        if ($값 === '' || preg_match('/\d/', $값) !== 1) {
            return '';
        }

        return $값;
    }

    /**
     * 아직 운송장을 기다릴 때인가 — 출고 사건이 온 지 얼마 되지 않았는가.
     *
     * 출고 시각은 창고 사건에 적힌 것을 본다(withworks_events). 사건이 없으면
     * 기다릴 근거가 없으므로 기다리지 않는다 — 옛 건이 영영 멈추면 안 된다.
     */
    private function 운송장을기다리는중인가(Order $order): bool
    {
        $출고 = \App\Models\WithworksEvent::where('order_id', $order->id)
            ->where('event', 'so.shipped')
            ->latest('id')
            ->value('occurred_at');

        if (! $출고) {
            return false;
        }

        return \Carbon\Carbon::parse($출고)->gt(now()->subMinutes(self::운송장대기분));
    }

    public function compose(Order $order, string $tracking): string
    {
        /* 거래처가 받는 글이다 — 사업부 접두 (E) 는 떼고 적는다 (2026-09-11 지시) */
        $name = \App\Models\Patient::bare($order->patient?->name) ?: '고객';

        $body = MessageTemplate::channel('sms')->active()
            ->where('code', self::TEMPLATE)->value('body');

        if (!$body) {
            $body = "[콜로플라스트] #{고객명}님, 제품이 발송되었습니다.\n"
                  . "주문번호: #{주문번호}\n운송장: #{운송장번호}";
        }

        /* 운송장이 없으면 그 줄을 통째로 뺀다 — 「운송장: 」만 남으면 빠뜨린 것처럼 읽힌다.
           담당자가 문구를 고쳐도 자리표만 찾으면 되므로 그대로 걸린다. */
        if ($tracking === '') {
            $body = implode("\n", array_filter(
                explode("\n", $body),
                fn ($line) => !str_contains($line, '#{운송장번호}'),
            ));
        }

        return strtr($body, [
            '#{고객명}'      => $name,
            '#{주문번호}'    => (string) $order->order_number,
            '#{운송장번호}'  => $tracking,
        ]);
    }
}
