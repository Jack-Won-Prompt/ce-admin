<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 교환·반품·취소가 움직이면 그 일을 할 사람에게 알린다 (요청서 4쪽 Case 표, 2026-08-31).
 *
 * 절차서가 「inform」이라 적어 둔 자리가 셋이다. 그 가운데 둘이 여기다.
 *
 *   창고 → 접수자        알람과 채팅을 함께 (tellTaker)
 *   접수자 → 팀장 승인요청  알람과 채팅을 함께 (askApproval)
 *
 * 나머지 하나(접수자 → 환자)는 밖으로 나가는 말이라 따로 둔다 — ReturnPatientNotice.
 *
 * 알람과 채팅을 함께 보내는 까닭 — 토스트는 보고 있을 때만 눈에 든다. 자리를 비운
 * 사이에 지나간 것은 아무 데도 남지 않아, 돌아온 담당자는 목록을 새로 불러야 무슨 일이
 * 있었는지 안다. 채팅에 남겨야 돌아와서 볼 수 있다.
 *
 * 토스트는 그 사람에게만 보낸다. 전원에게 띄우면 정작 할 일이 있는 사람의 화면에서
 * 남의 건에 섞여 묻힌다.
 *
 * 알리지 못해도 부른 쪽은 성공이다 — 알리지 못한 것과 하지 못한 것은 다른 일이다.
 */
class ReturnNotice
{
    /** 창고 소식이 쌓이는 방. 사람마다 하나다. */
    public const ROOM_NAME = '창고 알림';

    /** 승인을 기다리는 건이 쌓이는 방. 사람마다 하나다. */
    public const APPROVAL_ROOM = '승인 요청';

    /**
     * 창고가 움직였음을 접수자에게 알린다.
     *
     * 접수자는 배정된 사람, 없으면 접수한 사람이다. 둘 다 없으면 보낼 곳이 없다.
     */
    public function tellTaker(OrderReturn $return, string $what, string $tone = 'info'): void
    {
        $userId = $return->assigned_user_id ?? $return->created_by;

        if (!$userId) {
            return;
        }

        $this->push([$userId], self::ROOM_NAME, '창고 — ' . $what, $return, $what, $tone);
    }

    /**
     * 다음이 승인이면 승인할 수 있는 사람들에게 알린다.
     *
     * 절차서의 「접수자 → 팀장님 승인요청」이다. 접수자가 따로 부탁하지 않아도, 단계가
     * 승인 차례에 닿으면 그때가 곧 요청이다 — 사람 손을 거치게 두면 잊는다.
     *
     * 누가 승인자인가는 권한이 정한다. 갈래마다 다른 직책(approverRole)은 화면에
     * 적어 주는 말이고, 실제로 누를 수 있는 사람은 권한을 가진 이들이다.
     */
    public function askApproval(OrderReturn $return): void
    {
        $next = collect($return->nextStatuses())
            ->first(fn ($s) => OrderReturn::needsApproval($s));

        if (!$next) {
            return;
        }

        $ids = $this->approverIds();

        if (!$ids) {
            Log::warning('[반품] 승인할 수 있는 사람이 없다', ['receipt' => $return->receipt_no]);

            return;
        }

        /* 「을」로 적어도 된다 — 승인을 기다리는 단계는 둘뿐이고(검수 확정ㆍ전자 승인)
           둘 다 받침으로 끝난다. 「을(를)」은 읽을 때 걸린다. */
        $what = (OrderReturn::STATUS_LABELS[$next] ?? $next) . '을 기다립니다'
            . ' · ' . $return->approverRole();

        $this->push($ids, self::APPROVAL_ROOM, '승인 요청', $return, $what, 'warning');
    }

    /**
     * 알람과 채팅을 함께 보낸다.
     *
     * @param list<int> $userIds
     */
    private function push(array $userIds, string $roomName, string $title,
                          OrderReturn $return, string $what, string $tone): void
    {
        /* 대괄호로 시작하지 않는다. 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag) — 그렇게 적으면 본문이
           통째로 사라지고 그 글이 보낸 사람 이름 자리에 선다. */
        $line = trim(sprintf(
            '%s · %s · %s%s — %s',
            $roomName,
            $return->receipt_no,
            $return->typeLabel(),
            $return->order?->patient?->name ? ' · ' . $return->order->patient->name : '',
            $what
        ));

        $body = $return->receipt_no . ' · ' . $return->typeLabel()
            . ($return->order?->patient?->name ? ' · ' . $return->order->patient->name : '')
            . ' — ' . $what;

        foreach ($userIds as $userId) {
            try {
                broadcast(new WithworksStatusChanged(
                    'return.inform', $title, $body,
                    route('order-returns.show', $return), $tone, $userId
                ));
            } catch (\Throwable $e) {
                Log::warning('[반품] 알람 실패', ['user' => $userId, 'error' => $e->getMessage()]);
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
                Log::warning('[반품] 채팅 알림 실패', [
                    'receipt' => $return->receipt_no, 'user' => $userId, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 그 사람의 알림 방 — 없으면 만든다.
     *
     * 이름만으로 찾으면 남의 방이 걸린다. 그 사람이 든 방 가운데 이 이름인 것을 찾는다.
     * 접수 건마다 방을 만들지 않는 까닭 — 채팅 목록이 접수번호로 뒤덮여 정작 사람과
     * 나눈 이야기를 찾을 수 없게 된다.
     */
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

    /**
     * 반품을 승인할 수 있는 사람들.
     *
     * 권한은 사람마다 그룹으로 달려 있어 한 번에 거를 질의가 없다. 사람 수가 적어
     * 훑어도 된다 — 늘어나면 그때 권한 그룹으로 좁힌다.
     *
     * @return list<int>
     */
    private function approverIds(): array
    {
        return User::query()
            ->with('permissionGroup')
            ->get()
            ->filter(fn (User $u) => app(PermissionService::class)
                ->allows($u, 'order-returns', 'approve'))
            ->pluck('id')->all();
    }
}
