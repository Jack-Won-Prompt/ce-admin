<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 한 입금을 환자별로 나눈 몫 (요청서 5쪽).
 *
 * 지자체는 여러 환자 건을 통으로 보낸다. 원본 줄은 은행이 준 그대로 두고 여기서 나눈다 —
 * 원본을 쪼개면 통장과 맞춰 볼 수 없게 된다.
 */
class BankTransactionSplit extends Model
{
    protected $fillable = [
        'bank_transaction_id', 'order_id', 'patient_id', 'amount',
        'memo', 'staff_memo', 'created_by',
    ];

    protected $casts = ['amount' => 'integer'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
