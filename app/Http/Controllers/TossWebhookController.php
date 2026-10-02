<?php
// app/Http/Controllers/TossWebhookController.php
// 토스페이먼츠 웹훅 수신 (가상계좌 입금 알림)

namespace App\Http\Controllers;

use App\Services\TossPayments\VirtualAccountService;
use App\Support\WebhookLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TossWebhookController extends Controller
{
    public function __construct(private readonly VirtualAccountService $vaService) {}

    /**
     * POST /toss/webhook
     *
     * 토스페이먼츠에서 전송하는 웹훅 이벤트 처리
     * 받는 이벤트: PAYMENT_STATUS_CHANGED (카드ㆍ간편결제) · DEPOSIT_CALLBACK (가상계좌 입금)
     *   ㆍ상점관리자 웹훅 등록 화면의 이름이 그 둘이다. 예전 이름
     *     VIRTUAL_ACCOUNT_DEPOSIT 도 그대로 받는다(2026-09-10).
     *
     * 서명 검증: Toss-Signature 헤더 (HMAC-SHA256)
     * - TOSS_WEBHOOK_SECRET 환경변수가 설정된 경우에만 검증
     * - 미설정 시 서명 검증 스킵 (개발환경)
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        /* 이름으로 꺼낸다 — 주소가 두 꼴이라 차례로 받으면 어긋난다 (2026-09-18) */
        $key       = $request->route('key');
        $rawBody   = $request->getContent();
        $signature = $request->header('tosspayments-webhook-signature', '');
        $txTime    = $request->header('tosspayments-webhook-transmission-time', '');

        /* 주소에 박은 열쇠 (2026-09-18 지시).

           서명이 붙는 갈래는 아래에서 서명으로 가르지만, **가상계좌 입금에는 서명이
           없다** — 실제로 들어오고 있는 것이 그 갈래다. 열쇠는 서명이 없는 갈래까지
           덮는다. 「열쇠 확인」이 꺼져 있으면 지나간다 — 토스 콘솔의 주소를 바꿀 틈이다. */
        if (! \App\Support\WebhookKeys::맞나('toss', $request, $key)) {
            Log::warning('[Toss] 웹훅 열쇠가 맞지 않는다', ['ip' => $request->ip()]);
            WebhookLogger::finish(
                WebhookLogger::inbound('toss', json_decode($rawBody, true)['eventType'] ?? null, $request, false),
                ok: false, status: 401, error: '인증 키 불일치');

            return response()->json(['message' => '인증 키가 일치하지 않습니다.'], 401);
        }

        // 서명 검증: 서명 헤더가 포함된 웹훅(payout/seller 등)에 한해, 보안키가 설정된 경우에만 수행.
        // 가상계좌 입금 웹훅은 서명이 없으므로 handleDepositWebhook 의 토스 API 재조회로 검증한다.
        if (config('toss.webhook_secret') && $signature !== ''
            && !$this->vaService->verifyWebhookSignature($rawBody, $signature, $txTime)) {
            Log::warning('[Toss] 웹훅 서명 불일치', ['sig' => substr($signature, 0, 24)]);

            /* 서명이 틀린 것도 남긴다 — 남의 것이 두드리고 있다는 뜻일 수 있다 */
            WebhookLogger::finish(
                WebhookLogger::inbound('toss', json_decode($rawBody, true)['eventType'] ?? null, $request, false),
                ok: false, status: 401, error: '서명 불일치');

            return response()->json(['message' => '서명 불일치'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            return response()->json(['message' => '잘못된 페이로드'], 400);
        }

        $event = $payload['eventType'] ?? 'UNKNOWN';

        Log::info('[Toss] 웹훅 수신', ['event' => $event]);

        /* 오간 것을 표에도 남긴다 (2026-09-10 지시). 여기서 나는 어떤 오류도
           본디 하려던 일을 방해하지 않는다 — WebhookLogger 안에서 삼킨다.
           서명은 위에서 이미 보았다: 헤더가 있고 열쇠가 있으면 맞는 것만 여기 온다. */
        $기록 = WebhookLogger::inbound('toss', $event, $request,
            $signature !== '' && config('toss.webhook_secret') ? true : null);

        /* 매입 상태가 실려 왔으면 그 자리에서 적는다 (2026-10-02 지시
           「30분마다 도는 것을 웹훅으로 처리 가능한가요」).

           토스 웹훅 본문의 `data.card.acquireStatus` 에 값이 들어 있다 — 승인 웹훅
           23건이 그랬다. 오는 것은 받아 적어 두면 그만큼 되묻지 않아도 된다.

           **다만 이것만으로는 매입 완료를 알 수 없다.** 토스 웹훅은 결제 상태
           (`status`)가 바뀔 때 우는데, 매입은 그 값을 바꾸지 않는다 — 승인도 DONE,
           매입 완료도 DONE 이다. 우리 기록으로 확인했다: 카드 결제 25건이 받은 웹훅이
           모두 1회씩 승인 그 순간뿐이고, 늦게 온 것이 하나도 없었다.

           그래서 결제 화면이 여는 김에 다시 묻는 길은 그대로 둔다. 토스가 뒤에 매입
           사건을 따로 보내 주게 되면, 그 본문에도 이 칸이 실릴 테니 여기서 저절로
           잡힌다 — 사건 이름을 가리지 않고 본문만 본다. */
        $this->매입상태적기($payload);

        /* 카드 결제는 결제창이 우리 화면으로 돌아오면서 마무리된다. 그런데 고객이
           그 화면을 닫거나 통신이 끊기면 돌아오지 않는다 — 돈은 나갔는데 우리는
           모르는 채로 남는다(테스트 시나리오 3.1).

           토스가 그때도 PAYMENT_STATUS_CHANGED 로 알려 준다. 여기서 받아 마무리한다.
           두 길이 같은 건을 두 번 마무리해도 탈이 없다 — 발행은 스스로 두 번 내지
           않고, 창고 확정도 이미 확정된 건에는 그렇다고 답한다. */
        if ($event === 'PAYMENT_STATUS_CHANGED') {
            $답 = $this->paymentStatusChanged($payload);
            WebhookLogger::finish($기록,
                ok: $답->getStatusCode() < 400,
                status: $답->getStatusCode(),
                response: $답->getData(true),
                ref: $payload['data']['orderId'] ?? null);

            return $답;
        }

        try {
            $tossPayment = $this->vaService->handleDepositWebhook($payload);

            $답 = ['ok' => true, 'payment_id' => $tossPayment?->id];

            /* 아무것도 하지 않고 지나간 까닭도 로그에 적는다 — 「성공」만 남으면
               무엇을 건너뛰었는지 알 수 없다. 남이 두드린 것으로 보이는 까닭
               하나만 실패로 세운다(2026-09-10 지시). */
            $까닭 = $tossPayment ? null : $this->vaService->건너뛴까닭;

            WebhookLogger::finish($기록,
                ok: $까닭 !== VirtualAccountService::수상함,
                status: 200,
                response: $답,
                error: $까닭,
                ref: $tossPayment?->order?->order_number ?? ($payload['data']['orderId'] ?? null));

            return response()->json($답);
        } catch (\Throwable $e) {
            Log::error('[Toss] 웹훅 처리 오류: ' . $e->getMessage(), ['payload' => $payload]);
            WebhookLogger::finish($기록, ok: false, status: 500, error: $e->getMessage());

            return response()->json(['message' => '처리 오류: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 다시 물어도 소용없는 실패인가 (2026-09-30).
     *
     * `TossClient` 는 `[코드] 메시지` 꼴로 던지고 예외 코드에 HTTP 상태를 담는다.
     * 없는 결제(404 · NOT_FOUND_PAYMENT)와 열쇠가 틀린 것(401)은 다시 보내도 같다.
     * 그 밖(연결 끊김ㆍ저쪽 5xx)은 잠깐 그런 것일 수 있으니 다시 받는다.
     */
    private function 영영없는결제인가(\Throwable $e): bool
    {
        $http = (int) $e->getCode();

        if ($http === 404 || $http === 401) {
            return true;
        }

        return (bool) preg_match('/\[(NOT_FOUND_PAYMENT|UNAUTHORIZED_KEY|INVALID_API_KEY)\]/', $e->getMessage());
    }

    /**
     * 결제 상태가 바뀌었다는 알림 — 카드 결제가 여기로 온다.
     *
     * 페이로드의 status 를 믿지 않는다. 서명이 없는 웹훅이라, paymentKey 로 토스에
     * 다시 물어 확인한 값으로만 움직인다(가상계좌 입금 웹훅과 같은 방식이다).
     */
    /**
     * 웹훅 본문에 실려 온 매입 상태를 적는다 — 없으면 아무것도 하지 않는다.
     *
     * 사건 이름을 가리지 않는다. 지금 오는 것은 `PAYMENT_STATUS_CHANGED` 뿐이지만,
     * 토스가 뒤에 다른 사건을 보내 주어도 본문에 이 칸이 있으면 그대로 잡힌다.
     *
     * 터지지 않는다 — 웹훅을 받는 본래 일을 이것 때문에 그르치면 안 된다.
     */
    private function 매입상태적기(array $payload): void
    {
        try {
            $값  = $payload['data']['card']['acquireStatus'] ?? null;
            $열쇠 = $payload['data']['paymentKey'] ?? null;

            if (! is_string($값) || $값 === '' || ! is_string($열쇠) || $열쇠 === '') {
                return;
            }

            if (! \Illuminate\Support\Facades\Schema::hasColumn('toss_payments', 'acquire_status')) {
                return;   // 칸이 아직 없다(배포 중) — 다음 웹훅에 적힌다
            }

            $줄 = \App\Models\TossPayment::where('payment_key', $열쇠)->first();

            if (! $줄) {
                return;
            }

            $줄->forceFill([
                'acquire_status'     => $값,
                'acquire_checked_at' => now(),
            ])->saveQuietly();

            Log::info('[Toss] 매입 상태를 웹훅에서 적었다', [
                'payment_key' => $열쇠, 'acquire' => $값,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Toss] 매입 상태를 웹훅에서 적지 못했다', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }
    }

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

            /* **영영 못 찾을 것은 200 으로 접는다** (2026-09-30 지시).

               토스는 2xx 가 아니면 다시 보낸다. 잠깐 안 되는 것(연결 끊김ㆍ5xx)은 다시
               받아야 하므로 500 이 맞다 — 그러라고 이 자리를 두었다.

               그런데 **없는 결제**는 몇 번을 물어도 없다. 그것까지 500 으로 돌려주면
               토스가 끝없이 다시 보낸다. 토스 개발자센터의 「웹훅 테스트 발송」이 바로
               그 꼴이라(가짜 paymentKey), 연동을 확인하려고 누른 단추가 무한 재시도로
               남는다(2026-09-30 운영 등록 뒤 확인). */
            if ($this->영영없는결제인가($e)) {
                return response()->json(['ok' => true, 'skipped' => '없는 결제 — 다시 보내지 마십시오']);
            }

            return response()->json(['message' => '재조회 실패'], 500);
        }

        $tp = \App\Models\TossPayment::where('payment_key', $key)->first();

        /* **결제 줄이 없으면 결제 링크로 세운다** (2026-10-01 지시 — 입금 웹훅과 같다).

           카드 건도 돌아오는 화면이 그 줄을 세운다. 그 요청이 끊기면 줄이 없는 채로
           승인 알림만 오고, 여기서 그냥 지나가 **돈은 받았는데 장부에 한 줄도 남지
           않는다.** 우리가 200 으로 답하므로 토스도 다시 보내지 않는다. */
        if (! $tp?->order) {
            $링크 = \App\Models\PaymentLink::query()
                ->where(fn ($q) => $q->where('payment_key', $key)
                                     ->orWhere('toss_order_id', $payload['data']['orderId'] ?? ''))
                ->with('order')->latest('id')->first();

            if ($링크?->order) {
                try {
                    $tp = $this->vaService->주문결제줄($링크, $res);
                    Log::warning('[Toss] 결제 줄이 없어 링크로 세웠다 — 돌아오는 화면이 끊긴 건이다', [
                        'link' => $링크->id, 'order' => $링크->order_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[Toss] 링크로 결제 줄을 세우지 못했다', [
                        'link' => $링크->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $tp?->order) {
            return response()->json(['ok' => true, 'skipped' => '이어진 주문 없음']);
        }

        /* 취소ㆍ부분취소를 받는다 (2026-09-16 지시).

           여태는 DONE 이 아니면 그냥 버렸다. 「되돌리는 일은 담당자의 손을 거쳐
           돈다」는 뜻이었는데, **담당자에게 알리는 자리가 없었다** — 환자는 돈을
           돌려받았는데 화면은 「결제완료」라 말하고, 다시 청구할 수도 없었다.

           되돌리는 판단은 여전히 담당자의 몫이다. 여기서는 토스가 준 사실만 옮겨
           적고 알린다(PaymentCancelSync). */
        if (in_array($res['status'] ?? '', ['CANCELED', 'PARTIAL_CANCELED'], true)) {
            $맞춤 = app(\App\Services\TossPayments\PaymentCancelSync::class)
                        ->맞추기($tp, $res, '카드 결제 취소 웹훅');

            return response()->json([
                'ok' => true, 'status' => $res['status'], 'synced' => $맞춤['changed'],
            ]);
        }

        // 나머지는 다 낸 것만 다룬다
        if (($res['status'] ?? '') !== 'DONE') {
            return response()->json(['ok' => true, 'status' => $res['status'] ?? null]);
        }

        /* 가상계좌는 여기가 아니라 입금 웹훅이 다룬다 — 승인(DONE)이 곧 입금은 아니다 */
        if ($tp->method === 'VIRTUAL_ACCOUNT') {
            return response()->json(['ok' => true, 'skipped' => '가상계좌는 입금 웹훅에서 처리합니다']);
        }

        if (! $tp->deposited_at) {
            $tp->update(['status' => 'DONE', 'deposited_at' => now(), 'raw_response' => $res]);
        }

        /* 돈이 들어온 날이 곧 모든 서류 발행일이다 — 거기서 급여 종료일과 다음 재구매
           가능일을 센다(2026-09-09 확정) */
        \App\Support\BenefitDates::onPaid($tp->order);

        try {
            app(\App\Services\DepositAutoIssue::class)->run($tp->order->refresh(), '토스 결제 웹훅');
        } catch (\Throwable $e) {
            /* 웹훅이 실패로 끝나면 토스가 다시 보낸다 — 발행에서 나는 오류가 그
               재시도를 부르지 않게 여기서 삼킨다. */
            Log::warning('[Toss] 카드 결제 뒤 자동 처리 실패', [
                'order' => $tp->order->order_number, 'error' => $e->getMessage(),
            ]);
        }

        /* 결제가 끝났음을 환자에게 알린다 (2026-09-18 운영 시험) — 한 건에 한 번이다 */
        try {
            app(\App\Services\PaymentDoneNotice::class)->send($tp->order->refresh());
        } catch (\Throwable $e) {
            Log::warning('[Toss] 결제 완료 안내 실패', [
                'order' => $tp->order->order_number, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'order_id' => $tp->order_id]);
    }
}
