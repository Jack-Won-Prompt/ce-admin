<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ChatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'chat_room_id', 'user_id', 'body', 'reply_to_id', 'thread_root_id', 'thread_at',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'edited_at',
    ];

    protected $casts = [
        'thread_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    /** 답글이 가리키는 원본 */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function isImage(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }

    /**
     * 새 메시지의 묶음 정보를 정하고, 그 묶음 전체를 최근 위치로 끌어올린다.
     *
     * 답글이면 원본이 속한 묶음에 붙는다. 답글에 다시 답글을 달아도 묶음은 하나로 유지한다 —
     * 화면이 무한히 들여쓰기되지 않게 한 단계로 눕힌다.
     */
    public static function attachToThread(self $message): void
    {
        $root = $message->id;

        if ($message->reply_to_id) {
            $parent = static::withTrashed()->find($message->reply_to_id);
            if ($parent) {
                $root = $parent->thread_root_id ?: $parent->id;
            }
        }

        $message->forceFill([
            'thread_root_id' => $root,
            'thread_at'      => $message->created_at,
        ])->saveQuietly();

        // 묶음 전원이 같은 정렬 시각을 갖는다. 이래야 원본이 답글을 따라 내려간다.
        static::withTrashed()
            ->where('chat_room_id', $message->chat_room_id)
            ->where('thread_root_id', $root)
            ->update(['thread_at' => $message->created_at]);
    }

    /** 방의 메시지를 '묶음 최신순 → 묶음 안 시간순' 으로 정렬 */
    public function scopeThreadOrdered($query, string $direction = 'asc')
    {
        return $query
            ->orderBy(DB::raw('COALESCE(thread_at, created_at)'), $direction)
            ->orderBy(DB::raw('COALESCE(thread_root_id, id)'), $direction)
            ->orderBy('id', $direction);
    }
}
