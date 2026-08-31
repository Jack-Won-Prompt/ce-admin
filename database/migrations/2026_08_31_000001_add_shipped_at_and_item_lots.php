<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 출고일자와 Lotㆍ유효기간을 담을 자리 (요청서 2쪽, 2026-08-31).
 *
 * 「제품 정보가 든 모든 화면에 Lot 에 해당하는 유효기간을 함께」 — 담을 칸이 하나도 없었다.
 * 거래명세서에 lot 칸이 있으나 늘 빈칸이었다(TransactionStatement).
 *
 * 출고일자도 마찬가지다. 지금 있는 withworks_ship_at 은 「우리가 출고 정보를 적어 둔
 * 시각」이라 실제 출고일이 아니다 — 열 분마다 도는 훑기가 늦게 채우면 그만큼 늦은 날이
 * 적힌다. 창고가 알려 주는 진짜 출고일을 따로 받는다.
 *
 * Lot 을 주문 줄에 붙이지 않고 표를 따로 두는 까닭 — 같은 제품이 두 Lot 으로 나뉘어
 * 나가는 일이 있다. 한 칸에 담으면 그때 둘 중 하나를 버리거나 쉼표로 이어 붙여야 하고,
 * 이어 붙인 것은 유효기간과 짝이 어긋난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipped_at')) {
                // 창고가 알려 주는 출고일. 날짜만 쓴다 — 목록에 서는 것은 「언제 나갔나」다.
                $table->date('shipped_at')->nullable()->after('withworks_ship_at');
            }
        });

        if (!Schema::hasTable('order_item_lots')) {
            Schema::create('order_item_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
                $table->string('lot_no', 100);
                $table->date('expiry_date')->nullable();
                // 그 Lot 으로 몇 개가 나갔는가. 나뉘어 나간 건을 다시 맞출 때 쓴다.
                $table->integer('quantity')->nullable();
                $table->timestamps();

                /* 같은 사건이 두 번 와도 줄이 겹치지 않게 한다. 웹훅은 다시 보내는 것이
                   정상이고, 걸러 내는 것은 받는 쪽 몫이다. */
                $table->unique(['order_item_id', 'lot_no'], 'order_item_lots_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_lots');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipped_at')) {
                $table->dropColumn('shipped_at');
            }
        });
    }
};
