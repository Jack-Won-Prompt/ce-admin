<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_room_id', 'user_id', 'body',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function isImage(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }
}
