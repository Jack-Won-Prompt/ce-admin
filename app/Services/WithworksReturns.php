<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 교환 · 반품 · 취소를 위드웍스로 넘긴다.
 *
 * 지금까지 되돌리는 건은 CEAdmin 안에서만 돌았다. 창고는 물건이 돌아온다는 것을
 * 알지 못했고, 우리는 창고가 무엇을 했는지 알 수 없었다.
 *
 * 창고가 하는 일은 셋 다 다르다. 반품은 수거해 넣고, 교환은 넣었다가 다시 내보내고,
 * 취소는 아무것도 움직이지 않는다. 그래서 판매유형도 셋으로 나뉜다
 * (취소 5004 · 반품 5005 · 교환 5006). 실제 코드값은 설정 화면이 정한다.
 *
 * 출고 전 취소만은 반품 주문을 세우지 않는다. 되돌릴 물건이 없는데 주문을 하나 더
 * 만들면 창고에 「아무것도 하지 않는 주문」이 쌓이고, 원 주문은 살아 있어 그대로
 * 나갈 위험이 남는다. 원 판매주문 자체를 취소하는 것이 맞다.
 */
class WithworksReturns
{
    /** 위드웍스가 알아듣는 구분값 — 코드값 대신 이름으로 보낸다 */
    private const TYPE_NAMES = [
        OrderReturn::TYPE_CANCEL   => 'cancel',
        OrderReturn::TYPE_RETURN   => 'return',
        OrderReturn::TYPE_EXCHANGE => 'exchange',
    ];

    public function configured(): bool
    {
        return (bool) (config('services.demoworks.api_url') && config('services.demoworks.token'));
    }

    /**
     * 반품을 위드웍스에 알린다.
     *
     * 아직 나가지 않은 물건이면 종류와 상관없이 원 판매주문을 취소하고,
     * 이미 나간 뒤면 종류대로 반품 판매주문을 세운다.
     * 어느 쪽이든 결과를 접수 건에 적어 둔다 — 실패했을 때 왜인지 화면에서 보여야 한다.
     *
     * 되돌려주는 값은 성공 여부다. 실패해도 접수 자체는 살려 둔다. 창고에 알리지
     * 못한 것과 고객의 신청을 받지 못한 것은 다른 일이다.
     */
    public function push(OrderReturn $return): bool
    {
        if (!$this->configured()) {
            return $this->fail($return, '위드웍스 연동 설정이 없습니다');
        }

        $order = $return->order;
        if (!$order) {
            return $this->fail($return, '주문을 찾을 수 없습니다');
        }

        if ($return->withworks_so_no) {
            return true;   // 이미 보냈다 — 접수번호로 멱등이지만 부르지 않는 편이 낫다
        }

        if (!$this->isPreShipCancel($return, $order)) {
            return $this->storeReturn($return, $order);
        }

        /* 부분이면 원 판매주문을 통째로 취소하지 않는다 — 남는 수량은 그대로 나가야 한다.
           수량을 줄이는 것은 Consumer Operation 이 위드웍스에서 직접 한다(절차서). */
        if ($return->is_partial) {
            return $this->fail($return, '부분 취소라 판매주문을 통째로 취소하지 않았습니다 — '
                . '위드웍스에서 수량을 줄여 주십시오 (Consumer Operation)');
        }

        return $this->cancelOrigin($return, $order);
    }

    /**
     * 아직 나가지 않은 물건인가.
     *
     * 송장이 채번되기 전이면 창고에서 물건이 나가지 않았다. 그때는 종류가 무엇이든
     * 되돌려 받을 물건이 없다 — 반품도 교환도 실질은 「이 주문을 없던 일로」다.
     * 반품 주문을 세우면 창고는 오지 않을 물건을 기다리고, 우리 표에는 나가지도 않은
     * 건의 수거·검수 단계가 남는다.
     *
     * 송장이 붙었으면 물건이 나간 것이므로 종류대로 반품 주문을 세운다.
     */
    private function isPreShipCancel(OrderReturn $return, Order $order): bool
    {
        return !$order->withworks_ship_no && !$order->withworks_tracking_no;
    }

