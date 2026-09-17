<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 주문 정정 — 원 판매주문을 취소하고 새로 세운다 (2026-09-15 지시).
 *
 * 여태 정정은 so_update 로 **같은 판매주문을 제자리에서** 고쳤다. 그래서 창고가
 * 할당ㆍ피킹에 손을 댄 건은 저쪽이 거절했고(「이미 창고 작업이 시작된 주문이라
 * 수정할 수 없습니다」), 우리 화면도 아예 단추를 잠갔다 — 담당자는 창고에 전화를
 * 걸어 되돌려 달라 부탁하고 나서야 고칠 수 있었다.
 *
 * 이제 창고가 어디까지 갔느냐로 길을 가른다.
 *
 *   none     아직 안 넘겼다 — 창고에 할 말이 없다. 우리 줄만 고친다.
 *   new      출고가 신규다 — 그 자리에서 취소하고 새로 세운다. 기다릴 것이 없다.
 *   working  할당ㆍ피킹이 걸렸다 — eud_cancel_yn='Y' 를 보내 두고 기다린다.
 *            창고가 되돌리는 그 순간 저쪽이 스스로 취소까지 잇고, 우리는 그
 *            웹훅을 받아 재등록한다(WithworksWebhookController).
 *   shipped  나갔다 — 정정이 아니라 교환ㆍ반품이 할 일이다.
 *
 * **확정까지 우리가 한다** (2026-09-15 지시 3). so_store 는 확정하지 않으므로
 * (confirm=0) 그대로 두면 출고 정보가 서지 않는다 — 정정 전에는 있던 출고가
 * 정정 뒤에 사라지는 꼴이 된다. 새로 세운 뒤 so_confirm 까지 이어 부른다.
 */
class OrderAmendService
{
    public function __construct(
        private readonly OrderCancelService $취소,
    ) {}

    /** @return array{url:string, token:string}|null */
    private function 저쪽(): ?array
    {
        $url   = rtrim((string) config('services.demoworks.api_url'), '/');
        $token = config('services.demoworks.token');

        return ($url && $token) ? ['url' => $url, 'token' => $token] : null;
    }

    /**
     * 정정한다.
     *
     * @param array $창고내용 새로 세울 판매주문의 내용 — so_store 가 받는 꼴 그대로
     * @param int   $이전금액 정정 전 기준 금액 (Order::결제기준금액)
     *
     * @return array{ok:bool, message:string, so_no:?string, state:?string}
     */
    public function 정정(Order $order, array $창고내용, int $이전금액): array
    {
        if (! $order->정정가능한가()) {
            return [
                'ok'      => false,
                'so_no'   => null,
                'state'   => $order->amend_state,
                'message' => $order->창고단계() === 'shipped'
                    ? '이미 출고된 주문입니다 — 교환/반품/취소 화면에서 처리해 주십시오.'
                    : ($order->정정기다리는중인가()
                        ? '이미 정정을 요청한 주문입니다 — 창고에서 취소하면 자동으로 진행됩니다.'
                        : '정정할 수 있는 주문이 아닙니다.'),
            ];
        }

        $단계 = $order->창고단계();

        /* ── 아직 안 넘긴 건 — 창고에 할 말이 없다 ────────────────── */
        if ($단계 === 'none') {
            return ['ok' => true, 'so_no' => null, 'state' => null, 'message' => ''];
        }

        /* ── 할당ㆍ피킹이 걸린 건 — 청해 두고 기다린다 ─────────────── */
        if ($단계 === 'working') {
            $결과 = $this->취소요청($order);

            if (! $결과['ok']) {
                return ['ok' => false, 'so_no' => null, 'state' => null, 'message' => $결과['message']];
            }

            /* 되돌려진 뒤 무엇으로 세울지를 들고 있는다. 지금 화면에 있는 값이
               정본이다 — 며칠 뒤에 되돌아와도 그때 적은 대로 세워야 한다. */
            $order->forceFill([
                'amend_state'        => Order::AMEND_REQUESTED,
                'amend_payload'      => $창고내용,
                'amend_requested_at' => now(),
                'amend_requested_by' => Auth::id(),
                'amend_note'         => null,
            ])->save();

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("주문 정정 요청 ({$order->order_number}) — 창고가 할당ㆍ피킹을 취소하면 새 판매주문을 등록합니다");

            return [
                'ok'      => true,
                'so_no'   => $order->withworks_so_no,
                'state'   => Order::AMEND_REQUESTED,
                'message' => '창고에 취소를 요청했습니다 — 할당ㆍ피킹이 취소되면 '
                           . '새 판매주문이 자동으로 등록됩니다.',
            ];
        }

        /* ── 출고가 신규인 건 — 그 자리에서 갈아 세운다 ─────────────── */
        return $this->갈아세우기($order, $창고내용);
    }

