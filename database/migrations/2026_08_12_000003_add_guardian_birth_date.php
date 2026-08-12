<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 보호자 생년월일. 담당자가 검수 화면에서 미리 적어 두거나, 보호자가 서명하며 적는다. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->date('guardian_birth_date')->nullable()->after('guardian_relation');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->dropColumn('guardian_birth_date');
        });
    }
};
