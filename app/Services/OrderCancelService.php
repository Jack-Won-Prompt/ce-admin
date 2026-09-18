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
     * 정정으로 금액이 바뀌었을 때 돈을 맞춘다 (2026-09-14 지시 ②ㆍ2026-09-15 고침).
     *
     *   결제 전  보낸 링크를 해지하고 바뀐 금액으로 다시 보낸다
     *   결제 후  받은 돈을 **통째로 무르고** 바뀐 금액으로 다시 보낸다
     *
     * 결제 후에 차액만 부분 환불하던 것을 걷어냈다 (2026-09-15 지시). 부분 환불은
     * 늘어난 때를 담지 못해 「차액은 따로 청구해 주십시오」라는 안내로 끝났고, 그
     * 안내는 토스트로 지나가 담당자가 잊으면 모자란 채로 남았다. 무르고 다시
     * 청하면 늘든 줄든 한 가지 길이라 헷갈릴 자리가 없다.
     *
     * @param int $이전 정정 전 기준 금액 — **실제로 오간 돈**이다
     *                  (Order::결제기준금액). 주문의 patient_copay 가 아니다 —
     *                  정정 직전에 다른 요청이 그 값을 이미 바꿔 놓기 때문이다
     *                  (2026-09-15 고침).
     */
    public function 금액맞추기(Order $order, int $이전): string
    {
        $지금 = (int) $order->expectedDeposit();

        /* 기관부담이 바뀐 것도 「금액이 바뀐 것」이다 (2026-09-18 운영 시험에서 드러남).

           여태 환자에게서 받을 돈(본인부담)만 견주었다. 그래서 차상위경감ㆍ기초처럼
           **본인부담이 0원이고 기관이 전액을 내는 건**은 수량을 반으로 줄여도
           0 === 0 이라 그냥 지나갔다 — 옛 금액의 세금계산서가 그대로 살아남았다.
           세금계산서는 기관부담으로 발행되므로 그쪽이 바뀌면 반드시 물러야 한다.

           견주는 잣대는 **발행된 계산서에 적힌 금액**(tax_invoice_supply)이다.
           정정 전 주문 금액을 쥐어 두는 방법은 쓸 수 없다 — 화면이 ［주문 정정］
           한 번에 요청을 여럿 보내고, 앞선 요청이 이미 주문 금액을 새 값으로
           맞춰 놓기 때문이다. 본인부담은 링크ㆍ입금이 옛 값을 붙들어 주는데
           기관부담에는 그런 자리가 없다. 계산서에 적힌 금액은 발행한 그때 값
           그대로라 흔들리지 않는다. */
        $지금기관 = (int) ($order->nhis_amount ?? 0);
        $낸금액   = (int) round((float) $order->tax_invoice_supply + (float) $order->tax_invoice_vat);

        $기관바뀜 = $order->tax_invoice_status === 'issued'
                 && $낸금액 > 0
                 && $낸금액 !== $지금기관;

        if ($지금 === $이전 && ! $기관바뀜) {
            return '';
        }

        /* 환자 돈은 그대로인데 기관부담만 바뀐 건 — 증빙만 무르고 결제는 손대지 않는다.
           받을 돈이 애초에 없어 무를 결제도, 다시 보낼 링크도 없다. */
        if ($지금 === $이전) {
            return $this->증빙무르기($order, $낸금액, $지금기관);
        }

        /* 발행된 증빙을 먼저 무른다 (2026-09-16 지시).

           여태 정정에서 증빙을 그대로 두었다. 그래서 금액이 바뀌어도 옛 금액의
           세금계산서가 살아 있었고, 다시 결제해도 「이미 발행됨」으로 걸러져 새
           금액이 영영 나가지 않았다 — 받은 돈과 신고한 금액이 어긋난 채 남았다.

           무르면 상태가 cancelled 로 서므로, 재결제 때 DepositAutoIssue 가 **바뀐
           금액으로 다시 낸다.** 우리가 만든 서류(양식ㆍ거래명세서ㆍ카드매출전표)도
           함께 지워 다시 그리게 한다.

           결제 전이든 후든 똑같이 무른다 — 본인부담 0원 건은 입금 없이도 증빙이
           나가 있기 때문이다. */
        $증빙말 = $this->증빙무르기($order, $이전, $지금);

        /* ── 결제 전 — 해지하고 바뀐 금액으로 다시 보낸다 ────────────────
           여태 해지만 하고 「다시 보내 주십시오」라 알렸다. 그 말은 토스트로
           지나가고, 담당자가 정정을 마친 뒤 결제전송을 따로 눌러야 했다 — 잊으면
           환자는 옛 링크가 죽은 줄도 모른 채 기다린다. */
        if (! $order->isDepositConfirmed()) {
            return trim($증빙말 . ' ' . $this->링크다시보내기($order, $지금));
        }

        /* ── 결제 후 — 받은 돈을 통째로 무르고 다시 청한다 (2026-09-15 지시) ── */
        $결과 = $this->결제취소->cancel($order, sprintf(
            '주문 정정 — 금액 변경 (%s원 → %s원)', number_format($이전), number_format($지금)
        ));

        if (! ($결과['ok'] ?? false)) {
            Log::warning('[주문 정정] 결제 취소 실패', [
                'order' => $order->order_number, '이전' => $이전, '지금' => $지금,
                'message' => $결과['message'] ?? '',
            ]);

            /* 가상계좌로 받은 돈은 왔던 길로 되돌아가지 않아, 돌려줄 계좌
               (은행ㆍ번호ㆍ예금주)가 있어야 무를 수 있다. 정정 자체는 막지 않는다 —
               제품ㆍ수량은 이미 바뀌었고, 돈만 사람 손을 거치면 된다. 어디서
               마무리하는지를 분명히 적어 둔다.

               가상계좌를 아예 쓰지 않으려면 서비스 연동 설정에서 끈다
               (토스페이먼츠 › 가상계좌 발급 · 2026-09-15 지시). */
            $가상계좌 = str_contains($결과['message'] ?? '', '가상계좌');

            return '주문은 정정했으나 받은 돈을 무르지 못했습니다 — ' . ($결과['message'] ?? '')
                 . ($가상계좌
                    ? ' 교환/반품/취소 화면에서 돌려줄 계좌를 받아 환불한 뒤,'
                      . ' 「결제전송」으로 바뀐 금액을 다시 청해 주십시오.'
                    : ' 결제를 취소한 뒤 바뀐 금액으로 다시 보내 주십시오.');
        }

        /* 손으로 확인해 둔 자취를 지운다. 남겨 두면 무른 뒤에도 「받은 건」으로
           보여, 바뀐 금액의 링크가 「이미 결제가 끝난 주문」으로 막힌다
           (PaymentLinkController). */
        $order->forceFill([
            'deposit_confirmed_at' => null,
            'deposit_confirmed_by' => null,
            'deposit_amount'       => null,
        ])->save();

        activity()->causedBy(Auth::user())->performedOn($order)->log(sprintf(
            '주문 정정 결제 취소 (%s) — %s원을 돌려주고 %s원으로 다시 청합니다',
            $order->order_number, number_format($이전), number_format($지금)
        ));

        return trim($증빙말 . ' 받은 ' . number_format($이전) . '원을 돌려주었습니다. '
                  . $this->링크다시보내기($order->refresh(), $지금, true));
    }

    /**
     * 발행된 증빙을 무른다 — 바뀐 금액으로 다시 내기 위해서다 (2026-09-16 지시).
     *
     * 공단 청구는 건드리지 않는다. 정정은 주문을 되돌리는 일이 아니라 내용을 고치는
     * 일이라, 청구는 그대로 가고 금액만 바뀐다.
     *
     * 무르지 못해도 정정 자체는 막지 않는다 — 제품ㆍ수량은 이미 바뀌었고, 증빙만
     * 사람 손을 거치면 된다. 어디서 마무리하는지를 분명히 적어 돌려준다.
     */
    private function 증빙무르기(Order $order, int $이전, int $지금): string
    {
        $낸것있나 = $order->tax_invoice_status === 'issued'
                 || $order->cash_receipt_status === 'issued';

        $왜 = sprintf('주문 정정 — 금액 변경 (%s원 → %s원)', number_format($이전), number_format($지금));

        try {
            $결과 = app(\App\Services\OrderCancellation::class)->증빙무르기($order, $왜);
        } catch (\Throwable $e) {
            Log::error('[주문 정정] 증빙을 무르지 못했습니다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return '증빙을 무르지 못했습니다 — 「세금계산서 취소」ㆍ「현금영수증 취소」에서 '
                 . '직접 취소한 뒤 다시 발행해 주십시오.';
        }

        if ($결과['warnings']) {
            return '증빙 일부를 무르지 못했습니다 — ' . implode(' / ', $결과['warnings'])
                 . ' 직접 취소한 뒤 다시 발행해 주십시오.';
        }

        if (! $낸것있나 && ! $결과['docs']) {
            return '';                      // 무를 것이 없었다 — 말할 것도 없다
        }

        $말 = [];
        if ($낸것있나)          { $말[] = '발행된 증빙을 취소했습니다.'; }
        if ($결과['docs'])      { $말[] = "서류 {$결과['docs']}장을 다시 그립니다."; }
        $말[] = '결제가 확인되면 바뀐 금액으로 다시 발행됩니다.';

        return implode(' ', $말);
    }

    /**
     * 살아 있는 결제 링크를 해지하고 바뀐 금액으로 다시 보낸다.
     *
     * 어느 방법으로 보낼지는 마지막에 보낸 것을 따른다. 처음 보낼 때 담당자가
     * 고른 것이고, 금액만 바뀌었을 뿐 방법이 달라질 까닭이 없다.
     *
     * @param bool $무를것없어도 살아 있는 링크가 없어도 보낸다. 결제가 끝난 건은
     *                           링크가 이미 「결제완료」로 닫혀 있어 해지할 것이
     *                           없지만, 무른 뒤에는 다시 보내야 한다.
     */
    private function 링크다시보내기(Order $order, int $지금, bool $무를것없어도 = false): string
    {
        $살아있던것 = PaymentLink::where('order_id', $order->id)
            ->where('status', 'sent')->latest('id')->get();

        if ($살아있던것->isEmpty() && ! $무를것없어도) {
            return '';
        }

        if ($살아있던것->isNotEmpty()) {
            PaymentLink::whereIn('id', $살아있던것->pluck('id'))
                ->update(['status' => 'cancelled']);
        }

        $해지 = $살아있던것->count();

        /* 보낼 방법 — 살아 있던 것이 없으면 지난 것 가운데 마지막을 본다 */
        $본보기 = $살아있던것->first()
               ?? PaymentLink::where('order_id', $order->id)->latest('id')->first();

        if (! $본보기) {
            return $해지 ? "보낸 결제 링크 {$해지}건을 해지했습니다." : '';
        }

        /* 바뀐 금액으로 다시 보낸다. 못 보내도 해지는 이미 끝났다 —
           그 사실을 그대로 알려 담당자가 손으로 보내게 한다. */
        try {
            $새것 = app(\App\Services\PaymentLinkService::class)
                        ->issue($order->refresh(), $본보기->method, $본보기->receiver);
        } catch (\Throwable $e) {
            Log::warning('[주문 정정] 결제 링크를 다시 보내지 못했습니다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);
            $새것 = ['sent' => false, 'message' => $e->getMessage()];
        }

        $앞말   = $해지 ? "보낸 결제 링크 {$해지}건을 해지하고, " : '';
        $바뀐금액 = number_format($지금);

        return ($새것['sent'] ?? false)
            ? "{$앞말}바뀐 금액 {$바뀐금액}원으로 다시 보냈습니다."
            : ($해지 ? "보낸 결제 링크 {$해지}건을 해지했습니다 — " : '')
              . "바뀐 금액으로 다시 보내지 못했으므로 「결제전송」으로 직접 보내 주십시오 ("
              . ($새것['message'] ?? '') . ')';
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

        /* 증빙은 발행된 것만 무른다 (2026-09-16 고침).

           여태 안내만 내고 사람 손에 맡겼다. 그러면 취소한 건의 세금계산서가 살아 남아
           국세청에는 팔린 것으로 남는다 — 안내는 토스트로 지나가고, 담당자가 잊으면
           아무도 되짚지 않는다.

           자동으로 부르되, 실패하면 그 사실을 그대로 적어 손으로 마무리하게 한다.
           공단 청구는 여기서 손대지 않는다 — 사람이 공단 사이트에서 해야 한다. */
        $낸것있나 = $order->tax_invoice_status === 'issued'
                 || $order->cash_receipt_status === 'issued';

        if ($낸것있나) {
            try {
                $증빙 = app(\App\Services\OrderCancellation::class)
                            ->증빙무르기($order, "주문 취소 — {$사유}");

                $말[] = $증빙['warnings']
                    ? '증빙 일부를 무르지 못했습니다 — ' . implode(' / ', $증빙['warnings'])
                      . ' 직접 취소해 주십시오.'
                    : '발행된 증빙을 취소했습니다.';
            } catch (\Throwable $e) {
                Log::error('[주문 취소] 증빙을 무르지 못했습니다', [
                    'order' => $order->order_number, 'error' => $e->getMessage(),
                ]);

                $말[] = '증빙을 무르지 못했습니다 — 「세금계산서 취소」ㆍ「현금영수증 취소」에서 '
                      . '직접 취소해 주십시오.';
            }
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
