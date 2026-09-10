<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 서명 직전에 받은 최종 동의 세 줄 (2026-09-10 「서명 동의」).
 *
 * 문장 그대로 담는다. 나중에 화면 문구를 고쳐도, 그때 읽고 동의한 것은 그때 것이라야
 * 뒷날 되짚을 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescription_consents', 'final_agreements')) {
            return;
        }

        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->json('final_agreements')->nullable()->after('signature_data');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescription_consents', 'final_agreements')) {
            Schema::table('prescription_consents', function (Blueprint $table) {
                $table->dropColumn('final_agreements');
            });
        }
    }
};
