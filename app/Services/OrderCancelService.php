<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentLink;
use App\Services\TossPayments\PaymentCancelService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 창고로 넘긴 주문을 되돌린다 — 주문 정정과 주문 취소 (2026-09-14 지시).
 *
 * 되돌리는 길은 창고가 어디까지 갔느냐가 가른다.
 *
 *   출고 신규        판매주문을 그 자리에서 취소하고 지운다. 한 번에 끝난다.
 *   할당ㆍ피킹ㆍ송장  우리가 취소를 **청하고**(eud_cancel_yn = 'Y') 창고 담당자가
 *                    할당ㆍ피킹을 되돌리기를 기다린다. 그 되돌림이 끝나는 순간
 *                    위드웍스가 스스로 확정취소ㆍ삭제까지 잇는다.
 *   출고 완료        여기서 하지 않는다. 교환/반품/취소 화면이 할 일이다.
 *
 * **돈과 증빙도 함께 되돌린다** (2026-09-14 지시 ①-가). 창고만 되돌리고 돈을 두면
 * 받은 것이 남은 주문이 생기고, 그것은 어느 목록에도 「받을 돈」으로 서지 않아
 * 다음 달 정산에서야 드러난다.
 */
class OrderCancelService
{
    public function __construct(
        private readonly PaymentCancelService $결제취소,
    ) {}

    /** 위드웍스로 가는 길 — 없으면 부르지 않는다 */
    private function 저쪽(): ?array
    {
        $url   = rtrim((string) config('services.demoworks.api_url'), '/');
        $token = (string) config('services.demoworks.token');

        return ($url && $token) ? [$url, $token] : null;
    }

    /**
     * 주문을 취소한다.
     *
     * @return array{ok: bool, message: string, state: ?string}
     */
    public function 취소(Order $order, string $사유): array
    {
        if (! $order->취소가능한가()) {
            return [
                'ok'      => false,
                'state'   => $order->cancel_state,
                'message' => $order->창고단계() === 'shipped'
                    ? '이미 출고된 주문입니다 — 교환/반품/취소 화면에서 처리해 주십시오.'
                    : ($order->취소기다리는중인가()
                        ? '이미 취소 요청 중인 주문입니다.'
                        : '취소할 수 있는 주문이 아닙니다.'),
            ];
        }

        $단계 = $order->창고단계();

        /* ── ① 창고 ─────────────────────────────────────────────
           아직 넘기지 않은 건은 창고에 할 말이 없다. */
        $창고말 = '';

        if ($단계 === 'new') {
            $결과 = $this->판매주문취소($order);
            if (! $결과['ok']) {
                return ['ok' => false, 'state' => null, 'message' => $결과['message']];
            }
            $창고말 = '위드웍스 판매주문을 취소했습니다.';
        } elseif ($단계 === 'working') {
            $결과 = $this->취소요청($order, $사유);
            if (! $결과['ok']) {
                return ['ok' => false, 'state' => null, 'message' => $결과['message']];
            }
            $창고말 = '창고에 취소를 요청했습니다 — 할당ㆍ피킹이 되돌려지면 자동으로 취소됩니다.';
        }

        /* ── ② 돈과 증빙 ─────────────────────────────────────────
           창고가 아직 되돌리는 중이어도 돈은 지금 무른다. 받아 둔 돈을 들고 기다릴
           까닭이 없고, 기다리는 동안 환자에게는 「취소했다는데 돈은 그대로」다. */
        $돈말 = $this->돈되돌리기($order, $사유);

        /* ── ③ 우리 줄 ──────────────────────────────────────────
           바로 끝난 건은 취소로, 기다리는 건은 요청으로 적어 둔다. */
        $끝났나 = $단계 !== 'working';

        $order->forceFill([
            'cancel_state'        => $끝났나 ? Order::CANCEL_DONE : Order::CANCEL_REQUESTED,
            'cancel_requested_at' => now(),
            'cancel_requested_by' => Auth::id(),
            'cancel_reason'       => mb_substr($사유, 0, 200),
            'cancel_done_at'      => $끝났나 ? now() : null,
        ]);

        if ($끝났나) {
            $order->status = 'cancelled';
        }

        $order->save();

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log($끝났나
                ? "주문 취소 ({$order->order_number}) — {$사유}"
                : "주문 취소 요청 ({$order->order_number}) — {$사유}");

        return [
            'ok'      => true,
            'state'   => $order->cancel_state,
            'message' => trim(($창고말 ? $창고말 . ' ' : '') . $돈말) ?: '주문을 취소했습니다.',
        ];
    }

