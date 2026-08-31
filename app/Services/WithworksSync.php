<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Withworks 물류 진행 상태를 끌어온다.
 *
 * 판매주문을 넘긴 뒤의 일 — 확정·할당·피킹·송장·출고·배송 — 은 Withworks 안에서 일어나고
 * 우리는 결과만 본다. Withworks 가 우리를 불러 주는 콜백이 아직 없어서 우리가 물어본다.
 *
 * 예전에는 담당자가 주문 상세를 열 때만 물어봤다. 그래서 아무도 안 열면 목록의 상태가
 * 며칠씩 옛것이었고, 배송이 끝난 주문이 계속 '출고 대기'로 남아 청구 대상에서도 빠졌다.
 * 지금은 진행 중인 주문을 주기적으로 훑는다(withworks:sync).
 *
 * 콜백이 생기면 이 클래스는 그대로 두고 부르는 쪽만 바꾸면 된다 — 저장하는 모양이 같다.
 */
class WithworksSync
{
    /** 아직 끝나지 않아 상태가 더 바뀔 주문 */
    public const OPEN_STATUSES = ['pending', 'confirmed', 'shipping'];

    /**
     * 컬럼이 담을 수 있는 길이.
     *
     * 남이 보내는 값이라 무엇이 올지 우리가 정하지 않는다. 넘치면 저장이 실패해 500 이 나가고,
     * 500 을 받은 쪽은 계속 다시 보낸다. 요약 칸은 잘라서라도 받는다 — 원본은 사건 표에 남는다.
     */
    private const WIDTH = [
        'withworks_status'            => 50,
        'withworks_status_label'      => 50,
        'withworks_ship_no'           => 50,
        'withworks_ship_status'       => 50,
        'withworks_ship_status_label' => 100,
        'withworks_tracking_no'       => 100,
    ];

    public function configured(): bool
    {
        return (bool) (config('services.demoworks.api_url') && config('services.demoworks.token'));
    }

