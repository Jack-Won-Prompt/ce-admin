<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withworks 가 알려 온 물류 사건을 남긴다.
 *
 * 두 가지 때문에 표로 남긴다.
 *
 * 하나는 같은 사건이 두 번 오는 것을 막기 위해서다. 웹훅은 응답이 늦거나 끊기면 다시 보내는
 * 것이 정상이라, 받는 쪽이 event_id 로 걸러야 한다.
 *
 * 다른 하나는 나중에 「언제 출고됐는지」를 따질 일이 생기기 때문이다. 주문 컬럼은 마지막
 * 상태만 남아서, 중간에 무엇이 언제 지나갔는지는 여기에만 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withworks_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();     // 보내는 쪽이 매기는 사건 고유번호
            $table->string('event', 50);                   // so.confirmed, so.shipped ...
            $table->string('ce_order_number', 50)->index();
            $table->string('so_no', 50)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 50)->nullable();
            $table->string('status_label', 100)->nullable();
            $table->json('payload');                       // 받은 그대로. 규격이 바뀌어도 원본은 남는다
            $table->timestamp('occurred_at')->nullable();  // 그쪽에서 일어난 시각
            $table->timestamps();                          // 우리가 받은 시각

            $table->index(['ce_order_number', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withworks_events');
    }
};
