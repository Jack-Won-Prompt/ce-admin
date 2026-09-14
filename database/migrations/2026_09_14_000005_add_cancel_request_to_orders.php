<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 취소 요청 (2026-09-14 지시).
 *
 * 창고로 넘긴 주문을 되돌리는 데는 두 가지 길이 있다.
 *
 *   출고가 아직 신규면   — 판매주문을 그 자리에서 취소하고 지운다. 한 번에 끝난다.
 *   할당ㆍ피킹이 걸렸으면 — 우리가 취소를 **요청**하고, 창고 담당자가 할당ㆍ피킹을
 *                          되돌리기를 기다린다. 그 되돌림이 끝나는 순간 위드웍스가
 *                          스스로 확정취소ㆍ삭제까지 잇는다.
 *
 * 뒤엣것은 며칠이 걸릴 수도 있다. 그 사이 이 주문이 「취소를 청해 둔 건」임을 화면이
 * 말해 주지 않으면, 다른 담당자가 결제 안내를 보내거나 제품을 고친다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cancel_state')) {
                /* requested  — 창고에 청해 두었다. 되돌림을 기다린다
                   cancelled  — 취소가 끝났다
                   rejected   — 창고가 되돌릴 수 없다고 했다 */
                $table->string('cancel_state', 20)->nullable()->after('status');
                $table->index('cancel_state');
            }
            if (! Schema::hasColumn('orders', 'cancel_requested_at')) {
                $table->timestamp('cancel_requested_at')->nullable()->after('cancel_state');
            }
            if (! Schema::hasColumn('orders', 'cancel_requested_by')) {
                $table->unsignedBigInteger('cancel_requested_by')->nullable()->after('cancel_requested_at');
            }
            if (! Schema::hasColumn('orders', 'cancel_reason')) {
                $table->string('cancel_reason', 200)->nullable()->after('cancel_requested_by');
            }
            if (! Schema::hasColumn('orders', 'cancel_done_at')) {
                $table->timestamp('cancel_done_at')->nullable()->after('cancel_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['cancel_done_at', 'cancel_reason', 'cancel_requested_by', 'cancel_requested_at'] as $c) {
                if (Schema::hasColumn('orders', $c)) {
                    $table->dropColumn($c);
                }
            }
            if (Schema::hasColumn('orders', 'cancel_state')) {
                $table->dropIndex(['cancel_state']);
                $table->dropColumn('cancel_state');
            }
        });
    }
};
