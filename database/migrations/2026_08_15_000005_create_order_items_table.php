<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 품목.
 *
 * 지금까지 주문은 제품을 한 줄로 눌러 담았다 — 첫 제품의 이름·코드만 남기고 수량은 합쳐서
 * (90 + 30 = 120) 넣고, 나머지 이름은 note 에 문자열로 적었다. 그래서
 *   · 주문 관리 화면에서 두 번째 제품이 보이지 않고
 *   · 공단에 보내는 '제품 구매내역' 서류가 늘 비어 나갔다
 *     (collectFaxFiles 가 order->items 를 보는데 그런 관계가 없었다)
 *
 * 처방(prescription_items)과 따로 두는 이유 — 처방한 것과 실제로 주문한 것이 늘 같지는 않다.
 * 수량을 줄여 주문하거나 일부만 보내는 일이 있고, 구매내역 서류는 '주문한 것' 이어야 한다.
 *
 * orders 의 product_name·quantity 는 그대로 둔다. 목록 화면들이 그 칸을 쓰고 있고,
 * 이제 그 값은 품목의 요약이 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('product_name', 200);
            $table->string('product_code', 50)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('product_price', 12, 2)->nullable();
            $table->decimal('insurance_price', 12, 2)->nullable();
            $table->decimal('nhis_amount', 12, 2)->nullable();
            $table->decimal('patient_copay', 12, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->index(['order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
