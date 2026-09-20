<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 현금영수증 문서번호를 적어 둔다 (2026-09-20 주문 정정 시험에서 드러남).
 *
 * 세금계산서는 2026-09-18 에 같은 일을 겪고 고쳤는데(tax_invoice_mgt_key) 현금영수증은
 * 그대로 남아 있었다. 문서번호를 「CR + 발행일 + 주문」으로 그때그때 다시 만들어 써서,
 * 같은 날 취소하고 다시 내면 같은 번호가 되어 팝빌이 「동일한 문서번호(MgtKey)의
 * 현금영수증이 존재합니다([-14001019])」로 거절했다 — 정정으로 금액이 바뀐 건은
 * 새 금액의 현금영수증이 영영 나가지 못하고 양식만 첨부됐다.
 *
 * 번호를 적어 두면 새로 낼 때 겹치지 않는 번호를 만들 수 있고, 취소할 때는 그때 쓴
 * 번호에서 취소 번호를 만들어 그것도 겹치지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cash_receipt_mgt_key', 24)->nullable()->after('cash_receipt_no');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_receipt_mgt_key');
        });
    }
};
