<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 평문 주민등록번호 컬럼 제거 — P0-1 마지막 단계.
 *
 * ⚠ 이 마이그레이션은 다른 것들과 함께 자동으로 돌리면 안 된다.
 *    반드시 아래를 먼저 통과시킨 뒤 경로를 지정해 따로 실행한다.
 *
 *      php artisan rrn:backfill --verify      → 잔여 0 / 불일치 0
 *      (애플리케이션 코드 전환 배포 완료 + 정상 동작 확인)
 *      php artisan migrate --path=database/migrations/2026_08_10_100000_drop_plain_resident_no_columns.php
 *
 * 되돌릴 수 없다. down() 은 컬럼만 되살릴 뿐 값은 복구하지 못한다.
 * 파일명 날짜를 2026-08-10 으로 둔 것도 통상 배포에 섞여 들어가지 않게 하려는 것이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 안전장치 — 이관되지 않은 행이 하나라도 있으면 지우지 않는다
        $residual = DB::table('patients')
            ->whereNotNull('resident_no')->where('resident_no', '<>', '')
            ->whereNull('resident_no_enc')->count()
            + DB::table('prescriptions')
            ->whereNotNull('resident_no_ocr')->where('resident_no_ocr', '<>', '')
            ->whereNull('resident_no_ocr_enc')->count();

        if ($residual > 0) {
            throw new RuntimeException(
                "이관되지 않은 평문이 {$residual}건 남아 있습니다. rrn:backfill 을 먼저 완료하세요."
            );
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('resident_no');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('resident_no_ocr');
        });
    }

    public function down(): void
    {
        // 컬럼 구조만 되살린다. 값은 암호문에서 다시 채워야 한다.
        Schema::table('patients', function (Blueprint $table) {
            $table->string('resident_no', 20)->nullable()->after('name');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('resident_no_ocr', 255)->nullable()->after('patient_name_ocr');
        });
    }
};
