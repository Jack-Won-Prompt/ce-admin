<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use Illuminate\Support\Facades\Log;

/**
 * 동의가 끝나면 공단에 등록 서류를 팩스로 보낸다(2026-09-03 지시 · 시나리오 1.1.x.1).
 *
 * 여태 담당자가 팩스 창을 열어 서류를 고르고 보냈다. 환자가 밤에 서명하면 그 일은
 * 다음 날 아침으로 밀렸고, 잊으면 공단 등록이 며칠씩 늦어졌다.
 *
 * 다만 조용히 보내지는 않는다. 낼 서류가 다 있으면 보내고, 하나라도 빠졌으면 보내는
 * 대신 담당자에게 알린다 — 빠진 채로 나간 팩스는 공단이 되돌려 보내고, 그때는
 * 처음부터 다시 해야 한다.
 */
final class NhisFaxAuto
{
    /**
     * 공단에 내는 등록 서류 — 화면의 팩스 창이 세우는 것과 같다.
     *
     * 이 목록이 두 곳(여기와 order.blade.php 의 FAX_REQ_DOCS)에 있다. 화면은 담당자가
     * 고르는 자리라 「무엇이 없는가」를 그리는 일이 함께 있고, 여기는 보낼지 말지만
     * 가린다 — 합치려면 화면 쪽이 이 값을 받아 가야 하는데 그 자리는 아직 없다.
     */
    public const REQUIRED = [
        'registration_form' => '등록신청서',
        'test_result'       => '결과지',
        'delegation'        => '요양비위임장',
        'id_card'           => '신분증',
    ];

    /** 알림이 쌓이는 방 */
    private const ROOM_NAME = '공단 팩스';

    /**
     * 보낼 수 있으면 보내고, 아니면 알린다.
     *
     * @return array{sent: bool, reason: ?string}
     */
    public function attempt(Prescription $prescription): array
    {
        $no = fn (?string $why) => ['sent' => false, 'reason' => $why];

        if (! config('order.nhis_fax_on_consent')) {
            return $no(null);                       // 꺼 두었으면 말도 하지 않는다
        }

        $prescription->loadMissing('patient', 'billingOffice', 'order');

        /* 이미 보낸 건은 다시 보내지 않는다. 동의는 다시 받을 수 있고(만료ㆍ재발송),
           그때마다 같은 서류가 공단에 또 가면 저쪽에서 중복 등록으로 읽는다. */
        $order = $prescription->order;

        if ($order && \App\Models\NhisFaxLog::where('order_id', $order->id)
                ->where('status', '!=', 'failed')->exists()) {
            return $no(null);
        }

        /* 공단에 등록할 일이 있는 건만이다. 신구매와 재등록이 그렇고, 그 밖에는
           보낼 까닭이 없다 — 산재ㆍ자동차보험은 애초에 공단에 내지 않는다. */
        if (! $this->needsRegistration($prescription)) {
            return $no(null);
        }

        $missing = $this->missing($prescription);
        $fax     = $this->faxNumber($prescription);

        if ($missing || ! $fax) {
            $why = $missing
                ? implode('ㆍ', $missing) . ' 이(가) 아직 없습니다'
                : '보낼 팩스번호가 없습니다 — 관할 청구처를 먼저 골라 주십시오';

            $this->tell($prescription, $why);

            return $no($why);
        }

        return $this->send($prescription, $fax);
    }

    /** 공단에 등록해야 하는 건인가 */
    private function needsRegistration(Prescription $prescription): bool
    {
        /* 우리가 공단에 대신 청구하는 건만 등록한다. 산재ㆍ자동차보험ㆍ처방외는
           환자가 직접 내므로 등록할 일이 없다(위임동의를 받지 않는 것과 같은 잣대다). */
        if (! \App\Support\BillingStrategy::needsDelegation(
                $prescription->counsel_acc_add_type, $prescription->benefit_class)) {
            return false;
        }

        /* 신구매는 처음 등록하는 건이다. 재등록은 거래처에 적어 둔다(공단 재등록
           예정일) — 처방전이 아니라 사람에게 붙는 값이라 그쪽에서 읽는다. */
        $new   = (string) ($prescription->purchase_type ?? '') === '신구매';
        $renew = trim((string) ($prescription->patient?->nhis_renew ?? '')) !== '';

        return $new || $renew;
    }

