<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주민등록번호 암호화 컬럼 추가 — P0-1 1단계.
 *
 * 평문 컬럼은 아직 그대로 둔다. 이관 배치(rrn:backfill)와 코드 전환이 끝나고
 * 검증까지 통과한 뒤 별도 마이그레이션으로 제거한다. 그래야 무중단으로 넘어간다.
 *
 * 크기: Laravel 암호화 페이로드는 base64(JSON{iv,value,mac,tag}) 라 원문보다 훨씬 길다.
 * Gap Analysis 본문의 VARBINARY(255) 는 부족하여 512 로 잡는다(적용 지침의 정정과 동일).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->binary('resident_no_enc')->nullable()->after('name');
            $table->char('resident_no_hash', 64)->nullable()->after('resident_no_enc')
                  ->comment('HMAC-SHA256(정규화 RRN, pepper) 조회용');
            $table->string('resident_no_masked', 20)->nullable()->after('resident_no_hash')
                  ->comment('표시용 900101-1******');
            $table->string('rrn_purpose', 40)->nullable()
                  ->comment('처리근거: nhis_claim_form');
            $table->date('rrn_retention_basis_at')->nullable()
                  ->comment('기산점(최종 주문·청구일)');
            $table->date('rrn_retention_until')->nullable()
                  ->comment('폐기 예정일 = 기산점 + RoPA 기재 연수');
            $table->timestamp('rrn_destroyed_at')->nullable()
                  ->comment('폐기 배치가 실제로 지운 시각');
            $table->index('resident_no_hash', 'patients_resident_no_hash_index');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->binary('resident_no_ocr_enc')->nullable()->after('patient_name_ocr')
                  ->comment('OCR 원문 보존 — 정규화하지 않고 그대로 암호화');
            $table->string('resident_no_ocr_masked', 20)->nullable()->after('resident_no_ocr_enc');
        });

        // Laravel 의 binary() 는 BLOB 을 만든다. 문서가 지정한 VARBINARY(512) 로 맞춘다.
        DB::statement('ALTER TABLE `patients` MODIFY `resident_no_enc` VARBINARY(512) NULL');
        DB::statement('ALTER TABLE `prescriptions` MODIFY `resident_no_ocr_enc` VARBINARY(512) NULL');
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_resident_no_hash_index');
            $table->dropColumn([
                'resident_no_enc', 'resident_no_hash', 'resident_no_masked',
                'rrn_purpose', 'rrn_retention_basis_at', 'rrn_retention_until', 'rrn_destroyed_at',
            ]);
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['resident_no_ocr_enc', 'resident_no_ocr_masked']);
        });
    }
};