    /**
     * 주문 하나의 상태를 물어와 저장한다.
     *
     * 돌려주는 값은 Withworks 가 준 원본이다. 화면이 세부 항목을 더 보여 줄 때 쓴다.
     * 못 물어봤으면 null 이다 — 실패를 성공과 구분해야 이전 값을 그대로 둘지 판단할 수 있다.
     */
    public function pull(Order $order): ?array
    {
        if (!$order->withworks_so_no || !$this->configured()) {
            return null;
        }

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');

        try {
            $res = Http::withToken(config('services.demoworks.token'))->timeout(8)
                ->get("{$baseUrl}/api/v1/ce-admin/so_show", [
                    'ce_order_number' => $order->order_number,
                ]);

            if (!$res->successful() || !($res->json('success') ?? false)) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('Withworks 상태 조회 실패', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return null;
        }

        $result = $res->json('result') ?? [];
        $this->apply($order, $result, full: true);   // 조회는 그 순간의 전체 상태다

        return $result;
    }

    /**
     * 받아 온 상태를 주문에 옮긴다.
     *
     * 콜백이 생기면 그쪽에서도 이 메서드를 부르면 된다.
     */
    /**
     * 창고의 출고 상태값(withworks_ship_status).
     *
     * 판매주문 상태(withworks_status)와 다른 체계다 — 그쪽은 02 등록ㆍ03 확정ㆍ95 확정ㆍ
     * 99 취소이고, 이쪽이 출고가 어디까지 왔는지를 말한다. 둘을 섞으면 「95」가 판매
     * 확정인지 출고완료인지 갈리지 않는다.
     *
     * 창고 화면의 고르는 칸에 있는 그대로다(2026-08-31 확인).
     */
    public const SHIP_STATUS = [
        '02' => '신규',              '9'  => '취소',
        '14' => '부분할당',          '15' => '부분할당/부분피킹',
        '16' => '부분할당/부분출고', '17' => '할당완료',
        '51' => '피킹중',            '52' => '부분피킹',
        '53' => '부분피킹/부분출고', '55' => '피킹완료',
        '57' => '피킹완료/부분출고', '92' => '부분출고',
        '93' => '출고확정대기',      '95' => '출고완료',
        '98' => '오더종결',
    ];

    /**
     * 환자에게 「보냈습니다」라고 말해도 되는 상태.
     *
     * 부분출고(16ㆍ53ㆍ57ㆍ92)는 넣지 않는다. 나머지가 남아 있는데 발송 안내를 보내면
     * 환자는 전량이 온 줄 알고 기다리다 다시 묻는다. 오더종결(98)은 넣는다 — 출고완료를
     * 놓치고 종결만 본 건에서도 알림은 나가야 하고, 두 번 보내는 것은 ShipNotice 가 막는다.
     */
    public const SHIPPED = ['95', '98'];

    public function apply(Order $order, array $result, bool $full = false): void
    {
        $shipBefore = (string) $order->withworks_ship_status;

        /* 온 것만 덮는다. 웹훅 한 건은 그때 바뀐 것만 담을 수 있어서, 없는 값을 null 로 밀어
           넣으면 확정 알림 하나에 앞서 받은 송장이 지워진다. 「값이 없다」와 「이번에 안
           왔다」는 다르다.

           조회(so_show)는 그 순간의 전체 상태라 이야기가 다르다. 거기서 빠진 것은 정말로
           없어진 것이므로 $full 로 알려 지우게 한다 — 그러지 않으면 취소된 배송 정보가 남는다. */
        $update = ['withworks_status_at' => now()];

        if ($full) {
            $update += [
                'withworks_status'            => null,
                'withworks_status_label'      => null,
                'withworks_ship_no'           => null,
                'withworks_ship_status'       => null,
                'withworks_ship_status_label' => null,
                'withworks_tracking_no'       => null,
                'withworks_ship_at'           => null,
                'shipped_at'                  => null,
            ];
        }

        foreach (['status' => 'withworks_status', 'status_label' => 'withworks_status_label'] as $from => $to) {
            if (array_key_exists($from, $result)) {
                $update[$to] = $this->fit($result[$from], self::WIDTH[$to]);
            }
        }

        if (array_key_exists('ship', $result) && is_array($result['ship'])) {
            $ship = $result['ship'];
            foreach ([
                'ship_no'          => 'withworks_ship_no',
                'ship_status'      => 'withworks_ship_status',
                'ship_status_label' => 'withworks_ship_status_label',
                'tracking_no'      => 'withworks_tracking_no',
            ] as $from => $to) {
                if (array_key_exists($from, $ship)) {
                    $update[$to] = $this->fit($ship[$from], self::WIDTH[$to]);
                }
            }
            $update['withworks_ship_at'] = now();

            /* 창고가 알려 주는 출고일. 바로 위의 withworks_ship_at 과 다른 값이다 —
               그것은 우리가 받아 적은 시각이라, 웹훅이 실패해 열 분 뒤 훑기가 채우면
               열 분 늦은 날이 적힌다. 청구 기한(출고일+2주)이 이 날을 센다. */
            if (($shippedAt = $ship['shipped_at'] ?? null)) {
                $update['shipped_at'] = \Carbon\Carbon::parse($shippedAt)->toDateString();
            }

            // 송장이 나오면 우리 주문에도 옮겨 둔다. 목록·청구가 이 컬럼을 본다.
            if (($ship['tracking_no'] ?? null) && !$order->tracking_number) {
                $update['tracking_number'] = $ship['tracking_no'];
            }
        }

        $order->update($update);

        if (isset($result['ship']['items']) && is_array($result['ship']['items'])) {
            $this->applyLots($order, $result['ship']['items']);
        }

        /* 출고로 막 바뀌었으면 환자에게 알린다.

           웹훅(so.shipped)에서도 부르지만 여기에도 둔다 — 웹훅이 몇 번 실패한 건은 10분마다
           도는 훑기가 상태를 맞추는데, 알림이 웹훅에만 있으면 그 건은 문자도 빠진다.
           두 번 나가는 것은 ShipNotice 가 발송 이력으로 막는다. */
        $shipAfter = (string) $order->refresh()->withworks_ship_status;

        if ($shipAfter !== $shipBefore && in_array($shipAfter, self::SHIPPED, true)) {
            app(ShipNotice::class)->send($order);

            /* 입금이 먼저 들어온 건은 그때 발행을 미뤄 두었다(요청서 8ㆍ9쪽 —
               「입금 및 출고 되어야」). 이제 출고됐으니 낸다.

               두 번 내지 않는다 — 이미 발행된 것은 DepositAutoIssue 가 거른다.
               입금이 아직이면 거기서 물러나고, 나중에 입금이 확인될 때 그쪽에서 낸다. */
            app(DepositAutoIssue::class)->run($order, '출고 확인');
        }
    }

    /**
     * 출고한 Lot 과 유효기간을 주문 줄에 적는다 (요청서 2쪽, 2026-08-31).
     *
     * 제품코드로 짝짓는다. 우리 주문에 없는 코드가 오면 적지 않고 남긴다 — 짐작으로
     * 아무 줄에나 붙이면 그 물건의 유효기간이 딴 제품의 것이 된다. 원본은 사건 표
     * (withworks_events.payload)에 통째로 남아 나중에 볼 수 있다.
     *
     * 같은 사건이 다시 와도 줄이 겹치지 않는다 — Lot 번호로 갱신한다.
     */
    private function applyLots(Order $order, array $items): void
    {
        $byCode = $order->items()->get()->keyBy(fn ($i) => (string) $i->product_code);

        foreach ($items as $row) {
            $code = (string) ($row['product_code'] ?? '');
            $lot  = trim((string) ($row['lot_no'] ?? ''));

            // Lot 번호가 없으면 적을 것이 없다 — 유효기간만으로는 무엇의 것인지 모른다
            if ($lot === '' || !($item = $byCode[$code] ?? null)) {
                if ($lot !== '') {
                    Log::warning('Withworks 출고 Lot — 짝이 없는 제품코드', [
                        'order' => $order->order_number, 'code' => $code, 'lot' => $lot,
                    ]);
                }

                continue;
            }

            $item->lots()->updateOrCreate(
                ['lot_no' => mb_substr($lot, 0, 100)],
                [
                    'expiry_date' => ($row['expiry_date'] ?? null)
                        ? \Carbon\Carbon::parse($row['expiry_date'])->toDateString() : null,
                    'quantity'    => isset($row['quantity']) ? (int) $row['quantity'] : null,
                ]
            );
        }
    }

    /** 칸에 들어갈 만큼만 남긴다 */
    private function fit($value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $max);
    }

    /**
     * 아직 진행 중인 주문을 훑는다.
     *
     * 이미 끝난 주문(delivered·cancelled)은 더 바뀔 것이 없어 건너뛴다. 매번 전부 물어보면
     * 주문이 쌓일수록 느려지고 Withworks 에도 부담이다.
     */
    public function sweep(int $limit = 200): array
    {
        if (!$this->configured()) {
            return ['configured' => false, 'checked' => 0, 'updated' => 0];
        }

        $orders = Order::whereNotNull('withworks_so_no')
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderBy('withworks_status_at')          // 오래 안 본 것부터
            ->limit($limit)
            ->get();

        $updated = 0;
        foreach ($orders as $order) {
            $before = $order->withworks_status . '|' . $order->withworks_ship_status;
            if ($this->pull($order) !== null) {
                $order->refresh();
                if ($before !== $order->withworks_status . '|' . $order->withworks_ship_status) {
                    $updated++;
                }
            }
        }

        return ['configured' => true, 'checked' => $orders->count(), 'updated' => $updated];
    }
}
