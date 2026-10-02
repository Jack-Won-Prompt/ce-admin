<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 청구 상태를 업무가 쓰는 여섯 자리로 갈아 끼운다 (2026-10-02 지시).
 *
 *   신규 · 서류확인 · 청구등록 · 청구반려 · 청구취소 · 청구완료
 *
 * 예전 일곱 자리(청구 전·청구중·청구완료·승인·반려·보류·취소)에서 옮긴다.
 * 「승인」과 「보류」는 따로 두지 않는다 — 공단이 인정한 것은 청구완료에 담고,
 * 판단을 미룬 것은 아직 청구등록에 머문 것으로 본다.
 *
 * ## 칸이 enum 이다
 *
 * 값만 바꾸려다 그 자리에서 막혔다 —
 *
 *   SQLSTATE[01000]: Warning: 1265 Data truncated for column 'nhis_claim_status'
 *
 * `enum('pending','submitting','submitted','approved','rejected','on_hold','cancelled')`
 * 이라 목록에 없는 이름은 들어가지 않는다. 그래서 세 걸음으로 옮긴다.
 *
 *   ① 옛 이름과 새 이름을 **함께** 받도록 넓힌다
 *   ② 담긴 값을 옮긴다
 *   ③ 새 이름만 남기고 기본값을 신규로 둔다
 *
 * 한 걸음이라도 건너뛰면 그 사이의 줄이 갈 곳을 잃는다.
 *
 * 갈아 끼울 때 담겨 있던 것은 **청구 전 143건뿐**이었다(나머지 여섯은 0건).
 */
return new class extends Migration
{
    /** 옮기는 동안만 함께 받는 이름들 */
    private const 옛이름 = ['pending', 'submitting', 'submitted', 'approved', 'on_hold'];

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $새이름 = array_keys(Order::CLAIM_STATUS_LABELS);

        // ① 둘 다 받도록 넓힌다 — 옮기는 사이에 어느 쪽이 와도 받아야 한다
        $this->칸바꾸기(array_unique(array_merge(self::옛이름, $새이름)), 'pending');

        // ② 담긴 값을 옮긴다
        foreach (Order::CLAIM_STATUS_MIGRATION as $옛 => $새) {
            if ($옛 === $새) {
                continue;   // rejected·cancelled 는 이름이 그대로다
            }

            DB::table('orders')->where('nhis_claim_status', $옛)
                ->update(['nhis_claim_status' => $새]);
        }

        // ③ 새 이름만 남기고, 처음 서는 자리를 신규로 둔다
        $this->칸바꾸기($새이름, Order::CLAIM_NEW);
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $새이름 = array_keys(Order::CLAIM_STATUS_LABELS);

        $this->칸바꾸기(array_unique(array_merge(self::옛이름, $새이름)), 'pending');

        /* 되돌려도 「승인」과 「보류」는 살아나지 않는다 — 청구완료·청구등록으로
           합쳐졌기 때문이다. 그 둘을 쓰던 건이 0건이라 잃을 것이 없다. */
        foreach ([Order::CLAIM_NEW => 'pending', 'registered' => 'submitting', 'completed' => 'submitted'] as $새 => $옛) {
            DB::table('orders')->where('nhis_claim_status', $새)
                ->update(['nhis_claim_status' => $옛]);
        }

        $this->칸바꾸기(array_merge(self::옛이름, ['rejected', 'cancelled']), 'pending');
    }

    /**
     * enum 목록을 다시 적는다.
     *
     * Doctrine 없이 enum 을 바꾸려면 날 질의를 쓸 수밖에 없다 — 스키마 빌더는
     * enum 의 목록 변경을 다루지 못한다. 값은 코드가 쥐고 있어 밖에서 들어오지
     * 않지만, 그래도 따옴표는 DB 가 걸러 주는 길로 넣는다.
     */
    private function 칸바꾸기(array $값들, string $기본): void
    {
        $목록 = implode(',', array_map(
            fn ($v) => "'" . str_replace("'", "''", $v) . "'",
            array_values(array_unique($값들))
        ));

        $기본값 = "'" . str_replace("'", "''", $기본) . "'";

        DB::statement("ALTER TABLE `orders` MODIFY `nhis_claim_status` "
                    . "ENUM({$목록}) NOT NULL DEFAULT {$기본값}");
    }
};
