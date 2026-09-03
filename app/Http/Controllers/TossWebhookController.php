<?php
// app/Http/Controllers/TossWebhookController.php
// 토스페이먼츠 웹훅 수신 (가상계좌 입금 알림)

namespace App\Http\Controllers;

use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TossWebhookController extends Controller
{
    public function __construct(private readonly VirtualAccountService $vaService) {}

    /**
     * POST /toss/webhook
     *
     * 토스페이먼츠에서 전송하는 웹훅 이벤트 처리
     * 지원 이벤트: VIRTUAL_ACCOUNT_DEPOSIT
     *
     * 서명 검증: Toss-Signature 헤더 (HMAC-SHA256)
     * - TOSS_WEBHOOK_SECRET 환경변수가 설정된 경우에만 검증
     * - 미설정 시 서명 검증 스킵 (개발환경)
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('tosspayments-webhook-signature', '');
        $txTime    = $request->header('tosspayments-webhook-transmission-time', '');

        // 서명 검증: 서명 헤더가 포함된 웹훅(payout/seller 등)에 한해, 보안키가 설정된 경우에만 수행.
        // 가상계좌 입금 웹훅은 서명이 없으므로 handleDepositWebhook 의 토스 API 재조회로 검증한다.
        if (config('toss.webhook_secret') && $signature !== ''
            && !$this->vaService->verifyWebhookSignature($rawBody, $signature, $txTime)) {
            Log::warning('[Toss] 웹훅 서명 불일치', ['sig' => substr($signature, 0, 24)]);
            return response()->json(['message' => '서명 불일치'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            return response()->json(['message' => '잘못된 페이로드'], 400);
        }

        $event = $payload['eventType'] ?? 'UNKNOWN';

        Log::info('[Toss] 웹훅 수신', ['event' => $event]);

        /* 카드 결제는 결제창이 우리 화면으로 돌아오면서 마무리된다. 그런데 고객이
           그 화면을 닫거나 통신이 끊기면 돌아오지 않는다 — 돈은 나갔는데 우리는
           모르는 채로 남는다(테스트 시나리오 3.1).

           토스가 그때도 PAYMENT_STATUS_CHANGED 로 알려 준다. 여기서 받아 마무리한다.
           두 길이 같은 건을 두 번 마무리해도 탈이 없다 — 발행은 스스로 두 번 내지
           않고, 창고 확정도 이미 확정된 건에는 그렇다고 답한다. */
        if ($event === 'PAYMENT_STATUS_CHANGED') {
            return $this->paymentStatusChanged($payload);
        }

        try {
            $tossPayment = $this->vaService->handleDepositWebhook($payload);

            return response()->json([
                'ok'         => true,
                'payment_id' => $tossPayment?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Toss] 웹훅 처리 오류: ' . $e->getMessage(), ['payload' => $payload]);
            return response()->json(['message' => '처리 오류: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 결제 상태가 바뀌었다는 알림 — 카드 결제가 여기로 온다.
     *
     * 페이로드의 status 를 믿지 않는다. 서명이 없는 웹훅이라, paymentKey 로 토스에
     * 다시 물어 확인한 값으로만 움직인다(가상계좌 입금 웹훅과 같은 방식이다).
     */
    private function paymentStatusChanged(array $payload): \Illuminate\Http\JsonResponse
    {
        $key = $payload['data']['paymentKey'] ?? $payload['paymentKey'] ?? null;

        if (! $key) {
            return response()->json(['ok' => true, 'skipped' => 'paymentKey 없음']);
        }

        try {
            $res = $this->vaService->fetchByPaymentKey($key);
        } catch (\Throwable $e) {
            Log::warning('[Toss] 결제 재조회 실패', ['key' => substr($key, 0, 12), 'error' => $e->getMessage()]);

            return response()->json(['message' => '재조회 실패'], 500);
        }

        /* 다 낸 것만 다룬다. 취소ㆍ부분취소는 우리 쪽 되돌리기(OrderCancellation)가
           담당자의 손을 거쳐 도는 일이라 여기서 건드리지 않는다. */
        if (($res['status'] ?? '') !== 'DONE') {
            return response()->json(['ok' => true, 'status' => $res['status'] ?? null]);
        }

        $tp = \App\Models\TossPayment::where('payment_key', $key)->first();

        if (! $tp?->order) {
            return response()->json(['ok' => true, 'skipped' => '이어진 주문 없음']);
        }

        /* 가상계좌는 여기가 아니라 입금 웹훅이 다룬다 — 승인(DONE)이 곧 입금은 아니다 */
        if ($tp->method === 'VIRTUAL_ACCOUNT') {
            return response()->json(['ok' => true, 'skipped' => '가상계좌는 입금 웹훅이 다룬다']);
        }

        if (! $tp->deposited_at) {
            $tp->update(['status' => 'DONE', 'deposited_at' => now(), 'raw_response' => $res]);
        }

        try {
            app(\App\Services\DepositAutoIssue::class)->run($tp->order->refresh(), '토스 결제 웹훅');
        } catch (\Throwable $e) {
            /* 웹훅이 실패로 끝나면 토스가 다시 보낸다 — 발행에서 나는 오류가 그
               재시도를 부르지 않게 여기서 삼킨다. */
            Log::warning('[Toss] 카드 결제 뒤 자동 처리 실패', [
                'order' => $tp->order->order_number, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'order_id' => $tp->order_id]);
    }
}
