<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\WithworksStatusChanged;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Prescription;
use App\Models\PrescriptionReuploadRequest;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 되물은 자료가 다시 올라온 것을 웹으로 알린다 (2026-09-17 지시).
 *
 * 검수자가 「파일 다시 올리기」로 자료를 되물으면 올린 사람의 앱으로 알림이 간다
 * (ReuploadRequestService). 그 반대 방향은 없었다 — 담당자가 앱에서 다시 올려도
 * 검수자는 목록을 다시 열어 봐야 알았다. 되물은 사람이 기다리는 자리이므로,
 * 자료가 도착하면 그 사람에게 알린다.
 *
 * 알림 방식은 검수 요청(ReviewNotice)과 같다 — 토스트로 그 자리에 띄우고,
 * 「승인 요청」 방에 한 줄로 남긴다. 자리를 비운 사이에 지나간 것도 방에 남는다.
 *
 * 알리지 못해도 업로드는 이미 끝났다 — 여기서 예외를 던지지 않는다.
 */
class ReuploadArrivedNotice
{
    /** 검수 요청과 같은 방에 쌓는다 — 검수자가 볼 것이 한자리에 모여야 한다 */
    public const ROOM_NAME = ReturnNotice::APPROVAL_ROOM;

    /**
     * @param \Illuminate\Support\Collection<int, PrescriptionReuploadRequest> $닫힌요청
     */
    public function arrived(Prescription $rx, $닫힌요청): void
    {
        $서류 = $닫힌요청->pluck('doc_label')->filter()->unique()->implode('ㆍ');

        /* 되물은 사람에게 알린다. 자기가 올린 경우에는 보내지 않는다 —
           방금 올린 것을 알림으로 되받으면 그 알림을 믿지 않게 된다. */
        $받는이들 = $닫힌요청->pluck('requested_by')->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) Auth::id())
            ->unique()->values();

        if ($받는이들->isEmpty()) {
            return;
        }

        $who  = $rx->patient?->name ?: ($rx->patient_name_ocr ?: '');
        $body = $rx->rx_number . ($who ? ' · ' . $who : '')
              . ' — ' . ($서류 ?: '자료') . ' 재업로드';
        $url  = route('prescriptions.show', $rx);

        foreach ($받는이들 as $userId) {
            try {
                /* 제목ㆍ본문은 메시지 관리에서 고친다 (2026-09-23 지시) */
                $값 = ['#{처방번호}' => (string) $rx->rx_number,
                       '#{고객명}'   => $who,
                       '#{서류명}'   => (string) ($서류 ?: '자료'),
                       '#{내용}'     => $body,
                       '#{고객명줄}' => $who ? ' · ' . $who : ''];

                broadcast(new WithworksStatusChanged(
                    'reupload.arrived',
                    MessageTemplate::문구('reupload_arrived_title', $값, '자료 재업로드', 'pusher'),
                    MessageTemplate::문구('reupload_arrived', $값, $body, 'pusher'),
                    $url, 'success', $userId
                ));
            } catch (\Throwable $e) {
                Log::warning('[재업로드] 알람 실패', ['user' => $userId, 'error' => $e->getMessage()]);
            }

            try {
                $room = $this->roomFor($userId, self::ROOM_NAME);

                /* 줄머리를 대괄호로 시작하지 않는다 — 채팅이 [○○] 를 「어느 화면에서
                   보냈는가」로 읽어 본문에서 떼어 낸다(ChatController::stripScreenTag). */
                $message = ChatMessage::create([
                    'chat_room_id' => $room->id,
                    // 사람이 보낸 것이 아니다. 화면은 이것을 「알림」으로 세운다.
                    'user_id'      => null,
                    'body'         => trim(self::ROOM_NAME . ' · ' . $body),
                ]);

                ChatMessage::attachToThread($message);

                broadcast(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('[재업로드] 채팅 알림 실패', [
                    'rx' => $rx->rx_number, 'user' => $userId, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** 그 사람의 방 — 없으면 만든다 (ReviewNotice 와 같은 방식) */
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
