<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 청구 준비 상태를 주문에 둔다.
 *
 * 지금까지는 청구 창을 열어야 무엇이 빠졌는지 알았다. 목록에서는 전부 똑같아 보여서,
 * 담당자가 하나씩 열어 보고 닫기를 반복했다. 자료가 갖춰졌는지를 미리 계산해 두면
 * 목록에서 바로 골라낼 수 있다.
 *
 * 계산해서 얻을 수 있는 값을 굳이 저장하는 이유는 목록 때문이다. 한 화면에 수십 건을
 * 띄우면서 건마다 서류를 뒤지면 목록이 느려지고, 정렬·필터도 할 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'claim_ready')) {
                $table->boolean('claim_ready')->default(false)->after('nhis_rejection_reason');
            }
            if (!Schema::hasColumn('orders', 'claim_missing')) {
                $table->string('claim_missing', 255)->nullable()->after('claim_ready');
            }
            if (!Schema::hasColumn('orders', 'claim_checked_at')) {
                $table->timestamp('claim_checked_at')->nullable()->after('claim_missing');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('claim_ready', 'orders_claim_ready_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_claim_ready_idx');
            $table->dropColumn(['claim_ready', 'claim_missing', 'claim_checked_at']);
        });
    }
};