    /** 원 판매주문을 취소한다 — 반품 주문을 만들지 않는다 */
    private function cancelOrigin(OrderReturn $return, Order $order): bool
    {
        $res = $this->call('post', 'so_cancel', [
            'ce_order_number' => $order->order_number,
            'so_no'           => $order->withworks_so_no,
        ]);

        if ($res === null || !($res['success'] ?? false)) {
            return $this->fail($return, $res['message'] ?? '판매주문 취소를 보내지 못했습니다');
        }

        $return->forceFill([
            'withworks_so_no'        => $order->withworks_so_no,
            'withworks_so_type'      => null,   // 새 주문을 세우지 않았다
            'withworks_status'       => '99',
            'withworks_status_label' => '판매주문 취소',
            'withworks_sent_at'      => now(),
            'withworks_error'        => null,
        ])->save();

        /* 주문 쪽도 맞춰 둔다 — 창고에서 취소된 주문이 우리 화면에 살아 있으면 안 된다.
           출고 전 취소는 물건이 안 나가는 것이 확정된 순간이라 여기서 바로 닫는다.
           반품·교환은 다르다 — 검수에서 되돌아오는 일이 있어 환불완료까지 기다린다. */
        $before = $order->status;

        $order->forceFill([
            'status'                 => 'cancelled',
            'withworks_status'       => '99',
            'withworks_status_label' => '취소',
            'withworks_status_at'    => now(),
        ])->save();

        if ($before !== 'cancelled') {
            activity()->performedOn($order)->log(
                "출고 전 취소로 주문을 닫았습니다 ({$return->receipt_no})"
            );
        }

        /* 주문만 닫으면 뒤에 달린 것들이 남는다 — 이미 발행한 계산서·현금영수증을
           되돌리고, 청구 대상에서도 뺀다. 공단에 이미 청구한 건은 우리가 물릴 수
           없으므로 접수 건에 적어 사람이 보게 한다. */
        $closed = app(\App\Services\OrderCancellation::class)->close($order, $return);

        $return->forceFill([
            'withworks_status_label' => '판매주문 취소',
            'withworks_error'        => $closed['warnings'] ? implode(' / ', $closed['warnings']) : null,
        ])->save();

        return true;
    }

    /** 반품 판매주문을 세운다 (반품 · 교환 · 출고 후 취소) */
    private function storeReturn(OrderReturn $return, Order $order): bool
    {
        $items = $this->items($return, $order);
        if ($items === []) {
            return $this->fail($return, '보낼 품목이 없습니다 — 제품코드를 확인하십시오');
        }

        $res = $this->call('post', 'return_store', [
            'ce_return_number' => $return->receipt_no,
            'ce_order_number'  => $order->order_number,
            'so_no'            => $order->withworks_so_no,
            'return_type'      => self::TYPE_NAMES[$return->type] ?? $return->type,
            'reason'           => $this->reasonText($return),
            'items'            => $items,
        ]);

        if ($res === null || !($res['success'] ?? false)) {
            return $this->fail($return, $res['message'] ?? '반품 주문을 보내지 못했습니다');
        }

        $r = $res['result'] ?? [];

        $return->forceFill([
            'withworks_so_no'   => $this->fit($r['so_no'] ?? null, 50),
            'withworks_so_id'   => $r['so_id'] ?? null,
            'withworks_so_type' => $this->fit($r['so_type'] ?? null, 10),
            'withworks_status'  => $this->fit($r['status'] ?? null, 50),
            // 등록 직후에는 상태 이름이 오지 않는다 — 유형 이름이라도 남겨 화면을 비우지 않는다
            'withworks_status_label' => $this->fit(
                $r['status_label'] ?? $r['so_type_label'] ?? null, 100
            ),
            'withworks_sent_at' => now(),
            'withworks_error'   => null,
        ])->save();

        /* 위드웍스는 원 판매주문을 udf5 에 담아 되돌려준다. 우리 주문에 이미 같은 값이
           있으므로 어긋나면 잘못 이어진 것이다 — 남겨서 사람이 보게 한다. */
        $origin = $r['origin_so_no'] ?? null;
        if ($origin && $order->withworks_so_no && $origin !== $order->withworks_so_no) {
            Log::warning('Withworks 반품의 원 판매주문이 다릅니다', [
                'receipt' => $return->receipt_no,
                'ours'    => $order->withworks_so_no,
                'theirs'  => $origin,
            ]);
        }

        /* 등록 응답에는 상태 코드만 있고 이름이 없다. 그대로 두면 화면에 '02' 가 뜬다.
           바로 되짚어 이름을 받아 둔다 — 한 번 더 부르는 값이 있다. */
        if (!$return->withworks_status_label) {
            $this->pull($return);
        }

        return true;
    }

