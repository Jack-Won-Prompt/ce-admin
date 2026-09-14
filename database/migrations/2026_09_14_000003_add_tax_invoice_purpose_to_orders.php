<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 세금계산서를 「영수」로 신고했는지 「청구」로 신고했는지 적어 둔다 (2026-09-14 지시).
 *
 * 종이 서식은 여태 이 값을 주문의 입금확인에서 되짚어 그렸다. 그런데 그 입금은
 * 환자에게 받는 본인부담금이고, 세금계산서가 다루는 돈은 공단ㆍ지자체에서 받을
 * 기관부담금이다 — 서로 다른 돈이라 되짚은 값이 맞을 리가 없었다.
 *
 * 앞으로는 신고할 때 정한 값을 그대로 적어 두고 서식이 그것을 읽는다. 이미 발행한
 * 건은 이 칸이 비어 있는데, 그때 팝빌로 나간 값이 「영수」였으므로 서식도 그대로
 * 영수로 그린다(TaxInvoiceForm 의 기본값). 국세청에 신고된 것과 종이가 어긋나서는
 * 안 된다 — 그 건들을 청구로 바꾸려면 수정세금계산서를 내야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'tax_invoice_purpose')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('tax_invoice_purpose', 10)->nullable()->after('tax_invoice_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'tax_invoice_purpose')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_invoice_purpose');
        });
    }
};
