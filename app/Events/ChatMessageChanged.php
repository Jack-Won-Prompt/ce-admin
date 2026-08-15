<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 이미 보낸 메시지가 고쳐지거나 지워졌을 때.
 *
 * 새 메시지(ChatMessageSent)와 갈라 둔다 — 받는 쪽에서 할 일이 다르다. 새 메시지는 아래에 붙이고
 * 토스트를 띄우지만, 이건 이미 그려진 말풍선을 제자리에서 바꿔치기할 뿐이라 알림을 띄우지 않는다.
 */
class ChatMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param 'edited'|'deleted' $action */
    public function __construct(
        public ChatMessage $message,
        public string $action,
        public ?string $body = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->message->chat_room_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->message->id,
            'room_id'   => $this->message->chat_room_id,
            'user_id'   => $this->message->user_id,
            'action'    => $this->action,
            'body'      => $this->action === 'edited' ? $this->body : null,
            'edited_at' => $this->message->edited_at?->format('Y-m-d H:i:s'),
        ];
    }
}