    /**
     * 진행 상태를 위드웍스에 알린다.
     *
     * 우리가 반품을 접수 취소했을 때 창고도 멈춰야 한다. 반품 주문을 세우지 않은
     * 건(출고 전 취소)은 알릴 곳이 없다.
     */
    public function pushStatus(OrderReturn $return): bool
    {
        if (!$this->configured() || !$return->withworks_so_no || !$return->withworks_so_type) {
            return false;
        }

        $res = $this->call('post', 'return_status', [
            'ce_return_number' => $return->receipt_no,
            'status'           => $return->status,
            'status_label'     => OrderReturn::STATUS_LABELS[$return->status] ?? $return->status,
        ]);

        if ($res === null || !($res['success'] ?? false)) {
            Log::warning('Withworks 반품 상태 전달 실패', [
                'receipt' => $return->receipt_no,
                'message' => $res['message'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    /** 반품 현황을 물어와 적는다 */
    public function pull(OrderReturn $return): ?array
    {
        if (!$this->configured() || !$return->withworks_so_no || !$return->withworks_so_type) {
            return null;
        }

        $res = $this->call('get', 'return_show', ['ce_return_number' => $return->receipt_no]);

        if ($res === null || !($res['success'] ?? false)) {
            return null;
        }

        $r = $res['result'] ?? [];

        $return->forceFill([
            'withworks_status'       => $this->fit($r['status'] ?? null, 50)
                ?? $return->withworks_status,
            'withworks_status_label' => $this->fit($r['status_label'] ?? null, 100)
                ?? $return->withworks_status_label,
            'withworks_so_type'      => $this->fit($r['so_type'] ?? null, 10)
                ?? $return->withworks_so_type,
        ])->save();

        return $r;
    }

    /**
     * 되돌릴 품목.
     *
     * 교환은 무엇으로 바꾸는지가 아니라 무엇이 돌아오는지를 보낸다 — 창고가 받는 것은
     * 원래 나갔던 물건이다. 재출고는 그쪽 화면에서 별도로 처리한다.
     */
    private function items(OrderReturn $return, Order $order): array
    {
        /* 부분 취소면 받아 둔 줄이 곧 되돌리는 것이다. 원 주문의 수량을 그대로 보내면
           창고는 다 돌아온다고 알고 기다린다. */
        if ($return->relationLoaded('items') || $return->items()->exists()) {
            $picked = $return->items
                ->filter(fn ($i) => $i->product_code && $i->quantity > 0)
                ->map(fn ($i) => ['item_code' => $i->product_code, 'qty' => (int) $i->quantity])
                ->values()->all();

            if ($picked !== []) {
                return $picked;
            }
        }

        $items = $order->items
            ->filter(fn ($i) => $i->product_code)
            ->map(fn ($i) => [
                'item_code' => $i->product_code,
                'qty'       => max(1, (int) $i->quantity),
            ])
            ->values()
            ->all();

        if ($items === [] && $order->product_code) {
            $items = [[
                'item_code' => $order->product_code,
                'qty'       => max(1, (int) $order->quantity),
            ]];
        }

        return $items;
    }

    private function reasonText(OrderReturn $return): string
    {
        $label = OrderReturn::REASONS[$return->reason_code]['label'] ?? $return->reason_code;

        return trim($label . ($return->reason_text ? ' — ' . $return->reason_text : ''));
    }

    /** 실패를 접수 건에 적는다 — 화면에서 왜 안 갔는지 보여야 다시 보낼 수 있다 */
    private function fail(OrderReturn $return, string $message): bool
    {
        $return->forceFill([
            'withworks_error'   => $this->fit($message, 500),
            'withworks_sent_at' => now(),
        ])->save();

        Log::warning('Withworks 반품 전달 실패', [
            'receipt' => $return->receipt_no, 'message' => $message,
        ]);

        return false;
    }

    /**
     * 한 번 부른다.
     *
     * 못 부른 것과 거절당한 것을 가르지 않는다 — 호출한 쪽은 어느 쪽이든 실패로 다루고
     * 사람에게 알린다. 다만 거절 사유는 그대로 실어 준다.
     */
    private function call(string $method, string $path, array $payload): ?array
    {
        $baseUrl = rtrim((string) config('services.demoworks.api_url'), '/');

        try {
            $req = Http::withToken(config('services.demoworks.token'))->timeout(12);

            $res = $method === 'get'
                ? $req->get("{$baseUrl}/api/v1/ce-admin/{$path}", $payload)
                : $req->post("{$baseUrl}/api/v1/ce-admin/{$path}", $payload);

            return $res->json() ?? ['success' => false, 'message' => 'HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            Log::warning('Withworks 호출 실패', [
                'path' => $path, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** 그쪽이 정하는 값이라 얼마나 길지 알 수 없다 — 칸에 맞춰 자른다 */
    private function fit($value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $max);
    }
}