    /**
     * 취소하고 새로 세운 뒤 확정까지 잇는다.
     *
     * 웹훅도 이 길을 쓴다 — 창고가 되돌려 준 뒤의 재등록이 같은 일이기 때문이다.
     *
     * @return array{ok:bool, message:string, so_no:?string, state:?string}
     */
    public function 갈아세우기(Order $order, array $창고내용): array
    {
        $저쪽 = $this->저쪽();

        if (! $저쪽) {
            return ['ok' => false, 'so_no' => null, 'state' => null, 'message' => '위드웍스 API 설정이 없습니다.'];
        }

        $옛번호 = $order->withworks_so_no;

        /* ① 원 판매주문을 취소한다. 이미 취소됐으면(웹훅으로 이어 온 길) 저쪽이
              멱등으로 성공을 돌려준다 — 그 답도 성공으로 받는다. */
        if ($옛번호) {
            /* 취소를 부르기 전에 갈아 세우는 중임을 적어 둔다 (2026-09-17 시험).

               저쪽이 보내는 so.cancelled 는 옛 번호를 싣고 오는데, 그 사건이 닿는
               시점에는 우리 줄에도 아직 옛 번호가 지금 번호로 적혀 있다 —
               물러난판매번호인가() 는 「지금 번호와 다르냐」로 가리므로 지나가지
               못한다. 이 표가 서 있는 동안 웹훅이 취소 사건을 건너뛴다.

               이력에도 함께 적는다 — 갈아탄 뒤 늦게 닿는 사건은 그것이 가려낸다. */
            $order->옛번호남기기($옛번호);
            $order->amend_state = Order::AMEND_SWAPPING;
            $order->save();

            $끈 = $this->부르기('post', 'so_cancel', [
                'ce_order_number' => $order->order_number,
                'so_no'           => $옛번호,
            ]);

            if (! $끈['ok']) {
                /* 갈아 세우지 못했으니 표를 내린다 — 세워 둔 채로 두면 다음 사건까지
                   건너뛰고, 화면의 단추도 잠긴 채로 남는다. */
                $order->amend_state = null;
                $order->save();

                return ['ok' => false, 'so_no' => null, 'state' => null,
                        'message' => '원 판매주문을 취소하지 못했습니다 — ' . $끈['message']];
            }
        }

        /* ② 새로 세운다 */
        $세움 = $this->부르기('post', 'so_store', $창고내용);

        if (! $세움['ok']) {
            /* 옛것은 이미 취소됐는데 새것이 서지 않았다. 창고에 아무것도 없는
               상태이므로 그 사실을 분명히 알린다 — 조용히 넘기면 출고가 사라진
               줄 아무도 모른다. */
            Log::error('[주문 정정] 새 판매주문을 세우지 못했습니다', [
                'order' => $order->order_number, 'old_so_no' => $옛번호,
                'message' => $세움['message'],
            ]);

            $order->판매번호갈아타기(null);
            $order->forceFill([
                'amend_state' => null,
                'amend_note'  => '새 판매주문을 세우지 못했습니다 — ' . $세움['message'],
            ])->save();

            return ['ok' => false, 'so_no' => null, 'state' => null,
                    'message' => '원 판매주문은 취소했으나 새 주문을 생성하지 못했습니다 — '
                               . $세움['message'] . ' 창고에 주문이 없는 상태입니다. 다시 연계해 주십시오.'];
        }

        $새번호 = $세움['body']['result']['so_no'] ?? null;

        /* ③ 확정까지 잇는다 (2026-09-15 지시 3).

              so_store 는 확정하지 않는다(confirm=0). 그대로 두면 출고 정보가 서지
              않아, 정정 전에는 있던 출고가 정정 뒤에 사라진 꼴이 된다. */
        $확정말 = '';

        if ($새번호) {
            $확정 = $this->부르기('post', 'so_confirm', [
                'ce_order_number' => $order->order_number,
                'so_no'           => $새번호,
            ]);

            $확정말 = $확정['ok']
                ? ' 확정까지 마쳤습니다.'
                : ' 다만 확정하지 못했습니다 — ' . $확정['message'] . ' 창고 화면에서 확정해 주십시오.';
        }

        /* ④ 우리 줄을 새 번호로 갈아탄다 — 옛 번호는 남긴다 */
        $order->판매번호갈아타기($새번호);
        $order->forceFill([
            'amend_state'        => null,
            'amend_payload'      => null,
            'amend_requested_at' => null,
            'amend_requested_by' => null,
            'amend_note'         => null,
        ])->save();

        activity()->causedBy(Auth::user())->performedOn($order)->log(sprintf(
            '주문 정정 — 판매주문을 신규 등록했습니다 (%s → %s)',
            $옛번호 ?: '없음', $새번호 ?: '번호 없음'
        ));

        /* 받을 돈이 없는 건은 정정한 금액으로 증빙을 다시 낸다 (2026-09-16 지시).

           본인부담금이 0원인 건은 입금 확인이 없어, 정정으로 기관부담금이 바뀌어도
           그것을 국세청에 알릴 자리가 없었다. 이미 발행된 것은 안에서 거르므로,
           바뀐 금액으로 새로 낼 것이 있을 때만 나간다. */
        if ((int) $order->expectedDeposit() === 0) {
            try {
                app(\App\Services\DepositAutoIssue::class)
                    ->run($order->refresh(), '주문 정정(본인부담금 없음)');
            } catch (\Throwable $e) {
                Log::warning('[주문 정정] 증빙 발행 실패', [
                    'order' => $order->order_number, 'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ok'      => true,
            'so_no'   => $새번호,
            'state'   => null,
            'message' => sprintf('새 판매주문 %s 을 등록했습니다%s', $새번호 ?: '', $확정말),
        ];
    }

    /** 할당ㆍ피킹이 걸린 건에 취소를 청해 둔다 — eud_cancel_yn = 'Y' */
    private function 취소요청(Order $order): array
    {
        $결과 = $this->부르기('post', 'so_cancel_request', [
            'ce_order_number' => $order->order_number,
            'so_no'           => $order->withworks_so_no,
            'reason'          => '주문 정정 — 제품ㆍ수량 변경으로 재등록합니다',
        ]);

        if (! $결과['ok'] && $결과['status'] === 404) {
            return ['ok' => false, 'message' =>
                '위드웍스에 취소 요청 주소(so_cancel_request)가 아직 없습니다. '
                . '창고 담당자에게 직접 연락해 할당ㆍ피킹을 되돌려 달라고 요청해 주십시오.'];
        }

        return $결과;
    }

    /**
     * 저쪽을 부른다.
     *
     * @return array{ok:bool, message:string, status:int, body:array}
     */
    private function 부르기(string $방법, string $길, array $몸): array
    {
        $저쪽 = $this->저쪽();

        if (! $저쪽) {
            return ['ok' => false, 'message' => '위드웍스 API 설정이 없습니다.', 'status' => 0, 'body' => []];
        }

        try {
            $res = Http::withToken($저쪽['token'])->timeout(20)->asForm()
                ->{$방법}("{$저쪽['url']}/api/v1/ce-admin/{$길}", $몸);
        } catch (\Throwable $e) {
            Log::error("[주문 정정] {$길} 에 닿지 못했습니다", ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => '위드웍스 서버에 연결할 수 없습니다.', 'status' => 0, 'body' => []];
        }

        $body = $res->json() ?? [];

        \App\Services\WithworksNotice::sent('주문 정정', $길, $몸, $body);

        if (! $res->successful() || ! ($body['success'] ?? false)) {
            return [
                'ok'      => false,
                'message' => $body['message'] ?? "HTTP {$res->status()}",
                'status'  => $res->status(),
                'body'    => $body,
            ];
        }

        return ['ok' => true, 'message' => '', 'status' => $res->status(), 'body' => $body];
    }
}