    /** 없는 서류의 이름들 — 다 있으면 빈 배열 */
    private function missing(Prescription $prescription): array
    {
        $have = PrescriptionAttachment::where('prescription_id', $prescription->id)
            ->pluck('doc_type')->unique()->all();

        $missing = [];
        foreach (self::REQUIRED as $type => $label) {
            if (! in_array($type, $have, true)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** 어디로 보내는가 — 그 건에 골라 둔 청구처의 팩스 */
    private function faxNumber(Prescription $prescription): ?string
    {
        $fax = preg_replace('/[^0-9\-]/', '', (string) ($prescription->billingOffice?->fax ?? ''));

        return $fax !== '' ? $fax : null;
    }

    /** 팩스를 실제로 보낸다 — 화면의 「팩스 전송」이 지나는 그 길이다 */
    private function send(Prescription $prescription, string $fax): array
    {
        $ids = PrescriptionAttachment::where('prescription_id', $prescription->id)
            ->whereIn('doc_type', array_keys(self::REQUIRED))
            ->pluck('id')->all();

        $request = \Illuminate\Http\Request::create('/', 'POST', [
            'recipient_type' => 'nhis',
            'fax_no'         => $fax,
            'attachment_ids' => $ids,
        ]);

        try {
            $res = app(\App\Http\Controllers\PrescriptionController::class)
                ->sendFax($request, $prescription);

            $body = json_decode($res->getContent(), true);
        } catch (\Throwable $e) {
            Log::error('[공단 팩스 자동] 보내지 못했다', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);

            $this->tell($prescription, '보내는 중 오류가 났습니다 — ' . $e->getMessage());

            return ['sent' => false, 'reason' => $e->getMessage()];
        }

        if ($body['success'] ?? false) {
            activity()->performedOn($prescription)->log("공단 팩스 자동 발송 → {$fax}");
            $this->tell($prescription, "공단({$fax})으로 등록 서류를 보냈습니다.", 'success');

            return ['sent' => true, 'reason' => null];
        }

        $why = $body['message'] ?? '보내지 못했습니다';
        $this->tell($prescription, $why);

        return ['sent' => false, 'reason' => $why];
    }

    /**
     * 담당자에게 알린다.
     *
     * 그 건을 맡은 사람에게 보낸다. 맡은 사람이 없으면 만든 사람에게 — 아무에게도
     * 가지 않으면 서류가 빠진 채로 아무도 모르게 남는다.
     */
    private function tell(Prescription $prescription, string $what, string $tone = 'warning'): void
    {
        $userId = $prescription->assigned_user_id ?: $prescription->created_by;

        if (! $userId) {
            Log::info('[공단 팩스 자동] 알릴 사람이 없다', ['rx' => $prescription->rx_number, 'why' => $what]);

            return;
        }

        $name = $prescription->patient?->name ?: ($prescription->patient_name_ocr ?: '');
        $line = trim(self::ROOM_NAME . ' · ' . $prescription->rx_number
                   . ($name ? ' · ' . $name : '') . ' — ' . $what);

        try {
            $room = $this->roomFor($userId, self::ROOM_NAME);

            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => null,          // 사람이 보낸 것이 아니다
                'body'         => $line,
            ]);

            ChatMessage::attachToThread($message);
            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('[공단 팩스 자동] 알림 실패', [
                'rx' => $prescription->rx_number, 'user' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** 그 사람의 알림 방 — 없으면 만든다(반품 알림과 같은 방식) */
    private function roomFor(int $userId, string $name): ChatRoom
    {
        $room = ChatRoom::where('name', $name)
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
