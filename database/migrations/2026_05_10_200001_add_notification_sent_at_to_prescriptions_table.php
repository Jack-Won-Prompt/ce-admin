<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('kakao_sent_at')->nullable()->after('counseling_data');
            $table->timestamp('sms_sent_at')->nullable()->after('kakao_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['kakao_sent_at', 'sms_sent_at']);
        });
    }
};
