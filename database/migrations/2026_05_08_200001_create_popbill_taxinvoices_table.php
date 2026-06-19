<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popbill_taxinvoices', function (Blueprint $table) {
            $table->id();
            $table->string('corp_num', 20);
            $table->string('mgt_key_type', 10)->default('SELL');
            // 관리번호 (SELL=invoicerMgtKey, BUY=invoiceeMgtKey, TRUSTEE=trusteeMgtKey)
            $table->string('mgt_key', 100);

            // 팝빌 상태
            $table->string('item_key', 50)->nullable();
            $table->integer('state_code')->default(0);
            $table->string('state_dt', 20)->nullable();

            // 세금계산서 기본 정보
            $table->string('tax_type', 20)->nullable();       // ValueAdded / ZeroTax / FreeTax
            $table->string('purpose_type', 20)->nullable();   // Receipt / Request
            $table->string('issue_type', 20)->nullable();     // Normal / Blank
            $table->string('write_date', 8)->nullable();      // YYYYMMDD
            $table->string('issue_dt', 20)->nullable();       // YYYYMMDDHHmmss

            // 공급자
            $table->string('invoicer_corp_num', 20)->nullable();
            $table->string('invoicer_corp_name', 100)->nullable();
            $table->string('invoicer_ceo_name', 50)->nullable();

            // 공급받는자
            $table->string('invoicee_corp_num', 20)->nullable();
            $table->string('invoicee_corp_name', 100)->nullable();
            $table->string('invoicee_ceo_name', 50)->nullable();

            // 금액
            $table->bigInteger('supply_cost_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total_amount')->default(0);

            // 국세청 승인번호
            $table->string('nts_confirm_num', 50)->nullable();

            // 최종 상태 여부 (400=국세청완료, 500=취소 → 더 이상 sync 불필요)
            $table->boolean('is_final')->default(false);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(['corp_num', 'mgt_key_type', 'mgt_key']);
            $table->index(['corp_num', 'mgt_key_type', 'write_date']);
            $table->index(['is_final', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popbill_taxinvoices');
    }
};
