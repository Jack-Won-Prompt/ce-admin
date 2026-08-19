<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 결제 전송 이력.
 *
 * 환자에게 「이 주문 값을 여기서 내십시오」라고 보낸 것을 한 줄로 남긴다. 무엇을 어떤
 * 방법으로 언제 보냈고, 냈는지 안 냈는지가 한 자리에 있어야 다시 보낼지 전화할지를
 * 정할 수 있다. 지금까지는 문자 이력에만 흩어져 있어 그 판단을 사람이 머리로 했다.
 *
 * 링크 주소는 토큰으로만 연다 — 주문번호로 열리면 번호를 바꿔 가며 남의 주문을 볼 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // 우리 결제 페이지를 여는 열쇠. 짧으면 찍어 볼 수 있어 넉넉히 둔다.
            $table->string('token', 64)->unique();

            // card · virtual · bank — 무엇으로 내라고 보냈는가
            $table->string('method', 20);
            $table->unsignedInteger('amount');

            // sent · paid · expired · cancelled · failed
            $table->string('status', 20)->default('sent');

            $table->string('channel', 20)->nullable();      // alimtalk · sms
            $table->string('receiver', 20)->nullable();     // 받은 번호 — 나중에 환자 번호가 바뀌어도 남는다
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // 낸 뒤에 토스가 준 것들 — 확인·취소는 이 값으로 한다
            $table->string('payment_key', 200)->nullable();
            $table->string('toss_order_id', 64)->nullable();

            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
