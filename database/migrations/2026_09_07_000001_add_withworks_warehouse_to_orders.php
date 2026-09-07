<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 창고 이름을 담을 자리 (2026-09-07 지시).
 *
 * Finance 화면이 위드웍스 판매현황과 같은 칸을 세우게 되었는데, 창고만 우리가
 * 만들 길이 없어 빈 채였다. 저쪽 판매주문에는 진작 적혀 있는 값이라 웹훅에 실어
 * 보내게 하고 여기 받아 둔다.
 *
 * 번호가 아니라 이름을 담는다 — 저쪽 번호는 우리 화면에서 아무 뜻이 없고,
 * 그것을 이름으로 바꾸려면 매번 저쪽에 물어야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('withworks_warehouse', 100)->nullable()->after('withworks_status_at')
              ->comment('출고창고 이름 — 위드웍스 웹훅이 알려 준다');
            $t->string('withworks_deliver_warehouse', 100)->nullable()->after('withworks_warehouse')
              ->comment('납품창고 이름 — 위드웍스 웹훅이 알려 준다');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['withworks_warehouse', 'withworks_deliver_warehouse']);
        });
    }
};
