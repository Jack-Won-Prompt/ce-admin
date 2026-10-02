<?php

namespace App\Services\TossPayments;

use App\Models\DuplicatePaymentRefund;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\TossPayment;
use Illuminate\Support\Carbon;

/**
 * 한 주문에 두 번 이상 들어온 결제를 찾는다 (2026-10-02 지시).
 *
 * ## 왜 토스에 묻는가 — 우리 표에는 없기 때문이다
 *
 * 2026-10-01 (E)윤채우 건에서 드러났다. 81,000원이 두 번 승인됐는데(13:33:15 ·
 * 13:34:27) 우리 장부는 하나만 알았다. 세 표가 모두 그 사실을 담지 못한다 —
 *
 *   toss_payments   `firstOrNew(['order_id' => …])` 라 뒤 결제가 앞 결제를 덮는다
 *   payment_links   링크는 하나뿐이고 paid_at 도 하나다
 *   payment_events  두 번째 승인 때 링크는 이미 `paid` 라 상태가 바뀌지 않아
 *                   PaymentLinkObserver 가 걸음을 적지 않는다(실측 0건)
 *
 * 그러니 **토스가 정본**이다. 저쪽 거래 목록을 받아 우리 주문에 되짚어 맞춘다.
 *
 * ## 되짚는 길
 *
 * 토스에 넘기는 주문번호가 두 꼴이다.
 *
 *   카드ㆍ간편결제   `{payment_links.token 앞 40자}-{무작위 8자}`
 *                   (pay/show.blade.php 의 newOrderId)
 *   가상계좌         `CE-{주문번호}-{YmdHis}`
 *                   (VirtualAccountService:27)
 *
 * 둘 다 거꾸로 읽힌다. 앞엣것은 토큰 앞자리로 링크를 찾고, 뒤엣것은 주문번호를
 * 그대로 품고 있다.
 *
 * ## 두 걸음으로 가린다
 *
 * 거래 목록은 **상태가 바뀔 때마다 한 줄씩** 쌓인다 — 같은 결제키가
 * `WAITING_FOR_DEPOSIT` 과 `DONE` 두 줄로 온다. 그래서 목록만으로 금액을 세면
 * 틀린다.
 *
 *   ① 목록으로 **후보**만 추린다 — 한 주문에 결제키가 둘 이상인 건
 *   ② 그 후보의 결제키만 `/v1/payments/{key}` 로 하나씩 **확정**한다
 *
 * 저쪽을 부르는 수를 후보로 좁히려는 것이다. 하루 47건을 모두 상세 조회하면
 * 화면이 그만큼 기다린다.
 */
class DuplicatePaymentFinder
{
    /** 한 번에 받아 오는 줄 수 — 토스가 받는 가장 큰 값보다 넉넉히 작게 둔다 */
    private const 묶음 = 100;

    /** 몇 번까지 이어 받을 것인가 — 끝없이 도는 일이 없게 둔다 */
    private const 최대묶음 = 50;

    public function __construct(private readonly TossClient $toss) {}

