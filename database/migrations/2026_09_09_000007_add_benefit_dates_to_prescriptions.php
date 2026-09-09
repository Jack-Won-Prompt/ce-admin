<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사용 개시일ㆍ급여 종료일 (2026-09-08 확인요청 10쪽 · 2026-09-09 확정).
 *
 * 여태 이 둘은 제 자리가 없었다. 화면의 「사용 시작일」ㆍ「급여 종료일」이 거래처의
 * 건보위임동의 기간(patients.nhis_agree_start/end)을 그대로 빌려 쓰고 있었고,
 * 같은 값이 바로 아래 「건보위임동의 시작일ㆍ종료일」에도 그대로 비쳤다.
 *
 * 그런데 둘은 규칙이 다르다.
 *   건보위임동의 종료일 = 시작일 + 5년 - 1일   (공단이 정한 최장 위임기간)
 *   급여 종료일        = 결제일 + 총 처방일수  (이 건을 언제까지 쓰는가)
 *
 * 한 값이 두 규칙을 만족할 수 없다. 이 건에 딸린 값이므로 처방전에 제 칸을 준다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'benefit_end_date')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $t) {
            $t->date('use_start_date')->nullable()->after('buy_date');
            $t->date('benefit_end_date')->nullable()->after('use_start_date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prescriptions', 'benefit_end_date')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $t) {
            $t->dropColumn(['use_start_date', 'benefit_end_date']);
        });
    }
};
