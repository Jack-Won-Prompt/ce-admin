<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 다시 올리기 요청에 「화면으로 알렸나」를 더한다 (2026-10-13 지시 · SR #175).
 *
 * 여태 알리는 길이 **앱 푸시 하나**였다. 그런데 요청을 받는 담당자들은 앱 토큰이
 * 없다 — 운영에서 요청 29건이 모두 「앱 알림 토큰이 없습니다」로 남았고, 사용자
 * 25명 가운데 토큰이 있는 사람은 둘뿐이다. 담당자는 웹으로 일한다.
 *
 * 그래서 화면 알림(Pusher)을 함께 보내고, 그것이 갔는지를 이 칸에 적는다.
 * `fcm_sent` 는 앱으로 간 것만 뜻하므로 거기에 섞어 적지 않는다 — 섞으면 뒤에
 * 「앱으로 갔나」를 물을 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescription_reupload_requests')) {
            return;
        }

        if (Schema::hasColumn('prescription_reupload_requests', 'web_sent')) {
            return;
        }

        Schema::table('prescription_reupload_requests', function (Blueprint $table) {
            $table->boolean('web_sent')->default(false)->after('fcm_error')
                  ->comment('화면 알림(Pusher)을 보냈는가');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('prescription_reupload_requests')
            && Schema::hasColumn('prescription_reupload_requests', 'web_sent')) {
            Schema::table('prescription_reupload_requests', function (Blueprint $table) {
                $table->dropColumn('web_sent');
            });
        }
    }
};
