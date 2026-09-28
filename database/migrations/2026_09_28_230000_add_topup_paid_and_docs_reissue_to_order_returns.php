<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 차액 입금과 증빙 재발행 (2026-09-28 지시).
 *
 * 「금액 변경이 없으면 증빙은 손대지 않고, 금액이 바뀐 건은 고객이 결제해서 웹훅으로
 * 전달받으면 증빙을 전부 다시 발행한다.」
 *
 * 차액 결제를 **접수에 적는다.** toss_payments 는 한 주문 한 줄이고 order_id 가
 * 유일이라, 차액 결제가 들어오면 원 결제 줄을 덮어쓴다 — 37,500원 결제가 4,500원으로
 * 바뀌고 원 결제키가 사라져, 받은 돈이 4,500원으로 읽히고 원 결제를 무를 수도 없게
 * 된다. 차액은 여기에 따로 담아 원 결제를 건드리지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            /* 고객이 차액을 실제로 낸 자취 */
            $table->timestamp('topup_paid_at')->nullable()->after('topup_sent_at');
            $table->string('topup_payment_key', 200)->nullable()->after('topup_paid_at');
            $table->integer('topup_amount')->nullable()->after('topup_payment_key');

            /* 증빙을 다시 낸 자취 — 언제ㆍ누가ㆍ무엇이 되었나 */
            $table->timestamp('docs_reissued_at')->nullable()->after('topup_amount');
            $table->unsignedBigInteger('docs_reissued_by')->nullable()->after('docs_reissued_at');
            $table->string('docs_reissue_note', 500)->nullable()->after('docs_reissued_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn(['topup_paid_at', 'topup_payment_key', 'topup_amount',
                                'docs_reissued_at', 'docs_reissued_by', 'docs_reissue_note']);
        });
    }
};
