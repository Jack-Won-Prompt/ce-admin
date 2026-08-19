<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 환자 주소를 주문 등록과 같은 칸으로 나눈다.
 *
 * 지금까지는 address 한 칸에 우편번호·도로명·상세를 통째로 적어 두었다. 주문을 낼 때는
 * 세 칸으로 나뉘어 있어야 해서(창고로 그대로 나간다) 옮겨 적을 때마다 손이 갔다.
 * 기존 address 는 건드리지 않는다 — 도로명 자리로 그대로 두고, 새로 적는 것부터 나뉜다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('postcode', 10)->nullable()->after('address');
            $table->string('address_detail', 200)->nullable()->after('postcode');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['postcode', 'address_detail']);
        });
    }
};
