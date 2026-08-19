<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 샘플을 달라고 한 사람 — 영업 담당자.
 *
 * 지금은 등록한 사람(created_by)만 남는데, 대개 사무실에서 대신 넣어 준다. 그러면
 * 나중에 「이 샘플 누가 요청했나」를 물을 곳이 없다.
 *
 * 이름을 함께 적어 둔다. 계정이 지워지거나 이름이 바뀌어도 그때 누구였는지는 남아야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_orders', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable()->after('patient_id')
                  ->constrained('users')->nullOnDelete();
            $table->string('requester_name', 100)->nullable()->after('requester_id');
        });
    }

    public function down(): void
    {
        Schema::table('sample_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requester_id');
            $table->dropColumn('requester_name');
        });
    }
};
