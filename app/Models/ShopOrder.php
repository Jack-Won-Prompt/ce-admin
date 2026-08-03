<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
    // shop_orders 는 create_shop_orders_table 마이그레이션이 기본 커넥션에 만든다.
    // 별도 lcpoint 커넥션을 보던 설정이 오히려 어긋나 있었고, DB 통합으로 그 커넥션도 없어졌다.

    protected $fillable = [
        'shop_order_id', 'order_number', 'customer_name', 'customer_phone', 'customer_company',
        'items', 'subtotal', 'discount_rate', 'discount_amount', 'shipping_fee', 'total_amount',
        'delivery_method', 'delivery_name', 'delivery_phone', 'delivery_zipcode',
        'delivery_address', 'delivery_note', 'buyer_note', 'status',
        'withworks_so_no', 'withworks_so_id', 'admin_memo',
    ];

    protected $casts = [
        'items'          => 'array',
        'subtotal'       => 'float',
        'discount_rate'  => 'float',
        'discount_amount'=> 'float',
        'shipping_fee'   => 'float',
        'total_amount'   => 'float',
    ];

    public function statusLabel(): string
    {
        return match($this->status) {
            'confirmed'  => '주문확인',
            'processing' => '처리중',
            'shipped'    => '배송중',
            'delivered'  => '배송완료',
            'cancelled'  => '취소',
            default      => $this->status,
        };
    }
}
