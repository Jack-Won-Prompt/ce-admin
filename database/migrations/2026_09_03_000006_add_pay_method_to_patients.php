<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 거래처가 어떻게 내는 사람인지 적어 둔다(2026-09-03 지시).
 *
 * 여태 결제 방식은 시스템 설정 하나로 정해져, 어느 환자든 같은 안내가 나갔다.
 * 사람마다 다르다 — 카드로 내는 사람이 있고 계좌로 내는 사람이 있다.
 *
 * 한 번 고르면 그 사람 것으로 남는다. 다음 주문에서 상세 목록 탭이 그 값으로
 * 열리고, 담당자가 매번 다시 고르지 않는다.
 *
 * 발급한 가상계좌도 함께 적는다. 고객이 문자를 지우고 「계좌번호가 뭐였죠」라
 * 물으면 담당자가 다시 보내 줄 수 있어야 하는데, 지금은 볼 곳이 없어 새로
 * 발급하고 있었다 — 그러면 앞의 계좌로 들어온 돈이 뜨게 된다.
 *
 * 토스 가상계좌는 금액과 유효시간(72시간)에 묶여 발급된다. 그래서 이 자취는
 * 「그 주문의 그 계좌」이지, 다음 주문에 그대로 쓸 수 있는 것이 아니다.
 * 되살리는 것은 같은 주문 안에서다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // 이 사람이 고른 결제 방식 — card(링크페이) · virtual(가상계좌) · bank(무통장)
            $table->string('pay_method', 20)->nullable()->after('remitter_name');

            // 마지막으로 발급한 가상계좌 — 다시 보내 줄 때 읽는다
            $table->string('va_bank', 40)->nullable()->after('pay_method');
            $table->string('va_account', 40)->nullable()->after('va_bank');
            $table->string('va_holder', 60)->nullable()->after('va_account');
            $table->dateTime('va_due_at')->nullable()->after('va_holder');
            $table->unsignedBigInteger('va_order_id')->nullable()->after('va_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['pay_method', 'va_bank', 'va_account', 'va_holder', 'va_due_at', 'va_order_id']);
        });
    }
};
