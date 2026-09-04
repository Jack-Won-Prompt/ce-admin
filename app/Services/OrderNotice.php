<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * 창고가 주문을 움직이면 그 주문의 담당자에게 알린다 (2026-09-04 지시).
 *
 * 여태 주문 사건은 브로드캐스트 한 줄이 전부였다 — 그것도 admin 채널이라
 * 로그인한 모두에게 갔고, 채팅에 남기지 않아 화면을 보고 있지 않았으면
 * 그대로 사라졌다. 「창고가 언제 출고했지」를 나중에 찾을 자리가 없었다.
 *
 * 반품 쪽에는 이미 같은 일을 하는 ReturnNotice 가 있다. 같은 틀로 맞춘다 —
 * 토스트는 그 사람에게만, 그리고 채팅에 남겨 돌아와서 볼 수 있게.
 *
 * 담당자를 못 찾으면 예전처럼 admin 채널로 띄운다. 알릴 사람을 모른다고
 * 아무에게도 알리지 않으면, 담당자가 비어 있는 건은 영영 묻힌다.
 *
 * 알리지 못해도 부른 쪽은 성공이다 — 알리지 못한 것과 받지 못한 것은 다른 일이다.
 */
class OrderNotice
{
    /** 창고 소식이 쌓이는 방. 사람마다 하나다 — 반품과 같은 방을 쓴다. */
    public const ROOM_NAME = ReturnNotice::ROOM_NAME;

    /**
     * 창고가 움직였음을 담당자에게 알린다.
     *
     * @param string $what 「출고되었습니다」처럼 그 자리에서 읽을 한 마디
     */
    public function tellOwner(Order $order, string $what, string $tone = 'info'): void
    {
        $order->loadMissing(['patient', 'prescription']);

        $body = $order->order_number
            . ($order->patient?->name ? ' · ' . $order->patient->name : '')
            . ($order->withworks_tracking_no ? ' · ' . $order->withworks_tracking_no : '');

        $title = '창고 — ' . $what;
        $url   = route('orders.show', $order);
        $userId = $this->ownerId($order);

        /* 담당자를 모르면 예전 자리로 — 모두가 보는 채널에 띄우기만 한다.
           채팅은 남길 곳이 없다(사람마다 하나인 방이라 임자가 있어야 한다). */
        if (! $userId) {
            $this->broadcast(null, $title, $body, $url, $tone, $order);

            return;
        }

        $this->broadcast($userId, $title, $body, $url, $tone, $order);
        $this->leaveInChat($userId, $order, $what, $body);
    }

    /**
     * 이 주문은 누구의 것인가.
     *
     * 주문에 적힌 운영 담당자가 먼저다. 없으면 처방전에 배정된 사람 —
     * 창고로 넘긴 그 건을 실제로 쥐고 있는 사람이 거기 적힌다.
     */
    private function ownerId(Order $order): ?int
    {
        foreach ([$order->operation_user_id,
                  $order->prescription?->assigned_user_id] as $id) {
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private function broadcast(?int $userId, string $title, string $body,
                               string $url, string $tone, Order $order): void
    {
        try {
            broadcast(new WithworksStatusChanged(
                'order.inform', $title, $body, $url, $tone, $userId
            ));
        } catch (\Throwable $e) {
            Log::warning('[주문 알림] 알람 실패', [
                'order' => $order->order_number, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 돌아와서 볼 수 있게 채팅에 남긴다 */
    private function leaveInChat(int $userId, Order $order, string $what, string $body): void
    {
        /* 대괄호로 시작하지 않는다. 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag) — 그렇게 적으면 본문이
           통째로 사라지고 그 글이 보낸 사람 이름 자리에 선다. */
        $line = trim(self::ROOM_NAME . ' · ' . $body . ' — ' . $what);

        try {
            $room = $this->roomFor($userId, self::ROOM_NAME);

            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                // 사람이 보낸 것이 아니다. 화면은 이것을 「알림」으로 세운다.
                'user_id'      => null,
                'body'         => $line,
            ]);

            ChatMessage::attachToThread($message);

            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('[주문 알림] 채팅 알림 실패', [
                'order' => $order->order_number, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 그 사람의 「창고 알림」 방 — 없으면 만든다 */
    private function roomFor(int $userId, string $name): ChatRoom
    {
        $room = ChatRoom::where('type', 'group')
            ->where('name', $name)
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if ($room) {
            return $room;
        }

        $room = ChatRoom::create(['type' => 'group', 'name' => $name]);
        $room->users()->attach($userId);

        return $room;
    }
}
