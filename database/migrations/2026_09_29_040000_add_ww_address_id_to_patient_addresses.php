<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 옮겨 온 주소가 어느 줄에서 왔는지 적어 둔다 (2026-09-29 지시).
 *
 * 한 사람에게 주소가 여럿이다(평균 1.73개ㆍ많게는 17개). 원천 번호를 적어 두지
 * 않으면 다시 옮길 때 같은 주소가 한 벌 더 쌓인다 — 이력 화면에 같은 주소가 두 줄로
 * 서고, 어느 것이 최신인지 알 수 없게 된다.
 *
 * 유일 색인을 건다. 「같은 줄은 한 번만」을 코드가 아니라 표가 지키게 하려는 것이다 —
 * 코드로만 막으면 두 번 돌리는 순간을 놓친다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patient_addresses')) {
            return;
        }

        Schema::table('patient_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_addresses', 'ww_address_id')) {
                $table->unsignedBigInteger('ww_address_id')->nullable()->unique()
                      ->comment('위드웍스 ww_customer_addresses.ww_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patient_addresses')) {
            return;
        }

        Schema::table('patient_addresses', function (Blueprint $table) {
            $table->dropUnique(['ww_address_id']);
            $table->dropColumn('ww_address_id');
        });
    }
};
