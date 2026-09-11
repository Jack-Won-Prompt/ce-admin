<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 명단에 딸려 오는 값을 담을 자리 (2026-09-11 「위임 데이터」).
 *
 * 받은 명단(위임 필요 리스트)에는 이름ㆍ번호 말고도 열 칸이 더 있다. 보낼 때는
 * 쓰지 않지만, 누구에게 왜 보내는지를 가리는 값들이라 함께 들고 있어야 한다 —
 * 「다음 재구매가 언제인가」를 보고 보낼 차례를 정하고, 「마지막 판매상태」로
 * 진행중인 건을 가린다.
 *
 * 남의 표를 참조하지 않는다는 원칙은 그대로다. 적힌 대로 글자와 날짜로만 담는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            // 원본 명단의 줄 번호 — 되짚을 때 명단과 맞춰 보는 열쇠다
            $table->unsignedInteger('src_no')->nullable()->after('id');

            /* 「거래처명」은 판매처(대리점)다. 서명하는 사람은 「환자거래처 명」이고
               그것이 customer_name 에 들어간다 — 두 낱말이 닮아 헷갈리기 쉽다. */
            $table->string('dealer_name', 100)->nullable()->after('customer_name');

            $table->date('next_repurchase_at')->nullable()->after('phone2');
            $table->date('last_register_at')->nullable()->after('next_repurchase_at');
            $table->unsignedSmallInteger('rx_days')->nullable()->after('last_register_at');
            $table->date('last_confirm_at')->nullable()->after('rx_days');

            /* 명단이 적어 보낸 말 그대로 담는다. 우리 status(발송 흐름)와 이름이
               겹치므로 src_ 를 붙여 가른다. */
            $table->string('src_status', 30)->nullable()->after('last_confirm_at');
            $table->string('rx_type', 30)->nullable()->after('src_status');
            $table->string('benefit_class', 30)->nullable()->after('rx_type');
            $table->string('last_sale_status', 30)->nullable()->after('benefit_class');

            $table->index('dealer_name');
            $table->index('next_repurchase_at');
        });
    }

    public function down(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->dropIndex(['dealer_name']);
            $table->dropIndex(['next_repurchase_at']);
            $table->dropColumn([
                'src_no', 'dealer_name', 'next_repurchase_at', 'last_register_at',
                'rx_days', 'last_confirm_at', 'src_status', 'rx_type',
                'benefit_class', 'last_sale_status',
            ]);
        });
    }
};
