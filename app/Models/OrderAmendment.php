<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 주문 정정 이력 한 줄 — 「고치기 전」이 담긴다 (2026-09-22 확인요청 2ㆍ4쪽).
 *
 * 정정은 주문을 제자리에서 고치므로, 이 줄이 없으면 어느 화면에도 정정 전 값이
 * 남지 않는다. 남기는 일은 한 곳에서 한다 — OrderAmendment::뜨기().
 */
class OrderAmendment extends Model
{
    protected $fillable = [
        'order_id', 'seq',
        'product_code', 'product_name', 'quantity', 'unit_price',
        'patient_copay', 'nhis_amount', 'total_amount',
        'withworks_so_no',
        'cash_receipt_no', 'cash_receipt_amount', 'tax_invoice_no',
        'reason', 'amended_at', 'amended_by',
    ];

    protected $casts = [
        'quantity'            => 'integer',
        'unit_price'          => 'integer',
        'patient_copay'       => 'integer',
        'nhis_amount'         => 'integer',
        'total_amount'        => 'integer',
        'cash_receipt_amount' => 'integer',
        'amended_at'          => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 고치기 전 주문을 그대로 뜬다.
     *
     * **주문을 고치기 전에** 불러야 한다 — 고친 뒤에 부르면 새 값이 담긴다.
     *
     * 차례(seq)는 이미 쌓인 줄 수로 센다. 같은 주문을 거듭 정정하면 1, 2, 3 으로
     * 이어진다.
     *
     * @param  string|null  $reason  무엇을 고쳤는지 — 화면이 적어 두는 말
     */
    public static function 뜨기(Order $order, ?string $reason = null): self
    {
        $seq = static::where('order_id', $order->id)->max('seq');

        return static::create([
            'order_id'            => $order->id,
            'seq'                 => (int) $seq + 1,
            'product_code'        => $order->product_code,
            'product_name'        => $order->product_name,
            'quantity'            => (int) $order->quantity,
            'unit_price'          => (int) $order->unit_price,
            'patient_copay'       => (int) $order->patient_copay,
            'nhis_amount'         => (int) $order->nhis_amount,
            'total_amount'        => (int) $order->total_amount,
            'withworks_so_no'     => $order->withworks_so_no,
            /* 그때 살아 있던 증빙만 적는다 — 이미 취소된 것은 이번 정정이 무른 것이
               아니다. 취소된 번호까지 적으면 현금영수증 화면이 같은 취소를 두 번 센다. */
            'cash_receipt_no'     => $order->cash_receipt_status === 'issued' ? $order->cash_receipt_no : null,
            'cash_receipt_amount' => $order->cash_receipt_status === 'issued' ? (int) $order->cash_receipt_amount : 0,
            'tax_invoice_no'      => $order->tax_invoice_status === 'issued' ? $order->tax_invoice_no : null,
            'reason'              => $reason ? mb_substr($reason, 0, 255) : null,
            'amended_at'          => now(),
            'amended_by'          => \Illuminate\Support\Facades\Auth::id(),
        ]);
    }
}
