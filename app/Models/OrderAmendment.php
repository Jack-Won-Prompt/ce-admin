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
     * 고치기 전 값 — 주문 행이 아니라 **품목 줄**에서 읽는다.
     *
     * 화면은 ［주문 정정］ 한 번에 요청을 셋 보내고, 첫 요청(주문 등록 저장)이
     * OrderSync::ensure 로 주문의 **돈만** 먼저 맞춘다. 창고로 나간 주문은 품목
     * 줄ㆍ제품ㆍ수량을 건드리지 않으므로(OrderSync 주석 참고), 정정이 이 자리에
     * 닿을 때 주문 행은 이미 새 금액이고 품목 줄만 옛 것이다.
     *
     * 그래서 주문 행을 그대로 뜨면 수량은 50(옛것)인데 본인부담은 37,500(새것)인
     * 반쪽짜리 줄이 남는다 — 2026-09-23 무한 테스트 CASE 6 에서 그렇게 나왔다.
     * 결제 쪽이 진작 Order::결제기준금액() 으로 이 함정을 피해 가던 것과 같은
     * 이야기다.
     *
     * 품목 줄이 없는 건(옛 주문)은 주문 행으로 물러선다 — 그때는 주문 행이
     * 유일하게 남은 것이다.
     */
    public static function 이전값(Order $order): array
    {
        $줄 = $order->items;

        if ($줄->isEmpty()) {
            return [
                'product_code'  => $order->product_code,
                'product_name'  => $order->product_name,
                'quantity'      => (int) $order->quantity,
                'unit_price'    => (int) $order->unit_price,
                'patient_copay' => (int) $order->patient_copay,
                'nhis_amount'   => (int) $order->nhis_amount,
                'total_amount'  => (int) $order->total_amount,
            ];
        }

        $첫줄  = $줄->first();
        $본인  = (int) $줄->sum('patient_copay');

        return [
            'product_code'  => $첫줄->product_code,
            'product_name'  => $첫줄->product_name,
            'quantity'      => (int) $줄->sum('quantity'),
            'unit_price'    => (int) ($첫줄->insurance_price ?? $첫줄->product_price ?? 0),
            'patient_copay' => $본인,
            'nhis_amount'   => (int) $줄->sum('nhis_amount'),
            /* 받을 돈은 본인부담 그것뿐이다(배송비 없음, 2026-09-03 확정) */
            'total_amount'  => $본인,
        ];
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
        $전  = static::이전값($order);

        return static::create([
            'order_id'            => $order->id,
            'seq'                 => (int) $seq + 1,
            'product_code'        => $전['product_code'],
            'product_name'        => $전['product_name'],
            'quantity'            => $전['quantity'],
            'unit_price'          => $전['unit_price'],
            'patient_copay'       => $전['patient_copay'],
            'nhis_amount'         => $전['nhis_amount'],
            'total_amount'        => $전['total_amount'],
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
