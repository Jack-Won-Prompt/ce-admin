<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 한 처방전에 주문을 더 세울 수 있게 한다 — 「추가 주문」 (2026-09-14 확인요청 4쪽).
 *
 * 처방전 한 장으로 수량을 나눠 사는 일이 있다. 먼저 일부만 사고 뒤에 나머지를 더 사는데,
 * 여태는 처방전 하나에 주문이 하나뿐이라 그 나머지를 담을 자리가 없었다.
 *
 * 처방번호는 그대로 쓴다 — 담당자에게 그것은 여전히 한 건이다. 주문번호만 따로 선다.
 *
 * orders.prescription_id 에는 유니크 잣대가 없어 표는 이미 여러 줄을 받아 준다.
 * 막고 있던 것은 Prescription::order() 의 hasOne 하나였다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'parent_order_id')) {
                /* 어느 주문에 딸린 것인가 — 추가 주문일 때만 채운다.
                   원 주문이 지워지면 딸린 줄도 함께 지운다(cascade)가 아니라 끊어만 둔다:
                   주문은 돈이 오간 자취라 남겨야 한다. */
                $table->unsignedBigInteger('parent_order_id')->nullable()->after('prescription_id');
                $table->index('parent_order_id');
            }

            if (! Schema::hasColumn('orders', 'order_kind')) {
                // origin(원 주문) · extra(추가 주문). 옛 줄은 모두 원 주문이다.
                $table->string('order_kind', 10)->default('origin')->after('parent_order_id');
                $table->index('order_kind');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_kind')) {
                $table->dropIndex(['order_kind']);
                $table->dropColumn('order_kind');
            }
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $table->dropIndex(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            }
        });
    }
};
