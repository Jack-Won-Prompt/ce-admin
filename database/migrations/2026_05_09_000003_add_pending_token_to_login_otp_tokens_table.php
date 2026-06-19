<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_otp_tokens', function (Blueprint $table) {
            // 모바일 API 전용 stateless 임시 토큰 (UUID)
            $table->string('pending_token', 64)->nullable()->unique()->after('code');
            $table->index('pending_token');
        });
    }

    public function down(): void
    {
        Schema::table('login_otp_tokens', function (Blueprint $table) {
            $table->dropIndex(['pending_token']);
            $table->dropColumn('pending_token');
        });
    }
};
