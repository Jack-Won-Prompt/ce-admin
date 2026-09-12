<?php

namespace App\Services;

use App\Helpers\FcmHelper;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionReuploadRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 자료 다시 올리기 요청 (2026-09-12 지시).
 *
 * 검수하던 사람이 파일 한 장을 짚어 「이것을 다시」를 남기고, 올린 사람의 앱으로
 * 곧바로 알린다. 앱은 이미 제가 올린 자료를 지우고 다시 올릴 수 있으므로
 * (DELETE /api/prescriptions/{rx}/attachments/{id}), 알려 줄 것은 「어느 것을,
 * 왜」 하나뿐이다.
 */
class ReuploadRequestService
{
    /**
     * 요청을 걸고 앱으로 알린다.
     *
     * 알림이 나가지 못해도 요청은 남긴다 — 앱을 지운 사람이라도 화면에서는
     * 「무엇을 되물었나」가 보여야 하고, 검수자가 전화를 걸 근거가 된다.
     */
    public function 걸기(
        Prescription $처방전,
        ?int $첨부id,
        string $사유,
        ?string $메모 = null,
    ): PrescriptionReuploadRequest {
        $첨부 = $첨부id ? PrescriptionAttachment::find($첨부id) : null;

        /* 그 파일을 올린 사람에게 묻는다. 첨부에 올린 이가 없거나 본 그림이면
           처방전을 만든 사람으로 내려간다 (2026-09-12 지시). */
        $받는이 = $this->받을사람($처방전, $첨부);

        $요청 = PrescriptionReuploadRequest::create([
            'prescription_id'   => $처방전->id,
            'attachment_id'     => $첨부?->id,
            'doc_label'         => $첨부?->doc_type_label ?? '처방전',
            'reason'            => $사유,
            'memo'              => $메모 ?: null,
            'requested_by'      => Auth::id(),
            'requested_by_name' => Auth::user()?->name,
            'requested_at'      => now(),
            'target_user_id'    => $받는이?->id,
            'target_user_name'  => $받는이?->name,
        ]);

        /* 새 자료를 받아야 하므로 검수 전으로 돌린다 (2026-09-12 지시).
           이미 마친 건이라도 되돌린다 — 되물을 자료가 있다는 것은 아직 볼 것이
           남았다는 뜻이다. */
        if ($처방전->status !== 'review_needed') {
            $처방전->update(['status' => 'review_needed']);
        }

        $this->알리기($요청, $처방전, $받는이);

        activity()->causedBy(Auth::user())->performedOn($처방전)
            ->withProperties([
                '서류'   => $요청->doc_label,
                '사유'   => $요청->사유말(),
                '받는이' => $받는이?->name ?? '(받을 사람 없음)',
                '알림'   => $요청->fcm_sent ? '보냄' : ($요청->fcm_error ?: '보내지 못함'),
            ])
            ->log('자료 다시 올리기 요청');

        return $요청;
    }

    /**
     * 새 자료가 올라오면 그 서류를 물었던 요청을 닫는다.
     *
     * 사람이 손으로 닫게 하면 닫히지 않는다 — 올린 사람은 다시 올렸으니 끝났다고
     * 여기고, 검수자는 목록에서 「요청 중」이 그대로인 것만 본다.
     */
    public function 닫기(Prescription $처방전, ?string $서류이름, ?int $새첨부id = null): int
    {
        $질의 = $처방전->reuploadRequests()->whereNull('resolved_at');

        /* 서류 이름을 알면 그것만, 모르면(본 그림을 갈아 끼웠을 때 따위) 그 처방전의
           같은 이름짜리만 닫는다. 이름이 아예 없으면 아무것도 닫지 않는다 — 엉뚱한
           요청까지 닫는 것보다 남겨 두는 편이 낫다. */
        if (! $서류이름) {
            return 0;
        }

        $질의->where('doc_label', $서류이름);

        return $질의->update([
            'resolved_at'            => now(),
            'resolved_attachment_id' => $새첨부id,
        ]);
    }

    // ──────────────────────────────────────────────────────

    private function 받을사람(Prescription $처방전, ?PrescriptionAttachment $첨부): ?User
    {
        $id = $첨부?->uploaded_by ?: $처방전->created_by;

        return $id ? User::find($id) : null;
    }

    private function 알리기(PrescriptionReuploadRequest $요청, Prescription $처방전, ?User $받는이): void
    {
        if (! $받는이) {
            $요청->update(['fcm_error' => '올린 사람을 찾지 못했습니다.']);

            return;
        }

        if (! $받는이->fcm_token) {
            $요청->update(['fcm_error' => '앱 알림 토큰이 없습니다(앱 로그인 필요).']);

            return;
        }

        $환자 = $처방전->patient?->name ?: $처방전->patient_name_ocr ?: $처방전->rx_number;

        try {
            $보냄 = FcmHelper::send(
                $받는이->fcm_token,
                '처방전 자료를 다시 올려 주십시오',
                $환자 . ' · ' . $요청->doc_label . ' — ' . $요청->사유말(),
                [
                    /* 앱이 이 값을 보고 해당 처방전 화면으로 간다. 앱이 아직 모르는
                       갈래여도 알림 자체는 뜬다 — 글만으로도 무엇을 다시 올릴지 안다. */
                    'type'            => 'rx_reupload',
                    'request_id'      => $요청->id,
                    'prescription_id' => $처방전->id,
                    'rx_number'       => $처방전->rx_number,
                    'attachment_id'   => $요청->attachment_id ?? '',
                    'doc_label'       => $요청->doc_label,
                    'reason'          => $요청->reason,
                ],
                $받는이->id,
            );

            $보냄
                ? $요청->update(['fcm_sent' => true])
                : $요청->update(['fcm_error' => '앱 알림을 보내지 못했습니다.']);
        } catch (\Throwable $e) {
            Log::warning('[자료 재요청] 알림 실패', ['id' => $요청->id, 'error' => $e->getMessage()]);
            $요청->update(['fcm_error' => mb_substr($e->getMessage(), 0, 255)]);
        }
    }
}
