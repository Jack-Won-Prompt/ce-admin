<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상담이 어느 주문 이야기였는지 이어 둔다.
 *
 * 환자 한 사람이 주문을 여러 번 한다 — 처방을 받아 사는 때도 있고 처방 없이 사는 때도
 * 있다. 그래서 상담을 처방전 한 건에 묶어 두면 「무슨 건으로 전화했나」를 알 수 없다.
 *
 * 상담을 적을 때 주문이력에서 골라 잇고, 나중에 잘못 이었으면 다시 고를 수 있어야
 * 하므로 따로 칸을 둔다. 잇지 않은 상담도 있다(주문 전 문의) — 비어 있을 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('counsel_order_id')->nullable()->after('counsel_contents')
                  ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counsel_order_id');
        });
    }
};
