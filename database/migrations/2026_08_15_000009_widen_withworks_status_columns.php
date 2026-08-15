<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withworks 상태 컬럼을 넓힌다.
 *
 * varchar(10) 은 Withworks 가 '02' 같은 짧은 코드를 주던 시절에 맞춰 둔 것이다. 이제 그쪽이
 * 웹훅으로 상태를 보내는데, 무엇을 보낼지는 우리가 정하지 않는다. 길이가 넘치면 저장이 실패해
 * 500 이 나가고, 500 을 받은 쪽은 계속 다시 보낸다 — 한 건 때문에 재시도가 쌓인다.
 *
 * 남의 값을 담는 칸은 넉넉해야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('withworks_status', 50)->nullable()->change();
            $table->string('withworks_ship_status', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('withworks_status', 10)->nullable()->change();
            $table->string('withworks_ship_status', 10)->nullable()->change();
        });
    }
};
