<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 이관 처방전의 **위드웍스 출고 상태**를 적을 자리 (2026-10-12 지시).
 *
 * 「출고 상태 이관 범위이고 우리에게 적용」. 2026-10-06 에 판매 쪽(`ww_so_no`ㆍ
 * `ww_closed_at`ㆍ`ww_cancelled`)을 적을 자리를 두었는데, 출고 쪽은 이관 범위에
 * 들어 있지 않아 화면에 적을 것이 없었다.
 *
 * 저쪽은 판매주문 한 줄에 출고 지시 한 줄이 붙는다.
 *
 *   sales_orders.id  ──→  schedule_by_ships.so_id   (ship_noㆍship_complete_dateㆍstatus)
 *                    ──→  trackings.so_id            (tracking_no)
 *
 * 상태 코드는 우리 웹훅이 쓰는 것과 같은 자리다 — 95 출고완료 · 02 신규 ·
 * 17 할당완료(운영 주문 208건에서 확인). 모르는 코드는 꾸미지 않고 숫자로 적는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'ww_ship_no')) {
                $table->string('ww_ship_no', 40)->nullable()->after('ww_cancelled')
                      ->comment('위드웍스 출고번호 — schedule_by_ships.ship_no');
            }

            if (! Schema::hasColumn('prescriptions', 'ww_ship_status')) {
                $table->string('ww_ship_status', 10)->nullable()->after('ww_ship_no')
                      ->comment('위드웍스 출고 상태 코드 — 95 출고완료 · 02 신규 · 17 할당완료');
            }

            if (! Schema::hasColumn('prescriptions', 'ww_shipped_at')) {
                $table->dateTime('ww_shipped_at')->nullable()->after('ww_ship_status')
                      ->comment('위드웍스 출고완료 시각 — schedule_by_ships.ship_complete_date');
            }

            if (! Schema::hasColumn('prescriptions', 'ww_tracking_no')) {
                $table->string('ww_tracking_no', 60)->nullable()->after('ww_shipped_at')
                      ->comment('위드웍스 운송장번호 — trackings.tracking_no');
            }
        });

        /* 「출고된 건인가」로 찾는 자리가 생긴다 — 목록이 이 칸으로 가린다 */
        Schema::table('prescriptions', function (Blueprint $table) {
            $이름 = 'prescriptions_ww_shipped_idx';

            if (! $this->인덱스있나($이름)) {
                $table->index('ww_shipped_at', $이름);
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if ($this->인덱스있나('prescriptions_ww_shipped_idx')) {
                $table->dropIndex('prescriptions_ww_shipped_idx');
            }

            foreach (['ww_ship_no', 'ww_ship_status', 'ww_shipped_at', 'ww_tracking_no'] as $칸) {
                if (Schema::hasColumn('prescriptions', $칸)) {
                    $table->dropColumn($칸);
                }
            }
        });
    }

    private function 인덱스있나(string $이름): bool
    {
        return collect(Schema::getIndexes('prescriptions'))
            ->contains(fn ($i) => ($i['name'] ?? '') === $이름);
    }
};
