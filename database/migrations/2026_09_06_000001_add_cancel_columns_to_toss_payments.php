<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 무른 자취를 적을 자리.
 *
 * 여태 돈을 돌려주는 일은 토스 콘솔에서 따로 했고, 그 자취는 반품 화면에 사람이
 * 손으로 옮겨 적었다. 두 곳에 나눠 적으면 언젠가 갈린다 — 실제로 물린 금액과
 * 화면에 적힌 금액이 다른 건이 남는다. 무른 것도 여기에 적는다.
 *
 * cancel_amount 는 누계다. 토스는 한 결제를 여러 번 나누어 무를 수 있어
 * (부분 반품이 두 번 일어나는 건이 있다) 마지막 한 번만 적으면 얼마가 남았는지
 * 알 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toss_payments', function (Blueprint $table) {
            $table->timestamp('canceled_at')->nullable()->after('deposited_at');
            $table->unsignedBigInteger('cancel_amount')->nullable()->after('canceled_at');
            $table->string('cancel_reason', 200)->nullable()->after('cancel_amount');
        });
    }

    public function down(): void
    {
        Schema::table('toss_payments', function (Blueprint $table) {
            $table->dropColumn(['canceled_at', 'cancel_amount', 'cancel_reason']);
        });
    }
};
