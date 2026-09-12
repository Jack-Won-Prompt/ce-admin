<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 앱으로 보낸 알림 한 건.
 *
 * 푸시는 폰 알림창에만 떠서 지우면 사라진다. 그래서 보낸 것을 여기 남겨
 * 앱에서 다시 볼 수 있게 한다(GET /api/notifications).
 */
class FcmNotification extends Model
{
    protected $fillable = [
        'user_id', 'title', 'body', 'type', 'payload', 'sent', 'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent'    => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 이 알림이 가리키는 화면의 키.
     *
     * 앱이 이 값으로 화면을 잇는다. 갈래마다 키 이름이 달라 여기서 한 가지 이름으로
     * 모아 준다 — 앱이 갈래별 키 이름을 다 알고 있어야 할 까닭이 없다.
     */
    public function getTargetKeyAttribute(): ?string
    {
        $p = $this->payload ?? [];

        return match ($this->type) {
            'chat'        => isset($p['room_id']) ? (string) $p['room_id'] : null,
            'rx_reupload' => isset($p['rx_number']) ? (string) $p['rx_number'] : null,
            default       => null,
        };
    }
}
