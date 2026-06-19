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
        $signature = $request->header('Toss-Signature', '');

        // 서명 검증 (시크릿이 설정된 경우만)
        if (config('toss.webhook_secret') && !$this->vaService->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('[Toss] 웹훅 서명 불일치', ['sig' => substr($signature, 0, 20)]);
            return response()->json(['message' => '서명 불일치'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            return response()->json(['message' => '잘못된 페이로드'], 400);
        }

        Log::info('[Toss] 웹훅 수신', ['event' => $payload['eventType'] ?? 'UNKNOWN']);

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
}
