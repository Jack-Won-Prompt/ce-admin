<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 상병 구분과 요류역학검사 확인사항 (화면 확정요청 2026-08-27, 12·13쪽).
 *
 * 상병 구분(1 · 2-1 · 2-2 · 3)은 위드웍스와 맞춰 보는 값이다. 예전에 그 값을 담던
 * disease_class 는 지금 상병명을 담고 있어(그때 요청으로 목록을 걷고 이름을 적게 했다)
 * 거기에 다시 넣으면 적어 둔 이름이 지워진다 — 칸을 따로 둔다.
 *
 * 확인사항은 등록 신청서(별지 제4호서식)가 요구하는 소견이다. 요류역학검사 결과가
 * 다섯 가운데 하나 이상이거나, 선천기형으로 검사 자체가 불가해야 급여 대상이 된다.
 * 지금까지는 검사일만 적었고 무엇이 확인됐는지는 어디에도 남지 않았다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // 1 · 2-1 · 2-2 · 3
            $table->string('disease_grade', 10)->nullable()->after('disease_class');

            /* 확인사항 — 고른 것들을 쉼표로 잇는다. 표를 따로 두기에는 값이 여섯 개뿐이고,
               한 처방전에 한 벌만 붙는다. */
            $table->string('uro_findings', 200)->nullable()->after('uro_date');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['disease_grade', 'uro_findings']);
        });
    }
};
