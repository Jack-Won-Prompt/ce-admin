<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Prescription;
use Carbon\Carbon;

/**
 * 급여 종료일과 다음 재구매 가능일을 센다 (2026-09-08 확인요청 10쪽 · 2026-09-09 확정).
 *
 * 둘 다 **모든 서류 발행일 + 총 처방일수**다. 그리고 모든 서류 발행일은 곧 결제일이라
 * 늘 같은 날짜여야 한다 — 그래서 여기서 두 칸을 함께 맞춘다.
 *
 * 언제 채우나
 *   · 돈이 들어오면 그때 (가상계좌 입금 확인ㆍ담당자 확인ㆍ토스 승인)
 *   · 결제가 없는 건(기초ㆍ차상위)은 주문 등록에서 저장할 때 — 그때 담당자가 적어 둔
 *     구입일이 기준이 된다
 *
 * 건보위임동의 기간과 헷갈리지 않는다. 그쪽은 시작일 + 5년 - 1일이고 거래처에 붙는다.
 */
class BenefitDates
{
    /**
     * 기준일에서 다시 센다. 셀 수 없으면 아무것도 건드리지 않고 false 를 돌려준다.
     *
     * 총 처방일수를 모르면 세지 않는다 — 0 을 더해 「오늘까지」로 적어 두면 청구가
     * 막히는데, 그것이 셈에서 나온 값인지 사람이 적은 값인지 알 길이 없어진다.
     */
    public static function apply(?Prescription $rx, ?string $base = null): bool
    {
        if (! $rx) {
            return false;
        }

        $base = $base ?: ($rx->pay_date ?: $rx->buy_date);
        $days = (int) ($rx->total_days ?? 0);

        if (! $base || $days < 1) {
            return false;
        }

        try {
            $d = Carbon::parse($base)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $기준 = $d->toDateString();

        /* 급여 종료일은 **사용 개시일 + 실지급일수 − 1**이다 (2026-09-10 확정).

           공단 화면이 그렇게 센다 — 사용개시일 2026-06-21 · 실지급일수 90 이면
           급여종료일은 2026-09-18 이다(2026-09-19 가 아니다). 마지막 날도 쓰는 날로
           세기 때문이다. 하루를 더 적어 두면 그 하루치가 이중으로 청구된다.

           기준은 사용 개시일이다. 적어 둔 것이 있으면 그것을 쓰고, 없으면 결제일이
           곧 사용 개시일이다 — 공단 화면에서도 구입일과 사용개시일은 다를 수 있다. */
        $사용개시 = trim((string) ($rx->use_start_date ?? '')) !== ''
                    ? Carbon::parse($rx->use_start_date)->startOfDay()
                    : $d->copy();

        $종료 = $사용개시->copy()->addDays($days - 1)->toDateString();

        /* Five/Six(110days) 인 건은 다음 재구매 가능일이 스무 날 뒤다
           (2026-09-08 확인요청 10쪽 · 2026-09-09 확정).

           급여 종료일은 그대로 둔다 — 언제까지 쓰는가와 언제 다시 살 수 있는가는
           다른 물음이다. 그 프로그램은 한 번에 더 많이 받아 가므로 다음 구매가
           그만큼 늦다. */
        $다음구매 = self::백십일프로그램인가($rx)
                        ? $사용개시->copy()->addDays($days + 20)->toDateString()
                        : $사용개시->copy()->addDays($days)->toDateString();

        /* 결제일과 구입일(모든 서류 발행일)은 늘 같은 날짜다(2026-09-09 확정) */
        /* 사용 개시일은 적혀 있으면 덮지 않는다 (2026-09-10).
           공단 화면에서 구입일과 사용개시일은 다를 수 있고, 그 날짜에서 급여 기간이
           선다 — 결제했다고 담당자가 적어 둔 개시일을 지울 까닭이 없다. */
        $rx->forceFill([
            'pay_date'         => $기준,
            'buy_date'         => $기준,
            'use_start_date'   => $사용개시->toDateString(),
            'benefit_end_date' => $종료,
            'next_repurchase'  => $다음구매,
        ])->save();

        return true;
    }

    /**
     * Five/Six(110days) 인가.
     *
     * 그 칸에 값이 적혀 있으면 그 프로그램으로 본다 — 「예ㆍ아니오」를 고르는 칸이
     * 아니라 값을 적는 칸이고(지금 담긴 일곱 건은 모두 540), 적혀 있다는 것 자체가
     * 그 프로그램이라는 뜻이다.
     */
    private static function 백십일프로그램인가(Prescription $rx): bool
    {
        return trim((string) ($rx->five_110days ?? '')) !== '';
    }

    /** 돈이 들어온 날로 다시 센다. 날짜를 주지 않으면 오늘이다. */
    public static function onPaid(?Order $order, ?string $date = null): bool
    {
        $order?->loadMissing('prescription');

        return self::apply($order?->prescription, $date ?: now()->toDateString());
    }
}
