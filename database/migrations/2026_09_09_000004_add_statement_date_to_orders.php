<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 거래명세서 발행일.
 *
 * 명세서에 찍는 날은 「돈이 오간 날」로 셈해 왔다(TransactionStatement::issueDate).
 * 그런데 셈만 하고 어디에도 남기지 않아, 종이에 찍힌 날과 우리가 나중에 다시 셈한
 * 날이 어긋날 수 있었다 — 카드 결제가 취소되고 다시 잡히면 셈이 달라진다.
 *
 * 명세서를 만드는 그때의 날을 여기에 굳힌다. 창고(위드웍스)의 판매주문에도 같은
 * 날을 보낸다 — 저쪽 sales_orders.statement_date 가 그 자리다(2026-09-09 지시).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'statement_date')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->date('statement_date')->nullable()->after('withworks_ship_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'statement_date')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('statement_date');
        });
    }
};
