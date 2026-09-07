<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 담당자로 배정되었음을 그 사람에게 알린다 (2026-09-07 지시).
 *
 * 배정은 남이 나에게 일을 넘기는 걸음이다. 넘긴 쪽은 넘긴 줄 알지만 받는 쪽은
 * 목록을 새로 열어 보기 전에는 모른다 — 그 사이가 곧 놀고 있는 시간이다.
 *
 * 창고 알림ㆍ승인 요청과 같은 틀을 쓴다(ReturnNotice·OrderNotice). 토스트는
 * 그 사람에게만, 그리고 채팅에 남겨 자리를 비웠어도 돌아와서 볼 수 있게.
 *
 * 한 번에 여러 건을 배정하면 알림도 한 번이다. 스무 건을 넘기고 스무 번을
 * 울리면 받는 쪽은 그것을 읽지 않고 지운다.
 *
 * 알리지 못해도 배정은 이미 됐다 — 여기서 던지면 성공한 배정이 실패로 보인다.
 */
class AssignNotice
{
    /** 배정 소식이 쌓이는 방. 사람마다 하나다. */
    public const ROOM_NAME = '담당 배정';

    /**
     * @param  User                            $to  배정받은 사람
     * @param  array<int, Prescription>        $prescriptions
     * @param  User|null                       $by  배정한 사람
     */
    public function tell(User $to, array $prescriptions, ?User $by = null): void
    {
        $n = count($prescriptions);

        if ($n === 0) {
            return;
        }

        /* 첫 건은 이름을 적고 나머지는 수로 적는다. 한 건이면 무엇인지 바로 알고,
           여러 건이면 몇 건인지가 먼저다 — 스무 줄을 토스트에 담아야 읽지 않는다. */
        $first = $prescriptions[0];
        $label = $first->rx_number
            . (($name = $first->patient?->name ?? $first->patient_name_ocr) ? ' · ' . $name : '');
        $body  = $n > 1 ? $label . ' 외 ' . ($n - 1) . '건' : $label;
        $title = '담당 배정 — ' . $n . '건'
            . ($by ? ' (' . $by->name . ')' : '');

        /* 한 건이면 그 자리로 바로 간다. 여러 건이면 갈 자리가 하나가 아니라
           주문 등록 화면의 목록으로 보낸다 — 거기서 고른다. */
        $url = $n === 1
            ? route('prescriptions.show', $first)
            : route('prescriptions.create');

        try {
            broadcast(new WithworksStatusChanged(
                'assign.inform', $title, $body, $url, 'info', $to->id
            ));
        } catch (\Throwable $e) {
            Log::warning('[담당 배정] 알람 실패', ['user' => $to->id, 'error' => $e->getMessage()]);
        }

        /* 대괄호로 시작하지 않는다. 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag) — 그렇게 적으면 본문이
           통째로 사라지고 그 글이 보낸 사람 이름 자리에 선다. */
        $lines = [self::ROOM_NAME . ' · ' . $n . '건을 맡았습니다'
            . ($by ? ' — ' . $by->name . ' 배정' : '')];

        foreach (array_slice($prescriptions, 0, 10) as $p) {
            $lines[] = '· ' . $p->rx_number
                . (($nm = $p->patient?->name ?? $p->patient_name_ocr) ? ' · ' . $nm : '')
                . ($p->order?->order_number ? ' · ' . $p->order->order_number : '');
        }

        if ($n > 10) {
            $lines[] = '· 외 ' . ($n - 10) . '건';
        }

        try {
            $room = $this->roomFor($to->id, self::ROOM_NAME);

            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                // 사람이 보낸 것이 아니다. 화면은 이것을 「알림」으로 세운다.
                'user_id'      => null,
                'body'         => implode("\n", $lines),
            ]);

            ChatMessage::attachToThread($message);

            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('[담당 배정] 채팅 알림 실패', ['user' => $to->id, 'error' => $e->getMessage()]);
        }
    }

    /** 그 사람의 「담당 배정」 방 — 없으면 만든다 */
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
