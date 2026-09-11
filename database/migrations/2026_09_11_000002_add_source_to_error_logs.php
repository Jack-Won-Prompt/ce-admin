<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 브라우저에서 난 오류도 같은 표에 담는다 (2026-09-11 지시).
 *
 * 화면이 조용히 망가지는 일은 서버 로그에 흔적이 남지 않는다 — 단추를 눌렀는데
 * 아무 일도 일어나지 않는 그 순간이 JS 오류다. 담당자가 「눌러도 안 돼요」라고
 * 말할 때 함께 볼 자리가 있어야 한다.
 *
 * 출처를 나누어 담되 표는 하나로 둔다. 두 자리로 갈리면 시각을 맞춰 견주기 어렵다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->string('source', 10)->default('server')->after('fingerprint')->index();
            $table->unsignedInteger('col')->nullable()->after('line');   // 브라우저는 열 번호까지 준다
        });
    }

    public function down(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->dropColumn(['source', 'col']);
        });
    }
};
