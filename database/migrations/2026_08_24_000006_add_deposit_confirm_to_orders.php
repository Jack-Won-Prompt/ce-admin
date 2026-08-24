<?php
// 담당자가 눈으로 확인한 입금.
//
// 지금 「선택 입금확인」은 토스에 물어보는 일이다. 그래서 가상계좌를 발급하지 않은 건,
// 토스 밖에서 들어온 계좌이체ㆍ현금, 웹훅이 유실된 건은 통장에서 확인해도 화면에 적을
// 자리가 없어 영영 「대기중」에 남았다.
//
// toss_payments 가 아니라 주문에 적는다. 가상계좌를 발급하지 않은 건에는 그 행 자체가
// 없고, 억지로 만들면 「토스가 발급한 계좌」와 「사람이 확인한 입금」이 한 표에 섞인다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders') || Schema::hasColumn('orders', 'deposit_confirmed_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('deposit_confirmed_at')->nullable()->after('patient_copay');
            $table->unsignedBigInteger('deposit_confirmed_by')->nullable()->after('deposit_confirmed_at');
            // 실제로 들어온 금액. 기본은 청구액이고, 다르면 그 값을 적는다.
            $table->unsignedBigInteger('deposit_amount')->nullable()->after('deposit_confirmed_by');
            // 무엇을 보고 확인했는지 — 입금자명ㆍ통장 적요
            $table->string('deposit_note', 200)->nullable()->after('deposit_amount');

            $table->index('deposit_confirmed_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'deposit_confirmed_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['deposit_confirmed_at']);
            $table->dropColumn(['deposit_confirmed_at', 'deposit_confirmed_by', 'deposit_amount', 'deposit_note']);
        });
    }
};
