<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 주문 한 줄이 어느 Lot 으로 나갔는가.
 *
 * 창고가 출고를 확정할 때 알려 준다(so.shipped 웹훅). 우리가 정하는 값이 아니라
 * 받아 적는 값이라, 화면에서는 고칠 수 없다.
 *
 * 같은 제품이 두 Lot 으로 나뉘어 나가면 줄이 둘이 된다 — 유효기간이 Lot 마다 다르므로
 * 한 줄에 담을 수 없다.
 */
class OrderItemLot extends Model
{
    protected $fillable = ['order_item_id', 'lot_no', 'expiry_date', 'quantity'];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity'    => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
