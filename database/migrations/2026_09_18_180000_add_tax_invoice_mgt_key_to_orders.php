<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 세금계산서 문서번호를 적어 둔다 (2026-09-18 운영 시험에서 드러남).
 *
 * 여태 문서번호를 「TI + 발행일 + 주문번호」로 그때그때 다시 만들어 썼다. 그래서 같은
 * 날 취소하고 다시 내면 **같은 번호가 되어** 팝빌이 「동일한 공급자 문서번호가 사용
 * 중입니다([-11001038])」로 거절했다 — 정정으로 금액이 바뀐 건이 새 금액으로 영영
 * 나가지 못했다.
 *
 * 번호를 적어 두면 두 가지가 풀린다. 새로 낼 때는 겹치지 않는 번호를 만들 수 있고,
 * 취소할 때는 그때 쓴 번호를 그대로 찾아 부를 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tax_invoice_mgt_key', 24)->nullable()->after('tax_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_invoice_mgt_key');
        });
    }
};
