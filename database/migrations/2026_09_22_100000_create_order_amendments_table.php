<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 정정 이력 — 정정 한 번에 한 줄, 「고치기 전」을 담는다
 * (2026-09-22 확인요청 2ㆍ4쪽).
 *
 * 정정은 주문을 제자리에서 고친다. 그래서 어느 화면을 열어도 **마지막 내용 한 줄**만
 * 서고, 재무는 「원래 얼마였고 얼마가 물러났고 지금이 얼마인가」를 볼 수 없었다 —
 * 확인요청 4쪽의 「원 주문 라인 / 취소 라인 / 정정 라인 있어야 하는데 일부 라인만
 * 보임」이 그것이다.
 *
 * 고치기 전 값을 여기에 남겨 두면 세 줄이 선다.
 *
 *   원 주문 라인   이 표의 줄 (정정 전 금액ㆍ수량)
 *   취소 라인      같은 줄을 음수로 — 그 금액이 물러났다
 *   정정 라인      주문 자체 (지금 값)
 *
 * 창고 판매번호도 함께 적는다. 정정은 저쪽 판매주문을 취소하고 새로 세우므로
 * (OrderAmendService), 그 줄이 어느 판매번호의 것이었는지를 알아야 창고 화면과
 * 맞춰 볼 수 있다.
 *
 * 지난 정정은 소급하지 않는다 — 고치기 전 값이 어디에도 남아 있지 않아 만들어 낼
 * 수 없다. 이 표가 선 뒤의 정정부터 쌓인다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_amendments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // 이 주문의 몇 번째 정정인가 — 1 부터
            $t->unsignedInteger('seq')->default(1);

            /* ── 고치기 전 ──────────────────────────────────────
               주문의 요약 칸을 그대로 뜬다. 품목 줄까지 뜨지는 않는다 — 재무가 보는
               것은 「얼마가 물러났나」이고, 그것은 요약 칸으로 셀 수 있다. */
            $t->string('product_code', 50)->nullable();
            $t->string('product_name', 255)->nullable();
            $t->unsignedInteger('quantity')->default(0);
            $t->bigInteger('unit_price')->default(0);
            $t->bigInteger('patient_copay')->default(0);
            $t->bigInteger('nhis_amount')->default(0);
            $t->bigInteger('total_amount')->default(0);

            // 물러난 창고 판매번호 — 정정은 저쪽 판매주문을 취소하고 새로 세운다
            $t->string('withworks_so_no', 50)->nullable();

            /* 그때 나가 있던 증빙 — 정정하며 함께 물린다(OrderCancelService::증빙무르기).
               현금영수증 화면이 「정정 전 발행 / 취소 / 정정 후 발행」 세 줄을 세울 때
               어느 것이 정정 전 것인지 가리는 데 쓴다. */
            $t->string('cash_receipt_no', 50)->nullable();
            $t->bigInteger('cash_receipt_amount')->default(0);
            $t->string('tax_invoice_no', 50)->nullable();

            $t->string('reason', 255)->nullable();

            $t->timestamp('amended_at')->nullable();
            $t->foreignId('amended_by')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamps();

            $t->index(['order_id', 'seq']);
            $t->index('amended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_amendments');
    }
};
