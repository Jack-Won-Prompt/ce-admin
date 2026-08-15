<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Withworks 가 알려 온 물류 사건 하나 */
class WithworksEvent extends Model
{
    protected $fillable = [
        'event_id', 'event', 'ce_order_number', 'so_no', 'order_id',
        'status', 'status_label', 'payload', 'occurred_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
