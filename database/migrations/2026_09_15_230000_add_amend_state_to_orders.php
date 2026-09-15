<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 정정을 「취소 후 재등록」으로 바꾸며 필요해진 자리 (2026-09-15 지시).
 *
 * 여태 정정은 so_update 로 **같은 판매주문을 제자리에서** 고쳤다. 그래서 창고가
 * 할당ㆍ피킹에 손을 댄 건은 저쪽이 거절했고(「이미 창고 작업이 시작된 주문」),
 * 우리 화면도 아예 단추를 잠갔다.
 *
 * 이제는 원 판매주문을 취소하고 새로 세운다. 할당ㆍ피킹이 걸린 건은 곧바로
 * 취소되지 않는다 — eud_cancel_yn='Y' 를 보내 두면 창고가 되돌리는 그 순간
 * 저쪽이 스스로 취소까지 잇고, 우리는 그 웹훅을 받아 재등록한다.
 *
 * 그 「기다리는 동안」을 담을 자리가 없었다.
 *
 *   amend_state    정정이 어디까지 갔는가 (null · requested)
 *   amend_payload  되돌려진 뒤 무엇으로 다시 세울 것인가 — 창고로 보낼 내용 그대로
 *   amend_*_at/by  언제 누가 청했는가
 *   so_no_history  갈아탄 판매번호들. 정정할 때마다 번호가 바뀌므로, 옛 번호를
 *                  남겨 두지 않으면 어느 판매주문이 이 주문이었는지 되짚을 수 없다
 *                  (2026-09-15 지시 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'amend_state')) {
                $table->string('amend_state', 20)->nullable()->after('cancel_done_at');
                $table->index('amend_state');
            }
            if (! Schema::hasColumn('orders', 'amend_payload')) {
                $table->json('amend_payload')->nullable()->after('amend_state');
            }
            if (! Schema::hasColumn('orders', 'amend_requested_at')) {
                $table->timestamp('amend_requested_at')->nullable()->after('amend_payload');
            }
            if (! Schema::hasColumn('orders', 'amend_requested_by')) {
                $table->unsignedBigInteger('amend_requested_by')->nullable()->after('amend_requested_at');
            }
            if (! Schema::hasColumn('orders', 'amend_note')) {
                $table->string('amend_note', 300)->nullable()->after('amend_requested_by');
            }
            if (! Schema::hasColumn('orders', 'withworks_so_no_history')) {
                $table->json('withworks_so_no_history')->nullable()->after('amend_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'amend_state', 'amend_payload', 'amend_requested_at',
                'amend_requested_by', 'amend_note', 'withworks_so_no_history',
            ] as $칸) {
                if (Schema::hasColumn('orders', $칸)) {
                    if ($칸 === 'amend_state') {
                        $table->dropIndex(['amend_state']);
                    }
                    $table->dropColumn($칸);
                }
            }
        });
    }
};
