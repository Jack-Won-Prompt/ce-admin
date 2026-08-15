<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 지자체 청구 등기 발송 한 건 */
class LocalClaimDispatch extends Model
{
    protected $fillable = [
        'order_id', 'local_gov', 'registered_no', 'sent_date',
        'receipt_path', 'receipt_name', 'memo', 'created_by',
    ];

    protected $casts = ['sent_date' => 'date'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
