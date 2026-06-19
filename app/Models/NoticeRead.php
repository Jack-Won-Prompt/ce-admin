<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['notice_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
