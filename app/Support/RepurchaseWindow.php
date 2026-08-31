<?php

namespace App\Support;

use App\Models\Prescription;
use Carbon\Carbon;

/**
 * 아직 살 때가 아닌 건을 막는다 (요청서 2쪽, 2026-08-31).
 *
 * 「재구매 가능일보다 2주(14일) 넘게 이른 주문은 진행할 수 없다.」 공단이 정한 재구매
 * 주기보다 일찍 나간 건은 나중에 청구가 반려된다 — 물건은 이미 나갔고 돈은 못 받는다.
 * 그래서 나가기 전에 막는다.
 *
 * 기준은 「같은 거래처의 이전 구매일」이다(2026-08-31 회신). 지금 적고 있는 처방전이
 * 아니라, 그 사람이 지난번에 산 건을 본다 — 이번 처방전의 재구매 가능일은 아직 담당자가
 * 적는 중이라 비어 있거나 방금 고쳐 넣은 값이다. 그것을 기준 삼으면 스스로를 재는 셈이다.
 *
 * 판단할 수 없으면 막지 않는다. 첫 구매인 사람, 이전 건에 재구매 가능일이 적히지 않은
 * 사람이 그렇다 — 모르는 것을 이유로 막으면 담당자는 까닭도 모른 채 일을 못 한다.
 */
class RepurchaseWindow
{
    /** 재구매 가능일보다 이만큼 앞서면 살 수 있다 */
    public const DAYS = 14;

    /**
     * 지금 주문을 세워도 되는가.
     *
     * 되면 null, 안 되면 막는 까닭을 돌려준다.
     */
    public static function block(?Prescription $rx): ?string
    {
        if (!$rx?->patient_id) {
            return null;
        }

        if (!($due = self::dueFromLastPurchase($rx))) {
            return null;
        }

        // 열 수 있는 날 = 재구매 가능일 - 2주. 그 전이면 아직 이르다.
        if (!today()->lt($due->copy()->subDays(self::DAYS))) {
            return null;
        }

        return '재구매 가능일 2주 이상 남아 진행불가합니다'
            . ' (재구매 가능일 ' . $due->format('Y-m-d')
            . ' · ' . today()->diffInDays($due) . '일 남음)';
    }

    /**
     * 같은 거래처의 이전 구매가 정한 재구매 가능일.
     *
     * 「이전 구매」는 실제로 주문이 선 처방전이다. 주문 없이 적다 만 처방전은 산 것이
     * 아니라 세지 않는다.
     *
     * 차례는 구입일로 본다. 없으면 만든 차례로 본다 — 구입일은 담당자가 나중에 적는
     * 일이 있어, 비었다고 그 건을 통째로 빼면 바로 앞의 구매를 놓친다.
     */
    public static function dueFromLastPurchase(Prescription $rx): ?Carbon
    {
        $last = Prescription::query()
            ->where('patient_id', $rx->patient_id)
            ->whereKeyNot($rx->getKey())
            ->whereHas('order')
            /* 이번 건보다 뒤에 산 것은 「이전 구매」가 아니다. 이번 건에 구입일이 아직
               없으면 가릴 잣대가 없으니 전부 본다 — 가장 최근 구매가 곧 이전 구매다. */
            ->when($rx->buy_date, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('buy_date')->orWhere('buy_date', '<=', $rx->buy_date)
            ))
            ->orderByRaw('buy_date IS NULL, buy_date DESC')
            ->orderByDesc('id')
            ->first();

        $due = $last?->next_repurchase ?: $last?->repurchase_date;

        return $due ? Carbon::parse($due)->startOfDay() : null;
    }
}
