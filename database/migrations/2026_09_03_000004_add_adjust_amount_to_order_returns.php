<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 부분 건의 조정 금액을 적을 자리(2026-09-02 자 유형표).
 *
 * 표는 부분 교환ㆍ부분 반품ㆍ자격 변경 셋에 「조정 필요」라 적었다. 지금은 부분 건을
 * 만나면 「최종 청구분에 반영합니다」라 적고 지나간다 — 얼마로 반영할지는 어디에도
 * 남지 않아, 나중에 청구할 사람이 원 주문과 반품 줄을 놓고 다시 셈해야 했다.
 *
 * 두 칸을 둔다.
 *   adjust_amount    조정 뒤 남는 금액(원). 되돌린 몫을 뺀 값이다.
 *   adjust_direction 'refund' 돌려준다 · 'charge' 더 받는다.
 *
 * 자격 변경은 방향이 둘 다 될 수 있다 — 일반에서 차상위경감으로 바뀌면 돌려주고,
 * 반대로 바뀌면 더 받는다. 여태 환불 한쪽만 있었다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->integer('adjust_amount')->nullable()->after('adjust_so_no');
            $table->string('adjust_direction', 10)->nullable()->after('adjust_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn(['adjust_amount', 'adjust_direction']);
        });
    }
};
