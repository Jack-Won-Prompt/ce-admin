<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 처방전에 「위드웍스에서 이미 끝난 건인가」를 적는다 (2026-10-06 · SR #78).
 *
 * 위드웍스에서 온 처방전 84,189장 가운데 **80,163장(95.2%)이 이미 구매확정되거나
 * 취소된 건**인데, 전부 `approved` 로 서 있어 처방전 목록에서 「주문등록 이동」을
 * 누를 수 있었다. 담당자는 쓸 수 있는 4,026장을 그 속에서 눈으로 골라내고 있었고,
 * 실제로 50건이 눌려 주문번호가 났다(2026-10-06 실측 · 김봉식 건이 그 하나).
 *
 * 그 판정은 저쪽 DB 를 두 번 타야 나온다.
 *
 *   prescriptions.ww_add_id → counsellings.add_id → counsellings.so_id → sales_orders
 *
 * 상담 10만 줄과 판매주문 30만 줄을 목록을 열 때마다 묶을 수는 없고, 걸린 처방전
 * 번호를 목록으로 넘기면 질의가 터진다(실제로 「too many placeholders」로 막혔다).
 * 그래서 **우리 표에 적어 두고** 가져오기가 돌 때 갱신한다.
 *
 * 번호와 날짜까지 적는 까닭 — 담당자가 「왜 안 보이나」를 물을 때 화면에서 바로
 * 답할 수 있어야 한다. 「위드웍스 S2607020137 · 구매확정 2026-07-02」처럼 보인다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'ww_so_no')) {
                $table->string('ww_so_no', 40)->nullable()->after('ww_add_id')
                      ->comment('위드웍스 판매주문 번호 — 이 처방전으로 이미 나간 주문');
            }
            if (! Schema::hasColumn('prescriptions', 'ww_closed_at')) {
                $table->date('ww_closed_at')->nullable()->after('ww_so_no')
                      ->comment('위드웍스에서 마감된 날 — 구매확정일. 취소면 비어 있고 ww_cancelled 가 선다');
            }
            if (! Schema::hasColumn('prescriptions', 'ww_cancelled')) {
                $table->boolean('ww_cancelled')->default(false)->after('ww_closed_at')
                      ->comment('위드웍스에서 취소된 건인가');
            }
        });

        /* 목록이 「아직 안 끝난 것」만 세우는 자리에 걸린다 — 8만 장을 건너뛴다 */
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['ww_closed_at', 'ww_cancelled'], 'prescriptions_ww_closed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('prescriptions_ww_closed_idx');
            $table->dropColumn(['ww_so_no', 'ww_closed_at', 'ww_cancelled']);
        });
    }
};
