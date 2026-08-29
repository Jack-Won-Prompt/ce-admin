<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 되돌리는 품목 한 줄.
 *
 * 부분 취소를 하려면 줄이 있어야 한다. 두 품목 가운데 하나만, 열 개 가운데 셋만
 * 되돌리는 일이 잦은데 제품명 한 칸과 수량 한 칸으로는 담을 수 없었다.
 *
 * 원 주문 수량을 함께 적어 둔다 — 나중에 원 주문이 고쳐져도 「그때 무엇의 몇 개였는지」가
 * 남아야 부분인지 전부인지 다시 따질 수 있다.
 */
class OrderReturnItem extends Model
{
    protected $fillable = [
        'order_return_id', 'order_item_id',
        'product_code', 'product_name',
        'ordered_quantity', 'quantity', 'unit_price', 'copay',
    ];

    protected $casts = [
        'ordered_quantity' => 'integer',
        'quantity'         => 'integer',
        'unit_price'       => 'integer',
        'copay'            => 'integer',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    /** 원 주문보다 적게 되돌리는가 */
    public function isPartial(): bool
    {
        return $this->ordered_quantity > 0 && $this->quantity < $this->ordered_quantity;
    }

    /**
     * 되돌리는 금액 — 마이너스 발행은 환자가 낸 돈을 기준으로 한다.
     *
     * copay 는 원 주문 줄의 환자부담 「합계」다(주문 저장이 줄마다의 값을 더해 주문
     * 총액으로 쓴다). 부분이면 되돌리는 수량만큼만 떼어 낸다.
     */
    public function refundAmount(): int
    {
        if ($this->ordered_quantity <= 0) {
            return $this->copay;
        }

        return (int) round($this->copay * $this->quantity / $this->ordered_quantity);
    }
}
