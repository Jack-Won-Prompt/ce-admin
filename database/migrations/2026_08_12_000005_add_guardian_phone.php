<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 보호자 전화번호. 서명한 당사자와 연락이 닿아야 한다. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->string('guardian_phone', 40)->nullable()->after('guardian_birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->dropColumn('guardian_phone');
        });
    }
};
