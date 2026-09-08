<?php

namespace App\Support;

use App\Models\Prescription;
use App\Services\NhisFaxAuto;

/**
 * 빠졌던 신분증이 나중에 들어왔을 때 공단 팩스를 다시 재는 자리.
 *
 * 여태 NhisFaxAuto::attempt() 를 부르는 자리는 하나뿐이었다 — **위임동의 서명이
 * 끝나는 그 순간**이다. 그때 신분증이 없으면 「신분증이 아직 없습니다」로 걸리고,
 * 뒤에 신분증이 들어와도 다시 재는 자리가 없어 담당자가 팩스 창을 열어 손으로
 * 보내야 했다(2026-09-09 확인).
 *
 * 신분증이 닿는 길은 둘이다 — 신분증 링크로 받는 것과 첨부로 올리는 것. 두 자리
 * 모두 여기를 지나게 해, 「다시 잰다」는 판단이 한곳에만 있게 한다.
 *
 * **설정으로 켠다.** 담당자가 팩스 창에서 손수 고르는 것을 기대하는 곳이 있어,
 * 아무 말 없이 나가기 시작하면 그쪽이 놀란다(order.nhis_fax_on_id_card).
 */
final class NhisFaxRetry
{
    /**
     * 신분증이 닿았다 — 이제 다 갖춰졌으면 보낸다.
     *
     * 보낼지 말지는 여전히 NhisFaxAuto 가 가린다. 여기서는 「다시 재도 되는가」만
     * 본다. 이미 보낸 건은 NhisFaxLog 를 보고 저쪽이 스스로 물러난다.
     */
    public static function afterIdCard(Prescription $prescription): void
    {
        if (! config('order.nhis_fax_on_id_card')) {
            return;
        }

        /* 보내는 것을 통째로 꺼 두었으면 이것만 켜도 나가지 않는다 — 저쪽에서도
           같은 것을 보지만, 여기서 물러나면 「알림」조차 남기지 않는다. 신분증이
           들어올 때마다 「꺼져 있습니다」가 쌓이면 정작 볼 것을 못 본다. */
        if (! config('order.nhis_fax_on_consent')) {
            return;
        }

        try {
            app(NhisFaxAuto::class)->attempt($prescription);
        } catch (\Throwable $e) {
            /* 팩스가 못 나갔다고 신분증 접수까지 되돌릴 수는 없다. 받은 것은 받은
               것이고, 못 보낸 것은 담당자가 팩스 창에서 보내면 된다. */
            \Illuminate\Support\Facades\Log::error('[공단 팩스] 신분증 접수 뒤 재시도 실패', [
                'rx'    => $prescription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
