<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 옮겨 온 처방 품목이 원천의 어느 줄에서 왔는지 적어 둔다 (2026-09-29 지시).
 *
 * 품목은 여섯 만 줄이다. 다시 돌릴 때 같은 품목이 한 벌 더 쌓이면 수량이 두 배로 읽혀
 * 주문에 그대로 실린다 — 유일 색인으로 표가 막는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescription_items')) {
            return;
        }

        Schema::table('prescription_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_items', 'ww_sod_id')) {
                $table->unsignedBigInteger('ww_sod_id')->nullable()->unique()
                      ->comment('위드웍스 sales_order_details.id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescription_items')) {
            return;
        }

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropUnique(['ww_sod_id']);
            $table->dropColumn('ww_sod_id');
        });
    }
};
