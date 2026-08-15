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

    public function configured(): bool
    {
        return (bool) (config('services.todoworks.api_url') && config('services.todoworks.token'));
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

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');

        try {
            $res = Http::withToken(config('services.todoworks.token'))->timeout(8)
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
        $this->apply($order, $result);

        return $result;
    }

    /**
     * 받아 온 상태를 주문에 옮긴다.
     *
     * 콜백이 생기면 그쪽에서도 이 메서드를 부르면 된다.
     */
    public function apply(Order $order, array $result): void
    {
        $ship = $result['ship'] ?? null;

        $order->update([
            'withworks_status'            => $result['status'] ?? null,
            'withworks_status_label'      => $result['status_label'] ?? null,
            'withworks_status_at'         => now(),
            'withworks_ship_no'           => $ship['ship_no'] ?? null,
            'withworks_ship_status'       => $ship['ship_status'] ?? null,
            'withworks_ship_status_label' => $ship['ship_status_label'] ?? null,
            'withworks_tracking_no'       => $ship['tracking_no'] ?? null,
            'withworks_ship_at'           => $ship ? now() : null,
        ]);

        // 송장이 나오면 우리 주문에도 옮겨 둔다. 목록·청구가 이 컬럼을 본다.
        if (($ship['tracking_no'] ?? null) && !$order->tracking_number) {
            $order->update(['tracking_number' => $ship['tracking_no']]);
        }
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
