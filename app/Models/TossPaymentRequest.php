<?php
// app/Models/TossPaymentRequest.php
// 토스 결제창에 실어 보낸 값 — 보낸 그때 적는다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 결제창을 연 한 번의 시도.
 *
 * 토스가 승인 응답에 돌려주지 않는 값(구매자명ㆍ주문이름)을 우리 쪽에 남겨 둔다.
 * 승인까지 가지 않은 시도도 남는다 — 무엇을 보냈는지는 결제가 되든 안 되든 같다.
 */
class TossPaymentRequest extends Model
{
    protected $fillable = [
        'payment_link_id', 'order_id', 'toss_order_id', 'method', 'amount',
        'customer_name', 'order_name', 'ip', 'user_agent', 'requested_at',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'requested_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    /** 승인된 결제 줄 — 토스 주문번호로 잇는다 */
    public function tossPayment(): BelongsTo
    {
        return $this->belongsTo(TossPayment::class, 'toss_order_id', 'toss_order_id');
    }

    /** 가상계좌면 이 이름이 예금주, 그 밖이면 구매자명이다 */
    public function 이름의뜻(): string
    {
        return \App\Support\PayNames::가상계좌인가($this->method) ? '예금주' : '구매자명';
    }
}
