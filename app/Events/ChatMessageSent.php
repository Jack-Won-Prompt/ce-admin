<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private const SCREEN_TAG_PATTERN = '/(?:^|\R)\[[^\]\r\n]+\]\s*(.+)$/u';

    public function __construct(public ChatMessage $message)
    {
        $this->message->load('user', 'room');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->message->chat_room_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $msg = $this->message;
        return [
            'id'              => $msg->id,
            'room_id'         => $msg->chat_room_id,
            'user_id'         => $msg->user_id,
            'user_name'       => $msg->user->name,
            'screen_name'     => $this->resolveSenderScreenName(),
            'body'            => $this->stripScreenTag($msg->body),
            'attachment_path' => $msg->attachment_path,
            'attachment_name' => $msg->attachment_name,
            'attachment_mime' => $msg->attachment_mime,
            'attachment_size' => $msg->attachment_size,
            'is_image'        => $msg->isImage(),
            'created_at'      => $msg->created_at->format('Y-m-d H:i:s'),
            'time_label'      => $msg->created_at->format('H:i'),
        ];
    }

    private function resolveSenderScreenName(): ?string
    {
        $companyRoles = ['admin', 'manager', 'super_admin', 'operations_admin', 'company_admin', 'approver'];
        $senderRole = $this->message->user?->role;

        if ($senderRole && in_array($senderRole, $companyRoles, true)) {
            return null;
        }

        $room = $this->message->room;

        if ($this->message->user && $room?->shop_user_name && $this->message->user->name === $room->shop_user_name) {
            return null;
        }

        return $this->extractScreenName($this->message->body);
    }

    private function extractScreenName(?string $body): ?string
    {
        if (blank($body)) {
            return null;
        }

        if (! preg_match(self::SCREEN_TAG_PATTERN, $body, $matches)) {
            return null;
        }

        $screenName = trim((string) ($matches[1] ?? ''));

        return $screenName !== '' ? $screenName : null;
    }

    private function stripScreenTag(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        return rtrim((string) preg_replace(self::SCREEN_TAG_PATTERN, '', $body));
    }
}
