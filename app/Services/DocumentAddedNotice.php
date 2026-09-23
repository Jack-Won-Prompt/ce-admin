<?php

namespace App\Services;

use App\Events\WithworksStatusChanged;
use App\Helpers\FcmHelper;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 남이 내 건에 서류를 보탰다고 알린다 (2026-09-23 지시).
 *
 * 앱에서 이름ㆍ생년월일로 남의 건을 찾아 서류를 보탤 수 있게 되면서 생긴 자리다.
 * 어제 처방전을 올린 사람은 오늘 누가 무엇을 보탰는지 모른다 — 검수를 청할 때
 * 무엇이 올라와 있는지 다시 열어 봐야 한다. 그래서 건을 만든 사람에게 알린다.
 *
 * 앱으로 올린 사람에게는 앱 알림(FCM)으로, 웹에서 일하는 사람에게는 화면 알림으로
 * 간다. 둘 다 없으면 조용히 지나간다 — 알림은 덤이고, 서류는 이미 붙었다.
 *
 * 제가 제 건에 보탠 경우에는 보내지 않는다. 방금 올린 것을 알림으로 되받으면
 * 그 알림을 믿지 않게 된다.
 */
class DocumentAddedNotice
{
    public function 알린다(Prescription $처방전, string $서류갈래, ?User $보탠이): void
    {
        $주인id = (int) $처방전->created_by;

        if (! $주인id || ! $보탠이 || $주인id === (int) $보탠이->id) {
            return;
        }

        $주인 = User::find($주인id);

        if (! $주인 || ! $주인->is_active) {
            return;
        }

        $환자   = $처방전->patient?->name ?: ($처방전->patient_name_ocr ?: $처방전->rx_number);
        $서류이름 = PrescriptionAttachment::labelFor($서류갈래) ?: '서류';
        $본문   = $환자 . ' · ' . $서류이름 . ' — ' . $보탠이->name . ' 님이 추가했습니다';

        $this->앱으로($주인, $처방전, $서류이름, $본문);
        $this->화면으로($주인id, $처방전, $본문);
    }

    /** 앱 알림 — 앱으로 일하는 사람이 바로 본다 */
    private function 앱으로(User $주인, Prescription $처방전, string $서류이름, string $본문): void
    {
        if (! $주인->fcm_token) {
            return;
        }

        try {
            /* 제목ㆍ본문은 메시지 관리에서 고친다 (2026-09-23 지시).
               아래 data 는 앱이 갈 화면을 가리는 값이라 건드리지 않는다. */
            $값 = ['#{처방번호}' => (string) $처방전->rx_number,
                   '#{서류명}'   => $서류이름,
                   '#{내용}'     => $본문];

            FcmHelper::send(
                $주인->fcm_token,
                MessageTemplate::문구('rx_document_added_title', $값,
                    '처방전에 서류가 추가되었습니다', 'fcm'),
                MessageTemplate::문구('rx_document_added', $값, $본문, 'fcm'),
                [
                    // 앱이 이 값을 보고 그 처방전 화면으로 간다
                    'type'            => 'rx_added',
                    'prescription_id' => $처방전->id,
                    'rx_number'       => $처방전->rx_number,
                    'doc_label'       => $서류이름,
                ],
                $주인->id,
            );
        } catch (\Throwable $e) {
            Log::warning('[서류 보탬] 앱 알림 실패', [
                'rx' => $처방전->rx_number, 'user' => $주인->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 웹 화면 알림 — 관리자 화면을 열어 둔 사람에게 토스트로 뜬다 */
    private function 화면으로(int $주인id, Prescription $처방전, string $본문): void
    {
        try {
            $값 = ['#{처방번호}' => (string) $처방전->rx_number, '#{내용}' => $본문];

            broadcast(new WithworksStatusChanged(
                'rx.document.added',
                MessageTemplate::문구('rx_document_added_pusher_title', $값, '서류 추가', 'pusher'),
                MessageTemplate::문구('rx_document_added_pusher', $값, $본문, 'pusher'),
                route('prescriptions.show', $처방전), 'info', $주인id
            ));
        } catch (\Throwable $e) {
            Log::warning('[서류 보탬] 화면 알림 실패', [
                'rx' => $처방전->rx_number, 'user' => $주인id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
