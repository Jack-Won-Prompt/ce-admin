<?php
// app/Services/TossPayments/VirtualAccountService.php
// 토스페이먼츠 가상계좌 발급 및 입금 확인

namespace App\Services\TossPayments;

use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Support\Facades\Log;

class VirtualAccountService extends TossClient
{
    // ─────────────────────────────────────────────────────────────
    // 가상계좌 발급
    // ─────────────────────────────────────────────────────────────

    /**
     * 주문에 대한 가상계좌 발급
     *
     * @return TossPayment  저장된 결제 레코드
     */
    public function issueVirtualAccount(Order $order): TossPayment
    {
        $bank       = config('toss.virtual_account.bank', 'IBK');
        $validHours = (int) config('toss.virtual_account.valid_hours', 72);
        $dueDate    = now()->addHours($validHours)->format('Y-m-d\TH:i:s');
        $orderId    = 'CE-' . $order->order_number . '-' . now()->format('YmdHis');
        $amount     = (int) round($order->patient_copay);

        if ($amount <= 0) {
            throw new TossApiException('본인부담금이 0원인 주문에는 가상계좌를 발급할 수 없습니다.');
        }

        // 주의: 토스 API는 validHours / dueDate 중 하나만 허용한다.
        // (둘 다 보내면 INVALID_VALID_HOURS_WITH_DUE_DATE_AND_SINGLE 400 발생)
        try {
        $response = $this->post('/v1/virtual-accounts', [
            'amount'       => $amount,
            'orderId'      => $orderId,
            'orderName'    => ($order->product_name ?? '처방조제') . ' 본인부담금',
            'customerName' => $order->patient?->name ?? '환자',
            'bank'         => $bank,
            'validHours'   => $validHours,
        ]);
        } catch (TossApiException $e) {
            /* 시험 상점이 가상계좌를 열어 두지 않은 일이 있다
               (NOT_SUPPORTED_METHOD). 그러면 시험이 거기서 막혀 받는 쪽 화면·문자·
               입금 확인을 한 번도 밟지 못한다.

               **시험 모드에서만** 임의 계좌를 세워 그다음 걸음을 밟게 한다.
               운영 키에서는 그대로 되돌린다 — 진짜 돈을 받는 계좌를
               우리가 지어내면 그 돈은 어디로도 가지 않는다. */
            if (!$this->testMode) {
                throw $e;
            }

            Log::warning('[Toss][시험] 가상계좌를 임의로 세움', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return $this->mockVirtualAccount($order, $orderId, $amount, $bank, $dueDate, $e->getMessage());
        }

        return TossPayment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_key'    => $response['paymentKey'],
                'toss_order_id'  => $orderId,
                'method'         => 'VIRTUAL_ACCOUNT',
                'status'         => $response['status'],
                'amount'         => $amount,
                'bank'           => $response['virtualAccount']['bank']          ?? $bank,
                'account_number' => $response['virtualAccount']['accountNumber'] ?? '',
                'customer_name'  => $response['virtualAccount']['customerName']  ?? '',
                'due_date'       => $response['virtualAccount']['dueDate']       ?? $dueDate,
                'raw_response'   => $response,
            ]
        );
    }

    /**
     * 임의 가상계좌 — 시험 모드 전용.
     *
     * 번호는 진짜 계좌와 갈리도록 머리에 9 를 붙인다. 결제키도
     * SIMVA- 로 뗄어 토스에 물어볼 것과 섞이지 않게 한다 — 섞이면
     * 입금 확인이 토스로 돌아가 NOT_FOUND_PAYMENT 로 죽는다.
     */
    private function mockVirtualAccount(
        Order $order, string $orderId, int $amount, string $bank, string $dueDate, string $why
    ): TossPayment {
        $account = '9' . str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);

        return TossPayment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_key'    => 'SIMVA-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
                'toss_order_id'  => $orderId,
                'method'         => 'VIRTUAL_ACCOUNT',
                'status'         => 'WAITING_FOR_DEPOSIT',
                'amount'         => $amount,
                'bank'           => $bank,
                'account_number' => $account,
                'customer_name'  => $order->patient?->name ?? '환자',
                'due_date'       => $dueDate,
                'raw_response'   => [
                    'simulated' => true,
                    'reason'    => $why,
                    'note'      => '테스트 상점이 가상계좌를 지원하지 않아 임의 생성한 계좌입니다. '
                                . '입금 확인은 손으로 합니다.',
                ],
            ]
        );
    }

    /** 임의로 세운 계좌인가 — 토스에 물어봐야 소용없는 건이다 */
    public static function isSimulated(?TossPayment $p): bool
    {
        return $p && str_starts_with((string) $p->payment_key, 'SIMVA-');
    }

    // ─────────────────────────────────────────────────────────────
    // 결제 조회
    // ─────────────────────────────────────────────────────────────

    /** paymentKey로 결제 정보 조회 후 DB 갱신 */
    public function fetchByPaymentKey(string $paymentKey): array
    {
        $data = $this->get('/v1/payments/' . urlencode($paymentKey));

        TossPayment::where('payment_key', $paymentKey)->update([
            'status'       => $data['status'],
            'raw_response' => $data,
            'deposited_at' => $data['status'] === 'DONE' ? now() : null,
        ]);

        return $data;
    }

    /** 토스 orderId로 결제 정보 조회 */
    public function fetchByOrderId(string $tossOrderId): array
    {
        return $this->get('/v1/payments/orders/' . urlencode($tossOrderId));
    }

    // ─────────────────────────────────────────────────────────────
    // 웹훅 처리 — 가상계좌 입금
    // ─────────────────────────────────────────────────────────────

    /** 가상계좌 입금을 알리는 이벤트 이름 (2026-09-10 확인) */
    private const 입금이벤트 = ['DEPOSIT_CALLBACK', 'VIRTUAL_ACCOUNT_DEPOSIT'];

    /**
     * 입금 웹훅 처리
     *
     * **이름이 둘이다.** 토스 상점관리자의 웹훅 등록 화면에 있는 이름은
     * `DEPOSIT_CALLBACK` 이다. 코드는 여태 `VIRTUAL_ACCOUNT_DEPOSIT` 만 받고 있어,
     * 등록해 두었더라도 오는 족족 버렸을 것이다(2026-09-10 확인). 둘 다 받는다.
     *
     * **본문에 paymentKey 가 없다.** DEPOSIT_CALLBACK 은 orderIdㆍstatusㆍsecret 만
     * 실어 보낸다. 그래서 우리가 매긴 주문 번호(toss_order_id)로 건을 찾고,
     * 가상계좌를 만들 때 받아 둔 secret 과 맞춰 본다.
     *
     * {
     *   "eventType": "DEPOSIT_CALLBACK",
     *   "createdAt": "2026-09-10T12:00:00+09:00",
     *   "data": { "orderId": "...", "status": "DONE", "secret": "...", "transactionKey": "..." }
     * }
     *
     * @return TossPayment|null 매칭된 결제 레코드
     */
    public function handleDepositWebhook(array $payload): ?TossPayment
    {
        $eventType = $payload['eventType'] ?? '';

        /* 본문이 data 로 한 겹 싸여 오기도 하고 그대로 오기도 한다 — 둘 다 받는다 */
        $data = $payload['data'] ?? $payload;

        if (! in_array($eventType, self::입금이벤트, true)) {
            Log::info('[Toss] 웹훅 무시 (이벤트 타입 불일치)', ['type' => $eventType]);
            return null;
        }

        $paymentKey  = $data['paymentKey'] ?? null;
        $tossOrderId = $data['orderId']    ?? null;

        $tossPayment = $paymentKey
            ? TossPayment::where('payment_key', $paymentKey)->first()
            : null;

        /* paymentKey 가 없으면 우리가 매긴 주문 번호로 찾는다 — DEPOSIT_CALLBACK 의 길이다 */
        if (! $tossPayment && $tossOrderId) {
            $tossPayment = TossPayment::where('toss_order_id', $tossOrderId)->latest('id')->first();
        }

        if (! $tossPayment) {
            Log::warning('[Toss] 웹훅 매칭 실패 — 이어진 결제가 없다', [
                'key' => $paymentKey, 'order' => $tossOrderId,
            ]);
            return null;
        }

        /* 가상계좌를 만들 때 받아 둔 secret 과 맞춰 본다.
           틀리면 남이 두드린 것이다 — 그 자리에서 멈춘다. 받아 둔 것이 없는 옛 건은
           맞춰 볼 것이 없어 지나가되, 아래 재조회가 다시 한 번 걸러 준다. */
        $받아둔비밀 = $tossPayment->raw_response['secret'] ?? null;
        $온비밀     = $data['secret'] ?? null;

        if ($받아둔비밀 && $온비밀 && ! hash_equals((string) $받아둔비밀, (string) $온비밀)) {
            Log::warning('[Toss] 입금 웹훅 secret 불일치 — 처리하지 않는다', [
                'order' => $tossOrderId, 'payment_id' => $tossPayment->id,
            ]);
            return null;
        }

        $paymentKey = $tossPayment->payment_key;

        // 보안: 가상계좌 입금 웹훅에는 서명이 없으므로 페이로드의 status 를 신뢰하지 않는다.
        // paymentKey 로 토스 API 를 재조회해 실제 결제 상태로만 갱신한다. (위조 웹훅 방어)
        try {
            $verified = $this->fetchByPaymentKey($paymentKey);  // API 조회 + DB 갱신
        } catch (TossApiException $e) {
            Log::error('[Toss] 입금 웹훅 API 재검증 실패 — 상태 미변경', [
                'payment_key' => $paymentKey,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }

        $tossPayment->refresh();

        Log::info('[Toss] 입금 웹훅 처리 완료 (API 재검증)', [
            'payment_key' => $paymentKey,
            'order_id'    => $tossPayment->order_id,
            'status'      => $verified['status'] ?? null,
        ]);

        /* 입금이 취소된 알림도 온다(status=CANCELED). 되돌리는 일은 담당자의 손을
           거쳐 돌므로 여기서 건드리지 않되, 조용히 지나가지도 않는다 — 돈이 들어온
           줄 알고 이미 서류가 나갔을 수 있다. */
        if (($verified['status'] ?? '') === 'CANCELED') {
            Log::warning('[Toss] 가상계좌 입금이 취소되었습니다 — 담당자 확인이 필요합니다', [
                'order_id'    => $tossPayment->order_id,
                'order_no'    => $tossPayment->order?->order_number,
                'payment_key' => $paymentKey,
            ]);
        }

        /* 돈이 들어왔으면 청구전략이 정한 세무 서류를 낸다.
           담당자가 통장을 보고 세운 것과 같은 일이다 — 부르는 곳만 다르다.
           웹훅이 실패로 끝나면 토스가 다시 보내므로, 발행에서 나는 오류가 그 재시도를
           부르지 않게 여기서 삼킨다(자동 발행은 스스로 두 번 내지 않는다). */
        if ($tossPayment->is_done && $tossPayment->order) {
            /* 돈이 들어온 날이 곧 모든 서류 발행일이다 — 거기서 급여 종료일과 다음 재구매
               가능일을 센다(2026-09-09 확정). 세무 서류보다 먼저 세운다: 서류에 그 날짜가
               실린다. */
            \App\Support\BenefitDates::onPaid($tossPayment->order);

            try {
                app(\App\Services\DepositAutoIssue::class)->run($tossPayment->order, '토스 웹훅');
            } catch (\Throwable $e) {
                Log::warning('[Toss] 입금 후 자동 발행 실패', [
                    'order_id' => $tossPayment->order_id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $tossPayment;
    }
}
