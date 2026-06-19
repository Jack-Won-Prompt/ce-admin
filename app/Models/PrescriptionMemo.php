<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionMemo extends Model
{
    protected $fillable = ['prescription_id', 'user_id', 'content', 'is_pinned', 'pin_x', 'pin_y'];

    protected $casts = [
        'is_pinned' => 'boolean',
        'pin_x'     => 'float',
        'pin_y'     => 'float',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
