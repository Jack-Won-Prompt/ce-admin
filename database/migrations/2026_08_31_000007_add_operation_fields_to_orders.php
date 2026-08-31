<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operation 담당자ㆍ마감 체크ㆍ참고사항 (요청서 6ㆍ10ㆍ11ㆍ12쪽, 2026-08-31).
 *
 * 담당자 칸이 이미 둘 있는데도 새로 두는 까닭 — 재는 것이 다르다.
 *
 *   prescriptions.assigned_user_id  상담을 맡은 사람(Care team). 처방전에 붙는다.
 *   prescriptions.order_manager     주문 담당자. 자유 입력 글자다.
 *   orders.operation_user_id        발행ㆍ청구ㆍ정산을 맡은 사람(Consumer Operation)
 *
 * 절차서가 팀을 Care 와 Operation 으로 갈라 두었고(OrderReturn::STATUS_ACTORS), 요청서가
 * 네 쪽에 걸쳐 「Operation 담당자」를 따로 적어 달라 한다. 상담한 사람과 청구한 사람이
 * 다른 것이 예사라, 한 칸에 담으면 「누구에게 물어야 하나」가 갈리지 않는다.
 *
 * 사람 표에 걸어 둔다. 글자로 두면 같은 사람이 「김선미」와 「김 선미」로 갈리고, 그만둔
 * 사람의 이름이 영영 남는다.
 *
 * 마감 체크는 「이 건은 더 볼 것이 없다」는 표시다. 정산 상태(마감ㆍ확정ㆍ반려ㆍ보류ㆍ
 * 취소, 요청서 12쪽)와는 다른 것이라 따로 둔다 — 그쪽은 아직 정해지지 않았다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'operation_user_id')) {
                /* 그만둔 사람이 지워져도 주문은 남아야 한다 — 누가 맡았는지는 잃지만
                   주문을 잃는 것보다 낫다. 끊어만 둔다. */
                $table->foreignId('operation_user_id')->nullable()->after('created_by')
                      ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'closing_checked_at')) {
                // 누가 언제 보았는가 — 「확인했다」만 남기면 되물을 사람을 못 찾는다
                $table->timestamp('closing_checked_at')->nullable()->after('operation_user_id');
                $table->foreignId('closing_checked_by')->nullable()->after('closing_checked_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'reference_note')) {
                /* 참고사항 — 이 건에서 눈여겨볼 것. 주문의 note 와 다르다. 그것은 주문을
                   낼 때 적는 말이고, 이것은 청구ㆍ발행을 하며 남기는 말이다. */
                $table->string('reference_note', 500)->nullable()->after('closing_checked_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'reference_note')) {
                $table->dropColumn('reference_note');
            }
            foreach (['closing_checked_by', 'operation_user_id'] as $c) {
                if (Schema::hasColumn('orders', $c)) {
                    $table->dropConstrainedForeignId($c);
                }
            }
            if (Schema::hasColumn('orders', 'closing_checked_at')) {
                $table->dropColumn('closing_checked_at');
            }
        });
    }
};
