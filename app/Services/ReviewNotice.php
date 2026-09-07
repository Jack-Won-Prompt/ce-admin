<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 처방전 검수를 기다리는 것을 승인자에게 알린다 (2026-09-07 지시).
 *
 * 「검수 요청하기」는 여태 상태만 바꾸고 아무에게도 알리지 않았다. 요청한
 * 담당자는 눌러 놓고 기다리고, 검수자는 목록을 들여다봐야 알았다 — 그 사이가
 * 얼마나 벌어지는지는 아무도 모른다.
 *
 * 반품 쪽에는 같은 자리에 진작 알림이 있다(ReturnNotice::askApproval). 같은
 * 틀을 쓰고 같은 방에 쌓는다 — 승인할 것이 한자리에 모여야 훑을 수 있다.
 *
 * 되돌아오는 길도 함께 둔다. 승인됐는지도 목록을 다시 봐야 알았다.
 *
 * 알리지 못해도 요청ㆍ승인은 이미 됐다 — 여기서 던지면 끝난 일이 실패로 보인다.
 */
class ReviewNotice
{
    /** 승인을 기다리는 건이 쌓이는 방 — 반품과 같은 방을 쓴다 */
    public const ROOM_NAME = ReturnNotice::APPROVAL_ROOM;

    /* 승인 결과도 같은 방에 남긴다. 요청과 결과가 나란히 서야 「이건 어떻게
       됐더라」를 그 자리에서 읽는다 — 결과만 다른 방에 두면 짝을 찾아 오간다. */

    /** 검수를 기다린다 — 승인할 수 있는 사람들에게 */
    public function askReview(Prescription $rx): void
    {
        $ids = $this->approverIds();

        /* 요청한 사람이 곧 승인자이기도 하면 자기에게 보내지 않는다 —
           방금 누른 일을 알림으로 되받으면 그 알림을 믿지 않게 된다. */
        $ids = array_values(array_diff($ids, [Auth::id()]));

        if (!$ids) {
            Log::warning('[검수] 승인할 수 있는 사람이 없다', ['rx' => $rx->rx_number]);

            return;
        }

        $this->push($ids, self::ROOM_NAME, '검수 요청', $rx,
            '검수 승인을 기다립니다' . ($rx->assignedUser?->name ? ' · ' . $rx->assignedUser->name : ''),
            'warning');
    }

    /** 검수가 끝났다 — 요청한 담당자에게 */
    public function tellApproved(Prescription $rx): void
    {
        $userId = $rx->assigned_user_id ?: $rx->created_by;

        if (!$userId || (int) $userId === (int) Auth::id()) {
            return;
        }

        $this->push([(int) $userId], self::ROOM_NAME, '검수 승인', $rx,
            '검수가 승인되었습니다' . (Auth::user()?->name ? ' · ' . Auth::user()->name : ''),
            'success');
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 알람과 채팅을 함께 보낸다.
     *
     * 토스트는 보고 있을 때만 눈에 든다. 자리를 비운 사이에 지나간 것은 아무 데도
     * 남지 않아, 돌아온 사람은 목록을 새로 불러야 무슨 일이 있었는지 안다.
     *
     * @param list<int> $userIds
     */
    private function push(array $userIds, string $roomName, string $title,
                          Prescription $rx, string $what, string $tone): void
    {
        $who  = $rx->patient?->name ?: ($rx->patient_name_ocr ?: '');
        $body = $rx->rx_number . ($who ? ' · ' . $who : '') . ' — ' . $what;
        $url  = route('prescriptions.show', $rx);

        /* 대괄호로 시작하지 않는다. 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag) — 그렇게 적으면 본문이
           통째로 사라지고 그 글이 보낸 사람 이름 자리에 선다. */
        $line = trim($roomName . ' · ' . $body);

        foreach ($userIds as $userId) {
            try {
                broadcast(new WithworksStatusChanged(
                    'review.inform', $title, $body, $url, $tone, $userId
                ));
            } catch (\Throwable $e) {
                Log::warning('[검수] 알람 실패', ['user' => $userId, 'error' => $e->getMessage()]);
            }

            try {
                $room = $this->roomFor($userId, $roomName);

                $message = ChatMessage::create([
                    'chat_room_id' => $room->id,
                    // 사람이 보낸 것이 아니다. 화면은 이것을 「알림」으로 세운다.
                    'user_id'      => null,
                    'body'         => $line,
                ]);

                ChatMessage::attachToThread($message);

                broadcast(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('[검수] 채팅 알림 실패', [
                    'rx' => $rx->rx_number, 'user' => $userId, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** 검수를 승인할 수 있는 사람들 */
    private function approverIds(): array
    {
        return User::query()
            ->where('is_active', true)
            ->with('permissionGroup')
            ->get()
            ->filter(fn (User $u) => app(PermissionService::class)
                ->allows($u, 'prescriptions', 'approve'))
            ->pluck('id')->all();
    }

    /** 그 사람의 방 — 없으면 만든다 */
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
