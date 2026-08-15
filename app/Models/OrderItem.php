<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 주문 한 줄.
 *
 * 처방(PrescriptionItem)과 따로 둔다 — 처방한 것과 실제로 주문한 것이 늘 같지는 않다.
 * 공단에 내는 구매내역 서류는 이쪽을 근거로 한다.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_name', 'product_code', 'quantity',
        'product_price', 'insurance_price', 'nhis_amount', 'patient_copay', 'sort_order',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'product_price'   => 'float',
        'insurance_price' => 'float',
        'nhis_amount'     => 'float',
        'patient_copay'   => 'float',
        'sort_order'      => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** 단가 — 보험가가 있으면 그것이 실제 청구 기준이다 */
    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->insurance_price ?? $this->product_price ?? 0);
    }
}
