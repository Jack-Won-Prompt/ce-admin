<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** 어느 Lot 으로 나갔는가 — 창고가 출고 확정 때 알려 준다 */
    public function lots(): HasMany
    {
        return $this->hasMany(OrderItemLot::class);
    }

    /**
     * 한 칸에 적을 Lot — 목록의 좁은 칸에 선다.
     *
     * 둘로 나뉘어 나갔으면 둘 다 적는다. 하나만 적으면 나머지 물건의 유효기간을
     * 영영 알 수 없다.
     */
    public function getLotSummaryAttribute(): string
    {
        return $this->lots->pluck('lot_no')->filter()->implode(', ');
    }

    /** Lot 과 짝이 되는 유효기간. 차례가 위와 같아야 짝이 읽힌다. */
    public function getExpirySummaryAttribute(): string
    {
        return $this->lots
            ->map(fn ($l) => $l->expiry_date?->format('Y-m-d'))
            ->filter()->implode(', ');
    }
}
