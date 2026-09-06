<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 창고가 실물을 보고 적은 검수 비고 (2026-09-06 지시).
 *
 * 여태 창고에서 오는 것은 「어느 단계인가」뿐이었다(pl3_status). 정작 담당자가
 * 검수를 판단하는 근거 — **무엇을 보았는가** — 는 오지 않았다. 창고 담당자가
 * 입고 검수에 적은 말을 읽으려면 위드웍스 화면에 따로 들어가야 했고, 그래서
 * 대개 읽지 않은 채로 검수 확정을 눌렀다.
 *
 * 상태와 따로 둔다. 비고는 상태를 바꾸지 않고 고쳐 적히는 일이 잦아,
 * 언제 적힌 말인지가 함께 있어야 담당자가 최신인지 안다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->text('pl3_note')->nullable()->after('pl3_status_at');
            $table->timestamp('pl3_note_at')->nullable()->after('pl3_note');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn(['pl3_note', 'pl3_note_at']);
        });
    }
};
