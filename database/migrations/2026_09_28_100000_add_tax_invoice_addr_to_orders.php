<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 세금계산서 공급받는자 주소 (2026-09-28 지시).
 *
 * 종이 서식(별지 제11호)의 공급받는자 「사업장 주소」 칸이 늘 비어 있었다. 주소를
 * 어디에도 담아 두지 않아서다 — 상호ㆍ성명ㆍ등록번호는 신고한 값을 주문에 적어
 * 두는데 주소만 빠져 있었다.
 *
 * 환자 자료에서 그때그때 읽어 그리면 안 된다. 환자가 이사하면 예전에 발행한
 * 계산서를 다시 뽑았을 때 그 시절에 없던 주소가 찍힌다 — 신고한 종이와 어긋난다.
 * 그래서 다른 신고값과 같은 자리에 함께 적어 둔다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tax_invoice_addr', 200)->nullable()->after('tax_invoice_biz_no');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_invoice_addr');
        });
    }
};
