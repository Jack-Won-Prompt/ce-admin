<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 교환·반품·취소가 한 단계 움직인 기록 */
class OrderReturnLog extends Model
{
    protected $fillable = ['order_return_id', 'from_status', 'to_status', 'reason', 'created_by'];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
