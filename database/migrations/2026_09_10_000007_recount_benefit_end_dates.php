<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 급여 종료일을 새 잣대로 다시 센다 — 소급 (2026-09-10 지시).
 *
 * 공단 화면이 세는 법이 우리와 하루 달랐다. 사용개시일 2026-06-21 · 실지급일수 90 이면
 * 공단은 급여종료일을 2026-09-18 로 적는다 — 마지막 날도 쓰는 날로 세기 때문이다.
 * 우리는 09-19 로 적고 있었다. 하루를 더 적어 두면 그 하루치가 이중으로 청구된다.
 *
 * **이미 적어 둔 것만 다시 센다.** 급여 종료일이 비어 있는 건은 건드리지 않는다 —
 * 그 값은 결제나 주문 등록에서 서는 것이지, 여기서 채워 넣을 것이 아니다.
 *
 * 다음 재구매 가능일은 그대로다. 예전에도 「개시일 ＋ 일수」였고 지금도 그렇다 —
 * 급여 기간이 끝난 다음 날부터 산다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasColumn('prescriptions', 'benefit_end_date')) {
            return;
        }

        $줄들 = DB::table('prescriptions')
            ->whereNotNull('benefit_end_date')
            ->whereNotNull('total_days')
            ->where('total_days', '>', 0)
            ->get(['id', 'rx_number', 'use_start_date', 'pay_date', 'buy_date',
                   'total_days', 'benefit_end_date']);

        foreach ($줄들 as $r) {
            $기준 = $r->use_start_date ?: ($r->pay_date ?: $r->buy_date);

            if (! $기준) {
                continue;   // 셀 수 없는 건은 그대로 둔다
            }

            try {
                $종료 = Carbon::parse($기준)->startOfDay()
                    ->addDays((int) $r->total_days - 1)
                    ->toDateString();
            } catch (\Throwable) {
                continue;
            }

            if ($종료 === (string) $r->benefit_end_date) {
                continue;   // 이미 새 잣대와 같다
            }

            DB::table('prescriptions')->where('id', $r->id)->update([
                'benefit_end_date' => $종료,
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * 되돌리지 않는다.
     *
     * 되돌리면 공단과 하루 어긋난 값으로 돌아간다 — 고치려던 그 일이다.
     */
    public function down(): void
    {
    }
};
