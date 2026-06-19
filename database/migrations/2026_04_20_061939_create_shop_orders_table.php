<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_order_id')->unique()->comment('ce-shop orders.id');
            $table->string('order_number', 50)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_company', 100)->nullable();
            $table->json('items');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('delivery_method', 20)->nullable();
            $table->string('delivery_name', 100)->nullable();
            $table->string('delivery_phone', 30)->nullable();
            $table->string('delivery_zipcode', 10)->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('delivery_note')->nullable();
            $table->text('buyer_note')->nullable();
            $table->string('status', 30)->default('confirmed');
            $table->string('withworks_so_no', 50)->nullable();
            $table->unsignedBigInteger('withworks_so_id')->nullable();
            $table->text('admin_memo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
