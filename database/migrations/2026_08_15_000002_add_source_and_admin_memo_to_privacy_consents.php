<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 개인정보 동의 — 구분(수집 경로) · 관리자 메모.
 *
 * 화면의 '유형'은 카테터/장루라 동의를 어떻게 받았는지와는 다른 축이다. 지금은 모바일 폼으로만
 * 받지만 서면·전화로 받은 건을 옮겨 적는 일이 생기므로 값을 따로 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_consents', function (Blueprint $table) {
            $table->string('source', 20)->default('mobile')->after('type');
            $table->text('admin_memo')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_consents', function (Blueprint $table) {
            $table->dropColumn(['source', 'admin_memo']);
        });
    }
};
