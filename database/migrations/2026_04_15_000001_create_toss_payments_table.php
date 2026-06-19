<?php
// database/migrations/2026_04_15_000001_create_toss_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toss_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique()->index();  // 주문 1:1
            $table->string('payment_key', 200)->unique()->nullable();   // 토스 paymentKey
            $table->string('toss_order_id', 100)->nullable();           // 토스 orderId (CE-ORD-xxx)
            $table->string('method', 50)->default('VIRTUAL_ACCOUNT');   // 결제수단
            $table->string('status', 50)->default('READY');             // 토스 상태값
            $table->unsignedInteger('amount')->default(0);              // 결제 금액 (원)
            $table->string('bank', 10)->nullable();                     // 은행 코드 (IBK, KB 등)
            $table->string('account_number', 50)->nullable();           // 가상계좌 번호
            $table->string('customer_name', 100)->nullable();           // 예금주명
            $table->timestamp('due_date')->nullable();                  // 입금 만료일시
            $table->timestamp('deposited_at')->nullable();              // 실제 입금 확인일시
            $table->json('raw_response')->nullable();                   // 토스 원본 응답
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toss_payments');
    }
};
