<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WithworksEvent;
use App\Services\ClaimReadiness;
use App\Services\WithworksSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Withworks 물류 사건 수신.
 *
 * 판매주문을 넘긴 뒤의 일 — 확정·할당·피킹·송장·출고·배송 — 은 Withworks 안에서 일어난다.
 * 예전에는 우리가 주기적으로 물어봤는데(withworks:sync), 물어보는 사이에 벌어진 일은 늦게
 * 알았고 아무도 안 여는 주문은 며칠씩 옛 상태였다. 이제 그쪽이 바뀔 때마다 알려 준다.
 *
 * 폴링은 그대로 둔다. 웹훅이 몇 번 실패해도 결국 맞춰지는 그물이 있어야 하고, 그 비용이
 * 10분에 한 번 훑는 정도라면 싸다.
 *
 * 받은 사건은 표에 남긴다. 같은 사건이 두 번 오는 것을 event_id 로 막고, 나중에 「언제
 * 출고됐는지」를 따질 때도 이 표만 남는다 — 주문 컬럼에는 마지막 상태뿐이다.
 */
class WithworksWebhookController extends Controller
{
    /**
     * Withworks 상태 → 우리 주문 상태.
     *
     * 우리 주문 상태는 네 가지뿐이라 그쪽의 세분화된 단계를 그대로 받지 않는다. 할당·피킹은
     * 우리에게 「아직 출고 전」이라 주문 상태를 바꾸지 않고, 사건 기록에만 남는다.
     */
    private const ORDER_STATUS = [
        'so.shipped'   => 'shipping',
        'so.delivered' => 'delivered',
        'so.cancelled' => 'cancelled',
    ];

    public function receive(Request $request, WithworksSync $sync, ClaimReadiness $readiness): JsonResponse
    {
        $secret = config('services.demoworks.webhook_secret');

        // 비밀을 정해 두지 않았으면 아무나 주문 상태를 바꿀 수 있다. 열어 두느니 막는다.
        if (!$secret) {
            Log::error('[Withworks] webhook_secret 미설정 — 수신을 거부했다');

            return response()->json(['success' => false, 'message' => 'Webhook not configured'], 503);
        }

        if (!hash_equals($secret, (string) $request->header('X-Withworks-Secret'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $v = Validator::make($request->all(), [
            'event_id'        => 'required|string|max:100',
            'event'           => 'required|string|max:50',
            'ce_order_number' => 'required|string|max:50',
            'so_no'           => 'nullable|string|max:50',
            'occurred_at'     => 'nullable|date',
            /* 길이로 막지 않는다. 4xx 는 재시도하지 않는 것이 규격이라, 여기서 거절하면 그
               사건은 영영 유실된다. 우리 칸에 안 들어가면 잘라서라도 받는다 — 원본은 payload
               에 통째로 남으므로 잃는 것이 없다. */
            'status'          => 'nullable|string',
            'status_label'    => 'nullable|string',
            'ship'            => 'nullable|array',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed',
                                     'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        /* 같은 사건을 두 번 처리하지 않는다. 응답이 늦거나 끊기면 다시 보내는 것이 정상이므로
           보내는 쪽을 탓할 일이 아니라 받는 쪽이 걸러야 한다. 200 으로 답해야 재시도가 멈춘다. */
        if (WithworksEvent::where('event_id', $data['event_id'])->exists()) {
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        $order = Order::where('order_number', $data['ce_order_number'])->first();

        try {
            WithworksEvent::create([
                'event_id'        => $data['event_id'],
                'event'           => $data['event'],
                'ce_order_number' => $data['ce_order_number'],
                'so_no'           => $data['so_no'] ?? null,
                'order_id'        => $order?->id,
                // 요약 칸은 잘라 담는다. 원본은 바로 아래 payload 에 통째로 남는다.
                'status'          => isset($data['status']) ? mb_substr($data['status'], 0, 50) : null,
                'status_label'    => isset($data['status_label']) ? mb_substr($data['status_label'], 0, 100) : null,
                'payload'         => $request->all(),
                'occurred_at'     => $data['occurred_at'] ?? now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            /* 같은 사건이 동시에 두 번 들어오면 위의 존재 확인을 둘 다 통과한다. 표의 유일
               제약이 마지막 방어선이고, 여기까지 왔다는 것은 다른 요청이 이미 처리했다는
               뜻이라 200 으로 답한다 — 500 을 주면 그쪽이 계속 다시 보낸다. */
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        /* 우리가 모르는 주문이어도 사건은 남기고 200 으로 답한다. 404 를 주면 그쪽이 계속
           다시 보내는데, 다시 보낸다고 우리에게 그 주문이 생기지는 않는다. */
        if (!$order) {
            Log::warning('[Withworks] 모르는 주문의 사건', [
                'event' => $data['event'], 'order' => $data['ce_order_number'],
            ]);

            return response()->json(['success' => true, 'message' => 'Order not found — event recorded']);
        }

        $sync->apply($order, $data);

        // 출고·배송·취소는 우리 주문 상태도 함께 움직인다
        if ($newStatus = self::ORDER_STATUS[$data['event']] ?? null) {
            $order->update([
                'status'       => $newStatus,
                'delivered_at' => $newStatus === 'delivered'
                    ? ($data['ship']['delivered_at'] ?? $data['occurred_at'] ?? now())
                    : $order->delivered_at,
            ]);
        }

        // 배송이 끝나야 청구할 수 있다 — 상태가 움직였으면 준비 여부도 다시 따진다
        $readiness->refresh($order->refresh());

        return response()->json(['success' => true]);
    }
}
