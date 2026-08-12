<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위임장 항목 좌표와 미성년자 보호자 서명.
 *
 * 좌표는 서명 하나만 화면에서 관리되고 나머지 열아홉 개는 코드에 박혀 있었다.
 * 양식이 조금만 달라져도 배포를 해야 했다.
 *
 * 미성년자는 위임을 혼자 할 수 없다. 법정대리인의 이름과 서명을 함께 받는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_settings', function (Blueprint $table) {
            // [key => ['x'=>, 'y'=>, 'size'=>]] — config/delegation.php 의 fields 를 덮어쓴다
            $table->json('field_positions')->nullable()->after('sig_w');
            $table->float('gsig_x')->nullable()->after('field_positions')->comment('보호자 서명 X (mm)');
            $table->float('gsig_y')->nullable()->after('gsig_x');
            $table->float('gsig_w')->nullable()->after('gsig_y');
        });

        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->boolean('is_minor')->default(false)->after('patient_mobile')
                  ->comment('서명 요청 시점의 만 나이로 판정');
            $table->date('patient_birth_date')->nullable()->after('is_minor');
            $table->string('guardian_name', 100)->nullable()->after('patient_birth_date');
            $table->string('guardian_relation', 50)->nullable()->after('guardian_name');
            $table->longText('guardian_signature_data')->nullable()->after('guardian_relation');
            // 신분증은 본문에 담지 않고 파일로 둔다. 공개 경로에 두지 않는다.
            $table->string('guardian_id_path', 255)->nullable()->after('guardian_signature_data');
            $table->string('guardian_id_mime', 60)->nullable()->after('guardian_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('delegation_settings', function (Blueprint $table) {
            $table->dropColumn(['field_positions', 'gsig_x', 'gsig_y', 'gsig_w']);
        });
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->dropColumn(['is_minor', 'patient_birth_date', 'guardian_name',
                                'guardian_relation', 'guardian_signature_data',
                                'guardian_id_path', 'guardian_id_mime']);
        });
    }
};
