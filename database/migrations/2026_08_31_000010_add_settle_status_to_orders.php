<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 정산 상태 (요청서 12쪽, 2026-08-31 회신 A).
 *
 * 「마감」은 셈을 닫은 것이고 「확정」은 그 뒤 되돌릴 수 없게 잠근 것이다. 둘을 가르는
 * 까닭 — 닫고 나서도 며칠은 고칠 일이 생긴다. 잠그는 것을 따로 두지 않으면 담당자가
 * 닫기를 미루게 되고, 그러면 어디까지 봤는지가 영영 안 남는다.
 *
 *   진행중 → 마감 → 확정
 *              ↘ 반려ㆍ보류ㆍ취소
 *
 * 못 받은 돈이 있어도 닫을 수 있어야 한다(요청서 12쪽 — 「간혹 환자 또는 관할청구처에서
 * 입금 받지 못할 경우, 마감 확정해야 하는 경우도 있어야 함」). 그래서 사유를 함께 둔다 —
 * 왜 못 받은 채로 닫았는지가 남지 않으면 나중에 그 건을 다시 들출 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'settle_status')) {
                /* 처음 값은 진행중이다. 쌓인 건을 마감으로 올리지 않는다 — 아무도 보지
                   않은 건이 「닫혔다」고 서 있으면 그 숫자를 믿을 수 없게 된다. */
                $table->enum('settle_status',
                    ['open', 'closed', 'confirmed', 'rejected', 'on_hold', 'cancelled'])
                    ->default('open')->after('reference_note');
            }
            if (!Schema::hasColumn('orders', 'settle_status_at')) {
                $table->timestamp('settle_status_at')->nullable()->after('settle_status');
                $table->foreignId('settle_status_by')->nullable()->after('settle_status_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'settle_reason')) {
                // 왜 그 상태로 옮겼는가 — 못 받은 채로 닫은 건은 이것이 없으면 다시 못 들춘다
                $table->string('settle_reason', 300)->nullable()->after('settle_status_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'settle_reason')) {
                $table->dropColumn('settle_reason');
            }
            if (Schema::hasColumn('orders', 'settle_status_by')) {
                $table->dropConstrainedForeignId('settle_status_by');
            }
            foreach (['settle_status_at', 'settle_status'] as $c) {
                if (Schema::hasColumn('orders', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
