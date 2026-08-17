<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 샘플주문의 거래처는 고객(환자)이다.
 *
 * 처음에는 거래처를 글자로만 받았다. 그러면 같은 사람을 두 번 적을 때 이름이 갈리고,
 * 이 사람에게 샘플을 몇 번 보냈는지 셀 수 없다. 환자를 걸어 둔다.
 *
 * 이름은 그대로 남긴다 — 환자로 등록되지 않은 사람에게 보내는 일이 있고,
 * 환자 기록이 나중에 고쳐져도 그때 무엇으로 보냈는지가 흐려지면 안 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_orders', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('type')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sample_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