    /**
     * @param  string  $from  YYYY-MM-DD
     * @param  string  $to    YYYY-MM-DD (그날까지 들어온다)
     * @return array{rows: array, scanned: int, candidates: int, queried: int}
     */
    public function 찾기(string $from, string $to): array
    {
        $거래 = $this->거래목록($from, $to);

        /* ① 결제키별로 묶는다. 같은 키의 여러 줄은 한 결제의 걸음일 뿐이다. */
        $키별 = [];
        foreach ($거래 as $줄) {
            $키 = $줄['paymentKey'] ?? null;
            if (! $키) {
                continue;
            }
            $키별[$키] ??= ['orderId' => $줄['orderId'] ?? null, 'method' => $줄['method'] ?? null, 'at' => null];
            /* 가장 나중 걸음의 때를 들고 있는다 — 승인 시각은 상세에서 다시 받는다 */
            $때 = $줄['transactionAt'] ?? null;
            if ($때 && (! $키별[$키]['at'] || $때 > $키별[$키]['at'])) {
                $키별[$키]['at'] = $때;
            }
        }

        /* ② 결제키를 우리 주문에 맞춘다 */
        $주문별 = [];
        foreach ($키별 as $키 => $것) {
            $order = $this->주문찾기((string) $것['orderId']);
            if (! $order) {
                continue;
            }
            $주문별[$order->id] ??= ['order' => $order, 'keys' => []];
            $주문별[$order->id]['keys'][$키] = $것;
        }

        /* ③ 결제키가 둘 이상인 주문만 후보다 */
        $후보 = array_filter($주문별, fn ($것) => count($것['keys']) > 1);

        $rows = [];
        $물어본수 = 0;

        foreach ($후보 as $것) {
            /** @var Order $order */
            $order   = $것['order'];
            $장부키  = $order->tossPayment?->payment_key;
            $결제들  = [];

            foreach ($것['keys'] as $키 => $거래것) {
                $상세 = $this->상세($키);
                $물어본수++;

                if (! $상세) {
                    continue;
                }

                $상태 = $상세['status'] ?? '-';

                /* 살아 있는 승인만 센다 — 대기ㆍ중단ㆍ전액취소는 받은 돈이 아니다 */
                if (! in_array($상태, ['DONE', 'PARTIAL_CANCELED'], true)) {
                    continue;
                }

                $총액  = (int) ($상세['totalAmount'] ?? 0);
                $무른것 = collect($상세['cancels'] ?? [])->sum(fn ($c) => (int) ($c['cancelAmount'] ?? 0));
                $남은것 = $총액 - $무른것;

                if ($남은것 <= 0) {
                    continue;
                }

                $결제들[] = [
                    'payment_key'   => $키,
                    'toss_order_id' => $상세['orderId'] ?? $거래것['orderId'],
                    'method'        => $상세['method'] ?? $거래것['method'],
                    'amount'        => $남은것,
                    'total_amount'  => $총액,
                    'cancelled'     => $무른것,
                    'approved_at'   => $상세['approvedAt'] ?? null,
                    /* 장부(toss_payments)가 아는 그 결제인가 — 이쪽은 무르면 안 된다 */
                    'in_ledger'     => $장부키 !== null && $장부키 === $키,
                    /* 이미 올라와 있는 환불 건 */
                    'refund'        => DuplicatePaymentRefund::살아있는것($키),
                ];
            }

            /* 살아 있는 승인이 둘 이상일 때만 중복이다 */
            if (count($결제들) < 2) {
                continue;
            }

            usort($결제들, fn ($a, $b) => ($a['approved_at'] ?? '') <=> ($b['approved_at'] ?? ''));

            $받은합 = array_sum(array_column($결제들, 'amount'));

            $rows[] = [
                'order'      => $order,
                'payments'   => $결제들,
                'paid_sum'   => $받은합,
                'ledger_sum' => $order->받은금액(),
                'expected'   => (int) $order->expectedDeposit(),
                'excess'     => $받은합 - (int) $order->expectedDeposit(),
            ];
        }

        usort($rows, fn ($a, $b) => $b['excess'] <=> $a['excess']);

        return [
            'rows'       => $rows,
            'scanned'    => count($키별),
            'candidates' => count($후보),
            'queried'    => $물어본수,
        ];
    }

    /** 기간 안의 모든 거래 — 토스는 상태가 바뀔 때마다 한 줄씩 준다 */
    private function 거래목록(string $from, string $to): array
    {
        $시작 = Carbon::parse($from)->startOfDay()->format('Y-m-d\TH:i:s');
        /* 끝날도 들어와야 하므로 다음 날 0시까지 본다 */
        $끝   = Carbon::parse($to)->addDay()->startOfDay()->format('Y-m-d\TH:i:s');

        $모두 = [];
        $커서 = null;

        for ($i = 0; $i < self::최대묶음; $i++) {
            $길 = "/v1/transactions?startDate={$시작}&endDate={$끝}&limit=" . self::묶음
                . ($커서 ? '&startingAfter=' . urlencode($커서) : '');

            $묶음 = $this->toss->get($길);

            if (! is_array($묶음) || ! $묶음) {
                break;
            }

            foreach ($묶음 as $줄) {
                $모두[] = $줄;
            }

            if (count($묶음) < self::묶음) {
                break;
            }

            /* 다음 묶음은 마지막 거래 다음부터 — 겹치지 않는다(2026-10-02 확인) */
            $커서 = end($묶음)['transactionKey'] ?? null;

            if (! $커서) {
                break;
            }
        }

        return $모두;
    }

    /** 결제 하나를 확정해서 본다. 못 물어보면 null — 그 결제는 세지 않는다. */
    private function 상세(string $paymentKey): ?array
    {
        try {
            return $this->toss->get('/v1/payments/' . urlencode($paymentKey));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 토스 주문번호로 우리 주문을 되짚는다.
     *
     * 가상계좌는 주문번호를 그대로 품고 있고, 그 밖은 결제 링크 토큰의 앞 40자다.
     */
    private function 주문찾기(string $tossOrderId): ?Order
    {
        if ($tossOrderId === '') {
            return null;
        }

        if (str_starts_with($tossOrderId, 'CE-')
            && preg_match('/^CE-(.+)-\d{14}$/', $tossOrderId, $m)) {
            $order = Order::with(['patient', 'tossPayment'])
                ->where('order_number', $m[1])->first();

            if ($order) {
                return $order;
            }
        }

        /* 링크 토큰 앞 40자 — 토큰은 그보다 길어 앞자리만으로도 한 건으로 좁혀진다 */
        $앞 = substr($tossOrderId, 0, 40);

        if (strlen($앞) < 20) {
            return null;
        }

        $link = PaymentLink::where('token', 'like', $앞 . '%')
            ->with('order.patient', 'order.tossPayment')->first();

        if ($link?->order) {
            return $link->order;
        }

        /* 마지막으로 장부에 적힌 토스 주문번호로도 찾아 본다 */
        $tp = TossPayment::where('toss_order_id', $tossOrderId)->first();

        return $tp?->order?->loadMissing(['patient', 'tossPayment']);
    }
}