    /**
     * 정정으로 금액이 바뀌었을 때 돈을 맞춘다 (2026-09-14 지시 ②).
     *
     *   결제 전  보낸 결제 링크를 해지하고, 바뀐 금액으로 다시 보낼 수 있게 둔다
     *   결제 후  줄어든 차액만큼 부분 환불한다
     *
     * 늘어난 때는 무르지 않는다 — 더 받을 돈은 새 링크로 청한다.
     *
     * @param int $이전 정정 전 기준 금액 — **실제로 오간 돈**이다
     *                  (Order::결제기준금액). 주문의 patient_copay 가 아니다 —
     *                  정정 직전에 다른 요청이 그 값을 이미 바꿔 놓기 때문이다
     *                  (2026-09-15 고침).
     */
    public function 금액맞추기(Order $order, int $이전): string
    {
        $지금 = (int) $order->expectedDeposit();

        if ($지금 === $이전) {
            return '';
        }

        // ── 결제 전 — 보낸 링크를 해지한다
        if (! $order->isDepositConfirmed()) {
            $해지 = PaymentLink::where('order_id', $order->id)
                ->where('status', 'sent')
                ->update(['status' => 'cancelled']);

            return $해지
                ? "금액이 바뀌어 보낸 결제 링크 {$해지}건을 해지했습니다 — 바뀐 금액으로 다시 보내 주십시오."
                : '';
        }

        // ── 결제 후 — 줄어든 만큼만 무른다
        if ($지금 >= $이전) {
            $늘어난 = number_format($지금 - $이전);

            return "이미 받은 건입니다 — 늘어난 {$늘어난}원은 결제 링크로 별도 청구해 주십시오.";
        }

        $차액 = $이전 - $지금;
        $결과 = $this->결제취소->cancel($order, "주문 정정 — 금액 변경 (차액 {$차액}원)", $차액);

        if (! ($결과['ok'] ?? false)) {
            Log::warning('[주문 정정] 부분 환불 실패', [
                'order' => $order->order_number, 'amount' => $차액, 'message' => $결과['message'] ?? '',
            ]);

            return "차액 " . number_format($차액) . "원을 환불하지 못했습니다 — " . ($결과['message'] ?? '') ;
        }

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log("주문 정정 부분 환불 ({$order->order_number}) — " . number_format($차액) . '원');

        return "차액 " . number_format($차액) . "원을 환불했습니다.";
    }

    // ── 안쪽 ────────────────────────────────────────────────────────────

    /** 돈과 증빙을 무른다 — 취소할 때 */
    private function 돈되돌리기(Order $order, string $사유): string
    {
        $말 = [];

        if ($order->isDepositConfirmed()) {
            $결과 = $this->결제취소->cancel($order, mb_substr("주문 취소 — {$사유}", 0, 200));
            $말[] = ($결과['ok'] ?? false)
                ? '결제를 취소했습니다.'
                : ('결제를 취소하지 못했습니다 — ' . ($결과['message'] ?? ''));
        }

        /* 아직 받기 전이면 보낸 링크를 해지한다 — 취소한 건의 링크가 살아 있으면
           환자가 그 사이에 결제해 버린다. */
        $해지 = PaymentLink::where('order_id', $order->id)
            ->where('status', 'sent')
            ->update(['status' => 'cancelled']);

        if ($해지) {
            $말[] = "보낸 결제 링크 {$해지}건을 해지했습니다.";
        }

        /* 증빙은 발행된 것만 무른다. 국세청 실신고라 자동으로 부르되, 실패하면 그 사실을
           그대로 적어 담당자가 손으로 마무리하게 한다 — 조용히 넘기면 취소된 건의
           세금계산서가 살아 남는다. */
        if ($order->tax_invoice_status === 'issued') {
            $말[] = '세금계산서는 「세금계산서 취소」에서 별도로 취소하셔야 합니다.';
        }

        if ($order->cash_receipt_status === 'issued') {
            $말[] = '현금영수증은 「현금영수증 취소」에서 별도로 취소하셔야 합니다.';
        }

        return implode(' ', $말);
    }

