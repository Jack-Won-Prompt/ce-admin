<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 옮겨 온 처방전이 원천의 어느 줄에서 왔는지 적어 둔다 (2026-09-29 지시).
 *
 * 주소와 같은 뜻이다 — 유일 색인을 걸어 「같은 줄은 한 번만」을 표가 지키게 한다.
 * 처방전은 다섯 만 장이 넘으므로 두 번 돌리는 순간을 코드로만 막으면 놓친다.
 *
 * rx_number 에 원천 번호(ADD0072363)를 담으므로 그것으로도 가릴 수 있다. 그래도 칸을
 * 따로 두는 까닭은 rx_number 는 사람이 고칠 수 있는 화면 값이고, 이 칸은 어디서 왔는지를
 * 적는 자리라서다. 값이 섞이면 되짚을 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'ww_add_id')) {
                $table->unsignedBigInteger('ww_add_id')->nullable()->unique()
                      ->comment('위드웍스 account_add_informations.id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropUnique(['ww_add_id']);
            $table->dropColumn('ww_add_id');
        });
    }
};
