<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 교환 · 반품 · 취소.
 *
 * 지금까지는 주문을 취소하면 주문 상태만 cancelled 로 바뀌고 끝이었다. 왜 취소됐는지,
 * 물건은 돌아왔는지, 돈은 돌려줬는지가 어디에도 남지 않았다. 교환은 아예 다룰 자리가 없어
 * 취소하고 새로 주문하는 식으로 처리됐고, 그러면 원 주문과의 연결이 끊긴다.
 *
 * 한 건이 여러 단계를 지나며 상태가 바뀌므로 그 자취를 따로 남긴다(order_return_logs).
 * 단계마다 사유를 받는 것이 요청이었는데, 마지막 사유만 남기면 왜 그렇게 흘러왔는지
 * 나중에 알 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();        // 접수번호 — 고객에게 알려 주는 번호
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                        // exchange · return · cancel
            $table->string('status', 30);

            // 신청 사유 — 배송비를 누가 무는지가 여기서 갈린다
            $table->string('reason_code', 30);
            $table->string('reason_text', 500)->nullable();
            $table->string('shipping_burden', 20)->nullable(); // customer · company

            // 수거 — 취소는 보낸 물건이 없어 비어 있다
            $table->string('collect_method', 20)->nullable();  // courier · self
            $table->string('collect_tracking_no', 50)->nullable();

            // 교환이면 무엇으로 바꿔 보내는지, 어디로 보내는지
            $table->string('exchange_product', 200)->nullable();
            $table->integer('exchange_quantity')->nullable();
            $table->string('reship_address', 300)->nullable();

            // 반품·취소면 어떻게 돌려주는지
            $table->string('refund_method', 20)->nullable();   // account · card · va
            $table->string('refund_bank', 50)->nullable();
            $table->string('refund_account', 50)->nullable();
            $table->string('refund_holder', 50)->nullable();
            $table->integer('refund_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index('order_id');
        });

        /* 단계마다 사유를 받는다. 마지막 사유만 남기면 왜 그렇게 흘러왔는지 알 수 없다. */
        Schema::create('order_return_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_logs');
        Schema::dropIfExists('order_returns');
    }
};
