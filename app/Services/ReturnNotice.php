<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\Log;

/**
 * 창고가 움직이면 접수자에게 알린다 (요청서 4쪽 Case 표, 2026-08-31).
 *
 * 절차서가 「접수자에게 inform」이라 적어 둔 자리다. 알람과 채팅을 **함께** 보낸다
 * (2026-08-31 회신) — 토스트는 보고 있을 때만 눈에 들고, 자리를 비운 사이에 지나간
 * 것은 아무 데도 남지 않는다. 채팅에 남겨야 돌아와서 볼 수 있다.
 *
 * 방은 사람마다 하나씩 둔다. 접수 건마다 방을 만들면 채팅 목록이 접수번호로 뒤덮여
 * 정작 사람과 나눈 이야기를 찾을 수 없다.
 *
 * 알리지 못해도 웹훅은 성공이다 — 알리지 못한 것과 받지 못한 것은 다른 일이다.
 */
class ReturnNotice
{
    /** 창고 소식이 쌓이는 방. 사람마다 하나다. */
    public const ROOM_NAME = '창고 알림';

    /**
     * 접수자에게 한 줄 남긴다.
     *
     * 접수자는 배정된 사람, 없으면 접수한 사람이다. 둘 다 없으면 보낼 곳이 없다 —
     * 그때는 화면 토스트만 뜨고 여기서는 아무 일도 하지 않는다.
     */
    public function tellTaker(OrderReturn $return, string $what): void
    {
        $userId = $return->assigned_user_id ?? $return->created_by;

        if (!$userId) {
            return;
        }

        /* 대괄호로 시작하지 않는다. 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag) — 그렇게 적으면 본문이
           통째로 사라지고 그 글이 보낸 사람 이름 자리에 선다. */
        $line = trim(sprintf(
            '창고 알림 · %s · %s%s — %s',
            $return->receipt_no,
            $return->typeLabel(),
            $return->order?->patient?->name ? ' · ' . $return->order->patient->name : '',
            $what
        ));

        try {
            $room = $this->roomFor($userId);

            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                // 사람이 보낸 것이 아니다. 화면은 이것을 「알림」으로 세운다.
                'user_id'      => null,
                'body'         => $line,
            ]);

            ChatMessage::attachToThread($message);

            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('[반품] 접수자 채팅 알림 실패', [
                'receipt' => $return->receipt_no, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 그 사람의 창고 알림 방 — 없으면 만든다.
     *
     * 이름만으로 찾으면 남의 방이 걸린다. 그 사람이 든 방 가운데 이 이름인 것을 찾는다.
     */
    private function roomFor(int $userId): ChatRoom
    {
        $room = ChatRoom::where('type', 'group')
            ->where('name', self::ROOM_NAME)
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if ($room) {
            return $room;
        }

        $room = ChatRoom::create(['type' => 'group', 'name' => self::ROOM_NAME]);
        $room->users()->attach($userId);

        return $room;
    }
}
