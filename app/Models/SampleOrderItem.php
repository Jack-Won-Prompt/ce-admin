<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleOrderItem extends Model
{
    protected $fillable = [
        'sample_order_id', 'product_code', 'product_name',
        'quantity', 'unit_price', 'amount', 'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'integer',
        'amount'     => 'integer',
    ];

    public function sampleOrder(): BelongsTo
    {
        return $this->belongsTo(SampleOrder::class);
    }
}
