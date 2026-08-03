<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* 이 마이그레이션은 별도 lcpoint DB 의 orders 테이블을 고친다.
       DB 통합으로 그 커넥션이 사라졌으므로, 정의가 없으면 조용히 건너뛴다.
       (이미 적용된 이력이라 파일을 지우지 않고 무해화만 한다 —
        지우면 새 환경에서 migrate 가 '커넥션 없음' 으로 죽는다) */

    public function up(): void
    {
        if (!config('database.connections.lcpoint')) {
            return;
        }

        Schema::connection('lcpoint')->table('orders', function (Blueprint $table) {
            $table->string('withworks_status', 10)->nullable()->after('withworks_so_id');
            $table->string('withworks_status_label', 50)->nullable()->after('withworks_status');
            $table->timestamp('withworks_status_at')->nullable()->after('withworks_status_label');
        });
    }

    public function down(): void
    {
        if (!config('database.connections.lcpoint')) {
            return;
        }

        Schema::connection('lcpoint')->table('orders', function (Blueprint $table) {
            $table->dropColumn(['withworks_status', 'withworks_status_label', 'withworks_status_at']);
        });
    }
};
