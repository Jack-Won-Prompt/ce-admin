<?php
// app/Support/OrderSync.php
// 처방전에 딸린 주문 줄을 처방전과 나란히 둔다.

namespace App\Support;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;

/**
 * 저장하면 주문 관리에도 선다.
 *
 * 처방전 그림이 없어도, 제품을 아직 안 골랐어도 그렇다 — 주문 등록에서 저장한 건은
 * 곧 하나의 거래이고, 그것을 보는 자리가 주문 관리다. 예전에는 「주문 생성 및 연계」를
 * 눌러야만 줄이 생겨, 상담만 받아 적어 둔 건은 어느 목록에도 없이 떠 있었다.
 *
 * 우리 쪽 줄만 세운다. 위드웍스로 보내는 것은 그 단추가 할 일이다 — 저장할 때마다
 * 창고로 주문이 날아가서는 안 된다.
 *
 * 주문 등록의 저장과 위임동의 서명이 함께 쓴다.
 */
class OrderSync
{
    public static function ensure(Prescription $prescription): ?Order
    {
        $order = $prescription->order()->first();

        /* 이미 창고로 보낸 주문은 손대지 않는다. 보낸 뒤에 고치는 일은 「주문 수정」이
           창고와 함께 해야 하는 일이라, 여기서 조용히 바꾸면 두 쪽이 어긋난다. */
        if ($order && $order->withworks_so_no) {
            return null;
        }

        $items = $prescription->items;
        $first = $items->first();
        $copay = (float) $items->sum('patient_copay');
        $qty   = (int) $items->sum('quantity');

        $summary = [
            'patient_id'    => $prescription->patient_id,
            // 제품명은 비울 수 없는 칸이다. 아직 고른 것이 없으면 「-」로 둔다.
            'product_name'  => $first?->product_name ?: ($prescription->product_name ?: '-'),
            'product_code'  => $first?->product_code ?: $prescription->product_code,
            'quantity'      => $qty ?: 1,
            'unit_price'    => (float) ($first?->insurance_price ?? $first?->product_price ?? 0),
            'nhis_amount'   => (float) $items->sum('nhis_amount'),
            'patient_copay' => $copay,
            'total_amount'  => $copay + (float) ($order?->shipping_fee ?? 0),
        ];

        if ($order) {
            /* 아직 보내지 않은 줄은 저장할 때마다 처방전을 따라간다 — 제품을 고쳐 놓고
               주문 관리에서는 옛 제품이 보이는 일이 없어야 한다. 배송지ㆍ판매유형은
               건드리지 않는다. 그것은 주문 연계 탭에서 적는 값이다. */
            $order->update($summary);
        } else {
            $order = Order::create($summary + [
                'order_number'    => Order::generateOrderNumber(),
                'prescription_id' => $prescription->id,
                'created_by'      => Auth::id(),
                // 배송비는 받지 않기로 했다(2026-08-24)
                'shipping_fee'    => 0,
                'status'          => 'pending',
                'so_type'         => '1013',
            ]);
            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("주문 {$order->order_number} 자동 생성 (처방전 {$prescription->rx_number} 저장)");
        }

        // 품목 줄도 처방전을 따라간다
        $order->items()->delete();
        foreach ($items->values() as $i => $item) {
            $order->items()->create([
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
                'sort_order'      => $i,
            ]);
        }

        return $order;
    }
}
