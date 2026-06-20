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
        $response = $this->post('/v1/virtual-accounts', [
            'amount'       => $amount,
            'orderId'      => $orderId,
            'orderName'    => ($order->product_name ?? '처방조제') . ' 본인부담금',
            'customerName' => $order->patient?->name ?? '환자',
            'bank'         => $bank,
            'validHours'   => $validHours,
        ]);

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
    // 웹훅 처리 (VIRTUAL_ACCOUNT_DEPOSIT)
    // ─────────────────────────────────────────────────────────────

    /**
     * 입금 웹훅 처리
     *
     * 토스가 POST로 전송하는 payload 구조:
     * {
     *   "eventType": "VIRTUAL_ACCOUNT_DEPOSIT",
     *   "createdAt": "2024-01-01T12:00:00+09:00",
     *   "data": { "paymentKey": "...", "orderId": "...", "status": "DONE", ... }
     * }
     *
     * @return TossPayment|null 매칭된 결제 레코드
     */
    public function handleDepositWebhook(array $payload): ?TossPayment
    {
        $eventType = $payload['eventType'] ?? '';
        $data      = $payload['data']      ?? [];

        if ($eventType !== 'VIRTUAL_ACCOUNT_DEPOSIT') {
            Log::info('[Toss] 웹훅 무시 (이벤트 타입 불일치)', ['type' => $eventType]);
            return null;
        }

        $paymentKey = $data['paymentKey'] ?? null;
        $status     = $data['status']     ?? null;

        if (!$paymentKey) {
            Log::warning('[Toss] 웹훅 paymentKey 없음', $payload);
            return null;
        }

        $tossPayment = TossPayment::where('payment_key', $paymentKey)->first();

        if (!$tossPayment) {
            Log::warning('[Toss] 웹훅 매칭 실패 — paymentKey 없음', ['key' => $paymentKey]);
            return null;
        }

        $tossPayment->update([
            'status'       => $status,
            'raw_response' => array_merge($tossPayment->raw_response ?? [], ['webhook_data' => $data]),
            'deposited_at' => $status === 'DONE' ? now() : null,
        ]);

        Log::info('[Toss] 입금 웹훅 처리 완료', [
            'payment_key' => $paymentKey,
            'order_id'    => $tossPayment->order_id,
            'status'      => $status,
        ]);

        return $tossPayment->fresh();
    }
}
