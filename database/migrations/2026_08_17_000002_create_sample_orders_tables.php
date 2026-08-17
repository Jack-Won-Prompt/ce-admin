<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CE 샘플판매주문.
 *
 * 처방전에서 나오는 판매(5001)와 달리, 샘플은 처방 없이 거래처·환자에게 내보낸다.
 * 그래서 주문(orders)에 얹지 않고 따로 둔다 — 얹으면 청구·정산이 샘플까지 셈에 넣는다.
 *
 * 위드웍스 판매유형: 판매 6001 · 취소 6004 · 반품 6005 · 교환 6006.
 * 되돌리는 것은 판매와 코드가 다르다 — 창고가 하는 일이 다르기 때문이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sample_no', 30)->unique();      // 우리 번호 — 고객·창고와 맞출 때 쓴다
            $table->string('type', 10)->default('6001');    // 6001 판매 · 6004 취소 · 6005 반품 · 6006 교환

            // 받는 곳 — 거래처일 수도 환자일 수도 있다
            $table->string('account_name', 100)->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('address_detail', 200)->nullable();

            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->string('purpose', 200)->nullable();     // 무엇에 쓰는 샘플인지
            $table->string('note', 500)->nullable();

            $table->string('status', 20)->default('draft'); // draft · sent · shipped · cancelled
            $table->integer('total_qty')->default(0);
            $table->integer('total_amount')->default(0);

            // 위드웍스 — 넘긴 뒤 받는 값
            $table->string('withworks_so_no', 50)->nullable();
            $table->unsignedBigInteger('withworks_so_id')->nullable();
            $table->string('withworks_status', 50)->nullable();
            $table->string('withworks_status_label', 100)->nullable();
            $table->timestamp('withworks_sent_at')->nullable();
            $table->string('withworks_error', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('withworks_so_no');
        });

        Schema::create('sample_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_order_id')->constrained()->cascadeOnDelete();
            $table->string('product_code', 50);
            $table->string('product_name', 200);
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('amount')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sample_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_order_items');
        Schema::dropIfExists('sample_orders');
    }
};
