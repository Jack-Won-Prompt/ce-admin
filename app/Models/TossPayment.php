<?php
// app/Models/TossPayment.php

namespace App\Models;

use App\Services\TossPayments\TossClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TossPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'payment_key', 'toss_order_id', 'method',
        'status', 'amount', 'bank', 'account_number', 'customer_name',
        'due_date', 'deposited_at', 'raw_response',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'deposited_at' => 'datetime',
        'raw_response' => 'array',
        'amount'       => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** 상태 한글 레이블 */
    public function getStatusLabelAttribute(): string
    {
        return TossClient::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    /** 상태 배지 클래스 */
    public function getStatusBadgeAttribute(): string
    {
        return TossClient::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    /** 은행명 */
    public function getBankNameAttribute(): string
    {
        return TossClient::BANK_NAMES[$this->bank] ?? ($this->bank ?? '-');
    }

    /** 가상계좌 만료 여부 */
    public function getIsExpiredAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'DONE';
    }

    /** 입금 완료 여부 */
    public function getIsDoneAttribute(): bool
    {
        return $this->status === 'DONE';
    }
}
