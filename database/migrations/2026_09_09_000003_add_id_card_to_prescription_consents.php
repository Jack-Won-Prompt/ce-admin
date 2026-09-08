<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 신분증만 따로 받는 링크.
 *
 * 위임동의 링크는 서명ㆍ개인정보 동의ㆍ신분증을 한자리에서 받는다. 그런데 신분증만
 * 빠진 채로 끝나는 건이 있어(2026-09-09 지시로 신분증 없이도 저장하게 했다),
 * 그 하나만 다시 받을 자리가 필요하다. 같은 표를 쓰되 kind 로 갈라 세운다 —
 * 토큰ㆍ만료ㆍ발송 내역이 이미 여기에 있어 새 표를 세울 까닭이 없다.
 *
 * 본인 신분증 칸도 함께 만든다. 여태 보호자 것만 받았는데, 신분증 링크는 둘 다 받는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_consents', 'kind')) {
                // delegation = 위임동의(여태 하던 것) · id_card = 신분증만
                $table->string('kind', 20)->default('delegation')->after('token');
            }
            if (! Schema::hasColumn('prescription_consents', 'patient_id_path')) {
                $table->string('patient_id_path')->nullable()->after('guardian_id_mime');
            }
            if (! Schema::hasColumn('prescription_consents', 'patient_id_mime')) {
                $table->string('patient_id_mime', 100)->nullable()->after('patient_id_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            foreach (['kind', 'patient_id_path', 'patient_id_mime'] as $c) {
                if (Schema::hasColumn('prescription_consents', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
