<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 운영 자료를 우리 쪽으로 옮겨 담을 자리 (2026-09-18 지시).
 *
 * 위드웍스 운영 DB 는 **읽기만 한다** — 고치지도 지우지도 않는다. 그래서 그쪽 표를
 * 그대로 쓰지 않고 우리 표에 옮겨 담는다. 화면이 그 자리를 보고, 다시 가져올 때는
 * 마지막으로 담은 번호 뒤부터만 읽는다.
 *
 * 칸은 **값이 든 것만** 가져왔다(지시). 원천에 있으나 한 줄도 채워지지 않은 칸은 두지
 * 않는다 — 빈 칸이 백 개 서 있으면 어느 것이 쓰이는지 알 수 없다.
 *
 *   ww_prescription_infos   warehouse.account_add_informations  99,695줄 · 66칸 (5칸 뺌)
 *   ww_customers            admin.accounts                      17,835줄 · 84칸 (34칸 뺌)
 *   ww_customer_addresses   warehouse.account_addresses         30,267줄 · 38칸 (11칸 뺌)
 *
 * 저쪽 id 는 `ww_id` 로 담는다. 우리 id 와 뜻이 달라 같은 이름으로 두면 반드시 헷갈리고,
 * 다시 가져올 때 「어디까지 담았나」를 재는 잣대가 바로 이 값이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* 처방전 정보 — 위드웍스의 「부가정보」다. udf1~udf50 이 본체이고,
           우리가 그쪽으로 보낼 때 쓰는 짝과 같은 자리다(PrescriptionController). */
        Schema::create('ww_prescription_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ww_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('to_account_id')->nullable();
            $table->unsignedBigInteger('ref_account_id')->nullable();
            $table->string('add_no', 255)->nullable();
            $table->string('descr', 1000)->nullable();
            $table->string('type', 255)->nullable();
            $table->date('reg_date')->nullable();
            $table->string('status', 255)->nullable();
            $table->unsignedBigInteger('so_account_id')->nullable();
            $table->string('five', 255)->nullable();
            $table->string('five_program', 255)->nullable();
            $table->string('privacy', 255)->nullable();
            $table->string('diverticulums', 255)->nullable();
            $table->string('udf1', 255)->nullable();
            $table->string('udf2', 255)->nullable();
            $table->string('udf3', 255)->nullable();
            $table->string('udf4', 255)->nullable();
            $table->string('udf5', 255)->nullable();
            $table->string('udf6', 255)->nullable();
            $table->string('udf7', 255)->nullable();
            $table->string('udf8', 255)->nullable();
            $table->string('udf9', 255)->nullable();
            $table->string('udf10', 255)->nullable();
            $table->string('udf11', 255)->nullable();
            $table->string('udf12', 255)->nullable();
            $table->string('udf13', 255)->nullable();
            $table->string('udf14', 255)->nullable();
            $table->string('udf15', 255)->nullable();
            $table->string('udf16', 255)->nullable();
            $table->string('udf17', 255)->nullable();
            $table->string('udf18', 255)->nullable();
            $table->string('udf19', 255)->nullable();
            $table->string('udf20', 255)->nullable();
            $table->string('udf21', 255)->nullable();
            $table->string('udf22', 255)->nullable();
            $table->string('udf23', 255)->nullable();
            $table->string('udf24', 255)->nullable();
            $table->string('udf25', 255)->nullable();
            $table->string('udf26', 255)->nullable();
            $table->string('udf27', 255)->nullable();
            $table->string('udf28', 255)->nullable();
            $table->string('udf29', 255)->nullable();
            $table->string('udf30', 255)->nullable();
            $table->string('udf31', 255)->nullable();
            $table->string('udf32', 255)->nullable();
            $table->string('udf33', 255)->nullable();
            $table->string('udf34', 255)->nullable();
            $table->string('udf35', 255)->nullable();
            $table->string('udf36', 255)->nullable();
            $table->string('udf37', 255)->nullable();
            $table->string('udf38', 255)->nullable();
            $table->string('udf39', 255)->nullable();
            $table->string('udf42', 255)->nullable();
            $table->string('udf43', 255)->nullable();
            $table->string('udf45', 255)->nullable();
            $table->string('udf48', 255)->nullable();
            $table->string('udf49', 255)->nullable();
            $table->string('udf50', 255)->nullable();
            $table->string('registrant_ip', 255)->nullable();
            $table->string('edit_user_ip', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();

            /* 언제 우리 쪽으로 담았는가 — 다시 가져온 줄을 가린다 */
            $table->timestamp('imported_at')->nullable();

            $table->unique('ww_id');
            $table->index('account_id');
            $table->index('to_account_id');
            $table->index('add_no');
            $table->index('reg_date');
        });

        /* 고객 정보 — 콜로플라스트(148659) 아래 거래처(account_type=30)만 담는다. */
        Schema::create('ww_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ww_id')->nullable();
            $table->string('biz_type', 20)->nullable();
            $table->string('cid', 20)->nullable();
            $table->string('control_call', 20)->nullable();
            $table->string('view_call', 20)->nullable();
            $table->string('lang_call', 20)->nullable();
            $table->integer('top_account_id')->nullable();
            $table->tinyInteger('multi_industry')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->tinyInteger('use_taskchain')->nullable();
            $table->tinyInteger('ship_alarm')->nullable();
            $table->string('account_code', 255)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->text('detailed_description')->nullable();
            $table->string('country', 255)->nullable();
            $table->string('lang_code', 255)->nullable();
            $table->integer('address_id')->nullable();
            $table->string('tax_address_id', 255)->nullable();
            $table->char('tax_address_use_yn', 1)->nullable();
            $table->string('email_1', 255)->nullable();
            $table->string('phone_1', 255)->nullable();
            $table->string('fax_1', 255)->nullable();
            $table->string('email_2', 255)->nullable();
            $table->string('phone_2', 255)->nullable();
            $table->string('fax_2', 255)->nullable();
            $table->string('tax_email', 50)->nullable();
            $table->string('erp_cd', 50)->nullable();
            $table->string('erp_nm', 50)->nullable();
            $table->string('customer_type', 5)->nullable();
            $table->string('resident_no', 50)->nullable();
            $table->string('business_type', 50)->nullable();
            $table->string('business_item', 50)->nullable();
            $table->string('account_type', 255)->nullable();
            $table->string('use_yn', 255)->nullable();
            $table->string('test_yn', 20)->nullable();
            $table->string('packaging_yn', 20)->nullable();
            $table->string('industry', 50)->nullable();
            $table->string('tax_yn', 20)->nullable();
            $table->string('special_yn', 20)->nullable();
            $table->string('sales_partner_yn', 20)->nullable();
            $table->string('hospital_account_required_yn', 1)->nullable();
            $table->string('auto_credit_yn', 1)->nullable();
            $table->string('rep_code', 50)->nullable();
            $table->string('rep_name', 100)->nullable();
            $table->string('rep_manager', 100)->nullable();
            $table->string('udf1', 100)->nullable();
            $table->string('udf2', 100)->nullable();
            $table->string('udf3', 100)->nullable();
            $table->string('udf4', 100)->nullable();
            $table->string('udf5', 100)->nullable();
            $table->string('udf6', 100)->nullable();
            $table->string('udf7', 100)->nullable();
            $table->string('udf8', 100)->nullable();
            $table->string('udf9', 100)->nullable();
            $table->string('udf10', 100)->nullable();
            $table->string('udf11', 100)->nullable();
            $table->string('udf12', 100)->nullable();
            $table->string('udf13', 100)->nullable();
            $table->string('udf14', 100)->nullable();
            $table->string('udf15', 100)->nullable();
            $table->string('udf16', 100)->nullable();
            $table->string('udf17', 100)->nullable();
            $table->string('udf18', 100)->nullable();
            $table->string('udf19', 100)->nullable();
            $table->string('udf20', 100)->nullable();
            $table->string('udf21', 255)->nullable();
            $table->string('udf22', 255)->nullable();
            $table->string('udf23', 255)->nullable();
            $table->date('close_flag_date')->nullable();
            $table->string('client_id', 255)->nullable();
            $table->string('manager1', 255)->nullable();
            $table->string('manager2', 255)->nullable();
            $table->string('manager1_position', 255)->nullable();
            $table->string('manager2_position', 255)->nullable();
            $table->string('manager1_phone_number', 255)->nullable();
            $table->string('manager2_phone_number', 255)->nullable();
            $table->string('registrant_ip', 255)->nullable();
            $table->string('edit_user_ip', 255)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('pi_del_flag', 1)->nullable();

            /* 언제 우리 쪽으로 담았는가 — 다시 가져온 줄을 가린다 */
            $table->timestamp('imported_at')->nullable();

            $table->unique('ww_id');
            $table->index('account_code');
            $table->index('account_name');
            $table->index('address_id');
        });

        /* 고객 주소 — 한 고객에 여럿이다(평균 1.75개). 대표 주소는 고객의
           address_id 가 가리킨다. */
        Schema::create('ww_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ww_id')->nullable();
            $table->string('address_code', 255)->nullable();
            $table->string('address_name', 255)->nullable();
            $table->string('address_type', 255)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('top_account_id')->nullable();
            $table->string('public_yn', 255)->nullable();
            $table->integer('priority')->nullable();
            $table->string('country', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('zipcode', 255)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('address_line_4', 255)->nullable();
            $table->string('address_line_3', 255)->nullable();
            $table->string('contact1', 255)->nullable();
            $table->string('contact2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->string('fax1', 255)->nullable();
            $table->string('fax2', 255)->nullable();
            $table->text('memo')->nullable();
            $table->string('udf1', 50)->nullable();
            $table->string('udf10', 50)->nullable();
            $table->string('if_status', 255)->nullable();
            $table->string('city_or_state', 255)->nullable();
            $table->decimal('latitude', 44, 30)->nullable();
            $table->decimal('longitude', 44, 30)->nullable();
            $table->string('use_yn', 255)->nullable();
            $table->string('test_yn', 20)->nullable();
            $table->string('registrant_ip', 255)->nullable();
            $table->string('edit_user_ip', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();

            /* 언제 우리 쪽으로 담았는가 — 다시 가져온 줄을 가린다 */
            $table->timestamp('imported_at')->nullable();

            $table->unique('ww_id');
            $table->index('account_id');
            $table->index('zipcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ww_customer_addresses');
        Schema::dropIfExists('ww_customers');
        Schema::dropIfExists('ww_prescription_infos');
    }
};
