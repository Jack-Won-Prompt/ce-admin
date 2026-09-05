<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 공단 재등록이 다가온 사람을 담당자에게 알린다 (2026-09-05 지시).
 *
 * 공단에 신규 등록하면 **2년 뒤 다시 등록해야 한다.** 그 기한을 놓치면 자격이
 * 끊기고, 그 뒤에 나간 물건은 공단에 청구할 수 없다 — 이미 보낸 값은 우리가
 * 떠안는다. 환자는 자기 등록이 언제 끝나는지 모른다.
 *
 * 기한 계산은 진작 있었다(등록일 + 2년). 없던 것은 **그것을 누가 언제 보는가**다.
 * 기한이 칸에만 적혀 있으면 아무도 보지 않는다.
 *
 * **기한 2주 전**부터 담당자에게 알린다. 담당자가 확인해 환자에게 알린다 —
 * 문자로 보내고, 필요하면 전화로 상담한다.
 *
 * 하루 한 번 돈다. 같은 사람에게 날마다 알리지 않는다 — 한 번 알린 사람은
 * 표를 남겨 건너뛴다.
 */
class NhisRenewNotice
{
    /** 며칠 앞에서부터 알리는가 */
    public const LEAD_DAYS = 14;

    /** 알림이 쌓이는 방. 사람마다 하나다 — 창고ㆍ반품과 같은 방을 쓴다. */
    public const ROOM_NAME = ReturnNotice::ROOM_NAME;

    /**
     * 기한이 2주 안으로 들어온 사람을 훑어 알린다.
     *
     * @return array{checked:int, told:int, skipped:int}
     */
    public function sweep(?Carbon $today = null): array
    {
        $today = $today ?: Carbon::today();
        $until = $today->copy()->addDays(self::LEAD_DAYS);

        /* 이미 지난 것도 함께 본다 — 놓친 건이야말로 알려야 한다.
           너무 오래 지난 것(3개월 넘음)은 빼둔다. 그때는 재등록이 아니라
           새로 등록하는 일이 되어 이 알림이 할 말이 아니다. */
        $from = $today->copy()->subMonths(3);

        $rows = Patient::query()
            ->whereNotNull('nhis_renew_due')
            ->whereBetween('nhis_renew_due', [$from->toDateString(), $until->toDateString()])
            ->get();

        $told = $skipped = 0;

        foreach ($rows as $patient) {
            if ($this->alreadyToldToday($patient, $today)) {
                $skipped++;
                continue;
            }

            if ($this->tell($patient, $today)) {
                $told++;
            }
        }

        return ['checked' => $rows->count(), 'told' => $told, 'skipped' => $skipped];
    }

    /**
     * 한 사람 몫을 알린다.
     *
     * 담당자에게 토스트로 띄우고 채팅에 남긴다. 문자는 여기서 보내지 않는다 —
     * 환자에게 무엇이라 말할지는 담당자가 정할 일이고, 상담 뒤에 보내야 한다.
     * 화면의 「재등록 안내」 자리에서 담당자가 보낸다.
     */
    public function tell(Patient $patient, ?Carbon $today = null): bool
    {
        $today = $today ?: Carbon::today();
        $due   = Carbon::parse($patient->nhis_renew_due)->startOfDay();
        $left  = $today->diffInDays($due, false);

        $when = $left < 0
            ? '기한이 ' . abs($left) . '일 지났습니다'
            : ($left === 0 ? '오늘이 기한입니다' : $left . '일 남았습니다');

        $title = '공단 재등록 — ' . $when;
        $body  = $patient->name . ' · 기한 ' . $due->format('Y-m-d')
               . ($patient->mobile ? ' · ' . $patient->mobile : '');

        $userId = $this->ownerId($patient);
        $url    = route('patients.show', $patient);

        $this->broadcast($userId, $title, $body, $url, $left < 0 ? 'danger' : 'warning', $patient);

        if ($userId) {
            $this->leaveInChat($userId, $patient, $when, $body);
        }

        $patient->forceFill(['nhis_renew_told_at' => now()])->save();

        return true;
    }

    // ──────────────────────────────────────────────────────────

    /** 오늘 이미 알렸는가 — 날마다 같은 말을 되풀이하지 않는다 */
    private function alreadyToldToday(Patient $patient, Carbon $today): bool
    {
        return $patient->nhis_renew_told_at
            && Carbon::parse($patient->nhis_renew_told_at)->isSameDay($today);
    }

    /**
     * 이 사람은 누구의 것인가.
     *
     * 가장 마지막 처방전에 배정된 사람을 본다. 없으면 그 사람을 만든 사람이다 —
     * 알릴 사람을 모른다고 아무에게도 알리지 않으면 그 건은 영영 묻힌다.
     */
    private function ownerId(Patient $patient): ?int
    {
        $assigned = $patient->prescriptions()
            ->whereNotNull('assigned_user_id')
            ->latest('id')
            ->value('assigned_user_id');

        return $assigned ? (int) $assigned : ($patient->created_by ? (int) $patient->created_by : null);
    }

    private function broadcast(?int $userId, string $title, string $body,
                               string $url, string $tone, Patient $patient): void
    {
        try {
            broadcast(new WithworksStatusChanged('nhis.renew', $title, $body, $url, $tone, $userId));
        } catch (\Throwable $e) {
            Log::warning('[공단 재등록] 알람 실패', [
                'patient' => $patient->id, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 돌아와서 볼 수 있게 채팅에 남긴다 */
    private function leaveInChat(int $userId, Patient $patient, string $when, string $body): void
    {
        /* 대괄호로 시작하지 않는다 — 채팅은 줄머리의 [○○] 를 「어느 화면에서 보냈는가」로
           읽고 본문에서 떼어 낸다(ChatController::stripScreenTag). */
        $line = trim(self::ROOM_NAME . ' · 공단 재등록 ' . $when . ' — ' . $body
                   . ' · 환자에게 알려 주십시오(문자ㆍ전화)');

        try {
            $room = $this->roomFor($userId, self::ROOM_NAME);

            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => null,   // 사람이 보낸 것이 아니다
                'body'         => $line,
            ]);

            ChatMessage::attachToThread($message);
            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('[공단 재등록] 채팅 알림 실패', [
                'patient' => $patient->id, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 그 사람의 알림 방 — 없으면 만든다 */
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
