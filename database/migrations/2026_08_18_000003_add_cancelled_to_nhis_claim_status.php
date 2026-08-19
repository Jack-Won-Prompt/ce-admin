<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 취소된 주문은 청구 대상이 아니다.
 *
 * 지금 청구 상태에는 미청구·청구완료·승인·거부뿐이라, 주문이 취소돼도 「미청구」로
 * 남는다. 청구 목록은 주문 상태로 걸러 화면에서는 빠지지만, 값만 보면 아직 청구해야
 * 할 건처럼 읽힌다 — 거부와도 뜻이 다르다(거부는 공단이 물린 것이다).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `orders` MODIFY `nhis_claim_status`
            ENUM('pending','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `orders` SET `nhis_claim_status` = 'pending' WHERE `nhis_claim_status` = 'cancelled'");
        DB::statement("ALTER TABLE `orders` MODIFY `nhis_claim_status`
            ENUM('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
