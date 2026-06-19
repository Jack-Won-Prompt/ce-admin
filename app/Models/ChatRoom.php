<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $fillable = ['name', 'type', 'shop_user_name', 'shop_user_phone', 'shop_user_email'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_users')
                    ->withPivot('last_read_at')
                    ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /** 1:1 채팅방 상대방 이름 반환 */
    public function displayName(int $myUserId): string
    {
        if ($this->type === 'group') {
            if ($this->shop_user_name) {
                return 'CE샵 · ' . $this->shop_user_name;
            }
            return $this->name ?? '그룹 채팅';
        }
        $other = $this->users->firstWhere('id', '!=', $myUserId);
        return $other?->name ?? '알 수 없음';
    }

    /** 읽지 않은 메시지 수 */
    public function unreadCount(int $userId): int
    {
        $pivot = $this->users->firstWhere('id', $userId)?->pivot;
        $lastRead = $pivot?->last_read_at;

        return $this->messages()
                    ->where(fn($q) => $q->where('user_id', '!=', $userId)->orWhereNull('user_id'))
                    ->when($lastRead, fn($q) => $q->where('created_at', '>', $lastRead))
                    ->count();
    }
}
