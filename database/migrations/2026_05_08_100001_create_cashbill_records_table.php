<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbill_records', function (Blueprint $table) {
            $table->id();
            $table->string('corp_num', 20)->comment('사업자번호');
            $table->string('mgt_key', 24)->comment('관리번호');
            $table->string('item_key', 50)->nullable()->comment('팝빌 내부 키');

            // 거래 구분
            $table->string('trade_type', 20)->nullable()->comment('승인거래|취소거래');
            $table->string('trade_usage', 20)->nullable()->comment('소득공제용|지출증빙용');
            $table->string('taxation_type', 20)->nullable()->comment('과세|비과세');

            // 금액
            $table->bigInteger('total_amount')->default(0)->comment('합계금액');
            $table->bigInteger('supply_cost')->default(0)->comment('공급가액');
            $table->bigInteger('tax')->default(0)->comment('부가세');
            $table->bigInteger('service_fee')->default(0)->comment('봉사료');

            // 일시
            $table->string('issue_dt', 14)->nullable()->comment('발행일시 YYYYMMDDHHmmss');
            $table->string('trade_dt', 14)->nullable()->comment('거래일시');
            $table->string('trade_date', 8)->nullable()->comment('거래일자 YYYYMMDD');
            $table->string('reg_dt', 14)->nullable()->comment('등록일시');

            // 상태
            $table->smallInteger('state_code')->default(0)->comment('100임시|200대기|300발행|400취소');
            $table->string('state_dt', 14)->nullable()->comment('상태변경일시');
            $table->string('state_memo', 300)->nullable();

            // 식별 / 고객
            $table->string('identity_num', 30)->nullable()->comment('신분확인번호');
            $table->string('customer_name', 100)->nullable()->comment('고객명');
            $table->string('item_name', 200)->nullable()->comment('품목명');
            $table->string('order_number', 50)->nullable()->comment('주문번호');
            $table->string('email', 100)->nullable();
            $table->string('hp', 30)->nullable();

            // 국세청
            $table->string('confirm_num', 50)->nullable()->comment('국세청 승인번호');
            $table->string('org_confirm_num', 50)->nullable()->comment('원본 국세청 승인번호(취소거래)');
            $table->string('org_trade_date', 8)->nullable()->comment('원본 거래일자(취소거래)');
            $table->tinyInteger('nts_result')->nullable()->comment('0전송전|1전송중|2성공|3실패');
            $table->string('nts_result_dt', 14)->nullable();
            $table->string('nts_result_code', 10)->nullable();
            $table->string('nts_result_message', 300)->nullable();
            $table->string('nts_send_dt', 14)->nullable();

            // 가맹점
            $table->string('franchise_corp_num', 20)->nullable();
            $table->string('franchise_corp_name', 100)->nullable();
            $table->string('franchise_ceo_name', 50)->nullable();
            $table->string('franchise_addr', 300)->nullable();
            $table->string('franchise_tel', 30)->nullable();

            $table->timestamp('synced_at')->nullable()->comment('마지막 팝빌 동기화 시각');
            $table->timestamps();

            $table->unique(['corp_num', 'mgt_key']);
            $table->index('trade_dt');
            $table->index('state_code');
            $table->index('nts_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbill_records');
    }
};
