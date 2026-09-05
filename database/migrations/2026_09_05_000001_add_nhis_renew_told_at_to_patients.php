<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 공단 재등록을 언제 알렸는가.
 *
 * 기한 2주 전부터 담당자에게 알리는데(NhisRenewNotice), 하루 한 번 도는 일이라
 * 표를 남기지 않으면 같은 사람에게 날마다 같은 말을 한다. 그러면 담당자는
 * 알림을 읽지 않게 되고, 정작 놓치면 안 되는 것도 함께 묻힌다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('nhis_renew_told_at')->nullable()->after('nhis_renew_due');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('nhis_renew_told_at');
        });
    }
};