    /** 출고가 신규인 건 — 판매주문을 그 자리에서 취소하고 지운다 */
    private function 판매주문취소(Order $order): array
    {
        $저쪽 = $this->저쪽();

        if (! $저쪽) {
            return ['ok' => false, 'message' => '위드웍스 API 설정이 없습니다.'];
        }

        [$url, $token] = $저쪽;

        try {
            $res = Http::withToken($token)->timeout(20)->asForm()
                ->post("{$url}/api/v1/ce-admin/so_cancel", [
                    'ce_order_number' => $order->order_number,
                    'so_no'           => $order->withworks_so_no,
                ]);
        } catch (\Throwable $e) {
            Log::error('[주문 취소] 위드웍스에 닿지 못했습니다', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => '위드웍스 서버에 연결할 수 없습니다.'];
        }

        $몸 = $res->json();

        if (! $res->successful() || ! ($몸['success'] ?? false)) {
            return ['ok' => false, 'message' => '위드웍스 취소 실패 — ' . ($몸['message'] ?? "HTTP {$res->status()}")];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * 할당ㆍ피킹이 걸린 건 — 취소를 청해 둔다.
     *
     * 위드웍스의 schedule_by_ships.eud_cancel_yn 을 'Y' 로 세우는 일이다. 그 값이
     * 서면 저쪽이 출고확정ㆍ신규할당ㆍ신규피킹을 잠그고, 담당자가 할당취소ㆍ피킹취소로
     * 출고를 신규로 되돌리는 그 순간 스스로 확정취소ㆍ삭제까지 잇는다
     * (SalesOrderController::registerEudCancelAutoUnwindHook).
     *
     * 저쪽에 그 값을 세우는 주소가 아직 없다 — 없으면 그렇다고 말한다. 조용히 성공으로
     * 넘기면 담당자는 청해 둔 줄 알고 기다리는데 창고는 아무것도 모른다.
     */
    private function 취소요청(Order $order, string $사유): array
    {
        $저쪽 = $this->저쪽();

        if (! $저쪽) {
            return ['ok' => false, 'message' => '위드웍스 API 설정이 없습니다.'];
        }

        [$url, $token] = $저쪽;

        try {
            $res = Http::withToken($token)->timeout(20)->asForm()
                ->post("{$url}/api/v1/ce-admin/so_cancel_request", [
                    'ce_order_number' => $order->order_number,
                    'so_no'           => $order->withworks_so_no,
                    'reason'          => mb_substr($사유, 0, 200),
                ]);
        } catch (\Throwable $e) {
            Log::error('[주문 취소 요청] 위드웍스에 닿지 못했습니다', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => '위드웍스 서버에 연결할 수 없습니다.'];
        }

        if ($res->status() === 404) {
            return ['ok' => false, 'message' =>
                '위드웍스에 취소 요청 주소(so_cancel_request)가 아직 없습니다. '
                . '창고 담당자에게 직접 연락해 할당ㆍ피킹을 되돌려 달라고 요청해 주십시오.'];
        }

        $몸 = $res->json();

        if (! $res->successful() || ! ($몸['success'] ?? false)) {
            return ['ok' => false, 'message' => '위드웍스 취소 요청 실패 — ' . ($몸['message'] ?? "HTTP {$res->status()}")];
        }

        return ['ok' => true, 'message' => ''];
    }
}
