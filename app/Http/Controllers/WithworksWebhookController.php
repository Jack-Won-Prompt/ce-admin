<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnLog;
use App\Models\WithworksEvent;
use App\Services\ClaimReadiness;
use App\Services\WithworksSync;
use App\Support\WebhookLogger;
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
     * Withworks 사건 → 우리 주문 상태.
     *
     * 여태 할당ㆍ피킹ㆍ송장은 사건 기록에만 남고 주문 상태는 「주문 확정」에 멈춰
     * 있었다. 담당자는 물건이 어디쯤 왔는지 목록에서 알 수 없어 위드웍스 화면을 따로
     * 열었다 — 이제 단계대로 옮긴다(2026-09-03 · 테스트 시나리오 4).
     *
     * so.created 는 넣지 않는다. 우리가 보내서 선 것이라 알려 올 것이 없고, 받으면
     * 이미 확정된 건이 「주문 대기」로 되돌아간다.
     *
     * so.delivered 도 넣지 않는다. 저쪽은 택배사 조회가 없어 배송이 끝난 때를
     * 알지 못한다(2026-08-15 합의). 한 번도 닿은 적이 없어 표에서 걷어냈다
     * (2026-09-11 지시). 「배송 완료」는 주문 상세의 단추로 사람이 옮긴다.
     */
    private const ORDER_STATUS = [
        'so.confirmed' => 'confirmed',
        'so.allocated' => 'allocated',
        'so.picked'    => 'picked',
        'so.invoiced'  => 'invoiced',
        'so.shipped'   => 'shipping',
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

    /**
     * 받는 자리 — 오간 것을 웹훅 관리 표에 남기고 본디 하던 일로 넘긴다 (2026-09-10 지시).
     *
     * 몸통에 나가는 길이 여럿이라(이미 처리ㆍ주문 없음ㆍ검증 실패…) 길목마다 적으면
     * 하나를 빠뜨린다. 들고 나는 자리를 하나로 두고 여기서만 적는다.
     */
    public function receive(Request $request, WithworksSync $sync, ClaimReadiness $readiness): JsonResponse
    {
        $기록 = WebhookLogger::inbound('withworks', $request->input('event'), $request);

        try {
            $답 = $this->처리($request, $sync, $readiness);
        } catch (\Throwable $e) {
            WebhookLogger::finish($기록, ok: false, status: 500, error: $e->getMessage());
            throw $e;
        }

        WebhookLogger::finish($기록,
            ok: $답->getStatusCode() < 400,
            status: $답->getStatusCode(),
            response: $답->getData(true),
            ref: $request->input('ce_order_number') ?: $request->input('so_no'));

        return $답;
    }

    private function 처리(Request $request, WithworksSync $sync, ClaimReadiness $readiness): JsonResponse
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
            /* 창고가 지금 무엇을 하고 있는가 — 도착완료ㆍ검수중ㆍ검수완료ㆍ입고중ㆍ
               입고완료ㆍ출고중ㆍ출고완료(요청서 4쪽). 우리 접수 단계와 다른 것을 잰다. */
            'pl3'             => 'nullable|array',
            /* 줄마다의 Lot 과 유효기간 — item_code · lot_no · expiry_date · qty.

               규칙에 적지 않으면 validated() 가 통째로 걸러 낸다. 그래서 창고가 Lot 을
               분명히 실어 보냈는데도 WithworksSync 에는 빈 것이 넘어가, 주문에는 한 줄도
               적히지 않았다(사건 표의 payload 에는 남아 있어 더 찾기 어려웠다). */
            'details'         => 'nullable|array',
            /* 창고와 판매현황 값 — Lot 과 같은 함정이다. 규칙에 적지 않으면
               validated() 가 통째로 걷어 내, 저쪽이 분명히 실어 보냈는데도
               받는 쪽에는 빈 것이 넘어간다(2026-09-07, 실제로 그렇게 됐다). */
            'warehouse'       => 'nullable|array',
            'so_meta'         => 'nullable|array',
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

        /* 물러난 판매번호로 온 사건은 남기기만 한다 (2026-09-15 운영 확인).

           정정은 원 판매주문을 취소하고 새로 세운다. 그 취소에 저쪽이 so.cancelled 를
           보내는데, 주문번호(ce_order_number)는 그대로라 여기까지 닿는다. 그대로
           반영하면 **새 판매주문이 멀쩡히 서 있는데 주문은 취소로 뒤집힌다** — 실제로
           EUD202609111330121 이 status=cancelled 가 되었다.

           지금 우리 줄에 적힌 번호가 아니고, 이력에 물러난 것으로 남아 있으면 그
           사건은 지나간 판매주문의 일이다. 사건 자체는 위에서 이미 적어 두었다. */
        if ($order->물러난판매번호인가($data['so_no'] ?? null)) {
            Log::info('[Withworks] 이전 판매번호의 이벤트 — 상태에 반영하지 않습니다', [
                'event' => $data['event'],
                'order' => $order->order_number,
                'so_no' => $data['so_no'],
                '지금'  => $order->withworks_so_no,
            ]);

            return response()->json(['success' => true, 'message' => 'Superseded sale order — event recorded']);
        }

        $sync->apply($order, $data);

        /* 창고 이름 — 어디서 내보내고 어디로 들이는가 (2026-09-07 지시).
           Finance 화면이 판매현황과 같은 칸을 세우는데 창고만 우리가 만들 길이 없어
           빈 채였다. 저쪽 판매주문에 진작 적혀 있는 값이라 웹훅에 실어 보내게 했다.

           온 사건마다 다시 적는다 — 창고는 바뀌기도 한다. 오지 않은 값은 건드리지
           않는다(옛 위드웍스는 이 칸을 싣지 않아, 덮으면 적어 둔 것이 지워진다). */
        $wh = $data['warehouse'] ?? null;
        if (is_array($wh)) {
            $name = fn (?array $w) => $w && ($w['name'] ?? null) ? mb_substr($w['name'], 0, 100) : null;
            $put  = array_filter([
                'withworks_warehouse'         => $name($wh['ship_from']  ?? null),
                'withworks_deliver_warehouse' => $name($wh['deliver_to'] ?? null),
            ], fn ($v) => $v !== null);

            if ($put) {
                $order->forceFill($put)->save();
            }
        }

        /* 판매현황의 나머지 값 (2026-09-07 지시) — 제품그룹ㆍ바코드ㆍ등급ㆍ표준코드ㆍ
           Description ㆍ라인번호ㆍ발주번호ㆍ확정수량 따위. 저쪽 마스터에만 있어
           우리가 만들 수 없다. 온 것만 덧쓴다 — 오지 않은 열쇠는 지우지 않는다.
           옛 위드웍스는 이 묶음을 아예 싣지 않으므로 통째로 덮으면 안 된다. */
        if (is_array($data['so_meta'] ?? null) && $data['so_meta']) {
            $order->forceFill([
                'withworks_meta' => array_merge(
                    (array) ($order->withworks_meta ?? []),
                    array_filter($data['so_meta'], fn ($v) => $v !== null && $v !== ''),
                ),
            ])->save();
        }

        // 창고가 알려 온 단계로 우리 주문 상태도 함께 움직인다
        if ($newStatus = self::ORDER_STATUS[$data['event']] ?? null) {
            /* 정정이 갈아 세우는 중이면 그때의 취소는 지나간 판매주문의 일이다
               (2026-09-17 시험에서 드러남).

               정정은 옛 판매주문을 취소하고 곧바로 새로 세운다. 그 취소 사건이
               상태를 「취소」로 뒤집으면, 뒤이어 오는 so.created·so.confirmed 도
               그것을 되돌리지 못한다 — 취소가 맨 끝 단계라 rank 가 가장 높다.
               출고까지 끝낸 건이 취소로 남아 정정ㆍ취소 단추가 잠기고, 청구 관리와
               교환/반품/취소 목록에서도 사라졌다. */
            $갈아세우는중 = $newStatus === 'cancelled' && $order->정정갈아세우는중인가();

            if ($갈아세우는중) {
                Log::info('[Withworks] 정정이 갈아 세우는 중이라 취소는 상태에 반영하지 않습니다', [
                    'order' => $order->order_number,
                    'so_no' => $data['so_no'] ?? null,
                ]);
            }

            /* 뒤로 물리지 않는다. 웹훅은 순서가 뒤바뀌어 오거나 다시 오기도 해서,
               출고까지 간 건에 뒤늦게 「할당」이 닿으면 상태가 거꾸로 간다.
               취소만은 어디서든 받는다 — 되돌리는 일이라 앞뒤가 없다.

               다만 취소에서 앞으로 나가는 길은 열어 둔다. 지금 판매번호로 온
               사건은 살아 있는 판매주문의 일이므로, 취소로 잘못 뒤집힌 건이
               그 사건으로 제자리를 찾는다. 취소된 판매주문에는 확정ㆍ할당ㆍ출고
               사건이 오지 않으므로, 이 길이 열려도 진짜 취소는 취소로 남는다. */
            $지금번호사건 = ($data['so_no'] ?? null) !== null
                          && $data['so_no'] === $order->withworks_so_no;

            $되살림 = $order->status === 'cancelled'
                    && $newStatus !== 'cancelled'
                    && $지금번호사건;

            if ($되살림) {
                Log::info('[Withworks] 취소로 적힌 주문에 지금 판매번호의 사건이 닿아 상태를 되살립니다', [
                    'order' => $order->order_number,
                    'so_no' => $data['so_no'],
                    'event' => $data['event'],
                ]);
            }

            if (! $갈아세우는중
                && ($newStatus === 'cancelled' || $되살림
                    || self::rank($newStatus) > self::rank($order->status))) {
                $order->update(['status' => $newStatus]);
            }

            /* 청해 둔 취소가 끝났다 (2026-09-14 지시).

               할당ㆍ피킹이 걸린 건은 그 자리에서 취소할 수 없어 창고에 청해 두고
               기다린다(cancel_state = requested). 담당자가 할당ㆍ피킹을 되돌리면
               위드웍스가 스스로 확정취소ㆍ삭제까지 잇고 이 사건을 보내 온다 —
               그때 「취소 요청 중」을 「취소됨」으로 닫아 준다.

               이 자리가 없으면 화면에는 「취소 요청 중」이 영영 서 있게 된다. */
            if ($newStatus === 'cancelled' && ! $갈아세우는중
                && $order->cancel_state === \App\Models\Order::CANCEL_REQUESTED) {
                $order->update([
                    'cancel_state'   => \App\Models\Order::CANCEL_DONE,
                    'cancel_done_at' => now(),
                ]);

                activity()->performedOn($order)
                    ->log("주문 취소 완료 ({$order->order_number}) — 창고가 되돌려 자동 취소되었습니다");
            }

            /* 청해 둔 정정이 이제 이어진다 (2026-09-15 지시).

               할당ㆍ피킹이 걸린 건을 정정하면 그 자리에서 갈아 세울 수 없어,
               창고에 취소를 청해 두고(eud_cancel_yn='Y') 세울 내용을 들고 기다린다
               (amend_state = requested · amend_payload).

               담당자가 할당ㆍ피킹을 되돌리면 위드웍스가 스스로 취소까지 잇고 이
               사건을 보내 온다 — 그때가 새로 세울 차례다. 사람이 다시 눌러야 하면
               잊는다. 잊은 건은 창고에 아무것도 없는 채로 남는다.

               세우다 실패해도 사건 처리는 이어 간다. 실패는 amend_note 에 적히고
               화면이 그것을 보여 준다 — 여기서 멈추면 저쪽은 우리가 못 받은 줄
               알고 같은 사건을 다시 보낸다. */
            if ($newStatus === 'cancelled'
                && $order->amend_state === \App\Models\Order::AMEND_REQUESTED
                && is_array($order->amend_payload)) {

                try {
                    $결과 = app(\App\Services\OrderAmendService::class)
                                ->갈아세우기($order->refresh(), $order->amend_payload);

                    activity()->performedOn($order)->log($결과['ok']
                        ? "주문 정정 완료 ({$order->order_number}) — {$결과['message']}"
                        : "주문 정정 실패 ({$order->order_number}) — {$결과['message']}");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[주문 정정] 되돌린 뒤 재등록에서 예외', [
                        'order' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);

                    $order->forceFill([
                        'amend_note' => '재등록 중 오류 — ' . mb_substr($e->getMessage(), 0, 250),
                    ])->save();
                }
            }
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
        /* 운송장이 닿은 자리에서도 부른다 (2026-09-17 시험에서 드러남).

           운송장은 출고 뒤에 들어온다(so.shipped → so.invoiced). 출고 사건에서만
           부르면 안내에 운송장이 실릴 길이 없다 — ShipNotice 가 잠깐 기다렸다가
           이 사건에 번호를 실어 보낸다. 이미 보낸 건은 발송 이력으로 걸러진다. */
        if (in_array($data['event'], ['so.shipped', 'so.invoiced'], true)) {
            app(\App\Services\ShipNotice::class)->send($order->refresh());
        }

        if ($data['event'] === 'so.shipped') {
            /* 입금이 먼저 들어온 건은 그때 발행을 미뤄 두었다(요청서 8ㆍ9쪽 —
               「입금 및 출고 되어야」). 이제 출고됐으니 낸다.

               WithworksSync 도 출고 상태가 바뀌면 같은 것을 부르는데, 이 사건에
               ship 블록이 실려 오지 않으면 그쪽은 바뀐 것을 못 본다. 두 번 불려도
               이미 발행된 것은 DepositAutoIssue 가 거른다. */
            app(\App\Services\DepositAutoIssue::class)->run($order->refresh(), '출고 웹훅');
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

        /* 창고가 지금 무엇을 하고 있는가(요청서 4쪽). 우리 단계는 건드리지 않는다 —
           그것은 「우리가 어디까지 했는가」이고 이것은 「창고가 어디까지 했는가」다.

           pl3 로 오면 그것을 쓰고, 없으면 입고 사건이 싣고 온 입고 상태로 갈음한다 —
           위드웍스가 pl3 를 싣기 전까지는 그것이 우리가 아는 전부다. */
        $pl3 = $data['pl3'] ?? null;
        $label = $pl3['status_label'] ?? $data['receiving']['rcpt_status_label'] ?? null;

        if ($label) {
            $return->forceFill([
                'pl3_status'       => mb_substr((string) ($pl3['status']
                                        ?? $data['receiving']['rcpt_status'] ?? ''), 0, 30) ?: null,
                'pl3_status_label' => mb_substr((string) $label, 0, 50),
                'pl3_status_at'    => \Carbon\Carbon::parse(
                                        $pl3['occurred_at'] ?? $data['occurred_at'] ?? now()),
            ])->save();
        }

        /* 창고가 실물을 보고 적은 말(2026-09-06 지시). 담당자가 검수를 판단하는
           근거는 「어느 단계인가」가 아니라 「무엇을 보았는가」다. 여태 그것을
           읽으려면 위드웍스 화면에 따로 들어가야 했고, 그래서 대개 읽지 않은 채로
           검수 확정을 눌렀다.

           고쳐 적으면 다시 온다 — 창고가 비고의 지문으로 사건을 가른다.
           같은 말이 다시 와도 덮어쓰는 값이 같아 해가 없다. */
        $note = trim((string) ($data['receiving']['remark'] ?? ''));

        /* 우리가 보낸 말은 받지 않는다. 창고는 반품 입고를 만들 때 반품주문의
           적요를 그대로 복사하는데, 그 적요는 우리가 보낸 말(`[CE-ADMIN 반품] …`)과
           우리 진행 상태(`[CE 상태] …`)다. 저쪽에서 떼고 보내도록 고쳤지만
           (위드웍스 `0173086c6`), 저쪽이 아직 옛 코드로 돌 수도 있다.
           담당자가 「창고 검수 비고」에서 제 말을 읽는 일만은 없어야 한다. */
        if (str_starts_with($note, '[CE-ADMIN') || str_starts_with($note, '[CE 상태]')) {
            $note = '';
        }

        if ($note !== '' && $note !== $return->pl3_note) {
            $return->forceFill([
                'pl3_note'    => mb_substr($note, 0, 2000),
                'pl3_note_at' => \Carbon\Carbon::parse($data['occurred_at'] ?? now()),
            ])->save();
        }

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
     * 단계의 앞뒤 — 큰 것이 나중이다.
     *
     * 표에 없는 값은 -1 이라 무엇으로든 옮겨진다(옛 건에 다른 값이 적혀 있을 수 있다).
     */
    private static function rank(?string $status): int
    {
        $order = array_keys(\App\Models\Order::STATUS_LABELS);
        $at    = array_search((string) $status, $order, true);

        return $at === false ? -1 : $at;
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
            'so.confirmed' => ['창고가 주문을 확정했습니다', 'info'],
            'so.invoiced'  => ['송장이 붙었습니다',  'info'],
            'so.shipped'   => ['출고되었습니다',      'success'],
            'so.cancelled' => ['주문이 취소되었습니다', 'danger'],
        ];

        [$what, $tone] = $tell[$data['event']] ?? [null, null];
        if (!$what) {
            return;
        }

        /* 그 주문의 담당자에게 보내고 채팅에도 남긴다(2026-09-04 지시).
           여태 admin 채널로 전원에게 띄우기만 해, 화면을 보고 있지 않았으면 그대로
           사라졌다 — 반품 쪽(ReturnNotice)과 같은 틀로 맞춘다.
           담당자를 못 찾으면 OrderNotice 가 예전처럼 admin 채널로 띄운다. */
        app(\App\Services\OrderNotice::class)->tellOwner($order, $what, $tone);
    }

    /** 반품 사건을 화면에 알린다 */
    private function announceReturn(array $data, OrderReturn $return): void
    {
        $tell = [
            'ro.created'   => ['반품이 창고에 접수되었습니다', 'info'],
            'ro.inspected' => ['창고가 검수 비고를 등록했습니다 — 확인하십시오', 'warning'],
            'ro.rcpt_completed' => ['반품 실물이 창고에 들어왔습니다', 'success'],
            'ro.confirmed' => ['반품이 창고에서 확정되었습니다', 'success'],
            'ro.cancelled' => ['반품이 취소되었습니다',        'danger'],
        ];

        [$what, $tone] = $tell[$data['event']] ?? [null, null];
        if (!$what) {
            return;
        }

        /* 절차서의 「접수자에게 inform」 — 알람과 채팅을 함께, 접수자에게만 보낸다
           (2026-08-31 회신). 전원에게 띄우던 것을 걷었다 — 반품은 임자가 있고, 전원의
           화면에 띄우면 정작 할 일이 있는 사람에게서 남의 건에 섞여 묻힌다. */
        app(\App\Services\ReturnNotice::class)->tellTaker($return, $what, $tone);
    }

    /** 흐름에서 뒤로 가는 것인지 본다 — 취소는 어디서든 갈 수 있다 */
    private function canAdvanceTo(OrderReturn $return, string $to): bool
    {
        /* 끝난 건은 어떤 사건으로도 되살리지 않는다. 창고는 우리보다 늦게 움직인다 —
           담당자가 검수ㆍ승인ㆍ환불까지 다 마친 뒤에 창고가 실물을 입고하는 일이
           예사다. 그때 「입고완료」가 닿아 완료된 건이 「검수중」으로 되돌아갔다. */
        if (in_array($return->status, ['done', 'cancelled'], true)) {
            return false;
        }

        if ($to === 'cancelled') {
            return true;
        }

        /* FLOWS 의 열쇠는 **시나리오**(교환-변심ㆍ반품-불량 …)다. 여기서는 종류
           (return·exchange·cancel)로 찾고 있어 늘 빈 배열이 나왔다. 그러면 아래
           비교가 통째로 참이 되어, 「뒤로 물리지 않는다」가 아무것도 막지 못했다.
           그 건의 실제 흐름을 쓴다 — 부분 여부까지 반영된 것이다. */
        $flow = $return->flow();
        $now  = array_search($return->status, $flow, true);
        $next = array_search($to, $flow, true);

        return $now === false || $next === false || $next > $now;
    }
}
