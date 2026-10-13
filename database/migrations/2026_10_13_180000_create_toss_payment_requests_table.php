<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 토스 결제창에 **무엇을 보냈는지** 적어 둔다 (2026-10-13 지시).
 *
 * 토스는 승인 응답에 `customerName` 을 돌려주지 않는다. 그래서 `toss_payments.raw_response`
 * 만으로는 「구매자명으로 무엇을 보냈나」를 셀 수 없었다 — 2026-10-12 에 카드ㆍ간편결제
 * 구매자명이 상호로 나간 일을 확인할 때, 우리 자료로는 세지 못하고 코드가 바뀐 시각으로
 * 가려 세어야 했다.
 *
 * 환자가 결제창을 여는 순간 한 줄 적는다. 승인까지 가지 않은 시도도 남는다 — 무엇을
 * 보냈는지는 결제가 되든 안 되든 같은 사실이다.
 *
 * 토스 주문번호(toss_order_id)로 toss_payments 와 이어 본다. 결제창을 다시 열면 그
 * 번호가 새로 나므로 한 주문에 여러 줄이 쌓인다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toss_payment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            /* 그때 만든 토스 주문번호 — 승인된 줄과 잇는 열쇠다 */
            $table->string('toss_order_id', 80)->nullable()->index();

            /* 환자가 고른 결제수단 (CARD · VIRTUAL_ACCOUNT …) */
            $table->string('method', 30)->nullable();
            $table->unsignedBigInteger('amount')->default(0);

            /* 보낸 그 값들 */
            $table->string('customer_name', 100)->nullable();
            $table->string('order_name', 120)->nullable();

            /* 누가 어디서 열었나 — 같은 링크를 여럿이 여는 일이 있다 */
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toss_payment_requests');
    }
};
