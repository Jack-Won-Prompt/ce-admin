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
        $종료 = $d->copy()->addDays($days)->toDateString();

        /* 결제일과 구입일(모든 서류 발행일)은 늘 같은 날짜다(2026-09-09 확정) */
        $rx->forceFill([
            'pay_date'         => $기준,
            'buy_date'         => $기준,
            'use_start_date'   => $기준,
            'benefit_end_date' => $종료,
            'next_repurchase'  => $종료,
        ])->save();

        return true;
    }

    /** 돈이 들어온 날로 다시 센다. 날짜를 주지 않으면 오늘이다. */
    public static function onPaid(?Order $order, ?string $date = null): bool
    {
        $order?->loadMissing('prescription');

        return self::apply($order?->prescription, $date ?: now()->toDateString());
    }
}
