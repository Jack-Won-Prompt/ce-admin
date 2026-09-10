<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 참고 사항 칸을 따로 둔다 — 주문 등록 화면 (2026-09-10 확인요청 5쪽).
 *
 * 여태 그 자리는 review_memo 를 읽기만 했다. 그런데 review_memo 는 검수자가 승인ㆍ반려
 * 하며 남기는 말이라, 담당자가 거기에 적어 두면 다음 승인 한 번에 지워진다. 한 칸에
 * 여럿을 담다 겪은 일이 이미 있다(2026-09-09 — 요청 메모ㆍ승인 메모ㆍ반려 사유).
 *
 * 그래서 칸을 따로 판다. 셋이 각자 자리를 갖는다:
 *   review_request_memo  담당자가 검수를 청하며 붙이는 말 (요청 전에만 적는다)
 *   review_memo          검수자가 승인ㆍ반려하며 남기는 말
 *   reference_note       이 건을 두고 오래 남겨 둘 말 — 언제든 고친다
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'reference_note')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->text('reference_note')->nullable()->after('review_memo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'reference_note')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropColumn('reference_note');
            });
        }
    }
};
