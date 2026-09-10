<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 「영리목적의 광고성 정보 전송 동의」 칸 (2026-09-10 지시).
 *
 * 카테터 동의서를 기존 동의서와 같은 다섯 영역으로 맞추면서 하나가 남는다.
 * 나머지 넷은 이미 칸이 있다 — 장루에서 쓰던 것을 함께 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('privacy_consents', 'agree_ads')) {
            return;
        }

        Schema::table('privacy_consents', function (Blueprint $table) {
            $table->string('agree_ads', 12)->nullable()->after('agree_third_sensitive');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('privacy_consents', 'agree_ads')) {
            Schema::table('privacy_consents', function (Blueprint $table) {
                $table->dropColumn('agree_ads');
            });
        }
    }
};
