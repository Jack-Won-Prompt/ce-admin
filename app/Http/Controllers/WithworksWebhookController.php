<?php

namespace App\Http\Controllers;

use App\Events\WithworksStatusChanged;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnLog;
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

    /**
     * 반품 사건 → 우리 접수 단계.
     *
     * 판매와 사건 이름을 가르는 까닭이 있다. 반품에 so.* 를 쓰면 같은 ce_order_number 를
     * 싣기 때문에 원 주문이 다시 배송중으로 되돌아간다.
     *
     * 창고가 실물을 받아 확정하면(ro.confirmed) 우리 쪽은 검수 단계로 옮긴다 — 물건이
     * 들어왔으니 다음은 살펴보는 일이다. 등록(ro.created)은 우리가 보낸 것이 잘 섰다는
     * 뜻이라 단계를 건드리지 않는다.
     */
    private const RETURN_STATUS = [
        /* 실물이 창고에 들어온 순간이다. 반품주문의 확정(ro.confirmed)은 창고 담당자가
           따로 누르는 일이라 늦게 올 수 있다 — 그것만 보면 물건이 이미 들어왔는데도
           우리 표는 「수거중」에 멈춰 있다. 둘 중 먼저 오는 것이 단계를 옮기고, 뒤에
           오는 것은 제자리에 멈췄다(같은 단계로는 옮기지 않는다). */
        'ro.rcpt_completed' => 'inspecting',
        'ro.confirmed' => 'inspecting',
        'ro.cancelled' => 'cancelled',
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
            /* 반품 사건은 원 주문번호가 비어 올 수 있다 — 창고에서 만든 반품이면
               우리 주문과 이어지지 않는다. 없다고 거절하면 그 사건은 영영 유실된다. */
            'ce_order_number' => 'nullable|string|max:50',
            'ce_return_number'=> 'nullable|string|max:50',
            'origin_so_no'    => 'nullable|string|max:50',
            'return_kind'     => 'nullable|string|max:20',
            'so_type'         => 'nullable|string|max:10',
            'so_no'           => 'nullable|string|max:50',
            'occurred_at'     => 'nullable|date',
            /* 길이로 막지 않는다. 4xx 는 재시도하지 않는 것이 규격이라, 여기서 거절하면 그
               사건은 영영 유실된다. 우리 칸에 안 들어가면 잘라서라도 받는다 — 원본은 payload
               에 통째로 남으므로 잃는 것이 없다. */
            'status'          => 'nullable|string',
            'status_label'    => 'nullable|string',
            'ship'            => 'nullable|array',
            // 입고완료 사건이 실어 보내는 것 — 입고번호ㆍ상태ㆍ입고일시
            'receiving'       => 'nullable|array',
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

        $isReturn = str_starts_with($data['event'], 'ro.');
        $order    = ($data['ce_order_number'] ?? null)
            ? Order::where('order_number', $data['ce_order_number'])->first()
            : null;

        try {
            WithworksEvent::create([
                'event_id'        => $data['event_id'],
                'event'           => $data['event'],
                'ce_order_number' => $data['ce_order_number'] ?? null,
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

        /* 반품 사건은 접수 건을 찾아 옮긴다. 원 주문 상태에는 손대지 않는다 —
           반품이 들어왔다고 판매가 배송중으로 돌아가서는 안 된다. */
        if ($isReturn) {
            return $this->applyReturn($data);
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

        /* 출고일자는 창고가 ship.shipped_at 으로 알려 준다(WithworksSync 가 적는다).
           그것 없이 출고 사건만 온 건은 사건이 일어난 날을 출고일로 본다 — 목록의
           「출고일자」가 비어 있으면 청구 기한을 셀 수 없다. */
        if ($data['event'] === 'so.shipped' && !$order->refresh()->shipped_at) {
            $order->update([
                'shipped_at' => \Carbon\Carbon::parse($data['occurred_at'] ?? now())->toDateString(),
            ]);
        }

        // 배송이 끝나야 청구할 수 있다 — 상태가 움직였으면 준비 여부도 다시 따진다
        $readiness->refresh($order->refresh());

        /* 출고했으면 환자에게 알린다. 위드웍스는 배송 완료를 알려 주지 않으므로, 배송에
           관해 우리가 아는 마지막 시점이 여기다.
           보내지 못해도 웹훅은 성공이다 — 알리지 못한 것과 받지 못한 것은 다른 일이다.
           한 건에 한 번만 나가는 것은 ShipNotice 가 발송 이력으로 가린다. */
        if ($data['event'] === 'so.shipped') {
            app(\App\Services\ShipNotice::class)->send($order->refresh());
        }

        $this->announce($data, $order);

        return response()->json(['success' => true]);
    }

    /**
     * 반품 사건을 접수 건에 옮긴다.
     *
     * 접수번호(ce_return_number)로 짝짓는다. 우리가 보낸 것이 아니면 — 창고에서 직접
     * 만든 반품이면 — 짝이 없다. 그래도 200 으로 답한다. 다시 보낸다고 우리에게 그
     * 접수가 생기지는 않고, 사건은 이미 표에 남아 나중에 볼 수 있다.
     */
    private function applyReturn(array $data): JsonResponse
    {
        $no = $data['ce_return_number'] ?? null;

        $return = $no ? OrderReturn::where('receipt_no', $no)->first() : null;

        if (!$return) {
            Log::warning('[Withworks] 모르는 반품의 사건', [
                'event' => $data['event'], 'receipt' => $no,
            ]);

            return response()->json(['success' => true, 'message' => 'Return not found — event recorded']);
        }

        $return->forceFill([
            'withworks_so_no'        => $data['so_no'] ?? $return->withworks_so_no,
            'withworks_so_type'      => $data['so_type'] ?? $return->withworks_so_type,
            'withworks_status'       => isset($data['status'])
                ? mb_substr($data['status'], 0, 50) : $return->withworks_status,
            'withworks_status_label' => isset($data['status_label'])
                ? mb_substr($data['status_label'], 0, 100) : $return->withworks_status_label,
        ])->save();

        /* 실물이 들어온 날. 전에는 사람이 손으로 적었는데, 창고가 알려 주는 것을
           두고 다시 적게 할 까닭이 없다. 이미 적혀 있으면 건드리지 않는다 — 담당자가
           고쳐 둔 것이 창고의 날짜보다 정확할 수 있다. */
        $arrived = $data['receiving']['received_date'] ?? null;
        if ($arrived && !$return->arrived_at) {
            $return->forceFill(['arrived_at' => \Carbon\Carbon::parse($arrived)])->save();
        }

        /* 창고가 움직인 만큼만 우리 단계를 옮긴다. 이미 지나온 단계로는 되돌리지 않는다 —
           담당자가 손으로 앞서 옮겨 둔 것을 창고 사건이 뒤로 끌면 안 된다. */
        $to = self::RETURN_STATUS[$data['event']] ?? null;

        if ($to && $to !== $return->status && $this->canAdvanceTo($return, $to)) {
            OrderReturnLog::create([
                'order_return_id' => $return->id,
                'from_status'     => $return->status,
                'to_status'       => $to,
                /* 입고완료는 반품주문의 상태가 아니라 입고의 상태가 할 말을 한다 —
                   그때 반품주문은 아직 「등록」이라 발자취에 「창고 등록」이 남는다. */
                'reason'          => '창고 ' . (
                    $data['receiving']['rcpt_status_label']
                    ?? $data['status_label'] ?? $data['event']
                ),
            ]);

            $return->update(['status' => $to]);
        }

        $this->announceReturn($data, $return->refresh());

        return response()->json(['success' => true]);
    }

    /**
     * 판매 사건을 화면에 알린다.
     *
     * 웹훅은 사람이 보고 있지 않을 때 들어온다. 표에만 남기면 담당자가 목록을 새로
     * 불러야 알게 되고, 출고나 취소처럼 곧 손을 써야 하는 일이 늦어진다.
     *
     * 모든 단계를 알리지는 않는다. 할당·피킹은 창고 안의 일이라 우리가 할 일이 없다 —
     * 알림이 잦으면 정작 볼 것을 놓친다.
     */
    private function announce(array $data, Order $order): void
    {
        $tell = [
            'so.invoiced'  => ['송장이 붙었습니다',  'info'],
            'so.shipped'   => ['출고되었습니다',      'success'],
            'so.delivered' => ['배송이 끝났습니다',   'success'],
            'so.cancelled' => ['주문이 취소되었습니다', 'danger'],
        ];

        [$what, $tone] = $tell[$data['event']] ?? [null, null];
        if (!$what) {
            return;
        }

        $who  = $order->patient?->name;
        $body = $order->order_number . ($who ? ' · ' . $who : '')
            . ($order->withworks_tracking_no ? ' · ' . $order->withworks_tracking_no : '');

        $this->tell('창고 — ' . $what, $body, route('orders.show', $order), $tone, $data['event']);
    }

    /** 반품 사건을 화면에 알린다 */
    private function announceReturn(array $data, OrderReturn $return): void
    {
        $tell = [
            'ro.created'   => ['반품이 창고에 접수되었습니다', 'info'],
            'ro.rcpt_completed' => ['반품 실물이 창고에 들어왔습니다', 'success'],
            'ro.confirmed' => ['반품이 창고에서 확정되었습니다', 'success'],
            'ro.cancelled' => ['반품이 취소되었습니다',        'danger'],
        ];

        [$what, $tone] = $tell[$data['event']] ?? [null, null];
        if (!$what) {
            return;
        }

        $body = $return->receipt_no . ' · ' . $return->typeLabel()
            . ($return->order?->patient?->name ? ' · ' . $return->order->patient->name : '');

        $this->tell('창고 — ' . $what, $body, route('order-returns.show', $return), $tone, $data['event']);
    }

    /**
     * 알림을 띄운다.
     *
     * 방송이 실패해도 웹훅은 성공이다 — 알리지 못한 것과 받지 못한 것은 다른 일이다.
     * 여기서 터지면 그쪽이 같은 사건을 다시 보내고, 우리 표에는 이미 남아 있다.
     */
    private function tell(string $title, string $body, string $url, string $tone, string $event): void
    {
        try {
            broadcast(new WithworksStatusChanged($event, $title, $body, $url, $tone));
        } catch (\Throwable $e) {
            Log::warning('[Withworks] 알림 방송 실패', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /** 흐름에서 뒤로 가는 것인지 본다 — 취소는 어디서든 갈 수 있다 */
    private function canAdvanceTo(OrderReturn $return, string $to): bool
    {
        if ($to === 'cancelled') {
            return true;
        }

        $flow = OrderReturn::FLOWS[$return->type] ?? [];
        $now  = array_search($return->status, $flow, true);
        $next = array_search($to, $flow, true);

        return $now === false || $next === false || $next > $now;
    }
}
