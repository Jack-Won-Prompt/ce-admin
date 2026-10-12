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

        /* 처방외는 날짜에 매이지 않는다 (2026-09-22 확인요청 2쪽).

           재구매 주기는 공단이 급여로 대주는 건에 붙는 잣대다. 처방외는 처방전 없이
           제 돈으로 사는 것이라 공단에 청구하지 않는다 — 일찍 샀다고 반려될 청구가
           애초에 없으므로 막을 까닭이 없다.

           실제로 같은 사람이 처방전으로 한 번 사고 곧이어 처방외로 사려 하면 「재구매
           가능일 2주 이상 남아 진행불가」가 떠서 살 수 없었다. 두 번째가 처방전이면
           그 말이 맞고, 처방외면 맞지 않다. */
        if ((string) ($rx->counsel_acc_add_type ?? '') === '20') {
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
     * 직전 구매 — **우리 주문이 선 건이거나, 위드웍스에서 이미 산 건** (2026-10-12 · SR #160).
     *
     * 화면의 「이번 재구매 가능일」이 읽는 자리다.
     *
     * 아래 `dueFromLastPurchase()` 는 우리 주문이 선 건만 직전 구매로 센다. 그것이
     * 재구매 제한의 잣대인데, **이관 건에는 우리 주문이 없다** — 이관 처방전 84,302장
     * 가운데 주문이 선 것은 130장뿐이다. 그래서 위드웍스에서 몇 번을 산 사람도 우리
     * 화면에서는 「직전 구매 없음」으로 보였고, 이번 재구매 가능일이 비어 있었다.
     *
     *   (E)전은영  WW-ADD0024066  위드웍스 마감 2026-04-21 · 다음 재구매 2026-07-21
     *              RX-20261008-023 ← 이 건의 「이번 재구매 가능일」이 비어 있었다
     *
     * 위드웍스에서 산 것도 산 것이다. **마감(판매상태2 = 확정)되고 취소가 아닌 건**을
     * 직전 구매로 함께 센다 — 운영 이관 건 80,220장이 여기에 든다.
     *
     * **유형이 처방전인 것만 센다**(`counsel_acc_add_type = '10'`). 재구매 주기는 공단이
     * 급여로 대주는 건에 붙는 잣대라, 처방외(20)ㆍ그 밖(30)은 주기를 세우지 않는다 —
     * `block()` 이 처방외를 막지 않는 것과 같은 잣대다.
     *
     * 차례는 구입일 → 사용 개시일 → 발행일 → 만든 때 순으로 있는 것을 본다. 이관 건은
     * 구입일ㆍ사용 개시일이 거의 비어 있고(각 61장) 발행일만 52,921장 차 있어, 구입일만
     * 보면 이관 건이 통째로 차례에서 빠진다.
     */
    public static function 직전구매(?Prescription $rx): ?Prescription
    {
        if (! $rx?->patient_id) {
            return null;
        }

        $차례 = 'COALESCE(buy_date, use_start_date, issued_date, created_at)';
        $이번 = $rx->buy_date ?: $rx->use_start_date ?: $rx->issued_date ?: $rx->created_at;

        return Prescription::query()
            ->where('patient_id', $rx->patient_id)
            ->whereKeyNot($rx->getKey())
            ->where('counsel_acc_add_type', '10')
            ->where(function ($q) {
                /* 우리 주문이 선 것 — 취소는 산 것이 아니다 */
                $q->whereHas('order', fn ($o) => $o->where('status', '!=', 'cancelled'))
                  /* 또는 위드웍스에서 마감된 것 — 판매상태2 확정이고 취소가 아니다 */
                  ->orWhere(fn ($w) => $w->whereNotNull('ww_closed_at')
                      ->where(fn ($c) => $c->whereNull('ww_cancelled')->orWhere('ww_cancelled', false)));
            })
            /* 이번 건보다 뒤에 산 것은 직전 구매가 아니다 */
            ->when($이번, fn ($q) => $q->whereRaw(
                $차례 . ' <= ?', [\Carbon\Carbon::parse($이번)->toDateTimeString()]
            ))
            ->orderByRaw($차례 . ' DESC')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 이번 재구매 가능일 — 직전 구매가 정해 둔 「다음 재구매 가능일」 (2026-10-12 · SR #160).
     *
     * 이번 건을 **언제부터 살 수 있는가**이고, 이번 건이 만드는 날짜가 아니다. 그래서
     * 이번 건의 사용 개시일ㆍ총 처방일수와는 아무 상관이 없다.
     *
     * 이관 건은 위드웍스 원천의 udf30(다음 재구매 가능일)이 `next_repurchase` 에 담겨
     * 왔다(MigratePrescriptionsFromWithworksCommand). 우리가 올린 건은 저장할 때
     * 「사용 개시일 + 총 처방일수」가 같은 칸에 선다(Prescription::saving). 두 길이 한
     * 칸으로 모이므로 여기서는 그 칸 하나만 읽으면 된다.
     */
    public static function 이번재구매가능일(?Prescription $rx): ?Carbon
    {
        $직전 = self::직전구매($rx);
        $날   = $직전?->next_repurchase ?: $직전?->repurchase_date;

        return $날 ? Carbon::parse($날)->startOfDay() : null;
    }

    /**
     * 같은 거래처의 이전 구매가 정한 재구매 가능일.
     *
     * 「이전 구매」는 실제로 주문이 선 처방전이다. 주문 없이 적다 만 처방전은 산 것이
     * 아니라 세지 않는다.
     *
     * **취소된 주문도 세지 않는다** (2026-10-02 지시).
     *
     * 여태 주문이 있기만 하면 셌다. 그래서 세웠다가 취소한 건이 다음 구매를 막았다 —
     * (E)이명섭A 가 그랬다. 09-11 처방으로 세운 주문을 10-01 에 취소했는데, 그 건이
     * 만든 재구매 가능일(2026-12-11)이 10-02 의 새 주문을 **70일** 막았다.
     *
     *   WW-ADD0068090  재구매가능 2026-12-11
     *       주문 EUD202610010907331 · 상태 cancelled   ← 이것이 막았다
     *
     * 취소한 건은 물건이 나가지 않았고 공단에 청구할 것도 없다. 일찍 샀다고 반려될
     * 청구가 애초에 없으므로 주기를 세울 까닭이 없다 — 처방외를 막지 않는 것과 같은
     * 잣대다.
     *
     * 차례는 구입일로 본다. 없으면 만든 차례로 본다 — 구입일은 담당자가 나중에 적는
     * 일이 있어, 비었다고 그 건을 통째로 빼면 바로 앞의 구매를 놓친다.
     */
    public static function dueFromLastPurchase(Prescription $rx): ?Carbon
    {
        $last = Prescription::query()
            ->where('patient_id', $rx->patient_id)
            ->whereKeyNot($rx->getKey())
            /* 취소된 주문은 산 것이 아니다 (2026-10-02 지시).
               Order 에 상태 상수가 없어 글자로 둔다 — 표에 담긴 값이 그대로 'cancelled' 다. */
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
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
