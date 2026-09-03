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
            'customerKey' => $this->customerKey($link),
        ]);
    }

    /**
     * 결제위젯에 줄 「이 사람」 표.
     *
     * 여태 비회원(ANONYMOUS)으로 열었더니 결제수단 칸이 비어 있었다 — 위젯에 브랜드페이가
     * 들어 있고, 브랜드페이는 비회원에게 내주지 않는다(「비회원은 브랜드페이 사용이
     * 어려워요」). 그래서 사람마다 하나씩 붙는 표를 준다.
     *
     * 표는 우리 앱 열쇠로 뜬 것이라 밖에서 지어낼 수 없고, 같은 환자에게는 늘 같은 것이
     * 나온다 — 다음에 다시 낼 때 저장해 둔 카드가 그대로 보인다. 환자 줄이 아직 없으면
     * 그 결제 한 건에만 붙는 표를 준다.
     */
    private function customerKey(PaymentLink $link): string
    {
        $seed = $link->order?->patient_id
            ? 'patient:' . $link->order->patient_id
            : 'link:' . $link->token;

        return 'ce-' . substr(hash_hmac('sha256', $seed, (string) config('app.key')), 0, 40);
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
            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false,
                                     'message' => $error ?: '결제가 완료되지 않았습니다.']);
        }

        if ($amount !== (int) $link->amount) {
            Log::warning('[결제전송] 금액이 다르다', ['link' => $link->id, 'sent' => $link->amount, 'got' => $amount]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false, 'message' => '결제 금액이 맞지 않습니다.']);
        }

        try {
            $res = app(TossClient::class)->post('/v1/payments/confirm', [
                'paymentKey' => $paymentKey,
                'orderId'    => $tossOrder,
                'amount'     => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[결제전송] 승인 실패', ['link' => $link->id, 'error' => $e->getMessage()]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false, 'message' => $e->getMessage()]);
        }

        /* 가상계좌는 승인이 끝나도 아직 낸 것이 아니다 — 계좌가 나왔을 뿐이고, 돈은
           환자가 은행에 넣어야 들어온다(WAITING_FOR_DEPOSIT). 여기서 「결제완료」로
           적으면 목록에서 받은 돈으로 읽히고, 담당자가 입금을 기다리지 않게 된다.
           들어온 것은 토스가 입금 웹훅으로 알려 준다 — 그때 낸 것으로 적는다. */
        $waiting = ($res['status'] ?? '') === 'WAITING_FOR_DEPOSIT';

        if ($waiting) {
            $link->update(['payment_key' => $paymentKey, 'toss_order_id' => $tossOrder]);
        } else {
            $this->links->markPaid($link, $paymentKey, $tossOrder);
        }

        $this->record($link, $res);

        return view('pay.done', [
            'link' => $link->refresh(), 'ok' => true, 'message' => null,
            'toss' => $res, 'waiting' => $waiting,
        ]);
    }

    // ── 안쪽 ──────────────────────────────────────────────

    /** 토스가 준 결과를 주문 쪽 결제 기록으로도 남긴다 — 정산은 그 표를 본다 */
    private function record(PaymentLink $link, array $res): void
    {
        $va = $res['virtualAccount'] ?? null;

        $tp = TossPayment::updateOrCreate(
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

        /* 가상계좌를 고른 사람에게는 계좌를 문자로 한 번 더 적어 보낸다.
           이 화면을 닫으면 계좌를 다시 볼 곳이 우리 쪽에 없어, 담당자에게 전화해
           다시 묻는 일이 잦았다.
           방금 처음 담긴 때만 보낸다 — 이 자리는 새로고침으로 두 번 들어올 수 있다. */
        if ($va && $tp->wasRecentlyCreated) {
            app(\App\Services\PaymentLinkService::class)->sendVirtualAccount($link, $va);
        }

        /* 카드로 낸 건은 이 자리에서 다 낸 것이다 — 가상계좌처럼 기다릴 것이 없다.
           그런데 여태 여기서 아무것도 부르지 않아, 서류도 창고 확정도 돌지 않았다.
           담당자가 정산/회계에서 「입금확인」을 손으로 눌러야 그때 돌았다
           (테스트 시나리오 3.1.1ㆍ3.3 · 2026-09-03).

           가상계좌는 여기서 부르지 않는다. 계좌가 나왔을 뿐 돈은 아직 들어오지
           않았다 — 들어오면 입금 웹훅이 부른다. */
        if (! $va && $tp->order) {
            $this->afterPaid($tp->order, '카드 결제');
        }
    }

    /**
     * 돈이 들어온 뒤에 하는 일 — 서류를 내고 창고를 확정한다.
     *
     * 실패해도 결제는 이미 끝난 것이라 화면을 막지 않는다. 고객은 냈는데 「오류」를
     * 보면 다시 내려 든다 — 못 한 일은 로그와 자취에 남고, 담당자가 정산/회계에서
     * 잇는다.
     */
    private function afterPaid(\App\Models\Order $order, string $cause): void
    {
        try {
            app(\App\Services\DepositAutoIssue::class)->run($order->refresh(), $cause);
        } catch (\Throwable $e) {
            Log::warning('[결제전송] 결제 뒤 자동 처리 실패', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);
        }
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
