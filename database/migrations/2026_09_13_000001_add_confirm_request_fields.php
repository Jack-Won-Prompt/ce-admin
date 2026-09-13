<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 확인요청(2026-09-11) 3ㆍ4ㆍ5쪽에서 요청한 칸 셋.
 *
 *  3쪽 patients.managed_customer  관리고객 — 0ㆍ1ㆍ2 로 고른다
 *  4쪽 prescriptions.counsel_date 상담일시에 시간까지 담는다 (date → datetime)
 *  5쪽 orders.ship_request_date   출고요청일 — 대개 제품을 적는 날이지만,
 *                                 환자가 날을 지정하면 그날로 내보낸다
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedTinyInteger('managed_customer')->nullable()->after('fax');
        });

        /* 날짜만 담던 칸에 시간을 더한다. 이미 담긴 값은 00:00:00 이 붙는다 —
           그날 있었던 상담이라는 사실은 그대로다. */
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dateTime('counsel_date')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->date('ship_request_date')->nullable()->after('warehouse_note');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('managed_customer');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->date('counsel_date')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ship_request_date');
        });
    }
};
