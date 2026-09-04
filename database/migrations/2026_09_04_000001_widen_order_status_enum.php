<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 창고의 가운데 세 단계를 주문 상태에 받아들인다.
 *
 * 2026-09-03 에 위드웍스 사건마다 주문 상태를 옮기게 했다(so.allocated → 재고 할당,
 * so.picked → 피킹 완료, so.invoiced → 송장 출력). 화면의 STATUS_LABELS 도 여덟 갈래로
 * 늘렸다. 그런데 orders.status 는 다섯 갈래 ENUM 그대로였다.
 *
 * MySQL 은 ENUM 에 없는 값을 넣으면 경고를 내고 빈 문자열로 잘라 넣는다
 * (SQLSTATE 01000 · Data truncated). 그래서 세 사건이 올 때마다 500 으로 끝나고,
 * 주문은 「주문 확정」에 멈춘 채였다 — 담당자는 물건이 어디쯤 왔는지 목록에서
 * 알 수 없어 위드웍스 화면을 따로 열어야 했다. 늘리려던 그 일이 하나도 되지 않았다.
 *
 * 값은 Order::STATUS_LABELS 의 여덟 갈래와 같은 차례로 둔다.
 */
return new class extends Migration
{
    private const AFTER  = "'pending','confirmed','allocated','picked','invoiced','shipping','delivered','cancelled'";
    private const BEFORE = "'pending','confirmed','shipping','delivered','cancelled'";

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `orders` MODIFY `status` ENUM(" . self::AFTER . ") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        /* 되돌리기 전에 새 갈래에 멈춰 있는 건을 확정으로 내린다 —
           그대로 두면 ENUM 밖의 값이 되어 빈 문자열로 잘린다. */
        DB::table('orders')
            ->whereIn('status', ['allocated', 'picked', 'invoiced'])
            ->update(['status' => 'confirmed']);

        DB::statement(
            "ALTER TABLE `orders` MODIFY `status` ENUM(" . self::BEFORE . ") NOT NULL DEFAULT 'pending'"
        );
    }
};
