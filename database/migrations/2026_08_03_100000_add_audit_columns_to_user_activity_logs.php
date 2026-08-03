<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 감사로그 확장 — Gap Analysis P0-7 중 스키마 부분.
 *
 * P0-1(주민번호)이 "복호화 1회 = 감사로그 1행"을 성립시키려면 사유 코드를 적을 칸이
 * 먼저 있어야 하므로 함께 넣는다. 다운로드 사유 입력 UI 등 나머지 P0-7 은 별건이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->string('action', 40)->nullable()->after('type')
                  ->comment('view|export|unmask|update|delete');
            $table->string('target_type', 60)->nullable()->after('action');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->unsignedInteger('record_count')->nullable()->after('target_id')
                  ->comment('다운로드·조회 건수');
            $table->string('reason_code', 40)->nullable()->after('record_count')
                  ->comment('FR-036 다운로드·마스킹 해제 사유');
            $table->string('reason_text', 300)->nullable()->after('reason_code');
            $table->date('retention_until')->nullable()->after('reason_text')
                  ->comment('생성일 + 3년 (PIPC 고시 2023-6)');
            $table->index(['action', 'created_at'], 'ual_action_created_index');
        });

        // 배치·콘솔에서 발생한 시스템 행위는 사용자가 없다. 기록을 잃지 않도록 NULL 을 허용한다.
        DB::statement('ALTER TABLE `user_activity_logs` MODIFY `user_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropIndex('ual_action_created_index');
            $table->dropColumn([
                'action', 'target_type', 'target_id', 'record_count',
                'reason_code', 'reason_text', 'retention_until',
            ]);
        });
    }
};
