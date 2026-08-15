<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 지자체 청구 등기 발송 기록.
 *
 * 공단은 사이트에 올리지만 지자체는 서류를 등기로 보낸다. 보냈다는 증거가 남아야 나중에
 * 「안 왔다」는 말을 받았을 때 댈 것이 있다. 등기번호와 발송 영수증이 그것이다.
 *
 * 주문 컬럼이 아니라 표로 두는 이유는 재발송 때문이다. 반송되거나 서류가 빠져 다시 보내는
 * 일이 있는데, 컬럼이면 앞서 보낸 기록이 덮여 사라진다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_claim_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('local_gov', 60)->nullable();       // 보낸 곳 — 그때의 관할이 남아야 한다
            $table->string('registered_no', 50)->nullable();   // 등기번호
            $table->date('sent_date')->nullable();
            $table->string('receipt_path', 255)->nullable();   // 발송 영수증 이미지·PDF
            $table->string('receipt_name', 190)->nullable();
            $table->string('memo', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'sent_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_claim_dispatches');
    }
};
