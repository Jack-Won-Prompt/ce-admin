<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 되돌림 사건에는 원 주문번호가 없을 수 있다.
 *
 * 창고에서 직접 만든 반품이면 우리 주문과 이어지지 않는다. 그런 사건이 오면 칸이
 * NOT NULL 이라 저장에서 터지고, 500 을 받은 쪽은 같은 사건을 끝없이 다시 보낸다.
 *
 * 사건은 받아 두는 것이 먼저다 — 짝이 없더라도 나중에 볼 수 있어야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 컬럼 형 변경이라 doctrine/dbal 없이 raw 로 간다
        DB::statement('ALTER TABLE `withworks_events` MODIFY `ce_order_number` VARCHAR(50) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE `withworks_events` SET `ce_order_number` = '' WHERE `ce_order_number` IS NULL");
        DB::statement('ALTER TABLE `withworks_events` MODIFY `ce_order_number` VARCHAR(50) NOT NULL');
    }
};
