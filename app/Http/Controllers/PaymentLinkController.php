<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\TossPayment;
use App\Services\PaymentLinkService;
use App\Services\TossPayments\TossClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 결제 전송 — 환자에게 「여기서 내십시오」를 보내고, 낸 것을 받아 적는다.
 *
 * 결제 페이지(pay.show)와 그 결과(pay.done)는 환자가 여는 자리라 로그인 없이 열린다.
 * 대신 토큰으로만 찾는다 — 주문번호로 열리면 번호를 바꿔 가며 남의 주문을 볼 수 있다.
 */
class PaymentLinkController extends Controller
{
    public function __construct(private readonly PaymentLinkService $links) {}

    /** 만들고 보낸다 */
    public function store(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'method' => 'required|in:' . implode(',', array_keys(PaymentLink::METHODS)),
            'mobile' => 'nullable|string|max:20',
        ]);

        if ((int) $order->total_amount <= 0) {
            return response()->json(['success' => false, 'message' => '결제할 금액이 없습니다.'], 422);
        }

        $res = $this->links->issue($order, $data['method'], $data['mobile'] ?? null);

        return response()->json([
            'success' => $res['sent'],
            'message' => $res['message'],
            'link'    => $this->row($res['link']),
        ]);
    }

    /** 이 주문에 무엇을 보냈는가 */
    public function index(Order $order): JsonResponse
    {
        $rows = PaymentLink::where('order_id', $order->id)
            ->with('creator')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($l) => $this->row($l));

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    /** 잘못 보냈으면 닫는다 — 링크를 지우지 않고 열리지 않게만 한다(보낸 사실은 남는다) */
    public function cancel(PaymentLink $paymentLink): JsonResponse
    {
        if ($paymentLink->status === 'paid') {
            return response()->json(['success' => false, 'message' => '이미 결제된 건은 닫을 수 없습니다.'], 422);
        }

        $paymentLink->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => '닫았습니다.', 'link' => $this->row($paymentLink)]);
    }

    // ── 환자가 여는 자리 ──────────────────────────────────

    /** 결제 페이지 */
    public function show(string $token): View
    {
        $link = PaymentLink::where('token', $token)->with('order.patient')->firstOrFail();

        // 기한이 지난 것은 그 자리에서 표시해 준다 — 눌러 봐야 안 되는 이유를 알 수 있어야 한다
        if ($link->status === 'sent' && $link->expires_at && $link->expires_at->isPast()) {
            $link->update(['status' => 'expired']);
        }

        return view('pay.show', [
            'link'      => $link,
            'order'     => $link->order,
            'clientKey' => config('toss.client_key'),
        ]);
    }

    /**
     * 토스 결제창이 끝나고 돌아오는 자리.
     *
     * 성공했다고 그대로 믿지 않는다 — 브라우저가 들고 온 값이라 누구든 만들 수 있다.
     * 서버에서 승인(confirm)까지 마쳐야 낸 것으로 적는다.
     */
    public function done(Request $request, string $token): View
    {
        $link = PaymentLink::where('token', $token)->with('order.patient')->firstOrFail();

        $paymentKey = (string) $request->query('paymentKey', '');
        $tossOrder  = (string) $request->query('orderId', '');
        $amount     = (int) $request->query('amount', 0);
        $error      = $request->query('message') ?: $request->query('code');

        if (!$paymentKey || !$tossOrder) {
            return view('pay.done', ['link' => $link, 'ok' => false,
                                     'message' => $error ?: '결제가 완료되지 않았습니다.']);
        }

        if ($amount !== (int) $link->amount) {
            Log::warning('[결제전송] 금액이 다르다', ['link' => $link->id, 'sent' => $link->amount, 'got' => $amount]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'message' => '결제 금액이 맞지 않습니다.']);
        }

        try {
            $res = app(TossClient::class)->post('/v1/payments/confirm', [
                'paymentKey' => $paymentKey,
                'orderId'    => $tossOrder,
                'amount'     => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[결제전송] 승인 실패', ['link' => $link->id, 'error' => $e->getMessage()]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'message' => $e->getMessage()]);
        }

        $this->links->markPaid($link, $paymentKey, $tossOrder);
        $this->record($link, $res);

        return view('pay.done', ['link' => $link->refresh(), 'ok' => true, 'message' => null, 'toss' => $res]);
    }

    // ── 안쪽 ──────────────────────────────────────────────

    /** 토스가 준 결과를 주문 쪽 결제 기록으로도 남긴다 — 정산은 그 표를 본다 */
    private function record(PaymentLink $link, array $res): void
    {
        $va = $res['virtualAccount'] ?? null;

        TossPayment::updateOrCreate(
            ['payment_key' => $res['paymentKey'] ?? $link->payment_key],
            [
                'order_id'       => $link->order_id,
                'toss_order_id'  => $res['orderId'] ?? $link->toss_order_id,
                'method'         => $va ? 'VIRTUAL_ACCOUNT' : 'CARD',
                'status'         => $res['status'] ?? 'DONE',
                'amount'         => (int) ($res['totalAmount'] ?? $link->amount),
                'bank'           => $va['bankCode']      ?? null,
                'account_number' => $va['accountNumber'] ?? null,
                'customer_name'  => $va['customerName']  ?? ($link->order?->patient?->name),
                'due_date'       => $va['dueDate']       ?? null,
                'deposited_at'   => $va ? null : now(),
                'raw_response'   => $res,
            ],
        );
    }

    private function row(PaymentLink $l): array
    {
        return [
            'id'      => $l->id,
            'method'  => $l->method_label,
            'amount'  => $l->amount,
            'status'  => $l->status,
            'status_label' => $l->status_label,
            'tone'    => $l->status_tone,
            'channel' => ['alimtalk' => '알림톡', 'sms' => '문자'][$l->channel] ?? '-',
            'receiver' => $l->receiver,
            'sent_at' => $l->sent_at?->format('Y-m-d H:i'),
            'paid_at' => $l->paid_at?->format('Y-m-d H:i'),
            'url'     => $l->url,
            'creator' => $l->creator?->name,
            'error'   => $l->error,
            'open'    => $l->is_open,
        ];
    }
}
