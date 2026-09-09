<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주 연락처 — 환자와 보호자 가운데 어느 번호로 먼저 거는가 (2026-09-08 확인요청 4쪽).
 *
 * 전화번호가 둘인데 어느 것이 먼저인지 적어 둘 자리가 없었다. 상담사는 위에 있는
 * 번호부터 걸었고, 미성년이거나 본인이 받지 못하는 건에서는 늘 헛걸음이었다.
 *
 * 'mobile' = 환자 번호, 'guardian' = 보호자 번호. 비어 있으면 정하지 않은 것이다 —
 * 예전에 등록한 거래처를 함부로 「환자」로 단정하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'main_contact')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('main_contact', 10)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'main_contact')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('main_contact');
        });
    }
};
