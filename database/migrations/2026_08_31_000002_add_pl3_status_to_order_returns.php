<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3PL 이 지금 무엇을 하고 있는가 (요청서 4쪽, 2026-08-31).
 *
 * 도착완료ㆍ검수중ㆍ검수완료ㆍ입고중ㆍ입고완료ㆍ출고중ㆍ출고완료 — 창고 안에서 벌어지는
 * 일이라 우리가 적을 수 없다. 창고가 알려 주는 것을 그대로 받아 적는다(회신: 위드웍스
 * 웹훅으로 받기).
 *
 * 우리 접수 단계(order_returns.status)와 따로 두는 까닭 — 그것은 「우리가 어디까지
 * 했는가」이고 이것은 「창고가 어디까지 했는가」다. 한 칸에 섞으면 담당자가 승인을
 * 눌러야 할 차례인지 창고를 기다릴 차례인지 갈리지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_returns', 'pl3_status')) {
                // 창고가 쓰는 코드 그대로. 무엇이 올지 우리가 정하지 않으므로 넉넉히 둔다.
                $table->string('pl3_status', 30)->nullable()->after('withworks_error');
            }
            if (!Schema::hasColumn('order_returns', 'pl3_status_label')) {
                $table->string('pl3_status_label', 50)->nullable()->after('pl3_status');
            }
            if (!Schema::hasColumn('order_returns', 'pl3_status_at')) {
                // 창고에서 그 일이 벌어진 시각. 우리가 받아 적은 시각이 아니다.
                $table->timestamp('pl3_status_at')->nullable()->after('pl3_status_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            foreach (['pl3_status_at', 'pl3_status_label', 'pl3_status'] as $c) {
                if (Schema::hasColumn('order_returns', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
