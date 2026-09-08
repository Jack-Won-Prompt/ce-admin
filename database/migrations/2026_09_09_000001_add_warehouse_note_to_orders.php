<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 창고 전달 메모 — 위드웍스 판매주문의 「비고」로 그대로 나간다.
 *
 * 여태 그 자리에는 처방전의 등록자 메모(admin_note)가 실려 나갔다. 그 메모는 우리가
 * 접수하며 적어 두는 말이라 창고에 전할 말과 다르다 — 「결과지 19장 · 재구매 건」 같은
 * 것이 창고 비고에 그대로 찍혔다.
 *
 * 창고에 전할 말을 따로 받는다. 비워 두면 예전처럼 등록자 메모가 나간다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'warehouse_note')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('warehouse_note', 500)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'warehouse_note')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('warehouse_note');
        });
    }
};
