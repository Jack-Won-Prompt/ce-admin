<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryMessage extends Model
{
    protected $fillable = [
        'inquiry_id', 'user_id', 'body',
        'attachment_path', 'attachment_name', 'attachment_size', 'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
